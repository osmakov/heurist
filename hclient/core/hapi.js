/**
* Main class for Heurist 
*   it stores major config info
*   local db definitions
*   and provides methods to call server side 
*
* Constructor:
* @param _db - database name, if omit it takes from url parameter
* @param _oninit - callback function, obtain parameter true if initialization is successeful
* @returns hAPI Object
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

/* global ActionHandler, HSystemMgr */

/*

Properties:
    baseURL
    baseURL_pro
    iconBaseURL - url for record type icon (rty_ID to be added)
    database - current database name
    sysinfo
    is_publish_mode - false if Heurist is inited via main index.php and layout is not from the set of application (DH, EN, WebSearch)

Localization routines (assigned to window.hWin)

    HR  returns localized string
    HRA = localize all elements with class slocale for given element
    HRes = returns url or loads content for localized resource
    HRJ = returns localized value for json (options in widget)

LayoutMgr   HLayout object (@todo replace to new version from CMS)

Classes for server interaction

    SystemMgr - user credentials and system utilities
    RecordMgr - Records SCRUD actions    
    RecordSearch - wrapper for RecordMgr.search method
    EntityMgr - SCRUD for database defenitions and user/groups

*/
function hAPI(_db, _oninit, _baseURL) { //, _currentUser
    const _className = "HAPI",
        _version = "0.4";
    let _database = null, //same as public property  @toremove      

        _region = null, //current region ISO639-2 (alpha3) in uppercase
        _regional = {}, //localization resources

        _guestUser = { ugr_ID: 0, ugr_FullName: 'Guest' },
        _listeners = [],
        _is_callserver_in_progress = false,
        _last_check_dbcache_relevance = 0,

        _use_debug = true,
        
        
       
        actionHandler = null;
                

    /**
    * initialization of hAPI object
    *  1) define paths from top.location
    *  2) takes regional from  localization_xxx.txt
    *
    * @param _db - database name, if omit it takes from url parameter
    * @param _oninit - callback function, obtain parameter true if initialization is successeful
    * @param _baseURL - defined for embed mode only when location of heurist client is differend from heurist server 
    *
    */
    function _init(_db, _oninit, _baseURL) { //, _currentUser) {

        that.SystemMgr = new HSystemMgr(that);
        //2024-12-08 that.LayoutMgr2 = new HLayoutMgr();

        //@todo - take  database from URL
        if (_db) {
            _database = _db;
        } else {
            _database = window.hWin.HEURIST4.util.getUrlParameter('db');
        }

        detectBaseURL();
        
        that.database = _database;

        if (!window.hWin.HR) {
            window.hWin.HR = that.setLocale('ENG');
        }

        if (!window.hWin.HEURIST4.util.isFunction(that.fancybox)) {
            that.fancybox = $.fn.fancybox; //to call from iframes
        }

        // layout and configuration arrays are defined (from layout_default.js)    
        if (typeof HLayout !== 'undefined' && window.hWin.HEURIST4.util.isFunction(HLayout)
            && typeof cfg_widgets !== 'undefined' && typeof cfg_layouts !== 'undefined') {
            that.LayoutMgr = new HLayout();
        }
        if (typeof HRecordSearch !== 'undefined' && window.hWin.HEURIST4.util.isFunction(HRecordSearch)) {
            that.RecordSearch = new HRecordSearch();
        }

        if (!window.onresize) {
            that._delayOnResize = 0;
            function __trigger() {
                window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_WINDOW_RESIZE);
            };
            window.onresize = function () {
                if (that._delayOnResize) clearTimeout(that._delayOnResize);
                that._delayOnResize = setTimeout(__trigger, 1000);
            }
        }

        that.dbSettings = {};

        // Get current user if logged in, and global database settings
        // see usr_info.php sysinfo method  and then system->getCurrentUserAndSysInfo
        if (that.database) {
            
            that.SystemMgr.sys_info(function (success) {
                if (success) {
                    that.baseURL = window.hWin.HAPI4.sysinfo['baseURL'];
                    that.baseURL_pro = window.hWin.HAPI4.sysinfo['baseURL_pro'];
                    // @TODO: rename to rtyIconURL 
                    that.iconBaseURL = that.baseURL + '?db=' + _database + '&icon=';
                    
                    //loads list of actions                 window.hWin.document
                    if(typeof ActionHandler !== 'undefined'){
                        (async () => {
                            that.actionHandler = new ActionHandler();
                            await that.actionHandler.loadActionsFromFile();
                        })();                        
                    }
                    
                    
                    let lang = window.hWin.HEURIST4.util.getUrlParameter('lang');
                    if (lang) {
                        //save in preferences
                        window.hWin.HAPI4.save_pref('layout_language', lang);
                    } else {
                        lang = window.hWin.HAPI4.get_prefs_def('layout_language', 'ENG');
                    }
                    window.hWin.HR = that.setLocale(lang); //change current locale
                    window.hWin.HRA = that.HRA; //localize all elements with class slocale for given element
                    window.hWin.HRes = that.HRes; //returns url or content for localized resource (help, documentation)
                    window.hWin.HRJ = that.HRJ; // returns localized value for json (options in widget)

                }
                _oninit(success);
            });
        } else if (_oninit) {
                _oninit(false);
        }
    }
    
    // we obtain correct values from server side 
    // see window.hWin.HAPI4.sysinfo['baseURL']; and window.hWin.HAPI4.sysinfo['baseURL_pro'];
    //
    // finds and assign  installDir   baseURL    baseURL_pro
    // 
    function detectBaseURL(){
         let installDir = '';

        if(window.hWin.location.host.indexOf('.huma-num.fr')>0 && window.hWin.location.host!=='heurist.huma-num.fr'){
            installDir = '/heurist/';
        }else{

            let script_name = window.hWin.location.pathname;
            if(script_name.endsWith('/web') || script_name.endsWith('/website')) script_name = script_name + '/'; //add last slash

            //actions for redirection https://hist/heurist/[dbname]/web/
            if(script_name.search(/\/([A-Za-z0-9_]+)\/(website|web|hml|tpl|view|edit|adm|test)\/.*/)>=0){
                installDir = script_name.replace(/\/([A-Za-z0-9_]+)\/(website|web|hml|tpl|view|edit|adm|test)\/.*/, '')+'/';
                if(installDir=='/') installDir = '/h6-alpha/';/* to change back to '/heurist/'; */
            }else{
                //removed top folders: applications|common|search|records|
                installDir = script_name.replace(/(((\?|admin|context_help|export|hapi|hclient|hserv|import|startup|test|redirects|viewers|help|ext|external)\/.*)|(index.*|test.php))/, ""); // Upddate in utils_host.php also
            }
        }

        that.installDir = installDir; //to detect development or production version 
        if (!_baseURL) _baseURL = window.hWin.location.protocol + '//' + window.hWin.location.host + installDir;
        that.baseURL = _baseURL;
        
        //detect production version
        if (installDir && !installDir.endsWith('/heurist/')) {
            //replace devlopment folder to production one (ie h6-ij to heurist)
            installDir = installDir.split('/');
            let i = installDir.length-1;
            while(i>0 && installDir[i]=='') i--;
            installDir[i] = 'heurist';
            installDir = installDir.join('/');
            
            that.baseURL_pro = window.hWin.location.protocol + '//' + window.hWin.location.host + installDir;
        } else {
            that.baseURL_pro = _baseURL;
        }        
        
    }
    
    function _getKeyCount(data, level) {
        level = level || 0;
        let _key_count = 0;
        for (let k in data) {
            Object.hasOwn(data, k) && _key_count++;
            if(typeof data[k] === 'object'){
                _key_count = _key_count + _getKeyCount(data[k], level + 1);   
            }
        }
        return _key_count;
    }
    /**
     * Signature for _callserver callback
     * 
     * A complete list of status codes can be found in `hclient/core/detectHeurist.js`.
     * They are stored in `hWin.ResponseStatus` when Heurist is initialised in the window.
     * 
     * @callback callserverCallback
     * @param {{status: string, message: string, data: Object}} response - server response
     */
    
    /**
     * Request to Heurist server, specifying action to be taken
     * @typedef {Object} Request
     * @property {string} a - action to be performed
     * @property {string=} db - database to be affected
     */

    /**
     * internal function see HSystemMgr, HRecordMgr - ajax request to server
     *
     * @param {string} action - name of php script in hserv/controller folder on server side
     * @param {Request} request - data to be sent to server side
     * @param {callserverCallback} callback - callback, which receives object with following properties:
     * - `status`: a complete list of possible statuses can be found in `hclient/core/detectHeurist.js`
     * - `message`: error message or Ajax response
     * - `data`: data returned for request
     */
    function _callserver(action, request, callback, timeout=0) {

        _is_callserver_in_progress = true;
        
        if(window.hWin.HAPI4 && action!='entityScrud' && action!='usr_info'
            && (new Date().getTime())-_last_check_dbcache_relevance> 3000){ //7 seconds
            _last_check_dbcache_relevance = new Date().getTime();
            
            //ignore if record structure editor or Design panel is opened
            const can_check = $('div.defRecStructure').length==0 && 
            $('.ui-menu6-section.ui-heurist-design').css('display')!='block';

            if(can_check)
            {
                window.hWin.HAPI4.EntityMgr.relevanceEntityData(function(){
                    _callserver(action, request, callback, timeout);
                });
                return;
            }
        }
        

        if (!request.db) {
            request.db = _database;
        }
        if (request.notes) {
            request.notes = null; //unset to reduce traffic
        }
        if(!action){
            action = 'index';
        }

        //set d=0 and c=0 to disable debug  https://www.nusphere.com/kb/technicalfaq/faq_dbg_related.htm
        request.DBGSESSID = (_use_debug) ? '425944380594800002;d=1,p=0,c=1' : '425944380594800002;d=0,p=0,c=0';

        let url = that.baseURL + "hserv/controller/" + action + ".php";
        
        //@todo - count keys in request to avoid "Input variables exceeded 1000" on server side
        let cnt = _getKeyCount(request);
        if(cnt>999){
            if(that.baseURL.indexOf('127.0.0.1')>0){
                alert('Input variables exceeded 1000: '+cnt+' ,'+action);              
            }
            console.error('Input variables exceeded 1000',cnt);
        }

        let request_code = { script: action, action: request.a };
        //note jQuery ajax does not properly in the loop - success callback does not work often
        let ajax_options = {
            url: url,
            type: "POST",
            data: request,
            dataType: "json", //data to be expected from server
            cache: false,
            xhrFields: {
                withCredentials: true
            },
            //Content-type: application/json
            error: function (jqXHR, textStatus, errorThrown) {

                _is_callserver_in_progress = false;

                let response;
                if (jqXHR.responseJSON && jqXHR.responseJSON.status) {
                    response = jqXHR.responseJSON;
                } else {
                    response = window.hWin.HEURIST4.util.interpretServerError(jqXHR, url, request_code);
                }

                if (window.hWin.HEURIST4.util.isFunction(callback)) {
                    callback(response);
                }
               
            },
            success: function (response, textStatus, jqXHR) {

                _is_callserver_in_progress = false;

                if (window.hWin.HEURIST4.util.isFunction(callback)) {
                    if ($.isPlainObject(response)) {
                        response.request_code = request_code;
                    }
                    callback(response);
                }

                /*check response for special marker that forces to reload user and system info
                //after update sysIdentification, dbowner and user role
                if(response && 
                    (response.status == window.hWin.ResponseStatus.OK) && 
                     response.force_refresh_sys_info) {
                         that.SystemMgr.sys_info(function(success){
                             
                         });
                }*/

            },
            fail: function (jqXHR, textStatus, errorThrown) {

                _is_callserver_in_progress = false;

                let response = window.hWin.HEURIST4.util.interpretServerError(jqXHR, url, request_code);

                if (window.hWin.HEURIST4.util.isFunction(callback)) {
                    callback(response);
                }
            }
        };
        
        if(timeout>0){ //default 120000 - 120 seconds
            ajax_options['timeout'] = timeout;
        }
        
        $.ajax(ajax_options);

    }

    /**
     * Clears records that were affected by the action from the browseRecordCache, then
     * triggers HAPI4.Event.ON_REC_UPDATE
     * 
     * @param {Object} response
     * @param {string} response.status - status code of the response, see hclient/core/detectHeurist.js
     * @param {(string|Array)=} response.affectedRty - comma-seperated list or array of record ids
     * @param {Function=} callback
     */
    function _triggerRecordUpdateEvent(response, callback) {
        if (response && response.status == window.hWin.ResponseStatus.OK) {
            // $Db is alias for HEURIST4.dbs, defined in hclient/core/utils_dbs.js
            if ($Db) $Db.needUpdateRtyCount = 1;

            if (response.affectedRty && window.hWin.HEURIST4.browseRecordTargets) {
                    //clear record browse cache

                    let rtys = [];
                    if (Array.isArray(response.affectedRty)) {
                        rtys = response.affectedRty;
                    } else if (typeof response.affectedRty === 'string') {
                        rtys = response.affectedRty.split(',');
                    } else {
                        rtys = [response.affectedRty];
                    }
                    rtys.push('any');
                    $.each(rtys, function (i, id) {
                        if (window.hWin.HEURIST4.browseRecordTargets[id]) {
                            id = '' + id;
                            $.each(window.hWin.HEURIST4.browseRecordTargets[id], function (j, key) {
                                if (window.hWin.HEURIST4.browseRecordCache && window.hWin.HEURIST4.browseRecordCache[key]) {
                                    window.hWin.HEURIST4.browseRecordCache[key] = null;
                                    delete window.hWin.HEURIST4.browseRecordCache[key];
                                }
                            });
                            window.hWin.HEURIST4.browseRecordTargets[id] = null;
                            delete window.hWin.HEURIST4.browseRecordTargets[id];
                        }
                    });
            }
            window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_REC_UPDATE); //after save record     
        }
        if (window.hWin.HEURIST4.util.isFunction(callback)) {
            callback(response);
        }
    }


    /**
    * System class that responsible for record's edit, search and tags
    * @class
    *
    * @see
    * - record_edit.php
    * - record_batch.php
    * - record_tags.php
    * - record_search.php
    *
    * @classdesc
    * methods for record_edit controller
    * - addRecord: creates new temporary record
    * - saveRecord: save record
    * - remove: delete record
    * - duplicate
    * - access: ownership and visibility
    * - increment
    * 
    * for record_batch controller
    * - details - batch edition of record details for many records
    * 
    * for record_search controller
    * - search
    * - minmax
    * - get_facets
    * - search_related
    *
    * @returns {HRecordMgr}
    */
    function HRecordMgr() {

        /**
         * @typedef Record
         * Associative array of data about a Heurist record
         * @property {number} id
         * @property {number} RecTypeID
         * @property {number} OwnerUGrpID
         * @property {*} NonOwnerVisibility
         * @property {*} AddedByImport
         * @property {string} url
         * @property {*} FlagTemporary
         * @property {Details} details
         */

        /**
         * @typedef {Object.<string,Object.<string,string>>} Details
         * Associative array of record details.
         * Each key gives the detail type preceded by a 't', e.g. 't:1' is for the name of an entity.
         * Each value is an associative array where the keys are the ids for the details in the database
         * (each detail is a seperate record) and the values are the values to which those details
         * should be set.
         */

        let that = {

            /**
            * Creates temporary new record
            *
            * @param {Request} request
            * @param {string} [request.rt] - optional: rectype
            * @param {string} [request.ro] - optional: owner
            * @param {string} [request.rv] - optional: visibility
            * @param {callserverCallback} callback - response HRecordSet object
            */
            addRecord: function (request, callback) {
                if (request) {
                    request.a = 'a';
                } else {
                    request = { a: 'a' };
                }
                _callserver('record_edit', request, callback);
            },

            /**
             * Save Record (remove temporary flag if new record)
             *
             * @param {Request} request - request.a will be set to s|save
             * @param {Record} request.record - record data to be saved
             * @param {callserverCallback} callback - response HRecordSet object
             */
            saveRecord: function (request, callback) {
                if (request) request.a = 's';
                
                let encode_type = window.hWin.HAPI4.sysinfo['need_encode'];
                if(!(encode_type>0)) encode_type = 3; //json by default

                window.hWin.HEURIST4.util.encodeRequest(request, ['details','details_visibility'], encode_type);
                
                _callserver('record_edit', request, function (response) { _triggerRecordUpdateEvent(response, callback); });
            },

            /**
             *  Batch Save Multiple Records (remove temporary flag if new record)
             * 
             * @param {Request} request request.a will be set to batch_save
             * @param {Array.<Record>} request.records - array of record objects to save
             * @param {callserverCallback} callback - response object of record ids
             */
            batchSaveRecords: function (request, callback) {
                if (request) request.a = 'batch_save';

                _callserver('record_edit', request, function (response) { _triggerRecordUpdateEvent(response, callback); });
            },

            /**
             * @param {Request} request 
             * @param {number} request.id - id of record to duplicate
             * @param {callserverCallback} callback 
             */
            duplicate: function (request, callback) {
                if (request) request.a = 'duplicate';

                _callserver('record_edit', request, function (response) { _triggerRecordUpdateEvent(response, callback); });
            },

            /**
             * Set ownership and visibility
             * @param {Request} request
             * @param {Array.<number>} request.ids - ids of records to update
             * @param {number} request.OwnerUGrpID - usergroup that should own the records
             * @param {number} request.NonOwnerVisibility - visibility that records should have
             * @param {callserverCallback} callback
             */
            access: function (request, callback) {
                if (request) request.a = 'access';
                _callserver('record_edit', request, callback);
            },

            /**
             * Increment value for given detail field and returns it
             * @param {number} rtyID - recType ID
             * @param {number} dtyID - ID of record detail to increment
             * @param {callserverCallback} callback
             */
            increment: function (rtyID, dtyID, callback) {
                /** @type {Request} */
                let request = { a: 'increment', rtyID: rtyID, dtyID: dtyID };
                _callserver('record_edit', request, callback);
            },

            /**
            * Remove Record
            *
            * @param {Request} request a: d|delete
            * @param {Array.<number>} request.ids list of records to be deleted
            * @param {callserverCallback} callback
            */
            remove: function (request, callback) {
                if (request) request.a = 'd';

                _callserver('record_edit', request, function (response) { _triggerRecordUpdateEvent(response, callback); });
            },

            /**
            * Batch edition/update of record details
            *
            * @param {Request} request request.a must be add,replace or delete
            *
            * @param {Array.<number>} request.recIDs - list of records IDS to be processed
            * @param {number} request.rtyID - optional filter by record type
            * @param {number} request.dtyID  - detail field to be added
            * @param {*} request.val val, geo or ulfID should be set when request.a == 'add'
            * @param {*} request.geo val, geo or ulfID should be set when request.a == 'add'
            * @param {*} request.ulfID val, geo or ulfID should be set when request.a == 'add'
            * @param {*} request.sVal search value - may be set when request.a == 'replace' and 'delete'
            * @param {*} request.rVal replace value - must be set when request.a == 'replace'
            * @param {number} request.tag 0|1 - add system tag to mark processed records
            *
            * @param {callserverCallback} callback
            */
            batch_details: function (request, callback) {

                window.hWin.HEURIST4.util.encodeRequest(request, ['rVal', 'val']);

                _callserver('record_batch', request, function (response) { _triggerRecordUpdateEvent(response, callback); });
            },

            //@TODO - need to implement queue for record_search, otherwise sometimes we get conflict on simultaneous requests            

            /**
            * Search for records via global events.
            * To search directly, use RecordSearch
            *
            * @param {Request} request
            * @param {string} request.q - query string
            * @param {string} request.w - a|b - domain all or bookmarks
            * @param {string} request.f - optional: cs list detail,map,structure,tags,relations,(backward)links,text,comments - details of output
            * @param {number} limit - number of records to return
            * @param {number} o - offset
            *
            * @param {callserverCallback} callback - callback function or  $document we have trigger the event
            */
            search: function (request, callback) {

                if (!window.hWin.HAPI4.is_publish_mode && request['verify_credentials'] != 'ok') {
                    window.hWin.HAPI4.SystemMgr.verify_credentials(function () {
                        request['verify_credentials'] = 'ok';
                        that.search(request, callback);
                    }, 0);
                    return;
                }

                if (request['verify_credentials']) {
                    request['verify_credentials'] = null;
                    delete request['verify_credentials'];
                }

                if (!window.hWin.HEURIST4.util.isFunction(callback)) {
                    // it happens only of calback function is not set
                    // remove all this stuff since callback function is always defined

                    if (!request.increment || window.hWin.HEURIST4.util.isnull(request.id)) {
                        request.id = window.hWin.HEURIST4.util.random();
                    }

                    let document = callback;
                    if (!window.hWin.HEURIST4.util.isnull(document) && !request.increment) {
                        document.trigger(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, [request]); //global app event
                    }

                    callback = function (response) {
                        let resdata = null;
                        if (response.status == window.hWin.ResponseStatus.OK) {
                            resdata = new HRecordSet(response.data);
                        } else {

                            window.hWin.HEURIST4.msg.showMsgErr(response);

                        }
                        if (!window.hWin.HEURIST4.util.isnull(document)) {
                            document.trigger(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH, { resultset: resdata }); //global app event
                        }
                    }
                }


                window.hWin.HEURIST4.util.encodeRequest(request, ['q']);

                // start search
                _callserver('record_search', request, callback);    //standard search

            }

            //
            // prepare result in required format
            //
            , search_new: function (request, callback) {
                // start search
                _callserver('record_output', request, callback);    //standard search
            }

            //
            //
            //
            , lookup_external_service: function (request, callback) {
                // start search
                _callserver('record_lookup', request, callback);
            }

            //
            // load kml in geojson format
            // request
            //  recID - record id that has reference to kml file or has kml snippet
            //  simplify - reduce points
            //
            , load_kml_as_geojson: function (request, callback) {
                request['format'] = 'geojson';
                // start search
                _callserver('record_map_source', request, callback);
            }

            //
            // load kml in geojson format
            // request
            //  recID - record id that has reference to shp file
            //  simplify - reduce points
            //
            , load_shp_as_geojson: function (request, callback) {
                request['format'] = 'geojson'; //or wkt
                request['api'] = 0; //not api request
                // start search
                _callserver('record_shp', request, callback);
            }

            /**
            * Search relation within given set of records
            *
            * request { }
            *  ids - list of record ids
            *
            *  callback - callback function or  $document we have trigger the event
            */
            , search_related: function (request, callback) {
                if (request && !request.a) request.a = 'related';

                _callserver('record_search', request, callback);    //standard search
            }


            // find min and max values, or count of distinct values, or matching counts
            // for
            // rt - record type
            // dt - detailtyep
            , get_aggregations: function (request, callback) {
                _callserver('record_search', request, callback);
            }

            // find ranges for faceted search
            //
            , get_facets: function (request, callback) {
                if (request && !request.a) request.a = 'getfacets';

                window.hWin.HEURIST4.util.encodeRequest(request, ['q', 'count_query']);

                _callserver('record_search', request, callback);
            }

            //
            // return the date intervals for the provided record type using the provided detail type
            //
            , get_date_histogram_data: function (request, callback) {
                if (request && !request.a) request.a = 'gethistogramdata';
                _callserver('record_search', request, callback);
            }

            //
            // return record ids after matching record detail fields, using the rectype ids provided
            //
            , get_record_ids: function (request, callback) {
                if (request && !request.a) request.a = 'getrecordids';
                _callserver('record_search', request, callback);
            }

            //@TODO get full info for particular record
            , get: function (request, callback) {
                _callserver('record_get', request, callback);
            }
        }
        return that;
    }

    /**
    * System class that responsible for interaction with server in domains:
    *       User/groups information/credentials
    *       Database definitions - record structure, field types, terms
    *       @todo saved searches
    *
    * see entityScrud.php and db[TableName].php in hserv/entity
    *
    * methods:
    *   config - loads entity config
    *   search
    *   save
    *   delete
    *
    * @returns {Object}
    */
    function HEntityMgr() {

        let entity_configs = {};
        let entity_data = {};
        let entity_timestamp = 0;
        let _msgOnRefreshEntityData = 0;

        let that = {

            //load entity configuration file
            // entityScrud.action = config
            // 
            getEntityConfig: function (entityName, callback) {

                if (entity_configs[entityName] || entityName=='records') {
                    if(entityName=='records'){
                        entity_configs[entityName] = window.hWin.entityRecordCfg;                        
                    }
                    if (window.hWin.HEURIST4.util.isFunction(callback)) {
                        callback(entity_configs[entityName]);
                    }
                    return entity_configs[entityName];
                    
                } else {

                    _callserver('entityScrud', { a: 'config', 'entity': entityName, 'locale': window.hWin.HAPI4.getLocale() },
                        function (response) {
                            if (response.status == window.hWin.ResponseStatus.OK) {

                                entity_configs[response.data.entityName] = response.data;

                                //find key and title fields
                                window.hWin.HAPI4.EntityMgr.resolveFields(response.data.entityName);

                                callback(entity_configs[response.data.entityName]);
                            } else {
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        }

                    );
                }
            },

            //
            // reset entity data that forces reload from server on next request
            //
            clearEntityData: function (entityName) {
                if (!$.isEmptyObject(entity_data[entityName])) {
                    entity_data[entityName] = {};
                }
            },

            //
            // clear clinet side entity data for further refresh
            //
            emptyEntityData: function (entityName) {
                if (entityName) {
                    entity_data[entityName] = {};
                } else {
                    entity_data = {};
                }
            },

            //
            //  find key and title fields
            //
            resolveFields: function (entityName) {

                let entity_cfg = entity_configs[entityName];

                if (entity_cfg) {

                    function __findFields(fields) {
                        let idx;
                        for (idx in fields) {
                            if (fields[idx].children) {
                                __findFields(fields[idx].children);
                            } else {
                                if (fields[idx]['keyField'] == true) {
                                    entity_cfg.keyField = fields[idx]['dtID'];
                                }
                                if (fields[idx]['titleField'] == true) {
                                    entity_cfg.titleField = fields[idx]['dtID'];
                                }
                            }
                        }
                    }

                    __findFields(entity_cfg.fields);

                }
            },

            //
            // 1) index   rty_ID[dty_ID] = rst_ID
            // 2) reverse links rty <- dty_ID - list of record pointer and relmarker fields that refers this rectype
            // 3) direct links  rty -> rty_IDs - linked to 
            // 4) reverse links rty <- rty_IDs - linked from
            //
            createRstIndex: function () {
                let rst_index = {};

                let recset = entity_data['defRecStructure'];
                recset.each2(function (rst_ID, record) {

                    //rstfield = recset.getRecord(rst_ID)
                    let rty_ID = record['rst_RecTypeID'];
                    let dty_ID = record['rst_DetailTypeID'];

                    if (!rst_index[rty_ID]) rst_index[rty_ID] = {};
                    if (!rst_index[rty_ID][dty_ID]) {
                        record['rst_ID'] = dty_ID;
                        rst_index[rty_ID][dty_ID] = record;
                    }
                });

                //create separate recordset for every rectype
                for (let rty_ID in rst_index) {
                    let _order = Object.keys(rst_index[rty_ID]);
                    rst_index[rty_ID] = new HRecordSet({
                        entityName: 'defRecStructure',
                        count: _order.length,
                        records: rst_index[rty_ID],
                        order: _order
                    });
                }

                entity_data['rst_Index'] = rst_index;

                // see $Db.rst_links
               
            },

            //
            // Check that definitions are up to date on client side
            //            
            // It erases db definitions cache on server side (dbdef_cache.json) on every structure change - 
            // it means that every new heurist window obtains fresh set of definitions.
            // For existing instances (ie in different browser window) it verifies the  relevance of definitions every 20 seconds.
            // see initialLoadDatabaseDefintions 
            //
            relevanceEntityData: function (callback, errorCallback = null) {
                
                if(entity_timestamp>0){
                    window.hWin.HAPI4.EntityMgr.refreshEntityData('relevance', callback, errorCallback);
                }else if (window.hWin.HEURIST4.util.isFunction(callback)) {
                    callback(this, true);
                }
            },
            
            //
            // refresh several entity data at once
            // 
            refreshEntityData: function (entityName, callback, errorCallback = null) {

                let params = { a: 'structure', 'details': 'full'};
                params['entity'] = entityName;
                params['timestamp'] = entity_timestamp; //db defitions time on client side

                let s_time = new Date().getTime() / 1000;
                if(_msgOnRefreshEntityData) clearTimeout(_msgOnRefreshEntityData);
                _msgOnRefreshEntityData = setTimeout(function(){
                    window.hWin.HEURIST4.msg.showMsgFlash('Database definitions refresh', false, { position: {my: 'left+100 top+100', at: 'left top', of: $(document)} });
                }, 1000);

                 
                _callserver('entityScrud', params,
                    function (response) {

                        if(_msgOnRefreshEntityData) clearTimeout(_msgOnRefreshEntityData);
                        _msgOnRefreshEntityData = 0;
                        window.hWin.HEURIST4.msg.closeMsgFlash();
                        
                        if (response && response['uptodate']) { //relevance db definitions
                            //definitions are up to date
                            if (window.hWin.HEURIST4.util.isFunction(callback)) callback(this, true);
                            
                        }else if (response && response.status == window.hWin.ResponseStatus.OK || response['defRecTypes']) {

                            let fin_time = new Date().getTime() / 1000;
                            console.log('definitions are loaded: '+(fin_time-s_time)+' sec');
                            
                            let dbdefs = (response['defRecTypes']?response:response['data']);
                            for (let entityName in dbdefs) {
                                window.hWin.HAPI4.EntityMgr.setEntityData(entityName, dbdefs)
                            }

                            if (window.hWin.HEURIST4.util.isFunction(callback)) callback(this, true);

                        } else {
                            console.log('ERROR: ',response);
                            if(window.hWin.HEURIST4.util.isFunction(errorCallback)){ errorCallback.call(this, response); }
                            else{ window.hWin.HEURIST4.msg.showMsgErr(response); }
                        }
                    }

                );
            },


            //load entire entity data and store it in cache (applicable for entities with count < ~1500)
            // entityScrud.action = search
            //
            // entityName - name name or array of names or "all" for all db definitions
            //
            getEntityData: function (entityName, force_reload, callback) {

                
                if ($.isEmptyObject(entity_data[entityName]) || force_reload == true) {

                    let det = 'list';
                    if (entityName == 'defRecStructure'){ //|| entityName == 'defTerms') {
                        det = 'full';
                    }
                    
                    _callserver('entityScrud', { a: 'search', 'entity': entityName, 'details': det },
                        function (response) {
                            if (response.status == window.hWin.ResponseStatus.OK) {

                                entity_data[response.data.entityName] = new HRecordSet(response.data);

                                if (response.data.entityName == 'defRecStructure') {
                                    window.hWin.HAPI4.EntityMgr.createRstIndex();
                                }

                                if (window.hWin.HEURIST4.util.isFunction(callback)) {
                                    callback(entity_data[response.data.entityName]);
                                }
                            } else {
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        }

                    );
                } else {
                    //take from cache
                    if (window.hWin.HEURIST4.util.isFunction(callback)) {
                        callback(entity_data[entityName]);
                    } else {
                        //if user sure that data is already on client side
                        return entity_data[entityName];
                    }
                }
            },

            //
            // direct access (without check and request to server)
            //
            getEntityData2: function (entityName) {
                return entity_data[entityName];
            },

            //
            // data either recordset or response.data
            //
            setEntityData: function (entityName, data) {
                
                if(entityName=='timestamp'){ 
                    entity_timestamp = Number(data[entityName]); //db structure cache file last update time
                }else if (window.hWin.HEURIST4.util.isRecordSet(data)) {

                    entity_data[entityName] = data;
                } else {
                    
                    entity_data[entityName] = new HRecordSet(data[entityName]);

                    //build rst index
                    if (entityName == 'defRecStructure') {
                        window.hWin.HAPI4.EntityMgr.createRstIndex();
                    } else if (entityName == 'defTerms') {
                        entity_data['trm_Links'] = data[entityName]['trm_Links'];
                        entity_data['trm_Icons'] = data[entityName]['trm_Icons'] ?? [];
                    }

                    if (data[entityName]['config']) {
                        entity_configs[entityName] = data[entityName]['config'];
                        //find key and title fields
                        window.hWin.HAPI4.EntityMgr.resolveFields(entityName);
                    }
                }

            },

            //
            // generic request for entityScrud
            //
            doRequest: function (request, callback) {
                //todo - verify basic params
                request['request_id'] = window.hWin.HEURIST4.util.random();

                //set d and c=0 to disable debug  https://www.nusphere.com/kb/technicalfaq/faq_dbg_related.htm
                request.DBGSESSID = (_use_debug) ? '425944380594800002;d=1,p=0,c=1' : '425944380594800002;d=0,p=0,c=0';

                _callserver('entityScrud', request, callback);
            },

            //
            // retrieve title of entity by given ids
            // entityScrud.action = title
            //
            getTitlesByIds: function (entityName, recIDs, callback) {

                let idx, display_value = [];
                if (entity_data[entityName]) {
                    let ecfg = entity_configs[entityName];
                    if (!ecfg) {
                        window.hWin.HAPI4.EntityMgr.getEntityConfig(entityName, function () {
                            window.hWin.HAPI4.EntityMgr.getTitlesByIds(entityName, recIDs, callback);
                        });
                        return;
                    }


                    let edata = entity_data[entityName];
                    if (!Array.isArray(recIDs)) recIDs = [recIDs];
                    for (idx in recIDs) {
                        display_value.push(
                            edata.fld(edata.getById(recIDs[idx]), ecfg.titleField));
                    }

                    callback.call(this, display_value);
                } else {


                    let request = {};
                    request['recID'] = recIDs;
                    request['a'] = 'title'; //action
                    request['entity'] = entityName;
                    request['request_id'] = window.hWin.HEURIST4.util.random();

                    window.hWin.HAPI4.EntityMgr.doRequest(request,
                        function (response) {
                            if (response.status == window.hWin.ResponseStatus.OK) {

                                callback.call(this, response.data); //array of titles
                            } else {
                                callback.call(this, recIDs);
                            }
                        }
                    );

                }
            },

            //
            // Retrieve translations stored within defTranslations
            //
            getTranslatedDefs: function(entityName, key, recIDs, callback){

                if(key.indexOf('_Translation') == -1){
                    key += '_Translation';
                }

                if(entity_data[key] && window.hWin.HEURIST4.util.isRecordSet(entity_data[key])){ // already in cache
                    callback.call(this, entity_data[key]);
                    return;
                }

                let request = {
                    'a': 'batch',
                    'entity': entityName,
                    'get_translations': window.hWin.HEURIST4.util.isempty(recIDs) ? 'all': recIDs,
                    'request_id': window.hWin.HEURIST4.util.random()
                };

                window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){ 
                    if(response.status == window.hWin.ResponseStatus.OK){

                        let recordset = new HRecordSet(response.data);
                        window.hWin.HAPI4.EntityMgr.setEntityData(key, recordset); // save to local cache

                        callback.call(this, recordset);
                    }else{
                        callback.call(this, null);
                    }
                });
            }

        }
        return that;
    }


    //public members
    let that = {

        baseURL: '',
        iconBaseURL: '',
        database: '',
        currentUser: _guestUser,
        is_publish_mode: false,
        sysinfo: {},

        Event: {
            /*LOGIN: "LOGIN",
            LOGOUT: "LOGOUT",*/
            ON_CREDENTIALS: 'ON_CREDENTIALS', //login, logout, change user role, sysinfo (change sysIdentification) 
            ON_REC_SEARCHSTART: "ON_REC_SEARCHSTART",
            ON_REC_SEARCH_FINISH: "ON_REC_SEARCH_FINISH",
            ON_CUSTOM_EVENT: "ON_CUSTOM_EVENT", //special event for custom link various widgets
            ON_REC_UPDATE: "ON_REC_UPDATE",
            ON_REC_SELECT: "ON_REC_SELECT",
            ON_REC_STATUS: "ON_REC_STATUS",
            ON_REC_COLLECT: "ON_REC_COLLECT",
            ON_LAYOUT_RESIZE: "ON_LAYOUT_RESIZE",
            ON_WINDOW_RESIZE: "ON_WINDOW_RESIZE",
            ON_SYSTEM_INITED: "ON_SYSTEM_INITED",
            ON_STRUCTURE_CHANGE: 'ON_STRUCTURE_CHANGE',
            ON_PREFERENCES_CHANGE: 'ON_PREFERENCES_CHANGE',
        },

        /**
        * Assign user after system initialization - obtained from server side by SystemMgr.sys_info
        *
        * @param user
        */
        setCurrentUser: function (user) {

            let isChanged = (that.currentUser != user);

            if (user?.['ugr_Permissions']?.['disabled']===false) {
                that.currentUser = user;
            } else {
                that.currentUser = _guestUser;
            }

            const ENABLE_VERIFY_IDLE = false;
            if (ENABLE_VERIFY_IDLE) { //disabled: verify credentials if user is idle

                if (that.currentUser['ugr_ID'] > 0) {

                    if (!window.hWin.HAPI4.is_publish_mode && window.hWin.HEURIST4?.ui) 
                    {

                            window.hWin.HEURIST4.ui.onInactiveStart(5000, function () {  //300000 5 minutes 
                                //check that still logged in
                                window.hWin.HAPI4.SystemMgr.verify_credentials(function () {
                                    //ok we are still loggen in
                                    window.hWin.HEURIST4.ui.onInactiveReset(); //start again    
                                }, 0);
                            });
                    }
                } else {
                    //terminate completely
                    window.hWin.HEURIST4.ui.onInactiveReset(true);
                }

            }

            if (window.hWin.HEURIST4.dbs && isChanged) {
                window.hWin.HEURIST4.dbs.needUpdateRtyCount = 1;
                window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_REC_UPDATE); //after save record 
            }
        },

        currentUserRemoveGroup: function (groupID, isfinal) {

            if (window.hWin.HAPI4.currentUser['ugr_Groups'][groupID]) {
                window.hWin.HAPI4.currentUser['ugr_Groups'][groupID] = null;
                delete window.hWin.HAPI4.currentUser['ugr_Groups'][groupID];
            }
            if (isfinal) {
                window.hWin.HAPI4.sysinfo.db_usergroups[groupID] = null;
                delete window.hWin.HAPI4.sysinfo.db_usergroups[groupID];
            }
        },
        
        // is_admin, is_member, has_access - verify credentials on client side
        // they have to be used internally in widgets and loop operations to avoid server/network workload
        // However, before start any action or open widget popup need to call 
        // SystemMgr.verify_credentials

        is_guest_user: function(){
             return window.hWin.HAPI4.currentUser && 
                    window.hWin.HAPI4.currentUser['ugr_Permissions'] && 
                    window.hWin.HAPI4.currentUser['ugr_Permissions']['guest_user'];
        },
        
        /**
        * Returns true is current user is database admin (admin in group Database Managers)
        */
        is_admin: function () {
            return window.hWin.HAPI4.has_access(window.hWin.HAPI4.sysinfo.db_managers_groupid);
        },
        
        /**
        * Returns true if currentUser is member of given group ID or itself
        * @param ug
        */
        is_member: function (ugs) {

            if (ugs == 0 || ugs == null) {
                return true;
            }

            if (ugs > 0) {
                ugs = [ugs];
            } else {
                ugs = Array.isArray(ugs) ? ugs : ugs.split(',')
            }

            for (let idx in ugs) {
                let ug = ugs[idx];
                if (ug == 0 || that.currentUser['ugr_ID'] == ug ||
                    (that.currentUser['ugr_Groups'] && that.currentUser['ugr_Groups'][ug])) {
                    return true;
                }
            }
            return false;

        },

        /**
        * Returns type of current user
        * 
        * @returns {String}
        */
        getUserType: function(){
            
            let userType = 'visitor';

            if( that.has_access(2) ){
               userType = 'owner'; 
            }else if( window.hWin.HAPI4.is_admin() ){
               userType = 'admin'; 
            }else if( window.hWin.HAPI4.is_member(window.hWin.HAPI4.sysinfo.db_managers_groupid) ){
               userType = 'manager';  
            }else if(  window.hWin.HAPI4.is_guest_user() ){
               userType = 'guest';
            }else if( that.currentUser['ugr_ID']>0 ){
               userType = 'user';
            }
            
            return userType;
        },
        
        /**
        * Returns TRUE if currentUser satisfies to required level
        *
        * @param requiredLevel 
        * NaN or <1 - (DEFAULT) is logged in
        * 1 - db admin (admin of group 1 "Database managers")
        * 2 - db owner
        * n - admin of given group
        */
        has_access: function (requiredLevel) {

            requiredLevel = Number(requiredLevel);

            if (isNaN(requiredLevel) || requiredLevel < 1) {
                return (that.currentUser && that.currentUser['ugr_ID'] > 0); //just logged in
            }

            return (requiredLevel == that.currentUser['ugr_ID'] ||   //iself 
                2 == that.currentUser['ugr_ID'] ||   //db owner
                (that.currentUser['ugr_Groups'] && that.currentUser['ugr_Groups'][requiredLevel] == "admin")); //admin of given group
        },

        /**
        * Returns current user preferences
        *
        * @returns {Object}
        */
        get_prefs: function (name) {
            if (!that.currentUser['ugr_Preferences']) {
                //preferences by default
                that.currentUser['ugr_Preferences'] =
                {
                    layout_language: 'en',
                    search_result_pagesize: 100,
                    search_detail_limit: 500,
                    userCompetencyLevel: 2, //'beginner',
                    userFontSize: 12, //px
                    deriveMapLocation: true,
                    help_on: true,
                    optfields: true,
                    mapcluster_on: true,
                    searchQueryInBrowser: true
                };
            }
            if (window.hWin.HEURIST4.util.isempty(name)) {
                return that.currentUser['ugr_Preferences']; //returns all preferences
            } else {
                let res = '';
                if(that.currentUser['ugr_Preferences'] && Object.hasOwn(that.currentUser['ugr_Preferences'], name)){
                    res = that.currentUser['ugr_Preferences'][name];
                }
                
                let res2 = window.hWin.HEURIST4.util.isNumber(res)?res:0;

                // TODO: redundancy: this duplicates same in System.php
                if ('search_detail_limit' == name) {
                    res = Math.min(Math.max(res2,500),5000);
                } else if ('search_result_pagesize' == name) {
                    res = Math.min(Math.max(res2,100),5000);
                }
                return res;
            }
        },

        get_prefs_def: function (name, defvalue) {
            let res = window.hWin.HAPI4.get_prefs(name);
            if (window.hWin.HEURIST4.util.isempty(res)) {
                res = defvalue;
            }
            if (name == 'userCompetencyLevel' && isNaN(Number(res))) {
                res = defvalue;
            }
            return res;
        },

        //
        // limit - to save limited list of ids - for example: last selected tags
        //
        save_pref: function (name, value, limit) {

            if (Array.isArray(value) && limit > 0) {
                value = value.slice(0, limit);

                let cur_value = window.hWin.HAPI4.get_prefs(name);
                cur_value = (cur_value ? cur_value.split(',') : null);
                if (!Array.isArray(cur_value)) cur_value = [];

                $.each(value, function (i, item) {
                    if ($.inArray(item, cur_value) === -1) cur_value.unshift(item);
                });
                value = cur_value.slice(0, limit).join(',');
            }

            let request = {};
            request[name] = value;

            window.hWin.HAPI4.SystemMgr.save_prefs(request,
                function (response) {
                    if (response.status == window.hWin.ResponseStatus.OK) {
                        that.currentUser['ugr_Preferences'][name] = value;
                    }
                }
            );
        },

        triggerEvent: function (eventType, data) {
            $(window.hWin.document).trigger(eventType, data);

            //this is for listeners in other frames
            for (let i = 0; i < _listeners.length; i++) {
                if (_listeners[i].event_type == eventType) {
                    _listeners[i].callback.call(_listeners[i].obj, data);
                }
            }
        },

        //to support event listeners in other frames
        addEventListener: function (object, event_type, callback) {
            _listeners.push({ obj: object, event_type: event_type, callback: callback });
        },

        removeEventListener: function (object, event_type) {
            for (let i = 0; i < _listeners.length; i++) {
                if (_listeners[i].event_type == event_type && _listeners[i].obj == object) {

                    _listeners.splice(i, 1);
                    return;
                }
            }
        },

        user_id: function () {
            return that.currentUser['ugr_ID'];
        },

        // main result set that is filled in search_minimal - keeps all
        // purposes:
        // 1) to keep main set of records (original set) to apply RuleSet
        // 2) to get selected records by ids
        // 3) to pass result set into popup record action dialogs
        currentRecordset: null,

        currentRecordsetSelection: [],  //selected record ids - main assignment in lister of resultListMenu


        getClass: function () { return _className; },
        isA: function (strClass) { return (strClass === _className); },
        getVersion: function () { return _version; },


        //UserMgr: new hUserMgr(),

        SystemMgr: null,

        RecordMgr: new HRecordMgr(),

        EntityMgr: new HEntityMgr(),

        //assign it later since we may have different search managers - incremental, partial...
        RecordSearch: null, //class that responsible for search and incremental loading of result

        LayoutMgr: null,
        
        callserver: function(action, request, callback, timeout=0){
            _callserver(action, request, callback, timeout);
        },

        /*RecordMgr: function(){
        return /();
        }*/
        
        //
        // Returns 3 letters language code in upper case by 2 letters code 
        // or default language
        //
        getLangCode3: function(lang, def){

            if(lang && lang != 'def'){
                if(lang.length==2){
                    lang = lang.toLowerCase();
                    for(let code3 in that.sysinfo.common_languages){
                        if(lang==that.sysinfo.common_languages[code3]['a2']){
                            return code3;
                        }
                    }
                }else if(lang.length==3){
                    lang = lang.toUpperCase();
                    if(that.sysinfo.common_languages && that.sysinfo.common_languages[lang]){
                          return lang;
                    }
                }
            }
            
            //not found - English is default
            return (def ?def:(_region?_region:'ENG'));
        },
        
        //
        // values - array of strings
        //
        getTranslation: function(values, lang){
            
            //"xx" means take current system language
            let a2_lang = null;
            if(lang){
                lang = window.hWin.HAPI4.getLangCode3(lang);
                a2_lang = that.sysinfo.common_languages[lang]['a2'].toUpperCase();
            }
                
            if(lang || a2_lang){
                let def_val = '';
                let is_object = $.isPlainObject(values);
                
                for (let key in values) {
                    if (!is_object || Object.hasOwn(values, key)) {

                        let val = values[key];
                        
                        if(val!=null){

                            let val_orig = val, tag_to_remove = null;
                            if(val.indexOf('<p')===0 || val.indexOf('<span')===0){
                                tag_to_remove = val.indexOf('<p')===0?'</p>':'</span>';
                                val = window.hWin.HEURIST4.util.stripTags(val); //remove all tags
                            }
                            function __removeFirstTag(){
                                return window.hWin.HEURIST4.util.stripFirstElement(val_orig);
                            }
                        
                            if(val.length>4 && val.substr(3,1)==':'){ //has lang prefix

                                if(val.substr(0,3).toUpperCase() == lang){
                                    def_val = (tag_to_remove==null)?val.substr(4).trim() 
                                                             :__removeFirstTag();
                                    break;
                                }
                            }else if(val.length > 3 && val.substr(2, 1) == ':'){ // check for ar2 code

                                if(val.substr(0, 2).toUpperCase() == a2_lang){
                                    def_val = (tag_to_remove==null)?val.substr(3).trim() 
                                                                    :__removeFirstTag();
                                    break;
                                }
                            }else{
                                //without prefix
                                def_val = val_orig;
                                if(lang=='def'){ //take first without prefix
                                    break;
                                }
                            }
                        }
                    
                    }
                }//for

                return def_val;
            }else if($.isPlainObject(values)){
                return values[Object.keys(values)[0]];
            }else if (Array.isArray(values) && values.length>0){
                return  values[0];
            }else{
                return  values;
            }
            
        },


        //
        // returns current locale - language code
        //
        getLocale: function () {
            return _region;
        },
        /**
        * Returns function to string resouce according to current region setting
        */
        setLocale: function (region) {
        
            region = that.getLangCode3(region, 'ENG'); //English is default
            if (typeof _regional === 'undefined' || _regional === null){
                _regional = {};
            }
            
            if (!_regional[region]) {
                
                _region = region;
                
                $.get(that.baseURL + `hclient/assets/localization/localization_${region.toLowerCase()}.txt`,
                    function(response){
                        const lines = response.split("\n");
                        _regional[_region] = {};
                        let prev_key = '';
                        lines.forEach((line,i)=>{
                            if(line.trim()!='' && line.indexOf('//')!=0){
                                if(line[0]=='#'){
                                    const pos = line.indexOf('#',2);
                                    const key = line.substring(1,pos);
                                    _regional[_region][key] = line.substring(pos+1);
                                }else if(prev_key!=''){
                                    _regional[_region][prev_key] = line;
                                }
                            }    
                        });
                    }); //get
            }

            // function that returns string resouce according to current region setting
            return function (res) {

                if (window.hWin.HEURIST4.util.isempty(res)) {
                    return '';
                }
                let key = res.trim();

                if (_regional[_region] && _regional[_region][key]) {
                    return _regional[_region][key];
                } else if (_region != 'ENG' && _regional['ENG'] && _regional['ENG'][key])
                {
                    return _regional['ENG'][key];
                }else{
                    return res;
                }

            }
        },

