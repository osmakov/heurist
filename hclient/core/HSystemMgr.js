/**
*  HSystemMgr
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
/* global prepared_params */

    /**
    * @class 
    * System class that responsible for interaction with server in domains:
    *       user/groups information/credentials
    *       saved searches - @todo move to EntityMgr
    *       system info
    *
    * see usr_info.php and sys_structure.php
    *
    * methods:
    *   login        - login and get current user info
    *   logout
    *   reset_password
    *   verify_credentials  - checks whether user is logged in and force_refresh_sys_info in case change roles or update db settings
    * 
    *   sys_info     - get current user info and database settings - used only in hapi.init and on force_refresh_sys_info
    *                  (for details $system->getCurrentUserAndSysInfo) 
    *   sys_info_count - 
    *   save_prefs   - save user preferences  in session
    * 
    *   -----
    *           @later move to EntityMgr
    *   ssearch_get  - get saved searches for current user and all usergroups where user is memeber, or by list of ids
    *   ssearch_save - save saved search in database
    *   ssearch_copy - duplicate
    *   ssearch_delete - delete saved searches by IDs
    *   ssearch_savetree - save saved search treeview data
    *   ssearch_gettree - get saved search treeview data
    *   ------ 
    *          @later move to EntityMgr 
    *   user_get
    *   usr_names
    *   mygroups  - description of current user's Workgroups
    *   user_log  - activity log
    *   user_save
    *   user_wss  - working subset
    *   ------
    *   get_defs     - get the desired database structure definition (used in import definitionss only)
    *   get_defs_all - returns number of records in database, worksets and dashboard info
    * 
    *   ------ 
    *   get_url_content_type - resolve mimetype for given url
    *   get_foldercontent
    *   get_sysfolders
    *   checkPresenceOfRectype - check and download missed rectypes
    *   import_definitions
    *   versionCheck  - checks client software version and db version check
    * 
    *
    * @returns {Object}
    */
class HSystemMgr {
    
  hapi4;
    
  constructor(hapi4) {
    this.hapi4 = hapi4; 
  }
  
  /**
   * @param {Request} request
   * @param {string} request.username - user to log in
   * @param {string} request.password - user's password to verify
   * @param {string} request.session_type - one of 'public', 'shared' or 'remember'
   * @param {callserverCallback} callback - callback function with response parameter HUser object
   */
  login(request, callback) {
    if (request) request.a = 'login';
    window.hWin.HAPI4.callserver('usr_info', request, response => {
           if (response.status == window.hWin.ResponseStatus.OK) {
               this.matomoTrackLogin();
           }

           if (window.hWin.HEURIST4.util.isFunction(callback)) {
               callback(response);
           }
       });
  }  
 
  /**
   * @param {Request} request
   * @param {Request} request.username - user whose password to reset
   * @param {callserverCallback} callback
   */
  reset_password(request, callback) {
       if (request) request.a = 'reset_password';
       window.hWin.HAPI4.callserver('usr_info', request, callback); 
  } 
  
