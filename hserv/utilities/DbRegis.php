<?php
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
* Static class to perform database registration operation (in heurist_reference_index database)
*
* Methods:
* registrationAdd    - add new registation to HEURIST_INDEX_DATABASE database
* registrationUpdate - change url and name of registered database in HEURIST_INDEX_DATABASE database
* registrationDelete - remove registration
*
* private
* registrationRemoteCall - if script is not executed on reference server, it calls indexControleer on reference server
* addError - add error into global $system
* registrationValidateValues
* registrationValidateUser
*
*/
namespace hserv\utilities;
use hserv\System;
use hserv\utilities\DbUtils;
use hserv\utilities\USanitize;
use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/../records/edit/recordModify.php';

class DbRegis {

     /**
     * Construct won't be called inside this class and is uncallable from
     * the outside. This prevents instantiating this class.
     * This is by purpose, because we want a static class.
     */
    private function __construct() {}
    private static $mysqli = null;
    private static $system = null;
    private static $initialized = false;

    private static $isOutSideRequest = false;

    public static function initialize()
    {
        if (self::$initialized){
            return true;
        }

        self::$isOutSideRequest = (strpos(strtolower(HEURIST_INDEX_BASE_URL), strtolower(HEURIST_SERVER_URL))===false);

        if(!self::$isOutSideRequest){
            //connect
            self::$system = new System();
            if(self::$system->init(HEURIST_INDEX_DATABASE, true, false)){ //init without paths and consts
                self::$mysqli = self::$system->getMysqli();
            }else{
                self::addError();
                return false;
            }
        }
        self::$initialized = true;
        return true;

    }

    /**
    * Request Heurist reference server
    *
    * @param mixed $params
    */
    private static function registrationRemoteCall($params){

        if(@$params['db']!=null){
            unset($params['db']);//reset to avoid recursion
        }

        $reg_record = null;

        $remote_url = HEURIST_MAIN_SERVER.'/h6-alpha/'  //temp - replace with HEURIST_INDEX_BASE_URL as soon as heurist will be updated
                .'hserv/controller/indexController.php?'
                .http_build_query($params);

        $data = loadRemoteURLContentWithRange($remote_url, null, true);

        if (!isset($data) || $data==null) {
            global $glb_curl_error;
            $error_code = (!empty($glb_curl_error)) ? $glb_curl_error : 'Error code: 500 Heurist Error';

            //transfer error into global system
            self::addError(HEURIST_UNKNOWN_ERROR,
                'Unable to connect Heurist reference server, possibly due to timeout or proxy setting<br><br>'
                . $error_code . '<br>'
                ."URL requested: ".htmlspecialchars($remote_url));
            return false;
        }else{
            //add error
            $data = json_decode($data, true);
            if(@$data['status']==HEURIST_OK){
               $reg_record =  $data['data'];
            }else{
                //transfer error into global syst+em
                $data['message'] = 'Heurist reference server returns error message: '.@$data['message'];
                self::addError($data);//transfer error to global $system
                return false;
            }
        }

        return $reg_record;
    }

    /**
    * Adds/transfers error into global system variable
    */
    private static function addError($error=null, $msg=null)
    {
        global $system;
        if($error==null){
            $system->addErrorArr(self::$system->getError());//transfer from this $system
        }elseif (is_array($error)){
            $system->addErrorArr($error);
        }else{
            $system->addError($error, $msg);
        }
    }

