<?php

/**
*   Sync Heurist database with zotero group or user items
*   zotero API key in sys_SyncDefsWithDB/HEURIST_ZOTEROSYNC and mapping are specified in zoteroMap.xml
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
use hserv\structure\ConceptCode;

ini_set('max_execution_time', '0');

define('MANAGER_REQUIRED',1);
define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
require_once dirname(__FILE__).'/../../hserv/structure/search/dbsData.php';
require_once dirname(__FILE__).'/../../hserv/records/edit/recordModify.php';
require_once dirname(__FILE__).'/../../hserv/structure/import/dbsImport.php';
require_once dirname(__FILE__).'/../../external/php/phpZotero.php';

$system->defineConstants();

define('H_ID','h-id');

global $rectypes, $is_verbose, $report_log, $rep_errors_only, $dt_SourceRecordID;
global $alldettypes, $allterms, $fi_dettype, $fi_constraint, $fi_trmlabel;
global $mapping_dt, $mapping_errors, $warning_count, $transfer_errors, $successful_rows;


$dt_SourceRecordID = (defined('DT_ORIGINAL_RECORD_ID')?DT_ORIGINAL_RECORD_ID:0);
if($dt_SourceRecordID==0){ //this field is critical - need to download it from heurist core defintions database

    $isOK = false;
    $importDef = new DbsImport( $system );
    if($importDef->doPrepare(  array('defType'=>'detailtype',
    'conceptCode'=>$dtDefines['DT_ORIGINAL_RECORD_ID'] ) ))
    {
        $isOK = $importDef->doImport();
    }

    if(!$isOK){
        $system->addErrorMsg('Cannot download field "Source record" required by the function you have requested. ');
        include_once ERROR_REDIR;
        exit;
    }
    if(!$system->defineConstant('DT_ORIGINAL_RECORD_ID', true)){
        $system->addError(HEURIST_ERROR, 'Detail type "source record" id not defined');
        include_once ERROR_REDIR;
        exit;
    }

}

$HEURIST_ZOTEROSYNC = $system->settings->get('sys_SyncDefsWithDB');
/*
if($HEURIST_ZOTEROSYNC==''){
$system->addError(HEURIST_ERROR, 'Library key for Zotero synchronisation is not defined. '
.'Please configure Zotero connection in Database > Properties');
include_once ERROR_REDIR;
exit;
}
*/
$mapping_file = "zoteroMap.xml";
$fh_data = null;

if(!file_exists($mapping_file) || !is_readable($mapping_file)){
    $system->addError(HEURIST_ERROR, 'Sorry, could not find/read configuration file .../import/biblio/zoteroMap.xml '
        .'required for Zotero synchronisation - please ask your system administrator to copy it from Heurist source code');
    include_once ERROR_REDIR;
    exit;
}

$step = @$_REQUEST['step'];

$fh_data = simplexml_load_file($mapping_file);
if($fh_data==null || is_string($fh_data)){
    $system->addError(HEURIST_ERROR, 'Sorry, configuration file import/biblio/zoteroMap.xml for Zotero '
        .'synchronisation is corrupted - please ask your system administrator to update it from Heurist source code');
    include_once ERROR_REDIR;
    exit;
}
?>
<!DOCTYPE HTML>
<html lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        <title>Zotero synchronization</title>

        <?php
        includeJQuery();
        ?>

        <!-- Heurist -->
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>

        <!-- CSS -->
        <?php include_once dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php';?>

        <style type="text/css">
            .tbl-head > td {
                padding-top: 10px;
            }

            .ui-accordion-header.ui-state-active .ui-icon {
                background-image: url('https://code.jquery.com/ui/1.12.1/themes/base/images/ui-icons_444444_256x240.png') !important;
            }
        </style>

        <script>
            function __showLoading(){
                var ele = document.getElementById('divStart2');
                ele.style.display = 'none';
                ele = document.getElementById('divLoading');
                ele.style.display = 'block';
                return true;
            }
            function open_sysIdentification(){
                window.hWin.HEURIST4.ui.showEntityDialog('sysIdentification',
                    {onClose:function(){
                        location.reload();
                }});
                return false;
            }

            function showMappingReport(){

                var $report_ele = $('#mapping_report');
                if($report_ele.is(':hidden')){
                    $report_ele.show();
                    $('.report-btn').text('Hide Report');
                }else{
                    $report_ele.hide();
                    $('.report-btn').text('Show Report');
                }
            }
        </script>
    </head>

    <body class="popup" style="margin-right:30px;overflow:auto">

        <?php

        if($HEURIST_ZOTEROSYNC==''){
        ?>
            <p class="ui-state-error" style="padding:20px;text-align:center">
                Library key for Zotero synchronisation is not defined.<br><br>
                <a href="#dbprop" onclick="open_sysIdentification()">
                    Click here to edit properties which determine Zotero connection</a>
            </p>
        </body>
    </html>

    <?php
    exit;
}

// 1) load config file from import/biblio/zoteroMap.xml.
// This file maps Zotero names to Heurist codes according to context

$user_ID = null;
$group_ID = null;
$api_Key = null;
$mapping_dt = null;
$mapping_rt = array();
$warning_count = 0;
$mapping_errors = array();
$transfer_errors = array();
$successful_rows = array();
$mapping_rt_errors2 = array();
$rep_errors_only = true;

// TODO: we need a link here which opens the Properties form in the main interface, rather than the old form
// $linkToAdvancedProperties = "<a target=\"_blank\" href=\"../../admin/adminMenuStandalone.php?db="
// . HEURIST_DBNAME
// ."&mode=properties2\"Database > Prxoperties</a>";

$lib_keys = explode("|", $HEURIST_ZOTEROSYNC);

