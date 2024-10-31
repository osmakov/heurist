<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* recordsExportCSV.php - produces output to CSV format
*
* Controller is records_output
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/
use hserv\utilities\USystem;
use hserv\utilities\USanitize;
use hserv\entity\DbDefRecStructure;

require_once dirname(__FILE__).'/../../../vendor/autoload.php';//for geoPHP
require_once dirname(__FILE__).'/../../utilities/geo/mapSimplify.php';
require_once dirname(__FILE__).'/../../utilities/geo/mapCoordConverter.php';
require_once dirname(__FILE__).'/../../utilities/Temporal.php';
require_once dirname(__FILE__).'/../../structure/dbsTerms.php';

if(!defined('H_ID')){
    define('H_ID',' H-ID');
}

/**
*
*  setSession - work with different database
*  output - main method
*
*/
class RecordsExportCSV {

    private static $system = null;
    private static $mysqli = null;
    private static $initialized = false;
    private static $version = 3;

    private static $defRecTypes = null;
    private static $defDetailtypes = null;
    private static $defTerms = null;

//
//
//
private static function initialize()
{
    if (self::$initialized) {return;}

    global $system;
    self::$system  = $system;
    self::$mysqli = $system->get_mysqli();
    self::$initialized = true;
    self::$version = 3;
}

//
// set session different that current global one (to work with different database)
//
public static function setSession($system){
    self::$system  = $system;
    self::$mysqli = $system->get_mysqli();
    self::$initialized = true;
}

/*

output records as csv

$data  - recordset array('status'=>HEURIST_OK,
                                'data'=> array(
                                'queryid'=>@$params['id'],  //query unqiue id
                                'entityName'=>'Records',
                                'count'=>$total_count_rows,
                                'offset'=>get_offset($params),
                                'reccount'=>count($records),
                                'records'=>$records));

if parameter prefs.fields is defined it creates separate file for every record type

fields {rtid:{id, url, title, dt1, dt2, ....  dt4:resource_rt1, dt4:resource_rt2  } }

for constrained resource (record pointer) fields we use "dt#:rt#"
@todo for enum fields use dt#:code,dt#:id,dt#:label

NOTE: fastest way it simple concatenation in comparison to fputcsv and implode. We use fputcsv
*/
public static function output($data, $params){

    if (!($data && @$data['status']==HEURIST_OK)){
        print print_r($data, true);//print out error array
        return;
    }

    $data = $data['data'];

    if(!(@$data['reccount']>0)){
        print 'EMPTY RESULT SET';
        return;
    }

    self::initialize();

    $isJoinTable = (isset($params['prefs']['join_record_types']) && $params['prefs']['join_record_types']) ? true : false;

    // Get the main record type IDs.
    $mainRecordTypeIDs = [];
    if (isset($params['prefs']['main_record_type_ids'])) {
        $mainRecordTypeIDs = $params['prefs']['main_record_type_ids'];
    } else {
        print 'No field selected from the main record type';
        return;
    }

    $save_to_file = @$params['save_to_file'] == 1;

    $term_ids_only = (@$params['prefs']['term_ids_only']==1);
    $include_term_ids = (@$params['prefs']['include_term_ids']==1) || $term_ids_only;
    $include_term_codes = (@$params['prefs']['include_term_codes']==1) && !$term_ids_only;
    $include_resource_titles =  (@$params['prefs']['include_resource_titles']==1);
    $include_term_hierarchy = (@$params['prefs']['include_term_hierarchy']==1);
    $include_file_url = (@$params['prefs']['include_file_url']==1);
    $include_record_url_html = (@$params['prefs']['include_record_url_html']==1);
    $include_record_url_xml = (@$params['prefs']['include_record_url_xml']==1);
    $include_temporals = (@$params['prefs']['include_temporals']==1);

    $fields = @$params['prefs']['fields'];
    $details = array();//array of detail fields included into output
    $relmarker_details = array();//relmarker fields included into output

    // Handle final filename + directory
    $filename = basename('Export_'.self::$system->dbname());
    if(!empty(@$params['file']['filename'])){

        $filename = basename($params['file']['filename']);
        $filename = USanitize::sanitizeFileName($filename, false);
        $directory = @$params['file']['directory'];

        if(!empty($directory)){

            if(!folderExists($directory, true)){

                if(!$save_to_file) {
                    print "Unable to write to requested directory";
                    return;
                }
                return "Unable to write to requested directory";
            }

            $filename = rtrim($directory, '/') . "/$filename";
        }
    }

    if(self::$defRecTypes==null) {
        self::$defRecTypes = dbs_GetRectypeStructures(self::$system, null, 2);
    }
    $idx_name = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];
    $idx_dtype = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['dty_Type'];
    $idx_term_tree = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_FilteredJsonTermIDTree'];
    $idx_term_nosel = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];

    $defTerms = null;
    if(!$term_ids_only){
        $defTerms = dbs_GetTerms(self::$system);
        $defTerms = new DbsTerms(self::$system, $defTerms);
    }

    // Track column indices for advanced option fields.
    $groupFields = [];
    $sortFields = [];
    $countFields = [];
    $sumFields = [];
    $percentageFields = [];
    $groupColIndices = [];
    $sortColIndices = [];
    $sortOrders = [];
    $countColIndices = [];
    $sumColIndices = [];
    $percentageColIndices = [];

    $has_advanced = $isJoinTable;
    $need_check_memory = true;

    if (isset($params['prefs']['advanced_options']) || is_array($params['prefs']['advanced_options'])) {
        foreach ($params['prefs']['advanced_options'] as $fieldCode => $option) {
            $codeParts = explode(':', $fieldCode);
            if ($codeParts > 1) {
                $recordTypeID = $codeParts[count($codeParts) - 2];
                $fieldID = $codeParts[count($codeParts) - 1];
                $fieldKey = $recordTypeID . ':' . $fieldID;
                if (isset($option['total'])) {
                    if ($option['total'] === 'group') {
                        $groupFields[] = $fieldKey;
                        $has_advanced = true;
                    } elseif ($option['total'] === 'sum') {
                        $sumFields[]  = $fieldKey;
                        $has_advanced = true;
                    } elseif ($option['total'] === 'count') {
                        $countFields[]  = $fieldKey;
                        $has_advanced = true;
                    }
                }
                if (!empty($option['sort'])) {
                    $sortFields[$fieldKey] = $option['sort'];
                        $has_advanced = true;
                }

                if (isset($option['use_percentage']) && $option['use_percentage']) {
                    $percentageFields[] = $fieldKey;
                        $has_advanced = true;
                }
            }
        }


    }

    $memory_limit = USystem::getConfigBytes('memory_limit');

    //create header
    $any_rectype = null;
    $headers = array();
    $columnInfo = [];
    if($fields){
        foreach($fields as $rt=>$flds){

            if($flds[1] == 'rec_ID'){ // flip, so rec id is first
                list($flds[0], $flds[1]) = [$flds[1], $flds[0]];
            }

            //always include ID field into output
            if($flds[0]!='rec_ID') {array_unshift($flds, 'rec_ID');}
            $fields[$rt] = $flds;

            $details[$rt] = array();
            $headers[$rt] = array();
            $relmarker_details[$rt] = array();
            $columnInfo[$rt] = [];

            foreach($flds as $dt_id){

                $csvColIndex = null;
                $fieldFullID = $dt_id;

                $constr_rt_id = 0;
                if(strpos($dt_id,':')>0){ //for constrained resource (record pointer) fields
                    //example author:person or organization
                    list($dt_id, $constr_rt_id) = explode(':',$dt_id);
                }

                $field_name_title = '';

                if(is_numeric($dt_id) && $dt_id>0){

                    if($dt_id==DT_PARENT_ENTITY){
                        $field_name = 'Parent entity';
                        $field_type = 'resource';
                    }else{
                        //get field name from structure
                        $field_name = self::$defRecTypes['typedefs'][$rt]['dtFields'][$dt_id][$idx_name];
                        $field_type = self::$defRecTypes['typedefs'][$rt]['dtFields'][$dt_id][$idx_dtype];
                    }
                    if($constr_rt_id>0){
                        $rectypename_is_in_fieldname = (strpos(strtolower($field_name),
                            strtolower(self::$defRecTypes['names'][$constr_rt_id]))!==false);
                        $field_name_title = $field_name.($rectypename_is_in_fieldname
                            ?'':' ('.self::$defRecTypes['names'][$constr_rt_id].')').' Record Title';

                        $field_name = $field_name.($rectypename_is_in_fieldname
                            ?'':' ('.self::$defRecTypes['names'][$constr_rt_id].')').H_ID;
                    }else{
                        $field_name_title = $field_name.' Record Title';
                    }
                    if($field_type=='relmarker'){
                        $relmarker_details[$rt][$dt_id] = $constr_rt_id;
                    }else{
                        array_push($details[$rt], $dt_id);
                    }

                }else{
                    $field_type = null;

                    if($dt_id=='rec_ID'){
                        if($rt>0){
                            $field_name = self::$defRecTypes['names'][$rt].H_ID;
                        }else{
                            $field_name = 'H-ID';
                            $any_rectype = $rt;
                        }
                    }else{
                        $field_name = $dt_id; //record header field
                    }
                }

                if($field_type=='enum' || $field_type=='relationtype'){

                    if(!$term_ids_only){
                        array_push($headers[$rt], $field_name);//labels are always included by default
                        $csvColIndex = count($headers[$rt]) - 1;
                        $columnInfo[$rt][] = [
                            'index' => $csvColIndex,
                            'type' => 'value',
                            'field_id' => $fieldFullID,
                        ];
                    }

                    if($include_term_ids){
                        array_push($headers[$rt], $field_name.' ID');
                        $columnInfo[$rt][] = [
                            'index' => count($headers[$rt]) - 1,
                            'type' => 'term_id',
                            'field_id' => $fieldFullID,
                        ];
                    }

                    if($include_term_codes){
                        array_push($headers[$rt], $field_name.' StdCode' );
                        $columnInfo[$rt][] = [
                            'index' => count($headers[$rt]) - 1,
                            'type' => 'term_code',
                            'field_id' => $fieldFullID,
                        ];
                    }


                }else{
                    array_push($headers[$rt], $field_name);
                    $csvColIndex = count($headers[$rt]) - 1;
                    $columnInfo[$rt][] = [
                        'index' => $csvColIndex,
                        'type' => 'value',
                        'field_id' => $fieldFullID,
                    ];

                    if($include_temporals && $field_type=='date'){
                        array_push($headers[$rt], $field_name.'(temporal)');
                        $csvColIndex = count($headers[$rt]) - 1;
                        $columnInfo[$rt][] = [
                            'index' => $csvColIndex,
                            'type' => 'value',
                            'field_id' => $fieldFullID,
                        ];
                    }
                }

                if($dt_id == 'rec_ID'){
                    if($include_record_url_html){ // Record URL in HTML
                        array_push($headers[$rt], 'Record URL (HTML)' );
                        $columnInfo[$rt][] = [
                            'index' => count($headers[$rt]) - 1,
                            'type' => 'record_url_html',
                            'field_id' => $fieldFullID,
                        ];
                    }
                    if($include_record_url_xml){ // Record URL in XML
                        array_push($headers[$rt], 'Record URL (XML)' );
                        $columnInfo[$rt][] = [
                            'index' => count($headers[$rt]) - 1,
                            'type' => 'record_url_html',
                            'field_id' => $fieldFullID,
                        ];
                    }
                }

                if($field_type=='file'){ // Add extra details for files
                    array_push($headers[$rt], $field_name.' ID' );
                    $columnInfo[$rt][] = [
                        'index' => count($headers[$rt]) - 1,
                        'type' => 'file_id',
                        'field_id' => $fieldFullID,
                    ];
                    array_push($headers[$rt], $field_name.' Name' );
                    $columnInfo[$rt][] = [
                        'index' => count($headers[$rt]) - 1,
                        'type' => 'file_name',
                        'field_id' => $fieldFullID,
                    ];
                    array_push($headers[$rt], $field_name.' Path' );
                    $columnInfo[$rt][] = [
                        'index' => count($headers[$rt]) - 1,
                        'type' => 'file_path',
                        'field_id' => $fieldFullID,
                    ];
                    if($include_file_url){
                        array_push($headers[$rt], $field_name.' URL' );
                        $columnInfo[$rt][] = [
                            'index' => count($headers[$rt]) - 1,
                            'type' => 'file_url',
                            'field_id' => $fieldFullID,
                        ];
                    }
                }

                //add title for resource (record pointer) fields
                if($include_resource_titles && ($field_type=='resource' || $field_type=='relmarker')){

                    array_push($headers[$rt], $field_name_title);
                    $columnInfo[$rt][] = [
                        'index' => count($headers[$rt]) - 1,
                        'type' => 'value', //resource_title
                        'field_id' => $fieldFullID,
                    ];
                }

                // Save column index for advanced options.
                if ($csvColIndex !== null) {
                    $fieldKey = $rt . ':' . $dt_id;
                    if (in_array($fieldKey, $groupFields)) {
                        if (!isset($groupColIndices[$rt])) {
                            $groupColIndices[$rt] = [];
                        }
                        $groupColIndices[$rt][] = $csvColIndex;
                    }
                    if (in_array($fieldKey, $countFields)) {
                        if (!isset($countColIndices[$rt])) {
                            $countColIndices[$rt] = [];
                        }
                        $countColIndices[$rt][] = $csvColIndex;
                        $headers[$rt][$csvColIndex] = 'Count of ' . $headers[$rt][$csvColIndex];
                    }
                    if (in_array($fieldKey, $sumFields)) {
                        if (!isset($sumColIndices[$rt])) {
                            $sumColIndices[$rt] = [];
                        }
                        $sumColIndices[$rt][] = $csvColIndex;
                        $headers[$rt][$csvColIndex] = 'Sum of ' . $headers[$rt][$csvColIndex];
                    }
                    if (in_array($fieldKey, $percentageFields)) {
                        if (!isset($percentageColIndices[$rt])) {
                            $percentageColIndices[$rt] = [];
                        }
                        $percentageColIndices[$rt][] = $csvColIndex;
                    }
                    if (!empty($sortFields[$fieldKey])) {
                        if (!isset($sortColIndices[$rt])) {
                            $sortColIndices[$rt] = [];
                        }
                        if (!isset($sortOrders[$rt])) {
                            $sortOrders[$rt] = [];
                        }
                        $sortColIndices[$rt][] = $csvColIndex;
                        $sortOrders[$rt][] = $sortFields[$fieldKey];
                    }
                }
            }
        }
    }

    $csv_delimiter =  $params['prefs']['csv_delimiter']?$params['prefs']['csv_delimiter']:',';
    $csv_enclosure =  $params['prefs']['csv_enclosure']==null || $params['prefs']['csv_enclosure']==0?chr(0):$params['prefs']['csv_enclosure'];
    $csv_mvsep =  $params['prefs']['csv_mvsep']?$params['prefs']['csv_mvsep']:'|';
    $csv_linebreak =  $params['prefs']['csv_linebreak']?$params['prefs']['csv_linebreak']:'nix';//not used
    $csv_header =  $params['prefs']['csv_header']?$params['prefs']['csv_header']:true;

    //------------
    $records = $data['records'];

    $streams = array();//one per record type
    $rt_counts = array();
    $csvData = [];

    $error_log = array();
    $error_log[] = 'Total rec count '.count($records);

    $idx = 0;
    if(is_array($records))
    {
        while ($idx<count($records)){

        $recID = $records[$idx];
        $record = recordSearchByID(self::$system, $recID, false);
        $rty_ID = ($any_rectype!=null)?$any_rectype :$record['rec_RecTypeID'];

        $idx++;

        if(!@$fields[$rty_ID]) {continue;} //none of fields for this record type marked to output


        if($has_advanced){ // everything is putting into memory

            if (!isset($csvData[$rty_ID])) {
                $csvData[$rty_ID] = [];
                if($csv_header) {
                    $csvData[$rty_ID][] = $headers[$rty_ID];
                }

                $rt_counts[$rty_ID] = 1;
            } else {
                $rt_counts[$rty_ID]++;
            }

        }else {
            if(!@$streams[$rty_ID]){
                // create a temporary file
                $fd = fopen(TEMP_MEMORY, 'w');//less than 1MB in memory otherwise as temp file
                if (false === $fd) {
                    self::$system->error_exit_api('Failed to create temporary file for csv output');
                }
                $streams[$rty_ID] = $fd;

                //write header
                if($csv_header){
                    fputcsv($fd, $headers[$rty_ID], $csv_delimiter, $csv_enclosure);
                }

                $rt_counts[$rty_ID] = 1;
            }else{
                $fd = $streams[$rty_ID];

                $rt_counts[$rty_ID]++;
            }
        }

        if(!isEmptyArray(@$details[$rty_ID])){
            //fils $record
            recordSearchDetails(self::$system, $record, $details[$rty_ID]);
        }
        if(!isEmptyArray(@$relmarker_details[$rty_ID])){
            $related_recs = recordSearchRelated(self::$system, array($recID), 0);
            if(@$related_recs['status']==HEURIST_OK){
                $related_recs = $related_recs['data'];
            }else{
                $related_recs = array();
            }
        }else{
            $related_recs = array();
        }

        //prepare output array
        $record_row = array();
        foreach($fields[$rty_ID] as $dt_id){

            //suppl.fields for enum (terms) and resource (record pointer) fields
            $date_temporals = array();
            $enum_label = array();
            $enum_code = array();
            $resource_titles = array();
            $file_urls = array();
            $record_urls = array();
            $file_ids = array();
            $file_names = array();
            $file_paths = array();

            $constr_rt_id = 0;
            if(strpos($dt_id,':')>0){ //for constrained resource (record pointer) fields
                list($dt_id, $constr_rt_id) = explode(':', $dt_id);
            }

            if(is_numeric($dt_id) && $dt_id>0){

                if ($constr_rt_id>0 && @$relmarker_details[$rty_ID][$dt_id]==$constr_rt_id) {  //relation

                    $vals = array();

                    foreach($related_recs['direct'] as $relation){
                        $target_rt = $related_recs['headers'][$relation->targetID][1];
                        if( $constr_rt_id==$target_rt && $relation->trmID>0){ //contrained rt and allowed relation type

                            $all_terms = self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dt_id][$idx_term_tree];
                            $nonsel_terms = self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dt_id][$idx_term_nosel];
                            $is_allowed = VerifyValue::isValidTerm($all_terms, $nonsel_terms, $relation->trmID, $dt_id);

                            if($is_allowed){
                                //if record type among selected -  add record to list to be exported
                                //otherwise export only ID  as field "Rectype H-ID"
                                if($fields[$target_rt]){ //record type exists in output
                                    if(!in_array($relation->targetID, $records)){
                                        array_push($records, $relation->targetID);//add to be exported
                                    }
                                }
                                $vals[] = $relation->targetID;

                                if($include_resource_titles){
                                    $resource_titles[] = $related_recs['headers'][$relation->targetID][0];
                                }
                            }
                        }
                    }//foreach

                    //reverse will work only in case source record has detail id as in target
                    foreach($related_recs['reverse'] as $relation){
                        $source_rt = $related_recs['headers'][$relation->sourceID][1];
                        if( $constr_rt_id==$source_rt && $relation->trmID>0
                        && @self::$defRecTypes['typedefs'][$source_rt]['dtFields'][$dt_id]
                        ){ //contrained rt and allowed relation type

                            $all_terms = self::$defRecTypes['typedefs'][$source_rt]['dtFields'][$dt_id][$idx_term_tree];
                            $nonsel_terms = self::$defRecTypes['typedefs'][$source_rt]['dtFields'][$dt_id][$idx_term_nosel];
                            $is_allowed = VerifyValue::isValidTerm($all_terms, $nonsel_terms, $relation->trmID, $dt_id);

                            if($is_allowed){
                                //if record type among selected -  add record to list to be exported
                                //otherwise export only ID  as field "Rectype H-ID"
                                if($fields[$source_rt]){ //record type exists in output
                                    if(!in_array($relation->sourceID, $records)){
                                        array_push($records, $relation->sourceID);//add to be exported
                                    }
                                }
                                $vals[] = $relation->sourceID;

                                if($include_resource_titles){
                                    $resource_titles[] = $related_recs['headers'][$relation->sourceID][0];
                                }
                            }
                        }
                    }

                    $value = implode($csv_mvsep, $vals);

                    if($include_resource_titles && empty($vals)){ //empty value
                        $resource_titles[] = '';
                    }

                }else{

                    if($dt_id == DT_PARENT_ENTITY){
                        $dt_type = 'resource';
                    }else{
                        $dt_type = self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dt_id][$idx_dtype];
                    }

                    $values = @$record['details'][$dt_id];

                    if(isset($values)){

                        //$values = array_values($values);//get plain array
                        $vals = array();

                        if($dt_type=="resource"){

                            //if record type among selected -  add record to list to be exported
                            //otherwise export only ID (and optionally title)  as field "Rectype H-ID"
                            foreach($values as $val){
                                if( (!($constr_rt_id>0)) || $constr_rt_id==$val['type'] ){ //unconstrained or exact required rt

                                    if($fields[$val['type']]){ //record type exists in output
                                        if(!in_array($val['id'], $records)){
                                            array_push($records, $val['id']);//add to be exported
                                        }
                                    }
                                    $vals[] = $val['id'];

                                    if($include_resource_titles){
                                        $resource_titles[] = $val['title'];
                                    }
                                }
                            }
                        }elseif($dt_type=='geo'){
                            foreach($values as $val){
                                $vals[] = $val['geo']['wkt'];
                            }
                        }elseif($dt_type=='file'){
                            foreach($values as $val){

                                $vals[] = 'ulf_' . $val['file']['ulf_ObfuscatedFileID'];

                                $file_ids[] = $val['file']['ulf_ID'];
                                $file_names[] = !empty($val['file']['ulf_OrigFileName']) ? $val['file']['ulf_OrigFileName'] : ULF_REMOTE;//$val['file']['ulf_ExternalFileReference']

                                if(!empty($val['file']['fullPath'])){
                                    $file_paths[] = $val['file']['fullPath'];
                                }elseif(!empty($val['file']['ulf_ExternalFileReference'])){
                                    $file_paths[] = $val['file']['ulf_ExternalFileReference'];//ULF_REMOTE
                                }else{
                                    $file_paths[] = '';
                                }

                                if($include_file_url){
                                    if(@$val['file']['ulf_ExternalFileReference']){
                                        $file_urls[] = $val['file']['ulf_ExternalFileReference'];
                                    }else{
                                        $file_urls[] = HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&file='.$val['file']['ulf_ObfuscatedFileID'];
                                    }
                                }
                            }
                        }elseif($dt_type=='date'){
                            foreach($values as $val){
                                $vals[] = Temporal::toHumanReadable(trim($val));
                                if($include_temporals){
                                    $date_temporals[] = trim($val);
                                }
                            }
                        }elseif($dt_type=='enum' || $dt_type=='relationtype'){

                            if(!empty($defTerms) && !isEmptyArray($values) ){
                                foreach($values as $val){
                                    $enum_label[] = $defTerms->getTermLabel($val, $include_term_hierarchy);
                                    // @$defTerms[$val][$idx_term_label]?$defTerms[$val][$idx_term_label]:'';
                                    $enum_code[] = $defTerms->getTermCode($val);
                                    //@$defTerms[$val][$idx_term_code]?$defTerms[$val][$idx_term_code]:'';
                                }
                            }else{
                                $enum_label[] = '';
                                $enum_code[] = '';
                            }
                            $vals = $values;
                        }elseif($dt_type == 'freetext' && $dt_type == 'blocktext'){
                            // escape all line feed (\n) within text values, to avoid confusing the import
                            // freetext shouldn't have any, but just in case
                            foreach($values as $val){
                                $vals[] = str_replace("\n", "\\n", $val);
                            }
                        }else{
                            $vals = $values;
                        }

                        $value = implode($csv_mvsep, $vals);
                    }else{
                        $value = null;
                    }

                    //empty values
                    if($value === null){
                        if($dt_type=='enum' || $dt_type=='relationtype'){

                            $enum_label[] = '';
                            $enum_code[] = '';

                        }elseif($include_resource_titles && $dt_type=='resource'){
                            $resource_titles[] = '';
                        }elseif($dt_type=='file'){
                            $file_ids[] = '';
                            $file_names[] = '';
                            $file_paths[] = '';
                            if($include_file_url){
                                $file_urls[] = '';
                            }
                        }elseif($dt_type=='date' && $include_temporals){
                            $date_temporals[] = '';
                        }
                    }

                }

            }elseif($dt_id=='rec_Tags'){

                $value = recordSearchPersonalTags(self::$system, $recID);
                $value = ($value===null)?'':implode($csv_mvsep, $value);

            }elseif($dt_id=='rec_RecTypeName'){
                $value = self::$defRecTypes['names'][$rty_ID];
            }elseif($dt_id=='rec_ID'){
                $value = @$record[$dt_id];
                $rec_url_base = HEURIST_BASE_URL_PRO . '?db=' . HEURIST_DBNAME . '&recID=' . $value;
                if($include_record_url_html){ // html
                    $record_urls[] = $rec_url_base . '&fmt=html';
                }
                if($include_record_url_xml){ // xml
                    $record_urls[] = $rec_url_base;
                }
            }else{
                $value = @$record[$dt_id];//from record header
            }
            if($value===null) {$value = ''; }


            if(!isEmptyArray($enum_label)){
                if(!$term_ids_only) {$record_row[] = implode($csv_mvsep,$enum_label);}
                if($include_term_ids) {$record_row[] = $value;}
                if($include_term_codes) {$record_row[] = implode($csv_mvsep,$enum_code);}
            }else {
                $record_row[] = $value;

                // Additional Date Field
                if(!empty($date_temporals)){
                    $record_row[] = implode($csv_mvsep, $date_temporals);
                }

                // Additional File Fields
                if (!empty($file_ids)){
                    $record_row[] = implode($csv_mvsep,$file_ids);
                }
                if (!empty($file_names)){
                    $record_row[] = implode($csv_mvsep,$file_names);
                }
                if (!empty($file_paths)){
                    $record_row[] = implode($csv_mvsep,$file_paths);
                }

                if (!empty($resource_titles)){
                    $record_row[] = implode($csv_mvsep,$resource_titles);
                }elseif (!empty($file_urls)){
                    $record_row[] = implode($csv_mvsep,$file_urls);
                }elseif (!empty($record_urls)){
                    $record_row[] = implode($csv_delimiter,$record_urls);// two separate columns
                }

                if($value == '' && $dt_type=="resource" && $include_resource_titles && empty($resource_titles)){ // to avoid mismatched rows when adding details
                    $record_row[] = $value;
                }
            }

        }//for fields

        // write the data to csv
        if(!isEmptyArray($record_row)) {
            if($has_advanced){
                $csvData[$rty_ID][] = $record_row;

                if($need_check_memory){
                    $cnt = count($records);

                    if($cnt>2000){

                        if(strlen(implode(',',$record_row))*$cnt*1.5>$memory_limit){
                            self::$system->error_exit_api('Sorry, this export exceeds the limit set for this server. Please reduce the number of records or the number of fields selected');
                        }

                    }
                    $need_check_memory = false;
                }


            }else{
                fputcsv($fd, $record_row, $csv_delimiter, $csv_enclosure);
            }
        }

    }//for records
    }
    // Join csv tables.
    if ($isJoinTable && !empty($mainRecordTypeIDs)) {
        $mainRecordTypeID = $mainRecordTypeIDs[0];
        if (!empty($csvData[$mainRecordTypeID]) && !empty($columnInfo[$mainRecordTypeID])) {
            $csvData = [
                $mainRecordTypeID => self::createJointCSVTables($csvData, $columnInfo, $mainRecordTypeID, $csv_mvsep, $csv_header),
            ];

            // Change advanced option column indices.
            $groupColIndices = self::changeAdvancedOptionColumnIndex($groupColIndices, $mainRecordTypeID, $columnInfo);
            $sumColIndices = self::changeAdvancedOptionColumnIndex($sumColIndices, $mainRecordTypeID, $columnInfo);
            $countColIndices = self::changeAdvancedOptionColumnIndex($countColIndices, $mainRecordTypeID, $columnInfo);
            $percentageColIndices = self::changeAdvancedOptionColumnIndex($percentageColIndices, $mainRecordTypeID, $columnInfo);
            $sortColIndices = self::changeAdvancedOptionColumnIndex($sortColIndices, $mainRecordTypeID, $columnInfo);
            $sortOrders = self::changeSortOrders($sortOrders, $mainRecordTypeID);
        }
    }

    // Save data to streams.
    if ($has_advanced && !empty($csvData)) {
        foreach ($csvData as $recordTypeID => $rows) {
            $streams[$recordTypeID] = fopen(TEMP_MEMORY, 'w');

            if (is_array($rows) && !empty($rows)) {
                if ($csv_header) {
                    $headerRow = array_shift($rows);
                    if (!empty($percentageColIndices[$recordTypeID])) {
                        $headerRow = self::usePercentageForCSVHeaders($headerRow, $percentageColIndices[$recordTypeID]);
                    }
                    fputcsv($streams[$recordTypeID], $headerRow, $csv_delimiter, $csv_enclosure);
                }
                // Apply advanced options.
                if (!empty($groupColIndices[$recordTypeID])) {
                    $sumCols = empty($sumColIndices[$recordTypeID]) ? [] : $sumColIndices[$recordTypeID];
                    $countCols = empty($countColIndices[$recordTypeID]) ? [] : $countColIndices[$recordTypeID];
                    $rows = self::groupCSVRows($rows, $groupColIndices[$recordTypeID], $sumCols, $countCols);
                }
                if (!empty($percentageColIndices[$recordTypeID])) {
                    $rows = self::usePercentageForCSVRows($rows, $percentageColIndices[$recordTypeID]);
                }
                if (!empty($sortColIndices[$recordTypeID]) && is_array($sortColIndices[$recordTypeID])) {
                    // Mutate col indices as new columns inserted.
                    for ($i = 0; $i < count($sortColIndices[$recordTypeID]); $i++) {
                        $colIndex = $sortColIndices[$recordTypeID][$i];
                        foreach ($percentageColIndices[$recordTypeID] as $percentageColIndex) {
                            if ($colIndex > $percentageColIndex) {
                                $sortColIndices[$recordTypeID][$i]++;
                            }
                        }
                    }
                    $rows = self::sortCSVRows($rows, $sortColIndices[$recordTypeID], $sortOrders[$recordTypeID]);
                }

                foreach ($rows as $row) {
                    fputcsv($streams[$recordTypeID], $row, $csv_delimiter, $csv_enclosure);
                }
            }
        }
    }//$has_advanced


    //calculate number of streams with columns more than one
    $count_streams = 0;
    foreach($headers as $rty_ID => $columns){
        if(is_array($columns) && count($columns)>1){
            $count_streams++;
        }
    }

    $error_log[] = print_r($rt_counts, true);

    return self::writeResults( $streams, $filename, $headers, $error_log, $save_to_file );
} //output

