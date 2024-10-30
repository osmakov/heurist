<?php
    /**
    * Application interface. See HRecordMgr in hapi.js
    * Record search and output in required format
    * used in recordExportCSV.js
    *
    *
    * parameters
    * db - heurist database
    * format = geojson|json|csv|kml|xml|hml|gephi|iiif
    * linkmode = direct, direct_links, none, all
    * prefs:{ format specific parameters }, }
    *
    * prefs for csv
                csv_delimiter :','
                csv_enclosure :'""
                csv_mvsep     :'|',
                csv_linebreak :'nix',
                csv_header    :true
                csv_headeronly:false
                fields        : {rtid:[dtid1, dtid3, dtid2]}
                include_term_ids
                include_term_codes
                include_file_url
                include_record_url_html
                include_record_url_xml
                include_term_hierarchy
                include_resource_titles
                include_temporals
    *
    *
    *
    * prefs for json,xml
    *           zip  : 0|1  compress
    *           file : 0|1  output as file or ptintout
    *           defs : 0|1  include database definitions
    *           restapi: 0|1  not include db description and heurist header
    *
    * prefs for geojson, json
    *   extended 0 as is (in heurist internal format),
    *            1 - interpretable,
    *            2 - include concept code and labels
    *            3 - simple plain object for mediaViewer (only records with file fields are included)
    *   leaflet - true|false returns strict geojson and timeline data as two separate arrays, without details, only header fields rec_ID, RecTypeID and rec_Title
    *   simplify  true|false simplify  paths with more than 1000 vertices
    *
    * datatable -   datatable session id
    *               >1 and "q" is defined - save query request in session to result set returned,
    *               >1 and "q" not defined and "draw" is defined - takes query from session
    *                1 - use "q" parameter
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
    use hserv\utilities\USanitize;
    require_once dirname(__FILE__).'/../../autoload.php';

    require_once dirname(__FILE__).'/../records/search/recordSearch.php';
    require_once dirname(__FILE__).'/../records/search/recordFile.php';
    require_once dirname(__FILE__).'/../structure/dbsTerms.php';
    require_once dirname(__FILE__).'/../utilities/Temporal.php';
    require_once dirname(__FILE__).'/../../admin/verification/verifyValue.php';

    require_once dirname(__FILE__).'/../records/export/recordsExportCSV.php';

    $response = array();

    if(isset($req_params)){ //if set array has been already modified in api.php
        $params = $req_params;
    }else{
        $params = USanitize::sanitizeInputArray();
    }

    if(@$params['postdata']){
        //in export csv all parameters send as json array in postdata
        $params = json_decode($params['postdata'], true);
    }

    if(!isset($system) || $system==null){

        $system = new hserv\System();

        if( ! $system->init(@$params['db']) ){
            //get error and response
            $system->error_exit_api();//exit from script
        }
    }

    set_time_limit(0);//no limit

    if(@$params['file_refs']){
        downloadFileReferences($system, $params['ids']);
    }

    if(!@$params['format']){
        $params['format'] = @$params['fmt'];
    }
    if(!@$params['format']){
        $params['format'] = 'json';
    }



    $search_params = array();
    $search_params['w'] = filter_var(@$params['w'], FILTER_SANITIZE_STRING);

    if(@$params['format']=='gephi' || @$params['format']=='geojson'){
        $search_params['limit'] = (@$params['limit']>0)?intval($params['limit']):null;
    }elseif(!(@$params['offset'] || @$params['limit'])){
        $search_params['needall'] = 1;  //search without limit of returned record count
    }

    //
    // search for single record by "recID", by set of "ids" or heurist query "q"
    //
    if(@$params['recID']>0){
        $search_params['q'] = array('ids'=>intval($params['recID']));
    }elseif(@$params['ids']){
        $search_params['q'] = array('ids'=>filter_var(implode(',', prepareIds($params['ids']) ), FILTER_SANITIZE_STRING));
    }elseif(@$params['iiif_image']){
        $params['format'] = 'iiif';
        $search_params['q'] = '*file @'.filter_var($params['iiif_image'],FILTER_SANITIZE_STRING);

    }else{
        $search_params['q'] = @$params['q'];
    }
    if($search_params['q']==null || $search_params['q']==''){
        $search_params['q'] = 'sortby:-m';//get all records
    }


    if(@$params['rules']!=null){
        $search_params['rules'] = $params['rules'];
        if(@$params['rulesonly']==true || @$params['rulesonly']==1){
            $search_params['rulesonly'] = 1;
        }
    }


    $is_csv = (@$params['format'] == 'csv');
    if(@$params['format']){
        //search only ids - all
        $search_params['detail'] = 'ids';
    }

    if(@$params['prefs']['csv_headeronly']===true){
        $response = array('status'=>HEURIST_OK,'data'=>array());
    }else{

//    datatable -   datatable session id  - returns json suitable for datatable ui component
//              >1 and "q" is defined - save query request in session to result set returned,
//              >1 and "q" not defined and "draw" is defined - takes ids/query from session
//              1 - use "q" parameter
        if(@$params['format']=='json' && @$params['datatable']>1){

            $dt_key = 'datatable'.$params['datatable'];

            if(@$params['q']==null){
                //restore query by id from session
                $search_params['q'] = $system->user_GetPreference($dt_key);

                if($search_params['q']==null){
                    //query was removed
                    header(CTYPE_JSON);
                    echo json_encode(array('error'=>'Datatable session expired. Please refresh search'));
                    exit;
                }

                $search_by_type = '';
                $search_by_field = '';
                //search by record type
                if(is_array($params['columns'])){
                    foreach($params['columns'] as $idx=>$column){
                        if($column['data']=='rec_RecTypeID' && @$column['search']['value']!=''){
                            $search_by_type = '{"t":"'.$column['search']['value'].'"},';
                            break;
                        }
                    }
                }
                if(@$params['search']['value']!=''){
                      $search_by_field = '{"f":"'.addslashes($params['search']['value']).'"},';
                }
                if($search_by_type!='' || $search_by_field!=''){
                    $search_params['q'] = '['.$search_by_type.$search_by_field.$search_params['q'].']';

                    $search_params['detail'] = 'count';
                    $response = recordSearch($system, $search_params);//datatable search - reccount only
                    $search_params['detail'] = 'ids';

                    $params['recordsFiltered'] = $response['data']['count'];
                }

                if(@$params['start']>0){
                    $search_params['offset'] = $params['start'];
                }
                if($params['length']>0){
                    $search_params['limit'] = $params['length'];
                    $search_params['needall'] = 0;
                }

            }elseif(@$params['q']!=null){  //first request - save base filter
                //remove all other "datatableXXX" keys from session
                $dbname = $system->dbname_full();
                if(@$_SESSION[$dbname]['ugr_Preferences']!=null){
                    $keys = array_keys($_SESSION[$dbname]['ugr_Preferences']);
                    if(is_array($keys)){
                        foreach ($keys as $key) {
                            if(strpos($key,'datatable')===0){
                                $_SESSION[$dbname]['ugr_Preferences'][$key] = null;
                                unset($_SESSION[$dbname]['ugr_Preferences'][$key]);
                            }
                        }
                    }
                }
                //save int session and exit
                user_setPreferences($system, array($dt_key=>$params['q']));
                //returns OK
                header(CTYPE_JSON);
                echo json_encode(array('status'=>HEURIST_OK));
                exit;
            }
        }

        $response = recordSearch($system, $search_params);//search ids
    }

    $system->defineConstant('DT_PARENT_ENTITY');
    $system->defineConstant('DT_START_DATE');
    $system->defineConstant('DT_END_DATE');
    $system->defineConstant('DT_SYMBOLOGY');

    $system->defineConstant('RT_TLCMAP_DATASET');
    $system->defineConstant('RT_MAP_LAYER');
    $system->defineConstant('RT_MAP_DOCUMENT');
    $system->defineConstant('DT_NAME');
    $system->defineConstant('DT_MAP_LAYER');
    $system->defineConstant('DT_MAP_BOOKMARK');
    $system->defineConstant('DT_ZOOM_KM_POINT');
    $system->defineConstant('DT_GEO_OBJECT');

    $res = true;

    if($is_csv){

        if(@$params['prefs']['csv_headeronly'])   //export record type template
        {
            RecordsExportCSV::output_header( $response, $params );
        }else{
            RecordsExportCSV::output( $response, $params );
        }

    }else{

            $allowed_formats = array('xml','geojson','gephi','iiif','json','rdf');
            $idx = array_search(strtolower($params['format']),$allowed_formats);

            if($idx===false || !($idx>0)){
                $idx = 0;
            }

            $classname = 'hserv\records\export\ExportRecords'.strtoupper($allowed_formats[$idx]);

            $outputHandler = new $classname($system);

            if(!$outputHandler){
                $system->addError(HEURIST_INVALID_REQUEST, 'Wrong parameter "format": '.htmlspecialchars(@$params['format']));
                return false;
            }else{
                $res = $outputHandler->output( $response, $params );
            }
    }

    if(!$res) {
        $system->error_exit_api();
    }

    $system->dbclose();


/**
 * Write file references out into CSV format
 *
 * @param hserv\System $system Initialised Heurist system
 * @param string|array $ids File ids to include (comma separated string or array)
 * @return none
 *  Output CSV file containing file references, or error message
 */
