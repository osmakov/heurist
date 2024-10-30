<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* ExportRecords.php - abstract class to export records
*
* Controller is records_output
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

namespace hserv\records\export;

require_once dirname(__FILE__).'/../../../vendor/autoload.php';//for geoPHP and EasyRdf
require_once dirname(__FILE__).'/../../utilities/geo/mapSimplify.php';
require_once dirname(__FILE__).'/../../utilities/geo/mapCoordConverter.php';
require_once dirname(__FILE__).'/../../utilities/Temporal.php';
require_once dirname(__FILE__).'/../../structure/dbsTerms.php';

/**
 * Abstract class ExportRecords
 *
 * Handles the export of records from the system in multiple formats such as JSON, GeoJSON, XML, etc.
 * It supports fetching related records, applying various filters, and exporting to file or direct output.
 */
abstract class ExportRecords {

    /**
     * @var bool $initialized Indicates if the class has been initialized
     */
    private $initialized = false;

    /**
     * @var mixed $system The system instance used for database and system-wide operations
     */
    protected $system = null;

    /**
     * @var mysqli $mysqli The MySQLi connection instance
     */
    protected $mysqli = null;

    /**
     * @var array $records An array to store the records to be exported
     */
    protected $records;

    /**
     * @var array $rt_counts Counts of records grouped by record type ID (rty_ID => count)
     */
    protected $rt_counts;

    /**
     * @var string $tmp_destination Path to the temporary file for storing export data
     */
    private $tmp_destination;

    /**
     * @var resource $fd File handler for writing to the export file
     */
    protected $fd;

    /**
     * @var string $comma Separator used for JSON formatting
     */
    protected $comma = '';

    /**
     * @var string|null $retrieve_header_fields CSV of header fields to be retrieved
     */
    protected $retrieve_header_fields = null;

    /**
     * @var array|bool $retrieve_detail_fields Array of detail fields to be retrieved
     */
    protected $retrieve_detail_fields = false;

    /**
     * @var int $extended_mode Defines the output format for extended data
     * 0 = Heurist internal format
     * 1 = Interpretable format
     * 2 = Include concept code and labels
     * 3 = Format for media viewer
     */
    protected $extended_mode = 0;

    /**
     * @var array|null $defRecTypes Static cache for default record types
     */
    protected static $defRecTypes = null;

    /**
     * @var array|null $defDetailtypes Static cache for default detail types
     */
    protected static $defDetailtypes = null;

    /**
     * @var array|null $defTerms Static cache for default terms
     */
    protected static $defTerms = null;

    /**
     * Constructor for ExportRecords
     *
     * @param mixed $system System instance to be used by the class
     */
    public function __construct($system) {
        $this->setSession($system);
    }

    /**
     * Initializes the class by setting the system and database connection.
     * Ensures that initialization is only done once.
     */
    private function initialize() {
        if ($this->initialized) { return; }

        global $system;
        $this->system = $system;
        $this->mysqli = $system->get_mysqli();
        $this->initialized = true;
    }

    /**
     * Sets the session system instance and initializes the database connection.
     *
     * @param mixed $system System instance
     */
    public function setSession($system) {
        $this->system = $system;
        $this->mysqli = $system->get_mysqli();
        $this->initialized = true;
    }

