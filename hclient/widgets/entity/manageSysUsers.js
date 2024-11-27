/**
* manageSysUsers.js - main widget to manage sysUGrps users
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


$.widget( "heurist.manageSysUsers", $.heurist.manageEntity, {
   
    _entityName:'sysUsers',
    
    _currentSaml: null,

    //
    //
    //    
    _init: function() {
        
        this.options.default_palette_class = 'ui-heurist-admin';
        
        this.options.layout_mode = 'short';
        this.options.use_cache = false;

        this.options.edit_need_load_fullrecord = true;
        this.options.edit_height = 640;
        this.options.height = 640;

        if(this.options.edit_mode=='editonly'){
            this.options.edit_mode = 'editonly';
            this.options.select_mode = 'manager';
            this.options.layout_mode = 'editonly';
            this.options.width = 790;
           
        }else
        //for selection mode set some options
        if(this.options.select_mode!='manager'){
            this.options.width = (isNaN(this.options.width) || this.options.width<750)?750:this.options.width;                    
            //this.options.edit_mode = 'none'
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
            this._initEditorOnly();
            return;
        }
        
        //update dialog title
        let title = null;
        let usr_ID = 0;
        let that = this;
        
        
        if(this.options.title){
            title = this.options.title;
        }else
        if(this.options.select_mode=='select_single'){
           title = 'Select User'; 
        }else
        if(this.options.select_mode=='select_multi'){
           title = 'Select Users'; 
          
          if(this.options.ugl_GroupID<0){ 
                usr_ID = Math.abs(this.options.ugl_GroupID);
                title += ' to add to Workgroup #'+usr_ID+': ';
          }
           
        }else
        if(this.options.ugl_GroupID>0){
            usr_ID = this.options.ugl_GroupID;
            title = 'Manage Users of Workgroup #'+this.options.ugl_GroupID+': ';
        }else /*if(this.options.ugl_GroupID<0){
            usr_ID = Math.abs(this.options.ugl_GroupID);
            title = 'Select Users to add to Workgroup #'+usr_ID+': ';
        }else*/
        {
            if(window.hWin.HAPI4.is_admin()){
                title = 'Manage All Users as Database Administrator';    
            }else{                    
               
                title = 'Manage Users';    
            }
        }
        
        if(usr_ID>0 && title){
            function __set_dlg_title(res){
                if(res && res.status==window.hWin.ResponseStatus.OK){
                    that.setTitle( title+res.data[usr_ID] );    
                }
            } 
            window.hWin.HAPI4.SystemMgr.usr_names({UGrpID: usr_ID}, __set_dlg_title);
        }else{
            this.setTitle( title );    
        }
        
        // init search header
        this.searchForm.searchSysUsers(this.options);
        
        let iheight = 7;
        if(this.options.edit_mode=='inline'){            
            iheight = iheight + 6;
        }
        if(this.options.subtitle){
            iheight = iheight + 1.5;
        }
        
        this.searchForm.css({'height':iheight+'em',padding:'10px', 'min-width': '730px'});
        this.recordList.css({'top':iheight+0.5+'em', 'min-width': '730px'});
        //init viewer 
        
        if(this.options.select_mode=='manager'){
            this.recordList.parent().css({'border-right':'lightgray 1px solid'});
            
            let center_cols = 'border-left:1px solid gray;text-align:center;';
            this.recordList.resultList('option','rendererHeader',
                    function(){

                        let s = '<div style="display: flex;">'
                                    +'<div style="width:25px;border:none;"></div>'
                                    +'<div style="flex:0 2 3.5em;border-left:1px solid gray;padding-left:5px;">ID</div>'
                                    +'<div style="flex:0 1 8em;border-left:1px solid gray;padding-left:5px;">Name</div>'
                                    +'<div style="flex:0 3 10em;border-left:1px solid gray;padding-left:5px;">Full name</div>';

                        if (window.hWin.HAPI4.is_admin()){
                            s += `<div style="flex:0 0 4.5em;${center_cols}">Edit</div>`;
                            s += `<div style="flex:0 0 6em;${center_cols}">${!that.options.ugl_GroupID ? 'Delete' : 'Remove'}</div>`;
                        }

                        s += `<div style="flex:0 1 8em;${center_cols}">Membership</div>`;

                        if(!that.options.ugl_GroupID){
                            s += `<div style="flex:0 0 10em;${center_cols}">Edit membership</div>`;
                        }

                        s += '<div style="flex:0 5 20em;border-left:1px solid gray;padding-left: 10px;">Institution/Organisation</div>';
                        s += '</div>';
                        
                        return s;
                    }
                );
           
        }

        this._on( this.searchForm, {
            "searchsysusersonresult": this.updateRecordList,
            "searchsysusersonadd": function() { this.addEditRecord(-1); },
            "searchsysusersonfind": function() { 

                let ugl_GroupID = this.searchForm.find('#input_search_group').val(); 

                let options = {select_mode: 'select_multi',
                        ugl_GroupID: -ugl_GroupID,
                        edit_mode:'none',
                        title: ("Select Users to add to Workgroup #"+ugl_GroupID),
                        onselect:function(event, data){
                
                            if(data && window.hWin.HEURIST4.util.isArrayNotEmpty(data.selection))
                            {
                                let request = {};
                                request['a']        = 'action'; //batch action
                                request['entity']   = 'sysGroups';
                                request['role']     = 'member';
                                request['userIDs']  = data.selection;
                                request['groupID']  = ugl_GroupID;
                                request['request_id'] = window.hWin.HEURIST4.util.random();
                                
                                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                                    function(response){
                                        if(response.status == window.hWin.ResponseStatus.OK){
                                            //reload
                                            that.searchForm.searchSysUsers('startSearch');
                                            
                                            if(data.selection.indexOf(window.hWin.HAPI4.currentUser['ugr_ID'])>=0){
                                                window.hWin.HAPI4.currentUser['ugr_Groups'][ugl_GroupID] = 'member';
                                                $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS); 
                                            }
                                            
                                        }else{
                                            window.hWin.HEURIST4.msg.showMsgErr(response);      
                                        }
                                    });
                            
                            }                                    
                        }};                    
                
                window.hWin.HEURIST4.ui.showEntityDialog('sysUsers', options);
            }
        });

        this._on( this.recordList, {
            "resultlistonpagerender": function(event){

                //init role dropdown selector
                this.recordList.find('select.user-role')
                .each(function(idx,item){$(item).val($(item).attr('data-value'))})
                .on('change', function(event){

                    let ugl_GroupID = that.searchForm.find('#input_search_group').val(); 
                    if(!(ugl_GroupID>0)) return;
                    /*if(!(ugl_GroupID>0)) {
                        
                        this.recordList.find('.user-list-edit')
                        .each(function(idx,item){
                            $(item).attr('title','Edit user membership');
                        })
                        .on('click', function(event){
                            alert('Need open group mgr')
                        });
                        return;   
                    } */

                    //apply new role to user
                    
					let selector = $(event.target);
                    let usr_ID = selector.parents('.recordDiv').attr('recid');  
                    let newRole = selector.val();

                    let request = {};
                    request['a']        = 'action';
                    request['entity']   = 'sysGroups';
                    request['role']     = newRole;
                    request['userIDs']  = usr_ID;
                    request['groupID']  = ugl_GroupID;
                    request['request_id'] = window.hWin.HEURIST4.util.random();

                    window.hWin.HAPI4.EntityMgr.doRequest(request, 
                        function(response){             
                            if(response.status == window.hWin.ResponseStatus.OK){
                                //reload
                               
                                if(newRole=='remove'){
                                    let recset = that.recordList.resultList('getRecordSet');
                                    recset.removeRecord(usr_ID);
                                    that.recordList.resultList('refreshPage');  
                                    window.hWin.HEURIST4.msg.showMsgFlash('User removed from group');
                                }else{
                                    selector.attr('data-value', newRole);
                                    window.hWin.HEURIST4.msg.showMsgFlash('New role applied');      
                                }

                                if(usr_ID==window.hWin.HAPI4.currentUser['ugr_ID']){
                                    if(newRole=='remove'){
                                        window.hWin.HAPI4.currentUserRemoveGroup(ugl_GroupID);
                                    }else{
                                        window.hWin.HAPI4.currentUser['ugr_Groups'][ugl_GroupID] = newRole;
                                    }
                                    $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS); 
                                }
                                
                            }else{
                                //restore current value
                                selector.val( selector.attr('data-value') );
                                window.hWin.HEURIST4.msg.showMsgErr(response);      
                            }
                    });
                });

                //manage membership of user in all groups
                this.recordList.find('.edit-members')
                .on('click', function(event){
                    let user_ID = $(event.target).parents('.recordDiv').attr('recid');
                    let enabled = $(event.target).parents('.recordDiv').attr('usr_status');
                    
                    if (enabled=='n')
                    {
                        window.hWin.HEURIST4.msg.showMsgDlg('You need to enable the user before assigning them a group');
                        return;                        
                    }

                    let options = {select_mode: 'manager',
                        ugl_UserID: user_ID,
                        edit_mode:'popup',
                        title: ("Manage Membership for User #"+user_ID),
                        //before close - count for membership and refresh
                        beforeClose:function(){
                            
                            //count user memebrship in groups
                            let request = {
                                'a'          : 'search',
                                'entity'     : 'sysUsers',
                                'details'    : 'count',
                                'ugr_ID'     : user_ID
                            };
                            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                                function(response){
                                    if(response.status == window.hWin.ResponseStatus.OK){
                                        let resp = new HRecordSet( response.data );
                                        let rec_updated = resp.getFirstRecord();
                                        let cnt = resp.fld(rec_updated, 'ugr_Member');
                                        if(cnt>0){
                                            let record = that.getRecordSet().getById(user_ID);
                                            that.getRecordSet().setFld(record, 'ugr_Member', cnt);
                                            that.recordList.resultList('refreshPage');  
                                            $('body').find('div[id^="heurist-dialog-SysGroups-"]').manageSysGroups('closeDialog', true);
                                        }else{
                                            window.hWin.HEURIST4.msg.showMsgErr({
                                                message: 'User must belong to one group at least',
                                                error_title: 'Select a workgroup'
                                            });
                                        }
                                    }else{
                                        window.hWin.HEURIST4.msg.showMsgErr(response);
                                    }
                                }
                            );
                            return false;
                        }
                    };

                    window.hWin.HEURIST4.ui.showEntityDialog('sysGroups', options);
                });


            }
        });

        return true;
    }
    

    //----------------------
    //
    //
    //
    , _recordListItemRenderer:function(recordset, record){
        
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, sstyle){
            let swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(sstyle)){
                swidth = ` style="${sstyle}"`;
            }
            return `<div class="truncate" ${swidth}>${window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname))}</div>`;
        }
        
        //ugr_ID,ugr_Type,ugr_Name,ugr_Description, ugr_eMail,ugr_FirstName,ugr_LastName,ugr_Enabled,ugl_Role
        
        const recID = fld('ugr_ID');

        let recOpacity = (fld('ugr_Enabled')!='n')?1:0.3;

        let rtIcon = window.hWin.HAPI4.getImageUrl(this._entityName, 0, 'icon');

        let recThumb = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'thumb', 2, this.options.database);

        let html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+recThumb+'&quot;);opacity:'+recOpacity+'">'
        +'</div>';

        let name_width = navigator.userAgent.toLowerCase().includes('firefox') ? 7.5 : 8.5;

        let mail_link = !window.hWin.HEURIST4.util.isempty(fld('ugr_eMail')) ? ` href="mailto:${fld('ugr_eMail')}" ` : '';

        let user_content = window.hWin.HEURIST4.util.isempty(fld('ugr_Name'))
            ? ''
            : `<a class="truncate" style="flex:0 1 ${name_width}em"${mail_link}title="${fld('ugr_eMail')}">{fld('ugr_Name')}</a>`;

        let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" usr_status="'+fld('ugr_Enabled')+'" style="display: flex;">'
        + html_thumb
        + '<div class="recordSelector"><input type="checkbox"></div>'
        + '<div class="recordIcons" style="flex:0 0 20px;">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" style="background-image: url(&quot;'+rtIcon+'&quot;);opacity:'+recOpacity+'">'
        + '</div>'
        + fld2('ugr_ID','flex:0 2 4em;')
        + user_content
        + '<div class="truncate" style="flex:0 3 10em;">'+fld('ugr_FirstName')+' '+fld('ugr_LastName')+'</div>';

        let show_buttons = this.options.edit_mode !== 'none';

        // add edit/remove action buttons
        if(recID == 2 && window.hWin.HAPI4.has_access(2) && show_buttons){ // only db owner can edit db owner

            html += '<div title="Click to edit user" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit" '
                  + 'style="height:16px;margin: 0px 15px;flex:0 0 25px;">'
                    +'<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                + '</div>'
                + '<div style="width: 75px;"></div>';

        }else
        if(window.hWin.HAPI4.is_admin() && show_buttons){//current user is admin of database managers

            let icon = !this.options.ugl_GroupID ? 'circle-close' : 'arrowrefresh-1-n';
            let action = !this.options.ugl_GroupID ? 'delete' : 'remove';

            html += '<div title="Click to edit user" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit" '
                  + 'style="height:16px;margin: 0px 15px;flex:0 0 25px;">'
                    + '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                + '</div>'
                + `<div title="Click to ${action} user" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="${action}" `
                  + 'style="height:16px;margin: 0px 25px;flex:0 0 25px;">'
                    + `<span class="ui-button-icon-primary ui-icon ui-icon-${icon}"></span><span class="ui-button-text"></span>`
                + '</div>';

        }

        // add edit group memberships
        if(this.options.select_mode=='manager' && show_buttons){
        
            const ugl_GroupID = this.searchForm.find('#input_search_group').val(); 
            if(window.hWin.HAPI4.is_admin() && !(ugl_GroupID>0)){  //all groups - show count of groups where user is a member
                html += '<div class="truncate" style="flex: 0 1 8em;text-align: center;">'
                            + fld('ugr_Member') 
                    + '</div>'
                    + '<div class="edit-members ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" style="height:16px;margin: 0px 60px;" '
                      + 'title="Edit participation of user in groups">'
                        + '<span class="ui-icon ui-icon-pencil"></span>'
                    + '</div>';
            }

            if(ugl_GroupID>0){

                html += '<div class="rec_actions user-list" style="flex:0 1 8em;">';

                if(recID==2 && ugl_GroupID==window.hWin.HAPI4.sysinfo.db_managers_groupid){
                    html += '<div style="min-width:88px;text-align:center">admin</div>';
                }else 
                if(window.hWin.HAPI4.has_access(ugl_GroupID)){ // current user is admin of given group

                    html += '<select title="Role" style="min-width:70px;text-align:center;margin-right:18px;" class="user-role" data-value="'
                                + fld('ugl_Role')+'">'
                                +'<option>admin</option><option>member</option><option>remove</option></select>';

                }else{

                    html += '<div title="Role" style="min-width:88px;text-align:center">'
                                + fld('ugl_Role')+'</div>';
                }

                html += '</div>';
            }

        }
        
        html += '<div class="truncate" style="flex: 0 5 20em;">'+fld('ugr_Organisation')+'</div>';

        html += '</div>';

        return html;
        
    },

    _onActionListener: function(event, action){

        let is_resolved = this._super(event, action);

        if(is_resolved){
            return true;
        }else if(!window.hWin.HEURIST4.util.isObject(action)){
            return false;
        }

        let usr_ID = action.recID;
        action = action.action;

        if(action == 'remove' && this.options.ugl_GroupID > 0){

            let $select = this.recordList.find(`.recordDiv[recID="${usr_ID}"] select.user-role`);
            $select.val('remove').trigger('change');

            is_resolved = true;
        }

        return is_resolved;
    },

    //overwritten    
    _recordListGetFullData:function(arr_ids, pageno, callback){

        let request = {
            'a': 'search',
            'entity': this.options.entity.entityName,
            'details': 'list',
            'pageno': pageno,
            'db': this.options.database                
        };
        let ugl_GroupID = this.searchForm.find('#input_search_group').val();
        if(ugl_GroupID>0){
            request['ugl_GroupID'] = ugl_GroupID;
        }
        
        request[this.options.entity.keyField] = arr_ids;
        window.hWin.HAPI4.EntityMgr.doRequest(request, callback);
    },
    
    
    //-----
    // adding group ID value for new user
    //
    _afterInitEditForm: function(){

        this._super();

        let ugl_GroupID = this.searchForm.find('#input_search_group').val();
        if(ugl_GroupID>0 && !this._currentEditRecordset){ //insert       

            let ele = this._editing.getFieldByName('ugl_GroupID');
            ele.editing_input('setValue', ugl_GroupID);
            //hide save button
            if(this._toolbar){
                this._toolbar.find('.btnRecSave').css('visibility', 'visible');
            }
        }else
        //hide after edit init btnRecRemove for dbowner (user #2)
        if(this._currentEditID==2 || !window.hWin.HAPI4.is_admin()){
            let ele = this._toolbar;
            ele.find('.btnRecRemove').hide();
        }
        
        let btnTrOwner = this._toolbar.find('.btnTransferOwnership');
        
        if(this._currentEditID>0 && this._currentEditID!=2 && window.hWin.HAPI4.user_id()==2){
            //add ownershup transfer button
            if(btnTrOwner.length==0){
                btnTrOwner = $('<button class="btnTransferOwnership">')
                        .appendTo(this._toolbar);
            }
            btnTrOwner.button({
                         label:'Transfer Ownership',icon:'ui-icon-transfer-e-w'})
                        .css({'float':'left',margin:'.5em .4em 0 .5em'}).show();
                        
            this._on(btnTrOwner, {click:this._transferDBOwner});
        }else{
            if(btnTrOwner) btnTrOwner.hide();
        }
        
        if(!window.hWin.HAPI4.is_admin() || window.hWin.HAPI4.currentUser['ugr_ID']==this._currentEditID){
            let input_ele = this._editing.getFieldByName('ugr_Enabled');
            input_ele.hide();
           
        }
        
        //fill SAML service providers list
        let saml_sel = this._editing.getFieldByName('ugl_SpID').find('select');
        let has_saml = false;
        if($.isPlainObject(window.hWin.HAPI4.sysinfo.saml_service_provides)){
            let sp_keys = Object.keys(window.hWin.HAPI4.sysinfo.saml_service_provides);
            if(sp_keys.length>0){
                saml_sel.empty();
                
                for(let id of sp_keys){
                    window.hWin.HEURIST4.ui.addoption(saml_sel[0],id,window.hWin.HAPI4.sysinfo.saml_service_provides[id]);
                    has_saml = true;
                }
                                       
            }
        }
        
        this._currentSaml = null;
        if(has_saml){
            window.hWin.HEURIST4.ui.initHSelect(saml_sel[0], false);
            this._on(saml_sel, {change:function(event){
               //save current and load new one
               this._Saml_from_UI( this._currentSaml );
               
               //assign new one
               this._Saml_To_UI( $(event.target).val() );
            }});

            this._editing.setModified(0); // avoid triggering modified flag

            saml_sel.trigger('change');

        }else{
            let content = saml_sel.parents('.ui-accordion-content');
            content.hide();
            this.editForm.find('#'+content.attr('aria-labelledby')).hide(); // .ui-accordion-header 
        }
    },
    
    //
    // save current saml values
    //
    _Saml_from_UI: function( saml_id ){
       
       if(saml_id){
           let auth = this._editing.getValue('usr_ExternalAuthentication')[0];
           auth = window.hWin.HEURIST4.util.isJSON(auth);
           if(!auth) auth = {};
           
           let uid = this._editing.getValue('ugl_SpUID')[0];
           let mail = this._editing.getValue('ugl_SpMail')[0];

           if(uid!='' || mail=='y'){
               //replace SP
               auth[saml_id] = {uid:uid, mail:mail};
           }else if (auth[saml_id]){
               //remove SP
               auth[saml_id] = null;
               delete auth[saml_id];
           }
           
           this._editing.setFieldValueByName('usr_ExternalAuthentication', JSON.stringify(auth));
       }
    },
    
    //
    // assign values to UI
    //
    _Saml_To_UI: function( saml_id ){
        
       this._currentSaml = saml_id;

       let auth = this._editing.getValue('usr_ExternalAuthentication')[0];

       auth = window.hWin.HEURIST4.util.isJSON(auth);
       if(auth && auth[saml_id]){
            this._editing.setFieldValueByName('ugl_SpUID', auth[saml_id]['uid'], false);
            this._editing.setFieldValueByName('ugl_SpMail', auth[saml_id]['mail'], false);
       }else{
            this._editing.setFieldValueByName('ugl_SpUID', '', false);
            this._editing.setFieldValueByName('ugl_SpMail', 'n', false);
       }
        
    },               
    
    //
    //
    //
    _getValidatedValues: function(){
        this._Saml_from_UI( this._currentSaml );
        return this._super();
    },
    
    
    // update list after save (refresh)
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){

        // close on addition of new record in select_single mode    
        if(this._currentEditID<0 && this.options.select_mode=='select_single'){
            
                this._selection = new HRecordSet();
               
                this._selection.addRecord(recID, fieldvalues);
                this._selectAndClose();
                return;        
        }
        if (this._currentEditID<0) {
            fieldvalues['ugl_Role'] = 'member';    
        }
        
        this._super( recID, fieldvalues );
        
        if(this.options.edit_mode == 'editonly'){
            this.closeDialog(true); //force to avoid warning
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
                'Are you sure you wish to delete this user?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    },

    /*
     * Transfer DB Ownership to the selected User, will reload the page after completion to reset certain variables
     * It shows Warning Message about Transfering Ownership to another user.
     *
     * Param: unconditionally (bool) -> DB Owner's agreement to complete task
     */
    _transferDBOwner: function(unconditionally){

        if(this._currentEditID==null || this._currentEditID<1) return;

        const isEnabled = this._editing.getValue('ugr_Enabled')[0];
        const isModified = this._editing.isModified();
        let that = this;

        if(isModified){

            window.hWin.HEURIST4.msg.showMsgDlg('Please save or revert any changes made to this user to transfer ownership');
            return;
        }else if(isEnabled == 'n'){
            
            window.hWin.HEURIST4.msg.showMsgDlg('Unable to transfer database ownership to an disabled account.<br>Please enable it to transfer ownership');
            return;
        }

        if(unconditionally===true){
            
            const request = {
                'a': 'action',
                'transferOwner': true,
                'entity'     : this.options.entity.entityName,
                'request_id' : window.hWin.HEURIST4.util.random(),
                'recID'      : this._currentEditID 
            };

            window.hWin.HAPI4.EntityMgr.doRequest(request,
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){

                        let recID = that._currentEditID;
                        that._afterTransferOwnerHandler(recID);
                    
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                }
            );
            
        }else{
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to transfer the ownership of this database to the selected user? This action can only be undone by the new owner.<br>'
                +' <p style="font-size: 1.1em; font-weight: bold;">'
                + 'Note: Heurist will need to logout and reload once the changes have been made, ensure you save and complete any additional tasks before proceeding.</p>',
                function(){ that._transferDBOwner(true); },
                {title:'Warning',yes:'Proceed',no:'Cancel'});
        }
    },
    
    /*
     * After Action Handler for DB Ownership Tranfer, needs to logout the user and reload the page
     */
    _afterTransferOwnerHandler: function(recID){

        window.hWin.HEURIST4.msg.showMsgFlash(this.options.entity.entityTitle + ' ' + window.hWin.HR('ownership has been transfered') + '.'
            +'<br>Heurist will now refresh to set these changes.', 2000); // flash message

        /* Trigger Logout and Reload */
        window.hWin.HAPI4.SystemMgr.logout(
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    window.location.reload();  // page reload
                }
            }
        );
    }
    
});
