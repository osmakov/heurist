<?php
/**
* Interface/Controller for CSV,KML parse and import
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

// @todo  move all session routines to csvSession.php ?
// all parse routines to csvParser.php
    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

    /*
    =================== parameters for csv/kml import

    content
        function parse_content - parse CSV from content parameter and returns parsed array (used in import terms)

    records
        get records from import table


    action

    set_primary_rectype
        set main rectype for given session and returns list of dependencies (resource/record pointer field->rectype)


    1) step0
        ImportCsvParser::saveToTempFile   save CSV form "data" parameter into temp file in scratch folder, returns filename
                     (used to post pasted csv to server side)

    2) step1
        parse_step1  check encoding, save file in new encoding invoke parse_step2 with limit 1000 to get parse preview

    3) step2
        parse_step2 -  if limit>1000 returns first 1000 lines to preview parse (used after set of parse parameters)
                       otherwise (used after set of field roles)
                            remove spaces, convert dates, validate identifies, find memo and multivalues
                            if id and date fields are valid invokes parse_db_save
                            otherwise returns error array and first 1000

        parse_db_save - save content of file into import table, create session object and saves it to sysImportFiles table, returns session

        saveSession - saves session object into  sysImportFiles table (todo move to entity class SysImportFiles)
        getImportSession - get session from sysImportFiles  (todo move to entity class SysImportFiles)

    -------------------

        getMultiValues  - split multivalue field

    -------------------

    4) step3
        assignRecordIds -  Assign record ids to field in import table (negative if not found)
                findRecordIds - find exisiting /matching records in heurist db by provided mapping

    5) step4
        ImportAction::validateImport - verify mapping parameter for valid detail values (numeric,date,enum,pointers)

            getWrongRecords
            validateEnumerations
            validateResourcePointers
            validateNumericField
            validateDateField

    5) step5
        ImportAction::performImport - do import - add/update records in heurist database

    ============== parameters for xml/json import

    filename - name of temp file with import data

    action
        import_prepare      - reads import file and returns list of records to be imported
        import_definitions  -
        import_records

    */

use hserv\utilities\USanitize;
use hserv\entity\DbSysImportFiles;

require_once dirname(__FILE__).'/../../autoload.php';

require_once dirname(__FILE__).'/../structure/search/dbsData.php';
require_once dirname(__FILE__).'/../structure/search/dbsDataTree.php';
require_once dirname(__FILE__).'/../structure/dbsUsersGroups.php';

require_once dirname(__FILE__).'/../records/import/importParser.php';//parse CSV, KML and save into import table
require_once dirname(__FILE__).'/../records/import/importSession.php';//work work with import session
require_once dirname(__FILE__).'/../records/import/importAction.php';//work with import table: matching, assign id, performs validation and import
require_once dirname(__FILE__).'/../records/import/importHeurist.php';//work with Heurist exchange format

set_time_limit(0);

$response = null;
$need_compress = false;

$system = new hserv\System();

