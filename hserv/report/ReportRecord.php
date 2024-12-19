<?php
namespace hserv\report;

use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../structure/search/dbsData.php';
require_once dirname(__FILE__).'/../records/search/recordSearch.php';
require_once dirname(__FILE__).'/../records/search/relationshipData.php';
require_once dirname(__FILE__).'/../structure/dbsTerms.php';

require_once dirname(__FILE__).'/../utilities/Temporal.php';
require_once dirname(__FILE__).'/../../vendor/autoload.php';//for geoPHP

/**
 * Class ReportRecord
 *
 * A helper class to access Heurist data from Smarty reports. Provides methods to get record details,
 * related records, file field info, and to access Heurist constants.
 */
class ReportRecord
{
    protected $recordsCache;  // Cache for loaded records
    protected $rtyNames;    // Record type names
    protected $dtyTypes;    // Detail types
    protected $dtTerms = null;    // Detail terms
    protected $dbsTerms;     // Database terms object
    protected $system;       // System object
    protected $translations; // Cache for translated db definitions (terms,...)

    /**
     * ReportRecord constructor.
     *
     * Initializes system, loads record type names and detail types, and sets up cache structures.
     *
     * @param mixed $system The system object used for database and other interactions.
     */
    public function __construct($system)
    {
        $this->system = $system;
        $this->rtyNames = dbs_GetRectypeNames($system->getMysqli());
        $this->dtyTypes = dbs_GetDetailTypes($system, null, 4);
        $this->recordsCache = array(); // Cache for loaded records
        $this->translations = array('trm' => array());
    }

    /**
     * Returns the value of Heurist constants for Record and Detail types.
     *
     * @param string $name The name of the constant.
     * @param mixed|null $smarty_obj Unused Smarty object reference.
     * @return mixed|null The constant value or null if not defined.
     */
    public function constant($name, $smarty_obj = null)
    {
        $id = $this->system->getConstant($name);
        return $id;
    }

    /**
     * Returns the base URL of the system.
     *
     * @return string The base URL of the system (HEURIST_BASE_URL).
     */
    public function baseURL()
    {
        return HEURIST_BASE_URL;
    }

    /**
     * Returns various system information like database record counts or language settings.
     *
     * @param string $param The system information parameter to retrieve.
     * @return mixed|null The requested system information.
     */
    public function getSysInfo($param)
    {
        $res = null;
        $mysqli = $this->system->getMysqli();

        if ($param == 'db_total_records') {
            $res = mysql__select_value($mysqli, 'SELECT count(*) FROM Records WHERE not rec_FlagTemporary');
        } elseif ($param == 'db_rty_counts') {
            $res = mysql__select_assoc2($mysqli, 'SELECT rec_RecTypeID, count(*) FROM Records WHERE not rec_FlagTemporary GROUP BY rec_RecTypeID');
        } elseif ($param == 'lang') {
            $res = $_REQUEST['lang'] ?? $this->system->userGetPreference('layout_language', '');
            $res = getLangCode3($res);
        } elseif ($param == 'dbname') {
            $res = $this->system->dbname();
        } elseif ($param == 'user') {
            $usr = $this->system->getCurrentUser();
            unset($usr['ugr_Preferences']);
            return $usr;
        }

        return $res;
    }

    /**
     * Returns the name of a record type by its ID.
     *
     * @param int $rty_ID The record type ID.
     * @return string The name of the record type.
     */
    public function rty_Name($rty_ID)
    {
        return $this->rtyNames[$rty_ID];
    }

    /**
     * Returns the local record type ID for a given concept code.
     *
     * @param string $conceptCode The concept code.
     * @param mixed|null $smarty_obj Unused Smarty object reference.
     * @return int The local record type ID.
     */
    public function rty_id($conceptCode, $smarty_obj = null)
    {
        return ConceptCode::getRecTypeLocalID($conceptCode);
    }

    /**
     * Returns the local detail type ID for a given concept code.
     *
     * @param string $conceptCode The concept code.
     * @param mixed|null $smarty_obj Unused Smarty object reference.
     * @return int The local detail type ID.
     */
    public function dty_id($conceptCode, $smarty_obj = null)
    {
        return ConceptCode::getDetailTypeLocalID($conceptCode);
    }

