<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;

    /**
    * db access to recThreadedComments.php table
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

class DbRecThreadedComments extends DbEntityBase
{

    /**
    *  search usrReminders
    *
    *  other parameters :
    *  details - id|name|list|all or list of table fields
    *  offset
    *  limit
    *  request_id
    *
    */
    public function search(){

        if(!@$this->data['cmt_OwnerUgrpID']){
            $this->data['cmt_OwnerUgrpID'] = $this->system->getUserId();
        }

        if(parent::search()===false){
              return false;
        }

        $needRecords = false;

        $this->searchMgr->addPredicate('cmt_ID');
        $this->searchMgr->addPredicate('cmt_OwnerUgrpID');
        $this->searchMgr->addPredicate('cmt_RecID');
        $this->searchMgr->addPredicate('cmt_Text');

        switch (@$this->data['details']){
            case 'id': $fieldList = 'cmt_ID'; break;
            case 'list':
                $needRecords = true;
                $fieldList = 'cmt_ID,cmt_RecID,cmt_ParentCmtID,cmt_OwnerUgrpID,SUBSTRING(cmt_Text,1,50) as cmt_Text,cmt_Modified';
                break;
            case 'name':
                $fieldList = 'cmt_ID,cmt_RecID,cmt_ParentCmtID,cmt_OwnerUgrpID,SUBSTRING(cmt_Text,1,50) as cmt_Text,cmt_Modified';
                break;
            default:   //'full'
                $fieldList = 'cmt_ID,cmt_RecID,cmt_ParentCmtID,cmt_OwnerUgrpID,cmt_Text,cmt_Modified';
                break;
        }

        $orderby = $this->searchMgr->setOrderBy();
        if($orderby!=null && strpos('recTitle',$orderby)===0){
            $needRecords = true;
        }

        if($needRecords){ //return rec_Title for comment
              $fieldList .= ', rec_Title as cmt_RecTitle';
              $sup_tables = ', Records';
              $sup_where = '(rec_ID=cmt_RecID)';
        }

        $this->searchMgr->setSelFields($fieldList);

        return $this->searchMgr->composeAndExecute($orderby, $sup_tables, $sup_where);
    }

    //
    // validate permission for edit comment
    // for delete and assign see appropriate methods
    //
    protected function _validatePermission(){

        if(!$this->system->isDbOwner() && !isEmptyArray($this->recordIDs)){ //there are records to update/delete

            $ugrID = $this->system->getUserId();

            $mysqli = $this->system->getMysqli();

            $recIDs_norights = mysql__select_list($mysqli, $this->config['tableName'], $this->primaryField,
                    'cmt_ID in ('.implode(',', $this->recordIDs).') AND cmt_OwnerUgrpID!='.$ugrID);

            $cnt = (is_array($recIDs_norights))?count($recIDs_norights):0;

            if($cnt>0){
                $this->system->addError(HEURIST_REQUEST_DENIED,
                (($cnt==1 && (!is_array($this->records) || count($this->records)==1))
                    ? 'Comment belongs'
                    : $cnt.' Comments belong')
                    .' to other user. Insufficient rights (logout/in to refresh) for this operation');
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
            $rec_ID = intval(@$record[$this->primaryField]);
            $isinsert = ($rec_ID<1);
            if($isinsert){
                if(!($this->records[$idx]['cmt_OwnerUgrpID']>0)){
                    $this->records[$idx]['cmt_OwnerUgrpID'] = $this->system->getUserId();
                }
            }
            $this->records[$idx]['cmt_Modified'] = date(DATE_8601);//reset
        }

        return $ret;

    }

    //
    // batch action for comments - changing flag for cmt_Deleted
    //
    public function batch_action(){

        $recordIDs = prepareIds($this->data['recIDs']);
        if(!empty($recordIDs)){
            //find record by ids  - todo

        }

        if(!$this->prepareRecords()){
                return false;
        }

        $mysqli = $this->system->getMysqli();

        foreach($this->records as $record){

            if($record[$this->primaryField]>0){
                $this->recordIDs[] = $record[$this->primaryField];
            }


        }//for comments


        return true;
    }

}
?>
