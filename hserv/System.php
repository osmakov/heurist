<?php
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

namespace hserv;
use hserv\structure\ConceptCode;
use hserv\utilities\USystem;
use hserv\utilities\USanitize;
use hserv\SystemSettings;


require_once dirname(__FILE__).'/structure/dbsUsersGroups.php';
require_once dirname(__FILE__).'/structure/import/dbsImport.php';

set_error_handler('bootErrorHandler');   //see const.php

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
    private $dbnameFull = null;
    private $dbname = null;

    private $errors = array();

    private $isInited = false;

    private $currentUser = null;

    //do not check session folder, loads only basic user info
    private $needFullSessionCheck = false;

    //instance of SystemSettings class
    public $settings;

    /*

    init
    setDbnameFull
    init_db_connection - connect to server and select database (move to db_utils?)
    initPathConstants  - set path constants
    loginVerify  - load user info from session or reloads from database

    login
    loginVerify



    */

    public function __construct( $full_check=false ) {

        $this->needFullSessionCheck = $full_check;

        $this->settings = new SystemSettings($this);
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

        $this->isInited = false;
        
        if( !$this->setDbnameFull($db, $dbrequired) ){
            return false;
        }

        $res = mysql__init($this->dbnameFull);
        if (is_a($res, 'mysqli')){
            //connection OK
            $this->mysqli = $res;
        }else{
            //connection failed
            $this->addErrorArr($res);
            return false;
        }


        if($this->dbnameFull && !defined('HEURIST_DBNAME')){
            //init once for first system - preferable use methods
            define('HEURIST_DBNAME', $this->dbname);
            define('HEURIST_DBNAME_FULL', $this->dbnameFull);
        }

        if(!$init_session_and_constants){
            $this->isInited = true;
        }elseif($this->startMySession( $this->needFullSessionCheck )
            && $this->dbnameFull
            && $this->initPathConstants()){

            if($this->needFullSessionCheck){
                USystem::executeScriptOncePerDay();
            }

            $this->loginVerify( false );//load user info from session on system init
            if($this->getUserId()>0){
                //set current user for stored procedures (log purposes)
                $this->mysqli->query('set @logged_in_user_id = '.intval($this->getUserId()));
            }

            //ONLY_FULL_GROUP_BY,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO
            $this->mysqli->query('SET GLOBAL sql_mode = \'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION\'');

            $this->isInited = true;
        }

        return $this->isInited;

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
                $this->defineTermLocalMagic($str, $id[1], $id[0]);
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
    public function getConstant($const_name, $def=null) 
    {
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
                $this->defineTermLocalMagic($const_name, $trmDefines[$const_name][1], $trmDefines[$const_name][0]);
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

        foreach($config as $cfg){

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
            define($defString, $id);
        }
    }


    /**
    * lookup local id for a given rectype concept id pair
    * @global    type description of global variable usage in a function
    * @staticvar array [$rtyIDs] lookup array of local ids
    * @param     int [$rtID] origin rectype id
    * @param     int [$dbID] origin database id (default to 2 which is reserved for coreDefinition)
    * @return    int local rectype ID or null if not found
    */
    private function rectypeLocalIDLookup($rtID, $dbID = 2, $reset=false) {
        static $rtyIDs;

        if (!$rtyIDs || $reset) {
            $res = $this->mysqli->query('select rty_ID as localID,
            rty_OriginatingDBID as dbID, rty_IDInOriginatingDB as id from defRecTypes order by dbID');
            if (!$res) {
                $this->addError(HEURIST_DB_ERROR, 'Unable to build internal record-type lookup table', $this->mysqli->error);
                exit;
            }

            $regID = $this->settings->get('sys_dbRegisteredID');

            $rtyIDs = array();
            while ($row = $res->fetch_assoc()) {

                if( !isPositiveInt($row['dbID']) && $regID>0){
                    $row['dbID'] = $regID;
                    $row['id'] = $row['localID'];
                }

                if (!@$rtyIDs[$row['dbID']]) {
                    $rtyIDs[$row['dbID']] = array();
                }
                $rtyIDs[$row['dbID']][$row['id']] = $row['localID'];
            }
        }
        return @$rtyIDs[$dbID][$rtID] ? $rtyIDs[$dbID][$rtID] : null;
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
            define($defString, $id);
        }
    }


    /**
    * lookup local id for a given detailtype concept id pair
    * @global    type description of global variable usage in a function
    * @staticvar array [$rtyIDs] lookup array of local ids
    * @param     int [$dtID] origin detailtype id
    * @param     int [$dbID] origin database id (default to 2 which is reserved for coreDefinition)
    * @return    int local detailtype ID or null if not found
    */
    private function detailtypeLocalIDLookup($dtID, $dbID = 2, $reset=false) {
        static $dtyIDs;

        if (!$dtyIDs || $reset) {
            $res = $this->mysqli->query('select dty_ID as localID,dty_OriginatingDBID as dbID,dty_IDInOriginatingDB as id from defDetailTypes order by dbID');
            if (!$res) {
                echo "Unable to build internal field-type lookup table. Please ".CONTACT_SYSADMIN
                ." for assistance. MySQL error: " . $this->mysqli->error;
                exit;
            }

            $regID = $this->settings->get('sys_dbRegisteredID');

            $dtyIDs = array();
            while ($row = $res->fetch_assoc()) {

                if( !isPositiveInt($row['dbID']) && $regID>0){
                    $row['dbID'] = $regID;
                    $row['id'] = $row['localID'];
                }

                if (!@$dtyIDs[$row['dbID']]) {
                    $dtyIDs[$row['dbID']] = array();
                }
                $dtyIDs[$row['dbID']][$row['id']] = $row['localID'];
            }
        }
        return @$dtyIDs[$dbID][$dtID] ? $dtyIDs[$dbID][$dtID] : null;
    }

    /**
    * bind Magic Number Constants to their local id
    * @param    string [$defString] define string
    * @param    int [$trmID] origin term id
    * @param    int [$dbID] origin database id
    */
    private function defineTermLocalMagic($defString, $trmID, $dbID) {

        $id = ConceptCode::getTermLocalID($dbID.'-'.$trmID);
        if ($id) {
            define($defString, $id);
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
            $folders['blurredimagescache'] = array(null,'(for blurred due to visibility settings)', true, false);
            $folders['webimagecache'] = array(null,'(for cached web images)', true, false);
        }



        return $folders;
    }

    //
    // $is_for_backup - 0 no, 1 - archive backup, 2 - delete backup
    // returns ar
    //

    /**
    * Returns array of ALL database folders
    *
    * @param mixed $database_name - if null for current database
    */
    public function getSystemFolders($database_name=null){

        $folders = $this->getArrayOfSystemFolders();

        $system_folders = array();

        $dbfolder = $this->getSysDir(null, $database_name); //root db folder

        foreach ($folders as $folder_name=>$folder){
            $folder_name = $dbfolder.$folder_name;
            $folder_name = str_replace('\\', '/', $folder_name);
            array_push($system_folders, $folder_name.'/');
        }//for

        return $system_folders;
    }

    /**
    * Returns root upload folder of specified ($folder_name) subfolder
    *
    * @param mixed $folder_name - subfolder in database root upload dir
    * @param mixed $database_name
    */
    public function getSysDir($folder_name=null, $database_name=null){
        return $this->getSysFolderRes('path', $folder_name, $database_name);
    }

    /**
    * Returns root upload URL of specified ($folder_name) suburl
    *
    * @param mixed $folder_name - subfolder in database root upload dir
    * @param mixed $database_name
    */
    public function getSysUrl($folder_name=null, $database_name=null){
        return $this->getSysFolderRes('url', $folder_name, $database_name);
    }

    /**
    * Get system folder resouce - path or url
    *
    * @param mixed $type - path ot url
    * @param mixed $folder_name - subfolder of database upload folder
    * @param mixed $database_name - if null it takes current database
    */
    private function getSysFolderRes($type, $folder_name=null, $database_name=null){
        global $defaultRootFileUploadURL;

        if($type=='url'){
            $db_root = $defaultRootFileUploadURL;
        }else{
            $db_root = defined('HEURIST_FILESTORE_ROOT')
                            ?HEURIST_FILESTORE_ROOT
                            :$this->getFileStoreRootFolder();
        }

        $database_name = $database_name??$this->dbname;

        if(preg_match('/[^A-Za-z0-9_\$]/', $database_name)){
            return null; //invalid database name
        }

        $dbres = $db_root.$database_name.'/';

        if($folder_name!=null){

            $dir = USanitize::sanitizePath($folder_name);
            if( substr($dir, -1, 1) != '/' )  {
                $dir .= '/';
            }

            $dbres .= $dir;
        }

        return $dbres;
    }

    /**
    * Validates system config setting $defaultRootFileUploadPath
    * Check/creates system subfolders
    *
    * @param mixed $dbname - database shortname (without prefix)
    */
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

    /**
    * Returns true if system is inited ccorretly and db connection is established
    */
    public function isInited(){
        return $this->isInited;
    }

    /**
    * Get database connection object
    */
    public function getMysqli(){
        return $this->mysqli;
    }

    public function setMysqli($mysqli){
        $this->mysqli = $mysqli;
    }

    /**
    * Get full name of database
    */
    public function dbnameFull(){
        return $this->dbnameFull;
    }

    public function dbname(){
        return $this->dbname;
    }

    /**
    * set dbname and dbnameFull properties
    *
    * @param mixed $db
    */
    public function setDbnameFull($db, $dbrequired=true){

        $error = mysql__check_dbname($db);

        if($error==null && preg_match('/[A-Za-z0-9_\$]/', $db)){ //additional validatate database name for sonarcloud
            list($this->dbnameFull, $this->dbname ) = mysql__get_names( $db );
        }else{
            $this->dbname = null;
            $this->dbnameFull = null;

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
    public function errorExit( $message, $error_code=null) {

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
    public function errorExitApi( $message=null, $error_code=null, $is_api=true) {

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
    private function treatSeriousError($status, $message, $sysmsg, $title) {

        $now = getNow();
        $curr_logfile = 'errors_'.$now->format('Y-m-d').'.log';

        //3. write error into current error log
        $sTitle = 'db: '.preg_replace(REGEX_EOL, ' ', $this->dbname())
        ."\nerr-type: ".preg_replace(REGEX_EOL, ' ', $status)
        ."\nuser: ".$this->getUserId()
        .' '.@$this->currentUser['ugr_FullName']
        .' <'.@$this->currentUser['ugr_eMail'].'>';

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
            fileAdd($sTitle.'  '.$sMsg, $root_folder.$curr_logfile);
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
            $sysmsg = $this->getUserId();
        }

        if($status!=HEURIST_INVALID_REQUEST && $status!=HEURIST_NOT_FOUND &&
        $status!=HEURIST_REQUEST_DENIED && $status!=HEURIST_ACTION_BLOCKED){
            $this->treatSeriousError($status, $message, $sysmsg, $title);
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
        return $this->errors['message'] ?? '';
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

        if($this->hasAccess())
        {
            $query = 'select count(*) from sysDashboard where dsh_Enabled="y"';
            if($db_total_records<1){
                $query = $query.'AND dsh_ShowIfNoRecords="y"';
            }
            $db_has_active_dashboard = mysql__select_value($this->mysqli, $query);
            $db_has_active_dashboard = ($db_has_active_dashboard>0)?$db_has_active_dashboard:0;

            $curr_user_id = $this->getUserId();
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
                        "referenceServerURL"=>HEURIST_INDEX_BASE_URL,
                        'database_prefix'=>HEURIST_DB_PREFIX),
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
            $dbrecent = USystem::sessionRecentDatabases($this->currentUser);

            //retrieve lastest code version (cached in localfile and refreshed from main index server daily)
            $lastCode_VersionOnServer = USystem::getLastCodeAndDbVersion();

            $res = array(
                "currentUser"=>$this->currentUser,
                "sysinfo"=>array(
                    "registration_allowed"=>$this->settings->get('sys_AllowRegistration'), //allow new user registration
                    "db_registeredid"=>$this->settings->get('sys_dbRegisteredID'),
                    "db_managers_groupid"=>($this->settings->get('sys_OwnerGroupID')>0?$this->settings->get('sys_OwnerGroupID'):1),
                    "help"=>HEURIST_HELP,

                    //code version from configIni.php
                    "version"=>HEURIST_VERSION,
                    "version_new"=>$lastCode_VersionOnServer, //version on main index database server
                    //db version
                    "db_version"=>getDbVersion($this->getMysqli()),
                    "db_version_req"=>HEURIST_MIN_DBVERSION,

                    "dbowner_name"=>@$dbowner['ugr_FirstName'].' '.@$dbowner['ugr_LastName'],
                    "dbowner_org"=>@$dbowner['ugr_Organisation'],
                    "dbowner_email"=>@$dbowner['ugr_eMail'],
                    "sysadmin_email"=>HEURIST_MAIL_TO_ADMIN,
                    "db_total_records"=>$this->settings->get('sys_RecordCount'),
                    "db_usergroups"=> user_getAllWorkgroups($this->mysqli), //all groups- to fast retrieve group name
                    "baseURL"=>HEURIST_BASE_URL,
                    'baseURL_pro'=>HEURIST_BASE_URL_PRO,
                    'database_prefix'=>HEURIST_DB_PREFIX,
                    //"serverURL"=>HEURIST_SERVER_URL,
                    "referenceServerURL"=>HEURIST_INDEX_BASE_URL,
                    "dbconst"=>$this->getLocalConstants( $include_reccount_and_dashboard_count ), //some record and detail types constants with local values specific for current db
                    "service_config"=>$this->settings->get('sys_ExternalReferenceLookups'), //get 3d part web service mappings
                    "services_list"=>$this->getWebServiceConfigs(), //get list of all implemented lookup services
                    "dbrecent"=>$dbrecent,  //!!!!!!! need to store in preferences
                    "cms_allowed"=> $allowCMSCreation??1,

                    'max_post_size'=>USystem::getConfigBytes('post_max_size'),
                    'max_file_size'=>USystem::getConfigBytes('upload_max_filesize'),
                    'is_file_multipart_upload'=>($this->settings->getDiskQuota()>0)?1:0,
                    'host_logo'=>$host_logo,
                    'host_url'=>$host_url,
                    
                    'mediaFolder'=>$this->settings->get('sys_MediaFolders'),
                    'media_ext_index'=>$this->settings->get('sys_MediaExtensions'), //user define list - what is allowed to index

                    'media_ext'=>HEURIST_ALLOWED_EXT, //default list - what is allowed to upload
                    'rty_as_place'=>$this->settings->get('sys_TreatAsPlaceRefForMapping'),

                    'need_encode'=>$needEncodeRecordDetails,

                    'custom_js_allowed'=>$this->settings->isJavaScriptAllowed(),

                    'common_languages'=>$common_languages,

                    'saml_service_provides'=>$saml_service_provides,
                    'hideStandardLogin' => $hideStandardLogin,

                    'nakala_api_key'=>$this->settings->get('sys_NakalaKey'),

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
        return $this->currentUser;
    }

    /**
    * Set current user info
    *
    * @param mixed $user
    */
    public function setCurrentUser($user){
        $this->currentUser = $user;
    }



    /**
    * Get if of current user, if not looged in returns zero
    *
    */
    public function getUserId(){
        return $this->currentUser? intval($this->currentUser['ugr_ID']) :0;
    }



    /**
    * Returns array of ID of all groups for current user plus current user ID
    * $level - admin/memeber
    */
    public function getUserGroupIds($level=null, $refresh=false){

        $ugrID = $this->getUserId();

        if($ugrID>0){
            $groups = @$this->currentUser['ugr_Groups'];
            if($refresh || !is_array($groups)){
                $groups = $this->currentUser['ugr_Groups'] = user_getWorkgroups($this->mysqli, $ugrID);
            }
            if($level!=null){
                $groups = array();
                foreach($this->currentUser['ugr_Groups'] as $grpid=>$lvl){
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
    public function isMember($ugs){

        if($ugs==0 || isEmptyArray($ugs)){
            return true;
        }

        $current_user_grps = $this->getUserGroupIds();
        $ugs = prepareIds($ugs, true);//include zero
        foreach ($ugs as $ug){
            if ($ug==0 || (is_array($current_user_grps) && in_array($ug, $current_user_grps)) ){
                return true;
            }
        }
        return false;
    }

    /**
    * Verifies is current user is database owner
    * used to manage any recThereadedComments, recUploadedFiles, Reminders, Bookmarks, UsrTags
    * otherwise only direct owners can modify them or members of workgroup tags
    */
    public function isDbOwner(){
        return $this->getUserId()==2;
    }

    /**
    * id db owner or admin of database managers
    *
    * @param mixed $ugrID
    * @return mixed
    */
    public function isAdmin(){
       return $this->getUserId()>0 &&
            ($this->getUserId()==2 ||
                $this->hasAccess( $this->settings->get('sys_OwnerGroupID') ) );
    }

    public function isGuestUser(){
        $user = $this->currentUser;
        return $user!=null && @$user['ugr_Permissions']['guest_user'];
    }


    /**
    * check if current user is system administrator
    */
    public function isSystemAdmin(){
        if ($this->getUserId()>0){
            $user = user_getById($this->mysqli, $this->getUserId());
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
    public function hasAccess( $requiredLevel=null ) {

        $ugrID = $this->getUserId();

        if(!$requiredLevel || $requiredLevel<1){
            return $ugrID>0;//just logged in
        }

        if ($requiredLevel==$ugrID ||   //iself
        2==$ugrID)   //db owner
        {
            return true;
        }else{
            //@$this->current_User['ugr_Groups'][$requiredLevel]=='admin');//admin of given group
            $current_user_grps = $this->getUserGroupIds('admin');
            return is_array($current_user_grps) && in_array($requiredLevel, $current_user_grps);
        }
    }

    /**
    * Restore session by cookie id, or start new session
    * Refreshes cookie
    */
    private function startMySession($check_session_folder=true){

        if(headers_sent()) {return true;}

        //verify that session folder is writable
        if($this->needFullSessionCheck && $check_session_folder && !USystem::sessionCheckFolder()){
            $this->addError(HEURIST_SYSTEM_FATAL, "The sessions folder has become inaccessible. This is a minor, but annoying, problem for which we apologise. An email has been sent to your system administrator asking them to fix it - this may take up to a day, depending on time differences. Please try again later.");
            return false;
        }

        if (session_status() != PHP_SESSION_ACTIVE) {

            session_name('heurist-sessionid');//set session name
            session_cache_limiter('none');

            @session_start();
        }

        $result = false;

        if (session_status() == PHP_SESSION_ACTIVE)
        {
            if (@$_SESSION[$this->dbnameFull]['keepalive'] && !USystem::sessionUpdateCookies())
            {
                USanitize::errorLog('CANNOT UPDATE COOKIE '.session_id().'   '.$this->dbnameFull);
            }
            $result = true;
        }

        return $result;
    }


    /*
    * Verifies session only (without database connection and system initialization)
    * return current user id or false
    */
    public function verifyCredentials($db){

        if( $this->setDbnameFull($db) && $this->startMySession(false) ){
            return @$_SESSION[$this->dbnameFull]['ugr_ID'];
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
    */
    private function loginVerify( $user, $is_guest_allowed=false ){

        $reload_user_from_db = false;

        if( is_array($user) ){  //NOT USED user info already found (see login) - need reset session
            $reload_user_from_db = true;
            $userID = $user['ugr_ID'];
        }else{

            $reload_user_from_db = ($user===true);//reload user unconditionally

            $userID = @$_SESSION[$this->dbnameFull]['ugr_ID'];
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

        if(@$_SESSION[$this->dbnameFull]['need_refresh']) {
            unset($_SESSION[$this->dbnameFull]['need_refresh']);
        }

        $fname = HEURIST_FILESTORE_DIR.basename($userID);
        if(file_exists($fname)){  //user info was updated by someone else
            unlink($fname);
            //marker for usr_info.verify_credentials to be sure that client side is also up to date
            if($user!==true) {$_SESSION[$this->dbnameFull]['need_refresh'] = 1;}
            $reload_user_from_db = true;
        }

        if($reload_user_from_db){ //from database

            if(!$this->updateSessionForUser( $userID )){
                return false; //not logged in
            }

            if($is_guest_allowed && @$_SESSION[$this->dbnameFull]['ugr_Permissions']['disabled']){
                $_SESSION[$this->dbnameFull]['ugr_Permissions']['disabled'] = false;
                $_SESSION[$this->dbnameFull]['ugr_Permissions']['guest_user'] = true;
            }

            //always restore from db
            $this->currentUser = ['ugr_ID' => intval($userID)]; // set user ID to avoid resetting preferences
            $_SESSION[$this->dbnameFull]['ugr_Preferences'] = user_getPreferences( $this );
        }//$reload_user_from_db from db

        $this->currentUser = array('ugr_ID'=>intval($userID),
            'ugr_Name'        => @$_SESSION[$this->dbnameFull]['ugr_Name'],
            'ugr_FullName'    => $_SESSION[$this->dbnameFull]['ugr_FullName'],
            'ugr_Groups'      => $_SESSION[$this->dbnameFull]['ugr_Groups'],
            'ugr_Permissions' => $_SESSION[$this->dbnameFull]['ugr_Permissions']);

        $this->currentUser['ugr_Preferences'] = $_SESSION[$this->dbnameFull]['ugr_Preferences'];

        //remove credentials for remote repositories
        if(@$this->currentUser['ugr_Preferences']['externalRepositories']){
            $this->currentUser['ugr_Preferences']['externalRepositories'] = null;
            unset($this->currentUser['ugr_Preferences']['externalRepositories']);
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

        $_SESSION[$this->dbnameFull]['ugr_ID'] = $userID;
        $_SESSION[$this->dbnameFull]['ugr_Groups']   = user_getWorkgroups( $this->mysqli, $userID );
        $_SESSION[$this->dbnameFull]['ugr_Name']     = $user['ugr_Name'];
        $_SESSION[$this->dbnameFull]['ugr_FullName'] = $user['ugr_FirstName'] . ' ' . $user['ugr_LastName'];
        $_SESSION[$this->dbnameFull]['ugr_Enabled']  = $user['ugr_Enabled'];

        $is_disabled = $user['ugr_Enabled'] == 'n';
        $_SESSION[$this->dbnameFull]['ugr_Permissions'] = array(
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
                        if( strcasecmp($this->dbnameFull, $ldb2)==0 ){
                            //yes database is mutually linked
                            //4. find user email in linked database
                            $userEmail_in_linkedDB = mysql__select_value($this->mysqli, 'select ugr_eMail from '
                                .$ldb.'.sysUGrps where ugr_ID='.$userID_in_linkedDB);

                            //5. find user by email in this database
                            if($userEmail_in_linkedDB){
                                $user = user_getByField($this->getMysqli(), 'ugr_eMail', $userEmail_in_linkedDB);
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
    * @return  true if login is success
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

        $lifetime = 0;
        if($session_type == 'shared'){
            $lifetime = time() + 24*60*60;     //day
        }elseif($session_type == 'remember') {
            $lifetime = time() + 30*24*60*60;  //30 days
            $_SESSION[$this->dbnameFull]['keepalive'] = true; //refresh time on next entry
        }

        USystem::sessionUpdateCookies($lifetime);

        $_SESSION[$this->dbnameFull]['ugr_ID'] = $userID;

        //update login time in database
        user_updateLoginTime($this->mysqli, $userID);
    }


    /**
    * Clears cookie and destroy session and current_User info
    */
    public function doLogout(){

        $this->startMySession(false);

        unset($_SESSION[$this->dbnameFull]['ugr_ID']);
        unset($_SESSION[$this->dbnameFull]['ugr_Name']);
        unset($_SESSION[$this->dbnameFull]['ugr_FullName']);
        if(@$_SESSION[$this->dbnameFull]['ugr_Groups']) {unset($_SESSION[$this->dbnameFull]['ugr_Groups']);}
        if(@$_SESSION[$this->dbnameFull]['ugr_Permissions']) {unset($_SESSION[$this->dbnameFull]['ugr_Permissions']);}
        if(@$_SESSION[$this->dbnameFull]['ugr_GuestUser']!=null) {unset($_SESSION[$this->dbnameFull]['ugr_GuestUser']);}

        // clear
        // even if user is logged to different databases he has the only session per browser
        // it means logout exits all databases
        $is_https = (@$_SERVER['HTTPS']!=null && $_SERVER['HTTPS']!='');

        setcookie('heurist-sessionid', '', time() - 3600, '/', '', $is_https, true);//logout
        $this->currentUser = null;
        session_destroy();

        session_write_close();
        return true;
    }

    //
    // Returns individual property from SESSION
    // To load the entire set of preferences from database use user_getPreferences
    //
    public function userGetPreference($property, $def=null){

        $res = @$_SESSION[$this->dbnameFull]["ugr_Preferences"][$property];

        // POSSIBLE redundancy: this duplicates same in hapi.js
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
    public function userLogActivity($action, $suplementary = '', $user_id=null){

        if($user_id==null){
            $this->loginVerify( false );
            $user_id = $this->getUserId();
        }

        $now = new \DateTime();

        $user_agent = USystem::getUserAgent();

        $addr_IPv4 = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)??'Unknown';

        $info = array($user_id, $action, $now->format(DATE_8601), $user_agent['os'], $user_agent['browser'], $addr_IPv4);

        if(is_array($suplementary)){
            $info = array_merge($info, $suplementary);
        }else{
            array_push($info, $suplementary);
        }

        file_put_contents ( $this->getSysDir().'userInteraction.log' , implode(',', $info)."\n", FILE_APPEND );
    }

    /**
    * Returns link to given record. Either to standard record view html renderer
    * or to smarty template
    *
    * It returns link with url parametrers
    * of as url path (if global $useRewriteRulesForRecordLink is true)
    * databasename/tpl/templatename/id  or databasename/view/id
    *
    * @param mixed $rec_id
    */
    public function recordLink($rec_id){

        global $useRewriteRulesForRecordLink;

        $template = '';
        if(preg_match('/(\d+)\/(.+\.tpl)/', $rec_id, $matches)){ //strpos($rec_id, "/") !== false

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
            return empty($template) ? $base_url.'?recID='.$rec_id.'&fmt=html&db='.$this->dbname
            : $base_url . '?db='.$this->dbname.'&q=ids:'.$rec_id.'&template='.$template;
        }

        if(strpos($base_url, "/HEURIST/") !== false){
            $parts = explode('/', $base_url);
            $base_url = $parts[ count($parts) - 1 ] == 'HEURIST' ? $base_url : str_replace('/HEURIST', '', $base_url);
        }

        return empty($template) ? $base_url.$this->dbname.'/view/'.$rec_id
        : $base_url.$this->dbname.'/tpl/'.$template.'/'.$rec_id;
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


    /**
    * Remove database definition cache file
    */
    public function cleanDefCache(){
        fileDelete($this->getSysDir('entity') . 'db.json');//old name
        fileDelete($this->getSysDir('entity') . 'dbdef_cache.json');
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

}
