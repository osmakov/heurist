/**
* manageDefDetailTypeGroups.js - main widget mo manage defDetailTypeGroups
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


$.widget( "heurist.manageDefDetailTypeGroups", $.heurist.manageEntity, {
    
    _entityName:'defDetailTypeGroups',
    
    _init: function() {

        if(!this.options.layout_mode) this.options.layout_mode = 'short';
        this.options.use_cache = true;
        
        if(this.options.select_mode!='manager'){
            this.options.edit_mode = 'none';
            this.options.width = 300;
        }else if(this.options.edit_mode == 'inline') {
            this.options.width = 890;
        }
        
        this._super();
        
        if(this.options.select_mode!='manager'){
            //hide form 
            this.editForm.parent().hide();
            this.recordList.parent().css('width','100%');
        }

        if(!this.options.innerTitle){
            this.recordList.css('top',0);  
        }        
    },
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {

        if(!this._super()){
            return false;
        }

            
        var that = this;
        
        this.recordList.resultList('option', 'show_toolbar', false);
        if(this.options.edit_mode == 'inline'){
            this.recordList.resultList('option', 'sortable', true);
            this.recordList.resultList('option', 'onSortStop', function(){
                that._toolbar.find('#btnApplyOrder').show();
            });
        }

        window.hWin.HAPI4.EntityMgr.getEntityData(this._entityName, false,
            function(response){
                that.updateRecordList(null, {recordset:response});
            });
            
        if(this.options.innerTitle){
            //specify add new/save order buttons above record list
            var btn_array = [
                {showText:true, icons:{primary:'ui-icon-plus'},text:window.hWin.HR('Add Group'),
                      css:{'margin-right':'0.5em','float':'right'}, id:'btnAddButton',
                      click: function() { that._onActionListener(null, 'add'); }},

                {text:window.hWin.HR('Save Order'),
                      css:{'margin-right':'0.5em','float':'right',display:'none'}, id:'btnApplyOrder',
                      click: function() { that._onActionListener(null, 'save-order'); }}];

            
            this._toolbar = this.searchForm;
            this.searchForm.css({'padding-top': '8px'}).empty();
            btn_array[0].css['float'] = 'right'; btn_array[0].css['display'] = 'block';
            btn_array[1].css['float'] = 'right';
            this._defineActionButton2(btn_array[0], this.searchForm);
            this._defineActionButton2(btn_array[1], this.searchForm);
            
        }
        
        return true;
    },    
    
    //----------------------
    //
    // customized item renderer for search result list
    //
    _recordListItemRenderer: function(recordset, record){
        
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, col_width){
            swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = ' style="display:table-cell;width:'+col_width+';max-width:'+col_width+'"';
            }
            return '<div class="item" '+swidth+'>'+window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname))+'</div>';
        }
        
        var recID   = fld('dtg_ID');
        var recTitle = fld2('dtg_ID','4em')+fld2('dtg_Name');
        
        var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" style="height:1.3em">';
        if(this.options.select_mode=='select_multi'){
            html = html + '<div class="recordSelector"><input type="checkbox" /></div>';//<div class="recordTitle">';
        }else{
            //html = html + '<div>';
        }
        
        html = html + fld2('dtg_Name',250);
        
        if(this.options.edit_mode=='popup'){
            html = html
            + this._defineActionButton({key:'edit',label:'Edit', title:'', icon:'ui-icon-pencil', class:'rec_actions_button'},
                    null,'icon_text');
            //+ this._defineActionButton({key:'delete',label:'Remove', title:'', icon:'ui-icon-minus'}, null,'icon_text');
             /*
            + '<div title="Click to edit group" class="rec_edit_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit">'
            +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
            + '</div>&nbsp;&nbsp;'
            
             
            + '<div title="Click to delete group" class="rec_view_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete">'
            +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
            + '</div>';
            */
        }

        html = html 
                +((fld('dtg_FieldCount')>0)
                ?'<div style="display:table-cell;padding:0 4px">'+fld('dtg_FieldCount')+'</div>'
                :this._defineActionButton({key:'delete',label:'Remove', title:'', icon:'ui-icon-delete', class:'rec_actions_button'}, 
                            null,'icon_text'))
                + '<div class="selection_pointer" style="display:table-cell">'
                    +'<span class="ui-icon ui-icon-carat-r"></span></div>';
        

        return html+'</div>';
        
    },

    //
    // update list after save (refresh)
    //
    /*
    _afterSaveEventHandler: function( recID, fieldvalues ){
        
        window.hWin.HEURIST4.dbs.dtgRefresh( recID, fieldvalues );
        this._super( recID, fieldvalues );
    },
    _afterDeleteEvenHandler: function( recID ){
        if(window.hWin.HEURIST4.detailtypes.groups[recID]){
            delete window.hWin.HEURIST4.detailtypes.groups[recID];
        }
        window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 'detailtypes'); 
        this._super( recID );
    },
    */
    
    //
    // can remove group with assigned fields
    //     
    _deleteAndClose: function(){    
            if(this._getField('dtg_FieldCount')>0){
                window.hWin.HEURIST4.msg.showMsgFlash('Can\'t remove non empty group');  
                return;                
            }
            
            this._super();
    },
    
    //
    // extend for save order
    //
    _onActionListener: function(event, action){

        this._super(event, action);

        if(action=='save-order'){


            var recordset = this.getRecordSet();
            //assign new value for dtg_Order and save on server side
            var rec_order = recordset.getOrder();
            var idx = 0, len = rec_order.length;
            var fields = [];
            for(; (idx<len); idx++) {
                var record = recordset.getById(rec_order[idx]);
                var oldval = recordset.fld(record, 'dtg_Order');
                var newval = String(idx+1).lpad(0,3);
                if(oldval!=newval){
                    recordset.setFld(record, 'dtg_Order', newval);        
                    fields.push({"dtg_ID":rec_order[idx], "dtg_Order":newval});
                }
            }
            if(fields.length>0){

                var request = {
                    'a'          : 'save',
                    'entity'     : this._entityName,
                    'request_id' : window.hWin.HEURIST4.util.random(),
                    'fields'     : fields                     
                };

                var that = this;                                                
                //that.loadanimation(true);
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            //that._afterSaveEventHandler( recID, fields );
                            that._toolbar.find('#btnApplyOrder').hide();
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                });

            }else{
                this._toolbar.find('#btnApplyOrder').hide();    
            }

        }

    },
    
    //
    // extend dialog button bar
    //    
    _initEditForm_step3: function(recID){
        
        if(this._toolbar){
            this._toolbar.find('.ui-dialog-buttonset').css({'width':'100%','text-align':'right'});
            this._toolbar.find('#btnRecDelete').css('display', 
                    (recID>0 && this._getField('dtg_FieldCount')==0) ?'block':'none');
        }
        
        this._super(recID);
    },
    
    onEditFormChange: function( changed_element ){
        this._super(changed_element);
            
        if(this._toolbar){
            var isChanged = this._editing.isModified();
            this._toolbar.find('#btnRecDelete').css('display', 
                    (isChanged || this._getField('dtg_FieldCount')>0)?'none':'block');
        }
            
    },  
