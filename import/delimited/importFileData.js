/**
* Class to import file data from CSV
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
 * @class HImportFileData
 * @augments HImportBase
 * @classdesc 
 *  For handling the bulk addition or replacement of already registered file details by CSV.
 *  For bulk registeration see HImportMedia
 *
 * @function doPrepare - Prepare data for adding/updating file details
 * @function doPost - Send the prepared data server side to add/update file details
 */

class HImportFileData extends HImportBase{

    constructor(){
        let field_selectors = ['#file_id', '#file_desc', '#file_cap', '#file_rights', '#file_owner', '#file_vis'];
        super(0, 'ulf', field_selectors, false);
    }

    /**
     * Prepare CSV data for adding/replacing already registered file details
     */
    doPrepare(){

        this.prepared_data = [];

        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(this.parsed_data)){
            this.updatePreparedInfo('<i>No data. Upload and parse</i>', 0);
            return;
        }

        const file_id = $('#file_id').val();
        const file_desc = $('#file_desc').val();
        const file_cap = $('#file_cap').val();
        const file_rights = $('#file_rights').val();
        const file_owner = $('#file_owner').val();
        const file_vis = $('#file_vis').val();

        const allow_prepare = this.checkRequiredMapping({
            'File ID': [file_id],
            'A file data field': [file_desc, file_cap, file_rights, file_owner, file_vis]
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
                'file ID': [file_id],
                'file data': [file_desc, file_cap, file_rights, file_owner, file_vis]
            });
            if(is_valid !== true){
                msg += `Row #${count} is missing: ${is_valid}<br>`;
                $('.tbmain').find(`tr:nth-child(${count})`).addClass('data_error');
                continue;
            }

            this.createRecord(row, {
                ID: file_id,
                ulf_Description: file_desc,
                ulf_Caption: file_cap,
                ulf_Copyright: file_rights,
                ulf_Copyowner: file_owner,
                ulf_WhoCanView: file_vis
            });
        }//for

        msg = this.prepared_data.length == 0 ? '<span style="color:red">No valid file details to import</span>' : msg;
        this.updatePreparedInfo(msg, this.prepared_data.length);
    }

    /**
     * Sends prepared data server side to add/replace registered file details
     */
    doPost(){

        let request = {
            import_data: $('[name="dtl_handling"]:checked').val(),
            id_type: $('#file_id_type').val()
        };
        super.doPost(request);
    }
}