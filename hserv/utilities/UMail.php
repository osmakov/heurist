<?php

/**
* UMail.php - email sending routines
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

require_once dirname(__FILE__).'/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

//
// Uses PHPMailer
//
function sendEmail($email_to, $email_title, $email_text, $is_html=false, $email_attachment=null)
{
    return sendPHPMailer(null, null, $email_to, $email_title, $email_text, $email_attachment, $is_html);
}

// in php v8 use str_ends_with
function endsWith($haystack, $needle) {
    // search forward starting from end minus needle length characters
    return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
}

//
//
//
function sendPHPMailer($email_from, $email_from_name, $email_to, $email_title, $email_text, $email_attachment, $is_html){

    global $system, $mailRelayPwd;

    $replyTo = null;//$email_from;
    $replyToName = null;//$email_from_name;

    if(!$email_from) {$email_from = 'no-reply@'.(defined('HEURIST_MAIL_DOMAIN')?HEURIST_MAIL_DOMAIN:HEURIST_DOMAIN);}
    if(!$email_from_name) {$email_from_name = 'Heurist system. ('.HEURIST_SERVER_NAME.')';}

    if($is_html){
        USanitize::purifyHTML($email_text);
    }

    if(is_array($email_text)){
        $email_text =  json_encode($email_text);
    }

    if(!$email_to){
        if(isset($system)){
            $system->addError(HEURIST_ACTION_BLOCKED,
                'Cannot send email. Recipient email address is not defined');
        }
        return false;
    }elseif(!is_array($email_to)){
        $email_to = array($email_to);
    }

    //$is_html = (strpos("\n",$email_text)===false);

    // strip all whitespaces
    $email_from = filter_var($email_from, FILTER_SANITIZE_EMAIL);

    if(isset($mailRelayPwd) && $mailRelayPwd!=''
    && count($email_to)==1 && endsWith($email_to[0], '@gmail.com')){

        $data = array('pwd' => $mailRelayPwd ,
            'from_name' => $replyToName,
            'from' => $replyTo,
            'to' => implode(',',$email_to),  //cs list of recipients
            'title' => $email_title,
            'text' => $email_text,
            'html' => 1);

        $data_str = http_build_query($data);

        $ch =  curl_init("https://heuristref.net/HEURIST/mailRelay.php");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_str);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responce = curl_exec($ch);
        curl_close($ch);

        return $responce==1;
    }


    //send an email with attachment
    $email = new PHPMailer();

    /*
    $mail->IsSMTP();
    $mail->SMTPAuth   = true;
    $mail->Port       = 25;
    $mail->Host       = "xx.xxx.x.x";// SMTP server
    $mail->Username   = "myemail@mydomain.local";
    $mail->Password   = <myemailpassword>;

    $mail->From = 'contacto@45norte.com';
    $mail->addReplyTo($_POST['inputEmail'], $_POST['inputName']);//recipient
    */

    $email->CharSet = 'UTF-8';
    $email->Encoding = 'base64';
    $email->isHTML( $is_html );

    if($replyTo!=null && $replyTo!=$email_from){
        $email->ClearReplyTos();
        $email->addReplyTo($replyTo, $replyToName);
    }
    $email->SetFrom($email_from, $email_from_name);


    $email->Subject   = $email_title; //'=?UTF-8?B?'.base64_encode($email_title).'?=';
    $email->Body      = $email_text;

    $email_cc = array_key_exists('cc', $email_to) ? $email_to['cc'] : [];
    $email_bcc = array_key_exists('bcc', $email_to) ? $email_to['bcc'] : [];
    $email_to = array_key_exists('to', $email_to) ? $email_to['to'] : $email_to;

    foreach($email_to as $email_address){

        $email_address = filter_var($email_address, FILTER_SANITIZE_EMAIL);
        if(!filter_var($email_address, FILTER_VALIDATE_EMAIL)){

            $problem = (($email_address==null) || (trim($email_address)==='')) ? "is not defined" : "$email_address is invalid";

            if(isset($system)){
                $system->addError(HEURIST_ACTION_BLOCKED,
                    "Cannot send email. Recipient email address $problem.");
            }
            return false;
        }

        $email->AddAddress( $email_address );
    }
    foreach($email_cc as $email_address){

        $email_address = filter_var($email_address, FILTER_SANITIZE_EMAIL);
        if(!filter_var($email_address, FILTER_VALIDATE_EMAIL)){
            continue;
        }

        $email->AddCC($email_address);
    }
    foreach($email_bcc as $email_address){

        $email_address = filter_var($email_address, FILTER_SANITIZE_EMAIL);
        if(!filter_var($email_address, FILTER_VALIDATE_EMAIL)){
            continue;
        }

        $email->AddBCC($email_address);
    }

    if($email_attachment!=null){
        if(is_array($email_attachment)){
            foreach($email_attachment as $attach_file){
                $email->addAttachment( $attach_file );
            }
        }else{
            $email->addAttachment($email_attachment);// , 'new.jpg');
        }
    }

    try{
        $email->send();
        return true;
    } catch (Exception $e) {
        if(isset($system)){
            $system->addError(HEURIST_SYSTEM_CONFIG,
                'Cannot send email. Please ask system administrator to verify that mailing is enabled on your server'
                , $email->ErrorInfo);
        }
        return false;
    }
    return true;
}

