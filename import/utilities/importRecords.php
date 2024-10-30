<?php

/**
* importRecords.php
* user interface for
* Inter-database import. Import from another Heurist database. Data can be in HML,XML or JSON format
*
* via importController it calls methods in importHeurist.php
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
use hserv\utilities\USystem;

define('MANAGER_REQUIRED',1);
define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';

$post_max_size = USystem::getConfigBytes('post_max_size');
$file_max_size = USystem::getConfigBytes('upload_max_filesize');
$max_size = min($file_max_size,$post_max_size);
$s_max_size = round($max_size/1024/1024).' MBytes';
?>
<!DOCTYPE html>
<html lang="en">
    <head>

        <!-- Force latest IE rendering engine or ChromeFrame if installed -->
        <!--[if IE]>
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <![endif]-->
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>Import XML or JSON</title>

        <?php
            includeJQuery();
        ?>

        <script src="<?php echo PDIR;?>external/jquery-file-upload/js/jquery.iframe-transport.js"></script>
        <script src="<?php echo PDIR;?>external/jquery-file-upload/js/jquery.fileupload.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_dbs.js"></script>

        <!-- CSS -->
        <?php include_once dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php';?>
        <!-- Demo styles -->
        <link rel="stylesheet" href="../../external/jquery-file-upload/css/demo.css">
        <style>
            /* Adjust the jQuery UI widget font-size: */
            .ui-widget {
                font-size: 0.95em;
            }
        </style>
        <script type="text/javascript" src="importRecords.js"></script>

        <script type="text/javascript">

            var importRecords;
            // Callback function after initialization
            $(document).ready(function() {
                importRecords = new hImportRecords(<?php echo $max_size; ?>);
            });


        </script>
    </head>


    <body class="popup ui-heurist-populate" style="margin:15px 0 0 20px !important; color:black">

        <div class="banner">
            <h2>Import XML or JSON</h2>
        </div>

        <!-- STEP 0 - select source file -->
        <div id="divStep0" style="width: 90%">


            <!--
            <div style="padding-left:40px">
            <p>JSON file generated by Heurist using Export > JSON</p><p>or</p><p>XML file generated by Heurist using Export > HML</p>
            </div>
            <p>The database from which the file is exported must first be registered with the Heurist Reference Index (only the database owner or the system administrator can register a database). This is required so that all record types, fields and values are identified by global concept codes - this avoids the need to build lots of mapping between the databases, as the required structure can be imported automatically.</p>
            -->
            <input type="file" id="uploadFile" style="display:none">
            <button id="btn_UploadData" class="ui-button-action">Select file to import</button>
            <div style="margin-top: 10px;float: right;">Maximum size <?php echo $s_max_size?> - contact Heurist team if you need to upload a larger file</div>
            <div style="margin-top: 10px;float: right;">Source database MUST be registered ( Design > Register ) unless the database structure is identical</div>

        </div>

        <!-- STEP 1 - list of missing record types - offer to sync definitions -->
        <div id="div_sourcedb" style="display:none">
        </div>

        <div id="divStep1" style="display:none">
            <p class="st1_OfferSync import-rem heurist-helper3">The following entity (record) types in this file do not yet exist, or require updating
                in the database into which you are importing data:</p>

            <p class="st1_AllRecognized_afterSync import-rem heurist-helper3">All entity types are recognised</p>

            <p class="st1_AllRecognized_beforeSync import-rem heurist-helper3">Although all entity types are recognised, it is not guaranteed that they are identical.
                Press "Synchronise" button to make sure that all fields and terms are the same in source and destination databases. If you are sure that structure is valid you may skip synchronisation step and proceed to record import.</p>

            <p class="st1_NotRecognized_afterSync import-rem heurist-helper3">Download definitions from the source database is performed.
                However <span class="cnt_missed_rt3"></span> still cannot be matched to the target database as shown below.
            </p>


            <p class="st1_notreg import-rem heurist-helper3">The source database is not registered. If you proceed with import we will assume
                the same structure as the target database (eg. because the data is based on a downloaded XML template from the target or
                the target is a clone of the source database).<br>
                Note that local internal IDs are used where no global concept IDs are defined. This could result in mis-allocation of data
                if the source and target do not have the same structure </p>


            <div id="div_tsv" style="display:none"></div>

            <!-- list of missing definitions -->
            <div id="div_RectypeToBeImported" style="max-width:740px;max-height:400px;overflow-y: auto;border:1px solid lightgray;">
            </div>

            <!-- source broken: import blocked -->
            <p class="st1_E import-rem" style="color:red">
                There are errors in the XML file : faulty XML tags (note: case sensitive), <span class="cnt_missed_rt"></span>
                cannot be matched to the target database as shown above. We recommend corrections to the XML file to eliminate such errors
                or remove data which cannot be matched, or manual downloading of definitions to match the incoming data.
            </p>

            <!-- non-registered: all rt are recognized -->
            <p class="st1_notreg import-rem heurist-helper3">
                <span class="st1_D import-rem">All entity types are recognised. However it is not guaranteed that they are identical.</span>

                <!-- non-registered: some record types are NOT recognized -->
                <span class="st1_G import-rem">The target database does not contain <span class="cnt_missed_rt"></span>
                    required to accommodate the incoming data. You still can import the data. However, data for missing definitions will be ignored.</span>

                <!-- non-registered: compare offer -->
                <br>We recommend downloading the list of elements to be imported and verify matches.
                <br><a href="#" class="tsv_download">List of elements in source file</a> (will download a tab-separated file)
            </p>

            <p class="st1_C import-rem heurist-helper3">
                <span class="cnt_local_rt"></span> in the source file use local codes, which are not guaranteed to have the same meaning between the source file and the target database. We recommend downloading the list of elements to be imported and verifying that the source and target record types and fields match.
                <br><a href="#" class="tsv_download">List of elements in source file</a> (will download a tab-separated file)
            </p>

            <!--
            <p class="st1_A import-rem">
            The source database MUST be registered with the Heurist Reference Index BEFORE the data is exported. If it has not been registered, please close this dialogue, register the source database (only the owner or an administrator can do this), re-export the data and then run this function on the new file.
            </p>
            <p class="st1_A st1_B import-rem heurist-helper3">
            If the download/synch of listed entity types fails to clear this message, please click on Help > Bug report and let us know. We will get back to you within 24 hours and help you import your data.
            </p>
            -->


            <p class="st1_ImportRtError import-rem heurist-helper3" style="color:red;">
                We were unable to download definitions from the source database for
                <span class="cnt_missed_rt2"></span> (listed above) specified in the XML file.
                <br style="color:black;">This may indicate an unregistered source (if indicated above), deletion of these types from the source database, or errors in the XML file (typos in XML tags, record type or field code).
                <br>We recommend corrections to the XML file to eliminate such errors or remove data which cannot be matched, or manual downloading of definitions to match the incoming data.
            </p>
            <p class="st1_ImportRtError2 import-rem heurist-helper3">
                If you do proceed, data for which there is no appropriate target record type will be ignored. This will result in an incomplete database. Make sure this is what you want before proceeding.
            </p>
            <p class="st1_ImportRtError3 import-rem heurist-helper3" style="color:red;">
                There are missing fields. Import is not allowed.
            </p>

            <br>
            <button id="btn_ImportRt" class="import-rem">Download listed entity types</button>

            <label style="display:none">
                <input type="checkbox" id="btn_SameStructure"/>Source database has the same structure as target (this) one
            </label>
        </div>

        <!-- STEP 2  - start donwload -->
        <div id="divStep2" style="display:none">
            <p id="st2_B" class="import-rem">All entity types are recognised and synched.</p>

            <p>
                <p>Importing <span id="spanRecCount"></span> items as new records. To identify duplicate records select ID field.</p>

                <span>Unique ID field identifying records: <select id="sel_UniqueIdField"></select></span>

                <span id="sa_mode" style="display:none;">
                    <br><br>
                    <label><input type="checkbox" checked="checked" id="sa_insert" class="text">&nbsp;
                    Create new records</label>
                    <label><input type="checkbox" checked="checked" id="sa_update" class="text">&nbsp;
                    Update existing records</label>

                    <div id="divUpdateSetting" style="top: 220px; line-height: 0px; font-size: 0.8em; display:block;">

                        <input type="radio" checked="" name="sa_upd" id="sa_upd0" value="2" class="text">&nbsp;
                        <label for="sa_upd0">Add new values without deletion of existing values (duplicates are ignored)</label><br>

                        <input type="radio" name="sa_upd" id="sa_upd21" value="1" class="text">&nbsp;
                        <label for="sa_upd21">Load new values, replacing all existing values for these records/fields</label><br>

                        <a href="#imp" style="margin: 0.3em;line-height: 17px;text-decoration:none" onclick="{$('#divImport3').show();$('#divImport3_marker').removeClass('ui-icon-triangle-1-e').addClass('ui-icon-triangle-1-s');}">
                            <span class="ui-icon ui-icon-triangle-1-s" id="divImport3_marker"></span>&nbsp;&nbsp;Other options
                        </a>

                        <div style="padding-left: 60px; vertical-align: top;" id="divImport3">

                            <input type="radio" name="sa_upd" id="sa_upd1" value="3" class="text">&nbsp;
                            <label for="sa_upd1">Add new values only if field is empty (new values ignored for non-empty fields)</label><br>

                            <input type="radio" name="sa_upd" id="sa_upd20" value="4" class="text">&nbsp;
                            <label for="sa_upd20"> Replace existing values with new values, retain existing value if no new value supplied</label>

                        </div>

                    </div>

                </span>

            </p>
            <!--
            <p>Note: no attempt is made to identify duplicate records - all items imported will create a new record. If you require duplicate record identification and replacement or merging, please <?php echo CONTACT_HEURIST_TEAM;?> for assistance (support at heuristnetwork dot org or use the bug report function)</p>
            -->
            <br>
            <button id="btn_ImportRecords" class="ui-button-action">Import Records</button>
        </div>

        <!-- STEP 3 - result  -->
        <div id="divStep3" style="display:none">

            <p><span id="spanRecCount2"></span></p>

            <br><br>
            <button id="btn_Close">Close</button>

        </div>


        <div class="loading" style="display: none;">
            <div id="progressbar_div" style="width:80%;height:150px;padding:5px;text-align:center;margin:auto;margin-top:20%;">
                <div id="progressbar">
                    <div class="progress-label">Loading data...</div>
                </div>
                <div id="progress_stop" style="text-align:center;margin-top:4px">Abort</div>
            </div>
        </div>

    </body>

</html>