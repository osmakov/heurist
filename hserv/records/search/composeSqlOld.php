<?php
/**
* composeSqlOld.php - translates heurist query to SQL query
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Stephen White
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.1
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

define('BOOKMARK', 'bookmark');
define('NO_BOOKMARK', 'nobookmark');
define('BIBLIO', 'biblio');//outdated @toremove
define('EVERYTHING', 'everything');


define('SORT_FIXED', 'set');
define('SORT_POPULARITY', 'p');
define('SORT_RATING', 'r');
define('SORT_URL', 'u');
define('SORT_MODIFIED', 'm');
define('SORT_ADDED', 'a');
define('SORT_TITLE', 't');
define('SORT_ID', 'id');
define('REGEX_CSV', '/^\d+(?:,\d*)+$/');

define('SQL_RL_SOURCE_LINK',' rl.rl_SourceID=rd.rec_ID ');
define('SQL_RL_TARGET_LINK',' rl.rl_TargetID=rd.rec_ID ');
define('SQL_RELATION_IS_NULL',' rl.rl_RelationID IS NULL ');
define('SQL_RELATION_IS_NOT_NULL',' rl.rl_RelationID IS NOT NULL ');
define('SQL_RECLINK',' recLinks rl ');
define('SQL_RECORDS',' FROM Records rd ');

define('REC_MODIFIED','f:modified');

//defined in const.php define('DT_RELATION_TYPE', 6);
global $mysqli, $currUserID, $sortType;

$mysqli = null;
$currUserID = 0;
$sortType = 0;

/**
* Use the supplied _REQUEST variables (or $params if supplied) to construct a query starting with $query prefix
*
* @param mixed $query  -  prefix (usually SELECT with list of fields)
* @param mixed $params
*
parameters:

stype  - (OUTDATED) type of search: key - by tag title, all - by title of record and titles of its resource, by default by record title
s - sort order   (NOTE!!!  sort may be defined in "q" parameter also)
l or limit  - limit of records
o or offset
w - domain of search a|all, b|bookmark, e (everything)

qq - several conjunctions and disjunctions
q  - query string

keywords for 'q' parameter
url:  url
title: title contains
t:  record type id
f:   field id
tag:   tag
id:  id
n:   description
usr:   user id
any:
relatedto:
sortby:

*
* @param mixed $currentUser - array with indexes ugr_ID, ugr_Groups (list of group ids)
*                       we can access; Records records marked with a rec_OwnerUGrpID not in this list are omitted
*
*/
function compose_sql_query($db, $select_clause, $params, $currentUser=null) {

    $query = get_sql_query_clauses($db, $params, $currentUser=null);

    $res_query =  $select_clause.$query["from"].SQL_WHERE.$query["where"].$query["sort"].$query["limit"].$query["offset"];
    return $res_query;
}
/**
* Use the supplied _REQUEST variables (or $params if supplied) to construct a query starting with $query prefix
*
* @param mixed $query  -  prefix (usually SELECT with list of fields)
* @param mixed $params
*
parameters:

stype  - (OUTDATED) type of search: key - by tag title, all - by title of record and titles of its resource, by default by record title
s - sort order   (NOTE!!!  sort may be defined in "q" parameter also)
l or limit  - limit of records
o or offset
w - domain of search a|all, b|bookmark, e (everything)

qq - several conjunctions and disjunctions
q  - query string

keywords for 'q' parameter
url:  url
title: title contains
t:  record type id
f:   field id
tag:   tag
id:  id
n:   description
usr:   user id
any:
relatedto:
sortby:

*
* @param mixed $currentUser - array with indexes ugr_ID, ugr_Groups (list of group ids)
*                       we can access; Records records marked with a rec_OwnerUGrpID not in this list are omitted
*/
function get_sql_query_clauses($db, $params, $currentUser=null) {

    global $mysqli, $currUserID, $sortType;

    $mysqli = $db;

    /* use the supplied _REQUEST variables (or $params if supplied) to construct a query starting with $select_clause */
    if (! $params) {$params = array();} // $_REQUEST
    if(@$params['stype']) {$sortType = @$params['stype'];}

    // 1. DETECT CURRENT USER AND ITS GROUPS, if not logged search only all records (no bookmarks) ----------------------
    $wg_ids = array();//may be better use $system->getUserGroupIds() ???
    if($currentUser && @$currentUser['ugr_ID']>0){
        if(@$currentUser['ugr_Groups']){
            $wg_ids = array_keys($currentUser['ugr_Groups']);
        }
        $currUserID = $currentUser['ugr_ID'];
        array_push($wg_ids, $currUserID);
    }else{
        $currUserID = 0;
        $params['w'] = 'all';
    }
    array_push($wg_ids, 0);// be sure to include the generic everybody workgroup

    $publicOnly = (@$params['publiconly']==1);//@todo

    // 2. DETECT SEARCH DOMAIN ------------------------------------------------------------------------------------------
    if (strcasecmp(@$params['w'],'B') == 0  ||  strcasecmp(@$params['w'],BOOKMARK) == 0) {    // my bookmark entries
        $search_domain = BOOKMARK;
    } elseif (@$params['w'] == 'e') { //everything - including temporary
        $search_domain = EVERYTHING;
    } else {                // all records entries
        $search_domain = null;
    }

    //for database owner we will search records of any workgroup and view access
    //@todo UNLESS parameter owner is not defined explicitely
    if($currUserID==2 && $search_domain != BOOKMARK){
        $wg_ids = array();
    }

    // 3a. SPECIAL CASE for _BROKEN_

    $needbroken = false;
    if (@$params['q'] && preg_match('/\\b_BROKEN_\\b/', $params['q'])) {
        $params['q'] = preg_replace('/\\b_BROKEN_\\b/', '', $params['q']);
        $needbroken = true;
    }
    // 3b. SPECIAL CASE for _NOTLINKED_

    $neednotlinked = false;
    if (@$params['q'] && preg_match('/\\b_NOTLINKED_\\b/', $params['q'])) {
        $params['q'] = preg_replace('/\\b_NOTLINKED_\\b/', '', $params['q']);
        $neednotlinked = true;
    }

    // 4. QUERY MAY BE SIMPLE or full expressiveness ----------------------------------------------------------------------

    $query = parse_query($search_domain, @$params['q'], @$params['s'], @$params['parentquery'], $currUserID);

    $where_clause = $query->where_clause;

    // 4a. SPECIAL CASE for _BROKEN_
    if($needbroken){
        $where_clause = ' (rec_URLErrorMessage is not null) '
        . ($where_clause? SQL_AND.$where_clause :'');
        //'(to_days(now()) - to_days(rec_URLLastVerified) >= 8) '
    }
    // 4b. SPECIAL CASE for _NOTLINKED_
    if($neednotlinked){
        $where_clause = '(not exists (select rl_ID from recLinks where rl_SourceID=TOPBIBLIO.rec_ID  or rl_TargetID=TOPBIBLIO.rec_ID )) '
            . ($where_clause? SQL_AND.$where_clause :'');
    }

    // 4c. SPECIAL CASE for USER WORKSET
    if(@$params['use_user_wss']===true && $currUserID>0){

        $q2 = "select wss_RecID from usrWorkingSubsets where wss_OwnerUGrpID=$currUserID LIMIT 1";
        if(mysql__select_value($mysqli, $q2)>0){
            $where_clause = '(exists (select wss_RecID from usrWorkingSubsets where wss_RecID=TOPBIBLIO.rec_ID and wss_OwnerUGrpID='.$currUserID.'))'
                . ($where_clause? SQL_AND.$where_clause :'');
        }
    }

    // 5. DEFINE USERGROUP RESTRICTIONS ---------------------------------------------------------------------------------

    if ($search_domain != EVERYTHING) {

        if ($where_clause) {$where_clause = "( $where_clause ) and ";}

        if ($search_domain == BOOKMARK) {
            $where_clause .= ' (bkm_UGrpID=' . $currUserID . ' and not TOPBIBLIO.rec_FlagTemporary) ';
        } elseif($search_domain == BIBLIO) {   //NOT USED
            $where_clause .= ' (bkm_UGrpID is null and not TOPBIBLIO.rec_FlagTemporary) ';
        } else {
            $where_clause .= ' (not TOPBIBLIO.rec_FlagTemporary) ';
        }

    }

    if($publicOnly){
        $query->recVisibilityType = "public";
    }

    $where2 = '';
    $where2_conj = '';

    if($query->recVisibilityType && $currUserID>0){

        if($query->recVisibilityType=="public"){
            $where2 = '(TOPBIBLIO.rec_NonOwnerVisibility="'.$query->recVisibilityType.'")';//'pending','public','viewable'
        }else{

            if($query->recVisibilityType=='viewable'){

                $query->from_clause = $query->from_clause.' LEFT JOIN usrRecPermissions ON rcp_RecID=TOPBIBLIO.rec_ID ';

                //if there is entry for record in usrRecPermissions current user must be member of allowed groups
                $where2 = '(TOPBIBLIO.rec_NonOwnerVisibility="viewable"';
                if(!empty($wg_ids)){
                    $where2 .= ' and (rcp_UGrpID is null or rcp_UGrpID in ('.join(',', $wg_ids).'))';
                }
                $where2 .= ')';

                $where2_conj = SQL_AND;
            }else{
                $where2 = '(TOPBIBLIO.rec_NonOwnerVisibility="'.$query->recVisibilityType.'")';
                $where2_conj = SQL_AND;
            }

            if(!isEmptyArray($wg_ids)){
                $where2 = '( '.$where2.$where2_conj.'TOPBIBLIO.rec_OwnerUGrpID ';
                if(count($wg_ids)>1){
                    $where2 = $where2 . 'in (' . join(',', $wg_ids).') )';
                }else{
                    $where2 = $where2 .' = '.$wg_ids[0] . ' )';
                }
            }
        }


    }else{
        //visibility type not defined - show records visible for current user
        if($currUserID!=2){
            $where2 = '(TOPBIBLIO.rec_NonOwnerVisibility in ("public","pending"))';//any can see public

            if ($currUserID>0){ //logged in can see viewable
                $query->from_clause = $query->from_clause.' LEFT JOIN usrRecPermissions ON rcp_RecID=TOPBIBLIO.rec_ID ';
                //if there is entry for record in usrRecPermissions current user must be member of allowed groups
                $where2 = $where2.' or (TOPBIBLIO.rec_NonOwnerVisibility="viewable" and (rcp_UGrpID is null or rcp_UGrpID in ('
                        .join(',', $wg_ids).')))';
            }
            $where2_conj = ' or ';

        }elseif($search_domain != BOOKMARK){ //database owner can search everything (including hidden)
            $wg_ids = array();
        }

        if(!isEmptyArray($wg_ids) && $currUserID>0){
            //for hidden
            $where2 = '( '.$where2.$where2_conj.'TOPBIBLIO.rec_OwnerUGrpID ';
            if(count($wg_ids)>1){
                $where2 = $where2 . 'in (' . join(',', $wg_ids).') )';
            }else{
                $where2 = $where2 .' = '.$wg_ids[0] . ' )';
            }
        }
    }

    if($where2!=''){
        $where_clause = $where_clause . SQL_AND . $where2;
    }

    // 6. DEFINE LIMIT AND OFFSET ---------------------------------------------------------------------------------------

    $limit = get_limit($params);

    $offset = get_offset($params);


    // 7. COMPOSE QUERY  ------------------------------------------------------------------------------------------------
    return array("from"=>$query->from_clause, "where"=>$where_clause, "sort"=>$query->sort_clause, "limit"=>" LIMIT $limit", "offset"=>($offset>0? " OFFSET $offset " : ""));

}

function get_limit($params){
    if (@$params["l"]) {
        $limit = intval($params["l"]);
    }elseif(@$params["limit"]) {
        $limit = intval($params["limit"]);
    }

    if (!@$limit || $limit < 1){
        $limit = 100000;
    }
    return $limit;
}

function get_offset($params){
    $offset = 0;
    if (@$params["o"]) {
        $offset = intval($params["o"]);
    }elseif(@$params["offset"]) {
        $offset = intval($params["offset"]);// this is back in since hml.php passes through stuff from sitemap.xmap
    }
    if (!@$offset || $offset < 1){
        $offset = 0;
    }
    return $offset;
}

