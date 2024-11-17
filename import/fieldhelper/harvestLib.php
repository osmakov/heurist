<?php
use hserv\utilities\USanitize;

global $rep_counter, $rep_issues, $reg_info;

$rep_counter = null;
$rep_issues = null;
$reg_info = array('reg'=>array(),'nonreg'=>array());


//
// return folders and extents to index
//
function getMediaFolders($mysqli) {

    // Find out which folders to parse for XML manifests - specified for FieldHelper indexing in Advanced Properties
    $query1 = "SELECT sys_MediaFolders, sys_MediaExtensions from sysIdentification where 1";
    $row1 = mysql__select_row($mysqli, $query1);
    if (!$row1) {
        return array('error'=>'Sorry, unable to read the sysIdentification from the current database '.HEURIST_DBNAME
            .'. Possibly wrong database format.'.CONTACT_HEURIST_TEAM_PLEASE);
    }

    // Get the set of directories defined in Advanced Properties as FieldHelper indexing directories
    $mediaFolders = $row1[0];

    if($mediaFolders==null || $mediaFolders == ''){
        $mediaFolders = HEURIST_FILESTORE_DIR.'uploaded_files/';
        folderCreate( $mediaFolders, true );
    }
    $dirs = explode(';', $mediaFolders);// get an array of folders

    //sanitize folder names
    $dirs = array_map(array('hserv\utilities\USanitize', 'sanitizePath'), $dirs);

    // The defined list of file extensions for FieldHelper indexing.
    if($row1[1]==null){
        $mediaExts = HEURIST_ALLOWED_EXT;
    }else{
        $mediaExts = $row1[1];// user gets to define from scratch so they can restrict what's indexed
    }

    $mediaExts = explode(',', $mediaExts);

    if (empty($dirs)) {
        //It seems that there are no media folders specified for this database
        $dirs = array(HEURIST_FILESTORE_DIR);// default to the data folder for this database
    }

    return array('dirs'=>$dirs, 'exts'=>$mediaExts);

}

//
// $imode - 0 - registration
//          1 - get registered and nonreg files
// folders "thumbnail" will be skipped
//
function doHarvest($system, $dirs_and_exts, $is_report, $imode, $allowed_system_folders=false) {

    global $rep_counter, $rep_issues, $reg_info;

    $reg_info = array('reg'=>array(),'nonreg'=>array());

    $system_folders = $system->getSystemFolders();

    if(@$dirs_and_exts['error']){
        print "<div style=\"color:red\">".$dirs_and_exts['error'].DIV_E;
        return;
    }

    $dirs = $dirs_and_exts['dirs'];
    $mediaExts = $dirs_and_exts['exts'];

    foreach ($dirs as $dir){

        if($dir=="*"){

            $dir = HEURIST_FILESTORE_DIR;

        }else{

            $dir = USanitize::sanitizePath($dir);

            $real_path = isPathInHeuristUploadFolder($dir, true);

            if(!$real_path){
                if($is_report){
                    print errorDiv(htmlspecialchars($dir).' is ignored. Folder '
                    (($real_path==null)?'does not exist':'must be in Heurist filestore directory'));
                }
                continue;
            }

            if(substr($dir, -1) != '/'){
                $dir .= "/";
            }

        }

        $is_allowed = is_array($allowed_system_folders) && !empty($allowed_system_folders) && in_array($dir, $allowed_system_folders);

        if(!$is_allowed && in_array($dir, $system_folders)){

            if($is_report){
                print "<div style=\"color:red\">Files are not scanned in system folder $dir</div>";
            }

        }elseif($dir && file_exists($dir) && is_dir($dir))
        {

            $files = scandir($dir);
            if(!isEmptyArray($files))
            {
                $subdirs = array();

                $isfirst = true;

                foreach ($files as $filename){

                    if(!($filename=="." || $filename=="..")){
                        if(is_dir($dir.$filename)){
                            $subdir = $dir.$filename."/";
                            if($filename!='thumbnail' && !in_array($subdir, $system_folders)){
                                    array_push($subdirs, $subdir);
                            }
                        }elseif($isfirst){ //if($filename == "fieldhelper.xml"){
                            $isfirst = false;
                            if($dir == HEURIST_FILESTORE_DIR){
                                if($is_report){
                                    print "<div style=\"color:red\">Files are not scanned in root upload folder $dir</div>";
                                }
                            }else{
                                getFilesInDir($system, $dir, $mediaExts, $imode);
                            }
                        }
                    }
                }

                if(!empty($subdirs)){

                    doHarvest($system, array("dirs"=>$subdirs, "exts"=>$mediaExts), $is_report, $imode);
                    if($is_report) {flush();}
                }
            }
        }elseif($dir) {
            if($is_report){
                print "<div style=\"color:red\">Folder was not found: $dir</div>";
            }
        }
    }
} //doHarvest

//
// @todo - move code here from syncWithFieldHelper
/*
function doHarvestInDir($dir) {

}
*/

function getRegInfoResult(){
    global $reg_info;
    return $reg_info;
}

//
// return arrays registered and non-registered files
// $imode
// 0 - all
// 1 - reg and unreg separately
//
function getFilesInDir($system, $dir, $mediaExts, $imode) {

    global $reg_info;

    $all_files = scandir($dir);

    foreach ($all_files as $filename){

        if(is_dir($dir.$filename) || $filename=="." || $filename==".."
            || $filename=="fieldhelper.xml" || $filename=="index.html" || $filename==".htaccess"){
            continue;
        }

        $filename_base = $filename;
        $filename = $dir.$filename;
        $flleinfo = pathinfo($filename);

        //checks for allowed extensions
        if(in_array(strtolower(@$flleinfo['extension']),$mediaExts)){

            if($imode==1){

                $file_id = fileGetByFileName( $system, $filename );//see recordFile.php

                if($file_id <= 0 && strpos($filename, "/thumbnail/$filename_base") !== false){
                    //Check if this is just a thumbnail version of an image

                    $temp_name = str_replace("thumbnail/$filename_base", $filename_base, $filename);

                    if(in_array($temp_name, $reg_info['nonreg'])){
                        continue;
                    }
                }

                if($file_id>0){
                    array_push($reg_info['reg'], $filename);
                }else{
                    array_push($reg_info['nonreg'], $filename);
                }

            }else{
                array_push($reg_info, $filename);
            }
        }
    }  //for all_files
}
?>
