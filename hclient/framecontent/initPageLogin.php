<?php

/**
*  Init page with minimal client (HAPI) and forceful login
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

require_once 'initPageMin.php';//without client hapi

if(!@$_REQUEST['db']){
    $message = '<b>Required</b> database parameter >>> is not defined';
    include_once ERROR_REDIR;
    exit;
}

/*
Workflow:
loads main page for logo, icon, banner, style


*/

$system->defineConstants();
$hasAccess = ($system->is_admin());
?>
<!DOCTYPE html>
<html lang="en" xml:lang="en">
<head>
    <title><?php echo HEURIST_TITLE; ?></title>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <meta name="robots" content="noindex,nofollow">

<?php
    includeJQuery();
?>
    <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />
    <script>window.hWin = window;</script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>

    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hapi.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/HSystemMgr.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/profile/profile_login.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/profile/profile_edit.js"></script>
<?php
    include_once dirname(__FILE__).'/initPageCss.php';

    $dbname = @$_REQUEST['db'];
    $dbname = (preg_match('[\W]', $dbname))?'':$dbname;
?>
<script>
var login_warning = ''
var requiredLevel = 0; //1-admin, 2-owner, 0 logged in
var database = '<?php echo htmlspecialchars($dbname);?>';
//
//
//
function onHapiInit(success){

    if(!success){
        window.hWin.HEURIST4.msg.showMsgErr({
            message: 'Cannot initialize system on client side. '
                    +`Database${database}, please consult Heurist developers`,
            error_title: 'Unable to initialise Heurist'
        });
            return;
    }

<?php
     //returns total records in db and counts of active entries in dashboard
     list($db_total_records, $db_has_active_dashboard, $db_workset_count) = $system->getTotalRecordsAndDashboard();
     echo 'window.hWin.HAPI4.sysinfo.db_total_records = '.$db_total_records.';';
     echo 'window.hWin.HAPI4.sysinfo.db_has_active_dashboard = '.$db_has_active_dashboard.';';
     echo 'window.hWin.HAPI4.sysinfo.db_workset_count = '.$db_workset_count.';';
?>

    verify_credentials( false );
}

function verify_credentials( show_warning ){

    if(window.hWin.HAPI4.has_access(requiredLevel)){

    }else{

        msg = 'To perform this operation you have to be logged in (you may have been logged out due to lack of activity - if so, please reload the page)';

        if(requiredLevel==window.hWin.HAPI4.sysinfo.db_managers_groupid){
           msg += ' as database administrator';// of group "Database Managers"'
        }else if(requiredLevel==2){
           msg += ' as database onwer';
        //}else if(requiredLevel!=0){

        }
        if(msg!=''){
            var win_mappreview = window.parent.hWin;
            if(show_warning){

                var $dlg2 = win_mappreview.HEURIST4.msg.showMsgDlg(msg+'<br> Database: '+window.hWin.HAPI4.database,
                    {OK:
                    function(){

                        $dlg2.dialog( "close" );

                        showLoginDialog(false, function( is_logged ) {

                        }, win_mappreview, 'heurist-clearinghouse-login-dialog');
                    }});
            }else{
                //show login dialog at once
                showLoginDialog(false, function(is_logged){

                        }, win_mappreview, 'heurist-clearinghouse-login-dialog');
            }


        }
    }
}

//
//init hapi
//
$(document).ready(function() {
    window.hWin.HAPI4 = new hAPI(database, onHapiInit);
});
</script>
</head>
<body>
</body>
</html>

