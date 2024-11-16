<?php
/**
* Service
* fileUpload.php - file uploader handler
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

use hserv\utilities\USanitize;
use hserv\utilities\USystem;
use hserv\utilities\UImage;
use hserv\utilities\UploadHandler;
use hserv\entity\DbRecUploadedFiles;

require_once dirname(__FILE__).'/../../autoload.php';

require_once 'entityScrudSrv.php';

$response = null;
$system = new hserv\System();

$post_max_size = USystem::getConfigBytes('post_max_size');
$params = null;

if(intval($_SERVER['CONTENT_LENGTH'])>$post_max_size){

        $response = '<p class="heurist-message">The upload size of '.$_SERVER['CONTENT_LENGTH'].' bytes exceeds the limit of '.ini_get('post_max_size')
        .'.<br><br>If you need to upload larger files please contact the system administrator '.HEURIST_MAIL_TO_ADMIN.'</p>';
        /*
            $response = $system->addError(HEURIST_ACTION_BLOCKED, $response);
        */
}elseif($system->init(@$_REQUEST['db'])){

    $params = USanitize::sanitizeInputArray();

    //define upload folder
    $entity_name = null;
    $is_autodect_csv = (@$params['autodect']==1);
    $recID = @$params['recID'];
    $registerAtOnce = (@$params['registerAtOnce']==1);
    $tiledImageStack = (@$params['tiledImageStack']==1);//unzip archive and copy to uploaded_tilestacks

    $new_file_name = @$params['newfilename'];
    if($new_file_name){
        $new_file_name = basename($new_file_name);
        $new_file_name = USanitize::sanitizeFileName($new_file_name, false);
    }

    if(@$params['entity']){
        $entity_name = entityResolveName($params['entity']);
        if(!$entity_name){
            $response = $system->addError(HEURIST_INVALID_REQUEST, 'Wrong entity parameter');
        }
    }
    if($response==null){
        if ( !$system->has_access() ) { //not logged in
                $response = $system->addError(HEURIST_REQUEST_DENIED);
        }elseif($entity_name=='sysGroups' || $entity_name=='sysUsers') {
                if(!$system->has_access($recID)){ //only user or group admin
                  $response = $system->addError(HEURIST_REQUEST_DENIED);
                }
        }elseif(!($entity_name=='recUploadedFiles' || $entity_name=='sysBugreport'))
        { //for all other entities other than recUploadedFile must be admin of dbowners group
                if(!$system->is_admin()){
                  $response = $syfstem->addError(HEURIST_REQUEST_DENIED);
                }
        }
    }
    if($entity_name==null){
        $response = $system->addError(HEURIST_INVALID_REQUEST, error_WrongParam('"entity"'));
    }

    if(!$response){

        $quota = $system->settings->getDiskQuota();//takes value from disk_quota_allowances.txt
        $quota_not_defined = (!($quota>0));
        if($quota_not_defined){
            $quota = 1073741824; //1GB
        }
        $usage = filestoreGetUsageByScan($system);


        $content_length = (int)@$_SERVER['CONTENT_LENGTH'];
        $file_length = (int)(@$params['fileSize']>0?@$params['fileSize']:$content_length);

        if($usage + $file_length > $quota){ //check quota

            $error = 'The allowed disk quota ('.($quota/1048576).'Mb) for this database is reached';
            $response = $system->addError(HEURIST_ACTION_BLOCKED, $error);
            $response['message'] = $error . '<br><br>If you need more disk space please contact the system administrator ' . HEURIST_MAIL_TO_ADMIN;

            sendEmailToAdmin('Allowed disk quota reached', 'Database '.$system->dbname().'. '.$error, false);

        }elseif ($quota_not_defined && $post_max_size && ($content_length > $post_max_size)) { //quota not defined - multipart upload disabled

        }

    }

}else{
    $response = $system->getError();
}

