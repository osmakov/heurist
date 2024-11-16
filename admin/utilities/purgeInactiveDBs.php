<?php

/**
* purgeInactiveDBs.php: Reports (default) or purge/archive inactive databases
*
* Report/purge occurs if no data added/edited for more than:
*
*           3 months with 10 records or less
*           6 months with 50 records or less
*           one year with 200 records or less
*
* Sends sysadmin a list of databases
*            inactive for more than a year with more than 200 records

* @TODO: Should send the message out as a warning message one month before and 1 week before
*        stressing in particular the need to tell us if you want your database marked as non-purging
*
* @TODO: Should also check date of last modification of record types and record type structures
*        to detect databases which are being configured but have very little data
*
* Dump and bz2 import tables that are
*           more than 2 months old
*           older than 1 month if more than 10 tables
*           reduce to 20 most recent tables if more than 20 left
*
*           HEURIST_FILESTORE/_PURGES_IMPORTS/dbname_[name of original file]_yyyy-mm-dd.bz2
*
* Dump and bzip the sysArchive table if it exceeds 50,000 records (50K records is ~10MB)
*   with a name structured as below.
*          …HEURIST_FILESTORE/_PURGES_SYSARCHIVE/dbname_yyyy-mm-dd.bz2
* @TODO: should retain last week of archive records when table is purged
*
* Databases in HEURIST/databases_not_to_purge.txt are ignored
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
$is_shell =  false;
global $arg_no_action = true;
$need_email = true;
$eol = "\n";
$tabs = "\t\t";
$tabs0 = '';

define('ALLOW_ARCHIVE_SYSARCHIVE',true);
define('ALLOW_PURGE_IMPORTTABLES',true);

if (@$argv) {

// example:
//  sudo php -f /var/www/html/heurist/admin/utilities/purgeInactiveDBs.php -- -purge
//  sudo php -f purgeInactiveDBs.php -- -purge  -  action, otherwise only report

// TODO: It would be good if this had a parameter option to also delete the database
//       for use when transferring to a new server

// TODO: WARNING: it does not report an error if there is no filestore folder for the database


/*
 This routine:
 deletes database and keeps archives in DELETED_DATABASES

         Deletes/archives any database not updated for more than:
                   3 months with 10 records or less
                   6 months with 50 records or less
                   one year with 200 records or less
         Sends sysadmin a list of databases
                    for more than a year with more than 200 records


 dumps import tables and bz2 them in _PURGES_IMPORTS
           more than 2 months old
           older than 1 month if more than 10 tables
           reduce to 20 most recent tables if more than 20 left

 it purges sysArchive tables and keeps bz2 in _PURGES_SYSARCHIVE
           with more than 50000 entries

 services files:
     databases_not_to_purge.txt - file in heurist root with list of database to be excluded from this operation
     _operation_locks.info - lock file in HEURIST_FILESTORE_ROOT

*/

    $is_shell = true;

    // handle command-line queries
    $ARGV = array();
    for ($i = 0;$i < count($argv);++$i) {
        if ($argv[$i][0] === '-') {
            if (@$argv[$i + 1] && $argv[$i + 1][0] != '-') {
                $ARGV[$argv[$i]] = $argv[$i + 1];
                ++$i;
            } else {
                if(strpos($argv[$i],'-purge')===0){
                    $ARGV['-purge'] = true;
                }else{
                    $ARGV[$argv[$i]] = true;
                }


            }
        } else {
            array_push($ARGV, $argv[$i]);
        }
    }

    if (@$ARGV['-purge']) {$arg_no_action = false;}

}else{
    //report only
    $arg_no_action = true;
    $need_email = false;

    $eol = "</div><br>";
    $tabs0 = '<div style="min-width:300px;display:inline-block;">';
    $tabs = DIV_E.$tabs0;

}

use hserv\utilities\DbUtils;
use hserv\utilities\UArchive;
use hserv\utilities\USanitize;

require_once dirname(__FILE__).'/../../autoload.php';

require_once dirname(__FILE__).'/../../hserv/records/search/recordFile.php';

//retrieve list of databases
$system = new hserv\System();

