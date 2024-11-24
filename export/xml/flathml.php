<?php

/**
* flathml.php:  flattened version of HML - records are not generated redundantly but are indicated by references within other records.
*               $hunifile indicates special one-file-per-record + manifest file for HuNI (huni.net.au)
*               $output_file - file handler to write output, it allows to avoid memory overflow for large databases
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Kim Jackson
* @author      Stephen White
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.1.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/*
parameters
$OUTPUT_STUBS, stub  - output only record header fields (see outputRecordStub)
$REVERSE,  rev  - yes|no include reverse pointer fields
$EXPAND_REV_PTR, revexpand   yes|no
$NO_RELATIONSHIPS  yes|no

$WOOT, woot default to not output text content
$USEXINCLUDELEVEL, hinclude  default to not output xinclude format for related records until beyound 99 degrees of separation
$USEXINCLUDE hinclude default to not output xinclude format for related records
$INCLUDE_FILE_CONTENT fc default NOT expand xml file content

*/

/**
* @todo
* 1) use temp table to store relatedSet
* 2) use recLinks
*
* Function list:
* - makeTag()
* - openTag()
* - closeTag()
* - openCDATA()
* - closeCDATA()
*
* - findPointers()  NOT USED
* - findReversePointers()  NOT USED
* - findRelatedRecords()  NOT USED
* - buildGraphStructure()   NOT USED
* - outputRecords()
* - outputRecord()
* - outputXInclude()
* - outputRecordStub()
* - makeFileContentNode()
* - outputDetail()
* - outputTemporalDetail()
* - outputDateDetail()
* - outputTDateDetail()
* - outputDurationDetail()
* - replaceIllegalChars()
* - check()
*
* No Classes in this File
*
*/

if (@$argv) {
    // handle command-line queries
    $ARGV = array();
    for ($i = 0;$i < count($argv);++$i) {
        if ($argv[$i][0] === '-') {
            if (@$argv[$i + 1] && $argv[$i + 1][0] != '-') {
                $ARGV[$argv[$i]] = $argv[$i + 1];
                ++$i;
            } else {
                $ARGV[$argv[$i]] = true;
            }
        } else {
            array_push($ARGV, $argv[$i]);
        }
    }
    if (@$ARGV['-db']) {$_REQUEST["db"] = $ARGV['-db'];}
    if (@$ARGV['-f']) {$_REQUEST['f'] = $ARGV['-f'];}
    $_REQUEST['q'] = @$ARGV['-q'];
    $_REQUEST['w'] = @$ARGV['-w'] ? $ARGV['-w'] : 'a';// default to ALL RESOURCES
    if (@$ARGV['-stype']) {$_REQUEST['stype'] = $ARGV['-stype'];}
    $_REQUEST['style'] = '';
    $_REQUEST['depth'] = @$ARGV['-depth'] ? $ARGV['-depth'] : 0;
    if (@$ARGV['-rev']) {$_REQUEST['rev'] = $ARGV['-rev'];}
    if (@$ARGV['-woot']) {$_REQUEST['woot'] = $ARGV['-woot'];}
    if (@$ARGV['-stub']) {$_REQUEST['stub'] = '1';}
    if (@$ARGV['-fc']) {$_REQUEST['fc'] = '1';}// inline file content
    if (@$ARGV['-file']) {$_REQUEST['file'] = '1';}// inline file content

    $_REQUEST['linkmode'] = @$ARGV['-linkmode'] ? $ARGV['-linkmode'] : 'none';
    
    if (@$ARGV['-backup']) { //save output to backup folder
       if(!$_REQUEST['q']){
            $_REQUEST['q'] = 'sortby:-m';
       }
       $_REQUEST['filename'] = '1';
    }
}

use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../../hserv/structure/search/dbsData.php';
require_once dirname(__FILE__).'/../../hserv/records/search/recordSearch.php';


if(@$_REQUEST['postdata']){
    //all parameters can be sent as json array in postdata
    $_REQUEST = json_decode($_REQUEST['postdata'], true);
}

define('REGEX_JSON_CHECK','/[^\\:\\s"\\[\\]\\{\\}0-9\\,]/');
define('REGEX_SPLIT_DATE', "![-\/]!");
define('ERR_INVALID_FILTER', 'Error: invalid json filters string');

$human_readable_names = (@$_REQUEST['human_readable_names']==1);

$rectype_templates = array_key_exists('rectype_templates', $_REQUEST) && !empty($_REQUEST['rectype_templates']);//flag to produce rectype templates instead of real records

if($rectype_templates){ // output manifest + files ??

    $rectype_templates = $_REQUEST['rectype_templates']=='y' || $_REQUEST['rectype_templates']=='all' ? true : prepareIds($_REQUEST['rectype_templates']);
    $rectype_templates = empty($rectype_templates) ? false : $rectype_templates;

    $human_readable_names = $rectype_templates !== false;
}


if(@$_REQUEST['multifile']){ // output manifest + files ??
    $intofile = $_REQUEST['multifile']!=null && $_REQUEST['multifile']!=0; //flag one-record-per-file mode for HuNI
}else{
    $intofile = false;
}

$output_file = null;
$output_file_fd = null;

$hunifile = null; //name of file-per-record for HuNI mode

if(!defined('PDIR')){
    $system = new hserv\System();
    if( !$system->init(@$_REQUEST['db']) ){
        die('Cannot connect to database');
    }
}

//write the output into single file
// output to file is allowed in the only case - archiving database
if(@$_REQUEST['filename']==1 && file_exists(HEURIST_FILESTORE_DIR.DIR_BACKUP)){
    $output_file = tempnam(HEURIST_SCRATCHSPACE_DIR, "xml");

    $output_file_fd = fopen($output_file, 'w');

    $_REQUEST['mode'] = 1;
}


if(!defined('HEURIST_HML_DIR')){

    define('HEURIST_HML_DIR', $system->getSysDir('hml-output'));
    folderCreate2(HEURIST_HML_DIR, '', true);
    define('HEURIST_HML_URL', $system->getSysUrl('hml-output'));
}

// why is this left to be set later as a success on file access??

if(!$intofile){
    if (@$_REQUEST['mode'] != '1') {
        header('Content-type: text/xml; charset=utf-8');

        if(@$_REQUEST['file']==1 || @$_REQUEST['file']===true){
            //name of file in header - for download
            if($rectype_templates){
                $filename = 'Template_'.$_REQUEST['db'].'_'.date("YmdHis").'.hml';
            }else{
                $filename = 'Export_'.$_REQUEST['db'].'_'.date("YmdHis").'.xml';
            }

            header('Content-Disposition: attachment; filename='.$filename);

        }

    }
    output( XML_HEADER."\n" );
}

set_time_limit(0);//no limit

//TODO change to ConceptCode 
$relRT = ($system->defineConstant('RT_RELATION')?RT_RELATION:0);
$relSrcDT = ($system->defineConstant('DT_PRIMARY_RESOURCE')?DT_PRIMARY_RESOURCE:0);
$relTrgDT = ($system->defineConstant('DT_TARGET_RESOURCE')?DT_TARGET_RESOURCE:0);
$relTypDT = ($system->defineConstant('DT_RELATION_TYPE') ? DT_RELATION_TYPE : 0);

$mysqli = $system->getMysqli();

// Get database registration ID
$dbID = $system->settings->get('sys_dbRegisteredID');

//----------------------------------------------------------------------------//
//  Tag construction helpers
//----------------------------------------------------------------------------//

function output($str){
    global $intofile, $hunifile, $output_file, $output_file_fd;

    if($intofile){
        if($hunifile){
            fwrite($hunifile, $str);
        }
    }else{
        if($output_file_fd){
            fwrite($output_file_fd, $str);
        }else{
            echo $str;
        }
    }

}

function xmlspecialchars($value){
    $value = htmlspecialchars($value);
    $value = str_replace('%', '&#37;', $value);
    return $value;
}

function makeTag($name, $attributes = null, $textContent = null, $close = true, $encodeContent = true) {
    $tag = "<$name";
    if (is_array($attributes)) {
        foreach ($attributes as $attr => $value) {
            $tag.= ' ' . xmlspecialchars($attr) . '="' . xmlspecialchars($value) . '"';
        }
    }
    if ($close && ($textContent==null || $textContent=='')) {
        $tag.= '/>';
    } else {
        $tag.= '>';
    }
    if (!($textContent==null || $textContent=='')) {
        if ($encodeContent) {
            $tag.= xmlspecialchars($textContent);
        } else {
            $tag.= $textContent;
        }
        $tag.= "</$name>";
    }
    output( $tag . "\n" );

}


/**
* description
* @param     mixed $name
* @param     mixed $attributes
* @return    type description
*/
function openTag($name, $attributes = null) {
    makeTag($name, $attributes, null, false);
}


function closeTag($name) {
    output( "</$name>\n" );

}


function openCDATA() {
    output( "<![CDATA[\n" );
}


function closeCDATA() {
    output( "]]>\n" );
}


//----------------------------------------------------------------------------//
//  Retrieve record- and detail- type metadata
//----------------------------------------------------------------------------//

$RTN = array();//record type name
$RQS = array();//record type specific detail name
$DTN = array();//detail type name
$DTT = array();//detail type base type
$INV = array();//relationship term inverse
$WGN = array();//work group name
$UGN = array();//User name
$TL = array();//term lookup
$TLV = array();//term lookup by value

// record type labels
$rtStructs = dbs_GetRectypeStructures($system, null, 2);
$RTN = $rtStructs['names'];
foreach ($rtStructs['typedefs'] as $rt_id => $def)
{
    if($rt_id>0 && @$def['dtFields']) {
        foreach ($def['dtFields'] as $rst_DetailTypeID => $rdr){
            $RQS[$rt_id][$rst_DetailTypeID] = @$rdr[0];
        }
    }
}

// base names, varieties for detail types
$query = 'SELECT dty_ID, dty_Name, dty_Type FROM defDetailTypes';
$res = $mysqli->query($query);
while ($row = $res->fetch_assoc()) {
    $DTN[$row['dty_ID']] = $row['dty_Name'];
    $DTT[$row['dty_ID']] = $row['dty_Type'];
}
$res->close();

$INV = mysql__select_assoc2($mysqli, 'SELECT trm_ID, trm_InverseTermID FROM defTerms');//saw Enum change just assoc id to related id

// lookup detail type enum values
$query = 'SELECT trm_ID, trm_Label, trm_ParentTermID, trm_OntID, trm_Code FROM defTerms';
$res = $mysqli->query($query);
while ($row = $res->fetch_assoc()) {
    $TL[$row['trm_ID']] = $row;
    $TLV[$row['trm_Label']] = $row;
}
$res->close();

// group names
$WGN = mysql__select_assoc2($mysqli, 'SELECT grp.ugr_ID, grp.ugr_Name FROM sysUGrps grp WHERE ugr_Type = "workgroup"');
$UGN = mysql__select_assoc2($mysqli, 'SELECT grp.ugr_ID, grp.ugr_Name FROM sysUGrps grp WHERE ugr_Type = "user"');

$GEO_TYPES = array('r' => 'bounds', 'c' => 'circle', 'pl' => 'polygon', 'l' => 'path', 'p' => 'point', 'm'=>'multi');
$NO_RELATIONSHIPS = false;

