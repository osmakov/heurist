/**
* lookupBnFLibrary_aut.js - Searching the BnF Library's authoritative records (Under Development)
* 
* This file:
*   1) Loads the content of the corresponding html file (lookupBnFLibrary_aut.html), and
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

$.widget( "heurist.lookupBnFLibrary_aut", $.heurist.lookupBnF, {

    // default options
    options: {
    
        height: 750,
        width:  530,
        
        title:  "Search the Bibliothèque nationale de France's authoritative records",
        
        htmlContent: 'lookupBnFLibrary_aut.html'
    },

    baseURL: 'https://catalogue.bnf.fr/api/SRU?', // external url base
    serviceName: 'bnflibrary_aut', // service name

    _initControls: function(){

        // Extra field styling
        this.element.find('.header.recommended').css({width: '100px', 'min-width': '100px', display: 'inline-block'}).addClass('truncate');
        this.element.find('.bnf_form_field').css({display:'inline-block', 'margin-top': '2.5px'});

        return this._super();
    },

    /**
     * Set up additional settings tab
     */
    _setupSettings: function(){

        this._super({
            dump_record: true,
            dump_field: 'rec_ScratchPad'
        });
    },

    /**
     * Save extra settings
     *
     * @param {Boolean} settings - whether to get settings for saving 
     * @param {Boolean} close_dlg - whether to close the dialog after saving 
     */
    _saveExtraSettings: function(settings = false, close_dlg = false){

        const rec_dump_settings = this._getRecDumpSetting();

        if(settings !== false){
            settings = {
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

        /**
         * Retrieve specific field width
         *
         * @param {Number} def_width - default width
         * @param {Number} type - field code
         * @param {String} fld_name - field name
         *
         * @returns {Number} field width
         */
        function getFieldWidth(def_width, type, fld_name){

            let width = def_width;

            switch(type){

                case '215':
                case '216':
                case '240':
                case '250':
                    width = fld_name == 'name' ? 75 : 0;
                    break;

                case '200':
                    width = fld_name == 'location' ? 0 : width;
                    width = fld_name == 'name' ? 50 : width;
                    break;

                case '210':
                    width = fld_name == 'years_active' ? 0 : width;
                    width = fld_name == 'name' ? 40 : width;
                    width = fld_name == 'location' ? 20 : width;

                    break;

                default:
                    break;
            }

            return width;
        }

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
            let authority_type = recordset.fld(record, 'authority_type');

            s = window.hWin.HEURIST4.util.htmlEscape(s || '');

            let title = s;

            if(fldname == 'auturl'){
                s = `<a href="${s}" target="_blank"> view here </a>`;
                title = 'View authoritative record';
            }

            width = getFieldWidth(width, authority_type);
            if(s != ''){
                s = fldname == 'years_active' || fldname == 'location' ? `( ${s} )` : s;
                s = fldname == 'role' ? `[ ${s} ]` : s;
            }

            return `<div style="display:inline-block;width:${width}ex" class="truncate" title="${title}">${s}</div>`;
        }

        const recTitle = fld('name', 35) + fld('location', 15) + fld('years_active', 10) + fld('role', 15) + fld('auturl', 10);
        recordset.setFld(record, 'rec_Title', recTitle);

        return this._super(recordset, record);
    },

    /**
     * Return record field values in the form of a json array mapped as [dty_ID: value, ...]
     * For multi-values, [dty_ID: [value1, value2, ...], ...]
     */
    doAction: function(){

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element);

        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        let res = {};
        res['BnF_ID'] = recset.fld(record, 'BnF_ID'); // add BnF ID
        res['ext_url'] = recset.fld(record, 'auturl'); // add BnF URL

        res = this.prepareValues(recset, record, res, {check_term_codes: true});

        this.closingAction(res);
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
        const maxRecords = $('#rec_limit').val(); // limit number of returned records
        let params = {
            version: '1.2',
            operation: 'searchRetrieve',
            recordSchema: 'unimarcxchange',
            maximumRecords: !maxRecords || maxRecords <= 0 ? 20 : maxRecords,
            startRecord: 1
        };

        let accesspointHasValue = this.element.find('#inpt_accesspoint').val() != '';
        let typeHasValue = this.element.find('#inpt_type').val() != '';
        let isniHasValue = this.element.find('#inpt_isni').val() != '';
        let isnidateHasValue = this.element.find('#inpt_isnidate').val() != '';
        let domainHasValue = this.element.find('#inpt_domain').val() != '';
        let recidHasValue = this.element.find('#inpt_recordid').val() != '';

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
         * Additional search fields can be found here [catalogue.bnf.fr/api/test.do], note: ONLY the authoritative fields can be added here (fields starting with 'aut.')
         * if you wish to query bibliographic records (fields starting with 'bib.'), we suggest the alternative BnF lookup available (lookupBnFLibrary_bib)
         * 
         * each field name and search value are separated by a relationship, common ones are: [all, any, adj]
         * 
         * also separating each field query is a boolean logic [and, or, not]
         */

        // any field
        if(this.element.find('#inpt_any').val()!=''){
            last_logic = ` ${this.element.find('#inpt_any_logic').val()} `;
            query += `aut.anywhere ${this.element.find('#inpt_any_link').val()} "${this.element.find('#inpt_any').val()}"${last_logic}`;
        }

        // access point field
        if(accesspointHasValue){
            last_logic = ` ${this.element.find('#inpt_accesspoint_logic').val()} `;
            query += `aut.accesspoint ${this.element.find('#inpt_accesspoint_link').val()} "${this.element.find('#inpt_accesspoint').val()}"${last_logic}`;
        }

        // type field
        if(typeHasValue){
            last_logic = ` ${this.element.find('#inpt_type_logic').val()} `;
            query += `aut.type ${this.element.find('#inpt_type_link').val()} "${this.element.find('#inpt_type').val()}"${last_logic}`;
        }

        // isni field
        if(isniHasValue){
            last_logic = ` ${this.element.find('#inpt_isni_logic').val()} `;
            query += `aut.isni ${this.element.find('#inpt_isni_link').val()} "${this.element.find('#inpt_isni').val()}"${last_logic}`;
        }

        // isni date field
        if(isnidateHasValue){
            last_logic = ` ${this.element.find('#inpt_isnidate_logic').val()} `;
            query += `aut.isnidate ${this.element.find('#inpt_isnidate_link').val()} "${this.element.find('#inpt_isnidate').val()}"${last_logic}`;
        }

        // domain field
        if(domainHasValue){
            last_logic = ` ${this.element.find('#inpt_domain_logic').val()} `;
            query += `aut.domain ${this.element.find('#inpt_domain_link').val()} "${this.element.find('#inpt_domain').val()}"${last_logic}`;
        }

        // record id field
        if(recidHasValue){
            last_logic = '';
            query += `aut.recordid ${this.element.find('#inpt_recordid_link').val()} "${this.element.find('#inpt_recordid').val()}"`;
        }

        // Remove last logic connection
        if(!window.hWin.HEURIST4.util.isempty(last_logic)){
            let regex = new RegExp(`${last_logic}$`);
            query = query.replace(regex, '');
        }

        // Close off and encode query portion, then add to request url
        query += ')';
        params['query'] = query;

        this._super(params);
    }
});