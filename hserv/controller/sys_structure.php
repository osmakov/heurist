<?php

    /**
    * Application interface. See HSystemMgr in hapi.js
    *   database definitions - record types, record structure, field types, terms
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

    require_once dirname(__FILE__).'/../structure/search/dbsData.php';
    require_once dirname(__FILE__).'/../structure/search/dbsDataTree.php';
    require_once dirname(__FILE__).'/../structure/import/dbsImport.php';

    $response = array();
    $is_remote = false;
    $remoteURL = null;

    $req_params = USanitize::sanitizeInputArray();

    //get list of registered database and master index db on the same server
    if(@$req_params['remote']){

       $remoteURL = $req_params['remote'];
       preg_match("/db=([^&]*).*$/", $remoteURL, $match);

       if(strpos($remoteURL, HEURIST_SERVER_URL)===0){ //same domain

            unset($req_params['remote']);
            $req_params['db'] = $match[1];
       }else{

                if(@$match[1]==null || $match[1]==''){
                     $data = __getErrMsg($remoteURL, HEURIST_ERROR, 'Cannot detect database parameter in registration URL');
                     $data = json_encode($data);
                }else{

                    //load structure from remote server
                    $splittedURL = explode('?', $remoteURL);

                    $remoteURL_original = $remoteURL;
                    //change hsapi to hserv when master index will be v6.5
                    $remoteURL = $splittedURL[0]
                    .'hserv/controller/sys_structure.php?db='.$match[1];

                    if (@$req_params['rectypes']) {$remoteURL = $remoteURL.'&rectypes='.$req_params['rectypes'];}
                    if (@$req_params['detailtypes']) {$remoteURL = $remoteURL.'&detailtypes='.$req_params['detailtypes'];}
                    if (@$req_params['terms']) {$remoteURL = $remoteURL.'&terms='.$req_params['terms'];}
                    if (@$req_params['translations']) {$remoteURL = $remoteURL.'&translations='.$req_params['translations'];}
                    if (@$req_params['mode']) {$remoteURL = $remoteURL.'&mode='.$req_params['mode'];}

                    $data = loadRemoteURLContent($remoteURL);//load defitions from remote database

                    if($data==false){

                        //Server not found
                        //No response from server
                        $data = __getErrMsg($remoteURL_original, $glb_curl_code, $remoteURL.' '.$glb_curl_error);
                        $data = json_encode($data);
                    }else{
                        header('Content-Encoding: gzip');
                    }
                }

                header(CTYPE_JSON);
                echo $data;
                exit;
       }
    }


    $mode = 0;
    $system = new hserv\System();
    if( ! $system->init(@$req_params['db']) ){

        //get error and response
        $response = $system->getError();

        if($remoteURL!=null){

            //cannot connect to registered database on the same server
            $response = __getErrMsg($remoteURL, $response['status'], $response['message']);

        }


    }else{

        if(@$req_params["import"]){ //this is import
            if(!$system->is_admin()){
                $system->error_exit('To perform this action you must be logged in as '
                        .'Administrator of group \'Database Managers\'',
                        HEURIST_REQUEST_DENIED);
            }

            //combination of db and record type id eg. 1126-13
            $code = @$req_params['code'];//this is not concept code - these are source database and rectype id in it
            //concept code is unique for record type unfortunately it does not specify exactly what database is preferable as a source of donwloading

            $isOK = false;
ini_set('max_execution_time', 0);
            $importDef = new DbsImport( $system );

            $options = [
                'defType' => @$req_params["import"],
                'databaseID' => @$req_params["databaseID"],
                'definitionID' => @$req_params["definitionID"],
                'is_rename_target' => @$req_params["is_rename_target"] == 1,
                'conservative' => @$req_params["conservative"] == 1,
                'conceptCode'=> @$req_params['conceptCode']
            ];

            if($importDef->doPrepare( $options )){
                $isOK = $importDef->doImport();
            }

            if(!$isOK){
                $system->error_exit(null);//produce json output and exit script
            }
            $response = $importDef->getReport(true);//with updated definitions and sysinfo

            $response['status'] = HEURIST_OK;

            //
            // send error report about terms that were failed
            //
            if(@$response['report']['broken_terms'] && !empty($response['report']['broken_terms'])){

                $sText = 'Target database '.HEURIST_DBNAME;
                $sText .= ("\n".'Source database '.intval(@$req_params["databaseID"]));
                $sText .= ("\n".count($response['report']['broken_terms']).' terms were not imported.');
                foreach($response['report']['broken_terms'] as $idx => $term){
                    $sText .= ("\n".print_r($term, true));
                    $sText .= ("\n reason: ".$response['report']['broken_terms_reason'][$idx]);
                }
                //$sText .= ('</ul>');

                sendEmail(HEURIST_MAIL_TO_BUG, 'Import terms report', $sText);

                $response['report']['broken_terms_reason'] = null;
            }
            if(@$response['report'] && $response['report']['rectypes']){

                $sText = 'Target database '.HEURIST_DBNAME;
                $sText .= ("<br>".'Source database '.intval(@$req_params["databaseID"]));
                $sText .= (TABLE_S.'<tr><td colspan="2">source</td><td colspan="2">target</td></tr>'
                        .$response['report']['rectypes'].TABLE_E);

                sendEmail(HEURIST_MAIL_TO_ADMIN, 'Download templates', $sText, true);
            }


        }else{

            //$currentUser = $system->getCurrentUser();
            $data = array();

            if (@$req_params['translations']){
                $data['translations'] = dbs_GetTranslations($system, $req_params['translations']);
            }

            if (@$req_params['terms']) {
                $data["terms"] = dbs_GetTerms($system);
            }

            if (@$req_params['detailtypes']) {
                $ids = $req_params['detailtypes']=='all'?null
                            :filter_var($req_params['detailtypes'], FILTER_SANITIZE_STRING);
                $data["detailtypes"] = dbs_GetDetailTypes($system, $ids, intval(@$req_params['mode']) );
            }


            if (@$req_params['rectypes']) {
                $ids = $req_params['rectypes']=='all'?null
                            :filter_var($req_params['rectypes'], FILTER_SANITIZE_STRING);
                $mode = intval(@$req_params['mode']);

                if($mode>2){
                    $data["rectypes"] = dbs_GetRectypeStructureTree($system, $ids, $mode, @$req_params['fieldtypes'], @$req_params['parentcode']);
                }else{
                    $data["rectypes"] = dbs_GetRectypeStructures($system, $ids, $mode );
                }

            }else{
                $mode = 0;
            }

            if($mode==5){
                if(count($data["rectypes"])==1){
                    $response = $data["rectypes"][0]['children'];
                }else{
                    $response = $data["rectypes"];
                }
    /* verify this piece after merging 25-Jul-18
                if($mode==4 && @$req_params['lazyload']){
                    if(count($data["rectypes"])==1){
                        $response = $data["rectypes"][0]['children'];
                    }else{
                        $response = $data["rectypes"];
                    }
    */
            }else{
                $data["db_version"] = getDbVersion($system->get_mysqli());
                $response = array("status"=>HEURIST_OK, "data"=> $data );
            }

        }

        $system->dbclose();
    }


    /*
    if ( extension_loaded('zlib') && (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) )
    {
            ob_start('ob_gzhandler');
    }*/
