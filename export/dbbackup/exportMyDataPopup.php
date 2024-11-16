<?php

/**
* exportMyDataPopup.php: Set up the export of some or all data as an HML or zip file
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/



define('MANAGER_REQUIRED', 1);
define('PDIR','../../');//need for proper path to js and css

set_time_limit(0);//no limit

use hserv\structure\ConceptCode;
use hserv\utilities\DbUtils;
use hserv\utilities\UArchive;

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
require_once dirname(__FILE__).'/../../hserv/records/search/recordFile.php';


define('FOLDER_BACKUP', HEURIST_FILESTORE_DIR.DIR_BACKUP.HEURIST_DBNAME);
define('FOLDER_SQL_BACKUP', HEURIST_FILESTORE_DIR.DIR_BACKUP.HEURIST_DBNAME.'_sql');
define('FOLDER_HML_BACKUP', HEURIST_FILESTORE_DIR.DIR_BACKUP.HEURIST_DBNAME.'_hml');
define('FOLDER_TSV_BACKUP', HEURIST_FILESTORE_DIR.DIR_BACKUP.HEURIST_DBNAME.'_tsv');// TSV folder, for standalone download

$mode = @$_REQUEST['mode'];// mode=2 - entire archived folder,  mode=3 - sql dump only, mode=4 - cleanup backup folder, mode=5 - hml file only
$format = 'zip';//default
if(array_key_exists('is_zip', $_REQUEST) && $_REQUEST['is_zip']==1){
    $format = 'zip';
}
if(array_key_exists('is_tar', $_REQUEST) && $_REQUEST['is_tar']==1){
    $format = 'tar';
}

$mime = $format == 'tar' ? 'application/x-bzip2' : 'application/zip';
$is_repository = array_key_exists('repository', $_REQUEST);

// Download the dumped data as a zip file
if($mode>1){

    if($format == 'tar'){
        $format = 'tar.bz2';
    }

    if($mode=='2' && file_exists(FOLDER_BACKUP.'.'.$format) ){ //archived entire folder
        downloadFile($mime, FOLDER_BACKUP.'.'.$format);//see recordFile.php
    }elseif($mode=='3' && file_exists(FOLDER_SQL_BACKUP.'.'.$format)){  //archived sql dump
        downloadFile($mime, FOLDER_SQL_BACKUP.'.'.$format);
    }elseif($mode=='5' && file_exists(FOLDER_HML_BACKUP.'.'.$format)){  //archived hml file
        downloadFile($mime, FOLDER_HML_BACKUP.'.'.$format);
    }elseif($mode=='6' && folderExists(FOLDER_TSV_BACKUP, false)){  //archived tsv sub directory
        downloadFile($mime, FOLDER_TSV_BACKUP.'.'.$format);
    }elseif($mode=='4'){  //cleanup backup folder on exit
        folderDelete2(HEURIST_FILESTORE_DIR.DIR_BACKUP, false);
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        <title>Create data archive package</title>

<?php
        includeJQuery();
?>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>

        <!-- CSS -->
        <?php include_once dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php';?>

        <script type=text/javascript>
            var is_repository = <?php echo $is_repository?'true':'false';?>;
            var complete_list_repositories = {};

            $(document).ready(function() {
                $('input[type="submit"]').button();
                $('input[type="button"]').button();

                if(!window.hWin.HAPI4){
                    $('#btnClose_1').hide();
                    $('#btnClose_2').hide();

                    if(is_repository){
                        $('body').children().hide();
                        //show warning message: upload to repository is not available
                        window.location = '<?php echo PDIR.'hclient/framecontent/infoPage.php?error='.rawurlencode('It is possible to perform this operation from Heurist admin interface only');?>';
                    }
                }else if(is_repository){
                    initRepositorySelector();
                }



            });

            //
            // cleanup backup folder on exit
            //
            function closeArchiveWindow(){
                <?php print '$.ajax("'.HEURIST_BASE_URL.'/export/dbbackup/exportMyDataPopup.php?mode=4&db='.HEURIST_DBNAME.'");';?>
                window.close();
            }

            //
            // fill repository selector
            //
            function initRepositorySelector(){

                let $repos = $('#sel_repository');
                let $accounts = $('#sel_accounts');

                if($repos.length == 0 || $accounts.length == 0){
                    return;
                }

                $repos.empty();
                window.hWin.HEURIST4.ui.addoption($repos[0], '', 'select a repository...');

                window.hWin.HAPI4.SystemMgr.repositoryAction({'a': 'list', 'include_test': 1}, (response) => {

                    if(response.status != window.hWin.ResponseStatus.OK){
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                        return;
                    }

                    let listed_repos = [];
                    $.each(response.data, (idx, repo_details) => {

                        let repo_name = repo_details[1];
                        repo_name = repo_name.charAt(0).toUpperCase() + repo_name.slice(1);

                        let is_test = repo_details[0].indexOf('_') == -1;

                        repo_details[4] = true;

                        if(Object.hasOwn(complete_list_repositories, repo_name)){
                            complete_list_repositories[repo_name].push(repo_details);
                            return;
                        }

                        complete_list_repositories[repo_name] = [ repo_details ];

                        window.hWin.HEURIST4.ui.addoption($repos[0], repo_name, repo_name);
                    });

                    $accounts.parent().hide();

                    window.hWin.HEURIST4.ui.initHSelect($repos, false, {width: '150px', 'margin-left': '5px'}, {
                        onSelectMenu: () => {

                            let value = $repos.val();

                            $accounts.empty();
                            $('[class*="repo-"]').hide();

                            if(value == ''){
                                $accounts.parent().hide();
                                return;
                            }

                            $accounts.parent().show();

                            window.hWin.HEURIST4.ui.addoption($accounts[0], '', 'Select an account to use...');

                            $(`.repo-${value}`).show();

                            let accounts = complete_list_repositories[value];
                            $.each(accounts, (idx, details) => {

                                let lbl = `${details[0].indexOf('_') >= 0 ? '' : 'Test - '}${details[3]}`;

                                window.hWin.HEURIST4.ui.addoption($accounts[0], details[0], lbl);
                            });

                            if($accounts.hSelect('instance') !== undefined){
                                $accounts.hSelect('refresh');
                            }

                            if(value == 'Nakala'){
                                $('#nakala-url').hide();
                                getNakalaLicenses();
                            }
                        }
                    });

                    window.hWin.HEURIST4.ui.initHSelect($accounts, false, {width: '200px', 'margin-left':'5px'}, {
                        onSelectMenu: () => {

                            let repo = $repos.val();
                            let value = $accounts.val();

                            if(repo == 'Nakala'){
                                value != '' && value.indexOf('_') >= 0 ? $('#nakala-url').show() : $('#nakala-url').hide();
                            }

                            value != '' && value.indexOf('_') == -1 ? $('.setup-keys').show() : $('.setup-keys').hide();
                        }
                    });
                });
            }

            //
            // fill license selector
            //
            function getNakalaLicenses(){
                let $sel_license = $('#sel_license');

                if($sel_license.attr('data-init') == 'Nakala' && $sel_license.find('option').length > 1){ // already has values
                    return;
                }

                let request = {
                    serviceType: 'nakala',
                    service: 'nakala_get_metadata',
                    type: 'licenses'
                };

                window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));

                window.hWin.HAPI4.RecordMgr.lookup_external_service(request, (data) => {

                    window.hWin.HEURIST4.msg.sendCoverallToBack();
                    data = window.hWin.HEURIST4.util.isJSON(data);

                    if(data.status && data.status != window.hWin.ResponseStatus.OK){
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: 'An error occurred while attempting to retrieve the licenses for Nakala records, however the archiving process can still be completed.',
                            error_title: 'Unable to retrieve Nakala licenses',
                            status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                        });
                        $sel_license.parent().parent().hide();
                        return;
                    }

                    if(data.length > 0){
                        $.each(data, (idx, license) => {
                            window.hWin.HEURIST4.ui.addoption($sel_license[0], license, license);
                        });
                        window.hWin.HEURIST4.ui.initHSelect($sel_license, false, {'margin-left': '21px'});
                        $sel_license.attr('data-init', 'Nakala');
                        $sel_license.parent().parent().show();
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: 'An unknown error has occurred while attempting to retrieve the licenses for Nakala records, however the archiving process can still be completed.',
                            error_title: 'No Nakala licenses found',
                            status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                        });
                        $sel_license.parent().parent().hide();
                    }
                });
            }


            //
            // on start button click event handler
            //
            function exportArchive(){

                if(is_repository){
                    let repo = $('#sel_repository').val();
                    let acc = $('#sel_accounts').val();
                    if(repo == ''){
                        window.hWin.HEURIST4.msg.showMsgFlash('Please select a repository...', 2000);
                        return;
                    }else if(acc == ''){
                        window.hWin.HEURIST4.msg.showMsgFlash('Please select an account to use...', 2000);
                        return;
                    }else if(acc.indexOf('nakala') >= 0 && $('#sel_license').val() == ''){
                        window.hWin.HEURIST4.msg.showMsgFlash('Please select a license...', 2000);
                        return;
                    }
                }


                if(window.hWin.HAPI4){
                    /*
                    $('<div>Preparing archive file...</div>')
                        .addClass('coverall-div')
                        .css({'zIndex':60000,'padding':'30px 0 0 30px','font-size':'1.2em','opacity':0.8,'color':'white'})
                        .appendTo('body');
                    */
                    //show wait screen
                    window.hWin.HEURIST4.msg.bringCoverallToFront(null, null, 'Preparing archive file...');
                }

                document.getElementById('buttons').style.visibility = 'hidden';
                document.forms[0].submit();//page reload
            }

        </script>
    </head>
    <body class="popup ui-heurist-admin">

        <?php

        //<div class="banner"  style="padding-left:3px"><h2>Complete data archive package</h2></div>

        $please_advise = "<br>Please consult with your system administrator for a resolution.";

        if(!$mode) {
            ?>

            <h3 class="ui-heurist-title">This function is available to database adminstrators only (therefore you are a database administrator!)</h3>
            <p>The data will be exported as a fully self-documenting HML (Heurist XML) file, as a complete MySQL SQL data dump,
            as textual and wordprocessor descriptions of the structure of Heurist and of your database, and as a directory of
            attached files (image files, video, XML, maps, documents etc.) which have been uploaded or indexed in situ.
            </p>
            <p>The MySQL dump will contain the complete database which can be reloaded on any up-to-date MySQL database server.
            </p>
        <?php if(!$is_repository){?>
            <p>The output of the process will be made available as a link to a downloadable zip file
            <br>but is also available to the system adminstrator as a file in the backup directory in the database.
            </p>
            <h3 class="ui-heurist-title">Warning</h3>
            <p>Zipping databases with large numbers of images or very large files such as high
            <br>resolution maps or video may bog down the server and the zip file may be too big to download.
            <br>In that case you may need to ask your sysadmin to give you the files separately on a USB drive.</p>
        <?php }else{ ?>
            <p>The output of the process will be zipped and uploaded to the selected repository
            </p>
            <h3 class="ui-heurist-title">Warning</h3>
            <p>Zipping databases and including lots of images or video may bog down the server and the file may not upload to the repository
            - in that case it may be better to ask your sysadmin to give you the files separately on a USB drive and attempt to upload it yourself.</p>
        <?php } ?>
            <p>Attached files may be omitted by unchecking the first checkbox.
            <br>This may also be useful for databases with lots of attached files which are already backed up elsewhere.
            </p>

            <form name='f1' action='exportMyDataPopup.php' method='get'>

                <input name='db' value='<?=HEURIST_DBNAME?>' type='hidden'>
                <input name='mode' value='1' type='hidden'>

                <div class="input-row" style="padding-top:10px">
                    <label>
                        <input type="checkbox" name="includeresources" value="1">
                        Include attached (uploaded) files eg. images (essential for full database archive).
                    </label>
                </div>

                <div class="input-row">
                    <label title="Adds all tilestacks that have been uploaded to Heurist">
                        <input type="checkbox" name="include_tilestacks" value="1">
                        Include tiled map images - these are typically very large and are probably already available elsewhere
                    </label>
                </div>

                <div class="input-row">
                    <label title="Adds fully self-documenting HML (Heurist XML) file">
                        <input type="checkbox" name="include_hml" value="1">
                        Include HML (not required for transfer to a new server, but recommended for long-term archive)
                    </label>
                </div>

                <div class="input-row">
                    <label title="Adds a folder containing the database dump in TSV format">
                        <input type="checkbox" name="include_tsv" value="1">
                        Include database dump in TSV format (includes a complete record export in TSV format)
                    </label>
                </div>

<!-- 2024-04-09 - we use solely zip
                <div class="input-row">
                    <label title="Export / Upload the archive in BZip format, instead of Zip">
                        <input type="checkbox" name="is_tar" value="1">
Use BZip format rather than Zip (BZip is more efficient for archiving, but Zip is faster if there are lot of images and easier to open on personal computers)
                    </label>
                </div>
-->
                <div class="input-row" style="display:none;">
                    <label
                        title="Adds documents describing Heurist structure and data formats - check this box if the output is for long-term archiving">
                        <input type="checkbox" name="include_docs" value="1" checked>
                        Include background documentation for archiving
                    </label>
                </div>

                <div class="input-row" style="display: none;">
                    <label>
                        <input type="checkbox" name="allrecs" value="1" checked>
                        Include resources from other users (everything to which I have access)
                    </label>
                </div>

        <?php if($is_repository){ ?>
                <div class="input-row" style="padding: 20px 0 5px 0;">
                    <span>Select a repository <select id='sel_repository' name='repository'></select> </span>
                </div>

                <div class="input-row" style="padding: 10px 0px;">
                    <span>
                        Select an account
                        <select id='sel_accounts' name='repo_account'></select>

                        <span class="heurist-helper1 setup-keys" style="vertical-align: middle;display: none;">
                            This key is for a test account, you can setup your own key at Design > External repositories
                        </span>
                    </span>
                </div>

                <div id="nakala-url" class="input-row repo-Nakala" style="padding: 10px 0px; display: none;">
                    <span>
                        Select which version of Nakala to use: <br><br>
                        <label style="display: inline-block; margin-bottom: 5px;"> <input type='radio' name='use_test_url' value='0' checked="checked"> Standard</label> <br>
                        <label style="display: inline-block; margin-bottom: 5px;"> <input type='radio' name='use_test_url' value='1'> Test (test.nakala.fr)</label>
                        <span class="heurist-helper1" style="vertical-align: middle;">
                            Please note that this version should only be used for testing / temporary storage as at any moment the uploaded Zip can be cleared by Nakala/Huma-num
                        </span>
                    </span>
                </div>

                <div class="input-row repo-Nakala" style="display: none;padding: 5px 0 10px 0;">
                    <span>
                        Select a license
                        <select id='sel_license' name='license'> <option value="">select a license...</option> </select>
                    </span>
                </div>
        <?php } ?>

                <div id="buttons" class="actionButtons" style="padding-top:10px;text-align:left">
                    <input type="button" value="<?php echo $is_repository ? 'Export & Upload' : 'Create Archive';?>"
                        style="margin-right: 20px;" class="ui-button-action" onClick="{ exportArchive();}">
                    <input type="button" id="btnClose_1" value="Cancel" onClick="closeArchiveWindow();">
                </div>
            </form>
            <?php

        }else{

            $operation_in_progress = 'It appears that backup operation has been started already. Please try this function later';

            //flag that backup in progress
            if(!isActionInProgress('exportDB', 2, HEURIST_DBNAME)){
                report_message($operation_in_progress, false);
            }else{
                echo_flush2("<br>Beginning archive process<br>");
            }

            $separate_sql_zip = !$is_repository;
            $separate_hml_zip = !$is_repository && @$_REQUEST['include_hml']=='1';
            $separate_tsv_zip = !$is_repository && @$_REQUEST['include_tsv']=='1';

            //
            if(file_exists(FOLDER_BACKUP)){
                echo_flush2("<br>Clear folder ".FOLDER_BACKUP."<br>");
                //clean folder
                $res = folderDelete2(FOLDER_BACKUP, true);
                if(!$res){
                    report_message($operation_in_progress, false);
                }
            }
            if (!folderCreate(FOLDER_BACKUP, true)) {
                $message = 'Failed to create folder '.FOLDER_BACKUP.'<br> in which to create the backup. Please consult your sysadmin.';
                report_message($message, true);
            }

            // Just SQL dump
            if(file_exists(FOLDER_SQL_BACKUP)){
                $res = folderDelete2(FOLDER_SQL_BACKUP, true);
                if(!$res){
                    report_message($operation_in_progress, false);
                }
            }
            if($separate_sql_zip && !folderCreate(FOLDER_SQL_BACKUP, true)){
                $separate_sql_zip = false; // hide option
            }
            if($separate_hml_zip && !folderCreate(FOLDER_HML_BACKUP, true)){
                $separate_hml_zip = false; // hide option
            }
            if($separate_tsv_zip && !folderCreate(FOLDER_TSV_BACKUP, true)){
                $separate_tsv_zip = false; // hide option
            }
            if(@$_REQUEST['include_tsv'] == 1 && !folderCreate(FOLDER_BACKUP . '/tsv-output/records', true)){ // create tsv output directory
                $_REQUEST['include_tsv'] = 0;
                echo_flush2("Failed to create sub directory for TSV output within backup directory<br>");
            }

            $repo = !empty(@$_REQUEST['repository']) ? htmlspecialchars($_REQUEST['repository']) : null;
            if($is_repository && (!$repo || $repo != 'Nakala')){
                report_message('The repository ' . $repo . ' is not supported please ' . CONTACT_HEURIST_TEAM, true, false);
            }

            $folders_to_copy = [];

            $copy_uploaded_files = (@$_REQUEST['includeresources']=='1');

            //copy resource folders
            if(@$_REQUEST['include_docs']=='1'){
                $folders_to_copy = folderSubs(HEURIST_FILESTORE_DIR,
                    array('backup', 'scratch', 'generated-reports', 'file_uploads', 'filethumbs',
                          'tileserver', 'uploaded_files', 'uploaded_tilestacks', 'rectype-icons',
                          'term-images', 'webimagecache', 'blurredimagescache'));//except these folders - some of them may exist in old databases only

                //limited set
                echo_flush2("<br><br>Exporting system folders<br>");
            }

            //custom user upload folders
            $user_media_folders = $system->settings->get('sys_MediaFolders');
            $user_media_folders = explode(';', $user_media_folders);
            foreach($user_media_folders as $dir){

                $path = HEURIST_FILESTORE_DIR . $dir;
                if(file_exists($path)){
                    if(substr($path, -1, 1) != '/'){
                        $path = $path. '/';
                    }
                    if($copy_uploaded_files){
                        folderRecurseCopy( $path, FOLDER_BACKUP.'/'.$dir );
                    }
                    //exclude from full list of folders
                    $key = array_search($path, $folders_to_copy);
                    if($key!==false){
                       unset($folders_to_copy[$key]);
                    }
                }
            }//for

            if($copy_uploaded_files){ //uploaded images in standard folder

                $folders_to_copy[] = HEURIST_FILES_DIR;
                $folders_to_copy[] = HEURIST_THUMB_DIR;
                $copy_files_in_root = true; //copy all files within database folder

            }else{
                $copy_files_in_root = false;
            }

            if(@$_REQUEST['include_tilestacks']=='1' && defined('HEURIST_TILESTACKS_DIR')){
                $folders_to_copy[] = HEURIST_TILESTACKS_DIR;
            }

            if(@$_REQUEST['include_docs']=='1' || $copy_uploaded_files){
               folderRecurseCopy( HEURIST_FILESTORE_DIR, FOLDER_BACKUP, $folders_to_copy, $copy_files_in_root);
            }

            if(@$_REQUEST['include_docs']=='1'){// 2016-10-25
                echo_flush2('Copy context_help folder<br>');
                folderRecurseCopy( HEURIST_DIR.'context_help/', FOLDER_BACKUP.'/context_help/');
            }


           //remove dbdef_cache.json (database definitions cache) from entity folder
           fileDelete(FOLDER_BACKUP.'/entity/db.json');//old name
           fileDelete(FOLDER_BACKUP.'/entity/dbdef_cache.json');


           if(@$_REQUEST['include_hml']=='1'){

               echo_flush2("Exporting database as HML (Heurist Markup Language = XML)<br>(may take several minutes for large databases)<br>");

               /*
               $hml_url = HEURIST_BASE_URL.'export/xml/flathml.php?w=all&a=1&rev=no&filename=1&db='.HEURIST_DBNAME;

               //load hml output into string file and save it
               if(@$_REQUEST['allrecs']!="1"){
                   $userid = $system->get_user_id();
                   $q = "owner:$userid";//user:$userid OR

                   $hml_url = $hml_url.'&depth=5';
               }else{
                   $q = "sortby:-m";


                   $hml_url = $hml_url.'&depth=0&linkmode=none';
               }
               $hml_url = $hml_url.'&q='.$q;
               $outres = loadRemoteURLContentWithRange($hml_url, null, true);
               */

               //load hml output into string file and save it
               if(@$_REQUEST['allrecs']!="1"){
                   $userid = $system->get_user_id();
                   $q = "owner:$userid";//user:$userid OR
                   $_REQUEST['depth'] = '5';
               }else{
                   $q = "sortby:-m";
                   $_REQUEST['depth'] = '0';
                   $_REQUEST['linkmode'] = 'none';
               }


               $_REQUEST['w'] = 'all';
               $_REQUEST['a'] = '1';
               $_REQUEST['q'] = $q;
               $_REQUEST['rev'] = 'no'; //do not include reverse pointers
               $_REQUEST['filename'] = '1';

               $to_include = dirname(__FILE__).'/../../export/xml/flathml.php';
               if (is_file($to_include)) {
                   include_once $to_include;
               }


               if(file_exists(FOLDER_BACKUP.'/'.HEURIST_DBNAME.'.xml') && $separate_hml_zip){
                   $separate_hml_zip = fileCopy(FOLDER_BACKUP.'/'.HEURIST_DBNAME.'.xml', FOLDER_HML_BACKUP."/".HEURIST_DBNAME.".xml");
               }

           }//export HML

           if(@$_REQUEST['include_tsv']=='1'){

                require_once dirname(__FILE__).'/../../hserv/records/export/recordsExportCSV.php';

                $mysqli = $system->get_mysqli();

                // Export tables
                $skip_tables = [
                    'defCalcFunctions', 'recLinks',
                    'recSimilarButNotDupes', //'Records', 'recDetails',
                    'sysArchive', 'sysLocks', 'usrHyperlinkFilters'
                ];// tables to skip - woot, import and index are filtered out below

                $tables = mysql__select_list2($mysqli, "SHOW TABLES");

                $field_types = [];
                $record_types = [];

                echo_flush2("Exporting database tables as TSV<br>");

                foreach ($tables as $table) {

                    if(strpos($table, 'woot') !== false || strpos($table, 'import') === 0 || strpos($table, 'Index') !== false || in_array($table, $skip_tables)){
                        continue;
                    }

                    $query = "SELECT * FROM $table";
                    $res = $mysqli->query($query);

                    $get_headers = true;

                    if(!$res || $res->num_rows == 0){
                        continue;
                    }

                    $filename = FOLDER_BACKUP . "/tsv-output/{$table}.tsv";
                    $fd = fopen($filename, 'w');
                    if(!$fd){
                        $msg = error_get_last();
                        $msg = !empty($msg) ? print_r($msg, true) : "None provided";
                        echo_flush2("<br>Unable to create TSV file for $table values at $filename<br>Error message: $msg<br><br>");
                        break;
                    }

                    while($row = $res->fetch_assoc()){

                        if($table == 'defDetailTypes'){

                            $field_types[ $row['dty_ID'] ] = [ 'type' => $row['dty_Type'] ];

                        }elseif($table == 'defRecStructure'){

                            $rty_ID = $row['rst_RecTypeID'];
                            $dty_ID = $row['rst_DetailTypeID'];

                            if($field_types[$dty_ID]['type'] == 'separator'){
                                continue;
                            }

                            if(!array_key_exists($rty_ID, $record_fields)) {
                                $record_fields[$rty_ID] = [ 'rec_ID', 'rec_Title' ];// add id + title by default
                            }

                            $record_fields[$rty_ID][] = "$dty_ID";
                        }

                        if($get_headers){ // get table field names

                            $w_res = fputcsv($fd, array_keys($row), "\t");
                            if(!$w_res){

                                echo_flush2("Unable to write table headings to TSV file for $table<br>");

                                fclose($fd);
                                $res->close();
                                continue;
                            }

                            $get_headers = false;
                        }

                        $w_res = fputcsv($fd, $row, "\t");
                        if(!$w_res){

                            echo_flush2("Unable to write table row to TSV file for $table<br>");

                            fclose($fd);
                            $res->close();
                        }

                    }

                    fclose($fd);
                    $res->close();

                    if(filesize($filename) == 0){ // remove empty files
                        fileDelete($filename);
                    }
                }

                echo_flush2("Exporting database records as TSV<br>(may take several minutes for large databases)<br>");

                // Export records per rectype
                foreach ($record_fields as $rty_ID => $field_codes) {

                    $rty_CC_ID = ConceptCode::getRecTypeConceptID($rty_ID);
                    $rty_CC_ID = preg_replace('/^0000\-/', '0', $rty_CC_ID);
                    $rty_Name = mysql__select_value($mysqli, "SELECT rty_Name FROM defRecTypes WHERE rty_ID = $rty_ID");

                    $request = [
                        'detail' => 'ids',
                        'q' => "t:{$rty_ID}"
                    ];

                    $response = recordSearch($system, $request);
                    if($response['status'] != HEURIST_OK){

                        echo_flush2("Unable to retrieve records for record type #$rty_ID<br>");
                        continue;
                    }elseif($response['data']['reccount'] == 0){
                        continue;
                    }

                    $options = [
                        'prefs' => [
                            'main_record_type_ids' => $rty_ID,
                            'term_ids_only' => 1,
                            'include_resource_titles' => 1,
                            'include_temporals' => 1,
                            'fields' => [$rty_ID => $field_codes],
                            'csv_delimiter' => "\t"
                        ],
                        'save_to_file' => 1,
                        'file' => [
                            'directory' => FOLDER_BACKUP . "/tsv-output/records",
                            'filename' => "{$rty_CC_ID}_{$rty_ID}_{$rty_Name}.tsv"
                        ]
                    ];

                    $res = RecordsExportCSV::output($response, $options);
                    if($res <= 0){

                        $msg = $res == 0 ? "Failed to write to TSV file for record type #$rty_ID" : "An error occurred while handling the record type #$rty_ID, error was placed within TSV file";

                        echo_flush2("<span style='color: red;margin-left: 5px;'>$msg</span><br>");
                    }
                }//for

                $separate_tsv_zip = $separate_tsv_zip && folderSize2(FOLDER_BACKUP . "/tsv-output") > 0;
                if($separate_tsv_zip){ // copy tsv dumps to separate directory
                    $separate_tsv_zip = folderRecurseCopy(FOLDER_BACKUP . '/tsv-output', FOLDER_TSV_BACKUP);
                }
            }

           // Export database definitions as readable text

           echo_flush2("Exporting database definitions as readable text<br>");

           $url = HEURIST_BASE_URL . "hserv/structure/export/getDBStructureAsSQL.php?db=".HEURIST_DBNAME."&pretty=1";
           saveURLasFile($url, FOLDER_BACKUP."/Database_Structure.txt");//save to HEURIST_FILESTORE_DIR.DIR_BACKUP.HEURIST_DBNAME

           echo_flush2("Exporting database definitions as XML<br>");

           $url = HEURIST_BASE_URL . "hserv/structure/export/getDBStructureAsXML.php?db=".HEURIST_DBNAME;
           saveURLasFile($url, FOLDER_BACKUP."/Database_Structure.xml");//save to HEURIST_FILESTORE_DIR.DIR_BACKUP.HEURIST_DBNAME


           if($system->is_admin()){
                // Do an SQL dump of the whole database
                echo_flush2("Exporting SQL dump of the whole database (several minutes for large databases)<br>");


                $database_dumpfile = FOLDER_BACKUP."/".HEURIST_DBNAME."_MySQL_Database_Dump.sql";
                $dump_options = array('skip-triggers' => true,
                                      'single-transaction' => true,
                                      'quick' =>true,
                                      'add-drop-trigger' => false, 'no-create-db' =>true, 'add-drop-table'=>true);

                $res = DbUtils::databaseDump(HEURIST_DBNAME_FULL, $database_dumpfile, $dump_options, false );

                if(!$res){
                    print DIV_E;
                    report_message("Sorry, unable to generate MySQL database dump. ".$system->getErrorMsg().'  '.$please_advise, true, true);
                }

                if($separate_sql_zip){ // copy sql dump to separate directory
                    $separate_sql_zip = fileCopy($database_dumpfile, FOLDER_SQL_BACKUP."/".HEURIST_DBNAME."_MySQL_Database_Dump.sql");
                }
           }

           // remove old mysql dump - specifically the ones named HEURIST_DBNAME_FULL.sql
           if(file_exists(FOLDER_BACKUP.'/'.HEURIST_DBNAME_FULL.'.sql')) {
               unlink(FOLDER_BACKUP.'/'.HEURIST_DBNAME_FULL.'.sql');
           }

           echo_flush2('<br>Zipping files<br>');

           // Create a zipfile of the definitions and data which have been dumped to disk
           $destination = FOLDER_BACKUP.'.'.$format; // Complete archive
           if(file_exists($destination)){
               unlink($destination);
           }
           if($format == 'tar' && file_exists(FOLDER_BACKUP.'.tar.bz2')){
               unlink(FOLDER_BACKUP.'.tar.bz2');
           }

           if($format == 'zip'){
               $res = UArchive::zip(FOLDER_BACKUP, null, $destination, true);
           }else{
               $res = UArchive::createBz2(FOLDER_BACKUP, null, $destination, true);
           }

           if($res===true){ //archive successful

                $res_sql = false;
                if($separate_sql_zip){

                    $destination_sql = FOLDER_BACKUP.'_sql.'.$format; // SQL dump only
                    if(file_exists($destination_sql)){
                        unlink($destination_sql);
                    }
                    if($format == 'tar' && file_exists(FOLDER_BACKUP.'_sql.tar.bz2')){
                        unlink(FOLDER_BACKUP.'_sql.tar.bz2');
                    }

                    if($format == 'zip'){
                        $res_sql = UArchive::zip(FOLDER_BACKUP.'_sql', null, $destination_sql, true);
                    }else{
                        $res_sql = UArchive::createBz2(FOLDER_BACKUP.'_sql', null, $destination_sql, true);
                    }
                }

                $res_hml = false;
                if($separate_hml_zip){

                    $destination_hml = FOLDER_BACKUP.'_hml.'.$format; // HML dump only
                    if(file_exists($destination_hml)){
                        unlink($destination_hml);
                    }
                    if($format == 'tar' && file_exists(FOLDER_BACKUP.'_hml.tar.bz2')){
                        unlink(FOLDER_BACKUP.'_hml.tar.bz2');
                    }

                    if($format == 'zip'){
                        $res_hml = UArchive::zip(FOLDER_BACKUP.'_hml', null, $destination_hml, true);
                    }else{
                        $res_hml = UArchive::createBz2(FOLDER_BACKUP.'_hml', null, $destination_hml, true);
                    }

                }

                $res_tsv = false;
                if($separate_tsv_zip){

                    $destination_tsv = FOLDER_BACKUP.'_tsv.'.$format; // TSV dump only
                    if(file_exists($destination_tsv)){
                        unlink($destination_tsv);
                    }
                    if($format == 'tar' && file_exists(FOLDER_BACKUP.'_tsv.tar.bz2')){
                        unlink(FOLDER_BACKUP.'_tsv.tar.bz2');
                    }

                    if($format == 'zip'){
                        $res_tsv = UArchive::zip(FOLDER_BACKUP.'_tsv', null, $destination_tsv, true);
                    }else{
                        $res_tsv = UArchive::createBz2(FOLDER_BACKUP.'_tsv', null, $destination_tsv, true);
                    }

                }

                if(!$is_repository) {
                        //success - print two links to download archives

        $is_zip = '&is_tar='.($format == 'tar' ? 1 : 0);

        if($format == 'tar'){
            $format = 'tar.bz2';
        }
    ?>
    <p>Your data has been backed up in <?php echo FOLDER_BACKUP;?></p>
    <br><br><div class='lbl_form'></div>
        <a href="exportMyDataPopup.php/<?php echo HEURIST_DBNAME;?>.<?php echo $format; ?>?mode=2&db=<?php echo HEURIST_DBNAME.$is_zip;?>"
            target="_blank" rel="noopener" style="color:blue; font-size:1.2em">Click here to download your data as a <?php echo $format;?> archive</a>

    <?php
    if($separate_sql_zip){
        if($res_sql===true){ ?>
        <br><br>
        <a href="exportMyDataPopup.php/<?php echo HEURIST_DBNAME;?>_sql.<?php echo $format; ?>?mode=3&db=<?php echo HEURIST_DBNAME.$is_zip;?>"
            target="_blank" rel="noopener" style="color:blue; font-size:1.2em">Click here to download the SQL <?php echo $format;?> file only</a>
        <span class="heurist-helper1">(for db transfer on tiered servers)</span>
    <?php }else{ ?>
        <br><br>
        <div>Failed to create standalone SQL dump. <?php echo $res_sql;?></div>
    <?php
        }
    }
    if($separate_hml_zip){
        if($res_hml===true){ ?>
        <br><br>
        <a href="exportMyDataPopup.php/<?php echo HEURIST_DBNAME;?>_hml.<?php echo $format; ?>?mode=5&db=<?php echo HEURIST_DBNAME.$is_zip;?>"
            target="_blank" rel="noopener" style="color:blue; font-size:1.2em">Click here to download the HML <?php echo $format;?> file only</a>
    <?php }else{ ?>
        <br><br>
        <div>Failed to create / set up a standalone HML file. <?php echo $res_hml;?></div>
    <?php
        }
    }
    if($separate_tsv_zip){
        if($res_tsv===true){ ?>
        <br><br>
        <a href="exportMyDataPopup.php/<?php echo HEURIST_DBNAME;?>_tsv.<?php echo $format; ?>?mode=6&db=<?php echo HEURIST_DBNAME.$is_zip;?>"
            target="_blank" rel="noopener" style="color:blue; font-size:1.2em">Click here to download the TSV <?php echo $format;?> folder only</a>
    <?php }else{ ?>
        <br><br>
        <div>Failed to create / set up a standalone TSV folder. <?php echo $res_tsv;?></div>
    <?php
        }
    }
    ?>
    <p class="heurist-helper1">
    Note: If this file fails to download properly (eg. "Failed … file incomplete") the file is too large to download. Please ask your system administrator (<?php echo HEURIST_MAIL_TO_ADMIN; ?>) to send it to you via a large file transfer service
    </p>

                    <input type="button" id="btnClose_2" class="ui-button-action" value="Close" onClick="closeArchiveWindow();">

    <?php
                }
                elseif($is_repository){
                    //upload archive to repository

                    $repo_account = htmlspecialchars($_REQUEST['repo_account']);

                    if($format == 'tar'){
                        $format = 'tar.bz2';
                    }

                    $repo_details = user_getRepositoryCredentials2($system, $repo_account);

                    echo_flush2('<hr><br>Uploading archive to ' . $repo . '...');

                    // Prepare metadata
                    if($repo_details === null ||!@$repo_details[$repo_account]['params']['writeApiKey']){

                        $msg = $repo_details === null ?
                                'Credentials for sepecified repository and user/group not found.' : 'Write Credentials for sepecified repository and user/group not defined.';

                        $msg .= ' Please check the credentials within Design > External repositories.';

                        report_message($msg, true, true);
                    }elseif($repo == 'Nakala'){
                        // Title, Type, Alt Author, License, Created

                        $date = date('Y-m-d');

                        $params = array();
                        $params['file'] = array(
                            'path' => FOLDER_BACKUP . '.' . $format,
                            'type' => $mime,
                            'name' => HEURIST_DBNAME . '.' . $format
                        );

                        $params['meta']['title'] = array(
                            'value' => 'Archive of ' . HEURIST_DBNAME . ' on ' . $date,
                            'lang' => null,
                            'typeUri' => XML_SCHEMA,
                            'propertyUri' => NAKALA_REPO.'terms#title'
                        );

                        $usr = $system->getCurrentUser();
                        if(is_array($usr) && !empty($usr)){
                            $params['meta']['creator'] = array(
                                'value' => $usr['ugr_FullName'],
                                'lang' => null,
                                'typeUri' => XML_SCHEMA,
                                'propertyUri' => 'http://purl.org/dc/terms/creator'
                            );
                        }

                        $params['meta']['created'] = array(
                            'value' => $date,
                            'lang' => null,
                            'typeUri' => XML_SCHEMA,
                            'propertyUri' => NAKALA_REPO.'terms#created'
                        );

                        $params['meta']['type'] = array(
                            'value' => 'http://purl.org/coar/resource_type/c_ddb1',
                            'lang' => null,
                            'typeUri' => 'http://www.w3.org/2001/XMLSchema#anyURI',
                            'propertyUri' => NAKALA_REPO.'terms#type'
                        );

                        if(array_key_exists('license', $_REQUEST) && !empty($_REQUEST['license'])){
                            $params['meta']['license'] = array(
                                'value' => $_REQUEST['license'],
                                'lang' => null,
                                'typeUri' => XML_SCHEMA,
                                'propertyUri' => NAKALA_REPO.'terms#license'
                            );
                        }

                        $params['api_key'] = $repo_details[$repo_account]['params']['writeApiKey'];
                        $params['use_test_url'] = @$_REQUEST['use_test_url'] == 1 || strpos($repo_account,'nakala')===1 ? 1 : 0; // use test version

                        $params['status'] = 'pending';// keep new record private, so it can be deleted
                        $params['return_type'] = 'editor';// return link to private record, will require login

                        $rtn = uploadFileToNakala($system, $params);//upload database archive

                        if($rtn === false){
                            $rtn = $system->getErrorMsg();
                            echo_flush2('failed<br>');
                        }else{
                            echo_flush2('finished<br>');
                            $rtn = htmlspecialchars($rtn);
                            $rtn = 'The uploaded archive is at <a href="' . $rtn . '" target="_blank">'
                                        . $rtn . '&nbsp;<span class="ui-icon ui-icon-extlink" /> </a>';
                        }

                        echo_flush2('<br>'. $rtn .'<br>');
                    }else{

                        report_message('The repository ' . $repo . ' is not supported please ' . CONTACT_HEURIST_TEAM, true, true);
                    }

                }//end repository

                report_message('', false, true);
            }else
            {
                report_message($res.'<br>Try different archive format otherwise please consult system adminstrator', true, true);
            }

        }
        ?>

    </body>
