<?php

/**
*  Related class for Heurist System Email (massEmailSystem.php)
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Brandon McKay   <blmckay13@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

use hserv\utilities\USanitize;
use hserv\structure\ConceptCode;

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
require_once dirname(__FILE__).'/../../hserv/records/edit/recordModify.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/*
Return Codes:

1 => Function Specific
0 => No Error
-1 => General/Script Error
-2 => MySQLi Error
-3 => phpMailer Error
-4 => File Error
*/

class SystemEmailExt {

    private $cur_user; // logged in user's details

    public $databases; // list of selected DBs
    public $invalid_dbs; // list of invalid DBs

    public $users; // user options => owner: DB Owners, manager: DB Manager Admins, user: All Users, admin: All Admins

    private $user_details; // list of user details and databases, indexed on user emails
    private $user_invalid_email; // list of users with invalid emails

    public $email_subject; // email title/subject, from the name/title field from a notes record
    public $email_body; // email body, from the short summary field from a notes record + final editing

    public $rec_count; // number of records to include, default: 0

    public $rec_lastmod_period;// time period, default: 6
    public $rec_lastmod_unit; // unit of time, default: MONTH
    public $rec_lastmod_logic; // logic, default: <=

    private $records; // array of records+last modified information

    private $use_native_mail_function = false;
    private $debug_run = false;

    private $log = '';// log of emails, to be placed within a note record within the databases, extended version of receipt
    private $receipt; // receipt for all email transactions, is saved into current db as a note record with the Notes title (not rec_Title) set to "Heurist System Email Receipt"
    private $emails_sent_count = 0;
    private $error_msg = '';// error message

    private $user_options = array("owner", "admin", "manager", "user");// available user options
    private $substitute_vals = array("##firstname##", "##lastname##", "##email##", "##database##", "##dburl##", "##records##", "##lastmodified##");// available email body substitutions

    /*
    * Process the data received
    *
    * Param: $data => (int) Passed Form Data
    *
    * Return: VOID || Error Code
    */

    public function processFormData($data) {

        global $system;

        $rtn = 0;

        $this->databases = null;

        if (!$this->validateDatabaseInput($data)) {
            return -1;
        }

        if (!$this->validateUserInput($data)) {
            return -1;
        }

        // Get record filtering options, use defaults if none supplied
        $this->rec_count = (isset($data["recTotal"]) && is_numeric($data["recTotal"]) && $data["recTotal"] >= 0) ? $data["recTotal"] : "none";

        $this->rec_lastmod_period = (isset($data["recModVal"]) && is_numeric($data["recModVal"]) && $data["recModVal"] > 0) ? $data["recModVal"] : 6;
        $this->rec_lastmod_unit = isset($data["recModInt"]) ? $data["recModInt"] : "MONTH";
        $this->rec_lastmod_logic = isset($data["recModLogic"]) ? $data["recModLogic"] : "<=";

        // Initialise other variables
        $this->user_details = array();
        $this->user_invalid_email = array();

        $this->log = "";
        $this->receipt = null;
        $this->error_msg = "";

        if(@$data["use_native"]==1){
            $this->use_native_mail_function = true;
        }

        $rtn = $this->createUserList();// save user information; first and last name, email, and list of databases

        if ($rtn != 0) {
            return $rtn;
        }elseif(isEmptyArray($this->user_details)){

            $this->set_error('No users have been retrieved, no emails have been sent');
            return -1;
        }

        $rtn = $this->createRecordsList();// save record counts and last modified record dates for each db

        if ($rtn != 0) {
            return $rtn;
        }

        // Retrieve current user's email address
        $this->cur_user = $system->getCurrentUser();
        $this->getUserEmail();

        return 0;
    }

    private function validateDatabaseInput($data) {
        if (!isset($data["db"])) {
            $this->set_error('No current database has been provided<br>Please contact the Heurist team if this problem persists.');
            return false;
        }

        if (isset($data["databases"])) {
            if (!is_array($data["databases"])) {
                $data["databases"] = explode(',', $data["databases"]);
            }

            if (is_array($data["databases"]) && count($data["databases"]) >= 1) {
                $this->databases = $this->validateDatabases($data["databases"]);
            }
        }

        if (isEmptyArray($this->databases)) {
            $provided_dbs = is_array($data["databases"]) ? "" : "<br>databases => " . htmlspecialchars($data["databases"]);
            $this->set_error('No valid databases have been provided.' . $provided_dbs);
            return false;
        }

        return true;

    }