    /**
     * Returns the local term ID for a given concept code.
     *
     * @param string $conceptCode The concept code.
     * @param mixed|null $smarty_obj Unused Smarty object reference.
     * @return int The local term ID.
     */
    public function trm_id($conceptCode, $smarty_obj = null)
    {
        return ConceptCode::getTermLocalID($conceptCode);
    }

    /**
     * Retrieves record information by record ID, formatted for Smarty reports.
     *
     * @param mixed $rec The record ID or record array.
     * @param mixed|null $smarty_obj Unused Smarty object reference.
     * @return array|null The record information formatted for Smarty.
     */
    public function getRecord($rec, $smarty_obj = null)
    {
        $rec_ID = is_array($rec) && $rec['recID'] ? $rec['recID'] : $rec;

        if (@$this->recordsCache[$rec_ID]) {
            return $this->recordsCache[$rec_ID];
        }

        $rec = recordSearchByID($this->system, $rec_ID);
        if ($rec) {
            $rec['rec_Tags'] = recordSearchPersonalTags($this->system, $rec_ID);
            if (is_array($rec['rec_Tags'])) {
                $rec['rec_Tags'] = implode(',', $rec['rec_Tags']);
            }
            $rec['rec_IsVisible'] = $this->recordIsVisible($rec);
        }

        return $this->getRecordForSmarty($rec);
    }

    /**
     * Returns whether a record is visible to the current user.
     *
     * @param array $rec The record array.
     * @return bool True if the record is visible, false otherwise.
     */
    public function recordIsVisible($rec)
    {
        if (@$rec['rec_FlagTemporary'] == 1) {
            return false;
        }

        $currentUser = $this->system->getCurrentUser();

        if ($currentUser['ugr_ID'] == 2) { // db owner
            return true;
        }

        $res = true;

        if ($rec['rec_NonOwnerVisibility'] == 'hidden') {
            $res = false;
        } elseif ($currentUser['ugr_ID'] > 0 && $rec['rec_NonOwnerVisibility'] == 'viewable') {
            $wg_ids = @$currentUser['ugr_Groups'] ? array_keys($currentUser['ugr_Groups']) : $this->system->getUserGroupIds();
            array_push($wg_ids, 0); // Include generic everybody workgroup

            if (!isEmptyArray($wg_ids) && !in_array($rec['rec_OwnerUGrpID'], $wg_ids)) {
                $allowed_groups = mysql__select_list2($this->system->getMysqli(), 'SELECT rcp_UGrpID FROM usrRecPermissions WHERE rcp_RecID=' . $rec['rec_ID']);
                if (empty($allowed_groups) && count(array_intersect($allowed_groups, $wg_ids)) > 0) {
                    $res = false;
                }
            }
        }

        return $res;
    }

