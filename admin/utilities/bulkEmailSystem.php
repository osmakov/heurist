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

/**
 * Send multiple emails to users across several databases on this server, used primarily for system announcements
 *
 * Integer returns are as follows:
 *  1 => Function Specific
 *  0 => No Error
 * -1 => General/Script Error
 * -2 => SQL Error
 * -3 => phpMailer Error
 * -4 => File Error
 *
 * @property array $databases list of databases
 * @property array $invalid_dbs list of invalid databases, missing required tables
 * @property string $users what type of users to email {owner,manager,user,admin,all}
 * @property string $email_subject Email's title/subject
 * @property string $email_body Email's body
 * @property int $rec_count Number of records for filtering databases
 * @property int $rec_lastmod_period Time period since last modification for filtering databases
 * @property int $rec_lastmod_unit Time period unit for filtering databases {DAY,MONTH,YEAR,ALL}
 * @property int $rec_lastmod_logic Time period logic (more than or less than) since last modification for filtering databases {less,more,<=,>=}
 *
 * @method int processFormData
 * @method int constructEmails
 * @method int exportDetailsToCSV
 * @method int createListFromArray
 * @method string getError
 * @method string getLog
 * @method array<string, array> getErrorLog
 * @method array|int exportReceipt
 */
class BulkEmailSystem {

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
    public $rec_lastmod_logic; // logic, default: <= [more than]
    //public $filterIncompleteDesc; //New filter for databases w/ incomplete descriptions.

    private $records; // array of records+last modified information

    private $use_native_mail_function = false;
    private $debug_run = false;

    private $log = '';// log of emails, to be placed within a note record within the databases, extended version of receipt
    private $receipt; // receipt for all email transactions, is saved into current db as a note record with the Notes title (not rec_Title) set to "Heurist System Email Receipt"
    private $emails_sent_count = 0;
    private $error_msg = '';// error message

    private $user_options = ["owner", "admin", "manager", "user"];// available user options
    private $substitute_vals = ["##firstname##", "##lastname##", "##email##", "##database##", "##dburl##", "##records##", "##lastmodified##"];// available email body substitutions

    private $add_gdpr = true;// add GDPR statement to end

    /**
     * Processes form data for bulk emailing databases owners.
     *
     * @param array $data Form input data.
     * @return int Returns 0 on success, or an error code on failure.
     */
    public function processFormData($data) {
        global $system;

        $rtn = 0; // Default return value indicating success.

        // Reset databases property to null for a fresh start.
        $this->databases = [];

        $this->add_gdpr = !empty($data["add_gdpr"]);

        // Validate database input from form data; return error code -1 if invalid.
        if (!$this->validateDatabaseInput($data)) {
            return -1;
        }

        // Validate user-related input; return error code -1 if invalid.
        if (!$this->validateUserInput($data)) {
            return -1;
        }

        // Set record filtering options, applying defaults if not provided.
        $this->rec_count = (isset($data["recTotal"]) && is_numeric($data["recTotal"]) && $data["recTotal"] >= 0)
            ? $data["recTotal"]
            : "none";

        $this->rec_lastmod_period = (isset($data["recModVal"]) && is_numeric($data["recModVal"]) && $data["recModVal"] > 0)
            ? $data["recModVal"]
            : 6;

        $this->rec_lastmod_unit = $data["recModInt"] ?? "MONTH";

        $this->rec_lastmod_logic = $data["recModLogic"] ?? "<=";
        $this->rec_lastmod_logic = $this->rec_lastmod_logic == 'less'
            ? '>='
            : '<=';

        // Initialize arrays and variables for user details and error handling.
        $this->user_details = [];
        $this->user_invalid_email = [];
        $this->log = "";
        $this->receipt = null;
        $this->error_msg = "";

        // Set email processing options based on form input.
        $this->use_native_mail_function = !empty($data["use_native"]);

        // Create a list of users; return error code if it fails.
        $rtn = $this->createUserList();
        if ($rtn != 0) {
            return $rtn;
        }

        // Check if any users were retrieved; set an error and return -1 if none.
        if (isEmptyArray($this->user_details)) {
            $this->setError('No users have been retrieved, no emails have been sent');
            return -1;
        }

        // Create a list of records for each database; return error code if it fails.
        $rtn = $this->createRecordsList();
        if ($rtn != 0) {
            return $rtn;
        }

        // Retrieve the current user's details and email address.
        $this->cur_user = $system->getCurrentUser();
        $this->getUserEmail();

        return 0; // Return success.
    }