    /**
     * Prepares the output by initializing, fetching records, and applying depth for linked records.
     *
     * @param array $data Record search response data
     * @param array $params Parameters controlling the export (format, depth, etc.)
     *
     * @return bool True if successful, false otherwise
     */
    protected function _outputPrepare($data, $params) {
        $this->initialize();

        if (!($data && @$data['status'] == HEURIST_OK)) {
            return false;
        }

        $data = $data['data'];

        if (@$data['memory_warning']) {//memory overflow in recordSearch
            $this->records = array();
        } elseif (!(@$data['reccount'] > 0)) {
            $this->records = array();
        } else {
            $this->records = $data['records'];
        }

        $this->rt_counts = array();
        $this->tmp_destination = tempnam(HEURIST_SCRATCHSPACE_DIR, "exp");
        $this->fd = fopen($this->tmp_destination, 'w');

        if ($this->fd === false) {
            $this->system->addError(HEURIST_SYSTEM_CONFIG, 'Failed to create temporary file in scratch folder');
            return false;
        }

        $this->extended_mode = intval(@$params['extended']) > 0 ? intval($params['extended']) : 0;

        // Handling depth and linked records
        $max_depth = 0;
        if (@$params['depth'] !== null) {
            $max_depth = (@$params['depth'] == 'all') ? 9999 : intval(@$params['depth']);
        }

        $direction = 0;
        $no_relationships = false;

        if (@$params['linkmode']) {
            switch ($params['linkmode']) {
                case 'none':
                    $max_depth = 0;
                    break;
                case 'direct':
                    $direction = 1;
                    break;
                case 'direct_links':
                    $direction = 1;
                    $no_relationships = true;
                    break;
                default:
                    $direction = 0;
                    break;
            }
        }

        if ($max_depth > 0) {
            $limit = ($params['format'] == 'gephi' && @$params['limit'] > 0) ? $params['limit'] : 0;
            recordSearchRelatedIds($this->system, $this->records, $direction, $no_relationships, 0, $max_depth, $limit);
        }

        return true;
    }

    /**
     * Detects which header and detail fields should be retrieved for export based on the given parameters.
     *
     * @param array $params Parameters controlling the retrieval of fields (columns, details, etc.)
     */
    protected function _outputPrepareFields($params) {
        $default_all_fields = true;
        $this->retrieve_header_fields = array();
        $this->retrieve_detail_fields = array();

        if (@$params['detail']) {
            $params['columns'] = is_array($params['detail']) ? $params['detail'] : explode(',', $params['detail']);
            $default_all_fields = false;
        }

        if (@$params['columns'] && is_array($params['columns'])) {
            foreach ($params['columns'] as $col_name) {
                if (is_array($col_name)) {
                    $col_name = $col_name['data'];
                }

                if (is_numeric($col_name) && $col_name > 0) {
                    array_push($this->retrieve_detail_fields, $col_name);
                } elseif (strpos($col_name, 'rec_') === 0) {
                    array_push($this->retrieve_header_fields, $col_name);
                }
            }
        }

        if (empty($this->retrieve_header_fields)) {
            $this->retrieve_header_fields = null; // Retrieve all header fields
        } else {
            if (!in_array('rec_RecTypeID', $this->retrieve_header_fields)) {
                array_unshift($this->retrieve_header_fields, 'rec_RecTypeID');
            }
            if (!in_array('rec_ID', $this->retrieve_header_fields)) {
                array_unshift($this->retrieve_header_fields, 'rec_ID');
            }
            $this->retrieve_header_fields = implode(',', $this->retrieve_header_fields);
        }

        $this->retrieve_detail_fields = !empty($this->retrieve_detail_fields) ? $this->retrieve_detail_fields : $default_all_fields;
    }

    /**
     * Outputs the header for the export (must be implemented by subclasses).
     */
    abstract protected function _outputHeader();

    /**
     * Outputs a single record for the export (must be implemented by subclasses).
     *
     * @param array $record The record data to be output
     */
    abstract protected function _outputRecord($record);

    /**
     * Outputs the footer for the export (must be implemented by subclasses).
     */
    abstract protected function _outputFooter();

