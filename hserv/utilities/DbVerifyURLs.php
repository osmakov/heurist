<?php
/*
* Copyright (C) 2005-2023 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* DbVerifyURLs.php - class to check and validate URLs from various sources in the database.
*
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/
namespace hserv\utilities;
use hserv\utilities\DbUtils;

define('CURL_RANGE', '0-500');
define('CURL_TIMEOUT', 5); 
define('CURL_ERR', 'Fatal curl error');

/**
 * Class DbVerifyURLs
 * A class to check and validate URLs from various sources in the database.
 */
class DbVerifyURLs {

    /** @var mysqli $mysqli Database connection */
    private $system;
    private $mysqli;

    /** @var string $heuristDomain  domain for this Heurist server  */
    private $heuristDomain;

    /** @var bool $isHeuristReferenceIndex Whether the database is Heurist_Reference_Index */
    private $isHeuristReferenceIndex;

    /** @var array $passedRecIds Array of record IDs that passed URL validation */
    private $passedRecIds = [];


    private $isSession = false;
    private $isTerminated = false;
    
    /** @var bool $isVerbose Whether echo results at once */
    private $isVerbose = false;
    /** @var bool $listOnly Whether to check urls */
    private $listOnly = false;
    /** @var int $maxCountToCheck  */
    private $maxCountToCheck = 150;

    private $checkedCount = 0;

    
    private $context = null;

    private $timeoutDomains = [];
    private $alreadyChecked = [];
    
    private $results;
    

    /**
     * Constructor for DbVerifyURLs.
     *
     * @param mysqli $mysqli
     * @param string $heuristDomain
     * @param bool $isHeuristReferenceIndex
     */
    public function __construct($system, $heuristDomain, $isHeuristReferenceIndex) {
        
        $this->system = $system;
        $this->mysqli = $system->getMysqli();

        $info = parse_url(strtolower($heuristDomain));
        $this->heuristDomain = $info['scheme'].'://'.$info['host'];

        $this->isHeuristReferenceIndex = $isHeuristReferenceIndex;
        
        //ini_set('default_socket_timeout', 10); //default is 60 seconds
    }

    /**
     * Updates the last verified date for the given record IDs.
     *
     * @return void
     */
    private function updateRecordsLastVerified() {
        if(empty($this->passedRecIds)){
            return;
        }
        
        $date = date(DATE_8601);
        $query = 'UPDATE Records SET rec_URLLastVerified="' . $date . '", rec_URLErrorMessage=null WHERE rec_ID IN ('
                            . implode(',', $this->passedRecIds) . ')';
        $this->mysqli->query($query);
        $this->passedRecIds = []; // reset the array
    }
    
    //
    // Returns info about previous or current session
    //
    public function getCurrentSessionInfo(){

        //load previous result
        $this->readResultFile();
        
        if(@$this->results['session_id']>0){
            return array('session_id'=>$this->results['session_id']);
        }elseif(@$this->results['total_checked']>0){
            return array('total_checked'=>$this->results['total_checked'], 'total_bad'=>$this->results['total_bad']);
        }
        
        return array();
    }

