<?php
use hserv\utilities\USanitize;
use hserv\utilities\USystem;

    /**
    * File library - convert to static class
    * File/folder utilities
    *
    * folderExists
    * folderCreate
    * folderDelete
    * folderDelete2   using RecursiveIteratorIterator
    *
    * folderCreate2  - create folder, check write permissions, add index.html, write htaccess
    * folderAddIndexHTML
    * allowWebAccessForForlder
    *
    * folderContent  - get list of files in folder as search result (record list)
    * folderSize2
    * folderSize
    * folderTree
    * folderTreeToFancyTree - NOT USED
    * folderFirstFile - returns first file from first folder (search first file given ext or just first file in subfolders
    *                   used for tiled image stack
    * folderGetSubFolders - Returns array of subfolders
    *
    * fileCopy
    * fileSave
    * fileOpen - check existance, readability, opens and returns file handle, or -1 not exist, -2 not readable -3 can't open
    * fileDelete
    *
    * getRelativePath
    * folderRecurseCopy
    * folderSubs - list of subfolders
    *
    *
    * fileReadByChunks - Reads a file in chunks and outputs it to the client in a memory-efficient manner.
    * getFileSize - Retrieves the size of a file, with an optional cache-clearing mechanism.
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

    /*

    isPathInHeuristUploadFolder - checks that path is HEURIST_FILESTORE_DIR

    ----

    @todo move from record_shp? fileRetrievePath returns fullpath to file is storage, or to tempfile,
                   if it requires it extracts zip archive to tempfile or download remote url to tempfile

    ===
    move these function to separate uCurl

    saveURLasFile  loadRemoteURLContent + fileSave
    getTitleFromURL

    loadRemoteURLContentSpecial tries to avoid curl if url on the same domain

    loadRemoteURLContent
    loadRemoteURLContentWithRange  load data with curl
    loadRemoteURLContentType

    getScriptOutput

    =====

    autoDetectSeparators

    */

    $glb_curl_code = null;
    $glb_curl_error = null;



    //--------------------------------------------------------------------------
    // 1 - OK
    // -1  not exists
    // -2  not writable
    // -3  file with the same name cannot be deleted
    function folderExists($folder, $testWrite){

        if(file_exists($folder)){

            if(is_dir($folder)){

                if ($testWrite && !is_writable($folder)) {
                    //echo ("<h3>Warning:</h3> Folder $folder already exists and it is not writeable. Check permissions! ($msg)<br>");
                    return -2;
                }
            }else{
                if(!unlink($folder)){
                    //echo ("<h3>Warning:</h3> Unable to remove file $folder. We need to create a folder with this name ($msg)<br>");
                    return -3;
                }
                return -1;
            }

            return 1;

        }else{
            return -1;
        }

    }

    function folderExistsVerbose($folder, $testWrite, $folderName){

        $res = folderExists($folder, $testWrite);
        if($res<0){
            $s='';
            if($res==-1){
                $s = 'Cant find folder "'.$folderName.'" in database directory';
            }elseif($res==-2){
                $s = 'Folder "'.$folderName.'" in database directory is not writeable';
            }elseif($res==-3){
                $s = 'Cant create folder "'.$folderName.'" in database directory. It is not possible to delete file with the same name';
            }

            return $s;
        }

        return true;
    }


    /**
    *
    *
    * @param mixed $folder
    * @param mixed $testWrite
    * @return mixed
    */
    function folderCreate($folder, $testWrite){

        // -1  not exists
        // -2  not writable
        // -3  file with the same name cannot be deleted
        $res = folderExists($folder, $testWrite);

        if($res == -1){
            if (!mkdir($folder, 0777, true)) {
                //echo ("<h3>Warning:</h3> Unable to create folder $folder ($msg)<br>");
                return false;
            }
        }

        return true;
    }


    /**
    * create folder, check write permissions, add index.html, write htaccess
    *
    * @param mixed $folder
    * @param mixed $message
    * @param mixed $allowWebAccess
    */
    function folderCreate2($folder, $message, $allowWebAccess=false){

        $swarn = '';

        $check = folderExists($folder, true);

        if($check==-2){
            $swarn = 'Cannot access folder (it, or a subdirectory, is not writeable) '. $folder .'  '.$message.'<br>';

        }elseif($check==-1){
            if (!mkdir($folder, 0777, true)) {
                $swarn = 'Unable to create folder '. $folder .'  '.$message.'<br>';
            }else{
                $check=1;
            }
        }

        if ($check>0){

            folderAddIndexHTML( $folder );

            if($allowWebAccess){
                //copy htaccess
                $res = allowWebAccessForForlder( $folder );
                if(!$res){
                    $swarn = "Cannot copy htaccess file for folder $folder<br>";
                }
            }
        }
        return $swarn;
    }

    /**
    * add index.html to folder
    *
    * @param mixed $directory
    */
    function folderAddIndexHTML($folder) {

        $filename = $folder."/index.html";
        if(!file_exists($filename)){
            $file = fopen($filename,'x');
            if ($file) { // returns false if file exists - don't overwrite
                fwrite($file,"Sorry, this folder cannot be browsed");
                fclose($file);
            }
        }
    }


    //
    // It copies .htaccess_via_url that allow access (not index/listing view) to destination folder
    //
    function allowWebAccessForForlder($folder){
        $res = true;
        $folder = USanitize::sanitizePath($folder);
        if(file_exists($folder) && is_dir($folder) && !file_exists($folder.'.htaccess')){
            $res = copy(HEURIST_DIR.'admin/setup/.htaccess_via_url', $folder.'.htaccess');
        }
        return $res;
    }

    /**
    * clean folder and itself
    *
    * @param mixed $dir
    */
    function folderDelete($dir, $rmdir=true, $verbos = false) {

        $msgs = [];

        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != '.' && $object != '..') {
                    if (filetype($dir.'/'.$object) == 'dir') {

                        $rtn = folderDelete($dir.'/'.$object, true, $verbos);//delete files

                        $msgs[] = "Deleting sub directory $object";
                        if($verbos && !empty($rtn)){ // merge messages
                            $msgs = array_merge($msgs, $rtn);
                        }
                    } else {
                        $msgs[] = "Deleted file $object, size: " . filesize("$dir/$object");
                        unlink($dir.'/'.$object);
                    }
                }
            }
            reset($objects);
            if($rmdir){
                rmdir($dir);//delete folder itself
            }
        }

        return $verbos ? $msgs : null;
    }

    //
    // remove folder and all its content
    //
    function folderDelete2($dir, $rmdir) {

        if(file_exists($dir)){

            $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                $todo($fileinfo->getRealPath());
            }

            if($rmdir){
                $res = rmdir($dir);
                return $res;
            }
        }
        return true;
    }

    //
    // get list of files in folder as search result (record list)
    // It is used to get 1) all cfg files for entity configuration
    //                   2) browse for available icons in iconLibrary
    // $include_dates  search for old archives in form hdb_ztucs_passages.sql.bz2.2022-08-17
    //
    // @todo , $is_reqursive=false
    //
    function folderContent($dirs, $exts=null, $include_dates=false) {

        $records = array();
        $order = array();
        $fields = array('file_id', 'file_name', 'file_dir', 'file_url', 'file_size');
        $idx = 1;
        if(!is_array($dirs)) {$dirs = array($dirs);}
        if($exts!=null && !is_array($exts)) {$exts = array($exts);}

        foreach ($dirs as $dir) {

            $dir = USanitize::sanitizePath($dir);
            if( substr($dir, -1, 1) != '/' )  {
                $dir .= '/';
            }


            if (!defined('HEURIST_FILESTORE_ROOT') || strpos($dir, HEURIST_FILESTORE_ROOT)!==false) {
                //in database filestore
                $folder = $dir;
                $url = null;
            }elseif(strpos($dir, '/srv/BACKUP')===0){  //in /srv/BACKUP
                $folder = $dir;
                $url = null;
            }else{
                //relative to heurist folder
                $folder =  HEURIST_DIR.$dir;
                $url = HEURIST_BASE_URL.$dir;
            }

            if (!(file_exists($folder) && is_dir($folder))) {continue;}


            $files = scandir($folder);
            foreach ($files as $filename) {

                    $path_parts = pathinfo($filename);
                    if(array_key_exists('extension', $path_parts))
                    {
                        $ext = strtolower($path_parts['extension']);
                        if($include_dates && (strlen($ext)==10) && (DateTime::createFromFormat('Y-m-d', $ext) !== false)){
                            $fname = substr($filename, 0, -11);
                            $path_parts = pathinfo($fname);
                            if(array_key_exists('extension', $path_parts)){
                                $ext = strtolower($path_parts['extension']);
                            }
                        }

                        if(file_exists($folder.$filename) && ($exts==null || in_array($ext, $exts)))
                        {
                            $fsize = (is_file($folder.$filename))?filesize($folder.$filename):0;

                            $records[$idx] = array($idx, $filename, $folder, $url, $fsize);
                            $order[] = $idx;
                            $idx++;
                        }
                    }

            }//for
        }


        $response = array(
                            'pageno'=>0,  //page number to sync
                            'offset'=>0,
                            'count'=>count($records),
                            'reccount'=>count($records),
                            'fields'=>$fields,
                            'records'=>$records,
                            'order'=>$order,
                            'entityName'=>'files');

        return $response;

    }

    //
    // Returns summary size of all files in folder in bytes
    //
    function folderSize2($dir){

        $size = 0;

        $dir = realpath($dir);

        if($dir!==false && file_exists($dir) && is_dir($dir)===true){

            $arr = glob(rtrim($dir, '/').'/*', GLOB_NOSORT);
            foreach ($arr as $each) {
                $size += is_file($each) ? filesize($each) : folderSize($each);
            }

        }

        return $size;
    }


    function folderSize($dir)
    {
        $dir = rtrim(str_replace('\\', '/', $dir), '/');

        if (file_exists($dir) && (is_dir($dir) === true)) {

            $totalSize = 0;

            $dir = USanitize::sanitizePath($dir);

            $dir = realpath($dir);

            if($dir!==false){

                $os        = strtoupper(substr(PHP_OS, 0, 3));
                // If on a Unix Host (Linux, Mac OS)
                if ($os !== 'WIN') {
                    $cmd = escapeshellcmd('/usr/bin/du -sb ' . $dir);
                    $io = popen($cmd, 'r');
                    if ($io !==false) {
                        $totalSize = intval(fgets($io, 80));
                        pclose($io);
                        return $totalSize;
                    }
                }
                // If on a Windows Host (WIN32, WINNT, Windows)
                if ($os === 'WIN' && extension_loaded('com_dotnet')) {
                    $obj = new \COM('scripting.filesystemobject');
                    if (is_object($obj)) {
                        $ref       = $obj->getfolder($dir);
                        $totalSize = $ref->size;
                        $obj       = null;
                        return $totalSize;
                    }
                }
                // If System calls did't work, use slower PHP
                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
                foreach ($files as $file) {

                    if(!$file->isDir()){
                        $totalSize += $file->getSize();
                    }
                }
            }
            return $totalSize;
        } elseif (is_file($dir) === true) {
            return filesize($dir);
        }
    }

    //
    //
    //
    function folderFirstFile($dir, $ext=null, $recursion=true){

        $dir = realpath($dir);

        if($dir!==false){

            $dirs = scandir($dir);
            foreach ($dirs as $node) {
                if (($node == '.' ) || ($node == '..' )) {
                    continue;
                }
                $file = $dir.'/'.$node;
                if(is_dir($file)){
                    if($recursion){
                        return folderFirstFile($file, $ext, $recursion);
                    }
                }else{
                    if($ext!=null)
                    {
                        $path_parts = pathinfo($file);
                        if(array_key_exists('extension', $path_parts))
                        {
                            $fext = strtolower($path_parts['extension']);
                            if($ext==$fext){
                                return $file;
                            }
                        }
                    }else{
                        return $file;
                    }
                }
            }
        }

        return null;
    }

    //
    // Returns array of subfolders
    //
    function folderGetSubFolders($dir){
        $dir = realpath($dir);

        $res = array();

        if($dir!==false){

            $dirs = scandir($dir);
            foreach ($dirs as $node) {
                if (($node == '.' ) || ($node == '..' )) {
                    continue;
                }
                $file = $dir.'/'.$node;
                if(is_dir($file)){
                    $res[] = $node;
                }
            }
        }

        return $res;
    }

    /**
     * Creates a tree-structured array of directories and files from a given root folder.
     *
     * @param string $dir
     * @param string $params
     *                  withFiles: false
     *                  refex: file filter
     *                  ignoreEmtpty: not include emoty folder
     *                  systemFolders:
     *                  format: fancy (for fancyTree)
     * @param boolean $ignoreEmpty Do not add empty directories to the tree
     * @return array
     */
    function folderTree($dir, $params)
    {
        if($dir==null){
            $dir = HEURIST_FILESTORE_DIR;
        }

        if (!$dir instanceof DirectoryIterator) {
            $dir = new DirectoryIterator((string)$dir);
        }
        $dirs  = array();
        $files = array();
        $file_count = 0;

        $withFiles = (@$params['withFiles']==true);
        $regex = @$params['regex'];
        $ignoreEmpty = (@$params['ignoreEmtpty']==true);
        $systemFolders = @$params['systemFolders'];
        $isFancy = (@$params['format']=='fancy');
        if(is_array($params)) {$params['systemFolders'] = null;} //use on first level only
        if($regex==null) {$regex = '';}

        $fancytree = array();

        foreach ($dir as $node) {
            if ($node->isDir() && !$node->isDot()) {

                $folder_name = $node->getFilename();
                $is_system = (@$systemFolders[$folder_name]!=null);
                //(@$params['is_system']==true) ||
                //$params['is_system'] = $is_system;

                $tree = folderTree($node->getPathname(), $params);
                if (!$ignoreEmpty || count($tree)) {

                    if($isFancy){
                        $arr = array( 'key'=>$folder_name, 'title'=>$folder_name,
                            'folder'=>true, 'issystem'=>$is_system,
                            'children'=>$tree['children'], 'files_count'=>$tree['count'] );

                       if($is_system){
                           $arr['unselectable'] = true;
                           $arr['unselectableStatus'] = false;
                       }

                       $fancytree[] = $arr;

                    }else{
                        $dirs[$folder_name] = $tree;
                    }
                }

            } elseif($node->isFile()) {
                if($withFiles){
                    $name = $node->getFilename();
                    if ('' == $regex || preg_match($regex, $name)) { //file filter
                        $files[] = $name;
                        $fancytree[] = array( 'key'=>$name, 'title'=>$name);
                    }
                }
                $file_count++;
            }
        }

        if($isFancy){
            usort($fancytree, "__cmpTitleInTree");
            return array('children'=>$fancytree, 'count'=>$file_count);
        }else{
            asort($dirs);
            sort($files);
            return array_merge($dirs, $files);
        }
    }


    function __cmpTitleInTree($a, $b)
    {
        if ($a['title'] == $b['title']) {
            return 0;
        }
        $ret = ( strtolower($a['title']) < strtolower($b['title'])) ? -1 : 1;
        return $ret;
    }

    //
    //  NOT USED
    //
    function folderTreeToFancyTree($data, $lvl=0, $sysfolders=null){
        //for fancytree
        $fancytree = array();
        foreach($data as $folder => $children){

            $item = array( 'key'=>$folder, 'title'=>$folder,
                        'folder'=>($folder>=0), 'issystem'=>(@$sysfolders[$folder]!=null) );

            if(!isEmptyArray($children)){
                $item['children'] = folderTreeToFancyTree($children, $lvl+1);
            }
            $fancytree[] = $item;
        }
        usort($fancytree, "__cmpTitleInTree");
        return $fancytree;

    }



    //
    // check file existance, readability and opens the file
    // returns file handle, or -1 not exist, -2 not readable -3 can't open
    //
    function fileOpen($file)
    {
        if (!(file_exists($file) && is_file($file))) {
            return -1;
        }
        if (!is_readable($file)) {
            return -2;
        }
        $handle = fopen($file, 'rb');
        if (!$handle) {
            return -3;
        }
        return $handle;
    }

    //
    //
    //
    function fileCopy($s1, $s2) {
        $path = pathinfo($s2);

        if(folderCreate($path['dirname'], true)){
            if (!copy($s1,$s2)) {
                // "copy failed";
                return false;
            }
        }else{
           //can't create folder or it is not writeable
           return false;
        }
        return true;
    }

    function fileDelete( $filename ){
        if(!empty($filename) && file_exists($filename)){
            unlink($filename);
        }
    }

    // Usage:
    // 1) save version in System.php
    // 2) save remote content in temporary file in scratch folder
    //
    function fileSave($rawdata, $filename)
    {
        if(!empty($rawdata) && is_string($filename)){
            fileDelete($filename);
            $fp = fopen($filename,'x');
            fwrite($fp, $rawdata);
            fclose($fp);

            return filesize($filename);
        }else{
            return 0;
        }
    }
    //
    // Writes $rawdata to the end of $filename
    //
    function fileAdd($rawdata, $filename)
    {
        if($rawdata){
            try{
                $fp = fopen($filename,'a');//open for add
                if($fp===false){
                    // 'Cannot open file '.$filename
                }else{
                    fwrite($fp, $rawdata);
                    fclose($fp);
                }

            }catch(Exception  $e){
                // Cannot open file '.$filename.'  Error:'.$e->getMessage()
            }

            return filesize($filename);
        }else{
            return 0;
        }
    }

    //
    // Adds counter to the end of file name
    //
    function getUniqueFileName($folder, $filename, $ext){

        $path_parts = pathinfo($filename);
        $filename = $path_parts['filename'];
        if(strpos($ext,'.')==false){
            $ext = '.'.$ext;
        }

        $file_fullpath = $folder.$filename.$ext;

        $k = strpos($filename,'(');
        $k2 = strpos($filename,')');
        if($k>0 && $k2>0){
            $cnt = intval(substr($filename,$k+1,$k2-$k));
            $filename = substr($filename,0,$k);
        }else{
            $cnt = 0;
        }

        do{
            if(file_exists($file_fullpath)){
                $cnt++;
                $file_fullpath = $folder.$filename."($cnt)$ext";
            }
        }while (file_exists($file_fullpath));

        return $file_fullpath;
    }



    /**
     * Returns the target path as relative reference from the base path.
     *
     * Only the URIs path component (no schema, host etc.) is relevant and must be given, starting with a slash.
     * Both paths must be absolute and not contain relative parts.
     *
     * @param string $basePath   The base path
     * @param string $targetPath The target path
     *
     * @return string The relative target path
     */
     /*  does not work
    function getRelativePath($basePath, $targetPath)
    {
        // Normalize path separators for cross-platform compatibility
        $basePath = str_replace('\\', '/', rtrim($basePath, '/'));
        $targetPath = str_replace('\\', '/', rtrim($targetPath, '/'));

        // If both paths are identical, return an empty string (they are the same)
        if ($basePath === $targetPath) {
            return '';
        }

        // Split both paths into their individual components
        $baseDirs = explode('/', ltrim($basePath, '/'));
        $targetDirs = explode('/', ltrim($targetPath, '/'));

        // Remove identical segments from the start of both paths
        while (isset($baseDirs[0], $targetDirs[0]) && $baseDirs[0] === $targetDirs[0]) {
            array_shift($baseDirs);
            array_shift($targetDirs);
        }

        // Build the relative path by going up for each remaining base directory
        $relativePath = str_repeat('../', count($baseDirs)) . implode('/', $targetDirs);

        // If the relative path is empty (pointing to the same directory), return './'
        if ($relativePath === '') {
            return './';
        }

        // Special case: ensure the result does not start with a schema-like structure (e.g., "file:colon")
        if (strpos($relativePath, ':') !== false && strpos($relativePath, '/') === false) {
            return './' . $relativePath;
        }

        return $relativePath;
    }
    */

    /**
     * Returns the target path as relative reference from the base path.
     *
     * Only the URIs path component (no schema, host etc.) is relevant and must be given, starting with a slash.
     * Both paths must be absolute and not contain relative parts.
     * Relative URLs from one resource to another are useful when generating self-contained downloadable document archives.
     * Furthermore, they can be used to reduce the link size in documents.
     *
     * Example target paths, given a base path of "/a/b/c/d":
     * - "/a/b/c/d"     -> ""
     * - "/a/b/c/"      -> "./"
     * - "/a/b/"        -> "../"
     * - "/a/b/c/other" -> "other"
     * - "/a/x/y"       -> "../../x/y"
     *
     * @param string $basePath   The base path
     * @param string $targetPath The target path
     *
     * @return string The relative target path
     */
    function getRelativePath($basePath, $targetPath)
    {

        $targetPath = str_replace("\0", '', $targetPath);
        $targetPath = str_replace('\\', '/', $targetPath);

        //add last one
        if( substr($targetPath, -1, 1) != '/' )  {$targetPath = $targetPath.'/';}

        if ($basePath === $targetPath){
            return '';
        }elseif (substr($targetPath,0,1)!='/' && !preg_match('/^[A-Z]:/i',$targetPath)) { //it is already relative
            return $targetPath;
        }

        $baseDirs = explode('/', ltrim($basePath,'/'));
        $targetDirs = explode('/', ltrim($targetPath,'/'));
        array_pop($baseDirs);
        $targetFile = array_pop($targetDirs);

        // Remove identical segments from the start of both paths
        while (isset($baseDirs[0], $targetDirs[0]) && $baseDirs[0] === $targetDirs[0]) {
            array_shift($baseDirs);
            array_shift($targetDirs);
        }/*
        foreach ($baseDirs as $i => $dir) {
            if (isset($targetDirs[$i]) && $dir === $targetDirs[$i]) {
                unset($baseDirs[$i], $targetDirs[$i]);
            } else {
                break;
            }
        }*/

        $targetDirs[] = $targetFile;
        $path = str_repeat('../', count($baseDirs)).implode('/', $targetDirs);

        // A reference to the same base directory or an empty subdirectory must be prefixed with "./".
        // This also applies to a segment with a colon character (e.g., "file:colon") that cannot be used
        // as the first segment of a relative-path reference, as it would be mistaken for a scheme name
        // (see http://tools.ietf.org/html/rfc3986#section-4.2).
        return '' === $path || '/' === $path[0]
            || false !== ($colonPos = strpos($path, ':')) && ($colonPos < ($slashPos = strpos($path, '/')) || false === $slashPos)
            ? './'.$path : $path;
    }



