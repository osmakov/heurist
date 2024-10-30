<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* recordsBatch.php
*
* Class to perform actions in batch of records
*   1) add/replace and delete details
*   2) change record type
*   3) add reverse parent pointer field
*
* Controller is record_batch.php
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/
use hserv\utilities\USanitize;
use hserv\entity\DbRecUploadedFiles;

// Include Composer autoloader if not already done.
require_once dirname(__FILE__).'/../../../vendor/autoload.php';

require_once dirname(__FILE__).'/recordModify.php';
require_once dirname(__FILE__).'/recordTitleMask.php';
require_once dirname(__FILE__).'/../search/recordSearch.php';
require_once dirname(__FILE__).'/../../structure/dbsUsersGroups.php';

define('DEBUG_RUN', false);
define('ERR_REC_MODDATE','Cannot update record modification date. ');
define('ERR_REC_TITLE', 'Cannot update record title');
define('R_ARROW',' &Rightarrow; ');
define('FILE_NO','File #');

/**
* Methods for batch actions for list of records (recIDs) OR by record type rtyID
*
*
* detailsAdd - add details
* replace
* detailsReplace - replace + detailsAdd
* detailsDelete
* multiAction  - several actions in tuen: add,replace,delete
*
* addRevercePointerForChild - Adds parent pointer field converts - converts existing
*                             records to child record for given rectype/detailtype
* changeRecordTypeInBatch - Changes rec_RecTypeID in batch
*
* extractPDF - extracts PDF file content is put it into DT_EXTRACTED_TEXT field
*
*/
class RecordsBatch
{
    private $system;

    /*
    *       recIDs - list of records IDS to be processed or 'ALL'
    *       rtyID  - filter by record type
    *       dtyID  - detail field to be added,replaced or deleted
    *       for addition: val: | geo: | ulfID: - value to be added
    *       for edit sVal - search value (if missed - replace all occurences),  rVal - replace value,  subs= 1 | 0
    *       for delete: sVal, subs= 1 | 0
    *       tag  = 0|1  - add system tag to mark processed records
    *       details_encoded = 0|1 - val or rVal should be decoded
    *                   2 - restore "../" from ^^/
    */
    private $data;

    /*
    * records to be processed
    */
    private $recIDs;

    /*
    * distinct array of record types
    */
    private $rtyIDs;

    private $dt_extended_description = 0;

    /*
    passed     _tag _error
    noaccess
    processed - no rights to edit
    undefined - field definition not found (add) or search value not found (edit,delete)
    limited
    errors    - sql error on search or updata
    */
    private $result_data = array();


    private $session_id = null;

    private $not_putify = null; //fields that will not html purified
    private $purifier = null;   //html purifier instance

    public function __construct( $system, $data ) {
       $this->system = $system;
       $this->data = $data;

       $this->session_id = @$this->data['session'];
       if($this->session_id!=null){
            mysql__update_progress($system->get_mysqli(), $this->session_id, true, '0,1');
       }

       //refresh list of current user groups
       $this->system->get_user_group_ids(null, true);
    }

    //
    // Fills the list of exclusions for purifier
    // And inits HTML purifier
    //
    private function _initPutifier(){
        if($this->purifier==null){
            $not_purify = array();
            if($this->system->defineConstant('DT_CMS_EXTFILES')){ array_push($not_purify, DT_CMS_EXTFILES);}

            $this->not_purify = $not_purify;
            //$this->purifier = USanitize::getHTMLPurifier(); DISABLED
        }
    }

    //
    //
    //
    public function setData($data){
        $this->data = $data;
    }

    public function getReport(){
        return $this->result_data;
    }

    //
    //
    //
    private function _validateDetailType(){

        $rtyID = @$this->data['rtyID'];
        $dtyID = $this->data['dtyID'];//detail to be affected

        if ($rtyID && !((is_array($rtyID) || (ctype_digit($rtyID) && $rtyID>0))) ){
            $this->system->addError(HEURIST_ERROR, "Wrong parameter record type id $rtyID");
            return false;
        }

        if(!(ctype_digit($dtyID) && $dtyID>0)){
            $this->system->addError(HEURIST_ERROR, "Wrong parameter detail type id $dtyID");
            return false;
        }

        return true;
    }

    //
    //
    //
    private function _validateParamsAndCounts()
    {
        // Check that the user is allowed to edit records
        if(!userCheckPermissions($this->system, 'edit')){
            return false;
        }

        if(@$this->data['a']!='reset_thumbs' && !$this->_validateDetailType()){
            return false;
        }

        if (!( @$this->data['recIDs'])){ //record ids to be updated
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Insufficent data passed: records not defined');
            return false;
        }

        $mysqli = $this->system->get_mysqli();

        if($this->system->is_admin() && $this->data['recIDs']=='ALL'){

            $query = 'select count(*) from Records';

            $rty_ID = @$this->data['rtyID'];
            if(is_array($rty_ID)){
                if(!empty($rty_ID)){
                    $query .= ' WHERE rec_RecTypeID in ('.getCommaSepIds($rty_ID).')';
                    $this->rtyIDs = $rty_ID;
                }
            }elseif($rty_ID >0){
                $query .= ' WHERE rec_RecTypeID = '.$rty_ID;
                $this->rtyIDs = array($rty_ID);
            }

            $passedRecIDCnt = mysql__select_value($mysqli, $query);

            $this->result_data = array('passed'=>$passedRecIDCnt,
                        'noaccess'=>0,'processed'=>0);

            $this->recIDs = array('all');

        }else{

            //normalize recIDs to an array for code below
            $recIDs = prepareIds($this->data['recIDs']);

            $rtyID = intval(@$this->data['rtyID']);

            $passedRecIDCnt = count($recIDs);

            if ($passedRecIDCnt>0) {//check editable access for passed records

                if($rtyID>0){ //filter for record type
                    $recIDs = mysql__select_list($mysqli,'Records','rec_ID',"rec_RecTypeID = $rtyID and rec_ID  in ("
                                        .implode(",",$recIDs).")");
                    $recIDs = prepareIds($recIDs);//redundant for snyk
                    $passedRecIDCnt = is_array($recIDs)?count($recIDs):0;
                }
                if($passedRecIDCnt>0){
                    //exclude records if user has no right to edit
                    if($this->system->is_admin()){ //admin of database managers
                        $this->recIDs = $recIDs;
                    }else{
                        $this->recIDs = mysql__select_list($mysqli,'Records','rec_ID',"rec_ID in ("
                            .implode(",",$recIDs).") and rec_OwnerUGrpID in (0,"
                            .join(",",$this->system->get_user_group_ids()).")");
                        $this->recIDs = prepareIds($this->recIDs);//redundant for snyk
                    }

                    $inAccessibleRecCnt = $passedRecIDCnt - count(@$this->recIDs);
                }
            }

            $this->result_data = array('passed'=> $passedRecIDCnt>0?$passedRecIDCnt:0,
                                       'noaccess'=> @$inAccessibleRecCnt ?$inAccessibleRecCnt :0);

            if (isEmptyArray(@$this->recIDs)){
                $this->result_data['processed'] = 0;
                return true;
            }

            if($rtyID>0){
                $this->rtyIDs = array($rtyID);
            }else {
                $this->rtyIDs = mysql__select_list($mysqli, 'Records','distinct(rec_RecTypeID)',"rec_ID in ("
                    .implode(",",$this->recIDs).")");

                $this->rtyIDs = prepareIds($this->rtyIDs);
            }

        }


        return true;
    }

    //
    //
    //
    private function getDetailType($dty_ID){
        return mysql__select_value($this->system->get_mysqli(),
                 'select dty_Type from defDetailTypes where dty_ID = '.$dty_ID);
    }


    //
    //
    //
    private function _removeScriptTag($value){
        $value = trim($value);
        return preg_replace('#<script(.*?)>(.*?)</script>#is', '', $value);
    }


    /**
    * Converts existing records to child record for given rectype/detailtype
    * (adds rever pointer field DT_PARENT_ENTITY)
    *
    * rtyID - record type that is parent
    * dtyID - pointer detail type
    *
    */
    public function addRevercePointerForChild(){


        if (! $this->system->is_admin() ) {
            $this->system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }

        if(!$this->system->defineConstant('DT_PARENT_ENTITY')){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Field type 2-247 is not defined in this database');
            return false;
        }

        if(!$this->_validateDetailType()){
            return false;
        }

        $allow_multi_parent = ($this->data['allow_multi_parent']==true);

        $mysqli = $this->system->get_mysqli();

        //1. find resource (child) records for given record type and detail
        $query = 'SELECT dtl_RecID as parent_id, d.dtl_Value as child_id, child.rec_OwnerUGrpID, child.rec_RecTypeID, child.rec_Title '
            .'FROM  recDetails d LEFT JOIN Records child on child.rec_ID=d.dtl_Value, Records parent '
            .'WHERE d.dtl_RecID=parent.rec_ID and parent.rec_RecTypeID='
            .$this->data['rtyID'].' and d.dtl_DetailTypeID='.$this->data['dtyID'];

        $res = $mysqli->query($query);
        if ($res){

                $passedValues = 0;       //total values found
                $inAccessibleRecCnt = 0; //no rights for child records
                $cntDisambiguation = 0;  //more than one parent record

                $childNotFound = array();
                $parentRecords = array();
                $childRecords = array();

                $toProcess = array();

                $groups = $this->system->get_user_group_ids();
                array_push($groups, 0);

                while ($row = $res->fetch_row()){

                    $passedValues++;

                    if(!($row[3]>0)){  //rec_RecTypeID
                        array_push($childNotFound, $row[0]);
                    }elseif(in_array($row[2], $groups)){  //rec_OwnerUGrpID
                        if($allow_multi_parent || !@$childRecords[$row[1]]){

                            $toProcess[] = $row;  //parent_id,child_id,0,child_rectype,child_title
                            if(!in_array($row[0],$parentRecords)){
                                $parentRecords[] = $row[0];
                            }
                            $childRecords[$row[1]] = 1;

                        }else{
                            $cntDisambiguation++;
                        }
                    }else{
                        $inAccessibleRecCnt++;
                    }
                }
                $res->close();
        }else{
            $this->system->addError(HEURIST_DB_ERROR, "Can't find child records ".$mysqli->error );
            return false;
        }

        $this->result_data = array('passed'=> $passedValues,
                                   'noaccess'=> $inAccessibleRecCnt,
                                   'disambiguation'=> $cntDisambiguation);

        $keep_autocommit = mysql__begin_transaction($mysqli);

        if (!empty($toProcess)){
        //3. add reverse pointer field in child record to parent record
        $processedParents = array();
        $childInserted = array();
        $childUpdated = array();
        $childAlready = array();
        $titlesFailed = array();
        $childMiltiplied = array();

        foreach ($toProcess as $row) {
            //parent_id,child_id,0,child_rectype,child_title

            //check if child record has parent already
            $parent_id = $row[0];
            $child_id = $row[1];
            $res = addReverseChildToParentPointer($mysqli, $child_id, $parent_id, 0, $allow_multi_parent);

            if($res<0){
                $syserror = $mysqli->error;
                $mysqli->rollback();
                if($keep_autocommit===true) {$mysqli->autocommit(true);}
                return $this->system->addError(HEURIST_DB_ERROR,
                    'Unable to insert reverse pointer for child record ID:'.$child_id.' - ', $syserror);
            }elseif($res==0){
                 array_push($childAlready, $child_id);
            }else{

                if($res==2){
                    if($allow_multi_parent){
                        if(!in_array($child_id, $childMiltiplied)) {array_push($childMiltiplied, $child_id);}
                    }else{
                        array_push($childUpdated, $child_id);
                    }

                }else{
                    array_push($childInserted, $child_id);
                }
                if(!in_array($parent_id, $processedParents)){
                    array_push($processedParents, $parent_id);
                }

                //update record title for child record
                $child_rectype = $row[3];
                $child_title = $row[4];

                if(!recordUpdateTitle($this->system, $child_id, $child_rectype, $child_title)){
                    $titlesFailed[] = $child_id;
                }
            }



        } //foreach

        $this->result_data['processedParents'] = $processedParents;
        $this->result_data['childInserted'] = $childInserted;
        $this->result_data['childUpdated'] = $childUpdated;
        $this->result_data['childAlready'] = $childAlready;
        $this->result_data['childMiltiplied'] = $childMiltiplied;
        $this->result_data['titlesFailed'] = $titlesFailed;


        }
        //set rst_CreateChildIfRecPtr=1
        $query = 'UPDATE defRecStructure set rst_CreateChildIfRecPtr=1 WHERE rst_RecTypeID='
            .$this->data['rtyID'].' and rst_DetailTypeID='.$this->data['dtyID'];

        $res = $mysqli->query($query);
        if(!$res){
            $syserror = $mysqli->error;
            $mysqli->rollback();
            if($keep_autocommit===true) {$mysqli->autocommit(true);}
            return $this->system->addError(HEURIST_DB_ERROR,
                'Unable to set value in record sructure table', $syserror);
        }


        $mysqli->commit();
        if($keep_autocommit===true) {$mysqli->autocommit(true);}

        return $this->result_data;
    }