//
// output rectordtype template as csv (and terms pckup list)
//
public static function output_header($data, $params)
{

    self::initialize();

    $include_term_ids = (@$params['prefs']['include_term_ids']==1);
    $include_term_codes = (@$params['prefs']['include_term_codes']==1);
    $include_resource_titles =  (@$params['prefs']['include_resource_titles']==1);
    $include_term_hierarchy = (@$params['prefs']['include_term_hierarchy']==1);
    $include_file_url = (@$params['prefs']['include_file_url']==1);
    $include_record_url_html = (@$params['prefs']['include_record_url_html']==1);
    $include_record_url_xml = (@$params['prefs']['include_record_url_xml']==1);
    $include_temporals = (@$params['prefs']['include_temporals']==1);
    $output_rows = (@$params['prefs']['output_rows'] == 1);// default output details as columns

    $fields = @$params['prefs']['fields'];
    $details = array();//array of detail fields included into output
    $relmarker_details = array();//relmarker fields included into output
    $fld_type_names = dbs_GetDtLookups();
    $base_fld_names = dbs_GetDetailTypes(self::$system, null, 0);

    if(self::$defRecTypes==null) {
        self::$defRecTypes = dbs_GetRectypeStructures(self::$system, null, 2);
    }
    $idx_cid = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['dty_ConceptID'];
    $idx_name = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];
    $idx_dtype = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['dty_Type'];
    $idx_count = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_MaxValues'];
    $idx_require = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_RequirementType'];
    $idx_term_tree = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_FilteredJsonTermIDTree'];
    $idx_term_nosel = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];

    $fld_usages = array();
    $header_details = array('Field ID', 'Field name', 'Field type', 'Multivalue', 'Requirement', 'Usage count', 'Concept ID', 'Base name');// field details being exported
    $defRecStructure = new DbDefRecStructure(self::$system, null);
    $rst_data = array('a' => 'counts', 'mode' => 'rectype_field_usage', 'get_meta_counts' => 1, 'rtyID' => null);

    //create header
    $any_rectype = null;
    $headers = array();
    $fld_details = array();
    $terms_pickup = array();
    if($fields){
        foreach($fields as $rt=>$flds){

            if($flds[1] == 'rec_ID'){ // flip, so rec id is first
                list($flds[0], $flds[1]) = [$flds[1], $flds[0]];
            }

            //always include ID field into output
            if($flds[0]!='rec_ID') {array_unshift($flds, 'rec_ID');}
            $fields[$rt] = $flds;

            $details[$rt] = array();
            $headers[$rt] = array();
            $fld_details[$rt] = array();
            $relmarker_details[$rt] = array();

            // Get field usages
            if($rt > 0 && !array_key_exists($rt, $fld_usages)){
                // update rectype id
                $rst_data['rtyID'] = $rt;
                $defRecStructure->setData($rst_data);
                // retrieve usages
                $cnt_res = $defRecStructure->run();
                // save
                $fld_usages[$rt] = $cnt_res !== false ? $cnt_res : self::$system->getErrorMsg();
                //$fld_usages[$rt] = $cnt_res !== false ? $cnt_res : array();
            }

            foreach($flds as $dt_id){

                $constr_rt_id = 0;
                if(strpos($dt_id,':')>0){ //for constrained resource (record pointer) fields
                    //example author:person or organization
                    list($dt_id, $constr_rt_id) = explode(':',$dt_id);
                }

                $field_name_title = '';

                if(is_numeric($dt_id) && $dt_id>0){

                    if($dt_id==DT_PARENT_ENTITY){
                        $field_name = 'Parent entity';
                        $field_type = 'resource';
                    }else{
                        //get field name from structure
                        $field_name = self::$defRecTypes['typedefs'][$rt]['dtFields'][$dt_id][$idx_name];
                        $field_type = self::$defRecTypes['typedefs'][$rt]['dtFields'][$dt_id][$idx_dtype];
                    }
                    if($constr_rt_id>0){
                        $rectypename_is_in_fieldname = (strpos(strtolower($field_name),
                                            strtolower(self::$defRecTypes['names'][$constr_rt_id]))!==false);
                        $field_name_title = $field_name.' '
                                                //.($rectypename_is_in_fieldname?'':(self::$defRecTypes['names'][$constr_rt_id].' '))
                                                .'RecordTitle';
                        $field_name = $field_name.($rectypename_is_in_fieldname
                                            ?'':' ('.self::$defRecTypes['names'][$constr_rt_id].')').H_ID;
                    }else{
                        $field_name_title = $field_name.' RecordTitle';
                    }
                    if($field_type=='relmarker'){
                        $relmarker_details[$rt][$dt_id] = $constr_rt_id;
                    }else{
                        array_push($details[$rt], $dt_id);
                    }

                }else{
                    //record header
                    $field_type = null;

                    if($dt_id=='rec_ID'){
                        if($rt>0){
                            $field_name = self::$defRecTypes['names'][$rt].H_ID;
                        }else{
                            $field_name = 'H-ID';
                            $any_rectype = $rt;
                        }
                    }else{
                        $field_name = $dt_id; //record header field
                    }
                }

                if($field_type=='separator'){ // skip separator
                    continue;
                }

                $fld = self::$defRecTypes['typedefs'][$rt]['dtFields'][$dt_id];
                $count = $fld[$idx_count] != 1 ? 'Multivalue' : 'Single';
                $typename = !empty($fld_type_names[$field_type]) ? $fld_type_names[$field_type] : 'Built-in';
                $requirement = $fld[$idx_require];
                $usage = is_array($fld_usages[$rt]) && array_key_exists($dt_id, $fld_usages[$rt]) ? $fld_usages[$rt][$dt_id] : 0;
                $concept_id = $fld[$idx_cid];
                $base_name = $base_fld_names['names'][$dt_id];

                if($requirement == ''){
                    if($dt_id == 'rec_ID'){
                        $requirement = 'required';
                    }else{
                        $requirement = 'optional';
                    }
                }

                array_push($fld_details[$rt], array($dt_id, $field_name, $typename, $count, ucfirst($requirement), "N=$usage", $concept_id, $base_name));

                if($field_type=='enum' || $field_type=='relationtype'){

                    array_push($headers[$rt], $field_name);// labels are always included

                    if($include_term_ids){
                        array_push($headers[$rt], $field_name.' ID');
                    }

                    if($include_term_codes){
                        array_push($headers[$rt], $field_name.' StdCode' );
                    }

                    //add terms pickup list
                    if(!@$terms_pickup[$rt]) {$terms_pickup[$rt] = array();}
                    $terms_pickup[$rt][$dt_id] = array('name'=>$field_name, 'domain'=>$field_type,
                                             'term_ids'=>self::$defRecTypes['typedefs'][$rt]['dtFields'][$dt_id][$idx_term_tree],
                                             'nonsel'=>self::$defRecTypes['typedefs'][$rt]['dtFields'][$dt_id][$idx_term_tree]);

                }else{
                    array_push($headers[$rt], $field_name);
                    if($include_temporals && $field_type=='date'){
                        array_push($headers[$rt], $field_name.'(temporal)');
                    }
                }

                //add title for resource (record pointer) fields
                if($include_resource_titles && ($field_type=='resource' || $field_type=='relmarker')){
                    array_push($headers[$rt], $field_name_title);
                }
            }
        }
    }


    if(!isEmptyArray($terms_pickup)) {
        $defTerms = dbs_GetTerms(self::$system);
        $defTerms = new DbsTerms(self::$system, $defTerms);
    }


    $csv_delimiter =  $params['prefs']['csv_delimiter']?$params['prefs']['csv_delimiter']:',';
    $csv_enclosure =  $params['prefs']['csv_enclosure']?$params['prefs']['csv_enclosure']:'"';

    $streams = array();//one per record type

    $temp_name = null;
    $print_header = true;
    //------------
    foreach($headers as $rty_ID => $columns){

        $placeholders = null;
        $cnt_cols = count($columns);
        if($cnt_cols>1){
            if(!@$fields[$rty_ID]) {continue;} //none of fields for this record type marked to output

            //prepare terms
            if(is_array(@$terms_pickup[$rty_ID])){  //there are enum fields for this rt

                $max_count = 0;
                $placeholders = array();

                foreach($terms_pickup[$rty_ID] as $dtid => $field){

                    $placeholders[] = strtoupper($field['name']);
                    $ph_help[] = '<Use to create value control lists>';
                    //get list of terms
                    $vocabId = $field['term_ids'];
                    $terms = $defTerms->treeData($vocabId, 3);
                    array_unshift($terms, $vocabId);
                    $max_count = max($max_count, count($terms));
                    $terms_pickup[$rty_ID][$dtid]['terms'] = $terms;
                }
            }

            $fd = fopen(TEMP_MEMORY, 'w');//less than 1MB in memory otherwise as temp file
            $streams[$rty_ID] = $fd;

            $header = $headers[$rty_ID];
            if($output_rows){
                $header = $header_details;
            }

            //write header
            if($print_header){
                fputcsv($fd, $header, $csv_delimiter, $csv_enclosure);
                //fwrite($fd, "\n\n");

                $print_header = $output_rows ? false : true; // print header once for rows output
            }

            //write field details
            if(array_key_exists($rty_ID, $fld_details)){

                if($output_rows){
                    foreach ($fld_details[$rty_ID] as $details) {
                        fputcsv($fd, $details, $csv_delimiter, $csv_enclosure);
                    }
                }else{

                    $max = count($header_details);
                    $idx = 1; // ignore field name
                    while($idx < $max){

                        $dtl_row = array();
                        foreach($fld_details[$rty_ID] as $dtls){
                            array_push($dtl_row, $dtls[$idx]);
                        }

                        fputcsv($fd, $dtl_row, $csv_delimiter, $csv_enclosure);

                        $idx ++;
                    }
                }

                fwrite($fd, "\n\n");
            }

            //write terms
            if($placeholders!=null){

                fputcsv($fd, $placeholders, $csv_delimiter, $csv_enclosure);
                fputcsv($fd, $ph_help, $csv_delimiter, $csv_enclosure);

                $k = 0;
                while ($k<$max_count){

                    $placeholders = array(); //no need to create empty columns: array_fill(0, $cnt_cols, '')

                    foreach($terms_pickup[$rty_ID] as $dtid => $field){

                        $terms = $terms_pickup[$rty_ID][$dtid]['terms'];

                        if($k<count($terms)){
                            $placeholders[] =  $defTerms->getTermLabel($terms[$k], true);
                        }else{
                            $placeholders[] = '';
                        }
                    }//for fields

                    fputcsv($fd, $placeholders, $csv_delimiter, $csv_enclosure);

                    $k++;
                }//while

            }

            if($temp_name==null){
                $temp_name = 'Heurist_'.self::$system->dbname();//.'_t'.$rty_ID.'_'.self::$defRecTypes['names'][$rty_ID];
            }
        }
    }
    self::writeResults( $streams, $temp_name, $headers, null );
}