/**
* copy folder recursively
*
* @param mixed $src
* @param mixed $dst
* @param array $folders - folders to copy (first level only)
*/
function folderRecurseCopy($src, $dst, $folders=null, $copy_files_in_root=true) {
    $res = false;

    $src =  $src . ((substr($src,-1)=='/')?'':'/');

    $dir = opendir($src);
    if($dir!==false){

        if (file_exists($dst) || @mkdir($dst, 0777, true)) {

            $res = true;

            while(false !== ( $file = readdir($dir)) ) {
                if (( $file != '.' ) && ( $file != '..' )) {
                    if ( is_dir($src . $file) ) {

                        if(isEmptyArray($folders) || in_array($src.$file.'/',$folders))
                        {
                                $res = folderRecurseCopy($src.$file, $dst . '/' . $file, null, true);
                                if(!$res) {break;}
                        }

                    }
                    elseif ($copy_files_in_root) {
                        copy($src.$file,  $dst . '/' . $file);
                    }
                }
            }
        }
        closedir($dir);

    }

    return $res;
}


/**
* Gets list of subfolders for given folder
*
* @param mixed $src
*/
function folderSubs($src, $exclude=null, $full_path=true) {
    $res = array();

    $src =  $src . ((substr($src,-1)=='/')?'':'/');

    if(file_exists($src)){

        $dir = opendir($src);
        if($dir!==false){


                while(false !== ( $file = readdir($dir)) ) {
                    if (( $file != '.' ) && ( $file != '..' ) && is_dir($src . $file)) {

                            if(is_array($exclude) && in_array($file, $exclude)){
                                continue;
                            }

                            if($full_path){
                                $res[] = $src.$file.'/';
                            }else{
                                $res[] = $file;
                            }

                    }
                }
            closedir($dir);
        }
    }

    return $res;
}

