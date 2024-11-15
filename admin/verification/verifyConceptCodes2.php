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
    * Verifies missed IDinOriginatingDB
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
            <p>Record and base field types with missing xxx_OriginatingDBID or xxx_IDinOriginatingDB fields</p>

<?php


$mysqli = $system->get_mysqli();

    //1. find all database
    $query = 'show databases';

    $res = $mysqli->query($query);
    if (!$res) {  print $query.'  '.$mysqli->error;  return; }
    $databases = array();
    while ($row = $res->fetch_row()) {
        if( strpos($row[0], 'hdb_DEF19')===0 || strpos($row[0], 'hdb_def19')===0) {continue;}

        if( strpos($row[0], HEURIST_DB_PREFIX)===0 ){
                $databases[] = $row[0];
        }
    }

    $need_Details = true;
    $need_Terms = false;

    foreach ($databases as $idx=>$db_name){

        $db_name = preg_replace(REGEX_ALPHANUM, "", $db_name);

        $query = 'SELECT sys_dbSubVersion from `'.$db_name.'`.sysIdentification';
        $ver = mysql__select_value($mysqli, $query);

        $rec_types = array();
        $det_types = array();
        $terms = array();
        $is_found = false;

        //RECORD TYPES

        $query = 'SELECT rty_ID, rty_Name, rty_NameInOriginatingDB, rty_OriginatingDBID, rty_IDInOriginatingDB FROM `'
            .$db_name.'`.defRecTypes WHERE  rty_OriginatingDBID>0 AND '
            ."(rty_OriginatingDBID='' OR rty_OriginatingDBID=0 OR rty_OriginatingDBID IS NULL)";

        $res = $mysqli->query($query);
        if (!$res) {  print htmlspecialchars($query.'  '.$mysqli->error); return; }

        while ($row = $res->fetch_row()) {
               $is_found = true;
               array_push($rec_types, array_map('htmlspecialchars',$row));
        }

        if($need_Details){

        //FIELD TYPES
        $query = 'SELECT dty_ID, dty_Name, dty_NameInOriginatingDB, dty_OriginatingDBID, dty_IDInOriginatingDB FROM `'
            .$db_name.'`.defDetailTypes WHERE  dty_OriginatingDBID>0 AND '
            ."(dty_IDInOriginatingDB='' OR dty_IDInOriginatingDB=0 OR dty_IDInOriginatingDB IS NULL)";


        $res = $mysqli->query($query);
        if (!$res) {  print htmlspecialchars($query.'  '.$mysqli->error); return; }

        while ($row = $res->fetch_row()) {
               $is_found = true;
               array_push($det_types, array_map('htmlspecialchars',$row));
        }

        }
        if($need_Terms){

        //TERMS
        $query = 'SELECT trm_ID, trm_Label, trm_NameInOriginatingDB, trm_OriginatingDBID, trm_IDInOriginatingDB FROM `'
            .$db_name.'`.defTerms WHERE  trm_OriginatingDBID>0 AND (NOT (trm_IDInOriginatingDB>0)) ';

        $res = $mysqli->query($query);
        if (!$res) {  print htmlspecialchars($query.'  '.$mysqli->error); return; }

        while ($row = $res->fetch_row()) {
               $is_found = true;
               array_push($terms, array_map('htmlspecialchars',$row));
        }

        }

        if($is_found){
            print '<h4 style="margin:0;padding-top:20px">'.htmlspecialchars(substr($db_name,4)).'</h4><table style="font-size:12px">';

            print '<tr><td>Internal code</td><td>Name in this DB</td><td>Name in origin DB</td><td>xxx_OriginDBID</td><td>xxx_IDinOriginDB</td></tr>';

            if(!empty($rec_types)){
                print '<tr><td colspan=5><i>Record types</i></td></tr>';
                foreach($rec_types as $row){
                    //snyk does not see htmlspecialchars above
                    $list = str_replace(chr(29),TD,htmlspecialchars(implode(chr(29),$row)));
                    print TR_S.$list.TR_E;
                }
            }
            if(!empty($det_types)){
                print '<tr><td colspan=5>&nbsp;</td></tr>';
                print '<tr><td colspan=5><i>Detail types</i></td></tr>';
                foreach($det_types as $row){
                    //snyk does not see htmlspecialchars above
                    $list = str_replace(chr(29),TD,htmlspecialchars(implode(chr(29),$row)));
                    print TR_S.$list.TR_E;
                }
            }
            if(!empty($terms)){
                print '<tr><td colspan=5><i>Terms</i></td></tr>';
                foreach($terms as $row){
                    //snyk does not see htmlspecialchars above
                    $list = str_replace(chr(29),TD,htmlspecialchars(implode(chr(29),$row)));
                    print TR_S.$list.TR_E;
                }
            }
            print '</table>';
        }

    }//while  databases
    print '[end report]</div>';
?>
