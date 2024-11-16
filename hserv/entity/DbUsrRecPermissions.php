<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;

    /**
    * db access to usrRecPermissions table
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

class DbUsrRecPermissions extends DbEntityBase
{
    private $is_table_exists = false;

    public function init(){

        $mysqli = $this->system->getMysqli();

        $this->is_table_exists = hasTable($mysqli, 'sysImportFiles');

        if(!$this->is_table_exists){

            $query = 'CREATE TABLE IF NOT EXISTS `usrRecPermissions` ('
              ."`rcp_ID` int(10) unsigned NOT NULL auto_increment COMMENT 'Primary table key',"
              ."`rcp_UGrpID` smallint(5) unsigned NOT NULL COMMENT 'ID of group',"
              ."`rcp_RecID` int(10) unsigned NOT NULL COMMENT 'The record to which permission is linked',"
              ."`rcp_Level` enum('view','edit') NOT NULL default 'view' COMMENT 'Level of permission',"
              ."PRIMARY KEY  (rcp_ID)"
              //."UNIQUE KEY rcp_composite_key (rcp_RecID,rcp_UGrpID)"
            .") ENGINE=InnoDB COMMENT='Permissions for groups to records'";

            if ($mysqli->query($query)) {
                $this->is_table_exists = true;
            }

            $query = 'DROP INDEX IF EXISTS rcp_composite_key ON usrRecPermissions';
            $res = $mysqli->query($query);
        }

    }

    /**
    */
    public function isvalid(){
        return $this->is_table_exists && parent::isvalid();
    }


    /**
    *  search import sessions
    *
    *  sysUGrps.ugr_ID
    *  sysUGrps.ugr_Type
    *  sysUGrps.ugr_Name
    *  sysUGrps.ugr_Enabled
    *  sysUGrps.ugr_Modified
    *  sysUsrGrpLinks.ugl_UserID
    *  sysUsrGrpLinks.ugl_GroupID
    *  sysUsrGrpLinks.ugl_Role
    *  (omit table name)
    *
    *  other parameters :
    *  details - id|name|list|all or list of table fields
    *  offset
    *  limit
    *  request_id
    *
    *  @todo overwrite
    */
    public function search(){

        if(parent::search()===false){
              return false;
        }

        //compose WHERE
        $where = array();

        $pred = $this->searchMgr->getPredicate('rcp_RecID');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('rcp_UGrpID');
        if($pred!=null) {array_push($where, $pred);}

        $needCheck = false;

        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){

            $this->data['details'] = 'rcp_ID';

        }elseif(@$this->data['details']=='full'){

            $this->data['details'] = implode(',', $this->fields );
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

        $is_ids_only = (count($this->data['details'])==1);

        $from_table = $this->config['tableName'];

        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS  '.implode(',', $this->data['details']).' FROM '.$from_table;

         if(!empty($where)){
            $query = $query.SQL_WHERE.implode(SQL_AND,$where);
         }
         $query = $query.$this->searchMgr->getLimit().$this->searchMgr->getOffset();

        $res = $this->searchMgr->execute($query, $is_ids_only, $from_table);
        return $res;
    }

    //
    // at the moment we assign only "view" permissions
    // thus, check whether current user can change/edit the record
    //
    // similar see recordCanChangeOwnerwhipAndAccess
    //
    protected function _validatePermission(){

        if($this->system->isAdmin()){  //admin can always change any record
            return true;
        }else{
            $recids = array();
            foreach($this->records as $record){
                $recids[] = $record['rcp_RecID'];
            }
            $recids = array_unique($recids);
            $grp_ids = $this->system->getUserGroupIds();//current user groups ids + itself

            //verify that current owner is "everyone" or current user is member of owner group
            $query = 'SELECT count(rec_OwnerUGrpID) FROM Records WHERE '
                .predicateId('rec_ID',$recids)
                .SQL_AND
                .'(rec_OwnerUGrpID=0 OR '
                .predicateId('rec_OwnerUGrpID',$grp_ids).')';

            $cnt = mysql__select_value($this->system->getMysqli(), $query);
            if($cnt<count($recids)){

                if(count($recids)==1){
                    $sMsg = 'the record ID:'.$recids[0];
                }else{
                    $sMsg =  (($cnt==0)?'all':((count($recids)-$cnt).' of '.count($recids)))
                                .' records provided in request';
                }

                $this->system->addError(HEURIST_REQUEST_DENIED,
                    'Current user does not have sufficient authority to change '.$sMsg
                    .'. User must be either the owner or member of the group that owns record');
                    return false;

            }

            return true;
        }
    }

    //
    //
    //
    public function save(){

        //extract records from $_REQUEST data
        if(!$this->prepareRecords()){
                return false;
        }

        //validate permission for current user and set of records see $this->recordIDs
        if(!$this->_validatePermission()){ //is records permission to be set belongs to owner
            return false;
        }

        $recids = array();
        //validate values and check mandatory fields
        foreach($this->records as $record){

            $this->data['fields'] = $record;

            //validate mandatory fields
            if(!$this->_validateMandatory()){
                return false;
            }

            //validate values
            if(!$this->_validateValues()){
                return false;
            }

            $recids[] = $record['rcp_RecID'];
        }
        $recids = array_unique($recids);

        //array of inserted or updated record IDs
        $results = array();

        //start transaction
        $mysqli = $this->system->getMysqli();

        $keep_autocommit = mysql__begin_transaction($mysqli);

        //remove all current permissions
        $query = SQL_DELETE.$this->config['tableName']
                                .SQL_WHERE
                                .predicateId('rcp_RecID',$recids);
        $res = $mysqli->query( $query );
        if(!$res){
             $this->system->addError(HEURIST_DB_ERROR,
                        'Cannot delete current permissions', $mysqli->error);
        }else{

            //add new permissions
            $query = array();
            foreach($this->records as $rec_idx => $record){
                $query[] = '(' .$record['rcp_UGrpID'] .',' . $record['rcp_RecID'] . ', "view" )';
            }
            $query = ' INSERT INTO '.$this->config['tableName']
               .' (rcp_UGrpID,rcp_RecID,rcp_Level) VALUES '.implode(',', $query);

            $res = $mysqli->query( $query );
            if(!$res){
                $this->system->addError(HEURIST_DB_ERROR,
                        'Cannot save data in table '.$this->config['entityName'], $mysqli->error);
            }else{
                $res = array($mysqli->insert_id);
            }
        }

        mysql__end_transaction($mysql, $res, $keep_autocommit);

        return $res;

    }


    //
    // delete permissions for given Record IDs or Group IDs
    // see parameters $this->data['rcp_RecID'] or $this->data['rcp_UGrpID']
    //
    public function delete($disable_foreign_checks = false){

        //extract records from $_REQUEST data
        $mysqli = $this->system->getMysqli();

        if(!@$this->data['rcp_RecID']){ //array of record ids

            $this->records = array();//need to validate permissions
            $recids = prepareIds($this->data['rcp_RecID']);
            foreach ($recids as $id){
                $this->records = array('rcp_RecID'=>$id);
            }

            if(!$this->_validatePermission()){
                return false;
            }

            $query = SQL_DELETE.$this->config['tableName']
                                .SQL_WHERE
                                .predicateId('rcp_RecID',$recids);

            $res = $mysqli->query( $query );
            if(!$res){
                 $this->system->addError(HEURIST_DB_ERROR,
                            'Cannot delete permissions', $mysqli->error);
                 return false;
            }

        }elseif(!@$this->data['rcp_UGrpID']){ //array of group ids

            $group_ids_to_delete = prepareIds($this->data['rcp_UGrpID']);

            //current user must be a member of all provided groups
            $grp_ids = $this->system->getUserGroupIds();//current user groups ids + itself

            foreach ($group_ids_to_delete as $id){
                if(!in_array($id, $grp_ids)){
                    $this->system->addError(HEURIST_REQUEST_DENIED,
                        'Current user does not have sufficient authority to remove permissions. '
                        .' User must be either the owner or member of the group that owns record');
                    return false;
                }
            }

            $query = SQL_DELETE.$this->config['tableName']
                                .SQL_WHERE
                                .predicateId('rcp_UGrpID',$group_ids_to_delete);

            $res = $mysqli->query( $query );
            if(!$res){
                 $this->system->addError(HEURIST_DB_ERROR,
                            'Cannot delete permissions for given groups', $mysqli->error);
                 return false;
            }

        }


        return true;
    }


}
?>