if($response!=null){
    header(CTYPE_JSON);
    http_response_code(406);
    if(is_array($response)){
        print json_encode($response);
    }else{
        print $response;
    }
    exit;
}

    $scratchDir = $system->getSysDir(DIR_SCRATCH);
    $scratchUrl = $system->getSysUrl(DIR_SCRATCH);

    if($entity_name=="temp"){//redirect uploaded content back to client side after some processing
                                   // for example in term list import

        $max_file_size = intval(@$params['max_file_size']);

        $options = array(
                'upload_dir' => $scratchDir,
                'upload_url' => $scratchUrl,
                'max_file_size' => $max_file_size,
                // 'unique_filename' => false,  force unique file name
                //'image_versions' => array()
                //'print_response' => false,
                //'download_via_php' => 1
                );

    }
    elseif($entity_name=="recUploadedFiles"){
        
        $options = array(
                'upload_dir' => $scratchDir,
                'upload_url' => $scratchUrl,
                'unique_filename' => false,
                'newfilename' => $new_file_name,
                'correct_image_extensions' => true,
                'image_versions' => array(
                    ''=>array(
                        'auto_orient' => true,
                        ),
                    'thumbnail'=>array(
                        'auto_orient' => true,
                        'upload_dir' => $scratchDir.DIR_THUMBS,
                        'upload_url' => $scratchUrl.DIR_THUMBS,
                        'max_width' => 200,
                        'max_height' => 200,
                        'scale_to_png' => true
                    )
                )
                //'max_file_size' => 1024,
                //'print_response ' => false
        );

        allowWebAccessForForlder($scratchDir.DIR_THUMBS);

    }
    else{

        $entityDir = $system->getSysDir(DIR_ENTITY.$entity_name);

        $version = @$params['version']!='icon'?'thumbnail':'icon';
        $maxsize = intval(@$params['maxsize'])>0?intval($params['maxsize']):120; //dimension

        $options = array(
                'upload_dir' => $entityDir,
                'upload_url' => $system->getSysUrl(DIR_ENTITY.$entity_name),
                'unique_filename' => false,
                'newfilename' => $new_file_name,
                'correct_image_extensions' => true,
                'image_versions' => array(
                    ''=>array(
                        'auto_orient' => true,
                        ),
                    $version=>array(
                        'auto_orient' => true,
                        'max_width' => $maxsize,
                        'max_height' => $maxsize,
                        'scale_to_png' => true
                    )
                )

                //'max_file_size' => 1024,
                //'print_response ' => false
        );

        allowWebAccessForForlder($entityDir.$version.'/');

    }

    if(@$params['acceptFileTypes']){
        /*
        //all these complexity needs to avoid Path Traversal warning
        $allowed_exts = array();
        $allowed_exts_2 = explode('|', $params['acceptFileTypes']);
        foreach($allowed_exts_2 as $ext){
            if(in_array(strtolower($ext), $allowed_exts_all)){
                $idx = array_search(strtolower($ext), $allowed_exts_all);
                if($idx>=0) {$allowed_exts[] = $allowed_exts_all[$idx];}
            }
        }
        */
        $options['accept_file_types'] = 'zip|mbtiles';
    }else{
        $allowed_exts = mysql__select_list2($system->get_mysqli(), 'select fxm_Extension from defFileExtToMimetype');
        $options['accept_file_types'] = implode('|', $allowed_exts);
    }

    $options['print_response'] = false;

    $options['database'] = $system->dbname();

    $upload_handler = new UploadHandler($options);// from 3d party uploader

    //@todo set print_response=false
    //and send to client standard HEURIST response
    $response = null;
    $res = $upload_handler->get_response();//it returns file object  $res['size]

    foreach($res['files'] as $idx=>$file){
        if(@$file->error){
            $sMsg = "Sorry, file was not processed due to the following reported error:\n\n".$file->error.".\n\n";// Error Log

            if(strpos($file->error, 'Filetype not')===0 || strpos($file->error, 'File with the same name')===0){

                $response = $system->addError(HEURIST_ACTION_BLOCKED, $sMsg, null);

            }else{

                if(false && strpos($file->error, 'Filetype not')===false &&
                   strpos($file->error, 'ownership permissions')===false &&
                   strpos($file->error, 'post_max_size')===false){
                    $sMsg = $sMsg.' The most likely cause is that the file extension ('. ($file->type?$file->type:'XXX!') .') is not currently enabled for the upload function, jquery UploadHandler. Please use the bug report link above to request addition of this file type.';
                }

                $response = $system->addError(HEURIST_UNKNOWN_ERROR, $sMsg, null);
            }

            if(strpos($file->error, 'post_max_size')!=false){
                $sMsg .= $sMsg . '<br><br>If you need to upload larger files please contact the system administrator ' . HEURIST_MAIL_TO_ADMIN;
            }
            $response['message'] = nl2br($sMsg);

            break;
        }

        if( !($file->size_total>0) || $file->size_total==$file->size){

            if($entity_name=="recUploadedFiles"){ //register at once

                if($registerAtOnce==1){

                    $entity = new DbRecUploadedFiles($system);
                    $ret = $entity->registerFile($file, null, true, $tiledImageStack);//it returns ulf_ID

                    if( is_bool($ret) && !$ret ){
                        $response = $system->getError();
                    }else{
                        $file->ulf_ID = $ret;
                    }
                }elseif(!@$file->thumbnailUrl){ //if UploadHandler does not create thumb - creates it as image with text (file extension)

                    $thumb_file = HEURIST_SCRATCH_DIR.DIR_THUMBS.$new_file_name;
                    $img = UImage::createFromString($file->type?$file->type:'XXX!');
                    imagepng($img, $thumb_file);//save into file
                    imagedestroy($img);
                    $res['files'][$idx] ->thumbnailUrl = $scratchUrl.DIR_THUMBS.$new_file_name;
                }

            }
            elseif($entity_name=="temp" && $is_autodect_csv) {

                $filename = $scratchDir.basename($file->original_name);

                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $isKML = ($extension=='kml' || $extension=='kmz');
                if($isKML){ //no need to detect params for kml
                    $res['files'][$idx]->isKML = true;
                }else{
                    $csv_params = autoDetectSeparators( $filename );
                    if(is_array($csv_params) && !@$csv_params['error']){
                        $res['files'][$idx]->csv_params = $csv_params;
                    }
                }
            }
        }
    }

    if($response==null){
        $response = array("status"=>HEURIST_OK, "data"=> $res);
    }
    header(CTYPE_JSON);
    print json_encode($response);

    $system->dbclose();