//------------------------------------------
//
// Returns false if given folder is not in heurist upload folder
// Returns null  if given folder does not exist
// Otherwise return real path
//
function isPathInHeuristUploadFolder($path, $check_existance=true){

    chdir(HEURIST_FILESTORE_DIR);// relatively db root  or HEURIST_FILES_DIR??
    $heurist_dir = realpath(HEURIST_FILESTORE_DIR);
    $r_path = realpath($path);

    if($check_existance && !$r_path) { //does not exist
        return null;
    }

    if($r_path){
        $r_path = str_replace('\\','/',$r_path);
        $heurist_dir = str_replace('\\','/',$heurist_dir);

        //realpath gives real path on remote file server
        if(strpos($r_path, '/srv/HEURIST_FILESTORE/')===0 ||
           strpos($r_path, '/misc/heur-filestore/')===0 ||     //heurx
           strpos($r_path, '/data/HEURIST_FILESTORE/')===0 ||  //huma-num
           strpos($r_path, $heurist_dir)===0){
               return $r_path;
           }
    }elseif(strpos($path, HEURIST_FILESTORE_DIR)===0){
            return $path;
    }

    return false;
}


//-----------------------  LOAD REMOTE CONTENT (CURL) --------------------------


/**
* Save remote url as file and returns the size of saved file
*
* Usage
* 1) save import from other database in temp file
* 2) remote image to create thumbnail
*
* Remote data are saved in scratch folder as temporary file
*
* @param mixed $url
* @param mixed $filename
*/
function saveURLasFile($url, $filename)
{
    //Download file from remote server
    $rawdata = loadRemoteURLContent($url, false);//use proxy
    if(is_string($rawdata)){
        return fileSave($rawdata, $filename);//returns file size
    }else{
        //error_log('Can not access remote resource '.filter_var($url,FILTER_SANITIZE_URL)); //snyk security
        return 0;
    }
}