//
// save CSV streams into file and zip
//
private static function writeResults( $streams, $temp_name, $headers, $error_log, $save_to_file=false ) {

    if(is_array($streams) && count($streams)<2){

        $out = false;
        $rty_ID = 0;

        if(empty($streams)){
            if($error_log) {array_push($error_log, "Streams are not defined");}
        }else{
            $rty_ID = array_keys($streams);
            $rty_ID = intval($rty_ID[0]);

            if(!$save_to_file || empty($temp_name)){

                $csv_filename = basename($temp_name);
                if($rty_ID>0){
                    $rty_Name = mb_ereg_replace('\s', '_', self::$defRecTypes['names'][$rty_ID]);
                    $csv_filename = basename(USanitize::sanitizeFileName($csv_filename.'_t'.$rty_ID.'_'.$rty_Name));
                }
            }
            
            $ext = pathinfo($csv_filename, PATHINFO_EXTENSION);
            if(!(strlen($ext)>0 && preg_match('/^[a-zA-Z0-9]+$/', $ext))){
                $csv_filename .= '.csv';    
            }

            $csv_filename = basename($csv_filename);

            $fd = $streams[$rty_ID];

            if($fd==null){
                if($error_log) {array_push($error_log, "Stream for record type $rty_ID is not defined");}
            }else{
                rewind($fd);
                $out = stream_get_contents($fd);
                fclose($fd);
            }
        }

        $has_error = false;

        if( !isset($out) || $out===false || strlen($out)==0){
            $out = "Stream for record type $rty_ID is empty";
            if($error_log) {
                array_push($error_log, $out);
                $out = implode(PHP_EOL, $error_log);
            }
        }

        //header('Content-Description: File Transfer');
        //header('Content-Type: application/octet-stream');
        //header('Content-Transfer-Encoding: binary');


        $content_len = strlen($out);
        if(!($content_len>0)) {$content_len = 0;}

        if($save_to_file){
            if($content_len > 0){ // save csv/error log to file
                $content_len = fileSave($out, $temp_name);
            }
            if($has_error){
                $content_len *= -1;
            }
            return $content_len;
        }

        $content_len = $content_len+3;

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename='.rawurlencode($csv_filename));
        header(CONTENT_LENGTH . $content_len);
        echo "\xEF\xBB\xBF";// Byte Order Mark
        exit($out);

    }else{

        $zipname = $temp_name.'_'.date("YmdHis").'.zip';
        $destination = tempnam(HEURIST_SCRATCHSPACE_DIR, "zip");

        $zip = new ZipArchive();
        if (!$zip->open($destination, ZIPARCHIVE::OVERWRITE)) {
            array_push($error_log, "Cannot create zip $destination");
        }else{
            $is_first = true;

            foreach($streams as $rty_ID => $fd){

                if($fd==null){
                    array_push($error_log, "Stream for record type $rty_ID is not defined");
                }else{
                    // return to the start of the stream
                    rewind($fd);

                    if($is_first || (is_array($headers[$rty_ID]) && count($headers[$rty_ID])>1)){
                        $is_first = false;

                        $content = stream_get_contents($fd);

                        if($content===false || strlen($content)==0){
                            array_push($error_log, "Stream for record type $rty_ID is empty");
                        }else{
                            // add the in-memory file to the archive, giving a name
                            $rty_Name = mb_ereg_replace('\s', '_', self::$defRecTypes['names'][$rty_ID]);
                            $zip->addFromString('rectype-'.$rty_ID.'-'.$rty_Name.'.csv',  $content);
                        }

                    }
                    //close the file
                    fclose($fd);
                }
            }

            if(!isEmptyArray($error_log)){
                $zip->addFromString('log.txt', implode(PHP_EOL, $error_log) );
            }

            // close the archive
            $zip->close();
        }

        if(@file_exists($destination)>0){

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename='.$zipname);
            header(CONTENT_LENGTH . filesize($destination));
            readfile($destination);

            // remove the zip archive
            unlink($destination);

        }else{
            array_push($error_log, "Zip archive ".$destination." doesn't exist");

            $out = implode(PHP_EOL, $error_log);
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename=log.txt');
            header(CONTENT_LENGTH . strlen($out));
            exit($out);

        }

    }

}

