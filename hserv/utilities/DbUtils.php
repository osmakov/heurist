<?php
namespace hserv\utilities;
use hserv\utilities\DbRegis;
use hserv\utilities\UArchive;

/**
* dbUtils.php : Functions to create, delelet, clean the entire HEURIST database
*               and other functions to do with database file structure
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
* @subpackage  DataStore
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* Static class to perform database operations
*
* Methods:
*
* databaseDrop - Removes database entirely with optional beforehand archiving
* databaseDump - dumps all tables (except csv import cache) into SQL dump
* databaseCreateFull - Creates new heurist database, with file folders, given user and ready to use
* databaseValidateName - Verifies that database name is valid and optionally that database exists or unique
* databaseRestoreFromArchive - Restores database from archive
* databaseEmpty - Clears data tables (retains defintions)
* databaseCloneFull - clones database including folders
* databaseResetRegistration
* databaseRename - renames database (in fact it clones database with new name and archive/drop old database)
*
* databaseCheckNewDefs
* updateOriginatingDB - Assigns given Origin ID for rectype, detail and term defintions
* updateImportedOriginatingDB - Assigns Origin ID for rectype, detail and term defintions after import from unregistered database
*
* private:
*
* _databaseInitForNew - updates dbowner, adds default saved searches and lookups
* databaseClone - copy all tables (except csv import cache) from one db to another (@todo rename to _databaseCopyTables)
* _emptyTable - delete all records for given table
* databaseCreateFolders - creates if not exists the set of folders for given database
* databaseCreate - Creates new heurist database
* databaseCreateConstraintsAndTriggers - Recreates constraints and triggers
*/

require_once 'utils_db_load_script.php';
require_once dirname(__FILE__).'/../../external/php/Mysqldump8.php';
require_once dirname(__FILE__).'/../structure/import/importDefintions.php';

class DbUtils {

     /**
     * Construct won't be called inside this class and is uncallable from
     * the outside. This prevents instantiating this class.
     * This is by purpose, because we want a static class.
     */
    private function __construct() {}
    private static $mysqli = null;
    private static $system = null;
    private static $initialized = false;
    private static $db_del_in_progress = null;
    private static $session_id = 0;
    private static $progress_step = 0;

    public static function initialize($mysqli=null)
    {
        if (self::$initialized) {return;}

        global $system;
        self::$system = $system;

        if($mysqli){
            self::$mysqli = $mysqli;
        }else{
            self::$mysqli = $system->get_mysqli();
        }

        self::$initialized = true;
    }

    //
    // init progress session
    //
    public static function setSessionId($id){
        self::$session_id = $id;
        self::$progress_step = 0;
    }

    //
    // update progress session value
    // returns true if session has been terminated
    //
    public static function setSessionVal($session_val){

        if(self::$progress_step>0 && intval($session_val)>0){
            $session_val = self::$progress_step+$session_val;
        }

        $current_val = mysql__update_progress(self::$mysqli, self::$session_id, false, $session_val);
        if($current_val=='terminate'){ //session was terminated from client side
            self::$session_id = 0;
            return true;
        }else{
            return false;
        }
    }

    //
    //
    //
    public static function databaseCheckNewDefs($database=null){

        if($database!=null){
            list($database_full, $database ) = mysql__get_names($database);
            $database_full = '`'.$database_full.'`.';
        }else{
            $database_full = '';
        }

        //check for new definitions
        $rty = mysql__select_value(self::$mysqli, "SELECT count(*) FROM {$database_full}defRecTypes "
            ." WHERE (rty_OriginatingDBID = '0') OR (rty_OriginatingDBID IS NULL)");
        $dty = mysql__select_value(self::$mysqli, "SELECT count(*) FROM {$database_full}defDetailTypes "
            ." WHERE (dty_OriginatingDBID = '0') OR (dty_OriginatingDBID IS NULL)");
        $trm = mysql__select_value(self::$mysqli, "SELECT count(*) FROM {$database_full}defTerms "
            ." WHERE (trm_OriginatingDBID = '0') OR (trm_OriginatingDBID IS NULL)");

        $sHasNewDefsWarning = false;
        if($rty>0 || $dty>0 || $trm>0){
            $s = array();
            if($rty>0) { $s[] = intval($rty).' record types';}
            if($dty>0) { $s[] = intval($dty).' base fields';}
            if($trm>0) { $s[] = intval($trm).' vocabularies or terms';}
            $sHasNewDefsWarning = implode(', ',$s);
        }

        return $sHasNewDefsWarning;
    }

    /**
    * Removes database entirely
    *
    * @param mixed $verbose
    * @param mixed $database_name - name of database to be deleted
    * @param true $createArchive - if true - creates db dump and archives all uploaded files
    */
    public static function databaseDrop( $verbose=false, $database_name=null, $createArchive=false ){

        // 1. Create an SQL dump in the filestore direcory
        // 2. Zip the filestore directories (using bzip2) directly into the DELETED_DATABASES directory
        // 3. Delete filestore directory for the database
        // 4. Drop database
        // 5. Append row to DELETED_DATABASES_LOG.csv in the Heurist filestore.

        self::initialize();

        if(self::$db_del_in_progress!==null){
            //DELETION ALREADY IN PROGRESS
            return false;
        }

        $format = 'zip';
        if(!is_bool($createArchive)){ //default is zip format
            $format = ($createArchive=='tar')?'tar':'zip';
            $createArchive = true;
        }

        self::$db_del_in_progress = null;

        if($database_name==null){
            $msg = 'Database parameter not defined';
            self::$system->addError(HEURIST_INVALID_REQUEST, $msg);
            if($verbose) {echo '<br>'.$msg;}
            return false;
        }

        $mysqli = self::$mysqli;
        $system = self::$system;

        self::$db_del_in_progress = $database_name;

        list($database_name_full, $database_name) = mysql__get_names( $database_name );
        $msg_prefix = "Unable to delete <b> $database_name </b>. ";

        if(defined('HEURIST_DBNAME') && $database_name!=HEURIST_DBNAME){ //switch to database
           $connected = (mysql__usedatabase($mysqli, $database_name_full)===true);
        }else{
           $connected = true;
        }

        $archiveFolder = HEURIST_FILESTORE_ROOT."DELETED_DATABASES/";
        $db_dump_file = null;

        $source = HEURIST_FILESTORE_ROOT.$database_name.'/';//  HEURIST_FILESTORE_DIR;  database upload folder
        $archOK = true;


        if(!$connected){
            $msg = $msg_prefix.' Failed to connect to database '
                    .($database_name).'  '.($createArchive);
            $system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
            if($verbose) {echo '<br>'.htmlspecialchars($msg);}
            self::$db_del_in_progress = null;
            return false;
        }elseif($createArchive) {
            // Create DELETED_DATABASES directory if needed
            if(!folderCreate($archiveFolder, true)){
                    $system->addError(HEURIST_ACTION_BLOCKED,
                        $msg_prefix.' Cannot create archive folder for database to be deleteted.');
                    self::$db_del_in_progress = null;
                    return false;
            }

            self::setSessionVal(1);//archive folder created

            $db_dump_file = self::databaseDump( $database_name, null, null, $verbose );

            if ($db_dump_file===false) {
                    $msg = $msg_prefix.' Failed to dump database to a .sql file';
                    self::$system->addError(HEURIST_ACTION_BLOCKED, $msg);
                    if($verbose) {echo '<br>'.htmlspecialchars($msg);}
                    self::$db_del_in_progress = null;
                    return false;
            }

            if(self::setSessionVal(2)) {return false;} //database dumped

            // Zip $source to $destination
            $datetime1 = date_create('now');
            $destination = $archiveFolder.$database_name.'_'.$datetime1->format('Y-m-d_H_i_s');

            $filestore_dir = HEURIST_FILESTORE_ROOT.$database_name.'/';
            $folders_to_copy = folderSubs($filestore_dir, array('backup', 'scratch', 'documentation_and_templates',
            //'uploaded_files', 'uploaded_tilestacks',
            'rectype-icons','term-images','webimagecache','blurredimagescache'));
            foreach($folders_to_copy as $idx=>$folder_name){
                $folder_name = realpath($folder_name);
                if($folder_name!==false){
                    $folders_to_copy[$idx] = str_replace('\\', '/', $folder_name);
                }

            }

            $folders_to_copy[] = realpath($db_dump_file);

            if($format=='zip' || !extension_loaded('bz2')){

                $destination = $destination.'.zip';

                $archOK = UArchive::zip($source, $folders_to_copy, $destination, $verbose);
            }else{
                $destination = $destination.'.tar';

                $archOK = UArchive::createBz2($source, $folders_to_copy, $destination, $verbose);
            }

            if($archOK!==true){

                if($verbose){
                    $msg_prefix = $msg_prefix.' <br>'.$archOK;
                    $archOK = false;
                }

                $msg = $msg_prefix.' Cannot create archive with database folder. Failed to archive '
                        .($source).' to '.($destination);
                self::$system->addError(HEURIST_SYSTEM_CONFIG, $msg);
                if($verbose) {echo '<br>'.htmlspecialchars($msg);}
                self::$db_del_in_progress = null;
                return false;
            }

            if(self::setSessionVal(3)) {return false;} //database dump archived
        }

        if($archOK){

            //get owner info
            $owner_user = user_getDbOwner($mysqli);

            //set it to false to check archiving only
            $real_delete_database = true;
            if($real_delete_database){

                $regID = mysql__select_value($mysqli, 'select sys_dbRegisteredID from sysIdentification where 1');


                // Delete database from MySQL server
                if(!mysql__drop_database($mysqli, $database_name_full)){

                    $msg = $msg_prefix.' Database error on sql drop operation. '.$mysqli->error;
                    self::$system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
                    if($verbose) {echo '<br>'.htmlspecialchars($msg);}
                    return false;
                }

                if(self::setSessionVal(4)) {return false;} //database dropped

                if($verbose) {
                    echo "<br>Database ".htmlspecialchars($database_name)." has been dropped";
                }

                // Delete $source folder
                folderDelete($source);
                //change current folder
                chdir(HEURIST_FILESTORE_ROOT);
                if($verbose) {
                    echo "<br>Folder ".htmlspecialchars($source)." has been deleted";
                }
                if(self::setSessionVal(5)) {return false;} //database folder deleted

                //add to log file
                $filename = HEURIST_FILESTORE_ROOT.'DELETED_DATABASES_LOG.csv';
                $fp = fopen($filename, 'a');//open for add
                if($fp){
                    $row = array($database_name,
                        $owner_user['ugr_LastName'],
                        $owner_user['ugr_FirstName'],
                        $owner_user['ugr_eMail'],
                    date_create('now')->format(DATE_8601));
                    fputcsv($fp, $row);
                    fclose($fp);
                }

                if($regID>0)
                {
                    /* TEMP
                    $dbowner = user_getDbOwner($mysqli);
                    $params = array(
                        'action'=>'delete',
                        'dbID'=>$regID,
                        'usrPassword'=>$dbowner['ugr_Password'],
                        'usrEmail'=>$dbowner['ugr_eMail']
                    );
                    $res = DbRegis::registrationDelete($params);
                    // if not integer - this is error
                    if(is_bool($res) && $res===false){
                        self::$system->addErrorMsg(
                            'Failed to delete record in reference index for #'.$regID.' for deleted database '.$db_target.'<br>');
                    }
                    */
                }
            }

            self::$db_del_in_progress = null;
            return true;
        }


        self::$db_del_in_progress = null;
        return false;

    }