    /**
    * Validates serverURL and check presence dbID
    *
    * @param mixed $params
    */
    private static function registrationValidateValues(&$params){

        //check url
        if(@$params["serverURL"]){
            $serverURL = $params["serverURL"];
            $serverURL_lc = strtolower($params["serverURL"]);

            //add default scheme
            if(!(strpos($serverURL_lc,HTTP_SCHEMA)===0 || strpos($serverURL_lc,HTTPS_SCHEMA)===0)){
                $serverURL = HTTPS_SCHEMA.$serverURL;  //https by default
                $serverURL_lc = strtolower($serverURL);
            }

            if(!(strpos(strtolower($serverURL_lc),HTTPS_SCHEMA)===0 || strpos(strtolower($serverURL_lc),HTTP_SCHEMA)===0)){
                self::addError(HEURIST_ACTION_BLOCKED,
                        'Database url does not have a trusted scheme');
                return false;
            }

            if(strpos($serverURL_lc, '://localhost')>0 ||  strpos($serverURL_lc, '://127.0.0.1')>0 || strpos($serverURL_lc, '://web.local')>0){
                self::addError(HEURIST_ACTION_BLOCKED,
                        'Registered databases cannot be on local server '.htmlspecialchars($serverURL));
                return false;
            }

            //sanitize URL
            $serverURL = USanitize::sanitizeURL($serverURL);
            if($serverURL==null){
                self::addError(HEURIST_ACTION_BLOCKED, 'Database url to be registered is not valid');
                return false;
            }

            $params["serverURL"] = $serverURL;
        }

        // Check the record exists
        $dbID = intval(@$params["dbID"]);
        if($dbID>0){
            $res = mysql__select_value(self::$mysqli, 'SELECT rec_ID FROM Records WHERE rec_ID = '.$dbID);
            if(!$res){
                self::addError(HEURIST_INVALID_REQUEST, 'Unable to locate registered database with ID '.$dbID);
                return false;
            }
        }
        return true;
    }

    /**
    * put your comment there...
    *
    * @param mixed $params
    */
    private static function registrationValidateUser($params){

        $mysqli = self::$mysqli;

        $usrEmail = @$params['usrEmail'];
        $usrPassword = @$params['usrPassword'];
        $dbID = intval(@$params['dbID']);

        $user_id = 0; // existing record owner

        // Retrieve user - OWNER CAN BE CHANGED + DETAILS CAN BE CHANGED
        $usrEmail = strtolower(trim($usrEmail));
        $user_id = mysql__select_value($mysqli, 'select ugr_ID from sysUGrps where lower(ugr_eMail)="'
            .$mysqli->real_escape_string($usrEmail).'"');

        // Check if the email address is recognised as a user name
        if($user_id <= 0){
            $user_id = mysql__select_value($mysqli, 'select ugr_ID from sysUGrps where lower(ugr_Name)="'
                .$mysqli->real_escape_string($usrEmail).'"');
        }

        // Validate password
        $user_pwd = '';
        $valid_password = !empty($usrPassword);
        if($valid_password && $user_id > 0){
            $user_pwd = mysql__select_value($mysqli, 'select ugr_Password from sysUGrps where ugr_ID=' . intval($user_id));
            $valid_password = hash_equals($usrPassword, $user_pwd);
            //hash_equals(crypt($usrPassword, $user_pwd), $user_pwd);
        }

        // Unable to retrieve existing user or provided password is wrong
        if($user_id <= 0 || !$valid_password){
            $errorMsg = ($user_id <= 0 ? 'We were unable to retrieve your user account within the Heurist Index database.'
                : 'We were unable to authenicate your account on the Heurist Index database')
            . '<br>Please ensure that your email address and password on the Heurist Index database match your current email address and password.'
            . '<br>Contact the Heurist team if you require help with updating your email address and password on the Heurist Index database.';

            self::addError(HEURIST_ACTION_BLOCKED, $errorMsg);
            return false;
        }

        if($dbID>0){
            // Check user is owner of record
            $res = mysql__select_value($mysqli, 'SELECT rec_ID FROM Records WHERE rec_ID = ' . $dbID . ' AND rec_OwnerUGrpID = ' . $user_id);
            if(!$res){
                self::addError(HEURIST_ACTION_BLOCKED, 'You do not own the record for this registered database, this could be due to a previous transfer in database ownership.'
                . '<br>Please contact the Heurist team and request that the record for your database be updated.');
                return false;
            }
        }

        return true;
    }

