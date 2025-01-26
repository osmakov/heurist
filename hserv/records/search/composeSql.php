<?php
// declare(strict_types=1)
/**
* composeSql.php - translates heurist JSON query to SQL query
*                  or
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
use hserv\structure\ConceptCode;

// @todo - get rid global variables ie $mysqli

require_once dirname(__FILE__).'/composeSqlOld.php';

/*
Heurist is either json array  { conjunction: [ {predicate} , {predicate}, .... ] }
or simplified plain string format

Predicate is a pair of keyword and search value

t 7 t:7  => {"t":"7"}
t:7 f:20:value f1:"value with space" => {"t":"7","f:20":"value", "f:1":"value with space"}
: means that it should have value after and before it   t  :  7  =? t:7
linkedto:[ t:4 r:6421 ]  => {"linkedto":[{"t":4},{"r":6421}]}    [] - means subquery

conjunction:  not, all (default)
any        OR
@todo  notall     NOT ( AND )
@todo  notany     NOT ( OR )

predicate:   keyword:value

KEYWORDS

plain            query in old plain text format
url, u:          url
notes, n:        record heder notes (scratchpad)
title:           title contains
added:           creation date
date, modified:  edition date  (rec_Modified
after, since:
before:

addedby:         added by specified user (rec_AddedByUGrpID
owner:  - owned by  (rec_OwnerUGrpID
access:  visibility outiside owner group (rec_NonOwnerVisibility

user, usr:   user id  - bookmarked by user
tag, keyword, kwd:   tag

id, ids:  id
t, type:  record type id (for rectype allowed name or concept code also)
f, field:   field id
count, cnt, fc:field id  search for number of field instances

latitude, lat:
longitude, long, lng:
geo:

hhash:


link predicates

linked_to: pointer field type : query     recordtype
linkedfrom:
related: related in any direction
relatedfrom: relation type id (termid)    recordtype
related_to:
links: recordtype
relf, r: - relation type (enum)
    relf:  - field from relationship record  (for example relf:10 - start date)

    "rf:245":[{"t":4},{"r":6421}] - related from organization (4) with relation type 6421


recordtype is added to link query  as first predicate


sortby,sort,s - sort phrases must be on top level array - all others will be ignored

sort options:

set, fixed     as defined in ids:
r, rating      by bkm_Rating
p, popularity  by rec_Popularity
u, url         rec_URL
m, modified    bkm_Modified, rec_Modified
a, added       bkm_Added, rec_Added
t, title       rec_Title
rt, type       rec_RecTypeID
f, field       by dty_ID  "sortby":"f:25"

-----
VALUE

1)  Literal   "f:1":"Peter%"
2)  CSV       "ids":"1,2,3,4"
3)  WKT       "f:5":"POLYGON ((30 10, 40 40, 20 40, 10 20, 30 10))"
4)  Query     "linked_to:15": [{ t:4 }, {"f:1":"Alex" } ]
"f:10": {"any":[ {t:4}, {} ] }

5) Find all records without field  "f:5":"NULL"
6) Find all records with any value in field  "f:5":""
7) Find all records with value not equal ABC  "f:5":"-ABC"

Compare predicates
        @    fulltext (any word)
        @-   no words
        @+   all words
    OR
        -   negate value
            <> between
            OR
            = exact  (== case sensetive)
            > <  for numeric and dates only

Valuew Prefixes to search record via registered files
 ^  file size
 @  obfuscation id


================================================

new simplified heurist query rules.

1) keywords and values are separated either by : or spaces or for subqueries by parentheses.

t 10 f1 Peter  is equal to t:10 f1:Peter and produces [{"t":"10"},{"f:1":"Peter"}]
t Person linkedto(t Place London)  is equal to  t:10 lt(t:12 London) and produces [{"t":"10"},{"lt":[{"t":"12"},{"title":"London"}]}]

2) search values follows the same rules as currently ones

a) To isolate several words value use double quotes:  t 12 "City of London"  =>  [{"t":"12"},{"title":"City of London"}]

b) Use comparison predicates as first character of search value:
f1 =Boris    =>  [{"f:1":"=Boris"}]
f9 "<100 years ago"   => [{"f:9":"<100 years ago"}]
t 10 f20 -male  =>  [{"t":"10"},{"f:20":"-male"}]

c) If keyword is not recognized then word is assumed as value for default keyword "title"
London means title:London

Here is cheat sheet of Heurist search keywords

Record type: typename|typeid|type|t
Record header fields:  ids|id|title|added|url|notes|addedby|access
Record rec_Modified:  modified|before|after|since
Record rec_OwnerUGrpID:  workgroup|wg|owner
Base field:    field|f
Base field counts:    count|cnt|fc
Search location dtl_Geo:   geo : WKT
Search record by tags:     tag|keyword|kwd

Search by resource (record pointer/link):  linked_to|linkedto|linkto|link_to|lt
                                         linked_from|linkedfrom|linkfrom|link_from|lf
Search by relationships:  related_to|relatedto|rt
                                         related_from|relatedfrom|rf
Search by linked, related in both directions: related
                                                                      links
Search for field in relationship record (#1)  relation type:  r
                                                                    any other field:   relf:XXX

Keywords for all groups (except record header fields) are synonyms. The last one in the list is used as the default one.

Search by linked/related records should refer to subquery for these records or list of record ids.
Example:  search for person linked to London (recid 452)
t Person  linkedto(t Place London)
[{"t":"Person"},{"lt":[{"t":"Place"},{"title":"London"}]}]
t 10 lt 452

Sort keywords: sortby|sort|s    - sort order is applicable for first level query, it is ignored in subqueries
Logic operators:  any|all|not    -  by default predicates are logically connected by Conjunction (AND), to use Disjunction (OR) use "any" keyword with the following subquery

any(London Paris Berlin)

To test new format in UI place * in the beginning of search string.

*/


/*

select


*/
global $mysqli,$wg_ids,$publicOnly,$currUserID,$is_admin,$params_global,$top_query;
global $rty_id_relation,$dty_id_relation_type;

$mysqli = null;
$wg_ids = null; //groups current user is member
$publicOnly = false;
$currUserID = 0;
$is_admin = false;

//keep params for debug only!
$params_global;
$top_query;

$rty_id_relation = 1; // $system->defineConstant('RT_RELATION')
$dty_id_relation_type = 6; // $system->defineConstant('DT_RELATION_TYPE')


/*

[{"t":14},{"f:74":"X0"},
{"related_to:100":[{"t":"15"},{"f:80":"X1"}]},   //charge/conviction
{"relatedfrom:109":[{"t":"10"},{"f:97":"X2"},{"f:92":"X3"},{"f:98":"X4"},{"f:20":"X5"},{"f:18":"X6"}]},   //persons involved

{"related_to:99":[{"t":"12"}, {"linked_to:73":[{"t":"11"},{"f:1":"X7"}]}, {"linkedfrom:90":[{"t":"16"},{"f:89":"X8"}]}  ]}  //address->street(11)  and linkedfrom role(16) role of place
]

[{"t":"12"},{"linkedfrom:134":{"t":"49"}}]   places with events
[{"t":"12"}, {"not":{"ids": [{"t":"12"},{"linkedfrom:134":{"t":"4"}}] }} ]  places not linked from events

*/

/**
* Parses simplified heurist query and returns it in  json format
*
* (t 10 Peter) => [{"t":10},{"title":"Peter"}]
*
* @param String $query - heurist query in simplified notation
*/
function parse_query_to_json($query){

    $res = array();
    $subres = array();//parsed subqueries

                    //     /\(([^[\)]|(?R))*\)/'
    $regex_get_subquery = '/\(([^[\)])*\)/'; // extracts (aaaa) from string
    $regex_remove_parenthesis = '/(?:^[\s\(]+)|(?:[\s\)]+$)/';

    if($query!=null && $query!=''){

        //1) get subqueries
        $cnt = preg_match_all($regex_get_subquery, $query, $subqueries);


        if($cnt>0){

            $subqueries = $subqueries[0];
            foreach($subqueries as $subq){

                 //trim parenthesis in begining and end of string
                 $subq = preg_replace_callback($regex_remove_parenthesis, function($m) {return '';}, $subq);

                 $r = parse_query_to_json($subq);
                 $subres[] = ($r)?$r:array();
            }

            $query = preg_replace_callback($regex_get_subquery,
                function($m) {
                    return ' [subquery] ';
                }, $query);


        }
    }else{
        return false; //empty string
    }

//2) split by words ignoring quotes
    $cnt = preg_match_all('/[^\s":]+|"([^"]*)"|:{1}/',  $query, $matches);
    if($cnt>0){

        $matches = $matches[0];

        $idx = 0;

        $keyword = null;

//3) detect predicates  - they are in pairs: keyword:value
        foreach ($matches as $word)
        {
            if($word==':'){
              continue;
            }

            $word = trim($word,'"');


            if($keyword==null)
            {
                $dty_id = null;

                if(preg_match('/^(typename|typeid|type|t)$/', $word, $match)>0){ //rectype

                    $keyword = 't';

                }elseif(preg_match('/^(ids|id|title|added|modified|url|notes|addedby|access|before)$/', $word, $match)>0){ //header fields

                    $keyword = $match[0];

                }elseif(preg_match('/^(workgroup|wg|owner)$/', $word, $match)>0){ //ownership (header field)

                    $keyword = 'owner';

                }elseif(preg_match('/^(user|usr)$/', $word, $match)>0){ //ownership (header field)

                    $keyword = 'user';

                }elseif(preg_match('/^(after|since)$/', $word, $match)>0){ //special case for modified

                    $keyword = 'after';

                }elseif(preg_match('/^(sortby|sort|s)$/', $word, $match)>0){ //sort keyword

                    $keyword = 'sortby';

                }elseif(preg_match('/^(field|f)(\d*)$/', $word, $match)>0){ //base field

                    $keyword = 'f';
                    $dty_id = @$match[2];

                }elseif(preg_match('/^(count|cnt|fc|geo|file)(\d*)$/', $word, $match)>0){ //special base field

                    $keyword = $match[1];
                    $dty_id = @$match[2];

                }elseif(preg_match('/^(tag|keyword|kwd)$/', $word, $match)>0){ //tags

                    $keyword = 'tag';

                }elseif(preg_match('/^(linked_to|linkedto|linkto|link_to|lt)(\d*)$/', $word, $match)>0){ //resource - linked to

                    $keyword = 'lt';
                    $dty_id = @$match[2];

                }elseif(preg_match('/^(linked_from|linkedfrom|linkfrom|link_from|lf)(\d*)$/', $word, $match)>0){ //backward link

                    $keyword = 'lf';
                    $dty_id = @$match[2];

                }elseif(preg_match('/^(related_to|relatedto|rt)(\d*)$/', $word, $match)>0){ //relationship to

                    $keyword = 'rt';
                    $dty_id = @$match[2];

                }elseif(preg_match('/^(related_from|relatedfrom|rf)(\d*)$/', $word, $match)>0){ //relationship from

                    $keyword = 'rf';
                    $dty_id = @$match[2];

                }elseif(preg_match('/^(related|links)$/', $word, $match)>0){ //link or relation in any direction

                    $keyword = $match[0];

                }elseif(preg_match('/^(relf|r)(\d*)$/', $word, $match)>0){ //field from relation record

                    $keyword = $match[1];
                    $dty_id = @$match[2];

                }elseif(preg_match('/^(any|all|not)$/', $word, $match)>0){ //logical operation

                    $keyword = $match[0];

                }else{
                    $warn = 'Keywords '.$word.' not recognized';
                    //by default this is title (or add to previous value or skip pair?)
                    if($word=='[subquery]'){
                        $res = array_merge($res, array_shift($subres) );
                    }else{

                        if(false && @$res[count($res)-1]['title']){
                            $res[count($res)-1]['title'] .= (' '.$word);
                        }else{
                            array_push($res, array( 'title'=>$word ));
                        }
                    }
                    continue;
                }

                if($keyword){
                    if($dty_id>0){
                        $keyword = $keyword.':'.$dty_id;
                    }
                }

            }//keyword turn
            else{

                if($word=='[subquery]'){
                    $word = array_shift($subres);
                }

                array_push($res, array( $keyword=>$word ));

                $previous_key = $keyword;

                $keyword = null;
            }
        }//for

    }else{
        $res =  false; //no entries
    }

    return $res;


}//END parse_query_to_json