    /**
    * Manages the entire export process by preparing data, outputting headers, records, and footers.
    * Also handles file compression and download, if specified.
    *
    * @param array $data Record search response data
    * @param array $params Parameters controlling the export
    *
    *    format - json|geojson|xml|gephi|iiif
    *    linkmode = direct, direct_links, none, all
    *    defs  0|1  include database definitions
    *    file  0|1
    *    filename - export into file with given name
    *    zip   0|1
    *    depth 0|1|2|...all
    *
    *    tlcmap 0|1  convert tlcmap records to layer
    *    restapi 0|1  - json output in format {records:[]}
    *
    * prefs for iiif
    *     version 2 or 3(default)

    * prefs for geojson, json
    *    extended 0 as is (in heurist internal format), 1 - interpretable, 2 include concept code and labels, 3 - for media viewer
    *
    *    datatable -   datatable session id  - returns json suitable for datatable ui component
    *              >1 and "q" is defined - save query request in session to result set returned,
    *              >1 and "q" not defined and "draw" is defined - takes query from session
    *              1 - use "q" parameter
    *    columns - array of header and detail fields to be returned
    *    detail_mode  0|1|2  - 0- no details, 1 - inline, 2 - fill in "details" subarray
    *
    *    leaflet - 0|1 returns strict geojson and timeline data as two separate arrays, without details, only header fields rec_ID, RecTypeID and rec_Title
    *        geofields  - additional filter - get geodata from specified fields only (in facetsearch format rt:dt:rt:dt )
    *        suppress_linked_places - do not retriev geodata from linked places
    *        separate - do not create GeometryCollection for heurist record
    *    simplify  0|1 simplify  paths with more than 1000 vertices
    *
    *    limit for leaflet and gephi only
    *
    *
    *
    * @return bool True if successful, false otherwise
    */
    public function output($data, $params) {
        if (!$this->_outputPrepare($data, $params)) {
            return false;
        }

        $this->_outputPrepareFields($params);
        $this->_outputHeader();

        //MAIN LOOP  ----------------------------------------
        foreach ($this->records as $record) {
            $recID = is_array($record) ? $record['rec_ID'] : $record;
            if (!is_array($record)) {
                $record = recordSearchByID($this->system, $recID, $this->retrieve_detail_fields, $this->retrieve_header_fields);
            }

            $rty_ID = @$record['rec_RecTypeID'];
            if ($rty_ID > 0) {
                $this->rt_counts[$rty_ID] = isset($this->rt_counts[$rty_ID]) ? $this->rt_counts[$rty_ID] + 1 : 1;
            }

            if (!$this->_outputRecord($record)) {
                break;
            }
        }

        //CLOSE brackets ----------------------------------------
        $this->_outputFooter();

        $this->_outputResult($params);

        return true;
    }