    /**
    * Removes registration
    *
    * @param mixed $params
    * @return {false|true}
    */
    public static function registrationDelete($params){

        if(!self::initialize()) {return false;} //can not connect to index database

        if(self::$isOutSideRequest){
            return self::registrationRemoteCall($params);
        }

        $mysqli = self::$mysqli;

        $dbID = intval(@$params["dbID"]);

        if (!isPositiveInt($dbID)){
            self::addError(HEURIST_INVALID_REQUEST, 'Database ID not defined');
            return false;
        }

        if(!self::registrationValidateValues($params)){
            return false;
        }

        if(!self::registrationValidateUser($params)){
            return false;
        }

        mysql__supress_trigger($mysqli, false);
        ConceptCode::setSystem(self::$system);
        $rty_ID_registered_database = ConceptCode::getRecTypeLocalID(HEURIST_INDEX_DBREC);

        $keep_autocommit = mysql__begin_transaction($mysqli);
        self::$system->defineConstant('RT_RELATION');
        $stat = deleteOneRecord(self::$system, $dbID, $rty_ID_registered_database);

        if( array_key_exists('error', $stat) ){
            self::addError(HEURIST_INVALID_REQUEST, $stat['error']);
            $res = false;
            $mysqli->rollback();
        }else{
            $res = true;
            $mysqli->commit();
        }

        if($keep_autocommit===true) {$mysqli->autocommit(true);}

        return $res;
    }



    /**
    * Change existing registration
    *
    * @param mixed $params
    */
    public static function registrationUpdate($params){

        if(!self::initialize()) {return false;} //can not connect to index database

        if(self::$isOutSideRequest){
            return self::registrationRemoteCall($params);
        }

        $sys = self::$system;
        $mysqli = self::$mysqli;

        // Get parameters passed from update request
        $dbName = @$params['dbReg'];// Database name
        $dbTitle = @$params['dbTitle'];// Database description (DT_NAME)
        $dbID = intval(@$params['dbID']);

        if (!isPositiveInt($dbID)){
            self::addError(HEURIST_INVALID_REQUEST, 'Database ID not defined');
            return false;
        }

        if(!self::registrationValidateValues($params)){
            return false;
        }

        $serverURL = @$params["serverURL"];
        if(!$serverURL && !$dbName){
            self::addError(HEURIST_INVALID_REQUEST, 'Database name and url are not defined');
            return false;
        }

        if(!self::registrationValidateUser($params)){
            return false;
        }

        $defRecTitle = '<i>'.$dbName.'</i>';

        //update rec_URL and rec_Title
        $record = array(
            'rec_ID'=>$dbID,
            'rec_Modified'=>date(DATE_8601)
        );

        $err_msg = '';
        if(!empty($serverURL)){
            $record['rec_URL'] = $mysqli->real_escape_string($serverURL);
            $err_msg = 'URL (server URL)';
        }
        if(!empty($dbTitle)){
            $record['rec_Title'] = $defRecTitle.' : '.$dbTitle;
            $err_msg = $err_msg . (!empty($err_msg) ? ' and' : '') . ' Title (database name)';
        }

        $res = $dbID;
        if($err_msg!=''){  //!empty($serverURL) || !empty($dbTitle)
            $res = mysql__insertupdate($mysqli, 'Records', 'rec_', $record, true);
        }
        if($res != $dbID){
            self::addError(array(HEURIST_DB_ERROR,
                    'Failed to update database registration: ' . $err_msg, $mysqli->error));
            return false;
        }


        ConceptCode::setSystem($sys);

        if($dbTitle){
           self::recordUpdateField($sys, $dbID, '2-1', $dbTitle);
        }
        if($dbName){
           self::recordUpdateField($sys, $dbID, '1176-469', $dbName);
        }

        //update record title
        // it does not work - need to convert TitleMask class from static
        // $rty_ID_registered_database = ConceptCode::getRecTypeLocalID(HEURIST_INDEX_DBREC);
        // recordUpdateTitle($sys, $dbID, $rty_ID_registered_database, $defRecTitle);

        return $dbID;
    }

