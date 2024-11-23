/**
* Class to import record types from CSV
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
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

/**
 * @class HImportRecordTypes
 * @augments HImportBase
 * @classdesc For handling the bulk importing of new record types by CSV
 *
 * @function doPrepare - Prepare data for creating new record types
 */

class HImportRecordTypes extends HImportBase{

    /**
     * @param {integer} rtg_ID - default record type group ID, can be changed by the user
     */
    constructor(rtg_ID = 0){
        let field_selectors = ['#field_name', '#field_desc', '#field_uri'];
        super(rtg_ID, 'rty', field_selectors, !window.hWin.HEURIST4.util.isempty(rtg_ID) && rtg_ID > 0);
    }

    /**
     * Prepare CSV data for creating new record types
     */
    doPrepare(){

        this.prepared_data = [];

        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(this.parsed_data)){
            this.updatePreparedInfo('<i>No data. Upload and parse</i>', 0);
            return;
        }

        const field_name = $('#field_name').val();
        const field_desc = $('#field_desc').val();
        const field_uri = $('#field_uri').val();

        const allow_prepare = this.checkRequiredMapping({
            'Name': [field_name],
            'Description': [field_desc]
        });
        if(allow_prepare !== true){
            this.updatePreparedInfo(`<span style="color:red">${allow_prepare} must be defined</span>`, 0);
            return;
        }

        let msg = '';
        let found_header = !$('#csv_header').is(':checked');
        let count = 0;

        for(const row of this.parsed_data){

            if(!found_header){
                found_header = true;
                continue;
            }

            count ++;

            const is_valid = this.checkRequiredValues(row, {
                'name': [field_name],
                'description': [field_desc]
            });
            if(is_valid !== true){
                msg += `Row #${count} is missing: ${is_valid}<br>`;
                $('.tbmain').find(`tr:nth-child(${count})`).addClass('data_error');
                continue;
            }

            this.createRecord(row, {
                rty_Name: field_name,
                rty_Description: field_desc,
                rty_SemanticReferenceURL: field_uri
            });
        }//for

        msg = this.prepared_data.length == 0 ? '<span style="color:red">No valid record types to import</span>' : msg;
        this.updatePreparedInfo(msg, this.prepared_data.length);

        window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), (this.prepared_data.length == 0  || $('#field_rtg').val() == 0));
    }
}