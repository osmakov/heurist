<?php
namespace hserv;
use hserv\structure\ConceptCode;
use hserv\utilities\USystem;
use hserv\utilities\USanitize;

/**
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

//declare(strict_types=1);

require_once dirname(__FILE__).'/structure/dbsUsersGroups.php';
require_once dirname(__FILE__).'/structure/import/dbsImport.php';

set_error_handler('boot_error_handler');

/**
*  Class that contains mysqli (dbconnection), current user and system settings
*
*  it performs system initialization:
*   a) establish connection to server
*   b) define sytems constans - paths
*   c) perform login and load current user info
*   d) load system info (from sysIdentification)
*   e) keeps array of errors
*
* system constants:
*
* HEURIST_THUMB_DIR
* HEURIST_FILESTORE_DIR
*/
class System {

    private $mysqli = null;
    private $dbname_full = null;
    private $dbname = null;
    private $session_refix = null;

    private $errors = array();

    private $is_inited = false;

    //???
    //private $guest_User = array('ugr_ID'=>0,'ugr_FullName'=>'Guest');
    private $current_User = null;
    private $system_settings = null;
    private $send_email_on_error = 1; //set to 1 to send email for all severe errors

    private $version_release = null;

    private $database_settings = array('TinyMCE formats' => 'text_styles.json', 'Webfonts' => 'webfonts.json');

    //do not check session folder, loads only basic user info
    private $need_full_session_check = false;

    /*

    init
        set_dbname_full
        init_db_connection - connect to server and select database (move to db_utils?)
        initPathConstants  - set path constants
        loginVerify  - load user info from session or reloads from database

    login
        loginVerify



    */

    public function __construct( $full_check=false ) {

        $this->need_full_session_check = $full_check;
    }

    /**
    * Read configuration parameters from config file
    *
    * Establish connection to server
    * Open database
    *
    * @param $db - database name
    * @param $dbrequired - if false only connect to server (for database list)
    * @return true on success
    */
    public function init($db, $dbrequired=true, $init_session_and_constants=true){

        if( !$this->set_dbname_full($db, $dbrequired) ){
            return false;
        }

        //dbutils?
        $connection_ok = $this->init_db_connection();
        if(!$connection_ok){
           return false;
        }

        //connection OK

        if($this->dbname_full && !defined('HEURIST_DBNAME')){
            //init once for first system - preferable use methods
            define('HEURIST_DBNAME', $this->dbname);
            define('HEURIST_DBNAME_FULL', $this->dbname_full);
        }

        if(!$init_session_and_constants){
            $this->is_inited = true;
            return true;
        }

        if(!$this->start_my_session( $this->need_full_session_check )){
            return false;
        }

        if($this->dbname_full){

            if(!$this->initPathConstants()){
                return false;
            }

            // attempt to get release version
            $this->version_release = (preg_match("/h\d+\-alpha|alpha\//", HEURIST_BASE_URL) === 1) ? "alpha" : "stable";

            if($this->need_full_session_check){
                $this->_executeScriptOncePerDay();
            }


            $this->loginVerify( false );//load user info from session on system init
            if($this->get_user_id()>0){
                //set current user for stored procedures (log purposes)
                $this->mysqli->query('set @logged_in_user_id = '.intval($this->get_user_id()));
            }

            //ONLY_FULL_GROUP_BY,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO
            $this->mysqli->query('SET GLOBAL sql_mode = \'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION\'');
        }


        $this->is_inited = true;
        return true;

    }

    //
    //
    //
    private function init_db_connection(){

        $res = mysql__connection(HEURIST_DBSERVER_NAME, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD, HEURIST_DB_PORT);
        if ( is_array($res) ){
            //connection to server failed
            $this->addError($res[0], $res[1]);
            $this->mysqli = null;
            return false;
        }else{
            $this->mysqli = $res;
        }

        if($this->mysqli){
            if($this->dbname_full)  //database is defined
            {
                $res = mysql__usedatabase($this->mysqli, $this->dbname_full);
                if ( $res!==true ){
                    //open of database failed
                    $this->addErrorArr($res);
                    return false;
                }
            }
        }
        return $res;
    }

    //
    //
    //
    public function dbclose(){

        if($this->mysqli && isset($this->mysqli->server_info)){
            $this->mysqli->close();
        }
        $this->mysqli = null;

    }


    //------------------------- RT DT CONSTANTS --------------------
    /**
    * Defines all constants
    */
    public function defineConstants($reset=false) {

        // Record type constants
        global $rtDefines;
        foreach ($rtDefines as $str => $id){
            if(!defined($str)){
                $this->defineRTLocalMagic($str, $id[1], $id[0], $reset);
            }
        }

        // Data type constants
        global $dtDefines;
        foreach ($dtDefines as $str => $id){
            if(!defined($str)){
                $this->defineDTLocalMagic($str, $id[1], $id[0], $reset);
            }
        }

        // Term constants
        global $trmDefines;
        foreach ($trmDefines as $str => $id){
            if(!defined($str)){
                $this->defineTermLocalMagic($str, $id[1], $id[0], $reset);
            }
        }

    }

    //
    // define constant with value - if not defined
    //
    public function defineConstant2($const_name, $value) {
        if(!defined($const_name)){
            define($const_name, $value);
        }
    }

    //
    //  returns constant value, init constant if not defined, if it fails returns default value
    //
    public function getConstant($const_name, $def=null) {

        return $this->defineConstant($const_name) ?constant($const_name) :$def;
    }

    //
    // init the only constant
    //
    public function defineConstant($const_name, $reset=false) {

        if(defined($const_name)){
            return true;
        }else{
            global $rtDefines;
            global $dtDefines;
            global $trmDefines;
            if(@$rtDefines[$const_name]){
                $this->defineRTLocalMagic($const_name, $rtDefines[$const_name][1], $rtDefines[$const_name][0], $reset);
            }elseif(@$dtDefines[$const_name]){
                $this->defineDTLocalMagic($const_name, $dtDefines[$const_name][1], $dtDefines[$const_name][0], $reset);
            }elseif(@$trmDefines[$const_name]){
                $this->defineTermLocalMagic($const_name, $trmDefines[$const_name][1], $trmDefines[$const_name][0], $reset);
            }
            return defined($const_name);
        }
    }

    //
    // get 3d party web service configuration and their mapping to heurist record types and fields
    //
    private function getWebServiceConfigs(){

        //read service_mapping.json from setting folder
        $config_file = dirname(__FILE__).'/controller/record_lookup_config.json';

        if(!file_exists($config_file)){
            return null;
        }

        $json = file_get_contents($config_file);

        $config = json_decode($json, true);
        if(!is_array($config)){
            return null;
        }

        $config_res = array();

        foreach($config as $idx=>$cfg){

            $rty_ID = ConceptCode::getRecTypeLocalID($cfg['rty_ID']);

            $cfg['rty_ID'] = $rty_ID;

            foreach($cfg['fields'] as $field=>$code){

                $extra = '_';

                if(strpos($code, '_') !== false){
                    $parts = explode('_', $code);
                    $code = $parts[0];
                    $extra .= $parts[1];
                }

                $dty_ID = ConceptCode::getDetailTypeLocalID($code);

                if($dty_ID != null && $extra != '_'){
                    $dty_ID .= $extra;
                }

                $cfg['fields'][$field] = $dty_ID;
            }

            $config_res[] = $cfg;
            //}
            //}
        }

        return $config_res;
    }


    //
    // get constants as array to use on client side
    //
    private function getLocalConstants( $reset=false ){

        $this->defineConstants( $reset );

        $res = array();

        global $rtDefines;
        foreach ($rtDefines as $magicRTName => $id) {
            if(defined($magicRTName)){
                $res[$magicRTName] = constant ( $magicRTName );
            }
        }

        // Data type constants
        global $dtDefines;
        foreach ($dtDefines as $magicDTName => $id) {
            if(defined($magicDTName)){
                $res[$magicDTName] = constant ( $magicDTName );
            }
        }

        // Term constants
        global $trmDefines;
        foreach ($trmDefines as $magicTermName => $id) {
            if(defined($magicTermName)){
                $res[$magicTermName] = constant ( $magicTermName );
            }
        }


        return $res;
    }

    /**
    * bind Concept Number Constants to their local id
    * @param    string [$defString] define string
    * @param    int [$rtID] origin rectype id
    * @param    int [$dbID] origin database id
    */
    private function defineRTLocalMagic($defString, $rtID, $dbID, $reset=false) {

        $id = $this->rectypeLocalIDLookup($rtID, $dbID, $reset);

        if ($id) {
            //echo "\nRT DEFINING \"" . $defString . "\" AS " . $id;
            define($defString, $id);
        } else {
            //echo "\nRT DEFINING \"" . $defString . "\" AS " . $rtID;
            // define($defString, $rtID);
        }
    }


