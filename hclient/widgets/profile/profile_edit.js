/**
* Dialog to register new user (not logged in)
* @todo replace with entity.manageSysUsers
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


$.widget( "heurist.profile_edit", {

    // default options
    options: {
        isdialog: true,

        ugr_ID:0,
        edit_data: {},
        isregistration: false,
        parentwin: null,

        needclear: true, //clear input everytime for registration
        
        is_guest: false, //guest user registration

        // callbacks
        callback:null,
        afterRegistration: null
    },

    // the widget's constructor
    _create: function() {

        if(!this.options.parentwin){
            this.options.parentwin = window.hWin;  
        }

        // prevent double click to select text
        this.element.disableSelection();

        this.edit_form = $( "<div>" ).addClass('ui-heurist-bg-light')
        .appendTo( this.element );

        this.reg_message = $( "<div>" )
        .appendTo( this.element );

        // Sets up element to apply the ui-state-focus class on focus.
       

        this._refresh();

    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash,
    // the widget is initialized; this includes when the widget is created.
    _init: function() {

        if(this.edit_form.is(':empty') ){

            let that = this;

            this.edit_form.load(window.hWin.HAPI4.baseURL+"hclient/widgets/profile/profile_edit.html?t="+(new Date().getTime()),
                function(){
                    
                    $('#divConditions').load(`${window.hWin.HAPI4.baseURL}?disclaimer=terms_and_conditions.html #content`);
                    
                    that.edit_form.css('overflow','hidden');
                    
                    //find all labels and apply localization
                    that.edit_form.find('label').each(function(){
                        $(this).html(window.hWin.HR($(this).html()));
                    });
                    let allFields = that.edit_form.find('input');

                    that.edit_form.find( "#accordion" ).accordion({collapsible: true, heightStyle: "content", active: false});

                    function __doSave(){
                        that._doSave();
                    }

                    that.options.isregistration = !(Number(that.options.ugr_ID)>0 || window.hWin.HAPI4.is_admin());
                    if(that.options.isregistration){
                        
                        if(window.hWin.HEURIST4.util.isempty(window.hWin.HAPI4.sysinfo.dbowner_email)){ //new db creation and not logged in
                            $("#contactDetails").html(window.hWin.HR('Email to')+': System Administrator '+
                                '<a href="mailto:'+window.hWin.HAPI4.sysinfo.sysadmin_email+'">'+window.hWin.HAPI4.sysinfo.sysadmin_email+'</a>');
                        }else{
                            $("#contactDetails").html(window.hWin.HR('Email database owner')+': '+window.hWin.HAPI4.sysinfo.dbowner_name+'  '+
                                '<a href="mailto:'+window.hWin.HAPI4.sysinfo.dbowner_email+'">'+window.hWin.HAPI4.sysinfo.dbowner_email+'</a>');
                        }
                        
                    }else{
                        $("#contactDetails").html('');
                    }

                    if(that.options.isdialog){

                        that.edit_form.dialog({
                            autoOpen: false,
                            height: 630,
                            width: 740,
                            modal: true,
                            resizable: true,
                            title: '',
                            buttons: [
                                {text:window.hWin.HR(that.options.isregistration?'Register':'Save'), click: __doSave, id:'btn_save'}, //, disabled:(isreg)?'disabled':''
                                {text:window.hWin.HR('Cancel'), click: function() {
                                    $( this ).dialog( "close" );
                                }}
                            ],
                            close: function() {
                                if(that.options.needclear){
                                    allFields.val( "" ).removeClass( "ui-state-error" );
                                }
                            },
                            open: function(){
                                that.enable_register(!that.options.isregistration);
                            }
                        });
                    }

                    that._init();

            });

        }else{

            this._fromDataToUI();

        }

    },

    _setOption: function( key, value ){
        if(key==='ugr_ID'){
            this.options.ugr_ID = value;
            this._init();
        }else if(key==='is_guest'){
            this.options.is_guest = value;
        }else if(key==='parentwin'){
            this.options.parentwin = value;
            if(!this.options.parentwin){
                this.options.parentwin = window.hWin;  
            }
        }
    },

    /*
    * private function
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

    },
    //
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements
       
    },

    //----
    _fromDataToUI: function(){

        let allFields = this.edit_form.find('input, textarea');

        allFields.val( "" ).removeClass( "ui-state-error" );

        /*if(this.options.ugr_ID == window.hWin.HAPI4.currentUser.ugr_ID){

        this.options.edit_data = window.hWin.HAPI4.currentUser;

        }else */
        if(Number(this.options.ugr_ID)>0){

            if(this.options.edit_data && this.options.edit_data['ugr_ID']==this.options.ugr_ID) {
                this.options.edit_data['ugr_Password']='';
            }else{
                let that = this;
                window.hWin.HAPI4.SystemMgr.user_get( { UGrpID: this.options.ugr_ID},
                    function(response){
                        let  success = (response.status == window.hWin.ResponseStatus.OK);
                        if(success){
                            that.options.edit_data = response.data;
                            if(that.options.edit_data && that.options.edit_data['ugr_ID']==that.options.ugr_ID){
                                that._fromDataToUI();
                            }else{
                                that.options.parentwin.HEURIST4.msg.showMsgErr({
                                    message: "Unexpected user data obtained from server",
                                    error_title: 'Invalid user data',
                                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                                });
                            }
                        }else{
                            that.options.parentwin.HEURIST4.msg.showMsgErr(response, true);
                        }
                    }
                );
                return;
            }

            this.edit_form.find("#ugr_Password").removeClass('mandatory');
            this.edit_form.find(".mode-registration").hide();
            this.edit_form.find(".mode-edit").show();
        }else{
            if(!this.options.edit_data) this.options.edit_data = {};
            this.edit_form.find("#ugr_Password").addClass('mandatory');
            this.edit_form.find(".mode-edit").hide();

            if(Number(this.options.ugr_ID)>0 || window.hWin.HAPI4.is_admin()){ //create new user by admin
                this.edit_form.find(".mode-registration").hide();
            }else{ //registration
                this.edit_form.find(".mode-registration").show();

                //init captcha
                this.edit_form.find('#imgdiv').show();
                /*that.edit_form.find('#btnCptRefresh')
                .button({showLabel:false, iconPosition:'end', icon:'ui-icon-refresh'})
                .show()
                .on('click', __refreshCaptcha );*/

                this._refreshCaptcha();
            }
        }

        //hide enable field
        if(window.hWin.HAPI4.is_admin()){
            this.edit_form.find(".mode-admin").show();
        }else{
            this.edit_form.find(".mode-admin").hide();
        }

        for(let id in this.options.edit_data){
            if(!window.hWin.HEURIST4.util.isnull(id)){
                this.edit_form.find("#"+id).val(this.options.edit_data[id]);
            }
        }
        //restore repeat password also
        if(this.options.edit_data['ugr_Password']!=''){
            this.edit_form.find("#password2").val(this.options.edit_data['ugr_Password']);
        }
        //always reset captcha
        this.edit_form.find('#ugr_Captcha').val('');

        this._on(this.edit_form.find('#ugr_eMail'),{'blur':function(){
            this.autofill_login( this.edit_form.find('#ugr_eMail').val() );
        }});
        this._on(this.edit_form.find('#cbAgree'),{'change':function(){
            this.enable_register( this.edit_form.find('#cbAgree').is(':checked') );
        }});
        
        if(this.options.isdialog){
            this.edit_form.dialog('option', 'title',
                Number(this.options.ugr_ID)>0 ? window.hWin.HR('Edit profile')+': '+this.options.edit_data['ugr_FirstName'] + ' ' + this.options.edit_data['ugr_LastName'] //this.options.edit_data['ugr_FullName']
                : window.hWin.HR('Registration')  );
            this.edit_form.dialog("open");
            this.edit_form.parent().addClass('ui-dialog-heurist');
           
            this.edit_form.parent().position({ my: "center center", at: "center center", of: $(top.document) });

        }
    },

    _refreshCaptcha: function(){
        let that = this;
        that.edit_form.find('#ugr_Captcha').val('');
        let $dd = that.edit_form.find('#imgdiv');
        let id = window.hWin.HEURIST4.util.random();
        const is_simple_captcha = true;
        if(is_simple_captcha){  //simple captcha
            $dd.load(window.hWin.HAPI4.baseURL+'hserv/utilities/captcha.php?id='+id);
        }else{ //image captcha
            $dd.empty();
            $('<img id="img" src="hserv/utilities/captcha.php?img='+id+'"/>').appendTo($dd);
        }
    },

    _doSave: function(){
        
        let parentWin = this.options.parentwin;

        let that = this;
        let allFields = this.edit_form.find('input, textarea');
        let err_text = '';

        // validate mandatory fields
        allFields.each(function(){
            let input = $(this);
            if(input.hasClass('mandatory') && input.val()==''){
                input.addClass( "ui-state-error" );
                err_text = err_text + ', '+that.edit_form.find('label[for="' + input.attr('id') + '"]').html();
            }
        });
        if(this.options.isregistration){
        	//remove/trim spaces
        	let ele = this.edit_form.find("#ugr_Captcha");
        	let val = ele.val().trim().replace(/\s+/g,'');
        	
            const ss = parentWin.HEURIST4.msg.checkLength2( ele, '', 1, 0 );
            if(ss!=''){
                err_text = err_text + ', Humanity check';
            }else{
				ele.val(val);
            }
        }

        if(err_text==''){
            // validate email
            // From jquery.validate.js (by joern), contributed by Scott Gonzalez: http://projects.scottsplayground.com/email_address_validation/
            let email = this.edit_form.find("#ugr_eMail");
            let bValid = parentWin.HEURIST4.util.checkEmail(email);
            if(!bValid){
                err_text = err_text + ', '+window.hWin.HR('Email does not appear to be valid');
            }

            // validate login
            let login = this.edit_form.find("#ugr_Name");
            if(!parentWin.HEURIST4.util.checkRegexp( login, /^[a-z]([0-9a-z_@.])+$/i)){
                err_text = err_text + ', '+window.hWin.HR('Login/user name should only contain ')
                    +'a-z, 0-9, _, @ and begin with a letter';   // "Username may consist of a-z, 0-9, _, @, begin with a letter."
            }else{
                const ss = parentWin.HEURIST4.msg.checkLength2( login, "user name", 3, 60 );
                if(ss!=''){
                    err_text = err_text + ', '+ss;
                }
            }
            // validate passwords
            let password = this.edit_form.find("#ugr_Password");
            let password2 = this.edit_form.find("#password2");
            if(password.val()!=password2.val()){
                err_text = err_text + ', '+window.hWin.HR(' Passwords do not match');
                password.addClass( "ui-state-error" );
            }else  if(password.val()!=''){
                /* restrict password to alphanumeric only - removed at 2016-04-29
                if(!parentWin.HEURIST4.util.checkRegexp( password, /^([0-9a-zA-Z])+$/)){  //allow : a-z 0-9
                    err_text = err_text + ', '+window.hWin.HR('Wrong password format');
                }else{*/
                const ss = parentWin.HEURIST4.msg.checkLength2( password, "password", 3, 16 );
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
            // fill data with values from UI
            allFields.each(function(){
                let input = $(this);
                if(input.attr('id').indexOf("ugr_")==0){ // that.options.edit_data[input.attr('id')]!=undefined)

                    if(input.attr('type') === "checkbox"){
                        that.options.edit_data[input.attr('id')] = input.is(':checked')?'y':'n';
                    }else{
                        that.options.edit_data[input.attr('id')] = input.val();
                    }
                }
            });

            that.options.edit_data['ugr_Type'] = 'user';
            that.options.edit_data['ugr_IsModelUser'] = (that.options.edit_data['ugr_IsModelUser']=='y')?1:0;
            that.options.edit_data['is_guest'] = that.options.is_guest?1:0;

            if( !window.hWin.HEURIST4.util.isnull(that.options.callback) && window.hWin.HEURIST4.util.isFunction(that.options.callback) ){

                that.edit_form.dialog("close");
                that.options.callback.call(that);

            }else{

                that.enable_register(false);
                
                window.hWin.HAPI4.SystemMgr.user_save( that.options.edit_data,
                    function(response){
                        that.enable_register(true);
                        let  success = (response.status == window.hWin.ResponseStatus.OK);
                        if(success){
                            if(that.options.isdialog){
                                that.edit_form.dialog("close");
                                if(that.options.isregistration){
                                    if(that.options.is_guest && window.hWin.HEURIST4.util.isFunction(that.options.afterRegistration)){
                                        that.options.afterRegistration.call(that, response);
                                    }else{
                                        parentWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL+"hclient/widgets/profile/profile_regmsg.html?t="+(new Date().getTime()),null,'Confirmation');
                                    }
                                }else{
                                    parentWin.HEURIST4.msg.showMsgDlg("User information saved");
                                }

                            }
                        }else{
                            parentWin.HEURIST4.msg.showMsgErr(response, !that.options.isregistration);
                            if(that.options.isregistration){
                                that.edit_form.find("#ugr_Captcha").val('');
                                that._refreshCaptcha();
                            }
                        }
                    }
                );

            }

        }else{
            parentWin.HEURIST4.msg.showMsgErr({
                message: err_text,
                error_title: 'Missing required fields',
                status: window.hWin.ResponseStatus.INVALID_REQUEST
            });
            /*let message = $dlg.find('.messages');
            message.html(err_text).addClass( "ui-state-highlight" );
            setTimeout(function() {
            message.removeClass( "ui-state-highlight", 1500 );
            }, 500 );*/
        }

    },

    open:function(){
        this.edit_form.dialog("open");
    },

    //these functions is used in edit_profile.html
    autofill_login: function (value){
        let ele = this.edit_form.find('#ugr_Name');
        if(ele && ele.val()==''){
            ele.val(value);
        }
    },
    
    enable_register: function (is_enabled){
        let ele = this.edit_form.parent().find('#btn_save');
        window.hWin.HEURIST4.util.setDisabled(ele, !is_enabled);
    }
    

});

