/**
* recordAccess.js - apply ownership and access rights
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

$.widget( "heurist.recordAccess", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 520,
        width:  520,
        modal:  true,
        init_scope: 'selected',
        title:  'Change Record Access and Ownership',
        currentOwner: 0,
        currentAccess: null,
        currentAccessGroups: null,
        show_modes: false,
        
        htmlContent: 'recordAccess.html',
        helpContent: false
    },

    _initControls:function(){

        if(!window.hWin.HAPI4.currentUser){
            return false;
        }
        
        let that = this;
        
        if(this.options.scope_types=='none'){
            this._$('#hr_sel_record_scope').hide();
        }
        
        that.fillAccessControls();
        
        
        //window.hWin.HAPI4.addEventListener(this, 
        $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_CREDENTIALS, 
            function(e, data) { 
                that.fillAccessControls();
        });
        
        return this._super();
    },
    
    
    //
    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_CREDENTIALS);
        return this._super();
    },
    
    //
    //
    //    
    fillAccessControls: function(){

        let that = this;
        let groups = window.hWin.HAPI4.is_admin() ? 'all_users_and_groups' : null;

        let fieldSelect = this._$('#sel_Ownership');
        window.hWin.HEURIST4.ui.createUserGroupsSelect(fieldSelect[0], groups,
            [{key:0, title:'Any logged-in user'}, {key:'current_user', title:'Current user'}], () => {

                fieldSelect = window.hWin.HEURIST4.ui.initHSelect(fieldSelect, false);
                
                that._off(fieldSelect,'change');
                that._off( that.element.find('input[name="rb_Owner"]'),'change');
                that._off( that.element.find('input[name="rb_Access"]'),'change');

                that._on(fieldSelect,{change:
                    function(){
                        if(fieldSelect.val()==0){
                            that.element.find('.access-hidden').hide();   
                            if(!that.element.find('#rb_Access-public').is(':checked'))
                                that.element.find('#rb_Access-viewable').prop('checked', true); 
                        }else{
                            that.element.find('.access-hidden').show();    
                        }
                        that._onRecordScopeChange();
                        
                    }
                });
                
                //define group selector for edit
                let ele = that.element.find('#sel_OwnerGroups');
                /*
                if(!ele.editing_input('instance')){
                    ele.empty();
                    this._createGroupSelectorElement('sel_OwnerGroups', null);    
                }
                */
                ele.hide();    
                
                
                //define group selector for access
                ele = that.element.find('#sel_AccessGroups');
                if(ele.length==0 || !ele.editing_input('instance')){
                    ele.empty();
                    that._createGroupSelectorElement('sel_AccessGroups', that.options.currentAccessGroups);    
                }
                //ele.hide();    

                if(!window.hWin.HEURIST4.util.isempty(that.options.currentOwner) || that.options.currentOwner==0){
                    
                    fieldSelect.val(that.options.currentOwner);
                    if( fieldSelect.val()==null && that.options.currentOwner){
                        let editors = that.options.currentOwner.split(',');
                        fieldSelect.val(editors[0]);
                    }
                    if( fieldSelect.val()==null ){
                        fieldSelect.val( window.hWin.HAPI4.currentUser['ugr_ID'] );
                    }
                    
                    /* multigroup edit option
                    if(this.options.currentOwner==0){
                        this._$('#rb_Owner-everyone').prop('checked', true);    
                    }else if(this.options.currentOwner == window.hWin.HAPI4.currentUser['ugr_ID']){
                        this._$('#rb_Owner-user').prop('checked', true);    
                    }else{
                        this._$('#rb_Owner-group').prop('checked', true);    
                        this._$('#sel_OwnerGroups').show().editing_input('setValue', [this.options.currentOwner]);
                    }
                    */
                }
                fieldSelect.hSelect('refresh');

                
                if(that.options.currentAccess){
                   
                    
                    if( that.options.currentAccess=='hidden' || that.options.currentAccessGroups){
                        that.element.find('#rb_Access-hidden').prop('checked', true); //was viewable-group
                        that.element.find('#div_AccessGroups').show();
                        that._adjustHeight();
                    }else{
                        that.element.find('#rb_Access-'+that.options.currentAccess).prop('checked', true);
                        
                    }
                }
                
                that._on( that.element.find('input[name="rb_Owner"]'),{change:function(){
                    
                    if(that.element.find('#rb_Owner-group').prop('checked')){
                        that.element.find('#sel_OwnerGroups').show();
                    }else{
                        that.element.find('#sel_OwnerGroups').hide();
                    }
                    
                    that._onRecordScopeChange();
                    
                }});
                
                that._on( that.element.find('input[name="rb_Access"]'), {change:function(){
                    
                    if(that.element.find('#rb_Access-hidden').prop('checked')){ //was viewable-group
                        that.element.find('#div_AccessGroups').show();
                    }else{
                        that.element.find('#div_AccessGroups').hide();
                    }
                    
                    that._adjustHeight();
                   
                    that._onRecordScopeChange();
                }});

                that._onRecordScopeChange();
            }
        );

        if(this.options.show_modes){
            this._$('#div_operation_mode').show();

            this._on(this._$('#div_operation_mode input[type="radio"]'), {
                change: (event) => {
                    let mode = $(event.target).val();

                    that.element.find('#div_sel_ownership, #sel_OwnerGroups, #div_sel_access, #div_sel_access2, #div_sel_access3').hide();
                    if(mode == 0 || mode == 1){
                        that.element.find('#div_sel_ownership, #sel_OwnerGroups').show();
                    }
                    if(mode == 0 || mode == 2){
                        that.element.find('#div_sel_access, #div_sel_access2, #div_sel_access3').show();
                    }

                    that._onRecordScopeChange();
                }
            });
        }else{
            this._$('#div_operation_mode').hide();
        }
    },
    
    _adjustHeight: function(){
        
    },

    //    
    //
    //
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Apply');
        return res;
    },    

    //
    //
    //
    _createGroupSelectorElement: function(input_id, init_value){
        
        if(window.hWin.HEURIST4.util.isnull(init_value)) init_value = '';
        
        let that = this;

        let ed_options = {
            recID: -1,
            dtID: input_id, //'group_selector',
            //rectypeID: rectypeID,
            values: [init_value],
            readonly: false,
            showclear_button: true,
            dtFields:{
                dty_Type:"resource",
                rst_DisplayName:'Select Groups:', rst_DisplayHelpText:'',
                rst_FieldConfig: {entity:'sysGroups', csv:true}
            },
            change: function(){ that._onRecordScopeChange(); }
            //change:_onAddRecordChange
        };

        /*
        $("<div>").attr('id','group_selector').editing_input(ed_options).appendTo($.find(input_id));
        var ele = $('#group_selector');
        ele.css('display','table');
        ele.find('.header').css({'min-width':'150px','text-align':'right'})
        */
        
        let ele = this._$('#'+input_id);
        ele.editing_input(ed_options);
        ele.find('.editint-inout-repeat-button').hide();
        ele.find('.header').css({'padding-right':'16px', 'padding-top':'4px', display:'inline-block'});
        ele.find('.input-cell').css({display:'inline-block'});
        
        ele.find('.entity_selector').css('max-width',200);
        
    },
    
    //
    //
    //
    getSelectedParameters: function( showWarning ){
       
        /* option for many groups edit 
        var ownership = this._$('input[type="radio"][name="rb_Owner"]:checked').val();
                    
        if(ownership=='everyone') {
            ownership = 0;
        } else if(ownership=='user') {
            ownership = window.hWin.HAPI4.currentUser['ugr_ID'];
        } else {
            ownership = $('#sel_OwnerGroups').editing_input('getValues');
            if(ownership && ownership.length>0){
                ownership = ownership[0];
            }
            if(!ownership){
                if(showWarning)
                    window.hWin.HEURIST4.msg.showMsgFlash('Select group with edit permission');
                return false;
            }
        }
        */

        let mode = this.options.show_modes ? this._$('#div_operation_mode [name="mode"]:checked').val() : 0;
        let ownership = this._$('#sel_Ownership').val();
        
        let visibility = this._$('input[type="radio"][name="rb_Access"]:checked').val();
        if(!visibility && (mode == 0 || mode == 2)){
            if(showWarning)
                window.hWin.HEURIST4.msg.showMsgFlash('Select access permission');
            return false;
        }
        
        let visibility_groups = '';
        
        if(ownership==0 && visibility!='public'){
            
            visibility='viewable';
            
        }else if(visibility=='hidden' && this._$('#sel_AccessGroups').editing_input('instance')){
            let sel = this._$('#sel_AccessGroups').editing_input('getValues');

            if(sel && sel.length>0 && sel[0]!=''){
                visibility = 'viewable';
                visibility_groups = sel.join(',');
            }
        }
            
        this.options.currentOwner = ownership;           
        this.options.currentAccess = visibility;
        this.options.currentAccessGroups = visibility_groups;
        
        return true;
    },
    
    //
    //
    //
    doAction: function(){

            let scope_val = (this.options.scope_types=='none' || !this.selectRecordScope)?'any':this.selectRecordScope.val();
            
            if(window.hWin.HEURIST4.util.isempty(scope_val)) return; 
    
            if(!this.getSelectedParameters(true)) return;
            
        
            if(this.options.scope_types=='none'){
                //return values as context
                this._context_on_close = {
                'OwnerUGrpID': this.options.currentOwner,
                'NonOwnerVisibility': this.options.currentAccess,
                'NonOwnerVisibilityGroups':this.options.currentAccessGroups,
                };
                
                this.closeDialog();
                return;
            }
            
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
            
            //unique session id    
            let session_id = Math.round((new Date()).getTime()/1000);
            this._showProgress( session_id, false, 1000 );
            
            let request = {
                request_id : window.hWin.HEURIST4.util.random(),
                ids  : scope.join(','),
                session: session_id
            };

            if(this.options.show_modes){
                let mode = this._$('#div_operation_mode [name="mode"]:checked').val();
                if(mode == 0 || mode == 1){
                    request['OwnerUGrpID'] = this.options.currentOwner;
                }
                if(mode == 0 || mode == 2){
                    request['NonOwnerVisibility'] = this.options.currentAccess;
                    request['NonOwnerVisibilityGroups'] = this.options.currentAccessGroups;
                }
            }else{

                request['OwnerUGrpID'] = this.options.currentOwner;
                request['NonOwnerVisibility'] = this.options.currentAccess;
                request['NonOwnerVisibilityGroups'] = this.options.currentAccessGroups;
            }

            if(rec_RecTypeID>0){
                request['rec_RecTypeID'] = rec_RecTypeID;
            }
                
                let that = this;                                                
                
                window.hWin.HAPI4.RecordMgr.access(request, 
                    function(response){
            
                        that._hideProgress();
            
                        if(response.status == window.hWin.ResponseStatus.OK){

                            that._context_on_close = (response.data.updated>0);
                            
                            that.closeDialog();
                            
                            let msg = 'Processed : '+response.data.processed + ' record'
                                + (response.data.processed>1?'s':'') +'. Updated: '
                                + response.data.updated  + ' record'
                                + (response.data.updated>1?'s':'');
                           if(response.data.noaccess>0){
                               msg += ('<br><br>Not enough rights (logout/in to refresh) for '+response.data.noaccess+
                                        ' record' + (response.data.noaccess>1?'s':''));
                           }     
                            
                            window.hWin.HEURIST4.msg.showMsgFlash(msg, 2000);
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
        
    },
    
    //
    // overwritten
    //
    _onRecordScopeChange: function () 
    {
        
        let scope_val = (this.options.scope_types=='none' || !this.selectRecordScope)?'any':this.selectRecordScope.val();
        let isdisabled = !(scope_val!='' && this.getSelectedParameters(false))
            
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('.btnDoAction'), isdisabled );
    }
  
});

