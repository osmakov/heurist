/*
* editTheme.js - define Heurist color theme
* refer initPageTheme.php for documentaton about heurist color themes
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
/* global HEditing */

let _theme_editing_symbology;

function editTheme(current_value, callback){

    let edit_dialog = null; //assigned on popup_dlg.dialog
    let editFields = null;
    
    let popup_dlg = $('#heurist-dialog-editTheme');

    if(popup_dlg.length>0){
        popup_dlg.empty();
    }else{
        popup_dlg = $('<div id="heurist-dialog-editTheme">')
            .appendTo( $(window.hWin.document).find('body') );
    }

    let editForm = $('<div class="ent_content_full editForm" style="top:0">')
    .appendTo($('<div class="ent_wrapper">').appendTo(popup_dlg));
    
    //edit form configuration
    const url = window.hWin.HAPI4.baseURL+'hclient/widgets/editing/editTheme.json';
            
    $.getJSON(url, function(res){
        
            editFields = res;
                    
            _theme_editing_symbology = new HEditing({container:editForm, 
                onchange:
                function(){
                    if(edit_dialog){
                        let ele = edit_dialog.parent().find('#btnRecSave');
                        if(ele){
                            let isChanged = _theme_editing_symbology.isModified();
                            let mode = isChanged?'visible':'hidden';
                            edit_dialog.parent().find('#btnRecSave').css('visibility', mode);
                        }
                    }
                },
                oninit: function(){
                    _theme_editing_symbology = this;
                    __editTheme_continue();
                }
            });
    });
    


function __editTheme_continue(){
    
    let recdata = current_value ? new HRecordSet({count:1, order:[1], 
        records:{1:current_value}, 
        fields: {'stub':0}}) :null;
        //Object.getOwnPropertyNames(current_value)
    
    _theme_editing_symbology.initEditForm( editFields, recdata, true );

    let edit_buttons = [
        {text:window.hWin.HR('Cancel'), 
            id:'btnRecCancel',
            css:{'float':'right'}, 
            click: function() { 
                edit_dialog.dialog('close'); 
        }},
        {text:window.hWin.HR('Save'),
            id:'btnRecSave',
            css:{'visibility':'hidden', 'float':'right'},  
            click: function() { 
                let res = _theme_editing_symbology.getValues(); //all values
                //remove empty values
                let propNames = Object.getOwnPropertyNames(res);
                for (let i = 0; i < propNames.length; i++) {
                    let propName = propNames[i];
                    if (window.hWin.HEURIST4.util.isempty(res[propName])) {
                        delete res[propName];
                    }
                }
                if(res['cd_corner']>16 || res['cd_corner']<0){
                    res['cd_corner']=0;
                }
                
                _theme_editing_symbology.setModified(false);
                edit_dialog.dialog('close');
                
                if(window.hWin.HEURIST4.util.isFunction(callback)){
                    callback.call(this, res);
                }

        }}
    ];                

    //
    //
    edit_dialog = popup_dlg.dialog({
        autoOpen: true,
        height: 700,
        width:  740,
        modal:  true,
        title: window.hWin.HR('Define Heurist Theme'),
        resizeStop: function( event, ui ) {//fix bug
           
        },
        beforeClose: function(){
            //show warning in case of modification
            if(_theme_editing_symbology.isModified()){
                
                window.hWin.HEURIST4.msg.showMsgOnExit(window.hWin.HR('Warn_Lost_Data'),
                    ()=>{edit_dialog.parent().find('#btnRecSave').trigger('click');}, //save
                    ()=>{_theme_editing_symbology.setModified(false); edit_dialog.dialog('close'); }); //ignore and close
                return false;   
            }
            return true;
        },

        buttons: edit_buttons
    });                

    edit_dialog.parent().addClass('ui-heurist-design');
                
}
}//end editTheme