    /**
     * Returns an array of related records with additional relation details.
     *
     * @param mixed $rec The record ID or record array.
     * @param mixed|null $smarty_obj Unused Smarty object reference.
     * @return array The array of related records with relationship details.
     */
    public function getRelatedRecords($rec, $smarty_obj = null)
    {
        $rec_ID = $rec['recID'] ?? $rec;

        $relRT = $this->rty_id('2-1'); //RT_RELATION
        $relSrcDT =  $this->dty_id('2-7');  //DT_PRIMARY_RESOURCE
        $relTrgDT = $this->dty_id('2-5'); //DT_TARGET_RESOURCE

        $res = array();
        $rel_records = array();

        if (!($rec_ID > 0 && $relRT > 0 && $relSrcDT > 0 && $relTrgDT > 0)) {
             return $res;
        }

        $mysqli = $this->system->getMysqli();
        $from_res = $mysqli->query('SELECT rl_RelationID as dtl_RecID FROM recLinks WHERE rl_RelationID IS NOT NULL AND rl_SourceID=' . $rec_ID);
        $to_res = $mysqli->query('SELECT rl_RelationID as dtl_RecID FROM recLinks WHERE rl_RelationID IS NOT NULL AND rl_TargetID=' . $rec_ID);

        if (!($from_res && $to_res && ($from_res->num_rows > 0 || $to_res->num_rows > 0))) {
             return $res;
        }

        while ($reln = $from_res->fetch_assoc()) {
            $bd = fetch_relation_details($this->system, $reln['dtl_RecID'], true);
            array_push($rel_records, $bd);
        }
        while ($reln = $to_res->fetch_assoc()) {
            $bd = fetch_relation_details($this->system, $reln['dtl_RecID'], false);
            array_push($rel_records, $bd);
        }

        foreach ($rel_records as $value) {
            if (array_key_exists('RelatedRecID', $value) && array_key_exists('RelTerm', $value)) {
                $record = $this->getRecord($value['RelatedRecID']['rec_ID']);

                $record["recRelationID"] = $value['recID'];
                $record["recRelationType"] = $value['RelTerm'];
                $record["recRelationNotes"] = $value['Notes'] ?? null;
                $record["recRelationStartDate"] = \Temporal::toHumanReadable($value['StartDate']) ?? null;
                $record["recRelationEndDate"] = \Temporal::toHumanReadable($value['EndDate']) ?? null;

                $record["relationRecord"] = $this->getRecord($value['recID']);

                array_push($res, $record);
            }
        }

        $from_res->close();
        $to_res->close();


        return $res;
    }

    /**
     * Returns an array of linked records for a given record.
     *
     * @param mixed $rec The record ID or record array.
     * @param int|null $rty_ID The record type ID to filter linked records (optional).
     * @param string|null $direction The direction of the link ('linkedto', 'linkedfrom', or null for both directions).
     * @param mixed|null $smarty_obj Unused Smarty object reference.
     * @return array An array with keys 'linkedto' and 'linkedfrom' containing linked record IDs.
     */
    public function getLinkedRecords($rec, $rty_ID = null, $direction = null, $smarty_obj = null)
    {
        $rec_ID = is_array($rec) && $rec['recID'] ? $rec['recID'] : $rec;
        $where = SQL_WHERE;
        $predicateRty = predicateId('rec_RecTypeID', $rty_ID, SQL_AND);

        if ($predicateRty != '') {
            $where = ', Records WHERE linkID=rec_ID ' . $predicateRty . SQL_AND;
        }

        $mysqli = $this->system->getMysqli();
        $to_records = array();
        $from_records = array();

        if ($direction == null || $direction == 'linkedto') {
            $from_query = 'SELECT rl_TargetID as linkID FROM recLinks ' . str_replace('linkID', 'rl_TargetID', $where) . ' rl_RelationID IS NULL AND rl_SourceID=' . $rec_ID;
            $to_records = mysql__select_list2($mysqli, $from_query);
        }

        if ($direction == null || $direction == 'linkedfrom') {
            $to_query = 'SELECT rl_SourceID as linkID FROM recLinks ' . str_replace('linkID', 'rl_SourceID', $where) . ' rl_RelationID IS NULL AND rl_TargetID=' . $rec_ID;
            $from_records = mysql__select_list2($mysqli, $to_query);
        }

        return array('linkedto' => $to_records, 'linkedfrom' => $from_records);
    }

    /**
     * Converts a record array into an array that can be assigned to a Smarty variable.
     *
     * @param array $rec The record array to convert.
     * @return array|null The converted record array or null if the record is invalid.
     */
    private function getRecordForSmarty($rec)
    {
        if (!$rec) {
            return null;
        }

        $recordID = $rec['rec_ID'];

        if (@$this->recordsCache[$recordID]) {
            return $this->recordsCache[$recordID]; //form cache
        }

        $record = array();
        $recTypeID = null;
        $lang = $this->getSysInfo('lang');

        foreach ($rec as $key => $value) {
            if (strpos($key, "rec_") === 0) {
                $this->processRecordField($record, $key, $value, $recTypeID);
            } elseif ($key == "details") {
                $this->processRecordDetails($record, $value, $recTypeID, $recordID, $lang);
            }
        }

        if (count($this->recordsCache) > 2500) {
            $this->recordsCache = array(); // Reset cache if too many records are loaded
        }

        $this->recordsCache[$recordID] = $record;
        return $record;
    }

