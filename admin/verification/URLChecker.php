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
* URLChecker.php - class to check and validate URLs from various sources in the database.
*
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/

define('CURL_RANGE', '0-500');
define('CURL_TIMEOUT', 2); //was 5

/**
 * Class URLChecker
 * A class to check and validate URLs from various sources in the database.
 */
class URLChecker {

    /** @var mysqli $mysqli Database connection */
    private $mysqli;

    /** @var string $heuristDomain  domain for this Heurist server  */
    private $heuristDomain;

    /** @var bool $isHeuristReferenceIndex Whether the database is Heurist_Reference_Index */
    private $isHeuristReferenceIndex;

    /** @var array $passedRecIds Array of record IDs that passed URL validation */
    private $passedRecIds = [];


    /** @var bool $isVerbose Whether echo results at once */
    private $isVerbose = false;

    private $context = null;

    private $timeoutDomains = [];
    private $alreadyChecked = [];

    /**
     * Constructor for URLChecker.
     *
     * @param mysqli $mysqli
     * @param string $heuristDomain
     * @param bool $isHeuristReferenceIndex
     */
    public function __construct($mysqli, $heuristDomain, $isHeuristReferenceIndex) {
        $this->mysqli = $mysqli;

        $info = parse_url(strtolower($heuristDomain));
        $this->heuristDomain = $info['scheme'].'://'.$info['host'];

        $this->isHeuristReferenceIndex = $isHeuristReferenceIndex;
    }

    /**
     * Updates the last verified date for the given record IDs.
     *
     * @return void
     */
    private function updateRecordsLastVerified() {
        $date = date(DATE_8601);
        $query = 'UPDATE Records SET rec_URLLastVerified="' . $date . '", rec_URLErrorMessage=null WHERE rec_ID IN ('
                            . implode(',', $this->passedRecIds) . ')';
        $this->mysqli->query($query);
        $this->passedRecIds = []; // reset the array
    }

    /**
     * Check URLs in various sources and validate them.
     *
     * @param bool $isVerbose echo results at once
     * @param bool $listOnly Only list the URLs, do not perform any validations.
     * @return array Results of the URL validation or boolean.
     */
    public function checkURLs($isVerbose = false, $listOnly = false) {

        $this->passedRecIds = [];
        $this->timeoutDomains = [];
        $this->alreadyChecked = [];

        //define timeout for get_headers
        $opts['http']['timeout'] = 5;
        $this->context = stream_context_create( $opts );

        $results =  [
            0 => [],  // Broken record URLs   'count' => 0, 'broken_urls' => []
            1 => [],  // Broken free text/block text URLs
            2 => [],  // Broken external file URLs
            3 => ''   //fatal CURL error message
            ];

        $this->isVerbose = $isVerbose;
        $this->listOnly = $listOnly;

        // Check record URLs
        $this->checkRecordURLs($results);

        // Check free text/block text fields for URLs
        $this->checkTextFieldURLs($results);

        // Check external URLs in use (e.g., file fields)
        $this->checkExternalFileURLs($results);

        return $results;
    }

    private function printHeader($sHeader){
        if($this->isVerbose || $this->listOnly){
            print $sHeader;
        }
    }

    private function printFooter($broken_cnt, $passed_cnt, $sMsg, $sMsg2='', $timestart=0){
        if($this->isVerbose){

            echo "<p>$sMsg. Processed: $passed_cnt</p>";

            if($broken_cnt>0){
                print "<div style=\"padding-top:20px;color:red\">There are <b>$broken_cnt</b> broken urls. $sMsg2</div>";
            }else{
                print '<div><h3 class="res-valid">OK: All URLs are valid</h3></div>';
            }

            if($timestart>0){
                print '<p>total time:    '.(microtime(true) - $timestart).'</p>';
            }

        }
    }

    private function isReferenceDatabase( $recTypeId ){
        return $this->isHeuristReferenceIndex && ($recTypeId == 101 || $recTypeId == 103);
    }

