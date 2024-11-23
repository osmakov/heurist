/**
* Class to import terms from CSV
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
 * @class HImportTerms
 * @augments HImportBase
 * @classdesc For handling the bulk importing of new vocabularies and terms, or new label and description translations, by CSV
 *
 * @property {integer} vcg_ID - the default vocabulary group ID to add the new vocabularies to
 * @property {integer} trm_ParentTermID - the default vpcabulary/parent term to add the new terms to
 * @property {string} trm_Domain - what types of new vocabularies/terms are being created, [enum|relation]
 * @property {boolean} is_Translations - whether the import is for only translating existing term's label and description
 *
 * @function doPrepare - Prepare data for creating new vocabularies/terms
 * @function doPrepareTranslation - Prepare data for creating new label and description translations
 * @function doPost - Sends the prepared data server side and either; creates the new vocabularies/terms or adds the new translation values
 */

class HImportTerms extends HImportBase{

    _vcg_ID = 0;
    _trm_ParentTermID = 0;
    _trm_Domain = 'enum';

    _is_Translations = false;

    /**
     * @param {integer} trm_ParentTermID - For importing new terms, the parent term for the to-be created terms
     * @param {integer} vcg_ID - For importing vocabularies, the vocabulary group for the to-be created vocabularies
     * @param {boolean} is_Translations - Whether this is to import term label and description translations
     */
    constructor(trm_ParentTermID, vcg_ID, is_Translations){

        let field_selectors = ['#field_term', '#field_code', '#field_desc', '#field_uri', '#field_ref_term', '#field_ref_id', '#field_trn_term', '#field_trn_desc'];
        super(0, 'trm', field_selectors, false);

        let vcg_Details = vcg_ID > 0 ? $Db.vcg(vcg_ID) : null;
        let parent_Details = trm_ParentTermID > 0 ? $Db.trm(trm_ParentTermID) : null;

        this._vcg_ID = vcg_ID;
        this._trm_ParentTermID = trm_ParentTermID;
        this._is_Translations = is_Translations;

        if(!vcg_Details && !parent_Details){

            let msg = 'Neither vocabulary group nor vocabulary defined';
            msg = vcg_ID > 0 ? `Vocabulary group #${vcg_ID} not found` : msg;
            msg = trm_ParentTermID > 0 ? `Vocabulary #${vcg_ID} not found` : msg;

            $('body').empty();
            $('body').html(`<h2>${msg}</h2>`);

            return;
        }
        if(vcg_ID > 0){
            this._trm_Domain = vcg_Details['vcg_Domain'];
        }else if(trm_ParentTermID > 0){
            const trm_VocabularyID = $Db.getTermVocab(trm_ParentTermID);
            this._trm_Domain = $Db.trm(trm_VocabularyID, 'trm_Domain');
        }

        // Override click event for import button, need to check the separator setting
        $('#btnImportData').off('click');
        $('#btnImportData').on('click', () => {

            let trm_sep = $('#term_separator').val();
            if(!window.hWin.HEURIST4.util.isempty(trm_sep)){

                let $dlg = null;
                let btns = {};
                btns['Proceed'] = () => { $dlg.dialog('close'); this.doPost(); };
                btns['Clear character'] = () => { $dlg.dialog('close'); $('#term_separator').val(''); this.doPost(); };

                $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                    `If this character [${trm_sep}] appears in your terms they will be split into<br>two or more separate terms nested as hierarchy.<br><br>`
                    + 'This can generate a complete mess if used unintentionally.', 
                    btns, 
                    {title: 'Terms with sub-terms', yes: 'Proceed', no: 'Clear character'}, {default_palette_class: 'ui-heurist-design'}
                );
            }else{
                this.doPost();
            }
        });

