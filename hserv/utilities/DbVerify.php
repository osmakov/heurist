<?php
/**
* dbVerify.php : methods to validate and fix database struture and data integrity
* see databaseController $action=='verify'
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
* @subpackage  utilities
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

namespace hserv\utilities;
use hserv\utilities\DbUtils;

require_once dirname(__FILE__).'/../../admin/verification/verifyValue.php';
require_once dirname(__FILE__).'/../../admin/verification/verifyFieldTypes.php';
require_once dirname(__FILE__).'/../utilities/Temporal.php';
require_once dirname(__FILE__)."/../utilities/geo/mapCoordinates.php";

class DbVerify {

    private $mysqli = null;
    private $system = null;

    private $out = null; //output stream
    private $keep_autocommit = null;

    public function __construct($system) {
       $this->system = $system;
       $this->mysqli = $system->get_mysqli();
    }

    //
    //
    //
    private function _outStreamInit(){
        $this->out = fopen(TEMP_MEMORY, 'w');//less than 1MB in memory otherwise as temp file
    }

    //
    //
    //
    private function _outStreamRes(){
        rewind($this->out);
        $res = stream_get_contents($this->out);
        fclose($this->out);
        $this->out = null;
        return $res;
    }

    //
    //
    //
    private function _terminatedByUser(){

        $this->system->addError(HEURIST_ACTION_BLOCKED, 'Database Verification has been terminated by user');
        if($this->keep_autocommit!=null && $this->mysqli){
            $this->mysqli->rollback();
            if($this->keep_autocommit===true) {$this->mysqli->autocommit(true);}
        }
        if($this->out){
            fclose($this->out);
        }
        $this->keep_autocommit = null;
        $this->out = null;
    }

    //
    // compose record edit url
    //
    private function getEditURL($rec_ID){
        return HEURIST_BASE_URL.'?fmt=edit&db='.$this->system->dbname().'&recID='.$rec_ID;
    }

    private function getAllURL($rec_IDs){
        return HEURIST_BASE_URL.'?db='.$this->system->dbname().'&w=all&q=ids:'.implode(',', $rec_IDs);
    }

    //
    //
    //
    private function printList($title, $sub_title, $resList, $marker){

        if(!$sub_title) {$sub_title = '';}

        if($title!=null){

            $resMsg = <<<HEADER
                <div>
                    <h3>$title</h3>
                    $sub_title
                    <span>
                        <a target=_new href="#" data-show-all="$marker">(show results as search)</a>
                        <a target=_new href="#selected_link" data-show-selected="$marker">(show selected as search)</a>
                    </span>
                </div>
                <table role="presentation">
                    <tr>
                        <td colspan="7">
                            <label><input type="checkbox" data-mark-all="$marker">Mark all</label>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3"></td>
                        <td><strong>Record title</strong></td>
                        <td><strong>ID</strong></td>
                        <td><strong>Field name</strong></td>
                        <td><strong>Field value</strong></td>
                    </tr>
                HEADER;

            fwrite($this->out, $resMsg);
        }

        if($resList!=null){

            $url_icon_placeholder = ICON_PLACEHOLDER;
            $url_icon_extlink = ICON_EXTLINK;

            //$ids = array();
            $rec_id = null;
            $idx = 0;

            while ($row = is_array($resList)?@$resList[$idx]:$resList->fetch_assoc()){
                //foreach ($resList as $row) {
                $resMsg = '';
                $idx++;
                if($rec_id==null || $rec_id!=$row['rec_ID']) {

                    $rec_id = $row['rec_ID'];

                    //array_push($ids, $rec_id);
                    $url_icon = @$row['rec_RecTypeID']?HEURIST_RTY_ICON.$row['rec_RecTypeID']:'';
                    $url_rec =  $this->getEditURL($rec_id);
                    $rec_title = @$row['rec_Title']?strip_tags($row['rec_Title']):'';
                    if(@$row['wkt']){
                        $dtl_value = $row['wkt'];
                    }else{
                        $dtl_value = strip_tags($row['dtl_Value'],'<span>');
                    }


                    $resMsg = $resMsg . <<<EOT
                        <tr>
                            <td><input type=checkbox name="$marker" value={$rec_id}></td>
                            <td><img alt class="rft" style="background-size:contain;background-image:url($url_icon)" src="$url_icon_placeholder"></td>
                            <td style="white-space: nowrap;"><a target=_new href="$url_rec">
                                    {$rec_id} <img alt src="$url_icon_extlink" style="vertical-align:middle" title="Click to edit record">
                                </a></td>
                            <td class="truncate" style="max-width:400px">$rec_title</td>
                        EOT;
                }else{
                    $resMsg .= '<tr><td colspan="4"></td>';
                }
                $resMsg = $resMsg . <<<EOT
                            <td>{$row['dty_ID']}</td>
                            <td width="150px" style="max-width:150px" class="truncate">{$row['dty_Name']}</td>
                            <td style="max-width:400px" class="truncate">{$dtl_value}</td></tr>
                            EOT;

                fwrite($this->out, $resMsg);

            }//foreach


            if($title!=null){
                fwrite($this->out, TABLE_E);
            }

            //$url_all = $this->getAllURL($ids);
            //$resMsg = str_replace('href="url_all"','href="'.$url_all.'"',$resMsg);

            if(!is_array($resList)){
                $resList->close();
            }

        }

        //return $resMsg;
    }

    /**
     * Checks if the system is in "fix mode" based on the parameters.
     *
     * @param array|null $params The input parameters, which may contain a 'fix' flag.
     * @return bool Returns true if 'fix' mode is enabled, false otherwise.
     */
    private function isFixMode($params)
    {
        return is_array($params) && @$params['fix'] == 1;
    }

    /**
     * Checks that all records have valid Owner and Added By User references.
     *
     * This function verifies that all records in the database have valid 'Added By' and 'Owner' references.
     * If invalid references are found (users that don't exist), it provides an option to attribute those records
     * to the Database OwnerM (user ID #2). Additionally, it can auto-fix these issues in "fix mode."
     *
     * @param array|null $params An associative array of parameters. If 'fix' is set to 1, it will attempt to fix invalid references.
     * @return array An associative array with 'status' (true if no issues or successfully repaired, false if errors found)
     *               and 'message' (HTML string describing the results or errors).
     */
    public function check_owner_ref($params=null){

        $resStatus = true; // Default status is 'true' unless issues are found.
        $resMsg = ''; // Message to hold the result or error information.
        $wasassigned1 = 0; // Track number of records assigned to 'Added by' user #2.
        $wasassigned2 = 0; // Track number of records assigned to owner user #2.

        // If the system is in fix mode, attempt to repair invalid references.
        if($this->isFixMode($params)){

            mysql__safe_updatess($this->mysqli, false);

            // Attempt to update invalid 'Added By' and 'Owner' references
            $resMsg = $this->updateGroupIds('rec_AddedByUGrpID', 'reference (Added By)');
            $resMsg .= $this->updateGroupIds('rec_OwnerUGrpID', 'ownership');

            mysql__safe_updatess($this->mysqli, true);
        }

        // If any error messages are generated during the repair, set status to false.
        if ($resMsg != '') {
            $resStatus = false;
        }

        // Check for records with non-existent 'Added By' users.
        $wrongUser_Add = mysql__select_value($this->mysqli,
                'SELECT count(distinct rec_ID) FROM Records left join sysUGrps on rec_AddedByUGrpID=ugr_ID where ugr_ID is null');
        $wrongUser_Add = intval($wrongUser_Add);

        // Check for records with non-existent 'Owner' users.
        $wrongUser_Owner = mysql__select_value($this->mysqli,
                'SELECT count(distinct rec_ID) FROM Records left join sysUGrps on rec_OwnerUGrpID=ugr_ID where ugr_ID is null');
        $wrongUser_Owner = intval($wrongUser_Owner);

        // If no issues are found, display success message.
        if($wrongUser_Add==0 && $wrongUser_Owner==0){
            $resMsg .= '<div><h3 class="res-valid">OK: All records have valid Owner and Added by User references</h3></div>';

            if($wasassigned1>0){
                $resMsg .= "<div>$wasassigned1 records 'Added by' value were set to user # 2 Database Manager</div>";
            }
            if($wasassigned2>0){
                $resMsg .= "<div>$wasassigned2 records were attributed to owner # 2 Database Manager</div>";
            }
        }
        else
        {
            // If invalid references are found, display a warning and provide an option to fix the issues.
            $resStatus = false;
            $resMsg .= DIV_S;
            if($wrongUser_Add>0){
                $resMsg .= "<h3> $wrongUser_Add records are added by non-existent users</h3>";
            }
            if($wrongUser_Owner>0){
                $resMsg .= "<h3> $wrongUser_Owner records are owned by non-existent users</h3>";
            }

            $resMsg .= '<br><br><button data-fix="owner_ref">Attribute them to owner # 2 Database Manager</button></div>';
        }

        // Return the status and message as an associative array.
        return array('status'=>$resStatus,'message'=>$resMsg);
    }

    /**
     * Updates invalid user group references in the Records table.
     *
     * This function assigns the default user group ID (2, Database Manager) to records
     * where the current user group reference is invalid (i.e., where the user group does not exist).
     * It operates on the specified column and logs the type of change made (e.g., 'Added By' or 'Owner').
     *
     * @param string $column The column in the Records table to update (e.g., 'rec_AddedByUGrpID' or 'rec_OwnerUGrpID').
     * @param string $msg A descriptive message indicating the type of user reference being updated (e.g., 'reference (Added By)' or 'ownership').
     * @return string Returns an empty string if the update is successful, or an error message if the query fails.
     */
    private function updateGroupIds($column, $msg) {
        // SQL query to update the specified column in Records where the user group reference is invalid (ugr_ID is NULL)
        $query = "UPDATE Records
                  LEFT JOIN sysUGrps ON $column = ugr_ID
                  SET $column = 2
                  WHERE ugr_ID IS NULL";

        // Execute the query and check for errors
        $result = mysql__exec_param_query($this->mysqli, $query, null, true);

        // If the result is a string (indicating an error), return an error message
        if (is_string($result)) {
            return "<div class='error'>Cannot assign correct User $msg for Records.</div>";
        }

        // Return an empty string if the operation was successful
        return '';
    }

    /**
    * Check
    *
    * @param array $params
    */
    public function check_dup_terms($params=null){
        global $TL;

        $resStatus = true;
        $resMsg = '';

        $mysqli = $this->mysqli;

        if(@$params['data']){
            $lists = json_decode($params['data'], true);
        }else{
            $lists = getTermsWithIssues($mysqli);
        }
        $trmWithWrongParents = prepareIds(@$lists["trm_missed_parents"]);
        $trmWithWrongInverse = prepareIds(@$lists["trm_missed_inverse"]);
        $trmDuplicates = @$lists["trm_dupes"];

        $wasassigned1 = 0;
        $wasassigned2 = 0;
        if(@$params['fix']=="1")
        {
                mysql__safe_updatess($mysqli, false);

                if(!isEmptyArray($trmWithWrongParents)){

                    $trash_group_id = mysql__select_value($mysqli, 'select vcg_ID from defVocabularyGroups where vcg_Name="Trash"');

                    if($trash_group_id>0){

                        $query = 'UPDATE defTerms set trm_ParentTermID=NULL, trm_VocabularyGroupID='.intval($trash_group_id)
                        .' WHERE trm_ID in ('.implode(',',$trmWithWrongParents).')';

                        $wasassigned1 = mysql__exec_param_query($this->mysqli, $query, null, true );
                        if(is_string($wasassigned1))
                        {
                            $resMsg = '<div class="error">Cannot delete invalid pointers to parent terms. Error:'.$wasassigned1.DIV_E;
                            $resStatus = false;
                        }else{
                            $trmWithWrongParents = array();//reset
                        }

                    }else{
                            $resMsg = '<div class="error">Cannot find "Trash" group to fix invalid pointers to parent terms.</div>';
                            $resStatus = false;
                    }
                }

                if(!isEmptyArray($trmWithWrongInverse)){
                    $query = 'UPDATE defTerms set trm_InverseTermID=NULL '
                    .' WHERE trm_ID in ('.implode(',',$trmWithWrongInverse).')';

                    $wasassigned2 = mysql__exec_param_query($this->mysqli, $query, null, true );
                    if(is_string($wasassigned2))
                    {
                        $resMsg = '<div class="error">Cannot clear missing inverse terms ids. Error:'.$wasassigned2.DIV_E;
                        $resStatus = false;
                    }else{
                        $trmWithWrongInverse = array();//reset
                    }

                }

                mysql__safe_updatess($mysqli, true);
        }

        if(empty($trmWithWrongParents) && empty($trmWithWrongInverse)){
                $resMsg .= '<div><h3 class="res-valid">OK: All terms have valid inverse and parent term references</h3></div>';

                if($wasassigned1>0){
                    $resMsg .= "<div>$wasassigned1 terms with wrong parent terms moved to 'Trash' group as vocabularies</div>";
                }
                if($wasassigned2>0){
                    $resMsg .= "<div>$wasassigned2 wrong inverse term references are cleared</div>";
                }
            }
            else
            {

                $resMsg .= DIV_S;
                if(!empty($trmWithWrongParents)){

                    $resMsg .= '<h3>'.count($trmWithWrongParents).' terms have wrong parent term references</h3>';
                    $cnt = 0;
                    foreach ($trmWithWrongParents as $trm_ID) {
                        $resMsg .= ('<br>'.$trm_ID.'  '.$TL[$trm_ID]['trm_Label']);
                        if($cnt>30){
                          $resMsg .= ('<br>'.count($trmWithWrongParents)-$cnt.' more...');
                          break;
                        }
                        $cnt++;
                    }
                    $resStatus = false;
                }
                if(!empty($trmWithWrongInverse)){

                    $resMsg .= '<h3>'.count($trmWithWrongInverse).' terms have wrong inverse term references</h3>';
                    $cnt = 0;
                    foreach ($trmWithWrongInverse as $trm_ID) {
                        $resMsg .= '<br>'.$trm_ID.'  '.$TL[$trm_ID]['trm_Label'];
                        if($cnt>30){
                          $resMsg .= '<br>'.count($trmWithWrongInverse)-$cnt.' more...';
                          break;
                        }
                        $cnt++;
                    }
                    $resStatus = false;
                }

                $resMsg .= '<br><br><button data-fix="dup_terms">Correct wrong parent and inverse term references</button></div>';
        }

        if(!empty($trmDuplicates)){
            $resStatus = false;

            $resMsg .= '<h3>Terms are duplicated or ending in a number: these may be the result of automatic duplicate-avoidance. '
            .'If so, we suggest deleting the numbered term or using Design > Vocabularies to merge it with the un-numbered version.</h3>';
            foreach ($trmDuplicates as $parent_ID=>$dupes) {
                $resMsg .= '<div style="padding-top:10px;font-style:italic">parent '.intval($parent_ID).'  '
                    .htmlspecialchars($TL[$parent_ID]['trm_Label']).DIV_E;
                foreach ($dupes as $trm_ID) {
                    $resMsg .= '<div style="padding-left:60px">'.intval($trm_ID).'  '
                    .htmlspecialchars($TL[$trm_ID]['trm_Label']).DIV_E;
                }
            }
        }

        return array('status'=>$resStatus,'message'=>$resMsg);
    }

    /**
     * Check field types for inconsistencies or invalid data and optionally repair them.
     *
     * This function checks the validity of field types in terms of terms, non-selectable terms,
     * and record type constraints. It can also auto-repair invalid fields if in fix mode.
     *
     * @param array|null $params An associative array of parameters.
     *                           'data' or 'show' can be used to check or display field types.
     *                           'rt' for the record type ID.
     * @return array Returns an associative array with 'status' and 'message'.
     *               'status' is true if valid or repairable, false otherwise.
     *               'message' contains success/error or repair information.
     */
    public function check_field_type($params=null){
        global $TL;

        $mysqli = $this->mysqli;
        $lists = array();

        if(@$params['data']){
            $lists = json_decode($params['data'], true);
        }elseif (@$params['show']) {

            $lists = getInvalidFieldTypes($mysqli, intval(@$params['rt']));//in verifyFieldTypes.php
        }

        $resMsgSuccess = '<h3 class="res-valid">OK: All field type definitions are valid</h3></div>';

        if (isEmptyArray(@$lists["terms"]) &&
            isEmptyArray(@$lists["terms_nonselectable"]) &&
            isEmptyArray(@$lists["rt_contraints"])){

                return array('status'=>true, 'message'=>DIV_S.$resMsgSuccess);
        }

        $resStatus = true;
        $resMsg = '';

        // Check if fix mode is enabled
        if($this->isFixMode($params)){

            try {
                $k = $this->repairInvalidFields(@$lists['terms'], 'dty_JsonTermIDTree', 'validTermsString');
                $k += $this->repairInvalidFields(@$lists['terms_nonselectable'], 'dty_TermIDTreeNonSelectableIDs', 'validNonSelTermsString');
                $k += $this->repairInvalidFields(@$lists['rt_contraints'], 'dty_PtrTargetRectypeIDs', 'validRectypeConstraint');

                $resMsg = $k.' field'.($k>1?'s have':' has')
                .' been repaired. If you have unsaved data in an edit form, save your changes and reload the page to apply revised/corrected field definitions';
                return array('status'=>true,'message'=>DIV_S.$resMsg.$resMsgSuccess);

            } catch (\Exception $e) {
                $resMsg = '<div class="error">SQL error updating field type '.$e->getMessage().DIV_E;
            }

        }else{

            $contact_us = CONTACT_HEURIST_TEAM;
            $resMsg = <<<HEADER
            <br><h3>Warning: Inconsistent field definitions</h3><br>&nbsp;<br>
            The following field definitions have inconsistent data (unknown codes for terms and/or record types). This is nothing to be concerned about, unless it reoccurs, in which case please $contact_us<br><br>
            To fix the inconsistencies, please click here: <button data-fix="field_type">Auto Repair</button>  <br>&nbsp;<br>
            <hr>
            HEADER;

            $resMsg .= $this->generateFieldMessages(@$lists["terms"], 'invalidTermIDs', 'term ID');
            $resMsg .= $this->generateFieldMessages(@$lists["terms_nonselectable"], 'invalidNonSelectableTermIDs', 'non selectable term ID');
            $resMsg .= $this->generateFieldMessages(@$lists["rt_contraints"], 'invalidRectypeConstraint', 'record type constraint');

        }

        return array('status'=>false, 'message'=>$resMsg);
    }


    /**
     * Repair invalid field types by updating the database.
     *
     * This function attempts to repair invalid fields by updating the specified column with valid data.
     *
     * @param array $fieldList A list of invalid fields to be repaired.
     * @param string $column The database column to update.
     * @param string $validField The key representing the valid data for each field in the fieldList.
     * @return int The number of fields successfully repaired.
     * @throws \Exception If there is an error during the update operation.
     */
    private function repairInvalidFields($fieldList, $column, $validField){

        if(isEmptyArray($fieldList)){
            return 0;
        }
        $k = 0;

        foreach ($fieldList as $row) {
            $query="UPDATE defDetailTypes SET $column=? WHERE dty_ID=".intval($row['dty_ID']);
            $res = mysql__exec_param_query($this->mysqli, $query, array('s',$row[$validField]), true );
            if(is_string($res)){
                throw new \Exception($row['dty_ID'] . ". Error: $res");
            }
            $k++;
        }

        return $k;
    }

    /**
     * Generate messages for fields with invalid data.
     *
     * This function generates error messages for fields that contain invalid term IDs, non-selectable term IDs, or record type constraints.
     *
     * @param array $fieldList A list of fields to check for invalid data.
     * @param string $invalid_ids The key for the invalid data (term IDs, non-selectable term IDs, etc.).
     * @param string $msgType The type of message to generate (term ID, record type constraint, etc.).
     * @return string The generated HTML error messages for each invalid field.
     */
    private function generateFieldMessages($fieldList, $invalid_ids, $msgType)
    {
        $resMsg = '';
        if (is_array($fieldList)) {
            foreach ($fieldList as $row) {
                $cnt = count($row[$invalid_ids]);

                $resMsg .= '<div class="msgline"><b>' . htmlspecialchars($row['dty_Name']) . '</b> field (code ' .
                            intval($row['dty_ID']) . ') has ' . $cnt . ' invalid ' . $msgType
                            . ($cnt>1?'s':'')
                            . ' (code: ' . htmlspecialchars(implode(',', $row[$invalid_ids])) . ')</div>';
            }
        }
        return $resMsg;
    }


    /**
    * Check Db definitions - wrong default valuss
    *
    * @param array $params
    */
    public function check_default_values($params=null){

        $resStatus = true;
        $resMsg = '';

        $mysqli = $this->mysqli;

        if(@$params['data']){
            $lists = json_decode($params['data'], true);
        }else{
            $lists = getInvalidDefaultValues($mysqli, intval(@$params['rt']));//in getFieldTypeDefinitionErrors.php
        }

        $rstWithInvalidDefaultValues = @$lists["rt_defvalues"];

        if (!isEmptyArray($rstWithInvalidDefaultValues)){


            $resMsg = <<<'HEADER'
            <br><h3>Warning: Wrong field default values for record type structures</h3><br>&nbsp;<br>
            The following fields use unknown terms as default values. <u>These default values have been removed</u>.
            <br>&nbsp;<br>
            HEADER;
// <br>You can define new default value by clicking on the name in the list below and editing the field definition.<br>&nbsp;<br>

            foreach ($rstWithInvalidDefaultValues as $row) {
                $resMsg .= '<div class="msgline"><b>'
                    .htmlspecialchars($row['rst_DisplayName'])
                    .'</b> field (code '.intval($row['dty_ID']).' ) in record type '
                    .htmlspecialchars($row['rty_Name'])
                    .' had invalid default value ('.($row['dty_Type']=='resource'?'record ID ':'term ID ')
                    .htmlspecialchars($row['rst_DefaultValue'])
                    .'<span style="font-style:italic">'.htmlspecialchars($row['reason']).'</span></div>';
            }//for

        }else{
            $resMsg = '<h3 class="res-valid">OK: All default values in record type structures are valid</h3>';
        }

        return array('status'=>$resStatus,'message'=>$resMsg);
    }


    /**
    * Check
    *
    * @param array $params
    */
    public function check_pointer_targets($params=null){

        $resStatus = true;
        $resMsg = '';
        $wasdeleted = 0;
        $mysqli = $this->mysqli;

        if($this->isFixMode($params)){

            $query = 'DELETE d from recDetails d
            left join defDetailTypes dt on dt.dty_ID = d.dtl_DetailTypeID
            left join Records b on b.rec_ID = d.dtl_Value and b.rec_FlagTemporary!=1
            where dt.dty_Type = "resource"
            and b.rec_ID is null';
            $res = $mysqli->query( $query );
            if(! $res )
            {
                $resMsg = error_Div('Cannot delete invalid pointers from Records');
                $resStatus = false;
            }else{
                $wasdeleted = $mysqli->affected_rows;
            }
        }

        $res = $mysqli->query('select a.rec_ID, dty_ID, dty_Name, a.rec_Title, a.rec_RecTypeID, dtl_Value
            from recDetails
            left join defDetailTypes on dty_ID = dtl_DetailTypeID
            left join Records a on a.rec_ID = dtl_RecID and a.rec_FlagTemporary!=1
            left join Records b on b.rec_ID = dtl_Value and b.rec_FlagTemporary!=1
            where dty_Type = "resource"
            and a.rec_ID is not null
            and b.rec_ID is null ORDER BY a.rec_ID');
        $bibs = array();
        $ids = array();
        while ($row = $res->fetch_assoc()) {
            array_push($bibs, $row);
        }
        $res->close();

        if(empty($bibs)){
            $resMsg = '<div><h3 class="res-valid">OK: All record pointers point to a valid record</h3></div>';

            if($wasdeleted>0){
                $resMsg .= "<div>$wasdeleted invalid pointer(s) were removed from database</div>";
            }
        }
        else
        {
            $resStatus = false;

            $this->_outStreamInit();
            fwrite($this->out, $resMsg);

            $fixMsg = '<div style="padding:20px 0px">To fix the inconsistencies, please click here: <button data-fix="pointer_targets">Delete ALL faulty pointers</button></div>';
            $this->printList('Records record pointers to non-existent records', $fixMsg, $bibs, 'recCB0');

            $resMsg = $this->_outStreamRes();
        }


        return array('status'=>$resStatus,'message'=>$resMsg);
    }

    /**
    * Check
    *
    * @param array $params
    */
    public function check_target_types($params=null){

        $resStatus = true;
        $resMsg = '';
        $mysqli = $this->mysqli;

        $res = $mysqli->query('SELECT dtl_RecID AS rec_ID, dty_ID, dty_Name, ' //dty_PtrTargetRectypeIDs,
            .' rec_ID as target_ID, rec_Title, rty_Name ' //, rec_RecTypeID
            .' FROM defDetailTypes'
            .' left join recDetails on dty_ID = dtl_DetailTypeID'
            .' left join Records on rec_ID = dtl_Value and rec_FlagTemporary!=1'
            .' left join defRecTypes on rty_ID = rec_RecTypeID'
            .' where dty_Type = "resource"'
            .' and dty_PtrTargetRectypeIDs > 0'
        .' and (INSTR(concat(dty_PtrTargetRectypeIDs,\',\'), concat(rec_RecTypeID,\',\')) = 0) ORDER BY dtl_RecID');

        $bibs = array();
        while ($row = $res->fetch_assoc()){
            $rec_title = (substr(strip_tags($row['rec_Title']), 0, 50));
            $row['dtl_Value'] = "points to {$row['target_ID']} ({$row['rty_Name']}) - $rec_title";
            unset($row['rec_Title']);
            $bibs[] = $row;
        }
        $res->close();

        if(empty($bibs)){
            $resMsg = '<div><h3 class="res-valid">OK: All record pointers point to the correct record type</h3></div>';
        }
        else
        {
            $resStatus = false;
            $this->_outStreamInit();
            fwrite($this->out, $resMsg);

            $fixMsg = null;
            $this->printList('Records with record pointers to the wrong record type', $fixMsg, $bibs, 'recCB2');

            $resMsg = $this->_outStreamRes();
        }

        return array('status'=>$resStatus,'message'=>$resMsg);
    }


    /**
    * Check
    *
    * @param array $params
    */
    public function check_target_parent($params=null){

        $resStatus = true;
        $resMsg = '';
        $wasdeleted = 0;
        $wasadded1 = 0;

        $mysqli = $this->mysqli;
        $this->system->defineConstant('DT_PARENT_ENTITY');
        $dt_parent_entity_field_id = DT_PARENT_ENTITY;

        $error_msg = 'Cannot delete invalid pointers from Records';

        if(false && $this->isFixMode($params)){ //OLD WAY DISABLED

            //remove pointer field in parent records that does not have reverse in children
            $query = 'DELETE parent FROM Records parentrec, defRecStructure, recDetails parent '
            .'LEFT JOIN recDetails child ON child.dtl_DetailTypeID='.DT_PARENT_ENTITY.' AND child.dtl_Value=parent.dtl_RecID '
            .'LEFT JOIN Records childrec ON parent.dtl_Value=childrec.rec_ID '
            .'WHERE '
            .'parentrec.rec_ID=parent.dtl_RecID AND rst_CreateChildIfRecPtr=1 '
            .'AND rst_RecTypeID=parentrec.rec_RecTypeID AND rst_DetailTypeID=parent.dtl_DetailTypeID '
            .'AND child.dtl_RecID is NULL';

            $res = $mysqli->query( $query );
            if(! $res )
            {
                $resMsg = error_Div($error_msg);
                $resStatus = false;
            }else{
                $wasdeleted1 = $mysqli->affected_rows;
            }
        }

        //find parents with pointer field rst_CreateChildIfRecPtr=1) without reverse pointer in child record
        $query1 = <<<QUERY
            SELECT parentrec.rec_ID, parentrec.rec_RecTypeID, parentrec.rec_Title as p_title,
            parent.dtl_Value, childrec.rec_Title as c_title, child.dtl_Value as f247, child.dtl_ID
            FROM Records parentrec, defRecStructure, recDetails parent, Records childrec
            LEFT JOIN recDetails child ON child.dtl_DetailTypeID=$dt_parent_entity_field_id AND child.dtl_RecID=childrec.rec_ID
            WHERE
            rst_CreateChildIfRecPtr=1 AND rst_RecTypeID=parentrec.rec_RecTypeID AND parentrec.rec_FlagTemporary!=1
            AND parentrec.rec_ID=parent.dtl_RecID AND rst_DetailTypeID=parent.dtl_DetailTypeID
            AND parent.dtl_Value=childrec.rec_ID
            AND (child.dtl_Value!=parent.dtl_RecID OR child.dtl_Value IS NULL)
            ORDER BY parentrec.rec_ID
            QUERY;

        $res = $mysqli->query( $query1 );
        $bibs1 = array();
        $prec_ids1 = array();
        while ($row = $res->fetch_assoc()){

            if(@$params['fix']==1){
                //new way: add missed reverse link to alleged children
                $dtl_ID = intval($row['dtl_ID']);//reference to parent ifd with wrong value
                if($dtl_ID>0){
                    $query2 = 'UPDATE recDetails set dtl_Value='.intval($row['rec_ID']).' WHERE dtl_ID='.$dtl_ID;
                }else{
                    $child_ID = intval($row['dtl_Value']);//child record ID
                    $query2 = 'INSERT into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) VALUES('
                        .$child_ID . ','. DT_PARENT_ENTITY  .', '.intval($row['rec_ID']) . ')';
                }
                $mysqli->query($query2);
                $wasadded1++;
            }else{
                $bibs1[] = $row;
                $prec_ids1[$row['rec_ID']] = 1;
            }

        }//while
        $res->close();

        //find children without reverse pointer in parent record
        $query2 =
"SELECT child.dtl_ID as child_d_id, child.dtl_RecID as child_id, childrec.rec_Title as c_title, child.dtl_Value,
parentrec.rec_Title as p_title, parent.dtl_ID as parent_d_id, parent.dtl_Value rev, dty_ID, rst_CreateChildIfRecPtr,
childrec.rec_FlagTemporary
FROM recDetails child
LEFT JOIN  Records childrec ON childrec.rec_ID=child.dtl_RecID
LEFT JOIN Records parentrec ON child.dtl_Value=parentrec.rec_ID
LEFT JOIN recDetails parent ON parent.dtl_RecID=parentrec.rec_ID AND parent.dtl_Value=childrec.rec_ID
LEFT JOIN defDetailTypes ON parent.dtl_DetailTypeID=dty_ID AND dty_Type='resource' AND parent.dtl_DetailTypeID!=$dt_parent_entity_field_id
LEFT JOIN defRecStructure ON rst_RecTypeID=parentrec.rec_RecTypeID AND rst_DetailTypeID=dty_ID AND rst_CreateChildIfRecPtr=1
WHERE child.dtl_DetailTypeID=$dt_parent_entity_field_id  AND childrec.rec_FlagTemporary!=1 and parent.dtl_Value IS NULL
ORDER BY child.dtl_RecID";


/*
        'SELECT child.dtl_ID as child_d_id, child.dtl_RecID as child_id, childrec.rec_Title as c_title, child.dtl_Value, '
            .'parentrec.rec_Title as p_title, parent.dtl_ID as parent_d_id, parent.dtl_Value rev, dty_ID, rst_CreateChildIfRecPtr, '
            .'childrec.rec_FlagTemporary '
            .' FROM recDetails child '
            .'LEFT JOIN Records parentrec ON child.dtl_Value=parentrec.rec_ID '
            .'LEFT JOIN Records childrec ON childrec.rec_ID=child.dtl_RecID  '
            .'LEFT JOIN recDetails parent ON parent.dtl_RecID=parentrec.rec_ID AND parent.dtl_Value=childrec.rec_ID '
            .'LEFT JOIN defDetailTypes ON parent.dtl_DetailTypeID=dty_ID AND dty_Type="resource" ' //'AND dty_PtrTargetRectypeIDs=childrec.rec_RecTypeID '
            .'LEFT JOIN defRecStructure ON rst_RecTypeID=parentrec.rec_RecTypeID AND rst_DetailTypeID=dty_ID AND rst_CreateChildIfRecPtr=1 '
            .'WHERE child.dtl_DetailTypeID='.DT_PARENT_ENTITY .' AND parent.dtl_DetailTypeID!='.DT_PARENT_ENTITY
            .' AND dty_Type="resource" AND (rst_CreateChildIfRecPtr IS NULL OR rst_CreateChildIfRecPtr!=1) ORDER BY child.dtl_RecID';
*/
        $res = $mysqli->query( $query2 );

        $bibs2 = array();
        $prec_ids2 = array();
        $det_ids = array();
        if($res){
            while ($row = $res->fetch_assoc()){
                if($row['rec_FlagTemporary']==1) {continue;}
                if(in_array( $row['child_d_id'], $det_ids)) {continue;}
                $bibs2[] = $row;
                $prec_ids2[$row['dtl_Value']] = 1;  //remove DT_PARENT_ENTITY from orphaned children
                array_push($det_ids, intval($row['child_d_id']));
                /*
                if($row['parent_d_id']>0){
                // keep dtl_ID of pointer field in parent record
                // to remove this 'fake' pointer in parent - that's wrong - need to remove DT_PARENT_ENTITY in child
                $det_ids[] = $row['parent_d_id'];
                }
                */
            }//while
            $res->close();
        }else{
             $resMsg .= '<div class="error">Cannot execute query "find children without reverse pointer in parent record".</div>';
             $resStatus = false;
        }

        $wasdeleted2 = 0;
        if(@$params['fix']==2 && !empty($det_ids)){

            $query = 'DELETE FROM recDetails WHERE dtl_ID in ('.implode(',',$det_ids).')';
            $res = $mysqli->query( $query );
            if(! $res )
            {
                $resMsg .= error_Div($error_msg);
                $resStatus = false;
            }else{
                $wasdeleted2 = $mysqli->affected_rows;
                $bibs2 = array();
            }
        }

        $url_icon_placeholder = ICON_PLACEHOLDER;
        $url_icon_extlink = ICON_EXTLINK;

        if(empty($bibs1)){
            $resMsg .= '<div><h3>OK: All parent records are correctly referenced by their child records</h3></div>';

            if($wasdeleted1>1){
                $resMsg .= "<div>$wasdeleted1 invalid pointer(s) were removed from database</div>";
            }
            if($wasadded1>0){
                $resMsg .= "<div>$wasadded1 reverse pointers were added to child records</div>";
            }
        }

        else
        {
            $resStatus = false;

            $url_all = $this->getAllURL(array_keys($prec_ids1));

            $resMsg .= <<<HEADER
                <br><h3>Parent records which are not correctly referenced by their child records (missing pointer to parent)</h3>

                <div>To fix the inconsistencies, please click here:
                    <button data-fix="target_parent">Add missing parent record pointers to child records</button>
                </div>

                <a target=_new href="$url_all">(show results as search)</a>
                <a target=_new href="#selected_link" data-show-selected="recCB3">(show selected as search)</a>
                <table role="presentation">
                <tr>
                    <td colspan="7">
                        <label><input type="checkbox" data-mark-all="recCB">Mark all</label>
                    </td>
                </tr>
                <tr>
                    <td colspan="3"></td>
                    <td><strong>Parent record title</strong></td>
                    <td colspan="2"></td>
                    <td><strong>Child record title</strong></td>
                </tr>
HEADER;

                $url_icon_placeholder = ICON_PLACEHOLDER;
                $url_icon_extlink = ICON_EXTLINK;

                foreach ($bibs1 as $row) {

                    $url_icon_parent = HEURIST_RTY_ICON.$row['rec_RecTypeID'];
                    $url_rec_parent = $this->getEditURL($row['rec_ID']);
                    $url_rec_child =  $this->getEditURL($row['dtl_Value']);
                    $rec_title_parent = substr(strip_tags($row['p_title']),0,50);
                    $rec_title_child = substr(strip_tags($row['c_title']),0,50);

                    $resMsg .= <<<EOT
                    <tr>
                        <td><input type=checkbox name="recCB3" value={$row['rec_ID']}></td>
                        <td><img alt class="rft" style="background-image:url($url_icon_parent)" src="$url_icon_placeholder"></td>
                        <td style="white-space: nowrap;"><a target=_new href="$url_rec_parent">
                                {$row['rec_ID']} <img alt src="$url_icon_extlink" style="vertical-align:middle" title="Click to edit record">
                            </a></td>
                        <td class="truncate" style="max-width:400px">$rec_title_parent</td>

                        <td>points to</td>

                        <td style="white-space: nowrap;"><a target=_new href="$url_rec_child">
                                {$row['dtl_Value']} <img alt src="$url_icon_extlink" style="vertical-align:middle" title="Click to edit record">
                            </a></td>
                        <td class="truncate" style="max-width:400px">$rec_title_child</td>
                    </tr>
                    EOT;

                }//foreach
                $resMsg .= TABLE_E."\n";
        }


        if (empty($bibs2)) {
            $resMsg .= '<br><h3 class="res-valid">OK: All parent records correctly reference records which believe they are their children</h3><br>';

            if($wasdeleted2>1){
                $resMsg .= "<div>$wasdeleted2 invalid pointer(s) were removed from database</div>";
            }
        }
        else
        {
            $resStatus = false;

            $url_all = $this->getAllURL(array_keys($prec_ids2));

            $resMsg .= <<<HEADER
            <br><h3>Child records indicate a parent which does not identify them as their child. </h3>

                <a target=_new href="$url_all">(show results as search)</a>
                <a target=_new href="#selected_link" data-show-selected="recCB4">(show selected as search)</a>

            <table role="presentation">
                <tr>
                    <td colspan="6">
                        <label><input type="checkbox" data-mark-all="recCB4">Mark all</label>
                    </td>
                </tr>
                <tr>
                    <td colspan="2"></td>
                    <td><strong>Child record title</strong></td>
                    <td colspan="2"></td>
                    <td><strong>Parent record title</strong></td>
                </tr>
            HEADER;
            /*
            IJ: For the moment I suggest you omit a FIX button for this case
                fix=2
                <div>To fix the inconsistencies, please click here:
                    <button data-fix="target_parent">Delete broken parent-child fields in alleged children records</button>
                </div>
            */

            foreach ($bibs2 as $row) {

                    $url_rec_parent = $this->getEditURL($row['dtl_Value']);
                    $url_rec_child = $this->getEditURL($row['child_id']);
                    $rec_title_parent = substr(strip_tags($row['p_title']),0,50);
                    $rec_title_child = substr(strip_tags($row['c_title']),0,50);

                    $resMsg .= <<<EOT
                    <tr>
                        <td><input type=checkbox name="recCB4" value={$row['dtl_Value']}></td>
                        <td style="white-space: nowrap;"><a target=_new href="$url_rec_child">
                                {$row['child_id']} <img alt src="$url_icon_extlink" style="vertical-align:middle" title="Click to edit record">
                            </a></td>
                        <td class="truncate" style="max-width:400px">$rec_title_child</td>

                        <td>has reverse 'pointer to parent' in </td>

                        <td style="white-space: nowrap;"><a target=_new href="$url_rec_parent">
                                {$row['dtl_Value']} <img alt src="$url_icon_extlink" style="vertical-align:middle" title="Click to edit record">
                            </a></td>
                        <td class="truncate" style="max-width:400px">$rec_title_parent</td>
                    </tr>
                    EOT;
            }
            $resMsg .= TABLE_E."\n";

        }

        return array('status'=>$resStatus,'message'=>$resMsg);
    }

    /**
    * Check
    *
    * @param array $params
    */
    public function check_empty_fields($params=null){

        $resStatus = true;
        $resMsg = '';

        $wascorrected = 0;
        $mysqli = $this->mysqli;


        if($this->isFixMode($params)){

            mysql__safe_updatess($mysqli, false);
            $mysqli->query('DELETE d.* FROM recDetails d, defDetailTypes, Records a '
                .'WHERE (dtl_ID>0) and (a.rec_ID = dtl_RecID) and (dty_ID = dtl_DetailTypeID) and (a.rec_FlagTemporary!=1)
            and (dty_Type!=\'file\') and ((dtl_Value=\'\') or (dtl_Value is null))');

            $wascorrected = $mysqli->affected_rows;
            mysql__safe_updatess($mysqli, true);
        }

        $total_count_rows = 0;

        //find all fields with empty values
        $res = $mysqli->query('select a.rec_ID, a.rec_RecTypeID, a.rec_Title, dty_ID, dty_Name
            from recDetails, defDetailTypes, Records a
            where (a.rec_ID = dtl_RecID) and (dty_ID = dtl_DetailTypeID) and (a.rec_FlagTemporary!=1)
        and (dty_Type!=\'file\') and ((dtl_Value=\'\') or (dtl_Value is null)) ORDER BY a.rec_ID');

        $total_count_rows = mysql__found_rows($mysqli);

        if($total_count_rows<1){
            $resMsg .= '<div><h3 class="res-valid">OK: There are no fields containing null values</h3></div>';
        }
        if($wascorrected>0){
            $resMsg .= "<div>$wascorrected empty fields were deleted</div>";
        }

        if($total_count_rows>0)
        {
            $resStatus = false;
            $this->_outStreamInit();
            fwrite($this->out, $resMsg);

            $fixMsg =
            '<div>NULL values are not useful in Heurist, as Heurist handles NULLs by storing no value. It is therefore perfectly safe to delete them</div>'
            .'<div style="padding:20px 0px">To REMOVE empty fields, please click here: <button data-fix="empty_fields">Remove all null values</button></div>';
            $this->printList('Records with empty fields', $fixMsg, $res, 'recCB5');

            $resMsg = $this->_outStreamRes();
        }

        return array('status'=>$resStatus,'message'=>$resMsg);
    }

    /**
    * Check
    *
    * @param array $params
    */
    public function check_term_values($params=null){

        $resStatus = true;
        $resMsg = '';

        $wasdeleted = 0;
        $mysqli = $this->mysqli;


        if($this->isFixMode($params)){

            $query = 'DELETE d FROM recDetails d
            left join defDetailTypes dt on dt.dty_ID = d.dtl_DetailTypeID
            left join defTerms b on b.trm_ID = d.dtl_Value
            where dt.dty_Type = "enum" or  dt.dty_Type = "relmarker"
            and b.trm_ID is null';
            $res = $mysqli->query( $query );

            if(! $res )
            {
                $resStatus = false;
                $resMsg = '<div class="error">Cannot delete invalid term values from Records. SQL error: '.$mysqli->error.DIV_E;
            }else{
                $wasdeleted = $mysqli->affected_rows;
            }
        }

        $total_count_rows = 0;

        //find non existing term values
        $res = $mysqli->query('SELECT a.rec_ID, a.rec_RecTypeID, a.rec_Title, dty_ID, dty_Name
            FROM recDetails
            left join defDetailTypes on dty_ID = dtl_DetailTypeID
            left join Records a on a.rec_ID = dtl_RecID
            left join defTerms b on b.trm_ID = dtl_Value
            where (dty_Type = "enum" or dty_Type = "relmarker") and dtl_Value is not null
            and a.rec_ID is not null
            and b.trm_ID is null ORDER BY a.rec_ID');

        $total_count_rows = mysql__found_rows($mysqli);

        if($total_count_rows<1){
            $resMsg .= '<div><h3 class="res-valid">OK: All records have recognisable term values</h3></div>';
        }
        if($wasdeleted>0){
            $resMsg .= "<div>$wasdeleted invalid term value(s) were removed from database</div>";
        }

        if($total_count_rows>0)
        {
            $resStatus = false;
            $this->_outStreamInit();
            fwrite($this->out, $resMsg);

            $fixMsg  = '<div style="padding:20px 0px">To fix the inconsistencies, please click here: <button data-fix="term_values">Delete ALL faulty term values</button></button></div>';
            $this->printList('Records with non-existent term values', $fixMsg, $res, 'recCB6');

            $resMsg = $this->_outStreamRes();
        }


        return array('status'=>$resStatus,'message'=>$resMsg);
    }

    /**
    * Check
    *
    * @param array $params
    */
    public function check_single_value($params=null){

        $resStatus = true;
        $resMsg = '';

        $mysqli = $this->mysqli;


        $total_count_rows = 0;

        //find repetative single value fields

        //unfortunately this query does not work well for mySQL v5.7 (huma-num)
        $res = $mysqli->query('SELECT rec_ID, rec_RecTypeID, dtl_DetailTypeID as dty_ID, rst_DisplayName as dty_Name, rec_Title, count(*) as cnt
            FROM defRecStructure, Records, recDetails
            WHERE rst_MaxValues=1
            AND rst_RecTypeID=rec_RecTypeID AND rec_FlagTemporary!=1
            AND rec_ID = dtl_RecID AND rst_DetailTypeID=dtl_DetailTypeID
            GROUP BY rec_ID, rec_RecTypeID, dtl_DetailTypeID, rst_DisplayName, rec_Title
            HAVING cnt > 1 ORDER BY rec_ID');

        $total_count_rows = mysql__found_rows($mysqli);

        if($total_count_rows<1){
            $resMsg = '<div><h3 class="res-valid">OK: No single value fields exceed 1 value</h3></div>';
        }else
        {
            $resStatus = false;
            $this->_outStreamInit();
            fwrite($this->out, $resMsg);

            $this->printList('Single value fields with multiple values', null, $res, 'recCB7');

            $resMsg = $this->_outStreamRes();
        }

        return array('status'=>$resStatus,'message'=>$resMsg);
    }


    /**
    * Check
    *
    * @param array $params
    */
    public function check_required_fields($params=null){

        $resStatus = true;
        $resMsg = '';

        $mysqli = $this->mysqli;


        $total_count_rows = 0;

        //find missed required fields
        $res = $mysqli->query(
            "select rec_ID, rec_RecTypeID, rec_Title, rst_DetailTypeID as dtyID, rst_DisplayName as dty_Name, dtl_Value
            from Records
            left join defRecStructure on rst_RecTypeID = rec_RecTypeID
            left join recDetails on rec_ID = dtl_RecID and rst_DetailTypeID = dtl_DetailTypeID
            left join defDetailTypes on dty_ID = rst_DetailTypeID
            where rec_FlagTemporary!=1 and rst_RequirementType='required' and (dtl_Value is null or dtl_Value='')
            and dtl_UploadedFileID is null and dtl_Geo is null and dty_Type!='separator' and dty_Type!='relmarker'
        order by rec_ID");

        $total_count_rows = mysql__found_rows($mysqli);

        if($total_count_rows<1){
            $resMsg = '<div><h3 class="res-valid">OK: No required fields with missing or empty values</h3></div>';
        }else
        {
            $resStatus = false;
            $this->_outStreamInit();
            fwrite($this->out, $resMsg);

            $this->printList('Records with missing or empty required values', null, $res, 'recCB8');

            $resMsg = $this->_outStreamRes();
        }

        return array('status'=>$resStatus,'message'=>$resMsg);
    }

    /**
    * Check
    *
    * @param array $params
    */
    public function check_nonstandard_fields($params=null){

        $resStatus = true;
        $resMsg = '';

        $mysqli = $this->mysqli;


        $total_count_rows = 0;

        //find non standard fields
        $query = 'select rec_ID, rec_RecTypeID, dty_ID, dty_Name, dtl_Value, rec_Title
        from Records
        left join recDetails on rec_ID = dtl_RecID
        left join defRecStructure on rst_RecTypeID = rec_RecTypeID and rst_DetailTypeID = dtl_DetailTypeID
        left join defDetailTypes on dtl_DetailTypeID = dty_ID
        where rec_FlagTemporary!=1 AND rst_ID IS NULL';

        $except_ids = array();
        if($this->system->defineConstant('DT_PARENT_ENTITY')){
            $except_ids[] = DT_PARENT_ENTITY;
        }
        if($this->system->defineConstant('DT_WORKFLOW_STAGE')){
            $except_ids[] = DT_WORKFLOW_STAGE;
        }
        if($this->system->defineConstant('DT_ORIGINAL_RECORD_ID')){
            $except_ids[] = DT_ORIGINAL_RECORD_ID;
        }
        if(!empty($except_ids)){
            $query .= ' AND dtl_DetailTypeID NOT IN ('.implode(',',$except_ids).')';
        }

        $query .= ' order by rec_ID';

        $res = $mysqli->query( $query );

        $total_count_rows = mysql__found_rows($mysqli);

        if($total_count_rows<1){
            $resMsg = '<div><h3 class="res-valid">OK: No extraneous fields (fields not defined in the list for the record type)</h3></div>';
        }else
        {
            $resStatus = false;
            $this->_outStreamInit();
            fwrite($this->out, $resMsg);

            $this->printList('Records with extraneous fields (not defined in the list of fields for the record type)', null, $res, 'recCB9');

            $resMsg = $this->_outStreamRes();
        }


        return array('status'=>$resStatus,'message'=>$resMsg);
    }

    /**
    * Check
    *
    * @param array $params
    */
    public function check_invalid_chars($params=null){

        $resStatus = true;
        $resMsg = '';

        $mysqli = $this->mysqli;


        $invalidChars = array(chr(0),chr(1),chr(2),chr(3),chr(4),chr(5),chr(6),chr(7),chr(8),chr(11),chr(12),chr(14),chr(15),chr(16),chr(17),chr(18),chr(19),chr(20),chr(21),chr(22),chr(23),chr(24),chr(25),chr(26),chr(27),chr(28),chr(29),chr(30),chr(31));// invalid chars that need to be stripped from the data.
        $replacements = array("?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?"," ","?","?","?","?","?");
        $charlist = implode('',$invalidChars);

        $now = date(DATE_8601);
        $prevInvalidRecId = 0;
        $is_finished = true;
        $is_error = false;
        $offset = 0;
        $cnt = 0;
        $cnt2 = 0;

        $total_count = mysql__select_value($mysqli, 'SELECT COUNT(dtl_ID) FROM recDetails, defDetailTypes WHERE dtl_DetailTypeID = dty_ID AND (dty_Type=\'freetext\' OR dty_Type=\'blocktext\') ');

        $this->keep_autocommit = mysql__begin_transaction($mysqli);

        while(true){

            $is_finished = true;

            $res = $mysqli->query('SELECT dtl_ID,dtl_RecID,dtl_Value,dty_Name '.
            'FROM recDetails, defDetailTypes '.
            'WHERE dtl_DetailTypeID = dty_ID AND (dty_Type=\'freetext\' OR dty_Type=\'blocktext\') '
            .' ORDER BY dtl_RecID  limit 10000 offset '.$offset);
            if($res){
                while ($row = $res->fetch_assoc()) {

                    $is_finished = false;

                    $text = $row['dtl_Value'];//.chr(17);
                    /*
                    $is_found = false;
                    foreach ($invalidChars as $charCode){
                        if (strpos($text, $charCode)!==false) {
                            $is_found = true;
                            break;
                        }
                    }
                    */

                    $is_found = (strpbrk($text, $charlist)!==false);

                    if ($is_found)
                    {
                        $resStatus = false;

                        if ($prevInvalidRecId < $row['dtl_RecID']) {

                            $url_rec = $this->getEditURL($row['dtl_RecID']);
                            $resMsg .= '<tr><td><a target=_blank href="'.$url_rec. '"> ' . $row['dtl_RecID']. "</a></td></tr>\n";
                            $prevInvalidRecId = $row['dtl_RecID'];

                            //update record header
                            mysql__insertupdate($mysqli, 'Records', 'rec_',
                                    array('rec_ID'=>intval($row['dtl_RecID']), 'rec_Modified'=>$now) );

                            $cnt++;
                        }
                        $resMsg .= "<tr><td> field: ".htmlspecialchars($row['dty_Name']). "</td></tr>\n";

                        $newText = str_replace($invalidChars ,$replacements, $text);

                        $res2 = mysql__insertupdate($mysqli, 'recDetails', 'dtl_',
                                    array('dtl_ID'=>intval($row['dtl_ID']), 'dtl_Value'=>$newText) );


                        if ($res>0) {
                            //$resMsg .= "<tr><td><pre>" . "Updated to : ".htmlspecialchars($newText) . "</pre></td></tr>\n";
                        }else{
                            $is_error = true;
                            $resMsg .= "<tr><td>Error ". res2. " while updating to : ".htmlspecialchars($newText) . "</td></tr>\n";
                            break 2;
                        }

                        $cnt2++;

                    }
                }//while
            }

            if($is_finished){
                break;
            }

            $offset = $offset + 10000;

            if(@$params['progress_report_step']>=0){
                $percentage = intval($offset*100/$total_count);
                if(DbUtils::setSessionVal($params['progress_report_step'].','.$percentage)){
                    //terminated by user
                    $this->_terminatedByUser();
                    return false;
                }
            }

        }//while limit by 10000


        if($resStatus){
                $resMsg = '<div><h3 class="res-valid">OK: All records have valid characters in freetext and blocktext fields.</h3></div>';
        }else{
                if($is_error){
                    $mysqli->rollback();
                }else{
                    $mysqli->commit();
                }

                $resMsg = '<div><h3>'.$cnt.' Records with invalid characters in '.$cnt2
                    .' freetext and blocktext fields</h3></div><table role="presentation">'
                    .$resMsg.TABLE_E;
        }

        if($this->keep_autocommit===true) {$mysqli->autocommit(true);}

        return array('status'=>$resStatus,'message'=>$resMsg);
    }

    /**
    * Check
    *
    * @param array $params
    */
    public function check_title_mask($params=null){

        $resStatus = true;
        $resMsg = '';

        $mysqli = $this->mysqli;

        $rectypes = mysql__select_assoc($mysqli, 'select rty_ID, rty_Name, rty_TitleMask from defRecTypes order by rty_ID');
        //check all rectypes
        foreach ($rectypes as $rty_ID => $rectype) {

            $mask = $rectype['rty_TitleMask'];

            if($mask==null || trim($mask)==""){
                $res = array();
                $res[0] = "Title mask is not defined";
            }else{
                //get human readable
                $res = \TitleMask::execute($mask, $rty_ID, 2, null, ERROR_REP_MSG);
            }

            if(is_array($res)){ //error
                $resStatus = false;
                $resMsg .= "<div style=\"padding:15px 0px 10px 4px;\"><b> $rty_ID : <i>".htmlspecialchars($rectype['rty_Name'])."</i></b> <br> </div>";
                $resMsg .= '<div class="maskCell">Mask: <i>'.htmlspecialchars($mask).'</i></div>';
                $resMsg .= '<div class="errorCell" style="padding:10px 40px"><b>'.(@$res['message']?$res['message']:$res[0]).'</b>'.DIV_E;
            }

        }//for

        if($resStatus){
            $resMsg = '<div><h3 class="res-valid">OK: All record type have valid title masks.</h3></div>';

        }else{
            $resMsg = '<div><h3>Record types with invalid title masks</h3></div>'
                    .$resMsg;
        }

        return array('status'=>$resStatus, 'message'=>$resMsg);
    }

    /**
    * Check
    *
    * @param array $params
    */
    public function check_relationship_cache($params=null){

        $resStatus = true;
        $resMsg = '';

        $mysqli = $this->mysqli;

        if($this->isFixMode($params)){

            if(recreateRecLinks($this->system, true)){
                $resMsg = '<div><h3 class="res-valid">Relationship cache has been successfully recreated</h3></div>';
            }else{
                $response = $this->system->getError();
                $resMsg = "<div><h3 class=\"error\">{$response['message']}</h3></div>";
                $resStatus = false;
            }
        }

        if($resStatus){

            $is_table_exist = hasTable($mysqli, 'recLinks');

            if($is_table_exist){

                if(!defined('RT_RELATION')) {$this->system->defineConstant('RT_RELATION');}

                //count of relations
                $query = 'SELECT count(rec_ID) FROM Records '
                        .'where rec_RecTypeID='.RT_RELATION
                        .' and rec_FlagTemporary=0';
                $cnt_relationships = intval(mysql__select_value($mysqli, $query));

                //count of missed relations in recLinks
                $query = 'SELECT count(rec_ID) FROM Records left join recLinks on rec_ID=rl_RelationID '
                        .'where rec_RecTypeID='.RT_RELATION
                        .' and rec_FlagTemporary=0 and rl_RelationID is null';
                $missed_relationships = intval(mysql__select_value($mysqli, $query));


                //count of links
                $query = 'SELECT count(dtl_ID) FROM Records, recDetails, defDetailTypes '
                        .' where rec_ID=dtl_RecID and dtl_DetailTypeID=dty_ID and dty_Type="resource"'
                        .' and rec_FlagTemporary=0';
                $cnt_links = intval(mysql__select_value($mysqli, $query));

                //count of missed links
                $query = 'SELECT count(dtl_ID) FROM Records, defDetailTypes, recDetails left join recLinks on dtl_ID=rl_DetailID'
                        .' where rec_RecTypeID!='.RT_RELATION
                        .' and rec_ID=dtl_RecID and dtl_DetailTypeID=dty_ID and dty_Type="resource"'
                        .' and rec_FlagTemporary=0 and rl_DetailID is null';
                $missed_links = intval(mysql__select_value($mysqli, $query));


                $resMsg .= '<div style="padding:5px">Total count of relationships:&nbsp;<b>'.$cnt_relationships.'</b>'
                        .($missed_relationships>0?'':'&nbsp;&nbsp;&nbsp;&nbsp;All relationships are in cache. Cache is OK.').DIV_E;

                if($missed_relationships>0){
                    $resMsg .='<div style="padding:5px;color:red">Missed relationships in cache:&nbsp;<b>'.$missed_relationships.'</b>'.DIV_E;
                }

                $resMsg .= '<br><div style="padding:5px">Total count of links/resources:&nbsp;<b>'.$cnt_links.'</b>'
                        .($missed_links>0?'':'&nbsp;&nbsp;&nbsp;&nbsp;All links are in cache. Cache is OK.').DIV_E;

                if($missed_links>0){
                    $resMsg .= '<div style="padding:5px;color:red">Missed links in cache:&nbsp;<b>'.$missed_links.'</b>'.DIV_E;
                }

            }else{
                $resMsg .= '<div><h3 class="error">Relationship cache is not found</h3></div>';
            }

            $cache_missed = ((!$is_table_exist) || $missed_relationships>0 || $missed_links>0);

            if($cache_missed){
                $resStatus = false;
                $resMsg .= '<div>Recreate Relationship cache to restore missing entriese:</div>';
            }else{
                $resMsg .= '<div><h3 class="res-valid">OK: Relationship cache is valid</h3></div>';
            }
            $resMsg .= '<div><button data-fix="relationship_cache">Recreates Relationship Cache</button></div>';
        }

        return array('status'=>$resStatus, 'message'=>$resMsg);
    }

    /**
    * Check
    *
    * @param array $params
    */
    public function check_dateindex($params=null){

        $resStatus = true;
        $resMsg = '';

        if($this->isFixMode($params)){

            $rep = recreateRecDetailsDateIndex($this->system, true, false, 0, @$params['progress_report_step']);//see utils_db

            if(is_bool($rep) && $rep==false){

                $response = $this->system->getError();
                $resMsg = "<div><h3 class=\"error\">{$response['message']}</h3></div>";
                $resStatus = false;
            }else{
                $resMsg = '<div><h3 class="res-valid">Record Details Date Index has been successfully recreated</h3></div>';
                if(is_array($rep)){
                    $resMsg .= implode('<br>', $rep);
                }
            }
        }

        if($resStatus){

            $mysqli = $this->mysqli;

            //count of date fields
            $query = 'SELECT dty_ID FROM defDetailTypes WHERE dty_Type="date"';
            $fld_dates = mysql__select_list2($mysqli, $query);
            $fld_dates = implode(',',prepareIds($fld_dates));
            $query = 'SELECT count(dtl_ID) FROM recDetails  WHERE dtl_DetailTypeID in ('.$fld_dates.')';//' AND dtl_Value!=""';
            $cnt_dates = intval(mysql__select_value($mysqli, $query));

            $query = 'SELECT count(dtl_ID) FROM recDetails WHERE dtl_DetailTypeID in ('.$fld_dates.') AND dtl_Value LIKE "%estMinDate%"';
            $cnt_fuzzy_dates = mysql__select_value($mysqli, $query);

            $resMsg .= '<div style="padding:5px">Total count of date fields:&nbsp;<b>'.$cnt_dates.'</b>'.DIV_E;
            $resMsg .= '<div style="padding:5px">Fuzzy/complex dates:&nbsp;<b>'.intval($cnt_fuzzy_dates).'</b>'.DIV_E;


            $is_table_exist = hasTable($mysqli, 'recDetailsDateIndex');

            if($is_table_exist){

                //count of index
                $query = 'SELECT count(rdi_DetailID) FROM recDetailsDateIndex';
                $cnt_index = intval(mysql__select_value($mysqli, $query));

                $query = 'SELECT count(rdi_DetailID) FROM recDetailsDateIndex WHERE rdi_estMinDate=0 AND rdi_estMaxDate=0';
                $cnt_empty = intval(mysql__select_value($mysqli, $query));

                if($cnt_dates==$cnt_index && $cnt_empty==0){
                    $resMsg .= '<div style="padding:5px">'
                        .'&nbsp;&nbsp;&nbsp;&nbsp;All date entries are in index. Index values are OK</div>';
                }

                if($cnt_dates > $cnt_index || $cnt_empty>0){
                    if($cnt_dates > $cnt_index){

                        $query = "SELECT dtl_RecID FROM recDetails WHERE dtl_DetailTypeID IN ($fld_dates) AND dtl_ID NOT IN (SELECT rdi_DetailID FROM recDetailsDateIndex)";
                        $rec_IDs = mysql__select_list2($mysqli, $query);
                        $resMsg .= '<div style="padding:5px;color:red">Missed entries in index:&nbsp;<b>'.($cnt_dates - $cnt_index).'</b>&nbsp;&nbsp;&nbsp;'
                                 . '<a href="' . $this->getAllURL($rec_IDs) . '" target="_blank" rel="noopener">view as search</a>'.DIV_E;
                    }
                    if($cnt_empty > 0){

                        $query = "SELECT rdi_RecID FROM recDetailsDateIndex WHERE rdi_estMinDate = 0 AND rdi_estMaxDate = 0";
                        $rec_IDs = mysql__select_list2($mysqli, $query);
                        $resMsg .= '<div style="padding:5px;color:red">Empty dates in index:&nbsp;<b>'.($cnt_empty).'</b>&nbsp;&nbsp;&nbsp;'
                                 . '<a href="' . $this->getAllURL($rec_IDs) . '" target="_blank" rel="noopener">view as search</a>'.DIV_E;
                    }

                    $resMsg .= '<div><h3 class="error">Recreate Details Date Index table to restore missing entries</h3></div>';

                    $resStatus = false; //index is outdated
                }else{
                    $resMsg .= '<div><h3 class="res-valid">OK: Record Details Date Index is valid</h3></div>';
                }

            }else{
                    $resStatus = false;
                    $resMsg .= '<div><h3 class="error">Record Details Date Index table does not exist</h3></div>';
            }

            $resMsg .= '<div><button data-fix="dateindex">Recreate Date Index</button></div>';
        }

        return array('status'=>$resStatus, 'message'=>$resMsg);
    }

    /**
    * Check
    *
    * @param array $params
    */
    public function check_defgroups($params=null){

        $resStatus = true;
        $resMsg = '';

        $mysqli = $this->mysqli;

        $div_valid = '<div><h3 class="res-valid">';
        $div_found = '<div><h3 class="error">Found ';

        // record types =========================
        $cnt = intval(mysql__select_value($mysqli, 'select count(rty_ID) from defRecTypes left join defRecTypeGroups on rty_RecTypeGroupID=rtg_ID WHERE rtg_ID is null'));
        if($cnt>0){

            $resStatus = false;
            $resMsg .= $div_found.$cnt.' record types that are not belong any group</h3></div>';

            //find trash group
            $trash_id = mysql__select_value($mysqli, 'select rtg_ID FROM defRecTypeGroups WHERE rtg_Name="Trash"');
            if($trash_id>0){

                $mysqli->query('update defRecTypes left join defRecTypeGroups on rty_RecTypeGroupID=rtg_ID set rty_RecTypeGroupID='.intval($trash_id).' WHERE rtg_ID is null');

                $cnt2 = $mysqli->affected_rows;

                $resMsg .= $div_valid.$cnt2.' record types have been placed to "Trash" group</h3></div>';
            }else{
                $resMsg .= '<div><h3 class="error">Cannot find record type "Trash" group. </h3></div>';
            }
        }else{
            $resMsg .= '<div><h3 class="res-valid">OK: All Record Types belong to existing groups</h3></div>';
        }

        // fields types =========================
        $cnt = intval(mysql__select_value($mysqli, 'select count(dty_ID) from defDetailTypes left join defDetailTypeGroups on dty_DetailTypeGroupID=dtg_ID WHERE dtg_ID is null'));
        if($cnt>0){

            $resStatus = false;
            $resMsg .= $div_found.$cnt.' base field types that are not belong any group</h3></div>';

            //find trash group
            $trash_id = mysql__select_value($mysqli, 'select dtg_ID FROM defDetailTypeGroups WHERE dtg_Name="Trash"');
            if($trash_id>0){

                $mysqli->query('update defDetailTypes left join defDetailTypeGroups on dty_DetailTypeGroupID=dtg_ID set dty_DetailTypeGroupID='.intval($trash_id).' WHERE dtg_ID is null');

                $cnt2 = $mysqli->affected_rows;

                $resMsg .= $div_valid.$cnt2.' field types have been placed to "Trash" group</h3></div>';
            }else{
                $resMsg .= '<div><h3 class="error">Cannot find field type "Trash" group.</h3></div>';
            }
        }else{
            $resMsg .= '<div><h3 class="res-valid">OK: All Base Field Types belong to existing groups</h3></div>';
        }

        // vocabularies =========================
        $cnt = intval(mysql__select_value($mysqli, 'select count(trm_ID) from defTerms left join defVocabularyGroups on trm_VocabularyGroupID=vcg_ID WHERE trm_ParentTermID is null and vcg_ID is null'));
        if($cnt>0){

            $resStatus = false;
            $resMsg .= $div_found.$cnt.' vocabularies that are not belong any group</h3></div>';

            //find trash group
            $trash_id = mysql__select_value($mysqli, 'select vcg_ID FROM defVocabularyGroups WHERE vcg_Name="Trash"');
            if($trash_id>0){
                $mysqli->query('update defTerms left join defVocabularyGroups on trm_VocabularyGroupID=vcg_ID set trm_VocabularyGroupID='.intval($trash_id).' WHERE trm_ParentTermID is null and vcg_ID is null');

                $cnt2 = $mysqli->affected_rows;

                $resMsg .= $div_valid.$cnt2.' vocabularies have been placed to "Trash" group</h3></div>';
            }else{
                $resMsg .= '<div><h3 class="error">Cannot vocabularies "Trash" group.</h3></div>';
            }
        }else{
            $resMsg .= '<div><h3 class="res-valid">OK: All Vocabularies belong to existing groups</h3></div>';
        }

        return array('status'=>$resStatus, 'message'=>$resMsg);
    }

    /**
    * Check
    *
    * @param array $params
    */
    public function check_geo_values($params=null){

        $resStatus = true;
        $resMsg = '';

        $mysqli = $this->mysqli;


        $query = 'SELECT dtl_ID, rec_ID, rec_Title, rec_RecTypeID, dtl_Value, dtl_Geo, ST_asWKT(dtl_Geo) AS wkt, dty_Name, rty_Name
                  FROM Records
                  LEFT JOIN recDetails ON rec_ID = dtl_RecID
                  LEFT JOIN defDetailTypes ON dty_ID = dtl_DetailTypeID
                  LEFT JOIN defRecTypes ON rty_ID = rec_RecTypeID
                  WHERE dty_Type = "geo"';

        $res = $mysqli->query($query);

        // missing dtl_Geo value or values missing geoType
        $bibs1 = array();
        //$ids1 = array();
        // values that are out of bounds (out of bounds abs(lat)>90 abs(lng)>180)
        $bibs2 = array();
        //$ids2 = array();
        $ids2_lng = array();//with wrong longitudes - because wrong digitizing in continual word
        // invalid coordinates
        $bibs3 = array();
        //$ids3 = array();

        $need_correct_long = (@$params['fix']=="1");
        $geojson_adapter = null;
        $wkt_adapter = null;
        $update_stmt = null;
        $this->keep_autocommit = null;
        if($need_correct_long){
            if(method_exists('geoPHP','getAdapter')){
                $wkt_adapter = \geoPHP::getAdapter('wkt');
                $geojson_adapter = \geoPHP::getAdapter('json');
            }else{
                $wkt_adapter = new \WKT();
                $geojson_adapter = new \GeoJSON();
            }
            $update_stmt = $mysqli->prepare('UPDATE recDetails SET dtl_Geo=ST_GeomFromText(?) WHERE dtl_ID=?');
            $this->keep_autocommit = mysql__begin_transaction($mysqli);
        }
        $isOK = true;

        while ($row = $res->fetch_assoc()){

            if($row['dtl_Geo'] == null){ // Missing geo data
                array_push($bibs1, $row);
                array_push($ids1, $row['rec_ID']);
                continue;
            }

            $dtl_Value = $row['dtl_Value'];

            $geoType = super_trim(substr($dtl_Value, 0, 2));
            $hasGeoType = false;

            if($geoType=='p'||$geoType=='l'||$geoType=='pl'||$geoType=='c'||$geoType=='r'||$geoType=='m'){
                $geoValue = super_trim(substr($dtl_Value, 2));
                $hasGeoType = true;
            }else{
                $geoValue = super_trim($dtl_Value);
                if(strpos($geoValue, 'GEOMETRYCOLLECTION')!==false || strpos($geoValue, 'MULTI')!==false){
                    $geoType = "m";
                    $hasGeoType = true;
                }elseif(strpos($geoValue,'POINT')!==false){
                    $geoType = "p";
                    $hasGeoType = true;
                }elseif(strpos($geoValue,'LINESTRING')!==false){
                    $geoType = "l";
                    $hasGeoType = true;
                }elseif(strpos($geoValue,'POLYGON')!==false){ //MULTIPOLYGON
                    $geoType = "pl";
                    $hasGeoType = true;
                }
            }

            if(!$hasGeoType){ // invalid geo type
                array_push($bibs1, $row);
                array_push($ids1, $row['rec_ID']);
                continue;
            }

            try{

                $geom = \geoPHP::load($row['wkt'], 'wkt');
                if($geom!=null && !$geom->isEmpty()){ // Check that long (x) < 180 AND lat (y) < 90

                    $bbox = $geom->getBBox();
                    $within_bounds = (abs($bbox['minx'])<=180) && (abs($bbox['miny'])<=90)
                                  && (abs($bbox['maxx'])<=180) && (abs($bbox['maxy'])<=90);

                    if (!$within_bounds){

                        $is_wrong_long = abs($bbox['minx'])>180 && abs($bbox['maxx'])>180
                                            && abs($bbox['minx'])<1080 && abs($bbox['maxx'])<1080;

                        if( $is_wrong_long && $need_correct_long){

                                $json = $geojson_adapter->write($geom, true);
                                $json = geoCorrectLngJSON($json);
                                $r_value = $wkt_adapter->write($geojson_adapter->read(json_encode($json), true));
                                list($r_type, $r_value) = prepareGeoValue($mysqli, $r_value);
                                if($r_type===false){
                                    $isOK = false;
                                    $resMsg .=  error_Div('Record #'.$row['rec_ID'].'. '.$r_value);
                                    $mysqli->rollback();
                                    break;
                                }

                                $update_stmt->bind_param('si', $r_value, $row['dtl_ID']);
                                $res33 = $update_stmt->execute();
                                if(! $res33 )
                                {
                                    $isOK = false;
                                    $resMsg .=  error_Div('Record #'.$row['rec_ID'].'. Cannot replace geo in record details. SQL error: '.$mysqli->error);
                                    $mysqli->rollback();
                                    break;
                                }
                        }else{
                            array_push($bibs2, $row);
                            //array_push($ids2, $row['rec_ID']);
                            if( $is_wrong_long ){
                                array_push($ids2_lng, $row['rec_ID']);
                            }
                        }

                        continue;
                    }
                }else{ // is invalid
                    array_push($bibs3, $row);
                    //array_push($ids3, $row['rec_ID']);
                    continue;
                }
            }catch(\Exception $e){ // it is invalid, viewed as a string without numbers/numbers separated with a comma + no spaces
                array_push($bibs3, $row);
                //array_push($ids3, $row['rec_ID']);
                continue;
            }
        } //while
        if($res) {$res->close();}

        if($isOK){
            $mysqli->commit();
        }
        if($this->keep_autocommit===true) {$mysqli->autocommit(true);}

        $this->_outStreamInit();
        fwrite($this->out, $resMsg);

        // Invalid wkt values
        if(empty($bibs3)){
            fwrite($this->out, '<h3 class="res-valid">OK: No invalid geospatial values</h3><br>');
        }else{
            $resStatus = false;
            $this->printList('Records with invalid geospatial values', null, $bibs3, 'recCB10');
        }


        // Missing wkt or general invalid value
        if(empty($bibs1)){
            fwrite($this->out, '<h3 class="res-valid">OK: No missing geospatial values</h3><br>');
        }else{
            $resStatus = false;
            $this->printList('Records with missing geospatial values', null, $bibs1, 'recCB11');
        }

        // Value that is out of bounds, i.e. -90 > lat || lat > 90 || -180 > long || long > 180
        if(empty($bibs2)){
            fwrite($this->out, '<h3 class="res-valid">OK: All geospatial data is within bounds</h3>');
        }else{
            $resStatus = false;
            $fixMsg = null;
            if(!empty($ids2_lng)){
                $fixMsg = '<div style="padding:20px 0px">There are '.count($ids2_lng)
                        .' geo values with wrong longitudes. To fix longitudes (less than -180 or greater than 180 deg) click here:'
                        .' <button data-fix="geo_values">Fix longitudes</button></div>';
            }
            $this->printList('Records with geospatial data that is out of bounds', $fixMsg, $bibs2, 'recCB12');
        }

        $resMsg = $this->_outStreamRes();

        return array('status'=>$resStatus, 'message'=>$resMsg);
    }

    /**
    * Check
    *
    * @param array $params
    */
    public function check_fld_spacing($params=null){

        $resStatus = true;
        $resMsg = '';

        $mysqli = $this->mysqli;

        $is_finished = true;
        $offset = 0;

        $records_to_fix = array();
        if(@$params['fix'] == 1 && isset($params['recids'])){
            $records_to_fix = explode(',', $params['recids']);
        }

        // values that have double, leading, and/or trailing spaces
        $bibs1 = array(0 => array(), 1 => array());
        $ids1 = array();
        // values that have multiple spaces
        $bibs2 = array();
        $ids2 = array();
        $fixed2 = array();

        $total_count = mysql__select_value($mysqli, 'SELECT COUNT(dtl_ID) FROM recDetails, defDetailTypes WHERE dtl_DetailTypeID = dty_ID AND (dty_Type=\'freetext\' OR dty_Type=\'blocktext\') ');

        $this->keep_autocommit = mysql__begin_transaction($mysqli);

        while(true){

            $is_finished = true;

            $res = $mysqli->query('SELECT dtl_ID,dtl_RecID as rec_ID,dty_ID,dty_Name,dtl_Value '
            .'FROM recDetails, defDetailTypes '
            .'WHERE dtl_DetailTypeID = dty_ID AND (dty_Type=\'freetext\' OR dty_Type=\'blocktext\') '
            .' ORDER BY dtl_RecID  limit 10000 offset '.$offset);

            if($res){
                while ($row = $res->fetch_assoc()) {

                    $is_finished = false;

                    $fixed_multi = false;
                    $org_val = $row['dtl_Value'];
                    $new_val = $org_val;
                    $rec_id = intval($row['rec_ID']);

                    if(($org_val=='') || (super_trim($org_val)=='') || preg_match('/\s/', $org_val) === false){ // empty value, or no spaces
                        continue;
                    }

                    if(preg_match('/(\S)\s\s(\S)/', $new_val) > 0){ // Double spaces

                        $new_val = preg_replace('/(\S)\s\s(\S)/', '$1 $2', $new_val);
                        $bibs1[0][] = $row['dtl_ID'];
                    }
                    if(super_trim($new_val) != $new_val){ // Leading/Trailing spaces

                        $new_val = super_trim($new_val);
                        $bibs1[1][] = $row['dtl_ID'];
                    }

                    if(preg_match('/\s\s\s+/', $new_val) > 0){ // Multiple spaces (3 or more)

                        if(in_array($rec_id, $records_to_fix)){
                            $new_val = preg_replace('/\s\s\s+/', ' ', $new_val);
                            $fixed_multi = true;
                        }else{

                            $row2 = mysql__select_row_assoc($mysqli,'SELECT rec_Title, rec_RecTypeID FROM Records WHERE rec_ID='.$rec_id);
                            $row['rec_Title'] = $row2['rec_Title'];
                            $row['rec_RecTypeID'] = $row2['rec_RecTypeID'];
                            $row['dtl_Value'] = $new_val;
                            $bibs2[] = $row;

                            if(!in_array($rec_id, $ids2)){
                                $ids2[] = $rec_id;
                            }
                        }
                    }

                    if($new_val != $org_val){ // update existing value

                        $upd_query = 'UPDATE recDetails SET dtl_Value = ? WHERE dtl_ID = ' . intval($row['dtl_ID']);
                        mysql__exec_param_query($mysqli,$upd_query,array('s',$new_val));

                        if($fixed_multi && !in_array($rec_id, $fixed2)){
                            $fixed2[] = $rec_id;
                        }elseif(!in_array($rec_id, $ids1)){
                            $ids1[] = $rec_id;
                        }
                    }

                }//while
                $res->close();
            }
            if($is_finished){
                break;
            }

            $offset = $offset + 10000;

            if(@$params['progress_report_step']>=0){
                $percentage = intval($offset*100/$total_count);
                if(DbUtils::setSessionVal($params['progress_report_step'].','.$percentage)){
                    //terminated by user
                    $this->_terminatedByUser();
                    return false;
                }
            }
        }//while limit by 10000

        $mysqli->commit();
        if($this->keep_autocommit===true) {$mysqli->autocommit(true);}

        $this->_outStreamInit();

        // Value has double, leading, and/or trailing spaces; [0] => Double, [1] => Leading/Trailing
        if(empty($ids1)){
            fwrite($this->out, '<h3 class="res-valid">OK: No double, leading, or trailing spaces found in field values</h3><br>');
        }else{
            $resStatus = false;

            if(count($bibs1[0]) > 0){
                fwrite($this->out, '<h3>'. count($bibs1[0])
                    .' double spaces in text fields have been converted to single spaces.</h3><br>'
                    .'<span>Double spaces are almost always a typo and in any case they are ignored by html rendering.</span><br>');
            }
            if(count($bibs1[1]) > 0){
                fwrite($this->out, '<h3>'. count($bibs1[1])
                    .' leading or trailing spaces have been removed.</h3><br>'
                    .'<span>Leading and trailing spaces should never exist in data.</span><br>');
            }

            $url_all = $this->getAllURL($ids1);

            fwrite($this->out, '<a target=_new href="'.$url_all.'">Search for updated values '
                       .'<img alt src="'.ICON_EXTLINK.'" style="vertical-align:middle"></a>');
        }

        // Value that has multi-spaces, except double spacing
        if(empty($bibs2)){
            fwrite($this->out, '<h3 class="res-valid">OK: No multiple spaces found in field values</h3>');
        }else{
            $resStatus = false;

            $fixMsg = <<<FIX
            <div style="padding:20px 0px">
            <span>
                We recommend reducing these to single spaces.<br>
                If these spaces are intended as formatting eg. for indents, removing them could throw out some formatting.<br>
                However we strongly discourage the use of spaces in this way, as they are ignored by html rendering<br>
                and will not necessarily work consistently in case of varying fonts.
            </span>
            <button data-fix="fld_spacing" data-selected="recCB14">Fix selected records</button></div>
            FIX;

            $this->printList('Multiple consecutive spaces detected', $fixMsg, $bibs2, 'recCB14');
        }

        if(!empty($fixed2)){
            fwrite($this->out, '<br><h3>'. count($fixed2) .' multi-spaced values changed to single space</h3><br>');
        }

        $resMsg = $this->_outStreamRes();

        return array('status'=>$resStatus, 'message'=>$resMsg);
    }

    /**
    * Check
    *
    * @param array $params
    */
    public function check_multi_swf_values($params=null){

        $resStatus = true;
        $resMsg = '';

        $mysqli = $this->mysqli;

        $this->system->defineConstant('TRM_SWF_IMPORT');

        $completedRecords = array();
        if( $this->system->defineConstant('DT_WORKFLOW_STAGE') ){

            $recsWithManySWF = mysql__select_assoc($mysqli,
                'SELECT dtl_RecID, rec_RecTypeID, rec_Title '
                .' FROM recDetails INNER JOIN Records ON rec_ID = dtl_RecID '
                .' WHERE dtl_DetailTypeID = '. DT_WORKFLOW_STAGE
                .' GROUP BY dtl_RecID HAVING COUNT(dtl_RecID) > 1');

            if(!isEmptyArray($recsWithManySWF)){

                foreach($recsWithManySWF as $rec_ID => $rec){

                    $rectype_ID = intval($rec['rec_RecTypeID']);
                    $rec_Title = $rec['rec_Title'];

                    $rec_ID = intval($rec_ID);
                    $rectype_ID = intval($rectype_ID);

                    $repeatability = mysql__select_value($mysqli,
                        "SELECT rst_MaxValues FROM defRecStructure WHERE rst_RecTypeID = $rectype_ID AND rst_DetailTypeID = " . DT_WORKFLOW_STAGE);
                    $repeatability = $repeatability === null ? 1 : intval($repeatability);

                    if($repeatability > 1 || $repeatability == 0){
                        unset($recsWithManySWF[$rec_ID]);
                        continue;
                    }

                    $stages = mysql__select_assoc2($mysqli,
                        "SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_RecID = $rec_ID AND dtl_DetailTypeID="
                        .DT_WORKFLOW_STAGE." ORDER BY dtl_ID");

                    $final_stage = [];
                    $found_import = [];

                    foreach($stages as $dtl_ID => $swf_ID){

                        if(defined('TRM_SWF_IMPORT') && $swf_ID == TRM_SWF_IMPORT){
                            $found_import = empty($found_import) ? [$dtl_ID, $swf_ID] : $found_import;
                            continue;
                        }

                        $final_stage = [$dtl_ID, $swf_ID];// use the oldest
                    }

                    $final_stage = empty($final_stage) ? $found_import : $final_stage;
                    if(empty($final_stage)){
                        unset($recsWithManySWF[$rec_ID]);// $strangeRecs[] = $rec_ID;
                        continue;
                    }

                    unset( $stages[$final_stage[0]] );
                    $dtl_IDs = array_keys($stages);
                    $dtl_IDs = prepareIds($dtl_IDs);
                    if(count($dtl_IDs) > 1){
                        $mysqli->query("DELETE FROM recDetails WHERE dtl_ID IN (". implode(',', $dtl_IDs) .")");
                    }else{
                        $mysqli->query("DELETE FROM recDetails WHERE dtl_ID = {$dtl_IDs[0]}");
                    }

                    $swf_Label = mysql__select_value($mysqli, "SELECT trm_Label FROM defTerms WHERE trm_ID = {$final_stage[1]}");

                    $completedRecords[] = array('rec_ID'=>$rec_ID, 'rec_Title'=>$rec_Title, 'rec_RecTypeID'=>$rectype_ID,
                                        'dtl_Value'=>'was set to stage: '.htmlspecialchars($swf_Label));
                }
            }
        }

        if(empty($completedRecords)){
            $resMsg = '<h3 class="res-valid">OK: All records have single values for their workflow stage</h3>';
        }else{
            $resStatus = false;
            $this->_outStreamInit();
            $this->printList('Records were found to have multiple workflow stages',
                '<div style="padding:20px 0px">There workflow stage has been set to the newest available value, that is not the importing stage (unless it is the only value found).</div>',
                $completedRecords, 'recCB15');
            $resMsg = $this->_outStreamRes();
        }

        return array('status'=>$resStatus, 'message'=>$resMsg);
    }

    /**
    * Check
    *
    * @param array $params
    */
    public function check_expected_terms($params=null){

        $this->_outStreamInit();

        $resStatus = true;
        $resMsg = '';

        $mysqli = $this->mysqli;

        $fix_same_name_terms = (@$params['fix'] == 1);
        $fix_count = 0;

        $cnt = 0;
        $suggest_cnt = 0;
        $err_count = 0;
        $is_first = true;

        $offset = 0;
        $is_finished = false;

        $total_count = mysql__select_value($mysqli, 'SELECT COUNT(dtl_ID) FROM recDetails, defDetailTypes '
            .'WHERE dtl_DetailTypeID = dty_ID AND (dty_Type = "enum" or dty_Type = "relmarker")');

        $dbterms = \VerifyValue::getTerms();

        $this->keep_autocommit = mysql__begin_transaction($mysqli);

        while(true){

            $is_finished = true;

            $res = $mysqli->query('SELECT dtl_ID,rec_ID,rec_RecTypeID,rec_Title,dty_ID,dty_Name,dtl_Value,dty_JsonTermIDTree ' //',trm_Label '
                    .' FROM defDetailTypes,recDetails,Records'  // left join defTerms on dtl_Value=trm_ID
                    .' WHERE (dty_Type = "enum" or dty_Type = "relmarker") AND dty_ID = dtl_DetailTypeID AND rec_ID=dtl_RecID '
                    .' AND rec_FlagTemporary!=1 '
                .' ORDER by dtl_DetailTypeID limit 10000 offset '.$offset);//  and dtl_RecID=62734

//, rec_Title, rec_RecTypeID,

            if($res){
                while ($row = $res->fetch_assoc()) {

                    $is_finished = false;

                    //$fixed_multi = false;
                    $term_id = $row['dtl_Value'];
                    $rec_id = intval($row['rec_ID']);

                    //verify value
                    if(trim($term_id) == ''
                       ||  \VerifyValue::isValidTerm($row['dty_JsonTermIDTree'],null, $term_id, $row['dty_ID'] ))
                    {
                        continue;   //valid term
                    }


                    if($is_first){
                        $is_first = false;
                        $this->printList('Records with terms not in the list of terms specified for the field', null, null, 'recCB16');
                    }

                    //ok - term does not belong to required vocabullary
                    //check that this vocabulary already has term with the same label
                    $term_label = $dbterms->getTermLabel($term_id);
                    if($term_label){
                        $row['dtl_Value'] = $term_id.'&nbsp;'.$term_label;

                        $suggested_term_id = \VerifyValue::hasVocabGivenLabel($row['dty_JsonTermIDTree'], $term_label);
                        if($suggested_term_id>0){

                            if($fix_same_name_terms){

                                $update_query = 'UPDATE recDetails SET dtl_Value='.intval($suggested_term_id)
                                                .' WHERE dtl_ID='.intval($row['dtl_ID']);
                                $update_res = $mysqli->query($update_query);
                                if(!$update_res)
                                {
                                    $resStatus = false;
                                    $resMsg = error_Div('Cannot replace terms in record details. Query :'
                                            .$update_query.'  SQL error: '.$mysqli->error);
                                    $mysqli->rollback();
                                    fclose($this->out);
                                    $this->out = null;
                                    break 2;
                                }

                                $row['dtl_Value'] .= " <span style=\"color:green\">changed to $suggested_term_id</span>";
                                $fix_count++;

                            }else{
                                $row['dtl_Value'] .= " <span style=\"color:green\">suggestion: $suggested_term_id</span>";
                                $suggest_cnt++;
                            }
                        }
                    }else{
                        $row['dtl_Value'] = $term_id.'&nbsp; <span style="color:red">not found</span>';
                        $err_count++;
                    }

                    $this->printList(null, null, array($row), 'recCB16');//out one row

                    $cnt++;

                }//while
                $res->close();
            }
            if($is_finished){
                break;
            }

            $offset = $offset + 10000;

            if(@$params['progress_report_step']>=0){
                $percentage = intval($offset*100/$total_count);
                if(DbUtils::setSessionVal($params['progress_report_step'].','.$percentage)){
                    //terminated by user
                    $this->_terminatedByUser();
                    return false;
                }
            }
        }//while limit by 10000

        if($resStatus){
            $mysqli->commit();
        }
        if($this->keep_autocommit===true) {$mysqli->autocommit(true);}

        if($resStatus){


            if ($cnt == 0) {
                fwrite($this->out, '<h3 class="res-valid">OK: All records have valid terms (terms are as specified for each field)</h3>');
            }else{
                fwrite($this->out, TABLE_E);

                if($err_count>0){
                    $resStatus = false;
                    fwrite($this->out, '<h3 class="error">'.$err_count.' terms not found</h3>');
                }

                if($fix_count>0){
                    fwrite($this->out, '<h3>'.$fix_count.' terms referenced in incorrect vocabulary changed to terms in the vocabulary specified for the field</h3>');

                }elseif($suggest_cnt>0){
                    $resStatus = false;

                    fwrite($this->out, <<<FIXMSG
<h3>Terms referenced in incorrect vocabulary (n = $suggest_cnt)</h3>
<span style="font-size:0.9em;">Terms are referenced in a different vocabulary than that specified for the corresponding field,
<br>however the same term label exists in the vocabulary specified for the field.
<br><button data-fix="expected_terms">Click here to change these terms</button> to the ones in the vocabularies specified for each field,<br>otherwise they can be fixed for each term individually in record editing.</span><br><br>
FIXMSG
                    );

                }
            }

            $resMsg = $this->_outStreamRes();
        }


        return array('status'=>$resStatus, 'message'=>$resMsg);
    }


    /**
    * Check
    *
    * @param array $params
    */
    public function check_date_values($params=null){

        $this->_outStreamInit();

        $resStatus = true;
        $resMsg = '';

        $mysqli = $this->mysqli;

        $fix_as_suggested = (@$params['fix'] == 1);
        $records_to_fix = array();
        if(@$params['recids']!=null){
            $records_to_fix = explode(',', $params['recids']);
        }

        $fix_count = 0;

        $cnt = 0;
        $bibs_suggested = array();
        $bibs_manualfix = array();

        $is_first = true;

        $decade_regex = '/^\d{2,4}s$/';//words like 80s 1990s
        $year_range_regex = '/^\d{2,4}\-\d{2,4}$/';//2-4 year ranges

        $offset = 0;
        $is_finished = false;

        $total_count = mysql__select_value($mysqli, 'SELECT COUNT(dtl_ID) FROM recDetails, defDetailTypes '
            .'WHERE dtl_DetailTypeID = dty_ID AND dty_Type = "date"');

        $this->keep_autocommit = mysql__begin_transaction($mysqli);

        while(true){

            $is_finished = true;

            $res = $mysqli->query('SELECT dtl_ID,rec_ID,rec_RecTypeID,rec_Title,rec_Added,'
                    .' dty_ID, dty_Name, dtl_Value '
                    .' FROM defDetailTypes,recDetails,Records'
                    .' WHERE dty_Type = "date" AND dty_ID = dtl_DetailTypeID AND rec_ID=dtl_RecID '
                    .' AND rec_FlagTemporary!=1 '
                .' ORDER by dtl_DetailTypeID limit 10000 offset '.$offset);

            if($res){
                while ($row = $res->fetch_assoc()) {

                    $is_finished = false;

                    $autofix = false;
                    $date_val = trim($row['dtl_Value']);
                    $rec_id = intval($row['rec_ID']);
                    $row['is_ambig'] = true;
                    $row['new_value'] = null;

                    //verify value
                    if($date_val == '')
                    {
                        $row['new_value'] = '';
                        $autofix = true;
                    }elseif(strpos($date_val,"|")!==false || strpos($date_val,'estMinDate')!==false){
                    //check if dtl_Value is old plain string temporal object or new json object
                        continue;
                    }elseif(  (strlen($date_val)==3 || strlen($date_val)==5)
                        && preg_match( $decade_regex, $date_val )){
                    //ignore decade dates

                        //this is decades
                        $row['is_ambig'] = 'we suggest using a date range';

                    }elseif(preg_match( $year_range_regex, $date_val)){

                        list($y1, $y2) = explode('-',$date_val);
                        if($y1>31 && $y2>12){
                            //this is year range
                            $row['is_ambig'] = 'we suggest using a date range';
                        }
                    }

                    if($row['new_value']==null && $row['is_ambig']===true){

                        //parse and validate value order 2 (mm/dd), don't add day if it is not defined
                        $row['new_value'] = \Temporal::dateToISO($date_val, 2, false, $row['rec_Added']);
                        if($row['new_value']==$date_val){ //nothing to correct - result is the same

                            if(strlen($date_val)>=8 && strpos($date_val,'-')==false){ // try automatic convert to ISO format

                                try{
                                    $t2 = new \DateTime($date_val);

                                    $format = 'Y-m-d';
                                    if($t2->format('H')>0 || $t2->format('i')>0 || $t2->format('s')>0){
                                        if($t2->format('s')>0){
                                            $format .= ' H:i:s';
                                        }else{
                                            $format .= ' H:i';
                                        }
                                    }
                                    $row['new_value'] = $t2->format($format);
                                    $row['dtl_Value'] = $row['new_value'];// for final ambiguous check
                                }catch(\Exception  $e){
                                    //skip
                                }
                            }
                            continue;
                        }
                        if($row['new_value']!=null && $row['new_value']!=''){
                            $row['is_ambig'] = \Temporal::correctDMYorder($date_val, true);
                            $autofix = ($row['is_ambig']===false);
                        }
                    }

                    //correct wrong dates and remove empty values
                    if($autofix || ($fix_as_suggested && in_array($rec_id, $records_to_fix)) ){

                        if($row['new_value']!=null && $row['new_value']!=''){

                            $query = 'update recDetails set dtl_Value=? where dtl_ID='.intval($row['dtl_ID']);
                            mysql__exec_param_query($mysqli, $query, array('s',$row['new_value']), false );

                            $row['dtl_Value'] .= " <span style=\"color:green\">changed to {$row['new_value']}</span>";
                        }else{
                            $mysqli->query('delete from recDetails where dtl_ID='.intval($row['dtl_ID']));

                            $row['dtl_Value'] = '<span style="color:green">empty value removed</span>';
                        }

                        if($is_first){
                            $is_first = false;
                            $this->printList('Auto-corrected dates', 'The following dates have been corrected as shown', null, 'recCB17');
                        }
                        $this->printList(null, null, array($row), 'recCB17');//out one row
                        $fix_count++;
                    }else {

                        if($row['new_value']==null || $row['new_value']==''){
                            //manual fix
                            if($row['is_ambig']!==true){
                                $row['dtl_Value'] .= " <span style=\"color:red\">{$row['is_ambig']}</span>";
                            }
                            $bibs_manualfix[] = $row;

                        } else {
                            //suggestion
                            $row['dtl_Value'] .= " <span style=\"color:green\">suggestion to {$row['new_value']}</span>";
                            $bibs_suggested[] = $row;
                        }

                        $cnt++;

                    }

                }//while
                $res->close();
            }
            if($is_finished){
                break;
            }

            $offset = $offset + 10000;

            if(@$params['progress_report_step']>=0){
                $percentage = intval($offset*100/$total_count);
                if(DbUtils::setSessionVal($params['progress_report_step'].','.$percentage)){
                    //terminated by user
                    $this->_terminatedByUser();
                    return false;
                }
            }
        }//while limit by 10000

        $mysqli->commit();
        if($this->keep_autocommit===true) {$mysqli->autocommit(true);}

        if($cnt==0){
            fwrite($this->out, '<h3 class="res-valid">OK: All records have recognisable Date values</h3>');
        }

        if($fix_count>0){
            fwrite($this->out, TABLE_E);
        }
        if($cnt>0){
            $resStatus = false;

            if(!empty($bibs_suggested)){
                $fixMsg = '<div>To fix faulty date values as suggested, mark desired records and please click here: <button data-fix="date_values" data-selected="recCB18">Fix dates</button></div>';
                $this->printList('Suggestions for date field corrections', $fixMsg, $bibs_suggested, 'recCB18');
            }
            if(!empty($bibs_manualfix)){

                $this->printList('Invalid dates that needs to be fixed manually by a user', null, $bibs_manualfix, 'recCB19');
            }
        }

        $resMsg = $this->_outStreamRes();

/*
                if(strlen($fixdate_url) > 2000){ // roughly a upper limit for the date fix url

                <div style="color: red;margin: 10px 0;">
                    Note: You cannot correct more than a few hundred dates at a time due to URL length limitations.<br>
                    If you have more dates to correct we recommend exporting as a CSV, reformatting in a spreadsheet and reimporting as replacements.
                </div>

                <div>To fix faulty date values as suggested, mark desired records and please click here:
                    <button
                        onclick="correctDates();">
                        Correct
                    </button>

                    <div style="display: inline-block;margin-left: 20px;"> Dates are in
                        <label><input type="radio" name="date_format" value="1" checked> dd/mm/yyyy (normal format)</label>
                        <label><input type="radio" name="date_format" value="2"> mm/dd/yyyy (US format)</label>
                    </div>
                </div>
*/

        return array('status'=>$resStatus, 'message'=>$resMsg);
    }


}


?>