// set parameter defaults
if(@$_REQUEST['linkmode']){//direct, direct_links, none, all

    if($_REQUEST['linkmode']=='none'){
        $_REQUEST['depth'] = '0';
        $NO_RELATIONSHIPS = true;
        $_REQUEST['rev'] = 'no';

    }elseif($_REQUEST['linkmode']=='direct'){
        $_REQUEST['revexpand'] = 'no';
        $_REQUEST['rev'] = 'no';

    }elseif($_REQUEST['linkmode']=='direct_links'){
        $_REQUEST['revexpand'] = 'no';
        $_REQUEST['rev'] = 'no';
        $NO_RELATIONSHIPS = true;

    }elseif($_REQUEST['linkmode']=='all'){
        $_REQUEST['revexpand'] = 'yes';
    }
}

$REVERSE = @$_REQUEST['rev'] === 'no' ? false : true; //default to including reverse pointers
$EXPAND_REV_PTR = @$_REQUEST['revexpand'] === 'no' ? false : true;
$WOOT = @$_REQUEST['woot'] ? intval($_REQUEST['woot']) : 0; //default to not output text content
$USEXINCLUDELEVEL = array_key_exists('hinclude', $_REQUEST) && is_numeric($_REQUEST['hinclude']) ? $_REQUEST['hinclude'] : 99;
//default to not output xinclude format for related records until beyound 99 degrees of separation
$USEXINCLUDE = array_key_exists('hinclude', $_REQUEST) ? true : false; //default to not output xinclude format for related records
$OUTPUT_STUBS = @$_REQUEST['stub'] == '1' ? true : false; //default to not output stubs
$INCLUDE_FILE_CONTENT = (@$_REQUEST['fc'] && $_REQUEST['fc'] == - 1
            ? false
            : (@$_REQUEST['fc'] && is_numeric($_REQUEST['fc']) ? $_REQUEST['fc'] : false));// default NOT expand xml file content
//before 2020-01-17 $INCLUDE_FILE_CONTENT=0 - include for level 0
//TODO: supress loopback by default unless there is a filter.
$SUPRESS_LOOPBACKS = (@$_REQUEST['slb'] && $_REQUEST['slb'] == 0 ? false : true);// default to supress loopbacks or gives oneside of a relationship record  ARTEM:NOT USED ANYMORE
$FRESH = (@$_REQUEST['f'] && $_REQUEST['f'] == 1 ? true : false);
//$PUBONLY = (((@$_REQUEST['pub_ID'] && is_numeric($_REQUEST['pub_ID'])) ||
$PUBONLY = ((@$_REQUEST['pubonly'] && $_REQUEST['pubonly'] > 0) ? true : (!$system->hasAccess() ? true : false));

$filterString = (@$_REQUEST['rtfilters'] ? $_REQUEST['rtfilters'] : null);
if ($filterString && preg_match(REGEX_JSON_CHECK, $filterString)) {
    die(ERR_INVALID_FILTER);
}

$RECTYPE_FILTERS = ($filterString ? json_decode($filterString, true) : array());
if (!isset($RECTYPE_FILTERS)) {
    die(" error decoding json record type filters string");
}

$filterString = (@$_REQUEST['relfilters'] ? $_REQUEST['relfilters'] : null);
if ($filterString && preg_match(REGEX_JSON_CHECK, $filterString)) {
    die(ERR_INVALID_FILTER);
}

$RELTYPE_FILTERS = ($filterString ? json_decode($filterString, true) : array());
if (!isset($RELTYPE_FILTERS)) {
    die(" error decoding json relation type filters string");
}

$filterString = (@$_REQUEST['ptrfilters'] ? $_REQUEST['ptrfilters'] : null);
if ($filterString && preg_match(REGEX_JSON_CHECK, $filterString)) {
    die(ERR_INVALID_FILTER);
}

$PTRTYPE_FILTERS = ($filterString ? json_decode($filterString, true) : array());
if (!isset($PTRTYPE_FILTERS)) {
    die(" error decoding json pointer type filters string");
}

$filterString = (@$_REQUEST['selids'] ? $_REQUEST['selids'] : null);
if ($filterString && preg_match(REGEX_JSON_CHECK, $filterString)) {
    die(ERR_INVALID_FILTER);
}

$SELIDS_FILTERS = ($filterString ? json_decode($filterString, true) : array());
if (!isset($SELIDS_FILTERS)) {
    die(" error decoding json selected ids string");
} else {
    $selectedIDs = array();
    foreach ($SELIDS_FILTERS as $ids) {
        foreach ($ids as $id) {
            $selectedIDs[$id] = 1;
        }
    }
    $selectedIDs = array_keys($selectedIDs);
}

if(@$_REQUEST['depth']=='all'){
    $MAX_DEPTH = 9999;
}else{
    $MAX_DEPTH = (@$_REQUEST['depth'] ? intval($_REQUEST['depth'])
            : (count(array_merge(array_keys($PTRTYPE_FILTERS), array_keys($RELTYPE_FILTERS), array_keys($RECTYPE_FILTERS),
                array_keys($SELIDS_FILTERS))) > 0 ? max(array_merge(array_keys($PTRTYPE_FILTERS), array_keys($RELTYPE_FILTERS),
                array_keys($RECTYPE_FILTERS), array_keys($SELIDS_FILTERS))) : 0));// default to only one level
}


// handle special case for collection where ids are stored in the session.
if (array_key_exists('q', $_REQUEST)) {
    if (preg_match('/_COLLECTED_/', $_REQUEST['q'])) {

        $collection =  &$_SESSION[HEURIST_DBNAME_FULL]['record-collection'];
        if (!empty($collection)) {
            $_REQUEST['q'] = 'ids:' . join(',', prepareIds(array_keys($collection)));
        } else {
            $_REQUEST['q'] = '';
        }
    }
} elseif (array_key_exists('recID', $_REQUEST)) { //record IDs to use as a query
    //check for expansion of query records.
    $recIDs = prepareIds($_REQUEST['recID']);
    $_REQUEST['q'] = 'ids:' . join(',', $recIDs);
}

//----------------------------------------------------------------------------//
//  Authentication
//----------------------------------------------------------------------------//

$ACCESSABLE_OWNER_IDS = mysql__select_list($mysqli,
    'sysUsrGrpLinks left join sysUGrps grp on grp.ugr_ID=ugl_GroupID', 'ugl_GroupID', 'ugl_UserID='
    . $system->getUserId() . ' and grp.ugr_Type != "user" order by ugl_GroupID');


if ($system->hasAccess()) { //logged in
    array_push($ACCESSABLE_OWNER_IDS, $system->getUserId());
    if (!in_array(0, $ACCESSABLE_OWNER_IDS)) {
        array_push($ACCESSABLE_OWNER_IDS, 0);
    }
}

//----------------------------------------------------------------------------//
// Traversal functions
// The aim here is to bundle all the queries for each level of relationships
// into one query, rather than doing them all recursively.
//----------------------------------------------------------------------------//

//
//
//
function predicateRecordVisibility($dst='trg'){
    global $system, $ACCESSABLE_OWNER_IDS, $PUBONLY;


    return  (!empty($ACCESSABLE_OWNER_IDS) && !$PUBONLY
            ? '('.$dst.'.rec_OwnerUGrpID in (' .join(',', prepareIds($ACCESSABLE_OWNER_IDS, true)) . ') OR '
            : '(') .
    (($system->hasAccess() && !$PUBONLY) ? 'NOT '.$dst.'.rec_NonOwnerVisibility = "hidden")' : $dst.'.rec_NonOwnerVisibility = "public")');

}

//
//
//
function predicateRtyDtyFilters($rtyIDs, $dtyIDs, $dt_field='dty_ID')
{
    return predicateId('trg.rec_RecTypeID', $rtyIDs, SQL_AND) . predicateId($dt_field, $dtyIDs, SQL_AND);
}