/*
    ini_set("zlib.output_compression", '4096');
    ini_set("zlib.output_compression_level", '6');
    header(CTYPE_JS);
    print json_encode($response);
*/

ob_start();
echo json_encode($response);
$output = gzencode(ob_get_contents(),6);
ob_end_clean();

$system->setResponseHeader();
header('Content-Encoding: gzip');
echo $output;
unset($output);

function __getErrMsg($remoteURL, $code, $err_msg){

            if($code=='curl'){
                $reason = 'This may be due to a missing server or timeout on connection';
            }elseif($code==HEURIST_SYSTEM_FATAL){
                $reason = 'This may be due to an installation problem on the server';
            }else{
                $reason = 'This may be due to an error in the registration information recorded in the Heurist master index';
            }

            $message = '<h3>Unable to obtain database structure information</h3>'
            .'<p>We are unable to contact the selected source database</p>'
            .'<p>URL: <a href="'.$remoteURL.'">'.$remoteURL.'</a></p>'
            .'<p>Error returned: '.$err_msg.'</p><p>'
            .$reason
            .'</p>'
            .'<p>Please ask the owner of the database to correct registration information in the Heurist master index '
            .'(login to their database and use Design > Register to correct the information), or ask the system administrator '
            .'of the server in question to correct the installation. If the server/database no-longer exists please '
            .CONTACT_HEURIST_TEAM.' with the URL to the target database</p>';

            return array("status"=>HEURIST_ERROR, "message"=>$message, "sysmsg"=>null);

}
?>