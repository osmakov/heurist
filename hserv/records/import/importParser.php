<?php

/**
* importParser.php:  operations with uploaded import file (csv, kml)
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
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

/**
* Public methods
* saveToTempFile  - saves csv into temp file in scratch folder
* encodeAndGetPreview - check encoding, save file in new encoding and parse first x lines for preview
* parseAndValidate - read file, remove spaces, convert dates, validate identifies/integers, find memo and multivalues
*
*/
use hserv\utilities\USanitize;
use hserv\utilities\UArchive;

define('ERR_MSG_NOT_READABLE',' is not readable');

class ImportParser {

    private static $system = null;
    private static $initialized = false;

private static function initialize()
{
    if (self::$initialized) {return;}

    global $system;
    self::$system  = $system;
    self::$initialized = true;
}

/**
*  STEP 0
*  save CSV from $content into temp file in scratch folder, returns filename
*                    (used to post pasted csv to server side)
*
*  returns  array( "filename"=> temp file name );
*/
public static function saveToTempFile($content, $extension='csv'){

    self::initialize();

    if(!$content){
        self::$system->addError(HEURIST_INVALID_REQUEST, "Parameter 'data' is missing");
        return false;
    }

    //check if scratch folder exists
    $res = folderExistsVerbose(HEURIST_SCRATCH_DIR, true, 'scratch');
// -1  not exists
// -2  not writable
// -3  file with the same name cannot be deleted

    if($res!==true){
        self::$system->addError(HEURIST_ACTION_BLOCKED, 'Cant save temporary file. '.$res);
        return false;
    }

    $upload_file_name = tempnam(HEURIST_SCRATCH_DIR, $extension);

    $res = file_put_contents($upload_file_name, trim($content));
    unset($content);
    if(!$res){
        self::$system->addError(HEURIST_ACTION_BLOCKED, 'Cant save temporary file '.$upload_file_name);
        return false;
    }

    $path_parts = pathinfo($upload_file_name);

    //$extension = strtolower(pathinfo($upload_file_name, PATHINFO_EXTENSION));
    //, 'isKML'=>($extension=='kml')

    return array( 'filename'=>$path_parts['basename'], 'fullpath'=>$upload_file_name);
}

//--------------------------------------
// STEP 1
/**
 * Encodes the uploaded file to UTF-8 if necessary and generates a preview by parsing the first X lines.
 *
 * @param string $upload_file_name The name of the uploaded file.
 * @param array $params Parameters that include the CSV encoding information.
 * @return bool|string Returns the preview data or false if an error occurs.
 */
public static function encodeAndGetPreview($upload_file_name, $params)
{
    self::initialize();

    $original_filename = basename($upload_file_name);
    $upload_file_name = HEURIST_SCRATCH_DIR . $upload_file_name;
    $contact_team = ' If the problem persists, please ' . CONTACT_HEURIST_TEAM . ' immediately';

    // Validate the uploaded file
    if (!self::validateUploadedFile($upload_file_name, $contact_team)) {
        return false;
    }

    // Handle KML and KMZ files directly
    $extension = strtolower(pathinfo($upload_file_name, PATHINFO_EXTENSION));
    if ($extension == 'kml' || $extension == 'kmz') {
        return self::parseAndValidate($upload_file_name, $original_filename, 3, $params);
    }

    // Read and validate file header
    $line = self::readFileHeader($upload_file_name);
    if (!$line) {
        return false;
    }

    // Detect and convert encoding if necessary
    $encoded_file_name = self::convertEncodingIfNeeded($upload_file_name, $original_filename, $params, $line);
    if (!$encoded_file_name) {
        return false;
    }

    // Parse and validate the file content
    return self::parseAndValidate($encoded_file_name, $original_filename, 1000, $params);
}

/**
 * Validates the uploaded file existence, readability, and checks for errors.
 *
 * @param string $upload_file_name The name of the uploaded file.
 * @param string $contact_team The contact message for error reporting.
 * @return bool Returns true if the file is valid, false if an error occurs.
 */
private static function validateUploadedFile($upload_file_name, $contact_team)
{
    if (!$upload_file_name) {
        $error = errorWrongParam('File') . '<br><br>' . $contact_team;
    } elseif (!file_exists($upload_file_name)) {
        $error = ' does not exist.<br><br>Please clear your browser cache and try again. ' . $contact_team;
    } elseif (!is_readable($upload_file_name)) {
        $error = ERR_MSG_NOT_READABLE;
    }

    if (isset($error)) {
        self::$system->addError(HEURIST_ACTION_BLOCKED, 'Temporary file (uploaded csv data) ' . $upload_file_name . $error);
        return false;
    }

    return true;
}

/**
 * Reads the first line of the uploaded file as the header for further processing.
 *
 * @param string $upload_file_name The name of the uploaded file.
 * @return string|bool Returns the first line of the file or false if an error occurs.
 */
private static function readFileHeader($upload_file_name)
{
    $handle = @fopen($upload_file_name, "r");
    if (!$handle) {
        self::$system->addError(HEURIST_ACTION_BLOCKED, 'Can\'t open temporary file (uploaded csv data) ' . $upload_file_name);
        return false;
    }

    setlocale(LC_ALL, 'en_US.utf8'); // Set locale to handle encoding
    $line = fgets($handle, 1000000);
    fclose($handle);

    if (!$line) {
        self::$system->addError(HEURIST_ACTION_BLOCKED, 'Empty header line');
        return false;
    }

    return $line;
}

/**
 * Detects and converts file encoding to UTF-8 if necessary.
 *
 * @param string $upload_file_name The name of the uploaded file.
 * @param string $original_filename The original filename of the uploaded file.
 * @param array $params Parameters that include the CSV encoding information.
 * @param string $line The first line of the file (header).
 * @return string|bool Returns the name of the encoded file or false if an error occurs.
 */
private static function convertEncodingIfNeeded($upload_file_name, $original_filename, $params, $line)
{
    $csv_encoding = $params['csv_encoding'] ?? null;

    if ($csv_encoding != 'UTF-8') {
        $content = file_get_contents($upload_file_name);

        if (!$csv_encoding) {
            $csv_encoding = mb_detect_encoding($content); // Automatically detect encoding
        }

        // Convert file content to UTF-8
        if ($csv_encoding && $csv_encoding != 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $csv_encoding);
        } else {
            $content = mb_convert_encoding($content, 'UTF-8');
        }

        if (!$content) {
            self::$system->addError(HEURIST_ACTION_BLOCKED, 'Your file can\'t be converted to UTF-8. '
                . 'Please select the appropriate encoding or save it with UTF-8 encoding.');
            return false;
        }

        // Save the converted content to a temporary file
        $encoded_file_name = tempnam(HEURIST_SCRATCH_DIR, $original_filename);
        if (!file_put_contents($encoded_file_name, $content)) {
            self::$system->addError(HEURIST_ACTION_BLOCKED, 'Cannot save temporary file (with UTF-8 encoded CSV data) ' . $encoded_file_name);
            return false;
        }

        return $encoded_file_name;
    }