    /**
    *  parameters of  data: recIDs, dtyID, val (or geo or ulfID)
    */
    public function detailsAdd(){

        if( !@$this->data['val'] && @$this->data['rVal']){
            $this->data['val'] = $this->data['rVal'];
        }

        if (!(@$this->data['val']!=null || @$this->data['geo']!=null || @$this->data['ulfID']!=null)){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Insufficent data passed. New field value not defined");
            return false;
        }

        if(@$this->data['val']!=null){
            //attempt to pass server filters against malicious code
            if(@$this->data['details_encoded']==1 || @$this->data['details_encoded']==2){
                //$this->data['val'] = json_decode(str_replace( ' xxx_style=', ' style=',
                //        str_replace( '^^/', '../', urldecode($this->data['val']))));
                //}elseif(@$this->data['details_encoded']==2){
                $this->data['val'] = urldecode( $this->data['val'] );
            }
        }

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray($this->recIDs)){
            return $this->result_data;
        }

        $dtyID = $this->data['dtyID'];
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']."");

        $mysqli = $this->system->get_mysqli();

        //get array of max allowed values per record type
        $query = "SELECT rst_RecTypeID,rst_MaxValues FROM defRecStructure WHERE rst_DetailTypeID = $dtyID and rst_RecTypeID in ("
                        .implode(',', $this->rtyIDs).')';
        $rtyLimits = mysql__select_assoc2($mysqli, $query);

        $basetype = null;
        if(@$this->data['geo']==null){
            $basetype = $this->getDetailType($dtyID);
            if($basetype=='geo'){
                $this->data['geo'] = $this->data['val'];
            }
        }

        $now = date(DATE_8601);
        $dtl = array('dtl_DetailTypeID'  => $dtyID,
                     'dtl_Modified'  => $now);
        $rec_update = array('rec_ID'  => 'to-be-filled',
                     'rec_Modified'  => $now);

        $baseTag = "~add field $dtyName $now";//name of tag assigned to modified records

        if(@$this->data['geo']!=null){

            list($geoType, $geoValue) = prepareGeoValue($mysqli, $this->data['geo']);
            if($geoType===false){
                $this->system->addError(HEURIST_INVALID_REQUEST, $geoValue);
                return false;
            }
            $dtl['dtl_Value'] = $geoType;
            $dtl['dtl_Geo'] = $geoValue;
            //$dtl['dtl_Geo'] = array("ST_GeomFromText(\"" . $this->data['geo'] . "\")");
        }elseif($basetype=='date'){

            $useNewTemporalFormatInRecDetails = ($this->system->get_system('sys_dbSubSubVersion')>=14);

            $dtl['dtl_Value'] = Temporal::getValueForRecDetails( $this->data['val'], $useNewTemporalFormatInRecDetails );


        }elseif(@$this->data['val']!=null){ //sanitize new value

            $this->_initPutifier();
            if(!in_array($dtyID, $this->not_purify)){

                //remove html script tags
                $dtl['dtl_Value'] = $this->_removeScriptTag($this->data['val']);

                //$s = $this->purifier->purify( $this->data['val']);
                //$dtl['dtl_Value'] = htmlspecialchars_decode( $this->data['val'] );
            }else{
                $dtl['dtl_Value'] = $this->data['val'];
            }

        }

        if(@$this->data['ulfID']>0){
            $dtl['dtl_UploadedFileID'] = $this->data['ulfID'];
        }

        $undefinedFieldsRecIDs = array();//limit not defined
        $processedRecIDs = array();//success
        $limitedRecIDs = array();//over limit - skip
        $sqlErrors = array();

        foreach ($this->recIDs as $recID) {
            $recID = intval($recID);//redundant for snyk
            //check field limit for this record
            $query = "select rec_RecTypeID, tmp.cnt from Records ".
            "left join (select dtl_RecID as recID, count(dtl_ID) as cnt ".
            "from recDetails ".
            "where dtl_RecID = $recID and dtl_DetailTypeID = $dtyID group by dtl_RecID) as tmp on rec_ID = tmp.recID ".
            "where rec_ID = $recID";

            $res = $mysqli->query($query);
            if(!$res){
                array_push($undefinedFieldsRecIDs, $recID);//cannot retrieve limit
                $sqlErrors[$recID] = $mysqli->error;
                continue;
            }

            $row = $res->fetch_row();

            $rectype_ID = $row[0];

            if (!array_key_exists($rectype_ID,$rtyLimits)) { //limit not defined
                array_push($undefinedFieldsRecIDs, $recID);
                continue;
            }elseif (intval($rtyLimits[$rectype_ID])>0 && $row[1]>0 && ($rtyLimits[$rectype_ID] - $row[1]) < 1){
                array_push($limitedRecIDs, $recID);//over limit - skip
                continue;
            }

            //limit ok so insert field
            $dtl['dtl_RecID'] = $recID;
            $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);