    private function processRecordField(&$record, $key, $value, &$recTypeID)
    {
        $record['rec' . substr($key, 4)] = $value;

        if ($key == 'rec_RecTypeID') {
            $recTypeID = $value;
            $record['recTypeID'] = $recTypeID;
            $record['recTypeName'] = $this->rtyNames[$recTypeID];
        } elseif ($key == 'rec_Tags') {
            $record['rec_Tags'] = $value;
        } elseif ($key == 'rec_ID') {
            $record['recWootText'] = $this->getWootText($value);
        }
    }

    private function processRecordDetails(&$record, $details, $recTypeID, $recordID, $lang)
    {
        foreach ($details as $dtKey => $dtValue) {
            $dt = $this->getDetailForSmarty($dtKey, $dtValue, $recTypeID, $recordID, $lang);
            if ($dt != null) {
                $record = array_merge($record, $dt);
            }
        }
    }

    /**
    *
    */
    private function addTermValue($res, $val){

        if($val){
            if(strlen($res)>0) {$res = $res.", ";}
            $res = $res.$val;
        }
        return $res;
    }

    /**
    * convert details to array to be assigned to smarty variable
    * $dtKey - detailtype ID, if <1 this dummy relationship detail
    */
    private function getDetailForSmarty($dtKey, $dtValue, $recTypeID, $recID, $lang){

        $dtname = null;

        if($dtKey<1){
            $dtname = 'Relationship';
            $detailType =  'relmarker';
        }elseif (@$this->dtyTypes[$dtKey]) {
            $dtname = 'f'.$dtKey;
            $detailType =  $this->dtyTypes[ $dtKey ];
        }else{
            return null;//name is not defined
        }

        if(!is_array($dtValue)){
                return array( $dtname=>$dtValue );
        }

        //complex type - need more analize

        $res = null;

        switch ($detailType) {
            case 'enum':
            case 'relationtype':

                $res = $this->getDetailForEnum($dtname, $dtValue, $lang);
                break;

            case 'date':

                $res = "";
                $origvalues = array();
                foreach ($dtValue as $value){
                    if(strlen($res)>0) {$res = $res.", ";}
                    $res = $res.\Temporal::toHumanReadable($value, true, 0, '|', 'native');
                    array_push($origvalues, $value);
                }
                if(strlen($res)==0){ //no valid terms
                    $res = null;
                }else{
                    $res = array( $dtname=>$res, $dtname."_originalvalue"=>$origvalues);
                }
                break;

            case 'file':
                //get url for file download

                //if image - special case

                $res = array();//list of urls
                $origvalues = array();

                foreach ($dtValue as $value){

                    $external_url = @$value['file']['ulf_ExternalFileReference'];
                    if ($external_url && strpos($external_url,'http://')!==0) {
                        array_push($res, $external_url);//external

                    }elseif (@$value['file']['ulf_ObfuscatedFileID']) {
                        //local
                        array_push($res, HEURIST_BASE_URL."?db=".$this->system->dbname()
                                ."&file=".$value['file']['ulf_ObfuscatedFileID']);
                    }
                    //keep reference to record id
                    $value['file']['rec_ID'] = $recID;

                    //original value keeps the whole 'file' array
                    array_push($origvalues, $value['file']);
                }
                if(empty($res)){
                    $res = null;
                }else{
                    $res = array($dtname=>implode(', ',$res), $dtname."_originalvalue"=>$origvalues);
                }

                break;

            case 'geo':

                $res = "";
                $arres = array();
                foreach ($dtValue as $key => $value){

                    //original value keeps whole geo array
                    $dtname2 = $dtname."_originalvalue";
                    $value['geo']['recid'] = $recID;
                    $arres = array_merge($arres, array($dtname2=>$value['geo']));

                    $geom = \geoPHP::load($value['geo']['wkt'], 'wkt');
                    if(!$geom->isEmpty()){
                        $geojson_adapter = new \GeoJSON();
                        $json = $geojson_adapter->write($geom, true);
                    }
                    if(!$json) {$json = array();}
                    $dtname2 = $dtname."_geojson";
                    $arres = array_merge($arres, array($dtname2=>$json));


                    $res = $value['geo']['wkt'];
                    break; //only one geo location at the moment
                }

                if(strlen($res)==0){
                    $res = null;
                }else{
                    $res = array_merge($arres, array($dtname=>$res));
                }

                break;

            case 'separator':
            case 'calculated':
            case 'fieldsetmarker':
                break;

            case 'relmarker': // NOT USED
                break;
            case 'resource': // link to another record type

                $res = array();
                if(empty($dtValue)){
                   break;
                }

                foreach ($dtValue as $key => $value){
                    $recordID = $value['id'];
                    array_push($res, $recordID);
                }

                $res = array( $dtname =>$res[0], $dtname."s" =>$res );

                break;

            default:
                // repeated basic detail types
                $res = "";
                $origvalues = array();
                foreach ($dtValue as $key => $value){
                    if(strlen($res)>0) {$res = $res.", ";}
                    $res = $res.$value;
                    array_push($origvalues, $value);
                }

                if(count($dtValue)>1 && ($detailType=='freetext' || $detailType=='blocktext')){
                    $translated_value = getCurrentTranslation($dtValue, $lang);
                    if($translated_value!=null){
                        $res = $translated_value;
                    }
                }

                if(strlen($res)==0){ //no valid value
                    $res = null;
                }else{
                    $res = array( $dtname=>$res, $dtname."s" =>$origvalues, $dtname."_originalvalue"=>$origvalues);
                }


        }//end switch

        return $res;

    }

