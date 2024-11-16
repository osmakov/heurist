/**
 * lookupESTC_editions.js
 *
 *  1) Loads html content from lookupLRC18C.html
 *  2) User defines search parameters and searches Book(edition) (rt:30) in ESTC database
 *  3) Found records are show in list. User selects a record
 *  4) doAction calls search for details about selected book ids and linked Agent(rt:10), Place (12) and Work (49)
 *      Retrieves data from ESTC_Helsinki_Bibliographic_Metadata for record pointer and enumerated fields
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

$.widget("heurist.lookupESTC_editions", $.heurist.lookupESTC, {

    return_mapping: [
        {field_name: 'originalID', index: 'rec_ID'},
        {field_name: 'title', index: 1},
        {field_name: 'estcID', index: 254},
        {field_name: 'yearFirstVolume', index: 9},
        {field_name: 'yearLastVolume', index: 275},
        {field_name: 'summary', index: 285},
        {field_name: 'extendedTitle', index: 277},
        {field_name: 'numOfVolumes', index: 137},
        {field_name: 'numOfParts', index: 290},
        {field_name: 'imprintDetails', index: 270},
        {field_name: 'place', index: 259},
        {field_name: 'author', index: 15},
        {field_name: 'work', index: 284},
        {field_name: 'bookFormat', index: 256}
    ],

    search_mapping: {
        t: '30',
        'f:1': '@__edition_name__',
        'f:9': '__edition_date__',
        'linkedto:15': {t: '10', 'f:250': '__edition_author__'},
        'linkedto:284': {t: '49', 'f:272': '__edition_work__'},
        'linkedto:259': {t: '12', title: '__edition_place__'},
        'f:137': '=__vol_count__',
        'f:290': '=__vol_parts__',
        'f:256': '__select_bf__',
        'f:254': '@__estc_no__',
        'sortby': 'f:__sort_by_field__'
    },

    /**
     * Show a confirmation window after user selects a record from the lookup query results
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

        let recpointers = [];
        let term_id = '';

        for(const fld_Name of fields){

            let dty_ID = this.options.mapping.fields[fld_Name];
            if(dty_ID < 1 || !$Db.dty(dty_ID)){
                continue;
            }

            dlg_response[dty_ID] = this._mapValues(fld_Name, recset, record);
        }

        if(Object.hasOwn(details, 259)){ // Place - Rec Pointer
            recpointers.push(details[259]);
        }
        if(Object.hasOwn(details, 15)){ // Author - Rec Pointer
            recpointers.push(details[15]);
        }
        if(Object.hasOwn(details, 284)){ // Works - Rec Pointer
            recpointers.push(details[284]);
        }

        if(Object.hasOwn(details, 256)){ // Book format - Term
            term_id = details[256][0];
        }

        this._getRecPointers(dlg_response, recpointers.join(','), term_id);
    }
});