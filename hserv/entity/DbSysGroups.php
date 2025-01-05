<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;

    /**
    * db access to usrUGrps table for workgroups
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

require_once dirname(__FILE__).'/../records/edit/recordModify.php';//for recordDelete
require_once dirname(__FILE__).'/../records/search/recordFile.php';


class DbSysGroups extends DbEntityBase
{

    public function __construct( $system, $data=null ) {
       parent::__construct( $system, $data );
       $this->requireAdminRights = false;
    }

    /**
    *  search groups
    *
    *  other parameters :
    *  details - id|name|list|all or list of table fields
    *  offset
    *  limit
    *  request_id
    */
    public function search(){


        if(parent::search()===false){
              return false;
        }

        $needCheck = false;
        $needRole = false;
        $needCount = false;  //find members count
        $is_ids_only = false;

        //compose WHERE
        $where = array('ugr_Type="workgroup"');
        $from_table = array($this->config['tableName']);

        $pred = $this->searchMgr->getPredicate('ugr_ID');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('ugr_Name');
        if($pred!=null) {array_push($where, $pred);}

        //find groups where this user is member or admin
        $pred = $this->searchMgr->getPredicate('ugl_UserID');
        if($pred!=null) {

                $needRole = true;
                $where2 = array();
                array_push($where2, $pred);
                $pred = $this->searchMgr->getPredicate('ugl_Role');
                if($pred!=null) {
                    array_push($where2, $pred);
                }
                array_push($where2, '(ugl_GroupID = ugr_ID)');

                if(@$this->data['ugl_Join']){ //always search for role

                    $from_table[0] = $from_table[0].' LEFT JOIN sysUsrGrpLinks ON '.implode(SQL_AND,$where2);

                }else{
                    $where = array_merge($where,$where2);
                    array_push($from_table, 'sysUsrGrpLinks');
                }
        }

        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){

            $this->data['details'] = 'ugr_ID';
            $is_ids_only = true;

        }elseif(@$this->data['details']=='name'){

            $this->data['details'] = 'ugr_ID,ugr_Name';

        }elseif(@$this->data['details']=='count'){

            $this->data['details'] = 'ugr_ID';
            $needCount = true;

        }elseif(@$this->data['details']=='list' || @$this->data['details']=='full'){

            $this->data['details'] = 'ugr_ID,ugr_Name,ugr_LongName,ugr_Description,ugr_Enabled';
            if($needRole) {
                $this->data['details'] .= ',ugl_Role';
            }
            $needCount = true;

        }else{
            $needCheck = true;
        }

        if(!is_array($this->data['details'])){ //specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);
        }

        //validate names of fields
        if($needCheck && !$this->_validateFieldsForSearch()){
            return false;
        }

        //----- order by ------------
        $orderby = $this->searchMgr->setOrderBy();

        //$is_ids_only = (count($this->data['details'])==1);

        if($needCount || strpos($orderby, 'ugr_Members')!==false){
            array_push($this->data['details'],
                '(select count(*) from sysUsrGrpLinks where (ugl_GroupID=ugr_ID)) as ugr_Members');
        }

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

        if(!$this->system->isDbOwner() && !isEmptyArray($this->recordIDs)){ //there are records to update/delete

            $ugrID = $this->system->getUserId();

            $mysqli = $this->system->getMysqli();

            $recIDs_norights = mysql__select_list($mysqli, $this->config['tableName'].',sysUsrGrpLinks',
                $this->primaryField,
                    '( usr_ID in ('.implode(',', $this->recordIDs)
                    .') ) AND ( ugl_GroupID=ugr_ID ) AND ( ugl_Role=\'admin\' ) AND ugl_UserID!='.$ugrID);


            $cnt = is_array($recIDs_norights)?count($recIDs_norights):0;

            if($cnt>0){
                $this->system->addError(HEURIST_REQUEST_DENIED,
                    'You are not an admin of group. Insufficient rights (logout/in to refresh) for this operation');
                return false;
            }
        }

        return true;
    }

    //
    //
    //
    protected function prepareRecords(){

        $ret = parent::prepareRecords();

        //add specific field values
        foreach($this->records as $idx=>$record){
            $this->records[$idx]['ugr_Type'] = 'workgroup';
            $this->records[$idx]['ugr_Modified'] = date(DATE_8601);//reset
            $this->records[$idx]['ugr_Password'] = 'PASSWORD NOT REQUIRED';
            $this->records[$idx]['ugr_eMail'] = 'EMAIL NOT SET FOR '.$this->records[$idx]['ugr_Name'];

            //validate duplication
            if(!$this->doDuplicationCheck($idx, 'ugr_Name', 'Workgroup cannot be saved. The provided name already exists')){
                    return false;
            }

        }

        return $ret;

    }

    //
    // add current user as admin for new group
    //
    public function save(){

        $savedRecIds = parent::save();

        if($savedRecIds!==false){

            //treat group image
            foreach($this->records as $record){
                $group_ID = @$record['ugr_ID'];
                if($group_ID && in_array($group_ID, $savedRecIds)){
                    $thumb_file_name = @$record['ugr_Thumb'];

                    //rename it to recID.png
                    if($thumb_file_name){
                        parent::renameEntityImage($thumb_file_name, $group_ID);
                    }

                    if(!in_array($group_ID, $this->recordIDs )){ //add current user as admin for new group

                        $admin_role = array();
                        $admin_role['ugl_GroupID'] = $group_ID;
                        $admin_role['ugl_UserID'] = $this->system->getUserId();
                        $admin_role['ugl_Role'] = 'admin';
                        $res = mysql__insertupdate($this->system->getMysqli(), 'sysUsrGrpLinks', 'ugl', $admin_role);

                        //$fname = HEURIST_FILESTORE_DIR.$this->system->getUserId();
                        //fileSave('X',$fname); on save
                    }
                }
            }
        }

        return $savedRecIds;

    }

    //
    // delete group
    //
    public function delete($disable_foreign_checks = false){

        $this->recordIDs = null; //reset to obtain ids from $data

        $this->foreignChecks = array(
                    array('SELECT FIND_IN_SET(1, "#IDS#")','Cannot remove "Database Owners" group'),
                    array('SELECT count(rec_ID) FROM Records WHERE rec_FlagTemporary=0 AND rec_OwnerUGrpID IN (#IDS#) LIMIT 1',
                          'Deleting Group with existing Records not allowed')
                );

        if(!$this->deletePrepare()){
            return false;
        }

        $mysqli = $this->system->getMysqli();

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

        //find affected users
        $query = 'SELECT ugl_UserID FROM sysUsrGrpLinks'
            . SQL_WHERE . predicateId('ugl_GroupID',$this->recordIDs);

        $affectedUserIds = mysql__select_list2($mysqli, $query);

        //remove from roles table
        $query = 'DELETE FROM sysUsrGrpLinks'
            . SQL_WHERE . predicateId('ugl_GroupID',$this->recordIDs);

        $res = $mysqli->query($query);
        if(!$res){
            $this->system->addError(HEURIST_DB_ERROR,
                            'Cannot remove entries from user/group links (sysUsrGrpLinks)',
                            $mysqli->error );
            $ret = false;
        }
        $query = 'DELETE FROM usrSavedSearches  WHERE svs_UGrpID in (' . implode(',', $this->recordIDs) . ')';
        $mysqli->query($query);
        $query = 'DELETE FROM usrTags  WHERE tag_UGrpID in (' . implode(',', $this->recordIDs) . ')';
        $mysqli->query($query);
        $query = 'DELETE FROM usrRecPermissions  WHERE rcp_UGrpID in (' . implode(',', $this->recordIDs) . ')';
        $mysqli->query($query);

        if($ret){
            $ret = parent::delete();

            if(!isEmptyArray(@$affectedUserIds)){
                foreach($affectedUserIds as $usrID)  //affected users
                {
                    if($usrID!=$this->system->getUserId()){
                            $usrID = intval($usrID);
                            $fname = $this->getEntityImagePath($usrID);
                            if(file_exists($fname)){
                                unlink($fname);
                            }
                    }
                }
            }
        }

        mysql__end_transaction($mysqli, $ret, $keep_autocommit);

        return $ret;
    }

    //
    // batch action for groups - add/remove users to/from group
    // parameters
    // groupID  - affected group
    // userIDs  - user roles to be changed
    // role - remove admin member
    //
    public function batch_action(){

        if(!in_array(@$this->data['role'],array('remove','admin','member'))){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid parameter "role"');
            return false;
        }

        //group ids
        $this->recordIDs = prepareIds(@$this->data['groupID']);
        if(empty($this->recordIDs)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid workgroup identificator');
            return false;
        }

        //user ids
        $assignIDs = prepareIds(@$this->data['userIDs']);
        if(empty($assignIDs)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid user identificators');
            return false;
        }

        if(!$this->_validatePermission()){
            return false;
        }

        $mysqli = $this->system->getMysqli();

        $ret = true;


        //group cannot be without admin.
        if($this->data['role']=='remove' || $this->data['role']=='member'){

            //verification
            foreach ($this->recordIDs as $groupID){
                foreach ($assignIDs as $usrID){

                    $query = 'SELECT count(g2.ugl_ID) FROM sysUsrGrpLinks AS g2 LEFT JOIN sysUsrGrpLinks AS g1 '
                                .'ON g1.ugl_GroupID=g2.ugl_GroupID AND g2.ugl_Role="admin" '                             //is it the only admin
                                .'WHERE g1.ugl_UserID='.$usrID.' AND g1.ugl_Role="admin" AND g1.ugl_GroupID='.$groupID;  //is this user admin

                    //can't remove last admin
                    $cnt = mysql__select_value($mysqli, $query);
                    if($cnt==1){
                        $this->system->addError(HEURIST_ACTION_BLOCKED,
                            'It is not possible to '.(($this->data['role']=='remove')?'remove':' change role to" member" for')
                            .' user #'.$usrID.' from group #'.$groupID.'. This user is the only admin of the workgroup');
                        return false;
                    }
                }
            }

        }


        $keep_autocommit = mysql__begin_transaction($mysqli);

        $query2 = 'DELETE FROM sysUsrGrpLinks'
            . SQL_WHERE . predicateId('ugl_GroupID',$this->recordIDs)
            . SQL_AND . predicateId('ugl_UserID',$assignIDs);

        $res = $mysqli->query($query2);
        if(!$res){
            $this->system->addError(HEURIST_DB_ERROR, 'Can\'t remove users from workgroup', $mysqli->error );
            $ret = false;
        }

        if($this->data['role']!='remove'){

            foreach ($this->recordIDs as $groupID){
                $query = array();
                foreach ($assignIDs as $usrID){
                    array_push($query, ' ('. $groupID .' , '. $usrID .', "'.$this->data['role'].'")');
                }
                $query = 'INSERT INTO sysUsrGrpLinks (ugl_GroupID, ugl_UserID, ugl_Role) VALUES '
                        .implode(',', $query);
                $res = $mysqli->query($query);
                if(!$res){
                    $ret = false;
                    $this->system->addError(HEURIST_DB_ERROR,
                        'Can\'t set role in workgroup #'.$groupID, $mysqli->error );
                    break;
                }
            }//foreach

        }

        mysql__end_transaction($mysqli, $ret, $keep_autocommit);

        return $ret;
    }

}
?>
