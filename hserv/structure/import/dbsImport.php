<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* dbsImport.php - import definitions from other database
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

use hserv\utilities\DbRegis;
use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/../search/dbsData.php';
require_once dirname(__FILE__).'/../edit/saveStructureLib.php';
require_once dirname(__FILE__).'/../../controller/entityScrudSrv.php';
require_once dirname(__FILE__).'/../dbsTerms.php';

define('IS_DBG', false);//debug log output

class DbsImport {

    private $system = null;

    // definition to be imported
    private $imp_recordtypes; //ids in source database
    private $rectypes_correspondence; //source->target id

    private $imp_fieldtypes;
    private $fields_correspondence;

    private $imp_terms;  //ids of source vocabularies to be imported
    private $terms_correspondence;
    private $vcg_correspondence; //vocab group src->target ids
    private $inverse_term_pairs; //map of term id to inverse term id from source database, to be translated for target

    private $source_db_reg_id = 0;

    private $source_defs = null;
    private $target_defs = null;

    private $targetTerms = null;
    private $sourceTerms = null; //ref to DbsTerms
    private $sourceIconURL = null; //url to copy rectype icons
    private $source_db_name;

    private $src_CalcFields = null;

    private $cloning_template = false;
    private $prime_defType = null; // what is primarily being imported (rectype, detailtype, term)

    //report data
    private $rectypes_upddated;
    private $rectypes_added;

    private $detailtypes_updated;
    private $detailtypes_added;

    private $terms_updated;
    private $terms_added;

    private $broken_terms;
    private $broken_terms_reason;

    private $rename_target_entities = false;
    private $conservative = false; //import new linked rectord types ONLY

    private $def_translations;
    private $translations_report;

    //  $data =
    public function __construct( $system ) {
        $this->system = $system;
    }

    /*
    // getter
    //
    public function getCorrespondences(){
        return array('rectypes'=>$this->rectypes_correspondence,
                     'fields'=>$this->fields_correspondence,
                     'terms'=>$this->terms_correspondence);
    }
    */

    //
    // getter
    //
    public function getDefinitions($type='target'){
        if($type=='target'){
            return $this->target_defs;
        }else{
            return $this->source_defs;
        }
    }

// 1. get database url
// 2. get definitions from remote database
// 3. Find what defintions will be imported
//        for rectypes perfrom deep check for field structure and compare terms

// 4. With list of all record types, build a list of all the base field types:
// 5. Perform database action - add rectypes, structure, fields and terms into our database

    /**
    * Finds all defintions to be imported
    *
    * @param mixed $data
    *
    * array(
        defType - rectype,detailtype,term
        databaseID - database where to search definition if not defined take db from conceptCode
        conceptCode - unique identifier (optional if definitionID is set)

        definitionID  - id in source database OR array of ids

      )
    * @return mixed
    *
    *
    * It fills various arrays that will be used in importing
    *
    *   $this->imp_recordtypes = array();
    *   $this->imp_fieldtypes = array();
    *   $this->imp_terms = array("enum"=>array(), "relation"=>array());
    *
    *    //source id => target id  - local ids
    *    $this->rectypes_correspondence = array();//source rectypeID => new (target) rectype ID
    *    $this->fields_correspondence = array();//import field id -> target id - IMPORTANT for proper titlemask conversion
    *    //$fields_correspondence_existed = array();
    *    $this->terms_correspondence = array();//"enum"=>array(), "relation"=>array());
    *    $this->vcg_correspondence = array();
    *    //$terms_correspondence_existed = array();
    *    $this->rectypes_upddated  = array();
    *    $this->rectypes_added  = array();
    *
    *
    */
    public function doPrepare( $data ){

        $this->rename_target_entities = (@$data['is_rename_target'] == 1);
        $this->conservative = (@$data['conservative'] == 1);

        $this->prime_defType = $data['defType'];//'rectype','detailtype','term'
        $this->cloning_template = $this->prime_defType == 'all' && @$data['definitionID'] == 'all';

        $allowed_types = array('rectype','detailtype','term','all');

        if(!in_array($this->prime_defType, $allowed_types)){
            $this->system->addError(HEURIST_INVALID_REQUEST, $this->prime_defType." is not allowed type to be import");
            return false;
        }

        $db_reg_id = 0;
        $local_ids = array();//local id of defintion to be imported
        $cCode = null;

        if(@$data['databaseID']>0){  //source database id
            $db_reg_id = $data['databaseID'];
        }
        if(@$data['definitionID']){  //id or concept code in source database
            $local_ids = $data['definitionID'];
            if(!is_array($local_ids)) {$local_ids = array($local_ids);}
        }

        if(!@$data['databaseURL']){

            if(@$data['conceptCode']){  //take db id and def id from concept code

                $cCode = $data['conceptCode'];

                if(is_array($cCode)){
                    if(count($cCode)>2){
                        $db_reg_id = $cCode[2];
                        $cCode = $cCode[0].'-'.$cCode[1];
                    }else{
                        $cCode = implode('-',$cCode);
                    }
                }
                list($db_id, $ent_id) = explode('-', $cCode);

                if(!(ctype_digit($db_id) && $db_id>0 && ctype_digit($ent_id) && $ent_id>0)){
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Concept code ($cCode) has wrong format - should be two numbers separated by dash");
                    return false;
                }
                if(!($db_reg_id>0)){
                    $db_reg_id = $db_id; //take source database from concept code
                }
            }

            if (!($db_reg_id>0)) {
                $this->system->addError(HEURIST_ERROR, "Not possible to determine an origin database id (source of import)");
                return false;
            }
            if(!(!empty($local_ids) || $cCode)){
                $this->system->addError(HEURIST_ERROR, "Neither concept code nor local id is defined");
                return false;
            }

            $this->source_db_reg_id = $db_reg_id;

            // 1. get database url by database id
            $database_url = DbRegis::registrationGet(array('dbID'=>$db_reg_id));
            if(!$database_url){
                $this->system->addErrorMsg('Can not obtain database url. ');
            }

        }else{
            $this->source_db_reg_id = $db_reg_id;
            $database_url = $data['databaseURL'];
            if(!$database_url){
                $this->system->addError(HEURIST_ERROR, 'Can not obtain database url for database # '.$this->source_db_reg_id);
            }
        }

        if(!$database_url){
            return false;
        }

        // 2. get definitions from remote database
        $this->source_defs  = $this->_getDatabaseDefinitions($database_url, $db_reg_id, ($this->prime_defType=='term'));

        if (!$this->source_defs) {
            return false; //see $system->getError
        }

        $this->source_defs['databaseURL'] = $database_url;

        /* We require at least version 1.2 for database defintion */
        if(false && @$this->source_defs['terms']){
            if(!@$this->source_defs['terms']['groups'] ||
                !(@$this->source_defs['terms']['fieldNamesToIndex']['trm_VocabularyGroupID']>0)){

                $this->system->addError(HEURIST_ERROR, 'Database registration URL '.$database_url.' returns old version '
                .'(DB version 1.2, you are using version 1.3) of database definitions. '
                .'Please ask the system administrator (<email address*>) to upgrade the system.');
                return false;
            }
        }


        //find source id by conceptcodes or verify local_ids
        $def_ids = array();
        $wrong_id = null;
        $missed_name = null;

        $this->_createTrmLinks();//create virtual trm_Links if source db is 1.2

        $this->sourceTerms = new DbsTerms(null, $this->source_defs['terms']);
        if($this->cloning_template){
            $def_ids[] = 'all';
        }elseif($this->prime_defType=='term'){

            if(!isEmptyArray($local_ids)){
                foreach($local_ids as $local_id){
                    $rt = $this->sourceTerms->getTerm($local_id);
                    if($rt!=null){
                        $def_ids[] = $local_id;
                    }else{
                        $wrong_id = 'id#'.$local_id;
                        break;
                    }
                }
            }else{
                $def_ids[] = $this->sourceTerms->findTermByConceptCode($cCode);
            }
        }else{

            if(!isEmptyArray($local_ids)){
                foreach($local_ids as $local_id){ //$local_id either id in source db or concept code

                    if($this->prime_defType=='rt' || $this->prime_defType=='rectypes' || $this->prime_defType=='rectype'){
                        $missed_name = @$data['rectypes'][$local_id]['name'];
                    }

                    $found_local_id = $this->_getLocalCode($this->prime_defType, $this->source_defs, $local_id);
                    if($found_local_id){
                        $def_ids[] = $found_local_id;
                    }else{
                        $wrong_id = 'id#'.$local_id;
                        if($missed_name){
                            $wrong_id = $wrong_id.' "'.$missed_name.'"';
                        }
                        break;
                    }
                }

            }else{
                //get local id in source db by concept code
                $def_ids[] = $this->_getLocalCode($this->prime_defType, $this->source_defs, $cCode);
            }
        }

        if (empty($def_ids) || $wrong_id>0) { //definition not found in source database
            $smsg = ($wrong_id!=null) ?$wrong_id :'concept code '.$cCode;
            $this->system->addError(HEURIST_ERROR, 'Unable to get '.$this->prime_defType. ' definition with '.$smsg.' from registered database #'.$db_reg_id);
            return false; //see $system->getError
        }

        //definitions to be imported - list of source local ids
        $this->imp_recordtypes = array();
        $this->imp_fieldtypes = array();
        $this->imp_terms = array("enum"=>array(), "relation"=>array());
        $this->inverse_term_pairs = array();

        //source id => target id  - local ids
        $this->rectypes_correspondence = array();//source rectypeID => new (target) rectype ID
        $this->fields_correspondence = array();//import field id -> target id - IMPORTANT for proper titlemask conversion
        //$fields_correspondence_existed = array();
        $this->terms_correspondence = array();//"enum"=>array(), "relation"=>array());
        $this->vcg_correspondence = array();
        //$terms_correspondence_existed = array();
        $this->rectypes_upddated  = array();
        $this->rectypes_added  = array();
        $this->detailtypes_updated = array();
        $this->detailtypes_added = array();
        $this->terms_updated = array();
        $this->terms_added = array();

        $this->def_translations = array(
            'terms' => array(),
            'detailtypes' => array(),
            'recordtypes' => array()
        );// Get translated names from source
        $this->translations_report = array(
            'terms' => array(),
            'detailtypes' => array(),
            'recordtypes' => array()
        );

        //target(local) definitions
        $this->target_defs = array();
        if($this->prime_defType!='term'){
            $this->target_defs['rectypes'] = dbs_GetRectypeStructures($this->system, null, 2);
            $this->target_defs['detailtypes'] = dbs_GetDetailTypes($this->system, null, 2);
        }

        $this->target_defs['terms'] = dbs_GetTerms($this->system);
        $this->targetTerms = new DbsTerms($this->system, $this->target_defs['terms']);

        // 3. Find what defintions will be imported
        if($this->cloning_template){ // all definitions

            // 3.1 Handle terms
            $term_ids = array_merge($this->sourceTerms->treeData('enum', 3), $this->sourceTerms->treeData('relation', 3));// merge both types of terms to be imported into one array
            $has_terms = !empty($term_ids);
            foreach($term_ids as $term_id){
                $this->_getTopMostVocabulary($term_id, 'enum');
                $this->_getTopMostVocabulary($term_id, 'relation');
            }

            if($has_terms){
                $this->_getInverseTerms();
            }

            // 3.2 Handle base fields
            $dty_ids = $this->_getLocalCode('detailtype', $this->source_defs, null, true);
            foreach ($dty_ids as $dty_id) {
                $this->_findDependentRecordTypesByFieldId($dty_id);
            }

            // 3.3 Handle record type
            $rty_ids = $this->_getLocalCode('rectype', $this->source_defs, null, true);
            foreach ($rty_ids as $rty_id) {
                $this->_findDependentRecordTypesByFieldId($rty_id);
            }

        }elseif($this->prime_defType=='term'){ // terms only

            foreach($def_ids as $def_id){
                $this->_getTopMostVocabulary($def_id, 'enum');
                $this->_getTopMostVocabulary($def_id, 'relation');
            }

            $this->_getInverseTerms();

        }else{ // record types or base fields

            if($this->prime_defType=='rectype'){
                //find record types
                foreach($def_ids as $def_id){
                    $this->_findDependentRecordTypes($def_id, 0);
                }

                if(empty($this->imp_recordtypes) && empty($this->imp_fieldtypes) && empty($this->imp_terms)){
                    $this->system->addError(HEURIST_NOT_FOUND, 'No one entity to be imported found');
                    return false;
                }


            } elseif($this->prime_defType=='detailtype'){

                foreach($def_ids as $def_id){
                    $this->_findDependentRecordTypesByFieldId($def_id);
                }

                if(empty($this->imp_fieldtypes) && empty($this->imp_terms)){
                    $this->system->addError(HEURIST_NOT_FOUND, 'No one field or vocabulary to be imported found');
                    return false;
                }

            }


            // 4. With list of all record types, build a list of all base field types to be imported
            $def_rts = $this->source_defs['rectypes']['typedefs'];
            $def_dts = $this->source_defs['detailtypes']['typedefs'];

            $idx_type = $def_dts['fieldNamesToIndex']['dty_Type'];
            $idx_ccode = $def_dts['fieldNamesToIndex']['dty_ConceptID'];
            $idx_terms = $def_dts['fieldNamesToIndex']['dty_JsonTermIDTree'];

            foreach ($this->imp_recordtypes as $rty_id){
                $fields = $this->source_defs['rectypes']['typedefs'][$rty_id]['dtFields'];
                foreach ($fields as $ftId => $field){

                    if(!(@$this->fields_correspondence[$ftId] || in_array($ftId, $this->imp_fieldtypes) )){

                        $ccode = $def_dts[$ftId]['commonFields'][$idx_ccode];
                        $local_ftId = 0;//DbsImport::_getLocalIfUnregistered($ccode);
                        if($local_ftId===0){ //registered concept code
                            $local_ftId = $this->_getLocalCode('detailtype', $this->target_defs, $ccode);
                        }else{
                            $local_ftId = 0;
                        }

                        //Get vocabulary for all terms used
                        $dt_Type = $def_dts[$ftId]['commonFields'][$idx_type];
                        if($dt_Type == "enum" || $dt_Type == "relmarker"){
                            //get topmost vocabulary
                            $this->_getTopMostVocabulary($def_dts[$ftId]['commonFields'][$idx_terms], $dt_Type);
                        }

                        $this->_getInverseTerms();

                        if($local_ftId>0){ //already exists in target
                            $this->fields_correspondence[$ftId] = $local_ftId;
                            continue; //field with the same concept code is already in database
                        }

                        //there is no such field in target - it must be imported
                        if(!in_array($ftId, $this->imp_fieldtypes)){
                            array_push($this->imp_fieldtypes, $ftId);
                        }
                    }
                }
            }//for

            //5. some field types may not belong to recordtypes, they should be imported too
            // {id: '1', name: 'Titre', code: '2-1'}
            $all_fieldtypes = @$data['fieldtypes'];//[$local_id]['name']
            if(!isEmptyArray($all_fieldtypes)){
                foreach ($all_fieldtypes as $ftId => $field){
                    if(!(@$this->fields_correspondence[$ftId] || in_array($ftId, $this->imp_fieldtypes) )){

                            $ccode = $def_dts[$ftId]['commonFields'][$idx_ccode];

                            $local_ftId = 0; //DbsImport::_getLocalIfUnregistered($ccode);
                            if($local_ftId===0){ //registered concept code
                                $local_ftId = $this->_getLocalCode('detailtype', $this->target_defs, $ccode);
                            }else{
                                $local_ftId = 0;
                            }

                            if($local_ftId>0){ //already exists in target
                                $this->fields_correspondence[$ftId] = $local_ftId;
                                continue; //field with the same concept code is already in database
                            }
                            //there is no such field in target - it must be imported
                            if(!in_array($ftId, $this->imp_fieldtypes)){
                                array_push($this->imp_fieldtypes, $ftId);
                            }
                    }
                }
            }

        }

        return true;
    }

