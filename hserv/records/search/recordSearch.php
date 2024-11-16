<?php
/**
* recordSearch.php
* Library to search records
*
* recordSearchMinMax - Find minimal and maximal values for given detail type and record type
* recordSearchFacets - returns counts for facets for given query
*
* recordSearchRelatedIds - search all related (links and releationship) records for given set of records recursively
* recordSearchRelated
* recordLinkedCount  - search count by target record type for given source type and base field
* recordSearchPermissions  - all view group permissions for given set of records
* recordGetOwnerVisibility - NOT USED returns sql where to check record visibility
* recordGetRelationshipType - returns only first relationship type ID for 2 given records
* recordGetRelationship - returns relrecord (RT#1) for given pair of records (id or full record)
* recordGetLinkedRecords - returns all linked record and their types (for update titles)
* recordSearchMenuItems - returns all CMS records for given CMS home record
* not implemented recordSearchMapDocItems - returns all layers and datasource records for given map document record
*
* recordSearchFindParent - find parent record for rec_ID with given record type
*
* recordSearch - MAIN method - parses query and searches for heurist records
* recordSearchByID - returns header (and details)
* recordSearchDetails - returns details for given rec id
* recordSearchGeoDetails - find geo in linked places
* recordSearchPersonalTags
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
use hserv\utilities\USystem;
use hserv\entity\DbsUsersGroups;
use hserv\structure\ConceptCode;
use hserv\entity\DbRecUploadedFiles;

require_once 'recordFile.php';//it includes UFile.php
require_once 'composeSql.php';
require_once dirname(__FILE__).'/../../structure/search/dbsData.php';
require_once dirname(__FILE__).'/../../structure/dbsTerms.php';
require_once dirname(__FILE__).'/../../utilities/Temporal.php';

define('MSG_SAVED_FILTER', 'Saved filter: ');
define('MSG_MEMORY_LIMIT', ' records are in result of search query. Memory limit does not allow to retrieve all of them. Please filter to a smaller set of results.');

define('SQL_RECDETAILS', ' FROM Records, recDetails WHERE rec_ID=dtl_RecID AND rec_FlagTemporary!=1 AND ');
define('SQL_RELMARKER_CONSTR', 'SELECT dty_ID, dty_JsonTermIDTree, dty_PtrTargetRectypeIDs FROM defDetailTypes WHERE dty_Type = "relmarker" AND ');

/**
* Find distinct detail values for for given detail type and record type
*
* @param mixed $system
* @param mixed $params - array  rt - record type, dt - detail type
*/
function recordSearchDistinctValue($system, $params){

    $mysqli = $system->get_mysqli();
    $all_records_for_rty = false; //if false - search for given set of record ids

    //0 unique, 1 -both, 2 - all values
    if(!@$params['mode']){
        $params['mode'] = 1;
    }
    $search_unique = (intval($params['mode'])<=1);
    $search_all = (intval($params['mode'])>=1);

    if(@$params['rec_IDs']){
        $rec_IDs = prepareIds($params['rec_IDs']);
        $total_cnt = count($rec_IDs);
        $offset = 0;
        if($total_cnt>0 && intval(@$params['dty_ID'])>0){

            if(intval(@$params['rty_ID'])>0){
                $query = 'SELECT count(rec_ID) FROM Records WHERE rec_FlagTemporary!=1 AND rec_RecTypeID='.intval($params['rty_ID']);
                $res = mysql__select_value($mysqli, $query);
                if(intval($res)==$total_cnt){
                    $all_records_for_rty = true;
                }
            }

            if(!$all_records_for_rty){

                $values_unique = array();
                $detail_count = 0;

                while ($offset<$total_cnt){

                    $rec_IDs_chunk = array_slice($rec_IDs, $offset, 1000);

                    if($search_unique){

                        $query = 'SELECT DISTINCT dtl_Value '
                        .SQL_RECDETAILS
                        .predicateId('rec_ID',$rec_IDs_chunk)
                        .SQL_AND
                        .predicateId('dtl_DetailTypeID',$params['dty_ID']);

                        $values = mysql__select_list2($mysqli, $query);

                        $values_unique = array_unique(array_merge($values_unique, $values));

                    }
                    if($search_all){
                        $query = 'SELECT count(dtl_ID) '
                        .SQL_RECDETAILS
                        .predicateId('rec_ID',$rec_IDs_chunk)
                        .SQL_AND
                        .predicateId('dtl_DetailTypeID',$params['dty_ID']);

                        $detail_count = $detail_count+mysql__select_value($mysqli, $query);
                    }

                    $offset = $offset+1000;
                }//while

                $response = array('status'=>HEURIST_OK, 'data'=> array('unique'=>count($values_unique),'total'=>$detail_count));
            }

        }else{
            $response = $system->addError(HEURIST_INVALID_REQUEST, 'Count query parameters are invalid');
        }
    }else{
        $all_records_for_rty = true;
    }

    if($all_records_for_rty){
        if(intval(@$params['rty_ID'])>0 && intval(@$params['dty_ID'])>0){

            $unique_count = 0;
            $detail_count = 0;

            if($search_unique){
                $query = 'SELECT COUNT(DISTINCT dtl_Value) '
                .SQL_RECDETAILS
                .predicateId('rec_RecTypeID',$params['rty_ID'])
                .SQL_AND
                .predicateId('dtl_DetailTypeID',$params['dty_ID']);

                $res = mysql__select_value($mysqli, $query);
                if ($res==null){
                    return $system->addError(HEURIST_DB_ERROR, 'Search query error on unique values count. Query '.$query, $mysqli->error);
                }
                $unique_count = intval($res);
            }
            if($search_all){
                $query = 'SELECT COUNT(dtl_ID) '
                .SQL_RECDETAILS
                .predicateId('rec_RecTypeID',$params['rty_ID'])
                .SQL_AND
                .predicateId('dtl_DetailTypeID',$params['dty_ID']);
                $res = mysql__select_value($mysqli, $query);
                if ($res==null){
                    return $system->addError(HEURIST_DB_ERROR, 'Search query error on details count. Query '.$query, $mysqli->error);
                }
                $detail_count = intval($res);
            }

            $response = array('status'=>HEURIST_OK, 'data'=> array('unique'=>$unique_count,'total'=>$detail_count));

        }else{
            $response = $system->addError(HEURIST_INVALID_REQUEST, 'Count query parameters are invalid');
        }
    }

    return $response;
}

//
// Returns count of matching records by given detail field
//         pairs of matching records
//
function recordSearchMatchedValues($system, $params){

    if(intval(@$params['dty_src'])>0 &&  //intval(@$params['rty_src'])>0 &&
    intval(@$params['rty_trg'])>0 && intval(@$params['dty_trg'])>0){
        $mysqli = $system->get_mysqli();


        $need_nonmatches = (@$params['nonmatch']==1); //non-match report
        $need_ids = (@$params['pairs']==1); //return pairs - otherwise just count

        $rec_IDs = prepareIds($params['rec_IDs']);

        $total_cnt = count($rec_IDs);
        $offset = 0;

        if($total_cnt>0){

            //'distinct d1.dtl_RecID, d2.dtl_RecID '
            //d1.dtl_Value, d2.dtl_Value,
            if($need_nonmatches || $need_ids){
                $result = array();
            }else{
                $result = 0;
            }

            $iteration = 1;
            $is_completed_without_error = true;

            while ($offset<$total_cnt){

                $rec_IDs_chunk = array_slice($rec_IDs, $offset, 500);

                if($need_nonmatches){

                    $query = 'select distinct d1.dtl_RecID, r1.rec_Title, d1.dtl_Value FROM Records r1, recDetails d1 '
                    .' LEFT JOIN recDetails d2 on d1.dtl_Value=d2.dtl_Value and d1.dtl_RecID!=d2.dtl_RecID'
                    .' LEFT JOIN Records r2 on d2.dtl_RecID=r2.rec_ID and r2.rec_RecTypeID='
                    .intval($params['rty_trg']).' and d2.dtl_DetailTypeID='.intval($params['dty_trg'])
                    .' WHERE r1.rec_ID IN ('
                    .implode(',',$rec_IDs_chunk).') and d1.dtl_DetailTypeID='
                    .intval($params['dty_src'])
                    .' and d1.dtl_RecID=r1.rec_ID and d2.dtl_Value is null';

                }else {
                    if($need_ids){
                        $query = 'select distinct d1.dtl_RecID, d2.dtl_RecID ';
                    }else{
                        $query = 'select count(distinct d1.dtl_RecID, d2.dtl_RecID) ';
                    }
                    $query = $query
                    .' from recDetails d1, recDetails d2, Records r2'   //Records r1,
                    .' where d1.dtl_RecID IN ('.implode(',',$rec_IDs_chunk).')'      //=r1.rec_ID and r1.rec_RecTypeID='.intval($params['rty_src'])
                    .' and d1.dtl_DetailTypeID='.intval($params['dty_src'])
                    .' and d2.dtl_RecID=r2.rec_ID and r2.rec_RecTypeID='.intval($params['rty_trg'])
                    .' and d2.dtl_DetailTypeID='.intval($params['dty_trg'])
                    .' and d1.dtl_RecID!=d2.dtl_RecID and d1.dtl_Value=d2.dtl_Value';
                }

                if($need_nonmatches){
                    $query .= ' ORDER BY d1.dtl_RecID';
                    $res = mysql__select_all($mysqli, $query, 0, 100);
                }elseif($need_ids){
                    $query .= ' ORDER BY d1.dtl_RecID';
                    $res = mysql__select_all($mysqli, $query);
                }else{
                    $res = mysql__select_value($mysqli, $query);
                }

                if ($res == null) {
                    if(is_array($res)){
                        //error_log('Empty array on interation '.$iteration);
                    }else{
                        $response = $system->addError(HEURIST_DB_ERROR, 'Search query error on matching values. '
                            .'<br> Records given: '.$total_cnt
                            .'<br> Iteration: '.$iteration
                            .'<br> Found so far '.(is_array($result)?count($result):$result)
                            //.'<br>Res: '.print_r($res,true)
                            .'<br>Query '.$query, $mysqli->error);
                        $is_completed_without_error = false;
                        break;
                    }
                }else{
                    if($need_nonmatches || $need_ids){
                        if(!empty($res)){
                            $result = array_merge($result, $res);
                        }
                    }else{
                        $result = $result + $res;
                    }
                }

                $offset = $offset+500;
                $iteration++;
            }//wile

            if ($is_completed_without_error){
                $response = array('status'=>HEURIST_OK, 'data'=> $result);
            }

        }else{
            $response = $system->addError(HEURIST_INVALID_REQUEST, 'Source records are not defined as matching query parameter');
        }
    }else{
        $response = $system->addError(HEURIST_INVALID_REQUEST, 'Matching query parameters are invalid');
    }

    return $response;
}


/**
* Find minimal and maximal values for given detail type and record type
*
* @param mixed $system
* @param mixed $params - array  rt - record type, dt - detail type
*/
function recordSearchMinMax($system, $params){

    if(intval(@$params['rt'])>0 && intval(@$params['dt'])>0){

        $mysqli = $system->get_mysqli();
        //$currentUser = $system->getCurrentUser();

        $query = 'SELECT MIN(CAST(dtl_Value as decimal)) as MIN, MAX(CAST(dtl_Value as decimal)) AS MAX '
        .SQL_RECDETAILS;

        $where_clause  = predicateId('rec_RecTypeID',$params['rt'])
        .SQL_AND
        .predicateId('dtl_DetailTypeID',$params['dt'])
        ." AND dtl_Value is not null AND dtl_Value!=''";

        $currUserID = $system->get_user_id();
        if( $currUserID > 0 ) {
            $q2 = 'select wss_RecID from usrWorkingSubsets where wss_OwnerUGrpID='.$currUserID.' LIMIT 1';
            if(mysql__select_value($mysqli, $q2)>0){
                $query = $query.', usrWorkingSubsets ';
                $where_clause = $where_clause.' AND wss_RecID=rec_ID AND wss_OwnerUGrpID='.$currUserID;
            }

        }
        //@todo - current user constraints

        //$res = $mysqli->query($query.$where_clause);
        $res = mysql__select($mysqli, $query.$where_clause);
        if (!$res){
            $response = $system->addError(HEURIST_DB_ERROR, "Search query error on min/max. Query ".$query, $mysqli->error);
        }else{
            $row = $res->fetch_assoc();
            if($row){
                $response = array("status"=>HEURIST_OK, "data"=> $row);
            }else{
                $response = array("status"=>HEURIST_NOT_FOUND);
            }
            $res->close();
        }

    }else{
        $response = $system->addError(HEURIST_INVALID_REQUEST, "MinMax query parameters are invalid");
    }

    return $response;
}

/**
* parses string  $resource - t:20 f:41
* and returns array of recordtype and detailtype IDs
*/
function _getRt_Ft($resource)
{
    if($resource){

        $vr = explode(" ", $resource);
        $resource_rt = substr($vr[0],2);
        $resource_field = $vr[1];
        if(strpos($resource_field,"f:")===0){
            $resource_field = substr($resource_field,2);
        }

        return array("rt"=>$resource_rt, "field"=>$resource_field);
    }

    return null;
}