/**
* Returns title of html dcoument for given url
*
* @param mixed $url
* @return $title
*/
function getTitleFromURL($url){

    $title = null;

    $url = str_replace(' ', '+', $url);

    $data = loadRemoteURLContentWithRange($url, "0-10000");//get title of webpage

    if ($data){

        // "/<title>(.*)<\/title>/siU"
        preg_match('!<\s*title[^>]*>\s*([^<]+?)\s*</title>!is', $data, $matches);
        if ($matches) {
            // Clean up title: remove EOL's and excessive whitespace.
            $title = preg_replace('/\s+/', ' ', $matches[1]);
            $title = trim($title);
        }
    }

    return $title;
}

//
// if the same server - try to include script instead of CURL request
// usage:
// 1. get registered database URL
// 2. database registration
// 3. get current db version
//
function loadRemoteURLContentSpecial($url){

    if(strpos($url, HEURIST_SERVER_URL)===0){

        //if requested url is on the same server
        //replace URL to script path in current installation folder
        //and execute script
        if(strpos(strtolower($url), strtolower(HEURIST_INDEX_BASE_URL))===0){
            $path = str_replace(HEURIST_INDEX_BASE_URL, HEURIST_DIR, $url);
        }else{
            $path = str_replace(HEURIST_BASE_URL, HEURIST_DIR, $url);
        }

        $path = substr($path,0,strpos($path,'?'));

        $parsed = parse_url($url);
        parse_str($parsed['query'], $_REQUEST);

        $out = getScriptOutput($path);

        return $out;
    }else{
        return loadRemoteURLContentWithRange($url, null, true);
    }
}

