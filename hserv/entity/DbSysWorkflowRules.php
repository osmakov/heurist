<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;
use hserv\utilities\USanitize;

    /**
    * db access to usrReminders table
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

class DbSysWorkflowRules extends DbEntityBase
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
    *  @todo overwrite
    */
    public function search(){


        if(parent::search()===false){
              return false;
        }

        //special case -> find all rectypes with rules
        if(@$this->data['details']=='rty'){
            $query = 'SELECT DISTINCT swf_RecTypeID FROM '.$this->config['entityName'];
            $result = $this->searchMgr->execute($query, true, $this->config['entityName'], null);
            return $result;
        }

        $needCheck = false;

        //compose WHERE
        $where = array();
        $from_table = array($this->config['tableName']);

        $pred = $this->searchMgr->getPredicate('swf_ID');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('swf_RecTypeID');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('swf_Stage');
        if($pred!=null) {array_push($where, $pred);}


        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){

            $this->data['details'] = 'swf_ID';

        }elseif(@$this->data['details']=='name' || @$this->data['details']=='list' || @$this->data['details']=='full'){

            $this->data['details'] = 'swf_ID,swf_RecTypeID,swf_Stage,swf_Order,swf_StageRestrictedTo,swf_SetOwnership,swf_SetVisibility,swf_SendEmail,swf_EmailList,swf_RecEmailField,swf_EmailText';

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
        //compose ORDER BY
        $order = array('swf_RecTypeID, swf_Order, swf_Stage ASC');

        $is_ids_only = (count($this->data['details'])==1);

        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS  '.implode(',', $this->data['details'])
        .' FROM '.implode(',', $from_table);

        if(!empty($where)){
            $query = $query.SQL_WHERE.implode(SQL_AND,$where);
        }
        if(!empty($order)){
            $query = $query.' ORDER BY '.implode(',',$order);
        }

        $query = $query.$this->searchMgr->getLimit().$this->searchMgr->getOffset();
        $calculatedFields = null;

        $result = $this->searchMgr->execute($query, $is_ids_only, $this->config['entityName'], $calculatedFields);

        return $result;
    }

    //
    //
    //
    protected function prepareRecords(){

        $ret = parent::prepareRecords();

        //add specific field values
        foreach($this->records as $idx=>$record){

            if($this->records[$idx]['swf_StageRestrictedTo']==''){
                $this->records[$idx]['swf_StageRestrictedTo'] = null;
            }
            if($this->records[$idx]['swf_SetOwnership']==''){
                $this->records[$idx]['swf_SetOwnership'] = null;
            }
            if($this->records[$idx]['swf_SetVisibility']==''){
                $this->records[$idx]['swf_SetVisibility'] = null;
            }
            if($this->records[$idx]['swf_SendEmail']==''){
                $this->records[$idx]['swf_SendEmail'] = null;
            }
            if($this->records[$idx]['swf_Order']=='' || $this->records[$idx]['swf_Order']<0){
                $this->records[$idx]['swf_Order'] = 0;
            }elseif($this->records[$idx]['swf_Order']>255){
                $this->records[$idx]['swf_Order'] = 255;
            }

            if(intval($this->records[$idx]['swf_RecEmailField']) <= 0){
                $this->records[$idx]['swf_RecEmailField'] = null;
            }

            if($this->records[$idx]['swf_EmailList'] == ''){
                $this->records[$idx]['swf_EmailList'] = null;
            }else{
                $list = explode(',', $this->records[$idx]['swf_EmailList']);
                $list = array_filter($list, function($email){
                    return filter_var($email, FILTER_VALIDATE_EMAIL);
                });
                $this->records[$idx]['swf_EmailList'] = empty($list) ? null : implode(',', $list);
            }

            if(!empty($this->records[$idx]['swf_EmailText'])){
                $this->records[$idx]['swf_EmailText'] = USanitize::sanitizeString($this->records[$idx]['swf_EmailText']);
                $this->records[$idx]['swf_EmailText'] = str_replace(["\r\n", "\r", "\n"], "<br>", $this->records[$idx]['swf_EmailText']);
            }
        }

        return $ret;

    }

    // Operations:
    // 1) adds entire ruleset for record type
    // 2) set order of stages per record type
    //
    public function batch_action(){

        $ret = true;
        $rty_ID = @$this->data['rty_ID'];
        if($rty_ID>0){

            $mysqli = $this->system->getMysqli();

            if(mysql__select_value($mysqli,
            'SELECT swf_RecTypeID FROM sysWorkflowRules where swf_RecTypeID='.$rty_ID.' LIMIT 1')>0){

                $this->system->addError(HEURIST_ACTION_BLOCKED, 'There are already rules for record type '.$rty_ID);
                $ret = false;
            }else{

                if(!$this->system->isAdmin()){

                    $this->system->addError(HEURIST_REQUEST_DENIED,
                        'You are not DB admin. Insufficient rights (logout/in to refresh) for this operation');
                    $ret = false;
                }else{

                    $this->system->defineConstant('TRM_SWF');
                    $query = 'INSERT INTO sysWorkflowRules (swf_RecTypeID,swf_Stage) SELECT '
                    .$rty_ID.', trm_ID FROM defTerms where trm_ParentTermID='.TRM_SWF.' ORDER BY trm_Label';
                    $ret = $mysqli->query($query);
                    if(!$ret){
                        $this->system->addError(HEURIST_DB_ERROR,
                            'Cannot add ruleset to sysWorkflowRules table', $mysqli->error);
                        $ret = false;
                    }
                }
            }

        }else{
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Record type not defined');
            $ret = false;

        }

        return $ret;
    }


}
?>