    /**
    * Return registration info from Heurist reference index
    *
    * @param mixed $params
    * @return null
    */
    public static function registrationGet($params){

        if(!self::initialize()) {return false;} //can not connect to index database

        if(self::$isOutSideRequest){
            $params['action'] = 'info';
            return self::registrationRemoteCall($params);
        }

        if(!isPositiveInt(@$params['dbID'])){
            self::addError(HEURIST_INVALID_REQUEST, 'Database ID is not set or invalid. It must be an integer positive value.');
            return false;
        }

            $database_url = null;
            $database_id = intval(@$params['dbID']);

            $sys = self::$system;
            $mysqli = $sys->getMysqli();

            ConceptCode::setSystem($sys);
            $rty_ID_registered_database = ConceptCode::getRecTypeLocalID(HEURIST_INDEX_DBREC);

            $rec = mysql__select_row_assoc($mysqli,
                'select rec_Title, rec_URL from Records where rec_RecTypeID='
                .$rty_ID_registered_database.' and rec_ID='  //1-22
                .$database_id);

            if ($rec!=null){
                $database_url = @$rec['rec_URL'];
                if(isEmptyStr($database_url)){
                    self::addError(HEURIST_NOT_FOUND,
                        'Database URL is not set in Heurist Reference Index database for database ID#'.$database_id);
                    return false;
                }
                return $database_url;
            }


            $err = $mysqli->error;
            if($err){
                self::addError(HEURIST_DB_ERROR,
                     'Heurist Reference Index database is not accessible at the moment. Please try later');
            }else{
                self::addError(HEURIST_NOT_FOUND,
                     'Database with ID#'.$database_id.' is not found in Heurist Reference Index database');
            }
            return false;
    }


