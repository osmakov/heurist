/**                                  
* manageSysWorkflowRules.js - main widget to Workflow Stages Rules
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
$.widget( "heurist.manageSysWorkflowRules", $.heurist.manageEntity, {
   
    _entityName:'sysWorkflowRules',
    
    is_first: true,

    options: {
        edit_height: 600
    },
    
    //keep to refresh after modifications
    _keepRequest:null,
    
    _init: function() {
        
        if(!this.options.default_palette_class){
            this.options.default_palette_class = 'ui-heurist-design';    
        }
        
        this.options.innerTitle = false;
        this.options.use_cache = true;
        
        if(this.options.edit_mode=='editonly'){
            this.options.edit_mode = 'editonly';
            this.options.select_mode = 'manager';
            this.options.layout_mode = 'editonly';
            this.options.width = 790;
            if(!(this.options.height>0)) this.options.height = 600;
            this.options.beforeClose = function(){}; //to supress default warning
        }else{
            if(this.options.select_mode!='manager'){
                    this.options.edit_mode = 'popup'; 
            }
            this.options.list_header = true; //show header for resultList
        }

        this._super();

        let that = this;

        if(this.options.isFrontUI){
            
            this.searchForm.css({padding:'10px 5px 0 10px'});
            
            //window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, {'background-color':'#fff', opacity:1});   
        
            if(this.options.select_mode=='manager'){ //adjust table widths
                window.hWin.HAPI4.addEventListener(this, window.hWin.HAPI4.Event.ON_WINDOW_RESIZE, 
                    function(){
                        if(that.recordList && that.recordList.resultList('instance')){
                            that.recordList.resultList('applyViewMode','list', true);
                            that.recordList.resultList('refreshPage');
                        }
                    });
            }    
        }        
        
        
        //refresh list        
        $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            function(e, data) { 
                if(!data || 
                   (data.source != that.uuid && data.type == 'swf'))
                {
                    that._loadData();
                }
            });
        
    },
    
    
    _loadData: function( is_first ){
        
            this.updateRecordList(null, {recordset:$Db.swf()});
            if(!is_first && this.searchForm.searchDefRecTypes('instance')){ //is_first!==true && 
                this.searchForm.searchSysWorkflowRules('startSearch');
            }
    },
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }
      
        let that = this;

        if(this.options.edit_mode=='editonly'){
            //load rules
            if(this.options.swf_ID>0){
                    let request = {};
                    request['swf_ID']  = this.options.rem_RecID;
                    request['a']          = 'search'; //action
                    request['entity']     = this.options.entity.entityName;
                    request['details']    = 'full';
                    request['request_id'] = window.hWin.HEURIST4.util.random();
                    
                    window.hWin.HAPI4.EntityMgr.doRequest(request, 
                        function(response){
                            if(response.status == window.hWin.ResponseStatus.OK){
                                let recset = new HRecordSet(response.data);
                                if(recset.length()>0){
                                    that.updateRecordList(null, {recordset:recset});
                                    that.addEditRecord( recset.getOrder()[0] );
                                }
                                else {
                                    //nothing found - add new bookmark
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
        }
        else{
            this.searchForm.searchSysWorkflowRules(this.options);
            
            
            let iheight = 12;
            this.searchForm.css({'height':iheight+'em',padding:'10px'});
            this.recordList.css({'top':iheight+0.5+'em'});
            
            this.recordList.resultList({
                show_toolbar:false,
                view_mode: 'list',
                sortable: true,
                onSortStop: function(){
                    
                    let recordset = this.getRecordSet();
                    window.hWin.HEURIST4.dbs.applyOrder(recordset, 'swf', function(res){
                       
                    });
                    
                }
            });

            this.recordList.find('.div-result-list-content').css({'display':'table','width':'99%'});
            
            let vocab_id = $Db.getLocalID('trm', '2-9453');
            
            this._on( this.searchForm, {
                "searchsysworkflowrulesonfilter": this.filterRecordList,
                "searchsysworkflowrulesonresult": this.updateRecordList,
                "searchsysworkflowrulesonadd": function() {

                        let recset = this.recordList.resultList('getRecordSet');
                    
                        if(recset.length()==0){
                            this._addRuleSet();
                        }else{
                            let rty_ID = this.searchForm.searchSysWorkflowRules('getSelectedRty');
                            let terms = $Db.trm_TreeData(vocab_id, 'set');
                            let _swf_rules = $Db.getSwfByRectype(rty_ID);
                            if(_swf_rules.length<terms.length){
                                this._onActionListener(null, 'add');    
                            }else{
                                window.hWin.HEURIST4.msg.showMsgFlash('This record type has full set of stages');
                            }
                        }
                },
                "searchsysworkflowrulesonaddset": function() {
                        this._addRuleSet();
                },
                "searchsysworkflowrulesonvocabedit": function() {

                    let options = {
                        height:800, width:1300,
                        selection_on_init: vocab_id,
                        innerTitle: false,
                        innerCommonHeader: $('<div>'
                            +'<span style="margin-left:260px"><b>Editing Workflow Vocabulary</b></span>'
                            +'</div>'),
                        onInitFinished: function(){
                            let that2 = this;
                            setTimeout(function(){
                                that2.vocabularies_div.manageDefTerms('selectVocabulary', vocab_id);
                            },500);
                        },
                        onClose: function(){
                            that.searchForm.searchSysWorkflowRules('startSearch');
                        }
                    };
                    
                    window.hWin.HEURIST4.ui.showEntityDialog('defTerms', options);
                }
            });
        }


        return true;
    },
    
    //
    // listener of onfilter event generated by searchEtity. appicable for use_cache only       
    //
    filterRecordList: function(event, request){

        if( this.is_first ){
            this.is_first = false;
            this._loadData(true);
        }

        let results = this._super(event, request);
        
        this.searchForm.searchSysWorkflowRules('setButton', (results==null || results.length()==0));
        this.searchForm.searchSysWorkflowRules('refreshRectypeList');

        if(results==null || results.length()==0){ //count_total
        
            /*            
            if(this.options.select_mode=='manager'){
                
                let sMsg;
                let s_all = this.element.find('#chb_show_all_groups').is(':checked');
                if(!s_all){
                    sMsg = '<div style="margin-top:1em;">There are no record types defined in this group.'
                            +'<br><br>Please drag record types from other groups or add new<br>record types to this group.</div>';   
                }else{
                    sMsg = '<div style="padding: 10px">'
                            +'<h3 class="not-found" style="color:red;">Filter/s are active (see above)</h3><br>'
                            +'<h3 class="not-found" style="color:teal">No entities match the filter criteria</h3>'
                            +'</div>';
                }
                this.recordList.resultList('option','empty_remark', sMsg);
                this.recordList.resultList('renderMessage', sMsg);
            }
            */
        }
        
        
    },
    
    //
    //
    //
    _addRuleSet: function(){
        
        let that = this;
        let rty_ID = this.searchForm.searchSysWorkflowRules('getSelectedRty');
        let request = {a:'batch', entity:this.options.entity.entityName, 
            operation: 'add_rule_set',
            rty_ID:rty_ID};
        if(rty_ID>0)
        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    window.hWin.HAPI4.EntityMgr.refreshEntityData('swf', function(success){
                        if(success){
                            that.is_first = true;
                            that.searchForm.searchSysWorkflowRules('startSearch');
                        }    
                    });
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
    },
    
    
