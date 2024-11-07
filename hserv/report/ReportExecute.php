<?php
/*
* ReportExecute.php - executes Smarty templates, handles the
* output of reports in various formats
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2024 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.6
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

namespace hserv\report;

use hserv\report\ReportRecord;
use hserv\utilities\USanitize;

require_once 'smartyInit.php';
require_once dirname(__FILE__).'/../utilities/Temporal.php';
require_once dirname(__FILE__).'/../records/search/recordSearch.php';
require_once dirname(__FILE__).'/../../vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php';

define('HEAD_E','</head>');

/**
 * Class ReportExecute
 *
 * This class manages the execution of Smarty templates for reports, handling the
 * output of reports in various formats (HTML, JS, etc.) and dealing with records
 * fetched from the system.
 */
class ReportExecute
{
    private $smarty;

    private $templateFile = null; //basename of template file

    private $outputfile;
    private $outputmode; //output format
    private $isVoid = false;   //if true - not browser output
    //private $maxAllowedDepth = 2; not used

    // 0 in UI (including tests in editor)
    // 1 saves into generated-reports and produces info page (user report) only
    // 2 downloads ONLY it under given output name (no file save, no browser output)
    // 3 saves into generated-reports and outputs report into browser (without UI limits)
    // 4 calculation field
    private $publishmode;

    private $smartySessionId;
    private $executionCounter;
    private $executionCounterTotal;

    //private $is_included = false;    true if this report is included into anpther one - NOT USED

    private $isJsAllowed;
    private $recordWithCustomCSS;
    private $isHeadless; //output without html header and styles - for snippet (loading as content of existing html element) and xml output

    private $limit;
    private $replevel;

    private $system;
    private $params;

    private $messageAboutTruncation;
    private $messageError;

/*
* parameters
* 'template' or 'template_body' - template file name or template value as a text
* 'replevel'  - 1 notices, 2 all, 3 debug mode
*
* 'output' -  name of file to be saved in generated-reports folder
* 'mode' -    output format if publish>0: js, xml, txt, json or html (default)
* 'publish' - 0 for user interface only (including editor)
*             1 if output defined, saves into generated-reports and produces info page (user report)
*             2 downloads ONLY it under given output name (no file save, no browser output)
*             3 if output defined it saves into generated-reports and outputs report into browser (without UI limits)
*             4 calculation field
*
* other parameters are hquery's
*/


    /**
     * Constructor
     *
     * @param mixed $system The system object used for database and other interactions.
     * @param array|null $params The parameters array typically passed from $_REQUEST.
     */
    public function __construct($system, $params=null)
    {
        $this->system = $system;

        if ($params!=null) {
            // Initialize properties from parameters or set defaults
            $this->setParameters($params);
        }
    }

    /**
     * Main function to execute the Smarty template with the provided parameters.
     *
     * @return bool Returns true on successful execution, false on failure.
     */
    public function execute()
    {
        $result = false;

        // Check if the system is initialized
        if (!isset($this->system) || !$this->system->is_inited()) {
            $this->outputError();
        } elseif (!isset($this->params)) {
            // Check if parameters are defined
            $this->outputError('Parameters for smarty executions are not defined');
        } elseif (!$this->initSmarty()) {
            // Initialize Smarty if necessary
            // Do nothing as the error was already handled
        } else {
            set_time_limit(0); // No script execution time limit

            // Load template content
            $content = $this->loadTemplateContent();

            if ($content) {
                // Fetch record IDs based on search query
                $query_result = $this->fetchRecordIDs();

                $result = true;

                // Handle empty result set
                if ($this->handleEmptyResultSet($query_result)) {
                    // Process the fetched records and execute the template with Smarty
                    $result = $this->executeTemplate($query_result, $content);
                }
            }
        }

        return $result;
    }



    /**
     * Initializes properties from parameters or sets defaults.
     * Handles the output mode and sets appropriate flags.
     * Sets the search limit based on publishing mode or user preferences.
     *
     * @param array|null $params The parameters array to set.
     */
    public function setParameters($params=null)
    {

        if($params!=null){
            $this->params = $params;
        }

        $this->publishmode = isset($params['publish']) ? intval($params['publish']) : 1; //by default full recordset execution

        $this->publishmode = max(min($this->publishmode,4),0);

        $this->recordWithCustomCSS = isset($params['cssid']) ? intval($params['cssid']) : null;

        $this->smartySessionId = isset($params['session']) ? $params['session'] : null;


        $this->outputmode = isset($params['mode']) ? preg_replace('/[^a-z]/', "", $params["mode"]) : 'html';
        $allowed_exts = array('html','js','txt','text','csv','xml','json','css');
        $idx = array_search($this->outputmode, $allowed_exts);
        $this->outputmode = ($idx>=0)?$allowed_exts[intval($idx)]:'html';

        if ($this->outputmode === 'text') {
            $this->outputmode = 'txt';
        }

        $this->isHeadless = isset($params['snippet']) && $params['snippet'] == 1; //html output with header or not

        $this->isVoid = isset($params['void']) && $params['void'];  //fetch into file

        $this->isJsAllowed = $this->system->isJavaScriptAllowed();

        $this->replevel = 0;
        if (@$params['template_body']){

            if ($this->publishmode != 4) {
                $this->publishmode = 0;
            }
            $this->outputmode = 'html'; //always html in test or snippet mode

            $this->replevel = isset($params['replevel']) ? intval($params['replevel']) : 0;
        }
    }

    /**
     * Prepares and sanitizes the output file name.
     *
     * @return string The sanitized output file name.
     */
    private function prepareOutputFile(){
        $this->outputfile = $this->params["output"] ?? $this->templateFile ?? 'heurist_output';
        $path_parts = pathinfo($this->outputfile);
        $this->outputfile = USanitize::sanitizeFileName($path_parts['filename']) . '.' . $this->outputmode;
        return $this->outputfile;
    }


    /**
     * Fetch record IDs based on the provided query parameters.
     *
     * @return array The query result containing records and record count.
     */
    private function fetchRecordIDs()
    {
        $this->setLimit();
        $this->messageAboutTruncation = null;

        if (isset($this->params['recordset'])) {
            // Handle predefined recordset
            $qresult = $this->handleRecordset($this->params['recordset']);
        } else {
            // Perform a query/search for the recordset
            $qresult = $this->searchRecords();
        }

        return $qresult;
    }