//
// returns counts for facets for given query
//
//  1) It finds all possible facet values for current query
//  2) Calculates counts for every value
//
//
// @param mixed $system
// @param mixed $params - array or parameters
//      q - JSON query array
//      field - field id to search
//      type - field type (todo - search it dynamically with getDetailType)
//      needcount     2 - count for related records
// @return
//
function recordSearchFacets($system, $params){

    $ft_Select = 1;
    $ft_List = 2;
    $ft_Column = 3;

    $mysqli = $system->get_mysqli();

    //set savedSearchName for error messages
    $savedSearchName = '';
    if(is_numeric(@$params['qname']) && $params['qname'] > 0){ // retrieve extra details

        $query = 'SELECT svs_ID AS qID, svs_Name AS qName, svs_UGrpID as uID, ugr_Name as uName '
        . 'FROM usrSavedSearches '
        . 'INNER JOIN sysUGrps ON ugr_ID = svs_UGrpID '
        . 'WHERE svs_ID = ' . intval($params['qname']);

        $saved_search = mysql__select_row_assoc($mysqli, $query);

        if($saved_search !== null){
            $name = empty($saved_search['qName']) ? $saved_search['qID'] : $saved_search['qName'] . ' (# '. $saved_search['qID'] .')';
            $workgroup = $saved_search['uName'] . ' (# '. $saved_search['uID'] .')';//empty($saved_search['uName']) ? $saved_search['uID'] :
            $savedSearchName = '<br>'.MSG_SAVED_FILTER . $name . '<br>Workgroup: ' . $workgroup . '<br>';
        }else{
            $savedSearchName = MSG_SAVED_FILTER.$params['qname'].'<br>';
        }
    }else{
        $savedSearchName = @$params['qname'] ? MSG_SAVED_FILTER. $params['qname'] .'<br>' : '';
    }
    $savedSearchName .= empty($savedSearchName) ? '' : 'It is probably best to delete this saved filter and re-create it.<br>';

    $missingIds = false;

    if(@$params['q'] && @$params['field']){

        //{type:'freetext',step:0,field:1,facet_type:3,
        $currentUser = $system->getCurrentUser();
        $dt_type     = @$params['type'];
        $step_level  = intval(@$params['step']);
        $fieldid     = $params['field'];
        $count_query = @$params['count_query'];
        $facet_type =  intval(@$params['facet_type']);//0 direct search search, 1 - select/slider, 2 - list inline, 3 - list column
        $facet_groupby = @$params['facet_groupby'];//by first char for freetext, by year for dates, by level for enum
        $vocabulary_id = @$params['vocabulary_id'];//special case for groupby first level
        $limit         = @$params['limit'];//limit for preview

        //special parameter to avoid nested queries - it allows performs correct count for distinct target record type
        //besides it return correct field name to be used in count function
        $params['nested'] = (@$params['needcount']!=2);


        //do not include bookmark join
        if(!(strcasecmp(@$params['w'],'B') == 0  ||  strcasecmp(@$params['w'],BOOKMARK) == 0)){
            $params['w'] = NO_BOOKMARK;
        }

        if(!@$params['q']){
            return $system->addError(HEURIST_INVALID_REQUEST, $savedSearchName."Facet query search request. Missing query parameter");
        }

        if( $system->get_user_id() > 0 ) {
            //use subset for initial search only
            $params['use_user_wss'] = @$params['step']==0;
        } else {
            $params['use_user_wss'] = false;
        }

        //get SQL clauses for current query
        $qclauses = get_sql_query_clauses_NEW($mysqli, $params, $currentUser);

        $select_field  = "";
        $detail_link   = "";
        $details_where = "";

        if($fieldid=="rectype" || $fieldid=="typeid"){
            $select_field = "r0.rec_RecTypeID";
        }elseif($fieldid=='typename'){

            $select_field = "rty_Name";
            $detail_link   = ", defRecTypes ";
            $details_where = " AND (rty_ID = r0.rec_RecTypeID) ";

        }elseif($fieldid=='recTitle' || $fieldid=='title'){
            $select_field = "r0.rec_Title";
            $dt_type = "freetext";
        }elseif($fieldid=='id' || $fieldid=='ids' || $fieldid=='recID'){
            $select_field = "r0.rec_ID";
            $dt_type = "integer";
        }elseif($fieldid=='owner'){
            $select_field = "r0.rec_OwnerUGrpID";
            $dt_type = "integer";
        }elseif($fieldid=='addedby'){
            $select_field = "r0.rec_AddedByUGrpID";
            $dt_type = "integer";
        }elseif($fieldid=='notes'){
            $select_field = "r0.rec_ScratchPad";
            $dt_type = "freetext";
        }elseif($fieldid=='url'){
            $select_field = "r0.rec_URL";
            $dt_type = "freetext";
        }elseif($fieldid=='tag'){

            $select_field = "tag_Text";
            $detail_link   = ", usrTags, usrRecTagLinks ";
            $details_where = " AND (rtl_TagID=tag_ID AND r0.rec_ID=rtl_RecID) ";

        }elseif($fieldid=='access'){
            $select_field = "r0.rec_NonOwnerVisibility";
            //$dt_type = "freetext";
        }elseif($fieldid=='recAdded' || $fieldid=='added'){
            $select_field = "r0.rec_Added";
        }elseif($fieldid=='recModified' || $fieldid=='modified'){
            $select_field = "r0.rec_Modified";
        }else{
            if(strpos($fieldid,',')>0 && getCommaSepIds($fieldid)!=null){
                $compare_field = 'IN ('.$fieldid.')';
            }else{
                $compare_field = '='.$fieldid;
            }

            $select_field  = "dt0.dtl_Value";
            $detail_link   = ", recDetails dt0 ";
            $details_where = " AND (dt0.dtl_RecID=r0.rec_ID and dt0.dtl_DetailTypeID $compare_field) "
            ." AND (NULLIF(dt0.dtl_Value, '') is not null)";
            //$detail_link   = " LEFT JOIN recDetails dt0 ON (dt0.dtl_RecID=r0.rec_ID and dt0.dtl_DetailTypeID=".$fieldid.")";
            //$details_where = " and (dt0.dtl_Value is not null)";
        }

        $select_clause = "";
        $grouporder_clause = "";
        $rec_query = "";

        if($dt_type=='date'){

            //select valid dates
            $select_field = 'dt0.rdi_estMinDate';
            $detail_link = ', recDetailsDateIndex dt0';
            $details_where = " AND (dt0.rdi_estMinDate<2100 and dt0.rdi_RecID=r0.rec_ID and dt0.rdi_DetailTypeID $compare_field) ";

            //OLD ' AND (cast(getTemporalDateString('.$select_field.') as DATETIME) is not null ';
            //OLD .'OR (cast(getTemporalDateString('.$select_field.') as SIGNED) is not null  AND '
            //OLD .'cast(getTemporalDateString('.$select_field.') as SIGNED) !=0) )';

            //for dates we search min and max values to provide data to slider
            //facet_groupby   by year, day, month, decade, century
            if ($facet_groupby=='month') {


                $select_field = 'ROUND(dt0.rdi_estMinDate ,2)';

                //OLD $select_field = 'LAST_DAY(cast(getTemporalDateString('.$select_field.') as DATE))';

                $select_clause = "SELECT $select_field as rng, count(*) as cnt ";
                if($grouporder_clause==''){
                    $grouporder_clause = ' GROUP BY rng ORDER BY rng';
                }

            }elseif($facet_groupby=='year' || $facet_groupby=='decade' || $facet_groupby=='century') {

                $select_field = 'ROUND(dt0.rdi_estMinDate ,0)';

                if($facet_groupby=='decade'){
                    $select_field = $select_field.' DIV 10 * 10';
                }elseif($facet_groupby=='century'){
                    $select_field = $select_field.' DIV 100 * 100';
                }

                $select_clause = "SELECT $select_field as rng, count(*) as cnt ";
                if($grouporder_clause==''){
                    $grouporder_clause = ' GROUP BY rng ORDER BY rng';
                    //" GROUP BY $select_field ORDER BY $select_field";
                }

            }else{

                //concat('00',
                //OLD $select_field = "cast(if(cast(getTemporalDateString( $select_field ) as DATETIME) is null,"
                //OLD ."concat('00',cast(getTemporalDateString( $select_field ) as SIGNED),'-1-1'),"  //year
                //OLD ."concat('00',getTemporalDateString( $select_field ))) as DATETIME)";

                $select_clause = "SELECT min(dt0.rdi_estMinDate) as min, max(dt0.rdi_estMaxDate) as max, count(distinct r0.rec_ID) as cnt ";

                if($facet_type==$ft_Select){
                    $rec_query = "SELECT r0.rec_ID ";
                }
            }

        }
        elseif(($dt_type=="enum" || $dt_type=="reltype") && $facet_groupby=='firstlevel' && $vocabulary_id!=null){

            $params_enum = null;
            if($count_query){
                $params_enum = json_decode( json_encode($params), true);
            }

            $qclauses = get_sql_query_clauses_NEW($mysqli, $params_enum, $currentUser);


            //NOTE - it applies for VOCABULARY only (individual selection of terms is not applicable)

            // 1. get first level of terms using $vocabulary_id
            $first_level = getTermChildren($vocabulary_id, $system, true);//get first level for vocabulary



            // 2.  find all children as plain array  [[parentid, child_id, child_id....],.....]
            $terms = array();
            foreach ($first_level as $parentID){
                $children = getTermChildren($parentID, $system, false);//get first level for vocabulary
                array_unshift($children, $parentID);
                array_push($terms, $children);
            }

            //3.  find distinct count for recid for every set of terms
            $select_clause = "SELECT count(distinct r0.rec_ID) as cnt ";

            $data = array();

            foreach ($terms as $vocab){


                if($params_enum!=null){ //new way
                    $params_enum['q'] = __assignFacetValue($count_query, implode(',', $vocab) );

                    $qclauses2 = get_sql_query_clauses_NEW($mysqli, $params_enum, $currentUser);
                    $query =  $select_clause.$qclauses2['from'].SQL_WHERE.$qclauses2['where'];
                }else{
                    $d_where = $details_where.' AND ('.$select_field.SQL_IN.implode(',', $vocab).'))';
                    //count query
                    $query =  $select_clause.$qclauses['from'].$detail_link.SQL_WHERE.$qclauses['where'].$d_where;
                }

                $res = mysql__select($mysqli, $query);
                if (!$res){
                    return $system->addError(HEURIST_DB_ERROR, $savedSearchName
                        .'Facet query error(A). Parameters:'.print_r($params, true), $mysqli->error);
                    //'.$query.'
                }else{
                    $row = $res->fetch_row();

                    //firstlevel term id, count, search value (set of all terms)
                    if($row[0]>0){
                        array_push($data, array($vocab[0], $row[0], implode(',', $vocab) ));
                        $res->close();
                    }
                }

            }//for
            return array("status"=>HEURIST_OK, "data"=> $data, "svs_id"=>@$params['svs_id'],
                "request_id"=>@$params['request_id'], //'dbg_query'=>$query,
                "facet_index"=>@$params['facet_index'], 'q'=>$params['q'], 'count_query'=>$count_query );

        }
        //SLIDER
        elseif((($dt_type=="integer" || $dt_type=="float") && $facet_type==$ft_Select) || $dt_type=="year"){

            //if ranges are not defined there are two steps 1) find min and max values 2) create select case
            $select_field = "cast($select_field as DECIMAL)";

            $select_clause = "SELECT min($select_field) as min, max($select_field) as max, count(distinct r0.rec_ID) as cnt ";

        }
        else { //freetext and other if($dt_type==null || $dt_type=="freetext")

            if($dt_type=="integer" || $dt_type=="float"){

                $select_field = "cast($select_field as DECIMAL)";

            }elseif($step_level==0 && $dt_type=="freetext"){

                $select_field = 'SUBSTRING(trim('.$select_field.'), 1, 1)';//group by first charcter                }
            }

            if($params['needcount']==1){

                $select_clause = "SELECT $select_field as rng, count(DISTINCT r0.rec_ID) as cnt ";
                if($grouporder_clause==""){
                    $grouporder_clause = " GROUP BY $select_field ORDER BY $select_field";
                }

            }else{ //count for related  if($params['needcount']==2)

                $tab = 'r0';
                while(strpos($qclauses["from"], 'Records '.$tab.'_0')>0){
                    $tab = $tab.'_0';
                }
                $select_clause = "SELECT $select_field as rng, count(DISTINCT ".$tab.".rec_ID) as cnt ";

                if($grouporder_clause==""){
                    $grouporder_clause = " GROUP BY $select_field ORDER BY $select_field";
                }

            }
            /*else{ //for fields from related records - search distinc values only

            $select_clause = "SELECT DISTINCT $select_field as rng, 0 as cnt ";
            if($grouporder_clause==""){
            $grouporder_clause = " ORDER BY $select_field";
            }
            }*/

        }


        //count query
        if($grouporder_clause!='' && strpos($grouporder_clause,'ORDER BY')>0){  //mariadb hates "order by" in the same time with "group by"
            $grouporder_clause = substr($grouporder_clause,0,strpos($grouporder_clause,'ORDER BY')-1);
        }

        $query =  $select_clause.$qclauses["from"].$detail_link.SQL_WHERE.$qclauses["where"].$details_where.$grouporder_clause;
        $rec_query = !empty($rec_query) ? "{$rec_query}{$qclauses["from"]}{$detail_link} WHERE {$qclauses["where"]}{$details_where}" : '';

        /*
        if($limit>0){
        $query = $query.' LIMIT '.$limit;
        }
        */

        //$res = $mysqli->query($query);
        $res = mysql__select($mysqli, $query);
        if (!$res){
            $response = $system->addError(HEURIST_DB_ERROR, $savedSearchName
                .'Facet query error(B). '.$query);// 'Parameters:'.print_r($params, true), $mysqli->error);
            //'.$query.'
        }else{
            $data = array();

            while ( $row = $res->fetch_row() ) {

                if((($dt_type=='integer' || $dt_type=='float') && $facet_type==$ft_Select)  ||
                (($dt_type=='year' || $dt_type=='date') && $facet_groupby==null)  ){
                    $third_element = $row[2];// slider - third parameter is COUNT for range

                    if(!$missingIds &&
                    (is_Array($params['q']) && !array_key_exists('ids', $params['q'])) &&
                    $row[2] != 0)
                    { // For range's histogram
                        $missingIds = true;
                    }
                }elseif($dt_type=="year" || $dt_type=="date") {

                    if($facet_groupby=='decade'){
                        $third_element = $row[0]+10;
                        //$row[0] = $row[0].'-01-01';
                    }elseif($facet_groupby=='century'){
                        $third_element = $row[0]+100;
                        //$row[0] = $row[0].'-01-01';
                    }

                    $third_element = $row[0];
                }elseif($step_level==0 && $dt_type=="freetext"){
                    $third_element = $row[0].'%';// first character
                }elseif($step_level>0 || $dt_type!='freetext'){
                    $third_element = $row[0];
                    if($dt_type=='freetext'){
                        $third_element = ('='.$third_element);
                    }
                }

                //value, count, second value(max for range) or search value for firstchar
                array_push($data, array($row[0], $row[1], $third_element ));

                // Retrieve list of record IDs, for additional functions (histogram)
                if(!empty($rec_query)){

                    $rec_ids = mysql__select_list2($mysqli, $rec_query, 'intval');
                    if(!empty($rec_ids)){
                        array_push($data, implode(',', $rec_ids));
                    }
                }
            }

            if($missingIds){

                $recid_query = "SELECT DISTINCT rec_ID " . $qclauses["from"] . $detail_link .
                SQL_WHERE . $qclauses["where"] . $details_where . $grouporder_clause;

                $recid_res = $mysqli->query($recid_query);
                if($recid_res){
                    $recids = array();

                    while($recid_row = $recid_res->fetch_row()){
                        array_push($recids, $recid_row[0]);
                    }

                    if(!empty($recids)){
                        $params['q']['ids'] = implode(',', $recids);
                    }

                    $recid_res->close();
                }
            }

            $response = array("status"=>HEURIST_OK, "data"=> $data, "svs_id"=>@$params['svs_id'],
                "request_id"=>@$params['request_id'], //'dbg_query'=>$query,
                "facet_index"=>@$params['facet_index'],
                'q'=>$params['q'], 'count_query'=>$count_query );
            $res->close();
        }

    }else{
        $response = $system->addError(HEURIST_INVALID_REQUEST, $savedSearchName."Facet query parameters are invalid. Try to edit and correct this facet search");
    }

    return $response;
}

//
//
//
function __assignFacetValue($params, $subs){
    foreach ($params as $key=>$value){
        if(is_array($value)){
            $params[$key] = __assignFacetValue($value, $subs);
        }elseif($value=='$FACET_VALUE'){
            $params[$key] = $subs;
            return $params;
        }
    }
    return $params;
}

