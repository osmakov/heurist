/**
* Base class for definition importing by CSV, does not include Records
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2024 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
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

/**
 * @class HImportBase
 * @classdesc Base class for importing definitions by CSV
 *
 * @property {array} allowed_entities - Currently handled entities
 * @property {json} entity_details - Configuration details for each handled entity
 * @property {string} entity_type - The import's current entity
 * @property {integer} column_count - Table headers count
 * @property {array} parsed_data - Imported file data/User entered data formatted into an array
 * @property {json} prepared_data - Processed parsed data into 'records' for creating the new definitions server side
 * @property {boolean} return_results - On success, close the window and return the result as context
 *
 * @function doParse - Convert the data from #sourceContent into a 2D array for processing
 * @function cleanupParsedData - Removes any empty rows or rows that contain only empty values
 * @function doPrepare - Processes the parsed data into records for creation, must be expanded by child classes
 * @function createRecord - Creates the record in json format, then adds it to the prepared records
 * @function checkRequiredMapping - Checks whether the required fields have been mapped before preparing
 * @function checkRequiredValues - Checks whether the provided indexes are valid
 * @function doPost - Sends the prepared records server side to create the new definitions
 * @function redrawPreviewTable - Draws the parsed data into a human readable table, including the results when return_results is false
 * @function renderTableHeader - Draws the column headers to the human readable table
 * @function renderTableBody - Draws the field data to the human readable table
 * @function getHeaderCount - Retrieves the maximum number of column headers needs, based on the longest row
 * @function prepareHeaders - Generates an array of headers to fit the maximum number of headers
 * @function matchColumns - Attempts to auto match columns from the CSV data to the mappable fields for the definition
 * @function handleResults - Adds the results from doPost to the human readable table
 * @function setupGroupSelector - Creates the necessary entity group selector, used by record types, base fields and terms
 * @function setupColumnRoles - Populates the column roles dropdowns with the headers, to allow field mapping
 * @function showError - Creates a Heurist error popup
 * @function updatePreparedInfo - Updates the prepared info elements with any error messages and the record count
 */

class HImportBase{

