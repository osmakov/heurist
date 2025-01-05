/**
*  ActionHandler - manages list of (menu) actions 
*
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

/* global CmsManager */

/**
 * Class: ActionHandler
 * 
 * The ActionHandler class is responsible for managing a list of actions (menu actions) fetched from a remote JSON file or provided directly as an array. 
 * It offers methods for executing actions based on their ID and performing verification tasks where necessary.
 */
class ActionHandler {

    /**
     * Constructor: Initializes the ActionHandler instance.
     * 
     * If `arg` is an array, it sets the `actions` property directly. 
     * 
     * @param {Array|String|undefined} arg - Can be an array of actions, a string URL, or undefined (default URL used).
     */
    constructor(arg) {
        if(Array.isArray(arg)){
            this.actions = arg;
        } else {
            this.actions = null;
        }
    }
    
    /**
     * Method: loadActionsFromFile
     * 
     * Fetches actions from the given URL in JSON format and sets the `actions` property.
     * 
     * @param {string} url - The URL to fetch actions from.
     * @returns {Promise<void>} No return value. Sets `this.actions` internally.
     * @throws {Error} If the fetch operation fails or the response is not OK.
     */
    async loadActionsFromFile(url) {
        try {
            const path = url || (window.hWin.HAPI4.baseURL + 'hclient/core/actions.json');
            const response = await fetch(path);
            if (!response.ok) {
                throw new Error(`Failed to load actions from ${url}: ${response.statusText}`);
            }
            this.actions = await response.json();
        } catch (error) {
            console.error("Error loading actions:", error);
        }
    }
    
    /**
     * Method: getActions
     */
    getActions() {
        return this.actions;
        /*console.log("Available actions:");
        this.actions.forEach(action => {
            console.log(`ID: ${action.id}, Text: ${action.text}`);
        });*/
    }

    /**
     * Method: findActionById
     * 
     * Finds and returns an action object by its ID.
     * 
     * @param {string} id - The ID of the action to find.
     * @returns {Object|undefined} The action object or `undefined` if not found.
     */
    findActionById(id) {
        return this.actions.find(action => action.id === id);
    }

    /**
     * Helper Method: #handleVerification
     * 
     * Handles the verification process.
     */
    #handleVerification(action, dialog_options) {
        
        let adata = action.data;            
        
        if(!adata){
            return false;
        }
        
        // Handle password and permission verification
        let action_passworded = adata.pwd;
        if (!action_passworded && !window.hWin.HAPI4.has_access(2)) {
            action_passworded = adata['pwd-nonowner'];
        }
        
        let action_admin_level = adata['user-admin-status'];
        let action_member_level = adata['user-memebr-status'];
        let action_user_permissions = adata['user-permissions']; 
        let requiredLevel = (action_admin_level == -1 || action_admin_level >= 0) ? action_admin_level : 0;
        
        if(!(action_passworded || requiredLevel > 0)){ 
            return false;                
        }
        
        if (action_member_level > 0) {
            requiredLevel += ';' + action_member_level;
        }
        
        window.hWin.HAPI4.SystemMgr.verify_credentials((entered_password) => {
            dialog_options.entered_password = entered_password;
            dialog_options.verification_passed = true;
            this.executeActionById(action.id, dialog_options);              
        },
        requiredLevel, action_passworded, null, action_user_permissions);
            
