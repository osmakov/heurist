<?php
/**
* Main header for all heurist pages.
* It
* 1) initializes System.php
* 2) prints out html header with minimum set of scripts
* 3) init client side hAPI
* 4) apply theme
* 5) load and init localiztion
* 6) calls for user defined onPageInit function that should perform further page init - IMPORTANT
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

require_once dirname(__FILE__).'/../../autoload.php';


if(defined('IS_INDEX_PAGE')){
    //from main (index) page it redirects to startup
    $_REQUEST['list'] = 1;
    define('ERROR_REDIR','startup/index.php');//redirects to startup page - list of all databases
}else{
    if(!defined('PDIR')) {define('PDIR','../../');}//need for proper path to js and css
    define('ERROR_REDIR', dirname(__FILE__).'/../../hclient/framecontent/infoPage.php');
}

$error_msg = '';
$isSystemInited = false;

// init main system class
$system = new hserv\System(true);

if(@$_REQUEST['db']){
    //if database is defined then connect to given database
    $isSystemInited = $system->init(@$_REQUEST['db']);
}

if(!$isSystemInited){
    include_once ERROR_REDIR;
    exit;
}

if(defined('IS_INDEX_PAGE')){

    //verify database version against minimal required
    $subsubVer = intval($system->settings->get('sys_dbSubSubVersion'));

    if($subsubVer===null){
        $message = $system->getErrorMsg();
        include_once ERROR_REDIR;
        exit;
    }

    if (version_compare(HEURIST_MIN_DBVERSION,
    $system->settings->get('sys_dbVersion').'.'
    .$system->settings->get('sys_dbSubVersion').'.'
    .$subsubVer)>0){

        include_once 'admin/setup/dbupgrade/upgradeDatabase.php';
        exit;
    }

    //check for missed tables
    $missed = hasAllTables($system->get_mysqli());

    if(is_array($missed)){
        if(!empty($missed)){
            $message = 'Database <b>'.HEURIST_DBNAME
            .'</b> is missing the following tables:<br><br><i>'
            .implode(', ',$missed)
            .'</i><p>Either the database has not been fully reated (if new) or fully restored from archive. '
            .CRITICAL_DB_ERROR_CONTACT_SYSADMIN.'</p>';

            //to add to error log
            $system->addError(HEURIST_DB_ERROR, 'Database '.HEURIST_DBNAME
                    .' is missing the following tables: '.implode(', ',$missed));

            include_once ERROR_REDIR;
            exit;
        }
    }else{
        $message = 'There is database server intermittens. '.CRITICAL_DB_ERROR_CONTACT_SYSADMIN;

        $system->addError(HEURIST_DB_ERROR, 'Database '.HEURIST_DBNAME, $missed);

        include_once ERROR_REDIR;
        exit;
    }
}

if(!$system->has_access() && !empty(@$_REQUEST['user']) && !empty(@$_REQUEST['pwd'])){ // attempt login with provided creds

    $user_pwd = USanitize::getAdminPwd();

    $mysqli = $system->get_mysqli();
    $ugr_ID = is_numeric($_REQUEST["user"]) && $_REQUEST["user"] > 0 ? intval($_REQUEST["user"]) : null;
    $username = "";

    $attempt_login = false;

    if($ugr_ID !== null){
        $res = $mysqli->query("SELECT ugr_Name FROM sysUGrps WHERE ugr_ID = $ugr_ID");
        $username = $res ? $res->fetch_row()[0] : null;
    }else{

        $username = $mysqli->real_escape_string($_REQUEST['user']);

        $res = $mysqli->query("SELECT ugr_ID FROM sysUGrps WHERE ugr_Name = '$username'");
        $ugr_ID = $res ? intval($res->fetch_row()[0]) : null;
    }

    // Handle individual cases
    if(intval($ugr_ID) > 2 &&
        array_key_exists('rec_rectype', $_REQUEST) && strpos($_SERVER['REQUEST_URI'], 'recordEdit.php') !== false){
        // Record Edit from non-logged in user, use the provided default account
        // Cannot be a workgroup admin, a member of the DB managers workgroup or the DB owner

        $query = "SELECT COUNT(ugl_ID) FROM sysUsrGrpLinks WHERE ugl_UserID = $ugr_ID AND (ugl_GroupID = 1 OR ugl_Role = 'admin')";

        $res = $mysqli->query($query);
        $role_count = $res ? $res->fetch_row()[0] : -1;
        $res->close();

        $attempt_login = intval($role_count) === 0;
    }

    if($attempt_login && !empty($username) && $user_pwd!=null){
        $system->doLogin($username, $user_pwd, 'public');
    }
}

$login_warning = 'To perform this action you must be logged in';
$invalid_access = true;

$is_admin = $system->is_admin();

//
// to limit access to particular page
//
if(defined('LOGIN_REQUIRED') && !$system->has_access()){
    //No Need to show error message when login is required, login popup will be shown
    //$message = $login_warning

    exit;
}elseif(defined('MANAGER_REQUIRED') && !$is_admin){ //A member should also be able to create and open database
    $message = $login_warning.' as Administrator of group \'Database Managers\'';
    include_once ERROR_REDIR;
    exit;
}elseif(defined('OWNER_REQUIRED') && !$system->is_dbowner()){
    $message = $login_warning.' as Database Owner';
    include_once ERROR_REDIR;
    exit;
}else{
    $invalid_access = false;
}

// Check if current user has the necessary permissions
if(!$invalid_access && (defined('CREATE_RECORDS') || defined('DELETE_RECORDS'))){

    $required = '';
    $user_permissions = $system->getCurrentUser()['ugr_Permissions'];

    if(defined('CREATE_RECORDS') && !$user_permissions['add'] && !$is_admin){
        $required = 'create';
    }
    if(defined('DELETE_RECORDS') && !$user_permissions['delete'] && !$is_admin){
        $required .=  $required === '' ? 'delete' : ' and delete';
    }

    if($required !== ''){ $message = "To perform this action you need permission to $required records";}
}

//$system->defineConstants();//init constants for record and field types

// BASE tag is convenient however it does not suit
// reason: some jquery widgets uses href (tabcontrol for example)
// <base href="<?php echo PDIR;">
?>
<!DOCTYPE html>
<html lang="en">
<head>

<title><?php echo (@$_REQUEST['db']?htmlspecialchars($_REQUEST['db']):'').'. '.HEURIST_TITLE; ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="robots" content="noindex,nofollow">

<meta name="SKYPE_TOOLBAR" content="SKYPE_TOOLBAR_PARSER_COMPATIBLE" />
<meta content="telephone=no" name="format-detection">

<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!--
<meta http-equiv="Content-Security-Policy" content="frame-ancestors 'self'; frame-src 'self' https://test-idp.federation.renater.fr;" />

<meta http-equiv="Content-Security-Policy" content="default-src https: data: http: 'unsafe-eval' 'unsafe-inline'; img-src https: data: http:;">
-->
<!--
'self' http://maps.nypl.org -->

<link rel=icon href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">
<link rel="shortcut icon" href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">

<?php
    includeJQuery();
?>

<script src="<?php echo PDIR;?>external/jquery-file-upload/js/jquery.fileupload.js"></script>
<script src="<?php echo PDIR;?>external/jquery-file-upload/js/jquery.iframe-transport.js"></script>

<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />

<script type="text/javascript" src="<?php echo PDIR;?>external/js/wellknown.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/temporalObjectLibrary.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_dbs.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hapi.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/HSystemMgr.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/layout.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hRecordSearch.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/recordset.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_query.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_geo.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utilsCollection.js"></script>

<!-- CSS -->
<?php include_once dirname(__FILE__).'/initPageCss.php';?>

<script type="text/javascript">

    // overwrite the standard jquery show method
    // apply listener in widgets on this page to refresh content on show
    // example

    //        this.element.on("myOnShowEvent", function(event){
    //            if( event.target.id == that.element.attr('id')){

    //            }


    var orgShow = $.fn.show;
    $.fn.show = function()
    {
        orgShow.apply( this, arguments );//apply original show
        $(this).trigger( 'myOnShowEvent' );
        return this;
    }

    //
    // overwrite datepicker method
    //

    $.datepicker._gotoToday = function(event){

        var target = $(event),
        inst = this._getInst(target[0]);

        var date = new Date();

        inst.selectedDay = date.getDate();
        inst.drawMonth = inst.selectedMonth = date.getMonth();
        inst.drawYear = inst.selectedYear = date.getFullYear();

        $.datepicker._selectDate(event,
            $.datepicker._formatDate(inst,
                inst.selectedDay, inst.selectedMonth, inst.selectedYear));

    }


    window.onAboutInit = null;
    window.onPageInit = null;
    window.isHapiInited = false;

    // if hAPI is not defined in parent(top most) window we have to create new instance
    $(document).ready(function() {

        // Standalone check
        if(!window.hWin.HAPI4){
            window.hWin.HAPI4 = new hAPI('<?php echo htmlspecialchars($_REQUEST['db'])?>', onHapiInit);
        }else if(!window.isHapiInited){
            // Not standalone, use HAPI from parent window
            onHapiInit( true );
        }

    });

    //
    // Callback function on hAPI initialization
    //
    function onHapiInit(success)
    {
        window.isHapiInited = true;

        if(success) // Successfully initialized system
        {
            applyTheme();

            if(!window.hWin.HEURIST4.util.isnull(window.onAboutInit) && window.hWin.HEURIST4.util.isFunction(window.onAboutInit)){
                if(window.hWin.HAPI4.sysinfo['layout']!='WebSearch')
                    window.onAboutInit();//init about dialog
            }

            if(initialLoadDatabaseDefintions(null, window.onPageInit)){
                return;
            }

        }else{
            window.hWin.HEURIST4.msg.showMsgErr({
                message: 'Cannot initialize system on client side, please consult Heurist developers',
                error_title: 'Unable to initialise Heurist'
            });
            success = false;
        }

        if(window.hWin.HEURIST4.util.isFunction(window.onPageInit)){
            window.onPageInit(success);
        }
    }

    //
    //
    //
    function initialLoadDatabaseDefintions(params, callback){

            if($.isEmptyObject(window.hWin.HAPI4.EntityMgr.getEntityData2('defRecTypes'))){ //defintions are not loaded

                var sMsg = 'Cannot obtain database definitions (refreshEntityData function). '
                +'This is probably due to a network timeout. However, if the problem '
                +'persists please report to Heurist developers as it could indicate '
                +'corruption of the database.';

                //params = {recID:recID} or {rty_ID:rty_ID} - to load defs for particular record or rectype
                var entities = (params)?params:'all';

                window.hWin.HAPI4.EntityMgr.refreshEntityData(entities, function(){
                    if(arguments){
                    if(arguments[1]){

                        //verify definitions relevance every 20 seconds
                        if(!window.hWin.RefreshCacheInterval){
                            window.hWin.RefreshCacheInterval = setInterval(function(){window.hWin.HAPI4.EntityMgr.relevanceEntityData()}, 600000);
                        }

                        if(!window.hWin.HEURIST4.util.isnull(callback) && window.hWin.HEURIST4.util.isFunction(callback)){
                            callback(true);
                        }
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: sMsg,
                            error_title: 'Issue with database definitions',
                            status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                        });
                        if(window.hWin.HEURIST4.util.isFunction(callback)){ callback(false);}
                    }
                    }
                });
                return true;
            }
            return false;

    }

    //
    // it itakes name of theme from preferences , oherwise default theme is heurist
    //
    function applyTheme(){

        var prefs = window.hWin.HAPI4.get_prefs();
        /* unfortunately dynamic addition of theme and style is not applied properly.
        Browser takes some time on its parsing while we have already created some ui elements, need timeout.
        So, its better to detecct current theme on server side
        if(prefs['layout_theme'] && !(prefs['layout_theme']=="heurist" || prefs['layout_theme']=="base")){
        //load one of standard themes from jquery web resource
        cssLink = $('<link rel="stylesheet" type="text/css" href="http:......./themes/'+
        prefs['layout_theme']+'/jquery-ui.css" />');
        }else{
        //default BASE or HEURIST theme
        cssLink = $('<link rel="stylesheet" type="text/css" href="ext/jquery-ui-....../themes/'+prefs['layout_theme']+'/jquery-ui.css" />');
        }
        $("head").append(cssLink);
        $("head").append($('<link rel="stylesheet" type="text/css" href="h4styles.css?t='+(new Date().getTime())+'">'));
        */
        var layoutid = '<?php echo htmlspecialchars(@$_REQUEST['ll']);?>';

        if(window.hWin.HEURIST4.util.isempty(layoutid)){
            layoutid = "H6Default";
            /*layoutid = window.hWin.HAPI4.get_prefs('layout_id');
            if(window.hWin.HEURIST4.util.isempty(layoutid)){
            layoutid = "H5Default";
            }*/
        }
        if(!window.hWin.HAPI4.sysinfo['layout']){
            window.hWin.HAPI4.sysinfo['layout'] = layoutid; //keep current layout
        }
        //add version to title

    }
</script>

