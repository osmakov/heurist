/**
* controlPanel.js : Header panel with logo, main menu and dashboard (optionally)
*                   It inits actionHandler object
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

/*

    Main menu is list of Heurist operations. They are grouped in several sections:
    Admin, Database, Export, Import, Help, Profile, Management etc. 
    
    Each menu entry defined in action.json
    with the following attributes:
    id:  unique id of action  (like "id":"menu-database-clone")
    data:{
        user-admin-status - accessibility according to user level (2 db owner, 1 - db admin, 0 - logged in, -1 - all)
        logaction  - log tag 
        icon - icon in menu
        container - target element where to load dialog/form
        header  - title for container/popup dialog
        }
    text - default label in menu    
    title - default hint (mouseover) for menu item
    
    Localized versions for menu label and hint, dialog header are taken from localization files
    menu-database-clone#
    menu-database-clone-hint#
    menu-database-clone-header#
*/

/**
 * jQuery UI Widget: heurist.controlPanel
 * 
 * This widget creates a control panel for the Heurist application. It handles menu creation, login actions, 
 * version checks, and user notifications.
 * 
 * @options
 *   @param {String} host_logo - Path to the host logo (default: null).
 *   @param {Boolean} login_inforced - If true, forces the user to log in (default: true).
 * 
 * @properties
 *   @property {Object} menues - Stores references to various menus in the control panel.
 *   @property {Object} actionHandler - Handles actions related to menu items and user actions.
 *   @property {Boolean} _initial_search_already_executed - Tracks whether the initial search has already been performed.
 *   @property {Boolean} _retrieved_notifications - Tracks whether user notifications have been retrieved.
 *   @property {Object} version_message - Contains the message about available alpha or stable versions.
 * 
 * @methods
 *   _init() - Initializes the control panel, loads HTML content, and sets up event listeners.
 *   _initControls() - Initializes the visual and interactive elements within the control panel.
 *   _refresh() - Refreshes the control panel, adjusting visibility based on login status and showing notifications.
 *   _destroy() - Cleans up event listeners and removes elements when the widget is destroyed.
 *   doLogin() - Initiates the login process, showing the login dialog if the user is not logged in.
 *   _performInitialSearch() - Performs the initial search or action based on URL parameters, or triggers a dashboard search.
 *   _dashboardVisibility(is_startup) - Controls the visibility of the dashboard panel.
 *   _adjustHeight() - Adjusts the height of the header and main panel after dashboard visibility changes.
 *   _showVersionMessage() - Displays a message next to the database name about available software versions.
 *   _getUserNotifications() - Retrieves and displays user notifications, including prompting for bug reports.
 */