    /**
    * Dumps all tables into SQL dump
    *
    * @param mixed $database_name   - database name if not defined - current database
    * @param mixed $database_dumpfile - name of dumpfile
    * @param mixed $dump_options
    * @param mixed $verbose
    */
    public static function databaseDump($database_name=null, $database_dumpfile=null, $dump_options=null, $verbose=false ) {

        self::initialize();

        list($database_name_full, $database_name) = mysql__get_names( $database_name );

        $mysqli = self::$mysqli;

        if(defined('HEURIST_DBNAME') && $database_name!=HEURIST_DBNAME){ //switch to database
           $connected = (mysql__usedatabase($mysqli, $database_name_full)===true);
        }else{
           $connected = true;
        }

        if($connected){

            // dump will be created in database upload folder
            if($database_dumpfile==null){
                $directory = HEURIST_FILESTORE_ROOT.basename($database_name);

                // Define dump file name
                $database_dumpfile = $directory.'/'.basename($database_name_full).'_'.time().'.sql';
            }

            if($dump_options==null){
                $dump_options = array(
                        'add-drop-table' => true,
                        'single-transaction' => true, //improve performance on restore
                        'quick' =>true,               //improve performance on restore
                        'add-drop-trigger' => true,
                        //'databases' => true,
                        'skip-triggers' =>true,
                        'skip-dump-date' => true,
                        //'routines' =>true,
                        'no-create-db' =>true,
                        'add-drop-database' => true);

                //do not archive sysArchive and import tables??


            }else{
                //$dump_options = array('skip-triggers' => true,  'add-drop-trigger' => false);
            }

            //0: use 3d party PDO mysqldump, 2 - call mysql via shell (default)
            $dbScriptMode = defined('HEURIST_DB_MYSQL_DUMP_MODE')?HEURIST_DB_MYSQL_DUMP_MODE :0;

            if($dbScriptMode==2){  //use native mysqldump
                if (!defined('HEURIST_DB_MYSQLDUMP') || !file_exists(HEURIST_DB_MYSQLDUMP)){

                    $msg = 'The path to mysqldump has not been correctly specified. '
                    .'Please ask your system administrator to fix this in the heuristConfigIni.php '
                    .'(note the settings required for a single server vs mysql running on a separate server)';

                    self::$system->addError(HEURIST_SYSTEM_CONFIG, $msg);
                    if($verbose) {echo '<br>'.$msg;}
                    return false;
                }
            }else{ //use php library
                $dbScriptMode = 0;
            }

            if($verbose){
                echo 'dump mode: '.$dbScriptMode.'<br>';
            }

            if($dbScriptMode==2){ // use mysql native mysqldump utility via shell

                $tables = array();
                $options = '';

                foreach($dump_options as $opt => $val){

                    if($opt=='include-tables'){
                        if(!isEmptyArray($val)){
                            $tables = $val;
                        }
                    }elseif($val===true){
                        $options = $options .' --'.$opt;
                    }elseif($val!==false){
                        $options = $options .' --'.$opt.'='.$val;
                    }
                }

                if(!empty($tables)){
                    $tables = implode(' ', $tables);//'--tables '.
                }else{
                    $tables = '';
                }

                //--log-error=mysqldump_error.log -h {$server_name}
                //--hex-blob --routines --skip-lock-tables
                //-u ".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD."
                $res2 = null;

                //https://dev.mysql.com/doc/refman/8.0/en/mysql-config-editor.html
                // use mysql_config_editor to store authentication credentials
                // in an obfuscated login path file named .mylogin.cnf.


                $cmd = escapeshellcmd(HEURIST_DB_MYSQLDUMP);
                if(strpos(HEURIST_DB_MYSQLDUMP,' ')>0){
                    $cmd = '"'.$cmd.'"';
                }

                $port = '';
                if(HEURIST_DB_PORT){
                    $port = " -P ".HEURIST_DB_PORT;
                }

                $cmd = $cmd
                ." -h ".HEURIST_DBSERVER_NAME." ".$port
                ." -u".ADMIN_DBUSERNAME." -p".ADMIN_DBUSERPSWD
                //." --login-path=local
                ." {$options} ".escapeshellarg($database_name_full)
                ." {$tables} > ".$database_dumpfile;

                $arr_out = array();

                exec($cmd, $arr_out, $res2);

                if($res2 !== 0) {

                    $msg = 'mysqldump for '.htmlspecialchars($database_name_full)
                            .' failed with a return status: '.($res2!=null?intval($res2):'unknown')
                            .'. Output: '.(is_array($arr_out)&&!empty($arr_out)?print_r($arr_out, true):'');

                    if($verbose) {echo '<br>'.$msg;}

                    self::$system->addError(HEURIST_SYSTEM_CONFIG, $msg);


                    //echo "Error message was:\n";
                    //$file = escapeshellarg("mysqldump_error.log");
                    //$message = `tail -n 1 $file`;
                    //echo "- $message\n\n";

                    return false;
                }elseif($verbose){
                    echo 'MySQL Dump completed<br>';
                }


            }
            else{ //USE 3d Party php MySQLdump lib

                if(@$dump_options['quick']){ unset($dump_options['quick']);} //not supported
                if(@$dump_options['no-create-db']){ unset($dump_options['no-create-db']);}

                try{
                    $port = '';
                    if(HEURIST_DB_PORT){
                        $port = ';port='.HEURIST_DB_PORT;
                    }
                    $pdo_dsn = 'mysql:host='.HEURIST_DBSERVER_NAME.$port.';dbname='.$database_name_full.';charset=utf8mb4';
                    $dump = new \Mysqldump( $pdo_dsn, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD, $dump_options);

                    $dump->start($database_dumpfile);
                } catch (\Exception $e) {
                    self::$system->addError(HEURIST_SYSTEM_CONFIG, $e->getMessage());
                    return false;
                }

            }

            //$mysqli->close();

            chmod($database_dumpfile, 0750);

            // Echo output
            if($verbose) {
                $size = filesize($database_dumpfile) / pow(1024,2);
                echo "<br>Successfully dumped "
                    .htmlspecialchars($database_name)." to ".htmlspecialchars($database_dumpfile);
                echo "<br>Size of SQL dump: ".htmlspecialchars(sprintf("%.2f", $size))." MB";
            }

            return $database_dumpfile;

        }else{
            $msg = 'Failed to connect to database '.htmlspecialchars($database_name_full);
            self::$system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
            if($verbose) {echo '<br>'.$msg;}
            return false;
        }
    }

