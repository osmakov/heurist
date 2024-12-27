<?php

/**
* Page for popup modal dialog to perform record batch actions
*
*    add/edit/delete details
*    change record type
*
*    STRUCTURE:
*    header that describes the action
*    selector of records: all, selected, by record type
*    widget to enter data
*    request to server
*    results
*        given
*        processed
*        rejected (rights)
*        error
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


/*
workflow

resultList.js -> resultListMenuSelected.html/resultListMenu.js

detailBatchEditPopup opens either

to convert
    recordAddLink - define links/relationship -> RecordMgr.batch_details for links, RecordMgr.save new record for relationship
    recordAction - change details in batch    -> RecordMgr.batch_details

    recordAccess - define ownership and access rights  -> h3 executeAction("set_wg_and_vis") or just close window
    recordAdd - define record add initial preferences  -> save prefs and RecordMgr.add -> record_edit.php -> recordModify.php

todo
*    recordRate - assign record rating -> EntityMgr.doRequest -> dbUsrBookmarks.php
*    recordBookmark - assign/remove record bookmark -> EntityMgr.doRequest -> dbUsrBookmarks.php

*    recordDelete -> RecordMgr.remove or (EntityMgr.doRequest) -> dbRecords.php
     recordTitle - update titles -> RecordMgr.remove (or EntityMgr.doRequest) -> dbRecords.php use TitleMask class

*    recordTag - add/remove tags in batch   -> EntityMgr.doRequest ->  dbUsrTags (use dbUsrBookmarks)

    recordNotify - send email about record

on server side - controller
RecordMgr.add, save, duplicate, remove  -> record_edit.php -> recordModify.php

RecordMgr.batch_details -> record_batch.php  -> recordsBatch



*/




define('LOGIN_REQUIRED',1);

require_once 'initPage.php';

//verify parameter action
$action_type = @$_REQUEST['action'];
$allowed_actions = array('add_detail','replace_detail','delete_detail','rectype_change',
            'extract_pdf','url_to_file','local_to_repository','reset_thumbs',
            'case_conversion','nl2br','translation');
if(!in_array($action_type, $allowed_actions)){
    //@todo - it does not work since initPage already produces some output
    // need to call this piece of code with callback function in initPage after system itit
    redirectURL(ERROR_REDIR.'?msg=Action is not defined or not allowed');
    exit;
}

?>
<script type="text/javascript" src="recordAction.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/temporalObjectLibrary.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_exts.js"></script>


<script type="text/javascript">
    // Callback function on page init complete
    function onPageInit(success){
        if(success){

            var action = window.hWin.HEURIST4.util.getUrlParameter('action', window.location.search);
            var scope = window.hWin.HEURIST4.util.getUrlParameter('scope', window.location.search);
            var field_type = window.hWin.HEURIST4.util.getUrlParameter('ft', window.location.search);
            var field_value = window.hWin.HEURIST4.util.getUrlParameter('value', window.location.search);

            var recordAction = new hRecordAction(action, scope, field_type, field_value);
        }
    }
</script>
<style type="text/css">
    #div_result > div > span {
        min-width:200px;
        display:table-cell;
        font-size:1.1em;
    }

.calendars-jumps .calendars-cmd-prev, .calendars-jumps .calendars-cmd-next { width: 20%;}
.calendars-month-header, .calendars-nav, .calendars-month th,
.calendars-month-header select, .calendars-ctrl, .calendars a{
    background-color:lightgray;
    color:black;
}
.calendars-month, .calendars{
    border-color:lightgray;
}
.calendars-month table, .calendars-month-header select{
    font-size: 1.2em;
}
</style>
</head>

<!-- HTML -->
<body style="overflow:hidden;" class="ui-heurist-explore">

    <div id="div_parameters" class="popup_content_div" style="overflow-y:auto;overflow-x:hidden;bottom:3.5em;">

        <div id="div_header" style="padding: 0.2em; min-width: 600px;">

        </div>

        <fieldset><legend style="display:none"></legend>
            <div style="padding: 0.2em; width: 100%;" class="input">
                <div class="header" style="padding: 0 16px 0 16px;"><label for="sel_record_scope">Records scope:</label></div>
                <select id="sel_record_scope" class="text ui-widget-content ui-corner-all" style="max-width:30em"></select>
            </div>
            <div id="div_sel_fieldtype" style="padding: 0.2em; min-width: 600px;display:none;" class="input">
                <div class="header" style="padding: 0 16px 0 16px;"><label for="sel_fieldtype">Field to modify:</label></div>
                <select id="sel_fieldtype" class="ui-widget-content ui-corner-all" style="max-width:30em"></select>
            </div>

            <div id="div_sel_rectype" style="padding: 0.2em; min-width: 600px;display:none;" class="input">
                <div class="header" style="padding: 0 16px 0 16px;"><label for="sel_recordtype">Convert to record type:</label></div>
                <select id="sel_recordtype" style="max-width:30em"></select>

                <div id="btnAddRecord" style="font-size:0.9em;display:none;margin:0 30px"></div>
                <div id="btnAddRecordInNewWin" style="font-size:0.9em;display:none;"></div>
            </div>


            <div style="padding: 0.2em; width: 100%;" class="input">
                <div class="header" style="padding:16px;"><label for="cb_add_tags">Tag affected records (auto-generated tag)</label></div>
                <input id="cb_add_tags" type="checkbox" class="text ui-widget-content ui-corner-all">
            </div>

        </fieldset>

        <div id="div_widget" style="padding-left: 16px; width: 100%;">
            <fieldset><legend style="display:none"></legend>
            </fieldset>
        </div>

    </div>

    <div id="div_result" class="content_div" style="display: none;">
        RESULT
    </div>

    <div class="ui-dialog-buttonpane ui-widget-content ui-helper-clearfix popup_buttons_div" style="padding:10px">
        <div class="ui-dialog-buttonset">
            <div id="btn-ok" class="ui-state-disabled">OK</div>
            <div id="btn-cancel">Cancel</div>
        </div>
    </div>

    <div class="loading" style="width:100%;height:100%;display: none;">
    </div>
</body>
</html>