$.widget( "heurist.controlPanel", {

    // default options
    options: {
        host_logo:null,
        login_inforced: true,
    },
    
    menues:{},
    actionHandler: null,
    
    //flags    
    _initial_search_already_executed: false,
    _retrieved_notifications: false,

    version_message: null, // container for message about available alpha/stable version
    
    /**
     * _init
     * 
     * Initializes the control panel widget. Loads the content from a predefined URL and calls `_initControls` once the content is loaded.
     */
    _init: function() {

        let that = this;
        
        this.actionHandler = window.hWin.HAPI4.actionHandler;
        
        const url = window.hWin.HAPI4.baseURL
                        +'hclient/widgets/cpanel/controlPanel.html?t=' 
                        +window.hWin.HEURIST4.util.random()
        
        // Load HTML content into the widget
        this.element.load(url, 
            function(response, status, xhr){
                that._need_load_content = false;
                if ( status == "error" ) {
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: response,
                        error_title: 'Failed to load HTML content',
                        status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                    });
                }else{
                    that._initControls()
                }
            });
        
    },
    
    /**
     * _initControls
     * 
     * Sets up the visual and interactive controls for the control panel, 
     * such as the logo, version information, database dropdown, and profile menu.
     * 
     */    
    _initControls:function(){
        
        let that = this;
        
        // Set the basic CSS for the control panel
        this.element.css('height', '100%').addClass('ui-heurist-header2')
            .disableSelection();// prevent double click to select text

        this.div_logo = $('div.logo');

        //validate server side version  - compare version of code in server where main index database and this server version
        let res = window.hWin.HEURIST4.util.versionCompare(window.hWin.HAPI4.sysinfo.version_new, window.hWin.HAPI4.sysinfo['version']);   
        let sUpdate = '';
        let mr = 45;
        if(res==-2){ // -2=newer code on server
            mr = 55;
            sUpdate = '&nbsp;<span class="ui-icon ui-icon-alert" style="width:16px;display:inline-block;vertical-align: middle;cursor:pointer"></span>';
        }
        
        this.div_logo.find('div.version')
            .css('margin-right','-'+mr+'px')
            .html('<span>v'+window.hWin.HAPI4.sysinfo.version+sUpdate+'</span>');
            
        // Bind click events for version alert and reload actions
        this._on( this.div_logo, {
            click: function(event){
                if($(event.target).is('span.ui-icon-alert')){
                    window.hWin.HEURIST4.msg.showMsgDlg(                    
                    "Your server is running Heurist version "+window.hWin.HAPI4.sysinfo['version']+" The current stable version of Heurist (version "
                    +window.hWin.HAPI4.sysinfo.version_new+") is available from <a target=_blank href='https://github.com/HeuristNetwork/heurist'>GitHub</a> or "
                    +"<a target=_blank href='https://HeuristNetwork.org'>HeuristNetwork.org</a>. We recommend updating your copy of the software if the sub-version has changed "
                    +"(or better still with any change of version).<br><br>"
                    +"Heurist is copyright (C) 2005-2024 The University of Sydney and available as Open Source software under the GNU-GPL licence. "
                    +"Beta versions of the software with new features may also be available at the GitHub repository or linked from the HeuristNetwork home page.");
                }else{
                    //reload without query string
                    document.location = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database;
                    //.reload();    
                }
            }
        });


        // current and last databases dropdown
        this.div_dbname = this.element.find('div.dblist-container')
            
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(window.hWin.HAPI4.sysinfo.dbrecent)){
            
            this.div_dbname.css({
                'background-position': 'left center',
                'background-repeat': 'no-repeat',
                'background-image': 'url("'+window.hWin.HAPI4.baseURL+'hclient/assets/database.png")'});
            
            let wasCtrl = false;
            let selObj = window.hWin.HEURIST4.ui.createSelector(null, window.hWin.HAPI4.sysinfo.dbrecent);        
            $(selObj).css({'font-size':'1em', 'font-weight':'bold','border':'none', outline: 'none',
                           'min-width':'150px', 'margin-left':'25px', })
            .on('click', function(event){
                wasCtrl = event.shiftKey;
            })
            .on('change', function(event){
                if(window.hWin.HAPI4.database!=$(event.target).val()){
                    let url =  window.hWin.HAPI4.baseURL+'?db='+$(event.target).val();
                    $(event.target).val(window.hWin.HAPI4.database);
                    if(wasCtrl){
                        location.href = url;
                    }else{
                        window.open(url, '_blank');
                    }
                }
                $(event.target).trigger('blur');//remove focus
            })
            .addClass('ui-heurist-header2')
            .uniqueId()
            .val( window.hWin.HAPI4.database ).appendTo( this.div_dbname );
        }else{
            
            $("<div>").css({'font-size':'1em', 'font-weight':'bold', 'padding-left':'22px', 'margin-left':'50px',
                'background-position': 'left center',
                'background-repeat': 'no-repeat',
                'background-image': 'url("'+window.hWin.HAPI4.baseURL+'hclient/assets/database.png")' })
            .text(window.hWin.HAPI4.database).appendTo( this.div_dbname );
            
        }
            
        // MAIN MENU-----------------------------------------------------
        this.divProfileMenu = this.element.find('div.menu-container');
        
        this.divProfileMenu.addClass('horizontalmenu');
            
        this.divProfileMenu.buttonsMenu({
            /*
           menuContent:
                    '<div>'                                                                          //margin-left:150px
                    +'<ul title="Help" link-style="width:auto;background:none;border:none;" style="" data-icon-left="ui-icon-circle-b-help">'
                    +'<li data-action="menu-help-online"/>'
                    +'<li data-action="menu-help-quick-tips"/>'
                    +'<li data-action="menu-help-website"/>'
                    +'<li data-action="menu-help-roadmap"/>'
                    +'<li data-action="menu-help-devhist"/>'
                    +'<li>---------------</li>'
                    +'<li data-action="menu-help-bugreport"/>'
                    +'<li data-action="menu-help-emailteam"/>'
                    +'<li data-action="menu-help-emailadmin"/>'
                    +'<li data-action="menu-help-acknowledgements"/>'
                    +'<li data-action="menu-help-about"/>'
                    +'</ul>'
                    +'<ul title="Profile" link-style="width:auto;background:none;border:none;" style="margin-left:30px" data-icon-left="ui-icon-user">'
                    +'<li data-action="menu-profile-preferences"/>'
                    +'<li data-action="menu-profile-tags"/>'
                    +'<li data-action="menu-profile-reminders"/>'
                    +'<li>---------------</li>'
                    +'<li data-action="menu-profile-info"/>'
                    +'<li data-action="menu-profile-groups"/>'
                    +'<li data-action="menu-profile-users"/>'
                    +'<li data-action="menu-profile-import"/>'
                    +'<li data-action="menu-profile-logout"/>'
                    +'</ul>'
           
           ,*/ 
           manuActionHandler:function(action){
                that.actionHandler.executeActionById(action)
           }
        });
            
           
        // LISTENERS --------------------------------------------------
        $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_CREDENTIALS
            +' '+window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE, function(e, data) {
            that._refresh();
        });

        this._refresh();

    }, //end _create
     
