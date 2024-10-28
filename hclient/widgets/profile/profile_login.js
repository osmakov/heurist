/**
* Login dialogue
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
/* global prepared_params */

let login_dialog = null;

//
//isforsed - if true - it is not possible to get out from login other than switch database
//
function showLoginDialog(isforsed, callback, parentwin, dialog_id){
    
    let is_secondary_parent = false;
    if(!parentwin){
        parentwin = window.hWin;  
    }else{
        is_secondary_parent = true; //to reduce font in popup
    } 
    
    if(!dialog_id) dialog_id = 'heurist-login-dialog';
    
    login_dialog = $(parentwin.document['body']).find('#'+dialog_id);
    
    function __onDialogClose($dlg) {
             
            let allFields = $dlg.find('input');       
            allFields.val( "" ).removeClass( "ui-state-error" );
            
            if(window.hWin.HEURIST4.util.isFunction(callback)){
                callback(window.hWin.HAPI4.has_access());
            }else
            if( isforsed && !window.hWin.HAPI4.has_access() ){
                //redirects to startup page - list of all databases
                window.hWin.location  = window.HAPI4.baseURL; //startup page 
            }
            $dlg.remove();
    }
    
    //
    //
    //
    function __onSamlLogin(){
/*        
        $dlg.dialog('option','close',function(){ 
                $dlg.remove(); 
        });;
        let sel = $dlg.find('#saml_sp');
        if(sel.val()){
            $dlg.dialog('close');
            doSamlLogin(callback, parentwin, sel.val());
        }
*/
        let $dlg = login_dialog;
        let sel = $dlg.find('#saml_sp');
        if(sel.val()){
            
        let sp_entity = sel.val();
        
        let surl = window.hWin.HAPI4.baseURL+'hserv/controller/saml.php?a=login&sp='+sp_entity+'&db='+window.hWin.HAPI4.database;
        
        let isFrameAllowed = false;
        if(!isFrameAllowed){
            surl = surl + '&noframe=1';
            window.hWin.location = surl;
            return;
        }


        //loads saml dialog into iframe
        window.hWin.HEURIST4.msg.showDialog(
        surl,
        {
            title: 'External Authentification',
            width: 980,
            height: 420,
            //noClose: true,
            
            afterclose: function(context) {
                //$(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS, 
               
                /*
                if(!window.hWin.HAPI4.has_access() ){
                    //redirects to startup page - list of all databases
                    window.hWin.location  = window.HAPI4.baseURL; //startup page 
                }
                */
                
                //if not logged in - reopen login dialogue
            },
            callback:function(user_id){
    console.log('callback on close saml dialog', user_id);            

                if(user_id>0){
                    doAuthentication({username: user_id, password:null, saml_entity:sp_entity}, login_dialog);
                }else{
                    //reopen login dialogue
                    alert('Saml authentication returned:\n'+user_id+'\n Login dialog will be reopened');
                }

    /* 
                    if(context>0){


    console.log('current user: ',window.hWin.HAPI4.currentUser['ugr_ID']);                            
                        
                            if(window.hWin.HAPI4.currentUser && window.hWin.HAPI4.currentUser['ugr_ID']==context){
                                
                                    $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS, 
                                                                [window.hWin.HAPI4.currentUser]);

                                    
                                    if( window.hWin.HAPI4.SystemMgr.versionCheck() ) {
                                        //version is old 
                                        return;
                                    }
                                    return true;
                            }else{
                        
                            window.hWin.HAPI4.SystemMgr.sys_info(function (success) {

    console.log('getting sysinfo ',success);                            
                                if (success) {
                                    
    console.log(window.hWin.HAPI4.currentUser);                                
    console.log(window.hWin.HAPI4.sysinfo);                                
                                    
                                    $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS, 
                                                                [window.hWin.HAPI4.currentUser]);

                                    
                                    if( window.hWin.HAPI4.SystemMgr.versionCheck() ) {
                                        //version is old 
                                        return;
                                    }
                                    
                                }
                            });            
                                                    
                            }
                    }else
                    if(context && context.currentUser && context.sysinfo){
                        
                            window.hWin.HAPI4.setCurrentUser(context.currentUser);
                            window.hWin.HAPI4.sysinfo = context.sysinfo;
                            
                            $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS, 
                                                                [window.hWin.HAPI4.currentUser]);

                                    
                            if( window.hWin.HAPI4.SystemMgr.versionCheck() ) {
                                        //version is old 
                                        return;
                            }

                        return true;
                    }else{
                        return false;
                    }
    */        
        }});
        }
        
    } //__onSamlLogin
    

    if(login_dialog.length<1)  // login_dialog.is(':empty') )
    {

        login_dialog = $( '<div id="'+dialog_id+'">' )
            .addClass('ui-heurist-bg-light')
            .appendTo( $(parentwin.document['body']) );

        let $dlg = login_dialog;

        //load login dialogue
        $dlg.load(window.hWin.HAPI4.baseURL + "hclient/widgets/profile/profile_login.html?t="
                            +window.hWin.HEURIST4.util.random(), 
            function(){ 

            
            let iWidth = 600; //was 450 if only Heurist login visible
            
            let show_guest_login = (typeof prepared_params !== 'undefined' && prepared_params['guest_data']);
            
            if($.isPlainObject(window.hWin.HAPI4.sysinfo.saml_service_provides)){
                let sp_keys = Object.keys(window.hWin.HAPI4.sysinfo.saml_service_provides);
                if(sp_keys.length>0){

                    let sel = $dlg.find('#saml_sp');
                    
                    //hide standard login
                    if(window.hWin.HAPI4.sysinfo.hideStandardLogin==1){
                       
                       
                        
                        $dlg.find('#login_guest').hide();
                        $dlg.find('.login_heurist').hide();
                        show_guest_login = false;
                    }else{
                       
                        
                    }
                    
                    for(let id of sp_keys){
                        window.hWin.HEURIST4.ui.addoption(sel[0],id,window.hWin.HAPI4.sysinfo.saml_service_provides[id]);
                    }
                    
                    sel.on({change:function(event){$(event.target).val()==0?$dlg.find('.login_heurist').show():$dlg.find('.login_heurist').hide();}})
                    
                    $dlg.find('#login_saml').show();
                   
                }
            }
            
            if(show_guest_login){

                if(iWidth == 700){
                    $dlg.find('#login_guest').css({'display': 'inline-block','margin-left':'-10px'});    
                }else{
                    $dlg.find('#login_guest').css({'display': 'block', 'margin-left':'133px'});
                }

                $dlg.find('#btn_guest_auth').button().on('click',()=>doRegister( parentwin, true ));
            }
                
            $dlg.find('#span-database').text(window.hWin.HAPI4.database);

            $dlg.find('img#favicon').attr('src', window.hWin.HAPI4.baseURL + 'favicon.ico');

            if(window.hWin.HAPI4.sysinfo.host_logo){

                $('<div style="height:40px;padding-left:4px;float:right">'
                    +'<a href="'+(window.hWin.HAPI4.sysinfo.host_url?window.hWin.HAPI4.sysinfo.host_url:'#')
                    +'" target="_blank" style="text-decoration:none;color:black;">'
                            +'<span>at: &nbsp;</span>'
                            +'<img src="'+window.hWin.HAPI4.sysinfo.host_logo
                            +'" height="35" align="center"></a></div>')
                .appendTo($dlg.find('div#host_info'));
            }
            
            //find all labels and apply localization
            $dlg.find('label').each(function(){
                $(this).html(window.hWin.HR($(this).html()));
            });
            
            //fill databse owner information
            $dlg.find('#span-owner-name').text(window.hWin.HAPI4.sysinfo.dbowner_name);
            $dlg.find('#span-owner-org').text(window.hWin.HEURIST4.util.isempty(window.hWin.HAPI4.sysinfo.dbowner_org)
                    ?'N/A':window.hWin.HAPI4.sysinfo.dbowner_org);
            $dlg.find('#span-owner-email').html('<a href="mailto:'
                    +window.hWin.HAPI4.sysinfo.dbowner_email+'">'+window.hWin.HAPI4.sysinfo.dbowner_email+'</a>');
            
            if(window.hWin.HAPI4.sysinfo.db_total_records && window.hWin.HAPI4.sysinfo.db_total_records>=0){
                $dlg.find('#span-reccount').text(window.hWin.HAPI4.sysinfo.db_total_records+' records');
            }else{
                $dlg.find('#span-reccount').text('');
            }
            
            let allFields = $dlg.find('input');
            
            const saml_login_only = window.hWin.HAPI4.sysinfo.hideStandardLogin==1 && 
                                    $.isPlainObject(window.hWin.HAPI4.sysinfo.saml_service_provides);

            function __doLogin(){

                
                if($dlg.find('#saml_sp').val()!=0){
                        __onSamlLogin();
                        return;
                }else
                if(saml_login_only){
                        let sp_keys = Object.keys(window.hWin.HAPI4.sysinfo.saml_service_provides);
                        if(sp_keys.length>0){
                            __onSamlLogin();
                            return;
                        }
                }

                allFields.removeClass( "ui-state-error" );
               

                let mode = $dlg.attr('data-mode');

                if(mode == 1){
                    setupResetPin($dlg);
                }else if(mode == 2){
                    validateResetPin($dlg);
                }else if(mode == 3){
                    resetPassword($dlg);
                }else{

                    let username = $dlg.find('#username');
                    let password = $dlg.find('#password');
                    let session_type = $dlg.find('input[name="session_type"]');
                    if(session_type.is("input[type=checkbox]")){
                        session_type = 'remember';
                    }else{
                        session_type = session_type.val();
                    }

                    let bValid = window.hWin.HEURIST4.msg.checkLength( username, "username", null, 1 )
                    && window.hWin.HEURIST4.msg.checkLength( password, "password", null, 1 );         //3,63

                    if ( bValid ) {
                        doAuthentication({username: username.val(), password:password.val(), session_type:session_type}, login_dialog);
                    }
                }
            }

            //start login on enter press
            allFields.on("keypress",function(event){
                let code = (event.keyCode ? event.keyCode : event.which);
                if (code == 13) {
                    __doLogin();
                }
            });

            // Avoid submitting the form, it returns the user to the switchboard
            $dlg.find('form').on('submit', function(event){
                window.hWin.HEURIST4.util.stopEvent(event);
            });

            $dlg.find("#link_restore").on("click", function(){
                if(saml_login_only){ return; }
                updateStatus($dlg, 1, '');
            });

            $dlg.find("#link_resend").on("click", function(){

                updateStatus($dlg, 2, '');
                setupResetPin($dlg);
            });

            let arr_buttons = [];
            let reg_status = window.hWin.HAPI4.sysinfo.registration_allowed;
            if(2 & reg_status){
                arr_buttons.push({text:window.hWin.HR('Import profile from another DB'), click: function(){
                    doImport();   
                }, id:'btn_import'});
            }
            arr_buttons.push({html:('<b>'+window.hWin.HR('Login')+'</b>'), click: __doLogin, id:'btn_login2'});
            if(1 & reg_status){
                arr_buttons.push({text:window.hWin.HR('Register'), click: function(){
                    doRegister( parentwin );   
                }, id:'btn_register'});
            }
            arr_buttons.push({text:window.hWin.HR('Cancel'), click: function() {    //isforsed?'Change database':

                let mode = $dlg.attr('data-mode');
                if(mode == 0){
                    $dlg.dialog( "close" );
                }else{
                    updateStatus($dlg, 0, '');
                }
            }, id:'btn_close'});


            // login dialog definition
            $dlg.dialog({
                autoOpen: false,
                //height: 300,
                width: iWidth,
                modal: true,
                resizable: false,
                title: window.hWin.HR('Heurist Login'),
                buttons: arr_buttons,
                open: function() {
                    updateStatus($dlg, 0, '');
                }
                //position:{ my: "center center", at: "center center", of: $(top.document) }
            });
            
            login_dialog = $dlg.dialog("open");
            if(is_secondary_parent)$dlg.addClass('ui-dialog-heurist').css({'font-size':'0.8em'});
            $dlg.parent().position({ my: "center center", at: "center center", of: $(top.document) });

            login_dialog.dialog('option','close', function(){__onDialogClose($dlg)});

            updateStatus($dlg, saml_login_only ? -1 : 0, '');

        });//load html
    }else{
        //show dialogue
        login_dialog.dialog("open");
        login_dialog.parent().addClass('ui-dialog-heurist');
        login_dialog.dialog('option','close', function(){__onDialogClose(login_dialog)});
    }
}

