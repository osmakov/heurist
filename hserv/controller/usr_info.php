<?php
    /**
    * Application interface. See HSystemMgr in hapi.js
    *    user/groups information/credentials
    *    saved searches
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <osmakov@gmail.com>
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

    //use hserv\utilities as utils;
    use hserv\utilities\USanitize;

    require_once dirname(__FILE__).'/../../autoload.php';

    require_once dirname(__FILE__).'/../structure/dbsUsersGroups.php';
    require_once dirname(__FILE__).'/../structure/dbsSavedSearches.php';

    $response = array();
    $res = false;

    $req_params = USanitize::sanitizeInputArray();

    $action = @$req_params['a'];

    $system = new hserv\System();
    $dbname = @$req_params['db'];
    $error = mysql__check_dbname($dbname);

    if($error!=null){
        $system->addError(HEURIST_INVALID_REQUEST, $error);
        $res = false;

    }elseif($action=='verify_credentials'){ //just check only if logged in (db connection not required)

        $res = $system->verifyCredentials($dbname);

        if( $res>0 ){ //if logged id verify that session info (especially groups) is up to date
            //if exists file with userid it means need to reload system info
            $db_full_name = $system->dbnameFull();
            $reload_user_from_db = (@$_SESSION[$db_full_name]['need_refresh']==1);

            $const_toinit = true;
            if(!$reload_user_from_db){ //check for flag file to force update user (user rights can be changed by admin)
                $const_toinit = false;
                $system->initPathConstants($dbname);
                $fname = HEURIST_FILESTORE_DIR.$res;
                $reload_user_from_db = file_exists($fname);
            }

            if($reload_user_from_db){
                $system->init($dbname, false, $const_toinit);//session and constant are defined already
                $res = $system->getCurrentUserAndSysInfo();
            }else{
                $res = true;
            }

            if($res && !empty(@$req_params['permissions']) && !empty(@$_SESSION[$db_full_name]['ugr_Permissions'])){
                // Check if user has the required permission

                $required = $req_params['permissions'];
                $permissions = $_SESSION[$db_full_name]['ugr_Permissions'];
                $error_msg = "";

                if(strpos($required, 'add') !== false && $permissions['add']){
                    $error_msg = "create";
                }
                if(strpos($required, 'delete') !== false && $permissions['delete']){
                    $error_msg = (!empty($error_msg) ? " or " : "") . "delete";
                }

                $res = !empty($error_msg);
                if(!$res){
                    $error_msg = "Your account does not have permission to $error_msg records,<br>please contact the database owner for more details.";
                    $system->addError(HEURIST_ACTION_BLOCKED, $error_msg);
                }
            }
        }else{
            //logged off
            $res = array("currentUser"=>array('ugr_ID'=>0,'ugr_FullName'=>'Guest'));
        }

    }
    elseif($action=='usr_log'){

        if($system->setDbnameFull($dbname)){

            $system->initPathConstants($dbname);
            $system->userLogActivity(@$req_params['activity'], @$req_params['suplementary'], @$req_params['user']);
            $res = true;

            if(@$req_params['activity']=='impEmails'){
                $msg = 'Click on "Harvest EMail" in menu. DATABASE: '.$dbname;
                $rv = sendEmail(HEURIST_MAIL_TO_ADMIN, $msg, $msg);
            }
        }

    } elseif (false && $action == "save_prefs"){ //NOT USED save preferences into session (without db)

        if($system->verifyCredentials($dbname)>0){
            user_setPreferences($system, $req_params);
            $res = true;
        }

    } elseif($action == "logout"){ //save preferences into session

        if($system->setDbnameFull($dbname)){

            $system->initPathConstants($dbname);
            $system->userLogActivity('Logout');

            if($system->doLogout()){
                $res = true;
            }
        }

    }elseif($action == 'check_for_alpha'){ // check if an alpha version is available

        $is_alpha = (preg_match("/h\d+\-alpha|alpha\//", HEURIST_BASE_URL) === 1) ? true : false;
        $res = '';

        if(!$is_alpha){

            if(!defined('HEURIST_FILESTORE_ROOT')){
                if($system->setDbnameFull($dbname)){
                    $system->initPathConstants($dbname);
                }
            }

            if(defined('HEURIST_FILESTORE_ROOT')){
                $fname = HEURIST_FILESTORE_ROOT."lastAdviceSent.ini";
                $verison_numbers = array();
                array_push($verison_numbers, explode('.', HEURIST_VERSION)[0]);// Check using current major version

                if (file_exists($fname)){
                    list($date_last_check, $version_last_check, $release_last_check) = explode("|", file_get_contents($fname));
                    if($verison_numbers[0] < explode('.', $version_last_check)[0]){
                        array_unshift($verison_numbers, explode('.', $version_last_check)[0]);// Check using new major version, performed first
                    }
                }

                foreach ($verison_numbers as $number) {

                    $url = HEURIST_SERVER_URL . '/h' . $number . '-alpha/';
                    $http_response = get_headers($url)[0];
                    if(preg_match('/4\d{2}|5\d{2}/', $http_response) === 0){ // valid
                        $res = $url;
                        break;
                    }
                }

                if($res == ''){ // Finally, check last supported version
                    $url = HEURIST_SERVER_URL . '/alpha/';
                    $http_response = get_headers($url)[0];
                    if(preg_match('/4\d{2}|5\d{2}/', $http_response) === 0){ // valid
                        $res = $url;
                    }
                }
            }
        }

    }elseif($action == 'get_time_diffs'){

        $data = $req_params['data'];
        if(!is_array($data)){
            $data = json_decode($data);
        }

        $early_org = @$data->early_date;
        $latest_org = @$data->latest_date;

        if(empty($early_org) || empty($latest_org)){
            $err = empty($early_org) && empty($latest_org) ? 'Both earliest and latest are ' : (empty($early_org) ? 'Earliest is ' : 'Latest is ');
            $system->addError(HEURIST_ACTION_BLOCKED, $err . 'required');
        }else{

            $err_msg = array();
            $res = true;

            $res = Temporal::getPeriod($early_org, $latest_org);

            if($res === false){
                $system->addError(HEURIST_INVALID_REQUEST, 'Invalid earliest or latest date provided. Impossible to get difference');
            }
        }


    }elseif( !$system->init( $dbname ) ){

    }elseif($action == 'check_for_databases'){ // check if the provided databases are available on the current server

        $mysqli = $system->getMysqli();
        $data = $req_params['data'];
        if(!is_array($data)){
            $data = json_decode($data, true);
        }

        if(JSON_ERROR_NONE !== json_last_error() || !is_array($data)){
            $system->addError(HEURIST_INVALID_REQUEST, 'Invalid database names were provided<br>Please contact the Heurist team.');
            $res = false;
        }else{

            $res = array();
            foreach ($data as $rec_id => $db_name) {

                if(strpos($db_name, HEURIST_DB_PREFIX) === false){
                    $db_name = HEURIST_DB_PREFIX . $db_name;
                }

                $query = "SHOW DATABASES WHERE `database` = '" . $db_name . "'";
                $query_res = $mysqli->query($query);

                if($query_res){
                    $row = $query_res->fetch_row();
                    if($row && $row[0]){
                        $res[$rec_id] = 1;
                    }
                    $query_res->close();
                }
            }
        }
    }elseif($action == 'get_user_notifications'){
        $res = user_getNotifications($system);
    }elseif($action == 'get_tinymce_formats'){

        $settings = $system->settings->getDatabaseSetting('TinyMCE formats');

        if($settings===false){
            $res = false;
        }elseif(empty($settings) || empty($settings['formats'])){
            $res = array(
                'content_style' => '',
                'formats' => array(),
                'style_formats' => array(),
                'block_formats' => ''
            );
        }else{

            $res = array(
                'content_style' => '',
                'formats' => array(),
                'style_formats' => array(),
                'block_formats' => array()
            );

            $valid_formats = array();

            /*
            $settings['formats'] keys => format id, for $settings['style_formats'] and $settings['block_formats']
            $settings['formats'] values => array(
                inline|block => html tag
                classes => html class for selector
                styles => css for class selectors
            )
            */
            if(@$settings['webfonts']){
                $res['webfonts'] = array();
                $settingsURL = $system->getSysUrl('settings');
                foreach($settings['webfonts'] as $key => $font){
                    $font = str_replace("url('settings/", "url('".$settingsURL, $font);
                    $res['webfonts'][$key] = $font;
                }
            }

            foreach($settings['formats'] as $key => $format){

                $key = str_ireplace(' ', '_', $key);// replace spaces with underscore
                if(in_array($key, $valid_formats)){
                    continue; // already handle
                }

                $styles = $format['styles'];

                $classes = $format['classes'];

                if(empty($styles) || empty($classes)){
                    continue;
                }

                $css = "." . implode(", .", explode(" ", $classes)) . " { ";
                foreach($styles as $property => $value){
                    $css .= "$property: $value; ";
                }
                $css .= "} ";

                $res['content_style'] .= $css;

                unset($format['styles']);// avoid inserting css into style attribute of html
                $res['formats'][$key] = $format;

                $valid_formats[] = $key;
            }

            $has_styles = !empty($settings['style_formats']);
            $has_blocks = !empty($settings['block_formats']);

            // Setup style and block formats
            foreach($valid_formats as $key){

                if($has_styles){

                    foreach($settings['style_formats'] as $idx => $style){

                        $style['format'] = str_ireplace(' ', '_', $style['format']);// replace spaces with underscore
                        if($style['format'] == $key){

                            $res['style_formats'][] = $style;

                            unset($settings['style_formats'][$idx]);// remove
                            break;
                        }
                    }

                    $has_styles = !empty($settings['style_formats']);
                }

                if($has_blocks){

                    foreach($settings['block_formats'] as $idx => $block){

                        $block['format'] = str_ireplace(' ', '_', $block['format']);// replace spaces with underscore
                        if($block['format'] == $key){

                            $res['block_formats'][] = $block;

                            unset($settings['block_formats'][$idx]);// remove
                            break;
                        }
                    }

                    $has_blocks = !empty($settings['block_formats']);
                }
            }

            if(empty($res['style_formats']) && empty($res['block_formats'])){
                $res = array();// invalid formatting
            }
        }
    }elseif($action == "translate_string"){ // translate given string using Deepl's API, if able
        $res = getExternalTranslation($system, @$req_params['string'], @$req_params['target'], @$req_params['source']);
    }else{

        $mysqli = $system->getMysqli();

        //allowed actions for guest
        $quest_allowed = array('login','reset_password','svs_savetree','svs_gettree','usr_save','svs_get');

        if ($action=="sysinfo") { //it call once on hapi.init on client side - so it always need to reload sysinfo

            $res = $system->getCurrentUserAndSysInfo(false, (@$req_params['is_guest']==1));

        }elseif($action == "save_prefs"){

            if($system->verifyCredentials($dbname)>0){
                user_setPreferences($system, $req_params);
                $res = true;
            }

        }
        elseif ( $system->getUserId()<1 &&  !in_array($action,$quest_allowed)) {

            $response = $system->addError(HEURIST_REQUEST_DENIED);

        }
        elseif($action=="file_in_folder") { //get list of system images

              $exts = @$req_params['exts'];
              if($exts){
                    $exts = explode(',',$exts);
              }
              if(!is_array($exts) || empty($exts)){
                    $exts = array('png','svg');
              }

              $source = @$req_params['source'];

              if($source=='tilestacks'){
                  $lib_path = array(HEURIST_FILESTORE_DIR.'uploaded_tilestacks/');
              }else{ //assets
                  $lib_path = array('admin/setup/iconLibrary/'.(($source=='assets16')?'16':'64').'px/');
              }

              $res = folderContent($lib_path, $exts);

        }
        elseif($action=="foldercontent") { //get list of files for given folder

              //by default this are mbtiles in uploaded_tilestack

              $source = @$req_params['source'];
              if(@$req_params['exts']){
                    $exts = explode(',',@$req_params['exts']);
              }
              if(!is_array($exts) || empty($exts)){
                    $exts = array('png','svg');
              }

              $include_dates = false;

              if($source=='uploaded_tilestacks'){
                  $lib_path = array(HEURIST_FILESTORE_DIR.'uploaded_tilestacks/');
              }elseif(intval($source)>0){

                  $source = intval($source);
                  if($source==1){
                      $lib_path = HEURIST_FILESTORE_ROOT.'DELETED_DATABASES/';
                  }elseif($source==2){
                      $lib_path = '/srv/BACKUP';
                      $include_dates = true;
                  }elseif($source==3){
                      $include_dates = true;
                      if(strpos(HEURIST_BASE_URL, '://127.0.0.1')>0){
                          $lib_path = HEURIST_FILESTORE_ROOT.'BACKUP/ARCHIVE/';
                      }else{
                          $lib_path = '/srv/BACKUP/ARCHIVE';
                      }
                  }elseif($source==4){
                      $lib_path = HEURIST_FILESTORE_ROOT.'DBS_TO_RESTORE/';
                  }

                  $lib_path = array($lib_path);
              }else{
                  //default 64px
                  $lib_path = array('admin/setup/iconLibrary/'.(($source=='assets16')?'16':'64').'px/');
              }
              $res = folderContent($lib_path, $exts, $include_dates);

        }
        elseif($action=="folders") { //get list of system images

              $folders = $system->getArrayOfSystemFolders();

              $op = @$req_params['operation'];
              if(!$op || $op=='list'){

                  $root_dir = null;
                  if(@$req_params['root_dir']){
                      $root_dir = USanitize::sanitizePath(HEURIST_FILESTORE_DIR.@$req_params['root_dir']);
                  }

                  $res = folderTree($root_dir,
                      array('systemFolders'=>$folders,'format'=>'fancy') );//see utils_file
                  $res = $res['children'];
              }else{

                  $res = false;

                  $dir_name = USanitize::sanitizePath(@$req_params['name']);

                  if($dir_name==''){
                      $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder name is not defined or out of the root');
                  }elseif($op!='create' && !is_dir(HEURIST_FILESTORE_DIR.$dir_name)){
                      $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder name is not a directory');
                  }else{

                  $f_name = htmlspecialchars($dir_name);
                  $folder_name = HEURIST_FILESTORE_DIR.$dir_name;

                  if($folders[strtolower($dir_name)]){
                      $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Cannot modify system folder');
                  }elseif($op=='rename'){

                      $new_name = USanitize::sanitizePath(@$req_params['newname']);
                      if($new_name==''){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'New folder name is not defined or out of the root');
                      }elseif($folders[strtolower($new_name)]){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Name "'.$new_name.'" is reserved for system folder');
                      }elseif(file_exists(HEURIST_FILESTORE_DIR.$new_name)){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, "Folder with name '$new_name' already exists");
                      }elseif(!file_exists($folder_name)){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, "Folder with name '$f_name' does not exist");
                      }else{
                          $res = rename($folder_name, HEURIST_FILESTORE_DIR.$new_name);
                          if(!$res){
                              $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Cannot rename folder "'
                                    .$dir_name.'" to name "'.$new_name.'"');
                          }
                      }

                  }elseif($op=='delete'){

                      if(!file_exists($folder_name)){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder with name "'.$f_name.'" does not exist');
                      }elseif (count(scandir($folder_name))>2){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Non empty folder "'.$f_name.'" cannot be removed');
                      }else{
                          $res = folderDelete2($folder_name, true);
                          if(!$res){
                              $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder "'.$f_name.'" cannot be removed');
                          }
                      }

                  }elseif($op=='create'){
                      if(file_exists($folder_name)){
                          $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder with such name already exists');
                      }else{
                         $res = folderCreate($folder_name, true);
                         if(!$res){
                              $response = $system->addError(HEURIST_ACTION_BLOCKED, 'Folder "'.$dir_name.'" cannot be created');
                         }
                      }
                  }

                  }
              }
        }
        elseif($action == 'check_allow_estc'){ // check if the ESTC or LRC18C lookups are allowed for current server+database

            $msg = '';

            if(isset($ESTC_PermittedDBs) && strpos($ESTC_PermittedDBs, $dbname) !== false){
                if(@$req_params['ver'] == 'ESTC'){ // is original LRC18C lookup, both ESTC and LRC18C DBs need to be on the same server

                    if($dbname == 'Libraries_Readers_Culture_18C_Atlantic'){ // check if current db is the LRC18C DB

                        $query = "SHOW DATABASES WHERE `database` = '". HEURIST_DB_PREFIX ."ESTC_Helsinki_Bibliographic_Metadata'";
                        $res = $mysqli->query($query);

                        if($res){
                            $row = $res->fetch_row();
                            if($row && $row[0]){
                                $res = 1;
                            }
                            $res->close();
                        }
                        if($res != 1){
                            $msg = 'This lookup requires the ESTC_Helsinki_Bibliographic_Metadata database to be on this server.<br>Please use the alternative ESTC_editions or ESTC_works lookups instead.';
                        }
                    }else{
                        $msg = 'This lookup is made for the Libraries_Readers_Culture_18C_Atlantic database only.';
                    }
                }
                if($msg == ''){
                    $res = 1;
                }else{
                    $system->addError(HEURIST_ACTION_BLOCKED, $msg);
                    $res = false;
                }
            }else{
                $msg = 'For licensing reasons this function is only accessible to authorised projects.<br>Please contact the Heurist team if you wish to use this.';
                $system->addError(HEURIST_ACTION_BLOCKED, $msg);
                $res = false;
            }
        }
        else{

            $res = false;

            if ($action=="login") {

                //check request
                $username = @$req_params['username'];
                $password = @$req_params['password'];
                $session_type = @$req_params['session_type'];
                $is_guest = (@$req_params['is_guest']==1);
                $skip_pwd_check = false;
                $res = false;

                if(@$req_params['saml_entity']){

                    $sp = $req_params['saml_entity'];

                    //check saml session
                    require_once dirname(__FILE__).'/../utilities/USaml.php';

                    //if currently authenticated - take username
                    $username = samlLogin($system, $sp, $system->dbname(), false);

                    if($username>0){
                        $password= null;
                        $session_type = 'remember';
                        $skip_pwd_check = true;
                    }else{
                        $username = null;
                    }
                }

                if($username && $system->doLogin($username, $password, $session_type, $skip_pwd_check, $is_guest)){
                    $res = $system->getCurrentUserAndSysInfo( true, $is_guest );//including reccount and dashboard entries

                    checkDatabaseFunctions($mysqli);

                    $system->userLogActivity('Login');
                }

            } elseif($action=="reset_password") {

                $password = array_key_exists('new_password', $req_params) ? $req_params['new_password'] : null;
                if(array_key_exists('new_password', $req_params)) {unset($req_params['new_password']);}// remove from REQUEST

                if($req_params['pin'] && $req_params['username'] && $password){ // update password w/ pin
                    $system->userLogActivity('ResetPassword', "Updating password for {$req_params['username']}");
                    $res = user_ResetPassword($system, $req_params['username'], $password, $req_params['pin']);
                }elseif($req_params['pin']){ // get/validate reset pin
                    $system->userLogActivity('ResetPassword', "Handling reset pin for {$req_params['username']}");
                    $res = user_HandleResetPin($system, @$req_params['username'], @$req_params['pin'], @$req_params['captcha']);
                }else{
                    $res = $system->addError(HEURIST_ERROR, 'An invalid request was made to the password reset system.<br>Please contact the Heurist team.');
                }

                /* original method - Lets random people reset passwords for random accounts
                if(user_ResetPasswordRandom($system, @$req_params['username'])){
                    $res = true;
                }
                */

            } elseif ($action=="action_password") { //special passwords for some admin actions - defined in configIni.php

                $actions = array('DatabaseCreation', 'DatabaseDeletion', 'ReservedChanges', 'ServerFunctions');
                $action = @$req_params['action'];
                $password = @$req_params['password'];

                if($action && in_array($action, $actions) && !empty($password)){
                    $varname = 'passwordFor'.$action;
                    $varvalue = @${$varname};
                    $res = ($varvalue==$password)?'ok':'wrong';
                }

            }
              elseif($action=="sys_info_count") {

                $res = $system->getTotalRecordsAndDashboard();

            } elseif($action=="usr_save") {

                USanitize::sanitizeRequest($req_params);

                $is_guest_registration = (@$req_params['is_guest']==1);

                $res = user_Update($system, $req_params, $is_guest_registration);

                if($res!==false && $is_guest_registration){
                    //login at once
                    if($system->doLogin($res, null, 'remember', true, true)){
                        $res = $system->getCurrentUserAndSysInfo( true, $is_guest_registration );//including reccount and dashboard entries
                    }
                }

            } elseif($action=="usr_get" && is_numeric(@$req_params['UGrpID'])) {

                $ugrID = $req_params['UGrpID'];

                if($system->hasAccess($ugrID)){  //allowed for itself only
                    $res = user_getById($system->getMysqli(), $ugrID);
                    if(is_array($res)){
                        $res['ugr_Password'] = '';
                    }
                }else{
                    $system->addError(HEURIST_REQUEST_DENIED);
                }

            } elseif($action=="usr_names" && @$req_params['UGrpID']) {

                $res = user_getNamesByIds($system, $req_params['UGrpID']);

            } elseif($action=="groups") {

                $ugr_ID = @$req_params['UGrpID']?$req_params['UGrpID']:$system->getUserId();

                $res = user_getWorkgroups($system->getMysqli(), $ugr_ID, true);

            } elseif($action=="members" && @$req_params['UGrpID']) {

                $res = user_getWorkgroupMembers($system->getMysqli(), @$req_params['UGrpID']);

            } elseif($action=="user_wss") {

                $res = user_WorkSet($system, $req_params);

            } elseif($action=="svs_copy"){

                $res = svsCopy($system, $req_params);

            } elseif($action=="svs_save"){

                USanitize::stripScriptTagInRequest($req_params);
                $res = svsSave($system, $req_params);

            } elseif($action=="svs_delete" && @$req_params['ids']) {

                $res = svsDelete($system, $req_params['ids'], @$req_params['UGrpID']);

            } elseif($action=="svs_get" ) {

                if(@$req_params['svsIDs']){
                    $res = svsGetByIds($system, $req_params['svsIDs']);
                }else{
                    $res = svsGetByUser($system, @$req_params['UGrpID'], @$req_params['keep_order']);
                }

            } elseif($action=="svs_savetree" ) { //save saved searches tree status

                USanitize::stripScriptTagInRequest($req_params);
                $res = svsSaveTreeData($system, @$req_params['data']);

            } elseif($action=="svs_gettree" ) { //save saved searches tree status

                $res = svsGetTreeData($system, @$req_params['UGrpID']);


            }elseif($action == 'get_url_content_type'){

                $url = filter_input(INPUT_POST, 'url', FILTER_VALIDATE_URL);

                $res = recognizeMimeTypeFromURL($mysqli, $url, false);

            }elseif($action == 'upload_file_nakala'){ //@todo - move to separate controller

                // load ONE file to ext.repository - from manageRecUploadedFiles
                // see also local_to_repository in record_batch

                $credentials = user_getRepositoryCredentials2($system, $req_params['api_key']);
                if($credentials === null || !@$credentials[$service_id]['params']['writeApiKey']){
                    $system->addError(HEURIST_INVALID_REQUEST, 'We were unable to retrieve the specified Nakala API key, please ensure you have entered the API key into Design > External repositories');
                }else{

                    // Prepare parameters
                    $params = array();

                    // File
                    $params['file'] = array(
                        'path' => HEURIST_FILESTORE_DIR . DIR_SCRATCH
                                . USanitize::sanitizeFileName(USanitize::sanitizePath($req_params['file'][0]['name'])),
                        'type' => htmlspecialchars($req_params['file'][0]['type']),
                        'name' => htmlspecialchars($req_params['file'][0]['original_name'])
                    );

                    // Metadata
                    $params['meta']['title'] = array(
                        'value' => htmlspecialchars(@$req_params['meta']['title']),
                        'lang' => null,
                        'typeUri' => XML_SCHEMA,
                        'propertyUri' => NAKALA_REPO.'terms#title'
                    );

                    if(empty($req_params['meta']['creator']['authorId'])){
                        $params['meta']['creator'] = array(
                            'value' => null,
                            'lang' => null,
                            'typeUri' => null,
                            'propertyUri' => NAKALA_REPO.'terms#creator'
                        );

                        if(array_key_exists('givenname', $req_params['meta']['creator']) || array_key_exists('surname', $req_params['meta']['creator'])){

                            $fullname = '';
                            if(array_key_exists('givenname', $req_params['meta']['creator'])){
                                $fullname .= htmlspecialchars($req_params['meta']['creator']['givenname']);
                            }
                            if(array_key_exists('surname', $req_params['meta']['creator'])){
                                $fullname .= ' ' . htmlspecialchars($req_params['meta']['creator']['surname']);
                            }
                            $fullname = trim($fullname);

                            $params['meta']['alt_creator'] = array(
                                'value' => $fullname,
                                'lang' => null,
                                'typeUri' => XML_SCHEMA,
                                'propertyUri' => 'http://purl.org/dc/terms/creator'
                            );
                        }
                    }else{
                        $params['meta']['creator'] = array(
                            'value' => @$req_params['meta']['creator'],
                            'propertyUri' => NAKALA_REPO.'terms#creator'
                        );
                    }

                    if(array_key_exists('created', $req_params['meta']) && !empty($req_params['meta']['created'])){
                        $params['meta']['created'] = array(
                            'value' => @$req_params['meta']['created'],
                            'lang' => null,
                            'typeUri' => XML_SCHEMA,
                            'propertyUri' => NAKALA_REPO.'terms#created'
                        );
                    }else{
                        $params['meta']['created'] = array(
                            'value' => null,
                            'lang' => null,
                            'typeUri' => null,
                            'propertyUri' => NAKALA_REPO.'terms#created'
                        );
                    }

                    $params['meta']['type'] = array(
                        'value' => @$req_params['meta']['type'],
                        'lang' => null,
                        'typeUri' => 'http://www.w3.org/2001/XMLSchema#anyURI',
                        'propertyUri' => NAKALA_REPO.'terms#type'
                    );

                    $params['meta']['license'] = array(
                        'value' => @$req_params['meta']['license'],
                        'lang' => null,
                        'typeUri' => XML_SCHEMA,
                        'propertyUri' => NAKALA_REPO.'terms#license'
                    );

                    // User API Key
                    $params['api_key'] = $credentials[$req_params['api_key']]['params']['writeApiKey'];

                    $params['use_test_url'] = @$req_params['use_test_url'] == 1 || strpos($req_params['api_key'],'nakala')===1 ? 1 : 0;

                    $params['status'] = 'published';// publish uploaded file, return url to newly uploaded file on Nakala

                    // Upload file
                    $res = uploadFileToNakala($system, $params);//from record edit - define file field

                }

                if($res !== false){
                    // delete local file after upload
                    fileDelete(HEURIST_FILESTORE_DIR . DIR_SCRATCH . basename($req_params['file'][0]['name']));
                    fileDelete(HEURIST_FILESTORE_DIR . '/scratch/thumbnail/' . basename($req_params['file'][0]['name']));
                }
            } else {

                $system->addError(HEURIST_INVALID_REQUEST);
            }


        }

        $system->dbclose();
    }

    if(is_bool($res) && !$res){
        $response = $system->getError();
    }else{
        $response = array("status"=>HEURIST_OK, "data"=> $res);
        if(@$req_params['context']){
            $response['context'] = filter_var($req_params['context']);
        }
    }

$system->setResponseHeader();
print json_encode($response);
?>