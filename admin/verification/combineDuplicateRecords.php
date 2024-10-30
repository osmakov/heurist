<?php

/*
* Copyright (C) 2005-2023 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* combineDuplicateRecords.php - merge given list of records (by ids)
* dependcy - titleMask
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson.heurist@gmail.com>
* @author      Stephen White
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

// Add checks for required access + permission
define('MANAGER_REQUIRED', 1);
define('CREATE_RECORDS', 1);
define('DELETE_RECORDS', 1);

define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
require_once dirname(__FILE__).'/../../hserv/records/edit/recordTitleMask.php';


global $mysqli;
$mysqli = $system->get_mysqli();

$do_merge_details = false;
$finished_merge = false;
$instant_merge = false;

if (@$_REQUEST['finished_merge']==1){
    $finished_merge = true;
}
//store the master record id
if (@$_REQUEST['keep'])  {
    $master_rec_id = intval($_REQUEST['keep']);
}else{
    $master_rec_id = intval(@$_REQUEST['master_rec_id']);
}

if(@$_REQUEST['instant_merge']){
    $instant_merge = true;
}

//get all enumeration fields - global
$enum_bdts = mysql__select_assoc2($mysqli,
        'select dty_ID, dty_Name from defDetailTypes where (dty_Type="relationtype") OR (dty_Type="enum")');

if (@$_REQUEST['keep']  &&  @$_REQUEST['duplicate']){  //user has select master and dups- time to merge details
    $do_merge_details = true;
    $_REQUEST['bib_ids'] = implode(',',array_merge($_REQUEST['duplicate'],array($_REQUEST['keep'])));//copy only the selected items

}

$bib_ids = prepareIds(filter_var(@$_REQUEST['bib_ids'], FILTER_SANITIZE_STRING));
$bib_ids_list = implode(',', $bib_ids);

if ( empty($bib_ids) ){
    redirectURL(ERROR_REDIR.'&msg='.rawurlencode('Wrong parameter. List of record ids is not defined'));
    exit;
}

if(@$_REQUEST['commit'] || $instant_merge){
    do_fix_dupe();
    return;
}

$bdts = mysql__select_assoc2($mysqli,'select dty_ID, dty_Name from defDetailTypes');
$reference_bdts = mysql__select_assoc2($mysqli,'select dty_ID, dty_Name from defDetailTypes where dty_Type="resource"');

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Merging records</title>

<?php
        includeJQuery();
?>

        <!-- CSS -->
        <?php include_once dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php';?>

        <style type="text/css">
            body { font-size: 0.7em; background-color:white }
            td { vertical-align: top; }
            table {line-height:2.5ex}
            A:link, A:visited {color: #6A7C99;}
        </style>

        <script type="text/javascript">
            <!--
            function keep_bib(rec_id) {
                e = document.getElementById('tb');
                if (!e) {return;}
                for (var i = 0; i < e.childNodes.length; ++i) {
                    row = e.childNodes[i];
                    if (row.nodeName == "TR" && row.id){
                        id = row.id.replace(/row/,'');
                        d = document.getElementById('duplicate' + id);
                        if (id == rec_id){
                            row.style.backgroundColor = '#EEE';
                            if(d){
                                d.style.display = "none";
                                d.nextSibling.style.display = "none";
                            }
                        }else{
                            if (d) {
                                d.style.display = "block";
                                d.nextSibling.style.display = "block";
                            }
                            if (d && d.checked){
                                row.style.backgroundColor = '#EEE';
                            }else{
                                row.style.backgroundColor = '';
                            }
                        }
                    }
                }
                e = document.getElementById('keep'+rec_id);
                if (e) e.checked = true;
                e = document.getElementById('duplicate'+rec_id);
                if (e) e.checked = false;
            }
            function delete_bib(rec_id) {
                e = document.getElementById('row'+rec_id);
                if (e) e.style.backgroundColor = '#EEE';
            }
            function undelete_bib(rec_id) {
                e = document.getElementById('row'+rec_id);
                if (e) e.style.backgroundColor = '';
            }

            $(document).ready(function() {

                $('input[type="button"]').button();
                $('input[type="submit"]').addClass('ui-button-action').button();

                let $merge = $('input[name="merge"]');
                if($merge.length == 1 && $('input[name="duplicate[]"]').length == 2){
                    // automatically merge if there is only two records listed
                    $merge.trigger('click');
                    return;
                }

                let $popup = $(window.frameElement).parent('div.ui-dialog-content');//[role="dialog"]
                let content_ele = $('.ent_content')[0];
                let max_height = window.parent.innerHeight - 80;

                if($popup.length == 0){
                    return;
                }

                setTimeout(() => {

                    let has_scroll = content_ele.scrollHeight - content_ele.clientHeight;

                    if(has_scroll > 0){
                        let height = content_ele.scrollHeight + 50;
                        height = height > max_height ? max_height : height;

                        $popup.height(`${height}px`);
                        $popup.parent().position({of: window.parent});
                    }
                }, 1000);
            });

            -->
        </script>
    </head>

    <body class="popup" width="800" height="600">
        <form>
        <div class="ent_wrapper">
            <div class="ent_content" style="top:0">
                <div>
                    <?php
                    if (! @$do_merge_details){
                        print 'This function combines duplicate records. One record MUST be selected as a master record'.
                        ' and there must be at least one duplicate selected. Processing duplicates allows you to merge,'.
                        'data with the master record.<br><br>'.
                        'Bookmarks, Tags and Relationships from deleted records are added to the master record.<br><br>'.
                        'None of these data are duplicated if they already exist in the master record.';
                    } else{
                        print 'Select the data items which should be retained, added or replaced in the master records.'.
                        ' Repeatable (multi-valued)fields are indicated by checkboxes and single value fields are '.
                        ' indicated by radio buttons. Pressing the "commit changes" button will start the process to'.
                        ' save the changes to the master record. You will be able to view the master record to verify '.
                        'the changes.';
                    }
                    ?>
                </div><br><hr>

                <table role="presentation"><tbody id="tb">
                        <?php

                        if($master_rec_id>0){
                            print '<input type="hidden" name="master_rec_id" value="'.$master_rec_id.'">';
                        }
                        if($finished_merge){
                            print '<input type="hidden" name="finished_merge" value="1">';
                        }


                        print '<input type="hidden" name="bib_ids" value="'.htmlspecialchars($bib_ids_list).'">';

                        $rtyNameLookup = mysql__select_assoc2($mysqli,
                            'select rty_ID, rty_Name from Records left join defRecTypes on rty_ID=rec_RecTypeID '
                            .'where rec_ID in ('.$bib_ids_list.')');

                        print TR_S.TR_E;

                        //get requirements for details
                        $res = $mysqli->query('select rst_RecTypeID,rst_DetailTypeID, rst_DisplayName, rst_RequirementType, rst_MaxValues from defRecStructure where rst_RecTypeID in ('.join(',',array_keys($rtyNameLookup)).')');
                        $rec_requirements =  array();

                        while ($req = $res->fetch_assoc()) {
                            $rec_requirements[$req['rst_RecTypeID']][$req['rst_DetailTypeID']]= $req;
                        }
                        $res->close();

                        $query2 = 'select * from Records where rec_ID in ('.$bib_ids_list.') order by find_in_set(rec_ID, "'.$bib_ids_list.'")';
                        $res = $mysqli->query($query2);
                        $records = array();
                        $counts = array();
                        $rec_references = array();
                        $invalid_rec_references = array();
                        while ($rec = $res->fetch_assoc()) {
                            $records[$rec['rec_ID']] = $rec;
                        }
                        $res->close();

                        foreach($records as $index => $record){

                            //Note - it searches only reverse references
                            //search rec_IDs that links to this record
                            $rec_references = mysql__select_list2($mysqli,
                                    'select dtl_RecID from recDetails WHERE dtl_Value='.intval($records[$index]['rec_ID'])
                                    .' and dtl_DetailTypeID in ('.join(',', array_keys($reference_bdts)).')');
                            if ($rec_references){
                                // only store the references that are actually records
                                $records[$index]["refs"] = mysql__select_assoc2($mysqli, 'select rec_ID, rec_Title from Records '
                                     .' WHERE rec_ID in ('.join(',', $rec_references).')');
                                if(is_array($records[$index]["refs"])){
                                    $records[$index]["ref_count"] = count($records[$index]["refs"]);
                                }else{
                                    $records[$index]["ref_count"] = 0;
                                }
                                $invalid_rec_references += array_diff($rec_references, array_keys($records[$index]["refs"]));
                            }

                            $details = array();
                            $res = $mysqli->query('select dtl_DetailTypeID, dtl_Value, dtl_ID, dtl_UploadedFileID, if(dtl_Geo is not null, ST_AsWKT(dtl_Geo), null) as dtl_Geo, trm_Label
                                from recDetails  left join defTerms on trm_ID = dtl_Value
                                where dtl_RecID = ' . intval($records[$index]['rec_ID']) . '
                            order by dtl_DetailTypeID, dtl_ID');

                            $records[$index]['details'] = array();
                            while ($row = $res->fetch_assoc()) {

                                $type = $row['dtl_DetailTypeID'];

                                if (! array_key_exists($type, $records[$index]['details'])) {
                                    $records[$index]['details'][$type] = array();
                                }

                                if(!in_array($type, array_keys($enum_bdts))){
                                       $row['trm_Label'] = null;
                                }

                                array_push($records[$index]['details'][$type], $row);
                            }
                            $counts[$index] = $res->num_rows;
                            $res->close();
                        }
                        //Output records in order of most fiel values - first is the default 'master' record
                        $rec_keys = array_keys($records);
                        if (! @$master_rec_id){
                            array_multisort($counts, SORT_NUMERIC, SORT_DESC, $rec_keys );
                            $master_rec_id = intval($rec_keys[0]);
                        }
                        if (! @$do_merge_details){  // display a page to user for selecting which record should be the master record
                            //    foreach($records as $index) {
                            foreach($records as $record) {

                                $rec_ID = intval($record['rec_ID']);
                                $is_master = ($rec_ID== $master_rec_id);
                                print '<tr'. ($is_master && !$finished_merge ? ' style="background-color: #EEE;" ': '').' id="row'.$rec_ID.'">';
                                $checkKeep =  $is_master? "checked" : "";
                                $checkDup = !$is_master && count($records) < 5 ? "checked" : "";
                                $disableDup = $is_master? "none" : "block";
                                if (!$finished_merge) {
                                    print <<<EXP
<td><input type="checkbox" name="duplicate[]" $checkDup value="$rec_ID" title="Check to mark this as a duplicate record for deletion"
    id="duplicate$rec_ID" style="display:$disableDup" onclick="if (this.checked) delete_bib($rec_ID); else undelete_bib($rec_ID);">
    <div style="font-size: 70%; display:$disableDup;">DUPLICATE</div></td>'
EXP;
                                }
                                print '<td style="width: 500px;">';
                                if (!$finished_merge) {
                                    print <<<EXP
<input type="radio" name="keep" $checkKeep value="$rec_ID" title="Click to select this record as the Master record"
    id="keep$rec_ID" onclick="keep_bib($rec_ID);">
EXP;
                                }
                                print '<span style="font-size: 120%;">'
                                    .edit_link($rec_ID,$rec_ID.' <b>'.htmlspecialchars($record['rec_Title']).'</b>',false)
                                    .' - <span style="background-color: #EEE;">'. htmlspecialchars($rtyNameLookup[$record['rec_RecTypeID']]).'</span></span>';
                                print TABLE_S;
                                foreach ($record['details'] as $rd_type => $detail) {
                                    if (! $detail) {continue;}    //FIXME  check if required and mark it as missing and required
                                    if(!@$rec_requirements[$record['rec_RecTypeID']][$rd_type]) {continue;}
                                    $reqmnt = $rec_requirements[$record['rec_RecTypeID']][$rd_type]['rst_RequirementType'];
                                    $color = ($reqmnt == 'required' ? 'red': ($reqmnt == 'recommended'? 'black':'grey'));
                                    print '<tr><td style="color: '.$color .';">'.$bdts[$rd_type].TD_E;
                                    print '<td style="padding-left:10px;">';
                                    foreach($detail as $i => $rg){


                                        if ($rg['dtl_UploadedFileID']) {
                                            $rd_temp = mysql__select_value($mysqli,
                                                    'select ulf_OrigFileName from recUploadedFiles where ulf_ID ='
                                                    .$rg['dtl_UploadedFileID']);
                                            $rd_temp = htmlspecialchars($rd_temp);
                                        }else {

                                            if ($rg['dtl_Geo']) {
                                                $rd_temp = $rg['dtl_Geo'];
                                            }
                                            elseif($rg['trm_Label']){
                                                $rd_temp = $rg['trm_Label']." (".$rg['dtl_Value'].")";
                                                //$temp = $rg['dtl_Value'];//trm_ID
                                            }
                                            else {
                                                $rd_temp = $rg['dtl_Value'];
                                            }

                                        }
                                        if(! @$temp) {$temp=$rd_temp;}
                                        elseif(!is_array($temp)){
                                            $temp = array($temp,$rd_temp);
                                        }else {array_push($temp,$rd_temp);}
                                    }
                                    $detail = detail_str($rd_type, $temp);
                                    unset($temp);
                                    if (is_array($detail)) {
                                        $repeatCount = intval($rec_requirements[$record['rec_RecTypeID']][$rd_type]['rst_MaxValues']);
                                        if ($repeatCount==0){
                                            foreach ($detail as $val) {
                                                $val = htmlspecialchars($val);
                                                print "<div style=\"word-break: break-word;\">$val</div>";
                                            }
                                        } else{
                                            for ($i = 0; $i < $repeatCount; $i++) {
                                                $val = htmlspecialchars($detail[$i]);
                                                print "<div style=\"word-break: break-word;\">$val</div>";
                                            }
                                            //FIXME  add code to remove the extra details that are not supposed to be there
                                        }
                                    } else{
                                        print "<div style=\"word-break: break-word;\">$detail</div>";
                                    }

                                    print TD_E;
                                }

                                if ($record['rec_URL']) {print '<tr><td>URL</td><td><a href="'.$record['rec_URL'].'">'.htmlspecialchars($record['rec_URL']).'</a></td></tr>';}

                                if ($record['rec_Added']) {print '<tr><td>Added</td><td style="padding-left:10px;">'.htmlspecialchars(substr($record['rec_Added'], 0, 10)).TR_E;}
                                if ($record['rec_Modified']) {print '<tr><td>Modifed</td><td style="padding-left:10px;">'.htmlspecialchars(substr($record['rec_Modified'], 0, 10)).TR_E;}


                                print TABLE_E.TD;

                                print TABLE_S;

                                if (array_key_exists("refs", $record)) {
                                    print '<tr><td>References</td></tr><tr><td>';
                                    $i = 1;
                                    foreach ($record["refs"] as $rec_ID=>$rec_Title) {
                                        print edit_link($rec_ID, htmlspecialchars($rec_Title), true);
                                    }
                                    print TR_E;
                                }

                                $bkmk_count = intval(mysql__select_value($mysqli,
                                    'select count(distinct bkm_ID) from usrBookmarks where bkm_RecID='.$record['rec_ID']));

                                if ($bkmk_count>0) {print '<tr><td>Bookmarks</td><td>'.$bkmk_count.TR_E;}

                                $kwd_count = intval(mysql__select_value($mysqli,
                                    'select count(distinct rtl_ID) from usrBookmarks left join usrRecTagLinks '
                                    .'on rtl_RecID=bkm_recID where bkm_RecID='.$rec_ID.' and rtl_ID is not null'));

                                if ($kwd_count>0) {print '<tr><td>Tags</td><td>'.$kwd_count.TR_E;}

                                $res2 = $mysqli->query('select concat(ugr_FirstName," ",ugr_LastName) as name, '
                                .'rem_Freq, rem_StartDate from usrReminders left join sysUGrps on ugr_ID=rem_OwnerUGrpID '
                                .'where ugr_Type = "User" and rem_RecID='.$rec_ID);

                                $rems = array();
                                while ($rem = $res2->fetch_assoc()){
                                    $rems[] = htmlspecialchars($rem['name'].' '.$rem['rem_Freq']
                                                .($rem['rem_Freq']=='once' ? ' on ' : ' from ').$rem['rem_StartDate']);
                                }
                                $res2->close();

                                if (count($rems)){
                                    print '<tr><td>Reminders</td><td>' . join(', ', $rems) . TR_E;
                                }

                                print '</table>';

                                print TR_E;
                                print '<tr><td colspan=3><br><hr></td></tr>';
                                print "</tr>\n\n";
                            }
                        }else{  //display page for the user to select the set of details to keep for this record  - this is the basic work for the merge
                            $master_index = array_search($master_rec_id, $rec_keys);
                            if ($master_index === false){  // no master selected we can't do a merge
                                return;
                            } elseif ($master_index > 0){  // rotate the keys so the master is first
                                $temp = array_slice($rec_keys, 0,$master_index);
                                $rec_keys = array_merge(array_slice($rec_keys,$master_index),$temp);
                                $master_rec_type = $records[$master_rec_id]['rec_RecTypeID'];
                            }

                            $master_details = [];
                            $missing_in_master = [];

                            foreach($rec_keys as $index) {

                                $record = $records[$index];
                                $rec_ID = intval($record['rec_ID']);
                                $is_master = ($rec_ID== $master_rec_id);
                                print '<tr id="row'.intval($record['rec_ID']).'">';
                                if ($is_master) {print '<td><div><b>MASTER</b></div></td>';}
                                else {print '<td><div><b>Duplicate</b></div></td>';}
                                print '<td style="width: 500px;">';
                                print '<div style="font-size: 120%;">'
                                        .edit_link($rec_ID,$rec_ID.' <b>'.htmlspecialchars($record['rec_Title']).'</b>', false)
                                .' - <span style="background-color: #EEE;">'. htmlspecialchars($rtyNameLookup[$record['rec_RecTypeID']]).'</span></div>';
                                print TABLE_S;
                                if ($is_master) {
                                    $master_details = $record['details'];
                                }
                                foreach ($record['details'] as $rd_type => $detail) {
                                    if (! $detail) {continue;}    //FIXME  check if required and mark it as missing and required
                                    // check to see if the master record already has the same detail with the identical value ignoring leading and trailing spaces
                                    $removeIndices = array();
                                    if (!$is_master && @$master_details[$rd_type]){

                                        $cur_master_detail =  $master_details[$rd_type];
                                        foreach ($detail as $i => $d_detail){

                                            foreach ($cur_master_detail as $m_detail){

                                                if($m_detail['dtl_Geo']){
                                                    if(trim($d_detail['dtl_Geo']) == trim($m_detail['dtl_Geo'])){

                                                        array_push($removeIndices,$i);
                                                    }
                                                }elseif($m_detail['dtl_UploadedFileID']) {
                                                    if(trim($d_detail['dtl_UploadedFileID']) == trim($m_detail['dtl_UploadedFileID'])){
                                                        array_push($removeIndices,$i);
                                                    }
                                                }elseif($m_detail['dtl_Value'] && trim($d_detail['dtl_Value']) == trim($m_detail['dtl_Value'])){
                                                        //mark this detail for removal
                                                        array_push($removeIndices,$i);
                                                }
                                            }
                                        }
                                    }
                                    foreach ($removeIndices as $i){
                                        unset($detail[$i]);
                                    }
                                    if (count($detail) == 0) {continue;}
                                    if(!@$rec_requirements[$master_rec_type][$rd_type]) {continue;}
                                    $reqmnt = $rec_requirements[$master_rec_type][$rd_type]['rst_RequirementType'];
                                    $color = ($reqmnt == 'required' ? 'red': ($reqmnt == 'recommended'? 'black':'grey'));
                                    print '<tr><td style=" color: '.$color .';">'.$bdts[$rd_type].TD_E;
                                    //FIXME place a keep checkbox on values for repeatable fields , place a radio button for non-repeatable fields with
                                    //keep_dt_### where ### is detail Type id and mark both "checked" for master record
                                    print '<td style="padding-left:10px;">';
                                    $repeatCount = 0;
                                    if(@$rec_requirements[$master_rec_type][$rd_type]){
                                        $repeatCount = intval($rec_requirements[$master_rec_type][$rd_type]['rst_MaxValues']);
                                    }

                                    $is_missing_master = $repeatCount == 1 && !array_key_exists($rd_type, $master_details);
                                    if($is_missing_master && !in_array($rd_type, $missing_in_master)){
                                        $missing_in_master[] = $rd_type;
                                    }
                                    $inputs = detail_get_html_input_str( $detail, $repeatCount, $is_master, $is_missing_master );
                                    if (!empty($inputs)) {
                                        if ($repeatCount != 1){//repeatable
                                            foreach ($inputs as $val) {
                                                print "<div>$val</div>";
                                            }
                                        } else{
                                            print "<div>{$inputs[0]}</div>";
                                            //FIXME  add code to remove the extra details that are not supposed to be there
                                        }
                                        //} else{
                                        //print "<div>$detail</div>";
                                    }

                                    print TD_E;
                                }

                                if ($record['rec_URL']) {

                                    print '<tr><td>URL</td><td><input type="radio" name="URL" '.($is_master?"checked=checked":"").
                                    ' title="'.($is_master?"Click to keep URL with Master record":"Click to replace URL in Master record (overwrite)").
                                    '" value="'.$record['rec_URL'].
                                    '" id="URL'.$record['rec_ID'].
                                    '"><a href="'.$record['rec_URL'].'">'.$record['rec_URL'].'</a>'.TR_S;
                                }
                                if ($record['rec_Added']) {print '<tr><td>Add &nbsp;&nbsp;&nbsp;'.htmlspecialchars(substr($record['rec_Added'], 0, 10)).TR_E;}
                                if ($record['rec_Modified']) {print '<tr><td>Mod &nbsp;&nbsp;&nbsp;'.htmlspecialchars(substr($record['rec_Modified'], 0, 10)).TR_E;}


                                print '</table></td><td>';

                                print TABLE_S;

                                if (array_key_exists("refs", $record)) {
                                    print '<tr><td>References</td></tr><tr><td>';
                                    $i = 1;
                                    foreach ($record["refs"] as $rec_ID=>$rec_Title) {
                                        print edit_link($rec_ID, htmlspecialchars($rec_Title), true);
                                    }
                                    print TR_E;
                                }

                                $bkmk_count = intval(mysql__select_value($mysqli,
                                    'select count(distinct bkm_ID) from usrBookmarks where bkm_recID='.$record['rec_ID']));
                                if ($bkmk_count>0) {print '<tr><td>Bookmarks</td><td>'.$bkmk_count.TR_E;}

                                $kwd_count = intval(mysql__select_value($mysqli,
                                    'select count(distinct rtl_ID) from usrBookmarks left join usrRecTagLinks '
                                    .'on rtl_RecID=bkm_recID where bkm_RecID='.$record['rec_ID'].' and rtl_ID is not null'));
                                if ($kwd_count>0) {print '<tr><td>Tags</td><td>'.$kwd_count.TR_E;}


                                $res2 = $mysqli->query('select concat(ugr_FirstName," ",ugr_LastName) as name, '
                                .'rem_Freq, rem_StartDate from usrReminders left join sysUGrps on ugr_ID=rem_OwnerUGrpID '
                                .'where rem_RecID='.intval($record['rec_ID']));

                                $rems = array();
                                while ($rem = $res2->fetch_assoc()){
                                    $rems[] = htmlspecialchars($rem['name'].' '.$rem['rem_Freq']
                                        .($rem['rem_Freq']=='once' ? ' on ' : ' from ').$rem['rem_StartDate']);
                                }
                                $res2->close();
                                if (!empty($rems)){
                                    print '<tr><td>Reminders</td><td>' . join(', ', $rems) . TR_E;
                                }

                                print '</table>';

                                print TR_E;
                                print '<tr><td colspan=3><br><hr></td></tr>';
                                print "</tr>\n\n";
                            }

                            if(!empty($missing_in_master)){
                                print '<script>'
                                        . '$(".not_in_master").on("change", (e) => {'
                                            . 'let name = $(e.target).attr("name"); let new_state = $(e.target).prop("checked");'
                                            . 'let $inputs = $(`input[type="checkbox"][name="${name}"]`); if($inputs.length > 1) { $inputs.prop("checked", false); $(e.target).prop("checked", new_state);} })'
                                    . '</script>';
                            }
                        }
                        ?>
                    </tbody></table>
                <input type="hidden" name="db" id="db" value="<?php echo HEURIST_DBNAME;?>">
            </div>
        </div>
        <div class="ent_footer ui-dialog-buttonpane" style="padding-top:10px">
                <?php
                if (! $finished_merge) {
                    print '<input type="submit" name="'.($do_merge_details? "commit":"merge").'" style="float:right;" value="'. ($do_merge_details? "Commit&nbsp;Changes":"Merge&nbsp;Duplicates").'" >';
                } else{
                    print 'Changes were commited';
                    print '<input type="button" style="float:right;"  name="close_window" id="close_window" value="Close Window" title="Cick here to close this window" onclick="window.close(\'commited\');">';
                }
                ?>
        </div>
        </form>
    </body>
</html>

<?php

function detail_get_html_input_str( $detail, $repeatCount, $is_master, $use_checkbox = false ) {
    global $mysqli;

    $is_type_repeatable = $repeatCount != 1;
    $rv = array();

    foreach($detail as $rg){
        $detail_id = $rg['dtl_ID'];
        $detail_type = $rg['dtl_DetailTypeID'];
        $detail_val = '';

        if ($rg['dtl_Value']) {

            if ($rg['dtl_Geo']) {
                $detail_val = $rg['dtl_Geo'];
            } elseif($rg['trm_Label']){
                $detail_val = $rg['trm_Label']." (".$rg['dtl_Value'].")";
                //$temp = $rg['dtl_Value'];//trm_ID
            }else{
                $detail_val = $rg['dtl_Value'];
            }


        }elseif ($rg['dtl_UploadedFileID']) {
            $detail_val = mysql__select_value($mysqli,
                    'select ulf_OrigFileName from recUploadedFiles where ulf_ID ='.$rg['dtl_UploadedFileID']);
            $detail_val = htmlspecialchars($detail_val);
        }

        if($detail_val==null) {$detail_val = '';}

        $def_checked = $is_master || $is_type_repeatable ? "checked=checked" : "";

        $input = '<input type="'.($is_type_repeatable || $use_checkbox ? "checkbox":"radio").
        '" name="'.($is_type_repeatable || $use_checkbox ? ($is_master?"keep":"add").$detail_type.'[]':"update".$detail_type).
        '" title="'.($is_type_repeatable || $use_checkbox ? ($is_master?"check to Keep value in Master record - uncheck to Remove value from Master record":"Check to Add value to Master record"):
            ($is_master?  "Click to Keep value in Master record": "Click to Replace value in Master record")).
        '" '.($def_checked).
        ' value="'.($is_type_repeatable?  $detail_id :($is_master? "master":$detail_id)).
        '" id="'.($is_type_repeatable? ($is_master?"keep_detail_id":"add_detail_id"):"update").$detail_id.
        ($use_checkbox ? '" class="not_in_master"' : '').
        '">'.htmlspecialchars( detail_str($detail_type, $detail_val) ).'';

        $rv[]= $input;
    }
    return $rv;
}

function edit_link($rec_id, $label, $id_only=false){

    $link = '<a target="edit" href="'
            .HEURIST_BASE_URL.'?fmt=edit&db='.HEURIST_DBNAME.'&recID='.$rec_id.'">';

    if($id_only){
        $link .= "<span title=\"$label\">$rec_id</span></a> ";
    }else{
        $link .= "$label</a>";
    }

    return $link;
}

//
// $rd_type - detail type id or term id

// Artem: Errneus implementation. dty_ID and trm_ID can have the same values!
//
function detail_str($rd_type, $rd_val)
{
    global $mysqli, $reference_bdts, $enum_bdts;

    if (in_array($rd_type, array_keys($reference_bdts))) { //valid detail type
        if (is_array($rd_val)) {
            $titles = mysql__select_assoc2($mysqli, 'select rec_ID, rec_Title from Records where rec_ID in ('
                    .implode(',',$rd_val).')');

            $rv = array();
            foreach ($rd_val as $val){
                $rv[] = edit_link($val, $titles[$val], true);
            }
            return $rv;
        }
        elseif($rd_val>0) {
            $title =  mysql__select_value($mysqli, 'select rec_Title from Records where rec_ID ='.$rd_val);
            return edit_link($rd_val, $title, true);
        }
    }
    /*
    elseif($rd_type == 158) {
    if (is_array($rd_val[0])) {
    foreach ($rd_val as $val)
    $rv[] = $val['per_citeas'];
    return $rv;
    }
    else {
    return $rd_val['per_citeas'];
    }
    }
    */
    elseif (in_array($rd_type, array_keys($enum_bdts)) && is_integer($rd_val) ) {
            $res = mysql__select_value($mysqli, 'select trm_Label from defTerms where trm_ID ='.$rd_val);
            if($res){
                return htmlspecialchars($res);
            }
    }

    return $rd_val;
}