function downloadFileReferences($system, $ids){

    if(empty($ids)){

        header(CTYPE_HTML);
        echo 'No file ids have been provided';
        exit;
    }

    $where_clause = '';
    if(is_array($ids) || (is_string($ids) && $ids != 'all')){ // change comma separated list into array
        $ids = prepareIds($ids);
        $where_clause = !empty($ids) ? ' WHERE ulf_ID IN ('. implode(',', $ids) .')' : '';
    }

    // open output handler
    $fd = fopen('php://output', 'w');
    if(!$fd){

        header(CTYPE_HTML);
        echo 'Unable to open temporary output for writing CSV.<br>Please contact the Heurist team.';
        exit;
    }

    $sep = "\t";

    // retrieve file details
    $mysqli = $system->get_mysqli();
    $file_query = 'SELECT ulf_ID, ulf_FileName, ulf_ExternalFileReference, ulf_ObfuscatedFileID, ulf_FilePath, ulf_Description, ulf_MimeExt, ulf_FileSizeKB,
                    ugr_Name, ulf_Added, ulf_Modified, ulf_OrigFileName, ulf_Caption, ulf_Copyright, ulf_Copyowner
                   FROM recUploadedFiles
                   LEFT JOIN sysUGrps ON ulf_UploaderUGrpID = ugr_ID' . $where_clause;

    $res_files = $mysqli->query($file_query);

    $err_message = null;
    if (!$res_files) {
        $err_message = 'File record details could not be retrieved from database.<br><br>'
                        .(!empty($mysqli->error) ? $mysqli->error :'Unknown error');
    }else{
        $total_count_rows = mysql__found_rows($mysqli);
        if($total_count_rows==0){
            $err_message = 'Empty result set';
        }
    }

    if($err_message!=null){
        fclose($fd);

        header(CTYPE_HTML);
        echo $err_message;
        exit;
    }

    // return setup

    // write results
    fputcsv($fd, array("Uploaded_File_ID", "Referenced by", "New ref H-IDs", "Name", "Path", "Obfuscated URL", "Description", "Caption", "Copyright", "Copy Owner", "File Type", "File Size (in KB)", "Checksum", "Uploaded By", "Added On", "Last Modified", "Original file name"), $sep);

    /*
        [0] => File Name
        [1] => Link to external file
        [2] => Obfuscated File ID
        [3] => Local file path
        [4] => Description
        [5] => File Type
        [6] => File Size in KB
        [7] => Uploader Name
        [8] => Added On
        [9] => Last Modified
        [10] => Original file name
        [11] => Caption
        [12] => Copyright
        [13] => Copyowner
    */
    while ($details = $res_files->fetch_row()){

        $id = array_shift($details);

        $name = !empty($details[0]) ? $details[0] : $details[1];
        $path = !empty($details[3]) ? $details[3] . $name : 'External Source';
        $obf_url = empty($details[2]) ? 'missing' : HEURIST_BASE_URL . '?db=' . HEURIST_DBNAME . '&file=' . $details[2];
        $file_size = $details[6] == 0 ? 'remote' : $details[6];

        $fullpath = !empty($details[0]) ? resolveFilePath( $details[3].$details[0] ) : '';
        $checksum = empty($fullpath) ? 'remote' : md5_file($fullpath);

        $usage_query = 'SELECT dtl_RecID FROM recDetails WHERE dtl_UploadedFileID = ' . $id;
        $recs = mysql__select_list2($mysqli, $usage_query);
        if(!$recs || empty($recs)){
            $recs = array(0);
        }
        fputcsv($fd, array($id, implode('|', $recs), "", $name, $path, $obf_url, $details[4], $details[11], $details[12], $details[13], $details[5], $file_size, $checksum, $details[7], $details[8], $details[9], $details[10]), $sep);
    }
    $res_files->close();

    rewind($fd);
    $output = stream_get_contents($fd);
    fclose($fd);

    $filename = HEURIST_DBNAME . '_File_References.csv';
    dataOutput($output, $filename, 'text/csv');
}
?>