    /**
    * Register database in heurist reference index datbase and returns database ID
    *
    * @param mixed $params
    * @return {false|null|true}
    */
    public static function registrationAdd($params){

        if(!self::initialize()) {return false;} //can not connect to index database

        if(self::$isOutSideRequest){
            $dbname = @$params['db'];//keep
            $reg_rec = self::registrationRemoteCall($params);

            if($dbname!=null && $reg_rec){
                 //on remote servr
                 //$reg_rec = array('dbID'=>$dbID, 'dbTitle'=>$params['dbTitle']);
                 DbUtils::databaseUpdateRegistration($dbname, $reg_rec);
            }
            return $reg_rec;
        }

        $sys = self::$system;
        $mysqli = self::$mysqli;


        //validate serverURL
        if(!self::registrationValidateValues($params)){
            return false;
        }

        $indexdb_user_id = 0; // Flags problem if not reset

        // Get parameters passed from registration request
        // @ preceding $params avoids errors, sets Null if parameter missing
        $dbName = @$params['dbReg'];
        $dbTitle = @$params['dbTitle'];//DT_NAME
        $dbVersion = @$params['dbVer'];

        $usrEmail = @$params['usrEmail'];
        $usrPassword = @$params['usrPassword'];//hashed
        $usrName = @$params['usrName'];
        $usrFirstName = @$params['usrFirstName'];
        $usrLastName = @$params['usrLastName'];

        $serverURL = @$params['serverURL'];
        if(!$serverURL || !$dbName || !$dbTitle){
            self::addError(HEURIST_INVALID_REQUEST, 'Database name and url are not defined');
            return false;
        }

        if(!$usrEmail || !$usrName || !$usrFirstName || !$usrLastName || !$usrPassword) { // error in one or more parameters
            self::addError(HEURIST_INVALID_REQUEST, 'User parameters and credentials are not fully defined');
            return false;
        }

        // the record type for database (collection) descriptor records - fixed for Master database
        ConceptCode::setSystem($sys);
        $rty_ID_registered_database = ConceptCode::getRecTypeLocalID(HEURIST_INDEX_DBREC);

        if(!isPositiveInt($rty_ID_registered_database)){
            self::addError(HEURIST_SYSTEM_CONFIG, 'Record type "Database registration" ('.HEURIST_INDEX_DBREC.') bot found in Heurist reference index database');
            return false;
        }

        // if database is on main server it is possible to register database with user-defined ID
        /* DISABLED
        $newid = intval(@$params["newid"]);
        if($newid>0){
            if(!(strpos(strtolower($serverURL_lc), strtolower(HEURIST_MAIN_SERVER))===0)){
                return '0,It is possible to assign arbitrary ID for databases on heurist servers only';
            }
            $rec_id = mysql__select_value($mysqli, 'select rec_ID from Records where rec_ID='.$newid);
            if($rec_id>0){
                return '0,Database ID '.$newid.' is already allocated. Please choose different number';
            }
        }*/

        // allocate a new user for this database unless the user's email address is recognised
        // If a new user, log the user in and assign the record ownership to that user
        // By allocating users on the database based on email address we can allow them to edit their own registrations
        // but they can't touch anyone else's

        // Find the registering user in the index database, make them the owner of the new record
        $usrEmail = strtolower(trim($usrEmail));

        $indexdb_user_id = mysql__select_value($mysqli, 'select ugr_ID from sysUGrps where lower(ugr_eMail)="'
                .$mysqli->real_escape_string($usrEmail).'"');

        // Check if the email address is recognised as a user name
        // Added 19 Jan 2012: we also use email for ugr_Name and it must be unique, so check it has not been used
        if(!isPositiveInt($indexdb_user_id)) { // no user found on email, try querying on user name
            $indexdb_user_id = mysql__select_value($mysqli, 'select ugr_ID from sysUGrps where lower(ugr_Name)="'
                .$mysqli->real_escape_string($usrEmail).'"');
        }

        if(!isPositiveInt($indexdb_user_id)) { // did not find the user, create a new one and pass back login info

            // Note: we use $usrEmail as user name because the person's name may be repeated across many different users of
            // different databases eg. there are lots of johnsons, which will cause insert statement to fail as ugr_Name is unique.

            $indexdb_user_id = mysql__insertupdate($mysqli, 'sysUGrps', 'ugr_',
                array(
                    'ugr_Name'=>$usrEmail,
                    'ugr_Password'=>$usrPassword,
                    'ugr_eMail'=>$usrEmail,
                    'ugr_Enabled'=>'y',
                    'ugr_FirstName'=>$usrFirstName,
                    'ugr_LastName'=>$usrLastName,
                )
            );

            if(!isPositiveInt($indexdb_user_id)) { // Unable to create the new user
                self::addError(array(HEURIST_DB_ERROR,
                        'Unable to write new user in Heurist reference index database', $mysqli->error));
                return false;
            }
        }


        // write the core database record describing the database to be registered and allocate registration ID
        // This is not a fully valid Heurist record, we let the edit form take care of that
        // First look to see if there is an existing registration - note, this uses the URL to find the record, not the registration ID
        //
        // TOOD: Would be good to have a recaptcha style challenge otherwise can be called repeatedly
        // with slight URL variations to spawn multiple registrations of dummy databases

        $dbID = mysql__select_value($mysqli, "select rec_ID from Records where lower(rec_URL)='".
                        $mysqli->real_escape_string(strtolower(trim($serverURL)))."'");
        if($dbID>0) {
            //database with such id already exist
            self::addError(HEURIST_ACTION_BLOCKED, 'Database with such URL already registered');
            return false;
            //return $dbID;
        }else{// new registration

            $defRecTitle = '<i>'.$dbName.'</i> : '.$dbTitle;

            $mysqli->query('set @logged_in_user_id = 2');

            $record = array(
                    'rec_ID'=>0,  //($newid>0)?-$newid:0,
                    'rec_URL'=>$mysqli->real_escape_string($serverURL),
                    'rec_Added'=>date(DATE_8601),
                    'rec_RecTypeID'=> $rty_ID_registered_database,
                    'rec_Title' => $defRecTitle,
                    'rec_AddedByImport'=>0,
                    'rec_OwnerUGrpID'=>$indexdb_user_id,
                    'rec_NonOwnerVisibility'=>'public',
                    'rec_Popularity'=>99,
                );

            $dbID = mysql__insertupdate($mysqli, 'Records', 'rec_', $record, true);

            $mysqli->query('set @logged_in_user_id = '.$sys->getUserId());

            if($dbID>0){
                if($dbTitle){
                    self::recordUpdateField($sys, $dbID, '2-1', $dbTitle, false);
                }
                if($dbName){
                    self::recordUpdateField($sys, $dbID, '1176-469', $dbName, false);
                }
                if($dbVersion){
                    self::recordUpdateField($sys, $dbID, '1176-335', $dbVersion, false);
                }

                //update record title
                // it does not work - need to convert TitleMask class from static
                //recordUpdateTitle($sys, $dbID, $rty_ID_registered_database, $defRecTitle);


                // Write the record bookmark into the bookmarks table. It allows the user registering the database
                // to see their list of databases as My Bookmarks
                mysql__insertupdate($mysqli, 'usrBookmarks', 'bkm_',
                    array(
                        'bkm_UGrpID'=>$indexdb_user_id,
                        'bkm_RecID'=>$dbID
                    )
                );

                
                //send email to administrator about new database registration
                $email_text =
                "There is a new Heurist database registration on the Heurist Reference Index\n\n".
                "Database Title:     ".htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8')."\n".
                "Registration ID:    ".$dbID."\n". // was $indexdb_user_id, which is always 0 b/cnot yet logged in to reference index
                "DB Format Version:  ".$dbVersion."\n\n".
                // "User name:    ".$usrFirstName." ".$usrLastName."\n".  // comes out 'every user' b/c user not set
                // "Email address: ".$usrEmail."\n".                      // comes out 'not set for user 0'
                "Go to the address below to review the database:\n".
                $serverURL;

                $dbowner = user_getDbOwner($mysqli);
                $dbowner_Email = $dbowner['ugr_eMail'];
                $email_title = 'Database registration ID: '.$dbID.'. User ['.$indexdb_user_id.']';

                sendEmail($dbowner_Email, $email_title, $email_text);
            
                //END email -----------------------------------

                $res = array('dbID'=>$dbID, 'dbTitle'=>$params['dbTitle']);

                if(@$params['db']!=null && $dbID>0){
                     //on the same server
                     DbUtils::databaseUpdateRegistration($params['db'], $res);
                }

                return $res;
            }else{

                self::addError(array(HEURIST_DB_ERROR, 'Cannot write record in Heurist reference index ', $mysqli->error));
                return false;
            }

        }

    }//registrationAdd