/**
 * 
 * @param {jQuery} $dlg - login popup/element
 * @param {int} new_mode - current mode, passed to changeDisplay, false will only update error message
 * @param {string} error - error message to display, empty to remove error message 
 */
function updateStatus($dlg, new_mode = false, error = ''){

    let $err_msg = $dlg.find('.messages');
    if(window.hWin.HEURIST4.util.isempty(error)){
        $err_msg.empty();
        $err_msg.removeClass('ui-state-error');   
    }else{
        $err_msg.html(error);
        $err_msg.addClass('ui-state-error');   
    }

    if(new_mode === false){
        return;
    }

    $dlg.attr('data-mode', new_mode);

    if(new_mode == 0){
        $dlg.find('#reset_username').val('');
        $dlg.find('#reset_pin').val('');
        $dlg.find('#new_password').val('');
        $dlg.find('#dup_new_password').val('');
    }

    changeDisplay(new_mode, $dlg);
    return;
}

/**
 * Change what is displayed within the login dialog
 *  -1 - Login (SAML Only)
 *  0 (Default) - Login (username + password and SAML)
 *  1 - Request reset pin
 *  2 - Validate reset pin
 *  3 - Change password
 * 
 * @param {int} mode - controls what is displayed
 */
function changeDisplay(mode, $dlg){

    if(isNaN(Number(mode))){
        return;
    }

    let title = '';
    let btn_label = '';
    let btn_cancel = 'Back';
    let show_reg_btn = false;

    switch(mode){

        case 1:

            title = window.hWin.HR('Reset password');
            btn_label = window.hWin.HR('Request pin');

            $dlg.find("#fld_request_pin").show();
            $dlg.find("#fld_login, #fld_validate_pin, #fld_reset_password").hide();

            setupCaptcha($dlg);

            break;

        case 2:

            title = window.hWin.HR('Reset password');
            btn_label = window.hWin.HR('Validate pin');

            $dlg.find("#fld_validate_pin").show();
            $dlg.find("#fld_login, #fld_request_pin, #fld_reset_password").hide();

            break;
        
        case 3:

            title = window.hWin.HR('Reset password');
            btn_label = window.hWin.HR('Change password');

            $dlg.find("#fld_reset_password").show();
            $dlg.find("#fld_login, #fld_request_pin, #fld_validate_pin").hide();

            break;

        default:

            title = window.hWin.HR('Heurist Login');
            btn_label = '<b>'+window.hWin.HR('Login')+'</b>';
            btn_cancel = window.hWin.HR('Cancel');

            $dlg.find("#fld_login").show();
            $dlg.find("#fld_request_pin, #fld_validate_pin, #fld_reset_password").hide();

            if(mode == -1){
                $dlg.find('#login_standard').hide();    
                $dlg.find('#login_forgot').hide();
            }

            show_reg_btn = true;

            break;
    }

    // Update labels + titles, toggle register button display
    if($dlg.dialog('instance') !== undefined) { $dlg.dialog("option","title",title); }

    if(show_reg_btn){
        $("#btn_register").show();
    }else{
        $("#btn_register").hide();
    }

    $("#btn_login2").button("option","label",btn_label);
    $("#btn_close").button("option","label",btn_cancel);
}

