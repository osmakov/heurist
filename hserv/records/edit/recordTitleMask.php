<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* recordTitleMask.php
*
* static class with
* Three MAIN methods
*
*   check($mask, $rt) => returns an error string if there is a fault in the given mask for the given record type
*   fill($mask, $rec_id, $rt) => returns the filled-in title mask for this record entry
*   execute($mask, $rt, $mode, $rec_id=null) => converts titlemask to coded, humanreadable or fill mask with values
*
*
* Note that title masks have been updated (Artem Osmakov late 2013) to remove the awkward storage of two versions - 'canonical' and human readable.
* They are now read and used as internal code values (the old 'canonical' form), decoded to human readable for editing,
* and then recoded back to internal codes for storage, as per original design.
*
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson.heurist@gmail.com>
* @author      Stephen White
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.6
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  CommonPHP
*/
use hserv\utilities\USystem;

require_once dirname(__FILE__).'/../../utilities/Temporal.php';


define('ERROR_REP_WARN', 0);// returns general message that titlemask is invalid - default
define('ERROR_REP_MSG', 1);// returns detailed error message
define('ERROR_REP_SILENT', 2);// returns empty string

define('TITLEMASK_ERROR_MSG', 'Invalid title mask: please define the title mask in record structure editor');
define('TITLEMASK_ERROR_MSG2', 'Error in title mask. Please look for syntax errors or special characters. '
.'If the problem is not clear, please rebuild the mask one field at a time and let the Heurist team know which field causes the problem so we can fix it');

define('TITLEMASK_EMPTY_MSG', '**** No data in title fields for this record ****');

//
// static class
//
class TitleMask {

     /**
     * Construct won't be called inside this class and is uncallable from
     * the outside. This prevents instantiating this class.
     * This is by purpose, because we want a static class.
     */
    private function __construct() {}
    private static $system = null;
    private static $mysqli = null;
    private static $db_regid = 0;
    private static $initialized = false;

    private static $fields_correspondence = null;
    private static $rdt = null;  //detail types array indexed by id,name and concept code
    private static $rdr = null;  //record detail types
    //private static $rectypes = null;
    private static $records = null;

    private static $provided_mask = null; // provided title mask - for checking/testing

    //private static $DT_PARENT_ENTITY = 0;

    public static function initialize()
    {

        if (self::$initialized) {return;}

        global $system;
        self::$system = $system;
        self::$mysqli = $system->get_mysqli();
        self::$db_regid = $system->get_system('sys_dbRegisteredID');
        self::$initialized = true;

        $system->defineConstant('DT_PARENT_ENTITY');
    }