    /**
    * Converts enum field value for smarty
    *
    * @param mixed $dtname
    * @param mixed $dtValue
    * @param mixed $lang
    */
    private function getDetailForEnum($dtname, $dtValue, $lang){

        if($this->dtTerms==null){
            $this->dtTerms = dbs_GetTerms($this->system);
            $this->dbsTerms = new \DbsTerms($this->system, $this->dtTerms);
        }

        $fi = $this->dtTerms['fieldNamesToIndex'];

        $res_id = "";
        $res_cid = "";
        $res_code = "";
        $res_label = "";
        $res_label_full = '';
        $res_desc = "";
        $res = array();


        foreach ($dtValue as $value){

            $term = $this->dbsTerms->getTerm($value);
            if($term){

                //IJ wants to show terms for all parents
                $term_full = $this->dbsTerms->getTermLabel($value, true);

                $term_label = $this->getTranslation('trm', $value, 'trm_Label', $lang);
                $term_desc = $this->getTranslation('trm', $value, 'trm_Description', $lang);

                $res_id = $this->addTermValue($res_id, $value);
                $res_cid = $this->addTermValue($res_cid, $term[ $fi['trm_ConceptID'] ]);
                $res_code = $this->addTermValue($res_code, $term[ $fi['trm_Code'] ]);

                $res_label_full = $this->addTermValue($res_label_full, $term_full);
                $res_label = $this->addTermValue($res_label, $term_label);//$term[ $fi['trm_Label'] ]);
                $res_desc = $this->addTermValue($res_desc, $term_desc);//$term[ $fi['trm_Description'] ]);

                //NOTE id and label are for backward
                //original value
                array_push($res, array("id"=>$value, "internalid"=>$value,
                    "code"=>$term[ $fi['trm_Code'] ],
                    "label"=>$term_label,
                    "term"=>$term_full,
                    "conceptid"=>$term[ $fi['trm_ConceptID'] ],
                    "desc"=>$term_desc
                ));
            }
        }

        if(!empty($res)){
            $res = array( $dtname =>$res[0], $dtname."s" =>$res );
        }

        return $res;
    }

    /**
     * Retrieves Woot text associated with a record.
     *
     * @param int $recID The record ID.
     * @return string The Woot text.
     */
    public function getWootText($recID)
    {
        // Woot is disabled in this version.
        return '';

/* woot is disabled in this version
        $woot = loadWoot(array("title"=>"record:".$recID));
        if(@$woot["success"])
        {
            if(@$woot["woot"]){

                $chunks = $woot["woot"]["chunks"];
                $cnt = count($chunks);

                for ($i = 0; $i < $cnt; $i++) {
                    $chunk = $chunks[$i];
                    if(@$chunk["text"]){
                        $res = $res.$chunk["text"];
                    }
                }//for
            }
        }elseif (@$woot["errorType"]) {
            $res = "WootText: ".$woot["errorType"];
        }
*/
    }