    /**
     * Validates database input from the provided form data.
     *
     * @param array $data Form input data.
     * @return bool Returns true if the database input is valid, false otherwise.
     */
    private function validateDatabaseInput($data) {
        // Ensure the current database is provided; set an error if missing.
        if (empty($data["db"])) {
            $this->setError('No current database has been provided.<br>Please contact the Heurist team if this problem persists.');
            return false;
        }

        // Process the 'databases' input if provided.
        if (!empty($data["databases"])) {
            // Convert a comma-separated string into an array if necessary.
            if (!is_array($data["databases"])) {
                $data["databases"] = explode(',', $data["databases"]);
            }

            // Validate the provided databases if the array is not empty.
            if (is_array($data["databases"]) && count($data["databases"]) >= 1) {
                $this->databases = $this->validateDatabases($data["databases"]);
            }
        }

        // Check if valid databases exist after validation; set an error if none.
        if (isEmptyArray($this->databases)) {
            $provided_dbs = is_array($data["databases"]) ? "" : "<br>databases => " . htmlspecialchars($data["databases"]);
            $this->setError("No valid databases have been provided.<br>{$provided_dbs}");
            return false;
        }

        // Return true if all validations pass.
        return true;
    }

    /**
     * Validates user-related input from the provided form data.
     *
     * @param array $data Form input data.
     * @return bool Returns true if user input is valid, false otherwise.
     */
    private function validateUserInput($data) {
        // Validate the 'users' field and ensure it matches one of the allowed options.
        if (!empty($data["users"]) && in_array($data["users"], $this->user_options)) {
            $this->users = $data["users"]; // Assign valid users.
        } else {
            // Generate an error message if 'users' is invalid or missing.
            $main_msg = 'No valid users have been provided.<br>users => '
                . (!empty($data["users"])
                    ? htmlspecialchars(print_r($data["users"], true))
                    : ' not defined');
            $this->setError($main_msg);
            return false;
        }

        // Validate the email subject, ensuring it's a string or defaulting to null.
        $this->email_subject = isset($data["emailTitle"]) && is_string($data["emailTitle"])
            ? $data["emailTitle"]
            : null;

        // Ensure the email body is provided and is a string.
        if (!isset($data["emailBody"]) || !is_string($data["emailBody"])) {
            $this->setError('No email body has been provided');
            return false;
        }

        $this->email_body = $data["emailBody"]; // Assign the valid email body.

        // Add a GDPR statement to the email body if required.
        $this->addGDPRStatement();

        return true; // All validations passed.
    }

    /**
     * Appends a GDPR disclaimer to the email body.
     * The disclaimer content is loaded from a predefined HTML file.
     *
     * @return void
     */
    private function addGDPRStatement() {
        // Exit early if GDPR addition is not required.
        if (!$this->add_gdpr) {
            return;
        }

        // Define the primary and fallback file paths for the GDPR disclaimer.
        $primaryGDPRFile = __DIR__ . '/../../../GDPR.html';
        $fallbackGDPRFile = __DIR__ . '/../../movetoparent/GDPR.html';

        // Determine the appropriate GDPR file to use.
        $GDPRFile = file_exists($primaryGDPRFile) && is_readable($primaryGDPRFile) && filesize($primaryGDPRFile) > 0
            ? $primaryGDPRFile
            : (file_exists($fallbackGDPRFile) && is_readable($fallbackGDPRFile) && filesize($fallbackGDPRFile) > 0
                ? $fallbackGDPRFile
                : null);

        // Exit if no valid GDPR file is found.
        if (!$GDPRFile) {
            return;
        }

        // Load the GDPR HTML content, removing unnecessary tags (e.g., <title>, <meta>).
        $DOM = new \DOMDocument();
        $DOM->loadHTMLFile($GDPRFile, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);

        $body = $DOM->getElementsByTagName('body');
        if ( $body && 0<$body->length ) {
            $body = $body->item(0);
            $mock = new DOMDocument;
            foreach ($body->childNodes as $child){
                $mock->appendChild($mock->importNode($child, true));
            }
            $GDPRContent = $mock->saveHTML();
        }else{
            foreach (['title', 'meta'] as $tagName) {
                $nodes = $DOM->getElementsByTagName($tagName);
                while ($nodes->length > 0) {
                    $nodes->item(0)->parentNode->removeChild($nodes->item(0));
                }
            }
            $GDPRContent = $DOM->saveHTML();
        }

        // Append the cleaned GDPR content to the email body.
        if (!empty($GDPRContent)) {
            $this->email_body .= "<br><br>{$GDPRContent}";
        }
    }