    /**
    * Sets the search limit based on publishing mode or user preferences.
    */
    private function setLimit()
    {
        if (!isset($this->params["limit"])) {
            if ($this->publishmode == 0) {
                $limit_for_interface = intval($this->system->user_GetPreference('smarty-output-limit'));
                if (!$limit_for_interface || $limit_for_interface < 1) {
                    $limit_for_interface = 50; // Default limit
                }
                $this->params["limit"] = $limit_for_interface;
            } else {
                $this->params["limit"] = PHP_INT_MAX;
            }
        }
        $this->limit = intval($this->params["limit"]);
    }

    /**
    * recordset (list of record ids) is already defined
    *
    * @param mixed $recordset
    */
    private function handleRecordset($recordset)
    {
        if (is_array($recordset)) {
            $qresult = $recordset;
        } else {
            $qresult = json_decode($recordset, true);
        }

        if ($this->publishmode == 0 && $qresult && isset($qresult['recIDs'])) {
            $recIDs = prepareIds($qresult['recIDs']);

            if($this->limit < count($recIDs) || $this->limit < $qresult['recordCount'] ){
                $this->messageAboutTruncation = '<div><b>Report preview has been truncated to '.intval($this->limit).' records.<br>'
                    .'Please use publish or print to get full set of records.<br Or increase the limit in preferences</b></div>';
            }

            if ($this->limit < count($recIDs)) {
                $qresult = [
                    'records' => array_slice($recIDs, 0, $this->limit),
                    'reccount' => count($recIDs)
                ];

            } else {
                $qresult = [
                    'records' => $recIDs,
                    'reccount' => count($recIDs)
                ];
            }
        }

        return $qresult;
    }

    /**
    *  get recordset from query/search results
    */
    private function searchRecords()
    {
        $this->params['detail'] = 'ids';
        $qresult = recordSearch($this->system, $this->params);

        if (isset($qresult['status']) && $qresult['status'] == HEURIST_OK) {
            return $qresult['data'];
        } else {
            $msg = $this->system->getErrorMsg();
            if($msg==''){
                $msg = 'Undefined error on query executtion';
            }
            $this->params['emptysetmessage'] = $msg;
            return null;
        }
    }

    /**
     * Handles empty result sets and outputs an appropriate error message or info.
     *
     * @param array $qresult The query result containing records and record count.
     * @return bool Returns false if result is empty, true otherwise.
     */
    private function handleEmptyResultSet($qresult)
    {
        $emptysetmessage = $this->params['emptysetmessage'] ?? null;

        if($emptysetmessage=='def'){
            $emptysetmessage = null;
        }

        if (isset($qresult['records']) && intval(@$qresult['reccount']) > 0) {
            return true;
        }

        if ($this->publishmode == 4) {
            //for calculation field
            echo USanitize::sanitizeString($emptysetmessage ?? '');
        } else {
            $error = $emptysetmessage ?? ($this->publishmode > 0
                    ? 'Note: There are no records in this view. The URL will only show records to which the viewer has access. Unless you are logged in to the database, you can only see records which are marked as Public visibility'
                    : 'Search records to see template output');
            $this->outputError($error);
        }
        return false;

    }

    /**
     * Loads the template content from a file or from a provided template body.
     *
     * @return string|false Returns the template content or false if the file or content is invalid.
     */
    private function loadTemplateContent()
    {
        $templateFile = isset($this->params['template']) ? basename($this->params['template']) : null;
        $template_body = isset($this->params['template_body']) ? $this->params['template_body'] : null;

        if ($templateFile) {
            $content = $this->loadTemplateFile($templateFile);
        } else {
            $content = $template_body;
        }


        if(!isset($this->params["output"]) && $this->publishmode != 2){
            $this->outputfile = null;
            if($this->publishmode==1 ){
                //if output is not defined - output to browser by default
                $this->publishmode = 3;
            }
            $this->outputfile = null;
        }else{
            $this->outputfile = $this->prepareOutputFile();
        }

        if($content!==false && ($content==null || strlen(trim($content))==0)){
            $this->outputError('Template content is empty');
            return false;
        }

        return $content;
    }

    /**
    * Loads template content from file
    *
    * @param mixed $templateFile
    */
    private function loadTemplateFile($templateFile)
    {
        if (substr($templateFile, -4) !== ".tpl") {
            $templateFile .= ".tpl";
        }

        $template_path = $this->system->getSysDir('smarty-templates') . $templateFile;
        if (!file_exists($template_path)) {
            $error = 'Template file ' . htmlspecialchars($templateFile) . ' does not exist';
            $this->outputError($error);
            return false;
        }

        $this->templateFile = $templateFile;
        return file_get_contents($template_path);
    }

    /**
     * Initializes the Smarty engine if it is not already initialized.
     *
     * @param bool $force_init Force reinitialization of the Smarty engine if set to true.
     * @return bool Returns true on successful initialization, false otherwise.
     */
     public function initSmarty($force_init=false)
    {
        if (!$force_init && isset($this->smarty)) {
            return true; //already inited
        }

        $errorMsg = '';

        try{
            $this->smarty = smartyInit($this->system->getSysDir('smarty-templates'));
        } catch (\Exception $e) {
            $errorMsg  = $e->getMessage();
        }


        if (!isset($this->smarty) || $this->smarty === null) {
            $this->outputError('Cannot init Smarty report engine. '.$errorMsg);
            return false;
        }

        $this->smarty->registerPlugin(\Smarty\Smarty::PLUGIN_FUNCTION, 'progressCallback', [$this, 'progressCallback']);
        $this->smarty->registerPlugin(\Smarty\Smarty::PLUGIN_FUNCTION, 'out', [$this, 'printLabelValuePair']);
        $this->smarty->registerPlugin(\Smarty\Smarty::PLUGIN_FUNCTION, 'wrap', [$this, 'printProcessedValue']);

        return true;
    }

    /**
     * Executes the Smarty template with the provided records and template content.
     *
     * @param array $qresult The result set containing records.
     * @param string $content The content of the template.
     * @return bool Returns true on successful execution, false otherwise.
     */
    public function executeTemplate($qresult, $content)
    {
        $results = $qresult["records"];  //reocrd ids
        $this->executionCounterTotal = count($results);
        $heuristRec = new ReportRecord($this->system);

        if (method_exists($this->smarty, 'assignByRef')) {
            $this->smarty->assignByRef('heurist', $heuristRec); //deprecated
        } else {
            $this->smarty->assign('heurist', $heuristRec);
        }

        $this->smarty->assign('results', $results);

        $facet_value = isset($this->params['facet_val']) ? htmlspecialchars($this->params['facet_val']) : null;
        if (!empty($facet_value)) {
            $this->smarty->assign('selected_term', $facet_value);
        }

        // Register Smarty plugins and modifiers
        try {
            $this->smarty->registerPlugin('modifier', 'file_data', [$heuristRec, 'getFileField']);
        } catch (\Exception $e) {
            if (strpos($e, 'already registered') === false) {
                $this->outputError('Cannot register smarty plugin. '.$e->getMessage());
                return false;
            }
        }

        // Handle activity logging if required
        //if (!$this->isHeadless && !isset($this->params['template_body']) && !$this->is_fetch) {
        //    log_smarty_activity($this->system, $results);
        //}

        // Execute the template and handle output filtering
        return $this->executeTemplateContinue($content, $results);
    }