    /**
    * Creates new heurist database with file folders, given user and ready to use
    *
    * @param mixed $database_name - target db name
    * @param mixed $user_record   - user that will added as dbowner
    * @param mixed $templateFileName  - text based database definitions (coreDefinitions.txt by default)
    *
    * @returns false or array of warnings
    */
    public static function databaseCreateFull($database_name, &$user_record, $templateFileName=null){

            self::initialize();
            $mysqli = self::$mysqli;
            $system = self::$system;

            if($templateFileName==null){
                $templateFileName = HEURIST_DIR."admin/setup/dbcreate/coreDefinitions.txt";
            }
            $templateFoldersContent = 'NOT DEFINED';//it is used for template database only

            //check template
            if(!file_exists($templateFileName)){
                $system->addError(HEURIST_SYSTEM_CONFIG,
                        'Template database structure file '.$templateFileName.' not found');
                return false;
            }

            list($database_name_full, $database_name) = mysql__get_names( $database_name );

            //checks that database name is valid, correct length and unique
            $error_msg = self::databaseValidateName($database_name, 1);//unique
            if ($error_msg!=null) {
                self::$system->addError(HEURIST_ACTION_BLOCKED, $error_msg);
                return false;
            }

            if(self::setSessionVal(1)) {return false;}

            //create folders
            $upload_root = self::$system->getFileStoreRootFolder();

            $database_folder = $upload_root.$database_name.'/';

            $warnings = self::databaseCreateFolders($database_name);
            if(!isEmptyArray($warnings)){
                folderDelete($database_folder);
                self::$system->addError(HEURIST_ACTION_BLOCKED,
                                            implode("<br>",$warnings));
                return false;
            }

            if(self::setSessionVal(2)) {return false;}

            //create empty database
            if(!self::databaseCreate($database_name_full)){ //with structure and triggers from default dump file
                folderDelete($database_folder);
                return false;
            }

            if(self::setSessionVal(3)) {return false;}

            //switch to new database
            if(!self::importDefinitionsFromTemplate($database_name_full, $templateFileName)){
                //rollback
                folderDelete($database_folder);
                mysql__drop_database( $mysqli, $database_name_full );
                return false;
            }

            //override content of setting folders with template database files - rectype icons, dashboard icons, smarty templates etc
            //not used
            if(file_exists($templateFoldersContent) && filesize($templateFoldersContent)>0){
                $upload_root = $system->getFileStoreRootFolder();

                $unzip_error = null;
                try{
                    UArchive::unzip($system, $templateFoldersContent, $upload_root.$database_name.'/');
                }catch(\Exception $e){
                    array_push($warnings, 'Cannot extract template folders from archive '.$templateFoldersContent
                                //.' Target :'.$upload_root.$database_name
                                .' Error: '.$e->getMessage());
                }
            }

            $warnings2 = self::_databaseInitForNew($user_record);

            if(self::setSessionVal(5)) {return false;}

            $warnings = array_merge($warnings, $warnings2);

            //self::setSessionVal('REMOVE');

            return $warnings;
    }

    /**
     * Imports core definitions from a template file into the specified database.
     *
     * @param string $database_name_full - The full name of the database to import definitions into.
     * @param string $templateFileName - The path to the template file containing the definitions.
     *
     * @return bool - Returns true on success, false on failure.
     */
    private static function importDefinitionsFromTemplate($database_name_full, $templateFileName){

        $mysqli = self::$mysqli;
        $system = self::$system;

        // Switch to the target database
        mysql__usedatabase( $mysqli, $database_name_full );

        if(file_exists($templateFileName) && filesize($templateFileName)>0){

            //import definitions from template file
            $idef = new \ImportDefinitions();
            $idef->initialize( $mysqli );

            if(!$idef->doImport( $templateFileName )) {

                $system->addError(HEURIST_SYSTEM_CONFIG,
                    'Error importing core definitions from '
                    . basename($templateFileName)
                    .' for database '.$database_name_full.'<br>'
                    .'Check whether this file or database is valid.'.CONTACT_HEURIST_TEAM_PLEASE.' if needed');

                return false;
            }

        }
        if(self::setSessionVal(4)) {return false;} //import core defs

        return true;
    }




