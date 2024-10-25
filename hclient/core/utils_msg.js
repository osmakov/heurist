/**
*  Message and popup functions
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

if (!window.hWin.HEURIST4){
    window.hWin.HEURIST4 = {};
}

/*
showMsgErrJson - check if response is json 
showMsgErr     -    loads into id=dialog-error-messages

getMsgDlg      - creates and returns div (id=dialog-common-messages) that is base element for jquery ui dialog
getPopupDlg    - creates and returns div (id=dialog-popup) similar to  dialog-common-messages - but without width limit

showMsg        - NEW MAIN 
showMsgDlg     - MAIN 
showMsg_ScriptFail - shows standard error message on dynamic script loading
showPrompt    - show simple input value dialog with given prompt message
    
showMsgFlash - show buttonless dialog with given timeout
showHintFlash
checkLength  - fill given element with error message and highlight it
checkLength2 - get message if input value beyound given ranges

showMsgDlgUrl  - loads content url (not frame!) into dialog (getMsgDlg) and show it (showMsgDlg)

showDialog - creates div with frame, loads url content into it and shows it as popup dialog, on close this div will be removed
showElementAsDialog

createAlertDiv - return error-state html with alert icon 

bringCoverallToFront - show loading icon and lock all screen (curtain)
sendCoverallToBack

showDialogInDiv - show diaog content inline for h6 ui

*/
if (! window.hWin.HEURIST4.msg) window.hWin.HEURIST4.msg = {

    showMsgErrJson: function(response){
        if(typeof response === "string"){
            window.hWin.HEURIST4.msg.showMsgErr(null);
        }else{
            window.hWin.HEURIST4.msg.showMsgErr(window.hWin.HR('Error_Json_Parse')+': '+response.substr(0,255)+'...');
        }
    },

    showMsgErr: function(response, needlogin, ext_options){
        let msg = '';
        let dlg_title = null;
        let show_login_dlg = false;
        
        window.hWin.HEURIST4.msg.sendCoverallToBack(true);
        window.hWin.HEURIST4.msg.closeMsgFlash();
        
        if(typeof response === "string"){
            msg = response;
        }else{
            let request_code = null;
            
            if($.isPlainObject(response.message)){
                    response = response.message;
            }
            
            if(window.hWin.HEURIST4.util.isempty(response.message) || response.message.trim().toLowerCase() == 'error'){

                msg = 'Error_Empty_Message';
                if(response){
                    if(response.status==window.hWin.ResponseStatus.REQUEST_DENIED ){
                        msg = '';
                    }else if (!window.hWin.HEURIST4.util.isempty(response.request_code)){
                        request_code = response.request_code;    
                    }
                }
            }else{
                msg = response.message;
            }
            msg = window.hWin.HR(msg);

            dlg_title = response.error_title?response.error_title:'';

            if(response.sysmsg && response.status!=window.hWin.ResponseStatus.REQUEST_DENIED){
                //sysmsg for REQUEST_DENIED is current user id - it allows to check if session is expired
                msg = msg + '<br><br>System error:<br>';
                if(typeof response.sysmsg['join'] === "function"){
                    msg = msg + response.sysmsg.join('<br>');
                }else{
                    msg = msg + response.sysmsg;
                }

            }
            if(response.status==window.hWin.ResponseStatus.SYSTEM_FATAL
            || response.status==window.hWin.ResponseStatus.SYSTEM_CONFIG){

                let def_title = window.hWin.ResponseStatus.SYSTEM_CONFIG ? 'System misconfiguration' : 'Fatal error';
                dlg_title = window.hWin.HEURIST4.util.isempty(dlg_title) ? def_title : dlg_title;
                msg = msg + '<br><br>'+window.hWin.HR('Error_System_Config');

            }else if(response.status==window.hWin.ResponseStatus.INVALID_REQUEST){

                dlg_title = window.hWin.HEURIST4.util.isempty(dlg_title) ? 'Invalid request made' : dlg_title;

                msg = msg + '<br><br>' + window.hWin.HR('Error_Wrong_Request') 
                    +'<br><br>' + window.hWin.HR('Error_Report_Team').replace('#sysadmin_email#', window.hWin.HAPI4.sysinfo.sysadmin_email);

            }else if(response.status==window.hWin.ResponseStatus.REQUEST_DENIED){
                
                if(msg!='') msg = msg + '<br><br>';
                
                if(window.hWin && window.hWin.HAPI4){
                    dlg_title = 'Login required to access '+window.hWin.HAPI4.database;  
                    response.sysmsg = (window.hWin.HAPI4.currentUser['ugr_ID']==0)?0:1;
                }else{
                    dlg_title = 'Access denied';
                }

                if(msg=='' || (needlogin && response.sysmsg==0)){
                    msg = msg + top.HR('Session expired');
                    show_login_dlg = true;
                }else if(response.sysmsg==0){
                    msg = msg + 'You must be logged in';  
                }else{ 
                    dlg_title = 'Access denied';
                    msg = msg + 'This action is not allowed for your current permissions';    
                } 
                
            }else if(response.status==window.hWin.ResponseStatus.DB_ERROR){
                dlg_title = window.hWin.HEURIST4.util.isempty(dlg_title) ? 'Database error' : dlg_title;
                msg = msg + '<br><br>'+window.hWin.HR('Error_Report_Team').replace('#sysadmin_email#', window.hWin.HAPI4.sysinfo.sysadmin_email);
            }else  if(response.status==window.hWin.ResponseStatus.ACTION_BLOCKED){
                // No enough rights or action is blocked by constraints
                dlg_title = window.hWin.HEURIST4.util.isempty(dlg_title) ? 'Action blocked' : dlg_title;
            }else  if(response.status==window.hWin.ResponseStatus.NOT_FOUND){
                // The requested object not found.
                dlg_title = window.hWin.HEURIST4.util.isempty(dlg_title) ? 'Request not found' : dlg_title;
            }else if(response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                // An unknown/un-handled error
                dlg_title = window.hWin.HEURIST4.util.isempty(dlg_title) ? 'An unknown error has occurred' : dlg_title;
                msg += '<br><br>'+window.hWin.HR('Error_Report_Team').replace('#sysadmin_email#', window.hWin.HAPI4.sysinfo.sysadmin_email);
            }
            
            if(request_code!=null){
                msg = msg + '<br>'+window.hWin.HR('Error_Report_Code')+': "'
                    +(request_code.script+' '
                    +(window.hWin.HEURIST4.util.isempty(request_code.action)?'':request_code.action)).trim()+'"';
            }
        }
        
        if(window.hWin.HEURIST4.util.isempty(msg) || msg.trim().toLowerCase() == 'error'){
            msg = window.hWin.HR('Error_Empty_Message');
        }
        if(window.hWin.HEURIST4.util.isempty(dlg_title)){
            dlg_title = 'Error_Title';
        }
        dlg_title = window.hWin.HEURIST4.util.isFunction(window.hWin.HR)?window.hWin.HR(dlg_title):'Heurist';

        let buttons = {};
        buttons[window.hWin.HR('OK')]  = function() {
                    let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
                    $dlg.dialog( "close" );
                    if(show_login_dlg){
                            window.hWin.HAPI4.setCurrentUser(null);
                            $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS);
                    }
                }; 
                
        if(!ext_options) ext_options = {};
        //else if(!ext_options['default_palette_class']) 
        ext_options['default_palette_class'] = 'ui-heurist-error';
                
        window.hWin.HEURIST4.msg.showMsgDlg(msg, buttons, dlg_title, ext_options);
       
        return msg; 
    },

    //
    // loads content url into dialog (getMsgDlg) and show it (showMsgDlg)
    //
    showMsgDlgUrl: function(url, buttons, title, options){

        let $dlg;
        if(url){
            let isPopupDlg = (options && (options.isPopupDlg || options.container));
            $dlg = isPopupDlg
                            ?window.hWin.HEURIST4.msg.getPopupDlg( options.container )
                            :window.hWin.HEURIST4.msg.getMsgDlg();
            $dlg.load(url, function(){
                window.hWin.HEURIST4.msg.showMsgDlg(null, buttons, title, options);

                if(options && options.use_doc_title){
                    $dlg.dialog('option', 'title', $dlg.find('title').text());
                }
            });
        }
        return $dlg;
    },
    
    //
    //  shows message if loading of js fail
    //
    showMsg_ScriptFail: function( isdlg, message ){

        if(window.hWin.HEURIST4.util.isempty(message)){
            message = "this feature";
        }

        message = 
'Unfortunately we have encountered a program error. Please report this to us using ' 
+'the bug reporter under Help at the top right of the main screen, or via email '
+'to support@heuristnetwork.org so that we can fix it immediately.' 

+'<br><br>Please remember to tell us the context in which this occurred. '
+'A screenshot including the URL is very useful.';
        
        if(isdlg){
            window.hWin.HEURIST4.msg.showMsgDlg(message, null, "Work in Progress");    
        }else{
            window.hWin.HEURIST4.msg.showMsgFlash(message, 4000, {title:'Work in Progress',height:160});
        }
        
    },

    //
    // show simple input value dialog with given message
    //  message - either plain text or html with #dlg-prompt-value input element
    //
    showPrompt: function(message, callbackFunc, sTitle, ext_options){
        
        if(message.indexOf('dlg-prompt-value')<0){
            message = message+'<input id="dlg-prompt-value" class="text ui-corner-all" '
                + (ext_options && ext_options['password']?'type="password"':'') 
                + ' style="max-width: 250px; min-width: 10em; width: 250px; margin-left:0.2em"/>';    
        }

        let dlg_id = ext_options && ext_options['dialogId'] ? ext_options['dialogId'] : 'dialog-common-messages';

        return window.hWin.HEURIST4.msg.showMsgDlg( message,
        function(){
            if(window.hWin.HEURIST4.util.isFunction(callbackFunc)){
                let $dlg = window.hWin.HEURIST4.msg.getMsgDlg(dlg_id);      
                let ele = $dlg.find('#dlg-prompt-value');
                let val = '';
                if(ele.attr('type')!='checkbox' || ele.is(':checked')){
                    val =  ele.val();
                }
                callbackFunc.call(this, val);
            }
        },
        window.hWin.HEURIST4.util.isempty(sTitle)?'Specify value':sTitle, ext_options);
        
    },
    
    //
    // creates and returns div (id=dialog-common-messages) that is base element for jquery ui dialog
    //
    getMsgDlg: function(dialogId){

        if(window.hWin.HEURIST4.util.isempty(dialogId)){
            dialogId = "dialog-common-messages";
        }else if(dialogId[0] == '#'){
            dialogId = dialogId.slice(0, 1);
        }

        let $dlg = $( "#" + dialogId );
        if($dlg.length==0){
            $dlg = $('<div>',{id: dialogId})
                .css({'min-wdith':'380px','max-width':'640px'}) //,padding:'1.5em 1em'
                .appendTo( $('body') );
        }
        return $dlg.removeClass('ui-heurist-border');
    },

    getMsgFlashDlg: function(){
        let $dlg = $( "#dialog-flash-messages" );
        if($dlg.length==0){
            $dlg = $('<div>',{id:'dialog-flash-messages'}).css({'min-wdith':'380px','max-width':'640px'})
                .appendTo('body'); //$(window.hWin.document)
        }
        return $dlg.removeClass('ui-heurist-border');
    },
    
    //
    // creates and returns div (id=dialog-popup) similar to  dialog-common-messages - but without width limit
    //
    getPopupDlg: function(element_id){        
        if(!element_id) element_id = 'dialog-popup';
        let $dlg = $( '#'+element_id );
        if($dlg.length==0){
            $dlg = $('<div>',{id:element_id})
                .css({'padding':'2em','min-wdith':'380px',overflow:'hidden'}).appendTo('body');
            $dlg.removeClass('ui-heurist-border');
        }
        return $dlg;
    },

    
    //
    //
    //
    showTooltipFlash: function(message, timeout, to_element){
        
        if(!window.hWin.HEURIST4.util.isFunction(window.hWin.HR)){
            alert(message);
            return;
        }
        
        if(window.hWin.HEURIST4.util.isempty(message) ||  window.hWin.HEURIST4.util.isnull(to_element)){
            return;   
        }
        
        let position;
        
        if($.isPlainObject(to_element)){
                position = { my:to_element.my, at:to_element.at};
                to_element =  to_element.of;
        }else{
                position = { my: "left top", at: "left bottom", of: $(to_element) };    
        }

        if (!(timeout>200)) {
            timeout = 1000;
        }
        
        $( to_element ).attr('title',window.hWin.HR(message));
        $( to_element ).tooltip({
            position: position,
            //content: '<span>'+window.hWin.HR(message)+'</span>',
            hide: { effect: "explode", duration: 500 }
        });

        $( to_element ).tooltip('open');
        
        setTimeout(function(){
            $( to_element ).tooltip('close');
            $( to_element ).attr('title',null);
        }, timeout);
        
    },

    //
    // show buttonless dialog with given timeout
    //
    showMsgFlash: function(message, timeout, options, position_to_element){

        if(!window.hWin.HEURIST4.util.isFunction(window.hWin.HR)){
            alert(message);
            return;
        }

        if(!$.isPlainObject(options)){
             options = {title:options};
        }
        
        let $dlg = window.hWin.HEURIST4.msg.getMsgFlashDlg();

        if(message==null){
            return;
        }

        $dlg.empty();
        let content = $('<span>'+window.hWin.HR(message)+'</span>')
                    .css({'overflow':'hidden','font-weight':'bold','font-size':'1.2em'});
                    
        $dlg.append(content);
        
        let hideTitle = (options.title==null);
        if(options.title){
            options.title = window.hWin.HR(options.title);
        }

        $.extend(options, {
            resizable: false,
            width: 'auto',
            modal: false,
            //height: 80,
            buttons: {}
        });

        if(position_to_element){
           if($.isPlainObject(position_to_element)){
                options.position = position_to_element.position;
           }else{
                options.position = { my: "left top", at: "left bottom", of: $(position_to_element) };    
           } 
        }else{
            options.position = { my: "center center", at: "center center", of: $(document) };    
        }
        options.position.collision = 'none'; //FF fix

        $dlg.dialog(options);
        
        if(!(options.height>0)){
            let height = $(content).height()+90;
           
            $dlg.dialog({height:height});
        }
           
        
        content.position({ my: "center center", at: "center center", of: $dlg });
        
        if(hideTitle){
            $dlg.parent().find('.ui-dialog-titlebar').hide();
        }
    
        $dlg.parent().css({background: '#7092BE', 'border-radius': "6px", 'border-color': '#7092BE !important',
                    'outline-style':'none', outline:'none'})
        $dlg.css({color:'white', border:'none', overflow:'hidden' });
        
        if(timeout!==false){

            if (!(timeout>200)) {
                timeout = 1000;
            }
            setTimeout(window.hWin.HEURIST4.msg.closeMsgFlash, timeout);
            
        }
    },
    
    closeMsgFlash: function(){
        
        if(window.hWin.HEURIST4.msg.coverallKeep===true) return;
        
        let $dlg = window.hWin.HEURIST4.msg.getMsgFlashDlg();
        if($dlg.dialog('instance')) $dlg.dialog('close');
        $dlg.parent().find('.ui-dialog-titlebar').show();
    },

    //@todo - redirect to error page
    redirectToError: function(message){
        window.hWin.HEURIST4.msg.showMsgDlg(message, null, 'Error');
    },

    //
    // fill given element with error message and highlight it
    // message - error message that overrides default message
    //
    checkLength: function( input, title, message, min, max ) {
        let message_text = window.hWin.HEURIST4.msg.checkLength2( input, title, min, max );
        if(message_text!=''){
                                          
                                          //'<div class="ui-state-error" style="padding:10px">XXXX'+        +'</div>'
                                          
                                          //class="ui-state-error" 
            if(!window.hWin.HEURIST4.util.isempty(message)) message_text = message;
             
            window.hWin.HEURIST4.msg.showMsgFlash('<span style="padding:10px;border:0;">'+message_text+'</span>', 3000);
            
            /*
            if(message){
                message.text(message_text);
                message.addClass( "ui-state-error" );
                setTimeout(function() {
                    message.removeClass( "ui-state-error", 1500 );
                    }, 3500 );
            } */

            return false;
        }else{
            return true;
        }

    },

    //
    // get message if input value beyound given ranges
    //
    checkLength2: function( input, title, min, max ) {

        let len = input.val().length;
        let message_text = '';

        if ( (max>0 &&  len > max) || len < min ) {
            input.addClass( "ui-state-error" );
            if(max>0 && min>1){
                message_text = window.hWin.HR(title)+" "+window.hWin.HR("length must be between ") +
                min + " "+window.hWin.HR("and")+" " + max + ". ";
                if(len>=min){
                    message_text = message_text + (len-max) + window.hWin.HR(" characters over");
                }

            }else if(min>1){
               message_text = window.hWin.HR(title)+" "+window.hWin.HR('. At least '+min+' characters required'); 
            }else if(min==1){
                message_text = window.hWin.HR(title)+" "+window.hWin.HR(" is required field");
            }

            return message_text;

        } else {
            input.removeClass( "ui-state-error" );
            return '';
        }
    },

    
    /**
    * show url in iframe within popup dialog
    * 
    * options
    *   dialogid - unique id to reuse dialog  (in this case dialog will not be removed on close)
    *   force_reload - to reload iframe contenr if dialogid is define dialog div is preserved
    * 
    *   is_h6style - apply heurist6 style
    *   position - adjust dialog position
    *   maximize - set maximum allowed widht and height  
    *   default_palette_class - color scheme class
    *   window - opener
    *   params - to be send to iframe via function assignParameters
    * 
    *   onpopupload - function that will be called on iframe load complete
    *   callback - called on iframe close event, if it returns true dialog will be closes
    *   afterclose - on close dialog event (for iframe close event see "callback")
    * 
    *   modal - is dialog modal
    *   onmouseover - event listener 
    *   default_palette_class - dialog css class
    *   padding - dialog padding (around iframe)
    *   allowfullscreen
    *   noClose - hide close button on dialog titlebar
    *   borderless - hide titlebae and border for dialog
    *   title
    *   width, height
    * 
    *   on frame load it calls onFirstInit function within iframe
    */
    showDialog: function(url, options){

        if(!options) options = {};

        if(options.container){
            window.hWin.HEURIST4.msg.showDialogInDiv(url, options);
            return;
        }


        if(!options.title) options.title = ''; // removed 'Information'  which is not a particualrly useful title

        let opener = options['window']?options['window'] :window;

        //.appendTo( that.document.find('body') )
        let $dlg = [];

        if(options['dialogid']){
            $dlg = $(opener.document).find('body #'+options['dialogid']);
        }
        
        
        let $dosframe;
        
        
        function __canAccessIframe(iframe) {
          try {
            return Boolean(iframe.contentDocument);
          }
          catch(e){
            return false;
          }
        }        

        if($dlg.length>0){
            //reassign dialog onclose and call new parameters
            
            $dlg.dialog('open');  
            
            $dosframe = $dlg.find('iframe');
            if(__canAccessIframe($dosframe[0]))
            {
                let content = $dosframe[0].contentWindow;
                
                //close dialog from inside of frame - need redifine each time
                content.close = function() {
                    let rval = true;
                    let closeCallback = options['callback'];
                    if(window.hWin.HEURIST4.util.isFunction(closeCallback)){
                        rval = closeCallback.apply(opener, arguments);
                    }
                    if ( rval===false ){ //!rval  &&  rval !== undefined){
                        return false;
                    }
                    $dlg.dialog('close');
                    return true;
                };

                // if content in iframe has function "assignParameters" we may pass parameters
                if(options['params'] && window.hWin.HEURIST4.util.isFunction(content.assignParameters)) {
                    content.assignParameters(options['params']);
                }
            }
            if(options['height']>0 && options['width']>0){
                 $dlg.dialog('option', 'width', options['width']);    
                 $dlg.dialog('option', 'height', options['height']);    
            }

            if($dosframe.attr('src')!=url || options['force_reload']){ // hide previous content
                $dosframe.hide();
                $dlg.addClass('loading');
            }

        }else{

           
            $dlg = $('<div>')
            .addClass('loading')
            .appendTo( $(opener.document).find('body') );

            if(options['dialogid']){
                $dlg.attr('id', options['dialogid']);
            }else{
                $dlg.uniqueId();
            }


            if(options.class){
                $dlg.addClass(options.class);
            }

            $dosframe = $( "<iframe>").attr('parent-dlg-id', $dlg.attr('id'))
            .css({border:'none', overflow: 'none !important', width:'100% !important'}).appendTo( $dlg );
            
            if(options['allowfullscreen']){
                $dosframe.attr('allowfullscreen',true);
                $dosframe.attr('webkitallowfullscreen',true);
                $dosframe.attr('mozallowfullscreen',true);
                
               
            }

            $dosframe.hide();
            //callback function to resize dialog from internal frame functions
            $dosframe[0].doDialogResize = function(width, height) {
                
                /*
                let body = $(this.document).find('body');
                let dim = { h: Math.max(400, body.innerHeight()-10), w:Math.max(400, body.innerWidth()-10) };

                if(width>0)
                $dlg.dialog('option','width', Math.min(dim.w, width));
                if(height>0)
                $dlg.dialog('option','height', Math.min(dim.h, height));
                */    
            };

            //on load content event listener
            $dosframe.on('load', function(){
                if(window.hWin.HEURIST4.util.isempty($dosframe.attr('src'))){
                    return;
                }

                window.hWin.HEURIST4.msg.sendCoverallToBack();

                let has_access = __canAccessIframe($dosframe[0]);
                
                if(has_access)
                {
                    
                    let content = $dosframe[0].contentWindow;
                    try{
                        //replace standard "alert" to Heurist dialog    
                        content.alert = function(txt){
                            let $dlg_alert = window.hWin.HEURIST4.msg.showMsgDlg(txt, null, ""); // Title was an unhelpful and inelegant "Info"
                            $dlg_alert.dialog('open');
                            return true;
                        }
                    }catch(e){
                        console.error(e);
                    }

                    if(!options["title"]){
                        $dlg.dialog( "option", "title", content.document.title );
                    }  

                    /*
                    content.confirm = function(txt){
                    var resConfirm = false,
                    isClosed = false;

                    var $confirm_dlg = window.hWin.HEURIST4.msg.showMsgDlg(txt, function(){
                    resConfirm = true;
                    }, "Confirm");

                    $confirm_dlg.dialog('option','close',
                    function(){
                    isClosed = true;        
                    });

                    while(!isClosed){
                    $.wait(1000);
                    }

                    return resConfirm;
                    }*/

                   
                   
                    //functions in internal document
                    //content.close = $dosframe[0].close;    // make window.close() do what we expect

                    //close dialog from inside of frame
                    content.close = function() {
                        let rval = true;
                        let closeCallback = options['callback'];
                        if(window.hWin.HEURIST4.util.isFunction(closeCallback)){
                            rval = closeCallback.apply(opener, arguments);
                        }
                        if ( rval===false ){ //!rval  &&  rval !== undefined){
                            return false;
                        }
                        $dlg.dialog('close');
                        return true;
                    };

                   
                    content.doDialogResize = $dosframe[0].doDialogResize;

                }
                
                $dlg.removeClass('loading');
                $dosframe.show();    

                let onloadCallback = options['onpopupload'];
                if(onloadCallback){
                    onloadCallback.call(opener, $dosframe[0]);
                }

                if(has_access){
                    let content = $dosframe[0].contentWindow;

                    if(window.hWin.HEURIST4.util.isFunction(content.onFirstInit)) {  //see mapPreview
                        content.onFirstInit();
                    }
                    //pass parameters to frame 
                    if(options['params'] && window.hWin.HEURIST4.util.isFunction(content.assignParameters)) {
                        content.assignParameters(options['params']);
                    }
                }
                
                if(options['onmouseover']){ 
                    $dlg.parent().find('.ui-dialog-titlebar').mouseover(function(){
                        options['onmouseover'].call();
                    });
                    $dosframe.mouseover(function(){
                        options['onmouseover'].call();
                    });
                }
                

            });

            options.width = window.hWin.HEURIST4.msg._setDialogDimension(options, 'width');
            options.height = window.hWin.HEURIST4.msg._setDialogDimension(options, 'height');
            
            let opts = {
                autoOpen: true,
                width : options.width,
                height: options.height,
                modal: (options['modal']!==false),
                resizable: (options.resizable!==false),
                draggable: (options.draggable!==false),
                title: options["title"],
                resizeStop: function( event, ui ) {
                    $dosframe.css('width','100%');
                },
                closeOnEscape: options.closeOnEscape,
                beforeClose: options.beforeClose,
                close: function(event, ui){
                    let closeCallback = options['afterclose'];
                    if(window.hWin.HEURIST4.util.isFunction(closeCallback)){
                        closeCallback.apply();
                    }
                    if(!options['dialogid']){
                        $dlg.remove();
                    }
                },
                open: options.onOpen
            };
            $dlg.dialog(opts);
            
            if($dlg.attr('data-palette'))
                $dlg.parent().removeClass($dlg.attr('data-palette'));
            if(options.default_palette_class){
                $dlg.attr('data-palette', options.default_palette_class);
                $dlg.parent().addClass(options.default_palette_class);
            }else{
                $dlg.attr('data-palette', null);
            }
            
            $dlg.parent().find(".ui-dialog-title").html(options["title"]);
            $dlg.parent().find('.ui-dialog-content').css({'overflow':'hidden'});

            if(options.noClose){
                $dlg.parent().find('.ui-dialog-titlebar').find('.ui-icon-closethick').parent().hide();
            }


            if(!window.hWin.HEURIST4.util.isempty(options['padding'])){ //by default 2em
                $dlg.css('padding', options.padding);
            }
            if(!window.hWin.HEURIST4.util.isempty(options['padding-content'])){ 
                $dlg.parent().find('.ui-dialog-content').css('padding', options['padding-content']);
            }
            
            if(!options.is_h6style && options.maximize){
                        function __maximizeOneResize(){
                            let dialog_height = window.innerHeight;
                            $dlg.dialog( 'option', 'height', dialog_height);
                            $dlg.dialog( 'option', 'width', '100%'); //dialog_width
                        }
                        //$(window).resize(__maximizeOneResize)
                        __maximizeOneResize();
            }     
            if(options.borderless){
                $dlg.css('padding',0);
                $dlg.parent() //s(".ui-dialog")
                      .css("border", "0 none")
                      .find(".ui-dialog-titlebar").remove();
            }

        }


        if(options.is_h6style)
        {

            $dlg.parent().addClass('ui-dialog-heurist ui-heurist-explore');

            if(options.container){

                $dlg.dialog( 'option', 'position', 
                    { my: "left top", at: "left top", of:options.container, collision:'none'});

                function __adjustOneResize(e){
                    let ele = e ?$(e.target) :options.container;

                    let dialog_height = ele.height(); 
                    $dlg.dialog( 'option', 'height', dialog_height);
                    let dialog_width = ele.width(); 
                    $dlg.dialog( 'option', 'width', dialog_width);
                }
                //$(window).on('onresize',__adjustOneResize)
                options.container.off('resize');
                options.container.on('resize', __adjustOneResize);
                __adjustOneResize();

            }else
                if(options.position){
                    $dlg.dialog( 'option', 'position', options.position );   
                    if(options.maximize){
                        function __maximizeOneResize(){
                            let dialog_height = window.innerHeight - $dlg.parent().position().top - 5;
                            $dlg.dialog( 'option', 'height', dialog_height);
                            let dialog_width = window.innerWidth - $dlg.parent().position().left - 5;
                            $dlg.dialog( 'option', 'width', dialog_width);
                        }
                        //$(window).resize(__maximizeOneResize)
                        __maximizeOneResize();
                    }else{
                        if($dlg.parent().position().left<0){
                            $dlg.parent().css({left:0});
                        }else{
                            let max_width = window.innerWidth - $dlg.parent().position().left - 5;
                            let dlg_width = $dlg.dialog( 'option', 'width');
                            if(max_width<380 || $dlg.parent().position().left<0){
                                $dlg.parent().css({left:0});
                                $dlg.dialog( 'option', 'width', 380);    
                            }else if(dlg_width>max_width){
                                $dlg.dialog( 'option', 'width', max_width);    
                            }
                        }
                    }
                }
        }

        //start content loading
        if(url!='' && ($dosframe.attr('src')!=url || options['force_reload'])){
            if(options.coverMsg){
                window.hWin.HEURIST4.msg.bringCoverallToFront($dlg, {'font-size': '16px', color: 'white'}, options.coverMsg); 
            }
            $dosframe.attr('src', url);
        }

    },

    //
    // h6 style do not popup dialog - show iframe inline
    //
    showDialogInDiv: function(url, options){

            if(!options) options = {};

            if(!options.title) options.title = '&nbsp;';
            
            let $container = options['container'];
            
            let $dosframe = $container.find('iframe');
            let _innerTitle = $container.children('.ui-heurist-header');
            let frame_container = $container.children('.ent_content_full');
            let $info_button;
               
            if($dosframe.length==0)
            {
                $container.empty();    
                //add h6 style header    
                _innerTitle = $('<div>').addClass('ui-heurist-header')
                        .appendTo($container);

                frame_container = $('<div>').addClass('ent_content_full')
                        .css({'top':'37px','bottom':'1px', 'overflow':'hidden'})
                        .appendTo($container);

                
                $dosframe = $( "<iframe>")
                            .css({height:'100%', width:'100%'})
                            .appendTo( frame_container );
            }else{
                if($dosframe.attr('src')==url){ //not changed
                    $container.show();
                    return $dosframe;        
                }
            }

            _innerTitle.empty();
            if(options.title){
                _innerTitle.text(options['title']);
                _innerTitle.show();
                frame_container.css('top','37px');
            }else{
                _innerTitle.text('');
                _innerTitle.hide();
                frame_container.css('top','0px');
            }
            
            function __onDialogClose() {
                
                let canClose = true;
                if(window.hWin.HEURIST4.util.isFunction(options['beforeClose'])){
                    canClose = options['beforeClose'].call( $dosframe[0], arguments );
                }
                if(canClose===false){
                    return false;
                }else{
                    $container.hide();
                    if(window.hWin.HEURIST4.util.isFunction(options['afterClose'])){
                        canClose = options['afterClose'].call( $dosframe[0], arguments );
                    }
                    return true;
                }
            };

            //init close button     
            $('<button>').button({icon:'ui-icon-closethick',showLabel:false, title:'Close'}) 
                                    //classes:'ui-corner-all ui-dialog-titlebar-close'})
                     .css({'position':'absolute', 'right':'4px', 'top':'6px', height:24, width:24})
                     .appendTo(_innerTitle)
                     .on({click:function(){
                         __onDialogClose();
                     }});
                     
            //init help button     
            if( options["context_help"] && window.hWin.HEURIST4.ui ){
                    
                    $info_button = $('<button>')
                            .button({icon:'ui-icon-circle-help', showLabel:false, label:'Help'})
                            .addClass('ui-helper-popup-button')
                            .css({'position':'absolute', 'right':'34px', 'top':'6px', height:24, width:24})
                            .appendTo(_innerTitle);
                    
                    window.hWin.HEURIST4.ui.initHelper({
                            button:$info_button, 
                            url: window.hWin.HRes(options['context_help']),
                            position:{my:'right top', at:'right top', of:$container},
                            container: $container,
                            is_open_at_once: options['show_help_on_init']===false ? false : true
                    });
            }else{
                //hide helper div
                frame_container.css({right:1});
                $container.children('.ui-helper-popup').hide();
            }
                
            if(navigator.userAgent.indexOf('Firefox')<0){            
                $dosframe.hide();
            }
            
            frame_container.addClass('loading');
            
            //callback function to resize dialog from internal iframe functions
           

            $dosframe.off('load');
            //on load content event listener
            $dosframe.on('load', function(){
                         
                if(window.hWin.HEURIST4.util.isempty($dosframe.attr('src'))){
                    return;
                }
                    
                let content = $dosframe[0].contentWindow;
                
                //replace native alert 
                try{
                    content.alert = function(txt){
                        let $dlg_alert = window.hWin.HEURIST4.msg.showMsgDlg(txt, null, ""); // Title was an unhelpful and inelegant "Info"
                        $dlg_alert.dialog('open');
                        return true;
                }
                }catch(e){
                    console.error(e);
                }
                
                frame_container.removeClass('loading');
                $dosframe.show();    
                
                //close dialog from inside of frame
                content.close = __onDialogClose;
                
               
                if(window.hWin.HEURIST4.util.isFunction(options['doDialogResize'])){
                    content.doDialogResize = options['doDialogResize'];
                } 
                if(window.hWin.HEURIST4.util.isFunction(options['onContentLoad'])){
                    options['onContentLoad'].call(this, $dosframe[0]);
                } 
                       
                //pass params into iframe
                if(options['params'] && window.hWin.HEURIST4.util.isFunction(content.assignParameters)) {
                    content.assignParameters(options['params']);
                }
                
            }); //onload

                        
            if(!window.hWin.HEURIST4.util.isempty(options['padding'])){
                //by default 2em
                $container.css('padding', options.padding);
            } 
                    
            //start content loading
            $dosframe.attr('src', url);
                        
           
    },   
    
    //
    //
    //
    _setDialogDimension: function(options, axis){
        
            let opener = options['window']?options['window'] :window;
        
            let wp = 0;
            
            if(axis=='width'){
                wp = (opener && opener.innerWidth>0)? opener.innerWidth
                    :(window.hWin?window.hWin.innerWidth:window.innerWidth);
            }else{
                wp = (opener && opener.innerHeight>0)? opener.innerHeight
                    :(window.hWin?window.hWin.innerHeight:window.innerHeight);
            }
            
            let res;

            if(typeof options[axis]==='string'){
                let isPercent = (options[axis].indexOf('%')>0);
                
                res = parseInt(options[axis], 10);
                
                if(isPercent){
                    res = wp*res/100;
                }
            }else{
                res = options[axis];
            }
            
            if(isNaN(res) || res<100){
                res = (axis=='width')?640:480;
            } 
            
            if(res > wp){
                res = wp;
            }
            
            return res;
        
    },
    
    //
    // take element and assign it to dialog, on dialog close place element back to original parent
    // and dialog object will be destroed
    //
    showElementAsDialog: function(options){

            let opener = options['window']?options['window'] :window;

            let $dlg = $('<div>')
               .appendTo( $(opener.document).find('body') );

            let element = options['element'];
            let originalParentNode = element.parentNode;
            originalParentNode.removeChild(element);

            $(element).show().appendTo($dlg);

            let dimW = window.hWin.HEURIST4.msg._setDialogDimension(options, 'width');
            let dimH = window.hWin.HEURIST4.msg._setDialogDimension(options, 'height');
            
            let onCloseCalback = (options['close'])?options.close:null;
            
            let opts = {
                    autoOpen:(options['autoOpen']!==false),
                    width : dimW,
                    height: dimH,
                    modal: (options.modal!==false),
                    resizable: (options.resizable!==false),
                    //draggable: false,
                    title: options["title"],
                    buttons: options["buttons"],
                    open: options.open,  //callback
                    beforeClose: options.beforeClose,
                    close: function(event, ui){

                        let need_remove = true;                        
                        if(window.hWin.HEURIST4.util.isFunction(onCloseCalback)){
                             need_remove = onCloseCalback.call(this, event, ui);
                        }
                        
                        if(need_remove!==false){
                            element.parentNode.removeChild(element);
                            element.style.display = "none";
                            originalParentNode.appendChild(element);

                            $dlg.remove();
                        }
                    }
            };
            if(options["position"]) opts["position"] = options["position"];
            
            $dlg.dialog(opts);

            if(options.borderless){
                $dlg.css('padding',0);
                $dlg.parent() //s(".ui-dialog")
                      .css("border", "0 none")
                      .find(".ui-dialog-titlebar").remove();
            }
            
            if(options.default_palette_class){
                $dlg.attr('data-palette', options.default_palette_class);
                $dlg.parent().addClass(options.default_palette_class);
            }else {
                $dlg.attr('data-palette', null);
                if (options.h6style_class){
                    $dlg.parent().addClass('ui-dialog-heurist '+options.h6style_class);
                }
            }

            
            
            return $dlg;
    },

    //
    //
    //
    createAlertDiv: function(msg){
        
        return '<div class="ui-state-error" style="width:90%;margin:auto;margin-top:10px;padding:10px;">'+
                                '<span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>'+
                                msg+'</div>';
    },
    
    //curtain
    bringCoverallToFront: function(ele, styles, message) {
        if (!  window.hWin.HEURIST4.msg.coverall ) {
            window.hWin.HEURIST4.msg.coverall = 
                $('<div><div class="internal_msg" style="position: absolute;top: 30px;left: 30px;">'
                        +'</div></div>').addClass('coverall-div')
                .css({
                    'zIndex': 60000, //9999999999
                    'font-size': '1.2em'
                });
        }else{
            window.hWin.HEURIST4.msg.coverall.detach();
        }
        
        if(!message){
            message = 'Loading Content';
            message = (window.hWin.HEURIST4.util.isFunction(window.hWin.HR)?window.hWin.HR(message):message)+'...';
        }    
        window.hWin.HEURIST4.msg.coverall.find('.internal_msg').html(message);
        
        if(ele){
            if($(ele).find('.coverall-div').length==0) window.hWin.HEURIST4.msg.coverall.appendTo($(ele));
        }else{
            if($('body').find('.coverall-div').length==0) window.hWin.HEURIST4.msg.coverall.appendTo('body');
        }
        
        if(!styles){
            styles = {opacity: '0.6', 'background-color': "rgb(0, 0, 0)", color: 'rgb(0, 190, 0)', 'font-size': '20px', 'font-weight': 'bold'};
        }
        window.hWin.HEURIST4.msg.coverall.css( styles );
        
        $(window.hWin.HEURIST4.msg.coverall).show();
    },    
    
    sendCoverallToBack: function(force_close) {
        if(force_close===true) window.hWin.HEURIST4.msg.coverallKeep = false;
        if(window.hWin.HEURIST4.msg.coverallKeep===true) return;
        $(window.hWin.HEURIST4.msg.coverall).hide();
    },
  

    //
    // MAIN method
    // buttons - callback function or objects of buttons for dialog option
    // title - either string for title, or object with labels {title:, yes: ,no, cancel, }
    //       labels for buttons are applicable for default set of buttons (if buttons array is not defined)
    // ext_options:   
    //  default_palette_class, 
    //  position
    //  container
    //  isPopupDlg
    //
    showMsg: function(message, options){
        return window.hWin.HEURIST4.msg.showMsgDlg(message, null, null, options )
    },
    
    showMsgDlg: function(message, buttons, labels, ext_options){

        if(!window.hWin.HEURIST4.util.isFunction(window.hWin.HR)){
            alert(message);
            return;
        }
        
        if(!ext_options) ext_options = {};
        
        
        if(ext_options['buttons']){
            buttons = ext_options['buttons'];
            delete ext_options['buttons'];
        }
        if(ext_options['labels']){
            labels = ext_options['labels'];
            delete ext_options['labels'];
        }
        
        let isPopupDlg = (ext_options.isPopupDlg || ext_options.container);
        let dialogId = (!ext_options.dialogId) ? 'dialog-common-messages' : ext_options.dialogId;

        let $dlg = isPopupDlg  //show popup in specified container
                    ?window.hWin.HEURIST4.msg.getPopupDlg(ext_options.container)
                    :window.hWin.HEURIST4.msg.getMsgDlg(dialogId);

        if(message!=null){
            
            let isobj = (typeof message ===  "object");

            if(!isobj){
                isPopupDlg = isPopupDlg || (message.indexOf('#')===0 && $(message).length>0);
            }
 
            if(isPopupDlg){

                $dlg = window.hWin.HEURIST4.msg.getPopupDlg( ext_options.container );
                if(isobj){
                    $dlg.append(message);
                }else if(message.indexOf('#')===0 && $(message).length>0){
                    //it seems it is in Digital Harlem only
                    $dlg.html($(message).html());
                }else{
                    $dlg.html(message);
                }

            }else{
                
                $dlg.empty();
                
                if(isobj){
                    $dlg.append(message);
                }else{
                    $dlg.append('<span>'+window.hWin.HR(message)+'</span>');
                }
            }
        }

        let title = '·', // 'Info' removed - it's a useless popup window title, better to have none at all
            lblYes = window.hWin.HR('Yes'),
            lblNo =  window.hWin.HR('No'),
            lblOk = window.hWin.HR('OK'),
            lblCancel = window.hWin.HR('Cancel');
        
        if($.isPlainObject(labels)){
            if(labels.title)  title = labels.title;
            if(labels.yes)    lblYes = window.hWin.HR(labels.yes);
            if(labels.no)     lblNo = window.hWin.HR(labels.no);
            if(labels.ok)     lblOk = window.hWin.HR(labels.ok);
            if(labels.cancel) lblCancel = window.hWin.HR(labels.cancel);
        }else if (labels!=''){
            title = labels;
        }
        
        if (window.hWin.HEURIST4.util.isFunction(buttons)){ //}typeof buttons === "function"){

            let callback = buttons;

            buttons = {};
            buttons[lblYes] = function() {
                $dlg.dialog( "close" );
                callback.call();
            };
            buttons[lblNo] = function() {
                $dlg.dialog( "close" );
            };
        }else if(!buttons){    //buttons are not defined - the only one OK button

            buttons = {};
            buttons[lblOk] = function() {
                $dlg.dialog( "close" );
            };

        }

        let options =  {
            title: window.hWin.HR(title),
            resizable: false,
            modal: true,
            closeOnEscape: true,
            buttons: buttons
            
        };
        
        if(ext_options){

           if( $.isPlainObject(ext_options) ){
                $.extend(options, ext_options);
           }
           if(ext_options.my && ext_options.at && ext_options.of){
               options.position = {my:ext_options.my, at:ext_options.at, of:ext_options.of};
           }
           else if(!ext_options.options && !$.isPlainObject(ext_options)){  
                //it seems this is not in use
                let posele = $(ext_options);
                if(posele.length>0)
                    options.position = { my: "left top", at: "left bottom", of: $(ext_options) };
           }
           
           if(ext_options['removeOnClose']){
                options.close = function(event, ui){  $dlg.remove(); };   
           }
           
        }
        if(!options.position){
            options.position = { my: "center center", at: "center center", of: window };    
        }
        options.position.collision = 'none'; //FF fix
        

        if(isPopupDlg){

            if(!options.open){
                options.open = function(event, ui){
                    $dlg.scrollTop(0);
                };
            }

            if(!options.height) options.height = 515;
            if(!options.width) options.width = 705;
            if(window.hWin.HEURIST4.util.isempty(options.resizable)) options.resizable = true;
            /* auto height dialog
            if(options.resizable === true){
            options.resizeStop = function( event, ui ) {
 
               var nh = $dlg.parent().height()
                            - $dlg.parent().find('.ui-dialog-titlebar').height() - $dlg.parent().find('.ui-dialog-buttonpane').height(); //-20

                    $dlg.css({overflow: 'none !important','width':'100%', 'height':nh });
                };
            }*/
        }else if(!options.width){
            options.width = 'auto';
        }

        
        $dlg.dialog(options);
        
        if(options.hideTitle){
            $dlg.parent().find('.ui-dialog-titlebar').hide();
        }else{
            $dlg.parent().find('.ui-dialog-titlebar').show();
        }

        
        if($dlg.attr('data-palette'))
            $dlg.parent().removeClass($dlg.attr('data-palette'));
        if(ext_options.default_palette_class){
            $dlg.attr('data-palette', ext_options.default_palette_class);
            $dlg.parent().addClass(ext_options.default_palette_class);
        }else{
            $dlg.attr('data-palette', null);
        }
        
        return $dlg;
       
       
       
        //'#8ea9b9 none repeat scroll 0 0 !important'     none !important','background-color':'none !important
    },  
    
    // for progress message
    _progressInterval: 0,
    _progressDiv: null,
    _progressPopup: null,
    
    //
    // returns session_id
    // container - container element. if not defined it shows in popup
    // content - 1)html code 2)false - use given content 3) otherwise fill container with default content
    // steps - array of labels
    //
    // session_id - unique id of progress session
    // t_interval - checkout interval
    //
    showProgress: function( options ){
        if(window.hWin.HEURIST4.msg._progressInterval>0){
            console.log('previous progress is not completed');            
            return;
        }
        
        let $progress_div;
        let content = options?.content;
        let is_popup = true;
        
        if(!options){
            options = {};
        }        

        if(options?.container){ //container element
            is_popup = false;
            $progress_div = options.container;
        }        
        
        if (window.hWin.HEURIST4.util.isempty(content)) {
            //default content
            content = '';
            
            if(Array.isArray(options?.steps)){
                
                content = '<ol type="1" style="font-size:12px;height:80%;padding-top:20px;" class="progress-steps">';
                
                options.steps.forEach((item)=>{
                    content += `<li style="color:gray">${item}</li>`;
                });
                
                content += '</ol>';
            }else{
                content = '<div class="loading" style="height:80%"></div>';
            }
            
            content += '<div style="width:80%;height:40px;padding:5px;text-align:center;margin:auto;margin-top:10px">'
                +'<div id="progressbar"><div class="progress-label">Processing data...</div></div>'
                +'<div class="progress_stop" style="text-align:center;margin-top:4px">Abort</div>'
            +'</div>';
            
        }else if(typeof content !== 'string'){
            content = false;
        }
        
        if(is_popup){
            options['buttons'] = {}; //no buttons
            options['width'] = 500;
            options['hideTitle'] = true;
   
            window.hWin.HEURIST4.msg._progressPopup = window.hWin.HEURIST4.msg.showMsg( 
                    '<div class="progressbar_div" style="margin:10px"></div>', options );
        
            $progress_div = window.hWin.HEURIST4.msg._progressPopup.find('div.progressbar_div');
        }
        
        
        let progress_url = window.hWin.HAPI4.baseURL + "hserv/controller/progress.php";
        
        let session_id = options.session;
        if(!(session_id>0)) session_id = window.hWin.HEURIST4.util.random();

        let t_interval = options.interval;
        if(!(t_interval>0)) t_interval = 900;
        
        window.hWin.HEURIST4.msg._progressDiv = $progress_div;
        $progress_div.show(); 
        document.body.style.cursor = 'progress';
        
        //add progress bar content
        if(content){
            $progress_div.html(content);
        }
        

        //elements        
        let btn_stop = $progress_div.find('.progress_stop').button();
        
        // termination
        btn_stop.on({click:function(){
                let request = {terminate:1, t:(new Date()).getMilliseconds(), session:session_id};
                window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
                    window.hWin.HEURIST4.msg.hideProgress();
                });
            }});
    
        let progressSteps = $progress_div.find('.progress-steps');
        let div_loading = $progress_div.find('.loading').show();
        let pbar = $progress_div.find('#progressbar');
        let progressLabel = pbar.find('.progress-label').text('');
        pbar.progressbar({value:0});

        let elapsed = 0;
        
        window.hWin.HEURIST4.msg._progressInterval = setInterval(function(){ 
            
            let request = {t:(new Date()).getMilliseconds(), session:session_id};            
            
            window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){

//response=='' || 
                if(response?.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                    window.hWin.HEURIST4.msg.hideProgress();
                }else if(response){
                    //it may return terminate,done,
                    
                    let resp = response.split(',');
                    
                    if(response=='terminate' || !(resp.length>=2)){
                        //first or last response
                        if(response=='terminate'){
                            window.hWin.HEURIST4.msg.hideProgress();
                        }else{
                            div_loading.show();    
                        }
                    }else{
                        div_loading.hide();
                        
                        if(resp.length==3 || resp.length==1){
                            let newStep = resp.shift();
                            let percentage = 0;
                            progressSteps.show();

                            if(window.hWin.HEURIST4.util.isNumber(newStep)){
                                newStep = parseInt(newStep);
                                let all_li = progressSteps.find('li');
                                if(newStep>0){
                                    let arr = all_li.slice(0,newStep);
                                    arr.css('color','black');
                                    arr.find('span.processing').remove(); //remove rotation icon
                                }
                                if(newStep<all_li.length){
                                        if(percentage>0){
                                            if(percentage>100) percentage = 100;
                                            percentage = percentage+'%'; 
                                        }else{
                                            percentage = 'processing...'
                                        }
                                        let ele = $(all_li[newStep]).find('span.percentage');
                                        if(ele.length==0){
                                            percentage = '<span class="percentage">'+percentage+'</span>';
                                            $('<span class="processing"> <span class="ui-icon ui-icon-loading-status-balls"></span> '
                                                +percentage+'</span>')
                                                .appendTo( $(all_li[newStep]) );
                                            $(all_li[newStep]).css('color','black');
                                        }else{
                                            ele.text(percentage);
                                        }
                                }
                            }else{
                                let container = progressSteps.find('ol');
                                let li_ele = container.find('li:contains("'+newStep+'")');
                                if(li_ele.length==0){ //not added yet
                                    $('<li>'+newStep+'</li>').appendTo(container);    
                                }
                            }
                        }

                        let cnt=0, total=0;
                        if(resp.length>1){
                            cnt = resp[0];
                            total = resp[1];
                        }
                        
                        if(cnt>0 && total>0){
                            const val = cnt*100/total;
                            pbar.progressbar( "value", val );

                            elapsed += t_interval;
                            let est_remaining = (elapsed / resp[0]) * (resp[1] - resp[0]);

                            if(est_remaining < 10000){ // less than 10 seconds
                                est_remaining = 'a few seconds';
                            }else if(est_remaining < 60000){ // less than a minute
                                est_remaining = `${Math.ceil(est_remaining / 1000)} seconds`;
                            }else{
                                est_remaining = `${Math.ceil(est_remaining / 60000)} minutes`;
                            }

                            progressLabel.text(`${resp[0]} of ${resp[1]}   ${(resp.length==3?resp[2]:'')} (approximately ${est_remaining} remaining)`);
                        }else{
                            progressLabel.text('preparing...');
                            pbar.progressbar( "value", 0 );
                        }
                    }
                    
                }
            },'text');
          
        
        }, t_interval);                
        
        return session_id;
    },
    
    hideProgress: function(){
        $('body').css('cursor','auto');

        if(window.hWin.HEURIST4.msg._progressInterval!=null){
            
            clearInterval(window.hWin.HEURIST4.msg._progressInterval);
            window.hWin.HEURIST4.msg._progressInterval = null;
        }
        if(window.hWin.HEURIST4.msg._progressDiv){
            window.hWin.HEURIST4.msg._progressDiv.hide();    
            window.hWin.HEURIST4.msg._progressDiv = null;
        }
        if(window.hWin.HEURIST4.msg._progressPopup){
            let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
            $dlg.dialog( "close" );
            window.hWin.HEURIST4.msg._progressPopup = null;
        }
        
    },
    
    
    //
    // Warning on exit
    //             
    showMsgOnExit: function(sMessage, onSave, onIgnore){
        let $dlg, buttons = {};
        buttons['Save'] = function(){ 
            //that._saveEditAndClose(null, 'close'); 
            onSave();
            $dlg.dialog('close'); 
        }; 
        buttons['Ignore and close'] = function(){ 
            onIgnore();
            $dlg.dialog('close'); 
        };

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
            window.hWin.HR('Warn_Lost_Data'),
            buttons,
            {title: window.hWin.HR('Confirm'),
               yes: window.hWin.HR('Save'),
                no: window.hWin.HR('Ignore and close')},
            {default_palette_class:'ui-heurist-design'});
        
    },
    
    //
    // for smarty reports
    //
    showWarningAboutDisabledFunction: function(txt){
      
            if(txt.indexOf('Exception on execution: Syntax error in template')==0 
            && txt.indexOf('not allowed by security setting')>0){
                
                    let buttons = null
                    if(window.hWin.HAPI4.actionHandler){
                        buttons = 
                        {   'Send Bug Report': function() {
                                window.hWin.HAPI4.actionHandler.executeActionById('menu-help-bugreport');
                                $dlgm.dialog( 'close' );
                                },
                            'Cancel':function() {
                                $dlgm.dialog( 'close' );
                            }
                        };
                    }

                    let $dlgm = window.hWin.HEURIST4.msg.showMsgDlg(
    '<p>Sorry, native php functions in custom reports are disabled by default<br>'
    +'as a security precaution. </p>'
    +'<p>Please use the bug report function to ask that this function be enabled. </p>',
                        buttons,
                        'Warning');
                        
                        return true;
                
            }
            
            return false;
        
    },
    
    
  
};