//
// Get an array of lower and upper limits plus a record count for each interval
//
// @param:
//   $range (array) => (lowest date, highest date),
//   $interval (int) => interval size (e.g. $interval = 3, ([1982, 1985], [1986, 1989], ...))
//   $rec_ids (array) => record ids used for the count
//   $dty_id (int) => id for detail/base field containing the date in each record from above
//   $format (string) => default date format ("year", "month", "day")
//
// @return:
//   Array => each index is the lower and upper limits for the interval plus the number of records that fit this interval
//
function getDateHistogramData($system, $range, $interval, $rec_ids, $dty_id, $format="year", $is_between=true){

    $mysqli = $system->get_mysqli();

    $date_int = null;
    $intervals = array();
    $count = 0;
    $add_day = new DateInterval('P1D');// Keep the class limits inclusive
    $is_years_only = ($format=='years_only');
    if($is_years_only) {$format='year';}

    // Validate Input
    if($rec_ids == null){
        return $system->addError(HEURIST_INVALID_REQUEST, "No record ids have been provided");
    }elseif(is_string($rec_ids) && strpos($rec_ids, ',') !== false){
        $rec_ids = explode(',', $rec_ids);
    }elseif(!is_array($rec_ids) && intval($rec_ids) > 0){
        $rec_ids = array($rec_ids);
    }elseif(!is_array($rec_ids)){
        return $system->addError(HEURIST_INVALID_REQUEST, "Record ids have been provided in an un-supported format<br>".$rec_ids);
    }

    if($dty_id == null || !is_numeric($dty_id)){
        return $system->addError(HEURIST_INVALID_REQUEST, "An invalid detail type id has been provided");
    }

    if(is_array($interval) || intval($interval) == 0){
        return $system->addError(HEURIST_INVALID_REQUEST, "An invalid interval has been provided");
    }

    $period = Temporal::getPeriod($range[0], $range[1]);
    if(!$period){
        return false;
    }

    $years = $period['years'];
    $months = @$period['months'];
    $days = @$period['days'];
    $fulldays = @$period['fulldays'];

    $s_date = new Temporal($range[0]);
    $e_date = new Temporal($range[1]);

    // Control variables
    $org_interval = $interval;
    $lower_level = false;
    $in_count = 0;

    if($format=='year'){

        if($months > 0 || $days > 0){ // Round up
            $years += 1;
        }

        $count = $years / $interval; // get the init number of classes

        $format = 'Y';

        if($count < 20){ // decrease interval size

            while($count < 20){

                $interval -= 5;
                if($interval <= 1){
                    $lower_level = true;
                    break;
                }
                $count = $years / $interval;

            }

            if($lower_level){
                return getDateHistogramData($system, $range, $org_interval, $rec_ids, $dty_id, 'month', $is_between);
            }
        }elseif($count > $interval){ // increase internal size

            while($count > $org_interval){

                $interval += 5;
                $count = $years / $interval;
            }
        }

        if($count <= 1){
            //$s_date->format($format), $e_date->format($format)
            array_push($intervals, array($s_date->getMinMax()[0], $e_date->getMinMax()[1], count($rec_ids)));
            return array("status"=>HEURIST_OK, "data"=>$intervals);
        }

        $date_int = new DateInterval('P'.$interval.'Y');
        $count = ceil($count);

    }elseif($format == 'month'){

        // Round up, +1 for any days and +12 for any years
        if($days > 0){
            $months += 1;
        }

        if($years > 0){
            $months += (12 * $years);
        }

        $count = $months / $interval; // get the init number of classes

        $format = 'd M Y';

        if($count < 15){ // decrease interval size

            while($count < 15){

                $interval -= 12;
                if($interval <= 1){
                    $lower_level = true;
                    break;
                }
                $count = $months / $interval;
            }

            if($lower_level){
                return getDateHistogramData($system, $range, $org_interval, $rec_ids, $dty_id, 'day', $is_between);
            }
        }elseif($count > $interval){ // increase internal size

            while($count > $org_interval){
                $interval += 12;
                $count = $months / $interval;

                $in_count++;
            }

            if($in_count >= 15){
                return getDateHistogramData($system, $range, $org_interval, $rec_ids, $dty_id, 'year', $is_between);
            }
        }

        if($count <= 1){
            //$s_date->format($format), $e_date->format($format)
            array_push($intervals, array($s_date->getMinMax()[0], $e_date->getMinMax()[1], count($rec_ids)));
            return array("status"=>HEURIST_OK, "data"=>$intervals);
        }

        $date_int = new DateInterval('P'.$interval.'M');
        $count = ceil($count);

    }
    else{  //DAYS

        $days = $fulldays>0?$fulldays:$days;

        $count = $days / $interval; // get the init number of classes

        $format = 'd M Y';

        if($count > $interval){ // increase internal size

            while($count > $org_interval){
                $interval += 30;
                $count = $days / $interval;

                $in_count++;
            }

            if($in_count >= 12){
                return getDateHistogramData($system, $range, $org_interval, $rec_ids, $dty_id, 'month', $is_between);
            }
        }elseif($count < 15){ // decrease interval size

            while($interval - 30 > 1 && $count < 1){

                $interval  = $interval - 30;
                if($interval <= 1){
                    $interval = 1;
                    break;
                }
                $count = $days / $interval;
            }
        }

        if($count <= 1){
            //$s_date->format($format), $e_date->format($format)
            array_push($intervals, array($s_date->getMinMax()[0], $e_date->getMinMax()[1], count($rec_ids)));
            return array("status"=>HEURIST_OK, "data"=>$intervals);
        }

        $date_int = new DateInterval('P'.$interval.'D');
        $count = ceil($count);

    }

    // Create date intervals (class limits)
    if($is_years_only){
        $lower = $s_date->getMinMax()[0];//in decimal
        $end_year = $e_date->getMinMax()[1];
        for($i = 0; $i < $count; $i++){

            $upper = $lower +  $interval;

            if($upper > $end_year){ // last class
                array_push($intervals, array($lower, ($end_year>0?($end_year+0.1231):$end_year), 0));
                break;
            }else{ // add class
                array_push($intervals, array($lower, $upper, 0));
            }

            $lower = $upper;
        }
    }else{
        try{
            $start_interval0 = Temporal::decimalToYMD($s_date->getMinMax()[0]);
            $start_interval = new DateTime($start_interval0);
        }catch(Exception $e){
            return $system->addError(HEURIST_ERROR, 'Wrong start of range '.$range[0].'  '.$s_date->getMinMax()[0].' '.$start_interval0);
        }
        try{
            $end_date = new DateTime(Temporal::decimalToYMD($e_date->getMinMax()[1]));
        }catch(Exception $e){
            return $system->addError(HEURIST_ERROR, 'Wrong end of range '.$range[1].'  '.$s_date->getMinMax()[1]);
        }

        for($i = 0; $i < $count; $i++){

            $lower = floatval($start_interval->format('Y.md'));
            $upper = new DateTime($start_interval->add($date_int)->format('Y-m-d'));

            if($upper > $end_date){ // last class
                array_push($intervals, array($lower, $e_date->getMinMax()[1], 0));
                break;
            }else{ // add class
                array_push($intervals, array($lower, floatval($upper->format('Y.md')), 0));
            }

            $start_interval->add($add_day);
        }
    }

    $sql = 'SELECT rdi_estMinDate, rdi_estMaxDate '
    .' FROM recDetailsDateIndex'
    .' WHERE rdi_estMaxDate<2100 AND rdi_RecID IN ('
    .implode(',', $rec_ids).") AND rdi_DetailTypeID = ".$dty_id;

    $res = mysql__select($mysqli, $sql);
    if(!$res){
        return $system->addError(HEURIST_DB_ERROR, "An SQL Error has Occurred => " . $mysqli->error);
    }

    while($row = $res->fetch_row()){ // cycle through all records

        $dt0 = $row[0];
        $dt1 = $row[1];

        $class_found = 0;

        for($k = 0; $k < count($intervals); $k++){ // cycle through classes, add to required count

            $lower = $intervals[$k][0];
            $upper = $intervals[$k][1];

            if($lower <= $dt0 && $dt1 <= $upper){
                $intervals[$k][2] += 1;
                if($is_between){ break; } // within - exclusive
                // else overlap - inclusive
                $class_found = 1;
            }elseif($class_found == 1){
                break;
            }
        }
    }

    return array("status"=>HEURIST_OK, "data"=>$intervals);

    //return $system->addError(HEURIST_UNKNOWN_ERROR, "An unknown error has occurred with attempting to retrieve the date data for DB => " . HEURIST_DBNAME . ", record ids => " . implode(',', $rec_ids));
}

//
// Search for record ids based on provided search values, returns only one id per search
// $ptr_record_ids => array of strings (example. ['1,2,3,4', '5,6,7,8', ...])
// $search_values => array of strings (example. ['Jean-Jacques', ''])
//
function getRecordIds($system, $ptr_record_ids, $search_values){

    $mysqli = $system->get_mysqli();
    $rec_ids = array();

    if(!is_array($ptr_record_ids) || count($ptr_record_ids) <= 0){
        return $system->addError(HEURIST_ERROR, "No record type ids where sent to search");
    }elseif(!is_array($search_values) || count($search_values) != count($ptr_record_ids)){
        return $system->addError(HEURIST_ERROR, "There needs to be an equal number of search values with each record type being checked");
    }

    foreach ($ptr_record_ids as $key => $rectype_id_set) {

        $searching = $search_values[$key];
        $query_base = "SELECT rec.rec_ID FROM Records AS rec INNER JOIN recDetails AS dtl ON rec.rec_ID = dtl.dtl_RecID WHERE rec.rec_RecTypeID IN ($rectype_id_set) AND ";

        if(is_array($searching)){

            foreach ($searching as $s_key => $search) {

                $query = $query_base . "dtl.dtl_Value = '$search'";

                $rtn = $mysqli->query($query);

                if(!$rtn){
                    $rec_ids[$key] = null;
                    break;
                }else{

                    $ids = array();
                    while($result = $rtn->fetch_row()){

                        if($result){
                            $ids[] = $result[0];
                        }
                    }
                    $rtn->close();

                    if(!empty($ids)){
                        $rec_ids[$key] = $ids;
                        $query_base = "SELECT dtl.dtl_RecID FROM recDetails AS dtl WHERE dtl.dtl_RecID IN (". implode(',', $ids) .") AND ";
                    }else{
                        $rec_ids[$key] = null;
                        break;
                    }

                }

            }
        }else{

            $ids = array();

            $query_base .= "dtl.dtl_Value = '$searching'";

            $rtn = $mysqli->query($query_base);

            if(!$rtn){
                $rec_ids[$key] = null;
            }else{

                $ids = array();
                while($result = $rtn->fetch_row()){

                    if($result){
                        $ids[] = $result[0];
                    }
                }
                $rtn->close();

                if(!empty($ids)){
                    $rec_ids[$key] = $ids;
                }else{
                    $rec_ids[$key] = null;
                }
            }
        }

        if(is_array(@$rec_ids[$key])){

            if(count($rec_ids[$key]) == 1){
                $rec_ids[$key] = $rec_ids[$key][0];
            }else{ // Ambiguous, need user to select appropriate record

                $rec_id_list = implode(',', $rec_ids[$key]);

                $rec_ids[$key] = array($rec_id_list, $rectype_id_set);
            }
        }
    }

    return array("status"=>HEURIST_OK, "data"=>$rec_ids);
}

/**
* search all related (links and releationship) records for given set of records
* it searches links recursively and adds found records into original array  $ids
*
* @param mixed $system
* @param mixed $ids
* @param mixed $direction  -  1 direct/ -1 reverse/ 0 both
*/
function recordSearchRelatedIds($system, &$ids, $direction=0, $no_relationships=false,
    $depth=0, $max_depth=1, $limit=0, $new_level_ids=null, $temp_ids=null){

    if($depth>=$max_depth) {return;}

    if($new_level_ids==null) {$new_level_ids = $ids;}

    if(!($direction==1||$direction==-1)){
        $direction = 0;
    }

    $mysqli = $system->get_mysqli();

    $res1 = null; $res2 = null;

    if($temp_ids==null && !$no_relationships){
        //find temp relationship records (rt#1)
        $relRT = ($system->defineConstant('RT_RELATION')?RT_RELATION:0);
        $query = 'SELECT rec_ID FROM Records '
        .' where rec_RecTypeID='.$relRT.' AND rec_FlagTemporary=1';
        $temp_ids = mysql__select_list2($mysqli, $query);
    }

    if($direction>=0){

        //find all target related records
        $query = 'SELECT rl_TargetID, rl_RelationID FROM recLinks, Records '
        .' where rl_SourceID in ('.implode(',',$new_level_ids).') '
        .' AND rl_TargetID=rec_ID AND rec_FlagTemporary=0';
        if($no_relationships){
            $query = $query . ' AND rl_RelationID IS NULL';
        }

        $res = $mysqli->query($query);
        if ($res){
            $res1 = array();

            while ($row = $res->fetch_row()){

                $id = intval($row[1]);
                if($id>0){
                    if($temp_ids!=null && in_array($id, $temp_ids)){ //is temporary
                        continue;     //exclude temporary
                    }elseif(!in_array($id, $ids)){
                        array_push($res1, $id);//add relationship record
                    }
                }

                $id = intval($row[0]);
                if(!in_array($id, $ids)) {array_push($res1, $id);}
            }
            $res->close();
        }
    }

    if($direction<=0){
        $query = 'SELECT rl_SourceID, rl_RelationID FROM recLinks, Records where rl_TargetID in ('
        .implode(',',$new_level_ids).') '
        .' AND rl_SourceID=rec_ID AND rec_FlagTemporary=0';
        if($no_relationships){
            $query = $query . ' AND rl_RelationID IS NULL';
        }

        $res = $mysqli->query($query);
        if ($res){
            $res2 = array();

            while ($row = $res->fetch_row()){

                $id = intval($row[1]);
                if($id>0){
                    if($temp_ids!=null && in_array($id, $temp_ids)){ //is temporary
                        continue;
                    }elseif(!in_array($id, $ids)){
                        array_push($res2, $id);
                    }
                }

                $id = intval($row[0]);
                if(!in_array($id, $ids)) {array_push($res2, $id);}
            }
            $res->close();
        }
    }

    if(!isEmptyArray($res1) && is_array($res2)){
        $res = array_merge_unique($res1, $res2);
    }elseif(!isEmptyArray($res1)){
        $res = $res1;
    }else{
        $res = $res2;
    }

    //find new level
    if(!isEmptyArray($res)){
        $ids = array_merge_unique($ids, $res);

        if($limit>0 && count($ids)>=$limit){
            $ids = array_slice($ids,0,$limit);
        }else{
            recordSearchRelatedIds($system, $ids, $direction, $no_relationships, $depth+1, $max_depth, $limit, $res, $temp_ids);
        }

    }
}

/**
* Finds all related (and linked) record IDs for given set record IDs
*
* @param mixed $system
* @param mixed $ids -
* @param mixed $direction -  1 direct/ -1 reverse/ 0 both
* @param mixed $need_headers - if "true" returns array of titles,ownership,visibility for linked records
*                              if "ids" returns ids only
* @param mixed $link_type 0 all, 1 links, 2 relations
*
* @return array of direct and reverse links (record id, relation type (termid), detail id)
*/
function recordSearchRelated($system, $ids, $direction=0, $need_headers=true, $link_type=0){

    if(!@$ids){
        return $system->addError(HEURIST_INVALID_REQUEST, 'Invalid search request');
    }

    $ids = prepareIds($ids);

    if(empty($ids)) {return array("status"=>HEURIST_OK, 'data'=>array());}//returns empty array

    if(!($direction==1||$direction==-1)){
        $direction = 0;
    }
    if(!($link_type>=0 && $link_type<3)){
        $link_type = 0;
    }
    if($link_type==2){ //relations only
        $sRelCond  = ' AND (rl_RelationID IS NOT NULL)';
    }elseif($link_type==1){ //links only
        $sRelCond  = ' AND (rl_RelationID IS NULL)';
    }else{
        $sRelCond = '';
    }

    $rel_ids = array();//relationship records (rt #1)

    $direct = array();
    $reverse = array();
    $headers = array();//record title and type for main record
    $direct_ids = array();//sources
    $reverse_ids = array();//targets

    $mysqli = $system->get_mysqli();

    //query to find start and end date for relationship
    $system->defineConstant('DT_START_DATE');
    $system->defineConstant('DT_END_DATE');
    $query_rel = 'SELECT rec_ID, d2.dtl_Value t2, d3.dtl_Value t3 from Records '
    .' LEFT JOIN recDetails d2 on rec_ID=d2.dtl_RecID and d2.dtl_DetailTypeID='.(defined('DT_START_DATE')?DT_START_DATE:0)
    .' LEFT JOIN recDetails d3 on rec_ID=d3.dtl_RecID and d3.dtl_DetailTypeID='.(defined('DT_END_DATE')?DT_END_DATE:0)
    .SQL_WHERE.' rec_ID=';

    if($direction>=0){

        //find all target related records
        $query = 'SELECT rl_SourceID, rl_TargetID, rl_RelationTypeID, rl_DetailTypeID, rl_RelationID FROM recLinks '
        .SQL_WHERE.predicateId('rl_SourceID', $ids).$sRelCond.' order by rl_SourceID';

        $res = $mysqli->query($query);
        if (!$res){
            return $system->addError(HEURIST_DB_ERROR, "Search query error on related records. Query ".$query, $mysqli->error);
        }else{
            while ($row = $res->fetch_row()) {
                $relation = new stdClass();
                $relation->recID = intval($row[0]);
                $relation->targetID = intval($row[1]);
                $relation->trmID = intval($row[2]);// rl_RelationTypeID
                $relation->dtID  = intval($row[3]);// rl_DetailTypeID
                $relation->relationID  = intval($row[4]);//rl_RelationID

                if($relation->relationID>0) {

                    $vals = mysql__select_row($mysqli, $query_rel.$relation->relationID);
                    if($vals!=null){
                        $relation->dtl_StartDate = $vals[1];
                        $relation->dtl_EndDate = $vals[2];
                    }
                }

                array_push($rel_ids, intval($row[1]));
                array_push($direct, $relation);
            }
            $res->close();
            if($need_headers=='ids'){
                $direct_ids = $rel_ids;
            }
        }

    }

    if($direction<=0){

        //find all reverse related records
        $query = 'SELECT rl_TargetID, rl_SourceID, rl_RelationTypeID, rl_DetailTypeID, rl_RelationID FROM recLinks '
        .SQL_WHERE.predicateId('rl_TargetID', $ids).$sRelCond.' order by rl_TargetID';


        $res = $mysqli->query($query);
        if (!$res){
            return $system->addError(HEURIST_DB_ERROR, 'Search query error on reverse related records. Query '.$query, $mysqli->error);
        }else{
            while ($row = $res->fetch_row()) {
                $relation = new stdClass();
                $relation->recID = intval($row[0]);
                $relation->sourceID = intval($row[1]);
                $relation->trmID = intval($row[2]);
                $relation->dtID  = intval($row[3]);
                $relation->relationID  = intval($row[4]);

                if($relation->relationID>0) {
                    $vals = mysql__select_row($mysqli, $query_rel.$relation->relationID);
                    if($vals!=null){
                        $relation->dtl_StartDate = $vals[1];
                        $relation->dtl_EndDate = $vals[2];
                    }
                }

                array_push($reverse, $relation);
                array_push($rel_ids, intval($row[1]));
                array_push($reverse_ids, intval($row[1]));
            }
            $res->close();
        }

    }

    //find all rectitles and record types for main recordset AND all related records
    if($need_headers===true){

        $ids = array_merge($ids, $rel_ids);

        $query = 'SELECT rec_ID, rec_Title, rec_RecTypeID, rec_OwnerUGrpID, rec_NonOwnerVisibility from Records '
        .' WHERE rec_ID IN ('.implode(',',$ids).')';
        $res = $mysqli->query($query);
        if (!$res){
            return $system->addError(HEURIST_DB_ERROR, "Search query error on search related. Query ".$query, $mysqli->error);
        }else{

            while ($row = $res->fetch_row()) {
                $headers[$row[0]] = array($row[1], $row[2], $row[3], $row[4]);
            }
            $res->close();
        }

    }

    if($need_headers==='ids'){
        $response = array("status"=>HEURIST_OK,
            "data"=> array("direct"=>$direct_ids, "reverse"=>$reverse_ids, "headers"=>$headers));
    }else{
        $response = array("status"=>HEURIST_OK,
            "data"=> array("direct"=>$direct, "reverse"=>$reverse, "headers"=>$headers));
    }


    return $response;

}



