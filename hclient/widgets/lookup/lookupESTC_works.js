/**
 * lookupESTC_works.js
 *
 *  1) Loads html content from lookupESTC_works.html
 *  2) User defines search parameters and searches Works (rt:49) in ESTC database
 *  3) Found records are show in list. User selects a record (var sels)
 *  4) doAction calls search for details about selected work's enum fields Retrieves data from ESTC_Helsinki_Bibliographic_Metadata
 *
 * @package     Heurist academic knowledge management system
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Darshan Nagavara   <darshan@intersect.org.au>
 * @author      Brandon McKay   <blmckay13@gmail.com>
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

$.widget("heurist.lookupESTC_works", $.heurist.lookupESTC, {

    return_mapping: [
        {field_name: 'title', index: 1},
        {field_name: 'extendedTitle', index: 276},
        {field_name: 'projectId', index: 271},
        {field_name: 'helsinkiTitle', index: 273},
        {field_name: 'helsinkiId', index: 272},
        {field_name: 'helsinkiIdAssignation', index: 298},
        {field_name: 'helsinkiRawData', index: 236}
    ],

    search_mapping: {
        t: '49',
        'f:1': '__work_name__',
        'f:271': '__project_id__',
        'f:273': '__helsinki_name__',
        'f:272': '__helsinki_id__',
        'sortby': 'f:__sort_by_field__'
    },

    _initControls: function () {

        this.element.find('#btnStartSearch').parent().parent().position({
            my: 'left center',
            at: 'right center',
            of: '#ent_header > fieldset'
        });

        return this._super();
    },

    /**
     * Show a confirmation window after user selects a record from the lookup query results
     *  If the user clicks "Check Author", then call method _checkAuthor
     */
    doAction: function(){

        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        let dlg_response = {};
        let fields = Object.keys(this.options.mapping.fields); // mapped fields names, to access fields of rec

        let details = record.d;

        if(!details){
            this._getRecordDetails(recset, record);
            return;
        }

        for(const fld_Name of fields){

            let dty_ID = this.options.mapping.fields[fld_Name];
            if(dty_ID < 1 || !$Db.dty(dty_ID)){
                continue;
            }

            dlg_response[dty_ID] = this._mapValues(fld_Name, recset, record);
        }

        let term_id = '';

        if(Object.hasOwn(details, 298)){
            term_id = details[298][0];
        }
        
        this._getTerms(dlg_response, term_id);
    }
});