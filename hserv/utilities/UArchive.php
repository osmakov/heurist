<?php
/**
* uArchive.php
*
*   zip
*   unzip
*   unzipFlat
*
*   createBz2
*   extractBz2 - if bz2 archive contains the only file
*
* At the moment we have 3 places where we use archives
* DbUtils::databaseDrop  - optionally archive the entire dbfolder+sql dump into single archive
* Safeguard archive/upload to repository - creates 3 archives a) with individual set of folder (depends on user preferences)+dump b) sql dump c) hml
* Purge inactive databases.  Uses DbUtils::databaseDrop and optionally creates 2 archives with sysArchive and Import tables
*
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

namespace hserv\utilities;
use hserv\utilities\USanitize;

define('MAX_FILES', 10000);
define('MAX_SIZE', 1073741824);// 1 GB
define('MAX_RATIO', 90);
define('READ_LENGTH', 1024);
define('WRITE_LENGTH', 4096);//16384


class UArchive {

    /**
    * Zips everything in a directory
    *
    * @param mixed $source       Source folder or array of folders
    * @param mixed $destination  Destination file
    */
    public static function zip($source, $only_these_folders, $destination, $verbose=true) {

        if (!extension_loaded('zip')) {
            return $verbose?'PHP Zip extension is not accessible':false;
        }
        if (!file_exists($source)) {
            return $verbose?(htmlspecialchars($source).' was not found'):false;
        }



        $zip = new \ZipArchive();
        if (!$zip->open($destination, \ZipArchive::CREATE)) {
            return $verbose?('Failed to create zip file at '.htmlspecialchars($destination)):false;
        }

        try{

            $src = realpath($source);
            if(!$src) {
                return $verbose?('Cannot create zip archive '.htmlspecialchars($source).' is not a folder'):false;
            }
            $source = str_replace('\\', '/', $src);

            if (is_dir($source) === true) {

                chdir($source);

                $parent_dir = '';
                //$root_dir = $source;
                if( is_array($only_these_folders) ){
                    foreach ($only_these_folders as $idx=>$folder) {
                        $folder = str_replace('\\', '/', $folder);
                        if(strpos( $folder, $source )!==0){
                            $folder = $source."/".$folder;
                        }
                        $only_these_folders[$idx] = $folder;
                    }
                }

                $entry_idx = 0;
                $do_not_compress = array('jpg','jpeg','jfif','jpe','gif','png','mp3','mp4','mpg','mpeg','tif','tiff','zip','gzip','kmz','tar');

                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source), \RecursiveIteratorIterator::SELF_FIRST);

                foreach ($files as $file) {

                    $file = str_replace('\\', '/', $file);

                    // Ignore "."   and   ".." folders
                    if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) ) {continue;}

                    if( is_dir($file) && substr($file,-1)!='/' ){
                        $file = $file.'/';
                    }

                    //ignore files that are not in list of specified folders
                    $is_filtered = true;
                    if( is_array($only_these_folders) ){
                        $is_filtered = false;
                        foreach ($only_these_folders as $folder) {
                            if( strpos( $file, $folder )===0 ){
                                $is_filtered = true;
                                break;
                            }
                        }
                    }

                    if(!$is_filtered) {continue;} //exclude not in $only_these_folders

                    // Determine real path
                    $file = realpath($file);

                    if($file!==false){

                        $file2 = str_replace('\\', '/', $file);

                        if (is_dir($file) === true) { // Directory
                            //remove root path
                            $newdir = str_replace($source.'/', '', $file2.'/');
                            if(!$zip->addEmptyDir( $newdir )){
                                //$zip->getStatusString()
                                return $verbose?('Can not add folder '.$newdir.' to archive'):false;
                            }
                            $entry_idx++;
                        }
                        elseif (is_file($file) === true) { // File
                            $newfile = str_replace($source.'/', '', $file2);//without folder name
                            if(!$zip->addFile($file, $newfile)){
                                return $verbose?('Can not add file '.$newfile.' to archive'):false;
                            }
                            $type = strtolower(substr(strrchr($newfile, '.'), 1));
                            if(in_array($type, $do_not_compress)){
                                $zip->setCompressionIndex($entry_idx, \ZipArchive::CM_STORE);
                            }
                            $entry_idx++;

                            //$zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                        }
                    }
                }//recursion

            } elseif (is_file($source) === true) {
                $zip->addFile($source, basename($source));
                //$zip->addFromString(basename($source), file_get_contents($source));
            }


            // Close zip and show output if verbose
            $numFiles = $zip->numFiles;
            $zip->close();
            if(file_exists($destination)){
                $size = filesize($destination) / pow(1024, 2);

                if($verbose) {
                    echo "<br>Successfully dumped data from ". htmlspecialchars($source) ." to ".htmlspecialchars($destination);
                    echo "<br>The zip file contains ".htmlspecialchars($numFiles." files and is ".sprintf("%.2f", $size))."MB";
                }
            }else{
                return $verbose?($destination.' archive not created. Directory may be non-writeable or archive function is not installed on server'):false;
            }
            return true;

        } catch (\Exception  $e){
            error_log( $e->getMessage() );
            return $verbose?('Cannot create zip archive '.htmlspecialchars($destination).' '.\Exception::getMessage()):false;
        }
    }

    /**
    * unzip given archive to destination folder
    *
    * @param mixed $system
    * @param mixed $zipfile
    * @param mixed $destination
    */
    public static function unzip($system, $zipfile, $destination){

        if(!(file_exists($zipfile) && filesize($zipfile)>0 &&  file_exists($destination))){
            throw new \Exception('Archive file not found');
        }

        $root_folder = $system->getFileStoreRootFolder();
        chdir($root_folder);
        $root_folder = realpath($root_folder);

        if (strpos($root_folder, '\\')!==false){
            $root_folder = str_replace('\\','/',$root_folder);
        }

        //set current folder
        chdir($destination);// relatively db root  or HEURIST_FILES_DIR??
        $destination_dir = realpath($destination);

        if ($destination_dir !== false) {
            if (strpos($destination_dir, '\\')!==false){
                $destination_dir = str_replace('\\','/',$destination_dir);
            }
            if( substr($destination_dir, -1, 1) != '/' ){
                $destination_dir = $destination_dir.'/';
            }
            if (strpos($destination_dir, $root_folder) !== 0) {
                //HEURIST_SCRATCH_DIR
                //HEURIST_TILESTACKS_DIR
                throw new \Exception('Destination folder must within database storage folder ');//$destination_dir.'  '.$root_folder
            }
        }

        $fileCount = 0;
        $totalSize = 0;

        $zip = new \ZipArchive();
        if ($zip->open($zipfile) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                $stats = $zip->statIndex($i);

                if (strpos($filename, '../') !== false || substr($filename, 0, 1) === '/') {
                    throw new \Exception('Archive contains unsecure entry '.$filename);
                }

                if (substr($filename, -1) !== '/') {
                    $fileCount++;
                    if ($fileCount > MAX_FILES) {
                        // Reached max. number of files
                        throw new \Exception('Archive contains more than '.MAX_FILES.' entries');
                    }

                    $destination_file = $destination_dir.$filename;

                    $fp = $zip->getStream($filename);// Compliant
                    $currentSize = 0;
                    while (!feof($fp)) {
                        $currentSize += READ_LENGTH;
                        $totalSize += READ_LENGTH;

                        if ($totalSize > MAX_SIZE) {
                            // Reached max. size
                            throw new \Exception('Maximum allowed extraction size achieved ('.MAX_SIZE.')');
                        }

                        // Additional protection: check compression ratio
                        if ($stats['comp_size'] > 0  && $stats['comp_size']>READ_LENGTH) {
                            $ratio = floor($currentSize / $stats['comp_size']);
                            if ($ratio > MAX_RATIO) {
                                // Reached max. compression ratio
                                throw new \Exception('Maximum allowed compression ration detected ('.$ratio.' > '.MAX_RATIO.')');
                            }
                        }

                        file_put_contents($destination_file, fread($fp, READ_LENGTH), FILE_APPEND);
                    }

                    fclose($fp);
                } else {
                    if (!file_exists($destination_dir.$filename) && !mkdir($destination_dir.$filename, 0777, true)) {
                        throw new \Exception('Cannot create subfolder on unzip');
                    }
                }
            }
            $zip->close();
        }

        return $fileCount;

    }
    //
    // flatten zip archive - extract without structures
    // returns list of files
    //
    public static function unzipFlat($zipfile, $destination){

        if(file_exists($zipfile) && filesize($zipfile)>0 &&  file_exists($destination)){

            $res = array();
            $zip = new \ZipArchive;
            if ( $zip->open( $zipfile ) === true)
            {
                for ( $i=0; $i < $zip->numFiles; $i++ )
                {
                    $entry = $zip->getNameIndex($i);
                    if ( substr( $entry, -1 ) == '/' ) {continue;} // skip directories

                    $fp = $zip->getStream( $entry );
                    if (!$fp ) {
                        throw new \Exception('Unable to extract the file.');
                    }else{
                        $filename = $destination.USanitize::sanitizeFileName(basename($entry));//snyk SSRF
                        $ofp = fopen($filename, 'w' );
                        while ( ! feof( $fp ) ) {
                            fwrite( $ofp, fread($fp, 8192) );
                        }

                        fclose($fp);
                        fclose($ofp);

                        $res[] = $filename;
                    }
                }

                $zip->close();
                return $res;
            }
            else {
                return false;
            }
        }else{
            return false;
        }
    }

    //
    //
    //
    public static function createBz2($source, $only_these_folders, $destination, $verbose=true) {

        if (!extension_loaded('bz2')) {
            return $verbose?'PHP Bz2 extension is not accessible':false;
        }
        if (!file_exists($source)) {
            return $verbose?(htmlspecialchars($source).' was not found'):false;
        }else {
            $numFiles = 0;
        }

        $phar = new \PharData($destination);

        if (false === $phar) {
            return $verbose?('Failed to create bz2 file at '.htmlspecialchars($destination)):false;
        }

        try{
            $src = realpath($source);

            if(!$src) {
                return $verbose?('Cannot create bz2 archive '.htmlspecialchars($source).' is not a folder'):false;
            }

            if($verbose){
                echo '<br>Source '.htmlspecialchars($source.' '.$src);
            }

            $source = str_replace('\\', '/', $src);


            if (is_dir($source) === true) {

                chdir($source);

                $parent_dir = '';
                //$root_dir = $source;
                if( is_array($only_these_folders) ){
                    foreach ($only_these_folders as $idx=>$folder) {
                        $folder = str_replace('\\', '/', $folder);
                        if(strpos( $folder, $source )!==0){
                            $folder = $source."/".$folder;
                        }
                        $only_these_folders[$idx] = $folder;
                    }
                }

                $entry_idx = 0;
                //$do_not_compress = array('jpg','jpeg','jfif','jpe','gif','png','mp3','mp4','mpg','mpeg','tif','tiff','zip','gzip','kmz','tar');

                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source), \RecursiveIteratorIterator::SELF_FIRST);

                foreach ($files as $file) {

                    $file = str_replace('\\', '/', $file);

                    // Ignore "."  and  ".." folders
                    if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) ) {continue;}

                    if( is_dir($file) && substr($file,-1)!='/' ){
                        $file = $file.'/';
                    }

                    //ignore files that are not in list of specified folders
                    $is_filtered = true;
                    if( is_array($only_these_folders) ){
                        $is_filtered = false;
                        foreach ($only_these_folders as $folder) {
                            if( strpos( $file, $folder )===0 ){
                                $is_filtered = true;
                                break;
                            }
                        }
                    }

                    if(!$is_filtered) {continue;} //exclude not in $only_these_folders

                    // Determine real path
                    $file = realpath($file);

                    if($file!==false){

                        $file2 = str_replace('\\', '/', $file);

                        if (is_dir($file) === true) { // Directory
                            //remove root path
                            $newdir = str_replace($source.'/', '', $file2.'/');
                            $phar->addEmptyDir( $newdir );
                            $entry_idx++;
                        }
                        elseif (is_file($file) === true) { // File
                            $newfile = str_replace($source.'/', '', $file2);//without folder name

                            $phar->addFile($file, $newfile);

                            // THERE IS NO WAY TO SET INDIVIDUAL COMPRESSION LEVEL PER FILE

                            $entry_idx++;

                            $numFiles++;
                        }

                    }
                }//recursion

            } elseif (is_file($source) === true) {

                $size_mb = filesize($source) / pow(1024, 2);
                if($size_mb>128){
                    if($size_mb>256){
                        ini_set('memory_limit','1024M');
                    }else{
                        ini_set('memory_limit','256M');//'2048M');
                    }
                }

                if($verbose) {echo "Add file ".htmlspecialchars(basename($source))." (size $size_mb)\n";}

                $phar->addFile($source, basename($source));
                $numFiles++;
                //$phar->addFromString(basename($source), file_get_contents($source));
            }

            $res = self::bzip2($destination, $destination.'.bz2');

            if($res!==true){
                return $verbose?$res:false;
            }

            //$phar->compress(\Phar::BZ2); it does not work for large data

            if(file_exists($destination.'.bz2')){ //

                unlink($destination);

                $size = filesize($destination.'.bz2') / pow(1024, 2);

                if($verbose) {
                    echo "<br>Successfully dumped data from ". htmlspecialchars($source) ." to ".htmlspecialchars($destination);
                    echo "<br>The archive file contains ".$numFiles." files and is ".sprintf("%.2f", $size)."MB";
                }
            }else{
                return $verbose?($destination.'.bz2 archive not created Directory may be non-writeable or archive function is not installed on server'):false;
            }

            return true;

        } catch (\Exception  $e){
            error_log( $e->getMessage() );
            return $verbose? ('Cannot create archive '.htmlspecialchars($destination).' '.$e->getMessage()) :false;
        }
    }

    /**
     * @return true or error message
     * @param string $in - filename to be compressed
     * @param string $out - name of archive if not set it renames $in with bz2 ext and place in the same folder
     * @desc compressing the file with the bzip2-extension
    */

    private static function bzip2 ($in, $out)
    {

        if (!file_exists ($in) || !is_readable ($in)){
            return 'Source file to be archived doesn\'t exists';
        }

        if($out==null){
            $out = $in.'.bz2';
            if(file_exists($out)){
                unlink($out);
            }
        }

        if (  (!file_exists($out) && !is_writeable(dirname($out)))
            ||
              (file_exists($out) && !is_writable($out)) ){
            return 'Destination folder is not writeable';
        }

        $in_file = fopen ($in, "rb");
        $out_file = bzopen ($out, "w");

        while (!feof ($in_file)) {
            $buffer = fgets ($in_file, WRITE_LENGTH);
            bzwrite ($out_file, $buffer, WRITE_LENGTH);
        }

        fclose ($in_file);
        bzclose ($out_file);

        return true;
    }


    /**
     * @return bool
     * @param string $in
     * @param string $out
     * @desc uncompressing the file with the bzip2-extension
    */
    public static function bunzip2($in, $out)
    {
        if (!file_exists ($in) || !is_readable ($in)){
             throw new \Exception('Archive file doesn\'t exists');
        }

        if (!file_exists ($out) && !is_writeable (dirname ($out)) || (file_exists($out) && !is_writable($out)) ){
             throw new \Exception('Destination folder or file is not writeable');
        }

        $in_file = bzopen ($in, "r");
        $out_file = fopen ($out, "wb");

        while ($buffer = bzread ($in_file, 4096)) {
            fwrite ($out_file, $buffer, 4096);
        }

        bzclose ($in_file);
        fclose ($out_file);

        return true;
    }
}
?>