            if (!is_numeric($ret)) {
                $sqlErrors[$recID] = $ret;
                continue;
            }
            array_push($processedRecIDs, $recID);
            //update record edit date
            $rec_update['rec_ID'] = $recID;
            $ret = mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
            if (!is_numeric($ret)) {
                $sqlErrors[$recID] = ERR_REC_MODDATE.$ret;
            }else{
                //update record title
                if(!recordUpdateTitle($this->system, $recID, $rectype_ID, null)){
                    $sqlErrors[$recID] = ERR_REC_TITLE;
                }
            }
        }

        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('undefined', $undefinedFieldsRecIDs, $baseTag);
        $this->_assignTagsAndReport('limited',  $limitedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);



        return $this->result_data;
    }

    /**
    * Executes several actions in turn
    * Queue is defined in "actions" parameter
    * Supports add,replace,delete
    */
    public function multiAction(){

        $main_data = $this->data['actions'];

        $mysqli = $this->system->get_mysqli();
        $keep_autocommit = mysql__begin_transaction($mysqli);

        foreach ($main_data as $action_data) {

            $this->setData($action_data);

            if(@$this->data['a'] == 'add'){

                $res = $this->detailsAdd();

            }elseif(@$this->data['a'] == 'replace'){ //returns

                $res = $this->detailsReplace();

            }elseif(@$this->data['a'] == 'addreplace'){

                $res = $this->detailsReplace();
                if(is_array($res) && @$res['passed']==1 && @$res['undefined']==1){
                    //detail not found - add new one
                    $res = $this->detailsAdd();
                }

            }elseif(@$this->data['a'] == 'delete'){

                $res = $this->detailsDelete(true);
            }

            if($res===false){
                break;
            }else{
                if(!@$this->result_data['processed']) {$this->result_data['processed'] = 0;}
                $this->result_data['processed'] = $this->result_data['processed']
                    +(@$res['processed']>0?$res['processed']:0);
            }
        }

        if($res===false){
            $mysqli->rollback();
            $res_data = $res;
        }else{
            $mysqli->commit();
            $res_data = $this->result_data;
        }
        if($keep_autocommit===true) {$mysqli->autocommit(true);}

        return $res_data;

    }

    /**
    * Replace detail value for given set of records and detail type and values
    * sVal - search value to replace otherwise replace all values
    * rVal - new value
    */
    public function detailsReplace()
    {
        if (@$this->data['rVal']==null && @$this->data['replace_empty'] != 1){
            $this->system->addError(HEURIST_INVALID_REQUEST, "Insufficent data passed. New value not defined");
            return false;
        }

        $useNewTemporalFormatInRecDetails = ($this->system->get_system('sys_dbSubSubVersion')>=14);


        if(@$this->data['rVal']!=null || @$this->data['encoded']==2){
            if(@$this->data['encoded']==1){
                $this->data['rVal'] = urldecode( $this->data['rVal'] );
            }
        }

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $dtyID = $this->data['dtyID'];
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']);
        $insert_new_value = false;

        $mysqli = $this->system->get_mysqli();

        $rval = $mysqli->real_escape_string($this->data['rVal']);

        $this->_initPutifier();

        //split value if exceeds 64K
        $splitValues = array();

        if(@$this->data['dt_extended_description']>0){
            $this->dt_extended_description = $this->data['dt_extended_description'];
        }elseif(!($this->dt_extended_description>0)){
            $this->system->defineConstant('DT_EXTENDED_DESCRIPTION');
            $this->dt_extended_description = DT_EXTENDED_DESCRIPTION;
        }

        if(@$this->data['needSplit'] && $dtyID==$this->dt_extended_description){
                //split replacement value
               $lim = checkMaxLength2($rval);
               //TEST $lim = 100;
               if($lim>0){
                    $dtl_Value = $this->data['rVal'];

                    $dtl_Value = $this->_removeScriptTag($dtl_Value);

                    //$dtl_Value =  $this->purifier->purify($dtl_Value);
                    //$dtl_Value = htmlspecialchars_decode( $dtl_Value );

                    $iStart = 0;
                    while($iStart<mb_strlen($dtl_Value)){

                        array_push($splitValues, mb_substr($dtl_Value, $iStart, $lim));
                        $iStart = $iStart + $lim;
                    }
               }
        }else{
            $err_msg = checkMaxLength($dtyName, $rval);
            if($err_msg!=null){
                $this->system->addError(HEURIST_INVALID_REQUEST, $err_msg);
                return false;
            }
        }

        $basetype = $this->getDetailType($dtyID);

        $partialReplace = @$this->data['subs'] == 1 || @$this->data['substr'] == 1;
        $wholeReplace = @$this->data['wholeval'] == 1;

        if(@$this->data['sVal']==null){    //value to be replaced
            //all except file type
            //$searchClause = '1=1';
            $replace_all_occurences = true;   //search value not defined replace all
            $insert_new_value = @$this->data['insert_new_values'] == 1;

            //??? why we need it if $dtyID is defined
            $types = mysql__select_list2($mysqli, 'select dty_ID from defDetailTypes where dty_Type = "file"');// OR dty_Type = "geo"
            $types = prepareIds($types);//redundant for snyk
            $searchClause = 'dtl_DetailTypeID NOT IN ('.implode(',',$types).')';

        }else{

            $searchClause = null;
            $is_like = false;

            switch ($basetype) {
                case "freetext":
                case "blocktext":
                    if($partialReplace || $wholeReplace){
                        $is_like = true;
                    }
                    $searchClause = $mysqli->real_escape_string(@$this->data['sVal']);

                    break;
                case "enum":
                case "relationtype":
                case "float":
                case "integer":
                case "resource":
                    $searchClause = $mysqli->real_escape_string(@$this->data['sVal']);
                    $partialReplace = false;
                    break;
                case "date":

                    $dtl_Value = Temporal::getValueForRecDetails( @$this->data['sVal'], $useNewTemporalFormatInRecDetails );

                    $searchClause = $mysqli->real_escape_string($dtl_Value);

                    $partialReplace = false;
                    break;
                case "relmarker":
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Relationship marker fields are not supported by value-replace service");
                    return false;
                    break;
                default:
                    $this->system->addError(HEURIST_INVALID_REQUEST, "$basetype fields are not supported by value-replace service");
                    return false;
            }

            if($searchClause!=null){
                if($is_like){
                    $searchClause = 'dtl_Value LIKE "%'.$searchClause.'%"';
                }else{
                    $searchClause = 'dtl_Value = "'.$searchClause.'"';
                }
            }


            $replace_all_occurences = false;
        }

        $undefinedFieldsRecIDs = array();//value not found
        $processedRecIDs = array();//success
        $sqlErrors = array();

        $now = date(DATE_8601);
        $dtl = array('dtl_Modified'  => $now);
        $rec_update = array('rec_ID'  => 'to-be-filled',
                     'rec_Modified'  => $now);

        $baseTag = "~replace field $dtyName $now";

        if($basetype=='geo'){
            list($geoType, $geoValue) = prepareGeoValue($mysqli, $this->data['rVal']);
            if($geoType===false){
                $this->system->addError(HEURIST_INVALID_REQUEST, $geoValue);
                return false;
            }
        }

        $is_multiline = !empty($splitValues);

        foreach ($this->recIDs as $recID) {

            $query = 'SELECT dtl_ID, dtl_RecID '
                    .($is_multiline?'':', dtl_Value')
                    .'  FROM recDetails ';

            if($recID=='all' && is_array($this->rtyIDs) && @$this->rtyIDs[0]>0){
                if(is_array($this->rtyIDs) && @$this->rtyIDs[0]>0){

                    $query = $query.', Records '
                            .'WHERE rec_ID=dtl_RecID AND  rec_RecTypeID '
            .(count($this->rtyIDs)>1?('in ('.implode(',',$this->rtyIDs).')'):('='.$this->rtyIDs[0]))
                            ." AND dtl_DetailTypeID = $dtyID and $searchClause";
                }
            }else{
                $query = $query."WHERE  dtl_DetailTypeID = $dtyID and $searchClause";
                //get matching detail value for record if there is one
                if($recID!='all'){
                    $query = $query.' AND  dtl_RecID = '.intval($recID);
                }
            }
            $query = $query.' ORDER BY dtl_RecID';

            //$valuesToBeReplaced = mysql__select_assoc2($mysqli, $query);

            $res = $mysqli->query($query);

            if($mysqli->error!=null || $mysqli->error!=''){
                $sqlErrors[$recID] = $mysqli->error;
                continue;
            //}elseif(isEmptyArray($valuesToBeReplaced)){  //not found
            //    array_push($undefinedFieldsRecIDs, $recID);
            //    continue;
            }

            $recDetailWasUpdated = false;
            $valuesToBeDeleted = array();
            $keep_recID = $recID;
            $recID = 0;
            $get_next_row = true;

            //update the details
            while (true) {

                if($get_next_row) {$row = $res->fetch_row();}
                $get_next_row = true;
                $inserting_value = $insert_new_value && $res->num_rows == 0;

                if(!$row || ($recID>0 && $row[1]!=$recID) ){

                    //next record - update changed record and
                    if($recID>0){

                        if ($recDetailWasUpdated) {
                            //only put in processed if a detail was processed,
                            // obscure case when record has multiple details we record in error array also
                            array_push($processedRecIDs, $recID);

                            //update record edit date
                            $rec_update['rec_ID'] = $recID;
                            $ret = mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
                            if (!is_numeric($ret)) {
                                $sqlErrors[$recID] = 'Cannot update modify data. '.$ret;
                            }
                        }else{
                            array_push($undefinedFieldsRecIDs, $recID);
                        }
                        if(!empty($valuesToBeDeleted) && !@$this->data['debug']){
                            //remove the rest for replace all occurences
                            $sql = 'delete from recDetails where dtl_ID in ('.implode(',',$valuesToBeDeleted).')';
                            if ($mysqli->query($sql) === false) {
                                $sqlErrors[$recID] = $mysqli->error;
                            }
                        }
                    }

                    if(!$row && ($res->num_rows > 0 || !$insert_new_value)){ //end of loop

                        if($recID==0){
                            array_push($undefinedFieldsRecIDs, $keep_recID);
                        }
                        break;
                    }

                    $recDetailWasUpdated = false;
                    $valuesToBeDeleted = array();
                }

                $dtlID = $inserting_value ? -1 : intval($row[0]);
                $recID = $inserting_value ? $keep_recID : intval($row[1]);

                if($is_multiline){ //replace with several values (long text)

                    foreach($splitValues as $val){
                        $dtl['dtl_ID'] = -1;
                        $dtl['dtl_RecID'] = $recID;
                        $dtl['dtl_DetailTypeID'] = $dtyID;
                        $dtl['dtl_Value'] = $val;
                        $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);
                    }
                    $recDetailWasUpdated = true;

                }else{
                    $dtlVal = $inserting_value ? null : $row[2];

                    if($this->data['rVal']=='replaceAbsPathinCMS'){

                        $newVal = replaceAbsPathinCMS($recID, $dtlVal);

                    }elseif (!$replace_all_occurences && $partialReplace) {// need to replace sVal with rVal
                        $newVal = preg_replace("/".preg_quote($this->data['sVal'], "/")."/i", $this->data['rVal'], $dtlVal);
                    }else{
                        $newVal = $this->data['rVal'];
                    }

                    $dtl['dtl_ID'] = $dtlID;  //detail type id

                    if(($basetype=='freetext' || $basetype=='blocktext')
                        && !in_array($dtyID, $this->not_purify))
                    {
                            //remove html script tags
                            $dtl['dtl_Value'] = $this->_removeScriptTag($newVal);

                            //$s = $this->purifier->purify( $newVal );
                            //$dtl['dtl_Value'] = htmlspecialchars_decode( $dtl['dtl_Value'] );
                    }elseif($basetype=='geo'){

                        $dtl['dtl_Value'] = $geoType;
                        $dtl['dtl_Geo'] = $geoValue;

                    }elseif($basetype=='date'){

                        $dtl['dtl_Value'] = Temporal::getValueForRecDetails( $newVal, $useNewTemporalFormatInRecDetails );

                    }else{
                        $dtl['dtl_Value'] = $newVal;
                    }

                    if(!@$this->data['debug']){

                        if($insert_new_value){
                            $dtl['dtl_RecID'] = $recID;
                            $dtl['dtl_DetailTypeID'] = $dtyID;
                        }else{
                            unset($dtl['dtl_RecID']);
                            unset($dtl['dtl_DetailTypeID']);
                        }

                        $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);

                        if (!is_numeric($ret)) {
                            $sqlErrors[$recID] = $ret;

                            if($inserting_value){
                                break;
                            }else{
                                continue;
                            }
                        }elseif($inserting_value){
                            array_push($processedRecIDs, $recID);
                        }
                    }

                    $recDetailWasUpdated = true;

                }

                if($replace_all_occurences || $is_multiline){

                    if($is_multiline && $dtlID > 0){
                        array_push($valuesToBeDeleted, intval($dtlID));
                    }

                    while ($row = $res->fetch_row()) { //gather all old detail IDs
                        if($row[1]!=$recID){
                            break;
                        }
                        array_push($valuesToBeDeleted, intval($row[0]));
                    }
                    $get_next_row = false;
                }

                if($inserting_value){ break; }

            }//while

        }//while records

        if($res) {$res->close();}

        //update record title
        foreach ($processedRecIDs as $recID){
                if(!recordUpdateTitle($this->system, $recID, null, null)){
                    $sqlErrors[$recID] = ERR_REC_TITLE;
                }
        }

        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('undefined', $undefinedFieldsRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);

        return $this->result_data;
    }

    /**
    * Remove detail value for given set of records and detail type and values
    */
    public function detailsDelete($unconditionally=false){

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $dtyID = $this->data['dtyID'];
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']);
        $isDeleteAll = (!array_key_exists("sVal",$this->data) || $this->data['sVal']=='');//without conditions
        if($isDeleteAll){
            $unconditionally = true;
        }


        $isDeleteInAllRecords = $this->recIDs[0]=='all' && !isEmptyArray($this->rtyIDs);

        $partialRemove = @$this->data['subs'] == 1 || @$this->data['substr'] == 1;
        $wholeRemove = @$this->data['wholeval'] == 1;

        $mysqli = $this->system->get_mysqli();

        if($isDeleteAll){
            $searchClause = '1=1';
        }else{

            $searchClause=null;
            $is_like=false;

            $basetype = $this->getDetailType($dtyID);
            switch ($basetype) {
                case "freetext":
                case "blocktext":
                    if($partialRemove || $wholeRemove){
                        $unconditionally = true;
                        $is_like = true;
                    }
                    $searchClause = $mysqli->real_escape_string($this->data['sVal']);

                    break;
                case "enum":
                case "relationtype":
                case "float":
                case "integer":
                case "resource":
                case "date":
                    $searchClause = $mysqli->real_escape_string($this->data['sVal']);

                    break;
                case "geo":
                    $isDeleteAll = true;
                    break;
                case "relmarker":
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Relationship marker fields are not supported by batch deletion");
                    return false;
                    break;
                default:
                    $this->system->addError(HEURIST_INVALID_REQUEST, "$basetype fields are not supported by deletion service");
                    return false;
            }

            if($searchClause!=null){
                if($is_like){
                    $searchClause = 'dtl_Value LIKE "%'.$searchClause.'%"';
                }else{
                    $searchClause = 'dtl_Value = "'.$searchClause.'"';
                }
            }else{
                $searchClause = "(1=1)";
            }
        }


        //get array of required detail types per record type
        $rtyRequired = mysql__select_list($mysqli, "defRecStructure","rst_RecTypeID",
        "rst_DetailTypeID = $dtyID and rst_RecTypeID in (".implode(",",$this->rtyIDs).") and rst_RequirementType='required'");


        $undefinedFieldsRecIDs = array();//value not found
        $processedRecIDs = array();//success
        $limitedRecIDs = array();//it is not possible to delete requried fields
        $sqlErrors = array();

        $now = date(DATE_8601);
        $dtl = array('dtl_Modified'  => $now);
        $rec_update = array('rec_ID'  => 'to-be-filled',
                     'rec_Modified'  => $now);

        if($partialRemove){
            $baseTag = "~replace field $dtyName $now";
        }else{
            $baseTag = "~delete field $dtyName $now";
        }



        //
        if($isDeleteInAllRecords)
        {

            //special case remove field for all records of specified record type
            if($isDeleteAll){  //for admin only

                $query = 'DELETE d FROM recDetails d, Records r WHERE r.rec_ID=d.dtl_RecID AND r.rec_RecTypeID '
                        .((count($this->rtyIDs)==1)
                                ?('='.$this->rtyIDs[0])
                                :('in ('.implode(',', $this->rtyIDs).')'))
                        .' AND d.dtl_DetailTypeID='.$dtyID;
                $mysqli->query($query);

                if($mysqli->error!=null || $mysqli->error!=''){
                    $this->result_data['processed'] = 0;
                    $this->result_data['error'] = $mysqli->error;
                    return $this->result_data;
                }else{
                    $this->result_data['processed'] = $mysqli->affected_rows;
                    return $this->result_data;
                }
            }

            //find all records of particular record type
            $query = 'SELECT rec_ID FROM Records WHERE rec_RecTypeID '
                    .((count($this->rtyIDs)==1)
                            ?('='.$this->rtyIDs[0])
                            :('in ('.implode(',', $this->rtyIDs).')'));

            $this->recIDs = mysql__select_list2($mysqli, $query);
            if($mysqli->error!=null || $mysqli->error!=''){
                $this->result_data['processed'] = 0;
                $this->result_data['error'] = $mysqli->error;
                return $this->result_data;
            }
        }

        foreach ($this->recIDs as $recID) {

            $recID = intval($recID);

            //get matching detail value for record if there is one
            $query = "SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_RecID = $recID and dtl_DetailTypeID = $dtyID and $searchClause";
            $valuesToBeDeleted = mysql__select_assoc2($mysqli, $query);

//$valuesToBeDeleted = mysql__select_list($mysqli, "recDetails", "dtl_ID", "dtl_RecID = $recID and dtl_DetailTypeID = $dtyID and $searchClause");

            if($valuesToBeDeleted==null && $mysqli->error){
                $sqlErrors[$recID] = $mysqli->error;
                continue;
            }elseif(isEmptyArray($valuesToBeDeleted)){  //not found
                array_push($undefinedFieldsRecIDs, $recID);
                continue;
            }


            if(!$unconditionally){
                //validate if details can be deleted for required fields
                if(count($this->rtyIDs)>1){
                    //get rectype for current record
                    $rectype_ID = mysql__select_value($mysqli, 'select rec_RecTypeID from Records where rec_ID='.$recID);
                }else{
                    $rectype_ID = $this->rtyIDs[0];
                }
                if(array_search($rectype_ID, $rtyRequired)!==false){ //this is required field
                    if(!$isDeleteAll){
                        //find total count
                        $total_cnt = mysql__select_value($mysqli, "SELECT count(*) FROM recDetails ".
                            " WHERE dtl_RecID = $recID AND dtl_DetailTypeID = $dtyID");

                    }
                    if($isDeleteAll || ($total_cnt == count($valuesToBeDeleted))){
                        array_push($limitedRecIDs, $recID);
                        continue;
                    }
                }
            }

            if($partialRemove){
                //this is not real delete - this is replacement of value part with empty string
                $now = date(DATE_8601);
                $dtl = ['dtl_Modified' => $now];

                $sRegEx = "/".preg_quote($this->data['sVal'], "/")."/";

                foreach ($valuesToBeDeleted as $dtlID => $dtlVal) {

                    $newVal = preg_replace($sRegEx,'',$dtlVal);

                    if(trim($newVal)==''){
                        $sql = 'delete from recDetails where dtl_ID = '.$dtlID;
                        if ($mysqli->query($sql) === true) {
                            $sqlErrors[$recID] = $mysqli->error;
                        }

                    }else{
                        $dtl['dtl_ID'] = $dtlID;
                        $dtl['dtl_Value'] = $newVal;

                        $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);

                        if (!is_numeric($ret)) {
                            $sqlErrors[$recID] = $ret;
                        }
                    }
                }//for

                $sql = true;

            }elseif($wholeRemove){

                $sRegEx = "/".preg_quote($this->data['sVal'], "/")."/";

                foreach ($valuesToBeDeleted as $dtl_ID => $dtl_Value) {

                    if(preg_match($sRegEx, $dtl_Value)){
                        $sql = "DELETE FROM recDetails WHERE dtl_ID = $dtl_ID";
                        if($mysqli->query($sql) !== true){
                            $sqlErrors[$recID] = $mysqli->error;
                        }
                    }
                }

                $sql = true;

            }else{
                //delete the details
                $sql = 'delete from recDetails where dtl_ID in ('.implode(',',array_keys($valuesToBeDeleted)).')';
            }

            if ($sql===true || $mysqli->query($sql) === true) {
               array_push($processedRecIDs, $recID);
               //update record edit date
               $rec_update['rec_ID'] = $recID;
               $ret = mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
               if (!is_numeric($ret)) {
                    $sqlErrors[$recID] = ERR_REC_MODDATE.$ret;
               }else{
                    if(!recordUpdateTitle($this->system, $recID, null, null)){
                        $sqlErrors[$recID] = ERR_REC_TITLE;
                    }
               }

            } else {
               $sqlErrors[$recID] = $mysqli->error;
            }
        }//for records


        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('undefined', $undefinedFieldsRecIDs, $baseTag);
        $this->_assignTagsAndReport('limited',  $limitedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);

        return $this->result_data;
    }


    /**
    * assign child detail type (2-272) that refers to parent record
    * or change given detail type to 2-272
    */
    public function setRecordAsChild(){
        // parameters
        // 1. required. detail type (pointer) from parent record - to detect what record types will be affected
        // 2. optional. detail type in child record that already has backward reference to parent record -
        //             if hot defined ALL records of given record type will be affected
        //             this detail will be replaced to


    }


    /**
    * Changes rec_RecTypeID in batch
    *
    * rtyID_new - new record type
    */
    public function changeRecordTypeInBatch(){

        $this->data['dtyID'] = '1';//dumb value to pass validation

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $rtyID_new = $this->data['rtyID_new'];
        $rtyName = (@$this->data['rtyName'] ? "'".$this->data['rtyName']."'" : "id:".$this->data['rtyID_new']);

        $mysqli = $this->system->get_mysqli();

        $processedRecIDs = array();//success
        $sqlErrors = array();

        $now = date(DATE_8601);
        $dtl = array('dtl_Modified'  => $now);
        $rec_update = array('rec_ID'  => 'to-be-filled',
                            'rec_Modified'  => $now,
                            'rec_RecTypeID'  => $rtyID_new);

        $baseTag = "~changed rectype $rtyName $now";

        foreach ($this->recIDs as $recID) {
               //update record edit date
               $rec_update['rec_ID'] = $recID;

               $ret = mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
               if (!is_numeric($ret)) {
                    $sqlErrors[$recID] = ERR_REC_MODDATE.$ret;
               }else{
                   array_push($processedRecIDs, $recID);
                   //update title
                   $new_title = TitleMask::fill($recID);
                   $rec_update2 = array('rec_ID'  => $recID, 'rec_Title'  => $new_title);
                   mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update2);
               }
        }//for recors



        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',    $sqlErrors, $baseTag);

        return $this->result_data;
    }


    /**
    * Extracts text content from PDF file that is defined in dtyID field
    * and put this content to DT_EXTRACTED_TEXT field
    *
    * for recIDs
    */
    public function extractPDF(){

        //default value to pass validation
        if(!@$this->data['dtyID']){
            if(!defined('DT_EXTRACTED_TEXT')){
                $this->system->addError(HEURIST_NOT_FOUND, 'Field "Extracted text" (2-652) not found');
                return false;
            }
            $this->data['dtyID'] = DT_EXTRACTED_TEXT;
        }

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $mysqli = $this->system->get_mysqli();

        $tot_count = count($this->recIDs);

        $execution_counter = 0;

        $processedRecIDs = array();//success
        $sqlErrors = array();
        $skippedRecIDs = array();//values already defined

        $skippedNoPDF   = array();//no assosiated records
        $skippedEmpty   = array();//empty
        $skippedParseEx = array();//parse exception



        $now = date(DATE_8601);
        $dtl = array('dtl_DetailTypeID'  => $this->data['dtyID'],
                     'dtl_Modified'  => $now);
        $rec_update = array('rec_ID'  => 'to-be-filled',
                     'rec_Modified'  => $now);

        $baseTag = "~extract pdf $now";

        $parser = new \Smalot\PdfParser\Parser();

        foreach ($this->recIDs as $recID) {

            $sql = 'select count(dtl_ID) from recDetails where dtl_RecID='.$recID.' AND dtl_DetailTypeID = '.$this->data['dtyID'];
            $isExistsAlready = mysql__select_value($mysqli, $sql)>0;

            if($isExistsAlready){
               $skippedRecIDs[] = $recID;
               continue;
            }

            $details = array();
            $hasPDFs = false;

            $record = recordSearchByID($this->system, $recID, array('file'));
            foreach ($record['details'] as $dtl_ID => $detailValue){
    // 2. find assosiated pdf files
                if(is_array($detailValue)){
                    foreach ($detailValue as $id => $fileValue){
                    if($fileValue['file']['fxm_MimeType']=='application/pdf'){

                        $hasPDFs = true;

                        $file = $fileValue['file']['fullPath'];
                        $file = resolveFilePath($file);
                        if(file_exists($file)){
        // 3. Parse pdf file
                            try{

                                if(!DEBUG_RUN){
                                    $pdf    = $parser->parseFile($file);

                                        // Retrieve all pages from the pdf file.
                                        $pages  = $pdf->getPages();
                                        $page_cnt = 0;
                                        $text = '';
                                        // Loop over each page to extract text.
                                        foreach ($pages as $page) {

                                            $pagetext = $page->getText();

                                            if(mb_detect_encoding($pagetext, 'UTF-8', true)===false){

                                                $pagetext = iconv("UTF-8","UTF-8//IGNORE", $pagetext);// to remove

                                                //$pagetext = Encoding::fixUTF8($pagetext);
                                                if(mb_detect_encoding($pagetext, 'UTF-8', true)===false){
                                                    $pagetext = 'Page '.$page_cnt.' cannot be converted to UTF-8';
                                                }
                                            }

                                            $text = $text . $pagetext;
                                            if(strlen($text)>60000 || $page_cnt>10){
                                                break;
                                            }
                                            $page_cnt++;


                                        }//foreach

                                }else{
                                    //debug without real parsing
                                    sleep(1);
                                    $text = 'test';
                                    $skippedParseEx[$recID] = $file.' Debug parse exception';
                                }

                                if($text==null || mb_strlen(trim($text))==0){
                                    $skippedEmpty[$recID] = $file;
                                }else{
                                    $orig_len = mb_strlen($text);
                                    $maxlen = 20000;
                                    if($orig_len>$maxlen){ //split by 20k

                                            $k=0;
                                            while (strlen($text)>$maxlen && $k<3){
                                                $details[] = mb_substr($text,0,$maxlen);
                                                $text = mb_substr($text,$maxlen);
                                                $k++;
                                            }
                                            if($k>2){
                                                $len = count($details)-1;
                                                $details[$len] =
                                                    $details[$len]
                                                    .' <more text is available. Remaining text has not been extracted from file>';
                                            }
                                   }else{
                                        $details[] = $text;
                                   }
                                }

                            } catch (\Exception $ex) {
                                //throw new ParseException($ex);
                                $skippedParseEx[$recID] = $file.' '.print_r($ex, true);
                            }
                        }else{
                            $skippedNoPDF[$recID] = 'PDF file not found';
                        }
                    }
                }
                }
            }//details

            if(!$hasPDFs){
                $skippedNoPDF[] = $recID;
            }elseif(!empty($details)){

                /*
                // 4. remove old 2-652 "Extracted text"
                $sql = 'delete from recDetails where dtl_RecID='.$recID.' AND dtl_ID = '.$this->data['dtyID'];
                if ($mysqli->query($sql) !== true) {
                    $sqlErrors[$recID] = 'Cannot remove dt#'.$this->data['dtyID'].' for record # '.$recID.'  '.$mysqli->error;
                }else{}
                */
    // 5. Add new values to 2-652 - one entry per file
                if(!DEBUG_RUN){
                    $dtl['dtl_RecID'] = $recID;
                    foreach($details as $text){
                        $dtl['dtl_Value'] = $text;
                        if(mb_detect_encoding($dtl['dtl_Value'], 'UTF-8', true)===false){
                            $sqlErrors[$recID] = 'Extracted text has not valid utf8 encoding';
                            break;
                            /*
                            $query = 'INSERT INTO recDetails (dtl_RecID,dtl_DetailTypeID,dtl_Value) VALUES ('
                            .$dtl['dtl_RecID'].', '.$dtl['dtl_DetailTypeID'].', '
                            .'CONVERT( CAST(? AS BINARY) USING utf8mb4))';

                            $ret = mysql__exec_param_query($mysqli, $query, array($dtl['dtl_Value']));
                            */
                        }else{
                            $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl);
                            if (!is_numeric($ret)) {
                                    $sqlErrors[$recID] = $ret;
                                    break;
                            }
                        }
                    }//foreach
                    if(@$sqlErrors[$recID]) {continue;}

                    //update record edit date
                    $rec_update['rec_ID'] = $recID;
                    $ret = mysql__insertupdate($mysqli, 'Records', 'rec', $rec_update);
                    if (!is_numeric($ret)) {
                        $sqlErrors[$recID] = 'Cannot update record "Modify date". '.$ret;
                    }
                }
                $processedRecIDs[] = $recID;
            }


            if($this->session_id!=null){
                //check for termination and set new value
                $execution_counter++;
                $session_val = $execution_counter.','.$tot_count;
                $current_val = mysql__update_progress($mysqli, $this->session_id, false, $session_val);
                if($current_val=='terminate'){ //session was terminated from client side
                    break;
                }
            }

        }//for records

        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('undefined', $skippedNoPDF, null); //no pdf assigned
        $this->_assignTagsAndReport('limited',   $skippedRecIDs, null); //value already defined
        $this->_assignTagsAndReport('parseexception', $skippedParseEx, null);
        $this->_assignTagsAndReport('parseempty', $skippedEmpty, null);
        $this->_assignTagsAndReport('errors',  $sqlErrors, null);

        return $this->result_data;
    }

    /**
    * Converts remote URLs in the specified File field, places them in the database,
    * and replaces the remote URL with a reference to file in the database
    *
    * for recIDs
    */
    public function changeUrlToFileInBatch(){

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $mysqli = $this->system->get_mysqli();

        $date_mode = date(DATE_8601);

        $tot_count = count($this->recIDs);

        $dtyID = $this->data['dtyID'];
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']);
        $baseTag = "~replace url to file $dtyName $date_mode";

        $processedRecIDs = array();
        $sqlErrors = array();
        $downloadError = array();

        //1. find external urls for field values
        //2. ulf_ExternalFileReference - extract filename and decode it
        //3. match_only!=1 Download of the remote file and check if the file already exists with the same name and checksum in the database and will not create a duplicate.
        //4. match_only==1 Check the file name only (avoids having to download the remote file if the name exists)
        //5. If download - register new file
        //6. Replace ulf_ID in dtl_UploadedFileID

        $file_entity = new DbRecUploadedFiles($this->system);

        //1. find external urls for field values
        $query = 'SELECT dtl_ID, ulf_ID, ulf_ExternalFileReference, dtl_RecID FROM recUploadedFiles, recDetails '
        .'WHERE ulf_ID=dtl_UploadedFileID AND ulf_OrigFileName="_remote" AND dtl_DetailTypeID='.$dtyID
        .SQL_AND.predicateId('dtl_RecID', $this->recIDs);

        if($this->data['url_substring']){
            $query = $query.' AND ulf_ExternalFileReference LIKE "%'.$mysqli->real_escape_string($this->data['url_substring']).'%"';
        }

        $query = $query.' ORDER BY ulf_ID';

        $res = $mysqli->query($query);
        if ($res){
            $ulf_ID = null;
            $dtl_IDs = array();
            $rec_IDs = array();
            $ulf_ID_new = null;

            while ($row = $res->fetch_row()){
                if($ulf_ID!=$row[1]){

                    if($ulf_ID_new>0){
                        if($this->_updateUploadedFileIDs($ulf_ID_new, $dtl_IDs, $date_mode)){
                            $processedRecIDs = array_merge($processedRecIDs, $rec_IDs);
                        }else{
                            $sqlErrors = array_merge($sqlErrors, $rec_IDs);
                        }
                    }

                    $ulf_ID = $row[1];
                    $dtl_IDs = array();
                    $rec_IDs = array();
                    $ulf_ID_new = null;

                    //find local ulf_ID

                    //2. ulf_ExternalFileReference
                    $surl = $row[2];

                    //5. If download - register new file
                    $file_entity->setRecords(null);
                    //$ulf_ID_new = false;
                    $ulf_ID_new = $file_entity->downloadAndRegisterdURL($surl, null, (@$this->data['match_only']==1)?1:2);//it returns ulf_ID
                    if(!$ulf_ID_new){
                        //can't download
                        $downloadError[] = $row[3];//rec_ID
                    }

                }

                $dtl_IDs[] = intval($row[0]);
                $rec_IDs[] = intval($row[3]);


            }//while

            if($ulf_ID_new>0){
                if($this->_updateUploadedFileIDs($ulf_ID_new, $dtl_IDs, $date_mode)){
                    $processedRecIDs = array_merge($processedRecIDs, $rec_IDs);
                }else{
                    $sqlErrors = array_merge($sqlErrors, $rec_IDs);
                }
            }
        }

        //$this->result_data['processed'] = $tot_count;

        //assign special system tags
        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',  $sqlErrors, $baseTag);
        $this->result_data['fails'] = count($downloadError);
        $this->result_data['fails_list'] = $downloadError;

        return $this->result_data;
    }

    //
    //
    //
    private function _updateUploadedFileIDs($ulf_ID_new, $dtl_IDs, $date_mode){

        if($ulf_ID_new>0 && !empty($dtl_IDs)){
            $mysqli = $this->system->get_mysqli();
            //6. Replace ulf_ID in dtl_UploadedFileID
            $query2 = 'UPDATE recDetails SET dtl_Modified="'.$date_mode
                .'", dtl_UploadedFileID='.intval($ulf_ID_new).' WHERE dtl_ID in ('.implode(',',$dtl_IDs).')';
            $res2 = $mysqli->query($query2);

            if(!$res2){
                //$this->system->addError(HEURIST_DB_ERROR,'Cannot assign IDs for registered files', $mysqli->error );
                return false;
            }
            //$tag_count = $mysqli->affected_rows;
        }
        return true;

    }


    /**
    * Deletes thumbnail images for files assosiated with selected records
    *
    */
    public function resetThumbnails(){

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $mysqli = $this->system->get_mysqli();

        //1. find external urls for field values
        $query = 'SELECT ulf_ObfuscatedFileID FROM recUploadedFiles, recDetails '
        .'WHERE ulf_ID=dtl_UploadedFileID '
        .SQL_AND.predicateId('dtl_RecID', $this->recIDs);

        $cnt = 0;
        $res = $mysqli->query($query);
        if ($res){

            while ($row = $res->fetch_row()){
                $obfuscation_id = preg_replace('/[^a-z0-9]/', "", $row[0]);//for snyk
                $thumbnail_file = HEURIST_THUMB_DIR.'ulf_'.$obfuscation_id.'.png';//'ulf_ObfuscatedFileID'
                if(file_exists($thumbnail_file)){
                    unlink($thumbnail_file);
                    $cnt++;
                }
            }
        }

        $this->result_data['processed'] = $cnt;
        return $this->result_data;
    }

    //
    // remove long-time opeartion from session table
    //
    public function removeSession(){
        if($this->session_id!=null){
            mysql__update_progress($this->system->get_mysqli(), $this->session_id, false, 'REMOVE');
        }
    }

