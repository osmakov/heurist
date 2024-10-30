<?php

/**
* Script is run by daily cronjob
* It performs the following actions
*
* parameters:
* 1. reminder   Send record remainders sepcified in usrReminders
* 2. report     Updates reports by schedule specified in usrReportSchedule
* 3. url        Checks that rec_URL and URL like values are valid, database is skipped if sys_URLCheckFlag is set to false
*
* Databases in HEURIST/databases_exclude_cronjobs.txt are ignored
*
* Runs from shell only
*
* in heuristConfigIni.php define $serverName
* if (!@$serverName && php_sapi_name() == 'cli') {$serverName = 'heuristref.net';}
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
$arg_no_action = true;
$eol = "\n";
$tabs = "\t\t";
$tabs0 = '';

$do_reports = false;
$do_reminders = false;
$do_url_check = false;

$func_return = 1; // for checkRecURL.php

if (@$argv) {

// example:
//  sudo php -f /var/www/html/heurist/admin/setup/dboperations/dailyCronJobs.php -- reminder report
// sudo php -f /var/www/html/h6-alpha/admin/setup/dboperations/dailyCronJobs.php -- url

    $ARGV = array();
    for ($i = 0;$i < count($argv);++$i) {
        if ($argv[$i][0] === '-') {  //pair: -param value
            if (@$argv[$i + 1] && $argv[$i + 1][0] != '-') {
                $ARGV[substr($argv[$i],1)] = $argv[$i + 1];
                ++$i;
            }
        } else {
            $ARGV[$argv[$i]] = true;
        }
    }

//sudo php -f /var/www/html/h6-alpha/admin/setup/dboperations/dailyCronJobs.php -- -database camil_inthemarginofstone
//camillaC_Pakistan_Villages
    $arg_database = null;
    if(@$ARGV['database']){ //limit scrit to the only database
        $arg_database = $ARGV['database'];
    }

    if(@$ARGV['reminder']){ //send reinders
        $do_reminders = true;
    }
    if(@$ARGV['report']){   //update scheduled reports
        $do_reports = true;
    }
    if(@$ARGV['url']){  //validate urls
        $do_url_check = true;
    }
    if(!$do_reminders && !$do_reports && !$do_url_check){
        $do_reminders = true;
        $do_reports = true;
        $do_url_check = true;
    }


}else{
    exit('This function must be run from the shell');
    //$arg_database = 'libraries_readers_culture_18c_atlantic';
    //$do_reports = true;
}

use hserv\entity\DbUsrReminders;
use hserv\controller\ReportController;

require_once dirname(__FILE__).'/../../../autoload.php';

require_once dirname(__FILE__).'/../../../hserv/records/search/recordFile.php';
require_once dirname(__FILE__).'/../../../admin/verification/checkRecURL.php';

//retrieve list of databases
$system = new hserv\System();
if( !$system->init(null, false, false) ){
    exit("Cannot establish connection to sql server\n");
}


if(!defined('HEURIST_MAIL_DOMAIN')) {define('HEURIST_MAIL_DOMAIN', 'cchum-kvm-heurist.in2p3.fr');}
if(!defined('HEURIST_SERVER_NAME') && isset($serverName)) {define('HEURIST_SERVER_NAME', $serverName);}//'heurist.huma-num.fr'
if(!defined('HEURIST_SERVER_NAME')) {define('HEURIST_SERVER_NAME', 'heurist.huma-num.fr');}

print 'Mail: '.HEURIST_MAIL_DOMAIN.'   Domain: '.HEURIST_SERVER_NAME."\n";

$mysqli = $system->get_mysqli();

if($arg_database){
    echo "database: ".$arg_database."\n";
    $databases = array($arg_database);
}else{
    $databases = mysql__getdatabases4($mysqli, false);//list of all databases without hdb_ prefix
}

$upload_root = $system->getFileStoreRootFolder();

echo "root : ".$upload_root."\n";

$exclusion_list = exclusion_list();//read databases_not_to_crons

set_time_limit(0);//no limit

$datetime1 = date_create('now');
$cnt_archived = 0;
$report_list = array();//reports errors,create,updated,intacted  by database
$email_list = array();//reminders
$url_list = array();
$reminders = null;

if($do_reminders){
    $reminders = new DbUsrReminders($system, $params);
}

print 'HEURIST_SERVER_NAME='.HEURIST_SERVER_NAME."\n";
print 'HEURIST_BASE_URL='.HEURIST_BASE_URL."\n";
print 'HEURIST_MAIL_TO_INFO='.HEURIST_MAIL_TO_INFO."\n";


// HEURIST_SERVER_NAME
// HEURIST_MAIL_TO_INFO

// HEURIST_SMARTY_TEMPLATES_DIR  $system->getSysDir('smarty-templates')
// HEURIST_SCRATCHSPACE_DIR      $system->getSysDir('scratch')
// HEURIST_DBNAME                $system->dbname()

// For sending an email to the sysadmin about reports that take longer than 10 seconds to generate
$long_reports = array();
$long_reports_count = 0;

$mysql_gone_away_error = false;
$last_processed_database = null;

$reports = new ReportController($system, []);

foreach ($databases as $idx=>$db_name){

    $report='';

    $res = mysql__usedatabase($mysqli, $db_name);
    if($res!==true){
        $mysql_gone_away_error = $mysqli && $mysqli->errno==2006;
        if($mysql_gone_away_error){
            $last_processed_database = $db_name;
            break;
        }else{
            echo @$res[1]."\n";
            continue;
        }
    }

    $system->set_mysqli($mysqli);
    $system->set_dbname_full($db_name);

    if($do_reports){

        $report = $reports->updateTemplate(); //update all templates for database

        if(@$report[5]['fatal']){
            echo 'Fatal error for database '.htmlspecialchars($db_name).'  '.$report[5]['fatal'].$eol;
        }else{
            if(!empty($report[4])){
                $long_reports[$db_name] = $report[4];
                $long_reports_count = $long_reports_count + count($report[4]);
            }

            $report_list[$db_name] = $report;

            echo $eol.htmlspecialchars($db_name).$tabs;
            echo ' reports errors:'.$report[0].' created:'.$report[1].' updated:'.$report[2].' unchanged:'.$report[3].$eol;

            if(!empty($report[5])){
                echo 'Reports with errors:'.$eol;
                foreach($report[5] as $id=>$err){
                    echo $id.'   '.$err.$eol;
                }
            }
            if(!empty($report[4])){
                echo 'Long execution reports:'.$eol;
                foreach($report[4] as $id=>$time){
                    echo $id.'   '.$time.$eol;
                }
            }
        }
    }

    if(in_array($db_name,$exclusion_list)){
        continue;
    }

    if($do_reminders){
        $reminders->setmysql($mysqli);
        $report = $reminders->batch_action();
        if(!empty($report)){
            echo $tabs0.htmlspecialchars($db_name);
            echo $tabs.' reminders: ';
            foreach($report as $freq=>$cnt){
                echo $freq.':'.$cnt.'  ';
                if(!@$email_list[$freq]) {$email_list[$freq] = 0;}
                $email_list[$freq] = $email_list[$freq] + $cnt;
            }
            echo $eol;
        }
    }

    $do_url_check = false; //DISABLED TEMP 2024-08-27
    if($do_url_check){

        $perform_url_check = mysql__select_value($mysqli, 'SELECT sys_URLCheckFlag FROM sysIdentification');
        if(!$perform_url_check || $perform_url_check == 0){ // check for flag setting
            continue;
        }

        echo $eol.$db_name;

        $checkerURL = new URLChecker($mysqli, HEURIST_SERVER_URL, false);
        $url_results = $checkerURL->checkURLs();

        $invalid_rec_urls = $url_results[0];
        $invalid_fb_urls = $url_results[1];
        $invalid_file_urls = $url_results[2];
        $fatal_curl_error = $url_results[3];

        if(!$fatal_curl_error){

            $url_list[$db_name] = array([], []);

            if(!empty($invalid_rec_urls)){
                echo $eol.'Records with invalid urls: ';
                foreach ($invalid_rec_urls as $rec_id => $url) {
                    echo $eol.$rec_id.' : '.$url;
                    $url_list[$db_name][0][] = $rec_id.' : '.$url;
                }
            }

            if(!empty($invalid_fb_urls)){

                    echo $eol.'text fields containing invalid urls: ';

                    foreach ($invalid_fb_urls as $rec_id => $flds) {
                        echo $eol.$rec_id.': ';
                        foreach($flds as $dty_id => $urls){
                            echo $eol.$tabs.$dty_id.': '.implode(',', $urls);
                        }

                        $url_list[$db_name][1][] = $rec_id . ' : ' . implode(',', array_keys($flds));
                    }

            }
            if(!empty($invalid_file_urls)){

                    echo $eol.'file fields contain invalid urls: ';
                    foreach ($invalid_file_urls as $rec_id => $flds) {
                        echo $eol.$rec_id.': ';
                        foreach($flds as $dty_id => $urls){
                            echo $eol.$tabs.$dty_id.': '.implode(',', $urls);
                        }

                        $url_list[$db_name][1][] = $rec_id . ' : ' . implode(',', array_keys($flds));
                    }
            }

        }else{
            echo $eol.'CURL error: '.$fatal_curl_error;
            sendEmail(HEURIST_MAIL_TO_ADMIN, HEURIST_SERVER_NAME.' Check url fails.',
                $fatal_curl_error.' It stopped on '.$db_name);

        }

    }
}//foreach database


echo $eol.$tabs0.'finished'.$eol;

if($mysql_gone_away_error){
    $text = ' dailyCronJobs failed. MySQL server has gone away';
echo $text.' Db '.htmlentities($last_processed_database).$eol;
    sendEmail(HEURIST_MAIL_TO_ADMIN, HEURIST_SERVER_NAME.$text,
                $text.' It stopped on '.$last_processed_database);
}

$errors = 0;

if(!empty($email_list) || !empty($report_list) || !empty($url_list)){

    $created = 0;
    $updated = 0;
    $intacted = 0;
    foreach($report_list as $dbname=>$rep){
        $errors = $errors + $rep[0];
        $created = $created + $rep[1];
        $updated = $updated + $rep[2];
        $intacted = $intacted + $rep[3];
    }
    $text = '';
    foreach($email_list as $freq=>$cnt){
          $text = $text. $freq.':'.$cnt.'  ';
    }

    $text = "Reminder emails sent, reports updated and URLs verified\n\n"
    ."Number of reminder emails sent:\n"
    .$text
    ."\n\nNumber of reports "
    ."\n created: ".$created
    ."\n updated: ".$updated
    ."\n unchanged: ".$intacted
    ."\n errors: ".$errors."\n";

    $text = $text."\n\nInvalid urls: ";
    foreach($url_list as $dbname=>$reps){
        $rec_URL = 'None';
        $fld_URL = 'None';
        if(!empty($reps[0])){
            $rec_URL = "\n  ".implode("\n  ", $reps[0]);
        }
        if(!empty($reps[1])){
            $fld_URL = "\n  ".implode("\n  ", $reps[1]);
        }
        $text = $text . "\n" . $dbname . "\n rec_URL => " . $rec_URL . "\n Fields => " . $fld_URL;
    }
    if(empty($url_list)){
        $text = $text . "None";
    }
    $text = $text . "\n";

    echo $text;

    $rep_count = $created + $updated + $intacted;
    sendEmail(HEURIST_MAIL_TO_ADMIN, HEURIST_SERVER_NAME." Emails sent: ".$cnt." | Reports:".$rep_count." | Errors: ".$errors." | Bad URLs: ".count($url_list),
                $text);

}

// Send list of long report generation to system admin
if($long_reports_count > 0){

    $email_body = "The following report" . ($long_reports_count > 1 ? "s have" : " has") . " taken longer than 10 seconds to regenerate:\n";

    // $report_dtls
    foreach($long_reports as $dbname => $report_dtls){
        foreach($report_dtls as $id=>$time){
            $email_body .= "DB: $dbname, Report name: " . $id . " takes " . $time . " seconds to regenerate\n";
        }
    }

    $email_body .= "\nWe recommend either increasing the time between regenerations for these reports, "
                . "or setting the regeneration time to zero and requesting the owner to manually regenerate the report as needed.";

    sendEmail(HEURIST_MAIL_TO_ADMIN, "Slow report generation on " . HEURIST_SERVER_NAME, $email_body);
}

if($errors>0){
    $email_body = "The following report" . ($errors > 1 ? "s have" : " has") . " errors and can not be executed/regenerated:\n";

    foreach($report_list as $dbname=>$report){
            if(!empty($report[5])){
                echo 'Reports with errors:'.$eol;
                foreach($report[5] as $id=>$err){
                    $email_body .= "DB: $dbname, Report name: " . $id . '   '.$err."\n";
                }
            }
    }
    sendEmail(HEURIST_MAIL_TO_ADMIN, "Reports with errors " . HEURIST_SERVER_NAME, $email_body);
}




function exclusion_list(){

    $res = array();
    $fname = realpath(dirname(__FILE__)."/../../../../databases_exclude_cronjobs.txt");
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