/**
 * Requests the creation of a reset pin
 * @param {jQuery} $dlg - login dialog
 */
function setupResetPin($dlg){

    let username = $dlg.find('#reset_username');
    let captcha_code = $dlg.find('#captcha_ans').val();

    if(!window.hWin.HEURIST4.msg.checkLength( username, "username", null, 1, 0 )){
        updateStatus($dlg, 1, 'Please enter a username or email');
        return;
    }
    if(window.hWin.HEURIST4.util.isempty(captcha_code)){
        updateStatus($dlg, 1, 'Please complete the captcha');
        return;
    }

    window.hWin.HEURIST4.msg.bringCoverallToFront($dlg.parents('.ui-dialog'),null,'sending pin...');

    window.hWin.HAPI4.SystemMgr.reset_password({username: username.val(), pin: 1, captcha: captcha_code}, function(response){

        window.hWin.HEURIST4.msg.sendCoverallToBack();

        if(response.status == window.hWin.ResponseStatus.OK){

            if(response.data !== true && typeof response.data === 'string'){
                window.hWin.HEURIST4.msg.showMsgFlash(response.data, 3000);
            }

            updateStatus($dlg, 2, '');
            return;
        }else{
            window.hWin.HEURIST4.msg.showMsgErr(response);
        }
    });
}