    /**
     * Continues the template execution by processing output and handling filters.
     *
     * @param string $content The template content.
     * @param array $results The result records.
     * @return bool True on success, false otherwise.
     */
    private function executeTemplateContinue($content, $results)
    {
        $result = true;
        $temp_templateFile = null;

        $this->setupErrorReporting($this->replevel);

        if ($this->templateFile==null){  //execution from $this->params['template_body']

                $temp_templateFile = $this->saveTemporaryTemplate($content);

                if($this->publishmode == 4){
                    //assign the only record for calculation field
                    $this->smarty->assign('r', (new ReportRecord($this-system))->getRecord($results[0]));

                    try{
                        $output = $this->smarty->fetch($temp_templateFile);
                    } catch (\Exception $e) {
                        $output = 'Exception on calculation field execution: '.$e->getMessage();
                    }
                    fileDelete($this->smarty->getTemplateDir().$temp_templateFile);
                    echo $output;
                    return true;
                }

                $this->templateFile = $temp_templateFile;
        }


        $this->smarty->registerFilter('pre', [$this, 'translateTerms']);  //smarty_pre_filter
        if ($this->publishmode == 0 && $this->smartySessionId > 0) {
            $this->executionCounter = 0;

            $this->smarty->registerFilter('post', [$this, 'addProgressCallback']);  //smarty_post_filter
        }else{
            $this->smartySessionId = 0;
        }

        /* smarty output filter is not used in this version since we fetch output to variable
            $this->smarty->registerFilter('output', [$this, 'handleTemplateOutput']);
        */

        $this->smarty->assign('template_file', $this->templateFile);
        try {

            $this->handleTemplateOutput( $this->smarty->fetch($this->templateFile) );

            /* Apparently need to use $this->smarty->display($templateFile) for huge reprot to direct output to browser*/

        } catch (\Exception $e) {
            $this->outputError('Exception on execution: ' . $e->getMessage());
            $result = false;
        }

        if ($this->smartySessionId > 0) {
            mysql__update_progress(null, $this->smartySessionId, false, 'REMOVE');
        }


        if($this->publishmode==0 && isset($this->messageAboutTruncation)){
            echo $this->messageAboutTruncation;
        }

        //remove temporary file
        if($temp_templateFile){
            fileDelete($this->smarty->getTemplateDir().$temp_templateFile);
        }

        return $result;
    }

    //
    //
    //
    private function setupErrorReporting($replevel){

        $this->smarty->debugging = false;
        $this->smarty->error_reporting = 0;

        if($replevel==1 || $replevel==2){
            ini_set( 'display_errors' , 'true');// 'stdout' );
            if($replevel==2){
                $this->smarty->error_reporting = E_ALL & ~E_STRICT & ~E_NOTICE;
            }else{
                $this->smarty->error_reporting = E_NOTICE;
            }
        }else{
            ini_set( 'display_errors' , 'false');
            $this->smarty->debugging = ($replevel==3);
        }
        if($replevel>0){
            $this->smarty->debug_tpl = dirname(__FILE__).'/debug_html.tpl';
        }

    }

    //
    // helper function. It saves template_body into temporary tempalte file
    //
    private function saveTemporaryTemplate($content){
        //save temporary template
        //this is user name $templateFile = "_temp.tpl";
        $user = $this->system->getCurrentUser();

        $templateFile = '_'.basename(USanitize::sanitizeFileName($user['ugr_Name'])).'.tpl';
        $template_folder = $this->smarty->getTemplateDir();
        if(is_array($template_folder)) {$template_folder = $template_folder[0];}
        $file = fopen ($template_folder.$templateFile, "w");
        fwrite($file, $content);
        fclose ($file);

        return $templateFile;
    }

    //
    //
    //
    private function outputError($error_msg=null){

        if(!isset($error_msg)){
            $error_msg = $this->system->getErrorMsg();
            if($error_msg==''){
                $error_msg = 'Undefined smarty error';
            }
        }

        $this->messageError = $error_msg;

        if($this->outputmode=='html'){
            $error_msg = '<span style="color:#ff0000;font-weight:bold">'.$error_msg.'</span>';
        }
        $this->handleTemplateOutput($error_msg);
    }

    public function getError(){
        return $this->messageError;
    }

    //SMARTY FILTERS

    //
    // Convert short form term translations (before Smarty processes the report)
    //
    public function translateTerms($tpl_source, \Smarty\Template $template){

        $matches = array();

        // Handle shorthand term translations {trm_id \Language_1 \Language_2 ...}
        if(!preg_match_all('/{\\d*\\s*(?:\\\\\\w{3}\\s*)+}/', $tpl_source, $matches)){
            return $tpl_source;
        }

        $mysqli = $this->system->get_mysqli();

        $query = "SELECT trn_Translation FROM defTranslations WHERE trn_Code={id} AND trn_Source='trm_Label' AND trn_LanguageCode='{lang}'";
        $to_replace = array('{id}', '{lang}');
        $done = array();

        foreach ($matches[0] as $match) {

            if(in_array($match, $done)){ // already replaced
                continue;
            }

            $parts = explode('\\', trim($match, ' {}'));
            $str_replace = '';

            if(empty($parts) || intval($parts[0]) < 1){ // ignore
                continue;
            }

            $id = intval(array_shift($parts));

            foreach ($parts as $lang) {
                $str_replace = mysql__select_value($mysqli, str_replace($to_replace, array($id, $lang), $query));

                if(!empty($str_replace)){
                    break;
                }
            }

            if(empty($str_replace)){
                $str_replace = mysql__select_value($mysqli, "SELECT trm_Label FROM defTerms WHERE trm_ID=$id");
            }

            $tpl_source = str_replace($match, $str_replace, $tpl_source);
            array_push($done, $match);
        }


        return $tpl_source;
    }