if(!$step){
    if(count($lib_keys)>1){ //select key
    ?>

        <form action="syncZotero.php" style="padding:20px;">

            <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>" />
            <input type="hidden" name="step" value="1" />
            Please select library:
            <select name="lib_key">
                <?php
                foreach($lib_keys as $idx=>$key){
                    $vals = explode(",",$key);
                    print '<option value="'.$idx.'">'.$vals[0].'</option>';
                }
                ?>
            </select>
            <input type="submit" value="Start" />
        </form>
        </body>
        </html>

<?php
        exit;
    }else{
        $lib_key_idx = 0;
        $step = "1";
    }
}else{
    $lib_key_idx = @$_REQUEST['lib_key'];
}

$key = $lib_keys[$lib_key_idx];

$vals = explode(",",$key);

$user_ID = @$vals[1];
$group_ID = @$vals[2];
$api_Key  = @$vals[3];

if($user_ID!=null) {$user_ID = trim($user_ID);}
if($group_ID!=null) {$group_ID = trim($group_ID);}
if($api_Key!=null) {$api_Key = trim($api_Key);}

$is_verbose = true;

if($is_verbose){
    $rectypes = dbs_GetRectypeStructures($system, null, 2);
    print '<div id="mapping_report" style="display: none;">';
}

$mapping_errors = [];
$transfer_errors = [];
$successful_rows = [];

// 2) verify heurist codes in mapping and create mapping array
foreach ($fh_data->children() as $f_gen){
    if($f_gen->getName()=="zTypes"){

        foreach ($f_gen->children() as $f_type){

            if($f_type->getName()=="typeMap"){
                $arr = $f_type->attributes();
                if(@$arr[H_ID]){

                    $zType = strval($arr['zType']);
                    // find record type with such code (or concept code)
                    $org_rt_id = strval($arr[H_ID]);
                    $rt_id = ConceptCode::getRecTypeLocalID($arr[H_ID]);

                    printMappingReport_rt($arr, $rt_id);

                    if($rt_id != null){

                        $mapping_dt = array();

                        foreach ($f_type->children() as $f_field){
                            if($f_field->getName()=="field"){
                                $arr = $f_field->attributes();

                                if(strval($arr['value'])=="creator"){

                                    foreach ($f_field->children() as $f_ctype){
                                        if($f_ctype->getName()=="creatorType"){
                                            $arr = $f_ctype->attributes();
                                            if(@$arr[H_ID])
                                            {
                                                addMapping($arr, $zType, $rt_id, $org_rt_id);
                                            }
                                        }
                                    }

                                }elseif(@$arr[H_ID])
                                {
                                    addMapping($arr, $zType, $rt_id, $org_rt_id);
                                }
                            }
                        }

                        if(empty($mapping_dt)){
                            array_push($mapping_rt_errors2, $zType);
                            $warning_count ++;
                        }else{
                            $mapping_dt["h3rectype"] = $rt_id;
                            $mapping_rt[$zType] = $mapping_dt;
                        }

                    }
                }
            }
        }
    }
}///foreach

if($step=="1"){  // info about current status
    // show mapping and transfer issues report, also show the success mappings+transfers
    if(!empty($mapping_rt_errors2)
    || !empty($mapping_errors)
    || !empty($transfer_errors)){

        if(!empty($mapping_errors)){
            print "<strong>Data not mapped</strong><br>";
            print "<em>The following data has not been mapped for transfer from Zotero to Heurist.<br>If you require these record types or fields to be mapped,<br>please email a list to the Heurist team (support at HeuristNetwork.org).</em><br><br>";
            print TABLE_S.implode("",$mapping_errors).TABLE_E."<br>";
        }
        if(!empty($transfer_errors)){
            print "<strong>Data not transfered</strong><br>";
            print "<em>The following fields in Zotero have been mapped into the Heurist database but will<br>"
            . "not be saved as the record type does not contain a field to hold them. If you feel that<br>"
            . "any of these fields are needed, you may add the indicated base field to the record<br>"
            . "type. Contact the Heurist team (support at HeuristNetwork.org) if you require help<br>with this.</em><br><br>";
            print TABLE_S.implode("", $transfer_errors).TABLE_E."<br>";
        }
        if(!empty($mapping_rt_errors2)){
            print "<p style='color:red'><br>No proper field mapping found for record types:";
            print "<br><br>".implode("<br>",$mapping_rt_errors2).'</p>';
        }

        print "<p style='color: red;margin-top: 0px;'>Please import them from the Heurist_Bibliographic database (# 6) using Design > Browse templates</p>";
    }

    if(!empty($successful_rows)){

        print "<div id='success-accordion'><h3><strong>Data mapped for transfer</strong></h3>";
        print DIV_S.TABLE_S.implode("", $successful_rows).TABLE_E.DIV_E."</div><br><br>";

        // Make this section an accordion (jQuery UI)
        print '<script> $("#success-accordion").accordion({collapsible: true, heightStyle: "content", active: false});';
        print '$("#success-accordion").find(".ui-accordion-content").css({background: "none", border: "none"});';
        print '$("#success-accordion").find(".ui-accordion-header").css({color: "black", "font-size": "larger", "padding-left": "0px"});';
        print '</script>';
    }
}

print DIV_E;

if($is_verbose){
    print '<div>Mapping check completed. '.$warning_count.' warnings';
    print '<button class="h3button report-btn" onclick="showMappingReport()" style="margin-left: 10px;">Show report</button>';
    print '</div><br>';
}


if( ( is_empty($group_ID) && is_empty($user_ID) ) || is_empty($api_Key) ){
    print "<div class='ui-state-error' style='padding:20px'>Current Zotero access settings incomplete: ' ".$key
    .'<br><br><a href="#" onclick="open_sysIdentification()">Click here to edit properties which determine Zotero connection</a>'
    .'</div></body></html>';
    exit;
}

$zotero = null;
$zotero = new phpZotero($api_Key);

print "<div><b>zotero has been initiated with api key [$api_Key]</b></div>";
print '<br><a href="#" onclick="open_sysIdentification()">Click here to modify properties which determine Zotero connection</a><br><br>';