//
//
//
function loadRemoteURLContent($url, $bypassProxy = true) {
    return loadRemoteURLContentWithRange($url, null, $bypassProxy);
}

//
// $range - loads first n bytes (for example to detect title of web page)
//
function loadRemoteURLContentWithRange($url, $range, $bypassProxy = true, $timeout=30, $additional_headers=null) {

    global $glb_curl_code, $glb_curl_error;

    $glb_curl_code = null;
    $glb_curl_error = null;

    if(!function_exists("curl_init"))  {

        $glb_curl_code = HEURIST_SYSTEM_FATAL;
        $glb_curl_error = 'Cannot init curl extension. Verify php installation';

        return false;
    }

    $url = filter_var($url, FILTER_VALIDATE_URL);
    if(empty($url) || $url===false){
        $glb_curl_code = HEURIST_INVALID_REQUEST;
        $glb_curl_error = 'URL is not defined or invalid';

        return false;
    }

    if(!(strpos(strtolower($url),'https://')===0 || strpos(strtolower($url),'http://')===0)){
        $glb_curl_code = HEURIST_INVALID_REQUEST;
        $glb_curl_error = 'URL is not started with a trusted scheme';
        return false;
    }

    /*
    if(false && strpos($url, HEURIST_SERVER_URL)===0){
        return loadRemoteURLviaSocket($url);
    }
    */

    $useragent = 'Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.0.6) Gecko/2009011913 Firefox/3.0.6';
                 //'Firefox (WindowsXP) - Mozilla/5.0 (Windows; U; Windows NT 5.1; en-GB; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_COOKIEFILE, '/dev/null');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//return the output as a string from curl_exec
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
    curl_setopt($ch, CURLOPT_NOBODY, 0);
    curl_setopt($ch, CURLOPT_HEADER, 0);//don't include header in output
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);// follow server header redirects
    //Vulnerability curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);// don't verify peer cert
    if(strpos(strtolower($url), strtolower(HEURIST_MAIN_SERVER))===0){
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);// timeout after ten seconds
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);// no more than 5 redirections

    curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    //curl_setopt($ch, CURLOPT_REFERER, HEURIST_SERVER_URL);

    if($range){
        curl_setopt($ch, CURLOPT_RANGE, $range);
    }

    // check if the proxy needs to be used, $httpProxyActive defined in heuristConfigIni.php
    if(defined('HEURIST_HTTP_PROXY_ALWAYS_ACTIVE') && HEURIST_HTTP_PROXY_ALWAYS_ACTIVE){
        $bypassProxy = false;
    }

    if ( (!$bypassProxy) && defined('HEURIST_HTTP_PROXY') ) {
        curl_setopt($ch, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
        if(  defined('HEURIST_HTTP_PROXY_AUTH') ) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, HEURIST_HTTP_PROXY_AUTH);
        }
    }

    if(is_array($additional_headers) && !empty($additional_headers)){ // Add additional/custom headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, $additional_headers);
        //curl_setopt($curl, CURLOPT_HTTPHEADER, array('Expect:'));
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    $data = curl_exec($ch);

    $error = curl_error($ch);

    if ($error) {

        $glb_curl_code = 'curl';
        $glb_curl_error = $error;

        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));

        if(strpos($glb_curl_error, $code) !== false){ // http error
            $glb_curl_error = explode(': ', $glb_curl_error)[1];
            $glb_curl_error = 'Error Code : '.$error;
        }

        curl_close($ch);
        return false;
    } else {
        if(!$data){
            $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));

            $glb_curl_code = HEURIST_SYSTEM_FATAL;
            $glb_curl_error = 'HTTP Response Code: '.$code;
        }

        curl_close($ch);
        return $data;
    }
}

// Detects mimetype for given url
//
// alternative2: get_headers()
// alternative3: https://stackoverflow.com/questions/37731544/get-mime-type-by-url
// for local file use mime_content_type
//
function loadRemoteURLContentType($url, $bypassProxy = true, $timeout=30) {

    if(!function_exists("curl_init"))  {
        return false;
    }
    if(!$url){
        return false;
    }

    if(!(strpos(strtolower($url),'https://')===0 || strpos(strtolower($url),'http://')===0)){
        return false;
    }

    $content_type = false;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_NOBODY, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);// timeout after ten seconds
    //Vulnerability curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    curl_setopt($ch, CURLOPT_URL, $url);

    // check if the proxy needs to be used, $httpProxyActive defined in heuristConfigIni.php
    if(defined('HEURIST_HTTP_PROXY_ALWAYS_ACTIVE') && HEURIST_HTTP_PROXY_ALWAYS_ACTIVE){
        $bypassProxy = false;
    }

    if ( (!$bypassProxy) && defined('HEURIST_HTTP_PROXY') ) {
        curl_setopt($ch, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
        if(  defined('HEURIST_HTTP_PROXY_AUTH') ) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, HEURIST_HTTP_PROXY_AUTH);
        }
    }

    $data = curl_exec($ch);
    $error = curl_error($ch);

    if ($error) {
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        USanitize::errorLog('CURL ERROR: http code = '.$code.'  curl error='.$error);
    } else {
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    }
    curl_close($ch);

    return $content_type;
}

//
//
//
function getURLExtension($url){
    $extension = null;
    $ap = parse_url($url);
    if( array_key_exists('path', $ap) ){
        $path = $ap['path'];
        if($path){
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        }
    }
    return $extension;
}