    //
    // adds a small piece of code in main loop with function "progressCallback" - need to maintain progress
    //
    public function addProgressCallback($tpl_source, \Smarty\Template $template)
    {
        //find fist foreach and insert as first operation
        $offset = strpos($tpl_source,'foreach ($_from ?? [] as $_smarty_tpl->getVariable(');//'foreach ($_from as $_smarty_tpl');

        if($offset>0){
            $pos = strpos($tpl_source,'{',$offset);

            return substr($tpl_source,0,$pos+1)
."\n".'$_smarty_tpl->getSmarty()->getFunctionHandler("progressCallback")->handle(array(), $_smarty_tpl);'."\n"
//old way            ."\n".'{ if(progressCallback(array(), $_smarty_tpl)){ return; }'."\n"
            .substr($tpl_source,$pos+1);

        }

        return $tpl_source;
    }

    private function removeHeadAndBodyTags($content){

            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = false;
            //disable this feature $dom->formatOutput       = true;
            @$dom->loadHTML($content);
            $body = $dom->getElementsByTagName('body');
            if($body){
                $new_content = $dom->saveHtml($body[0]);//outer html
                $new_content = preg_replace( '@(?:^<body[^>]*>)|(?:</body>$)@g', '', $new_content );
                $new_content = preg_replace( '@(?:^<p[^>]*>)|(?:</p>$)@g', '', $new_content );
                if($new_content!=null && $new_content!=''){
                    return $new_content;
                }
            }

            return $content;
    }

    //
    // Get TinyMCE formats
    //
    private function getFontStyles(){

        $font_styles = '';

            $formats = $this->system->getDatabaseSetting('TinyMCE formats');
            if(is_array($formats) && array_key_exists('formats', $formats)){
                foreach($formats['formats'] as $format){

                    $styles = $format['styles'];

                    $classes = $format['classes'];

                    if(empty($styles) || empty($classes)){
                        continue;
                    }

                    $font_styles .= "." . implode(", .", explode(" ", $classes)) . " { ";
                    foreach($styles as $property => $value){
                        $font_styles .= "$property: $value; ";
                    }
                    $font_styles .= "} ";
                }
            }

        return $font_styles;
    }


    /**
     * Adds custom styles and scripts from CMS settings.
     *
     * @return string The HTML content containing custom styles and scripts.
     */
    private function addCustomStylesAndScripts()
    {
         $head = '';
         $css_fields = array();
         if($this->system->defineConstant('DT_CMS_CSS')){
             array_push($css_fields, DT_CMS_CSS);
         }
         if($this->system->defineConstant('DT_CMS_EXTFILES')){
             array_push($css_fields, DT_CMS_EXTFILES);
         }
         if(empty($css_fields)){
             return '';
         }

         $record = recordSearchByID($this->system, $this->recordWithCustomCSS, $css_fields, 'rec_ID');
         if(!@$record['details']){
            return '';
         }

         if(defined('DT_CMS_CSS') && @$record['details'][DT_CMS_CSS]){
             //add to begining
             $head .= '<style>'.recordGetField($record, DT_CMS_CSS).'</style>';
         }

         if(defined('DT_CMS_EXTFILES') && @$record['details'][DT_CMS_EXTFILES]){
             //add to header
             $external_files = $record['details'][DT_CMS_EXTFILES] ?? [];
             if(!is_array($external_files)){
                     $external_files = array($external_files);
             }

             foreach ($external_files as $ext_file){
                $head .= $ext_file;
             }
         }

         return $head;
    }