/* test connection
$items = $zotero->getItemsTop($group_ID,
array('format'=>'atom', 'content'=>'none', 'start'=>'0', 'limit'=>'1', 'order'=>'dateModified', 'sort'=>'desc' ));
$code = $zotero->getResponseStatus();
print $code;

//test new library
$zotero = new \Zotero\Library('user', $user_ID, 'Library', $api_Key);
$permissions = $zotero->getKeyPermissions('','');
print json_encode($permissions, JSON_PRETTY_PRINT);

$items = $zotero->fetchItemsTop(array(
'format'=>'atom', 'content'=>'none', 'start'=>'0', 'limit'=>'10', 'order'=>'dateModified', 'sort'=>'desc' ));
//'limit'=>10, 'collectionKey'=>$collectionKey, 'order'=>'dateAdded', 'sort'=>'desc'));
*/

if($step=="1"){  //first step - info about current status

    // 1) verify connection to zotero (get total count of top-level items in zotero)
    if($group_ID){
        $items = $zotero->getItemsTop($group_ID,
            array('format'=>'atom', 'content'=>'none', 'start'=>'0', 'limit'=>'1', 'order'=>'dateModified', 'sort'=>'desc' ), "groups");
    }else{
        $items = $zotero->getItemsTop($user_ID,
            array('format'=>'atom', 'content'=>'none', 'start'=>'0', 'limit'=>'1', 'sort'=>'dateModified', 'direction'=>'desc' ));
    }

    $code = $zotero->getResponseStatus();

    if($code>499 ){
        print "<div class='ui-state-error' style='padding:20px'>Zotero Server Side Error: returns response code: $code.<br><br>"
        ."Please try this operation later.</div>";
    }elseif($code>399){
        $msg = "<div class='ui-state-error' style='padding:20px'>Error. Cannot connect to Zotero API: returns response code: $code.<br><br>";
        if($code==400 || $code==401 || $code==403){
            $msg = $msg."Please verify Zotero API key in Database > Properties - it may be incorrect or truncated.";
        }elseif($code==404 ){
            $msg = $msg."Please verify Zotero User and Group ID in Database > Properties - values may be incorrect.";
        }elseif($code==407 ){
            $msg = $msg."Proxy Authentication Required, please ask system administrator to set it";
        }
        print $msg.DIV_E;
    }elseif(!$items){
        print "<div class='ui-state-error' style='padding:20px'>Unrecognized Error: cannot connect to Zotero API: returns response code: $code</div>";
        if($code==0){
            print "<div class='ui-state-error' style='padding:20px'>Please ask your system administrator to check that the Heurist proxy settings are correctly set.</div>";
        }
    }else{


        //Responses for multi-object read requests will include a custom HTTP header, Total-Results
        $totalitems = $zotero->getTotalCount();

        print "<div>Count items in Zotero: $totalitems</div>";
        if($totalitems>0){
            print "<div id='divStart2'><br><a href='syncZotero.php?step=2&cnt=".$totalitems."&db=".HEURIST_DBNAME.
            "&lib_key=".$lib_key_idx."' onclick='__showLoading()'><button class='h3button'>Start</button></a></div><br><br>";
            print "<div id='divLoading' style='display:none;height:40px;background-color:#FFF; background-image: url(../../hclient/assets/loading-animation-white.gif);background-repeat: no-repeat;background-position:50%;'>loading...</div>";
        }
    }
}elseif($step=='2'){ //second step - sync

    $alldettypes = dbs_GetDetailTypes($system);
    $allterms = dbs_GetTerms($system);

    $fi_dettype = $alldettypes['typedefs']['fieldNamesToIndex']['dty_Type'];
    $fi_constraint = $alldettypes['typedefs']['fieldNamesToIndex']['dty_PtrTargetRectypeIDs'];
    $fi_trmlabel = $allterms['fieldNamesToIndex']['trm_Label'];

    $report_log = "";
    $unresolved_pointers = array();


    // 1) start loop: fetch items by 100
    $cnt_updated = array();
    $cnt_added = array();
    $cnt_report = array();

    //not recognized zotero entries (rectypes)
    $cnt_ignored = 0;
    $arr_ignored = array();
    $arr_ignored_by_type = array();

    //ignored zote entries since no keys are mapped
    $cnt_empty = 0;
    $arr_empty = array();

    //not recognized zotero keys (fields)
    $cnt_notmapped = 0;
    $arr_notmapped = array();

    //detail type not found in this databse
    $cnt_notfound = 0;
    $arr_notfound = array();


    $start = 0;
    $fetch = min(intval($_REQUEST['cnt']),100);
    $totalitems = intval($_REQUEST['cnt']);
    $new_recid = 0;
    $isFailure = false;

    $is_echo = false;

    $mysqli = $system->getMysqli();

    //$tmp_destination = HEURIST_SCRATCH_DIR.'zotero.xml';
    //$fd = fopen($tmp_destination, 'w');//less than 1MB in memory otherwise as temp file

    print '<br>Starting Zotero Library Sync for '. intval($totalitems) .' records...<br>';

    while ($start<$totalitems){

        if($group_ID){
            $items = $zotero->getItemsTop($group_ID, array('format'=>'atom', 'content'=>'json', 'start'=>$start,
                'limit'=>$fetch, 'order'=>'dateAdded', 'sort'=>'asc' ), "groups");
        }else{
            $items = $zotero->getItemsTop($user_ID, array('format'=>'atom', 'content'=>'json', 'start'=>$start,
                'limit'=>$fetch, 'order'=>'dateAdded', 'sort'=>'asc' ));
        }

        //fwrite($fd, $items);

        $zdata = simplexml_load_string($items);

        if($zdata===false){
            print "<div style='color:red'>Error: zotero returns non valid xml response for range $start ~ ".intval($start+$fetch)." </div>";
            $isFailure = true;

            $system->addError(HEURIST_ERROR, 'Zotero Synchronisation, Invalid XML Response',
                'Zotero Synchronisation has Encountered an Invalid XML Response',
                "Error: zotero returns non valid xml response for range $start ~ ".intval($start+$fetch));

            break;
        }elseif(count($zdata->children())<1){
            print "<div style='color:red'>Error: zotero returns empty response for range $start ~ ".intval($start+$fetch)." </div>";
            $isFailure = true;

            $system->addError(HEURIST_ERROR, 'Zotero Synchronisation, Empty Response',
                'Zotero Synchronisation has encountered an Empty Response',
                "Error: zotero returns empty response for range $start ~ ".intval($start+$fetch));

            break;
        }

        foreach ($zdata->children() as $entry){

            if($entry->getName()=="entry"){

                $zotero_itemid = strval(findXMLelement($entry, "zapi", "key"));

                // 2) get content of item if itemType is supported
                $itemtype = strval(findXMLelement($entry, "zapi", "itemType"));
                $itemtitle = strval(findXMLelement($entry, null, "title"));

                #print " <br>".$itemtype."  ".strval(findXMLelement($entry, null, "title"))."<br>";

                //@ob_flush();
                //@flush();

                if(!array_key_exists($itemtype, $mapping_rt)){ //this type is not mapped

                    print "<br>Undefined Record type ".htmlspecialchars($itemtype.' '.$itemtitle)."<br>";

                    #print " <br> Undefined Record type".$itemtype."  ".$itemtitle."<br>";
                    array_push($arr_ignored, $itemtype.':  '.$itemtitle);
                    if(!@$arr_ignored_by_type[$itemtype]) {$arr_ignored_by_type[$itemtype] = 0;}
                    $arr_ignored_by_type[$itemtype]++;
                    $cnt_ignored++;
                    continue;
                }
                else
                {
                    if($is_echo){
                        print htmlspecialchars($itemtype.": ".$itemtitle)."&nbsp;";
                    }
                }

                $recId = null;
                $rec_URL = null;

                // 3) try to search record in database by zotero id
                $query = "select r.rec_ID, r.rec_Modified from Records r, recDetails d  ".
                "where  r.rec_Id=d.dtl_recId and d.dtl_DetailTypeID="
                .intval($dt_SourceRecordID)." and d.dtl_Value='"
                .$mysqli->real_escape_string($zotero_itemid)."'";
                $res = $mysqli->query($query);
                if($res){
                    $row = $res->fetch_row();
                    if($row){
                        $recId = $row[0];

                        $rec_modified = strtotime($row[1]);

                        // 4) compare updated time - if it is less than in Heurist database, ignore this entry
                        $t_updated = strtotime(strval(findXMLelement($entry, null, "updated")));

                        if(false && $t_updated && $rec_modified>$t_updated){
                            print 'Rec#'.intval($recId).'entry was not changed since last sync.  '.
                            date("Y-m-d", $t_updated).' '.date("Y-m-d",$rec_modified ).'  <br>';
                            continue;
                        }
                    }
                }

                $content = json_decode(strval(findXMLelement($entry, null, "content")));

                // 5) create "details" array based on mapping

                $unresolved_records = array();
                $details = array();

                //find heurist record type mapped to zotero entry
                $mapping_dt = $mapping_rt[$itemtype];
                $recordType = $mapping_dt["h3rectype"];

                $is_empty_zotero_entry = true;

                foreach ($content as $zkey => $value){

                    if(!$value) {continue;}

                    $is_empty_zotero_entry = false;

                    if($zkey == "creators"){

                        /* sample of creator objects in Zoterp
                        Array (
                        [0] => stdClass Object
                        (
                        [creatorType] => editor
                        [firstName] => Harold
                        [lastName] => Mytum
                        )

                        [1] => stdClass Object
                        (
                        [creatorType] => editor
                        [firstName] => Gilly
                        [lastName] => Carr
                        )

                        [2] => stdClass Object
                        (
                        [creatorType] => author
                        [firstName] => John H.
                        [lastName] => Jameson
                        )
                        )
                        */


                        foreach($value as $creator){

                            $prop = 'creatorType';
                            $ctype = @$creator->$prop;

                            $key = @$mapping_dt[$ctype];
                            if(!$key) {continue;}

                            $prop = 'name';
                            $title = @$creator->$prop;

                            if(!is_array($key)){
                                if(!$title){
                                    $key = array($key, RT_PERSON, 0);
                                }else{
                                    $key = array($key, RT_ORGANIZATION, 0);
                                }
                            }

                            if(!$title){
                                $prop = 'lastName';
                                $lastName = @$creator->$prop;

                                if($lastName){
                                    $prop = 'firstName';
                                    assignUnresolvedPointer($unresolved_records, $key,
                                        array(DT_GIVEN_NAMES=>@$creator->$prop, DT_NAME=>$lastName) );
                                    continue;
                                }
                            }

                            if ($title){
                                assignUnresolvedPointer($unresolved_records, $key, array(DT_NAME => $title));
                            }


                        }

                        continue;
                    }

                    if($zkey == "url"){
                        $rec_URL = $value;
                    }

                    //find heurist field type mapped to zotero key
                    $key = @$mapping_dt[$zkey];
                    $resource_rt_id = null;
                    $resource_dt_id = null;

                    if($key){

                        if(is_array($key)){ //reference to record pointer
                            $detail_id = $key[0];
                        }else{
                            $detail_id = $key;
                        }

                        if(!@$alldettypes['typedefs'][$detail_id] && $zkey != 'url'){
                            //field id not found in this db
                            $msg = $itemtype.'.'.$zkey.' -> '.$detail_id;
                            if(!in_array($msg, $arr_notfound)){
                                array_push($arr_notfound, $msg);
                                $cnt_notfound++;
                            }
                            continue;
                        }

                        $dt_type = $alldettypes['typedefs'][$detail_id]['commonFields'][$fi_dettype];

                        if($dt_type=='enum' || $dt_type=='relationtype'){
                            // 6) find terms by label values
                            $trm_value = resolveTermValue($dt_type, $value);
                            if($trm_value==null){
                                $report_log = $report_log." term not found for ".$value;
                                continue;
                            }

                            $value = $trm_value;

                        }elseif($dt_type=='resource'){

                            // 7) store pointer titles in 'unresolved' pointers
                            if(!is_array($key)){ //by default
                                $key = array($detail_id, RT_NOTE, DT_NAME);
                            }

                            assignUnresolvedPointer($unresolved_records, $key, $value);

                            continue;
                        }
                        if($zkey=="pages"){

                            $pages = explode("-",$value);
                            $details["t:".$detail_id] = array("0"=>$pages[0]);
                            $detail_id2 = ConceptCode::getDetailTypeLocalID("3-1027");//MAGIC NUMBER
                            if($detail_id2){
                                $details["t:".$detail_id2] = array("0"=>(count($pages)>1)?$pages[1] :$pages[0]);
                            }

                        }else{

                            if($dt_type=='freetext' || $dt_type=='blocktext'){
                                $value = html_entity_decode($value);
                                //$val = htmlspecialchars_decode($val);
                            }

                            $details["t:".$detail_id] = array("0"=>$value);
                        }

                    }elseif(!($zkey == 'url' || $zkey=='key')){
                        if(!in_array($itemtype.'.'.$zkey, $arr_notmapped)){
                            array_push($arr_notmapped, $itemtype.'.'.$zkey);
                            $cnt_notmapped++;
                        }
                    }
                }//for fields in content

                $new_recid = null;

                if($is_empty_zotero_entry){

                    print errorDiv('Warning: zotero id '.htmlspecialchars($zotero_itemid)
                        .': no data recorded in Zotero for this entry');

                }elseif(empty($details)){
                    //no one zotero key has proper mapping to heurist fields
                    array_push($arr_empty, $zotero_itemid);
                    $cnt_empty++;
                }else{
                    $new_recid = addRecordFromZotero($recId, $recordType, $rec_URL, $details, $zotero_itemid, $is_echo, $totalitems);
                    if($new_recid){
                        if(!empty($unresolved_records)){
                            $unresolved_pointers[$new_recid] = $unresolved_records;
                        }
                        if(!@$cnt_report[$recordType]) {$cnt_report[$recordType] = array('added'=>array(), 'updated'=>array());}

                        if($recId==$new_recid){
                            $cnt_updated[]=$new_recid;
                            $cnt_report[$recordType]['updated'][] = $new_recid;
                        }else{
                            $cnt_added[]=$new_recid;
                            $cnt_report[$recordType]['added'][] = $new_recid;
                        }
                    }
                }


            }//entry

        }//end of for each loop by items in fetch

        $start = $start + $fetch;

    }// end of while loop
    print 'Synching Completed, Printing Report<br>';

    //fclose($fd);
    print TABLE_S.'<tr><td>&nbsp;</td><td>added</td><td>updated</td></tr>';
    foreach ($cnt_report as $rty_ID=>$cnt){
        print TR_S.htmlspecialchars($rectypes['names'][$rty_ID])
        .'</td><td align="center">'.composeLinkForAllIds($cnt['added'])
        .'</td><td align="center">'.composeLinkForAllIds($cnt['updated']).TR_E;


    }

    print TABLE_E.'<div><br>Records added : '.composeLinkForAllIds($cnt_added).DIV_E;

    print '<div>Records updated : '.composeLinkForAllIds($cnt_updated).DIV_E;

    $tot_erros = $cnt_ignored + $cnt_notmapped + $cnt_empty + $cnt_notfound;

    $err_msg = 'Zotero Synching has encountered issues in Database: ' . HEURIST_DBNAME;
    $line_sep = '<br>- ';

    if($tot_erros>0){
        print '<div style="color:red">';
        if($cnt_ignored>0){
            print '<br>Zotero entries that are not mapped to Heurist record types: '.intval($cnt_ignored).TABLE_S;
            foreach ($arr_ignored_by_type as $itemtype => $cnt){
                print TR_S.htmlspecialchars($itemtype).TD.intval($cnt).TR_E;
            }
            print '</table>';

            $err_msg = $err_msg . '\nZotero entries that are not mapped to Heurist record types: '.$cnt_ignored;
        }
        if($cnt_empty>0){
            print '<br>Zotero entries ignored because there are no properly mapped keys: '.$cnt_empty;
            print "<div style ='color:red; padding-left:20px'>- ".implode($line_sep,$arr_empty).DIV_E;

            $err_msg = $err_msg . '\nZotero entries ignored because there are no properly mapped key: '.$cnt_empty;
        }
        if($cnt_notfound>0){
            print '<br>Zotero keys are mapped to field types that are not found in this database: '.$cnt_notfound;
            print "<div style ='color:red; padding-left:20px'>- ".implode($line_sep,$arr_notfound).DIV_E;

            $err_msg = $err_msg . '\nZotero keys are mapped to field types that are not found in this database: '.$cnt_notfound;
        }
        print DIV_E;

        print '<div style="color:black">';
        if($cnt_notmapped>0){
            print '<br>Zotero keys that are not mapped to Heurist field types: '.$cnt_notmapped;
            print "<div style ='padding-left:20px'>- ".implode($line_sep,$arr_notmapped).DIV_E;

            $err_msg = $err_msg . '\nZotero keys that are not mapped to Heurist field types: '.$cnt_notmapped;
        }
        print DIV_E;

        $system->addError('Zotero Synchronisation Warnings',
            'Zotero Synchronisation has reported ' . $tot_erros . ' warnings',
            $err_msg);

        print '<span><br>If you think the Zotero import needs updating or wish to provide additional information you can contact the '.CONTACT_HEURIST_TEAM.' here.</span>';

        print '<script>window.hWin.HEURIST4.msg.showMsgDlg("Warning: '.$tot_erros
        .' warnings reported: Please check the warnings listed. '
        .' We do not map all fields from Zotero as for most purposes these are fields of little use in your database.'
        .' Please submit a bug report (Help > Bug report) if you think the Zotero import needs updating."'
        .',null,"Zotero synchronisation warnings");</script>';
    }

    if(!empty($unresolved_pointers)){
        print "<br>";

    }

    ob_flush();flush();

    // try to find 'unresolved pointers
    // $rec_id - record to be updated
    // $dt_id - field that must contain pointer to resource
    // $resource_rt_id - record type for resouce record
    // $resource_dt_id


    $ptr_cnt = 0;
    $missing_pointers_count = count($unresolved_pointers);
    foreach($unresolved_pointers as $rec_id=>$pntdata)
    {
        // pntdata = array of  detail id in main record => record id of resource =>
        //           detail id in resource OR simialr array for next level  => value
        //  $dt_id=>$resource_rt_id=>$resource_details


        foreach($pntdata as $dt_id=>$recdata){  //detail id in main record

            foreach($recdata as $resource_rt_id=>$resource_details){ //recordtype

                $recource_recid = createResourceRecord($mysqli, $resource_rt_id, $resource_details, $missing_pointers_count);

                if(!is_array($recource_recid)){
                    $recource_recid = array("0"=>$recource_recid);
                }

                foreach($recource_recid as $idx=>$res_rec_id){
                    //update main record
                    $inserts = array($rec_id, $dt_id, $res_rec_id, 1);
                    $query = "insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport) values ("
                    . join(",", $inserts).")";
                    $mysqli->query($query);
                    $ptr_cnt++;
                }
            }
        }

    }

    #if($ptr_cnt>0)
    #print "<br><br>Total count of RESOLVED pointers:".$ptr_cnt;

    // done - show report log

    #print "<br>Sync done";

}

