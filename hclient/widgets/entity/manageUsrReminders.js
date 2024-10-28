/**
* manageUsrReminders.js - main widget to manage users reminders
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
// there is no search, select mode for reminders - only edit
//
$.widget( "heurist.manageUsrReminders", $.heurist.manageEntity, {
   
    _entityName:'usrReminders',
    
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
            this.options.width = 790;
            if(!(this.options.height>0)) this.options.height = 600;
            this.options.beforeClose = function(){}; //to supress default warning
        }else{
           this.options.edit_mode = 'popup'; 
           this.options.list_header = true; //show header for resultList
        }

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
            //load reminder for given record id
            if(this.options.rem_RecID>0){
                    let request = {};
                    request['rem_RecID']  = this.options.rem_RecID;
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
                                    //nothing found - add new reminder
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
            this.searchForm.searchUsrReminders(this.options);
            
            
            let iheight = 6;
            this.searchForm.css({'height':iheight+'em',padding:'10px'});
            this.recordList.css({'top':iheight+0.5+'em'});
            
            this.recordList.resultList('option','show_toolbar',false);
            this.recordList.resultList('option','view_mode','list');

            
            this.recordList.find('.div-result-list-content').css({'display':'table','width':'99%'});
            
            this._on( this.searchForm, {
                "searchusrremindersonresult": this.updateRecordList
            });
            
        }

        return true;
    },
    
//----------------------------------------------------------------------------------    
    _getValidatedValues: function(){
        
        let fields = this._super();
        
        if(fields!=null){
            //validate that at least on recipient is defined
            if(!(fields['rem_ToWorkgroupID'] || fields['rem_ToUserID'] || fields['rem_ToEmail'])){
                  window.hWin.HEURIST4.msg.showMsgFlash('You have to fill one of recipients field');
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
        if(this.options.edit_mode=='editonly' && this.options.rem_RecID>0){
            let ele2 = this._editing.getFieldByName('rem_RecID');
            ele2.editing_input('setValue', this.options.rem_RecID );
        }
        
        let ele = this._editing.getFieldByName('rem_IsPeriodic');
        let res = ele.editing_input('getValues'); 
        if(res[0]=='now'){
            
            this._sendReminder();
        
        }else{    
            this._super();
        }
    },
    
    //
    //
    //
    _sendReminder: function(){

        let fields = this._getValidatedValues(); 
        if(fields==null) return; //validation failed
        
        let request = {                                                                                        
            'a'          : 'action',
            'entity'     : this.options.entity.entityName,
            'request_id' : window.hWin.HEURIST4.util.random(),
            'fields'     : fields                     
            };
            
            let that = this;                                                
            let dlged = this._getEditDialog();
            if(dlged) window.hWin.HEURIST4.msg.bringCoverallToFront(dlged);

            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    window.hWin.HEURIST4.msg.sendCoverallToBack();
                    if(response.status == window.hWin.ResponseStatus.OK){
                        window.hWin.HEURIST4.msg.showMsgFlash(that.options.entity.entityTitle+' '+window.hWin.HR('has been sent'));
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
        
    },    
    
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
                'Are you sure you wish to delete this reminder?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    },
    
    _afterInitEditForm: function(){

        this._super();
    
        let that = this;
        let ele = this._editing.getFieldByName('rem_IsPeriodic');
        
        if(this.options.edit_mode=='editonly'){
        
            //reminder
            let val = this._getField('rem_StartDate');
            
            let isManual = window.hWin.HEURIST4.util.isempty(val) || val=='0000-00-00';
            
            function __onChangeType(){ 
                let ele1 = that._editing.getFieldByName('rem_Freq');
                let ele2 = that._editing.getFieldByName('rem_StartDate');
                
                let btn_save;
                if(that._toolbar){
                    btn_save = that._toolbar.find('.btnRecSave');
                }
                
                let res = ele.editing_input('getValues'); 
                if(res[0]=='now'){
                        ele2.editing_input('setValue', '');
                        ele1.hide();
                        ele2.hide();
                        
                        if(btn_save) btn_save.button('option','label','Send');
                }else{
                        ele1.show();
                        ele2.show();
                        
                        if(btn_save) btn_save.button('option','label','Save');
                }
            }
            
            ele.editing_input('option', 'change', __onChangeType);
            ele.editing_input('setValue', isManual?'now':'later');
            __onChangeType();
        
        }else{
            ele.editing_input('option','readonly',true);
            ele.editing_input('setValue', 'later');
            ele.hide();
        }
        
        let ele1 = this._editing.getFieldByName('rem_ToWorkgroupID');
        let ele2 = this._editing.getFieldByName('rem_ToUserID');
        let ele3 = this._editing.getFieldByName('rem_ToEmail');
        
        
        function __onChange2( ){
           let res = $(this.element).editing_input('getValues')
           if(res[0]!=''){
               let dtID = $(this.element).editing_input('option','dtID');
               if(dtID!='rem_ToWorkgroupID') ele1.editing_input('setValue', '');
               if(dtID!='rem_ToUserID') ele2.editing_input('setValue', '');
               if(dtID!='rem_ToEmail') ele3.editing_input('setValue', '');
           }
        }
    
        ele1.editing_input('option', 'change', __onChange2);
        ele2.editing_input('option', 'change', __onChange2);
        ele3.editing_input('option', 'change', __onChange2);

    
    },

    //
    // header for resultList
    //     
    _recordListHeaderRenderer:function(){
        
        function __cell(colname, width){
          //return '<div style="display:table-cell;width:'+width+'ex">'+colname+'</div>';            
          return '<div style="width:'+width+'ex">'+colname+'</div>';            
        }
        
        //return '<div style="display:table;height:2em;width:99%;font-size:0.9em">'
        return __cell('Record title',35)+__cell('Recipient',17)+__cell('Freq',7)
                    +__cell('Date',12)+__cell('Message',50);
                    
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
            return '<div class="truncate" style="display:inline-block;'+swidth+'">'
                    +fld(fldname)+'</div>';
        }
        
        //rem_ID,rem_RecID,rem_OwnerUGrpID,rem_ToWorkgroupID,rem_ToUserID,rem_ToEmail,rem_Message,rem_StartDate,rem_Freq,rem_RecTitle
        //rem_ToWorkgroupName
        //rem_ToUserName        
        
        
        let recID   = fld('rem_ID');
        let recipient = fld('rem_ToWorkgroupName');
        if(!recipient) recipient = fld('rem_ToUserName');
        if(!recipient) recipient = fld('rem_ToEmail');
        recipient = '<div class="truncate" style="display:inline-block;width:17ex">'+recipient+'</div>';
        
        let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'
                + fld2('rem_RecTitle','35ex') + ' ' + recipient 
                + fld2('rem_Freq','7ex')+fld2('rem_StartDate','14ex')
                + fld2('rem_Message','50ex'); //position:absolute;left:500px;bottom:6px
        
        // add edit/remove action buttons
        if(this.options.select_mode=='manager' && this.options.edit_mode=='popup'){
            html = html 
                + '<div class="logged-in-only" style="width:60px;display:inline-block">'
                + '<div title="Click to edit reminder" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit"  style="height:16px">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                + '</div>'
                +'<div title="Click to delete reminder" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete"  style="height:16px">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                + '</div></div>';
        }
        //<div style="float:right"></div>' + '<div style="float:right"></div>
        
        html = html + '</div>';

        return html;
        
    }    
    
});
