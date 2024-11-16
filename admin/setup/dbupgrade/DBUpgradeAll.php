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
    * Upgrade all database to version 1.3
    *
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @copyright   (C) 2005-2023 University of Sydney
    * @link        https://HeuristNetwork.org
    * @version     3.1
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  Administration
    */

ini_set('max_execution_time', '0');

define('OWNER_REQUIRED',1);

define('PDIR','../../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../../hclient/framecontent/initPageMin.php';
require_once dirname(__FILE__).'/../../../admin/setup/dbupgrade/DBUpgrade.php';


print '<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px">';


$mysqli = $system->getMysqli();

    //1. find all database
    $query = 'show databases';

    $res = $mysqli->query($query);
    if (!$res) {  print $query.'  '.$mysqli->error;  return; }
    $databases = array();
    while ($row = $res->fetch_row()) {
        if( strpos($row[0], HEURIST_DB_PREFIX)===0 ){
                $databases[] = $row[0];
        }
    }

    $db_undef = array();//it seems this is not heurist db

    $db = array();
    $cnt = 0;

    foreach ($databases as $idx=>$db_name){

        $query = 'SELECT sys_dbSubVersion from '.$db_name.'.sysIdentification';
        $ver = mysql__select_value($mysqli, $query);


        if( (!isPositiveInt($ver)) || $ver<3){

            if(!hasTable($mysqli, 'sysIdentification',$db_name)){
                $db_undef[] = $db_name;
                continue;
            }

            if(!@$db[$ver]){
                $db[$ver] = array($db_name);
            }else{
                array_push($db[$ver], $db_name);
            }

            $res = doUpgradeDatabase($system, $db_name, 1, 3, false);
            if(!$res){

                print errorDiv('Error: Unable upgrade '.htmlspecialchars($db_name));

                $error = $system->getError();
                if($error){
                    print errorDiv($error['message'].BR.@$error['sysmsg']);
                }
                break;
            }

            $cnt++;

        }else{
            //check that v1.3 has


        }


    }//while  databases


    if(!isEmptyArray($db_undef)){
        print '<p>It seems these are not Heurist databases</p>';
        foreach ($db_undef as $db_name){
            print htmlspecialchars($db_name).'<br>';
        }
    }
    if(!isEmptyArray($db)){
        foreach ($db as $ver => $dbs){
           print '<p>List of databases with v 1.'.$ver.'   Cnt: '.count($dbs).'</p>';
           foreach ($dbs as $db_name){
                print htmlspecialchars($db_name).'<br>';
           }
        }
    }

    print '[end report]</div>';
?>