/**
* Returns array with 3 elements: FROM, WHERE and ORDER BY
*
* @param mixed $search_domain -   bookmark - searcch my bookmarks, otherwise all records
* @param mixed $text     - query string
* @param mixed $sort_order
* $parentquery - array of SQL clauses of parent/top query - it is needed for linked and relation queries that are depended on source/top query
* @$currUserID
* NOTUSED @param mixed $wg_ids is a list of the workgroups we can access; records records marked with a rec_OwnerUGrpID not in this list are omitted
*/
function parse_query($search_domain, $text, $sort_order, $parentquery, $currUserID) {

    if($sort_order==null) {$sort_order = '';}

    // remove any  lone dashes outside matched quotes.
    $text = preg_replace('/- (?=[^"]*(?:"[^"]*"[^"]*)*$)|(?:-\s*$)/', ' ', $text);
    // divide the query into dbl-quoted and other (note a dash(-) in front of a string is preserved and means negate)
    preg_match_all('/(-?"[^"]+")|([^" ]+)/',$text,$matches);
    $preProcessedQuery = "";
    $connectors = array(":",">","<","=",",");
    foreach ($matches[0] as $queryPart) {
        //if the query part is not a dbl-quoted string (ignoring a preceeding dash and spaces)
        //necessary since we want double quotes to allow all characters
        if (!preg_match('/^\s*-?".*"$/',$queryPart)) {
            // clean up the query.
            // liposuction out all the non-kocher characters
            // (this means all punctuation except -, _, %(45), () (50,51) :, ', ", = and ,  ...?)
            $queryPart = preg_replace('/[\000-\041\043-\044\046\052-\053\073\077\100\133\135\136\140\173-\177]+/s', ' ', $queryPart);
        }
        //reconstruct the string
        $addSpace = $preProcessedQuery != "" && !in_array($preProcessedQuery[strlen($preProcessedQuery)-1],$connectors) && !in_array($queryPart[0],$connectors);
        $preProcessedQuery .= ($addSpace ? " ":"").$queryPart;
    }
    if(trim($preProcessedQuery)==''){
        $preProcessedQuery = '"'.$text.'"';
    }


    $query = new Query($search_domain, $preProcessedQuery, $currUserID, $parentquery);
    $query->makeSQL();

    $q = null;

    if ($query->sort_phrases) {
        // already handled in Query logic
    } elseif (preg_match('/^f:(\d+)/', $sort_order, $matches)) {
        //mindfuck!!!! - sort by detail?????
        $q = 'ifnull((select if(link.rec_ID is null, dtl_Value, link.rec_Title) from recDetails left join Records link on dtl_Value=link.rec_ID where dtl_RecID=TOPBIBLIO.rec_ID and dtl_DetailTypeID='.$matches[1].' ORDER BY link.rec_Title limit 1), "~~"), rec_Title';
    } else {
        if ($search_domain == BOOKMARK) {
            switch ($sort_order) {
                case SORT_FIXED:
                    if($query->fixed_sortorder){
                        $q = "FIND_IN_SET(TOPBIBLIO.rec_ID, '{$query->fixed_sortorder})";
                    }else{
                        $q = null;
                    }
                    break;
                case SORT_POPULARITY:
                    $q = 'rec_Popularity desc, rec_Added desc'; break;
                case SORT_RATING:
                    $q = 'bkm_Rating desc'; break;
                case SORT_URL:
                    $q = 'rec_URL is null, rec_URL'; break;
                case SORT_MODIFIED:
                    $q = 'bkm_Modified desc'; break;
                case SORT_ADDED:
                    $q = 'bkm_Added desc'; break;
                case SORT_ID:
                    $q = 'rec_ID asc'; break;
                case SORT_TITLE: default:
                    $q = 'rec_Title = "", rec_Title';
            }
        } else {
            switch ($sort_order) {
                case SORT_FIXED:
                    if($query->fixed_sortorder){
                        $q = "FIND_IN_SET(TOPBIBLIO.rec_ID, '{$query->fixed_sortorder}')";
                    }else{
                        $q = null;
                    }
                    break;
                case SORT_POPULARITY:
                    $q = 'rec_Popularity desc, rec_Added desc'; break;
                case SORT_URL:
                    $q = 'rec_URL is null, rec_URL'; break;
                case SORT_MODIFIED:
                    $q = 'rec_Modified desc'; break;
                case SORT_ADDED:
                    $q = 'rec_Added desc'; break;
                case SORT_ID:
                    $q = 'rec_ID asc'; break;
                case SORT_TITLE: default:
                    $q = 'rec_Title = "", rec_Title';
            }
        }

    }
    if($q){ //sort defined in separate request param
        $query->sort_clause = ' ORDER BY '.$q;
    }
    return $query;
}


class Query {

    public $from_clause = '';
    public $where_clause = '';
    public $sort_clause = '';
    public $recVisibilityType;
    public $parentquery = null;

    public $top_limbs;
    public $sort_phrases;
    public $sort_tables;

    public $fixed_sortorder = null;


    public function __construct($search_domain, $text, $currUserID, $parentquery, $absoluteStrQuery = false) {

        $this->search_domain = $search_domain;
        $this->recVisibilityType = null;
        $this->currUserID = $currUserID;
        $this->absoluteStrQuery = $absoluteStrQuery;
        $this->parentquery = $parentquery;

        $this->top_limbs = array();
        $this->sort_phrases = array();
        $this->sort_tables = array();

        // Find any 'vt:' phrases in the query, and pull them out.   vt - visibility type
        while (preg_match('/\\G([^"]*(?:"[^"]*"[^"]*)*)\\b(vt:(?:f:|field:|geo:)?"[^"]+"\\S*|vt:\\S*)/', $text, $matches)) {
            $this->addVisibilityTypeRestriction(substr($matches[2],3));
            $text = preg_replace('/\bvt:\S+/i', '', $text);
            //$text = $matches[1] . substr($text, strlen($matches[1])+strlen($matches[2]));
        }

        // Find any 'sortby:' phrases in the query, and pull them out.
        // "sortby:..." within double quotes is regarded as a search term, and we don't remove it here
        while (preg_match('/\\G([^"]*(?:"[^"]*"[^"]*)*)\\b(sortby:(?:f:|field:)?"[^"]+"\\S*|sortby:\\S*)/', $text, $matches)) {

            $this->addSortPhrase($matches[2]);
            $text = $matches[1] . substr($text, strlen($matches[1])+strlen($matches[2]));
        }

        // Search-within-search gives us top-level ANDing (full expressiveness of conjunctions and disjunctions)
        // except matches between quotes
        preg_match_all('/"[^"]+"|(&&|\\bAND\\b)/i', $text, $matches, PREG_OFFSET_CAPTURE);
        $q_bits = array();
        $offset = 0;
        if(!empty($matches[1])){
            foreach($matches[1] as $entry){
                if(is_array($entry)){ //
                    array_push($q_bits, substr($text, $offset, $entry[1]-$offset));
                    $offset = $entry[1]+strlen($entry[0]);
                }
            }
        }
        if($offset<strlen($text)){
            array_push($q_bits, substr($text, $offset));
        }

        foreach ($q_bits as $q_bit) {
            $this->addTopLimb($q_bit);
        }

    }

    private function addTopLimb($text) {

        $or_limbs = array();
        // According to WWGD, OR is the top-level delimiter (yes, more top-level than double-quoted text)
        preg_match_all('/"[^"]+"|(&&|\\ OR \\b)/i', $text, $matches, PREG_OFFSET_CAPTURE);
        $offset = 0;
        if(!empty($matches[1])){

            foreach($matches[1] as $entry){
                if(is_array($entry)){ //

                    array_push( $or_limbs, new OrLimb($this, substr($text, $offset, $entry[1]-$offset)) );
                    $offset = $entry[1]+strlen($entry[0]);
                }
            }
        }
        array_push( $or_limbs, new OrLimb($this, substr($text, $offset)) );

        array_push($this->top_limbs, $or_limbs);
    }

    //
    private function addSortPhrase($text) {
        array_unshift($this->sort_phrases, new SortPhrase($this, $text));
    }

    //
    private function addVisibilityTypeRestriction($visibility_type) {
        if ($visibility_type){
            $visibility_type = strtolower($visibility_type);
            if ($visibility_type[0] == '-') {
                //not implemented $negate = true;
                $visibility_type = substr($visibility_type, 1);
            }
            if(in_array($visibility_type,array('viewable','hidden','pending','public')))
            {
                $this->recVisibilityType = $visibility_type;
            }
        }
    }

    public function makeSQL() {

        //WHERE
        $where_clause = '';
        $and_clauses = array();
        if(is_array($this->top_limbs)){
            for ($i=0; $i < count($this->top_limbs);++$i) {


            $or_clauses = array();
            $or_limbs = $this->top_limbs[$i];
            for ($j=0; $j < count($or_limbs);++$j) {
                $new_sql = $or_limbs[$j]->makeSQL();
                array_push($or_clauses, '(' . $new_sql . ')');
            }
            sort($or_clauses);// alphabetise
            $where_clause = join(' or ', $or_clauses);
            if(count($or_clauses)>1) {$where_clause = '(' . $where_clause . ')';}
            array_push($and_clauses, $where_clause);
        }
        }
        sort($and_clauses);
        $this->where_clause = join(SQL_AND, $and_clauses);

        //SORT
        $this->sort_clause = $this->makeSortClause();

        //FROM
        if ($this->search_domain == BOOKMARK) {
            $this->from_clause = 'FROM usrBookmarks TOPBKMK LEFT JOIN Records TOPBIBLIO ON bkm_recID=rec_ID ';
        }else{
            $this->from_clause = 'FROM Records TOPBIBLIO LEFT JOIN usrBookmarks TOPBKMK ON bkm_recID=rec_ID and bkm_UGrpID='.$this->currUserID.' ';
        }

        $this->from_clause .= join(' ', $this->sort_tables);// sorting may require the introduction of more tables

        //MAKE
        return $this->from_clause . SQL_WHERE . $this->where_clause . $this->sort_clause;
    }

    private function makeSortClause() {

        $sort_clause = '';
        $sort_clauses = array();
        for ($i=0; $i < count($this->sort_phrases);++$i) {
            @list($new_sql, $new_sig, $new_tables) = $this->sort_phrases[$i]->makeSQL();

            if($new_sql!=null && ! @$sort_clauses[$new_sig]) {    // don't repeat identical sort clauses
                    if ($sort_clause) {$sort_clause .= ', ';}

                    $sort_clause .= $new_sql;
                    if ($new_tables) {array_push($this->sort_tables, $new_tables);}

                    $sort_clauses[$new_sig] = 1;
            }
        }
        if ($sort_clause) {$sort_clause = ' ORDER BY ' . $sort_clause;}
        return $sort_clause;
    }

}


class OrLimb {
    public $and_limbs;

    public $parent;


    public function __construct(&$parent, $text) {
        $this->parent = &$parent;
        $this->absoluteStrQuery = $parent->absoluteStrQuery;
        $this->and_limbs = array();
        if (substr_count($text, '"') % 2 != 0) {$text .= '"';}// unmatched quote

        //ORIGINAL if (preg_match_all('/(?:[^" ]+|"[^"]*")+(?= |$)/', $text, $matches)) {

        //"geo:\"POLYGON((37.5
        // split by spaces - exclude text inside quotes and parentheses
        if (preg_match_all('/(?:[^"( ]+|["(][^")]*[")])+(?= |$)/', $text, $matches)) {

            $and_texts = $matches[0];

            for ($i=0; $i < count($and_texts);++$i){
                $str = $and_texts[$i];
                if ($str!=null && $str!='') {
                    $str = str_replace('+', " ", $str);//workaround until understand how to regex F:("AA BB CC")
                    $this->addAndLimb($str);
                }
            }
        }
    }

    private function addAndLimb($text) {
        $this->and_limbs[] = new AndLimb($this, $text);
    }

    public function makeSQL() {

        $and_clauses = array();
        for ($i=0; $i < count($this->and_limbs);++$i) {
            $new_sql = $this->and_limbs[$i]->pred->makeSQL();
            if (strlen($new_sql) > 0) {
                array_push($and_clauses, $new_sql);
            }
        }
        sort($and_clauses);
        return join(SQL_AND, $and_clauses);
    }
}


class AndLimb {
    public $negate;
    public $exact;
    public $lessthan;
    public $greaterthan;
    public $fulltext;
    public $pred;

    public $parent;


    public function __construct(&$parent, $text) {
        $this->parent = &$parent;
        $this->absoluteStrQuery = false;
        if (preg_match('/^".*"$/',$text,$matches)) {
            $this->absoluteStrQuery = true;
        }

        $this->exact = false;
        if ($text[0] == '-') {
            $this->negate = true;
            $text = substr($text, 1);
        } else {
            $this->negate = false;
        }

        //create predicate
        $this->pred = $this->createPredicate($text);//was by reference

    }