/**
* findPointers - Helper function that finds recIDs of record pointer details for all records in a given set of recIDs
* which can be filtered to a set of rectypes
* @author Stephen White derived from original work by Kim Jackson
* @param $rec_ids an array of recIDs from the Records table for which to search from details
* @param $rtyIDs an array of rectypeIDs valid in the defRecTypes table used to filter the result
* @param $dtyIDs an array of detailTypeIDs valid in the defDetailTypes table used to filter the results
* @return $ret a comma separated list of recIDs
*/
function findPointers($qrec_ids, &$recSet, $depth, $rtyIDs, $dtyIDs) {
    global $system, $mysqli;
    //saw TODO add error checking for numeric values in $rtyIDs and $dtyIDs
    // find all detail values for record pointer type details which exist for any record with an id in $rec_ids
    // and also is of a type in rtyIDs if rtyIDs is set to non null
    $nlrIDs = array();// new linked record IDs
    $query = 'SELECT dtl_RecID as srcRecID, src.rec_RecTypeID as srcType, '
    . 'dtl_Value as trgRecID, '
    . 'dtl_DetailTypeID as ptrDetailTypeID ' . ', trg.* ' . ', trg.rec_NonOwnerVisibility ' .
    //saw TODO check if we need to also check group ownership
    'FROM recDetails LEFT JOIN defDetailTypes on dtl_DetailTypeID = dty_ID ' .
    'LEFT JOIN Records src on src.rec_ID = dtl_RecID ' .
    'LEFT JOIN Records trg on trg.rec_ID = dtl_Value ' .
    'WHERE dtl_RecID in (' . join(',', prepareIds($qrec_ids)) . ') AND (trg.rec_FlagTemporary=0) '
    . predicateRtyDtyFilters($rtyIDs, $dtyIDs)
    . 'AND dty_Type = "resource" AND '
        .predicateRecordVisibility();


    $res = $mysqli->query($query);
    if($res){
        while ($row = $res->fetch_assoc()) {
            // if target is not in the result
            if (!array_key_exists($row['trgRecID'], $recSet['relatedSet'])) {
                $recSet['relatedSet'][$row['trgRecID']] = array('depth' => $depth, 'recID' => $row['trgRecID']);
                $nlrIDs[$row['trgRecID']] = 1; //save it for next level query

            } elseif($rtyIDs || $dtyIDs) { // TODO placed here for directed filtering which means we want repeats to be expanded
                $nlrIDs[$row['trgRecID']] = 1; //save it for next level query

            }

            if (!@$recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']) {
                $recSet['relatedSet'][$row['trgRecID']]['revPtrLinks'] = array('byInvDtlType' => array(), 'byRecIDs' => array());//create an entry

            }

            if (!@$recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']]) {
                $recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']] = array($row['srcRecID']);
            } elseif (!in_array($row['srcRecID'], $recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']])) {
                array_push($recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']], $row['srcRecID']);
            }

            if (!@$recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byRecIDs'][$row['srcRecID']]) {
                $recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byRecIDs'][$row['srcRecID']] = array($row['ptrDetailTypeID']);
            } elseif (!in_array($row['ptrDetailTypeID'], $recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byRecIDs'][$row['srcRecID']])) {
                array_push($recSet['relatedSet'][$row['trgRecID']]['revPtrLinks']['byRecIDs'][$row['srcRecID']], $row['ptrDetailTypeID']);
            }

            if (!@$recSet['relatedSet'][$row['srcRecID']]['ptrLinks']) {
                $recSet['relatedSet'][$row['srcRecID']]['ptrLinks'] = array('byDtlType' => array(), 'byRecIDs' => array());//create ptrLinks sub arrays

            }

            if (!@$recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']]) {
                $recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']] = array($row['trgRecID']);
            } elseif (!in_array($row['trgRecID'], $recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']])) {
                array_push($recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']], $row['trgRecID']);
            }

            if (!@$recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byRecIDs'][$row['trgRecID']]) {
                $recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byRecIDs'][$row['trgRecID']] = array($row['ptrDetailTypeID']);
            } elseif (!in_array($row['ptrDetailTypeID'], $recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byRecIDs'][$row['trgRecID']])) {
                array_push($recSet['relatedSet'][$row['srcRecID']]['ptrLinks']['byRecIDs'][$row['trgRecID']], $row['ptrDetailTypeID']);
            }
        }//while

        $res->close();
    }
    return array_keys($nlrIDs);

}


/**
* findReversePointers - Helper function that finds recIDs of all records excluding relationShip records
* that have a pointer detail value that is in a given set of recIDs which can be filtered to a set of rectypes
* @author Kim Jackson
* @author Stephen White
* @param $rec_ids an array of recIDs from the Records table for which to search from details
* @param $pointers an out array of recIDs to store look up detailTypeID by pointed_to recID by pointed_to_by recID
* @param $rtyIDs an array of rectypeIDs valid in the defRecTypes table
* @param $dtyIDs an array of detailTypeIDs valid in the defDetailTypes table used to filter the results
* @return $ret a comma separated list of recIDs of pointed_to_by records
*/
function findReversePointers($qrec_ids, &$recSet, $depth, $rtyIDs, $dtyIDs) {
    global $system, $mysqli, $REVERSE, $ACCESSABLE_OWNER_IDS, $relRT, $PUBONLY;

    $nlrIDs = array();// new linked record IDs
    $query = 'SELECT dtl_Value as srcRecID, src.rec_RecTypeID as srcType, ' .
    'dtl_RecID as trgRecID, dty_ID as ptrDetailTypeID ' . ', trg.* ' . ', trg.rec_NonOwnerVisibility ' .
    'FROM recDetails '
    .' LEFT JOIN defDetailTypes ON dtl_DetailTypeID = dty_ID '
    .' LEFT JOIN Records trg on trg.rec_ID = dtl_RecID '
    .' LEFT JOIN Records src on src.rec_ID = dtl_Value '
    .' WHERE dty_Type = "resource" ' . 'AND dtl_Value IN (' .
        join(',', prepareIds($qrec_ids)) . ') ) AND (trg.rec_FlagTemporary=0) '
    . predicateRtyDtyFilters($rtyIDs, $dtyIDs)
    . "AND trg.rec_RecTypeID != $relRT AND "
    . predicateRecordVisibility();

    $res = $mysqli->query($query);
    if($res){
        while ($row = $res->fetch_assoc()) {
            // if target is not in the result
            $nlrIDs[$row['trgRecID']] = 1; //save it for next level query
            if (!array_key_exists($row['trgRecID'], $recSet['relatedSet'])) {
                $recSet['relatedSet'][$row['trgRecID']] = array('depth' => $depth, 'recID' => $row['trgRecID']);
                $nlrIDs[$row['trgRecID']] = 1; //save it for next level query

            } elseif($rtyIDs || $dtyIDs) { // TODO placed here for directed filtering which means we want repeats to be expanded
                $nlrIDs[$row['trgRecID']] = 1; //save it for next level query

            }
            if (!@$recSet['relatedSet'][$row['trgRecID']]['ptrLinks']) {
                $recSet['relatedSet'][$row['trgRecID']]['ptrLinks'] = array('byDtlType' => array(), 'byRecIDs' => array());//create an entry

            }

            if (!@$recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']]) {
                $recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']] = array($row['srcRecID']);
            } elseif (!in_array($row['srcRecID'], $recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']])) {
                array_push($recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byDtlType'][$row['ptrDetailTypeID']], $row['srcRecID']);
            }

            if (!@$recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byRecIDs'][$row['srcRecID']]) {
                $recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byRecIDs'][$row['srcRecID']] = array($row['ptrDetailTypeID']);
            } elseif (!in_array($row['ptrDetailTypeID'], $recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byRecIDs'][$row['srcRecID']])) {
                array_push($recSet['relatedSet'][$row['trgRecID']]['ptrLinks']['byRecIDs'][$row['srcRecID']], $row['ptrDetailTypeID']);
            }

            if (!@$recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']) {
                $recSet['relatedSet'][$row['srcRecID']]['revPtrLinks'] = array('byInvDtlType' => array(), 'byRecIDs' => array());//create an entry

            }

            if (!@$recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']]) {
                $recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']] = array($row['trgRecID']);
            } elseif (!in_array($row['trgRecID'], $recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']])) {
                array_push($recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byInvDtlType'][$row['ptrDetailTypeID']], $row['trgRecID']);
            }

            if (!@$recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byRecIDs'][$row['trgRecID']]) {
                $recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byRecIDs'][$row['trgRecID']] = array($row['ptrDetailTypeID']);
            } elseif (!in_array($row['ptrDetailTypeID'], $recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byRecIDs'][$row['trgRecID']])) {
                array_push($recSet['relatedSet'][$row['srcRecID']]['revPtrLinks']['byRecIDs'][$row['trgRecID']], $row['ptrDetailTypeID']);
            }
        }
        $res->close();
    }
    return array_keys($nlrIDs);
}


/**
* findRelatedRecords - Helper function that finds recIDs of all related records using relationShip records
* that have a pointer detail value that is in a given set of recIDs which can be filtered to a set of rectypes
* @author Kim Jackson
* @author Stephen White
* @param $rec_ids an array of recIDs from the Records table for which to search related records
* @param $relationships an out array of recIDs to store look up relationRecID by supplied target/source recIDs
* @param $rtyIDs an array of rectypeIDs valid in the defRecTypes table
* @param $relTermIDs an array of termIDs valid in the defTerms table used to filter the results
* @return $ret a comma separated list of recIDs of other record recIDs
*/
function findRelatedRecords($qrec_ids, &$recSet, $depth, $rtyIDs, $relTermIDs) {
    global $system, $mysqli, $REVERSE, $ACCESSABLE_OWNER_IDS, $relRT, $relTrgDT, $relTypDT, $relSrcDT, $PUBONLY;
    $nlrIDs = array();
    $query = 'SELECT f.dtl_Value as srcRecID, rel.rec_ID as relID, ' . // from detail
    'IF( f.dtl_Value IN (' . join(',', prepareIds($qrec_ids)) . '),1,0) as srcIsFrom, ' .
    't.dtl_Value as trgRecID, trm.trm_ID as relType, trm.trm_InverseTermID as invRelType ' .
    ', src.rec_NonOwnerVisibility , trg.rec_NonOwnerVisibility ' . 'FROM recDetails f ' .
    'LEFT JOIN Records rel ON rel.rec_ID = f.dtl_RecID and f.dtl_DetailTypeID = ' . intval($relSrcDT) . ' ' .
    'LEFT JOIN recDetails t ON t.dtl_RecID = rel.rec_ID and t.dtl_DetailTypeID = ' . intval($relTrgDT) . ' ' .
    'LEFT JOIN recDetails r ON r.dtl_RecID = rel.rec_ID and r.dtl_DetailTypeID = ' . intval($relTypDT) . ' ' .
    'LEFT JOIN defTerms trm ON trm.trm_ID = r.dtl_Value ' . 'LEFT JOIN Records trg ON trg.rec_ID = t.dtl_Value ' .
    'LEFT JOIN Records src ON src.rec_ID = f.dtl_Value ' .
    'WHERE rel.rec_RecTypeID = ' . intval($relRT) . ' AND (rel.rec_FlagTemporary=0) '
        . 'AND (f.dtl_Value IN (' . join(',', prepareIds($qrec_ids)) . ') '
    .predicateRtyDtyFilters($rtyIDs, null)
    .($REVERSE ? 'OR t.dtl_Value IN (' . join(',', prepareIds($qrec_ids)) . ') ' .
        (is_array($rtyIDs) && !empty($rtyIDs) ? 'AND src.rec_RecTypeID in (' .
            join(',', prepareIds($rtyIDs)) . ') ' : '') : '') . ')'
    .predicateRecordVisibility('src')
    .predicateRecordVisibility()
    (is_array($relTermIDs) && !empty($relTermIDs) ? 'AND (trm.trm_ID in (' .
        join(',', prepareIds($relTermIDs)) . ') OR trm.trm_InverseTermID in (' . join(',', prepareIds($relTermIDs)) . ')) ' : '');

    $res = $mysqli->query($query);
    if($res){
        while ($row = $res->fetch_assoc()) {
            if (!$row['relType'] && !$row['invRelType']) { // no type information invalid relationship
                continue;
            }

            // if source is not in the result
            if (!array_key_exists($row['srcRecID'], $recSet['relatedSet'])) {
                $recSet['relatedSet'][$row['srcRecID']] = array('depth' => $depth, 'recID' => $row['srcRecID']);
                $nlrIDs[$row['srcRecID']] = 1; //save it for next level query
            }

            //if rel rec not in result
            if (!array_key_exists($row['relID'], $recSet['relatedSet'])) {
                $recSet['relatedSet'][$row['relID']] = array('depth' => $recSet['relatedSet'][$row['srcRecID']]['depth'] . ".5", 'recID' => $row['relID']);
            }

            // if target is not in the result
            if (!array_key_exists($row['trgRecID'], $recSet['relatedSet'])) {
                $recSet['relatedSet'][$row['trgRecID']] = array('depth' => $depth, 'recID' => $row['trgRecID']);
                $nlrIDs[$row['trgRecID']] = 1; //save it for next level query

            }

            if (!@$recSet['relatedSet'][$row['srcRecID']]['relLinks']) {
                $recSet['relatedSet'][$row['srcRecID']]['relLinks'] =
                array('byRelType' => array(), 'byRecIDs' => array(), 'relRecIDs' => array());//create an entry
            }

            if (!@$recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRelType'][$row['relType']]) {
                $recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRelType'][$row['relType']] = array($row['trgRecID']);
            } elseif (!in_array($row['trgRecID'], $recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRelType'][$row['relType']])) {
                array_push($recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRelType'][$row['relType']], $row['trgRecID']);
            }

            if (!@$recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRecIDs'][$row['trgRecID']]) {
                $recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRecIDs'][$row['trgRecID']] = array($row['relType']);
            } elseif (!in_array($row['relType'], $recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRecIDs'][$row['trgRecID']])) {
                array_push($recSet['relatedSet'][$row['srcRecID']]['relLinks']['byRecIDs'][$row['trgRecID']], $row['relType']);
            }

            if ($row['relID'] && !in_array($row['relID'], $recSet['relatedSet'][$row['srcRecID']]['relLinks']['relRecIDs'])) {
                array_push($recSet['relatedSet'][$row['srcRecID']]['relLinks']['relRecIDs'], $row['relID']);
            }

            if (!@$recSet['relatedSet'][$row['trgRecID']]['revRelLinks']) {
                $recSet['relatedSet'][$row['trgRecID']]['revRelLinks'] =
                array('byInvRelType' => array(), 'byRecIDs' => array(), 'relRecIDs' => array());//create an entry
            }

            $inverse = $row['invRelType'] ? $row['invRelType'] : "-" . $row['relType'];
            if (!@$recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byInvRelType'][$inverse]) {
                $recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byInvRelType'][$inverse] = array($row['srcRecID']);
            } elseif (!in_array($row['srcRecID'], $recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byInvRelType'][$inverse])) {
                array_push($recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byInvRelType'][$inverse], $row['srcRecID']);
            }

            if (!@$recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byRecIDs'][$row['srcRecID']]) {
                $recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byRecIDs'][$row['srcRecID']] = array($inverse);
            } elseif (!in_array($inverse, $recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byRecIDs'][$row['srcRecID']])) {
                array_push($recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['byRecIDs'][$row['srcRecID']], $inverse);
            }

            if ($row['relID'] && !in_array($row['relID'], $recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['relRecIDs'])) {
                array_push($recSet['relatedSet'][$row['trgRecID']]['revRelLinks']['relRecIDs'], $row['relID']);
            }
        }
        $res->close();
    }
    return array_keys($nlrIDs);
}


/**
* buildGraph - Function to build the structure for a set of records and all there related(linked) records to a given level
* @author Stephen White
* @param $rec_ids an array of recIDs from the Records table for which to build the tree
* @param $recSet an out array to store look up records by recordID
* @param $relationships an out array of recIDs to store look up relationRecID by supplied target/source recIDs
*/
function buildGraphStructure($rec_ids, &$recSet) {
    global $mysqli, $MAX_DEPTH, $REVERSE, $RECTYPE_FILTERS, $RELTYPE_FILTERS, $PTRTYPE_FILTERS, $EXPAND_REV_PTR, $OUTPUT_STUBS;
    $depth = 0;
    $rtfilter = (array_key_exists($depth, $RECTYPE_FILTERS) ? $RECTYPE_FILTERS[$depth] : null);
    if ($rtfilter) {
        $query = 'SELECT rec_ID from Records ' .
        'WHERE rec_ID in (' . join(",", prepareIds($rec_ids)) . ') ' .
        'AND rec_RecTypeID in (' . join(",", prepareIds($rtfilter)) . ')';
        $filteredIDs = array();

        $res = $mysqli->query($query);
        if($res){
            while ($row = $res->fetch_row()) {
                $filteredIDs[$row[0]] = 1;
            }
            $res->close();
        }


        $rec_ids = array_keys($filteredIDs);
    }

    if ($MAX_DEPTH == 0 && $OUTPUT_STUBS && !empty($rec_ids)) {
        findPointers($rec_ids, $recSet, 1, null, null);
    } else {
        while ($depth++ < $MAX_DEPTH && !empty($rec_ids)) {
            $rtfilter = (@$RECTYPE_FILTERS && array_key_exists($depth, $RECTYPE_FILTERS) ? $RECTYPE_FILTERS[$depth] : null);
            $relfilter = (@$RELTYPE_FILTERS && array_key_exists($depth, $RELTYPE_FILTERS) ? $RELTYPE_FILTERS[$depth] : null);
            $ptrfilter = (@$PTRTYPE_FILTERS && array_key_exists($depth, $PTRTYPE_FILTERS) ? $PTRTYPE_FILTERS[$depth] : null);
            $p_rec_ids = findPointers($rec_ids, $recSet, $depth, $rtfilter, $ptrfilter);
            $rp_rec_ids = $REVERSE ? findReversePointers($rec_ids, $recSet, $depth, $rtfilter, $ptrfilter) : array();
            $rel_rec_ids = findRelatedRecords($rec_ids, $recSet, $depth, $rtfilter, $relfilter);
            $rec_ids = array_merge($p_rec_ids, ($EXPAND_REV_PTR ? $rp_rec_ids : array()), $rel_rec_ids);// record set for a given level

        }
    }
}

//-----------------------------
//
// new set of functions to find links and related records dynamically
//
function _getReversePointers($rec_id, $depth){

    global $system, $mysqli, $ACCESSABLE_OWNER_IDS, $PUBONLY, $RECTYPE_FILTERS, $PTRTYPE_FILTERS;

    $rtfilter = (@$RECTYPE_FILTERS && array_key_exists($depth, $RECTYPE_FILTERS) ? $RECTYPE_FILTERS[$depth] : null);
    $ptrfilter = (@$PTRTYPE_FILTERS && array_key_exists($depth, $PTRTYPE_FILTERS) ? $PTRTYPE_FILTERS[$depth] : null);

    $query = 'SELECT rl_SourceID, rl_TargetID, src.rec_RecTypeID, rl_DetailTypeID FROM recLinks, Records src '
    .' where rl_TargetID='.$rec_id.' and rl_DetailTypeID>0 '
    .'  and (src.rec_ID = rl_SourceID) and (src.rec_FlagTemporary=0) '
    .predicateId('src.rec_RecTypeID', $rtfilter, SQL_AND)
    .predicateId('rl_DetailTypeID', $ptrfilter, SQL_AND)
    .predicateRecordVisibility('src');

    $resout = array();

    $res = $mysqli->query($query);
    if($res){
        while ($row = $res->fetch_assoc()) {
            if(@$resout[$row['rl_SourceID']]){
                if(!in_array($row['rl_DetailTypeID'], $resout[$row['rl_SourceID']]['dty_IDs'])){ //rare case
                    array_push($resout[$row['rl_SourceID']]['dty_IDs'], $row['rl_DetailTypeID']);
                }
            }else{
                $resout[$row['rl_SourceID']] = array('rec_RecTypeID'=>$row['rec_RecTypeID'], 'dty_IDs'=>array($row['rl_DetailTypeID']));
            }
        }
        $res->close();
    }

    return $resout;
}

//
// get linked records for relationship record
//
function _getForwardPointers_for_relRT($rec_id, $depth){

    global $system, $mysqli, $relSrcDT, $relTrgDT, $ACCESSABLE_OWNER_IDS, $PUBONLY, $RECTYPE_FILTERS, $PTRTYPE_FILTERS;

    $rtfilter = (@$RECTYPE_FILTERS && array_key_exists($depth, $RECTYPE_FILTERS) ? $RECTYPE_FILTERS[$depth] : null);
    $ptrfilter = (@$PTRTYPE_FILTERS && array_key_exists($depth, $PTRTYPE_FILTERS) ? $PTRTYPE_FILTERS[$depth] : null);

    $query = 'SELECT dtl_RecID, dtl_Value, trg.rec_RecTypeID, dtl_DetailTypeID FROM recDetails, Records trg '
    .' where dtl_RecID='.intval($rec_id).' and (dtl_DetailTypeID='.intval($relSrcDT).' OR dtl_DetailTypeID='.intval($relTrgDT).') '
    .'  and (trg.rec_ID = dtl_Value)  and (trg.rec_FlagTemporary=0) '
    .predicateRtyDtyFilters($rtfilter, $ptrfilter, 'dtl_DetailTypeID')
    .predicateRecordVisibility();


    $resout = array();

    $res = $mysqli->query($query);
    if($res){
        while ($row = $res->fetch_assoc()) {

            if(@$resout[$row['dtl_Value']]){
                if(!in_array($row['dtl_DetailTypeID'], $resout[$row['dtl_Value']]['dty_IDs'])){ //rare case
                    array_push($resout[$row['dtl_Value']]['dty_IDs'], $row['dtl_DetailTypeID']);
                }
            }else{
                $resout[$row['dtl_Value']] = array('rec_RecTypeID'=>$row['rec_RecTypeID'], 'dty_IDs'=>array($row['dtl_DetailTypeID']));
            }
        }
        $res->close();
    }

    return $resout;

}

//
//
//
function _getForwardPointers($rec_id, $depth){

    global $system, $mysqli, $ACCESSABLE_OWNER_IDS, $PUBONLY, $RECTYPE_FILTERS, $PTRTYPE_FILTERS;

    $rtfilter = (@$RECTYPE_FILTERS && array_key_exists($depth, $RECTYPE_FILTERS) ? $RECTYPE_FILTERS[$depth] : null);
    $ptrfilter = (@$PTRTYPE_FILTERS && array_key_exists($depth, $PTRTYPE_FILTERS) ? $PTRTYPE_FILTERS[$depth] : null);

    $query = 'SELECT rl_SourceID, rl_TargetID, trg.rec_RecTypeID, rl_DetailTypeID FROM recLinks, Records trg '
    .' where rl_SourceID='.$rec_id.' and rl_DetailTypeID>0 '
    .'  and (trg.rec_ID = rl_TargetID)  and (trg.rec_FlagTemporary=0) '
    .predicateRtyDtyFilters($rtfilter, $ptrfilter, 'rl_DetailTypeID')
    .predicateRecordVisibility();

    $resout = array();

    $res = $mysqli->query($query);
    if($res){
        while ($row = $res->fetch_assoc()) {

            if(@$resout[$row['rl_TargetID']]){
                if(!in_array($row['rl_DetailTypeID'], $resout[$row['rl_TargetID']]['dty_IDs'])){ //rare case
                    array_push($resout[$row['rl_TargetID']]['dty_IDs'], $row['rl_DetailTypeID']);
                }
            }else{
                $resout[$row['rl_TargetID']] = array('rec_RecTypeID'=>$row['rec_RecTypeID'], 'dty_IDs'=>array($row['rl_DetailTypeID']));
            }
        }
        $res->close();
    }

    return $resout;
}

//
//
//
function _getRelations($rec_id, $depth){

    global $system, $mysqli, $ACCESSABLE_OWNER_IDS, $PUBONLY, $RECTYPE_FILTERS, $RELTYPE_FILTERS, $REVERSE;

    $rtfilter = (@$RECTYPE_FILTERS && array_key_exists($depth, $RECTYPE_FILTERS) ? $RECTYPE_FILTERS[$depth] : null);
    $relfilter = (@$RELTYPE_FILTERS && array_key_exists($depth, $RELTYPE_FILTERS) ? $RELTYPE_FILTERS[$depth] : null);

    //
    // find direct relations
    //
    $query = 'SELECT rl_SourceID, rl_TargetID, rl_RelationID, rl_RelationTypeID FROM Records trg, recLinks '
    .' left join Records rel on rel.rec_ID=rl_RelationID'
    .' where rl_SourceID='.intval($rec_id).' and rl_RelationTypeID>0 '
    .'  and trg.rec_ID = rl_TargetID  and (trg.rec_FlagTemporary=0) and (rel.rec_FlagTemporary=0) '
    .predicateRtyDtyFilters($rtfilter, $relfilter, 'rl_RelationTypeID')
    .predicateRecordVisibility();

    $resout = array();

    $res = $mysqli->query($query);
    if($res){
        while ($row = $res->fetch_assoc()) {
            $resout[$row['rl_RelationID']] = array('termID'=> $row['rl_RelationTypeID'], 'relatedRecordID'=>$row['rl_TargetID']);
        }
        $res->close();
    }

    if(!$REVERSE){
        return $resout;
    }

        //
        // find reverse relations
        //
        $query = 'SELECT rl_SourceID, rl_TargetID, rl_RelationID, rl_RelationTypeID FROM Records src, recLinks '
        .' left join Records rel on rel.rec_ID=rl_RelationID'
        .' where rl_TargetID='.intval($rec_id).' and rl_RelationTypeID>0 '
        .'  and src.rec_ID = rl_SourceID  and (src.rec_FlagTemporary=0)  and (rel.rec_FlagTemporary=0) '
        .predicateId('src.rec_RecTypeID', $rtfilter, SQL_AND)
        .predicateId('rl_RelationTypeID', $relfilter, SQL_AND)
        .predicateRecordVisibility('src');


        $res = $mysqli->query($query);
        if($res){
            while ($row = $res->fetch_assoc()) {
                $resout[$row['rl_RelationID']] = array('useInverse'=>true, 'termID'=> $row['rl_RelationTypeID'], 'relatedRecordID'=>$row['rl_SourceID']);
            }
            $res->close();
        }

    return $resout;
}


//----------------------------------------------------------------------------//
//  Output functions
//----------------------------------------------------------------------------//

/**
* Outputs the set of records as an xml stream or separate files per record (if $intofile set)
*
* returns array of printed out recID=>recTypeID
*
* result = array('count'=>$total_count_rows,
'offset'=>get_offset($params),
'reccount'=>count($records),
'records'=>$records))
*
*
*
* @param mixed $result
*/
function outputRecords($result) {

    global $OUTPUT_STUBS, $FRESH, $MAX_DEPTH, $intofile, $hunifile, $relRT;

    $recSet = array('count' => 0, 'relatedSet' => array());
    $rec_ids = $result['records'];

    //this feature is not used anymore 2018-06-20
    if (false && array_key_exists('expandColl', $_REQUEST)) {
        $rec_ids = expandCollections($rec_ids);//see v3 getSearchResults.php
    }

    if(!is_array($rec_ids)){
        $rec_ids = array();
    }

    set_time_limit(0);
    /*
    if(count($rec_ids)>1000){
    set_time_limit( 30 * count($rec_ids) % 1000 );
    }
    */

    $current_depth_recs_ids = $rec_ids;
    $current_depth = 0;
    $already_out = array();//result - array of all printed out records recID => recTypeID

    if(!$intofile){
        openTag('records');
    }

    $relations_rec_ids = array();//list of all relationship records

    while ($current_depth<= $MAX_DEPTH){
        $next_depth_recs_ids = array();
        $relations_rec_ids[$current_depth] = array();

        foreach ($current_depth_recs_ids as $recID) {

            // output one record - returns recTypeID and array of related records for given record
            $res = outputRecord($recID, $current_depth, $OUTPUT_STUBS);

            // close the file if using HuNI manifest + separate files output
            // The file is opened in outputRecord but left open!
            if($intofile && $hunifile){
                fclose($hunifile);
            }

            if($res){
                //output is successful

                $already_out[$recID] = $res['recTypeID'];

                $related_rec_ids = $res['related'];

                if(!empty($res['relationRecs'])){
                    $relations_rec_ids[$current_depth] = array_merge($relations_rec_ids[$current_depth], $res['relationRecs']);
                }


                //add to $next_depth_recs_ids if not already printed out or in current depth
                foreach ($related_rec_ids as $rel_recID) {
                    if (!(@$already_out[$rel_recID] ||
                    in_array($rel_recID, $current_depth_recs_ids) ||
                    in_array($rel_recID, $next_depth_recs_ids) )) {

                        array_push($next_depth_recs_ids, $rel_recID);
                    }
                }

            }elseif($intofile && file_exists(HEURIST_HML_DIR.$recID.".xml")){
                unlink(HEURIST_HML_DIR.intval($recID).".xml");
            }
        }

        unset($current_depth_recs_ids);
        $current_depth_recs_ids = $next_depth_recs_ids;
        $current_depth++;
    }//while depth

    //print out relationship records with half depth
    $current_depth = 0;
    while ($current_depth<= $MAX_DEPTH){
        foreach ($relations_rec_ids[$current_depth] as $recID) {
            if (!@$already_out[$recID]){

                $res = outputRecord($recID, $current_depth.'.5', $OUTPUT_STUBS);
                if($res){
                    $already_out[$recID] = $relRT;
                }elseif ($intofile && file_exists(HEURIST_HML_DIR.$recID.".xml")){
                    unlink(HEURIST_HML_DIR.$recID.".xml");
                }
            }
        }
        $current_depth++;
    }


    if(!$intofile){
        closeTag('records');
    }
    return $already_out;


    /* OLD VERSION pre 2016-12-05
    foreach ($rec_ids as $recID) {
    if($recID>0)
    $recSet['relatedSet'][$recID] = array('depth' => 0, 'recID' => $recID );
    }

    buildGraphStructure($rec_ids, $recSet);
    $recSet['count'] = count($recSet['relatedSet']);

    $resout = array();

    if(!$intofile){
    openTag('records', array('count' => $recSet['count']));
    }

    foreach ($recSet['relatedSet'] as $recID => $recInfo) {
    if (intval($recInfo['depth']) <= $MAX_DEPTH) {

    // output one record - returns rec_RecTypeID for given record
    $res = outputRecord($recInfo, $recSet['relatedSet'], $OUTPUT_STUBS);

    // close the file if using HuNI manifest + separate files output
    // The file is opened in outputRecord but left open!
    if($intofile && $hunifile){
    fclose($hunifile);
    }

    if($res){
    $resout[$recID] = $res;
    }elseif($intofile && file_exists(HEURIST_HML_DIR.$recID.".xml")){
    unlink(HEURIST_HML_DIR.$record['rec_ID'].".xml");
    }
    }
    }

    if(!$intofile){
    closeTag('records');
    }

    return $resout;
    */

}


//
//returns recTypeID and array of related records for given record
//
// $parentID - NOT USED
function outputRecord($recID, $depth, $outputStub = false, $parentID = null){


    global $system, $RTN, $DTN, $INV, $TL, $RQS, $WGN, $UGN, $MAX_DEPTH, $WOOT, $USEXINCLUDELEVEL, $already_out,
    $RECTYPE_FILTERS, $SUPRESS_LOOPBACKS, $relRT, $relTrgDT, $relTypDT, $relSrcDT, $selectedIDs, $intofile, $hunifile, $dbID,
    $EXPAND_REV_PTR, $REVERSE, $NO_RELATIONSHIPS, $rectype_templates, $human_readable_names;

    $hunifile = null;

    $recID = intval($recID);

    if($rectype_templates){
        $record = recordTemplateByRecTypeID($system, $recID);//see recordSearch.php
    }else{
        $record = recordSearchByID($system, $recID);//see recordSearch.php
    }

    $filter = (array_key_exists($depth, $RECTYPE_FILTERS) ? $RECTYPE_FILTERS[$depth] : null);

    if (isset($filter) && !in_array($record['rec_RecTypeID'], $filter)) {
        if ($record['rec_RecTypeID'] != $relRT) { //not a relationship rectype
            if ($depth > 0) {
                if ($outputStub) {
                    outputRecordStub($record);
                } else {
                    output( $record['rec_ID'] );
                }
            }
            return false;
        }
    }

    $resout = array('recTypeID'=>$record['rec_RecTypeID'], 'related'=>array(), 'relationRecs'=>array());

    // using separare files per record
    // TODO: no error checking on success so silent failure if directory non-writable
    // beware: File is closed in outputRecords function
    $recAttr = array();

    if($intofile){
        $hunifile = fopen( HEURIST_HML_DIR.$record['rec_ID'].".xml", 'w');
        output( XML_HEADER."\n" );

        //add attributes
        $recAttr['xmlns'] = 'https://heuristnetwork.org';
        $recAttr['xmlns:xsi'] = 'https://www.w3.org/2001/XMLSchema-instance';
        $recAttr['xsi:schemaLocation'] = 'https://heuristnetwork.org/documentation_and_templates/scheme_record.xsd';
    }

    if(!$rectype_templates){
        $recAttr['visibility'] = ($record['rec_NonOwnerVisibility'] ? $record['rec_NonOwnerVisibility'] : 'viewable');
        $recAttr['visnote'] = ($record['rec_NonOwnerVisibility']=='hidden' ? 'owner group only'
            : (($record['rec_NonOwnerVisibility']=='public') ? 'no login required' : 'logged in users') );
        $recAttr['selected'] = (in_array($record['rec_ID'], $selectedIDs) ? 'yes' : 'no');
        $recAttr['depth'] = $depth;
    }else{
        output("<!--\n".$RTN[$record['rec_RecTypeID']]."\n-->\n");
    }

    openTag('record', $recAttr);

    if(!$rectype_templates && $intofile){
        if (isset($dbID) && ($dbID != 0)) {
            output( "<dbID>".$dbID."</dbID>\n");
        } else {
            output( "<dbID>0</dbID>\n");// unregistered database
        }
    }

    if (array_key_exists('error', $record)) {
        makeTag('error', null, $record['error']);
        closeTag('record');
        return false;
    }

    if ($depth > $USEXINCLUDELEVEL) {
        outputXInclude($record);
        closeTag('record');
        return $record['rec_RecTypeID'];
    }

    $conceptID = ConceptCode::getRecTypeConceptID($record['rec_RecTypeID']);

    if ($rectype_templates){
        output('<!-- Specify the entity identifier in the source database (numeric or alphanumeric) if entity may be the target '
            ."of a record pointer field, including the target record pointer of a  relationship record.-->\n");
    }

    makeTag('id', null, $record['rec_ID']);

    if ($rectype_templates){
        output("<!-- type specifies the record (entity) type of the record -->\n");
        makeTag('type', array('conceptID' => $conceptID), $RTN[$record['rec_RecTypeID']]);
    }else{
        makeTag('type', array('id' => $record['rec_RecTypeID'],
            'conceptID' => $conceptID), $RTN[$record['rec_RecTypeID']]);
    }


    if ($rectype_templates || $record['rec_URL']) {
        makeTag('url', null, $record['rec_URL']);
    }

    if ($rectype_templates || $record['rec_ScratchPad']) {
        makeTag('notes', null, replaceIllegalChars($record['rec_ScratchPad']));
    }

    if (!$rectype_templates){
        makeTag('citeAs', null, HEURIST_BASE_URL.'?recID='.$record['rec_ID'].'&db='.HEURIST_DBNAME);
        makeTag('title', null, $record['rec_Title']);
        if(@$record['rec_Added']) {makeTag('added', null, $record['rec_Added']);}
        if(@$record['rec_Modified']) {makeTag('modified', null, $record['rec_Modified']);}

        // saw FIXME  - need to output groups only
        if (array_key_exists($record['rec_OwnerUGrpID'], $WGN) || array_key_exists($record['rec_OwnerUGrpID'], $UGN)) {
            makeTag('workgroup', array('id' => $record['rec_OwnerUGrpID']), $record['rec_OwnerUGrpID'] > 0 ? (array_key_exists($record['rec_OwnerUGrpID'], $WGN) ? $WGN[$record['rec_OwnerUGrpID']] : (array_key_exists($record['rec_OwnerUGrpID'], $UGN) ? $UGN[$record['rec_OwnerUGrpID']] : 'Unknown')) : 'public');
        }
    }

    foreach ($record['details'] as $dt => $details) {
        foreach ($details as $value) {
            if(!$outputStub){
                outputDetail($dt, $value, $record['rec_RecTypeID'], $depth, $outputStub);
            }

        }
    }
    /* woot is disabled
    if ($WOOT) {
    $result = loadWoot(array('title' => 'record:' . $record['rec_ID']));
    if ($result['success'] && is_numeric($result['woot']['id']) && count($result['woot']['chunks']) > 0) {
    openTag('woot', array('title' => 'record:' . $record['rec_ID']));
    openCDATA();
    foreach ($result['woot']['chunks'] as $chunk) {
    $text = preg_replace("/&nbsp;/", " ", $chunk['text']);
    output( replaceIllegalChars($text) . "\n" );
    }
    closeCDATA();
    closeTag('woot');
    }
    }
    */


    if(!$rectype_templates && strpos($depth, '.')===false){

        //artem - find reverse pointers dynamically
        if($REVERSE){
            $relations = _getReversePointers($recID, $depth+1);
            foreach ($relations as $rec_id => $rels) {
                $linkedRecType = $rels['rec_RecTypeID'];
                foreach ($rels['dty_IDs'] as $dtID) {
                    $attrs = array('id' => $dtID, 'conceptID' => ConceptCode::getDetailTypeConceptID($dtID));
                    if($human_readable_names){
                        $attrs['basename'] = $DTN[$dtID];
                        if (array_key_exists($dtID, $RQS[$linkedRecType])) {
                            $attrs['name'] = $RQS[$linkedRecType][$dtID];
                        }
                    }
                    makeTag('reversePointer', $attrs, $rec_id);
                }
            }
            if($EXPAND_REV_PTR && $depth<$MAX_DEPTH){
                $resout['related'] = array_merge($resout['related'], array_keys($relations));
            }
        }

        if($depth<$MAX_DEPTH){
            if($record['rec_RecTypeID']==$relRT){
                $relations = _getForwardPointers_for_relRT($recID, $depth+1);
            }else{
                $relations = _getForwardPointers($recID, $depth+1);
            }
            $resout['related'] = array_merge($resout['related'], array_keys($relations));
        }

        if(!$NO_RELATIONSHIPS){

            $relations = _getRelations($recID, $depth+1);

            foreach ($relations as $relRec_id => $attrs) {

                $trmID = $attrs['termID'];

                $attrs['term'] = $TL[$trmID]['trm_Label'];
                $attrs['termConceptID'] = ConceptCode::getTermConceptID($trmID);
                if ($TL[$trmID]['trm_Code']) {
                    $attrs['code'] = $TL[$trmID]['trm_Code'];
                }
                
                if (array_key_exists($trmID, $INV) && $INV[$trmID]) {
                    $attrs['inverse'] = $TL[$INV[$trmID]]['trm_Label'];
                    $attrs['invTermID'] = $INV[$trmID];
                    $attrs['invTermConceptID'] = ConceptCode::getTermConceptID($INV[$trmID]);
                }
                array_push($resout['related'], $attrs['relatedRecordID']);
                array_push($resout['relationRecs'], $relRec_id);

                makeTag('relationship', $attrs, $relRec_id);
            }

        }

    }
    closeTag('record');

    return $resout;
} //outputRecord


function outputXInclude($record) {
    global $RTN, $dbID;
    $recID = $record['rec_ID'];
    $outputFilename = "" . $dbID . "-" . $recID . ".hml";
    openTag('xi:include', array('href' => "" . $outputFilename . "#xpointer(//hml/records/record[id=$recID]/*)"));
    openTag('xi:fallback');
    makeTag('id', null, $recID);
    $type = $record['rec_RecTypeID'];
    makeTag('type', array('id' => $type, 'conceptID' => ConceptCode::getRecTypeConceptID($type)), $RTN[$type]);
    $title = $record['rec_Title'];
    makeTag('title', null, "Record Not Available");
    makeTag('notavailable', null, null);
    closeTag('xi:fallback');
    closeTag('xi:include');
}

function outputRecordStub($recordStub) {
    global $RTN;
    openTag('record', array('isStub' => 1));
    makeTag('id', null, array_key_exists('id', $recordStub) ? $recordStub['id'] : $recordStub['rec_ID']);
    $type = array_key_exists('type', $recordStub) ? $recordStub['type'] : $recordStub['rec_RecTypeID'];
    makeTag('type', array('id' => $type, 'conceptID' => ConceptCode::getRecTypeConceptID($type)), $RTN[$type]);
    $title = array_key_exists('title', $recordStub) ? $recordStub['title'] : $recordStub['rec_Title'];
    makeTag('title', null, $title);
    closeTag('record');
}


function makeFileContentNode($file) {

    if (@$file['fxm_MimeType'] !== "application/xml") {
        return;
    }

        $filename = resolveFilePath($file['fullPath']);
        if ( !($file['ulf_OrigFileName'] == ULF_REMOTE || strpos($file['ulf_OrigFileName'],ULF_IIIF)===0 ) && file_exists($filename)) { //@todo check preferred source

            $src = $filename;
            $xml = simplexml_load_file( $filename );
            if ($xml) {
                $xml = $xml->asXML();
            }
        } else {
            $src = $file['URL'];
            $xml = loadRemoteURLContent($file['URL']);//include remote xml into hml output
        }

        if (!$xml) {
            makeTag('error', null, ' Error while attemping to read '.$src);
            return;
        }

        //embedding so remove any xml element
        $content = preg_replace("/^\<\?xml[^\?]+\?\>/", "", $xml);

        //embedding so remove any DOCTYPE  TODO saw need to validate first then this is fine. also perhaps attribute with type and ID
        $content = preg_replace("/\<\!DOCTYPE[^\[]+\s*\[\s*(?:(?:\<--)?\s*\<[^\>]+\>\s*(?:--\>)?)*\s*\]\>/", "", $content, 1);
        if (preg_match("/\<\!DOCTYPE/", $content)) {
            $content = preg_replace("/\<\!DOCTYPE[^\>]+\>/", "", $content, 1);
        }

        $parser = xml_parser_create();
        $ret = xml_parse_into_struct($parser, $content, $vals, $index);
        if ($ret == 0) {
            makeTag('error', null, "Invalid XML - ".xml_error_string(xml_get_error_code($parser)));
            xml_parser_free($parser);

        }elseif ($content) {
            makeTag('content', array("type" => "xml"), $content, true, false);
        }
}


//
//
//
function outputDetail($dt, $value, $rt, $depth = 0, $outputStub) {

    global $system,$DTN, $DTT, $TL, $RQS, $INV, $GEO_TYPES, $MAX_DEPTH, $INCLUDE_FILE_CONTENT, $SUPRESS_LOOPBACKS, $relTypDT,
    $rectype_templates, $human_readable_names;

    $attrs = array('conceptID' => ConceptCode::getDetailTypeConceptID($dt));

    if($human_readable_names){
        if (array_key_exists($rt, $RQS) && array_key_exists($dt, $RQS[$rt])) {
            $attrs['name'] = $RQS[$rt][$dt];
        }

        if($rectype_templates){
            if(!@$attrs['name'] && array_key_exists($dt, $DTN)){
                $attrs['name'] = $DTN[$dt];
            }
        }else {
            $attrs['id'] = $dt;
            if (array_key_exists($dt, $DTN)) {
                $attrs['basename'] = $DTN[$dt];
            }
        }
    }

    if ($dt === $relTypDT && array_key_exists($value, $INV) && $INV[$value] && array_key_exists($INV[$value], $TL)) { //saw Enum change
        $attrs['inverse'] = $TL[$INV[$value]]['trm_Label'];
        $attrs['invTermConceptID'] = ConceptCode::getTermConceptID($INV[$value]);
    }

    if (is_array($value)) {
        if (array_key_exists('id', $value)) {
            // record pointer
            $attrs['isRecordPointer'] = "true";
            if ($MAX_DEPTH == 0 && $outputStub) {
                openTag('detail', $attrs);

                $recinfo = recordSearchByID($system, $value['id']);//recordSearch.php
                outputRecordStub($recinfo);
                closeTag('detail');
            } else {
                makeTag('detail', $attrs, $value['id']);
            }
        } elseif (array_key_exists('file', $value)) {
            $file = $value['file'];

            $external_url = @$file['ulf_ExternalFileReference'];//ulf_ExternalFileReference
            $file_nonce = @$file['ulf_ObfuscatedFileID'];

            $file_URL   = HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&file='.$file_nonce; //download
            $file['URL'] = $external_url?$external_url:$file_URL;

            //including resources disabled since 2016-12-13
            if (false && @$_REQUEST['includeresources'] == '1' && @$_REQUEST['mode'] == '1') {

                $filename = resolveFilePath($file['fullPath']);

                if (file_exists($filename)) {

                    $file['URL'] = $filename; //relative path to db root

                    //if path is relative then we copy file
                    if(@$file['ulf_FilePath']==null || $file['ulf_FilePath']=='' || substr($file['ulf_FilePath'],1)!='/'){



                        //copy file and create required folders
                        chdir(HEURIST_FILESTORE_DIR);// relatively db root
                        $fpath = realpath($file['fullPath']);


                        //ARTEM 2016-12-13 This recurse_copy - since ti copy everything in backup recursively!!!!
                        //moreover it is already done in exportMyDataPopup


                    }else{
                        //otherwise skip copy and use downloadURL

                    }

                    /* this code is not use anymore - we copy the entire file_uploads folder
                    // backup file into backup/user folder
                    $folder = HEURIST_FILESTORE_DIR . DIR_BACKUP . get_user_username() . "/resources/";

                    if(!file_exists($folder) && !mkdir($folder, 0777, true)){
                    print "<p class='error'>'Failed to create folder for file resources: ".$folder.'</p>';
                    break;
                    }

                    $path_parts = pathinfo($file['fullpath']);
                    $file['URL'] = $path_parts['basename'];
                    $filename_bk = $folder . $file['URL'];
                    copy($file['fullpath'], $filename_bk);
                    $file['URL'] = 'resources/'.$file['URL'];
                    */
                }
            }
            openTag('detail', $attrs);
            openTag('file');

            if($rectype_templates){
                makeTag('url', null, 'FILE_OR_URL');
                makeTag('mimeType', null, 'TEXT');
                makeTag('description', null, 'MEMO_TEXT');
                makeTag('origName', null, 'TEXT');

            }else{
                makeTag('id', null, $file['ulf_ID']);
                makeTag('nonce', null, $file['ulf_ObfuscatedFileID']);
                makeTag('origName', null, $file['ulf_OrigFileName']);
                if (@$file['fxm_MimeType']) {
                    makeTag('mimeType', null, $file['fxm_MimeType']);
                }
                if (@$file['ulf_FileSizeKB']) {
                    makeTag('fileSize', array('units' => 'kB'), $file['ulf_FileSizeKB']);
                }
                if (@$file['ulf_Added']) {
                    makeTag('date', null, $file['ulf_Added']);
                }
                if (@$file['ulf_Description']) {
                    makeTag('description', null, $file['ulf_Description']);
                }
                if (@$file['URL']) {
                    makeTag('url', null, $file['URL']);
                }
                if (@$file['thumbURL']) { //not used
                    makeTag('thumbURL', null, $file['thumbURL']);
                }
                if ($INCLUDE_FILE_CONTENT !== false && $INCLUDE_FILE_CONTENT >= $depth) {
                    makeFileContentNode($file);
                }
            }
            closeTag('file');
            closeTag('detail');
        } elseif (array_key_exists('geo', $value)) {
            openTag('detail', $attrs);
            openTag('geo');
            if(!$rectype_templates) {makeTag('type', null, $GEO_TYPES[$value['geo']['type']]);}
            makeTag('wkt', null, $value['geo']['wkt']);
            closeTag('geo');
            closeTag('detail');
        }
    } elseif($DTT[$dt] === 'date') {


            $dt = new Temporal($value);
            if($dt->isValidSimple()){

                if($rectype_templates){
                    makeTag('detail', $attrs, $value);
                }else{
                    openTag('detail', $attrs);
                    outputDateDetail($attrs, $value);
                    closeTag('detail');
                }

            }else{
                $temporalStr = $dt->toPlain();

                openTag('detail', $attrs);
                    openTag('raw');
                    output( $value );
                    closeTag('raw');
                    outputTemporalDetail($attrs, $temporalStr);
                closeTag('detail');

            }

/* OLD VERSION
        if (strpos($value, "|") === false) {
            if($rectype_templates){
                makeTag('detail', $attrs, $value);
            }else{
                openTag('detail', $attrs);
                outputDateDetail($attrs, $value);
                closeTag('detail');
            }
        } else {
            openTag('detail', $attrs);
            outputTemporalDetail($attrs, $value);
            closeTag('detail');
        }
*/

    } elseif($DTT[$dt] === 'resource') {
        $attrs['isRecordPointer'] = "true";
        if ($MAX_DEPTH == 0 && $outputStub) {
            openTag('detail', $attrs);
            $recinfo = recordSearchByID($system, $value['id']);//recordSearch.php
            outputRecordStub($recinfo);
            closeTag('detail');
        } else {
            makeTag('detail', $attrs, $value['id']);
        }
    } elseif ($DTT[$dt] === 'enum' || $DTT[$dt] === 'relationtype') {
        if($human_readable_names){
            $attrs['termID'] = $value;
        }
        if($rectype_templates){
            makeTag('detail', $attrs, null);
        }else{
            if( array_key_exists($value, $TL) ){
                $attrs['termConceptID'] =  ConceptCode::getTermConceptID($value);
                if ($human_readable_names && @$TL[$value]['trm_ParentTermID']) {
                    $attrs['ParentTerm'] = $TL[$TL[$value]['trm_ParentTermID']]['trm_Label'];
                }
            }
            makeTag('detail', $attrs, htmlspecialchars($TL[$value]['trm_Label']));
        }

    } else {
        makeTag('detail', $attrs, replaceIllegalChars($value));
    }
}

$typeDict = array("s" => "Simple Date", "c" => "C14 Date", "f" => "Approximate Date", "p" => "Date Range", "d" => "Duration");
$fieldsDict = array("VER" => "Version Number", "TYP" => "Temporal Type Code", "PRF" => "Probability Profile", "SPF" => "Start Profile", "EPF" => "End Profile", "CAL" => "Calibrated", "COD" => "Laboratory Code", "DET" => "Determination Type", "COM" => "Comment", "EGP" => "Egyptian Date");
$determinationCodes = array(0 => "Unknown", 1 => "Attested", 2 => "Conjecture", 3 => "Measurement");
$profileCodes = array(0 => "Flat", 1 => "Central", 2 => "Slow Start", 3 => "Slow Finish");
$tDateDict = array("DAT" => "ISO DateTime", "BPD" => "Before Present (1950) Date", "BCE" => "Before Current Era", "TPQ" => "Terminus Post Quem", "TAQ" => "Terminus Ante Quem", "PDB" => "Probable begin", "PDE" => "Probable end", "SRT" => "Sortby Date");
$tDurationDict = array("DUR" => "Simple Duration", "RNG" => "Range", "DEV" => "Standard Deviation", "DVP" => "Deviation Positive", "DVN" => "Deviation Negative", "ERR" => "Error Margin");



function outputTemporalDetail($attrs, $value) {
    global $typeDict, $fieldsDict, $determinationCodes, $profileCodes, $tDateDict, $tDurationDict;

        $temporalStr = substr_replace($value, "", 0, 1);// remove first verticle bar
        $props = explode("|", $temporalStr);
        $properties = array();
        foreach ($props as $prop) {
            list($tag, $val) = explode("=", $prop);
            $properties[$tag] = $val;
        }

        openTag('temporal', array("version" => $properties['VER'], "type" => $typeDict[$properties['TYP']]));
        unset($properties['VER']);
        unset($properties['TYP']);
        foreach ($properties as $tag => $val) {
            if (array_key_exists($tag, $fieldsDict)) { //simple property
                openTag('property', array('type' => $tag, 'name' => $fieldsDict[$tag]));
                switch ($tag) {
                    case "DET":
                        output( $determinationCodes[$val] );
                        break;
                    case "PRF":
                    case "SPF":
                    case "EPF":
                        output( $profileCodes[$val] );
                        break;
                    default:
                        output( $val );
                }
                closeTag('property');
            } elseif (array_key_exists($tag, $tDateDict)) {
                openTag('date', array('type' => $tag, 'name' => $tDateDict[$tag]));
                outputTDateDetail(null, $val);
                closeTag('date');
            } elseif (array_key_exists($tag, $tDurationDict)) {
                openTag('duration', array('type' => $tag, 'name' => $tDurationDict[$tag]));
                outputDurationDetail(null, $val);
                closeTag('duration');
            }
        }
        closeTag('temporal');

}


function outputDateDetail($attrs, $value) {
    makeTag('raw', null, $value);
    if (preg_match('/^\\s*-?(\\d+)\\s*$/', $value, $matches)) { // year only
        makeTag('year', null, $matches[1]);
    } else {
        $date = strtotime($value);
        if ($date) {
            makeTag('year', null, date('Y', $date));
            makeTag('month', null, date('n', $date));
            makeTag('day', null, date('j', $date));
            if (preg_match("![ T]!", $value)) { // looks like there's a time
                makeTag('hour', null, date('H', $date));
                makeTag('minutes', null, date('i', $date));
                makeTag('seconds', null, date('s', $date));
            }
        } else {
            // cases that strtotime doesn't catch
            if (preg_match('/^([+-]?\d\d\d\d+)-(\d\d)$/', $value, $matches)) {
                // e.g. MMMM-DD
                makeTag('year', null, intval($matches[1]));
                makeTag('month', null, intval($matches[2]));
            } else {
                @list($date, $time) = preg_split("![ T]!", $value);
                @list($y, $m, $d) = array_map("intval", preg_split(REGEX_SPLIT_DATE, $date));
                if (!(1 <= $m && $m <= 12 && 1 <= $d && $d <= 31)) {
                    @list($d, $m, $y) = array_map("intval", preg_split(REGEX_SPLIT_DATE, $date));
                }
                if (!(1 <= $m && $m <= 12 && 1 <= $d && $d <= 31)) {
                    @list($m, $d, $y) = array_map("intval", preg_split(REGEX_SPLIT_DATE, $date));
                }
                if (1 <= $m && $m <= 12 && 1 <= $d && $d <= 31) {
                    makeTag('year', null, $y);
                    makeTag('month', null, $m);
                    makeTag('day', null, $d);
                }
                @list($h, $m, $s) = array_map("intval", preg_split("![-:]!", $time));
                if (0 <= $h && $h <= 23) {
                    makeTag('hour', null, $h);
                }
                if (0 <= $m && $m <= 59) {
                    makeTag('minutes', null, $m);
                }
                if (0 <= $s && $s <= 59) {
                    makeTag('seconds', null, $s);
                }
            }
        }
    }
}



function outputTDateDetail($attrs, $value) {
    makeTag('raw', null, $value);
    if (preg_match('/^([^T\s]*)(?:[T\s+](\S*))?$/', $value, $matches)) { // valid ISO Duration split into date and time
        $date = @$matches[1];
        $time = @$matches[2];
        if (!$time && preg_match('/:\./', $date)) {
            $time = $date;
            $date = null;
        }
        if ($date) {
            preg_match('/^(?:(\d\d\d\d)[-\/]?)?(?:(1[012]|0[23]|[23](?!\d)|0?1(?!\d)|0?[4-9](?!\d))[-\/]?)?(?:([12]\d|3[01]|0?[1-9]))?\s*$/', $date, $matches);
            if (@$matches[1]) {makeTag('year', null, $matches[1]);}
            if (@$matches[2]) {makeTag('month', null, $matches[2]);}
            if (@$matches[3]) {makeTag('day', null, $matches[3]);}
        }
        if ($time) {
                         // hours                                 minutes                   seconds


            preg_match('/([0-1]?\d|2[0-3])?(?::([0-5]?\d))?(?:[:\.]([0-5]?\d))?/', $time, $matches);

            if (@$matches[1]) {makeTag('hour', null, $matches[1]);}
            if (@$matches[2]) {makeTag('minutes', null, $matches[2]);}
            if (@$matches[3]) {makeTag('seconds', null, $matches[3]);}
        }
    }
}



function outputDurationDetail($attrs, $value) {
    makeTag('raw', null, $value);
    if (preg_match('/^P([^T]*)T?(.*)$/', $value, $matches)) { // valid ISO Duration split into date and time
        $date = @$matches[1];
        $time = @$matches[2];
        if ($date) {
            if (preg_match('/[YMD]/', $date)) { //char separated version 6Y5M8D
                preg_match('/(?:(\d+)Y)?(?:(\d|0\d|1[012])M)?(?:(0?[1-9]|[12]\d|3[01])D)?/', $date, $matches);
            } else { //delimited version  0004-12-06
                preg_match('/^(?:(\d\d\d\d)[-\/]?)?(?:(1[012]|0[23]|[23](?!\d)|0?1(?!\d)|0?[4-9](?!\d))[-\/]?)?(?:([12]\d|3[01]|0?[1-9]))?\s*$/', $date, $matches);
            }
            if (@$matches[1]) {makeTag('year', null, intval($matches[1]));}
            if (@$matches[2]) {makeTag('month', null, intval($matches[2]));}
            if (@$matches[3]) {makeTag('day', null, intval($matches[3]));}
        }
        if ($time) {
            if (preg_match('/[HMS]/', $time)) { //char separated version 6H5M8S
                preg_match('/(?:(0?[1-9]|1\d|2[0-3])H)?(?:(0?[1-9]|[0-5]\d)M)?(?:(0?[1-9]|[0-5]\d)S)?/', $time, $matches);
            } else { //delimited version  23:59:59

                preg_match('/([0-1]?\d|2[0-3])?(?::([0-5]?\d))?(?:[:\.]([0-5]?\d))?/', $time, $matches);
            }
            if (@$matches[1]) {makeTag('hour', null, intval($matches[1]));}
            if (@$matches[2]) {makeTag('minutes', null, intval($matches[2]));}
            if (@$matches[3]) {makeTag('seconds', null, intval($matches[3]));}
        }
    }
}

$invalidChars = array(chr(0), chr(1), chr(2), chr(3), chr(4), chr(5), chr(6), chr(7), chr(8), chr(11), chr(12), chr(14), chr(15), chr(16), chr(17), chr(18), chr(19), chr(20), chr(21), chr(22), chr(23), chr(24), chr(25), chr(26), chr(27), chr(28), chr(29), chr(30), chr(31));// invalid chars that need to be stripped from the data.
$replacements = array("[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", "[?]", " ", "[?]", "[?]", "[?]", "[?]", "[?]");



function replaceIllegalChars($text) {
    global $invalidChars, $replacements;
    return str_replace($invalidChars, $replacements, $text);
}

function check($text) {
    global $invalidChars;
    foreach ($invalidChars as $charCode) {

        if (strpos($text, $charCode)) {
            return false;
        }
    }
    return true;
}

//----------------------------------------------------------------------------//
//  Turn off output buffering
//----------------------------------------------------------------------------//

if (!@$ARGV) {
    if(function_exists('apache_setenv')){
        @apache_setenv('no-gzip', 1);
    }
}
if (@$_REQUEST['mode'] != '1') { //not include
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');
    for ($i = 0;$i < ob_get_level();$i++) {
        ob_end_flush();
    }
    ob_implicit_flush(1);
}
//----------------------------------------------------------------------------//
//  Output
//----------------------------------------------------------------------------//

$hmlAttrs = array();

$hmlAttrs['xmlns'] = 'https://heuristnetwork.org';
$hmlAttrs['xmlns:xsi'] = 'https://www.w3.org/2001/XMLSchema-instance';
$hmlAttrs['xsi:schemaLocation'] = 'https://heuristnetwork.org/documentation_and_templates/scheme_hml.xsd';

if ($USEXINCLUDE) {
    $hmlAttrs['xmlns:xi'] = 'https://www.w3.org/2001/XInclude';
}

/* DISABLED  2023-12-26
if (@$_REQUEST['filename']) {
    $hmlAttrs['filename'] = $_REQUEST['filename'];
}
*/

$params = array();
foreach($_REQUEST as $key=>$value) { $params[$key] = filter_var($value, FILTER_SANITIZE_STRING);}

$params['q'] = @$_REQUEST['q'];
$params['detail'] = 'ids';// return ids only

$params['publiconly'] =  $PUBONLY?1:0;
$params['needall'] = 1; //to avoid search_detail_limit limit
$error_msg  = null;

if($rectype_templates){
    $result = dbs_GetRectypeIDs($system->getMysqli(), $rectype_templates);
    $result['records'] = $result;
    $result['reccount'] = count($result['records']);
}else{
    $result = recordSearch($system, $params);//see recordSearch.php

    if(@$result['status']==HEURIST_OK){
        $result = $result['data'];
    }else{
        $error_msg = @$result['message'];
        $error_msg = $system->getError();
        if(!empty($error_msg)){
            $error_msg = $error_msg[0]['message'];
        }else{
            $error_msg = 'Unknown error on record search. Search parmeters: '.print_r($params,true);
        }

    }
}

$query_attrs = array_intersect_key($_REQUEST, array('q' => 1, 'w' => 1, 'pubonly' => 1, 'hinclude' => 1, 'depth' => 1, 'sid' => 1, 'label' => 1, 'f' => 1, 'limit' => 1, 'offset' => 1, 'db' => 1, 'expandColl' => 1, 'recID' => 1, 'stub' => 1, 'woot' => 1, 'fc' => 1, 'slb' => 1, 'fc' => 1, 'slb' => 1, 'selids' => 1, 'layout' => 1, 'rtfilters' => 1, 'relfilters' => 1, 'ptrfilters' => 1));

/*if(@$_REQUEST['offset']){
$offset = intval($_REQUEST['offset']);
}else{
$offset = 0;
}
$last_limit = null;
if(@$_REQUEST['limit']){
$limit = intval($_REQUEST['limit']);
if($limit>1000){
$last_limit = $limit+$offset;
$limit = 1000;
}
}*/

if($intofile){ // flags HuNI manifest + separate files per record

    if ($error_msg) {
        print "Error: ".htmlspecialchars($error_msg);
    }else{

        ?>
<!DOCTYPE html>
<html lang="en">
        <head>
            <meta name="robots" content="noindex,nofollow">
            <title>The HuNI project</title>
            <style>
                * {
                    font-family: Helvetica,Arial,sans-serif;
                    font-size: 12px;
                }
            </style>
        </head>
        <body>
        <table role="presentation" style='width:500px;'>
            <tr>
                <td style='width:150px;'><img alt src='../../hclient/assets/branding/logo_huni.png'></td>
                <td><h2 style="padding-top:1em;font-size: 16px;">The HuNI Project</h2></td>
            </tr>
            <tr>
                <td colspan="2">

                    <p style="margin-top: 1em;">
                        The HuNI project (Humanities Networked Infrastructure, <a href="https://huni.net.au" target="_blank" rel="noopener">https://huni.net.au</a>)
                        funded as a Virtual Laboratory by the Australian National eResearch Collaboration Tools and Resources Project
                        (<a href="https://nectar.org.au">NeCTAR</a>), has built a central searchable aggregate of metadata harvested from 28 Australian
                        cultural datasets, including AusStage, AusLit, Dictionary of Art and Architecture Online, Australian Dictionary of Biography,
                        Circus Oz and Paradisec</p>

                    <p style="margin-top: 1em;">
                        Heurist provides a database-on-demand function for HuNI. Databases created using the HuNI Core Metadata template can be immediately
                        exported to a HuNI harvestable format. Other databases can be made available for harvesting by mapping fields to the HuNI Core naming conventions.
                    </p>

                </td>
            </tr>
        </table>
        <?php

        // remove all files form  HEURIST_HML_DIR
        folderDelete(HEURIST_HML_DIR, false);
        allowWebAccessForForlder( HEURIST_HML_DIR );


        // write out all records as separate files
        $resout = outputRecords($result);

        if(empty($resout)){
            print '<h3>There are no results to export</h3>';
            print '</body></html>';
        }else{


            // create HuNI manifest
            $huni_resources = fopen( HEURIST_HML_DIR."resources.xml","w");
            fwrite( $huni_resources, XML_HEADER."\n" );
            fwrite( $huni_resources, '<resources recordCount="'.count($resout)."\">\n");

            // dbID set at start of script
            if (isset($dbID) && ($dbID != 0)) {
                fwrite( $huni_resources, "<dbID>".$dbID."</dbID>\n");
            }
            else
            {
                fwrite( $huni_resources, "<dbID>0</dbID>\n");// unregistered indicated by 0
            }

            // add each output file to the manifest
            foreach ($resout as $recID => $recTypeID) {

                $resfile = HEURIST_HML_DIR.$recID.".xml";

                if(file_exists($resfile)){
                    $conceptID =  ConceptCode::getRecTypeConceptID($recTypeID);
                    fwrite( $huni_resources, "<record>\n");
                    fwrite( $huni_resources, "<name>".$recID.".xml</name>\n");
                    fwrite( $huni_resources, "<hash>".md5_file($resfile)."</hash>\n");
                    fwrite( $huni_resources, "<RTConceptID>".$conceptID."</RTConceptID>\n");
                    fwrite( $huni_resources, "</record>\n");
                }
            }

            fwrite( $huni_resources, "</resources>");
            fclose( $huni_resources );


            print '<h3>Export completed</h3> Files are in <b><a href='.HEURIST_HML_URL.' target="_blank">'.HEURIST_HML_URL.'</a></b>';
            print '</body></html>';
        }
    }

}
else{ // single output stream

    openTag('hml', $hmlAttrs);

    if($rectype_templates){
        output( "<!-- for preparing an XML file
            with Heurist schema which can be imported into a Heurist database.
            \n
            The file must indicate a source database in <database id=nnnn>.
            This is added automatically when HML or this template is exported
            from a registered Heurist database (it is set to 0 if the
            database is not registered).
            \n
            In the case of data from a non-Heurist source, the file should
            indicate a database which contains definitions for all the
            record types and fields to be imported. This can either be
            the database from which this template is exported or zero or the
            target database if all the necessary record types and fields
            exist in the target (either by being imported into it or through
            cloning from a suitable source). If definitions are missing Heurist
            will update the target database structure from the indicated source
            (if specified). If required definitions cannot be obtained, it will
            report an error indicating the missing definitions.
            \n
            Values to be replaced are indicated with ALLCAPS, such as
            WKT (WellKnownText), NUMERIC, TERM, DATE etc.
            \n
            RECORD_REFERENCE may be replaced with a numeric or alphanumeric
            reference to another record, indicated by the <ID> tag.
            Note that this reference will be replaced with an automatically
            generated numeric Heurist record ID (H-ID), which will be different
            from the reference supplied. The reference supplied will be recorded
            in a field Original ID.
            \n
            If you wish to specify existing Heurist records in the target
            database as the target (value) of a Record Pointer field, specify
            their Heurist record ID (H-ID) in the form H-ID-nnnn, where nnnn
            is the H-ID of the target record in the target database. Specifying
            non-existent record IDs will throw an error. The record type of
            target records are not checked on import; pointers to records of
            the wrong type can be found later with Verify > Verify integrity.
            \n
            In the current version of HML import, you cannot import additional
            data into an existing record (this will be developed later according
            to demand - in the meantime please use CSV import to update records).
            If you use an H-ID-nnnn format specification in the <id> tag of
            a record, it will be regarded as an unknown alphanumeric identifier
            and will simply create a new record with a new H-ID.
            \n
            <url>URL</url> This record level tag specifies a special URL
            attached directly to the record which is used to hyperlink
            record lists and for which checking can be automated.
            Primarily used for internet bookmarks.
            \n
            Specify date field values in ISO format (yyyy or yyyy-mm or yyyy-mm-dd)
            \n
            termID= specifies any of the following, which are evaluated
            in order: local ID, concept code, label or standard code.
            If no match is found, the value will be added as a new term
            \n
            Relationship markers: these are indicated by SEE NOTES AT START in
            the value. Relationship markers are special fields as they contain no data;
            they are simply a marker in the data structure indicating the display
            of relationship records which satisfy particular criteria (relationship type
            and target entity type). They also trigger the creation of relationships
            with particular parameters during data entry.
            \n
            Relationships should therefore be imported by importing as records of
            type RELATIONSHIP. They will appear in the marker fields when the data is
            viewed. Leave at least one copy of each relationship marker field in the
            file as this will trigger download of the field definition if it is not in
            the target database. Only one copy of each relationship marker is needed to
            trigger the download of the definition, duplicates can be deleted if there
            is a need to limit file size.
            \n
            The XML file may (optionally) specify a Heurist database ID
            with <database id=??>. If a database ID is specified, synchronisation
            of definitions from that database will be performed before the data
            are imported. Since imported files will normally use a template for
            record types and fields exported from the target database, this is
            only useful for synchronising vocabularies and terms.
        -->\n");



    }


    /*  TODO: The schema locations are clearly rubbish
    openTag('hml', array(
    'xmlns' => '<?pho echo HEURIST_INDEX_BASE_URL;?>hml',
    'xmlns:xsi' => 'https://www.w3.org/2001/XMLSchema-instance',
    'xsi:schemaLocation' => '<?pho echo HEURIST_INDEX_BASE_URL;?>hml <?pho echo HEURIST_INDEX_BASE_URL;?>schemas/hml.xsd')
    );
    */
    makeTag('database', array('id' => $dbID ), HEURIST_DBNAME);

    if(!$rectype_templates){
        makeTag('query', $query_attrs);
        if (!empty($selectedIDs)) {
            makeTag('selectedIDs', null, join(",", $selectedIDs));
        }
        makeTag('dateStamp', null, date('c'));
    }

    if (array_key_exists('error', $result)) {
        makeTag('error', null, xmlspecialchars($result['error']));
    } else {
        if(!$rectype_templates) {makeTag('resultCount', null, @$result['reccount']>0 ? intval($result['reccount']) : " 0 ");}
        // Output all the records as XML blocks
        if (@$result['reccount'] > 0){
            $resout = outputRecords($result);
            if(!$rectype_templates) {makeTag('recordCount', null, count($resout));}
        }
    }
    closeTag('hml');

    if($output_file_fd){
        fclose ($output_file_fd);
        $output_file_name = HEURIST_FILESTORE_DIR.DIR_BACKUP.HEURIST_DBNAME.".xml";
        rename($output_file, $output_file_name);
    }

}
?>