/**
 * Group by columns for exported CSV rows.
 *
 * @param array $rows The CSV row data.
 * @param array $groupColIndices The indices of the group by columns.
 * @param array $sumColIndices The indices of the columns applied with SUM private static function.
 * @param array $countColIndices The indices of the columns applied with COUNT private static function.
 *
 * @return array The grouped CSV rows.
 */
private static function groupCSVRows(array $rows, array $groupColIndices = [], array $sumColIndices = [], array $countColIndices = []) {
    if (!empty($groupColIndices)) {
        $groupedRows = [];
        foreach ($rows as $row) {
            $findRowIndex = -1;
            for ($i = 0; $i < count($groupedRows); $i++) {
                $isMatch = true;
                for ($j = 0; $j < count($groupColIndices); $j++) {
                    if ($groupedRows[$i][$groupColIndices[$j]] !== $row[$groupColIndices[$j]]) {
                        $isMatch = false;
                        break;
                    }
                }
                if ($isMatch) {
                    $findRowIndex = $i;
                    break;
                }
            }
            if ($findRowIndex >= 0) {
                for ($i = 0; $i < count($countColIndices); $i++) {
                    $groupedRows[$findRowIndex][$countColIndices[$i]] += 1;
                }
                for ($i = 0; $i < count($sumColIndices); $i++) {
                    $groupedRows[$findRowIndex][$sumColIndices[$i]] += self::valueToNumeric($row[$sumColIndices[$i]]);
                }
            } else {
                for ($i = 0; $i < count($countColIndices); $i++) {
                    $row[$countColIndices[$i]] = 1;
                }
                for ($i = 0; $i < count($sumColIndices); $i++) {
                    $row[$sumColIndices[$i]] = self::valueToNumeric($row[$sumColIndices[$i]]);
                }
                $groupedRows[] = $row;
            }
        }
        return $groupedRows;
    } else {
        return $rows;
    }
}

