<?php
// db_rel_details_temp.php
//legacy of h3 - used in reportRecord and renderRecordData
//@todo ??? 1) use recLinks  2) move to recordSearch use recordGetRelationship?

global $inverses;

/**
* get related record data for a given relationship record ID
* related = { "recID" => recID,
*             "RelTermID" => relationTrmID,
*             "RelTerm" => trmLabel,
*             "ParentTermID" => parentTrmID,
*             "RelatedRecID" => linkedRecID,
*             "InterpRecID" => interpretationRecID,
*             "Notes" => relationshipNotes,
*             "Title" => relationshipTitle,
*             "StartData" => relationshipStartDate,
*             "EndDate" => relationshipEndDate}
*
* @global    int $relTypDT local id of relationtype detailType (magic number)
* @global    int $relSrcDT local id of source record pointer detailType (magic number)
* @global    int $relTrgDT local id of target record pointer detailType (magic number)
* @global    int $intrpDT local id of interpretation record pointer detailType (magic number)
* @global    int $notesDT local id of notes detailType (magic number)
* @global    int $startDT local id of start time detailType (magic number)
* @global    int $endDT local id of end time detailType (magic number)
* @global    int $titleDT local id of title detailType (magic number)
* @param     int $recID relationshipRecID to use for related
* @param     boolean $i_am_primary true if context is "from" record of relationship link
* @return    object related record structure
* @todo      change $i_am_primary to useInverseRelation
*/
function fetch_relation_details($system, $recID, $i_am_primary) {

    $relRT = ($system->defineConstant('RT_RELATION')?RT_RELATION:0);
    $relTypDT = ($system->defineConstant('DT_RELATION_TYPE')?DT_RELATION_TYPE:0);
    $relSrcDT = ($system->defineConstant('DT_PRIMARY_RESOURCE')?DT_PRIMARY_RESOURCE:0);
    $relTrgDT = ($system->defineConstant('DT_TARGET_RESOURCE')?DT_TARGET_RESOURCE:0);
    $intrpDT = ($system->defineConstant('DT_INTERPRETATION_REFERENCE')?DT_INTERPRETATION_REFERENCE:0);
    $notesDT = ($system->defineConstant('DT_SHORT_SUMMARY')?DT_SHORT_SUMMARY:0);
    $startDT = ($system->defineConstant('DT_START_DATE')?DT_START_DATE:0);
    $endDT = ($system->defineConstant('DT_END_DATE')?DT_END_DATE:0);
    $titleDT = ($system->defineConstant('DT_NAME')?DT_NAME:0);

    //global $system, $relTypDT, $relSrcDT, $relTrgDT, $intrpDT, $notesDT, $startDT, $endDT, $titleDT;

    /* get recDetails for the given linked resource and extract all the necessary values */
    $mysqli = $system->getMysqli();
    $res = $mysqli->query('select * from recDetails where dtl_RecID = ' . intval($recID));

    $bd = array('recID' => $recID);
    if($res){

        $query_select = 'select rec_ID, rec_Title, rec_RecTypeID, rec_URL from Records where rec_ID = ';

        while ($row = $res->fetch_assoc()) {

        switch ($row['dtl_DetailTypeID']) {
            case $relTypDT: //saw Enum change - added RelationValue for UI
                if ($i_am_primary) {
                    $bd['RelTermID'] = $row['dtl_Value'];
                } else {
                    $bd['RelTermID'] = reltype_inverse($system, $row['dtl_Value']);// BUG: assumes reltype_inverse returns ID
                    //TODO: saw this should have a -1 which is different than self inverse and the RelTerm should be "inverse of ".
                }
                $relval = mysql__select_row_assoc($mysqli,
                        'select trm_Label, trm_ParentTermID from defTerms where trm_ID = ' . intval($bd['RelTermID']));
                if($relval!=null){
                    $bd['RelTerm'] = $relval['trm_Label'];
                    if ($relval['trm_ParentTermID']) {
                        $bd['ParentTermID'] = $relval['trm_ParentTermID'];
                    }
                }
                break;
            case $relTrgDT: // linked resource
                if (!$i_am_primary) {break;}

                $bd['RelatedRecID'] = mysql__select_row_assoc($mysqli,
                                    $query_select.intval($row['dtl_Value']) );
                break;
            case $relSrcDT:
                if ($i_am_primary) {break;}

                $bd['RelatedRecID'] = mysql__select_row_assoc($mysqli,
                                    $query_select.intval($row['dtl_Value']) );

                break;
            case $intrpDT:

                $bd['InterpRecID'] = mysql__select_row_assoc($mysqli,
                                    $query_select.intval($row['dtl_Value']) );

                break;
            case $notesDT:
                $bd['Notes'] = $row['dtl_Value'];
                break;
            case $titleDT:
                $bd['Title'] = $row['dtl_Value'];
                break;
            case $startDT:
                $bd['StartDate'] = $row['dtl_Value'];
                break;
            case $endDT:
                $bd['EndDate'] = $row['dtl_Value'];
                break;
            default;
        }
    }
        $res->close();
    }

    return $bd;
}

/**
* determine the inverse of a relationship term
* @global    array llokup of term inverses by trmID to inverseTrmID
* @param     int $relTermID reltionship trmID
* @return    int inverse trmID
* @todo      modify to retrun -1 in case not inverse defined
*/
function reltype_inverse($system, $relTermID) { //saw Enum change - find inverse as an id instead of a string

    global $inverses;

    $mysqli = $system->getMysqli();

    if (!$relTermID) {return;}
    if (!isset($inverses)) {
        $inverses = mysql__select_assoc2($mysqli,
                "SELECT A.trm_ID, B.trm_ID FROM defTerms A left join defTerms B on B.trm_ID=A.trm_InverseTermID"
                ." WHERE A.trm_Label is not null and B.trm_Label is not null");
    }
    $inverse = @$inverses[$relTermID];
    if (!$inverse) {$inverse = array_search($relTermID, $inverses);}//do an inverse search and return key.
    if (!$inverse) {$inverse = $relTermID;} //'Inverse of ' . FIXME: This should be -1 indicating no inverse found.
    return $inverse;
}
?>