  /**
   * @param {callserverCallback} callback
   */
  logout(callback) {
    window.hWin.HAPI4.callserver('usr_info', { a: 'logout' }, response => {
      if (response.status == window.hWin.ResponseStatus.OK) {
        window.hWin.HAPI4.setCurrentUser(null);
        
        this.matomoTrackLogout();
        
        $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS);

        if (window.hWin.HEURIST4.util.isFunction(callback)) {
          callback(response);
        }
      } else {
        window.hWin.HEURIST4.msg.showMsgErr(response);
      }
    });
  } 
  
  /**
  * 
  *  1) Verify crendentials on server side and checks if they will be upated
  *  2) In case they are changed, returns up-to-date user and sys info
  *  3) In case needed level of credentials is defined it verifies the permissions
  * 
  *  This method should be called before every major action or open popup dialog.
  *  For internal actions use client-side methods of hapi.is_admin, is_member, has_access.
  * 
  * @param {passwordCallback} callback
  * @param {(number|string)} requiredLevel - level of verification required
  *  - `-1`: no verification
  *  - `0`: logged (DEFAULT)
  *  - `groupid`: admin of group  
  *  - `1`: db admin (admin of group #1)
  *  - `2`: db owner
  * @param {string} password_protected - name of password
  * @param {string} password_entered - password entered by the user on the client
  * @param {string} requiredPermission - required permissions; 'add', 'delete', 'add delete'
  * 
  */
  verify_credentials(callback, requiredLevel, password_protected, password_entered, requiredPermission) {

      let requiredMembership = 0; //membership in group

      if (typeof requiredLevel === 'string' && requiredLevel.indexOf(';') > 0) {

          requiredLevel = requiredLevel.split(';');
          requiredMembership = requiredLevel[1];
          requiredLevel = requiredLevel[0];
          if (requiredLevel < 0) requiredLevel = 0;
      }

      requiredLevel = Number(requiredLevel);
      if (requiredLevel < 0) { //no verification required - everyone access

          //however need to check password protection
          if (window.hWin.HEURIST4.util.isempty(password_protected)) {
              //no password protection
              callback(password_entered);
              return;
          } else {
              if (window.hWin.HAPI4.sysinfo['pwd_' + password_protected]) { //system administrator password defined allowing system admin override for specific actions otherwise requiring ownership

                  //
                  window.hWin.HEURIST4.msg.showPrompt(
                      '<div style="padding:20px 0px">'
                      + 'Only an administrator (server manager) or the owner (for<br>'
                      + 'actions on a single database) can carry out this action.<br>'
                      + 'This action requires a special system administrator password (not a normal login password)'
                      + '</div><span style="display: inline-block;padding: 10px 0px;">Enter password:&nbsp;</span>',
                      (password_entered)=>{

                          let on_passwordcheck = 
                          (response)=>{
                              if (response.status == window.hWin.ResponseStatus.OK && response.data == 'ok') {
                                  callback(password_entered);
                              } else {
                                  window.hWin.HEURIST4.msg.showMsgFlash('Wrong password');
                              }
                          };

                          window.hWin.HAPI4.SystemMgr.action_password(
                              { action: password_protected, password: password_entered },
                              on_passwordcheck);

                      },
                      { title: 'Sysadmin override password required', yes: 'OK', no: 'Cancel' }, { password: true });

              } else {
                  window.hWin.HEURIST4.msg.showMsgDlg('This action is not allowed unless a special system administrator password is set - please consult system administrator');
              }
              return;
          }
      }      


      /**
      * Utility method to verify user locally based on membership and permissions.
      *  
      * @param {boolean} is_expired 
      */
      function __verify(is_expired) {

          if ((requiredMembership == 0 || window.hWin.HAPI4.is_member(requiredMembership))
              &&
              window.hWin.HAPI4.has_access(requiredLevel)) {
              //verification is accepted now check for password protection
              window.hWin.HAPI4.SystemMgr.verify_credentials(callback, -1, password_protected, password_entered);
              return;
          }

          let response = {};
          response.sysmsg = 0;
          response.status = window.hWin.ResponseStatus.REQUEST_DENIED;
          response.message = 'To perform this operation you have to be logged in (you may have been logged out due to lack of activity - if so, please reload the page)';

          if (requiredMembership > 0) {
              let sGrpName = '';
              if (window.hWin.HAPI4.sysinfo.db_usergroups?.[requiredMembership]) {
                  sGrpName = ' "' + window.hWin.HAPI4.sysinfo.db_usergroups[requiredMembership] + '"';
              }
              response.message += ' as member of group #' + requiredMembership + sGrpName;

          } else if (requiredLevel == window.hWin.HAPI4.sysinfo.db_managers_groupid) {
              response.message += ' as database administrator';// of group "Database Managers"' 
          } else if (requiredLevel == 2) {
              response.message += ' as database onwer';
          } else if (requiredLevel > 0) {
              let sGrpName = '';
              if (window.hWin.HAPI4.sysinfo.db_usergroups?.[requiredLevel]) {
                  sGrpName = ' "' + window.hWin.HAPI4.sysinfo.db_usergroups[requiredLevel] + '"';
              }
              response.message += ' as administrator of group #' + requiredLevel + sGrpName;
          } else if (requiredLevel == 0 && is_expired) {
              response.message = '';
          }

          if (response.message) {
              window.hWin.HEURIST4.msg.showMsgFlash(response.message, 2000);
          } else {
              //login expired
              window.hWin.HEURIST4.msg.showMsgErr(response, true);
          }
      }


      /**
      * Handles the server response for credential verification.
      * 
      * Adjust user's credentials based on verification, then triggers
      * window.hWin.HAPI4.Event.ON_CREDENTIALS
      */
      function __response_handler(response) {
          if (response.status == window.hWin.ResponseStatus.OK) {
              // Logic to update user credentials and trigger events

              if (response.data.sysinfo) {
                  window.hWin.HAPI4.sysinfo = response.data.sysinfo;
              }

              let is_expired = false;
              if (response.data.currentUser) {

                  let old_id = window.hWin.HAPI4.user_id();

                  window.hWin.HAPI4.setCurrentUser(response.data.currentUser);

                  is_expired = (old_id > 0 && window.hWin.HAPI4.user_id() == 0);

                  //trigger global event ON_CREDENTIALS
                  if (response.data.currentUser.ugr_ID > 0) {
                      $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS);
                  }
              }

              //since currentUser is up-to-date - use client side method
              __verify(is_expired);

          } else {
              window.hWin.HEURIST4.msg.showMsgErr(response, true);
          }
      }  
      
      const VERIFY_LOCALLY = false;

      if (VERIFY_LOCALLY) {
          __verify();
      } else {
          window.hWin.HAPI4.callserver(
              'usr_info',
              { a: 'verify_credentials', permissions: requiredPermission },
              __response_handler   //, callback, password_protected, password_entered, requiredLevel
          );
      }      

  }
  
  repositoryAction(request, callback) {
      window.hWin.HAPI4.callserver('repoController', request, callback);
  }

  databaseAction(request, callback) {
      let controller = 'databaseController';
      if(request.action=='register'){
          controller = 'indexController';
      }
      window.hWin.HAPI4.callserver(controller, request, callback, 600000); //5 minutes
  }

  reportAction(request, callback) {
      //let controller = 'ReportController';
      request.controller = 'ReportController';
      window.hWin.HAPI4.callserver('index', request, callback, 'auto');
  }
  

  /**
  * Returns number of records in database, worksets and dashboard info
  * @param {Function} callback
  */
  sys_info_count(callback) {
      window.hWin.HAPI4.callserver('usr_info', { a: 'sys_info_count' }, response => {
          if (response.status == window.hWin.ResponseStatus.OK) {
              window.hWin.HAPI4.sysinfo['db_total_records'] = response.data[0];
              window.hWin.HAPI4.sysinfo['db_has_active_dashboard'] = response.data[1];
              window.hWin.HAPI4.sysinfo['db_workset_count'] = response.data[2];
              if (callback) callback();
          } else {
              window.hWin.HEURIST4.msg.showMsgErr(response);
          }
      });
  }  

  /**
  * Get current user and global database settings
  * used in hapi.init and on force_refresh_sys_info
  * 
  * @param {sysinfoCallback} callback
  */
  sys_info(callback) {
      let request = { a: 'sysinfo' };

      if (typeof prepared_params!=="undefined" && prepared_params?.guest_data) {
          request['is_guest'] = 1;
      }

      this.hapi4.callserver('usr_info', request, response => {
          let success = response.status == window.hWin.ResponseStatus.OK;
          if (success) {
              if (response.data.currentUser) {
                  window.hWin.HAPI4.setCurrentUser(response.data.currentUser);
              }
              if (response.data.sysinfo) {
                  window.hWin.HAPI4.sysinfo = response.data.sysinfo;
              }
          } else {
              window.hWin.HEURIST4.msg.showMsgErr(response);
          }
          if (callback) callback(success);
      });
  }

  /**
  * Save user preferences
  * @param {Object} request
  * @param {callserverCallback} callback
  */
  save_prefs(request, callback) {
      if (request) request.a = 'save_prefs';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }  
  
  /**
  * Set/Clear work subset
  * @param {Object} request
  * @param {callserverCallback} callback
  */
  user_wss(request, callback) {
      if (request) request.a = 'user_wss';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Save user profile info in the database
  * @param {Object} request
  * @param {callserverCallback} callback
  */
  user_save(request, callback) {
      if (request) request.a = 'usr_save';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Get user profile info from the database
  * @param {Object} request
  * @param {callserverCallback} callback
  */
  user_get(request, callback) {
      if (request) request.a = 'usr_get';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Get user full names for IDs
  * @param {Object} request
  * @param {(string|Array)} request.UGrpID - comma-separated list or Array of user ID numbers
  * @param {callserverCallback} callback
  */
  usr_names(request, callback) {
      let ugrp_ids = request.UGrpID;
      let sUserNames = {};
      request.UGrpID = [];

      if (ugrp_ids >= 0) {
          ugrp_ids = [ugrp_ids];
      } else {
          ugrp_ids = !Array.isArray(ugrp_ids) ? ugrp_ids.split(',') : ugrp_ids;
      }

      for (let idx in ugrp_ids) {
          let usr_ID = Number(ugrp_ids[idx]);
          let sUserName = this.getUserNameLocal(usr_ID);

          if (sUserName) {
              sUserNames[usr_ID] = sUserName;
          } else {
              request.UGrpID.push(usr_ID);
          }
      }

      if (request.UGrpID.length == 0) {
          callback.call(this, { status: window.hWin.ResponseStatus.OK, data: sUserNames, context: request.context });
      } else {
          if (request) request.a = 'usr_names';
          window.hWin.HAPI4.callserver('usr_info', request, context => {
              if (context.status == window.hWin.ResponseStatus.OK) {
                  sUserNames = { ...sUserNames, ...context.data };
                  callback.call(this, { status: window.hWin.ResponseStatus.OK, data: sUserNames, context: context.context });
              } else {
                  callback.call(this, { status: context.status });
              }
          });
      }
  }

  /**
  * Returns user or group name from local cache
  * @param ugrp_id
  */
  getUserNameLocal(ugrp_id) {
      let usr_ID = Number(ugrp_id);
      let sUserName = null;

      if (usr_ID == 0) {
          sUserName = window.hWin.HR('Everyone');
      } else if (usr_ID == window.hWin.HAPI4.currentUser['ugr_ID']) {
          sUserName = window.hWin.HAPI4.currentUser['ugr_FullName'];
      } else if (window.hWin.HAPI4.sysinfo.db_usergroups && window.hWin.HAPI4.sysinfo.db_usergroups[usr_ID]) {
          sUserName = window.hWin.HAPI4.sysinfo.db_usergroups[usr_ID];
      }

      return sUserName;
  }

  /**
  * Returns detailed description of groups for current user
  * @param {mygroupsCallback} callback
  */
  mygroups(callback) {
      window.hWin.HAPI4.callserver('usr_info', { a: 'groups' }, callback);
  }

  /**
  * Log activity of user in the system
  * @param {string} activity underscore-separated string of actions to log
  * @param {string} suplementary info
  */
  user_log(activity, suplementary) {
      const log_actions = ['VisitPage']; //'editRec', 
      const log_prefix = ['db', 'st', 'prof', 'cms', 'imp', 'sync', 'exp', 'configure', 'rec', 'hlp', 'search'];
      const action_parts = activity.indexOf('_') > 0 ? activity.split('_') : [];

      if (
          log_actions.includes(activity) ||
          (action_parts.length > 0 && log_prefix.includes(action_parts[0].toLowerCase()))
      ) {
          let category = ''
          if (action_parts.length > 0) {
              category = action_parts[0];
              activity = '';
              for (let i = 1; i < action_parts.length; i++) {
                  action_parts[i] = action_parts[i].charAt(0).toUpperCase() + action_parts[i].slice(1);
                  activity = activity + action_parts[i];
              }
          }
          
          if(window._paq){
          
              //matomo
              if(activity=='VisitPage'){
                  
                    this.matomoTrackNewPage('web', suplementary);
                  
              }else if(activity!='editRec'){
                    if(category=='db'){
                        category='Database';
                    }else if(category=='st'){
                        category='Structure';
                    }else if(category=='prof'){
                        category='Profile';
                    }else if(category=='imp'){
                        category='Import';
                    }else if(category=='exp'){
                        category='Export';
                    }else if(category=='rec'){
                        category='Record';
                    }else if(category=='hlp'){
                        category='Help';
                    }
                    
                    category = category.charAt(0).toUpperCase() + category.slice(1);
                    
                    let value;
                    if(window.hWin.HEURIST4.util.isPositiveInt(suplementary)){
                        value = suplementary;
                    }
                    if(!activity){
                        activity = 'TBD';
                    }
                  
                    this.matomoTrackEventAction(category, activity, undefined, value);
              }
          
          }else{
            let request = { a: 'usr_log', activity: (category+activity), suplementary: suplementary, user: this.hapi4.user_id() };
            this.hapi4.callserver('usr_info', request);
          }
      }
  }

  /**
  * 
  */
  matomoTrackInit(pageType, value){

      if(!window._paq){
          return;
      }

      //?? _paq.push(['setCustomDimension', customDimensionId = 1, customDimensionValue = 'database']);
      //per page
      _paq.push(['setCustomDimension', 1, this.hapi4.database ]);  
      _paq.push(['setCustomDimension', 2, pageType ]); // web|tpl|hml|view|edit|adm
      _paq.push(['setCustomDimension', 3, this.hapi4.getLocale() ]);  
      _paq.push(['setCustomDimension', 4, (pageType=='web')?value:'' ]);  //website id

      //per visit
      const usrType = this.hapi4.getUserType()
      if(usrType=='visitor'){
        _paq.push(['resetUserId']);
      }else{  
        _paq.push(['setUserId', this.hapi4.currentUser['ugr_eMail'] ]); //unique email
      }
      _paq.push(['setCustomDimension', 5, usrType ]); // owner, admin, manager, user, guest, visitor


      // define class name and assign it to download links - to be tracked
      //_paq.push(['setDownloadClasses', "file-download"]); 
      // or manually
      //_paq.push(['trackLink', 'https://heuristref.net/file=d2332423c', 'download']);

      this.matomoTrackNewPage(pageType, value);

  }
  
  /**
  * Track new page view (for smarty, web, recordview, recordedit)
  * 
  * @param pageType {string} web|tpl|view|edit|adm (hml) or startup
  * @param value {string} suplementary info
  * @param title {string} page title
  */
  matomoTrackNewPage(pageType, value, title){
      
        if(!window._paq){
            return;
        }
        
        let pageURL;
        if(pageType=='adm'){
            pageURL = '/?db=' + this.hapi4.database;
        }else if(pageType=='startup'){
            pageURL = '/startup';
        }else {
            pageURL = '/'+this.hapi4.database+'/'+pageType; //this.hapi4.baseURL+
            if(value){
                pageURL = pageURL+'/'+value;
            }
        }
      
        _paq.push(['setCustomUrl', pageURL ]);
        if(title){
            _paq.push(['setDocumentTitle', title]);
        }
        _paq.push(['trackPageView']);      
//  _paq.push(['enableLinkTracking']);  

  }

  /**
  * Track Event Action
  */
  matomoTrackEventAction(category, action, name, value){
        if(!window._paq){
            return;
        }
        //trigger event
        //Event Category
        //Event Action
        //Event Name
        //Event Value
        let eventParams = ['trackEvent', category, action, name];
        if(value>0){
            eventParams.push(value);
        }
        _paq.push(eventParams);      
  }

  /**
  * ON LOGOUT
  */
  matomoTrackLogout(){
      if(!window._paq){
          return;
      }

      // User has just logged out, we reset the User ID
      _paq.push(['resetUserId']);

      // we also force a new visit to be created for the pageviews after logout
      _paq.push(['appendToTrackingUrl', 'new_visit=1']); 

      _paq.push(['trackPageView']);

      // we finally make sure to not again create a new visit afterwards (important for Single Page Applications)
      _paq.push(['appendToTrackingUrl', '']); 

  }

  matomoTrackLogin(){
        if(!window._paq){
            return;
        }

      const usrType = this.hapi4.getUserType()
      if(usrType=='visitor'){
        _paq.push(['resetUserId']);
      }else{  
        _paq.push(['setUserId', this.hapi4.currentUser['ugr_eMail'] ]); //unique email
      }
      _paq.push(['setCustomDimension', 5, usrType ]); // owner, admin, manager, user, guest, visitor

      _paq.push(['trackPageView']);
  }
  
  
  /**
  * Verify special system passwords for password-protected actions
  * @param {Object} request
  * @param {callserverCallback} callback
  */
  action_password(request, callback) {
      if (request) request.a = 'action_password';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Get saved searches for current user and all user groups where user is a member
  * @param {Object} [request]
  * @param {number} [request.UGrpID] - ID of user group
  * @param {ssearch_getCallback} callback
  */
  ssearch_get(request, callback) {
      if (!request) request = {};
      request.a = 'svs_get';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Save a Heurist query in the database
  * @param {Object} request
  * @param {number} [request.svs_ID] (not specified if ADD new search)
  * @param {string} request.svs_Name - name of saved search
  * @param {string} request.svs_Query - Heurist query that defines the saved search
  * @param {number} request.svs_UGrpID - user/group ID under which search should be saved
  * @param {callserverCallback} callback
  */
  ssearch_save(request, callback) {
      if (request) request.a = 'svs_save';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Duplicate saved search
  * @param {Request} request
  * @param {number} request.svs_ID - id of search to duplicate
  * @param {callserverCallback} callback
  */
  ssearch_copy(request, callback) {
      if (request) request.a = 'svs_copy';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Delete saved searches by ID
  * @param {Request} request
  * @param {string} request.ids - comma-separated list of ids
  */
  ssearch_delete(request, callback) {
      if (request) request.a = 'svs_delete';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Save nested hierarchy of saved searches
  * @param {Request} request
  * @param {Object} request.data - json representation of search tree
  * @param {callserverCallback} callback
  */
  ssearch_savetree(request, callback) {
      if (request) request.a = 'svs_savetree';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Retrieve nested hierarchy of saved searches.
  * @param {Request} request
  * @param {string} [request.UGrpID] - optional: user group ID whose tree you wish to retrieve
  * @param {callserverCallback} callback
  */
  ssearch_gettree(request, callback) {
      if (request) request.a = 'svs_gettree';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }  

  /**
  * Get the desired database structure definition
  * @param {Request} request
  * @param {string} [request.terms] comma-separated list of term ids, or 'all'
  * @param {string} [request.rectypes] comma-separated list of rectype ids, or 'all'
  * @param {string} [request.detailtypes] comma-separated list of detailtype ids, or 'all'
  * @param {number} [mode] applied for rectypes: 0 only names (default), 1 only structure, 2 - both, 3 - all
  */
  get_defs(request, callback) {
      window.hWin.HAPI4.callserver('sys_structure', request, callback);
  }

  /**
  * Wrapper for EntityMgr.refreshEntityData
  * @param {boolean} is_message - whether to show message to user after refresh
  * @param {any} document - unused
  * @param {Function} callback
  */
  get_defs_all(is_message, document, callback) {
      window.hWin.HEURIST4.msg.bringCoverallToFront();

      window.hWin.HAPI4.EntityMgr.refreshEntityData('force_all', success => {
          window.hWin.HEURIST4.msg.sendCoverallToBack();

          if (success && is_message === true) {
              let $dlg = window.hWin.HEURIST4.msg.showMsgDlg('Database structure definitions refreshed.');
              $dlg.parent('.ui-dialog').css({ top: 150, left: 150 });
          }

          if (window.hWin.HEURIST4.util.isFunction(callback)) callback(success);
      });
  }

  /**
  * Resolve mimetype for a given URL
  * @param {string} url
  * @param {callserverCallback} callback
  */
  get_url_content_type(url, callback) {
      let request = { a: 'get_url_content_type', url: url };
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Returns list of files for given folders
  * @param {string|Array.<string>} folders single folder or array of folders to search
  * @param {callserverCallback} callback
  */
  get_foldercontent(source, exts, callback) {
      let request = { a: 'foldercontent', source: source, exts: exts };
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }  

  /**
  * Check if current server + db has access to ESTC lookups
  * @param {Request} [request] - if not provided, current db & server are checked
  * @param {callserverCallback} callback 
  */
  check_allow_estc(request, callback) {
      if (!request) {
          request = { a: 'check_allow_estc', db: window.hWin.HAPI4.database };
      }
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Check if current server has an alpha build setup
  * @param {Request} [request]
  * @param {callserverCallback} callback 
  */
  check_for_alpha(request, callback) {
      if (!request) {
          request = { a: 'check_for_alpha' };
      }
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Get user notifications, if any
  * @param {Request} [request] 
  * @param {callserverCallback} callback 
  */
  get_user_notifications(request, callback){
      if(!request){
          request = { a: 'get_user_notifications' };
      }

      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Get object of custom formats for the TinyMCE editor
  * @param {Request} request 
  * @param {callserverCallback} callback 
  */
  get_tinymce_formats(request, callback){
      if(!request) request = {a: 'get_tinymce_formats'};

      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Use Deepl to translate string
  * @param {Request} request 
  * @param {callserverCallback} callback 
  */
  translate_string(request, callback){
      if(!request.a) request = {a: 'translate_string'};

      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Check if the provided databases are available on the current server
  * @param data - array (registred ID => database name)
  * @param {callserverCallback} callback 
  * @returns 
  */
  check_for_databases(data, callback){

      if(!data){
          window.hWin.HEURIST4.msg.showMsgErr({
              message: 'The list of databases to be checked is missing<br>'
              +'Please contact the Heurist team.',
              error_title: 'Missing database list',
              status: window.hWin.ResponseStatus.INVALID_REQUEST
          });
          return false;
      }

      let request = {
          a: 'check_for_databases', 
          data: JSON.stringify(data), 
          db: window.hWin.HAPI4.database
      };

      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Calculate and return the numbers of days, months, and years between two dates
  * @param data - object containing earliest and latest dates
  * @param {callserverCallback} callback
  */
  get_time_diffs(data, callback){

      if(!data || !data.early_date || !data.latest_date){
          window.hWin.HEURIST4.msg.showMsgErr({
              message: 'Both an earliest and latest date are required.',
              error_title: 'Missing dates',
              status: window.hWin.ResponseStatus.INVALID_REQUEST
          });
          return false;
      }

      let request = {
          a: 'get_time_diffs',
          data: JSON.stringify(data),
          db: window.hWin.HAPI4.database
      };

      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Manipulate folders within HEURIST_FILESTORE_DIR on the server
  * @param {Request} [request] 
  * @param {string} [request.operation] - 'list', 'rename' or 'delete'; defaults to 'list'
  * @param {string} [request.root_dir] - directory to search; defaults to `HEURIST_FILESTORE_DIR`
  * @param {callserverCallback} callback 
  */
  get_sysfolders(request, callback) {
      if (!request) request = {};
      if (!request.a) request.a = 'folders';
      if (!request.operation) request.operation = 'list';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
   * Upload file to Nakala
   * @param {Request} request 
   * @param {callserverCallback} callback 
   */
  upload_to_nakala(request, callback){
      if (!request) callback.call(this, false);
      if (!request.a) request.a = 'upload_file_nakala';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * 1. verifies that given rty_IDs (concept codes) exist in this database
  * 2. If rectype is missed - download from given db_ID (registration ID)
  * 3. Show warning of info report
  * 
  * @param {Array.<string>} rty_IDs - array of concept codes
  * @param {number} databaseID - registratiion ID of source database. If it is not defined, it takes #2 by default
  * @param {(string|boolean)} message - additional (context explanatory) message for final report, if false - without message
  * @param {Function} callback
  * @param {boolean} force_refresh - treats all concepts as undefined, assigning new codes to all
  * @returns {boolean|number}
  *  - `0`: at least one of the passed rty_IDs was not defined and was assigned a concept code
  *  - `true`: all record types are in this database
  *  - `false`: parameter `rty_IDs` was missing
  */
  checkPresenceOfRectype(rty_IDs, databaseID, message, callback, force_refresh) {

      if (!rty_IDs) {
          if (window.hWin.HEURIST4.util.isFunction(callback)) {
              callback.call();   
          }
          return false;
      }

      if (!Array.isArray(rty_IDs)) {
          rty_IDs = [rty_IDs];
      }

      //check what rectypes are missed in this database                  
      let missed = [];

      if (force_refresh) {

          missed = rty_IDs;

      } else {

          rty_IDs.forEach(function(rty_ID){
              let local_id = $Db.getLocalID('rty', rty_ID);
              if (!(local_id > 0)) {
                  //not found
                  missed.push(rty_ID);
              }
          });
      }

      //all record types are in this database
      if (missed.length == 0) {
          if (window.hWin.HEURIST4.util.isFunction(callback)) {
              callback.call();   
          }
          return true;
      }

      //by default we take Heurist_Core_Definitions id#2
      if (!(databaseID > 0)) {
          databaseID = 2;   
      }

      if (message == false) {
          //downlaod unconditionally
          window.hWin.HAPI4.SystemMgr.import_definitions(databaseID, missed, 'rectype', false, true, callback);
          return 0;
      }

      window.hWin.HEURIST4.msg.showMsgDlg(message
          + '<br>'
          + window.hWin.HR('Click "Import" to get these definitions'),
          {
              'Import': function () {
                  let $dlg2 = window.hWin.HEURIST4.msg.getMsgDlg();
                  $dlg2.dialog('close');

                  window.hWin.HEURIST4.msg.bringCoverallToFront();
                  window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Import definitions'), 10000);

                  //import missed record types
                  window.hWin.HAPI4.SystemMgr.import_definitions(databaseID, missed, 'rectype', false, true,
                      function (response) {
                          window.hWin.HEURIST4.msg.sendCoverallToBack();
                          let $dlg2 = window.hWin.HEURIST4.msg.getMsgFlashDlg();
                          if ($dlg2.dialog('instance')) $dlg2.dialog('close');

                          if (response.status == window.hWin.ResponseStatus.OK) {
                              if (window.hWin.HEURIST4.util.isFunction(callback)) callback.call();
                          } else {
                              window.hWin.HEURIST4.msg.showMsgErr(response);
                          }
                  });

              },
              'Skip': function () {
                  let $dlg2 = window.hWin.HEURIST4.msg.getMsgDlg();
                  $dlg2.dialog('close');
                  if (window.hWin.HEURIST4.util.isFunction(callback)) callback.call();
              },
              'Cancel': function () {
                  let $dlg2 = window.hWin.HEURIST4.msg.getMsgDlg();
                  $dlg2.dialog('close');
              }
          },
          window.hWin.HR('Definitions required'));

      return 0;
  }


  /** 
  * imports database defintions 
  * @param {number} databaseID - source database 
  * @param {Array.<string|number>} definitionID - array of Rectype ids or Concept Codes to be imported
  * @param {boolean} is_rename_target - should rectype/concept labels be overwritten with labels imported from the source database?
  * @param {string} entity - what is being imported? {rectype|detailtype|term}
  * @param {callserverCallback} callback - applied to response after entity definitions are updated
  */
  import_definitions(databaseID, definitionID, entity, is_rename_target, is_conservative, callback) {

      /** @type {Request} */
      let request = {
          databaseID: databaseID,
          definitionID: definitionID,
          conservative: is_conservative ?1 :0,
          is_rename_target: is_rename_target ? 1 : 0,
          db: window.hWin.HAPI4.database, import: entity
      };

      window.hWin.HAPI4.callserver('sys_structure', request, function (response) {

          if (response.status == window.hWin.ResponseStatus.OK) {

              //refresh local definitions
              if (response.defs) {
                  if (response.defs.sysinfo) window.hWin.HAPI4.sysinfo = response.defs.sysinfo; //constants

                  if (response.defs.entities)
                  for (let entityName in response.defs.entities) {
                      //refresh local definitions
                      window.hWin.HAPI4.EntityMgr.setEntityData(entityName,
                          response.defs.entities);
                  }
              }

              window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);
          }
          if (window.hWin.HEURIST4.util.isFunction(callback)) {
              callback(response);
          }
      });
  }

  /** 
  * Checks client software version and db version check
  * @todo move to sys utility (?)
  * 1. Checks client software version and 
  * 2. Checks Database version and runs update script
  */
  versionCheck() {

      //@todo define parameter in layout "production=true"
      if (!window.hWin.HAPI4.is_publish_mode) {

          let version_in_cache = window.hWin.HAPI4.get_prefs_def('version_in_cache', null);
          let need_exit = false;

          //
          // version of code to compare with server provided - to avoid caching issue
          //
          if (window.hWin.HAPI4.has_access() && window.hWin.HAPI4.sysinfo['version']) {
              if (version_in_cache) {
                  need_exit = (window.hWin.HEURIST4.util.versionCompare(version_in_cache,
                      window.hWin.HAPI4.sysinfo['version']) < 0);
                  if (need_exit) { // -1=older code in cache, -2=newer code in cache, +1=same code version in cache
                      // show lock popup that forces to clear cache
                      window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL + 'hclient/widgets/cpanel/versionCheckMsg.html',
                          {}/* no buttons */, null,
                          {
                              hideTitle: true, closeOnEscape: false,
                              open: function (event, ui) {
                                  let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                                  $dlg.find('#version_cache').text(version_in_cache);
                                  $dlg.find('#version_srv').text(window.hWin.HAPI4.sysinfo['version']);
                              }
                      });
                  }
              }
              if (version_in_cache!=window.hWin.HAPI4.sysinfo['version']) {
                  window.hWin.HAPI4.save_pref('version_in_cache', window.hWin.HAPI4.sysinfo['version']);
              }
              if (need_exit) return true;

              let res = window.hWin.HEURIST4.util.versionCompare(window.hWin.HAPI4.sysinfo.db_version_req,
                  window.hWin.HAPI4.sysinfo.db_version);
              if (res == -2) { //-2= db_version_req newer
                  // show lock popup that forces to upgrade database
                  window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL + 'hclient/widgets/cpanel/versionDbCheckMsg.html',
                      {
                          'Upgrade': function () {
                              top.location.href = (window.hWin.HAPI4.baseURL + 'admin/setup/dbupgrade/upgradeDatabase.php?db=' + window.hWin.HAPI4.database);
                          }
                      }, null,
                      {                                   
                          hideTitle: false, closeOnEscape: false,
                          open: function (event, ui) {
                              let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                              $dlg.find('#version_db').text(window.hWin.HAPI4.sysinfo.db_version);
                              $dlg.find('#version_min_db').text(window.hWin.HAPI4.sysinfo.db_version_req);
                              $dlg.find('#version_srv').text(window.hWin.HAPI4.sysinfo['version']);
                          }
                  });

              }
          }

      }
      return false;
  }
  
  
}