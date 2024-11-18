<?php

/**
* purgeFullTextIndexes.php:
*
* Remove fulltext indexes and optimize Records and recDetail tables for databases
* inactive for 3 months
*
* Runs from shell only
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

// Default values for arguments
$is_shell = false;
$arg_no_action = true;
$eol = "\n";
$tabs = "\t\t";
$tabs0 = '';

if (@$argv) {

    define('PURGE','-purge');

// example:
//  sudo php -f /var/www/html/heurist/admin/utilities/purgeFullTextIndexes.php -- -purge
//  sudo php -f purgeFullTextIndexes.php -- -purge  -  action, otherwise only report

    $is_shell =  true;

    // handle command-line queries
    $ARGV = array();
    for ($i = 0;$i < count($argv);++$i) {
        if ($argv[$i][0] === '-') {
            if (@$argv[$i + 1] && $argv[$i + 1][0] != '-') {
                $ARGV[$argv[$i]] = $argv[$i + 1];
                ++$i;
            } else {
                if(strpos($argv[$i],PURGE)===0){
                    $ARGV[PURGE] = true;
                }else{
                    $ARGV[$argv[$i]] = true;
                }


            }
        } else {
            array_push($ARGV, $argv[$i]);
        }
    }

    if (@$ARGV[PURGE]) {$arg_no_action = false;}

}else{

    //report only
    $arg_no_action = true;
    $eol = "</div><br>";
    $tabs0 = '<div style="min-width:300px;display:inline-block;">';
    $tabs = DIV_E.$tabs0;

}


use hserv\utilities\USanitize;

require_once dirname(__FILE__).'/../../autoload.php';

require_once dirname(__FILE__).'/../../hserv/records/search/recordFile.php';

//retrieve list of databases
$system = new hserv\System();

if(!$is_shell){
    $sysadmin_pwd = USanitize::getAdminPwd();

    if($system->verifyActionPassword( $sysadmin_pwd, $passwordForServerFunctions) ){
        $response = $system->getError();
        print $response['message'];
        exit;
    }
}

if( !$system->init(null, false, false) ){
    exit("Cannot establish connection to sql server\n");
}

$mysqli = $system->getMysqli();
$databases = mysql__getdatabases4($mysqli, false);

$exclusion_list = exclusionList();

if(!$arg_no_action){

    $action = 'purgeFullTextIndexes';
    if(false && !isActionInProgress($action, 1)){
        exit("It appears that 'purge full text indexes' operation has been started already. Please try this function later\n");
    }
}


set_time_limit(0);//no limit
ini_set('memory_limit','1024M');

$datetime1 = date_create('now');
$cnt_processed = 0;

foreach ($databases as $idx=>$db_name){

    if(in_array($db_name,$exclusion_list)){
        continue;
    }
    $res = mysql__usedatabase($mysqli, $db_name);
    if($res!==true){
        echo @$res[1]."\n";
        continue;
    }

    $db_name = htmlspecialchars($db_name);

/*
* Delete/archive any database not updated for more than:
*           3 months with 10 records or less
*           6 months with 50 records or less
*           one year with 200 records or less
* Send sysadmin a list of databases
*            for more than a year with more than 200 records
*/
    //find number of records and date of last update
    $query = 'SELECT count(rec_ID) as cnt, max(rec_Modified) as mdate FROM Records';
    $vals = mysql__select_row_assoc($mysqli, $query);
    if($vals==null){
        echo $tabs0.$db_name.' cannot execute query for Records table'.$eol;
        continue;
    }
    if(@$vals['cnt']==0){
        //find date of last modification from definitions
        $vals['mdate'] = mysql__select_value($mysqli, 'select max(rst_Modified) from defRecStructure');
    }

    $datetime2 = date_create($vals['mdate']);

    if(!$datetime2){
        echo $tabs0.$db_name.' cannot detect modification date'.$eol;
        continue;
    }

    //"processing ".
    //echo $db_name.' ';//.'  in '.$folder
    $report = '';

    $interval = date_diff($datetime1, $datetime2);
    $diff = $interval->format('%y')*12 + $interval->format('%m');

    if($diff>=3){ //older than 3 months
        //drop full text indexes and optiomize table
        $report = $diff.' months, n='.$vals['cnt'];
        if($arg_no_action){
            $report .= ' ';
        }else{

            purgeFtsIndex('Records','rec_Title_FullText',$report);
            purgeFtsIndex('recDetails','dtl_Value_FullText',$report);

        }
    }

    if($report!=''){
        echo $tabs0.htmlspecialchars($db_name).$tabs.htmlspecialchars($report).$eol;
    }


    //echo "   ".$db_name." OK \n";//.'  in '.$folder
}//for


if(!$arg_no_action){
    echo $tabs0.'Purged indexes for '.$cnt_processed.' databases'.$eol;
}

echo $tabs0.'finished'.$eol;

function purgeFtsIndex($table, $index, &$report ){

            $res = false;

            $query = "SHOW INDEX FROM $table WHERE Key_name='$index'";
            $has_index = mysql__select_value($mysqli, $query, null);
            if($has_index!=null){
                $query = "ALTER TABLE $table DROP INDEX `$index`";
                $res = mysql__exec_param_query($mysqli, $query, null);
                if($res===true){
                    $query = "OPTIMIZE TABLE $table"; //it cleanups FTS*.ibd files
                    $res = mysql__exec_param_query($mysqli, $query, null);
                }
            }else{
                $res = 'skip';
            }
            if($res===true){
                $report .= "$table FTS purged ";
            }elseif($res=='skip'){
                $report .= " $table index does not exist ";
            }else{
                $report .= ('ERROR: '.$res);
            }

}

function exclusionList(){

    $res = array();
    $fname = realpath(dirname(__FILE__)."/../../../../databases_not_to_purge.txt");
    if($fname!==false && file_exists($fname)){

        $handle = @fopen($fname, "r");
        while (!feof($handle)) {
            $line = trim(fgets($handle, 100));
            if($line=='' || substr($line,0,1)=='#') {continue;}
            $res[] = $line;
        }
        fclose($handle);
    }
    return $res;
}

?>