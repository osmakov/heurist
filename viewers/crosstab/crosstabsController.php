<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* Corsstabs server side interface/controller
*
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/
require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../../hserv/records/search/recordSearch.php';

$system = new hserv\System();
if( !$system->init(@$_REQUEST['db']) ){
    $response = $system->getError();
}else{

    $mysqli = $system->getMysqli();
    $params = $_REQUEST;

    if(@$_REQUEST['a'] == 'minmax' ){

            $response = recordSearchMinMax( $system, $params );//recordSearch.php

    }elseif(@$_REQUEST['a'] == 'pointers' ){

            $response = recordSearchDistinctPointers( $params );

    }elseif(@$_REQUEST['a'] == 'crosstab' ){

ini_set('max_execution_time', '0');

            $response = getCrossTab( $params );

    }else{
            $response = array("status"=>HEURIST_INVALID_REQUEST, 'No proper action defined');
    }
}

header(CTYPE_JSON);
print json_encode($response);
exit;


/**
* find min amd max value for given detail type
*
* @param mixed $mysqli
* @param mixed $params : dt - detail type id
*/
/*
function recordSearchMinMax( $params){
    global $system;

    $mysqli = $system->getMysqli();

    if(@$params['dt']){

// no more rectype filter
//        $query = "select min(cast(dtl_Value as decimal)) as min, max(cast(dtl_Value as decimal)) as max from Records, recDetails where rec_ID=dtl_RecID and rec_RecTypeID="
//                .$params['rt']." and dtl_DetailTypeID=".$params['dt'];


        $query = "select min(cast(dtl_Value as decimal)) as min, max(cast(dtl_Value as decimal)) as max from recDetails where dtl_DetailTypeID=".$params['dt'];

        //@todo - current user constraints

        $res = $mysqli->query($query);
        if (!$res){
            $response = $system->addError(HEURIST_DB_ERROR, 'Search query error on min/max for crosstabs', $mysqli->error);
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
        $response = array("status"=>HEURIST_INVALID_REQUEST);
    }

   return $response;
}
*/
//
//
//
function getWhereRecordIds($params){

    $recIDs = null;

    if(@$params['recordset']){
        if(is_array($params['recordset'])){
            $recids = $params['recordset'];
        }else{
            $recids = json_decode($params['recordset'], true);
        }
        //$recIDs = explode(',',$recids['recIDs']);
        $recIDs = prepareIds($recids['recIDs']);


    }
    return $recIDs;
}


/**
* finds the list of distict record IDs for given detail type "record pointer"
*
* @param mixed $mysqli
* @param mixed $params:  dt
*/
function recordSearchDistinctPointers( $params ){
    global $system, $mysqli;

    if(@$params['dt']){

    $where = getWhereRecordIds($params);

    if($where==null){

        $currentUser = $system->getCurrentUser();

        $query = get_sql_query_clauses($mysqli, $params, $currentUser);
        $where_clause = $query["where"];

        /*remove order by
        $pos = strrpos($where, " order by ");
        if($pos){
            $where = substr($where,0,$pos);
        }*/
        $where = '(select rec_ID '.$where_clause.' )';
    }else{

        $where = '('.implode(',',$where).')';
    }

    $query = "select distinct dtl_Value as id, rec_Title as text from Records, recDetails where rec_ID=dtl_Value and dtl_DetailTypeID="
                        .intval($params['dt'])." and dtl_RecID in ".$where;

        $res = $mysqli->query($query);
        if (!$res){
            $response = $system->addError(HEURIST_DB_ERROR, "Search query error on crosstabs distinct pointers", $mysqli->error);
        }else{


            $outp = array();
            while ($row = $res->fetch_assoc()) {
                array_push($outp, $row);
            }
            $response = array("status"=>HEURIST_OK, "data"=> $outp);
            $res->close();
        }

    }else{
        $response = array("status"=>HEURIST_INVALID_REQUEST);
    }

   return $response;
}