    /**
     * Handles the case where JavaScript is allowed.
     *
     * @param string $tpl_source The source template content.
     * @param string $font_styles The CSS font styles.
     * @return string The modified template content with JavaScript and styles.
     */
    private function handleJsAllowed($tpl_source, $font_styles){

         $script_tag = '<script type="text/javascript" src="'.HEURIST_BASE_URL;

         if(!$this->isHeadless){ //full html output. inside iframe - add all styles and scripts to header at once

             //adds custom scripts and styles to html head

             $head = $font_styles;
             $close_tags = '';

             if(strpos($tpl_source, '<html>')===false){
                 $open_tags = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
                 $close_tags = '</body></html>';
             }

             //add custom css and external links from CMS_HOME  DT_CMS_CSS and DT_CMS_EXTFILES
             if ($this->recordWithCustomCSS > 0) {
                    $head .= $this->addCustomStylesAndScripts();
             }

             //check if need to init mediaViewer
             if(strpos($tpl_source,'fancybox-thumb')>0){

                 $baseURL = HEURIST_BASE_URL;
                 $head .= <<<EXP
                            {$script_tag}external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
                            {$script_tag}external/jquery-ui-1.12.1/jquery-ui.min.js"></script>
                            {$script_tag}external/jquery.fancybox/jquery.fancybox.js"></script>
                            {$script_tag}hclient/core/detectHeurist.js"></script>
                            {$script_tag}hclient/widgets/viewers/mediaViewer.js"></script>
                            <link rel="stylesheet" href="{$baseURL}external/jquery.fancybox/jquery.fancybox.css" />
                        EXP;
                 //init mediaviewer after page load
                 $head .=  ('<script>'
                     .'var rec_Files=[];'
                     .'$(document).ready(function() {'
                     .'$("body").mediaViewer({rec_Files:rec_Files, showLink:false, selector:".fancybox-thumb", '
                     .'database:"'.$this->system->dbname().'", baseURL:"'.HEURIST_BASE_URL.'"});'
                     .'});'
                     .'</script>'
                     .'<style>.fancybox-toolbar{visibility: visible !important; opacity: 1 !important;}</style>');
             }


             //forcefully adds html and body tags
             $tpl_source = $open_tags.$tpl_source.$close_tags;

             $tpl_source = str_replace('<body>','<body class="smarty-report">', $tpl_source);
             if($head!=''){
                 $tpl_source = str_replace(HEAD_E,$head.HEAD_E, $tpl_source);
             }


         }else{ //html snippet output (without head) ----------------------------
                //loading as content of existing html element

             //adds custom scripts and styles to parent document head (insertAdjacentHTML and )

             $head = $font_styles;

             //check if need to init mediaViewer
             if(strpos($tpl_source,'fancybox-thumb')>0){

                 $head = <<<EXP
    {$script_tag}external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
    {$script_tag}external/jquery-ui-1.12.1/jquery-ui.js"></script>
    {$script_tag}external/jquery.fancybox/jquery.fancybox.js"></script>
    {$script_tag}hclient/core/detectHeurist.js"></script>
    {$script_tag}hclient/widgets/viewers/mediaViewer.js"></script>
    <script>var rec_Files=[];</script>
    EXP;

                 $head .= (
                     '<script>$(document).ready(function() {'

                     .'document.getElementsByTagName("head")[0].insertAdjacentHTML("beforeend","<link rel=\"stylesheet\" href=\"'.HEURIST_BASE_URL.'external/jquery.fancybox/jquery.fancybox.css\" />");'

                     .'$("body").mediaViewer({rec_Files:rec_Files, showLink:false, selector:".fancybox-thumb", '
                     .'database:"'.$this->system->dbname().'", baseURL:"'.HEURIST_BASE_URL.'"});'
                     .'});'
                     .'</script>'
                     .'<style>.fancybox-toolbar{visibility: visible !important; opacity: 1 !important;}</style>');
             }

             //add custom css and external links from CMS_HOME  DT_CMS_CSS and DT_CMS_EXTFILES
             if($this->recordWithCustomCSS>0){
                 //find record with css fields
                 $css_fields = array();
                 if($this->system->defineConstant('DT_CMS_CSS')){
                     array_push($css_fields, DT_CMS_CSS);
                 }
                 if($this->system->defineConstant('DT_CMS_EXTFILES')){
                     array_push($css_fields, DT_CMS_EXTFILES);
                 }
                 if(!empty($css_fields)){
                     $record = recordSearchByID($this->system, $this->recordWithCustomCSS, $css_fields, 'rec_ID');
                     if($record && @$record['details']){

                         if(defined('DT_CMS_CSS') && @$record['details'][DT_CMS_CSS]){
                             //add to begining
                             $head = '<style>'.recordGetField($record, DT_CMS_CSS).'</style>'.$head;

                             $head = $head.'<script>if(document.body){
                             document.body.classList.add("smarty-report");
                             } </script>';
                         }

                         if(defined('DT_CMS_EXTFILES') && @$record['details'][DT_CMS_EXTFILES]){
                             //add to header
                             $external_files = @$record['details'][DT_CMS_EXTFILES];
                             if($external_files!=null){
                                 if(!is_array($external_files)){
                                     $external_files = array($external_files);
                                 }
                                 if(!empty($external_files)){
                                     foreach ($external_files as $ext_file){
                                         if(strpos($ext_file,'<link')===0){ // || strpos($ext_file,'<script')===0
                                             $head = $head .$ext_file;
                                         }
                                     }

                                 }
                             }
                         }
                     }
                 }
             }

             //
             if($head!=''){
                 if(strpos($tpl_source, '<head>')>0){
                     $tpl_source = str_replace(HEAD_E, $head.HEAD_E, $tpl_source);
                 }else{
                     $tpl_source = $this->removeHeadAndBodyTags($tpl_source);
                     $tpl_source = $head.$tpl_source;
                 }
             }
         }
         return $tpl_source;
    }

    /**
     * Sanitizes the HTML using HTMLPurifier.
     *
     * @param string $tpl_source The source template content.
     * @param string $font_styles The CSS font styles.
     * @return string The sanitized template content.
     */
    private function sanitizeHtml($tpl_source, $font_styles)
    {

                //if javascript not allowed, use html purifier to remove suspicious code
                $config = \HTMLPurifier_Config::createDefault();
                $config->set('HTML.Doctype', 'HTML 4.01 Transitional');

                $config->set('HTML.DefinitionID', 'html5-definitions');// unqiue id
                $config->set('HTML.DefinitionRev', 1);

                $config->set('Cache', 'SerializerPath', $this->system->getSysDir('scratch'));
                $config->set('CSS.Trusted', true);
                $config->set('Attr.AllowedFrameTargets','_blank');
                $config->set('HTML.SafeIframe', true);
                //allow YouTube, Soundlcoud and Vimeo
                // https://w.soundcloud.com/player/
                $config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/|w\.soundcloud\.com/player/)%');

                $def = $config->getHTMLDefinition(true);
                $def->addElement(
                    'audio',
                    'Block',
                    'Flow',
                    'Common',
                    [
                        'controls' => 'Bool',
                        'autoplay' => 'Bool',
                        'data-id' => 'Number'
                    ]
                );
                $def->addElement('source', 'Block', 'Flow', 'Common', array(
                    'src' => 'URI',
                    'type' => 'Text',
                ));

                /* to test it
                if ($def = $config->maybeGetRawHTMLDefinition()) {
                    // http://developers.whatwg.org/the-video-element.html#the-video-element
                    $def->addElement('video', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', array(
                        'src' => 'URI',
                        'type' => 'Text',
                        'width' => 'Length',
                        'height' => 'Length',
                        'poster' => 'URI',
                        'preload' => 'Enum#auto,metadata,none',
                        'controls' => 'Bool',
                    ));
                }
                $config->set('HTML.Trusted', true);
                $config->set('Filter.ExtractStyleBlocks', true);

                */

                $purifier = new \HTMLPurifier($config);

                $tpl_source = $purifier->purify($tpl_source);

                if(!empty($font_styles)){
                    if(strpos($tpl_source, '<head>')>0){
                        $tpl_source = str_replace(HEAD_E,$font_styles.HEAD_E, $tpl_source);
                    }else{
                        $tpl_source = $font_styles.$tpl_source;
                    }
                }

                return $tpl_source;
    }

    /**
     * Strips JavaScript and sanitizes HTML output.
     * This function is called before other output filters.
     *
     * @param string $tpl_source The source template content.
     * @param \Smarty\Template $template The Smarty template object.
     * @return string The sanitized template content.
     */
    private function stripJavascriptAndSantize($tpl_source){

            if(!($this->outputmode=='js' || $this->outputmode=='html')){
                //other than html or js output - it removes html and body tags
                return $this->removeHeadAndBodyTags($tpl_source);
            }

            $font_styles = $this->system->getWebFontsLinks('ui-sans-serif');
            if(isEmptyStr($font_styles)){
                $font_styles = '';
            }else{
                $font_styles = "<style> $font_styles </style>";
            }
            $tinymce_styles = $this->getFontStyles(); //tinyMCE formats
            if(!empty($tinymce_styles)){
                $font_styles = "<style> $tinymce_styles </style>".$font_styles;
            }

            // Allow JavaScript or sanitize HTML
            if ($this->isJsAllowed) {
                $tpl_source = $this->handleJsAllowed($tpl_source, $font_styles);
            } else {
                $tpl_source = $this->sanitizeHtml($tpl_source, $font_styles);
            }

        //replace relative path for images that are in blocktext fields
        $tpl_source = str_replace(' src="./?db='.$this->system->dbname().'&',
            ' src="'.HEURIST_BASE_URL.'?db='.$this->system->dbname().'&',$tpl_source);



        $onclick = '';
        if($this->publishmode==0 || $this->publishmode==1){
            $onclick = 'onclick="'
                . '{try'
                    .'{'
                        .'let event_target = event.target.getAttribute("target");'
                        .'let def_targets = ["_self","_blank","_parent","_top"];'
                        .'if(event_target && def_targets.indexOf(event_target) !== -1){ return true; }'
                        .'var h=window.hWin?window.hWin.HEURIST4:window.parent.hWin.HEURIST4;'
                        .'h.msg.showDialog(event.target.href,{title:\'.\',width: 600,height:500,modal:false});'
                        .'return false'
                    .'}catch(e){'
                        .'return true'
                    .'}'
                .'}" ';
        }

        $tpl_source = preg_replace_callback('/href=["|\']?(\d+\/.+\.tpl|\d+)["|\']?/',
                function($matches) use ($onclick){
                    return $onclick.'href="'.$this->system->recordLink($matches[1]).'"';
                },
                $tpl_source);

        return $tpl_source;

    }

    //
    //
    //
    private function saveOutputAsJavascript( $tpl_source ){

        $tpl_source = str_replace("\n","",$tpl_source);
        $tpl_source = str_replace("\r","",$tpl_source);
        $tpl_source = str_replace("'","&#039;",$tpl_source);
        return "document.write('". $tpl_source."');";
    }

    //
    //
    //
    private function saveOutputToFile($file_name, $tpl_source){

        $errors = null;

        try{
            //output to generated-reports only
            $dirname = $this->system->getSysDir(DIR_GENERATED_REPORTS);
            if(!folderCreate($dirname, true)){
                return 'Failed to create folder for generated reports';
            }

            $res_file = $dirname."/".$file_name; // acutal file
            $temp_file = $dirname."/_".$file_name; // temporary file, if needed

            $file = false; // file handler
            $use_temp = false; // using temporary file

            if(!file_exists($res_file) || is_writable($res_file)){ // open existing file
                $file = fopen ($res_file, "w");
            }else{ // create temp file to replace original
                $file = fopen($temp_file, "w");
                $use_temp = true;
            }

            if(!$file){
                $errors = "Can't write file $res_file. Check permission for directory";
            }else{
                fwrite($file, $tpl_source);
                fclose($file);
            }

            if($use_temp){

                if(unlink($res_file) === false){ // Delete old file
                    unlink($temp_file);// on error, remove temp file
                    $errors = "Can't delete old report file $res_file. Check permission for file";
                }elseif(rename($temp_file, $res_file) === false){ // Rename temp file
                    unlink($temp_file);// on error, remove temp file
                    $errors = "Can't rename temporary file $temp_file to $res_file. Check permissions";
                }
            }


        }catch(\Exception $e)
        {
            $errors = $e->getMessage();
        }

        return $errors;

    }


    /**
     * Handles the output from the Smarty template, saving or outputting it as required.
     *
     * @param string $smarty_output The rendered Smarty output.
     * @param bool $need_sanitize Whether or not to sanitize the output.
     */
    private function handleTemplateOutput($smarty_output, $need_sanitize=true){ // ,  \Smarty\Template $template=null

        $errors = null;

        //sanitize
        if($need_sanitize){
            $smarty_output = $this->stripJavascriptAndSantize($smarty_output);

            if($this->outputmode=='js'){
                $smarty_output = $this->saveOutputAsJavascript($smarty_output);
            }
        }


        if($this->publishmode!=1){
            $this->setOutputHeaders();
        }

        // if param "output" ($outputfile) is defined it saves smarty report into file
        // and
        // $publishmode - 1 saving into file and produces info page (user report) only
        //                2 downloads ONLY it under given output name (no file save, no browser output)
        //                3 saving into file and outputs smarty report into browser

        if ($this->publishmode==2) {//download

                header('Pragma: public');
                header('Content-Disposition: attachment; filename="'.$this->outputfile.'"');
                header(CONTENT_LENGTH . strlen($smarty_output));
                echo $smarty_output;

        }elseif ($this->publishmode==0) {    //browser output only

            echo $smarty_output;
        }else {
            //3 - save into file and browser output
            //1 - save into file and info page

            if($this->outputfile!=null){
                $errors = $this->saveOutputToFile($this->outputfile, $smarty_output);
            }
            if($this->isVoid){
                return;
            }
            if($this->publishmode==3){
                echo $smarty_output;
            }else{
                $this->generateInfoPage($this->outputfile, $errors);
            }

        }

    }


    /**
     * Outputs appropriate headers for the content type based on the output mode.
     */
    private function setOutputHeaders(){

            if($this->publishmode!=1){
                switch ($this->outputmode) {
                    case 'js': $mimetype = 'text/javascript'; break;
                    case 'txt': $mimetype = 'text/plain'; break;
                    case 'json': $mimetype = MIMETYPE_JSON; break;
                    default: $mimetype = 'text/'.$this->outputmode  ; break; //text/xml text/html
                }
                header("Content-type: $mimetype;charset=UTF-8");
            }

    }

    //
    //
    //
    private function generateInfoPage($file_name, $errors){

        header(CTYPE_HTML);

        if(isset($errors)){
            print $errors;
            return;
        }

        $gparams = $this->params;

        $url = htmlspecialchars($this->system->getSysUrl(DIR_GENERATED_REPORTS) . $file_name);
?>
<!DOCTYPE>
<html lang="en">
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <link rel="stylesheet" type="text/css" href="../../h4styles.css">
    <title>File generated</title>
</head>
<body style="margin: 25px;">
<div>
    The following file has been updated:  <a href="<?php echo $url; ?>" target="_blank" rel="noopener"><?php echo $url;?></a>
</div>
<br>

<?php
$rps_recid = @$gparams['rps_id']; //report schedule iD
if($rps_recid){

    //$link = str_replace('&amp;','&',htmlspecialchars(HEURIST_BASE_URL."?db=".$this->system->dbname()."&publish=3&template_id=".$rps_recid));
    
    $link = HEURIST_BASE_URL.'?db='.htmlspecialchars($this->system->dbname()).'&publish=3&template_id='.intval($rps_recid);
?>

    <p style="font-size: 14px;">Regenerate and view the file:<br><br>
    <?php echo strtoupper($this->outputmode); ?>: <a href="<?=$link?>" target="_blank"  rel="noopener" style="font-weight: bold;font-size: 0.9em;"><?=$link?></a><br><br>
    Javascript: <a href="<?=$link?>&mode=js" target="_blank" style="font-weight: bold;font-size: 0.9em;"><?=$link?>&mode=js</a><br>

<?php
}

// code for insert of dynamic report output - duplication of functionality in repMenu.html
$surl = HEURIST_BASE_URL."?db=".$this->system->dbname().
"&ver=".$gparams['ver']."&w=".$gparams['w']."&q=".$gparams['q'].
"&template=".$gparams['template'];

if(@$gparams['rules']){
    $surl = $surl."&rules=".$gparams['rules'];
}
if(@$gparams['h4']){
    $surl = $surl."&h4=".$gparams['h4'];
}

$surl = str_replace('&amp;','&',htmlspecialchars($surl, ENT_QUOTES));


$surl2 = $surl.'&mode=js';
if($this->outputmode!='html'){
    $surl = $surl.'&mode='.$this->outputmode;
}


?><br>
To publish the report as dynamic (generated on-the-fly) output, use the code below.
<br><br>
URL:<br>
<textarea readonly style="border: 1px dotted gray; padding: 3px; margin: 2px; font-family: times; font-size: 10px;"
    id="code-textbox1" onClick="select(); if (window.clipboardData) clipboardData.setData('Text', value);" rows="3" cols="150"><?php echo $surl;?></textarea>

<br>
Javascript wrap:<br>
<textarea readonly style="border: 1px dotted gray; padding: 3px; margin: 2px; font-family: times; font-size: 10px;"
    id="code-textbox2" onClick="select(); if (window.clipboardData) clipboardData.setData('Text', value);" rows="5" cols="150">
    <script type="text/javascript" src="<?php echo $surl2;?>"></script><noscript><iframe title width="80%" height="70%" frameborder="0" src="<?php echo $surl;?>"></iframe></noscript>
</textarea>

</p></body></html>
                    <?php
    }


    //
    // Runtime tag - smarty plugin function
    //
    // progress call back
    //
    public function progressCallback($params, &$smarty){

        if($this->publishmode!=0 || $this->smartySessionId==null){ //check that this call from ui
            return false;
        }

        $res = false;

            if(@$params['done']==null){//not set, this is execution from smarty
                $this->executionCounter++;
            }else{
                $this->executionCounter = @$params['done'];
            }

            if(isset($this->executionCounterTotal) && $this->executionCounterTotal>0){
                $tot_count = $this->executionCounterTotal;
            }elseif(@$params['tot_count']>=0){
                $tot_count = $params['tot_count'];
            }else{
                $tot_count = count(@$smarty->getVariable('results')->value);
            }

            if($this->executionCounter<2 || $this->executionCounter % 10==0 || $this->executionCounter>$tot_count-3){

                $session_val = $this->executionCounter.','.$tot_count;
                $current_val = mysql__update_progress(null, $this->smartySessionId, false, $session_val);
                if($current_val && $current_val=='terminate'){
                    $session_val = '';//remove from db
                    $res = true;
                }
            }

        return $res;


    }


    // Runtime tag - smarty plugin function
    //
    //  prints <div>label: value</div>
    //
    public function printLabelValuePair($params, &$smarty)
    {
        if($params['var']){
            return '<div><div class="tlbl">'.$params['lbl'].': </div><b>'.$params['var'].'</b></div>';
        }else{
            return '';
        }
    }

    // Runtime tag - smarty plugin function
    //
    //
    // {wrap var=$s.f8_originalvalue dt="file" width="100" height="auto" mode=""}
    //
    // $params - array of
    // var - value
    // dt - detail type: url, file, geo
    // mode - for dt=file only:   thumbnail, link or player by default
    // lbl - description
    // fancybox - fills rec_Files with file info, rec_Files will be data source for fancybox viewer
    // style or width,height
    // limit - limits output for multivalue fields
    //
    public function printProcessedValue($params, &$smarty)
    {
        if(!isset($params['var'])){
            return '';
        }


            if(array_key_exists('dt',$params)){
                $dt = $params['dt'];
            }
            if(array_key_exists('mode',$params)){
                $mode = $params['mode'];
            }else{
                $mode = null;
            }

            $label = "";
            if(array_key_exists('lbl',$params) && $params['lbl']!=""){
                $label = $params['lbl'];
            }
            $size = '';
            $mapsize = '';
            $style = '';

            if(array_key_exists('style',$params) && $params['style']!=""){

                $style = ' style="'.$params['style'].'"';

            }else{

                $width = "";
                $mapsize = "width=200";

                if(array_key_exists('width',$params) && $params['width']!=""){
                    $width = $params['width'];
                    if(is_numeric($width)<0){
                        $width = $width."px";
                        $mapsize = "width=".$width;
                    }
                }
                $height = "";
                if(array_key_exists('height',$params) && $params['height']!=""){
                    $height = $params['height'];
                    if(is_numeric($height)<0){
                        $height = $height."px";
                        $mapsize = $mapsize."&height=".$height;
                    }
                }
                if(strpos($mapsize,'&')===false){
                    $mapsize = $mapsize.'&height=200';
                }

                $size = '';
                if($width=='' && $height==''){
                    if($mode!='thumbnail'){
                        $size = "width=".(($dt=='geo')?"200px":"'300px'");
                    }
                }else {
                    if($width!=""){
                        $size = "width='".$width."'";
                    }
                    if($height!=""){
                        $size = $size." height='".$height."'";
                    }
                }
            }

            switch ($dt){
                case 'url':
                    $result = "<a href='{$params['var']}' target=_blank rel=noopener $style>{$params['var']}</a>";
                    break;
                case 'file':
                    $result = $this->processFieldFile($params, $mode, $style, $size);
                    break;
                case 'geo':
                    $value = $params['var'];
                    $result = '';

                    if($value && $value['wkt']){
                        $geom = \geoPHP::load($value['wkt'],'wkt');
                        if(!$geom->isEmpty()){
                                $point = $geom->centroid();
                                if($label=="") {$label = "on map";}
                                $result = '<a href="https://maps.google.com/maps?z=18&q='.$point->y().",".$point->x().'" target="_blank" rel="noopener">'.$label."</a>";

                            /* static maps by third party service is blocked 2024-09-29
                            if(array_key_exists('mode',$params) && $params['mode']=="link"){
                            }else{
                                $recid = $value['recid'];
                                $url = HEURIST_BASE_URL."viewers/gmap/mapStatic.php?".$mapsize."&q=ids:".$recid."&db=".$this->system->dbname();//"&t="+d;
                                return "<img src=\"".$url."\" ".$size."/>";
                            }
                            */
                        }
                    }
                    break;
                case 'date':
                    if($mode==null) {$mode = 1;}

                    $calendar = null;
                    if(array_key_exists('calendar',$params)){
                        $calendar = $params['calendar'];
                    }
                    if(is_array($params['var']) && array_key_exists(0,$params['var'])){
                        $params['var'] = $params['var'][0];
                    }

                    $content = \Temporal::toHumanReadable($params['var'], true, $mode, '|', $calendar);

                    if($label!="") {$label = $label.": ";}
                    $result = $label.$content.'<br>';
                    break;
                default:
                    //if this is CMS content
                    // 1. Extract HTML content from text elements [{"name":"Content","type":"text","css":{},"content":
                    // 2. Convert relative paths to absolute
                    if(is_string(@$params['var'])){
                        $content = json_decode($params['var'], true);
                    }else{
                        $content = @$params['var'];
                    }
                    if(is_array($content)){
                        $content = $this->prepareCMScontent($content);
                    }else{
                        $content = $this->prepareCMScontent($params['var']);
                    }

                    if($label!="") {$label = $label.": ";}
                    $result = $label.$content.'<br>';
            }

            return $result;
    }

    /**
    * Process file field and output img or player/viewer output
    *
    * @param mixed $params
    * @param mixed $mode
    * @param mixed $style
    * @param mixed $size
    */
    private function processFieldFile($params, $mode, $style, $size){
        //insert image or link
        $values = $params['var'];

        $limit = intval(@$params['limit']);

        $sres = "";

        if(!is_array($values) || !array_key_exists(0,$values)) {$values = array($values);}

        foreach ($values as $idx => $fileinfo){

            if($limit>0 && $idx>=$limit) {break;}

            $external_url = $fileinfo['ulf_ExternalFileReference'];//ulf_ExternalFileReference
            $originalFileName = $fileinfo['ulf_OrigFileName'];
            $file_nonce = $fileinfo['ulf_ObfuscatedFileID'];
            $file_desc = htmlspecialchars(strip_tags($fileinfo['ulf_Description']));
            $mimeType = $fileinfo['fxm_MimeType'];
            $file_Ext= $fileinfo['ulf_MimeExt'];

            /*in this version we use player tag  see fileGetPlayerTag
                $file_playerURL = HEURIST_BASE_URL.'?db='.$this->system->dbname().'&file='.$file_nonce.'&mode=tag';
            */
            $file_thumbURL  = HEURIST_BASE_URL.'?db='.$this->system->dbname().'&thumb='.$file_nonce;
            $file_URL   = HEURIST_BASE_URL.'?db='.$this->system->dbname().'&file='.$file_nonce; //download

            if($mode=="link") {

                $sname = (!$originalFileName || $originalFileName==ULF_REMOTE || strpos($originalFileName,ULF_IIIF)===0)
                ?$external_url:$originalFileName;

                if(@$params['fancybox']){
                    $sres = $sres."<a class=\"fancybox-thumb\" data-id=\"$file_nonce\" href='"
                    .$file_URL."' target=_blank rel=noopener title='".$file_desc."' $style>$sname</a>";
                }else{
                    $sres = $sres."<a href='$file_URL' target=_blank rel=noopener title='$file_desc' $style>$sname</a>";
                }

            }elseif($mode=="thumbnail"){

                if(@$params['fancybox']){
                    $sres .= "<img class=\"fancybox-thumb\" data-id=\"$file_nonce\" src=\"".$file_thumbURL."\" title=\"".$file_desc."\" $size $style/></a>";
                }else{
                    $sres = $sres."<a href='$file_URL' target=_blank rel=noopener>".
                    "<img class=\"\" src=\"".$file_thumbURL."\" title=\"".$file_desc."\" $size $style/></a>";
                }

            }else{ //player is default

                $sres = $sres.fileGetPlayerTag($this->system, $file_nonce, $mimeType, $params, $external_url, $size, $style);//see recordFile.php

            }

            if(@$params['fancybox'] && $this->isJsAllowed){

                $mode_3d_viewer = detect3D_byExt($file_Ext);

                $sres .= ('<script>if(rec_Files)rec_Files.push({'
                    .'rec_ID:'.$fileinfo['rec_ID']
                    .',id:"'.$file_nonce
                    .'",mimeType:"'.$mimeType
                    .'",mode_3d_viewer:"'.$mode_3d_viewer
                    .'",filename:"'.htmlspecialchars($originalFileName)
                    .'",external:"'.htmlspecialchars($external_url).'"});</script>');
            }

        }

        return $sres;
    }


    //
    //  Replace relative path to absolute
    //
    private function prepareCMScontent($content){

        $cnt = '';
        $convert_links = true;

        if(is_array($content)){

            if(@$content['type']=='group' && is_array(@$content['children'])){
                $convert_links = false;
                $cnt = $this->prepareCMSgroup($content['children']);
            }elseif(!@$content['type']=='text'){
                $convert_links = false;
                $cnt = $this->prepareCMSgroup($content);
            }elseif(@$content['type']=='text'){
                $cnt =  @$content['content'];
            }
        }else{
            $cnt = $content;
        }

        if($convert_links && $cnt!=null){
            $cnt = str_replace('./?db=',HEURIST_BASE_URL.'?db=',$cnt);
        }

        return $cnt;

    }

    private function prepareCMSgroup($content){
        $cnt = '';
        foreach($content as $grp){
            $res = $this->prepareCMScontent($grp);
            if($res){
                $cnt = $cnt.'<br>'.$res;
            }
        }
        return $cnt;
    }

    //
    // setParameters must be executed beforehand
    // $update_interval - in minutes
    //
    public function outputGeneratedReport($update_interval){

        $dir = $this->system->getSysDir(DIR_GENERATED_REPORTS);

        $this->outputfile = $this->prepareOutputFile();

        $generated_report = $dir.$this->outputfile;

        if(file_exists($generated_report)){

            if($update_interval>0){
                $dt1 = new \DateTime('now');
                $dt2 = new \DateTime();
                $dt2->setTimestamp(filemtime($generated_report));//get file time
                $interval = $dt1->diff( $dt2 );

                $tot_minutes = ($interval->days*1440 + $interval->h*60 + $interval->i);
                if($tot_minutes > $update_interval){
                    //generatated report is outdated
                    return 2; //existing and outdated
                }
            }

            $result = 3; //existing and up to date

            //request for current files (without smarty execution)
            $content = file_get_contents($generated_report);

            $this->outputfile = null; //to avoid save to file
            $this->handleTemplateOutput($content, false);


        }else{
            $result = 1; //need to create new report
        }

        return $result;
    }

}