private static function usePercentageForCSVHeaders(array $headers, array $usePercentageColIndices = []) {
    if (!empty($usePercentageColIndices)) {
        $colIncrease = 0;
        for ($i = 0; $i < count($usePercentageColIndices); $i++) {
            $colIndex = $usePercentageColIndices[$i] + $colIncrease;
            if ($colIndex + 1 > count($headers) - 1) {
                $headers[] = $headers[$colIndex] . '(%)';
            } else {
                array_splice($headers, $colIndex + 1, 0, [$headers[$colIndex] . '(%)']);
            }
            $colIncrease++;
        }
    }
    return $headers;
}

/**
 * Calculate the percentage value of the specified columns in the CSV rows.
 *
 * @param array $rows The CSV row data.
 * @param array $usePercentageColIndices The indices of the columns to calculate the percentages.
 *
 * @return array The CSV rows with the percentage values calculated.
 */
private static function usePercentageForCSVRows(array $rows, array $usePercentageColIndices = []) {
    if (!empty($usePercentageColIndices)) {
        $colTotal = [];
        for ($i = 0; $i < count($rows); $i++) {
            for ($j = 0; $j < count($usePercentageColIndices); $j++) {
                $colIndex = $usePercentageColIndices[$j];
                if (!isset($colTotal[$colIndex])) {
                    $colTotal[$colIndex] = self::valueToNumeric($rows[$i][$colIndex]);
                } else {
                    $colTotal[$colIndex] += self::valueToNumeric($rows[$i][$colIndex]);
                }
            }
        }
        for ($i = 0; $i < count($rows); $i++) {
            $colIncrease = 0;
            for ($j = 0; $j < count($usePercentageColIndices); $j++) {
                $colIndex = $usePercentageColIndices[$j];
                if($colTotal[$colIndex]>0){
                    $percentage = round(self::valueToNumeric($rows[$i][$colIndex + $colIncrease]) / $colTotal[$colIndex], 4) * 100;
                }else{
                    $percentage = 0;
                }
                if ($colIndex + 1 > count($rows[$i]) - 1) {
                    $rows[$i][] = $percentage;
                } else {
                    array_splice($rows[$i], $colIndex + $colIncrease + 1, 0, [$percentage]);
                }
                $colIncrease++;
            }
        }
    }
    return $rows;
}