    #allowed_entities = ['rty', 'dty', 'trm', 'ulf'];
    #entity_details = {
        rty: {name: 'record type', group: 'rtg', script: 'defRecTypes', refresh: 'rty'},
        dty: {name: 'detail type', group: 'dtg', script: 'defDetailTypes', refresh: 'dty,rst'},
        trm: {name: 'term', group: 'trm', script: 'defTerms', refresh: 'trm'},
        vcg: {name: 'vocabulary group', group: 'vcg', script: 'defTerms', refresh: 'trm'},
        ulf: {name: 'file', group: '', script: 'recUploadedFiles', refresh: ''}
    };
    #entity_headers = {
        rty: {'#field_name': ['name', 'label', 'rectype'], '#field_desc': ['desc'], '#field_uri': ['uri', 'url', 'reference', 'semantic']},
        dty: {'#field_name': ['name', 'label', 'detailtype'], '#field_desc': ['desc'], '#field_uri': ['uri', 'url', 'reference', 'semantic'], '#field_type': ['type'], '#field_vocab': ['vocab', 'enum', 'term'], '#field_target': ['target', 'pointer', 'rectype']},
        trm: {'#field_term': ['name', 'label', 'term', 'vocab'], '#field_desc': ['desc'], '#field_uri': ['uri', 'url', 'reference', 'semantic'], '#field_code': ['code']},
        ulf: {'#field_url': ['url', 'path', 'uri'], '#field_desc,#file_desc': ['desc'], '#file_id': ['id', 'file'], '#file_cap': ['cap'], '#file_rights': ['right'], '#file_owner': ['owner'], '#file_vis': ['vis', 'whocanview']}
    };

    entity_type = null;

    column_count = 0;
    parsed_data = null;
    prepared_data = null;

    field_selectors = [];

    return_results = false;

    /**
     * Initialise UI elements and validate options/settings
     *
     * @param {integer} group_id - Default entity type group ID, if necessary
     * @param {string} entity_type - Entity type, short hand format
     * @param {string} field_selectors - A selector for field dropdowns to assign columns
     * @param {boolean} return_results - Whether to close the window and return the immport results ON SUCCESS ONLY
     */
    constructor(group_id, entity_type, field_selectors, return_results = false){

        // Validate simple values
        if(this.#allowed_entities.indexOf(entity_type) === -1){
            this.showError('ACTION_BLOCKED', 'Provided entity type is not currently handled', 'Invalid entity type');
            $('body').empty();
            return;
        }
        if(window.hWin.HEURIST4.util.isempty(field_selectors)){
            this.showError('ACTION_BLOCKED', 'Missing selectors for field dropdowns', 'No field selectors provided');
            $('body').empty();
            return;
        }

        this.entity_type = entity_type;
        this.field_selectors = Array.isArray(field_selectors) ? field_selectors.join(', ') : field_selectors;
        this.return_results = return_results;

        // Initialise UI elements, e.g. buttons, dropdowns, etc...
        let $uploadWidget = $('#uploadFile');

        let $btnUploadFile = $('#btnUploadFile')
                    .css({width: '120px', 'font-size': '0.8em'})
                    .button({label: window.hWin.HR('Upload File')})
                    .on('click', () => {
                        $uploadWidget.trigger('click');
                    });

        $('#btnParseData')
                    .css('width', '120px')
                    .button({label: window.hWin.HR('Analyse'), iconPosition:'end', icon:'ui-icon-circle-arrow-e'})
                    .on('click', () => {
                        this.doParse();
                    });
        
        let $btnStartImport = $('#btnImportData')
                    .css('width', '110px')
                    .addClass('ui-button-action')
                    .button({label: window.hWin.HR('Import'), iconPosition:'end', icon:'ui-icon-circle-arrow-e'})
                    .on('click', () => {
                        this.doPost();
                    });

        window.hWin.HEURIST4.util.setDisabled($btnStartImport, true);
        
        $('#csv_header').on('change', () => { this.doParse() });

        // Setup group selector fo entity
        if(!window.hWin.HEURIST4.util.isempty(this.#entity_details[this.entity_type]['group'])){
            this.setupGroupSelector(group_id);
        }

        window.hWin.HEURIST4.ui.createEncodingSelect($('#csv_encoding'));

        let src_content = '';
        $('#sourceContent').on('keyup', () => {
            let cur_content = $('#sourceContent').val().trim();
            src_content = src_content != cur_content ? cur_content : src_content; 
        });

        $uploadWidget.fileupload({
            url: `${window.hWin.HAPI4.baseURL}hserv/controller/fileUpload.php`, 
            formData: [
                {name:'db', value: window.hWin.HAPI4.database},
                {name:'entity', value:'temp'},
                {name:'max_file_size', value:1024*1024}
            ],
            autoUpload: true,
            sequentialUploads:true,
            dataType: 'json',
            done: (e, response) => {

                response = response.result;

                if(response.status != window.hWin.ResponseStatus.OK){
                    this.showError(response);
                    return;
                }

                let data = response.data;
                $.each(data.files, (index, file) => {

                    if(file.error){
                        $('#sourceContent').val(file.error);
                        return;
                    }

                    let url_get = `${file.deleteUrl.replace('fileUpload.php','fileGet.php')}&encoding=${$('#csv_encoding').val()}&db=${window.hWin.HAPI4.database}`;
                    $('#sourceContent').load(url_get, null);
                });

                $btnUploadFile.off('click');
                $btnUploadFile.on({click: function(){
                    $uploadWidget.trigger('click');
                }});                
            }
        });

        $('.column_roles').on('change', (e) => { 

            let $ele = $(e.target);
            if($ele.val() >= 0){
                // Reset any other selects mapped to the new field
                $(`.column_roles:not("#${$ele.attr('id')}") [value="${$ele.val()}"]:selected`).parent().val(-1);
            }

            // Update prepared data
            this.doPrepare();
        });
    }

    /**
     * Parse the provided input (either uploaded file or directly from the user) into flat rows
     */
    doParse(){

        let content = $('#sourceContent').val();
        if(content == ''){
            window.hWin.HEURIST4.msg.showMsgFlash('No content entered', 2000);
            return;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));

        let request = { 
            content: content,
            csv_delimiter: $('#csv_delimiter').val(),
            csv_enclosure: $('#csv_enclosure').val(),
            csv_linebreak: 'auto',
            id: window.hWin.HEURIST4.util.random()
        };

        window.hWin.HAPI4.doImportAction(request, (response) => {

            window.hWin.HEURIST4.msg.sendCoverallToBack();

            if(response.status != window.hWin.ResponseStatus.OK){
                this.showError(response);
                return;
            }

            this.parsed_data = response.data;

            this.cleanupParsedData();

            this.redrawPreviewTable();
        });
    }

    /**
     * Removes empty rows and rows that contain no data from the parsed data
     */
    cleanupParsedData(){

        this.parsed_data = this.parsed_data.filter(
            (row) => window.hWin.HEURIST4.util.isArrayNotEmpty(row)
                        && !row.every((value) => window.hWin.HEURIST4.util.isempty(value)));
    }

    /**
     * Convert the data rows into usable data for Heurist to interpret server side
     *  Function should be extended by child classes
     */
    doPrepare(){
        this.showError('UNKNOWN_ERROR', 'Failed to prepare data, the prepare function has not been implemented', 'Unable to prepare data');
    }

    /**
     * Prepares row as a record to be pushed into prepared array
     *
     * @param {array} row - parsed row of data
     * @param {json} fields - mapping definition field to parsed column index {field => index}
     * @param {json} record - default values for the new record
     */
    createRecord(row, fields, record = {}){

        for(const field in fields){

            const idx = fields[field];
            if(!idx || idx < 0 || row.length <= idx){
                continue;
            }

            record[field] = row[idx].trim();
        }

        if(Object.keys(record).length > 0){
            this.prepared_data.push(record);
        }
    }

    /**
     * Check whether the provided required fields have been mapped with valid indexes, 
     *  then constructs the message for any invalid field
     *
     * @param {json} fields - fields that must have a valid index assigned {field name => field indexes}
     *
     * @returns {boolean|string} returns true on no errors, otherwise returns a string with the missing fields
     */
    checkRequiredMapping(fields){

        let missing = [];

        for(const field_name in fields){
            let missing_fields = fields[field_name].filter(
                (index) => index < 0 || index >= this.column_count
            );
            missing_fields.length < fields[field_name].length || missing.push(field_name);
        }

        let last = '';
        switch(missing.length){
            case 0:
                return true;

            case 1:
            case 2:
                return missing.join(' and ');

            default:
                last = missing.pop();
                return `${missing.join(', ')} and ${last}`;
        }
    }

    /**
     * Check whether the provided fields have values, 
     *  then constructs the error message for any missing fields
     *
     * @param {array} data - row from parsed data, used to check for a value
     * @param {json} fields - fields to validate {field name => field value}
     *
     * @returns {boolean|string} returns true on no errors, otherwise returns a string with the invalid/missing fields
     */
    checkRequiredValues(data, fields){

        let missing = [];

        for(const field_name in fields){
            let invalid_indexes = fields[field_name].filter(
                (index) => index < 0 || index >= data.length || window.hWin.HEURIST4.util.isempty(data[index])
            );
            invalid_indexes.length < fields[field_name].length || missing.push(field_name);
        }

        let last = '';
        switch(missing.length){
            case 0:
                return true;

            case 1:
            case 2:
                return missing.join(' and ');

            default:
                last = missing.pop();
                return `${missing.join(', ')} and ${last}`;
        }
    }

    /**
     * Sends data to server to create the new definitions/values
     *
     * @param {json} request - Base of request object, is extended to include general properties
     * @param {function} callback - Handles the server response instead of the default below
     * @returns {void} Callback function receives the server's response
     */
    doPost(request = {}, callback = null){

        if(this.prepared_data.length < 1){
            window.hWin.HEURIST4.msg.showMsgFlash('No definitions prepared for importing...', 3000);
            return;
        }

        // Check group entity settings
        const group_entity = this.#entity_details[this.entity_type];
        const group_ID = $(`#field_${group_entity.group}`).val();

        if($(`#field_${group_entity.group}`).length > 0 && group_ID < 1){
            window.hWin.HEURIST4.msg.showMsgFlash(`Please select a ${group_entity.name} group...`, 2000);
            return;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));

        // Prepare request object with default values
        request = $.extend({
            a: 'batch',
            entity: group_entity.script,
            request_id: window.hWin.HEURIST4.util.random()
        }, request);

        // Add missing fields
        if(!Object.hasOwn(request, 'fields') && !Object.hasOwn(request, 'set_translations')){
            request['fields'] = JSON.stringify(this.prepared_data);
        }
        if(!Object.hasOwn(request, 'csv_import') && !Object.hasOwn(request, 'import_data')){
            request['csv_import'] = 1;
        }
        if(group_ID > 0){
            request[`${group_entity.group}_ID`] = group_ID;
        }

        window.hWin.HAPI4.EntityMgr.doRequest(request, (response) => {

            window.hWin.HEURIST4.msg.sendCoverallToBack();
            
            if(window.hWin.HEURIST4.util.isFunction(callback)){
                callback.call(this, response);
                return;
            }

            if(response.status != window.hWin.ResponseStatus.OK){
                this.showError(response);
                return;
            }

            let entities_refresh = group_entity.refresh;
            entities_refresh += response.data['refresh_terms'] ? ',trm' : '';

            if(this.return_results){

                window.hWin.HAPI4.EntityMgr.refreshEntityData(entities_refresh, () => {
                    window.close({ result: response.data });
                }); // update cache and return import results

                return;
            }

            response.refresh = entities_refresh;
            this.handleResults(response);
        });
    }

    /**
     * Draws human readable table based on parsed CSV data
     */
    redrawPreviewTable(){

        if(this.parsed_data==null){
            this.doParse();
            return;
        }

        // Get maximum column count
        this.column_count = this.getHeaderCount();

        let $container = $('#divParsePreview').empty();    
        let $table  = $('<table>', {class: 'tbmain'}).appendTo($container);
        const data_has_headers = $('#csv_header').is(':checked');

        // PREPARE HEADER FIELDS
        const headers = this.prepareHeaders();

        // RENDER TABLE HEADER
        this.renderTableHeader($table, headers);

        // RENDER TABLE BODY (parsed data)
        this.renderTableBody($table);

        // POPULATE COLUMN ROLES DROPDOWNS
        this.setupColumnRoles(headers);

        // AUTODETECT COLUMN ROLES by header names
        if(data_has_headers){
            this.matchColumns(headers);
        }

        this.doPrepare();
    }

    /**
     * Render the table's headings
     *
     * @param {jQuery} $table - jQuery object selecting the table
     * @param {array} headers - Array of column headers
     */
    renderTableHeader($table, headers){

        let $header_row = $('<tr>').appendTo($table);

        headers.forEach((header, idx) => {
            $('<th>', {
                class: 'truncate',
                text: header
            }).appendTo($header_row);
        });
    }

    /**
     * Render the table's data
     *
     * @param {jQuery} $table - jQuery object selecting the table
     */
    renderTableBody($table){

        let check_header = $('#csv_header').is(':checked');

        for(const row of this.parsed_data){

            if(check_header){
                check_header = false;
                continue;
            }

            let $body_row = $('<tr>').appendTo($table);
            for(let i = 0; i < this.column_count; i ++){
                $('<td>', {
                    class: 'truncate',
                    text: i < row.length ? row[i] : ''
                }).appendTo($body_row);
            }

        }
    }

    /**
     * Gets the column count for the parsed CSV data
     *  This count is based on each row's values, not just the first line
     *
     * @returns {integer} The number of columns needed for complete and valid CSV
     */
    getHeaderCount(){
        return this.parsed_data.reduce((max_columns, row) => {
            return Math.max(max_columns, row.length);
        }, 0);
    }

    /**
     * Setup the column headers 
     *
     * @returns {array} The column headers
     */
    prepareHeaders(){

        let headers = $('#csv_header').is(':checked') ? this.parsed_data[0] : [];

        return Array.from({length: this.column_count}, (val, idx) => {
            return headers.length > idx ? headers[idx] : `column ${idx}`;
        });
    }

    /**
     * Match the column headers from the CSV data to the mappable columns
     *  Function should be extended by child classes
     *
     * @param {array} headers - Column headers from data
     */
    matchColumns(headers = []){

        const entity = this.entity_type == 'vcg' ? 'trm' : this.entity_type;
        let fields = this.#entity_headers[entity];

        if(headers.length == 0){
            return;
        }

        headers = headers.map(header => header.toLowerCase());

        for(const selector in fields){

            let $select = $(selector);
            if($select.length == 0){
                continue;
            }

            let labels = fields[selector];

            let idx = headers.findIndex(header => labels.filter(field => header.indexOf(field) >= 0).length > 0);

            $select.val(idx);
        }
    }

    /**
     * Adds results from success import to data table
     *
     * @param {json} response - Response from server, includes: 
     *                          data - details about imported definitions
     *                          refresh - which entities cache needs to be updated, both server and client side
     */
    handleResults(response){

        let results = response.data;
        let $header_row = $('.tbmain').find('tr:first');
        let $rows = $('.tbmain').find('tr:not(.data_error)');
        let col_num = 0;
        let update_cache = false;

        // Add result header
        if($header_row.find('.post_results').length == 0){
            $('<th>', {class: 'post_results truncate', text: 'Results'}).appendTo($header_row);
        }else{
            col_num = $header_row.find('th').length - 1;
        }

        // Add result data
        for(let i = 1; i < $rows.length; i++){

            let $row = $($rows[i]);
            let data = results[i-1];

            if(data == null){
                continue;
            }
            if($row.length == 0){
                break;
            }

            if(data.indexOf('Created') >= 0){
                update_cache = true;
            }

            if(col_num == 0){
                $('<td>', {class: 'truncate', text: data}).appendTo($row);
            }else{
                $($row.find('td')[col_num]).text(data);
            }
        }

        // Refresh caches
        if(update_cache && !window.hWin.HEURIST4.util.isempty(response.refresh)){
            window.hWin.HAPI4.EntityMgr.refreshEntityData(response.refresh, null);
        }
    }

    /**
     * Creates the necessary definition group select, for base fields and record types
     *  Vocabularies/Terms currently only allow additions to the provided vocabulary group/parent term
     *
     * @param {integer} def_ID - Default value for group select
     */
    setupGroupSelector(def_ID){

        if(window.hWin.HEURIST4.util.isempty(this.entity_type)){
            return;
        }

        const group_entity = this.#entity_details[this.entity_type];

        let $select = $(`#field_${group_entity.group}`);
        let first_option = [{key: 0, title: `select a ${group_entity.name} group...`}];

        switch (this.entity_type) {
            case 'rty':
                window.hWin.HEURIST4.ui.createRectypeGroupSelect($select[0], first_option);
                break;

            case 'dty':
                window.hWin.HEURIST4.ui.createDetailtypeGroupSelect($select[0], first_option);
                break;

            default:
                break;
        }

        if($select.length == 0 || $select.find('option').length == 0){
            return;
        }

        $select.val(def_ID > 0 ? def_ID : 0);
        if($select.hSelect('instance') !== undefined){
            $select.hSelect('refresh');
        }

        $select.on('change', () => {

            const label = $select.find(':selected').text();
            $select.attr('title', label);
            
            if($select.hSelect('instance') !== undefined){
                $select.hSelect('widget').attr('title', label);
            }

            if($select.val() == 0){
                window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), true);
                return;
            }

            this.doPrepare();
        });
    }

    /**
     * Sets up the column roles dropdowns, used to map the CSV columns to imported fields
     *
     * @param {array} headers - Array of column headers
     */
    setupColumnRoles(headers){

        $('.column_roles').empty();
        let $field_sels = $(this.field_selectors);

        $('<option>', {value: -1, text: 'select...'}).appendTo($field_sels);

        for(const idx in headers){
            $('<option>', {value: idx, text: headers[idx]}).appendTo($field_sels);
        }
    }

    /**
     * Display Heurist error message popup
     *
     * @param {string|json} type - Heurist error status, needs to fit window.hWin.ResponseStatus otherwise unknown is used
     *                              If type is a JSON object, assume it is an error sent from the server
     * @param {string} message - Error message
     * @param {string} title - Error title
     */
    showError(type, message, title){

        if(window.hWin.HEURIST4.util.isObject(type)){
            window.hWin.HEURIST4.msg.showMsgErr(type);
            return;
        }

        if(window.hWin.HEURIST4.util.isempty(message)){
            return;
        }

        type = window.hWin.ResponseStatus[type] ?? window.hWin.ResponseStatus.UNKNOWN_ERROR;

        window.hWin.HEURIST4.msg.showMsgErr({message: message, error_title: title, status: type});
    }

    /**
     * Update the prepared info elements with messages and to-be imported record count
     *
     * @param {string} primary_msg - content to place in #preparedInfo
     * @param {string} count - record count to be placed in prepared info 2
     */
    updatePreparedInfo(primary_msg, count = 0){

        $('#preparedInfo').html(primary_msg);

        const msg = count <= 0 ? '' : `n = ${count}`;
        $('#preparedInfo2').html(msg);

        window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), count <= 0);
    }
}