    /**
     * Check URLs in various sources and validate them.
     *
     * @param bool $isVerbose echo results at once
     * @param bool $listOnly Only list the URLs, do not perform any validations.
     * @param int $maxCountToCheck
     * @param int $mode - 0 start from last position, 1 check existing bad urls fist, 2 - from scratch
     * @return array Results of the URL validation or boolean.
     */
    public function checkURLs($isVerbose = false, $listOnly = false, $maxCountToCheck=150, $mode=0, $session_id=0) {

        $this->passedRecIds = [];
        $this->timeoutDomains = [];
        $this->alreadyChecked = [];

        //define timeout for get_headers
        $opts['http']['timeout'] = CURL_TIMEOUT;
        $this->context = stream_context_create( $opts );

        $this->isVerbose = $isVerbose || $listOnly;
        $this->listOnly = $listOnly;
        
        $this->maxCountToCheck = intval($maxCountToCheck)??150;
        $this->checkedCount = 0;
        
        //load previous result
        $this->readResultFile($mode==2); //if 2 - reset
        
        //start new session
        $this->isTerminated = false;
        if($session_id>0){
            $this->results['session_id'] = $session_id;
            $this->saveResultFile();
            
            $this->isSession = true;
            DbUtils::setSessionId($session_id);
            ob_start();
        }else{
            $session_id = 0;
        }
        
        //0. Check record URLs
        $res = $this->checkRecordURLs();
        
        //1. Check free text/block text fields for URLs
        if($res && $this->checkedCount<$this->maxCountToCheck && !$this->isTerminated){
            $res = $this->checkTextFieldURLs();
        }
  
        //2. Check external URLs in use (e.g., file fields)
        if($res && $this->checkedCount<$this->maxCountToCheck && !$this->isTerminated){
            $res = $this->checkExternalFileURLs();
        }
        
        if(!$res){
            //fatal curl error
            $this->results = false;
        }
        
        if(@$this->results['session_id']){
            unset($this->results['session_id']);
            
            if(!isPositiveInt(@$this->results['total_checked'])){
                $this->results['total_checked'] = 0;
            }
            $this->results['session_checked'] = $this->checkedCount;
            $this->results['total_checked'] += $this->checkedCount;
            $this->results['total_bad'] = $this->getTotalBad();
        }
            
        $this->saveResultFile();  
        
        if($res && $this->isSession && $this->isVerbose){
            
            $this->results['output'] = ob_get_clean();
        }        
          
        return $this->results;
    }

    //
    //
    //
    private function getTotalBad(){
        
        $this->results['record_bad'] = count($this->results['record']);
        
        foreach(array('text','file') as $key){
            $this->results[$key.'_bad'] = 0;
            if(array_key_exists($key, $this->results) && is_array($this->results[$key])){
                foreach($this->results[$key] as $recId=>$fields){
                    foreach($fields as $detailTypeId=>$urls){
                        if(is_array($urls)){
                            $this->results[$key.'_bad'] = $this->results[$key.'_bad'] + count($urls);
                        }
                    }
                }
            }
        }
        $total = $this->results['record_bad'] + $this->results['file_bad'] + $this->results['text_bad'];
        return $total;
    }

    //
    //
    //
    public function outputSummaryInfoAsCSV(){
        
        $this->readResultFile();
        
        $csv_delimiter = ',';
        $csv_enclosure = '"';
        
        $fd = fopen(TEMP_MEMORY, 'w');//less than 1MB in memory otherwise as temp file
        fputcsv($fd, array('rec_ID','dty_ID','URL'), $csv_delimiter, $csv_enclosure);
        
        
        foreach($this->results['record'] as $recId=>$url){
            fputcsv($fd, array($recId, 0, $url), $csv_delimiter, $csv_enclosure);
        }

        foreach(array('text','file') as $key){
            if(array_key_exists($key, $this->results) && is_array($this->results[$key])){
                foreach($this->results[$key] as $recId=>$fields){
                    foreach($fields as $detailTypeId=>$urls){
                        foreach($urls as $url){
                            fputcsv($fd, array($recId, $detailTypeId, $url), $csv_delimiter, $csv_enclosure);    
                        }
                    }
                }
            }
        }

        rewind($fd);
        $res = stream_get_contents($fd);
        fclose($fd);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=bad_urls.csv');
        header(CONTENT_LENGTH . strlen($res));
        exit($res);
    }
    
    
    //
    //
    //
    private function readResultFile($isReset = false)
    {
        $this->results = $this->system->settings->getDatabaseSetting('Invalid URLs');
        if($isReset || !$this->results){
            $this->results = [];
        }

/*
    'record' => [],  // Broken record URLs   $rec_id => $url
    'text' => [],    // Broken free text/block text URLs  rec_id=>(dty_id=>url)
    'file' => [],    // Broken external file URLs
    'curl' => ''     //fatal CURL error message - not used
*/        
        foreach(array('record','text','file') as $key){
            $this->results['session_processed_'.$key] = 0;
            $this->results['session_bad_'.$key] = 0;
            if(!array_key_exists($key, $this->results)){
                $this->results[$key] = [];
            }
        }
        $this->results['curl'] = [];
    }