//------------
//  NOT USED. verification of uploaded file integrated with UploadHandler
//
function postmode_file_selection() {

    $param_name = 'file';

    // there are two ways into the file selection mode;
    // either the user has just arrived at the import page,
    // or they've selected a file *and might progress to file-parsing mode*
    $error = '';
    if (@$_FILES[$param_name]) {
        if ($_FILES[$param_name]['size'] == 0) {
            $error = 'no file was uploaded';
        } else {
            switch ($_FILES[$param_name]['error']) {
                case UPLOAD_ERR_OK:
                    break;
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $error = "The uploaded file was too large.  Please consider importing it in several stages.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error = "The uploaded file was only partially uploaded.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error = "No file was uploaded.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $error = "Missing a temporary folder.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $error = "Failed to write file to disk";
                    break;
                default:
                    $error = "Unknown file error";
            }


            $content_length = USystem::fixIntegerOverflow((int)@$_SERVER['CONTENT_LENGTH']);

            $post_max_size = USystem::getConfigBytes('post_max_size');
            if ($post_max_size && ($content_length > $post_max_size)) {
                $error = 'The uploaded file exceeds the post_max_size directive in php.ini';
            }else{
                if ($_FILES[$param_name]['tmp_name'] && is_uploaded_file($_FILES[$param_name]['tmp_name'])) {
                    $file_size = getFileSize($_FILES[$param_name]['tmp_name']);
                } else {
                    $file_size = $content_length;
                }
                $file_max_size = USystem::getConfigBytes('upload_max_filesize');
                if ($file_max_size && ($content_length > $file_max_size)) {
                    $error = 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
                }

            }
        }

        if (!$error) {    // move on to the next stage!
            //$error = postmode_file_load_to_db($_FILES[$param_name]['tmp_name'], $_FILES[$param_name]['name'], true);
        }
    }

    return $error;
}
?>
