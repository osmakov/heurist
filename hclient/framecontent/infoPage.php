<?php

/**
* Information page
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
use hserv\utilities\USanitize;

$is_inlcuded = false;

if(!defined('PDIR')) {
    $is_inlcuded = true;
    define('PDIR','../../');
    require_once dirname(__FILE__).'/../../autoload.php';
}

//variable is_error can be defined as global
if(!isset($is_error)){
    $is_error = true;
}

$is_error_unknown = false;

//variable message can be defined as global
if(!isset($message)){
    
    $req_params = USanitize::sanitizeInputArray();
    
    if( @$req_params['error'] ){
        $message = $req_params['error'];
        if(is_array($message)){
            $message = @$message['message'];
            if(@$message['sysmsg'] && $message!=null){
                $message = $message.$message['sysmsg'];
            }
        }
    }elseif( @$req_params['message'] ){
        $message = $req_params['message'];
        $is_error = false;
    }else{
        //take error message from system
        if(isset($system)){
            $err = $system->getError();
            $message = @$err['message'];
        }else{
            $message = 'Heurist core engine is not initialized.';
            $is_error_unknown = true;
        }

    }
    if(!$message){
        $message ='Unknown error.';
        $is_error_unknown = true;
    }
    USanitize::purifyHTML($message);

    if($is_error_unknown){
        if(defined('CONTACT_HEURIST_TEAM')){
            $message = $message.' Please '.CONTACT_SYSADMIN.' or '.CONTACT_HEURIST_TEAM;
        }else{
            $message = $message.' Please contact Heurist team';
        }
    }
}

    $dbname = @$_REQUEST['db'];
    $dbname = (preg_match('[\W]', $dbname))?'':$dbname;
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title><?php print defined('HEURIST_TITLE')?HEURIST_TITLE:"Heurist";?></title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">

        <link rel=icon href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">

        <!-- CSS -->
        <?php include_once dirname(__FILE__).'/initPageCss.php';?>
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />

        <style>
            a.login-link{
                text-decoration: underline;
                color: blue;
                cursor: pointer;
            }
        </style>
    <?php
    if(isset($try_login) && $try_login === true){ // Does a login link need to be handled

            includeJQuery();
    ?>

        <script>window.hWin = window;</script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hapi.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/HSystemMgr.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/profile/profile_login.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/profile/profile_edit.js"></script>

        <script>

            function onHapiInit() {
                let $login_ele = $(document).find('.login-link');
                if($login_ele.length > 0 && window.hWin && window.hWin.HEURIST4){
                    let reload_target = $login_ele.attr('reload');
                    $login_ele.on('click', () => {
                        if(window.hWin && window.hWin.HEURIST4){
                            window.hWin.HEURIST4.ui.checkAndLogin(true, () => {
                                if(reload_target=='home'){
                                    document.location = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database;
                                }else{
                                    location.reload();
                                }

                            });
                        }
                    });
                }
            }

            $(document).ready(() => {
                window.hWin.HAPI4 = new hAPI('<?php echo htmlspecialchars($dbname);?>', onHapiInit);
            });
        </script>
    <?php
    }else{
        print '<script>if(window.history && typeof history.pushState === "function") {window.history.pushState({}, document.title, window.location.pathname)}</script>';
    }

    ?>
    </head>
    <body style="padding:44px;" class="ui-heurist-header1">
        <div class="ui-corner-all ui-widget-content" style="text-align:left; width:70%; min-width:220px; margin:0px auto; padding: 0.5em;">

            <div class="logo" style="background-color:#2e3e50;width:100%"></div>

            <div class="<?php echo $is_error?'ui-state-error':'';?>"
                style="width:90%;margin:auto;margin-top:10px;padding:10px;">
                <span class="ui-icon <?php echo $is_error?'ui-icon-alert':'ui-icon-info';?>"
                      style="float: left; margin-right:.3em;font-weight:bold"></span>
                <?php echo USanitize::sanitizeString($message);?>
            </div>
        </div>
    </body>
</html>