/*
public methods

    detailsAdd
    detailsReplace
    detailsDelete
    changeRecordTypeInBatch
    addRevercePointerForChild
    setRecordAsChild
    extractPDF
    -------------
*/

// all tags routine must be in dbUsrTags
    /*
    * helper method
    * assign special system tags
    *       passed - count of given rec ids
    *       noaccess - no rights to edit
    *       processed - success
                _tag   _tag_error
    *       undefined - value not found
    *       errors     - sql error on search or updata
                errors_list
    */
    private function _assignTagsAndReport($type, $recordIds, $baseTag)
    {
        if (!isEmptyArray($recordIds)) {

            if($type=='errors' || $type=='parseexception' || $type=='parseempty' || $type=='fails'){
                $this->result_data[$type.'_list'] = $recordIds;
                $recordIds = array_keys($recordIds);
            }

            $this->result_data[$type] = count($recordIds);

            $needBookmark = (@$this->data['tag']==1);

            if($baseTag!=null && $needBookmark){

                if($type!='processed'){
                    $baseTag = $baseTag.' '.$type;
                }

                $success = $this->_tagsAssign($recordIds, null, $baseTag);
                if($success){
                    $this->result_data[$type.'_tag'] = $baseTag;
                }else{
                    //error on tag assign
                    $this->result_data[$type.'_tag_error'] = $this->system->getError();
                }
            }
        }
    }

    /**
    * Assign tags to records and bookmark records
    *
    * @param mixed $system
    * @param mixed $record_ids - array of record ids
    * @param mixed $tag_ids - array of tag ids
    * @param mixed $tag_names - if tag ids are not defined, we use $tag_names to get ids
    * @param mixed $ugrID
    *
    * returns false if error
    *     or array ('tags_added'=>$tag_count, 'bookmarks_added'=>$bookmarks_added)
    */
    private function _tagsAssign($record_ids, $tag_ids, $tag_names=null, $ugrID=null){

        $system = $this->system;

        if($ugrID<1) {$ugrID = $system->get_user_id();}

        if (!$system->has_access($ugrID)) {
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }else{
            //find tag_ids by tag name
            if($tag_ids==null){
                if($tag_names==null){
                    $system->addError(HEURIST_INVALID_REQUEST, 'Tag name is not defined');
                    return false;
                }else{

                    $tag_ids = $this->_tagGetByName(array_filter(explode(',', $tag_names)), true, $ugrID);
                }
            }
            if( isEmptyArray($record_ids) ){
                $system->addError(HEURIST_INVALID_REQUEST, 'Record ids are not defined');
                return false;
            }

            if( isEmptyArray($tag_ids) ){
                $system->addError(HEURIST_INVALID_REQUEST, 'Tags ids either not found or not defined');
                return false;
            }

            $mysqli = $system->get_mysqli();

            $record_ids = prepareIds($record_ids);//for snyk
            $tag_ids    = prepareIds($tag_ids);//for snyk

            //assign links
            $insert_query = 'insert ignore into usrRecTagLinks (rtl_RecID, rtl_TagID) '
                . 'select rec_ID, tag_ID from usrTags, Records '
                . ' where rec_ID in (' . implode(',', $record_ids) . ') '
                . ' and tag_ID in (' . implode(',', $tag_ids) . ')'
                . ' and tag_UGrpID = '.$ugrID;
            $res = $mysqli->query($insert_query);
            if(!$res){
                $system->addError(HEURIST_DB_ERROR,"Cannot assign tags", $mysqli->error );
                return false;
            }
            $tag_count = $mysqli->affected_rows;

            /*$new_rec_ids = mysql__select_column($mysqli,
            'select rec_ID from Records '
            .' left join usrBookmarks on bkm_recID=rec_ID and bkm_UGrpID='.$ugrID
            .' where bkm_ID is null and rec_ID in (' . join(',', $record_ids) . ')');*/

            //if $ugrID is not a group - create bookmarks
            $bookmarks_added = 0;
            if ($ugrID==$system->get_user_id() ||
                mysql__select_value($mysqli, 'select ugr_Type from sysUGrps where ugr_ID ='.$ugrID)=='user')
            { //not bookmarked yet
                $query = 'insert into usrBookmarks '
                .' (bkm_UGrpID, bkm_Added, bkm_Modified, bkm_recID)'
                .' select ' . $ugrID . ', now(), now(), rec_ID from Records '
                .' left join usrBookmarks on bkm_recID=rec_ID and bkm_UGrpID='.$ugrID
                .' where bkm_ID is null and rec_ID in (' . implode(',', $record_ids) . ')';

                //$stmt = $mysqli->query($query);

                $res = $mysqli->prepare($query);

                if(!$res){
                    $system->addError(HEURIST_DB_ERROR,"Cannot add bookmarks", $mysqli->error);
                    return false;
                }
                $bookmarks_added = $mysqli->affected_rows;
            }

            return array('tags_added'=>$tag_count, 'bookmarks_added'=>$bookmarks_added);
        }
    }

    /**
    * Get tag IDs by tag names
    *
    * @param mixed $tag_names
    * @param mixed $ugrID
    */
    private function _tagGetByName($tag_names, $isadd, $ugrID=null){

        $system = $this->system;

        if (!$ugrID) {
            $ugrID = $system->get_user_id();
        }
        if(!$ugrID) {return null;}

        if(is_string($tag_names)){
            $tag_names = explode(",", $tag_names);
        }

        $tag_ids = array();
        foreach ($tag_names as $tag_name) {
            $tag_name = preg_replace('/\\s+/', ' ', trim($tag_name));
            if(strlen($tag_name)>0){

                $res = mysql__select_value($system->get_mysqli(), 'select tag_ID from usrTags where lower(tag_Text)=lower("'.
                    $system->get_mysqli()->real_escape_string($tag_name).'") and tag_UGrpID='.$ugrID);
                if($res){
                    array_push($tag_ids, $res);
                }elseif($isadd){
                    $res = $this->_tagSave( array('tag_UGrpID'=>$ugrID, 'tag_Text'=>$tag_name));
                    if($res){
                        array_push($tag_ids, $res);
                    }
                }
            }
        }
        $tag_ids = array_unique($tag_ids, SORT_NUMERIC);

        return $tag_ids;
    }

    /**
    * insert/update tag
    *
    * @param mixed $system
    * @param mixed $tag  - array [ ID, UGrpID, Text, Description, AddedByImport ]
    *
    * return false or new tag_ID
    */
    private function _tagSave($tag){

        $system = $this->system;

        if(!@$tag['tag_Text']){
            $system->addError(HEURIST_INVALID_REQUEST, "Text not defined");
            return false;
        }

        if (!$system->has_access(@$tag['tag_UGrpID'])) {
            $system->addError(HEURIST_REQUEST_DENIED);
            return false;
        }else{

            if(intval(@$tag['tag_ID'])<1){
                $samename = $this->_tagGetByName($tag['tag_Text'], false, $tag['tag_UGrpID']);

                if(!isEmptyArray($samename)){
                    $tag['tag_ID'] = $samename[0];
                }
            }

            $res = mysql__insertupdate($system->get_mysqli(), "usrTags", "tag", $tag);
            if(is_numeric($res) && $res>0){
                return $res; //returns affected record id
            }else{
                $system->addError(HEURIST_DB_ERROR, 'Cannot update record in database', $res);
                return false;
            }

        }
    }

    /**
     * Create sub records for the given record type
     *  Selected record fields are transferred to the newly created records of the selected 'sub-record' type
     *  This new 'sub-record' are set as a child record to the original record, the source of the field values
     *
     * @return false|array - false on error | array with keys count (number of new records) and record_ids (comma list of new record ids)
     */
    public function createSubRecords(){

        // Can only be used by an administrator
        if(!$this->system->is_admin()){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Only database administrators can create sub records');
            return false;
        }

        $system = $this->system;
        $mysqli = $system->get_mysqli();

        $system->defineConstant('DT_PARENT_ENTITY');
        if(!defined('DT_PARENT_ENTITY')){
            $system->addError(HEURIST_ERROR, 'An error occurred while attempting to define system constants');
            return false;
        }

        $data = $this->data;

        // Retrieve and validate values

        /** List of values:
         * src_rty => Source record type
         * src_dtys => Source base fields
         * trg_rty => Target record type
         * trg_dty => Target record pointer field
         * split_values => Create a record per repeated value for each record
         */

        if(empty(@$data['src_rty']) || empty(@$data['trg_rty']) || empty(@$data['src_dtys']) || empty(@$data['trg_dty'])){
            $system->addError(HEURIST_INVALID_REQUEST, 'Parameters missing');
            return false;
        }

        $source_rty = intval($data['src_rty']);
        $target_rty = intval($data['trg_rty']);
        $split_values = empty(@$data['split_value']) ? 0 : $data['split_value'];
        $source_ids = prepareIds($data['src_dtys']);
        $target_field = intval($data['trg_dty']);

        if($source_rty <= 0){
            $system->addError(HEURIST_INVALID_REQUEST, 'Invalid source record type provided');
            return false;
        }
        if($target_rty <= 0){
            $system->addError(HEURIST_INVALID_REQUEST, 'Invalid target record type provided');
            return false;
        }
        if(empty($source_ids)){
            $system->addError(HEURIST_INVALID_REQUEST, 'Invalid source fields prepared');
            return false;
        }
        if($target_field <= 0){
            $system->addError(HEURIST_INVALID_REQUEST, 'Invalid target field provided');
            return false;
        }

        // Ensure target field exists in source structure and is a record pointer
        $query = "SELECT rst_ID FROM defRecStructure INNER JOIN defDetailTypes ON dty_ID = rst_DetailTypeID WHERE rst_RecTypeID = $source_rty AND rst_DetailTypeID = $target_field AND dty_Type = 'resource'";
        $target_in_struct = mysql__select_value($mysqli, $query);
        if($target_in_struct <= 0){
            $system->addError(HEURIST_ACTION_BLOCKED, 'Invalid target field');
            return false;
        }

        // Retieve existing records of source type
        $record_ids = mysql__select_list2($mysqli, "SELECT rec_ID FROM Records WHERE rec_FlagTemporary != 1 AND rec_RecTypeID = $source_rty", 'intval');

        if(empty($record_ids)){
            $system->addError(HEURIST_ACTION_BLOCKED, 'No source records found');
            return false;
        }

        $rec_count = count($record_ids);// this is to avoid multiple swf emails when creating records

        $new_records = array();// final array of newly created records

        $keep_autocommit = mysql__begin_transaction($mysqli);

        foreach($record_ids as $rec_id){

            $rec_id = intval($rec_id);//snyk does not see intval in mysql__select_list2

            // 1. Get values -----
            $details_to_transfer = array();
            $has_values = false;

            foreach($source_ids as $dty_id){

                $details = mysql__select_list2($mysqli, "SELECT dtl_ID FROM recDetails WHERE dtl_RecID = $rec_id AND dtl_DetailTypeID = $dty_id");

                if(empty($details)){ // skip
                    continue;
                }

                $idx = 0;

                if($split_values == 0){
                    array_push($details_to_transfer, ...$details);
                }else{
                    foreach($details as $dtl_ID){

                        if(!array_key_exists($idx, $details_to_transfer)){
                            $details_to_transfer[$idx] = array();
                        }
                        array_push($details_to_transfer[$idx], ...$dtl_ID);

                        ++ $idx;
                    }
                }

                $has_values = true;
            }

            if(!$has_values){
                continue;
            }

            // 2. Create new sub-records -----
            // Include references to the parent record
            $record = array(
                'ID' => 0,
                'no_validation' => 'ignore_all',
                'rec_RecTypeID' => $target_rty,
                'details' => array(
                    DT_PARENT_ENTITY => array($rec_id)
                )
            );

            $new_rec_ids = array();
            if($split_values == 0){

                //$record['details'][DT_PARENT_ENTITY] = array($rec_id);

                $result = recordSave($this->system, $record, false, false, 0, $rec_count);// $rec_count to avoid sending multiple swf emails
                if($result['status'] != HEURIST_OK){

                    $mysqli->rollback();
                    if($keep_autocommit===true) {$mysqli->autocommit(true);}

                    return false;
                }

                $new_rec_ids[] = $result['data'];
            }else{

                foreach($details_to_transfer as $details){

                    //$record['details'][DT_PARENT_ENTITY] = array($rec_id);

                    $result = recordSave($this->system, $record, false, false, 0, $rec_count);// $rec_count to avoid sending multiple swf emails
                    if($result['status'] != HEURIST_OK){

                        $mysqli->rollback();
                        if($keep_autocommit===true) {$mysqli->autocommit(true);}

                        return false;
                    }

                    $new_rec_ids[] = $result['data'];
                }
            }

            // 3. Update dtl_RecID for original values to point to new records -----
            foreach($new_rec_ids as $idx => $rec_id){

                $dtl_IDs = $details_to_transfer;
                if($split_values != 0){
                    $dtl_IDs = $details_to_transfer[$idx];
                }

                $dtl_IDs = prepareIds($dtl_IDs);//for snyk

                $upd_where = count($dtl_IDs) == 1 ? ("= " . $dtl_IDs[0]) : ("IN (" . implode(',', $dtl_IDs) . ")");
                $upd_query = "UPDATE recDetails SET dtl_RecID = $rec_id WHERE dtl_ID $upd_where";
                $res = $mysqli->query($upd_query);

                if(!$res || $mysqli->affected_rows == 0){ // affected rows should always be greater than 0

                    $msg = "<br><br>Error => " . $mysqli->error . "<br><br>Query => $upd_query";
                    $msg .= "<br><br>dtl_IDs => " . print_r($dtl_IDs, true);
                    $system->addError(HEURIST_DB_ERROR, "An SQL error occurred while attempting to update the original values from record #$rec_id");

                    $mysqli->rollback();
                    if($keep_autocommit===true) {$mysqli->autocommit(true);}

                    return false;
                }
            }

            // 4. Add child reference to original record -----

            // Get original record's header fields, to avoid lossing them
            $record = recordSearchByID($this->system, $rec_id, false);
            if(!$record){

                $mysqli->rollback();
                if($keep_autocommit===true) {$mysqli->autocommit(true);}
                return false;
            }

            // Add rec pointer value(s)
            $record['ID'] = $record['rec_ID'];
            unset($record['rec_ID']);
            $record['no_validation'] = 1;
            $record['details'] = array(
                $target_field => $new_rec_ids
            );

            $result = recordSave($this->system, $record, false, false, 2);
            if($result['status'] != HEURIST_OK || $result['data'] != $record['ID']){

                $mysqli->rollback();
                if($keep_autocommit===true) {$mysqli->autocommit(true);}

                return false;
            }

            array_push($new_records, ...$new_rec_ids);// add new rec ids to array
        }

        $mysqli->commit();
        if($keep_autocommit===true) {$mysqli->autocommit(true);}

        $final_count = count($new_records);// get final count of new records

        return array('count' => $final_count, 'record_ids' => implode(',', $new_records));
    }

    /**
     * Change letter cases fo values found in freetext and blocktext (memo) fields based on selection:
     *  1 - Lowercase, uppercase first letter + first letter following fullstops
     *  2 - Lowercase, uppercase first letter of each word
     *  3 - All lowercase
     *  4 - All capital
     * Also changes words/phrases based on list of exceptions (performed last to avoid further editing)
     * Exceptions can be array or '|' separated list
     *
     * @return false|array - false on error | array with keys processed (records with updated vaules), undefined (no values) and errors (encountered an SQL error)
     */
    public function caseConversion(){

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $mysqli = $this->system->get_mysqli();
        $date_mode = date(DATE_8601);// for tags, rec_modified and dtl_modified

        $operation = intval($this->data['op']);// number corresponding to an operation below
        $doc = new DOMDocument; // for handling html text

        // Prepare exceptions list
        $exceptions = empty(@$this->data['except']) ? array() : $this->data['except'];
        if(!is_array($exceptions)){
            $exceptions = explode('|', $exceptions);
        }

        if(!empty($exceptions)){
            $new_excepts = array();

            foreach ($exceptions as $value) {
                if(empty($value)){
                    continue;
                }

                array_push($new_excepts, $mysqli->real_escape_string($value));
            }

            $exceptions = $new_excepts;
        }

        // Regular expressions for operations
        $regex = $operation == 1 ? '(\.\s+)(\w+)' : '';
        $regex = $operation != 1 ? '\w+' : $regex;

        // Temp tags for HTML handling, loadHTML will tend to add paragraph tags if no outer tag exists
        $temp_open = "<span data-t='zzz_temp'>";
        $temp_close = "</span>";

        // Callback function for regex functions
        $callback = function($match) use ($operation){

            $word = $operation == 1 ? $match[2] : $match[0];

            if($operation == 1){
                // lowercase then capitalise first letter + first letter following full stop

                $first = mb_substr($word, 0, 1);
                $remainder = mb_substr($word, 1, null);

                return $match[1] . mb_strtoupper($first) . $remainder;

            }elseif($operation == 2){
                // lowercase then capitalise first letter for all words

                if(strlen($word) == 1 || mb_ereg("[a-z][A-Z]|[A-Z][a-z]", $word)){ // skip if one letter or camel case
                    return $word;
                }

                $first = mb_substr($word, 0, 1);
                $remainder = mb_substr($word, 1, null);

                return mb_strtoupper($first) . $remainder;

            }
        };

        // Field details
        $dtyID = intval($this->data['dtyID']);
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']);
        $baseTag = "~replace case convert $dtyName $date_mode";

        // Check field is freetext or blocktext
        $fld_type = $this->getDetailType($dtyID);
        if($dtyID < 1 || ($fld_type != 'freetext' && $fld_type != 'blocktext')){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Case conversion only works on valid freetext and blocktext fields');
            return false;
        }

        // Validate operation value
        if($operation < 1 || $operation > 4){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Provided operation is not handled by case converter');
            return false;
        }

        $use_reg = $operation < 2; // whether to use the regex functions

        //$keep_autocommit = mysql__begin_transaction($mysqli);

        // Setup report variable
        $completed_recs = array();
        $skipped_recs = array();
        $sql_errors = array();

        // Cycle through records
        foreach ($this->recIDs as $recID){

            $res = $mysqli->query("SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_DetailTypeID = $dtyID AND dtl_RecID = $recID");

            if(!$res){
                $sql_errors[$recID] = $mysqli->error;
                continue;
            }elseif($res->num_rows == 0){ // no values within field
                array_push($skipped_recs, $recID);
                continue;
            }

            $sql_errors[$recID] = array();

            // Cycle through values
            while($values = $res->fetch_row()){

                $value = '';

                if($values[1] != strip_tags($values[1])){ // potentially has HTML

                    $value = $temp_open.$values[1].$temp_close; // add temp tags, to avoid extra elements

                    $doc->loadHTML($value, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);// load html

                    $xpath = new DOMXPath($doc);// retrieve text only
                    $text_nodes = $xpath->query('//text()');

                    foreach($text_nodes as $node){

                        $text = $operation == 1 || $operation == 3 ? mb_strtolower($node->data) : $node->data;
                        $text = $operation == 4 ? mb_strtoupper($text) : $text;

                        $node->data = $use_reg ? mb_ereg_replace_callback($regex, $callback, $text) : $text;
                    }

                    $value = $doc->saveHTML();// save new value

                    // strip temp tags
                    $value = mb_substr($value, strlen($temp_open));
                    $value = mb_substr($value, 0, mb_strlen($value) - strlen($temp_close) - 1);

                }else{ // normal text

                    $text = $operation == 1 ? mb_strtolower($values[1]) : $values[1];
                    $text = $operation == 4 ? mb_strtoupper($text) : $text;

                    $value = $use_reg ? mb_ereg_replace_callback($regex, $callback, $text) : $text;

                    if($operation == 1 && !empty($value)){ // capitalise first letter

                        $first = mb_substr($value, 0, 1);
                        $remainder = mb_strlen($value) == 1 ? "" : mb_substr($value, 1, null);

                        $value = mb_strtoupper($first) . $remainder;
                    }
                }

                if(empty($value)){ // ensure there is a value to save
                    continue;
                }

                foreach($exceptions as $except){ // apply exceptions
                    $regex = preg_quote($except);
                    $regex = "\b$regex\b";
                    if(mb_eregi($regex, $value)){ // check if exception appears in string
                        $value = mb_eregi_replace($regex, $except, $value);// replace
                    }
                }

                // Update details value + modified
                $dtl_rec = array('dtl_ID' => intval($values[0]), 'dtl_Value' => $value, 'dtl_Modified' => $date_mode);

                $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl_rec);
                if(!is_numeric($ret)){
                    $sql_errors[$recID][] = $ret;
                    continue;
                }

                // Update record modified
                $ret = mysql__insertupdate($mysqli, 'Records', 'rec', array('rec_ID' => $recID, 'rec_Modified' => $date_mode));
                if(!is_numeric($ret)){
                    $sql_errors[$recID][] = $ret;
                }
            }//for

            array_push($completed_recs, $recID);
            if(!empty($sql_errors[$recID])){
                $sql_errors[$recID] = implode(' ;', $sql_errors[$recID]);
            }else{
                unset($sql_errors[$recID]);
            }
        }

        // Final touches to report
        $this->_assignTagsAndReport('processed', $completed_recs, $baseTag);
        $this->_assignTagsAndReport('undefined', $skipped_recs, $baseTag);
        $this->_assignTagsAndReport('errors',  $sql_errors, $baseTag);

        $this->result_data['undefined'] = count($skipped_recs);
        $this->result_data['undefined_list'] = $skipped_recs;

        return $this->result_data;
    }


    /**
     * Translate field value
     *
     * @return false|array - false on error | array with keys processed (records with updated vaules), undefined (no values) and errors (encountered an SQL error)
     */
    public function fieldTranslation(){

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $mysqli = $this->system->get_mysqli();
        $date_mode = date(DATE_8601);// for tags, rec_modified and dtl_modified

        // Field details
        $dtyID = intval($this->data['dtyID']);
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']);
        $baseTag = "~translation $dtyName $date_mode";

        // Check field is freetext or blocktext
        $fld_type = $this->getDetailType($dtyID);
        if($dtyID < 1 || ($fld_type != 'freetext' && $fld_type != 'blocktext')){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Translation only works on valid freetext and blocktext fields');
            return false;
        }

        $is_replacement = (@$this->data['replace']==1);
        $is_deletion = (@$this->data['delete']==1);
        $lang = @$this->data['lang'];

        $lang = getLangCode3($lang);

        if($lang==null){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Language is not defined');
            return false;
        }

        // Setup report variable
        $completed_recs = array();
        $skipped_recs = array();
        $already_translated = array();
        $sql_errors = array();

        // Cycle through records
        foreach ($this->recIDs as $recID){

            $res = $mysqli->query("SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_DetailTypeID = $dtyID AND dtl_RecID = $recID");

            if(!$res){
                $sql_errors[$recID] = $mysqli->error;
                continue;
            }elseif($res->num_rows == 0){ // no values within field
                array_push($skipped_recs, $recID);
                continue;
            }

            $sql_errors[$recID] = array();

            $replacement_dtl_id = -1;
            $value_to_translate = null;
            $all_detected = 0;
            $source_lang = null;

            // Cycle through values - find and source and possible replacement
            while( ($values = $res->fetch_row()) && ($all_detected<2)){

                //detect language
                list($lang2, $val) = extractLangPrefix($values[1]);
                if($lang2==null){
                    //source
                    $value_to_translate = $val;      // $is_replacement
                    $all_detected++;
                    $source_lang = null;
                }elseif($lang2==$lang){
                    //already has this translation
                    if($is_replacement || $is_deletion){
                        $replacement_dtl_id = intval($values[0]);
                    }else{
                        $replacement_dtl_id = 0;
                    }
                    $all_detected++;
                }elseif(empty($value_to_translate)){
                    // temporary source, is replaced by value w/o language prefix
                    $value_to_translate = $val;
                    $source_lang = $lang2;
                }
            }//while

            if($is_deletion){

                if($replacement_dtl_id>0){
                    $query = 'DELETE FROM recDetails WHERE dtl_ID='.$replacement_dtl_id;
                    $ret = $mysqli->query($query);
                    if(!$ret){
                        $sql_errors[$recID][] = $mysqli->error;
                    }
                }else{
                    array_push($skipped_recs, $recID);
                }
            }elseif($value_to_translate==null){
                //source not found - skip
                array_push($skipped_recs, $recID);
            }elseif($replacement_dtl_id==0){
                //already translated
                array_push($already_translated, $recID);
            }else {

                // get translated value
                $translated = getExternalTranslation($this->system, $value_to_translate, $lang, $source_lang);

                //$translated = $lang.': TRNASLATED! '.$value_to_translate;

                if($translated===false){
                    //break;
                    $this->system->addErrorMsg('Translation has been terminated for record# '.$recID.'. <br>');
                    return false;
                }

                $translated = $lang.':'.$translated;

                // Update details value + modified
                $dtl_rec = array('dtl_Value' => $translated, 'dtl_Modified' => $date_mode);
                if($replacement_dtl_id>0){
                    $dtl_rec['dtl_ID'] = $replacement_dtl_id;
                }else{
                    $dtl_rec['dtl_RecID'] = $recID;
                    $dtl_rec['dtl_DetailTypeID'] = $dtyID;
                }

                $ret = mysql__insertupdate($mysqli, 'recDetails', 'dtl', $dtl_rec);
                if(!is_numeric($ret)){
                    $sql_errors[$recID][] = $ret;
                    continue;
                }

                // Update record modified
                $ret = mysql__insertupdate($mysqli, 'Records', 'rec', array('rec_ID' => $recID, 'rec_Modified' => $date_mode));
                if(!is_numeric($ret)){
                    $sql_errors[$recID][] = $ret;
                    continue;
                }
            }

            array_push($completed_recs, $recID);
            if(!empty($sql_errors[$recID])){
                $sql_errors[$recID] = implode(' ;', $sql_errors[$recID]);
            }else{
                unset($sql_errors[$recID]);
            }
        }//foreach records

        // Final touches to report
        $this->_assignTagsAndReport('processed', $completed_recs, $baseTag);
        $this->_assignTagsAndReport('undefined', $skipped_recs, $baseTag);
        $this->_assignTagsAndReport('translated', $already_translated, $baseTag);
        $this->_assignTagsAndReport('errors',  $sql_errors, $baseTag);

        $this->result_data['undefined'] = count($skipped_recs);
        $this->result_data['undefined_list'] = $skipped_recs;

        return $this->result_data;
    }

    /**
     * Upload file to an external repository
     * 1. Find files linked to given set of records
     * 2. Create metadata
     */
    public function uploadFileToRepository(){

        if(!$this->_validateParamsAndCounts()){
            return false;
        }elseif (isEmptyArray(@$this->recIDs)){
            return $this->result_data;
        }

        $mysqli = $this->system->get_mysqli();
        $date_mode = date(DATE_8601);

        $dtyID = $this->data['dtyID'];
        $dtyName = (@$this->data['dtyName'] ? "'".$this->data['dtyName']."'" : "id:".$this->data['dtyID']);
        $baseTag = "~replace file to url $dtyName $date_mode";

        $processedRecIDs = array();
        $sqlErrors = array();
        $uploadError = array();
        $failed_ids = array();

        $file_entity = new DbRecUploadedFiles($this->system);

        // Find relevant local files
        $query = 'SELECT dtl_ID, ulf_ID, dtl_RecID '
        .'FROM recUploadedFiles, recDetails '
        .'WHERE ulf_ID=dtl_UploadedFileID AND '
        .'(NOT(ulf_OrigFileName="_remote" OR ulf_OrigFileName LIKE "'.ULF_IIIF.'%" OR ulf_OrigFileName LIKE "'.ULF_TILED_IMAGE.'%"))'
        .' AND dtl_DetailTypeID='.$dtyID
        .SQL_AND.predicateId('dtl_RecID', $this->recIDs)
        .' ORDER BY ulf_ID';
        $res = $mysqli->query($query);
        /** $row:
         * [0] => Rec Detail ID
         * [1] => File ID
         * [2] => Record ID
         */

        if(!$res){ // mysql error, end
            $this->system->addError(HEURIST_ERROR, 'An error occurred while attempting to retrieve records using locally stored files.<br><br>MySQLi Error: ' . $mysqli->error);
            return false;
        }

        $cur_ulf_ID = 0;
        $new_ulf_ID = 0;
        $dtl_IDs = array();
        $rec_IDs = array();
        $completed_ulf_IDs = array();

        //2024-03-23
        // Obtain write API key/credentials
        $service_id = $this->data['repository'];

        $credentials = user_getRepositoryCredentials2($this->system, $service_id);

        if($credentials==null){

            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Credentials for sepecified repository and user/group not found');
            return false;

        }elseif(!@$credentials[$service_id]['params']['writeApiKey']){  // || @$credentials['params']['writeUser']

            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Write Credentials for sepecified repository and user/group not defined');
            return false;

        }elseif(strpos($service_id,'nakala')===0 || strpos($service_id,'nakala')===1){

            if(!array_key_exists('license', $this->data) || empty($this->data['license'])){ // ensure a license has been provided
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'A license is missing');
                return false;
            }

            $meta_values = array();
            $file = array();

            // General Meta data
            // Normal Creator field (we use alternative author field, as this requires Author Ids/ORCIDs)
            $meta_values['creator'] = array(
                'value' => null,
                'lang' => null,
                'typeUri' => null,
                'propertyUri' => NAKALA_REPO.'terms#creator'
            );
            // Provided by user - used for all files
            $meta_values['license'] = array(
                'value' => $this->data['license'],
                'lang' => null,
                'typeUri' => XML_SCHEMA,
                'propertyUri' => NAKALA_REPO.'terms#license'
            );

            $api_key = $credentials[$service_id]['params']['writeApiKey'];  //$this->system->get_system('sys_NakalaKey')
            $use_test_url = @$this->data['use_test_url'] == 1 || strpos($service_id,'nakala')===1 ? 1 : 0;

            while($row = $res->fetch_row()){

                if($cur_ulf_ID != $row[1]){

                    if($new_ulf_ID > 0){
                        if($this->_updateUploadedFileIDs($new_ulf_ID, $dtl_IDs, $date_mode)){
                            $completed_ulf_IDs[$row[1]] = $new_ulf_ID;
                            $processedRecIDs = array_merge($processedRecIDs, $rec_IDs);
                        }else{
                            $failed_ids = array_merge($failed_ids, $rec_IDs);
                        }
                    }

                    $cur_ulf_ID = $row[1];
                    $dtl_IDs = array();
                    $rec_IDs = array();
                    $new_ulf_ID = 0;

                    $file_query = 'SELECT ulf_OrigFileName, concat(ulf_FilePath, ulf_FileName) AS "fullPath", fxm_MimeType, ulf_Description, concat(ugr_FirstName, " ", ugr_LastName) AS "fullName", DATE(ulf_Added) '
                    .'FROM recUploadedFiles, defFileExtToMimetype, sysUGrps '
                    .'WHERE ulf_ID=' . intval($row[1]) . ' AND ulf_MimeExt=fxm_Extension AND ulf_UploaderUGrpID=ugr_ID';
                    $file_res = $mysqli->query($file_query);
                    if(!$file_res){ // another mysql error, skip
                        $sqlErrors[$row[2]][] = FILE_NO . $row[1] . R_ARROW . $mysqli->error;
                        $failed_ids[] = $row[2];
                        continue;
                    }
                    /** $file_dtl:
                     * [0] => title
                     * [1] => file path
                     * [2] => mime type
                     * [3] => description
                     * [4] => Uploader's full name
                     * [5] => created date (no time)
                     */

                    $file_dtl = $file_res->fetch_row();
                    $file_path = resolveFilePath($file_dtl[1]);
                    if(!file_exists($file_path)){
                        $uploadError[$row[2]][] = FILE_NO . $row[1] . R_ARROW . 'Unable to locate the local file for transfer';
                        $failed_ids[] = $row[2];
                        continue;
                    }

                    $file = array('path' => $file_path,
                                  'type' => $file_dtl[2],
                                  'name' => $file_dtl[0],
                                  'description' => $file_dtl[3]);

                    $meta_values['title'] = array(
                        'value' => $file_dtl[0],
                        'lang' => null,
                        'typeUri' => XML_SCHEMA,
                        'propertyUri' => NAKALA_REPO.'terms#title'
                    );

                    $file_type = $file_dtl[2];

                    /** Use fxm_MimeType
                     * Nakala <=> Mime Type
                     * text <=> text | pdf
                     * image <=> image
                     * sound <=> sound | audio
                     * video <=> video
                     * other <=> anything else
                     */
                    if(strpos($file_type, 'text') !== false || strpos($file_type, 'pdf') !== false){
                        $file_type = 'http://purl.org/coar/resource_type/c_1843';
                    }elseif(strpos($file_type, 'sound') !== false || strpos($file_type, 'audio') !== false){
                        $file_type = 'http://purl.org/coar/resource_type/c_18cc';
                    }elseif(strpos($file_type, 'image') !== false){
                        $file_type = 'http://purl.org/coar/resource_type/c_c513';
                    }elseif(strpos($file_type, 'video') !== false){
                        $file_type = 'http://purl.org/coar/resource_type/c_12ce';
                    }else{ // other
                        $file_type = 'http://purl.org/coar/resource_type/c_1843';
                    }
                    $meta_values['type'] = array(
                        'value' => $file_type,
                        'lang' => null,
                        'typeUri' => 'http://www.w3.org/2001/XMLSchema#anyURI',
                        'propertyUri' => NAKALA_REPO.'terms#type'
                    );

                    // Current Heurist user
                    $meta_values['alt_creator'] = array(
                        'value' => $file_dtl[4],
                        'lang' => null,
                        'typeUri' => XML_SCHEMA,
                        'propertyUri' => 'http://purl.org/dc/terms/creator'
                    );

                    // ulf_Added
                    $meta_values['created'] = array(
                        'value' => $file_dtl[5],//date('Y-m-d', $file_dtl[5]),
                        'lang' => null,
                        'typeUri' => XML_SCHEMA,
                        'propertyUri' => NAKALA_REPO.'terms#created'
                    );

                    $rtn = uploadFileToNakala($this->system,   //upload in batch
                        array('api_key' => $api_key, 'file' => $file,
                              'meta' => $meta_values, 'status' => 'published', // pending | published
                              'use_test_url' => $use_test_url));

                    if($rtn){ // register URL ($rtn)
                        //$file_entity->setRecords(null);// reset records
                        if($service_id){
                            $fields = array('ulf_Parameters'=>'{"repository":"'.$service_id.'"}');
                        }else{
                            $fields = null;
                        }

                        $new_ulf_ID = $file_entity->registerURL($rtn,false,0,$fields);// register nakala url
                        if(!is_numeric($new_ulf_ID) || $new_ulf_ID > 0){
                            $sqlErrors[$row[2]][] = FILE_NO . $row[1] . R_ARROW . $mysqli->error;
                            $failed_ids[] = $row[2];
                        }
                    }else{
                        $err_msg = $this->system->getError();
                        if(array_key_exists('message', $err_msg)){
                            $err_msg = $err_msg['message'];
                        }else{
                            $err_msg = 'Unknown error occurred while uploading to Nakala';
                        }
                        $uploadError[$row[2]][] = FILE_NO . $row[1] . R_ARROW . $err_msg;
                        $failed_ids[] = $row[2];
                    }
                }

                $dtl_IDs[] = intval($row[0]);
                $rec_IDs[] = intval($row[3]);

            } // while
        }
        if($new_ulf_ID > 0){
            if($this->_updateUploadedFileIDs($new_ulf_ID, $dtl_IDs, $date_mode)){
                $completed_ulf_IDs[$row[1]] = $new_ulf_ID;
                $processedRecIDs = array_merge($processedRecIDs, $rec_IDs);
            }else{
                $failed_ids = array_merge($failed_ids, $rec_IDs);
            }
        }

        if(!empty($completed_ulf_IDs)){
            $ulf_to_delete = array();
            foreach ($completed_ulf_IDs as $org_ID => $new_ID) {
                $query = 'SELECT dtl_ID FROM recDetails WHERE dtl_UploadedFileID = ' . $org_ID;
                $dtl_IDs = mysql__select_list2($mysqli, $query, 'intval');

                if(!$dtl_IDs){
                    continue;
                }

                if(empty($dtl_IDs)){ // delete file reference + local file
                    $ulf_to_delete[] = $org_ID;
                }elseif(array_key_exists('delete_file', $this->data) && $this->data['delete_file'] == 1){
                    // update references
                    $dtl_IDs = prepareIds($dtl_IDs);//for snyk
                    if($this->_updateUploadedFileIDs($new_ID, $dtl_IDs, $date_mode)){
                        // then delete the file reference + local file
                        $ulf_to_delete[] = $org_ID;
                    }
                }
            }

            if(!empty($ulf_to_delete)){
                $cur_data = $file_entity->getData();
                $cur_date['ulf_ID'] = array_unique($ulf_to_delete);
                $file_entity->setData($cur_data);
                $file_entity->delete();
            }
        }

        $failed_ids = array_unique($failed_ids);

        $this->_assignTagsAndReport('processed', $processedRecIDs, $baseTag);
        $this->_assignTagsAndReport('errors',  array_merge($sqlErrors, $uploadError), $baseTag);
        $this->result_data['fails'] = count($failed_ids);
        $this->result_data['fails_list'] = $failed_ids;

        return $this->result_data;
    }


    /**
     * Creates links (resource/record pointer field) or Adds relationship records between records
     * based on certain fields values matching
     *
     * $data parameters
     *          dty_ID - resource (record pointer) field id or  trm_ID - relationtype ID
     *          rty_src or recids_src, dty_src, rty_trg, dty_trg - matching conditions
     *          repalce - 1 replace existing, otherwise add new link
     *
     * @return false|array - false on error | array with keys count (number of new records) and record_ids (comma list of new record ids)
     */
    public function createRecordLinksByMatching(){

        // Can only be used by an administrator
        if(!$this->system->is_admin()){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Only database administrators can add multiple links');
            return false;
        }

        $system = $this->system;
        $mysqli = $system->get_mysqli();

        $data = $this->data;

        //dty_ID - resource (record pointer) field id or  trm_ID - relationtype ID
        //recIDs or rty_src, dty_src, rty_trg, dty_trg - matching conditions

        //1. Validate dty_ID or trm_ID from parameters
        $dty_ID = intval(@$data['dty_ID']);
        $trm_ID = intval(@$data['trm_ID']);


        //check that this is resouce filed
        if($dty_ID>0){

            if('resource' != $this->getDetailType($dty_ID)){
                $system->addError(HEURIST_INVALID_REQUEST, 'Wrong paramters for records link creation. Given field is not a record pointer ("resource") field');

                return false;
            }
        }elseif($trm_ID>0){
            //check that trm_ID is valid

        }



        $to_replace = (@$data['replace']==1);//replace existing link

        //2. Find matching pairs - [source rec_ID, target rec_ID]
        $data['pairs'] = 1;
        $pairs = recordSearchMatchedValues($system, $data);

        if(@$pairs['status']==HEURIST_OK){
            $pairs = $pairs['data'];
        }else{
            return false;
        }

        //3. Add pointer or create relationship record

        if($trm_ID>0){
            $system->defineConstant('RT_RELATION');
            $system->defineConstant('DT_PRIMARY_RESOURCE');
            $system->defineConstant('DT_TARGET_RESOURCE');
        }

        $keep_autocommit = mysql__begin_transaction($mysqli);

        $this->result_data = array('added'=>0,'exist'=>0,'records_updated'=>0);

        $res = true;

        $execution_counter = 0;
        $tot_count = count($pairs);

        $prev_rec_ID = 0;

        foreach($pairs as $row){

            $source_id = $row[0];
            $target_id = $row[1];

            if($trm_ID>0){
                $res = 1;
            }else{
                $res = addPointerField($system, $source_id, $target_id, $dty_ID, $to_replace);
            }
            if($res<0){
                $mysqli->rollback();
                $res = false;
                break;
            }elseif($res==0){
                $res = true;
                $this->result_data['exist']++;
            }else{
                $this->result_data['added']++;
                if($prev_rec_ID!=$source_id){
                    $prev_rec_ID=$source_id;
                    $this->result_data['records_updated']++;
                }
            }


            if($this->session_id!=null){
                //check for termination and set new value
                $execution_counter++;
                $session_val = $execution_counter.','.$tot_count;
                $current_val = mysql__update_progress($mysqli, $this->session_id, false, $session_val);
                if($current_val=='terminate'){ //session was terminated from client side
                    $system->addError(HEURIST_ACTION_BLOCKED, 'Action has been terminated by user');
                    return false;
                }
            }
        }

        if($res){
            $mysqli->commit();
        }
        if($keep_autocommit===true) {$mysqli->autocommit(true);}

        return $res?$this->result_data:false;
    }

}
?>
