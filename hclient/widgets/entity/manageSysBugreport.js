/**
* manageSysBugreport.js - prepare and send bugreport by email
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
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

//
// there is no search, select mode for bug report - only add and send by email
//
$.widget( "heurist.manageSysBugreport", $.heurist.manageEntity, {
   
    _entityName:'sysBugreport',
    
    //keep to refresh after modifications
    _keepRequest:null,
    
    _init: function() {
        
        this.options.title = 'Heurist feedback';
        this.options.edit_mode = 'editonly';
        this.options.select_mode = 'manager';
        this.options.layout_mode = 'editonly';
        this.options.width = 750;
        this.options.height = 912;

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
            ele.fileupload('option','pasteZone',this._as_dialog);
        }

        // Add default values to report type and url
        this._editing.getFieldByName('2-2', 'Suggestion / feature request', false);
        this._editing.getFieldByName('3-1058', location.href, false);

		// Add spacing between fields, and give textarea's larger height
        let eles = this._editing.getAllFields();
        let help = '';
        for(let i = 0; i < eles.length; i++){ // ignore last element (image field)

            let $ele = $(eles[i]);

            if($ele.find('textarea').length != 0 || $ele.find('.fileupload').length != 0){
                $ele.css({'padding-top': '10px', 'display': 'block'});
            }else if($ele.attr('data-dtid') == '2-2'){

                $ele.hide();

                let $input = $ele.find('input');

                let report_types = '<label><input type="checkbox" name="report_types" value="Suggestion / feature request" checked="checked">Suggestion / feature request</label>'
                                 + '<label style="margin-left: 5px"><input type="checkbox" name="report_types" value="Minor annoyance">Minor annoyance</label>'
                                 + '<label style="margin-left: 5px"><input type="checkbox" name="report_types" value="Major annoyance">Major annoyance</label>'
                                 + '<label style="margin-left: 5px"><input type="checkbox" name="report_types" value="Minor bug">Minor bug</label>'
                                 + '<label style="margin-left: 5px"><input type="checkbox" name="report_types" value="Significant bug">Significant bug</label>'
                                 + '<label style="margin-left: 5px"><input type="checkbox" name="report_types" value="Urgent bug">Urgent bug</label>';

                let $types = $('<div>', {
                    html: report_types,
                    style: 'font-weight: bold;display: block;text-align: center;margin-bottom: 15px;',
                    id: 'report_types_sel'
                }).insertBefore($ele);

                this._on($types, {
                    change: function(event){

                        let val = $(event.target).val();
                        let cur_val = $input.val();
                        let remove = !$(event.target).is(':checked');

                        if(remove && cur_val.indexOf(val) === -1){
                            return;
                        }
                        if(!remove){
                            cur_val = cur_val == '' ? val : `${cur_val}, ${val}`;
                        }else{
                            cur_val = cur_val.split(', ');
                            let idx = cur_val.indexOf(val);
                            cur_val.splice(idx, 1);
                            cur_val = cur_val.join(', ');
                        }

                        $input.val(cur_val).trigger('change');
                    }
                });
            }else{

                let $before = help === '' ? this._as_dialog.find('#report_types_sel') : $ele;
                let padding = `padding: ${help === '' ? '0px' : '10px'} 15px 20px;`;
                if(help === ''){
                    help = 'We value your feedback and do our best to fix bugs rapidly and to incorporate your suggestions into our development process.<br>'
                         + 'Please don\'t hesitate to let us know about anything which annoys you or which you feel could be improved.<br><br>'
                         + 'We pop this form up monthly to encourage your feedback. It is accessible at any time through Help > Feedback / bug report.';
                }else{
                    help = 'It is very helpful if you can provide a screen capture for annoyances and bug reports,<br>'
                         + 'or an annotated screen capture or drawing for feature requests.';
                }
                // add extra info at top
				$('<div>', {
                    html: help,
                    style: `${padding} display: block;font-size: 12px;`
                }).insertBefore($before);
            }
        }
    },    
    
});
