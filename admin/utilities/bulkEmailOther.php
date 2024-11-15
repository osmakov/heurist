<?php

/**
*  Email Users of any Heurist database located on this server, requires a Heurist Database + System Administrator password
*  For server calls from main form
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Brandon McKay   <blmckay13@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

define('PDIR','../../');//need for proper path to js and css

use hserv\utilities\USanitize;
use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/../../autoload.php';

header(CTYPE_JSON);

$system = new hserv\System();

$sysadmin_pwd = USanitize::getAdminPwd('sysadmin_pwd');

$data = null;
$response = array();
$rtn = false;

$isSystemInited = $system->init(@$_REQUEST['db']);

if(!$isSystemInited) {

    $response = $system->getError();
    $rtn = json_encode($response);

    print $rtn;
    exit;
}

$mysqli = $system->get_mysqli();

if(isset($_REQUEST['get_email']) && isset($_REQUEST['recid'])) {/* Get the Title and Short Summary field for the selected id, id is for Email record */

    $email_title = "";
    $email_body = "";
    $id = intval($_REQUEST['recid']);

    // Validate ID
    if(!is_numeric($id) || intval($id) < 1){

        $response = array("status"=>HEURIST_ACTION_BLOCKED, "message"=>"An invalid Email record id was provided.", "request"=>htmlspecialchars($id));
        $system->addError(HEURIST_ERROR, "Bulk Email Other: The record IDs used for the Email selector are invalid or have not been retrieved correctly. Invalid ID => " . htmlspecialchars($_REQUEST['recid']));
        $rtn = json_encode($response);

        print $rtn;
        exit;
    }

    // Get title/name and short summary detail type ids
    $title_detailtype_id = ConceptCode::getDetailTypeLocalID("2-1");
    $shortsum_detiltype_id = ConceptCode::getDetailTypeLocalID("2-3");
    if (empty($title_detailtype_id) || empty($shortsum_detiltype_id)) {
        $missing = "";

        if(empty($title_detailtype_id) && empty($shortsum_detiltype_id)){
            $missing = "for both title and short summary detail types.";
        }else{
            $missing = empty($title_detailtype_id) ? "for the title detail type." : "for the short summary detail type.";
        }

        $response = array("status"=>HEURIST_ACTION_BLOCKED, "message"=>"Unable to retrieve the local id $missing <br>If this problem persists, please notify the Heurist team.");

        $rtn = json_encode($response);

        print $rtn;
        exit;
    }

  $query = "SELECT dtl_Value, dtl_DetailTypeID
            FROM recDetails
            WHERE dtl_RecID = $id AND dtl_DetailTypeID IN (".$shortsum_detiltype_id.", ".$title_detailtype_id.")";

  $detail_rtn = $mysqli->query($query);
  if(!$detail_rtn){

    $response = array("status"=>HEURIST_ACTION_BLOCKED, "message"=>"Unable to retrieve the details of Email record ID => $id.<br>If this persists, please notify the Heurist team.<br>", "error_msg"=>$mysqli->error, "request"=>$id);
    $rtn = json_encode($response);

    print $rtn;
    exit;
  }

  while($email_dtl = $detail_rtn->fetch_row()){
      if($email_dtl[1] == $shortsum_detiltype_id){
          $email_body = $email_dtl[0];
      }elseif($email_dtl[1] == $title_detailtype_id){
          $email_title = $email_dtl[0];
      }
  }

  $data = array($email_title, $email_body);

    $response = array("status"=>HEURIST_OK, "data"=>$data, "request"=>$id);
    $rtn = json_encode($response);

    print $rtn;

} elseif(isset($_REQUEST['db_filtering'])) { /* Get a list of DBs based on the list of provided filters, first search gets all dbs */

    $db_request = $_REQUEST['db_filtering'];
    $dbs = array();// list of databases
    $databases = array();// array of database details
    $invalid_dbs = array();

    // Get all dbs that start with the Heurist prefix
    $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE `SCHEMATA`.`SCHEMA_NAME` LIKE '".HEURIST_DB_PREFIX."%' ORDER BY `SCHEMATA`.`SCHEMA_NAME` COLLATE utf8_general_ci";

    $db_list = $mysqli->query($query);
    if (!$db_list) {

        $response = array("status"=>HEURIST_ACTION_BLOCKED, "message"=>"Unable to retrieve a list of Heurist databases.<br>", "error_msg"=>$mysqli->error, "request"=>$db_request);
        $rtn = json_encode($response);

        print $rtn;
        exit;
    }

    while($db = $db_list->fetch_row()){

        //check version - use >=1.3.0
        $query = 'SELECT sys_dbVersion, sys_dbSubVersion from '.$db[0].'.sysIdentification';
        $ver = mysql__select_row_assoc($mysqli, $query);
        if(!$ver){
            continue; //skip - broken database
        }else{
            if($ver['sys_dbSubVersion']<3){
                continue; //skip - old database
            }
        }


        // Ensure that the Heurist db has the required tables, ignore if they don't
        $dbname = $db[0];
        if(preg_match('/[^A-Za-z0-9_\$]/', $db_name)){ //invalid dbname
            continue;
        }
        $query = "SHOW TABLES IN $dbname WHERE Tables_in_$dbname = 'Records' OR Tables_in_$dbname = 'recDetails' OR Tables_in_$dbname = 'sysUGrps' OR Tables_in_$dbname = 'sysUsrGrpLinks'";

        $table_listing = $mysqli->query($query);
        if (!$table_listing || mysqli_num_rows($table_listing) != 4) { // Skip, missing required tables

            if($table_listing && $db_request == "all"){
                $invalid_dbs[] = $db[0];
            }

          continue;
        }

        $dbs[] = $db[0];
    }//while

    if($db_request == "all"){ // No additional filtering needed

        $data = array('list' => $dbs, 'details' => array());
        $details = getDatabaseDetails($mysqli, $dbs);
        $data['details'] = $details;

    } elseif(is_array($db_request) && count($db_request)==4){ // Do filtering, record count and last modified

        $count = intval($db_request['count']);

        $lastmod_logic = $mysqli->real_escape_string( filter_var($db_request['lastmod_logic'],FILTER_SANITIZE_STRING) );
        $lastmod_logic = $lastmod_logic == 'more' ? '<=' : '>=';
        $lastmod_period = intval($db_request['lastmod_period']);

        //to avoid injection
        $lastmod_unit = 'ALL';
        switch (strtoupper(@$db_request['lastmod_unit'])) {
            case 'DAY':  $lastmod_unit = 'DAY'; break;
            case 'MONTH':  $lastmod_unit = 'MONTH'; break;
            case 'YEAR':  $lastmod_unit = 'YEAR'; break;
            default;
        }

        $lastmod_where = ($lastmod_unit!="ALL") ? "AND rec_Modified " . $lastmod_logic
                    . " date_format(curdate(), '%Y-%m-%d') - INTERVAL "
                    . $lastmod_period . " " . $lastmod_unit . " " : "";

        foreach ($dbs as $db) {

            $db = preg_replace(REGEX_ALPHANUM, "", $db);

            $query = "SELECT count(*)
                                FROM (
                                    SELECT *
                                    FROM `$db`.Records AS rec
                                    WHERE rec_Title IS NOT NULL
                                    AND rec_Title NOT LIKE 'Heurist System Email Receipt%'
                                    AND rec_FlagTemporary != 1
                                    AND rec_Title != '' " . $lastmod_where . "
                                ) AS a";

            $count_res = $mysqli->query($query);
            if($count_res>0){

                $row = $count_res->fetch_row();

                if($row[0] > $count){
                    $data[] = $db;
                }

            }else{
                $response = array("status"=>HEURIST_ERROR, "message"=>"Unable to filter Heurist databases based on provided filter.<br>", "error_msg"=>$mysqli->error, "request"=>$db_request);
                $rtn = json_encode($response);

                $count_res->close();

                print $rtn;

                exit;

            }
        }
    }

    $response = array("status"=>HEURIST_OK, "data"=>$data, "request"=>$db_request);
    $rtn = json_encode($response);

    print $rtn;

} elseif(isset($_REQUEST['user_count']) && isset($_REQUEST['db_list'])) { // Get a count of distinct users

    $user_request = $_REQUEST['user_count'];
    $dbs = $_REQUEST['db_list'];
    if(!is_array($dbs)){
        $dbs = explode(',', $dbs);
    }

    $data = 0;
    $email_list = array();

    foreach($dbs as $db){

        $db = preg_replace(REGEX_ALPHANUM, "", $db);//for snyk

        if($user_request == "owner"){ // Owners
            $where_clause = "WHERE ugr.ugr_ID = 2";
        }elseif($user_request == "manager"){ // Admins of Database Managers Workgroup

            $where_clause = "WHERE ugl.ugl_Role = 'admin' AND ugr.ugr_Enabled != 'n' AND ugl.ugl_GroupID = 1";

            }elseif($user_request == "admin"){ // Admins for ALL workgroups

                $where_clause = "WHERE ugl.ugl_Role = 'admin' AND ugr.ugr_Enabled != 'n' AND ugl.ugl_GroupID IN
                   (SELECT ugr_ID
                         FROM `" . $db . "`.sysUGrps
                         WHERE ugr_Type = 'workgroup' AND ugr_Enabled != 'n')";

        }elseif($user_request == "user"){ // ALL users
            $where_clause = "WHERE ugr.ugr_Type = 'user' AND ugr.ugr_Enabled != 'n'";
        }else{

            $response = array("status"=>HEURIST_INVALID_REQUEST, "message"=>"Invalid user choice", "request"=>$user_request);
            $rtn = json_encode($response);

            print $rtn;
            exit;
        }

        $query = "SELECT DISTINCT ugr.ugr_FirstName, ugr.ugr_LastName, ugr.ugr_eMail
                          FROM `" . $db . "`.sysUsrGrpLinks AS ugl
                          INNER JOIN `" . $db . "`.sysUGrps AS ugr ON ugl.ugl_UserID = ugr.ugr_ID "
                        . $where_clause;

        $res = $mysqli->query($query);
        if(!$res){
            //Unable to retrieve user count for databases
            continue;
        }

        while($row = $res->fetch_row()){

            if(!in_array($row[2], $email_list)){
                $data += 1;
                $email_list[] = $row[2];
            }
        }

    }

    $response = array("status"=>HEURIST_OK, "data"=>$data, "request"=>$user_request);
    $rtn = json_encode($response);

    print $rtn;

} elseif(isset($_REQUEST['rec_count']) && isset($_REQUEST['db_list'])){ // Get a count of records

    $dbs = $_REQUEST['db_list'];
    if(!is_array($dbs)){
        $dbs = explode(',', $dbs);
    }

    $data = array();
    foreach($dbs as $db){
        if(strpos($db, HEURIST_DB_PREFIX)===0){
            $db = preg_replace(REGEX_ALPHANUM, "", $db);//for snyk
            $query = 'SELECT count(*) FROM `' . $db . '`.`Records` WHERE rec_FlagTemporary != 1';
            $res = $mysqli->query($query);
            if(!$res){
                $data[$db] = 'error';
                continue;
            }

            while($row = $res->fetch_row()){
                $data[$db] = $row[0];
            }
        }
    }

    $response = array("status"=>HEURIST_OK, "data"=>$data, "request"=>implode(',', $dbs));
    $rtn = json_encode($response);

    print $rtn;

} elseif(isset($sysadmin_pwd)) { // Verify Admin Password

    if(!$system->verifyActionPassword($sysadmin_pwd, $passwordForServerFunctions)){
        $data = true;
    } else {
        $data = false;
    }

    $response = array("status"=>HEURIST_OK, "data"=>$data);
    $rtn = json_encode($response);

    print $rtn;
} else { // Invalid Request

    $response = array("status"=>HEURIST_INVALID_REQUEST, "message"=>"invalid request sent", "request"=>$_REQUEST);
    $rtn = json_encode($response);

    print $rtn;
}

//
// Retrieve the record count and last update (record or structure, depending on which is newer)
//  for each provided database
//
function getDatabaseDetails($mysqli, $db_list){


    $details = array();

    // Retrieve record count and last update (record or structure)
    foreach ($db_list as $database) {

        $database = preg_replace(REGEX_ALPHANUM, "", $database);

        $db_data = array('name' => $database, 'rec_count' => 0, 'last_update' => null);
        // Get record count
        $db_data['rec_count'] = mysql__select_value($mysqli, "SELECT COUNT(*) FROM `$database`.Records WHERE rec_FlagTemporary != 1");

        $last_recent = mysql__select_value($mysqli,
        "SELECT CONVERT_TZ(MAX(rec_Modified), @@session.time_zone, \"+00:00\") FROM `$database`.Records WHERE rec_FlagTemporary != 1");

        if(!$last_recent){
            $last_recent = date_create($last_recent);
        }

        $last_struct = getDefinitionsModTime($mysqli, true);

        if(!$last_recent || $last_struct>$last_recent){
            $last_recent = $last_struct;
        }

        $db_data['last_update'] = $last_recent->format('Y-m-d');

        $details[] = $db_data;
    }

    return $details;
}
