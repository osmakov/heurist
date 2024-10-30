<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* Shows an email form
*
* @author      Jan Jaap de Groot <jjedegroot@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     4.0.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
use hserv\utilities\USanitize;

define('PDIR','../../');//need for proper path to js and css
define('MANAGER_REQUIRED', 1);

require_once 'initPageMin.php';

// POST request
if(isset($_POST['data'])) {
    $params = USanitize::sanitizeInputArray();

    $data = json_decode($params['data']);
    $response = "";

    $subject = htmlspecialchars(filter_var($data->subject));// Email subject
    foreach($data->emails as $email) {
        // Determine message & recipients
        USanitize::purifyHTML($email->message);// Email message
        $recipients = $email->recipients; // One or more e-mail adresses
        foreach($recipients as $recipient) {
            // Check if the e-mail address is valid
            $recipient_sanitized = filter_var($recipient, FILTER_VALIDATE_EMAIL);
            if($recipient_sanitized) {
                // Send e-mail
                $result = sendEmail($recipient_sanitized, $subject, $email->message);
                if(!$result){
                    $err = $system->getError();
                    $result = $err['message'];
                }else{
                    $result = 'sent';
                }

                $response .= $recipient_sanitized . " --> " . $result . "\n";
            }else{
                $response .= $recipient . " --> invalid e-mail address\n";
            }
        }
    }

    echo htmlentities($response);
    exit;
}

// GET REQUEST
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <title>Bulk email sender</title>

    <!-- CSS -->
    <?php

    include_once dirname(__FILE__).'/initPageCss.php';
    includeJQuery();

    ?>

    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_dbs.js"></script>

    <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />

    <style>
        #btn_redo {
            cursor: pointer;
            float: right;
            font-size: 1.4em;
            height: 1.8em;
            margin-left: 2px;
            margin-top: 6px;
            width: 1.8em;
        }

    </style>

</head>