    private function createPredicate($text) {
        global $sortType;

        $colon_pos = strpos($text, ':');
        if ($equals_pos = strpos($text, '=')) {
            if (! $colon_pos  ||  $equals_pos < $colon_pos) {
                // an exact match has been requested
                $colon_pos = $equals_pos;
                $this->exact = true;
            }
        }
        if ($lessthan_pos = strpos($text, '<')) {
            if (! $colon_pos  ||  $lessthan_pos < $colon_pos) {
                // a less-than match has been requested
                $colon_pos = $lessthan_pos;
                $this->lessthan = true;
            }
        }
        if ($greaterthan_pos = strpos($text, '>')) {
            if (! $colon_pos  ||  $greaterthan_pos < $colon_pos) {
                // a greater-than match has been requested
                $colon_pos = $greaterthan_pos;
                $this->greaterthan = true;
            }
        }

        if ($this->absoluteStrQuery || ! $colon_pos) {    // a colon was either NOT FOUND or AT THE BEGINNING OF THE STRING
            $pred_val = $this->cleanQuotedValue($text);
            /* 2024-08-02
            if ($sortType == 'key'){
                return new TagPredicate($this, $pred_val);
            }elseif($sortType == 'all'){
                return new AnyPredicate($this, $pred_val);
            }else{    // title search is default search
                return new TitlePredicate($this, $pred_val);
            }
            */
            return new TitlePredicate($this, $pred_val);
        }

        $pred_type = substr($text, 0, $colon_pos);

        if ($pred_type[0] == '-') {    // bit of DWIM here: did the user accidentally put the negate here instead?
            $this->negate = true;
            $pred_type = substr($pred_type, 1);
        }

        $raw_pred_val = substr($text, $colon_pos+1);
        $pred_val = $this->cleanQuotedValue($raw_pred_val);
        if ($pred_val === '""') {    // special case SC100:  xxx:"" becomes equivalent to xxx="" (to find blank values, not just values that contain any string)
            $this->exact = true;
        }

        switch (strtolower($pred_type)) {
            case 'type':
            case 't':
                return new TypePredicate($this, $pred_val);

            case 'url':
            case 'u':
                return new URLPredicate($this, $pred_val);

            case 'notes':
            case 'n':
                return new NotesPredicate($this, $pred_val);

            case 'user':
            case 'usr':
                return new UserPredicate($this, $pred_val);

            case 'addedby':
                /* JT6728, fuck knows what this is going to be used for ... maybe it is for EBKUZS az FAXYUQ */
                return new AddedByPredicate($this, $pred_val);

            case 'title':
                return new TitlePredicate($this, $pred_val);

            case 'keyword':
            case 'kwd':
            case 'tag':
                return new TagPredicate($this, $pred_val);

            case 'any':
            case 'all':
                $value = $this->cleanQuotedValue($pred_val);
                return new AnyPredicate($this, $value);

            case 'id':
            case 'ids':
                return new BibIDPredicate($this, $pred_val);

            case 'fc':  //field counter

                $colon_pos = strpos($raw_pred_val, ':');
                if (! $colon_pos) {
                    if ($colon_pos = strpos($raw_pred_val, '=')) {$this->exact = true;}
                    elseif ($colon_pos = strpos($raw_pred_val, '<')) {$this->lessthan = true;}
                        elseif ($colon_pos = strpos($raw_pred_val, '>')) {$this->greaterthan = true;}
                }

                $fieldtype_id = null;

                if ($colon_pos === false){
                    $value = $this->cleanQuotedValue($raw_pred_val);
                } elseif($colon_pos == 0){
                    $value = $this->cleanQuotedValue($raw_pred_val);
                    $value =  substr($value, 1);
                }else{
                    $fieldtype_id = $this->cleanQuotedValue(substr($raw_pred_val, 0, $colon_pos));
                    $value = $this->cleanQuotedValue(substr($raw_pred_val, $colon_pos+1));

                    if (($colon_pos = strpos($value, '='))===0) {$this->exact = true;}
                    elseif (($colon_pos = strpos($value, '<'))===0) {$this->lessthan = true;}
                        elseif (($colon_pos = strpos($value, '>'))===0) {$this->greaterthan = true;}
                            if($colon_pos===0){
                        $value = substr($value,1);
                    }
                }

                return new FieldCountPredicate($this, $fieldtype_id, $value);

            case 'field':
            case 'f':

                $colon_pos = strpos($raw_pred_val, ':');
                if (! $colon_pos) {
                    if ($colon_pos = strpos($raw_pred_val, '=')) {$this->exact = true;}
                    elseif ($colon_pos = strpos($raw_pred_val, '<')) {$this->lessthan = true;}
                        elseif ($colon_pos = strpos($raw_pred_val, '>')) {$this->greaterthan = true;}
                            //elseif (($colon_pos = strpos($raw_pred_val, '@'))) {$this->fulltext = true;}
                }
                if ($colon_pos === false){
                    $value = $this->cleanQuotedValue($raw_pred_val);

                    if (($colon_pos = strpos($value, '@'))===0) {$this->fulltext = true;}
                    if($colon_pos===0){
                        $value = substr($value,1);
                    }

                    return new AnyPredicate($this, $value);
                } elseif($colon_pos == 0){
                    $value = $this->cleanQuotedValue($raw_pred_val);
                    return new AnyPredicate($this, substr($value, 1));
                }else{
                    //field id is defined

                    $fieldtype_id = $this->cleanQuotedValue(substr($raw_pred_val, 0, $colon_pos));
                    $value = $this->cleanQuotedValue(substr($raw_pred_val, $colon_pos+1));

                    if (($colon_pos = strpos($value, '='))===0) {$this->exact = true;}
                    elseif (($colon_pos = strpos($value, '<'))===0) {$this->lessthan = true;}
                        elseif (($colon_pos = strpos($value, '>'))===0) {$this->greaterthan = true;}
                            elseif (($colon_pos = strpos($value, '@'))===0) {$this->fulltext = true;}
                    if($colon_pos===0){
                        $value = substr($value,1);
                    }

                    return new FieldPredicate($this, $fieldtype_id, $value);
                }

            case 'linkedfrom':
            case 'linkto':
                return new LinkedFromParentPredicate($this, $pred_val);
            case 'linked_to':
            case 'linkedto':
                return new LinkedToParentPredicate($this, $pred_val);
            case 'relatedfrom': //related from given record type + relation type
                return new RelatedFromParentPredicate($this, $pred_val);
            case 'related_to':  //related to given record type + relation type
                return new RelatedToParentPredicate($this, $pred_val);
            case 'related':
                return new RelatedPredicate($this, $pred_val);
            case 'links':
                return new AllLinksPredicate($this, $pred_val);
/* 2016-02-29
            case 'linkto':    // linkto:XXX matches records that have a recDetails reference to XXX
                return new LinkToPredicate($this, $pred_val);
            case 'linkedto':    // linkedto:XXX matches records that are referenced in one of XXX's bib_details
                return new LinkedToPredicate($this, $pred_val);
*/
            case 'relatedto':    // relatedto:XXX matches records that are related (via a type-1 record) to XXX
                return new RelatedToPredicate($this, $pred_val);
            case 'relationsfor':    // relatedto:XXX matches records that are related (via a type-1 record) to XXX, and the relationships themselves
                return new RelationsForPredicate($this, $pred_val);

            case 'after':
            case 'since':
                return new AfterPredicate($this, $pred_val);

            case 'before':
                return new BeforePredicate($this, $pred_val);

            case 'date':
            case 'modified':
                return new DateModifiedPredicate($this, $pred_val);

            case 'added':
                return new DateAddedPredicate($this, $pred_val);

            case 'workgroup':
            case 'wg':
            case 'owner':
                return new WorkgroupPredicate($this, $pred_val);

            case 'geo':
                return new SpatialPredicate($this, $pred_val);

            case 'latitude':
            case 'lat':
                return new CoordinatePredicate($this, $pred_val, 'ST_Y');

            case 'longitude':
            case 'long':
            case 'lng':
                return new CoordinatePredicate($this, $pred_val, 'ST_X');

            case 'hhash':
                return new HHashPredicate($this, $pred_val);
            default:
                return new TitlePredicate($this, $pred_val);
        }

        // 2024-08-02
        /*
        // no predicate-type specified ... look at search type specification
        if ($sortType == 'key') {    // "default" search should be on tag
            return new TagPredicate($this, $pred_val);
        } elseif($sortType == 'all') {
            return new AnyPredicate($this, $pred_val);
        } else {
            return new TitlePredicate($this, $pred_val);
        }
        */

    }


    private function cleanQuotedValue($val) {
        if (strlen($val)>0 && $val[0] == '"') {
            if ($val[strlen($val)-1] == '"'){
                $val = substr($val, 1, -1);
            }else{
                $val = substr($val, 1);
            }
            return preg_replace('/ +/', ' ', trim($val));
        }

        return $val;
    }
}

//
//
//
class SortPhrase {
    public $value;

    public $parent;

    public function __construct(&$parent, $value) {
        $this->parent = &$parent;

        $this->value = $value;
    }
    // return list of  sql Phrase, signature, from clause for sort
    public function makeSQL() {
        global $mysqli;

        $colon_pos = strpos($this->value, ':');
        $text = substr($this->value, $colon_pos+1);

        $colon_pos = strpos($text, ':');
        if ($colon_pos === false) {$subtext = $text;}
        else {$subtext = substr($text, 0, $colon_pos);}

        // if sortby: is followed by a -, we sort DESCENDING; if it's a + or nothing, it's ASCENDING
        $scending = '';
        if ($subtext[0] == '-') {
            $scending = ' desc ';
            $subtext = substr($subtext, 1);
            $text = substr($text, 1);
        } elseif($subtext[0] == '+') {
            $subtext = substr($subtext, 1);
            $text = substr($text, 1);
        }

        switch (strtolower($subtext)) {
            case 'set': case 'fixed': //sort as defined in ids predicate
                if($this->parent->fixed_sortorder){
                    return array("FIND_IN_SET(TOPBIBLIO.rec_ID, '{$this->parent->fixed_sortorder}')", 'rec_ID', null);
                }else{
                    return array(null, null, null);
                }

            case 'p': case 'popularity':
                return array('-rec_Popularity'.$scending.', -rec_ID'.$scending, 'rec_Popularity', null);

            case 'r': case 'rating':
                if ($this->parent->search_domain == BOOKMARK) {
                    return array('-(bkm_Rating)'.$scending, 'bkmk_rating', null);//SAW Ratings Change todo: test queries with rating
                } else {    // default to popularity sort
                    return array('-rec_Popularity'.$scending.', -rec_ID'.$scending, 'rec_Popularity', null);
                }

            case 'interest':    //todo: change help file to reflect depricated predicates
            case 'content':
            case 'quality':
                return array('rec_Title'.$scending, null);// default to title sort
                break;

            case 'u': case 'url':
                return array('rec_URL'.$scending, 'rec_URL', null);

            case 'm': case 'modified':
                if ($this->parent->search_domain == BOOKMARK) {return array('bkm_Modified'.$scending, null);}
                else {return array('rec_Modified'.$scending, 'rec_Modified', null);}

            case 'a': case 'added':
                if ($this->parent->search_domain == BOOKMARK) {return array('bkm_Added'.$scending, null);}
                else {return array('rec_Added'.$scending, 'rec_Added', null);}

            case 'f': case 'field':
                /* Sort by field is complicated.
                * Unless the "multiple" flag is set, then if there are multiple values for a particular field for a particular record,
                * then we can only sort by one of them.  We choose a representative value: this is the lex-lowest of all the values,
                * UNLESS it is field 158 (creator), in which case the order of the authors is important, and we choose the one with the lowest dtl_ID
                */
                $CREATOR = (defined('DT_CREATOR')?DT_CREATOR:'0');

                if (preg_match('/^(?:f|field):(\\d+)(:m)?/i', $text, $matches)) {
                    @list($_, $field_id, $show_multiples) = $matches;
                    $res = $mysqli->query("select dty_Type from defDetailTypes where dty_ID = $field_id");
                    $baseType = $res->fetch_row();
                    $baseType = @$baseType[0];

                    if ($show_multiples) {    // "multiple" flag has been provided -- provide (potentially) multiple matches for each entry by left-joining recDetails
                        $bd_name = 'bd' . (count($this->parent->sort_phrases) + 1);
                        return array("$bd_name.dtl_Value".$scending, "$bd_name.dtl_Value".$scending,
                            "left join recDetails $bd_name on $bd_name.dtl_RecID=rec_ID and dtl_DetailTypeID=$field_id ");
                    } elseif($baseType == "integer"){//sort field is an integer so need to cast in order to get numeric sorting
                        return array(" cast(dtl_Value as unsigned)".$scending,"dtl_Value is integer",
                            "left join recDetails dtlInt on dtlInt.dtl_RecID=rec_ID and dtlInt.dtl_DetailTypeID=$field_id ");
                    } elseif($baseType == "float"){//sort field is an numeric so need to cast in order to get numeric sorting
                        return array(" cast(dtl_Value as decimal)".$scending,"dtl_Value is decimal",
                            "left join recDetails dtlInt on dtlInt.dtl_RecID=rec_ID and dtlInt.dtl_DetailTypeID=$field_id ");
                    } else {
                        // have to introduce a defDetailTypes join to ensure that we only use the linked resource's
                        // title if this is in fact a resource (record pointer)) type (previously any integer, e.g. a date, could potentially
                        // index another records record)
                        return array(" ifnull((select if(dty_Type='resource', link.rec_Title, ".
                            "if(dty_Type='date',getEstDate(dtl_Value,0),dtl_Value)) ".
                            "from recDetails left join defDetailTypes on dty_ID=dtl_DetailTypeID left join Records link on dtl_Value=link.rec_ID ".
                            "where dtl_RecID=TOPBIBLIO.rec_ID and dtl_DetailTypeID=$field_id ".
                            "order by if($field_id=$CREATOR, dtl_ID, link.rec_Title) limit 1), '~~') ".$scending,
                            "dtl_DetailTypeID=$field_id", null);
                    }
                } elseif (preg_match('/^(?:f|field):"?([^":]+)"?(:m)?/i', $text, $matches)) {
                    @list($_, $field_name, $show_multiples) = $matches;
                    $res = $mysqli->query("select dty_ID, dty_Type from defDetailTypes where dty_Name = '$field_name'");
                    $baseType = $res->fetch_row();
                    $field_id = @$baseType[0];
                    $baseType = @$baseType[1];

                    if ($show_multiples) {    // "multiple" flag has been provided -- provide (potentially) multiple matches for each entry by left-joining recDetails
                        $bd_name = 'bd' . (count($this->parent->sort_phrases) + 1);
                        return array("$bd_name.dtl_Value".$scending, "$bd_name.dtl_Value".$scending,
                            "left join defDetailTypes bdt$bd_name on bdt$bd_name.dty_Name='".$mysqli->real_escape_string($field_name)."' "
                            ."left join recDetails $bd_name on $bd_name.dtl_RecID=rec_ID and $bd_name.dtl_DetailTypeID=bdt$bd_name.dty_ID ");
                    } elseif($baseType == "integer"){//sort field is an integer so need to cast in order to get numeric sorting
                        return array(" cast(dtl_Value as decimal)".$scending,"dtl_Value is decimal",
                            "left join defDetailTypes bdtInt on bdtInt.dty_Name='".$mysqli->real_escape_string($field_name)."' "
                            ."left join recDetails dtlInt on dtlInt.dtl_RecID=rec_ID and dtlInt.dtl_DetailTypeID=bdtInt.dty_ID ");
                    } elseif($baseType == "float"){//sort field is an numeric so need to cast in order to get numeric sorting
                        return array(" cast(dtl_Value as unsigned)".$scending,"dtl_Value is integer",
                            "left join defDetailTypes bdtInt on bdtInt.dty_Name='".$mysqli->real_escape_string($field_name)."' "
                            ."left join recDetails dtlInt on dtlInt.dtl_RecID=rec_ID and dtlInt.dtl_DetailTypeID=bdtInt.dty_ID ");
                    } else {
                        return array(" ifnull((select if(dty_Type='resource', link.rec_Title, ".
                            "if(dty_Type='date',getEstDate(dtl_Value,0),dtl_Value)) ".
                            "from defDetailTypes, recDetails left join Records link on dtl_Value=link.rec_ID ".
                            "where dty_Name='".$mysqli->real_escape_string($field_name)."' and dtl_RecID=TOPBIBLIO.rec_ID and dtl_DetailTypeID=dty_ID ".
                            "order by if(dty_ID=$CREATOR,dtl_ID,link.rec_Title) limit 1), '~~') ".$scending,
                            "dtl_DetailTypeID=$field_id", null);
                    }
                }

            case 't': case 'title':
                return array('rec_Title'.$scending, null);
            case 'id': case 'ids':
                return array('rec_ID'.$scending, null);
            case 'rt': case 'type':
                return array('rec_RecTypeID'.$scending, null);
            default;
        }
    }
}


