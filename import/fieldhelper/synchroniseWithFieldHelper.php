<?php

/**
* synchroniseWithFieldHelper.php
* Read the FieldHelper XML manifests in directories specified in Advanced Properties,
* and creates Heurist records for indexed files. If there is no manifest, it creates one.
* The list of extensions indexed can also be specified in Advanced Properties, otherwise
* it indexes a range of common file types including most text, image, audio and video formats
*
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2
* @param       includeUgrps=1 will output user and group information in addition to definitions
* @param       approvedDefsOnly=1 will only output Reserved and Approved definitions
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
* @todo        write Heurist IDs back into FH XML files
* @todo        update existing records from XML files which have changed
* @todo        update XML files from Heurist records which have changed
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
use hserv\utilities\USanitize;

define('MANAGER_REQUIRED',1);
define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
require_once dirname(__FILE__).'/../../hserv/records/edit/recordModify.php';
require_once dirname(__FILE__).'/../../hserv/records/search/recordFile.php';

require_once dirname(__FILE__).'/../../hserv/structure/import/dbsImport.php';

$system->defineConstants();

global $rep_counter, $rep_issues, $system_folders,
$fieldhelper_to_heurist_map, $mediaExts, $progress_divid,
$geoDT, $fileDT, $titleDT, $startdateDT, $enddateDT, $descriptionDT, $failed_exts;

if(!defined('RT_MEDIA_RECORD')){

    $importDef = new DbsImport( $system );
    $isOK = $importDef->checkAndImportRty($rtDefines['RT_MEDIA_RECORD']);
    
    if(!$isOK){
        $system->addErrorMsg('Cannot download record type "Digital Media item" required by the function you have requested. ');
        include_once ERROR_REDIR;
        exit;
    }
    if(!$system->defineConstant('RT_MEDIA_RECORD', true)){
        $system->addError(HEURIST_ERROR, 'Record type "Digital Media item"" id not defined');
        include_once ERROR_REDIR;
        exit;
    }
}

/*
<Name0>
<Annotation>
<latitude>
<longitude>
<filename>
<extension>
*/


$titleDT = (defined('DT_NAME')?DT_NAME:0);
$geoDT = (defined('DT_GEO_OBJECT')?DT_GEO_OBJECT:0);
$fileDT = (defined('DT_FILE_RESOURCE')?DT_FILE_RESOURCE:0);
$startdateDT = (defined('DT_START_DATE')?DT_START_DATE:0);
$enddateDT = (defined('DT_END_DATE')?DT_END_DATE:0);
$descriptionDT = (defined('DT_SHORT_SUMMARY')?DT_SHORT_SUMMARY:0);

$fieldhelper_to_heurist_map = array(
    "heurist_id" => "recordId",
    "Name0" => $titleDT,
    "Annotation" => $descriptionDT,
    "DateTimeOriginal" => $startdateDT,
    "filename" => "file",
    "latitude" => "lat",
    "longitude" => "lon",

    "file_name" => (defined('DT_FILE_NAME')?DT_FILE_NAME:0),
    "file_path" => (defined('DT_FILE_FOLDER')?DT_FILE_FOLDER:0),
    "extension"  => (defined('DT_FILE_EXT')?DT_FILE_EXT:0),

    "device"      => (defined('DT_FILE_DEVICE')?DT_FILE_DEVICE:0),
    "duration"  => (defined('DT_FILE_DURATION')?DT_FILE_DURATION:0),
    "filesize"  => (defined('DT_FILE_SIZE')?DT_FILE_SIZE:0),
    "md5"          => (defined('DT_FILE_MD5')?DT_FILE_MD5:0)
);

