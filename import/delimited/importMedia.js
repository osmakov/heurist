/**
* Class to import recUploadedFiles from CSV
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
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
 * @class HImportMedia
 * @augments HImportBase
 * @classdesc For handling the bulk registeration of new external files by CSV
 *
 * @function doPrepare - Prepare data for registering new external media
 * @function doPost - Sends the prepared data server side to register new external media
 * @function prepareURLs - Process the URL field, which can contain several URLs to handle individually
 * @function prepareDescription - Process the description field, splitting it by the ', Download' separator
 */
class HImportMedia extends HImportBase{

    constructor(){
        let field_selectors = ['#field_url', '#field_desc'];
        super(0, 'ulf', field_selectors, false);
    }

    /**
     * Prepare CSV data for registering new external files
     */
    doPrepare(){

        this.prepared_data = [];

        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(this.parsed_data)){
            this.updatePreparedInfo('<i>No data. Upload and parse</i>', 0);
            return;
        }

        const field_url = $('#field_url').val();

        const allow_prepare = this.checkRequiredMapping({
            'URL/Path': [field_url]
        });
        if(allow_prepare !== true){
            this.updatePreparedInfo(`<span style="color:red">${allow_prepare} must be defined</span>`, 0);
            return;
        }

        let urls = [];
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
                'File URL or path': [field_url]
            });
            if(is_valid !== true){
                msg += `Row #${count} is missing: ${is_valid}<br>`;
                $('.tbmain').find(`tr:nth-child(${count})`).addClass('data_error');
                continue;
            }

            this.prepareURLs(row, urls);
        }//for

        msg = this.prepared_data.length == 0 ? '<span style="color:red">No valid files to import</span>' : msg;
        this.updatePreparedInfo(msg, this.prepared_data.length);
    }

    /**
     * Sends prepared data server side to register new external files
     */
    doPost(){

        let request = {
            is_download: $('#field_download').is(':checked') ? 1 : 0
        };

        super.doPost(request, (response) => {

            if(response.status != window.hWin.ResponseStatus.OK){
                this.showError(response);
                return;
            }

            window.hWin.HEURIST4.msg.showMsgDlg(response.data);
        });
    }

    /**
     * Process the current record account for potentially several URLs, leading to several records
     *
     * @param {array} row - current record row to process, with potentially multiple URLs to import 
     * @param {array} urls - already handled URLs, to avoid duplication here
     */
    prepareURLs(row, urls){

        const field_url = $('#field_url').val();

        const multival_separator = $('#multival_separator').val();

        let _urls = row[field_url];
        let descriptions = this.prepareDescription(row);

        _urls = multival_separator ? _urls.split(multival_separator) : [_urls];

        let url_count = -1;
        for(let url of _urls){

            const _url = url.trim();
            url_count ++;

            // also verify possible duplication
            if(window.hWin.HEURIST4.util.isempty(_url) || urls.indexOf(_url.toLowerCase()) >= 0){
                continue;
            }

            let _desc = url_count < descriptions.length ? descriptions[url_count] : '';

            urls.push(_url.toLowerCase());

            this.prepared_data.push({
                ulf_ExternalFileReference: _url,
                ulf_Description: _desc
            });
        }//_urls
    }

    /**
     * Prepare the file's description
     *
     * @param {array} row - current record row to retrieve the description from
     *
     * @returns {array} returns the prepared description value
     */
    prepareDescription(row){

        const field_url = $('#field_url').val();

        const field_desc = $('#field_desc').val();
        const field_desc_sep = ', Download ';
        const field_desc_concat = $('#field_desc_concat').is(':checked');

        if(field_desc < 0 || field_desc >= row.length){
            return [];
        }

        let description = row[field_desc].indexOf(' Download') == 0 ? row[field_desc].substring(9) : row[field_desc];
        description = description.trim().split(field_desc_sep);

        if(field_desc_concat){ //add other fields to description

            let _desc = '';
            for(const idx in row){
                _desc = idx != field_url && idx != field_desc ? `${row[idx]}, ${_desc}` : _desc;
            }
            description = description.map(desc => _desc + desc); // map extra description to start of every element
        }

        return description;
    }
}