//----------------------------------------------------------------------------------    
    _getValidatedValues: function(){
        
        let fields = this._super();
        
        if(fields!=null){
            
            if(fields['swf_Visibility']=='null'){
                    fields['swf_SetVisibility'] = '';
            }else if(fields['swf_Visibility']!='hidden'){ //viewable or public
                    fields['swf_SetVisibility'] = fields['swf_Visibility'];    
            }else if(fields['swf_SetVisibility']==''){ //hidden
                    fields['swf_SetVisibility'] = 'hidden';
            }
            
            if(fields['swf_SetOwnership'] == 'null'){
                fields['swf_SetOwnership'] = '';
            }
            /*
            if(fields['swf_Ownership']=='null'){
                    fields['swf_SetOwnership'] = '';
            }else if(fields['swf_Ownership']=='0'){ //viewable or public
                    fields['swf_SetOwnership'] = '0';    
            }
            */
            
        }
        
        return fields;
    },

    //
    //
    //
    _saveEditAndClose: function( fields, afteraction ){

        //assign record id    
        if(this.options.edit_mode=='editonly' && this.options.swf_ID>0){
            let ele2 = this._editing.getFieldByName('swf_ID');
            ele2.editing_input('setValue', this.options.swf_ID );
        }
                                                                                  
        this._super();
    },
    
    _afterSaveEventHandler: function( recID, fieldvalues ){

        this._super( recID, fieldvalues );
        
        $Db.swf().setRecord(recID, fieldvalues);

        window.hWin.HAPI4.EntityMgr.refreshEntityData('swf', () => { // Update cache
            
            if(this.options.edit_mode=='editonly'){
                this.closeDialog(true);
            }else{
                //this.getRecordSet().setRecord(recID, fieldvalues);    
                //this.recordList.resultList('refreshPage'); 
                
                this.searchForm.searchSysWorkflowRules('option', 'rty_ID', fieldvalues['swf_RecTypeID']);                    
                //this.searchForm.searchSysWorkflowRules('refreshSelectors', fieldvalues['swf_RecTypeID']); 
            }
        });

    },

    _deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this._super(); 
        }else{
            let that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this rule?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    },
    
    _afterDeleteEvenHandler: function(recID){
        this._super(recID);
        $Db.swf().removeRecord(recID);
        this.searchForm.searchSysWorkflowRules('startSearch');
    },

    
    _afterInitEditForm: function(){

        this._super();
        
        let that = this;
        
        if(this.options.edit_mode=='editonly' || this.options.edit_mode=='popup'){
            
            if(that._currentEditID<0){
                const rty_ID = this.searchForm.searchSysWorkflowRules('getSelectedRty');
                that._editing.setFieldValueByName('swf_RecTypeID', rty_ID, false);
                
                //disable already selected stages
                let _swf_rules = $Db.getSwfByRectype(rty_ID);
                let ele = that._editing.getFieldByName('swf_Stage').editing_input('getInputs');
                ele = ele[0];
                for(let i=0; i<_swf_rules.length; i++){
                    ele.find('option[value='+_swf_rules[i]['swf_Stage']+']').attr('disabled',true);
                }
                ele.hSelect('refresh');
                
            }else{
                //disable rectype and stage for edit
                that._editing.getFieldByName('swf_RecTypeID').editing_input('setDisabled', true);
                that._editing.getFieldByName('swf_Stage').editing_input('setDisabled', true);
            }
            

            function __onChangeVisType(is_first){ 
                let ele = that._editing.getFieldByName('swf_Visibility');
                let ele1 = that._editing.getFieldByName('swf_SetVisibility');
                
                let res = ele.editing_input('getValues'); 
                if(res[0]=='hidden'){
                        ele1.show();
                }else{
                        ele1.hide();
                }
                
                if(is_first!==true){
                    ele.editing_input('isChanged', true);
                    that.onEditFormChange();  
                } 
            }

            let ele = that._editing.getFieldByName('swf_Visibility');
            let ele1 = that._editing.getFieldByName('swf_SetVisibility');        
            let res = ele1.editing_input('getValues'); 
            
            //assign value to swf_Visibility
            if(res[0]=='viewable' || res[0]=='public'){
                ele.editing_input('setValue', res[0]);
            }else if(res[0]==''){
                ele.editing_input('setValue', 'null');
            }else{
                ele.editing_input('setValue', 'hidden');
            }
            if(res[0]=='viewable' || res[0]=='public' || res[0]=='hidden'){
                ele1.editing_input('setValue', '');    
            }
            
            ele.editing_input('isChanged', false);
            ele1.editing_input('isChanged', false);
            ele.editing_input('option', 'change', __onChangeVisType);
            __onChangeVisType(true);
            
            //
            //ownership     
            /*
            function __onChangeOwnerType(is_first){ 
                let ele = that._editing.getFieldByName('swf_Ownership');
                let ele1 = that._editing.getFieldByName('swf_SetOwnership');
                
                let res = ele.editing_input('getValues'); 
                if(res[0]=='group'){
                        ele1.show();
                        if(is_first!==true) ele1.find('.entity_selector').trigger('click');
                }else{
                        ele1.hide();
                }
                
                if(is_first!==true){
                    ele.editing_input('isChanged', true);
                    that.onEditFormChange();  
                } 
            }

            ele = that._editing.getFieldByName('swf_Ownership');
            ele1 = that._editing.getFieldByName('swf_SetOwnership');        
            res = ele1.editing_input('getValues'); 
            
            //assign value to swf_Ownership
            if(res[0]==''){
                ele.editing_input('setValue', 'null');
            }else if(res[0]=='0'){
                ele.editing_input('setValue', '0');
            }else{
                ele.editing_input('setValue', 'group');
            }
            if(res[0]=='' || res[0]=='0'){
                ele1.editing_input('setValue', '');    
            }
            ele.editing_input('isChanged', false);
            ele1.editing_input('isChanged', false);
            ele.editing_input('option', 'change', __onChangeOwnerType);
            __onChangeOwnerType(true);
            */

            let rty_ID = this.searchForm.searchSysWorkflowRules('getSelectedRty');
            let has_FreetextFld = rty_ID > 0;
            let list_Fields = {
                title: 'Record title',
                stage: 'Workflow stage',
                user: 'Modifying user',
                link_v: 'Record viewer link',
                link_e: 'Record editor link',
                url: 'Record url'
            };
            !has_FreetextFld || $Db.rst(rty_ID).each2((rst_ID, record) => {
                switch ($Db.dty(rst_ID, 'dty_Type')) {
                    case 'freetext':
                        has_FreetextFld = true;
                        list_Fields[rst_ID] = record['rst_DisplayName'];
                        break;

                    case 'blocktext':
                    case 'file':
                    case 'resource':
                    case 'date':
                    case 'enum':
                    case 'float':
                    case 'integer':
                        list_Fields[rst_ID] = record['rst_DisplayName'];
                        break;

                    default:
                        break;
                }
            });

            // Hide and replace input with checkbox & dropdown combo
            ele = that._editing.getFieldByName('swf_RecEmailField');
            if(has_FreetextFld){

                let $input = ele.find('input');

                let $chk_Enabled = $('<input>', {
                    type: 'checkbox',
                    class: 'chkbx_EnableFld'
                }).insertAfter($input);

                let $sel_Field = $('<select>', {
                    class: 'sel_RecField'
                }).insertAfter($chk_Enabled);

                this._on($chk_Enabled, {
                    change: () => {
                        window.hWin.HEURIST4.util.setDisabled($sel_Field, !$chk_Enabled.is(':checked'));
                        if(!$chk_Enabled.is(':checked')){
                            $input.val('').trigger('change');
                        }
                    }
                });

                // Consider: should add rec owner? current user?
                window.hWin.HEURIST4.ui.createRectypeDetailSelect($sel_Field[0], rty_ID, ['freetext'],
                    [ {key: '', title: window.hWin.HR('Select field...')} ], {
                        useHtmlSelect: false,
                        selectedValue: $input.val(),
                        eventHandlers: {
                            onSelectMenu: (event) => {
                                let new_fld = $chk_Enabled.is(':checked') ? $sel_Field.val() : '';
                                $input.val(new_fld).trigger('change');
                            }
                        }
                    }
                );

                let def_value = $Db.getLocalID('dty', '1317-242');
                if($sel_Field.val() !== ''){
                    $chk_Enabled.prop('checked', true);
                }else if($sel_Field.find(`option[value="${def_value}"]`).length == 1){
                    $sel_Field.val(def_value).hSelect('refresh');
                }

                window.hWin.HEURIST4.util.setDisabled($sel_Field, !$chk_Enabled.is(':checked'));

                $input.hide();
            }else{
                ele.hide(); // hide field completely
            }

            ele = that._editing.getFieldByName('swf_EmailText');
            let $extra_help = $('<div>', {style: 'cursor: default;'})
                .html('Field subsitutions can be performed by enclosing the field ID within hash (#) symbols<span class="lnk_Flds">see the list here</span>');

            ele.find('.heurist-helper1').append($extra_help);

            let $txt_emailtext = ele.find('textarea');
            let $help_link = ele.find('.lnk_Flds').css({
                'text-decoration': 'underline',
                cursor: 'pointer',
                display: 'block',
                color: 'blue'
            });

            let list = '<div style="cursor: default;">List of available fields:<br><br>';
            let shared_styles = 'display: inline-block; vertical-align: -0.2em;';

            for(const dty_ID in list_Fields){

                let id = Number.isInteger(dty_ID) ? `ID #${dty_ID}` : dty_ID;
                let type = Number.isInteger(dty_ID) ? $Db.dty(dty_ID, 'dty_Type') : 'freetext';

                list += `<span style="display: inline-block; padding-bottom: 7.5px;">
                    <button class="ui-icon ui-icon-plus" data-dtyid="${dty_ID}" title="Add field code to message"></button>
                    <span style="${shared_styles} width: 150px; padding-left: 5px;" class="truncate" title="${list_Fields[dty_ID]}">${list_Fields[dty_ID]}</span> 
                    <span style="${shared_styles} width: 65px;" class="truncate" title="${id}">(${id})</span> 
                    <span style="${shared_styles} width: 65px;" class="truncate">[ ${type} ]</span>
                </span><br>`;
            }

            list += '</div>';

            let $dlg_fields, dialog_opened = false;

            this._on($help_link, {
                click: () => {

                    if(dialog_opened){
                        $dlg_fields.dialog('moveToTop');
                        return;
                    }

                    let interval = null;

                    $dlg_fields = window.hWin.HEURIST4.msg.showMsgDlg(list, null,
                        {title: 'Field insert', ok: window.hWin.HR('Close')},
                        {dialogId: 'dlg-field-insert', modal: false, default_palette_class: 'ui-heurist-design', 
                            position: {
                                my: 'right-12.5 center', at: 'left center', of: this._edit_dialog
                            },
                            close: () => {
                                dialog_opened = false;
                                $dlg_fields.remove();
                                clearInterval(interval);
                            }
                        }
                    );

                    $dlg_fields.find('button').button({icon: 'ui-icon-plus'}).on('click', (event) => {
                        let cursor_pos = $txt_emailtext[0].selectionStart;
                        let value = $txt_emailtext.val();
                        let insert = `#${$(event.target).attr('data-dtyid')}#`;

                        value = `${value.substr(0, cursor_pos)}${insert}${value.substr(cursor_pos)}`;
                        $txt_emailtext.val(value).trigger('change');
                    });

                    interval = setInterval(() => {
                        if(!this._edit_dialog
                        || this._edit_dialog.dialog('instance') === undefined
                        || !this._edit_dialog.dialog('isOpen')){

                            if($dlg_fields?.length > 0 && $dlg_fields.dialog('instance') !== undefined){
                                $dlg_fields.dialog('close');
                            }

                            clearInterval(interval);
                        }
                    }, 500);
                }
            });
        }
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
        return __cell('Stage',24)+__cell('Restricted to',24)+__cell('Ownership',24)
                    +__cell('Visibility',25)+__cell('Notification',25);
                    
    },
    
    //----------------------
    //
    //  overwrite standard render for resultList
    //
    _recordListItemRenderer:function(recordset, record){

        let that = this;

        function fld(fldname, def){

            let extra_val = '';
            if(fldname == 'swf_SendEmail'){
                
                let rty_ID = that.searchForm.searchSysWorkflowRules('getSelectedRty');
                let emails = recordset.fld(record, 'swf_EmailList');
                let field = recordset.fld(record, 'swf_RecEmailField');

                if(emails){
                    extra_val += `${emails.split(',').join('<br>')}`;
                }

                if(field){
                    extra_val += `${extra_val === '' ? '' : '<br>'}Values from: ${$Db.rst(rty_ID, field, 'rst_DisplayName')}`;
                }
            }

            let val = recordset.fld(record, fldname);
            if(val){
                if(fldname=='swf_Stage'){
                    val = $Db.trm(val,'trm_Label');
                }else{
                    if(fldname=='swf_SetVisibility' && (val=='viewable' || val=='public' || val=='hidden')){
                        return val;
                    }

                    let names = [];
                    $.each(val.split(','), function(i,item){
                        let name = window.hWin.HAPI4.sysinfo.db_usergroups[item];
                        if(!name && window.hWin.HEURIST4.allUsersCache){
                            let idx = window.hWin.HEURIST4.allUsersCache.findIndex((user) => {
                                return user.id == item;
                            });

                            if(idx >= 0){
                                name = window.hWin.HEURIST4.allUsersCache[idx].name;
                            }
                        }
                        if(name) names.push(window.hWin.HEURIST4.util.htmlEscape(name));
                    });
                    return `${names.join('<br>')}${extra_val === '' ? '' : '<br>'}${extra_val}`;
                }
            }else if(extra_val){
                val = extra_val;
            }else{
                val = def;
            }
            return window.hWin.HEURIST4.util.htmlEscape(val);
        }
        function fld2(val, col_width){
            let swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = 'width:'+col_width;
            }
            let title = val.replaceAll('<br>', "\n");
            return `<div class="truncate" style="display:inline-block;${swidth}" title="${title}">${val}</div>`;
        }

        //rem_ID,rem_RecID,rem_OwnerUGrpID,rem_ToWorkgroupID,rem_ToUserID,rem_ToEmail,rem_Message,rem_StartDate,rem_Freq,rem_RecTitle
        //rem_ToWorkgroupName
        //rem_ToUserName


        let recID   = recordset.fld(record,'swf_ID');

        let s_restrict = fld('swf_StageRestrictedTo','no restrictions'),
            s_ownership = fld('swf_SetOwnership','no changes'),
            s_visibility = fld('swf_SetVisibility','no changes'),
            s_email = fld('swf_SendEmail','no notification'),
            s_stage = fld('swf_Stage');

        let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'
                + fld2(s_stage,'25ex')
                + fld2(s_restrict,'25ex')
                + fld2(s_ownership,'25ex')
                + fld2(s_visibility,'25ex')
                + fld2(s_email,'25ex');

        // add edit/remove action buttons
        if(this.options.select_mode=='manager' && this.options.edit_mode=='popup'){
            html = html
                + '<div class="logged-in-only" style="width:60px;display:inline-block;">' //rec_view_link
                + '<div title="Click to edit rule" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit"  style="height:16px">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                + '</div>'
                +'<div title="Click to delete rule" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete"  style="height:16px">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                + '</div></div>';
        }
        //<div style="float:right"></div>' + '<div style="float:right"></div>

        html = html + '</div>';

        return html;

    }

});