class Predicate {
    public $value;

    public $parent;

    public $need_recursion = true;

    public $query;

    public function __construct(&$parent, $value) {
        $this->parent = &$parent;

        $this->value = $value;
        $this->query = null;
    }

    //$table_name=null
    public function makeSQL() { return '1';}


    public function stopRecursion() {
       $this->need_recursion = false;
    }

    //get the top most parent - the Query
    public function &getQuery() {
        if (! $this->query) {
            $c = &$this->parent;

            //loop up to top-most parent "Query"
            while ($c  &&  strtolower(get_class($c)) != 'query') {
                $c = &$c->parent;
            }


            $this->query = &$c;
        }
        return $this->query;
    }

    public function isDateTime() {

        $timestamp0 = null;
        $timestamp1 = null;
        if (strpos($this->value,"<>")>0) {
            $vals = explode("<>", $this->value);

             try{
                $timestamp0 = new DateTime($vals[0]);
                $timestamp1 = new DateTime($vals[1]);
             } catch (Exception  $e){
             }
        }else{
             try{
                $timestamp0 = new DateTime($this->value);
                $timestamp1 = 1;
             } catch (Exception  $e){
             }
        }
        return $timestamp0  &&  $timestamp1;
    }

    public function makeDateClause_old() {

        if (strpos($this->value,"<>")) {

            $vals = explode("<>", $this->value);
            $datestamp0 = Temporal::dateToISO($vals[0]);
            $datestamp1 = Temporal::dateToISO($vals[1]);

            return "between '$datestamp0'".SQL_AND."'$datestamp1'";

        }else{

            $datestamp = Temporal::dateToISO($this->value);

            if ($this->parent->exact) {
                return "= '$datestamp'";
            }
            elseif($this->parent->lessthan) {
                return "< '$datestamp'";
            }
            elseif($this->parent->greaterthan) {
                return "> '$datestamp'";
            }
            else {
                return "like '$datestamp%'";

                //old way
                /*
                // it's a ":" ("like") query - try to figure out if the user means a whole year or month or default to a day
                $match = preg_match('/^[0-9]{4}$/', $this->value, $matches);
                if (@$matches[0]) {
                    $date = $matches[0];
                }
                elseif (preg_match('!^\d{4}[-/]\d{2}$!', $this->value)) {
                    $date = date('Y-m', $timestamp);
                }
                else {
                    $date = date('Y-m-d', $timestamp);
                }
                return "like '$date%'";
                */
            }
        }
    }

    public function makeDateClause() {

        //???? if($this->isEmptyValue()){ // {"f:10":"NULL"}

        if (strpos($this->value,"<>")) {

            $vals = explode("<>", $this->value);

            $temporal1 = new Temporal($vals[0]);
            $temporal2 = new Temporal($vals[1]);

            if(!$temporal1->isValid() || !$temporal2->isValid()){
                return null;
            }

            $timespan = $temporal1->getMinMax();
            $min = $timespan[0];

            $timespan = $temporal2->getMinMax();
            $max = $timespan[1];

            $timespan = array($min, $max);

        }else{
            $temporal = new Temporal($this->value);
            if(!$temporal->isValid()){
                return null;
            }

            $timespan = $temporal->getMinMax();
        }

        $res = '';

        if ($this->parent->exact) {
            //timespan within interval
            $res = "(rdi_estMinDate <= {$timespan[0]} AND {$timespan[1]} <= rdi_estMaxDate)";
        }
        elseif($this->parent->lessthan) {

            //timespan max < rdi_estMinDate
            $res = "({$timespan[1]} < rdi_estMinDate)";
        }
        elseif($this->parent->greaterthan) {

            //timespan min > rdi_estMaxDate
            $res = "(rdi_estMaxDate < {$timespan[0]})";
        }
        else {
            //overlaps/intersects with interval
            // @End >= tbl.start AND @Start <= tbl.end
            $res = "(rdi_estMaxDate>={$timespan[0]} AND rdi_estMinDate<={$timespan[1]})";
        }

        return $res;
    }


    /**
     * Retrieves the inverse term ID for a given relationship type.
     *
     * @param int $relation_type_ID The relationship type ID.
     * @return int|null The inverse term ID, or null if not found.
     */
    protected function getInverseTermId($relation_type_ID) {
        global $mysqli;
        $res = $mysqli->query("SELECT trm_InverseTermID FROM defTerms WHERE trm_ID = " . intval($relation_type_ID));
        if ($res) {
            $inverseTermId = $res->fetch_row();
            return $inverseTermId[0] ?? null;
        }
        return null;
    }
}


class TitlePredicate extends Predicate {

    public function makeSQL($isTopRec=true) {
        global $mysqli;

        $not = ($this->parent->negate)? SQL_NOT : '';

        $query = &$this->getQuery();//not used
        $evalue = $mysqli->real_escape_string($this->value);

        if($isTopRec){
            $topbiblio = "TOPBIBLIO.";
        }else{
            $topbiblio = "";
        }

        if ($this->parent->exact){
            return $not . $topbiblio.'rec_Title = "'.$evalue.'"';
        }elseif($this->parent->lessthan){
            return $not . $topbiblio.'rec_Title < "'.$evalue.'"';
        }elseif($this->parent->greaterthan){
                return $not . $topbiblio.'rec_Title > "'.$evalue.'"';
        }elseif(strpos($this->value,"%")===false){
                return $topbiblio."rec_Title $not like '%$evalue%'";
        }else{
                return $topbiblio."rec_Title $not like '$evalue'";
        }

    }
}

class TypePredicate extends Predicate {

    public function makeSQL($isTopRec=true) {
        global $mysqli;

        $eq = ($this->parent->negate)? '!=' : '=';
        if (is_numeric($this->value)) {
            $res = "rec_RecTypeID $eq ".intval($this->value);
        }
        elseif (preg_match(REGEX_CSV, $this->value)) {
            // comma-separated list of defRecTypes ids
            $in = ($this->parent->negate)? 'not in' : 'in';
            $res = "rec_RecTypeID $in (" . $this->value . ")";
        }
        else {
            $name = $mysqli->real_escape_string($this->value);
            $res = "rec_RecTypeID $eq (select rft.rty_ID from defRecTypes rft where rft.rty_Name = '$name' limit 1)";
        }

        if($isTopRec){
            $res = "TOPBIBLIO.".$res;
        }
        return $res;
    }
}


class URLPredicate extends Predicate {

    public function makeSQL() {
        global $mysqli;

        $not = ($this->parent->negate)? SQL_NOT : '';

        $query = &$this->getQuery();
        $val = $mysqli->real_escape_string($this->value);
        return "TOPBIBLIO.rec_URL $not like '%$val%'";
    }
}


class NotesPredicate extends Predicate {

    public function makeSQL() {
        global $mysqli;

        $not = ($this->parent->negate)? SQL_NOT : '';

        $query = &$this->getQuery();
        if ($query->search_domain == BOOKMARK){    // saw TODO change this to check for woot match or full text search
            return '';
        }else{
            $val = $mysqli->real_escape_string($this->value);
            return "TOPBIBLIO.rec_ScratchPad $not like '%$val%'";
        }
    }
}


class UserPredicate extends Predicate {

    public function makeSQL() {
        global $mysqli;

        $not = ($this->parent->negate)? SQL_NOT : '';
        if (is_numeric($this->value)) {
            return '('.$not . 'exists (select * from usrBookmarks bkmk where bkmk.bkm_recID=TOPBIBLIO.rec_ID '
            . ' and bkmk.bkm_UGrpID = ' . intval($this->value) . '))';
        }
        elseif (preg_match(REGEX_CSV, $this->value)) {
            return '('.$not . 'exists (select * from usrBookmarks bkmk where bkmk.bkm_recID=TOPBIBLIO.rec_ID '
            . ' and bkmk.bkm_UGrpID in (' . $this->value . ')))';
        }
        elseif (preg_match('/^(\D+)\s+(\D+)$/', $this->value,$matches)){    // saw MODIFIED: 16/11/2010 since Realname field was removed.
            return '('.$not . 'exists (select * from usrBookmarks bkmk, sysUGrps usr '
            . ' where bkmk.bkm_recID=TOPBIBLIO.rec_ID and bkmk.bkm_UGrpID = usr.ugr_ID '
            . ' and (usr.ugr_FirstName = "' . $mysqli->real_escape_string($matches[1])
            . '" and usr.ugr_LastName = "' . $mysqli->real_escape_string($matches[2]) . '")))';
        }
        else {
            return '('.$not . 'exists (select * from usrBookmarks bkmk, sysUGrps usr '
            . ' where bkmk.bkm_recID=TOPBIBLIO.rec_ID and bkmk.bkm_UGrpID = usr.ugr_ID '
            . ' and usr.ugr_Name = "' . $mysqli->real_escape_string($this->value) . '"))';
        }
    }
}


class AddedByPredicate extends Predicate {

    public function makeSQL() {
        global $mysqli;

        $eq = ($this->parent->negate)? '!=' : '=';
        if (is_numeric($this->value)) {
            return "TOPBIBLIO.rec_AddedByUGrpID $eq " . intval($this->value);
        }
        elseif (preg_match(REGEX_CSV, $this->value)) {
            $not = ($this->parent->negate)? "not" : "";
            return "TOPBIBLIO.rec_AddedByUGrpID $not in (" . $this->value . ")";
        }
        else {
            $not = ($this->parent->negate)? "not" : "";
            return "TOPBIBLIO.rec_AddedByUGrpID $not in (select usr.ugr_ID from sysUGrps usr where usr.ugr_Name = '"
            . $mysqli->real_escape_string($this->value) . "')";
        }
    }
}

class AnyPredicate extends Predicate {

    public function makeSQL() {
        global $mysqli;

        $val = $mysqli->real_escape_string($this->value);

        if($this->parent->fulltext){

               $res = 'select dtl_RecID from recDetails '
                . ' left join defDetailTypes on dtl_DetailTypeID=dty_ID '
                . ' left join Records link on dtl_Value=link.rec_ID '
                .' where if(dty_Type="resource", '
                    .'link.rec_Title like "%'.$val.'%", '
                    .' MATCH(dtl_Value) AGAINST("'.$val.'"))';

                $list_ids = mysql__select_list2($mysqli, $res);

                if($list_ids && !empty($list_ids)){
                    $res = predicateId('TOPBIBLIO.rec_ID',$list_ids).' OR ';
                }else{
                    $res = '';//nothing found
                }

                return '('.$res.' (TOPBIBLIO.rec_Title like "%'.$val.'%"))';

        }


        $not = ($this->parent->negate)? SQL_NOT : '';
        return $not . ' (exists (select rd.dtl_ID from recDetails rd '
        . 'left join defDetailTypes on rd.dtl_DetailTypeID=dty_ID '
        . 'left join Records link on rd.dtl_Value=link.rec_ID '
        . 'where rd.dtl_RecID=TOPBIBLIO.rec_ID '
        . '  and if(dty_Type != "resource", '
        . 'if(dty_Type="enum", dtl_Value in (select trm_ID from defTerms where trm_Label like "%'.$val.'%" or trm_Code="'.$val.'"), rd.dtl_Value like "%'.$val.'%"), '
        .'link.rec_Title like "%'.$val.'%"))'
        .' or TOPBIBLIO.rec_Title like "%'.$val.'%") ';
    }
}


class FieldPredicate extends Predicate {
    public $field_type;        //name of dt_id
    public $field_type_value;  //field type
    public $nests = null;