// ---------------------------------------------- //
// function to actually fix stuff on form submission
function do_fix_dupe()
{
    global $mysqli, $master_rec_id, $finished_merge, $enum_bdts, $bib_ids_list, $bib_ids, $instant_merge;

    $finished_merge = true;

    $master_details = array();
    $res = $mysqli->query('select dtl_DetailTypeID, dtl_Value, dtl_ID, dtl_UploadedFileID, '
      .' if(dtl_Geo is not null, ST_asWKT(dtl_Geo), null) as dtl_Geo, trm_Label'
      .' from recDetails  left join defTerms on trm_ID = dtl_Value '
      .' where dtl_RecID = ' . intval($master_rec_id) . ' order by dtl_DetailTypeID, dtl_ID');

    while ($row = $res->fetch_assoc()) {

        $type = $row['dtl_DetailTypeID'];

        if(!in_array($type, array_keys($enum_bdts))){
               $row['trm_Label'] = null;
        }

        if (! array_key_exists($type, $master_details)) {
            $master_details[$type] = array();
        }

        array_push($master_details[$type], $row);
    }
    $res->close();

    $master_rectype_id = mysql__select_value($mysqli, 'SELECT rec_RecTypeID FROM Records where rec_ID='.intval($master_rec_id));

    $dup_rec_ids=array();
    if(in_array($master_rec_id, $bib_ids )){
        $dup_rec_ids = array_diff($bib_ids, array($master_rec_id) );
    }

    $dup_rec_list = '(' . join(',', prepareIds($dup_rec_ids)) . ')';
    $add_dt_ids = array();// array of detail ids to insert for the master record grouped by detail type is
    $update_dt_ids = array();// array of detail ids to get value for updating the master record
    $keep_dt_ids = array();// array of master record repeatable detail ids to keep grouped by detail type id- used to find master details to remove
    //parse form data
    foreach($_REQUEST as $key => $value){
        preg_match('/(add|update|keep)(\d+)/',$key,$matches);
        if (! $matches) {continue;}
        $prepared_values = array();
        if(is_array($value)){
            foreach($value as $idx => $val){
                if(intval($val)>0) {$prepared_values[] = intval($val);}
            }
        }elseif(intval($value)>0){
            $prepared_values[] = intval($value);
        }
        if(!empty($prepared_values)){
            switch (strtolower($matches[1])){
                case 'add':
                    $add_dt_ids[$matches[2]] = $prepared_values;
                    break;
                case 'update':
                    if ($value != "master") {$update_dt_ids[$matches[2]] = $prepared_values[0];}
                    break;
                case 'keep':
                    $keep_dt_ids[$matches[2]] = $prepared_values;
                    break;
                default;
            }
        }
    }


    // set modified on master so the changes will stick  aslo update url if there is one.
    $now = date(DATE_8601);
    $rec_values = array('rec_ID'=>$master_rec_id, "rec_Modified"=>$now);
    if(@$_REQUEST['URL']){
        $rec_values['rec_URL'] = $_REQUEST['URL'];
    }

    mysql__insertupdate($mysqli, 'Records', 'rec_', $rec_values);

    //process keeps - which means find repeatables in master record to delete  all_details - keeps = deletes
    //get array of repeatable detail ids for master
    $master_rep_dt_ids = array();
    $master_rep_dt_ids = mysql__select_list2($mysqli,
            'select rst_DetailTypeID from defRecStructure WHERE rst_MaxValues != 1 and rst_RecTypeID = '
            .intval($master_rectype_id));

    $master_rep_detail_ids = array();
    foreach($master_rep_dt_ids as $rep_dt_id ){
        if (array_key_exists($rep_dt_id,$master_details)){
            foreach ($master_details[$rep_dt_id]as $detail){
                array_push($master_rep_detail_ids, intval($detail['dtl_ID']));
            }
        }
    }

    //get flat array of keep detail ids
    if (is_array($keep_dt_ids) && !empty($keep_dt_ids)){
        $master_keep_ids = array();
        foreach($keep_dt_ids as $dt_id => $details){
            foreach($details as $detail){
                array_push($master_keep_ids,intval($detail));
            }
        }
    }
    //diff the arrays  don't delet yet as the user might be adding an existing value
    $master_delete_dt_ids = array();
    if($master_rep_detail_ids){ $master_delete_dt_ids = array_diff($master_rep_detail_ids,$master_keep_ids);}//ART HERE   $master_keep_ids
    //FIXME add code to remove any none repeatable extra details
    //for each update
    if ($update_dt_ids){
        $update_detail=array();
        foreach($update_dt_ids as $rdt_id => $rd_id){
            //look up data for detail and
            $update_detail = mysql__select_row_assoc($mysqli, 'select * from recDetails where dtl_ID='.intval($rd_id));
            // if exist in master details  update val
            if(in_array($rdt_id,array_keys($master_details))){
                //@todo what about geo and file fields

                $rec_detail = array('dtl_ID'=>intval($master_details[$rdt_id][0]['dtl_ID']), 'dtl_Value'=>$update_detail['dtl_Value']);
                mysql__insertupdate($mysqli, 'recDetails', 'dtl_', $rec_detail);//update in master

                // else  insert the data as detail for master record
            }else {
                unset($update_detail['dtl_ID']);//get rid of the detail id the insert will create a new one.
                $update_detail['dtl_RecID'] = $master_rec_id;   // set this as a detail of the master record

                mysql__insertupdate($mysqli, 'recDetails', 'dtl_', $update_detail);//insert to master
            }
        }//foreach
    }
    //process adds
    if($add_dt_ids){
        $add_detail = array();
        // for each add detail
        foreach($add_dt_ids as $key => $detail_ids){
            foreach($detail_ids as $detail_id){
                // since adds are only for repeatables check if it exist in delete array ?yes - remove from delete list if there
                if ($key_remove = array_search($detail_id, $master_delete_dt_ids)!== FALSE){      //FIXME need to compare the value not the dtl_ID (they will always be diff)
                    //remove from array
                    unset($master_delete_dt_ids[$key_remove]);
                }else{ //no  then lookup data for detail and insert the data as detail under the master rec id
                    $add_detail = mysql__select_row_assoc($mysqli, 'select * from recDetails where dtl_ID='.$detail_id);
                    unset($add_detail['dtl_ID']);//the id is auto set during insert
                    $add_detail['dtl_RecID'] = $master_rec_id;

                    mysql__insertupdate($mysqli, 'recDetails', 'dtl_', $add_detail);
                #000000
            }
        }
    }
    }

    foreach ($dup_rec_ids as $dup_rec_id) {
        //saw FIXME we should be updating the chain of links
        //find all references to $dup_rec_id that will be removed
        $dup_rec_id = intval($dup_rec_id);
        if($dup_rec_id>0){
            $mysqli->query('update recForwarding set rfw_NewRecID='.$master_rec_id.' where rfw_NewRecID='.$dup_rec_id);
            $mysqli->query('insert into recForwarding (rfw_OldRecID, rfw_NewRecID) values ('.$dup_rec_id.', '.$master_rec_id.')');
        }
        //saw FIXME  we should update the relationship table on both rr_rec_idxxx  fields
    }

    // move dup bookmarks and tags to master unless they are already there
    //get bookmarkid =>userid for bookmarks of master record
    $master_bkm_UGrpIDs = mysql__select_assoc2($mysqli, 'select bkm_ID, bkm_UGrpID usrBookmarks WHERE bkm_recID = '.$master_rec_id);
    //get kwd_ids for  all bookmarks of master record
    $master_tag_ids = mysql__select_list2($mysqli, 'select rtl_TagID from usrRecTagLinks WHERE rtl_RecID = '.$master_rec_id);
    //get bookmarkid => userid of bookmarks for dup records
    $dup_bkm_UGrpIDs = mysql__select_assoc2($mysqli, 'select bkm_ID, bkm_UGrpID usrBookmarks WHERE bkm_recID in '. $dup_rec_list);


    // if dup userid already has a bookmark on master record then add dup bkm_ID to delete_bkm_IDs_list else add to  update_bkm_IDs
    $update_bkm_IDs  = array();
    $delete_bkm_IDs = array();
    $dup_delete_bkm_ID_to_master_bkm_id = array();
    //for every user or group that bookmarks a dup record if it already bookmarks the master then mark it for deletion
    // otherwise mark it for update to point to the master record
    if($dup_bkm_UGrpIDs){
        foreach ($dup_bkm_UGrpIDs as $dup_bkm_ID => $dup_bkm_UGrpID){
            if (!isEmptyArray($master_bkm_UGrpIDs) && $matching_master_bkm_ID = array_search($dup_bkm_UGrpID,$master_bkm_UGrpIDs))
            {
                array_push($delete_bkm_IDs, $dup_bkm_ID);
                $dup_delete_bkm_ID_to_master_bkm_id[$dup_bkm_ID] = $matching_master_bkm_ID;
            }else{
                array_push($update_bkm_IDs, $dup_bkm_ID);
                $master_bkm_UGrpIDs[$dup_bkm_ID] = $dup_bkm_UGrpID;
            }
        }
    }
    //move duplicate record bookmarks for users without bookmarks on the master record
    $update_bkm_IDs_list  = '('.join(',',$update_bkm_IDs). ")";
    $delete_bkm_IDs_list  = '('.join(',',$delete_bkm_IDs). ")";

    if (strlen($update_bkm_IDs_list)>2) { // update the bookmarks and tags that are not in the master
        $mysqli->query('update usrBookmarks set bkm_recID='.$master_rec_id.' where bkm_ID in '.$update_bkm_IDs_list);
    }
    // process to be deleted dup bookmarks
    foreach ($delete_bkm_IDs as $delete_dup_bkm_ID) {
        //copy soon to be deleted dup bookmark data to master record bookmark  by concat notes and pwd_reminder, max of ratings and copy zotero if non existant
        $master_bkm_ID = @$dup_delete_bkm_ID_to_master_bkm_id[$delete_dup_bkm_ID];
        if(!($master_bkm_ID>0 && $delete_dup_bkm_ID>0)) {continue;}

        $master_pers_record = mysql__select_row_assoc($mysqli, 'select * from usrBookmarks where bkm_ID='.$master_bkm_ID);
        $delete_dup_pers_record = mysql__select_row_assoc($mysqli, 'select * from usrBookmarks where bkm_ID='.$delete_dup_bkm_ID);

        if(!($master_pers_record && $delete_dup_pers_record)) {continue;}


        if(strlen(@$delete_dup_pers_record['bkm_PwdReminder'])>0){
            $master_pers_record['bkm_PwdReminder'] = $master_pers_record['bkm_PwdReminder'].";". $delete_dup_pers_record['bkm_PwdReminder'];
        }

        $master_pers_record['bkm_Rating'] = max($master_pers_record['bkm_Rating'],$delete_dup_pers_record['bkm_Rating']);
        if (!$master_pers_record['bkm_ZoteroID']){ $master_pers_record['bkm_ZoteroID']= $delete_dup_pers_record['bkm_ZoteroID'];}

        $master_pers_record['bkm_ID'] = $master_bkm_ID;
        mysql__insertupdate($mysqli, 'usrBookmarks', 'bkm_', $master_pers_record);
    }
    //for every delete dup tag link whoses tag id is not already linked to the master record change the record id to master
    //get tag links for the soon to be deleted dup records
    $delete_dup_rtl_ids = mysql__select_assoc2($mysqli, 'select rtl_ID, rtl_TagID FROM usrRecTagLinks WHERE rtl_RecID in'. $dup_rec_list);
    foreach ($delete_dup_rtl_ids as $rtl_ID => $tag_id) {
        if (is_array($master_tag_ids) && !empty($master_tag_ids) && array_search($tag_id,$master_tag_ids)){ //if it's already linked to the master delete it
            $mysqli->query('delete from usrRecTagLinks where rtl_ID = '.$rtl_ID);//FIXME add error code
        }else{ // otherwise point it to the master record
            $mysqli->query('update usrRecTagLinks set rtl_RecID='.$master_rec_id.', where rtl_ID = '.$rtl_ID);
            array_push($master_tag_ids,$tag_id);// add to the array of tagids already on the master record
        }
    }

    // move reminders to master
    $mysqli->query('update usrReminders set rem_RecID='.$master_rec_id.' where rem_RecID in '.$dup_rec_list);//?FIXME  do we need to check reminders like we checked usrBookmarks
    //delete master details
    if(is_array($master_delete_dt_ids) && !empty($master_delete_dt_ids)){
        $master_detail_delete_list = '('.join(',',$master_delete_dt_ids).')';
        $mysqli->query('delete from recDetails where dtl_ID in '.$master_detail_delete_list);//FIXME add error code
    }

    if($instant_merge){
        // Transfer all record pointer to master first
        $mysqli->query("UPDATE recDetails LEFT JOIN defDetailTypes ON dty_ID = dtl_DetailTypeID SET dtl_Value = {$master_rec_id} "
                        ."WHERE dtl_Value IN {$dup_rec_list} AND dty_Type = 'resource'");
    }

    //delete dup details
    $mysqli->query('delete from recDetails where dtl_RecID in '.$dup_rec_list);
    //delete dup usrBookmarks
    if (strlen($delete_bkm_IDs_list)>2) {
        $mysqli->query('delete from usrBookmarks where bkm_ID in '.$delete_bkm_IDs_list);
    }

    // move dup record pointers to master record
    $mysqli->query('update recDetails left join defDetailTypes on dty_ID=dtl_DetailTypeID set dtl_Value='.$master_rec_id.
        ' where dtl_Value in '.$dup_rec_list.' and dty_Type="resource"');

    // remove duplicate target pointers going to master record
    $refs_to_master = $mysqli->query("SELECT dtl_ID, dtl_DetailType, dtl_RecID FROM recDetails LEFT JOIN defDetailTypes ON dty_ID = dtl_DetailTypeID WHERE dtl_Value = $master_rec_id AND dty_Type = 'resource'");
    if($refs_to_master && $refs_to_master->num_rows > 0){

        $found_refs = [];
        $refs_to_delete = [];
        while($row = $refs_to_master->fetch_row()){

            if(!array_key_exists($row[1], $found_refs)){ // check if field has been handled
                $found_refs[$row[1]] = [ $row[2] ];
                continue;
            }

            if(!in_array($row[2], $found_refs[$row[1]])){ // check if source record has been handled
                $found_refs[$row[1]][] = $row[2];
                continue;
            }

            $refs_to_delete[] = $row[0];// duplicate value
        }

        if(!empty($refs_to_delete)){
            $mysqli->query("DELETE FROM recDetails WHERE dtl_ID IN (". join(',', prepareIds($refs_to_delete)) .")");
        }
    }

    //delete dups
    $mysqli->query('delete from Records where rec_ID in '.$dup_rec_list);

    //try to get the record to update title and hash
    // calculate title, do an update
    $mask = mysql__select_value($mysqli, 'select rty_TitleMask from defRecTypes where rty_ID='.$master_rectype_id);
    if ( $mask ) {

        $new_title = TitleMask::execute($mask, $master_rectype_id, 0, $master_rec_id);

        if ($new_title!=null) {
            $query = 'update Records set rec_Title=? where rec_ID=?';
            mysql__exec_param_query($mysqli, $query, array('si',$new_title, $master_rec_id));
        }
    }
    //reload with flag that operation is completed
    redirectURL('combineDuplicateRecords.php?db='.HEURIST_DBNAME.'&finished_merge=1&bib_ids='.$bib_ids_list);
}
?>