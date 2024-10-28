/**
* manageSysGroups.js - main widget to manage sysUGrps workgroups
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


$.widget( "heurist.manageSysGroups", $.heurist.manageEntity, {

    _entityName: 'sysGroups',

    _select_roles: {},

    //
    //
    //    
    _init: function() {

        this.options.default_palette_class = 'ui-heurist-admin';

        this.options.layout_mode = 'short';
        this.options.use_cache = false;
        this.options.width = 960;

       
        this.options.edit_need_load_fullrecord = false;
        this.options.edit_height = 572;
        this.options.height = 740;

        //for selection mode set some options
        if(this.options.select_mode!='manager'){
            this.options.width = (isNaN(this.options.width) || this.options.width<815)?900:this.options.width;                    
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

        //update dialog title
        let title = null;
        let usr_ID = 0;
        let that = this;
        
        if(this.options.title){
            title = this.options.title;
        }else
        if(this.options.select_mode=='select_single'){
           title = 'Select Workgroup'; 
        }else
        if(this.options.select_mode=='select_multi'){
           title = 'Select Workgroups'; 
        }else
        if(this.options.ugl_UserID>0){
            usr_ID = this.options.ugl_UserID;
            title = 'Manage Workgroups for User #'+this.options.ugl_UserID+': '
            /*}else if(this.options.ugl_UserID<0){
            usr_ID = Math.abs(this.options.ugl_UserID);
            title = 'Define Roles for User #'+usr_ID+': '; */
        }else{
            if(window.hWin.HAPI4.is_admin()){
                title = 'Manage Workgroups as Database Administrator';    
            }else{                    
                usr_ID = window.hWin.HAPI4.currentUser['ugr_ID'];
                title = 'Manage Workgroups for user #'+window.hWin.HAPI4.currentUser['ugr_ID']+': ';    
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
        this.searchForm.searchSysGroups(this.options);

        let iheight = 5;
        if(this.options.edit_mode=='inline'){            
            iheight = iheight + 6;
        }
        if(this.options.subtitle){
            iheight = iheight + 1.5;
        }
        this.searchForm.css({'height':iheight+'em',padding:'10px','min-width': '580px'});
        this.recordList.css({'top':iheight+0.4+'em'});
        //init viewer 

        if(this.options.select_mode=='manager' || that.options.select_mode=='select_roles'){
            this.recordList.parent().css({'border-right':'lightgray 1px solid'});

            let center_cols = 'border-left:1px solid gray;text-align:center;';
            this.recordList.resultList('option','rendererHeader',
                function(){

                    let select_roles = that.options.ugl_UserID>0 || that.options.select_mode=='select_roles';

                    let sHeader = '<div style="display:flex;">'
                        +`<div style="flex:0 0 33px;border-right:none;"></div>`
                        +`<div style="flex:0 1 ${select_roles?'3.5':'4'}em;border-left:1px solid gray;padding-left:5px;">ID</div>`
                        +`<div style="flex:0 2 11em;border-left:1px solid gray;padding-left:5px;">Name</div>`;

                    if(select_roles){
                        sHeader = sHeader
                        +`<div style="width:6em;${center_cols}">Members</div>`
                        +`<div style="width:5em;${center_cols}">Admin</div>`
                        +`<div style="width:5em;${center_cols}">Member</div>`
                        +`<div style="width:4.5em;${center_cols}">Edit</div>`
                        +`<div style="width:6em;${center_cols}">Delete</div>`;
                    }else{
                        sHeader = sHeader
                        +`<div style="flex:0 0 6em;${center_cols}">Your role</div>`
                        +`<div style="flex:0 0 4em;${center_cols}">Edit</div>`
                        +`<div style="flex:0 0 5em;${center_cols}">Delete</div>`
                        +`<div style="flex:0 1 6em;${center_cols}">Members</div>`
                        +`<div style="flex:0 0 8em;${center_cols}">Edit members</div>`;
                    }

                    sHeader += '<div style="flex:0 5 50em;padding-left:5px;border-left:1px solid gray;">Description</div>'
                    +'</div>';

                    return sHeader;
                }
            );
        }

        this._on( this.searchForm, {
            "searchsysgroupsonresult": this.updateRecordList,
            "searchsysgroupsonadd": function() { 
                this.addEditRecord(-1); 
            }
        });

        this._on( this.recordList, {
            "resultlistonpagerender": function(event){
                
                
                //init role selector
                this.recordList.find('.edit-members')
                .each(function(idx,item){
                    $(item).attr('title','View users in a group or assign users to a group');
                    //To view users in a group or assign users to a group, click on the edit icon in the Edit Membership column

                    /*$(item).button({icon:'ui-icon-pencil', iconPosition:"end"})
                    .css({'background':'gray !important', 'max-height':'1em'});*/
                })
                .on('click', function(event){
                    let group_ID = $(event.target).parents('.recordDiv').attr('recid');

                    let options = {select_mode: 'manager',
                        ugl_GroupID: group_ID,
                        edit_mode:'popup',
                        title: ("Manage Users of Workgroup #"+group_ID),

                        //before close - count for membership and refresh
                        beforeClose:function(){
                            
                            if(window.hWin.HAPI4.has_access(group_ID)){ //current user is admin of given group
                                let request = {
                                    'a'          : 'search',
                                    'entity'     : 'sysGroups',
                                    'details'    : 'count',
                                    'ugr_ID'     : group_ID
                                };
                                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                                    function(response){
                                        if(response.status == window.hWin.ResponseStatus.OK){
                                            let resp = new HRecordSet( response.data );
                                            let rec_updated = resp.getFirstRecord();
                                            let cnt = resp.fld(rec_updated, 'ugr_Members');

                                            let record = that.getRecordSet().getById(group_ID);
                                            that.getRecordSet().setFld(record, 'ugr_Members', cnt);
                                            that.recordList.resultList('refreshPage');  
                                        }else{
                                            window.hWin.HEURIST4.msg.showMsgErr(response);
                                        }
                                    }
                                );
                            }
                            return true;
                        }




                    };


                    window.hWin.HEURIST4.ui.showEntityDialog('sysUsers', options);
                });

                //event listeners for adminSelector and memberSelector    
                function __onRoleSelectorClick(event){
                    let ele = $(event.target);

                    let newRole = 'remove';
                    let currentStatus = ele.is(':checked');                                                    
                    if(currentStatus){
                        if(ele.parent().hasClass('adminSelector')){
                            newRole = 'admin';
                        }else{
                            newRole = 'member';
                        }
                    }

                    // verify that at least one checkbox is checked
                    let has_membership = that.recordList.find('input[type="checkbox"]:visible').is(':checked');
                    if(!has_membership){
                        ele.prop('checked', true);
                        window.hWin.HEURIST4.msg.showMsgFlash('User must be member of atleast one workgroup...', 3000);
                        return false;
                    }

                    let item = ele.parents('.recordDiv');
                    let group_ID = item.attr('recid');  


                    if(that.options.select_mode=='select_roles'){

                        if(newRole=='remove'){
                            that._select_roles[group_ID] = null;
                            delete that._select_roles[group_ID];
                        }else{
                            that._select_roles[group_ID] = newRole;
                        }
                        item.attr('data-value', newRole);
                        let cb2;
                        if(ele.parent().hasClass('adminSelector')){
                            cb2 = item.find('.memberSelector > input');
                        }else{
                            cb2 = item.find('.adminSelector > input');
                        } 
                        cb2.prop('checked',false);      

                        return;
                    }

                    let request = {};
                    request['a']        = 'action';
                    request['entity']   = 'sysGroups';
                    request['role']     = newRole;
                    request['userIDs']  = that.options.ugl_UserID;
                    request['groupID']  = group_ID;
                    request['request_id'] = window.hWin.HEURIST4.util.random();

                    window.hWin.HAPI4.EntityMgr.doRequest(request, 
                        function(response){             
                            if(response.status == window.hWin.ResponseStatus.OK){
                                //reload
                               
                                if(newRole=='remove'){
                                    window.hWin.HEURIST4.msg.showMsgFlash('User removed from group');
                                }else{
                                    item.attr('data-value', newRole);
                                    let cb2;
                                    if(ele.parent().hasClass('adminSelector')){
                                        cb2 = item.find('.memberSelector > input');
                                    }else{
                                        cb2 = item.find('.adminSelector > input');
                                    } 
                                    cb2.prop('checked',false);      

                                    window.hWin.HEURIST4.msg.showMsgFlash('New role applied');      
                                }
                                
                                if(that.options.ugl_UserID==window.hWin.HAPI4.currentUser['ugr_ID']){
                                    if(newRole=='remove'){
                                        window.hWin.HAPI4.currentUserRemoveGroup(group_ID);
                                    }else{
                                        window.hWin.HAPI4.currentUser['ugr_Groups'][group_ID] = newRole;
                                    }
                                    $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS); 
                                }
                                
                            }else{
                                //restore current value - rollback
                                let restoreRole = item.attr('data-value');
                                item.find('.adminSelector > input').prop('checked', (restoreRole=='admin') );
                                item.find('.memberSelector > input').prop('checked',(restoreRole=='member') );
                                window.hWin.HEURIST4.msg.showMsgErr(response);      
                            }
                    });



                }
                this.recordList.find('.adminSelector').on('change', __onRoleSelectorClick );
                this.recordList.find('.memberSelector').on('change', __onRoleSelectorClick );


        }});

        return true;
    },

    //----------------------
    //
    //  custom renderer for recordList
    //
    _recordListItemRenderer:function(recordset, record){

        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, sstyle, tip_text){
            
            if(!window.hWin.HEURIST4.util.isempty(sstyle)){
                sstyle = ' style="'+sstyle+'"';
            }else{
                sstyle = '';
            }
            let val = window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
            return '<div class="truncate" '+sstyle+' title="'+tip_text+'">'+val+'</div>';
        }
        
        let cur_mode = this.recordList.resultList('getCurrentViewMode');
        let is_list = (cur_mode=='list');
        let is_icon = (cur_mode=='icons');

        //ugr_ID,ugr_Type,ugr_Name,ugr_Description, ugr_eMail,ugr_FirstName,ugr_LastName,ugr_Enabled,ugl_Role

        let is_user_roles = (this.options.ugl_UserID>0 || this.options.select_mode=='select_roles');

        const recID   = fld('ugr_ID');
        let name = fld('ugr_Name');
        let desc = fld('ugr_Description');

        let name_width = navigator.userAgent.toLowerCase().includes('firefox') ? 10 : 11;
        let recTitle = fld2('ugr_ID',is_list?'flex:0 1 4em':'', '')
            +fld2('ugr_Name',is_list
                        ?`flex:0 2 ${name_width}em;padding-left:5px;`
                        :('position:absolute;top:16px;'+(is_icon?'left:60px;right:42px;':''))
                        , name);

        let rtIcon = window.hWin.HAPI4.getImageUrl(this._entityName, 0, 'icon');

        let recThumb = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'thumb');

        let html_thumb = '<div class="recTypeThumb" style="'
            +(cur_mode.indexOf('thumbs')>=0?'top:38px;':'')+'background-image: url(&quot;'+recThumb+'&quot;);opacity:1">'
        +'</div>';
        
        let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" data-value="'+ fld('ugl_Role')
            +'" style="'+(is_list?'display:flex;':'')+'">'
        + html_thumb
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + '<div class="recordIcons" '+(is_list?'style="flex: 0 0 30px;"':'')+'>'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" style="background-image: url(&quot;'+rtIcon+'&quot;);">'
        + '</div>'
        + recTitle;

        if(!is_user_roles){

            let show_role = this.searchForm.find('#input_search_type').val()!='any';
            html = html + '<div title="Role" style="'+(is_list?'flex:0 0 80px;':'')+'text-align:center;">'
                    +(show_role?fld('ugl_Role'):'')+'</div>';
        }

        let flexs = (is_list?'margin: 0px 15px;flex:0 0 25px;':'position:absolute;right:4px;');
        
        let btn_edit = '<div title="Click to edit group" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit" '
        +   'style="height:16px;top:4px;'+flexs+'">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
        + '</div>';
        let btn_delete = '<div title="Click to delete group" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete" '
        +   'style="height:16px;'+flexs+(is_list?'':'top:22px;')+'">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
        + '</div>';

        let locked_edit = '<div title="Status: not admin - locked" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" '
            +   'style="height:4px;top:4px;'+flexs+'">'
            +     '<span class="ui-button-icon-primary ui-icon ui-icon-lock"></span><span class="ui-button-text"></span>'
            + '</div>';

        if(!is_user_roles){
            html += window.hWin.HAPI4.has_access(recID) ? btn_edit : locked_edit;
            html += window.hWin.HAPI4.has_access(recID) && recID != 1 ? btn_delete : 
                        '<div style="height:16px;'+(is_list?'flex:0 0 55px;':'')+'"></div>';
        }

        if(this.options.select_mode=='select_roles'){

            html = html
            +'<div class="truncate" style="'+(is_list?'flex:0 1 50px;':'')
                        +'text-align:center;margin: 0px 15px 0px 10px;">' + fld('ugr_Members') + '</div>'
            +'<div class="adminSelector" style="'+(is_list?'flex:0 0 50px;':'')
                    +'padding-top:2px;"><input type="checkbox" id="adm'+recID
                    +'" '+(this._select_roles[recID]=='admin'?'checked':'')
            +'/></div>' 
            +'<div class="memberSelector" style="'+(is_list?'flex:0 0 30px;':'')
                    +'padding-top:2px;"><input type="checkbox" id="mem'+recID
                    +'" '+(this._select_roles[recID]=='member'?'checked':'')
            +'/><label for="mem'+recID+'">Member</label></div>';

            html = html + '</div>';

        }else if(this.options.select_mode=='manager' && this.options.edit_mode=='popup'){

            // admin/member checkboxes
            if(this.options.ugl_UserID>0){ //select_role

                html = html
                +'<div class="truncate" style="'+(is_list?'flex:0 1 50px;':'')+'text-align:center;margin: 0px 15px;">' + fld('ugr_Members') + '</div>'
                +'<div class="adminSelector" style="'+(is_list?'flex:0 0 50px;':'')+'padding-top:2px;"><input type="checkbox" id="adm'+recID
                +'" '+(fld('ugl_Role')=='admin'?'checked':'')
                +'/></div>' 
                +'<div class="memberSelector" style="'+(is_list?'flex:0 0 30px;':'')+'padding-top:2px;"><input type="checkbox" id="mem'+recID
                +'" '+(fld('ugl_Role')=='member'?'checked':'')
                +'/></div>';

            }else{

                html = html 
                + '<div class="truncate" style="'+(is_list?'flex:0 0 50px;':'position:absolute;top:50px;right:22px;')
                        +'text-align:center;margin: 0px 15px 0px 10px;">'
                        + fld('ugr_Members')
                + '</div>'  //'<span class="ui-icon ui-icon-pencil" style="font-size:0.8em"></span>
                + '<div class="edit-members ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" '
                +   'style="height:16px;'+(is_list?'margin: 0px 35px;':'position:absolute;top:50px;right:5px;')+ '">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                + '</div>'
            }
        }

        if(is_user_roles){

            let edit = window.hWin.HAPI4.has_access(recID) ? btn_edit : locked_edit; 
            edit = edit.replace('margin: 0px 15px;', 'margin: 0px 10px;');

            html += edit;
            html += btn_delete;
            //(window.hWin.HAPI4.has_access(recID) && recID != 1 || true) ? btn_delete : 
           
        }

        html = html 
            + fld2('ugr_Description',(is_list
                    ?'flex:0 0 50em;padding-left:10px;'
                    :('position:absolute;bottom:5px;'+(is_icon?'left:60px;right:42px;':'width:100%;'))), desc)
            + '</div>';

        return html;

    },

    //overwritten    
    _recordListGetFullData:function(arr_ids, pageno, callback){

        let request = {
            'a'          : 'search',
            'entity'     : this.options.entity.entityName,
            'details'    : 'list',
            'pageno'     : pageno
        };

        //add additional parameter - need to get a Role
        if(this.options.ugl_UserID>0){
            request['ugl_UserID'] = this.options.ugl_UserID;
            request['ugl_Join'] = true;
        }else 
            if(this.searchForm.find('#input_search_type').val()!='any'){
                request['ugl_UserID'] = window.hWin.HAPI4.currentUser['ugr_ID'];
            }

        request[this.options.entity.keyField] = arr_ids;
        window.hWin.HAPI4.EntityMgr.doRequest(request, callback);
    },

    _afterSaveEventHandler: function( recID, fieldvalues ){

        // close on addition of new record in select_single mode    
        if(this._currentEditID<0 && this.options.select_mode=='select_single'){
                this._selection = new HRecordSet();
               
                this._selection.addRecord(recID, fieldvalues);
                this._selectAndClose();
                return;       
        }
        //addition of new group - update fields in recordset and change current user credentials
        if(this._currentEditID<0){
            fieldvalues['ugr_Members'] = 1;
            fieldvalues['ugl_Role'] = 'admin';
            window.hWin.HAPI4.currentUser['ugr_Groups'][recID] = 'admin';
            window.hWin.HAPI4.sysinfo.db_usergroups[recID] = fieldvalues['ugr_Name'];
            $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS); 
        }
        this._super( recID, fieldvalues );

        this.getRecordSet().setRecord(recID, fieldvalues);
        this.recordList.resultList('refreshPage');  
        
    },
    
    _afterDeleteEvenHandler: function( recID )   {
        window.hWin.HAPI4.currentUserRemoveGroup(recID, true);
        
        this._super( recID );
        
        $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS); 
    },

    _afterInitEditForm: function(){
        this._super();
        //hide after edit init btnRecRemove for group=1
        if(this._currentEditID==window.hWin.HAPI4.sysinfo.db_managers_groupid){ //sys_OwnerGroupID
            let ele = this._toolbar;
            ele.find('.btnRecRemove').hide();
        }

    },


    _changeUserRole: function(group_id, user_ids, new_role){

        let request = {
            'a'          : 'action',
            'entity'     : this.options.entity.entityName,
            'request_id' : window.hWin.HEURIST4.util.random(),
            'groupID'    : group_id,
            'userIDs'    : user_ids,
            'role'       : new_role, //admin,member,remove
        };

        let that = this;                                                
       
        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){

                    let recID = response.data[0];
                    let fields = {};
                    fields[ that.options.entity.keyField ] = (''+recID);

                    //update record in cache
                    if(that.options.use_cache){
                        this._cachedRecordset.addRecord(recID, fields);
                    }else{
                        //add/update record in recordset in _afterSaveEventHandler depends on entity
                    }

                    that._afterSaveEventHandler( recID, fields );

                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });

    },

    //
    // event handler for select-and-close (select_multi)
    // or for any selection event for select_single
    // triger onselect event
    //
    _selectAndClose: function(){

        if(this.options.select_mode=='select_roles'){ //special case - select roles for any set of users
            if( $.isEmptyObject(this._select_roles)){
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: 'You have to allocate membership in at last one group',
                    error_title: 'Select a workgroup'
                });
            }else{
                this._trigger( "onselect", null, {selection: this._select_roles});
                this.closeDialog();
            }
        }else{
            this._super(); 
        }


    }
    
    ,_deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this._super(); 
        }else{
            let that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this group?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    }

});
