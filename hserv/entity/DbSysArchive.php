<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;
use hserv\utilities\USanitize;

    /**
    * db access to sysArchive table
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

require_once dirname(__FILE__).'/../records/edit/recordModify.php';
require_once dirname(__FILE__).'/../records/search/recordFile.php';
require_once dirname(__FILE__).'/../utilities/Temporal.php';


class DbSysArchive extends DbEntityBase
{

    /**
    *  search groups
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

        $needCheck = false;
        $is_ids_only = false;

        //compose WHERE
        $where = array();
        $from_table = array($this->config['tableName']);

        $pred = $this->searchMgr->getPredicate('arc_ID');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('arc_PriKey');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('arc_ChangedByUGrpID');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('arc_TimeOfChange');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('arc_ContentType');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('arc_Table');
        if($pred!=null) {array_push($where, $pred);}


        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){

            $this->data['details'] = 'arc_ID';
            $is_ids_only = true;

        }elseif(@$this->data['details']=='name'){

            $this->data['details'] = 'arc_ID, arc_DataBeforeChange';

        }elseif(@$this->data['details']=='list')
        {
            $this->data['details'] = 'arc_ID, arc_Table, arc_PriKey, arc_ChangedByUGrpID, arc_OwnerUGrpID, arc_RecID, arc_TimeOfChange, arc_ContentType';

        }elseif(@$this->data['details']=='full')
        {
            $this->data['details'] = 'arc_ID, arc_Table, arc_PriKey, arc_ChangedByUGrpID, arc_OwnerUGrpID, arc_RecID, arc_TimeOfChange, arc_DataBeforeChange, arc_ContentType';

        }else{
            $needCheck = true;
        }

        if(!is_array($this->data['details'])){ //user specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);

        }

        //validate names of fields
        if($needCheck && !$this->_validateFieldsForSearch()){
            return false;
        }

        //----- order by ------------
        //compose ORDER BY
        $order = array();

        $value = @$this->data['sort:arc_TimeOfChange'];
        if($value!=null){
            array_push($order, 'arc_TimeOfChange '.($value>0?'ASC':'DESC'));
        }

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

        if(@$this->data['convert']=='records_list'){
            $result = $this->convertToHeuristRecords($result, 'records_list');
        }


        return $result;
    }

    //
    // extract data from arc_DataBeforeChange and converts resultset to Heurist records
    //
    private function convertToHeuristRecords($response, $details){

        if(is_array($response) && $response['reccount']>0){

            $rectypes = array();
            $records = array();
            $order = array();
            $csv_delimiter = "\t";
            $csv_enclosure = '|';

            if($details=='records_list'){ //returns fields suitable for list only
                //0,1,2,3,4,6,11,12
                $fields = 'rec_ID,rec_URL,rec_Added,rec_Modified,rec_Title,rec_RecTypeID,'
                    .'rec_OwnerUGrpID, rec_NonOwnerVisibility,'
                    .'arc_ID,arc_ChangedByUGrpID,arc_TimeOfChange,arc_ContentType';
            }else{
                $fields = 'rec_ID,rec_URL,rec_Added,rec_Modified,rec_Title,rec_ScratchPad,rec_RecTypeID,rec_AddedByUGrpID,'
                .'rec_AddedByImport,rec_Popularity,rec_FlagTemporary,rec_OwnerUGrpID,rec_NonOwnerVisibility,rec_URLLastVerified,'
                .'rec_URLErrorMessage,rec_URLExtensionForMimeType,rec_Hash';
            }
            $fields = explode(',',$fields);

            $idx_data = array_search('arc_DataBeforeChange', $response['fields']);


            foreach ($response['records'] as $arc_ID => $arcrow){

                $arc = $arcrow[$idx_data];
                //$arc = substr(str_replace('","', "\t", $arc),1);
                $arc = str_replace(SQL_NULL,'""', $arc);
                $arc = str_replace('","', "|\t|", $arc);
                /*
                $arc = str_replace('",NULL', "@\tNULL", $arc);
                $arc = str_replace('NULL,"', "NULL\t@", $arc);
                $arc = str_replace('NULL,NULL', "NULL\tNULL", $arc);
                */
                $arc = '|'.substr($arc, 1);
                $rec = str_getcsv($arc, $csv_delimiter, $csv_enclosure);
                /*
                if(count($rec)>17){
                    $rec2 = array();
                    $cnt = -1;
                    foreach ($rec as $fld){
                        if(strpos($fld,'"')===strlen($fld)-1){
                            $rec2[$cnt] = $rec2[$cnt].$fld;
                        }else{
                            $rec2[] = $fld;
                            $cnt++;
                        }
                    }
                    $rec = $rec2;
                }*/

                $rec_ID = $arc_ID;
                $rec_RecTypeID = $rec[6];

                array_push($order, $arc_ID);

                if($details=='records_list'){

                    $records[$arc_ID] = array($rec[0],$rec[1],$rec[2],$rec[3],$rec[4],$rec[6],$rec[11],$rec[12],
                                    $arc_ID,$arcrow[3],$arcrow[6],$arcrow[8]);

                }else{
                    $records[$arc_ID] = $rec;
                }

                if(!in_array($rec_RecTypeID, $rectypes)){
                    array_push($rectypes, $rec_RecTypeID);
                }
            }