    /**
    * Updates dbowner, adds default saved searches (for users ##1,2) and lookups (geonames and nakala)
    * it uses current database
    */
    private static function _databaseInitForNew(&$user_record)
    {
            $warnings = array();

            $mysqli = self::$mysqli;

            //update owner user (#2) in new database
            $user_record['ugr_ID'] = 2;
            $user_record['ugr_NavigationTree'] = '"bookmark":{"expanded":true,"key":"root_1","title":"root","children":[{"folder":false,"key":"_1","title":"Recent changes","data":{"url":"?w=bookmark&q=sortby:-m after:\"1 week ago\"&label=Recent changes"}},{"folder":false,"key":"_2","title":"All (date order)","data":{"url":"?w=bookmark&q=sortby:-m&label=All records"}}]},"all":{"expanded":true,"key":"root_2","title":"root","children":[{"folder":false,"key":"_3","title":"Recent changes","data":{"url":"?w=all&q=sortby:-m after:\"1 week ago\"&label=Recent changes"}},{"folder":false,"key":"_4","title":"All (date order)","data":{"url":"?w=all&q=sortby:-m&label=All records"}}]}';
//,{"folder":true,"key":"_5","title":"Rules","children":[{"folder":false,"key":"12","title":"Person > anything they created","data":{"isfaceted":false}},{"folder":false,"key":"13","title":"Organisation > Assoc. places","data":{"isfaceted":false}}]}
            $user_record['ugr_Preferences'] = '';

            $ret = mysql__insertupdate($mysqli, 'sysUGrps', 'ugr', $user_record);
            if($ret!=2){
                array_push($warnings, 'Cannot set owner user. '.$ret);
            }

            //add default saved searches and tree
            $navTree = '{"expanded":true,"key":"root_3","title":"root","children":[{"expanded":true,"folder":true,"key":"_1","title":"Save some filters here ...","children":[]}]}';
//{"key":"28","title":"Organisations","data":{"isfaceted":false}},{"key":"29","title":"Persons","data":{"isfaceted":false}},{"key":"30","title":"Media items","data":{"isfaceted":false}}
            $ret = mysql__insertupdate($mysqli, 'sysUGrps', 'ugr', array('ugr_ID'=>1, 'ugr_NavigationTree'=>$navTree ));
            if($ret!=1){
                array_push($warnings, 'Cannot set navigation tree for group 1. '.$ret);
            }

            //ADD DEFAULT LOOKUPS
            $def_lookups = array();

            $to_replace = array('DB_ID', 'DTY_ID', 'RTY_ID');
            $dty_CCode = 'SELECT dty_ID FROM defDetailTypes INNER JOIN defRecStructure ON rst_DetailTypeID = dty_ID WHERE dty_OriginatingDBID = DB_ID AND dty_IDInOriginatingDB = DTY_ID AND rst_RecTypeID = RTY_ID';

            // GeoNames
            $rty_query = 'SELECT rty_ID FROM defRecTypes WHERE rty_OriginatingDBID = 3 AND rty_IDInOriginatingDB = 1009';
            $rty_id = mysql__select_value($mysqli, $rty_query);
            if(!empty($rty_id)){

                $fld_name = mysql__select_value($mysqli, str_replace($to_replace, array('2', '1', $rty_id), $dty_CCode));
                $fld_name = (empty($fld_name)) ? '' : $fld_name;

                $fld_geo = mysql__select_value($mysqli, str_replace($to_replace, array('2', '28', $rty_id), $dty_CCode));
                $fld_geo = (empty($fld_geo)) ? '' : $fld_geo;

                $fld_cc = mysql__select_value($mysqli, str_replace($to_replace, array('2', '26', $rty_id), $dty_CCode));
                $fld_cc = (empty($fld_cc)) ? '' : $fld_cc;

                $fld_fname = mysql__select_value($mysqli, str_replace($to_replace, array('3', '1068', $rty_id), $dty_CCode));
                $fld_fname = (empty($fld_fname)) ? '' : $fld_fname;

                $fld_id = mysql__select_value($mysqli, str_replace($to_replace, array('2', '581', $rty_id), $dty_CCode));
                $fld_id = (empty($fld_id)) ? '' : $fld_id;

                $key = 'geoName_' . $rty_id;
                $def_lookups[$key] = array('service' => 'geoName', 'rty_ID' => $rty_id, 'label' => 'GeoName', 'dialog' => 'lookupGN', 'fields' => null);
                $def_lookups[$key]['fields'] = array('name' => $fld_name, 'lng' => $fld_geo, 'lat' => $fld_geo, 'countryCode' => $fld_cc, 'adminCode1' => "", 'fclName' => $fld_fname, 'fcodeName' => "", 'geonameId' => $fld_id, 'population' => "");
            }

            // Nakala
            $rty_query = 'SELECT rty_ID FROM defRecTypes WHERE rty_OriginatingDBID = 2 AND rty_IDInOriginatingDB = 5';
            $rty_id = mysql__select_value($mysqli, $rty_query);
            if(!empty($rty_id)){

                $fld_url = mysql__select_value($mysqli, str_replace($to_replace, array('2', '38', $rty_id), $dty_CCode));
                $fld_url = (empty($fld_url)) ? '' : $fld_url;

                $fld_title = mysql__select_value($mysqli, str_replace($to_replace, array('2', '1', $rty_id), $dty_CCode));
                $fld_title = (empty($fld_title)) ? '' : $fld_title;

                $fld_aut = mysql__select_value($mysqli, str_replace($to_replace, array('2', '15', $rty_id), $dty_CCode));
                $fld_aut = (empty($fld_aut)) ? '' : $fld_aut;

                $fld_date = mysql__select_value($mysqli, str_replace($to_replace, array('2', '10', $rty_id), $dty_CCode));
                $fld_date = (empty($fld_date)) ? '' : $fld_date;

                $fld_lic = mysql__select_value($mysqli, str_replace($to_replace, array('1144', '318', $rty_id), $dty_CCode));
                $fld_lic = (empty($fld_lic)) ? '' : $fld_lic;

                $fld_type = mysql__select_value($mysqli, str_replace($to_replace, array('2', '41', $rty_id), $dty_CCode));
                $fld_type = (empty($fld_type)) ? '' : $fld_type;

                $fld_desc = mysql__select_value($mysqli, str_replace($to_replace, array('2', '3', $rty_id), $dty_CCode));
                $fld_desc = (empty($fld_desc)) ? '' : $fld_desc;

                $fld_name = mysql__select_value($mysqli, str_replace($to_replace, array('2', '62', $rty_id), $dty_CCode));
                $fld_name = (empty($fld_name)) ? '' : $fld_name;

                $key = 'nakala_' . $rty_id;
                $def_lookups[$key] = array('service' => 'nakala', 'rty_ID' => $rty_id, 'label' => 'Nakala Lookup', 'dialog' => 'lookupNakala', 'fields' => null);
                $def_lookups[$key]['fields'] = array('url' => $fld_url, 'title' => $fld_title, 'author' => $fld_aut, 'date' => $fld_date, 'license' => $fld_lic, 'mime_type' => $fld_type, 'abstract' => $fld_desc, 'rec_url' => '', 'filename' => $fld_name);
            }

            if(!empty($def_lookups)){

                $lookup_str = json_encode($def_lookups);
                $upd_query = "UPDATE sysIdentification SET sys_ExternalReferenceLookups = ? WHERE sys_ID = 1";
                mysql__exec_param_query($mysqli, $upd_query, array('s', $lookup_str));
            }else{
                array_push($warnings, 'Unable to setup default lookup services.');
            }

            return  $warnings;

    }


    /**
    * Verifies that database name is valid and optionally that database exists or unique
    *
    * @param mixed $database_name
    * @param mixed $check_exist_or_unique - 1 must be unique, 2 - must exist, 0 - skip this check
    */
    public static function databaseValidateName($database_name, $check_exist_or_unique=1){

        list($database_name_full, $database_name) = mysql__get_names( $database_name );

        $error_msg = mysql__check_dbname($database_name_full);

        if ($check_exist_or_unique>0 && $error_msg==null) {

            if($check_exist_or_unique==1 &&
               (strcasecmp($database_name,'DELETED_DATABASES')==0 ||
                strcasecmp($database_name,'DBS_TO_RESTORE')==0 ||
                strcasecmp($database_name,'AAA_LOGS')==0)){

                $error_msg = 'Database name '.htmlspecialchars($database_name).' is reserved. Try different name.';

            }else{
                //verify that database with such name already exists
                $dblist = mysql__select_list2(self::$mysqli, 'show databases');
                if (array_search(strtolower($database_name_full), array_map('strtolower', $dblist)) !== false ){
                    if($check_exist_or_unique==1){
                        $error_msg = 'Database with name '.htmlspecialchars($database_name_full).' aready exists. Try different name.';
                    }
                }elseif($check_exist_or_unique==2){
                        $error_msg = 'Database with name '.htmlspecialchars($database_name_full).' does not exists.';
                }
            }
        }

        return $error_msg;
    }

