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

        $serverUrl = ($isSecure ? 'https' : 'http') . "://" . $host_params['server_name'];

        if(isset($heuristBaseURL)){
            $baseUrl = $heuristBaseURL;
            $baseUrl_pro = $heuristBaseURL_pro ?? $heuristBaseURL;

            if(strpos($baseUrl, $serverUrl)===false){ //alpha version is on different domain
                $baseUrl = $baseUrl_pro;
            }
            if( substr($baseUrl, -1, 1) != '/' )  {
                $baseUrl .= '/';
            }
            if( substr($baseUrl_pro, -1, 1) != '/' )  {
                $baseUrl_pro .= '/';
            }

        }else{
            //for auto detect both alpha and pro version must be on the same domain
            $baseUrl = $serverUrl . $installDir;
            $baseUrl_pro = $serverUrl . $installDir_pro;
        }

        $host_params['server_url']  = $serverUrl;
        $host_params['baseURL']     = $baseUrl;
        $host_params['baseURL_pro'] = $baseUrl_pro;

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


    //======================= daily actions =================================
    //
    //
    //
    public static function executeScriptOncePerDay(){

        $now = getNow();
        $flag_file = HEURIST_FILESTORE_ROOT.'flag_'.$now->format('Y-m-d');

        if(file_exists($flag_file)){
            return;
        }

        file_put_contents($flag_file,'1');

        //remove flag files for previous days
        for($i=1;$i<10;$i++){
            $d = getNow();
            $yesterday = $d->sub(new \DateInterval('P'.sprintf('%02d', $i).'D'));
            $arc_flagfile = HEURIST_FILESTORE_ROOT.'flag_'.$yesterday->format('Y-m-d');
            //if yesterday log file exists
            if(file_exists($arc_flagfile)){
                unlink($arc_flagfile);
            }
        }

        //add functions for other daily tasks
        self::sendDailyErrorReport();
        self::heuristVersionCheck();// Check if different local and server code versions are different
        self::updateDeeplLanguages();// Get list of allowed target languages from Deepl API

    }

    //
    //
    //
    private static function sendDailyErrorReport(){

        $root_folder = HEURIST_FILESTORE_ROOT;
        
        $archiveFolder = $root_folder."AAA_LOGS/";
        $logs_to_be_emailed = array();
        $y1 = null;
        $y2 = null;

        //1. check if log files for previous 30 days exist
        for($i=1;$i<31;$i++){
            $now = getNow();
            $yesterday = $now->sub(new \DateInterval('P'.sprintf('%02d', $i).'D'));
            $arc_logfile = 'errors_'.$yesterday->format('Y-m-d').'.log';
            //if yesterday log file exists
            if(file_exists($root_folder.$arc_logfile)){
                //2. copy to log archive folder
                fileCopy($root_folder.$arc_logfile, $archiveFolder.$arc_logfile);
                unlink($root_folder.$arc_logfile);

                $logs_to_be_emailed[] = $archiveFolder.$arc_logfile;

                $y2 = $yesterday->format('Y-m-d');
                if($y1==null) {$y1 = $y2;}
            }
        }

        if(!empty($logs_to_be_emailed)){

            $msgTitle = 'Error report '.HEURIST_SERVER_NAME.' for '.$y1.($y2==$y1?'':(' ~ '.$y2));
            $msg = $msgTitle;
            foreach($logs_to_be_emailed as $log_file){
                $msg = $msg.'<br>'.file_get_contents($log_file);
            }
            //'Bug reporter',
            sendEmail(HEURIST_MAIL_TO_BUG, $msgTitle, $msg, true);
        }


    }

    //
    // Send email to system admin about available Heurist updates, daily tasks
    //
    private static function heuristVersionCheck(){

        $local_ver = HEURIST_VERSION; // installed heurist version

        // attempt to get release version
        $server_ver = USystem::getLastCodeAndDbVersion();

        if($server_ver == "unknown"){
            error_log("Unable to retrieve Heurist server version, this maybe due to the main server being un-available. If this problem persists please contact the Heurist team.");
            return;
        }

        $local_parts = explode('.', $local_ver);
        $server_parts = explode('.', $server_ver);

        for($i = 0; $i < count($server_parts); $i++){

            if($server_parts[$i] == $local_parts[$i]){
                continue;
            }

            if($server_parts[$i] > $local_parts[$i]){ // main release is newer than installed version, send email

                $title = "Heurist version " . htmlspecialchars($local_ver)
                . " at " . HEURIST_BASE_URL . " is behind Heurist home server";

                $msg = 'Heurist on the referenced server is running version '
                . " $local_ver which can be upgraded to the newer $server_ver<br><br>"
                . 'Please check for an update package at <a href="https://heuristnetwork.org/installation/">https://heuristnetwork.org/installation/</a><br><br>'
                . 'Update packages reflect the alpha version and install in parallel with existing versions'
                . ' so you may test them before full adoption. We recommend use of the alpha package'
                . ' by any confident user, as they bring bug-fixes, cosmetic improvements and new'
                . ' features. They are safe to use and we will respond repidly to any reported bugs.';

                //Update notification
                sendEmail(HEURIST_MAIL_TO_ADMIN, $title, $msg, true);
            }
            //else main release is less than installed version, maybe missed alpha or developemental version

            break;
        }//for
    }

    /**
    * Get and save list of available languages from Deepl API
    * Saved to FILESTORE_ROOT/DEEPL_languages.json
    */
    private static function updateDeeplLanguages(){

        global $accessToken_DeepLAPI;
        if(empty($accessToken_DeepLAPI)){
            return;
        }

        $target_url = 'https://api-free.deepl.com/v2/languages?type=target';

        $language_file = HEURIST_FILESTORE_ROOT . 'DEEPL_languages.json';

        $target_res = loadRemoteURLContentWithRange($target_url, false, true, 60, array('Authorization: DeepL-Auth-Key ' . $accessToken_DeepLAPI));

        $target_languages = array();

        if(!empty($target_res)){

            $target_res = json_decode($target_res, true);
            $target_res = json_last_error() !== JSON_ERROR_NONE ? array() : $target_res;

            // Extra processing needed, some target languages have multiple versions; e.g. ENG-GB and ENG-US
            foreach ($target_res as $lang) {

                $lang_name = $lang['language'];
                if(strpos($lang_name, '-') !== false){
                    $lang_name = explode('-', $lang_name)[0];
                }

                if(array_search($lang_name, $target_languages) !== false){
                    continue;
                }

                array_push($target_languages, $lang_name);
            }
        }

        fileSave(json_encode($target_languages), $language_file);
    }

    /**
    * Checks database version
    * first check version in file lastAdviceSent, version stored in this file valid for 24 hrs
    */
    public static function getLastCodeAndDbVersion(){

        $isAlpha = (preg_match("/h\d+\-alpha|alpha\//", HEURIST_BASE_URL) === 1) ? true :false;

        $version_last_check = 'unknown';
        $need_check_main_server = true;

        $fname = HEURIST_FILESTORE_ROOT."lastAdviceSent.ini";

        $release = ($isAlpha ? 'alpha' : 'stable');

        if (file_exists($fname)){
            //last check and version
            list($date_last_check, $version_last_check, $release_last_check) = explode("|", file_get_contents($fname));

            if($release_last_check && strncmp($release_last_check, $release, strlen($release)) == 0 
                && $date_last_check && strtotime($date_last_check) ){
                    $days = intval((time()-strtotime($date_last_check))/(3600*24));//days since last check

                    if(intval($days)<1){
                        $need_check_main_server = false;
                    }
            }
        }//file exitst     
        
        if(!$need_check_main_server){
            return $version_last_check;
        }

        $rawdata = null;

        //send request to main server at HEURIST_INDEX_BASE_URL
        // HEURIST_INDEX_DATABASE is the refernece standard for current database version
        // Maybe this should be changed to Heurist_Sandpit?. Note: sandpit no longer needed, or used, from late 2015

        if(strpos(strtolower(HEURIST_INDEX_BASE_URL), strtolower(HEURIST_SERVER_URL))===0){ //same domain

            $mysql_indexdb = mysql__init(HEURIST_INDEX_DATABASE);
            $db_version = getDbVersion($mysql_indexdb);
            if($db_version){
                $rawdata = HEURIST_VERSION."|".$db_version;    
            }

        }else{
            $url = ($isAlpha
                ? HEURIST_MAIN_SERVER . '/h6-alpha/'
                : HEURIST_INDEX_BASE_URL)
            . "admin/setup/dbproperties/getCurrentVersion.php?db=".HEURIST_INDEX_DATABASE."&check=1";
            $rawdata = loadRemoteURLContentSpecial($url);//it returns HEURIST_VERSION."|".HEURIST_DBVERSION
        }

        if($rawdata){
            $current_version = explode("|", $rawdata);

            if (!empty($current_version))
            {
                $curver = explode(".", $current_version[0]);
                if( count($curver)>=2
                && intval($curver[0]) > 0
                && is_numeric($curver[1])
                && intval($curver[1])>=0 )
                {
                    $version_last_check = $current_version[0];
                }
            }
        }

        $version_in_session = date("Y-m-d").'|'.$version_last_check.'|'.$release;
        fileSave($version_in_session, $fname);//save last version
    

        return $version_last_check;
    }
    
    //
    // getrusage
    //
    public static function rutime($ru, $rus, $index='utime'){
        return ($ru["ru_$index.tv_sec"]*1000 + intval($ru["ru_$index.tv_usec"]/1000))
        -  ($rus["ru_$index.tv_sec"]*1000 + intval($rus["ru_$index.tv_usec"]/1000));        
    }

    //
    // Functions to check if webstites are dummies or worth referencing
    //

    // Uses cURL to fetch HTML page content.
    function fetchPageContent($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $content = curl_exec($ch);
        curl_close($ch);
        return $content;
    }

    // Extracts links from HTML page using DOMDocument.
    function extractLinks($url, $content) {
        $dom = new DOMDocument();
        @$dom->loadHTML($content);
        $links = [];

        foreach ($dom->getElementsByTagName('a') as $link) {
            $href = $link->getAttribute('href');

            // Convert relative URLs to absolute URLs.
            if (!filter_var($href, FILTER_VALIDATE_URL)) {
                $href = rtrim($url, '/') . '/' . ltrim($href, '/');
            }

            // Add only valid URLs.
            if (filter_var($href, FILTER_VALIDATE_URL)) {
                $links[] = $href;
            }
        }

        return array_unique($links);
    }

    // Crawls the pages of a website from a "startpoint" URL and checks for content.
    function analyzeSite($startUrl) {
        $visited = [];
        $toVisit = [$startUrl];
        $emptyPages = 0;

        while (!empty($toVisit)) {
            $url = array_shift($toVisit);

            if (in_array($url, $visited)) {
                continue;
            }

            echo "Counting characters: $url\n";
            $content = fetchPageContent($url);
            $charCount = strlen(strip_tags($content));

            if ($charCount < 500) {
                $emptyPages++;
            }

            $visited[] = $url;
            $links = extractLinks($url, $content);

            foreach ($links as $link) {
                if (!in_array($link, $visited) && !in_array($link, $toVisit)) {
                    $toVisit[] = $link;
                }
            }
        }

        return $emptyPages;
    }

    // Checks pages for specific dummy strings.
    function isDummy($startUrl) {
        $visited = [];
        $toVisit = [$startUrl];
        $dummyPages = 0;

        while (!empty($toVisit)) {
            $url = array_shift($toVisit);

            if (in_array($url, $visited)) {
                continue;
            }

            echo "Browsing: $url\n";
            $content = fetchPageContent($url);
            $contentAsString = strip_tags($content);

            // Check for specific dummy content markers.
            if (strpos($contentAsString, "UNCONFIGURED TITLE") !== false ||
                strpos($contentAsString, "Use this page to provide contact and location information for the project.") !== false ||
                strpos($contentAsString, "Please retain this page in your websiteE") !== false) {
                $dummyPages++;
            }

            $visited[] = $url;
            $links = extractLinks($url, $content);

            foreach ($links as $link) {
                if (!in_array($link, $visited) && !in_array($link, $toVisit)) {
                    $toVisit[] = $link;
                }
            }
        }

        return $dummyPages;
    }
}