    private function validateUserInput($data) {
        if (isset($data["users"]) && in_array($data["users"], $this->user_options)) {
            $this->users = $data["users"];
        } else {
            $main_msg = 'No valid users have been provided.<br>users => '
            . (isset($data["users"]) ? htmlspecialchars(print_r($data["users"], true)) : ' not defined');
            $this->set_error($main_msg);
            return false;
        }

        $this->email_subject = isset($data["emailTitle"]) && is_string($data["emailTitle"]) ? $data["emailTitle"] : null;

        if (!isset($data["emailBody"]) || !is_string($data["emailBody"])) {
            $this->set_error('No email body has been provided');
            return false;
        }

        $this->email_body = $data["emailBody"];
        return true;
    }

    /*
    * Get current user's email
    *
    * Param: None
    *
    * Return: VOID
    */

    private function getUserEmail() {

        global $system;
        $mysqli = $system->getMysqli();

        $query = "SELECT ugr.ugr_eMail FROM ". HEURIST_DBNAME_FULL .".sysUGrps AS ugr WHERE ugr.ugr_ID = ". $this->cur_user['ugr_ID'];

        $email = false;
        $eres = $mysqli->query($query);
        if($eres){
            $email = $eres->fetch_row()[0];
            $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        }

        if(!$email){
            //not found or invalid
            $this->cur_user['ugr_eMail'] = HEURIST_MAIL_TO_ADMIN;
        }else{
            $this->cur_user['ugr_eMail'] = $email;
        }
    }

    /*
    * Validate the list of database, ignore any invalid databases
    *
    * Param: $db_list => List of selected databases
    *
    * Return:
    * Array, List of valid databases
    */

    private function validateDatabases($db_list) {

        global $system;
        $mysqli = $system->getMysqli();

        $valid_dbs = array();

        foreach($db_list as $db){

            // Required tables  are 'Records', 'recDetails', 'sysUGrps', and 'sysUsrGrpLinks'
            $query = "SHOW TABLES IN ".$db." WHERE Tables_in_".$db." = 'Records' OR Tables_in_".$db." = 'recDetails' OR Tables_in_".$db." = 'sysUGrps' OR Tables_in_".$db." = 'sysUsrGrpLinks'";

            $table_listing = $mysqli->query($query);
            if (!$table_listing || mysqli_num_rows($table_listing) != 4) { // Skip, missing required tables
                continue;
            }

            $valid_dbs[] = $db;
        }

        return $valid_dbs;
    }

    /*
    * Create list of user details and associated databases
    *
    *
    * Param: None
    *
    * Return: VOID || Error Code
    */

    private function createUserList() {

        global $system;
        $mysqli = $system->getMysqli();

        $dbs = $this->databases;
        $users = $this->users;

        $wg_count = 0;

        foreach ($dbs as $db){

            $where_clause = $this->generateWhereClause($this->users, $db);

            if (empty($where_clause)) {
                $this->set_error('Unable to construct WHERE clause for User List query due to an invalid users option<br>users => '
                    . htmlspecialchars($this->users));
                return -1;
            }

            $query = "SELECT DISTINCT ugr.ugr_FirstName, ugr.ugr_LastName, ugr.ugr_eMail, ugr.ugr_ID
            FROM " . $db . ".sysUsrGrpLinks AS ugl
            INNER JOIN " . $db . ".sysUGrps AS ugr ON ugl.ugl_UserID = ugr.ugr_ID "
            . $where_clause;

            $res = $mysqli->query($query);
            if (!$res) {

                continue;
            }

            $this->processUserResults($res, $db);

            $res->close();

        }

        return 0;
    }

