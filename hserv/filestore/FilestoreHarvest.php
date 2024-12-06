<?php
namespace hserv\filestore;
use hserv\utilities\USanitize;

/**
*  Searches registered and not registered user's files in database folder
*  (in specified folder or set of subfolders)
*/
class FilestoreHarvest
{
    private $system;
    
    private $rep_issues;
    private $reg_info;

    //
    // constructor
    //
    public function __construct( $system ) {
        
        $this->system = $system;
        
        $this->rep_counter = null;
        $this->rep_issues = null;
        $this->reg_info = array('reg'=>array(),'nonreg'=>array());
    }

    //
    // return folders and extents to index
    //
    public function getMediaFolders() {
        
        $mediaFolders = $this->system->settings->get('sys_MediaFolders');
        $mediaExts = $this->system->settings->get('sys_MediaExtensions'); //user define list - what is allowed to index

        if($mediaFolders==null || $mediaFolders == ''){ // by default
            $mediaFolders = $this->system->getSysDir('uploaded_files');
            folderCreate( $mediaFolders, true );
        }
       
        $mediaFolders = explode(';', $mediaFolders);// get an array of folders

        //always include file_uploads
        if(!in_array('file_uploads', $mediaFolders)){
                $mediaFolders[] = 'file_uploads';
        }
        
        //sanitize folder names
        $mediaFolders = array_map(array('hserv\utilities\USanitize', 'sanitizePath'), $mediaFolders);

        // The defined list of file extensions for FieldHelper indexing.
        if($mediaExts==null || $mediaExts==''){
            $mediaExts = HEURIST_ALLOWED_EXT;
        }

        $mediaExts = explode(',', $mediaExts);

        if (empty($mediaFolders)) {
            //It seems that there are no media folders specified for this database
            $dirs = array($this->system->getSysDir('file_uploads'));// default to the data folder for this database
        }

        return array('dirs'=>$mediaFolders, 'exts'=>$mediaExts);
    }
    

    public function getRegInfoResult(){
        return $this->reg_info;
    }
    
    
    //
    // fills reg_info array with registered and non-registered files
    // $imode
    // 0 - all
    // 1 - reg and unreg separately
    //
    private function getFilesInDir($dir, $mediaExts, $imode) {

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

                    //find file in dbRecUploadedFiles by name    
                    $file_id = fileGetByFileName( $this->system, $filename );//see recordFile.php

                    if($file_id <= 0 && strpos($filename, "/thumbnail/$filename_base") !== false){
                        //Check if this is just a thumbnail version of an image

                        $temp_name = str_replace("thumbnail/$filename_base", $filename_base, $filename);

                        if(in_array($temp_name, $this->reg_info['nonreg'])){
                            continue;
                        }
                    }

                    if($file_id>0){
                        array_push($this->reg_info['reg'], $filename);
                    }else{
                        array_push($this->reg_info['nonreg'], $filename);
                    }

                }else{
                    array_push($this->reg_info, $filename);
                }
            }
        }  //for all_files
    }    

    //
    // $imode - 0 - registration
    //          1 - get registered and nonreg files
    // folders "thumbnail" will be skipped
    //
    /**
    * collests user's files in database folder 
    * 
    * @param mixed $dirs_and_exts - set of subfolders and extensions
    * @param mixed $is_report - 
    * @param mixed $imode
    * @param mixed $allowed_system_folders
    */
    public function doHarvest($dirs_and_exts, $is_report, $imode, $allowed_system_folders=null) {

        $this->reg_info = array('reg'=>array(),'nonreg'=>array());
        
        if($allowed_system_folders==null){
            $allowed_system_folders = ['file_uploads'];
        }

        $db_folder = $this->system->getSysDir();
        //get exclusion list of system subfolders - where user's files don't exist
        $system_folders = $this->system->getSystemFolders();

        $dirs = $dirs_and_exts['dirs'];
        $mediaExts = $dirs_and_exts['exts'];

        foreach ($dirs as $dir){

            if($dir=="*"){

                $dir = $db_folder;

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
                                if($dir == $db_folder){
                                    if($is_report){
                                        print "<div style=\"color:red\">Files are not scanned in root upload folder $dir</div>";
                                    }
                                }else{
                                    $this->getFilesInDir($dir, $mediaExts, $imode);
                                }
                            }
                        }
                    }

                    if(!empty($subdirs)){

                        $this->doHarvest(array("dirs"=>$subdirs, "exts"=>$mediaExts), $is_report, $imode);
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


    
}