function composeLinkForAllIds($ids){
    if(empty($ids)){
        return '0';
    }else{
        return '<a target="_blank" href="'
        .HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.'&q=ids:'
        .htmlspecialchars(implode(',',$ids)).'&nometadatadisplay=true">'
        .count($ids).'</a>';
    }
}

/**
* add mapping for given zotero type
*
* @param mixed $arr - attributes
* @param mixed $zType - zotero type
*/
function addMapping($arr, $zType, $rt_id, $org_rt_id)
{

    global $mapping_dt, $mapping_errors, $warning_count;

    $dt_code = strval($arr[H_ID]);
    $resource_rt_id = null;
    $resource_dt_id = null;

    $extra_info = array();// [0] => Zotero rectype id, [1] => Zotero rectype name, [2] => field id, [3] => field name
    array_push($extra_info, $org_rt_id, $zType, $dt_code, $arr['value']);

    //pointer mapping
    if(strpos($dt_code,".")>0){

        $res = getResourceMapping($dt_code, $rt_id, $arr, $extra_info);
        if(is_array($res)){
            $mapping_dt[strval($arr['value'])] = $res;
        }else{

            // Resource, NOT FOUND
            if(array_key_exists($dt_code, $mapping_errors)){

                $mapping_errors[$dt_code] = str_replace(TR_E, "", $mapping_errors[$zType]).", ".$res.TR_E;
                $warning_count ++;

            }else{
                $mapping_errors[$dt_code] = "<tr><td colspan='3'><strong>".$zType." (".$org_rt_id."):</strong></td><td colspan='4'>".$res.TR_E;
                $warning_count ++;
            }
        }

    }else{

        $dt_id = ConceptCode::getDetailTypeLocalID($dt_code);

        if($dt_id != null){
            $mapping_dt[strval($arr['value'])] = $dt_id;
        }

        printMappingReport_dt($arr, $rt_id, $dt_id, $extra_info);
    }
}