    /**
     * Returns the record IDs for a given query.
     *
     * @param string|array $query The Heurist query or JSON object.
     * @param mixed|null $current_rec The current record ID or array (optional).
     * @return array|null An array of record IDs or null if none are found.
     */
    public function getRecords($query, $current_rec = null)
    {
        $rec_ID = is_array($current_rec) && $current_rec['recID'] ? $current_rec['recID'] : $current_rec;

        if(is_array($query)){
            $query = json_encode($query);
        }


        if ($rec_ID > 0 && strpos($query, '[ID]') !== false) {
            $query = str_replace('[ID]', strval($rec_ID), $query);
        } elseif (strpos($query, '[ID]') !== false) {
            return null;
        }

        $params = array('detail' => 'ids', 'q' => $query, 'needall' => 1);
        $response = recordSearch($this->system, $params);

        if (@$response['status'] == HEURIST_OK) {
            return $response['data']['records'];
        } else {
            return null;
        }
    }

    /**
     * Returns aggregation values for a set of records or query.
     *
     * @param array $functions An array of pairs (field_id, avg|count|sum) specifying the aggregation functions.
     * @param string|array $query_or_ids Heurist query or record IDs.
     * @param mixed|null $current_rec The current record ID or array (optional).
     * @return array|mixed|null Aggregation result, or null if none are found.
     */
    public function getRecordsAggr($functions, $query_or_ids, $current_rec = null)
    {
        $ids = prepareIds($query_or_ids);
        if (empty($ids)) {
            $ids = $this->getRecords($query_or_ids, $current_rec);
        }

        //calculate aggregation values
        $select = array();
        $from = array('Records');
        $result = array();
        $idx = 0;

        if(is_array($functions) && count($functions)==2 && !is_array($functions[0])){
            $functions = array($functions);
        }

        foreach ($functions as $func) {
            $dty_ID = $func[0];
            $func_type = $func[1];

            if (in_array($func_type, ['avg', 'sum', 'count'])) {
                if ($dty_ID > 0) {
                    array_push($select, $func_type . '(d' . $idx . '.dtl_Value)');
                    array_push($from, 'JOIN recDetails d' . $idx . ' ON rec_ID=d' . $idx . '.dtl_RecID AND d' . $idx . '.dtl_DetailTypeID=' . $dty_ID);
                } else {
                    array_push($select, 'count(rec_ID)');
                }
                array_push($result, array($dty_ID, $func_type, 0));
                $idx++;
            }
        }

        if (empty($select) || empty($ids)) {
            return null;
        }

        $query = 'SELECT ' . implode(',', $select) . ' FROM ' . implode(' ', $from) . ' WHERE rec_ID IN (' . implode(',', $ids) . ')';
        $res = mysql__select_row($this->system->getMysqli(), $query);

        if ($res == null) {
               return null;
        }

        if (count($res) == 1) {
            return $res[0];
        }

        foreach ($res as $idx => $val) {
            $result[$idx][2] = $val;
        }
        return $result;

    }

