/**
* lookupNomisma.js - Searching the Nomisma's records (Under Development)
* 
* This file:
*   1) Loads the content of the corresponding html file (lookupNomisma.html), and
*   2) Performs an api call to Nomisma's Search API using the User's input, displaying the results within a Heurist result list
* 
* Current Nomisma services supported:
*    - getMints
*    - getHoards
*    - getFindspots
*    - getRdf (currently unavailable)
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2021 University of Sydney
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

$.widget( "heurist.lookupNomisma", $.heurist.lookupBase, {

    // default options
    options: {

        height: 720,
        width:  510,

        title:  'Search Nomisma database of coins and currency via several options',

        htmlContent: 'lookupNomisma.html'
    },

    baseURL: '', // external url base
    serviceName: 'nomisma', // service name

    search_button_selector: '#btnMintSearch, #btnHoardsSearch, #btnFindspotsSearch',

    _initControls: function(){

        // Extra field styling
        this.element.find('#search_container > div > div > .header.recommended').css({'min-width':'65px', display: 'inline-block'});

        this.element.find('#btnRdfSearch').hide();

        // Prepare result list options
        this.options.resultList = $.extend(this.options.resultList, {
            empty_remark: '<div style="padding:1em 0 1em 0">No Results Found<br><br>'
                        + 'This result may also be due to a misconfiguration/failed connection to the Nomisma server.<br>'
                        + 'Please advise the Heurist team if this persists with searches which you are sure should return results.</div>' // For empty results
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

            let s

            if(fldname == 'dates'){
                s = `${recordset.fld(record, 'when.timespans.start')} to ${recordset.fld(record, 'when.timespans.end')}` 
                        + ` (end date: ${recordset.fld(record, 'properties.closing_date')})`;
            }else{
                s = recordset.fld(record, fldname);
            }

            s = s || '';
            let title = s;

            if(fldname == 'properties.gazetteer_uri'){
                s = `<a href="${s}" target="_blank" rel="noopener"> view here </a>`;
                title = 'View nomisma record';
            }else if(fldname == 'properties.count'){
                s = `(count: ${s})`;
            }

            return width > 0 ? `<div style="display:inline-block;width:${width}ex" class="truncate" title="${title}">${s}</div>` : s;
        }
        
        let recTitle = '';
        if(fld('properties.type') == 'hoard'){
            recTitle = fld('properties.type', 10) + fld('label', 30) + fld('dates', 35) + fld('properties.gazetteer_uri', 10);
        }else{
            recTitle = fld('properties.type', 10) + fld('properties.gazetteer_label', 30) + fld('properties.count', 15) + fld('properties.gazetteer_uri', 10); 
        }
        recordset.setFld(record, 'rec_Title', recTitle);

        return this._super(recordset, record);
    },

    /**
     * Either perform search or select entry in resultList and triggers addition of new record
     */
    doAction: function(){

        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        this.closingAction(recset);
    },

    /**
     * Create search URL and perform search
     *
     * @param {MouseEvent} event - OnClick event to retrieve the lookup type
     */
    _doSearch: function(event){

        let search_type = $(event.target).val();

        if(this.element.find('#inpt_any').val()==''){
            window.hWin.HEURIST4.msg.showMsgFlash('Enter value to search...', 500);
            return;
        }

        switch(search_type){
            case 'mint':
                this.baseURL = 'https://nomisma.org/apis/getMints?id=';
                break;
            case 'hoard':
                this.baseURL = 'https://nomisma.org/apis/getHoards?id=';
                break;
            case 'findspots':
                this.baseURL = 'https://nomisma.org/apis/getFindspots?id=';
                break;
            default:
                return;
        }

        this._super({id: this.element.find('#inpt_any').val()});
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

        let fields = ['rec_ID','rec_RecTypeID'];
        let map_flds = Object.keys(this.options.mapping.fields);
        fields = fields.concat(map_flds);

        map_flds = map_flds.map((prop) => prop.split('.'));

        if(!geojson_data.features) geojson_data.features = geojson_data;

        //parse json
        let i = 0;
        for(const feature of geojson_data.features){

            let recID = i++;
            
            let hasGeo = true;
            let values = [recID, this.options.mapping.rty_ID];
            for(const fld_Names of map_flds){

                let val = feature[fld_Names[0]];

                val = this.getTimespan(fld_Names, val);

                val = this.getValueByParts(fld_Names, val);

                if(DT_GEO_OBJECT == this.options.mapping.fields[fld_Names] && !window.hWin.HEURIST4.util.isempty(val)){ // looking for geospatial values
                    val = this.createGeoFeature(val);
                    hasGeo = !window.hWin.HEURIST4.util.isempty(val);
                } // else not looking for geospatial values

                values.push(val);    
            }

            if(hasGeo){
                res_orders.push(recID);
                res_records[recID] = values;    
            }
        }

        let res = res_orders.length > 0 ? {fields: fields, order: res_orders, records: res_records} : false;
        this._super(res);
    }
});