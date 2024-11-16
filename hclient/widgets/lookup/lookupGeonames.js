/**
* lookupGeonames.js - Base widgt for Geoname lookups
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

$.widget("heurist.lookupGeonames", $.heurist.lookupBase, {

    options: {

        height: 520,
        width: 800,

        title: 'Lookup values Postal codes for Heurist record',

        htmlContent: ''
    },

    _is_postal_codes: false,

    _country_vocab_id: 0,

    _init: function(){

        this._is_postal_codes = this.options.mapping.service == 'postalCodeSearch';

        this.options.htmlContent = this._is_postal_codes ? 'lookupGN_postalCode.html' : 'lookupGN.html';

        return this._super();
    },

    _initControls: function(){

        // Fill countries dropdown
        let ele = this.element.find('#inpt_country');
        this._country_vocab_id = $Db.getLocalID('trm','2-509');

        if(this._country_vocab_id > 0){
            window.hWin.HEURIST4.ui.createTermSelect(ele.get(0), {vocab_id:this._country_vocab_id,topOptions:'select...',useHtmlSelect:false});
        }
        if(ele.hSelect('instance') != 'undefined'){
            ele.hSelect('widget').css({'max-width':'30em'});
        }

        this.options.resultList = $.extend(this.options.resultList, {
            empty_remark: '<div style="padding:1em 0 1em 0">No Locations Found</div>'
        });

        return this._super();
    },

    /**
     * Return record field values in the form of a json array mapped as [dty_ID: value, ...], or
     * for multi-values, [dty_ID: [value1, value2, ...], ...]
     */
    doAction: function(){

        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        let link_field = this._is_postal_codes ? 'googlemap_link' : 'geoname_link';

        let res = {};
        res['ext_url'] = recset.fld(record, link_field);
        res = this.prepareValues(recset, record, res, {check_term_codes: this._country_vocab_id});

        res = this.handleGeoValue(res);

        // Pass mapped values and close dialog
        this.closingAction(res);
    },

    /**
     * Corrects the geospatial fields (lat and long) before returning the results back to the record editor
     *
     * @param {Object} res - Assigned field values, containing the keys lat and long
     *
     * @returns {Object} updated assigned field values
     */
    handleGeoValue: function(res){

        let geo_keys = Object.keys(res);
        geo_keys = geo_keys.filter((key) => key.indexOf('lat') > 0 || key.indexOf('long') > 0);

        if(geo_keys.length != 2){
            return res;            
        }

        let location_key = geo_keys[0].split('_')[0];

        if(!Object.hasOwn(res, location_key)){
            return res;
        }

        let locations = res[location_key];
        let latitude = res[geo_keys[0]];
        let longitude = res[geo_keys[1]];

        let idx = 0;
        while(idx < latitude.length && idx < longitude.length){

            const lat = latitude[idx];
            const long = longitude[idx];

            if(locations.indexOf(lat) >= 0 && locations.indexOf(long) >= 0){

                latitude.splice(idx, 1);
                longitude.splice(idx, 1);

                continue;
            }

            idx ++;
        }

        res[geo_keys[0]] = latitude;
        res[geo_keys[1]] = longitude;

        return res;
    }
});