    public function __construct(&$parent, $type, $value) {
        $this->field_type = $type;
        parent::__construct($parent, $value);

        if (strlen($value)>0 && $value[0] == '-') {    // DWIM: user wants a negate, we'll let them put it here
            $parent->negate = true;
            $value = substr($value, 1);
        }

        //nests are inside parentheses
        preg_match('/\((.+?)(?:\((.+)\))?\)/', $this->value, $matches);
        if(!empty($matches) && $matches[0]==$this->value){

            $this->nests = array();
            for ($k=1; $k < count($matches);++$k) {

                $text = $matches[$k];


                if (preg_match_all('/(?:[^" ]+|"[^"]*")+(?= |$)/', $text, $matches2)) {
                    $and_texts = $matches2[0];
                    $limbs = array();
                    for ($i=0; $i < count($and_texts);++$i){
                        $limbs[] = new AndLimb($this, $and_texts[$i]);
                    }
                    array_push($this->nests, $limbs);
                }
            }
        }
        /*
        for ($i=0; $i < count($limbs);++$i) {
        $new_sql = $limbs[$i]->pred->makeSQL();
        if (strlen($new_sql) > 0) {
        array_push($and_clauses, $new_sql);
        }
        }
        */
    }

    public function makeSQL() {
        global $mysqli;

        $not = ($this->parent->negate)? SQL_NOT : '';

        $and_link = ' and link';
        $sql_detail_exists = 'exists (select rd.dtl_ID from recDetails rd ';
        $sql_recdetail_link = ' where rd.dtl_RecID=TOPBIBLIO.rec_ID ';
        $sql_and_detailtype = ' and rd.dtl_DetailTypeID=';

        if($this->nests){  //special case nested query for resources

            $field_value = '';
            $nest_joins = '';
            $relation_second_level = '';
            $relation_second_level_where = '';
            $use_new_version = true;

            if( $use_new_version ){ //new test version

                $isrelmarker_0 = ($this->field_type=="relmarker");
                $isrelmarker_1 = false;

                for ($i=0; $i < count($this->nests);++$i) {

                    $limbs = $this->nests[$i];
                    $type_clause = null;
                    $field_type = null;

                    for ($j=0; $j < count($limbs);++$j) {
                        $cn = get_class($limbs[$j]->pred);

                        if($cn == 'TypePredicate'){
                            $type_clause = $limbs[$j]->pred->makeSQL(false);// rec_RecTypeID in (12,14)
                        }elseif($cn == 'FieldPredicate'){
                            if($i==0 && $limbs[$j]->pred->field_type=="relmarker"){ //allowed for i==0 only
                                $isrelmarker_1 = true;

                                $relation_second_level = ', recLinks rel1';
                                if($isrelmarker_0){
                                    $relation_second_level_where = ' and (rel1.rl_RelationID is not null)'
                                    .' and ((rel1.rl_TargetID=rel0.rl_SourceID and rel1.rl_SourceID=link1.rec_ID) '
                                    .'or (rel1.rl_SourceID=rel0.rl_TargetID and rel1.rl_TargetID=link1.rec_ID))';
                                }else{
                                    $relation_second_level_where =
                                    ' and (rel1.rl_RelationID is not null)'
                                    .' and ((rel1.rl_TargetID=rel0.rl_SourceID and rel1.rl_SourceID=rd.dtl_Value) '
                                    .'or (rel1.rl_SourceID=rel0.rl_TargetID and rel1.rl_TargetID=rd.dtl_Value))';
                                }

                            }else{

                                $field_type = $limbs[$j]->pred->get_field_type_clause();
                                if(strpos($field_type,"like")!==false){
                                    $field_type = " in (select rdt.dty_ID from defDetailTypes rdt where rdt.dty_Name $field_type limit 1)";
                                }
                                if($limbs[$j]->pred->value){
                                    $field_value .= ' and linkdt'.$i.'.dtl_Value '.$limbs[$j]->pred->get_field_value();
                                }

                            }
                        }elseif($cn == 'TitlePredicate'){
                            $field_value .= $and_link.$i.'.'.$limbs[$j]->pred->makeSQL(false);
                        }elseif($cn == 'DateModifiedPredicate'){
                            $field_value .= $and_link.$i.'.'.$limbs[$j]->pred->makeSQL();
                            $field_value = str_replace("TOPBIBLIO.","",$field_value);
                        }
                    }//for predicates

                    if($type_clause){ //record type clause is mandatory

                        $nest_joins .= ' left join Records link'.$i.' on link'.$i.'.'.$type_clause;
                        if($i==0){
                            if(!$isrelmarker_0){
                                $nest_joins .= ' and rd.dtl_Value=link0.rec_ID ';
                            }
                        }elseif(!$isrelmarker_1){
                                $nest_joins .= ' and linkdt0.dtl_Value=link1.rec_ID ';

                        }

                        //$nest_joins .= SQL_AND.($i==0?'rd.dtl_Value':'linkdt'.($i-1).'.dtl_Value').'=link'.$i.'.rec_ID ';//STRCMP('.($i==0?'rd.dtl_Value':'linkdt'.($i-1).'.dtl_Value').',link'.$i.'.rec_ID)=0

                        if($field_type){
                            $nest_joins .= ' left join recDetails linkdt'.$i.' on linkdt'.$i.'.dtl_RecID=link'.$i.'.rec_ID and linkdt'.$i.'.dtl_DetailTypeID '.$field_type;
                        }

                    } else {
                        return '';//fail - record type is mandatory for nested queries
                    }
                }//for nests

                if($isrelmarker_0){

                    $resq = '('.$not . 'exists (select rel0.rl_TargetID, rel0.rl_SourceID from recLinks rel0 '.$relation_second_level
                    .$nest_joins
                    .' where (rel0.rl_RelationID is not null) and ((rel0.rl_TargetID=TOPBIBLIO.rec_ID and rel0.rl_SourceID=link0.rec_ID)'
                    .' or (rel0.rl_SourceID=TOPBIBLIO.rec_ID and rel0.rl_TargetID=link0.rec_ID)) '
                    .$relation_second_level_where
                    .$field_value.'))';

                }else{

                    $rd_type_clause = '';
                    $rd_type_clause = $this->get_field_type_clause();
                    if(strpos($rd_type_clause,"like")===false){
                        $rd_type_clause = " and rd.dtl_DetailTypeID $rd_type_clause";
                    }else{
                        $rd_type_clause = " and rd.dtl_DetailTypeID in (select rdt.dty_ID from defDetailTypes rdt where rdt.dty_Name $rd_type_clause limit 1)";
                    }

                    $resq = '('.$not . $sql_detail_exists.$relation_second_level
                    .$nest_joins
                    . $sql_recdetail_link . $relation_second_level_where . $field_value . $rd_type_clause.'))';
                }

            }else{  //working copy!!!!

                for ($i=0; $i < count($this->nests);++$i) {

                    $limbs = $this->nests[$i];
                    $type_clause = null;
                    $field_type = null;

                    for ($j=0; $j < count($limbs);++$j) {
                        $cn = get_class($limbs[$j]->pred);

                        if($cn == 'TypePredicate'){
                            $type_clause = $limbs[$j]->pred->makeSQL(false);
                        }elseif($cn == 'FieldPredicate'){
                            $field_type = $limbs[$j]->pred->get_field_type_clause();
                            if(strpos($field_type,"like")!==false){
                                $field_type = " in (select rdt.dty_ID from defDetailTypes rdt where rdt.dty_Name $field_type limit 1)";
                            }
                            if($limbs[$j]->pred->value){
                                $field_value .= ' and linkdt'.$i.'.dtl_Value '.$limbs[$j]->pred->get_field_value();
                            }
                        }elseif($cn == 'TitlePredicate'){
                            $field_value .= $and_link.$i.'.'.$limbs[$j]->pred->makeSQL(false);
                        }elseif($cn == 'DateModifiedPredicate'){
                            $field_value .= $and_link.$i.'.'.$limbs[$j]->pred->makeSQL();
                            $field_value = str_replace("TOPBIBLIO.","",$field_value);
                        }
                    }//for predicates

                    if($type_clause){ //record type clause is mandatory     STRCMP('.($i==0?'rd.dtl_Value':'linkdt'.($i-1).'.dtl_Value').',link'.$i.'.rec_ID)=0
                        $nest_joins .= ' left join Records link'.$i.' on '.($i==0?'rd.dtl_Value':'linkdt'.($i-1).'.dtl_Value').'=link'.$i.'.rec_ID and link'.$i.'.'.$type_clause;
                        if($field_type){
                            $nest_joins .= ' left join recDetails linkdt'.$i.' on linkdt'.$i.'.dtl_RecID=link'.$i.'.rec_ID and linkdt'.$i.'.dtl_DetailTypeID '.$field_type;
                        }
                    } else {
                        return '';//fail - record type is mandatory for nested queries
                    }
                }//for nests

                $rd_type_clause = '';
                $rd_type_clause = $this->get_field_type_clause();
                if(strpos($rd_type_clause,"like")===false){
                    $rd_type_clause = " and rd.dtl_DetailTypeID $rd_type_clause";
                }else{
                    $rd_type_clause = " and rd.dtl_DetailTypeID in (select rdt.dty_ID from defDetailTypes rdt where rdt.dty_Name $rd_type_clause limit 1)";
                }

                $resq = '('.$not . $sql_detail_exists
                .$nest_joins
                . $sql_recdetail_link. $field_value . $rd_type_clause.'))';

            }

            return $resq;
        } //end special case nested query for resources

        if (preg_match('/^\\d+$/', $this->field_type)) {
            $dt_query = "select rdt.dty_Type from defDetailTypes rdt where rdt.dty_ID = ".intval($this->field_type);
            $this->field_type_value = mysql__select_value($mysqli, $dt_query);
        }else{
            $this->field_type_value ='';
        }

        $match_pred = $this->get_field_value();

        $isnumericvalue = false;
        $isin = false;
        if($this->field_type_value!='date'){

            $cs_ids = getCommaSepIds($this->value);

            if ($cs_ids) {
                $isnumericvalue = false;
                $isin = true;
            }else{
                $isin = false;
                $isnumericvalue = is_numeric($this->value);
            }
        }

        /*
        if($isin){
            $match_pred_for_term = $match_pred;
        }elseif($isnumericvalue){
            $match_pred_for_term = $match_pred; //" = $match_value";
        }else{
            $match_pred_for_term = " = trm.trm_ID";
        }*/

        $timestamp = $isin?false:true; //numeric values $this->isDateTime();

        if($this->field_type_value=='resource'){ //field type is found - search for specific detailtype
            return '('.$not . $sql_detail_exists
            . ' left join Records link on rd.dtl_Value=link.rec_ID '
            . $sql_recdetail_link . $sql_and_detailtype . intval($this->field_type).SQL_AND
            . ($isnumericvalue ? 'rd.dtl_Value ':' link.rec_Title ').$match_pred . '))';

        }elseif($this->field_type_value=='enum' || $this->field_type_value=='relationtype'){

            return '('.$not . $sql_detail_exists
            //. (($isnumericvalue || $isin)?'':'left join defTerms trm on trm.trm_Label '. $match_pred )
            . $sql_recdetail_link . $sql_and_detailtype . intval($this->field_type)
            . " and rd.dtl_Value $match_pred))";

        }elseif($this->field_type_value=='date'){


            $res = '('.$not.'EXISTS (SELECT rdi_DetailID FROM recDetailsDateIndex WHERE TOPBIBLIO.rec_ID=rdi_RecID AND '
                    .'rdi_DetailTypeID='. intval($this->field_type);

            $dateindex_clause = $this->makeDateClause();
            if($dateindex_clause){
                $res = $res.SQL_AND.$dateindex_clause.'))';
            }else{
                $res = '';
            }

            return $res;

        }elseif($this->field_type_value){

            if($this->field_type_value=='file'){
                $fieldname = 'rd.dtl_UploadedFileID';

                if(!($isnumericvalue || $isin)){
                    $q = 'exists (select rd.dtl_ID from recDetails rd, recUploadedFiles rf '
                    . $sql_recdetail_link . ' and rf.ulf_ID=rd.dtl_UploadedFileID'
                    . $sql_and_detailtype . intval($this->field_type)
                    . ' and (rf.ulf_OrigFileName ' . $match_pred. ' or rf.ulf_MimeExt '. $match_pred.'))';

                    if($not){
                        $q = '('.$not . $q . ')';
                    }
                    return $q;
                }
            }elseif($this->parent->fulltext){

                $res = 'select dtl_RecID from recDetails where '
                .' dtl_DetailTypeID='.intval($this->field_type)
                .' AND MATCH(dtl_Value) AGAINST("'.$mysqli->real_escape_string($this->value).'")';

                $list_ids = mysql__select_list2($mysqli, $res);

                $res = predicateId('TOPBIBLIO.rec_ID',$list_ids);

                return $res;

            }else{
                $fieldname = 'rd.dtl_Value';
            }

            return '('.$not . $sql_detail_exists
                . $sql_recdetail_link . $sql_and_detailtype . intval($this->field_type)
                . SQL_AND . $fieldname . ' ' . $match_pred. '))';


        }else{

            $rd_type_clause = $this->get_field_type_clause();
            if(strpos($rd_type_clause,"like")===false){ //several field type
                $rd_type_clause = " and rd.dtl_DetailTypeID $rd_type_clause";
            }else{
                if($rd_type_clause=='like "%"'){ //any field type
                    $rd_type_clause = '';
                }else{
                    $rd_type_clause = " and rdt.dty_Name ".$rd_type_clause;
                }
            }

            if($this->parent->fulltext){


                $res = 'select dtl_RecID from recDetails '
                . ' left join defDetailTypes on dtl_DetailTypeID=dty_ID '
                . ' left join Records link on dtl_Value=link.rec_ID '
                .' where if(dty_Type="resource", '
                    .'link.rec_Title ' . $match_pred . ', '
                    .' MATCH(dtl_Value) AGAINST("'.$mysqli->real_escape_string($this->value).'"))';

                $list_ids = mysql__select_list2($mysqli, $res);

                $res = predicateId('TOPBIBLIO.rec_ID',$list_ids);

                return $res;
            }


            $dateindex_clause = $this->makeDateClause();

            return '('.$not . $sql_detail_exists
            . 'left join defDetailTypes rdt on rdt.dty_ID=rd.dtl_DetailTypeID '
            . 'left join Records link on rd.dtl_Value=link.rec_ID '
            .' left join recDetailsDateIndex on rd.dtl_ID=rdi_DetailID '
//. (($isnumericvalue || $isin)?'':'left join defTerms trm on trm.trm_Label '. $match_pred ). " "
            . 'where rd.dtl_RecID=TOPBIBLIO.rec_ID '
            . ' and if(rdt.dty_Type = "resource" AND '.($isnumericvalue?'0':'1').', '
            .'link.rec_Title ' . $match_pred . ', '     //THEN
//see 1377            .'if(rdt.dty_Type in ("enum","relationtype"), rd.dtl_Value '.$match_pred_for_term.', '
            . ($dateindex_clause!=null
                ? 'if(rdt.dty_Type = "date", (rdi_DetailTypeID=rd.dtl_DetailTypeID AND '.$dateindex_clause.') , '
                . "rd.dtl_Value $match_pred)"
                : "rd.dtl_Value $match_pred"
              ) . ')'
            . $rd_type_clause . '))';
        }

    }