//
//
//
function printMappingReport_rt($arr, $rt_id){
    global $rectypes, $is_verbose, $mapping_errors, $successful_rows, $warning_count;

    if($is_verbose){

        $table_class = (is_object($arr) && $rt_id != null) ? 'tbl-head' : 'tbl-row';

        if(is_object($arr)){
            $zType = strval($arr['zType']);
            $code = $arr[H_ID];
        }else{
            $zType = '->';
            $code = $arr;
        }

        if($rt_id==null){ // NOT FOUND

            if($zType != '->'){
                //-> will be covered during resource (record type) field handling
                $rt_id = strval($code);
                $mapping_errors[$zType] = "<tr class='".$table_class."'><td colspan='3'><strong>".$zType." (".$rt_id."):</strong></td><td colspan='4'>no field mappings available</td></tr>";
                $warning_count ++;
            }
        }else{
            $successful_rows[] = "<tr class='".$table_class."'><td colspan='2'><strong>".$zType."</strong></td><td><strong>".$code."</strong></td>"
            ."<td><strong>&rArr;".$rectypes['names'][$rt_id]."</strong></td><td><strong>".$rt_id."</strong></td></tr>";
        }
    }
}

//
//
//
function printMappingReport_dt($arr, $rt_id, $dt_id, $extra_info){
    global $rectypes, $is_verbose, $mapping_errors, $transfer_errors, $successful_rows, $warning_count;

    if($is_verbose){

        if(is_object($arr)){
            $label = $arr['value'];
            $code = $arr[H_ID];
        }elseif(is_array($arr)){
            $label = $arr[3][0];
            $code = $arr[2];
        }else{
            $label = '';
            $code = $arr;

            if(is_array($arr)){ error_log(print_r($arr, true));}
        }

        if($extra_info == null){
            if(is_array($arr)){
                $extra_info = $arr;
            }
        }

        if(is_array($extra_info)){

            if(is_empty($extra_info[0])){
                $extra_info[0] = $rt_id;
            }
            if($label == ''){
                $dt_str = $extra_info[3][0]."(".$code.")";
            }else{
                $dt_str = $label."(".$code.")";
            }
        }

        if($dt_id==null){

            if($extra_info != null){

                if(array_key_exists($extra_info[1], $mapping_errors)){ // NOT FOUND

                    if(strpos($mapping_errors[$extra_info[1]], $dt_str) === false){ // Check if field is already listed
                        $mapping_errors[$extra_info[1]] = str_replace(TR_E, "", $mapping_errors[$extra_info[1]]).", ".$dt_str.TR_E;
                        $warning_count ++;
                    }
                }else{
                    $mapping_errors[$extra_info[1]] = "<tr class='tbl-row'><td colspan='3'><strong>".$extra_info[1]." (".$extra_info[0]."):</strong></td><td colspan='4'>unmapped fields: ".$dt_str.TR_E;
                    $warning_count ++;
                }
            }
        }else{
            if(@$rectypes['typedefs'][$rt_id]['dtFields'][$dt_id]){
                $successful_rows[] = "<tr class='tbl-row'><td></td><td>".$label.TD.$code."</td><td>&rArr;".$rectypes['typedefs'][$rt_id]['dtFields'][$dt_id][0].TD.$dt_id.TR_E;
            }else{ // NOT IN RECORD TYPE STRUCTURE

                if($extra_info != null){

                    if(array_key_exists($extra_info[1], $transfer_errors)){

                        if(strpos($transfer_errors[$extra_info[1]], $dt_str) === false){
                            $transfer_errors[$extra_info[1]] = str_replace(TR_E, "", $transfer_errors[$extra_info[1]]).", ".$dt_str.TR_E;
                            $warning_count ++;
                        }
                    }else{
                        $transfer_errors[$extra_info[1]] = "<tr class='tbl-row'><td colspan='3'><strong>".$extra_info[1]." (".$extra_info[0]."):</strong></td><td colspan='4'>".$dt_str.TR_E;
                        $warning_count ++;
                    }
                }
            }
        }
    }
}