    public static function set_fields_correspondence($fields_correspondence){
        self::$fields_correspondence = $fields_correspondence;
    }

/**
* Check that the given title mask is well-formed for the given reference type
* Returns an error string describing any faults in the mask.
*
* @param mixed $mask
* @param mixed $rt
* @param mixed $checkempty
*/
 public static function check($mask, $rt, $checkempty) {

    self::initialize();
    // \[([^]]+)\]  - works in php     \[([^\]]+)\] - is js

    if (! preg_match_all('/\\[\\[|\\]\\]|\\[\\s*([^]]+)\\s*\\]/', $mask, $matches))
    {
        // no substitutions to make
        return $checkempty?'Title mask must have at least one data field ( in [ ] ) to replace':'';
    }

    self::$provided_mask = $mask;

    $res = self::execute($mask, $rt, 1, null, ERROR_REP_MSG);
    if(is_array($res)){
        return $res[0];// mask is invalid - this is error message
    }else{
        return "";
    }
}

/**
* Execute titlemask - replace tags with values
*
* @param mixed $mask
* @param mixed $rec_id
* @param mixed $rt
*/
public static function fill($rec_id, $mask=null){

    self::initialize();

    $rec_value = self::__get_record_value($rec_id, true);
    if($rec_value){
        if($mask==null){
            $mask = $rec_value['rty_TitleMask'];
        }
        $rt = $rec_value['rec_RecTypeID'];
        return self::execute($mask, $rt, 0, $rec_id, ERROR_REP_WARN);
    }else{
        return "Title mask not generated. Record ".$rec_id." not found";
    }
}

/*
* Converts titlemask to coded, human readable or fill mask with values
* In case of invalid titlemask it returns either general warning, error message or empty string (see $rep_mode)
*
* @param mixed $mask - titlemask
* @param mixed $rt - record type
* @param mixed $mode - 0 get value from coded, 1 to coded, 2 - to human readable, 3 get value from human readable
* @param mixed $rec_id - record id for value mode
* @param mixed $rep_mode - output in case failure: 0 - general message(ERROR_REP_WARN), 1- detailed message, 2 - empty string (ERROR_REP_SILENT)
* @return string
*/
public static function execute($mask, $rt, $mode, $rec_id=null, $rep_mode=ERROR_REP_WARN) {

    self::initialize();

    if(self::$fields_correspondence!=null){
        self::$rdr = null;
    }

    if($rec_id){
        self::__get_record_value($rec_id, true);//keep recvalue in static
    }

    if (!$mask) {
        $ret = ($rep_mode!=ERROR_REP_SILENT)?"Title mask is not defined": ($mode==0?self::__get_forempty($rec_id, $rt):"");
        return $ret;
    }

    if($mode==3){
        //get value from human readable
        //execute($mask, $rt, $mode, $rec_id=null, $rep_mode=ERROR_REP_WARN)
        $res = self::execute($mask, $rt, 1, $rec_id, ERROR_REP_MSG);
        if (is_array($res)) {
            return $res[0];
        }else{
            return self::execute($res, $rt, 0, $rec_id, ERROR_REP_MSG);
        }
    }


    //find inside brackets
    if (! preg_match_all('/\s*\\[\\[|\s*\\]\\]|(\\s*(\\[\\s*([^]]+)\\s*\\]))/s', $mask, $matches)){
        return $mask;    // nothing to do -- no substitutions
    }

    $replacements = array();
    $len = count($matches[1]);
    $fields_err = 0;
    $fields_blank = 0;
    for ($i=0; $i < $len; ++$i) {
        /* $matches[3][$i] contains the field name as supplied (the string that we look up),
        * $matches[2][$i] contains the field plus surrounding whitespace and containing brackets
        *        (this is what we replace if there is a substitution)
        * $matches[1][$i] contains the field plus surrounding whitespace and containing brackets and LEADING WHITESPACE
        *        (this is what we replace with an empty string if there is no substitution value available)
        */

        if(!trim($matches[3][$i])) {continue;} //empty []

        $value = self::__fill_field($matches[3][$i], $rt, $mode, $rec_id);

        if(is_array($value)){
            //ERROR
            if($rep_mode==ERROR_REP_WARN){
                return TITLEMASK_ERROR_MSG;
            }elseif($rep_mode==ERROR_REP_MSG){
                return $value;
            }else{
                $replacements[$matches[1][$i]] = "";
                $fields_err++;
            }
        }elseif (null==$value || trim($value)==""){
            $replacements[$matches[1][$i]] = "";
            $fields_blank++;

        }else{
            if($mode==0){ //value
                $replacements[$matches[2][$i]] = $value;
            }else{ //coded or human readable
                $replacements[$matches[2][$i]] = "[$value]";
            }
        }
    }

    if($mode==0){
        if($fields_err==$len){
            return self::__get_forempty($rec_id, $rt);
        }
        $replacements['[['] = '[';
        $replacements[']]'] = ']';

        // Check if there are any conditional parts in the title mask
        /* Two versions:
         * Old format: Checks if the preceeding field has a value, if it has a value append it to the first section; otherwise print the second section or remove completely
         * New format: The conditional field/s are placed with the output string, e.g. {\Full name: [Given name] [Last name] \...},
         *              all fields needs to have a value to print out that string, a section without a field (except the first section) will be printed out if reached
         */
        if(preg_match_all("/(?:\[[^\[\]]+?\])?\s?{\d*\s?(?:\\\\[^\\\\\}]*\s?)*}/", $mask, $conditions_mask)){ // get all conditional strings

            foreach ($conditions_mask[0] as $key => $cond_str) {

                $cond_field = array();
                $cond_mask = array();
                $cond_replace = null;
                $str_maxlen = 0;

                // retrieve conditional sections
                preg_match("/{\d*\s?(?:\\\\[^\\\\]*\s?)*}/", $cond_str, $cond_mask);

                $cond_mask[0] = trim($cond_mask[0], ' {}');// remove curly brackets

                $cond_parts = mb_split("\\\\", $cond_mask[0]);
                if(is_numeric(trim($cond_parts[0])) || empty($cond_parts[0])){
                    $str_maxlen = intval($cond_parts[0]);
                    array_shift($cond_parts);
                }

                if(strpos($cond_parts[0], '[') !== false && strpos($cond_parts[0], ']') !== false){ // new method

                    foreach ($cond_parts as $cond_part) { // process each section, checking for each field value
                        preg_match_all("/(?:\[[^\[\]]+?\])+/", $cond_part, $cond_fields);

                        $is_valid = true;
                        foreach ($cond_fields[0] as $cond_field) {
                            if(!array_key_exists($cond_field, $replacements)){
                                $is_valid = false;
                                break;
                            }

                            $new_str = ($str_maxlen > 0 && mb_strlen($replacements[$cond_field]) > $str_maxlen ? mb_substr($replacements[$cond_field], 0, $str_maxlen) . '...' : $replacements[$cond_field]);
                            $cond_part = mb_eregi_replace(preg_quote($cond_str, "/"), $new_str, $cond_part);
                        }

                        if($is_valid){
                            $cond_replace = $cond_part;
                            break;
                        }
                    }

                    if($cond_replace === null){ // default, replace with empty
                        $cond_replace = '';
                    }
                }elseif(count($cond_parts) == 1 || count($cond_parts) == 2){ // original method

                    // retrieve proceeding field
                    preg_match("/\[[^\[\]]+?\]/", $cond_str, $cond_field);
                    $new_str = array_key_exists($cond_field[0], $replacements) ? $replacements[$cond_field[0]] : '';

                    if(!empty($new_str)){
                        $cond_replace = $cond_parts[0] . ' ' . ($str_maxlen > 0 && mb_strlen($new_str) > $str_maxlen ? mb_substr($new_str, 0, $str_maxlen) . '...' : $new_str);
                    }elseif(empty($cond_parts[1])){
                        $cond_replace = '';
                    }else{
                        $cond_replace = $cond_parts[1];
                    }
                }elseif(empty($cond_parts) && $str_maxlen > 0){

                    // retrieve proceeding field
                    preg_match("/\[[^\[\]]+?\]/", $cond_str, $cond_field);
                    $new_str = array_key_exists($cond_field[0], $replacements) ? $replacements[$cond_field[0]] : '';

                    $cond_replace = !empty($new_str) && mb_strlen($new_str) > $str_maxlen ? mb_substr($new_str, 0, $str_maxlen) . '...' : $new_str;
                }

                if($cond_replace !== null){ // replace part
                    $mask = mb_eregi_replace(preg_quote($cond_str, "/"), $cond_replace, $mask);
                }
            }
        }
    }

    $title = array_str_replace(array_keys($replacements), array_values($replacements), $mask);

    if($mode==0){  //fill the mask with values


        if($fields_blank==$len && $rec_id){ //If all the title mask fields are blank
            $title =  "Record ID $rec_id - no data has been entered in the fields used to construct the title";
        }

        /* Clean up miscellaneous stray punctuation &c. */
        if (! preg_match('/^\\s*[0-9a-z]+:\\S+\\s*$/i', $title)) {    // not a URI

            $puncts = '-:;,.@#|+=&(){}';// These are stripped from begining and end of title
            $puncts2 = '-:;,@#|+=&';// same less period

            $regex_ = '/\\s]*(.*?)[';
            $regex_2 = '!\\([';

            $title = preg_replace('!^['.$puncts.$regex_.$puncts2.'/\\s]*$!s', '\\1', $title);// remove leading and trailing punctuation
            $title = preg_replace($regex_2.$puncts.'/\\s]+\\)!s', '', $title);// remove brackets containing only punctuation
            $title = preg_replace($regex_2.$puncts.$regex_.$puncts2.'/\\s]*\\)!s', '(\\1)', $title);// remove leading and trailing punctuation within brackets
            $title = preg_replace($regex_2.$puncts.'/\\s]*\\)|\\[['.$puncts.'/\\s]*\\]!s', '', $title);// remove brackets containing only punctuation
            $title = preg_replace('!^['.$puncts.$regex_.$puncts2.'/\\s]*$!s', '\\1', $title);// remove leading and trailing punctuation
            $title = preg_replace('!,\\s*,+!s', ',', $title);// replace commas with nothing between them, e.g. "Hello, , World" => "Hello, World"
            $title = preg_replace('!\\s+,!s', ',', $title);// remove leading spaces before comma, e.g. "Hello    , World" => "Hello, World"

        }
        $title = trim(preg_replace('!  +!s', ' ', $title));//remove double spaces

        if($title==""){

            if($rep_mode==ERROR_REP_SILENT){
                $title = self::__get_forempty($rec_id, $rt);
            }elseif($rep_mode==ERROR_REP_MSG){
                return array(TITLEMASK_EMPTY_MSG);
            }else{
                return TITLEMASK_EMPTY_MSG;
            }
        }
    }

    return $title;
}

//-------------- private methods -----------------

/**
* If the title mask is blank or contains no valid fields, build the title using the values of the first three
* data fields (excluding memo fields) truncated to 40 characters if longer, separated with pipe symbols
*/
private static function __get_forempty($rec_id, $rt){

    $rdr = self::__get_rec_detail_types($rt);
    //$rec_values = self::__get_record_value($rec_id);

    $allowed = array('freetext', 'enum', 'float', 'date', 'relmarker', 'integer', 'year', 'boolean');
    $cnt = 0;
    $title = array();
    foreach($rdr as $dt_id => $detail){
        if( is_numeric($dt_id) && in_array($detail['dty_Type'], $allowed) && $detail['rst_RequirementType']!='forbidden'){
            $val = self::__get_field_value($dt_id, $rt, 0, $rec_id);
            $val = trim(mb_substr($val,0,40));
            if($val){
                array_push($title, $val);
                $cnt++;
                if($cnt>2) {break;}
            }
        }
    }
    $title = implode("|", $title);
    if(!$title){
        $title =  "Record ID $rec_id - no data has been entered in the fields used to construct the title";
    }
    return $title;
}


/*
* Returns ALL field types definitions and keeps it into static array
*/
private static function __get_detail_types() {

    if (! self::$rdt) {
        self::$rdt = array();

        $res = self::$mysqli->query('select dty_ID, lower(dty_Name) as dty_Name, dty_Name as originalName, dty_Type, '
            .' dty_PtrTargetRectypeIDs as rst_PtrFilteredIDs, dty_Name as dty_NameOrig, '
            .' dty_OriginatingDBID, dty_IDInOriginatingDB from defDetailTypes');

        if ($res){
            while ($row = $res->fetch_assoc()) {

                if (is_numeric($row['dty_OriginatingDBID']) && $row['dty_OriginatingDBID']>0 &&
                is_numeric($row['dty_IDInOriginatingDB']) && $row['dty_IDInOriginatingDB']>0) {
                    $dt_cc = "" . $row['dty_OriginatingDBID'] . "-" . $row['dty_IDInOriginatingDB'];
                } elseif (self::$db_regid>0) {
                    $dt_cc = "" . self::$db_regid . "-" . $row['dty_ID'];
                } else {
                    $dt_cc = $row['dty_ID'];
                }

                $row['dty_ConceptCode'] = $dt_cc;

                self::$rdt[$row['dty_ID']] = $row;
                self::$rdt[$row['dty_Name']] = $row;
                self::$rdt[$dt_cc] = $row;
            }
            $res->close();
        }
    }

    return self::$rdt;
}

/*
* Fill record type structure
* keeps it in static array
* this array for each given record type
*/
private static function __get_rec_detail_types($rt) {

    if (!self::$rdr) {
        self::$rdr = array();
    }

    if(!@self::$rdr[$rt]){

        //dty_Name as dty_NameOrig,

        $query ='select rst_RecTypeID, '
        .' lower(rst_DisplayName) as rst_DisplayName, rst_DisplayName as originalName, '   //lower(dty_Name) as dty_Name,
        .' dty_Type, if(rst_PtrFilteredIDs,rst_PtrFilteredIDs, dty_PtrTargetRectypeIDs) as rst_PtrFilteredIDs,'
        .' dty_OriginatingDBID, dty_IDInOriginatingDB, dty_ID, rst_RequirementType '
        .' from defRecStructure left join defDetailTypes on rst_DetailTypeID=dty_ID '
        .SQL_WHERE//since 2017-11-25 rst_RequirementType in ("required", "recommended", "optional") and '
        .' rst_RecTypeID='.intval($rt)
        .' order by rst_DisplayOrder';

        $res = self::$mysqli->query($query);

        if($res){
            self::$rdr[$rt] = array();
            while ($row = $res->fetch_assoc()) {

                if (is_numeric($row['dty_OriginatingDBID']) && $row['dty_OriginatingDBID']>0 &&
                is_numeric($row['dty_IDInOriginatingDB']) && $row['dty_IDInOriginatingDB']>0) {

                    $dt_cc = "" . $row['dty_OriginatingDBID'] . "-" . $row['dty_IDInOriginatingDB'];
                } elseif (self::$db_regid>0) {
                    $dt_cc = "" . self::$db_regid . "-" . $row['dty_ID'];
                } else {
                    $dt_cc = $row['dty_ID'];
                }

                $row['dty_ConceptCode'] = $dt_cc;

                $fld_name_idx = mb_eregi_replace("/\s{2,}/", " ", $row['rst_DisplayName']);// remove double spacing from field name used for indexing

                //keep 3 indexes by id, name and concept code
                self::$rdr[$rt][$row['dty_ID']] = $row;
                self::$rdr[$rt][$fld_name_idx] = $row;
                self::$rdr[$rt][$dt_cc] = $row;
            }
            $res->close();
        }
    }
    return self::$rdr[$rt];

}

/*
* Returns array of related record ids for given record and relmarker field
*/
private static function __get_related_record_ids($rec_id, $dty_ID) {

    //1. find all relation types
    $vocab_id = mysql__select_value(self::$mysqli,
        'SELECT dty_JsonTermIDTree FROM defDetailTypes WHERE dty_ID='.$dty_ID);

    $reltypes = null;
    if($vocab_id>0){
        $reltypes = getTermChildrenAll(self::$mysqli, $vocab_id, true);
        if(count($reltypes)==1){
            $reltypes = '='.$reltypes[0];
        }else{
            $reltypes = ' IN ('.implode(',',$reltypes).')';
        }
    }
    //2. find rectype constraints
    $constr_ids = mysql__select_value(self::$mysqli,
        'SELECT dty_PtrTargetRectypeIDs FROM defDetailTypes WHERE dty_ID='.$dty_ID);
    $constr_ids = prepareIds($constr_ids);
    if(count($constr_ids)==1){
        $constr_ids = '='.$constr_ids[0];
    }elseif(count($constr_ids)>1){
        $constr_ids = ' IN ('.implode(',',$constr_ids).')';
    }else{
        $constr_ids = null;
    }

    //direct
    $query = 'SELECT rl_TargetID  as record_ID '
        .'FROM recLinks, Records WHERE rl_SourceID='.$rec_id
        .' AND rl_TargetID=rec_ID AND rec_FlagTemporary=0 ';
    if($reltypes){
        $query = $query.' AND rl_RelationTypeID'.$reltypes;
    }
    if($constr_ids){
        $query = $query.' AND rec_RecTypeID'.$constr_ids;
    }

    //reverse
    $query = $query.' UNION '
        .'SELECT rl_SourceID as record_ID '
        .'FROM recLinks, Records WHERE rl_TargetID='.$rec_id
        .' AND rl_SourceID=rec_ID AND rec_FlagTemporary=0 ';
    if($reltypes){
        $query = $query.' AND rl_RelationTypeID'.$reltypes;
    }
    if($constr_ids){
        $query = $query.' AND rec_RecTypeID'.$constr_ids;
    }

    $record_ids = mysql__select_list2(self::$mysqli, $query);

    return $record_ids;
}

/*
* load the record values (except forbidden fields)
*
* @param mixed $rec_id
*/
private static function __get_record_value($rec_id, $reset=false) {

/*
    $memory_limit = USystem::getConfigBytes('memory_limit');
    $mem_used = memory_get_usage();
    if($mem_used>$memory_limit-104857600){ //100M

    }
*/
    //if not reset it leads to memory exhaustion
    //$reset = true;
    if ($reset || !is_array(self::$records) || count(self::$records)>1000) {
        self::$records = array();
    }

    if(@self::$records[$rec_id]){
        return self::$records[$rec_id];
    }

        $ret = null;

        $query = 'SELECT rec_ID, rec_Title, rec_Modified, rec_RecTypeID, rty_Name, rty_TitleMask '
                    .'FROM Records, defRecTypes where rec_RecTypeID=rty_ID and rec_ID='.intval($rec_id);
        $res = self::$mysqli->query($query);
        if($res){
            $row = $res->fetch_assoc();
            if($row){

                $ret = $row;
                $ret['rec_Details'] = array();

                //trim(substr(dtl_Value,0,300)) as
                $query = 'SELECT dtl_DetailTypeID, dtl_Value, dtl_UploadedFileID, rst_RequirementType '
                .'FROM recDetails LEFT JOIN defRecStructure '
                .'ON rst_RecTypeID='.intval($ret['rec_RecTypeID'])
                   .' AND rst_DetailTypeID=dtl_DetailTypeID '
                   .' WHERE dtl_RecID='.intval($rec_id)." ORDER BY dtl_DetailTypeID, dtl_ID";
                $res2 = self::$mysqli->query($query);
                while ($row = $res2->fetch_assoc()){
                    if($row['rst_RequirementType']!='forbidden'){
                        array_push($ret['rec_Details'], $row);
                    }
                }
                $res2->close();
            }
            $res->close();
        }

        self::$records[$rec_id] = $ret;

    return self::$records[$rec_id];
}

/*
* find and return value for enumeration field
*
* @param mixed $enum_id
* @param mixed $enum_param_name
*/
private static function __get_enum_value($enum_id, $enum_param_name)
{

    if($enum_param_name==null || strcasecmp($enum_param_name,'term')==0){
        $enum_param_name = "label";
    }elseif(strcasecmp($enum_param_name,'internalid')==0){
        $enum_param_name = "id";
    }

    $ress = self::$mysqli->query('select trm_id, trm_label, trm_code, '
    .'concat(trm_OriginatingDBID, \'-\', trm_IDInOriginatingDB) as trm_conceptid, trm_parenttermid from defTerms where trm_ID = '.intval($enum_id));
    if(!$ress){
        return null;
    }


        $relval = $ress->fetch_assoc();
        $ress->close();

        $get_param = mb_strtolower($enum_param_name, 'UTF-8');

        // If trm_label then construct is: "branch_trm_label. ... .leaf_term_label", ignore root label
        if(!(strcasecmp($get_param, 'label') == 0 && @$relval['trm_parenttermid'] > 0 && $relval['trm_label'] != null)){

            return @$relval['trm_'.$get_param];

        }

        $ret = null;

        $trm_id = @$relval['trm_parenttermid'];
        $ret = @$relval['trm_label'];

        while(1){

            $parent_ress = self::$mysqli->query("select trm_label, trm_ParentTermID from defTerms where trm_ID = " . intval($trm_id));

            if(!$parent_ress){
                break;
            }

            $parent_trm = $parent_ress->fetch_assoc();
            if($parent_trm == null || $parent_trm['trm_ParentTermID'] == null || $parent_trm['trm_ParentTermID'] == 0){
                $parent_ress->close();
                break;
            }

            $ret = $parent_trm['trm_label'] . "." . $ret;

            $trm_id = $parent_trm['trm_ParentTermID'];

            $parent_ress->close();
        }//while

        return $ret;
}

//
//
//
private static function __get_file_name($ulf_ID){

    if($ulf_ID>0){
        $fileinfo = fileGetFullInfo(self::$system, $ulf_ID);
        if(!isEmptyArray($fileinfo)){
            return $fileinfo[0]['ulf_OrigFileName'] == ULF_REMOTE ?
                    $fileinfo[0]['ulf_ExternalFileReference'] : $fileinfo[0]['ulf_OrigFileName'];
            //  array("file" => $fileinfo[0], "fileid"=>$fileinfo[0]["ulf_ObfuscatedFileID"]);
        }
    }
    return '';
}


/*
* Returns value for given detail type
*
* @param mixed $rdt_id - detail type id
* @param mixed $rt - record type
* @param mixed $mode - 0 value, 1 coded, 2 - human readable
* @param mixed $rec_id - record id for value mode
* @param mixed $enum_param_name - name of term field for value mode
* @return mixed
*/
private static function __get_field_value( $rdt_id, $rt, $mode, $rec_id, $enum_param_name=null) {

    if($mode==0){

        $local_dt_id = self::__get_dt_field($rt, $rdt_id, $mode, 'dty_ID');//local dt id
        $dt_type = '';
        if($local_dt_id>0){
            $dt_type = self::__get_dt_field($rt, $local_dt_id, $mode, 'dty_Type');
        }
        if($dt_type=='relmarker'){
            //find related record id
            $res = self::__get_related_record_ids($rec_id, $local_dt_id);

        }else{

            $rec_values = self::__get_record_value($rec_id);

            if(!$rec_values){
                return "";
            }elseif (strcasecmp($rdt_id,'id')==0){
                return $rec_values['rec_ID'];
            }elseif (strcasecmp($rdt_id,'rectitle')==0) {
                return $rec_values['rec_Title'];
            }elseif (strcasecmp($rdt_id,'rectypeid')==0) {
                return $rec_values['rec_RecTypeID'];
            }elseif (strcasecmp($rdt_id,'rectypename')==0) {
                return $rec_values['rty_Name'];
            }elseif (strcasecmp($rdt_id,'modified')==0) {
                return $rec_values['rec_Modified'];
            }

            $details = $rec_values['rec_Details'];
            $rdt_id = $local_dt_id;

            //dtl_DetailTypeID, dtl_Value, dtl_UploadedFileID, rst_RequirementType
            $res = array();
            $found = false;
            foreach($details as $detail){
                if($detail['dtl_DetailTypeID']==$rdt_id){
                    $found = true;
                    if($dt_type=="enum" || $dt_type=="relationtype"){
                        $value = self::__get_enum_value($detail['dtl_Value'], $enum_param_name);
                    }elseif($dt_type=='date'){
                        $value = Temporal::toHumanReadable(trim($detail['dtl_Value']));
                    }elseif($dt_type=="file"){
                        $value = self::__get_file_name(intval($detail['dtl_UploadedFileID']));
                    }elseif($dt_type=='freetext' || $dt_type=='blocktext'){
                        list(, $value) = extractLangPrefix($detail['dtl_Value']);// remove possible language prefix
                    }else{
                        $value = $detail['dtl_Value'];
                    }
                    if($value!=null && $value!=''){
                        array_push($res, $value);
                    }
                }elseif($found){
                    break;
                }
            }

        }

        if(empty($res)){
            return "";
        /*}elseif($dt_type == 'file'){
            return count($res)." file".(count($res)>1?"s":"");*/
        }elseif($dt_type == 'geo') {
            return count($res)." geographic object".(count($res)>1?"s":"");
        }else{
            return implode(",", $res);
        }

    }else{

        if (strcasecmp($rdt_id,'id')==0 ||
        strcasecmp($rdt_id,'rectitle')==0 ||
        strcasecmp($rdt_id,'modified')==0){
            return $rdt_id;
        }elseif($mode==1){ //convert to
            return $rdt_id; //concept code
        } else {
            return self::__get_dt_field($rt, $rdt_id, $mode, 'originalName');//original name (with capital chars)
        }
    }
}

/*
* Returns detail type attribute by  dty_ID, rst_DisplayName, dty_ConceptCode
* returns  dty_ConceptCode, dty_Type or original name (not lowercased)
*
* @param mixed $rt - record type
* @param mixed $search_fieldname  - search value: name of attribute(field) of detail type: dty_ID, rst_DisplayName, dty_ConceptCode
* @param mixed $result_fieldname - result filed
*/
private static function __get_dt_field($rt, $search_fieldname, $mode, $result_fieldname='dty_ConceptCode'){

    $rdr = self::__get_rec_detail_types($rt);

    $search_fieldname = mb_strtolower($search_fieldname, 'UTF-8');
    //$search_fieldname = strtolower($search_fieldname);

    if(self::_is_parent_entity($search_fieldname)){

        if (defined('DT_PARENT_ENTITY')){
            $rdt = self::__get_detail_types();
            if(@$rdt[DT_PARENT_ENTITY]){
                return $rdt[DT_PARENT_ENTITY][$result_fieldname];
            }
        }else{
            return '';
        }
    }elseif(@$rdr[$search_fieldname]){  //search by dty_ID, rst_DisplayName, dty_ConceptCode
        //search in record type structure
        return $rdr[$search_fieldname][$result_fieldname];
    }elseif($mode!=1) { //allow to search among all fields
        //if not found in structure - search among all detail types
        $rdt = self::__get_detail_types();
        if(@$rdt[$search_fieldname]){
            return $rdt[$search_fieldname][$result_fieldname];
        }
    }
    return null;
}

//
// get rectype id by name, cc or id
//
private static function __get_rt_id( $rt_search ){

        $query = 'SELECT rty_ID, rty_Name, rty_OriginatingDBID, rty_IDInOriginatingDB FROM defRecTypes where ';
        $where = '';

        $pos = mb_strpos($rt_search,'-');
        if ($pos>0){
            $db_oid = mb_substr($rt_search,0,$pos);
            $oid = mb_substr($rt_search,$pos+1);
            if(is_numeric($db_oid) && $db_oid>=0 && is_numeric($oid) && $oid>0){
                $where = 'rty_OriginatingDBID ='.$db_oid
                    .' AND rty_IDInOriginatingDB ='.$oid;
            }
        }
        $params = null;
        if($where==''){
            if($rt_search>0){
                $params = array('i',intval($rt_search));
                $where = 'rty_ID=?';
            }else{
                $params = array('s', mb_strtolower($rt_search, 'UTF-8'));
                $where = 'LOWER(rty_Name)=?';
            }
        }
        $query = $query . $where;

        $res = mysql__select_param_query(self::$mysqli, $query, $params);

        if(!$res){
            return array(0, '', '');

        }

        $row = $res->fetch_assoc();
        $res->close();
        if(!$row){
            return array(0, '', '');
        }

        if (is_numeric($row['rty_OriginatingDBID']) && $row['rty_OriginatingDBID']>0 &&
        is_numeric($row['rty_IDInOriginatingDB']) && $row['rty_IDInOriginatingDB']>0) {
            $rt_cc = "" . $row['rty_OriginatingDBID'] . "-" . $row['rty_IDInOriginatingDB'];
        } elseif (self::$db_regid>0) {
            $rt_cc = "" . self::$db_regid . "-" . $row['rty_ID'];
        } else {
            $rt_cc = $row['rty_ID'];
        }
        return array($row['rty_ID'], $rt_cc, $row['rty_Name']);
}

/*
* replace title mask tag to value, coded (concept codes) or textual representation
*
* @param mixed $field_name - mask tag
* @param mixed $rt - record type
* @param mixed $mode - 0 value, 1 coded, 2 - human readable
* @param $rec_id - record id for value mode
* @return mixed
*/
private static function __fill_field($field_name, $rt, $mode, $rec_id=null) {

    if (is_array($rt)){
        //ERROR
        return array("Field name '$field_name' was tested with Array of record types - bad parameter");
        // TODO: what does this error message mean? Make it comprehensible to the user
    }

    if(strcasecmp($field_name,'Record Title')==0){
        $field_name = 'rectitle';
    }elseif(strcasecmp($field_name,'Record ID')==0){
        $field_name = 'id';
    }elseif(strcasecmp($field_name,'Record TypeID')==0){
        $field_name = 'rectypeid';
    }elseif(strcasecmp($field_name,'Record TypeName')==0){
        $field_name = 'rectypename';
    }elseif(strcasecmp($field_name,'Record Modified')==0){
        $field_name = 'modified';
    }


    if (strcasecmp($field_name,'id')==0 ||
    strcasecmp($field_name,'rectitle')==0 ||
    strcasecmp($field_name,'modified')==0 ||
    strcasecmp($field_name,'rectypeid')==0 ||
    strcasecmp($field_name,'rectypename')==0)
    {
        $field_val = self::__get_field_value( $field_name, $rt, $mode, $rec_id );
        return $field_val;
    }

    $fullstop = '.';
    $fullstop_ex = '/^([^.]+?)\\s*\\.\\s*(.+)$/';
    $fullstop_concat = '.';

    if($mode==1 || mb_strpos($field_name, '..')>0){ //convert to concept codes
        $fullstop = '..';
        $fullstop_ex = '/^([^..]+?)\\s*\\..\\s*(..+)$/';//parsing
    }
    if($mode==2){ //convert to human readable codes
        $fullstop_concat = '..';
    }

    // Return the rec-detail-type ID for the given field in the given record type
    if (mb_strpos($field_name, $fullstop) === false && mb_strpos($field_name,'{')!==0) {    // direct field name lookup

        if($mode==1 && self::$fields_correspondence!=null){
            $field_name = self::__replaceInCaseOfImport($field_name);
        }

        $rdt_id = self::__get_dt_field($rt, $field_name, $mode);//get concept code
        if(!$rdt_id){
            //ERROR
            $msg = "Field name '$field_name' not recognised";
            $check_mask = $mode == 1 && !empty(self::$provided_mask);
            if($check_mask && mb_ereg("(^|[^\[])\[ +$field_name|$field_name +\]([^\]]|$)", self::$provided_mask)){
                // check for possible error
                $msg .= "<br>This may be due to leading, trailing or multiple spaces in"
                       ."<br>the field names - please edit the field names if this is the case";
            }
            return array($msg);
        }else {
            return self::__get_field_value( $rdt_id, $rt, $mode, $rec_id );
        }
    }

    $parent_field_name = null;
    $inner_field_name = null;

    $matches = array();

    //
    if(false && $fullstop == '.'){
        //preg_match does not split   A...term or A(s.)..term correctly
        preg_match_all($fullstop_ex, $field_name, $matches);
    }else{
        //parse human readable with double fullstops
        $matches = explode($fullstop, $field_name);

        if (!empty($matches)) {
            // fix rare case when we have more than 2 fullstops
            // in this case redundant fullstops are added to previous field
            //  AAA...BBB  =>  AAA. and BB
            $i = 1;
            while ($i<count($matches)){
                while(mb_strpos($matches[$i],'.')===0){
                    //move fullstop to the end of previous field
                    $matches[$i-1] = $matches[$i-1].'.';
                    $matches[$i] = mb_substr($matches[$i],1);
                }
                $i++;
            }
            //add full string to the begining
            array_unshift($matches, $field_name);
        }

    }



    if ($matches && count($matches)>1) {
        $parent_field_name = $matches[1];


        if($mode==1 && self::$fields_correspondence!=null){  //special case
            $parent_field_name = self::__replaceInCaseOfImport($parent_field_name);
        }//special case

        $rdt_id = self::__get_dt_field($rt, $parent_field_name, $mode);

        if($rdt_id){

            $inner_field_name = $matches[2];

            $dt_type = self::__get_dt_field($rt, $rdt_id, $mode, 'dty_Type');
            if($dt_type=="enum" || $dt_type=="relationtype"){

                if(!$inner_field_name || strcasecmp($inner_field_name,'label')==0){
                    $inner_field_name = "term";
                }elseif(strcasecmp($inner_field_name,'id')==0){
                    $inner_field_name = "internalid";
                }

                if (strcasecmp($inner_field_name,'internalid')==0 ||
                strcasecmp($inner_field_name,'term')==0 ||
                strcasecmp($inner_field_name,'code')==0 ||
                strcasecmp($inner_field_name,'conceptid')==0)
                {

                    if($mode==0){
                        return self::__get_field_value( $rdt_id, $rt, $mode, $rec_id, $inner_field_name);
                    }else{
                        if($mode==1){
                            $s1 = $rdt_id;
                        }else{
                            $s1 = self::__get_dt_field($rt, $rdt_id, $mode, 'originalName');
                        }

                        return $s1. $fullstop_concat .strtolower($inner_field_name);
                    }

                }else{
                    //ERROR
                    return array("error_title" => "Syntax error",
                                 "message" => "Unable to interpret '$inner_field_name' as a field<br><br>"
                                            + "Fields must be enclosed in square brackets []. If the name appears<br>"
                                            + "correct, please check for unwanted spaces, formatting or other characters.<br><br>"
                                            + "If you have used the tree on the left to insert a field and it insert incorrect<br>"
                                            + "text, please let us know with name of database, record type and field name,<br>"
                                            + "as this should not happen.");
                }
            }elseif($dt_type== 'resource'){


            }


            if(false && $dt_type== 'relmarker') { //@todo - to implement it in nearest future
                return array("'$parent_field_name' is a relationship marker field type. This type is not supported at present.");
            }
            if($dt_type!== 'resource' && $dt_type!=='relmarker') {
                //ERROR
                return array("'$parent_field_name' must be either a record type name, a terms list field name or a record pointer field name. "
                    ."Periods are used as separators between record type name and field names. If you have a period in your record type name or field name, "
                    ."please rename it to use an alternative punctuation such as - _ , ) |");
            }

        }else{
            //ERROR
            $msg = "'$parent_field_name' not recognised as a field name";
            $check_mask = $mode == 1 && !empty(self::$provided_mask);
            if($check_mask && mb_ereg("(^|[^\[])\[ +$parent_field_name|$parent_field_name +\]([^\]]|$)", self::$provided_mask)){
                // check for possible error
                $msg .= "<br>This may be due to leading, trailing or multiple spaces in"
                       ."<br>the field names - please edit the field names if this is the case";
            }
            return array($msg);
        }
    } else {
        return "";
    }

    //parent field id and inner field
    if ($rdt_id  &&  $inner_field_name) {

        //recordttype for pointer field may be defined in mask
        //it is required to distiguish rt for multiconstrained pointers
        $inner_rectype = 0;
        $inner_rectype_name = '';
        $inner_rectype_cc = '';//concept code
        $multi_constraint = false;

        if(count($matches)>3){ //this is resource (record pointer) field  [Places referenced..Media..Media item title]

            $ishift = 0;
            $pos = mb_strpos($inner_field_name, '{');//{Organization}..Name - name of target rectype is defined
            $pos2 = mb_strpos($inner_field_name, '}');
            $is_parent_entity = !empty($inner_field_name) ? self::_is_parent_entity($inner_field_name) : false;
            if($pos===0 && $pos2==mb_strlen($inner_field_name)-1){
                $inner_rectype_search = mb_substr($inner_field_name, 1, -1);

                $ishift = 3;
                $multi_constraint = true;
            }else{

                $inner_rectype = self::__get_dt_field($rt, $rdt_id, $mode, 'rst_PtrFilteredIDs');
                $inner_rectype = explode(",", $inner_rectype);//mb_split
                if(count($inner_rectype)==1 && $inner_rectype[0]>0 || $is_parent_entity){
                    $inner_rectype = $inner_rectype[0];
                    $ishift = 2;
                }else{
                    $inner_rectype = 0;
                    $inner_rectype_search = $inner_field_name;
                    $ishift = 3;
                }

            }
            if($inner_rectype==0 && !$is_parent_entity){
                list($inner_rectype, $inner_rectype_cc, $inner_rectype_name) = self::__get_rt_id( $inner_rectype_search );
                if(!($inner_rectype>0)){
                    return array("error_title" => "Syntax error",
                                 "message" => "Unable to interpret '$inner_rectype_search' as a record type<br><br>"
                                            .'Record types must be enclosed in curly brackets {}. If the name appears<br>'
                                            .'correct, please check for unwanted spaces, formatting or other characters.<br><br>'
                                            .'If you have used the tree on the left to insert a field and it insert incorrect<br>'
                                            .'text, please let us know with name of database, record type and field name,<br>'
                                            .'as this should not happen.');
                }
            }

            $f_name = implode($fullstop, array_splice($matches,$ishift));

            if($mode==0){//replace with value
                $pointer_ids = self::__get_field_value( $rdt_id, $rt, $mode, $rec_id);
                $pointer_ids = prepareIds($pointer_ids);
                $res = array();
                foreach ($pointer_ids as $rec_id){
                    $fld_value = self::__fill_field($f_name, $inner_rectype, $mode, $rec_id);//recursion
                    array_push($res, $fld_value);
                }
                $res = implode(", ", $res);
            }else{

                if($mode==1){
                    $s1 = $rdt_id; //parent detail id
                    if($multi_constraint){
                        $s1 = $s1 .$fullstop_concat.'{'. $inner_rectype_cc.'}';
                    }
                }else{
                    $s1 = self::__get_dt_field($rt, $rdt_id, $mode, 'originalName');
                    if($multi_constraint){
                        $s1 = $s1 .$fullstop_concat.'{'. $inner_rectype_name. '}';
                    }
                }

                $s2 = self::__fill_field($f_name, $inner_rectype, $mode, $rec_id);
                if(is_array($s2)){
                    $res = $s2; //error
                } else {
                    $res = $s1. $fullstop_concat .$s2; //recursion
                }
            }


            return $res;
            // TEMP
            //list($inner_rectype, $inner_rectype_cc, $inner_rectype_name) = self::__get_rt_id( $inner_rectype_search );
            //$inner_field_name = $matches[3];
        }else{


            $pos = mb_strpos($inner_field_name, $fullstop);//{Organization}..Name
            $pos2 = mb_strpos($inner_field_name, '}');
            if ( $pos>0 &&  $pos2>0 && $pos2 < $pos ) {
                $inner_rectype_search = mb_substr($inner_field_name, 1, $pos2-1);//was $pos-mb_strlen($fullstop)
                list($inner_rectype, $inner_rectype_cc, $inner_rectype_name) = self::__get_rt_id( $inner_rectype_search );
                $inner_field_name = mb_substr($inner_field_name, $pos+mb_strlen($fullstop));
            }
        }

        if($mode==0){ //replace with values
//[Note title]  [Author(s).{PersonBig}.Family Name] ,  [Author(s).{Organisation}.Full name of organisation]
// [2-1]  [2-15.{2-10}.2-1] ,  [2-15.{2-4}.2-1]

            //get values for resource (record pointer) field
            $pointer_ids = self::__get_field_value( $rdt_id, $rt, $mode, $rec_id);
            $pointer_ids = prepareIds($pointer_ids);
            $res = array();
            foreach ($pointer_ids as $rec_id){

                $rec_value = self::__get_record_value($rec_id);
                if($rec_value){
                    $res_rt = $rec_value['rec_RecTypeID'];//resource (linked record) type rt

                    if($inner_rectype>0 && $inner_rectype!=$res_rt) {continue;}

                    $fld_value = self::__fill_field($inner_field_name, $res_rt, $mode, $rec_id);
                    if(is_array($fld_value)){
                        //for multiconstraint it may return error since field may belong to different rt
                        return '';//$fld_value; //ERROR
                    }elseif($fld_value) {
                        array_push($res, $fld_value);
                    }
                }
                //self::__get_field_value( $rdt_id, $rt, $mode, $rec_id) );
            }
            return implode(", ", $res);

        }else{ //convert  coded<->human

            if($inner_rectype>0){
                $inner_rec_type = array($inner_rectype);
            }else{
                $inner_rec_type = self::__get_dt_field($rt, $rdt_id, $mode, 'rst_PtrFilteredIDs');
                $inner_rec_type = explode(",", $inner_rec_type);
            }
            if(!empty($inner_rec_type)){ //constrained
                $field_not_found = null;
                foreach ($inner_rec_type as $rtID){
                    $rtid = intval($rtID);
                    if (!$rtid) {continue;}
                    if($inner_rectype>0){
                        if($inner_rectype!=$rtid) {continue;} //skip
                    }else{
                        list($rtid, $inner_rectype_cc, $inner_rectype_name) = self::__get_rt_id( $rtid );
                    }

                    $inner_rdt = self::__fill_field($inner_field_name, $rtid, $mode);
                    if(is_array($inner_rdt)){
                        //it may be found in another record type for multiconstaints
                        $field_not_found = $inner_rdt; //ERROR
                    }elseif($inner_rdt) {

                        if($mode==1){
                            $s1 = $rdt_id; //parent detail id
                            if($inner_rectype>0){
                                $s1 = $s1 .$fullstop_concat.'{'. $inner_rectype_cc.'}';
                            }
                        }else{
                            $s1 = self::__get_dt_field($rt, $rdt_id, $mode, 'originalName');
                            if($inner_rectype>0){
                                $s1 = $s1 .$fullstop_concat.'{'. $inner_rectype_name. '}';
                            }
                        }
                        return $s1. $fullstop_concat .$inner_rdt;
                    }
                }
                if($field_not_found){
                    return $field_not_found;
                }
            }
            if($mode==1){  //return concept code
                $s1 = $rdt_id;
            }else{
                $s1 = self::__get_dt_field($rt, $rdt_id, $mode, 'originalName');
            }
            return $s1. ($inner_field_name? $fullstop_concat.$inner_field_name:"");
        }
    }

    return "";
}

//
// replace local dty_ID to concept code (for import)
//
private static function __replaceInCaseOfImport($dty_ID){
    //special case - replace dty_ID in case of definition import
    if(strpos($dty_ID,"-")===false && is_numeric($dty_ID)){ //this is not concept code and numeric

        if(self::$fields_correspondence!=null && count(self::$fields_correspondence)>0 && @self::$fields_correspondence[$dty_ID]){
            //print "<br>>>>was ".$dty_ID;
            $dty_ID = @self::$fields_correspondence[$dty_ID];
            //print "<br>>>>replace to ".$dty_ID;
        }
    }
    return $dty_ID;
}

//
// Check if provided field is for a record's parent entity
//
private static function _is_parent_entity($field_name){

    $field_name = mb_strtolower($field_name, 'UTF-8');

    return mb_strpos($field_name, 'parent entity')===0
        || mb_strpos($field_name, 'record parent')===0
        || (defined('DT_PARENT_ENTITY') && $field_name==DT_PARENT_ENTITY)
        || $field_name=='2-247';
}

}//end of class

