<?php

/**
* ImportHeurist.php - import records and definitions from Heurist exchange json or xml file
* (see user interface in importController.php)
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
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

use hserv\utilities\DbUtils;
use hserv\utilities\USanitize;
use hserv\entity\DbRecUploadedFiles;

require_once dirname(__FILE__).'/../edit/recordModify.php';
require_once dirname(__FILE__).'/../edit/recordsBatch.php';
require_once dirname(__FILE__).'/../../structure/import/dbsImport.php';
require_once dirname(__FILE__).'/../../../admin/verification/verifyValue.php';


define('ERR_XML_IMPORT','XML import error');

/*
main methods

getDefintions - returns list of definitions (record types to be imported)
importDefintions - Imports missed record types from remote database (uses dbsImport)
importRecords - import records from xml or json file


Special case - import from unregistered dastabase
1) When we import definition with concept code 0000-xxx it is added to target as 9999-xxx
2) When we import record with concept code 0000-xxx it searches defs in target as 9999-xxx

*/
class ImportHeurist {

    private static $system = null;
    private static $mysqli = null;
    private static $initialized = false;

private static function initialize($fields_correspondence=null)
{
    if (self::$initialized) {return;}

    global $system;
    self::$system  = $system;
    self::$mysqli = $system->get_mysqli();
    self::$initialized = true;

    if(!defined('HEURIST_DBID')){
        define('HEURIST_DBID', $system->get_system('sys_dbRegisteredID'));
    }
}

/**
* Reads import file
*
* detect format
* if xml coverts to json
*
* @param mixed $filename - archive or temp  import file -
*               this is either websiteStarterRecords or file in scratch folder
* @param mixed $type - type of file manifest of record data
*/
private static function _readDataFile($filename, $type=null, $validate=true){

    $data=null;
    try{
        // TODO: This special-casing based on a specific file name, which could be changed in an entirely
        // different part of the code, is a pretty horrible way of setting a file path. Should be passed directly
        // and setting default path should only occur where no file path is specified.
        if(strpos($filename,'websiteStarterRecords')===0 || strpos($filename,'webpageStarterRecords')===0){
            $filename = HEURIST_DIR.'hclient/widgets/cms/'.$filename;
        }elseif (!file_exists($filename)) {
            $filename = HEURIST_SCRATCH_DIR.basename($filename);
        }

        if(!file_exists($filename)){

            self::$system->addError(HEURIST_ACTION_BLOCKED, 'Import file doesn\'t exist');
        }elseif(!is_readable($filename))
        {
            self::$system->addError(HEURIST_ACTION_BLOCKED, 'Import file is not readable. Check permissions');
        }else
        {

            if(isXMLfile($filename)){
                $data = self::hmlToJson($filename);
            }else{
                $content = file_get_contents($filename);
                $data = json_decode($content, true);
            }


            if($validate){
                $imp_rectypes = @$data['heurist']['database']['rectypes'];
                if($data==null || !$imp_rectypes)
                {
                    if(!(count(self::$system->getError())>0)){
                        self::$system->addError(HEURIST_ACTION_BLOCKED,
                            'The data file contains data which does not correspond with expectations.<br> "Record type" section not found. You might be trying to load a data template file.');
                    }
                }




            }

        }
    } catch (Exception  $e){
        $data = null;
    }

    return $data;
}

//
// @todo use XMLReader to allow stream read,
// simplexml_load_file - loads the entire file into memory
//
private static function hmlToJson($filename){

    $xml_doc = simplexml_load_file($filename, 'SimpleXMLElement', LIBXML_PARSEHUGE);

    if($xml_doc===false){

        $errors = libxml_get_errors();

        foreach ($errors as $error) {
            error_log( display_xml_error($error, null) );
        }

        libxml_clear_errors();

        self::$system->addError(HEURIST_ACTION_BLOCKED, 'It appears that the xml is corrupted.', null, ERR_XML_IMPORT);
        return null;
    }
    if(!$xml_doc->database){
        self::$system->addError(HEURIST_ACTION_BLOCKED, 'The provided xml file is missing the "database" element,<br>this identifies the Heurist database this export originated from.', null, ERR_XML_IMPORT);
        return null;
    }

    $GEO_TYPES = array('bounds'=>'r', 'circle'=>'c' , 'polygon'=>'pl', 'path'=>'l' , 'point'=>'p', 'multi'=>'m');

    $db_attr = $xml_doc->database[0]->attributes();

    $json = array('heurist'=>array('records'=>array(), 'database'=>array(
            'id'=>''.$db_attr['id'],     //registration id
            'db'=>''.$xml_doc->database, //name of db
            'url'=>''
        )));

    $db_url = null;

    //found rectypes, fieldtypes
    $rectypes = array();
    $fieldtypes = array();

    $xml_recs = $xml_doc->records;
    $hasValidIdCount = 0;
    $invalidIds = array();
    if($xml_recs)
    {
        foreach($xml_recs->children() as $xml_rec){
			$rectype = $xml_rec->type->attributes();
			$rectype_id = ''.$rectype['id'];//may be not defined

			if(is_numeric($xml_rec->id) || is_numeric(trim($xml_rec->id))){ // Check record's id, checking if file is template
				$hasValidIdCount++;
			}else{
				$invalidIds[] = $xml_rec->id;
			}

			$record = array(
				'rec_ID'=>''.$xml_rec->id,
				'rec_RecTypeID'=>$rectype_id,
				'rec_RecTypeConceptID'=>''.$rectype['conceptID'],
				'rec_Title'=>''.$xml_rec->title,
				'rec_URL'=>''.$xml_rec->url,
				'rec_ScratchPad'=>''.$xml_rec->notes,
				'rec_OwnerUGrpID'=>0, //''.$xml_rec->workgroup->id,
				'rec_NonOwnerVisibility'=>''.$xml_rec->visibility,
				'rec_Added'=>''.$xml_rec->added,
				'rec_Modified'=>''.$xml_rec->modified,
				'rec_AddedByUGrpID'=>0 //$xml_rec->workgroup->id
			);

			//fill rectype array - it will be required to find missed rectypes
			//if id is not defined we take concept code
			$rt_idx = ($rectype_id>0)?$rectype_id: ''.$rectype['conceptID'];
			if(!@$rectypes[$rt_idx]){
				$rectypes[$rt_idx] = array(
					'id'   => $rectype_id,
					'name'=>''.$xml_rec->type,
					'code'=>''.$rectype['conceptID'],
					'count'=>1
				);
			}else{
				$rectypes[$rt_idx]['count']++;
			}

			if($db_url==null){
				$db_url = ''.$xml_rec->citeAs;
				if($db_url!=''){
                    $db_url = substr($db_url,0,strpos($db_url,'?'));
                    $db_url = $db_url.'?db='.$xml_doc->database;
                }
			}

			foreach($xml_rec->children() as $xml_det){
				if ($xml_det->getName()=='detail')
				{
				   $dets = $xml_det->attributes();
				   $fieldtype_id = ''.$dets['id'];
				   $detail = ''.$xml_det;

				   //field idx can be local id or concept code
				   $field_idx = ($fieldtype_id > 0)?$fieldtype_id: ''.$dets['conceptID'];

				   if(!@$fieldtypes[$field_idx]){
						$fieldtypes[$field_idx] = array(
							'id'   => $fieldtype_id,
							'name' => ''.$dets['name'],
							'code' => ''.$dets['conceptID']
						);
				   }

				   if($dets['isRecordPointer']=='true'){
					   /*$detail = array(
						'id'=>$xml_det,
						'type'=>'',
						'title'=>''
					   );*/
				   }elseif($xml_det->raw){
					   $detail = ''.$xml_det->raw;
				   }elseif($dets['termID']){

					   $trm_cCode = @$dets['termConceptID'];

					   if($trm_cCode!=null && $trm_cCode!=''){

							$ids = explode('-', $trm_cCode);
							if ($ids && (count($ids) == 2) && is_numeric($ids[0]) && ($ids[0] > 0)){
									$detail = ''.$dets['termID'];
							}
					   }

				   }elseif($xml_det->geo){

					   $geotype = @$GEO_TYPES[ ''.$xml_det->geo->type ];
					   if(!$geotype) {$geotype = ''.$xml_det->geo->type;}

					   $detail = array('geo'=>array(
						'type'=>$geotype,
						'wkt'=>''.$xml_det->geo->wkt
					   ));
				   }elseif($xml_det->file){
					   $detail = array('file'=>array(
						'ulf_ID'=>''.$xml_det->file->id,
						//'fullPath'=> null,
						'ulf_OrigFileName'=>''.$xml_det->file->origName,
						//'ulf_ExternalFileReference'=>$xml_det->file->url,
						'ulf_MimeExt'=>''.$xml_det->file->mimeType,
						'ulf_ObfuscatedFileID'=>''.$xml_det->file->nonce,
						'ulf_Description'=>''.$xml_det->file->description,
						'ulf_Added'=>''.$xml_det->file->date
					   ),
					   'fileid'=>''.$xml_det->file->nonce);

					   $file_url = ''.$xml_det->file->url;
                       //$db_url - source database url
					   if($file_url && ($db_url=='' || $db_url==null
                                || strpos($file_url, $db_url)===false)
                            &&  (strpos($file_url,'http://')===0
                              || strpos($file_url,'https://')===0)
                         ){
							$detail['file']['ulf_ExternalFileReference'] = $file_url;
					   }
				   }

				   //field idx can be local id or concept code
				   if(!@$record['details'][$field_idx]) {$record['details'][$field_idx] = array();}
				   $record['details'][$field_idx][] = $detail;
				}
			}

			$json['heurist']['records'][] = $record;

        }//records
    }else{
        self::$system->addError(HEURIST_ACTION_BLOCKED, 'Cannot find any records within the provided xml file,<br>records need to be within "records" elements.<br>You might be trying to load a template file without data.', null, ERR_XML_IMPORT);
        return null;
    }

    if($hasValidIdCount == 0){
        $extra_details = "<br><br>If this occurs when Heurist says it is doing an automatic update,<br>please advise the Heurist team (Bug Report in the Help menu at the top right) so that we can fix this problem.<br>" . (count($invalidIds) > 0) ? "The list of invalid ids: " . implode(',', $invalidIds) : "";
        self::$system->addError(HEURIST_ACTION_BLOCKED, 'There are no valid record IDs within the provided xml file.<br>You may be trying to upload an xml data template rather than actual data.' . $extra_details, null, ERR_XML_IMPORT);
        return null;
    }

    $json['heurist']['database']['url'] = $db_url;
    $json['heurist']['database']['rectypes'] = $rectypes; //need to download/sync rectypes
    $json['heurist']['database']['detailtypes'] = $fieldtypes; //need to show missed detail fields

    return $json;
}


/**
* returns list of definitions (record types to be imported)
*
* It reads manifest files and tries to find all record types in current database by concept code. All record types from manifest file
* Returns array of rectypes, false otherwise
* $res = array(
            'database'=> database id
            'database_name'=>
            'rectypes'=> array(source_rectype_id => array(name,code,count,target_RecTypeID),...
            'detailtypes'=>$imp_detailtypes  //need to show missed detail fields
        );
*
*
*
* @param mixed $filename
*/
public static function getDefintions($filename){

    self::initialize();

    $res = false;

    $data = self::_readDataFile( $filename );

    if($data!=null){

        $database_defs = dbs_GetRectypeStructures(self::$system, null, 2);
        $database_defs = array('rectypes'=>$database_defs);

        //list of all rectypes in file
        $imp_rectypes = @$data['heurist']['database']['rectypes'];

        if($data==null || !$imp_rectypes){
            self::$system->addError(HEURIST_ACTION_BLOCKED, 'Import data has wrong format or no record types found');
            return false;
        }

        //find local ids
        foreach ($imp_rectypes as $rtid => $rt){
            $conceptCode = $rt['code']?$rt['code']:rtid;

            $local_id = DbsImport::getLocalCode('rectypes', $database_defs, $conceptCode, false);
            $imp_rectypes[$rtid]['target_RecTypeID'] = $local_id;
        }

        //for not registered and the same - found missed
        $dbsource_is_same = ( (!(@$data['heurist']['database']['id']>0)) ||
                              (defined('HEURIST_DBID') && @$data['heurist']['database']['id']==HEURIST_DBID) );

        $imp_detailtypes = null;

        if(true || $dbsource_is_same){

            $imp_detailtypes = @$data['heurist']['database']['detailtypes'];

            if($imp_detailtypes){
                $database_defs = array('detailtypes'=>dbs_GetDetailTypes(self::$system, null, 2));
                //find local ids
                foreach ($imp_detailtypes as $dtid => $dt){
                    $conceptCode = $dt['code']?$dt['code']:$dtid;

                    $local_id = DbsImport::getLocalCode('detailtypes', $database_defs, $conceptCode, false);
                    $imp_detailtypes[$dtid]['target_dtyID'] = $local_id;
                }
            }
        }

        //return array of $imp_rectypes - record types to be imported
        $res = array(
            'database'=>@$data['heurist']['database']['id'],
            'database_name'=>@$data['heurist']['database']['db'],
            'database_url'=>@$data['heurist']['database']['url'],
            'rectypes'=>$imp_rectypes,    //need to download/sync rectypes
            'detailtypes'=>$imp_detailtypes  //need to show missed detail fields
        );

    }else{
        if(!(count(self::$system->getError())>0)){
            self::$system->addError(HEURIST_ACTION_BLOCKED, 'Import data not recognized');
        }
    }

    return $res;
}

//
// Imports missed record types from remote database (along with all dependencies).
// It uses dbsImport.php
//
public static function importDefintions($filename, $session_id){

    self::initialize();

    $res = false;

    //read manifest
    $data = self::_readDataFile( $filename );

    if($data!=null){

        $imp_rectypes = $data['heurist']['database']['rectypes'];

        //find orphaned detail types
        $imp_detailtypes = $data['heurist']['database']['detailtypes'];


        ini_set('max_execution_time', '0');
        $importDef = new DbsImport( self::$system );

        //special case - target is registered
        if (false && !(@$data['heurist']['database']['id']>0)) {  //TT1 - todo use $allow_import_unregistered
            self::$system->addError(HEURIST_ERROR, '<b>Not possible to determine an origin database id (source of import).</b>'
.'<br><br><div>Value read = 0 = non-Heurist source or unregistered Heurist database. This will only work if your database already'
.' contains all the entity types, fields, vocabularies and terms required to hold the incoming data. Please add all required structures'
.' or set the source database with <database id=xxxx> near the start of the file.</div>', null,
'Non-Heurist or unregistered Heurist source database'
            );
            return false;
        }


        //Finds all defintions to be imported
        if($importDef->doPrepare(  array(
                    'session_id'=>$session_id,
                    'defType'=>'rectype',
                    'databaseID'=>@$data['heurist']['database']['id'],
                    'databaseURL'=>@$data['heurist']['database']['url'],
                    'definitionID'=>array_keys($imp_rectypes),
                    'rectypes'=>$imp_rectypes,
                    'fieldtypes'=>$imp_detailtypes )))
        {
            $res = $importDef->doImport();
        }

        if(!$res){
            /*$err = self::$system->getError();
            if($err && $err['status']!=HEURIST_NOT_FOUND){
                self::$system->error_exit(null);//produce json output and exit script
            }*/
        }else{
            //need to call refresh client side defintions
            $res = 'ok';
        }
    }

    return $res;
}

/**
    Import records from another database on the same server
*/
public static function importRecordsFromDatabase($params){

    self::initialize();

//1. saves import file into scratch folder - see record_output
    $remote_path = HEURIST_BASE_URL.'hserv/controller/record_output.php?format=json&depth=0&db='
            .$params['source_db'];

    $search_params = array();
    if(@$params['recID']>0){
        $params['q'] = 'ids:'.$params['recID'];
    }elseif(@$params['ids']){
        $params['q'] = 'ids:'.implode(',', prepareIds($params['ids']));
    }
    $remote_path = $remote_path.'&q='.$params['q'];

    if(@$params['rules']!=null){
        //$rules = json_decode($params['rules']);

        $remote_path = $remote_path.'&rules='.rawurlencode($params['rules']);
        if(@$params['rulesonly']==true || @$params['rulesonly']==1){
            $remote_path = $remote_path.'&rulesonly=1';
        }
    }

    // convert tlcmap dataset to map layer and creates parent mapspace
    // see record_output.php
    if(@$params['tlcmapspace']!=null){
        $remote_path = $remote_path.'&tlcmap='.urlencode($params['tlcmapspace']);
    }

    // save file that produced with record_output.php from source to temp file
    $heurist_path = tempnam(HEURIST_SCRATCH_DIR, "_temp_");

    $filesize = saveURLasFile($remote_path, $heurist_path);//save json import from remote db to tempfile in scratch folder

//2. import records
    if($filesize==0 || !file_exists($heurist_path)){
        self::$system->addError(HEURIST_ERROR,
                'Cannot download records from '.$params['source_db'].'.  '.$remote_path.' to '.$heurist_path);
        return false;
    }

        //read temp file, import records
        $params2 = array(
            'session' => @$params['session'],
            'is_cms_init' => 0,
            'make_public' => (@$params['make_public']!=0),
            'owner_id' => self::$system->get_user_id(),
            'mapping_defs' => @$params['mapping']
        );

        $res = self::importRecords($heurist_path, $params2);


        if(@$params['tlcmapshot'] && $res!==false){
            //find map document among imported records
            self::$system->defineConstant('RT_MAP_DOCUMENT');
            $mysqli = self::$system->get_mysqli();
            $map_doc_rec_id = mysql__select_value($mysqli,
                'select rec_ID from Records where rec_ID in ('
                .implode(',',$res['ids']).') and rec_RecTypeID='.RT_MAP_DOCUMENT);

            if($map_doc_rec_id>0){
                //save snapshot as mapspace thumbnail
                self::saveMapDocumentSnapShot($map_doc_rec_id, $params['tlcmapshot']);
            }
        }

        unlink($heurist_path);//remove temp file

        return $res;
}

//
// save snapshot as mapspace thumbnail
// $ids - record ids of all mapdocument records
// $tlcmapshot - base64 encoded image
//
//1. find mapdocument among ids
//2. save encoded image as file and register it
//3. add DT_THUMBNAIL detail to mapdocume record
//
public static function saveMapDocumentSnapShot($rec_ID, $tlcmapshot){

    if(($rec_ID>0) && self::$system->defineConstant('DT_THUMBNAIL')){
        //$mysqli = self::$system->get_mysqli();

        //2. save encoded image as file and register it
        $entity = new DbRecUploadedFiles(self::$system);
        $ulf_ID = $entity->registerImage($tlcmapshot, 'map_snapshot_'.$rec_ID);//it returns ulf_ID
        if( is_bool($ulf_ID) && !$ulf_ID ){
            return false;
        }
        if(is_array($ulf_ID)){
            $ulf_ID = $ulf_ID[0];
        }

        //3. add DT_THUMBNAIL detail to mapdocument record
        $dbRecDetails = new RecordsBatch(self::$system, array('ulfID'=>$ulf_ID, dtyID=>DT_THUMBNAIL, 'recIDs'=>$rec_ID));
        $res = $dbRecDetails->detailsAdd();

        return $res;
    }else{
        return false;
    }
}

/*
    $params                                                                                                  1
       session_id - progress session id
       is_cms_init=if true this is a creation of set of records for website - it adds info text for webpage content
       make_public=true
       owner_id=1  - by defaul owner will be "Database managers" group
       mapping_def=null - mapping detail fields for direct import from other database on the same server

       unique_field_id=0  id in source to maintain uniquiness of record
       allow_insert=true
       update_mode = 0 (no update), 1 overwrite, 2 -add, 3 - add if empty, 4 - replace/retain

returns array
    ids
    count_imported (total)
        count_inserted
        count_updated
    count_ignored  - rectype not found
    details_empt - details are empty
    resource_notfound

*/
public static function importRecords($filename, $params){

    self::initialize();

    $is_debug = @$params['dbg'] == 1;
    $session_id  = @$params['session'];
    $is_cms_init = (@$params['is_cms_init']===true || @$params['is_cms_init']==1);
    $make_public = !(@$params['make_public']===false || @$_REQUEST['make_public']==0);
    $owner_id = @$params['onwer_id']>0 ?$params['onwer_id'] :1;
    $mapping_defs = @$params['mapping_defs'];

    $unique_field_id = @$params['unique_field_id'];
    $allow_insert = true;
    $update_mode  = 1; //by default 1 - overwrite, if zero - no update allowed
    if($unique_field_id){
        $allow_insert = (@$params['allow_insert']==1 || @$params['allow_insert']===true);
        $update_mode = @$params['update_mode'];
    }

    $mysqli = self::$system->get_mysqli();

    //init progress
    mysql__update_progress($mysqli, $session_id, true, '0,1');


    $res = false;
    $cnt_imported = 0;
    $cnt_inserted = 0;
    $cnt_updated = 0;
    $cnt_ignored = 0;
    $ids_exist = array();
    $rec_ids_details_empty = array();
    $resource_notfound = array();//target id, source id, field name, value

    $home_page_id = 0;
    $page_id_for_blog = 0; //for cms init find record with DT_EXTENDED_DESCRIPTION=='BLOG TEMPLATE'

    $data = self::_readDataFile( $filename );

    self::$system->defineConstant('DT_ORIGINAL_RECORD_ID');

    if($data!=null){

        $value_cms_info = '';

        if($is_cms_init){
            self::$system->defineConstant('RT_CMS_HOME');
            self::$system->defineConstant('DT_EXTENDED_DESCRIPTION');
            $value_cms_info = <<<'EOD'
<p>-----</p>
<p>This is default content generated by the CMS function of the Heurist data management system (<a title="Heurist Academic Knowledge Management System" href="https://heuristnetwork.org/" target="_blank" rel="noopener">HeuristNetwork.org</a>).</p>
<p>Please edit the content to create an appropriate page, or delete the menu entry and page if not required.</p>
<p>Please see <a href="../context_help/website_instructions.htm">Heurist CMS instructions</a> for further information.</p>
<table style="border-collapse: collapse;margin-top:40px" border="1">
<tbody>
<tr>
<td style="width: 100%; padding-left: 30px; padding-right: 30px;">
<p><strong><span style="color: #ff0000;">If you come across this page with dummy content, please email the website owner </span></strong></p>
<p><strong><span style="color: #ff0000;">(link at top right) and ask them to update or delete the page.</span></strong></p>
</td>
</tr>
</tbody>
</table>
<p> </p>
EOD;
        }
        else{
           $make_public = true;
        }

        $execution_counter = 0;

        $tot_count = 0;
        if(is_array(@$data['heurist']['database']['records'])){
            $tot_count = count(@$data['heurist']['database']['records']);
        }
        if(!($tot_count>0)){
            $tot_count = count($data['heurist']['records']);
        }

        //init progress
        mysql__update_progress($mysqli, $session_id, false, '0,'.$tot_count);

        $imp_rectypes = $data['heurist']['database']['rectypes'];

        //need to copy files
        $source_url = @$data['heurist']['database']['url'];//base url of database
        $source_db = @$data['heurist']['database']['db'];//name of database

        ini_set('max_execution_time', '0');

        // if database not defined or the same
        // is the same it is assumed that all local codes in $data are already found and exists in
        // target database, elements without local codes or if not found will be ignored
        //$dbsource_is_same = defined('HEURIST_DBID') && ((!(@$data['heurist']['database']['id']>0)) ||
        // @$data['heurist']['database']['id']==HEURIST_DBID);

        $dbsource_is_registered = (@$data['heurist']['database']['id']>0);

        if(defined('HEURIST_DBID') && HEURIST_DBID>0){ //target is registered
            $dbsource_is_same = ( !$dbsource_is_registered ||
                            @$data['heurist']['database']['id']==HEURIST_DBID);
        }else{

            if(!$dbsource_is_registered){
                //if source is not smae, definitions with conceptcodes 0000-xx will be imported with concept codes 9999-xxx
                $dbsource_is_same = ($params['same_source']==1);
            }else{
                //if source is registered - source is different
                $dbsource_is_same = !$dbsource_is_registered;
            }
        }


        if($dbsource_is_same){

            $defs = array(
                'rectypes' => dbs_GetRectypeStructures(self::$system, null, 2),
                'detailtypes' => dbs_GetDetailTypes(self::$system, null, 2),
                'terms' => dbs_GetTerms(self::$system));

        }else{

            $importDef = new DbsImport( self::$system );

            $databaseURL = null;
            if($source_url){
                if(strpos($source_url,'?db=')>0){
                    $databaseURL = $source_url;
                }elseif($source_db){
                    $databaseURL = $source_url.'?db='.$source_db;
                }
            }

            if($mapping_defs!=null){

                //for import records by mapping we check and import affected vocabularies only
                $res2 = $importDef->doPrepare(  array('defType'=>'term',
                            'databaseID'=>@$data['heurist']['database']['id'],
                            'databaseURL'=>$databaseURL,
                            'definitionID'=>$mapping_defs['vocabularies']));//array of vocabularies to be imported

                if($res2 && @$mapping_defs['import_vocabularies']==1){
                        $res2 = $importDef->doImport();//sync/import vocabularies
                }

                if($res2){
                    //mapping for fields and rectypes
                    $importDef->doMapping($mapping_defs);//need for getTargetIdBySourceId
                    $defs = $importDef->getDefinitions();//terms aready here (target defs)

                    $defs['rectypes'] = dbs_GetRectypeStructures(self::$system, null, 2);
                    $defs['detailtypes'] = dbs_GetDetailTypes(self::$system, null, 2);
                }


            }else{
                //Finds all defintions to be imported
                $res2 = $importDef->doPrepare(  array('defType'=>'rectype',
                            'databaseID'=>@$data['heurist']['database']['id'],
                            'databaseURL'=>$databaseURL,
                            'definitionID'=>array_keys($imp_rectypes), //array of record type source ids
                            'rectypes'=>$imp_rectypes ));

                //get target definitions (this database)
                $defs = $importDef->getDefinitions();

            }

            $src_defs = $importDef->getDefinitions('source');
            if(@$src_defs['databaseURL']){
                $source_url = $src_defs['databaseURL'];
            }

            if(!$res2){
                $err = self::$system->getError();
                if($err && $err['status']!=HEURIST_NOT_FOUND){
                    mysql__update_progress($mysqli, $session_id, false, 'REMOVE');
                    return false;
                }
                self::$system->clearError();
            }

        }

        $def_dts  = $defs['detailtypes']['typedefs'];
        $idx_type = $def_dts['fieldNamesToIndex']['dty_Type'];
        $idx_name = $def_dts['fieldNamesToIndex']['dty_Name'];
        $def_rst  = $defs['rectypes']['typedefs'];
        $idx_parent = $def_rst['dtFieldNamesToIndex']['rst_CreateChildIfRecPtr'];

        $file_entity = new DbRecUploadedFiles(self::$system);
        $file_entity->setNeedTransaction(false);

        $records = $data['heurist']['records'];//records to be imported

        $records_corr_alphanum = array();
        $records_corr = array();//correspondance: source rec id -> target rec id
        $resource_fields = array();//source rec id -> field type id -> field value (target recid)
        $keep_rectypes = array();//keep rectypes for furhter rectitle update
        $recid_already_checked = array();//keep verified H-ID resource records

        $parent_child_links = array();//keep parent_id => child_id

        //term
        $enum_fields = array();//source rec id -> field type id -> field value (term label)
        $enum_fields_values = array();//rectype -> field id -> value

        $is_rollback = false;
        $keep_autocommit = mysql__begin_transaction($mysqli);
        mysql__foreign_check($mysqli, false);

        self::$system->defineConstant('DT_PARENT_ENTITY');

        $record_count = count($records);
        foreach($records as $record_src){

            $is_blog_record = false;

            if(!is_array($record_src) && $record_src>0){
                //this is record id - record data in the separate file
                //@todo
            }

            $record_src_original_id = null;
            if($record_src['rec_ID']){
                $record_src_original_id = ((@$data['heurist']['database']['id']>0)
                            ?$data['heurist']['database']['id']:'0').'-'
                            .$record_src['rec_ID'];
            }

            $target_RecID = 0;

            if($dbsource_is_same){
                    $recTypeID = DbsImport::getLocalCode('rectypes', $defs,
                    $record_src['rec_RecTypeID']>0
                        ?$record_src['rec_RecTypeID']
                        :$record_src['rec_RecTypeConceptID'], false);
            }elseif($mapping_defs!=null){

                $recTypeID = @$mapping_defs[$record_src['rec_RecTypeID']]['rty_ID'];

                //check that record already exists in
                //get key value from target
                if($recTypeID>0){
                    $keyDty_ID = $mapping_defs[$record_src['rec_RecTypeID']]['key'];
                    $key_value = $record_src['details'][$keyDty_ID];
                    if(is_array($key_value)) {$key_value = array_shift($key_value);}

                    //search in target
                    $keyDty_ID = $mapping_defs[$record_src['rec_RecTypeID']]['details'][$keyDty_ID];
                    $target_RecID = mysql__select_value($mysqli, 'select rec_ID from Records, recDetails where dtl_RecID=rec_ID '
                    .' AND rec_RecTypeID='.$recTypeID.' AND dtl_DetailTypeID='.$keyDty_ID.' AND dtl_Value="'.$key_value.'"');

                    $target_RecID = intval($target_RecID);
                    if($target_RecID>0){
                        $ids_exist[] = $target_RecID;

                        $records_corr[$record_src['rec_ID']] = $target_RecID;
                        $keep_rectypes[$target_RecID] = $recTypeID;

                        continue; //only insert allowed
                    }else{
                        $target_RecID = 0;
                    }
                }else{
                    $cnt_ignored++;
                    continue;
                }

            }else{
                $recTypeID = $importDef->getTargetIdBySourceId('rectypes',
                    $record_src['rec_RecTypeID']>0
                            ?$record_src['rec_RecTypeID']
                            :$record_src['rec_RecTypeConceptID']);
            }

            if($mapping_defs==null && $unique_field_id){
                    //detect insert, update or skip

                    $query3 = null;

                    if($unique_field_id!='rec_ID'){
                        //this is detail field
                        if($dbsource_is_same){
                            $local_id = DbsImport::getLocalCode('detailtypes', $defs, $unique_field_id, false);
                        }else{
                            $local_id = DbsImport::getLocalCode('detailtypes', $importDef->getDefinitions('source'), $unique_field_id, false);
                        }



                        foreach($record_src['details'] as $dty_ID => $values){

                            if(is_array($values) && @$values['dty_ID']>0){ //interpreatable format
                                $dty_ID = $values['dty_ID'];
                                $values = array($values['value']);
                            }

                            if($dty_ID==$local_id && is_array($values)){
                                $record_src_original_id = array_shift($values);
                                break;
                            }
                        }
                    }

                    if($record_src_original_id){

$query3 = 'select rec_ID from Records, recDetails where dtl_RecID=rec_ID  AND dtl_DetailTypeID='.DT_ORIGINAL_RECORD_ID.' AND dtl_Value="'.$record_src_original_id.'"';

                        $target_RecID = mysql__select_value($mysqli, $query3);

                        $target_RecID = intval($target_RecID);
                        if($target_RecID>0){
                            //already exists
                            $ids_exist[] = $target_RecID;
                            $records_corr[$record_src['rec_ID']] = $target_RecID;
                            $keep_rectypes[$target_RecID] = $recTypeID;

                            if(!($update_mode>0)) {continue;} //no update allowed

                        }else{
                            if(!$allow_insert) {continue;}
                            $target_RecID = 0;
                        }

                    }

            }

            if(!($recTypeID>0)) {
                //skip this record - record type not found
                $cnt_ignored++;
                continue;
            }

            // prepare records - replace all fields, terms, record types to local ones
            // keep record IDs in resource (record pointer)) fields to replace them later
            $record = array();
            $record['ID'] = $target_RecID; //0 - add new
            $record['RecTypeID'] = $recTypeID;


            if(!@$record_src['rec_ID']){ //if not defined assign arbitrary unique
                $record_src['rec_ID'] = uniqid();
            }else {

                //in case source id is not numerics or more than MAX INT
                if((!ctype_digit($record_src['rec_ID'])) || strlen($record_src['rec_ID'])>9 ){  //4 957 948 868
                    $rec_id_low = strtolower($record_src['rec_ID']);
                    if(@$records_corr_alphanum[$rec_id_low]){ //aplhanum->random int
                        $record_src['rec_ID'] = $records_corr_alphanum[$rec_id_low];
                    }else{
                        $rand_id = rand(900000000,999999999);//random_int
                        $records_corr_alphanum[$rec_id_low] = $rand_id;
                        $record_src['rec_ID'] = $rand_id;
                    }
                }
            }


            $record['AddedByImport'] = 2; //import without strict validation
            $record['no_validation'] = true;
            $record['URL'] = @$record_src['rec_URL'];
            $record['URLLastVerified'] = @$record_src['rec_URLLastVerified'];
            $record['ScratchPad'] = @$record_src['rec_ScratchPad'];
            $record['Title'] = @$record_src['rec_Title'];


            $record['OwnerUGrpID'] = $owner_id;
            $record['NonOwnerVisibility'] = ($make_public)?'public':'viewable';

            $record['details'] = array();

            if(@$record_src['details']==null){
                array_push($rec_ids_details_empty, $record_src['rec_ID']);
                continue;
            }


            foreach($record_src['details'] as $dty_ID => $values){

                if(is_array($values) && @$values['dty_ID']>0){ //interpreatable format
                    $dty_ID = $values['dty_ID'];
                    $values = array($values['value']);
                }

                //field id in target database
                if($dbsource_is_same){
                    //$dty_ID can be local id or concept code
                    $ftId = DbsImport::getLocalCode('detailtypes', $defs, $dty_ID, false);
                }elseif($mapping_defs!=null){

                    $ftId = @$mapping_defs[$record_src['rec_RecTypeID']]['details'][$dty_ID];

                }else{
                    $ftId = $importDef->getTargetIdBySourceId('detailtypes', $dty_ID);
                }

                if(!($ftId>0)){
                    //target not found - field is ignored
                    //@todo - add to report
                    continue;
                }
                if(!@$def_dts[$ftId]){
                    //definitions not found
                    //@todo - add to report
                    continue;
                }
                if($dty_ID==self::$system->getConstant('DT_PARENT_ENTITY',0)){ //ignore
                    continue;
                }


                $def_field = $def_dts[$ftId]['commonFields'];

                if($def_field[$idx_type] == "relmarker"){ //ignore
                    continue;
                }

                $new_values = array();
                if($def_field[$idx_type] == "enum" ||
                   $def_field[$idx_type] == "relationtype")
                {
                    foreach($values as $value){
                        //change terms ids for enum and reltypes
                        if($dbsource_is_same){
                            //by local id or concept code
                            $termID = DbsImport::getLocalCode($def_field[$idx_type], $defs, $value, false);
                        }else{
                            $termID = $importDef->getTargetIdBySourceId($def_field[$idx_type], $value);
                        }
                        // if not numeric - it can be term code or term label
                        $termID = self::validateEnumeration($recTypeID, $ftId,
                                        ($termID>0 ?$termID:$value), $defs);

                        if($termID>0){
                            $new_values[] = $termID;
                        }else{
                            //either not allowed or not found
                            if(ctype_digit($value) || strpos($value,'-')>0){
                                //not allowed
                                //@todo - add to report
                                continue;
                            }else{

                               //keep label value
                               if(!@$enum_fields_values[$recTypeID]){
                                   $enum_fields_values[$recTypeID] = array();
                               }
                               if(!@$enum_fields_values[$recTypeID][$ftId]){
                                   $enum_fields_values[$recTypeID][$ftId] = array();
                               }

                               $uid = array_search($value, $enum_fields_values[$recTypeID][$ftId]);
                               if($uid===false){
                                    $uid = uniqid();
                                    $enum_fields_values[$recTypeID][$ftId][$uid] = $value;
                               }

                               //save $uid as field value, it will be replaced to term id
                               //after new terms will be added
                               if(!@$enum_fields[$record_src['rec_ID']]){
                                   $enum_fields[$record_src['rec_ID']] = array();
                               }
                               if(!@$enum_fields[$record_src['rec_ID']][$ftId]){
                                   $enum_fields[$record_src['rec_ID']][$ftId] = array();
                               }
                               $enum_fields[$record_src['rec_ID']][$ftId][] = $uid;

                               $new_values[] = $uid;
                            }
                        }
                        //replaceTermIds( $value, $def_field[$idx_type] );
                    }
                }elseif($def_field[$idx_type] == "geo"){

                   foreach($values as $value){
                       //geo
                       $geotype = '';
                       if (@$value['geo']['type']){
                            $geotype = $value['geo']['type'].' ';
                       }
                       $new_values[] = $geotype.$value['geo']['wkt'];
                   }

                }elseif($def_field[$idx_type] == "file"){

                   //copy remote file to target filestore, register and get ulf_ID
                   foreach($values as $value){

                       $tmp_file = null;
                       $value = $value['file'];
                       $dtl_UploadedFileID = null;

                       if(@$value['ulf_ExternalFileReference'] &&
                            (strpos($value['ulf_ExternalFileReference'],'http://')===0
                            || strpos($value['ulf_ExternalFileReference'],'https://')===0)){ //remote URL

                            //detect mimetype
                            $ext = recognizeMimeTypeFromURL($mysqli, $value['ulf_ExternalFileReference']);
                            if(@$ext['extension']){
                                $value['ulf_MimeExt'] = $ext['extension'];
                            }

                            if(@$value['ulf_ID']>0) {$value['ulf_ID']=0;}

                            $fileinfo = array('entity'=>'recUploadedFiles', 'fields'=>$value);

                            $file_entity->setData($fileinfo);
                            $file_entity->setRecords(null);//reset
                            $dtl_UploadedFileID = $file_entity->save();//register remote url - it returns ulf_ID

                       }elseif(!$dbsource_is_same || !defined('HEURIST_DBID')) { //do not copy file for the same database

                            //download to scratch folder

                            $tmp_file = tempnam(HEURIST_SCRATCH_DIR, '_temp_');
                            $newfilename = USanitize::sanitizeFileName($value['ulf_OrigFileName']);

                            //source on the same server as target
                            if(strpos($source_url, HEURIST_SERVER_URL)===0 && @$value['fullPath'])
                            {
                                if (file_exists(HEURIST_FILESTORE_ROOT.$source_db.'/'.$value['fullPath'])) {
                                    copy(HEURIST_FILESTORE_ROOT.$source_db.'/'.$value['fullPath'] , $tmp_file);
                                }
                            }
                            else
                            {
                                //$fileURL = @$value['url'];
                                if(strpos($source_url,'?db=')===false){
                                    $file_URL = $source_url.'?db='.$source_db;
                                }else{
                                    $file_URL = $source_url;
                                }
                                $file_URL = $file_URL.'&file='.$value['ulf_ObfuscatedFileID'];//download
                                saveURLasFile($file_URL, $tmp_file);//save imported image to temp file in scratch folder
                            }

                            //register imported image
                            if(file_exists($tmp_file)){
                                $dtl_UploadedFileID = $file_entity->registerFile($tmp_file, $newfilename);//it returns ulf_ID
                            }


                       }elseif($dbsource_is_same) {

                           $dtl_UploadedFileID = array($value['ulf_ID']);
                       }

                       if($dtl_UploadedFileID!=null){
                            if($dtl_UploadedFileID===false){
                                $err_msg = self::$system->getError();
                                $err_msg = $err_msg['message'];
                                self::$system->clearError();
                                $dtl_UploadedFileID = null;
                            }else{
                                $dtl_UploadedFileID = $dtl_UploadedFileID[0];
                                $new_values[] = $dtl_UploadedFileID;
                            }
                       }

                       if($tmp_file && file_exists($tmp_file)){
                            unlink($tmp_file);
                       }


                    }

                }
                elseif($def_field[$idx_type] == "resource"){

                   $new_values = array();
                   //keep source record id to replace it to new target record id
                   if(!@$resource_fields[$record_src['rec_ID']]){
                       $resource_fields[$record_src['rec_ID']] = array();
                   }
                   if(!@$resource_fields[$record_src['rec_ID']][$ftId]){
                       $resource_fields[$record_src['rec_ID']][$ftId] = array();
                   }
                   $is_parent = false;
                   if(@$def_rst[$recTypeID]['dtFields'][$ftId]!=null){
                       $is_parent = ($def_rst[$recTypeID]['dtFields'][$ftId][$idx_parent]==1);
                   }


                   foreach($values as $value){

                       $resourse_id = null;

                       if(is_array($value)){
                           $value = $value['id'];
                       }
                       if(strpos($value,'H-ID-')===0){ //there is such id in target
                           $value = substr($value,5);

                           if($recid_already_checked[$value]){
                               $resourse_id = $value;
                           }elseif(is_numeric($value) && $value>0){
                               //check existence
                               $is_found = (mysql__select_value($mysqli,
                                        'select rec_ID from Records where rec_ID='
                                        .$value)>0);
                               if($is_found){
                                   $recid_already_checked[]  = $value;
                                   $resourse_id = $value;
                               }else{
                                   $resource_notfound[] = array(0, $record_src['rec_ID'], $def_field[$idx_name], 'H-ID-'.$value);
                               }
                           }

                       }else{

                           if((!ctype_digit($value)) || strlen($value)>9 ){  //8 724 803 625

                               $rec_id_low = strtolower($value);
                               if(@$records_corr_alphanum[$rec_id_low]){
                                   $value = $records_corr_alphanum[$rec_id_low];
                               }else{
                                   $rand_id = rand(900000000,999999999);//was random_int
                                   $records_corr_alphanum[$rec_id_low] = $rand_id;
                                   $value = $rand_id;
                               }
                           }
                           $resource_fields[$record_src['rec_ID']][$ftId][] = $value;
                           $resourse_id = $value;
                       }

                       if($resourse_id!=null){
                           $new_values[] = $resourse_id;
                           if($is_parent && $record_src['rec_ID']!=null){
                               $parent_child_links[] = array('parent'=>$record_src['rec_ID'], 'child'=>$resourse_id);
                           }
                       }

                   }
//"2552":{"7462":{"id":"1326","type":"98","title":"Record to imported","hhash":null}}}
                }else{

                    $new_values = $values;
                }

                if(!isEmptyArray($new_values))
                {
                    if (isset($record['details'][$ftId])){
                        array_push($record['details'][$ftId], ...$new_values);
                    }else{
                        $record['details'][$ftId] = $new_values;
                    }

                    if($is_cms_init && $dty_ID == DT_EXTENDED_DESCRIPTION && $new_values[0]=='BLOG TEMPLATE'){
                        $is_blog_record = true;
                    }
                }

            }//for details


            //keep original id
            if(defined('DT_ORIGINAL_RECORD_ID') && $record_src_original_id!=null){
                if(!is_array(@$record['details'][DT_ORIGINAL_RECORD_ID])){
                    $record['details'][DT_ORIGINAL_RECORD_ID] = array();
                }
                $record['details'][DT_ORIGINAL_RECORD_ID][] = $record_src_original_id;
            }

            // note: we need to suppress creation of reverse 247 pointer for parent-child links

            //no transaction, suppress parent-child
            $out = recordSave(self::$system, $record, false, true, $update_mode, $record_count);//see recordModify.php

            if ( @$out['status'] != HEURIST_OK ) {
                $is_rollback = true;
                break;
            }

            //source rec id => target rec id
            $new_rec_id  = intval($out['data']);//new record id
            $records_corr[$record_src['rec_ID']] = $new_rec_id;
            $keep_rectypes[$new_rec_id] = $record['RecTypeID'];

            if($is_cms_init){
                if($is_blog_record){
                    $page_id_for_blog = $new_rec_id;
                }
                if($record['RecTypeID']==RT_CMS_HOME){
                    $home_page_id = $new_rec_id;
                }
            }

            $execution_counter++;

            if($session_id!=null){
                $session_val = $execution_counter.','.$tot_count;
                $current_val = null;
                //check for termination and set new value
                if ($execution_counter % 100 == 0) { //(intdiv($execution_counter,100) == $execution_counter/100){
                    $current_val = mysql__update_progress($mysqli, $session_id, false, $session_val);
                }

                if($current_val && $current_val=='terminate'){ //session was terminated from client side
                    //need rollback
                    self::$system->addError(HEURIST_ACTION_BLOCKED, 'Operation has been terminatated');
                    $is_rollback = true;
                    break;
                }
            }
            $cnt_imported++;

            if($target_RecID==0){
                $cnt_inserted++;
            }else{
                $cnt_updated++;
            }


        }//records

        if(!$is_rollback){

            //import new terms
            $new_terms = array();
            foreach ($enum_fields_values as $recTypeID=>$fields){
                foreach ($fields as $fieldtype_id=>$values){
                    foreach ($values as $uid=>$term_label){

                        //add new term
                        $new_term_id = self::addNewTerm($recTypeID, $fieldtype_id, $term_label, $defs);
                        //add new term id to correspondance array
                        $new_terms[$uid] = $new_term_id;
                    }
                }
            }
            //replace temp uniqid in records to new term ids
            foreach ($enum_fields as $src_recid=>$fields){
                //get new id in target db
                $trg_recid = @$records_corr[$src_recid];//source rec id -> target rec id
                if($trg_recid>0){
                    foreach ($fields as $fieldtype_id=>$values){
                        foreach ($values as $idx=>$uid){

                            //get new terms id
                            $term_id = @$new_terms[$uid];

                            if($term_id>0){
$query = "UPDATE recDetails SET dtl_Value=$term_id WHERE dtl_RecID=$trg_recid AND dtl_DetailTypeID=$fieldtype_id AND dtl_Value='$uid'";

                            }else{
                                //new terms was not added
$query = "DELETE FROM recDetails WHERE dtl_RecID=$trg_recid AND dtl_DetailTypeID=$fieldtype_id AND dtl_Value='$uid'";
                            }

                            $ret = mysql__exec_param_query($mysqli, $query, null);
                            if($ret!==true){
                                self::$system->addError(HEURIST_DB_ERROR, 'Cannot update term fields', 'Query:'.$query.'. '.$ret);
                                $is_rollback = true;
                                break;
                            }
                        }
                    }
                }
            }

            if(!$is_rollback){

                //set target id for $resource_notfound
                foreach ($resource_notfound as $idx=>$item){
                    $resource_notfound[$idx][0] = @$records_corr[$item[1]];
                }

                //update resource (record pointer) fields with new record ids
                foreach ($resource_fields as $src_recid=>$fields){  //src recid => dty ids

                    //get new id in target db
                    $trg_recid = @$records_corr[$src_recid];//source rec id -> target rec id
                    if($trg_recid>0){
                        foreach ($fields as $fieldtype_id=>$old_values){
                            foreach ($old_values as $old_value){
                                //get new id in target db
                                $query = null;
                                $new_value = @$records_corr[$old_value];
                                if($new_value>0){
$query = "UPDATE recDetails SET dtl_Value=$new_value WHERE dtl_RecID=$trg_recid AND dtl_DetailTypeID=$fieldtype_id AND dtl_Value=$old_value";

                                }elseif($old_value>0){
                                    //target record not found
$query = "DELETE FROM recDetails WHERE dtl_RecID=$trg_recid AND dtl_DetailTypeID=$fieldtype_id AND dtl_Value=$old_value";

                                    $resource_notfound[] = array($trg_recid, $src_recid,
                                            $def_dts[$fieldtype_id]['commonFields'][$idx_name], $old_value);
                                }
                                if($query!=null){
                                    $ret = mysql__exec_param_query($mysqli, $query, null);
                                    if($ret!==true){
                                        self::$system->addError(HEURIST_DB_ERROR, 'Cannot update record pointer fields', 'Query:'.$query.'. '.$ret);
                                        $is_rollback = true;
                                        break;
                                    }
                                }
                            }
                        }//for
                    }
                }//for
            }
            //create reverse child to parent links if required
            if(!$is_rollback && !isEmptyArray($parent_child_links)){

                foreach($parent_child_links as $idx=>$link){

                    $parent_id = $link['parent'];
                    $child_id = $link['child'];

                    $child_id = @$records_corr[$child_id];
                    $parent_id = @$records_corr[$parent_id];

                    if($parent_id>0 && $child_id>0){
                        $res = addReverseChildToParentPointer($mysqli, $child_id, $parent_id, 1, false);

                        if($res<0){
                            $syserror = $mysqli->error;
                            self::$system->addError(HEURIST_DB_ERROR, 'Cannot insert reverse pointer for child record', $syserror);
                            $is_rollback = true;
                            break;
                        }
                    }
                }//for
            }
            if(!$is_rollback){
                $idx_mask = $defs['rectypes']['typedefs']['commonNamesToIndex']['rty_TitleMask'];
                //update record title
                foreach ($keep_rectypes as $rec_id=>$rty_id){
                    $mask = @$defs['rectypes']['typedefs'][$rty_id]['commonFields'][$idx_mask];
                    recordUpdateTitle(self::$system, $rec_id, $mask, null);
                }

                //update special concept codes 9999-xxx to correct ones
                DbUtils::updateImportedOriginatingDB();
            }
        }

        if($is_rollback){
                $mysqli->rollback();
                if($keep_autocommit===true) {$mysqli->autocommit(true);}
                $res = false;
        }else{
                $mysqli->commit();
                if($keep_autocommit===true) {$mysqli->autocommit(true);}
                $res = array('count_imported'=>$cnt_imported,
                             'count_ignored'=>$cnt_ignored, //rectype not found
                             'count_inserted'=>$cnt_inserted,
                             'count_updated'=>$cnt_updated,
                             'cnt_exist'=>count($ids_exist), //such record already exists
                             'details_empty'=>$rec_ids_details_empty,
                             'home_page_id'=>$home_page_id,
                             'page_id_for_blog'=>$page_id_for_blog,
                             'resource_notfound'=>$resource_notfound  );//if value is H-ID-nnn
                if(count($records_corr)<1000){
                    $res['ids'] = array_values($records_corr);
                }
                if(count($ids_exist)<1000){
                    $res['exists'] = $ids_exist;
                }
        }
        mysql__foreign_check($mysqli, true);

    }//$data

    //finish progress session
    mysql__update_progress($mysqli, $session_id, false, 'REMOVE');

    return $res;
}

//
// Import term by label
// 1. tries to find terms among allowed terms for given field id
// 2. if not found - add to vocabulary or to special 'Auto-added terms' terms
//

//
// check $term_value for field $dt_id of $recordType
// $defs - database definitions
// $term_value can be term id, term code or label
//
// returns term_id of $term_value is allowed,
//        otherwise it is condsidered as label and added to array to be added
//
private static function validateEnumeration($recTypeID, $dt_id, $term_value, $dbdefs){


    $r_value2 = trim_lower_accent($term_value);
    if($r_value2!=''){ //skip empty value

        $recStruc = $dbdefs['rectypes']['typedefs'];
        //see similar code code in importAction.php validateEnumerations

        $dt_def = @$recStruc[$recTypeID]['dtFields'][$dt_id];

        if($dt_def==null){ //such field is not found
            $dtyStruc = @$dbdefs['detailtypes']['typedefs'];

            if($dtyStruc){
                $dt_def = @$dtyStruc[$dt_id];
                if($dt_def==null) {return false;}

                $idx_fieldtype = $dtyStruc['commonNamesToIndex']['dty_Type'];
                $idx_term_tree = $dtyStruc['commonNamesToIndex']['dty_JsonTermIDTree'];
                $idx_term_nosel = $dtyStruc['commonNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];
            }
        }else{
            $idx_fieldtype = $recStruc['dtFieldNamesToIndex']['dty_Type'];
            $idx_term_tree = $recStruc['dtFieldNamesToIndex']['rst_FilteredJsonTermIDTree'];
            $idx_term_nosel = $recStruc['dtFieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];
        }


        $is_termid = false;
        if(ctype_digit($r_value2)){ //value is numeric try to compare with trm_ID
            $is_termid = VerifyValue::isValidTerm( $dt_def[$idx_term_tree], $dt_def[$idx_term_nosel], $r_value2, $dt_id);
        }

        if($is_termid){
            $term_id = $term_value;
        }else{
            //strip accents on both sides
            $term_id = VerifyValue::isValidTermLabel($dt_def[$idx_term_tree], $dt_def[$idx_term_nosel], $r_value2, $dt_id, true );

            if(!$term_id){
                $term_id = VerifyValue::isValidTermCode($dt_def[$idx_term_tree], $dt_def[$idx_term_nosel], $r_value2, $dt_id );
            }
        }

        if (!$term_id)
        {   //not found
            return false;
        }else{
            return $term_id;
        }
    }

}

//
// Import term by label for given field
// 1.Detect vocabulary or set of terms
// 2.Get parent term id
// 3.Add new term
//
private static function addNewTerm($recTypeID, $dt_id, $term_label, $dbdefs){

//@todo use dbDefTerms _prepareddata.push({trm_Label:lbl, trm_ParentTermID:trm_ParentTermID, trm_Domain:'enum'});

    $recStruc = $dbdefs['rectypes']['typedefs'];
    $dt_def = $recStruc[$recTypeID]['dtFields'][$dt_id];
    $idx_fieldtype = $recStruc['dtFieldNamesToIndex']['dty_Type'];
    $idx_term_tree = $recStruc['dtFieldNamesToIndex']['rst_FilteredJsonTermIDTree'];
    $idx_term_nosel = $recStruc['dtFieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];

    $defs = $dt_def[$idx_term_tree];
    $defs_nonsel = $dt_def[$idx_term_nosel];

    $domain = $dt_def[$idx_fieldtype]=='enum'?'enum':'relation';//for domain

    $terms = getTermsFromFormat($defs);//see dbsData.php

    if (($cntTrm = count($terms)) > 0) {

        if ($cntTrm > 1) {  //vocabulary
            $nonTerms = getTermsFromFormat($defs_nonsel);//see dbsData.php
            if (count($nonTerms) > 0) {
                $terms = array_diff($terms, $nonTerms);
            }
        }
        if (count($terms)<1) {
            //@todo - add or find Added Terms vocabulary
            return -1;
        }

        $parentID = $terms[0];

        $mysqli = self::$system->get_mysqli();

        $query = 'select trm_ID from defTerms where trm_ParentTermID='
                        .$parentID.' and trm_Label="'.$mysqli->real_escape_string($term_label).'"';
        $trmID = mysql__select_value($mysqli, $query);
        if($trmID>0){
            //already exists

        }else{
            //add new
            $trmID = mysql__insertupdate($mysqli, 'defTerms', 'trm',
                array('trm_Label'=>$term_label, 'trm_ParentTermID'=>$parentID, 'trm_Domain'=>$domain ));
        }

        return $trmID;

    }else{
        return -1; //terms for field not defined
    }


}

}
?>

