<?php
/**
* welcomeEmail.php: mails for new and cloned database
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
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

function sendEmailNewDatabase($user_record, $database_name, $source_database){

    $fullName = $user_record['ugr_FirstName'].' '.$user_record['ugr_LastName'];

    // email the system administrator to tell them a new database has been created
    /*
    $email_text =
    "There is new Heurist database.\n".
    "Database name: ".$database_name."\n\n".

    'The user who created the new database is:'.$user_record['ugr_Name']."\n".
    "Full name:    ".$fullName."\n".
    "Email address: ".$user_record['ugr_eMail']."\n".
    "Organisation:  ".$user_record['ugr_Organisation']."\n".
    "Research interests:  ".$user_record['ugr_Interests']."\n".
    "Go to the address below to review further details:\n".
    HEURIST_BASE_URL."?db=".$database_name;
    */

    //send an email with attachment
    $message = file_get_contents(dirname(__FILE__).'/welcomeEmail.html');

    $message =  substr($message,strpos($message,'<body>')+6);
    $message =  substr($message,0,strpos($message,'</body>')-1);
    $message =  str_replace('##Email##',$user_record['ugr_eMail'], $message);
    $message =  str_replace('##GivenNames##',$user_record['ugr_FirstName'],$message);
    $message =  str_replace('##FamilyName##',$user_record['ugr_LastName'],$message);
    $message =  str_replace('##DBURL##',''.HEURIST_BASE_URL."?db=".$database_name.'',$message);//<pre>
    $message =  str_replace('##Organisation##',$user_record['ugr_Organisation'],$message);
    $message =  str_replace('##Interests##',$user_record['ugr_Interests'],$message);

    if(strtolower(substr($user_record['ugr_eMail'], -3)) === '.fr'){
        $message =  str_replace('&lt;FrenchNoteIfFrance&gt;',
        '<p>Je m\'excuse du fait que ce mel soit en anglais. Vous pouvez cependant me r&eacute;pondre en fran&ccedil;ais.</p>'
        ,$message);
    }else{
        $message =  str_replace('&lt;FrenchNoteIfFrance&gt;','',$message);
    }

    $email_title = (($source_database!=null)?'CloneDB: ':'NewDB: ')
                    .'Getting up to speed with your Heurist database ('.$database_name.') on '.HEURIST_SERVER_NAME;

    return sendEmail(array($user_record['ugr_eMail'],HEURIST_MAIL_TO_ADMIN), $email_title, $message,
                                                    true, dirname(__FILE__).'/Heurist Welcome attachment.pdf');
}

//
//
//
function sendEmailDatabaseDelete($usr_owner, $database_name){

                    $server_name = HEURIST_SERVER_NAME;
                    $email_title = 'Your Heurist database '.$database_name.' has been archived';
                    $email_text = <<<EOD
Dear {$usr_owner['ugr_FirstName']} {$usr_owner['ugr_LastName']},

Your Heurist database {$database_name} on {$server_name} has been archived. We would like to help you get (re)started.

In order to conserve server space (or as part of moving to another server) we have archived your database on {$server_name} since it has not been used for several months and/or no data has ever been created and/or it is being recreated somewhere else.

If you got as far as creating a database but did not know how to proceed you are not alone, but those who persevere, even a little, will soon find the system easy to use and surprisingly powerful. We invite you to get in touch ( support@HeuristNetwork.org ) so that we can help you over that (small) initial hump and help you see how it fits with your research (or other use).

With a brand new interface in 2021, developed in collaboration with an experienced UX designer, Heurist is significantly easier to use than previous versions. The new interface (version 6) remains compatible with databases created more than 10 years ago - we believe in sustainability.

Please contact us if you need your database re-enabled or visit one of our free servers to create a new database (visit HeuristNetwork dot org).

Heurist is research-led and responds rapidly to evolving user needs - we often turn around small user suggestions (and most bug-fixes) in a day and larger ones within a couple of weeks. We are still forging ahead with new capabilities such as enhancements to the integrated CMS website builder, new output formats, improved data exchange through XML, JSON, remote resource lookups and linked open data, and improved search, mapping and visualisation widgets (embeddable in websites).

For more information email us at support@HeuristNetwork.org and visit our website at HeuristNetwork.org. We normally respond within hours, depending on time zones. We are actively developing new documentation and training resources for version 6 and can make advance copies available on request.
EOD;

sendEmail(array($usr_owner['ugr_eMail'],HEURIST_MAIL_TO_ADMIN), $email_title, $email_text);

}