//
// recognize mime type from url, update ext table if missed and returns extension
//
function recognizeMimeTypeFromURL($mysqli, $url, $use_default_ext = true){


        $url = filter_var($url, FILTER_SANITIZE_URL);

        //special cases for well known resources
        $force_add = null;
        $extension = null;
        $needrefresh = false;
        $mimeType = null;

        if(strpos($url, 'soundcloud.com')!==false){
            $mimeType  = MT_SOUNDCLOUD;
            $extension = 'soundcloud';
            $force_add = "('soundcloud','".MT_SOUNDCLOUD."', '0','','Soundcloud','')";
        }elseif(strpos($url, 'vimeo.com')!==false){
            $mimeType  = MT_VIMEO;
            $extension = 'vimeo';
            $force_add = "('vimeo','".MT_VIMEO."', '0','','Vimeo Video','')";
        }elseif(strpos($url, 'youtu.be')!==false || strpos($url, 'youtube.com')!==false){
            $mimeType  = MT_YOUTUBE;
            $extension = 'youtube';
            $force_add = "('youtube','".MT_YOUTUBE."', '0','','Youtube Video','')";
        }else{
            //get extension from url - unreliable
            //$f_extension = getURLExtension($url)

            $mimeType = loadRemoteURLContentType($url);

        }


        if($mimeType!=null && $mimeType!==false){

            //remove charset section
            if(strpos($mimeType,';')>0){
                $parts = explode(';', $mimeType);
                $k = 0;
                while($k<count($parts)){
                    if(strpos($parts[$k],'charset')!==false){
                        array_splice($parts, $k, 1);
                        //unset($parts[$k]);
                    }else{
                        $k++;
                    }
                }//while
                $mimeType = @$parts[0];
            }

            if($mimeType){

                if($mimeType==MIMETYPE_JSON ||  $mimeType=='application/ld+json'){
                    $mimeType = MIMETYPE_JSON;
                    $extension = 'json';
                    $force_add = "('json','application/json', '0','','JSON','')";
                }

                $ext_query = 'SELECT fxm_Extension FROM defFileExtToMimetype WHERE fxm_MimeType="'
                            .$mimeType.'"';
                $f_extension = mysql__select_value($mysqli, $ext_query);

                if($f_extension==null && $force_add!=null){
                    $mysqli->query('insert into defFileExtToMimetype ('
        .'`fxm_Extension`,`fxm_MimeType`,`fxm_OpenNewWindow`,`fxm_IconFileName`,`fxm_FiletypeName`,`fxm_ImagePlaceholder`'
                    .') values '.$force_add);
                    $needrefresh = true;
                }else{
                    $extension = $f_extension;
                }

            }
        }
        //if extension not found apply bin: application/octet-stream - generic mime type
        if($extension==null && $use_default_ext) {
            $extension = 'bin';
        }
        $res = array('extension'=>$extension, 'needrefresh'=>$needrefresh);

        return $res;
}




//----------------------------------
//
//
//
function getScriptOutput($path, $print = false)
{
    global $system;

    ob_start();

    if( is_readable($path) && $path )
    {
        include_once $path;
    }
    else
    {
        return false;
    }

    if( $print == false ){
        return ob_get_clean();
    }else{
        echo ob_get_clean();
    }
}


//----------------------------------------------- PARSING

//
// try to read file and detect separtors
// return an aray with suggestions array('csv_delimiter'=>, 'csv_delimiter'=> , 'csv_enclosure'=>)
//
// Important: it works only in case file is UTF8
//
//
function autoDetectSeparators($filename, $csv_linebreak='auto', $csv_enclosure='"'){

    $handle = @fopen($filename, 'r');
    if (!$handle) {
        $s = null;
        if (! file_exists($filename)) {$s = ' does not exist';}
        elseif (! is_readable($filename)) {$s = ' is not readable';}
            else {$s = ' could not be read';}

        if($s){
            return array('error'=>('File '.$filename. $s));
        }
    }

    //DETECT End of line
    if($csv_enclosure=='' || $csv_enclosure=='none'){
        $csv_enclosure = 'ʰ';//rare character
    }

    $eol = null;
    if($csv_linebreak=='win'){
        $eol = "\r\n";
    }elseif($csv_linebreak=='nix'){
        $eol = "\n";
    }elseif($csv_linebreak=='mac'){
        $eol = "\r";
    }

    if($csv_linebreak=='auto' || $csv_linebreak==null || $eol==null){
        ini_set('auto_detect_line_endings', 'true');

        $line = fgets($handle, 1000000);//read line and auto detect line break
        $position = ftell($handle);
        fseek($handle, $position - 5);
        $data = fread($handle, 10);
        rewind($handle);

        if(substr_count($data, "\r\n") > 0){
            $eol = "\r\n";
        }elseif(substr_count($data, "\n") > 0){
            $eol = "\n";
        }else{
            $eol = "\r";
        }
    }

    //--------- DETECT FIELD SEPARATOR
    //fgetcsv и str_getcsv depends on server locale
    // it is possible to set it in  /etc/default/locale (Debian) or /etc/sysconfig/i18n (CentOS)  LANG="en_US.UTF-8"
    setlocale(LC_ALL, 'en_US.utf8');


    $delimiters = array("\t"=>0,','=>0,';'=>0,':'=>0,'|'=>0,'-'=>0);
    $force_tabs = false; // if the first line contains tab separators, default to tabs

    foreach ($delimiters as $csv_delimiter=>$val){
        $line_no = 0;

        while (!feof($handle)) {

            $line = stream_get_line($handle, 1000000, $eol);
            /*
            if(!mb_detect_encoding($line, 'UTF-8', true)){
                fclose($handle);
                return array('error'=>('File '.$filename. ' is not UTF-8. It is not possible to autodetect separators'));
            }
            */

            $fields = str_getcsv ( $line, $csv_delimiter, $csv_enclosure );// $escape = "\\"

            $cnt = count($fields);
            if($cnt>200){ //too many fields
                $delimiters[$csv_delimiter] = 0; //not use
                break;
            }else{
                if($line_no==0){
                    $delimiters[$csv_delimiter] = $cnt;

                    if($cnt > 0 && $csv_delimiter == "\t"){
                        $force_tabs = true;
                        break 2;
                    }
                }elseif($delimiters[$csv_delimiter] != $cnt){
                    $delimiters[$csv_delimiter] = 0; //not use
                    break;
                }
            }

            if($line_no>10) {break;}
            $line_no++;
        }
        rewind($handle);
    }//for delimiters
    fclose($handle);

    if($force_tabs){
        $csv_delimiter = "tab";
    }else{

        $max = 0;
        $csv_delimiter = ',';//default
        foreach ($delimiters as $delimiter=>$cnt){
            if($cnt>$max){
                $csv_delimiter = $delimiter;
                $max = $cnt;
            }
        }
        if($csv_delimiter=="\t") {$csv_delimiter = "tab";}
    }

    if($eol=="\r\n"){
        $csv_linebreak='win';
    }elseif($eol=="\n"){
        $csv_linebreak='nix';
    }elseif($eol=="\r"){
        $csv_linebreak='mac';
    }

    return array('csv_linebreak'=>$csv_linebreak, 'csv_delimiter'=>$csv_delimiter, 'csv_enclosure'=>$csv_enclosure);
}

//
//
//
function isXMLfile($filename){

    $res = false;
    $handle = @fopen($filename, 'r');
    if ($handle) {
        $output = fread($handle, 10);
        $pp = strpos($output, '<?xml');
        $res = ($pp === 0 || $pp === 3);
        fclose($handle);
    }
    return $res;
}