    return $upload_file_name; // No encoding change needed
}

//--------------------------------------
// STEP 2
//
// $encoded_filename - csv data in UTF8 - full path
// $original_filename - originally uploaded filename
// $limit if >0 returns first X lines, otherwise try to parse, validate, save it into $prepared_filename
//                        and pass data to save to database
// $params
//    keyfield,datefield,memofield
//    csv_dateformat
//    csv_mvsep,csv_delimiter,csv_linebreak,csv_enclosure
//
// read file, remove spaces, convert dates, validate identifies/integers, find memo and multivalues
// if there are no errors and $limit=0 invokes  saveToDatabase - to save prepared csv into database
//
public static function parseAndValidate($encoded_filename, $original_filename, $limit, $params){

    self::initialize();

    if(is_numeric($encoded_filename) && intval($encoded_filename)>0){
        $id = intval($encoded_filename);
        $encoded_filename = ImportParser::_getEncodedFilename( $id );
    }

    $is_kml_data = (@$params["kmldata"]===true);
    $is_csv_data = (@$params["csvdata"]===true);
    $extension = null;

    if($is_kml_data){
        $extension = 'kml';
    }elseif(!$is_csv_data) {

        $s = null;
        if(!$encoded_filename){
            $encoded_filename = '';
            $s = ' not defined';
        }elseif (! file_exists($encoded_filename)) {$s = ' does not exist';}
        elseif (! is_readable($encoded_filename)) {$s = ERR_MSG_NOT_READABLE;}
        if($s){
            self::$system->addError(HEURIST_ACTION_BLOCKED, 'Temporary file '.$encoded_filename. $s);
            return false;
        }

        $extension = strtolower(pathinfo($encoded_filename, PATHINFO_EXTENSION));
    }
    $isKML = ($extension=='kml' || $extension=='kmz');

    $err_colnums = array();
    $err_encoding = array();
    $err_keyfields = array();
    $err_encoding_count = 0;

    $int_fields = array();// array of fields with integer values
    $num_fields = array();// array of fields with numeric values
    $empty_fields = array();// array of fields with NULL/empty values
    $empty75_fields = array();// array of fields with NULL/empty values in 75% of lines

    //$memos = array();
    $field_sizes = array();//keeps max length for all fields (or memo for len>1000 or multiline fields)
    $multivals = array();
    $parsed_values = array();



    // fields that were marked by user as particular type
    $keyfields  = @$params["keyfield"];
    $datefields = @$params["datefield"];
    $memofields = @$params["memofield"];

    if(!$keyfields) {$keyfields = array();}
    if(!$datefields) {$datefields = array();}
    if(!$memofields) { $memofields = array();}

    $csv_dateformat = @$params["csv_dateformat"];

    $check_datefield = (!isEmptyArray($datefields));
    $check_keyfield = (!isEmptyArray($keyfields));

    $len = 0;
    $header = null;
    $handle_wr = null;

    if($limit==0){ //if limit no defined prepare data and write into temp csv file
        //get filename for prepared filename with converted dates and removed spaces
        //    $encoded_filename = basename($encoded_filename);
        $prepared_filename = tempnam(HEURIST_SCRATCH_DIR, "prepared");//basename($encoded_filename)
        if (!is_writable($prepared_filename)) {
            self::$system->addError(HEURIST_ACTION_BLOCKED, 'Cannot save prepared data: '.$prepared_filename);
            return false;

        }
        if (!$handle_wr = fopen($prepared_filename, 'w')) {
            self::$system->addError(HEURIST_ACTION_BLOCKED, 'Cannot open file to save prepared data: '.$prepared_filename);
            return false;
        }
    }

    if($isKML){

        if($extension=='kmz'){

            $files = UArchive::unzipFlat($encoded_filename, HEURIST_SCRATCH_DIR);

            foreach($files as $filename){
                if(strpos(strtolower($filename),'.kml')==strlen($filename)-4){
                    $encoded_filename = $filename;
                }else{
                    unlink( $filename );
                }
            }
        }

        if($is_kml_data){
            $kml_content =  $encoded_filename;
            $encoded_filename = null;
        }else{
            $encoded_filename = HEURIST_SCRATCH_DIR.basename($encoded_filename);//for snyk
            $kml_content = file_get_contents($encoded_filename);
        }


        // Change tags to lower-case
        preg_match_all('%(</?[^? ><![]+)%m', $kml_content, $result, PREG_PATTERN_ORDER);
        $result = $result[0];

        $result = array_unique($result);
        sort($result);
        $result = array_reverse($result);

        foreach ($result as $search) {
          $replace = mb_strtolower($search, mb_detect_encoding($search));
          $kml_content = str_replace($search, $replace, $kml_content);
        }

        // Load into DOMDocument
        $xmlobj = new DOMDocument();
        //@
        $xmlobj->loadXML($kml_content);
        if ($xmlobj === false) {
            self::$system->addError(HEURIST_ACTION_BLOCKED, 'Invalid KML '.($is_kml_data?'data':('file '.$encoded_filename)));
            return false;
        }

        $geom_types = geoPHP::geometryList();
        $placemark_elements = $xmlobj->getElementsByTagName('placemark');
        $line_no = 0;
        if ($placemark_elements && $placemark_elements->length) {
          foreach ($placemark_elements as $placemark) {
                $properties = self::parseKMLPlacemark($placemark, $geom_types);
                if($properties==null) {continue;}
                if($line_no==0){
                    $fields = array_keys($properties);
                    //always add geometry, timestamp, timespan_begin, timespan_end
                    if(!@$fields['geometry']) {$fields[] = 'geometry';}
                    if(!@$fields['timestamp']) {$fields[] = 'timestamp';}
                    if(!@$fields['timespan_begin']) {$fields[] = 'timespan_begin';}
                    if(!@$fields['timespan_end']) {$fields[] = 'timespan_end';}

                    $header = $fields;
                    $len = count($fields);
                    $int_fields = $fields; //assume all fields are integer
                    $num_fields = $fields; //assume all fields are numeric
                    $empty_fields = $fields; //assume all fields are empty
                    $empty75_fields = array_pad(array(),$len,0);
                }

                $k=0;
                $newfields = array();
                $line_values = array();
                foreach($header as $field_name){

                    $field = @$properties[$field_name];

                    if($field==null) {$field='';}

                    //Identify repeating value fields and flag - will not be used as key fields
                    if($field_name=='geometry'){
                        $int_fields[$k] = null;
                        $num_fields[$k] = null;
                        $empty_fields[$k] = null;
                        $field_sizes[$k] = 'memo';
                    }else{

                        if( !in_array($k, $multivals) && strpos($field, '|')!==false ){
                            array_push($multivals, $k);
                        }

                        $field = trim($field);

                        //get field size before import to temp table
                        if($limit==0 && @$field_sizes[$k]!=='memo'){
                            if(in_array($k, $memofields)){
                                $field_sizes[$k] = 'memo';
                            }else {
                                $flen = strlen($field);
                                if ($flen>500 || strpos($field, '\\r')!==false) {
                                    $field_sizes[$k] = 'memo';
                                }elseif(@$field_sizes[$k]>0){
                                    $field_sizes[$k] = max($field_sizes[$k], $flen);//select max
                                }else{
                                    $field_sizes[$k] = $flen;
                                }
                            }
                        }

                        //Remove any spaces at start/end of fields (including potential memos) & any redundant spaces in field that is not multi-line
                        if(@$field_sizes[$k]!=='memo'){

                            $field = trim(preg_replace('/([\s])\1+/', ' ', $field));

                            //Convert dates to standardised format.  //'field_'.
                            if($check_datefield && @$datefields[$k]!=null && $field!=""){
                                $field = self::prepareDateField($field, $csv_dateformat);

                                $field_sizes[$k] = max($field_sizes[$k],strlen($field));
                            }

                            $check_keyfield_K = ($check_keyfield && @$keyfields['field_'.$k]!=null);
                            if($check_keyfield_K || @$int_fields[$k]){
                                //check integer value
                                if(@$int_fields[$k]){
                                    self::prepareIntegerField($field, $k, $check_keyfield_K, $err_keyfields, $int_fields);
                                }
                                if($check_keyfield_K){
                                    $field_sizes[$k] = max($field_sizes[$k], 9);
                                }
                            }
                            if(@$num_fields[$k] && !is_numeric($field)){
                                $num_fields[$k]=null;
                            }
                        }

                        if($field==null || $field==''){
                            $empty75_fields[$k]++;
                        }elseif(@$empty_fields[$k]){//not empty
                            $empty_fields[$k]=null;
                        }

                    }//not geometry

                    //Doubling up as an escape for quote marks
                    $field = addslashes($field);
                    array_push($line_values, $field);
                    $field = '"'.$field.'"';
                    array_push($newfields, $field);
                    $k++;
                }//foreach field value

                    $line_no++;

                    if ($handle_wr){
                        $line = implode(',', $newfields)."\n";

                        if (fwrite($handle_wr, $line) === false) {
                            return "Cannot write to file $prepared_filename";
                        }

                    }else {
                        array_push($parsed_values, $line_values);
                        if($line_no>$limit){
                            break; //for preview
                        }
                    }

          }//foreach
        }//for placemarks


        $csv_enclosure = '"';
        $csv_mvsep = '|';

    }
    else{   //CSV

        $csv_mvsep     = @$params["csv_mvsep"];
        $csv_delimiter = @$params["csv_delimiter"];
        $csv_linebreak = @$params["csv_linebreak"];
        if(@$params["csv_enclosure"]==1){
            $csv_enclosure = "'";
        }elseif(@$params["csv_enclosure"]=='none'){
            $csv_enclosure = 'ʰ';//rare character
        }else {
            $csv_enclosure = '"';
        }

        if($csv_delimiter=='tab') {
            $csv_delimiter = "\t";
        }elseif($csv_delimiter==null) {
            $csv_delimiter = ",";
        }
        
        //always autodetect
        $lb = null;
        ini_set('auto_detect_line_endings', 'true');
        $csv_linebreak = 'auto';
        $check_encoding = false;  //since $encoded_filename already utf-8
        
        /* 2024-10-31 - always autodetect
        if($csv_linebreak=='auto'){
            ini_set('auto_detect_line_endings', 'true');
            $lb = null;
        }elseif($csv_linebreak=='win'){
            $lb = "\r\n";
        }elseif($csv_linebreak=='nix'){
            $lb = "\n";
        }elseif($csv_linebreak=='mac'){
            $lb = "\r";
        }
        */

        if($is_csv_data){
            $limitMBs = 10 * 1024 * 1024;
            $handle = fopen("php://temp/maxmemory:$limitMBs", 'r+');
            fputs($handle, $encoded_filename);
            rewind($handle);
        }else{
            $handle = @fopen($encoded_filename, "r");
            if (!$handle) {
                self::$system->addError(HEURIST_ACTION_BLOCKED, 'Temporary file '.$encoded_filename.' could not be read');
                return false;
            }
        }
        //fgetcsv и str_getcsv depends on server locale
        // it is possible to set it in  /etc/default/locale (Debian) or /etc/sysconfig/i18n (CentOS)  LANG="en_US.UTF-8"
        setlocale(LC_ALL, 'en_US.utf8');

        $line_no = 0;
        while (!feof($handle)) {
            
            if($check_encoding){
                
                if($lb==null){
                    $line = fgets($handle, 1000000);//read line and auto detect line break
                }else{
                    $line = stream_get_line($handle, 1000000, $lb);
                }

                if(!mb_detect_encoding($line, 'UTF-8', true)){
                    $err_encoding_count++;
                    if(count($err_encoding)<100){
                        $line = mb_convert_encoding( substr($line,0,2000), 'UTF-8');//to send back to client
                        array_push($err_encoding, array("no"=>($line_no+2), "line"=>htmlspecialchars($line)));
                    }
                }

                $fields = str_getcsv ( $line, $csv_delimiter, $csv_enclosure );// $escape = "\\"
            }else{
                $fields = fgetcsv($handle, 1000000, $csv_delimiter, $csv_enclosure, "\\" );  
                if(!$fields){
                    break; //end of file
                }
                $line = implode($csv_delimiter, $fields);
            }
            
            if($len==0){ //first line is header with field names
                $header = $fields;

                $len = count($fields);

                if($len>200){
                    fclose($handle);
                    if($handle_wr) {fclose($handle_wr);}

                    self::$system->addError(HEURIST_ACTION_BLOCKED,
                        "Too many columns ".$len."  This probably indicates that you have selected the wrong separator or end-of-line type.");
                    return false;
                }

                $int_fields = $fields; //assume all fields are integer
                $num_fields = $fields; //assume all fields are numeric
                $empty_fields = $fields; //assume all fields are empty
                $empty75_fields = array_pad(array(),$len,0);

            }
            else{
                $line_no++;

                if(trim($line)=="") {continue;}

                if($len!=count($fields)){        //number of columns differs from header
                    // Add error to log if wrong field count
                    array_push($err_colnums, array("cnt"=>count($fields), "no"=>$line_no, "line"=>htmlspecialchars(substr($line,0,2000))));
                    if(count($err_colnums)>100) {break;} //too many mistakes
                }else{
                    $k=0;
                    $newfields = array();
                    $line_values = array();
                    foreach($fields as $field){

                        //Identify repeating value fields and flag - will not be used as key fields
                        if( !in_array($k, $multivals) && strpos($field, '|')!==false ){
                            array_push($multivals, $k);
                        }

                        $field = USanitize::cleanupSpaces($field);

                        //get field size before import to temp table
                        if($limit==0 && @$field_sizes[$k]!=='memo'){
                            if(in_array($k, $memofields)){
                                $field_sizes[$k] = 'memo';
                            }else {
                                $flen = strlen($field);
                                if ($flen>500 || strpos($field, '\\r')!==false) {
                                    $field_sizes[$k] = 'memo';
                                }elseif(@$field_sizes[$k]>0){
                                    $field_sizes[$k] = max($field_sizes[$k], $flen);
                                }else{
                                    $field_sizes[$k] = $flen;
                                }
                            }
                        }

                        if(@$field_sizes[$k] !== 'memo'){

                            //Convert dates to standardised format.  //'field_'.
                            if($check_datefield && @$datefields[$k]!=null && $field!=""){
                                $field = self::prepareDateField($field, $csv_dateformat);
                                $field_sizes[$k] = max($field_sizes[$k],strlen($field));
                            }

                            $check_keyfield_K =  ($check_keyfield && @$keyfields['field_'.$k]!=null);
                            //check integer value
                            if($check_keyfield_K || @$int_fields[$k]){
                                //check integer value
                                if(@$int_fields[$k]){
                                    self::prepareIntegerField($field, $k, $check_keyfield_K, $err_keyfields, $int_fields);
                                }
                                if($check_keyfield_K){ //key field cannot be size less than 6
                                    $field_sizes[$k] = max($field_sizes[$k], 9);
                                }
                            }

                            if(@$num_fields[$k] && !is_numeric($field)){
                                $num_fields[$k]=null;
                            }
                        }

                        if($field==null || $field==''){
                             $empty75_fields[$k]++;
                        }elseif(@$empty_fields[$k]){//field has value
                             $empty_fields[$k]=null;
                        }

                        //Doubling up as an escape for quote marks
                        $field = addslashes($field);
                        array_push($line_values, $field);
                        $field = '"'.$field.'"';
                        array_push($newfields, $field);
                        $k++;
                    }

                    if ($handle_wr){
                        $line = implode(',', $newfields)."\n";

                        if (fwrite($handle_wr, $line) === false) {
                            self::$system->addError(HEURIST_ACTION_BLOCKED, "Cannot write to file $prepared_filename");
                            return false;
                        }

                    }else {
                        array_push($parsed_values, $line_values);
                        if($line_no>$limit){
                            break; //for preview
                        }
                    }
                }
            }

        } //while
        fclose($handle);

    }




    if($handle_wr) {fclose($handle_wr);}

    //???? unlink($encoded_filename);
    $empty75 = array();
    $lines75 = $line_no*0.75;
    foreach ($empty75_fields as $k=>$cnt){
        if($cnt>=$lines75) {$empty75[$k] = $cnt;}
    }
    /*$empty_fields = array_keys($empty_fields);
    $int_fields = array_keys($int_fields);
    $num_fields = array_keys($num_fields);*/


    if($limit>0){

        $encoded_filename_id = ImportParser::_saveEncodedFilename($encoded_filename);

        // returns reference to encoded filename  and parsed values for given limit lines
        return array(
                'encoded_filename_id'=>$encoded_filename_id,   //full path
                'original_filename'=>$original_filename, //filename only
                'step'=>1, 'col_count'=>$len,
                'err_colnums'=>$err_colnums,
                'err_encoding'=>$err_encoding,
                'err_encoding_count'=>$err_encoding_count,

                'int_fields'=>$int_fields,
                'empty_fields'=>$empty_fields,
                'num_fields'=>$num_fields,
                'empty75_fields'=>$empty75,

                'fields'=>$header, 'values'=>$parsed_values );
    }else{

        if( !empty($err_colnums)
            || !empty($err_encoding)
            || !empty($err_keyfields)){
            //we have errors - delete temporary prepared file
            if(file_exists($prepared_filename)) {unlink($prepared_filename);}

            return array( 'step'=>2, 'col_count'=>$len,
                'err_colnums'=>$err_colnums,
                'err_encoding'=>$err_encoding,
                'err_keyfields'=>$err_keyfields,
                'err_encoding_count'=>$err_encoding_count,

                'int_fields'=>$int_fields,
                'num_fields'=>$num_fields,
                'empty_fields'=>$empty_fields,
                'empty75_fields'=>$empty75,

                'field_sizes'=>$field_sizes, 'multivals'=>$multivals, 'fields'=>$header );
        }else{
            //everything ok - proceed to save into db
            $encoded_filename_id = ImportParser::_saveEncodedFilename($encoded_filename);

            $preproc = array();
            //$preproc['prepared_filename'] = $prepared_filename; rearked to avoid sql injection warning
            $preproc['encoded_filename_id']  = $encoded_filename_id;
            $preproc['original_filename'] = $original_filename;  //filename only
            $preproc['fields'] = $header;
            $preproc['field_sizes']  = $field_sizes;
            $preproc['multivals'] = $multivals;
            $preproc['keyfields'] = $keyfields; //indexes => "field_3":"10",

            $preproc['csv_enclosure'] = $csv_enclosure;
            $preproc['csv_mvsep'] = $csv_mvsep;

            $res = self::saveToDatabase($preproc, $prepared_filename);
            //delete prepare
            if(file_exists($prepared_filename)) {unlink($prepared_filename);}
            if($res!==false){
                //delete encoded
                ImportParser::_deleteEncodedFilename($encoded_filename_id);
                if(file_exists($encoded_filename)) {unlink($encoded_filename);}
                //delete original
                $upload_file_name = HEURIST_SCRATCH_DIR.basename($original_filename);
                if(file_exists($upload_file_name)) {unlink($upload_file_name);}
            }
            return $res;
        }
    }

}

