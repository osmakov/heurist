/**
* manageDefCalcFunctions.js - main widget to manage field calculations
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
// there is no search, select mode for Calculations - only edit
//
$.widget( "heurist.manageDefCalcFunctions", $.heurist.manageEntity, {
   
    _entityName:'defCalcFunctions',
    
    //keep to refresh after modifications
    _keepRequest:null,
    
    _init: function() {

        if(!this.options.default_palette_class){
            this.options.default_palette_class = 'ui-heurist-admin';    
        }

        this.options.use_cache = false;

        if(this.options.edit_mode=='editonly'){
            this.options.edit_mode = 'editonly';
            this.options.select_mode = 'manager';
            this.options.layout_mode = 'editonly';
            this.options.width = 1000;
            if(!(this.options.height>0)) this.options.height = 600;
            this.options.beforeClose = function(){}; //to supress default warning

        }else{
            this.options.edit_mode = 'popup'; 
            this.options.list_header = true; //show header for resultList
            if(this.options.select_mode == 'select_single'){
                this.options.width = 790;
                this.options.height = 600;
            }
            this.options.title = "Select formula for calculated field";
        }
        
        this.options.edit_height = 640;
        this.options.edit_width = 1200;
        

        this._super();
    },
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }
      
        if(this.options.edit_mode=='editonly'){
            //load calculation record for given record id
            if(this.options.cfn_ID>0){
                    let request = {};
                    request['cfn_ID']  = this.options.cfn_ID;
                    request['a']          = 'search'; //action
                    request['entity']     = this.options.entity.entityName;
                    request['details']    = 'full';
                    request['request_id'] = window.hWin.HEURIST4.util.random();
                    
                    let that = this;                                                
                    
                    window.hWin.HAPI4.EntityMgr.doRequest(request, 
                        function(response){
                            if(response.status == window.hWin.ResponseStatus.OK){
                                let recset = new HRecordSet(response.data);
                                if(recset.length()>0){
                                    that.updateRecordList(null, {recordset:recset});
                                    that.addEditRecord( recset.getOrder()[0] );
                                }
                                else {
                                    //nothing found - add new 
                                    that.addEditRecord(-1);
                                }                            
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                                that.closeEditDialog();
                            }
                        });        
                        
            }else{
                this.addEditRecord(-1);
            }
        }else{
            this.searchForm.searchDefCalcFunctions(this.options);

            let iheight = 6;
            this.searchForm.css({'height':iheight+'em',padding:'10px'});
            this.recordList.css({'top':iheight+0.5+'em'});
            
            this.recordList.resultList('option','rendererHeader','');
            this.recordList.resultList('option','show_toolbar',false);
            this.recordList.resultList('option','view_mode','list');
           

            
            this.recordList.find('.div-result-list-content').css({'display':'table','width':'99%'});
            
            this._on( this.searchForm, {
                "searchdefcalcfunctionsonresult": this.updateRecordList,
                "searchdefcalcfunctionsonadd": function() { this.addEditRecord(-1); }
            });
            
        }

        return true;
    },
    
//----------------------------------------------------------------------------------    
    _getValidatedValues: function(){
        
        let fields = this._super();
        
        if(fields!=null){
            //validate that code is defined
            if(!fields['cfn_FunctionSpecification']){
                  window.hWin.HEURIST4.msg.showMsgFlash('You have to define formula code');
                  return null;
            }
        }
        
        return fields;
    },

    //
    //
    //
    _saveEditAndClose: function( fields, afteraction ){

        //assign record id    
        if(this.options.edit_mode=='editonly' && this.options.cfn_ID>0){
            let ele2 = this._editing.getFieldByName('cfn_ID');
            ele2.editing_input('setValue', this.options.cfn_ID );
        }
  
        this._super();
    },
    
    //
    //
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){
        this._super( recID, fieldvalues );
        
        if(this.options.edit_mode=='editonly'){
            this.closeDialog(true);
        }else{
            this.getRecordSet().setRecord(recID, fieldvalues);    
            this.recordList.resultList('refreshPage');  
        }
    },

    _deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this._super(); 
        }else{
            let that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this field calculation?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    },
    
    _afterInitEditForm: function(){

        this._super();

        this.formulaeditor = $( "<div>" )
                    .addClass('ent_wrapper')
                    .css({'top': '155px'})
                    .appendTo( this.editForm );
                    
        let that = this;

        let cfn_Content = this._editing.getValue('cfn_FunctionSpecification')[0];

        let popup_dialog_options = {path: 'widgets/report/', 
                    //default_palette_class: 'ui-heurist-design',
                    keep_instance:false, 
                    
                    is_snippet_editor: true, 
                    //rty_ID:rectypes, 
                    rec_ID:0,
                    template_body:cfn_Content,
                    
                    isdialog: false,
                    container: this.formulaeditor,
                    
                    onChange: function(context){
                        if(!context) return;
                        
                        that._editing.setFieldValueByName2('cfn_FunctionSpecification', context);

                    }
        };
        window.hWin.HEURIST4.ui.showRecordActionDialog('reportEditor', popup_dialog_options);

    },

    //
    // header for resultList
    //     
    _recordListHeaderRenderer:function(){
        return '<span style="height:10px;background:none;"></span>'; // add space above result list
        /*
        function __cell(colname, width){
          //return '<div style="display:table-cell;width:'+width+'ex">'+colname+'</div>';            
          return '<div style="width:'+width+'ex">'+colname+'</div>';            
        }
        
        //return '<div style="display:table;height:2em;width:99%;font-size:0.9em">'
        return __cell('Calculation title',120);
        */            
    },
    
    //----------------------
    //
    //  overwrite standard render for resultList
    //
    _recordListItemRenderer:function(recordset, record){
        
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, col_width){
            let swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = 'width:'+col_width;
            }
            return '<div class="truncate" style="display:inline-block;'+swidth+'">'+fld(fldname)+'</div>';
        }
        
        let recID   = fld('cfn_ID');
        
        let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'
                + fld2('cfn_Name','50ex');
        
        // add edit/remove action buttons
        html = html 
                + '<div class="logged-in-only" style="width:90px;display: inline-block">'
                + '<div title="Click to edit calculation" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit"  style="height:16px">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                + '</div>'              
/*
                + '<div title="Click to edit calculation formula" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit-formula"  style="height:16px">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-calculator-b"></span><span class="ui-button-text"></span>'
                + '</div>'
*/                
                +'<div title="Click to delete calculation" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete"  style="height:16px;padding-left:20px">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                + '</div></div>';
        
        html = html 
            + fld2('cfn_FunctionSpecification','50%')
            + '</div>';

        return html;
        
    },
    
    //
    // extend for edit formula
    //
    _onActionListener: function(event, action){

        if(action && action.action=='edit-formula'){

            window.hWin.HEURIST4.dbs.editCalculatedField(action.recID)
     
        }else{
            this._super( event, action );
        }

    },
    
});
