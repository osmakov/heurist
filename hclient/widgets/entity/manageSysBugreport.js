/**
* manageSysBugreport.js - prepare and send bugreport by email
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.6.5
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

//
// there is no search, select mode for bug report - only add and send by email
//

$.widget( "heurist.manageSysBugreport", $.heurist.manageEntity, {
   
    _entityName:'sysBugreport',
    
    //keep to refresh after modifications
    _keepRequest:null,

    _checkDescription: true, // check if bug description is over 20 characters long

    _program_area: null,

    _init: function() {
        
        this.options.title = 'Heurist feedback';
        this.options.edit_mode = 'editonly';
        this.options.select_mode = 'manager';
        this.options.layout_mode = 'editonly';
        this.options.width = 900;
        this.options.height = 932;

        this._super();
    },
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {

        this.options.default_palette_class = 'ui-heurist-admin';

        if(!this._super()){
            return false;
        }

        // always new report
        this.addEditRecord(-1);

        return true;
    },
    
    // change label for remove
    _getEditDialogButtons: function(){
        let btns = this._super();
        
        for(let idx in btns){
            if(btns[idx].class.indexOf('btnRecSave')>=0){
                btns[idx].text = window.hWin.HR('Send to heurist development team');
                break;
            }
        }
        
        return btns;
    },

    _getValidatedValues: function(){

        let that = this;
        let res = this._super();

        if(!res){
            return null;
        }

        // Check for a usable description (a min of 20 words) or the inclusion of steps to reproduce
        let desc = res['bug_Description'];
        if(this._checkDescription && desc.split(' ').length < 20){

            let $dlg;
            let msg = 'In order for bugs to be found and fixed as quickly as possible, the team requires as many details about the issue you are encountering.<br>'
                + 'Providing the steps that has lead you to this issue will also greatly speed up the initial stages of fixing this issue.<br><br>'
                + 'Otherwise, you can click \'Proceed as-is\' if you feel that there are no more details you can provided about this issue.';

            let btns = {};
            btns[window.hWin.HR('Proceed as-is')] = () => {

                that._checkDescription = false;
                $dlg.dialog('close');

                that._saveEditAndClose();
            }
            btns[window.hWin.HR('Close')] = () => {
                $dlg.dialog('close');
            };

            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, {title: 'More information recommended'}, {default_palette_class: 'ui-heurist-admin'});

            return null;
        }

        res['bug_Image'] = [];
        let $img_div = this._editing.getFieldByName('bug_Image');
        $img_div.find('img').each((idx, img) => {
            let matches = img.src.match(/~\d{10}(?:%20%28\d+%29)?\.(?:png|gif|jpg)/);
            if(matches?.length == 2){
                res['bug_Image'].push(matches[1]);
            }
        });

        return res;
    },
    
//---------------------------------------------------------------------------------- 
    _afterSaveEventHandler: function(message){
        window.hWin.HEURIST4.msg.showMsgDlg(message, null, {title: 'Bug report sent'}, {default_palette_class: 'ui-heurist-admin'});
        this.closeDialog(true); //force to avoid warning
    },
    
    _afterInitEditForm: function(){

        this._super();

        //find file uploader and make entire dialogue as a paste zone - to catch Ctrl+V globally
        let ele = this._as_dialog.find('input[type=file]');
        if(ele.length>0){
            ele.fileupload('option', 'pasteZone', this._as_dialog);
        }

        // Add default values to url
        this._editing.setFieldValueByName('bug_URL', location.href, false);

		// Add spacing between fields, and give textarea's larger height
        let eles = this._editing.getAllFields();
        let help = '';
        for(const ele of eles){ // ignore last element (image field)

            let $ele = $(ele);

            if($ele.find('textarea,input.text,.fileupload').length != 0){
                $ele.css({'padding-top': '10px', 'display': 'block'});
            }else if($ele.attr('data-dtid') == 'bug_Type'){
                $ele.find('.header').hide();

            }
            
            if(help === ''){

                let padding = `padding: 0px 15px 20px;`;
                help = 'We value your feedback and do our best to fix bugs rapidly and to incorporate your suggestions into our development process.<br>'
                     + 'Please don\'t hesitate to let us know about anything which annoys you or which you feel could be improved.<br><br>'
                     + 'We pop this form up monthly to encourage your feedback. It is accessible at any time through Help > Feedback / bug report.<br>'
                     + 'You can also paste an image which will be added to the screenshots.';

                // add extra info at top
				$('<div>', {
                    html: help,
                    style: `${padding} display: block;font-size: 12px;`
                }).insertBefore($ele);
            }
        }

        ele = this._editing.getFieldByName('bug_Image');
        let padding = `padding: 10px 15px 20px;`;
        $('<div>', {
            html: 'It is very helpful if you can provide a screen capture for annoyances and bug reports,<br>'
                + 'or an annotated screen capture or drawing for feature requests.',
            style: `${padding} display: block;font-size: 12px;`
        }).insertBefore($(ele));

        this._formatBugTypeField();
        this._formatBugImageField();

        this._setupProgramArea();
    },

    _setupProgramArea: function(){

        let ele = this._editing.getFieldByName('bug_Location');
        let $input = ele.find('input');

        if(this._program_area === null){

            let request = {
                terms: '6988',
                mode: 2,
                remote: `${window.hWin.HAPI4.sysinfo.referenceServerURL}?db=Heurist_Job_Tracker`
            };

            window.hWin.HAPI4.SystemMgr.get_defs(request, (response) => {

                if(response.status != window.hWin.ResponseStatus.OK){

                    window.hWin.HEURIST4.msg.showMsgErr(response);

                    this._program_area = false;

                    $input.val('7105');
                    ele.hide();

                    return;
                }

                this._bugDBTerms = response.data.terms;
                this._program_area = [{key: '', title: 'Please select...'}];

                this._processProgramArea(6988);
                this._setupProgramArea();
            });

            return;
        }

        let $select = $('<select>').insertAfter($input);

        window.hWin.HEURIST4.ui.createSelector($select[0], this._program_area);
        window.hWin.HEURIST4.ui.initHSelect($select, false, null, {
            onSelectMenu: (e) => {
                $input.val($select.val()).trigger('change');
            }
        });

        $input.hide();
    },

    _processProgramArea: function(parent_term_id, depth = 0){

        let that = this;
        let trm_Label_idx = this._bugDBTerms.fieldNamesToIndex.trm_Label;
        let trm_Order_idx = this._bugDBTerms.fieldNamesToIndex.trm_OrderInBranch;

        function sortProgramArea(a, b){

            let a_name = that._bugDBTerms.termsByDomainLookup.enum[a][trm_Label_idx].toLocaleUpperCase();
            let b_name = that._bugDBTerms.termsByDomainLookup.enum[b][trm_Label_idx].toLocaleUpperCase();
            let a_order = parseInt(that._bugDBTerms.termsByDomainLookup.enum[a][trm_Order_idx], 10);
            let b_order = parseInt(that._bugDBTerms.termsByDomainLookup.enum[b][trm_Order_idx], 10);
    
            a_order = (!a_order || a_order < 1 || isNaN(a_order)) ? null : a_order;
            b_order = (!b_order || b_order < 1 || isNaN(b_order)) ? null : b_order;
    
            if(a_order == null && b_order == null){ // alphabetic
                return a_name.localeCompare(b_name);
            }else if(a_order == null || b_order == null){ // null is first
                return a_order == null;
            }else{ // branch order
                return (a_order - b_order);
            }
        }

        let terms = this._bugDBTerms.trm_Links[parent_term_id];

        terms.sort(sortProgramArea);

        for(let trm_ID of terms){

            let term = this._bugDBTerms.termsByDomainLookup.enum[trm_ID];

            this._program_area.push({key: trm_ID, title: term[trm_Label_idx], depth: depth});

            if(window.hWin.HEURIST4.util.isArrayNotEmpty(this._bugDBTerms.trm_Links[trm_ID])){
                this._processProgramArea(trm_ID, depth + 1);
            }
        }
    },

    _formatBugTypeField: function(){

        // Format widths
        let ele = this._editing.getFieldByName('bug_Type');
        $.each(ele.find('label.enum_input'), (idx, label) => {

            label = $(label);
            let width = idx % 3 === 0 ? '17' : '25';
            width = idx % 3 === 2 ? '23' : width;

            label.css({
                width: `${width}em`,
                'min-width': `${width}em`,
                'max-width': '',
                'margin-right': ''
            });
        });

        // Bold values
        ele.find('.input-div').css('font-weight', 'bold');

        // Hide field name
        ele.find('.header').hide();
    },

    _formatBugImageField: function(){

        let ele = this._editing.getFieldByName('bug_Image');

        if(!ele){
            return;
        }

        ele.find('.input-div').css({
            display: 'inline-block',
            'padding-right': '30px'
        });

        ele.find('.btn_input_move').remove();
    },

    onEditFormChange: function(changed_element){

        this._super(changed_element);

        if(changed_element?.enum_buttons !== null){
            this._formatBugTypeField();
        }else if(changed_element?.detailType === 'file'){
            this._formatBugImageField();
        }
    },

    onEditFormNewInput: function(added_element){

        if(added_element?.detailType === 'file'){
            this._formatBugImageField();
        }
    }
    
});
