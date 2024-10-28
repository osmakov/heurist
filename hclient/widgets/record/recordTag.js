/**
* recordTag.js - assign, detach tags
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

$.widget( "heurist.recordTag", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 500,
        width:  700,
        modal:  true,
        init_scope: 'selected',
        title:  'Add or Remove Tags for Records',
        helpContent: 'recordTags',
        scope_types: ['selected', 'collected', 'current'],
        groups: 'all',
        modes: ['assign','remove']       //bookmark=assign bookmark_url - just selection of tags - no real action
    },
    
    _tags_selection:[], //selected tags
    _tagSelectionWidget:null, 

    _initControls:function(){
        
        this.options.helpContent = 'recordTags';
        
        let sMsg;
        if(this.options.modes=='bookmark_url'){
            this._$('#div_fieldset').hide();
            sMsg = window.hWin.HR('recordTag_hint0');
        }else if (this.options.modes=='bookmark') { 
            sMsg = window.hWin.HR('recordTag_hint1');
            this.options.helpContent = 'recordBookmark';
        }else{
            sMsg = window.hWin.HR('recordTag_hint2');;
        }   
        sMsg = sMsg + window.hWin.HR('recordTag_hint3');
        
        this._$('#div_header')
            //.css({'line-height':'21px'})
            .addClass('heurist-helper1')
            .html(sMsg);
        
        this._tagSelectionWidget = $('<div>').css({'width':'100%', padding: '0.2em'}).appendTo( this.element );
        
        let that = this;
        
        window.hWin.HEURIST4.ui.showEntityDialog('usrTags', {
                refreshtags:true, 
                isdialog: false,
                container: this._tagSelectionWidget,
                select_mode: 'select_multi', 
                layout_mode: '<div class="recordList"/>',
                list_mode: 'compact', //special option for tags
                groups: this.options.groups, //all,personal,grouponly,or list of ids
                show_top_n_recent: true, //show top and recent lists
                selection_ids: [], //already selected tags
                select_return_mode:'recordset', //ids by default
                onselect:function(event, data){
                    if(data && data.selection){
                        that._tags_selection = data.selection;
                        that._onRecordScopeChange();
                    }
                }
        });
        
        let res = this._super();
        
        //'width':106,'min-width':96,
        this._$('fieldset > div > .header').css({'padding':'0 16 0 0'});
        
        return res;
    },
    
    _destroy: function() {
        this._super();
        if(this._tagSelectionWidget) this._tagSelectionWidget.remove();
    },
    
    _getActionButtons: function(){
        let res = this._super();
        
        let that = this;
        
        
        if(this.options.modes.indexOf('bookmark_url')>=0){
            res[1].text = window.hWin.HR('Bookmark');
            res[1].click = function() { 
                            that.doTagSelection();                        
                        };
        }else if(this.options.modes=='bookmark'){
            
            res[1].text = window.hWin.HR('Bookmark');
            res[1].click = function() { 
                            that.doAction('assign');
                        };
            
        }else if(this.options.modes.indexOf('remove')>=0){
            res[1].text = window.hWin.HR('Remove tags');
        }else{
            res.pop(); //remove last
        }
            
        
        if(this.options.modes.indexOf('assign')>=0)
            res.push({text:window.hWin.HR('Add tags'),
                    disabled:'disabled',
                    css:{'float':'right'},
                    class: 'ui-button-action btnDoAction2',
                    click: function() { 
                            that.doAction('assign'); 
                    }});
        return res;
    },

    //
    // ony tag selection for bookmark_url (see importHyperlinks)
    //
    doTagSelection: function(){
        this._context_on_close = this._tags_selection;
        this.closeDialog();
    },
    //
    //
    //
    doAction: function(mode){
        
            let scope_val = this.selectRecordScope.val();
            if(scope_val=='') return;
            
            if(mode!='assign') mode = 'remove';

            
            if(window.hWin.HEURIST4.util.isempty(this._tags_selection)){
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: `Need to select tags to ${mode}`,
                    error_title: 'Missing tags',
                    status: window.hWin.ResponseStatus.INVALID_REQUEST
                });
                return;
            }
            
            let scope = [], 
            rec_RecTypeID = 0;
            
            if(scope_val == 'selected'){
                scope = this._currentRecordsetSelIds;
            }else if(scope_val == 'collected'){
                scope = this._currentRecordsetColIds;
            }else{ //(scope_val == 'current'
                scope = this._currentRecordset.getIds();
                if(scope_val  >0 ){
                    rec_RecTypeID = scope_val;
                }   
            }
            
        
            let request = {
                'a'          : 'batch',
                'entity'     : 'usrTags',
                'request_id' : window.hWin.HEURIST4.util.random(),
                'mode'       : mode,
                'tagIDs'  : this._tags_selection,
                'recIDs'  : scope.join(',')
                };
                
            if(rec_RecTypeID>0){
                request['rec_RecTypeID'] = rec_RecTypeID;
            }
                
            let that = this;                                                
            
            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){

                            that._context_on_close = (response.data.res_bookmarks>0);
                            
                            that.closeDialog();
                            
                            let msg = window.hWin.HR('Processed records')+': '+response.data.processed + '<br>';
                             
                             if(response.data.added==0 && response.data.removed==0) {
                                 msg += window.hWin.HR('No tags were affected');
                             }else{
                                 if(response.data.added>0){
                                     msg += window.hWin.HR('Tags added')+': '+response.data.added + '<br>';

                                     if(response.data.bookmarks>0){
                                        msg += window.hWin.HR('Bookmarks added')+': '+response.data.bookmarks;
                                     }
                                     
                                 }else if(response.data.removed>0){
                                     msg += (window.hWin.HR('Tags removed')+': '+response.data.removed);
                                 }
                             } 
                                
                            window.hWin.HEURIST4.msg.showMsgFlash(msg, 2000);
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
        
    },

    _onRecordScopeChange: function () 
    {
        let isdisabled = (this.options.modes.indexOf('bookmark_url')<0 && this.selectRecordScope.val()=='') 
                        || !(this._tags_selection.length>0);
        
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('.btnDoAction2'), isdisabled );
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('.btnDoAction'), isdisabled );

    },

  
}); 