/**
* main request to find crosstab data
*
* @param mixed $mysqli
* @param mixed $params
*               dt_page - detail type for page/groups
*               dt_col - detail type for columns
*               dt_row - detail type for rows
*               agg_mode - aggreagation mode: sum, avg, count
*               agg_field - field for avg or sum mode
*               q - current Heurist query
*/
function getCrossTab( $params){

    global $system;

    $mysqli = $system->getMysqli();

    $dt_page = @$params['dt_page'];
    if($dt_page){
        $pagefld = ", d4.dtl_Value as page";
    }else{
        $pagefld = "";
    }
    $dt_col = @$params['dt_col'];
    if($dt_col){
        $columnfld = "d1.dtl_Value as cls, ";
    }else{
        $columnfld = "0, ";
    }

    $mode = filter_var(@$params['agg_mode'], FILTER_SANITIZE_STRING);
    $issum = (($mode=="avg" || $mode=="sum") && intval(@$params['agg_field'])>0);

    if ($issum){
        $mode = ($mode=='avg'?'avg':'sum').'(cast(d3.dtl_Value as decimal(20,2)))';//.$params['agg_field'].")";
    }else{
        $mode = "count(*)";
    }

    $recIDs = getWhereRecordIds($params);
    if($recIDs!=null){
        $params['q'] = 'ids:'.implode(',',$recIDs);
    }

    $currentUser = $system->getCurrentUser();

    $query = get_sql_query_clauses($mysqli, $params, $currentUser);
    $where = $query["where"];
    $from = $query["from"];


    /*remove order by
    $pos = strrpos($where, " order by ");
    if($pos){
        $where = substr($where,0,$pos);
    }*/

$query = "select d2.dtl_Value as rws, ".$columnfld.$mode." as cnt ".$pagefld." ".$from;

$query = $query." left join recDetails d2 on d2.dtl_RecID=TOPBIBLIO.rec_ID and d2.dtl_DetailTypeID=".intval($params['dt_row']);
if($dt_col>0){
    $query = $query." left join recDetails d1 on d1.dtl_RecID=TOPBIBLIO.rec_ID and d1.dtl_DetailTypeID=".intval($dt_col);
}
if($dt_page>0){
    $query = $query." left join recDetails d4 on d4.dtl_RecID=TOPBIBLIO.rec_ID and d4.dtl_DetailTypeID=".intval($dt_page);
}
if($issum){
    $query = $query
     ." ,recDetails d3 "
    //20130517 ." where rec_RectypeID=".$params['rt']
    ." where d3.dtl_RecID=TOPBIBLIO.rec_ID and d3.dtl_Value is not null && d3.dtl_DetailTypeID=".intval($params['agg_field'])
    .SQL_AND.$where;

}else{
    $query = $query.SQL_WHERE.$where; //20130517 rec_RectypeID=".$params['rt'];
}
//20130517 $query = $query.SQL_AND.$where_2;

$query = $query." group by d2.dtl_Value ";

if($dt_col){
    $query = $query.", d1.dtl_Value";
}
if($dt_page){
    $query = $query.", d4.dtl_Value ";
}

$query = $query." order by ";

if($dt_page){
    if($params['dt_pagetype']=="integer" || $params['dt_pagetype']=="float"){
        $query = $query." cast(d4.dtl_Value as decimal(20,2)), ";
    }else{
        $query = $query." d4.dtl_Value, ";
    }
}

if($params['dt_rowtype']=="integer" || $params['dt_rowtype']=="float"){
    $query = $query." cast(d2.dtl_Value as decimal(20,2)) ";
}else{
    $query = $query." d2.dtl_Value ";
}

if($dt_col){
    if($params['dt_coltype']=="integer" || $params['dt_coltype']=="float"){
        $query = $query.", cast(d1.dtl_Value as decimal(20,2))";
    }else{
        $query = $query.", d1.dtl_Value";
    }
}

        $res = $mysqli->query($query);
        if (!$res){
            $response = $system->addError(HEURIST_DB_ERROR, "Search query error on crosstabs", $mysqli->error);
        }else{

            $outp = array();
            while ($row = $res->fetch_row()) {
                array_push($outp, $row);
            }
            $response = array("status"=>HEURIST_OK, "data"=> $outp);
            $res->close();
        }

return $response;

}
?>