<?php
namespace hserv\utilities;

/**
* Library to obtain system and php config value
*
* getHostParams
* isMemoryAllowed
* getConfigBytes
* fixIntegerOverflow
* getUserAgent
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

class USystem {

    /**
    * Detects host parameters (base url, server name) or take them from cnfiguration file
    *
    * @param mixed - argumets for cli environment
    *
    * @return array with the following values
    *   server_name - from global $serverName or $_SERVER["SERVER_NAME"]  (heuristref.net:80)
    *   domain      - server_name without port                            (heuristref.net)
    *   server_url  - full server url                                     (https://heuristref.net:80)
    *   heurist_dir - code folder, for cli from getcwd or $_SERVER["DOCUMENT_ROOT"]    (/var/www/html/HEURIST)
    * 
    *   baseURL     - base url ( ie server url+optional folder (https://heuristref.net/h6-alpha/)
    *   baseURL_pro - url for production version  ( https://heuristref.net/heurist/ )
    */
    public static function getHostParams( $argv=null )
    {
        global $serverName, $heuristBaseURL, $heuristBaseURL_pro;

        $host_params = array();

        $localhost = '127.0.0.1';

        $installDir = '';
        $installDir_pro = '';
        $codeFolders = array('heurist','h6-alpha','h6-ao','h6-ij');//need to cli and short url

        if (php_sapi_name() == 'cli'){

            if(!isset($serverName) || !$serverName){
                $serverName = $localhost;
            }

            $k = strpos($serverName,":");
            $host_params['domain'] = ($k>0)?substr($serverName,0,$k-1):$serverName;
            $isSecure = true;

            if($argv==null || !is_array($argv)){
                $sDir = getcwd();
            }else{
                $sDir = dirname(realpath($argv[0]));
            }


            $sDir = str_replace('\\','/',$sDir);

            $iDir = explode('/', $sDir);
            $cntDir = count($iDir)-1;
            $path = null;
            for ($i=$cntDir; $i>=0; $i--){
                if(in_array($iDir[$i], $codeFolders)) {
                    $installDir = '/'.$iDir[$i].'/';
                    $path = array_slice($iDir, 0, $i);
                    break;
                }
            }
            
            $installDir_pro = '/heurist/';
            $host_params['heurist_dir'] = implode('/',$path).'/';
            $host_params['server_name'] = $serverName;

            //echo "Install dir      $installDir \n";
            //echo "3>>> ".$host_params['heurist_dir']."\n";

        }else{

            // server name or IP address of your Web server, null will pull SERVER_NAME from the request header
            $always_detect = true;
            if ($always_detect){ //always detect dynamically  !@$serverName) {
                if(@$_SERVER["SERVER_NAME"]){

                    $host_params['server_name'] = $_SERVER["SERVER_NAME"] .
                    ((is_numeric(@$_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443")
                        ? ":" . $_SERVER["SERVER_PORT"] : "");
                    $host_params['domain'] = $_SERVER["SERVER_NAME"];
                }else{
                    $host_params['server_name'] = $localhost;
                    $host_params['domain'] = $localhost;
                }
                
            }else{
                $k = strpos($serverName,":");
                $host_params['domain'] = ($k>0)?substr($serverName,0,$k-1):$serverName;
                $host_params['server_name'] = $serverName;
            }
           
            $dir = realpath(dirname(__FILE__).'/../../'); //@$_SERVER["DOCUMENT_ROOT"];
            $dir = str_replace('\\', '/', $dir);
            if( substr($dir, -1, 1) != '/' )  {
                $dir .= '/';
            }            
            $host_params['heurist_dir'] = $dir;

            $isSecure = false;
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
                $isSecure = true;
            }
            elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
                $isSecure = true;
            }
            
            if(!isset($heuristBaseURL)){
                //try to detect installation and production folders
                list($installDir, $installDir_pro) = USystem::detectInstalltionDir();
            }
        }

        $host_params['server_url'] = ($isSecure ? 'https' : 'http') . "://" . $host_params['server_name'];
        
        if(isset($heuristBaseURL)){
            $host_params['baseURL'] = $heuristBaseURL;
            $host_params['baseURL_pro'] = $heuristBaseURL_pro ?? $host_params['baseURL'];
            
            if( substr($host_params['baseURL'], -1, 1) != '/' )  {
                $host_params['baseURL'] .= '/';
            }
            if( substr($host_params['baseURL_pro'], -1, 1) != '/' )  {
                $host_params['baseURL_pro'] .= '/';
            }
            
        }else{
            $host_params['baseURL'] = $host_params['server_url'] . $installDir;
            $host_params['baseURL_pro'] = $host_params['server_url'] . $installDir_pro;
        }
        
        return $host_params;

    }

                
    /**
    * if $heuristBaseURL is not defined in configuration detect installation folder and base url 
    *                             
    */
    private static function detectInstalltionDir(){
        $installDir = '/heurist';
        $is_own_domain = (strpos($_SERVER["SERVER_NAME"],'.huma-num.fr')>0 && $_SERVER["SERVER_NAME"]!='heurist.huma-num.fr');
        if(!$is_own_domain){

            $rewrite_actions = 'website|web|hml|tpl|view|edit|adm';//actions for redirection https://hist/heurist/[dbname]/web/

            if(@$_SERVER["SCRIPT_NAME"] &&
            (substr($_SERVER["SCRIPT_NAME"], -4 ) === '/web' || substr($_SERVER["SCRIPT_NAME"], -8 ) === '/website')){
                $_SERVER["SCRIPT_NAME"] .= '/';//add last slash
            }

            $regex_actions = "/\/([A-Za-z0-9_]+)\/($rewrite_actions)\/.*/";

            $matches = array();
            preg_match($regex_actions, @$_SERVER["SCRIPT_NAME"], $matches);
            if($matches){
                $installDir = preg_replace($regex_actions, '', @$_SERVER["SCRIPT_NAME"]);
            }else{

                // calculate the dir where the Heurist code is installed, for example /h5 or /h5-ij
                // removed root folders: pi|applications|common|search|records|
                $topdirs = 'admin|context_help|export|hapi|hclient|hserv|import|startup|redirects|viewers|help|ext|external';

                $installDir = preg_replace("/\/(" . $topdirs . ")\/.*/", "", @$_SERVER["SCRIPT_NAME"]);// remove "/top level dir" and everything that follows it.
                if ($installDir == @$_SERVER["SCRIPT_NAME"]) { // no top directories in this URI must be a root level script file or blank
                    $installDir = preg_replace("/\/[^\/]*$/", "", @$_SERVER["SCRIPT_NAME"]);// strip away everything past the last slash "/index.php" if it's there
                }

            }

        }

        // this should be the path difference between document root $_SERVER["DOCUMENT_ROOT"] and heurist code root
        if ($installDir == @$_SERVER["SCRIPT_NAME"]) {
            $installDir = '/';
            $installDir_pro = '/';
        }else{
            $installDir = $installDir.'/';

            $iDir = explode('/',$installDir);
            $cntDir = count($iDir)-1;
            for ($i=$cntDir; $i>=0; $i--){
                if($iDir[$i]!='') {
                    $iDir[$i] = 'heurist';
                    break;
                }
            }
            $installDir_pro = implode('/', $iDir);
        }

        //validate
        if(@$_SERVER["DOCUMENT_ROOT"]){
            $codeFolders = array('heurist','h6-alpha','h6-ao');//need to cli and short url

            $i = 0;
            while ($i<=count($codeFolders)) {
                $test_file = @$_SERVER["DOCUMENT_ROOT"].$installDir.'configIni.php';
                if(file_exists($test_file)){
                    if($installDir_pro!=$installDir){
                        $test_file = @$_SERVER["DOCUMENT_ROOT"].$installDir_pro.'configIni.php';
                        if(!file_exists($test_file)){
                            $installDir_pro = $installDir;
                        }
                    }
                    break;
                }
                if($i==count($codeFolders)){
                    exit('Sorry, it is not possible to detect heurist installation folder. '
                        .'Please ask system administrator to verify server configuration.');
                }
                $installDir = '/'.$codeFolders[$i].'/';
                $i++;
            }
        }
        
        return array($installDir, $installDir_pro);
    }

    /**
    * Returns true if specified bytes can be loaded into memory
    *
    * @param mixed $memoryNeeded
    */
    public static function isMemoryAllowed( $memoryNeeded ){

        $mem_limit = self::getConfigBytes('memory_limit');
        $mem_usage = memory_get_usage();

        if ($mem_usage+$memoryNeeded > $mem_limit - 10485760){
            return 'It requires '.((int)($memoryNeeded/1024/1024)).
            ' Mb.  Available '.((int)($mem_limit/1024/1024)).' Mb';
        }else{
            return true;
        }
    }

    /**
    * Return amount of bytes for given php config variable
    *
    * @param mixed $php_var
    */
    public static function getConfigBytes( $php_var, $val=null ){

        if($val==null){
            $val = ini_get($php_var);
        }
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);

        if($last){
            $val = intval(substr($val,0,strlen($val)-1));
        }

        switch($last) {
            case 'g':
                $val *= 1073741824; break;
            case 'm':
                $val *= 1048576; break;
            case 'k':
                $val *= 1024; break;
            default;
        }
        return self::fixIntegerOverflow($val);
    }


    // Fix for overflowing signed 32 bit integers,
    // works for sizes up to 2^32-1 bytes (4 GiB - 1):
    public static function fixIntegerOverflow($size) {
        if ($size < 0) {
            $size += 2.0 * (PHP_INT_MAX + 1);
        }
        return $size;
    }

    /**
     * Return array of processed user agent details
     *
     * @return array [os, browser]
     */
    public static function getUserAgent(){

        $os = 'Unknown';
        $browser = 'Unknown';

        $ret = [
            'os' => $os,
            'browser' => $browser
        ];
        $ua_string = @$_SERVER['HTTP_USER_AGENT'];

        if(empty($ua_string)){
            return $ret;
        }

        // Get OS
        if(preg_match("/Android|ADR/i", $ua_string)){
            $os = 'Android';
        }elseif(preg_match("/CrOS/i", $ua_string)){
            $os = 'Chrome OS';
        }elseif(preg_match("/Linux/i", $ua_string)){
            $os = 'Linux';
        }elseif(preg_match("/Unix/i", $ua_string)){
            $os = 'Unix';
        }elseif(preg_match("/Win/i", $ua_string)){
            $os = 'Windows';
            // Check for version number
            preg_match("/Windows NT (\d+\.\d+)/i", $ua_string, $parts);
            if(count($parts) > 1){
                if($parts[1] == 10.0){ $os .= " 10/11";}
                elseif($parts[1] >= 6.4){ $os .= " 10";}
                elseif($parts[1] >= 6.2){ $os .= " 8";}
                elseif($parts[1] >= 6.1){ $os .= " 7";}
            }
        }elseif(preg_match("/CPU (iPhone )?OS/i", $ua_string)){
            $os = 'iOS';
        }elseif(preg_match("/Mac/i", $ua_string) || preg_match("/Darwin/i", $ua_string)){
            $os = preg_match("/Darwin/i", $ua_string) ? 'Mac OS X' : 'macOS';
        }
        /*
        elseif(preg_match("/Googlebot/i", $ua_string)){
            $os = 'Google bot';
        }elseif(preg_match("/Yahoo\! Slurp/i", $ua_string)){
            $os = 'Yahoo bot';
        }elseif(preg_match("/bingbot/i", $ua_string)){
            $os = 'Bing bot';
        }
        */

        $ret['os'] = $os;

        // Get browser
        if(preg_match("/Firefox|FxiOS/i", $ua_string)){
            $browser = preg_match("/FxiOS/", $ua_string) ? 'Firefox iOS' : 'Firefox';
        }elseif(preg_match("/Opera|OPR/i", $ua_string)){
            $browser = 'Opera';
        }elseif(preg_match("/Edge|Edg|EdgA|EdgiOS/i", $ua_string)){
            $browser = preg_match("/EdgA/", $ua_string) ? 'MS Edge Android' : 'MS Edge';
            $browser = preg_match("/EdgiOS/", $ua_string) ? 'MS Edge iOS' : $browser;
        }elseif(preg_match("/Vivaldi/i", $ua_string)){
            $browser = 'Vivaldi';
        }elseif(preg_match("/YaBrowser/i", $ua_string)){
            $browser = 'Yandex';
        }elseif(preg_match("/Chrome|CriOS/i", $ua_string)){
            $browser = preg_match("/CriOS/", $ua_string) ? 'Chrome iOS' : 'Chrome';
        }elseif(preg_match("/Safari/i", $ua_string)){
            $browser = 'Safari';
        }
        /*
        elseif(preg_match("/MSIE|Trident/i", $ua_string)){
            $browser = 'Internet Explorer';
        }
        */

        $ret['browser'] = $browser;

        return $ret;
    }

    //
    //host organization logo and url (specified in root installation folder next to heuristConfigIni.php)
    //
    public static function getHostLogoAndUrl($return_url = true){

        //host organization logo and url (specified in root installation folder next to heuristConfigIni.php)
        $host_logo = realpath(dirname(__FILE__)."/../../../organisation_logo.jpg");
        $mime_type = 'jpg';
        if(!$host_logo || !file_exists($host_logo)){
            $host_logo = realpath(dirname(__FILE__)."/../../../organisation_logo.png");
            $mime_type = 'png';
        }
        $host_url = null;
        if($host_logo!==false && file_exists($host_logo)){

            !$return_url || $host_logo = defined('HEURIST_BASE_URL') ? HEURIST_BASE_URL.'?logo=host' : null;

            $host_url = realpath(dirname(__FILE__)."/../../../organisation_url.txt");
            if($host_url!==false && file_exists($host_url)){
                $host_url = file_get_contents($host_url);
            }else{
                $host_url = null;
            }
        }else{
            $host_logo = null;
        }

        return array($host_logo, $host_url, $mime_type);
    }

    //======================= session routines =================================
    //
    //
    // Retruns array of database where current user was logged in
    //
    public static function sessionRecentDatabases($current_User){
        $dbrecent = array();
        if($current_User && @$current_User['ugr_ID']>0){
            foreach ($_SESSION as $db=>$session){

                $user_id = @$_SESSION[$db]['ugr_ID'];
                if($user_id == $current_User['ugr_ID']){
                    if(strpos($db, HEURIST_DB_PREFIX)===0){
                        $db = substr($db,strlen(HEURIST_DB_PREFIX));
                    }
                    array_push($dbrecent, $db);
                }
            }
        }
        return $dbrecent;
    }

    //
    //
    //
    public static function sessionCheckFolder(){

        if(!ini_get('session.save_handler')=='files') { return true; }

        $folder = session_save_path();
        if(file_exists($folder) && is_writeable($folder)){ return true; }

        sendEmailToAdmin('Session folder access', 'The sessions folder has become inaccessible', true);

        return false;
    }

    //
    //
    //
    public static function sessionUpdateCookies($lifetime=null){

        $is_https = (@$_SERVER['HTTPS']!=null && $_SERVER['HTTPS']!='');

        //update cookie - to keep it alive for next 30 days
        if($lifetime==null){
                $lifetime = time() + 30*24*60*60;
        }

        $session_id = session_id(); //ID of current session $cookie_session_id

        if (strnatcmp(phpversion(), '7.3') >= 0) {
            $cres = setcookie('heurist-sessionid', $session_id, array(
                'expires' => $lifetime,
                'path' => '/',
                'domain' => '',
                'Secure' => $is_https,
                'HttpOnly' => true,
                'SameSite' => 'Strict' //'Lax'
            ));
        }else{
            //workaround: header("Set-Cookie: key=value; path=/; domain=example.org; HttpOnly; SameSite=Lax")
            $cres = setcookie('heurist-sessionid', $session_id, $lifetime, '/', '', $is_https, true );
        }

        return $cres;
    }



}
?>