    private function generateWhereClause($users, $db) {
        switch ($users) {
            case "owner":
                return "WHERE ugr.ugr_ID = 2";
            case "manager":
                return "WHERE ugl.ugl_Role = 'admin' AND ugr.ugr_Enabled != 'n' AND ugl.ugl_GroupID = 1";
            case "admin":
                return "WHERE ugl.ugl_Role = 'admin' AND ugr.ugr_Enabled != 'n' AND ugl.ugl_GroupID IN
                (SELECT ugr_ID FROM $db.sysUGrps WHERE ugr_Type = 'workgroup' AND ugr_Enabled != 'n')";
            case "user":
                return "WHERE ugr.ugr_Type = 'user' AND ugr.ugr_Enabled != 'n'";
            default:
                return "";
        }
    }

    private function processUserResults($res, $db) {
        while ($row = $res->fetch_row()) {
            $db_name = substr($db, strlen(HEURIST_DB_PREFIX));

            $email = filter_var($row[2], FILTER_VALIDATE_EMAIL);


            if (!$email) {
                $this->user_invalid_email[] = array($db, $row[0], $row[1], $row[3], $row[2]);
            } else {
                if (array_key_exists($email, $this->user_details)) {
                    if (!in_array($db_name, $this->user_details[$email]["db_list"])) {
                        $this->user_details[$email]["db_list"][] = $db_name;
                    }
                } else {
                    $this->user_details[$email] = [
                        "first_name" => $row[0],
                        "last_name" => $row[1],
                        "db_list" => [$db_name]
                    ];
                }
            }
        }
    }

    /*
    * Retrieve the record count and newest last modified date
    *
    * Param: None
    *
    * Return: VOID || Error Code
    */


    private function createRecordsList() {

        global $system;
        $mysqli = $system->getMysqli();

        $dbs = $this->databases;

        $lastmod_period = $this->rec_lastmod_period;
        $lastmod_unit = $this->rec_lastmod_unit;
        $lastmod_logic = $this->rec_lastmod_logic;

        // Create Last modified's WHERE Clause
        $lastmod_where = ($lastmod_unit!="ALL") ? "AND rec_Modified " . $lastmod_logic . " date_format(curdate(), '%Y-%m-%d') - INTERVAL " . $lastmod_period . " " . $lastmod_unit . " " : "";

        foreach ($dbs as $db) {

            $count = 0;
            $date = "unknown";

            // Get record count
            $query = "SELECT COUNT(*)
            FROM (
            SELECT *
            FROM " . $db . ".Records AS rec
            WHERE rec_Title IS NOT NULL
            AND rec_Title NOT LIKE 'Heurist System Email Receipt%'
            AND rec_FlagTemporary != 1
            AND rec_Title != '' " . $lastmod_where . "
            ) AS a";

            $res = $mysqli->query($query);
            if (!$res) {

                $this->set_error('Query Error: Unable to get record count for the '
                    .htmlspecialchars($db).' database<br>Error => ' .htmlspecialchars($mysqli->error));
                return -2;
            }

            if ($row = $res->fetch_row()) {
                $count = $row[0];
            }

            $res->close();

            // Get newest record/last edited record, ignore system email receipt records
            $query = "SELECT max(rec_Modified)
            FROM $db.Records AS rec
            WHERE rec_Title IS NOT NULL
            AND rec_Title != '' $lastmod_where
            AND rec_Title NOT LIKE 'Heurist System Email Receipt%'
            ORDER BY rec_Modified DESC
            LIMIT 1";

            $res = $mysqli->query($query);
            if ($res && $row = $res->fetch_row()) {

                $date_obj = new DateTime($row[0]);
                $date = $date_obj->format("Y-m-d");
                $this->records[$db] = array($count, $date);// save results

                continue;
            }

            // Get newest edit to definitions
            $query = "SELECT max(newest)
            FROM (
            SELECT max(dty_Modified) AS newest FROM $db.defDetailTypes
            UNION ALL
            SELECT max(dtg_Modified) AS newest FROM $db.defDetailTypeGroups
            UNION ALL
            SELECT max(rst_Modified) AS newest FROM $db.defRecStructure
            UNION ALL
            SELECT max(rty_Modified) AS newest FROM $db.defRecTypes
            UNION ALL
            SELECT max(rtg_Modified) AS newest FROM $db.defRecTypeGroups
            UNION ALL
            SELECT max(trm_Modified) AS newest FROM $db.defTerms
            UNION ALL
            SELECT max(vcg_Modified) AS newest FROM $db.defVocabularyGroups
            ) as maximum";

            $res = $mysqli->query($query);

            if (!$res) {
                $this->set_error('Query Error: Unable to retrieve a last modified record from '
                    .htmlspecialchars($db).' database<br>Error => ' .htmlspecialchars($mysqli->error));
                return -2;
            }

            if ($row = $res->fetch_row()) {
                $date_obj = new DateTime($row[0]);
                $date = $date_obj->format("Y-m-d");
            }

            $this->records[$db] = array($count, $date);// save results
        }//foreach ($dbs as $db)

        return 0;
    }

