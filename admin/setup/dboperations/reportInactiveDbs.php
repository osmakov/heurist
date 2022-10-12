<?php

/**
* Reports (default) or purge/archive any database not updated for more than: 
*           3 months with 10 records or less
*           6 months with 50 records or less
*           one year with 200 records or less 
* Send sysadmin a list of databases
*            for more than a year with more than 200 records
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
* 
* Databases in HEURIST/databases_not_to_purge.txt are ignored
* 
* Runs from shell only
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2022 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

// Default values for arguments
$arg_no_action = true;  
$eol = "\n";
$tabs = "\t\t";
$tabs0 = '';

if (@$argv) {
    
// example:
//  sudo php -f /var/www/html/h6-alpha/export/dbbackup/reportInactiveDbs.php -- -purge
//  sudo php -f reportInactiveDbs.php -- -purge  -  action, otherwise only report 

// TODO: It would be good if this had a parameter option to also delete the database for use when transferring to a new server
// TODO: WARNING: AT THIS TIME (21 May 2022) IT DOES NOT REPORT AN ERROR IF THERE IS NO FILESTORE FOLDER

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
    
    if (@$ARGV['-purge']) $arg_no_action = false;

}else{
    //report only
    $arg_no_action = true;
    $eol = "</div><br>";
    $tabs0 = '<div style="min-width:300px;display:inline-block;">';
    $tabs = "</div>".$tabs0;
    //exit('This function must be run from the shell');
}


require_once(dirname(__FILE__).'/../../configIni.php'); // read in the configuration file
require_once(dirname(__FILE__).'/../../hsapi/consts.php');
require_once(dirname(__FILE__).'/../../hsapi/System.php');
require_once(dirname(__FILE__).'/../../hsapi/dbaccess/db_files.php');
require_once(dirname(__FILE__).'/../../hsapi/utilities/dbUtils.php');
require_once(dirname(__FILE__).'/../../external/php/Mysqldump.php');

//retrieve list of databases
$system = new System();
if( !$system->init(null, false, false) ){
    exit("Cannot establish connection to sql server\n");
}

if(!defined('HEURIST_MAIL_DOMAIN')) define('HEURIST_MAIL_DOMAIN', 'cchum-kvm-heurist.in2p3.fr');
if(!defined('HEURIST_SERVER_NAME')) define('HEURIST_SERVER_NAME', 'heurist.huma-num.fr');

print 'Mail: '.HEURIST_MAIL_DOMAIN.'   Domain: '.HEURIST_SERVER_NAME."\n";

$mysqli = $system->get_mysqli();
$databases = mysql__getdatabases4($mysqli, false);   
//DEBUG $databases = array('ACD_Basins','ACD_Candlesticks');

$upload_root = $system->getFileStoreRootFolder();
$backup_root = $upload_root.'DELETED_DATABASES/';
$backup_imports = $upload_root.'_PURGES_IMPORTS/';
$backup_sysarch = $upload_root.'_PURGES_SYSARCHIVE/';

define('HEURIST_FILESTORE_ROOT', $upload_root );

$exclusion_list = exclusion_list();

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
        //echo $backup_sysarch.' exists'."\n";
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
//$databases = array('ARNMP_COMET','ArScAn_Material','arthur_base','arvin_stamps');
$databases = array('AmateurS1');
//$databases = array('ARNMP_COMET');
*/

set_time_limit(0); //no limit
ini_set('memory_limit','1024M');

$datetime1 = date_create('now');
$cnt_archived = 0;
$email_list = array();