/**
* Search count by target record type for given source type and base field
*
* @param mixed $system
* @param mixed $rty_ID
* @param mixed $dty_ID - base field id
* @param mixed $direction -  1 direct/ -1 reverse/ 0 both
*/
function recordLinkedCount($system, $source_rty_ID, $target_rty_ID, $dty_ID){

    if(!( (is_array($target_rty_ID) || $target_rty_ID>0) && $source_rty_ID>0)){
        return $system->addError(HEURIST_INVALID_REQUEST, 'Invalid search request. Source and target record type not defined');
    }

    $query = 'SELECT rl_TargetID, count(rl_SourceID) as cnt FROM recLinks, ';

    if(is_array($target_rty_ID)){
        $query = $query.'Records r1 WHERE rl_TargetID in ('.implode(',',$target_rty_ID).')';
    }else{
        $query = $query.'Records r1,  Records r2 '
        .'WHERE rl_TargetID=r2.rec_ID AND r2.rec_RecTypeID='.$target_rty_ID;

    }

    $query = $query.' AND rl_SourceID=r1.rec_ID AND r1.rec_RecTypeID='.$source_rty_ID;
    if($dty_ID>0){
        $query = $query.' AND rl_DetailTypeID='.$dty_ID;
    }
    $query = $query.' GROUP BY rl_TargetID ORDER BY cnt DESC';

    /*
    use hdb_MPCE_Mapping_Print_Charting_Enlightenment;
    SELECT rl_TargetID, count(rl_SourceID) FROM recLinks, Records r1,  Records r2
    WHERE rl_SourceID=r1.rec_ID AND r1.rec_RecTypeID=55
    AND rl_TargetID=r2.rec_ID AND r2.rec_RecTypeID=56
    AND rl_DetailTypeID=955
    group by rl_TargetID
    */
    $mysqli = $system->get_mysqli();

    $list = mysql__select_assoc2($mysqli, $query);

    if (!$list && $mysqli->error){
        return $system->addError(HEURIST_DB_ERROR, 'Search query error on related records. Query '.$query, $mysqli->error);
    }else{
        return array("status"=>HEURIST_OK, "data"=> $list);
    }
}


/**
* get all view group permissions for given set of records
*
* @param mixed $system
* @param mixed $ids
*/
function recordSearchPermissions($system, $ids){
    if(!@$ids){
        return $system->addError(HEURIST_INVALID_REQUEST, "Invalid search request");
    }

    $ids = prepareIds($ids);

    $permissions = array();
    $mysqli = $system->get_mysqli();

    $query = 'SELECT rcp_RecID, rcp_UGrpID, rcp_Level FROM usrRecPermissions '
    .' WHERE rcp_RecID IN ('.implode(",", $ids).')';
    $res = $mysqli->query($query);
    if (!$res){
        return $system->addError(HEURIST_DB_ERROR, "Search query error on search permissions. Query ".$query, $mysqli->error);
    }else{

        $response = array("status"=>HEURIST_OK, "view"=>array(), "edit"=>array());

        while ($row = $res->fetch_row()) {
            if(@$response[$row[2]][$row[0]]){
                array_push($response[$row[2]][$row[0]], $row[1]);
            }else{
                $response[$row[2]][$row[0]] = array($row[1]);
            }
        }
        $res->close();

        return $response;
    }

}

// NOT USED
//  returns SQL owner/visibility conditions for given user/group
// see also  _getRecordOwnerConditions in dbDefRecTypes
//
function recordGetOwnerVisibility($system, $ugrID){

    $is_db_owner = ($ugrID==2);

    $where2 = '';

    if(!$is_db_owner){

        $where2 = '(rec_NonOwnerVisibility="public")';// in ("public","pending")

        if($ugrID>0){ //logged in
            $mysqli = $system->get_mysqli();
            $wg_ids = user_getWorkgroups($this->mysqli, $ugrID);
            array_push($wg_ids, $ugrID);
            array_push($wg_ids, 0);// be sure to include the generic everybody workgroup

            //$this->from_clause = $this->from_clause.' LEFT JOIN usrRecPermissions ON rcp_RecID=r0.rec_ID ';

            $where2 = $where2.' OR (rec_NonOwnerVisibility="viewable")';
            // and (rcp_UGrpID is null or rcp_UGrpID in ('.join(',', $wg_ids).')))';

            $where2 = '( '.$where2.' OR rec_OwnerUGrpID in (' . join(',', $wg_ids).') )';
        }
    }

    return $where2;

}

//
// returns only first relationship type ID for 2 given records
//
function recordGetRelationshipType($system, $sourceID, $targetID ){

    $mysqli = $system->get_mysqli();

    //find all target related records
    $query = 'SELECT rl_RelationTypeID FROM recLinks '
    .'WHERE rl_SourceID='.$sourceID.' AND rl_TargetID='.$targetID.' AND rl_RelationID IS NOT NULL';
    $res = $mysqli->query($query);
    if (!$res){
        return null;// $system->addError(HEURIST_DB_ERROR, "Search query error on get relationship type", $mysqli->error);
    }else{
        if($row = $res->fetch_row()) {
            return $row[0];
        }else{
            return null;
        }
    }
}

//
// return linked record ids and their types (for update linked record titles)
//
function recordGetLinkedRecords($system, $recordID){

    $mysqli = $system->get_mysqli();
    $query = 'SELECT DISTINCT rl_TargetID, rec_RecTypeID FROM recLinks, Records WHERE rl_TargetID=rec_ID  AND rl_SourceID='.$recordID;
    $ids1 = mysql__select_assoc2($mysqli, $query);
    if($ids1===null){
        $system->addError(HEURIST_DB_ERROR, "Search query error for target linked and related records. Query ".$query, $mysqli->error);
        return false;
    }
    $query = 'SELECT DISTINCT rl_SourceID, rec_RecTypeID FROM recLinks, Records WHERE rl_SourceID=rec_ID  AND rl_TargetID='.$recordID;
    $ids2 = mysql__select_assoc2($mysqli, $query);
    if($ids2===null){
        $system->addError(HEURIST_DB_ERROR, "Search query error for source linked and related records. Query ".$query, $mysqli->error);
        return false;
    }

    //merge
    if(count($ids2)>count($ids1)){
        foreach($ids1 as $recid=>$rectype_id){
            if(!@$ids2[$recid]){
                $ids2[$recid] = $rectype_id;
            }
        }
        return $ids2;
    }else{
        foreach($ids2 as $recid=>$rectype_id){
            if(!@$ids1[$recid]){
                $ids1[$recid] = $rectype_id;
            }
        }
        return $ids1;
    }



}

//
// returns relationship records(s) (RT#1) for given source and target records
//
function recordGetRelationship($system, $sourceID, $targetID, $search_request=null){

    $mysqli = $system->get_mysqli();

    //find all target related records
    $query = 'SELECT rl_RelationID FROM recLinks WHERE rl_RelationID IS NOT NULL';

    if($sourceID>0){
        $query = $query.' AND rl_SourceID='.$sourceID;
    }
    if($targetID>0){
        $query = $query.' AND rl_TargetID='.$targetID;
    }

    $res = $mysqli->query($query);
    if (!$res){
        return $system->addError(HEURIST_DB_ERROR, "Search query error on relationship records for source-target. Query ".$query, $mysqli->error);
    }else{
        $ids = array();
        while ($row = $res->fetch_row()) {
            array_push($ids, intval($row[0]));
        }
        $res->close();

        if($search_request==null){
            $search_request = array('q'=>'ids:'.implode(',', $ids), 'detail'=>'detail');
        }else{
            $search_request['q'] = 'ids:'.implode(',', $ids);
            if(@$search_request['detail']=='ids'){
                return $ids;
            }elseif(!@$search_request['detail']){
                $search_request['detail'] = 'detail';//returns all details
            }
        }

        return recordSearch($system, $search_request);
    }


}

//
// find parent record for rec_ID with given record type
//
function recordSearchFindParent($system, $rec_ID, $target_recTypeID, $allowedDetails, $level=0){

    $query = 'SELECT rec_RecTypeID from Records WHERE rec_ID='.$rec_ID;
    $rtype = mysql__select_value($system->get_mysqli(), $query);

    if($rtype==$target_recTypeID){
        return $rec_ID;
    }

    $query = 'SELECT rl_SourceID FROM recLinks '
    .'WHERE rl_TargetID='.$rec_ID;
    if(is_array($allowedDetails)){
        $query = $query.' AND rl_DetailTypeID IN ('.implode(',',$allowedDetails).')';
    }else{
        $query = $query.' AND rl_DetailTypeID IS NOT NULL';
    }

    $parents = mysql__select_list2($system->get_mysqli(), $query);
    if(!isEmptyArray($parents)){
        if($level>5){
            $system->addError(HEURIST_ERROR, 'Cannot find parent CMS Home record. It appears that menu items refers recursively');
            return false;
        }

        $parent_ID = $parents[0];

        if(count($parents)>1 && defined('DT_CMS_PAGETYPE')){ //more that one parent
            $webpage = ConceptCode::getTermLocalID('2-6254');
            foreach($parents as $rec_ID){
                $isWebPage = false;
                $rec = recordSearchByID($system, $rec_ID, array(DT_CMS_PAGETYPE), 'rec_ID,rec_RecTypeID');
                if(@$rec['rec_RecTypeID']==RT_CMS_MENU && is_array(@$rec['details'][DT_CMS_PAGETYPE])){
                    //get term id by concept code
                    $val = recordGetField($rec, DT_CMS_PAGETYPE);
                    $isWebPage = ($val==$webpage);//standalone
                }
                if(!$isWebPage){
                    $parent_ID = $rec_ID;
                    break;
                }
            }
        }

        return recordSearchFindParent($system, $parent_ID, $target_recTypeID, $allowedDetails, $level+1);
    }else{
        $system->addError(HEURIST_ERROR, 'Cannot find parent CMS Home record');
        return false;
    }
}
//
// $menuitems - record ids
// fills $result array recursively with record ids and returns full detail at the end
//
function recordSearchMenuItems($system, $menuitems, &$result, $find_root_menu=false, $ids_only=false){

    $menuitems = prepareIds($menuitems, true);
    $isRoot = (empty($result));//find any first CMS_HOME (non hidden)
    if($isRoot && $find_root_menu){

        //if root record is menu - we have to find parent cms home
        if(count($menuitems)==1){
            if($menuitems[0]==0){
                //find ANY first home record
                $response = recordSearch($system, array('q'=>'t:'.RT_CMS_HOME, 'detail'=>'ids', 'w'=>'a'));

                if($response['status'] == HEURIST_OK  && !isEmptyArray(@$response['data']['records']) ){
                    $res = $response['data']['records'][0];
                }else{
                    return $system->addError(HEURIST_ERROR,
                        'Cannot find website home record');
                }

            }else{
                $root_rec_id = $menuitems[0];
                $isWebPage = false;

                if($system->defineConstant('DT_CMS_PAGETYPE')){
                    //check that this is single web page (for embed)
                    $rec = recordSearchByID($system, $root_rec_id, array(DT_CMS_PAGETYPE), 'rec_ID,rec_RecTypeID');
                    if(@$rec['rec_RecTypeID']==RT_CMS_MENU && is_array(@$rec['details'][DT_CMS_PAGETYPE])){
                        //get term id by concept code
                        $val = recordGetField($rec, DT_CMS_PAGETYPE);
                        $isWebPage = ($val==ConceptCode::getTermLocalID('2-6254'));//standalone
                    }
                }

                if($isWebPage){

                    return recordSearch($system, array('q'=>array('ids'=>$root_rec_id),
                        'detail'=>array(DT_NAME,DT_SHORT_SUMMARY,DT_CMS_TARGET,DT_CMS_CSS,
                            DT_CMS_PAGETITLE,DT_EXTENDED_DESCRIPTION,DT_CMS_TOP_MENU,DT_CMS_MENU,DT_THUMBNAIL,
                            DT_CMS_TOPMENUSELECTABLE), //'detail'
                        'w'=>'e', 'cms_cut_description'=>1));
                }else{
                    //find parent home record
                    $res = recordSearchFindParent($system,
                        $root_rec_id, RT_CMS_HOME, array(DT_CMS_MENU,DT_CMS_TOP_MENU));
                }
            }
            if($res===false){
                return $system->getError();
            }else{
                $menuitems[0] = $res;
            }
        }
    }

    $rec_IDs = array();

    foreach ($menuitems as $rec_ID){
        if(!in_array($rec_ID, $result)){ //to avoid recursion
            array_push($result, $rec_ID);
            array_push($rec_IDs, $rec_ID);
        }
    }

    if(!empty($rec_IDs)){
        /*
        $query = 'SELECT dtl_Value FROM recDetails WHERE dtl_RecID in ('
        .implode(',',$rec_IDs).') AND (dtl_DetailTypeID='.DT_CMS_MENU
        .' OR dtl_DetailTypeID='.DT_CMS_TOP_MENU.')';
        */
        $query = 'SELECT rl_TargetID FROM recLinks WHERE rl_SourceID in ('
        .implode(',',$rec_IDs).') AND (rl_DetailTypeID='.DT_CMS_MENU
        .' OR rl_DetailTypeID='.DT_CMS_TOP_MENU.')';

        $menuitems2 = mysql__select_list2($system->get_mysqli(), $query);

        $menuitems2 = prepareIds( $menuitems2 );

        if(!isEmptyArray($menuitems2)){
            recordSearchMenuItems($system, $menuitems2, $result);
        }
    }elseif($isRoot) {
        return $system->addError(HEURIST_INVALID_REQUEST, 'Root record id is not specified');
    }


    if($isRoot){
        if($ids_only){
            return $result;
        }else{
            //return recordset
            return recordSearch($system, array('q'=>array('ids'=>$result),
                'detail'=>array(DT_NAME,DT_SHORT_SUMMARY,DT_CMS_TARGET,DT_CMS_CSS,DT_CMS_PAGETITLE,DT_EXTENDED_DESCRIPTION,
                    DT_CMS_TOP_MENU,DT_CMS_MENU,DT_THUMBNAIL,DT_CMS_TOPMENUSELECTABLE), //'detail'
                'w'=>'e', 'cms_cut_description'=>1));
        }
    }

}