//
// Working with semaphore file for particular long action
// if $range_minutes<0 - remove log file
//
function isActionInProgress($action, $range_minutes, $db_name=''){

    $progress_flag = HEURIST_FILESTORE_ROOT.'_operation_locks'.($db_name?('_'.$db_name):'').'.info';

    //flag that backup in progress
    if(file_exists($progress_flag)){

        if($range_minutes<0){
            unlink($progress_flag);
            return false;
        }

        $datetime2 = date_create('now');

        $reading = fopen($progress_flag, 'r');
        $writing = fopen('myfile.tmp', 'w');

        $replaced = false;
        $not_allowed = false;

        while (!feof($reading)) {
            $line = fgets($reading);
            if (strpos($line, $action)===0) {

                $datetime1 = date_create(trim(substr($line, strlen($action))));
                $interval = date_diff($datetime1, $datetime2);

                $allowed = ($interval->format('%y')>0 ||
                $interval->format('%m')>0 || $interval->format('%d')>0 ||
                $interval->format('%h')>0 || $interval->format('%i')>$range_minutes);

                if($allowed){
                    $line = $action.' '.$datetime2->format(DATE_8601)."\n";
                    $replaced = true;
                }else{
                    $not_allowed = true;
                    break;
                }
            }
            fputs($writing, $line);
        }
        fclose($reading); fclose($writing);
        // might as well not overwrite the file if we didn't replace anything
        if ($replaced)
        {
            rename('myfile.tmp', $progress_flag);
        } else {
            unlink('myfile.tmp');
        }
        if($not_allowed){
            return false;
        }
    }elseif($range_minutes>0) {
        $fp = fopen($progress_flag, 'w');
        fwrite($fp, $action.' '. date_create('now')->format(DATE_8601));
        fclose($fp);
    }
    return true;
}

//
// Upload file to Nakala and return URL to new Nakala file
// $system => Initiated System object
// $params => array(
//      'api_key' => User's Nakala API Key (retrieved from User Preferences)
//      'file' => array(
//          'path' => path to file
//          'type' => mime type
//          'name' => file name
//      ),
//      'files' => array( formatted array of files values, already uploaded to Nakala )
//      'meta' => array( formatted array of Nakala Metadata values )
// )
// $uri_parts => return new file + Nakala generated DOI in array(sha1, doi) or uri format
// $upload_only => only upload file, returns sha1 value (usually for a file part of a set of files)
//
function uploadFileToNakala($system, $params) {

    global $glb_curl_code, $glb_curl_error, $system;
    $glb_curl_code = null;
    $glb_curl_error = null;

    $herror = HEURIST_ACTION_BLOCKED;

    $NAKALA_BASE_URL = @$params['use_test_url'] == 1 ? 'https://test.nakala.fr/u/datas/' : 'https://nakala.fr/u/datas/';
    $NAKALA_BASE_URL_API = @$params['use_test_url'] == 1 ? 'https://apitest.nakala.fr/datas/' : 'https://api.nakala.fr/datas/';

    $missing_key = '<br><br>Your Nakala API key is either missing or invalid, please ';
    $missing_key .= $system->isAdmin() ? 'ask a database administrator to setup the key within' : 'ensure you\'ve set it in';
    $missing_key .= ' Database properties';

    $unknow_error_msg = 'An unknown response was receiveed from Nakala after uploading the selected file.<br>Please contact the Heurist team if this persists.';

    if(!function_exists("curl_init"))  {

        $glb_curl_code = HEURIST_SYSTEM_FATAL;
        $glb_curl_error = 'Cannot init curl extension. Verify php installation';
        $system->addError(HEURIST_SYSTEM_FATAL, $glb_curl_error);

        return false;
    }

    $api_key = 'X-API-KEY: ' . $params['api_key'];
    $file_sha1 = '';

    $useragent = 'Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.0.6) Gecko/2009011913 Firefox/3.0.6';

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_HTTPHEADER, array($api_key));// USERs API KEY

    curl_setopt($ch, CURLOPT_COOKIEFILE, '/dev/null');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//return the output as a string from curl_exec
    curl_setopt($ch, CURLOPT_NOBODY, 0);
    curl_setopt($ch, CURLOPT_HEADER, 0);//don't include header in output
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);// follow server header redirects

    curl_setopt($ch, CURLOPT_TIMEOUT, 60);// timeout after sixty seconds
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);// no more than 5 redirections

    curl_setopt($ch, CURLOPT_USERAGENT, $useragent);

    curl_setopt($ch, CURLOPT_AUTOREFERER, true);

    if(defined("HEURIST_HTTP_PROXY")) {
        curl_setopt($ch, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
        if(defined('HEURIST_HTTP_PROXY_AUTH')) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, HEURIST_HTTP_PROXY_AUTH);
        }
    }

    if(!file_exists($params['file']['path'])){
        $system->addError(HEURIST_ERROR, 'Could not locate the file to be uploaded to Nakala');
        return false;
    }

    $curl_file = new CURLFile($params['file']['path'], $params['file']['type'], $params['file']['name']);
    $local_sha1 = sha1_file($params['file']['path']);

    curl_setopt($ch, CURLOPT_URL, "{$NAKALA_BASE_URL_API}uploads");

    // Check if file has already been uploaded - may have previously failed
    $file_list = curl_exec($ch);

    $error = curl_error($ch);

    if ($error) {

        $glb_curl_code = 'curl';
        $glb_curl_error = $error;

        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));

        if($code == 401 || $code == 403){ // invalid/missing api key, or unknown account/user
            $glb_curl_error .= $missing_key;
            $herror = HEURIST_INVALID_REQUEST;

            curl_close($ch);
            $system->addError($herror, $glb_curl_error);

            return false;
        } // other error do not matter here
    }

    $file_list = json_decode($file_list, true);
    if(JSON_ERROR_NONE == json_last_error() && is_array($file_list)){

        if(array_key_exists('message', $file_list)){
            $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));

            if($code == 401 || $code == 403){ // invalid/missing api key, or unknown account/user
                $glb_curl_error .= $missing_key;
                $herror = HEURIST_INVALID_REQUEST;

                curl_close($ch);
                $system->addError($herror, $glb_curl_error);

                return false;
            } // other error do not matter here
        }else{
            foreach ($file_list as $file_dtls) {
                if($local_sha1 == $file_dtls['sha1']){
                    $file_sha1 = $local_sha1;
                    break;
                }
            }
        }
    }

    if($file_sha1 == ''){ // UPLOAD FILE - (upload one file at a time, collect all SHA1 values, then process all together)

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array('file' => $curl_file));

        $file_details = curl_exec($ch);

        $error = curl_error($ch);

        if ($error) {

            $glb_curl_code = 'curl';
            $glb_curl_error = $error;

            $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));

            if($code == 401 || $code == 403){ // invalid/missing api key, or unknown account/user
                $glb_curl_error .= $missing_key;
                $herror = HEURIST_INVALID_REQUEST;
            }else{
                $glb_curl_error = $file_details['message'];
            }

            curl_close($ch);
            $system->addError($herror, $glb_curl_error);

            return false;
        }

        $file_details = json_decode($file_details, true);

        if(JSON_ERROR_NONE != json_last_error() || !is_array($file_details)){ // json error occurred | is not array | is missing information
            curl_close($ch);
            $system->addError(HEURIST_ACTION_BLOCKED, $unknow_error_msg);

            return false;
        }

        if(array_key_exists('message', $file_details)){

            $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));

            if($code == 401 || $code == 403){ // invalid/missing api key, or unknown account/user
                $glb_curl_error .= $missing_key;
                $herror = HEURIST_INVALID_REQUEST;
            }else{
                $glb_curl_error = $file_details['message'];
            }

            curl_close($ch);
            $system->addError($herror, $glb_curl_error);

            return false;
        }

        $file_sha1 = $file_details['sha1'];

        if($local_sha1 != $file_sha1){
            $system->addError(HEURIST_ACTION_BLOCKED, 'The local file and uploaded file to Nakala do not match.<br>Please contact the Heurist team if this persists.');
            return false;
        }
        //$file_title = $upload_details['name']; Don't need it
    }

    $status = 'pending';
    if(array_key_exists('status', $params)){
        $status = $params['status'];
    }

    // UPLOAD METADATA
    $metadata = array('status' => $status, 'metas' => array(), 'files' => array());

    $metadata['files'][] = array( 'sha1' => $file_sha1 );
    if(!empty($params['file']['description'])){
        $metadata['files'][0]['description'] = htmlspecialchars($params['file']['description']);
    }

    foreach ($params['meta'] as $data) {
        $metadata['metas'][] = $data;
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, array($api_key, 'Content-Type:application/json'));// Reset headers to specify the return type
    curl_setopt($ch, CURLOPT_URL, "{$NAKALA_BASE_URL_API}");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($metadata));

    $result = curl_exec($ch);

    $error = curl_error($ch);

    if ($error) {

        $glb_curl_code = 'curl';
        $glb_curl_error = $error;

        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));

        if($code == 401 || $code == 403){ // invalid/missing api key, or unknown account/user
            $glb_curl_error .= $missing_key;
            $herror = HEURIST_INVALID_REQUEST;
        }else{
            $glb_curl_error = $file_details['message'];
        }

        curl_close($ch);
        $system->addError($herror, $glb_curl_error);

        return false;
    }

    $result = json_decode($result, true);

    if(JSON_ERROR_NONE != json_last_error() || !is_array($result)){ // json error occurred | is not array | is missing information
        curl_close($ch);
        $system->addError(HEURIST_ACTION_BLOCKED, $unknow_error_msg);

        return false;
    }

    if(array_key_exists('payload', $result)){
        $payload = $result['payload'];
        if(array_key_exists('id', $payload)){
            if(array_key_exists('return_type', $params) && $params['return_type'] == 'editor'){ // returns link to private view
                $external_url = "{$NAKALA_BASE_URL}{$result['payload']['id']}";
            }else{ // returns link to publically available file
                $external_url = "{$NAKALA_BASE_URL_API}{$result['payload']['id']}/{$file_sha1}";
            }
        }else{

            curl_close($ch);
            $msg = '';
            if(is_array($payload)){
                $msg = implode('<br>', array_values($payload));
            }else{
                $msg = $payload;
            }
            $system->addError($herror, $msg);

            return false;
        }
    }elseif(array_key_exists('message', $result)){

        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));

        if($code == 401 || $code == 403){ // invalid/missing api key, or unknown account/user
            $glb_curl_error .= $missing_key;
            $herror = HEURIST_INVALID_REQUEST;
        }else{
            $glb_curl_error = $result['message'];
        }

        curl_close($ch);
        $system->addError($herror, $glb_curl_error);

        return false;
    }else{
        curl_close($ch);
        $system->addError(HEURIST_ACTION_BLOCKED, $unknow_error_msg);

        return false;
    }

    return $external_url;
}

