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
* BACKWARD capabilities for old versions. It has been replaced with DbRegis::registrationGet
*
* getDatabaseURL.php - requests URL for registered DB by its ID from Heurist Reference Index
*
* this script may be inited via http, otherwise it is included and $database_id already defined
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/../../../autoload.php';

$isOutSideRequest = (strpos(strtolower(HEURIST_INDEX_BASE_URL), strtolower(HEURIST_SERVER_URL))===false);//this is reference server
if($isOutSideRequest){ //this is request from outside - redirect to master index

    $reg_url = HEURIST_INDEX_BASE_URL
    .'admin/setup/dbproperties/getDatabaseURL.php?db='.HEURIST_INDEX_DATABASE
    .'&remote=1&id='.$database_id;

    $data = loadRemoteURLContentSpecial($reg_url);//get registered database URL

    if (!$data) {

        $error_code = (!empty($glb_curl_error)) ? $glb_curl_error : 'Error code: 500 Heurist Error';

        $error_msg = "Unable to connect Heurist Reference Index, possibly due to timeout or proxy setting<br>"
            . $error_code . "<br>"
            . "URL requested: ".$reg_url."<br><br>";
    }else{

        $data = json_decode($data, true);

        // Artem TODO: What circumstance would give rise to this? Explain how the data is 'wrong'/'incorrect'
        // Artem: cannot connect to Master Reference Database, Records table is corrupted, $database_id is not found
        if(@$data['error_msg']){
            $error_msg = $data['error_msg'];
        }elseif(!@$data['rec_URL']){
            $error_msg = "Heurist Reference Index returns incorrect data for registered database # ".$database_id.
            " The page may contain an invalid database reference (0 indicates no reference has been set)";
        }else{
            $database_url = $data['rec_URL'];
        }
    }

}else{
    //on this server

    $system2 = new hserv\System();
    $system2->init(HEURIST_INDEX_DATABASE, true, false);//init without paths and consts

    if(@$_REQUEST['remote']){
        $database_id = @$_REQUEST["id"];
    }
    $rec = array();
    if($database_id>0){

        ConceptCode::setSystem($system2);
        $rty_ID_registered_database = ConceptCode::getRecTypeLocalID(HEURIST_INDEX_DBREC);

        $rec = mysql__select_row_assoc($system2->getMysqli(),
            'select rec_Title, rec_URL from Records where rec_RecTypeID='
            .$rty_ID_registered_database.' and rec_ID='  //1-22
            .$database_id);

        if ($rec!=null){
            $database_url = @$rec['rec_URL'];
            if($database_url==null || $database_url==''){
                $error_msg = 'Database URL is not set in Heurist_Reference_Index database for database ID#'.$database_id;
            }

        }else{
            $err = $system2->getMysqli()->error;
            if(err){
                $error_msg = 'Heurist Reference Index database is not accessible at the moment. Please try later';
            }else{
                $error_msg = 'Database with ID#'.$database_id.' is not found in Heurist Reference Index';
            }
        }
    }else{
        $error_msg = 'Database ID is not set or invalid. It must be an interger positive value.';
    }

    if(@$_REQUEST['remote']) {
        header(CTYPE_JS);

        if(isset($error_msg)){
            $res = array('error_msg'=>$error_msg);
        }else{
            $res = array('rec_URL'=>$database_url);
        }
        print json_encode($res);
    }
}
?>