/**
* Localization in Heurist
*
*For widgets we use json from /hlcient/assets/localization/localization[_lang3].js  (function window.hWin.HR)
*To localize entity edit forms (record types, fields, terms etc) we use localized json from /hserv/entity/defRecTypes[_lang3].json
*For static context help or html snippets  we take html snippets from /context_help/resultListEmptyMsg_fre.htm  (function window.hWin.HRes )
* 
*In other words, where content is created dynamically (widgets, edit forms) we take localized strings (mostly for labels and hints) from json arrays 
*For large static content with a lot of text we load the entire translated html snippet. 
*/
/*
When translation happens
on client side:
A) On widgets initControls
B) On load html snippets
C) On arbitrary call HLocale.translate(element, lang)
it peroforms the following actions
1) loads translations from assets/widgetName_lang.txt
2) loads common words from assets/localization_lang.txt
3) scans this.element and find either elements with id, data-hr or data-title-hr(and with class "slocale"
   or plain text content less than 20 chars - it may be some kind of code too) 
4) Finds in translations by ID or by plain text content

#widgetName#id/data-hr:
#id-hint:

html_snippetName_lang.txt


on server side:
1) Message translation


Automatic translation
1) Finds elements with id and data-hr
2) Finds elements with id and content with limited set of tags (p,i,b,a,strong)

*/
        //
        //localize all elements with class slocale for given element
        //
        HRA: function (ele, lang) {
            if (ele) {
                
                if(lang){
                    lang = that.getLangCode3(lang, _region);    
                }
                
                $.each($(ele).find('.slocale'), function (i, item) {
                    let s = $(item).text();
                    $(item).html(window.hWin.HR(s));
                });

                $(ele).find('[slocale-title]').each(function () {
                    $(this).attr('title', window.hWin.HR($(this).attr('slocale-title')));
                });
            }
        },

        //
        // returns localized value for json (options in widget)
        //
        HRJ: function (name, options, lang) {

            let def_value = options[name];
            
            lang = that.getLangCode3(lang, _region);

            let loc_value = options[name + ':' + lang];

            return loc_value ? loc_value : def_value;
        },

        //
        // returns url or loads content for localized resource (for example help, documentation or html snippet for form)
        // name - name of file (default html ext)
        // ele - target element
        //
        HRes: function (name, ele) {

            //window.hWin.HAPI4.getLocale()
            let sURL = window.hWin.HAPI4.baseURL + '?lang=' + _region + '&asset=' + name;
        
            if (ele) {
                ele.load(sURL);
            } else {
                return sURL;
            }

            /*default extension is html
            let ext = window.hWin.HEURIST4.util.getFileExtension(name);
            if(window.hWin.HEURIST4.util.isempty(ext)){
                name = name + '.html';
            }
            let sURL = '';
            if(_region && _region!='en'){
                sURL = sURL + _region + '/';
                
            }
            resultListEmptyMsg.html;
            */

        },


        /**
        *  @todo need to rewrite since it works with global currentRecordset
        * 
        *   Returns subset of currentRecordset or array of its ids
        *   @param selection :
        *           all - returns all records of currentRecordset
        *           array of ids
        *           recordset
        *           @todo array of recordtype
        *
        *   @param needIds if it is true  it returns array of record ids
        */
        getSelection: function (selection, needIds) {

            if (selection == "all") {
                if (this.currentRecordset) {
                    selection = needIds ? this.currentRecordset.getIds() : this.currentRecordset;
                } else {
                    return null;
                }
            }
            if (selection) {
                if (window.hWin.HEURIST4.util.isRecordSet(selection)) {
                    if (selection.length() > 0) {
                        return (needIds) ? selection.getIds() : selection; //array of record ids
                    }
                } else {  //selection is array of ids
                    return (needIds) ? selection
                        : ((that.currentRecordset) ? that.currentRecordset.getSubSetByIds(selection) : null);
                }
            }
            return null;
        }

        //
        //  returns url for entity image
        // 
        // version = thumb, icon
        // def - if file not found return empty placeholder (0) or "add image" gif (1) 
        //       2 - default icon/thumb for entity
        //       3 - check existence
        //
        , getImageUrl: function (entityName, recID, version, def, database, random_number = false) {

            return window.hWin.HAPI4.baseURL //redirected to + 'hserv/controller/fileGet.php'
                + '?db=' + (database ? database : window.hWin.HAPI4.database)
                + (entityName ? ('&entity=' + entityName) : '') //rty by default
                + '&icon=' + recID
                + (version ? ('&version=' + version) : '')
                + (def ? ('&def=' + def) : '')
                + (random_number ? `&${window.hWin.HEURIST4.util.random()}` : '');
        }

        //
        // Requests 
        //
        , checkImage: function (entityName, recID, version, callback) {

            if (entityName == 'Records') {

                let request = {
                    db: window.hWin.HAPI4.database,
                    file: recID,  // ulf_ID
                    mode: 'metaonly'  // get width and height for image file
                };

                window.hWin.HEURIST4.util.sendRequest(window.hWin.HAPI4.baseURL + 'hserv/controller/fileDownload.php',
                    request, null, callback);

            } else {

                let checkURL = window.hWin.HAPI4.getImageUrl(entityName, recID, version, 'check');

                window.hWin.HEURIST4.util.sendRequest(checkURL, null, null, callback);

            }
        }

        //
        //
        //
        , doImportAction: function (request, callback) {
            _callserver('importController', request,
                function (response) {
                    _triggerRecordUpdateEvent(response, callback);
                });
        }



        /**
        * returns true if _callserver ajax request is in progress
        */
        , is_callserver_in_progress: function () {
            return _is_callserver_in_progress;
        }


    }

    _init(_db, _oninit, _baseURL);
    return that;  //returns object
}