//-----------------------------------------------------------------------
/**
* put your comment there...
*
* @param mixed $system
* @param mixed $relation_query - sql expression to be executed (used as recursive parameters to search relationship records)
* @param mixed $params
*
*       FOR RULES
*       rules - rules queries - to search related records on server side
*       rulesonly - return rules only (without original query)
*       getrelrecs (=1) - search relationship records (along with related) on server side
*       topids - list of records ids, it is used to compose 'parentquery' parameter to use in rules (@todo - replace with new rules algorithm)
*       queryset - array of queries that will be executed one by one and result will be merged according to intersect param
*                  queryset will be created implicitely of first key of json query is "all" or "any"
*       intersect (=1) AND/conjunction or (=0) OR/disjunction
*
*       INTERNAL/recursive
*       parentquery - sql expression to substiture in rule query
*
*       SEARCH parameters that are used to compose sql expression
*       q - query string (old mode) or json array (new mode)
*       w (=all|bookmark a|b) - search among all or bookmarked records
*       limit  - limit for sql query is set explicitely on client side
*       offset - offset parameter value for sql query
*       s - sort order - if defined it overwrites sortby in q json param
*
*       OUTPUT parameters
*       needall (=1) - by default it returns only first 3000, to return all set it to 1,
*                      it is set to 1 for server-side rules searches
*       publiconly (=1) - ignore current user and returns only public records
*
*       detail (former 'f') - ids       - only record ids
*                             count     - only count of records
*                             count_by_rty - only count of records grouped by record types
*                             header    - record header only
*                             timemap   - record header + timemap details (time, location and symbology fields)
*                             detail    - record header + list of details
*                                           list of rec_XXX and field ids, if rec_XXX is missed all header fields are included
*                             complete  - all header fields, relations, full file info
*                             structure - record header + all details + record type structure (for editing) - NOT USED
*       tags                  returns with tags for current user (@todo for given user, group)
*       CLIENT SIDE
*       id - unque id to sync with client side
*       source - id of html element that is originator of this search
*       qname - original name of saved search (for messaging)
*/
function recordSearch($system, $params, $relation_query=null)
{
    //if $params['q'] has svsID it means search by saved filter - all parameters will be taken from saved filter
    // {"svs":5}

    $mysqli = $system->get_mysqli();

    $return_h3_format = false;

    if(@$params['q']){

        $svsID = null;
        $query_json = is_array(@$params['q']) ?$params['q'] :json_decode(@$params['q'], true);
        if(!isEmptyArray($query_json)){
            $svsID = @$query_json['svs'];

            if(@$query_json['any'] || @$query_json['all']){
                //first level is defined explicitely as "any" ot "all" - we will execute it separately - to avoid complex nested queries
                $params['queryset'] = @$query_json['any']?$query_json['any']:$query_json['all'];
                $params['intersect'] = @$query_json['all']?1:0;
                $params['sortby'] = @$query_json['sortby']?$query_json['sortby']
                :(@$query_json['sort']?$query_json['sort']
                    :(@$query_json['s']?$query_json['s']:null));
            }

        }elseif(@$params['q'] && strpos($params['q'],':')>0){
            list($predicate, $svsID) = explode(':', $params['q']);
            if(!($predicate=='svs' && $svsID>0)){
                $svsID = null;
            }
        }
        if($svsID>0){ //saved search id

            $vals = mysql__select_row($mysqli,
                'SELECT svs_Name, svs_Query FROM usrSavedSearches WHERE svs_ID='.$mysqli->real_escape_string( $svsID ));

            if($vals){
                $query = $vals[1];
                $params['qname'] = $vals[0];

                if(strpos($query, '?')===0){
                    parse_str(substr($query,1), $new_params);

                    if(@$new_params['q']) { $params['q'] = @$new_params['q'];}
                    if(@$new_params['rules']) { $params['rules'] = @$new_params['rules'];}
                    if(@$new_params['w']) { $params['w'] = @$new_params['w'];}
                    if(@$new_params['notes']) { $params['notes'] = @$new_params['notes'];}

                    return recordSearch($system, $params);

                }else{
                    //this is faceted search - it is not supported
                    return $system->addError(HEURIST_ERROR, 'Saved search '
                        .$params['qname']
                        .'<br> It is not possible to run faceted search as a query string');
                }
            }
        }
    }


    $memory_limit = USystem::getConfigBytes('memory_limit');

    //set savedSearchName for error messages
    $savedSearchName = '';
    if(is_numeric(@$params['qname']) && $params['qname'] > 0){ // retrieve extra details

        $query = 'SELECT svs_ID AS qID, svs_Name AS qName, svs_UGrpID as uID, ugr_Name as uName '
        . 'FROM usrSavedSearches '
        . 'INNER JOIN sysUGrps ON ugr_ID = svs_UGrpID '
        . 'WHERE svs_ID = ' . $params['qname'];

        $saved_search = mysql__select_row_assoc($mysqli, $query);

        if($saved_search !== null){
            $name = empty($saved_search['qName']) ? $saved_search['qID'] : $saved_search['qName'] . ' (# '. $saved_search['qID'] .')';
            $workgroup = empty($saved_search['uName']) ? $saved_search['uID'] : $saved_search['uName'] . ' (# '. $saved_search['uID'] .')';
            $savedSearchName = '<br>' .MSG_SAVED_FILTER. $name . '<br>Workgroup: ' . $workgroup . '<br>';
        }else{
            $savedSearchName = MSG_SAVED_FILTER.$params['qname'].'<br>';
        }
    }else{
        $savedSearchName = @$params['qname']? MSG_SAVED_FILTER. $params['qname'] .'<br>' : '';
    }
    $savedSearchName .= empty($savedSearchName) ? '' : 'It is probably best to delete this saved filter and re-create it.<br>';

    $system->defineConstant('RT_CMS_MENU');
    $system->defineConstant('DT_EXTENDED_DESCRIPTION');

    $useNewTemporalFormatInRecDetails = ($system->settings->get('sys_dbSubSubVersion')>=14);

    $fieldtypes_in_res = null;
    //search for geo and time fields and remove non timemap records - for rules we need all records
    $istimemap_request = (@$params['detail']=='timemap' && @$params['needall']!=1);
    $find_places_for_geo = false;
    $istimemap_counter = 0; //total records with timemap data
    $needThumbField = false;
    $needThumbBackground = false;
    $needCompleteInformation = false; //if true - get all header fields, relations, full file info
    $needTags = (@$params['tags']>0)?$system->get_user_id():0;
    $checkFields = (@$params['checkFields'] == 1);// check validity of certain field types

    $relations = null;
    $permissions = null;

    if(!@$params['detail']){// list of rec_XXX and field ids, if rec_XXX is missed all header fields are included
        $params['detail'] = @$params['f'];//backward capability
        if(!@$params['detail']){
            $params['detail'] = 'ids';
        }
    }
    if($params['detail']=='complete'){
        $params['detail'] = 'detail';
        $needCompleteInformation = true; //all header fields, relations, full file info
    }

    $header_fields = null;
    $fieldtypes_ids = null;

    $is_count_only = ('count'==$params['detail']);
    $is_count_by_rty = ('count_by_rty'==$params['detail']);
    if($is_count_by_rty) {$is_count_only = true;}
    $is_ids_only = ('ids'==$params['detail']);

    if($params['detail']=='timemap'){ //($istimemap_request){
        $params['detail']='detail';

        $system->defineConstant('DT_START_DATE');
        $system->defineConstant('DT_END_DATE');
        $system->defineConstant('DT_GEO_OBJECT');
        $system->defineConstant('DT_DATE');
        $system->defineConstant('DT_SYMBOLOGY_POINTMARKER');//outdated
        $system->defineConstant('DT_SYMBOLOGY_COLOR');//outdated
        $system->defineConstant('DT_BG_COLOR');//outdated
        $system->defineConstant('DT_OPACITY');//outdated

        //list of rectypes that are sources for geo location
        $rectypes_as_place = $system->settings->get('sys_TreatAsPlaceRefForMapping');
        if($rectypes_as_place){
            $rectypes_as_place = prepareIds($rectypes_as_place);
        }else {
            $rectypes_as_place = array();
        }
        //Place always in this array
        if($system->defineConstant('RT_PLACE')){
            if(!in_array(RT_PLACE, $rectypes_as_place)){
                array_push($rectypes_as_place, RT_PLACE);
            }
        }

        //get date,year and geo fields from structure
        $fieldtypes_ids = dbs_GetDetailTypes($system, array('date','year','geo'), 3);
        if(isEmptyArray($fieldtypes_ids)){
            //this case nearly impossible since system always has date and geo fields
            $fieldtypes_ids = array(DT_GEO_OBJECT, DT_DATE, DT_START_DATE, DT_END_DATE);//9,10,11,28';
        }
        //add symbology fields
        if(defined('DT_SYMBOLOGY_POINTMARKER')) {$fieldtypes_ids[] = DT_SYMBOLOGY_POINTMARKER;}
        if(defined('DT_SYMBOLOGY_COLOR')) {$fieldtypes_ids[] = DT_SYMBOLOGY_COLOR;}
        if(defined('DT_BG_COLOR')) {$fieldtypes_ids[] = DT_BG_COLOR;}
        if(defined('DT_OPACITY')) {$fieldtypes_ids[] = DT_OPACITY;}

        $fieldtypes_ids = prepareIds($fieldtypes_ids);

        $fieldtypes_ids = implode(',', $fieldtypes_ids);
        $needThumbField = true;

        //find places linked to result records for geo field
        if(@$params['suppres_derivemaplocation']!=1){ //for production sites - such as USyd Book of Remembrance Online or Digital Harlem
            $find_places_for_geo = !empty($rectypes_as_place) &&
            ($system->user_GetPreference('deriveMapLocation', 1)==1);
        }

    }elseif(  !in_array($params['detail'], array('count','count_by_rty','ids','header','timemap','detail','structure')) ){ //list of specific detailtypes
        //specific set of detail fields and header fields
        if(is_array($params['detail'])){
            $fieldtypes_ids = $params['detail'];
        } else {
            $fieldtypes_ids = explode(',', $params['detail']);
        }

        if(!isEmptyArray($fieldtypes_ids))
        //(count($fieldtypes_ids)>1 || is_numeric($fieldtypes_ids[0])) )
        {
            $f_res = array();
            $header_fields = array();

            foreach ($fieldtypes_ids as $dt_id){

                if(is_numeric($dt_id) && $dt_id>0){
                    array_push($f_res, $dt_id);
                }elseif($dt_id=='rec_ThumbnailURL'){
                    $needThumbField = true;
                }elseif($dt_id=='rec_ThumbnailBg'){
                    $needThumbBackground = true;
                }elseif(strpos($dt_id,'rec_')===0){
                    array_push($header_fields, $dt_id);
                }
            }

            if(!isEmptyArray($f_res)){
                $fieldtypes_ids = implode(',', $f_res);
                $params['detail'] = 'detail';
                $needThumbField = true;
            }else{
                $fieldtypes_ids = null;
            }
            if(empty($header_fields)){
                $header_fields = null;
            }else{
                //always include rec_ID and rec_RecTypeID
                if(!in_array('rec_RecTypeID',$header_fields)) {array_unshift($header_fields, 'rec_RecTypeID');}
                if(!in_array('rec_ID',$header_fields)) {array_unshift($header_fields, 'rec_ID');}
            }

        }else{
            $fieldtypes_ids = null;
            $params['detail'] = 'ids';
        }

    }else{
        $needThumbField = true;
    }


    //specific for USyd Book of Remembrance parameters - returns prevail bg color for thumbnail image
    $needThumbBackground = $needThumbBackground || (@$params['thumb_bg']==1);

    if(null==$system){
        $system = new hserv\System();
        if( ! $system->init(htmlspecialchars(@$_REQUEST['db'])) ){
            $response = $system->getError();
            if($return_h3_format){
                $response['error'] = $response['message'];
            }
            return $response;
        }
    }

    $currentUser = $system->getCurrentUser();

    if ( $system->get_user_id()<1 ) {
        $params['w'] = 'all';//does not allow to search bookmarks if not logged in
    }

    if($is_count_only){

        if($is_count_by_rty){
            $select_clause = 'select rec_RecTypeID, count(rec_ID) ';
        }else{
            $select_clause = 'select count(rec_ID) ';
        }


    }elseif($is_ids_only){

        //
        $select_clause = 'select SQL_CALC_FOUND_ROWS DISTINCT rec_ID ';

    }elseif($header_fields!=null){

        $select_clause = 'select SQL_CALC_FOUND_ROWS DISTINCT '.implode(',',$header_fields).' ';

    }else{

        $select_clause = 'select SQL_CALC_FOUND_ROWS DISTINCT '   //this function does not pay attention on LIMIT - it returns total number of rows
        .'bkm_ID,'
        .'bkm_UGrpID,'
        .'rec_ID,'
        .'rec_URL,'
        .'rec_RecTypeID,'
        .'rec_Title,'
        .'rec_OwnerUGrpID,'
        .'rec_NonOwnerVisibility,'
        .'rec_Modified,'
        .'bkm_PwdReminder,'
        .'rec_URLErrorMessage ';//don't forget trailing space
        /*
        .'rec_URLLastVerified,'
        .'bkm_PwdReminder ';*/


        if($needCompleteInformation){
            $select_clause = $select_clause
            .',rec_Added'
            .',rec_AddedByUGrpID'
            .',rec_ScratchPad'
            .',bkm_Rating ';
        }
    }

    if($currentUser && @$currentUser['ugr_ID']>0){
        $currUserID = $currentUser['ugr_ID'];
    }else{
        $currUserID = 0;
        $params['w'] = 'all';
    }


    if ( @$params['topids'] ){ //if topids are defined we use them as starting point for following rule query
        // it is used for incremental client side only

        if ( @$params['is_json'] ){

            //second parameter is link - add ids
            $keys = array_keys($params['q']);
            array_push($params['q'][$keys[count($keys)>1?1:0]],array('ids'=>prepareIds($params['topids'])));

        }else{

            $query_top = array();

            if (strcasecmp(@$params['w'],'B') == 0  ||  strcasecmp(@$params['w'], 'bookmark') == 0) {
                $query_top['from'] = 'FROM usrBookmarks TOPBKMK LEFT JOIN Records TOPBIBLIO ON bkm_recID=rec_ID ';
            }else{
                $query_top['from'] = 'FROM Records TOPBIBLIO LEFT JOIN usrBookmarks TOPBKMK ON bkm_recID=rec_ID and bkm_UGrpID='.$currUserID.' ';
            }
            $query_top['where'] = "(TOPBIBLIO.rec_ID in (".implode(',',prepareIds($params['topids']))."))";
            $query_top['sort'] =  '';
            $query_top['limit'] =  '';
            $query_top['offset'] =  '';

            $params['parentquery'] = $query_top;  //parentquery parameter is used in  get_sql_query_clauses

        }

    }
    elseif( @$params['rules'] ){ //set of consequent queries that depend on main query

        // rules - JSON array the same as stored in saved searches table

        if(is_array($params['rules'])){
            $rules_tree = $params['rules'];
        }else{
            $rules_tree = json_decode($params['rules'], true);
        }

        $flat_rules = array();
        $flat_rules[0] = array();

        //create flat rule array
        _createFlatRule( $flat_rules, $rules_tree, 0 );

        //find result for main query
        unset($params['rules']);
        if(@$params['limit']) {unset($params['limit']);}
        if(@$params['offset']) {unset($params['offset']);}

        $params['needall'] = 1; //return all records, otherwise dependent records could not be found

        $resSearch = recordSearch($system, $params);//search for main set
        //rulesonly 3 - keep original+last rule,  2 - returns only last extension, 1- returns all exts, 0 keep original+all rules
        $keepMainSet = (@$params['rulesonly']!=1 && @$params['rulesonly']!=2);
        $keepLastSetOnly = (@$params['rulesonly']==2 || @$params['rulesonly']==3);

        if(is_array($resSearch) && $resSearch['status']!=HEURIST_OK){  //error
            return $resSearch;
        }

        //find main query results
        $fin_result = $resSearch;
        //main result set
        $has_results = @$fin_result['data']['records'] && is_array($fin_result['data']['records']);
        if($has_results){
            $flat_rules[0]['results'] = $is_ids_only
            ?$fin_result['data']['records']
            :array_keys($fin_result['data']['records']);//get ids
        }else{
            $flat_rules[0]['results'] = array();
        }

        if(!$has_results || !$keepMainSet){
            //empty main result set
            $fin_result['data']['records'] = array();//empty
            $fin_result['data']['reccount'] = 0;
            $fin_result['data']['count'] = 0;
        }

        $is_get_relation_records = (@$params['getrelrecs']==1);//get all related and relationship records

        foreach($flat_rules as $idx => $rule){ //loop for all rules
            if($idx==0) {continue;}

            $is_last = (@$rule['islast']==1);

            //create request
            $params['q'] = $rule['query'];
            $parent_ids = $flat_rules[$rule['parent']]['results'];//list of record ids of parent resultset
            $rule['results'] = array();//reset

            //split by 3000 - search based on parent ids (max 3000)
            $k = 0;
            if(is_array($parent_ids)){
                while ($k < count($parent_ids)) {

                    //$need_details2 = $need_details && ($is_get_relation_records || $is_last);

                    $params3 = $params;
                    $params3['topids'] = implode(",", array_slice($parent_ids, $k, 3000));
                    if( !$is_last ){  //($is_get_relation_records ||
                        //$params3['detail'] = 'ids';//no need in details for preliminary results  ???????
                    }

                    if(is_array($params3['q'])){
                        $params3['is_json'] = true;
                    }elseif(strpos($params3['q'],'related_to')>0){
                        //t:54 related_to:10 =>   {"t":"54","related":"10"}
                        $params3['q'] = str_replace('related_to','related',$params3['q']);

                    }elseif(strpos($params3['q'],'relatedfrom')>0){

                        $params3['q'] = str_replace('relatedfrom','related',$params3['q']);
                    }

                    if($needCompleteInformation){
                        $params3['detail'] = 'complete';
                    }

                    $response = recordSearch($system, $params3);

                    if($response['status'] == HEURIST_OK){

                        if(!$rule['ignore'] && (!$keepLastSetOnly || $is_last)){
                            //merge with final results
                            if($is_ids_only){

                                $fin_result['data']['records'] = array_merge_unique($fin_result['data']['records'],
                                    $response['data']['records']);

                            }else{
                                $fin_result['data']['records'] = mergeRecordSets($fin_result['data']['records'],
                                    $response['data']['records']);

                                $fin_result['data']['fields_detail'] = array_merge_unique($fin_result['data']['fields_detail'],
                                    $response['data']['fields_detail']);

                                $fin_result['data']['rectypes'] = array_merge_unique($fin_result['data']['rectypes'],
                                    $response['data']['rectypes']);

                                $fin_result['data']['order'] = array_merge($fin_result['data']['order'],
                                    array_keys($response['data']['records']));
                            }
                        }

                        if(!$is_last){ //add top ids for next level
                            $flat_rules[$idx]['results'] = array_merge_unique($flat_rules[$idx]['results'],
                                $is_ids_only ?$response['data']['records'] :array_keys($response['data']['records']));
                        }

                        if($is_get_relation_records &&
                            (strpos($params3['q'],"related")>0 ||
                                strpos($params3['q'],"related_to")>0 || strpos($params3['q'],"relatedfrom")>0) )
                            { //find relationship records (recType=1)

                                //create query to search related records
                                if (strcasecmp(@$params3['w'],'B') == 0  ||  strcasecmp(@$params3['w'], 'bookmark') == 0) {
                                    $from = 'FROM usrBookmarks TOPBKMK LEFT JOIN Records TOPBIBLIO ON bkm_recID=rec_ID ';
                                }else{
                                    $from = 'FROM Records TOPBIBLIO LEFT JOIN usrBookmarks TOPBKMK ON bkm_recID=rec_ID and bkm_UGrpID='.$currUserID.' ';
                                }

                                if(strpos($params3['q'],"related_to")>0){
                                    $fld2 = "rl_SourceID";
                                    $fld1 = "rl_TargetID";
                                }else{
                                    $fld1 = "rl_SourceID";
                                    $fld2 = "rl_TargetID";
                                }

                                $ids_party1 = $params3['topids'];//source ids (from top query)
                                $ids_party2 = $is_ids_only?$response['data']['records'] :array_keys($response['data']['records']);

                                if(!isEmptyArray($ids_party2))
                                {


                                    $where = "WHERE (TOPBIBLIO.rec_ID in (select rl_RelationID from recLinks "
                                    ."where (rl_RelationID is not null) and ".
                                    "(($fld1 in (".$ids_party1.") and $fld2 in (".implode(",", $ids_party2).")) OR ".
                                    " ($fld2 in (".$ids_party1.") and $fld1 in (".implode(",", $ids_party2)."))) ".
                                    "))";

                                    $params2 = $params3;
                                    unset($params2['topids']);
                                    unset($params2['q']);

                                    $relation_query = $select_clause.$from.$where;

                                    $response = recordSearch($system, $params2, $relation_query);//search for relationship records
                                    if($response['status'] == HEURIST_OK){

                                        if(!@$fin_result['data']['relationship']){
                                            $fin_result['data']['relationship'] = array();
                                        }

                                        if($is_ids_only){
                                            $fin_result['data']['relationship'] = array_merge_unique(
                                                $fin_result['data']['relationship'],
                                                $response['data']['records']);
                                        }else{
                                            $fin_result['data']['relationship'] = mergeRecordSets($fin_result['data']['relationship'], $response['data']['records']);
                                        }


                                        /*merge with final results
                                        if($is_ids_only){
                                        $fin_result['data']['records'] = array_merge($fin_result['data']['records'], $response['data']['records']);
                                        }else{
                                        $fin_result['data']['records'] = mergeRecordSets($fin_result['data']['records'], $response['data']['records']);
                                        $fin_result['data']['order'] = array_merge($fin_result['data']['order'], array_keys($response['data']['records']));
                                        $fin_result['data']['rectypes'][1] = 1;
                                        }
                                        */
                                    }
                                }//array of ids is defined

                        }  //$is_get_relation_records

                    }else{
                        //@todo terminate execution and return error
                    }

                    $k = $k + 3000;
                }//while chunks
            }
        } //for rules


        if($is_ids_only){
            //$fin_result['data']['records'] = array_unique($fin_result['data']['records']);
        }
        $fin_result['data']['count'] = count($fin_result['data']['records']);
        $fin_result['data']['reccount'] = $fin_result['data']['count'];

        if($return_h3_format){
            $fin_result = array("resultCount" => $fin_result['data']['count'],
                "recordCount" => $fin_result['data']['count'],
                "recIDs" => implode(",", $fin_result['data']['records']) );
        }

        //@todo - assign if size less than 3000? only
        $fin_result['data']['mainset'] = $flat_rules[0]['results'];

        return $fin_result;
    }//END RULES ------------------------------------------
    elseif( @$params['queryset'] ){ //list of queries with OR (default) or AND operators
        // to facilitate database server workload. Old versions of mySQL (5.7) fail to execute
        // complex nested queries. Especailly with OR operators

        if(is_array($params['queryset'])){
            $queryset = $params['queryset'];
        }else{
            $queryset = json_decode($params['queryset'], true);
        }

        $is_or_conjunction = (@$params['intersect']!=1);//intersect or merge = AND or OR
        $details = @$params['detail'];
        $limit = @$params['limit'];
        $sortby = @$params['sortby'];

        unset($params['queryset']);
        unset($params['all']);
        if(@$params['limit']) {unset($params['limit']);}
        if(@$params['offset']) {unset($params['offset']);}
        if(@$params['sortby']) {unset($params['sortby']);}

        $params['detail'] = 'ids';
        $params['needall'] = 1;
        $fin_result = null;
        foreach($queryset as $idx => $query){ //loop for all queries

            $params['q'] = $query;

            $resSearch = recordSearch($system, $params);//search for main set
            if(is_array($resSearch) && $resSearch['status']!=HEURIST_OK){  //error
                return $resSearch;
            }
            if($fin_result==null){
                $fin_result = $resSearch;
            }else{
                //if OR - merge unique
                if($is_or_conjunction){
                    $fin_result['data']['records'] = array_merge_unique(
                        $fin_result['data']['records'],
                        $resSearch['data']['records']);
                }else{
                    //if AND - intersect
                    $fin_result['data']['records'] = array_intersect(
                        $fin_result['data']['records'],
                        $resSearch['data']['records']);
                }
            }
        }//foreach

        if(@$fin_result['data']['records']){
            $total_count = count($fin_result['data']['records']);
            if($limit>0 && $limit<$total_count){
                $fin_result['data']['records'] = array_slice($fin_result['data']['records'],0,$limit);
            }

            if(($details=='ids' || $details==null) && $sortby==null){
                $fin_result['data']['offset'] = 0;
                $fin_result['data']['reccount'] = count($fin_result['data']['records']);
            }else{
                $params['details'] = $details;
                $params['q'] = array('ids'=>$fin_result['data']['records']);
                $params['q']['sortby'] = $sortby;
                $fin_result = recordSearch($system, $params);//search for main set
            }

            if($fin_result['status']==HEURIST_OK){
                $fin_result['data']['count'] = $total_count; //total count
            }
        }

        return $fin_result;
    }
    elseif( $currUserID>0 ) {
        //find user work susbset (except EVERYTHING search)
        $params['use_user_wss'] = (@$params['w']!='e');//(strcasecmp(@$params['w'],'E') == 0);
    }


    $search_detail_limit = PHP_INT_MAX;

    if($relation_query!=null){
        $query = $relation_query;
    }else{

        $is_mode_json = false;

        $q = @$params['q'];

        if($q!=null && $q!=''){

            if(is_array($q)){
                $query_json = $q;
            }else{
                $query_json = json_decode($q, true);

                //try to parse plain string
                if( strpos($q,'*')===0 && isEmptyArray($query_json)){
                    $q = substr($q, 1);
                    $query_json = parse_query_to_json( $q );
                }
            }

            if(!isEmptyArray($query_json)){
                $params['q'] = $query_json;
                $is_mode_json = true;
            }

        }else{
            return $system->addError(HEURIST_INVALID_REQUEST, $savedSearchName."Invalid search request. Missing query parameter 'q'");
        }

        if($is_mode_json){
            $aquery = get_sql_query_clauses_NEW($mysqli, $params, $currentUser);//main usage
        }else{
            $aquery = get_sql_query_clauses($mysqli, $params, $currentUser);//!!!! IMPORTANT CALL OR compose_sql_query at once
        }

        if(@$aquery['error']=='create_fulltext'){
            return $system->addError(HEURIST_ACTION_BLOCKED, '<h3 style="margin:4px;">Building full text index</h3>'
                .'<p>To process word searches efficiently we are building a full text index.</p>'
                .'<p>This is a one-off operation and may take some time for large, text-rich databases '
                .'(where it will make the biggest difference to retrieval speeds).</p>', null);
        }elseif(@$aquery['error']){
            return $system->addError(HEURIST_ERROR, 'Unable to construct valid SQL query. '.@$aquery['error'], null);
        }
        if(!isset($aquery["where"]) || trim($aquery["where"])===''){
            return $system->addError(HEURIST_ERROR, 'Invalid search request; unable to construct valid SQL query', null);
        }

        if($is_count_only || ($is_ids_only && @$params['needall']) || !$system->has_access() ){ //not logged in
            $search_detail_limit = PHP_INT_MAX;
            $aquery['limit'] = '';
            if($is_count_only) {$aquery['sort'] = '';}
            $aquery['offset'] = '';
        }else{
            $search_detail_limit = $system->user_GetPreference('search_detail_limit');//limit for map/timemap output
        }
        if($is_count_by_rty){
            $aquery['sort'] = ' GROUP BY rec_RecTypeID';
        }

        $query =  $select_clause.$aquery['from'].SQL_WHERE.$aquery["where"].$aquery["sort"].$aquery["limit"].$aquery["offset"];

    }

    if(@$_REQUEST['dbg']==1) {
        print htmlspecialchars($query);
        exit;
    }


    //$res = $mysqli->query($query);
    $res = mysql__select($mysqli, $query);
    if (!$res){

        $sMsg = '';
        if($savedSearchName){
            $sMsg = 'in saved filter '.$savedSearchName;
        }else{
            $sMsg = 'in your query';
        }

        $response = $system->addError(HEURIST_ACTION_BLOCKED,
            '<h4>Uninterpretable Heurist query/filter</h4>'
            .'There is an error '.$sMsg.' syntax generating invalid SQL. Please check for misspelled keywords or incorrect syntax. See help for assistance.<br><br>'

            //.$params['q'].'  '.$query.'<br><br>'

            .'If you think the filter is correct, please make a bug report (link under Help menu at top right) or email the Heurist team, including the text of your filter.');

        //$response = $system->addError(HEURIST_DB_ERROR, $savedSearchName.
        //    ' Search query error on saved search. Parameters:'.print_r($params, true).' Query '.$query, $mysqli->error);
    }elseif($is_count_by_rty){

        $total_count_rows = 0;
        $records = array();

        while ($row = $res->fetch_row())  {
            $records[$row[0]] = (int)$row[1];
            $total_count_rows = $total_count_rows + (int)$row[1];
        }
        $res->close();

        $response = array('status'=>HEURIST_OK,
            'data'=> array(
                'queryid'=>@$params['id'],  //query unqiue id
                'recordtypes'=>$records,
                'count'=>$total_count_rows));

    }elseif($is_count_only){

        $total_count_rows = $res->fetch_row();
        $total_count_rows = (int)$total_count_rows[0];
        $res->close();

        $response = array('status'=>HEURIST_OK,
            'data'=> array(
                'queryid'=>@$params['id'],  //query unqiue id
                'count'=>$total_count_rows));

    }else{

        $total_count_rows = mysql__found_rows($mysqli);

        if($total_count_rows*10>$memory_limit){
            return $system->addError(HEURIST_ACTION_BLOCKED,
                $total_count_rows.MSG_MEMORY_LIMIT);
        }

        $rec_RecTypeID_index = false;

        if($is_ids_only)
        { //------------------------  LOAD and RETURN only IDS

            $records = array();

            while ($row = $res->fetch_row())  {
                array_push($records, (int)$row[0]);
            }
            $res->close();

            $response = array('status'=>HEURIST_OK,
                'data'=> array(
                    'queryid'=>@$params['id'],  //query unqiue id
                    'entityName'=>'Records',
                    'count'=>$total_count_rows,
                    'offset'=>get_offset($params),
                    'reccount'=>count($records),
                    'records'=>$records));

            if(@$params['links_count'] && !empty($records)){

                $links_counts = recordLinkedCount($system,
                    $params['links_count']['source'],
                    count($records)<500?$records:
                    $params['links_count']['target'],
                    @$params['links_count']['dty_ID']);

                if($links_counts['status']==HEURIST_OK && !isEmptyArray(@$links_counts['data']) ){

                    //order output
                    $res = array_keys($links_counts['data']);
                    if(count($res) < count($records)){
                        foreach ($records as $id){
                            if(!in_array($id, $res)){
                                $res[] = $id;
                            }
                        }
                    }
                    $response['data']['records'] = $res;
                    $response['data']['links_count'] = $links_counts['data'];
                    $response['data']['links_query'] = '{"t":"'
                    .$params['links_count']['source']
                    .'","linkedto'
                    .(@$params['links_count']['dty_ID']>0?(':'.$params['links_count']['dty_ID']):'')
                    .'":"[ID]"}';
                }
            }



        }else{ //----------------------------------

            $rectype_structures  = array();
            $rectypes = array();
            $records = array();
            $order = array();
            $all_rec_ids = array();
            $memory_warning = null;
            $limit_warning = false;

            // read all field names
            $_flds =  $res->fetch_fields();
            $fields = array();
            foreach($_flds as $fld){
                array_push($fields, $fld->name);
            }
            $rec_ID_index = array_search('rec_ID', $fields);
            $rec_RecTypeID_index = array_search('rec_RecTypeID', $fields);
            $date_add_index = array_search('rec_Added', $fields);
            $date_mod_index = array_search('rec_Modified', $fields);

            if($needThumbField) {array_push($fields, 'rec_ThumbnailURL');}
            if($needThumbBackground) {array_push($fields, 'rec_ThumbnailBg');}

            //array_push($fields, 'rec_Icon');//last one -icon ID
            if($needTags>0) {array_push($fields, 'rec_Tags');}

            // load all records
            while ($row = $res->fetch_row()) {

                if($needThumbField) {
                    $tres = fileGetThumbnailURL($system, $row[$rec_ID_index], $needThumbBackground);
                    array_push( $row, $tres['url'] );
                    if($needThumbBackground) {array_push( $row, $tres['bg_color'] );}
                }
                if($needTags>0){ //get record tags for given user/group
                    /*var dbUsrTags = new DbUsrTags($system, array('details'=>'label',
                    'tag_UGrpID'=>$needTags,
                    'rtl_RecID'=>$row[2] ));*/

                    $query = 'SELECT tag_Text FROM usrTags, usrRecTagLinks WHERE tag_ID=rtl_TagID AND tag_UGrpID='
                    .$needTags.' AND rtl_RecID='.$row[$rec_ID_index];
                    array_push( $row, mysql__select_list2($mysqli, $query));
                }

                //convert add and modified date to UTC
                if($date_add_index!==false) {
                    // zero date not allowed by default since MySQL 5.7, default date changed to 1000
                    if($row[$date_add_index]=='0000-00-00 00:00:00'
                    || $row[$date_add_index]=='1000-01-01 00:00:00'){ //not defined
                        $row[$date_add_index] = '';
                    }else{
                        $row[$date_add_index] = DateTime::createFromFormat(DATE_8601, $row[$date_add_index])
                        ->setTimezone(new DateTimeZone('UTC'))
                        ->format(DATE_8601);
                    }
                }
                if($date_mod_index!==false) {
                    $row[$date_mod_index] = DateTime::createFromFormat(DATE_8601, $row[$date_mod_index])
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format(DATE_8601);
                }


                //array_push( $row, $row[4] );//by default icon if record type ID
                $rec_ID = intval($row[$rec_ID_index]);
                $records[$rec_ID] = $row;
                array_push($order, $rec_ID);
                array_push($all_rec_ids, $rec_ID);
                if($rec_RecTypeID_index>=0 && !@$rectypes[$row[$rec_RecTypeID_index]]){  //rectypes is resultset
                    $rectypes[$row[$rec_RecTypeID_index]]=1;
                }

                if(count($all_rec_ids)>5000){
                    $mem_used = memory_get_usage();
                    if($mem_used>$memory_limit-104857600){ //100M
                        return $system->addError(HEURIST_ACTION_BLOCKED,
                            $total_count_rows.MSG_MEMORY_LIMIT);
                    }
                }

            }//load headers
            $res->close();

            //LOAD DETAILS
            if(($istimemap_request ||
            $params['detail']=='detail' ||
            $params['detail']=='structure') && !empty($records)){


                //$all_rec_ids = array_keys($records);
                $res_count = count($all_rec_ids);
                //split to 2500 to use in detail query
                $offset = 0;

                if($istimemap_request){
                    $tm_records = array();
                    $order = array();
                    $rectypes = array();
                    $istimemap_counter = 0;
                }

                $fieldtypes_in_res = array();//reset

                // FIX on fly: get "file" field types  - @todo  remove on 2022-08-22
                $file_field_types = mysql__select_list2($mysqli,'select dty_ID from defDetailTypes where dty_Type="file"');

                $datetime_field_types = mysql__select_list2($mysqli,'select dty_ID from defDetailTypes where dty_Type="date"');

                $loop_cnt=1;
                while ($offset<$res_count){

                    //here was a problem, since chunk size for mapping can be 5000 or more we got memory overflow here
                    //reason the list of ids in SELECT is bigger than mySQL limit
                    //solution - we perfrom the series of request for details by 1000 records
                    $chunk_rec_ids = array_slice($all_rec_ids, $offset, 1000);
                    $offset = $offset + 1000;

                    $ulf_fields = 'f.ulf_ObfuscatedFileID, f.ulf_Parameters';//5,6

                    //search for specific details
                    if($fieldtypes_ids!=null && $fieldtypes_ids!=''){

                        $detail_query = 'select dtl_ID, dtl_RecID,'
                        .'dtl_DetailTypeID,'     // 0
                        .'dtl_Value,'            // 1
                        .'ST_asWKT(dtl_Geo), dtl_UploadedFileID, '  //2,3
                        .'dtl_HideFromPublic, ' //4
                        .$ulf_fields
                        .' FROM recDetails '
                        . ' left join recUploadedFiles as f on f.ulf_ID = dtl_UploadedFileID '
                        . SQL_WHERE
                        .predicateId('dtl_RecID',$chunk_rec_ids)
                        .SQL_AND
                        .predicateId('dtl_DetailTypeID',$fieldtypes_ids);


                        if($find_places_for_geo){ //find location in linked Place records
                            $detail_query = $detail_query . 'UNION  '
                            .'SELECT dtl_ID, rl_SourceID,dtl_DetailTypeID,dtl_Value, ST_asWKT(dtl_Geo), rl_TargetID, 0, 0, 0 '
                            .' FROM recDetails, recLinks, Records '
                            .' WHERE (dtl_Geo IS NOT NULL) ' //'dtl_DetailTypeID='. DT_GEO_OBJECT
                            .' AND dtl_RecID=rl_TargetID AND rl_TargetID=rec_ID AND '
                            .predicateId('rec_RecTypeID',$rectypes_as_place)
                            .SQL_AND
                            .predicateId('rl_SourceID',$chunk_rec_ids);
                        }
                    }else{

                        if($needCompleteInformation){
                            $ulf_fields = 'f.ulf_OrigFileName,f.ulf_ExternalFileReference,f.ulf_ObfuscatedFileID,'
                            .'f.ulf_MimeExt';//5,6,7,8
                        }else{

                        }

                        $detail_query = 'select dtl_ID, dtl_RecID,'
                        .'dtl_DetailTypeID,'     // 0
                        .'dtl_Value,'            // 1
                        .'ST_asWKT(dtl_Geo),'    // 2
                        .'dtl_UploadedFileID,'   // 3
                        .'dtl_HideFromPublic,'   // 4
                        .$ulf_fields
                        .' from recDetails
                        left join recUploadedFiles as f on f.ulf_ID = dtl_UploadedFileID
                        where dtl_RecID in (' . join(',', $chunk_rec_ids) . ')';

                    }
                    //$detail_query = $detail_query . ' order by dtl_RecID, dtl_ID';
                    $need_Concatenation = false;
                    $loop_cnt++;
                    // @todo - we may use getAllRecordDetails
                    $res_det = $mysqli->query( $detail_query );

                    if (!$res_det){
                        $response = $system->addError(HEURIST_DB_ERROR,
                            $savedSearchName.'Search query error (retrieving details)',
                            $mysqli->error);
                        return $response;
                    }else{

                        while ($row = $res_det->fetch_row()) {
                            $dtl_ID = array_shift($row);
                            $recID = array_shift($row);
                            if( !array_key_exists('d', $records[$recID]) ){
                                $records[$recID]['d'] = array();
                                $need_Concatenation = $need_Concatenation ||
                                (defined('RT_CMS_MENU') && $records[$recID][4]==RT_CMS_MENU);
                            }
                            $dtyID = $row[0];


                            // FIX on fly - @todo  remove on 2022-08-22
                            if( (!($row[3]>0)) && in_array($dtyID,$file_field_types) ){
                                if($ruf_entity==null){
                                    $ruf_entity = new DbRecUploadedFiles($system);
                                }
                                $fileinfo = $ruf_entity->registerURL($row[1], false, $dtl_ID);

                                if($fileinfo && !isEmptyArray($fileinfo)){

                                    if($needCompleteInformation){
                                        $row[3] = $fileinfo['ulf_ID'];
                                        $row[5] = $fileinfo['ulf_OrigFileName'];
                                        $row[6] = $fileinfo['ulf_ExternalFileReference'];
                                        $row[7] = $fileinfo['ulf_ObfuscatedFileID'];
                                        $row[8] = $fileinfo['ulf_MimeExt'];
                                    }else{
                                        $row[5] = $fileinfo['ulf_ObfuscatedFileID'];
                                        $row[6] = '';
                                    }
                                }

                            }

                            $val = null;
                            $field_error = null;

                            if($row[2]){ //GEO
                                //dtl_Geo @todo convert to JSON
                                $val = $row[1];//geotype

                                // see $find_places_for_geo 3d value is record id of linked place
                                $linked_Place_ID = $row[3];//linked place record id
                                if($linked_Place_ID>0){
                                    $val = $val.':'.$linked_Place_ID;      //reference to real geo record
                                }

                                $val = $val.' '.$row[2];//WKT

                            }elseif($row[3]){ //uploaded file

                                if($needCompleteInformation){

                                    $val = array('ulf_ID'=>$row[3],
                                        'ulf_OrigFileName'=>$row[5],
                                        'ulf_ExternalFileReference'=>$row[6],
                                        'ulf_ObfuscatedFileID'=>$row[7],
                                        'ulf_MimeExt'=>$row[8]);


                                }else{
                                    $val = array($row[5], $row[6]);//obfuscated value for fileid and parameters
                                }

                            }elseif(in_array($dtyID, $datetime_field_types) && @$row[1]!=null) {
                                //!$useNewTemporalFormatInRecDetails &&
                                //convert date to old plain string temporal object to return to client side
                                $val = Temporal::getValueForRecDetails( $row[1], false );

                                if($checkFields){ // check if this date has been indexed and interpreted

                                    $check_query = 'SELECT rdi_estMinDate, rdi_estMaxDate FROM recDetailsDateIndex WHERE rdi_DetailID = '.intval($dtl_ID);// AND rdi_estMinDate != 0 AND rdi_estMaxDate != 0
                                    $check_res = $mysqli->query($check_query);

                                    if($check_res){

                                        $field_error = $check_res->num_rows == 0 ? 'This date has not been indexed' : null;

                                        if(!$field_error){ // has been indexed
                                            $row = $check_res->fetch_row();
                                            $field_error = intval($row[0]) === 0 && intval($row[1]) === 0 ? 'This date has been indexed, but it couldn\'t be interpreted' : null;
                                        }

                                    } // else mysql error
                                }

                            }elseif(@$row[1]!=null) {
                                $val = $row[1];//dtl_Value
                            }

                            if($val!=null){
                                $fieldtypes_in_res[$dtyID] = 1;
                                if( !array_key_exists($dtyID, $records[$recID]['d']) ){
                                    $records[$recID]['d'][$dtyID] = array();
                                    $records[$recID]['v'][$dtyID] = array();

                                    if($checkFields) { $records[$recID]['errors'][$dtyID] = array();}
                                }
                                array_push($records[$recID]['d'][$dtyID], $val);

                                //individual field visibility
                                array_push($records[$recID]['v'][$dtyID], $row[4]);//dtl_HideFromPublic

                                // if checked, return any errors found with the field
                                if($checkFields) { array_push($records[$recID]['errors'][$dtyID], $field_error);}
                            }
                        }//while
                        $res_det->close();


                        ///@todo optionally return geojson and timeline items

                        //additional loop for timemap request
                        //1. exclude records without timemap data
                        //2. limit to $search_detail_limit from preferences 'search_detail_limit'
                        if($istimemap_request){

                            foreach ($chunk_rec_ids as $recID) {
                                $record = $records[$recID];
                                if(!isEmptyArray(@$record['d'])){
                                    //this record is time enabled
                                    if($istimemap_counter<$search_detail_limit){
                                        $tm_records[$recID] = $record;
                                        array_push($order, $recID);
                                        if($rec_RecTypeID_index>=0) {$rectypes[$record[$rec_RecTypeID_index]] = 1; }
                                        //$records[$recID] = null; //unset
                                        //unset($records[$recID]);
                                    }else{
                                        $limit_warning = true;
                                        break;
                                    }
                                    $istimemap_counter++;
                                }
                            }
                        }//$istimemap_request
                        //it has RT_CMS_MENU - need concatenate all DT_EXTENDED_DESCRIPTION

                        if($need_Concatenation){

                            foreach ($chunk_rec_ids as $recID) {
                                $record = $records[$recID];
                                if($record[4]==RT_CMS_MENU
                                && is_array(@$record['d'][DT_EXTENDED_DESCRIPTION]))
                                {
                                    $records[$recID]['d'][DT_EXTENDED_DESCRIPTION] = array(implode('',$record['d'][DT_EXTENDED_DESCRIPTION]));

                                    if(@$params['cms_cut_description']==1 && @$records[$recID]['d'][DT_EXTENDED_DESCRIPTION][0]){
                                        $records[$recID]['d'][DT_EXTENDED_DESCRIPTION][0] = 'X';
                                    }
                                }
                            }
                        }


                        if($res_count>5000){
                            $mem_used = memory_get_usage();
                            if($mem_used>$memory_limit-52428800){ //50M
                                //cut off exceed records
                                $order = array_slice($order, 0, $offset);
                                $sliced_records = array();
                                if($istimemap_request){
                                    foreach ($order as $recID) {
                                        $sliced_records[$recID] = $tm_records[$recID];
                                    }
                                    $tm_records = $sliced_records;
                                    $memory_warning = '';
                                }else{
                                    foreach ($order as $recID) {
                                        $sliced_records[$recID] = $records[$recID];
                                    }
                                    $records = $sliced_records;
                                    $memory_warning = 'Search query produces '.$res_count.' records. ';
                                }
                                $memory_warning = $memory_warning.'The result is limited to '.count($sliced_records).' records due to server limitations.'
                                .' Please filter to a smaller set of results.';
                                break;
                            }
                        }

                    }

                }//while offset

                if($istimemap_request){

                    $records = $tm_records;
                    $total_count_rows = $istimemap_counter;
                }elseif($needCompleteInformation){
                    $relations = recordSearchRelated($system, $all_rec_ids);
                    if($relations['status']==HEURIST_OK){
                        $relations = $relations['data'];
                    }

                    $permissions = recordSearchPermissions($system, $all_rec_ids);
                    if($permissions['status']==HEURIST_OK){
                        $view_permissions = $permissions['view'];

                        array_push($fields, 'rec_NonOwnerVisibilityGroups');
                        $group_perm_index = array_search('rec_NonOwnerVisibilityGroups', $fields);
                        foreach ($view_permissions as $recid=>$groups){
                            $records[$recid][$group_perm_index] = implode(',', $groups);
                        }

                        $edit_permissions = $permissions['edit'];
                        $group_perm_index = array_search('rec_OwnerUGrpID', $fields);
                        foreach ($edit_permissions as $recid=>$groups){
                            array_unshift($groups, $records[$recid][$group_perm_index]);
                            $records[$recid][$group_perm_index] = implode(',', $groups);
                        }

                    }
                    //array("direct"=>$direct, "reverse"=>$reverse, "headers"=>$headers));
                }



            }//$need_details

            $rectypes = array_keys($rectypes);
            if( @$params['detail']=='structure' && !empty($rectypes)){ //rarely used in editing.js
                //description of recordtype and used detail types
                $rectype_structures = dbs_GetRectypeStructures($system, $rectypes, 1);//no groups
            }

            //"query"=>$query,
            $response = array('status'=>HEURIST_OK,
                'data'=> array(
                    //'query'=>$query,
                    'queryid'=>@$params['id'],  //query unqiue id
                    'pageno'=>@$params['pageno'],  //to sync page
                    'entityName'=>'Records',
                    'count'=>$total_count_rows,
                    'offset'=>get_offset($params),
                    'reccount'=>count($records),
                    'tmcount'=>$istimemap_counter,
                    'fields'=>$fields,
                    'fields_detail'=>array(),
                    'records'=>$records,
                    'order'=>$order,
                    'rectypes'=>$rectypes,
                    'limit_warning'=>$limit_warning,
                    'memory_warning'=>$memory_warning));
            if(is_array($fieldtypes_in_res)){
                $response['data']['fields_detail'] =  array_keys($fieldtypes_in_res);
            }
            if(is_array($relations)){
                $response['data']['relations'] =  $relations;
            }
        }//$is_ids_only





    }

    return $response;

}