    private function saveResultFile(){
        $this->system->settings->setDatabaseSetting('Invalid URLs', $this->results?$this->results:[]);
    }
        
    //
    //
    //
    private function printHeader($sHeader){
        if($this->isVerbose){
            print $sHeader;
        }
    }

    private function printFooter($broken_cnt, $passed_cnt, $sMsg, $sMsg2='', $timestart=0){
        if($this->isVerbose){

            print "<p>$sMsg. Processed: $passed_cnt</p>";

            if($broken_cnt>0){
                print "<div style=\"padding-top:20px;color:red\">There are <b>$broken_cnt</b> broken urls. $sMsg2</div>";
            }else{
                print '<div>OK: All URLs are valid</div>';
            }

            if($timestart>0){
                print '<p>total time:    '.round(microtime(true) - $timestart).'</p>';
            }

        }
    }

    private function isReferenceDatabase( $recTypeId ){
        return $this->isHeuristReferenceIndex && ($recTypeId == 101 || $recTypeId == 103);
    }
    
    private function updateSessionProgress($counter){
        if($this->isSession){
            if(DbUtils::setSessionVal($counter)){
                //terminated by user
                $this->system->addError(HEURIST_ACTION_BLOCKED, 'URLs Verification has been terminated by user');
                $this->isTerminated = true;
                return true;
            }
        }
        return false;        
    }

    //
    //
    //
    private function errorMsg($errMsg){
            if($this->isVerbose){
                print errorDiv($errMsg);
            }else{
                $this->system->addError(HEURIST_ACTION_BLOCKED, $errMsg);
            }
    }
    
    
    /**
     * Check URLs from records and validate.
     *
     * @return void
     */
    private function checkRecordURLs() {
        $query = 'SELECT rec_ID, rec_URL, rec_RecTypeID FROM Records WHERE (rec_URL != "") AND (rec_URL IS NOT NULL)';
        
        if(!$this->listOnly){
            if(@$this->results['ts_record']){
                $query = $query.' AND rec_Modified>"'.$this->results['ts_record'].'"';    
            }
            $query = $query.' ORDER BY rec_Modified';
            if($this->maxCountToCheck>0){
                $query = $query.' LIMIT '.$this->maxCountToCheck;
            }
        }
        
        $res = $this->mysqli->query($query);

        $this->printHeader('<hr><h4>Records URLs</h4>');

        if (!$res) {
            $this->errorMsg('Cant retrieve records with URLs: '.$this->mysqli->error);
            return false;
        }

        $timestart = microtime(true);
        $passed_cnt = 0;

        while ($row = $res->fetch_row()) {
            $recId = $row[0];
            $recUrl = $row[1];
            $recTypeId = $row[2];

            // Modify URL for Heurist Reference Index if necessary
            $isReferenceDatabase = $this->isReferenceDatabase($recTypeId);
            if ($isReferenceDatabase) {
                $recUrl .= '&isalive=1';
            }

            // Skip URLs that match current Heurist server URL
            if (strpos(strtolower($recUrl), $this->heuristDomain) === 0) {
                continue;
            }

            // Handle listing URLs without validation
            if ($this->listOnly) {
                $recUrl = htmlentities($recUrl);
                print intval($recId) . " : <a href=\"$recUrl\" target=\"_blank\" rel=\"noopener\">$recUrl</a><br>";
                continue;
            }
            
            
            if(@$this->results['record'][$recId]==$recUrl){
                //already in broken list - skip
                continue;
            }

            // Validate the URL
            $error_msg = $this->checkRemoteURL($recUrl, $isReferenceDatabase);

            if($error_msg==null){
                //passed
                $this->handleRecordUrl($recId, $recUrl, $data, $isReferenceDatabase);
            }elseif($this->handleBrokenRecordUrl($recId, $recUrl, $error_msg)){ //exit on curl error
                return false;
            }
            
            $this->checkedCount++;
            $passed_cnt++;
            if($this->checkedCount >= $this->maxCountToCheck ||
               $this->updateSessionProgress($this->checkedCount.','.$this->maxCountToCheck))
            {
                break;                
            }
        } //while
        $res->close();


        $this->updateRecordsLastVerified();

        $this->results['ts_record'] = (new \DateTime())->format(DATE_8601);
        $this->results['session_processed_record'] = $passed_cnt;
        $this->results['session_bad_record'] = count($this->results['record']);
   
        $broken_cnt = count($this->results['record']);
        $this->printFooter($broken_cnt, $passed_cnt, 'Record URLs', 'Search "_BROKEN_" for details', $timestart);
        
        return true;
    }

