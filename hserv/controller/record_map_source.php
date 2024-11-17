<?php
    /**
    * Converts kml,csv to geojson or downloads file based on Datasource record id
    *
    * Reads file map source record (KML, CSV or DBF) and returns content
    * either as geojson (conversion), original file (acts as proxy) or zip archive
    * (with metadata). No functions
    * Usage: viewers/map/mapLayer2.js - to load kml,csv,dbf source as geojson.
    *
    * $_REQUEST parameters:
    * recID   datasource record ID
    * format  geojson - converts file to geojson,
    *         rawfile - return zipped original file with metadata
    *         n/a - works as proxy - it downloads original file with http header (mimetype, size)
    *
    * metadata - 1 include text file with link to flathml for format=rawfile
    *
    * When it generates geojson it simplifies path by removing extra points with given tolerance
    *
    * @uses mapSimplify.php
    * @uses importParser.php
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
    use hserv\structure\ConceptCode;
    use hserv\utilities\USanitize;
    use hserv\utilities\UArchive;

    require_once dirname(__FILE__).'/../../autoload.php';

    require_once dirname(__FILE__).'/../records/search/recordSearch.php';

    require_once dirname(__FILE__).'/../../vendor/autoload.php';//for geoPHP
    require_once dirname(__FILE__).'/../records/import/importParser.php';//parse CSV, KML and save into import table
    require_once dirname(__FILE__).'/../utilities/geo/mapSimplify.php';

    $response = array();

    $system = new hserv\System();

    $params = $_REQUEST;

    $parser_parms = array();

    $input_format = null;

    if(!(@$params['recID']>0)){
        $system->errorExitApi('recID parameter value is missing or invalid');//exit from script
    }

    if( ! $system->init(@$params['db']) ){
        //get error and response
        $system->errorExitApi();//exit from script
    }
    $system->defineConstants();

    $record = array("rec_ID"=>intval($params['recID']));
    //load record with details and 2 header fields
    $record = recordSearchByID($system, intval($params['recID']), true, "rec_ID, rec_RecTypeID");
    //array(DT_KML, DT_KML_FILE, DT_FILE_RESOURCE));

    if (@$record['details'] &&
       (@$record['details'][DT_KML] || @$record['details'][DT_KML_FILE] || @$record['details'][DT_FILE_RESOURCE]))
    {
            $input_format = ($record['rec_RecTypeID']==RT_KML_SOURCE)?'kml':'csv';
            $file_content = null;
            $tmp_destination = null;

            if(@$params['format']=='rawfile'){
                $tmp_destination = tempnam(HEURIST_SCRATCHSPACE_DIR, "data");
            }

            if(@$record['details'][DT_KML]){
                //snippet - format unknown
                $file_content = array_shift($record['details'][DT_KML]);
                if($tmp_destination){
                    file_put_contents($tmp_destination, $file_content);
                }
            }
            else
            {
                if(@$record['details'][DT_KML_FILE]){
                    $kml_file = array_shift($record['details'][DT_KML_FILE]);
                }else{
                    $kml_file = array_shift($record['details'][DT_FILE_RESOURCE]);
                }
                $kml_file = $kml_file['file'];
                $url = @$kml_file['ulf_ExternalFileReference'];
                $originalFileName = @$kml_file['ulf_OrigFileName'];

                if($url){
                    $file_content = loadRemoteURLContent($url, true);//load remote KML into temp file
                    if($file_content===false){
                      $system->errorExitApi('Cannot load remote file '.$url, HEURIST_ERROR);
                    }

                    $ext = strtolower(substr($url,-4,4));

                    if($tmp_destination){
                        file_put_contents($tmp_destination, $file_content);
                    }

                }else {
                    $filepath = resolveFilePath($kml_file['fullPath']);

                    $filepath = isPathInHeuristUploadFolder($filepath);//snyk SSRF

                    if ($filepath && file_exists($filepath)) {

                        $ext = strtolower(substr($filepath,-4,4));
                    }else{
                        // Cannot load kml file
                        exit; //@todo error return
                    }

                    if($tmp_destination==null){

                        if($ext=='.kmz'){
                            //check if scratch folder exists
                            $res = folderExistsVerbose(HEURIST_SCRATCH_DIR, true, 'scratch');
                            if($res!==true){
                                $system->errorExitApi('Cannot extract kmz data to "scratch" folder. '.$res, HEURIST_ERROR);
                            }

                            $files = UArchive::unzipFlat($filepath, HEURIST_SCRATCH_DIR);

                            foreach($files as $filename){
                                if(strpos(strtolower($filename),'.kml')==strlen($filename)-4){
                                    $filepath = $filename;
                                }else{
                                    unlink( $filename );
                                }
                            }
                        }

                        $file_content = file_get_contents($filepath);
                    }else{
                        if($ext=='.kmz'){
                            $input_format = 'kmz';
                        }
                        $tmp_destination = $filepath;
                    }

                }

                if($input_format=='kml' || $ext=='.kmz' || $ext=='.kml'){
                    $input_format = 'kml';
                }elseif($ext=='.tsv'){
                    $input_format = 'csv';
                    $parser_parms['csv_delimiter'] = 'tab';
                }
            }

            //output format
            if(@$params['format']=='geojson'){

                //detect type of data
                if($input_format==null){
                    $totest = strtolower($file_content);
                    $pos = strpos($totest,'<placemark>');
                    if($pos!==false && $pos < strpos($totest,'</placemark>')){
                        $input_format = 'kml';
                    }else{
                        $input_format = 'csv';
                    }
                }

                //X 2-930, Y 2-931, t1 2-932, t2 2-933, Name 2-934, Summary description 2-935
                $mapping = array();
                $fm_name = ConceptCode::getDetailTypeLocalID('2-934');
                $fm_desc = ConceptCode::getDetailTypeLocalID('2-935');
                $fm_X = ConceptCode::getDetailTypeLocalID('2-930');
                $fm_Y = ConceptCode::getDetailTypeLocalID('2-931');
                $fm_t1 = ConceptCode::getDetailTypeLocalID('2-932');
                $fm_t2 = ConceptCode::getDetailTypeLocalID('2-933');


                if($fm_name!=null && is_array(@$record['details'][$fm_name])){
                    $mapping[DT_NAME] = array_shift($record['details'][$fm_name]);
                }
                if($fm_desc!=null && is_array(@$record['details'][$fm_desc])){
                    $mapping[DT_EXTENDED_DESCRIPTION] = array_shift($record['details'][$fm_desc]);
                }

                if($fm_t1!=null && is_array(@$record['details'][$fm_t1])){
                    $mapping[DT_START_DATE] = array_shift($record['details'][$fm_t1]);
                }
                if($fm_t2!=null && is_array(@$record['details'][$fm_t2])){
                    $mapping[DT_END_DATE] = array_shift($record['details'][$fm_t2]);
                }


                if($input_format == 'kml'){
                    $parser_parms['kmldata'] = true;
                    $mapping[DT_GEO_OBJECT] = 'geometry';
                    if(!@$mapping[DT_START_DATE]) {$mapping[DT_START_DATE] = 'timespan_begin';}
                    if(!@$mapping[DT_END_DATE]) {$mapping[DT_END_DATE] = 'timespan_end';}
                    if(!@$mapping[DT_DATE]) {$mapping[DT_DATE] = 'timestamp';}

                }else{
                    $parser_parms['csvdata'] = true;

                    if($fm_X!=null && @$record['details'][$fm_X]){
                        $mapping['longitude'] = array_shift($record['details'][$fm_X]);
                    }
                    if($fm_Y!=null && @$record['details'][$fm_Y]){
                        $mapping['latitude'] = array_shift($record['details'][$fm_Y]);
                    }

                }

                if(!empty($mapping)){

                    if(!@$mapping[DT_NAME]){
                        $mapping[DT_NAME] = 'name';
                    }

                    //returns csv with header
                    $parsed = ImportParser::parseAndValidate($file_content, null, PHP_INT_MAX, $parser_parms);
                    //'fields'=>$header, 'values'=>$parsed_values

                    //returns records in PLACE? format recordSearchByID/recordSearchDetails
                    $records = ImportParser::convertParsedToRecords($parsed, $mapping);
                    $recdata = array('status'=>HEURIST_OK, 'data'=>array('reccount'=>count($records), 'records'=>$records));

                    //it outputs geojson and exits
                    $classname = 'hserv\records\export\ExportRecordsGEOJSON';
                    $outputHandler = new $classname($system);
                    $res = $outputHandler->output($recdata, array('format'=>'geojson', 'leaflet'=>true, 'depth'=>0, 'simplify'=>true) );

                }else{
                    //entire kml is considered as unified map entry
                    try{
                        $geom = geoPHP::load($file_content, 'kml');
                    }catch(Exception $e){
                        $system->errorExitApi('Cannot process kml: '.$e->getMessage(), HEURIST_ERROR);
                    }
                    if($geom!==false && !$geom->isEmpty()){


                            $geojson_adapter = new GeoJSON();
                            $json = $geojson_adapter->write($geom, true);

                            if(!isEmptyArray(@$json['coordinates'])){

                                if(@$params['simplify']){

                                    if($json['type']=='LineString'){

                                        simplifyCoordinates($json['coordinates']);//see mapSimplify.php

                                    } elseif($json['type']=='Polygon'){
                                        for($idx=0; $idx<count($json['coordinates']); $idx++){
                                            simplifyCoordinates($json['coordinates'][$idx]);
                                        }
                                    } elseif ( $json['type']=='MultiPolygon' || $json['type']=='MultiLineString')
                                    {
                                        for($idx=0; $idx<count($json['coordinates']); $idx++){ //shapes
                                            for($idx2=0; $idx2<count($json['coordinates'][$idx]); $idx2++){ //points
                                                simplifyCoordinates($json['coordinates'][$idx][$idx2]);
                                        }}
                                    }
                                }

                                $json = array(array(
                                    'type'=>'Feature',
                                    'geometry'=>$json,
                                    'properties'=>array()
                                ));
                            }

                            $json = json_encode($json);
                            header(CTYPE_JSON);
                            header(CONTENT_LENGTH . strlen($json));
                            exit($json);
                    }else{
                        $system->errorExitApi('No coordinates retrieved from kml file', HEURIST_ERROR);
                    }
                }


            }
            else
            {
                //downloadFile()
                $originalFileName = null;
                if(is_array($record['details'][DT_NAME])){
                    $originalFileName = USanitize::sanitizeFileName(array_values($record['details'][DT_NAME])[0]);
                }
                if(!$originalFileName) {$originalFileName = 'Dataset_'.$record['rec_ID'];}


                if(@$params['format']=='rawfile'){
                    //return zipped original file with metadata
                    $file_zip = $originalFileName.'.zip';
                    $file_zip_full = tempnam(HEURIST_SCRATCHSPACE_DIR, "arc");
                    $zip = new ZipArchive();
                    if (!$zip->open($file_zip_full, ZIPARCHIVE::CREATE)) {
                        $system->errorExitApi("Cannot create zip $file_zip_full");
                    }else{
                        $zip->addFile($tmp_destination, $originalFileName.'.'.$input_format);
                    }

                    if(@$params['metadata']){//save hml into scratch folder

                        $zip->addFromString($originalFileName.'.txt',
                                       recordLinksFileContent($system, $record));
                    }
                    $zip->close();
                    //donwload
                    $contentDispositionField = 'Content-Disposition: attachment; '
                        . sprintf('filename="%s";', rawurlencode($file_zip))
                        . sprintf("filename*=utf-8''%s", rawurlencode($file_zip));

                    header('Content-Type: application/zip');
                    header($contentDispositionField);
                    header(CONTENT_LENGTH . filesize($file_zip_full));
                    readfile($file_zip_full);

                }else{

                    if($input_format=='kml'){
                        header('Content-Type: application/vnd.google-earth.kml+xml');
                    }elseif($input_format=='csv'){
                        header('Content-Type: text/csv');
                    }elseif($input_format=='dbf'){
                        header('Content-Type: application/x-dbase');
                    }
                    $originalFileName = $originalFileName.$input_format;
                    $contentDispositionField = 'Content-Disposition: attachment; '
                            . sprintf('filename="%s";', rawurlencode($originalFileName))
                            . sprintf("filename*=utf-8''%s", rawurlencode($originalFileName));

                    header($contentDispositionField);
                    header(CONTENT_LENGTH . strlen($file_content));
                    echo $file_content;
                }

            }
    }else{
        $system->errorExitApi('Database '
                .htmlspecialchars($params['db']).'. Record '
                .intval($params['recID']).' does not have data for KML/CSV snipppet or file');
    }

    $system->dbclose();
?>