// $params need for
// q - json query array
// w - domain all or bookmarked or e(everything)
// limit and offset
// nested = false - to avoid nested queries - it allows performs correct count for distinct target record type (see faceted search)
// NOT USED - s - sort
// @todo vt - visibility type
// @todo publiconly
//
function get_sql_query_clauses_NEW($db, $params, $currentUser=null){

    global $mysqli, $wg_ids, $currUserID, $publicOnly, $params_global, $top_query
        , $rty_id_relation, $dty_id_relation_type, $is_admin;


    if(defined('RT_RELATION')){
        $rty_id_relation = RT_RELATION;
    }
    if(defined('DT_RELATION_TYPE')){
        $dty_id_relation_type = DT_RELATION_TYPE;
    }

    $params_global = $params;

    $mysqli = $db;

    if(!$params){ $params = array();} // request

    if(is_array(@$params['q'])){
        $query_json = $params['q'];
    }else{
        $query_json = json_decode(@$params['q'], true);
    }

    // 1. DETECT CURRENT USER AND ITS GROUPS, if not logged search only all records (no bookmarks) ----------------------
    $wg_ids = array();//may be better use $system->getUserGroupIds() ???
    if($currentUser && @$currentUser['ugr_ID']>0){
        if(@$currentUser['ugr_Groups']){
            $wg_ids = array_keys($currentUser['ugr_Groups']);
        }
        $currUserID = $currentUser['ugr_ID'];
        array_push($wg_ids, $currUserID);

        if(!@$params['w']) {$params['w'] = 'all';}

    }else{
        $currUserID = 0;
        $params['w'] = 'all';
    }
    array_push($wg_ids, 0);// be sure to include the generic everybody workgroup

    $publicOnly = (@$params['publiconly'] == 1);//@todo change to vt - visibility type parameter of query

    // set is_admin
    $is_admin = true; //2023-11-28 TEMPORARY DISABLE field visibility check  $is_admin = $currUserID == 2
    if(!$is_admin && $currUserID > 0){
        // Check if user is part of db admin group
        $db_admin_id = mysql__select_value($mysqli, 'SELECT sys_OwnerGroupID FROM sysIdentification');
        $db_admin_id = !$db_admin_id || !intval($db_admin_id) < 1 ? 1 : intval($db_admin_id);

        $admin_query = "SELECT ugl_ID FROM sysUsrGrpLinks WHERE ugl_UserID = $currUserID AND ugl_GroupID = $db_admin_id AND ugl_Role='admin'";

        $is_admin = mysql__select_value($mysqli, $admin_query);
        $is_admin = $is_admin && intval($is_admin) < 0;
    }

    // 2. DETECT SEARCH DOMAIN ------------------------------------------------------------------------------------------
    if (strcasecmp($params['w'],'B') == 0  ||  strcasecmp($params['w'],BOOKMARK) == 0) {    // my bookmark entries
        $search_domain = BOOKMARK;
    } elseif($params['w'] == 'e') { //everything - including temporary
        $search_domain = EVERYTHING;
    } elseif($params['w'] == 'nobookmark') { //all without BOOKMARK
        $search_domain = NO_BOOKMARK;
    } else {                // all records entries
        $search_domain = "a";
    }

    //$system->isDbOwner()
    //for database owner we will search records of any workgroup and view access
    //@todo UNLESS parameter owner is not defined explicitely
    if($currUserID==2 && $search_domain != BOOKMARK){
        $wg_ids = array();
    }
    //$use_user_wss = (@$params['use_user_wss']===true);

    $query = new HQuery( "0", $query_json, $search_domain, $currUserID );
    $top_query = $query;
    $query->makeSQL();


    if($query->error_message){
        return array('error'=>$query->error_message);
    }

    //1. create tree of predicates
    //2. make where

    // 4c. SPECIAL CASE for USER WORKSET
    if(@$params['use_user_wss']===true && $currUserID>0){

        $q2 = 'select wss_RecID from usrWorkingSubsets where wss_OwnerUGrpID='.$currUserID.' LIMIT 1';
        if(mysql__select_value($mysqli, $q2)>0){
            $query->where_clause = '(exists (select wss_RecID from usrWorkingSubsets where wss_RecID=r0.rec_ID and wss_OwnerUGrpID='.$currUserID.'))'
                . ($query->where_clause && trim($query->where_clause)!=''? SQL_AND.$query->where_clause :'');
        }
    }


    // 6. DEFINE LIMIT AND OFFSET ---------------------------------------------------------------------------------------

    $limit = get_limit($params);

    $offset = get_offset($params);

    if(!$query->where_clause || trim($query->where_clause)==''){
        $query->where_clause = "(1=1)";
    }

    // 7. COMPOSE QUERY  ------------------------------------------------------------------------------------------------
    return array("from"=>" FROM ".$query->from_clause, "where"=>$query->where_clause, "sort"=>$query->sort_clause, "limit"=>" LIMIT $limit", "offset"=>($offset>0? " OFFSET $offset " : ""));

}

class HQuery {

    public $from_clause = '';
    public $where_clause = '';
    public $sort_clause = '';
    public $recVisibilityType;
    public $parentquery = null;

    public $error_message = null;

    public $top_limb = array();
    public $sort_phrases;
    public $sort_tables; // sorting may require the introduction of more tables

    public $currUserID;
    public $search_domain;

    public $level = "0";
    public $cnt_child_query = 0;

    public $fixed_sortorder = null;


    public function __construct($level, $query_json, $search_domain=null, $currUserID=null) {

        $this->level = $level;
        $this->search_domain = $search_domain;
        $this->currUserID = $currUserID;

        $this->top_limb = new HLimb($this, "all", $query_json);
        // $top_limbs =

        //find search phrases
        if($level==0){
            $this->extractSortPharses( $query_json );
        }
    }

    public function makeSQL(){

        global $publicOnly, $wg_ids, $is_admin;

        $res = $this->top_limb->makeSQL();//it creates where_clause and fill tables array

        $from_records = 'Records r'.$this->level.' ';

        if($this->top_limb->error_message){
             $this->error_message = $this->top_limb->error_message;
             return;
        }elseif($this->search_domain!=null){

            if ($this->search_domain == NO_BOOKMARK){
                $this->from_clause = $from_records;
            }elseif($this->search_domain == BOOKMARK) {
                $this->from_clause = 'usrBookmarks b LEFT JOIN '.$from_records.' ON b.bkm_recID=r'.$this->level.'.rec_ID ';
            }else{
                $this->from_clause = $from_records.' LEFT JOIN usrBookmarks b ON b.bkm_recID=r'.$this->level.'.rec_ID and b.bkm_UGrpID='.$this->currUserID.' ';
            }

        }else{
            $this->from_clause =  $from_records;
        }

        if(!$is_admin){
            $this->from_clause .= ' LEFT JOIN defRecStructure ON rst_RecTypeID = r'.$this->level.'.rec_RecTypeID ';
        }

        //add usrRecPermission join
        if($this->level=='0' && !$publicOnly && $this->currUserID>0 && $this->currUserID!=2){
            $this->from_clause = $this->from_clause.' LEFT JOIN usrRecPermissions ON rcp_RecID=r0.rec_ID ';
        }
        /*
        if($this->level=='0' && $this->currUserID>0 && $use_user_wss===true)
        {
            $q2 = 'select wss_RecID from usrWorkingSubsets where wss_OwnerUGrpID='.$this->currUserID.' LIMIT 1';
            if(mysql__select_value($mysqli, $q2)>0){
                $this->from_clause = $this->from_clause.' LEFT JOIN usrWorkingSubsets ON wss_RecID=r0.rec_ID and wss_OwnerUGrpID='.$this->currUserID.' ';
            }
        }*/

        // add tables from other
        foreach($this->top_limb->tables as $table){
            $this->from_clause =  $this->from_clause.", ".$table; //.$this->level;    ART20150807
            if($table=="recDetails rd"){
                $this->where_clause = " (r".$this->level.".rec_ID=rd".$this->level.".dtl_RecID) AND ";
            }
        }
        $this->where_clause = $this->where_clause . $this->top_limb->where_clause." ";

        //add owner access restriction @todo look at parmeter owner
        if($this->level=='0'){ //apply this restriction for top level only
            if($publicOnly){
                $recVisibilityType = "public";
            }else{
                $recVisibilityType = null;
            }

            $where2 = '';


            if($recVisibilityType && $recVisibilityType!="hidden"){
                $where2 = '(r0.rec_NonOwnerVisibility="'.$recVisibilityType.'")';//'pending','public','viewable'
            }else{
                $where2_conj = '';
                if($recVisibilityType){ //hidden
                    $where2 = '(r0.rec_NonOwnerVisibility="hidden")';
                    $where2_conj = SQL_AND;
                }elseif($this->currUserID!=2){ //by default always exclude "hidden" for not database owner
                    //$where2 = '(not r0.rec_NonOwnerVisibility="hidden")';

                    $where2 = '(r0.rec_NonOwnerVisibility in ("public","pending"))';
                    if ($this->currUserID>0){ //logged in

                        //if there is entry for record in usrRecPermissions current user must be member of allowed groups
                        $where2 = $where2.' or (r0.rec_NonOwnerVisibility="viewable" and (rcp_UGrpID is null or rcp_UGrpID in ('
                                .join(',', $wg_ids).')))';
                    }

                    $where2_conj = ' or ';
                }elseif($this->search_domain != BOOKMARK){ //database owner can search everything (including hidden)
                    $wg_ids = array();
                }

                if($this->currUserID>0 && !isEmptyArray($wg_ids)){
                    $where2 = '( '.$where2.$where2_conj.'r0.rec_OwnerUGrpID ';
                    if(count($wg_ids)>1){
                        $where2 = $where2 . 'in (' . join(',', $wg_ids).') )';
                    }else{
                        $where2 = $where2 .' = '.$wg_ids[0] . ' )';
                    }
                }
            }

            if($this->search_domain!=EVERYTHING){
                $where2 = '(not r0.rec_FlagTemporary)'.($where2?SQL_AND:'').$where2;
            }

            if(trim($where2)!=''){
                if(trim($this->where_clause)!=''){
                    $this->where_clause = $this->where_clause. SQL_AND . $where2;
                }else{
                    $this->where_clause = $where2;
                }
            }

            //apply sort clause for top level only
            $this->createSortClause();
        }

    }

    //
    // sort phrases must be on top level array - all others will be ignored
    // {"sort":"rt"}
    //
    private function extractSortPharses( $query_json ){

        $this->sort_phrases = array();

        foreach ($query_json as $key => $value){
            if(is_numeric($key) && is_array($value)){  //this is sequental array
                $key = array_keys($value);
                $key = $key[0];
                if(is_string($value[$key])){
                    $value = strtolower($value[$key]);
                }else{
                    continue;
                }
            }
            if( ($key == 'sortby' || $key == 'sort' || $key == 's')
                &&!in_array($value, $this->sort_phrases)){ //this is sort phrase

                        $this->sort_phrases[] = $value;
            }
        }

    }