    /**
    * Helper function
    *
    * @param mixed $recId
    * @param mixed $recUrl
    * @param mixed $data
    */
    private function handleRecordUrl($recId, $recUrl, $data, $isReferenceDatabase){

        //special case for reference index database
        if($isReferenceDatabase && strpos($data, 'error: ')===0 && $this->isVerbose){

            $recUrl = htmlspecialchars($recUrl);
            print intval($recId)
            ." : <a href=\"$recUrl\" target=\"_blank\" rel=\"noopener\">$recUrl</a>";

            $data = strpos($data, 'timeout') !== false ? 'Timeout occurred' : $data;
            $data = strpos($data, 'does not exist') !== false ? 'Database does not exist' : $data;

            print errorDiv(htmlspecialchars($data));
        }else{
            if(array_key_exists($recId, $this->results['record'])){
                unset($this->results['record'][$recId]);
            }
            $this->passedRecIds[] = $recId;
            if (count($this->passedRecIds) > 1000) {
                $this->updateRecordsLastVerified();
            }
        }
        
    }

    /**
     * Check free text and block text fields for URLs.
     *
     * @return void
     */
    private function checkTextFieldURLs() {
        $query = 'SELECT dtl_RecID, dtl_Value, dtl_DetailTypeID, dtl_ID FROM recDetails ' .
            'INNER JOIN defDetailTypes ON dty_ID = dtl_DetailTypeID ' .
            'INNER JOIN Records ON rec_ID = dtl_RecID ' .
            'WHERE (dty_Type = "freetext" OR dty_Type = "blocktext") AND dtl_Value REGEXP "https?://"';
            
            
        if(!$this->listOnly){
            if(@$this->results['ts_text']>0){
                $query = $query.' AND dtl_ID > '.intval($this->results['ts_text']);    
            }
            $query = $query.' ORDER BY dtl_ID';
            if($this->maxCountToCheck>0){
                $query = $query.' LIMIT '.$this->maxCountToCheck;
            }
        }
            

        $res = $this->mysqli->query($query);

        $this->printHeader('<hr><h4>URLs in text fields</h4>');

        if (!$res) {
            $this->errorMsg('Cant retrieve records with URLs in text fields: '.$this->mysqli->error);
            return false;
        }

        $timestart = microtime(true);


        $this->results['session_bad_text'] = 0;
        $passed_cnt = 0;
        
        while ($row = $res->fetch_row()) {
            $recId = $row[0];
            $value = $row[1];
            $detailTypeId = $row[2];
            $dtlID = $row[3];

            $urls = [];
            preg_match_all("/https?:\/\/[^\s\"'>()\\\\]*/", $value, $urls);
            $urls = $urls[0] ?? [];

            foreach ($urls as $url) {
                
                if($this->skipFieldUrl($recId, $url, $detailTypeId, 'text')){
                    continue;
                }
                
                if($this->validateAndHandleFieldUrl($recId, $url, $detailTypeId, 'text')){
                    return false; //fatal error
                }
                
                $this->results['ts_text'] = $dtlID;
                $passed_cnt++;
                $this->checkedCount++;
            }
            
            if($this->checkedCount >= $this->maxCountToCheck ||
                   $this->updateSessionProgress($this->checkedCount.','.$this->maxCountToCheck))
            {
                break;                
            }
        }
        $res->close();

        $broken_cnt = count($this->results['text']);
        $this->results['session_processed_text'] = $passed_cnt;
        $this->printFooter($this->results['session_bad_text'], $passed_cnt, 'Text fields with URLs', '', $timestart);

        return true;
    }