if(!$system->init(@$_REQUEST['db'])){
    //get error and response
    $response = $system->getError();
}else{

    if(!userCheckPermissions($system, 'add', 1)){ // Check that the user is allowed to edit records

        $response = $system->getError();

   }else{

        //for kml step2,step3,set_primary_rectype,step3
        $action = @$_REQUEST["action"];
        $res = false;

        if($action=='step0'){
            $res = ImportParser::saveToTempFile( @$_REQUEST['data'] );//it saves csv data in temp file  -returns array(filename)

        }elseif($action=='step1'){
            //file is uploaded with help fileupload widget and controller/fileUpload.php
            $upload_file_name = @$_REQUEST["upload_file_name"];
            if($upload_file_name!=null){
                $upload_file_name = USanitize::sanitizeFileName(basename($upload_file_name), false);//snyk SSRF
                //encode and invoke parse_prepare with limit
                $res = ImportParser::encodeAndGetPreview( $upload_file_name, $_REQUEST);
            }

        }elseif($action=='step2'){

            //vaidate values(dates,int) saves into import table
            $res = ImportParser::parseAndValidate( intval(@$_REQUEST["encoded_filename_id"]),
                                                   filter_var(@$_REQUEST["original_filename"],FILTER_SANITIZE_STRING),
                                                   0, $_REQUEST);

        }elseif($action=='step3'){ // matching - assign record ids

            $res = ImportAction::assignRecordIds($_REQUEST);

        }elseif($action=='step4'){ // validate import - check field values

            $res = ImportAction::validateImport($_REQUEST);

        }elseif($action=='step5'){ // perform import

            $res = ImportAction::performImport($_REQUEST, 'json');

        }elseif(@$_REQUEST['content']){ //for import terms

            $res = ImportParser::simpleCsvParser($_REQUEST);

        }elseif($action=='set_primary_rectype'){

            $res = ImportSession::setPrimaryRectype( intval(@$_REQUEST['imp_ID']), intval(@$_REQUEST['rty_ID']), @$_REQUEST['sequence']);

        }elseif($action=='get_matching_samples'){

            $res = ImportSession::getMatchingSamples( intval(@$_REQUEST['imp_ID']), intval(@$_REQUEST['rty_ID']) );

        }elseif($action=='records'){  //load records from temp import table

            $table_name = filter_var(@$_REQUEST['table'],FILTER_SANITIZE_STRING);

            if($table_name==null || $table_name==''){
                $system->addError(HEURIST_INVALID_REQUEST, error_WrongParam('"table"'));
                $res = false;

            }elseif(@$_REQUEST['imp_ID']){
                $res = ImportSession::getRecordsFromImportTable1($table_name, intval($_REQUEST['imp_ID']));
            }else{
                $res = ImportSession::getRecordsFromImportTable2($table_name,
                            @$_REQUEST['id_field'],
                            @$_REQUEST['mode'], //all, insert, update
                            @$_REQUEST['mapping'],
                            @$_REQUEST['offset'],
                            @$_REQUEST['limit'],
                            @$_REQUEST['output']
                            );
            }


            if($res && @$_REQUEST['output']=='csv'){

                // Open a memory "file" for read/write...
                $fp = fopen('php://temp', 'r+');
                $sz = 0;
                $cnt = 0;

                //put header
                $header_flds = @$_REQUEST['header_flds'];
                if($header_flds!=null && !is_array($header_flds)){
                    $header_flds = json_decode($header_flds, true);
                    //$header_flds = explode(',',$header_flds);
                }
                if(!isEmptyArray($header_flds)){
                    $sz = $sz + fputcsv($fp, $header_flds, ',', '"');
                }

                foreach ($res as $idx=>$row) {

                    $sz = $sz + fputcsv($fp, $row, ',', '"');
                    $cnt++;
                }
                rewind($fp);
                // read the entire line into a variable...
                $data = fread($fp, $sz+1);
                fclose($fp);

                $res = $data;

            }

        }elseif($action=='import_preview'){
            //reads import file and returns list of record types to be imported
            $filename = filter_var(basename(@$_REQUEST['filename']),FILTER_SANITIZE_STRING);

            $res = ImportHeurist::getDefintions($filename);

        }elseif($action=='import_definitions'){ //import defs before import records

            //update record types from remote database
            $filename = filter_var(basename(@$_REQUEST['filename']),FILTER_SANITIZE_STRING);

            $res = ImportHeurist::importDefintions($filename, @$_REQUEST['session']);
            //$need_compress = true;

        }elseif($action=='import_records'){

            //returns count of imported records
            if(@$_REQUEST['filename']!=null){
                //filename - source hml or json file (in scratch), session - unique id for progress
                $filename = filter_var(basename(@$_REQUEST['filename']),FILTER_SANITIZE_STRING);

                $res = ImportHeurist::importRecords($filename, @$_REQUEST);

            }else{
                //direct import from another database (the same server)
                $res = ImportHeurist::importRecordsFromDatabase(@$_REQUEST);
            }

        }elseif($action=='import_terms'){

            $res = ImportAction::importTerms(@$_REQUEST);

        }elseif($action=='insert_column'){

            $res = ImportAction::insertNewColumns(@$_REQUEST);

        }else{
            $system->addError(HEURIST_INVALID_REQUEST, "Action parameter is missing or incorrect");
            $res = false;
        }




        if(is_bool($res) && $res==false){
                $response = $system->getError();
        }else{
                $response = array("status"=>HEURIST_OK, "data"=> $res);
        }
   }
}



// ----------------------- OUTPUT ----------------------------------
//
//
if(@$_REQUEST['output']=='csv'){


    if($_REQUEST['output']=='csv'){
        header('Content-Type: text/plain;charset=UTF-8');
        header('Pragma: public');
        header('Content-Disposition: attachment; filename="import.csv"');//import_name
    }

    if($response['status']==HEURIST_OK){
        header(CONTENT_LENGTH . strlen($response['data']));
        print $response['data'];

    }else{
        print htmlspecialchars($response['message']).'. ';
        print 'status: '.htmlspecialchars($response['status']);
    }


}

elseif($need_compress){ //importDefintions returns complete set of new defintions - need to compress

    ob_start();
    echo json_encode($response);
    $output = gzencode(ob_get_contents(),6);
    ob_end_clean();
    header('Content-Encoding: gzip');
    header(CTYPE_JSON);
    echo $output;
    unset($output);
}else{

    header(CTYPE_JSON);
    print json_encode($response);
}
?>