<body class="ui-heurist-bg-light" onload="setup()">

    <fieldset style="font-size:0.8em"><legend style="display:none"></legend>

        <span id="selected-records"></span>

        <hr>

        <div style="font-size: smaller; margin-top: 2px;display:block">This function assumes that the records contain - at the least - an email address field. Choose this (required) field in the first dropdown. For each record selected, one email will be sent to the address stored in this field.
            If name fields also exist, these can be selected in the next two dropdowns and may be used in the body of the message. The first three dropdowns show only text fields.
        </div>

        <div>
            <div class="header mandatory"><label for="email">Email:</label></div>
            <select name="email" id="email" class="text ui-widget-content ui-corner-all mandatory"></select>
        </div>
        <div>
            <div class="header"><label for="firstname">First Name:</label></div>
            <select name="firstname" id="firstname" class="text ui-widget-content ui-corner-all"></select>
        </div>
        <div>
            <div class="header"><label for="familyname">Family Name:</label></div>
            <select name="familyname" id="familyname" class="text ui-widget-content ui-corner-all"></select>
        </div>

        <hr>

        <div style="font-size: smaller; margin-top: 2px;display:block">Additional user-defined fields can be selected in the other three dropdowns, which show all available fields. Each selected field can be used in the text of the message and will be substituted in the message body for each email sent.
        </div>

        <div>
            <div class="header"><label for="field1">Field 1:</label></div>
            <select name="field1" id="field1" class="text ui-widget-content ui-corner-all"></select>
        </div>
        <div>
            <div class="header"><label for="field2">Field 2:</label></div>
            <select name="field2" id="field2" class="text ui-widget-content ui-corner-all"></select>
        </div>
        <div>
            <div class="header"><label for="field3">Field 3:</label></div>
            <select name="field3" id="field3" class="text ui-widget-content ui-corner-all"></select>
        </div>

        <hr>

        <div>
            <div class="header_narrow"><label for="subject">Subject :</label></div>
            <input type="text" name="subject" id="subject" class="text ui-widget-content ui-corner-all mandatory"  maxlength="40" style="width:24.2em"/>
        </div>
        <div>
            <div class="header_narrow" style="vertical-align:top"><label for="message">Message :</label></div>
            <textarea name="message2" id="message" rows="8" class="text ui-widget-content ui-corner-all mandatory"  style="margin-top:0.4em;width:25em"></textarea>
            <textarea name="message" id="message-prepared" rows="10"
                                                            class="text ui-widget-content ui-corner-all mandatory"  style="margin-top:0.4em;width:25em;display: none"></textarea>
            <button id="btn_redo" style="display: none" onclick="redo()" class="ui-icon ui-icon-arrowrefresh-1-s"></button>

            <div style="font-size: smaller; margin-top: 2px;">may include html; #fieldname to include content of field</div>
       </div>

       <div style="display:block;padding-top:1em;text-align:right;width:100%">
            <button type="button" id="prepare"
                    class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only"
                    aria-disabled="false" onClick="prepare()">
                    <span class="ui-button-text">Prepare emails</span>
            </button>
       </div>

    </fieldset>


    <!-- Javascript -->
    <script type="text/javascript">
        var storage_key = "email";// Key used to store data
        var dropdowns = ["#email", "#firstname", "#familyname", "#field1", "#field2", "#field3"];// ID's of the dropdowns
        var text_types = ["freetext", "blocktext"];// Text only types for the dropdowns
        var all_types = ["freetext", "blocktext", "memo", "seperator", "numeric", "date", "enum"];// All types that can be selected from the dropdown
        var definitions; // Record type field definitions
        var ids =  window.hWin.HAPI4.selectedRecordIds; // Selected record ID's
        var recordset = window.hWin.HAPI4.currentRecordset.getSubSetByIds(ids);
        var records = recordset.getRecords();// Array of record objects
        var first_record = recordset.getFirstRecord();// First record in the list, used to determine the Record Types

        // Retrieves an item from localStorage
        function getItem(name) {
            return localStorage[storage_key+name];
        }

        // Stores an item in localStorage
        function putItem(name, value) {
            localStorage[storage_key+name] = value;
        }

        // Determines valid options
        function determineOptions(types) {
            // Determine options
            var options = [];
            // Go through each field for the first record type
            definitions.each2(function(dty_ID, detail){

                var field_type = $Db.dty(dty_ID, 'dty_Type');
                // Appropriate type check
                if(types.indexOf(field_type) >= 0) { // Check if this field is allowed
                    options.push({name: detail['rst_DisplayName'], value: dty_ID});
                }
            });

            // Sort alphabetically
            options.sort(function(a, b) {
                return a.name.localeCompare(b.name);
            });

            // Generate HTML
            var html = "<option value=\"-1\" disabled=\"disabled\" selected=\"selected\">Select...</option>";
            if(options.length > 0) {
                for(var i=0; i<options.length; i++) {
                    html += "<option value=\"" +options[i].value+ "\">" + options[i].name + "</option>";// Add field to dropdown
                }
            }
            return html;
        }

        // Fills a dropdown with options
        function fillDropdown(i, options) {
            // Append options to each dropdown
            $(dropdowns[i]).html(options);

            // Remember last selected index
            var selectedIndex = getItem(dropdowns[i]);
            if(selectedIndex) {
                $(dropdowns[i]).prop("selectedIndex", selectedIndex);
            }

            // Listen to dropdown hanges
            $(dropdowns[i]).on('change', function(e) {
                let id = $(this).attr("id");// Dropdown ID
                let value = $(this).prop("selectedIndex");// Selected dropdown index
                putItem("#"+id, value);// Store data
                redo();// Message needs to be re-done
            });
        }

        // Sets up all fields
        function setup() {
            // Selected records
            $("#selected-records").html("# of records selected: " + ids.length);

            // Determine record type of first record

            var rectype = recordset.fld(first_record, 'rec_RecTypeID');// Record type of first record
            definitions = $Db.rst(rectype);

            // TEXT ONLY DROPDOWNS
            var text_options = determineOptions(text_types);
            fillDropdown(0, text_options);
            fillDropdown(1, text_options);
            fillDropdown(2, text_options);

            // OTHER DROPDOWNS
            var all_options = determineOptions(all_types);
            fillDropdown(3, all_options);
            fillDropdown(4, all_options);
            fillDropdown(5, all_options);

            // Setup subject field
            $("#subject").on("keyup", function(e) {
                var text = $(this).val();
                putItem("subject", text);// Store subject text
            });
            $("#subject").val(getItem("subject"));// Set subject text

            // Setup message field
            $("#message").on("keyup", function(e) {
                var text = $(this).val();
                putItem("message", text);// Store message text
            });
            $("#message").val(getItem("message"));// Set message text

        }

        // Swaps the text area's
        function redo() {
            $("#prepare > span").text("Prepare emails");
            $("#btn_redo").hide();// Hide redo button
            $("#message-prepared").slideUp(500, function(e) {  // Hide prepared message
                $("#message").slideDown(500);// Show raw message
            })
        }

         // Determines the actual record value at the given index
        function getValue(record, type, index) {
            // Determine type
            if(type == "freetext" || type =="blocktext" || type == "date") {
                return record.d[index];

            }else if(type == "memo") {
                alert("Memo");

            }else if(type == "seperator") {
                alert("sep");

            }else if(type == "numeric") {
                alert("num");

            }else if(type == "enum") {
                var enumID = record.d[index];
                if(enumID) {
                    return $Db.trm(enumID, 'trm_Label');
                }

            }else if(type == "termlist") { //???
                         alert("terms");

            }

            return null;
        }

        // get list of field types
        function getSelectedFieldTypeIds() {
            var res = [];
            for(var i=0; i<dropdowns.length; i++) {
                // Index selected in the dropdown
                var index = $(dropdowns[i]).val();// field type index
                if(index && index > 0) {
                     res.push(index);
                }
            }
            return res.join(',');
        }


        // Replaces the raw message text with fields in a record
        function prepareMessage(message, record, fields) {
            // Replace hashtags by actual content
            for(var i=0; i<dropdowns.length; i++) {
                // Index selected in the dropdown
                var dty_ID = $(dropdowns[i]).val();// field type index
                var value = "?";
                if(dty_ID && dty_ID > 0) {
                    var field_type = $Db.dty(dty_ID, 'dty_Type');
                    value = getValue(record, field_type, dty_ID);// Record value at the given index
                }

                // Regex
                var regex = new RegExp(dropdowns[i], "ig");// Replace #xxx case insensitive
                message = message.replace(regex, value);// Replace all occurences with @value
            }
            return message;
        }

        // Prepares the email
        function prepare() {
            // Raw message
            var rawMessage =  $("#message").val();

            var buttonText = $("#prepare > span").text();// stupid check by button text!!!!!

            var details = getSelectedFieldTypeIds();
            if(details!='' && !this.record){ // && !this.record.d
                //load details if required
                 var request = request = {q: 'ids:'+ids.join(','), w: 'all', detail:details };

                 window.hWin.HAPI4.RecordSearch.doSearchWithCallback( request, function( new_recordset )
                 {
                    if(new_recordset!=null){
                        this.records = new_recordset.getRecords();
                        this.record = new_recordset.getFirstRecord();
                        prepare();
                    }
                 });
                 return;
            }

            // Check action
            if(buttonText.indexOf("Prepare") >= 0) { // Check button text to determine action
                // PREPARE EMAILS
                var message = prepareMessage(rawMessage, record, definitions);

                // Show prepared message in new text area
                $("#message").slideUp(500, "linear", function(e) {
                    $("#btn_redo").slideDown(500);
                    $("#message-prepared").val(message).slideDown(500);
                    $("#prepare > span").text("Send emails");
                });
            }else{
                // SEND EMAILS
                var data = {};
                data.subject = $("#subject").val();
                data.emails = [];

                // Construct a message based on record data
                for(var r in records) {
                    var email = {};

                    // Email
                    var emailIndex = $("#email").val();// Dropdown index

                    if(emailIndex>0){
                        var emailType = $Db.dty(emailIndex, 'dty_Type');
                        email.recipients = getValue(records[r], emailType, emailIndex);// Determine e-mail address(es) [comma seperated]
                        if(!top.HEURIST4.util.isArrayNotEmpty(email.recipients)) email.recipients = [];

                        // Message
                        email.message = prepareMessage(rawMessage, records[r], definitions);// Determine message
                        data.emails.push(email);
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: "Define email field. It is mandatory",
                            error_title: 'Missing email',
                            status: window.hWin.ResponseStatus.ACTION_BLOCKED
                        });
                        return;
                    }
                }

                // Include e-mail to current user/database owner
                var owner = {recipients: [window.hWin.HAPI4.sysinfo.dbowner_email], message: rawMessage};
                data.emails.push(owner);

                // Send data to PHP file, everything is checked server-sided
                $.post("send_email.php", {
                    db: window.hWin.HAPI4.database,
                    data: JSON.stringify(data)}, function(response) {
                    window.hWin.HEURIST4.msg.showMsgDlg(response);
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    window.hWin.HEURIST4.msg.showMsgDlg(jqXHR.status + " --> " + jqXHR.responseText);
                });

            }
        }

    </script>
</body>
</html>
