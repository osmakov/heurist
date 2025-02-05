<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;
use hserv\System;
use hserv\entity\DbRecUploadedFiles;

    /**
    * Function specific to the Heurist_Job_Tracker database on HeuristRef.net
    *  Queries user for issue details and populates a Type 56 (concept ID 8-23) 
    *  Task (Features, Bug, Issue) record in the database
    *
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     6.6.5
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */
    
require_once dirname(__FILE__).'/../records/search/recordFile.php';

define('DT_FILE','type:2-38');

class DbSysBugreport extends DbEntityBase
{

    private $performLogout = false; // perform logout after completing the required action

    private $reportEmail = <<<EMAIL
    Your bug report has been successfully added to the Heurist Job tracker database.<br> <br>
    
    You can view your report at: <a href="__LINK__">__LINK__</a><br><br>
    
    For current and resolved issues list see: <a href="https://heuristref.net/Heurist_Job_Tracker/web/64/1526">https://heuristref.net/Heurist_Job_Tracker</a><br><br>
    <br>
    Reporter: __NAME__ [__EMAIL__]<br>
    Database: __DBLINK__<br>
    Bug description:<br>__DESC__
    EMAIL;

    public function __construct( $system, $data=null ) {
       parent::__construct( $system, $data );
       $this->requireAdminRights = false;
    }

    protected function _validatePermission(){

        $res = parent::_validatePermission();

        if(!$res){

            $attempt_public_login = strpos(strtolower(HEURIST_BASE_URL), strtolower(HEURIST_MAIN_SERVER)) !== false
                                    && $this->system->dbname() == HEURIST_BUGREPORT_DATABASE;

            $this->performLogout = $attempt_public_login ? $this->system->doLogin('extern', null, 'public', true) : false; // attempt login to publicly available guest account

            if($this->performLogout){
                $this->system->getCurrentUserAndSysInfo(false); // refresh system vars before continuing
                $res = true;
            }
        }

        return $res;
    }

    /**
    *  search users
    *
    *  other parameters :
    *  details - id|name|list|all or list of table fields
    *  offset
    *  limit
    *  request_id
    *
    *  @todo overwrite
    */
    public function search(){
        return null;
    }

