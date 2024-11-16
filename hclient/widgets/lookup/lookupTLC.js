/**
* lookupTLC.js - Lookup values in third-party web service for Heurist record 
* 
* This file:
*   1) Loads the content of the corresponding html file (lookupTLC.html)
*   2) Performs an api call to the Geoname service using the User's input, displaying the results within a Heurist result list
*   3) map external results with our field details (see options.mapping) and returns the mapped results to the record edit form
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

/* global stringifyMultiWKT */

$.widget( "heurist.lookupTLC", $.heurist.lookupBase, {

    // dialog options, the default values and other available options can be found in hclient/widget/record/recordAction.js
    options: {

        height: 520,
        width:  800,

        title:  'Lookup values for Heurist record', // dialog title

        htmlContent: 'lookupTLC.html' // in hclient/widgets/lookup folder
    },

    baseURL: '', // external url base
    serviceName: 'tlcmap', // service name

    _initControls: function(){

        this.element.find('fieldset > div > .header').css({width:'80px','min-width':'80px'});

        window.hWin.HEURIST4.msg.showMsgErr({
            error_title: 'Warning: broken lookup',
            message: 'Please beware that due to changes with the TLC map project this lookup is not currently 100% functional.<br><br>'
                    +'The issue appears when attempting to search, if the results are too large the server will response with a "un-supported format" error.<br>'
                    +'We recommend either:<br>'
                    +'a) Searching directly on the projects website at <a href="https://ghap.tlcmap.org/" target="_blank" rel="noopener">https://ghap.tlcmap.org/</a><br>'
                    +'b) Using one of the GeoNames lookup instead, or<br>'
                    +'c) You can attempt to refine your search here <strong>NOT RECOMMENDED</strong><br><br>'
                    +'We apologise for any inconvenience.'
        });

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

            if(fldname == 'properties.LGA'){ 
                s = s.lga; 
            }

            s = s || '';
            let title = s;

            if(fldname == 'tlc_link'){
                s = `<a href="${s}" target="_blank"> view here </a>`;
                title = 'View tlcmap record';
            }

            return width > 0 ? `<div style="display:inline-block;width:${width}ex" class="truncate" title="${title}">${s}</div>` : s;
        }

        const recTitle = fld('properties.placename',40) + fld('properties.LGA', 25) + fld('properties.state', 6) + fld('properties.description', 65) + fld('tlc_link', 12); 
        recordset.setFld(record, 'rec_Title', recTitle);

        return this._super(recordset, record);
    },

    /**
     * Either return mapped fields to record or create a new record using the mapped fields
     */
    doAction: function(){

        // retrieve selected record/s
        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        if(!this.options.add_new_record){
            this.closingAction(recset);
            return;
        }

        //create new record 
        let rectype_id = (!window.hWin.HEURIST4.util.isempty(this.options.rectype_for_new_record)) ? this.options.rectype_for_new_record : this.options.mapping.rty_ID;
        this._addNewRecord(rectype_id, recset);
    },

    /**
     * Create search url, perform search and call result handler
     */
    _doSearch: function(){
        
        let params = {
            format: 'json',
            paging: 100
        };

        // get base url
        if(this.options.mapping.service=='tlcmap'){
            this.baseURL = 'https://tlcmap.org/ghap/search?';
        }else if(this.options.mapping.service=='tlcmap_old'){
            this.baseURL = 'https://tlcmap.australiasoutheast.cloudapp.azure.com/ws/ghap/search?';  
        }else{
            window.hWin.HEURIST4.msg.showMsgFlash('Name of service not defined...', 500);
            return;
        }

        // check for input
        if(this.element.find('#inpt_name').val()=='' && this.element.find('#inpt_anps_id').val()==''){
            window.hWin.HEURIST4.msg.showMsgFlash('Define name or ANPS ID...', 500);
            return;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent()); // cover screen

        // add input to request url
        if(this.element.find('#inpt_name').val()!=''){
            let fld_name = this.element.find('#inpt_exact').is(':checked') ? 'name' : 'fuzzyname';
            params[fld_name] = this.element.find('#inpt_name').val();
        }
        if(this.element.find('#inpt_anps_id').val()!=''){
            params['anps_id'] = this.element.find('#inpt_anps_id').val();
        }
        if(this.element.find('#inpt_lga').val()!=''){
            params['lga'] = this.element.find('#inpt_lga').val()
        }
        if(this.element.find('#inpt_state').val()!=''){
            params['state'] = this.element.find('#inpt_state').val()
        }

        this._super(params);
    },

    /**
     * Prepare json for displaying via the Heuirst resultList widget
     *
     * @param {Object} json_data - search response
     */
    _onSearchResult: function(geojson_data){

        if(!window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true)){
            this._super(false);
        }

        let res_records = {}, res_orders = [];

        let DT_GEO_OBJECT = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'];

        // Retrieve fields for records, any additional fields that are not part of the new record (e.g. url to original record) can be added here
        let fields = ['rec_ID','rec_RecTypeID'];
        let map_flds = Object.keys(this.options.mapping.fields);
        fields = fields.concat(map_flds);
        fields = fields.concat('tlc_link');

        map_flds = map_flds.map((prop) => prop.split('.'));

        if(!geojson_data.features) geojson_data.features = geojson_data;

        // Parse GeoJSON, special handling can be done here (e.g. creating Geo Objects for Geospatial fields)
        let i = 0;
        for(const feature of geojson_data.features){

            let recID = i++;

            let hasGeo = false;
            let values = [recID, this.options.mapping.rty_ID];
            for(const fld_Name of map_flds){

                let val = feature[ fld_Name[0] ];

                val = this.getValueByParts(fld_Name, val);

                // Special handling for Geo Objects
                if(DT_GEO_OBJECT == this.options.mapping.fields[fld_Name] && !window.hWin.HEURIST4.util.isempty(val)){ // looking for geospatial values
                    val = this.createGeoFeature(val);
                    hasGeo = !window.hWin.HEURIST4.util.isempty(val);
                }

                values.push(val); // push value into record
            }

            // creating the additional recordLink field
            values.push(`https://tlcmap.org/ghap/search?id=${feature['properties']['id']}`);

            if(hasGeo){
                // Add record into recordset
                res_orders.push(recID);
                res_records[recID] = values;    
            }
        }

        let res = res_orders.length > 0 ? {fields: fields, order: res_orders, records: res_records} : false;
        this._super(res);
    }
});

