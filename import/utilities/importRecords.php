<?php

    /**
    * importRecords.php
    * Inter-database import. Import from another Heurist database. Data can be in HML,XML or JSON format
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2019 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     3.2
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

define('MANAGER_REQUIRED',1);
define('PDIR','../../');  //need for proper path to js and css    

require_once(dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php');
require_once(dirname(__FILE__).'/../../hsapi/utilities/utils_file.php');

$post_max_size = get_php_bytes('post_max_size');
$file_max_size = get_php_bytes('upload_max_filesize');
?>
<html>
    <head>

        <!-- Force latest IE rendering engine or ChromeFrame if installed -->
        <!--[if IE]>
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <![endif]-->
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>File upload manager</title>
        
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>

        <script src="../../external/jquery-file-upload/js/jquery.iframe-transport.js"></script>
        <script src="../../external/jquery-file-upload/js/jquery.fileupload.js"></script>
        
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>
        <link rel="stylesheet" type="text/css" href="<?php echo $cssLink;?>" /> <!-- theme css -->
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
        

        <!-- Demo styles -->
        <link rel="stylesheet" href="../../external/jquery-file-upload/css/demo.css">
        <!--[if lte IE 8]>
        <link rel="stylesheet" href="../../external/jquery-file-upload/css/demo-ie8.css">
        <![endif]-->
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
                    var max_size = Math.min(<?php echo $file_max_size;?>, <?php echo $post_max_size;?>);
                    importRecords = new hImportRecords(max_size);
            });
        
        
        </script>
    </head>


    <body class="popup" style="margin:0 !important; color:black;">

        <div class="banner">
            <h2>Heurist JSON or HML Import</h2>
        </div>
        
        <!-- STEP 0 - select source file -->
        <div id="divStep0">
        <p>This function imports</p>
           <div style="padding-left:40px">
           <p>JSON file generated by Heurist using Export > JSON</p><p>or</p><p>XML file generated by Heurist using Export > HML</p>
           </div> 

        <p>The database from which the file is exported must first be registered with the Heurist Master Index (only the database owner or the system administrator can register a database). This is required so that all record types, fields and values are identified by global concept codes - this avoids the need to build lots of mapping between the databases, as the required structure can be imported automatically.</p>
        
            <input type="file" id="uploadFile" style="display:none">
            <button id="btn_UploadData">Select file to import</button>
        
        </div>

        <!-- STEP 1 - list of missed record types - offer to sync definitions -->
        <div id="divStep1" style="display:none">
           <p class="st1_A import-rem">The following entity (record) types in this file do not yet exist in the database into which you are importing data:</p>
           <p id="st1_B" class="import-rem">All entity types are recognised. However it is not guaranteed that they are identical. 
           Press "Sync" button to make sure that all fields and terms are the same in source and destination databases. If you are sure that structure is valid you may skip sync step and proceed to record import.</p>

           <p class="st1_C import-rem">The target database does not contain all the structural elements required to accommodate the incoming data. 
            You still can import the data. However, data for missed definitions will be ignored.
            <a href="#" class="tsv_download">List of elements  in source file</a> (will download a tab-separated file).</p>
            
           <p class="st1_D import-rem">All entity types are recognised. However it is not guaranteed that they are identical.  
            It is recommened to download the list of elements to be imported and verify matches.
            <a href="#" class="tsv_download">List of elements in source file</a> (will download a tab-separated file)</p>
            
           <div id="div_tsv" style="display:none"></div>
           
           <!-- list of missed definitions -->
           <div id="div_RectypeToBeImported" style="max-height: 128px;overflow-y: auto;">
           </div>     

           <p class="st1_A import-rem">
The source database MUST be registered with the Heurist Master Index BEFORE the data is exported. If it has not been registered, please close this dialogue, register the source database (only the owner or an administrator can do this), re-export the data and then run this function on the new file.
           </p>
           <p class="st1_A import-rem">
If the download of listed entity types fails to clear this message, please click on Help > Bug report and let us know. We will get back to you within 24 hours and help you import your data.
           </p>
           
           <br><br>
           <button id="btn_ImportRt" class="import-rem">Download listed entity types</button>
        </div>
        
        <!-- STEP 2  - start donwload -->
        <div id="divStep2" style="display:none">
<br>
<p id="st2_B" class="import-rem">All entity types are recognised and synched.</p>
<p>Importing <span id="spanRecCount"></span> items as new records.</p>
<p>Note: no attempt is made to identify duplicate records - all items imported will create a new record. If you require duplicate record identification and replacement or merging, please <?php echo CONTACT_HEURIST_TEAM;?> for assistance (support at heuristnetwork dot org or use the bug report function)</p>
           <br><br>
           <button id="btn_ImportRecords">Import Records</button>
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