foreach ($databases as $idx=>$db_name){
    
    if(in_array($db_name,$exclusion_list)){
        continue;
    }
    
    if(!mysql__usedatabase($mysqli, $db_name)){
        echo $system->getError()['message']."\n";
        continue;
    }

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
    //echo $db_name.' '; //.'  in '.$folder
    $report = '';
    
    $interval = date_diff($datetime1, $datetime2);    
    $diff = $interval->format('%y')*12 + $interval->format('%m');

    if(($vals['cnt']<11 && $diff>=3) || ($vals['cnt']<51 && $diff>=6) || ($vals['cnt']<201 && $diff>=12)){
        //archive and drop database
        $report = $diff.' months, n='.$vals['cnt'];
        if($arg_no_action){
            $report .= ' ARCHIVE'; 
        }else{
            $usr_owner = user_getByField($mysqli, 'ugr_ID', 2);
            
            $res = DbUtils::databaseDrop( false, $db_name, true );
            if($res){
                    $server_name = HEURIST_SERVER_NAME;
                    $email_title = 'Your Heurist database '.$db_name.' has been archived';
                    $email_text = <<<EOD
Dear {$usr_owner['ugr_FirstName']} {$usr_owner['ugr_LastName']},                    
                    
Your Heurist database {$db_name} on {$server_name} has been archived. We would like to help you get (re)started.

In order to conserve server space (or as part of moving to another server) we have archived your database on {$server_name} since it has not been used for several months and/or no data has ever been created and/or it is being recreated somewhere else. 
 
If you got as far as creating a database but did not know how to proceed you are not alone, but those who persevere, even a little, will soon find the system easy to use and surprisingly powerful. We invite you to get in touch ( support@HeuristNetwork.org ) so that we can help you over that (small) initial hump and help you see how it fits with your research (or other use). 

With a brand new interface in 2021, developed in collaboration with an experienced UX designer, Heurist is significantly easier to use than previous versions. The new interface (version 6) remains compatible with databases created more than 10 years ago - we believe in sustainability.
 
Please contact us if you need your database re-enabled or visit one of our free servers to create a new database (visit HeuristNetwork dot org).
 
Heurist is research-led and responds rapidly to evolving user needs - we often turn around small user suggestions (and most bug-fixes) in a day and larger ones within a couple of weeks. We are still forging ahead with new capabilities such as enhancements to the integrated CMS website builder, new output formats, improved data exchange through XML, JSON, remote resource lookups and linked open data, and improved search, mapping and visualisation widgets (embeddable in websites). 
 
For more information email us at support@HeuristNetwork.org and visit our website at HeuristNetwork.org. We normally respond within hours, depending on time zones. We are actively developing new documentation and training resources for version 6 and can make advance copies available on request.                    
EOD;
                            
sendEmail(array($usr_owner['ugr_eMail'], HEURIST_MAIL_TO_ADMIN), $email_title, $email_text);                
                
                $report .= ' ARCHIVED'; 
                $cnt_archived++;
            }else{
                $report .= ('ERROR: '.$system->getError()['message']);
            }
        }
    }else{
        
        if ($vals['cnt']>200 && $diff>=12){
            //send email to sysadmin
            $usr_owner = user_getByField($mysqli, 'ugr_ID', 2);
            
            $usr_owner  = 'Owner: '.$usr_owner['ugr_FirstName'].'  '.$usr_owner['ugr_LastName'].'   ('.$usr_owner['ugr_eMail'].')';
            
            $email_list[] = $db_name.'  '.$usr_owner.'  '
                .$vals['cnt'].' records. Last update: '.$datetime2->format('Y-m-d').' ('.$diff.' months ago)';
                
            $report =  $diff.' months, n='.$vals['cnt'].' EMAIL';
        }else{
            //echo ' '.$vals['cnt'].' records '.$diff.' months. OK'."\n";
            //no report for db without action echo $eol;
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
        $sif_list = mysql__select_assoc2($mysqli, 'SELECT sif_ID, sif_ProcessingInfo FROM sysImportFiles'); //sif_TempDataTable, 
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
            
            
            if(count($sif_purge)>0){
                
                        
            $report .= (' ... '.count($sif_purge).' import tables, archive');
            
            if(!$arg_no_action){

            //dump and archive
            // Do an SQL dump for import tables
            $backup_imports2 = $backup_imports.$db_name;
            if (!folderCreate($backup_imports2, true)) {
                exit("$db_name Failed to create backup folder $backup_imports2 \n");
            }
            
            $cnt_dumped = 0;
            
            //$sif_purge = array( 3 => 'import20210531163600');
            $arc_cnt = 0;
            $cnt_dumped = 10;
            if(false){
            
            foreach($sif_purge as  $sif_id => $sif_table){
                
                if(hasTable($mysqli,$sif_table)){
                    $file_name = $sif_list[$sif_id];
                    try{
                        $dump = new Mysqldump( 'hdb_'.$db_name, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD, HEURIST_DBSERVER_NAME, 'mysql', 
                            array('include-tables' => array($sif_table),
                                  'skip-triggers' => true,  
                                  'add-drop-trigger' => false));
                        $dumpfile = $backup_imports2."/".fileNameSanitize($file_name).'.sql';  //.$db_name.' '
                        $dump->start($dumpfile);
                        
                        /*
                        if($summary_size>256){
                            $destination = $backup_imports.$db_name.' '.$datetime1->format('Y-m-d')
                                    .($arc_cnt>0?('_'.$arc_cnt):'').'.tar';
                            $archOK = createBz2Archive($backup_imports2, null, $destination, false);
                            
                            $arc_cnt++;
                            if(!$archOK){
                                $report .= " Cannot create archive import tables. Failed to archive $backup_imports2 to $destination";
                                break;
                            }
                        }*/
            
                        $cnt_dumped++;            
                    } catch (Exception $e) {
                       $report .= (" Error: unable to generate MySQL database dump for import table  $db_name.".$e->getMessage());
                    }
                }
            }//foreach
            }
            
            $archOK = true;
            if($cnt_dumped>0){
                $destination = $backup_imports.$db_name.' '.$datetime1->format('Y-m-d').'.tar';
                $archOK = createBz2Archive($backup_imports2, null, $destination, false);
            }
            
            if($archOK){
                $report .= 'd';
                //drop tables
                $query = 'DROP TABLE IF EXISTS '.implode(',', $sif_purge);
                $mysqli->query($query);
                $query = 'DELETE FROM sysImportFiles WHERE sif_ID IN ('.implode(',', array_keys($sif_purge)).')';
                $mysqli->query($query);
                $report .= ('   done');
            }else{
                $report .= " Cannot create archive import tables. Failed to archive $backup_imports2 to $destination";
            }
            //remove folder
            folderDelete($backup_imports2);
            chdir($upload_root);
            
            }//no action
            }//cnt>0
        }//sif list
        
        if(true){
        $arc_count = mysql__select_value($mysqli, 'SELECT count(arc_ID) FROM sysArchive'); //sif_TempDataTable, 
        if($arc_count>50000){
            
            if($arg_no_action){
                    $report .= (' ... sysArchive, n='.$arc_count.', archive');
            }else{
                    try{
                        $dumpfile = $backup_sysarch.$db_name.'_'.$datetime1->format('Y-m-d').'.sql';  //.$db_name.' '
                        
                        $dump = new Mysqldump( 'hdb_'.$db_name, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD, HEURIST_DBSERVER_NAME, 'mysql', 
                            array('include-tables' => array('sysArchive'),
                                  'skip-triggers' => true,  
                                  'add-drop-trigger' => false));

                        //echo $db_name.' purge sysArchive to '.$dumpfile;
                        
                        $dump->start($dumpfile);
            
                        //echo $db_name.' ... dumped ';
                        
                        
                        $destination = $backup_sysarch.$db_name.'_'.$datetime1->format('Y-m-d');
                        
                        if( extension_loaded('bz2') ){
            
                            $destination = $destination.'.tar';

                            //echo ' ... archived to '.$destination."\n";

                            $archOK = createBz2Archive($dumpfile, null, $destination, false);
                        }else{
                            
                            $destination = $destination.'.zip'; 
                            $archOK = createZipArchive($dumpfile, null, $destination, false);
                        }
                        
                        if($archOK){
                            //clear table
                            $query = 'DELETE FROM sysArchive WHERE arc_ID>0';
                            $mysqli->query($query);
                            $report .= (' ... sysArchive, n='.$arc_count.', archived');
                        }else{
                            $report .= ("Cannot create archive sysArchive table. Failed to archive $dumpfile to $destination");
                        }
                        unlink($dumpfile);                        
                        
                    } catch (Exception $e) {
                        $report .= ("Error: unable to generate MySQL database dump for sysArchive table in $db_name.".$e->getMessage());
                    }
            }   
            
        }
        }
        
    }
    
    if($report!=''){
        echo $tabs0.$db_name.$tabs.$report.$eol;
    }


    //echo "   ".$db_name." OK \n"; //.'  in '.$folder
}//for


if(!$arg_no_action){
    echo $tabs0.'Archived '.$cnt_archived.' databases'.$eol;    
}

echo ($tabs0.'finished'.$eol);

if(is_array($email_list) && count($email_list)>0){
    
sendEmail(HEURIST_MAIL_TO_ADMIN, "List of inactive databases on ".HEURIST_SERVER_NAME,
    "List of inactive databases for more than a year with more than 200 records:\n"
    .implode(",\n", $email_list));
}

function exclusion_list(){
    
    $res = array();
    $fname = realpath(dirname(__FILE__)."/../../../databases_not_to_purge.txt");
    if(file_exists($fname)){
        //ini_set('auto_detect_line_endings', true);
        $handle = @fopen($fname, "r");
        while (!feof($handle)) {
            $line = trim(fgets($handle, 100));
            if($line=='' || substr($line,0,1)=='#') continue;
            $res[] = $line;
        }
        fclose($handle);
    }
    return $res;
}

?>