    /**
    * Restores database from archive
    *
    * @param mixed $database_name - name of target dastabase
    * @param mixed $archive_file - name of zip file
    * @param mixed $archive_folder - id of source folder (DATABASE_DELETED by default)
    */
    public static function databaseRestoreFromArchive($database_name, $archive_file, $archive_folder=1){

        self::initialize();

        $upload_root = self::$system->getFileStoreRootFolder();

        //only from limited list of folders
        $source = intval($archive_folder);
        if($source==2){
            $lib_path = '/srv/BACKUP/';
        }elseif($source==3){
            if(strpos(HEURIST_BASE_URL, '://127.0.0.1')>0){
                $lib_path = HEURIST_FILESTORE_ROOT.'BACKUP/ARCHIVE/';
            }else{
                $lib_path = '/srv/BACKUP/ARCHIVE/';
            }
        }elseif($source==4){
            $lib_path = $upload_root.'DBS_TO_RESTORE/';
        }else{
            //default
            $lib_path = $upload_root.'DELETED_DATABASES/';
        }

        $archive_file = $lib_path.basename($archive_file);

        //check archive
        if(!file_exists($archive_file)){
            self::$system->addError(HEURIST_ACTION_BLOCKED, 'Database archive file not found');
            return false;
        }

        list($database_name_full, $database_name) = mysql__get_names( $database_name );

        //check database name and unique
        $error_msg = self::databaseValidateName($database_name, 1);//unique
        if ($error_msg!=null) {
            self::$system->addError(HEURIST_ACTION_BLOCKED, $error_msg);
            return false;
        }

        self::setSessionVal(1);//database name and archive validated

        //create folders and all subfolders with default content
        $database_folder = $upload_root.$database_name.'/';

        $warnings = self::databaseCreateFolders($database_name);
        if(!isEmptyArray($warnings)){
            folderDelete($database_folder);
            self::$system->addError(HEURIST_ACTION_BLOCKED,
                                    implode('<br>',$warnings));
            return false;
        }

        self::setSessionVal(2);//folders created

        $needCopyCurrentDbFolder = false;
        //unpack archive into this folder
        $unzip_error = null;
        try{
            $path_parts = pathinfo($archive_file);
            $ext = 'zip';
            if(array_key_exists('extension', $path_parts)){
                $ext = $path_parts['extension'];
            }

            if(strcasecmp($ext, 'bz2')==0 || strpos($archive_file,'.sql.bz2.')>0){
                if(extension_loaded('bz2')){
                    $needCopyCurrentDbFolder = true;
                    UArchive::bunzip2($archive_file, $database_folder.'dump.sql');
                }else{
                    throw new \Exception('bz2 extension is not detected');
                }
            }else{
                $fileCount = UArchive::unzip(self::$system, $archive_file, $database_folder);
                $needCopyCurrentDbFolder = ($fileCount==1);
            }

        }catch(\Exception $e){
            folderDelete($database_folder);
            self::$system->addError(HEURIST_ACTION_BLOCKED, 'Cannot unpack database archive. '
                            .' Error: '.$e->getMessage());
            return false;
        }

        self::setSessionVal(3);//unpack archive

        //find dump file
        $dumpfile = folderFirstFile($database_folder, 'sql', false);

        //create database and import data from dumpfile
        if(!file_exists($dumpfile)){

            folderDelete($database_folder);
            self::$system->addError(HEURIST_ACTION_BLOCKED, 'Archive does not contain sql dump file');
            return false;

        }else{

            $dumpfile_from_archive = $database_folder.basename($dumpfile);
            $filecontent = file_get_contents($dumpfile_from_archive);

            //$subs = folderGetSubFolders($database_folder);
            if($needCopyCurrentDbFolder){
                //archive does not contain any file but database dump
                //copy folders from current database

                if(folderRecurseCopy( HEURIST_FILESTORE_DIR, $database_folder )){
                    self::databaseUpdateFilePaths(self::$system->dbname() , $database_name);
                }else{
                    folderDelete($database_folder);
                    self::$system->addError(HEURIST_ACTION_BLOCKED,
                        'Sorry, we were not able to copy file directories for restoring database.');
                    return false;
                }
            }

            fileDelete($dumpfile_from_archive);//remove temp dump file
            $dumpfile = $database_folder.'_temp_dump.sql';
            file_put_contents($dumpfile, preg_replace('/DEFINER=`\w+`@`[\w.]+`/m', 'DEFINER=CURRENT_USER', $filecontent));

            $script_file = basename($dumpfile);
            //$script_file = HEURIST_DIR.'admin/setup/dbcreate/'.$script_file;
            //fileCopy($dumpfile, $script_file);

            $res = self::databaseCreate($database_name, 1, $script_file);//from archive

            self::setSessionVal(4);//database restored from dump

            fileDelete($dumpfile);//remove temp dump file

            if(!$res){
                folderDelete($database_folder);
            }else{
                $path = realpath(dirname(__FILE__).'/../../../');
                $now = getNow();
                fileAdd($database_name.' # restore '.$now->format('Y-m-d'),
                            $path.'/databases_not_to_purge.txt');
            }

            return $res;
        }
    }

    /**
    * Creates new heurist database
    *
    * @param mixed $database_name
    * @param mixed $level - 0 empty db, 1 +structure, 2 +constraints and triggers
    * @param mixed $dumpfile - database dump file to restore if $level>0
    */
    public static function databaseCreate($database_name, $level=2, $dumpfile=null){

        self::initialize();

        list($database_name_full, $database_name) = mysql__get_names( $database_name );

        $error_msg = self::databaseValidateName($database_name, 1);//unique
        if ($error_msg!=null) {
            self::$system->addError(HEURIST_ACTION_BLOCKED, $error_msg);
            return false;
        }

        $database_folder = null;

        if($dumpfile==null){
            $dumpfile = 'blankDBStructure.sql';
        }else{
            $dumpfile = basename($dumpfile);

            $upload_root = self::$system->getFileStoreRootFolder();
            $database_folder = $upload_root.$database_name.'/';

            //remove COLLATE= for huma-num
            if(defined('HEURIST_SERVER_NAME') && HEURIST_SERVER_NAME=='heurist.huma-num.fr'){
                $dump_name_full = $database_folder.$dumpfile;
                $cmd = "sed -i 's/ COLLATE=utf8mb4_0900_ai_ci//g' ".escapeshellarg($dump_name_full);
                exec($cmd, $arr_out, $res2);

                if ($res2 != 0 ) {
                    $error = 'Error: '.print_r($res2, true);
                    error_log($error);
                }
            }
        }

        $mysqli = self::$mysqli;

        $res = mysql__create_database($mysqli, $database_name_full);

        if (is_array($res)){
            self::$system->addErrorArr($res);//can't create
            mysql__drop_database($mysqli, $database_name_full);
            return false;
        }elseif($level<1){
            return true; //create empty database
        }

        //restore data from sql dump
        $res = mysql__script($database_name_full, $dumpfile, $database_folder);//restore from dump
        if($res!==true){
            $res[1] = 'Cannot create database tables. '.$res[1];
            self::$system->addErrorArr($res);
        }elseif($level<2){
            return true;  //without constraints and triggers
        }elseif(self::databaseCreateConstraintsAndTriggers($database_name_full)){
            return true;
        }
//----------------------

        if (is_array($res)){
            self::$system->addErrorArr($res);//can't create
            mysql__drop_database($mysqli, $database_name_full);
            return false;
        }elseif($level<1){
            return true; //create empty database
        }

        //restore data from sql dump
        $res = mysql__script($database_name_full, $dumpfile, $database_folder);//restore from dump
        if($res!==true){
            $res[1] = 'Cannot create database tables. '.$res[1];
            self::$system->addErrorArr($res);
        }elseif($level<2){
            return true;  //without constraints and triggers
        }elseif(self::databaseCreateConstraintsAndTriggers($database_name_full)){
            return true;
        }

        //fails
        mysql__drop_database($mysqli, $database_name_full);
        return false;
    }


    /**
    * Recreates constraints and triggers (executes sql commands from files)
    *
    * @param mixed $database_name
    * @return {false|true}
    */
    public static function databaseCreateConstraintsAndTriggers($database_name){

        self::initialize();
        list($database_name_full, $database_name) = mysql__get_names( $database_name );

        $mysqli = self::$mysqli;

        $res = mysql__script($database_name_full, 'addReferentialConstraints.sql');
        if($res===true){
            $res = mysql__script($database_name_full, 'addProceduresTriggers.sql');
        }

        if($res!==true){
            self::$system->addErrorArr($res);
            $res = false;
        }

        return $res;

    }

    /**
    * Creates if not exists set of folders for given database
    *
    * @param mixed $database_name
    */
    public static function databaseCreateFolders($database_name){

        list($database_name_full, $database_name) = mysql__get_names( $database_name );

        $upload_root = self::$system->getFileStoreRootFolder();

        // Create a default upload directory for uploaded files eg multimedia, images etc.
        $database_folder = $upload_root.$database_name.'/';

        if (folderCreate($database_folder, true)){
            folderAddIndexHTML( $database_folder );//add index file to block directory browsing
        }else{
            return array('Heurist was unable to create the required database root folder,<br>Database name: '
                        . $database_name . '<br>Server url: ' . HEURIST_BASE_URL);//, 'revert'=>true
        }

        $warnings = array();

        if(folderRecurseCopy( HEURIST_DIR."admin/setup/dbcreate/icons", $database_folder."entity" )){

            folderAddIndexHTML($database_folder."entity");// index file to block directory browsing
        }else{
            $warnings[] = "Unable to create/copy entity folder (icons) to $database_folder";
        }



        if(folderRecurseCopy( HEURIST_DIR."admin/setup/dbcreate/smarty-templates", $database_folder."smarty-templates" )){

            folderAddIndexHTML($database_folder."smarty-templates");// index file to block directory browsing
        }else{
            $warnings[] = "Unable to create/copy smarty-templates folder to $database_folder";
        }
/* removed 2021-07-15
        if(folderRecurseCopy( HEURIST_DIR."admin/setup/xsl-templates", $database_folder."xsl-templates" )){

            folderAddIndexHTML($database_folder."xsl-templates");// index file to block directory browsing
        }else{
            $warnings[] = "Unable to create/copy xsl-templates folder to $database_folder";
        }
*/
    //since 2023-06-02 documentation_and_templates is not created
    /*
    if(false){
        if(folderRecurseCopy( HEURIST_DIR."documentation_and_templates", $database_folder."documentation_and_templates" )){

            folderAddIndexHTML($database_folder."documentation_and_templates");// index file to block directory browsing
        }else{
            $warnings[] = "Unable to create/copy documentation folder to $database_folder";
        }
    }
    */

        // Create all the other standard folders required for the database
        // index.html files are added by createFolder to block index browsing
        $warnings[] = folderCreate2($database_folder. '/filethumbs', 'used to store thumbnails for uploaded files', true);
        $warnings[] = folderCreate2($database_folder. '/file_uploads','used to store uploaded files by default');
        $warnings[] = folderCreate2($database_folder. '/scratch', 'used to store temporary files');
        $warnings[] = folderCreate2($database_folder. '/hml-output', 'used to write published records as hml files', true);
        $warnings[] = folderCreate2($database_folder. '/html-output', 'used to write published records as generic html files', true);
        $warnings[] = folderCreate2($database_folder. '/generated-reports', 'used to write generated reports');
        $warnings[] = folderCreate2($database_folder. '/backup', 'used to write files for user data dump');

        //remove empty warns
        $warnings = array_filter($warnings, function($value) { return $value !== '';});
        if(!empty($warnings)){
            array_unshift($warnings, "Unable to create the sub directories within the database root directory,<br>Database name: "
                        . $database_name . ",<br><br>Server url: " . HEURIST_BASE_URL . ",<br>Warnings:\n");
        }

        return $warnings;
    }

