<?php

/**
* ImportAnnotations.php
* 1. Loops a) all external resources with type "_iiif"
*          b) "_iiif" files linked to the specified set of records
* 2. Detect that remote url is iiif manifest
* 3. Downloads manifest, extract annontaion info
* 4. Add or update heurist record type:Annotation
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
namespace hserv\records\import;
use hserv\utilities\USanitize;
use hserv\entity\DbAnnotations;
use hserv\entity\DbRecUploadedFiles;

require_once dirname(__FILE__).'/../edit/recordModify.php';

set_time_limit(0);

class ImportAnnotations{

    private $system;

    //private $recIDs; // record ids to check manifest
    private $ulfIDs; // files ids to check manifest

    private $progressSessionId = 0;

    private $createThumbnail = false;

    private $linkAnnotationWithManifest = false;


    public function __construct( $system, $params = null ) {
        $this->system = $system;

        $this->ulfIDs = @$params['ids'];

        $this->progressSessionId = @$params['session'];

        $this->createThumbnail = @$params['create_thumb']==1;
        $this->linkAnnotationWithManifest = @$params['direct_link']==1;

    }

    /**
    * Finds registered manifests in recUploadedFiles
    */
    private function findRegisteredManifests(){

        $mysqli = $this->system->get_mysqli();
        $query = 'SELECT ulf_ID, ulf_ExternalFileReference FROM recUploadedFiles WHERE ulf_OrigFileName="'.ULF_IIIF.'"';

        if(!empty($this->ulfIDs)){
            $ids = prepareStrIds($this->ulfIDs);
            if(!empty($ids)){
                if(count($ids)==1){
                    $query = $query . ' AND ulf_ObfuscatedFileID='.$ids[0];
                }else{
                    $query = $query . ' AND ('.implode(' OR ulf_ObfuscatedFileID =',$ids).')';
                }
            }
        }

        return mysql__select_assoc2($mysqli, $query);

    }


    /**
    * Return array off annotations per given url
    *
    * @param mixed $url
    */
    private function processManifest( $url ){

        $annotations = null;

        $iiif_manifest = loadRemoteURLContent($url);//check that json is iiif manifest

        if(!$iiif_manifest){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Manifest file '.$url.' is not accessible');
            return false;
        }

        $iiif_manifest = json_decode($iiif_manifest, true);
        // Check if the JSON was decoded successfully
        if($iiif_manifest!==false && is_array($iiif_manifest))
        {
            // Check if the content is valid
            if(@$iiif_manifest['@type']=='sc:Manifest' ||   //v2
                @$iiif_manifest['type']=='Manifest')        //v3
            {
                $annotations = $this->getIiifAnnotationList($iiif_manifest);

            }elseif( $this->isAnnotationList($iiif_manifest) ||   //v2
                    @$iiif_manifest['type']=='AnnotationList')        //v3
            {
                $annotations = $iiif_manifest['resources'] ?? [];
            }

        }else{
            $msg = '';
            if (json_last_error() !== JSON_ERROR_NONE) {
                    $msg = json_last_error_msg();
            }
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Manifest file is not valid. '.$msg);
            return false;
        }


        return $annotations;
    }

    //
    //
    //
    private function isAnnotationList($ele){
        return @$ele['@type']=='sc:AnnotationList';
    }

    //
    //
    //
    private function getIiifAnnotationList($iiif_manifest){

        //find annoatations in sequences->[canvases->[otherContent->["@type": "sc:AnnotationList"]
        $annotations = array();

        foreach($iiif_manifest['sequences'] as $seq){
            foreach($seq['canvases'] as $canvas){
                foreach($canvas['otherContent'] as $annoList){
                    if($this->isAnnotationList($annoList)){
                        $annotations = $this->processManifest(@$annoList['@id']);
                    }
                }
            }
        }

        return $annotations;
    }

    private function prepareExecution(){

        //must be database manager
        if(!$this->system->is_admin()){
            $this->system->addError(HEURIST_REQUEST_DENIED, 'To perform this action you must be logged in as Administrator of group \'Database Managers\'');
            return false;
        }

        //finds manifests
        $urls = $this->findRegisteredManifests();

        if(empty($urls)){
            $urls = array('total'=>0);
        }
        return $urls;
    }

    public function execute(){

        $urls = prepareExecution();

        if(!$urls || @$urls['total']==0 ){
            return $urls;
        }

        $tot_count = count($urls);

        if($this->progressSessionId){
            //init progress session
            mysql__update_progress(null, $this->progressSessionId, true, '0,'.$tot_count);
        }


        $dbAnno = new DbAnnotations($this->system);
        $dbUlf  = new DbRecUploadedFiles($this->system);

        $result = array('total'=>$tot_count,
                         'processed'=>0,
                         'missed'=>0,
                         'added'=>array(),
                         'updated'=>array(),
                         'retained'=>array(),
                         'without_annotations'=>array(),
                         'issues'=>array()
                         );

        //loop manifests
        foreach($urls as $ulf_ID=>$manifest_url){

            $annotations = $this->processManifest($manifest_url);
            $result['processed']++;

            //find linked records
            $rec_ids = $dbUlf->getMediaRecords($ulf_ID, 'file_fields', 'rec_ids');

            $source_rec_id = $rec_ids?$rec_ids[0]:0;
            if($source_rec_id==0){
                continue;
            }

            if($annotations===false){
                $result['missed']++;
                $err_msg = $this->system->getError();
                $issues[$source_rec_id] = $err_msg['message'];
                $this->system->clearError();
                continue;
            }

            if(empty($annotations)){
                $result['without_annotations'][$ulf_ID] = $source_rec_id;
                continue;
            }

            foreach ($annotations as $anno){

                $dbAnno->setData(array('fields'=>array('annotation'=>$anno, 'sourceRecordId'=>$source_rec_id, 'manifestUrl'=>$manifest_url)));
                $res = $dbAnno->save($this->createThumbnail, $this->linkAnnotationWithManifest?$ulf_ID:0);

                $this->processResult($result, $res, $source_rec_id);
            }

            if($this->progressSessionId && $cnt_processed % 5 == 0){
                $current_val = mysql__update_progress(null, $this->progressSessionId, true, $cnt_processed.','.$tot_count);
                if($current_val && $current_val=='terminate'){
                    $this->system->addError(HEURIST_ACTION_BLOCKED, 'Operation is terminated by user');
                    return false;
                }
            }

        }//for

        if($this->progressSessionId){
            //remove session file
            mysql__update_progress(null, $this->progressSessionId, false, 'REMOVE');
        }

        return $result;
    }

    //
    //
    //
    private function processResult(&$result, $res, $source_rec_id)
    {
        if($res===false){

            $err_msg = $this->system->getError();
            $result['issues'][$source_rec_id] = $err_msg['message'];
            $this->system->clearError();

        }elseif(is_array($res) && $res['status']!=HEURIST_OK){

            $result['issues'][$source_rec_id] = $res['message'];

        }else{

            $rec_id = $res['data'];
            if(@$res['is_new']){
                $result['added'][] = $rec_id;
            }elseif(@$res['is_retained']){
                if(!in_array($rec_id, $recids_added)){
                    $result['retained'][] = $rec_id;
                }
            }else{
                $result['updated'][] = $rec_id;
            }
        }
    }

}