/**
* array_merge_unique - return an array of unique values,
* composed of merging one or more argument array(s).
*
* As with array_merge, later keys overwrite earlier keys.
* Unlike array_merge, however, this rule applies equally to
* numeric keys, but does not necessarily preserve the original
* numeric keys.
*/
function array_merge_unique($a, $b) {
    foreach($b as $item){

        if(array_search($item, $a)===false){
            $a[] = $item;
        }
    }
    return $a;
}

function mergeRecordSets($rec1, $rec2){

    $res = $rec1;

    foreach ($rec2 as $recID => $record) {
        if(!@$rec1[$recID]){
            $res[$recID] = $record;
        }
    }

    return $res;
}

//
//
//
function _createFlatRule(&$flat_rules, $r_tree, $parent_index){

    if($r_tree){
        foreach ($r_tree as $rule) {
            $e_rule = array('query'=>@$rule['query'],
                'results'=>array(),
                'parent'=>$parent_index,
                'ignore'=>(@$rule['ignore']==1), //not include in final result
                'islast'=>(isEmptyArray(@$rule['levels']))?1:0 );
            array_push($flat_rules, $e_rule );
            _createFlatRule($flat_rules, @$rule['levels'], count($flat_rules)-1);
        }
    }

}

//
// find replacement for given record id
//
function recordSearchReplacement($mysqli, $rec_id, $level=0){

    if($rec_id>0){
        $rep_id = mysql__select_value($mysqli,
            'select rfw_NewRecID from recForwarding where rfw_OldRecID=' . intval($rec_id));
        if($rep_id>0){
            if($level<10){
                return recordSearchReplacement($mysqli, $rep_id, $level++);
            }else{
                return $rep_id;
            }
        }else{
            return $rec_id;
        }
    }else{
        return 0;
    }
}