/**
 * 
 * @param {jQuery} $dlg - login dialog 
 */
function validateResetPin($dlg){

    let username = $dlg.find('#reset_username').val();
    let pin = $dlg.find('#reset_pin').val();

    window.hWin.HEURIST4.msg.bringCoverallToFront($dlg.parents('.ui-dialog'),null,'validating pin...');

    window.hWin.HAPI4.SystemMgr.reset_password({username: username, pin: pin}, function(response){

        window.hWin.HEURIST4.msg.sendCoverallToBack();

        if(response.status == window.hWin.ResponseStatus.OK){

            if(response.data !== true && typeof response.data === 'string'){
                window.hWin.HEURIST4.msg.showMsgFlash(response.data, 3000);
                return;
            }

            updateStatus($dlg, 3, '');
            return;
        }else{
            window.hWin.HEURIST4.msg.showMsgErr(response);
        }
    });
}

/**
 * 
 * @param {jQuery} $dlg - login dialog 
 */
function resetPassword($dlg){

    let username = $dlg.find('#reset_username').val();
    let pin = $dlg.find('#reset_pin').val();

    let pwd = $dlg.find('#new_password').val();
    let dup_pwd = $dlg.find('#dup_new_password').val();

    if(pwd !== dup_pwd){

        updateStatus($dlg, 3, 'Both passwords must match');
        return;
    }

    window.hWin.HEURIST4.msg.bringCoverallToFront($dlg.parents('.ui-dialog'),null,'validating pin...');

    window.hWin.HAPI4.SystemMgr.reset_password({username: username, pin: pin, new_password: pwd}, function(response){

        window.hWin.HEURIST4.msg.sendCoverallToBack();

        if(response.status == window.hWin.ResponseStatus.OK){

            updateStatus($dlg, 0, '');

            window.hWin.HEURIST4.msg.showMsgDlg('Your password has been update.<br>Please login using your new password.');

            return;
        }else{
            window.hWin.HEURIST4.msg.showMsgErr(response);
        }
    });
}

