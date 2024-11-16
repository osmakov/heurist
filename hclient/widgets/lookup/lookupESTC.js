/**
* lookupESTC.js - Base widgt for ESTC lookups
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

$.widget("heurist.lookupESTC", $.heurist.lookupBase, {

    options: {

        height: 540,
        width: 820,

        title: 'Lookup ESTC Helsinki Bibliographic Metadata values for Heurist record',

        htmlContent: ''
    },

    _is_works: false,

    search_mapping: {},
    return_mapping: [],

    _init: function(){

        this._is_works = this.options.mapping.service == 'ESTC_works';

        this.options.htmlContent = !this._is_works ? 'lookupLRC18C.html' : 'lookupESTC_works.html';

        return this._super();
    },

    _initControls: function(){

        let px = this._is_works ? 100 : 80;
        this.element.find('fieldset > div > .header').css({width: `${px}px`, 'min-width': `${px}px`});

        this.options.resultList = $.extend(this.options.resultList, {
            empty_remark: '<div style="padding:1em 0 1em 0">Nothing found</div>'
        });

        this._populateBookFormats();

        return this._super();
    },

    /**
     * Setting record type and title fields before formatting HTML
     *
     * @param {HRecordSet} recordset - complete record set
     * @param {Array} record - current record being rendered
     *
     * @returns {String} formatted html string
     */
    _rendererResultList: function (recordset, record) {

        recordset.setFld(record, 'rec_RecTypeID', 1);

        const rec_Title = recordset.fld(record, 'rec_Title');
        recordset.setFld(record, 'rec_Title', `<div class="recordTitle" style="left:30px;right:2px">${rec_Title}</div>`);

        return this._super(recordset, record);
    },

    /**
     * Retrieve and add options to the book format dropdown, formats are retrieve from ESTC database
     */
    _populateBookFormats: function(){

        if(this.element.find('#select_bf').length == 0){
            return;
        }

        //Populate Bookformat dropdown on lookup page
        let request = {
            serviceType: 'ESTC',
            db:'ESTC_Helsinki_Bibliographic_Metadata',
            a: 'search',
            entity: 'defTerms',
            details: 'list',
            request_id: window.hWin.HEURIST4.util.random(),
            trm_ParentTermID: 5430
        };

        let selBf = this.element.find('#select_bf').empty();
        window.hWin.HEURIST4.ui.addoption(selBf[0], 0, 'select...'); //first option

        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, (response) => {

            response = window.hWin.HEURIST4.util.isJSON(response);

            if(response.status != window.hWin.ResponseStatus.OK){
                return;
            }

            let recordset = new HRecordSet(response.data);
            recordset.each2((trm_ID, term) => {
                window.hWin.HEURIST4.ui.addoption(selBf[0], trm_ID, term['trm_Label']);
            });
        });
    },

    /**
     * Retrieve the detail fields for the suppied record from the ESTC database
     *  These are not retrieved early due to how large and un-necessary 
     *  it would be to retrieve ALL details for ALL results
     *
     * @param {HRecordSet} recset - complete record set
     * @param {Array} record - the relevent record missing record details
     */
    _getRecordDetails: function(recset, record){

        let that = this;

        let sel_Rec_ID = recset.fld(record, 'rec_ID'); 
        let query_request = { 
            serviceType: 'ESTC',
            org_db: window.hWin.HAPI4.database,
            db: 'ESTC_Helsinki_Bibliographic_Metadata',
            q: `ids:${sel_Rec_ID}`, 
            detail: 'detail'
        };
        
        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        window.hWin.HAPI4.RecordMgr.lookup_external_service(query_request, function(response){
            
            window.hWin.HEURIST4.msg.sendCoverallToBack();
            
            response = window.hWin.HEURIST4.util.isJSON(response);

            if(response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            let recordset = new HRecordSet(response.data);
            let record = recordset.getFirstRecord();
            if(!record?.d){
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: 'We are having trouble performing your request on the ESTC server. '
                            +`Impossible obtain details for selected record ${sel_Rec_ID}`,
                    error_title: 'Issues with ESTC server',
                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                });
                return;
            }

            // Update record + recordset
            let recset = that.recordList.resultList('getRecordSet');
            recset.addRecord2(sel_Rec_ID, record);

            that.doAction();
        });
    },

    /**
     * Import selected records from ESTC database
     *
     * @param {Array|String} rec_IDs - the records selectd by the user to be imported from the ESTC database
     */
    _importRecords: function(rec_IDs){

        let that = this;

        //avoid sync on every request
        this.mapping_defs['import_vocabularies'] = window.hWin.HEURIST4.dbs.vocabs_already_synched ? 0 : 1;

        rec_IDs = Array.isArray(rec_IDs) ? rec_IDs.join(',') : rec_IDs;

        let request = { 
            serviceType: 'ESTC',
            action: 'import_records',
            source_db: 'ESTC_Helsinki_Bibliographic_Metadata',
            org_db: window.hWin.HAPI4.database,
            db: window.hWin.HAPI4.database,
            q: `ids:${rec_IDs}`,
            rules: '[{"query":"t:10 linkedfrom:30-15"},{"query":"t:12 linkedfrom:30-259"},{"query":"t:49 linkedfrom:30-284"}]',
            mapping: this.mapping_defs,
            id: window.hWin.HEURIST4.util.random()
        };

        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function( response ){

            response = window.hWin.HEURIST4.util.isJSON(response);

            if(Object.hasOwn(response, 'status') && response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            let target_dty_ID = that.options.mapping.fields['properties.edition']

            let ids = response.data.ids; //all
            that._reportResults(ids, response.data);

            window.hWin.HEURIST4.dbs.vocabs_already_synched = true;

            that.closingAction({[target_dty_ID]: ids[0]});
        });
    },

    /**
     * Get additional record(s) from the ESTC database,
     *  these records belong to record pointer fields
     *
     * @param {Object} dlg_response - alreay mapped field results, new record ids to be added
     * @param {Array|String} rec_IDs - record ID(s) to import from ESTC database
     * @param {Number|Array} term_ID - term ID(s) to import from ESTC database, after the records
     */
    _getRecPointers: function(dlg_response, rec_IDs, term_ID){

        let that = this;

        if(window.hWin.HEURIST4.util.isempty(rec_IDs)){
            this._getTerms(dlg_response, term_ID);
            return;
        }

        rec_IDs = Array.isArray(rec_IDs) ? rec_IDs.join(',') : rec_IDs;

        let query_request = { 
            serviceType: 'ESTC',
            org_db: window.hWin.HAPI4.database,
            db: 'ESTC_Helsinki_Bibliographic_Metadata',
            q: `ids:"${rec_IDs}"`, 
            detail: 'header' 
        };
        
        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        window.hWin.HAPI4.RecordMgr.lookup_external_service(query_request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack();
            response = window.hWin.HEURIST4.util.isJSON(response);

            if(response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            let recordset = new HRecordSet(response.data);
            recordset.each2(function(id, record){
                for(const i in dlg_response){

                    dlg_response[i] = Array.isArray(dlg_response[i]) ? dlg_response[i] : [dlg_response[i]];

                    if(that.assignValue(dlg_response[i], id, record['rec_Title'])){
                        break;
                    }
                }
            });

            dlg_response['heurist_url'] = `https://heuristref.net/h6-alpha/?db=ESTC_Helsinki_Bibliographic_Metadata&w=a&q=ids:${rec_IDs}`;

            if(window.hWin.HEURIST4.util.isempty(term_ID)){
                that.closingAction(dlg_response);
                return;
            }

            that._getTerms(dlg_response, term_ID);
        });
    },

    /**
     * Gets the missing terms from the ESTC database, for user to define matching value / create new term
     *
     * @param {Object} dlg_response - alreay mapped field results, new term IDs to be added
     * @param {Number|Array} term_ID - term ID(s) to import from ESTC database
     */
    _getTerms: function(dlg_response, term_ID){

        let that = this;

        if(window.hWin.HEURIST4.util.isempty(term_ID)){
            this.closingAction(dlg_response);
            return;
        }

        let request = {
            serviceType: 'ESTC',
            db: 'ESTC_Helsinki_Bibliographic_Metadata',
            a: 'search',
            entity: 'defTerms',
            details: 'list', //name
            request_id: window.hWin.HEURIST4.util.random(),
            trm_ID: term_ID
        };

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());
        
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack();
            response = window.hWin.HEURIST4.util.isJSON(response);

            if(response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            let recordset = new HRecordSet(response.data);
            recordset.each2(function(id, record){
                for(const i in dlg_response){

                    dlg_response[i] = Array.isArray(dlg_response[i]) ? dlg_response[i] : [dlg_response[i]];

                    let assigned_label = that.assignValue(dlg_response[i], id, {
                        label: record['trm_Label'],
                        desc: record['trm_Description'],
                        code: record['trm_Code'],
                        uri: record['trm_SemanticReferenceURL']
                    });

                    if(assigned_label){
                        break;
                    }
                }
            });

            that.closingAction(dlg_response);
        });
    },

    /**
     * Replaces placeholder term and record IDs with values that will allow the user to either:
     *  select an existing term/record, or 
     *  create a new term/record
     *
     * @param {Array} values - array of values to check
     * @param {Number} to_replace - the id value to replace
     * @param {String|Array} replace_value - what to replace the id value with
     *
     * @returns {Boolean} whether the value was replaced
     */
    assignValue: function(values, to_replace, replace_value){

        let replaced_value = false;

        for(const idx in values){

            if(values[idx] == to_replace){

                values[idx] = replace_value;
                replaced_value = true;
                break;
            }
        }

        return replaced_value;
    },

    /**
     * Report results from importing ESTC records
     *
     * @param {Array|String} rec_IDs - array or comma list of record IDs
     * @param {Object} data - counts and new record IDs to display in report
     */
    _reportResults: function(rec_IDs, data){

        const cnt = data.count_imported;
        const cnt_ex = data.cnt_exist;
        const cnt_i = data.count_ignored;
        const ids = data.ids; //all

        let ids_ex = data.exists; //skipped
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(ids_ex)) ids_ex = [];

        const imported_extra = cnt > 1 ? 's are' : ' is';
        const existed_extra = cnt_ex > 1 ? 's are' : ' is';
        const skipped_extra = cnt_i > 1 ? 's are' : ' is';

        const sIgnored = cnt_i > 0 
            ? `${cnt_i} record${skipped_extra} skipped. Either record type is not set in mapping or is missing from this database` : '';

        rec_IDs = !Array.isArray(rec_IDs) ? rec_IDs.split(',') : rec_IDs;
        rec_IDs = rec_IDs.concat(ids_ex);

        rec_IDs = rec_IDs.filter((rec_ID) => !window.hWin.HEURIST4.util.isempty(rec_ID) && rec_ID > 0);

        let query_request = { 
            serviceType: 'ESTC',
            org_db: window.hWin.HAPI4.database,
            db: 'ESTC_Helsinki_Bibliographic_Metadata',
            q: `ids:"${rec_IDs.join(',')}"`, 
            w: 'a',
            detail: 'header' 
        };

        //find record titles
        window.hWin.HAPI4.RecordMgr.lookup_external_service(query_request, (response) => {

            window.hWin.HEURIST4.msg.sendCoverallToBack();
            response = window.hWin.HEURIST4.util.isJSON(response);

            if(Object.hasOwn(response, 'status') && response.status != window.hWin.ResponseStatus.OK){
                return;
            }
            
            let sImported = '', sExisted = '';

            let recordset = new HRecordSet(response.data);

            for(const rec_ID of ids){
                let rec = recordset.getById(rec_ID);
                sImported += ids_ex.indexOf(rec_ID) < 0 ? `<li>${rec_ID}: ${recordset.fld(rec, 'rec_Title')}</li>` : '';
            }
            sImported = cnt > 0 ? `<ul>${sImported}</ul>` : 'None';
            sImported = `${cnt} record${imported_extra} imported:<br>${sImported}`;

            for(const rec_ID of ids_ex){
                let rec = recordset.getById(rec_ID);
                sExisted += `<li>${rec_ID}: ${recordset.fld(rec, 'rec_Title')}</li>`;
            }
            sExisted = cnt_ex > 0 ? `${cnt_ex} record${existed_extra} already in database<br><ul>${sExisted}</ul>` : 'None';

            window.hWin.HEURIST4.msg.showMsgDlg(`<p>Lookup has been completed.</p>${sImported}${sExisted}${sIgnored}`);
        });
    },

    /**
     * Construct and send query search to Heurist ESTC database
     */
    _doSearch: function(){

        let that = this;

        let query = {};
        for(const field in this.search_mapping){

            let value_field = this.search_mapping[field];
            let actual_field = typeof value_field === 'string' ? value_field : Object.values(value_field)[1];

            let placeholder = actual_field.match(/__([a-zA-Z_]{7,14})__/);
            if(!placeholder){
                query[field] = value_field;
                continue;
            }

            let value = this.element.find(`#${placeholder[1]}`).val();
            if(window.hWin.HEURIST4.util.isempty(value) || value == 0){
                continue;
            }

            if(actual_field === value_field){
                query[field] = value_field.replace(placeholder[0], value);
            }else{
                let replacing = Object.keys(value_field)[1];
                value_field[replacing] = actual_field.replace(placeholder[0], value);
                query[field] = value_field;
            }
        }

        if(Object.keys(query).length <= 2){
            window.hWin.HEURIST4.msg.showMsgFlash('Please specify some criteria to narrow down the search...', 1000);
            return;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        let query_request = { 
            serviceType: 'ESTC',
            org_db: window.hWin.HAPI4.database,
            db: 'ESTC_Helsinki_Bibliographic_Metadata',
            q: query, 
            limit: 1000,
            detail: 'header' 
        };

        window.hWin.HAPI4.RecordMgr.lookup_external_service(query_request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack();
            response = window.hWin.HEURIST4.util.isJSON(response);

            if(Object.hasOwn(response, 'status') && response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            if(response.data.count>response.data.reccount){
                window.hWin.HEURIST4.msg.showMsgDlg(`Your request generated ${response.data.count} results. `
                + `Only first ${response.data.reccount} have been retrieved. `
                + 'You may specify more restrictive criteria to narrow the result.');
                response.data.count = response.data.reccount;
            }

            that._onSearchResult(response);
        });
    },

    /**
     * Build each Book(Edition) [LRC18C|ESTC_editions] or Works [ESTC_works]
     *  as a record to display list of records that can be selected by the user
     *
     * @param {Object} response - JSON response from local/remote Heurist server
     */
    _onSearchResult: function (response) {
        if(!response.data){
            response.data = response;
        }
        this._super(response.data, true);
    },

    /**
     * Retrieves the value from the record via the mapping provided
     *  defintions mapping can be found in the original version => lookupLRC18C.js
     *
     * @param {String} field_name - field to be mapped
     * @param {HRecordSet} recordset - current record set
     * @param {Array} record - current record from record set
     *
     * @returns {String} final value
     */
    _mapValues: function(field_name, recordset, record){

        const field = this.return_mapping.find((field) => field.field_name === field_name);

        if(!field){
            return '';
        }

        let value = '';

        value = recordset.fld(record, field.index);

        return !window.hWin.HEURIST4.util.isempty(value) ? value : '';
    }
});