/*
* parse resource mapping (recursive)
*/
function getResourceMapping($dt_code, $rt_id, $arr=null, $extra_info=null){

    $arrdt = explode(".",$dt_code);
    if(count($arrdt)>2){
        $dt_code = array_shift($arrdt);// $arrdt[0];
        $resource_rt_id = array_shift($arrdt);//$arrdt[1];//resource record type
        $resource_dt_id = $arrdt[0];
    }else{
        return "Invalid resource mapping for id: ".$dt_code;
    }

    $dt_id = ConceptCode::getDetailTypeLocalID($dt_code);

    if($arr!=null){
        printMappingReport_dt($arr, $rt_id, $dt_id, $extra_info);
    }else{
        printMappingReport_dt($dt_code, $rt_id, $dt_id, $extra_info);
    }

    if($dt_id == null){
        return "Unable to find the detail type for id: ".$dt_code;
    }

    $res_rt_id = ConceptCode::getRecTypeLocalID($resource_rt_id);

    printMappingReport_rt($resource_rt_id, $res_rt_id);

    if($res_rt_id == null){
        return "Resource record type not recognised for id: ".$resource_rt_id;
    }

    $res_dt_id = ConceptCode::getDetailTypeLocalID($resource_dt_id);

    if($res_dt_id == null){
        printMappingReport_dt($resource_dt_id, $res_rt_id, $res_dt_id, $extra_info);
        return "Detail type for resource (record pointer) not recognised for id: ".$resource_dt_id;
    }

    if(count($arrdt)>1){
        // next level
        $subres = getResourceMapping( implode(".",$arrdt), $res_rt_id, $extra_info );
        if(is_array($subres)){
            $res = array($dt_id, $res_rt_id, $subres);
        }else{
            return $subres;
        }
    }else{
        //pointer detail type and detail type in resource record
        printMappingReport_dt($resource_dt_id, $res_rt_id, $res_dt_id, $extra_info);
        $res = array($dt_id, $res_rt_id, $res_dt_id);
    }

    return $res;
}