    /**
     * Returns a translated value for a given entity and field.
     *
     * @param string $entity The entity type ('trm', 'rty', 'dty').
     * @param mixed $ids The entity IDs.
     * @param string|null $field The field to translate (default is 'Label').
     * @param string|null $language_code The language code for the translation (optional).
     * @return array|string The translated value(s).
     */
    public function getTranslation($entity, $ids, $field = null, $language_code = null)
    {
        if ($language_code == null) {
            $language_code = $this->getSysInfo('lang');
        }
        $language_code = getLangCode3($language_code);
        $rtn = array();
        $def_values = array();
        $id_clause = '';

        if (!is_array($ids)) {
            $ids = explode(',', $ids);
        }

        if (!array_key_exists($language_code, $this->translations[$entity])) {
            $this->translations[$entity][$language_code] = array();
        }

        $cache = $this->translations[$entity][$language_code];

        if ($entity == 'trm') {
            $field = (strpos(strtolower($field), 'desc') === false) ? 'trm_Label' : 'trm_Description';
        } else {
            $field = $entity . '_Name';
        }

        //take translation from cache
        foreach ($ids as $idx => $id) {
            if (array_key_exists($id, $cache) && @$cache[$id][$field]) {
                $rtn[$id] = $cache[$id][$field];
                unset($ids[$idx]);
            }
        }

        if (empty($ids)) {
            return count($rtn) == 1 ? array_shift($rtn) : $rtn;
        }

        $ids = prepareIds($ids);
        $id_clause = predicateId('trn_Code', $ids, SQL_AND);

        if ($id_clause != '') {

            if ($entity == 'trm') {
                $def_values = $this->fillTermNames($ids, $field);
            }

            $mysqli = $this->system->getMysqli();
            $query = "SELECT trn_Code, trn_Translation FROM defTranslations WHERE trn_Source = '$field' AND trn_LanguageCode = '$language_code' $id_clause";
            $res = mysql__select_assoc2($mysqli, $query);

            foreach ($ids as $id) {
                $rtn[$id] = $res[$id] ?? $def_values[$id] ?? '';
                $cache[$id] = array($field => $rtn[$id]);
            }
        }

        $this->translations[$entity][$language_code] = $cache;
        return count($rtn) == 1 ? array_shift($rtn) : $rtn;
    }

    /**
    * fill array with term labels for given set of term ids
    *
    * @param mixed $ids
    */
    private function fillTermNames($ids, $field){

        $def_values = array();

        if ($this->dtTerms == null) {
            $this->dtTerms = dbs_GetTerms($this->system);
        }
        if ($this->dbsTerms == null) {
            $this->dbsTerms = new \DbsTerms($this->system, $this->dtTerms);
        }

        foreach ($ids as $trm_id) {
            $term = $this->dbsTerms->getTerm($trm_id);
            $def_values[$trm_id] = $term ? $term[$this->dtTerms['fieldNamesToIndex'][$field]] : '';
        }
        return $def_values;
    }

    /**
     * Returns the specific field for uploaded media information.
     *
     * @param mixed $file_details The file details array.
     * @param string $field The field to retrieve ('name', 'desc', 'cap', etc.).
     * @return string|array The value(s) for the requested field.
     */
    public function getFileField($file_details, $field = 'name')
    {
        $mysqli = $this->system->getMysqli();
        $fields_map = [
            'desc' => 'ulf_Description',
            'description' => 'ulf_Description',
            'cap' => 'ulf_Caption',
            'caption' => 'ulf_Caption',
            'rights' => 'ulf_Copyright',
            'copyright' => 'ulf_Copyright',
            'owner' => 'ulf_Copyowner',
            'copyowner' => 'ulf_Copyowner',
            'type' => 'ulf_MimeExt',
            'ext' => 'ulf_MimeExt',
            'extension' => 'ulf_MimeExt',
            'filename' => 'ulf_OrigFileName',
            'name' => 'ulf_OrigFileName'
        ];
        $field = $fields_map[$field] ?? '';

        if (empty($field)) {
            return $file_details;
        }

        $results = [];

        if (is_array($file_details)){
            foreach ($file_details as $file_dtls) {
                $results[] = $file_dtls[$field] ?? '';
            }
            return count($results) == 1 ? $results[0] : $results;
        }

        $files = explode(',', $file_details);
        foreach ($files as $f_url) {
            $url_params = [];
            parse_str(parse_url(trim($f_url), PHP_URL_QUERY), $url_params);
            $ulf_ObfuscatedFileID = $url_params['file'] ?? null;
            if ($ulf_ObfuscatedFileID && preg_match('/^[a-z0-9]+$/', $ulf_ObfuscatedFileID)) {
                $result = mysql__select_value($mysqli, "SELECT $field FROM recUploadedFiles WHERE ulf_ObfuscatedFileID = '$ulf_ObfuscatedFileID'");
                $results[] = $result ?: '';
            }
        }

        return count($results) == 1 ? $results[0] : $results;
    }

}
