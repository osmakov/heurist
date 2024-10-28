/**
* manageDefFileExtToMimetype.js - main widget mo manage extension to mimetype 
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


$.widget( "heurist.manageDefFileExtToMimetype", $.heurist.manageEntity, {
    
    
    _entityName:'defFileExtToMimetype',
    
    _init: function() {

        this.options.default_palette_class = 'ui-heurist-admin';

        if(isNaN(this.options.width)) this.options.width = 570;
        if(this.options.width<420) this.options.width = 420;
        this.options.height = 600;

        this._super();
    },
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        if(this.options.select_mode!='manager'){
            this.options.width = (isNaN(this.options.width) || this.options.width<600)?600:this.options.width;                    
        }
        
        this.options.use_cache = true;
       
        
        /*if(this.options.edit_mode=='popup'){ //only inline allowed
            this.options.edit_mode='inline'
        }*/

        if(!this._super()){
            return false;
        }
        
        if(this.searchForm && this.searchForm.length>0){
            this.searchForm.searchDefFileExtToMimetype(this.options);   
            if(this.options.edit_mode=='inline') this.searchForm.height('5.5em').css('border','none');    
        }
        
        if(this.options.edit_mode=='inline') {
            this.recordList.css('top','5.5em');   
            this._toolbar = this._as_dialog.parent();
        }
        
        this.recordList.resultList('option', 'show_toolbar', false);
        this.recordList.resultList('option', 'view_mode', 'list');

        let that = this;
        window.hWin.HAPI4.EntityMgr.getEntityData(this.options.entity.entityName, false,
            function(response){
                that.updateRecordList(null, {recordset:response});
            });
            
        this._on( this.searchForm, {
                "searchdeffileexttomimetypeonfilter": this.filterRecordList
                });
        this._on( this.searchForm, { //not used
                "searchdeffileexttomimetypeonaddrecord": function(){this._onActionListener(null, 'add');}
                });
        
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
        
        let recID   = fld('fxm_Extension');
        let recTitle = '<span style="display:inline-block;width:4em">'+fld('fxm_Extension') + '</span>  ' 
                        + fld('fxm_FiletypeName');
        
        let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'; // style="height:1.3em"
        if(this.options.select_mode=='select_multi'){
            html = html + '<div class="recordSelector"><input type="checkbox" /></div><div class="recordTitle">';
        }else{
            html = html + '<div>';
        }
        
        html = html + recTitle + '</div>';

        /*        
        if(false && this.options.edit_mode=='popup'){ //action button in reclist
            html = html
            + this._defineActionButton({key:'edit',label:'Edit', title:'', icon:'ui-icon-pencil'}, null,'icon_text')
            + this._defineActionButton({key:'delete',label:'Remove', title:'', icon:'ui-icon-minus'}, null,'icon_text');
        }
        */
        

        return html+'</div>';
        
    },

    /*
    updateRecordList: function( event, data ){
        this._super(event, data);
    },
    
    filterRecordList: function(event, request){
        var subset = this._super(event, request);
        this.selectRecordInRecordset(subset);
    },
    */
    
    _initEditForm_step3: function(recID){
        
        if(recID<0){
            this.options.entity.fields[0].dtFields['rst_Display'] = 'visible';
        }else{
            this.options.entity.fields[0].dtFields['rst_Display'] = 'readonly';
        }

        if(this._toolbar){
            this._toolbar.find('.ui-dialog-buttonset').css({'width':'100%','text-align':'right'});
        }
        
        this._super(recID);
    },
    
    _getEditDialogButtons: function(){
                                    
            let that = this;        
            
            let btns = [       /*{text:window.hWin.HR('Reload'), id:'btnRecReload',icon:'ui-icon-refresh',
                click: function() { that._initEditForm_step3(that._currentEditID) }},  //reload edit form*/
                      
                {showLabel:true, icon:'ui-icon-plus',text:window.hWin.HR('Add New File Type'),
                      css:{'margin-right':'0.5em','float':'left'}, class:'btnAddButton',
                      click: function() { that._onActionListener(null, 'add'); }},
                      
                      
                {text:window.hWin.HR('Close'), 
                      css:{'margin-left':'3em','float':'right'},
                      click: function() { 
                          that.closeDialog(); 
                      }},
                {text:window.hWin.HR('Drop Changes'), class:'btnRecCancel', 
                      css:{'margin-left':'0.5em','float':'right'},
                      click: function() { that._initEditForm_step3(that._currentEditID) }},  //reload edit form
                {text:window.hWin.HR('Save'), class:'btnRecSave',
                      accesskey:"S",
                      css:{'font-weight':'bold','float':'right'},
                      click: function() { that._saveEditAndClose( null, 'none' ); }},
                      
                      ];
        
            return btns;
    },
    
    
});
