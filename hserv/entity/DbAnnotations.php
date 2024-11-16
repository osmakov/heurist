<?php
namespace hserv\entity;

use hserv\entity\DbEntityBase;
use hserv\entity\DbRecUploadedFiles;
use hserv\utilities\USanitize;

    /**
    * dbAnnotations
    *
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

require_once dirname(__FILE__).'/../structure/import/dbsImport.php';

class DbAnnotations extends DbEntityBase
{
    private $dtyAnnotationInfo;


    public function __construct( $system, $data=null ) {
        $this->system = $system;
        $this->data = $data;

        $this->system->defineConstant('RT_MAP_ANNOTATION');
        $this->system->defineConstant('DT_NAME');
        $this->system->defineConstant('DT_URL');
        $this->system->defineConstant('DT_DATE');
        $this->system->defineConstant('DT_ORIGINAL_RECORD_ID');
        $this->system->defineConstant('DT_ANNOTATION_INFO');
        $this->system->defineConstant('DT_EXTENDED_DESCRIPTION');
        $this->system->defineConstant('DT_MEDIA_RESOURCE');

        $this->system->defineConstant('DT_SHORT_SUMMARY');
        $this->system->defineConstant('DT_THUMBNAIL');
        $this->system->defineConstant('DT_FILE_RESOURCE');


        $this->dtyAnnotationInfo = (defined('DT_ANNOTATION_INFO'))
                ? DT_ANNOTATION_INFO
                : 0;

    }

    public function isvalid(){
        return true;
    }

    /**
    *  Search all annotaions for given uri (IIIF manifest)
    *  or particular annotaion id
    *
    *  Mirador requests our Annotation server (via api/annotations) for annotations per page(canvas).
    */
    public function search(){

        if($this->data['recID']=='edit'){

            $recordId = $this->findRecIDbyUUID($this->data['uuid']);
            if($recordId>0){
                $redirect = HEURIST_BASE_URL.'/hclient/framecontent/recordEdit.php?db='.HEURIST_DBNAME.'&fmt=edit&recID='.$recordId;
                redirectURL($redirect);
            }
            exit;
        }


        $sjson = array('id'=>"https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]",
                        'type' => 'AnnotationPage');

        $sjson['items'] = array();

        //find all annotation for given uri
        if($this->data['recID']!='pages'){
            $item = $this->findItembyUUID($this->data['recID']);
            if($item!=null){
                $sjson['items'] = array(json_decode($item, true));
            }
            return $sjson;
        }

        if(!@$this->data['uri']){
            $params = USanitize::sanitizeInputArray();
            $this->data['uri'] = @$params['uri'];
        }
        $uri = $this->data['uri'];
        $items = $this->findItemsByCanvas($uri);
        if(isEmptyArray($items)){
            return $sjson;
        }

        foreach($items as $item){
            $anno = json_decode($item, true);
            if($anno && $anno['type']=='Annotation'){ //only WebAnnotations
                $sjson['items'][] = $anno;
            }
        }

        return $sjson;
    }

    //
    // returns Annotation description by Canvas URI
    //
    private function findItemsByCanvas($canvasUri){
        if($this->dtyAnnotationInfo>0 && defined('DT_URL')){
            $query = 'SELECT d2.dtl_Value FROM recDetails d1, recDetails d2 WHERE '
            .'d1.dtl_DetailTypeID='.DT_URL .' AND d1.dtl_Value="'.$canvasUri.'"'
            .' AND d1.dtl_RecID=d2.dtl_RecID'
            .' AND d2.dtl_DetailTypeID='.$this->dtyAnnotationInfo;
            return mysql__select_list2($this->system->getMysqli(), $query);
        }else{
            return array();
        }
    }

    //
    //
    //
    private function findItembyUUID($uuid){
        if($this->dtyAnnotationInfo>0 && defined('DT_ORIGINAL_RECORD_ID')){
            $query = 'SELECT d2.dtl_Value FROM recDetails d1, recDetails d2 WHERE '
            .'d1.dtl_DetailTypeID='.DT_ORIGINAL_RECORD_ID .' AND d1.dtl_Value="'.$uuid.'"'
            .' AND d1.dtl_RecID=d2.dtl_RecID'
            .' AND d2.dtl_DetailTypeID='.$this->dtyAnnotationInfo;
            return mysql__select_value($this->system->getMysqli(), $query);
        }else{
            return array();
        }
    }

    //
    //
    //
    private function findRecIDbyUUID($uuid){
        if(defined('DT_ORIGINAL_RECORD_ID')){
            $query = 'SELECT dtl_RecID FROM recDetails WHERE dtl_DetailTypeID='.DT_ORIGINAL_RECORD_ID.' AND dtl_Value="'.$uuid.'"';
            $recordId = mysql__select_value($this->system->getMysqli(), $query);
        }
        if(!$recordId){
            $recordId = 0;
        }
        return $recordId;
    }

    //
    //
    //
    public function delete($disable_foreign_checks = false){

        if($this->data['recID']){  //annotation UUID

            //validate permission for current user and set of records see $this->recordIDs
            if(!$this->_validatePermission()){
                return false;
            }

            //remove annotation with given ID
            $recordId = $this->findRecIDbyUUID($this->data['recID']);
            if($recordId>0){
                return recordDelete($this->system, $recordId);
            }

            $this->system->addError(HEURIST_NOT_FOUND, 'Annotation record to be deleted not found');

        }else{
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid annotation identificator');
        }

        return false;
    }

    //
    //
    //
    private function assignField(&$details, $id, $value){

        //field id
        if(intval($id)>0){
            $id = intval($id);
        }elseif(defined($id)){
            $id = constant($id);
        }

        $was_changed = false;

        if(intval($id)>0){
            if(@$details[$id]){
                //already exist
                if(array_search($value, $details[$id])===false){
                    $details[$id][] = $value;
                    $was_changed = true;
                }
            }else{
                //add new field
                $details[$id] = array($value);
                $was_changed = true;
            }
        }

        return $was_changed;
    }

    private function checkRequiredDefintions(){

        if(!defined('RT_MAP_ANNOTATION')){
            //import missed record type
            $importDef = new \DbsImport( $this->system );
            $isOK = $importDef->checkAndImportRty('2-101');

            if(!$isOK){
                $this->system->addError(HEURIST_ACTION_BLOCKED,
                    'Can not add annotation. This database does not have "Map/Image Annotation" record type. '
                    .'Import required record type');
                return false;
            }

            //redefine constants
            $this->system->defineConstant('RT_MAP_ANNOTATION', true);
            $this->system->defineConstant('DT_ORIGINAL_RECORD_ID', true);
            $this->system->defineConstant('DT_ANNOTATION_INFO', true);
        }

        if( !defined('DT_ANNOTATION_INFO') || !defined('DT_ORIGINAL_RECORD_ID')){
            $this->system->addError(HEURIST_ACTION_BLOCKED,
                    'Can not add annotation. This database does not have "Annotation" (2-1098) or "Original ID" fields (2-36). '
                    .'Import record type "Map/Image Annotation" to get this field');
            return false;
        }

        return true;
    }

    //
    // see similar in importAction
    // to implement - make general function
    //
    private function findOriginalRecord($recordId, &$details){

        if(!$recordId){
            return;
        }

        $query = "SELECT dtl_Id, dtl_DetailTypeID, dtl_Value, ST_asWKT(dtl_Geo), dtl_UploadedFileID FROM recDetails WHERE dtl_RecID=$recordId ORDER BY dtl_DetailTypeID";
        $dets = mysql__select_all($this->system->getMysqli(), $query);
        if(!$dets){
            return;
        }

        foreach ($dets as $row){
            //uniuque dtl_ID $bd_id = $row[0];
            $field_type = $row[1];
            if($row[4]){ //dtl_UploadedFileID
                $value = $row[4];
            }elseif($row[3]){ //geo
                $value = $row[2].' '.$row[3];
            }else{
                $value = $row[2];
            }
            $details[$field_type][] = $value; //"t:"
        }

    }

    //
    //
    //
    private function getAnnotationId($anno){

        $anno_uid = $this->removeUriSchema($this->isOpenAnnotation($anno)?@$anno['@id']:@$anno['uuid']);
        if(!$anno_uid){
            $this->system->addError(HEURIST_ACTION_BLOCKED,
                    'Can not add annotation. Anotation UUID is not found');
            return false;
        }
        return $anno_uid;
    }

    //
    //
    //
    public function save($createThumbnail=true, $ulf_ID=0){


        $anno = $this->data['fields']['annotation'];
        $anno_uid = $this->getAnnotationId($anno);

        //validate permission for current user and set of records see $this->recordIDs
        if(!($this->_validatePermission() &&
             $this->checkRequiredDefintions() &&
             $anno_uid
           )){
            return false;
        }
/*
        annotation: {
          canvas: this.canvasId,
          data: JSON.stringify(annotation),
          uuid: annotation.id,
        },
*/
        $this->dtyAnnotationInfo = DT_ANNOTATION_INFO;

        $recordId = 0;
        $details = array();

        if(!$this->is_addition){
            //find record id by annotation UID
            $recordId = $this->findRecIDbyUUID($anno_uid);
            //already exists
            $this->findOriginalRecord($recordId, $details);
        }

        $sourceRecordId = $this->data['fields']['sourceRecordId']??@$anno['sourceRecordId'];
        $manifestUrl = $this->data['fields']['manifestUrl']??@$anno['manifestUrl'];

        //get values from annotation and add/replace values in current record
        $was_changed = $this->parseAnnotation($details, $anno, $createThumbnail, $sourceRecordId, $manifestUrl);
        if(!$details[DT_NAME]){
            $this->system->addError(HEURIST_ACTION_BLOCKED,
                    'Can not add annotation. Anotation text is not found');
            return false;
        }

        if($ulf_ID>0){
            //link annotation record to registered manifest
            $this->assignField($details, 'DT_FILE_RESOURCE', $ulf_ID);

        }elseif($sourceRecordId>0 && $recordId!=$sourceRecordId){
            //link referenced image record with annotation record
            $this->assignField($details, 'DT_MEDIA_RESOURCE', $sourceRecordId);
        }


        if($was_changed){
            //record header
            $record = array();
            $record['ID'] = $recordId;
            $record['RecTypeID'] = RT_MAP_ANNOTATION;
            $record['no_validation'] = true;
            $record['details'] = $details;

            $out = recordSave($this->system, $record, false, true);
            if(is_array($out) && $out['data']>0){
                $out['is_new'] = $recordId == 0;
            }
        }elseif($recordId>0){
            $out = array('status'=>HEURIST_OK, 'data'=>$recordId, 'is_retained'=>true);
        }else{
            $this->system->addError(HEURIST_ACTION_BLOCKED,
                    'Can not add annotation. Anotation data is not valid');
            $out = false;
        }

        return $out;
    }

    //
    //
    //
    private function parseAnnotation(&$details, $anno, $createThumbnail, $sourceRecordId, $manifestUrl){

        if($this->isOpenAnnotation($anno)){
            //Open Annotation
            $was_changed = $this->parseOpenAnnotation($details, $anno, $createThumbnail, $sourceRecordId, $manifestUrl);
        }elseif(@$anno['data']){
            //WebAnnotation
            $was_changed = $this->parseWebAnnotation($details, $anno, $createThumbnail);
        }
        return $was_changed;
    }


    /*
            Web Annotation
            Sample:

            sourceRecordId:15
            manifestUrl: http://127.0.0.1//h6-alpha/?db=iiif_import&file=3c6a9074ce8037cb5ec4da4cc1a2d0a63deacb65
            canvas: http://8f74dd58-ab81-4d0c-8003-28d1d008f3db
            data:{
                body:{type:"TextualBody",
                      value:"text"},
                id:"d6a3f2a3-c8bc-48ba-abbe-f6bfb4a6d30f",
                motivation:"commenting",
                target:{
                    source:"http://8f74dd58-ab81-4d0c-8003-28d1d008f3db",
                    selector:[
                          {type:"FragmentSelector",
                          value:"xywh=2791,79,307,326"},
                          {type:"SvgSelector",
                          value:"<svg xmlns='http://www.w3.org/2000/svg'><path xmlns=\\\"http://www.w3.org/2000/svg\\\" d=\\\"M2791.08071,405.81478v-326.10117h307.98444v326.10117z\\\" data-paper-data=\\\"{&quot;state&quot;:null}\\\" fill=\\\"none\\\" fill-rule=\\\"nonzero\\\" stroke=\\\"#00bfff\\\" stroke-width=\\\"1\\\" stroke-linecap=\\\"butt\\\" stroke-linejoin=\\\"miter\\\" stroke-miterlimit=\\\"10\\\" stroke-dasharray=\\\"\\\" stroke-dashoffset=\\\"0\\\" font-family=\\\"none\\\" font-weight=\\\"none\\\" font-size=\\\"none\\\" text-anchor=\\\"none\\\" style=\\\"mix-blend-mode: normal\\\"/></svg>"
                          }]
                    },
                type: "Annotation"}
            uuid: "d6a3f2a3-c8bc-48ba-abbe-f6bfb4a6d30f"
    */
    private function parseWebAnnotation(&$details, $anno, $createThumbnail){

        //"body":{"type":"TextualBody","value":"<p>RR Station</p>"},
        $anno_dec = json_decode($anno['data'], true);

        if(! (is_array($anno_dec) && //invalid annotation data
             $this->assignField($details, $this->dtyAnnotationInfo, $anno['data']))){//not changed
            return false;
        }

        if(@$anno_dec['body']['type']=='TextualBody'){
            $this->assignField($details, 'DT_NAME', substr(strip_tags($anno_dec['body']['value']),0,50));
            $this->assignField($details, 'DT_SHORT_SUMMARY', $anno_dec['body']['value']);
        }

        $this->assignField($details, 'DT_ORIGINAL_RECORD_ID', $anno['uuid']);

        // "selector":[{"type":"FragmentSelector","value":"xywh=524,358,396,445"}
        //canvas defined on addition only
        if(@$anno['canvas']){
            //url to page/canvas
            $details[DT_URL][] = $anno['canvas'];

        }elseif($this->is_addition && @$anno_dec['target']['source']){ //page is not changed on edit
                $this->assignField($details, 'DT_URL', $anno_dec['target']['source']); //canvas url
        }


        //at the moment it creates thumbnail on addition only
        // recreate thumbnail if annotated area is changed
        if(!($createThumbnail && is_array(@$anno_dec['target']) && @$anno_dec['target']['selector'] && defined('DT_THUMBNAIL'))){
            return true;
        }

        foreach ($anno_dec['target']['selector'] as $selector){
            if(@$selector['type']=='FragmentSelector'){
                $region = @$selector['value'];

                $thumb_id = $this->getAnnotationImage($anno['manifestUrl'], $anno['uuid'], $region, $anno['canvas']);
                if($thumb_id>0){
                    $this->assignField($details, 'DT_THUMBNAIL', $thumb_id);
                }
            }
        }

        return true;
    }

    /**
    * remove http:// schema
    *
    * @param mixed $val
    */
    private function removeUriSchema($val){
        if($val && strpos($val, 'http://')!==false){
            $val = substr($val, 7);
        }
        return $val;
    }

    /* Sample:
            "resource": [
                {
                    "full_text": "Raoult, Henriette",
                    "@type": "dctypes:Text",
                    "format": "text/html",
                    "chars": "<p>Raoult, Henriette</p>"
                }
            ],
            "@type": "oa:Annotation",
            "dcterms:creator": "Société <a href=\"https://teklia.com/fr/\" target=\"_blank\">TEKLIA</a> pour le projet <a href=\"https://www.collexpersee.eu/projet/pret19/\" target=\"_blank\">CollEx-Persée PRET19</a>",
            "motivation": [
                "oa:commenting"
            ],
            "dcterms:created": "2024-09-30T13:15:16",
            "@id": "http://12b549a1-7dce-475f-8156-838bf83f4c5d",
            "dcterms:modified": "2024-09-30T13:15:16",
            "@context": "file:/usr/local/tomcat/webapps/ROOT/contexts/iiif-2.0.json",
            "on": [
                {
                    "@type": "oa:SpecificResource",
                    "selector": {
                        "default": {
                            "@type": "oa:FragmentSelector",
                            "value": "xywh=252.0,2935.0,2599.0,1417.0"
                        },
                        "item": {
                            "@type": "oa:SvgSelector",
                            "value": "<svg xmlns=\"http://www.w3.org/2000/svg\"><path fill-rule=\"evenodd\" stroke-miterlimit=\"10\" fill=\"#66cc99\" stroke=\"#008000\" stroke-width=\"3.0\" fill-opacity=\"0\" opacity=\"1\" d=\"M 252.0,2935.0 L 252.0,4352.0 L 2851.0,4352.0 L 2851.0,2935.0 L 252.0,2935.0 z\" /></svg>"
                        },
                        "@type": "oa:Choice"
                    },
                    "full": "http://1b1dd10d-4bef-464e-b3c0-d30b90763b32"
                }
            ]
    */

    private function isOpenAnnotation($anno){
       return @$anno['@type']=='oa:Annotation';
    }


    private function parseOpenAnnotation(&$details, $anno, $createThumbnail, $sourceRecordId, $manifestUrl){

        $anno_uid = $this->removeUriSchema(@$anno['@id']);

        if(! ($this->isOpenAnnotation($anno) &&
              $anno_uid &&
              $this->assignField($details, $this->dtyAnnotationInfo, json_encode($anno)))) //annotation is not changed
        {
            return false;
        }

        $value = $anno['resource'][0]['full_text'];
        $this->assignField($details, 'DT_NAME', substr(strip_tags($value),0,50));
        $value = $anno['resource'][0]['chars'];
        $this->assignField($details, 'DT_SHORT_SUMMARY', $value);

        $this->assignField($details, 'DT_ORIGINAL_RECORD_ID', $anno_uid);

        if(@$anno['dcterms:modified']){
            $this->assignField($details, 'DT_DATE', $anno['dcterms:modified']);
        }

        if(is_array(@$anno['on'])){

            foreach($anno['on'] as $target){

                $canvas_url = @$target['full'];
                $this->assignField($details, 'DT_URL', $canvas_url); //canvas url

                $fragment = @$target['selector']['default']['value'];

                if($fragment && defined('DT_THUMBNAIL') && $createThumbnail)
                {
                        $thumb_id = $this->getAnnotationImage($manifestUrl, $anno_uid, $fragment, $canvas_url);
                        if($thumb_id>0){
                            $this->assignField($details, 'DT_THUMBNAIL', $thumb_id);
                        }
                }

            }
        }

        return true;
    }


    private function extractImageUrlFromCanvas($canvas, $url) {
        if($canvas['@id']!=$url || !is_array(@$canvas['images'])){
            return null;
        }
        foreach($canvas['images'] as $image){
            $url2 = @$image['resource']['service']['@id'];
            if($url2!=null) {
                return $url2;
            }
        }
        return null;
    }


    private function getImageUrlV2($iiif_manifest, $url){

        if(!is_array(@$iiif_manifest['sequences'])){
            return null;
        }

        foreach($iiif_manifest['sequences'] as $seq){
            if(is_array(@$seq['canvases'])){
                foreach($seq['canvases'] as $canvas){
                    $url2 = $this->extractImageUrlFromCanvas($canvas, $url);
                    if($url2!=null) {
                        return $url2;
                    }
                }
            }
        }
        //not found
        return null;
    }


    private function extractImageUrlFromAnnotationPage($annot_page) {

        if(@$annot_page['type']=='AnnotationPage' && is_array(@$annot_page['items']))
        {
            foreach($annot_page['items'] as $annot){
                if(@$annot['type']=='Annotation'
                && @$annot['body']['type']=='Image')
                {
                    $url2 = @$annot['body']['service']['id'];
                    if($url2!=null) {
                        return $url2;
                    }
                }
            }
        }
        return null;
    }

    private function getImageUrlV3($iiif_manifest, $url){

        if(!is_array(@$iiif_manifest['items'])){
            return $url;
        }

        foreach($iiif_manifest['items'] as $canvas){
            if(@$canvas['type']=='Canvas' && $canvas['id']==$url && is_array(@$canvas['items'])){
                foreach($canvas['items'] as $annot_page){
                    $url2 = $this->extractImageUrlFromAnnotationPage($annot_page);
                    if($url2!=null) {
                        return $url2;
                    }
                }
            }
        }

        //not found
        return $url;
    }

    private function getAnnotationImage($manifestUrl, $anno_uid, $region, $canvas_url){

        if(!$region){
            return 0;
        }
            $region = substr($region, 5);

            // https://fragmentarium.ms/metadata/iiif/F-hsd6/canvas/F-hsd6/fol_2r.jp2.json
            // https://gallica.bnf.fr/iiif/ark:/12148/bpt6k9604118j/canvas/f11/
            $url = $canvas_url;

            if($manifestUrl){ //target manifest url
                //find image service uri by canvas in manifest
                $iiif_manifest_url = filter_var($manifestUrl, FILTER_SANITIZE_URL);
                $iiif_manifest = loadRemoteURLContent($iiif_manifest_url);//retrieve iiif manifest into manifest
                $iiif_manifest = json_decode($iiif_manifest, true);
                if($iiif_manifest!==false && is_array($iiif_manifest)){

                    //"@context": "http://iiif.io/api/presentation/2/context.json"
                    //sequences->canvases->images->resource->service->@id
                    $context_url = 'http'.'://iiif.io/api/presentation/2/context.json';

                    if(@$iiif_manifest['@context']==$context_url){

                        $url = $this->getImageUrlV2($iiif_manifest, $url);

                    }else{ //version 3
                        //"@context": "http://iiif.io/api/presentation/3/context.json"
                        //items(type:Canvas)->items[AnnotationPage]->items[Annotation]->body->service[0]->id

                        $url = $this->getImageUrlV3($iiif_manifest, $url);
                    }

                }
            }

            if(strpos($url, '/canvas/')>0){
                //remove /canvas to get image url
                $url = str_replace('/canvas/','/',$url);
            }
            // {scheme}://{server}{/prefix}/{identifier}/{region}/{size}/{rotation}/{quality}.{format}
            $url = $url.'/'.$region.'/!200,200/0/default.jpg';

            $tmp_file = HEURIST_SCRATCH_DIR.'/'.basename($anno_uid.'.jpg');//basename for snyk
            //tempnam(HEURIST_SCRATCH_DIR,'iiif_thumb');
            //tempnam()
            $res = saveURLasFile($url, $tmp_file);

            if($res>0){
                $entity = new DbRecUploadedFiles($this->system);

                $dtl_UploadedFileID = $entity->registerFile($tmp_file, null);//it returns ulf_ID

                if($dtl_UploadedFileID===false){
                    $err_msg = $this->system->getError();
                    $err_msg = $err_msg['message'];
                    $this->system->clearError();
                }else{
                    return $dtl_UploadedFileID[0];
                }
            }
    }
}