    //
    //   This is virtual "save". In fact it sends email
    //
    public function save(){

        if(!$this->prepareRecords()){
            return false;
        }

        if(is_array($this->records) && count($this->records)==1){
            //SPECIAL CASE - this is response to emailForm widget (from CMS website page)
            // it sends email to owner of database or to email specified in website_id record
            $fields = $this->records[0];
            if(array_key_exists('email', $fields) && array_key_exists('content', $fields)){
                return $this->_prepareEmail($fields);
            }
        }

        //validate permission for current user and set of records see $this->recordIDs
        if(!$this->_validatePermission()){
            return false;
        }

        if(array_key_exists('new_record', $this->data)){
            // Called from external server

            $res = $this->createBugReportRecord($this->data['new_record']);

            if($this->performLogout){
                $this->system->doLogout();
            }

            return $res !== false ? $res['data'] : $res;
        }

        //validate values and check mandatory fields
        foreach($this->records as $record){

            $this->data['fields'] = $record;

            //validate mandatory fields
            if(!$this->_validateMandatory()){
                return false;
            }
        }

        $record = $this->records[0];

        $mysqli = $this->system->getMysqli();

        $toEmailAddress = HEURIST_MAIL_TO_BUG;

        if(!(isset($toEmailAddress) && $toEmailAddress)){
             $this->system->addError(HEURIST_SYSTEM_CONFIG,
                    'The owner of this instance of Heurist has not defined either the info nor system emails');
             return false;
        }

        $sMessage = '';

        $new_record = [
            'ID' => 0,// New record
            'RecTypeID' => 56,// Task (Feature, Bug, Issue) rectype on Heurist_Job_Tracker
            'NonOwnerVisibility' => 'public',// Force visibility to public
            'NonOwnerVisibilityGroups' => 0,// Force group visibility to everyone
            'OwnerUGrpID' => 0,// Force ownership to DB admins later
            'details' => []
        ];

        $report_title = htmlspecialchars($record['2-1']);
        $bug_title = "Bug report or feature request: $report_title";
        $new_record['details']['1'] = $report_title;

        //keep new line
        $bug_descr = htmlspecialchars($record['2-3']);
        if(!empty($bug_descr)){

            $bug_descr = '<p>' . str_replace("\n",'<br>', $bug_descr) . '</p>';

            $new_record['details']['3'] = $bug_descr;
            $sMessage = $bug_descr;
        }

        $repro_steps = $record['2-4'];
        if(!empty($repro_steps)){
            $sMessage = $sMessage.'<p>Reproduction steps:<br>'.implode('<br>',$repro_steps).'</p>';
            $new_record['details']['4'] = $repro_steps;
        }

        //add current system information into message
        $ext_info = [];
        array_push($ext_info, "    Browser information: ".htmlspecialchars($_SERVER['HTTP_USER_AGENT']));

        //add current heurist information into message
        array_push($ext_info, "   Heurist url: ".HEURIST_BASE_URL.'?db='.HEURIST_DBNAME);
        array_push($ext_info, "   Heurist version: ".HEURIST_VERSION);
        array_push($ext_info, "   Heurist dbversion: ".getDbVersion($mysqli));

        //extra information
        $types = array_key_exists('2-2', $record) ? $record['2-2'] : 'None provided';
        $type_term = [];
        $arr_types = explode(',', $types);

        // Sets form return values associated with (internal) term codes in the Heurist_Job_Tracker database
        foreach($arr_types as $type){
            switch($type){
                case 'Suggestion / feature request':
                    $type_term[] = 6983;  // term = 09 New feature
                    break;

                case 'Minor annoyance':
                    $type_term[] = 6981;  // term = 06 Workflow or minor annoyance
                    break;

                case 'Major annoyance':
                    $type_term[] = 6980;  // term = 05 Unexpected behaviour or major annoyance
                    break;

                case 'Minor bug':
                    $type_term[] = 6982;  // term = 07 Minor bug or cosmetic issue
                    break;

                case 'Significant bug':
                    $type_term[] = 6977;  // term = 02 Severe
                    break;
                              
                case 'Urgent bug':
                    $type_term[] = 6976;  // term = 01 Fatal
                    break;

                default:
                    $type_term[] = 6986;   // term = ? (unassinged)
                    break;
            }
        }

        $new_record['details']['960'] = empty($type_term) ? 6986 : $type_term;
        array_push($ext_info, "   Report type: $types");

        $url = @$record['3-1058'];
        $cur_url = HEURIST_BASE_URL.'?db='.HEURIST_DBNAME;
        if(!empty($url)){
            array_push($ext_info, "   Provided url: $url   Base url: $cur_url");
            $new_record['details']['993'] = [$url,$cur_url];
        }else{
            $new_record['details']['993'] = $cur_url;
        }

        $user_info = $this->system->getCurrentUser();
        if($user_info){

            $user = user_getByField($mysqli, 'ugr_ID', $user_info['ugr_ID']);

            array_push($ext_info, "   Heurist user: ".@$user['ugr_Name'].' ('.@$user['ugr_eMail'].')');

            $new_record['details']['955'] = "{$user_info['ugr_FullName']} [{$user['ugr_Organisation']}]";
            $new_record['details']['956'] = $user['ugr_eMail'];
        }

        //$new_record['details']['2-51'] = $ext_info;
        $ext_info = '<p>'.implode('<br>',$ext_info).'</p>';

        $filename = null;
        $attachment_temp_name = @$record['2-38'];
        if(!empty($attachment_temp_name)){

            if(!is_array($attachment_temp_name)){
                $attachment_temp_name = [$attachment_temp_name];
            }

            $filename = [];
            $new_record['details']['38'] = [];
            foreach ($attachment_temp_name as $file) {

                $info = parent::getTempEntityFile($file);

                if(!$info){
                    continue;
                }

                $filename[] = $info->getPathname();

                $new_record['details']['38'][] = $this->system->getSysUrl(DIR_ENTITY) . "{$this->config['entityName']}/{$info->getFilename()}";
            }
        }

        $res = false;
        if(strpos(strtolower(HEURIST_BASE_URL), strtolower(HEURIST_MAIN_SERVER)) !== false){ // on server with Heurist_Job_Tracker DB
            $res = $this->createBugReportRecord($new_record);
        }else{

            $params = [
                'a' => 'save',
                'entity' => 'sysBugreport',
                'db' => HEURIST_BUGREPORT_DATABASE,
                'new_record' => $new_record,
                'fields' => ['is_bug_report' => 1]
            ];
            $url = HEURIST_MAIN_SERVER . '/h6-alpha/hserv/controller/entityScrud.php?' . http_build_query($params);

            $res = loadRemoteURLContentWithRange($url, null, true, 60);

            $json = json_decode($res, true);
            $res = json_last_error() === JSON_ERROR_NONE
                    ? $json
                    : ['status' => HEURIST_UNKNOWN_ERROR, 'message' => 'An unknown response was returned from the main Heurist server.<br>Please, ' . CONTACT_HEURIST_TEAM . ' directly'];
        }

        if($this->performLogout){
            $this->system->doLogout();
        }

        $email_already_sent = false;
        if($res){

            // Add record ID, title & url to edit record
            $rec_ID = @$res['status'] == HEURIST_OK ? $res['data']['recID'] : 0;
            $email_already_sent = $rec_ID > 0 ? $res['data']['email_sent'] : false;

            if($rec_ID > 0){

                $bug_title = "Heurist tracker #$rec_ID: {$record['2-1']}";
                $report_link = HEURIST_MAIN_SERVER . "/" . HEURIST_BUGREPORT_DATABASE . "/view/$rec_ID";
                $sMessage .= "<p>Link: $report_link</p>";

                $user_name = is_array($user_info) ? $user_info['ugr_FullName'] : 'None found';
                $user_email = is_array($user_info) ? $user_info['ugr_eMail'] : 'None found';

                $res = str_replace(['__LINK__', '__DESC__','__NAME__','__EMAIL__','__DBLINK__'], [$report_link, $record['details']['3'], $user_name, $user_email, $cur_url], $this->reportEmail);

            }elseif(is_array($res)){
                $this->system->addErrorArr($res);
                $res = false;
            }
        }

        $sMessage .= $ext_info;

        if($res && $email_already_sent){
            return [$res];
        }elseif(!$email_already_sent && sendPHPMailer(null, 'Bug reporter', $toEmailAddress,
                $bug_title,
                $sMessage, //since 02 Dec 2021 we sent human readable message
                $filename, true)){

            $message = $res ?: "Your bug report has been sent to the Heurist team.";
            return $res === false ? false : [$message];
        }else{

            $error_msg = 'An unknown error has prevented Heurist from create the bug report.<br>Please re-try in a few minutes, however if the issue persists please ' . CONTACT_HEURIST_TEAM . ' directly.';
            $email_already_sent || $this->system->addError(HEURIST_UNKNOWN_ERROR, $error_msg);
            return false;
        }
    }

