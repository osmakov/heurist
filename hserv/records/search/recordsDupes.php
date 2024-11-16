<?php
/**
* recordsDupes.php
*
* Searches for duplicates by header or detail fields
* It uses metaphone, levenshtein (or @todo text_similar methods)
*
* Controller is recordVerify
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

/**
*
*  setSession - work with different database
*  findDupes  - main method
*
*/
class RecordsDupes {

    private static $system = null;
    private static $mysqli = null;
    private static $initialized = false;

    private static $defDetailtypes = null;

    private static $cache_id;
    private static $cache_str;
    private static $cache_str_exact;
    private static $all_similar_records;
    private static $all_similar_ids;
    private static $distance;
    private static $all_similar_ids_cnt;

    private static $processed;
    private static $tot_count;
    private static $limit_cnt;
    private static $progress_session_id;

    private static $dupeIgnoring = null;

//
//
//
private static function initialize()
{
    if (self::$initialized) {return;}

    global $system;
    self::$system  = $system;
    self::$mysqli = $system->getMysqli();
    self::$initialized = true;

    //check existense NEW_LIPOSUCTION_255
    checkDatabaseFunctionsForDuplications(self::$mysqli);
}

//
// set session different that current global one (to work with different database)
//
public static function setSession($system){
    self::$system  = $system;
    self::$mysqli = $system->getMysqli();
    self::$initialized = true;
}

//
// add to list of exclusions
//
public static function setIgnoring( $params ){

    if(@$params['ignore']){

        self::initialize();

        if($params['ignore']=='clear'){

                    $res = self::$mysqli->query('DELETE FROM recSimilarButNotDupes WHERE snd_SimRecsList IS NOT NULL');

                    if(!$res){
                        $response = self::$system->addError(HEURIST_DB_ERROR,
                                'Set group as ignoring. Cannot execute query', self::$mysqli->error);
                        return false;
                    }

        }else{

            $diffHash = $params['ignore'];

            $diffHash = explode(',',$diffHash);
            sort($diffHash);
            $diffHash = implode(',',$diffHash);

            $dupeIgnoring = mysql__select_value(self::$mysqli, 'SELECT snd_SimRecsList FROM recSimilarButNotDupes '
            .' WHERE snd_SimRecsList="'.$diffHash.'"');

            if($dupeIgnoring==null){

                //array_push($dupeIgnoring, $diffHash);

                $res = self::$mysqli->query('INSERT INTO recSimilarButNotDupes VALUES("'.$diffHash.'")');

                if(!$res){
                    $response = self::$system->addError(HEURIST_DB_ERROR,
                            'Set group as ignoring. Cannot execute query', self::$mysqli->error);
                    return false;
                }
            }
        }

    }
    return 1;
}

//
// Main method - finds duplications
//
// $params:
//    mode - levenshtein or metaphone
//    rty_ID
//    fields - comma separated list or array of dty_IDs and header fields (rec_Title by default)
//    dista1nce - 0 exact duplication (by default), or percent of strlen.                     1
//      So 5% for 100 characters distance is 5 chars (strlen*d/100)
//      for strlen<50  distance = 3 chars
//      for strlen<10  distance = 1
//      if defined, min distance is 1, max = 10
//
public static function findDupes( $params ){

    self::initialize();

    $rty_ID = @$params['rty_ID'];
    if(!($rty_ID>0)){
        self::$system->addError(HEURIST_INVALID_REQUEST, 'Required parameter rty_ID is missed. Define record type ID');
        return false;
    }

    $in_memory_limit = 10000;

    self::$distance = @$params['distance'];
    if(!(self::$distance>0)) {self::$distance = 0;}
    if(self::$distance>20) {self::$distance = 20;} //percentage

    $startgroup = @$params['startgroup'];
    if(!($startgroup>0)){ $startgroup = 0;}
    if($startgroup>5) {$startgroup = 5;}

    $sort_field = @$params['sort_field'];


    $fields = @$params['fields'];
    if(!$fields) {$fields = 'rec_Title';}//by default

    if(!is_array($fields)){
        $fields = explode(',', $fields);
    }

    //for record header field (rec_Title)
    $header_fields = array('');//for retrieve query
    $header_fields2 = array();//for compare query
    //for detail fields
    $detail_joins = array();
    $detail_fields = array('');
    $detail_fields2 = array();

    $dty_IDs = array();//field types

    foreach($fields as $v){
        if(is_numeric($v) && $v>0){
            $dty_IDs[] = $v;
        }
    }

    //get field types
    $dty_IDs = mysql__select_assoc2(self::$mysqli, 'select dty_ID, dty_Type from defDetailTypes where dty_ID in ('
                .implode(',',$dty_IDs).')');


    foreach($fields as $v){
        if(is_numeric($v) && $v>0){
            $p = 'd'.$v;
            $detail_joins[] = ' left join recDetails '.$p.' on rec_ID='.$p.'.dtl_RecID and '.$p.'.dtl_DetailTypeID='.$v;

            if(self::$distance>0 && (@$dty_IDs[$v]=='freetext' || @$dty_IDs[$v]=='blocktext')){
                $s = 'NEW_LIPOSUCTION_255(IFNULL('.$p.'.dtl_Value,""))';
            }else{
                $s = $p.'.dtl_Value';
            }
            $detail_fields[$v] = $s.' as '.$p; //for retrieve
            $detail_fields2[$v] = $s;

            if($sort_field==$v || $sort_field==null){
                $sort_field = $p.'.dtl_Value';
            }


        }elseif (strpos($v,'rec_')===0){

            if($v=='rec_AddedBy'){

                $s = 'rec_AddedByUGrpID';
                $header_fields[$v] = $s; //for retrieve
                $header_fields2[$v] = $s; //for compare query

            }else{

                $s = 'NEW_LIPOSUCTION_255(IFNULL('.$v.',""))';
                $header_fields[$v] = $s.' as '.$v; //for retrieve
                $header_fields2[$v] = $s; //for compare query

                if($sort_field==$v || $sort_field==null){
                    $sort_field = $v;
                }
            }
        }
    }

    $search_params = 'i';//for recid
    $search_where = array();

    $compare_fields = array();
    $exact_fields = array();
    foreach($fields as $v){

             if($v=='rec_Title' || $v=='rec_URL' || $v=='rec_ScratchPad' || $v=='rec_AddedBy'){
                 if(self::$distance>0 && $v!='rec_AddedBy'){
                    $compare_fields[] = $header_fields2[$v];
                 }else{
                    $exact_fields[] = $header_fields[$v];
                    if($v=='rec_AddedBy'){
                        $v = 'rec_AddedByUGrpID';
                    }
                    $search_where[] = '('.$v.'=?)';
                    $search_params = $search_params.'s';
                 }
             }elseif(self::$distance>0 && (@$dty_IDs[$v]=='freetext' || @$dty_IDs[$v]=='blocktext')){
                    $compare_fields[] = $detail_fields2[$v];
             }else{
                    $exact_fields[] = $detail_fields[$v];
                    $search_where[] = '(d'.$v.'.dtl_Value=?)';//$detail_fields2[
                    $search_params = $search_params.'s';
             }
    }

    $compare_mode = 0;
    //1. leven only           - one request - load ID and C1 into memory completely
    //2. equal+leven          - individual search for every loop
    //3. only  equal searches - individual search for every loop

    if(!empty($compare_fields)){

            if($sort_field==null){
                $sort_field = $compare_fields[0];
            }

            $compare_fields = count($compare_fields)>1?'CONCAT('.implode(',',$compare_fields).')':$compare_fields[0];
            $compare_fields = ', SUBSTRING('. $compare_fields .',1,255) as C1 ';

            // we use php levenshtein now
            //$search_where[] = '(ABS(CHAR_LENGTH(?)-CHAR_LENGTH(C1))<'.self::$distance.') AND '
            //    .'(LEVENSHTEIN_LIMIT(?, C1,'.self::$distance.')<'.self::$distance.')';
            //$search_params = $search_params.'ss';
            $compare_mode = 1;

            array_unshift($search_where, '(rec_ID!=?)');
    }else{
            array_unshift($search_where, '(rec_ID>?)');
            $compare_fields = '';
            $sort_field = null;
            $startgroup = 0; //only exact search - no reason group by first chars
    }
    if(!empty($exact_fields)){
        $compare_mode = ($compare_mode==1)?2:3;

        if($compare_mode==3){ //each field separately
            $exact_fields = ', '.implode(',',$exact_fields);
        }else{
            //remove "as dXXX" otherwise CONCAT doesn't work
            foreach($exact_fields as $idx=>$fld){
                $k = strpos($fld,' as ');
                if($k>0) {$exact_fields[$idx] = substr($exact_fields[$idx],0,$k);}
            }

            $exact_fields = ', '.(count($exact_fields)>1?'CONCAT('.implode('|',$exact_fields).')':$exact_fields[0]);
        }


    }else{
        $exact_fields = '';
    }

    if($compare_mode==0){
        self::$system->addError(HEURIST_INVALID_REQUEST, 'Required parameter "fields" value is missing or invalid and no field found');
        return false;
    }


    //1. search for all records and create temporary table
    $query = ' from Records '.implode(' ',$detail_joins)
    .' where (rec_RecTypeID ='.$rty_ID.') and (not rec_FlagTemporary)';


    self::$tot_count = mysql__select_value(self::$mysqli, 'SELECT count(rec_ID) '.$query);

    if(!(self::$tot_count>1)){
        $response = self::$system->addError(HEURIST_NOT_FOUND,
                (self::$tot_count==1?'Only one record':'No records').' found for record type '.$rty_ID);
        return false;
    }
    if(self::$tot_count >= $in_memory_limit && $compare_mode<3 && $sort_field==null){

        $response = self::$system->addError(HEURIST_ACTION_BLOCKED, self::$tot_count);
        return false;
    }


    $main_query = $query;

    $query = ' rec_ID '.$compare_fields.' '.$exact_fields.$query;

    if($sort_field!=null){
          $query = $query." ORDER BY $sort_field, rec_ID asc";
    }else{
          $query = $query.' order by rec_ID asc';
    }


        //self::$mysqli->query('DROP TABLE IF EXISTS tmp_find_dupes');
        //$query = 'CREATE TEMPORARY TABLE tmp_find_dupes (PRIMARY KEY find_dupes_pkey (rec_ID)) SELECT '.$query;
            //.' SET utf8 COLLATE utf8_general_ci';

    $query = 'SELECT '.$query;
    $res = self::$mysqli->query($query);

    if(!$res){

        $response = self::$system->addError(HEURIST_DB_ERROR,
                'Search duplications. Cannot execute main query', self::$mysqli->error);
        return false;
    }

    self::$dupeIgnoring = mysql__select_list2(self::$mysqli, 'SELECT snd_SimRecsList FROM recSimilarButNotDupes');

    self::$all_similar_ids = array();// plain array of ids of similar records (to facilitate search)
    self::$all_similar_ids_cnt = 0;       //
    self::$all_similar_records = array();//result: grouped similar records - rec_ID=>rec_Title

    //limits
    //max allowed similar records
    // total and pecentage
    self::$limit_cnt = @$params['limit_cnt'];
    if(!(self::$limit_cnt>0)) {self::$limit_cnt = 1000;}
    elseif(self::$limit_cnt>3000) {self::$limit_cnt = 3000;}

    $limit_pc = @$params['limit_cnt'];
    if(!($limit_pc>0)) {$limit_pc = 30;}
    elseif($limit_pc>50) {$limit_pc = 50;}


    self::$progress_session_id = @$params['session'];
    $msg_termination = null;
    self::$processed = 0;

    //3. loop for records
    if(!$res){

        $response = self::$system->addError(HEURIST_DB_ERROR,
                'Search duplications (base query for records)', self::$mysqli->error);
        return false;

    }else{

        $limit_pc = self::$tot_count*$limit_pc/100;
        self::$limit_cnt = min(self::$limit_cnt, $limit_pc);


        if(self::$progress_session_id){
            //init progress session
            mysql__update_progress(null, self::$progress_session_id, true, '0,'.self::$tot_count);
        }

        //load into memory
        if($compare_mode<3)
        {
            self::$cache_id = array();
            self::$cache_str = array();
            self::$cache_str_exact = array();

            //($startgroup>0)    ordered by C1 and recID
            $curr_c1 = null;
            $cache_cnt = 0;
            $is_reset = false;

            while ($row = $res->fetch_row()) {  //main query

                if($row[1]==''){
                    continue;
                }


                if($cache_cnt>=$in_memory_limit){

                   $is_reset = true;
                   $cache_cnt = 0;

                }elseif($startgroup>0){
                    //strcasecmp()
                    $str1 = mb_strtolower(mb_substr($row[1], 0, $startgroup));
                    if($str1!=$curr_c1){
                        $curr_c1 = $str1;
                        $is_reset = true;
                    }
                }
                if($is_reset){
                        $is_reset = false;
                        //start search
                        $rep = self::_searchInCache();
                        if($rep>0){
                            $msg_termination = ($rep==2);
                            break;
                        }

                        //reset
                        self::$cache_id = array();
                        self::$cache_str = array();
                        self::$cache_str_exact = array();
                }

                self::$cache_id[] = $row[0];//array($row[0]=>$row[1]); rec_ID
                self::$cache_str[] = $row[1];//array($row[0]=>$row[1]); C1
                if($compare_mode==2) {self::$cache_str_exact[] = $row[2];}
                $cache_cnt++;
            }//while

            if(count(self::$cache_id)>0){
                $rep = self::_searchInCache();
                $msg_termination = ($rep==2);
            }

        }else{
            //exact search only

            //3. create search query    uss $compare_fields
            $search_query = 'SELECT rec_ID '.($compare_mode<3?(', '.$compare_fields):'').$main_query; //' FROM tmp_find_dupes WHERE ';
            $search_query = $search_query.' AND ('. implode(SQL_AND, $search_where) .')';

            /*
            if($startgroup>0){ //limit search query to records that starts with the same characters
                $search_query = $search_query.'AND (SUBSTRING(NEW_LIPOSUCTION_255(IFNULL(rec_Title,"")),1,'.$startgroup.') = ?)';
                //$search_query = $search_query.' AND (C1 LIKE ?)';
                $search_params = $search_params.'s';
            }
            */

            //4. prepare query
            $stmt = self::$mysqli->prepare($search_query);
            if(!$stmt){
                    self::$system->addError(HEURIST_DB_ERROR, 'Cannot prepare query to find duplication records',
                        self::$mysqli->error);
                    return false;
            }

            //----------------------------
            //
            //
            $str1 = '';
            $len1 = 0;
            $dist = 0;

            while ($row = $res->fetch_row()) {  //main query

                $curr_recid = intval($row[0]);

                //exclude this record since it is already included in other group
                if(count(self::$all_similar_ids)>0){
                    $idx = array_search($curr_recid,  self::$all_similar_ids, true);
                    if($idx>0){
                        self::$processed++;
                        continue;


                    }
                }

                /*
                if($compare_mode<3){ //need levenshtein
                    $str1 = $row[1];
                    array_splice($row,1,1);//get C1 and remove it from array
                    $len1 = strlen($str1);
                    $dist = ceil($len1*self::$distance/100);
                    if($dist==0){
                        $dist = 1;
                    }elseif($dist>10){
                        $dist = 10;
                    }

                    if($startgroup>0){ //add value for C1 LIKE
                        //$row[0] = 0;
                        $row[] = substr($str1, 0, $startgroup);
                    }
                }
                */
                //fill values array
                array_unshift($row, $search_params);//add as a first element - list of parameter types

                $group = null;
                //Call the $stmt->bind_param() method with atrguments (string $types, mixed &...$vars)
                call_user_func_array(array($stmt, 'bind_param'), referenceValues($row));
                if(!$stmt->execute()){
                    self::$system->addError(HEURIST_DB_ERROR, 'Cannot execute query to find duplication records',
                        self::$mysqli->error);
                    return false;
                }
                $res2 = $stmt->get_result();
                if ($res2){
                    $group = array($curr_recid);
                    while ($row2 = $res2->fetch_row()){ //search loop

                        /*
                        if($compare_mode<3){ //need levenshtein
                            $str2 = $row2[1];
                            if(abs($len1-strlen($str2))<=$dist){
                                $d = levenshtein($str1, $str2);
                                if($d<=$dist){
                                    $group[] = $row2[0];//for mix compare mode
                                }
                            }
                        }else{
                            $group[] = $row2[0];//rec_ID - for exact compare mode
                        }*/

                        $group[] = $row2[0];//rec_ID - for exact compare mode

                    }
                    $res2->close();
                }

                //NP $group = mysql__select_assoc2(self::$mysqli, $query);
                if($group && count($group)>1){

                    sort($group);
                    $diffHash = implode(',',$group);
                    if (is_array(self::$dupeIgnoring) && !in_array($diffHash, self::$dupeIgnoring))
                    {
                        self::$all_similar_ids = array_merge(self::$all_similar_ids, $group);//add new set of ids except first (current rec_id)

                        //find titles
                        $group = mysql__select_assoc2(self::$mysqli,
                            'select rec_ID, rec_Title from Records where rec_ID in ('.implode(',',$group).')');

                        self::$all_similar_records[] = $group; //id=>title
                        self::$all_similar_ids_cnt = self::$all_similar_ids_cnt + count($group);
                    }
                }

                self::$processed++;

                //update session and check for termination
                if(self::$progress_session_id && (self::$processed % 10 == 0)){
                    $session_val = self::$processed.','.self::$tot_count;
                    $current_val = mysql__update_progress(null, self::$progress_session_id, false, $session_val);
                    if($current_val && $current_val=='terminate'){
                        $msg_termination = 'Operation is terminated by user';
                        break;
                    }
                }

                if(self::$all_similar_ids_cnt>self::$limit_cnt){
                    break;
                }

    /*
            ON a.rec_ID != b.rec_ID AND b.rec_RecTypeID=10
        AND NEW_LEVENSHTEIN(NEW_LIPOSUCTION_255(a.rec_Title), NEW_LIPOSUCTION_255(b.rec_Title))<5
    WHERE a.rec_ID=:XXX'
    */
            }//while


            $res->close();

        }

        //add info
        self::$all_similar_records['summary'] = array(
            'scope'=>self::$tot_count,
            'cnt_groups'=>count(self::$all_similar_records),
            'cnt_records'=>self::$all_similar_ids_cnt,
            'limit'=>self::$limit_cnt,
            'is_terminated'=>($msg_termination!=null)
        );

        if(self::$progress_session_id){
            //remove session file
            mysql__update_progress(null, self::$progress_session_id, false, 'REMOVE');
        }
    }//if $res

    return self::$all_similar_records;

}//findDupes

//
//
//
private static function _searchInCache(){

    foreach (self::$cache_id as $idx=>$curr_recid){

        $group = array();
        //$group2 = array();

        $str1 = self::$cache_str[$idx];

        $str_exact = @self::$cache_str_exact[$idx];


        if($curr_recid==20 || $curr_recid==1169){

            $w = 1;
        }


        $len1 = strlen($str1);
        if($len1>255){
            $str1 = substr($str1,0,255);
            $len1 = 255;
        }
        $cnt = count(self::$cache_id);


        $i = array_search($curr_recid,  self::$all_similar_ids, true);
        if($i==false && $len1>2){

            $dist = ceil($len1*self::$distance/100);//difference % set in client side
            if($dist==0){
                $dist = 1;
            }elseif($dist>10){
                $dist = 10;
            }

            for ($idx2=$idx+1; $idx2<$cnt; $idx2++){

                if($str_exact==null || ($curr_recid!=self::$cache_id[$idx2] && $str_exact==@self::$cache_str_exact[$idx2]))
                {
                    $str2 = self::$cache_str[$idx2];

                    $len2 = strlen($str2);
                    if($len2>255){
                        $str2 = substr($str2,0,255);
                        $len2 = 255;
                    }

                    if(abs($len1-$len2)<=$dist){
                        $d = levenshtein($str1, $str2);
                        if($d<=$dist){
                            $group[] = self::$cache_id[$idx2];
                            //$group2[self::$cache_id[$idx2]] = '('.$d.'  '.$dist.')';
                        }
                    }
                }

            }
        }

        if(!empty($group)){
            array_unshift($group, $curr_recid);//add current

            sort($group);
            $diffHash = implode(',',$group);
            if (is_array(self::$dupeIgnoring) && !in_array($diffHash, self::$dupeIgnoring))
            {

                self::$all_similar_ids = array_merge(self::$all_similar_ids, $group);//add new set of ids except first (current rec_id)

                //find titles
                $group = mysql__select_assoc2(self::$mysqli,'select rec_ID, rec_Title from Records where rec_ID in ('
                            .implode(',',$group).')');

                foreach ($group as $recid=>$title){
                    $group[$recid] = $title;    //@$group2[$recid].'   '.$title;
                }


                self::$all_similar_records[] = $group; //id=>title
                self::$all_similar_ids_cnt = self::$all_similar_ids_cnt + count($group);

                if(self::$all_similar_ids_cnt>self::$limit_cnt){
                    return 1;
                }

            }
        }

        self::$processed++;


        //update session and check for termination
        if(self::$progress_session_id && (self::$processed % 10 == 0)){
            $session_val = self::$processed.','.self::$tot_count;
            $current_val = mysql__update_progress(null, self::$progress_session_id, false, $session_val);
            if($current_val && $current_val=='terminate'){
                $msg_termination = 'Operation is terminated by user';
                return 2;
            }
        }
    }  //for

    return 0;
}

public static function exportList($params){

    if(!array_key_exists('export', $params)){
        self::$system->addError(HEURIST_INVALID_REQUEST, 'Invalid request to export duplicates list');
        return false;
    }
    if(!defined('HEURIST_SCRATCH_DIR')){
        self::$system->addError(HEURIST_ACTION_BLOCKED, 'Unable to write to temporary space');
        return false;
    }

    $duplicates = self::findDupes($params);
    if(!$duplicates){
        return false;
    }elseif($duplicates['summary']['cnt_records'] == 0){
        self::$system->addError(HEURIST_ACTION_BLOCKED, 'No duplicate records found for exporting');
        return false;
    }

    $fd = fopen('php://output', 'w');
    if(!$fd){
        header(CTYPE_HTML);
        echo 'Unable to open temporary output for writing TSV.<br>Please contact the Heurist team.';
        return false;
    }

    // Add headers
    fputcsv($fd, ['Record ID', 'Record title', 'View record', 'Merge group', 'Search group', 'Ignore group', 'Instant merge (replace record_to_keep with record ID to keep)'], "\t");

    unset($duplicates['summary']);

    foreach($duplicates as $records){

        $all_group_IDs = implode(',', array_keys($records));

        $merge_URL = HEURIST_BASE_URL . "admin/verification/combineDuplicateRecords.php?bib_ids={$all_group_IDs}&db=" . HEURIST_DBNAME;
        $search_URL = HEURIST_BASE_URL . "?w=all&q=ids:{$all_group_IDs}&db=" . HEURIST_DBNAME;
        $ignore_URL = HEURIST_BASE_URL . "hserv/controller/recordVerify.php?a=dupes&ignore={$all_group_IDs}&db=" . HEURIST_DBNAME;
        $instant_URL = HEURIST_BASE_URL . "admin/verification/combineDuplicateRecords.php?bib_ids={$all_group_IDs}&instant_merge=1&db=" . HEURIST_DBNAME . "&master_rec_id=record_to_keep";

        foreach($records as $rec_ID => $rec_Title) {

            $rec_URL = HEURIST_BASE_URL . "viewers/record/viewRecord.php?recID={$rec_ID}&db=" . HEURIST_DBNAME;
            fputcsv($fd, [$rec_ID, $rec_Title, $rec_URL, $merge_URL, $search_URL, $ignore_URL, $instant_URL], "\t");

            $merge_URL = '';
            $search_URL = '';
            $ignore_URL = '';
            $instant_URL = '';
        }

        fwrite($fd, "\n\n");
    }

    // Get content, length and close resource
    rewind($fd);
    $output = stream_get_contents($fd);
    fclose($fd);

    $filename = HEURIST_DBNAME . '_Duplicate_Records.tsv';

    dataOutput($output, $filename, 'text/tab-separated-values');
}

} //end class
?>