/**
* add to list of resource record pointers
*
* @param mixed $unresolved
* @param mixed $key
* @param mixed $value
*/
function assignUnresolvedPointer(&$unresolved, $key, $value){

    $detail_id      = $key[0];
    $resource_rt_id = $key[1];
    $resource_dt_id = $key[2];

    if(!@$unresolved[$detail_id]){
        $unresolved[$detail_id] = array();
    }
    if(!@$unresolved[$detail_id][$resource_rt_id]){
        $unresolved[$detail_id][$resource_rt_id] = array();
    }
    if(is_array($resource_dt_id)){

        assignUnresolvedPointer($unresolved[$detail_id][$resource_rt_id], $resource_dt_id, $value);

    }else{
        if( is_array($value) ){ //this is creator  detail id in value
            array_push($unresolved[$detail_id][$resource_rt_id], $value);
        }else{
            $unresolved[$detail_id][$resource_rt_id][$resource_dt_id] = $value;
        }
    }
}

/**
* try to find resource record, create it if not found
*
* @param mixed $record_type - recordtype for resource
* @param mixed $recdetails - array of dt_id=>value
* @param integer $missing_pointers_count - count of unresolved pointers (to avoid spamming emails)
*
* returns array of resource record ID
*/
function createResourceRecord($mysqli, $record_type, $recdetails, $missing_pointers_count){

    global $alldettypes, $fi_dettype, $report_log;

    if(is_array($recdetails) && array_key_exists(0, $recdetails)){ //these are creators
        $recource_recids = array();
        foreach($recdetails as $idx=>$creator){
            array_push($recource_recids, createResourceRecord($mysqli, $record_type, $creator, $missing_pointers_count));
        }
        return $recource_recids;
    }

    $value_params = array('');
    $query = '';
    $details = array();
    $dcnt = 1;
    $recource_recid = null; //returned value

    foreach($recdetails as $dt_id=>$recdata){  //detail id in main record


        if(!@$alldettypes['typedefs'][$dt_id]) {continue;}  //detail type not found

        $dt_type = $alldettypes['typedefs'][$dt_id]['commonFields'][$fi_dettype];
        if($dt_type=='enum' || $dt_type=='relationtype'){

            $trm_value = resolveTermValue($dt_type, $recdata);
            if($trm_value==null){
                $report_log = $report_log."<br> term not found for ".$recdata;
                continue;
            }
            $value = $trm_value;

        }elseif($dt_type=='resource'){ //next level of reference

            if(!is_array($recdata)){

                $record_type_2 =  getConstrainedRecordType($dt_id);
                if($record_type_2){
                    $recdata = array(DT_NAME=>$recdata);
                    $value = createResourceRecord($mysqli, $record_type_2, $recdata, $missing_pointers_count);
                }else{
                    $report_log = $report_log."<br> resource (record pointer) record type unconstrained for detail type: ".$dt_id;
                    continue;
                }

            }else{
                $value = array();
                foreach($recdata as $record_type_2=>$recdata_nextlevel){ //recordtype

                    $value = createResourceRecord($mysqli, $record_type_2, $recdata_nextlevel, $missing_pointers_count);//return rec_id
                    break;

                }
            }
        }else{
            $value = $recdata;
            if($dt_id==DT_DATE){

                $value = Temporal::dateToISO($value, 1);
                /*
                try{
                $t2 = new DateTime($value);
                $value = $t2->format(DATE_8601);
                } catch (Exception  $e){
                }
                */
            }
        }


        if($value){

            if (!is_array($value)) {
                $value = array("0"=>$value);
            }
            //query to search similar record

            $details['t:'.$dt_id] = $value;
            foreach($value as $idx=>$val){
                $value_params[0] .= 's';
                $value_params[] = $val;
                $query = $query." and r.rec_Id=d$dcnt.dtl_recId and d$dcnt.dtl_DetailTypeID=".intval($dt_id).
                " and d$dcnt.dtl_Value=? ";
                $dcnt++;
            }
        }
    }//for recdetails

    // try to find the existing record
    if($query){
        $qd = "";
        for ($idx=1; $idx<$dcnt; $idx++){ //count of details
            $qd = $qd.",recDetails d$idx ";
        }

        //find resouce record , if not found create new one
        $query = "select r.rec_ID from Records r $qd where r.rec_RecTypeID=".intval($record_type).$query;
        //$res = $mysqli->query($query);
        $res = mysql__select_param_query($mysqli,$query,$value_params);
        if($res){
            $row = $res->fetch_row();
            if($row){
                $recource_recid = intval($row[0]);
            }
        }
    }

    if(!($recource_recid>0)){
        //such record not found - create new one
        $recource_recid = addRecordFromZotero(null, $record_type, null, $details, null, false, $missing_pointers_count);
    }

    return intval($recource_recid);
}