    private function _outputResult($params){

        $format = @$params['format'];
        if($format==null) {$format = 'xml';}
        if($format=='json' || $format=='geojson' || $format=='iiif' || @$params['serial_format']=='json'){
            $mimeType = CTYPE_JSON;
        }elseif(@$params['serial_format']=='ntriples' || @$params['serial_format']=='turtle'){ //$format=='rdf'
            $mimeType = CTYPE_HTML;
        }else {
            $mimeType = 'Content-Type: text/xml';
        }

        //
        // OUTPUT
        //
        if(@$params['zip']==1 || @$params['zip']===true){

            $output = gzencode(file_get_contents($this->tmp_destination), 6);
            fclose($this->fd);

            header('Content-Encoding: gzip');
            header($mimeType);

            fileDelete($this->tmp_destination);
            echo $output;
            unset($output);

            return;
        }

        //$content = stream_get_contents($this->fd);
        fclose($this->fd);

        //
        // download output as a file
        //
        if(@$params['filename'] || @$params['metadata']){

            $record = null;
            $originalFileName = null;
            if(@$params['metadata']){
                list($db_meta,$rec_ID) = explode('-',$params['metadata']);
                if(!$db_meta && $rec_ID) {$db_meta = $this->system->dbname();}

                $record = array("rec_ID"=>$rec_ID);
                if($db_meta!=$this->system->dbname()){
                    $this->system->init($db_meta, true, false);
                    //mysql__usedatabase($this->mysqli, $db_meta);
                }

                if($this->system->defineConstant('DT_NAME', true)){

                    recordSearchDetails($this->system, $record, array(DT_NAME));
                    if(is_array($record['details'][DT_NAME])){
                        $originalFileName = USanitize::sanitizeFileName(array_values($record['details'][DT_NAME])[0]);
                    }
                }
                if(!$originalFileName) {$originalFileName = 'Dataset_'.$record['rec_ID'];}

            }else{
                $originalFileName = $params['filename'];
            }


            //save into specified file in scratch folder
            $file_records  = $originalFileName.'.'.($format=='gephi'?'gexf':$format);

            //archive into zip
            $file_zip = $originalFileName.'.zip';
            $file_zip_full = tempnam(HEURIST_SCRATCHSPACE_DIR, "arc");
            $zip = new ZipArchive();
            if (!$zip->open($file_zip_full, ZIPARCHIVE::CREATE)) {
                $this->system->addError(HEURIST_SYSTEM_CONFIG, "Cannot create zip $file_zip_full");
                return false;
            }else{
                $zip->addFile($this->tmp_destination, $file_records);
            }

            // SAVE hml into file DOES NOT WORK - need to rewrite flathml
            if(@$params['metadata']){//save hml into scratch folder
                $zip->addFromString($originalFileName.'.txt',
                    recordLinksFileContent($this->system, $record));

            }
            $zip->close();
            //donwload
            $contentDispositionField = 'Content-Disposition: attachment; '
            . sprintf('filename="%s";', rawurlencode($file_zip))
            . sprintf("filename*=utf-8''%s", rawurlencode($file_zip));

            header('Content-Type: application/zip');
            header($contentDispositionField);
            header(CONTENT_LENGTH . getFileSize($file_zip_full));
            fileReadByChunks($file_zip_full);

            // remove the zip archive and temp files
            //unlink($file_zip_full);
            //unlink($file_metadata_full);
            fileDelete($this->tmp_destination);
            return;
        }

        if(@$params['restapi']){

            // Allow from any origin
            if (isset($_SERVER['HTTP_ORIGIN'])) {
                // Decide if the origin in $_SERVER['HTTP_ORIGIN'] is one
                // you want to allow, and if so:
                header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Max-Age: 5');// default value 5 sec
            }
            //2024-02-23 else header(HEADER_CORS_POLICY);
        }

        header($mimeType);

        if(@$params['file']==1 || @$params['file']===true){

            if($format=='iiif'){
                $filename = 'manifest_'.$params['db'].'_'.date("YmdHis").'.json';
            }else{
                $filename = 'Export_'.$params['db'].'_'.date("YmdHis").'.'.($format=='gephi'?'gexf':$format);
            }

            header('Content-Disposition: attachment; filename='.$filename);
            header(CONTENT_LENGTH . getFileSize($this->tmp_destination));
        }

        if(@$params['restapi']){

            if(empty($this->rt_counts)){
                http_response_code(404);
            }else{
                http_response_code(200);
            }
        }
        //read and download file
        fileReadByChunks($this->tmp_destination);
        fileDelete($this->tmp_destination);

    }

    //
    //
    //
    protected function _getDatabaseInfo(){

        //add database information to be able to load definitions later
        $dbID = $this->system->get_system('sys_dbRegisteredID');
        $database_info = array('id'=>$dbID,
                                'url'=>HEURIST_BASE_URL,
                                'db'=>$this->system->dbname());

        $query = 'select rty_ID,rty_Name,'
        ."if(rty_OriginatingDBID, concat(cast(rty_OriginatingDBID as char(5)),'-',cast(rty_IDInOriginatingDB as char(5))), concat('$dbID-',cast(rty_ID as char(5)))) as rty_ConceptID"
        .' from defRecTypes where rty_ID in ('.implode(',',array_keys($this->rt_counts)).')';
        $rectypes = mysql__select_all($this->system->get_mysqli(),$query,1);

        foreach($this->rt_counts as $rtid => $cnt){
            //include record types that are in output - name, ccode and count
            $this->rt_counts[$rtid] = array('name'=>$rectypes[$rtid][0],'code'=>$rectypes[$rtid][1],'count'=>$cnt);
        }
        $database_info['rectypes'] = $this->rt_counts;

        return $database_info;
    }
} //end class
?>