/*      
    _getEditDialogButtons: function(){
                                    
            var that = this;        
            
            var btns = [ 
                {showText:true, icons:{primary:'ui-icon-plus'},text:window.hWin.HR('Add Group'),
                      css:{'margin-right':'0.5em','float':'left',display:'none'}, id:'btnAddButton',
                      click: function() { that._onActionListener(null, 'add'); }},

                {text:window.hWin.HR('Save Order'),
                      css:{'margin-right':'0.5em','float':'left',display:'none'}, id:'btnApplyOrder',
                      click: function() { that._onActionListener(null, 'save-order'); }},
                      
                      
                {text:window.hWin.HR('Close'), 
                      css:{'margin-left':'3em','float':'right'},
                      click: function() { 
                          that.closeDialog(); 
                      }},
                {text:window.hWin.HR('Drop Changes'), id:'btnRecCancel', 
                      css:{'margin-left':'0.5em','float':'right'},
                      click: function() { that._initEditForm_step3(that._currentEditID) }},  //reload edit form
                {text:window.hWin.HR('Save'), id:'btnRecSave',
                      accesskey:"S",
                      css:{'font-weight':'bold','float':'right'},
                      click: function() { that._saveEditAndClose( null, 'none' ); }},
                      
                {text:window.hWin.HR('Delete'), id:'btnRecDelete',
                      css:{'float':'right',display:'none'},
                      click: function() { that._onActionListener(null, 'delete'); }},
                      
                      ];
        
            return btns;
    },
*/    
});