/**
 * Sort the CSV rows by specified columns and orders.
 *
 * @param array $rows The CSV row data.
 * @param array $sortByColIndices The indices of the columns to sort.
 * @param array $sortOrders The sort orders of the specified columns. Each element can either
 *   be 'asc' or 'des'.
 *
 * @return array The sorted CSV rows.
 */
private static function sortCSVRows(array $rows, array $sortByColIndices = [], array $sortOrders = []) {
    if (!empty($sortByColIndices)) {
        usort($rows, function($a, $b) use ($sortByColIndices, $sortOrders) {
            $result = 0;
            for ($i = 0; $i < count($sortByColIndices); $i++) {
                $sortByColIndex = $sortByColIndices[$i];
                $isAscending = true;
                if (isset($sortOrders[$i])) {
                    $isAscending = ($sortOrders[$i] === 'asc');
                }
                if (is_numeric($a[$sortByColIndex]) && is_numeric($b[$sortByColIndex])) {
                    if ($a[$sortByColIndex] == $b[$sortByColIndex]) {
                        $result = 0;
                    } else {
                        $result = $a[$sortByColIndex] < $b[$sortByColIndex] ? -1 : 1;
                        if (!$isAscending) {
                            $result = -$result;
                        }
                        break;
                    }
                } else {
                    $result = strcmp($a[$sortByColIndex], $b[$sortByColIndex]);
                    if ($result !== 0) {
                        if (!$isAscending) {
                            $result = -$result;
                        }
                        break;
                    }
                }
            }
            return $result;
        });
    }
    return $rows;
}

