<?php

/**
* Import File Data from CSV
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Brandon McKay   <blmckay13@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

define('LOGIN_REQUIRED',1);
define('PDIR','../../');

require_once dirname(__FILE__).'/../../hclient/framecontent/initPage.php';
?>
<script type="text/javascript" src="importBase.js"></script>
<script type="text/javascript" src="importFileData.js"></script>

<script type="text/javascript">
    function onPageInit(success){
        if(success){
            var importFileData = new HImportFileData();
        }
    }
</script>
<style>
    body{
        font-size: 12px;
    }
    .tbmain td, .tbmain th
    {
        border-left: 1px solid lightgray;
        padding:3px;
        text-align:left;
    }
    .tbmain th
    {
        font-weight:bold;
        padding-top:6px;
    }
    .tbmain
    {
        font-size: 1em;
    }

    div.top-div,
    div.bottom-div{
        display: flex;
        align-content: center;
        /*justify-content: center;*/
        flex-wrap: wrap;
    }
    div.bottom-div{
        flex-wrap: nowrap;
    }

    div#divStep1{
        margin-right:10px;
        flex: 1 1 425px;
    }
    div#divStep2{
        border-right:1px lightgray solid;
        flex: 0 1 175px;
    }
    div#divStep3{
        margin-left: 10px;
        flex: 0 1 200px;
    }
</style>

</head>

<!-- HTML -->
<body>
    <div class="top-div">

        <div id="divStep1">

            <div style="height:8em;padding:0">

                <p><b>Step 1</b></p>
                <p>
                    Paste content in area below (each line MUST contain an ID), with usual delimiters (eg. commas) and enclosing (e.g. double quotes).<br>
                    The visibility field can only have one value, the newest value will be used (except when retaining existing values).<br><br>

                    It is recommend to download the CSV file reference (Found at Admin > Manage files) and then use the values under the ID column (first column) here.
                </p>
                <input type="file" id="uploadFile" style="display:none">
                <div style="padding-top:4px">
                    <h2 style="display: inline-block;margin:0">or</h2>
                    <div id="btnUploadFile">Upload File</div>

                    <div style="float:right">encoding:
                        <select id="csv_encoding" class="text ui-widget-content ui-corner-all" style="width:120px;font-size:0.9em">
                        </select>
                    </div>
                </div>

            </div>

            <textarea id="sourceContent" rows="25" cols="0"
                style="width:100%;resize:none;padding:0.5em;border:2px solid lightblue;margin-top: 10px;"></textarea>

        </div>

        <div id="divStep2">

            <div style="height:10em;">
                <p><b>Step 2</b></p>
                <div>
                    <input id="csv_header"
                        style="margin:0 0.5em 0 0"
                        class="text ui-widget-content ui-corner-all" type="checkbox" value="1">
                    <label for="csv_header">Labels in line 1</label>
                </div>
                <div id="btnParseData" style="margin-top: 10px;">Analyse</div>
            </div>

            <fieldset style="padding-top:1em;"><legend style="display:none"></legend>

                <div>
                    <label for="csv_delimiter">Field separator:</label>
                    <select id="csv_delimiter" class="text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                        <option value="," selected>comma</option>
                        <option value="tab">tab</option>
                        <option value=";">semicolon</option>
                        <option value="space">space</option>
                    </select>
                </div>

                <div>
                    <label for="csv_enclosure">Fields enclosed in:</label>
                    <select id="csv_enclosure" class="text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                        <option selected value='2'>"</option><option value="1">'</option>
                    </select>
                </div>

            </fieldset>

        </div>

        <div id="divStep3">

            <div style="height:1em;">
                <p><b>Step 3</b></p>
            </div>

            <fieldset style="padding-top:1em;"><legend style="display:none"></legend>

                <p style="margin: 0px 0px 10px;">Select field assignment<br>(ID and Descriptor fields)</p>

                <div>
                    <span style="color:red">ID Field</span><br>
                    <select id="file_id" class="column_roles text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                    </select>
                </div>
                <div>
                    <span style="color:red">ID Type</span><br>
                    <select id="file_id_type" class="text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                        <option value="ulf_ID">Heurist File ID</option>
                        <option value="ulf_ObfuscatedFileID">Obfuscated ID</option>
                        <option value="ulf_FullPath">File path + name</option>
                    </select>
                </div>
                <div>
                    <span>Description</span><br>
                    <select id="file_desc" class="column_roles text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                    </select>
                </div>
                <div>
                    <span>Caption</span><br>
                    <select id="file_cap" class="column_roles text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                    </select>
                </div>
                <div>
                    <span>Copyright</span><br>
                    <select id="file_rights" class="column_roles text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                    </select>
                </div>
                <div>
                    <span>Copy owner</span><br>
                    <select id="file_owner" class="column_roles text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                    </select>
                </div>
                <div>
                    <span>Visibility</span><br>
                    <select id="file_vis" class="column_roles text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                    </select>
                    <br>
                    <span class="heurist-helper1">Allowed values: <strong>public</strong> or <strong>private</strong></span>
                </div>

            </fieldset>

            <div style="margin: 5px">
                <label>
                    <input type="radio" name="dtl_handling" value="1" class="text ui-widget-content ui-corner-all" checked="checked">
                    Keep existing values
                </label>
                <br>
                <label>
                    <input type="radio" name="dtl_handling" value="2" class="text ui-widget-content ui-corner-all">
                    Append new values
                </label>
                <br>
                <label>
                    <input type="radio" name="dtl_handling" value="3" class="text ui-widget-content ui-corner-all">
                    Replace existing values
                </label>
            </div>

            <div style="padding-left:5px;height:5em">
                <div id="preparedInfo2" style="font-weight:bold;font-size:1.1em;padding:4px"></div>
                <div id="btnImportData">Import</div>
            </div>

        </div>

    </div>

    <div style="margin-top:10px;">
        <div style="height:2em;border-bottom:none;border-top:1px solid lightgray;padding-top:10px;">
            <b>Preview of the data as it will be imported:</b>
            <div id="preparedInfo" style="float:right;padding-right:10px"><!-- div to show results of data preparation --></div>
        </div>
        <div style="font-size:0.9em;" id="divParsePreview"><!-- div to show results  --></div>
    </div>
</body>
</html>