function setupCaptcha($dlg){

    let $ele = $dlg.find('#captcha_code');
    let id = window.hWin.HEURIST4.util.random();

    if($ele.length < 1){
        return;
    }else if($ele.children().length > 1){
        $ele.empty();
    }

    $ele.load(window.hWin.HAPI4.baseURL+'hserv/utilities/captcha.php?id='+id, function(e){
        // add input
        let $input = $('<input>', {id: 'captcha_ans'}).css('width', '30px');
        $ele.append($input);
    });
}

//
//
//
function doRegister( parentwin, is_guest=false ){

    let is_secondary_parent = false;
    if(!parentwin){
        parentwin = window.hWin;  
    }else{
        is_secondary_parent = true;
    } 
    let $doc = $(parentwin.document['body']);

    if(window.hWin.HEURIST4.util.isFunction($doc.profile_edit)){

        let profile_edit_dialog = $('#heurist-profile-dialog');
        if(profile_edit_dialog.length<1){
            profile_edit_dialog = $( '<div id="heurist-profile-dialog">' ).addClass('ui-heurist-bg-light').appendTo( $doc );
        }
        
        profile_edit_dialog.profile_edit({'ugr_ID': window.hWin.HAPI4.currentUser.ugr_ID, 'parentwin': parentwin,
                 'is_guest':is_guest, 'afterRegistration': onAuthentication});
        
    }else{
        $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/profile/profile_edit.js', function() {
            if(window.hWin.HEURIST4.util.isFunction($doc.profile_edit)){
                doRegister( parentwin, is_guest );
            }else{
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: 'Widget "Profile edit" cannot be loaded!',
                    error_title: 'Script loading failed',
                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                });
            }
        });
    }

}

