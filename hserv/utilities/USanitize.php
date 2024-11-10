<?php
namespace hserv\utilities;

/**
* Sanitize library to make requests, urls, paths, filenames safe
* (SSRF and path traversal attacks)
*
* sanitizeRequest - removes all tags from request variables
* sanitizePath - removes /../
* sanitizeURL
* sanitizeString - strip_tags (except allowed) and htmlspecialchars
* stripScriptTagInRequest - removes only script tags
*
* sanitizeFileName
* fileNameBeautify (protected)
*
* getHTMLPurifier
* purifyHTML - clean html with HTMLPurifier
*
* errorLog - wraps around error_log to prevent log injection
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
class USanitize {

    private static $purifier = null;

    //
    // sysadmin protection - reset from request to avoid exposure in possible error/log messages
    //
    //
    //
    //
    public static function getAdminPwd($name='pwd'){
        if(@$_REQUEST[$name]){
            $sysadmin_pwd  = $_REQUEST[$name];
            unset($_REQUEST[$name]);
        }else{
            $sysadmin_pwd = null;
        }
        return $sysadmin_pwd;
    }

    //
    //
    //
    public static function sanitizeInputArray()
    {
        if(@$_SERVER['REQUEST_METHOD']=='POST'){
            $req_params = filter_input_array(INPUT_POST);
        }else{
            $req_params = filter_input_array(INPUT_GET);
        }
        return $req_params;
    }

    //
    //  to be removed (used only once in usr_info.php )
    //
    public static function sanitizeRequest(&$params){

        foreach($params as $k => $v)
        {
            if($v!=null){

                if(!isEmptyArray($v)){
                    USanitize::sanitizeRequest($v);

                }else{
                    $v = trim($v);//so we are sure it is whitespace free at both ends

                    //sanitise string
                    $v = filter_var($v, FILTER_SANITIZE_STRING);

                }
                $params[$k] = $v;
            }
        }

    }

    //
    //  removes /../
    //
    public static function sanitizePath($path, $use_native_separator=false) {
        // Skip invalid input.
        if (!isset($path)) {
            return '';
        }
        if ($path === '') {
            return '';
        }

        // Attempt to avoid path encoding problems.
        //$path = preg_replace("/[^\x20-\x7E]/", '', $path);
        $path = str_replace("\0", '', $path);
        $path = str_replace('\\', '/', $path);

        // Remember path root.
        $prefix = substr($path, 0, 1) === '/' ? '/' : '';

        // Process path components
        $stack = array();
        $parts = explode('/', $path);
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                // No-op: skip empty part.
            } elseif ($part !== '..') {
                array_push($stack, $part);
            } elseif (!empty($stack)) {
                array_pop($stack);
            } else {
                return '';// Out of the root.
            }
        }

        // Return the "clean" path
        $path = $prefix . implode('/', $stack);
        if( is_dir($path) && substr($path, -1, 1) != '/' )  {
            $path = $path.'/';
        }

        if($use_native_separator && DIRECTORY_SEPARATOR!='/'){
            $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
        }

        return $path;
    }

    //
    //
    //
    public static function sanitizeURL($url){
        if($url!=null && trim($url)!=''){
            $url = filter_var($url, FILTER_SANITIZE_URL);
            if(filter_var($url, FILTER_VALIDATE_URL)){
                return $url;
            }
        }
        return null;
    }

    //
    // We can also use HTMLPurifier (see example in ReportExecute.php)
    //
    public static function sanitizeString($message, $allowed_tags=null, $allowed_entities=true){
        if($message==null){
            $message = '';
        }else{
            if($allowed_tags==null) {
                $allowed_tags = '<a><u><i><em><b><strong><sup><sub><small><br><h1><h2><h3><h4><h5><h6><p><ul><li><img><blockquote><pre><span>';
            }elseif($allowed_tags===false){
                $allowed_tags = null;
            }

            $message = strip_tags($message, $allowed_tags);
            if($allowed_tags!=null){
                // remove attributes except img.src and a.href a.target
                //$message = preg_replace("/<([a-z][a-z0-9]*)[^>]*?(\/?)>/si",'<$1$2>', $message);//remove all attributes

                //$clean = preg_replace("/\n(<[^ai]([\w\d]+)?).+/i","\n$1>",$clean);
                //$clean = preg_replace("/<a.+href='([:\w\d#\/\-\.]+)'.+/i","<a href=\"$1\">",$clean);
                //$clean = preg_replace("/<img.+src='([\w\d_:?.\/%=\-]+)'.+/i","<img src=\"$1\">",$clean);
            }

            $message = htmlspecialchars($message, ENT_NOQUOTES);
            if($allowed_tags!==false){
                $message = str_replace('&lt;', '<', $message);
                $message = str_replace('&gt;', '>', $message);
            }

            if($allowed_entities){
                $message = mb_ereg_replace_callback("&amp;([a-zA-Z]{2,35}|#[0-9]{1,6}|#x[a-fA-F0-9]{1,6});", function($matches){
                    return "&{$matches[1]};";
                }, $message);
            }
        }
        return $message;
    }

    //
    //
    //
    public static function stripScriptTagInRequest(&$params){

        foreach($params as $k => $v)
        {
            if($v!=null){

                if(!isEmptyArray($v)){
                    USanitize::stripScriptTagInRequest($v);
                }else{
                    $v = trim($v);//so we are sure it is whitespace free at both ends

                    //remove script tag
                    $v = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $v);

                }
                $params[$k] = $v;
            }
        }//for
    }

    //
    //
    //
    public static function getHTMLPurifier(){

            $config = \HTMLPurifier_Config::createDefault();

            $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
            
            //reason: Warning: Due to a documentation error in previous version of HTML Purifier, your definitions are not being cached. If this is OK, you can remove the %$type.DefinitionRev and %$type.DefinitionID declaration. Otherwise, modify your code to use maybeGetRawDefinition, and test if the returned value is null before making any edits (if it is null, that means that a cached version is available, and no raw operations are necessary). 
            //$config->set('HTML.DefinitionID', 'html5-definitions');// unqiue id
            //$config->set('HTML.DefinitionRev', 1);

            $config->set('Cache.SerializerPath', HEURIST_SCRATCHSPACE_DIR);
            //$config->set('Core.EscapeNonASCIICharacters', true);
            $config->set('CSS.AllowImportant', true);
            $config->set('CSS.AllowTricky', true);//allow css
            $config->set('CSS.Proprietary', true);
            $config->set('CSS.Trusted', true);

            $config->set('Attr.AllowedFrameTargets','_blank');
            $config->set('HTML.SafeIframe', true);
            /*$config->set('Core.AcceptFullDocuments',false);
            $config->set('Core.HiddenElements',array (
                    'script' => true,
                    'style' => false,
                    'head' => false,
                    ));
            $config->set('HTML.Trusted', true);
            $config->set('HTML.Allowed', array('head'=>true,'style'=>true));
            $config->set('HTML.AllowedElements', array('head'=>true,'style'=>true));
            */
            $def = $config->getHTMLDefinition(true);//non standard attributes
            $def->addAttribute('div', 'id', 'Text');
            $def->addAttribute('img', 'data-id', 'Text');
            $def->addAttribute('div', 'data-heurist-app-id', 'Text');
            $def->addAttribute('div', 'data-inited', 'Text');
            $def->addAttribute('a', 'data-ref', 'Text');

            return new \HTMLPurifier($config);

    }

    //
    // It is used in mail and cms
    //
    // $params - object or array to purify
    //
    public static function purifyHTML(&$params, $purifier = null){

        if($purifier==null){
            if(self::$purifier==null){
               self::$purifier = USanitize::getHTMLPurifier();
            }
            $purifier = self::$purifier;
        }

        if(is_array($params)){

            foreach($params as $k => $v)
            {
                if($v!=null){

                    if(isEmptyArray($v)){
                        $v = $purifier->purify($v);
                        //$v = htmlspecialchars_decode($v);
                    }else{
                        USanitize::purifyHTML($v, $purifier);
                    }
                    $params[$k] = $v;
                }
            }//for
        }else{
            $params = $purifier->purify($params);
        }
    }

    //
    //
    //
    public static function sanitizeFileName($filename, $beautify=true) {
        // sanitize filename
        if($filename!=null){
    //            [\x7F\xA0\xAD]|          # non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN - removed since it brokes utf-8 characters
    
    //            [#\[\]@!$&\'+,;=()]|     # URI reserved https://tools.ietf.org/html/rfc3986#section-2.2
    //            [{}^\~`]                 # URL unsafe characters https://www.ietf.org/rfc/rfc1738.txt

            $filename = preg_replace(
                '~
                [\x00-\x1F]|             # control characters http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
                [<>:"/\\|?*]             # file system reserved https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
                ~x',
                '-', $filename);
            // avoids ".", ".." or ".hiddenFiles"
            $filename = ltrim($filename, '.-');
            // optional beautification
            if ($beautify) {$filename = USanitize::fileNameBeautify($filename);}
            // maximize filename length to 255 bytes http://serverfault.com/a/9548/44086
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $filename = mb_strcut(pathinfo($filename, PATHINFO_FILENAME), 0, 255 - ($ext ? strlen($ext) + 1 : 0), mb_detect_encoding($filename)) . ($ext ? '.' . $ext : '');
        }
        return $filename;
    }

    //
    //
    //
    protected static function fileNameBeautify($filename) {
        // reduce consecutive characters
        $filename = preg_replace(array(
            // "file   name.zip" becomes "file-name.zip"
            '/ +/',
            // "file___name.zip" becomes "file-name.zip"
            '/_+/',
            // "file---name.zip" becomes "file-name.zip"
            '/-+/'
        ), '-', $filename);
        $filename = preg_replace(array(
            // "file--.--.-.--name.zip" becomes "file.name.zip"
            '/-*\.-*/',
            // "file...name..zip" becomes "file.name.zip"
            '/\.{2,}/'
        ), '.', $filename);
        // lowercase for windows/unix interoperability http://support.microsoft.com/kb/100625
        $filename = mb_strtolower($filename, mb_detect_encoding($filename));
        // ".file-name.-" becomes "file-name"
        $filename = trim($filename, '.-');
        return $filename;
    }

    /**
    * Wraps around error_log to prevent log injection
    *
    * @param mixed $message
    */
    public static function errorLog($message){
        $safe_message = preg_replace(REGEX_EOL, ' ', $message);
        error_log($safe_message);
    }

    /**
     * Removes leading, trailing and double (spaces and tabs only) spacing
     *
     * @param mixed $value
     * @return string
     */
    public static function cleanupSpaces($value){

        if(is_string($value)){
            $value = mb_ereg_replace('[ \t]{2,}', ' ', $value);// strip double spaces and tabs
            return function_exists('super_trim') ? super_trim($value) : trim($value);
        }

        if(is_array($value)){ // need to traverse through the array
            foreach($value as $idx => $val){
                $value[$idx] = self::cleanupSpaces($val);
            }
        }

        // else do nothing to avoid errors/faulty data

        return $value;
    }

}
?>
