<?php
    /**
    * dbsData.php - retrieveing database definitions
    *
    * Library of function that provides database structure information: rectypes, fieldtypes and terms defined in database
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    * @todo convert to class (singleton?)
    *
    *
    *
    * dbs_ - prefix for functions
    *
    * dbs_GetRectypeStructures
    * dbs_GetRectypeStructure
    * dbs_GetRectypeGroups
    * dbs_GetRectypeByID
    * dbs_GetRectypeNames
    * dbs_GetRectypeIDs  - returns array of rty_IDs
    * dbs_GetTerms
    * dbs_GetDetailTypes
    * dbs_GetDtLookups
    *
    * TERMS RELATED FUNCTION - to be public methods,
    * they work with global $terms array - need to be defined by dbs_GetTerms before call these methods
    * getTermOffspringList
    * getTermTopMostParent
    * getTermChildren
    * getTermChildrenAll - get all children including by reference as a flat array
    * getTermInTree
    * getTermByLabel
    * getTermByCode
    * getTermById
    * getTermFullLabel
    * getTermListAll - get tree for domain
    * getTermLabels
    * getTermCodes
    *
    * INTERNAL FUNCTIONS
    * __getRectypeColNames
    * __getColumnNameToIndex
    * __getRectypeStructureFieldColNames
    * __getTermTree
    * __attachChild
    * __getTermColNames
    *
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */
    use hserv\utilities\USanitize;
    use hserv\report\ReportTemplateMgr;

    require_once dirname(__FILE__).'/../../records/edit/recordTitleMask.php';

    define('DATABASE','DATABASE: ');

    /**
    * @return    object iformation describing all the rectypes defined in the database
    * an array of RecType Structures for all RecTypes
    * rectypes = {{"groups":{"groupIDToIndex":{recTypeGroupID:index},
    *                         recTypeGroupID:{propName:val,...},...}},
    *             "names":{rtyID:name,...},
    *             "pluralNames":{rtyID:pluralName,...},
    *             "usageCount":{rtyID:nonzero-count,...},
    *             "dtDisplayOrder":{rtyID:[ordered dtyIDs], ...},
    *             "typedefs":{"commonFieldNames":[defRecType Column names],
    *                         "commonNamesToIndex":{"defRecTypes columnName":index,...},
    *                         "dtFieldNamesToIndex":{"defRecStructure columnName":index,...},
    *                         "dtFieldNames":[defRecStructure Column names],
    *                         rtyID:{"dtFields":{dtyID:[val,...]},
    *                                 "commonFields":[val,....]},
    *                         ...},
    *             "constraints":[]}};
    *
    * @uses      dbs_GetRectypeColNames()
    * @uses      dbs_GetColumnNameToIndex()
    * @uses      dbs_GetRectypeStructureFieldColNames()
    * @uses      dbs_GetRectypeGroups()
    * @uses      dbs_GetRecTypeUsageCount()
    *
    *
    * @param mixed $rectypeids null means all, otherwise comma separated list of ids
    * @param mixed $imode  0 - only names and groupnames  (default)
    *                      1 - only structure (@TODO NO NAMES!!!!)
    *                      2 - full, both headers and structures
    */
    function dbs_GetRectypeStructures($system, $rectypeids=null, $imode=0) { //$useCachedData = false) {


        if($imode<0 || $imode>2){
            $imode = 0;
        }

        $mysqli = $system->get_mysqli();
        $dbID = $system->settings->get('sys_dbRegisteredID');

        /*ARTEM $cacheKey = DATABASE . ":AllRecTypeInfo";
        if ($useCachedData) {
        $rtStructs = getCachedData($cacheKey);
        if ($rtStructs) {
        return $rtStructs;
        }
        }*/
        // NOTE: these are ordered to match the order of dbs_GetRectypeStructureFieldColNames from DisplayName on
        $colNames = array("rst_RecTypeID", "rst_DetailTypeID",
            //here we check for an override in the recTypeStrucutre for displayName which is a rectype specific name, use detailType name as default
            "if(rst_DisplayName is not null and CHAR_LENGTH(rst_DisplayName)>0,rst_DisplayName,dty_Name) as rst_DisplayName",
            //here we check for an override in the recTypeStrucutre for HelpText which is a rectype specific HelpText, use detailType HelpText as default
            "if(rst_DisplayHelpText is not null and (dty_Type='separator' OR CHAR_LENGTH(rst_DisplayHelpText)>0),rst_DisplayHelpText,dty_HelpText) as rst_DisplayHelpText",
            //here we check for an override in the recTypeStrucutre for ExtendedDescription which is a rectype specific ExtendedDescription, use detailType ExtendedDescription as default
            "if(rst_DisplayExtendedDescription is not null and CHAR_LENGTH(rst_DisplayExtendedDescription)>0,rst_DisplayExtendedDescription,dty_ExtendedDescription) as rst_DisplayExtendedDescription",
            "rst_DisplayOrder", "rst_DisplayWidth", "rst_DisplayHeight", "rst_DefaultValue", "rst_RecordMatchOrder", "rst_CalcFunctionID", "rst_RequirementType",
            "rst_NonOwnerVisibility", "rst_Status", "rst_OriginatingDBID", "rst_MaxValues", "rst_MinValues",
            //here we check for an override in the recTypeStrucutre for displayGroup
            "if(rst_DisplayDetailTypeGroupID is not null,rst_DisplayDetailTypeGroupID,dty_DetailTypeGroupID) as rst_DisplayDetailTypeGroupID",
            //here we check for an override in the recTypeStrucutre for TermIDTree which is a subset of the detailType dty_JsonTermIDTree
            "if(rst_FilteredJsonTermIDTree is not null and CHAR_LENGTH(rst_FilteredJsonTermIDTree)>0,rst_FilteredJsonTermIDTree,dty_JsonTermIDTree) as rst_FilteredJsonTermIDTree",
            //here we check for an override in the recTypeStrucutre for Pointer types which is a subset of the detailType dty_PtrTargetRectypeIDs
            "if(rst_PtrFilteredIDs is not null and CHAR_LENGTH(rst_PtrFilteredIDs)>0,rst_PtrFilteredIDs,dty_PtrTargetRectypeIDs) as rst_PtrFilteredIDs",
            "rst_CreateChildIfRecPtr", "rst_PointerMode", "rst_PointerBrowseFilter",
            "rst_OrderForThumbnailGeneration", "rst_TermIDTreeNonSelectableIDs", "rst_Modified", "rst_LocallyModified",
            "rst_SemanticReferenceURL", "rst_TermsAsButtons",
            "dty_TermIDTreeNonSelectableIDs",
            "dty_FieldSetRectypeID",
            "dty_Type");
        //add dty_ConceptID
        if ($dbID) { //if(trm_OriginatingDBID,concat(cast(trm_OriginatingDBID as char(5)),'-',cast(trm_IDInOriginatingDB as char(5))),'null') as trm_ConceptID
            $dty_ConceptID = "if(dty_OriginatingDBID, concat(cast(dty_OriginatingDBID as char(5)),'-',cast(dty_IDInOriginatingDB as char(5))), concat('$dbID-',cast(dty_ID as char(5)))) as dty_ConceptID";
        } else {
            $dty_ConceptID = "if(dty_OriginatingDBID, concat(cast(dty_OriginatingDBID as char(5)),'-',cast(dty_IDInOriginatingDB as char(5))), '') as dty_ConceptID";
        }
        array_push($colNames, $dty_ConceptID);

        $query = "select " . join(",", $colNames) .
        " from defRecStructure".
        " left join defDetailTypes on rst_DetailTypeID = dty_ID".
        " left join defDetailTypeGroups on dtg_ID = if(rst_DisplayDetailTypeGroupID is not null,rst_DisplayDetailTypeGroupID,dty_DetailTypeGroupID)";

        if(!is_array($rectypeids) && !is_numeric($rectypeids) && strpos($rectypeids, '-') !== false){ // concept code, get local id

            $concept_code = explode('-', $rectypeids);
            $concept_code[0] = is_numeric($concept_code[0]) && $concept_code[0] > 0 ? intval($concept_code[0]) : $dbID;
            $concept_code[1] = intval($concept_code[1]);

            $rectypeids = mysql__select_value($mysqli, "SELECT rty_ID FROM defRecTypes WHERE rty_OriginatingDBID = ? AND rty_IDInOriginatingDB = ?", ["ii", $concept_code[0], $concept_code[1]]);
            $rectypeids = !$rectypeids ? 0 : intval($rectypeids);
        }

        if($rectypeids){
            $querywhere = " where rst_RecTypeID in (".(is_array($rectypeids)?implode(",", $rectypeids) :$rectypeids).")";
        } else {
            $querywhere = "";
        }

        $rtStructs = array();
        $rtStructs['names'] = array();
        $rtStructs['pluralNames'] = array();

        $columns = __getRectypeColNames();
        $ind_TitleMask = array_search('rty_TitleMask', $columns);
        $ind_CanonicalTitleMask = array_search('rty_CanonicalTitleMask', $columns);
        $ind_Name = array_search('rty_Name', $columns);
        $ind_Plural = array_search('rty_Plural', $columns);

        if($imode!=1){
            $rtStructs['groups'] = dbs_GetRectypeGroups($mysqli);
        }

        if($imode==2){
            //@todo  'usageCount' => getRecTypeUsageCount(),
            //@todo  'constraints' => getAllRectypeConstraint(),
        }
        if($imode>0){   //structure description

            $rtStructs['dtDisplayOrder'] = array();
            $rtStructs['typedefs'] = array('commonFieldNames' => $columns,
                'commonNamesToIndex' => __getColumnNameToIndex( $columns ),
                'dtFieldNamesToIndex' => __getColumnNameToIndex(__getRectypeStructureFieldColNames()),
                'dtFieldNames' => __getRectypeStructureFieldColNames());

            $query .=  $querywhere." order by rst_RecTypeID, rst_DisplayOrder, rst_ID";
            $res = $mysqli->query($query);

            if($res){
                while ($row = $res->fetch_row()) {
                    $vals = array_slice($row, 2);
                    if (!array_key_exists($row[0], $rtStructs['typedefs'])) {
                        $rtStructs['typedefs'][$row[0]] = array('dtFields' => array($row[1] => $vals));
                        $rtStructs['dtDisplayOrder'][$row[0]] = array();
                    } else {
                        $rtStructs['typedefs'][$row[0]]['dtFields'][$row[1]] = $vals;
                    }
                    array_push($rtStructs['dtDisplayOrder'][$row[0]], $row[1]);

                }
                $res->close();
            }else{
                USanitize::errorLog(DATABASE.$system->dbname().'. Error retrieving rectype structure '.$mysqli->error);
            }



            //has calculation field
                //load calculation fields
                $colNames = array('cfn_ID','cfn_Name','cfn_Domain','cfn_FunctionSpecification','cfn_Modified','cfn_RecTypeIDs');
                $cfields = array();
                $query = 'SELECT DISTINCT '.implode(',', $colNames).' FROM defCalcFunctions, defRecStructure WHERE cfn_ID=rst_CalcFunctionID';
                $rtStructs['calcfields'] = array();
                //$rtStructs['calcfields']['fieldNames'] = $colNames;
                //$rtStructs['calcfields']['values'] = mysql__select_all($mysqli, $query, 1);

                $repAction = new ReportTemplateMgr($system, null);

                $res = $mysqli->query($query);
                if($res){
                    while ($row = $res->fetch_assoc()) {
                        $cfn_code = $row['cfn_FunctionSpecification'];
                        //convert to concept codes
                        $row['cfn_FunctionSpecification'] = $repAction->convertTemplate($cfn_code, 0);

                        $rtStructs['calcfields'][$row['cfn_ID']]  = $row; //array_slice($row, 1);
                    }
                }
        }


        // get rectypes ordered by the RecType Group order, then by Group Name, then by rectype order in group and then by rectype name
        $query = "select rty_ID, rtg_ID, rtg_Name, " . join(",", $columns);
        $query = str_replace('rty_ConceptID', '', $query);
        if ($dbID) { //if(trm_OriginatingDBID,concat(cast(trm_OriginatingDBID as char(5)),'-',cast(trm_IDInOriginatingDB as char(5))),'null') as trm_ConceptID
            $query.= " if(rty_OriginatingDBID, concat(cast(rty_OriginatingDBID as char(5)),'-',cast(rty_IDInOriginatingDB as char(5))), concat('$dbID-',cast(rty_ID as char(5)))) as rty_ConceptID";
        } else {
            $query.= " if(rty_OriginatingDBID, concat(cast(rty_OriginatingDBID as char(5)),'-',cast(rty_IDInOriginatingDB as char(5))), '') as rty_ConceptID";
        }

        if($rectypeids){
            $querywhere = SQL_WHERE.predicateId('rty_ID', $rectypeids);
        } else {
            $querywhere = "";
        }

        $query = $query." from defRecTypes left join defRecTypeGroups  on rtg_ID = rty_RecTypeGroupID" .
        $querywhere.
        " order by rtg_Order, rtg_Name, rty_OrderInGroup, rty_Name";


        $res = $mysqli->query($query);
        if($res){
            while ($row = $res->fetch_row()) {

                $rtyID = $row[0];

                if($imode!=1){
                    $rtg_ID = $row[1];
                    if(!@$rtStructs['groups']['groupIDToIndex'][$rtg_ID]){
                        if($rtg_ID>0){
                        }
                        $idxs = array_keys($rtStructs['groups']['groupIDToIndex']);
                        $rtg_ID = $idxs[0];
                    }
                    if(@$rtStructs['groups']['groupIDToIndex'][$rtg_ID]>=0){
                        array_push($rtStructs['groups'][$rtStructs['groups']['groupIDToIndex'][$rtg_ID]]['allTypes'], $rtyID);
                        if ($row[14]) { //rty_ShowInList
                            array_push($rtStructs['groups'][$rtStructs['groups']['groupIDToIndex'][$rtg_ID]]['showTypes'], $rtyID);
                        }
                    }
                }
                $commonFields = array_slice($row, 3);
                if($imode>0){
                    //convert concept code title mask to human readable
                    $mask_concept_codes = $commonFields[$ind_TitleMask];
                    $commonFields[$ind_CanonicalTitleMask] = $mask_concept_codes; //keep
                    $commonFields[$ind_TitleMask] = TitleMask::execute($mask_concept_codes, $rtyID, 2, null, ERROR_REP_SILENT);

                    $rtStructs['typedefs'][$rtyID]['commonFields'] = $commonFields;
                }
                $rtStructs['names'][$rtyID] = $commonFields[$ind_Name];
                $rtStructs['pluralNames'][$rtyID] = $commonFields[$ind_Plural];
            }
            $res->close();
        }else{
            USanitize::errorLog(DATABASE.$system->dbname().'. Error retrieving rectype structure '.$mysqli->error);
        }


        //ARTEM setCachedData($cacheKey, $rtStructs);
        return $rtStructs;
    }

    /**
    * Return record structure for given id and update $recstructures array
    *
    * @param mixed $system
    * @param mixed $recstructures
    * @param mixed $rectype
    */
    function dbs_GetRectypeStructure($system, &$recstructures, $rectype)
    {
        if (!@$recstructures[$rectype] ) {
            //load rectype
            $res = dbs_GetRectypeStructures($system, $rectype, 1);
            if(!@$recstructures['commonNamesToIndex']){
                $recstructures['commonNamesToIndex'] = $res['typedefs']['commonNamesToIndex'];
                $recstructures['dtFieldNamesToIndex'] = $res['typedefs']['dtFieldNamesToIndex'];
            }
            $recstructures[$rectype] = @$res['typedefs'][$rectype];
        }
        return @$recstructures[$rectype];
    }

    /**
    * get recType Group definitions
    * groups = {"groupIDToIndex":{recTypeGroupID:index},
    *           index:{propName:val,...},...}
    * @return    array recTypeGroup definitions as array of prop:val pairs
    */
    function dbs_GetRectypeGroups($mysqli, $load_rectypes=false) {
        $rtGroups = array('groupIDToIndex' => array());
        $index = 0;
        $res = $mysqli->query("select * from defRecTypeGroups order by rtg_Order, rtg_Name");
        while ($row = $res->fetch_assoc()) {

            array_push($rtGroups, array('id' => $row["rtg_ID"], 'name' => $row["rtg_Name"], 'order' => $row["rtg_Order"], 'description' => $row["rtg_Description"], 'allTypes' => array(), 'showTypes' => array()));
            $rtGroups['groupIDToIndex'][$row["rtg_ID"]] = $index++;
        }
        $res->close();


        if(!$load_rectypes){
            return $rtGroups;
        }

            $query = 'select rty_ID, rty_RecTypeGroupID, rty_ShowInLists from defRecTypes order by rty_OrderInGroup, rty_Name';

            $res = $mysqli->query($query);
            if(!$res){
                return $rtGroups;
            }

                while ($row = $res->fetch_row()) {

                    $rtg_ID = $row[1];

                    if(@$rtGroups[$rtGroups['groupIDToIndex'][$rtg_ID]]){
                        $rtyID = $row[0];

                        //add rectype to allTypes and showTypes
                        array_push($rtGroups[$rtGroups['groupIDToIndex'][$rtg_ID]]['allTypes'], $rtyID);
                        if ($row[2]) { //rty_ShowInList
                            array_push($rtGroups[$rtGroups['groupIDToIndex'][$rtg_ID]]['showTypes'], $rtyID);
                        }
                    }
                }

        return $rtGroups;
    }


    /**
    * return rectype by id
    * mostly used to verify rectype existence
    *
    * @param mixed $mysqli
    * @param mixed $rty_ID
    */
    function dbs_GetRectypeByID($mysqli, $rty_ID) {

        $rectype = null;
        $query = 'select * from defRecTypes where rty_ID ='.intval($rty_ID);
        $res = $mysqli->query($query);
        if($res){
            $rectype =$res->fetch_assoc();
        }
        $res->close();
        return $rectype;
    }

    /**
    * returns array of rectype names for given ids
    *
    * @param mixed $mysqli
    * @param mixed $rty_IDs
    */
    function dbs_GetRectypeNames($mysqli, $rty_IDs=null){

        $query = 'select rty_ID, rty_Name from defRecTypes';

        if($rty_IDs){
            $rty_IDs = prepareIds($rty_IDs);

            $labels = array();
            if (!isEmptyArray($rty_IDs)) {
                    $query = $query.' where rty_ID in ('.implode(',', $rty_IDs).')';
            }
        }
        $labels = mysql__select_assoc2($mysqli, $query);
        return $labels;

    }

    function dbs_GetRectypeIDs($mysqli, $_rty_IDs){

        if(is_true($_rty_IDs)){
            $rty_IDs = true;  //returns all
        }else{
            $rty_IDs = prepareIds($_rty_IDs);
        }

        $labels = array();
        if ($rty_IDs) {
            $query = 'select rty_ID from defRecTypes';
            if(!isEmptyArray($rty_IDs)){
                $query = $query . ' where rty_ID in ('.implode(',', $rty_IDs).')';
            }
            $labels = mysql__select_list2($mysqli, $query);
        }
        return $labels;

    }


