<?php

/**
* importAction.php  working with import data in temporary table -
*  1) matching
*  2) assign rec ids    assignRecordIds
*  3) validate          validateImport
*  4) perform import  - performImport
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
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

use hserv\entity\DbDefTerms;
use hserv\utilities\USanitize;
use hserv\entity\DbRecUploadedFiles;

require_once dirname(__FILE__).'/../edit/recordModify.php';
require_once dirname(__FILE__).'/../../utilities/geo/mapCoordinates.php';
require_once dirname(__FILE__).'/../../utilities/geo/mapCoordConverter.php';
require_once dirname(__FILE__).'/../../../vendor/autoload.php';//for geoPHP
require_once dirname(__FILE__).'/../../../admin/verification/verifyValue.php';

define('ERR_VALIDATION_QUERY','SQL error: Cannot perform validation query: ');
define('MSG_VALIDATION_1','validation of numeric fields');
define('MSG_VALIDATION_2','validation of record pointer fields');

/**
* 3 public methods
* assignRecordIds  (matching)
* validateImport
* performImport
*
*/
class ImportAction {

    private static $system = null;
    private static $mysqli = null;
    private static $initialized = false;

    private static $rep_processed = 0;
    private static $rep_added     = 0;
    private static $rep_updated   = 0;
    private static $rep_skipped    = 0;
    private static $rep_skipped_details = array();
    private static $rep_permission  = 0;
    private static $rep_unique_ids = array();
    //private static $imp_session;

    private static $invalid_geo = 0;