//-----------------------
function recordTemplateByRecTypeID($system, $id){

    $record = array(
        'rec_ID'=>'RECORD-IDENTIFIER',
        'rec_RecTypeID'=>$id,
        'rec_Title'=>'',
        'rec_URL'=>'URL',
        'rec_ScratchPad'=>'',
        'rec_OwnerUGrpID'=>2,
        'rec_NonOwnerVisibility'=>'public',
        'rec_URLLastVerified'=>'',
        'rec_URLErrorMessage'=>'',
        'rec_AddedByUGrpID'=>2);

    $mysqli = $system->get_mysqli();
    $fields = mysql__select_assoc($mysqli, 'select dty_ID, dty_Type, dty_JsonTermIDTree, dty_PtrTargetRectypeIDs '
        .'from defRecStructure, defDetailTypes where dty_ID = rst_DetailTypeID '
        .'and rst_RecTypeID = '.$id);

    $details = array();
    $idx = 1;

    foreach ($fields as $dty_ID=>$fieldDetails){

        $dty_Type = $fieldDetails['dty_Type'];

        if($dty_Type=='separator') {continue;}


        if($dty_Type=='file'){
            $details[$dty_ID] = array($idx=>array('file'=>array('file'=>'TEXT', 'fileid'=>'TEXT')) );

        }elseif($dty_Type=='resource'){

            $extra_details = '';
            if(array_key_exists('dty_PtrTargetRectypeIDs', $fieldDetails)){ // retrieve list of rectype names

                $rty_names = mysql__select_list2($mysqli, 'SELECT rty_Name FROM defRecTypes WHERE rty_ID IN (' . $fieldDetails['dty_PtrTargetRectypeIDs'] .')');
                if(!empty($rty_names)){
                    $extra_details = ' to ' . implode(' | ', $rty_names);
                }
            }

            $details[$dty_ID] = array($idx=>array('id'=>'RECORD_REFERENCE'.$extra_details, 'type'=>0, 'title'=>''));
        }elseif($dty_Type=='relmarker'){

            $extra_details = '';
            if(array_key_exists('dty_JsonTermIDTree', $fieldDetails)){ // retrieve list of vocab labels
                $trm_names = mysql__select_list2($mysqli, 'SELECT trm_Label FROM defTerms WHERE trm_ID IN ('. $fieldDetails['dty_JsonTermIDTree'] .')');
                if(!empty($trm_names)){
                    $extra_details = ', ' . implode(' | ', $trm_names) . ' relation to ';
                }
            }
            if(array_key_exists('dty_PtrTargetRectypeIDs', $fieldDetails)){ // retrieve list of rectype names
                $rty_names = mysql__select_list2($mysqli, 'SELECT rty_Name FROM defRecTypes WHERE rty_ID IN ('. $fieldDetails['dty_PtrTargetRectypeIDs'] .')');
                if(!empty($rty_names)){
                    if(empty($extra_details)){
                        $extra_details = ', relation to ';
                    }
                    $extra_details .= implode(' | ', $rty_names);
                }
            }

            $details[$dty_ID] = array($idx=>'SEE NOTES AT START'.$extra_details);
        }elseif($dty_Type=='geo'){
            $details[$dty_ID] = array($idx=>array('geo'=>array('wkt'=>'WKT_VALUE')) );//'type'=>'TEXT',

        }elseif($dty_Type=='enum' || $dty_Type=='relationtype'){

            $extra_details = '';
            if(array_key_exists('dty_JsonTermIDTree', $fieldDetails)){ // retrieve list of vocab labels
                $trm_names = mysql__select_list2($mysqli, 'SELECT trm_Label FROM defTerms WHERE trm_ID IN ('. $fieldDetails['dty_JsonTermIDTree'] .')');
                if(!empty($trm_names)){
                    $extra_details = ' from ' . implode(' | ', $trm_names);
                }
            }

            $details[$dty_ID] = array($idx=>'VALUE'.$extra_details);
        }elseif($dty_Type=='integer' || $dty_Type=='float' || $dty_Type=='year' ){
            $details[$dty_ID] = array($idx=>'NUMERIC');
        }elseif($dty_Type=='blocktext' ){
            $details[$dty_ID] = array($idx=>'MEMO_TEXT');
        }elseif($dty_Type=='date' ){
            $details[$dty_ID] = array($idx=>'DATE');
        }else{
            $details[$dty_ID] = array($idx=>'TEXT');
        }

        $idx++;
    }
    $record['details'] = $details;

    return $record;
}