/**
* get rectype constraint structure with lookups by target and term id index by srcID
* constraints = array( [recID | any] => array(
*                          byTerm => array(
*                              [trmID|any] => array([trgID|any] => array(
*                                                          limit => $max
*                                                          [,addsTo => parentTrmID]))
*                              [,offspring =>array(trmID[,trmID])),
*                          byTarget => array(
*                              [trgID|any] => array([trmID|any] => array(
*                                                          limit => $max,
*                                                          notes => $notes
*                                                          [,addsTo => parentTrmID])))))
* where $max can be "unlimited" or any positive integer with 0 meaning not allowed
* addsTo atttemps to handle child terms with out specific constraints, they effectively
* inherit the parents limit.
* @return    object constraint structure
* @uses      getTermOffspringList()
*/
function dbs_GetRectypeConstraint($system) {

    $query = "select rcs_SourceRectypeID as srcID,
    rcs_TermID as trmID,
    rcs_TargetRectypeID as trgID,
    rcs_TermLimit as max,
    rcs_Description as notes,
    trm_Depth as level,
    if(trm_ChildCount > 0, true, false) as hasChildren
    from defRelationshipConstraints
    left join defTerms on rcs_TermID = trm_ID
    order by rcs_SourceRectypeID is null,
    rcs_SourceRectypeID,
    trm_Depth,
    rcs_TermID is null,
    rcs_TermID,
    rcs_TargetRectypeID is null,
    rcs_TargetRectypeID";

    $mysqli = $system->get_mysqli();

    $res = $mysqli->query($query);
    $cnstrnts = array();
    while ($row = $res->fetch_assoc()) {
        //        $srcID = (@$row['srcID'] === null ? "".'0' : $row['srcID']);
        //        $trmID = (@$row['trmID'] === null ? "".'0' : $row['trmID']);
        //        $trgID = (@$row['trgID'] === null ? "".'0' : $row['trgID']);
        //        $max = (@$row['max'] === null ? '' : $row['max']);
        $srcID = (@$row['srcID'] === null ? "any" : $row['srcID']);
        $trmID = (@$row['trmID'] === null ? "any" : $row['trmID']);
        $trgID = (@$row['trgID'] === null ? "any" : $row['trgID']);
        $max = (@$row['max'] === null ? 'unlimited' : $row['max']);
        $notes = $row['notes'];
        $hasChildren = $row['hasChildren'];
        if (!@$cnstrnts[$srcID]) {//first instance of this recType as source, create structure
            $cnstrnts[$srcID] = array('byTerm' => array(), 'byTarget' => array());
        }
        if (!@$cnstrnts[$srcID]['byTerm'][$trmID]) {//first instance of this recType as target, create structure
            $cnstrnts[$srcID]['byTerm'][$trmID] = array($trgID => array('limit' => $max));
        }
        if (!@$cnstrnts[$srcID]['byTarget'][$trgID]) {//first instance of this recType as bytarget target, create structure
            $cnstrnts[$srcID]['byTarget'][$trgID] = array($trmID => array('limit' => $max, "notes" => $notes));
        } elseif (!@$cnstrnts[$srcID]['byTarget'][$trgID][$trmID]) {
            $cnstrnts[$srcID]['byTarget'][$trgID][$trmID] = array('limit' => $max, "notes" => $notes);
        }
        if (!@$cnstrnts[$srcID]['byTerm'][$trmID][$trgID]) {//new target for term lookup
            $cnstrnts[$srcID]['byTerm'][$trmID][$trgID] = array('limit' => $max);
        }
        if (@$cnstrnts[$srcID]['byTerm'][$trmID][$trgID]['addsTo']) {
            $cnstrnts[$srcID]['byTerm'][$trmID][$trgID]['limit'] = $max;
        }
        if (@$cnstrnts[$srcID]['byTarget'][$trgID][$trmID]['addsTo']) {
            $cnstrnts[$srcID]['byTarget'][$trgID][$trmID]['limit'] = $max;
        }
        $offspring = $trmID && $trmID !== "any" && $hasChildren ? getTermOffspringList($mysqli, $trmID) : null;
        if ($offspring) {
            $cnstrnts[$srcID]['byTerm'][$trmID]['offspring'] = $offspring;
            foreach ($offspring as $childTermID) { // point all offspring to inherit from term
                $cnstrnts[$srcID]['byTerm'][$childTermID][$trgID] = array('addsTo' => $trmID);
                $cnstrnts[$srcID]['byTarget'][$trgID][$childTermID] = array('addsTo' => $trmID);
            }
        }
    }
    $res->close();
    return $cnstrnts;
}


    /**
     * Get translation records for given values
     *
     * @param   array $fields array of objects containing details about what translations to retrieve
     * @return  array record rows from defTranslations
     */
    function dbs_GetTranslations($system, $fields){

        if(isEmptyArray($fields)){
            return array('response' => 'data is in wrong format');
        }

        $mysqli = $system->get_mysqli();
        $search_query = 'SELECT trn_ID, trn_Source, trn_Code, trn_LanguageCode, trn_Translation, trn_Modified '
            . 'FROM defTranslations '
            . 'WHERE trn_Source {def_source} AND trn_Code={def_id}';

        $to_replace = array('{def_source}', '{def_id}');

        $results = array(
            'translations' => array(),
            'key_mapping' => array()
            //'commonFieldNames' => array_slice(__getTranslationColName(), 1),
            //'fieldNamesToIndex' => __getColumnNameToIndex(array_slice(__getTranslationColName(), 1))
        );

        // array( [local_id] => array(
        //  trn_Source, trn_Code
        // ))
        $translations = array();
        $translations_id_mapping = array();

        foreach ($fields as $key => $field) {

            if(empty(@$field['trn_Source']) || empty(@$field['trn_Code'])){
                continue;
            }

            $trn_Code = @$field['trn_Code'];
            $trn_Source = @$field['trn_Source'];

            if(substr($trn_Source, -1) == '_'){ // for all translations of a definition, {trm_, dty_, rty_}
                $trn_Source = 'LIKE "'. htmlspecialchars($trn_Source) .'%"';
            }else{
                $trn_Source = '= "'. htmlspecialchars($trn_Source) .'"';
            }

            if(strpos($trn_Code, '-')){ // concept code
                $trn_Code = explode('-', $trn_Code)[1];
            }
            $trn_Code = intval($trn_Code);

            if($trn_Code < 1){
                continue;
            }

            $query = str_replace($to_replace, array($trn_Source, $trn_Code), $search_query);

            $records = mysql__select_assoc($mysqli, $query);
            if(isEmptyArray($records)){ // no translations found
                continue;
            }
            $translations = array_replace($translations, $records);// $translations + $records
            $translations_id_mapping[$key] = array_keys($records);
        }

        if(!isEmptyArray($translations)){
            $results['translations'] = $translations;
            $results['key_mapping'] = $translations_id_mapping;
        }

        return $results;
    }

    /**
    * Get term structure with trees from relation and enum domains
    *
    * @param     boolean $useCachedData whether to use cached data (default = false)
    * @return    object terms structure that contains domainLookups and domain term trees
    * @uses      __getTermColNames()
    * @uses      __getTermTree()
    * @uses      getCachedData()
    * @uses      setCachedData()
    */
    function dbs_GetTerms($system){ //$useCachedData = false) {

        $mysqli = $system->get_mysqli();
        $dbID = $system->settings->get('sys_dbRegisteredID');


        /* ARTEM
        $cacheKey = DATABASE . ":dbs_GetTerms";
        if ($useCachedData) {
        $terms = getCachedData($cacheKey);
        if ($terms) {
        return $terms;
        }
        }*/
        $query = "select " . join(",", __getTermColNames());
        $query = str_replace('trm_ConceptID', '', $query);

        //in case database v1.2 there is not field trm_VocabularyGroupID
        $dbVer = $system->settings->get('sys_dbVersion');
        $dbVerSub = $system->settings->get('sys_dbSubVersion');
        if($dbVer==1 && $dbVerSub<3){
            $query = str_replace('trm_VocabularyGroupID', '1 as trm_VocabularyGroupID', $query);
        }
        if($dbVer==1 && $dbVerSub<4 && $system->settings->get('sys_dbSubSubVersion')<6){
            $query = str_replace('trm_OrderInBranch', '0 as trm_OrderInBranch', $query);
        }

        if ($dbID) { //if(trm_OriginatingDBID,concat(cast(trm_OriginatingDBID as char(5)),'-',cast(trm_IDInOriginatingDB as char(5))),'null') as trm_ConceptID
            $query.= " if(trm_OriginatingDBID, concat(cast(trm_OriginatingDBID as char(5)),'-',cast(trm_IDInOriginatingDB as char(5))), concat('$dbID-',cast(trm_ID as char(5)))) as trm_ConceptID";
        } else {
            $query.= " if(trm_OriginatingDBID, concat(cast(trm_OriginatingDBID as char(5)),'-',cast(trm_IDInOriginatingDB as char(5))), '') as trm_ConceptID";
        }
        $query.= " from defTerms order by trm_Domain, trm_Label";

        $res = $mysqli->query($query);

        $terms = array('termsByDomainLookup' =>  array('relation' => array(),
            'enum' => array()),
            'commonFieldNames' => array_slice(__getTermColNames(), 1),
            'fieldNamesToIndex' => __getColumnNameToIndex(array_slice(__getTermColNames(), 1)));

        if($res){

            while ($row = $res->fetch_row()) {
                $domain = $row[9];
                //array_push($row, false);

                if($domain=='relation' && HEURIST_UNITED_TERMS){
                    $terms['termsByDomainLookup']['enum'][$row[0]] = array_slice($row, 1);
                }
                $terms['termsByDomainLookup'][$domain][$row[0]] = array_slice($row, 1);
            }

            $res->close();
        }else{
            USanitize::errorLog(DATABASE.$system->dbname().'. Error retrieving terms '.$mysqli->error);
        }
        $terms['treesByDomain'] = array(
                'relation' => __getTermTree($system, "relation", "exact"),
                'enum' => __getTermTree($system, "enum", "exact"));


        $vcgGroups = array();//'groupIDToIndex' => array());
        $matches_refs = array();

        //see dbDefTerms->getTermLinks
        if($dbVer==1 && $dbVerSub>2){
            $query = 'SELECT trl_ParentID, trl_TermID FROM defTermsLinks ORDER BY trl_ParentID';
        }else{
            $query = 'SELECT trm_ParentTermID, trm_ID FROM defTerms ORDER BY trm_ParentTermID';
        }
        $res = $mysqli->query($query);

        $matches = array();
        if ($res){
            while ($row = $res->fetch_row()){

                if(@$matches[$row[0]]){
                    $matches[$row[0]][] = $row[1];
                }else{
                    $matches[$row[0]] = array($row[1]);
                }
            }
            $res->close();
            $terms['trm_Links'] = $matches;
        }

        if($dbVer==1 && $dbVerSub>2){
            //get vocabulary groups
            $query = 'SELECT vcg_ID, vcg_Name, vcg_Domain, vcg_Order, vcg_Description FROM defVocabularyGroups';
            $res = $mysqli->query($query);
            if($res){
                while ($row = $res->fetch_assoc()) {
                    $vcgGroups[$row['vcg_ID']] = $row;
                    //groupIDToIndex['groupIDToIndex'][$row["vcg_ID"]] = $index++;
                }
                $res->close();
            }else{
                USanitize::errorLog(DATABASE.$system->dbname().'. Error retrieving vocabulary groups '.$mysqli->error);
            }


            //terms by reference
            $query = 'select trl_ParentID,trl_TermID from defTermsLinks r, defTerms t '
            .'where trl_TermID=trm_ID AND trl_ParentID!=trm_ParentTermID ORDER BY trl_ParentID';
            $res = $mysqli->query($query);
            if ($res){
                while ($row = $res->fetch_row()){

                    if(@$matches_refs[$row[0]]){
                        $matches_refs[$row[0]][] = $row[1];
                    }else{
                        $matches_refs[$row[0]] = array($row[1]);
                    }
                }
                $res->close();
            }else{
                USanitize::errorLog(DATABASE.$system->dbname().'. Error retrieving terms by reference '.$mysqli->error);
            }

        }//$dbVer==1 && $dbVerSub>2
        else{
            $vcgGroups[1] = array('vcg_ID'=>1, 'vcg_Name'=>'General');
        }

        $terms['groups'] = $vcgGroups;
        $terms['references'] = $matches_refs;

        //ARTEM setCachedData($cacheKey, $terms);
        return $terms;

    }

    // to public method ------>

    /**
    * returns array list of all terms under a given term
    * @param     int $termID
    * @param     boolean $getAllDescentTerms determines whether to recurse and retrieve children of children (default = true)
    * @return    array  of term IDs
    */
    function getTermOffspringList($mysqli, $termID, $parentlist = null) {

        if($parentlist==null) {$parentlist = array($termID);}
        $offspring = array();
        if (!isPositiveInt($termID)) {
             return $offspring;
        }

        $res = $mysqli->query("select trm_ID from defTerms where trm_ParentTermID=".intval($termID));
        if (!($res && $res->num_rows>0)) { //child nodes exist
            return $offspring;
        }

        $emailsent = false;

        while ($row = $res->fetch_assoc()) { // for each child node

            $subTermID = $row['trm_ID'];
            if(array_search($subTermID, $parentlist)===false){
                array_push($offspring, $subTermID);
                array_push($parentlist, $subTermID);
                $offspring = array_merge($offspring, getTermOffspringList($mysqli, $subTermID, $parentlist));

            }elseif(!$emailsent){

                    $dbname = htmlspecialchars(mysql__select_value($mysqli, 'SELECT database() AS the_db'));
                    $sMsg = 'DATABASE '.$dbname.'. Recursion in parent-term hierarchy '.$termID.'  '.$subTermID;

                    $emailsent = true;
                    $dbowner = user_getDbOwner($mysqli);//info about user #2

                    sendEmail(HEURIST_MAIL_TO_ADMIN, 'CORRUPTED DATABASE '.$dbname
                    .', owner '.@$dbowner['ugr_FirstName'].' '.@$dbowner['ugr_LastName'].' '.@$dbowner['ugr_eMail'],
                    'db: '.$dbname.'\nOwner:'.@$dbowner['ugr_eMail']
                    .'\nUser was unable to load the database due to a corrupted terms tree. '
                    .'Recursion in parent-term hierarchy '.$termID.'  '.$subTermID);
            }
        }


        return $offspring;
    }


    //
    // Finds real vocabulary for given term
    //
    function getTermTopMostParent($mysqli, $termId, $terms=null){

        if(!$terms) {$terms = array($termId);}//to prevent recursion

        $query = "select trm_ParentTermID from defTerms where trm_ID = ".$termId;

        $parentId = mysql__select_value($mysqli, $query);

        if($parentId>0){

            if(in_array($parentId, $terms)){ //avoid recursion
                return $termId;
            }else{
                array_push($terms, $parentId);
                return getTermTopMostParent($mysqli, $parentId, $terms);
            }
        }else{
            return $termId;
        }
    }


    /**
    * return all terms's children as a plain array
    *
    * @param mixed $system
    */
    function getTermChildren($parentID, $system, $firstlevel_only){

        $mysqli = $system->get_mysqli();
        $children = array();

        $query = 'select trm_ID from defTerms where trm_ParentTermID = ' . intval($parentID);
        $res = $mysqli->query($query);
        if ($res) {
            while ($row = $res->fetch_row()) {
                array_push($children, $row[0]);
                if(!$firstlevel_only){
                    $children = array_merge($children, getTermChildren($row[0], $system, false));
                }
            }
        }

        return $children;

    }

    //
    // get inverse term and all its children terms
    //
    function getTermInverseAll($mysqli, $parent_ids, $all_levels=true){

        //compose query
        $query = 'SELECT trm_InverseTermID FROM defTerms WHERE '
            .predicateId('trm_ID', $parent_ids);

        $ids = mysql__select_list2($mysqli, $query, 'intval');
        if(is_array($ids) && !empty($ids)){
            return array_merge($ids, getTermChildrenAll($mysqli, $ids, $all_levels));
        }else{
            return array();
        }

    }

    //
    // get all children including by reference as a flat array
    //
    function getTermChildrenAll($mysqli, $parent_ids, $all_levels=true){

        //compose query
        $query = 'SELECT trl_TermID FROM defTermsLinks WHERE '
                .predicateId('trl_ParentID', $parent_ids);

        $ids = mysql__select_list2($mysqli, $query, 'intval');

        if($all_levels && !empty($ids)){
            $ids = array_merge($ids, getTermChildrenAll($mysqli, $ids, true));
        }

        return $ids;
    }

    /**
    * prints term label including parents term labels
    *
    * @param mixed $dtTerms
    * @param mixed $term
    * @param mixed $domain
    * @param mixed $withVocab
    * @param mixed $parents
    */
    function getTermFullLabel($dtTerms, $term, $domain, $withVocab, $parents=null){

        $fi = $dtTerms['fieldNamesToIndex'];
        $parent_id = $term[ $fi['trm_ParentTermID'] ];

        $term_label = $term[ $fi['trm_Label']];

        if(!isPositiveInt($parent_id)){
            return $term_label;
        }
        $term_parent = @$dtTerms['termsByDomainLookup'][$domain][$parent_id];
        if(!$term_parent){
            return $term_label;
        }

        $parent_label = '';

        if(!$withVocab){
            $parent_id = $term_parent[ $fi['trm_ParentTermID'] ];
            if(!isPositiveInt($parent_id)){
                return $term_label;
            }
        }

        if($parents==null){
            $parents = array();
        }

        if(array_search($parent_id, $parents)===false){
            array_push($parents, $parent_id);

            $parent_label = getTermFullLabel($dtTerms, $term_parent, $domain, $withVocab, $parents);
            if($parent_label) {$parent_label = $parent_label.'.';}
        }

        return $parent_label.$term_label;
    }

    //
    // get tree for domain
    //
    function getTermListAll($mysqli, $termDomain){

        $terms = array();
        $res = $mysqli->query('SELECT * FROM defTerms
            where (trm_Domain="'.$termDomain.'") and (trm_ParentTermId=0 or trm_ParentTermId is NULL)');

        if ($res && $res->num_rows) { //child nodes exist
            while ($row = $res->fetch_assoc()) { // for each child node
                array_push($terms, $row['trm_ID']);
                //ARTEM: trm_ChildCount is not reliable   }$row['trm_ChildCount'] > 0 && $getAllDescentTerms) {
                $terms = array_merge($terms, getTermOffspringList( $mysqli, $row['trm_ID'] ));
            }
        }else{
        }
        return $terms;
    }


    //
    //
    //
    function getTermLabels($mysqli, $termIDs) {
        $labels = array();
        if ($termIDs) {
            $labels = mysql__select_assoc2($mysqli,
            'select trm_ID, LOWER(trm_Label) from defTerms where trm_ID in ('.implode(',', $termIDs).')');
        }
        return $labels;
    }

    //
    //
    //
    function getTermCodes($mysqli, $termIDs) {
        $labels = array();
        if ($termIDs) {
            $labels = mysql__select_assoc2($mysqli,
            'select trm_ID, LOWER(trm_Code) from defTerms where trm_ID in ('.implode(',', $termIDs).')');
        }
        return $labels;
    }
    //
    // find tree in term tree
    // return branch with childs
    //
    function getTermInTree($term_id){

        global $terms;

        $res = null;

        if($term_id>0){

            $domain = 'enum';
            $term = @$terms['termsByDomainLookup']['enum'][$term_id];
            if(null==$term){
                $domain = 'relation';
                $term = @$terms['termsByDomainLookup']['relation'][$term_id];
            }
            if(null!=$term){
                  $res = getTermInTree2($terms['treesByDomain'][$domain], $term_id);
            }
        }
        return $res;
    }

    function getTermInTree2($tree, $term_id){

        if(@$tree[$term_id]){
            return $tree[$term_id];
        }else{

            foreach($tree as $pID => $children){
                if(!isEmptyArray($children)){
                    $res = getTermInTree2($children, $term_id);
                    if($res!=null){
                        return $res;
                    }
                }
            }
        }
        return null;
    }

    //
    // to public method - @todo replace with $terms->getTermByLabel
    //
    function getTermByLabel($term_label, $domain)
    {
        global $terms;

        $idx = $terms['fieldNamesToIndex']['trm_Label'];

        $list = $terms['termsByDomainLookup'][$domain];
        foreach($list as $term_id => $term){
            if(strcasecmp($term_label, $term[$idx][0])==0){
                return $term_id;
            }
        }

        return null;
    }

    //
    // to public method (NOT USED)
    //
    function getTermByCode($term_code, $domain)
    {
        global $terms;

        $idx = $terms['fieldNamesToIndex']['trm_Code'];

        $list = $terms['termsByDomainLookup'][$domain];
        foreach($list as $term_id => $term){
            if(strcasecmp($term_code, $term[$idx])==0){
                return $term_id;
            }
        }

        return null;
    }

    //
    //
    //
    function getTermById($term_id, $field='trm_Label'){

        global $terms;

        if($term_id>0){

            $term = @$terms['termsByDomainLookup']['enum'][$term_id];
            if(null==$term){
                $term = @$terms['termsByDomainLookup']['relation'][$term_id];
            }

            if($term){

                $idx = @$terms['fieldNamesToIndex'][$field];

                if($idx>=0){
                    return $term[$idx];
                }else{
                    return $term;
                }
            }
        }

        return null;
    }


    //
    // get term ids from json string - parse values in dty_JsonTermIDTree dty_TermIDTreeNonSelectableIDs
    //
    // similar functions are in importRectype
    function getTermsFromFormat( $formattedStringOfTermIDs ) {

        if (!$formattedStringOfTermIDs || $formattedStringOfTermIDs == "") {
            return array();
        }

        if (strpos($formattedStringOfTermIDs,"{")!== false) {
            $temp = preg_replace("/[\{\}\",]/","",$formattedStringOfTermIDs);
            if (strrpos($temp,":") == strlen($temp)-1) {
                $temp = substr($temp,0, strlen($temp)-1);
            }
            $termIDs = explode(":",$temp);
        } else {
            $temp = preg_replace("/[\[\]\"]/","",$formattedStringOfTermIDs);
            $termIDs = explode(",",$temp);
        }
        return $termIDs;
    }

    //-----------------------------------------------------------------------------------------------
    // INTERNAL FUNCTIONS


    /**
    * return array of recType table column names
    */
    function __getRectypeColNames() {
        return array("rty_Name", "rty_OrderInGroup", "rty_Description", "rty_TitleMask", "rty_CanonicalTitleMask", "rty_Plural",
            "rty_Status", "rty_OriginatingDBID", "rty_NameInOriginatingDB", "rty_IDInOriginatingDB", "rty_NonOwnerVisibility",
            "rty_ShowInLists", "rty_RecTypeGroupID", "rty_RecTypeModelIDs", "rty_FlagAsFieldset",
            "rty_ReferenceURL", "rty_AlternativeRecEditor", "rty_Type", "rty_ShowURLOnEditForm", "rty_ShowDescriptionOnEditForm",
            "rty_Modified", "rty_LocallyModified", "rty_ConceptID");
    }
    /**
    * get name to index map for columns
    * @param     array $columns array of strings
    * @return    object string => index lookup
    */
    function __getColumnNameToIndex($columns) {
        $columnsNameIndexMap = array();
        $index = 0;
        foreach ($columns as $columnName) {
            $columnsNameIndexMap[$columnName] = $index++;
        }
        return $columnsNameIndexMap;
    }
    function __getRectypeStructureFieldColNames() {
        return array("rst_DisplayName", "rst_DisplayHelpText", "rst_DisplayExtendedDescription", "rst_DisplayOrder",
            "rst_DisplayWidth", "rst_DisplayHeight",
            "rst_DefaultValue", "rst_RecordMatchOrder", "rst_CalcFunctionID", "rst_RequirementType", "rst_NonOwnerVisibility",
            "rst_Status", "rst_OriginatingDBID", "rst_MaxValues", "rst_MinValues", "rst_DisplayDetailTypeGroupID",
            "rst_FilteredJsonTermIDTree", "rst_PtrFilteredIDs", "rst_CreateChildIfRecPtr", "rst_PointerMode",
            "rst_PointerBrowseFilter", "rst_OrderForThumbnailGeneration", "rst_TermIDTreeNonSelectableIDs",
            "rst_Modified", "rst_LocallyModified", "rst_SemanticReferenceURL", "rst_TermsAsButtons", "dty_TermIDTreeNonSelectableIDs", "dty_FieldSetRectypeID", "dty_Type", "dty_ConceptID");
    }

    /**
    * attaches a child branch to a parent, recursively calling itself to build the childs subtree
    * @param     int $parentIndex id of parent term to attach child branch to
    * @param     int $childIndex id of child branch in current array
    * @param     mixed $terms the array of branches to build the tree
    * @param     array $parents of current branch to avoid recursion
    * @return    object $terms
    */
    function __attachChild($system, $parentIndex, $childIndex, $terms, $parents) {

        if (!array_key_exists($childIndex, $terms)) {//check if this child is parent itself
            return $terms;
        }

        if(isEmptyArray($terms[$childIndex])) { //no children
            $terms[$parentIndex][$childIndex] = null;
            unset($terms[$childIndex]);
            return $terms;
        }


            if($parents==null){
                $parents = array($childIndex);
            }else{
                array_push($parents, $childIndex);
            }

            $emailsent = false;

            foreach ($terms[$childIndex] as $gChildID => $n) { //loop for his children
                if ($gChildID == null) {
                    continue;
                }

                    if(array_search($gChildID, $parents)===false){
                        $terms = __attachChild($system, $childIndex, $gChildID, $terms, $parents);//depth first recursion
                    }else{
                        $dbname = $system->dbname();
                        $sMsg = 'Recursion in '.$dbname.'.defTerms! Tree '.implode('>',$parents)
                                .'. Cannot add term '.$gChildID;
                        USanitize::errorLog($sMsg);
                        if(!$emailsent){
                            $dbowner = user_getDbOwner($system->get_mysqli());//info about user #2
                            $emailsent = true;

                            sendEmail(HEURIST_MAIL_TO_ADMIN, 'CORRUPTED DATABASE '.$dbname,
                                'db: '.$dbname.'\nOwner:'.@$dbowner['ugr_eMail']
                                .'User was unable to load the database due to a corrupted terms tree. '
                                .'Recursion in parent-term hierarchy. Parent:'.implode('>',$parents)
                                .'  Child:'.$gChildID);
                        }
                    }
            } //foreach

            $terms[$parentIndex][$childIndex] = $terms[$childIndex];
            unset($terms[$childIndex]);

        return $terms;
    }

    /**
    * build a tree of term ids for a given domain name or names (prefix or postfix subdomain naming)
    * @global    type description of global variable usage in a function
    * @staticvar type [$varname] description of static variable usage in function
    * @param     string $termDomain the term domain to build
    * @param     string $matching indicates whether the domain name is complete or portion of the domain
    * @return    mixed
    * @uses      __attachChild()
    */
    function __getTermTree($system, $termDomain, $matching = 'exact') { // termDomain can be empty, 'reltype' or 'enum' or any future term use domain defined in the trm_Domain enum
        $mysqli = $system->get_mysqli();

        if($termDomain=='enum' && HEURIST_UNITED_TERMS){
            $whereClause = '1=1';
        }else{
            $whereClause = "a.trm_Domain " . ($matching == 'prefix' ? " like '" . $termDomain . "%' " : ($matching == 'postfix' ? " like '%" . $termDomain . "' " : "='" . $termDomain . "'"));
        }
        $query = "select a.trm_ID as pID, b.trm_ID as cID
        from defTerms a
        left join defTerms b on a.trm_ID = b.trm_ParentTermID
        where $whereClause
        order by a.trm_Label, b.trm_Label";

        $res = $mysqli->query($query);
        $terms = array();
        // create array of parent => child arrays
        while ($row = $res->fetch_assoc()) {
            if (!@$terms[$row["pID"]]) {
                $terms[$row["pID"]] = array();
            }
            if ($row['cID']) {//insert child under parent
                $terms[$row["pID"]][$row['cID']] = array();
            }
        }//we have all the branches, now lets build a tree
        $res->close();

        foreach ($terms as $parentID => $childIDs) {
            foreach ($childIDs as $childID => $n) {
                //check that we have a child branch
                if ($childID != null && array_key_exists($childID, $terms)) {
                    if (!isEmptyArray($terms[$childID])) {//yes then attach it and it's children's branches
                        $terms = __attachChild($system, $parentID, $childID, $terms, null);
                    } else {//no then it's a leaf in a branch, remove this redundant node.
                        unset($terms[$childID]);
                    }
                }
            }
        }

        return $terms;
    }

    /**
    * return array of term table column names
    */
    function __getTermColNames() {
        return array("trm_ID", "trm_Label", "trm_InverseTermID", "trm_Description", "trm_Status", "trm_OriginatingDBID",
            //                    "trm_NameInOriginatingDB",
            "trm_IDInOriginatingDB", "trm_AddedByImport", "trm_IsLocalExtension", "trm_Domain", "trm_OntID", "trm_ChildCount",
            "trm_ParentTermID", "trm_Depth", "trm_Modified", "trm_LocallyModified", "trm_Code",
            "trm_SemanticReferenceURL", "trm_VocabularyGroupID", "trm_OrderInBranch", "trm_NameInOriginatingDB", "trm_ConceptID" );
    }

    //
    //-----------------------------------------------------------------------------------------------
    // DETAILS


    /**
    * get rectype usage by detailTypeID
    * rectypesByDetailType = { dtyID => [rtyID,...], ...}
    * @return    object rtyIDs (array) using detailType indexed by dtyID
    */
    function getDetailTypeDefUsage($mysqli) {
        $rectypesByDetailType = array();
        $res = $mysqli->query("select rst_DetailTypeID as dtID, rst_RecTypeID as rtID
        from defRecStructure order by dtID, rtID");
        while ($row = $res->fetch_assoc()) {
            if (!array_key_exists($row['dtID'], $rectypesByDetailType)) {
                $rectypesByDetailType[$row['dtID']] = array();
            }
            array_push($rectypesByDetailType[$row['dtID']], $row["rtID"]);
        }
        $res->close();
        return $rectypesByDetailType;
    }
    /**
    * get usage counts of rectypes from the record table
    * usageCount = {rtyID:nonzero-count,...}
    * @return    object non-zero usage counts indexed by rtyID
    */
    function getRecTypeUsageCount($mysqli) {
        $recCountByRecType = array();
        $res = $mysqli->query("select rty_ID as rtID, count(rec_ID) as usageCnt
            from Records left join defRecTypes on rty_ID = rec_RecTypeID
        group by rec_RecTypeID");
        while ($row = $res->fetch_assoc()) {
            $recCountByRecType[$row['rtID']] = $row["usageCnt"];
        }
        $res->close();
        return $recCountByRecType;
    }
    //-----------------------------------------------------------------------------------------------

    /**
    * returns an array of detailType structured information for all detailTypes
    *     detailTypes = {"groups" => {"groupIDToIndex":{detailTypeGroupID:index},
    *                                       index:{propName:val,...},...},
    *                    "names":{dtyID:name,...},
    *                    "rectypeUsage" => { dtyID => [rtyID,...], ...},
    *                    'usageCount' => {dtyID:nonzero-count,...},
    *                    "typedefs":{"commonFieldNames":[defDetailTypes Column names],
    *                                "commonNamesToIndex":{"defDetailTypes columnName":index,...},
    *                                dtyID:{"commonFields":[val,....]},
    *                                ...},
    *                    'lookups' => {basetype=>displayName);
    *
    * @global    int $dbID databse ID
    * @param     boolean $useCachedData if true does a lookup for the detailtypes structure in cache
    * @return    object information describing all the detailtypes defined in the database
    * @uses      getDetailTypeColNames()
    * @uses      __getColumnNameToIndex()
    * @uses      __getRectypeStructureFieldColNames()
    * @uses      getDetailTypeGroups()
    * @uses      getDetailTypeUsageCount()
    * @uses      getDetailTypeDefUsage()
    * @uses      dbs_GetDtLookups()
    * @uses      getCachedData()
    * @uses      setCachedData()
    *
    * $dettypeids null means all, otherwise comma separated list of ids  or list of field types (numeric,blocktext etc)
    * $imode  0 - only names and groupnames
    *         1 - only structure
    *         2 - full, both headers and structures
    *         3 - ids only
    *         4 - assoc dty_ID => dty_Type
    */
    function dbs_GetDetailTypes($system, $dettypeids=null, $imode=2){

        $mysqli = $system->get_mysqli();
        $dbID = $system->settings->get('sys_dbRegisteredID');

        /*  ARTEM
        global $mysqli, $dbID;
        $cacheKey = DATABASE . ":AllDetailTypeInfo";
        if ($useCachedData) {
        $dtStructs = getCachedData($cacheKey);
        if ($dtStructs) {
        return $dtStructs;
        }
        }
        */

        $dtStructs = array();

        if($imode==0 || $imode==2){
            $dtG = getDetailTypeGroups($mysqli);
            $dtStructs['groups'] = $dtG;
            $dtStructs['names'] = array();
        }
        if($imode==1 || $imode==2){
            $dtStructs['typedefs'] = array('commonFieldNames' => getDetailTypeColNames(),
                'fieldNamesToIndex' => __getColumnNameToIndex(getDetailTypeColNames()));
            $dtStructs['lookups'] = dbs_GetDtLookups();
        }
        if($imode==2){
            $dtStructs['rectypeUsage'] = getDetailTypeDefUsage($mysqli);
            //$dtStructs['usageCount']   = getDetailTypeUsageCount($mysqli);
        }

        $where_exp = null;
        if($dettypeids!=null || $dettypeids!='' && $dettypeids!='all'){
            if(!is_array($dettypeids)){
                $dettypeids = array($dettypeids);
            }
            if($dettypeids[0]!='all'){
                //detect ID or TYPE
                if(is_numeric($dettypeids[0])){
                    $dettypeids = prepareIds($dettypeids);
                    $where_exp = ' dty_ID in ('.implode(',',$dettypeids).')';
                }else{
                    escapeValues($mysqli, $dettypeids);
                    $where_exp = ' dty_Type in (\''.implode("','",$dettypeids).'\')';
                }
            }
        }

        if($imode==3){ //ids only
            //$query = "select dty_ID from defDetailTypes";
            if($where_exp==null){
                $where_exp = '';
            }
            $res = mysql__select_list($mysqli, 'defDetailTypes', 'dty_ID', $where_exp);
            return $res;
        }elseif($imode==4){ //dty_ID => dty_Type

            $query = 'SELECT dty_ID, dty_Type FROM defDetailTypes';
            if($where_exp!=null && $where_exp != ''){
                $query = $query.SQL_WHERE.$where_exp;
            }

            $res = mysql__select_assoc2($mysqli, $query);
            return $res;

        }else{

            $query = "select dtg_ID, dtg_Name, " . join(",", getDetailTypeColNames());
            $query = str_replace('dty_ConceptID', '', $query);

            if ($dbID) { //if(trm_OriginatingDBID,concat(cast(trm_OriginatingDBID as char(5)),'-',cast(trm_IDInOriginatingDB as char(5))),'null') as trm_ConceptID
                $query.= " if(dty_OriginatingDBID, concat(cast(dty_OriginatingDBID as char(5)),'-',cast(dty_IDInOriginatingDB as char(5))), concat('$dbID-',cast(dty_ID as char(5)))) as dty_ConceptID";
            } else {
                $query.= " if(dty_OriginatingDBID, concat(cast(dty_OriginatingDBID as char(5)),'-',cast(dty_IDInOriginatingDB as char(5))), '') as dty_ConceptID";
            }
            $query.= " from defDetailTypes left join defDetailTypeGroups  on dtg_ID = dty_DetailTypeGroupID";
            if($where_exp!=null){
                $query = $query.SQL_WHERE.$where_exp;
            }
            $query = $query . " order by dtg_Order, dtg_Name, dty_OrderInGroup, dty_Name";
        }
        $res = $mysqli->query($query);

        try{
            if(!$res){
                USanitize::errorLog('Database: '.$system->dbname().'FAILED QUERY: '.$mysqli->error);
            }else{
                while ($row = $res->fetch_row()) {
                    $dty_ID = $row[2];
                    if($imode==0 || $imode==2){
                        $dtg_ID = $row[0];
                        $idx = @$dtStructs['groups']['groupIDToIndex'][$dtg_ID];
                        if(!@$dtStructs['groups'][$idx]){
                            $idx = 0; //orphaned detail without group
                            $row[11] = $dtStructs['groups'][0]['id'];
                        }
                        $dty_ShowInLists = $row[17];
                        array_push($dtStructs['groups'][$idx]['allTypes'], $dty_ID);
                        if ($dty_ShowInLists) {// dty_ShowInLists
                            array_push($dtStructs['groups'][$idx]['showTypes'], $dty_ID);
                        }

                        $dtStructs['names'][$dty_ID] = $row[3];
                    }
                    $dtStructs['typedefs'][$dty_ID]['commonFields'] = array_slice($row, 2);
                }
            }
        }catch(Exception $e) {
            //trying to find weird error - missed trm_Modified column
            USanitize::errorLog('Message: ' .$e->getMessage());
            USanitize::errorLog('QUERY: '.$query);
            USanitize::errorLog('Database: '.$system->dbname());
        }

        //SPECIAL CASE for relation type #6
        if($imode>0){
            if($system->defineConstant('DT_RELATION_TYPE') && @$dtStructs['typedefs'][DT_RELATION_TYPE]){
                $idx = $dtStructs['typedefs']['fieldNamesToIndex']['dty_JsonTermIDTree'];
                $dtStructs['typedefs'][DT_RELATION_TYPE]['commonFields'][$idx] = 0;
                $idx = $dtStructs['typedefs']['fieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];
                $dtStructs['typedefs'][DT_RELATION_TYPE]['commonFields'][$idx] = '';
            }
        }

        //ARTEM setCachedData($cacheKey, $dtStructs);

        return $dtStructs;
    }

    /**
    * return record structure for given id and update $recstructures array
    */
    function getDetailType($system, &$detailtypes, $dtyID)
    {
        if (!@$detailtypes[$dtyID] ) {
            //load
            $res = dbs_GetDetailTypes($system, $dtyID, 1);
            if(!@$detailtypes['fieldNamesToIndex']){
                $detailtypes['fieldNamesToIndex'] = $res['typedefs']['fieldNamesToIndex'];
            }
            $detailtypes[$dtyID] = @$res['typedefs'][$dtyID]['commonFields'];
        }
        return @$detailtypes[$dtyID];
    }
    /**
    * get detailType Group definitions
    * groups = {"groupIDToIndex":{detailTypeGroupID:index},
    *           index:{propName:val,...},...}
    * @return    object detailTypeGroup definitions as array of prop:val pairs indexed by dtgID
    */
    function getDetailTypeGroups($mysqli) {
        $dtGroups = array('groupIDToIndex' => array());
        $index = 0;
        $res = $mysqli->query("select * from defDetailTypeGroups order by dtg_Order, dtg_Name");
        while ($row = $res->fetch_assoc()) {
            array_push($dtGroups, array('id' => $row["dtg_ID"], 'name' => $row["dtg_Name"], 'order' => $row["dtg_Order"], 'description' => $row["dtg_Description"], 'allTypes' => array(), 'showTypes' => array()));
            $dtGroups['groupIDToIndex'][$row["dtg_ID"]] = $index++;
        }
        $res->close();
        return $dtGroups;
    }
    /**
    * get detailType usage counts
    * @return    array usage counts index by detailTypeID
    * @link      URL
    */
    function getDetailTypeUsageCount($mysqli) {
        $useCntByDetailTypeID = array();
        $res = $mysqli->query("select dty_ID as dtID, count(dtl_ID) as usageCnt ".
            " from recDetails left join defDetailTypes on dty_ID = dtl_DetailTypeID".
            " group by dtl_DetailTypeID");
        while ($row = $res->fetch_assoc()) {
            $useCntByDetailTypeID[$row['dtID']] = $row["usageCnt"];
        }
        $res->close();
        return $useCntByDetailTypeID;
    }
    /**
    * return array of defDetailType table column names
    */
    function getDetailTypeColNames() {
        return array("dty_ID", "dty_Name", "dty_Documentation", "dty_Type", "dty_HelpText", "dty_ExtendedDescription", "dty_Status",
            "dty_OriginatingDBID", "dty_IDInOriginatingDB", "dty_DetailTypeGroupID", "dty_OrderInGroup", "dty_JsonTermIDTree",
            "dty_TermIDTreeNonSelectableIDs", "dty_PtrTargetRectypeIDs", "dty_FieldSetRectypeID", "dty_ShowInLists",
            "dty_NonOwnerVisibility", "dty_Modified", "dty_LocallyModified", "dty_EntryMask", "dty_NameInOriginatingDB",
            "dty_SemanticReferenceURL", "dty_ConceptID");
    }
    /**
    * get map for internal storage base datatype names to Human readable interface names.
    * note this must match defDetailTypes table's enumeration for the dty_Type column
    * @return    object lookup from basetype to Display name
    * @todo      compare this against table for complete list logging error if mismatch
    */
    function dbs_GetDtLookups() {
        return array(
            "enum" => "Terms list",
            "float" => "Numeric",
            "date" => "Date / temporal",
            "file" => "File",
            "geo" => "Geospatial",
            "freetext" => "Text (single line)",
            "blocktext" => "Memo (multi-line)",
            "resource" => "Record pointer",
            "relmarker" => "Relationship marker",
            "separator" => "Heading (no data)",
            "calculated" => "Calculated",
            // Note=> the following types are no longer deinable but may be required for backward compatibility
            "relationtype" => "Relationship type",
            //"fieldsetmarker" => "Field set marker",
            "integer" => "Numeric - integer",
            "year" => "Year (no mm-dd)",
            //"urlinclude" => "File/URL of include content",
            "boolean" => "Boolean (T/F)");
    }

    //
    // utility
    //
    function removeLastNum($name){

        $k = strrpos($name," ");//find last space

        if( $k>0 && is_numeric(substr($name, $k+1)) ){
            $name = substr($name,0,$k);
        }
        return $name;
    }
?>