/**
 * Create the joint CSV data from multiple record types.
 *
 * @param array $csvData The original CSV data divided by record types.
 * @param array $columnInfo The column information of the original CSV data by
 *   record types.
 * @param int $mainRecordTypeID The ID of the root record type to export.
 * @param string $filedValueDelimiter The delimiter used for multi-value field.
 * @param bool $includeHeader Whether the header included in the CSV data.
 *
 * @return array The joint CSV data.
 */
private static function createJointCSVTables($csvData, &$columnInfo, $mainRecordTypeID, $filedValueDelimiter, $includeHeader = true) {
    $csvRows = $csvData[$mainRecordTypeID];

    // Create join table lookups.
    $csvRowLookups = [];
    foreach ($csvData as $recordTypeID => $rows) {
        if ($recordTypeID != $mainRecordTypeID) {
            if (!isset($csvRowLookups[$recordTypeID])) {
                $csvRowLookups[$recordTypeID] = [];
            }

            if ($includeHeader) {
                $csvRowLookups[$recordTypeID]['header'] = array_shift($rows); //$rows[0];
            }

            foreach ($rows as $row) {
                $csvRowLookups[$recordTypeID][$row[0]] = $row;
            }
        }
    }

    $jointRows = [];

    foreach ($csvRows as $row) {
        $jointRow = [];
        $recordTypeIDTrack = [];
        self::createJointCSVRow($jointRow, $row, $mainRecordTypeID, $columnInfo, $csvRowLookups, $recordTypeIDTrack, $filedValueDelimiter);
        $jointRows[] = $jointRow;
    }

    return $jointRows;
}