//
// Uses php native mail function (used in send_email.php only)
//
function sendEmail_native($email_to, $email_title, $email_text, $email_header, $is_utf8=false, $use_html=false){

    $res = "ok";

    $email_to = filter_var($email_to, FILTER_SANITIZE_EMAIL);

    if(!filter_var($email_to, FILTER_VALIDATE_EMAIL)){

        $problem = (($email_to==null) || (trim($email_to)==='')) ? "is not defined" : "$email_to is invalid";
        $res = "Mail send failed. Recipient email address $problem.";
    }elseif(!$email_text){
        $res = "Mail send failed. Message text is not defined.";
    }else {

        if(!@$email_title){
            $email_title = "";
        }
        $errorMsg = "Cannot send email "
        .($email_title?"'".$email_title."'":'')
        .". This may indicate that mail transport agent is not correctly configured on server."
        ." Please ask your system administrator to correct the installation";

        if(!checkSmtp()){
            return $errorMsg;
        }

        $email_title = "HEURIST ".$email_title;

        if(!$email_header){
            $email_header = "From: HEURIST";
            if(defined('HEURIST_DOMAIN')){
                if(defined('HEURIST_DBNAME')){
                    $email_header = $email_header." (".HEURIST_DBNAME.")";
                }
                $email_header = $email_header." <no-reply@".(defined('HEURIST_MAIL_DOMAIN')?HEURIST_MAIL_DOMAIN:HEURIST_DOMAIN).">";
            }
        }

        if($use_html){

            $email_header = $email_header."\r\nContent-Type: text/html;";
            if(!$is_utf8){
                $email_header = $email_header."\r\n";
            }else{
                $email_header = $email_header." charset=utf-8\r\n";
                $email_title = '=?utf-8?B?'.base64_encode($email_title).'?=';
            }
        }elseif($is_utf8){
            $email_header = $email_header."\r\nContent-Type: text/plain; charset=utf-8\r\n";
            $email_title = '=?utf-8?B?'.base64_encode($email_title).'?=';
        }

        $email_text = $email_text."\n\n"
        ."-------------------------------------------\n"
        ."This email was generated by Heurist (info@HeuristNetwork.org)\n";
        // This tends to confuse people who click on the link and get a list of databases
        // .(defined('HEURIST_BASE_URL') ?(":\n".HEURIST_BASE_URL) :"")."\n";


        $rv = mail($email_to, $email_title, $email_text, $email_header);
        if(!$rv){
            $res = $errorMsg;
        }
    }

    return $res;
}

//
//
//
function checkSmtp(){

    $smtpHost = '127.0.0.1';//'localhost';
    $smtpPort = '25';
    $smtpTimeout = 5;

    $res = @fsockopen($smtpHost,
        $smtpPort,
        $errno,
        $errstr,
        $smtpTimeout);

    if (!is_resource($res))
    {
        USanitize::errorLog("email_smtp_error {$errno} {$errstr}");
        return false;
    }
    return true;
}


//
// Send warning email to admin once per 4 hours
//
// $is_global - true - check global lastWarningSent in file upload root
//              false - check lastWarningSent in database folder
//
function sendEmailToAdmin($title, $message, $is_global){
    global $system;

    if(isset($system)){
        $folder = $system->getFileStoreRootFolder();
        if(!$is_global){
            $folder .= $system->dbname().'/';
        }
    }else{
        $folder = dirname(__FILE__).'/../../../'; //$defaultRootFileUploadPath;
    }

    $fname = $folder."lastWarningSent.ini";

    $needSend = true;
    if (file_exists($fname)){//check if warning is already sent
        $datetime1 = date_create(file_get_contents($fname));
        $datetime2 = date_create('now');
        $interval = date_diff($datetime1, $datetime2);
        $needSend = ($interval->format('%h')>4);//in hours
    }
    if($needSend){

        $rv = sendEmail(HEURIST_MAIL_TO_ADMIN, $title, $message);
        if($rv){
            if (file_exists($fname)) {unlink($fname);}
            file_put_contents($fname, date_create('now')->format(DATE_8601));
        }
    }



}