</html>

<?php
function report_message($message, $is_error=true, $need_cleanup=false)
{
    if($need_cleanup){
            if(array_key_exists('repository', $_REQUEST)){
                //cleanup backup after upload to reporsitory
                folderDelete2(HEURIST_FILESTORE_DIR.DIR_BACKUP, false);
            }else{
                //cleanup temp folders
                folderDelete2(FOLDER_BACKUP, true);
                folderDelete2(FOLDER_SQL_BACKUP, true);
                folderDelete2(FOLDER_HML_BACKUP, true);
                folderDelete2(FOLDER_TSV_BACKUP, true);
            }
            //remove lock file
            isActionInProgress('exportDB', -1, HEURIST_DBNAME);
    }
    if($message){
?>
        <div class="ui-corner-all ui-widget-content" style="text-align:left; width:70%; min-width:220px; margin:0px auto; padding: 0.5em;">

            <!-- <div class="logo" style="background-color:#2e3e50;width:100%"></div> -->

            <div class="<?php echo $is_error?'ui-state-error':'';?>"
                style="width:90%;margin:auto;margin-top:10px;padding:10px;">
                <span class="ui-icon <?php echo $is_error?'ui-icon-alert':'ui-icon-info';?>"
                      style="float: left; margin-right:.3em;font-weight:bold"></span>
                <?php echo $message;?>
            </div>
        </div>
<?php
    }
?>
        <script>if(window.hWin.HEURIST4.msg){ window.hWin.HEURIST4.msg.sendCoverallToBack(true);}</script>
    </body>
</html>
<?php
    exit;
}
?>