    /**
    * Clears data (delete all records) for given table
    *
    * @param mixed $name
    * @param mixed $remark - session message
    * @param mixed $verbose
    */
    private static function _emptyTable($name, $remark, $verbose){

        $mysqli = self::$mysqli;

        if($verbose){ echo "Deleting ".htmlspecialchars($remark)."</br>";}

        self::setSessionVal($remark);

        if(!$mysqli->query("delete from $name where 1")){

            $error_msg = 'Unable to clean '.htmlspecialchars($remark);

            self::$system->addError(HEURIST_ACTION_BLOCKED, $error_msg, $mysqli->error);
            if($verbose) {
                echo "<br><p>Warning: $error_msg - SQL error: {$mysqli->error}</p>";
            }
            return false;
        }else{
            return true;
        }
    }

    /**
    * Clears data tables (retains defintions)
    *
    * @param mixed $database_name - target database
    * @param mixed $verbose
    * @return {false|mysqli_result|true}
    */
    public static function databaseEmpty($database_name, $verbose=true){

        self::initialize();
        $mysqli = self::$mysqli;
        $system  = self::$system;

        $error_msg = self::databaseValidateName($database_name, 2);//exists
        if ($error_msg!=null) {
            $system->addError(HEURIST_ACTION_BLOCKED, $error_msg);
            return false;
        }

        list($database_name_full, $database_name) = mysql__get_names( $database_name );

        $res = true;

        if($database_name!=HEURIST_DBNAME){ //switch to database
           $connected = (mysql__usedatabase($mysqli, $database_name_full)==true);
        }else{
           $connected = true;
        }
        if(!$connected){
            $msg = 'Failed to connect to database '.htmlspecialchars($database_name);
            $system->addError(HEURIST_DB_ERROR, $msg, $mysqli->error);
            if($verbose) {echo '<br><p>'.$msg.'</p>';}
            return false;
        }

        self::setSessionVal('Permission validation');

        $keep_autocommit = mysql__select_value($mysqli, 'SELECT @@autocommit');
        if($keep_autocommit===true) {$mysqli->autocommit(false);}

        if(!$mysqli->query("update recThreadedComments set cmt_ParentCmtID = NULL where cmt_ID>0")){
            //not used
            $system->addError(HEURIST_ACTION_BLOCKED, 'Unable to set parent IDs to null for Comments');
            $res = false;
            if($verbose) {
                echo "<br><p>Warning: Unable to set parent IDs to null for Comments - SQL error: {$mysqli->error}</p>";
            }
        }

        if($res){

            $tables = array(
                "recThreadedComments" => "Comments",
                "recForwarding" => "Forwarding",
                "recLinks" => "Relationships Cache",
                "recSimilarButNotDupes" => "List of Similar Records",
                "usrRecTagLinks" => "Tag Links",
                "usrReminders" => "Reminders",
                "usrRemindersBlockList" => "Reminders Block List",
                "usrRecPermissions" => "Permissions",
                "recDetails" => "Details",
                "usrBookmarks" => "Bookmarks",
                "Records" => "Records"
            );

            foreach ($tables as $name => $remark) {
                if(! self::_emptyTable($name, $remark, $verbose)){
                    $res = false;
                    break;
                }
            }

            if($res){
                $res2 = $mysqli->query("ALTER TABLE Records AUTO_INCREMENT = 0");
                if($res2) {$mysqli->commit();}
            }
        }

        if(!$res){
            $mysqli->rollback();
        }

        if($keep_autocommit===true) {$mysqli->autocommit(true);}
        //$mysqli->close();

        return $res;
    }