//------------------------
function recordSearchByID($system, $id, $need_details = true, $fields = null)
{
    if($fields==null){
        $fields = "rec_ID,
        rec_RecTypeID,
        rec_Title,
        rec_URL,
        rec_ScratchPad,
        rec_OwnerUGrpID,
        rec_NonOwnerVisibility,
        rec_URLLastVerified,
        rec_URLErrorMessage,
        rec_Added,
        rec_Modified,
        rec_AddedByUGrpID,
        rec_Hash,
        rec_FlagTemporary";
    }

    $mysqli = $system->get_mysqli();
    $record = mysql__select_row_assoc( $mysqli,
        "select $fields from Records where rec_ID = $id");
    if ($need_details !== false && $record) {
        recordSearchDetails($system, $record, $need_details);
    }
    return $record;
}

//
// Returns value for given field
//
function recordGetField($record, $field_id){

    $value = @$record['details'][$field_id];
    if(!isEmptyArray($value)){
        return array_shift($value);
    }else{
        return null;
    }
}

//
// load details for given record plus id,type and title for linked records
//
/*

details
dty_ID
dtl_ID=>value

value
for file  file=>ulf_ID, fileid=>ulf_ObfuscatedFileID
for resource id=>rec_ID, type=>rec_RecTypeID, title=>rec_Title
for geo   geo => array(type=> , wkt=> )

*/
/**
* Adds details element to $record array (by reference)
*
* @param mixed $system
* @param mixed $record - record array - details to be added
* @param mixed $detail_types - array of dty_ID or dty_Type or true (all details)
*/
function recordSearchDetails($system, &$record, $detail_types) {

    $mysqli = $system->get_mysqli();

    $recID = $record['rec_ID'];

    $squery =
    "select dtl_ID,
    dtl_DetailTypeID,
    dtl_Value,
    ST_asWKT(dtl_Geo) as dtl_Geo,
    dtl_UploadedFileID,
    dty_Type,
    rec_ID,
    rec_Title,
    rec_RecTypeID,
    rec_Hash
    from recDetails
    left join defDetailTypes on dty_ID = dtl_DetailTypeID
    left join Records on rec_ID = dtl_Value and dty_Type = 'resource' ";

    $swhere = " WHERE dtl_RecID = $recID";

    $relmarker_fields = array();

    if(!isEmptyArray($detail_types) ){

        if(is_numeric($detail_types[0]) && $detail_types[0]>0){ //by id

            $swhere .= SQL_AND.predicateId('dtl_DetailTypeID', $detail_types);
            $qr = SQL_RELMARKER_CONSTR.predicateId('dty_ID', $detail_types);
            $relmarker_fields =  mysql__select_all($mysqli, $qr);

        }else{ //by type
            $swhere .= ' AND dty_Type in ("'.implode('","',$detail_types).'")';
        }
    }

    //individual visibility for fields
    $rec_visibility = @$record['rec_NonOwnerVisibility'];
    $rec_owner = @$record['rec_OwnerUGrpID'];
    $rec_type = @$record['rec_RecTypeID'];

    if($rec_type!=null && $rec_type>0){

        $usr_groups = $system->get_user_group_ids();
        if(!is_array($usr_groups)) {$usr_groups = array();}
        array_push($usr_groups, 0);//everyone

        if($system->has_access() && in_array($rec_owner, $usr_groups)){
            //owner of record can see any field
            $detail_visibility_conditions = ' AND (IFNULL(rst_RequirementType,"")!="forbidden")';//ifnull needed for non-standard fields
        }else{
            $detail_visibility_conditions = array('(rst_NonOwnerVisibility IS NULL)');//not standard field
            if($system->has_access()){
                //logged in user can see viewable
                $detail_visibility_conditions[] = '(rst_NonOwnerVisibility="viewable")';
            }
            $detail_visibility_conditions[] = '((rst_NonOwnerVisibility="public" OR rst_NonOwnerVisibility="pending") AND IFNULL(dtl_HideFromPublic, 0)!=1)';

            $detail_visibility_conditions = ' AND (IFNULL(rst_RequirementType,"")!="forbidden") AND ('
            .implode(' OR ',$detail_visibility_conditions).')';
        }

        if($detail_visibility_conditions!=null){
            $squery .= 'left join defRecStructure rdr on rdr.rst_DetailTypeID = dtl_DetailTypeID and rdr.rst_RecTypeID = '.$rec_type;
            $swhere .= $detail_visibility_conditions;
        }
    }

    $squery .= $swhere;

    //main query for details
    $res = $mysqli->query($squery);

    $ruf_entity = null;
    $details = array();
    if($res){
        while ($rd = $res->fetch_assoc()) {
            // skip all invalid values
            if (( !$rd["dty_Type"] === "file" && $rd["dtl_Value"] === null ) ||
            (($rd["dty_Type"] === "enum" || $rd["dty_Type"] === "relationtype") && !$rd["dtl_Value"])) {
                continue;
            }

            if (! @$details[$rd["dtl_DetailTypeID"]]) {$details[$rd["dtl_DetailTypeID"]] = array();}

            $detailValue = null;

            switch ($rd["dty_Type"]) {
                case "blocktext":
                case "freetext":
                case "float":
                case "date":
                case "enum":
                case "relationtype":
                case "integer": case "boolean": case "year": case "urlinclude": // these shoudl no logner exist, retained for backward compatibility
                    $detailValue = $rd["dtl_Value"];
                    break;

                case "file":

                    $fileinfo = null;

                    if(!($rd['dtl_UploadedFileID']>0)){
                        // FIX on fly - @todo  remove on 2022-08-22
                        if($ruf_entity==null){
                            $ruf_entity = new DbRecUploadedFiles($system);
                        }
                        $fileinfo = $ruf_entity->registerURL($rd['dtl_Value'], false, $rd['dtl_ID']);
                    }else{
                        $fileinfo = fileGetFullInfo($system, $rd["dtl_UploadedFileID"]);
                        if(!isEmptyArray($fileinfo)){
                            $fileinfo = $fileinfo[0];//
                        }
                    }

                    if($fileinfo){
                        $detailValue = array("file" => $fileinfo, "fileid"=>$fileinfo["ulf_ObfuscatedFileID"]);
                    }

                    break;

                case "resource":
                    $detailValue = array(
                        "id" => $rd["rec_ID"],
                        "type"=>$rd["rec_RecTypeID"],
                        "title" => $rd["rec_Title"],
                        "hhash" => $rd["rec_Hash"]
                    );
                    break;

                case "geo":
                    if ($rd["dtl_Value"]  &&  $rd["dtl_Geo"]) {
                        $detailValue = array(
                            "geo" => array(
                                "type" => $rd["dtl_Value"],
                                "wkt" => $rd["dtl_Geo"]
                            )
                        );
                    }
                    break;

                case "separator":    // this should never happen since separators are not saved as details, skip if it does
                case "relmarker":    // relmarkers are places holders for display of relationships constrained in some way
                default:
                    break;
            }

            if ($detailValue!=null && $detailValue!='') {
                $details[$rd["dtl_DetailTypeID"]][$rd["dtl_ID"]] = $detailValue;
            }
        }

        //special case for RT_CMS_MENU - JOIN all descriptions
        $system->defineConstant('DT_EXTENDED_DESCRIPTION');
        if($system->defineConstant('RT_CMS_MENU') && RT_CMS_MENU==@$record['rec_RecTypeID']
        && is_array(@$details[DT_EXTENDED_DESCRIPTION]))
        {
            $details[DT_EXTENDED_DESCRIPTION] = array(implode('',$details[DT_EXTENDED_DESCRIPTION]));
        }

        $res->close();
    }



    $record["details"] = $details;
}

//
// Add inofrmation about relationship records int details section of record
//
function recordSearchDetailsRelations($system, &$record, $detail_types) {

    $mysqli = $system->get_mysqli();

    $recID = $record['rec_ID'];

    $relmarker_fields = array();

    if(is_array($detail_types) && !empty($detail_types) ){

        if(is_numeric($detail_types[0]) && $detail_types[0]>0){ //by id

            $qr = SQL_RELMARKER_CONSTR.predicateId('dty_ID',$detail_types);

        }else{ //by type

            $qr = 'SELECT dty_ID, dty_JsonTermIDTree, dty_PtrTargetRectypeIDs '
            .' FROM defDetailTypes, defRecStructure, Records'
            .' WHERE rec_ID='.$recID
            .' AND dty_ID=rst_DetailTypeID AND rst_RecTypeID=rec_RecTypeID AND dty_Type = "relmarker"';
        }

        $relmarker_fields =  mysql__select_all($mysqli, $qr);
    }

    //query for relmarkers
    if(!isEmptyArray($relmarker_fields)){
        $terms = new DbsTerms($system, dbs_GetTerms($system));

        // both directions (0), need headers
        $related_recs = recordSearchRelated($system, $recID, 0, true, 2);
        // filter out by allowed relation type and constrained record type

        foreach ($relmarker_fields as $dty_ID=>$constraints) {

            $allowed_terms = null; //$terms->treeData($constraints[1], 'set');
            $constr_rty_ids = explode(',', $constraints[2]);
            if(empty($constr_rty_ids)) {$constr_rty_ids = false;}

            //find among related record that satisfy contraints
            foreach ($related_recs['data']['direct'] as $relation){

                if(!$allowed_terms || in_array($relation->trmID, $allowed_terms)){

                    $rty_ID = $related_recs['data']['headers'][$relation->targetID][1];//rectype id
                    if(!$constr_rty_ids || in_array($rty_ID, $constr_rty_ids) ){
                        if(!@$record["details"][$constraints[0]]) {$record["details"][$constraints[0]] = array();}
                        $record["details"][$constraints[0]][] = array('id'=>$relation->targetID,
                            'type'=>$rty_ID,
                            'title'=>$related_recs['data']['headers'][$relation->targetID][0],
                            'relation_id'=>$relation->relationID);
                    }
                }
            }
            foreach ($related_recs['data']['reverse'] as $relation){

                if(!$allowed_terms || in_array($relation->trmID, $allowed_terms)){

                    $rty_ID = $related_recs['data']['headers'][$relation->sourceID][1];//rectype id
                    if(!$constr_rty_ids || in_array($rty_ID, $constr_rty_ids) ){
                        if(!@$record["details"][$constraints[0]]) {$record["details"][$constraints[0]] = array();}
                        $record["details"][$constraints[0]][] = array('id'=>$relation->sourceID,
                            'type'=>$rty_ID,
                            'title'=>$related_recs['data']['headers'][$relation->sourceID][0],
                            'relation_id'=>$relation->relationID);
                    }
                }

            }
        }
    }

}

//
//
//
function recordSearchDetailsRaw($system, $rec_ID) {

    $query =
    "select dtl_ID,dtl_DetailTypeID,dtl_Value,ST_asWKT(dtl_Geo) as dtl_Geo,dtl_UploadedFileID"
    ." from recDetails where dtl_RecID = $rec_ID";

    return mysql__select_assoc($system->get_mysqli(), $query);
}

//
// returns string with short description and links to record view and hml
// $record - rec id or record array
//
function recordLinksFileContent($system, $record){

    if(is_numeric($record)){
        $record = array("rec_ID"=>$record);
        recordSearchDetails($system, $record, array(DT_NAME));
    }

    $url = HEURIST_SERVER_URL . HEURIST_DEF_DIR . '?db='.$system->dbname().'&recID='.$record['rec_ID'];

    return 'Downloaded from: '.$system->settings->get('sys_dbName', true)."\n"
    .'Dataset ID: '.$record['rec_ID']."\n"
    .(is_array(@$record['details'][DT_NAME])?'Dataset: '.array_values($record["details"][DT_NAME])[0]."\n":'')
    .'Full metadata (XML): '.$url."\n"
    .'Human readable (html): '.($url.'&fmt=html')."\n";

}

//
// find geo in linked places
// $find_geo_by_linked_rty - if true it searches for linked RT_PLACE
//                        or it is array of rectypes defined in sys_TreatAsPlaceRefForMapping + RT_PLACE
// $find_geo_by_linked_dty - list of pointer fields search for geo limited to
//
function recordSearchGeoDetails($system, $recID, $find_geo_by_linked_rty, $find_geo_by_linked_dty) {

    $details = array();


    if ($find_geo_by_linked_rty===true && $system->defineConstant('RT_PLACE')){
        $find_geo_by_linked_rty = array(RT_PLACE);
    }

    if(isEmptyArray($find_geo_by_linked_rty)){   //search geo in linked records
        return $details;
    }

    $squery = 'SELECT rl_SourceID,dtl_DetailTypeID,dtl_Value,ST_asWKT(dtl_Geo) as dtl_Geo, '
    .'rl_TargetID,dtl_ID,rl_DetailTypeID,rl_RelationTypeID'
    .' FROM recDetails, recLinks, Records '
    .' WHERE (dtl_Geo IS NOT NULL) '
    .' AND dtl_RecID=rl_TargetID AND rl_TargetID=rec_ID AND '
    .predicateId('rec_RecTypeID',$find_geo_by_linked_rty)
    .' AND rl_SourceID = '.$recID;

    if(!isEmptyArray($find_geo_by_linked_dty)){
        $squery = $squery.' AND '
        .predicateId('rl_DetailTypeID',$find_geo_by_linked_dty);
    }

    $squery = $squery.' ORDER BY rl_ID';

    $mysqli = $system->get_mysqli();
    $res = $mysqli->query($squery);
    if(!$res){
        return $details;
    }

    while ($rd = $res->fetch_assoc()) {

        if ($rd["dtl_Value"]  &&  $rd["dtl_Geo"]) {
            $detailValue = array(
                "geo" => array(
                    "type" => $rd["dtl_Value"],
                    "wkt" => $rd["dtl_Geo"],
                    "placeID" => $rd["rl_TargetID"],
                    "pointerDtyID" => $rd["rl_DetailTypeID"],
                    "relationID" => $rd['rl_RelationTypeID']
                )
            );
            $details[$rd["dtl_DetailTypeID"]][$rd["dtl_ID"]] = $detailValue;
        }
    }
    $res->close();

    return $details;
}

//replace $IDS in $query to $recID
function __fillQuery(&$q, $recID){
    if(is_array($q)){
        foreach ($q as $idx=>$predicate){

            foreach ($predicate as $key=>$val)
            {
                if( is_array($val)){
                    __fillQuery($val, $recID);
                    $q[$idx][$key] = $val;
                }elseif( is_string($val) && $val == '$IDS') {
                    //substitute with array of ids
                    $q[$idx][$key] = $recID;
                }
            }
        }
    }elseif( is_string($q) && $q == '$IDS') {
        $q = array('ids'=>$recID);
    }
}
//
//
//
function recordSearchLinkedDetails($system, $recID, $dty_IDs, $query) {

    $dty_IDs = prepareIds($dty_IDs);

    __fillQuery($query, $recID);

    //find linked record ids
    $recs = recordSearch($system, array('detail'=>'ids', 'q'=>$query));
    $recs = $recs['data']['records'];

    $res = array();
    foreach($recs as $recid){
        $rec = array('rec_ID'=>$recid);
        recordSearchDetails($system, $rec, $dty_IDs);
        foreach($rec['details'] as $dty_ID=>$field_details){
            if(!@$res[$dty_ID]){
                $res[$dty_ID] = $field_details;
            }else{
                foreach ($field_details as $dtl_ID=>$value){
                    $res[$dty_ID][$dtl_ID] = $value;
                }
            }
        }
    }
    return $res;

}

//
// Search details for list of records
//
function recordSearchDetailsForRecIds($system, $recIDs, $dty_IDs) {

    $dty_IDs = prepareIds($dty_IDs);

    $res2 = array();
    foreach($recIDs as $recid){
        $rec = array('rec_ID'=>$recid);
        recordSearchDetails($system, $rec, $dty_IDs);

        $res = array();
        foreach($rec['details'] as $dty_ID=>$field_details){
            if(!@$res[$dty_ID]){
                $res[$dty_ID] = $field_details;
            }else{
                foreach ($field_details as $dtl_ID=>$value){
                    $res[$dty_ID][$dtl_ID] = $value;
                }
            }
        }
        $res2[$recid] = $res;


    }
    return $res2;


}

//
// load personal tags (current user) for given record ID
//
function recordSearchPersonalTags($system, $rec_ID) {

    $mysqli = $system->get_mysqli();

    return mysql__select_list2($mysqli,
        'SELECT tag_Text FROM usrRecTagLinks, usrTags WHERE '
        ."tag_ID = rtl_TagID and tag_UGrpID= ".$system->get_user_id()." and rtl_RecID = $rec_ID order by rtl_Order");
}
