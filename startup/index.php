
<?php
/**
* Main setup sequence page
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
if (!defined('PDIR')){
    define('PDIR','../');
    require_once dirname(__FILE__).'/../autoload.php';
}

// init main system class
//$system = new hserv\System();
//$system->defineConstants();
?>
<!DOCTYPE html>
<html lang="en">
<head>

<title>Heurist Academic Collaborative Database (C) 2005 - 2024, University of Sydney</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">

<meta name="SKYPE_TOOLBAR" content="SKYPE_TOOLBAR_PARSER_COMPATIBLE" />
<meta content="telephone=no" name="format-detection">

<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel=icon href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">
<link rel="shortcut icon" href="<?php echo PDIR;?>favicon.ico" type="image/x-icon">

<?php
    includeJQuery();
?>

<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>

<script>
    window.hWin = window;
     //stub
    window.hWin.HR = function(res){ return res; }
</script>

<script type="text/javascript">

    const baseURL = '<?php echo HEURIST_BASE_URL; ?>';
    const sysadmin_email = '<?php echo HEURIST_MAIL_TO_ADMIN; ?>';
    let all_databases = {};

/*
    screens/steps
    1. setup startup page - get list of databases (_getDatabases), init controls/search db dropdown (_initControls)
    2. user registration form - init controls (_showRegistration), validate input data (_validateRegistration)
    3. define db name - create new database (_doCreateDatabase)
    4. wait screen  - in progress
    5. success report
    6. getting started
    7. terms and conditions
    8. list of all databases

    _getDatabases() - get db list from server
    _initControls() - init controls on step1 (dropdown search db)
    _showRegistration() - show user reg form and init controls
    _validateRegistration - validate user registration form (step2)
    _doCreateDatabase - create new database (from step3)
    _showGetStarted() - getting started (step6)
    _showDatabaseList()  - all databases (step8)


*/

    //
    //
    //
    function _showStep(arg){
        let step_no = 1;
        if(window.hWin.HEURIST4.util.isNumber(arg)){
            step_no = arg
        }else{
            step_no = $(arg.target).attr('data-step');
        }

        $('.center-box').hide();
        $('.center-box.screen'+step_no).fadeIn(300);
    }

    //
    //
    //
    function _showRegistration(){

        let screen = $('.center-box.screen2');

        if(screen.children().length>0){ //is(':empty')

            refreshCaptcha();
            _showStep(2);

        }else{

            let sForm = (document.location.pathname.indexOf('startup/')>0
                            ?'':'startup/')+'userRegistration.html';
            screen.load(sForm,
                function(){

                    $('.registration-form').children('div').css('padding-top','5px');

                    $('.registration-form').find('.header').each(function(idx, item){

                        item = $(item);
                        let ele = item.next('input');
                        if(ele.length==0) ele = item.next('textarea');
                        if(ele.length==1){
                            ele.attr('autocomplete','off')
                                .attr('autocorrect','off')
                                .attr('autocapitalize','none');
                            //ele.value(item.text()).attr('data-heder',item.text());
                            //ele.on({focus:function(){ if(this.value==$(this).attr('data-heder')) this.value = '';}});
                        }

                    });

                    let ele = $('#ugr_CaptchaCode')
                    ele.parent().css({
                        display: 'inline-block', float: 'left', 'min-width': 90, width: 90});

                    $('#btnRegisterDo').button().on({click: _validateRegistration});
                    $('#btnRegisterCancel').button().on({click: _showStep});


                    $("#contactDetails").html('Email to: System Administrator<br>'+
                        '<a style="padding-left: 60px;" href="mailto:'+sysadmin_email+'">'+sysadmin_email+'</a>');

                    $('#cbAgree').on({'change':function(){
                        window.hWin.HEURIST4.util.setDisabled($('#btnRegisterDo'), !$(this).is(':checked'));
                    }});

                    $('#ugr_eMail').on({'blur':function(){
                        if($('#ugr_Name').val()=='') {
                                $('#ugr_Name').val(this.value);
                        }
                    }});

                    $('#showConditions').on({click: function(){
                        if($('#divConditions').is(':empty')){
                            $('#divConditions').load('../documentation_and_templates/terms_and_conditions.html #content');
                        }
                        _showStep(7);
                        return false;
                    }});

                    $('#btnTermsOK').button().on({click: function(){ $('#cbAgree').prop('checked',true).trigger('change'); _showStep(2);}});
                    $('#btnTermsCancel').button().on({click: function(){ $('#cbAgree').prop('checked',false).trigger('change'); _showStep(2);}});

                    refreshCaptcha();
                    _showStep(2);
                });
        }

    }

    //
    // allow only alphanumeric characters for db name
    //
    function onKeyPress(event){

        event = event || window.event;
        let charCode = typeof event.which == "number" ? event.which : event.keyCode;
        if (charCode && charCode > 31)
        {
            const keyChar = String.fromCharCode(charCode);
            if(!/^[a-zA-Z0-9$_]+$/.test(keyChar)){
                event.cancelBubble = true;
                event.returnValue = false;
                event.preventDefault();
                if (event.stopPropagation) event.stopPropagation();
                return false;
            }
        }
        return true;
    }

    //
    // in user reg form
    //
    function refreshCaptcha(){
        $('#ugr_Captcha').val('');
        const id = window.hWin.HEURIST4.util.random();
        if(true){  //simple captcha
            $('#ugr_CaptchaCode').load(baseURL+'hserv/utilities/captcha.php?id='+id);
        }else{ //image captcha
            let $dd = $('#imgdiv');
            $dd.empty();//find("#img").remove();
            $('<img id="img" src="hserv/utilities/captcha.php?img='+id+'"/>').appendTo($dd);
        }
    }

    //
    // validate user registration form (step2)
    //
    function _validateRegistration(){

        let regform = $('.registration-form');
        let allFields = regform.find('input, textarea');
        let err_text = '';

        // validate mandatory fields
        allFields.each(function(){
            let input = $(this);
            if(input.hasClass('mandatory') && input.val()==''){
                input.addClass( "ui-state-error" );
                err_text = err_text + ', '+regform.find('label[for="' + input.attr('id') + '"]').html();
            }
        });

        //remove/trim spaces
        let ele = regform.find("#ugr_Captcha");
        let val = ele.val().trim().replace(/\s+/g,'');
        if(val!=''){
            ele.val(val);
        }else{
            err_text = err_text + ', Humanity check';
        }

        if(err_text==''){
            // validate email
            // From jquery.validate.js (by joern), contributed by Scott Gonzalez: http://projects.scottsplayground.com/email_address_validation/
            let email = regform.find("#ugr_eMail");
            const bValid = window.hWin.HEURIST4.util.checkEmail(email);
            if(!bValid){
                err_text = err_text + ', '+window.hWin.HR('Email does not appear to be valid');
            }

            // validate login
            let login = regform.find("#ugr_Name");
            if(!window.hWin.HEURIST4.util.checkRegexp( login, /^[a-z]([0-9a-z_@.])+$/i)){
                err_text = err_text + ', '+window.hWin.HR('Login/user name should only contain ')
                    +'a-z, 0-9, _, @ and begin with a letter';// "Username may consist of a-z, 0-9, _, @, begin with a letter."
            }else{
                const ss = window.hWin.HEURIST4.msg.checkLength2( login, "User name", 3, 60 );
                if(ss!=''){
                    err_text = err_text + ', '+ss;
                }
            }
            // validate passwords
            let password = regform.find("#ugr_Password");
            let password2 = regform.find("#password2");
            if(password.val()!=password2.val()){
                err_text = err_text + ', '+window.hWin.HR(' Passwords do not match');
                password.addClass( "ui-state-error" );
            }else if(password.val()!=''){
                /* restrict password to alphanumeric only - removed at 2016-04-29
                if(!window.hWin.HEURIST4.util.checkRegexp( password, /^([0-9a-zA-Z])+$/)){  //allow : a-z 0-9
                    err_text = err_text + ', '+window.hWin.HR('Wrong password format');
                }else{*/
                const ss = window.hWin.HEURIST4.msg.checkLength2( password, "password", 3, 16 );
                if(ss!=''){
                    err_text = err_text + ', '+ss;
                }

            }

            if(err_text!=''){
                err_text = err_text.substring(2);
            }


        }else{
            err_text = window.hWin.HR('Missing required field(s)')+': '+err_text.substring(2);
        }


        if(err_text==''){


            const user_name = document.getElementById("ugr_Name").value;
            let ele = document.getElementById("uname");
            ele.value = user_name.substr(0,5).replace(/[^a-zA-Z0-9$_]/g,'');

            _showStep(3);
            document.getElementById("dbname").focus();
        }else{
            window.hWin.HEURIST4.msg.showMsgErr({
                message: err_text,
                error_title: 'Missing required user details'
            });
        }
    }

    //
    // create new database (from step5)
    //
    function _doCreateDatabase(){

        let err_text = window.hWin.HEURIST4.msg.checkLength2( $("#dbname"), 'Database name', 1, 60 );

        if(err_text==''){

            let request = {};

            request['uname'] = $('#uname').val();
            request['dbname'] = $('#dbname').val();
            request['action'] = 'create';

            //get user registration data
            let inputs = $(".registration-form").find('input, textarea');
            inputs.each(function(idx, inpt){
                inpt = $(inpt);
                if(inpt.attr('name') && inpt.val()){
                    request[inpt.attr('name')] = inpt.val();
                }
            });

            const url = baseURL+'hserv/controller/databaseController.php';

            _showStep(4);

            window.hWin.HEURIST4.util.sendRequest(url, request, null,
                function(response){

                    if(response.status == window.hWin.ResponseStatus.OK){

                        window.open(response.data.newdblink, '_self');
                        /*
                        $('#newdbname').text(response.newdbname);
                        $('#newusername').text(response.newusername);
                        $('#newdblink').attr('href',response.newdblink).text(response.newdblink);

                        if(response.warnings && response.warnings.length>0){
                            $('#div_warnings').html(response.warnings.join('<br><br>')).show();
                        }else{
                            $('#div_warnings').hide()
                        }

                        _showStep(5);
                        */
                    }else{
                        //either wrong captcha or invalid registration values
                        if(response.status == window.hWin.ResponseStatus.INVALID_REQUEST){
                            _showRegistration();//back to registration
                        }else{
                            _showStep(3);//back to db form
                        }

                        window.hWin.HEURIST4.msg.showMsgErr(response, false);
                    }
                });

        }else{
            window.hWin.HEURIST4.msg.showMsgErr({message: err_text, error_title: 'Invalid database name'});
        }

    }

    //
    // server request for db list
    //
    function _getDatabases( show_list ){

            const url = baseURL+'startup/listDatabases.php';

            let request = {format:'json'};

            window.hWin.HEURIST4.util.sendRequest(url, request, null,
                function(response){

                    if(response.status == window.hWin.ResponseStatus.OK){

                        all_databases = response.data;

                        if(Object.keys(all_databases).length>0 && show_list){
                            _showDatabaseList();
                        }else{
                            _initControls();
                        }

                    }else{
                        all_databases = {};
                        //@todo show error on special screen - not popup
                        window.hWin.HEURIST4.msg.showMsgErr(response, false);
                    }

                });
    }

    //
    // loads getting started
    //
    function _showGetStarted(){

        let sForm = (document.location.pathname.indexOf('startup/')>0
                            ?'':'startup/')+'gettingStarted.html';

        let screen = $('.center-box.screen6');
        screen.load(sForm,
            function(){

                screen.find('img').each(function(i,img){
                    img = $(img);
                    img.attr('src',baseURL+'hclient/assets/v6/'+img.attr('data-src'));
                });

                let smsg = 'Sorry, these videos are not yet available';
                $('.video-anchor')
                    .attr('title',smsg)
                    .on({click:function(){
                        window.hWin.HEURIST4.msg.showMsgFlash(smsg);return false;
                    }});

                $('#btnOpenHeurist').button({icon:'ui-icon-arrow-1-e',iconPosition:'end'}).on({click:function(){
                    const turl = $('#newdblink').text();
                    $('.ent_wrapper').effect('drop',null,500,function(){
                        location.href = url;
                    });
                }});

                _showStep(6);
            });

    }

    //
    // init db lookup - open dropdown list on keypress in search database input
    //
    function _initControls(){

        if(window.hWin.HAPI4){
            window.hWin.HR = window.hWin.HAPI4.setLocale('ENG');
        }

        $('.button-registration').button().on({click:_showRegistration});//goto step2

        $('#btnCreateDatabase').button().on({click: _doCreateDatabase});//on step 3
        $('#btnGetStarted').button().on({click: _showGetStarted });//goto step6 - getting started

        if(Object.keys(all_databases).length>0){

            //init controls on existing-user div

            $('#btnNewDatabase').button().show();
            $('#showDatabaseList').on({click: _showDatabaseList});//goto step8

            $(document).on({click: function(event){
               if($(event.target).parents('.list_div').length==0) { $('.list_div').hide();};
            }});

            $('.list_div').on({click:function(e){
                        $(e.target).hide();
                        if($(e.target).hasClass('truncate')){
                            //navigate to database
                            $('#search_database').val($(e.target).text());
                            $('.list_div').hide();
                        }
                    }});

            $('#search_database')
                .attr('autocomplete','off')
                .attr('autocorrect','off')
                .attr('autocapitalize','none')
                .on({'keyup': function(event){

                let list_div = $('.list_div');

                let inpt = $(event.target);
                let sval = inpt.val().toLowerCase();

                if(sval.length>1){
                    list_div.empty();
                    let is_added = false;
                    let len = Object.keys(all_databases).length;
                    for (let idx=0;idx<len;idx++){
                        if(all_databases[idx].toLowerCase().indexOf(sval)>=0){
                            is_added = true; //res.push( all_databases[idx] );
                            $('<div class="truncate">'+all_databases[idx]+'</div>').appendTo(list_div);
                        }
                    }

                    list_div.addClass('ui-widget-content').position({my:'left top', at:'left bottom', of:inpt})
                        //.css({'max-width':(maxw+'px')});
                        .css({'max-width':inpt.width()+60});
                        if(is_added){
                            list_div.show();
                        }else{
                            list_div.hide();
                        }
                }else{
                    list_div.hide();
                }


                }});

            $('#btnOpenDatabase').button().on({click:function(){

                    let sval = $('#search_database').val().trim();
                    if(sval==''){
                        window.hWin.HEURIST4.msg.showMsgFlash('Define database name');
                    }else{
                        let len = Object.keys(all_databases).length;
                        for (let idx=0;idx<len;idx++){
                            if(all_databases[idx] == sval){
                                location.href = baseURL + '?db='+sval;
                                return;
                            }
                        }
                        window.hWin.HEURIST4.msg.showMsgFlash('Database "'+sval+'" not found');
                    }

            }});


        }else{
            //no one database found - hide existing user div - force create new database
            $('.existing-user').hide();
        }

        _showStep(1);
    }

    //
    // opens list of all databases
    //
    function _showDatabaseList(){

        let screen = $('.center-box.screen8');
        let list_div = screen.find('.db-list');

        if(list_div.children().length==0){

            $('#filter_database').on({'keyup': function(event){

                let list_div = $('.db-list');

                let inpt = $(event.target);
                let sval = inpt.val().toLowerCase();

                if(sval.length>1){

                    list_div.find('li').each(function(i,li){
                        if(li.innerHTML.toLowerCase().indexOf(sval)>=0){
                            li.style.display = 'block';
                        }else{
                            li.style.display = 'none';
                        }
                    });

                }else{
                    list_div.find('li').show();
                }
            }});


            list_div.empty();
            let len = Object.keys(all_databases).length;
            for (let idx=0;idx<len;idx++){
                $('<li class="truncate">'+all_databases[idx]+'</li>').appendTo(list_div);
            }

            // hide loading icon - show title and list
            screen.find('span.ui-icon').parent().hide();
            list_div.show();
            screen.find('h1').show();

            list_div.find('li').css('cursor','pointer').on({click:function(e){
                    let dbname  = $(e.target).text();
                    location.href = baseURL + '?db='+dbname;
            }});

        }
        _showStep(8);
    }

    // if hAPI is not defined in parent(top most) window we have to create new instance
    $(document).ready(function() {
        _showStep(8);
        _getDatabases( <?php echo (@$_REQUEST['list']==1)?'true':'false';?> );

        <?php if(isset($_REQUEST['error']) && count($_REQUEST['error']) >= 1){

            $_REQUEST['error']['message'] = '<br>' . $_REQUEST['error']['message'];
            ?>

            window.hWin.HEURIST4.msg.showMsgErr(<?php echo json_encode($_REQUEST['error']);?>);

        <?php
        }elseif(isset($message) && !empty($message)){
        ?>

            window.hWin.HEURIST4.msg.showMsgErr('<?php echo str_replace("'",'&#39;',$message);?>');

        <?php } ?>
    });
</script>
<style>
body {
    font-family: Helvetica,Arial,sans-serif;
    font-size: 14px;
    overflow:hidden;
}
a{
    outline: none;
}
.ui-widget {
    font-size: 0.9em;
}
.text{
    padding: 0.2em;
}
.truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width:230px;
}
.logo_intro{
    background-image: url("<?php echo PDIR;?>hclient/assets/v6/h6logo_intro.png");
    background-repeat: no-repeat !important;
    background-size: contain;
    width: 320px;
    height: 90px;
}
.bg_intro{
    background-color: rgba(218, 208, 228, 0.15);/*#DAD0E4*/
    background-image: url("<?php echo PDIR;?>hclient/assets/v6/h6logo_bg_200.png");
    background-repeat: no-repeat !important;
    background-position-x:right;
    background-position-y:bottom;
    background-size: 400px;
}
.center-box, .gs-box{
    background: #FFFFFF 0% 0% no-repeat padding-box;
    box-shadow: 0px 1px 3px #00000033;
    border: 1px solid #707070;
    border-radius: 4px;
    padding: 5px 30px 10px;
}
.center-box{
    width: 800px;
    height: 480px;
    margin: 3% auto;
}
.center-box.screen2{
    height: 600px;
}
.center-box h1, .center-box h3, .center-box .header{
    color: #7B4C98;
    font-weight:bold;
}
.center-box .helper{
    color: #00000099;
    margin: 10px 0px;
}
.center-box .entry-box{
    background: #EBEBEB 0% 0% no-repeat padding-box;
    box-shadow: 0px 1px 3px #00000033;
    border-radius: 4px;
    padding: 5px 20px 20px;
    margin:20px 0;
}
.button-registration{
    background: #4477B9;
    color: #FFFFFF;
    font-size: 1em;
    display: inline-block;
    vertical-align: bottom;
    margin-left: 20px;
}
.button-registration:hover{
    background: #3D9946;
    color: #FFFFFF;
}
.ui-button-action{
    font-weight:bold  !important;
    text-transform: uppercase;
    background: #3D9946;
    color: #FFFFFF;
}
.ui-button-action:hover{
    background: #4477B9;
    color: #FFFFFF;
}