    public function get_field_type_clause(){
        global $mysqli;

        if(trim($this->value)===''){

            $rd_type_clause = "!=''";

        }elseif (preg_match('/^\\d+$/', $this->field_type)) {
            /* handle the easy case: user has specified a (single) specific numeric type */
            $rd_type_clause = '= ' . intval($this->field_type);
        }
        elseif (preg_match(REGEX_CSV, $this->field_type)) {
            /* user has specified a list of numeric types ... match any of them */
            $rd_type_clause = 'in (' . $this->field_type . ')';
        }
        else {
            $val = $mysqli->real_escape_string($this->field_type);
            /* user has specified the field name */
            $rd_type_clause = 'like "' . $val . '%"';
        }
        return  $rd_type_clause;
    }

    //
    public function get_field_value(){
        global $mysqli;

        if(trim($this->value)==='' || $this->value===false){   //if value is not defined find any non empty value

            $match_pred = " !='' ";

        }elseif($this->field_type_value=='enum' || $this->field_type_value=='relationtype'){

            if(preg_match(REGEX_CSV, $this->value)){
                $match_pred = ' in (select trm_ID from defTerms where trm_ID in ('
                    .$this->value.') or trm_ParentTermID in ('.$this->value.'))';
            }elseif(intval($this->value)>0){
                $match_pred = ' in (select trm_ID from defTerms where trm_ID='
                    .$this->value.' or trm_ParentTermID='.$this->value.')';
            }else{
                $value = $mysqli->real_escape_string($this->value);

                $match_pred  = ' in (select trm_ID from defTerms where trm_Label ';
                if($this->parent->exact){
                    $match_pred  =  $match_pred.'="'.$value.'"';
                } else {
                    $match_pred  =  $match_pred." like '%$value%'";
                }
                $match_pred  =  $match_pred.' or trm_Code="'.$value.'")';


            }

        }elseif (strpos($this->value,"<>")>0) {  //(preg_match('/^\d+(\.\d*)?|\.\d+(?:<>\d+(\.\d*)?|\.\d+)+$/', $this->value)) {

            $vals = explode("<>", $this->value);
            $match_pred = SQL_BETWEEN.$vals[0].SQL_AND.$vals[1].' ';

        }else {

            $cs_ids =null;
            if(!($this->field_type_value=='float' || $this->field_type_value=='integer')){
                $cs_ids = getCommaSepIds($this->value);
            }

            if ($cs_ids) {
            //  if (preg_match(REGEX_CSV, $this->value)) {  not work for >500 entries
                // comma-separated list of ids
                $match_pred = ' in ('.$cs_ids.')';

            }else{

                $isnumericvalue = is_numeric($this->value);

                if($isnumericvalue && $this->value!==''){
                    $match_value = floatval($this->value);
                }else{
                    $match_value = '"'.$mysqli->real_escape_string($this->value).'"';
                }

                if ($this->parent->exact  ||  $this->value === "") {    // SC100
                    $match_pred = ' = '.$match_value; //for unknown reason comparison with numeric takes ages
                } elseif($this->parent->lessthan) {
                    $match_pred = " < $match_value";
                } elseif($this->parent->greaterthan) {
                    $match_pred = " > $match_value";
                } else {
                    if(($this->field_type_value=='float' || $this->field_type_value=='integer') && $isnumericvalue){
                        $match_pred = ' = "'.floatval($this->value).'"';
                    }elseif(strpos($this->value,"%")===false){
                        $match_pred = " like '%".$mysqli->real_escape_string($this->value)."%'";
                    }else{
                        $match_pred = " like '".$mysqli->real_escape_string($this->value)."'";
                    }
                }
            }

        }

        return $match_pred;
    }

}


class FieldCountPredicate extends Predicate {
    public $field_type;        //name of dt_id

    public function __construct(&$parent, $type, $value) {
        $this->field_type = $type;
        parent::__construct($parent, $value);

        if ($value[0] == '-') {    // DWIM: user wants a negate, we'll let them put it here
            $parent->negate = true;
            $value = substr($value, 1);
        }
    }

    public function makeSQL() {
        global $mysqli;

        $not = ($this->parent->negate)? '(not ' : '';
        $not2 = ($this->parent->negate)? ') ' : '';

        $match_pred = $this->get_field_value();

        $ft_compare = '';
        if($this->field_type>0){
            $ft_compare = 'and rd.dtl_DetailTypeID='.intval($this->field_type);
        }

        return $not . '(select count(rd.dtl_ID) from recDetails rd left join Records link on rd.dtl_Value=link.rec_ID
where rd.dtl_RecID=TOPBIBLIO.rec_ID '.$ft_compare.' )'.$match_pred . $not2;
    }

    //
    public function get_field_value(){
        global $mysqli;

        if (strpos($this->value,"<>")>0) {  //(preg_match('/^\d+(\.\d*)?|\.\d+(?:<>\d+(\.\d*)?|\.\d+)+$/', $this->value)) {

            $vals = explode("<>", $this->value);
            $match_pred = SQL_BETWEEN.$vals[0].SQL_AND.$vals[1].' ';

        }else {

                if(!is_numeric($this->value)){
                    $match_value = 0;
                }else{
                    $match_value = intval($this->value);
                }

                if ($this->parent->lessthan) {
                    $match_pred = " < $match_value";
                } elseif($this->parent->greaterthan) {
                    $match_pred = " > $match_value";
                } else {
                    $match_pred = ' = '.$match_value;
                }
        }

        return $match_pred;
    }

}


class TagPredicate extends Predicate {
    public $wg_value;

    public function __construct(&$parent, $value) {
        $this->parent = &$parent;

        $this->value = array();
        $this->wg_value = array();
        $values = explode(',', $value);
        $any_wg_values = false;

        // Heavy, heavy DWIM here: if the tag for which we're searching contains comma(s),
        // then split it into several tags, and do an OR search on those.
        for ($i=0; $i < count($values);++$i) {
            if (strpos($values[$i], '\\') === false) {
                array_push($this->value, trim($values[$i]));
                array_push($this->wg_value, '');
            } else {    // A workgroup tag.  How nice.
                preg_match('/(.*?)\\\\(.*)/', $values[$i], $matches);
                array_push($this->wg_value, trim($matches[1]));
                array_push($this->value, trim($matches[2]));
                $any_wg_values = true;
            }
        }
        if (! $any_wg_values) {$this->wg_value = array();}
        $this->query = null;
    }

    private function tagWhereExp(){
        global $mysqli;

        $query = '';

        $sql_tag_eq = 'kwd.tag_Text ="';
        $sql_tag_like = 'kwd.tag_Text like "';

        for ($i=0; $i < count($this->value);++$i) {
                if ($i > 0) {$query .= 'or ';}

                $value = $this->value[$i];
                $wg_value = $this->wg_value[$i];

                $sql_tags = ($this->parent->exact? $sql_tag_eq.$mysqli->real_escape_string($value).'" '
                        : $sql_tag_like.$mysqli->real_escape_string($value).'%" ');

                if ($wg_value) {
                    $query .= '( '. $sql_tags .' and ugr_Name = "'.$mysqli->real_escape_string($wg_value).'") ';

                } elseif (is_numeric($value)) {
                    $query .= "kwd.tag_ID=$value ";
                } else {
                    $query .= '( '.$sql_tags;

                    $pquery = &$this->getQuery();
                    if($pquery->search_domain != BOOKMARK){
                        $query .= ' and ugr_ID is null ';
                    }
                    $query .= ') ';
                }
        }

        return null;
    }

    public function makeSQL() {
        global $mysqli;

        $sql_where = 'where kwi.rtl_RecID=TOPBIBLIO.rec_ID and (';
        $sql_tag_eq = 'kwd.tag_Text ="';
        $sql_tag_like = 'kwd.tag_Text like "';

        $pquery = &$this->getQuery();
        $not = ($this->parent->negate)? SQL_NOT : '';
        if ($pquery->search_domain == BOOKMARK) {
            if (is_numeric(join('', $this->value))) {    // if all tag specs are numeric then don't need a join
                return '('.$not . 'exists (select * from usrRecTagLinks where rtl_RecID=bkm_RecID and rtl_TagID in ('.join(',', $this->value).')))';
            } elseif (! $this->wg_value) {
                // this runs faster (like TEN TIMES FASTER) - think it's to do with the join
                $query='('.$not . 'exists (select * from usrRecTagLinks kwi left join usrTags kwd on kwi.rtl_TagID=kwd.tag_ID '
                . $sql_where;
                $first_value = true;
                foreach ($this->value as $value) {
                    if (! $first_value) {$query .= 'or ';}
                    if (is_numeric($value)) {
                        $query .= 'rtl_TagID='.intval($value).' ';
                    } else {
                        $query .=  ($this->parent->exact
                            ? $sql_tag_eq.$mysqli->real_escape_string($value).'" '
                            : $sql_tag_like.$mysqli->real_escape_string($value).'%" ');
                    }
                    $first_value = false;
                }
                $query .= ') and kwd.tag_UGrpID='.$pquery->currUserID.')) ';
            } else {
                $query='('.$not . 'exists (select * from sysUGrps, usrRecTagLinks kwi left join usrTags kwd on kwi.rtl_TagID=kwd.tag_ID '
                . ' where ugr_ID=tag_UGrpID and kwi.rtl_RecID=TOPBIBLIO.rec_ID and ('
                . tagWhereExp(). ')))';
            }
        } else {
            if (! $this->wg_value) {
                $query = '('.$not . 'exists (select * from usrRecTagLinks kwi left join usrTags kwd on kwi.rtl_TagID=kwd.tag_ID '
                . $sql_where;
                $first_value = true;
                foreach ($this->value as $value) {
                    if (! $first_value) {$query .= 'or ';}
                    if (is_numeric($value)) {
                        $query .= "kwd.tag_ID=$value ";
                    } else {
                        $query .=      ($this->parent->exact? $sql_tag_eq.$mysqli->real_escape_string($value).'" '
                            : $sql_tag_like.$mysqli->real_escape_string($value).'%" ');
                    }
                    $first_value = false;
                }
                $query .= '))) ';
            } else {
                $query = '('.$not . 'exists (select * from usrRecTagLinks kwi left join usrTags kwd on kwi.rtl_TagID=kwd.tag_ID left join sysUGrps on ugr_ID=tag_UGrpID '
                . $sql_where
                . tagWhereExp(). '))) ';

            }
        }

        return $query;
    }
}


class BibIDPredicate extends Predicate {

    public function makeSQL() {
        $res = "TOPBIBLIO.rec_ID ".$this->get_field_value();
        return $res;
    }

    public function get_field_value(){
        global $mysqli;

        if (strpos($this->value,"<>")>0) {

            $vals = explode("<>", $this->value);
            $vals[0] = recordSearchReplacement($mysqli, $vals[0]);
            $vals[1] = recordSearchReplacement($mysqli, $vals[1]);
            $match_pred = SQL_BETWEEN.$vals[0].SQL_AND.$vals[1].' ';

        }else{

            $cs_ids = getCommaSepIds($this->value);
            if ($cs_ids && strpos($cs_ids,',')>0) {

                $pquery = &$this->getQuery();
                if(true || $pquery->search_domain == EVERYTHING){
                    $cs_ids = explode(',', $cs_ids);
                    $rsvd = array();
                    foreach($cs_ids as $recid){
                        array_push($rsvd, recordSearchReplacement($mysqli, $recid));//find new value
                    }
                    $cs_ids = implode(',',$rsvd);
                }

                // comma-separated list of ids
                $not = ($this->parent->negate)? ' not' : '';
                $match_pred = $not.' in ('.$cs_ids.')';

                $pquery->fixed_sortorder = $cs_ids; //need in case sortby:set
            }else{

                $this->value = recordSearchReplacement($mysqli, $this->value);

                $value = intval($this->value);

                if ($this->parent->lessthan) {
                    $match_pred = " < $value";
                } elseif($this->parent->greaterthan) {
                    $match_pred = " > $value";
                } else {
                    if($this->parent->negate){
                        $match_pred = ' <> '.$value;
                    }else{
                        $match_pred = '='.$value;
                    }
                }
            }

        }

        return $match_pred;
    }

}


