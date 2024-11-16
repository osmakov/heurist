/**
 * lookupLRC18C.js
 *
 *  1) Loads html content from LRC18C.html
 *  2) User defines search parameters and searches Book(edition) (rt:30) in ESTC database
 *  3) Found records are show in list. User selects a record (var sels)
 *  4) doAction calls doImportAction with request  
 *          selected book ids and linked Agent(rt:10), Place (12) and Work (49)
 *      Imports data from ESTC_Helsinki_Bibliographic_Metadata to Libraries_Readers_Culture_18C_Atlantic
 *
 * @package     Heurist academic knowledge management system
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Darshan Nagavara   <darshan@intersect.org.au>
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

let mapDict = {}
$.widget("heurist.lookupLRC18C", $.heurist.lookupESTC, {

    //defintions mapping
    // source rectype: target rectype
    mapping_defs:{
        10:{
            rty_ID:10,
            key:253, //Author->Agent
            details: { //source:target
                //Standarised Name
                250: 1,
                //Given Name
                18: 18,
                //Designation
                248: 999,
                //Family Name
                1: 1046,
                //ESTC Actor ID
                252: 1098,
                //ESTC Name Unified
                253: 1086, //KEY
                //Also Known As
                287: 132,
                //Birth Date
                10: 10,
                //Death Date
                11: 11,
                // Prefix
                249: 1049, //ENUM!
                // Suffix to Name
                279: 1050,
                // Agent Type
                278: 1000   //ENUM!
            }
        },

        49:{
            rty_ID:56,
            key:271, //Work->Work
            details: { 
                //Title
                1: 1,
                //Full/Extended title
                276: 1091,
                //Project Record ID
                271: 1092,
                //Helsinki Work Name
                273: 1093
                //272 => Helsinki Work ID
                //298 => Helsinki Work ID assignation
                //236 => Helsinki raw data
            }
        },    

        12:{
            rty_ID:12,
            key:268, //Place->Place
            details: { 
                //Title
                1: 1,
                //Region
                260: 939, //ENUM
                //Country
                264: 940, //ENUM
                //ESTC location ID                
                265: 1089, 
                //Place type
                133: 133, //ENUM  Cleaned Upper Territory vocab
                //ESTC Place ID
                268: 1090  //KEY
            }
        },    

        30:{
            rty_ID:55,
            key:254, //Book(edition)->Edition
            details: {
                //Title
                1: 1,
                // Year of First Volume
                9: 10,
                // Year of Final Volume
                275: 955,
                //Book Format
                256: 991, //ENUM
                //Plase - POINTER
                259: 238,
                //Summary Publisher Statement
                285: 1096,
                //ESTC ID
                254: 1094, //KEY
                //Author - POINTER
                15: 1106,
                //Work - POINTER
                284: 949,
                //Extended Edition title
                277: 1095,
                //No of volumes
                137: 962,
                // No of parts
                290: 1107,
                // Imprint details
                270: 652
            }
        },

        vocabularies:[
            5430, //1321-5430 book formats for 256 => 991  6891 ( 1323-6891 ) - SYNCED!
            5432, // ( 1321-5432 )region 18C for 260 => 939    6353
            5436, // ( 1321-5436 ) country 18C for 264 => 940   6499  BT Sovereign territory 18 century
            5039, // ( 3-5039 ) place type for 133 =>    5039  ( 3-5039 )
            507, // ( 2-507 ) prefix/honorofic for 249:1049       7124 (1323-7124)  - SYNCED
            5848 // ( 1321-5848 now  1323-6901)  agent type for 278: 1000   6901 (1323-6901)   - SYNCED
        ]

    },

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
     *  If the user clicks "Check Author", then call method _checkAuthor
     */
    doAction: function(){

        let that = this;

        let sels = this.recordList.resultList('getSelected', true); //ids of selected records
        if(!sels || sels.length == 0){
            return;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront( that._as_dialog.parent() );

        this._importRecords(sels.join(','));
    }
});