        if(is_Translations){ // Set display for translation importing
            $('.trm_translation').show();
            $('.trm_import').hide();
        }
    }

    /**
     * Prepare CSV data for creating new terms/vocabularies
     */
    doPrepare(){

        this.prepared_data = [];

        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(this.parsed_data)){
            this.updatePreparedInfo('<i>No data. Upload and parse</i>', 0);
            return;
        }

        if(this._is_Translations){
            this.doPrepareTranslation();
            return;
        }

        const field_term = $('#field_term').val();

        const allow_prepare = this.checkRequiredMapping({
            'Term (label)': [field_term]
        });
        if(allow_prepare !== true){
            this.updatePreparedInfo(`<span style="color:red">${allow_prepare} must be defined</span>`, 0);
            return;
        }

        const field_code = $('#field_code').val();
        const field_desc = $('#field_desc').val();
        const field_uri = $('#field_uri').val();

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
                'term label': [field_term]
            });
            if(is_valid !== true){
                msg += `Row #${count} is missing: ${is_valid}<br>`;
                $('.tbmain').find(`tr:nth-child(${count})`).addClass('data_error');
                continue;
            }

            let record = {};
            record['trm_Domain'] = this._trm_Domain;

            record['trm_ParentTermID'] = this._trm_ParentTermID;
            record['trm_VocabularyGroupID'] = this._vcg_ID;

            this.createRecord(row, {
                trm_Label: field_term,
                trm_Description: field_desc,
                trm_Code: field_code,
                trm_SemanticReferenceURL: field_uri
            }, record);

        }//for

        const entity = this._vcg_ID > 0 ? 'vocabulary' : 'terms';
        msg = this.prepared_data.length == 0 
                ? `<span style="color:red">No valid ${entity} to import</span>` : msg;
        this.updatePreparedInfo(msg, this.prepared_data.length);
    }

    /**
     * Prepare CSV data for creating new label and description translations
     */
    doPrepareTranslation(){

        const field_ref_term = $('#field_ref_term').val();

        const allow_prepare = this.checkRequiredMapping({
            'Reference Term (label)': [field_ref_term]
        });
        if(allow_prepare !== true){
            this.updatePreparedInfo(`<span style="color:red">${allow_prepare} must be defined</span>`, 0);
            return;
        }

        const field_trn_term = $('#field_trn_term').val();
        const field_trn_desc = $('#field_trn_desc').val();

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
                'term label': [field_ref_term],
                'a translation': [field_trn_term, field_trn_desc]
            });
            if(is_valid !== true){
                msg += `Row #${count} is missing: ${is_valid}<br>`;
                $('.tbmain').find(`tr:nth-child(${count})`).addClass('data_error');
                continue;
            }

            this.createRecord(row, {
                ref_id: field_ref_term,
                trm_Label: field_trn_term,
                trm_Description: field_trn_desc
            });
        }//for

        msg = this.prepared_data.length == 0 ? `<span style="color:red">No valid translations to import</span>` : msg;
        this.updatePreparedInfo(msg, this.prepared_data.length);
    }

    /**
     * Sends prepared data server side to create the new terms/vocabularies/translations
     */
    doPost(){

        if(this.prepared_data.length < 1){
            return;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));

        let request = {
            a: 'batch',
            entity: 'defTerms',
            request_id: window.hWin.HEURIST4.util.random()
        };

        if(this._is_Translations){
            request['set_translations'] = JSON.stringify(this.prepared_data);
            request['vcb_ID'] = this._trm_ParentTermID;
        }else{
            request['fields'] = JSON.stringify(this.prepared_data);
            request['term_separator'] = $('#term_separator').val();
        }

        window.hWin.HAPI4.EntityMgr.doRequest(request, (response) => {

            if(response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                this.showError(response);
                return;
            }

            if(this._is_Translations){

                window.hWin.HEURIST4.msg.sendCoverallToBack();
                window.close( {result: response.data} );
            }else{

                // need to refresh local defintions
                let trm_IDs = response.data;
                window.hWin.HAPI4.EntityMgr.refreshEntityData('trm', () => {
                    window.hWin.HEURIST4.msg.sendCoverallToBack();
                    window.close( {result: trm_IDs} );
                });
            }
        });
    }
}