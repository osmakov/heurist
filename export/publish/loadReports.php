<?php

/**
*
* loadReports.php : load the particular smarty report or list of reports
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

require_once dirname(__FILE__).'/../../autoload.php';


$system = new hserv\System();
if( !$system->init(@$_REQUEST['db']) ){
    $system->errorExit();
}

if(!$system->hasAccess()){
   $system->errorExit( 'To perform this action you must be logged in',  HEURIST_REQUEST_DENIED);
}

header(CTYPE_JSON);

global $sys_usrReportSchedule_ColumnNames;

$sys_usrReportSchedule_ColumnNames = array(
    "rps_ID"=>"i",
    "rps_Type"=>"s",
    "rps_Title"=>"s",
    "rps_FilePath"=>"s",
    "rps_URL"=>"s",
    "rps_FileName"=>"s",
    "rps_HQuery"=>"s",
    "rps_Template"=>"s",
    "rps_IntervalMinutes"=>"i"
    );

$metod = @$_REQUEST['method'];

$mysqli = $system->getMysqli();

    if($metod=="searchreports"){

        //search the list of users by specified parameters
        $f_id     = @$_REQUEST['recID'];
        $f_name = $mysqli->real_escape_string(filter_var(@$_REQUEST['name'], FILTER_SANITIZE_STRING));
        $f_userid = @$_REQUEST['usrID'];//@todo

        $records = array();
        $recordsCount = 0;

        //loads list of all records

        $query = "select rps_ID, rps_Type, rps_Title, rps_FilePath, rps_URL, rps_FileName, rps_HQuery, rps_Template, rps_IntervalMinutes, 0 as selection, 0 as status from usrReportSchedule";

        if($f_name && $f_name!=""){
            $query = $query." where rps_Title like '%".$f_name."%'";
        }

        $res = $mysqli->query($query);

        while ($row = $res->fetch_assoc()) {

            $row['status'] = getStatus($row);

            array_push($records, $row);
        }
        $res->close();

        $response = array("status"=>HEURIST_OK, "data"=>$records);
        print json_encode($response);

    }elseif($metod=="getreport"){ //-----------------

        $recID = @$_REQUEST['recID'];
        if ($recID==null) {
              $system->errorExit('Invalid call to loadReports, recID is required');
        }

        $colNames = array("rps_ID", "rps_Type", "rps_Title", "rps_FilePath", "rps_URL", "rps_FileName", "rps_HQuery", "rps_Template", "rps_IntervalMinutes");

        $records = array();
        $records['fieldNames'] = $colNames;
        $records['records'] = array();

        $query = "select ".join(",", $colNames)." from usrReportSchedule ";

        if($recID>0){
            $query = $query." where rps_ID=".intval($recID);
            $res = $mysqli->query($query);
            while ($row = $res->fetch_row()) {
                $records['records'][$row[0]] = $row;
            }
            $res->close();
        }

        $response = array("status"=>HEURIST_OK, "data"=>$records);
        print json_encode($response);

    }elseif($metod=="savereport"){ //-----------------

        $data  = @$_REQUEST['data'];


        if (!array_key_exists('report',$data) ||
        !array_key_exists('colNames',$data['report']) ||
        !array_key_exists('defs',$data['report'])) {
              $system->errorExit('Invalid data structure sent with savereport method call to loadReports.php');
        }

        $colNames = $data['report']['colNames'];

        $rv = array();//result

        foreach ($data['report']['defs'] as $recID => $rt) {
            array_push($rv, updateReportSchedule($mysqli, $colNames, intval($recID), $rt));
        }

        $response = array("status"=>HEURIST_OK, "data"=>$rv);
        print json_encode($response);

    }elseif($metod=="deletereport"){

        $recID  = @$_REQUEST['recID'];
        $rv = array();
        if (!($recID>0)) {
              $system->errorExit('Invalid  or not ID sent with deletereport method call to loadReports.php');
        }else{
            $rv = deleteReportSchedule($mysqli, $recID);
            if(@$rv['error']){
                $response = $system->addError(HEURIST_ERROR, $rv['error']);
            }else{
                $response = array("status"=>HEURIST_OK, "data"=>$rv['result']);
            }
            print json_encode($response);
        }
    }else{
        $system->errorExit('Invalid or no method provided to loadReports.php');
    }

exit;

    /**
    * @return 0 - ok,  1 - template file missed, 2 - output folder does not exist, 2 - file does not exist
    */
    function getStatus($row){

        if(!file_exists(HEURIST_SMARTY_TEMPLATES_DIR.$row['rps_Template'])){
            return 1;
        }

        if($row['rps_FilePath']!=null){
            $dir = $row['rps_FilePath'];
            if(substr($dir,-1)!="/") {$dir = $dir."/";}
        }else{
            $dir = HEURIST_FILESTORE_DIR."generated-reports/";
        }

        if(!file_exists($dir)){
            return 2;
        }

        $filename = ($row['rps_FileName']!=null)?$row['rps_FileName']:$row['rps_Template'];

        $outputfile = $dir.$filename;

        $path_parts = pathinfo($outputfile);
        $ext = array_key_exists('extension',$path_parts)?$path_parts['extension']:null;
        if ($ext == null) {
            $outputfile = $outputfile.".html";
        }

        if(!file_exists($outputfile)){
            return 3;
        }else{
            return 0;
        }
    }

    /**
    * deleteReportSchedule - delete record from
    * @author Artem Osmakov
    * @param $recID record ID to delete
    * @return $ret record id that was deleted or error message
    **/
    function deleteReportSchedule($mysqli, $recID) {

        $ret = array();

        //delete references from user-group link table
        $query = 'delete from usrReportSchedule where rps_ID='.intval($recID);
        $res = $mysqli->query($query);
        if ( $mysqli->error ) {
            $ret['error'] = 'Db error deleting record from report schedules';
        }else{
            $ret['result'] = $recID;
        }

        return $ret;
    }

    /**
    *
    *
    * @param mixed $colNames
    * @param mixed $recID
    * @param mixed $rt
    */
    function updateReportSchedule($mysqli, $colNames, $recID, $values){

        global $sys_usrReportSchedule_ColumnNames;

        $ret = null;

        if (!isEmptyArray($colNames) && is_array($values)){

            $isInsert = ($recID<0);

            $query = "";
            $fieldNames = "";
            $parameters = array("");
            $fieldNames = join(",",$colNames);
            $rps_Title = '';

            foreach ($colNames as $colName) {

                $val = array_shift($values);

                if (array_key_exists($colName, $sys_usrReportSchedule_ColumnNames))
                {

                    if($query!="") {$query = $query.",";}

                    if($isInsert){
                            $query = $query."?";
                    }else{
                            $query = $query."$colName = ?";
                    }

                    $parameters[0] = $parameters[0].$sys_usrReportSchedule_ColumnNames[$colName];//take datatype from array
                    array_push($parameters, $val);

                    if($colName=='rps_Title'){
                        $rps_Title = $val;
                    }

                }
            }//for columns

            if($query!=""){
                if($isInsert){
                    $query = "insert into usrReportSchedule (".$fieldNames.") values (".$query.")";
                    $recID = -1;
                }else{
                    $query = "update usrReportSchedule set ".$query." where rps_ID = $recID";
                }

                //check duplication
                $rid = mysql__select_value($mysqli, 'SELECT rps_ID FROM usrReportSchedule WHERE rps_ID!='
                    .$recID.' AND rps_Title="'.$rps_Title.'"');
                if($rid>0){

                    $ret = 'Duplicate entry. There is already report with the same name.';

                }else{



                    //temporary alter the structure of table 2016-05-17 - remark it in one year
                    $res = $mysqli->query("SHOW FIELDS FROM usrReportSchedule where Field='rps_IntervalMinutes'");
                    $struct = $res->fetch_assoc();
                    if(strpos($struct['Type'],'tinyint')!==false){
                        $mysqli->query('ALTER TABLE `usrReportSchedule` CHANGE COLUMN `rps_IntervalMinutes` `rps_IntervalMinutes` INT NULL DEFAULT NULL');
                    }

                    $rows = mysql__exec_param_query($mysqli, $query, $parameters, true);

                    if ($rows==0 || is_string($rows) ) {
                        $oper = (($isInsert)?"inserting":"updating");
                        $ret = "error $oper in updateReportSchedule - ".$rows.' '.$query;
                    } else {
                        if($isInsert){
                            $ret = -$mysqli->insert_id;
                        }else{
                            $ret = $recID;
                        }
                    }
                }
            }

        }//if column names


        if ($ret==null){
            $ret = "no data supplied for updating report - $recID";
        }

        return $ret;
    }
?>