/*
"212","","2019-12-21 09:49:35","2019-12-22 13:26:23","Note2 [ Alice Lee Roosevelt Longworth, 21 Dec 2019 ]","", rt"3","2","0","0",ft"0",
own"0","viewable",NULL,NULL,NULL,NULL
*/

            $response['fields'] = $fields;
            $response['records'] = $records;
            $response['order'] = $order;
            $response['entityName']='Records';
            $response['rectypes'] = $rectypes;

            return $response;
        }else{
            return $response;
        }

    }

    //
    // this table is updated via triggers only
    //
    public function save(){
        return false;
    }

    //
    // delete disabled
    //
    public function delete($disable_foreign_checks = false){
        return false;
    }

    /**
     * Batch functions
     *
     * Functions:
     *  get_record_history - retrieve record value changes, either added (oldest known value) or modified (any following value that's different)
     *  revert_record_history - rollback value history with values stored within the archive record
     */
    public function batch_action(){

        global $useNewTemporalFormatInRecDetails;

        $mysqli = $this->system->get_mysqli();

        /**
         * Retrieve field value, raw value for comparison
         *
         * @param string $value - arc_DataBeforeChange, archived recDetails row
         * @param array $defStruct - record structure, used to check type
         *
         * @return array - [extracted value, detail type ID]
         */
        $__get_value = function($value, $defStruct){

            // Get recDetail values from arc_DataBeforeChange
            $dtl_record = str_getcsv($value, ',', '"');

            $value = !empty($dtl_record[3]) && $dtl_record[3] != "NULL" ? $dtl_record[3] : null; // dtl_Value
            $value = !empty($dtl_record[5]) && $dtl_record[5] != "NULL" ? $dtl_record[5] : $value; // dtl_UploadedFileID
            $value = !empty($dtl_record[6]) && $dtl_record[6] != "NULL" ? $dtl_record[6] : $value; // dtl_Geo - already WKT format

            $dty_ID = !empty($dtl_record[2]) ? intval($dtl_record[2]) : 0;

            if(empty($value) || empty($dty_ID) || $dty_ID < 1){ // ? Unknown
                return [null, null];
            }

            if($defStruct[$dty_ID] == 'date' && strpos($value, "}") !== false){
                $dtl_record = explode(",", $value);

                $value = [$dtl_record[3]];

                $idx = 4;
                while($idx < count($dtl_record)){

                    if($dtl_record[$idx] == '"0"' || $dtl_record[$idx] == '"1"'){
                        break;
                    }

                    array_push($value, $dtl_record[$idx]);

                    $idx ++;
                }

                $value = trim( implode(',', $value) , '"');
            }elseif($defStruct[$dty_ID] == 'freetext' || $defStruct[$dty_ID] == 'blocktext'){
                $value = USanitize::cleanupSpaces($value);// remove extra spacing, avoid displaying extra historical values that are actually the same
            }


            return [$value, $dty_ID];
        };

        /**
         * Perform extra processing, value to show the user
         *
         * @param mixed $value - field value, to be processed
         * @param string $type - field type
         *
         * @return mixed - processed value, for user display
         */
        $__process_value = function($value, $type) use ($mysqli){

            switch ($type) {

                case 'resource': // replace with simple record details (i.e. id, title, rectype id)

                    $id = intval($value);
                    if($id < 1){
                        return $value;
                    }

                    list($title, $rectype) = mysql__select_row($mysqli, "SELECT rec_Title, rec_RecTypeID FROM Records WHERE rec_ID = $id");

                    $value = [
                        'rec_ID' => $id,
                        'rec_Title' => $title,
                        'rec_RecTypeID' => intval($rectype),
                        'rec_IsChildRecord' => 0
                    ];

                    break;

                case 'enum': // replace with term label w/ hierarchy - vocab label

                    $id = intval($value);
                    if($id < 1){
                        return $value;
                    }
                    $org_id = $id;

                    $value = [];
                    while(true){

                        list($lbl, $id) = mysql__select_row($mysqli, "SELECT trm_Label, trm_ParentTermID FROM defTerms WHERE trm_ID = $id");

                        $id = intval($id);
                        if(empty($id) || $id < 1){
                            break;
                        }

                        if(!empty($lbl)){
                            array_unshift($value, $lbl);
                        }
                    }

                    $value = !empty($value) ? implode(' . ', $value) : "Unable to find term #$org_id";

                    break;

                case 'file': // replace with filename / website URL

                    list($filename, $url) = mysql__select_row($mysqli, "SELECT ulf_FileName, ulf_ExternalFileReference FROM recUploadedFiles WHERE ulf_ID = $value");

                    $value = !$filename || empty($filename) ? "Unable to find file #$value" : $filename;
                    $value = !$url || empty($url) ? $value : $url;

                    break;

                case 'date': // replace with simple string

                    $json_value = json_decode($value, true);

                    if(json_last_error() === JSON_ERROR_NONE){
                        $value = $json_value;
                    }

                    $value = \Temporal::toHumanReadable($value, true, 1);

                    $value = USanitize::cleanupSpaces($value);

                    break;

                case 'freetext':
                case 'blocktext':
                    // clean up strings

                    $value = USanitize::sanitizeString($value);

                    break;

                default:
                    break;
            }

            return $value;
        };

        $ret = true;

        if(array_key_exists('get_record_history', $this->data)){

            $rec_ID = intval($this->data['rec_ID']);

            if(!$rec_ID || $rec_ID < 1){
                $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid record ID provided');
                return false;
            }

            // Check that rec_ID is a record
            $record_type = mysql__select_value($mysqli, "SELECT rec_RecTypeID FROM Records WHERE rec_FlagTemporary != 1 AND rec_ID = $rec_ID");
            if($mysqli->error){
                $this->system->addError(HEURIST_DB_ERROR, 'Unable to query Records table for the selected record\'s entity type', $mysqli->error);
                return false;
            }elseif(!$record_type){
                return [];
            }
            $record_type = intval($record_type);

            // Get record structure
            $record_fields = mysql__select_assoc2($mysqli, "SELECT dty_ID, dty_Type FROM defDetailTypes INNER JOIN defRecStructure ON rst_DetailTypeID = dty_ID WHERE rst_RecTypeID = $record_type");
            if($mysqli->error){
                $this->system->addError(HEURIST_DB_ERROR, 'Unable to query defDetailTypes table for the selected record\'s base fields', $mysqli->error);
                return false;
            }elseif(empty($record_fields)){
                $this->system->addError(HEURIST_DB_ERROR, "The provided record type #$record_type does not have any usable fields");
                return false;
            }

            // Get set of datetime changes
            $query_date = "SELECT DISTINCT arc_TimeOfChange FROM sysArchive WHERE arc_Table = 'dtl' AND arc_RecID = $rec_ID ORDER BY arc_TimeOfChange";
            $res_date = $mysqli->query($query_date);

            if(!$res_date){
                $this->system->addError(HEURIST_DB_ERROR, 'Unable to query sysArchive table for dates of changes', $mysqli->error);
                return false;
            }

            /*
                arc_PriKey => dtl_ID
                arc_ChangedByUGrpID => ugr_ID
                arc_TimeOfChange => timestamp of action
                arc_Value => Value extracted from arc_DataBeforeChange {dtl_DetailTypeID [2], dtl_Value [3], dtl_UploadedFileID [5], or dtl_Geo [6]}
                arc_Compare => For comparing indexes (Move to separate structure, so it's not returned)
                arc_Action => 'added', 'modified', 'deleted'
             */
            $complete_history = [];
            $last_editor = [];
            $user_list = [];

            while($row_date = $res_date->fetch_row()){

                $query_changes = "SELECT arc_PriKey, arc_ID, arc_ChangedByUGrpID, arc_TimeOfChange, arc_DataBeforeChange " .
                                 "FROM sysArchive " .
                                 "WHERE arc_Table = 'dtl' AND arc_RecID = ? AND arc_TimeOfChange = ? " .
                                 "ORDER BY arc_ID";

                $res_changes = mysql__select_param_query($mysqli, $query_changes, ['is', $rec_ID, $row_date[0]]);

                if(!$res_changes){
                    $this->system->addError(HEURIST_DB_ERROR, 'Unable to query sysArchive table for list of changes made at '
                                        . $row_date[0], $mysqli->error);
                    return false;
                }

                $cur_set = [];

                while($row_changes = $res_changes->fetch_assoc()){

                    $arc_ID = intval($row_changes['arc_ID']);
                    $ugr_ID = intval($row_changes['arc_ChangedByUGrpID']);

                    $res_row = [
                        'arc_ID' => $arc_ID,
                        'arc_ChangedByUGrpID' => 0,
                        'arc_TimeOfChange' => $row_changes['arc_TimeOfChange'],
                        'arc_Value' => '',
                        'arc_Compare' => '',
                        'arc_Action' => 'revert'
                    ];

                    list($value, $dty_ID) = $__get_value($row_changes['arc_DataBeforeChange'], $record_fields);
                    if(!$value || !$dty_ID){
                        continue;
                    }

                    if(!array_key_exists($dty_ID, $complete_history)){
                        $complete_history[$dty_ID] = [];
                        $last_editor[$dty_ID] = [];
                    }
                    if(!array_key_exists($dty_ID, $cur_set)){
                        $cur_set[$dty_ID] = [];
                    }

                    // Replace certain values, for display to user
                    $display_value = $__process_value($value, $record_fields[$dty_ID]);

                    $res_row['arc_Compare'] = $value;
                    $res_row['arc_Value'] = $display_value;
                    $cur_set[$dty_ID][] = 1;

                    // Determine whether the value was added or modified
                    $dty_idx = count($cur_set[$dty_ID]) - 1;
                    $last_value = array_key_exists($dty_ID, $complete_history) && array_key_exists($dty_idx, $complete_history[$dty_ID]) ?
                                        $complete_history[$dty_ID][$dty_idx][0]['arc_Compare'] : null;

                    if(mb_strcasecmp($last_value, $value) == 0){ // no change, skip
                        continue;
                    }

                    //$res_row['arc_Action'] = empty($last_value) ? 'add' : 'mod';

                    if(!array_key_exists($dty_idx, $complete_history[$dty_ID])){
                        $complete_history[$dty_ID][$dty_idx] = [];
                    }

                    if(array_key_exists($dty_idx, $last_editor[$dty_ID]) && !empty($last_editor[$dty_ID][$dty_idx])){
                        $res_row['arc_ChangedByUGrpID'] = $last_editor[$dty_ID][$dty_idx];
                    }elseif(mysql__select_value($mysqli, "SELECT dtl_ID FROM recDetails WHERE dtl_ID = " . intval($row_changes['arc_PriKey'])) > 0){
                        // was updated outside of standard record editor
                        $res_row['arc_ChangedByUGrpID'] = $ugr_ID;
                    }else{

                        $query_arc_rec = "SELECT arc_ChangedByUGrpID FROM sysArchive WHERE arc_ID < $arc_ID AND arc_Table = 'rec' AND arc_RecID = $rec_ID ORDER BY arc_ID DESC LIMIT 1";
                        $intial_ugr_ID = mysql__select_value($mysqli, $query_arc_rec);

                        $res_row['arc_ChangedByUGrpID'] = intval($intial_ugr_ID) > 0 ? intval($intial_ugr_ID) : 0;
                    }

                    $last_editor[$dty_ID][$dty_idx] = $ugr_ID;
                    array_unshift($complete_history[$dty_ID][$dty_idx], $res_row);// newest first
                    $user_list[$res_row['arc_ChangedByUGrpID']] = 'Unknown';
                }

                $res_changes->close();
            }

            $res_date->close();

            // Retrieve current values
            $query_existing = "SELECT dtl_ID, dtl_DetailTypeID, dtl_Value, dtl_UploadedFileID, ST_AsText(dtl_Geo) AS dtl_Geo, dtl_Modified FROM recDetails WHERE dtl_RecID = $rec_ID ORDER BY dtl_ID";
            $res_existing = $mysqli->query($query_existing);

            if(!$res_existing){
                $this->system->addError(HEURIST_DB_ERROR, 'Unable to query recDetails table for existing record details', $mysqli->error);
                return false;
            }

            $final_set = [];

            while($row_current = $res_existing->fetch_assoc()){

                $dtl_ID = intval($row_current['dtl_ID']);
                $dty_ID = intval($row_current['dtl_DetailTypeID']);

                $res_row = [
                    'dtl_ID' => $dtl_ID,
                    'arc_ChangedByUGrpID' => 0,
                    'arc_TimeOfChange' => $row_current['dtl_Modified'],
                    'arc_Value' => '',
                    'arc_Compare' => '',
                    'arc_Action' => 'current'
                ];

                $value = !empty($row_current['dtl_Value']) ? $row_current['dtl_Value'] : null;
                $value = !empty($row_current['dtl_UploadedFileID']) ? $row_current['dtl_UploadedFileID'] : $value;
                $value = !empty($row_current['dtl_Geo']) ? $row_current['dtl_Geo'] : $value;

                if(empty($value) || $dty_ID < 1){ // ? Unknown
                    continue;
                }

                if(!array_key_exists($dty_ID, $complete_history)){
                    $complete_history[$dty_ID] = [];
                }
                if(!array_key_exists($dty_ID, $final_set)){
                    $final_set[$dty_ID] = [];
                }

                $display_value = $__process_value($value, $record_fields[$dty_ID]);

                $res_row['arc_Compare'] = $value;
                $res_row['arc_Value'] = $display_value;
                $final_set[$dty_ID][] = 1;

                // Determine whether the value was added or modified
                $dty_idx = count($final_set[$dty_ID]) - 1;
                $last_value = array_key_exists($dty_ID, $complete_history) && array_key_exists($dty_idx, $complete_history[$dty_ID]) ?
                                    $complete_history[$dty_ID][$dty_idx][0]['arc_Compare'] : null;

                if(mb_strcasecmp($last_value, $value) == 0){ // no change, skip, add dtl_ID
                    $complete_history[$dty_ID][$dty_idx][0]['dtl_ID'] = $dtl_ID;
                    $complete_history[$dty_ID][$dty_idx][0]['arc_Action'] = 'current';
                    continue;
                }

                //$res_row['arc_Action'] = empty($last_value) ? 'add' : 'mod';

                if(!array_key_exists($dty_idx, $complete_history[$dty_ID])){
                    $complete_history[$dty_ID][$dty_idx] = [];
                }

                if(array_key_exists($dty_idx, $last_editor[$dty_ID]) && !empty($last_editor[$dty_ID][$dty_idx])){
                    $res_row['arc_ChangedByUGrpID'] = $last_editor[$dty_ID][$dty_idx];
                }

                $user_list[$res_row['arc_ChangedByUGrpID']] = 'Unknown';
                array_unshift($complete_history[$dty_ID][$dty_idx], $res_row);// newest first
            }

            if(!empty($user_list)){

                foreach($user_list as $ugr_ID => $name){
                    $ugr_ID = intval($ugr_ID);
                    if($ugr_ID < 1){
                        continue;
                    }

                    $user_row = mysql__select_row_assoc($mysqli, "SELECT ugr_Type, ugr_Name, CONCAT(ugr_FirstName, ' ', ugr_LastName) AS ugr_FullName FROM sysUGrps WHERE ugr_ID = $ugr_ID");
                    if(empty($user_row)){
                        continue;
                    }

                    $user_list[$ugr_ID] = $user_row['ugr_Type'] == 'user' && !empty($user_row['ugr_FullName']) ? $user_row['ugr_FullName'] : $user_row['ugr_Name'];
                }
            }

            $res_existing->close();

            $ret = ['history' => $complete_history, 'users' => $user_list];

        }elseif(array_key_exists('revert_record_history', $this->data)){

            $ret = ['errors' => [], 'issues' => []];

            // Validate record id
            $rec_ID = intval($this->data['rec_ID']);
            if(!$rec_ID || $rec_ID < 1){
                $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid record ID provided');
                return false;
            }

            // Validate revisions
            $revisions = $this->data['revisions'];
            if(!empty($revisions) && !is_array($revisions)){
                $revisions = json_decode($revisions, true);
            }

            if(!$revisions || empty($revisions)){
                $this->system->addError(HEURIST_INVALID_REQUEST, 'No record revisions provided');
                return false;
            }

            // Check that rec_ID is a record
            $record_type = mysql__select_value($mysqli, "SELECT rec_RecTypeID FROM Records WHERE rec_FlagTemporary != 1 AND rec_ID = $rec_ID");
            if($mysqli->error){
                $this->system->addError(HEURIST_DB_ERROR, 'Unable to query Records table for the selected record\'s entity type', $mysqli->error);
                return false;
            }elseif(!$record_type){
                $this->system->addError(HEURIST_INVALID_REQUEST, 'Unable to retrieve the record type of current record');
                return false;
            }
            $record_type = intval($record_type);

            // Get record structure
            $record_fields = mysql__select_assoc($mysqli, "SELECT dty_ID, dty_Type AS type, rst_DisplayName AS name FROM defDetailTypes INNER JOIN defRecStructure ON rst_DetailTypeID = dty_ID WHERE rst_RecTypeID = $record_type");
            if($mysqli->error){
                $this->system->addError(HEURIST_DB_ERROR, 'Unable to query defDetailTypes table for the selected record\'s base fields', $mysqli->error);
                return false;
            }elseif(empty($record_fields)){
                $this->system->addError(HEURIST_DB_ERROR, "The provided record type #$record_type does not have any usable fields");
                return false;
            }

            foreach($revisions as $dty_ID => $fld_changes){

                $dty_ID = intval($dty_ID);

                if($dty_ID <= 0 || !is_array($fld_changes) || !array_key_exists($dty_ID, $record_fields)){
                    if($dty_ID <= 0){
                        $ret['errors'][] = "Invalid detail type ID provided #$dty_ID";
                    }elseif(!array_key_exists($dty_ID, $record_fields)){
                        $ret['errors'][] = "Field ID #$dty_ID is not part of the record's structure";
                    }
                    continue;
                }

                foreach ($fld_changes as $fld_idx => $arc_ID) {

                    // Validate archive ID and Field index
                    $arc_ID = intval($arc_ID);
                    $fld_idx = intval($fld_idx);

                    if($fld_idx < 0 || $arc_ID <= 0){
                        $ret['errors'][] = $fld_idx < 0 ? "Invalid field index provided #$fld_idx" : "Invalid archive ID provided #$arc_ID";
                        continue;
                    }

                    // Get archived value
                    $arc_Value = mysql__select_value($mysqli, "SELECT arc_DataBeforeChange FROM sysArchive WHERE arc_ID = $arc_ID");
                    if(!$arc_Value){
                        $ret['errors'][] = "Unable to retrieve the archived value for #$arc_ID";
                        continue;
                    }

                    list($arc_Value, ) = $__get_value($arc_Value, $record_fields);

                    if(!$arc_Value){

                        $type = $record_fields[$dty_ID]['type'] == 'enum' ? 'term' : 'record';
                        $type = $record_fields[$dty_ID]['type'] == 'file' ? 'file' : $type;

                        $ret['errors'][] = "Couldn't update value #$fld_idx for the field {$record_fields[$dty_ID]['name']}, missing the $type with ID #$arc_ID";
                        continue;
                    }

                    // Check if updating existing record, or creating a record
                    $existing_dtl_id = mysql__select_value($mysqli, "SELECT dtl_ID FROM recDetails WHERE dtl_RecID = $rec_ID AND dtl_DetailTypeID = $dty_ID ORDER BY dtl_ID LIMIT $fld_idx, 1");
                    $existing_dtl_id = intval($existing_dtl_id);

                    $record = [];

                    // Set value index
                    switch ($record_fields[$dty_ID]['type']) {

                        case 'geo':
                            list($dtl_Value, $dtl_Geo) = prepareGeoValue($mysqli, $arc_Value);// recordModify.php
                            $record['dtl_Value'] = $dtl_Value === false ? $arc_Value : $dtl_Value;
                            $record['dtl_Geo'] = $dtl_Value === false ? null : $dtl_Geo;
                            break;

                        case 'date':
                            $arc_Value = \Temporal::getValueForRecDetails( $arc_Value, $useNewTemporalFormatInRecDetails );
                            break;

                        case 'file':
                            $record['dtl_UploadedFileID'] = $arc_Value;
                            break;

                        default:
                            $record['dtl_Value'] = $arc_Value;
                            break;
                    }

                    if($existing_dtl_id > 0){ // update existing value
                        $record['dtl_ID'] = $existing_dtl_id;
                    }else{ // create new value
                        $record['dtl_DetailTypeID'] = $dty_ID;
                        $record['dtl_RecID'] = $rec_ID;
                    }

                    // Update/create recDetails record
                    $res_ID = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $record);
                    if($res_ID <= 0){

                        $fld_idx ++;
                        $ret['errors'][] = "Couldn't update value #$fld_idx for the field {$record_fields[$dty_ID]['name']}";
                    }
                }
            }
        }

        return $ret;
    }
}
?>
