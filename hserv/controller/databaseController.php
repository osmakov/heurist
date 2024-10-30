<?php
/**
* databaseController.php
* Interface/Controller for manipulations with database(s)
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
action

List

Clear
Clone
Create
Delete
Rename
Restore

*/
set_time_limit(0);

use hserv\utilities\DbUtils;
use hserv\utilities\DbVerify;
use hserv\utilities\USanitize;

require_once dirname(__FILE__).'/../../autoload.php';

require_once dirname(__FILE__).'/../structure/dbsUsersGroups.php';
require_once dirname(__FILE__).'/../../admin/setup/dboperations/welcomeEmail.php';

define('PARAM_WELCOME','?welcome=1&db=');

$system = new hserv\System();

//sysadmin protection - reset from request to avoid exposure in possible error/log messages
$create_pwd = USanitize::getAdminPwd('create_pwd');
$challenge_pwd = USanitize::getAdminPwd('chpwd');
$sysadmin_pwd = USanitize::getAdminPwd('pwd');

$req_params = USanitize::sanitizeInputArray();

$action = @$req_params['a'];
$locale = @$req_params['locale'];

if($action==null){
    $action = @$req_params['action'];
}

$session_id = intval(@$req_params['session']);

if(!$system->init(@$req_params['db'], ($action!='create'))){ //db required, except create
    //get error and response
    $response = $system->getError();
}else{

   $isNewUserRegistration = ($action=='create' && !$system->has_access());

   if(!($isNewUserRegistration || $system->has_access())){
        $response = $system->addError(HEURIST_REQUEST_DENIED, 'You must be logged in');
        //@todo !!!!!  check for $passwordForDatabaseCreation
   }else{

        $res = false;

        DbUtils::initialize();
        if($session_id>0){
            DbUtils::setSessionId($session_id);//start progress session
        }

        $mysqli =  $system->get_mysqli();

        if($action=='list'){
            //get list of available databases


        }
        elseif($action=='check_newdefs'){

            //check new definitions for current database
            $res = DbUtils::databaseCheckNewDefs();
            if($res===false){
                $res = '';//there are not new defintions
            }

        }
        elseif($action=='create'){

            if( !isset($passwordForDatabaseCreation)
                || $passwordForDatabaseCreation==''
                || !$system->verifyActionPassword($create_pwd, $passwordForDatabaseCreation, 6)){


                //compose database name
                $database_name = __composeDbName($system, $req_params);
                if($database_name!==false){

                    $usr_owner = null;

                    if($isNewUserRegistration)
                    {
                        //check capture
                        $captcha_code_ok = true;
                        $captcha_code = @$req_params['ugr_Captcha'];
                        if (@$_SESSION["captcha_code"] && $_SESSION["captcha_code"] != $captcha_code) {
                            $system->addError(HEURIST_INVALID_REQUEST,
                                'Are you a bot? Please enter the correct answer to the challenge question');
                            $captcha_code_ok = false;
                        }
                        if (@$_SESSION["captcha_code"]){
                            unset($_SESSION["captcha_code"]);
                        }

                        if($captcha_code_ok){
                            unset($req_params['ugr_Captcha']);

                            //get registration form fields
                            $usr_owner = array();
                            foreach($req_params as $name=>$val){
                                if(strpos($name,'ugr_')===0){
                                    $usr_owner[$name] = $mysqli->real_escape_string($req_params[$name]);
                                }
                            }

                            //mandatory fields
                            $fld_req = array('ugr_FirstName','ugr_LastName','ugr_eMail','ugr_Name','ugr_Password');
                            foreach($fld_req as $name){
                                if(@$usr_owner[$name]==null || $usr_owner[$name]==''){
                                    $system->addError(HEURIST_INVALID_REQUEST, 'Mandatory data for your registration profile '
                                        .'(first and last name, email, password) are not completed. Please fill out registration form');
                                    $usr_owner = null;
                                    break;
                                }
                            }
                            if($usr_owner!=null){
                                $usr_owner['ugr_Password'] = hash_it( $usr_owner['ugr_Password'] );
                            }
                        }

                    }else{
                        $ugr_ID = $system->get_user_id();
                        $usr_owner = user_getById($mysqli, $ugr_ID);
                    }

                    if($usr_owner!=null){
                        //it returns false or array of warnings
                        $res = DbUtils::databaseCreateFull($database_name, $usr_owner);

                        if($res!==false){
                            sendEmail_NewDatabase($usr_owner, $database_name, null);
                            //add url to new database
                            $res = array(
                                'newdbname'  => $database_name,
                                'newdblink'  => HEURIST_BASE_URL.PARAM_WELCOME.$database_name,
                                'newusername'=> $usr_owner['ugr_Name'],
                                'warnings'   => $res);
                        }
                    }
                }
            }

        }
        elseif($action=='restore')
        {
            //compose database name
            $database_name = __composeDbName($system, $req_params);
            if($database_name!==false){

                if($system->verifyActionPassword($sysadmin_pwd, $passwordForServerFunctions)){
                    $allow_action = false;
                    $system->addErrorMsg('This action requires a special system administrator password<br>');

                }else{

                    $archive_file = @$req_params['file'];
                    $archive_folder = intval(@$req_params['folder']);

                    $res = DbUtils::databaseRestoreFromArchive($database_name, $archive_file, $archive_folder);

                    if($res!==false){
                        //add url to new database
                        $res = array(
                            'newdbname'  => $database_name,
                            'newdblink'  => HEURIST_BASE_URL.PARAM_WELCOME.$database_name
                        );
                    }
                }
            }
        }
        elseif($action=='delete' || $action=='clear')
        {

            $allow_action = false;
            $db_target = @$req_params['database']?$req_params['database']:$req_params['db'];
            $db_target = trim(preg_replace(REGEX_ALPHANUM, '', $db_target));//for snyk

            $create_archive = !array_key_exists('noarchive', $req_params);//for delete

            $sErrorMsg = DbUtils::databaseValidateName($db_target, 2);//exists
            if ($sErrorMsg!=null) {
                $system->addError(HEURIST_ACTION_BLOCKED, $sErrorMsg);
            }else{

                $is_current_db = ($req_params['db']==$db_target);

                //validate premissions - sysadmin protection
                if($sysadmin_pwd!=null){
                    $allow_action = !$system->verifyActionPassword($sysadmin_pwd, $passwordForDatabaseDeletion, 15);
                }

                if($is_current_db) {

                    if($system->is_dbowner() || $allow_action){

                        $challenge_word = ($action=='clear')?'CLEAR ALL RECORDS':'DELETE MY DATABASE';
                        $allow_action = !$system->verifyActionPassword($challenge_pwd, $challenge_word);
                    }else{
                        $system->addError(HEURIST_REQUEST_DENIED,
                            'To perform this action you must be logged in as Database Owner');
                    }

                }elseif(!$allow_action && (!isset($passwordForDatabaseDeletion) || $passwordForDatabaseDeletion=='')) {
                    $system->addError(HEURIST_REQUEST_DENIED,
                        'This action is not allowed unless a password is provided - please consult system administrator');
                }
            }

            if($allow_action){
                if($action=='clear'){

                    $res = DbUtils::databaseEmpty($db_target, false);

                }elseif($action=='delete'){
                    //keep owner info to send email after deletion
                    $usr_owner = user_getByField($mysqli, 'ugr_ID', 2, $db_target);

                    $res = DbUtils::databaseDrop(false, $db_target, $create_archive);

                    if($res!==false && !$is_current_db){
                        sendEmail_DatabaseDelete($usr_owner, $db_target, 1);
                    }
                }
            }


        }
        elseif($action=='rename' || $action=='clone')
        {
            $allow_action = false;
            $is_current_db = false;

            //source database
            $is_template = false; //(@$req_params['templatedb']!=null);//not used anymore
            if($is_template){
                $db_source = filter_var(@$req_params['templatedb'], FILTER_SANITIZE_STRING);
            }elseif (@$req_params['sourcedb']){ //by sysadmin from list of databases
                $db_source = filter_var(@$req_params['sourcedb'], FILTER_SANITIZE_STRING);
            }else{
                $db_source = $req_params['db'];
                $is_current_db  = true;
            }

            $db_source = trim(preg_replace(REGEX_ALPHANUM, '', $db_source));//for snyk

            $sErrorMsg = DbUtils::databaseValidateName($db_source, 2);//exists
            if ($sErrorMsg!=null) {
                if(strpos($sErrorMsg,'not exists')>0){
                   $sErrorMsg = 'Operation is possible when database to be cloned or renamed is on the same server. '
                                .$sErrorMsg;
                }
                $system->addError(HEURIST_ACTION_BLOCKED, $sErrorMsg);
            }else{

                //target database
                $db_target = __composeDbName($system, $req_params);
                if($db_target!==false){//!is_bool($db_target)
                    $sErrorMsg = DbUtils::databaseValidateName($db_target, 1);//unique
                    if ($sErrorMsg!=null) {
                        $system->addError(HEURIST_ACTION_BLOCKED, $sErrorMsg);
                    }else{

                        //validate permissions
                        //
                        //1. current database
                        //   must dbowner or dbadmin
                        //   must be registered or without new defs - otherwise sysadmin pwd
                        //2. cloned by sysadmin (sourcedb=)
                        //   sysadmin pwd
                        //3. template (templatedb=)
                        //   owner will be replaced with current owner
                        //   must be curated (regID<21) or <1000?

                        list($db_source_full, $db_source ) = mysql__get_names($db_source);
                        $sourceRegID = mysql__select_value($mysqli, 'select sys_dbRegisteredID from `'.$db_source_full.'`.sysIdentification where 1');

                        if($is_current_db){
                            if(!$system->is_admin()){
                                $system->addError(HEURIST_REQUEST_DENIED,
'To perform this action you must be logged in as Administrator of group \'Database Managers\' or as Database Owner');
                            }else{
                                $allow_action = true;
                                if($action=='clone' && !($sourceRegID>0)){
                                    //check for new definitions - sysadmin protection
                                    $hasWarning = DbUtils::databaseCheckNewDefs();
                                    if($hasWarning!=false && $system->verifyActionPassword($sysadmin_pwd, $passwordForServerFunctions)){
                                        $allow_action = false;
                                        //add prefix
                                        $system->addErrorMsg(
"Sorry, the database $db_source has new definitions.<br>It must be registered before cloning or provide special system administrator password.<br><br>");
                                    }
                                }
                            }
                        }elseif($is_template){

                            if($sourceRegID>0 && $sourceRegID<1000){
                                $allow_action = true;
                            }else{
$sErrorMsg = "Sorry, the database $db_source must be registered with an ID less than 1000, indicating a database curated or approved by the Heurist team, to allow cloning through this function. You may also clone any database that you can log into through the Advanced functions under Administration.";
                            }

                        }elseif (!$system->verifyActionPassword($sysadmin_pwd, $passwordForServerFunctions)){
                            //cloned by sysadmin (sourcedb=) from list of databases
                            $allow_action = true;
                        }
                    }
                }
            }

            if($allow_action){

                $create_archive = !array_key_exists('noarchive', $req_params);//for rename
                $nodata = array_key_exists('nodata', $req_params);//for clone


                if($action=='rename'){

                    $res = DbUtils::databaseRename($db_source, $db_target, $create_archive);

                    if($res!==false){
                        $res = array(
                                'newdbname'  => $db_target,
                                'newdblink'  => HEURIST_BASE_URL.PARAM_WELCOME.$db_target,
                                'warning'    => $system->getErrorMsg());
                    }

                }elseif($action=='clone'){

                    $res = DbUtils::databaseCloneFull($db_source, $db_target, $nodata, $is_template);

                    if($res!==false){

                        DbUtils::databaseResetRegistration($db_target);//remove registration from sysIndetification

                        //to send email after clone
                        $usr_owner = user_getByField($mysqli, 'ugr_ID', 2, $db_target);
                        sendEmail_NewDatabase($usr_owner, $db_target,
                                ' from  '.($is_template?'':'template ').$db_source);

                        $res = array(
                                'newdbname'  => $db_target,
                                'newdblink'  => HEURIST_BASE_URL.PARAM_WELCOME.$db_target);

                    }

                }
            }

        }

        elseif($action=='verify')
        {

            if(!$system->is_admin()){
                $system->addError(HEURIST_REQUEST_DENIED,
'To perform this action you must be logged in as Administrator of group \'Database Managers\' or as Database Owner');
            }else{

                $dbVerify = new DbVerify($system);

                if(@$req_params['checks']==null || @$req_params['checks']=='all'){
                    $class_methods = get_class_methods($dbVerify);
                    $actions = array();
                    foreach ($class_methods as $method_name) {
                        if(strpos($method_name,'check_')===0){
                            array_push($actions, substr($method_name,6));
                        }
                    }
                }else{
                    $actions = explode(',',$req_params['checks']);
                }

                $res = array();
                if(!empty($actions)){

                    $counter = 0;
                    foreach($actions as $action){
                        $method = 'check_'.$action;
                        if(method_exists($dbVerify, $method) && is_callable(array($dbVerify, $method))){

                            $req_params['progress_report_step'] = $counter;

                            $res2 = $dbVerify->$method($req_params);

                            if(is_bool($res2) && $res2==false){
                                //terminated by user
                                if(empty($res)){
                                    $res = false;
                                }
                                break;
                            }else{
                                $counter++;
                                $res[$action] = $res2;
                                if(DbUtils::setSessionVal($counter)){
                                    //terminated by user
                                    $system->addError(HEURIST_ACTION_BLOCKED, 'Database Verification has been terminated by user');
                                    if(empty($res)){
                                        $res = false;
                                    }
                                    break;
                                }
                            }
                        }
                    }

                }
                if(is_array($res)){
                    if(empty($res)){
                        $system->addError(HEURIST_INVALID_REQUEST, "'Checks' parameter is missing or incorrect");
                        $res = false;
                    }elseif(@$req_params['reload']==1){
                        $res['reload'] = true;
                    }
                }
            }
        }
        else{
            $system->addError(HEURIST_INVALID_REQUEST, "Action parameter is missing or incorrect");
            $res = false;
        }

        DbUtils::setSessionVal('REMOVE');

        if(is_bool($res) && $res==false){
                $response = $system->getError();
        }else{
                $response = array('status'=>HEURIST_OK, 'data'=> $res, 'message'=>$system->getErrorMsg());
        }
   }
}

header(CTYPE_JSON);
print json_encode($response);



//
//
//
function __composeDbName($system, $req_params){

    $uName = '';
    if(@$req_params['uname']){
        $uName = trim(preg_replace(REGEX_ALPHANUM, '', @$req_params['uname'])).'_';//for snyk
        if ($uName == '_') {
                $uName='';  // don't double up underscore if no user prefix
        }
    }
    $dbName = trim(preg_replace(REGEX_ALPHANUM, '', @$req_params['dbname']));

    if($dbName==''){
        $system->addError(HEURIST_INVALID_REQUEST, "Database name parameter is missing or incorrect");
        $res = false;
    }else{
        $res = $uName . $dbName;
    }

    return $res;
}
?>