    //
    // {"sort":"f:233"}  {"sort":"-title"}  {"sort":"set:4,5,1"}
    //
    private function createSortClause() {

        global $mysqli;

        $sort_fields = array();
        $sort_expr = array();


        foreach($this->sort_phrases as $subtext){

            // if sortby: is followed by a -, we sort DESCENDING; if it's a + or nothing, it's ASCENDING
            $scending = '';
            if ($subtext[0] == '-') {
                $scending = ' DESC ';
                $subtext = substr($subtext, 1);
            } elseif($subtext[0] == '+') {
                $subtext = substr($subtext, 1);
            }
            $dty_ID = null;
            if(strpos($subtext,':')>0){
                list($subtext,$dty_ID) = explode(':',$subtext);
            }

            switch (strtolower($subtext)) {
                case 'set': case 'fixed': //no sort - returns as is

                        if($this->fixed_sortorder){
                            $cs_ids = $this->fixed_sortorder;
                        }else{
                            $cs_ids = getCommaSepIds($dty_ID);
                        }
                        if($cs_ids){
                            $sort_fields[] = 'r0.rec_ID';
                            $sort_expr[] = 'FIND_IN_SET(r0.rec_ID, \''.$cs_ids.'\')';
                        }

                        break;
                case 'r': case 'rating':
                    if ($this->search_domain == BOOKMARK) {
                        if(!in_array('bkm_Rating', $sort_fields)) {
                            $sort_fields[] = 'bkm_Rating';
                            $sort_expr[] = 'bkm_Rating'.$scending;
                        }
                        break;
                    }
                case 'p': case 'popularity':
                    if(!in_array('r0.rec_Popularity', $sort_fields)) {
                        $sort_fields[] = 'r0.rec_Popularity';
                        $sort_expr[] = 'r0.rec_Popularity'.$scending;
                    }
                    if(!in_array('r0.rec_ID', $sort_fields)) {
                        $sort_fields[] = 'r0.rec_ID';
                        $sort_expr[] = 'r0.rec_ID'.$scending;
                    }
                    break;
                case 'u': case 'url':
                    if(!in_array('r0.rec_URL', $sort_fields)) {
                        $sort_fields[] = 'r0.rec_URL';
                        $sort_expr[] = 'r0.rec_URL'.$scending;
                    }
                    break;
                case 'm': case 'modified':
                    $fld = ($this->search_domain == BOOKMARK)?'bkm_Modified':'r0.rec_Modified';
                    if(!in_array($fld, $sort_fields)) {
                        $sort_fields[] = $fld;
                        $sort_expr[] = $fld.$scending;
                    }
                    break;
                case 'a': case 'added':
                    $fld = ($this->search_domain == BOOKMARK)?'bkm_Added':'r0.rec_Added';
                    if(!in_array($fld, $sort_fields)) {
                        $sort_fields[] = $fld;
                        $sort_expr[] = $fld.$scending;
                    }
                    break;
                case 't': case 'title':
                    if(!in_array('r0.rec_Title', $sort_fields)) {
                        $sort_fields[] = 'r0.rec_Title';
                        $sort_expr[] = 'r0.rec_Title'.$scending;
                    }
                    break;
                case 'id': case 'ids':
                    if(!in_array('r0.rec_ID', $sort_fields)) {
                        $sort_fields[] = 'r0.rec_ID';
                        $sort_expr[] = 'r0.rec_ID'.$scending;
                    }
                    break;
                case 'rt': case 'type':
                    if(!in_array('r0.rec_RecTypeID', $sort_fields)) {
                        $sort_fields[] = 'r0.rec_RecTypeID';
                        $sort_expr[] = 'r0.rec_RecTypeID'.$scending;
                    }
                    break;

                case 'hie': //special case for Hamburg EARLY ISLAMIC EMPIRE

                    if(!in_array('hie', $sort_fields)) {
                        $sort_expr[] =
//OLD    '(select GREATEST(getTemporalDateString(ifnull(sd2.dtl_Value,\'0\')), getTemporalDateString(ifnull(sd3.dtl_Value,\'0\')))'
    '(select GREATEST(getEstDate(sd2.dtl_Value,0), getEstDate(sd3.dtl_Value,0))'
                        .' from recDetails sd1'
                        .' left join recDetails sd2 on sd1.dtl_Value=sd2.dtl_RecID and sd2.dtl_DetailTypeID=9'
                        .' left join recDetails sd3 on sd1.dtl_Value=sd3.dtl_RecID and sd3.dtl_DetailTypeID=10'
                        .' where r0.rec_ID=sd1.dtl_RecID and sd1.dtl_DetailTypeID=293 limit 1) DESC';
                        $sort_fields[] = 'hie';
                    }

                    break;
                case 'f': case 'field':
                    if($dty_ID>0 && !in_array($dty_ID, $sort_fields)) {

                        $field_type = mysql__select_value($mysqli, 'select dty_Type from defDetailTypes WHERE dty_ID = '.$dty_ID);

                        if($field_type!=null){ //field type found

                            $where_exp = ' WHERE dtl_RecID=r'.$this->level.'.rec_ID and dtl_DetailTypeID='.$dty_ID;

                            if($field_type=='enum'){
                                $sortby = 'ifnull((select trm_OrderInBranch from recDetails left join defTerms on trm_ID=dtl_Value '
                                    . $where_exp
                                    .' ORDER BY trm_Label limit 1), ' // attempt sortby Order in Branch first
                                      . 'ifnull((select trm_Label from recDetails left join defTerms on trm_ID=dtl_Value '
                                        . $where_exp
                                        .' ORDER BY trm_Label limit 1), "~~")) ';// then by term label
                            }else{

                                $fld = $field_type != 'date' ? 'dtl_Value' : 'getEstDate(dtl_Value,0)';//for sort

                                $sortby = 'ifnull((select '.$fld.' from recDetails '
                                        . $where_exp
                                        .' ORDER BY dtl_Value limit 1), "~~") ';
                            }

                            if($field_type=='float'){
                                $sortby = 'CAST('.$sortby.' AS DECIMAL) ';
                            }elseif($field_type=='integer'){
                                $sortby = 'CAST('.$sortby.' AS SIGNED) ';
                            }

                            $sort_expr[] = $sortby . $scending . ', r'.$this->level . '.rec_Title';
                            $sort_fields[] = $dty_ID;
                        }
                    }
                    break;
                default;
            }//switch

        }//foreach

        //
        if(!empty($sort_expr)){
            $this->sort_clause = ' ORDER BY '.implode(',',$sort_expr);
        }

    }

}

/**
*
*
*/
class HLimb {

    public $parent;           // query
    public $limbs = array();// limbs and predicates
    public $conjunction = "all";//and

    //results
    public $tables = array();
    public $where_clause = "";
    public $error_message = null;

    public $allowed = array('all'=>SQL_AND,'any'=>" OR ",'not'=>SQL_NOT);

    //besides  not,any
    //

    public function __construct(&$parent, $conjunction, $query_json) {

        $this->parent = &$parent;
        $this->conjunction = $conjunction;

        if(isEmptyArray($query_json)){
            return;
        }

        foreach ($query_json as $key => $value){

            if(is_numeric($key) && is_array($value)){  //this is sequental array
                $key = array_keys($value);
                $key = $key[0];
                $value = $value[$key];
            }

            $key = strtolower($key);

            if( array_key_exists($key, $this->allowed) ){ //this is limb
                $limb = new HLimb($this->parent, $key, $value);
                if(!isEmptyArray($limb->limbs)){
                    //do not add empty limb
                    array_push( $this->limbs, $limb );
                }
            }else{ //this is predicate

                $predicate = new HPredicate($this->parent, $key, $value, count($this->limbs) );

                if($predicate->valid){
                    array_push( $this->limbs,  $predicate);
                }
            }
        }

    }

    public function addPredicate($key, $value){
        $predicate = new HPredicate($this->parent, $key, $value, count($this->limbs) );

        if($predicate->valid){
            array_push( $this->limbs,  $predicate);
        }
    }

    public function setRelationPrefix($val){
        foreach ($this->limbs as $ind=>$limb){
            $res = $limb->setRelationPrefix($val);
        }
    }
    //
    // fills $tables and $where_clause
    //
    public function makeSQL(){
        global $rty_id_relation;

        $cnj = $this->allowed[$this->conjunction];
        $where = "";
        $from = "";

        if($this->conjunction=="not"){

            $res = $this->limbs[0]->makeSQL();
            if($res){
                $where = $cnj."(".$res["where"].")";
                $this->addTable(@$res["from"]);
            }else{
                $this->error_message = $this->limbs[0]->error_message;
                return null;
            }


        }else{

            $wheres = array();
            if(is_array($this->limbs)){
                $is_relationship = false;
                foreach ($this->limbs as $ind=>$limb){
                    if($limb->pred_type=='t' && $limb->value == $rty_id_relation){
                        $is_relationship = true;
                        break;
                    }
                }
                $cnt = count($this->limbs)-1;
                foreach ($this->limbs as $ind=>$limb){
                    $limb->is_relationship = $is_relationship;
                    $res = $limb->makeSQL();

                    if($res && @$res["where"]){
                        $this->addTable(@$res["from"]);
                        if(false && $cnt==1){
                            $where = $res["where"];
                        }else{
                            array_push($wheres, "(".$res["where"].")");
                        }
                    }elseif($limb->error_message){
                        $this->error_message = $limb->error_message;
                        return null;
                    }
                }
            }

            //IMPORTANT!!!!!!!!
            if(!isEmptyArray($wheres)){  //@TODO!  this is temporal solution!!!!!
//if cnj is OR (any) need to execute each OR section separately - otherwise it kills server (at least for old mySQL versions (5.7))
                $where = implode($cnj, $wheres);
            }
        }


        $this->where_clause = $where;
        $res = array("from"=>$this->tables, "where"=>$where);

        return $res;
    }

    public function addTable($table){
        if($table){
            if(is_array($table)){
                $this->tables = array_merge($this->tables, $table);
                $this->tables = array_unique($this->tables);
            }elseif(!in_array($table, $this->tables)){
                array_push($this->tables, $table);
            }
        }
    }

}

// ===========================
//
//
class HPredicate {

    public $pred_type;
    public $field_id = null; //dty_ID
    public $field_type = null;
    public $field_term = null; //term field: array('term', 'label', 'concept', 'conceptid', 'desc', 'code') // trm_XXX fields

    public $value;
    public $valid = false;
    public $query = null;

    //for related_to, related_from
    public $is_relationship = false;

    public $relation_types = null;
    public $relation_fields = null; // field in relationshio record: array(field_id=>value)
    public $relation_prefix = '';//prefix for recLinks

    public $field_list = false; //list of id values used in predicate IN (val1, val2, val3... )

    public $error_message = null;

    public $qlevel;
    public $index_of_predicate;
    //@todo - remove?
    public $negate = false;
    public $exact = false;
    public $fulltext = false;
    public $case_sensitive = false;
    public $lessthan = false;
    public $greaterthan = false;

    public $allowed = array('t','type','typeid','typename',
            'ids','id','title','added','modified','url','notes',
            'after','before',
            'addedby','owner','access',
            'user','usr',
            'f','field',
            'count','cnt','fc','geo', 'file',
            'lt','linked_to','linkedto','lf','linkedfrom',
            'related','rt','related_to','relatedto','rf','relatedfrom',
            'links','plain',
            'tag','keyword','kwd');

    //trm_OriginatingDBID trm_IDInOriginatingDB
    public $allowed_term_fields = array('term'=>'trm_Label', 'label'=>'trm_Label',
        'concept'=>'trm_ConceptId', 'conceptid'=>'trm_ConceptId', 'desc'=>'trm_Description', 'code'=>'trm_Code');

    /*
    notes, n:        record heder notes (scratchpad)
    title:           title contains
    url              record header url (rec_URL)
    added:           creation date
    date, modified:  edition date
    after, since:
    before:
    plain            query in old plain text format

    addedby:         added by specified user (rec_AddedByUGrpID)
    owner:  - owned by  (rec_OwnerUGrpID)
    access:  visibility outiside owner group (rec_NonOwnerVisibility)

    user, usr:   user id  - bookmarked by user
    tag, keyword, kwd:   tag

    id, ids:  id
    t, type:  record type id
    f, field:   field id
    */

