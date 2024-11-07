<?php

    /**
    * manageFilesUpload.php
    * Uploading of single/multiple/large data files to <dbname> directories, notably .../scratchspace
    * Intended primarily for uploading data files to be imported, but could also be used for uploading a bunch of images
    * Note that scratch directory should be marked web inacessible to ensure dangerous files cannot be uploaded and then executed
    * TODO: address security concern above
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
if(!($max_size>0)) {$max_size = 0;}

?>
<!DOCTYPE html>
<html lang="en">
    <head>

        <!-- Force latest IE rendering engine or ChromeFrame if installed -->
        <!--[if IE]>
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <![endif]-->
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        
        <title>File upload manager</title>

        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>

        <!-- CSS -->
        <?php include_once dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php';?>

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
        <!-- blueimp Gallery styles -->
        <link rel="stylesheet" href="https://blueimp.github.io/Gallery/css/blueimp-gallery.min.css">
        <!-- CSS to style the file input field as button and adjust the Bootstrap progress bars -->
        <link rel="stylesheet" href="../../external/jquery-file-upload/css/jquery.fileupload.css">
        <link rel="stylesheet" href="../../external/jquery-file-upload/css/jquery.fileupload-ui.css">

        <script>
            function setUploadEntireFolder(){

                var ele = $('.fileupload-buttons > input');
                if(ele.prop('webkitdirectory')){
                    ele.removeProp('webkitdirectory');
                    //ele.prop('onchage',null);
                }else{
                    ele.prop('webkitdirectory',true);
                }
            }

        </script>
    </head>


    <body class="popup ui-heurist-populate" style="margin:0 !important; color:black;"">

        <div class="banner">
            <h2>FILE MANAGEMENT</h2>
        </div>

        <p>Members of the database managers group can upload multiple files and/or large files to the database scratch space. <br>
           Most commonly, files will be uploaded prior to importing data from them or running the in situ multimedia import.<br>
           Files with the same path, name and checksum are not duplicated, changed files may be overwriten or added.<br>
           The function is restricted to database managers to reduce the risk of other users filling the database with unwanted material.<br>
        </p>

        <?php

            // ----Visit #1 - CHECK MEDIA FOLDERS --------------------------------------------------------------------------------

            if(!array_key_exists('mode', $_REQUEST)) {

                $system_folders = $system->getSystemFolders();

                // Find out which folders are allowable - the default scratch space plus any
                // specified for FieldHelper indexing in Advanced Properties
                $mediaFolders = $system->get_system('sys_MediaFolders');
                if($mediaFolders==null || $mediaFolders == ''){ //not defined
                    $mediaFolders = HEURIST_FILESTORE_DIR.'uploaded_files/';
                    folderCreate( $mediaFolders, true );
                    $mediaFolders = 'uploaded_files';
                }
                $mediaExts = $system->get_system('sys_MediaExtensions');//from preferences
                if(!$mediaExts) {$mediaExts = '';}

                // Get the set of directories defined in Advanced Properties as FieldHelper indexing directories
                // These are the most likely location for bulk upload (of images) and restricting to these directories
                // avoids the risk of clobbering the system's required folders (since most people won't know what they're called)
                $dirs = explode(';', $mediaFolders);// get an array of folders
                $dirs_checked = array();
                $dirs2 = array();//real paths

                // MEDIA FOLDERS ALWAYS RELATIVE TO HEURIST_FILESTORE_DIR
                foreach ($dirs as $dir){
                    if( $dir && $dir!="*") {

                        $dir = str_replace('\\','/',$dir);

                        if(substr($dir, -1) != '/'){  //add last slash
                            $dir .= "/";
                        }

                        $dir_original = $dir;

                        if(!( substr($dir, 0, strlen(HEURIST_FILESTORE_DIR)) === HEURIST_FILESTORE_DIR )){
                            chdir(HEURIST_FILESTORE_DIR);
                            $resdir = realpath($dir);//realpath returns false if folder does not exist
                            if($resdir===false){
                                $dir = HEURIST_FILESTORE_DIR.'/'.$dir;
                                $dir = str_replace('//','/',$dir);
                            }else{
                                $dir = $resdir;
                            }

                            //realpath gives real path on remote file server
                            if(strpos($dir, '/srv/HEURIST_FILESTORE/')===0){
                                $dir = str_replace('/srv/HEURIST_FILESTORE/', HEURIST_FILESTORE_ROOT, $dir);
                            }elseif(strpos($dir, '/misc/heur-filestore/')===0){
                                $dir = str_replace('/misc/heur-filestore/', HEURIST_FILESTORE_ROOT, $dir);
                            }elseif(strpos($dir, '/data/HEURIST_FILESTORE/')===0){  //for huma-num
                                $dir = str_replace('/data/HEURIST_FILESTORE/', HEURIST_FILESTORE_ROOT, $dir);
                            }
                            $dir = str_replace('\\','/',$dir);
                            if(!( substr($dir, 0, strlen(HEURIST_FILESTORE_DIR)) === HEURIST_FILESTORE_DIR )){
                                // Folder must be in heurist filestore directory

                                print "Folder '$dir' is not in heurist filestore directory<br>";

                                continue;
                            }
                        }

                        if(file_exists($dir) && is_dir($dir) && !in_array($dir, $system_folders)){
                            array_push($dirs2, $dir);
                            array_push($dirs_checked, $dir_original);
                        }else{
                            if(in_array($dir, $system_folders)){
                                print "Folder '$dir' is system one and cannot be used for file upload<br>";
                            }else{
                                print "Folder '$dir' does not exist<br>";
                                if (!mkdir($dir, 0777, true)) {
                                    print 'Unable to create or wtite into folder '.$dir;
                                }else{
                                    print 'Created successfully<br>';
                                    array_push($dirs2, $dir);
                                    array_push($dirs_checked, $dir_original);
                                }

                            }

                        }
                    }
                }
                $dirs = $dirs_checked; //$dirs2;

                // add the scratch directory, which will be the default for upload of material for import
                //array_push($dirs, HEURIST_FILESTORE_DIR.DIR_SCRATCH);
                //array_push($dirs, HEURIST_FILES_DIR);

                // The defined list of file extensions for FieldHelper indexing.
                // For the moment keep this in as a restriction on file types which can be uploaded
                // Unlike indexing, we add the user-defined set to the default set
                $allowed_exts = mysql__select_list2($system->get_mysqli(), 'select fxm_Extension from defFileExtToMimetype');

                //$mediaExts = HEURIST_ALLOWED_EXT.','.$mediaExts; // default set to allow
               // $mediaExts = implode(',',array_unique(explode(',',$mediaExts)));
                // TODO: we should eliminate any duplicate extensions which might have been added by the user

                if ($mediaFolders=="" || count($dirs) == 1) {
                    print "<p>If you wish to upload files to a directory other than those in the dropdown, or to define additional file extensions,<br>".
                        "go to ";// Design > Properties link is supplied by next block
                }else{
                    print '<p><b>Allowable extensions for upload:</b>'.htmlspecialchars( implode(', ',$allowed_exts) ).'</p>';
                }
                ?>

                 <p><a href='#' onclick="{window.hWin.HEURIST4.ui.showEntityDialog('sysIdentification',
                    {onClose:function(){
                        location.reload();

                    }}); return false;}"
                    title='Open form to edit properties which determine the handling of files and directories in the database upload folders'>
                    Design > Properties</a>
                </p>
                <?php
            } // Visit #1

            // keep all thumbnails in one special folder outside upload foldee otherwise
            // indexing will be interfered with these thumbs


        ?>

        <!--  Acknowledgements: this code closely follows the published JQuery file upload examples -->

        <!-- The file upload form used as target for the file upload widget -->
        <form id="fileupload" action="//jquery-file-upload.appspot.com/" method="POST" enctype="multipart/form-data">

            <input type="hidden" name="upload_thumb_dir" value="<?php echo HEURIST_THUMB_DIR; ?>"/>
            <input type="hidden" name="upload_thumb_url" value="<?php echo defined('HEURIST_THUMB_URL')?HEURIST_THUMB_URL:'';?>"/>
            <input type="hidden" name="unique_filename" value="0"/>

            <input type="hidden" name="db" value="<?php echo HEURIST_DBNAME; ?>"/>
            <div><label for="upload_folder" style="color:black;">Select target folder:</label>
                <select name="upload_subfolder" id="upload_folder">
                    <?php
                        $is_dir_found = false;
                        foreach($dirs as $upload_dir) {
                            if(file_exists($upload_dir)){
                                print '<option value="'.$upload_dir.'">'.$upload_dir.'</option>';
                                $is_dir_found = true;
                            }
                        }
                    ?>
                </select>
                <br><br>
            </div>
            <!-- The fileupload-buttonbar contains buttons to add/delete files and start/cancel the upload -->
            <div class="fileupload-buttonbar" style="display:<?php print $is_dir_found?'block':'none';?>">
                <label><input type="checkbox" checked=checked onchange="setUploadEntireFolder()">
                    Upload directory and keep its structure on server side
                </label>
                <label style="font-size:smaller;font-style:italic"> To maintain the directory structure, you should use Chrome or FireFox</label>

                <br>

                <label style="margin-left:25px"> <!-- <input type="checkbox" checked=checked name="registerFiles"> -->
                    If files do not appear in Admin > Manage files, click on the Refresh Index button
                </label>

                <br><br>
                <label><input type="radio" checked=checked name="replace_edited_file" value="1">
                    Replace existing file if file has been modified
                </label><br>
                <label><input type="radio" name="replace_edited_file" value="2">
                    Create new file if file has been modified (1, 2, 3 … will be added)
                </label><br>
                <label><input type="radio" name="replace_edited_file" value="3">
                    Ignore changes to files already uploaded
                </label>

                <br>

                <h3>Files will not be uploaded until you click Upload (individual file) or Start uploads (batch)</h3>

                <div class="fileupload-buttons">
                    <!-- The fileinput-button span is used to style the file input field as button -->
                    <span class="fileinput-button">
                        <span>Add files...</span>
                    </span>
                    <input type="file" name="files[]" multiple webkitdirectory="true" style="display:none;">
                    <button id="btnCancel" type="reset" class="cancel">Clear upload list</button>

                    <div style="display:inline-block;min-width:40px"></div>

                    <button id="btnStart" type="submit" class="start" style="text-transform: none;font-weight: normal !important;" disabled>Start uploads</button>
                    <button id="btnFinished" disabled>Finished</button>
                    <!-- The global file processing state -->
                    <span class="fileupload-process"></span>
                </div>

                <!-- The global progress state -->
                <div class="fileupload-progress fade" style="display:none">
                    <!-- The global progress bar -->
                    <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100"></div>
                    <!-- The extended global progress state -->
                    <div class="progress-extended">&nbsp;</div>
                </div>

            </div>


            <!-- The table listing the files available for upload/download -->
            <table role="presentation" style="display:<?php print $is_dir_found?'block':'none';?>">
            <tbody class="files"></tbody>
            </table>


            <div id="msgWarnDir" style="color:red;display:<?php print $is_dir_found?'none':'block';?>">
                You must set folders before uploading files
            </div>
        </form>


        <!-- The template to display files available for upload -->
        <script id="template-upload" type="text/x-tmpl">
            {% for (var i=0, file; file=o.files[i]; i++) { %}
                <tr class="template-upload fade">
                <td>
                <span class="preview"></span>
                </td>
                <td>
                <p class="name">{%=file.name%}</p>
                <!-- p class="folder">{%=file.webkitRelativePath%}</p -->
                <strong class="error"></strong>
                </td>
                <td>
                <p class="size">Processing...</p>
                </td>
                <td>
                <!--  disable upload one file - otherwise mass upload may fail -->
                {% if (!i && !o.options.autoUpload) { %}
                <button class="start" style="visibility:hidden" disabled>Upload</button>
                {% } %}
            {% if (!i) { %}
                <button class="cancel">Cancel</button>
                {% } %}
            </td>
            </tr>
            {% } %}
        </script>


        <!-- The template to display files available for download -->
        <script id="template-download" type="text/x-tmpl">
            {% for (var i=0, file; file=o.files[i]; i++) { %}
                <tr class="template-download fade">
                <td>
                <span class="preview">
                {% if (file.thumbnailUrl) { %}
                <a href="{%=file.url%}" title="{%=file.name%}" download="{%=file.name%}" data-gallery><img src="{%=file.thumbnailUrl%}"></a>
                {% } %}
                </span>
            </td>
            <td style="{% if (file.error) { %}padding: 10px 0 10px 0; {% } %}">
            <p class="name" style="{% if (file.error) { %}margin-bottom: 5px; {% } %}">
            <a href="{%=file.url%}" title="{%=file.name%}" download="{%=file.name%}" {%=file.thumbnailUrl?'data-gallery':''%}>{%=file.name%}</a>
            </p>
              {% if (file.error) { %}
                <div>{%=file.error%} <span>Upload cancelled</span></div>
                <span class="error_for_msg" style="display:none">{%=file.name%} {%=file.error%}</span>
              {% } %}
                </td>
                <td>
                <span class="size" file-size="{%=file.deleteUrl?0:file.size%}">{%=o.formatFileSize(file.size)%}</span>
                </td>
                <td>
                <!-- disable delete for already uploaded files - since they are auto-indexed
                <button class="delete" data-type="{%=file.deleteType%}"
                data-url="{%=file.deleteUrl%}"{% if (file.deleteWithCredentials) { %}
                data-xhr-fields='{"withCredentials":true}'{% } %}>{%=file.error?'Clear':'Remove'%}</button>
                -->
                <!-- another confuising control - presumably meant to be a selection checkbox for multiple removals
                <input type="checkbox" name="delete" value="1" class="toggle">
                -->
              {% if (file.error && file.error.indexOf("already exists")>0) { %}
                <div style="color:green;display:inline-block;font-weight:bold;">No change</div>
              {% } %}
              {% if (!file.error) { %}
                <div style="color:blue;display:inline-block;font-weight:bold;">Upload OK</div>
              {% } %}
                </td>
                </tr>
              {% } %}
              {% $('#btnCancel').button('option','label','Clear list');%}
        </script>


        <!-- The Templates plugin is included to render the upload/download listings -->
        <script src="https://blueimp.github.io/JavaScript-Templates/js/tmpl.min.js"></script>
        <!-- The Load Image plugin is included for the preview images and image resizing functionality -->
        <script src="https://blueimp.github.io/JavaScript-Load-Image/js/load-image.all.min.js"></script>
        <!-- The Canvas to Blob plugin is included for image resizing functionality -->
        <script src="https://blueimp.github.io/JavaScript-Canvas-to-Blob/js/canvas-to-blob.min.js"></script>
        <!-- blueimp Gallery script -->
        <script src="https://blueimp.github.io/Gallery/js/jquery.blueimp-gallery.min.js"></script>
        <!-- The Iframe Transport is required for browsers without support for XHR file uploads -->
        <script src="../../external/jquery-file-upload/js/jquery.iframe-transport.js"></script>
        <!-- The basic File Upload plugin -->
        <script src="../../external/jquery-file-upload/js/jquery.fileupload.js"></script>
        <!-- The File Upload processing plugin -->
        <script src="../../external/jquery-file-upload/js/jquery.fileupload-process.js"></script>
        <!-- The File Upload image preview & resize plugin -->
        <script src="../../external/jquery-file-upload/js/jquery.fileupload-image.js"></script>
        <!-- The File Upload audio preview plugin -->
        <script src="../../external/jquery-file-upload/js/jquery.fileupload-audio.js"></script>
        <!-- The File Upload video preview plugin -->
        <script src="../../external/jquery-file-upload/js/jquery.fileupload-video.js"></script>
        <!-- The File Upload validation plugin -->
        <script src="../../external/jquery-file-upload/js/jquery.fileupload-validate.js"></script>
        <!-- The File Upload user interface plugin -->
        <script src="../../external/jquery-file-upload/js/jquery.fileupload-ui.js"></script>
        <!-- The File Upload jQuery UI plugin -->
        <script src="../../external/jquery-file-upload/js/jquery.fileupload-jquery-ui.js"></script>

        <script type="text/javascript">

            function closeCheck(event){

                if($(event.target).is('span, a')) { return false; }

                var files = $('tbody.files').find('a[data-gallery]');

                if(files.length > 0){

                    var msg = "You have uploaded " + (files.length/2) + " new media files.<br><br>"
                            + "They will not be visible as records in the database until you create media<br>records using Create media records.";

                    var btns = {};
                    btns[window.hWin.HR('Create Media Records')] = function(){

                        // Close popup
                        var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                        $dlg.dialog('close');

                        // Unbind mouseleave
                        $(document).off('mouseleave');

                        //Cancel possible uploads and reset form
                        $('#btnCancel').trigger('click');
                        // Close Upload media window
                        if($(event.target).is('button')) {
                            setTimeout(function(){ window.close();}, 100);
                        }

                        // Open Create media records window
                        setTimeout(function(){ $(parent.document).find('li[data-action="menu-files-index"]').trigger('click');}, 500);
                    };
                    btns[window.hWin.HR('Exit without Indexing')] = function(){

                        // Close popup
                        var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                        $dlg.dialog('close');

                        // Unbind mouseleave
                        $(document).off('mouseleave');

                        // Close Upload media window
                        if($(event.target).is('button')) {
                            $('#btnCancel').trigger('click');//reset form
                            setTimeout(function(){ window.close();}, 100);
                        }
                    }

                    window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, {title:'Indexing Uploaded Media Files',
                        yes:window.hWin.HR('Create Media Records'), no:window.hWin.HR('Exit without Indexing')});
                } else
                if ($(event.target).is('button')){
                        $('#btnCancel').trigger('click');
                        setTimeout(function(){ window.close();}, 100);
                }
            }

            var max_file_size = <?php echo $max_size;?>;
            var max_file_size_msg = 'File size exceeds allowed upload_max_filesize or post_max_size '
                                        +window.hWin.HEURIST4.util.formatFileSize(max_file_size);

            var files_to_register = [];
            var to_reg_count = 1;

            function registerFile(files, do_register){

                let prepare_file = true; //$('input[name="registerFiles"]').is(':checked');

                if(!prepare_file){
                    to_reg_count = 1;
                    return;
                }

                const base_path = $('#upload_folder').val();
                //let base_folder = base_path.split('/');
                //base_folder = base_folder[base_folder.length-1];
                let base_folder = base_path;

                if(files && files.length > 0){

                    for(let i = 0; i < files.length; i++){

                        const file_dtls = files[i];
                        let path = file_dtls['url'];
                        if(window.hWin.HEURIST4.util.isempty(path)){
                            -- to_reg_count;
                            continue;
                        }

                        let index = path.indexOf(base_folder);

                        if(index > -1){
                            files_to_register.push({'file_path': path.substr(index)});
                        }
                    }

                    to_reg_count = (to_reg_count < 1) ? 1 : to_reg_count;
                }

                if(do_register && files_to_register.length == to_reg_count && files_to_register.length > 0){

                    const request = {
                        'a'          : 'batch',
                        'entity'     : 'recUploadedFiles',
                        'request_id' : window.hWin.HEURIST4.util.random(),
                        'files'      : JSON.stringify(files_to_register),
                        'bulk_reg_filestore' : 1
                    };

                    window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));
                    files_to_register = [];
                    to_reg_count = 1;

                    window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){

                        window.hWin.HEURIST4.msg.sendCoverallToBack();

                        if(response.status == window.hWin.ResponseStatus.OK){
                            window.hWin.HEURIST4.msg.showMsgDlg(response.data, null, {title: 'Registering files'}, {default_palette_class: 'ui-heurist-admin'});
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
                }
            }

            $(function () {
                'use strict';

                window.hWin.HEURIST4.filesWereUploaded = false;

                //ART 2021-09-17 $(document).on("mouseleave", closeCheck);

                function __calculateTotalSizeToBeUploaded(){
                        //verify that all files are processed and show total size to be uploaded
                        var ele = $('tbody.files');
                        var size = 0;
                        var cnt = ele.find('.template-upload').length;
                        ele.find('.size').each(function (index,item){
                            if($(item).text() == 'Processing...'){
                                size = -1;
                                return false;
                            }else{
                                var filesize = Number($(item).attr('file-size'));
                                if(!isNaN(filesize)){
                                    if(max_file_size>0 && filesize > max_file_size){
                                        var template = $(item).parents('tr');
                                        template.find('button.start').prop('disabled',true).css('color','lightgray');
                                        $(item).text(window.hWin.HEURIST4.util.formatFileSize(filesize)+' !')
                                            .attr('title', max_file_size_msg)
                                            .css('color', 'red');

                                        var data = template.data('data') || {};
                                        if(data.files){ //prevent upload
                                            data.files[0].error = max_file_size_msg;
                                        }

                                    }else{
                                        size = size + filesize;
                                    }
                                }
                            }
                        });

                        if(size>0){

                            window.hWin.HEURIST4.util.setDisabled($('#btnStart'), false);
                            window.hWin.HEURIST4.util.setDisabled($('#btnCancel'), false);

                            $('#btnStart').button('option','label','Start uploads '+window.hWin.HEURIST4.util.formatFileSize(size))
                                          .addClass('ui-button-action')
                        }else{

                            window.hWin.HEURIST4.util.setDisabled($('#btnStart'), true);

                            $('#btnStart').button('option','label','Start uploads')
                                          .removeClass('ui-button-action');
                        }
                }

                // Initialize the jQuery File Upload widget:
                $('#fileupload').fileupload({
                    // Uncomment the following to send cross-domain cookies:
                    //xhrFields: {withCredentials: true},
                    //formData: {name: 'acceptFileTypes', value:"echo implode('|',$allowed_exts)" },
                    //upload_thumb_dir: '<?=HEURIST_THUMB_DIR?>',
                    url: '<?=HEURIST_BASE_URL?>hserv/utilities/UploadHandlerInit.php', //was external/jquery-file-upload/server/php/
                    added: function(e, data){
                        __calculateTotalSizeToBeUploaded();
                    },
                    finished: function(e, data){
                        __calculateTotalSizeToBeUploaded();

                        var ele = $('tbody.files');
                        var cnt = ele.find('.template-download').length;

                        window.hWin.HEURIST4.filesWereUploaded = window.hWin.HEURIST4.filesWereUploaded || (cnt>0);

                        // remove coverall
                        $('table[role="presentation"] .coverall-div').remove();

                        let disable_finished = e.originalEvent.type == 'done' && data.result?.files.length == 0;
                        window.hWin.HEURIST4.util.setDisabled($('#btnFinished'), disable_finished);

                        var cntAlreadyExists = 0;
                        var cntWarnMemtypes = 0;
                        var cntOtherErrors = 0;

                        var swarns = '';
                        var swarns_memtypes = '';
                        var swarns_exists = '';

                        var eles = $.find('span.error_for_msg');
                        $(eles).each(function(idx,item){
                            var s = $(item).text();

                            if (s.indexOf('already exists')>0){
                                var k = s.indexOf('File with the same name');
                                swarns_exists = swarns_exists + '<br>' + '<br>'+s.substr(0,k);
                                cntAlreadyExists++;
                            }else
                            if (s.indexOf('allowed mimetypes') > 0){
                                var k = s.indexOf('Filetype not listed among');
                                swarns_memtypes = swarns_memtypes + '<br>'+s.substr(0,k);
                                cntWarnMemtypes++;
                            }else
                            if(s.indexOf('uploaded file exceeds') < 0){ // ignore msg about exceeding upload max
                                swarns = swarns + '<br>'+s;
                                cntOtherErrors++;

                                let $prev_ele = $(item).prev();
                                if($prev_ele.length > 0 && $prev_ele.find('span').length == 1){ // redify 'Upload cancelled'
                                    $prev_ele.find('span').addClass('error');
                                }
                            }
                        });

                        if(cntAlreadyExists>0){
                            swarns_exists = '<h4 style="margin-bottom:0px">Already uploaded files: '+cntAlreadyExists+'</h4>'
                                    +'<div style="line-height:0.8;">'+swarns_exists+'</div>';
                        }
                        if(cntWarnMemtypes>0){
                            swarns_memtypes = '<h4 style="margin-bottom:0px">The following files were not uploaded as the mime type was not recognised (please contact us for an update).<br>Count = '+cntWarnMemtypes+'</h4>'
                                    +'<div style="line-height:0.8;">'+swarns_memtypes+'</div>';
                        }
                        if(cntOtherErrors>0){
                            swarns = '<h4 style="margin-bottom:0px">Attention. '
                                      +(cntOtherErrors==1?'File was not':(cntOtherErrors+' files were not'))
                                      +' uploaded.</h4><div style="line-height:0.9;">'+swarns+'</div>';
                        }

                        swarns = swarns_exists + swarns_memtypes + swarns;
                        swarns = window.hWin.HEURIST4.util.isempty(swarns) ? '' :
                                    '<div style="max-height: 500px; overflow-y: auto; padding-right: 5px;">' + swarns + '</div>';

                        if(cntAlreadyExists>0 || cntWarnMemtypes>0){

                            let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(swarns, {'OK': function(){
                                $dlg.dialog('close');
                                registerFile(null, true);
                            }}, {'yes': 'OK', 'title': 'File upload warnings'});

                        }else if (swarns!='') {
                            window.hWin.HEURIST4.msg.showMsgErr({
                                message: swarns,
                                error_title: 'File upload warnings'
                            });
                        }

                        if(e.originalEvent.type != 'done' && data.result?.files){
                            registerFile(data.result?.files, swarns == '');
                        }

                    }
                });

                $('#btnCancel').on('click', function(e){ //clear list

                    if($('#btnCancel').button('option','label')=='Clear list'){
                        $('tbody.files').find('.template-download').remove();
                    }
                    window.hWin.HEURIST4.util.setDisabled($('#btnStart'), true);
                    window.hWin.HEURIST4.util.setDisabled($('#btnCancel'), true);

                    setTimeout(function(){
                    window.hWin.HEURIST4.util.setDisabled($('#btnCancel'),
                        $('tbody.files').find('.template-download').length==0
                        && $('tbody.files').find('.template-upload').length==0  );
                    },500);

                    $('#btnStart').button('option','label','Start uploads')
                                  .removeClass('ui-button-action');

                    if($('tbody.files').find('.template-download').length>0){
                        $('#btnCancel').button('option','label','Clear list');
                    }else{
                        $('#btnCancel').button('option','label','Clear upload list');
                    }

                });
                $('#btnStart').on('click', function(e){

                    window.hWin.HEURIST4.util.setDisabled($('#btnStart'), true);
                    window.hWin.HEURIST4.util.setDisabled($('#btnFinished'), true);

                    $('#btnStart').button('option','label','uploading...')
                                  .removeClass('ui-button-action');
                    $('#btnCancel').button('option','label','Cancel uploads');

                    to_reg_count = $('.template-upload').length; //$('input[name="registerFiles"]').is(':checked') ? $('.template-upload').length : 1;

                    // add coverall
                    window.hWin.HEURIST4.msg.bringCoverallToFront($('table[role="presentation"]'), {'font-size': '16px', color: 'white', position: 'relative'}, 'Uploading all files...');
                });

                //cancel and close window
                $('#btnFinished')
                        .button({icon: 'ui-icon-check'})
                        .on('click', function(e){
                            e.preventDefault();
                            closeCheck(e);
                            return false;
                        });

                // Enable iframe cross-domain access via redirect option:
                $('#fileupload').fileupload(
                    'option',
                    'redirect',
                    window.location.href.replace(
                        /\/[^\/]*$/,
                        '<?=HEURIST_BASE_URL?>external/jquery-file-upload/cors/result.html?%s'
                    )
                );

                // Load existing files:
                $('#fileupload').addClass('fileupload-processing');
                $.ajax({
                    // Uncomment the following to send cross-domain cookies:
                    //xhrFields: {withCredentials: true},
                    url: $('#fileupload').fileupload('option', 'url'),
                    data: { db: "<?php echo HEURIST_DBNAME; ?>",
                            acceptFileTypes:"<?php echo htmlspecialchars(implode('|',$allowed_exts))?>",
                            unique_filename: 0,
                            max_file_size: <?php echo $max_size;?>,
                            upload_subfolder: $('#upload_folder').val() },  //subfolder of database upload folder
                    dataType: 'json',
                    /*
                    disableImageResize: /Android(?!.*Chrome)|Opera/
                    .test(window.navigator.userAgent),
                    maxFileSize: 5000000,
                    acceptFileTypes: /(\.|\/)(gif|jpe?g|png)$/i
                    */

                    context: $('#fileupload')[0]
                }).always(function () {
                    $(this).removeClass('fileupload-processing');
                }).done(function (result) {
                    $(this).fileupload('option', 'done')
                    .call(this, $.Event('done'), {result: result});
                });

                $('.fileinput-button > span').on({
                    "click": function(e){

                        var ele = $('.fileupload-buttons > input');

                        if(ele.prop('webkitdirectory')){

                            var $dlg;

                            var msg = "You are about to upload a FOLDER (and sub-folders)<br><br>"

                                + "THE BROWSER WILL NOT LIST ANY FILES as you navigate the tree of folders<br>"
                                + "(this is due to limitations on browser interactions)<br><br>"

                                + "Use of this mode allows the folder names to be recorded on upload, which is an <br>"
                                + "advantage if you use a hierarchical structure of named folders for your media files.<br><br>"

                                + "To upload files without folder name information, cancel this upload and clear the <br>"
                                + "'Upload directory ...' checkbox before clicking Add files";

                            var buttons = {};
                            buttons[window.hWin.HR('OK')] = function(){
                                    ele.trigger('click');
                                    $dlg.dialog('close');
                                };
                            buttons[window.hWin.HR('Cancel')] = function(){
                                    $dlg.dialog('close');
                                };

                            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, buttons,
                            'Uploading FOLDER and sub-folders', { default_palette_class: 'ui-heurist-populate' });
                        }else{
                            ele.trigger('click');
                        }
                    }
                });

                /*
                $('#upload_folder').on('change', function(){
                    if($('#upload_folder').val()==''){
                        $('.fileupload-buttonbar').hide();
                        $('#presentation').hide();
                    }else{
                        $('.fileupload-buttonbar').show();
                        $('#presentation').show();
                    }
                });
                */

            });
        </script>

    </body>

</html>