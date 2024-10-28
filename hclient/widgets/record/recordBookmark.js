/**
* recordBookmark.js - remove bookmarks and detach all personal tags for scope of records
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

$.widget( "heurist.recordBookmark", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 300,
        width:  540,
        modal:  true,
        init_scope: 'selected',
        title:  'Unbookmark selected records'
    },
    
    _initControls:function(){
        
        this._$('#div_header')
            .css({'line-height':'21px'})
            .addClass('heurist-helper1')
            .html(window.hWin.HR('recordUnbookmark_hint'));
        
        this.element.parents('.ui-dialog').find('.btnDoAction').attr('label', window.hWin.HR('Remove Bookmarks'));
        
        return this._super();
    },
    
    //
    //
    //
    doAction: function(){

            let scope_val = this.selectRecordScope.val();
            if(scope_val=='') return;
            
            let scope = [], 
            rec_RecTypeID = 0;
            
            if(scope_val == 'selected'){
                scope = this._currentRecordsetSelIds;
            }else { //(scope_val == 'current'
                scope = this._currentRecordset.getIds();
                if(scope_val  >0 ){
                    rec_RecTypeID = scope_val;
                }   
            }
        
            let request = {
                'a'          : 'batch',
                'entity'     : 'usrBookmarks',
                'request_id' : window.hWin.HEURIST4.util.random(),
                'mode'       : 'unbookmark',
                'bkm_RecID'  : scope
                };
                
            if(rec_RecTypeID>0){
                request['rec_RecTypeID'] = rec_RecTypeID;
            }
                
                let that = this;                                                
                
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){

                            that._context_on_close = (response.data.deleted>0);
                            
                            that.closeDialog();
                            
                            window.hWin.HEURIST4.msg.showMsgFlash(
                                window.hWin.HR('Bookmarks removed')+' '+response.data.deleted, 1000);
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
        
    },
  
});