abstract class LinkedPredicate extends Predicate {

    protected $fromField;
    protected $toField;
    protected $toRLink;

    /**
     * Constructs and returns an SQL query for linked records based on specified record types and detail types.
     *
     * This method generates an SQL query by analyzing the `value` parameter, which contains information
     * about the record type (`rty_ID`) and detail type (`dty_ID`). If the value is specified, the query
     * searches for linked records from the specific source type and field. If a parent query exists, it
     * incorporates it into the final SQL query. Otherwise, it generates a standalone query.
     *
     * @return string - Returns the constructed SQL query string.
     */
    public function makeSQL() {

        $rty_ID = null;
        $dty_ID = null;
        //if value is specified we search linked from specific source type and field
        if($this->value){
            $vals = explode('-', $this->value);
            $rty_ID = @$vals[0] ?? null;
            $dty_ID = @$vals[1] ?? '';
        }

        // Prepare record and detail type IDs for SQL
        $rty_IDs = prepareIds($rty_ID);
        $dty_IDs = prepareIds($dty_ID);

        // Initialize the additional WHERE clause for the SQL query
        $add_where = '';

        if($rty_ID==1){ //special case for relationship records
            $add_where = "rd.rec_RecTypeID=$rty_ID and rl.rl_RelationID=rd.rec_ID ";
        }else{

            $add_where = $add_where . SQL_RL_SOURCE_LINK
                . predicateId('rd.rec_RecTypeID', $rty_IDs, SQL_AND) // Add predicate for record type ID if available
                . SQL_AND;

            if(!empty($dty_IDs)){
                $add_where .= predicateId('rl.rl_DetailTypeID', $dty_IDs);
            }else{
                $add_where .= SQL_RELATION_IS_NULL;
            }
        }

        $add_from  = SQL_RECLINK;

        $select = 'TOPBIBLIO.rec_ID in (select '.$this->toField.' ';

        $pquery = &$this->getQuery();
        if ($pquery->parentquery){

            $query = $pquery->parentquery;

            // Adjust FROM and WHERE clauses for parent query
            $query["from"] = str_replace(['TOPBIBLIO', 'TOPBKMK'], ['rd', 'MAINBKMK'], $query["from"]);
            $query["where"] = str_replace(['TOPBIBLIO', 'TOPBKMK'], ['rd', 'MAINBKMK'], $query["where"]);


            // Construct the full SQL query using the parent query
            $select = $select.$query["from"].', '.$add_from.SQL_WHERE.$query["where"]
                        .SQL_AND.$add_where
                        .' '.$query["sort"].$query["limit"].$query["offset"].')';

        }else{

            $add_where = predicateId($this->fromField,$rty_IDs, SQL_AND);

            $add_where = $this->toRLink . $add_where;
            if($rty_ID!=1){
                $add_where .= SQL_AND;

                if(!empty($dty_IDs)){
                    $add_where = predicateId('rl.rl_DetailTypeID',$dty_IDs, SQL_AND);
                }else{
                    $add_where = $add_where.SQL_RELATION_IS_NULL;
                }
            }

            // Final SELECT clause for standalone query
            $select = $select.SQL_RECORDS.','.$add_from.SQL_WHERE.$add_where.')';
        }

        return $select;
    }
}

//
// this is special case
// find records that are linked from parent/top query (resource (record pointer) field in parent record = record ID)
//
// 1. take parent query from parent object
//
class LinkedFromParentPredicate extends LinkedPredicate {
    public function __construct(&$parent, $value) {
        parent::__construct( $parent, $value );

        $this->fromField = 'rl.rl_SourceID';
        $this->toField = 'rl.rl_TargetID';

        $this->toRLink = SQL_RL_TARGET_LINK;
    }
}


//
// find records that are linked (have pointers) to  parent/top query

//  resource (record pointer) detail value of parent query equals to record id
//
class LinkedToParentPredicate extends LinkedPredicate {
    public function __construct(&$parent, $value) {
        parent::__construct( $parent, $value );

        $this->fromField = 'rl.rl_TargetID';
        $this->toField = 'rl.rl_SourceID';

        $this->toRLink = SQL_RL_SOURCE_LINK;
    }
}


abstract class RelatedParentPredicate extends Predicate {

    /**
     * Constructs the WHERE clause for the SQL query.
     *
     * @param int|null $source_rty_ID The source record type ID.
     * @param string|null $relation_type_ID The relation type ID.
     * @param string $linkType SQL link type for the relationship.
     * @return string The WHERE clause.
     */
    protected function buildWhereClause($source_rty_ID, $relation_type_ID, $linkType) {
        return (($source_rty_ID) ? "rd.rec_RecTypeID = $source_rty_ID" . SQL_AND : '') .
            $linkType . SQL_AND .
            (($relation_type_ID) ? "rl.rl_RelationTypeID = $relation_type_ID" : SQL_RELATION_IS_NOT_NULL);
    }

    /**
     * Builds the full SELECT clause for the SQL query.
     *
     * @param string $add_from Additional FROM clause.
     * @param string $add_where Additional WHERE clause.
     * @return string The full SELECT clause.
     */
    protected function buildSelectClause($add_from, $add_where) {
        $pquery = &$this->getQuery();
        if ($pquery->parentquery) {
            $query = $pquery->parentquery;
            $query["from"] = str_replace(['TOPBIBLIO', 'TOPBKMK'], ['rd', 'MAINBKMK'], $query["from"]);
            $query["where"] = str_replace(['TOPBIBLIO', 'TOPBKMK'], ['rd', 'MAINBKMK'], $query["where"]);

            return $query["from"] . ', ' . $add_from . SQL_WHERE . $query["where"] . SQL_AND . $add_where .
                ' ' . $query["sort"] . $query["limit"] . $query["offset"] . ')';
        } else {
            return SQL_RECORDS . ',' . $add_from . SQL_WHERE . $add_where . ')';
        }
    }

}

/**
 * Class RelatedFromParentPredicate
 *
 * Constructs SQL for finding records related from records
 * This predicate finds records linked with a specific source type and relationship field.
 */
class RelatedFromParentPredicate extends RelatedParentPredicate {

    /**
     * Creates the SQL query for fetching records that are related from a parent source.
     *
     * @return string SQL query string for fetching related records.
     */
    public function makeSQL() {
        global $mysqli;

        $select_relto = null;
        $source_rty_ID = null;

        // Parse the provided value to get source record type and relation type.
        if ($this->value) {
            $vals = explode('-', $this->value);
            $source_rty_ID = $vals[0] ?? null;
            $relation_type_ID = $vals[1] ?? '';

            // If recursion is required, find the inverse relationship term and build the SQL.
            if ($this->need_recursion && $relation_type_ID) {
                $inverseTermId = $this->getInverseTermId($relation_type_ID);
                if ($inverseTermId) {
                    $relto = new RelatedToParentPredicate($this, $source_rty_ID . '-' . $inverseTermId);
                    $relto->stopRecursion();
                    $select_relto = $relto->makeSQL();
                }
            }
        }

        // Build the SQL query based on the source type and relation type.
        $add_from = SQL_RECLINK;
        $add_where = $this->buildWhereClause($source_rty_ID, $relation_type_ID, SQL_RL_SOURCE_LINK);

        $select = 'TOPBIBLIO.rec_ID IN (SELECT rl.rl_TargetID ';
        $select .= $this->buildSelectClause($add_from, $add_where);

        if ($select_relto !== null) {
            $select = '(' . $select . ') OR (' . $select_relto . ')';
        }

        return $select;
    }

}

/**
 * Class RelatedToParentPredicate
 *
 * Constructs SQL for finding records related to a parent record (target).
 * This predicate finds records linked to a specific source type and relationship field.
 */
class RelatedToParentPredicate extends RelatedParentPredicate {

    /**
     * Creates the SQL query for fetching records that are related to a parent target.
     *
     * @return string SQL query string for fetching related records.
     */
    public function makeSQL() {
        global $mysqli;

        $select_relto = null;
        $source_rty_ID = null;

        // Parse the provided value to get source record type and relation type.
        if ($this->value) {
            $vals = explode('-', $this->value);
            $source_rty_ID = $vals[0] ?? null;
            $relation_type_ID = $vals[1] ?? '';

            // If recursion is required, find the inverse relationship term and build the SQL.
            if ($this->need_recursion && $relation_type_ID) {
                $inverseTermId = $this->getInverseTermId($relation_type_ID);
                if ($inverseTermId) {
                    $relto = new RelatedFromParentPredicate($this, $source_rty_ID . '-' . $inverseTermId);
                    $relto->stopRecursion();
                    $select_relto = $relto->makeSQL();
                }
            }
        }

        // Build the SQL query based on the source type and relation type.
        $add_from = SQL_RECLINK;
        $add_where = $this->buildWhereClause($source_rty_ID, $relation_type_ID, SQL_RL_TARGET_LINK);

        $select = 'TOPBIBLIO.rec_ID IN (SELECT rl.rl_SourceID ';
        $select .= $this->buildSelectClause($add_from, $add_where);

        if ($select_relto !== null) {
            $select = '(' . $select . ') OR (' . $select_relto . ')';
        }

        return $select;
    }
}

/**
 * Class RelatedPredicate
 *
 * Constructs SQL for finding records related in both directions (from and to the parent).
 * This predicate searches relations in both directions for a given record type and relationship type.
 */
class RelatedPredicate extends Predicate {

    /**
     * Creates the SQL query for fetching records that are related in both directions.
     *
     * @return string SQL query string for fetching related records.
     */
    public function makeSQL() {
        global $mysqli;

        $related_rty_ID = null;
        $inverseTermId = 0;
        $relation_type_ID = 0;

        // Parse the provided value to get related record type and relation type.
        if ($this->value) {
            $vals = explode('-', $this->value);
            $related_rty_ID = $vals[0] ?? null;
            $relation_type_ID = $vals[1] ?? 0;

            // Find inverse relationship term if needed.
            if ($relation_type_ID > 0) {
                $inverseTermId = $this->getInverseTermId($relation_type_ID);
            }
        }

        // Return false if no related record type is found.
        if (!$related_rty_ID) {
            return false;
        }

        //NEW  ---------------------------
        $add_from  = SQL_RECLINK;
        $add_where = '';
        if($relation_type_ID>0){
            $add_where = $add_where.'(';
            if($inverseTermId>0){
                $add_where = $add_where."(rl.rl_RelationTypeID=$inverseTermId) OR ";
            }
            $add_where = $add_where."(rl.rl_RelationTypeID=$relation_type_ID))";
        }else{
            $add_where = $add_where. SQL_RELATION_IS_NOT_NULL;
        }

        $pquery = &$this->getQuery();
        if ($pquery->parentquery){

            $add_where = "(rd.rec_RecTypeID=$related_rty_ID) and ".$add_where;

            $query = $pquery->parentquery;

            $query["from"] = str_replace('TOPBIBLIO', 'rd', $query["from"]);
            $query["where"] = str_replace('TOPBKMK', 'MAINBKMK', $query["where"]);
            $query["where"] = str_replace('TOPBIBLIO', 'rd', $query["where"]);
            $query["from"] = str_replace('TOPBKMK', 'MAINBKMK', $query["from"]);

            $select = '(TOPBIBLIO.rec_ID in (select rl.rl_SourceID '.$query["from"].',recLinks rl '
                      .SQL_WHERE.$query["where"].SQL_AND.$add_where.' and ('.SQL_RL_TARGET_LINK.'))) OR '
                      .'(TOPBIBLIO.rec_ID in (select rl.rl_TargetID '.$query["from"].',recLinks rl '
                      .SQL_WHERE.$query["where"].SQL_AND.$add_where.' and ('.SQL_RL_SOURCE_LINK.')))';

        }else{

            $add_where = "(TOPBIBLIO.rec_RecTypeID=$related_rty_ID) and ".$add_where;

            return '(EXISTS (SELECT rl.rl_ID FROM '.SQL_RECLINK.SQL_WHERE .
                    '(rl.rl_TargetID=TOPBIBLIO.rec_ID OR rl.rl_SourceID=TOPBIBLIO.rec_ID) AND '
                    .$add_where . '))';
        }


        return $select;
    }
}