/**
 * Create a row for the joint CSV.
 *
 * @param array $jointRow Passed in reference. It will contain the data for the row
 *   after the private static function is finished.
 * @param array $row The row data from the original CSV data.
 * @param int $recordTypeID The record type ID of the original row data.
 * @param array $columnInfo The full array containing the original column information.
 *   It will contain the new column indices in the joint CSV after the private static function is finished.
 * @param array $csvRowLookups The lookup array for each record type keyed by record ID.
 * @param array $recordTypeIDTrack The array which keeps a track of the record type IDs have
 *   been joint.
 * @param string $filedValueDelimiter The delimiter used for multi-value field.
 * @param int $level The depth of the current record type.
 */
private static function createJointCSVRow(&$jointRow, $row, $recordTypeID, &$columnInfo, $csvRowLookups, &$recordTypeIDTrack, $filedValueDelimiter, $level = 1) {
    $recordTypeIDTrack[] = (int) $recordTypeID;
    foreach ($columnInfo[$recordTypeID] as &$colInfo) {
        if (strpos($colInfo['field_id'], ':') === false) {
            if ($level === 1 || $colInfo['index'] !== 0) {
                $jointRow[] = $row[$colInfo['index']];
                $colInfo['joint_column_index'] = count($jointRow) - 1;
            }
        } else {
            $fieldIDParts = explode(':', $colInfo['field_id']);
            $targetRecordTypeID = $fieldIDParts[count($fieldIDParts) - 1];
            if (false && in_array((int) $targetRecordTypeID, $recordTypeIDTrack) && $colInfo['type'] !== 'resource_title') {
                $jointRow[] = $row[$colInfo['index']];
                $colInfo['joint_column_index'] = count($jointRow) - 1;
            } else {
                if ($colInfo['type'] === 'value') {
                    $jointRow[] = $row[$colInfo['index']];
                    $colInfo['joint_column_index'] = count($jointRow) - 1;
                    $targetRecordID = $row[$colInfo['index']];
                    if (!empty($targetRecordID) && !is_numeric($targetRecordID) && strpos($targetRecordID, $filedValueDelimiter) === false) {
                        $targetRecordID = 'header';
                    }
                    if (empty($targetRecordID)) {
                        $jointRow = array_merge($jointRow, self::generateEmptyCellsForTargetRecordType($targetRecordTypeID, $csvRowLookups));
                    } else {
                        $targetRow = self::findInCSVRowLookup($targetRecordID, $targetRecordTypeID, $csvRowLookups, $filedValueDelimiter);
                        if ($targetRow) {
                            self::createJointCSVRow($jointRow, $targetRow, $targetRecordTypeID, $columnInfo, $csvRowLookups, $recordTypeIDTrack, $filedValueDelimiter, $level + 1);
                        } else {
                            $jointRow = array_merge($jointRow, self::generateEmptyCellsForTargetRecordType($targetRecordTypeID, $csvRowLookups));
                        }
                    }
                }
            }
        }
    }
}

/**
 * Lookup the row data based on the record ID.
 *
 * @param string $recordIDLiteral The record ID. It could be multiple IDs separated
 *   by the delimiter.
 * @param string $targetRecordTypeID The ID of the target record type.
 * @param string $csvRowLookups The lookup data.
 * @param string $filedValueDelimiter The delimiter used for multiple IDs.
 *
 * @return array|bool
 */
private static function findInCSVRowLookup($recordIDLiteral, $targetRecordTypeID, $csvRowLookups, $filedValueDelimiter) {
    $recordIDs = explode($filedValueDelimiter, $recordIDLiteral);
    $lookupRow = [];
    foreach ($recordIDs as $recordID) {
        if (isset($csvRowLookups[$targetRecordTypeID][$recordID])) {
            foreach ($csvRowLookups[$targetRecordTypeID][$recordID] as $index => $item) {
                if (!isset($lookupRow[$index])) {
                    $lookupRow[$index] = [];
                }
                $lookupRow[$index][] = $item;
            }
        }
    }
    if (empty($lookupRow)) {
        return false;
    } else {
        $concatRow = [];
        foreach ($lookupRow as $values) {
            $concatRow[] = implode($filedValueDelimiter, $values);
        }
        return $concatRow;
    }
}

/**
 * Generate an array of empty strings for a target record type.
 *
 * This private static function is used to generate empty cells in the joint CSV when the
 * reference value is empty, or the reference value can be found from the
 * lookup data.
 *
 * @param string $targetRecordTypeID The ID of the target record type.
 * @param array $csvRowLookups The lookup data.
 *
 * @return array
 */
private static function generateEmptyCellsForTargetRecordType($targetRecordTypeID, $csvRowLookups) {
    $cells = [];
    if (!empty($csvRowLookups[$targetRecordTypeID])) {
        $length = count(reset($csvRowLookups[$targetRecordTypeID])) - 1;
        for ($i = 0; $i < $length; $i++) {
            $cells[] = "";
        }
    }
    return $cells;
}

/**
 * Change the column index array after the CSV data is joint.
 *
 * This private static function will change the column indices of advanced option to the
 * new indices in the joint CSV table.
 *
 * @param array $columnIndices The original column indices of advanced option.
 * @param array $mainRecordTypeID The ID of the root record type to export.
 * @param array $columnInfo The column information. After the csv data is joined,
 *   this array contains the new column indices in the joint CSV table.
 *
 * @return array The new column indices.
 */
private static function changeAdvancedOptionColumnIndex($columnIndices, $mainRecordTypeID, $columnInfo) {
    if (empty($columnIndices)) {
        return $columnIndices;
    }
    $newColumnIndices = [
        $mainRecordTypeID => [],
    ];
    foreach ($columnIndices as $recordTypeID => $colIndices) {
        foreach ($colIndices as $columnIndex) {
            if (isset($columnInfo[$recordTypeID][$columnIndex]['joint_column_index'])) {
                $newColumnIndices[$mainRecordTypeID][] = $columnInfo[$recordTypeID][$columnIndex]['joint_column_index'];
            }
        }
    }
    return $newColumnIndices;
}

/**
 * Change the sorting orders to fit the joint CSV table.
 *
 * @param array $sortOrders The original sorting orders.
 * @param array $mainRecordTypeID The ID of the root record type to export.
 *
 * @return array The new sorting orders.
 */
private static function changeSortOrders($sortOrders, $mainRecordTypeID) {
    if (empty($sortOrders)) {
        return $sortOrders;
    }
    $newSortOrders = [
        $mainRecordTypeID => [],
    ];
    foreach ($sortOrders as $recordTypeID => $orders) {
        foreach ($orders as $order) {
            $newSortOrders[$mainRecordTypeID][] = $order;
        }
    }
    return $newSortOrders;
}

/**
 * Convert a value to numeric.
 *
 * @param $value
 *
 * @return int
 */
private static function valueToNumeric($value) {
    if (!is_numeric($value)) {
        $value = intval($value);
    }
    return $value;
}


} //end class
?>
