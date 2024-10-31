<?php

/**
* Import recUploadedFiles from CSV
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

define('LOGIN_REQUIRED',1);
define('PDIR','../../');

require_once dirname(__FILE__).'/../../hclient/framecontent/initPage.php';
?>
<script type="text/javascript" src="importBase.js"></script>
<script type="text/javascript" src="importMedia.js"></script>

<script type="text/javascript">

    // Callback function after initialization
    function onPageInit(success){
        if(success){
            var importMedia = new HImportMedia();
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
        /*border-collapse:collapse;*/
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
        flex: 1 1 265px;
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
                <p><b>This function uploads a set of files specified by URLs.</b></p>
                <p><b>Step 1: </b>Paste URLs + optional description, one file to a line, in the area below. Recommended format is CSV with a header ( URL,Description ) in line 1</p>
                <input type="file" id="uploadFile" style="display:none">
                <div style="padding-top:4px">
                    <h2 style="display: inline-block;margin:0">or</h2>
                    <div id="btnUploadFile">Upload File</div>
                    <div style="float:right">encoding:
                        <select id="csv_encoding" class="text ui-widget-content ui-corner-all" style="width:120px;font-size:0.9em">
                        </select>
                    </div>
                </div>
                <div class="heurist-helper1" style="padding-top: 9px;">

                </div>

            </div>
            <textarea id="sourceContent" rows="25" cols="0"
                style="width:100%;resize:none;padding:0.5em;border:2px solid lightblue;margin-top: 10px;"></textarea>


        </div>
        <div id="divStep2">
            <div style="height:10em;">
                <p><b>Step 2</b></p>
                <div>
                    <br>
                    <input id="csv_header"
                        style="margin:0 0.5em 0 0"
                        class="text ui-widget-content ui-corner-all" type="checkbox" value="1">
                    <label for="csv_header">Labels in line 1</label>
                </div>
                <div id="btnParseData" style="margin-top: 10px;">Analyse</div>
            </div>
            <fieldset style="padding-top:1em;"><legend style="display:none"></legend>
                <div>
                    <br><br>
                    <label for="csv_delimiter">Field separator:</label>
                    <select id="csv_delimiter" class="text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                        <option value="," selected>comma</option>
                        <option value="tab">tab</option>
                        <option value=";">semicolon</option>
                        <option value="space">space</option>
                    </select>
                </div>
                <div>
                    <br>
                    <label for="csv_enclosure">Fields enclosed in:</label>
                    <select id="csv_enclosure" class="text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                        <option selected value='2'>"</option><option value="1">'</option>
                    </select>
                </div>
                <div style="display:none;">
                    <label for="csv_linebreak">Line separator:</label>
                    <select id="csv_linebreak" class="text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                        <option selected value="auto">auto detect</option>
                    </select>
                </div>
                <div>
                    <label for="multival_separator">Multivalue separator:</label>
                    <input id="multival_separator" value="|" class="text ui-widget-content ui-corner-all"
                        style="width: 25px;margin-left: 8px;">
                </div>
            </fieldset>
        </div>
        <div id="divStep3">
            <div style="height:6em;">
                <p><b>Step 3</b></p>
                <p style="padding-top:0.4em; margin-bottom: 10px;">Select field assignment<br>(URL/path is required)</p>
            </div>
            <fieldset style="padding-top:1em;"><legend style="display:none"></legend>

                <div>
                    <span style="color:red">URL/Path</span><br>
                    <select id="field_url" class="column_roles text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                    </select>
                </div>
                <div>
                    <br>
                    <input id="field_download" checked class="column_roles text ui-widget-content ui-corner-all" type="checkbox" value="1" style="margin-left:0px"/>
                    <span>Get file from URL, upload and register as a local file in the database</span>
                </div>
                <div>
                    <br>
                    <span>Description</span><br>
                    <select id="field_desc" class="column_roles text ui-widget-content ui-corner-all" style="width:120px;margin-left:20px">
                    </select>
                </div>
                <div>
                    <br>
                    <input id="field_desc_concat" checked class="column_roles text ui-widget-content ui-corner-all" type="checkbox" value="1" style="margin-left:0px"/>
                    <span> Concatenate additional fields past first delimiter with Description</span>
                </div>


            </fieldset>
            <div style="padding-left:5px;height:5em">
                <div id="preparedInfo2" style="font-weight:bold;font-size:1.1em;padding:4px"></div>
                <div id="btnImportData">Import</div>
            </div>
        </div>
    </div>

    <div style="margin-top:10px;">
        <div style="height:4em;border-bottom:none;border-top:1px solid lightgray;padding-top:10px;">
            <br><b>Preview of the data as it will be imported</b> <br>(check that columns have been separated with | symbols and that column headings have been read correctly - should be shown in bold, data in normal font)<br>
            <div id="preparedInfo" style="float:right;padding-right:10px"> <!-- div to show results of data preparation --></div>
        </div>
        <div style="font-size:0.9em;" id="divParsePreview">
        </div>
    </div>

</body>
</html>