    /*
    * Prepare email body for sending
    *
    * Param: None
    *
    * Return: VOID || Error Code
    */

    public function constructEmails() {

        global $system, $mailRelayPwd; //se in heuristConfigIni

        $email_rtn = 0;
        $user_cnt = 0;

        $this->emails_sent_count = 0;

        if (empty($this->email_body)) {
            $this->set_error('The email body is missing, this needs to be provided at class initialisation.');
            return -1;
        }

        // Initialise PHPMailer
        $mailer = new PHPMailer(true);// send true to use exceptions
        $mailer->CharSet = "UTF-8";
        $mailer->Encoding = "base64";
        $mailer->isHTML(true);

        $email_from = 'no-reply@'.(defined('HEURIST_MAIL_DOMAIN')?HEURIST_MAIL_DOMAIN:HEURIST_DOMAIN);
        $email_from_name = 'Heurist system. ('.HEURIST_SERVER_NAME.')';

        $mailer->CharSet = 'UTF-8';
        $mailer->Encoding = 'base64';
        $mailer->isHTML( true );
        $mailer->ClearReplyTos();
        $mailer->addReplyTo($this->cur_user['ugr_eMail'], $this->cur_user["ugr_FullName"]);
        $mailer->SetFrom($email_from, $email_from_name);

        foreach ($this->user_details as $email => $details) {

            $email_rtn = $this->processEmailForUser($email, $details, $mailer, $mailRelayPwd);

            if ($email_rtn != 0) {
                //ERROR
                $this->save_receipt($email_rtn, $this->email_subject, $this->email_body, $user_cnt);
                return $email_rtn;
            }

            $user_cnt++;
        } //for users

        //SUCCESS
        $this->save_receipt($email_rtn, $this->email_subject, $this->email_body, $user_cnt);

        return $email_rtn;
    }

    private function processEmailForUser($email, $details, $mailer, $mailRelayPwd) {
        $email_rtn = 0;

        list($db_listed, $db_url_listed, $records_listed, $lastmod_listed) = $this->prepareEmailContent($details);

        $replace_with = [$details['first_name'], $details['last_name'], $email, $db_listed, $db_url_listed, $records_listed, $lastmod_listed];
        $body = str_ireplace($this->substitute_vals, $replace_with, $this->email_body);
        $title = isset($this->email_subject) ? $this->email_subject : "Heurist email about databases: " . $db_url_listed;

        if ($this->debug_run) {
            $status_msg = 'OK';
        } elseif ($this->use_native_mail_function) {
            $email_rtn = $this->sendNativeMail($email, $title, $body);
        } elseif (isset($mailRelayPwd) && $mailRelayPwd != '' && endsWith($email, '@gmail.com')) {
            $email_rtn = $this->sendViaRelay($email, $title, $body, $mailRelayPwd);
        } else {
            $email_rtn = $this->sendUsingPHPMailer($email, $title, $body, $mailer);
        }

        $this->logEmailStatus($email_rtn, $details, $email, $db_listed, $records_listed, $lastmod_listed, $body);
        $mailer->clearAddresses();

        return $email_rtn;
    }

    private function prepareEmailContent($details) {
        $db_url_arr = [];
        $records_arr = [];
        $lastmod_arr = [];

        foreach ($details['db_list'] as $db) {
            $url = HEURIST_BASE_URL . "?db=$db";
            $db_url_arr[] = "<a href='$url' target='_blank'>$url</a>";

            $row = $this->records[HEURIST_DB_PREFIX . $db];
            $records_arr[] = $row[0];
            $lastmod_arr[] = $row[1];
        }

        $db_listed = $this->createListFromArray($details['db_list']);
        $db_url_listed = $this->createListFromArray($db_url_arr);
        $records_listed = $this->createListFromArray($records_arr);
        $lastmod_listed = $this->createListFromArray($lastmod_arr);

        return [$db_listed, $db_url_listed, $records_listed, $lastmod_listed];
    }