    /**
    * Copy all tables (except csv import cache) from one db to another
    * It is assumed that all tables exist and empty in target db
    *
    * source and target database must exist
    *
    * $isCloneTemplate - true for clone curated database
    *
    * @param mixed $db_source - full name (with hdb_) for source database
    * @param mixed $db_target - must exist as new empty heurist database created by  databaseCreate($db_target, 1)
    * @param mixed $verbose
    *
    * @todo make private and rename to databaseCopyTables
    */
    public static function databaseClone($db_source, $db_target, $verbose, $nodata=false, $isCloneTemplate=false){

        self::initialize();

        $res = true;
        $mysqli = self::$mysqli;
        $message = null;

        $db_source = $mysqli->real_escape_string($db_source);
        $db_target = $mysqli->real_escape_string($db_target);

        if( mysql__usedatabase($mysqli, $db_source)!==true ){
            $message = 'Could not open source database '.$db_source;
            $res = false;
            if($verbose) {
                $message = '<br><p>Warning: '.$message.'</p>';
            }
        }else{

            if( mysql__usedatabase($mysqli, $db_target)!==true ){
                $message = 'Could not open target database '.$db_target;
                $res = false;
                if($verbose) {
                    $message = '<br><p>Warning: '.$message.'</p>';
                }
            }
        }

        if($res){

                // Remove initial values from empty target database
                $mysqli->query('delete from sysIdentification where 1');

                if(!$isCloneTemplate){
                    $mysqli->query('delete from sysUsrGrpLinks where 1');
                    $mysqli->query('delete from sysUGrps where ugr_ID>=0');
                }

                //$isCloneTemplate
                $exception_for_clone_template = array('sysugrps','sysusrgrplinks',
                'woot_chunkpermissions','woot_chunks','woot_recpermissions','woots',
                'usrworkingsubsets', //'usrrecpermissions',
                'usrreminders','usrremindersblocklist','recthreadedcomments','usrreportschedule','usrhyperlinkfilters', 'sysarchive');

                $data_tables = array('records','recdetails','reclinks','recdetailsdateindex',
                'recsimilarbutnotdupes','recthreadedcomments','recuploadedfiles','usrbookmarks','usrrectaglinks',
                'usrrecpermissions','usrworkingsubsets',
                'usrreminders','usrremindersblocklist','woot_chunkpermissions','woot_chunks','woot_recpermissions','woots', 'sysarchive');


                $tables = $mysqli->query("SHOW TABLES");//get all tables from target db
                if($tables){

                    mysql__foreign_check( $mysqli, false );
                    $mysqli->query("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");

                    if($verbose) {
                        echo "<b>Adding records to tables: </b>";
                    }
                    while ($table = $tables->fetch_row()) { //loop for all tables
                        $table = $table[0];

                        if($nodata && in_array(strtolower($table), $data_tables)){
                            continue;
                        }
                        if($isCloneTemplate &&  in_array(strtolower($table), $exception_for_clone_template)){
                            continue;
                        }

                        if(strtolower($table)=='usrrecpermissions'){
                            $cnt = mysql__select_value($mysqli,'select count(*) from usrRecPermissions');
                            if(!($cnt>0)) {continue;}
                        }elseif($table=='sysUGrps'){
                            $cnt = mysql__select_value($mysqli, "SELECT count(*) FROM `". $db_source ."`.sysUGrps WHERE ugr_Enabled != 'n' AND ugr_Enabled != 'y'");

                            if(is_numeric($cnt) && $cnt > 0){
                                checkUserStatusColumn(self::$system, $db_target);
                            }
                        }

                        $mysqli->query("ALTER TABLE `".$table."` DISABLE KEYS");
                        $res = $mysqli->query("INSERT INTO `".$table."` SELECT * FROM `".$db_source."`.`".$table."`"  );

                        if($res){
                                if($verbose) {
                                    echo " > " . htmlspecialchars($table) . ": ".intval($mysqli->affected_rows) . "  ";
                                }
                        }else{
                                if($table=='usrReportSchedule'){
                                    if($verbose) {
                                        echo "<br><p class=\"error\">Warning: Unable to add records into ".htmlspecialchars($table)." - SQL error: {$mysqli->error}</p>";
                                    }
                                }else{
                                    $message = "Unable to add records into $table - SQL error: ".$mysqli->error;
                                    if($verbose) {
                                        $message = "<br><p class=\"error\">Error: $message</p>";
                                    }
                                    $res = false;
                                    break;
                                }
                        }

                        if($table=='recForwarding'){ //remove missed records otherwise we get exception on constraint addition
                            $mysqli->query('DELETE FROM recForwarding where rfw_NewRecID not  in (select rec_ID from Records)');
                        }

                        $mysqli->query("ALTER TABLE `".$table."` ENABLE KEYS");
                    }//while

                    if($isCloneTemplate){
                        //change ownership OR remove entries for all users and groups but 0~3
                        $mysqli->query('delete FROM usrRecTagLinks,usrTags WHERE rtl_TagID=tag_ID AND tag_UGrpID NOT IN (0,1,2,3)');
                        $mysqli->query('delete FROM usrTags WHERE tag_UGrpID NOT IN (0,1,2,3)');

                        $mysqli->query('delete FROM usrBookmarks WHERE bkm_UGrpID NOT IN (0,1,2,3)');
                        $mysqli->query('delete FROM usrSavedSearches WHERE svs_UGrpID NOT IN (0,1,2,3)');

                        $mysqli->query('update Records set rec_AddedByUGrpID=2 WHERE rec_AddedByUGrpID NOT IN (0,1,2,3)');
                        $mysqli->query('update Records set rec_OwnerUGrpID=2 WHERE rec_OwnerUGrpID NOT IN (0,1,2,3)');
                        $mysqli->query('update recUploadedFiles set ulf_UploaderUGrpID=2 WHERE ulf_UploaderUGrpID NOT IN (0,1,2,3)');
                    }

                    mysql__foreign_check( $mysqli, true );//restore/enable foreign indexes verification

                    //cleanup target database to avoid issues with addition of constraints

                    //1. cleanup missed trm_InverseTermID
                    $mysqli->query('update defTerms t1 left join defTerms t2 on t1.trm_InverseTermID=t2.trm_ID
                        set t1.trm_InverseTermID=null
                    where t1.trm_ID>0 and t2.trm_ID is NULL');

                    //3. remove missed rl_SourceID and rl_TargetID
                    $mysqli->query('delete FROM recLinks
                        where rl_SourceID is not null
                    and rl_SourceID not in (select rec_ID from Records)');

                    $mysqli->query('delete FROM recLinks
                        where rl_TargetID is not null
                    and rl_TargetID not in (select rec_ID from Records)');

                    //4. cleanup orphaned details
                    $mysqli->query('delete FROM recDetails
                        where dtl_RecID is not null
                    and dtl_RecID not in (select rec_ID from Records)');

                    //5. cleanup missed references to uploaded files
                    $mysqli->query('delete FROM recDetails
                        where dtl_UploadedFileID is not null
                    and dtl_UploadedFileID not in (select ulf_ID from recUploadedFiles)');

                    //6. cleanup missed rec tags links
                    $mysqli->query('delete FROM usrRecTagLinks where rtl_TagID not in (select tag_ID from usrTags)');
                    $mysqli->query('delete FROM usrRecTagLinks where rtl_RecID not in (select rec_ID from Records)');

                    //7. cleanup orphaned bookmarks
                    $mysqli->query('delete FROM usrBookmarks where bkm_RecID not in (select rec_ID from Records)');

                }else{
                    $res = false;
                    $message = 'Cannot get list of table in database '.$db_target;
                    if($verbose) {
                        echo '<br><p class=\"error\">Error: '.htmlspecialchars($message).'</p>';
                    }
                }

            }

        if(!$res){
            if($verbose) {
                if($message) {echo htmlspecialchars($message);}
            }else{
                self::$system->addError(HEURIST_ERROR, $message);
            }
        }

        return $res;
    }

    /**
    * Clones database including folders
    *
    * @param $db_source - source database by default current one (HEURIST_DBNAME)
    * @param mixed $db_target - target database
    * @param mixed $nodata - if true only defintions will be clone (no Records)
    * @param false $isCloneTemplate - clone from curated registered datbase -NOT USED ANYMORE
    *           db onwer will be changed to current user
    */
    public static function databaseCloneFull($db_source, $db_target, $nodata=false, $isCloneTemplate=false)
    {
        global $passwordForServerFunctions;

        self::initialize();

        if($db_source==null){
            $db_source = HEURIST_DBNAME;
        }

        //$system = self::$system;
        $mysqli = self::$mysqli;
        $ugr_ID = self::$system->get_user_id();//current user
        $usr_owner = user_getById($mysqli, $ugr_ID);


        list($db_source_full, $db_source ) = mysql__get_names($db_source);
        list($db_target_full, $db_target ) = mysql__get_names($db_target);


        $sErrorMsg = DbUtils::databaseValidateName($db_target, 1);//unique
        if ($sErrorMsg!=null) {
            self::$system->addError(HEURIST_ACTION_BLOCKED, $sErrorMsg);
            return false;
        }

        //additional check for self clone/rename
        if($db_source==HEURIST_DBNAME && !self::$system->is_admin()){

                self::$system->addError(HEURIST_REQUEST_DENIED,
                            'To perform this action you must be logged in as Administrator of group \'Database Managers\' or as Database Owner');
                return false;
        }

        if(self::setSessionVal(1)) {return false;} //validation

        //create folders
        $upload_root = self::$system->getFileStoreRootFolder();
        $database_folder = $upload_root.$db_target.'/';

        //2. Copy folders
        //copy files and folder
        if($nodata){
            //limited set of folders
            $warnings = self::databaseCreateFolders($db_target);
            if(!isEmptyArray($warnings)){
                folderDelete($database_folder);
                self::$system->addError(HEURIST_ACTION_BLOCKED,
                    'Sorry, we were not able to create all file directories required by the database. '
                                                .implode("<br>",$warnings));
                return false;
            }

            folderRecurseCopy( HEURIST_FILESTORE_ROOT.$db_source."/smarty-templates",
                        HEURIST_FILESTORE_ROOT.$db_target."/smarty-templates" );
            folderRecurseCopy( HEURIST_FILESTORE_ROOT.$db_source."/xsl-templates",
                        HEURIST_FILESTORE_ROOT.$db_target."/xsl-templates" );
            folderRecurseCopy( HEURIST_FILESTORE_ROOT.$db_source."/entity",
                        HEURIST_FILESTORE_ROOT.$db_target."/entity" );

        }elseif(!folderRecurseCopy( HEURIST_FILESTORE_ROOT.$db_source, HEURIST_FILESTORE_ROOT.$db_target )){
                folderDelete($database_folder);
                self::$system->addError(HEURIST_ACTION_BLOCKED,
                    'Sorry, we were not able to copy file directories for cloning  database.');
                return false;
        }
        if(self::setSessionVal(2)) {return false;} //copy folders

        //3. create target database
        $res = DbUtils::databaseCreate($db_target, 1);

        if(!$res){
            folderDelete($database_folder);
            return false;
        }

        if(self::setSessionVal(3)) {return false;} //database creation

        //4. copy tables  - it switches to target db
        $res = DbUtils::databaseClone($db_source_full, $db_target_full, false, $nodata, $isCloneTemplate);

        if(!$res){
            DbUtils::databaseDrop( false, $db_target, false);
            return false;
        }

        if(self::setSessionVal(4)) {return false;} //copy data

        if($isCloneTemplate){
        //5. add current user from current database as owner to target cloned db
            $usr_owner['ugr_ID'] = 2;
            unset($usr_owner['ugr_NavigationTree']);
            $ret = mysql__insertupdate($mysqli, 'sysUGrps', 'ugr', $usr_owner);
            if($ret!=2){
                DbUtils::databaseDrop( false, $db_target, false);
                self::$system->addError(HEURIST_ACTION_BLOCKED,
                                'Cannot set owner user. '.$ret);
                return false;
            }
        }

        //6. add constraints
        if(!DbUtils::databaseCreateConstraintsAndTriggers($db_target)){
            DbUtils::databaseDrop( false, $db_target, false);
            return false;
        }

        if(self::setSessionVal(5)) {return false;} //triggers and constraints

        // 7. Update file path in target database  with absolute paths
        self::databaseUpdateFilePaths($db_source, $db_target);

        if(self::setSessionVal(6)) {return false;} //triggers and constraints

        return true;
    }

    /**
    * Update ulf_FilePath in recUploadedFiles for new database
    *
    * @param mixed $db_source
    * @param mixed $db_target
    */
    private static function databaseUpdateFilePaths($db_source, $db_target)
    {
        $mysqli = self::$mysqli;
        $query1 = "update recUploadedFiles set ulf_FilePath='".HEURIST_FILESTORE_ROOT.$db_target.
        "/' where ulf_FilePath='".HEURIST_FILESTORE_ROOT.$db_source."/' and ulf_ID>0";
        $res1 = $mysqli->query($query1);
        if ($mysqli->error)  { //(mysql_num_rows($res1) == 0)
//@todo
//        print "<p><h4>Warning</h4><b>Unable to set database files path to new path</b>".
//        "<br>Query was:".htmlspecialchars($query1).
//        "<br>Please get your system administrator to fix this problem BEFORE editing the database (your edits will affect the original database)</p>";

        }
    }


    /**
    * Removes registration info and assign originID for definitions
    * (after clone)
    *
    * @param mixed $dbname
    */
    public static function databaseResetRegistration($dbname){

        self::initialize();

        mysql__usedatabase(self::$mysqli, $dbname);

        //get current reg id
        $sourceRegID = mysql__select_value(self::$mysqli, 'select sys_dbRegisteredID from sysIdentification where 1');

        //reset reg id and some other values in sysIdentification
        $query1 = "update sysIdentification set sys_dbRegisteredID=0, sys_hmlOutputDirectory=null, "
            ."sys_htmlOutputDirectory=null, sys_SyncDefsWithDB=null, sys_MediaFolders='uploaded_files', "
            ."sys_eMailImapProtocol='', sys_eMailImapUsername='', sys_dbRights='', sys_NewRecOwnerGrpID=0 where 1";

        $res1 = self::$mysqli->query($query1);
        if($sourceRegID>0){
            self::updateOriginatingDB($sourceRegID);
        }
    }

    public static function databaseUpdateRegistration($dbname, $reg_record){

        self::initialize();
        $res = mysql__usedatabase(self::$mysqli, $dbname);
        if($res===true){

            $dbID = intval(@$reg_record['dbID']);
            $dbDescription = @$reg_record['dbTitle'];

            if($dbID>0){
                //update concept codes
                self::updateOriginatingDB( $dbID );

                //update sysIndentificatons
                $upd_query = 'update sysIdentification set `sys_dbRegisteredID`=?, `sys_dbDescription`=? where 1';
                mysql__exec_param_query(self::$mysqli, $upd_query, array('is', $dbID, $dbDescription));
            }
        }

    }



    /**
    * Renames database (in fact it clones database with new name and archive/drop old database)
    *
    * @param mixed $db_source
    * @param mixed $db_target
    * @param mixed $createArchive - if true creates archive of old db before dropping
    * @return true on success
    */
    public static function databaseRename($db_source, $db_target, $createArchive=false){

        //copy all data to new database
        $res = DbUtils::databaseCloneFull($db_source, $db_target, false, false);
        //drop/archive previous database
        if($res){
            $mysqli = self::$mysqli;
            //update registration
            $rec = mysql__select_row_assoc($mysqli, 'select sys_dbRegisteredID, sys_dbDescription from sysIdentification where 1');
            $regID = intval($rec['sys_dbRegisteredID']);
            if($regID>0)
            {
                $dbTitle = $rec['sys_dbDescription'];
                $dbowner = user_getDbOwner($mysqli);
                $serverURL = HEURIST_SERVER_URL . HEURIST_DEF_DIR . "?db=" . $db_target;
                $params = array(
                    'action'=>'update',
                    'dbID'=>$regID,
                    'dbReg'=>$db_target, //new name
                    'dbTitle'=>$dbTitle,
                    'usrPassword'=>$dbowner['ugr_Password'],
                    'usrEmail'=>$dbowner['ugr_eMail'],
                    'serverURL'=>$serverURL //new url
                );
                $res2 = DbRegis::registrationUpdate($params);
                // if not integer - this is error
                if(is_bool($res2) && $res2===false){
                    self::$system->addErrorMsg(
                        'Failed to update reference index for #'.$regID.' for renamed database '.$db_target.'<br>');
                }
            }

            //archive and drop database with old name
            self::$progress_step = 6;
            DbUtils::databaseDrop(false, $db_source, $createArchive);
        }

        return $res;
    }



    /**
    * Assigns given Origin ID for rectype, detail and term defintions
    *
    * @param mixed $dbID  - database registration id
    * @return {false|true}
    */
    public static function updateOriginatingDB($dbID){

        self::initialize();

        $res = true;

        if($dbID>0){
            $dbID = intval($dbID);
            $mysqli = self::$mysqli;
            $result = 0;
            $res2 = $mysqli->query("update defRecTypes set "
                ."rty_OriginatingDBID='$dbID',rty_NameInOriginatingDB=rty_Name,rty_IDInOriginatingDB=rty_ID "
                ."where (rty_OriginatingDBID = '0') OR (rty_OriginatingDBID IS NULL) ");
            if ($res2===false) {$result = 1; }
            // Fields
            $res2 = $mysqli->query("update defDetailTypes set "
                ."dty_OriginatingDBID='$dbID',dty_NameInOriginatingDB=dty_Name,dty_IDInOriginatingDB=dty_ID "
                ."where (dty_OriginatingDBID = '0') OR (dty_OriginatingDBID IS NULL) ");
            if ($res2===false) {$result = 1; }
            // Terms
            $res2 = $mysqli->query("update defTerms set "
                ."trm_OriginatingDBID='$dbID',trm_NameInOriginatingDB=trm_Label, trm_IDInOriginatingDB=trm_ID "
                ."where (trm_OriginatingDBID = '0') OR (trm_OriginatingDBID IS NULL) ");
            if ($res2===false) {$result = 1; }


            if ($result == 1){
                self::$system->addError(HEURIST_DB_ERROR,
                            'Error on update IDs "IDInOriginatingDB" fields for database registration '.$dbID, $mysqli->error);
                $res = false;
            }
        }
        return $res;
    }

    /**
    * Assigns Origin ID for rectype, detail and term defintions if they equal 9999
    * (after import from unregistered database)
    */
    public static function updateImportedOriginatingDB(){

        self::initialize();

        $res = true;

        $dbID = 0;
        if(defined('HEURIST_DBID')){
            $dbID = HEURIST_DBID;
        }

        $mysqli = self::$mysqli;
        $result = 0;
        $res2 = $mysqli->query("update defRecTypes set "
            ."rty_OriginatingDBID='$dbID',rty_NameInOriginatingDB=rty_Name,rty_IDInOriginatingDB=rty_ID "
            ."where (rty_OriginatingDBID = '9999')");
        if ($res2===false) {$result = 1; }
        // Fields
        $res2 = $mysqli->query("update defDetailTypes set "
            ."dty_OriginatingDBID='$dbID',dty_NameInOriginatingDB=dty_Name,dty_IDInOriginatingDB=dty_ID "
            ."where (dty_OriginatingDBID = '9999')");
        if ($res2===false) {$result = 1; }
        // Terms
        $res2 = $mysqli->query("update defTerms set "
            ."trm_OriginatingDBID='$dbID',trm_NameInOriginatingDB=trm_Label, trm_IDInOriginatingDB=trm_ID "
            ."where (trm_OriginatingDBID = '9999')");
        if ($res2===false) {$result = 1; }


        if ($result == 1){
            self::$system->addError(HEURIST_DB_ERROR,
                        'Error on update IDs "IDInOriginatingDB" fields for unregistered (imported) definitions '.$dbID, $mysqli->error);
            $res = false;
        }

        return $res;
    }


}


?>