    /**
    * Inserts or update field value (if multiple value - it updates first only)
    *
    * It is applicable for single values. If there are several values - it updated first only
    *
    * @param mixed $system
    * @param mixed $rec_ID
    * @param mixed $conceptCode
    * @param mixed $value
    * @param mixed $isnew
    */
    private static function recordUpdateField($system, $rec_ID, $conceptCode, $value, $is_exist=true){

        $dty_ID = ConceptCode::getDetailTypeLocalID($conceptCode);

        $mysqli = $system->getMysqli();

        $dtl_ID = -1;
        if($is_exist){
            $dtl_ID = mysql__select_value($mysqli, 'SELECT dtl_ID FROM recDetails WHERE dtl_DetailTypeID='.$dty_ID.' AND dtl_RecID='.$rec_ID);
        }

        $detail = array(
            'dtl_DetailTypeID'=>$dty_ID,
            'dtl_Value'=>$value
        );
        if(intval($dtl_ID)>0){
            // update
            $detail['dtl_ID'] = intval($dtl_ID);
        }else{
            //insert
            $detail['dtl_RecID'] = $rec_ID;
        }

        //Write the database title into the details, further data will be entered by the Heurist form
        mysql__insertupdate($mysqli, 'recDetails', 'dtl_', $detail);
    }

}