    private function sendNativeMail($email, $title, $body) {
        $email_header = 'From: Heurist system <no-reply@' . HEURIST_DOMAIN . '>' . "\r\n" . CTYPE_HTML . "\r\n";
        $title = '=?utf-8?B?' . base64_encode($title) . '?=';
        USanitize::purifyHTML($body);

        if (!mail($email, $title, $body, $email_header)) {
            $this->set_error('Unknown error');
            return -3;
        }

        return 0;
    }

    private function sendViaRelay($email, $title, $body, $mailRelayPwd) {
        $data = [
            'pwd' => $mailRelayPwd,
            'from_name' => $this->cur_user['ugr_FullName'],
            'from' => $this->cur_user['ugr_eMail'],
            'to' => $email,
            'title' => $title,
            'text' => $body,
            'html' => 1
        ];

        $data_str = http_build_query($data);
        $ch = curl_init("https://heuristref.net/HEURIST/mailRelay.php");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_str);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response != 1) {
            $this->set_error('Unknown error. Sending via heuristref relay');
            return -3;
        }

        return 0;
    }

    private function sendUsingPHPMailer($email, $title, $body, $mailer) {
        try {
            $mailer->AddAddress($email);
            $mailer->Subject = $title;
            USanitize::purifyHTML($body);
            $mailer->Body = $body;
            $mailer->send();
        } catch (Exception $e) {
            $this->set_error($e->errorMessage());
            return -3;
        } catch (\Exception $e) {
            $this->set_error($e->getMessage());
            return -3;
        }

        return 0;
    }

    private function logEmailStatus($email_rtn, $details, $email, $db_listed, $records_listed, $lastmod_listed, $body) {
        $status_msg = $email_rtn == 0 ? "Sent, Sent Message: " . $body : "Failed, Error Message: " . $this->get_error();
        $this->log .= htmlspecialchars("Values: {databases: {" . $db_listed . "}, email: $email, name: "
            . $details['first_name'] . " " . $details["last_name"]
            . ", record_count: {" . $records_listed . "}, last_modified: {" . $lastmod_listed . "} },"
            . "Timestamp: " . date(DATE_8601) . ", Status: " . $status_msg)
        . '<br><br>';
    }


    /*
    * Export Email Detail's as a CSV File
    *
    * Param: None
    *
    * Return:
    * File => CSV File for Downloading
    * , or Error Code
    */

    public function exportDetailsToCSV(){

        // Open descriptor to output buffer
        $fd = fopen('php://output', 'wb');

        if ($fd == null) {

            $this->set_error('exportDetailsToCSV() Error: Unable to open temporary files for CSV exporting');
            return -4;
        }

        // Construct initial headers
        $filename = "Heurist_System_Email_Export.csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');
        header('Pragma: no-cache');
        header('Expires: ' . gmdate("D, d M Y H:i:s", time() - 3600));

        // Add column headers
        fputcsv($fd, array("User Email", "User Name", "Databases", "Record Counts"));

        // Add column data, row by row
        foreach ($this->user_details as $email => $details) {

            $name = $details["first_name"] . " " . $details["last_name"];

            $record_count_arr = array();

            $dbs = $details["db_list"];

            // Raw listed information to Array
            foreach ($dbs as $db) {
                $row = $this->records[HEURIST_DB_PREFIX.$db];

                $record_count_arr[] = $row[0];
            }

            // Add row
            fputcsv($fd, array($email, $name, implode(",", $dbs), implode(",", $record_count_arr)));
        }

        // Close descriptor and exit
        fclose($fd);
        exit;
    }

    /*
    * Converts php Array into English list
    *
    * Param: $array => array to convert
    *
    * Return: array() => converted array, or empty string
    */

    public function createListFromArray($array) {

        if (is_array($array) && count($array) >= 1) {
            return implode(" | ", $array);
        } else {
            return $array;
        }
    }

    /*
    * Error Message and Log functions:
    *
    * set_error() => set the value of error_msg to msg
    *
    * get_error() => return the value of error_msg
    * get_log() => return the value of log
    * get_error_log() => return array containing both the values of error_msg and log
    */

    public function set_error($msg) {
        $this->error_msg = $msg;
    }

    public function get_error() {
        return $this->error_msg;
    }
    public function get_log() {
        return $this->log;
    }
    public function get_error_log() {
        return array($this->error_msg, $this->log);
    }

    /*
    * Receipt functions:
    *
    * save_receipt() => prepare receipt value
    * Param:
    * $status => (int) 0 || < 0, whether the emails were all sent
    * $email_subject => (string) email subject used
    * $email_body => (string) email body used
    * $user_count => (int) count of users who have been emailed
    *
    * get_receipt() => get the value of receipt
    *
    * export_receipt() => save receipt value into Note record, Titled: System Email Receipt [Current Date]
    */

    private function save_receipt($status, $email_subject, $email_body, $user_count = 0) {

        $max_size = 1024 * 64; // 64 KBytes

        $db = implode(", ", $this->databases);
        $db_list = str_replace(HEURIST_DB_PREFIX, "", $db);
        $u = $this->users;

        $u_cnt = count($this->user_details);

        $r_cnt = $this->rec_count;

        $lm = $this->rec_lastmod_logic . " " . $this->rec_lastmod_period . " " . $this->rec_lastmod_unit;

        $status_msg = $status==0 ? "Success" : "Failed, Error Message: " . $this->get_error();

        $main = "Parameters: {<br>"
        . "&nbsp;&nbsp;Databases: $db_list <br>"
        . "&nbsp;&nbsp;User Type: $u <br>"
        . "&nbsp;&nbsp;Number of Users to Email: $u_cnt <br>"
        . "&nbsp;&nbsp;Number of Users Emailed: $user_count <br>"
        . "&nbsp;&nbsp;Record Limit:$r_cnt <br>"
        . "&nbsp;&nbsp;Last Modified Filter: $lm <br>"
        . "}, <br> Timestamp: " . date(DATE_8601) . ", Status: " . $status_msg
        . ", <br> Email Subject: " . $email_subject
        . ", <br> Email Body: <br>" . $email_body;
        $main_size = strlen($main);// Main part in bytes

        $user_list = "Users: {<br>";
        foreach ($this->user_details as $email => $details) {
            $user_list .= "&nbsp;&nbsp;". $details["first_name"] ." ". $details["last_name"] .": ". $email ."<br>";
        }
        $user_list .= "}";

        if(!empty($this->user_invalid_email)){
            $user_list .= "<br>Users with invalid emails: {<br>";
            foreach ($this->user_invalid_email as $info) {
                $user_list .= "&nbsp;&nbsp;". $info[0] ." ". $info[1]. '  '. $info[2] .' ('.$info[3].')'.": ". $info[4] ."<br>";
            }
            $user_list .= "}";
        }

        $user_list_size = strlen($user_list);// User List part in bytes

        $this->emails_sent_count = $user_count;

        // Check if Main and User List parts can be placed together or in different blocktext fields
        if ($main_size+$user_list_size <= $max_size) { // Save the text in chucks
            $this->receipt = $main . "<br>" . $user_list;
            return;
        }

        $this->receipt = array();

        if ($main_size < $max_size) {
            $this->receipt[] = $main;
        } else { // Save this part in chunks
            $this->composeList($main);
        }
        if ($user_list_size < $max_size) {
            $this->receipt[] = $user_list;
        } else { // Save this part in chunks
            $this->composeList($user_list);
        }

    }

    private function composeList($list){

        $main_t = mb_convert_encoding($list, "UTF-8", "auto");

        if ($main_t) {
            $max_size = 1024 * 64; // 64 KBytes
            $max_chars = $max_size / 4 - 1;    // Max Characters, allow roughly 4 bytes per character (for encoded/special chars)
            $start = 0;
            while ($start < mb_strlen($main_t)) {
                $this->receipt[] = mb_substr($main_t, $start, $max_chars);
                $start += $max_chars;
            }
        }
    }

    private function get_receipt() {
        return $this->receipt;
    }
    public function export_receipt() {

        global $system;
        $mysqli = $system->getMysqli();

        // Get IDs
        $note_rectype_id = ConceptCode::getRecTypeLocalID("2-3");
        $title_detailtype_id = ConceptCode::getDetailTypeLocalID("2-1");
        $summary_detailtype_id = ConceptCode::getDetailTypeLocalID("2-3");
        $date_detailtype_id = ConceptCode::getDetailTypeLocalID("2-9");
        $count_detailtype_id = ConceptCode::getDetailTypeLocalID("1609-3322");

        if (empty($note_rectype_id) || empty($title_detailtype_id) || empty($summary_detailtype_id) || empty($date_detailtype_id)) { // ensure all are valid

            $this->set_error("Unable to retrieve the Record Type ID for Notes, and the Detail Type IDs for Name/Title, Short Summary, and Date fields.<br>The Heurist team has been notified.");
            $system->addError(HEURIST_ERROR, "Bulk Email System Error: Unable to get the Record Type ID for Notes, and the Detail Type IDs for Name/Title, Short Summary, and Date fields.");
            return -1;
        }

        if (isEmptyStr($this->receipt)) {
            return 0;
        }

        // Save receipt to note record
        $data = recordAdd($system, array("RecTypeID"=>$note_rectype_id), true);
        if (!empty($data["data"]) && is_numeric($data["data"])) {

            $rec_id = $data["data"];

            $title = isset($this->email_subject) ? $this->email_subject :'Heurist System Email Receipt';
            $title .= '  ['.$this->emails_sent_count.']  ';
            if(!isEmptyStr($this->error_msg)){
                $title = 'ERROR. '.$title;
            }

            $details = [
                $title_detailtype_id=>$title,
                $date_detailtype_id=>"now",
                $summary_detailtype_id=>$this->get_receipt(), //content
                "rec_ID"=>$rec_id
            ];

            if(!empty($count_detailtype_id)){
                $details[$count_detailtype_id] = $this->emails_sent_count;
            }

            // Proceed with saving
            $rtn = recordSave($system, array("ID"=>$rec_id, "RecTypeID"=>$note_rectype_id, "details"=>$details));

            if ($rtn["status"] === HEURIST_OK && $rtn["data"] == $rec_id) {
                return $rtn;
            }

            $this->set_error("An error has occurred with adding the new Notes record for the receipt, Error => " . print_r($system->getError(), true));
            return -1;

        } else {

            $this->set_error("Unable to create Note record for receipt, Error => " . htmlspecialchars($data["message"]));
            $system->addError(HEURIST_ERROR, "Bulk Email System: Unable to create Note record for receipt, Error => " .$data["message"]);
            return -1;
        }

    }//export_receipt
}