/**
 * Saves the encoded filename during the CSV import session.
 * Creates a session table if it doesn't exist and cleans up old entries.
 *
 * @param string $encoded_filename The name of the encoded file to be saved.
 * @return int|bool Returns the ID of the saved record on success, false on failure.
 */
private static function _saveEncodedFilename($encoded_filename)
{
    // Validate the file
    if ($encoded_filename == null || !file_exists($encoded_filename)) {
        return false;
    }

    $mysqli = self::$system->getMysqli();

    // Check if the session table exists
    $is_exist = hasTable($mysqli, 'import_tmp_file');
    if (!$is_exist) {
        // Create the session table if it doesn't exist
        $query = "CREATE TABLE `import_tmp_file` (
            `imp_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `imp_Date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `imp_filename` VARCHAR(500) NOT NULL,
            PRIMARY KEY (`imp_ID`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        if (!$mysqli->query($query)) {
            self::$system->addError(HEURIST_DB_ERROR, "Cannot create import session table", $mysqli->error);
            return false;
        }
    } else {
        // Retrieve filenames older than 2 days for cleanup
        $filenames = mysql__select_list2($mysqli, 'SELECT imp_filename FROM `import_tmp_file` WHERE imp_Date < NOW() - INTERVAL 2 DAY');

        // Cleanup old files
        foreach ($filenames as $fname) {
            $fname = HEURIST_SCRATCH_DIR . basename($fname);
            fileDelete($fname);
        }

        // Delete old entries from the session table
        $query = 'DELETE FROM `import_tmp_file` WHERE imp_Date < NOW() - INTERVAL 2 DAY';
        $mysqli->query($query);
    }

    // Insert the new encoded filename into the session table
    $res = mysql__insertupdate($mysqli, 'import_tmp_file', 'imp', array('imp_filename' => basename($encoded_filename)));

    if (isPositiveInt($res)) {
        return $res;
    }

    self::$system->addError(HEURIST_DB_ERROR, "Cannot add into import session table", $res);
    return false;
}

private static function _getEncodedFilename($encoded_filename_id){
    $mysqli = self::$system->getMysqli();
    $encoded_filename = mysql__select_value($mysqli,
        'SELECT imp_filename FROM `import_tmp_file` WHERE imp_ID='.intval($encoded_filename_id));

    return HEURIST_SCRATCH_DIR.basename($encoded_filename);
}

private static function _deleteEncodedFilename($encoded_filename_id){
    $mysqli = self::$system->getMysqli();
    $query = 'DELETE FROM `import_tmp_file` WHERE imp_ID='.intval($encoded_filename_id);
    $mysqli->query($query);
}

//
// $csv_dateformat 1 - dd/mm/yyyy,  2 - mm/dd/yyyy
//
private static function prepareDateField($field, $csv_dateformat){

    $t3 = Temporal::dateToISO($field, $csv_dateformat);//@todo - parse simple range

    if($t3!='Temporal' && $t3!=null){ //do not change if temporal
        $field = $t3;
    }

    return $field;
}

/**
 * Prepares and validates integer field values by checking for non-integer values,
 * negative values, or values that exceed MySQL's maximum integer size.
 * Updates the error key fields and integer fields arrays accordingly.
 *
 * @param string $field The field value to be processed.
 * @param mixed $k The key used to identify the field.
 * @param bool $check_keyfield_K A flag to determine if key field checks should be performed.
 * @param array &$err_keyfields An array to store error details for invalid key fields.
 * @param array &$int_fields An array of fields that are valid integers.
 * @return void
 */
private static function prepareIntegerField($field, $k, $check_keyfield_K, &$err_keyfields, &$int_fields){

    if($field==''){
        $field=0; //workaround empty values for key fields
    }

    $values = explode('|', $field);
    foreach($values as $value){
        if($value=='' || $value==0) {continue;}

        if(!isPositiveInt($value)){ //noy integer
            $idx = 1;
        }elseif(intval($value)<0 || intval($value)>2147483646){ //max int value in mysql
            $idx = 0;
        }else{
            continue;
        }

        if($check_keyfield_K){

            if(!is_array(@$err_keyfields[$k])){
                $err_keyfields[$k] = array(array(), array());
            }
            if(count($err_keyfields[$k][$idx]) <= 20){
                $err_keyfields[$k][$idx][] = $value;
            }
        }

        //exclude from array of fields with integer values
        if(@$int_fields[$k]) {$int_fields[$k]=null;}
    }//foreach
}

//
//
//
private static function parseKMLPlacemark($placemark, &$geom_types){

        $nodeText = '#text';
        $regex_space = '/\n\s+/';

        $wkt = new WKT();
        $properties = array();
        $textnodes = array($nodeText, 'lookat', 'style', 'styleurl');

        foreach ($placemark->childNodes as $child) {
          // Node names are all the same, except for MultiGeometry, which maps to GeometryCollection
          $node_name = $child->nodeName == 'multigeometry' ? 'geometrycollection' : $child->nodeName;

          if (array_key_exists($node_name, $geom_types))
          {
            $adapter = new KML;
            $geometry = $adapter->read($child->ownerDocument->saveXML($child));
            $geometry = $wkt->write($geometry);
            $properties['geometry'] = $geometry;
          }
          elseif ($node_name == 'extendeddata')
          {

            foreach ($child->childNodes as $data) {
              if ($data->nodeName != $nodeText) {
                if ($data->nodeName == 'data') {
                  $items = $data->getElementsByTagName('value');//DOMNodeList
                  if($items->length>0){
                        //$items->item(0);
                        $value = preg_replace($regex_space,' ',trim($items[0]->textContent));
                  }else{
                        $value = '';
                  }
                  $properties[$data->getAttribute('name')] = $value;
                }
                elseif ($data->nodeName == 'schemadata')
                {
                  foreach ($data->childNodes as $schemadata) {
                    if ($schemadata->nodeName != $nodeText) {
                      $properties[$schemadata->getAttribute('name')] = preg_replace($regex_space,' ',trim($schemadata->textContent));
                    }
                  }
                }

              }
            }
          }
          elseif ($node_name == 'timespan'){
            foreach ($child->childNodes as $timedata) {
                if ($timedata->nodeName == 'begin') {
                    $properties['timespan_begin'] = preg_replace($regex_space,' ',trim($timedata->textContent));
                }elseif($timedata->nodeName == 'end') {
                    $properties['timespan_end'] = preg_replace($regex_space,' ',trim($timedata->textContent));
                }
            }
          }
          elseif (!in_array($node_name, $textnodes))
          {
            $properties[$child->nodeName] = preg_replace($regex_space,' ',trim($child->textContent));
          }

        }

        $ret = (@$properties['geometry'])?$properties:null;
        return $ret;
}

//
//  save content of file into import table, create session object and saves it to sysImportFiles table, returns session
//
private static function saveToDatabase($preproc, $prepared_filename=null){


    $filename = $prepared_filename;

    $s = null;
    if (! file_exists($filename)) {$s = ' does not exist';}
    elseif (! is_readable($filename)) {$s = ERR_MSG_NOT_READABLE;}

    if($s){
        self::$system->addError(HEURIST_UNKNOWN_ERROR, 'Source file '.$filename. $s);
        return false;
    }


    $import_table = "import".date("YmdHis");

    //create temporary table import_datetime
    $query = "CREATE TABLE `".$import_table."` (`imp_ID` int(10) unsigned NOT NULL AUTO_INCREMENT, ";
    $columns = "";
    $counts = "";
    $mapping = array();

    $len = count($preproc['fields']);
    do{
        $max_size = 0;
        $max_size_index = -1;
        $row_size = 10;
        for ($i = 0; $i < $len; $i++) {
            $size = @$preproc['field_sizes'][$i];
            if($size==='memo'){
                   $row_size += 12;
            }elseif($size>0){
                   $row_size += (2 + 4*$size);
                   if($size>$max_size){
                        $max_size = $size;
                        $max_size_index = $i;
                   }
            }else{
                   $row_size += 6;
            }
        }
        if($row_size>50000 && $max_size_index>=0){
            $preproc['field_sizes'][$max_size_index] = 'memo';
        }
    }while($row_size>50000);

    $row_size = 10;
    for ($i = 0; $i < $len; $i++) {

        $size = @$preproc['field_sizes'][$i];
        if($size==='memo'){
           $row_size += 12;
           $fieldtype = 'mediumtext';
        }else {
            $size = intval($size);
            if($size>0){
               $row_size += (2 + 4*$size);
               $fieldtype = 'varchar('.$size.')';
            }else{
               $row_size += 6;
               $fieldtype = 'varchar(1)';
            }
        }

        $query = $query."`field_".$i."` ".$fieldtype.', ' ;

        $columns = $columns."field_".$i.",";
        $counts = $counts."count(distinct field_".$i."),";
        //array_push($mapping,0);
    }

    if($row_size>50000){
        self::$system->addError(HEURIST_ACTION_BLOCKED,
            'Cannot create import table. Rows exceeding 64 KBytes. This limit is set by MySQL and cannot be changed. '
            .'Remove unused columns. ('.$row_size.')');
        return false;
    }

    $query = $query." PRIMARY KEY (`imp_ID`)) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;";//was utf8 this is alias utf8mb3

    $columns = substr($columns,0,-1);
    $counts = $counts." count(*) ";

    $mysqli = self::$system->getMysqli();

    if (!$mysqli->query($query)) {
        self::$system->addError(HEURIST_DB_ERROR, "Cannot create import table", $mysqli->error);
        return false;
    }

    //always " if($csv_enclosure=="'") {$csv_enclosure = "\\".$csv_enclosure;}

    $filename = $mysqli->real_escape_string($filename);
    /* real_escape_string does it
    if(strpos($filename,"\\")>0){
       $filename = str_replace("\\","\\\\",$filename);
    }
    if(strpos($filename,"'")>0){
        $filename = str_replace("'","\\'",$filename);
    }*/

    //allow_local_infile
    $mysqli->query('SET GLOBAL local_infile = true');
    //$mysqli->query('SET GLOBAL allow_local_infile = true');//for MySQL v8
    //load file into table  LOCAL
    $query = "LOAD DATA LOCAL INFILE '".$filename."' INTO TABLE ".$import_table
    ." CHARACTER SET utf8mb4"    //was UTF8 this is alias for utf8mb3
    ." FIELDS TERMINATED BY ',' "  //.$csv_delimiter."' "
    ." OPTIONALLY ENCLOSED BY  '\"' " //.$csv_enclosure."' "
    ." LINES TERMINATED BY '\n'"  //.$csv_linebreak."' "
    //." IGNORE 1 LINES
    ." (".$columns.")";

    $rres = $mysqli->query($query);

    if(!$rres){

        self::$system->addError(HEURIST_DB_ERROR, 'Unable to import data. '
.'Your MySQL system is not set up correctly for text file import. Please ask your system adminstrator to make the following changes:<br>'
.'<br><br>Add to  /etc/mysql/my.cnf'
.'<br>[mysqld] '
.'<br>local-infile = 1'
.'<br>[mysql] '
.'<br>local-infile = 1'
.'<br><br>Add to php.ini'
.'<br>mysqli.allow_local_infile = On', $mysqli->error);
//.'<br>2. Replace the driver php5-mysql by the native driver'
//.'<br><br>see: http://stackoverflow.com/questions/10762239/mysql-enable-load-data-local-infile', $mysqli->error);

//self::$system->addError(HEURIST_DB_ERROR, 'Unable to import data. MySQL command: "'.$query.'" returns error: '.$mysqli->error);
        return false;
    }

    $warnings = array();
    if ($info = $mysqli->info) {
        if ($mysqli->warning_count) {
            array_push($warnings, $info);
            $e = $mysqli->get_warnings();
            do {
                array_push($warnings, $e->message);//$e->errno.": ".
            } while ($e->next());
        }
    }

    //calculate unique values
    $query = "select ".$counts." from ".$import_table;
    $res = $mysqli->query($query);
    if (!$res) {
        self::$system->addError(HEURIST_DB_ERROR, 'Cannot count unique values', $mysqli->error);
        return false;
    }

    $uniqcnt = $res->fetch_row();
    $reccount = array_pop ( $uniqcnt );

    //add record to import_log
    $session = array("reccount"=>$reccount,
        "import_table"=>$import_table,
        "import_name"=>((substr($preproc['original_filename'],-4)=='.tmp'?'csv':$preproc['original_filename']).'  '.date('d M Y').'  '.date('H:i')),
        "columns"=>$preproc['fields'],   //names of columns in file header
        //"memos"=>$preproc['memos'],
        "multivals"=>$preproc['multivals'],  //columns that have multivalue separator
        "csv_enclosure"=>$preproc['csv_enclosure'],
        "csv_mvsep"=>$preproc['csv_mvsep'],
        "uniqcnt"=>$uniqcnt,   //count of uniq values per column
        "indexes"=>$preproc['keyfields'] );//names of columns in import table that contains record_ID

    //new parameters to replace mapping and indexes_keyfields
    $session['primary_rectype'] =  0; //main rectype

    $session = ImportSession::save($session);
    if(!is_array($session)){
        self::$system->addError(HEURIST_DB_ERROR, 'Cannot save import session', $session);
        return false;
    }

    if(!empty($warnings)){
        $session['load_warnings'] = $warnings;
    }
    return $session;
}

//
// parse csv from content parameter (for terms import)
//
public static function simpleCsvParser($params){

    $content = $params['content'];
    //parse
    $csv_delimiter = @$params['csv_delimiter'];
    $csv_enclosure = @$params['csv_enclosure'];
    $csv_linebreak = @$params['csv_linebreak'];

    if(!$csv_delimiter) {$csv_delimiter = ',';}
    elseif($csv_delimiter=='tab') {$csv_delimiter="\t";}
    elseif($csv_delimiter=='space') {$csv_delimiter=" ";}

    if(!$csv_linebreak) {$csv_linebreak = "auto";}

    $csv_enclosure = ($csv_enclosure==1)?"'":'"';



    if(intval($csv_linebreak)>0){  //no breaks - group by
            $group_by = $csv_linebreak;
            $response = str_getcsv($content, $csv_delimiter, $csv_enclosure);

            $temp = array();
            $i = 0;
            while($i<count($response)) {
                $temp[] = array_slice($response, $i, $csv_linebreak);
                $i = $i + $csv_linebreak;
            }
            return $temp;
    }

    $response = array();

        if($csv_linebreak=="auto"){
            //ini_set('auto_detect_line_endings', true);
            $lb = "\n";
        }elseif($csv_linebreak="win"){
            $lb = "\r\n";
        }elseif($csv_linebreak="nix"){
            $lb = "\n";
        }elseif($csv_linebreak="mac"){
            $lb = "\r";
        }

        //remove spaces
        $content = trim(preg_replace('/([\s])\1+/', ' ', $content));

        $lines = str_getcsv($content, $lb, '¢');

        foreach($lines as &$Row) {
             $row = str_getcsv($Row, $csv_delimiter , $csv_enclosure);//parse the items in rows
             array_push($response, $row);
        }

    return $response;

}

//
// Converts data prepared by parseAndValidate to record format recordSearchByID/recordSearchDetails
// $parsed - parseAndValidate output
// $mapping - $dtyID=> column name in $parsed['fields']
//
public static function convertParsedToRecords($parsed, $mapping, $rec_RecTypeID=null){

    $fields = $parsed['fields'];
    $values = $parsed['values'];
    if($rec_RecTypeID==null){
      $rec_RecTypeID = 12; //RT_PLACE
    }
    $records = array();

    foreach($values as $idx=>$entry){

        $record = array('rec_ID'=>'C'.$idx,'rec_RecTypeID'=>$rec_RecTypeID, 'rec_Title'=>'');

        $detail = array();
        $lat  = null;
        $long = null;
        foreach($mapping as $dty_ID=>$column){

            $dty_ID = array_search($column, $mapping);
            //$dty_ID = @$mapping[$column];
            if($dty_ID>0 || $dty_ID=='longitude' || $dty_ID=='latitude'){
                $col_index = array_search($column, $fields);
                if($col_index>=0){
                    $detailValue = $entry[$col_index];
                    if($dty_ID==DT_GEO_OBJECT){
                            $detailValue = array(
                                "geo" => array(
                                    "type" => '',
                                    "wkt" => $detailValue
                                )
                            );
                    }elseif($dty_ID==DT_NAME){
                        $record['rec_Title'] = $detailValue;
                    }elseif($dty_ID==DT_EXTENDED_DESCRIPTION){
                        $record['Description'] = $detailValue;
                    }elseif($dty_ID=='longitude'){
                        $long = $detailValue;
                    }elseif($dty_ID=='latitude'){
                        $lat = $detailValue;
                    }

                    if($dty_ID>0){
                        $detail[$dty_ID][0] = $detailValue;
                    }
                }
            }
        }

        if(is_numeric($lat) && is_numeric($long)){
            $detail[DT_GEO_OBJECT][0] = array(
                                "geo" => array(
                                    "type" => '',
                                    "wkt" => $value = "POINT(".$long." ".$lat.")"
                                )
                            );
        }

        $record['details'] = $detail;
        $records[$idx] = $record;
    }

    return $records;
}


} //end class
?>