    /**
     * Check external file URLs in use.
     *
     * @return void
     */
    private function checkExternalFileURLs() {
        $query = 'SELECT dtl_RecID, ulf_ExternalFileReference, dtl_DetailTypeID, dtl_ID FROM recDetails ' .
            'INNER JOIN defDetailTypes ON dty_ID = dtl_DetailTypeID ' .
            'INNER JOIN recUploadedFiles ON ulf_ID = dtl_UploadedFileID ' .
            'WHERE dty_Type = "file" AND ulf_ExternalFileReference != "" AND ulf_OrigFileName="_remote"';

        if(!$this->listOnly){
            if(@$this->results['ts_file']>0){
                $query = $query.' AND dtl_ID > '.intval($this->results['ts_file']);    
            }
            $query = $query.' ORDER BY dtl_ID';
            if($this->maxCountToCheck>0){
                $query = $query.' LIMIT '.$this->maxCountToCheck;
            }
        }
            
        $res = $this->mysqli->query($query);

        $this->printHeader('<hr><h4>External URLs (File fields)</h4>');

        if (!$res) {
            $this->errorMsg('Cant retrieve records with URLs in text fields: '.$this->mysqli->error);
            return false;
        }

        $timestart = microtime(true);
        $this->results['session_bad_file'] = 0;
        $passed_cnt = 0;
        while ($row = $res->fetch_row()) {
            $recId = $row[0];
            $url = $row[1];
            $detailTypeId = $row[2];
            $dtlID = $row[3];

            if($this->skipFieldUrl($recId, $url, $detailTypeId, 'file')){
                continue;
            }
            
            if($this->validateAndHandleFieldUrl($recId, $url, $detailTypeId, 'file')){
                return false; //fatal error
            }
            
            $this->results['ts_file'] = $dtlID;
            $passed_cnt++;
            $this->checkedCount++;
            if($this->checkedCount >= $this->maxCountToCheck ||
               $this->updateSessionProgress($this->checkedCount.','.$this->maxCountToCheck))
            {
                break;                
            }
        }
        $res->close();

        $this->results['session_processed_file'] = $passed_cnt;
        $this->printFooter($this->results['session_bad_file'], $passed_cnt, 'External URLs (File fields)', '', $timestart);
        
        return true;
    }


    //
    // not used
    //
    private function loadRemoteURLContent($url) {
        global $glb_curl_error, $glb_curl_code;

        $exists = loadRemoteURLContentWithRange($url, CURL_RANGE, true, CURL_TIMEOUT);

        if(!$exists){
            if ($glb_curl_code == HEURIST_SYSTEM_FATAL) {
                if ($this->isVerbose) {
                    print errorDiv( htmlspecialchars($glb_curl_error) );
                }
                $this->results['curl'] = $glb_curl_error;
                $glb_curl_error = CURL_ERR.' '.$glb_curl_error;
            }
            return $glb_curl_error ?? 'Unknown error';
        }

        return null;

    }

