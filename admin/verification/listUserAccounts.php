<?php

/**
* listUserAccounts.php: All users and which databases they appear in, also listing how many records they and whether they're the db owner
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

define('ADMIN_PWD_REQUIRED', 1);
define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';

$system = new hserv\System();
if(!$system->init(null, false, false)){
    exit("Cannot establish connection to sql server\n");
}

$mysqli = $system->getMysqli();
$databases = mysql__getdatabases4($mysqli, false);

$user_list = []; // 0 => DB Name, 1 => User ID, 2 => Record Owner Count, 3 => Is DB Admin, 4 => Is DB Owner, {key} => User Email

/*
IF $_REQUEST HAS to_remove:
    FOREACH user_emails:
        TRANSFER RECORD OWNERSHIP TO DB OWNER
        RETAIN ACCOUNT IF DB OWNER
        SEND EMAIL NOTIFICATION TO USER
    END FOREACH
    RENDER RESULTS
    EXIT
END IF
*/

foreach($databases as $database){
    
    $res = mysql__usedatabase($mysqli, $database);

    $database = htmlspecialchars($database);

    $db_users = mysql__select_assoc2($mysqli, "SELECT ugr_ID, ugr_eMail FROM sysUGrps WHERE ugr_Type = 'user'");

    foreach($db_users as $usr_ID => $usr_email){

        $usr_ID = intval($usr_ID);
        
        if(!array_key_exists($usr_email, $user_list)){
            $user_list[$usr_email] = [];
        }

        $rec_count = mysql__select_value($mysqli, "SELECT COUNT(rec_ID) FROM Records WHERE rec_OwnerUGrpID = $usr_ID", 'intval');
        $is_admin = mysql__select_value($mysqli, "SELECT ugl_ID FROM sysUsrGrpLinks WHERE ugl_GroupID = 1 AND ugl_Role = 'admin' AND ugl_UserID = $usr_ID");

        $user_list[$usr_email][] = [
            $database,
            $usr_ID,
            intval($rec_count),
            $usr_ID == 2 ? 1 : 0,
            intval($is_admin) > 0 ? 1 : 0
        ];
    }
}

ksort($user_list, SORT_FLAG_CASE);

?>

<!DOCTYPE html>
<html lang="en">

    <head>

        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">

        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />

        <title>List of Users</title>

        <script type="text/javascript">

            document.addEventListener('DOMContentLoaded', () => {

                document.querySelector('#btn_Search').addEventListener('click', () => {

                    let filter = document.querySelector('input[name="txt_email"]').value;
                    document.querySelectorAll('h3').forEach((element) => {
                        let email = element.textContent;
                        if(filter.length < 2 || email == filter || email.indexOf(filter) >= 0){
                            element.parentNode.style.display = 'block';
                        }else{
                            element.parentNode.style.display = 'none';
                        }
                    });
                });

                document.querySelector('#btn_Reset').addEventListener('click', () => {
                    document.querySelectorAll('input[name="txt_email"]').value = '';
                    document.querySelector('input[name="chkbx_owner"]').checked = true;
                    document.querySelector('input[name="chkbx_admin"]').checked = true;
                });

                document.querySelector('input[name="chkbx_owner"], input[name="chkbx_admin"]').addEventListener('change', () => {

                    let show_owners = document.querySelector('input[name="chkbx_owner"]').checked;
                    let show_admins = document.querySelector('input[name="chkbx_admin"]').checked;

                    document.querySelectorAll('div.user-section').forEach((element) => {
                        if((!show_owners && element.querySelector(`[data-owner="1"]`)) || (!show_admins && element.querySelector(`[data-admin="1"]`))){
                            element.display.style = 'none';
                        }else{
                            element.display.style = 'block';
                        }
                    });
                });
            });
        </script>

        <style>

            .user-section{
                border-top: 1px black solid;
                margin-top: 10px;
            }

            h3{
                margin-top: 10px;
            }

            table{
                border-spacing: 25px 0px;
            }

            td{
                text-align: center;
            }

        </style>

    </head>

    <body>

        <div id="div_Filtering">
            Filter:<br>
            <label>Email: <input type="text" name="txt_email" size="20"></label>
            <button id="btn_Search" style="margin-right: 15px;">Search</button>

            Show: 
            <label><input type="checkbox" name="chkbx_owner" checked="checked"> Owners</label>
            <label><input type="checkbox" name="chkbx_admin" checked="checked"> Administrators</label>

            <button id="btn_Reset"style="margin-left: 15px;">Reset</button>
        </div>

    <?php

        foreach($user_list as $email => $user_accounts){

            $list_items = '';

            foreach($user_accounts as $details){

                $owner = $details[3] == 1 ? 'Yes' : 'No';
                $admin = $details[4] == 1 ? 'Yes' : 'No';
                $url = HEURIST_BASE_URL . "?db={$details[0]}";
                $recs_url = HEURIST_BASE_URL . "?db={$details[0]}&q=owner:{$details[1]}";

                $list_items .= '<tr class="user-row">'
                    . "<td><a href='{$url}' target='_blank' rel='noopener'>Database link</a></td><td>{$details[0]}</td>"
                    . "<td data-reccount='{$details[2]}' title='Click to search for records'><a href='{$recs_url}' target='_blank' rel='noopener'>{$details[2]}</a></td>"
                    . "<td data-owner='{$details[3]}'>{$owner}</td><td data-admin='{$details[4]}'>{$admin}</td>"
                . '</tr>';
            }

            print "<div data-idx='{$idx}' class='user-section'>"
                    . "<h3>{$email}</h3>"
                    . '<table role="presentation">'
                        . '<thead><tr><th></th><th>Database</th><th>Owned records</th><th>Is owner?</th><th>Is admin?</th></tr></thead>'
                        . "<tbody>{$list_items}</tbody>"
                    . '</table>'
                . '</div>';

        }
    ?>

    </body>

</html>