<?php

/**
* notifyDatabaseArchive.php:
*   Send emails about creating DB archives to DB owners
*   Owners will recieve the email if records in their DB has been modified within the last month
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

// example:
//  sudo php -f /var/www/html/heurist/admin/utilities/notifyDatabaseArchive.php -- -db=database_1,database_2
//  If dbs are not specified, all dbs are processed

/*
 This routine:
 Checks each database's last modification (in records or record structure)
 If the last modification was within the last month,
    send a notification to DB owner about making an archive of their database
*/

// Default values for arguments
$arg_database = null; // databases
$eol = "\n";
$tabs = "\t\t";
$tabs0 = '';

if (@$argv) {

    // handle command-line queries
    $ARGV = array();
    for ($i = 0;$i < count($argv);++$i) {
        if ($argv[$i][0] === '-') {
            if (@$argv[$i + 1] && $argv[$i + 1][0] != '-') {
                $ARGV[$argv[$i]] = $argv[$i + 1];
                ++$i;
            } elseif(strpos($argv[$i],'-db=')===0){
                $ARGV['-db'] = substr($argv[$i],4);
            }
        } else {
            array_push($ARGV, $argv[$i]);
        }
    }

    if (@$ARGV['-db']) {$arg_database = explode(',', $ARGV['-db']);}

}else{
    /*web browser
    $eol = "</div><br>";
    $tabs0 = '<div style="min-width:300px;display:inline-block;">';
    $tabs = DIV_E.$tabs0;

    if(array_key_exists('db', $_REQUEST)){
        $arg_database = explode(',',$_REQUEST['db']);
    }*/

    exit('This function is for command line execution');
}

define('HEURIST_DIR', dirname(__FILE__).'/../../');

use hserv\utilities\USystem;

require_once dirname(__FILE__).'/../../autoload.php';

//retrieve list of databases
$system = new hserv\System();
if( !$system->init(null, false, false) ){
    exit("Cannot establish connection to sql server\n");
}

// Setup server name
if(!defined('HEURIST_SERVER_NAME') && isset($serverName)) {define('HEURIST_SERVER_NAME', $serverName);}

if(!defined('HEURIST_SERVER_NAME') || empty(HEURIST_SERVER_NAME)){
    exit('The script was unable to determine the server\'s name, please define it within heuristConfigIni.php then re-run this script.');
}

// Setup Base URL
$base_url = '';
if(defined('HEURIST_BASE_URL_PRO')){
    $base_url = HEURIST_BASE_URL_PRO;
}else{
    $base_url = 'https://' . HEURIST_SERVER_NAME . HEURIST_DEF_DIR;
}

if(empty($base_url) || strcmp($base_url, 'http://') == 0 || strcmp($base_url, 'https://') == 0){
    exit('The script was unable to determine the server\'s name, please define it within heuristConfigIni.php then re-run this script.');
}

if(substr($base_url, -1, 1) != '/'){
    $base_url .= '/';
}
if(strpos($base_url, HEURIST_DEF_DIR) === false){
    $base_url = rtrim($base_url, '/') . HEURIST_DEF_DIR;
}

$mysqli = $system->getMysqli();
$databases = mysql__getdatabases4($mysqli, false);

$path_to_function = $base_url . "export/dbbackup/exportMyDataPopup.php?db=";

// Email title
$email_title = "Backup reminder : Heurist database ";

// Email body
$email_body = "This is a reminder that you can download an archive package of your database {db_name} from the Heurist server by going to this address: <a href='$path_to_function{db_name}'>$path_to_function{db_name}</a><br><br>"
. "Although we have comprehensive backup systems in place, we strongly recommend you do this once a month unless no modifications have been made<br>"
. "a. to retain your own copy for peace of mind;<br>"
. "b. because no-one can unequivocally guarantee the existence of a web service forever, even though we operate on a long-term perspective.<br><br>"
. "The package includes complete data in textual format (XML and SQL) which is sufficient to reconstruct the database on another Heurist server or (with programming) an alternative DBMS.<br>"
. "It also includes all images and other files uploaded to the database (unless you uncheck this option) and any websites created with the built-in CMS, and<br>"
. "comprehensive documentation sufficient to interpret the content into the indefinite future (depending on the quality of your own entity, field and vocabulary descriptions).<br><br>"
. "Please note: some very large databases could create files which are too large to download.<br>"
. "In that case please ".CONTACT_SYSADMIN." so that we are aware of the problem and can arrange an alternative procedure.";

set_time_limit(0);//no limit
ini_set('memory_limit','1024M');

$month_ago = strtotime('-1 month');
$emails_sent = 0;
$list_is_array = is_array($arg_database);

foreach ($databases as $idx=>$db_name){

    if($list_is_array && !in_array($db_name, $arg_database)){
        continue;
    }

    echo $eol.htmlspecialchars($db_name).' Checking'.$eol;

    $res = mysql__usedatabase($mysqli, $db_name);
    if(!$res){
        echo $tabs0.@$res[1].$eol;
        continue;
    }

    // Get last record modification
    $last_rec_mod = mysql__select_value($mysqli, 'SELECT MAX(rec_Modified) FROM Records WHERE rec_FlagTemporary = 0');
    if(!$last_rec_mod && !empty($mysqli->error)){
        echo $tabs."Error retrieving last record modification; MySQL Error: " . $mysqli->error;
        continue;
    }
    $last_rec_mod = $last_rec_mod ? strtotime($last_rec_mod) : null;

    // Check if last modification was more than a month ago
    if(!$last_rec_mod || $last_rec_mod < $month_ago){
        $msg = $last_rec_mod ? "Database was last modified on " . date("Y-m-d", $last_mod) : "Database has no records";
        echo $tabs.$msg;
        continue;
    }

    // Last modified less than a month ago - send reminder

    // Get owner's email
    $owner_mail = mysql__select_value($mysqli, 'SELECT ugr_eMail FROM sysUGrps WHERE ugr_ID = 2');
    if(!$owner_mail){
        echo $tabs."Unable retrieve the Database owner's email; Error: " . ($mysqli->error ? $mysqli->error : 'Owner not found');
        continue;
    }

    $cur_path = $path_to_function . $db_name;

    $success = sendEmail($owner_mail, $email_title . $db_name, str_replace('{db_name}', $db_name, $email_body), true);

    if($success){
        echo $tabs0.'Email sent.'.$eol;
    }else{
        echo $tabs0.'Unable to send email; Error: ' . $system->getErrorMsg() . $eol;
    }
}//for

?>