    private function createBugReportRecord($record){

        if(empty(@$record['details'])){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Bug report details are missing');
            return false;
        }

        $using_db = $this->system->dbname() == HEURIST_BUGREPORT_DATABASE;
        $report_system = $using_db ? $this->system : null;
        if(!$using_db && strpos(strtolower(HEURIST_BASE_URL), strtolower(HEURIST_MAIN_SERVER)) !== false){

            $report_system = new System();
            $using_db = $report_system->init(HEURIST_BUGREPORT_DATABASE, true, false);

            if($using_db && !$report_system->hasAccess()){
                $using_db = $report_system->doLogin('extern', null, 'public', true);
                !$using_db || $report_system->getCurrentUserAndSysInfo();
            }
        }

        $report_system_ready = $report_system && $report_system->isInited() && $report_system->hasAccess();
        if($using_db !== true || !$report_system_ready){
            $action = $report_system && !$report_system->hasAccess() ? 'access' : 'connect to';
            $this->system->addError(HEURIST_ACTION_BLOCKED, "Heurist was unable to $action the Job tracker database");
            return false;
        }

        $files = [];
        $rec_uploads = new DbRecUploadedFiles($report_system);
        if(!empty($record['details']['38']) && $rec_uploads){
            foreach($record['details']['38'] as $idx => $file_url){

                $file_name = explode("\\", $file_url);
                $file_name = str_replace('~', 'bugreport_img_', array_pop($file_name));

                $record['details']['38'][$idx] = $rec_uploads->downloadAndRegisterdURL($file_url, ['ulf_NewName' => $file_name], 2);

                if(!$record['details']['38'][$idx]){
                    continue;
                }

                $ulf_file_name = mysql__select_value($this->system->getMysqli(), "SELECT ulf_FileName FROM recUploadedFiles WHERE ulf_ID = {$record['details']['38'][$idx]}");
                $files[] = $this->system->getSysDir(DIR_FILEUPLOADS) . $ulf_file_name;
            }
            $record['details']['38'] = array_filter($record['details']['38']); // remove null/false values
        }

        $mysqli = $report_system->getMysqli();
        $guest_user = user_getByField($mysqli, 'ugr_Name', 'extern');// to update AddedBy value in new record
        $uid = is_array($guest_user) ? $guest_user['ugr_ID'] : 0;

        $res = recordSave($report_system, $record, true, false, 0, 2);// set total recs to 2 to avoid sending the swf email, we will send a more specific email instead
        $sent_email = false;

        if(@$res['status'] != HEURIST_OK){
            // Transfer error across
            $this->system->addErrorArr($report_system->getError());
            return false;
        }

        $res = $res['data'];

        mysql__insertupdate($mysqli, 'Records', 'rec', ['rec_ID' => $res, 'rec_AddedByUGrpID' => $uid, 'rec_OwnerUGrpID' => 2]);

        recordUpdateTitle($report_system, $res, $record['RecTypeID'], "Bug report: {$record['details']['1']}");

        if(!empty($record['details']['956'])){

            $title = "Heurist tracker #$res: {$record['details']['1']}";

            $report_link = HEURIST_MAIN_SERVER . "/" . HEURIST_BUGREPORT_DATABASE . "/view/$res";

            $user_name = $record['details']['955'] ?? 'None found';
            $user_name = strpos($user_name, '[') > 0 ? explode('[', $user_name)[0] : $user_name;

            $user_email = $record['details']['956'] ?? 'None found';

            $db_link = is_array($record['details']['993']) ? $record['details']['993'][1] : $record['details']['993'];

            $msg = str_replace(['__LINK__', '__DESC__', '__NAME__', '__EMAIL__','__DBLINK__'], [$report_link, $record['details']['3'], $user_name, $user_email, $db_link], $this->reportEmail);

            $user_query = "SELECT ugr_eMail FROM sysUsrGrpLinks LEFT JOIN sysUGrps ON ugr_ID = ugl_UserID WHERE ugl_GroupID = 1 AND ugl_Role='admin'";
            $admin_emails = mysql__select_list2($mysqli, $user_query);

            $sent_email = sendPHPMailer(null, 'Bug reporter', ['to' => $record['details']['956'], 'bcc' => $admin_emails], $title, $msg, $files, true);
        }

        return ['status' => HEURIST_OK, 'data' => ['recID' => $res, 'email_sent' => $sent_email]];
    }