    /**
     * Retrieves the current user's email address.
     *
     * If the email is invalid or not found, defaults to the system admin's email.
     *
     * @return void
     */
    private function getUserEmail() {
        global $system;

        // Get the mysqli connection parameters.
        $mysqli = $system->getMysqli();

        // Prepare the query to fetch the user's email by their ID.
        $query = "SELECT ugr.ugr_eMail
                  FROM " . HEURIST_DBNAME_FULL . ".sysUGrps AS ugr
                  WHERE ugr.ugr_ID = ?";

        // Set email default value to false.
        $email = false;

        // Use a prepared statement to prevent SQL injection.
        if ($stmt = $mysqli->prepare($query)) {

            $emailResult = '';

            $stmt->bind_param('i', $this->cur_user['ugr_ID']); // Bind the user ID as an integer.
            $stmt->execute();
            $stmt->bind_result($emailResult);
            $stmt->fetch();
            $stmt->close();

            // Validate the fetched email address.
            $email = filter_var($emailResult, FILTER_VALIDATE_EMAIL);
        }

        // Set the user's email to the default admin email if invalid or not found.
        $this->cur_user['ugr_eMail'] = $email ?: HEURIST_MAIL_TO_ADMIN;
    }

    /**
     * Validate the list of database, ignore any invalid databases
     *  Checks for tables: Records, recDetails, sysUGrps and sysUsrGrpLinks
     *
     * @param array<string> $db_list list of selected databases
     * @return array<string> list of validated databases
     */
    private function validateDatabases($db_list) {

        global $system;
        $mysqli = $system->getMysqli();

        $valid_dbs = [];

        foreach($db_list as $db){

            // Required tables  are 'Records', 'recDetails', 'sysUGrps', and 'sysUsrGrpLinks'
            $query = "SHOW TABLES IN {$db} WHERE Tables_in_{$db} = 'Records' OR Tables_in_{$db} = 'recDetails' OR Tables_in_{$db} = 'sysUGrps' OR Tables_in_{$db} = 'sysUsrGrpLinks'";

            $table_listing = $mysqli->query($query);
            if (!$table_listing || mysqli_num_rows($table_listing) != 4) { // Skip, missing required tables
                continue;
            }

            $valid_dbs[] = $db;
        }

        return $valid_dbs;
    }

    /**
     * Create list of user details and associated databases, sorted by user email
     *
     * @return int response code: 0 = success, -1 = Failed to get where clause
     */
    private function createUserList() {

        global $system;
        $mysqli = $system->getMysqli();

        $dbs = $this->databases;

        foreach ($dbs as $db){

            $where_clause = $this->generateWhereClause($this->users, $db);

            if (empty($where_clause)) {
                $this->setError('Unable to construct WHERE clause for User List query due to an invalid users option<br>users => '
                    . htmlspecialchars($this->users));
                return -1;
            }

            $query = "SELECT DISTINCT ugr.ugr_FirstName, ugr.ugr_LastName, ugr.ugr_eMail, ugr.ugr_ID
            FROM {$db}.sysUsrGrpLinks AS ugl
            INNER JOIN {$db}.sysUGrps AS ugr ON ugl.ugl_UserID = ugr.ugr_ID "
            . $where_clause;

            $res = $mysqli->query($query);
            if (!$res) {

                continue;
            }

            $this->processUserResults($res, $db);

            $res->close();

        }

        ksort($this->user_details, SORT_FLAG_CASE);

        return 0;
    }

    /**
     * Create WHERE clause for user search
     *
     * @param string $users type of users to be searched for {owner, manager, admin, user, all}
     * @param string $db database, with prefix, searching in, for admin search
     * @return string SLQ where clause
     */
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