if (! function_exists('array_str_replace')) {

    function array_str_replace($search, $replace, $subject) {
        /*
        * PHP's built-in str_replace is broken when $search is an array:
        * it goes through the whole string replacing $search[0],
        * then starts again at the beginning replacing $search[1], &c.
        * array_str_replace instead looks for non-overlapping instances of each $search string,
        * favouring lower-indexed $search terms.
        *
        * Whereas str_replace(array("a","b"), array("b", "x"), "abcd") returns "xxcd",
        * array_str_replace returns "bxcd" so that the user values aren't interfered with.
        */

        $val = '';

        while ($subject) {
            $match_idx = -1;
            $match_offset = -1;
            for ($i=0; $i < count($search);++$i) {
                if(isEmptyStr($search[$i])) {continue;}
                $offset = mb_strpos($subject, $search[$i]);
                if ($offset === false) {continue;}

                if ($match_offset == -1  ||  $offset < $match_offset) {
                    $match_idx = $i;
                    $match_offset = $offset;
                }
            }

            if ($match_idx == -1) {
                // no matches for any of the strings
                $val .= $subject;
                $subject = '';
                break;
            }

            $val .= mb_substr($subject, 0, $match_offset) . $replace[$match_idx];
            $subject = mb_substr($subject, $match_offset + mb_strlen($search[$match_idx]));
        }

        return $val;
    }

}
?>