    /**
    * lookup local id for a given rectype concept id pair
    * @global    type description of global variable usage in a function
    * @staticvar array [$RTIDs] lookup array of local ids
    * @param     int [$rtID] origin rectype id
    * @param     int [$dbID] origin database id (default to 2 which is reserved for coreDefinition)
    * @return    int local rectype ID or null if not found
    */
    private function rectypeLocalIDLookup($rtID, $dbID = 2, $reset=false) {
        static $RTIDs;

        if (!$RTIDs || $reset) {
            $res = $this->mysqli->query('select rty_ID as localID,
            rty_OriginatingDBID as dbID, rty_IDInOriginatingDB as id from defRecTypes order by dbID');
            if (!$res) {
                $this->addError(HEURIST_DB_ERROR, 'Unable to build internal record-type lookup table', $this->mysqli->error);
                /*
                echo 'Unable to build internal record-type lookup table. Please '
                    . CONTACT_SYSADMIN.' for assistance. MySQL error: '
                    . $this->mysqli->error;
                */
                exit;
            }

            $regID = $this->get_system('sys_dbRegisteredID');

            $RTIDs = array();
            while ($row = $res->fetch_assoc()) {

                if( !($row['dbID']>0) && $regID>0){
                    $row['dbID'] = $regID;
                    $row['id'] = $row['localID'];
                }

                if (!@$RTIDs[$row['dbID']]) {
                    $RTIDs[$row['dbID']] = array();
                }
                $RTIDs[$row['dbID']][$row['id']] = $row['localID'];
            }
            //print_r(@$RTIDs);
        }
        return @$RTIDs[$dbID][$rtID] ? $RTIDs[$dbID][$rtID] : null;
    }


    /**
    * bind Magic Number Constants to their local id
    * @param    string [$defString] define string
    * @param    int [$dtID] origin detailtype id
    * @param    int [$dbID] origin database id
    */
    private function defineDTLocalMagic($defString, $dtID, $dbID, $reset=false) {
        $id = $this->detailtypeLocalIDLookup($dtID, $dbID, $reset);
        if ($id) {
            //echo "\nDT DEFINING \"" . $defString . "\" AS " . $id;
            define($defString, $id);
        } else {
            //echo "\nDT DEFINING \"" . $defString . "\" AS " . $dtID;
            // define($defString, $dtID);
        }
    }


    /**
    * lookup local id for a given detailtype concept id pair
    * @global    type description of global variable usage in a function
    * @staticvar array [$RTIDs] lookup array of local ids
    * @param     int [$dtID] origin detailtype id
    * @param     int [$dbID] origin database id (default to 2 which is reserved for coreDefinition)
    * @return    int local detailtype ID or null if not found
    */
    private function detailtypeLocalIDLookup($dtID, $dbID = 2, $reset=false) {
        static $DTIDs;

        if (!$DTIDs || $reset) {
            $res = $this->mysqli->query('select dty_ID as localID,dty_OriginatingDBID as dbID,dty_IDInOriginatingDB as id from defDetailTypes order by dbID');
            if (!$res) {
                echo "Unable to build internal field-type lookup table. Please ".CONTACT_SYSADMIN
                        ." for assistance. MySQL error: " . $this->mysqli->error;
                exit;
            }


            $regID = $this->get_system('sys_dbRegisteredID');

            $DTIDs = array();
            while ($row = $res->fetch_assoc()) {

                if( !($row['dbID']>0) && $regID>0){
                    $row['dbID'] = $regID;
                    $row['id'] = $row['localID'];
                }

                if (!@$DTIDs[$row['dbID']]) {
                    $DTIDs[$row['dbID']] = array();
                }
                $DTIDs[$row['dbID']][$row['id']] = $row['localID'];
            }
        }
        return @$DTIDs[$dbID][$dtID] ? $DTIDs[$dbID][$dtID] : null;
    }

    /**
    * bind Magic Number Constants to their local id
    * @param    string [$defString] define string
    * @param    int [$trmID] origin term id
    * @param    int [$dbID] origin database id
    */
    private function defineTermLocalMagic($defString, $trmID, $dbID, $reset=false) {

        $id = ConceptCode::getTermLocalID($dbID.'-'.$trmID);

        if ($id) {
            //echo "\nTERM DEFINING \"" . $defString . "\" AS " . $id;
            define($defString, $id);
        } else {
        }
    }



    //------------------------- END RT DT and TERM CONSTANTS --------------------

    //
    //
    //
    public function getFileStoreRootFolder(){

        global $defaultRootFileUploadPath;

        if (!isEmptyStr($defaultRootFileUploadPath)) {

            if ($defaultRootFileUploadPath != "/" && !preg_match("/[^\/]\/$/", $defaultRootFileUploadPath)) { //check for trailing /
                $defaultRootFileUploadPath.= "/";// append trailing /
            }

            if ( !strpos($defaultRootFileUploadPath,":/") && $defaultRootFileUploadPath != "/" && !preg_match("/^\/[^\/]/", $defaultRootFileUploadPath)) {
                //check for leading /
                $defaultRootFileUploadPath = "/" . $defaultRootFileUploadPath; // prepend leading /
            }

            return $defaultRootFileUploadPath;

        }else{

            $install_path = 'HEURIST/';
            $dir_Filestore = "HEURIST_FILESTORE/";

            $documentRoot = @$_SERVER['DOCUMENT_ROOT'];
            if( $documentRoot && substr($documentRoot, -1, 1) != '/' ) {$documentRoot = $documentRoot.'/';}


            return  $documentRoot . $install_path . $dir_Filestore;

        }
    }

    /**
    *  Returns three values array for each system folder
    *  0 - parth of constant HEURIST_XXX_DIR and HEURIST_XXX_URL
    *  1 - desciption
    *  2 - allow webaccess (.htaccess_via_url will be copied to this folder)
    *  3 - must be backuped
    */
    public function getArrayOfSystemFolders($is_for_backup=false){

        global $allowWebAccessThumbnails, $allowWebAccessUploadedFiles, $allowWebAccessEntityFiles;

        //const name, description, allow webaccess, for backup
        $folders = array();

        $folders['filethumbs']   = array('THUMB','used to store thumbnails for uploaded files', $allowWebAccessThumbnails, true);
        $folders['hml-output']   = array('HML','used to write published records as hml files', true);
        $folders['html-output']  = array('HTML','used to write published records as generic html files', true);
        $folders['smarty-templates']  = array('SMARTY_TEMPLATES','', false, true);
        $folders['settings']      = array('SETTING','', false, true);
        // do not create constant (if name is empty)
        $folders['xsl-templates'] = array('XSL_TEMPLATES','', false, true);


        if(!$is_for_backup)
        {
            $folders['file_uploads'] = array('FILES','used to store uploaded files by default');
            //besides we have HEURIST_SCRATCHSPACE_DIR == sys temp dir
            $folders['scratch']      = array('SCRATCH','used to store temporary files', false);

            $folders['generated-reports'] = array(null,'used to write generated reports');
            $folders['entity']        = array(null,'used to store icons and images for record types users,groups,terms', $allowWebAccessEntityFiles);
            $folders['backup']        = array(null,'used to write files for user data dump');
            $folders['uploaded_tilestacks'] = array('TILESTACKS','used to store uploaded map tiles', true, false);
            //since 2023-06-02 $folders['documentation_and_templates'] = array('','', false, false);
            $folders['faims']    = array('','');
        }



        return $folders;
    }

    //
    // $is_for_backup - 0 no, 1 - archive backup, 2 - delete backup
    //
    public function getSystemFolders($database_name=null){

        $folders = $this->getArrayOfSystemFolders();

        $system_folders = array();

        $dbfolder = HEURIST_FILESTORE_ROOT;

        if($database_name!=null){
            $dbfolder = $dbfolder.$database_name.'/';
        }

        foreach ($folders as $folder_name=>$folder){
            $folder_name = $dbfolder.$folder_name;
            $folder_name = str_replace('\\', '/', $folder_name);
            array_push($system_folders, $folder_name.'/');
        }//for

        if(defined('HEURIST_XSL_TEMPLATES_DIR')) {array_push($system_folders, HEURIST_XSL_TEMPLATES_DIR);}
        if(defined('HEURIST_HTML_DIR')) {array_push($system_folders, HEURIST_HTML_DIR);}
        if(defined('HEURIST_HML_DIR')) {array_push($system_folders, HEURIST_HML_DIR);}

        return $system_folders;
    }

    //
    //
    //
    public function getSysDir($folder_name=null, $database_name=null){

        $upload_root = defined('HEURIST_FILESTORE_ROOT')
                            ?HEURIST_FILESTORE_ROOT
                            :$this->getFileStoreRootFolder();

        $database_name = $database_name==null?$this->dbname:$database_name;

        if(preg_match('/[^A-Za-z0-9_\$]/', $database_name)){
            return null; //invalid database name
        }

        $dbfolder = $upload_root.$database_name.'/';

        if($folder_name!=null){
            $dbfolder = $dbfolder . $folder_name . '/';
        }

        return $dbfolder;
    }


    //
    // $dbname - shortname (without prefix)
    //
    public function initPathConstants($dbname=null){

        global $defaultRootFileUploadPath, $defaultRootFileUploadURL;

        if(defined('HEURIST_FILESTORE_URL')){
            return true; //already defined
        }

        list($database_name_full, $dbname) = mysql__get_names($dbname);
        if(mysql__check_dbname($dbname)!=null) {return false;}

        $upload_root = $this->getFileStoreRootFolder();

        if (isEmptyStr($defaultRootFileUploadPath)) {

            //path is not configured in ini - set dafault values
            $install_path = 'HEURIST/';
            $dir_Filestore = "HEURIST_FILESTORE/";

            $defaultRootFileUploadURL = HEURIST_SERVER_URL . '/' . $install_path . $dir_Filestore;
        }

        $this->defineConstant2('HEURIST_FILESTORE_ROOT', $upload_root);
        $this->defineConstant2('HEURIST_FILESTORE_DIR', $upload_root . $dbname . '/');


        $check = folderExists(HEURIST_FILESTORE_DIR, true);
        if($check<0){

            $usr_msg = "Cannot access filestore directory for the database <b>". $dbname .
                       "</b><br>The directory "
                       .(($check==-1)
                       ?"does not exist (check setting in heuristConfigIni.php file)"
                       :"is not writeable by PHP (check permissions)")
                       ."<br><br>On a multi-tier service, the file server may not have restarted correctly or "
                       ."may not have been mounted on the web server.";


            $this->addError(HEURIST_SYSTEM_FATAL, $usr_msg, null, "Problem opening database");
            return false;
        }

        define('HEURIST_FILESTORE_URL', $defaultRootFileUploadURL . $dbname . '/');

        $folders = $this->getArrayOfSystemFolders();
        $warnings = array();

        foreach ($folders as $folder_name=>$folder){

            if(isEmptyStr($folder[0])) { continue; }

            $allowWebAccess = (@$folder[2]===true);

            $dir = HEURIST_FILESTORE_DIR.$folder_name.'/';

            $warn = folderCreate2($dir, $folder[1], $allowWebAccess);
            if($warn!=''){ //can't creat or not writeable
                $warnings[] = $warn;
                continue;
            }

            //it defines constants HEURIST_[FOLDER]_DIR and HEURIST_[FOLDER]_URL
            define('HEURIST_'.$folder[0].'_DIR', $dir);
            if($allowWebAccess){
                define('HEURIST_'.$folder[0].'_URL', HEURIST_FILESTORE_URL.$folder_name.'/');
            }
        }//for

        if(!empty($warnings)){
            $this->addError(HEURIST_SYSTEM_FATAL, implode('',$warnings));
            return false;
        }


        define('HEURIST_RTY_ICON', HEURIST_BASE_URL.'?db='.$dbname.'&icon=');//redirected to hserv/controller/fileGet.php

        return true;
    }

    /*
    // NOT USED. It is assumed that it is in HEURIST folder
    //
    private function getInstallPath(){

        $documentRoot = @$_SERVER['DOCUMENT_ROOT'];
        if( $documentRoot && substr($documentRoot, -1, 1) != '/' ) {$documentRoot = $documentRoot.'/';}

        $topDirs = "admin|api|applications|common|context_help|export|hapi|hclient|hserv|import|records|redirects|search|viewers|help|ext|external";// Upddate in 3 places if changed
        $installDir = preg_replace("/\/(" . $topDirs . ")\/.* /", "", @$_SERVER["SCRIPT_NAME"]);// remove "/top level dir" and everything that follows it.

        if ($installDir == @$_SERVER["SCRIPT_NAME"]) { // no top directories in this URI must be a root level script file or blank
            $installDir = preg_replace("/\/[^\/]*$/", "", @$_SERVER["SCRIPT_NAME"]);// strip away everything past the last slash "/index.php" if it's there
        }

        //the subdir of the server's document directory where heurist is installed
        if ($installDir == @$_SERVER["SCRIPT_NAME"]) { // this should be the path difference between document root and heurist code root
            $installDir = '';
        }

        $install_path = @$_SERVER['DOCUMENT_ROOT'].$installDir;
        if( substr($install_path, -1, 1) == '/' ) {$install_path = substr($install_path,0,-1);}//remove last slash

        if(is_link($install_path)){
            $install_path = readlink($install_path);//real installation path eg. html/HEURIST/h3-ij/
        }else{
            $install_path = "";
        }

        if($install_path!=""){ //this is simlink
            //remove code folder - to get real HEURIST installation
            if( substr($install_path, -1, 1) == '/' ) {$install_path = substr($install_path,0,-1);}//remove last slash
            if(strrpos($install_path,"/")>0){
                $install_path = substr($install_path,0,strrpos($install_path,"/")+1);//remove last folder

                if(strpos($install_path, $documentRoot)===0){
                    $install_path = substr($install_path, strlen($documentRoot));
                }
            }else{
                $install_path = "";
            }
        }else {

            $install_dir = $installDir;
            if($install_dir){
                if( substr($install_dir, -1, 1) == '/' ) {$install_dir = substr($install_dir,0,-1);}//remove last slash
                if($install_dir!=""){
                    if(strrpos($install_dir,"/")>0){
                        $install_dir = substr($install_dir,0,strrpos($install_dir,"/")+1);//remove last folder
                    }else{
                        $install_dir = "";
                    }
                }
                //$install_path = $install_dir . $install_path;
            }
            $install_path = $install_dir;
        }
        if( $install_path && substr($install_path, 0, 1) == '/' ) {$install_path = substr($install_path,1);}//remove first slash

        return $install_path;
    }
    */


    /**
    * Returns true if system is inited ccorretly and db connection is established
    */
    public function is_inited(){
        return $this->is_inited;
    }

    /**
    * Get database connection object
    */
    public function get_mysqli(){
        return $this->mysqli;
    }

    public function set_mysqli($mysqli){
        $this->mysqli = $mysqli;
    }

    /**
    * Get full name of database
    */
    public function dbname_full(){
        return $this->dbname_full;
    }

    public function dbname(){
        return $this->dbname;
    }

    /**
    * set dbname and dbname_full properties
    *
    * @param mixed $db
    */
    public function set_dbname_full($db, $dbrequired=true){

        $error = mysql__check_dbname($db);

        if($error==null && preg_match('/[A-Za-z0-9_\$]/', $db)){ //additional validatate database name for sonarcloud
            list($this->dbname_full, $this->dbname ) = mysql__get_names( $db );
        }else{
            $this->dbname = null;
            $this->dbname_full = null;

            if($dbrequired){
                $this->addErrorArr($error);
                $this->mysqli = null;
                return false;
            }
        }
        return true;
    }


    /**
    * produce json output and
    * terminate execution of script
    *
    * @param mixed $message
    */
    public function error_exit( $message, $error_code=null) {

        $this->dbclose();

        header(CTYPE_JSON);
        if($message){
            if($error_code==null){
                $error_code = HEURIST_INVALID_REQUEST;
            }
            $this->addError($error_code, $message);
        }

        print json_encode( $this->getError() );

        exit;
    }

    //
    //
    //
    public function error_exit_api( $message=null, $error_code=null, $is_api=true) {

        $this->dbclose();

        if($message){
            if($error_code==null){
                $error_code = HEURIST_INVALID_REQUEST;
            }
            $this->addError($error_code, $message);
        }

        $response = $this->getError();


        if($is_api){
            header(HEADER_CORS_POLICY);
            header(CTYPE_JSON);

            $status = @$response['status'];
            if($status==HEURIST_INVALID_REQUEST){
                $code = 400; // Bad Request - the request could not be understood or was missing required parameters.
            }elseif($status==HEURIST_REQUEST_DENIED) {
                $code = 403; // Forbidden - access denied
            }elseif($status==HEURIST_NOT_FOUND){
                $code = 404; //Not Found - resource was not found.
            }elseif($status==HEURIST_ACTION_BLOCKED) {
                $code = 409; //cannot add an existing object already exists or constraints violation
            }else{
                //HEURIST_ERROR, HEURIST_UNKNOWN_ERROR, HEURIST_DB_ERROR, HEURIST_SYSTEM_CONFIG, HEURIST_SYSTEM_FATAL
                $code = 500; //An unexpected internal error has occurred. Please contact Support for more information.
            }

            http_response_code($code);
        }else{
            header(CTYPE_JSON);
        }

        print json_encode( $response );

        exit;
    }

    //
    // add prefix for error message
    //
    public function addErrorMsg($message) {
        if($this->errors && @$this->errors['message']){
            $this->errors['message']  = $message.$this->errors['message'];
        }else{
            $this->addError(HEURIST_ERROR, $message);
        }
    }

    // NOT USED
    public function setErrorEmail($val){
        $this->send_email_on_error = $val;
    }

    /**
    * keeps error message (for further use with getError)
    */
    public function addErrorArr($error) {
        if(!is_array($error)){
            //just message - general message
            $error = array(HEURIST_ERROR, $error);
        }
        if(@$error['message']){
            //from remote request
            $status = @$error['status']?$error['status']:HEURIST_ERROR;
            return $this->addError($status, $error['message'], @$error['sysmsg'], @$error['error_title']);
        }else{
            //from mysql__ functions
            return $this->addError($error[0], $error[1], @$error[2], @$error[3]);
        }
    }

    //
    //
    //
    private function _treatSeriousError($status, $message, $sysmsg, $title) {

            $now = $this->getNow();
            $curr_logfile = 'errors_'.$now->format('Y-m-d').'.log';

            //3. write error into current error log
            $Title = 'db: '.preg_replace(REGEX_EOL, ' ', $this->dbname())
                    ."\nerr-type: ".preg_replace(REGEX_EOL, ' ', $status)
                    ."\nuser: ".$this->get_user_id()
                    .' '.@$this->current_User['ugr_FullName']
                    .' <'.@$this->current_User['ugr_eMail'].'>';

            //clear sensetive info
            $sensetive = array('pwd','','chpwd','create_pwd','usrPassword','password');
            array_walk($sensetive,function($key){
                if(array_key_exists($key,$_REQUEST)){
                    unset($_REQUEST[$key]);
                }
            });

            $sMsg = "\nMessage: ".preg_replace(REGEX_EOL, ' ', $message)."\n"
                    .($sysmsg?'System message: '.$sysmsg."\n":'')
                    .'Script: '.@$_SERVER['REQUEST_URI']."\n"
                    .'Request: '.substr(print_r($_REQUEST, true),0,2000)."\n\n"
                    ."------------------\n";

            if(defined('HEURIST_FILESTORE_ROOT')){
                $root_folder = HEURIST_FILESTORE_ROOT;
                fileAdd($Title.'  '.$sMsg, $root_folder.$curr_logfile);
            }

            $mysql_gone_away_error = $this->mysqli && $this->mysqli->errno==2006;
            if($mysql_gone_away_error){
                $message =  $message
                            .' There is database server intermittens. '.CRITICAL_DB_ERROR_CONTACT_SYSADMIN;
            }else{
                $message = 'Heurist was unable to process this request. ' . $message;
                $sysmsg = 'Although errors are emailed to the Heurist team (for servers maintained directly by the project), there are several thousand Heurist databases, so we are unable to review all automated reports. If this is the first time you have seen this error, please try again in a few minutes in case it is a temporary network outage. Please contact us if this error persists and is causing you a problem, as this will help us identify important issues. We apologise for any inconvenience';
            }

            if(!$mysql_gone_away_error){
                $message = "Heurist was unable to process this request.<br><strong>$message</strong><br>";
            }

            $this->errors = array("status"=>$status, "message"=>$message, "sysmsg"=>$sysmsg, 'error_title'=>$title);
    }


    /**
    * keep error message (for further use with getError)
    */
    public function addError($status, $message='', $sysmsg=null, $title=null) {

        if($status==HEURIST_REQUEST_DENIED && $sysmsg==null){
            $sysmsg = $this->get_user_id();
        }

        if($status!=HEURIST_INVALID_REQUEST && $status!=HEURIST_NOT_FOUND &&
           $status!=HEURIST_REQUEST_DENIED && $status!=HEURIST_ACTION_BLOCKED){
            $this->_treatSeriousError($status, $message, $sysmsg, $title);
        }else{
            $this->errors = array("status"=>$status, "message"=>$message, "sysmsg"=>$sysmsg, 'error_title'=>$title);
        }

        return $this->errors;
    }

    /**
    * returns error array (status,message,sysmsg,error_title)
    */
    public function getError(){
        return $this->errors;
    }

    public function getErrorMsg(){
        $ret = $this->errors['message'] ?? '';
        return $ret;
    }

    public function clearError(){
        $this->errors = array();
    }


    //
    // returns total records in db and counts of active entries in dashboard
    //  invoked on page init and after login
    //
    public function getTotalRecordsAndDashboard(){

        if( !$this->mysqli ){ return array(0,0,0); }


        $db_total_records = 0;
        $db_has_active_dashboard = 0;
        $db_workset_count = 0;

        $db_total_records = mysql__select_value($this->mysqli, 'SELECT count(*) FROM Records WHERE not rec_FlagTemporary');
        $db_total_records = ($db_total_records>0)?$db_total_records:0;

        if($this->has_access())
        {
            $query = 'select count(*) from sysDashboard where dsh_Enabled="y"';
            if($db_total_records<1){
                $query = $query.'AND dsh_ShowIfNoRecords="y"';
            }
            $db_has_active_dashboard = mysql__select_value($this->mysqli, $query);
            $db_has_active_dashboard = ($db_has_active_dashboard>0)?$db_has_active_dashboard:0;

            $curr_user_id = $this->get_user_id();
            if($curr_user_id>0){
                $query = 'select count(*) from usrWorkingSubsets where wss_OwnerUGrpID='.$curr_user_id;
                $db_workset_count = mysql__select_value($this->mysqli, $query);
                $db_workset_count = ($db_workset_count>0)?$db_workset_count:0;
            }
        }

        return array($db_total_records, $db_has_active_dashboard, $db_workset_count);
    }

    /**
    * Returns all info for current user and some sys config parameters
    * see usage usr_info.sysinfo and usr_info.login
    *
    * it always reload user info from database
    */
    public function getCurrentUserAndSysInfo( $include_reccount_and_dashboard_count=false, $is_guest_allowed=false )
    {
        global $passwordForDatabaseCreation, $passwordForDatabaseDeletion,
               $passwordForReservedChanges, $passwordForServerFunctions,
               $needEncodeRecordDetails,
               $common_languages_for_translation, $glb_lang_codes, $glb_lang_codes_index,
               $saml_service_provides, $hideStandardLogin,
               $accessToken_DeepLAPI, $useRewriteRulesForRecordLink,
               $allowCMSCreation;

        if(!isset($needEncodeRecordDetails)){
            $needEncodeRecordDetails = 0;
        }

        // extracts from $glb_lang_codes names and alpha2 codes to be sent to client
        initLangCodes();

        $common_languages = array();
        //ordered as in $common_languages_for_translation (defined in heuristConfigIni)
        foreach($common_languages_for_translation as $code){
            $lang = strtolower($code);

            $key = array_search($lang, array_column($glb_lang_codes, 'a3'));
            if($key!==false){
                $common_languages[strtoupper($lang)] = $glb_lang_codes[$key];
            }
        }


        try{

            list($host_logo, $host_url) = USystem::getHostLogoAndUrl();

            if(!$this->mysqli){
                return array(
                    "currentUser"=>null,
                    "sysinfo"=>array(
                        "help"=>HEURIST_HELP,
                        "version"=>HEURIST_VERSION,
                        "sysadmin_email"=>HEURIST_MAIL_TO_ADMIN,
                        "baseURL"=>HEURIST_BASE_URL,
                        'baseURL_pro'=>HEURIST_BASE_URL_PRO,
                        "referenceServerURL"=>HEURIST_INDEX_BASE_URL),
                        'host_logo'=>$host_logo,
                        'host_url'=>$host_url,
                        'saml_service_provides'=>$saml_service_provides,
                        'hideStandardLogin' => $hideStandardLogin,
                        'common_languages'=>$common_languages,
                        'use_redirect' => @$useRewriteRulesForRecordLink
                );
            }

            //current user reset - reload actual info from database
            $this->loginVerify( true, $is_guest_allowed );

            $dbowner = user_getDbOwner($this->mysqli);//info about user #2

            //list of databases recently logged in
            $dbrecent = USystem::sessionRecentDatabases($this->current_User);

            //retrieve lastest code version (cached in localfile and refreshed from main index server daily)
            $lastCode_VersionOnServer = $this->get_last_code_and_db_version($this->version_release == "alpha" ? true : false);

            $res = array(
                "currentUser"=>$this->current_User,
                "sysinfo"=>array(
                    "registration_allowed"=>$this->get_system('sys_AllowRegistration'), //allow new user registration
                    "db_registeredid"=>$this->get_system('sys_dbRegisteredID'),
                    "db_managers_groupid"=>($this->get_system('sys_OwnerGroupID')>0?$this->get_system('sys_OwnerGroupID'):1),
                    "help"=>HEURIST_HELP,

                    //code version from configIni.php
                    "version"=>HEURIST_VERSION,
                    "version_new"=>$lastCode_VersionOnServer, //version on main index database server
                    //db version
                    "db_version"=> $this->get_system('sys_dbVersion').'.'
                                        .$this->get_system('sys_dbSubVersion').'.'
                                        .$this->get_system('sys_dbSubSubVersion'),
                    "db_version_req"=>HEURIST_MIN_DBVERSION,

                    "dbowner_name"=>@$dbowner['ugr_FirstName'].' '.@$dbowner['ugr_LastName'],
                    "dbowner_org"=>@$dbowner['ugr_Organisation'],
                    "dbowner_email"=>@$dbowner['ugr_eMail'],
                    "sysadmin_email"=>HEURIST_MAIL_TO_ADMIN,
                    "db_total_records"=>$this->get_system('sys_RecordCount'),
                    "db_usergroups"=> user_getAllWorkgroups($this->mysqli), //all groups- to fast retrieve group name
                    "baseURL"=>HEURIST_BASE_URL,
                    'baseURL_pro'=>HEURIST_BASE_URL_PRO,
                    //"serverURL"=>HEURIST_SERVER_URL,
                    "referenceServerURL"=>HEURIST_INDEX_BASE_URL,
                    "dbconst"=>$this->getLocalConstants( $include_reccount_and_dashboard_count ), //some record and detail types constants with local values specific for current db
                    "service_config"=>$this->get_system('sys_ExternalReferenceLookups'), //get 3d part web service mappings
                    "services_list"=>$this->getWebServiceConfigs(), //get list of all implemented lookup services
                    "dbrecent"=>$dbrecent,  //!!!!!!! need to store in preferences
                    "cms_allowed"=> $allowCMSCreation??1,

                    'max_post_size'=>USystem::getConfigBytes('post_max_size'),
                    'max_file_size'=>USystem::getConfigBytes('upload_max_filesize'),
                    'is_file_multipart_upload'=>($this->getDiskQuota()>0)?1:0,
                    'host_logo'=>$host_logo,
                    'host_url'=>$host_url,

                    'media_ext'=>HEURIST_ALLOWED_EXT, //$this->get_system('sys_MediaExtensions'),
                    'rty_as_place'=>$this->get_system('sys_TreatAsPlaceRefForMapping'),

                    'need_encode'=>$needEncodeRecordDetails,

                    'custom_js_allowed'=>$this->isJavaScriptAllowed(),

                    'common_languages'=>$common_languages,

                    'saml_service_provides'=>$saml_service_provides,
                    'hideStandardLogin' => $hideStandardLogin,

                    'nakala_api_key'=>$this->get_system('sys_NakalaKey'),

                    'pwd_DatabaseCreation'=> (strlen(@$passwordForDatabaseCreation)>6), //pwd to creaste new database
                    'pwd_DatabaseDeletion'=> (strlen(@$passwordForDatabaseDeletion)>15),//delete from db statistics
                    'pwd_ReservedChanges' => (strlen(@$passwordForReservedChanges)>6),  //allow change reserved fields
                    'pwd_ServerFunctions' => (strlen(@$passwordForServerFunctions)>6),  //allow run multi-db server actions
                    'api_Translator' => (!empty($accessToken_DeepLAPI)), // an api key has been setup for Deepl
                    'use_redirect' => @$useRewriteRulesForRecordLink
                )
            );

            if($include_reccount_and_dashboard_count){
                $res2 = $this->getTotalRecordsAndDashboard();
                $res['sysinfo']['db_total_records'] = $res2[0];
                $res['sysinfo']['db_has_active_dashboard'] = $res2[1];
                $res['sysinfo']['db_workset_count'] = $res2[2];
            }

            recreateRecLinks( $this, false );//see utils_db

        }catch( \Exception $e ){
            $this->addError(HEURIST_ERROR, 'Unable to retrieve Heurist system information', $e->getMessage());
            $res = false;
        }

        return $res;
    }



    /**
    * Get current user info
    */
    public function getCurrentUser(){
        // $this->current_User['ismaster'] = $system->is_admin();
        return $this->current_User; // ?$this->current_User :$this->$guest_User;
    }

    /**
    * Set current user info
    *
    * @param mixed $user
    */
    public function setCurrentUser($user){
        $this->current_User = $user;
    }



    /**
    * Get if of current user, if not looged in returns zero
    *
    */
    public function get_user_id(){
        return $this->current_User? intval($this->current_User['ugr_ID']) :0;
    }



    /**
    * Returns array of ID of all groups for current user plus current user ID
    * $level - admin/memeber
    */
    public function get_user_group_ids($level=null, $refresh=false){

        $ugrID = $this->get_user_id();

        if($ugrID>0){
            $groups = @$this->current_User['ugr_Groups'];
            if($refresh || !is_array($groups)){
                $groups = $this->current_User['ugr_Groups'] = user_getWorkgroups($this->mysqli, $ugrID);
            }
            if($level!=null){
                $groups = array();
                foreach($this->current_User['ugr_Groups'] as $grpid=>$lvl){
                    if($lvl==$level){
                        $groups[] = $grpid;
                    }
                }
            }else{
                $groups = array_keys($groups);
            }


            //add user itself
            array_push($groups, intval($ugrID) );
            return $groups;
        }else{
            return null;
        }
    }



    /**
    * Returns true if given id is id of current user or it is id of member of one of current Workgroup
    *
    * @param mixed $ug - user ID to check
    */
    public function is_member($ugs){

        if($ugs==0 || isEmptyArray($ugs)){
            return true;
        }

        $current_user_grps = $this->get_user_group_ids();
        $ugs = prepareIds($ugs, true);//include zero
        foreach ($ugs as $ug){
            if ($ug==0 || (is_array($current_user_grps) && in_array($ug, $current_user_grps)) ){
                return true;
            }
        }
        return false;
        //return  $ug==0 || $ug==null || in_array($ug, $this->get_user_group_ids()) ;
    }

    /**
    * Verifies is current user is database owner
    * used to manage any recThereadedComments, recUploadedFiles, Reminders, Bookmarks, UsrTags
    * otherwise only direct owners can modify them or members of workgroup tags
    */
    public function is_dbowner(){
        return $this->get_user_id()==2;
    }

    /**
    * id db owner or admin of database managers
    *
    * @param mixed $ugrID
    * @return mixed
    */
    public function is_admin(){
        $ret = ($this->get_user_id()>0 &&
                   ($this->get_user_id()==2 ||
                    $this->has_access( $this->get_system('sys_OwnerGroupID') ) ));
        return $ret;
    }

    public function is_guest_user(){
        $user = $this->current_User;
        return $user!=null && @$user['ugr_Permissions']['guest_user'];
    }


    /**
    * check if current user is system administrator
    */
    public function is_system_admin(){
        if ($this->get_user_id()>0){
            $user = user_getById($this->mysqli, $this->get_user_id());
            return defined('HEURIST_MAIL_TO_ADMIN') && (@$user['ugr_eMail']==HEURIST_MAIL_TO_ADMIN);
        }else{
            return false;
        }
    }

    /**
    * Returns IF currentUser satisfies to required level
    *
    * @param requiredLevel
    * null or <1 - (DEFAULT) is logged in
    * 1 - db admin (admin of group 1 "Database managers")
    * 2 - db owner
    * n - admin of given group
    */
    public function has_access( $requiredLevel=null ) {

        $ugrID = $this->get_user_id();

        if(!$requiredLevel || $requiredLevel<1){
            return $ugrID>0;//just logged in
        }

        if ($requiredLevel==$ugrID ||   //iself
                2==$ugrID)   //db owner
        {
            return true;
        }else{
            //@$this->current_User['ugr_Groups'][$requiredLevel]=='admin');//admin of given group
            $current_user_grps = $this->get_user_group_ids('admin');
            return is_array($current_user_grps) && in_array($requiredLevel, $current_user_grps);
        }
    }

    /**
    * Restore session by cookie id, or start new session
    * Refreshes cookie
    */
    private function start_my_session($check_session_folder=true){

        if(headers_sent()) {return true;}

        //verify that session folder is writable
        if($this->need_full_session_check && $check_session_folder && !USystem::sessionCheckFolder()){
            $this->addError(HEURIST_SYSTEM_FATAL, "The sessions folder has become inaccessible. This is a minor, but annoying, problem for which we apologise. An email has been sent to your system administrator asking them to fix it - this may take up to a day, depending on time differences. Please try again later.");
            return false;
        }

        if (session_status() != PHP_SESSION_ACTIVE) {

            session_name('heurist-sessionid');//set session name
            session_cache_limiter('none');

            /*
            if (@$_COOKIE['heurist-sessionid']) { //get session id from cookes
                session_id($_COOKIE['heurist-sessionid']);
            }
            */

            @session_start();
        }

        if (session_status() != PHP_SESSION_ACTIVE) {
            return false;
        }

        if (@$_SESSION[$this->dbname_full]['keepalive'] && !USystem::sessionUpdateCookies())
        {
            USanitize::errorLog('CANNOT UPDATE COOKIE '.session_id().'   '.$this->dbname_full);
        }

        return true;
    }


    /*
    * Verifies session only (without database connection and system initialization)
    * return current user id or false
    */
    public function verify_credentials($db){

        if( $this->set_dbname_full($db) && $this->start_my_session(false) ){
            return @$_SESSION[$this->dbname_full]['ugr_ID'];
        }else{
            return false;
        }

    }


    /**
    * Load user info from session - called on init only
    *
    * $user = true - reload user info (id, name) from database
    *         false -  from $_SESSION
    *
    * ugr_Preferences are always loaded from database
    *
    * @todo - load all user info if it is required only
    */
    private function loginVerify( $user, $is_guest_allowed=false ){

        $reload_user_from_db = false;

        if( is_array($user) ){  //NOT USED user info already found (see login) - need reset session
            $reload_user_from_db = true;
            $userID = $user['ugr_ID'];
        }else{

            $reload_user_from_db = ($user==true);//reload user unconditionally

            $userID = @$_SESSION[$this->dbname_full]['ugr_ID'];
        }

        if($userID == null){
            //some databases may share credentials
            //check that there is session for linked databases
            //if such session exists find email in linked database
            //by this email find user id in this database and establish new session

            $userID = $this->doLoginByLinkedSession();

            $reload_user_from_db = ($userID!=null);
        }

        $islogged = ($userID != null);

        if(!$islogged){
            return false;
        }

        if(@$_SESSION[$this->dbname_full]['need_refresh']) {
            unset($_SESSION[$this->dbname_full]['need_refresh']);
        }

        $fname = HEURIST_FILESTORE_DIR.basename($userID);
        if(file_exists($fname)){  //user info was updated by someone else
            unlink($fname);
            //marker for usr_info.verify_credentials to be sure that client side is also up to date
            if($user!==true) {$_SESSION[$this->dbname_full]['need_refresh'] = 1;}
            $reload_user_from_db = true;
        }

        if($reload_user_from_db){ //from database

            if(!$this->updateSessionForUser( $userID )){
                return false; //not logged in
            }

            if($is_guest_allowed && @$_SESSION[$this->dbname_full]['ugr_Permissions']['disabled']){
                $_SESSION[$this->dbname_full]['ugr_Permissions']['disabled'] = false;
                $_SESSION[$this->dbname_full]['ugr_Permissions']['guest_user'] = true;
            }

            //always restore from db
            $this->current_User = ['ugr_ID' => intval($userID)]; // set user ID to avoid resetting preferences
            $_SESSION[$this->dbname_full]['ugr_Preferences'] = user_getPreferences( $this );
        }//$reload_user_from_db from db

        $this->current_User = array('ugr_ID'=>intval($userID),
                        'ugr_Name'        => @$_SESSION[$this->dbname_full]['ugr_Name'],
                        'ugr_FullName'    => $_SESSION[$this->dbname_full]['ugr_FullName'],
                        'ugr_Groups'      => $_SESSION[$this->dbname_full]['ugr_Groups'],
                        'ugr_Permissions' => $_SESSION[$this->dbname_full]['ugr_Permissions']);

        $this->current_User['ugr_Preferences'] = $_SESSION[$this->dbname_full]['ugr_Preferences'];

        //remove credentials for remote repositories
        if(@$this->current_User['ugr_Preferences']['externalRepositories']){
            $this->current_User['ugr_Preferences']['externalRepositories'] = null;
            unset($this->current_User['ugr_Preferences']['externalRepositories']);
        }


        return $islogged;
    }

    //
    // Update session with actual user info from database: id, name
    //
    public function updateSessionForUser( $userID ){

        $user = user_getById($this->mysqli, $userID);

        //user can be removed - check presence
        if($user==null){
            return false; //not logged in
        }

        $_SESSION[$this->dbname_full]['ugr_ID'] = $userID;
        $_SESSION[$this->dbname_full]['ugr_Groups']   = user_getWorkgroups( $this->mysqli, $userID );
        $_SESSION[$this->dbname_full]['ugr_Name']     = $user['ugr_Name'];
        $_SESSION[$this->dbname_full]['ugr_FullName'] = $user['ugr_FirstName'] . ' ' . $user['ugr_LastName'];
        $_SESSION[$this->dbname_full]['ugr_Enabled']  = $user['ugr_Enabled'];

        $is_disabled = $user['ugr_Enabled'] == 'n';
        $_SESSION[$this->dbname_full]['ugr_Permissions'] = array(
                    'disabled' => $is_disabled,
                    'add' => strpos($user['ugr_Enabled'], 'add') === false && !$is_disabled,
                    'delete' => strpos($user['ugr_Enabled'], 'del') === false && !$is_disabled);

        return true;
    }


    /**
    * some databases may share credentials
    * check that there is a session for linked databases
    * if such session exists find email in linked database
    * by this email find user id in this database and establish new session
    *
    * return userid in this database
    */
    private function doLoginByLinkedSession(){
        //1. find sys_UGrpsDatabase in this database
        $linked_dbs = mysql__select_value($this->mysqli, 'select sys_UGrpsDatabase from sysIdentification');
        if($linked_dbs)
        {

            $linked_dbs = explode(',', $linked_dbs);
            foreach ($linked_dbs as $ldb){
                //2. check if session exists
                if(strpos($ldb, HEURIST_DB_PREFIX)!==0){
                    $ldb = HEURIST_DB_PREFIX.$ldb;
                }

                $userID_in_linkedDB = @$_SESSION[$ldb]['ugr_ID'];

                if( $userID_in_linkedDB>0 ){
                    //3. find sys_UGrpsDatabase in linked database - this database must be in list
                    $linked_dbs2 = mysql__select_value($this->mysqli, 'select sys_UGrpsDatabase from '.$ldb.'.sysIdentification');
                    if(!$linked_dbs2) {continue;} //this database is not mutually linked
                    $linked_dbs2 = explode(',', $linked_dbs2);
                    foreach ($linked_dbs2 as $ldb2){
                        if(strpos($ldb2, HEURIST_DB_PREFIX)!==0){
                            $ldb2 = HEURIST_DB_PREFIX.$ldb2;
                        }
                        if( strcasecmp($this->dbname_full, $ldb2)==0 ){
                            //yes database is mutually linked
                            //4. find user email in linked database
                            $userEmail_in_linkedDB = mysql__select_value($this->mysqli, 'select ugr_eMail from '
                                .$ldb.'.sysUGrps where ugr_ID='.$userID_in_linkedDB);

                            //5. find user by email in this database
                            if($userEmail_in_linkedDB){
                                $user = user_getByField($this->get_mysqli(), 'ugr_eMail', $userEmail_in_linkedDB);
                                if(null != $user && $user['ugr_Type']=='user' && $user['ugr_Enabled']!='n') {
                                    //6. success - establed new session
                                    $this->doLoginSession($user['ugr_ID'], 'public');
                                    return $user['ugr_ID'];
                                }
                            }
                        }
                    }
                }
            }
        }

        return null;

    }

    /**
    * Find user by name and password and keeps user info in current_User and in session
    *
    * @param mixed $username
    * @param mixed $password
    * @param mixed $session_type   - public, shared, remember
    *
    * @return  TRUE if login is success
    */
    public function doLogin($username, $password, $session_type, $skip_pwd_check=false, $is_guest=false){
        global $passwordForDatabaseAccess;

        if(!($username && ($password || $skip_pwd_check))){
            $this->addError(HEURIST_INVALID_REQUEST, "Username / password not defined");//INVALID_REQUEST
            return false;
        }

        if($skip_pwd_check
            || (isset($passwordForDatabaseAccess) && strlen($passwordForDatabaseAccess)>15 && $passwordForDatabaseAccess==$password)
          )
        {
            $user_id = is_numeric($username)?intval($username):2;
            $user = user_getById($this->mysqli, $user_id);
            $skip_pwd_check = true;
        }else{
            $user = user_getByField($this->mysqli, 'ugr_Name', $username);//dbsUsersGroups.php
        }

        if(!$user){
            $this->addError(HEURIST_REQUEST_DENIED,  "The credentials supplied are not correct");

        }elseif (!$is_guest && $user['ugr_Enabled'] == 'n'){

            $this->addError(HEURIST_REQUEST_DENIED,  "Your user profile is not active. Please contact database owner");

        }elseif ($skip_pwd_check || hash_equals(crypt($password, $user['ugr_Password']), $user['ugr_Password']) ) {

            $this->doLoginSession($user['ugr_ID'], $session_type);

            return true;
        }else{
            $this->addError(HEURIST_REQUEST_DENIED,  "The credentials supplied are not correct");
        }

        return false;

    }

    //
    // establish new session
    //
    private function doLoginSession($userID, $session_type){

        $time = 0;
        if($session_type == 'public'){
            $lifetime = 0;
        }elseif($session_type == 'shared'){
            $lifetime = time() + 24*60*60;     //day
        }elseif($session_type == 'remember') {
            $lifetime = time() + 30*24*60*60;  //30 days
            $_SESSION[$this->dbname_full]['keepalive'] = true; //refresh time on next entry
        }

        USystem::sessionUpdateCookies($lifetime);

        $_SESSION[$this->dbname_full]['ugr_ID'] = $userID;

        //update login time in database
        user_updateLoginTime($this->mysqli, $userID);
    }


    /**
    * Clears cookie and destroy session and current_User info
    */
    public function doLogout(){

        $this->start_my_session(false);

        unset($_SESSION[$this->dbname_full]['ugr_ID']);
        unset($_SESSION[$this->dbname_full]['ugr_Name']);
        unset($_SESSION[$this->dbname_full]['ugr_FullName']);
        if(@$_SESSION[$this->dbname_full]['ugr_Groups']) {unset($_SESSION[$this->dbname_full]['ugr_Groups']);}
        if(@$_SESSION[$this->dbname_full]['ugr_Permissions']) {unset($_SESSION[$this->dbname_full]['ugr_Permissions']);}
        if(@$_SESSION[$this->dbname_full]['ugr_GuestUser']!=null) {unset($_SESSION[$this->dbname_full]['ugr_GuestUser']);}

        // clear
        // even if user is logged to different databases he has the only session per browser
        // it means logout exits all databases
        $is_https = (@$_SERVER['HTTPS']!=null && $_SERVER['HTTPS']!='');
        //$session_id = session_id();
        $cres = setcookie('heurist-sessionid', '', time() - 3600, '/', '', $is_https, true);//logout
        $this->current_User = null;
        session_destroy();

        session_write_close();
        return true;
    }

    //
    // Returns individual property from SESSION
    // To load the entire set of preferences from database use user_getPreferences
    //
    public function user_GetPreference($property, $def=null){

        $res = @$_SESSION[$this->dbname_full]["ugr_Preferences"][$property];

        // TODO: redundancy: this duplicates same in hapi.js
        if('search_detail_limit'==$property){
            if(!$res || $res<500 ) {$res = 500;}
            elseif($res>5000 ) {$res = 5000;}
        }elseif($res==null && $def!=null){
            $res = $def;
        }

        return $res;
    }

    //
    //
    //
    public function user_LogActivity($action, $suplementary = '', $user_id=null){

        if($user_id==null){
            $this->loginVerify( false );
            $user_id = $this->get_user_id();
        }

        $now = new \DateTime();

        $user_agent = USystem::getUserAgent();

        $IPv4 = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        $IPv4 = empty($IPv4) ? 'Unknown' : $IPv4;

        $info = array($user_id, $action, $now->format(DATE_8601), $user_agent['os'], $user_agent['browser'], $IPv4);

        if(is_array($suplementary)){
            $info = array_merge($info, $suplementary);
        }else{
            array_push($info, $suplementary);
        }

        //$info = array_map('htmlspecialchars', $info);
        file_put_contents ( $this->getSysDir().'userInteraction.log' , implode(',', $info)."\n", FILE_APPEND );
    }

    /**
    * Loads system settings (default values) from sysIdentification
    */
    public function get_system( $fieldname=null, $need_reset = false ){

        if(!$this->system_settings || $need_reset)
        {
            $check_updates = !$this->system_settings;

            $mysqli = $this->mysqli;
            $this->system_settings = getSysValues($mysqli);

            if(!$this->system_settings){
                //HEURIST_SYSTEM_FATAL
                $this->addError(HEURIST_DB_ERROR, 'Unable to read sysIdentification', $mysqli->error);
                return null;
            }

            if($check_updates){
                //updateDatabaseToLatest($this);
            }

            // it is required for main page only - so call this request on index.php
            //$this->system_settings['sys_RecordCount'] = mysql__select_value($mysqli, 'select count(*) from Records');
        }
        $ret = ($fieldname) ?@$this->system_settings[$fieldname] :$this->system_settings;
        return $ret;
    }

    //
    // Check if user's javascript is allowed in smarty reports
    //
    public function isJavaScriptAllowed(){

        $is_allowed = false;
        $fname = realpath(dirname(__FILE__)."/../../js_in_database_authorised.txt");
        if($fname!==false && file_exists($fname)){
            //ini_set('auto_detect_line_endings', 'true');
            $handle = @fopen($fname, "r");
            while (!feof($handle)) {
                $line = trim(fgets($handle, 100));
                if($line==$this->dbname){
                    $is_allowed=true;
                    break;
                }
            }
            fclose($handle);
            /*
            $databases = file_get_contents($fname);
            $databases = explode("\n", $databases);
            $is_allowed = (array_search($this->dbname,$databases)>0);
            */
        }
        return $is_allowed;
    }

    //
    // Returns allowed disk quota (for file_uploads and uploaded_tilestacks)
    //
    public function getDiskQuota(){

        $quota = 0;
        $fname = realpath(dirname(__FILE__)."/../../disk_quota_allowances.txt");
        if($fname!==false && file_exists($fname)){
            //ini_set('auto_detect_line_endings', 'true');
            $handle = @fopen($fname, "r");
            while (!feof($handle)) {
                $line = trim(fgets($handle, 100));
                if(strpos($line,$this->dbname)===0){
                    $quota = USystem::getConfigBytes(null, substr($line, strlen($this->dbname)));
                    break;
                }
            }
            fclose($handle);
            /*
            $databases = file_get_contents($fname);
            $databases = explode("\n", $databases);
            $is_allowed = (array_search($this->dbname,$databases)>0);
            */
        }

        if(!isPositiveInt($quota)){
            $quota = 0;
            //$quota = 1073741824; //1GB
        }
        return $quota;
    }

    //
    //  Retuns link for given record id
    //
    public function recordLink($rec_id){

        global $useRewriteRulesForRecordLink;

        $template = '';
        if(preg_match('/(\d+)\/(.+\.tpl)/', $rec_id, $matches)){ //strpos($rec_id, "/") !== false

            //$parts = explode("/", $rec_id);
            $rec_id = intval($matches[1]);
            $template = urldecode($matches[2]);

            // Check that the report exists
            if(empty($template) || !file_exists($this->getSysDir('smarty-templates') . $template)){
                $template = '';// use standard record viewer
            }else{
                $template = urlencode($template); //use smarty
            }
        }

        $use_rewrite = isset($useRewriteRulesForRecordLink) && $useRewriteRulesForRecordLink;

        $base_url = HEURIST_BASE_URL_PRO;

        if(!$use_rewrite){
            $url = empty($template) ? $base_url.'?recID='.$rec_id.'&fmt=html&db='.$this->dbname
                        : $base_url . '?db='.$this->dbname.'&q=ids:'.$rec_id.'&template='.$template;
            return $url;
        }

        if(strpos($base_url, "/HEURIST/") !== false){
            $parts = explode('/', $base_url);
            $base_url = $parts[ count($parts) - 1 ] == 'HEURIST' ? $base_url : str_replace('/HEURIST', '', $base_url);
        }

        $url = empty($template) ? $base_url.$this->dbname.'/view/'.$rec_id
                    : $base_url.$this->dbname.'/tpl/'.$template.'/'.$rec_id;

        return $url;
    }


    //
    // check database version
    // first check version in file lastAdviceSent, version stored in this file valid for 24 hrs
    //
    private function get_last_code_and_db_version($isAlpha=false){

        $version_last_check = 'unknown';
        $need_check_main_server = true;

        $fname = HEURIST_FILESTORE_ROOT."lastAdviceSent.ini";

        $release = ($isAlpha ? 'alpha' : 'stable');

        if (file_exists($fname)){
            //last check and version
            list($date_last_check, $version_last_check, $release_last_check) = explode("|", file_get_contents($fname));

            if($release_last_check && strncmp($release_last_check, $release, strlen($release)) == 0){

                if($date_last_check && strtotime($date_last_check)){

                        $days =intval((time()-strtotime($date_last_check))/(3600*24));//days since last check

                        if(intval($days)<1){
                            $need_check_main_server = false;
                        }
                }
            }
        }//file exitst

        if($need_check_main_server){

            $rawdata = null;

            //send request to main server at HEURIST_INDEX_BASE_URL
            // HEURIST_INDEX_DATABASE is the refernece standard for current database version
            // Maybe this should be changed to Heurist_Sandpit?. Note: sandpit no longer needed, or used, from late 2015

            if(strpos(strtolower(HEURIST_INDEX_BASE_URL), strtolower(HEURIST_SERVER_URL))===0){ //same domain

                $mysql_indexdb = mysql__connection(HEURIST_DBSERVER_NAME, ADMIN_DBUSERNAME, ADMIN_DBUSERPSWD, HEURIST_DB_PORT);
                if ( !is_array($mysql_indexdb) && mysql__usedatabase($mysql_indexdb, HEURIST_INDEX_DATABASE)==true){

                    $system_settings = getSysValues($mysql_indexdb);
                    if(is_array($system_settings)){

                        $db_version = $system_settings['sys_dbVersion'].'.'
                                .$system_settings['sys_dbSubVersion'].'.'
                                .$system_settings['sys_dbSubSubVersion'];

                        $rawdata = HEURIST_VERSION."|".$db_version;
                    }

                }

            }else{
                $url = ($isAlpha
                        ? HEURIST_MAIN_SERVER . '/h6-alpha/'
                        : HEURIST_INDEX_BASE_URL)
                        . "admin/setup/dbproperties/getCurrentVersion.php?db=".HEURIST_INDEX_DATABASE."&check=1";
                $rawdata = loadRemoteURLContentSpecial($url);//it returns HEURIST_VERSION."|".HEURIST_DBVERSION
            }

            if($rawdata){
                $current_version = explode("|", $rawdata);

                if (!empty($current_version))
                {
                    $curver = explode(".", $current_version[0]);
                    if( count($curver)>=2
                        && intval($curver[0]) > 0
                        && is_numeric($curver[1])
                        && intval($curver[1])>=0 )
                    {
                        $version_last_check = $current_version[0];
                    }
                }
            }

            $version_in_session = date("Y-m-d").'|'.$version_last_check.'|'.$release;
            fileSave($version_in_session, $fname);//save last version
        }

        return $version_last_check;
    }

    //
    // returns true if password is wrong
    //
    public function verifyActionPassword($password_entered, $password_to_compare, $min_length=6)
    {

        $is_NOT_allowed = true;

        if(!isEmptyStr($password_entered)) {
            $pw = $password_entered;

            // Password in configIni.php must be at least $min_length characters
            if($password_to_compare!=null && strlen(@$password_to_compare) > $min_length) {
                $comparison = strcmp($pw, $password_to_compare);// Check password
                if($comparison == 0) { // Correct password
                    $is_NOT_allowed = false;
                }else{
                    // Invalid password
                    $this->addError(HEURIST_ACTION_BLOCKED, 'Password is incorrect');
                }
            }else{
                $this->addError(HEURIST_ACTION_BLOCKED,
                    'This action is not allowed unless a challenge password is set - please consult system administrator');
            }
        }else{
            //password not defined
            $this->addError(HEURIST_ACTION_BLOCKED, 'Password is missing');
        }

        return $is_NOT_allowed;
    }

    //
    // Define response header. For embed mode (see websiteRecord) it sets allowed
    // origin domain to allow proper execution of heurist scripts from third-party
    // servers. This feature is disabled as a risky approach and possible issue
    // to support the code on third-party servers.
    //
    public function setResponseHeader($content_type=null){

        /*  remove this remark to enable embedding our code to third-partys server
$allowed = array(HEURIST_MAIN_SERVER, 'https://epigraphia.efeo.fr', 'https://november1918.adelaide.edu.au');//disabled
        if(isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed, true) === true){
            header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Content-Type');
        }
        */

        if(!$content_type){
            header(CTYPE_JSON);
        }else{
            header('Content-type: '.$content_type);
        }
    }

    //
    //
    //
    private function _executeScriptOncePerDay(){

            $now = $this->getNow();
            $flag_file = HEURIST_FILESTORE_ROOT.'flag_'.$now->format('Y-m-d');

            if(file_exists($flag_file)){
                return;
            }else{
                file_put_contents($flag_file,'1');

                //remove flag files for previous days
                for($i=1;$i<10;$i++){
                    $d = $this->getNow();
                    $yesterday = $d->sub(new \DateInterval('P'.sprintf('%02d', $i).'D'));
                    $arc_flagfile = HEURIST_FILESTORE_ROOT.'flag_'.$yesterday->format('Y-m-d');
                    //if yesterday log file exists
                    if(file_exists($arc_flagfile)){
                        unlink($arc_flagfile);
                    }
                }

                //add functions for other daily tasks
                $this->_sendDailyErrorReport();
                $this->_heuristVersionCheck();// Check if different local and server code versions are different
                $this->_getDeeplLanguages();// Get list of allowed target languages from Deepl API
            }
    }

    public function getNow(){
        return new \DateTime('now', new \DateTimeZone('UTC'));
    }

    //
    //
    //
    private function _sendDailyErrorReport(){

            $now = $this->getNow();
            $root_folder = HEURIST_FILESTORE_ROOT;
            $curr_logfile = 'errors_'.$now->format('Y-m-d').'.log';
            $archiveFolder = $root_folder."AAA_LOGS/";
            $logs_to_be_emailed = array();
            $y1 = null;
            $y2 = null;

            //1. check if log files for previous 30 days exist
            for($i=1;$i<31;$i++){
                $now = $this->getNow();
                $yesterday = $now->sub(new \DateInterval('P'.sprintf('%02d', $i).'D'));
                $arc_logfile = 'errors_'.$yesterday->format('Y-m-d').'.log';
                //if yesterday log file exists
                if(file_exists($root_folder.$arc_logfile)){
                    //2. copy to log archive folder
                    fileCopy($root_folder.$arc_logfile, $archiveFolder.$arc_logfile);
                    unlink($root_folder.$arc_logfile);

                    $logs_to_be_emailed[] = $archiveFolder.$arc_logfile;

                    $y2 = $yesterday->format('Y-m-d');
                    if($y1==null) {$y1 = $y2;}
                }
            }

            if($this->send_email_on_error==1 && !empty($logs_to_be_emailed)){

                $msgTitle = 'Error report '.HEURIST_SERVER_NAME.' for '.$y1.($y2==$y1?'':(' ~ '.$y2));
                $msg = $msgTitle;
                foreach($logs_to_be_emailed as $log_file){
                    $msg = $msg.'<br>'.file_get_contents($log_file);
                }
                //'Bug reporter',
                sendEmail(HEURIST_MAIL_TO_BUG, $msgTitle, $msg, true);
            }


    }

    //
    // Send email to system admin about available Heurist updates, daily tasks
    //
    private function _heuristVersionCheck(){

        $local_ver = HEURIST_VERSION; // installed heurist version

        $server_ver = $this->get_last_code_and_db_version($this->version_release == "alpha" ? true : false);

		if($server_ver == "unknown"){
			error_log("Unable to retrieve Heurist server version, this maybe due to the main server being un-available. If this problem persists please contact the Heurist team.");
			return;
		}

        $local_parts = explode('.', $local_ver);
        $server_parts = explode('.', $server_ver);

        for($i = 0; $i < count($server_parts); $i++){

            if($server_parts[$i] == $local_parts[$i]){
                continue;
            }elseif($server_parts[$i] > $local_parts[$i]){ // main release is newer than installed version, send email

                $title = "Heurist version " . htmlspecialchars($local_ver)
                 . " at " . HEURIST_BASE_URL . " is behind Heurist home server";

                $msg = 'Heurist on the referenced server is running version '
                    . " $local_ver which can be upgraded to the newer $server_ver<br><br>"
                . 'Please check for an update package at <a href="https://heuristnetwork.org/installation/">https://heuristnetwork.org/installation/</a><br><br>'
                . 'Update packages reflect the alpha version and install in parallel with existing versions'
                . ' so you may test them before full adoption. We recommend use of the alpha package'
                . ' by any confident user, as they bring bug-fixes, cosmetic improvements and new'
                . ' features. They are safe to use and we will respond repidly to any reported bugs.';

                //Update notification
                sendEmail(HEURIST_MAIL_TO_ADMIN, $title, $msg, true);

                return;

            }else{ // main release is less than installed version, maybe missed alpha or developemental version
                return;
            }
        }
    }

    /**
     * Get and save list of available languages from Deepl API
     * Saved to FILESTORE_ROOT/DEEPL_languages.json
     */
    private function _getDeeplLanguages(){

        global $accessToken_DeepLAPI;
        if(empty($accessToken_DeepLAPI)){
            return array();
        }

        $target_url = 'https://api-free.deepl.com/v2/languages?type=target';

        $language_file = HEURIST_FILESTORE_ROOT . 'DEEPL_languages.json';

        $target_res = loadRemoteURLContentWithRange($target_url, false, true, 60, array('Authorization: DeepL-Auth-Key ' . $accessToken_DeepLAPI));

        $target_languages = array();

        if(!empty($target_res)){

            $target_res = json_decode($target_res, TRUE);
            $target_res = json_last_error() !== JSON_ERROR_NONE ? array() : $target_res;

            // Extra processing needed, some target languages have multiple versions; e.g. ENG-GB and ENG-US
            foreach ($target_res as $lang) {

                $lang_name = $lang['language'];
                if(strpos($lang_name, '-') !== false){
                    $lang_name = explode('-', $lang_name)[0];
                }

                if(array_search($lang_name, $target_languages) !== false){
                    continue;
                }

                array_push($target_languages, $lang_name);
            }
        }

        fileSave(json_encode($target_languages), $language_file);
    }

    /**
    * Remove database definition cache file
    */
    public function cleanDefCache(){
            fileDelete($this->getFileStoreRootFolder().$this->dbname().'/entity/db.json');//old name
            fileDelete($this->getFileStoreRootFolder().$this->dbname().'/entity/dbdef_cache.json');
    }

    /**
    * Validates that db defintions cache is up to date with client side version
    *
    * @param mixed $timestamp - client side last update timestamp
    * @return {false|true} - returns false if client side cache is older
    public function checkDefCache($timestamp){
        $res = true;
        if($timestamp>0){
            $dbdef_cache = $this->getFileStoreRootFolder().$this->dbname().'/entity/dbdef_cache.json';
            if(file_exists($dbdef_cache)){
                $file_time = filemtime($dbdef_cache);
                if($file_time - $timestamp > 10){
                    $res = false;
                }
            }else{
                //cache file does not exist - need to be updated
                $res = false;
            }
        }
        return $res;
    }
    */

    /**
     * Retrieve saved settings for current database from settings/
     *
     * @param string $setting_name - setting's name, matches a key in database_settings
     *
     * @return array - returns either error message, or array of settings
     */
    public function getDatabaseSetting($setting_name){

        if(!defined('HEURIST_FILESTORE_DIR')){
            return false;
        }

        if(!array_key_exists($setting_name, $this->database_settings)){
            return $this->addError(HEURIST_INVALID_REQUEST, 'Invalid settings requested');
        }

        $setting_file = HEURIST_FILESTORE_DIR . "settings/" . $this->database_settings[$setting_name];

        if(!file_exists($setting_file)){
            return array();
        }

        $settings = file_get_contents($setting_file);
        if($settings === false){
            return $this->addError(HEURIST_ERROR, "An error occurred while attempting to read database settings for $setting_name");
        }elseif(empty($settings)){
            return array();
        }

        $settings = json_decode($settings, TRUE);
        if(json_last_error() !== JSON_ERROR_NONE){
            return $this->addError(HEURIST_ERROR, "An error occurred while decoding the existing database settings for $setting_name");
        }

        return $settings !== null ? $settings : array();
    }

    /**
     * Save settings for current database in settings/
     *
     * @param string $setting_name - setting's name, matches a key in database_settings
     * @param array $settings - settings in JSON format
     * @param int $replace_settings - how to handle the saving, 0 - completely replace; 1 - merge and replace existing; 2 - merge and retain existing
     *
     * @return true|array - returns true on success, returns array on error
     */
    public function setDatabaseSetting($setting_name, $settings, $replace_settings = 0){

        if(!defined('HEURIST_FILESTORE_DIR')){
            return false;
        }

        if(!array_key_exists($setting_name, $this->database_settings)){
            return $this->addError(HEURIST_INVALID_REQUEST, 'Invalid settings requested');
        }

        if(!is_array($settings)){
            return $this->addError(HEURIST_INVALID_REQUEST, 'Invalid settings format');
        }

        $setting_file = HEURIST_FILESTORE_DIR . "settings/" . $this->database_settings[$setting_name];
        $existing_settings = '';

        if(file_exists($setting_file) && $replace_settings != 0){
            $existing_settings = file_get_contents($setting_file);
        }

        if(!isEmptyStr($existing_settings)){

            $existing_settings = json_decode($existing_settings, true);
            if(json_last_error() !== JSON_ERROR_NONE || !is_array($existing_settings)){
                return $this->addError(HEURIST_ERROR, "An error occurred with retrieving the existing database settings for $setting_name");
            }

            if($replace_settings == 1){
                $existing_settings = array_replace_recursive($existing_settings, $settings);
            }else{
                $existing_settings = array_replace_recursive($settings, $existing_settings);
            }
        }else{
            $existing_settings = $settings;
        }

        $final_settings = json_encode($existing_settings);
        if(json_last_error() !== JSON_ERROR_NONE){
            return $this->addError(HEURIST_ACTION_BLOCKED, 'JSON ENCODE ERROR => ' . json_last_error_msg());
        }

        $res = fileSave($final_settings, $setting_file);

        if($res == 0){
            return $this->addError(HEURIST_ERROR, "An error occurred while attempting to save database settings for $setting_name");
        }

        return true;
    }
}
?>