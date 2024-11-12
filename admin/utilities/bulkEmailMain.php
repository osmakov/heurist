<?php

/**
*  Email Users of any Heurist database located on this server, requires a Heurist Database + System Administrator password
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Brandon McKay   <blmckay13@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

define('PDIR','../../');//need for proper path to js and css

use hserv\utilities\USanitize;
use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
require_once dirname(__FILE__).'/bulkEmailSystem.php';

$sysadmin_pwd = USanitize::getAdminPwd();

if (@$_REQUEST["exportCSV"] == 1) {

    if($system->verifyActionPassword($sysadmin_pwd, $passwordForServerFunctions)){
        echo "The System Administrator password is invalid, please re-try in the previous tab/window";
    }else{
        getCSVDownload($_REQUEST);
    }
    exit;
}

if ( !isset($_REQUEST['db']) || $system->verifyActionPassword($sysadmin_pwd, $passwordForServerFunctions) ){
    ?>

    <h3> A Heurist database and Server Manager password are required to enter this function </h3>

    <?php
    exit;
} elseif (isset($_REQUEST['databases']) && isset($_REQUEST['users']) && isset($_REQUEST['emailBody'])
            && isset($_REQUEST['db']) && isset($sysadmin_pwd)) {

    if($system->verifyActionPassword($sysadmin_pwd, $passwordForServerFunctions)){

        echo "The System Administrator password is invalid, please re-try in the previous tab/window";
        exit;
    } else {

        $rtn = sendSystemEmail($_REQUEST);

        if ($rtn["status"] == "ok") {
            echo "<br><br><div>A receipt of the process has been saved as a Notes Record<br><br>Record ID => "
                    . $rtn["data"] ."<br>Record Title => ". $rtn["rec_Title"] .DIV_E;
        }

        exit;
    }
}

$mysqli = $system->get_mysqli();

$emails = array();

$has_emails = false;

$current_db = HEURIST_DB_PREFIX . htmlspecialchars($_REQUEST['db']);

$email_rectype_id = ConceptCode::getRecTypeLocalID("2-9");
if (empty($email_rectype_id)) {
    print "Unable to retrieve the id for the Email record type.<br>Please download this record type from the Heurist Core Definitions database, available via Design > Browser templates.";
    exit;
}

$query = "SELECT rec_ID, rec_Title FROM Records WHERE rec_RecTypeID = "
            . $email_rectype_id
            . " AND rec_Title != '' AND rec_Title IS NOT NULL AND rec_FlagTemporary != 1";

$email_list = $mysqli->query($query);
if (!$email_list) {
    print "Either unable to retrieve Email records from the current database, Error => " . $mysqli->error. ", Query => " .$query;
    exit;
}

while($email = $email_list->fetch_row()){

    if(empty($email[1])) {
        continue;
    }

    $emails[$email[0]] = $email[1];//id -> title

    $has_emails = true;
}

if(!$has_emails || empty($emails)) {
    print "<br><br>This function sends bulk emails based on text in a selected <i>Email</i> record<br><br>"
    . "<strong>" . $current_db . " contains no valid Email records.</strong><br><br>"
    . "<strong>Please create an Email record in the database containing the text<br>"
    . "you want to send out, using ##xxxx## markers for values to be inserted.</strong><br><br>"
    . "The Email record to be used must contain a title field and a short summary field - the latter will be used as the email's body. The title and body can be edited before sending. <br>"
    . "If you want to create your email on-the-fly simply create a dummy record with placeholders for title and body to enable this function. <br><br>"
    . "Placeholders that will be replaced with proper values (case insensitive):<br><br>"
    . "##firstname## &rarr; User's First Name,<br>"
    . "##lastname## &rarr; User's Last Name,<br>"
    . "##email## &rarr; User's Email,<br>"
    . "##database## &rarr; Database Name,<br>"
    . "##dburl## &rarr; Database URL,<br>"
    . "##records## &rarr; Record Count, and<br>"
    . "##lastmodified## &rarr; Date of the Last Modified Record<br>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" xml:lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        <title>Heurist System Email</title>

        <!-- Style Sheets -->
        <!--<link rel="stylesheet" type="text/css" href="../../external/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />-->
        <link rel=icon href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">

<?php
        includeJQuery();
?>

        <link rel="stylesheet" type="text/css" href="../../h4styles.css">
        <link rel="stylesheet" type="text/css" href="../../h6styles.css">

        <!-- Scripts -->
        <script type="text/javascript" src="../../hclient/core/detectHeurist.js"></script>

        <script type="text/javascript" src="../../hclient/core/utils.js"></script>
        <script type="text/javascript" src="../../hclient/core/utils_ui.js"></script>
        <script type="text/javascript" src="../../hclient/core/utils_msg.js"></script>

        <!-- Inner Styling and Script -->
        <style type="text/css">

            .label {
                font-size: 14px;

                height: 20px;

                padding: 5px;
            }
            .label:nth-child(odd) {
                background:-moz-linear-gradient(center top, #FFFFFF, #EEEEEE) repeat scroll 0 0 transparent;
                background:-webkit-gradient(linear, left top, left bottom, from(#FFFFFF), to(#EEEEEE));
            }
            .label:nth-child(odd):hover {
                background:-moz-linear-gradient(center top, #EFEFEF, #DDDDDD) repeat scroll 0 0 transparent;
                background:-webkit-gradient(linear, left top, left bottom, from(#EFEFEF), to(#DDD));
            }
            .label:nth-child(even) {
                background:-moz-linear-gradient(center top, #EDF5FF, #EDF5FF) repeat scroll 0 0 transparent;
                background:-webkit-gradient(linear, left top, left bottom, from(#EDF5FF), to(#EDF5FF));
            }

            .instruction {
                font-size: 0.9em;
            }

            input[type=number] {
                font-size: 0.9em;

                margin: 2px;

                width: 70px;
            }

            #recModifiedLogic-button {
                min-width: 8em !important;

                margin-right: 5px;
            }

            #recModifiedSel-button {
                min-width: 7em !important;

                margin-left: 5px;
            }

            #recModifiedSel-button > .ui-selectmenu-text {
                padding-right: 0px;
            }

            /* Active State CSS */
            span.ui-button.ui-state-active {
                color: #000 !important;
                background: #F6F6F6 !important;
            }

            div.ui-state-active {
                color: #000 !important;
                background: #DDDDDD !important;
            }

            /* Misc */
            .non-selectable {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;

                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
                user-select: none;
            }

            .t-row {
                display: block;

                margin: 10px 0px 30px 0px;
            }

            .l-col {
                float: left;

                min-width: 15%;
                max-width: 25%;
            }

            #dbArea {
                max-height: 1px;
            }

            #dbSelection {
                overflow-y: auto;

                max-height: 75%;
            }

            .r-col {
                float: left;

                max-width: 70%;

                margin: 20px 0px 0px 65px;
            }

            span.truncate {
                display: inline-block;

                max-width: 250px;

                vertical-align: middle;
            }

        </style>

        <script type="text/javascript">

            window.history.pushState({}, '', '<?php echo htmlspecialchars($_SERVER['PHP_SELF']);?>');

            var all_emails = <?php echo json_encode($emails)?>;// Object of Email records id->title

            var current_db = "<?php echo $current_db ?>";
            var getting_databases = false; // Flag for database retrieval operation in progress; true - general, 1 - intial list, false - none
            var run_filter = false;
            var isFormSubmit = false;

            const handled_sort = ['name', 'rec_count', 'last_update'];
            var database_details = null; // [{name: db_name, rec_count: db_rec_count, last_update: db_last_update}, ...]

            //
            // Get list of currently selected databases
            //
            function getDbList(){

                var checked_dbs = $("#dbSelection").find(".dbListCB:checked");
                var dbs = [];
                checked_dbs.each(function(idx, ele){
                    dbs.push($(ele).attr("id"));
                });

                //input[name="databases"]
                $('#db_list').val(dbs.join(','));
                
                return dbs;
            }

            //
            // Get list of all databases in current list
            //
            function getAllDbs() {

                var dbs = [];

                var checked_dbs = $("#dbSelection").find(".dbListCB");
                checked_dbs.each(function(idx, ele){
                    dbs.push($(ele).attr("id"));
                });

                return dbs;
            }

            //
            // Prepare and run export script
            //
            function doExportCSV(e) {

                if(!validateForm(e)) {
                    return false;
                }

                //prevent dbl click
                if(isFormSubmit){
                    return;
                }
                isFormSubmit = true;

                var action = $("#emailOptions").attr("onsubmit");

                $("input[name='exportCSV']").val(1);

                /*
                var data = {};

                $.map($("#emailOptions").serializeArray(), function(obj, idx) {
                    data[obj['name']] = obj['value'];
                });
                $("#exportData").val(data);
                */
                getDbList();
                $("#emailOptions").trigger('submit');


                $("input[name='exportCSV']").val('');

                setTimeout('isFormSubmit=false', 5000);

                return false;
            }

            //
            // Valid main form
            //
            function validateForm(e) {

                var isValid = true;

                var err_text = "The following actions are required:<br><br>";
                var messages = {
                    "dbs": "Select at least one database for use<br>",
                    "workgroups": "Select at least one workgroup for use<br>",
                    "title": "Please enter a Email Title<br>",
                    "body": "Please enter a Email Body<br>",
                    "pwd": "Enter the System Admin password to proceed<br>",
                    "invalid_count": "Record count needs to be a non-negative number<br>",
                    "invalid_period": "Last modified amount needs to be a non-negative number higher than one<br>"
                };

                var $dbSel = $("#dbSelection");
                var $emailTitle = $("#emailTitle");
                var $emailBody = $("#emailBody");
                var $recCount = $("#recTotal");
                var $lmPeriod = $("#recModified");

                if(!$dbSel.find("input[type='checkbox']").is(":checked")){

                    err_text += messages["dbs"];
                    isValid = false;
                }

                if(window.hWin.HEURIST4.util.isempty($emailTitle.val())){

                    isValid = false;
                    err_text += messages["title"];
                }

                if(window.hWin.HEURIST4.util.isempty($emailBody.val())){

                    isValid = false;
                    err_text += messages["body"];
                }

                if($recCount.val() < 0){

                    isValid = false;
                    err_text += messages["invalid_count"];
                }

                if($lmPeriod.val() <= 0 && !$lmPeriod.attr("disabled")){

                    isValid = false;
                    err_text += messages["invalid_period"];
                }

                if(window.hWin.HEURIST4.util.isempty($("#sm_pwd").val())){

                    err_text += messages["pwd"];
                    isValid = false;
                }

                if(!isValid) {
                    window.hWin.HEURIST4.msg.showMsgFlash(err_text, 5000);
                }

                return isValid;
            }

            //
            // Setup database list (left hand section)
            //
            function setupDBSelection(dbs) {

                var $db_selection = $("#dbSelection");

                if(window.hWin.HEURIST4.util.isempty(dbs)){
                    window.hWin.HEURIST4.msg.showMsgFlash("There are no databases based on the filters");
                    $("#filterMsg").hide();
                    return;
                }

                $.each(dbs, function(key, value) {

                    var name = value.substring(4);

                    $db_selection.append(
                        "<div class='label non-selectable' title='"+ name +"'> "
                        + '<label><input type="checkbox" class="dbListCB" id="'+value+'" value="'+ value +'"><span class="truncate">' + name + "</span></label>"
                        + '<label data-id="'+value+'"></label>'
                      + '</div>'
                    );
                });

                $db_selection.find("div")
                    .on('dblclick', function(e){

                        if($(e.target).is('div')){

                            $(e.target).find('input').trigger('click');
                        }
                    });

                $("#dbArea").find("#allDBs")
                    .on("click", function(e){

                        var is_checked = $(e.target).is(":checked");

                        $db_selection.find(".dbListCB").prop("checked", is_checked);

                        getDBCount();
                        getUserCount();
                    })
                    .css("vertical-align", "middle");

                $db_selection.find(".dbListCB").on("change", () => {
                    getDBCount();
                    getUserCount();
                });

                $("#dbCount").text("0");
                $("#userCount").text("0");
                $("#filterMsg").hide();
            }

            //
            // Sort database list
            //
            function applyDBSort(order = 'name') {

                if(getting_databases){
                    setTimeout(() => {
                        applyDBSort(order);
                    }, 2000);
                    return;
                }

                let $db_list = $('#dbSelection');

                if(!database_details || database_details.length == 0){ // TODO: attempt another retrieval
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: 'Unable to apply sort order to database list, there were no databases found/provided.',
                        error_title: 'Database sorting failed'
                    });
                    return;
                }

                if(!order){
                    order = $('input[name="dbSortBy"]:checked').attr('id');
                }
                if(!order || window.hWin.HEURIST4.util.isempty(order) || !handled_sort.includes(order)){
                    order = 'name';
                }

                if($db_list.attr('data-order') == order){
                    return;
                }

                $db_list.attr('data-order', order);

                // Sort database_details
                database_details.sort((a, b) => {

                    let a_item = a[order];
                    let b_item = b[order];

                    if(order == 'name'){ // to lower case first
                        a_item = a_item.toLowerCase();
                        b_item = b_item.toLowerCase();
                    }

                    if(order == 'rec_count'){
                        return a_item - b_item;
                    }/*else{
                        return a_item < b_item;
                    }*/
                    if(a_item < b_item){
                        return -1;
                    }else if(a_item > b_item){
                        return 1;
                    }
                    return 0;
                });

                let $prev_child = null;

                for(let i = 0; i < database_details.length; i++){

                    const name = database_details[i]['name'];
                    let $ele = $db_list.find('input[id="'+ name +'"]');

                    if($ele.length == 0){
                        continue;
                    }

                    $ele = $ele.parent().parent();
                    if($prev_child){
                        $ele.insertAfter($prev_child);
                        $prev_child = $ele;
                    }else{
                        $ele.prependTo($db_list);
                        $prev_child = $ele;
                    }
                }
            }

            //
            // Setup user filtering elements
            //
            function setupUserSelection() {

                var $user_selection = $('#userSelection');

                var select = $("<select>")
                    .attr("name", "users")
                    .attr("id", "userSel")
                    .appendTo($user_selection);

                var options = [
                    {key:"owner", title:"Database Owner/s", selected: true},
                    {key:"manager", title:"Administrators - Database Managers"},
                    {key:"admin", title:"Administrators - All Workgroups"},
                    {key:"user", title:"All Users"}
                ];

                window.hWin.HEURIST4.ui.createSelector(select.get(0), options);

                if(select.hSelect("instance")!=undefined) {
                    select.hSelect("widget").css({"margin-top": "5px", "min-width": "15em", "width": "310px"});
                }

                $("<span id='wg-error' class='error-msg non-selectable' style='width: 234px;'></span>").appendTo($user_selection).hide();

                select.on("change", getUserCount);
            }

            //
            // Setup email selection elements
            //
            function setupEmailSelection() {

                var $email_selection = $("#emailOutline");

                var options = [
                    {key:"null", title: "Select a email record..."},
                ];

                $.each(all_emails, function(idx, value){

                    var opt = {key: idx, title: value};

                    options.push(opt);
                });

                window.hWin.HEURIST4.ui.createSelector($email_selection.get(0), options);

                $email_selection.on({
                    change: function(event) {

                        var emailDraft = $(event.target).val();

                        if (emailDraft == null || emailDraft == "null") {
                            $("#emailTitle").text("");
                            $("#emailBody").text("");
                        } else {
                            getEmailDetails(emailDraft);
                        }
                    }
                });
            }

            //
            // Setup remaining elements
            //
            function setupOtherElements() {

                var modifySel = $("#recModifiedSel");
                var modifyLogic = $("#recModifiedLogic");

                window.hWin.HEURIST4.util.setDisabled($("#recModified"), true);
                window.hWin.HEURIST4.util.setDisabled($("#recModifiedLogic-button"), true);

                modifySel.on({
                    change: function(event, data){

                        if($(event.target).val()==='ALL') {
                            window.hWin.HEURIST4.util.setDisabled($("#recModified"), true);

                            window.hWin.HEURIST4.util.setDisabled($("#recModifiedLogic"), true);
                        } else {
                            window.hWin.HEURIST4.util.setDisabled($("#recModified"), false);

                            window.hWin.HEURIST4.util.setDisabled($("#recModifiedLogic"), false);
                        }
                    }
                });

                $("#btnApply").on({
                    click: function(event, data) {

                        if(getting_databases){
                            run_filter = getting_databases == 1;
                            window.hWin.HEURIST4.msg.showMsgFlash('Please wait for the database list to update...', 5000);
                            return;
                        }

                        let cont_height = $('#dbSelection').height() + 100;
                        let cont_width = $('.l-col').width() + 50;
                        let cont_top = $('.l-col').position().top;

                        getting_databases = true;
                        window.hWin.HEURIST4.msg.bringCoverallToFront($('.l-col'), {top: `${cont_top}px`, 'max-height': `${cont_height}px`, width: `${cont_width}px`, color: 'white', opacity: 0.8}, 'Appling database filter...');

                        $("#dbSelection").find(".dbListCB").off("change");
                        $("#dbSelection").empty();
                        $('#allDBs').prop('checked', false).trigger('change');

                        $("#filterMsg").show().text("Filtering Databases...");

                        var data = {
                            db: current_db,
                            db_filtering: {
                                count: $("#recTotal").val(),
                                lastmod_logic: $("#recModifiedLogic").val(),
                                lastmod_period: $("#recModified").val(),
                                lastmod_unit: $("#recModifiedSel").val()
                            },
                            req_id: window.hWin.HEURIST4.util.random()
                        }

                        $.ajax({
                            url: 'bulkEmailOther.php',
                            type: 'POST',
                            data: data,
                            dataType: 'json',
                            cache: false,
                            xhrFields: {
                                withCredentials: true
                            },
                            //fail:
                            error: function(jqXHR, textStatus, errorThrown){

                                window.hWin.HEURIST4.msg.showMsgErr({
                                    message: "An error has occurred with retrieving the filtered list of databases.<br>"
                                        + `Error Details: ${jqXHR.status} => ${errorThrown}<br><br>`
                                        + "Please contact the Heurist team if this problem persists",
                                    error_title: 'Unable to retrieve database list',
                                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                                });
                            },
                            //done:
                            success: function(response, textStatus, jqXHR){

                                if(response.status == "ok"){
                                    setupDBSelection(response.data);
                                    applyDBSort($('input[name="dbSortBy"]:checked').attr('id'));

                                } else {

                                    if(window.hWin.HEURIST4.util.isempty(response.message)){
                                        window.hWin.HEURIST4.msg.showMsgErr({
                                            message: "An unknown error has occurred with retrieving the filtered list of databases.",
                                            status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                                        });
                                    } else {
                                        var msg = response.message + '<br>' + (!window.hWin.HEURIST4.util.isempty(response.error_msg) ? response.error_msg : '');
                                        window.hWin.HEURIST4.msg.showMsgErr({message: msg, error_title: 'Failed to retrieve database list'});
                                    }
                                }
                            },
                            //always:
                            complete: function(jqXHR, textStatus){

                                getting_databases = false;
                                window.hWin.HEURIST4.msg.sendCoverallToBack();

                                getUserCount();

                                if(textStatus == 'success'){
                                    $("#filterMsg").text('Database Filtering is Completed, Loading List');
                                }else{
                                    $("#filterMsg").text('An error has occurred!');
                                }
                            }
                        });
                    }
                });

                $("#btnEmail").on("click", function(event){
                    if(validateForm(event)){
                        getDbList();
                        $("input[name='exportCSV']").val('');
                        $("#emailOptions").trigger('submit');
                    }
                });

                $('.dbSort').on('change', function(event){
                    let order = $('input[name="dbSortBy"]:checked').attr('id');
                    applyDBSort(order);
                });

                $('input[id="name"]').prop('checked', true);
            }

            //
            // Get complete list of databases on current server
            //
            function getInitDbList() {

                getting_databases = 1;

                $.ajax({
                    url: 'bulkEmailOther.php',
                    type: 'POST',
                    data: {db: current_db, db_filtering: "all", req_id: window.hWin.HEURIST4.util.random()},
                    dataType: 'json',
                    cache: false,
                    xhrFields: {
                        withCredentials: true
                    },
                    //fail:
                    error: function(jqXHR, textStatus, errorThrown){

                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: "An error has occurred with retrieving the complete list of databases.<br>"
                                    + `Error Details: ${jqXHR.status} => ${errorThrown}<br><br>`
                                    + "Please contact the Heurist team if this problem persists",
                            error_title: 'Unable to retrieve database list',
                            status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                        });
                    },
                    //done:
                    success: function(response, textStatus, jqXHR){

                        if(response.status == "ok"){
                            database_details = response.data.details;
                            setupDBSelection(response.data.list);
                            //applyDBSort('name'); already in alphabetic order by default

                        } else {

                            if(window.hWin.HEURIST4.util.isempty(response.message)){
                                window.hWin.HEURIST4.msg.showMsgErr({
                                    message: "An unknown error has occurred with retrieving the complete list of databases, please contact the Heurist team.",
                                    error_title: 'Unable to retrieve database list',
                                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                                });
                            } else {
                                var msg = response.message + '<br>' + (!window.hWin.HEURIST4.util.isempty(response.error_msg) ? response.error_msg : '');
                                window.hWin.HEURIST4.msg.showMsgErr({message: msg, error_title: 'Failed to retrieve database list'});
                            }
                        }
                    },
                    complete: function(jqXHR, textStatus){
                        getting_databases = false;

                        if(textStatus == 'success' && run_filter){
                            run_filter = false;
                            $("#btnApply").trigger('click');
                        }
                    }
                });
            }

            //
            // Retrieve selected email details
            //
            function getEmailDetails(id) {

                $.ajax({
                    url: 'bulkEmailOther.php',
                    type: 'POST',
                    data: {db: current_db, get_email: true, recid: id, req_id: window.hWin.HEURIST4.util.random()},
                    dataType: 'json',
                    cache: false,
                    xhrFields: {
                        withCredentials: true
                    },
                    error: function(jqXHR, textStatus, errorThrown){

                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: "An error has occurred with retrieving the Email record short summary field (email body).<br>"
                                    + `Error Details: ${jqXHR.status} => ${errorThrown}<br><br>`
                                    + "Please contact the Heurist team if this problem persists",
                            error_title: 'Failed to retrieve email details'
                        });
                    },
                    success: function(response, textStatus, jqXHR){

                        if(response.status == "ok"){
                            $("#emailTitle").val(response.data[0]);
                            $("#emailBody").text(response.data[1]);
                        } else {

                            if(window.hWin.HEURIST4.util.isempty(response.message)){
                                window.hWin.HEURIST4.msg.showMsgErr({
                                    message: "An unknown error has occurred with retrieving email record details, please contact the Heurist team.",
                                    error_title: 'Unable to retrieve email details',
                                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                                });
                            } else {
                                var msg = response.message + '<br>' + (!window.hWin.HEURIST4.util.isempty(response.error_msg) ? response.error_msg : '');
                                window.hWin.HEURIST4.msg.showMsgErr({message: msg, error_title: 'Failed to retrieve email details'});
                            }
                        }
                    }
                });
            }

            //
            // Display record counts for databases
            //
            function displayRecordCount(data) {

                if(window.hWin.HEURIST4.util.isempty(data)){
                    data = database_details;
                }
                if(window.hWin.HEURIST4.util.isempty(data)){
                    return;
                }

                // Update individual record counts + update total record count for selected databases
                let $db_list = $("#dbSelection");

                let selected_dbs = getDbList();
                let total = 0;

                $.each(data, (db, count) => {

                    if($.isPlainObject(count)){
                        db = count['name'];
                        count = count['rec_count'];
                    }

                    let $ele = $db_list.find('[data-id="'+ db +'"]');

                    if($ele.length > 0){

                        let max_width = $ele.parent().width() - 30;
                        $ele.text('[' + count + ']').css({'float': 'right', 'padding-left': '5px'});

                        if(count > 0 && selected_dbs.indexOf(db) >= 0){
                            total += parseInt(count, 10);
                        }
                    }
                });

                $("#allDBs").parent().parent().find('span').show();
                $("#recCount").text(total);

                set_element_position();
            }

            //
            // Retrieve record count for list of databases
            //
            function getRecordCount() {

                if(getting_databases){
                    window.hWin.HEURIST4.msg.showMsgFlash('Please wait for the database list to update...', 3000);
                    return;
                }

                if(window.hWin.HEURIST4.util.isArrayNotEmpty(database_details) && Object.hasOwn(database_details[0], 'rec_count')){
                    displayRecordCount();
                    return;
                }

                let dbs = getAllDbs();

                if(dbs.length == 0){
                    return;
                }

                var data = {
                    db: current_db,
                    db_list: dbs,
                    rec_count: 1,
                    req_id: window.hWin.HEURIST4.util.random()
                };

                $.ajax({
                    url: 'bulkEmailOther.php',
                    type: 'POST',
                    data: data,
                    dataType: 'json',
                    cache: false,
                    xhrFields: {
                        withCredentials: true
                    },
                    error: (jqXHR, textStatus, errorThrown) => {

                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: "An error has occurred with retrieving the the user count for the selected databases and user type.<br>"
                                    + `Error Details: ${jqXHR.status} => ${errorThrown}<br><br>`
                                    + "Please contact the Heurist team if this problem persists",
                            error_title: 'Failed to retrieve record count'
                        });
                    },
                    success: (response, textStatus, jqXHR) => {

                        if(response.status == "ok"){
                            displayRecordCount(response.data);
                        } else {

                            if(window.hWin.HEURIST4.util.isempty(response.message)){
                                window.hWin.HEURIST4.msg.showMsgErr({
                                    message: "An unknown error has occurred with retrieving the record counts, please contact the Heurist team.",
                                    error_title: 'Unable to retrieve record counts',
                                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                                });
                            } else {
                                var msg = response.message + '<br>' + (!window.hWin.HEURIST4.util.isempty(response.error_msg) ? response.error_msg : '');
                                window.hWin.HEURIST4.msg.showMsgErr({message: msg, error_title: 'Failed to retrieve record count'});
                            }
                        }
                    }
                })
            }

            //
            // Count number of databases selected and update label
            //
            function getDBCount() {

                const $sel_dbs = $("#dbSelection").find(".dbListCB:checked");
                $("#dbCount").text($sel_dbs.length);
            }

            //
            // Get distinct user count for selected databases
            //
            function getUserCount() {

                if(getting_databases){
                    window.hWin.HEURIST4.msg.showMsgFlash('Please wait for the database list to update...', 3000);
                    return;
                }

                var dbs = getDbList();

                if(dbs.length == 0){
                    $("#userCount").text('0');
                    return;
                }

                var data = {
                    db: current_db,
                    user_count: $("#userSel").val(),
                    db_list: dbs.join(','),
                    req_id: window.hWin.HEURIST4.util.random()
                };

                $.ajax({
                    url: 'bulkEmailOther.php',
                    type: 'POST',
                    data: data,
                    dataType: 'json',
                    cache: false,
                    xhrFields: {
                        withCredentials: true
                    },
                    error: function(jqXHR, textStatus, errorThrown){

                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: "An error has occurred with retrieving the the user count for the selected databases and user type.<br>"
                                    + `Error Details: ${jqXHR.status} => ${errorThrown}<br><br>`
                                    + "Please contact the Heurist team if this problem persists",
                            error_title: 'Failed to retrieve user count'
                        });
                    },
                    success: function(response, textStatus, jqXHR){

                        if(response.status == "ok"){
                            $("#userCount").text(response.data);
                        } else {

                            if(window.hWin.HEURIST4.util.isempty(response.message)){
                                window.hWin.HEURIST4.msg.showMsgErr({
                                    message: "An unknown error has occurred with retrieving user counts, please contact the Heurist team.",
                                    error_title: 'Unable to retrieve user count',
                                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                                });
                            } else {
                                var msg = response.message + '<br>' + (!window.hWin.HEURIST4.util.isempty(response.error_msg) ? response.error_msg : '');
                                window.hWin.HEURIST4.msg.showMsgErr({message: msg, error_title: 'Failed to retrieve user counts'});
                            }
                        }
                    }
                });
            }

            //
            // Verify sysadmin password
            //
            function verifySystemAdminPwd() {

                var data = {
                    db: current_db,
                    sysadmin_pwd: $("#sm_pwd").val()
                };

                $.ajax({
                    url: 'bulkEmailOther.php',
                    type: 'POST',
                    data: data,
                    dataType: 'json',
                    cache: false,
                    xhrFields: {
                        withCredentials: true
                    },
                    error: function(jqXHR, textStatus, errorThrown){

                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: "An error has occurred with verifying the System Administrator password.<br>"
                                    + `Error Details: ${jqXHR.status} => ${errorThrown}<br><br>`
                                    + "Please contact the Heurist team if this problem persists",
                            error_title: 'Failed to authenticate'
                        });
                    },
                    success: function(response, textStatus, jqXHR){

                        if(response.status == "ok"){
                            if(response.data == true){

                                getDbList();
                                $("#emailOptions").trigger('submit');
                            }else{
                                window.hWin.HEURIST4.msg.showMsgFlash("The System Administrator password is incorrect.<br>Please re-enter it.", 5000);
                            }
                        } else {

                            if(window.hWin.HEURIST4.util.isempty(response.message)){
                                window.hWin.HEURIST4.msg.showMsgErr({
                                    message: "An unknown error has occurred while attempting to authenticate the system administrator password, please contact the Heurist team.",
                                    error_title: 'Unable to authenticate',
                                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                                });
                            } else { // There is no error_msg
                                window.hWin.HEURIST4.msg.showMsgErr({message: response.message, error_title: "Fail to authenticate"});
                            }
                        }
                    }
                });

                return false;
            }

            function set_element_position(){
                $("#btnEmail")
                    .position({
                        my: "left top+20",
                        at: "left bottom",
                        of: "#sm_pwd"
                    });

                $("#btnCsvExport")
                    .position({
                        my: "left+10 top",
                        at: "right top",
                        of: "#btnEmail"
                    });
            }

            $(document).ready(function() {

                setupUserSelection();
                setupEmailSelection();
                setupOtherElements();

                if(!window.hWin.HR){
                    window.hWin.HR = function(token){return token};
                }

                set_element_position();

                $("#btnCalRecCount").on('click',getRecordCount);

                getInitDbList();
            });

        </script>

    </head>

    <body style="margin: 10px 10px 10px 20px;">

        <div style="font-family:Arial,Helvetica,sans-serif;">
            <h3>Heurist System Email</h3>

            <span class="instruction">
                This tool allows you to email all users / specified types of user on all / selected Heurist databases available on this server. <br><br>
                The email to be sent should be created as a <strong>Email</strong> record in the current database, including subject line, body text and fields to be substituted using ##....## notation. <br><br>
            </span>

            <form id="emailOptions" action="bulkEmailMain.php" method="POST" target="_blank">

                <div class="t-row">

                    <span style="margin-right: 50px;">Filter: </span>

                    <span>

                        <label for="recTotal" class="non-selectable"> Over <input type="number" class="input-num" name="recTotal" id="recTotal" min="0" value="0"> records </label>

                        &nbsp;&nbsp;&nbsp;

                        <span class="non-selectable"> Last modified

                            <select name="recModLogic" id="recModifiedLogic">
                                <option value="more">more than</option>
                                <option value="less" selected>less than</option>
                            </select>

                            <input type="number" class="input-num" name="recModVal" id="recModified" min="1" value="">

                            <select name="recModInt" id="recModifiedSel">
                                <option value="DAY">Days</option>
                                <option value="MONTH">Months</option>
                                <option value="YEAR">Years</option>
                                <option value="ALL" selected>All</option>
                            </select>

                            Ago
                        </span>

                    </span>

                    <span style="margin-left: 15px;">
                        <button type="button" id="btnApply">Apply</button>
                        <span id="filterMsg" style="display: none;">Filtering Databases...</span>
                    </span>

                </div>

                <div class="l-col">

                    <div id="dbArea">

                        <div>Get users from these databases:</div>

                        <div style="margin: 10px 0px;">
                            Sort by:
                            <label><input type="radio" name="dbSortBy" class="dbSort" id="name"> Name</label>
                            <label><input type="radio" name="dbSortBy" class="dbSort" id="rec_count"> Record count</label>
                            <label><input type="radio" name="dbSortBy" class="dbSort" id="last_update"> Last updated</label>
                        </div>

                        <div class="non-selectable" style="margin: 0px 0px 10px 5px;">
                            <label><input type="checkbox" id="allDBs"> Select All</label>
                            <span style="float: right; display: none; margin-right: 10px;">Record count:</span>
                        </div>
                        <div id="dbSelection" data-order="name">
                        </div>

                    </div>

                </div>

                <div class="r-col">

                    <div style="margin-bottom: 20px;">
                        Send email to: <span id="userSelection"></span> &nbsp;&nbsp;&nbsp;
                        Count of distinct users: <span id="userCount">0</span>
                        <button id="btnCalRecCount" style="margin-left: 10px;" onclick="return false;">Count total DB records</button>
                        <span style="float: right; margin-left: 50px;">Number of databases selected: <span id="dbCount">0</span></span>
                        <br>
                        <span style="float: right;">Total count of records (selected databases): <span id="recCount">0</span></span>
                    </div>

                    <div class="non-selectable" style="margin-bottom: 20px;">
                        Email record containing the email outline
                        <br>
                        <select id="emailOutline" name="emailId" style="width: 99%;"></select>
                    </div>

                    <div style="margin-bottom: 20px;">

                        <div style="margin-bottom: 15px;">
                            Email Subject: <input type="text" id="emailTitle" name="emailTitle" style="margin-left: 5px;width: 86.6%;">
                        </div>

                        <div class="non-selectable" style="margin-bottom: 10px;">Email Body (use html tags):</div>
                        <textarea id="emailBody" rows="20" cols="90" name="emailBody"></textarea>
                    </div>

                    <div class="instruction" style="margin-bottom: 51px;">

                            <div style="margin-bottom: 10px;">Placeholders that will be replaced with proper values (case insensitive): </div>

                            <div style="float: left;margin: 0px 20px 35px 0px;">
                                ##firstname## &rarr; User's First Name, <br>
                                ##lastname## &rarr; User's Last Name, <br>
                                ##email## &rarr; User's Email, <br>
                                ##database## &rarr; Database Name
                            </div>

                            <div style="display: inline-block;">
                                ##dburl## &rarr; Database URL, <br>
                                ##records## &rarr; Record Count, and <br>
                                ##lastmodified## &rarr; Date of the Last Modified Record
                            </div>

                    </div>

                    <div id="authenContainer">

                        Please enter the System Manager password to confirm:&nbsp;

                        <input type="password" name="pwd" autocomplete="off" id="sm_pwd" />


                        <label><input type="checkbox" name="use_native" id="use_native" value="1"/>use native mail</label>

                    </div>

                    <div style="margin-top: 10px;">

                        <button style="margin-left: 5px;" type="button" id="btnEmail">Send Emails</button>

                    </div>

                </div>

                <input name="db" value="<?php echo htmlspecialchars($_REQUEST['db']);?>" style="display: none;" readonly />

                <input id="db_list" name="databases" type="hidden" />
                <input name="exportCSV" value="0" type="hidden"/>
            </form>
            <input type="button" id="btnCsvExport" value="Export CSV" onclick="doExportCSV(event)"/>
<!--
            <form id="csvExportForm" action="#" onsubmit="validateForm(event);return false;" style="display: inline-block;">
                <input type="hidden" name="exportdata" id="exportData" />
            </form>
-->
        </div>

    </body>
</html>