    public function __construct(&$parent, $key, $value, $index_of_predicate)
    {
        global $dty_id_relation_type;

        $this->parent = &$parent;
        $this->qlevel = $this->parent->level; //
        $this->index_of_predicate = $index_of_predicate;

        $key = explode(":", strtolower($key));
        $this->pred_type  = $key[0];
        $ll = count($key);
        if($ll>1){ //get field ids "f:5" -> 5
            if($this->pred_type=='f'){
                $this->field_id = $key[1];
                if($ll>2){ //get subfield for terms "f:5:desc"
                        $val1 = $key[2];
                        if(@$this->allowed_term_fields[$val1]){
                            $this->field_term = $val1;
                        }
                        /*
                        $val2 = null;
                        if($ll>3){
                            $val2 = $key[3];
                        }
                        if(@$this->allowed_term_fields[$val1]){
                            $this->field_term = $val1;
                            if($val2) {$this->field_lang = $val2;}
                        }elseif(@$this->allowed_term_fields[$val2]){
                            $this->field_term = $val2;
                            $this->field_lang = $val1;
                        }*/
                }
            }else{
                // get field id for predicates like "linkedfrom:10:240"  (10 is rectype)
                $this->field_id = $key[$ll-1];
            }
        }

        if( in_array($this->pred_type, $this->allowed) ){
            $this->value = $value;

            if(!isEmptyArray($value) &&
                !(is_numeric(@$value[0]) || is_string(@$value[0])) )
            { //subqueries
                //special behavior for relation - extract reltypes and record ids
                $p_type = strtolower($this->pred_type);
                if($p_type=='related' ||
                   $p_type=='related_to' || $p_type=='relatedto' || $p_type=='rt' ||
                   $p_type=='related_from' || $p_type=='relatedfrom' || $p_type=='rf')
                {

                    $this->relation_fields = array();
                    $this->value = array();

                    $REL_FLD = 'relf:'; //old predicate for relationship record field

                    // extract all with predicate type "r" - this is either relation type or other fields from relatinship record
                    // "rf:245":[{"t":4},{"r":6421}] - related from organization (4) with relation type 6421
                    foreach($value as $idx=>$val){
                        if($idx==='r'){ //for relation type

                            $this->relation_types = prepareIds($val);

                        }elseif (is_array($val) && array_key_exists('r',$val)) //for relation type
                        {
                            $val = $val['r'];
                            if($val){
                                $this->relation_types = prepareIds($val);
                            }else{
                                $this->relation_types = null; //not empty - any relationtype
                            }


                            //remove from array
                            //array_splice($this->value, $idx, 1);
                            //break;

                        //for fields in relation record
                        }elseif(strpos($idx,'r:')===0 || strpos($idx,$REL_FLD)===0){  //that's for {"r:10":10,,}
                               //fields in relationship record
                               //{"r:10":">2010"}
                               $rel_field = $idx;
                               $rel_field = strpos($rel_field,'r:')===0
                                                    ?str_replace('r:','f:',$rel_field)
                                                    :str_replace($REL_FLD,'f:',$rel_field);
                               $this->relation_fields[$rel_field] = $val;

                        }elseif(is_array($val) &&
                                    (strpos(@array_keys($val)[0],'r:')===0 || strpos(@array_keys($val)[0],$REL_FLD)===0)){  //that's for [{"r:10":10},{}]
                            //{"t":10,"rf:245":[{"t":4},{"r":6421},{"relf:10":">2010"}]}}
                               $rel_field = array_keys($val)[0];
                               if($rel_field=='r:'.$dty_id_relation_type){

                                   if($val[$rel_field]){
                                        $this->relation_types = prepareIds($val[$rel_field]);
                                   }

                               }else{
                                   $rel_field2 = strpos($rel_field,'r:')===0
                                                        ?str_replace('r:','f:',$rel_field)
                                                        :str_replace($REL_FLD,'f:',$rel_field);

                                   $this->relation_fields[$rel_field2] = $val[$rel_field];
                               }
                        }else{
                            $this->value[$idx] = $val;
                        }
                    }
                    $value = $this->value;


                    if(!isEmptyArray($this->relation_fields)){
                        $this->relation_fields = new HLimb($this->parent, 'all', $this->relation_fields);
                    }else{
                        $this->relation_fields = null;
                    }




                    // related to particular records
                    foreach($value as $idx=>$val){
                        if($idx==='ids' || (is_array($val) && @$val['ids']) ){
                            if(is_array($val) && @$val['ids']) {$val = $val['ids'];}
                            $this->value = $val;
                            $value = array();//reset
                            break;
                        }
                    }
                }
                if(!isEmptyArray($value)){

                    $level = $this->parent->level."_".$this->parent->cnt_child_query;
                    $this->parent->cnt_child_query++;

                    $this->query = new HQuery( $level, $value );
                }
            }
            $this->valid = true; //@todo
        }

    }

    //
    // not used
    //
    private function getTopLevelQuery(){
        if($this->parent->level==0){
            return $this->parent;
        }else{
            return $this->parent->getTopLevelQuery();
        }
    }

    public function setRelationPrefix($val){
        $this->relation_prefix = $val;
    }