        return true;
    }    
    
    /**
     * Helper Method: #handleHrefAction
     * 
     * Handles actions that involve opening a URL or external link.
     * 
     * @param {Object} action - The action object.
     * @param {string} href - The URL to open.
     * @param {string} target - The target window for the URL.
     * @param {Object} popup_dialog_options - Additional dialog options.
     */
    #handleHrefAction(action, popup_dialog_options) {

         let href = action.href;
         const target = action?.target;
         
         if (window.hWin.HEURIST4.util.isempty(href) || href == '#') {
             return false;
         }

         if (href.startsWith('mailto:')) {
             window.open(href, 'emailWindow');
             return;
         }

         if (!(href.startsWith('http://') || href.startsWith('https://'))) {
             href = window.hWin.HAPI4.baseURL + href + (href.indexOf('?') >= 0 ? '&' : '?') + 'db=' + window.hWin.HAPI4.database;
         }

         if (target) {
             window.open(href, target);
         } else {
             if (!popup_dialog_options.title) {
                 popup_dialog_options.title = action.text;
             }
             let options = $.extend(popup_dialog_options, { width: 800, height: 600 });

             /*                if (item.hasClass('upload_files')) {
             //beforeClose
             options['afterclose'] = function( event, ui ) {

             if(window.hWin.HEURIST4.filesWereUploaded){

             let buttons = {};
             buttons[window.hWin.HR('OK')]  = function() {
             let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
             $dlg.dialog( "close" );

             that.actionHandler.executeActionById('menu-index-files');
             };                                 

             window.hWin.HEURIST4.msg.showMsgDlg('The files you have uploaded will not appear as records in the database'
             +' until you run Import > index multimedia This function will open when you click OK.',buttons);
             }
             }
             }
             */            
             window.hWin.HEURIST4.msg.showDialog(href, options);
         }
         return true;
    }

    /**
     * Helper Method: #prepareDialogOptions
     * 
     * Prepares the dialog options for the action.
     */
    #prepareDialogOptions(action, dialog_options){        
         let actionid = action.id;
         let adata = action.data;
         let action_container = adata?.container;

         let container, menu_container;

         if (dialog_options.container) {
             container = dialog_options['container'];
         } else if (action_container) {
             let section = action_container;
             $('.ui-menu6').slidersMenu('switchContainer', section, true);
             container = $('.ui-menu6 > .ui-menu6-widgets.ui-heurist-'+section);
             container.removeClass('ui-suppress-border-and-shadow');

             menu_container = $('.ui-menu6 > .ui-menu6-section.ui-heurist-' + section);
             menu_container.find('li').removeClass('ui-state-active');
             menu_container.find('li[data-action="' + actionid + '"]').addClass('ui-state-active');
         }

         let popup_dialog_options = {
             isdialog: !container,
             innerTitle: true,
             menu_container: menu_container,
             container: container,

             isFrontUI: true,
             is_h6style: true,
             resizable: false,
             draggable: false,
             position: dialog_options.position || null,
             maximize: true,
             ...dialog_options
         };

         const title_key = actionid+'-header';
         if (window.hWin.HR(title_key) == title_key) { //translation not found
             popup_dialog_options.title = window.hWin.HR(adata?.header || action.text);
         }else{
             popup_dialog_options.title = window.hWin.HR(title_key);
         }
         
         return popup_dialog_options;
    }
    
    /**
     * Helper Method: _importUsers
     * 
     * Handles the import of users into the system through a series of dialog steps.
     * 
     * @param {Object} entity_dialog_options - Configuration options for the dialogs during the import process.
     */
    importUsers(entity_dialog_options) {
        if (!entity_dialog_options) entity_dialog_options = {};
        
        let that = this;
        let auto_select = '';
        
        let options = $.extend(entity_dialog_options, {
            subtitle: 'Step 1. Select database with users to be imported',
            title: 'Import users', 
            select_mode: 'select_single',
            pagesize: 300,
            edit_mode: 'none',
            use_cache: true,
            except_current: true,
            keep_visible_on_selection: true,
            onselect: function(event, data){
                if (!data?.selection || data.selection.length == 0) {
                    return;
                }

                let selected_database = data.selection[0];
                if(selected_database.indexOf(window.hWin.HAPI4.sysinfo.database_prefix) === 0){
                    selected_database = selected_database.substring(window.hWin.HAPI4.sysinfo.database_prefix.length);
                }

                let options2 = $.extend(entity_dialog_options, {
                    subtitle: `Step 2. Select users in ${selected_database} to be imported`,
                    title: 'Import users', 
                    database: selected_database,
                    select_mode: 'select_multi',
                    edit_mode: 'none',
                    keep_visible_on_selection: true,
                    onInitFinished: function(){
                        setTimeout((mngUsers, rec_id) => { mngUsers.recordList.find(`[recid="${rec_id}"]`).trigger('click'); }, 500, this, auto_select);
                        auto_select = '';
                    },
                    onselect: function(event, data){

                        if (!data?.selection || data.selection.length == 0) {
                            return;
                        }

                        let selected_users = data.selection;
                        let options3 = $.extend(entity_dialog_options, {
                            subtitle: 'Step 3. Allocate imported users to work groups',
                            title: 'Import users', 
                            select_mode: 'select_roles',
                            selectbutton_label: 'Allocate roles',
                            sort_type_int: 'recent',
                            edit_mode: 'none',
                            keep_visible_on_selection: false,
                            onselect: function(event, data){
                                that.importUsersComplete(data, selected_users, selected_database);
                            }
                        });

                        window.hWin.HEURIST4.ui.showEntityDialog('sysGroups', options3);
                    }
                });

                if(!window.hWin.HEURIST4.util.isempty(data.email)){
                    
                    let request = {
                        a: 'search',
                        entity: 'sysUsers',
                        details: 'id',
                        ugr_eMail: data.email,
                        db: selected_database
                    };

                    window.hWin.HAPI4.EntityMgr.doRequest(request, (response) => {

                        if(response.status === window.hWin.ResponseStatus.OK && response.data.reccount === 1){
                            auto_select = response.data.records[0];
                        }

                        window.hWin.HEURIST4.ui.showEntityDialog('sysUsers', options2);
                    });

                }else{
                    window.hWin.HEURIST4.ui.showEntityDialog('sysUsers', options2);
                }

            }
            
        });
        window.hWin.HEURIST4.ui.showEntityDialog('sysDatabases', options);
    }    
    
    /**
     * Helpr Method: importUsersComplete
     * 
     * 
     */ 
    importUsersComplete(data, selected_users, selected_database){

        if (!data || $.isEmptyObject(data.selection)){
            return;
        }
        //add new user to specified group
        let request = {
            a: 'action',
            entity: 'sysUsers',
            roles: data.selection,
            userIDs: selected_users,
            sourceDB: selected_database,
            request_id: window.hWin.HEURIST4.util.random()
        };
        window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){
            if (response.status == window.hWin.ResponseStatus.OK) {
                window.hWin.HEURIST4.msg.showMsgDlg(response.data);      
            } else {
                window.hWin.HEURIST4.msg.showMsgErr(response);      
            }
        });
        
    }
    
    /**
     * Method: executeActionById
     * 
     * Executes the action with the specified ID. Verifies credentials if required and handles dialogs or popups.
     * 
     * @param {string} id - The ID of the action to execute.
     * @param {Object} dialog_options - Optional parameters for dialog customization.
     * @returns {boolean} True if the action is supported, otherwise false.
     */
    executeActionById(id, dialog_options) {
        const action = this.findActionById(id);
        
        if (!action) {
            console.log(`Action with ID "${id}" not found.`);            
            return false;
        }
        
        let adata = action.data;
    
        // If action is disabled, return early
        if (adata?.ext == 1) {
            return;
        }
        
        if(!dialog_options){
            dialog_options = {};
        }

        if(!dialog_options?.verification_passed && 
            this.#handleVerification(action, dialog_options))
        {
            return;
        }

        let actionid = action.id;
        let action_log = adata?.logaction; 
        
        if (action_log) {
            window.hWin.HAPI4.SystemMgr.user_log(action_log);
        }

        if (actionid.indexOf('menu-cms') == 0) {
            if (!this.cmsManager) {
                this.cmsManager = new CmsManager();
            }
            this.cmsManager.executeAction(actionid);
            return true;
        }

        // Prepare dialog options
        let popup_dialog_options = this.#prepareDialogOptions(action, dialog_options);

        let is_supported = true;
        let contentURL;

        //database action name
        const s = actionid.substr(actionid.lastIndexOf('-') + 1);
        const actionName = 'db' + s.capitalize();
        
        switch (actionid) {
            case "menu-database-create":
            case "menu-database-restore":
            case "menu-database-delete":
            case "menu-database-clear":
            case "menu-database-rename":
            case "menu-database-clone":
            case "menu-database-register":
            case "menu-database-verify":
            case "menu-database-verifyURLs":
                window.hWin.HEURIST4.ui.showRecordActionDialog(actionName, popup_dialog_options);
                break;
            case "menu-lookup-config":
                popup_dialog_options['classes'] = {"ui-dialog": "ui-heurist-design", "ui-dialog-titlebar": "ui-heurist-design"};
                popup_dialog_options['service_config'] = window.hWin.HAPI4.sysinfo['service_config'];
                popup_dialog_options['title'] = window.hWin.HR('Lookup service configuration');
                popup_dialog_options['path'] = 'widgets/lookup/';
                window.hWin.HEURIST4.ui.showRecordActionDialog('lookupConfig', popup_dialog_options);
                break;
            case "menu-repository-config":

                popup_dialog_options['classes'] = {"ui-dialog": "ui-heurist-design", "ui-dialog-titlebar": "ui-heurist-design"};
                popup_dialog_options['service_config'] = window.hWin.HAPI4.sysinfo['repository_config'];
                popup_dialog_options['title'] = window.hWin.HR('Repository service configuration');
                popup_dialog_options['path'] = 'widgets/admin/';

                window.hWin.HEURIST4.ui.showRecordActionDialog('repositoryConfig', popup_dialog_options);
                break;
                
            case "menu-files-index":
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordUploadedFilesIndex', popup_dialog_options);
                break;
            case "menu-files-annotations":
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordImportAnnotations', popup_dialog_options);
                break;
            case "menu-records-archive":  // not used
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordArchive');
                break;
            case "menu-import-add-record": // hidden action at the moment (for dashboard)
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordAdd');
                break;
            case "menu-structure-duplicates":
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordFindDuplicates', popup_dialog_options);
                break;
            case "menu-import-get-template":
                popup_dialog_options['path'] = 'widgets/entity/popups/';
                window.hWin.HEURIST4.ui.showRecordActionDialog('rectypeTemplate', popup_dialog_options);
                break;
                
            case "menu-structure-refresh":
            
                window.hWin.HAPI4.EntityMgr.emptyEntityData(null); //reset all cached data for entities
                window.hWin.HAPI4.SystemMgr.get_defs_all( true, window.hWin.document);
                break;

            case "menu-profile-info":
                popup_dialog_options['edit_mode'] = 'editonly';
                popup_dialog_options['rec_ID'] = window.hWin.HAPI4.user_id();
                 // fall through
            case "menu-database-properties":
            case "menu-structure-rectypes":
            case "menu-structure-fieldtypes":
            case "menu-structure-vocabterms":
            case "menu-structure-workflowstages":
            case "menu-structure-mimetypes":
            case "menu-help-bugreport":
            case "menu-profile-tags":
            case "menu-profile-reminders":
            case "menu-profile-files":
            case "menu-profile-groups":
            case "menu-profile-users":
                window.hWin.HEURIST4.ui.showEntityDialog(adata.entity, popup_dialog_options);
                break;
            case "menu-manage-dashboards":
                popup_dialog_options['isViewMode'] = false;
                popup_dialog_options['is_iconlist_mode'] = false;
                popup_dialog_options['onClose'] = function(){
                    setTimeout('$(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE)',1000);
                }; 
                window.hWin.HEURIST4.ui.showEntityDialog(adata.entity, popup_dialog_options);

                break;

            case "menu-database-browse":
                
                popup_dialog_options['select_mode'] = 'select_single';
                popup_dialog_options['onselect'] = function(event, data) {
                        if (data?.selection && data.selection.length === 1) {
                            let db = data.selection[0];
                            if(db.indexOf(window.hWin.HAPI4.sysinfo.database_prefix)==0){
                                db = db.substring(window.hWin.HAPI4.sysinfo.database_prefix.length);
                            }
                            window.open(window.hWin.HAPI4.baseURL + '?db=' + db, '_blank');
                        }
                    };
                
                window.hWin.HEURIST4.ui.showEntityDialog('sysDatabases', popup_dialog_options);
                break;


            case "menu-structure-import":

                window.hWin.HEURIST4.ui.showWdigetDialog('importStructure', popup_dialog_options);
                break;
                
            case "menu-profile-preferences":
                popup_dialog_options['path'] = 'widgets/profile/';
                window.hWin.HEURIST4.ui.showRecordActionDialog('profilePreferences', popup_dialog_options);
                break;
            case "menu-profile-import":
                this.importUsers( popup_dialog_options ); //for admin only
                break;
            case "menu-profile-logout":
                window.hWin.HAPI4.SystemMgr.logout();
                break;
            case "menu-admin-server":                                       
                popup_dialog_options['path'] = 'widgets/admin/';
                window.hWin.HEURIST4.ui.showRecordActionDialog('manageServer', popup_dialog_options);
                break;
/* NOT USED. At the moment it rebuilds titles for entire database or per rty after titlemask edit
            case "menu-manage-rectitles":                                       
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordsTitles', popup_dialog_options);
                break;
*/

            case "menu-help-quick-tips":
                contentURL = window.hWin.HRes('quickTips');
                window.hWin.HEURIST4.msg.showMsgDlgUrl(contentURL, null, 'Tips', {isPopupDlg:true, width:500, height:500});
                break;
                
            case "menu-subset-set":
                //see menu Explore
                let widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('resultList');
                if(widget){
                    widget.resultList('callResultListMenu', 'menu-subset-set'); //call method
                }
                break;
           
            case "menu-help-acknowledgements":
            
                contentURL = window.hWin.HRes('acknowledgementsHeurist');
                window.hWin.HEURIST4.msg.showMsgDlgUrl(contentURL, null, 'Acknowledgements', {isPopupDlg:true, width:500, height:500});
                break;

            case "menu-help-about":

                contentURL = window.hWin.HRes('aboutHeurist');
                window.hWin.HEURIST4.msg.showMsgDlgUrl(contentURL, null, 'About', {isPopupDlg:true, width:500, height:390,
                    open: function( event, ui ) {
                        let $dlg = window.hWin.HEURIST4.msg.getPopupDlg();
                        $dlg.find('.version').text('version '+window.hWin.HAPI4.sysinfo['version']);
                        
                        if(window.hWin.HAPI4.sysinfo.host_logo){

                            $('<div style="height:40px;padding-left:4px;float:right"><a href="'
                                +(window.hWin.HAPI4.sysinfo.host_url?window.hWin.HAPI4.sysinfo.host_url:'#')
                                +'" target="_blank" style="text-decoration:none;color:black;">'
                                +'<label>at: &nbsp;</label>'
                                +'<img src="'+window.hWin.HAPI4.sysinfo.host_logo+'" height="35" align="center"></a></div>')
                            .appendTo($dlg.find('div.host_info'));
                        }                       
                    }                
                });
                break;

            case "menu-help-online":
            
                action.href = window.hWin.HAPI4.sysinfo.referenceServerURL+'?db=Heurist_Help_System&website';
                 // fall through
            default:
                is_supported = this.#handleHrefAction(action, popup_dialog_options);
                break;
        }

        return is_supported;
    }
}