$rep_counter = null;
$rep_issues = null;
$currfile = null;
$mediaExts = null;
$progress_divid = 0;
$system_folders = $system->getSystemFolders();
$failed_exts = array();

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        <title>Import Records In Situ / FieldHelepr Manifests</title>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>

        <!-- CSS -->
        <?php include_once dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php';?>

    </head>
    <body class="popup">

        <script type="text/javascript">

            function update_counts(divid, processed, added, total) {
                document.getElementById("progress"+divid).innerHTML = (total==0)?"": (" <div style=\"color:green\"> Processed "
                    +processed+" of "+total+". Added records: "+added+'</div>');
            }

            function sysIdentificationPopup() {
                window.hWin.HEURIST4.ui.showEntityDialog('sysIdentification');
                return false;
            }
        </script>

        <h2>ADVANCED USERS</h2>

        <p style="font-weight:bold;font-size:larger;padding:10 0">Create Digital Media records from uploaded files (often images)</p>

        <p>Note: Use with caution, this function can create a lot of extra records if there are many images</p>

        <p>This function creates, updates and reads XML manifest files in the folders (and their descendants)
            listed in Design > Properties (Paths which contain files to be indexed as multimedia records)
            and creates Digital Media records for all files uploaded to the database, with pointers back to the files.
            New files are added to existing manifests. </p>

        <p>
            Digital Media records are only added for files without such a record, existing Digital Media records are unaffected.
        </p><p>
            <i>Note (in case of need): If you get the message that your folders are not writeable, ask your system adminstrator to adjust the permissions on the HEURIST_FILESTORE directory and its descendants. The folders in the filestore must be writeable by the PHP system - normally they should belong to and be writable by the group heurist (which should be set up to include apache and any administrators who have access to the backend), or be owned and writable by apache or www-data (as appropriate).</i>
        </p>
        <p>
            Files should be uploaded through Populate > Upload media files/images (limited normally to 30M) or by direct sftp access to the file_uploads directory, or sub-directories, on the server for larger files or very large bulk uploads (available to system administrators only).
        </p>
        <?php

        $notfound = array();
        foreach ($fieldhelper_to_heurist_map as $key=>$id){
            if(is_numeric($id) && $id==0){
                array_push($notfound, $key);
            }
        }
        if(!empty($notfound)){
            print "<p style='color:brown;'> Warning: There are no fields in this database to hold the following information: <b>".
            implode(", ",$notfound).
            "</b><br>Note: these fields may appear to be present, but do not have the correct origin codes ".
            "(source of the field definition) for this function to use them.".
            "<p>We recommend importing the appropriate fields by (re)importing the Digital Media Item record type as follows".
            "<ul><li>Go to Design &gt; Browse templates<br>&nbsp;</li>".
            "<li>Navigate to the Heurist_Core_Definitions database (# 2)<br>&nbsp;</li>".
            "<li>Check 'Show all' checkbox and open the Media group</li>&nbsp;".
            "<li>Click the download icon on the Digital Media record type</li></ul>".
            "You may proceed without this step, but these fields will not be imported</p>";
        }

        // ----FORM 1 - CHECK MEDIA FOLDERS --------------------------------------------------------------------------------

        if(!array_key_exists('mode', $_REQUEST)) {

            if(false && !($system->settings->get('sys_dbRegisteredID')>0)){ //is not registered

                print "<div class='ui-state-error' style='padding:10px'>Note: Database must be registered with the Heurist master index to use this function<br>".
                "Register the database using Database administration page > Database > Registration - ".
                "only available to the database creator/owner (user #2)</div>";

            }else{

                $mediaFolders = $system->settings->get('sys_MediaFolders');
                $mediaExts = $system->settings->get('sys_MediaExtensions');

                if($mediaFolders==null || $mediaFolders==''){
                    $mediaFolders = HEURIST_FILESTORE_DIR.'uploaded_files/';
                    folderCreate( $mediaFolders, true );
                }
                $dirs = explode(';', $mediaFolders);// get an array of folders

                //sanitize folder names
                $dirs = array_map(array('hserv\utilities\USanitize', 'sanitizePath'), $dirs);
                $mediaFolders = implode(';', $dirs);

                // The defined list of file extensions for FieldHelper indexing.
                if($mediaExts==null || $mediaExts==''){
                    $mediaExts = HEURIST_ALLOWED_EXT;
                }

                print "<p><b>Folders to scan :</b> $mediaFolders<p>";
                print "<p><b>Extensions to scan:</b> $mediaExts<p>";
        ?>
                <p><a href='#' onclick="sysIdentificationPopup();"
                    title='Open form to edit properties which determine the handling of files and directories in the database upload folders'>
                    Click here to set media folders (database file directory descendants scanned by default)</a>
                </p>
        <?php

                if (!($mediaFolders=="" || empty($dirs))) {
                    print "<form name='selectdb' action='synchroniseWithFieldHelper.php' method='get'>";
                    print "<input name='mode' value='2' type='hidden'>";// calls the form to select mappings, step 2
                    print "<input name='db' value='".HEURIST_DBNAME."' type='hidden'>";
                    print '<input name="media" value="'.$mediaFolders.'" type="hidden">';
                    print "<input name='exts' value='$mediaExts' type='hidden'>";
                    print '<input type="submit" value="Continue" class="h3button"/>';
                }
            }
            exit;
        }

        // ---- visit #3 - PROCESS THE HARVESTING -----------------------------------------------------------------

        if(array_key_exists('mode', $_REQUEST) && $_REQUEST['mode']=='2'){
            $mediaFolders = filter_var($_REQUEST['media'], FILTER_SANITIZE_STRING);
            print "<p>Now harvesting FieldHelper metadata into <b> ". HEURIST_DBNAME. "</b><br> ";

            $dirs = explode(';', $mediaFolders);// get an array of folders

            $mediaExts = filter_var($_REQUEST['exts'], FILTER_SANITIZE_STRING);
            $mediaExts = explode(',', $mediaExts);

            $rep_counter = 0;
            $rep_issues = "";
            $progress_divid = 0;

            set_time_limit(0);//no limit

            doHarvestFieldHelper($dirs);

            if(!empty($failed_exts)) {

                $invalid_ext = implode(', ', $failed_exts);

                print "<br><div style='color: red;'>The following file types, which were encountered during media indexing,
                are not currently being indexed as media files: $invalid_ext</div><br><br><div>You may wish to add some
                of these types in the
                <span onclick='sysIdentificationPopup();' style='text-decoration:underline;cursor:pointer;color:blue;'>Design > Properties</span>
                function and run indexing again.</div><br>";
            }

            print "<div>Synchronisation completed</div>";
            print '<div style="color:green">Total files processed:'.intval($rep_counter).DIV_E;

            if($rep_counter > 0){
                print '<script type="text/javascript">if(window.hWin.HEURIST4.dbs) { window.hWin.HEURIST4.dbs.get_record_counts(null);}</script>';
            }
        }

        // ---- HARVESTING AND OTHER FUNCTIONS -----------------------------------------------------------------

        function doHarvestFieldHelper($dirs) {

            global $rep_counter, $rep_issues, $system_folders;

            foreach ($dirs as $dir){

                if($dir=="*"){

                    $dir = HEURIST_FILESTORE_DIR;

                }else{

                    $dir = USanitize::sanitizePath($dir);
                    $real_path = isPathInHeuristUploadFolder($dir, true);
                    if($real_path==null){
                        //does not exist - ignore
                        print error_Div(htmlspecialchars($dir).' is ignored. Folder does not exist.');
                        continue;
                    }elseif(!$real_path){
                        print error_Div(htmlspecialchars($dir).' is ignored. Folder must be in Heurist filestore directory.');
                        continue;
                    }

                    /*
                    $dir = str_replace('\\','/',$dir);
                    if(!( substr($dir, 0, strlen(HEURIST_FILESTORE_DIR)) === HEURIST_FILESTORE_DIR )){
                    $orig = $dir;
                    chdir(HEURIST_FILESTORE_DIR);
                    $dir = realpath($dir);
                    if($dir!==false){
                    //realpath gives real path on remote file server
                    if(strpos($dir, '/srv/HEURIST_FILESTORE/')===0){  //heurx-fs-pro
                    $dir = str_replace('/srv/HEURIST_FILESTORE/', HEURIST_FILESTORE_ROOT, $dir);
                    }elseif(strpos($dir, '/data/HEURIST_FILESTORE/')===0){  //huma-num
                    $dir = str_replace('/data/HEURIST_FILESTORE/', HEURIST_FILESTORE_ROOT, $dir);
                    }elseif(strpos($dir, '/misc/heur-filestore/')===0){
                    $dir = str_replace('/misc/heur-filestore/', HEURIST_FILESTORE_ROOT, $dir);
                    }

                    $dir = str_replace('\\','/',$dir);
                    }

                    if(!$dir || !( substr($dir, 0, strlen(HEURIST_FILESTORE_DIR)) === HEURIST_FILESTORE_DIR )){
                    print error_Div(htmlspecialchars($orig).' is ignored. Folder must be in Heurist filestore directory');
                    continue;
                    }
                    }
                    */

                    if(substr($dir, -1) != '/'){
                        $dir .= "/";
                    }

                }

                if(in_array($dir, $system_folders)){

                    print error_Div('Files are not scanned in system folder '.htmlspecialchars($dir));

                }elseif($dir && file_exists($dir) && is_dir($dir))
                {


                    $files = scandir($dir);
                    if($files && !empty($files))
                    {
                        $subdirs = array();

                        $isfirst = true;

                        foreach ($files as $filename){

                            if(!($filename=="." || $filename=="..")){
                                if(is_dir($dir.$filename)){
                                    if($filename=='thumbnail') {continue;}
                                    array_push($subdirs, $dir.$filename."/");
                                }elseif($isfirst){ //if($filename == "fieldhelper.xml"){
                                    $isfirst = false;
                                    if($dir == HEURIST_FILESTORE_DIR){
                                        print error_Div('Files are not scanned in root upload folder '.htmlspecialchars($dir));
                                    }else{
                                        $rep_counter = $rep_counter + doHarvestInDir($dir);
                                    }
                                }
                            }
                        }

                        if(!empty($subdirs)){
                            doHarvestFieldHelper($subdirs);
                            flush();
                        }
                    }
                }elseif($dir) {
                    print error_Div('Folder was not found: '.htmlspecialchars($dir));
                }
            }
        }

        /**
        * callback from saveRecord
        *
        * @param mixed $message
        */
        function jsonError($message) {
            global $rep_issues, $currfile;
            //mysql_query("rollback");
            $rep_issues = $rep_issues."<br>Error save record for file:".$currfile.". ".$message;
        }


        function extendMediaExts($versions){
            global $mediaExts;

            if(count(array_intersect($versions,$mediaExts))>0){
                foreach ($versions as $ext){
                    if(!in_array($ext,$mediaExts)){
                        array_push($mediaExts, $ext);
                    }
                }
            }
        }


        /**
        *
        * @global type $rep_counter
        * @global string $rep_issues
        * @global array $fieldhelper_to_heurist_map
        * @param type $dir
        */
        function doHarvestInDir($dir) {

            global $system, $rep_issues, $fieldhelper_to_heurist_map, $mediaExts, $progress_divid,
            $geoDT, $fileDT, $titleDT, $startdateDT, $enddateDT, $descriptionDT, $failed_exts;

            //add extension versions
            extendMediaExts(array('jpg','jpeg','jfif','jpe'));
            extendMediaExts(array('tif','tiff'));
            extendMediaExts(array('mpg','mpeg'));
            extendMediaExts(array('doc','docx'));
            extendMediaExts(array('xls','xlsx'));

            $rep_processed = 0;
            $rep_added = 0;
            $rep_updated = 0;
            $rep_processed_dir = 0;
            $rep_ignored = 0;
            $f_items = null; //reference to items element

            $progress_divid++;

            ob_start();
            print '<div><b>'.htmlspecialchars($dir).'</b><span id="progress'.intval($progress_divid).'"></span></div>';
            ob_flush();
            flush();

            if(!is_writable($dir)){
                print "<div style=\"color:red\">Folder is not writeable. Check permissions</div>";
                return 0;
            }

            $manifest_file = $dir."fieldhelper.xml";

            //list of all files in given folder - need to treat new files that are not mentioned in manifest file
            $all_files = scandir($dir);

            if(file_exists($manifest_file)){

                //read fieldhelper.xml
                if(is_readable($manifest_file)){

                    //check write permission
                    if(!is_writable($manifest_file)){
                        print "<div style=\"color:red\">Manifest is not writeable. Check permissions.</div>";
                        return 0;
                    }
                }else{
                    print "<div style=\"color:red\">Manifest is not readable. Check permissions.</div>";
                    return 0;
                }

                $fh_data = simplexml_load_file($manifest_file);

                if($fh_data==null || is_string($fh_data)){
                    print "<div style=\"color:red\">Manifest is corrupted</div>";
                    return 0;
                }

                //MAIN LOOP in manifest
                $not_found = true;  //true if manifest is empty

                foreach ($fh_data->children() as $f_gen){
                    if($f_gen->getName()=="items"){

                        $f_items = $f_gen;
                        $not_found = false;

                        $tot_files = count($f_gen->children());
                        $cnt_files = 0;

                        foreach ($f_gen->children() as $f_item){

                            $recordIds = array();
                            $recordType  = RT_MEDIA_RECORD; //media by default
                            $recordURL   = null;
                            $recordNotes = null;
                            $lat = null;
                            $lon = null;
                            $filename = null;
                            $filename_base = null;   //filename only
                            $details = array();
                            $file_id = null;
                            $old_md5 = null;

                            foreach ($f_item->children() as $el){  //$key=>$value

                                $content = strval($el);// (string)$el;
                                $key = $el->getName();

                                $value = $content;

                                if($key == "md5"){

                                    $old_md5 = $value;

                                }elseif(@$fieldhelper_to_heurist_map[$key]){

                                    $key2 = $fieldhelper_to_heurist_map[$key];

                                    if($key2=="file"){

                                        $filename = $dir.$value;
                                        $filename_base = $value;

                                        $key3 = $fieldhelper_to_heurist_map['file_name'];
                                        if($key3>0){
                                            $details["t:".$key3] = array("1"=>$value);
                                        }
                                        $key3 = $fieldhelper_to_heurist_map['file_path'];
                                        if($key3>0){

                                            $relative_path = getRelativePath(HEURIST_FILESTORE_DIR, $dir);
                                            $details["t:".$key3] = array("1"=>$relative_path);//change to relative path

                                        }

                                    }elseif($key2=="lat"){

                                        $lat = floatval($value);

                                    }elseif($key2=="lon"){

                                        $lon = floatval($value);

                                    }elseif($key2=="recordId"){
                                        $recordIds[] = $value;
                                    }elseif(intval($key2)>0) {
                                        //add to details
                                        $details["t:".$key2] = array("1"=>$value);
                                    }// else field type not defined in this instance

                                }
                            }//for item keys


                            if($filename){
                                //exclude from the list of all files in this folder
                                if(in_array($filename_base, $all_files)){
                                    $ind = array_search($filename_base,$all_files,true);
                                    unset($all_files[$ind]);
                                }
                            }

                            $recordId = null;
                            $notFoundMessage = null;
                            if(!empty($recordIds)){ //veify that this record exists
                                foreach($recordIds as $recId){
                                    $query2 = 'SELECT rec_ID FROM Records WHERE rec_ID='.$recId;
                                    $res = mysql__select_value($system->get_mysqli(),$query2);
                                    if (!($res>0)) {
                                        $notFoundMessage = "<div>File: <i>$filename_base</i> was indexed as rec# $recId. ".
                                        "This record was not found. File will be reindexed</div>";
                                    }else{
                                        $notFoundMessage = null;
                                        $recordId = $res;
                                        break;
                                    }
                                }//for
                            }
                            if($notFoundMessage){
                                print $notFoundMessage;
                            }

                            if($recordId==null){ //import only new (reocrd that refers this media not found)

                                if($filename){

                                    if(file_exists($filename)){

                                        $currfile = $filename; //assign to global

                                        //add-update the uploaded file
                                        $file_id = fileRegister($system, $filename);//see recordFile.php
                                        if(is_numeric($file_id)){
                                            $details["t:".$fileDT] = array("1"=>$file_id);

                                            //read EXIF data for JPEG images
                                            $recordNotes = readEXIF($filename);

                                        }else{
                                            print "<div>File: <i>$filename_base</i> <span  style=\"color:red\">".
                                            "Error: Failed to register. No record created</span></div>";
                                            $file_id = null;
                                        }

                                    }else{
                                        print "<div>File: <i>$filename_base</i> <span  style=\"color:red\">".
                                        "File is referenced in fieldhelper.xml but was not found on disk.".
                                        "No record was created.</span></div>";
                                        //@todo exclude missed file from manifest
                                    }
                                }

                                if(!$file_id){
                                    continue; //add with valid file only
                                }

                                if(is_numeric($lat) && is_numeric($lon) && ($lat!=0 || $lon!=0) ){
                                    $details["t:".$geoDT] = array("1"=>"p POINT ($lon $lat)");
                                }

                                //set title by default
                                if (!array_key_exists("t:".$titleDT, $details)){
                                    $details["t:".$titleDT] = array("1"=>$filename);
                                    print "<div>File: <i>$filename_base</i> <span  style=\"color:#ff8844\">".
                                    "Warning: there was no title recorded in the XML manifest for this file.".
                                    "Using file path + file name as title.</span></div>";
                                }

                                $new_md5 = null;
                                $key = $fieldhelper_to_heurist_map['md5'];
                                if($key>0){
                                    $new_md5 = md5_file($filename);
                                    $details["t:".$key] = array("1"=>$new_md5);
                                }

                                $query2 = 'SELECT dtl_RecID FROM recDetails WHERE dtl_UploadedFileID='.$file_id;
                                $new_recordID = mysql__select_value($system->get_mysqli(),$query2);
                                if ($new_recordID>0) {
                                    //int "<div>File: <i>$filename_base</i> <span>".
                                    //ile is already referenced in system. Record ID:".$dtl_RecID."</span></div>";
                                    $rep_updated++;
                                }else{
                                    $record = array();
                                    $record['ID'] = 0; //add new
                                    $record['RecTypeID'] = $recordType;
                                    $record['AddedByImport'] = 2;
                                    $record['no_validation'] = true;
                                    $record['URL'] = $recordURL;
                                    $record['ScratchPad'] = null;
                                    $record['details'] = $details;

                                    // create missed record (from data in manifest)
                                    $out = recordSave($system, $record);//see recordModify.php

                                    if ( @$out['status'] != HEURIST_OK ) {
                                        print error_Div('File: <i>'.htmlspecialchars($filename_base).'</i> Error: '.
                                            htmlspecialchars($out["message"]));

                                    }else{
                                        $new_recordID = intval($out['data']);
                                        $rep_added++;
                                    }
                                }

                                if($new_recordID>0){

                                    if($new_md5==null){
                                        $new_md5 = md5_file($filename);
                                    }
                                    $fname = htmlspecialchars($filename_base);

                                    //update xml
                                    if($old_md5!=$new_md5){
                                        print "<div>File: <i>$fname</i> <span  style=\"color:#ff8844\">".
                                        "Warning: Checksum differs from value in manifest; ".
                                        "data file may have been changed</span></div>";
                                    }
                                    $f_item->addChild("heurist_id", $new_recordID);
                                    $f_item->addChild("md5", $new_md5);
                                    $f_item->addChild("filesize", filesize($filename));

                                    if (@$out['warning']) {
                                        print "<div>File: <i>$fname</i> <span  style=\"color:#ff8844\">Warning: ".
                                        htmlspecialchars(implode(";",$out["warning"]))."</span></div>";
                                    }
                                }
                                $rep_processed++;

                            }else{
                                $rep_ignored++;
                                //$failed_exts[] = @$flleinfo['extension'];
                            }

                            $cnt_files++;
                            if ($cnt_files % 5 == 0) {
                                ob_start();
                                print "<script type=\"text/javascript\">update_counts($progress_divid,$cnt_files,$rep_processed,$tot_files)</script>\n";
                                ob_flush();
                                flush();
                            }


                        }//for items
                    }//if has items
                }//for all children in manifest


                if($not_found){
                    print "<div style=\"color:red\">Manifest is either corrupted or empty</div>";
                }else{
                    if($rep_processed>0){
                        print "<div>$rep_processed files processed. $rep_added added. $rep_updated updated.</div>";
                    }
                    if($rep_ignored>0){
                        print "<div>$rep_ignored files already indexed.</div>";
                    }
                }


            }//manifest does not exists
            else{
                //create empty manifest XML  - TODO!!!!
                $ver = HEURIST_VERSION;
                $s_manifest = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<fieldhelper version="1">
  <info>
    <AppName>Heurist</AppName>
    <AppVersion>v $ver</AppVersion>
    <AppCopyright>(c) 2012-2023 University of Sydney</AppCopyright>
    <date></date>
  </info>
<formatOutput>1</formatOutput>
</fieldhelper>
XML;
                $fh_data = simplexml_load_string($s_manifest);
            }



            // add new empty items element
            if($f_items==null){
                $f_items = $fh_data->addChild("items");
            }

            $tot_files = count($all_files);
            $cnt_files = 0;
            $cnt_added = 0;

            //for files in folder that are not specified in the manifest file
            foreach ($all_files as $filename){
                if($filename=="." || $filename==".." || is_dir($dir.$filename)
                || $filename=="fieldhelper.xml" || $filename=="index.html"){
                    continue;
                }

                $filename_base = $filename;
                $filename = $dir.$filename;
                $currfile = $filename;
                $flleinfo = pathinfo($filename);
                $recordNotes = null;

                //checks for allowed extensions
                if(in_array(strtolower(@$flleinfo['extension']),$mediaExts))
                {

                    $details = array();

                    $file_id = fileRegister($system, $filename);//see recordFile.php

                    if($file_id>0){
                        $details["t:".$fileDT] = array("1"=>$file_id);
                        $recordNotes = readEXIF($filename);
                    }else{
                        print '<div>File: <i>'.htmlspecialchars($filename_base).'</i> <span  style="color:#ff8844">'.
                        "Warning: failed to register. No record created for this file</span></div>";
                        //$rep_issues = $rep_issues."<br>Can't register file:".$filename.". ".$file_id;
                        $file_id = null;
                        continue;
                    }

                    $details["t:".$titleDT] = array("1"=>$flleinfo['basename']);
                    /* TODO - extract these data from exif
                    $details["t:".$descriptionDT] = array("1"=>$file_id);
                    $details["t:".$startdateDT] = array("1"=>$file_id);
                    $details["t:".$enddateDT] = array("1"=>$file_id);
                    $details["t:".$geoDT] = array("1"=>$file_id);
                    */

                    $new_md5 = md5_file($filename);
                    $key = $fieldhelper_to_heurist_map['md5'];
                    if($key>0){
                        $details["t:".$key] = array("1"=>$new_md5);
                    }
                    $key = $fieldhelper_to_heurist_map['file_name'];
                    if($key>0){
                        $details["t:".$key] = array("1"=>$flleinfo['basename']);
                    }
                    $key = $fieldhelper_to_heurist_map['file_path'];
                    if($key>0){

                        $targetPath = $flleinfo['dirname'];

                        $rel_path = getRelativePath(HEURIST_FILESTORE_DIR, $targetPath);//getRelativePath2($targetPath);
                        $details["t:".$key] = array("1"=>  $rel_path);

                        /*print "<div>".HEURIST_FILESTORE_DIR.DIV_E;
                        print "<div>file path :".$targetPath.DIV_E;
                        print "<div>relative path :".strpos($targetPath, HEURIST_FILESTORE_DIR)."--".$rel_path.DIV_E;
                        print "<div>relative path old :".getRelativePath(HEURIST_FILESTORE_DIR, $targetPath)."<br><br></div>";*/


                    }
                    $key = $fieldhelper_to_heurist_map['extension'];
                    if($key>0){
                        $details["t:".$key] = array("1"=>$flleinfo['extension']);
                    }
                    $key = $fieldhelper_to_heurist_map['filesize'];
                    if($key>0){
                        $details["t:".$key] = array("1"=>filesize($filename));
                    }



                    $f_item = $f_items->addChild("item");
                    $f_item->addChild("filename", htmlspecialchars($flleinfo['basename']));
                    $f_item->addChild("nativePath", htmlspecialchars($filename));
                    $f_item->addChild("folder", htmlspecialchars($flleinfo['dirname']));
                    $f_item->addChild("extension", $flleinfo['extension']);
                    //$f_item->addChild("DateTime", );
                    //$f_item->addChild("DateTimeOriginal", );
                    $f_item->addChild("filedate", date("Y/m/d H:i:s.", filemtime($filename)));
                    $f_item->addChild("typeContent", "image");
                    $f_item->addChild("device", "image");
                    $f_item->addChild("duration", "2000");
                    $f_item->addChild("original_metadata", "chk");
                    $f_item->addChild("Name0", htmlspecialchars($flleinfo['basename']));
                    $f_item->addChild("md5", $new_md5);
                    $f_item->addChild("filesize", filesize($filename));

                    $query2 = 'SELECT dtl_RecID FROM recDetails WHERE dtl_UploadedFileID='.$file_id;
                    $new_recordID = mysql__select_value($system->get_mysqli(),$query2);
                    if ($new_recordID>0) {
                        //print "<div>File: <i>$filename_base</i> <span>".
                        //"File is already referenced in system. Record ID:".$dtl_RecID."</span></div>";
                        $rep_updated++;

                    }else{
                        //add-update Heurist record
                        $record = array();
                        $record['ID'] = 0; //add new
                        $record['RecTypeID'] = RT_MEDIA_RECORD;
                        $record['AddedByImport'] = 2;
                        $record['no_validation'] = true;
                        $record['URL'] = null;
                        $record['ScratchPad'] = $recordNotes;
                        $record['details'] = $details;
                        $out = recordSave($system, $record);//see recordModify.php

                        if ( @$out['status'] != HEURIST_OK ) {
                            print error_Div('File: <i>'.htmlspecialchars($filename_base).' Error: '.htmlspecialchars($out["message"]));
                        }else{
                            $new_recordID = intval($out['data']);
                            $cnt_added++;
                        }
                    }

                    if($new_recordID>0){
                        $f_item->addChild("heurist_id", $new_recordID);
                    }

                    $rep_processed_dir++;
                }//check ext

                $cnt_files++;

                if ($cnt_files % 5 == 0) {
                    ob_start();
                    print "<script type=\"text/javascript\">update_counts($progress_divid,$cnt_files,$cnt_added,$tot_files)</script>\n";
                    ob_flush();
                    flush();
                }
            }//for files in folder that are not specified in the directory


            ob_start();
            if($rep_processed_dir>0){
                print "<div style=\"color:green\">$rep_processed_dir processed. $cnt_added records created (new entries added to manifests)</div>";
            }
            print "<script type=\"text/javascript\">update_counts($progress_divid,$cnt_files,$cnt_added,0)</script>\n";
            ob_flush();
            flush();

            if($rep_processed+$rep_processed_dir>0){
                //save modified xml (with updated heurist_id tags)
                $fh_data->formatOutput = true;
                $fh_data->saveXML($manifest_file);
            }

            return $rep_processed+$rep_processed_dir;
        }


        /**
        * Read EXIF from JPEG files
        *
        * @param mixed $filename
        */
        function readEXIF($filename){

            if(function_exists('exif_read_data') && file_exists($filename)){

                $flleinfo = pathinfo($filename);
                $ext = strtolower($flleinfo['extension']);

                if( $ext=="jpeg" || $ext=="jfif" || $ext=="jpg" || $ext=="tif" || $ext=="tiff" )
                {

                    $exif = exif_read_data($filename, 'IFD0');

                    if($exif===false){
                        return null;
                    }

                    $exif = exif_read_data($filename, 0, true);
                    return json_encode($exif);
                    /*
                    foreach ($exif as $key => $section) {
                    foreach  ($section as $name => $val) {
                    echo "$key.$name: $val<br>\n";
                    }
                    }*/
                }

            }else{
                return null;
            }

        }
        ?>
    </body>
</html>