/**
* create predicate to search related and linked records
*/
class AllLinksPredicate  extends Predicate {
    public function makeSQL() {

        $source_rty_ID = $this->value;

        $add_select1 = 'TOPBIBLIO.rec_ID in (select rl1.rl_SourceID ';
        $add_select2 = 'TOPBIBLIO.rec_ID in (select rl2.rl_TargetID ';

        //NEW
        $add_from1 = 'recLinks rl1 ';
        $add_where1 = ((false && $source_rty_ID) ?"rd.rec_RecTypeID=$source_rty_ID".SQL_AND:'')
            . ' rl1.rl_TargetID=rd.rec_ID';

        $add_from2 = 'recLinks rl2 ';
        $add_where2 = ((false && $source_rty_ID) ?"rd.rec_RecTypeID=$source_rty_ID".SQL_AND:'')
            . ' rl2.rl_SourceID=rd.rec_ID';


        $pquery = &$this->getQuery();
        if ($pquery->parentquery){

            $query = $pquery->parentquery;
            //$query =  'select dtl_Value '.$query["from"].", recDetails WHERE ".$query["where"].$query["sort"].$query["limit"].$query["offset"];

            $query["from"] = str_replace('TOPBIBLIO', 'rd', $query["from"]);
            $query["where"] = str_replace('TOPBKMK', 'MAINBKMK', $query["where"]);
            $query["where"] = str_replace('TOPBIBLIO', 'rd', $query["where"]);
            $query["from"] = str_replace('TOPBKMK', 'MAINBKMK', $query["from"]);

            $select1 = $add_select1.$query["from"].', '.$add_from1.SQL_WHERE.$query["where"].SQL_AND.$add_where1.' '.$query["sort"].$query["limit"].$query["offset"].')';

            $select2 = $add_select2.$query["from"].', '.$add_from2.SQL_WHERE.$query["where"].SQL_AND.$add_where2.' '.$query["sort"].$query["limit"].$query["offset"].')';


        }else{

            $ids = prepareIds($source_rty_ID);
            if(count($ids)>1){
                $add_where1 = $add_where1.' and rl1.rl_TargetID in ('.implode(',',$ids).')';
                $add_where2 = $add_where2.' and rl2.rl_SourceID in ('.implode(',',$ids).')';
            }elseif(!empty($ids)){
                $add_where1 = $add_where1.' and rl1.rl_TargetID = '.$ids[0];
                $add_where2 = $add_where2.' and rl2.rl_SourceID = '.$ids[0];
            }else{
                return SQL_FALSE;
            }

            $select1 = $add_select1.SQL_RECORDS.', recLinks rl1 WHERE '.$add_where1.')';
            $select2 = $add_select2.SQL_RECORDS.', recLinks rl2 WHERE '.$add_where2.')';

        }

        $select = '(' . $select1 . ' OR ' .$select2. ')';

        return $select;
    }
}

define('SQL_LINKED_EXISTS', '(exists (select dtl_ID from defDetailTypes, recDetails bd '
            .'where bd.dtl_RecID=TOPBIBLIO.rec_ID and dty_ID=dtl_DetailTypeID and dty_Type="resource" LIMIT 1))');
//
// find records that have pointed records
//
class LinkToPredicate extends Predicate {
    public function makeSQL() {
        if ($this->value) {

            $ids = prepareIds($this->value);
            if(count($ids)>1){   //??? seems wrong
                return SQL_FALSE;
            }else{
                return str_replace('LIMIT',' and bd.dtl_Value in (' . join(',', $ids) . ') LIMIT',SQL_LINKED_EXISTS);
            }
        }
        else {
            return SQL_LINKED_EXISTS;
        }
    }
}

//
// find records that are pointed (targets)
// search if parents(source) records exist
//
class LinkedToPredicate extends Predicate {
    public function makeSQL() {
        if ($this->value) {

            $ids = prepareIds($this->value);
            if(count($ids)>1){  //??? seems wrong
                return SQL_FALSE;
            }else{
                return str_replace('LIMIT',' and bd.dtl_RecID in (' . join(',', $ids) . ') LIMIT',SQL_LINKED_EXISTS);
            }
        }
        else {
            return SQL_LINKED_EXISTS;
        }
    }
}


class RelatedToPredicate extends Predicate {
    public function makeSQL() {
        if ($this->value) {
            $ids = prepareIds($this->value);
            $ids = "(" . implode(",",$ids) . ")";
            return "(exists (select * from recLinks where (rl_RelationID is not null) "
            ." and ((rl_TargetID=TOPBIBLIO.rec_ID and rl_SourceID in $ids) "
            ."   or (rl_SourceID=TOPBIBLIO.rec_ID and rl_TargetID in $ids))  ))";
        }
        else {
            /* just want something that has a relation */
            return "TOPBIBLIO.rec_ID in (select distinct rl_TargetID from recLinks WHERE rl_RelationID is not null '
            .'union select distinct rl_SourceID from recLinks WHERE rl_RelationID is not null)";
        }
    }
}


class RelationsForPredicate extends Predicate {
    public function makeSQL() {
        global $mysqli;
        $ids = prepareIds($this->value);
        $ids = "(" . implode(",", $ids) . ")";

        /* Okay, this isn't the way I would have done it initially, but it benchmarks well:
        * All of the methods above were taking 4-5 seconds.
        * Putting recLinks into the list of tables at the top-level gets us down to about 0.8 seconds, which is alright, but disruptive.
        * Coding the 'relationsfor:' predicate as   TOPBIBLIO.rec_ID in (select distinct rec_ID from recLinks where (rl_RelationID=TOPBIBLIO.rec_ID etc etc))
        *   gets us down to about 2 seconds, but it looks like the optimiser doesn't really pick up on what we're doing.
        * Fastest is to do a SEPARATE QUERY to get the record IDs out of the bib_relationship table, then pass it back encoded in the predicate.
        * Certainly not the most elegant way to do it, but the numbers don't lie.
        */
        $res = $mysqli->query("select group_concat( distinct rec_ID ) from Records, recLinks where (rl_RelationID=rec_ID or rl_TargetID=rec_ID or rl_SourceID=rec_ID)
            and (rl_RelationID is not null)
            and (rl_SourceID in $ids or rl_TargetID in $ids) and rec_ID not in $ids");
        $ids = $res->fetch_row();
        $ids = $ids[0];

        if (! $ids) {
            return "0";
        } else{
            return "TOPBIBLIO.rec_ID in ($ids)";
        }
    }
}


class AfterPredicate extends Predicate {

    public function makeSQL() {

         try{
            $timestamp = new DateTime($this->value);

            $not = ($this->parent->negate)? 'not' : '';
            $datestamp = $timestamp->format(DATE_8601);

            return "$not TOPBIBLIO.rec_Modified >= '$datestamp'";

         } catch (Exception  $e){
            //print $this->value.' => NOT SUPPORTED<br>';
         }
        return '1';
    }
}


class BeforePredicate extends Predicate {

    public function makeSQL() {
         try{
            $timestamp = new DateTime($this->value);

            $not = ($this->parent->negate)? 'not' : '';
            $datestamp = $timestamp->format(DATE_8601);

            return "$not TOPBIBLIO.rec_Modified <= '$datestamp'";

         } catch (Exception  $e){
            //print $this->value.' => NOT SUPPORTED<br>';
         }
        return '1';
    }
}


class DatePredicate extends Predicate {
    public $col;

    public function __construct(&$parent, $col, $value) {
        $this->col = $col;
        parent::__construct($parent, $value);
    }

    public function makeSQL() {
        $col = $this->col;

        if($this->isDateTime()){
            $not = ($this->parent->negate)? 'not' : '';
            $s = $this->makeDateClause();
            if(strpos($s, "between")===0){
                return " $col $not ".$s;
            }else{
                return " $not $col ".$s;
            }
        }
        return '1';
    }
}

class DateAddedPredicate extends DatePredicate {
    public function __construct(&$parent, $value) {
        parent::__construct($parent, 'TOPBIBLIO.rec_Added', $value);
    }
}

class DateModifiedPredicate extends DatePredicate {
    public function __construct(&$parent, $value) {
        parent::__construct($parent, 'TOPBIBLIO.rec_Modified', $value);
    }
}


class WorkgroupPredicate extends Predicate {
    public function makeSQL() {
        global $mysqli, $currUserID;

        if(strtolower($this->value)=='currentuser' || strtolower($this->value)=='current_user'){
            $this->value = $currUserID;
        }

        $eq = ($this->parent->negate)? '!=' : '=';
        if (is_numeric($this->value)) {
            return "TOPBIBLIO.rec_OwnerUGrpID $eq ".intval($this->value);
        }
        elseif (preg_match(REGEX_CSV, $this->value)) {
            $in = ($this->parent->negate)? 'not in' : 'in';
            return "TOPBIBLIO.rec_OwnerUGrpID $in (" . $this->value . ")";
        }
        else {
            $val = $mysqli->real_escape_string($this->value);
            return "TOPBIBLIO.rec_OwnerUGrpID $eq (select grp.ugr_ID from sysUGrps grp where grp.ugr_Name = '$val' limit 1)";
        }
    }
}

class SpatialPredicate extends Predicate {

    public function makeSQL() {
        return "(exists (select dtl_ID from recDetails bd
            where bd.dtl_RecID=TOPBIBLIO.rec_ID and bd.dtl_Geo is not null
            and ST_Contains(ST_GeomFromText('{$this->value}'), bd.dtl_Geo) limit 1))";//MBRContains
    }
}

class CoordinatePredicate extends Predicate {

    private $coordFunction;

    public function __construct(&$parent, $value, $coordFunction) {
        parent::__construct( $parent, $value );

        $this->coordFunction = $coordFunction;
    }

    public function makeSQL() {
        $op = '';

        if ($this->parent->lessthan) {
            $op = ($this->parent->negate)? '>=' : '<';
        } elseif($this->parent->greaterthan) {
            $op = ($this->parent->negate)? '<=' : '>';
        }

        $val = floatval($this->value);

        if ($op!='' && $op[0] == '<') {
            // see if the northernmost point of the bounding box lies south of the given latitude
            return "(exists (select * from recDetails bd
            where bd.dtl_RecID=TOPBIBLIO.rec_ID and bd.dtl_Geo is not null
            and {$this->coordFunction}( ST_PointN( ST_ExteriorRing( ST_Envelope(bd.dtl_Geo) ), 4 ) ) $op $val limit 1))";
        }
        elseif($op!='' && $op[0] == '>') {
            // see if the SOUTHERNmost point of the bounding box lies north of the given latitude
            return "(exists (select * from recDetails bd
            where bd.dtl_RecID=TOPBIBLIO.rec_ID and bd.dtl_Geo is not null
            and {$this->coordFunction}( ST_StartPoint( ST_ExteriorRing( ST_Envelope(bd.dtl_Geo) ) ) ) $op $val limit 1))";

        }
        elseif($this->parent->exact) {
            $op = $this->parent->negate? "!=" : "=";
            // see if there is a Point with this exact latitude
            return "(exists (select * from recDetails bd
            where bd.dtl_RecID=TOPBIBLIO.rec_ID and bd.dtl_Geo is not null and bd.dtl_Value = 'p'
            and {$this->coordFunction}(bd.dtl_Geo) $op $val limit 1))";
        }

            //Envelope - Bounding rect
            //ExteriorRing - exterior ring for polygone

            if (strpos($this->value,"<>")>0) {
                $vals = explode("<>", $this->value);
                $match_pred = $this->coordFunction.'( ST_Centroid( ST_Envelope(bd.dtl_Geo) ) ) between '.floatval($vals[0]).SQL_AND.floatval($vals[1]).' ';
            }else{
                // see if this latitude passes through the bounding box
                $match_pred = floatval($this->value)." between {$this->coordFunction}( ST_StartPoint( ST_ExteriorRing( ST_Envelope(bd.dtl_Geo) ) ) )
                        and {$this->coordFunction}( ST_PointN( ST_ExteriorRing( ST_Envelope(bd.dtl_Geo) ), 4 ) )";
            }

            return "(exists (select * from recDetails bd
            where bd.dtl_RecID=TOPBIBLIO.rec_ID and bd.dtl_Geo is not null
            and $match_pred limit 1))";

    }
}

class HHashPredicate extends Predicate {
    public function makeSQL() {
        global $mysqli;

        $op = '';
        if ($this->parent->exact) {
            $op = $this->parent->negate? "!=" : "=";
            return "TOPBIBLIO.rec_Hash $op '" . $mysqli->real_escape_string($this->value) . "'";
        }
        else {
            $op = $this->parent->negate? " not like " : " like ";
            return "TOPBIBLIO.rec_Hash $op '" . $mysqli->real_escape_string($this->value) . "%'";
        }
    }
}
/*

keywords for 'q' parameter
u:  url
t:  title
tag:   tag
id:  id
n:   description
usr:   user id
any:

function construct_legacy_search() {
$q = '';

if (@$_REQUEST['search_title']) $_REQUEST['t'] = $_REQUEST['search_title'];
if (@$_REQUEST['search_tagString']) $_REQUEST['k'] = $_REQUEST['search_tagString'];
if (@$_REQUEST['search_url']) $_REQUEST['u'] = $_REQUEST['search_url'];
if (@$_REQUEST['search_description']) $_REQUEST['n'] = $_REQUEST['search_description'];
if (@$_REQUEST['search_rectype']) $_REQUEST['r'] = $_REQUEST['search_rectype'];
if (@$_REQUEST['search_user_id']) $_REQUEST['uid'] = $_REQUEST['search_user_id'];


if (@$_REQUEST['t']) $q .= $_REQUEST['t'] . ' ';
if (@$_REQUEST['k']) {
$K = split(',', $_REQUEST['k']);
foreach ($K as $k) {
if (strpos($k, '"'))
$q .= 'tag:' . $k . ' ';
else
$q .= 'tag:"' . $k . '" ';
}
}
if (@$_REQUEST['u']) $q .= 'u:"' . $_REQUEST['u']. '" ';
if (@$_REQUEST['n']) $q .= 'n:"' . $_REQUEST['n']. '" ';
if (@$_REQUEST['r']) $q .= 't:' . intval($_REQUEST['r']) . ' ';// note: defRecTypes was 'r', now 't' (for TYPE!)
if (@$_REQUEST['uid']) $q .= 'usr:' . intval($_REQUEST['uid']) . ' ';
if (@$_REQUEST['bi']) $q .= 'id:"' . $_REQUEST['bi'] . '" ';
if (@$_REQUEST['a']) $q .= 'any:"' . $_REQUEST['a'] . '" ';

$_REQUEST['q'] = $q;
}
*/
?>