    //
    //
    //
    private function error_exit2($msg){

        $this->system->addError(HEURIST_ERROR, $msg);

        $mysqli = $this->system->get_mysqli();
        $mysqli->rollback();
        $mysqli->close();

    }

    //--------------------------------------------------------------------------
    //
    // Perform database action - add rectypes, structure, fields and terms into our database
    //
    public function doImport( ){

        global $mysqli;
        $mysqli = $this->system->get_mysqli();
        $mysqli->autocommit(false);

        $this->broken_terms = array();
        $this->broken_terms_reason = array();

// I. Add Terms (whole vocabulary)
        $stub = array();//stub for $all_terms_in_vocab
        if(! ($this->_importVocabulary(null, "enum", $stub) &&
              $this->_importVocabulary(null, "relation", $stub)) ){

            $mysqli->rollback();
            $mysqli->close();
            return false;
        }elseif(count($this->def_translations['terms']) > 0){
            $this->_importTranslations('terms');
        }

        // Correct inverse term values
        if(!empty($this->inverse_term_pairs) && !$this->_correctInverseIds()){
            $this->system->addError(HEURIST_ERROR, 'Can not import/set inverse terms');
            $mysqli->rollback();
            $mysqli->close();
            return false;
        }

        if(empty($this->imp_recordtypes) && empty($this->imp_fieldtypes)){
            //only terms we can exit
            $mysqli->commit();
            $this->system->cleanDefCache();
            return true;
        }

        $group_ft_ids = array();
        $group_rt_ids = array();
        $def_rts = $this->source_defs['rectypes']['typedefs'];
        $def_dts = $this->source_defs['detailtypes']['typedefs'];
        $def_calcfields = $this->source_defs['rectypes']['calcfields'];

        $trg_rectypes = $this->target_defs['rectypes'];
        $trg_detailtypes = $this->target_defs['detailtypes'];

// II. Add new record type groups --------------------------------------------------------------------------------------

$columnNames = array("rtg_Name","rtg_Order","rtg_Description");
$idx_rt_grp = $def_rts['commonNamesToIndex']['rty_RecTypeGroupID'];

foreach ($this->imp_recordtypes as $recId){

    $rt_name = @$this->source_defs['rectypes']['names'][$recId];//get rectype in source
    if(!@$def_rts[$recId]){
        if(!$rt_name){
            $this->error_exit2("Can't find record type #'$recId' in source database");
        }else{
            $this->error_exit2("Can't find definitions for record type #'$recId'. \"$rt_name\" in source database");
        }
        return false;
    }

    $grp_id = @$def_rts[$recId]['commonFields'][$idx_rt_grp];
    $grp_name = null;

    if(!$grp_id){
        $this->error_exit2("Group ID is not defined for record type #'$recId'. \"$rt_name\" in source database");
        return false;
    }

    $src_group=null;

    if(@$group_rt_ids[$grp_id]){ //already found
        continue;
    }

    //find group in source by ID
    foreach ($this->source_defs['rectypes']['groups'] as $idx=>$group){
        if(is_numeric($idx) && $group['id']==$grp_id){
            $src_group = $group;
            $grp_name = @$src_group['name'];
            break;
        }
    }

    if($src_group==null){
        $this->error_exit2("Can't find group #$grp_id for record type #'$recId'. \"$rt_name\" in source database");
        return false;
    }
    if(!$grp_name){
        $this->error_exit2("Name of group is empty. Can't add group #$grp_id for record type #'"
                                    .$recId."'. \"$rt_name\" in source database");
        return false;
    }

    //get name and try to find in target
    $isNotFound = true;
    foreach ($trg_rectypes['groups'] as $idx=>$group){
        if(is_numeric($idx) && mb_strtolower(trim($group['name']))== mb_strtolower(trim($grp_name))){
            $group_rt_ids[$grp_id] = $group['id'];
            $isNotFound = false;
            break;
        }
    }

    if($isNotFound){

        $res = createRectypeGroups($columnNames,
            array(array("values" =>
                array($grp_name, $src_group['order'], $src_group['description']))) );

        $new_grp_id = @$res['result'];

        if( is_numeric($new_grp_id) ){
            $group_rt_ids[$grp_id] = $new_grp_id;
            $trg_rectypes['groups'][$new_grp_id] = array('name'=>$grp_name);
        }else{
            $this->error_exit2("Can't add record type group '".$grp_name."'. ".@$res['error']);
            return false;
        }
    }
}

// ------------------------------------------------------------------------------------------------

// III. Add record types

$columnNames = $def_rts['commonFieldNames'];
$dtFieldNames = $def_rts['dtFieldNames'];

$idx_name        = $def_rts['commonNamesToIndex']['rty_Name'];
$idx_origin_dbid = $def_rts['commonNamesToIndex']['rty_OriginatingDBID'];
$idx_origin_id   = $def_rts['commonNamesToIndex']['rty_IDInOriginatingDB'];
$idx_origin_name  = $def_rts['commonNamesToIndex']['rty_NameInOriginatingDB'];
$idx_ccode       = $def_rts['commonNamesToIndex']['rty_ConceptID'];
$idx_titlemask   = $def_rts['commonNamesToIndex']['rty_TitleMask'];
$idx_titlemask_canonical = $def_rts['commonNamesToIndex']['rty_CanonicalTitleMask']; //not used

$cfn_tobeimported = array();

foreach ($this->imp_recordtypes as $rtyID){

    $def_rectype = $def_rts[$rtyID]['commonFields'];
    $new_rtyID = @$this->rectypes_correspondence[$rtyID];

    if($new_rtyID>0){
        //already  exists - update fields only - add missed fields
        // and overwrite name - if "rename" option is ON
        $this->rectypes_upddated[] = $new_rtyID;

        if($this->rename_target_entities){
            //$alt_name = $this->doDisambiguate($def_rectype[$idx_name], $trg_rectypes['names']);
            renameRectype($new_rtyID, $def_rectype, $def_rts['commonNamesToIndex']);
        }

    }else{
        //new record type - there is no such record type in target database

        //replace group id with local one
        $grp_id = $def_rectype[$idx_rt_grp];
        $def_rectype[$idx_rt_grp] = $group_rt_ids[$grp_id];


        //disambiguate rectype name
        $def_rectype[$idx_name] = $this->doDisambiguate($def_rectype[$idx_name], $trg_rectypes['names']);

        //assign title mask (in DB we store only rty_TitleMask)
        $def_rectype[$idx_titlemask] = $def_rectype[$idx_titlemask_canonical];

        //converts 0000 origin db to 9999            TT1
        $def_rectype[$idx_ccode] = DbsImport::convertUnregisteredCode($def_rectype[$idx_ccode],$rtyID);

        // Fill in missing original values
        DbsImport::_setConceptValues($def_rectype, [$this->source_db_reg_id, $rtyID, $def_rectype[$idx_name]], [$idx_ccode, $idx_origin_dbid, $idx_origin_id, $idx_origin_name]);

        //from saveStructureLib.php
        $res = createRectypes($columnNames, array("0"=>array("common"=>$def_rectype)), false, false, null);

        if(is_numeric($res)){

            $new_rtyID  = abs($res);
            $this->rectypes_correspondence[$rtyID] = $new_rtyID;
            $trg_rectypes['names'][$new_rtyID] = $def_rectype[$idx_name];//replace with new name
            $this->copyRectypeIcon($rtyID, $new_rtyID);

            $this->rectypes_added[] = $new_rtyID;

        }else{

            $this->error_exit2("Cannot add record type for id#".$recId.". ".$res);
            return false;

        }

    }

    if($new_rtyID > 0 && !array_key_exists($new_rtyID, $this->def_translations['recordtypes'])){
        $this->def_translations['recordtypes'][$new_rtyID] = array('trn_Source' => 'rty_', 'trn_Code' => $rtyID);
    }

}//for


// ------------------------------------------------------------------------------------------------

// IV. Add new field type groups

$columnNames = array("dtg_Name","dtg_Order","dtg_Description");
$idx_dt_grp = $def_dts['fieldNamesToIndex']['dty_DetailTypeGroupID'];

foreach ($this->imp_fieldtypes as $ftId){
    $src_group = [];
    $grp_id = $def_dts[$ftId]['commonFields'][$idx_dt_grp];
    $grp_name = "";
    if(@$group_ft_ids[$grp_id]){ //already found
        continue;
    }

    foreach ($this->source_defs['detailtypes']['groups'] as $idx=>$group){
        if(is_numeric($idx) && $group['id']==$grp_id){
            $src_group = $group;
            $grp_name = $src_group['name'];
            break;
        }
    }

    //get name and try to find in target
    $isNotFound = true;
    foreach ($trg_detailtypes['groups'] as $idx=>$group){
        if(is_numeric($idx) && mb_strtolower(trim($group['name']))== mb_strtolower(trim($grp_name))){
            $group_ft_ids[$grp_id] = $group['id'];
            $isNotFound = false;
            break;
        }
    }

    if($isNotFound){

        $res = createDettypeGroups($columnNames,
            array(array("values" =>
                array($grp_name, $src_group['order'], $src_group['description']))) );

        $new_grp_id = @$res['result'];

        if(is_numeric($new_grp_id)){
            $group_ft_ids[$grp_id] = $new_grp_id;
            $trg_detailtypes['groups'][$new_grp_id] = array('name'=>$grp_name);

        }else{

            $this->error_exit2("Can't add field type group for '".$grp_name."'. ".$res['error']);
            return false;

        }
    }
}

// ------------------------------------------------------------------------------------------------

// V. Add field types

$columnNames = $def_dts['commonFieldNames'];
array_shift($columnNames);//remove dty_ID

$idx_type           = $def_dts['fieldNamesToIndex']['dty_Type'];
$idx_name           = $def_dts['fieldNamesToIndex']['dty_Name'];
$idx_terms_tree     = $def_dts['fieldNamesToIndex']['dty_JsonTermIDTree'];
$idx_terms_disabled = $def_dts['fieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];
$idx_constraints    = $def_dts['fieldNamesToIndex']['dty_PtrTargetRectypeIDs'];
$idx_origin_dbid = $def_dts['fieldNamesToIndex']['dty_OriginatingDBID'];
$idx_origin_id   = $def_dts['fieldNamesToIndex']['dty_IDInOriginatingDB'];
$idx_origin_name   = $def_dts['fieldNamesToIndex']['dty_NameInOriginatingDB'];
$idx_ccode       = $def_dts['fieldNamesToIndex']['dty_ConceptID'];

if($this->rename_target_entities){
    //rename fields that are already in target database
    foreach($this->fields_correspondence as $src_ID=>$trg_ID){
        if(@$def_dts[$src_ID]['commonFields']){
            renameDetailtype($trg_ID, $def_dts[$src_ID]['commonFields'], $def_dts['fieldNamesToIndex']);

            if(!in_array($trg_ID, $this->detailtypes_updated)) {$this->detailtypes_updated[] = $trg_ID;}
        }

        if(!array_key_exists($trg_ID, $this->def_translations['detailtypes'])){
            $this->def_translations['detailtypes'][$trg_ID] = array('trn_Source' => 'dty_', 'trn_Code' => $src_ID);
        }
    }
}


foreach ($this->imp_fieldtypes as $ftId){

    $def_field = $def_dts[$ftId]['commonFields'];

    //replace group id
    $grp_id = $def_field[$idx_dt_grp];
    $def_field[$idx_dt_grp] = $group_ft_ids[$grp_id];

    //disambiguate field name
    $def_field[$idx_name] = $this->doDisambiguate($def_field[$idx_name], $trg_detailtypes['names']);

    if($def_field[$idx_type] == "enum" || $def_field[$idx_type] == "relationtype" || $def_field[$idx_type] == "relmarker"){
        //change terms ids for enum and reltypes
        $def_field[$idx_terms_tree] = $this->replaceTermIds(@$def_field[$idx_terms_tree], $def_field[$idx_type] );
        $def_field[$idx_terms_disabled] = $this->replaceTermIds(@$def_field[$idx_terms_disabled], $def_field[$idx_type]);
    }
    if($def_field[$idx_type] == "resource" || $def_field[$idx_type] == "relmarker"){
        //change record ids for pointers
        $def_field[$idx_constraints] = $this->replaceRectypeIds(@$def_field[$idx_constraints]);
    }

    $def_field[$idx_ccode] = DbsImport::convertUnregisteredCode($def_field[$idx_ccode],$ftId);

    // Fill in missing original values
    DbsImport::_setConceptValues($def_field, [$this->source_db_reg_id, $ftId, $def_field[$idx_name]], [$idx_ccode, $idx_origin_dbid, $idx_origin_id, $idx_origin_name]);

    array_shift($def_field);//remove dty_ID
    $res = createDetailTypes($columnNames, array("common"=>$def_field));

    if(is_numeric($res)){
        $new_dtyID = abs($res);

        $this->fields_correspondence[$ftId] = $new_dtyID;
        $trg_detailtypes['names'][$new_dtyID] = $def_field[$idx_name-1];//new name

        $this->detailtypes_added[] = $new_dtyID;

        if(!array_key_exists($new_dtyID, $this->def_translations['detailtypes'])){
            $this->def_translations['detailtypes'][$new_dtyID] = array('trn_Source' => 'dty_', 'trn_Code' => $ftId);
        }

    }else{
        //$ftId
        $this->error_exit2('Can\'t add base field "'.$def_field[$idx_name-1]
            .'" (global code '.$def_field[$idx_ccode-1]
            .') defined in  template. This may be due to a name conflict caused by unrecognised accents on similar names. <br><br>'
            .CONTACT_HEURIST_TEAM_PLEASE.' for advice'
            .'<br><br>MySQL message:'.$res);
        return false;
    }
}//for addition base/detail fields

// ------------------------------------------------------------------------------------------------

// VI. Add/update record structures
$trg_def_rts = $this->target_defs['rectypes']['typedefs'];

$def_rts2 = $def_rts['dtFieldNamesToIndex'];
$idx_type           = $def_rts2['dty_Type'];
$idx_terms_tree     = $def_rts2['rst_FilteredJsonTermIDTree'];//value is the same as
$idx_terms_disabled = $def_rts2['dty_TermIDTreeNonSelectableIDs'];
$idx_constraints    = $def_rts2['rst_PtrFilteredIDs'];
$idx_defaultvalue   = $def_rts2['rst_DefaultValue'];

$idx_name = $def_rts2['rst_DisplayName'];
$idx_desc = $def_rts2['rst_DisplayHelpText'];
$idx_desc2 = $def_rts2['rst_DisplayExtendedDescription'];
$idx_ref = $def_rts2['rst_SemanticReferenceURL'];
$idx_calcfield  = $def_rts2['rst_CalcFunctionID'];


$dtFieldNames = $def_rts['dtFieldNames'];

foreach ($this->imp_recordtypes as $rtyID){

    $target_RtyID = @$this->rectypes_correspondence[$rtyID];

    if($target_RtyID>0){

        if(@$trg_def_rts[$target_RtyID]['dtFields']){//this record type is already in destination need to sync structure
            $fields = $trg_def_rts[$target_RtyID]['dtFields'];
        }else{
            $fields = array();
        }

        //if field does not exists, assign values from source
        foreach ($def_rts[$rtyID]['dtFields'] as $ftId => $def_field){ //loop by source
            $trg_dty_id = $this->fields_correspondence[$ftId];
            if(!@$fields[ $trg_dty_id ]){
                //add
                $fields[ $trg_dty_id ] = $def_field;

            }elseif($this->rename_target_entities){

                $fields[$trg_dty_id][$idx_name] = $def_field[$idx_name];
                $fields[$trg_dty_id][$idx_desc] = $def_field[$idx_desc];
                $fields[$trg_dty_id][$idx_desc2] = $def_field[$idx_desc2];
                $fields[$trg_dty_id][$idx_ref] = $def_field[$idx_ref]==null?'':$def_field[$idx_ref];
            }

            $cfn_ID = $def_field[$idx_calcfield];
            $fields[$trg_dty_id][$idx_calcfield] = 0; //reset

            if($cfn_ID>0 && @$def_calcfields[$cfn_ID]){
                if(@$cfn_tobeimported[$cfn_ID]){
                    array_push($cfn_tobeimported[$cfn_ID], array($target_RtyID=>$trg_dty_id));
                }else{
                    $cfn_tobeimported[$cfn_ID] = array(array($target_RtyID=>$trg_dty_id));
                }
            }
        }
        //clear term trees and resource constraints; assign default value for terms
        foreach ($fields as $ftId => $def_field){

            if($def_field[$idx_type] == "enum"
                || $def_field[$idx_type] == "relationtype"
                || $def_field[$idx_type] == "relmarker")
            {
                //change terms ids for enum and reltypes
                $def_field[$idx_defaultvalue] = $this->replaceTermIds(@$def_field[$idx_defaultvalue], $def_field[$idx_type]);
            }

            $def_field[$idx_terms_tree] = '';
            $def_field[$idx_terms_disabled] = '';
            $def_field[$idx_constraints] = '';
            $fields[$ftId] = $def_field;

        }


        $ret = updateRecStructure( $dtFieldNames , $target_RtyID, array("dtFields"=>$fields));
        if(is_array($ret)){
            foreach($ret as $id=>$res2){
                foreach($res2 as $dtid=>$res){
                    if(!is_numeric($res)){
                        $this->error_exit2("Can't update record type structure rectype#".$id.". ".$res);
                        return false;
                    }
                }
            }

        }else{

            $error = $this->system->getError();
            if(@$error['message']){
                $ret = substr($error['message'],0,100);
            }

            $this->error_exit2("Can't update record type structure rectype#".$this->rectypes_correspondence[$rtyID].". ".$ret);
            return false;

        }
    }
}//foreach

// ------------------------------------------------------------------------------------------------

// VII. Update titlemasks with new ids - ONLY FOR NEW RECORD TYPES
//          title mask for existing record types remains unchanged

//$mysqli->commit();

TitleMask::set_fields_correspondence($this->fields_correspondence);

foreach ($this->imp_recordtypes as $rtyID){
    $target_ID = @$this->rectypes_correspondence[$rtyID];
    if($target_ID==null || !in_array($target_ID, $this->rectypes_added) ){
        continue;
    }

    $mask = $def_rts[$rtyID]['commonFields'][$idx_titlemask_canonical];
//echo $mask;
    // note we use special global array $this->fields_correspondence - for proper conversion of remote id to concept code
    $res = updateTitleMask( $target_ID, $mask);//see saveStructureLib
    if(!is_numeric($res)){
        //$this->error_exit2($res);
        $mysqli = $this->system->get_mysqli();
        $mysqli->rollback();
        $mysqli->close();
        return true;
    }

}

TitleMask::set_fields_correspondence(null);

// ------------------------------------------------------------------------------------------------

// VII. Import calculated fields
if(!isEmptyArray($cfn_tobeimported)){

$cfn_entity = new DbDefCalcFunctions($this->system);

$repAction = new ReportActions($this->system, null);

$cfn_names = mysql__select_list2($mysqli, 'SELECT cfn_Name FROM defCalcFunctions');

foreach($cfn_tobeimported as $cfn_ID => $rty_IDs){ //$rty_IDs $rty_ID=>$dty_ID

        $cfn_code = @$def_calcfields[$cfn_ID]['cfn_FunctionSpecification'];

        //1. convert smarty report from concept to local codes
        $cfn_code = $repAction->convertTemplate($cfn_code, 1);
        if(@$cfn_code['template']) {$cfn_code = $cfn_code['template'];}

//           $cfn_values[$idx_cfn_code] = convertCalcField( $cfn_values[$idx_cfn_code] );

        //2. check if the same code already exists
        $id = mysql__select_value($mysqli,
            'SELECT cfn_ID FROM defCalcFunctions WHERE cfn_FunctionSpecification="'
            .$mysqli->real_escape_string($cfn_code).'"');

        if($id>0){ //code is the same

            $new_cfn_ID = $id;

        }else{

            //3. convert record types
            $cfn_rectypes_new = array();
            $cfn_rectypes = @$def_calcfields[$cfn_ID]['cfn_RecTypeIDs'];
            $idx_ccode   = $def_rts['commonNamesToIndex']['rty_ConceptID'];

            if($cfn_rectypes){
                $cfn_rectypes = explode(',', $cfn_rectypes);
                foreach ($cfn_rectypes as $rtyID){
                    $trg_ID = @$this->rectypes_correspondence[$rtyID];
                    if(!($trg_ID>0)){
                        $ccode = @$def_rts[$rtyID]['commonFields'][$idx_ccode];
                        if($ccode){
                            $trg_ID = ConceptCode::getRecTypeLocalID($ccode);
                        }
                    }
                    if($trg_ID>0){
                        $cfn_rectypes_new[] = $trg_ID;
                    }
                }
            }
            $cfn_rectypes_new = implode(',', $cfn_rectypes_new);


            //4. save calc field in database
            //fields:[fldname:value,fieldname2:values,.....]
            $cfn_values = $def_calcfields[$cfn_ID];
            $cfn_values['cfn_ID'] = -1;
            $cfn_values['cfn_Name'] = $this->doDisambiguate($cfn_values['cfn_Name'], $cfn_names);
            $cfn_names[] = $cfn_values['cfn_Name'];
            $cfn_values['cfn_FunctionSpecification'] = $cfn_code;
            $cfn_values['cfn_RecTypeIDs'] = $cfn_rectypes_new;
            $cfn_values = array('entity'=>'defCalcFunctions', 'fields'=>$cfn_values);

            $cfn_entity->setData($cfn_values);
            //$cfn_entity->setRecords(null);//reset
            $new_cfn_ID = $cfn_entity->save();//register remote url - it returns ulf_ID
            if(is_array($new_cfn_ID)) {$new_cfn_ID = $new_cfn_ID[0];}

        }

        if($new_cfn_ID===false){
            $error = $this->system->getError();
            $ret = '';
            if(@$error['message']){
                $ret = substr($error['message'],0,100);
            }
            $this->error_exit2("Can't update calculation field# ".$cfn_ID.". ".$ret);
            return false;
        }

        //5. Update record type
        foreach($rty_IDs as $idx=>$codes){
            $rty_ID = array_keys($codes)[0];
            $dty_ID = $codes[$rty_ID];
            $query = 'UPDATE defRecStructure SET rst_CalcFunctionID='
                .intval($new_cfn_ID).' WHERE rst_RecTypeID='.intval($rty_ID).' AND rst_DetailTypeID='.intval($dty_ID);
            $mysqli->query( $query );
        }

}//foreach calc fields
}

// VIII. Import translations (term translations are handled earlier)
if(count($this->def_translations['detailtypes']) > 0 || count($this->def_translations['recordtypes']) > 0){

    $this->_importTranslations('detailtypes');
    $this->_importTranslations('recordtypes');
}

$mysqli->commit();

            $this->system->cleanDefCache();

            return true;
    }//doImport


    //
    //  fill "fake" source_defs for record types on base of mappping
    //  it is required for $this->getTargetIdBySourceId
    //
    public function doMapping($mapping){

        $defType = 'rectypes';
        $this->source_defs['rectypes'] = array();
        foreach($mapping as $sourceID=>$fields){
            if(is_int($sourceID) && $sourceID>0){
                $this->source_defs['rectypes'][$sourceID] = $fields['rty_ID'];

            }
        }
        //$defType = 'detailtypes';

    }

    //
    // returns source database definitions by database url
    //
    private function _getDatabaseDefinitions($database_url, $database_id, $only_terms){


        $reg_url = explode("?",$database_url);
        $remote_url = @$reg_url[0];

        preg_match("/db=([^&]*).*$/", $database_url, $match);
        $remote_dbname = $match[1];

        if(!$remote_dbname || !$remote_url){
            $this->system->addError(HEURIST_ERROR,
                "Heurist Reference Index returns incorrect data for registered database # $database_id<br>"
                ."The page may contain an invalid database reference<br>"
                ."URL requested: $database_url<br><br>");
            return false;
        }

        $message = '<p>Unable to contact the selected source database (ID # '.$database_id
            .'). This might indicate one of the following:</p>'
            .'<ol><li>the database is no longer online;</li>'
            .'<li>the registration in the Heurist reference index is missing or points to the wrong URL '
            .$remote_url
            .'<br>(check registration in Heurist Reference Index);</li>'
            .((strpos($remote_url, HEURIST_SERVER_URL)===0)
                ?'<li>a sql server error in contacting the database;</li>'
                :'<li>a proxy error in contacting the database;</li>')
            .'<li>network timeout.</li></ol> '
            .'<p>If you cannot determine the problem, please '.CONTACT_HEURIST_TEAM.' and include the URL below<br>'
            .'URL requested: ' . $database_url . '</p>';

        if(strpos($remote_url, HEURIST_SERVER_URL)===0){ //same server

          $defs = array();

          $system2 = new hserv\System();
          if(!$system2->init($remote_dbname, true, false)){ //init without paths and consts
            $this->system->addError(HEURIST_ERROR, $message);
            return false;
          }

          if(!$only_terms){
            $defs['rectypes'] = dbs_GetRectypeStructures($system2, null, 2 );
            $defs['detailtypes'] = dbs_GetDetailTypes($system2, null, 2 );
          }
          $defs["terms"] = dbs_GetTerms($system2);
        }else{
        //2b. if remote server - call sys_strcture.php with loadRemoteURLContentWithRange
            //change hsapi to hserv when master index will be v6.5
            $remoteURL = $remote_url
                            .'hserv/controller/sys_structure.php?mode=2&terms=all&db='.$remote_dbname;
            if(!$only_terms){
                $remoteURL = $remoteURL.'&rectypes=all&detailtypes=all';
            }

            $defs = loadRemoteURLContent($remoteURL);
            if(!$defs){ // unable to connect to Heurist Reference Index
                global $glb_curl_error;
                $error_code = (!empty($glb_curl_error)) ? $glb_curl_error : 'Error code: 500 Heurist Error';

                $this->system->addError(HEURIST_ERROR, "Unable to connect Heurist Reference Index, possibly due to timeout or proxy setting<br>"
                    . $error_code . "<br>"
                    ."a) URL requested: $remoteURL<br><br>");//$database_url
            }

            $defs = json_decode(gzdecode($defs), true);
            if(!$defs || @$defs['status']!=HEURIST_OK){
                $this->system->addError(HEURIST_ERROR, $message);
                return false;
            }
            $defs = $defs['data'];
        }
        if (!($defs['terms']  &&  ($only_terms || ($defs['rectypes'] && $defs['detailtypes'])))) {
            $this->system->addError(HEURIST_ERROR, "Structure definitions read from source database # $database_id are invalid."
                .CONTACT_HEURIST_TEAM_PLEASE);
            return false;
        }

        //change hsapi to hserv when master index will be v6.5
        $this->sourceIconURL = $remote_url
                            .'hserv/controller/fileGet.php?db='.$remote_dbname.'&id=';
        $this->source_db_name = $remote_dbname;

        return $defs;
    }

    //
    // verifies that definition with given ID exists
    //
    private function _checkLocalCode($defType, $database_defs, $localID){

        $defs = null;
        if($defType=='rectype' || $defType=='rt' || $defType == 'rectypes'){
            $defs = @$database_defs['rectypes']['typedefs'][$localID];
        }elseif($defType=='detailtype' || $defType=='dt' || $defType == 'detailtypes'){
            $defs = @$database_defs['detailtypes']['typedefs'][$localID];
        }

        $ret = ($defs!=null)?$localID:false;
        return $ret;
    }

    //
    // finds target id by source id via concept code
    // get concept code in source - find local in target
    //
    public function getTargetIdBySourceId($defType, $source_id){

        $iscc = (strpos($source_id,'-')!==false);

        //get concept code in source
        if($defType=='rectype' || $defType=='rt' || $defType == 'rectypes'){

            $defType = 'rectypes';

            if(@$this->source_defs[$defType]['typedefs']){
                $defs = $this->source_defs[$defType]['typedefs'];
                //get concept code
                $fieldName = 'rty_ConceptID';
                $idx_ccode = intval($defs['commonNamesToIndex'][$fieldName]);
                $conceptCode = $iscc ?$source_id :@$defs[$source_id]['commonFields'][$idx_ccode];
            }else{
                return $defs = $this->source_defs[$defType][$source_id];//via mapping
            }

        }elseif($defType=='detailtype' || $defType=='dt' || $defType == 'detailtypes'){

            $defType = 'detailtypes';

            if(@$this->source_defs[$defType]['typedefs']){
                $defs = $this->source_defs[$defType]['typedefs'];
                $fieldName = 'dty_ConceptID';
                $idx_ccode = intval($defs['fieldNamesToIndex'][$fieldName]);
                $conceptCode = $iscc ?$source_id :@$defs[$source_id]['commonFields'][$idx_ccode];

            }else{
                return $defs = $this->source_defs[$defType][$source_id];//via mapping
            }


        }elseif($defType=='enum' || $defType=='relationtype'){

            //$defs = $this->source_defs['terms']['termsByDomainLookup'][($defType=='enum'?'enum':'relation')];

            if($iscc){
                 $conceptCode = $source_id;
            }else{
                 $fieldName = 'trm_ConceptID';
                 $idx_ccode = intval($this->source_defs['terms']['fieldNamesToIndex'][$fieldName]);
                 $term = $this->sourceTerms->getTerm($source_id, $defType);

                 $conceptCode = @$term[$idx_ccode];
            }
            //$conceptCode = $iscc ?$source_id :@$defs[$source_id][$idx_ccode];

        }

        //TT1
        $conceptCode = DbsImport::convertUnregisteredCode($conceptCode, $source_id);

        if($conceptCode){
            return DbsImport::getLocalCode($defType, $this->target_defs, $conceptCode, false);
        }else{
            return 0;
        }
    }
    //
    // get local code by concept code
    //
    private function _getLocalCode($defType, $database_defs, $conceptCode, $sall=false){
        return DbsImport::getLocalCode($defType, $database_defs, $conceptCode, $sall);
    }

    //
    // get local code by concept code or verifies that element with given code/localid exists
    //
    public static function getLocalCode($defType, $database_defs, $conceptCode, $sall=false){
        $res = array();
        $defs2 = null;

        if($defType=='rectype' || $defType=='rt' || $defType == 'rectypes'){
            $defType = 'rectypes';
            $defs = $database_defs[$defType]['typedefs'];

            $fieldName = 'rty_ConceptID';
            $idx_ccode = intval($defs['commonNamesToIndex'][$fieldName]);

        }elseif($defType=='detailtype' || $defType=='dt' || $defType == 'detailtypes'){
            $defType = 'detailtypes';
            $defs = $database_defs[$defType]['typedefs'];

            $fieldName = 'dty_ConceptID';
            $idx_ccode = intval($defs['fieldNamesToIndex'][$fieldName]);

        }elseif($defType=='enum' || $defType=='relationtype'){

//            $defs = $database_defs['terms']['termsByDomainLookup'][($defType=='enum'?'enum':'relation')];

            $fieldName = 'trm_ConceptID';
            $idx_ccode = intval($database_defs['terms']['fieldNamesToIndex'][$fieldName]);

            $defs = $database_defs['terms']['termsByDomainLookup']['enum'];
            $defs2 = $database_defs['terms']['termsByDomainLookup']['relation'];

        }

        $local_id = 0;

        if(strpos($conceptCode,'-')!==false){

            list($db, $id) = explode('-', $conceptCode);
            if(ctype_digit($db) && $db==0 && ctype_digit($id) && $id>0){
                 $local_id = $id;
            }

            //$local_id = DbsImport::_getLocalIfUnregistered($conceptCode);
        }else{
            $local_id = $conceptCode;
        }

        if(is_numeric($local_id) && $local_id>0){ //this is local already

            if(@$defs[$local_id]){
                return $local_id;
            }else{
                return null; //not found
            }

        }else{
            //find by concept code
            foreach ($defs as $id => $def) {
                if(is_numeric($id)){

                    if($defType=='enum' || $defType=='relationtype'){
                        $is_equal = $def[$idx_ccode]==$conceptCode;
                    }else{
                        $is_equal = $def['commonFields'][$idx_ccode]==$conceptCode;
                    }

                    if($is_equal){
                        if($sall){
                            array_push($res, $id);
                        }else{
                            return $id;
                        }
                    }

                }
            }
            if($defs2){
                foreach ($defs2 as $id => $def) {
                    if(is_numeric($id) && $def[$idx_ccode]==$conceptCode){
                        return $id;
                    }
                }
            }

        }

        $ret = ($sall)?$res:null;
        return $ret;

    }

    //
    // If concept code is from unregistered database (0000-xxx), it converts it to 9999-xxx
    //
    public static function convertUnregisteredCode($conceptCode, $defID=null){

        if(!$conceptCode && $defID>0){
            return '9999-'.$defID;
        }

        list($db, $id) = explode('-', $conceptCode);
        if(ctype_digit($db) && $db==0 && ctype_digit($id) && $id>0){
             $conceptCode = '9999-'.$id;
        }
        return $conceptCode;
    }

    //
    // Returns second part of concept code if database part is 0
    //
    private static function _getLocalIfUnregistered($conceptCode){

        $local_id = 0;

        list($db, $id) = explode('-', $conceptCode);
        if(ctype_digit($db) && $db==0 && ctype_digit($id) && $id>0){
             $local_id = $id;
        }

        return $local_id;
    }

    //
    // Find vocabularies to be imported
    // It fills global $this->imp_terms with the top most vocabulary
    //
    // $terms_ids - list of terms (json or csv) from field definition
    // $this->imp_terms - list of source vocabulary ids to be imported
    //
    private function _getTopMostVocabulary($terms_ids, $domain){

        if($domain=='relationtype' || $domain=='relmarker'){
            $domain = 'relation';
        }

        //array of valid ids
        $terms_ids =  $this->sourceTerms->getTermsFromFormat($terms_ids, $domain);
        if(is_array($terms_ids)){
            foreach ($terms_ids as $term_id){
                $topmost = $this->sourceTerms->getTopMostTermParent($term_id, $domain);

                if($topmost && !in_array($topmost, $this->imp_terms[$domain])){

                    //because of HEURIST_UNITED_TERMS=true  enum domain can have relation vocabularies
                    //ignore them for enum to avoid import duplications
                    if($domain=='enum' && $this->sourceTerms->getTermField($topmost,'trm_Domain')=='relation'){
                        continue;
                    }

                    array_push($this->imp_terms[$domain], $topmost);
                }
            }
        }
    }

    //
    // fills  $this->inverse_term_pairs
    //
    private function _getInverseTerms($vocab_ids = null){

        $domain = 'relation';

        $inverse_idx = $this->source_defs['terms']['fieldNamesToIndex']["trm_InverseTermID"];

        if(empty($vocab_ids)){
            $vocab_ids = $this->imp_terms[$domain];// retrieve existing vocabs
        }

        //array of valid ids
        if(isEmptyArray($vocab_ids)){
            return;
        }

        $added_vcb_ids = array();

        foreach ($vocab_ids as $vocab_id){

            $child_terms = $this->sourceTerms->treeData($vocab_id, 3);
            if(isEmptyArray($child_terms)){
                continue;
            }

            foreach($child_terms as $term_id){

                $term = $this->sourceTerms->getTerm($term_id, $domain);
                $inverse_id = $term[$inverse_idx];
                $inverse_parent = $this->sourceTerms->getTopMostTermParent($inverse_id, $domain);

                if( isPositiveInt($inverse_id)
                    && !in_array($inverse_id, $this->imp_terms[$domain])
                    && !in_array($inverse_parent, $this->imp_terms[$domain]))
                {

                    array_push($this->imp_terms[$domain], $inverse_parent);// import vocab, avoid importing partial vocabs
                    $this->inverse_term_pairs[$term_id] = $inverse_id; // for correcting later

                    array_push($added_vcb_ids, $inverse_parent);
                }
            }

        }

        if(!isEmptyArray($added_vcb_ids)){
            $this->_getInverseTerms($added_vcb_ids);// get possible inverse terms for new terms
        }
    }

    // @to_review move to dbsRectypes
    //
    //
    private function _findDependentRecordTypesByFieldId($field_id){

        $res = array();

        $def_dts = $this->source_defs['detailtypes']['typedefs'];
        $idx_type = $def_dts['fieldNamesToIndex']['dty_Type'];
        $idx_ccode = $def_dts['fieldNamesToIndex']['dty_ConceptID'];
        $idx_constraints = $def_dts['fieldNamesToIndex']['dty_PtrTargetRectypeIDs'];
        $idx_terms = $def_dts['fieldNamesToIndex']['dty_JsonTermIDTree'];

        $field = $def_dts[$field_id]['commonFields'];

        $ccode = $field[$idx_ccode];//from source

        //is this field type already in target?
        $local_dtid = $this->_getLocalCode('detailtype', $this->target_defs, $ccode);
        if($local_dtid>0){  //already exist
            $this->fields_correspondence[$field_id] = $local_dtid;
        }

        array_push($this->imp_fieldtypes, $field_id);

        $res = array();

        $dt_Type = $field[$idx_type];

        if($dt_Type == "resource" || $dt_Type == "relmarker"){

            $constraints = $field[$idx_constraints];

            $rty_ids = explode(",", $constraints);
            foreach ($rty_ids as $rty_id){
                if(@$this->source_defs['rectypes']['names'][$rty_id]){

                    $dep = $this->_findDependentRecordTypes($rty_id, 0);
                    if($dep){
                        array_push($res, $dep);
                    }

                }
            }
        }
        if($dt_Type == "enum" || $dt_Type == "relmarker" || $dt_Type == "relationtype"){
            //find vocabulary to be imported
            $this->_getTopMostVocabulary($field[$idx_terms], $dt_Type);
        }


        return $res;
    }

    // @to_review move to dbsRectypes
    //
    // search source defintions
    // find all dependend record types in pointer constraints
    // 1) returns rt tree 2) fills $this->imp_recordtypes, $this->rectypes_correspondence
    //
    // if $this->conservative is true and dependednt record type already exists in target database, it will not be imported
    //
    private function _findDependentRecordTypes($rectype_id, $depth){

        $trg_rectypes = $this->target_defs['rectypes'];

        if(!($rectype_id>0) || in_array($rectype_id, $this->imp_recordtypes) || $depth>9){
            //already in array
            return false;
        }

        $def_rts = $this->source_defs['rectypes']['typedefs'];
        $def_dts = $this->source_defs['detailtypes']['typedefs'];
        $idx_type = $def_rts['dtFieldNamesToIndex']['dty_Type'];
        $idx_ccode = $def_rts['commonNamesToIndex']['rty_ConceptID'];
        $idx_constraints = $def_dts['fieldNamesToIndex']['dty_PtrTargetRectypeIDs'];

        $ccode = $def_rts[$rectype_id]['commonFields'][$idx_ccode];//from source

        //is this record type already in target?
        $local_recid = 0;//$this->_getLocalIfUnregistered($ccode);
        if($local_recid===0){ //registered concept code
            $local_recid = $this->_getLocalCode('rectype', $this->target_defs, $ccode);
        }else{
            $local_recid = 0 ;//Important: it is assumed that record type from unregistered db doesn't exist in target
        }

        if($local_recid>0){  //already exist in destination
            $this->rectypes_correspondence[$rectype_id] = $local_recid;
            if($this->conservative && $depth>0){
                return false; //rectype with the same concept code is already in database
            }
        }
        array_push($this->imp_recordtypes, $rectype_id);

        $res = array('correspondence'=>$local_recid, 'dependence'=>array());
        
        $fields = $def_rts[$rectype_id]['dtFields'];

        if(is_array($fields)){
            //loop all fields check constraint for pointers and relmarkers
            foreach ($fields as $ftId => $field){

                    if($field[$idx_type] == "resource" || $field[$idx_type] == "relmarker"){

                        $constraints = $def_dts[$ftId]['commonFields'][$idx_constraints];

                        $rty_ids = explode(",", $constraints);
                        foreach ($rty_ids as $rty_id){
                            if(@$this->source_defs['rectypes']['names'][$rty_id]!=null){

                                $dep = $this->_findDependentRecordTypes($rty_id, $depth+1);
                                if($dep){
                                    $res['dependence'][$rty_id] = $dep;
                                }

                            }
                        }
                    }
            }
        }
        return $res;
    }

    /*
     import entire vocabulary (including all children)

     $term_id - term to be imported, if not defined use $this->imp_terms to import all missing vocabs
     $domain - enum or relation
     $all_terms_in_vocab - target: all terms by vocabulary (plain array)
     $children - array of term ids (source)
     $parent_id - parent term id (target)
     $same_level_labels - labels and codes on the same level to disambiguate

    */
    private function _importVocabulary($term_id, $domain, &$all_terms_in_vocab, $children=null, $parent_id=null, $same_level_labels=null){


        if($term_id==null){
            //loop through all this->imp_terms

            //fills $this->vcg_correspondence
            $this->_importVocabularyGroups($this->imp_terms[$domain]);

            //top level import vocabularies
            foreach($this->imp_terms[$domain] as $term_id){
                $children = @$this->source_defs['terms']['trm_Links'] && @$this->source_defs['terms']['trm_Links'][$term_id]
                                ?@$this->source_defs['terms']['trm_Links'][$term_id]   //new structure with terms by reference
                                :@$this->source_defs['terms']['treesByDomain'][$domain][$term_id];

                $all_terms_in_vocab = array();//reset
                $res = $this->_importVocabulary($term_id, $domain, $all_terms_in_vocab, $children, null, null);
                if(!$res) {return false;}
            }

            return true;
        }else{

            $terms = $this->source_defs['terms'];

            //heurist core definitions may return old format - without new fields
            if(!(@$terms['fieldNamesToIndex']['trm_OrderInBranch']>0)){

            }
            if(!(@$terms['fieldNamesToIndex']['trm_NameInOriginatingDB']>0)){
                $terms['fieldNamesToIndex']['trm_NameInOriginatingDB'] = 19;
                $terms['commonFieldNames'][19] = 'trm_NameInOriginatingDB';
            }

            $columnNames = $terms['commonFieldNames'];
            $idx_ccode = intval($terms['fieldNamesToIndex']["trm_ConceptID"]);
            $idx_parentid = intval($terms['fieldNamesToIndex']["trm_ParentTermID"]);
            $idx_inverseid = intval($terms['fieldNamesToIndex']["trm_InverseTermID"]);
            $idx_label = intval($terms['fieldNamesToIndex']["trm_Label"]);
            $idx_code  = intval($terms['fieldNamesToIndex']["trm_Code"]);
            $idx_origin_dbid  = intval($terms['fieldNamesToIndex']["trm_OriginatingDBID"]);
            $idx_origin_id  = intval($terms['fieldNamesToIndex']["trm_IDInOriginatingDB"]);
            $idx_origin_name   = $terms['fieldNamesToIndex']['trm_NameInOriginatingDB'];

            $idx_vocab_group_id  = intval($terms['fieldNamesToIndex']["trm_VocabularyGroupID"]);

            //search both domains
            $term_import = $this->sourceTerms->getTerm($term_id, $domain);
            //$term_import = $terms['termsByDomainLookup'][$domain][$term_id];//6256 returns wrong!!

            // Fill in missing original values
            DbsImport::_setConceptValues($term_import, [$this->source_db_reg_id, $term_id, $term_import[$idx_label]], [$idx_ccode, $idx_origin_dbid, $idx_origin_id, $idx_origin_name]);

            //find term by concept code among local terms
            $new_term_id = $this->targetTerms->findTermByConceptCode($term_import[$idx_ccode], $domain);

            if($new_term_id){
                //this term aready exists in target - add it as reference to this vocabulary
                if($parent_id>0){ //this is not vocabulary

                    //check that this term is already in vocab
                    if(!in_array($new_term_id, $all_terms_in_vocab)){

                        //for second level references: term may refer to real parent
                        if($this->targetTerms->isTermLinked($parent_id, $new_term_id)){
                            array_push($all_terms_in_vocab, $new_term_id);
                        }else{
                            //add as reference
                            $res = addTermReference($parent_id, $new_term_id, $this->system->get_mysqli());//see saveStructureLib
                            if($res!==false){
                                array_push($all_terms_in_vocab, $new_term_id);
                                $this->targetTerms->addNewTermRef($parent_id, $new_term_id);//add in memory

                            }else{
                                //$this->system->addError(HEURIST_ERROR, "Can't add reference ".$term_id.' to '.$parent_id);
                                return false;
                            }
                        }
                    }
                }else{
                    //find vocabulary in target and all its terms
                    $all_terms_in_vocab = $this->targetTerms->treeData($new_term_id, 3);
                }

                if($this->rename_target_entities){
                    renameTerm($new_term_id, $term_import, $terms['fieldNamesToIndex']);
                }

                if(!in_array($new_term_id, $this->terms_updated)) {$this->terms_updated[] = $new_term_id;}
                $new_term_id = -$new_term_id;
            }else{
                //if not found add new term

                //change trm_InverseTermID, trm_ParentTermID  to new id from target
                $term_import[$idx_parentid] = $parent_id;//@$this->terms_correspondence[$term_import[$idx_parentid]];
                $term_import[$idx_inverseid] = @$this->terms_correspondence[$term_import[$idx_inverseid]];

                //get level - all terms of the same level - to search same name and codes
                //1317-3790 Org type selection
                //verify that code and label is unique for the same vocabulary in target(local) db
                if($parent_id==null){
                    //for vocabularies
                    $term_import[$idx_code] = $this->targetTerms->doDisambiguateTerms($term_import[$idx_code],
                                                            $idx_code);
                    $term_import[$idx_label] = $this->targetTerms->doDisambiguateTerms($term_import[$idx_label],
                                                            $idx_label);

                    if(@$term_import[$idx_vocab_group_id]>0 && @$this->vcg_correspondence[$term_import[$idx_vocab_group_id]]>0){
                        $term_import[$idx_vocab_group_id] = $this->vcg_correspondence[$term_import[$idx_vocab_group_id]];
                    }else{
                        //be default place into 1
                        $term_import[$idx_vocab_group_id] = 1;
                    }


                }else{
                    $term_import[$idx_code] = $this->targetTerms->doDisambiguateTerms2($term_import[$idx_code],
                                                $same_level_labels['code']);
                    $term_import[$idx_label] = $this->targetTerms->doDisambiguateTerms2($term_import[$idx_label],
                                                $same_level_labels['label']);

                    $term_import[$idx_vocab_group_id] = 0;
                }

                $res = updateTerms($columnNames, null, $term_import, $this->system->get_mysqli());//see saveStructureLib

                if(is_numeric($res)){
                    $new_term_id = $res;

                    $this->terms_added[] = $new_term_id;

                    $this->targetTerms->addNewTerm($new_term_id, $term_import);//add in memory

                    array_push($all_terms_in_vocab, $new_term_id);
                }else{
                    if($parent_id==null){
                        $this->system->addError(HEURIST_ERROR,
                        "Can't import vocabulary ".$term_id.' '.$res);//.print_r($term_import, true)."  ".$res);
                        return false;
                    }else{
                        //add to issue report - summary will be send to support email
                        array_push($this->broken_terms, $term_import);
                        array_push($this->broken_terms_reason, $res);
                        return -1; // if this term has children they are ignored too
                    }
                }
            }

            if($new_term_id > 0 && !array_key_exists($new_term_id, $this->def_translations['terms'])){
                $this->def_translations['terms'][$new_term_id] = array('trn_Source' => 'trm_Label', 'trn_Code' => $term_import[$idx_origin_id]);
            }

            //fill $terms_correspondence
            $this->terms_correspondence[$term_id] = abs($new_term_id);

            if($children){

                $target_parent_id = abs($new_term_id);

                //find same level codes and labels in target
                $lvl_src = $this->targetTerms->getSameLevelLabelsAndCodes($target_parent_id, $domain);

                foreach($children as $id){

                    if(@$this->source_defs['terms']['trm_Links']){
                        $children2 = @$this->source_defs['terms']['trm_Links'][$id];
                    }else{
                        $children2 = @$children[$id];
                    }

                    //($term_id, $domain, $children=null, $parent_id=null, $same_level_labels=null)
                    $new_id = $this->_importVocabulary($id, $domain, $all_terms_in_vocab, $children2, $target_parent_id, $lvl_src);
                    if($new_id>0){
                        //new term - add to codes and labels
                        $lvl_src['code'][] = $this->targetTerms->getTermCode($new_id);
                        $lvl_src['label'][] = $this->targetTerms->getTermLabel($new_id);
                    } elseif($new_id<0){
                        //this term aready exists in target - add it as reference to this vocabulary
                    }else {
                        return false;
                    }

                }
            }

            return $new_term_id;
        }
    }//_importVocabulary

    //
    // check vocabulry group by name and import if missed
    // for older databases all imported vocab will be placed to group "Import"
    //
    private function _importVocabularyGroups($src_vocab_ids){

        $columnNames = array("vcg_Name","vcg_Domain","vcg_Order","vcg_Description");
        $idx_vcg_grp = @$this->source_defs['terms']['fieldNamesToIndex']['trm_VocabularyGroupID'];
        $idx_parent = $this->source_defs['terms']['fieldNamesToIndex']['trm_ParentTermID'];

        $mysqli = $this->system->get_mysqli();

        $is_old_db_version = false;
        if(!($idx_vcg_grp>0) || @$this->source_defs['terms']['groups']==null){
            //old version of source - add new field and set all groups to "Import"
            $is_old_db_version = true;

            if(!($idx_vcg_grp>0)){
                $idx_vcg_grp = 20;
                $this->source_defs['terms']['commonFieldNames'][$idx_vcg_grp] = 'trm_VocabularyGroupID';
                $this->source_defs['terms']['fieldNamesToIndex']['trm_VocabularyGroupID'] = $idx_vcg_grp;
            }

            $this->source_defs['terms']['groups'] = array(11=>
                array('vcg_ID'=>"11", 'vcg_Name'=>"Import", 'vcg_Domain'=>"enum",
                'vcg_Order'=>"007", 'vcg_Description'=>"Imported vocabularies"));
        }

        foreach($src_vocab_ids as $term_id){

            $rt = $this->sourceTerms->getTerm($term_id);

            $parent_id = @$rt[$idx_parent];
            if($parent_id>0) {continue;} //this is not vocabulary

            if($is_old_db_version){
                $rt[$idx_vcg_grp] = 11;
            }

            $grp_id = @$rt[$idx_vcg_grp];

            if(!$grp_id){
                $this->error_exit2("Vocabulary Group ID is not defined for vocabulary #'$term_id'. in source database");
                return false;
            }

            $src_group=null;

            if(@$this->vcg_correspondence[$grp_id]){ //already found
                continue;
            }

            //find group in source by ID
            $src_group = @$this->source_defs['terms']['groups'][$grp_id];

            if($src_group==null){
                $this->error_exit2("Can't find vocabulary group #$grp_id for vocabulary #'$term_id'. in source database");
                return false;
            }
            $grp_name = @$src_group['vcg_Name'];
            if(!$grp_name){
                $this->error_exit2("Name of group is empty. Can't add group #$grp_id for vocabulary #'$term_id'. in source database");
                return false;
            }

            //get name and try to find in target by name
            $isNotFound = true;
            foreach ($this->target_defs['terms']['groups'] as $id=>$group){
                if(is_numeric($id) && mb_strtolower(trim($group['vcg_Name']))== mb_strtolower(trim($grp_name))){
                    $this->vcg_correspondence[$grp_id] = $id;
                    $isNotFound = false;
                    break;
                }
            }

            if($isNotFound){ //add new one

                $src_group['vcg_ID'] = -1;
                $new_grp_id = mysql__insertupdate($mysqli,'defVocabularyGroups','vcg',$src_group);

                if( is_numeric($new_grp_id) ){
                    $this->vcg_correspondence[$grp_id] = $new_grp_id;
                    $src_group['vcg_ID'] = $new_grp_id;
                    $this->target_defs['terms']['groups'][$new_grp_id] = $src_group;
                }else{
                    $this->error_exit2("Can't add vocabulary group '".$grp_name."'. ".$mysqli->error);
                    return false;
                }
            }
        }
    }


    // 
    // Copy record type icon and thumbnail from source to destination database
    //
    private function copyRectypeIcon($source_RtyID, $target_RtyID, $is_thumb=false){

        //new  entity/defRecTypes/  --------------------------------------------

        //@todo SVG ext support
        $targetFolder = HEURIST_FILESTORE_DIR.'/entity/defRecTypes/';
        $targetPath = $targetFolder.($is_thumb?'/thumbnail/':'/icon/').$target_RtyID.'.png';

        //check if the same server with target
        if(strpos($this->sourceIconURL, HEURIST_SERVER_URL)===0){

            $filename = HEURIST_FILESTORE_ROOT.$this->source_db_name.'/entity/defRecTypes/'.
                    ($is_thumb?'/thumbnail/':'/icon/').$source_RtyID.'.png';
            if(file_exists($filename)){
                if(file_exists($targetPath)){
                    unlink($targetPath);
                }
                copy($filename, $targetPath);
            }
        }else{
            $sourceURL = $this->sourceIconURL.$source_RtyID.($is_thumb?'&version=thumb':'');
            saveURLasFile($sourceURL, $targetPath);//save rty icon on import
        }


        //and thumbnail
        if(!$is_thumb){
            $this->copyRectypeIcon($source_RtyID, $target_RtyID, true);
        }
    }

    //
    // If entity with the same nae exists in target database it adds numeric count to the end of name
    //
    private function doDisambiguate($newvalue, $entities){

        if(!$newvalue || $newvalue=="") {return $newvalue;}

        $found = 0;
        $name = removeLastNum($newvalue);

        foreach($entities as $id=>$name1){
            $name1 = removeLastNum($name1);
            if(strcasecmp($name, $name1)==0){
                $found++;
            }
        }

        if($found>0){
            $found++;
            $newvalue = $name.' '.$found;
        }

        do{
            $not_unique = false;
            foreach($entities as $id=>$name1){
                if(strcasecmp($newvalue, $name1)==0){
                    $found++;
                    $newvalue = $name.' '.$found;
                    $not_unique = true;
                    break;
                }
            }
        }while ($not_unique);

        return $newvalue;
    }


    //
    // replace rectype ids in constraint string to local id
    //
    private function replaceRectypeIds($constraints){

        if($constraints){
            $recids = explode(",", $constraints);
            $recids_new = array();
            foreach($recids as $recid){
                if(@$this->rectypes_correspondence[$recid]){
                    array_push($recids_new, $this->rectypes_correspondence[$recid]);
                }
            }
            $constraints = implode(",",$recids_new);
        }
        return $constraints;
    }

    //
    // replace term_ids in string to new ones
    // function that translates all term ids in the passed string to their local/imported value
    //
    public function replaceTermIds( $sterms, $domain ) {

        if($sterms==null || $sterms=="") {return $sterms;}

        if($domain=="relationtype") {$domain = "relation";}

        // Import terms

        if (strpos($sterms,"{")!== false) {
            foreach ($this->terms_correspondence as $imp_id=>$trg_id) {
                //replace termID in string
                $sterms = preg_replace("/\"".$imp_id."\"/","\"".$trg_id."\"",$sterms);
            }
        }else{
            $aterms = explode(",",$sterms);
            $aterms_new = array();
            foreach ($aterms as $imp_id) {
                if(@$this->terms_correspondence[$imp_id]){
                    array_push($aterms_new, $this->terms_correspondence[$imp_id]);
                }
            }
            $sterms = implode(",", $aterms_new);
        }
        return $sterms;
    }

    //
    // Preparation report output
    // it returns updated definitions (as json) and html snippets with report about imported definitions
    //
    public function getReport($need_updated_defs=true){

        //reload structures
        $trg_rectypes = null;
        $trg_detailtypes = null;
        $trg_terms = null;

        $def_rts = @$this->source_defs['rectypes']['typedefs'];
        $def_dts = @$this->source_defs['detailtypes']['typedefs'];

        $sRectypes = '';
        $sFields  = '';
        $sTerms  = '';

        //RECORD TYPES
        if($def_rts){

            $idx_name  = $def_rts['commonNamesToIndex']['rty_Name'];
            $idx_ccode = $def_rts['commonNamesToIndex']["rty_ConceptID"];

            foreach ($this->imp_recordtypes as $imp_id){
                if(@$this->rectypes_correspondence[$imp_id]){
                    $trg_id = $this->rectypes_correspondence[$imp_id];

                    if($trg_rectypes==null){
                        $trg_rectypes = dbs_GetRectypeStructures($this->system, null, 0);//only names
                    }

                    $sRectypes = $sRectypes."<tr><td>$imp_id</td><td>".$def_rts[$imp_id]['commonFields'][$idx_name]
                    .TD
                    .$def_rts[$imp_id]['commonFields'][$idx_ccode]
                    ."</td><td>$trg_id</td><td>"
                    .@$trg_rectypes['names'][$trg_id]
                    //.$trg_rectypes['typedefs'][$trg_id]['commonFields'][$idx_name]
                    ."</td>" //."<td>".$trg_rectypes['typedefs'][$trg_id]['commonFields'][$idx_titlemask_canonical]."</td>"
                    ."</tr>";
                }
            }
        }

        //FIELD TYPES
        if($def_dts){

            $idx_name  = $def_dts['fieldNamesToIndex']['dty_Name'];
            $idx_ccode = $def_dts['fieldNamesToIndex']["dty_ConceptID"];

            foreach ($this->fields_correspondence as $imp_id=>$trg_id){

                if($trg_detailtypes==null){
                    $trg_detailtypes = dbs_GetDetailTypes($this->system, null, 0);//only names
                }

                $sFields = $sFields."<tr><td>$imp_id</td><td>".$def_dts[$imp_id]['commonFields'][$idx_name]
                .TD
                .$def_dts[$imp_id]['commonFields'][$idx_ccode]
                ."</td><td>$trg_id</td><td>"
                .@$trg_detailtypes['names'][$trg_id].TR_E;
                //.$trg_detailtypes['typedefs'][$trg_id]['commonFields'][$idx_name].TR_E;
            }
        }

        //TERMS TYPES
        $def_terms = $this->source_defs['terms'];
        $idx_name  = $def_terms['fieldNamesToIndex']['trm_Label'];
        $idx_ccode = $def_terms['fieldNamesToIndex']["trm_ConceptID"];

        foreach ($this->terms_correspondence as $imp_id=>$trg_id){

            if(@$def_terms['termsByDomainLookup']['enum'][$imp_id]){
                $domain = 'enum';
            }else{
                $domain = 'relation';
            }

            if($trg_terms==null){
                $trg_terms = dbs_GetTerms($this->system);
            }

            $sTerms = $sTerms . "<tr><td>$imp_id</td><td>".$def_terms['termsByDomainLookup'][$domain][$imp_id][$idx_name]
            .TD
            .$def_terms['termsByDomainLookup'][$domain][$imp_id][$idx_ccode]
            ."</td><td>$trg_id</td><td>"
            .$trg_terms['termsByDomainLookup'][$domain][$trg_id][$idx_name].TR_E;
        }

        $resp =  array( 'report'=>array('rectypes'=>$sRectypes,'detailtypes'=>$sFields,'terms'=>$sTerms,'translations'=>$this->translations_report) );

        // Add updated and added values
        if($this->prime_defType == 'all'){

            $resp['report']['updated']['terms'] = $this->terms_updated;
            $resp['report']['added']['terms'] = $this->terms_added;

            $resp['report']['updated']['detailtype'] = $this->detailtypes_updated;
            $resp['report']['added']['detailtype'] = $this->detailtypes_added;

            $resp['report']['updated']['rectype'] = $this->rectypes_upddated;
            $resp['report']['added']['rectype'] = $this->rectypes_added;
        }elseif($this->prime_defType == 'term'){
            $resp['report']['updated'] = $this->terms_updated;
            $resp['report']['added'] = $this->terms_added;
        }elseif($this->prime_defType == 'detailtype'){
            $resp['report']['updated'] = $this->detailtypes_updated;
            $resp['report']['added'] = $this->detailtypes_added;
        }else{
            $resp['report']['updated'] = $this->rectypes_upddated;
            $resp['report']['added'] = $this->rectypes_added;

            $resp['extra'] = [
                'detailtypes'=>[ 'added' => $this->detailtypes_added, 'updated' => $this->detailtypes_updated ],
                'terms'=>[ 'added' => $this->terms_added, 'updated' => $this->terms_updated ]
            ];
        }

        if(!empty($this->broken_terms_reason)){
            $resp['report']['broken_terms'] = $this->broken_terms;
            $resp['report']['broken_terms_reason'] = $this->broken_terms_reason;
        }

        if($need_updated_defs){
            //2021-06-15 we don't use old format for defintions anymore
            //$resp['defs'] = array('rectypes'=>$trg_rectypes,'detailtypes'=>$trg_detailtypes,'terms'=>$trg_terms);

            $data = $this->system->getCurrentUserAndSysInfo(true);
            $resp['defs']['sysinfo'] = $data['sysinfo'];

            $resp['defs']['entities'] = entityRefreshDefs($this->system, 'all', false);

        }
        return $resp;
    }

    //
    // helper function
    //
    private function __tree_to_links($tree, &$links){

        foreach($tree as $parent=>$children){
           $links[$parent] = array_keys($children);
           $this->__tree_to_links($children, $links);
        }

    }

    //
    // create virtual trm_Links if it is missed for old version of source
    //
    private function _createTrmLinks(){

        if(@$this->source_defs['terms']['trm_Links']) {return;}

        //links for individual selections
        //loop for details
        $det = $this->source_defs['detailtypes']['typedefs'];
        $idx_type = $det['fieldNamesToIndex']['dty_Type'];
        $idx_tree = $det['fieldNamesToIndex']['dty_JsonTermIDTree'];
        $idx_ccode = $det['fieldNamesToIndex']['dty_ConceptID'];

        $ids1 = array_keys($this->source_defs['terms']['termsByDomainLookup']['enum']);
        $ids2 = array_keys($this->source_defs['terms']['termsByDomainLookup']['relation']);

        $max_id = 999999;//max($ids1[count($ids1)-1], $ids2[count($ids2)-1]);
        //max(max(),
        //            max(array_keys($this->source_defs['terms']['termsByDomainLookup']['relation'])));

        foreach($det as $dty_ID=>$detail){

            if($dty_ID>0){
                $dty_Type = $detail['commonFields'][$idx_type];
                $dty_Tree = $detail['commonFields'][$idx_tree];

                if ($dty_Type=="enum" || $dty_Type=="relmarker"){
                    if($dty_Tree && is_numeric($dty_Tree)){
                        continue;
                    }

                    $dty_cCode = $detail['commonFields'][$idx_ccode];

                    if($dty_cCode=='3-1079'){
                        $vocab_id = 6255;
                    }elseif($dty_cCode=='3-1080'){
                        $vocab_id = 6256;
                    }elseif($dty_cCode=='3-1087'){
                        $vocab_id = 6257;
                    }elseif($dty_cCode=='3-1088'){
                        $vocab_id = 6258;
                    }else{
                        $vocab_id = 0;
                    }

                    //add virtaul vocabulary for individual selections
                    $domain = $dty_Type=='enum'?'enum':'relation';
                    $name = $detail['commonFields'][1].' - selection';

                    if($vocab_id>0){
                        $orig_db_id = 2;
                    }else{
                        $orig_db_id = 0;
                        $vocab_id = $max_id+1;
                    }
                    $max_id = $max_id+1;

                    $vocab = array($name, '0', '', 'open', $orig_db_id, $vocab_id, '0', '0',
                            $domain, '0', '0', '0', '0', '2012-06-04 08:18:57',
                            '0', '', '', '1', //group #1 by default
                            '0',  //trm_OrderInBranch
                            $name, $orig_db_id.'-'.$vocab_id, false);

    /*
    0: "trm_Label"
    1: "trm_InverseTermID"
    2: "trm_Description"
    3: "trm_Status"
    4: "trm_OriginatingDBID"
    5: "trm_IDInOriginatingDB"
    6: "trm_AddedByImport"
    7: "trm_IsLocalExtension"

    8: "trm_Domain"
    9: "trm_OntID"
    10: "trm_ChildCount"
    11: "trm_ParentTermID"
    12: "trm_Depth"
    13: "trm_Modified"

    14: "trm_LocallyModified"
    15: "trm_Code"
    16: "trm_SemanticReferenceURL"
    17: "trm_VocabularyGroupID"
    18: "trm_OrderInBranch"

    19: "trm_NameInOriginatingDB"
    20: "trm_ConceptID"
    21: "trm_HasImage"
    */
                    $this->source_defs['terms']['termsByDomainLookup'][$domain][$max_id] = $vocab;  //ERROR !!!!! assigned as ID
                    $this->source_defs['terms']['treesByDomain'][$domain][$max_id] = json_decode($dty_Tree, true);
                    $this->source_defs['detailtypes']['typedefs'][$dty_ID]['commonFields'][$idx_tree] = $max_id;

                }
            }
        }//for details


        $links = array();

        $tree = $this->source_defs['terms']['treesByDomain']['enum'];
        $this->__tree_to_links($tree, $links);

        $tree = $this->source_defs['terms']['treesByDomain']['relation'];
        $this->__tree_to_links($tree, $links);

        $this->source_defs['terms']['trm_Links'] = $links;
    }

    //
    //
    //
    private function _importTranslations($def){

        if(count($this->def_translations[$def]) == 0){ // no definitions to retrieve
            return;
        }

        $reg_url = explode("?",$this->source_defs['databaseURL']);
        $remote_url = @$reg_url[0];

        preg_match("/db=([^&]*).*$/", $this->source_defs['databaseURL'], $match);
        $remote_dbname = $match[1];

        if(!$remote_dbname || !$remote_url){
            // Invalid source, unable to retrieve translations
            $this->translations_report = 'We were unable to determine the registered database and url.';
            $this->def_translations = array();

            return;
        }

        if(strpos($remote_url, HEURIST_SERVER_URL)===0){ //same server

            $defs = array();

            $system2 = new hserv\System();
            if(!$system2->init($remote_dbname, true, false)){ //init without paths and consts
                // Invalid source, unable to retrieve translations
                $this->translations_report = 'We were unable to connect to the registered database located on this server.';
                $this->def_translations = array();

                return;
            }

            $translations = dbs_GetTranslations($system2, $this->def_translations[$def]);
        }else{ // remote server

            //change hsapi to hserv when master index will be v6.5
            $remoteURL = $remote_url
                .'hserv/controller/sys_structure.php?db='.$remote_dbname.'&'
                . http_build_query(array('translations' => $this->def_translations[$def]));

            $translations = $this->_getRemoteTranslations($remoteURL);

            if(!$translations){
                return;
            }

        }

        if(isEmptyArray(@$translations['translations'])){
            $this->translations_report[$def] = 'No translations found';
        }else{
            $this->_handleTranslations($def, $translations);
        }

        return true;
    }

    private function _getRemoteTranslations($remoteURL){

            $defs = loadRemoteURLContent($remoteURL);
            if(!$defs){ // unable to connect to remote server
                global $glb_curl_error;
                $error_code = (!empty($glb_curl_error)) ? $glb_curl_error : 'Error code: 500 Heurist Error';

                $this->translations_report = "Unable to connect remote server containing the registered database, possibly due to timeout or proxy setting<br>"
                    . $error_code . "<br>"
                    ."URL requested: $remoteURL<br><br>";

                return false;
            }

            $translations = json_decode(gzdecode($defs), true);
            if(!$translations || @$translations['status']!=HEURIST_OK){
                // Invalid source, unable to retrieve translations
                $this->translations_report = 'Invalid translation data was retrieved from remote registered database.';
                $this->def_translations = array();

                return false;
            }

            return $translations['data'];
    }


    private function _handleTranslations($def, $translations){

        $mysqli = $this->system->get_mysqli();

        $delete_stmt = $mysqli->prepare('DELETE FROM defTranslations where trn_Source=? AND trn_Code=?');

        foreach ($translations['key_mapping'] as $local_id => $remote_ids) {

            foreach ($remote_ids as $id) {

                $translation = $translations['translations'][$id];
                $local_id = intval($local_id);

                //$mysqli->query('DELETE FROM defTranslations where trn_Source="'
                //        .$mysqli->real_escape_string($translation['trn_Source'])
                //        .'" AND trn_Code='.$local_id);

                $delete_stmt->bind_param('si', $translation['trn_Source'], $local_id);
                $res33 = $delete_stmt->execute();

                $translation['trn_ID'] = 0;
                $translation['trn_Code'] = $local_id;

                $res = mysql__insertupdate($mysqli, 'defTranslations', 'trn', $translation);

                if($res > 0 && in_array($local_id, $this->translations_report[$def])){
                    $this->translations_report[$def][] = $local_id;
                }
            }
        }

    }

    //
    //
    //
    private function _correctInverseIds(){

        if(empty($this->inverse_term_pairs)){
            return true;
        }

        $mysqli = $this->system->get_mysqli();
        $idx_inverseid = intval($this->target_defs['terms']['fieldNamesToIndex']["trm_InverseTermID"]);

        foreach($this->inverse_term_pairs as $org_trm_id => $org_inv_id){

            $trm_id = @$this->terms_correspondence[$org_trm_id];
            $inverse_id = @$this->terms_correspondence[$org_inv_id];

            if(intval($trm_id) < 1 || intval($inverse_id) < 1){
                continue;
            }

            $term = $this->targetTerms->getTerm($trm_id, 'relation');
            $term[$idx_inverseid] = $inverse_id;

            $inverse_term = $this->targetTerms->getTerm($inverse_id, 'relation');
            $inverse_term[$idx_inverseid] = $trm_id;

            $res = updateTerms(array('trm_InverseTermID'), $trm_id, array($inverse_id), $mysqli);
            if(!is_numeric($res)){
                array_push($this->broken_terms, $term);
                array_push($this->broken_terms_reason, $res);
                //return false;
            }else{
                $this->targetTerms->addNewTerm($trm_id, $term);//add in memory
                $this->targetTerms->addNewTerm($inverse_id, $inverse_term);//add in memory
            }


        }

        return true;
    }

    /**
     * Assigns concept values [concept ID, original DB ID, original ID, and original Name] to current definition
     *  Set these here to avoid situations were the definitions are missing these values, causing potential issues later on
     *
     * @param array $definition - row representing the concept being imported
     * @param array $concept_values - [original database ID, original ID, original name]
     * @param array $indexes - array of indexes to access values from $definition [conceptCode, originDBID, originID, originName]
     * @return void
     */
    private function _setConceptValues($definition, $concept_values, $indexes){

        $source_DBID = @$concept_values[0];
        $source_ID = @$concept_values[1];
        $source_Name = @$concept_values[2];

        if(!ctype_digit($source_DBID) || intval($source_DBID) < 0
        || !ctype_digit($source_ID) || intval($source_ID) <= 0){
            return;
        }

        $missing_conceptID = empty($definition[$indexes[0]]);
        // Use the existing concept codes for source IDs
        if(!$missing_conceptID){
            $parts = explode('-', $definition[$indexes[0]]);
            if(count($parts) == 2 && ctype_digit($parts[0]) && ctype_digit($parts[1])){
                $source_DBID = intval($parts[0]);
                $source_ID = intval($parts[1]);
            }
        }

        // Check if either original ID is missing
        $update_conceptID = false;
        if(empty($definition[$indexes[1]]) || empty($definition[$indexes[2]])){
            $definition[$indexes[1]] = $source_DBID;
            $definition[$indexes[2]] = $source_ID;
            $update_conceptID = true;
        }

        // Update concept code, if missing or other original IDs were updated
        if($missing_conceptID || $update_conceptID){
            $definition[$indexes[0]] = "{$source_DBID}-{$source_ID}";
        }

        // Update original name
        if(empty($definition[$indexes[3]]) && !empty($source_Name)){
            $definition[$indexes[3]] = $source_Name;
        }
    }
}
?>
