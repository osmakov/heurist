/**
* manageDefGroups.js - abstract base widget for manageDefRecTypeGroups and manageDefDetailTypeGroups
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


$.widget( "heurist.manageDefGroups", $.heurist.manageEntity, {
    
    _entityName: 'to be specified in descendant',
    _entityPrefix: '',
    _title:'',
    
    _init: function() {

        this.options.default_palette_class = 'ui-heurist-design';

        this.options.innerTitle = false;
        
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

        if(this.options.isFrontUI){
            this.recordList.css('top','80px');  
        }else{
            this.recordList.css('top',0);  
        }        
        
        let that = this;

        //refresh list        
        $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            function(e, data) { 
                if(!data || 
                   (data.source != that.uuid && data.type == that._entityPrefix))
                {
                    that._loadData();
                }
            });
        
    },
    
    _destroy: function() {
       $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);
       this._super(); 
    },
    
    _addOnDrop: function(type_ID, group_ID){
        //to be implemented in descendant 
    },
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {

        if(!this._super()){
            return false;
        }
        
        if(this.options.edit_mode=='editonly'){
            this._initEditorOnly( $Db[this._entityPrefix]() );
            return;
        }

        let that = this;
        
        this.recordList.resultList({
            show_toolbar:false,
            sortable: true,
            empty_remark: 'Add new group',
            onSortStop: function(){
                that._onActionListener(null, 'save-order');
               
            },
            droppable: function(){   //change group for record type
                
                that.recordList.find('.recordDiv')  //.recordDiv, ,.recordDiv>.item
                    .droppable({
                        //accept: '.rt_draggable',
                        scope:  that._entityPrefix+'_change',  
                        hoverClass: 'ui-drag-drop',
                        drop: function( event, ui ){

                            let trg = $(event.target).hasClass('recordDiv')
                                        ?$(event.target)
                                        :$(event.target).parents('.recordDiv');
                                        
                            let type_ID = $(ui.draggable).parent().attr('recid');
                            let group_ID = trg.attr('recid');

                            that._addOnDrop(type_ID, group_ID);                            
                    }});
            },
            sortable_opts: {
                axis: 'y'
            }
        });
        

        if(this.options.isFrontUI){
            //specify add new/save order buttons above record list
            let btn_array = [
                {showLabel:true, icon:'ui-icon-plus',label:window.hWin.HR('Add'),
                      css:{'margin':'5px','float':'left',padding:'3px'}, class:'btnAddButton',
                      click: function() { that._onActionListener(null, 'add'); }},

                {label:window.hWin.HR('Save'),
                          css:{'margin-right':'0.5em','float':'left',display:'none'}, class:'btnApplyOrder',
                      click: function() { that._onActionListener(null, 'save-order'); }}
                      ];

            this._toolbar = this.searchForm;
            this.searchForm.css({'padding-top': '8px'}).empty();
            $(`<h4>${that._title}</h4>`).css({'margin':5}).appendTo(this.searchForm);
            this._defineActionButton2(btn_array[0], this.searchForm);
            this._defineActionButton2(btn_array[1], this.searchForm);
           
            
            this.searchForm.height(70);
        }
        
        that._loadData();

        return true;
    },    

    //
    //
    //
    _loadData: function(){
        let that = this;
        window.hWin.HAPI4.EntityMgr.getEntityData(this._entityName, false,
            function(response){
                that.updateRecordList(null, {recordset:response});

                that.selectRecordInRecordset( that.options.selection_on_init );
                that.options.selection_on_init = null;
            });
        // OR    
        // this.updateRecordList(null, {recordset:$Db[this._entityPrefix]()});
        // this.selectRecordInRecordset();
    },

    //----------------------
    //
    // customized item renderer for search result list
    //
    _recordListItemRenderer: function(recordset, record){
        
        let recID   = recordset.fld(record, this._entityPrefix+'_ID');
        let recName = recordset.fld(record, this._entityPrefix+'_Name');
        
        let html = '<div class="recordDiv white-borderless" id="rd'+recID+'" recid="'+recID+'">'; // style="height:1.3em"
        if(this.options.select_mode=='select_multi'){
            html = html + '<div class="recordSelector"><input type="checkbox" /></div>';
        }
        
        if(recName=='Trash'){
            html = html + '<div style="display:table-cell;vertical-align: middle;"><span class="ui-icon ui-icon-trash"></span></div>';
        }
        
        html = html + 
            '<div class="item truncate" style="font-weight:bold;display:table-cell;width:150;max-width:150;padding:6px;">'
            +window.hWin.HEURIST4.util.htmlEscape(recName)+'</div>';

        if(recName!='Trash'){        
            if(this.options.edit_mode=='popup'){
                html = html
                + this._defineActionButton({key:'edit',label:'Edit', title:'', icon:'ui-icon-pencil', class:'rec_actions_button'},
                null,'icon_text','padding-top:9px');
            }

            html = html 
                + this._defineActionButton({key:'delete',label:'Remove', title:'', icon:'ui-icon-delete', class:'rec_actions_button'}, 
                    null,'icon_text');
        }
        
        html = html + '<div class="selection_pointer" style="display:table-cell">'
                    +'<span class="ui-icon ui-icon-carat-r"></span></div>';
        

        return html+'</div>';
        
    },

    //
    // update list after save (refresh)
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){
        
        if(this.options.edit_mode=='editonly'){
            
                this._selection = new HRecordSet();
                this._selection.addRecord(recID, fieldvalues);
                this._currentEditID = null;
                this._selectAndClose();
        }else{
                this._super( recID, fieldvalues );
                if(this.it_was_insert){
                    this._onActionListener(null, 'save-order');
                    this.selectRecordInRecordset(); //select first
                }
                
        }
    
        this._triggerRefresh(this._entityPrefix, recID);    
        
    },
    
    //
    //
    //
    _afterDeleteEvenHandler: function( recID ){
        this._super( recID );
        this._triggerRefresh(this._entityPrefix, recID);   
        //select first
        this.selectRecordInRecordset();
    },
    
    //
    // cant remove group with assigned fields
    //     
    _deleteAndClose: function(unconditionally){
   
        if(unconditionally===true){
            this._super(); 
            
        }else{
            let that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this group?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'},
                {default_palette_class:this.options.default_palette_class});        
        }
    },
    
    //
    // extend for save order
    //
    _onActionListener: function(event, action){

        let isresolved = this._super(event, action);

        if(!isresolved){
            
            if(action=='save-order'){

                let recordset = this.getRecordSet();
                let that = this;
                window.hWin.HEURIST4.dbs.applyOrder(recordset, this._entityPrefix, function(res){
                    that._toolbar.find('.btnApplyOrder').hide();
                    that._triggerRefresh(this._entityPrefix);
                });
                
            }else if(action=='trash'){
                if(window.hWin.HEURIST4.util.isFunction(this.options.onSelect)){
                    let id = $Db.getTrashGroupId(this._entityPrefix);
                    this.options.onSelect.call( this, [id] );
                }
            }
            
        }
    }
  
});
