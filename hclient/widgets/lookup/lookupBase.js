/**
* lookupBase.js - Base widgt for all lookup widgets
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Brandon McKay   <blmckay13@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/* global stringifyMultiWKT */

$.widget( "heurist.lookupBase", $.heurist.recordAction, {

    // dialog options, the default values and other available options can be found in hclient/widget/record/recordAction.js
    options: {

        height: 700,
        width:  800,
        modal:  true,
        
        title:  "External lookup",
        
        htmlContent: 'lookupBase.html', // in hclient/widgets/lookup folder
        helpContent: null, // in context_help folder

        mapping: null, // configuration from record_lookup_config.json
        edit_fields: null, // realtime values from edit form fields
        edit_record: false, // recordset of the current record being edited

        add_new_record: false, // if true it creates new record on selection

        resultList: {

            recordDivEvenClass: 'recordDiv_blue',
            eventbased: false,  //do not listent global events

            multiselect: false, // allow only one record to be selected
            select_mode: 'select_single', // only accept one record for selection
            selectbutton_label: 'select!!', // not used

            view_mode: 'list', // result list viewing mode [list, icon, thumb]
            show_viewmode: false,
            pagesize: 20, // number of records to display per page,

            entityName: 'Lookups',

            empty_remark: '<div style="padding:1em 0 1em 0">No records match the search</div>' // For empty results
        }
    },

    baseURL: '', // external url base
    serviceName: '', // service name

    recordList: null, // Result list

    action_button_label: 'Select', // dialog action button label

    search_buttons: null, // search button(s)
    search_button_selector: '#btnStartSearch', // selector to retrieve the search button(s) that calls doSearch

    save_settings: null, // save extra settings/options button

    _forceClose: true, // skip saving additional mapping and close dialog

    tabs_container: null, // jQuery tabs container, separates query and results
    results_tab: 1, // Tab that displays the result list

    timeout: {
        action_timeout: null, // Timeout for certain actions
        field_name: '',
        value: ''
    },

    //element => dialog inner content
    //_as_dialog => dialog container

    _init: function(){
        this._super(); // bare in mind that the html hasn't been loaded yet
    },

    /**
     * Initialises various UI elements; buttons, selects, result list, etc...
     *
     * @returns void
     */
    _initControls: function(){

        // Init record list
        this.recordList = this.element.find('#div_result');
        if(this.recordList.length > 0){

            let context = this;

            // Record render function, is called on resultList updateResultSet
            this.options.resultList['renderer'] = (recordset, record) => { return context._rendererResultList(recordset, record); };

            this.recordList.resultList(this.options.resultList);
            this.recordList.resultList('option', 'pagesize', this.options.resultList.pagesize); // so the pagesize doesn't get set to a different value

            // Init select & double click events for result list
            this._on( this.recordList, {
                resultlistonselect: function(event, selected_recs){
                    window.hWin.HEURIST4.util.setDisabled(
                        this.element.parents('.ui-dialog').find('.btnDoAction'),
                        selected_recs && selected_recs.length() < 1
                    );
                },
                resultlistondblclick: function(event, selected_recs){
                    if(selected_recs && selected_recs.length()==1){
                        this.doAction();
                    }
                }
            });
        }

        // Init tabs
        if(this.element.find('#tabs-cont').length > 0){
            this.tabs_container = this.element.find('#tabs-cont').tabs();
        }

        // Init search button(s)
        this.search_buttons = this.element.find(this.search_button_selector);
        if(this.search_buttons.length > 0){

            // Action button styling
            this.search_buttons.addClass("ui-button-action").button();

            // Handling for 'Search' button
            this._on(this.search_buttons, {
                click: this._doSearch
            });

            // Set search button status based on the existence of input
            this._on(this.element.find('input, select'), {
                keyup: () => {
                    let $inputs_with_value = this.element.find('input, select').filter((idx, ele) => { 
                        return !window.hWin.HEURIST4.util.isempty($(ele).val());
                    });
                    window.hWin.HEURIST4.util.setDisabled(this.search_buttons, $inputs_with_value.length == 0);
                },
                change: () => {
                    let $inputs_with_value = this.element.find('input, select').filter((idx, ele) => { 
                        return !window.hWin.HEURIST4.util.isempty($(ele).val());
                    });
                    window.hWin.HEURIST4.util.setDisabled(this.search_buttons, $inputs_with_value.length == 0);
                }
            });
            window.hWin.HEURIST4.util.setDisabled(this.search_buttons, true);
        }

        // Init save settings button
        this.save_settings = this.element.find('#save-settings');
        if(this.save_settings.length > 0){

            this.save_settings.button();

            this._on(this.save_settings, {
                click: () => {
                    this._saveExtraSettings(true, false);
                }
            });
        }

        // For capturing the 'Enter' key while typing
        this._on(this.element.find('input.search_on_enter'), {
            keypress: this.startSearchOnEnterPress
        });

        // Save extra settings before exiting
        this._on(this._as_dialog, {
            dialogbeforeclose: () => {
                if(!this._forceClose){
                    this._forceClose = true;
                    this._saveExtraSettings(true, true);
                    return false;
                }
            }
        });

        // Setup settings tab
        this._setupSettings();

        // By default action button is disabled
        window.hWin.HEURIST4.util.setDisabled(this.element.parents('.ui-dialog').find('.btnDoAction'), true);

        return this._super();
    },

    /**
     * Function handler for pressing the enter button while focused on input element
     *
     * @param {KeyboardEvent} e - keypress event
     * @returns void
     */
    startSearchOnEnterPress: function(e){
        if(e.key === 'Enter' || e.key === 'NumpadEnter'){
            window.hWin.HEURIST4.util.stopEvent(e);
            e.preventDefault();
            this._doSearch();
        }
    },

    /**
     * Initial dialog buttons on bottom bar, _getActionButtons() under recordAction.js
     * Get and customise dialog buttons
     * By default there are two 'Go' and 'Cancel', these can be edited or more buttons can be added
     * 
     * buttons[0] => Cancel/Close: Closes dialog
     * buttons[1] => Go/Select: Calls doAction
     * 
     * @returns array of dialog buttons
     */
    _getActionButtons: function(){

        let buttons = this._super(); // setup and retrieve default dialog buttons

        buttons[1].text = window.hWin.HR(this.action_button_label);

        return buttons;
    },

    /**
     * Constructs the html for each record to be listed, uses the fields:
     * rec_ID, rec_Title and rec_RecTypeID
     *
     * @param {HRecordSet} recordset - complete record set, to retrieve fields
     * @param {Array} record - record being rendered
     * @returns formatted html string
     */
    _rendererResultList: function(recordset, record){

        const rec_ID = recordset.fld(record, 'rec_ID');
        const rec_Title = recordset.fld(record, 'rec_Title');
        const rec_RecTypeID = recordset.fld(record, 'rec_RecTypeID');
        const rec_Icon = window.hWin.HAPI4.iconBaseURL + rec_RecTypeID;

        const thumb = `<div class="recTypeThumb" style="background-image: url(&quot;${rec_Icon}&version=thumb&quot;);"></div>`;

        const html = `<div class="recordDiv" id="rd${rec_ID}" recid="${rec_ID}" rectype="${rec_RecTypeID}">`
                    + thumb
                    + '<div class="recordIcons">'
                        + `<img src="${window.hWin.HAPI4.baseURL}hclient/assets/16x16.gif" class="rt-icon" `
                        +   `style="background-image: url(&quot;${rec_Icon}&quot;)" />`
                    + '</div>'
                    + rec_Title
                + '</div>';

        return html;
    },

    doAction: function(url_fld = ''){

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        let res = {};

        if(!window.hWin.HEURIST4.util.isempty(url_fld) && recset.getFields().indexOf(url_fld) !== -1){
            res['ext_url'] = recset.fld(record, url_fld);
        }

        res = this.prepareValues(recset, record, res);

        this.closingAction(res);
    },

    /**
     * Return record field values in the form of a json array mapped as [dty_ID: value, ...]
     * For multi-values, [dty_ID: [value1, value2, ...], ...]
     *
     * To trigger record pointer selection/creation popup, value must equal [dty_ID, default_searching_value]
     *
     * Include a url to an external record that will appear in the record pointer guiding popup, add 'ext_url' to res
     *  the value must be the complete html (i.e. anchor tag with href and target attributes set)
     *  e.g. res['ext_url'] = '<a href="www.example.com" target="_blank">Link to Example</a>'
     * 
     * @param {HRecordSet} recordset - overall record set
     * @param {json} record - current record
     * @param {json} dlg_response - results to return
     * @param {json} extra_settings - extra settings
     * @returns {json} mapped return values from selection
     */
    prepareValues: function(recordset, record, dlg_response = {}, extra_settings = {}){

        if(!window.hWin.HEURIST4.util.isObject(dlg_response)){
            dlg_response = {};
        }

        let map_flds = Object.keys(this.options.mapping.fields); // mapped fields names, to access fields of rec

        // Assign individual field values, here you would perform any additional processing for selected values (example. get ids for vocabulrary/terms and record pointers)
        for(let fld_Name of map_flds){

            let dty_ID = this.options.mapping.fields[fld_Name];
            if(dty_ID < 1){
                continue;
            }

            let values = recordset.fld(record, fld_Name);

            this.prepareValue(values, dty_ID, extra_settings);

            // Check that values is valid, add to response object
            if(window.hWin.HEURIST4.util.isempty(values)){
                continue;
            }
            if(!Object.hasOwn(dlg_response, dty_ID)){
                dlg_response[dty_ID] = [];
            }
            dlg_response[dty_ID] = dlg_response[dty_ID].concat(values);
        }

        return dlg_response;
    },

    /**
     * Process the value(s) for the provided field, based on the field's type
     *
     * @param {any} values - value/values to be processed, will be converted into an array
     * @param {integer} dty_ID - field's ID, to retrieve field type
     * @param {json} extra_settings - additional settings
     */
    prepareValue: function(values, dty_ID, extra_settings){

        values = this.valueToArray(values);

        if(window.hWin.HEURIST4.util.isempty(values)){
            return;
        }

        const field_type = $Db.dty(dty_ID, 'dty_Type');

        switch(field_type){
            case 'enum':
                // Match term labels with val, need to return the term's id to properly save its value
                if(Object.hasOwn(extra_settings, 'check_term_codes')){
                    this._getTermByCode(extra_settings.check_term_codes, dty_ID, values);
                }
                break;

            case 'resource':
            case 'relmarker':
                this._processRecordFields(values);
                break;

            default:
                this._processValues(values);
                break;
        }
    },

    /**
     * Perform final actions before exiting popup
     * Clear timeout before returning result
     *
     * @param {json|boolean} dlg_response - mapped values to fields, or false to return nothing
     * @param {boolean} check_for_empty - check the response values for empty values, multi-value only
     */
    closingAction: function(dlg_response, check_for_empty = true){

        if(this.timeout.action_timeout){
            clearTimeout(this.timeout.action_timeout); // clear timeout
        }

        if(dlg_response !== false && window.hWin.HEURIST4.util.isempty(dlg_response)){
            dlg_response = {};
        }

        if(check_for_empty){

            for(const ID in dlg_response){

                if(!Array.isArray(dlg_response[ID])){
                    dlg_response[ID] = [dlg_response[ID]];
                    continue;
                }

                dlg_response[ID] = dlg_response[ID].filter((value) => !window.hWin.HEURIST4.util.isempty(value));
            }
        }

        window.hWin.HEURIST4.msg.sendCoverallToBack(true);

        // Pass mapped values back and close dialog
        this._context_on_close = dlg_response;
        this._as_dialog.dialog('close');
    },

    /**
     * Display result via the Heurist resultList widget
     *
     * @param {json|boolean|null} data - data to display
     * @param {boolean} is_record_set - whether the data is ready for HRecordSet, usually a response from Heurist
     * @returns void
     */
    _onSearchResult: function(data, is_record_set=false){

        this.recordList.show();

        let invalid_data = data === false 
            || !window.hWin.HEURIST4.util.isObject(data) 
            || (!is_record_set && (!Object.hasOwn(data, 'fields') || !Object.hasOwn(data, 'order') || !Object.hasOwn(data, 'records')));

        if(invalid_data && data !== null){

            this.recordList.resultList('updateResultSet', null);
            window.hWin.HEURIST4.msg.showMsgErr({
                message: 'Service did not return data in an appropriate format',
                error_title: 'No valid data'
            });

            return;
        }else if(!data){
            this.recordList.resultList('updateResultSet', null);
            return;
        }

        let res_recordset = null;
        if(!is_record_set){

            let fields = data.fields;
            let orders = data.order;
            let records = data.records;

            res_recordset = new HRecordSet({
                count: orders.length,
                offset: 0,
                fields: fields,
                rectypes: [this.options.mapping.rty_ID],
                records: records,
                order: orders,
                mapenabled: true
            });
        }else{
            res_recordset = new HRecordSet(data);
        }

        this.recordList.resultList('updateResultSet', res_recordset);

        if(this.tabs_container && this.tabs_container.tabs('instance') !== undefined){
            this.tabs_container.tabs('option', 'active', this.results_tab);
        }
    },

    /**
     * Loads extra settings/options
     *
     * @returns void
     */
    _setupSettings: function(){
        return;
    },

    /**
     * Save extra settings
     *
     * @param {json} [settings] - service extra settings to be saved
     * @param {boolean} [close_dlg] - whether to close the dialog after saving 
     * @returns void
     */
    _saveExtraSettings: function(settings, close_dlg = false){

        let that = this;

        let services = window.hWin.HEURIST4.util.isJSON(window.hWin.HAPI4.sysinfo['service_config']);

        if(services !== false && window.hWin.HEURIST4.util.isObject(settings)){

            let service_id = this.options.mapping.service_id;
            services[service_id]['options'] = settings;

            let fields = {
                'sys_ID': 1,
                'sys_ExternalReferenceLookups': JSON.stringify(services)
            };
    
            // Update sysIdentification record
            let request = {
                'a': 'save',
                'entity': 'sysIdentification',
                'request_id': window.hWin.HEURIST4.util.random(),
                'isfull': 0,
                'fields': fields
            };
    
            window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){
    
                if(response.status != window.hWin.ResponseStatus.OK){
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    return;
                }

                window.hWin.HAPI4.sysinfo['service_config'] = window.hWin.HEURIST4.util.cloneJSON(services); // update global copy

                if(close_dlg === true){
                    that._saveExtraSettings(false, true);
                    return;
                }

                that.options.mapping = window.hWin.HEURIST4.util.cloneJSON(services[service_id]);
                window.hWin.HEURIST4.msg.showMsgFlash('Extra lookup settings saved...', 3000);
            });

            return;
        }

        if(close_dlg === true){
            this._forceClose = true;
            this._as_dialog.dialog('close');
        }
    },

    /**
     * Creates a new record of the provided type, and includes the provided details by default
     *
     * @param {integer} rec_RecTypeID - record type id for the new record
     * @param {json} details - mapped fields of record details for the new record
     * @returns void
     */
    _addNewRecord: function(rec_RecTypeID, details){

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        let that = this;

        let request = {
            a: 'save',
            ID: 0,
            RecTypeID: rec_RecTypeID,
            details: details
        };

        window.hWin.HAPI4.RecordMgr.saveRecord(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack();

            if(response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            // ... Complete final tasks, then
            that._as_dialog.dialog('close'); // close dialog
        });
    },

    /**
     * Retrieves the user's selection from the result list
     *
     * @param {boolean} get_first - whether to retrieve the first record separately
     * @returns record set [HRecordSet] & record [json]
     */
    _getSelection: function(get_first = true){

        // get selected recordset
        let recset = this.recordList.resultList('getSelected', false);
        if(!recset || recset.length() < 0){
            window.hWin.HEURIST4.msg.showMsgFlash('Please make a selection first...', 3000);
            return [null, null];
        }

        if(!get_first){
            return [recset, null];
        }

        let record = recset.getFirstRecord(); // get selected record

        return [recset, record];
    },

    /**
     * Checks the array of values as term codes from within the provided vocabulary
     *
     * @param {integer} vocab_ID - vocabulary to check for term code
     * @param {integer} dty_ID - field ID for new value, used to get backup vocab ID if needed
     * @param {array} values - array of values to check
     * @returns array of processed values
     */
    _getTermByCode: function(vocab_ID, dty_ID, values){

        if(!vocab_ID || !$Db.trm(vocab_ID)){
            vocab_ID = $Db.dty(dty_ID, 'dty_JsonTermIDTree');
        }

        if(vocab_ID < 1){
            return values;
        }

        for(const idx in values){

            if(!Number.isInteger(+values[idx])){
                continue;
            }

            let existing_term = $Db.getTermByCode(vocab_ID, +values[idx]);
            if(existing_term !== null){
                values[idx] = existing_term;
            }
        }

        return values;
    },

    /**
     * Checks the array of values for record pointer and relationship marker fields
     *  if the value is a number greater than zero it is assumed to be a record ID
     *
     * @param {array} values - array of values
     * @returns array of converted values
     */
    _processRecordFields: function(values){

        let new_values = [];

        for(const idx in values){

            if(!window.hWin.HEURIST4.util.isNumber(values[idx])){
                new_values.push({value: values[idx], search: values[idx], relation: null});
                continue;
            }

            if(parseInt(values[idx]) > 0){
                new_values.push(values[idx]);
            }
        }

        return new_values;
    },

    /**
     * Trims leading and trailing spaces from values and removes empty values from array of values
     *
     * @param {array} values - array of values
     */
    _processValues: function(values){

        for(const idx in values){

            let value = values[idx];
            value = typeof value === 'string' ? value.trim() : value;

            !window.hWin.HEURIST4.util.isempty(value) || values.splice(idx, 1);
        }
    },

    setupTimeout: function(){

        let that = this;

        this.timeout.action_timeout = setTimeout(function(){

            window.hWin.HEURIST4.msg.sendCoverallToBack();

            let field = that.timeout.field_name;
            let value = that.timeout.value;
            let dty_ID = that.options.mapping.fields[field];

            if(Array.isArray(value) || window.hWin.HEURIST4.util.isObject(value)){
                value = JSON.stringify(value);
            }

            window.hWin.HEURIST4.msg.showMsgErr({
                message: 'An error has occurred with mapping values to their respective fields,<br>'
                        + 'please report this by using the bug reporter under Help at the top right of the main screen or,<br>'
                        + 'via email directly to support@heuristnetwork.org so we can fix this quickly.<br><br>'
                        + 'Invalid field details:<br>'
                        + `Response field - "${field}"<br>`
                        + `Record field - "${$Db.rst(that.options.mapping.rty_ID, dty_ID, 'rst_DisplayName')}" (<em>${$Db.dty(dty_ID, 'dty_Type')}</em>)<br>`
                        + `Value to insert - "${value}"<br>`,
                error_title: 'Saving selection canceled',
                status: window.hWin.ResponseStatus.UNKNOWN_ERROR
            });
        }, 20000); // set timeout to 20 seconds
    },

    checkResultSize: function(result_size, max_size){

        if(result_size > max_size){
            window.hWin.HEURIST4.msg.showMsgDlg(
                `There are ${result_size} records satisfying these criteria, only the first ${max_size} are shown.<br>Please narrow your search.`
            );
        }
    },

    /**
     * Converts the provided country name into it's country code, 
     *  this is just for the few missed/incorrect with default Heurist
     *
     * @param {string} country_code - country's code/label to be mapped
     * @returns the country in code format
     */
    _getCountryCode: function(country_code){

        if(window.hWin.HEURIST4.util.isempty(country_code)){
            return '';
        }

        let term_label = $Db.trm(country_code, 'trm_Label');
        let _countryCode = $Db.trm(country_code, 'trm_Code');

        if(typeof term_label === 'object' || typeof _countryCode === 'object'){
            return '';
        }

        switch (term_label) {
            case 'Iran':
                _countryCode = 'IR';
                break;
            case 'Kyrgistan': // Kyrgzstan
                _countryCode = 'KG';
                break;
            case 'Syria':
                _countryCode = 'SY';
                break;
            case 'Taiwan':
                _countryCode = 'TW';
                break;
            case 'UAE':
                _countryCode = 'AE';
                break;
            case 'UK':
                _countryCode = 'GB';
                break;
            case 'USA':
                _countryCode = 'US';
                break;
            case 'Vietnam':
                _countryCode = 'VN';
                break;
            default:
                break;
        }

        return _countryCode;
    },

    constructLocation: function(long, lat){

        return !window.hWin.HEURIST4.util.isempty(long) && !window.hWin.HEURIST4.util.isempty(lat)
                ? `p POINT(${long} ${lat})`
                : '';
    },

    getTimespan: function(fld_Names, value){

        if(!fld_Names[0].startsWith('when') || window.hWin.HEURIST4.util.isempty(value)){
            return value;
        }

        if(fld_Names[2].startsWith('start') && value['timespans']){
            value = value['timespans'][0]['start'];
        }else if(fld_Names[2].startsWith('end') && value['timespans']){
            value = value['timespans'][0]['end'];
        }

        return value;
    },

    getValueByParts: function(fld_Names, value){

        if(window.hWin.HEURIST4.util.isempty(value)){
            return value;
        }

        for(const part of fld_Names){
            if(value && !window.hWin.HEURIST4.util.isempty(value[part])){
                value = value[part];
            }else if(part == 'count'){
                value = 0;
            }
        }

        return value;
    },

    createGeoFeature: function(value){

        value = {type: 'Feature', geometry: value};

        let wkt = stringifyMultiWKT(value);    

        if(window.hWin.HEURIST4.util.isempty(wkt)){
            return '';
        }

        let typeCode = 'm';
        if(wkt.indexOf('GEOMETRYCOLLECTION')<0 && wkt.indexOf('MULTI')<0){
            if(wkt.indexOf('LINESTRING')>=0){
                typeCode = 'l';
            }else if(wkt.indexOf('POLYGON')>=0){
                typeCode = 'pl';
            }else {
                typeCode = 'p';
            }
        }

        return `${typeCode} ${wkt}`;
    },

    _doSearch: function(parameters, request = {}){

        let that = this;

        let full_url = this.baseURL;
        if(!full_url.endsWith('?') && !full_url.endsWith('&')){
            full_url += full_url.indexOf('?') === false ? '?' : '&';
        }

        // Add parameters
        let has_params = false;
        if(!window.hWin.HEURIST4.util.isempty(parameters) && window.hWin.HEURIST4.util.isObject(parameters)){
            let params = {};
            for(let key in parameters){
                if(Object.hasOwn(parameters, key) && !window.hWin.HEURIST4.util.isempty(parameters[key])){
                    params[key] = parameters[key];
                }
            }
            has_params = Object.keys(params).length > 0;
            full_url += has_params ? new URLSearchParams(params).toString() : '';
        }

        if(!has_params){
            window.hWin.HEURIST4.msg.showMsgErr({
                status: window.hWin.ResponseStatus.ACTION_BLOCKED,
                message: 'Please enter a value into one of the input fields'
            });
            return;
        }

        request = $.extend({
            service: full_url,
            serviceType: this.serviceName
        }, request);

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element);

        // calls /heurist/hserv/controller/record_lookup.php
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, (response) => {

            window.hWin.HEURIST4.msg.sendCoverallToBack(); // hide loading cover

            response = window.hWin.HEURIST4.util.isJSON(response);

            if(Object.hasOwn(response, 'status') && response.status != window.hWin.ResponseStatus.OK){ // Error return
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }else if(!response){
                window.hWin.HEURIST4.msg.showMsgErr({
                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR,
                    message: 'Service data was returned in an unhandled format.'
                });
                return;
            }

            that._handleSearchResult(response);

        });
    },

    _handleSearchResult: function(response){
        this._onSearchResult(response);
    },

    valueToArray: function(values){

        if(window.hWin.HEURIST4.util.isObject(values)){
            values = Object.values(values);
        }
        if(!Array.isArray(values)){
            values = window.hWin.HEURIST4.util.isempty(values) ? '' : values;
            values = [values];
        }

        values = values.filter((val) => !window.hWin.HEURIST4.util.isempty(val));

        return values;
    }
});