<?php
namespace hserv\entity;
use hserv\System;
use hserv\entity\DbEntityBase;

/**
* db access to usrUGrps table for users
*
*
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

require_once dirname(__FILE__).'/../records/search/recordFile.php';
require_once dirname(__FILE__).'/../records/edit/recordModify.php';//for recordDelete
require_once dirname(__FILE__).'/../structure/dbsUsersGroups.php';//send email methods

class DbSysUsers extends DbEntityBase
{
    private $keep_autocommit = false;
    private $transaction = false;

    public function __construct( $system, $data=null ) {
        parent::__construct( $system, $data );
        $this->requireAdminRights = false;
    }

    public function search(){

        $not_in_group = @$this->data['not:ugl_GroupID'];

        // Special handling when filtering with emails
        if(array_key_exists('ugr_eMail', $this->data) && strpos($this->data['ugr_eMail'], '=') !== 0){
            $this->data['ugr_eMail'] = "={$this->data['ugr_eMail']}";
        }

        if(parent::search()===false){
            return false;
        }

        $needCheck = false;
        $needRole = false;
        $needCount = false; //find cound where user is a member

        //compose WHERE
        $where = array('ugr_Type="user"');
        $from_table = array($this->config['tableName']);

        $pred = $this->searchMgr->getPredicate('ugr_ID');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('ugr_Name');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('ugr_Enabled');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('ugr_eMail');
        if($pred!=null) {array_push($where, $pred);}

        //find users belong to group
        $pred = $this->searchMgr->getPredicate('ugl_GroupID');
        if($pred!=null) {

            array_push($where, $pred);
            $needRole = true;

            $pred = $this->searchMgr->getPredicate('ugl_Role');
            if($pred!=null) {
                array_push($where, $pred);
            }

            array_push($where, '(ugl_UserID = ugr_ID)');
            array_push($from_table, 'sysUsrGrpLinks');
        }
        elseif (@$this->data['members_only']){ // user's who aren't group admins
            array_push($where, '(ugr_ID NOT IN (SELECT ugr_ID FROM sysUGrps, sysUsrGrpLinks WHERE (ugl_UserID = ugr_ID) AND (ugl_Role = "admin")))');
        }

        //special case - users must not be in given group

        if($not_in_group>0){
            array_push($where, '(ugr_ID NOT IN (SELECT ugr_ID FROM sysUGrps,sysUsrGrpLinks '
                .'WHERE (ugl_UserID = ugr_ID) AND (ugl_GroupID='.$not_in_group.') ))');
        }

        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){

            $this->data['details'] = 'ugr_ID';

        }elseif(@$this->data['details']=='name'){

            $this->data['details'] = 'ugr_ID,ugr_Name';

        }elseif(@$this->data['details']=='fullname'){

            $this->data['details'] = 'ugr_ID,CONCAT(ugr_FirstName,\' \',ugr_LastName) as ugr_FullName';

        }elseif(@$this->data['details']=='count'){

            $this->data['details'] = 'ugr_ID';
            $needCount = true;

        }elseif(@$this->data['details']=='list'){

            $this->data['details'] = 'ugr_ID,ugr_Name,ugr_FirstName,ugr_LastName,ugr_eMail,ugr_Organisation,ugr_Enabled';
            if($needRole) {
                $this->data['details'] .= ',ugl_Role';
            }else{
                $needCount = true;  //need count only for all groups
            }

        }elseif(@$this->data['details']=='full'){

            $this->data['details'] = 'ugr_ID,ugr_Name,ugr_FirstName,ugr_LastName,ugr_eMail,ugr_Department,ugr_Organisation,'
            .'ugr_City,ugr_State,ugr_Postcode,ugr_Interests,ugr_Enabled,usr_ExternalAuthentication';

            if($needRole){
                $this->data['details'] .= ',ugl_Role';
            }else{
                $needCount = true;  //need count only for all groups
            }

        }else{
            $needCheck = true;
        }

        if(!is_array($this->data['details'])){ //specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);
        }

        //exclude ugr_Password form sending to client side
        $idx = array_search('ugr_Password', $this->data['details']);
        if($idx !== false){
            unset($this->data['details'][$idx]);
        }
        // exclude ugr_eMail and ugr_Name from sending to client side when client's not logged in
        $idx = array_search('ugr_eMail', $this->data['details']);
        if(!$this->system->hasAccess() && $idx !== false){
            unset($this->data['details'][$idx]);
        }
        $idx = array_search('ugr_Name', $this->data['details']);
        if(!$this->system->hasAccess() && $idx !== false){
            unset($this->data['details'][$idx]);
        }

        //validate names of fields
        if($needCheck && !$this->_validateFieldsForSearch()){
            return false;
        }

        //----- order by ------------
        $orderby = $this->searchMgr->setOrderBy();

        if($needCount){ //find count of groups where given user is a memmber
            array_push($this->data['details'],
                '(select count(ugl_ID) from sysUsrGrpLinks where (ugl_UserID=ugr_ID)) as ugr_Member');
        }

        $is_ids_only = (count($this->data['details'])==1);

        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS  '.implode(',', $this->data['details'])
        .' FROM '.implode(',', $from_table);

        if(!empty($where)){
            $query = $query.SQL_WHERE.implode(SQL_AND,$where);
        }
        if($orderby!=null){
            $query = $query.' ORDER BY '.$orderby;
        }

        $query = $query.$this->searchMgr->getLimit().$this->searchMgr->getOffset();

        $calculatedFields = null;

        $result = $this->searchMgr->execute($query, $is_ids_only, $this->config['entityName'], $calculatedFields);

        return $result;
    }


    //
    // validate permission for edit tag
    // for delete and assign see appropriate methods
    //
    protected function _validatePermission(){

        $ugrID = $this->system->getUserId();

        if(!$this->system->isAdmin() &&
            ( !isEmptyArray($this->recordIDs)
                || !isEmptyArray($this->records))){ //there are records to update/delete

                if($this->recordIDs[0]!=$ugrID || count($this->recordIDs)>1){

                    $this->system->addError(HEURIST_REQUEST_DENIED,
                        'You are not admin and can\'t edit another user. Insufficient rights (logout/in to refresh) for this operation');
                    return false;
                }
        }elseif($ugrID != 2 && is_array($this->recordIDs) && in_array(2, $this->recordIDs)){
            $this->system->addError(HEURIST_REQUEST_DENIED,
                'You do not have permission to alter the database owner\'s account.');

            return false;
        }

        return true;
    }

    //
    //
    //
    protected function _validateValues(){

        $ret = parent::_validateValues();

        if($ret){

            $fieldvalues = $this->data['fields'];//current record

            $email_address = @$fieldvalues['ugr_eMail'];

            if($email_address){
                $email_address = filter_var($email_address, FILTER_SANITIZE_EMAIL);
                if(!filter_var($email_address, FILTER_VALIDATE_EMAIL)){
                    $this->system->addError(HEURIST_ACTION_BLOCKED, 'Email address is not valid');
                    return false;
                }
                $fieldvalues['ugr_eMail'] = $email_address;
            }

        }

        return $ret;
    }


    //
    //
    //
    protected function prepareRecords(){

        $ret = parent::prepareRecords();

        //@todo captcha validation for registration

        //add specific field values
        foreach($this->records as $idx=>$record){
            $this->records[$idx]['ugr_Type'] = 'user';
            $this->records[$idx]['ugr_Modified'] = date(DATE_8601);//reset

            //add password by default
            if(@$this->records[$idx]['ugr_Password']==''){
                unset($this->records[$idx]['ugr_Password']);

            }elseif(@$this->records[$idx]['ugr_Password']){

                $tmp_password = $this->records[$idx]['ugr_Password'];

                $this->records[$idx]['ugr_Password'] = hash_it( $tmp_password );
                $this->records[$idx]['tmp_password'] = $tmp_password;

            }
            if(!@$this->records[$idx]['ugr_Name']){
                $this->records[$idx]['ugr_Name'] = $this->records[$idx]['ugr_eMail'];
            }
            if(!$this->system->isAdmin() && (!@$this->records[$idx]['ugr_ID'] || $this->records[$idx]['ugr_Type']<0)){
                $this->records[$idx]['ugr_Enabled'] = 'n';
            }elseif(array_key_exists('ugr_Enabled', $this->records[$idx])){

                $allowed_status = array('n', 'y', 'y_no_add', 'y_no_delete', 'y_no_add_delete');
                $is_valid = in_array($this->records[$idx]['ugr_Enabled'], $allowed_status);

                $this->records[$idx]['ugr_Enabled'] = ($is_valid ? $this->records[$idx]['ugr_Enabled'] : 'n');// y_no_add_delete
            }

            //validate duplication
            //validate duplication
            if(!$this->doDuplicationCheck($idx, 'ugr_Name', 'User cannot be saved. The provided name already exists')){
                return false;
            }
            if(!$this->doDuplicationCheck($idx, 'ugr_eMail', 'User cannot be saved. The provided email already exists')){
                return false;
            }

            //find records to be approved and new ones
            if($this->system->isAdmin() && "y"==@$this->records[$idx]['ugr_Enabled'] && @$this->records[$idx]['ugr_ID']>0){
                $mysqli = $this->system->getMysqli();
                $res = mysql__select_value($mysqli,
                    'SELECT ugr_Enabled FROM sysUGrps WHERE ugr_LoginCount=0 AND ugr_Type="user" AND ugr_ID='
                    .$this->records[$idx]['ugr_ID']);
                if($res=='n'){
                    $this->records[$idx]['is_approvement'] = true;
                }
            }
            $this->records[$idx]['is_new'] = (!(@$this->records[$idx]['ugr_ID']>0));

        }

        return $ret;

    }

    //
    //
    //
    public function save(){

        $savedRecIds = parent::save();


        if($savedRecIds!==false){

            foreach($this->records as $idx=>$record){
                $ugr_ID = @$record['ugr_ID'];
                if($ugr_ID>0 && in_array($ugr_ID, $savedRecIds)){

                    //treat user image
                    $thumb_file_name = @$record['ugr_Thumb'];
                    //rename it to recID.png
                    if($thumb_file_name){
                        parent::renameEntityImage($thumb_file_name, $ugr_ID);
                    }

                    //add user to specified group
                    if($record['ugl_GroupID']>0){
                        $group_role = array();
                        $group_role['ugl_GroupID'] = $record['ugl_GroupID'];
                        $group_role['ugl_UserID'] = $ugr_ID;
                        $group_role['ugl_Role'] = 'member';

                        $res = mysql__insertupdate($this->system->getMysqli(), 'sysUsrGrpLinks', 'ugl', $group_role);

                        //$fname = HEURIST_FILESTORE_DIR.$ugr_ID;   //save special semaphore file to trigger user refresh for other users
                        //fileSave('X',$fname);//add to group ???
                    }

                    //send approvement or registration email
                    $rv = true;
                    $is_new = (@$this->records[$idx]['is_new']===true);
                    $is_approvement = (@$this->records[$idx]['is_approvement']===true);
                    if($is_new && $this->system->getUserId()<1){ //this is independent registration of new user
                        $rv = user_EmailAboutNewUser($this->system, $ugr_ID);
                    }elseif($is_new || $is_approvement){ //this is approvement or registration FOR user
                        $rv = user_EmailApproval($this->system, $ugr_ID, @$this->records[$idx]['tmp_password'], $is_approvement);

                        user_SyncCommonCredentials($this->system,  $ugr_ID, $is_approvement);
                    }
                    if(!$rv){
                        //cannot sent email
                        //return false;
                    }

                }
            }
        }
        return $savedRecIds;
    }

    //
    //
    //
    public function delete($disable_foreign_checks = false){


        $this->recordIDs = null; //reset to obtain ids from $data

        $this->foreignChecks = array(
            array('SELECT FIND_IN_SET(2, "#IDS#")','Cannot remove "Database Owner" user'),
            array('SELECT count(rec_ID) FROM Records WHERE rec_FlagTemporary=0 AND rec_OwnerUGrpID IN (#IDS#) LIMIT 1',
                'Deleting User with existing Records not allowed')
        );

        if(!$this->deletePrepare()){
            return false;
        }

        $mysqli = $this->system->getMysqli();

        //check for last admin
        foreach($this->recordIDs as $usrID){

            $query = 'SELECT g2.ugl_GroupID, count(g2.ugl_ID) AS adm FROM sysUsrGrpLinks AS g2 LEFT JOIN sysUsrGrpLinks AS g1 '
            .'ON g1.ugl_GroupID=g2.ugl_GroupID AND g2.ugl_Role="admin" '                        //is it the only admin
            .'WHERE g1.ugl_UserID='.$usrID.' AND g1.ugl_Role="admin" GROUP BY g1.ugl_GroupID  HAVING adm=1';//is this user admin

            //can't remove last admin
            $res = mysql__select_row($mysqli, $query);
            if($res!=null){
                $this->system->addError(HEURIST_ACTION_BLOCKED,
                    'It is not possible to remove  user #'.$usrID.'. This user is the only admin of the workgroup #'.$res[0]);
                return false;
            }
        }

        //---------------------------------------------------

        $keep_autocommit = mysql__begin_transaction($mysqli);

        //remove temporary records
        $query = 'SELECT rec_ID FROM Records WHERE rec_OwnerUGrpID in ('
        . implode(',', $this->recordIDs) . ') and rec_FlagTemporary=1';
        $rec_ids_to_delete = mysql__select_list2($mysqli, $query);
        if(!isEmptyArray($rec_ids_to_delete)){
            $res = recordDelete($this->system, $rec_ids_to_delete, false);
            if(@$res['status']!=HEURIST_OK) {return false;}
        }

        $ret = true;

        //remove from roles table
        $query = 'DELETE FROM sysUsrGrpLinks'
        . ' WHERE ugl_UserID in (' . implode(',', $this->recordIDs) . ')';

        $res = $mysqli->query($query);
        if(!$res){
            $this->system->addError(HEURIST_DB_ERROR,
                'Cannot remove entries from user/group links (sysUsrGrpLinks)',
                $mysqli->error );
            $ret = false;
        }

        $query = 'DELETE FROM usrHyperlinkFilters  WHERE hyf_UGrpID in (' . implode(',', $this->recordIDs) . ')';
        $mysqli->query($query);
        $query = 'DELETE FROM usrRemindersBlockList  WHERE rbl_UGrpID in (' . implode(',', $this->recordIDs) . ')';
        $mysqli->query($query);
        $query = 'DELETE FROM usrSavedSearches  WHERE svs_UGrpID in (' . implode(',', $this->recordIDs) . ')';
        $mysqli->query($query);
        $query = 'DELETE FROM usrTags  WHERE tag_UGrpID in (' . implode(',', $this->recordIDs) . ')';
        $mysqli->query($query);

        if($ret){
            $ret = parent::delete();
        }

        //@todo - remove assosiated images

        mysql__end_transaction($mysqli, $ret, $keep_autocommit);

        return $ret;
    }

    /**
    * Transfer User ID 2 (DB Owner) to the selected User ID,
    * and provide the new DB Owner administrator rights to all workgroups.
    *
    * @param bool $disable_foreign_checks If true, disables foreign key checks during transfer.
    * @return bool Returns true if ownership transfer was successful, false on failure.
    */
    private function transferOwner($disable_foreign_checks = false)
    {
        $mysqli = $this->system->getMysqli(); // MySQL connection
        $return = true; // Control variable
        $recID = $this->data['ugr_ID']; // Selected User ID

        $current_userid = $this->system->getUserId();
        if ($current_userid != 2) {
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Only the current database owner can transfer database ownership.');
            return false;
        }

        if (!is_numeric($recID)) {    /* Check if ID is a number */
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Provided ID is Invalid');
            return false;
        }
        $recID = intval($recID);
        if ($recID == 2) {   /* Check if selected ID is already the Owner */
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Cannot transfer Database Ownership to the current Database Owner');
            return false;
        }

        /* Check if selected user's account is enabled before proceeding */
        $usrEnabled = mysql__select_value($mysqli, 'SELECT ugr_Enabled FROM sysUGrps WHERE ugr_ID = ' . $recID);

        if ($usrEnabled == null) {
            $this->system->addError(HEURIST_DB_ERROR, 'Unable to retrieve user account status');
            return false;
        }

        if ($usrEnabled != 'y') {
            $msg = $usrEnabled == 'n' ? 'The selected user is not enabled. Please enable them to transfer database ownership to them.' :
            'The selected user does not have full create and delete record permissions. Please change this via the enabled field.';

            $this->system->addError(HEURIST_INVALID_REQUEST, $msg);
            return false;
        }

        /* Retrieve List of Workgroup IDs */
        $query = "SELECT ugr_ID FROM sysUGrps WHERE ugr_Type = 'workgroup'";
        $groupIds = mysql__select_list2($mysqli, $query);
        if (isEmptyArray($groupIds)) {
            $this->system->addError(HEURIST_DB_ERROR, 'Cannot retrieve group ids', $mysqli->error);
            return false;
        }

        /* Retrieve an un-used value for MAXINT, a temporary value used for swapping the two IDs */
        $maxUsrID = mysql__select_value($mysqli, 'SELECT max(ugr_ID)+1 FROM sysUGrps');
        $maxUsrID = intval($maxUsrID);

        /* Start Transaction, allow for rollback in case of errors */
        $this->keep_autocommit = mysql__begin_transaction($mysqli);
        $this->transaction = true;

        /* Remove all groups associated with the selected User's ID */
        $this->_updateDbTable('DELETE FROM sysUsrGrpLinks WHERE ugl_UserID = ' . $recID, 'Cannot remove old workgroups from ID: ' . $recID);

        /* Perform ID Swapping */
        $this->_updateDbTable("UPDATE sysUGrps SET ugr_ID = $maxUsrID WHERE ugr_ID = 2", 'Cannot set current Owner\'s ID to MAXINT');
        $this->_updateDbTable("UPDATE sysUGrps SET ugr_ID = 2 WHERE ugr_ID = $recID", 'Cannot set new Owner\'s ID to 2');
        $this->_updateDbTable("UPDATE sysUGrps SET ugr_ID = $recID WHERE ugr_ID =  $maxUsrID", 'Cannot set original Owner\'s ID to ' . $recID);

        /* Add new DB Owner as admin to retrieved list of IDs */
        $query = "INSERT INTO sysUsrGrpLinks (ugl_UserID, ugl_Role, ugl_GroupID) VALUES ";

        foreach ($groupIds as $groupId) {
            if ($groupId == 0) {
                continue;
            }
            $query = $query . "(2, 'admin', $groupId), ";
        }

        $query = substr($query, 0, -2); /* Remove last characters, space, and comma */
        $this->_updateDbTable($query, 'Cannot assign new owner\'s administrator privilege for each group');

        // Commit
        if ($this->transaction) {
            mysql__end_transaction($mysqli, true, $this->keep_autocommit);
            return true;
        }

        return false;
    }

    /**
    * Helper function to execute a query and handle transaction errors.
    *
    * @param string $query The SQL query to execute.
    * @param string $errorMsg The error message to display if the query fails.
    * @return bool Returns true if the query succeeds, false on failure.
    */
    private function _updateDbTable($query, $errorMsg)
    {
        if ($this->transaction) {
            $mysqli = $this->system->getMysqli();
            $mysqli->query($query);

            if ($mysqli->error || $mysqli->affected_rows < 0) {
                $this->system->addError(HEURIST_DB_ERROR, $errorMsg, $mysqli->error);
                mysql__end_transaction($mysqli, false, $this->keep_autocommit);
                $this->transaction = false;
                return false;
            }
        }
        return true;
    }




    //
    // special and batch action for users
    // 1) import users from another db
    // 2) transfer database ownership to another user
    //
    public function batch_action($ignore_permissions=false){

        $provided_pwd = $this->data['check_password'];
        $import_from_login = !empty($provided_pwd);

        if(!$import_from_login && !$ignore_permissions && !$this->system->isAdmin()){
            $this->system->addError(HEURIST_REQUEST_DENIED,
                'You are not admin and can\'t add/edit other users. Insufficient rights (logout/in to refresh) for this operation');
            return false;
        }

        if(@$this->data['transferOwner']){
            return $this->transferOwner();
        }

        //import users from another db

        // validate that current user is admin in source database as well
        $sytem_source = new System();
        if(!$sytem_source->init($this->data['sourceDB'], true, false)){
            return false;
        }

        //user ids
        $userIDs = prepareIds(@$this->data['userIDs']);
        if(empty($userIDs)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid user identificators');
            return false;
        }
        //group roles
        $roles = @$this->data['roles'];
        if(!$import_from_login && (isEmptyArray($roles))){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Group roles for import users are not defined');
            return false;
        }

        if($import_from_login && count($userIDs) !== 1){ // check if request is importing user from login popup, requires password for checking
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid request made for importing users');
            return false;
        }

        $exit_if_exists = (@$this->data['exit_if_exists']!=0);//proceed even user already exists

        $mysqli = $this->system->getMysqli();

        //find users that are already in this database
        $query = 'SELECT distinct src.ugr_ID, dest.ugr_ID from '
        .$sytem_source->dbnameFull().'.sysUGrps as src, sysUGrps as dest'
        .' where src.ugr_ID in ('.implode(',',$userIDs)
        .') and ((src.ugr_eMail = dest.ugr_eMail) OR (src.ugr_Name=dest.ugr_Name))';

        $userIDs_already_exists = mysql__select_assoc2($mysqli, $query);

        if($exit_if_exists && count($userIDs_already_exists)==count($userIDs)){
            $this->system->addError(HEURIST_NOT_FOUND,
                'It appears that all users selected to import exist in current database.');
            return false;
        }
        if(!empty($userIDs_already_exists)){
            $userIDs = array_diff($userIDs, array_keys($userIDs_already_exists));
        }

        $keep_autocommit = mysql__begin_transaction($mysqli);

        $overwrite_status = null;
        if($import_from_login && count($userIDs) == 1){

            $usr_id = intval($userIDs[0]);

            $usr_pwd = mysql__select_value($sytem_source->getMysqli(), "SELECT ugr_Password FROM sysUGrps WHERE ugr_ID = $usr_id");

            if(!$usr_pwd || !passwordCheck($provided_pwd, $usr_pwd, $sytem_source->getMysqli(), $usr_id)){

                $src_db = $sytem_source->dbname();
                $err_type = !$usr_pwd ? HEURIST_DB_ERROR : HEURIST_ACTION_BLOCKED;
                $err_msg = !$usr_pwd ? "An error ocurred while attempting to retrieve the password for your account on $src_db" : "Password is incorrect";

                $this->system->addError($err_type, $err_msg);
                return false;
            }

            $overwrite_status = 'y_no_delete';//y_no_add_delete
        }

        //1. import users from another database
        $fields = "ugr_Type,ugr_Name,ugr_LongName,ugr_Description,ugr_Password,ugr_eMail,".
        "ugr_FirstName,ugr_LastName,ugr_Department,ugr_Organisation,ugr_City,".
        "ugr_State,ugr_Postcode,ugr_Interests,ugr_Enabled,ugr_LastLoginTime,".
        "ugr_MinHyperlinkWords,ugr_LoginCount,ugr_IsModelUser,".
        "ugr_IncomingEmailAddresses,ugr_TargetEmailAddresses,ugr_URLs,ugr_FlagJT";

        $query1 = "insert into sysUGrps ($fields) ".
        "SELECT $fields ".
        "FROM ".$sytem_source->dbnameFull().".sysUGrps where ugr_ID=";


        $newUserIDs = array();

        $ret = true;
        foreach($userIDs as $userID){
            $res = $mysqli->query($query1.$userID);
            if($res){
                $userID_new = $mysqli->insert_id;
                $newUserIDs[] = $userID_new;

                if(!empty($overwrite_status)){

                    mysql__insertupdate($mysqli, 'sysUGrps', 'ugr', array('ugr_ID' => $userID_new, 'ugr_Enabled' => $overwrite_status));
                    $overwrite_status = null;
                }

                //copy user image
                $user_image = parent::getEntityImagePath($userID, null, $this->data['sourceDB']);//in source db
                if(file_exists($user_image)){

                    $extension = pathinfo($user_image, PATHINFO_EXTENSION);

                    $user_image_new = parent::getEntityImagePath($userID_new,null,null,$extension);//in this database

                    fileCopy($user_image, $user_image_new);

                    $user_thumb = parent::getEntityImagePath($userID, 'thumb', $this->data['sourceDB']);//in source db
                    $user_thumb_new = parent::getEntityImagePath($userID_new, 'thumb');//in this db
                    if(file_exists($user_thumb)){
                        fileCopy($user_thumb, $user_thumb_new);
                    }
                }

            }else{
                $this->system->addError(HEURIST_DB_ERROR,'Can\'t import users from '.$this->data['sourceDB'], $mysqli->error);
                $ret = false;
                break;
            }
        }

        //2. apply roles
        if($ret && !$import_from_login){

            foreach ($roles as $groupID=>$role){
                $values = array();
                $remove = null;
                foreach ($newUserIDs as $usrID){
                    array_push($values, ' ('. $groupID .' , '. $usrID .', "'.$role.'")');
                }
                if(!empty($userIDs_already_exists)){ //apply for user that already exists
                    $remove = array_values($userIDs_already_exists);
                    foreach ($userIDs_already_exists as $srcID=>$usrID){
                        array_push($values, ' ('. $groupID .' , '. $usrID .', "'.$role.'")');
                    }
                }

                if(!isEmptyArray($remove)){
                    $query = 'DELETE FROM sysUsrGrpLinks WHERE ugl_GroupID='.$groupID.' AND ugl_UserID in ('
                    .implode(',',$remove).')';
                    $res = $mysqli->query($query);
                    if(!$res){
                        $ret = false;
                        $this->system->addError(HEURIST_DB_ERROR, 'Can\'t remove roles for existing users in workgroup #'.$groupID, $mysqli->error );
                        break;
                    }
                }

                $query = 'INSERT INTO sysUsrGrpLinks (ugl_GroupID, ugl_UserID, ugl_Role) VALUES '
                .implode(',', $values);

                $res = $mysqli->query($query);
                if(!$res){
                    $ret = false;
                    $this->system->addError(HEURIST_DB_ERROR,
                        'Can\'t set role in workgroup #'.$groupID, $mysqli->error );
                    break;
                }

            }
        }

        if($ret){
            $ret = 'Users imported: '.count($newUserIDs);
            if (!empty($userIDs_already_exists)){
                $ret = $ret.'. Users already exists: '.count($userIDs_already_exists);
            }

            if(count($newUserIDs) == 1 && $import_from_login){ // email DB Owner
                user_EmailAboutNewUser($this->system, $newUserIDs[0], true);
            }
        }

        mysql__end_transaction($mysqli, $ret, $keep_autocommit);


        return $ret;
    }
}