/*
        function ___set_menu_item_visibility(idx, item, is_showhide){

                let lvl_user = $(item).attr('data-user-admin-status'); //level of access by workgroup membership

                let user_req_permissions = $(item).attr('data-user-permissions');
                
                let lvl_exp = $(item).attr('data-user-experience-level');  //level by ui experience
                
                let is_visible = true;
                
                item = $(item).is('li')?$(item):$(item).parent();
                let elink = $(item).find('a');
                
                if(lvl_user>=0){  //2 database owner, 1 - memeber of Database admin
                    // lvl_user=1 is_admin
                    is_visible = (lvl_exp!=3) && window.hWin.HAPI4.has_access(lvl_user);

                    if(is_visible){
                        window.hWin.HEURIST4.util.setDisabled(elink, false);
                        item.attr('title', '');
                    }else{
                        window.hWin.HEURIST4.util.setDisabled(elink, true);
                        
                        item.attr('title', 'Only '
                        + (item.attr('data-user-admin-status')==2?'the database owner':'database managers')
                        + ' can delete all records / the database');
                    }
                }

                if(!window.hWin.HEURIST4.util.isempty(user_req_permissions) && is_visible){

                    let required = '';
                    let cur_permissions = window.hWin.HAPI4.currentUser.ugr_Permissions;

                    if(user_req_permissions.indexOf('add') !== -1 && !cur_permissions?.add){
                        required = 'create'
                    }
                    if(user_req_permissions.indexOf('delete') !== -1 && !cur_permissions?.delete){
                        required += (required !== '' ? ' and ' : '') + 'delete';
                    }

                    if(required !== ''){

                        window.hWin.HEURIST4.util.setDisabled(elink, true);
                        item.attr('title', `You do not have permission to ${required} records`);

                        is_visible = false;
                    }else{
                        window.hWin.HEURIST4.util.setDisabled(elink, false);
                        item.attr('title', '');
                    }
                }

                if(is_showhide && !is_visible){
                    item.hide();  
                }else{
                    item.show();  
                }
                
                //0 advance, 1-experienced, 2-beginner
                if(lvl_exp>=0 && is_visible){
                    
                        let usr_exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);//beginner by default
                        
                        is_visible = (usr_exp_level<=lvl_exp);
                    
                        if(is_visible){
                            item.show();  
                        }else{
                            item.hide();  
                        }
                }         
        }
        
        //  0 - logged in                 
        //  1 - db admin (admin of group #1)
        //  2 - db owner
        //loop top level menu
        for (let key in this.menues){
            let menu = this.menues[key];
            if(menu.is('li')){
                ___set_menu_item_visibility(0, menu, true);  //top level menu - show/hide               
            }else{
                $(menu).find('li,a').each(___set_menu_item_visibility); //enable/disbale dropdown items
            }
        }
*/    
   
    /**
     * _refresh
     * 
     * Refreshes the control panel by checking the login status, displaying the version message, 
     * performing initial search if required, and retrieving user notifications.
     */   
    _refresh: function(){
    
        // Replace "Profile" label for menu to current user name
        this.divProfileMenu.find('span.ui-icon-user').next().text(window.hWin.HAPI4.currentUser.ugr_FullName);

        if(this.options.login_inforced && !window.hWin.HAPI4.has_access()){
            this.doLogin();
        }else {
            this._showVersionMessage();
            this._performInitialSearch();
            this._getUserNotifications();
        }
    },

    /**
     * _destroy
     * 
     * Cleans up the control panel widget by removing event listeners and clearing DOM elements related to the control panel.
     */
    _destroy: function() {

        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART);
        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_CREDENTIALS+' '+window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE);
        
        this.div_logo.remove();
        this.divProfileMenu.remove();
    },
    
    /**
     * doLogin
     * 
     * Displays the login dialog and handles the login process. If the user is not logged in and login is enforced, 
     * it redirects the user to the login page.
     */
    doLogin: function(){
        
        let isforced = this.options.login_inforced;
        let that = this;
        window.hWin.HEURIST4.ui.checkAndLogin( isforced, function(is_logged)
            { 
                if(is_logged) {
                    $(that.element).find('.usrFullName').text(window.hWin.HAPI4.currentUser.ugr_FullName);

                    that._showVersionMessage();
                    that._performInitialSearch();
                    that._getUserNotifications();

                } else if(that.options.login_inforced){
                    window.hWin.location  = window.HAPI4.baseURL
                }
            }); 
    },
    

    /**
     * _performInitialSearch
     * 
     * Executes an initial search or handles specific actions like opening the CMS editor or running commands based on URL parameters.
     */
    _performInitialSearch: function(){
    
        if(this._initial_search_already_executed){
            this._dashboardVisibility( false );
            return;  
        } 
        
        this._initial_search_already_executed = true;
        
        let cmd = window.hWin.HEURIST4.util.getUrlParameter('cmd', window.hWin.location.search);

        //ignore initial search of some menu command is called from url 
        if(cmd){
            //executes arbitrary command
            this.actionHandler.executeActionById(cmd);
            return;        
        }else if(window.hWin.HAPI4.is_publish_mode || window.hWin.HAPI4.sysinfo['db_total_records']==0){
            return;
        }    
        
        let request = {};

        if(window.hWin.HAPI4.postparams?.q){
            request = window.hWin.HAPI4.postparams;
        }else{
            let init_search = window.hWin.HEURIST4.util.getUrlParameter('q', window.hWin.location.search);
            let qdomain;
            let rules = null;
            if(init_search){
                qdomain = window.hWin.HEURIST4.util.getUrlParameter('w', window.hWin.location.search);
                rules = window.hWin.HEURIST4.util.getUrlParameter('rules', window.hWin.location.search);
            }else{
                init_search = window.hWin.HAPI4.get_prefs('defaultSearch'); 
            }
            if(!qdomain) qdomain = 'a';
            request = {q: init_search, w: qdomain}
            if(rules) request['rules'] = rules;
        }
        
        if(!window.hWin.HEURIST4.util.isempty(request['q'])){
            request['f'] = 'map';
            request['source'] = 'init';

            setTimeout(function(){
                window.hWin.HAPI4.RecordSearch.doSearch(window.hWin.document, request);//initial search
                }, 1000);
        }else{
            //trigger search finish to init some widgets
            window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH, {recordset:null});
        }
        
        window.hWin.HAPI4.postparams = null;

        this._dashboardVisibility( true ); //after login
        
    },

    /**
     * _dashboardVisibility
     * 
     * Manages the visibility of the dashboard panel based on the startup conditions. Shows or hides the dashboard or shortcuts ribbon.
     * 
     * @param {Boolean} is_startup - Flag indicating if the control panel is being initialized at startup.
     */
    _dashboardVisibility: function(is_startup){

        if (window.hWin.HAPI4.is_publish_mode || window.hWin.HAPI4.sysinfo.db_has_active_dashboard==0){
            return;
        }
            
            
               let remove_ribbon = true;
               //show dashboard
               let prefs = window.hWin.HAPI4.get_prefs_def('prefs_sysDashboard', {show_on_startup:0, show_as_ribbon:0});
               
               if(prefs.show_on_startup==1){
                    if(prefs.show_as_ribbon==1){
                        remove_ribbon = false;
                        if(!this.divShortcuts){
                            this.divShortcuts = $( "<div>")            
                                .css({'position':'absolute', left:0, right:-2, height:'36px', bottom:0})
                                .appendTo(this.element)
                                .manageSysDashboard({is_iconlist_mode:true, isViewMode:true});
                        }else{
                            //refresh
                            this.divShortcuts.manageSysDashboard('startSearch');
                        }
                    }else if(is_startup) {
                            window.hWin.HEURIST4.ui.showEntityDialog('sysDashboard'); //show as poup
                    }
               }
               
               if(remove_ribbon && this.divShortcuts){
                     this.divShortcuts.remove();    
                     this.divShortcuts = null;  
               } 
               
               let that = this;
              
               setTimeout( function(){ that._adjustHeight(); },is_startup?1000:10)
    },
    
    /**
     * _adjustHeight
     * 
     * Adjusts the height of the layout and main panel after changing the visibility of the dashboard.
     */
     _adjustHeight: function(){

        let ele = this.element.parents('#layout_panes');
        if(ele){
            let h = 50;
            
            if(this.divShortcuts){
                h = 100;
            }
            
            ele.children('#north_pane').height(h);
            ele.children('#center_pane').css({top: h});
            
            if($('.ui-layout-container').length>0){
               
                let layout = $('.ui-layout-container').layout();
                layout.resizeAll();
            }
            
        }
        
    },
    
    /**
     * _showVersionMessage
     * 
     * Display message next to DB name, about available stable/alpha version
     */
    _showVersionMessage: function(){

        const that = this;
        const SPIN_INTERVAL = 30000; // how often to spin - 5 minutes
        const SPIN_DURATION = 1000; // how long the spin takes - 1 second

        if(this.version_message){
            return;
        }

        this.version_message = true;

        let is_alpha = window.hWin.HAPI4.baseURL.match(/h\d+-alpha|alpha/);
        let suggestion_txt = '';
        let styling = {float:'left', 'margin-left':'25px', width:'360px', 'font-size':'0.85em', cursor:'default'};

        styling['margin-top'] = '0.9em';

        // Add message about reporting bugs
        styling['width'] = '220px';
        let $bug_msg = $('<div>', {title: 'Click to make a bug report'})
            .css($.extend({}, styling, {color: '#FFFF66', cursor: 'pointer'}))
            .insertAfter(this.div_dbname)
            .html('<span class="ui-icon ui-icon-bug" style="float: left;margin: 5px;"></span>Please report bugs here, or suggest improvements. We are responsive');

        this._on($bug_msg, {
            click: () => {
                window.hWin.HEURIST4.ui.showEntityDialog('sysBugreport');
            }
        });

        let $bug_icon = $bug_msg.find('span.ui-icon');
        const INTERVAL = setInterval(() => {
            $({deg: 0}).animate({deg: 360}, {
                duration: SPIN_DURATION,
                step: (rotation) => {

                    if($bug_icon.length == 0){
                        clearInterval(INTERVAL);
                        return;
                    }

                    $bug_icon.css('transform', `rotate(${rotation}deg)`);
                }
            });
        }, SPIN_INTERVAL);

        if(!is_alpha){ // need to check that an alpha version is available on this server
            window.hWin.HAPI4.SystemMgr.check_for_alpha({a:'check_for_alpha'}, function(response){ 
                
                if(window.hWin.HEURIST4.util.isempty(response.data)){
                    return;
                }

                suggestion_txt = `<a style="cursor: pointer;text-decoration: underline;" href="${response.data + location.search}" id="lnk_change" title="Move to alpha version">`
                               + `Use the latest (alpha) version</a> (recommended)`;

                that.version_message = $("<div>")
                    .css(styling)
                    .insertAfter($bug_msg)
                    .html(suggestion_txt);
            });
        }else{ // currently on alpha

            suggestion_txt = 'This is the latest (alpha) version. If you are blocked by a new bug you can switch to the '
                + '<a style="cursor: pointer;text-decoration: underline;" href="#" id="lnk_change" title="Go to standard version">standard version</a>';

            styling['width'] = '280px';

            this.version_message = $("<div>")
                .css(styling)
                .insertAfter($bug_msg)
                .html(suggestion_txt);

            this._on(this.version_message.find('#lnk_change'), {
                click: () => {

                    let $dlg;
                    let msg = 'If you encounter <span style="text-decoration: underline">any</span> bug, we ask that you report it with the <a href="#" id="msg_bug_rpt">bug reporter</a>'
                        + ' - bugs are generally<br>'
                        + 'fixed within a day or so.<br><br>'
                        + 'We recommend that you use the alpha version unless you encounter a newly introduced bug<br>'
                        + 'which blocks your work, in which case you can revert to the standard (/heurist/) version.<br><br>'
                        + 'We recommend that you return to using the alpha version as soon as the bug is fixed (you<br>'
                        + 'should receive an adivce email, otherwise switch back in a couple of days).';

                        let btns = {};
                        btns['Continue using alpha version'] = () => { 
                            $dlg.dialog('close'); 
                        };
                        btns['Report bug and switch to standard version'] = () => {
                            $dlg.dialog('close');
                            window.hWin.HEURIST4.ui.showEntityDialog('sysBugreport', {
                                onClose: () => {
                                    location.href = window.hWin.HAPI4.baseURL_pro + location.search;
                                }
                            });
                        };

                    $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, {title: 'Thanks for helping to test Heurist'}, {default_palette_class: 'ui-heurist-admin'});

                    $dlg.find('#msg_bug_rpt').on('click', function(){ // same action as proceed button
                        $dlg.dialog('close');
                        window.hWin.HEURIST4.ui.showEntityDialog('sysBugreport', {
                            onClose: () => {
                                location.href = window.hWin.HAPI4.baseURL_pro + location.search;
                            }
                        });
                    });
                }
            });
        }
    },

    /**
     * _getUserNotifications
     * 
     * Disply notifications about certain features / functions to the user
     * Or, open the bug reporter monthly
     * 
     * @returns none
     */
    _getUserNotifications: function(){

        if(this._retrieved_notifications){ return; }

        this._retrieved_notifications = true;

        let request = {
            a: 'get_user_notifications'
        };

        window.hWin.HAPI4.SystemMgr.get_user_notifications(request, function(response){

            if(window.hWin.HEURIST4.util.isempty(response.data) || response.status != window.hWin.ResponseStatus.OK){
                return;
            }

            let notifications = response.data;

            if(Object.keys(notifications).length == 1 && notifications['bug_report']){
                window.hWin.HAPI4.actionHandler.executeActionById('menu-help-bugreport');
            }
        });
    },

});