if(!$is_shell){
    $sysadmin_pwd = USanitize::getAdminPwd();
    if($system->verifyActionPassword( $sysadmin_pwd, $passwordForServerFunctions) ){
        include_once dirname(__FILE__).'/../../hclient/framecontent/infoPage.php';


        exit;
    }
}

if( !$system->init(null, false, false) ){
    exit("Cannot establish connection to sql server\n");
}



if(!defined('HEURIST_MAIL_DOMAIN')) {define('HEURIST_MAIL_DOMAIN', 'cchum-kvm-heurist.in2p3.fr');}
if(!defined('HEURIST_SERVER_NAME') && isset($serverName)) {define('HEURIST_SERVER_NAME', $serverName);}//'heurist.huma-num.fr'
if(!defined('HEURIST_SERVER_NAME')) {define('HEURIST_SERVER_NAME', 'heurist.huma-num.fr');}

print 'Mail: '.HEURIST_MAIL_DOMAIN.'   Domain: '.HEURIST_SERVER_NAME."\n";

$mysqli = $system->getMysqli();
$databases = mysql__getdatabases4($mysqli, false);

$upload_root = $system->getFileStoreRootFolder();
$backup_root = $upload_root.'DELETED_DATABASES/';
$backup_imports = $upload_root.'_PURGES_IMPORTS/';
$backup_sysarch = $upload_root.'_PURGES_SYSARCHIVE/';

define('HEURIST_FILESTORE_ROOT', $upload_root );

$exclusion_list = exclusion_list();

if($exclusion_list===false) {exit;}

if(!$arg_no_action){
    if (!folderCreate($backup_root, true)) {
        exit("Failed to create backup folder $backup_root \n");
    }
    if (!folderCreate($backup_imports, true)) {
        exit("Failed to create backup$backup_sysarch folder $backup_imports \n");
    }
    if (!folderCreate($backup_sysarch, true)) {
        exit("Failed to create backup folder $backup_sysarch \n");
    }else{

    }

    echo 'Deleted databases: '.$backup_root."\n";
    echo 'Archieved import tables: '.$backup_imports."\n";
    echo 'Archieved sysArchive tables: '.$backup_sysarch."\n";


    $action = 'purgeOldDBs';
    if(false && !isActionInProgress($action, 1)){
        exit("It appears that backup operation has been started already. Please try this function later\n");
    }
}

/*TMP
//Arche_RECAP
//AmateurS1
$databases = array('AmateurS1');
*/

set_time_limit(0);//no limit
ini_set('memory_limit','1024M');

$datetime1 = date_create('now');
$cnt_archived = 0;
$email_list = array();
$email_list_deleted = array();
$email_list_failed = array();

