<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;
use hserv\entity\DbDefTerms;
use hserv\utilities\USystem;
use hserv\utilities\USanitize;

    /**
    * db access to sysUGrpps table
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


class DbDefDetailTypes extends DbEntityBase
{
    /**
    *  search detail fields
    */
    public function search(){

        if(parent::search()===false){
              return false;
        }

        $this->searchMgr->addPredicate('dty_ID');
        $this->searchMgr->addPredicate('dty_Name');
        $this->searchMgr->addPredicate('dty_Type');
        $this->searchMgr->addPredicate('dty_Status');
        $this->searchMgr->addPredicate('dty_Modified');
        $this->searchMgr->addPredicate('dty_DetailTypeGroupID');

        switch (@$this->data['details']){
            case 'id': $this->searchMgr->setSelFields('dty_ID'); break;
            case 'name':
                $this->searchMgr->setSelFields('dty_ID,dty_Name');
                break;
            case 'list':
                $this->searchMgr->setSelFields('dty_ID,dty_Name,dty_ShowInLists,dty_HelpText,dty_Type,dty_Status,dty_DetailTypeGroupID');
                break;
            default:   // full
                $this->searchMgr->setSelFields(implode(',', $this->fieldNames));
        }

        $orderby = $this->searchMgr->setOrderBy();

        return $this->searchMgr->composeAndExecute($orderby);
    }

    //
    //
    //
    public function delete($disable_foreign_checks = false){

        if(!$this->deletePrepare()){
            return false;
        }

        if(count($this->recordIDs)>1){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'It is not possible to remove field types in batch');
            return false;
        }

        $dtyID = $this->recordIDs[0];

        $query = 'select dtl_RecID from recDetails where dtl_DetailTypeID='.$dtyID;
        $rec_IDs = mysql__select_list2($this->system->get_mysqli(), $query);

        if(is_array($rec_IDs) && count($rec_IDs) > 0){

            $query = 'select dty_Name from defDetailTypes where dty_ID='.$dtyID;
            $fld_name = mysql__select_value($this->system->get_mysqli(), $query);

            $this->system->addError(HEURIST_ACTION_BLOCKED,
                'You cannot delete field <strong>'. $fld_name .'</strong> as it is used <strong>'. count($rec_IDs) .'</strong> times in record data.<br><br>'
                .'<a href="#" onclick="window.open(\''. HEURIST_BASE_URL .'?db='. HEURIST_DBNAME .'&q=ids:'. implode(',', $rec_IDs) .'\',\'_blank\');">'
                .'Open these records in a search</a> to allow the removal of all instances of the '. $fld_name .' field.');
            return false;
        }

        return parent::delete();
    }

    //
    //
    //
    protected function prepareRecords(){

        $ret = parent::prepareRecords();

        $mysqli = $this->system->get_mysqli();
        //add specific field values
        foreach($this->records as $idx=>$record){

            $this->records[$idx]['is_new'] = (!(@$this->records[$idx]['dty_ID']>0));

            //validate duplication
            if(@$this->records[$idx]['dty_Name']){

                // Strip trailing + double spacing
                $this->records[$idx]['dty_Name'] = preg_replace("/\s\s+/", ' ', $this->records[$idx]['dty_Name']);
                $this->records[$idx]['dty_Name'] = super_trim($this->records[$idx]['dty_Name']);

                //validate duplication
                if(!$this->doDuplicationCheck($idx, 'dty_Name', 'Field type cannot be saved. The provided name already exists')){
//$this->system->addError(HEURIST_ACTION_BLOCKED, 'Field type cannot be saved. The provided name already exists', array('dty_id' => $res));
                        return false;
                }
            }

            if($this->records[$idx]['is_new']){
                $this->records[$idx]['dty_LocallyModified'] = 0; //default value for new

                if(@$this->records[$idx]['dty_IDInOriginatingDB']==''){
                    $this->records[$idx]['dty_IDInOriginatingDB'] = 0;
                }
            }else{
                //if enum or relmarker prevents vocabulary changing if there are records that use terms not in the new vocabulary
                if(@$this->records[$idx]['dty_Type']=='enum' || @$this->records[$idx]['dty_Type']=='relmarker'){

                    //get current vocabulary
                    $curr_vocab_id = mysql__select_value($mysqli,
                        'SELECT dty_JsonTermIDTree FROM '.$this->config['tableName'].' WHERE dty_ID='.
                            $this->records[$idx]['dty_ID']);
                    $curr_vocab_id = intval($curr_vocab_id);

                    if($curr_vocab_id>0 && $curr_vocab_id!=$this->records[$idx]['dty_JsonTermIDTree']){
                        //is going to be changed
                        $children = getTermChildrenAll($mysqli, $curr_vocab_id, true);
                        $new_children = getTermChildrenAll($mysqli, $this->records[$idx]['dty_JsonTermIDTree'], true);

                        $children = array_filter($children, function($id) use ($new_children) { return !in_array($id, $new_children);});

                        $s = predicateId('dtl_Value', $children, SQL_AND);

                        if($s!=''){

                            $query = 'SELECT COUNT(DISTINCT dtl_RecID) FROM recDetails '
                                .'WHERE (dtl_DetailTypeID='.$this->records[$idx]['dty_ID'].') AND '
                                .'(dtl_Value '.$s.')';

                            $total_count_rows = mysql__select_value($mysqli, $query);
                            if($mysqli->error){
                                $this->system->addError(HEURIST_DB_ERROR,
                                    'Search query error (retrieving number of records that uses terms)', $mysqli->error);
                                return false;
                            }elseif($total_count_rows>0){
                                $this->system->addError(HEURIST_ACTION_BLOCKED,
                                    'Sorry, we cannot change the vocabulary because terms in the '
                                    .'current vocabulary are already in use for this field.');
                                return false;
                            }
                        }
                    }
                }

                if (array_key_exists('dty_IDInOriginatingDB',$this->records[$idx])
                     && $this->records[$idx]['dty_IDInOriginatingDB']==''){

                    $this->records[$idx]['dty_IDInOriginatingDB'] = null;
                    unset($this->records[$idx]['dty_IDInOriginatingDB']);
                }
                if(array_key_exists('dty_LocallyModified',$this->records[$idx])){
                    $this->records[$idx]['dty_LocallyModified'] = null;
                    unset($this->records[$idx]['dty_LocallyModified']);
                }
            }

            $this->records[$idx]['dty_Modified'] = date(DATE_8601);//reset

        }

        return $ret;

    }

    public function save(){

        $savedRecIds = parent::save();

        if($savedRecIds!==false){

            $dbID = $this->system->get_system('sys_dbRegisteredID');
            if(!($dbID>0)) {$dbID = 0;}

            $mysqli = $this->system->get_mysqli();

            foreach($this->records as $idx=>$record){
                $dty_ID = @$record['dty_ID'];
                if($dty_ID>0 && in_array($dty_ID, $savedRecIds)){

                    $query = null;
                    //set dbid or update modified locally
                    if($record['is_new']){

                        $query= 'UPDATE defDetailTypes SET dty_OriginatingDBID='.$dbID
                                .', dty_NameInOriginatingDB=dty_Name'
                                .', dty_IDInOriginatingDB='.$dty_ID
                                .' WHERE (NOT dty_OriginatingDBID>0 OR dty_OriginatingDBID IS NULL) AND dty_ID='.$dty_ID;

                    }else{
                        $query = 'UPDATE defDetailTypes SET dty_LocallyModified=IF(dty_OriginatingDBID>0,1,0)'
                                . ' WHERE dty_ID = '.$dty_ID;
                    }
                    $res = $mysqli->query($query);
                }
            }
        }
        return $savedRecIds;
    }

    //
    // batch action for rectypes
    // 1) import detailtypes from another db
    //
    public function batch_action(){

        $mysqli = $this->system->get_mysqli();

        $this->need_transaction = false;
        $keep_autocommit = mysql__begin_transaction($mysqli);

        $ret = true;

        if(@$this->data['csv_import']){ // import new detail type via CSV

            if(@$this->data['fields'] && is_string($this->data['fields'])){ // new to perform extra validations first
                $this->data['fields'] = json_decode($this->data['fields'], true);
            }

            $defTerms = new DbDefTerms($this->system);// to create new vocabs
            $new_vocabs = array();

            $vcg_query = 'SELECT vcg_ID FROM defVocabularyGroups WHERE vcg_Domain = "enum" ORDER BY vcg_Order';
            $vcg_enum = mysql__select_value($mysqli, $vcg_query);

            $vcg_query = 'SELECT vcg_ID FROM defVocabularyGroups WHERE vcg_Domain = "relation" ORDER BY vcg_Order';
            $vcg_rel = mysql__select_value($mysqli, $vcg_query);

            if(!empty($this->data['fields'])){

                $ret = array();

                foreach($this->data['fields'] as $idx => $record){

                    // Remove leading and trailing spaces
                    $record = array_map([USanitize::class, 'cleanupSpaces'], $record);

                    $ret[$idx] = array();
                    if(empty(@$record['dty_Name'])){ // check that a name has been provided
                        $ret[$idx][] = 'A name is required';
                    }else{ // check that the name hasn't been used yet
                        $exists = mysql__select_value($mysqli, 'SELECT dty_ID FROM defDetailTypes WHERE dty_Name="'. $record['dty_Name'] .'"');
                        if($exists){
                            $ret[$idx][] = $record['dty_Name'] . ' already exists as ID#' . $exists;
                        }
                    }
                    if(empty(@$record['dty_HelpText'])){ // check that a description has been provided
                        $ret[$idx][] = 'A description is required';
                    }
                    if(empty(@$record['dty_Type'])){ // check that a type has been provided
                        $ret[$idx][] = 'A field type is required';
                    }else{
                        $record['dty_Type'] = strtolower($record['dty_Type']);
                        switch ($record['dty_Type']) {

                            case 'text':
                            case 'freetext':
                                $this->data['fields'][$idx]['dty_Type'] = 'freetext';
                                $record['dty_Type'] = 'freetext';
                                break;

                            case 'memo':
                            case 'blocktext':
                                $this->data['fields'][$idx]['dty_Type'] = 'blocktext';
                                $record['dty_Type'] = 'blocktext';
                                break;

                            case 'date':
                            case 'datetime':
                                $this->data['fields'][$idx]['dty_Type'] = 'date';
                                $record['dty_Type'] = 'date';
                                break;

                            case 'number':
                            case 'numeric':
                            case 'float':
                            case 'integer':
                            case 'int':
                                $this->data['fields'][$idx]['dty_Type'] = 'float';
                                $record['dty_Type'] = 'float';
                                break;

                            case 'terms':
                            case 'term':
                            case 'enum':
                                $this->data['fields'][$idx]['dty_Type'] = 'enum';
                                $record['dty_Type'] = 'enum';
                                break;

                            case 'record pointer':
                            case 'recpointer':
                            case 'resource':
                                $this->data['fields'][$idx]['dty_Type'] = 'resource';
                                $record['dty_Type'] = 'resource';
                                break;

                            case 'relationship marker':
                            case 'relmarker':
                                $this->data['fields'][$idx]['dty_Type'] = 'relmarker';
                                $record['dty_Type'] = 'relmarker';
                                break;

                            case 'file':
                            case 'uploads':
                                $this->data['fields'][$idx]['dty_Type'] = 'file';
                                $record['dty_Type'] = 'file';
                                break;

                            case 'geo':
                            case 'geospatial':
                            case 'location':
                                $this->data['fields'][$idx]['dty_Type'] = 'geo';
                                $record['dty_Type'] = 'geo';
                                break;

                            case 'group':
                            case 'separator':
                            case 'divider':
                                $this->data['fields'][$idx]['dty_Type'] = 'separator';
                                $record['dty_Type'] = 'separator';
                                break;

                            default:
                                $ret[$idx][] = $record['dty_Type'] . ' type is not handled';
                                break;
                        }

                        if($record['dty_Type'] == 'enum' || $record['dty_Type'] == 'relmarker'){
                            if(empty(@$record['dty_JsonTermIDTree'])){

                                $vcb_query = 'SELECT trm_ID FROM defTerms WHERE trm_ParentTermID = 0 AND trm_Label = "'. $record['dty_Name'] .'"';// Check for existing vocab with field name

                                $vcb_id = mysql__select_value($mysqli, $vcb_query);

                                if($vcb_id){

                                    $this->data['fields'][$idx]['dty_JsonTermIDTree'] = $vcb_id;
                                    $record['dty_JsonTermIDTree'] = $vcb_id;
                                }else{

                                    $defTerms_data = array(
                                        'entity' => 'defTerms',
                                        'fields' => array(
                                            'trm_ID' => -1,
                                            'trm_Label' => $record['dty_Name'],
                                            'trm_Domain' => $record['dty_Type'] == 'relmarker' ? 'relation' : 'enum',
                                            'trm_VocabularyGroupID' => $record['dty_Type'] == 'relmarker' && $vcg_rel ? $vcg_rel : $vcg_enum
                                        )
                                    );
                                    $defTerms->setData($defTerms_data);

                                    $save_res = $defTerms->save();
                                    if($save_res !== false){

                                        $rec = $defTerms->records();
                                        $new_id = $rec[0]['trm_ID'];

                                        $this->data['fields'][$idx]['dty_JsonTermIDTree'] = $new_id;
                                        $record['dty_JsonTermIDTree'] = $new_id;
                                        $new_vocabs[$idx] = $new_id;
                                    }else{
                                        $ret[$idx][] = 'Unable to create vocabulary for field with matching name';
                                    }
                                }
                            }else{

                                $trm_query = 'SELECT trm_ID, trm_Domain, trm_Label FROM defTerms WHERE trm_ID = ' . $record['dty_JsonTermIDTree'];
                                $trm_res = mysql__select_value($mysqli, $trm_query);

                                if(!$trm_res){
                                    $ret[$idx][] = 'Unable to find vocab id #' . $record['dty_JsonTermIDTree'];
                                }elseif($record['dty_Type'] == 'relmarker' && $trm_res[1] == 'enum'){
                                    $ret[$idx][] = $trm_res[2] . ' (#' . $trm_res[0] . ') is not setup as relation terms';
                                }
                            }
                        }

                        if($record['dty_Type'] == 'resource' || $record['dty_Type'] == 'relmarker'){
                            if(empty(@$record['dty_PtrTargetRectypeIDs'])){
                                // $ret[$idx][] = 'A Record Type target is needed for this field';
                            }else{
                                $rty_query = 'SELECT rty_ID FROM defRecTypes WHERE rty_ID = ' . $record['dty_PtrTargetRectypeIDs'];
                                $rty_res = mysql__select_value($mysqli, $rty_query);

                                if(!$rty_res){
                                    $ret[$idx][] = 'Unable to find rectype id #' . $record['dty_PtrTargetRectypeIDs'];
                                }
                            }
                        }
                    }


                    if(count($ret[$idx]) != 0){ // has error

                        unset($this->data['fields'][$idx]);
                        $ret[$idx] = "<strong>Row #" . ($idx + 1) . "</strong>: " . implode(', ', $ret[$idx]);
                    }else{
                        $ret[$idx] = '';

                        if(!array_key_exists('dty_HelpText', $this->data['fields'][$idx])){ // add help text
                            $this->data['fields'][$idx]['dty_HelpText'] = 'Please provide a short explanation for the user ...';
                        }
                        if(!array_key_exists('dty_Status', $this->data['fields'][$idx])){ // add status
                            $this->data['fields'][$idx]['dty_Status'] = 'open';
                        }
                        if(!array_key_exists('dty_ShowInLists', $this->data['fields'][$idx])){ // add show in list
                            $this->data['fields'][$idx]['dty_ShowInLists'] = '1';
                        }
                        if(!array_key_exists('dty_NonOwnerVisibility', $this->data['fields'][$idx])){ // add field visibility
                            $this->data['fields'][$idx]['dty_NonOwnerVisibility'] = 'viewable';
                        }
                        if(!array_key_exists('dty_DetailTypeGroupID', $this->data['fields'][$idx]) && isset($this->data['dtg_ID'])){ // add detail type group
                            $this->data['fields'][$idx]['dty_DetailTypeGroupID'] = $this->data['dtg_ID'];
                        }
                    }
                }

                $idx_to_do = array_keys($this->data['fields']);
                $this->data['fields'] = array_values($this->data['fields']);// re-write indexes

                $result = true;
                if(count($this->data['fields']) > 0){ // ensure there are still base fields to define
                    $result = $this->save();
                }

                if(!$result){
                    $ret = false;
                }elseif(count($idx_to_do) > 0){ // check if success messages need to be added

                    $i = 0;
                    foreach ($idx_to_do as $idx){
                        if(strlen($ret[$idx]) > 0){
                            continue;
                        }

                        $ret[$idx] = 'Created ID#'.$this->records[$i]['dty_ID'];
                        if(array_key_exists($idx, $new_vocabs)){
                            $ret[$idx] .= '<br> New vocabulary created ID#' . $new_vocabs[$idx];
                            $ret['refresh_terms'] = true;
                        }
                        $i++;
                    }
                }
            }else{
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'No import data has been provided. Ensure that you have enter the necessary CSV rows.<br>Please contact the Heurist team if this problem persists.');
            }
        }


        mysql__end_transaction($mysqli, $ret, $keep_autocommit);

        return $ret;
    }

    //
    //
    //
    public function counts(){

        $mysqli = $this->system->get_mysqli();
        $res = null;

        if(@$this->data['mode'] == 'record_usage'){

            $dty_ID = @$this->data['recID'];

            if(isset($dty_ID) && is_numeric($dty_ID) && $dty_ID > 0){
                $res = mysql__select_value($mysqli, 'SELECT count(dtl_ID) FROM recDetails WHERE dtl_DetailTypeID = ' . $dty_ID);
            }else{
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'Invalid base field id provided ' . $dty_ID);
                $res = false;
            }
        }

        return $res;
    }
}
?>
