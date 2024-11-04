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
    * Verifies missed concept codes for registered databases
    *
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @copyright   (C) 2005-2023 University of Sydney
    * @link        https://HeuristNetwork.org
    * @version     3.1
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */

define('ADMIN_PWD_REQUIRED', 1);
define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';

?>

<script>window.history.pushState({}, '', '<?php echo htmlspecialchars($_SERVER['PHP_SELF']);?>')</script>

<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px">
            <p>This list shows definitions without concept codes for registered databases</p>
<?php

$registered = array();

$mysqli = $system->get_mysqli();

    //1. find all database
    $databases = mysql__getdatabases4($mysqli, true);

    $need_Details = @$_REQUEST['nodty']!=1;
    $need_Terms = @$_REQUEST['noterms']!=1;

 // max ids in Heurist_Core_Def
 // rty 59
 // dty 961
    $all_rty_regs = array();


    foreach ($databases as $idx=>$db_name){

        $db_name = preg_replace(REGEX_ALPHANUM, "", $db_name);//for snyk

        $query = 'SELECT sys_dbRegisteredID from '.$db_name.'.sysIdentification';
        $ver = mysql__select_value($mysqli, $query);
        $ver = intval($ver);
        if(!($ver>0)) {continue;}
/* assign values for unregistered databases
        if($db_name=='hdb_johns_test_028') {continue;}
        $query = 'UPDATE '.$db_name
.'.defRecTypes set rty_IDInOriginatingDB = rty_ID, rty_NameInOriginatingDB = rty_Name, rty_OriginatingDBID=0'
." WHERE (rty_OriginatingDBID='' OR rty_OriginatingDBID=0 OR rty_OriginatingDBID IS NULL "
."OR rty_IDInOriginatingDB='' OR rty_IDInOriginatingDB=0 OR rty_IDInOriginatingDB IS NULL)";
        $mysqli->query($query);
if($mysqli->error){print $query.'  '.$mysqli->error; break;}

$query = 'UPDATE '.$db_name.'.defDetailTypes set dty_IDInOriginatingDB = dty_ID, dty_NameInOriginatingDB = dty_Name, dty_OriginatingDBID=0'
            ." WHERE (dty_ID>56 and dty_ID<73 and dty_OriginatingDBID=0 and dty_IDInOriginatingDB=0)";
            $mysqli->query($query);
if($mysqli->error){print $query.'  '.$mysqli->error; break;}

$query = 'UPDATE '.$db_name
.'.defDetailTypes set dty_IDInOriginatingDB = dty_ID, dty_NameInOriginatingDB = dty_Name, dty_OriginatingDBID=0'
." WHERE (dty_OriginatingDBID='' OR dty_OriginatingDBID=0 OR dty_OriginatingDBID IS NULL "
." OR dty_IDInOriginatingDB='' OR dty_IDInOriginatingDB=0 OR dty_IDInOriginatingDB IS NULL)";
        $mysqli->query($query);
if($mysqli->error){print $query.'  '.$mysqli->error; break;}

$query = 'UPDATE '.$db_name.'.defTerms set trm_IDInOriginatingDB = trm_ID, trm_NameInOriginatingDB = trm_Label,'
            .' trm_OriginatingDBID=0'
            ." WHERE (trm_ID>3257 and trm_ID<3297 and trm_OriginatingDBID=0 and trm_IDInOriginatingDB=0)";
$mysqli->query($query);
if($mysqli->error){print $query.'  '.$mysqli->error; break;}

$query = 'UPDATE '.$db_name.'.defTerms set trm_IDInOriginatingDB = trm_ID, trm_NameInOriginatingDB = trm_Label,'
            .' trm_OriginatingDBID=0'
            ." WHERE (trm_OriginatingDBID='' OR trm_OriginatingDBID=0 OR trm_OriginatingDBID IS NULL "
            ." OR trm_IDInOriginatingDB='' OR trm_IDInOriginatingDB=0 OR trm_IDInOriginatingDB IS NULL)";
$mysqli->query($query);
if($mysqli->error){print $query.'  '.$mysqli->error; break;}
        continue;
*/



        $rec_types = array();
        $det_types = array();
        $terms = array();
        $is_found = false;

        //RECORD TYPES

        $query = 'SELECT rty_ID, rty_Name, rty_NameInOriginatingDB, rty_OriginatingDBID, rty_IDInOriginatingDB '
            ." FROM `$db_name`.defRecTypes "
            .' WHERE (rty_OriginatingDBID="" OR rty_OriginatingDBID IS NULL '
            .'OR rty_IDInOriginatingDB="" OR rty_IDInOriginatingDB=0 OR rty_IDInOriginatingDB IS NULL)';


        $res = $mysqli->query($query);
        if (!$res) {  print htmlspecialchars($query.'  '.$mysqli->error); return; }

        while ($row = $res->fetch_row()) {
            $is_found = true;
            array_push($rec_types, array_map('htmlspecialchars',$row));
        }

        // set ids for registered databases, where originating DB id is 0
        $query = "SELECT rty_ID FROM `$db_name`.defRecTypes WHERE rty_OriginatingDBID = 0";
        $res = mysql__select_list2($mysqli, $query, 'intval');
        if(!empty($res)){

            $query = "UPDATE `$db_name`.defRecTypes SET rty_OriginatingDBID = $ver WHERE rty_OriginatingDBID = 0";
            $mysqli->query($query);
        }

/*   find alternatives
        if($ver==2){
            $dbid = 'in (2,3,1066)';
        }elseif($ver==6){ //biblio
            $dbid = 'in (3,6)';
        }else{
            $dbid = '='.$ver;
        }

        $query = 'SELECT rty_ID, rty_Name, rty_OriginatingDBID, rty_IDInOriginatingDB FROM '
            .$db_name.'.defRecTypes WHERE (rty_OriginatingDBID '.$dbid.' AND rty_OriginatingDBID>0)';

        $res = $mysqli->query($query);
        while ($row = $res->fetch_row()) {
               $row[1] = strtolower($row[1]);
               array_push($all_rty_regs, $row);
        }
*/

        //FIELD TYPES
        if($need_Details){
        $query = 'SELECT dty_ID, dty_Name, dty_NameInOriginatingDB, dty_OriginatingDBID, dty_IDInOriginatingDB '
            ." FROM `$db_name`.defDetailTypes "
            ." WHERE  dty_OriginatingDBID='' OR dty_OriginatingDBID IS NULL " //
            ."OR dty_IDInOriginatingDB='' OR dty_IDInOriginatingDB=0 OR dty_IDInOriginatingDB IS NULL ";



        $res = $mysqli->query($query);
        if (!$res) {  print htmlspecialchars($query.'  '.$mysqli->error); return; }

        while ($row = $res->fetch_row()) {
               $is_found = true;
               array_push($det_types, array_map('htmlspecialchars',$row));
        }

        // set ids for registered databases, where originating DB id is 0
        $query = "SELECT dty_ID FROM `$db_name`.defDetailTypes WHERE dty_OriginatingDBID = 0";
        $res = mysql__select_list2($mysqli, $query, 'intval');
        if(!empty($res)){

            $query = "UPDATE `$db_name`.defDetailTypes SET dty_OriginatingDBID = $ver WHERE dty_OriginatingDBID = 0";
            $mysqli->query($query);
        }
        if(!empty($det_types)){

/*
$query = 'UPDATE '.$db_name.'.defDetailTypes set dty_IDInOriginatingDB = dty_ID, dty_NameInOriginatingDB = dty_Name, dty_OriginatingDBID=2'
            ." WHERE (dty_ID>56 and dty_ID<73 and dty_OriginatingDBID=0 and dty_IDInOriginatingDB=0)";
$mysqli->query($query);

$query = 'UPDATE '.$db_name.'.defDetailTypes set dty_IDInOriginatingDB = dty_ID, dty_NameInOriginatingDB = dty_Name, dty_OriginatingDBID='.$ver
            ." WHERE (dty_OriginatingDBID='' OR dty_OriginatingDBID=0 OR dty_OriginatingDBID IS NULL "
            ." OR dty_IDInOriginatingDB='' OR dty_IDInOriginatingDB=0 OR dty_IDInOriginatingDB IS NULL)";
$mysqli->query($query);
*/
        }


        }

        //TERMS
        if($need_Terms){
            $query = 'SELECT trm_ID, trm_Label, trm_NameInOriginatingDB, trm_OriginatingDBID, trm_IDInOriginatingDB '
            ." FROM `$db_name`.defTerms "
            ." WHERE trm_OriginatingDBID='' OR trm_OriginatingDBID IS NULL " //
            ."OR trm_IDInOriginatingDB='' OR trm_IDInOriginatingDB=0 OR trm_IDInOriginatingDB IS NULL";

            $res = $mysqli->query($query);
            if (!$res) {  print htmlspecialchars($query.'  '.$mysqli->error); return; }

            while ($row = $res->fetch_row()) {
                   $is_found = true;
                   array_push($terms, array_map('htmlspecialchars',$row));
            }

            // set ids for registered databases, where originating DB id is 0
            $query = "SELECT trm_ID FROM `$db_name`.defTerms WHERE trm_OriginatingDBID = 0";
            $res = mysql__select_list2($mysqli, $query, 'intval');
            if(!empty($res)){

                $query = "UPDATE `$db_name`.defTerms SET trm_OriginatingDBID = $ver WHERE trm_OriginatingDBID = 0";
                $mysqli->query($query);
            }
          /*
        if(!empty($terms)){
$query = 'UPDATE '.$db_name.'.defTerms set trm_IDInOriginatingDB = trm_ID, trm_NameInOriginatingDB = trm_Label,'
            .' trm_OriginatingDBID=2'
            ." WHERE (trm_ID>3257 and trm_ID<3297 and trm_OriginatingDBID=0 and trm_IDInOriginatingDB=0)";
$mysqli->query($query);

$query = 'UPDATE '.$db_name.'.defTerms set trm_IDInOriginatingDB = trm_ID, trm_NameInOriginatingDB = trm_Label,'
            .' trm_OriginatingDBID='.$ver
            ." WHERE (trm_OriginatingDBID='' OR trm_OriginatingDBID=0 OR trm_OriginatingDBID IS NULL "
            ." OR trm_IDInOriginatingDB='' OR trm_IDInOriginatingDB=0 OR trm_IDInOriginatingDB IS NULL)";
$mysqli->query($query);

        }
        */
        }

        if($is_found){
            $registered[$db_name] = array('id'=>$ver, 'rty'=>$rec_types, 'dty'=>$det_types, 'trm'=>$terms);
        }

    }//while  databases

    foreach($registered as $db_name=>$data){

            $rec_types = $data['rty'];
            $det_types = $data['dty'];
            $terms = $data['trm'];

            print '<h4 style="margin:0;padding-top:20px">'.$data['id'].' - '.substr($db_name,4).'</h4><table style="font-size:12px">';

            print '<tr><td>Internal code</td><td>Name in this DB</td><td>Name in origin DB</td><td>xxx_OriginDBID</td><td>xxx_IDinOriginDB</td></tr>';

            if(!empty($rec_types)){
                print '<tr><td colspan=5><i>Record types</i></td></tr>';
                foreach($rec_types as $row){
                    print TR_S.implode(TD,$row).TR_E;

                    //find options what may be code for these rectypes
                    foreach($all_rty_regs as $k=>$rty)
                    {
                        if($rty[1]==strtolower($row[0]) || ($row[1] && $rty[1]==strtolower($row[1]))){
                            print '<tr><td colspan="2"></td><td>'.$rty[1].TD.$rty[2].TD.$rty[3].TR_E;
                        }
                    }
                }
            }
            if(!empty($det_types)){
                print '<tr><td colspan=5>&nbsp;</td></tr>';
                print '<tr><td colspan=5><i>Detail types</i></td></tr>';
                foreach($det_types as $row){
                    print TR_S.implode(TD,$row).TR_E;
                }
            }
            if(!empty($terms)){
                print '<tr><td colspan=5><i>Terms</i></td></tr>';
                foreach($terms as $row){
                    print TR_S.implode(TD,$row).TR_E;
                }
            }
            print '</table>';

    }

    print '[end report]</div>';

/*
    print DIV_S.TABLE_S;
    foreach($all_rty_regs as $k=>$rty)
    {
        print TR_S.$rty[1].TD.$rty[2].TD.$rty[3].TR_E;
    }
    print TABLE_E.DIV_E;
*/
/*
    asort($registered);

    foreach ($registered as $db_name=>$regid){
        print $regid.'  '.$db_name.'<br>';
    }
*/
?>
