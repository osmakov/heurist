<?php
    /*
    * Copyright (C) 2005-2023 University of Sydney
    *
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
    * in compliance with the License. You may obtain a copy of the License at
    *
    * https://www.gnu.org/licenses/gpl-3.0.txt
    *
    * Unless required by applicable law or agreed to in writing, software distributed under the License
    * is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
    * or implied. See the License for the specific language governing permissions and limitations under
    * the License.
    */

    /**
    * Correct file paths in recUploadFiles table, find missed and orphaned images
    *
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @copyright   (C) 2005-2023 University of Sydney
    * @link        https://HeuristNetwork.org
    * @version     3.1
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */

use hserv\utilities\USanitize;

require_once dirname(__FILE__).'/../../autoload.php';

header(CTYPE_JSON);

$rv = array();

// init main system class
$system = new hserv\System();

$req_params = USanitize::sanitizeInputArray();

if(!$system->init(@$req_params['db'])){
    $response = $system->getError();
    print json_encode($response);
    return;
}


if (!$system->is_dbowner()) {
    $response = $system->addError(HEURIST_REQUEST_DENIED,
                 'To perform this action you must be logged in as Database Owner');
    print json_encode($response);
    return;
}

$mysqli = $system->get_mysqli();


$data = null;
if(@$req_params['data']){
    $data = json_decode(urldecode(@$req_params['data']), true);
}else{
    $response = $system->addError(HEURIST_INVALID_REQUEST,
                 'Data not defined! Wrong request.');
    print json_encode($response);
    return;
}

    //------------------------------------------------------
    //Remove non-registred files
    $files_to_remove = @$data['files_notreg'];
    if(is_array($files_to_remove)){

        $res = array();
        foreach ($files_to_remove as $file) {

            $realpath_file = isPathInHeuristUploadFolder($file);//snyk SSRF

            if($realpath_file && file_exists($realpath_file) && unlink($realpath_file)) 
            {
                array_push($res, $file);
            }
        }
        $response = array("status"=>HEURIST_OK, "data"=> $res);
        print json_encode($response);
        exit;
    }

    //------------------------------------------------------
    // remove registration for nonused entries in ulf
    $regs_to_remove = @$data['unused_file_local'];
    if(!is_array($regs_to_remove)){
        $regs_to_remove = @$data['unused_file_remote'];
    }
    if(!isEmptyArray($regs_to_remove)){

        $mysqli->query('delete from recUploadedFiles where ulf_ID in ('.implode(',',$regs_to_remove).')');
        if ( $mysqli->error ) {
            $response = $system->addError(HEURIST_DB_ERROR,
                'Cannot delete entries from recUploadedFiles. mySQL error: '.$mysqli->error);
        }else{
            $response = array("status"=>HEURIST_OK, "data"=> $regs_to_remove);

        }
        print json_encode($response);
        exit;
    }

    //------------------------------------------------------
    // remove missed files
    $file_ids = @$data['files_notfound'];
    if(!isEmptyArray($file_ids)){

        $mysqli->query('delete from recDetails where dtl_UploadedFileID in ('.implode(',',$file_ids).')');
        if ($mysqli->error) {
            $response = $system->addError(HEURIST_DB_ERROR,
                'Cannot delete entries from recDetails. mySQL error: '.$mysqli->error);
        }else{
            $mysqli->query('delete from recUploadedFiles where ulf_ID in ('.implode(',',$file_ids).')');
            if ($mysqli->error) {
                $response = $system->addError(HEURIST_DB_ERROR,
                    'Cannot delete entries from recUploadedFiles. mySQL error: '.$mysqli->error);
            }else{
                $response = array("status"=>HEURIST_OK, "data"=> $file_ids);
            }
        }

        print json_encode($response);
        exit;
    }

    print json_format($rv);
    $response = $system->addError(HEURIST_INVALID_REQUEST,
                                'Wrong parameters. No data defined');
    print json_encode($response);
