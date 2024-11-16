/**
* lookupBnF.js - Base widgt for BnF lookups
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

$.widget("heurist.lookupBnF", $.heurist.lookupBase, {

    options: {
        htmlContent: ''
    },

    _is_authorities: false,

    _init: function(){

        this._is_authorities = this.options.mapping.service == 'bnfLibraryAut';

        this.options.htmlContent = this._is_authorities ? 'lookupBnFLibrary_aut.html' : 'lookupBnFLibrary_bib.html';

        return this._super();
    },

    _initControls: function(){

        let $select = this.element.find('#rty_flds');
        let top_opt = [{key: '', title: 'select a field...', disabled: true, selected: true, hidden: true}];
        let sel_options = {
            useHtmlSelect: false
        };
        window.hWin.HEURIST4.ui.createRectypeDetailSelect($select[0], this.options.mapping.rty_ID, ['blocktext'], top_opt, sel_options);

        this._on(this.element.find('input[name="dump_field"]'), {
            change: function(){
                let opt = this.element.find('input[name="dump_field"]:checked').val();
                window.hWin.HEURIST4.util.setDisabled(this.element.find('#rty_flds'), opt == 'rec_ScratchPad');
            }
        });

        return this._super();
    },

    /**
     * Set up additional settings tab
     */
    _setupSettings: function(def_options){

        let options = this.options.mapping?.options;
        let need_save = false;

        if(window.hWin.HEURIST4.util.isempty(options)){
            options = def_options;
            need_save = true;
        }

        if(!window.hWin.HEURIST4.util.isempty(options['dump_record'])){
            this.element.find('input[name="dump_record"]').prop('checked', options['dump_field']);
        }

        if(this.element.find('#author-codes').length > 0 && !window.hWin.HEURIST4.util.isempty(options['author_codes'])){
            this.element.find('#author-codes').text(options['author_codes']);
        }

        if(!window.hWin.HEURIST4.util.isempty(options['dump_field'])){
            const selected = options['dump_field'];

            if(selected === 'rec_ScratchPad'){
                this.element.find('input[name="dump_field"][value="rec_ScratchPad"]').prop('checked', true);
            }else{
                this.element.find('input[name="dump_field"][value="dty_ID"]').prop('checked', true);
                this.element.find('#rty_flds').val(selected);

                if(this.element.find('#rty_flds').hSelect('instance') !== undefined){
                    this.element.find('#rty_flds').hSelect('refresh');
                }
            }

            window.hWin.HEURIST4.util.setDisabled(this.element.find('#rty_flds'), selected == 'rec_ScratchPad');
        }

        if(need_save){
            this._saveExtraSettings();
        }
    },

    /**
     * Get record dump settings
     * 
     * @returns {Array<Boolean, String>} array containing the record dump setting and which record field to use
     */
    _getRecDumpSetting: function(){

        const get_recdump = this.element.find('input[name="dump_record"]').is(':checked');
        let recdump_fld = '';
        
        if(get_recdump){

            recdump_fld = this.element.find('input[name="dump_field"]:checked').val();
            if(recdump_fld === 'dty_ID'){
                recdump_fld = this.element.find('#rty_flds').val();
            }
        }

        return [ get_recdump, recdump_fld ];
    },

    /**
     * Prepare json for displaying via the Heuirst resultList widget
     *
     * @param {Object} json_data - search response
     */
    _onSearchResult: function(json_data){

        let maxRecords = this.element.find('#rec_limit').val(); // limit number of returned records
        maxRecords = (!maxRecords || maxRecords <= 0) ? 20 : maxRecords;

        json_data = window.hWin.HEURIST4.util.isJSON(json_data);

        if(!json_data?.result){
            this._super(false);
        }

        let res_records = {}, res_orders = [];

        // Prepare fields for mapping
        // the fields used here are defined within /heurist/hserv/controller/record_lookup_config.json where "service" = bnfLibrary
        let fields = ['rec_ID', 'rec_RecTypeID']; // added for record set
        let map_flds = Object.keys(this.options.mapping.fields);
        fields = fields.concat(map_flds, 'BnF_ID');

        // Parse json to Record Set
        let i = 1;
        for(const record of json_data.result){

            let recID = i++;
            let values = [recID, this.options.mapping.rty_ID];

            // Add current record details, field by field
            for(const fld_Name of map_flds){
                values.push(record[fld_Name]);
            }

            values.push(record['BnF_ID']);

            res_orders.push(recID);
            res_records[recID] = values;
        }

        this.checkResultSize(json_data.numberOfRecords, maxRecords);

        let res = res_orders.length > 0 ? {fields: fields, order: res_orders, records: res_records} : false;
        this._super(res);
    }
});