<?php

    /**
    * Entity SCRUD controller - web interface. It uses functions from entityScrudSrv.php
    * search, create, read, update and delete
    *
    * Application interface. See HRecordMgr in hapi.js
    * Add/replace/delete details in batch
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
    use hserv\utilities\USystem;

    require_once dirname(__FILE__).'/../../autoload.php';

    require_once 'entityScrudSrv.php';

    if(!isset($req_params)){ //if set array has been already modified in api.php
        $req_params = USanitize::sanitizeInputArray();
    }

    $dbname = @$req_params['db'];

    $system_init_failed = false;

    $system = new hserv\System();

    $dbdef_cache = null;

    $db_check_error = mysql__check_dbname( $dbname );//validate db name

    if($db_check_error==null
        && isset($defaultRootFileUploadURL)
        && strpos($defaultRootFileUploadURL,'sydney.edu.au')===false )
    {
            $path = $system->getFileStoreRootFolder().basename($dbname).'/entity/';
            if(is_dir($path) && file_exists($path)){
                $dbdef_cache = $path.'dbdef_cache.json';
            }
    }

    //
    // for structure request (to refresh db defs on client side)
    // entity parameters may be a name of structure table or
    // all - get all definitions (from cache dbdef_cache.json if it exists)
    // force_all - get all definitions and update dbdef_cache.json
    // relevance - compare filetime with timestamp,
    //                      if filetime is later - returns definitions
    //                      otherwise file time
    if( @$req_params['a']=='structure'
        && (@$req_params['entity']=='all' || @$req_params['entity']=='relevance')
        && $dbdef_cache!=null && file_exists($dbdef_cache)
        ){
            if($db_check_error==null){

                $dbdef_cache_is_uptodate = true;

                if($req_params['entity']=='relevance'){
                    $req_params['entity'] = 'all';
                    $file_time = filemtime($dbdef_cache);

                    if($file_time - intval($req_params['timestamp']) < 10){
                        //compare file time with time of db defs on client side
                        //defintions are up to date on client side
                        header(CTYPE_JSON);
                        print json_encode( array('uptodate'=>$file_time));
                        exit;
                        //otherwise download dbdef cache
                    }
                }else{
                    //check file time and last update time of definitions
                    if($system->init($dbname)){
                        $dbdef_mod = getDefinitionsModTime($system->getMysqli());//see utils_db

                        if($dbdef_mod!=null){
                            $db_time  = $dbdef_mod->getTimestamp();
                            $file_time = filemtime($dbdef_cache);

                            if($db_time>$file_time){ //db def cache is outdated
                                  $req_params['entity'] = 'force_all';
                                  $dbdef_cache_is_uptodate = false;
                            }
                        }

                    }else{
                        $system_init_failed = true;
                    }
                }

                if($dbdef_cache_is_uptodate){
                    //download db def cache or direct access
                    if(isset($allowWebAccessEntityFiles) && $allowWebAccessEntityFiles)
                    {
                        $host_params = USystem::getHostParams();
                        //
                        if(strpos($defaultRootFileUploadURL, $host_params['server_url'])===0){
                            $url = $defaultRootFileUploadURL;
                        }else{
                            //replace server name to avoid CORS issues
                            $parts = explode('/',$defaultRootFileUploadURL);
                            $url = $host_params['server_url'] . '/' . implode('/',array_slice($parts,3));
                        }

                        //rawurlencode - required for security reports only
                        $url = $url.rawurlencode($dbname).'/entity/dbdef_cache.json';
                        redirectURL($url);

                    }else{
                        downloadFile(MIMETYPE_JSON, $dbdef_cache);
                    }
                    exit;
                }
            }else{
                exit;//wrong db name
            }

    }

    $response = array();
    $res = false;

    $entity = null;

    $need_config = false;

    if( (!$system_init_failed)  //system can be inited beforehand for getDefinitionsModTime
        && ($system->isInited() || $system->init($dbname)))
    {

        //USanitize::sanitizeRequest($req_params); it brokes json strings
        USanitize::stripScriptTagInRequest($req_params);//remove <script>

        $res = array();
        $entities = array();

        if(@$req_params['a']=='structure'){
            // see HAPI4.refreshEntityData
            if(@$req_params['entity']=='force_all'){  //recreate cache
                $req_params['entity'] = 'all';
                //remove cache
                if($dbdef_cache!=null){
                    $system->cleanDefCache();
                }
            }elseif(@$req_params['entity']=='relevance'){
                $req_params['entity'] = 'all';
            }
            $res = entityRefreshDefs($system, @$req_params['entity'], true);

            //update dbdef cache
            if(@$req_params['entity']=='all' && $res!==false && $dbdef_cache!=null){
                $res['timestamp'] = time();//update time on client side
                //update db defintion cache file
                file_put_contents($dbdef_cache, json_encode($res));
            }

        }else {
            $res = entityExecute($system, $req_params);
        }

        $system->dbclose();
    }


    if(@$req_params['restapi']==1){

        if( is_bool($res) && !$res ){

            $system->errorExitApi();

        }else{
            header(HEADER_CORS_POLICY);
            header(CTYPE_JSON);

            $req = array();

            if(@$req['a'] == 'search' && empty($res)){
                $code = 404;
            }elseif (@$req['a'] == 'save'){
                $code = 201;
            }else{
                $code = 200;
            }
            http_response_code($code);

            print json_encode($res);
        }
    }else{
        header(CTYPE_JSON);

        if( is_bool($res) && !$res ){
            $response = $system->getError();
        }else{
            $response = array("status"=>HEURIST_OK, "data"=> $res);
        }

        if (strnatcmp(phpversion(), '7.3') >= 0) {

            try{

               $res = json_encode($response, JSON_THROW_ON_ERROR);

               if(false && strlen($res)>20000){
                   ob_start();
                   echo json_encode($res);
                   $output = gzencode(ob_get_contents(),6);
                   ob_end_clean();
                   header('Content-Encoding: gzip');
                   echo $output;
                   unset($output);
               }else{
                   echo $res;
                   unset($res);
               }


            } catch (JsonException $e) {

                $res = json_encode($response, JSON_INVALID_UTF8_IGNORE );

                print $res;
            }

        }else{

            $res = json_encode($response);//JSON_INVALID_UTF8_IGNORE
            if(!$res){

                //
                //find wrong value
                $wrong_string = null;
                try{
                    array_walk_recursive($response, 'find_invalid_string');

                }catch(Exception $exception) {
                       $wrong_string = $exception->getMessage();
                }

                $msg = 'Your data definitions (names, descriptions) contain invalid characters (non UTF-8). '
                .'Or system cannot convert them properly.';

                if($wrong_string){
                    $msg = $msg . ' Invalid character in string: '.$wrong_string;
                }

                $system->addError(HEURIST_SYSTEM_CONFIG, $msg);
                print json_encode( $system->getError() );
            }else{
                print $res;
            }

        }


    }

    function find_invalid_string($val){
        if(is_string($val)){
            $stripped_val = iconv('UTF-8', 'UTF-8//IGNORE', $val);/* important */
            if($stripped_val!=$val){
                throw new Exception(mb_convert_encoding($val,'UTF-8'));
            }
        }
    }
/*
Description Of Usual Server Responses:
200 OK - the request was successful (some API calls may return 201 instead).
201 Created - the request was successful and a resource was created.
204 No Content - the request was successful but there is no representation to return (i.e. the response is empty).
400 Bad Request - the request could not be understood or was missing required parameters.
401 Unauthorized - authentication failed or user doesn't have permissions for requested operation.
403 Forbidden - access denied.
404 Not Found - resource was not found.
405 Method Not Allowed - requested method is not supported for resource.
*/
?>