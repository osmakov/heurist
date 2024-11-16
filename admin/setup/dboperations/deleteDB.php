<?php
/**
* deleteDB.php: delete MULTIPLE databases. Called by dbStatistics.php (for system admin only)
*               note that deletion of current database is handled separately by deleteCurrentDB.php which calls dbUtils.php
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

use hserv\utilities\USanitize;
use hserv\utilities\DbUtils;

require_once dirname(__FILE__).'/../../../autoload.php';

require_once 'welcomeEmail.php';

set_time_limit(0);

$res = false;

$system = new hserv\System();

$sysadmin_pwd = USanitize::getAdminPwd();

if($sysadmin_pwd==null){
    $system->addError(HEURIST_INVALID_REQUEST, errorWrongParam('Password'));
}else{

    $database_to_delete = filter_var(@$_REQUEST['database'], FILTER_SANITIZE_STRING);

    $sErrorMsg = DbUtils::databaseValidateName($database_to_delete, 2);

    if ($sErrorMsg!=null) {
        $system->addError(HEURIST_ACTION_BLOCKED, $sErrorMsg);
    }else{

    $database_to_delete = preg_replace(REGEX_ALPHANUM, "", $database_to_delete);//for snyk

    if(array_key_exists('create_archive', $_REQUEST)){
        $create_arc = $_REQUEST['create_archive'];
    }else{
        $create_arc = false;
    }

//if user deletes its own database
    $is_delete_current_db = (@$_REQUEST['db']==$database_to_delete && $sysadmin_pwd=='DELETE MY DATABASE');

// Password check for system administrator who can delete any database
    if($is_delete_current_db || !$system->verifyActionPassword($sysadmin_pwd, $passwordForDatabaseDeletion, 14))
    {
        if($database_to_delete){

            //if database to be deleted is not current - only system admin can do it
            $isSystemInited = $system->init(@$_REQUEST['db']);//need to verify credentials for current database

            /** Db check */
            if($isSystemInited){

                    $allow_deletion = true;


                    list($dbname_full, $dbname ) = mysql__get_names( $database_to_delete );

                    if($is_delete_current_db){

                        $user = user_getById($system->getMysqli(), $system->getUserId());//user in current db

                        $allow_deletion = false;
                        //find the same user in database to be deleted
                        //find user by email
                        $usr = user_getByField($system->getMysqli(), 'ugr_eMail', $user['ugr_eMail'], $dbname_full);
                        if(@$usr['ugr_ID']==2){ //database owner
                            $allow_deletion = true;
                        }else{
                            //allowed if user is database admnistrator
                            $groups = user_getWorkgroups($system->getMysqli(), $usr['ugr_ID'], false, $dbname_full);
                            $allow_deletion = (@$groups[1]=='admin');
                        }
                    }

    /* before 2020-12-21 only system administrator or db
                if (defined('HEURIST_MAIL_TO_ADMIN') && (@$user['ugr_eMail']==HEURIST_MAIL_TO_ADMIN)){ //system admin

                    $allow_deletion = true;
                }else{
                    list($dbname_full, $dbname ) = mysql__get_names( $_REQUEST['database'] );
                    //find user by email
                    $usr = user_getByField($system->getMysqli(), 'ugr_eMail', $user['ugr_eMail'], $dbname_full);
                    if(@$usr['ugr_ID']==2){ //database owner
                        $allow_deletion = true;
                    }
                }
    */
                    if($allow_deletion)
                    {
                        //find owner of database
                        $usr_owner = user_getByField($system->getMysqli(), 'ugr_ID', 2, $dbname_full);

                        //not verbose
                        $res = DbUtils::databaseDrop(false, $database_to_delete, $create_arc);

                        // in case deletion by sysadmin - send email to onwer of deleted database
                        if($res && !$is_delete_current_db)
                        {
                            sendEmail_DatabaseDelete($usr_owner, $dbname, 1);
                        }

                    }else{
                        $system->addError(HEURIST_REQUEST_DENIED,
                            'You must be a database administrator or owner to delete database '.$database_to_delete,1);
                    }

            }
        }else{
            //database not defined - this is just authorization check
            $res = true; //authentification passed
        }
    }
    else{
        $system->addError(HEURIST_REQUEST_DENIED, 'Wrong password');
    }
}
}

if(is_bool($res) && !$res){
    $response = $system->getError();
}else{
    $response = array("status"=>HEURIST_OK, "data"=> $res);
}

header(CTYPE_JS);
print json_encode($response);
?>