/*
* Prepare and Send Emails using the supplied details
*
* Param: $data => Form Data
*
* Return: VOID || Error Message
*/

function sendSystemEmail($data) {

    $rtn_value = 0;
    $email_obj = new SystemEmailExt();

    $rtn_value = $email_obj->processFormData($data);

    if ($rtn_value == 0) {

        $rtn_value = $email_obj->constructEmails();//prepare and send emails

        if ($rtn_value <= -1) {

            echo errorDiv('An error occurred with preparing and sending the system emails.<br>'
                .$email_obj->get_log()); //remarked  due securiry reasons $email_obj->get_error().
            $rtn_value = -1;
        }

        // create note record with that will contain the contents of log
        $rtn_value = $email_obj->export_receipt();

        return $rtn_value;
    } else {
        echo errorDiv('An error occurred with processing the form\'s data.'); //remarked due securiry reasons '<br>'.$email_obj->get_error());
        return -1;
    }
}

/*
* Export Selected data as CSV
*
* Param: $data => Form Data
*
* Return: VOID || Error Message
*/

function getCSVDownload($data) {

    $rtn_value = 0;
    $csv_obj = new SystemEmailExt();

    $rtn_value = $csv_obj->processFormData($data);

    if ($rtn_value == 0) {

        $rtn_value = $csv_obj->exportDetailsToCSV();

        if ($rtn_value <= -1) {

            echo "An error occurred with exporting the selected data as a CSV file<br>";
            $output = $csv_obj->get_error();
            print $output[0];
            return -1;
        }

    } else {

        echo "An error occurred with processing the form's data<br>";
        $output = $csv_obj->get_error();
        print htmlspecialchars($output);
        return -1;
    }
}