//
// not used
//
function flush_buffers($start=true){
    //ob_end_flush();
    @ob_flush();
    @flush();
    if($start) {@ob_start();}
}

/**
 * Reads a file in chunks and outputs it to the client in a memory-efficient manner.
 *
 * This method is particularly useful for large files, as it reads the file in 10 MB chunks
 * and outputs it directly to the client to avoid exhausting memory.
 *
 * @param string $file_path The path to the file to be read and output.
 *
 * @return int|false Returns the size of the file in bytes if successful, or false on failure.
 */
function fileReadByChunks($file_path, $range_min=0, $range_max=0)
{
    // Get the size of the file
    $file_size = getFileSize($file_path);

    if($file_size==0){
        return; //file does not exist
    }

    // Set the chunk size to 10 MB (10 * 1024 * 1024 bytes)
    $chunk_size = 10 * 1024 * 1024;

    // Check if the file is larger than the chunk size
    if ($range_max==0 && $file_size < $chunk_size) {
        // If the file is smaller than the chunk size, output the entire file
        return readfile($file_path);
    }

    // Open the file in binary read mode
    $handle = fopen($file_path, 'rb');
    if(!$handle){
        //error_log('file not found: '.htmlspecialchars($filename));
        return 0;
    }

    if($range_max>0){ //output defined range only
        if($range_min>0) {fseek($handle,$range_min);}
        $chunk = fread($handle, $range_max-$range_min+1);
        echo $chunk;
    }else{
        // Loop through the file and read it in chunks
        while (!feof($handle)) {
            echo fread($handle, $chunk_size); // Output the current chunk (was 1000)
            @ob_flush(); // Flush the output buffer
            @flush();    // Flush the system buffers
        }
    }
    // Close the file handle
    fclose($handle);

    // Return the file size after reading
    return $file_size;
}

/**
 * Retrieves the size of a file, with an optional cache-clearing mechanism.
 *
 * This function checks the existence of the file and returns its size in bytes. It can optionally
 * clear the file status cache to ensure the most up-to-date file size is retrieved, which is useful
 * if the file is being modified during runtime.
 *
 * @param string $file_path The path to the file whose size is to be determined.
 * @param bool $clear_stat_cache (Optional) If true, clears the file status cache before checking the file size.
 *                               Default is false.
 *
 * @return int The size of the file in bytes. Returns 0 if the file does not exist.
 */
function getFileSize($file_path, $clear_stat_cache = false) {
    // If cache clearing is enabled, clear the file status cache
    if ($clear_stat_cache) {
        if (version_compare(phpversion(), '5.3.0') >= 0) {
            // Clear cache for the specific file (PHP 5.3.0 or higher)
            clearstatcache(true, $file_path);
        } else {
            // Clear entire cache (for versions lower than 5.3.0)
            clearstatcache();
        }
    }

    // Check if the file exists
    if (file_exists($file_path)) {
        // Return the file size, handling potential integer overflow on 32-bit systems
        return USystem::fixIntegerOverflow(filesize($file_path));
    } else {
        // Return 0 if the file does not exist
        return 0;
    }
}

?>