    //
    // this is response to emailForm widget
    // it sends email to owner of database or to email specified in website_id record
    //
    private function _prepareEmail($fields){

        //1. verify captcha
        if (@$fields['captcha'] && @$_SESSION["captcha_code"]){

            $is_InValid = (@$_SESSION["captcha_code"] != @$fields['captcha']);

            if (@$_SESSION["captcha_code"]){
                unset($_SESSION["captcha_code"]);
            }

            if($is_InValid) {
                $this->system->addError(HEURIST_ACTION_BLOCKED,
                   'Are you a bot? Please enter the correct answer to the challenge question');
                return false;
            }
        }else {
            $this->system->addError(HEURIST_ACTION_BLOCKED,
                    'Captcha is not defined. Please provide correct value');
            return false;
        }


        //2. get email fields
        $email_text = @$fields['content'];
        if(!$email_text){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Email message is not defined.');
            return false;
        }
        $email_from = @$fields['email'];
        if(!$email_from){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'Email address is not defined.');
            return false;
        }


        $email_title = null;
        $email_from_name = @$fields['person'];
        $email_to = null;

        //3. get $email_to - either address from website_id record or current database owner
        $rec_ID = $fields['website_id'];
        if($rec_ID>0){
            $this->system->defineConstant('DT_NAME');
            $this->system->defineConstant('DT_EMAIL');

            $record = recordSearchByID($this->system, $rec_ID, array(DT_NAME, DT_EMAIL), 'rec_ID');
            if($record){
                $email_title = 'From website '.recordGetField($record, DT_NAME).'.';
                $email_to = recordGetField($record, DT_EMAIL);
                if($email_to) {$email_to = explode(';', $email_to);}
            }
            $email_from_name = 'Website contact';
        }else{
            $email_from_name = 'Bug Reporter';
        }
        if(!$email_to){
            $email_to = user_getDbOwner($this->system->getMysqli(), 'ugr_eMail');
        }
        if(!$email_title){
            $email_title = '"Contact us" form. ';
        }
        if($email_from_name){
            $email_title = $email_title.'  From '.$email_from_name;
        }
        $email_text = 'From '.$email_from.' ( '.$email_from_name.' )<br>'.$email_text;

        $email_from = null;
        $email_from_name = null;


        if(sendPHPMailer(null, $email_from_name, $email_to,
                $email_title,
                $email_text,
                null, true))
        {
                return array(1);
        }else{
            return false;
        }

    }

    //
    //
    //
    public function delete($disable_foreign_checks = false){
        return false;
    }

    //
    // batch action for users
    // 1) import users from another db
    //
    public function batch_action(){
         return false;
    }
}
?>