function doImport(){

    let reg_status = window.hWin.HAPI4.sysinfo.registration_allowed;
    if(!(2 & reg_status)){
        return;
    }

    let selected_database = null;
    let selected_user = null;

    function _prepareImport(){

        let request = {
            'a': 'action',
            'entity': 'sysUsers',
            'userIDs': selected_user,
            'sourceDB': selected_database,
            'request_id': window.hWin.HEURIST4.util.random(),
            'exit_if_exists': 1
        };
        let $dlg;

        let msg = 'Please enter the password for the selected account: <input type="password" />';
        let btns = {};

        btns[window.HR('Proceed')] = function(){

            let pwd = $dlg.find('input').val();
            request['check_password'] = pwd;

            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        $dlg.dialog('close');
                        window.hWin.HEURIST4.msg.showMsgDlg('You account has been imported');
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
            });
        }

        btns[window.HR('Back')] = function(){
            $dlg.dialog('close');
            _showUsers();
        };

        // Request password for selected account - ensure it is the actual owner
        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, {title: window.HR('Confirm password'), no: window.HR('Cancel'), yes: window.HR('Proceed')}, 
            {dialogId: 'import-user', default_palette_class: 'ui-heurist-admin'});
    }

    function _showUsers(){

        let options = {
            subtitle: 'Step 2. Select your account in '+selected_database+' to be imported',
            title: 'Import users', 
            database: selected_database,
            select_mode: 'select_single',
            edit_mode: 'none',
            keep_visible_on_selection: true,
            onInitFinished: function(){
                selected_user = null;
            },
            onClose: function(){
                if(!selected_user){
                    _showDatabases();
                }
            },
            onselect:function(event, data){
                if(data && data.selection &&  data.selection.length>0){

                    selected_user = data.selection;
                    _prepareImport();
                }
            }
        };

        window.hWin.HEURIST4.ui.showEntityDialog('sysUsers', options);
    }

    function _showDatabases(){

        let options = {
            subtitle: 'Step 1. Select the database with your existing account',
            title: 'Import users', 
            select_mode: 'select_single',
            pagesize: 300,
            edit_mode: 'none',
            use_cache: true,
            except_current: true,
            keep_visible_on_selection: true,
            onInitFinished: function(){
                selected_database = null;
            },
            onselect:function(event, data){
                if(data && data.selection && data.selection.length>0){

                    selected_database = data.selection[0].substring(4);
                    _showUsers();
                }
            }
        };    
    
        window.hWin.HEURIST4.ui.showEntityDialog('sysDatabases', options);
    }

    _showDatabases();

}