/**
* find xml element
*
* @param mixed $xml
* @param mixed $ns
* @param mixed $name
*/
function findXMLelement($xml, $ns, $name){

    if($ns){
        $children = $xml->children($ns, true);
    }else{
        $children = $xml->children();
    }


    foreach ($children as $f_gen){
        if($f_gen->getName()==$name){
            return $f_gen;
        }else{
            $res = findXMLelement($f_gen, $ns, $name);
            if($res){
                return $res;
            }
        }
    }

    return null;
}

/**
* term value to id
*
* @param mixed $dt_type
* @param mixed $value
*/
function resolveTermValue($dt_type, $value)
{
    global $allterms, $fi_trmlabel;

    $terms = $allterms['termsByDomainLookup'][($dt_type=='enum'?'enum':'relation')];
    $trm_value = null;
    foreach ($terms as $trmid => $term){
        if( strpos(strtolower($term[$fi_trmlabel]), strtolower($value))===0 ){
            $trm_value = $trmid;
            break;
        }
    }
    return $trm_value;
}

/**
* get record type for resource (record pointer) detail type
*
* @param mixed $resource_dt_id
*/
function getConstrainedRecordType($resource_dt_id){

    global $alldettypes, $fi_constraint;

    $pointer_constraint = @$alldettypes['typedefs'][$resource_dt_id]['commonFields'][$fi_constraint];
    if(strpos($pointer_constraint,",")>0){
        $pointer_constraint = explode(",", $pointer_constraint);
        $pointer_constraint = $pointer_constraint[0];
    }
    return $pointer_constraint;
}


/**
* saves record in heurist DB
*
* @param mixed $recId
* @param mixed $recordType
* @param mixed $rec_URL
* @param mixed $details
* @param mixed $zotero_itemid
* @param mixed $is_echo
* @param int $record_count - prevent spamming emails about record creation
*/
function addRecordFromZotero($recId, $recordType, $rec_URL, $details, $zotero_itemid, $is_echo, $record_count){

    global $system, $rep_errors_only, $dt_SourceRecordID;

    $new_recid = null;

    if( !empty($details)){

        if($zotero_itemid){
            $details["t:".$dt_SourceRecordID] = array("0"=>$zotero_itemid);
        }
        // 8) save rtecord
        $ref = null;

        //add-update Heurist record
        $record = array();
        $record['ID'] = $recId?$recId:0; //0 means insert
        $record['RecTypeID'] = $recordType;
        $record['AddedByImport'] = 2;
        $record['no_validation'] = true;
        $record['URL'] = $rec_URL;
        $record['ScratchPad'] = null;
        $record['details'] = $details;

        $out = recordSave($system, $record, true, false, 0, $record_count);//see recordModify.php

        if ( @$out['status'] != HEURIST_OK ) {
            print "<div style='color:red'> Error: ".htmlspecialchars($out["message"]).DIV_E;
        }else{

            $new_recid = intval($out['data']);

            if($is_echo){
                print '['.($new_recid==$recId?"Updated":"Added")."&nbsp;Id&nbsp".$new_recid.']<br>';
            }


            if(!$rep_errors_only){
                if (@$out['warning']) {
                    print "<div style='color:red'>Warning: ".htmlspecialchars(implode(";",$out["warning"])).DIV_E;
                }
            }

        }

    }
    return intval($new_recid);
}

//isNullOrEmptyString
function is_empty($question){
    $ret = (!isset($question) || trim($question)==='');
    return $ret;
}

?>

</body>

</html>