    public function makeSQL(){

        global $mysqli, $top_query;

        switch (strtolower($this->pred_type)) {
            case 'plain':            //query in old plain text format
                //[{"t":"12"},{"plain":"0"},{"sortby":"t"}]


                $query = parse_query($top_query->search_domain, urldecode($this->value), null, null, $top_query->currUserID);

                $tab = 'r'.$this->qlevel;

                $where_clause = $query->where_clause;
                $where_clause = str_replace('TOPBIBLIO',$tab,$where_clause);//need to replace for particular level
                $where_clause = str_replace('TOPBKMK','b',$where_clause);


                return array("where"=>$where_clause);
            case 'typename':
            case 'typeid':
            case 'type':
            case 't':

                if(strpos($this->value, '-')===0){
                    $this->negate = true;
                    $this->value = substr($this->value, 1);
                }

                $eq = ($this->negate)? '!=' : '=';
                if (is_numeric($this->value)) {
                    if(intval($this->value)==0){
                        return null;
                    }else{
                        $res = "$eq ".intval($this->value);
                    }

                }
                elseif (preg_match('/^\d+(?:,\d*)+$/', $this->value)) {
                    // comma-separated list of defRecTypes ids
                    $in = ($this->negate)? 'not in' : 'in';
                    $res = "$in (" . $this->value . ")";
                }
                elseif (preg_match('/^\d+-\d+$/', $this->value)) {
                    $local_rtyid = ConceptCode::getRecTypeLocalID($this->value);
                    if($local_rtyid>0){
                        $res = "$eq $local_rtyid";
                    }else{
                        $res = "=0";//localcode not found
                    }
                }
                else
                {
                    $res = "$eq (select rft.rty_ID from defRecTypes rft where rft.rty_Name = '".$mysqli->real_escape_string($this->value)."' limit 1)";
                }
                $res = "r".$this->qlevel.".rec_RecTypeID ".$res;
                return array("where"=>$res);

            case 'geo':

                return $this->predicateSpatial();

            case 'file':

                $this->pred_type = 'field';
                $this->field_type = 'file';
                return $this->predicateField();

            case 'count':
            case 'cnt':
            case 'fc':

                if(!$this->field_id) {return null;}
                $this->pred_type = strtolower($this->pred_type);

            case 'field':
            case 'f':

                if(!$this->field_id){ //field type not defined
                    return $this->predicateAnyField();
                }else{
                    return $this->predicateField();
                }

            case 'after':
            case 'before':
            case 'modified':
            case 'added':
            case 'title':
            case 'url':
            case 'notes':
            case 'addedby':
            case 'owner':
            case 'access':

                $this->pred_type = strtolower($this->pred_type);
                return $this->predicateField();

            case 'ids':
            case 'id':

                $res = $this->predicateRecIds();

                return $res;

            case 'user':
            case 'usr':
                //bookmarked by
                //$this->search_domain = BOOKMARK;
                return $this->predicateBookmarked();

            case 'tag':
            case 'keyword':
            case 'kwd':

                return $this->predicateKeywords();

            case 'lt':
            case 'linked_to':
            case 'linkedto':

                return $this->predicateLinkedTo();

            case 'lf':
            case 'linkedfrom':

                return $this->predicateLinkedFrom();

            case 'related':

                return $this->predicateRelated();

            case 'rt':
            case 'related_to':
            case 'relatedto':

                return $this->predicateRelatedDirect(false);

            case 'rf':
            case 'relatedfrom':

                return $this->predicateRelatedDirect(true);

            case 'links':

                return $this->predicateLinks();

            default;
        }

        return null;
        //return array("from"=>"", "where"=>$this->key."=".$this->value);


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

    //
    //
    //
    private function predicateSpatial(){

        $p = "rd".$this->qlevel.'.';
        $p = '';

        $res = "exists (select dtl_ID from recDetails $p where r{$this->qlevel}.rec_ID={$p}dtl_RecID AND ";

        if($this->isEmptyValue()){
            $res = SQL_NOT.$res.$p.'dtl_Geo IS NOT NULL)';//not defined
        }elseif($this->value==''){
            $res = $res.$p.'dtl_Geo IS NOT NULL)';//any not null value
        }else {
            $res = $res
            .$p.'dtl_Geo is not null AND ST_Contains(ST_GeomFromText(\''.$this->value.'\'), '
            .$p.'dtl_Geo) limit 1)';//MBRContains
        }
        return array("where"=>$res);
    }

    //
    //
    //
    private function predicateField(){

        global $mysqli, $is_admin, $top_query, $wg_ids;

        //not used
        //$p_alias = "rd".$this->qlevel;
        //$p = $p_alias.".";
        $p_alias = '';
        $p = '';

        $several_ids = prepareIds($this->field_id);//getCommaSepIds - returns validated string
        $is_multi_field = !empty($several_ids) && count($several_ids)>1;
        
        if($is_multi_field){
            $field_id_filter = SQL_IN.implode(',',$several_ids).')';
        }else{
            $field_id_filter = '='.$this->field_id;
            $several_ids = null;
        }

        if($this->pred_type=='count' || $this->pred_type=='cnt' || $this->pred_type=='fc'){
            $this->field_type = 'link';//integer without quotes
        }elseif($this->field_type=='file'){

        }elseif(count($several_ids)==1 && intval($this->field_id)>0){
            //find field type - @todo from cache

            $this->field_type = mysql__select_value($mysqli, 'select dty_Type FROM defDetailTypes where dty_ID = '.$this->field_id);

            if($this->field_type=='relmarker'){
                return $this->predicateRelatedDirect(false);
            }
        }else{
            $this->field_type = 'freetext';
        }

        $field_condition = '';

        if(!$is_admin){  //field visibility per user

            $field_condition = ' AND rst_DetailTypeID '.$field_id_filter
                                .' AND rst_RecTypeID = r'.$this->qlevel.'.rec_RecTypeID'
                                .' AND rst_RequirementType != "forbidden"';

            if($top_query->currUserID < 1){
                $field_condition .= ' AND (rst_NonOwnerVisibility = "public" OR rst_NonOwnerVisibility = "pending") AND IFNULL(dtl_HideFromPublic, 0)!=1';
            }else{
                $field_condition .= ' AND (rst_NonOwnerVisibility != "hidden"';

                if(is_array($wg_ids) && !empty($wg_ids)){
                    $field_condition .= ' OR r'.$this->qlevel.'.rec_OwnerUGrpID ';
                    if(count($wg_ids)>1){
                        $field_condition .= 'IN (' . join(',', $wg_ids).')';
                    }else{
                        $field_condition .= '= '.$wg_ids[0];
                    }
                }

                $field_condition .= ')';
            }
        }

        $is_empty = $this->isEmptyValue();//search for empty or not defined value


        //@todo future - here we need to add code that will
        // call $this->getFieldValue() inside the loop if $this->value is array
        // any:[val1,val2,...] or all:[val1,val2,...] or [val1,val2,...] - by default use any
        //

        $val = $this->getFieldValue();
        if(!$val) {return null;}

        $ignoreApostrophe = false;

        $sHeaderField = null;

        if($this->pred_type=='title' || $this->field_id=='title'){

            $sHeaderField = 'rec_Title';
            $ignoreApostrophe = (strpos($val, 'LIKE')==1);

        }elseif($this->pred_type=='added' || $this->field_id=='added'){

            $sHeaderField = "rec_Added";

        }elseif($this->pred_type=='modified' || $this->pred_type=='after' || $this->pred_type=='before' || $this->field_id=='modified'){

            $sHeaderField = "rec_Modified";

        }elseif($this->pred_type=='url' || $this->field_id=='url'){

            $sHeaderField = "rec_URL";

        }elseif($this->pred_type=='notes' || $this->field_id=='notes'){

            $sHeaderField = 'rec_ScratchPad';
            $ignoreApostrophe = (strpos($val, 'LIKE')==1);

        }elseif($this->pred_type=='addedby' || $this->field_id=='addedby'){

            $sHeaderField = 'rec_AddedByUGrpID';

        }elseif($this->pred_type=='owner' || $this->field_id=='owner'){

            $sHeaderField = 'rec_OwnerUGrpID';

        }elseif($this->pred_type=='access' || $this->field_id=='access'){

            $sHeaderField = "rec_NonOwnerVisibility";

        }elseif($this->pred_type=='rectype' || $this->field_id=='rectype' ||
                 $this->pred_type=='typeid' || $this->field_id=='typeid'){

            $sHeaderField = "rec_RecTypeID";

        }elseif($this->pred_type=='ids' || $this->field_id=='ids'){

            $sHeaderField = "rec_ID";

        }


        if($sHeaderField){

            if($this->pred_type=='f') {$this->pred_type = $this->field_id;}

            if($is_empty){
                $res = "(r".$this->qlevel.".$sHeaderField is NULL OR r".$this->qlevel.".$sHeaderField='')";
            }else{
                $ignoreApostrophe = $ignoreApostrophe && (strpos($val,"'")===false);

                if($this->fulltext){

                    if($sHeaderField=='rec_Title'){
                        //execute fulltext search query
                        $res = '('. ($this->negate ? SQL_NOT : '') .'MATCH(r'.$this->qlevel.'.'.$sHeaderField.') '.$val.')';
                    }else{
                        $this->error_message = 'Full text search is allowed for rec_Title only';
                        return null;
                    }
                }elseif($ignoreApostrophe){
                    $res = "( replace( r".$this->qlevel.".$sHeaderField, \"'\", \"\")".$val.")";
                }else{
                    $res = "(r".$this->qlevel.".$sHeaderField ".$val.")";
                }
            }


        }elseif( $is_empty ){ //search for records where field value is not defined

            $res = "NOT exists (select dtl_ID from recDetails $p_alias where r{$this->qlevel}.rec_ID={$p}dtl_RecID AND {$p}dtl_DetailTypeID";

            $res .= $field_id_filter . $field_condition .')';

        }elseif($this->pred_type=='tag' || $this->field_id=='tag'){
            // it is better to use kwd keyword instead of f:tag

            $res = "(r".$this->qlevel.".rec_ID IN ".$val.")";

        }elseif($this->pred_type=='count' || $this->pred_type=='cnt' || $this->pred_type=='fc'){ //search for records where field occurs N times (repeatable values)

            $res = "(select count(dtl_ID) from recDetails $p_alias where r{$this->qlevel}.rec_ID={$p}dtl_RecID AND {$p}dtl_DetailTypeID";

            $res .= $field_id_filter . $field_condition . ')';

            $res = $res.$val; //$val is number of occurences

        }else{

            if($this->field_type=='date' && trim($this->value)!='') { //false && $this->isDateTime()){
                //OUTDATED - REMOVE

            }elseif($this->field_type=='file'){
                $field_name = $p."dtl_UploadedFileID ";//only for search non empty values
            }else{

                $field_name = $p."dtl_Value ";
                $ignoreApostrophe = ((strpos($val, 'LIKE')==1) && (strpos($val,"'")===false));
                if($ignoreApostrophe){
                    $field_name = "replace($field_name,".'"\'", "") ';
                }
            }

            if($this->relation_prefix){ //special case for search related records by values of relationship field
                $recordID = $this->relation_prefix.'.rl_RelationID';
            }else{
                $recordID = 'r'.$this->qlevel.'.rec_ID';
            }


            if($this->field_id>0){ //field id defined (for example "f:25")

                if($this->field_type=='date' && trim($this->value)!=''){

                    $res = "EXISTS (SELECT rdi_DetailID FROM `recDetailsDateIndex` WHERE $recordID=rdi_RecID AND "
                    .'rdi_DetailTypeID';

                    $field_name = '';
                    $this->fulltext = false;
                    //$val = $this->getFieldValue();

                    $res .= $field_id_filter;

                /*}elseif($this->field_type=='enum' && trim($this->value)!=''){  //!$is_multi_field && 
                //experimental    

                    $res = "EXISTS (SELECT rdi_DetailID FROM `recDetailsEnumIndex` WHERE $recordID=rdi_RecID AND "
                    .'rdi_DetailTypeID';

                    $field_name = 'rdi_Value';
                    $this->fulltext = false;
                    //$val = $this->getFieldValue();

                    $res .= $field_id_filter;
                */    
                }elseif($this->fulltext){


                    if($this->relation_prefix){
                            $rty_id_relation = 1;
                            if(defined('RT_RELATION')){
                                $rty_id_relation = RT_RELATION;
                            }

                        $res = 'SELECT rec_ID FROM Records LEFT JOIN recDetails ON dtl_RecID=rec_ID AND dtl_DetailTypeID'.$field_id_filter
                        .' WHERE rec_RecTypeID='.$rty_id_relation;
                    }else{
                        $res = 'SELECT dtl_RecID FROM recDetails WHERE dtl_DetailTypeID';
                        $res .= $field_id_filter;
                    }

                }else{
                    $res = "EXISTS (SELECT dtl_ID FROM recDetails ".$p." WHERE $recordID=".$p."dtl_RecID AND "
                    .$p.'dtl_DetailTypeID';

                    if($this->negate){
                        $val = $this->getFieldValue();//this time it returns without negate
                        $res = SQL_NOT.$res;
                    }
                    $res .= $field_id_filter;
                }

                if($this->fulltext){
                    //execute fulltext search query
                    $res2 = $res.SQL_AND.($this->negate ? SQL_NOT : ' ').'MATCH(dtl_Value) '.$val.$field_condition;
                    $list_ids = mysql__select_list2($mysqli, $res2);

                    $res = predicateId($recordID,$list_ids);

                }else{
                    $res = $res.SQL_AND.$field_name.$val.$field_condition.' LIMIT 1)';
                }
            }else{
                //field id not defined - at the moment used for search via registered file
                $res = 'select dtl_RecID from recDetails where '.$field_name.$val.$field_condition;
                $list_ids = mysql__select_list2($mysqli, $res);

                $res = predicateId($recordID,$list_ids);

            }

        }

        return array("where"=>$res);///"from"=>"recDetails rd",

    }

    //
    //
    //
    private function predicateAnyField(){

        global $mysqli, $is_admin, $top_query, $wg_ids;

        //$p_alias = "rd".$this->qlevel;
        //$p = $p_alias.'.';
        $p_alias = '';
        $p = '';

        $keep_val = $this->value;

        $this->field_type = 'enum';
        $val_enum = $this->getFieldValue();

        $this->field_type = 'freetext';
        $val_wo_prefixes = $this->value;
        $this->value = $keep_val;
        $val = $this->getFieldValue();
        if(!$val) {return null;}

        $field_name1 =  $p.'dtl_Value ';
        $field_name2 =  'link.rec_Title ';

        $ignoreApostrophe = (strpos($val, 'LIKE')==1);
        if($ignoreApostrophe){
            $field_name1 = "replace($field_name1,\"'\",'') ";
            $field_name2 = "replace($field_name2,\"'\",'') ";
        }

        $field_condition = '';

        if(!$is_admin){

            $field_condition = ' AND rst_DetailTypeID = dtl_DetailTypeID'
                                .' AND rst_RecTypeID = r'.$this->qlevel.'.rec_RecTypeID'
                                .' AND rst_RequirementType != "forbidden"';

            if($top_query->currUserID < 1){
                $field_condition .= ' AND (rst_NonOwnerVisibility = "public" OR rst_NonOwnerVisibility = "pending") AND IFNULL(dtl_HideFromPublic, 0)!=1';
            }else{
                $field_condition .= ' AND (rst_NonOwnerVisibility != "hidden"';

                if(is_array($wg_ids) && !empty($wg_ids)){
                    $field_condition .= ' OR '.predicateId('r'.$this->qlevel.'.rec_OwnerUGrpID',$wg_ids);
                }

                $field_condition .= ')';
            }
        }

        if($this->fulltext){

            //execute fulltext search query
            $res = 'select dtl_RecID from recDetails '
            . ' left join defDetailTypes on dtl_DetailTypeID=dty_ID '
            . ' left join Records link on dtl_Value=link.rec_ID '
            . ' left join defRecStructure on dtl_DetailTypeID=rst_DetailTypeID '
            .' where if(dty_Type != "resource", '
                .' if(dty_Type="enum", dtl_Value'.$val_enum
                    .', '. ($this->negate ? SQL_NOT : '')
                    ."MATCH(dtl_Value) $val), $field_name2 LIKE '%{$val_wo_prefixes}%')"
                .$field_condition;

            $list_ids = mysql__select_list2($mysqli, $res);

            $res = predicateId('r'.$this->qlevel.'.rec_ID',$list_ids);

        }else{

            $res = 'exists (select dtl_ID from recDetails '.$p
            . ' left join defDetailTypes on dtl_DetailTypeID=dty_ID '
            . ' left join Records link on '.$p.'dtl_Value=link.rec_ID '
            .' where r'.$this->qlevel.'.rec_ID='.$p.'dtl_RecID '
            .'  and if(dty_Type != "resource", '
                    .' if(dty_Type="enum", '.$p.'dtl_Value'.$val_enum
                       .', '.$field_name1.$val
                       .'), '.$field_name2.$val.')'
            .$field_condition.')';

        }

        return array("where"=>$res);
    }

    //
    //
    //
    private function predicateRecIds(){

        global $top_query, $params_global;
        $not_nested = (@$params_global['nested']===false);

        $this->field_type = "link";
        $p = $this->qlevel;

        if($this->query){
            $this->query->makeSQL();
            if($this->query->where_clause && trim($this->query->where_clause)!=""){
                if($not_nested){
                    $val = ' = r'.$this->query->level.'.rec_ID AND '.$this->query->where_clause;
                    $top_query->top_limb->addTable($this->query->from_clause);
                }else{
                    $val = " IN (SELECT rec_ID FROM ".$this->query->from_clause.SQL_WHERE.$this->query->where_clause.")";
                }
            }else{
                return null;
            }

        }else{
            $val = $this->getFieldValue();

            if(strpos($val,'between')===false){

                if(!$this->field_list){
                    $val = "=0";
                }elseif($p==0){
                    $cs_ids = getCommaSepIds($this->value);
                    if ($cs_ids && strpos($cs_ids, ',')>0) {
                        $top_query->fixed_sortorder = $cs_ids;
                    }
                }
            }
        }

        $where = "r$p.rec_ID".$val;

        return array("where"=>$where);

    }


    //
    //
    //
    private function predicateBookmarked(){

        $where = '';
        $this->field_type = "link";
        $p = $this->qlevel;

        $cs_ids = $this->value;
        if(strpos($cs_ids, '-')===0){
            $this->negate = true;
            $cs_ids = substr($cs_ids, 1);
        }

        $cs_ids = $this->getUserIds($cs_ids);
        //$cs_ids = getCommaSepIds($this->value);
        if ($cs_ids) {

                if(strpos($cs_ids, ',')>0){  //more than one

                    $where = ' IN (SELECT bkm_RecID FROM usrBookmarks where '
                            . 'bkm_UGrpID '.($this->negate?SQL_NOT:'').SQL_IN.$cs_ids.'))';

                }else{
                    $where = ' IN (SELECT bkm_RecID FROM usrBookmarks where bkm_UGrpID '.($this->negate?'!=':'=').$cs_ids.')';
                }
        }
        if($where){
            $where = "r$p.rec_ID ".$where;
            return array("where"=>$where);
        }else{
            return null;
        }

    }

    //
    //
    //
    private function predicateKeywords(){
        
        global $mysqli;

        $this->field_type = "link";
        $this->field_id = 'tag';
        $p = $this->qlevel;

        $where = '';

        if(false && !$this->field_list){
            $where = '=0';
        }else{

            $isAll = false;

            if(is_array($this->value)){
                if(@$this->value['any']!=null){
                    $this->value = $this->value['any'];
                }elseif(@$this->value['all']!=null){
                    $this->value = $this->value['all'];
                    $isAll = true;
                }
            }

            $cs_ids = getCommaSepIds($this->value);
            if ($cs_ids) {  // all values are int - search for tagID

                if(strpos($cs_ids, '-')===0){
                    $this->negate = true;
                    $cs_ids = substr($cs_ids, 1);
                }
                
                $ids = explode(',',$cs_ids);
                
                $pred = (($this->negate)?SQL_NOT:'')
                            . ' IN (SELECT rtl_RecID FROM usrRecTagLinks WHERE '
                            . predicateId('rtl_TagID', $ids);

                $cnt = count($ids);
                if($isAll && $cnt>1){
                    $pred = $pred.' GROUP BY rtl_RecID HAVING count(*)='.$cnt;
                }
                $pred = $pred.')';

//SELECT rtl_RecID as cnt FROM usrrectaglinks where rtl_TagID in (1,3) group by rtl_RecID having count(*)=2);
            }else{
                
                // mixed values - search for tag_Text
                $pred = 'IN (SELECT rtl_RecID FROM usrRecTagLinks, usrTags where rtl_TagID=tag_ID';
                
                $is_empty = false;
                $values = null;
                
                if(is_array($this->value)){
                    $values = $this->value;
                }elseif(is_string($this->value)){
                    $values = explode(',', $this->value);
                }

                if(empty($values)){
                    $is_empty = true;
                }elseif(is_string($values[0])){
                    
                    $value = $values[0];
                    
                    // -NULL - any tags, NULL - without tags
                    if(trim($value)=='' || strtolower($value)=='-null'){ //record with any tag
                        $is_empty = true;  //find any non empty value
                    }elseif(strtolower($value)=='null'){ //records without tags
                        $this->negate = true;
                        $is_empty = true;
                    }
                }
                
                if($is_empty){
                    $pred = (($this->negate)?SQL_NOT:'').$pred.')';
                }else{
                    
                    $tags = array();
                    foreach ($values as $val){

                        if(strpos($val, '-')===0){
                            $this->negate = true;
                            $val = substr($val, 1); 
                        }

                        if($val) {
                            array_push($tags,'tag_Text ="'
                                        . $mysqli->real_escape_string($val).'"');
                        }
                    }

                    $cnt = count($tags);
                    
                    if($cnt==0){
                       $pred = '=0'; 
                    }else{
                    
                        $pred = $pred.' and '.'('.implode(' OR ',$tags).')';
                        
                        if($cnt>1 && $isAll){
                            $pred = $pred.' GROUP BY rtl_RecID HAVING count(*)='.$cnt;
                        }
                        $pred = (($this->negate)?SQL_NOT:'').$pred.')';
                    }
                }
            }
            $where = "r$p.rec_ID ".$pred;
        }

        return array("where"=>$where);

    }

    /*
    linked_to: pointer field type : query     recordtype
    linkedfrom:
    relatedfrom: relation type id (termid)    recordtype
    related_to:
    links: recordtype
    */

    //
    //
    //
    private function getDistinctRecIds()
    {
        global $mysqli, $params_global, $top_query;

        $not_nested = (@$params_global['nested']===false);

        if($not_nested){
            $val = ' = r'.$this->query->level.'.rec_ID AND '.$this->query->where_clause;
            $top_query->top_limb->addTable($this->query->from_clause);
        }else{
            $sub_query = 'SELECT DISTINCT rec_ID FROM '.$this->query->from_clause.SQL_WHERE.$this->query->where_clause;
            $ids = mysql__select_list2($mysqli, $sub_query);
            if(!isEmptyArray($ids)){
                $val = SQL_IN.implode(',',$ids).')';
            }else{
                $val = ' =0';
            }
        }

        return $val;
    }


    /**
    * find records that have pointers to specified records
    */
    private function predicateLinkedTo(){

        $this->field_type = "link";
        $p = $this->qlevel;
        $rl = "rl".$p."x".$this->index_of_predicate;

        if($this->isEmptyValue()){

            if(strpos($this->value, '-')===0){
                $this->negate = true;
                $this->value = substr($this->value, 1);
            }

            $rd = "rd".$this->qlevel;

            if($this->field_id){
                //no pointer field exists among record details
                $where = (($this->negate)?'':SQL_NOT)." EXISTS (select dtl_ID from recDetails $rd where r$p.rec_ID=$rd.dtl_RecID AND "
            ."$rd.dtl_DetailTypeID=".$this->field_id." LIMIT 1)";

            }else{
                //no links at all or any link
                $where = "r$p.rec_ID ".(($this->negate)?'':SQL_NOT)
                    ." IN (select rl_SourceID from recLinks $rl where $rl.rl_RelationID IS NULL)";
            }

            return array("where"=>$where);

        }else{

            $rl = "rl".$p."x".$this->index_of_predicate;

            if($this->query){
                $this->query->makeSQL();

                if($this->query->where_clause && trim($this->query->where_clause)!=""){
                    $val = $this->getDistinctRecIds();
                }else{
                    return null;
                }
            }else{

                $val = $this->getFieldValue();

                if($val=='' && !$this->field_list){
                    $val = "=0";
                    //@todo  findAnyField query
                    //$val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE".$this->query->where_clause.")";
                }
            }

            if($this->field_id==7){ //special case for relationship records
                //relation record linked to specific target or source

                $where = "r$p.rec_ID=$rl.rl_RelationID "
                ." AND $rl.rl_DetailTypeID IS NULL "
                ." AND $rl.rl_SourceID".$val;

            }elseif($this->field_id==5){ //special case for relationship records

                $where = "r$p.rec_ID=$rl.rl_RelationID "
                ." AND $rl.rl_DetailTypeID IS NULL "
                ." AND $rl.rl_TargetID".$val;

            }else{

                $field_compare = "$rl.rl_RelationID IS NULL";

                $several_ids = prepareIds($this->field_id);//getCommaSepIds - returns validated string
                if(is_array($several_ids) && !empty($several_ids)){
                    $field_compare .= SQL_AND.predicateId("$rl.rl_DetailTypeID", $several_ids);
                }

                $where = "r$p.rec_ID=$rl.rl_SourceID AND ".
                $field_compare
                ." AND $rl.rl_TargetID".$val;
            }

            return array("from"=>"recLinks $rl", "where"=>$where);

        }
    }

    /**
    * find records that have pointers to specified records
    */
    private function predicateLinkedFrom(){

        $this->field_type = "link";
        $p = $this->qlevel;
        $rl = "rl".$p."x".$this->index_of_predicate;


        if($this->isEmptyValue()){
            //find records without reverse pointers

            if(strpos($this->value, '-')===0){
                $this->negate = true;
                $this->value = substr($this->value, 1);
            }

            $rd = "rd".$this->qlevel;

            //no pointer field exists among record details
            $field_compare = "$rl.rl_RelationID IS NULL";

            $several_ids = prepareIds($this->field_id);//getCommaSepIds - returns validated string
            if(is_array($several_ids) && !empty($several_ids)){
                $field_compare .= SQL_AND.predicateId("$rl.rl_DetailTypeID", $several_ids);
            }

            $where = "r$p.rec_ID ".(($this->negate)?'':SQL_NOT)
            ." IN (select rl_TargetID from recLinks $rl where $field_compare)";


            return array("where"=>$where);

        }else{


            if($this->query){

                $this->query->makeSQL();

                if($this->query->where_clause && trim($this->query->where_clause)!=""){
                    $val = $this->getDistinctRecIds();
                }else{
                    return null;
                }

            }else{

                $val = $this->getFieldValue();

                if($val=='' && !$this->field_list){
                    $val = "=0";
                    //@todo  findAnyField query
                    //$val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE".$this->query->where_clause.")";
                }
            }

            if($this->field_id==5){ //special case for relationship records
                //find target or source linked from relation record

                $where = "r$p.rec_ID=$rl.rl_TargetID "
                ." AND $rl.rl_DetailTypeID IS NULL "
                ." AND $rl.rl_RelationID".$val;

            }elseif($this->field_id==7){ //special case for relationship records

                $where = "r$p.rec_ID=$rl.rl_SourceID "
                ." AND $rl.rl_DetailTypeID IS NULL "
                ." AND $rl.rl_RelationID".$val;

            }else{

                $field_compare = "$rl.rl_RelationID IS NULL";

                $several_ids = prepareIds($this->field_id);//getCommaSepIds - returns validated string
                if(is_array($several_ids) && !empty($several_ids)){
                    $field_compare .= SQL_AND.predicateId("$rl.rl_DetailTypeID", $several_ids);
                }

                $where = "r$p.rec_ID=$rl.rl_TargetID AND "
                .$field_compare
                //OLD (($this->field_id) ?"$rl.rl_DetailTypeID=".$this->field_id :"$rl.rl_RelationID IS NULL")
                ." AND $rl.rl_SourceID".$val;
            }

            return array("from"=>"recLinks $rl", "where"=>$where);
        }
    }


    /**
    * find records that are source relation for specified records
    *
    * "related:term_id": query for target record
    *
    * $this->field_id - relation type (term id)
    */
    private function predicateRelated(){

        global $mysqli;

        $this->field_type = "link";
        $p = $this->qlevel;
        $rl = "rl".$p."x".$this->index_of_predicate;

        if($this->query){
            $this->query->makeSQL();
            if($this->query->where_clause && trim($this->query->where_clause)!=""){
                $val = $this->getDistinctRecIds();
            }else{
                return null;
            }

        }else{
            $val = $this->getFieldValue();

            if($val=='' && !$this->field_list){
                $val = "!=0";
                //@todo  findAnyField query
                //$val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE".$this->query->where_clause.")";
            }
        }

        $s1 = 'rl_SourceID';
        $s2 = 'rl_TargetID';
        if($this->is_relationship){
            $s1 = $s2 = 'rl_RelationID';
        }

        $where = '';

        $where_reverce_reltypes = '';
        $where_direct_reltypes = '';

        if(!isEmptyArray($this->relation_types)){

            //reverse
            $inverse_reltype_ids = getTermInverseAll($mysqli, $this->relation_types );
            if(!empty($inverse_reltype_ids)){
                $where_reverce_reltypes = predicateId("$rl.rl_RelationTypeID", $inverse_reltype_ids).SQL_AND;
            }

            //direct
            $this->relation_types = array_merge($this->relation_types,
                            getTermChildrenAll($mysqli, $this->relation_types));

            $where_direct_reltypes = predicateId("$rl.rl_RelationTypeID", $this->relation_types).SQL_AND;

            if($where_reverce_reltypes==''){
                $where = $where_direct_reltypes;
                $where_direct_reltypes = '';
            }



        }else{

            list($reltypes, $rty_constraints) = $this->_getRelationFieldConstraints();

            if($reltypes!=null && !empty($reltypes)>0){
                $reltypes = predicateId("$rl.rl_RelationTypeID", $reltypes);
            }else{
                $reltypes = "$rl.rl_RelationTypeID IS NOT NULL";
            }
            $where = $where . $reltypes . SQL_AND;
        }

        if($this->relation_fields!=null){
                $this->relation_fields->setRelationPrefix($rl);
                $w2 = $this->relation_fields->makeSQL();
                if($w2 && trim($w2['where'])!=''){
                    $where = $where.$w2['where'].SQL_AND;
                }
           }

        $where = $where
        //(($this->field_id && false) ?"$rl.rl_RelationTypeID=".$this->field_id :"$rl.rl_RelationID is not null")
         ." (($where_direct_reltypes r$p.rec_ID=$rl.$s1 AND  $rl.rl_TargetID".$val                   //direct
            .") OR ($where_reverce_reltypes r$p.rec_ID=$rl.$s2 AND  $rl.rl_SourceID".$val.'))';//reverse

        return array("from"=>"recLinks $rl", "where"=>$where);
    }

    /**
    * Finds relation type for current reltype field
    *
    */
    private function _getRelationFieldConstraints(){

        global $mysqli;
        if($this->field_id>0){
            list($vocab_id, $rty_constraints) = mysql__select_row($mysqli,
                    'SELECT dty_JsonTermIDTree, dty_PtrTargetRectypeIDs '
                    .'FROM defDetailTypes WHERE dty_ID='.$this->field_id);
            $reltypes = getTermChildrenAll($mysqli, $vocab_id);
            $rty_constraints = explode(',',$rty_constraints);

            return array($reltypes, $rty_constraints);
        }else{
            return array(null, null);
        }
    }


    /**
    * find records that are source relation for specified records
    *
    * "related_to:field_id": query for target record
    *
    * $this->field_id - relmarker field - it is used to get constraintes (dty_JsonTermIDTree, dty_PtrTargetRectypeIDs)
    * It takes relation type (term id) from value
    * for example "rf":[{"t":4},{"r":"6590"}]
    *
    * "rf":245 - related from record id 245
    * {"rf:245":[{"t":4},{"r":6421}]}   related from organization(t:4) with reltype id 6421
    */
    private function predicateRelatedDirect($is_reverse){

        global $mysqli;

        if($is_reverse){
            $part1 = 'rl_TargetID';
            $part2 = 'rl_SourceID';
        }else{
            $part1 = 'rl_SourceID';
            $part2 = 'rl_TargetID';
        }


        $this->field_type = "link";
        $p = $this->qlevel;
        $rl = "rl".$p."x".$this->index_of_predicate;


       //{"t":10,"rf:245":[{"t":4},{"r":6421},{"relf:10":">2010"}]}}
       //{"t":10,"rf:6421}

       if($this->isEmptyValue()){

            $rd = "rd".$this->qlevel;

            if($this->field_id){//relmarker field id
                //find rt constraints and all terms
                /*
                list($vocab_id, $rty_constraints) = mysql__select_row($mysqli,
                        'SELECT dty_JsonTermIDTree, dty_PtrTargetRectypeIDs '
                        .'FROM defDetailTypes WHERE dty_ID='.$this->field_id);
                $reltypes = getTermChildrenAll($mysqli, $vocab_id);
                $rty_constraints = explode(',',$rty_constraints);
                */
                list($reltypes, $rty_constraints) = $this->_getRelationFieldConstraints();

                if($rty_constraints!=null && !empty($rty_constraints)){

                    $rty_constraints = predicateId('rec_RecTypeID', $rty_constraints);

                    $rty_constraints = ', Records where '.$rty_constraints.SQL_AND;
                }else{
                    $rty_constraints = SQL_WHERE;
                }

                if($reltypes!=null && !empty($reltypes)){

                    $reltypes = predicateId('rl_RelationTypeID', $reltypes);

                }else{
                    $reltypes = 'rl_RelationTypeID IS NOT NULL';
                }

                $where = "r$p.rec_ID ".(($this->negate)?'':SQL_NOT)
                    ." IN (select $part1 from recLinks"
                                .$rty_constraints
                                .$reltypes.')';

            }else{
                //relmarker field not defined
                //no relation at all or any relation with specified reltypes (term) and record types
                $where = "r$p.rec_ID ".(($this->negate)?'':SQL_NOT)
                        ." IN (select $part1 from recLinks where rl_RelationID IS NOT NULL)";
            }

            return array("where"=>$where);

       }else{

            if($this->query){
                $this->query->makeSQL();
                if($this->query->where_clause && trim($this->query->where_clause)!=""){
                    $val = $this->getDistinctRecIds();
                }else{
                    return null;
                }

            }else{
                //related to/from list of record ids
                $val = $this->getFieldValue();

                if($val=='' && !$this->field_list){
                    $val = "!=0";
                    //@todo  findAnyField query
                    //$val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE".$this->query->where_clause.")";
                }
            }

            //compose where with recLinks ($rl) fields
            $where = "r$p.rec_ID=$rl.$part1 ";
            if($val){
                $where = $where . "AND $rl.$part2".$val;
            }

            if(is_array($this->relation_types)&& !empty($this->relation_types)){

                $this->relation_types = array_merge($this->relation_types,
                                getTermChildrenAll($mysqli, $this->relation_types));

                $where = $where . SQL_AND. predicateId("$rl.rl_RelationTypeID", $this->relation_types);

            }else{
                $where = $where . " AND $rl.rl_RelationID is not null";
            }

            if($this->relation_fields!=null){
                $this->relation_fields->setRelationPrefix($rl);
                $w2 = $this->relation_fields->makeSQL();
                if($w2 && trim($w2['where'])!=''){
                    $where = $where . SQL_AND . $w2['where'];
                }
            }
            return array("from"=>"recLinks $rl", "where"=>$where);
        }
    }


    /**
    * find records that any links (both pointers and relations) to specified records
    */
    private function predicateLinks(){

        $this->field_type = "link";
        $p = $this->qlevel;
        $rl = "rl".$p."x".$this->index_of_predicate;

        if($this->query){
            $this->query->makeSQL();
            if($this->query->where_clause && trim($this->query->where_clause)!=""){
                $val = $this->getDistinctRecIds();
            }else{
                return null;
            }

        }else{
            $val = $this->getFieldValue();

            if($val=='' && !$this->field_list){
                $val = "=0";
                //@todo  findAnyField query
                //$val = " IN (SELECT rec_ID FROM ".$this->query->from_clause." WHERE".$this->query->where_clause.")";
            }
        }

        //($rl.rl_RelationID is not null) AND
        $where = "((r$p.rec_ID=$rl.rl_SourceID AND $rl.rl_TargetID".$val.") OR (r$p.rec_ID=$rl.rl_TargetID AND $rl.rl_SourceID".$val."))";


        return array("from"=>"recLinks $rl", "where"=>$where);
    }

    /// not used
    private function isDateTime() {

        $timestamp0 = null;
        $timestamp1 = null;

        if (strpos($this->value,"<>")>0) {
            $vals = explode("<>", $this->value);

            if(is_numeric($vals[0]) || is_numeric($vals[1])) {return false;}

             try{
                $timestamp0 = new DateTime($vals[0]);
                $timestamp1 = new DateTime($vals[1]);
             } catch (Exception  $e){
             }
        }else{

            if(is_numeric($this->value)) {return false;}

             try{
                $timestamp0 = new DateTime($this->value);
                $timestamp1 = 1;
             } catch (Exception  $e){
             }
        }
        return $timestamp0 && $timestamp1;
    }

    //
    //
    //
    private function makeDateClause_ForHeaderField() {

        if (strpos($this->value,"<>") || strpos($this->value,"/")) {

            if(strpos($this->value,"<>")){
                $vals = explode("<>", $this->value);
            }else{
                $vals = explode("/", $this->value);
            }

            $datestamp0 = Temporal::dateToISO($vals[0]);
            $datestamp1 = Temporal::dateToISO($vals[1]);

            $ret = ($this->negate?SQL_NOT:'').SQL_BETWEEN." '$datestamp0'".SQL_AND."'$datestamp1'";
            return $ret;

        }elseif($this->isEmptyValue()){ // {"f:10":"NULL"}
            return SQL_NULL;
        }else{

            $datestamp = Temporal::dateToISO($this->value);

            if($datestamp==null){
                return null;
            }elseif ($this->exact) {
                $ret = ($this->negate?'!':'')."= '$datestamp'";
                return $ret;
            }
            elseif($this->lessthan) {
                return $this->lessthan." '$datestamp'";
            }
            elseif($this->greaterthan) {
                return $this->greaterthan." '$datestamp'";
            }
            else {
                //return "LIKE '$datestamp%'";

                //old way
                // it's a ":" ("like") query - try to figure out if the user means a whole year or month or default to a day
                /*$match = preg_match('/^[0-9]{4}$/', $this->value, $matches);
                if (@$matches[0]) {
                    $date = $matches[0];
                }
                else */
                if (preg_match('!^\d{4}[-/]\d{2}$!', $this->value)) {
                    $date = $this->value; //date('Y-m', $datestamp);
                }
                else {
                    $date = $datestamp; //date('Y-m-d', $datestamp);
                }
                $ret = ($this->negate?'not ':'')."LIKE '$date%'";
                return $ret;
            }
        }
    }

    /*
        It is possible to specify the following queries against dates:
        FOR OVERLAPS: {"f:1113":"1400/1500"}   or  {"f:1113":"1400<>1500"}  or {"f:1113":"<>    1400,1500"} the search range overlaps the specified interval (1400-1500)

        BETWEEN: {"f:1113":"1400><1500"}  or {"f:1113":"><1400/1500"}  the search range is between the specified interval

        if operator <> or >< is in the beginning of search string, interval can be specified as "1440 to 1500",  "1440/1500",  "1440,1500", "1440-1500", "1440 à 1500",  "1400/P100Y" or "P100Y/1500"

        FALL IN:{"f:1113":"1450"}  1450 should fall between range in database    1400<=1450<=1500
        AFTER:  {"f:1113":">=1300"} 1400>=1300  (start of range is after the specified date)
        BEFORE: {"f:1113":"<=1600"} 1500<=1600  (end of range is before the specified date)
        EXACT:  {"f:1113":"=1400"}  either start or end of range in db equals the specified date 
                {"f:1113":"=1400,1500"}  start of range in db is 1400 and end of range in db equals to 1500
        FALL IN/OVERLAP is default comparison.
    */
    private function makeDateClause() {

        if($this->isEmptyValue()){ // {"f:10":"NULL"}
            return SQL_NULL;
        }


        $is_overlap = strpos($this->value,'<>')!==false; // falls in/overlaps  - DEFAULT
        $is_within = strpos($this->value,'><')!==false;  // between

        if ($is_overlap || $is_within) {

            if(strpos($this->value,'><')===0 || strpos($this->value,'<>')===0){
                // <>500/P10Y  or ><1400-1550
                $temporal = new Temporal($this->value);
                if(!$temporal->isValid()){
                    return null;
                }
                $timespan = $temporal->getMinMax();
            }else{
                $vals = explode($is_within?'><':'<>', $this->value);
                //  200<>500
                $temporal1 = new Temporal($vals[0]);
                $temporal2 = new Temporal($vals[1]);

                if(!$temporal1->isValid() || !$temporal2->isValid()){
                    return null;
                }
                $timespan = array($temporal1->getMinMax()[0], $temporal2->getMinMax()[1]);
            }

        }else{
            $temporal = new Temporal($this->value, true);
            if(!$temporal->isValid()){
                return null;
            }
            $timespan = $temporal->getMinMax();
        }

        $res = '';

        if($is_within){  // min<=t1 && t2<=max

            //search dates are comletely within the specified interval
            $res = "({$timespan[0]} <= rdi_estMinDate  AND rdi_estMaxDate <= {$timespan[1]})";

        }elseif ($this->exact) {
            //either begin or end of date range is exact to specified interval
            $res = "(rdi_estMinDate = {$timespan[0]} OR rdi_estMaxDate = {$timespan[1]})";
        }
        elseif($this->lessthan) {  //search dates before the specified

            //timespan rdi_estMaxDate < max
            $res = "(rdi_estMaxDate {$this->lessthan} {$timespan[1]})";
        }
        elseif($this->greaterthan) { //search dates after the specified

            //timespan min > rdi_estMaxDate
            $res = "(rdi_estMinDate {$this->greaterthan} {$timespan[0]})";
        }
        else { // max>=t1 && min<=t2

            //search range overlaps/intersects within interval
            $res = "(rdi_estMaxDate>={$timespan[0]} AND rdi_estMinDate<={$timespan[1]})";

        }

        if($this->negate){
            $res = SQL_NOT.$res;
        }

        return $res;
    }


    /*
      is search for empty or null value
    */
    private function isEmptyValue(){
                                            //$this->value=='' ||
        return !is_array($this->value) && ( strtolower($this->value)=='null' || strtolower($this->value)=='-null');// {"f:18":"NULL"}
    }


    /**
    * Search user ids - prepare list of user ids
    *
    * @param mixed $value
    */
    private function getUserIds($value){
        global $mysqli, $currUserID;

        $values = explode(',',$value);
        $userids = array();
        foreach($values as $username){
            if(is_numeric($username)){
                $userids[] = $username;    //ugr_ID
            }elseif(strtolower($username)=='currentuser' || strtolower($username)=='current_user'){
                $userids[] = $currUserID;
            }else{
                 $username = mysql__select_value($mysqli, 'select ugr_ID from sysUGrps where ugr_Name = "'
                        .$mysqli->real_escape_string($username).'" limit 1');
                 if($username!=null) {$userids[] = $username;}
            }
        }
        return implode(',',$userids);
    }


    /**
    * Returns value with compare operator
    */
    private function getFieldValue(){

        global $mysqli, $params_global, $currUserID;

        $this->case_sensitive = false;

        if(is_array($this->value)){
            if(!empty($this->value)){
                $cs_ids = getCommaSepIds($this->value);
                if($cs_ids!=null){
                    $this->value = implode(',',$this->value);
                }
            }else{
                return '';
            }
        }

        // -   negate value
        // <> between  >< overlaps

        // then it analize for
        // == case sensetive
        // and at last
        // = - exact or LIKE (check for % at begin and end)
        // @    fulltext
        // > <  for numeric and dates only

        $this->negate = false;
        $this->exact = false;
        $this->fulltext = false;
        $this->lessthan = false;
        $this->greaterthan = false;

        //
        if(strpos($this->value, '-')===0 && !($this->field_type=='date' || $this->field_type=='integer' || $this->field_type=='float')){
            $this->negate = true;
            $this->value = substr($this->value, 1);
        }

        if($this->field_type=='freetext' || $this->field_type=='blocktext'){
            //avoid case when text contains html tag, ie: "<b>John</b><i>Peterson</i>"
            $stripped_value = strip_tags($this->value);
        }else{
            $stripped_value = $this->value;
        }


        if (strpos($stripped_value,'<>')===false && strpos($stripped_value,'><')===false) { //except overlaps and between operators

            if(strpos($this->value, '==')===0){
                $this->case_sensitive = true;
                $this->value = substr($this->value, 2);
            }

            if(strpos($this->value, '=')===0){
                $this->exact = true;
                $this->value = substr($this->value, 1);
            }elseif(strpos($this->value, '@')===0){ //full text search
                $this->fulltext = true; //for detail type "file" - search by obfuscation id
                $this->value = substr($this->value, 1);
            }elseif(strpos($this->value, '<=')===0){
                $this->lessthan = '<=';
                $this->value = substr($this->value, 2);
            }elseif(strpos($stripped_value, '<')===0){
                $this->lessthan = '<';
                $this->value = substr($this->value, 1);
            }elseif(strpos($this->value, '>=')===0){
                $this->greaterthan = '>=';
                $this->value = substr($this->value, 2);
            }elseif(strpos($this->value, '>')===0){
                $this->greaterthan = '>';
                $this->value = substr($this->value, 1);
            }

        }
        $this->value = $this->cleanQuotedValue($this->value);

        if(is_string($this->value) && trim($this->value)=='') {return "!=''";}//find any non empty value

        $eq = ($this->negate)? '!=' : (($this->lessthan) ? $this->lessthan : (($this->greaterthan) ? $this->greaterthan : '='));

        if($this->field_type=='enum' || $this->field_type=='relationtype'){

            $parent_ids = null;

            if (preg_match('/^\d+(?:,\d*)+$/', $this->value) || intval($this->value)){   //numeric comma separated
                $parent_ids = prepareIds($this->value);
            }


            /*
            if (preg_match('/^\d+(?:,\d*)+$/', $this->value)){   //numeric comma separated
                $res = ' in (select trm_ID from defTerms where trm_ID in ('
                    .$this->value.') or trm_ParentTermID in ('.$this->value.'))';
            }elseif(preg_match('/^\d+$/', trim($this->value)) && intval($this->value)){   //integer

                $parent_ids = prepareIds($this->value);

                $int_v = intval($this->value);
                $res = ' in (select trm_ID from defTerms where trm_ID='
                    .$int_v;
                if(!$this->exact){
                    $res = $res . ' or trm_ParentTermID='.$int_v;
                }
                $res = $res.')';
            }
            */

            //search for trm_ID
            if(!isEmptyArray($parent_ids) && $this->field_term==null){

                $all_terms = null;
                if(!$this->exact){
                    $all_terms = getTermChildrenAll($mysqli, $parent_ids);
                }
                if(!isEmptyArray($all_terms)){
                    $all_terms = array_merge($parent_ids, $all_terms);
                }else{
                    $all_terms = $parent_ids;
                }

                if(count($all_terms)==1){
                    $res = ($this->negate?'<>':'=').$all_terms[0];
                }else{
                    $res = ($this->negate?SQL_NOT:'').SQL_IN.implode(',',$all_terms).')';
                }


            }else{
            //search for trm_Label or trm_Code
                $res  = 'select trm_ID from defTerms where ';

                if($this->field_term!=null){
                    //'term', 'label', 'concept', 'conceptid', 'desc', 'code'
                    $trm_Field = $this->allowed_term_fields[$this->field_term];
                }else{
                    $trm_Field = 'trm_Label';
                    $this->value = '*:' . $this->value; // search translated labels as well
                }

                $value = $mysqli->real_escape_string($this->value);
                $ids = null;
                $need_search_in_defTerms = true;

                if($trm_Field == 'trm_ConceptId'){
                    $res = $res.' "'.$value
                        .'" = CONCAT(trm_OriginatingDBID,"-",trm_IDInOriginatingDB)';
                }else{
                    //check language prefix in $value
                    list($lang, $value) = extractLangPrefix($value);

                    if($lang!=null && $trm_Field=='trm_Label')
                    {
                        $need_search_in_defTerms = (strcasecmp($lang,'ALL')==0);

                        //search in translation table first
                        $query_tran = 'SELECT trn_Code FROM defTranslations WHERE '
                                  .'trn_Source="'.$trm_Field.'" AND ';

                        if(!$need_search_in_defTerms){
                            $query_tran = $query_tran.'trn_LanguageCode="'.$lang.'" AND ';
                        }

                        $query_tran = $query_tran.'trn_Translation';

                        if($this->exact){
                            $query_tran  =  $query_tran.' ="'.$value.'"';
                        } else {
                            $query_tran  =  $query_tran.' LIKE "%'.$value.'%"';
                        }

                        $ids = mysql__select_list2($mysqli, $query_tran);

                    }

                    $res = $res.$trm_Field;
                    if($this->exact){
                        $res  =  $res.' ="'.$value.'"';
                    } else {
                        $res  =  $res.' LIKE "%'.$value.'%"';
                    }
                    if($this->field_term==null){
                        $res  =  $res.' or trm_Code="'.$value.'"';
                    }

                }

                //find trm_IDs
                if($ids===null || $need_search_in_defTerms){
                    $ids2 = mysql__select_list2($mysqli, $res);
                    $ids = ($ids==null)?$ids2:array_unique(array_merge($ids2, $ids));
                }
                if(empty($ids)){
                    $res = ($this->negate?'>0':'=0');
                }elseif(count($ids)==1){
                    $res = ($this->negate?'<>':'=').$ids[0];
                }else{
                    $res = ($this->negate?SQL_NOT:'').SQL_IN.implode(',',$ids).')';
                }


            }
            //if put negate here is will accept any multivalue enum field
            //see negate for enum on level above $res = (($this->negate)?' not':'').$res;
        }elseif($this->field_id=='tag'){
        // it is better to use kwd keyword instead of f:tag

            if(preg_match('/^\d+$/', trim($this->value)) && intval($this->value)>0){
                $res = '(SELECT rtl_RecID FROM usrRecTagLinks where rtl_TagID='.$this->value.')';
            }else{
                $res = '(SELECT rtl_RecID FROM usrRecTagLinks, usrTags where rtl_TagID=tag_ID AND tag_Text="'
                        .$mysqli->real_escape_string($this->value).'")';
            }

        }elseif ($this->field_type=='file'){

            $value = $mysqli->real_escape_string($this->value);

            //
            if($this->fulltext){ //search obfuscation id

                $this->fulltext = false;
                $res = "ulf_ObfuscatedFileID = '".$this->value."'";

            }elseif(strpos($value,'^')===0){ //search file size

                $this->value = substr($this->value, 1);
                $res = "ulf_FileSizeKB $eq ".intval($this->value);

            }else {
                if($this->exact){
                    $res  =  $res.'="'.$value.'"';
                } else {
                    $res  =  $res.'LIKE "%'.$value.'%"';
                }
                //path, filename, description and remote URL
                $res = "(ulf_OrigFileName $res) OR "
                       ."(ulf_ExternalFileReference $res)  OR (ulf_Description $res)";
            }
            $res = ' in (select ulf_ID from recUploadedFiles where '.$res.')';

        }
        elseif (($this->field_type=='float' || $this->field_type=='integer' || $this->field_type=='link') && (is_numeric($this->value) || strpos($this->value, "<>") !== false)) {

            if (strpos($this->value,"<>")>0) {
                $vals = explode("<>", $this->value);
                $between = (($this->negate)?" not":"").SQL_BETWEEN;
                if(is_numeric($vals[0]) && is_numeric($vals[1])){
                    $res = $between.$vals[0].SQL_AND.$vals[1];
                }
            }elseif($this->field_type=="link"){
                $res = " $eq ".intval($this->value);//no quotes
            }else{
                $res = " $eq ".($this->field_type=='float'?floatval($this->value):intval($this->value));
            }
            $this->field_list = true;
        }
        else {

            $isHeaderDate = false;

            if($this->pred_type=='addedby' || $this->pred_type=='owner')
            {


                //if value is not numeric (user id), find user id by user login name
                $this->value = $this->getUserIds($this->value);
                $this->exact = true;
            }

            if($this->pred_type=='modified' || $this->pred_type=='added'
                || $this->pred_type=='after'  || $this->pred_type=='before'
                || $this->field_id=='added' || $this->field_id=='modified'){

                if($this->pred_type=='before') {$this->lessthan = '<=';}
                if($this->pred_type=='after') {$this->greaterthan = '>';}

                $this->field_type = 'date';
                $cs_ids = null;
                $isHeaderDate = true;
            }elseif($this->pred_type=='title' || $this->pred_type=='url' || $this->pred_type=='notes'
                || $this->field_type=='date'){
                $cs_ids = null;
            }else{
                $cs_ids = getCommaSepIds($this->value);
            }

            if ($cs_ids && strpos($cs_ids, ',')>0) {

                // comma-separated list of defRecTypes ids
                $in = ($this->negate)? 'not in' : 'in';
                $res = " $in (" . $cs_ids . ")";

                $this->field_list = true;

            } elseif($this->field_type=='date'){ //$this->isDateTime()){
                //
                if($isHeaderDate){
                    $res = $this->makeDateClause_ForHeaderField();
                }else{
                    $res = $this->makeDateClause();
                }

            } else {

                if($this->field_type=='link'){
                    $this->fulltext = false;
                }

                list($lang, $this->value) = extractLangPrefix($this->value);

                if (strpos($this->value,"<>")>0) {
                    $vals = explode("<>", $this->value);

                    $between = (($this->negate)?SQL_NOT:'').SQL_BETWEEN;
                    if(is_numeric($vals[0]) && is_numeric($vals[1])){
                        $res = $between.$vals[0].SQL_AND.$vals[1];
                    }else{
                        $res = $between."'".$mysqli->real_escape_string($vals[0])."'".SQL_AND."'".$mysqli->real_escape_string($vals[1])."'";
                    }

                }elseif($this->fulltext){ // && $this->field_type!='link'){

                    //1. check that fulltext index exists
                    if($this->checkFullTextIndex()){
                    //returns true and fill $this->error_message if something wrong
                        return null;
                    }

                    $res = '';

                    if(strpos($this->value, '++')===0 || strpos($this->value, '--')===0){

                        $op = (strpos($this->value, '+')===0)?' +':' -';
                        $this->negate = ($op==' -');

                        //get all words
                        $pattern = "/(\w+)/u";
                        if (preg_match_all($pattern, $this->value, $matches)) {
//words less than 3 characters in length or greater than 84 characters in length do not appear in an InnoDB full-text search index
//and stopwords
$stopwords = array('a','about','an','are','as','at','be','by','com','de','en','for','from','how','i','in','is','it','la','of','on','or','that','the','the','this','to','und','was','what','when','where','who','will','with','www');
                                $words = array();
                                foreach($matches[0] as $word){
                                    $len = strlen($word);
                                    if($len>2 && $len<85 && !in_array($word,$stopwords)){
                                        $words[] = $word;
                                    }
                                }
                                if(!empty($words)){
                                    $t_op = $op == ' -' ? ' ' : $op;
                                    $this->value = trim($t_op).implode($t_op, $words);
                                }else{
                                    //search phrase has only very short or long words
                                    $this->fulltext = false;
                                    $this->exact = false;
                                    $this->value = implode(' ', $matches[0]);
                                }
                        }

                    }

                    if($this->fulltext){
                        if(strpos($this->value, '+')>=0 || strpos($this->value, '-')>=0){
                            $res = ' IN BOOLEAN MODE ';
                        }

                        $res = " AGAINST ('".$mysqli->real_escape_string($this->value)."'$res)";
                    }

                }
                if(!$this->fulltext){

                    if(!$this->exact){ //$eq=='=' &&
                        $eq = ($this->negate?SQL_NOT:'').'LIKE';
                        $k = strpos($this->value,"%");//if begin or end
                        if($k===false || ($k>0 && $k+1<strlen($this->value))){
                            $this->value = '%'.$this->value.'%';
                        }
                    }else{
                        $eq = ($this->negate?'!=':'=');
                    }
                    if($this->case_sensitive){
                            $eq = 'COLLATE utf8_bin '.$eq;
                    }

                    $res = " $eq '" . $mysqli->real_escape_string($this->value) . "'";
                }

                if( !($this->relation_prefix && $this->fulltext) &&
                   ($this->field_type == 'freetext' || $this->field_type == 'blocktext') && intval($this->field_id) > 0){ // filter language


                    if(empty($lang)){ // default only
                        $res = $res . " AND dtl_Value NOT REGEXP '^[\w]{3}:'";
                        $res = $res . " AND dtl_Value NOT REGEXP '^[\w]{2}:'";
                    }elseif($lang == 'ALL' && $this->exact && !$this->fulltext){ // any language, exact and not a fulltext search
                        $res = $res . " OR SUBSTRING(dtl_Value, 0, 4) = '" . $mysqli->real_escape_string($this->value) . "'";
                        $res = $res . " OR SUBSTRING(dtl_Value, 0, 3) = '" . $mysqli->real_escape_string($this->value) . "'";
                    }elseif($lang != 'ALL' && !empty($lang)){ // specific language
                        $ar2 = strtoupper(getLangCode2($lang));
                        $res = $res . " AND ( LEFT(dtl_Value, 4) = '{$lang}:' OR LEFT(dtl_Value, 3) = '{$ar2}:' )";
                    }
                }
            }

        }

        return $res;

    }//getFieldValue

    //
    // check existanse of full text index and creates it
    // return true - if index is missed or its creation is in progress
    //
    private function checkFullTextIndex(){
        global $mysqli;

        if($this->pred_type=='title' || $this->field_id=='title'){

            $fld = mysql__select_value($mysqli,
            'select group_concat(distinct column_name) as fld'
            .' from information_schema.STATISTICS '
            ." where table_schema = '".HEURIST_DBNAME_FULL."' and table_name = 'Records' and index_type = 'FULLTEXT'");

            if($fld==null){
                $mysqli->query('ALTER TABLE Records ADD FULLTEXT INDEX `rec_Title_FullText` (`rec_Title`)');// VISIBLE
                $this->error_message = 'create_fulltext';
                return true;
            }

        }else{

            $fld = mysql__select_value($mysqli,
            'select group_concat(distinct column_name) as fld'
            .' from information_schema.STATISTICS '
            ." where table_schema = '".HEURIST_DBNAME_FULL."' and table_name = 'recDetails' and index_type = 'FULLTEXT'");

            if($fld==null){

                $mysqli->query('ALTER TABLE recDetails ADD FULLTEXT INDEX `dtl_Value_FullText` (`dtl_Value`)');// VISIBLE
                $this->error_message = 'create_fulltext';
                return true;
            }

        }

        return false;

    }



}//HPredicate
?>
