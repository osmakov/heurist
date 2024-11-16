/**
* lookupNakala.js - Search Nakala public records
* 
*   It consists of search form and result list to select one or several values of record
*   1) perform a search request on Nakala's API (api.nakala.fr/search)
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

$.widget( "heurist.lookupNakalaAuthor", $.heurist.lookupBase, {

    // default options
    options: {

        height: 700,
        width:  550,

        title:  "Search Nakala's author records",

        htmlContent: 'lookupNakalaAuthor.html'
    },

    _initControls: function(){

        // Extra field styling
        this.element.find('#search_container > div > div > .header.recommended').css({width:'55px', 'min-width':'55px', display: 'inline-block'});
        this.element.find('#btn_container').position({my: 'left bottom', at: 'right bottom', of: '#search_container'});

        return this._super();
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
         * Get field details for displaying
         * 
         * @param {String} fldname - mapping field name
         * @param {Number} width - width for field
         * 
         * @returns {String} sized and formatted html string
         */
        function fld(fldname, width){

            let s = recordset.fld(record, fldname);

            if(window.hWin.HEURIST4.util.isempty(s) && s !== ''){
                s = '';
            }

            if(fldname == 'name'){
                s += `${recordset.fld(record, 'surname')}, ${recordset.fld(record, 'givenname')}`;
                if(recordset.fld(record, 'fullName') != ''){
                    s += ` (${recordset.fld(record, 'fullName')})`;
                }
            }

            s = window.hWin.HEURIST4.util.isObject(s) ? Object.values(s) : s;
            s = Array.isArray(s) ? s.join('; ') : s;

            let title = window.hWin.HEURIST4.util.htmlEscape(s || '');

            if(fldname == 'orcid' && s != ''){ // create anchor tag for link to external record
                s = `<a href="${s}" target="_blank" rel="noopener"> view ORCID record </a>`;
                title = 'View ORCID record';
            }
            
            if(width>0){
                s = `<div style="display:inline-block;width:${width}ex" class="truncate" title="${title}">${s}</div>`;
            }
            return s;
        }

        const recTitle = fld('name', 30) + fld('authorId', 40) + fld('orcid', 20);
        recordset.setFld(record, 'rec_Title', recTitle);

        return this._super(recordset, record);
    },

    /**
     * Return record field values in the form of a json array mapped as [dty_ID: value, ...]
     * For multi-values, [dty_ID: [value1, value2, ...], ...]
     */
    doAction: function(){
        this._super('orcid');
    },

    /**
     * Create search URL using user input within form
     * Perform server call and handle response
     */
    _doSearch: function(){

        let that = this;

        // Construct base url for external request
        let sURL = 'https://api.nakala.fr/authors/search?q='; // base URL for Nakala request
        
        // Construct query portion of url
        // any field
        if(this.element.find('#inpt_any').val()!=''){
            sURL += encodeURIComponent(this.element.find('#inpt_any').val());
        }

        // Check that something has been entered
        if(this.element.find('#inpt_any').val()==''){
            window.hWin.HEURIST4.msg.showMsgFlash('Please enter a name in the search field...', 1000);
            return;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent()); // show loading cover

        // for record_lookup.php
        let request = {
            service: sURL, // request url
            serviceType: 'nakala_author' // requesting service, otherwise the request will result in an error
        };

        // calls /heurist/hserv/controller/record_lookup.php
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack(); // hide loading cover

            response = window.hWin.HEURIST4.util.isJSON(response);

            if(!Array.isArray(response) && response?.status != window.hWin.ResponseStatus.OK){ // Error return
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            that._onSearchResult(response);
        });
    },

    /**
     * Prepare json for displaying via the Heuirst resultList widget
     *
     * @param {Object} json_data - search response
     */
    _onSearchResult: function(array_data){

        let maxRecords = $('#rec_limit').val(); // limit number of returned records
        maxRecords = (!maxRecords || maxRecords <= 0) ? 20 : maxRecords;

        let is_array = Array.isArray(array_data);

        if(!is_array || array_data.length == 0){
            this._super(is_array && array_data.length == 0 ? null : false);
        }

        let res_records = {}, res_orders = [];

        // Prepare fields for mapping
        let fields = ['rec_ID', 'rec_RecTypeID']; // added for record set
        let map_flds = Object.keys(this.options.mapping.fields);
        fields = fields.concat(map_flds);

        // Parse json to Record Set
        for(const recID in array_data){

            let record = array_data[recID];
            let values = [recID, this.options.mapping.rty_ID];

            // Add current record details, field by field
            for(const fld_Name of map_flds){
                values.push(record[fld_Name]);
            }

            res_orders.push(recID);
            res_records[recID] = values;
        }

        if(array_data.length > maxRecords){
            window.hWin.HEURIST4.msg.showMsgDlg(
                `There are ${array_data.length} records satisfying these criteria, only the first ${maxRecords} are shown.<br>Please narrow your search.`
            );
        }

        let res = res_orders.length > 0 ? {fields: fields, order: res_orders, records: res_records} : false;
        this._super(res);
    }
});