    /**
     * Loads the content from a remote URL.
     *
     * @param string $url
     * @return mixed null if success or error message
     */
    private function checkRemoteURL($url, $use_curl=false) {

        $lurl = strtolower($url);

        $info = parse_url($lurl);
        if(strpos($info['scheme'],'http')!==0
          ||
          in_array($lurl, $this->alreadyChecked))
        {
            return null;
        }

        foreach($this->timeoutDomains as $bad_domain){
            if(strpos(strtolower($lurl), $bad_domain) === 0){
                return 'Check skipped (it was timeout previously)';
            }
        }

        $this->alreadyChecked[] = $lurl;

        //if($use_curl){
        //    return $this->loadRemoteURLContent($url);
        //}

        //use get_headers ------------------
        //check takes around one second
        // in case of DNS issue it may take 20 seconds - we reduce timeout to 5 seconds
        $error_msg = null;

        $file_headers = @get_headers($url, 0, $this->context);
        if(!$file_headers){
            $exists = false;

            $this->timeoutDomains[] = strtolower($info['scheme'].'://'.$info['host']);

            $error_msg = 'Timeout out';

        }elseif($file_headers[0] == 'HTTP/1.1 404 Not Found') {
            $error_msg = 'Not found (Error 404)';
            //HTTP/1.1 200 OK
        }

        //cooldown 2 seconds
        sleep(2);

        return $error_msg;
    }

    /**
     * Handles the case when a record URL is broken.
     *
     * @param int $recId
     * @param string $recUrl
     * @return true in case of fatal CURL error
     */
    private function handleBrokenRecordUrl($recId, $recUrl, $error_msg) {


        if(strpos($error_msg,CURL_ERR)===0){
            $this->results['curl'] = $error_msg;
            $this->errorMsg($error_msg);
            return true;
        }

        $query = 'UPDATE Records SET rec_URLLastVerified=?, rec_URLErrorMessage=? WHERE rec_ID='.intval($recId);
        mysql__exec_param_query($this->mysqli, $query, ['ss', date(DATE_8601), substr($error_msg, 0, 255)], true);

        if ($this->isVerbose) {
            $recUrl = htmlspecialchars($recUrl);
            $error_msg = htmlspecialchars($error_msg);
            print '<div>' . intval($recId) . " : <a href=\"$recUrl\" target=\"_blank\" rel=\"noopener\">$recUrl</a>&nbsp;$error_msg</div>";
        }

        $this->results['record'][$recId] = $recUrl;

        return false;
    }

    //
    //
    //
    private function skipFieldUrl($recId, $url, $detailTypeId, $field_type_idx) {

        // Skip URLs from the same server
        if (strpos(strtolower($url), $this->heuristDomain) === 0) {
            return true;
        }
                                                   
        //Skip if it is already in broken list
        if(is_array(@$this->results[$field_type_idx][$recId][$detailTypeId]) &&
           in_array($url, $this->results[$field_type_idx][$recId][$detailTypeId])){
            return true;
        }

        return false;
    }
    
    /**
     * Validates and handles URLs found in free text or block text fields or in file fields.
     *
     * @param int $recId
     * @param string $url
     * @param int $detailTypeId
     * @param string $field_type_idx  'text' or 'file'
     * @return true in case of fatal CURL error
     */
    private function validateAndHandleFieldUrl($recId, $url, $detailTypeId, $field_type_idx) {

        if ($this->listOnly) {
            $url = htmlentities($url);
            print intval($recId) . ' : ' . intval($detailTypeId) . ' : <a href="' . $url . '" target="_blank" rel="noopener">' . $url . '</a><br>';
            return false;
        }

        $error_msg = $this->checkRemoteURL($url);

        if ($error_msg!=null) {

            $this->results[$field_type_idx][$recId][$detailTypeId][] = $url;
            $this->results['session_bad_'.$field_type_idx]++;
        
            if($this->isVerbose){
                $urlMessage = '<div><a href="' . $url . '" target="_blank" rel="noopener">' . $url . '</a>&nbsp;' . htmlspecialchars($error_msg) . '</div>';
                print $urlMessage;
            }

        }

        $is_fatal = strpos($error_msg,CURL_ERR)===0;
        if($is_fatal){
            $this->results['curl'] = $error_msg;
            $this->errorMsg($error_msg);
        }

        return $is_fatal;

    }
}