    private static $increment_fields = array();// array(rty_ID => array(dty_ID1, dty_ID2, ...))

private static function initialize($fields_correspondence=null)
{
    if (self::$initialized) {return;}

    global $system;
    self::$system  = $system;
    self::$mysqli = $system->getMysqli();
    self::$initialized = true;
}

//-------------------------------- MATCHING ----------------------------------------

private static function findDisambResolution($keyvalue, $disamb_resolv){

    $idx = findInArray($disamb_resolv, 'key', $keyvalue);
    if(isset($idx) && $idx>=0){
        return @$disamb_resolv[$idx]['recid'];
    }

    return null;
}

private static function composeQuery($fields, $import_table){
    return "SELECT imp_id, `".implode("`,`",$fields)."` FROM `$import_table` ";
}

/**
* Perform matching - find record id in heurist db
*
* @param mixed $mysqli
* @param mixed $imp_session
* @param mixed $params
*/
private static function findRecordIds($imp_session, $params){

    $progress_session_id = @$params['session'];

    //result array
    $imp_session['validation'] = array(
        "count_update"=>0,
        "count_insert"=>0,
        "count_update_rows"=>0,
        "count_insert_rows"=>0,
        "count_ignore_rows"=>0, //all key fields are empty - ignore
        "count_error"=>0,  //NOT USED total number of errors (may be several per row)
        "error"=>array(),
        "recs_insert"=>array(),     //full record
        "recs_update"=>array() );

    $import_table = $imp_session['import_table'];
    $multivalue_field_name = @$params['multifield'];//name of multivalue field - among mapped fields ONLY ONE can be multivalued

    if( $multivalue_field_name!=null && $multivalue_field_name>=0 ) {$multivalue_field_name = 'field_'.$multivalue_field_name;}

    $cnt_update_rows = 0;
    $cnt_insert_rows = 0;

    //disambiguation resolution
    $disamb_resolv = @$params['disamb_resolv'];//record id => $keyvalue
    if($disamb_resolv!=null && !is_array($disamb_resolv)){
        $disamb_resolv = json_decode($disamb_resolv, true);
    }
    if(!$disamb_resolv){ //old way
        $disamb_ids = @$params['disamb_id'];//record ids
        $disamb_keys = @$params['disamb_key'];//key values
        $disamb_resolv = array();
        if($disamb_keys){
            foreach($disamb_keys as $idx => $keyvalue){
                array_push($disamb_resolv, array('recid'=>$disamb_ids[$idx], 'key'=>str_replace("\'", "'", $keyvalue) ));
                //$disamb_resolv[$disamb_ids[$idx]] = str_replace("\'", "'", $keyvalue);//rec_id => keyvalue
            }
        }
    }

    //get rectype to import
    $recordType = @$params['sa_rectype'];
    $currentSeqIndex = @$params['seq_index'];
    $ignore_rectype = @$params['ignore_rectype'] ? $params['ignore_rectype'] : 0;

    $detDefs = dbs_GetDetailTypes(self::$system, 'all', 1 );

    $detDefs = $detDefs['typedefs'];
    $idx_dt_type = $detDefs['fieldNamesToIndex']['dty_Type'];

    $mapped_fields = array();
    $mapping = @$params['mapping'];
    $sel_fields = array();
    $mapping_fieldname_to_index = array();//field_x => x for quick access

    //get arrays of  field_XXX => field type, field_XXX=>index in mapping
    if(is_array($mapping))
    {
        foreach ($mapping as $index => $field_type) {
            if($field_type=="url" || $field_type=="id" || @$detDefs[$field_type]){
                    $field_name = "field_".$index;
                    $mapping_fieldname_to_index[$field_name] = $index;

                    $mapped_fields[$field_name] = $field_type;
                    $sel_fields[] = $field_name;
            }
        }
    }

    if(empty($mapped_fields)){
        self::$system->addError(HEURIST_ACTION_BLOCKED, 'Import CSV. Matching step failed. No mapping defined');
        return false;
    }

    //keep mapping   field_XXX => dty_ID
    $imp_session['validation']['mapped_fields'] = $mapped_fields;

    //already founded IDs
    $pairs = array();//to avoid search    $keyvalue=>new_recID
    $mapping_keys_values = array();//new_recID => array(field idx=>value,.... - for multivalue keyvalues

    $records = array();
    $disambiguation = array();
    $disambiguation_lines = array();
    $tmp_idx_insert = array();//to keep indexes
    $tmp_idx_update = array();//to keep indexes

    $mysqli = self::$mysqli;

    //--------------------------------------------------------------------------
    //loop all records in import table and detect what is for insert and what for update

    $tot_count = $imp_session['reccount'];
    $cnt = 0;
    $step = ceil($tot_count/100);
    if($step>100) {$step = 100;}
    elseif($step<10) {$step=10;}

    $select_query = self::composeQuery($sel_fields, $import_table);

    $res = $mysqli->query($select_query);
    if($res){
        mysql__update_progress(null, $progress_session_id, true, '0,'.$tot_count);

        $ind = -1;
        while ($row = $res->fetch_assoc()){

                $imp_id = $row['imp_id'];

                $values_tobind = array();
                $values_tobind[] = '';//first element for bind_param must be a string with field types
                $keys_values = array();//field index => keyvalue from csv
                $keys_values_all = array();//all field values including empty

                //BEGIN statement constructor
                $select_query_match_from = array("Records");
                $select_query_match_where = array();
                if($ignore_rectype != 1){
                    array_push($select_query_match_where, "rec_RecTypeID=".$recordType);
                }

                $multivalue_selquery_from = null;
                $multivalue_selquery_where = null;
                $multivalue_field_value = null;

                $index = 0;
                foreach ($mapped_fields as $fieldname => $field_type) {


                    if($row[$fieldname]==null || trim($row[$fieldname])=='') {

                        //$keys_values_all[] = '';
                        //continue; //ignore empty values

                        if($field_type>0){
                            array_push($select_query_match_where, 'NOT exists (select dtl_ID from recDetails '
                                .' WHERE dtl_DetailTypeID='.$field_type
                                .' AND rec_ID=dtl_RecID)');

                            //$multivalue_selquery_from = '';
                        }


                    }else{
                        $fieldvalue = super_trim($row[$fieldname]);


                        if($field_type=="url" || $field_type=="id"){  // || $field_type=="scratchpad"){
                            array_push($select_query_match_where, "rec_".$field_type."=?");
                            $values_tobind[] = $fieldvalue;
                            $keys_values_all[] = $fieldvalue;
                        }elseif(is_numeric($field_type)){

                            $from = '';
                            $where = "d".$index.".dtl_DetailTypeID=".$field_type.SQL_AND;
                            $dt_type = $detDefs[$field_type]['commonFields'][$idx_dt_type];

                            if( $dt_type == "enum" ||  $dt_type == "relationtype") {
                                //if fieldname is numeric - compare it with dtl_Value directly
                                $where = $where."( d".$index.".dtl_Value=t".$index.".trm_ID and "
                                ." (? in (t".$index.".trm_Label, t".$index.".trm_Code)))";

                                $from = 'defTerms t'.$index.',';
                            }else{
                                $where = $where." (d".$index.".dtl_Value=?)";
                            }

                            $where = 'rec_ID=d'.$index.'.dtl_RecID and '.$where;
                            $from = $from.'recDetails d'.$index;

                            // we may work with the only multivalue field for matching
                            // otherwise it is not possible to detect proper combination
                            if($multivalue_field_name==$fieldname){
                                $multivalue_selquery_from = $from;
                                $multivalue_selquery_where = $where;
                                $multivalue_field_value = $fieldvalue;
                            }else{
                                array_push($select_query_match_where, $where);
                                array_push($select_query_match_from, $from);
                                $values_tobind[] = $fieldvalue;
                                $keys_values_all[] = $fieldvalue;
                                $values_tobind[0] = $values_tobind[0].'s';

                                if(@$mapping_fieldname_to_index[$fieldname]>=0){
                                    $keys_values[$mapping_fieldname_to_index[$fieldname]] = $fieldvalue;
                                }
                            }

                        }
                    }
                        $index++;
                }//for all fields in match array

                $cnt++;
                if($cnt % $step == 0){
                    mysql__update_progress(null, $progress_session_id, true, $cnt.','.$tot_count);
                }

                if($index==0){//all matching fields in import table are empty - skip it
                    $imp_session['validation']['count_ignore_rows']++;
                    continue;
                }


                $is_update = false;
                $is_insert = false;

                $ids = array();
                $values = array('');
                //split multivalue field
                if($multivalue_field_name!=null && $multivalue_field_value!=null && $multivalue_field_name!=='' && $multivalue_field_value!==''){
                    $values = self::getMultiValues($multivalue_field_value, $imp_session['csv_enclosure'], $imp_session['csv_mvsep']);
                    if(isEmptyArray($values)){
                        $values = array('');//at least one value
                    }
                }

                //keyvalue = values from other field + value from multivalue
                foreach($values as $idx=>$value){ //from multivalue matching field

                    $a_tobind = $values_tobind; //values for prepared query
                    $a_from = $select_query_match_from;
                    $a_where = $select_query_match_where;

                    $a_keys = $keys_values_all;

                    if($multivalue_field_name!=null){

                      $row[$multivalue_field_name] = $value;

                      if(@$mapping_fieldname_to_index[$multivalue_field_name]>=0){
                                $keys_values[$mapping_fieldname_to_index[$multivalue_field_name]] = $value;
                      }
                    }

                    if(super_trim($value)!=''){ //if multivalue field has values use $values_tobind
                            $a_tobind[0] = $a_tobind[0].'s';
                            $a_tobind[] = $value;
                            $a_keys[] = $value;

                            $a_from[] = $multivalue_selquery_from;
                            $a_where[] = $multivalue_selquery_where;
                    }

                    //merge all values - to create unuque key for combination of values
                    //$keyvalue = implode('▫',$keys_values2);
                    $keyvalue = implode('▫', $a_keys);

                    /*
                    $fc = $a_tobind;
                    array_shift($fc);//remove ssssss
                    if($imp_session['csv_mvsep']=='none'){
                        $keyvalue = implode('', $fc);
                    }else{
                        $keyvalue = implode($imp_session['csv_mvsep'], $fc);//csv_mvsep - separator
                    }
                    */

                    if($keyvalue=='') {continue;}


                    if(@$pairs[$keyvalue]){  //we already found record for this combination
//disambiguation

                        if(array_key_exists($keyvalue, $tmp_idx_insert)){
                            $imp_session['validation']['recs_insert'][$tmp_idx_insert[$keyvalue]][0] .= (','.$imp_id);
                            $is_insert = true;
                        }elseif(array_key_exists($keyvalue, $tmp_idx_update)) {

                            // make first field (id)  - csv
                            $imp_session['validation']['recs_update'][$tmp_idx_update[$keyvalue]][0] .= (','.$imp_id);

                            $is_update = true;
                        }
                        array_push($ids, $pairs[$keyvalue]);

                    }else{

                        //query to search record ids
                        $search_query = "SELECT rec_ID, rec_Title "
                        ." FROM ".implode(",",$a_from)
                        .SQL_WHERE.implode(SQL_AND,$a_where);

                        $search_stmt = $mysqli->prepare($search_query);
                        //$search_stmt->bind_param('s', $field_value);
                        $search_stmt->bind_result($rec_ID, $rec_Title);

                        //assign parameters for search query
                        //Call the $stmt->bind_param() method with atrguments (string $types, mixed &...$vars)
                        call_user_func_array(array($search_stmt, 'bind_param'), referenceValues($a_tobind));
                        $search_stmt->execute();
                        $disamb = array();
                        while ($search_stmt->fetch()) {
                            //keep pair ID => key value
                            $disamb[$rec_ID] = $rec_Title; //get value from binding
                        }

                        $search_stmt->close();

                        if(count($disamb)>1){
                            $resolved_recid = self::findDisambResolution($keyvalue, $disamb_resolv);
                        }else{
                            $resolved_recid = null;
                        }

                        if(empty($disamb)  || $resolved_recid<0){ //nothing found - insert

                            if($ignore_rectype == 1){ // never create new records when ignoring record type
                                continue;
                            }

                            $new_id = $ind;
                            $ind--;
                            $rec = $row;
                            $rec[0] = $imp_id;
                            $tmp_idx_insert[$keyvalue] = count($imp_session['validation']['recs_insert']);//keep index in rec_insert
                            array_push($imp_session['validation']['recs_insert'], $rec);//group_concat(imp_id), ".implode(",",$sel_query)
                            $is_insert = true;

                        }elseif(count($disamb)==1 || $resolved_recid!=null){
                            //array_search($keyvalue, $disamb_resolv, true)!==false){  @$disamb_resolv[addslashes($keyvalue)]){
                            //either found exact or disamiguation is resolved
                            if($resolved_recid!=null){
                                $rec_ID = $resolved_recid;
                            }

                            //find in rec_update

                            $new_id = $rec_ID;
                            $rec = $row;
                            $rec[0] = $imp_id;

                            //array_unshift($rec, $rec_ID);//add as first element
                            //$tmp_idx_update[$keyvalue] = count($imp_session['validation']['recs_update']);//keep index in rec_update
                            //array_push($imp_session['validation']['recs_update'], $rec);//rec_ID, group_concat(imp_id), ".implode(",",$sel_query)

                            $tmp_idx_update[$keyvalue] = $rec_ID;
                            if(@$imp_session['validation']['recs_update'][$rec_ID]){
                                $imp_session['validation']['recs_update'][$rec_ID][0] .= (','.$imp_id);
                            }else{
                                $imp_session['validation']['recs_update'][$rec_ID] = $rec;
                            }

                            $is_update = true;
                        }else{
                            $new_id = 'Found:'.count($disamb);//Disambiguation!
                            $disambiguation[$keyvalue] = $disamb;
                            $disambiguation_lines[$keyvalue] = $imp_id;
                        }
                        $pairs[$keyvalue] = $new_id;

                        $mapping_keys_values[$new_id] = $keys_values;

                        array_push($ids, $new_id);

                    }

                }//for multivalues

                if($imp_session['csv_mvsep']=='none'){
                    $records[$imp_id] = !empty($ids)?$ids[0]:'';
                }else{
                    $records[$imp_id] = implode($imp_session['csv_mvsep'], $ids);//IDS to be added to import table
                }


            if($is_update) {$cnt_update_rows++;}
            if($is_insert) {$cnt_insert_rows++;}
            if(!$is_update && !$is_insert && $ignore_rectype == 1) {$imp_session['validation']['count_ignore_rows']++;}

        }//while import table

    }
    else{
        self::$system->addError(HEURIST_DB_ERROR, 'Can not query import table '.$import_table, $mysqli->error);
        return false;
    }

    // result of work - counts of records to be inserted, updated
    $imp_session['validation']['count_update'] = count($imp_session['validation']['recs_update']);
    $imp_session['validation']['count_insert'] = count($imp_session['validation']['recs_insert']);
    $imp_session['validation']['count_update_rows'] = $cnt_update_rows;
    $imp_session['validation']['count_insert_rows'] = $cnt_insert_rows;
    $imp_session['validation']['disambiguation'] = $disambiguation;
    $imp_session['validation']['disambiguation_lines'] = $disambiguation_lines;
    $imp_session['validation']['pairs'] = $pairs;     //keyvalues => record id - count number of unique values

    if($multivalue_field_name!=null){
        $imp_session['validation']['mapping_keys_values'] = $mapping_keys_values;
    }

    //MAIN RESULT - ids to be assigned to each record in import table
    $imp_session['validation']['records'] = $records; //imp_id(line#) => list of records ids

    return $imp_session;
}

/**
* MAIN method for first step - finding exisiting /matching records in destination
* Assign record ids to field in import table
* (negative if not found)
*
* since we do match and assign in ONE STEP - first we call findRecordIds
*
* @param mixed $mysqli
* @param mixed $imp_session
* @param mixed $params
* @return mixed
*/
public static function assignRecordIds($params){

    self::initialize();

    //get rectype to import
    $rty_ID = @$params['sa_rectype'];
    $currentSeqIndex = @$params['seq_index'];
    $match_mode = @$params['match_mode'] ?$params['match_mode']: 0;   //by fields 0, by id 1, skip match 2

    if(intval($rty_ID)<1 || !(intval($currentSeqIndex)>=0)){
        self::$system->addError(HEURIST_INVALID_REQUEST, 'Record type value is missing or invalid');
        return false;
    }

    $imp_session = ImportSession::load($params['imp_ID']);

    if( is_bool($imp_session) && $imp_session==false ){
        return false; //error - cannot get import session
    }

    $records = null;
    $pairs = null;
    $disambiguation = array();

    $progress_session_id = @$params['session'];
    mysql__update_progress(null, $progress_session_id, true, '0,0');


    if($match_mode == 0){  //find records by mapping

        $imp_session = self::findRecordIds($imp_session, $params);

        if(is_array($imp_session)){
            $records = $imp_session['validation']['records'];//imp_id(line#) => list of records ids
            $pairs = $imp_session['validation']['pairs'];//keyvalues => record id - count number of unique values
            $disambiguation = $imp_session['validation']['disambiguation'];
        }else{
            mysql__update_progress(null, $progress_session_id, false, 'REMOVE');
            return false; //error
        }

        //keep counts
        if(!@$imp_session['sequence'][$currentSeqIndex]['counts']){
            $imp_session['sequence'][$currentSeqIndex]['counts'] = array();
        }

        if(!empty($disambiguation)){
            mysql__update_progress(null, $progress_session_id, false, 'REMOVE');
            return $imp_session; //It is not possible to proceed because of disambiguation
        }


    }//$match_mode==0

    $import_table = $imp_session['import_table'];

    $mysqli = self::$mysqli;

    $id_fieldname = @$params['idfield'];
    $id_field = null;
    $field_count = count($imp_session['columns']);

    if(!$id_fieldname || $id_fieldname=="null"){
        $id_fieldname = $imp_session['sequence'][$currentSeqIndex]['field'];
        //$rectype = dbs_GetRectypeByID($mysqli, $rty_ID);
        //$id_fieldname = $rectype['rty_Name'].' ID';//not defined - create new identification field
    }
    $index = array_search($id_fieldname, $imp_session['columns']);//find it among existing columns
    if($index!==false){ //this is existing field
        $id_field  = "field_".$index;
        $imp_session['uniqcnt'][$index] = (!isEmptyArray(@$pairs))?count($pairs):$imp_session['reccount'];
    }

    //add new field into import table
    if(!$id_field){

        $is_existing_id_field = false;

        $id_field = "field_".$field_count;


        $query = "SHOW COLUMNS FROM `$import_table` LIKE '$id_field'";
        $res = $mysqli->query($query);
        $row_cnt = $res->num_rows;
        if($res) {$res->close();}
        if(!$row_cnt){ //good name

            $altquery = "alter table ".$import_table." add column ".$id_field." varchar(1000) ";
            if (!$mysqli->query($altquery)) {
                mysql__update_progress(null, $progress_session_id, false, 'REMOVE');
                self::$system->addError(HEURIST_DB_ERROR, 'Cannot alter import session table; cannot add new index field', $mysqli->error);
                return false;
            }else{
                $is_existing_id_field = true;
            }
        }else{
            $is_existing_id_field = true;
        }

        /*
        $altquery = "update ".$import_table." set ".$id_field."=-1 where imp_id>0";
        if (!$mysqli->query($altquery)) {
            self::$system->addError(HEURIST_DB_ERROR, 'Cannot set new index field', $mysqli->error);
            return false;
        }*/

        array_push($imp_session['columns'], $id_fieldname );
        array_push($imp_session['uniqcnt'], (!isEmptyArray(@$pairs))?count($pairs):$imp_session['reccount'] );

        if(@$params['idfield']){
            array_push($imp_session['multivals'], $field_count );//!!!!
        }
    }else{
        $is_existing_id_field = true;
    }


    if($match_mode==2){   //skip matching - all as new

        if($is_existing_id_field){
            //$updquery = "update $import_table set $id_field=NULL where imp_id>0";
            //$mysqli->query($updquery);

            $mysqli->query('SET @my_increment := 0');
            $updquery = "UPDATE $import_table SET $id_field = @my_increment := @my_increment - 1";
            $mysqli->query($updquery);
        }

        $imp_session['validation'] = array(
            "count_update"=>0,
            "count_insert"=>$imp_session['reccount'],
            "count_update_rows"=>0,
            "count_insert_rows"=>$imp_session['reccount'],
            "count_ignore_rows"=>0,
            'disambiguation'=>array()
        );
    }
    elseif(!isEmptyArray($records))
    {
        //reset index field to '' (means no matching - record will be ignored on update/insert)
        $updquery = "update $import_table set $id_field='' where imp_id>0";
        $mysqli->query($updquery);

        //$records - is result of findRecordsIds
        //update ID values in import table - replace id to found
        $tot = count($records);
        $cnt = 0;
        $keep_autocommit = mysql__begin_transaction(self::$mysqli);
        $is_error = null;

        foreach($records as $imp_id => $ids){

            if($ids){

                if(strlen($ids)>1000){
                    self::$system->addError(HEURIST_ERROR, 'Cannot update import table: set ID field. '
                    .'Too many key values. The size of list of keys oversize key field length');
                    self::$mysqli->rollback();
                    if($keep_autocommit===true) {self::$mysqli->autocommit(true);}
                    return;
                }

                //update
                $updquery = "UPDATE `$import_table` SET `$id_field`='$ids' where imp_id=$imp_id";
                if(!$mysqli->query($updquery)){
                    $is_error = $updquery;
                    break;
                }
                $cnt++;
                if($cnt%100==0){
                    mysql__update_progress(null, $progress_session_id, false, $cnt.','.$tot);
                }
                if($cnt % 2000 == 0){
                    self::$mysqli->commit();
                    self::$mysqli->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
                }
            }
        }

        if($is_error){
            mysql__update_progress(null, $progress_session_id, false, 'REMOVE');
            self::$system->addError(HEURIST_DB_ERROR, 'Cannot update import table: set ID field', $mysqli->error.' QUERY:'.$is_error);
            self::$mysqli->rollback();
            if($keep_autocommit===true) {self::$mysqli->autocommit(true);}
            return;
        }else{
            self::$mysqli->commit();
            if($keep_autocommit===true) {self::$mysqli->autocommit(true);}
        }



        // find records to be ignored
        /* they already found in findRecordsIds
        $select_query = "SELECT count(*) FROM ".$import_table.SQL_WHERE.$id_field."=''";
        $cnt_ignored = mysql__select_value($mysqli, $select_query);
        $imp_session['validation']['count_ignore_rows'] = $cnt_ignored;
        */


    }elseif($match_mode==1){
        //find records to insert and update if matching is skipped AND WE USE current key field

        $cnt_query = "SELECT count(DISTINCT `$id_field`) FROM `$import_table`";

        // find records to update
        $select_query = $cnt_query." left join Records on rec_ID=`$id_field` WHERE rec_ID is not null and `$id_field`>0";
        $cnt_update = mysql__select_value($mysqli, $select_query);

        // find records to insert
        $select_query = $cnt_query." WHERE `$id_field`<0";
        $cnt = mysql__select_value($mysqli, $select_query);

        // id field not defined -  it records to insert as well
        $select_query = "SELECT count(*) FROM `$import_table` WHERE `$id_field` IS NULL";
        $cnt2 = mysql__select_value($mysqli, $select_query);

        // record ids for none existing records
        $select_query = $cnt_query." left join Records on rec_ID=`$id_field` WHERE rec_ID is null and `$id_field`>0";
        $cnt3 = mysql__select_value($mysqli, $select_query);

        // insert count
        $cnt_insert = (($cnt>0) ? intval($cnt) : 0) + (($cnt2>0) ? intval($cnt2) : 0) + (($cnt3>0) ? intval($cnt3) : 0);

        // find records to be ignored
        $select_query = "SELECT count(*) FROM ".$import_table.SQL_WHERE.$id_field."=''";
        $cnt_ignore = mysql__select_value($mysqli, $select_query);

        $imp_session['validation'] = array(
            "count_update"=>$cnt_update,
            "count_update_rows"=>$cnt_update,
            "count_insert"=>$cnt_insert,
            "count_insert_rows"=>$cnt_insert,
            "count_ignore_rows"=>$cnt_ignore,
            'disambiguation'=>array()
        );

        //$imp_session['validation']['count_insert'] = $imp_session['reccount'];
    }


    mysql__update_progress(null, $progress_session_id, false, 'REMOVE');

    //define field as index in session
    @$imp_session['indexes'][$id_field] = $rty_ID;

    //to keep mapping for index field
    if(!@$imp_session['sequence'][$currentSeqIndex]['mapping_keys']){
        $imp_session['sequence'][$currentSeqIndex]['mapping_keys'] = array();
    }

    if($match_mode!=2 &&  @$params['mapping']){
        $imp_session['sequence'][$currentSeqIndex]['mapping_keys'] = @$params['mapping'];
        if(@$imp_session['validation']['mapping_keys_values']){ //id - mapping field values - required for multivalue key fields
            $imp_session['sequence'][$currentSeqIndex]['mapping_keys_values'] = $imp_session['validation']['mapping_keys_values'];
        }
    }

    $imp_session['sequence'][$currentSeqIndex]['counts'] = array(
                        $imp_session['validation']['count_update'],      //records to be updated
                    $imp_session['validation']['count_update_rows'], //rows in source
                    $imp_session['validation']['count_insert'],
                    $imp_session['validation']['count_insert_rows'],
                    $imp_session['validation']['count_ignore_rows']);


    $ret_session = $imp_session;
    unset($imp_session['validation']);//save session without validation info

    $res = ImportSession::save( $imp_session );

    if(!is_array($res)){
        self::$system->addError(HEURIST_DB_ERROR, 'Cannot save import session', $res);
        return false;
    }

    return $ret_session;
}


//----------------------------------- VALIDATION --------------------------------------
/**
* 1) Performs mapping validation (required fields, enum, pointers, numeric/date)
* 2) Counts matched (update) and new records
*
* imp_ID
* sa_rectype - record type
* seq_index - sequence
* recid_field
* ignore_insert - ignore new records
* ignore_update - ignore records to be updated
* mapping
*
* @param mixed $mysqli
*/
public static function validateImport($params) {

    self::initialize();

    $progress_session_id = @$params['session'];

    $imp_session = ImportSession::load(@$params['imp_ID']);
    if($imp_session==false){
        return false;
    }

    //add result of validation to session
    $imp_session['validation'] = array(
        "count_update"=>0,
        "count_insert"=>0,       //records to be inserted
        "count_update_rows"=>0,
        "count_insert_rows"=>0,  //row that are source of insertion
        "count_ignore_rows"=>0,
        "count_error"=>0,
        "error"=>array(),
        "count_warning"=>0,
        "warning"=>array(),
        "utm_warning"=>0,
        "geo_invalid"=>array(
            "outOfBounds"=>array(),
            "invalid"=>array()
        )
    );

    //get rectype to import
    $recordType = @$params['sa_rectype'];

    $currentSeqIndex = @$params['seq_index'];
    $sequence = null;
    if($currentSeqIndex>=0 && @$imp_session['sequence'] && is_array(@$imp_session['sequence'][$currentSeqIndex])){
        $sequence = $imp_session['sequence'][$currentSeqIndex];
    }

    if(intval($recordType)<1){
        self::$system->addError(HEURIST_INVALID_REQUEST, 'Record type not defined');
        return false;
    }

    $import_table = $imp_session['import_table'];
    $id_field = @$params['recid_field'];//record ID field is always defined explicitly
    $ignore_insert = (@$params['ignore_insert']==1);//ignore new records
    $ignore_update = (@$params['ignore_update']==1);//ignore update records

    $cnt_update_rows = 0;
    $cnt_insert_rows = 0;

    $mysqli = self::$system->getMysqli();

    if($id_field!=null){
        $id_field = $mysqli->real_escape_string($id_field);
    }

    //get field mapping and selection query from _REQUEST(params)
    if(@$params['mapping']){    //new way
        $mapping_params = @$params['mapping'];

        $mapping = array();// fieldtype => fieldname in import table
        $sel_query = array();

        if(!isEmptyArray($mapping_params)){
            foreach ($mapping_params as $index => $field_type) {

                $field_name = "field_".$index;

                if(@$mapping[$field_type]){
                    array_push($mapping[$field_type], $field_name);
                }else{
                    $mapping[$field_type] = array($field_name);
                }

                $imp_session['validation']['mapped_fields'][$field_name] = $field_type;

                //all mapped fields - they will be used in validation query
                array_push($sel_query, $field_name);
            }
        }else{
            self::$system->addError(HEURIST_INVALID_REQUEST, 'Mapping not defined');
            return false;
        }
    }
    else{

        $mapping = array();// fieldtype ID => fieldname in import table
        $mapped_fields = array();
        $sel_query = array();
        foreach ($params as $key => $field_type) {
            if(strpos($key, "sa_dt_")===0 && $field_type){
                //get index
                $index = substr($key,6);
                $field_name = "field_".$index;

                //all mapped fields - they will be used in validation query
                array_push($sel_query, $field_name);
                if(@$mapping[$field_type]){
                    array_push($mapping[$field_type], $field_name);
                }else{
                    $mapping[$field_type] = array($field_name);
                }


                $mapped_fields[$field_name] = $field_type;
            }
        }
        if(empty($sel_query)){
            self::$system->addError(HEURIST_INVALID_REQUEST, 'Mapping not defined');
            return false;
        }

        $imp_session['validation']['mapped_fields'] = $mapped_fields;
    }


    mysql__update_progress(null, $progress_session_id, true, '1,7,index fields validation');

    // calculate the number of records to insert, update and insert with existing ids
    // @todo - it has been implemented for non-multivalue indexes only
    if(!$id_field){ //ID field not defined - all records will be inserted
        if(!$ignore_insert){
            $imp_session['validation']["count_insert"] = $imp_session['reccount'];
            $imp_session['validation']["count_insert_rows"] = $imp_session['reccount'];//all rows will be imported as new records
            $select_query = self::composeQuery($sel_fields, $import_table)." LIMIT 5000";//for peview only
            $imp_session['validation']['recs_insert'] = mysql__select_all($mysqli, $select_query);
        }

    }else{

        $cnt_recs_insert_nonexist_id = 0;

        $id_field = preg_replace(REGEX_ALPHANUM, "", $id_field);//for snyk

        // validate selected record ID field
        // in case id field is not created on match step (it is from original set of columns)
        // we have to verify that its values are valid
        if(!@$imp_session['indexes'][$id_field]){

            //find recid with different rectype
            $fields = implode("`,`",$sel_query);
            $query = "SELECT imp_id, `$fields`, `$id_field` FROM `$import_table` "
            ." LEFT JOIN Records on rec_ID=`$id_field`"
            ." WHERE rec_RecTypeID<>".$recordType;

            // TPDO: I'm not sure whether message below has been correctly interpreted
            $wrong_records = self::getWrongRecords($query, $imp_session,
                "Your input data contain record IDs in the selected ID column for existing records which are not numeric IDs. ".
                "The import cannot proceed until this is corrected.","Incorrect record types", $id_field);

            if(!isEmptyArray($wrong_records)) {
                $wrong_records['validation']['mapped_fields'][$id_field] = 'id';
                $imp_session = $wrong_records;
            }elseif($wrong_records) { //error
                self::$system->addError(HEURIST_ERROR, $wrong_records);
                return false;
            }

            if(!$ignore_insert){      //WARNING - it ignores possible multivalue index field
                //find record ID that do not exist in HDB - to insert
                $query = "select count(imp_id) "
                ." from `$import_table` "
                ." left join Records on rec_ID=`$id_field` "
                ." where `$id_field`>0 and rec_ID is null";
                $cnt = mysql__select_value($mysqli, $query);
                if($cnt>0){
                    $cnt_recs_insert_nonexist_id = $cnt;
                }
            }
        }

        // find records to update
        if(!$ignore_update){
            $select_query = "SELECT count(DISTINCT `$id_field`) FROM `$import_table` "
            ." left join Records on rec_ID=`$id_field` WHERE rec_ID is not null and `$id_field`>0";
            $cnt = mysql__select_value($mysqli, $select_query);
            if($cnt>0){

                    $imp_session['validation']['count_update'] = $cnt;
                    $imp_session['validation']['count_update_rows'] = $cnt;
                    //find first 5000 records to display
                    $select_query = "SELECT `$id_field`, imp_id, `".implode("`,`",$sel_query)
                    ."` FROM `$import_table` "
                    ." left join Records on rec_ID=`$id_field` "
                    ." WHERE rec_ID is not null and `$id_field`>0"
                    ." ORDER BY `$id_field` LIMIT 5000";//for preview only
                    $imp_session['validation']['recs_update'] = mysql__select_all($mysqli, $select_query);//for preview only

            }elseif($cnt==null){
                self::$system->addError(HEURIST_DB_ERROR,
                        'SQL error: Cannot execute query to calculate number of records to be updated!', $mysqli->error);
                return false;
            }
        }

        if(!$ignore_insert){

            // find records to insert
            $select_query = "SELECT count(DISTINCT `$id_field`) FROM `$import_table` WHERE `$id_field`<0";//$id_field." is null OR ".
            $cnt = mysql__select_value($mysqli, $select_query);
            $cnt = ($cnt>0)?intval($cnt):0;

            $select_query = "SELECT count(*) FROM `$import_table` WHERE `$id_field` IS NULL";
            $cnt2 = mysql__select_value($mysqli, $select_query);
            $cnt = $cnt + ($cnt2>0?intval($cnt2):0);

            if( $cnt>0 ){
                    $imp_session['validation']['count_insert'] = $cnt;
                    $imp_session['validation']['count_insert_rows'] = $cnt;

                    //find first 5000 records to preview display
                    $select_query = self::composeQuery($sel_query, $import_table)
                            ." WHERE `$id_field`<0 or `$id_field` IS NULL LIMIT 5000";//for preview only
                    $imp_session['validation']['recs_insert'] = mysql__select_all($mysqli, $select_query);
            }
        }
        //additional query for non-existing IDs
        if($cnt_recs_insert_nonexist_id>0){

            $imp_session['validation']['count_insert_nonexist_id'] = $cnt_recs_insert_nonexist_id;
            $imp_session['validation']['count_insert'] = $imp_session['validation']['count_insert']+$cnt_recs_insert_nonexist_id;
            $imp_session['validation']['count_insert_rows'] = $imp_session['validation']['count_insert'];

            $select_query = self::composeQuery($sel_query, $import_table)
            ." LEFT JOIN Records on rec_ID=`$id_field` "
            ." WHERE `$id_field`>0 and rec_ID is null LIMIT 5000";//for preview only
            $res = mysql__select_all($mysqli, $select_query);
            if(!isEmptyArray($res)){
                if(@$imp_session['validation']['recs_insert']){
                    $imp_session['validation']['recs_insert'] = array_merge($imp_session['validation']['recs_insert'], $res);
                }else{
                    $imp_session['validation']['recs_insert'] = $res;
                }
            }
        }

    }//id_field defined


    // fill array with field in import table to be validated
    $recStruc = dbs_GetRectypeStructures(self::$system, array($recordType), 2);
    $recStruc = $recStruc['typedefs'];
    $idx_reqtype = $recStruc['dtFieldNamesToIndex']['rst_RequirementType'];
    $idx_fieldtype = $recStruc['dtFieldNamesToIndex']['dty_Type'];

    $dt_mapping = array();//mapping to detail type ID

    $missed = array();
    $query_reqs = array();//fieldnames from import table
    $query_reqs_where = array();//where clause for validation of required fields

    $query_enum = array();
    $query_enum_join = array();
    $query_enum_where = array();

    $query_res = array();
    $query_res_join = array();
    $query_res_where = array();

    $query_num = array();
    $query_num_nam = array();
    $query_num_where = array();

    $query_date = array();
    $query_date_nam = array();
    $query_date_where = array();

    $numeric_regex = "'^([+-]?[0-9]+\.*)+'";

    if($sequence){
        $mapping_prev_session = @$sequence['mapping_keys'];//OR mapping_flds???
    }else{
        $mapping_prev_session = @$imp_session['mapping'];
    }


    $geo_fields = null;


    //loop for all fields in record type structure
    foreach ($recStruc[$recordType]['dtFields'] as $ft_id => $ft_vals) {

        //find among mappings
        $field_name = @$mapping[$ft_id];//there may be several field_xx in csv to be imported
        if(!$field_name){

            if(is_array($mapping_prev_session)){
                $field_name = array_search($recordType.".".$ft_id, $mapping_prev_session, true);//from previous session

                if($field_name && !is_array($field_name)) {$field_name = array($field_name);}
            }
        }

        if(!$field_name && $ft_vals[$idx_fieldtype] == "geo"){
            //specific mapping for geo fields
            //it may be mapped to itself or mapped to two fields - lat and long

            $field_name1 = @$mapping[$ft_id."_lat"];
            $field_name2 = @$mapping[$ft_id."_long"];
            if(!$field_name1 && !$field_name2 && is_array($mapping_prev_session)){
                $field_name1 = array_search($recordType.".".$ft_id."_lat", $mapping_prev_session, true);
                $field_name2 = array_search($recordType.".".$ft_id."_long", $mapping_prev_session, true);
            }

            if(!isEmptyArray($field_name1)){
                $field_name1 = $field_name1[0];
            }
            if(!isEmptyArray($field_name2)){
                $field_name2 = $field_name2[0];
            }
            /*
            if($field_name1){
                $field_name1 = preg_replace(REGEX_ALPHANUM, "", $field_name1);//for snyk
            }
            if($field_name2){
                $field_name2 = preg_replace(REGEX_ALPHANUM, "", $field_name2);//for snyk
            }*/

            //search for empty required fields in import table
            if($ft_vals[$idx_reqtype] == "required"){
                if(!$field_name1 || !$field_name2){
                    array_push($missed, $ft_vals[0]);
                }else{
                    array_push($query_reqs, $field_name1);
                    array_push($query_reqs, $field_name2);
                    $field_name1 = preg_replace(REGEX_ALPHANUM, "", $field_name1);//for snyk
                    $field_name2 = preg_replace(REGEX_ALPHANUM, "", $field_name2);//for snyk
                    array_push($query_reqs_where, "`$field_name1` is null or `$field_name1`=''");
                    array_push($query_reqs_where, "`$field_name2` is null or `$field_name2`=''");
                }
            }
            if($field_name1 && $field_name2){
                $field_name1 = preg_replace(REGEX_ALPHANUM, "", $field_name1);//for snyk
                $field_name2 = preg_replace(REGEX_ALPHANUM, "", $field_name2);//for snyk
                array_push($query_num, $field_name1);
                array_push($query_num_where, "(NOT(`$field_name1` is null or `$field_name1`='' or `$field_name1`='NULL') and NOT(`$field_name1` REGEXP ".$numeric_regex."))");
                array_push($query_num, $field_name2);
                array_push($query_num_where, "(NOT(`$field_name2` is null or `$field_name2`='' or `$field_name2`='NULL') and NOT(`$field_name2` REGEXP ".$numeric_regex."))");

                //if UTM zone is not specified need validate for possible UTM values
                // northing, easting
                $geo_fields = array($field_name1,$field_name2);
            }

        }elseif($ft_vals[$idx_fieldtype] == "geo"){

            //for geo fields take first
            $geo_fields = array(preg_replace(REGEX_ALPHANUM, '', $field_name[0]));//WKT field

        }elseif($ft_vals[$idx_reqtype] == "required"){
            if(!$field_name){ //required field is not mapped - error
                //$ft_vals[$idx_fieldtype] == "file" ||
                if(!($ft_vals[$idx_fieldtype] == "resource")){ //except file and resource (record pointer)
                    array_push($missed, $ft_vals[0]);
                }

            }else{
                //not empty
                $squery = array();
                foreach($field_name as $f_name){

                    $f_name = preg_replace(REGEX_ALPHANUM, "", $f_name);//for snyk

                    if($ft_vals[$idx_fieldtype] == "resource"){ //|| $ft_vals[$idx_fieldtype] == "enum"){
                        //$squery = "not (".$field_name.">0)";
                        $squery[] = "`$f_name`>0";
                    }else{
                        $squery[] = "`$f_name` is null or `$f_name`=''";
                    }
                    array_push($query_reqs, $field_name);
                }
                if($ft_vals[$idx_fieldtype] == "resource"){
                    $squery = 'not ('.implode(' OR ',$squery).')';
                }else{
                    $squery = ' ('.implode(' OR ',$squery).')';
                }

                array_push($query_reqs_where, $squery);

            }
        }

        if($field_name){  //mapping exists

            foreach($field_name as $f_name){
                $dt_mapping[$f_name] = $ft_id;
            }

            if($ft_vals[$idx_fieldtype] == "enum" ||  $ft_vals[$idx_fieldtype] == "relationtype") {

                foreach($field_name as $f_name){

                    $f_name = preg_replace(REGEX_ALPHANUM, "", $f_name);//for snyk

                    array_push($query_enum, $f_name);
                    $trm1 = "trm".count($query_enum);
                    array_push($query_enum_join,
                        " defTerms $trm1 on ($trm1.trm_Code=`$f_name` OR "
                        ." $trm1.trm_Label=`$f_name` OR $trm1.trm_Label=SUBSTRING_INDEX(`$f_name`,'.',-1))"
                        );
                    array_push($query_enum_where, "(".$trm1.".trm_Label is null and not (`$f_name` is null or `$f_name`='' or `$f_name`='NULL'))");
                }

            }elseif($ft_vals[$idx_fieldtype] == "resource"){

                $f_name = $field_name[0];//@todo for multimapping as for enum

                $f_name = preg_replace(REGEX_ALPHANUM, "", $f_name);//for snyk

                array_push($query_res, $f_name);
                $trm1 = "rec".count($query_res);
                array_push($query_res_join, " Records $trm1 on $trm1.rec_ID=$f_name ");
                array_push($query_res_where, "(".$trm1.".rec_ID is null and not ($f_name is null or $f_name='' or $f_name='NULL'))");

            }elseif($ft_vals[$idx_fieldtype] == "float" ||  $ft_vals[$idx_fieldtype] == "integer") {

                $f_name = $field_name[0];//@todo for multimapping as for enum
                $f_name = preg_replace(REGEX_ALPHANUM, "", $f_name);//for snyk

                array_push($query_num, $f_name);
                array_push($query_num_where, "(NOT($f_name is null or $f_name='' or $f_name='NULL') and NOT($f_name REGEXP ".$numeric_regex."))");



            }elseif($ft_vals[$idx_fieldtype] == "date" ||  $ft_vals[$idx_fieldtype] == "year") {

                $f_name = $field_name[0];//@todo for multimapping as for enum
                $f_name = preg_replace(REGEX_ALPHANUM, "", $f_name);//for snyk

                array_push($query_date, $f_name);
                if($ft_vals[$idx_fieldtype] == "year"){
                    array_push($query_date_where, "(concat('',$f_name * 1) != $f_name "
                        ."and not ($f_name is null or $f_name='' or $f_name='NULL'))");
                }else{
                    array_push($query_date_where, "(str_to_date($f_name, '%Y-%m-%d %H:%i:%s') is null "
                        ."and str_to_date($f_name, '%d/%m/%Y') is null "
                        ."and str_to_date($f_name, '%d-%m-%Y') is null "
                        ."and not ($f_name is null or $f_name='' or $f_name='NULL'))");
                }

            }

        }
    }

    //ignore_required

    //1. Verify that all required field are mapped  =====================================================
    if(!empty($missed)  &&
        ($imp_session['validation']['count_insert']>0   // there are records to be inserted
            //  || ($params['sa_upd']==2 && $params['sa_upd2']==1)   // Delete existing if no new data supplied for record
        )){
            /*
            self::$system->addError(HEURIST_ERROR, 'The following fields are required fields. You will need to map
them to incoming data before you can import new records:<br><br>'.implode(",", $missed));
            return false;
            */
            $imp_session['validation']['missed_required_fields_map'] = $missed;
    }

    if($id_field){ //validate only for defined records IDs
        $id_field = preg_replace(REGEX_ALPHANUM, "", $id_field);//for snyk
        if($ignore_insert){
            $only_for_specified_id = " (`$id_field` > 0) AND ";
        }else{
            if($ignore_update){
                $only_for_specified_id = " (NOT(`$id_field` > 0 OR `$id_field`='')) AND ";
            }else{
                $only_for_specified_id = " (`$id_field`!='') AND "; //otherwise it does not for skip matching " (NOT(".$id_field." is null OR ".$id_field."='')) AND "
            }
        }
    }else{
        $only_for_specified_id = '';
    }

    //2. In DB: Verify that all required fields have values =============================================
    mysql__update_progress(null, $progress_session_id, false, '2,7,checking that required fields have values');

    $k=0;
    foreach ($query_reqs as $field){
        $query = self::composeQuery($sel_query, $import_table)
        .SQL_WHERE.$only_for_specified_id;
        if(@$query_reqs_where[$k]){
            $query = $query . ' ('.$query_reqs_where[$k].')'; // implode(" or ",$query_reqs_where);
        }else{
            $query = $query . ' 1';
        }

        $k++;

        $field = preg_replace(REGEX_ALPHANUM, "", $field); //for snyk

        $wrong_records = self::getWrongRecords($query, $imp_session,
            "This field is required. It is recommended that a value must be supplied for every record. "
            ."You can find and correct these values using Verify > Verify integrity",
            "Missing Values", $field, 'warning');
        if(is_array($wrong_records)) {

            $cnt = count(@$imp_session['validation']['warning']);//was
            //@$imp_session['validation']['count_warning']
            $imp_session = $wrong_records;
            $imp_session['validation']['missed_required'] = true;

            //allow add records with empty required field - 2017/03/12
            if(false && count(@$imp_session['validation']['recs_insert'])>0 ){
                //--remove from array to be inserted - wrong records with missed required field
                $cnt2 = count(@$imp_session['validation']['warning']);//now
                if($cnt2>$cnt){
                    $wrong_recs_ids = $imp_session['validation']['warning'][$cnt]['recs_error_ids'];
                    if(!empty($wrong_recs_ids)){
                        $badrecs = array();
                        foreach($imp_session['validation']['recs_insert'] as $idx=>$flds){
                            if(in_array($flds[0], $wrong_recs_ids)){
                                array_push($badrecs, $idx);
                            }
                        }
                        $imp_session['validation']['recs_insert'] = array_diff_key($imp_session['validation']['recs_insert'],
                                    array_flip($badrecs) );
                        $imp_session['validation']["count_insert"] = count($imp_session['validation']['recs_insert']);
                    }
                }
            }


       }elseif($wrong_records) {
            self::$system->addError(HEURIST_ERROR, $wrong_records);
            return false;
       }
    }


    //3. In DB: Verify that enumeration fields have correct values =====================================
    mysql__update_progress(null, $progress_session_id, false, '3,7,validation of enumeration fields');

    if(!@$imp_session['csv_enclosure']){
        $imp_session['csv_enclosure'] = @$params['csv_enclosure']?$params['csv_enclosure']:'"';
    }
    if(!@$imp_session['csv_mvsep']){
        $imp_session['csv_mvsep'] = @$params['csv_mvsep']?$params['csv_mvsep']:'|';
    }


    $hwv = " have incorrect values";
    $k=0;
    foreach ($query_enum as $field){

        if(true || in_array(intval(substr($field,6)), $imp_session['multivals'])){ //this is multivalue field - perform special validation

            $query = self::composeQuery($sel_query, $import_table).SQL_WHERE.$only_for_specified_id." 1";

            $idx = array_search($field, $sel_query)+1;

            $wrong_records = self::validateEnumerations($query, $imp_session, $field, $dt_mapping[$field],
                $idx, $recStruc, $recordType,
                "Term list values read must match existing term labels OR standard codes defined for the field. Periods may be used as indicators of hierarchy (option in following screen if periods detected)", "new terms", $progress_session_id);

        }else{

            $query = self::composeQuery($sel_query, $import_table)
                ." left join ".$query_enum_join[$k]   //implode(" left join ", $query_enum_join)
                .SQL_WHERE.$only_for_specified_id;

            if(@$query_enum_where[$k]){
                $query = $query . ' ('.$query_enum_where[$k].')'; // implode(" or ",$query_enum_where)
            }else{
                $query = $query . ' 1';
            }

            $wrong_records = self::getWrongRecords($query, $imp_session,
                "Term list values read must match existing term labels OR standard codes defined for the field. Periods may be used as indicators of hierarchy (option in following screen if periods detected)",
                "Invalid Terms", $field);
        }

        $k++;

        if(is_array($wrong_records)) {
            $imp_session = $wrong_records;
        }elseif($wrong_records) {
            self::$system->addError(HEURIST_ERROR, $wrong_records);
            return false;
        }
    }

    //4. In DB: Verify resource (record pointer) fields ==================================================
    mysql__update_progress(null, $progress_session_id, false, '4,7,'.MSG_VALIDATION_2);

    $k=0;
    foreach ($query_res as $field){

         if(true || in_array(intval(substr($field,6)), $imp_session['multivals'])){ //this is multivalue field - perform special validation

            $query = self::composeQuery($sel_query, $import_table).SQL_WHERE.$only_for_specified_id." 1";

            $idx = array_search($field, $sel_query)+1;

            $wrong_records = self::validateResourcePointers($mysqli, $query, $imp_session,
                                        $field, $dt_mapping[$field], $idx, $recStruc, $recordType, $progress_session_id);

        }else{
            $query = self::composeQuery($sel_query, $import_table)." left join ".$query_res_join[$k]  //implode(" left join ", $query_res_join)
            .SQL_WHERE.$only_for_specified_id;

            if(@$query_res_where[$k]){
                $query = $query . ' ('.$query_res_where[$k].')'; // implode(" or ",$query_res_where)
            }else{
                $query = $query . ' 1';
            }


            $wrong_records = self::getWrongRecords($query, $imp_session,
                "Record pointer field values must reference an existing record in the database",
                "Invalid Pointers", $field);
        }

        $k++;

        //"Fields mapped as resources(pointers)".$hwv,
        if(is_array($wrong_records)) {
            $imp_session = $wrong_records;
        }elseif($wrong_records) {
            self::$system->addError(HEURIST_ERROR, $wrong_records);
            return false;
        }
    }

    //5. Verify numeric fields
    mysql__update_progress(null, $progress_session_id, false, '5,7,'.MSG_VALIDATION_1);

    $k=0;
    foreach ($query_num as $field){

        if(in_array(intval(substr($field,6)), $imp_session['multivals'])){ //this is multivalue field - perform special validation

            $query = self::composeQuery($sel_query, $import_table).SQL_WHERE.$only_for_specified_id." 1";

            $idx = array_search($field, $sel_query)+1;

            $wrong_records = self::validateNumericField($mysqli, $query, $imp_session, $field, $idx, 'warning', $progress_session_id);

        }else{
            $query = self::composeQuery($sel_query, $import_table).SQL_WHERE.$only_for_specified_id;

            if(@$query_num_where[$k]){
                $query = $query . '('.$query_num_where[$k].')';
            }else{
                $query = $query . ' 1';
            }


            $wrong_records = self::getWrongRecords($query, $imp_session,
                "Numeric fields must be pure numbers, they cannot include alphabetic characters or punctuation",
                "Invalid Numerics", $field, 'warning');
        }

        $k++;

        // "Fields mapped as numeric".$hwv,
        if(is_array($wrong_records)) {
            $imp_session = $wrong_records;
        }elseif($wrong_records) {
            self::$system->addError(HEURIST_ERROR, $wrong_records);
            return false;
        }
    }

    //6. Verify datetime fields
    mysql__update_progress(null, $progress_session_id, false, '6,7,validation of datetime fields');

    $k=0;
    foreach ($query_date as $field){

        if(true || in_array(intval(substr($field,6)), $imp_session['multivals'])){ //this is multivalue field - perform special validation

            $query = self::composeQuery($sel_query, $import_table).SQL_WHERE.$only_for_specified_id." 1";

            $idx = array_search($field, $sel_query)+1;

            $wrong_records = self::validateDateField($query, $imp_session, $field, $idx, 'warning');

        }else{
            $query = self::composeQuery($sel_query, $import_table).SQL_WHERE.$only_for_specified_id;

            if(@$query_date_where[$k]){
                $query = $query . '('.$query_date_where[$k].')';
            }else{
                $query = $query . ' 1';
            }

            $wrong_records = self::getWrongRecords($query, $imp_session,
                "Date values must be in dd-mm-yyyy, dd/mm/yyyy or yyyy-mm-dd formats",
                "Invalid Dates", $field, 'warning');

        }

        $k++;
        //"Fields mapped as date".$hwv,
        if(is_array($wrong_records)) {
            $imp_session = $wrong_records;
        }elseif($wrong_records) {
            self::$system->addError(HEURIST_ERROR, $wrong_records);
            return false;
        }
    }

    //7. Verify geo fields, TODO: Verify geo fields for UTM
    if(!isEmptyArray($geo_fields)){

        foreach($geo_fields as $k=>$fld){ //for snyk
            $geo_fields[$k] = preg_replace(REGEX_ALPHANUM, "", $fld);
        }

        // northing, easting
        $query = "select `".implode('`,`',$geo_fields)."`, imp_ID from `$import_table` ";

        if(count($geo_fields)==1){
            $query = $query . ' WHERE `'.$geo_fields[0].'` > ""';
        }else{
            $query = $query . ' LIMIT 5';
        }

        $res = $mysqli->query($query);
        $allInteger = true;
        $allOutWGS = true;
        if($res){
            if(count($geo_fields)==1){ //WKT field

                while ($row = $res->fetch_row()){

                    $imp_id = $row[count($row)-1];
                    $values = self::getMultiValues($row[0], $imp_session['csv_enclosure'], $imp_session['csv_mvsep']);

                    foreach($values as $wkt){

                        if(!preg_match('/\d/', $wkt) && !in_array($imp_id, $imp_session['validation']['geo_invalid']['invalid'])){
                            array_push($imp_session['validation']['geo_invalid']['invalid'], $imp_id);
                            continue 2;
                        }

                        $allOutWGS = true;
                        $valid_geo = false;

                        try{

                            $geom = geoPHP::load($wkt, 'wkt');
                            if($geom!=null && !$geom->isEmpty()){
                                $bbox = $geom->getBBox();
                                $allOutWGS = (abs($bbox['minx'])<=180) && (abs($bbox['miny'])<=90)
                                          && (abs($bbox['maxx'])<=180) && (abs($bbox['maxy'])<=90);
                                if (!$allOutWGS && !in_array($imp_id, $imp_session['validation']['geo_invalid']['outOfBounds'])){
                                    array_push($imp_session['validation']['geo_invalid']['outOfBounds'], $imp_id);
                                }
                            }else{
                                // Attempt to convert to POINT(x,y)
                                $valid_geo = self::validateGeoField($wkt, $imp_id, $import_table, $geo_fields[0]);

                                if(!$valid_geo && !in_array($imp_id, $imp_session['validation']['geo_invalid']['invalid'])){
                                    array_push($imp_session['validation']['geo_invalid']['invalid'], $imp_id);
                                }else{

                                    $geom = geoPHP::load($valid_geo, 'wkt');
                                    if($geom!=null && !$geom->isEmpty()){
                                        $bbox = $geom->getBBox();
                                        $allOutWGS = (abs($bbox['minx'])<=180) && (abs($bbox['miny'])<=90)
                                                  && (abs($bbox['maxx'])<=180) && (abs($bbox['maxy'])<=90);
                                        if (!$allOutWGS && !in_array($imp_id, $imp_session['validation']['geo_invalid']['outOfBounds'])){
                                            array_push($imp_session['validation']['geo_invalid']['outOfBounds'], $imp_id);
                                        }
                                    }
                                }
                            }

                        } catch (Exception $e){
                            // Attempt to convert to POINT(x,y)
                            $valid_geo = self::validateGeoField($wkt, $imp_id, $import_table, $geo_fields[0]);

                            if(!$valid_geo && !in_array($imp_id, $imp_session['validation']['geo_invalid']['invalid'])){
                                array_push($imp_session['validation']['geo_invalid']['invalid'], $imp_id);
                            }else{

                                $geom = geoPHP::load($valid_geo, 'wkt');
                                if($geom!=null && !$geom->isEmpty()){
                                    $bbox = $geom->getBBox();
                                    $allOutWGS = (abs($bbox['minx'])<=180) && (abs($bbox['miny'])<=90)
                                              && (abs($bbox['maxx'])<=180) && (abs($bbox['maxy'])<=90);
                                    if (!$allOutWGS && !in_array($imp_id, $imp_session['validation']['geo_invalid']['outOfBounds'])){
                                        array_push($imp_session['validation']['geo_invalid']['outOfBounds'], $imp_id);
                                    }
                                }
                            }
                        }

                        if(!$allOutWGS){
                            $allInteger = $allOutWGS;
                        }
                    }
                }
            }else{ //lat long fields
                while ($row = $res->fetch_row()){
                    $northing = (float)$row[0];
                    $easting = (float)$row[1];

                    $allInteger = $allInteger && ($northing==round($northing)) && ($easting==round($easting));
                    $allOutWGS = $allOutWGS && (abs($easting)>180) && (abs($northing)>90);
                    if (!($allOutWGS && $allInteger)) {break;}
                }
            }

            if($allInteger || $allOutWGS){
                $imp_session['validation']['utm_warning'] = 1;
            }
            $res->close();
        }
    }

    mysql__update_progress(null, $progress_session_id, false, 'REMOVE');

    return $imp_session;
}


/**
* execute validation query and fill session array with validation results
*
* returns string in case of error and array if success
*
* @param mixed $mysqli
* @param mixed $query
* @param mixed $message
* @param mixed $imp_session
* @param mixed $fields_checked
* @param mixed $type  error or warning
*/
private static function getWrongRecords($query, $imp_session, $message, $short_message, $fields_checked, $type='error'){

    $mysqli = self::$mysqli;

    //.((true || $type='error')?'':' LIMIT 5000')
    $res = $mysqli->query($query);//check all if its critical
    if($res){
        $wrong_records = array();
        $wrong_records_ids = array();
        while ($row = $res->fetch_row()){
            array_push($wrong_records, $row);
            array_push($wrong_records_ids, intval($row[0]));
        }
        $res->close();
        $cnt_error = count($wrong_records);
        if($cnt_error>0){
            $error = array();
            $error["count_".$type] = $cnt_error;
            $error["recs_error"] = $wrong_records; //array_slice($wrong_records,0,1000);//imp_id, fields
            $error["recs_error_ids"] = $wrong_records_ids;
            $error["field_checked"] = $fields_checked;
            $error["err_message"] = $message;
            $error["short_message"] = $short_message;

            $imp_session['validation']['count_'.$type] = $imp_session['validation']['count_'.$type]+$cnt_error;
            array_push($imp_session['validation'][$type], $error);

            return $imp_session;
        }

    }else{
        return ERR_VALIDATION_QUERY.$query;
    }
    return null;
}


/**
* validate values for field that is matched to enumeration fieldtype
*
* @param mixed $mysqli
* @param mixed $query  - query to retrieve values from import table
* @param mixed $imp_session
* @param mixed $fields_checked - name of field to be verified
* @param mixed $dt_id - mapped detail type ID
* @param mixed $field_idx - index of validation field in query result (to get value)
* @param mixed $recStruc - record type structure
* @param mixed $message - error message
* @param mixed $short_message
*/
private static function validateEnumerations($query, $imp_session, $fields_checked, $dt_id, $field_idx, $recStruc, $recordType,
            $message, $short_message, $progress_session_id){

    $mysqli = self::$mysqli;

    $dt_def = $recStruc[$recordType]['dtFields'][$dt_id];

    $idx_fieldtype = $recStruc['dtFieldNamesToIndex']['dty_Type'];
    $idx_term_tree = $recStruc['dtFieldNamesToIndex']['rst_FilteredJsonTermIDTree'];
    $idx_term_nosel = $recStruc['dtFieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];

    $dt_type = $dt_def[$idx_fieldtype];//for domain

    $res = $mysqli->query( $query );

    if($res){

        $cnt = 0;
        $tot_count = $imp_session['reccount']>0?$imp_session['reccount']:1000;
        if($tot_count>4000){
            mysql__update_progress(null, $progress_session_id, false, '0,'.$tot_count.',validation of enumeration fields');
        }

        $wrong_records = array();
        $wrong_values = array();

        $cc = 0;

        while ($row = $res->fetch_row()){

            $is_error = false;
            $newvalue = array();
            $values = self::getMultiValues($row[$field_idx], $imp_session['csv_enclosure'], $imp_session['csv_mvsep']);
            foreach($values as $idx=>$r_value){
                $r_value2 = trim_lower_accent($r_value);

                if($r_value2!='' && $r_value2!='null'){

                    $is_termid = false;
                    if(ctype_digit($r_value2)){ //value is numeric try to compare with trm_ID
                        $is_termid = VerifyValue::isValidTerm( $dt_def[$idx_term_tree], $dt_def[$idx_term_nosel], $r_value2, $dt_id);
                    }

                    if($is_termid){
                        $term_id = $r_value;
                    }else{
                        //strip accents on both sides
                        $term_id = VerifyValue::isValidTermLabel($dt_def[$idx_term_tree], $dt_def[$idx_term_nosel], $r_value2, $dt_id, true );

                        if(!$term_id){
                            $term_id = VerifyValue::isValidTermCode($dt_def[$idx_term_tree], $dt_def[$idx_term_nosel], $r_value2, $dt_id );
                        }
                    }

                    if (!$term_id)
                    {//not found
                        $is_error = true;
                        array_push($newvalue, "<span style=\"color:red\">$r_value</span>");
                        if(array_search($r_value, $wrong_values)===false){
                                array_push($wrong_values, $r_value);
                        }
                    }else{
                        array_push($newvalue, $r_value);
                    }
                }
            }

            if($is_error){
                $row[$field_idx] = implode($imp_session['csv_mvsep'], $newvalue);
                array_push($wrong_records, $row);
            }

            $cnt++;
            if($tot_count>4000 && $cnt%2000==0){
                mysql__update_progress(null, $progress_session_id, false, $cnt.','.$tot_count.','.MSG_VALIDATION_2);

            }
        }
        $res->close();
        $cnt_error = count($wrong_records);
        if($cnt_error>0){
            $error = array();
            $error["count_error"] = $cnt_error;
            $error["recs_error"] = array_slice($wrong_records,0,1000);
            $error["values_error"] = array_slice($wrong_values,0,1000);
            $error["field_checked"] = $fields_checked;
            $error["err_message"] = $message;
            $error["short_message"] = $short_message;

            $imp_session['validation']['count_error'] = $imp_session['validation']['count_error']+$cnt_error;
            array_push($imp_session['validation']['error'], $error);

            return $imp_session;
        }

    }else{
        return ERR_VALIDATION_QUERY.$query;
    }
    return null;
}


/**
* put your comment there...
*
* @param mixed $mysqli
* @param mixed $query
* @param mixed $imp_session
* @param mixed $fields_checked - name of field to be verified
* @param mixed $dt_id - mapped detail type ID
* @param mixed $field_idx - index of validation field in query result (to get value)
* @param mixed $recStruc - record type structure
*/
private static function validateResourcePointers($mysqli, $query, $imp_session,
                                        $fields_checked, $dt_id, $field_idx, $recStruc, $recordType, $progress_session_id){

    $dt_def = $recStruc[$recordType]['dtFields'][$dt_id];
    $idx_pointer_types = $recStruc['dtFieldNamesToIndex']['rst_PtrFilteredIDs'];

    $res = $mysqli->query($query);

    if($res){

        $cnt = 0;
        $tot_count = $imp_session['reccount']>0?$imp_session['reccount']:1000;
        if($tot_count>4000){
            mysql__update_progress(null, $progress_session_id, false, '0,'.$tot_count.','.MSG_VALIDATION_2);
        }

        $wrong_records = array();
        while ($row = $res->fetch_row()){

            $is_error = false;
            $newvalue = array();
            $values = self::getMultiValues($row[$field_idx], $imp_session['csv_enclosure'], $imp_session['csv_mvsep']);
            foreach($values as $idx=>$r_value){
                $r_value2 = super_trim($r_value);
                if(!($r_value2=='' || $r_value2=='NULL' || $r_value2<0)){        // && $r_value2>0

                    if (!VerifyValue::isValidPointer($dt_def[$idx_pointer_types], $r_value2 ))
                    {//not found
                        $is_error = true;
                        array_push($newvalue, "<span style=\"color:red\">$r_value</span>");
                    }else{
                        array_push($newvalue, $r_value);
                    }
                }
            }

            if($is_error){
                $row[$field_idx] = implode($imp_session['csv_mvsep'], $newvalue);
                array_push($wrong_records, $row);
            }

            $cnt++;
            if($tot_count>4000 && $cnt%2000==0){
                mysql__update_progress(null, $progress_session_id, false, $cnt.','.$tot_count.','.MSG_VALIDATION_2);
            }
        }
        $res->close();
        $cnt_error = count($wrong_records);
        if($cnt_error>0){
            $error = array();
            $error["count_error"] = $cnt_error;
            $error["recs_error"] = array_slice($wrong_records,0,1000);
            $error["field_checked"] = $fields_checked;
            $error["err_message"] = "Record pointer fields must reference an existing record of valid type in the database";
            $error["short_message"] = "Invalid Pointers";

            $imp_session['validation']['count_error'] = $imp_session['validation']['count_error']+$cnt_error;
            array_push($imp_session['validation']['error'], $error);

            return $imp_session;
        }

    }else{
        return ERR_VALIDATION_QUERY.$query;
    }
    return null;
}


/**
* put your comment there...
*
* @param mixed $mysqli
* @param mixed $query
* @param mixed $imp_session
* @param mixed $fields_checked - name of field to be verified
* @param mixed $field_idx - index of validation field in query result (to get value)
*/
private static function validateNumericField($mysqli, $query, $imp_session, $fields_checked, $field_idx, $type, $progress_session_id){

    $res = $mysqli->query($query);

    if($res){

        $cnt = 0;
        $tot_count = $imp_session['reccount']>0?$imp_session['reccount']:1000;
        if($tot_count>4000){
            mysql__update_progress(null, $progress_session_id, false, '0,'.$tot_count.','.MSG_VALIDATION_1);
        }

        $wrong_records = array();
        while ($row = $res->fetch_row()){

            $is_error = false;
            $newvalue = array();
            $values = self::getMultiValues($row[$field_idx], $imp_session['csv_enclosure'], $imp_session['csv_mvsep']);
            foreach($values as $idx=>$r_value){
                if($r_value!=null && super_trim($r_value)!='' && super_trim($r_value)!='NULL'){

                    if(!is_numeric($r_value)){
                        $is_error = true;
                        array_push($newvalue, "<span style=\"color:red\">$r_value</span>");
                    }else{
                        array_push($newvalue, $r_value);
                    }
                }
            }

            if($is_error){
                $row[$field_idx] = implode($imp_session['csv_mvsep'], $newvalue);
                array_push($wrong_records, $row);
            }

            $cnt++;
            if($tot_count>4000 && $cnt%2000==0){
                mysql__update_progress(null, $progress_session_id, false, $cnt.','.$tot_count.','.MSG_VALIDATION_1);
            }
        }
        $res->close();
        $cnt_error = count($wrong_records);
        if($cnt_error>0){
            $error = array();
            $error["count_".$type] = $cnt_error;
            $error["recs_error"] = array_slice($wrong_records,0,1000);
            $error["field_checked"] = $fields_checked;
            $error["err_message"] = "Numeric fields must be pure numbers, they cannot include alphabetic characters or punctuation";
            $error["short_message"] = "Invalid Numerics";
            $imp_session['validation']['count_'.$type] = $imp_session['validation']['count_'.$type]+$cnt_error;
            array_push($imp_session['validation'][$type], $error);

            return $imp_session;
        }

    }else{
        return ERR_VALIDATION_QUERY.$query;
    }
    return null;
}


/**
* put your comment there...
*
* @param mixed $mysqli
* @param mixed $query
* @param mixed $imp_session
* @param mixed $fields_checked - name of field to be verified
* @param mixed $field_idx - index of validation field in query result (to get value)
*/
private static function validateDateField($query, $imp_session, $fields_checked, $field_idx, $type){

    $res = self::$mysqli->query($query);

    if($res){
        $wrong_records = array();
        while ($row = $res->fetch_row()){

            $is_error = false;
            $newvalue = array();
            $values = self::getMultiValues($row[$field_idx], $imp_session['csv_enclosure'], $imp_session['csv_mvsep']);
            foreach($values as $idx=>$r_value){
                if($r_value!=null && super_trim($r_value)!='' && super_trim($r_value)!='NULL'){


                    if( preg_match(REGEX_YEARONLY, $r_value) ){ //this is year only
                        array_push($newvalue, $r_value);
                    }else{

                         $temporal = new Temporal($r_value);
                         if($temporal->isValid()){
                             array_push($newvalue, $r_value);
                         }else{

                             try{
                                $t2 = new DateTime($r_value);
                                $value = $t2->format(DATE_8601);
                                array_push($newvalue, $value);
                             } catch (Exception  $e){
                                $is_error = true;
                                array_push($newvalue, "<span style=\"color:red\">$r_value</span>");
                             }
                         }

                        /* OLD VERSION - strtotime doesn't work for dates prior 1901
                        $date = date_parse($r_value);
                        if ($date["error_count"] == 0 && checkdate($date["month"], $date["day"], $date["year"]))
                        {
                            $value = strtotime($r_value);
                            $value = date(DATE_8601, $value);
                            array_push($newvalue, $value);
                        }else{
                            $is_error = true;
                            array_push($newvalue, "<span style=\"color:red\">$r_value</span>");
                        }
                        */

                    }
                }
            }

            if($is_error){
                $row[$field_idx] = implode($imp_session['csv_mvsep'], $newvalue);
                array_push($wrong_records, $row);
            }
        }
        $res->close();
        $cnt_error = count($wrong_records);
        if($cnt_error>0){
            $error = array();
            $error["count_".$type] = $cnt_error;
            $error["recs_error"] = array_slice($wrong_records,0,1000);
            $error["field_checked"] = $fields_checked;
            $error["err_message"] = "Date values must be in dd-mm-yyyy, mm/dd/yyyy or yyyy-mm-dd formats. Or it should be valid Temporal object.";
            $error["short_message"] = "Invalid Dates";
            $imp_session['validation']['count_'.$type] = $imp_session['validation']['count_'.$type]+$cnt_error;
            array_push($imp_session['validation'][$type], $error);

            return $imp_session;
        }

    }else{
        return ERR_VALIDATION_QUERY.$query;
    }
    return null;
}

/**
* Check whether value is valid, attempts to correct into POINT
*
* @param mixed $wkt - Geo value to validate
* @param int $rec_id - Record id within import table
* @param mixed $table - Import table name
* @param mixed $field - Import field name within table
*/
private static function validateGeoField($wkt, $rec_id, $table, $field){

    $res = false;
    $mysqli = self::$mysqli;

    $coords = explode(",", $wkt);

    if(count($coords) != 2){ // Values maybe separated via space instead
        $coords = explode(" ", $wkt);
    }

    if(count($coords) == 3 && preg_replace('/[^0-9.]/', '', $coords[0]) == ''){ // First index maybe POINT, P, or something similar
        array_shift($coords);
    }

    $x = '';
    $y = '';

    if(count($coords) == 2){

        // Remove starting spaces
        $x = ltrim($coords[0]);
        $y = ltrim($coords[1]);

        // Remove non-digits, excluding periods (.) and minus symbol from x and y
        if(!is_numeric($x)){
            $x = preg_replace('/[^-0-9.]/', '', $x);
            $x = preg_replace('/-{2,}/', '-', $x);
        }
        if(!is_numeric($y)){
            $y = preg_replace('/[^-0-9.]/', '', $y);
            $y = preg_replace('/-{2,}/', '-', $y);
        }

        if(is_numeric($x) && is_numeric($y)){ // treat as a set of coords

            $constructed_geo = "POINT (" . $x . " " . $y . ")";// use a basic point
            $geo_len = strlen($constructed_geo);// in case field needs enlarging within import table
            $test_geom = geoPHP::load($constructed_geo, 'wkt');

            if($test_geom!=null && !$test_geom->isEmpty()){

                $field = preg_replace(REGEX_ALPHANUM, "", $field);//for snyk

                $maxw_query = 'SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE table_name = "'.$table.'" AND column_name = "'. $field .'"';
                $field_max_width = mysql__select_value($mysqli, $maxw_query);

                if($field_max_width && $field_max_width < $geo_len){
                    $update_col = "ALTER TABLE $table MODIFY $field varchar($geo_len)";
                    $mysqli->query($update_col);
                }

                $update_table = "UPDATE $table SET $field = '" . $constructed_geo . "' WHERE imp_ID = ".intval($rec_id);
                $update_table_res = $mysqli->query($update_table);

                if($update_table_res){
                    $res = $constructed_geo;
                }else{
                    $res = false;
                }
            }
        }
    }

    return $res;
}

/**
* Split multivalue field
*
* @param array|string $values
* @param mixed $csv_enclosure
*/
private static function getMultiValues($values, $csv_enclosure, $csv_mvsep){

    $nv = array();

    $values =  ($csv_mvsep=='none')?array($values) : explode($csv_mvsep, $values);

    if(count($values)==1){
        array_push($nv, super_trim($values[0]));
        return $nv;
    }

    if($csv_enclosure==1){
        $csv_enclosure = "'";
    }elseif($csv_enclosure=='none'){
        $csv_enclosure = 'ʰ';//rare character
    }else {
        $csv_enclosure = '"';
    }

    foreach($values as $idx=>$value){
        if($value!=""){
            if(strpos($value,$csv_enclosure)===0 && strrpos($value,$csv_enclosure)===strlen($value)-1){
                $value = substr($value,1,strlen($value)-2);
            }
            array_push($nv, super_trim($value));
        }
    }

    return $nv;
}


//=================
//
// assign real record ID into import (source) table  NOT USED
//
private static function updateRecIds($import_table, $imp_id, $id_field, $newids, $csv_mvsep){

    if(!isEmptyArray($newids)){

    $newids = "'".implode($csv_mvsep, $newids)."'";

    $updquery = "UPDATE `$import_table` SET `$id_field`=$newids WHERE imp_id=$imp_id";

    if(!self::$mysqli->query($updquery)){
        print "<div style='color:red'>Cannot update import table (set record id ".$newids.") for line:".$imp_id.".</div>";
    }

    }
}

//
//
//
private static function findOriginalRecord($recordId){

    $details = array();

    $query = "SELECT rec_URL, rec_ScratchPad FROM Records WHERE rec_ID=".$recordId;
    $row = mysql__select_row(self::$mysqli, $query);
    if(!$row){
        return $details;
    }

    $details['recordURL'] = $row[0];
    $details['recordNotes'] = $row[1];

    $query = "SELECT dtl_Id, dtl_DetailTypeID, dtl_Value, ST_asWKT(dtl_Geo), dtl_UploadedFileID FROM recDetails WHERE dtl_RecID=".$recordId." ORDER BY dtl_DetailTypeID";
    $dets = mysql__select_all(self::$mysqli, $query);
    if(!$dets){
        return $details;
    }


    foreach ($dets as $row){
        $bd_id = $row[0];
        $field_type = $row[1];
        if($row[4]){ //dtl_UploadedFileID
            $value = $row[4];
        }elseif($row[3]){
            $value = $row[2].' '.$row[3];
        }else{
            $value = $row[2];
        }
        if(!@$details["t:".$field_type]) {$details["t:".$field_type] = array();}
        $details["t:".$field_type]["bd:".$bd_id] = $value;
    }

    return $details;
}

//
//
//
private static function doInsertUpdateRecord($recordId, $import_table, $recordType, $csv_mvsep,
                                                                    $details, $id_field, $old_id_in_idfield, $mode_output,
                                                                    $ignore_errors, $record_count){

    //check permission beforehand
    if($recordId>0){

        $ownerid = null;
        $access = null;
        $rectypes = array();
        if(!recordCanChangeOwnerwhipAndAccess(self::$system, $recordId, $ownerid, $access, $rectypes)){
            // error message: $res;
            self::$rep_permission++;
            return null;
        }
    }elseif($recordId==null ||  $recordId==''){
        $recordId = 0; //add new record
    }

    $record = array();
    $record['ID'] = $recordId;
    $record['RecTypeID'] = $recordType;
    $record['AddedByImport'] = 1;
    $record['no_validation'] = $ignore_errors?'ignore_all':true; //if true - don't check resouces and enums
    if(@$details['URL']) {$record['URL'] = @$details['URL'];}
    if(@$details['ScratchPad']) {$record['ScratchPad'] = @$details['ScratchPad'];}
    $record['details'] = $details;

    $updating_record = $recordId != 0 && // Check that a record is actually being updated, for the final count
                       (array_key_exists('URL', $record) && !empty($record['URL'])
                        || array_key_exists('ScratchPad', $record) && !empty($record['ScratchPad'])
                        || is_array($details) && !empty($details));

    $ignore_dtys = array();

    foreach(array_keys($record['details']) as $dty){
        if(preg_match("/^t:\\d+$/", $dty) !== 1){
            continue;
        }

        $dty = substr($dty, 2);
        if($dty > 0){
            array_push($ignore_dtys, $dty);
        }
    }

    if($recordId == 0){ // New records only

        $cached_increments = array_key_exists($recordType, self::$increment_fields) ? self::$increment_fields[$recordType] : array();

        foreach($cached_increments as $dty => $value){

            if(in_array($dty, $ignore_dtys)){
                continue;
            }

            $matches = array();
            if (preg_match('/(\d+)$/', $value, $matches)){
                $digits = $matches[1];
                $increment_digit = str_pad(intval($digits) + 1, strlen($digits), '0', STR_PAD_LEFT);

                $value = substr($value,0,-strlen($digits)).($increment_digit);
            }else{ // shouldn't happen here, but just in case
                $value = $value.'1';
            }

            $cached_increments[$dty] = $value; // update cache
            $record['details'][$dty] = $value;

            array_push($ignore_dtys, $dty);
        }

        // Get missing auto increment values
        $auto_increment_params = array(
            'rtyID' => $recordType,
            'ignore_dtys' => $ignore_dtys
        );

        $auto_increment = recordGetAllIncremenetedValues(self::$system, $auto_increment_params);

        if(!empty($auto_increment)){
            $record['details'] = array_replace($details, $auto_increment);

            $cached_increments = array_replace($cached_increments, $auto_increment);
        }
        if(!empty($cached_increments)){
            self::$increment_fields[$recordType] = $cached_increments;
        }
    }

    $increment_update = true;
    if($updating_record){
        $increment_update = self::_isRecordUpdating($recordId, $record);
    }

    $out = recordSave(self::$system, $record, false, false, 0, $record_count);//see recordModify.php

    $new_recordID = null;

    if ( @$out['status'] != HEURIST_OK ) {

        if ($mode_output!='json'){
            //array("status"=>$status, "message"=>$message, "sysmsg"=>$sysmsg)

            //@todo special formatting
            //foreach($out["error"] as $idx=>$value){
                $value = htmlspecialchars($out["message"]);
                $value = str_replace(". You may need to make fields optional. Missing data","",$value);
                $k = strpos($value, "Missing data for Required field(s) in");
                if($k!==false){
                    $value = "<span style='color:red'>".substr($value,0,$k+37)."</span>".substr($value,$k+37);
                }else{
                    $value  = "<span style='color:red'>".$value."</span>";
                }
            //$out["error"][$idx] = $value;
            //for}

            foreach($details['imp_id'] as $imp_id){
                print "<div><span style='color:red'>Line: ".intval($imp_id).".</span> ".implode(";",$value);
                $res = self::getImportValue($imp_id, $import_table);
                if(is_array($res)){
                    $s = htmlspecialchars(implode(", ", $res));
                    print "<div style='padding-left:40px'>".$s.DIV_E;
                }
                print DIV_E;
            }
        }else{
            if(self::$rep_skipped<100){
                $line_id = @$details['imp_id'][0];

                self::$rep_skipped_details[] = ($recordId>0?('record#'.$recordId):'new record')
                        .($line_id>0?('(line#'.$line_id.')'):'')
                        .': '.$out["message"];
            }
        }

        self::$rep_skipped++;
    }
    else{

        $new_recordID = intval($out['data']);

        if($recordId != $new_recordID){ //}==null){
            self::$rep_added++;
            self::$rep_unique_ids[] = $new_recordID;
        }else{
            //do not count updates if this record was already inserted before
            if($updating_record && !in_array($new_recordID, self::$rep_unique_ids)){
                self::$rep_unique_ids[] = $new_recordID;
                !$increment_update || self::$rep_updated++;
            }elseif(is_numeric($new_recordID) && $new_recordID > 0){
                self::$rep_added++;
                self::$rep_unique_ids[] = $new_recordID;
            }
        }

        //change record id in import table from negative temp to id form Huerist records (for insert)
        if($id_field){ // ($recordId==null || $recordId>0)){

            $imp_ids = prepareIds($details['imp_id']);//for snyk
            $imp_ids = implode(",", $imp_ids);

            if($old_id_in_idfield==null){

                if($new_recordID>0){

                    $updquery = "UPDATE `$import_table` SET `$id_field`=$new_recordID WHERE imp_id in ($imp_ids)";

                    if(!self::$mysqli->query($updquery) && $mode_output!='json'){
                        print "<div style='color:red'>Cannot update import table (set record id ".$new_recordID.") for lines: $imp_ids</div>";
                    }
                }

            }else{

                $oldvals = mysql__select_all( self::$mysqli,
                            "SELECT `imp_id`, `$id_field` FROM `$import_table` WHERE `imp_id` in ($imp_ids)");
                if(is_array($oldvals))
                {
                    foreach($oldvals as $idx=>$row){
                        $ids = explode($csv_mvsep, $row[1]);
                        $new_ids = array();
                        foreach($ids as $i=>$id){
                            if($id==$old_id_in_idfield){
                                 $new_ids[] = intval($new_recordID);
                            }else{
                                 $new_ids[] = intval($id);
                            }
                        }
                        /*array_map(function ($v) use ($old_id_in_idfield, $new_id) {
                            return $v == $old_id_in_idfield ? $new_id : $v;
                        }, $ids);*/

                        $new_ids = "'".implode($csv_mvsep, $new_ids)."'";

                        $updquery = "UPDATE `$import_table` SET `$id_field`=$new_ids WHERE imp_id=".intval($row[0]);

                        self::$mysqli->query($updquery);
                    }//foreach
                }
            }

        }

        /*
        if (@$out['warning'] && $mode_output!='json') {
            print "<div style=\"color:#ff8844\">Warning: ".implode(";",$out["warning"]).DIV_E;
        }
        */

    }

    return $new_recordID;
}//end doInsertUpdateRecord

/*
* Get values for given ID from imort table (to preview values on UI)
*
* @param mixed $rec_id
* @param mixed $import_table
*/
private static function getImportValue($rec_id, $import_table){

    $query = "select * from $import_table where imp_id=".$rec_id;
    $res = mysql__select_row(self::$mysqli, $query);
    return $res;
}

/**
* create or update records
*
* @param mixed $params
*         is_csv_import
*         session
*         imp_ID  - import session id
*         download_files - download remote files and register
*         sa_rectype
*         utm_zone
*         seq_index
*         recid_field
*         mapping
*/
public static function performImport($params, $mode_output){

    self::initialize();

    self::$rep_processed = 0;
    self::$rep_added     = 0;
    self::$rep_updated   = 0;
    self::$rep_skipped    = 0;
    self::$rep_skipped_details = array();
    self::$rep_permission  = 0;
    self::$rep_unique_ids = array();

    $is_csv_import = 0;
    if(array_key_exists('is_csv_import', $params)){
        self::$invalid_geo = 0;
        $is_csv_import = 1;
    }

    $progress_session_id = @$params['session'];

    $imp_session = ImportSession::load(@$params['imp_ID']);
    if($imp_session==false){
        return false;
    }

    //rectype to import
    $import_table = $imp_session['import_table'];
    $recordType = @$params['sa_rectype'];
    $ignore_rectype = @$params['ignore_rectype'] ? $params['ignore_rectype'] : 0;

    $utm_zone = @$params['utm_zone'];
    $hemisphere = 'N';
    $gPoint = null;
    $geojson_adapter = null;
    $wkt_adapter = null;

    if($utm_zone!=0 && $utm_zone!=null){
        $utm_zone = strtoupper($utm_zone);
        if(strpos($utm_zone, 'S')==false){ $hemisphere='N';}
        else {$hemisphere = 'S';}
        $utm_zone = intval(str_replace(array('N','S'),'',$utm_zone));
        if($utm_zone<1 || $utm_zone>60) {
            $utm_zone = 0;
        }else{
            $geometry = geoPHP::load('MULTILINESTRING((10 10,20 20,10 40))','wkt');
            $wkt_adapter = new wkt();
            $geojson_adapter = new GeoJSON();
            $gPoint = new GpointConverter();
            $gPoint->setUTMZone($utm_zone.$hemisphere);
        }
    }

    $currentSeqIndex = @$params['seq_index'];
    $use_sequence = false;
    $mapping_keys_values = null;
    if($currentSeqIndex>=0 && @$imp_session['sequence'] && is_array(@$imp_session['sequence'][$currentSeqIndex])){
        $use_sequence = true;

        $mapping_keys_values = @$imp_session['sequence'][$currentSeqIndex]['mapping_keys_values'];
    }

    $id_field = @$params['recid_field'];//record ID field is always defined explicitly

    $id_field_not_defined = ($id_field==null || $id_field=='');

    if(intval($recordType)<1){
        return "record type not defined";
    }

    $field_types = array();// idx => fieldtype ID
    $field_indexes = array();
    $sel_query = array();
    $mapping = array();



    //get field mapping and selection query from _REQUEST(params)
    if(@$params['mapping']){    //new way
        $mapping = @$params['mapping'];// idx => fieldtype ID

        if(!isEmptyArray($mapping)){
            foreach ($mapping as $index => $field_type) {
                $field_name = "field_".$index;
                array_push($field_types, $field_type);
                array_push($sel_query, $field_name);
                array_push($field_indexes, $index);
            }
        }else{
            return 'Mapping is not defined';
        }
    }else{

        foreach ($params as $key => $field_type) {
            if(strpos($key, "sa_dt_")===0 && $field_type){  //search for values of field selector
                //get index
                $index = substr($key,6);
                $field_name = "field_".$index;

                //all mapped fields - they will be used in validation query
                array_push($sel_query, $field_name);
                array_push($field_types, $field_type);
                $mapping[$index] = $field_type;
            }
        }
        if(empty($sel_query)){
            return "mapping not defined";
        }

    }

    //indexes
    $recStruc = dbs_GetRectypeStructures(self::$system, array($recordType), 2);
    $recStruc = $recStruc['typedefs'];
    $recTypeName = $recStruc[$recordType]['commonFields'][ $recStruc['commonNamesToIndex']['rty_Name'] ];
    $idx_name = $recStruc['dtFieldNamesToIndex']['rst_DisplayName'];

    $idx_fieldtype = $recStruc['dtFieldNamesToIndex']['dty_Type'];
    $idx_term_tree = $recStruc['dtFieldNamesToIndex']['rst_FilteredJsonTermIDTree'];
    $idx_term_nosel = $recStruc['dtFieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];

    $idx_reqtype = $recStruc['dtFieldNamesToIndex']['rst_RequirementType'];
    $recordTypeStructure = $recStruc[$recordType]['dtFields'];

    //get terms name=>id
    $terms_enum = null;
    $terms_relation = null;

    $select_query = "SELECT ". implode(",", $sel_query)
    . ( $id_field ?", ".$id_field:"" )
    . ", imp_id "  //last field is row# - imp_id
    . " FROM ".$import_table;

    $ignore_insert = (@$params['ignore_insert']==1);//ignore new records
    $ignore_update = (@$params['ignore_update']==1);//ignore update records
    $ignore_errors = (@$params['ignore_errors']==1);//ignore any errors in data - add data "as is"

    if($id_field){  //index field defined - add to list of columns
        $id_field_idx = count($field_types);//last one

        if($ignore_insert){
            $select_query = $select_query." WHERE (".$id_field.">0) ";//use records with defined value in index field
        }elseif($ignore_update){
            $select_query = $select_query." LEFT JOIN Records ON rec_ID=".$id_field." WHERE (NOT(rec_ID IS NOT NULL OR ".$id_field."='')) ";//skip updating records
        }else {
            $select_query = $select_query." WHERE (".$id_field."!='') ";//ignore empty values
        }
        $select_query = $select_query." ORDER BY ".$id_field;

    }else{
        if($ignore_insert){
            return "id field not defined";
        }

        //create id field by default - add to import table

        $id_fieldname = "ID field for Record type #".$recordType;
        $index = array_search($id_fieldname, $imp_session['columns']);//find it among existing columns

        if($index!==false){ //this is existing field
            $id_field_def  = "field_".$index;
            $imp_session['indexes'][$id_field] = $recordType;
        }else{
            $field_count = count($imp_session['columns']);
            $id_field_def = "field_".$field_count;

            $altquery = "alter table ".$import_table." add column ".$id_field_def." int(10) ";
            if (!self::$mysqli->query($altquery)) {
                return "SQL error: cannot alter import session table, cannot add new index field: " . self::$mysqli->error;
            }
            $imp_session['indexes'][$id_field_def] = $recordType;
            array_push($imp_session['columns'], $id_fieldname);
        }
    }

    $res = self::$mysqli->query($select_query);
    if (!$res) {

        return "import table is empty";

    }else{

        $tot_count = $imp_session['reccount'];
        $first_time = true;
        $step = ceil($tot_count/100);
        if($step>100) {$step = 100;}
        elseif($step<10) {$step=10;}

        mysql__update_progress( null, $progress_session_id, true, '0,'.$tot_count );

        $csv_mvsep = @$params['csv_mvsep']?$params['csv_mvsep']:$imp_session['csv_mvsep'];
        $csv_enclosure = @$params['csv_enclosure']?$params['csv_enclosure']:$imp_session['csv_enclosure'];
        if(!$csv_mvsep) {$csv_mvsep = '|';}
        if(!$csv_enclosure) {$csv_enclosure = '"';}

        $previos_recordId = null;
        $recid_in_idfield = null; //value in id field in import table before import session
        $prev_recid_in_idfield = null;
        $ismulti_id = false; $prev_ismulti_id=false;
        $recordId = null;
        $details = array();
        $details_orig = array();//to keep original for sa_mode=2 (replace all existing value)

        $new_record_ids = array();
        $imp_id = null;

        $pairs = array();// for multivalue rec_id => new_rec_id  (keep new id if such id already used)
        $pairs_id_value = array();//for multivalue rec_id     new_rec_id => value of mapped field


        mysql__supress_trigger(self::$mysqli, true);

        // start transaction
        $use_transaction = true;
        $keep_autocommit = false;
        if($use_transaction){
            $keep_autocommit = mysql__begin_transaction(self::$mysqli);
        }

        $record_count = $res ? $res->num_rows : 0;
        $org_recordType = $recordType;

        while ($row = $res->fetch_row()){

            if($org_recordType != $recordType){
                $recordType = $org_recordType;
            }

            //split multivalue index field
            $id_field_values = array();
            if($id_field){
                if(@$row[$id_field_idx]){ //id field defined - detect insert or update
                    $id_field_values = explode($csv_mvsep, $row[$id_field_idx]);
                }elseif(!$ignore_insert){
                    //special case: field value is not defined - add new record
                    $id_field_values = array(0=>null);
                }
            }

            $ismulti_id = count($id_field_values)>1;

            //loop all id values - because index field may have multivalue ID - always consider ID field as multivalues
            foreach($id_field_values as $idx2 => $recid_in_idfield){   //this id from matching

                if($recid_in_idfield!=null && @$pairs[$recid_in_idfield]){ //it was already imported at this import session
                    $recordId_in_import = $pairs[$recid_in_idfield];
                }else{
                    $recordId_in_import = $recid_in_idfield;
                }

                //we already have recordID
                if($recordId_in_import){ //id field defined - detect insert or update

                    if($previos_recordId!=$recordId_in_import){ //next record ID

                        //record id is changed - save data
                        //$recordId is already set
                        if($previos_recordId!=null && !empty($details)){ //perform import action

                            $allow_operation = ! (($recordId>0) ?$ignore_update:$ignore_insert);
                            if($allow_operation){

                            //$details = retainExisiting($details, $details2, $params, $recordTypeStructure, $idx_reqtype);
                            //import detail is sorted by rec_id -0 thus it is possible to assign the same recId for several imp_id

                            if($ignore_rectype == 1 && $recordId > 0){ // update record type id
                                $recordType = mysql__select_value(self::$mysqli, 'SELECT rec_RecTypeID FROM Records WHERE rec_ID=' . $recordId);
                            }
                            $new_id = self::doInsertUpdateRecord($recordId, $import_table, $recordType, $csv_mvsep,
                                                        $details, $id_field, $prev_ismulti_id?$prev_recid_in_idfield:null, $mode_output,
                                                        $ignore_errors, $record_count);

                            if($prev_recid_in_idfield!=null) {$pairs[$prev_recid_in_idfield] = $new_id;}//new_A

                            }
                        }
                        $prev_recid_in_idfield = $recid_in_idfield; //ugly
                        $prev_ismulti_id = $ismulti_id;
                        $previos_recordId = $recordId_in_import;
                        //----------------------------------------------

                        //reset
                        $details = array();
                        $details_orig = array();

                        if($recordId_in_import>0){ //possible update
                            // find original record in HDB
                            $details_orig = self::findOriginalRecord($recordId_in_import);
                            if(empty($details_orig)){
                                //record not found - this is insert with predefined ID
                                $recordId = -$recordId_in_import;

                            }else{
                                // record found - update detail according TO settings
                                $recordId = $recordId_in_import;
                                $details = $details_orig;
                            }

                        }elseif($ignore_rectype == 1){ // no insert when ignoring rectype
                            self::$rep_skipped ++;
                            continue;
                        }else{
                            $recordId = null; //insert for negative
                        }
                    }

                }
                elseif($ignore_rectype == 1) { // no insert when ignoring rectype
                    self::$rep_skipped ++;
                    continue;
                }
                else
                { //INSERT - if field is not defined - always insert for each line in import data
                    $recordId = null;
                    $details = array();
                }

                //START TO FILL DETAILS ============================

                if(@$details['imp_id']==null){
                    $details['imp_id'] = array();
                }
                array_push( $details['imp_id'], intval(end($row)));

                //find values for mapping_keys fields
                // this array is filled on matching step
                // since multivalue in id field need to know the combination of mapping_keys
                // rec_id=>{field_idx:values,.....}
                $mapping_keys_values_curr = array();
                if(is_array($mapping_keys_values) && @$mapping_keys_values[$recid_in_idfield]){
                    $mapping_keys_values_curr = $mapping_keys_values[$recid_in_idfield];
                }

                $lat = null;
                $long = null;

                foreach ($field_types as $index => $field_type) {  //for import data

                    if($field_type=="url"){
                        if($row[$index]){
                            $details['URL'] = super_trim($row[$index]);
                        }
                    }elseif($field_type=="scratchpad"){
                        if($row[$index]){
                            $details['ScratchPad'] = super_trim($row[$index]);
                        }
                    }else{

                        if(substr($field_type, -strlen("_lat")) === "_lat"){ // || $field_type=="latitude"
                            $field_type = substr($field_type, 0, strlen($field_type)-4);
                            $fieldtype_type = "lat";
                        }elseif (substr($field_type, -strlen("_long")) === "_long"){ // || $field_type=="longitude"
                            $field_type = substr($field_type, 0, strlen($field_type)-5);
                            $fieldtype_type = "long";
                        }elseif(defined('DT_PARENT_ENTITY') && $field_type==DT_PARENT_ENTITY){
                            $fieldtype_type = 'resource';
                        }else{
                            $ft_vals = $recordTypeStructure[$field_type];//field type description
                            $fieldtype_type = $ft_vals[$idx_fieldtype];
                        }

                        if(@$mapping_keys_values_curr[@$field_indexes[$index]]){
                            $values = array( $mapping_keys_values_curr[$field_indexes[$index]] );
                        }elseif(strpos($row[$index], $csv_mvsep)!==false){ //multivalue
                            $values = self::getMultiValues($row[$index], $csv_enclosure, $csv_mvsep);
                        }else{
                            $values = array($row[$index]);
                        }

                        foreach ($values as $idx=>$r_value)
                        {
                            $value = null;
                            $r_value = super_trim($r_value);

                            if($fieldtype_type == "enum" || $fieldtype_type == "relationtype"){

                                $r_value = trim_lower_accent($r_value);


                                if($r_value!='' && $r_value!='null'){

                                    //value is numeric - check for trm_ID
                                    if(ctype_digit($r_value)
                                    && VerifyValue::isValidTerm( $ft_vals[$idx_term_tree], $ft_vals[$idx_term_nosel], $r_value, $field_type)){

                                        $value = $r_value;
                                    }

                                    //value not numeric or not found - find label or code
                                    if($value == null){
                                        //stip accents on both sides
                                        $value = VerifyValue::isValidTermLabel($ft_vals[$idx_term_tree], $ft_vals[$idx_term_nosel], $r_value, $field_type, true );
                                    }
                                    if(!($value>0)){
                                        $value = VerifyValue::isValidTermCode($ft_vals[$idx_term_tree], $ft_vals[$idx_term_nosel], $r_value, $field_type );
                                    }
                                }
                            }
                            elseif($fieldtype_type == "resource"){

                                if(!VerifyValue::isValidPointer(null, $r_value)){
                                     $value  = null;
                                }else{
                                     $value = $r_value;
                                }


                            }
                            elseif($fieldtype_type == "geo"){
                                //verify WKT
                                $geoType = null;
                                $r_value = strtoupper($r_value);

                                if(strpos($r_value,'GEOMETRYCOLLECTION')!==false || strpos($r_value,'MULTI')!==false){
                                    $geoType = 'm';
                                }else{

                                    //get WKT type
                                    if(strpos($r_value,'POINT')!==false){
                                        $geoType = "p";
                                    }elseif(strpos($r_value,'LINESTRING')!==false){
                                        $geoType = "l";
                                    }elseif(strpos($r_value,'POLYGON')!==false){ //MULTIPOLYGON
                                        $geoType = "pl";
                                    }
                                }

                                if($geoType){

                                    //convert UTM to WGS
                                    if($utm_zone>0){

                                        $geom = geoPHP::load($r_value, 'wkt');
                                        if(!$geom->isEmpty()){
                                            $json = $geojson_adapter->write($geom, true);
                                            $json = geoSimplifyAndConvertToJSON($json, false, $gPoint);
                                            $r_value = $wkt_adapter->write($geojson_adapter->read(json_encode($json), true));
                                        }
                                    }

                                    if(strpos($r_value, $geoType)===0){
                                        //already exists
                                        $value = $r_value;
                                    }else{
                                        $value = $geoType." ".$r_value;
                                    }
                                }else{

                                    if($is_csv_import != 1){
                                        $value = null;   //wrong or not defined geo
                                    }else{
                                        $value = $r_value;
                                        self::$invalid_geo++;
                                    }
                                }

                            }
                            elseif($fieldtype_type == "lat") {
                                $lat = $r_value;
                            }elseif($fieldtype_type == "long"){
                                //WARNING MILTIVALUE IS NOT SUPPORTED
                                $long = $r_value;

                            }elseif($fieldtype_type=="file"){
                                //value can be remote url
                                //obfuscation id (from the same database)  (default)
                                //ulf_ID (from the same database)
                                //relative path to current database file folder

                                $ulf_ID = null;
                                $is_url = false;
                                $file_query = null;
                                $fres = null;

                                if(is_numeric($r_value) && intval($r_value)>0){ //ulf_UD
                                    $file_query = 'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_ID='.$r_value;
                                }elseif(strpos($r_value,'http')===0){
                                    //find if url is already registered
                                    $is_url = true;
                                    $file_query = 'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_ExternalFileReference="'
                                                            .self::$mysqli->real_escape_string($r_value).'"';

                                }else{

                                    $k = strpos($r_value,'uploaded_files/');
                                    if($k===false) {$k = strpos($r_value,'file_uploads/');}

                                    if($k===0 || $k===1){
                                    //relative path in database folder
                                        $filename = HEURIST_FILESTORE_DIR.$r_value;
                                        if(file_exists($filename)){
                                            //this methods checks if file is already registered
                                            $fres = fileRegister(self::$system, $filename);//see recordFile.php
                                        }
                                    }else {
                                        $file_query = 'SELECT ulf_ID FROM recUploadedFiles WHERE ulf_ObfuscatedFileID="'
                                            .self::$mysqli->real_escape_string($r_value).'"';
                                    }
                                }

                                if($file_query){
                                    $fres = mysql__select_value(self::$mysqli, $file_query);
                                }


                                if($fres>0){
                                    $ulf_ID = $fres;
                                }elseif($is_url) {
                                    //otherwise register as new external resource

                                    $entity = new DbRecUploadedFiles(self::$system);

                                    $ulf_ID = $entity->registerURL( $r_value );

                                    /*
                                    $extension = null;
                                    //detect mime type

                                    $rname = strtolower($r_value);
                                    if(strpos($rname, 'youtu.be')!==false || strpos($rname, 'youtube.com')!==false){
                                        $extension = 'youtube';
                                    }elseif(strpos($rname, 'vimeo.com')!==false){
                                        $extension = 'vimeo';
                                    }elseif(strpos($rname, 'soundcloud.com')!==false){
                                        $extension = 'soundcloud';
                                    }else{
                                            //get extension from url - unreliable
                                            $ap = parse_url($r_value);
                                            if( array_key_exists('path', $ap) ){
                                                $path = $ap['path'];
                                                if($path){
                                                    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                                    if($extension=='php' || $extension=='asp'){
                                                        $extension = null;
                                                    }
                                                }
                                            }
                                    }

                                    if(!$extension){
                                        $mimeType = loadRemoteURLContentType($r_value);
                                        if(strpos($mimeType,';')>0){
                                            list($mimeType, $charset) = explode(';',$mimeType);
                                        }
                                        if($mimeType!=null && $mimeType!==false){
                                            $ext_query = 'SELECT fxm_Extension FROM defFileExtToMimetype WHERE fxm_MimeType="'
                                                        .$mimeType.'"';
                                            $extension = mysql__select_value(self::$mysqli, $ext_query);
                                        }
                                    }

                                    if($extension){
                                            //add to table
                                            $ulf_ID = mysql__insertupdate(self::$mysqli, 'recUploadedFiles', 'ulf',
                                                array("ulf_ID"=>0,
                                                    'ulf_OrigFileName'=>ULF_REMOTE,
                                                    'ulf_UploaderUGrpID'=> self::$system->getUserId(),
                                                    //'ulf_ObfuscatedFileID'=>$nonce,
                                                    'ulf_ExternalFileReference'=>$r_value,
                                                    'ulf_MimeExt'=>$extension,
                                                    'ulf_AddedByImport'=>0
                                                    )
                                            );

                                            if($ulf_ID>0){
                                                $nonce = addslashes(sha1($ulf_ID.'.'.random_int(0,99)));
                                                mysql__insertupdate(self::$mysqli, 'recUploadedFiles', 'ulf',
                                                array("ulf_ID"=>$ulf_ID,
                                                    'ulf_ObfuscatedFileID'=>$nonce
                                                    ));

                                            }else{
                                                $ulf_ID = null;
                                            }
                                    }else{
                                        //mimetype not registered
                                        if(self::$rep_skipped<100)
                                            self::$rep_skipped++;
                                            self::$rep_skipped_details[] = ($recordId>0?('record#'.$recordId):'new record')
                                                    .': Impossible register external URL. Memtype is not defined or not registered in Database';
                                    }
                                */
                                }

                                $value = $ulf_ID;

                            }
                            else{
                                //double spaces are removed on preprocess stage $value = trim(preg_replace('/([\s])\1+/', ' ', $r_value));

                                $value = super_trim($r_value);

                                if($value!='' && $value!='NULL') {
                                    if($fieldtype_type == "date") {
                                        //$value = strtotime($value);
                                        //$value = date(DATE_8601, $value);
                                    }else{
                                        //replace \\r to\r \\n to \n
                                        $value = str_replace("\\r", "\r", $value);
                                        $value = str_replace("\\n", "\n", $value);
                                    }
                                }else{
                                    $value = null;
                                }
                            }

                            if($lat && $long){

                                if($utm_zone>0){ //convert from UTM
                                    $gPoint->setUTM($long, $lat, $utm_zone.$hemisphere);
                                    $gPoint->convertTMtoLL();
                                    $lat  = $gPoint->Lat();
                                    $long = $gPoint->Long();
                                    /*
                                    $PC_LatLon = ToLL($lat, $long, $utm_zone, $hemisphere);
                                    $lat  = $PC_LatLon['lat'];
                                    $long = $PC_LatLon['lon'];
                                    */
                                }

                                $value = "p POINT (".$long."  ".$lat.")";//TODO Where does the 'p' come from? This appears to have been a local invention ...
                                //reset
                                $lat = null;
                                $long = null;

                                //$field_type = 28;
                            }

//sa_upd
//0 - Retain existing values and append distinct new data as repeat values(existing values are not duplicated)
//1 - Add new data only if field is empty (new data ignored for non-empty fields)
//2 - Replace all existing value(s) for the fields specified below
//3 - Add all values, regardless of duplication
//    sa_upd2
//       0   Retain existing if no new data supplied for record
//       1   Delete existing even if no new data supplied for record

                            if($value!=null && $value!='') {//value from cvs

                                $need_add = false;

                                //original is empty - add new
                                if($params['sa_upd']==1 && !@$details_orig["t:".$field_type]){
                                    $need_add = true;

                                }elseif($params['sa_upd']==2){ //replace old one
                                    $need_add = true;

                                    if(@$details_orig["t:".$field_type]){ //remove original
                                        unset($details["t:".$field_type]);
                                        unset($details_orig["t:".$field_type]);
                                    }

                                }elseif($params['sa_upd']==0 || $params['sa_upd']==3){ //add distinct only

                                    $need_add = true; //retain existing and add new distinct one
                                }

                                if($need_add){
                                    //always prevent duplications
                                    if(@$details["t:".$field_type]){
                                        if(strlen($value)<200){
                                            $details_lc = array_map('trim_lower_accent', $details["t:".$field_type]);
                                            //duplications not found - can be added
                                            $need_add = (array_search(trim_lower_accent($value), $details_lc, true)===false || $params['sa_upd'] == 3);
                                            $cnt = count(@$details["t:".$field_type])+1;
                                        }
                                    }else{
                                        $cnt = 1;
                                    }
                                    if($need_add){
                                        $details["t:".$field_type][$cnt] = $value;
                                    }
                                }

                            }elseif($params['sa_upd']==2 && $params['sa_upd2']==1
                                     && @$details_orig["t:".$field_type]
                                     && $recordTypeStructure[$field_type][$idx_reqtype] != "required") { //delete old even if new is not provided

                                    unset($details_orig["t:".$field_type]);
                                    unset($details["t:".$field_type]);
                            }

                        //if insert and require field is not defined - skip it
                        if( !($recordId>0) &&
                            @$recordTypeStructure[$field_type][$idx_reqtype] == "required")
                        {
                            //$is_valid_newrecord = false;
                            //break;
                        }

                    }   //for values
                    }
                }//for import data

                //END FILL DETAILS =============================

                $new_id = null;

                // || $recordId==null
                //add - update record for 2 cases: idfield not defined, idfield is multivalue
                                            //$recordId==null
                if(  ($id_field_not_defined || $ismulti_id) && !empty($details) ){ //id field not defined - insert for each line

                    if(!$ignore_insert){

                        $new_id = self::doInsertUpdateRecord($recordId, $import_table, $recordType, $csv_mvsep,
                                                    $details, $id_field, $ismulti_id?$recid_in_idfield:null, $mode_output,
                                                    $ignore_errors, $record_count);
                        if($recid_in_idfield!=null) {$pairs[$recid_in_idfield] = $new_id;}//new_A

                        $details = array();
                    }
                }

                self::$rep_processed++;
/*
                if (false && $mode_output=='html' && self::$rep_processed % $step == 0) {
                    ob_start();
                    if($first_time){
                        print '<script type="text/javascript">$("#div-progress").hide();</script>';
                        $first_time = false;
                    }
                    print '<script type="text/javascript">update_counts('
                        .self::$rep_processed.','
                        .self::$rep_added.','
                        .self::$rep_updated.','.$tot_count.')</script>'."\n";
                    ob_flush();
                    flush();
                }
*/
                if($use_transaction && self::$rep_processed % 1000 == 0){
                    self::$mysqli->commit();
                    self::$mysqli->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
                }
                if(self::$rep_processed % $step == 0){
                    mysql__update_progress(null, $progress_session_id, false, self::$rep_processed.','.$tot_count);
                }
            }//foreach multivalue index

        }//main  all recs in import table
        $res->close();

                                        //&& $recordId!=null
        if($id_field && !empty($details)){ //action for last record
            //$details = retainExisiting($details, $details2, $params, $recordTypeStructure, $idx_reqtype);
            $allow_operation = ! (($recordId>0) ?$ignore_update:$ignore_insert);
            if($allow_operation){

                if($ignore_rectype == 1 && $recordId > 0){ // update record type id
                    $recordType = mysql__select_value(self::$mysqli, 'SELECT rec_RecTypeID FROM Records WHERE rec_ID=' . $recordId);
                }
                $new_id = self::doInsertUpdateRecord($recordId, $import_table, $recordType, $csv_mvsep,
                                                    $details, $id_field, $ismulti_id?$recid_in_idfield:null,  $mode_output,
                                                    $ignore_errors, $record_count);
                if($recid_in_idfield!=null) {$pairs[$recid_in_idfield] = $new_id;}//new_A

            }
        }

        if($use_transaction){
            self::$mysqli->commit();
            if($keep_autocommit===true) {self::$mysqli->autocommit(true);}
        }
        mysql__update_progress(null, $progress_session_id, false, 'REMOVE');
        mysql__supress_trigger(self::$mysqli, false);

        /*
                if($use_transaction){
                    self::$mysqli->rollback();
                    if($keep_autocommit===true) {self::$mysqli->autocommit(true);}
                }
        */


        if(!$id_field){
            array_push($imp_session['uniqcnt'], self::$rep_added);
        }

        //reassign record ids to keep in session
        if(is_array($mapping_keys_values)){
            $new_keys_values = array();
            foreach ($mapping_keys_values as $old_id=>$values){
                $new_id = @$pairs[$old_id];
                if($new_id>0){
                     $new_keys_values[$new_id] = $values;
                }
            }
            $imp_session['sequence'][$currentSeqIndex]['mapping_keys_values'] = $new_keys_values;
        }

        $import_report = array(
            'processed'=>self::$rep_processed,
            'inserted'=>self::$rep_added,
            'updated'=>self::$rep_updated,
            'total'=>$tot_count,
            'skipped'=>self::$rep_skipped,
            'skipped_details'=>self::$rep_skipped_details,
            'permission'=>self::$rep_permission
        );

        if($is_csv_import == 1){
            $import_report['invalid_geo'] = self::$invalid_geo;
        }

        //update counts array
        $new_counts = array( self::$rep_updated+self::$rep_added, self::$rep_processed, 0,0 );

        if($ignore_insert){
            if($use_sequence){
                $prev_counts = @$imp_session['sequence'][$currentSeqIndex]['counts'];
            }elseif(@$imp_session['counts'][$recordType]){
                $prev_counts = $imp_session['counts'][$recordType];
            }
            if(is_array($prev_counts) && count($prev_counts)==4){
                $new_counts[2] = $prev_counts[2];
                $new_counts[3] = $prev_counts[3];
            }
        }

        if($use_sequence){
            $imp_session['sequence'][$currentSeqIndex]['counts'] = $new_counts;
        }else{
            if(!@$imp_session['counts']) {$imp_session['counts'] = array();}
            $imp_session['counts'][$recordType] = $new_counts;
        }


        if(false && $mode_output!='json'){

            print '<script type="text/javascript">update_counts('
                .self::$rep_processed.','.self::$rep_added.','
                .self::$rep_updated.','.self::$tot_count.')</script>'."\n";
            if(self::$rep_skipped>0){
                print '<p>'.self::$rep_skipped.' rows skipped</p>';
            }
        }

    }

    //save mapping into import_session
    if($use_sequence){
        $imp_session['sequence'][$currentSeqIndex]['mapping_flds'] = $mapping;
    }else{
        //old way
        if(!@$imp_session['mapping_flds']){
            $imp_session['mapping_flds'] = array();
        }
        $imp_session['mapping_flds'][$recordType] = $mapping;
    }

    $imp_session['import_report'] = $import_report;

    $res = ImportSession::save( $imp_session );
    if(!is_array($res)){
        self::$system->addError(HEURIST_DB_ERROR, 'Cannot save import session', $res);
        return false;
    }
    return $res;
} //end performImport

//
// Imports all missing terms
//
public static function importTerms($params){

    self::initialize();

    $imp_session = ImportSession::load(@$params['imp_ID']);
    if($imp_session==false){
        return false;
    }
    $import_table = $imp_session['import_table'];

    $fields = @$params['fields'];
    if(!is_array($fields) || empty($fields)){
        return [];
    }

    $trm_Separator = @$params['trm_Separator'];
    if(empty($trm_Separator) || $trm_Separator !== '.'){
        $trm_Separator = '';
    }

    $progress_session_id = @$params['session'];

    $results = [
        'invalid' => [],
        'error' => [],
        'success' => []
    ];

    $def_terms = new DbDefTerms(self::$system);

    mysql__update_progress(null, $progress_session_id, true, "0,0");

    $total = 0;

    // $fields -  array(field id in import table, $dty_ID, vocabulary ID)
    foreach($fields as $idx => $field){

        $tab_num = $idx + 1;

        if(!is_array($field) || count($field) != 3){// skip
            $results['invalid'][$idx] = "Tab $tab_num => Invalid number of items provided";
            continue;
        }

        $field_id = $field[0];
        $dty_ID = intval($field[1]);
        $parent_ID = intval($field[2]);

        if(strpos($field_id, 'field_') !== 0 || $dty_ID <= 0 || $parent_ID <= 0){
            $results['invalid'][$idx] = "Tab $tab_num => Invalid parameters provided";
            continue;
        }

        $parent_ID = mysql__select_value(self::$mysqli, "SELECT trm_ID FROM defTerms WHERE trm_ID = ?", ['i', $parent_ID]);
        if(!$parent_ID || $parent_ID < 0){
            $results['error'][$idx] = "Tab $tab_num => Invalid parent term id provided";
            continue;
        }

        //find all not empty terms from import table
        $query = "SELECT imp_ID, $field_id FROM $import_table WHERE $field_id <> '' GROUP BY $field_id";

        $terms = mysql__select_assoc2(self::$mysqli, $query);

        $results['success'][$idx] = [];

        if(empty($terms)){
            continue;
        }
        $results['error'][$idx] = [];

        $row_total = count($terms);
        mysql__update_progress(null, $progress_session_id, false, "0,$row_total");

        $new_terms = [];

        $new_term = [
            'trm_Label' => null,
            'trm_ParentTermID' => $parent_ID,
            'trm_Domain' => 'enum'
        ];

        $row_count = 1;
        foreach($terms as $row => $term){

            $row_count ++;

            if(mb_strlen($term) > 200){
                $results['error'][$idx] = "Row $row => Invalid term label (more than 200 characters)";
                continue;
            }

            $new_term['trm_Label'] = $term;

            $new_terms[] = $new_term;

            if(!empty($new_terms) && count($new_terms) % 500 === 0){

                $def_terms->setData([
                    'fields' => $new_terms,
                    'term_separator' => $trm_Separator
                ]);

                $res = $def_terms->batch_action();
                if(!$res){
                    $results['error'][$idx] = 'batch_action: '.self::$system->getErrorMsg();
                    continue 2;
                }

                if(!empty($res)){
                    $results['success'][$idx] = array_merge($results['success'][$idx], $res);
                    $total += count($res);
                }

                $new_terms = [];

                mysql__update_progress(null, $progress_session_id, false, "$row_count,$row_total");
            }
        }

        mysql__update_progress(null, $progress_session_id, false, "$row_total,$row_total");

        if(!empty($new_terms)){

            $def_terms->setData([
                'fields' => $new_terms,
                'term_separator' => $trm_Separator
            ]);

            $res = $def_terms->batch_action();
            if(!$res){
                $results['error'][$idx] = 'batch_action(2): '.self::$system->getErrorMsg();
                continue;
            }

            if(!empty($res)){
                $results['success'][$idx] = array_merge($results['success'][$idx], $res);
                $total += count($res);
            }
        }

        if(empty($results['error'][$idx])){
            unset($results['error'][$idx]);
        }else{
            $results['error'][$idx] = "Tab $tab_num:<br>" . implode('<br>', $results['error'][$idx]) . "<br>";
        }
    }

    $results['added'] = $total;

    mysql__update_progress(null, $progress_session_id, false, 'REMOVE');

    return $results;
}

//
// Insert a new column into each record row, with pre-filled data
//
public static function insertNewColumns($params){

    self::initialize();

    $mysqli = self::$mysqli;

    // Prepare data
    $imp_session = ImportSession::load(@$params['imp_ID']);
    if($imp_session==false){
        return false;
    }
    $import_table = $imp_session['import_table'];

    $col_name = @$params['column_name'];
    $col_data = USanitize::cleanupSpaces(@$params['column_data']);
    if(empty($col_name) || empty($col_data)){
        self::$system->addError(HEURIST_INVALID_REQUEST, 'Both a new column name and data are required');
        return false;
    }

    $new_column = count($imp_session['columns']);
    $new_column = "field_{$new_column}";

    $imp_session['columns'][] = $col_name;
    $imp_session['uniqcnt'][] = "1";

    $column_size = mb_strlen($col_data) + 5;

    // Update import table
    $add_column = "ALTER TABLE $import_table ADD $new_column VARCHAR($column_size)";
    $res = $mysqli->query($add_column);
    if(!$res){
        self::$system->addError(HEURIST_DB_ERROR, 'Failed to add the new column to the desired import table');
        return false;
    }

    $update_query = "UPDATE $import_table SET $new_column = ?";
    $res = mysql__exec_param_query($mysqli, $update_query, ['s', $col_data]);
    if($res !== true){
        self::$system->addError(HEURIST_DB_ERROR, 'Failed to update the column with provided data');
        return false;
    }

    // Update import session
    $res = ImportSession::save($imp_session);
    if(!$res){
        self::$system->addError(HEURIST_DB_ERROR, 'Failed to update import session with new column details');
        return false;
    }

    return true;
}

/**
 * Checks if the record is actually being updated with new values
 *
 * @param integer $rec_ID - record ID
 * @param array $record - record to be saved, needs to contain the 'details' key
 * @return bool whether the record can be considered to be updating
 */
private static function _isRecordUpdating($rec_ID, $record){

    $existing_record = recordSearchByID(self::$system, $rec_ID, false);
    $existing_details = recordSearchDetailsRaw(self::$system, $rec_ID);

    // Check if header fields are being updated, or if the record didn't have any values
    $has_scratchpad = !empty($record['details']['recordNotes']);
    $has_url = !empty($record['details']['recordURL']);
    if(empty($existing_details)
    || ($has_scratchpad && $record['details']['recordNotes'] != $existing_record['rec_ScratchPad'])
    || ($has_url && $record['details']['recordURL'] != $existing_record['rec_URL'])){
        return true;
    }

    $existing_values = [];
    $rtn = false;
    foreach($record['details'] as $dty_ID => $values){

        if(strpos($dty_ID, 't:') === false){
            // skip imp_ID, recordNotes and recordURL
            continue;
        }
        $dty_ID = str_replace('t:', '', $dty_ID);

        // Retrieve necessary values
        $check_values = null;
        if(array_key_exists($dty_ID, $existing_values)){
            $check_values = $existing_values[$dty_ID];
        }else{
            $check_values = array_filter($existing_details, function($detail) use ($dty_ID){
                return $detail['dtl_DetailTypeID'] == $dty_ID;
            });
            $existing_values[$dty_ID] = $check_values;
        }

        // Check whether values have been removed/added
        if(count($check_values) != count($values)){
            $rtn = true;
            break;
        }

        // Now check whether values have been updated
        $exists = array_filter($check_values, function($detail) use ($values){

            $value = !empty($detail['dtl_Value']) ? $detail['dtl_Value'] : '';
            $value = !empty($detail['dtl_Geo']) ? $detail['dtl_Geo'] : $value;
            $value = !empty($detail['dtl_UploadedFileID']) ? $detail['dtl_UploadedFileID'] : $value;

            return !empty($value) && in_array($value, $values);
        });

        if(count($exists) < count($values)){
            $rtn = true;
            break;
        }
    }

    return $rtn;
}
} //end class
?>