    /**
     * Check URLs from records and validate.
     *
     * @param array $results
     * @return void
     */
    private function checkRecordURLs(&$results) {
        $query = 'SELECT rec_ID, rec_URL, rec_RecTypeID FROM Records WHERE (rec_URL != "") AND (rec_URL IS NOT NULL)';
        $res = $this->mysqli->query($query);

        $this->printHeader('<h4>Records URLs</h4>');

        if (!$res) {
            if($this->isVerbose || $this->listOnly){
                print errorDiv('Cant retrieve records with URLs: '.$this->mysqli->error);
            }
            return;
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
                echo intval($recId) . " : <a href=\"$recUrl\" target=\"_blank\" rel=\"noopener\">$recUrl</a><br>";
                continue;
            }

            // Validate the URL
            $error_msg = $this->checkRemoteURL($recUrl, $isReferenceDatabase);
            if($error_msg==null){
                $this->handleRecordUrl($recId, $recUrl, $data, $isReferenceDatabase);
            }elseif($this->handleBrokenRecordUrl($recId, $recUrl, $results, $error_msg)){
                break;
            }
            $passed_cnt++;
        } //while
        $res->close();


        if (!empty($this->passedRecIds)) {
            $this->updateRecordsLastVerified();
        }

        $broken_cnt = count($results[0]);
        $this->printFooter($broken_cnt, $passed_cnt, 'Record URLs', 'Search "_BROKEN_" for details'.$timestart);
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
            $this->passedRecIds[] = $recId;
            if (count($this->passedRecIds) > 1000) {
                $this->updateRecordsLastVerified();
            }
        }
    }

    /**
     * Check free text and block text fields for URLs.
     *
     * @param array $results
     * @return void
     */
    private function checkTextFieldURLs(&$results) {
        $query = 'SELECT dtl_RecID, dtl_Value, dtl_DetailTypeID FROM recDetails ' .
            'INNER JOIN defDetailTypes ON dty_ID = dtl_DetailTypeID ' .
            'INNER JOIN Records ON rec_ID = dtl_RecID ' .
            'WHERE (dty_Type = "freetext" OR dty_Type = "blocktext") AND dtl_Value REGEXP "https?://"';

        $res = $this->mysqli->query($query);

        $this->printHeader('<h4>URLs in text fields</h4>');

        if (!$res) {
            if($this->isVerbose || $this->listOnly){
                print errorDiv('Cant retrieve records with URLs in text fields: '.$this->mysqli->error);
            }
            return;
        }

        $timestart = microtime(true);


        $passed_cnt = 0;
        while ($row = $res->fetch_row()) {
            $recId = $row[0];
            $value = $row[1];
            $detailTypeId = $row[2];

            $urls = [];
            preg_match_all("/https?:\/\/[^\s\"'>()\\\\]*/", $value, $urls);
            $urls = $urls[0] ?? [];

            foreach ($urls as $url) {
                if($this->validateAndHandleFieldUrl($recId, $url, $detailTypeId, $results, 1)){
                    break;
                }
                $passed_cnt++;
            }
        }
        $res->close();

        $broken_cnt = count($results[1]);
        $this->printFooter($broken_cnt, $passed_cnt, 'Text fields with URLs', '', $timestart);
    }

    /**
     * Check external file URLs in use.
     *
     * @param array $results
     * @return void
     */
    private function checkExternalFileURLs(&$results) {
        $query = 'SELECT dtl_RecID, ulf_ExternalFileReference, dtl_DetailTypeID FROM recDetails ' .
            'INNER JOIN defDetailTypes ON dty_ID = dtl_DetailTypeID ' .
            'INNER JOIN recUploadedFiles ON ulf_ID = dtl_UploadedFileID ' .
            'WHERE dty_Type = "file" AND ulf_ExternalFileReference != "" AND ulf_OrigFileName="_remote"';

        $res = $this->mysqli->query($query);

        $this->printHeader('<h4>External URLs (File fields)</h4>');

        if (!$res) {
            if($this->isVerbose){
                print errorDiv('Cant retrieve records with URLs in text fields: '.$this->mysqli->error);
            }
            return;
        }

        $timestart = microtime(true);
        $passed_cnt = 0;
        while ($row = $res->fetch_row()) {
            $recId = $row[0];
            $recUrl = $row[1];
            $detailTypeId = $row[2];

            if($this->validateAndHandleFieldUrl($recId, $recUrl, $detailTypeId, $results, 2)){
                break;
            }
            $passed_cnt++;
        }
        $res->close();

        $broken_cnt = count($results[2]);
        $this->printFooter($broken_cnt, $passed_cnt, 'External URLs (File fields)', '', $timestart);
    }


    private function loadRemoteURLContent($url) {
        global $glb_curl_error, $glb_curl_code;

        $exists = loadRemoteURLContentWithRange($url, CURL_RANGE, true, CURL_TIMEOUT);

        if(!$exists){
            if ($glb_curl_code == HEURIST_SYSTEM_FATAL) {
                if ($this->isVerbose) {
                    echo errorDiv( htmlspecialchars($glb_curl_error) );
                }
                $results[3] = $glb_curl_error;
                $glb_curl_error = 'Fatal curl error '.$glb_curl_error;
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
            if(strpos($lurl, $bad_domain) === 0){
                return 'Check skipped (it was timeout previously)';
            }
        }

        $this->alreadyChecked[] = $lurl;

        if($use_curl){
            return $this->checkRemoteURL($url);
        }

        //use get_headers ------------------
        //check takes around one second
        // in case of DNS issue it may take 20 seconds - we reduce timeout to 5 seconds
        $error_msg = null;

        $file_headers = @get_headers($url, 0, $this->context);
        if(!$file_headers){
            $exists = false;

            $this->timeoutDomains[] = $info['scheme'].'://'.$info['host'];

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
     * @param array $results
     * @return true in case of fatal CURL error
     */
    private function handleBrokenRecordUrl($recId, $recUrl, &$results, $error_msg) {


        if(strpos($error_msg,'Fatal curl error')===0){
            $results[3] = $error_msg;
            return true;
        }

        $query = 'UPDATE Records SET rec_URLLastVerified=?, rec_URLErrorMessage=? WHERE rec_ID='.intval($recId);
        mysql__exec_param_query($this->mysqli, $query, ['ss', date(DATE_8601), substr($error_msg, 0, 255)], true);

        if ($this->isVerbose) {
            $recUrl = htmlspecialchars($recUrl);
            $error_msg = htmlspecialchars($error_msg);
            echo '<div>' . intval($recId) . " : <a href=\"$recUrl\" target=\"_blank\" rel=\"noopener\">$recUrl</a>&nbsp;$error_msg</div>";
        }

        $results[0][$recId] = $recUrl;

        return false;
    }

    /**
     * Validates and handles URLs found in free text or block text fields or in file fields.
     *
     * @param int $recId
     * @param string $url
     * @param int $detailTypeId
     * @param array $results
     * @param int $idx_in_results  0 - no ouput, 1 for text fields, 2 - for file fields
     * @return true in case of fatal CURL error
     */
    private function validateAndHandleFieldUrl($recId, $url, $detailTypeId, &$results, $idx_in_results) {

        // Skip URLs from the same server
        if (strpos(strtolower($url), $this->heuristDomain) === 0) {
            return false;
        }

        if ($this->listOnly) {
            $url = htmlentities($url);
            echo intval($recId) . ' : ' . intval($detailTypeId) . ' : <a href="' . $url . '" target="_blank" rel="noopener">' . $url . '</a><br>';
            return false;
        }

        $error_msg = $this->checkRemoteURL($url);

        if ($error_msg!=null) {

            if ($idx_in_results>0) {
                $results[$idx_in_results][$recId][$detailTypeId][] = $url;
            }

            if($this->isVerbose){
                $urlMessage = '<div><a href="' . $url . '" target="_blank" rel="noopener">' . $url . '</a>&nbsp;' . htmlspecialchars($error_msg) . '</div>';
                echo $urlMessage;
            }

        }

        $is_fatal = strpos($error_msg,'Fatal curl error')===0;
        if($is_fatal){
            $results[3] = $error_msg;
        }

        return $is_fatal;

    }
}