foreach ($databases as $idx=>$db_name){

    if(in_array($db_name,$exclusion_list)){
        continue;
    }

    $res = mysql__usedatabase($mysqli, $db_name);
    if($res!==true){
        if(is_array($res) && $mysqli->error){
            //$mysqli->error 'gone away'
            $sMsg = 'Cannot execute purgeInactiveDBs. Execution stopped on database '
                .$db_name.' ('.$arg_no_action.','.$is_shell.'). Error message: '.$mysqli->error;
            error_log($sMsg);
            $sTitle = 'purgeInactiveDBs has been terminated. On '.HEURIST_SERVER_NAME;
            sendEmail(array(HEURIST_MAIL_TO_ADMIN), $sTitle, $sMsg, false);

            exit;
        }
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

    $d2 = $vals['mdate'];
/*
    $query = 'SELECT max(rty_Modified) as mdate FROM defRecTypes';
    $val = mysql__select_value($mysqli, $query);
    if($d2<$val){ $d2 = $val; }

    $query = 'SELECT max(rst_Modified) as mdate FROM defRecStructure';
    $val = mysql__select_value($mysqli, $query);
    if($d2<$val){ $d2 = $val; }

    $query = 'SELECT max(dty_Modified) as mdate FROM defDetailTypes';
    $val = mysql__select_value($mysqli, $query);
    if($d2<$val){ $d2 = $val; }

    $query = 'SELECT max(trm_Modified) as mdate FROM defTerms';
    $val = mysql__select_value($mysqli, $query);
    if($d2<$val){ $d2 = $val; }
*/
    $datetime2 = date_create($d2);

    if(!$datetime2){
        echo $tabs0.$db_name.' cannot detect modification date'.$eol;
        continue;
    }

    //"processing ".
    //echo $db_name.' ';//.'  in '.$folder
    $report = '';

    $interval = date_diff($datetime1, $datetime2);
    $diff = $interval->format('%y')*12 + $interval->format('%m');

    $archive_db = ($vals['cnt']<11 && $diff>=6) || ($vals['cnt']<51 && $diff>=12) || ($vals['cnt']<101 && $diff>=24);

    if($archive_db){ // check for structure updates

        $datetime3 = getDefinitionsModTime($mysqli);//see utils_db

        if(!$datetime3){
            echo $tabs0.$db_name.' cannot detect last structure modification date'.$eol;
            continue;
        }

        $interval = date_diff($datetime1, $datetime3);
        $diff = $interval->format('%y') * 12 + $interval->format('%m');

        $archive_db = $diff > 6; // more than six months ago
    }

    if($archive_db){
        //archive and drop database
        $report = $diff.' months, n='.$vals['cnt'];
        if($arg_no_action){
            $report .= ' to ARCHIVE';
        }else{
            $usr_owner = user_getByField($mysqli, 'ugr_ID', 2);

            $res = DbUtils::databaseDrop( false, $db_name, true );
            if($res){
                    array_push($email_list_deleted,
                        "<tr><td>$db_name</td><td>{$usr_owner['ugr_FirstName']} {$usr_owner['ugr_LastName']}</td><td>{$usr_owner['ugr_eMail']}</td></tr>");

                    $server_name = HEURIST_SERVER_NAME;
                    $email_title = 'Your Heurist database '.$db_name.' has been archived';
                    $email_text = <<<EOD
Dear {$usr_owner['ugr_FirstName']} {$usr_owner['ugr_LastName']},
<br><br>
Your Heurist database {$db_name} on {$server_name} has been archived. We would like to help you get (re)started, and can restore the database if it is still required (please read through this email before contacting us).
<br><br>
In order to conserve server space and reduce clutter we have archived your database on {$server_name} since it has not been modified for several months and/or no data has ever been created (you may also get this message if we are migrating a database to a new server for you).
<br><br>
The criteria for purging unused databases are:
<br><br>
   <span style="padding-left:15px">No data modification for 3 months and <= 10 records</span><br>
   <span style="padding-left:15px">No data modification for 6 months and <= 50 records</span><br>
   <span style="padding-left:15px">No data modification for 1 year and <= 100 records</span>
<br><br>
Note that structure modification is not taken into account in this calculation.
<br><br>
If you got as far as creating a database but did not know how to proceed you are not alone, but those who persevere, even a little, will soon find the system easy to use and surprisingly powerful. We invite you to get in touch ( support@HeuristNetwork.org ) so that we can help you over that (small) initial hump and help you see how it fits with your research (or other use).
<br><br>
Please contact us if you need your database re-enabled or visit one of our free servers to create a new database (visit HeuristNetwork dot org). If you didn't do any work on your database (entry of real data or setting up a structure for future use) please just create a new database (you can re-use the same name).
<br><br>
IMPORTANT: If the database was a finished project or reference database which will not be further modified, please let us know so that we can mark it as protected from future purges.
<br><br>
Heurist is research-led and responds rapidly to evolving user needs - we often turn around small user suggestions (and most bug-fixes) in a day and larger ones within a couple of weeks. Visit our website to see current developments. We aim for stable backwards compatibility and long-term sustainability, with active databases going back as far as the earliest version of Heurist (2005).
<br><br>
For more information email us at support@HeuristNetwork.org and visit our website at HeuristNetwork.org. We normally respond within hours, depending on time zones.
EOD;

if($need_email){
    sendEmail(array($usr_owner['ugr_eMail']), $email_title, $email_text, true);
}

                $report .= ' ARCHIVED';
                $cnt_archived++;
            }else{
                $err = $system->getError();
                error_log('purgeInactiveDBs. FAILED database drop: '.@$err['message']);
                $report .= (' ERROR: '.@$err['message']);

                array_push($email_list_failed,
                        "<tr><td>$db_name</td><td>".@$err['message'].TR_E);

            }
        }
    }else{

        if ($vals['cnt']>200 && $diff>=12){
            //send email to sysadmin
            $usr_owner = user_getByField($mysqli, 'ugr_ID', 2);

            $usr_owner  = 'Owner: '.$usr_owner['ugr_FirstName'].'  '.$usr_owner['ugr_LastName'].'   ('.$usr_owner['ugr_eMail'].')';

            $email_list[] = $db_name.'  '.$usr_owner.'  '
                .$vals['cnt'].' records. Last update: '.$datetime2->format('Y-m-d').' ('.$diff.' months ago)';

            $report =  $diff.' months, n='.$vals['cnt'].' INACTIVE';
        }else{


        }
/*
* Dump and bz2 import tables that are
*           more than 2 months old
*           older than 1 month if more than 10 tables
*           reduce to 20 most recent tables if more than 20 left
* HEURIST_FILESTORE/_PURGES_IMPORTS/dbname_[name of original file]_yyyy-mm-dd.bz2
*/

        $sif_purge = array();
        $sif_purge2 = array();
        $sif_list = mysql__select_assoc2($mysqli, 'SELECT sif_ID, sif_ProcessingInfo FROM sysImportFiles');//sif_TempDataTable,
        if(is_array($sif_list)){
            $sif_count = count($sif_list);
            $diff2 = ($sif_count>10) ?1 :2;

            foreach($sif_list as $sif_id => $sif_info){

                $data = json_decode($sif_info, true);
                if(!$data){
                    $k = strpos($sif_info,',"columns":[');
                    $sif_info = substr($sif_info,0,$k).'}';
                    $data = json_decode($sif_info, true);
                }

                if(!$data ||!@$data['import_table'] || !@$data['import_name'] ){
                    continue;
                }

//{"reccount":"697","import_table":"import20210630112638","import_name":"Glossaire.txt 2021-06-30 11:26:38"

                $imp_table = $data['import_table'];
                $file_name = $data['import_name'];
                $date = substr($file_name, strlen($file_name)-19);

                $datetime2 = date_create($date);

                $interval = date_diff($datetime1, $datetime2);
                $diff = $interval->format('%y')*12 + $interval->format('%m');

                if($diff>$diff2){
                    $sif_purge[$sif_id] = $imp_table;
                }

                if($sif_count-count($sif_purge2)>20){
                    $sif_purge2[$sif_id] = $imp_table; //all except 20 recent ones
                }
                $sif_list[$sif_id] = $file_name;
            }//foreach

            /*TMP!*/
            if($sif_count-count($sif_purge)>20){ //still more than 20
                $sif_purge = $sif_purge2;
            }


            if(!empty($sif_purge)){


            $report .= (' ... '.count($sif_purge).' import tables, archive');

            if(!$arg_no_action){

            //dump and archive
            // Do an SQL dump for import tables
            $backup_imports2 = $backup_imports.basename($db_name);
            if (!folderCreate($backup_imports2, true)) {
                exit("$db_name Failed to create backup folder $backup_imports2 \n");
            }

            $cnt_dumped = 0;


            $arc_cnt = 0;
            $cnt_dumped = 0;
            if(ALLOW_PURGE_IMPORTTABLES){

            foreach($sif_purge as  $sif_id => $sif_table){

                if(hasTable($mysqli,$sif_table)){
                    $file_name = USanitize::sanitizeFileName($sif_list[$sif_id]);

                    $file_name = preg_replace('/[()]/g','',$file_name);

                    $len = strlen($file_name);
                    if($len>96){ //100 is max for tar file
                        $len = $len-100+26;
                        $file_name = substr($file_name,0,-$len).substr($file_name,-19);
                    }

                    $dumpfile = $backup_imports2."/".$file_name.'.sql';

                    $opts = array('include-tables' => array($sif_table),
                                  'default-character-set'=>'utf8',
                                  'single-transaction'=>true,
                                  'no-create-info'=>true,
                                  'skip-triggers' => true,
                                  'add-drop-trigger' => false);

                    $res = DbUtils::databaseDump($db_name, $dumpfile, $opts);//import tables
                    if($res===false){
                        $err = $system->getError();
                        $report .= (" Error: unable to generate MySQL database dump for import table $sif_table in $db_name. "
                                .$err['message']."\n");
                        if($err['status']==HEURIST_SYSTEM_CONFIG) {break;}
                    }else{
                        $cnt_dumped++;
                    }

                    /*
                        try{
                            $pdo_dsn = 'mysql:host='.HEURIST_DBSERVER_NAME.';dbname=hdb_'.$db_name.';charset=utf8mb4';
                            $dump = new Mysqldump( $pdo_dsn, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD, $opts);

                            $dump->start($dumpfile);

                            $cnt_dumped++;
                        } catch (Exception $e) {
                           $report .= (" Error: unable to generate MySQL database dump for import table $sif_table in $db_name."
                                .$e->getMessage()."\n");
                        }
                    */
                }
            }//foreach
            }

            if($cnt_dumped>0)
            {   //archive import tables
                $archOK = true;
                $destination = $backup_imports.basename($db_name).' '.$datetime1->format('Y-m-d').'.tar';
                $archOK = UArchive::createBz2($backup_imports2, null, $destination, false);

                if($archOK){
                    $report .= 'd';
                    //drop tables
                    $query = 'DROP TABLE IF EXISTS '.implode(',', $sif_purge);
                    $mysqli->query($query);
                    $query = 'DELETE FROM sysImportFiles WHERE sif_ID IN ('.implode(',', array_keys($sif_purge)).')';
                    $mysqli->query($query);
                    $report .= ('   done');
                }else{
                    $report .= " Cannot create archive import tables. Failed to archive $backup_imports2 to $destination \n";
                }
            }
            //remove folder
            folderDelete($backup_imports2);
            chdir($upload_root);

            }//no action

            }//cnt>0
        }//sif list

        if(ALLOW_ARCHIVE_SYSARCHIVE){ //alow archive sysArchive
        $arc_count = intval(mysql__select_value($mysqli, 'SELECT count(arc_ID) FROM sysArchive'));//sif_TempDataTable,
        if($arc_count>50000){

            if($arg_no_action){
                    $report .= (' ... sysArchive, n='.$arc_count.', archive');
            }else{

                $dumpfile = $backup_sysarch.basename($db_name).'_'.$datetime1->format('Y-m-d').'.sql';//.$db_name.' '
                    $opts = array('include-tables' => array('sysArchive'),
                                  'default-character-set'=>'utf8',
                                  'single-transaction'=>true,
                                  'no-create-info'=>true,
                                  'skip-triggers' => true,
                                  'single-transaction' => false,
                                  'add-drop-trigger' => false);

                    /*
                        try{
                            $pdo_dsn = 'mysql:host='.HEURIST_DBSERVER_NAME.';dbname=hdb_'.$db_name.';charset=utf8mb4';
                            $dump = new Mysqldump( $pdo_dsn, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD, $opts);


                            $dump->start($dumpfile);

                            $res = true;
                        } catch (Exception $e) {
                            $report .= ("Error: ".$e->getMessage()."\n");
                            $res = false;
                        }
                    */

                    $res = DbUtils::databaseDump($db_name, $dumpfile, $opts);//sysArchive
                    if($res===false){
                        $err = $system->getError();

                        $report .= (" Error: unable to generate MySQL database dump $dumpfile for sysArchive table in $db_name.\n"
                                    .$err['message']."\n");
                        if($err['status']==HEURIST_SYSTEM_CONFIG) {break;}
                    }else{
                        $destination = $backup_sysarch.basename($db_name).'_'.$datetime1->format('Y-m-d');

                        if( extension_loaded('bz2') ){

                            $destination = $destination.'.tar';



                            $archOK = UArchive::createBz2($dumpfile, null, $destination, false);
                        }else{

                            $destination = $destination.'.zip';
                            $archOK = UArchive::zip($dumpfile, null, $destination, false);
                        }

                        if($archOK){
                            //clear table
                            $query = 'DROP TABLE sysArchive';
                            $mysqli->query($query);
                            $mysqli->query("CREATE TABLE sysArchive (
  arc_ID int(10) unsigned NOT NULL auto_increment COMMENT 'Primary key of archive table',
  arc_Table enum('rec','cfn','crw','dtg','dty','fxm','ont','rst','rtg','rty','rcs','trm','trn','urp','vcb','dtl','rfw','rrc','snd','cmt','ulf','sys','lck','tlu','ugr','ugl','bkm','hyf','rtl','rre','rem','rbl','svs','tag','wprm','chunk','wrprm','woot') NOT NULL COMMENT 'Identification of the MySQL table in which a record is being modified',
  arc_PriKey int(10) unsigned NOT NULL COMMENT 'Primary key of the MySQL record in the table being modified',
  arc_ChangedByUGrpID smallint(5) unsigned NOT NULL COMMENT 'User who is logged in and modifying this data',
  arc_OwnerUGrpID smallint(5) unsigned default NULL COMMENT 'Owner of the data being modified (if applicable eg. records, bookmarks, tags)',
  arc_RecID int(10) unsigned default NULL COMMENT 'Heurist record id (if applicable, eg. for records, bookmarks, tag links)',
  arc_TimeOfChange timestamp NOT NULL default CURRENT_TIMESTAMP COMMENT 'Timestamp of the modification',
  arc_DataBeforeChange mediumblob COMMENT 'A representation of the data in the MySQL record before the mod, may be a diff',
  arc_ContentType enum('del','raw','zraw','diff','zdiff') NOT NULL default 'raw' COMMENT 'Format of the data stored, del=deleted, raw=text dump, Diff=delta, Z=zipped indicates ',
  PRIMARY KEY  (arc_ID),
  KEY arc_Table (arc_Table,arc_ChangedByUGrpID,arc_OwnerUGrpID,arc_RecID,arc_TimeOfChange)
) ENGINE=InnoDB COMMENT='An archive of all (or most) changes in the database to allow'");

                            $report .= (' ... sysArchive, n='.$arc_count.', archived');
                        }else{
                            $report .= ("Cannot create archive sysArchive table. Failed to archive $dumpfile to $destination");
                        }
                        unlink($dumpfile);
                    }
            }

        }
        }

    }

    if($report!=''){
        echo $tabs0.$db_name.$tabs.htmlspecialchars($report).$eol; //htmlspecialchars for snyk
    }


    //echo "   ".$db_name." OK \n";//.'  in '.$folder
}//for


if(!$arg_no_action){
    //report after actual action
    echo $tabs0.'Archived '.$cnt_archived.' databases'.$eol;

    if( (!empty($email_list_deleted) || !empty($email_list_failed)) && $need_email){
        $sTitle = 'Archived databases on '.HEURIST_SERVER_NAME;
        $sMsg = $sTitle.TABLE_S.implode("\n", $email_list_deleted).TABLE_E;
        if(!empty($email_list_failed)){
             $sMsg = $sMsg.'<br>FAILED on database drop'.TABLE_S.implode("\n", $email_list_failed).TABLE_E;
        }
        sendEmail(array(HEURIST_MAIL_TO_ADMIN), $sTitle, $sMsg, true);
    }
}

echo $tabs0.'finished'.$eol;

if(!isEmptyArray($email_list) && $need_email)
{
    sendEmail(HEURIST_MAIL_TO_ADMIN, "List of inactive databases on ".HEURIST_SERVER_NAME,
        "List of inactive databases for more than a year with more than 200 records:\n"
        .implode(",\n", $email_list));
}

function exclusion_list(){
    global $arg_no_action;

    $res = array();
    $fname_ = dirname(__FILE__)."/../../../databases_not_to_purge.txt";

    $fname = realpath($fname_);
    if($fname==false || !file_exists($fname)){

        $sMsg = 'The file with purge exclustion list (databases_not_to_purge.txt) '
            .'was not found and please create it. '.($fname?$fname:$fname_);
        if($arg_no_action){
               print $sMsg.'<br>';
        }
        sendEmail(HEURIST_MAIL_TO_ADMIN, 'Purge exclustion list not found', $sMsg);
        return false;
    }


    $handle = @fopen($fname, "r");
    while (!feof($handle)) {
        $line = trim(fgets($handle, 100));

        if(strpos($line,'#')!==false){
            $line = trim(strstr($line,'#',true));
        }
        if($line=='') {continue;}

        $res[] = $line;
    }
    fclose($handle);
    if($arg_no_action){
        print '<br>Exclusion list:<br>';
        print implode('<br>', $res).'<br><br>';
    }


    return $res;
}

?>