.ent_wrapper{position:absolute;top:0;bottom:0;left:0;right: 0px;overflow:hidden;}
.ent_header,.ent_content_full{position:absolute; left:0; right:0;}
.ent_header{top:0;height:90px; padding:10px 40px;}
.ent_content_full{position:absolute;top:120px;bottom: 0;left:0;right: 1;overflow-y:auto;border-top:2px solid #323F50}

.video_lightbox_anchor_image{
    height:106px;
}
.db-list{
    column-width: 250px;
}
.db-list li {
    list-style: none;
    background-image: url("<?php echo PDIR;?>hclient/assets/database.png");
    background-position: left;
    background-repeat: no-repeat;
    padding-left: 30px;
    line-height: 17px;
}
</style>


</head>
<body>
    <div class="ent_wrapper" style="min-height:675px;">
        <div class="ent_header" style="min-width:1330px;">
            <div class="logo_intro" style="float:left"></div>
            <div style="float:left;font-style:italic;padding:34px">
                Designed by researchers, for researchers, Heurist reduces complex relational structures to simple, logical choices
                <br>and provides comprehensive tools to collect, manage, analyse, visualise, export, publish and archive information.
            </div>
            <div style="float:right;padding:34px">
                <a href="https://heuristnetwork.org" target="_blank" rel="noopener">Heurist Network website</a>
            </div>
        </div>
        <div class="ent_content_full bg_intro">

            <!-- SCREEN#1 -->
            <div class="center-box screen1">
                <h1>Set Up a New Database</h1>

                <div class="helper">
                    Create your first database on this Heurist server (<strong><?php print HEURIST_SERVER_NAME; ?></strong>) by registering as a user.<br>
                    As creator of a database you becomes the database owner and can manage the database and other database users.<br>
                    For more information on Heurist see <a href="https://heuristnetwork.org/" target="_blank" rel="noopener">Heurist Network website</a>
                </div>

                <div class="entry-box">
                    <h3>New Users</h3>
                    <div style="display: inline-block">
                        Please register in order to define the user who will become the database owner and administrator.
                    </div>
                    <button class="button-registration">Register</button>
                </div>

                <div class="entry-box existing-user">
                    <h3>Existing Users</h3>
                    <div style="display: inline-block;width:50%">
                        If you are already a user of another database on this server, we suggest logging into that database and creating your new database via the Administration menu, as this will carry over your login information from the existing database.
                    </div>
                    <div style="display: inline-block;line-height: 16px;padding-left: 20px;">
                        <div class="header" style="font-size:smaller">Find your database</div>
                        <div>
                            <input id="search_database" class="text ui-widget-content ui-corner-all" value="" autocomplete="off"/>
                            <button class="ui-button-action" id="btnOpenDatabase">Go</button>
                        </div>
                        <div style="font-size:smaller">You will be redirected to the Heurist database upon your selection</div>
                        <div style="font-size:smaller"><a href="#" id="showDatabaseList" data-step="8">Browse all databases on server</a>
                        (as <a href="../../HarvestableDatabaseDescriptions/index.html" target="_blank" rel="noopener">html pages</a>)</div>
                    </div>

                </div>


            </div>

            <!-- SCREEN#2 Registration form -->
            <div class="center-box screen2">
            </div>

            <!-- SCREEN#3 Enter database name -->
            <div class="center-box screen3">
                <h1>Set Up a New Database</h1>

                <div class="helper">
                    As creator of a database you becomes the database owner and can manage the database and other database users.
                </div>

                <div class="entry-box">
                    <h3>Enter a name for the database</h3>

                    <div>
                        <?php echo HEURIST_DB_PREFIX;?>
                        <input type="text" id="uname"  name="uname" class="text ui-widget-content ui-corner-all"
                                maxlength="30" size="6" onkeypress="{onKeyPress(event)}"/>
                        _<input type="text" id="dbname"  name="dbname" class="text ui-widget-content ui-corner-all"
                                maxlength="64" size="30" onkeypress="{onKeyPress(event)}"/>
                        <button class="ui-button-action" id="btnCreateDatabase">Create Database</button>
                    </div>

                </div>

                <div class="helper">
                    Do not use punctuation except underscore, names are case sensitive.<br><br>
                    <i>The user name prefix is editable, and may be left blank, but we suggest using a consistent prefix for<br>
                       personal databases so that they are easily identified and appear together in the search for databases.</i>
                </div>

            </div>


            <!-- SCREEN#4 In progress -->
            <div class="center-box screen4">
                <h1>Database is being created ...</h1>

                <div style="text-align: center;padding: 60px 0;">
                    <span class="ui-icon ui-icon-loading-status-circle rotate" style="height: 300px;width: 300px;font-size: 800%;color: rgb(79, 129, 189);"></span>
                </div>
            </div>

            <!-- SCREEN#5 Success  -->
            <div class="center-box screen5">
                <h1>Welcome</h1>

                <div class="entry-box">
                    <h3>Congratulations, your new database <span id="newdbname"></span> has been created</h3>

                    <div style="padding:5px 0px">
                        <span style="text-align:right;min-width:180px;display:inline-block">Owner:&nbsp;&nbsp;</span>
                        <span style="font-weight:bold" id="newusername"></span>
                    </div>

                    <div style="padding:5px 0px">
                        <span style="text-align:right;min-width:180px;display:inline-block">URL:&nbsp;&nbsp;</span>
                        <span style="font-weight:bold" id="newdblink"></span>
                    </div>

                    <div style="font-weight:normal;padding:25px 0px 20px 0px">
                        We suggest bookmarking this address for future access
                    </div>

                    <div class="ui-state-error" id="div_warnings" style="display:none;padding:10px;margin: 10px 0;">
                    </div>

                    <div style="text-align:right; padding:0px 30px">
                        <button class="ui-button-action" id="btnGetStarted" data-step="6">Get Started</button>
                    </div>
                </div>

                <div class="helper">
                    After logging in to your new database, we suggest visiting the Design menu to customise the structure of your database. You can modify the database structure repeatedly as your needs evolve without invalidating data already entered.
                </div>

            </div>

            <!-- SCREEN#6 Getting started -->
            <div class="center-box screen6" style="padding:0;border:none;width:1330;height:auto;margin:10px auto;background:none;box-shadow:none">
            </div>

            <!-- SCREEN#7 Terms and conditions -->
             <div class="center-box screen7">
                <h1>Terms and conditions</h1>
                <div id="divConditions" style="font-size:x-small;max-height:350px"></div>
                <div style="text-align:center;padding:20px">
                    <button id="btnTermsOK" class="ui-button-action">I Agree</button>
                    <button id="btnTermsCancel">Cancel</button>
                </div>
            </div>


            <!-- SCREEN#8 Terms and conditions -->
            <div class="center-box screen8" style="width:1330;height:auto;margin:10px;width:auto;">
                <h1 style="display:none">Databases</h1>
                <span>Filter: </span>
                <input id="filter_database" class="text ui-widget-content ui-corner-all" value="" autocomplete="off"/>
                <button id="btnNewDatabase" onclick="_showStep(1)" class="ui-button-action" style="float:right;display:none">New Database</button>

                <?php if(strpos(strtolower(HEURIST_BASE_URL), strtolower(HEURIST_MAIN_SERVER)) !== false){ ?>
                <span style="float:right;position:relative;bottom:25px;">
                    <span style="color: red;">If your database has disappeared:</span> Databases which have not been updated for more than 3 / 6 / 12 months, depending on size, will be archived unless marked for retention.<br>
                    Databases can be recovered later but it makes work for us, so please just create a new one if you did not enter any data.<br>
                    If you have a reference database which will never be updated or there will be a hiatus > 3 months in use of your database please inform us so we can protect it from deletion.
                </span>
                <?php } ?>

                <ul class="db-list" style="display:none;clear:both;">
                </ul>

                <div style="text-align: center;padding: 60px 0;">
                    <span class="ui-icon ui-icon-loading-status-circle rotate" style="height: 300px;width: 300px;font-size: 800%;color: rgb(79, 129, 189);"></span>
                </div>
            </div>

        </div>
    </div>

    <div class="list_div ui-heurist-header"
        style="z-index:999999999; height:auto; max-height:200px; padding:4px;cursor:pointer;display:none;overflow-y: auto"></div>
</body>
</html>