//
//
//
function doAuthentication(login_data, login_dialog)
{
    //allow guest login
    if(typeof prepared_params !== 'undefined' && prepared_params['guest_data']){
        login_data['is_guest'] = 1;
    }

    //get hapi and perform login
    window.hWin.HAPI4.SystemMgr.login(login_data,
        function(response){
            if(response.status == window.hWin.ResponseStatus.OK){

                onAuthentication(response);
                
                if( window.hWin.HAPI4.SystemMgr.versionCheck() ) {
                    //version is old 
                    return;
                }
                /*
                if(window.hWin.HEURIST4.util.isFunction(callback)){
                        callback(true);
                }
                */
                
               
            }else if(response.status == window.hWin.ResponseStatus.REQUEST_DENIED){
                if(login_dialog){
                    updateStatus(login_dialog, false, response.message);
                    setTimeout(function(){ updateStatus(login_dialog); }, 2000);
                }
            }else {
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        }

    );
}

function onAuthentication(response){

    if(response.status == window.hWin.ResponseStatus.OK){
        window.hWin.HAPI4.setCurrentUser(response.data.currentUser);
        window.hWin.HAPI4.sysinfo = response.data.sysinfo;

        $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS, 
                        [window.hWin.HAPI4.currentUser]);

        if(login_dialog && login_dialog.dialog('instance') !== undefined) login_dialog.dialog( "close" );
    }
}

//
// remove
//
function doSamlLogin(callback, parentwin, sp_entity, login_dialog){
    
    //loads saml dialog into iframe
    window.hWin.HEURIST4.msg.showDialog(
    window.hWin.HAPI4.baseURL+'hserv/controller/saml.php?a=login&sp='+sp_entity+'&db='+window.hWin.HAPI4.database,
    {
        title: 'BnF Authentification',
        width: 980,
        height: 420,
        //noClose: true,
        
        afterclose: function(context) {
            //$(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS, 
           
            /*
            if(!window.hWin.HAPI4.has_access() ){
                //redirects to startup page - list of all databases
                window.hWin.location  = window.HAPI4.baseURL; //startup page 
            }
            */
            
            //if not logged in - reopen login dialogue
        },
        callback:function(user_id){
console.log('callback on close saml dialog', user_id);            

            if(user_id>0){
                doAuthentication({username: user_id, password:null, saml_entity:sp_entity}, login_dialog);
            }else{
                //reopen login dialogue
                alert(user_id+'.  todo: Open login dialog');
            }

/* 
                if(context>0){


console.log('current user: ',window.hWin.HAPI4.currentUser['ugr_ID']);                            
                    
                        if(window.hWin.HAPI4.currentUser && window.hWin.HAPI4.currentUser['ugr_ID']==context){
                            
                                $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS, 
                                                            [window.hWin.HAPI4.currentUser]);

                                
                                if( window.hWin.HAPI4.SystemMgr.versionCheck() ) {
                                    //version is old 
                                    return;
                                }
                                return true;
                        }else{
                    
                        window.hWin.HAPI4.SystemMgr.sys_info(function (success) {

console.log('getting sysinfo ',success);                            
                            if (success) {
                                
console.log(window.hWin.HAPI4.currentUser);                                
console.log(window.hWin.HAPI4.sysinfo);                                
                                
                                $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS, 
                                                            [window.hWin.HAPI4.currentUser]);

                                
                                if( window.hWin.HAPI4.SystemMgr.versionCheck() ) {
                                    //version is old 
                                    return;
                                }
                                
                            }
                        });            
                                                
                        }
                }else
                if(context && context.currentUser && context.sysinfo){
                    
                        window.hWin.HAPI4.setCurrentUser(context.currentUser);
                        window.hWin.HAPI4.sysinfo = context.sysinfo;
                        
                        $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS, 
                                                            [window.hWin.HAPI4.currentUser]);

                                
                        if( window.hWin.HAPI4.SystemMgr.versionCheck() ) {
                                    //version is old 
                                    return;
                        }

                    return true;
                }else{
                    return false;
                }
*/        
    }});
    
    
}