    /**
     * Process list of users for database
     *
     * @param mixed $res SQL results of all relevant users on database
     * @param string $db database name with prefix
     * @return void
     */
    private function processUserResults($res, $db) {
        while ($row = $res->fetch_row()) {
            $db_name = substr($db, strlen(HEURIST_DB_PREFIX));

            $email = filter_var($row[2], FILTER_VALIDATE_EMAIL);


            if (!$email) {
                $this->user_invalid_email[] = [$db, $row[0], $row[1], $row[3], $row[2]];
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

    /**
     * Retrieve the record count and newest last modified date
     *
     * @return int response code: 0 = success, anything else means error
     */
    private function createRecordsList() {

        global $system;
        $mysqli = $system->getMysqli();

        $dbs = $this->databases;

        $lastmod_period = $this->rec_lastmod_period;
        $lastmod_unit = $this->rec_lastmod_unit;
        $lastmod_logic = $this->rec_lastmod_logic;

        // Create Last modified's WHERE Clause
        $lastmod_where = ($lastmod_unit!="ALL") ? "AND rec_Modified {$lastmod_logic} date_format(curdate(), '%Y-%m-%d') - INTERVAL {$lastmod_period} {$lastmod_unit} " : "";

        foreach ($dbs as $db) {

            $count = 0;
            $date = "unknown";

            // Get record count
            $query = "SELECT COUNT(*)
            FROM (
            SELECT *
            FROM {$db}.Records AS rec
            WHERE rec_Title IS NOT NULL
            AND rec_Title NOT LIKE 'Heurist System Email Receipt%'
            AND rec_FlagTemporary != 1
            AND rec_Title != '' {$lastmod_where}
            ) AS a";

            $res = $mysqli->query($query);
            if (!$res) {

                $this->setError('Query Error: Unable to get record count for the '
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
            AND rec_Title != '' {$lastmod_where}
            AND rec_Title NOT LIKE 'Heurist System Email Receipt%'
            ORDER BY rec_Modified DESC
            LIMIT 1";

            $res = $mysqli->query($query);
            if ($res && $row = $res->fetch_row()) {

                $date_obj = new DateTime($row[0]);
                $date = $date_obj->format("Y-m-d");
                $this->records[$db] = [$count, $date];// save results

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
                $this->setError('Query Error: Unable to retrieve a last modified record from '
                    .htmlspecialchars($db).' database<br>Error => ' .htmlspecialchars($mysqli->error));
                return -2;
            }

            if ($row = $res->fetch_row()) {
                $date_obj = new DateTime($row[0]);
                $date = $date_obj->format("Y-m-d");
            }

            $this->records[$db] = [$count, $date];// save results
        }//foreach ($dbs as $db)

        return 0;
    }

    /**
     * Prepare email body for sending
     *
     * @return int response code: 0 = success, anything else means error
     */
    public function constructEmails() {

        global $mailRelayPwd; //se in heuristConfigIni

        $email_rtn = 0;
        $user_cnt = 0;

        $this->emails_sent_count = 0;

        if (empty($this->email_body)) {
            $this->setError('The email body is missing, this needs to be provided at class initialisation.');
            return -1;
        }

        // Initialise PHPMailer
        $mailer = new PHPMailer(true);// send true to use exceptions
        $mailer->CharSet = "UTF-8";
        $mailer->Encoding = "base64";
        $mailer->isHTML(true);

        $email_from = 'no-reply@'.(defined('HEURIST_MAIL_DOMAIN')?HEURIST_MAIL_DOMAIN:HEURIST_DOMAIN);
        $email_from_name = 'Heurist system ('.HEURIST_SERVER_NAME.')';

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
                $this->saveReceipt($email_rtn, $this->email_subject, $this->email_body, $user_cnt);
                return $email_rtn;
            }

            $user_cnt++;
        } //for users

        //SUCCESS
        $this->saveReceipt($email_rtn, $this->email_subject, $this->email_body, $user_cnt);

        return $email_rtn;
    }

    /**
     * Process email details for current user
     *
     * @param string $email user's email
     * @param array<string, string> $details user details
     * @param PHPMailer $mailer PHPMailer instance
     * @param mixed $mailRelayPwd relay password
     * @return int response code, -3 = email error, 0 = success
     */
    private function processEmailForUser($email, $details, $mailer, $mailRelayPwd) {
        $email_rtn = 0;

        [$db_listed, $db_url_listed, $records_listed, $lastmod_listed] = $this->prepareEmailContent($details);

        $replace_with = [$details['first_name'], $details['last_name'], $email, $db_listed, $db_url_listed, $records_listed, $lastmod_listed];
        $body = str_ireplace($this->substitute_vals, $replace_with, $this->email_body);
        $title = $this->email_subject ?? "Heurist email about databases: {$db_listed}";

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

    /**
     * Prepare email values for replacing into body text
     *
     * @param array $details array of databases to process
     * @return array<string, string, string, string> [database, database links, database rec counts, database last mod dates]
     */
    private function prepareEmailContent($details) {
        $db_url_arr = [];
        $records_arr = [];
        $lastmod_arr = [];

        foreach ($details['db_list'] as $db) {
            $url = HEURIST_BASE_URL . "?db=$db";
            $db_url_arr[] = "<a href='$url' target='_blank'>$db</a>";

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

    /**
     * Send email using native PHP
     *
     * @param string $email Email recipient
     * @param string $title Email title/subject
     * @param string $body Email body text
     * @return int response code, -3 = email error, 0 = success
     */
    private function sendNativeMail($email, $title, $body) {
        $email_header = 'From: Heurist system <no-reply@' . HEURIST_DOMAIN . '>' . "\r\n" . CTYPE_HTML . "\r\n";
        $title = '=?utf-8?B?' . base64_encode($title) . '?=';
        USanitize::purifyHTML($body);

        if (!mail($email, $title, $body, $email_header)) {
            $this->setError('Unknown error');
            return -3;
        }

        return 0;
    }

    /**
     * Send email to email relay (for Gmail to avoid blockage)
     *
     * @param string $email Email recipient
     * @param string $title Email title/subject
     * @param string $body Email body text
     * @param mixed $mailRelayPwd relay password
     * @return int response code, -3 = email error, 0 = success
     */
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
            $this->setError('Unknown error: Sending via heuristref relay');
            return -3;
        }

        return 0;
    }

    /**
     * Send email using PHPMailer
     *
     * @param string $email Email recipient
     * @param string $title Email title/subject
     * @param string $body Email body text
     * @param PHPMailer $mailer PHPMailer instance
     * @return int response code, -3 = email error, 0 = success
     */
    private function sendUsingPHPMailer($email, $title, $body, $mailer) {
        try {
            $mailer->AddAddress($email);
            $mailer->Subject = $title;
            USanitize::purifyHTML($body);
            $mailer->Body = $body;
            $mailer->send();
        } catch (Exception $e) {
            $this->setError($e->errorMessage());
            return -3;
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return -3;
        }

        return 0;
    }

    /**
     * Update email log
     *
     * @param int $email_rtn new status
     * @param array<string, string> $details user details
     * @param string $email user's email
     * @param string $db_listed list of databases
     * @param int $records_listed record count
     * @param string $lastmod_listed last record/structure modification
     * @param string $body email body, with replaced values
     * @return void
     */
    private function logEmailStatus($email_rtn, $details, $email, $db_listed, $records_listed, $lastmod_listed, $body) {
        $status_msg = $email_rtn == 0 ? "Sent, Sent Message: {$body}" : "Failed, Error Message: " . $this->getError();
        $this->log .= htmlspecialchars("Values: {databases: {{$db_listed}}, email: {$email}, name: {$details['first_name']} {$details['last_name']}"
            . ", record_count: {{$records_listed}}, last_modified: {{$lastmod_listed}} },"
            . "Timestamp: " . date(DATE_8601) . ", Status: {$status_msg}")
        . '<br><br>';
    }

    /**
     * Export Email Detail's as a CSV File
     *
     * @return int Returns error code, otherwise execution ends with printing out CSV details
     */
    public function exportDetailsToCSV(){

        // Open descriptor to output buffer
        $fd = fopen('php://output', 'wb');

        if ($fd == null) {

            $this->setError('File Error: Unable to open temporary files for CSV exporting');
            return -4;
        }

        // Construct initial headers
        $filename = "Heurist_System_Email_Export.csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '";');
        header('Pragma: no-cache');
        header('Expires: ' . gmdate("D, d M Y H:i:s", time() - 3600));

        // Add column headers
        fputcsv($fd, ["User Email", "User Name", "Databases", "Record Counts"]);

        // Add column data, row by row
        foreach ($this->user_details as $email => $details) {

            $name = "{$details['first_name']} {$details['last_name']}";

            $record_count_arr = [];

            $dbs = $details["db_list"];

            // Raw listed information to Array
            foreach ($dbs as $db) {
                $row = $this->records[HEURIST_DB_PREFIX.$db];

                $record_count_arr[] = $row[0];
            }

            // Add row
            fputcsv($fd, [$email, $name, implode(",", $dbs), implode(",", $record_count_arr)]);
        }

        // Close descriptor and exit
        fclose($fd);
        exit;
    }

    /**
     * Converts php array into string list
     *
     * @param array $array array of listable values
     * @return string list of values
     */
    public function createListFromArray($array) {

        if (is_array($array) && count($array) >= 1) {
            return implode(" | ", $array);
        } else {
            return $array;
        }
    }

    // Error and Logging Functions

    /**
     * Set the value of error_msg to msg
     *
     * @param string $msg new error message
     * @return void
     */
    private function setError($msg) {
        $this->error_msg = $msg;
    }

    /**
     * Get the value of error_msg
     *
     * @return string current error message
     */
    public function getError() {
        return $this->error_msg;
    }

    /**
     * Get the value of log of email statuses
     *
     * @return string current email log
     */
    public function getLog() {
        return $this->log;
    }

    /**
     * Get both the values of error_msg and log
     *
     * @return array<string, array> [current error message, email log]
     */
    public function getErrorLog() {
        return [$this->error_msg, $this->log];
    }

    // Receipt Functions

    /**
     * Prepare receipt value
     *
     * @param int $status 0 || < 0, whether the emails were all sent
     * @param string $email_subject email subject used
     * @param string $email_body email body used
     * @param int $user_count count of users who have been emailed
     * @return void
     */
    private function saveReceipt($status, $email_subject, $email_body, $user_count = 0) {

        $max_size = 1024 * 64; // 64 KBytes

        $db = implode(", ", $this->databases);
        $db_list = str_replace(HEURIST_DB_PREFIX, "", $db);
        $u = $this->users;

        $u_cnt = count($this->user_details);

        $r_cnt = $this->rec_count;

        $lm = "{$this->rec_lastmod_logic} {$this->rec_lastmod_period} {$this->rec_lastmod_unit}";

        $status_msg = $status==0 ? "Success" : "Failed, Error Message: " . $this->getError();

        $main = "Parameters: {<br>"
        . "&nbsp;&nbsp;Databases: $db_list <br>"
        . "&nbsp;&nbsp;User Type: $u <br>"
        . "&nbsp;&nbsp;Number of Users to Email: $u_cnt <br>"
        . "&nbsp;&nbsp;Number of Users Emailed: $user_count <br>"
        . "&nbsp;&nbsp;Record Limit:$r_cnt <br>"
        . "&nbsp;&nbsp;Last Modified Filter: $lm <br>"
        . "}, <br> Timestamp: " . date(DATE_8601) . ", Status: {$status_msg}"
        . ", <br> Email Subject: {$email_subject}"
        . ", <br> Email Body: <br>{$email_body}";
        $main_size = strlen($main);// Main part in bytes

        $user_list = "Users: {<br>";
        foreach ($this->user_details as $email => $details) {
            $user_list .= "&nbsp;&nbsp;{$details['first_name']} {$details['last_name']}: {$email}<br>";
        }
        $user_list .= "}";

        if(!empty($this->user_invalid_email)){
            $user_list .= "<br>Users with invalid emails: {<br>";
            foreach ($this->user_invalid_email as $info) {
                $user_list .= "&nbsp;&nbsp;{$info[0]} {$info[1]} {$info[2]} ({$info[3]}): {$info[4]}<br>";
            }
            $user_list .= "}";
        }

        $user_list_size = strlen($user_list);// User List part in bytes

        $this->emails_sent_count = $user_count;

        // Check if Main and User List parts can be placed together or in different blocktext fields
        if ($main_size+$user_list_size <= $max_size) { // Save the text in chucks
            $this->receipt = "{$main}<br>{$user_list}";
            return;
        }

        $this->receipt = [];

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

    /**
     * Add listed items into receipt in chunks, to not overload the underlying blocktext field
     *
     * @param string $list value to be chunked into pieces
     * @return void
     */
    private function composeList($list){

        $main_t = mb_convert_encoding($list, "UTF-8", "auto");

        if ($main_t) {
            $max_size = 1024 * 64; // 64 KBytes
            $max_chars = $max_size / 4 - 1; // Max Characters, allow roughly 4 bytes per character (for encoded/special chars)
            $start = 0;
            while ($start < mb_strlen($main_t)) {
                $this->receipt[] = mb_substr($main_t, $start, $max_chars);
                $start += $max_chars;
            }
        }
    }

    /**
     * Get the value of receipt
     *
     * @return array|string current receipt
     */
    private function getReceipt() {
        return $this->receipt;
    }

    /**
     * Finish up receipt and save to database as a note record
     *
     * @return array|int Returns the results from recordSave, or an error code
     */
    public function exportReceipt() {

        global $system;

        // Get IDs
        $note_rectype_id = ConceptCode::getRecTypeLocalID("2-3");
        $title_detailtype_id = ConceptCode::getDetailTypeLocalID("2-1");
        $summary_detailtype_id = ConceptCode::getDetailTypeLocalID("2-3");
        $date_detailtype_id = ConceptCode::getDetailTypeLocalID("2-9");
        $count_detailtype_id = ConceptCode::getDetailTypeLocalID("1609-3322");

        if (empty($note_rectype_id) || empty($title_detailtype_id) || empty($summary_detailtype_id) || empty($date_detailtype_id)) { // ensure all are valid

            $this->setError("Unable to retrieve the Record Type ID for Notes, and the Detail Type IDs for Name/Title, Short Summary, and Date fields.<br>The Heurist team has been notified.");
            $system->addError(HEURIST_ERROR, "Bulk Email System Error: Unable to get the Record Type ID for Notes, and the Detail Type IDs for Name/Title, Short Summary, and Date fields.");
            return -1;
        }

        if (isEmptyStr($this->receipt)) {
            return 0;
        }

        // Save receipt to note record
        $data = recordAdd($system, ["RecTypeID"=>$note_rectype_id], true);
        if (!empty($data["data"]) && is_numeric($data["data"])) {

            $rec_id = $data["data"];

            $title = $this->email_subject ?? 'Heurist System Email Receipt';
            $title .= "  [{$this->emails_sent_count}]  ";
            if(!isEmptyStr($this->error_msg)){
                $title = "Error: {$title}";
            }

            $details = [
                $title_detailtype_id=>$title,
                $date_detailtype_id=>"now",
                $summary_detailtype_id=>$this->getReceipt(), //content
                "rec_ID"=>$rec_id
            ];

            if(!empty($count_detailtype_id)){
                $details[$count_detailtype_id] = $this->emails_sent_count;
            }

            // Proceed with saving
            $rtn = recordSave($system, ["ID"=>$rec_id, "RecTypeID"=>$note_rectype_id, "details"=>$details]);

            if ($rtn["status"] === HEURIST_OK && $rtn["data"] == $rec_id) {
                return $rtn;
            }

            $this->setError("An error has occurred with adding the new Notes record for the receipt, Error => " . print_r($system->getError(), true));
            return -1;

        } else {

            $this->setError("Unable to create Note record for receipt, Error => " . htmlspecialchars($data["message"]));
            $system->addError(HEURIST_ERROR, "Bulk Email System: Unable to create Note record for receipt, Error => {$data["message"]}");
            return -1;
        }

    }//exportReceipt
}

/**
 * Prepare and Send Emails using the supplied details
 *
 * @param array $data Form input data
 * @return array|int Returns the results from exportReceipt, or an error code
 */
function sendSystemEmail($data) {

    $email_obj = new BulkEmailSystem();

    if ($email_obj->processFormData($data) == 0) {

        //prepare and send emails
        if ($email_obj->constructEmails() <= -1) {

            echo errorDiv('An error occurred with preparing and sending the system emails.<br>'
                .$email_obj->getLog()); //remarked  due securiry reasons $email_obj->getError().

        }else{
            // create note record with that will contain the contents of log
            return $email_obj->exportReceipt();
        }

    } else {
        echo errorDiv('An error occurred with processing the form\'s data.'); //remarked due securiry reasons '<br>'.$email_obj->getError());
    }

    return -1;
}

/**
 * Export Selected data as CSV
 *
 * @param mixed $data Form input data
 * @return int Returns an error code, otherwise the script exits while printing the CSV details
 */
function getCSVDownload($data) {

    $csv_obj = new BulkEmailSystem();

    if ($csv_obj->processFormData($data) == 0) {

        if ($csv_obj->exportDetailsToCSV() <= -1) {

            echo "An error occurred with exporting the selected data as a CSV file<br>";
            $output = $csv_obj->getError();
            print $output[0];
        }

    } else {

        echo "An error occurred with processing the form's data<br>";
        $output = $csv_obj->getError();
        print htmlspecialchars($output);
    }

    return -1;
}
