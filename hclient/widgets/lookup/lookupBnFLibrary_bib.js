/**
* lookupBnFLibrary_bib.js - Searching the BnF Library's bibliographic records (Under Development)
* 
* This file:
*   1) Loads the content of the corresponding html file (lookupBnFLibrary_bib.html), and
*   2) Performs an api call to the BnF Library's Search API using the User's input, displaying the results within a Heurist result list
* 
* After record selection, the user:
*   Is required to select or create a record for each record pointer field, with a value
*   Is required to enter or correct any terms for each enumerated field, with a value 
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

$.widget( "heurist.lookupBnFLibrary_bib", $.heurist.lookupBnF, {

    // default options
    options: {
    
        height: 700,
        width:  800,
        
        title:  "Search the Bibliothèque nationale de France's bibliographic records"
    },

    baseURL: 'https://catalogue.bnf.fr/api/SRU?', // external url base
    serviceName: 'bnflibrary_bib', // service name

    _initControls: function(){

        // Extra field styling
        this.element.find('.header.recommended').css({width: '100px', 'min-width': '100px', display: 'inline-block'});
        this.element.find('.bnf_form_field').css({display:'inline-block', 'margin-top': '7.5px'});

        return this._super();
    },

    /**
     * Set up additional settings tab
     */
    _setupSettings: function(){

        this._super({
            author_codes: '',
            dump_record: true,
            dump_field: 'rec_ScratchPad'
        });
    },

    /**
     * Get listed author and contributor codes
     * 
     * @returns {Array<String, String>} array containing [author codes, contributor codes]
     */
    _getRoleCodes: function(){

        let author_codes = this.element.find('#author_codes').text();
        let contributor_codes = '';//this.element.find('#contributor-codes').text()
        const regex = /\d+/g;

        author_codes = regex.test(author_codes) ? author_codes.match(regex).join(',') : '';

        return [ author_codes, contributor_codes ];
    },

    /**
     * Save extra settings
     *
     * @param {Boolean} settings - whether to get settings for saving
     * @param {Boolean} close_dlg - whether to close the dialog after saving 
     */
    _saveExtraSettings: function(settings = false, close_dlg = false){

        const codes = this._getRoleCodes();
        const rec_dump_settings = this._getRecDumpSetting();

        if(settings !== null){
            settings = {
                author_codes: codes[0],
                dump_record: rec_dump_settings[0],
                dump_field: rec_dump_settings[1]
            };
        }

        this._super(settings, close_dlg);
    },

    /**
     * Result list rendering function called for each record
     *
     * @param {HRecordSet} recordset - complete record set, to retrieve fields
     * @param {Array} record - record being rendered
     * 
     * @returns {String} formatted html string
     */
    _rendererResultList: function(recordset, record){

        let that = this;

        /**
         * Get field details for displaying
         * 
         * @param {String} fldname - mapping field name
         * @param {Number} width - width for field
         * 
         * @returns {String} sized and formatted html string
         */
        function fld(fldname, width){

            let s = recordset.fld(record, fldname);

            if(fldname == 'author'){

                s = that.getAuthorHTML(recordset, record);

            }else if(fldname == 'publisher'){

                s = that.getPublisherHTML(s);

            }else{
                s = Array.isArray(s) ? s.join('; ') : s;
                s = window.hWin.HEURIST4.util.htmlEscape(s || '');
            }

            let title = s;

            if(fldname == 'biburl'){
                s = `<a href="${s}" target="_blank" rel="noopener"> view here </a>`;
                title = 'View bibliographic record';
            }

            return width > 0 ? `<div style="display:inline-block;width:${width}ex" class="truncate" title="${title}">${s}</div>` : s;
        }

        const recTitle = fld('author', 25) + fld('publisher', 20) + fld('date', 10) + fld('title', 70) + fld('biburl', 12); 
        recordset.setFld(record, 'rec_Title', recTitle);

        return this._super(recordset, record);
    },

    /**
     * Get author value as a html string
     *
     * @param {HRecordSet} recordset - complete record set, to retrieve fields
     * @param {Array} record - record being rendered
     * 
     * @returns {String} html formatted author value
     */
    getAuthorHTML: function(recordset, record){

        let value = recordset.fld(record, 'author');

        let contributor = recordset.fld(record, 'contributor');
        if(contributor !== undefined){
            value = window.hWin.HEURIST4.util.isempty(value) ? contributor : {...contributor, ...value};
        }

        if(window.hWin.HEURIST4.util.isempty(value)){
            return 'No provided creator';
        }

        let creator_val = '';

        for(let idx in value){

            let cur_obj = value[idx];
            let cur_string = cur_obj;

            if(window.hWin.HEURIST4.util.isObject(cur_obj)){
                cur_string = this._extractAuthorValue(cur_obj, false);
            }

            creator_val += !cur_string || typeof cur_string !== 'string' ? 'Missing author; ' : `${cur_string}; `;
        }

        return creator_val;
    },

    /**
     * Convert author value into formatted array of values
     *
     * @param {Object} cur_value - Author value to process
     * @param {Boolean} returning_search - is this for a search
     *
     * @returns {String|Array<String, String, String>} either field value or array of values [field value, search string, author role ID]
     */
    _extractAuthorValue: function(cur_value, returning_search = false){

        if(window.hWin.HEURIST4.util.isempty(cur_value)){
            return cur_value;
        }

        let value = '', search = '';

        value = cur_value['firstname'] ?? '';

        if(!window.hWin.HEURIST4.util.isempty(cur_value['surname'])){
            value = !window.hWin.HEURIST4.util.isempty(value) ? `${value} ${cur_value['surname']}` : cur_value['surname'];
        }

        search = value;

        if(!window.hWin.HEURIST4.util.isempty(cur_value['active'])){
            value += ` [${cur_value['active']}]`;
        }

        if(!returning_search){
            return value;
        }

        value = !window.hWin.HEURIST4.util.isempty(value) ? `${value} (id: ${cur_value['id']})` : `id: ${cur_value['id']}`;

        let role = !window.hWin.HEURIST4.util.isempty(value) ? cur_value['role'] : '';

        return [value, search, role];
    },

    /**
     * Convert publisher values into a html string
     *
     * @param {Array} value - array of publiher values to convert
     *
     * @returns {String} html formatted publisher string
     */
    getPublisherHTML: function(value){

        if(window.hWin.HEURIST4.util.isempty(value)){
            return 'No provided publisher';
        }

        let pub_val = '';

        for(let idx in value){

            let cur_obj = value[idx];
            let cur_string = cur_obj;

            if(window.hWin.HEURIST4.util.isObject(cur_obj)){
                [cur_string] = this._extractPublisherValue(cur_obj);
            }

            pub_val += !cur_string || typeof cur_string !== 'string' ? 'Missing author; ' : `${cur_string}; `;
        }

        return pub_val;
    },

    /**
     * Convert publisher value into formatted array of values
     *
     * @param {Object} cur_value - the current publisher
     * @param {Boolean} returning_search - is this for a search
     *
     * @returns {Array<String,String|Null>} an array containing [publisher value, seach string]
     */
    _extractPublisherValue: function(cur_value, returning_search = false){

        let value = '', search = '';

        if(!window.hWin.HEURIST4.util.isempty(cur_value['name'])){
            value = cur_value['name'];
            search = value;
        }
        if(!window.hWin.HEURIST4.util.isempty(cur_value['location'])){
            value = (value != '') ? `${value} ${cur_value['location']}` : cur_value['location'];
            search = (search != '') ? search : value;
        }

        return returning_search ? [value, search] : [value, null];
    },

    /**
     * Return record field values in the form of a json array mapped as [dty_ID: value, ...]
     * For multi-values, [dty_ID: [value1, value2, ...], ...]
     */
    doAction: function(){

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element);

        // get selected recordset
        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        this.setupTimeout();

        let res = {};
        let map_flds = Object.keys(this.options.mapping.fields); // mapped fields names, to access fields of rec

        res['BnF_ID'] = recset.fld(record, 'BnF_ID'); // add BnF ID
        res['ext_url'] = recset.fld(record, 'biburl'); // add BnF URL

        // Assign individual field values, here you would perform any additional processing for selected values (example. get ids for vocabulrary/terms and record pointers)
        for(const fld_Name of map_flds){

            this.timeout.field_name = fld_Name;
            let dty_ID = this.options.mapping.fields[fld_Name];
            if(dty_ID < 1){
                continue;
            }

            let val = recset.fld(record, fld_Name);
            this.timeout.value = val;

            let field_type = $Db.dty(dty_ID, 'dty_Type');

            val = this.valueToArray(val);

            if(window.hWin.HEURIST4.util.isempty(val)){
                continue;
            }

            switch(fld_Name){
                case 'author': // special treatment for author fields
                case 'contributor':

                    this.getAuthorValues(val, field_type);
                    break;
                case 'publisher':

                    this.getPublisherValues(val, field_type);
                    break;
                case 'language': // handle if language equals '###'

                    this.getLanguageValues(val);
                    break;
                default:

                    this.prepareValue(val, dty_ID, {check_term_codes: true});
                    break;
            }

            // Check that val and id are valid, add to response object
            if(window.hWin.HEURIST4.util.isempty(val)){
                continue;
            }
            if(!Object.hasOwn(res, dty_ID)){
                res[dty_ID] = [];
            }
            res[dty_ID] = res[dty_ID].concat(val);
        }

        this.closingAction(res);
    },

    /**
     * Prepare the current value for returning to the record editor
     *
     * @param {Object} values - mapped object of field valus
     * @param {Number} dty_ID - current field ID
     * @param {Object} settings - Any extra settings, for saving
     */
    prepareValue: function(values, dty_ID, settings){

        this._super(values, dty_ID, settings);

        // Match term labels with val, need to return the term's id to properly save its value
        if($Db.dty(dty_ID, 'dty_Type') == 'enum'){
            this._getTermByCode(null, dty_ID, values);
        }
    },

    /**
     * Processes author values for the specific field type
     *
     * @param {Object} values - mapped object of field values
     * @param {String} field_type - field type
     */
    getAuthorValues: function(values, field_type){

        for(const idx in values){

            const cur_val = values[idx];
            let is_object = window.hWin.HEURIST4.util.isObject(cur_val);
            
            let value = cur_val;
            let search = cur_val;
            let role = '';

            if(is_object){
                [value, search, role] = this._extractAuthorValue(cur_val, true);
            }

            if(window.hWin.HEURIST4.util.isempty(value) || Array.isArray(value) || window.hWin.HEURIST4.util.isObject(value)){
                continue;
            }

            values[idx] = field_type == 'resource' || field_type == 'relmarker' ? {value: value, search: search, relation: role} : value;
        }
    },

    /**
     * Processes publisher values for the specific field type
     *
     * @param {Object} values - mapped object of field values
     * @param {String} field_type - field type
     */
    getPublisherValues: function(values, field_type){

        for(const idx in values){

            let value = '';
            let search = '';
            const cur_val = values[idx];

            if(window.hWin.HEURIST4.util.isObject(cur_val)){
                [value, search] = this._extractPublisherValue(cur_val, true);
            }

            if(window.hWin.HEURIST4.util.isempty(value) || Array.isArray(value) || window.hWin.HEURIST4.util.isObject(value)){
                continue;
            }

            if(field_type == 'resource'){
                values[idx] = {value: value, search: search};
            }else if(field_type == 'relmarker'){
                values[idx] = {value: value, search: search, relation: ''};
            }else{
                values[idx] = value;
            }
        }
    },

    /**
     * Handle language strings, mostly to handle unknowns
     *
     * @param {String} values - mapped field values
     */
    getLanguageValues: function(values){

        for(const idx in values){
            values[idx] = window.hWin.HEURIST4.util.isempty(values[idx])
                        || values[idx] == '###'
                        || values[idx] == 'und'
                        ? 'unknown' : values[idx];
        }
    },

    /**
     * Create search URL using user input within form
     * Perform server call and handle response
     */
    _doSearch: function(){

        /**
         * recordSchema: XML structure for record details (changing this will require changes to the php code in record_lookup.php)
         * maximumRecords: maximum number of records returned from the search (api default: 100)
         * startRecord: starting point, complete searches in batches (api default: 1)
         * query: encoded string enclosed in brackets (at minimum, the spaces MUST be encoded)
         */
        let maxRecords = this.element.find('#rec_limit').val(); // limit number of returned records
        let params = {
            version: '1.2',
            operation: 'searchRetrieve',
            recordSchema: 'unimarcxchange',
            maximumRecords: !maxRecords || maxRecords <= 0 ? 20 : maxRecords,
            startRecord: 1
        };

        let has_filter = this.element.find('input.text:not(type)').filter((idx, input) => {
            return !window.hWin.HEURIST4.util.isempty($(input).val());
        });

        // Check that something has been entered
        if(has_filter.length == 0){
            window.hWin.HEURIST4.msg.showMsgFlash('Please enter a value in any of the search fields...', 1000);
            return;
        }
        
        // Construct query portion of url
        let query = '(';
        let last_logic = '';

        /** 
         * Additional search fields can be found here [catalogue.bnf.fr/api/test.do], note: ONLY the bibliographic fields can be added here (fields starting with 'bib.')
         * if you wish to query authority records (fields starting with 'aut.'), we suggest the alternative BnF lookup available (lookupBnFLibrary_aut)
         * 
         * each field name and search value are separated by a relationship, common ones are: [all, any, adj]
         * 
         * also separating each field query is a boolean logic [and, or, not]
         */

        let titleHasValue = this.element.find('#inpt_title').val() != '';
        let authorHasValue = this.element.find('#inpt_author').val() != '';
        let recidHasValue = this.element.find('#inpt_recordid').val() != '';

        // any field
        if(this.element.find('#inpt_any').val()!=''){
            last_logic = ` ${this.element.find('#inpt_any_logic').val()} `;
            query += `bib.anywhere ${this.element.find('#inpt_any_link').val()} "${this.element.find('#inpt_any').val()}"${last_logic}`;
        }

        // work title field
        if(titleHasValue){
            last_logic = ` ${this.element.find('#inpt_title_logic').val()} `;
            query += `bib.title ${this.element.find('#inpt_title_link').val()} "${this.element.find('#inpt_title').val()}"${last_logic}`;
        }

        // author field
        if(authorHasValue){
            last_logic = ` ${this.element.find('#inpt_author_logic').val()} `;
            query += `bib.author ${this.element.find('#inpt_author_link').val()} "${this.element.find('#inpt_author').val()}"${last_logic}`;
        }

        // record id field
        if(recidHasValue){
            last_logic = '';
            query += `bib.recordid ${this.element.find('#inpt_recordid_link').val()} "${this.element.find('#inpt_recordid').val()}"`;
            // no combination logic as record id is the last field
        }

        // Remove last logic connection
        if(!window.hWin.HEURIST4.util.isempty(last_logic)){
            let regex = new RegExp(`${last_logic}$`);
            query = query.replace(regex, '');
        }

        // Close off and encode query portion, then add to request url
        query += ')';
        params['query'] = query;

        const codes = this._getRoleCodes();

        this._super(params, {author_codes: codes[0]});
    }
});