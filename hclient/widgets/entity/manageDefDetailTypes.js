/**
* manageDefDetailTypes.js - main widget for field types
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


$.widget( "heurist.manageDefDetailTypes", $.heurist.manageEntity, {

    _entityName:'defDetailTypes',
    fields_list_div: null,  //suggestion list
    set_detail_type_btn: null,

    fieldSelectorLast:null,
    fieldSelectorOrig: null,
    fieldSelector: null,

    updatedRstField: null,
    
    use_remote: false,
    
    _record_type: 'all', // filter based on record type
    
    //
    //
    //    
    _init: function() {

        this.options.default_palette_class = 'ui-heurist-design';
        
        //allow select existing fieldtype by typing
        //or add new field both to defDetailTypes and defRecStructure
        //this.options.newFieldForRtyID = 0; 
        
        this.options.innerTitle = false;
        
        this.options.layout_mode = 'short';
        this.options.use_cache = true;

        if(this.options.import_structure){
            this.options.use_cache = true;
            this.use_remote = true; //use HEURIST4.remote.detailtypes for import structures
        }

       
        
       
        this.options.edit_need_load_fullrecord = false;
        this.options.height = 640;
        this.options.edit_width = 850;
        this.options.edit_height = 640;

        if(this.options.edit_mode=='editonly'){
            this.options.edit_mode = 'editonly';
            this.options.select_mode = 'manager';
            this.options.layout_mode = 'editonly';
            this.options.width = 850;
            this.options.height = 680;
        }else
        //for selection mode set some options
        if(this.options.select_mode!='manager'){
            this.options.width = (isNaN(this.options.width) || this.options.width<750)?750:this.options.width;                       
            this.options.edit_mode = 'popup';    
           
        }
        
        if(this.options.select_mode=='select_multi' || this.options.select_mode=='select_single'){ //special compact case
            this.options.width = 440;
        }
        

        this._super();

        if(this.options.isFrontUI){
            
            this.searchForm.css({padding:'10px 5px 0 10px'});
            
            window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, {'background-color':'#fff', opacity:1});   
        
            //add fields group editor
            this.element.addClass('ui-suppress-border-and-shadow');
            
            this.element.find('.ent_wrapper:first').addClass('ui-dialog-heurist').css('left',228);
            
            this.fieldtype_groups = $('<div data-container="ABBBBBB">').addClass('ui-dialog-heurist')
                .css({position: 'absolute',top: 0, bottom: 0, left: 0, width:220, overflow: 'hidden'})
                .appendTo(this.element);
                
                
            if(this.options.select_mode=='manager'){ //adjust table widths
                let that = this;
                window.hWin.HAPI4.addEventListener(this, window.hWin.HAPI4.Event.ON_WINDOW_RESIZE, 
                    function(){
                        if(that.recordList && that.recordList.resultList('instance')){
                            that.recordList.resultList('applyViewMode','list', true);
                            that.recordList.resultList('refreshPage');
                        }
                    });
            }    
                

        }        
    
    },
        
    _destroy: function() {

        window.hWin.HAPI4.removeEventListener(this, window.hWin.HAPI4.Event.ON_WINDOW_RESIZE);        
        
        if(this.fields_list_div){
            this.fields_list_div.remove();
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

            if(this.options.isdialog && this._as_dialog != null && this.options.parent_dialog != null){ // move popup position
                this._as_dialog.dialog('option', 'position', {my: 'left+130 top+50', at: 'left top', of: this.options.parent_dialog});
            }

            return;
        }
        
        let search_top = this.options.select_mode == 'select_multi' ? 100 : 80;

        this.searchForm.css({'min-width': '780px', padding:'6px', height:search_top});
        this.recordList.css({'min-width': '780px', top:search_top});
        this.searchForm.parent().css({'overflow-x':'auto'});

        let that = this;
        
        if(this.options.select_mode=='manager'){
            
            this.recordList.resultList({ 
                    show_toolbar: false,
                    pagesize: 99999,
                    list_mode_is_table: true,
                    rendererHeader:function(){ return that._recordListHeaderRenderer(); },
            
                    draggable: function(){
                        
                        that.recordList.find('.rt_draggable > .item').draggable({ // 
                                    revert: 'invalid',
                                    helper: function(){ 
                                        return $('<div class="rt_draggable ui-drag-drop" recid="'+
                                            $(this).parent().attr('recid')
                                        +'" style="width:300;padding:4px;text-align:center;font-size:0.8em;background:#EDF5FF"'
                                        +'>Drag and drop to group item to change base field group</div>'); 
                                    },
                                    zIndex:100,
                                    appendTo:'body',
                                    containment: 'window',
                                    scope: 'dtg_change'
                                    //delay: 200
                                });   
                    }
            });
            
            
            if(this.options.isFrontUI) {
                this._on( this.recordList, {        
                        "resultlistondblclick": function(event, selected_recs){
                                    this.selectedRecords(selected_recs); //assign
                                    
                                    if(window.hWin.HEURIST4.util.isRecordSet(selected_recs)){
                                        let recs = selected_recs.getOrder();
                                        if(recs && recs.length>0){
                                            let recID = recs[recs.length-1];
                                            this._onActionListener(event, {action:'edit',recID:recID}); 
                                        }
                                    }
                                }
                        });

            }

            this.coverMessage();			
        
        this.options.onInitCompleted =  function(){
            
            if(that.options.isFrontUI){
                let rg_options = {
                     isdialog: false, 
                     isFrontUI: true,
                     container: that.fieldtype_groups,
                     title: 'Base field groups',
                     layout_mode: 'short',
                     select_mode: 'manager',
                     reference_dt_manger: that.element,
                     onSelect:function(res){
                         
                        if(window.hWin.HEURIST4.util.isRecordSet(res)){
                             
                            if(!that.getRecordSet()){
                                that._loadData( true );
                            }
                             
                            res = res.getIds();                     
                            if(res && res.length>0){
                                //filter by field group
                                that.options.dtg_ID = res[0];
                                that.searchForm.searchDefDetailTypes('option','dtg_ID', res[0])
                            }
                        }
						 
						if(that.options.select_mode == 'manager'){ // reset search to default for manager

                            that.searchForm.find('#input_search').val('');
                            that.searchForm.find('#input_search_type').val('any');
                            that.searchForm.find('#chb_show_all_groups').prop('checked', false);
                            that.searchForm.find('#input_sort_type').val('name');

                            that.searchForm.searchDefDetailTypes('startSearch');
                        }
                     },
                     add_to_begin: true
                };
                                                                             
                window.hWin.HEURIST4.ui.showEntityDialog('defDetailTypeGroups', rg_options);        
            }else{
                that._loadData(true);    
            }
        }

        }else if(this.options.select_mode=='select_multi'){
            
            this.recordList.resultList({ 
                    show_toolbar: true,
                    view_mode:'list',
                    show_viewmode: false,
                    list_mode_is_table: true});
                   
            this.searchForm.css({padding:'10px', 'min-width': '420px'});
            this.recordList.css({'min-width': '420px'});
         
            this.visible_fields = ['name','type','usedin'];
            
            this.recordList.resultList('fullResultSet', $Db.dty());   
            
            that._loadData(true);
        }
        
        if(this.use_remote){

            this.recordList.resultList( this.options.recordList );

            that._loadData(true);

            that.searchForm.css({'height':0});
            that.recordList.css({'top':0});
        }
        
        // init search header
        this.searchForm.searchDefDetailTypes(this.options);
        
        
        if(this.options.use_cache){
            this._on( this.searchForm, {
                "searchdefdetailtypesonfilter": this.filterRecordList,
                "searchdefdetailtypesonadd": function() {
                    this._onActionListener(null, 'add');
                },
                "searchdefdetailtypesonrectypefilter": function(event, data){

                    let subset = this._cachedRecordset.getSubSetByIds($Db.rst(data.rtyID).getIds());

                    this.recordList.resultList('updateResultSet', subset, null);
                    this.filterRecordList(event, null, subset);
                },
                "searchdefdetailtypesonimport": this.importDetailTypes
            });
                
        }else{
            window.hWin.HEURIST4.msg.sendCoverallToBack();
            
            this._on( this.searchForm, {
                "searchdefdetailtypesonresult": this.updateRecordList,
                "searchdefdetailtypesonadd": function() { this.addEditRecord(-1); },
                "searchdefdetailtypesonimport": this.importDetailTypes
            });
            
        }

        return true;
    },            
    
    //
    // invoked after all elements are inited 
    //
    _loadData: function(is_first){
        
        let that = this;
        if(this.use_remote && this.options.import_structure){

            this.recordList.resultList('resetGroups');
            this.recordList.resultList('clearAllRecordDivs',null, '<div style="padding: 10px;cursor: wait;">'
                + '<span class="ui-icon ui-icon-loading-status-balls"></span> &nbsp;&nbsp;'
                + 'Loading detail fields from template database</div>');
            this.recordList.resultList('option', 'empty_remark', '');
            
            //@todo - obtain definitions in new format
            //get definitions (in old format) from REMOTE database
            window.hWin.HAPI4.SystemMgr.get_defs( //only basefield names
                {detailtypes:'all', mode:2, remote:that.options.import_structure.database_url}, function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){

                        if(!window.hWin.HEURIST4.remote){
                            window.hWin.HEURIST4.remote = {};
                        }

                        window.hWin.HEURIST4.remote.detailtypes = response.data.detailtypes;

                        that._cachedRecordset = that.getRecordsetFromRemote(response.data.detailtypes, true);
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                }
            );
        }else
        if(this.options.use_cache){
                this.updateRecordList(null, {recordset:$Db.dty()});
                if(is_first!==true)this.searchForm.searchDefDetailTypes('startSearch');
        }    
        
    },
    
    visible_fields: ['dtyid','ccode','edit','name','type','usedin','status','description', 'show'], //'usedin','show','ccode','group',       
    //----------------------
    //
    //
    //
    _recordListHeaderRenderer: function(){

        let max_width = this.recordList.find('.div-result-list-content').width() - 23;
        let used_width = 0;
        
        function fld2(col_width, value, style){
            
            if(!style) style = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                
                col_width = col_width - 1;
                used_width = used_width + col_width + 4;
                style += (';width:'+col_width+'px'); 
                
            }
            if(style!='') style = 'style=";'+style+'"';
            
            if(!value){
                value = '';
            }
            return '<div class="item truncate" '+style+'>'+window.hWin.HEURIST4.util.htmlEscape(value)+'</div>';
        }
        
        let html = '';
        let fields = this.visible_fields;
        
        let i = 0;
        for (;i<fields.length;i++){
           switch ( fields[i] ) {
                case 'dtyid': html += fld2(30,'ID','text-align:center'); break;
                case 'ccode': 
                    html += fld2(80,'ConceptID','text-align:center');     
                    break;
                case 'group': 
                    html += fld2(30,'Group','text-align:center');
                    break;
                case 'edit':  
                    html += fld2(30,'Edit','text-align:center');
                    break;
                case 'name':  
                    html += '$$NAME$$'; 
                    break;
                case 'type': 
                    html += fld2(80,'Type','text-align:center');
                    break;
                case 'description':  
                    html += '$$DESC$$'; //html += fld2(null,'Description',''); 
                    break;
                case 'show': 
                    html += fld2(30,'Show','text-align:center');
                    break;
                case 'usedin': 
                    html += fld2(30,'RecTypes','text-align:center');
                    break;
                case 'status': 
                    html += fld2(30,'Del','text-align:center');
                    break;
            }
        }

        let name_width = 200;

        let w_desc = max_width-used_width-name_width;
        if(w_desc<30) w_desc = 30;

        html = html.replace('$$DESC$$',fld2(w_desc, 'Description', 'text-align:left'));

        html = html.replace('$$NAME$$',fld2(name_width, 'Name', 'text-align:left'));

        return html;
    },
    
    //----------------------
    //
    //
    //
    _recordListItemRenderer:function(recordset, record){

        const that = this;
        
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, col_width, value, style){
            
            if(!style) style = '';
            if(col_width>0){
                style += (';max-width:'+col_width+'px;min-width:'+col_width+'px');
            }

            if(style!='') style = 'style=";'+style+'"';  //padding:0px 4px
            
            if(!value){
                value = recordset.fld(record, fldname);
            }
            if(fldname == 'dty_Name' && Number.isInteger(+that._record_type) && that._record_type > 0){
                // Use name used in record type
                let recID = fld('dty_ID');
                value = $Db.rst(that._record_type, recID, 'rst_DisplayName');
            }
            return '<div class="item truncate" '+style+'>'+window.hWin.HEURIST4.util.htmlEscape(value)+'</div>';
        }

        // Skip items in the Trash group, unless in manager mode
        let isTrash = (recordset.fld(record, 'dty_DetailTypeGroupID') == $Db.getTrashGroupId('dtg'));
        if(isTrash && this.options.select_mode!='manager'){
            return '';
        }
        
        let recID = fld('dty_ID');

        let html = '';
        
        let fields = this.visible_fields;
        
        function __action_btn(action,icon,title){
            return '<div class="item" style="min-width:30px;max-width:30px;text-align:center;"><div title="'+title+'" '
                    +'class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" '
                    +'role="button" aria-disabled="false" data-key="'+action+'" style="height:18px;">'
                    +     '<span class="ui-button-icon-primary ui-icon '+icon+'"></span>'
                    + '</div></div>'            
        }

        let max_width = this.recordList.find('.div-result-list-content').width() 
                            - ((this.options.select_mode=='select_multi') ?40:33);
        let used_width = 330;

        let name_width = 200;

        let w_desc = max_width - used_width - name_width + 10;
        if(w_desc<30) w_desc = 30; 

        let grayed = '';
        let i = 0;
        for (;i<fields.length;i++){
            
            switch ( fields[i] ) {
                case 'dtyid': html += fld2('dty_ID',30,null,'text-align:right'); break;
                case 'ccode': 
                    html += ('<div class="item truncate" style="min-width:80px;max-width:80px;text-align:center">'
                            +$Db.getConceptID('dty',recID,true)+'</div>');
                    break;
                case 'group': 
                    html += __action_btn('group','ui-icon-carat-d','Change group');
                    break;
                case 'edit':  
                    html += __action_btn('edit','ui-icon-pencil','Click to edit base field');
                    break;
                case 'name':  html += fld2('dty_Name',name_width); break;
                case 'type':  
                
                    html += fld2('', 80, $Db.baseFieldType[recordset.fld(record,'dty_Type')], 'font-size:smaller'); 
                    break;
                case 'description':  
                    html += fld2('dty_HelpText',null,null,
                        'min-width:'+w_desc+'px;max-width:'+w_desc+'px;font-style:italic;font-size:smaller'); break;
                case 'show': 
                
                    if(recordset.fld(record, 'dty_ShowInLists')==1){
                        html += __action_btn('hide_in_list','ui-icon-check-on','Click to hide in dropdowwn lists and trees');    
                    }else{
                        html += __action_btn('show_in_list','ui-icon-check-off','Click to show in dropdown lists and trees');
                        grayed = 'background:lightgray';
                    }
                    break;
                case 'usedin': 
                    html += __action_btn('usedin','ui-icon-circle-b-info','Click for the record types in which this base field is used');
                    break;
                case 'status': 
                    
                    if(recordset.fld(record, 'dty_Status')=='reserved'){
                        html += __action_btn('','ui-icon-lock','Status: Reserved');
                    }else{
                        html += __action_btn('delete','ui-icon-delete','Status: Open. Click to delete this base field');
                    }    
                
                    break;
            }    
        }

        html = '<div class="recordDiv rt_draggable white-borderless" recid="'+recID+'" style="display:table-row;height:28px;'+grayed+'">'
                    + '<div class="recordSelector item"><input type="checkbox" /></div>'
                    + html+'</div>';

        return html;
        
    },
    
    //
    //
    //
    _onActionListener:function(event, action){
        
        
        if(action && action.action=='delete'){
            
            const usage = $Db.rst_usage(action.recID);
            if(usage && usage.length>0)
            {            

                let sList = '';
                for(let i=0; i<usage.length; i++){
                    const rty_Name = $Db.rty(usage[i],'rty_Name');
                    if(rty_Name){ //it may already deleted
                        sList += ('<a href="#" data-rty_ID="'+usage[i]+'">'+$Db.rty(usage[i],'rty_Name')+'</a><br>');
                    }
                }
                if(sList!=''){ 
                    let sUsage = '<div><b>Warning</b><br><br><b>'+$Db.dty(action.recID,'dty_Name')
                            +'</b> is used in the following record types:<br><br>'
                            +sList
                            +'<br><br>'
                            +'You have to either delete the field from the record type, '
                            +'or delete the record type<br>(it may not be possible or desirable to delete the record type)</div>';

                    let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(sUsage, null, {title:'Warning'}, 
                                {default_palette_class:this.options.default_palette_class});        
                    
                    this._on($dlg.find('a[data-rty_ID]'),{click:function(e){
                        //edit structure
                        window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                            {new_record_params:{RecTypeID: $(e.target).attr('data-rty_ID')}, edit_structure:true});
                        
                        return false;                    
                    }});
                }
                return;
            }
        }
        
        
        let isResolved = this._super(event, action);

        if(!isResolved){
            
            let recID = 0;
            let target = null;

            if(action && action.action){
                recID =  action.recID;
                target = action.target;
                action = action.action;
            }
            if(recID>0){
                
                if(action=='show_in_list' || action=='hide_in_list'){
                    
                    
                    let newVal = (action=='show_in_list')?1:0;
                    this._saveEditAndClose({dty_ID:recID, dty_ShowInLists:newVal });
                    
                }else if(action=='usedin'){
                    
                    //show selectmenu with list of recordtypes where this field is used
                    if(Math.abs(this.fieldSelectorLast)!=recID){
                        
                        this.fieldSelectorLast   = recID;
                        const usage = $Db.rst_usage(recID);
                        let options = [];
                        if(usage && usage.length>0){
                            for(let i=0; i<usage.length; i++){
                                const rty_ID = usage[i];
                                const rty_Name = $Db.rty(rty_ID, 'rty_Name');
                                if(rty_Name){
                                    options.push({key:rty_ID, title:$Db.rty(rty_ID, 'rty_Name')});
                                }
                            }
                        }   
                        if(options.length==0){
                            this.fieldSelectorLast = -this.fieldSelectorLast;
                            window.hWin.HEURIST4.msg.showMsgFlash('Field is not in use',1000);
                            return;
                        }

                        if(!this.fieldSelector){
                            this.fieldSelectorOrig = document.createElement("select");    
                            $(this.fieldSelectorOrig).appendTo(this.element);
                            window.hWin.HEURIST4.ui.fillSelector(this.fieldSelectorOrig, options);
                            this.fieldSelector = window.hWin.HEURIST4.ui.initHSelect(this.fieldSelectorOrig, false);
                            
                            let menu = this.fieldSelector.hSelect( "menuWidget" );
                            menu.css({'max-height':'350px'});                        
                                this.fieldSelector.hSelect({change: function(event, data){

                                    //edit structure     
                                    let new_record_params = {RecTypeID: data.item.value};
                                    window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                                        {new_record_params:new_record_params, edit_structure:true});
    
                            }});
                            
                        }else{
                            $(this.fieldSelectorOrig).empty();
                            window.hWin.HEURIST4.ui.fillSelector(this.fieldSelectorOrig, options);
                            this.fieldSelector.hSelect('refresh');
                        }
                    }
                    if(this.fieldSelectorLast>0){
                        this.fieldSelector.hSelect('open');
                    this.fieldSelector.hSelect('widget').hide(); //hide selector
                        this.fieldSelector.val(-1);
                        this.fieldSelector.hSelect('menuWidget')
                            .position({my: "left top", at: "left+10 bottom-4", of: $(target)})
                            .show();                        
                        this.fieldSelector.hSelect('hideOnMouseLeave', $(target));                    
                    }
                    
                }
                
            }
        }

    },
       
    //
    // can remove group with assigned fields
    //     
    _deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this.deleted_from_group_ID = $Db.dty(this._currentEditID,'dty_DetailTypeGroupID');
            
            this._super(); 
        }else{
            this.deleted_from_group_ID = 0;
            
            let usage = $Db.rst_usage(this._currentEditID);
            if(usage && usage.length>0){ 
                window.hWin.HEURIST4.msg.showMsgFlash('Field in use in '+usage.length+' record types. Can\'t remove it');  
                return;                
            }
            
            let that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this field type?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'},
                {default_palette_class:this.options.default_palette_class});        
        }
        
    },
    
    //
    //
    //
    _afterDeleteEvenHandler: function(recID){
        
            this._super();

            this.searchForm.searchDefDetailTypes('startSearch');
            this._triggerRefresh('dty');
            
           
    },
    

    //-----
    //
    // Set group ID value for new field type
    // and perform some after load modifications (show/hide fields,tabs )
    //
    _afterInitEditForm: function(){

        this._super();
        
        if(this._toolbar){
            this._toolbar.find('.btnRecSave').button({label:window.hWin.HR(this._currentEditID>0?'Save':'Create New Field')});
        }
        let dty_DetailTypeGroupID = this.options.dtg_ID;
        
        if(!(this._currentEditID>0)){ //insert       
            
            if(!(dty_DetailTypeGroupID>0)){ //take first from list of groups
                dty_DetailTypeGroupID = $Db.dtg().getOrder()[0];                
            }

            this._editing.setFieldValueByName('dty_DetailTypeGroupID', dty_DetailTypeGroupID);
           
           
        }else{
            
            let ele = this._editing.getFieldByName('dty_ID');
            ele.find('div.input-div').html(this._currentEditID+'&nbsp;&nbsp;<span style="font-weight:normal">Code: </span>'
                                    +$Db.getConceptID('dty',this._currentEditID, true));
        }

        let name_field = this._editing.getInputs('dty_Name');   // Base Field Name input
        let main_container = $(this._editing.getContainer()[0]).find('fieldset').get(0);

        if(window.hWin.HEURIST4.util.isArrayNotEmpty(name_field)){  

            if(this._currentEditID<=0){ // Check that a new field is being defined

                if(!this.options.newFieldType){
                    if(this.options.create_sub_record){
                        this._setupSubRecordField();
                        this.options.newFieldType = 'resource';
                    }else
                    if(this.options.newFieldForRtyID > 0){ // Ensure that the new field is for a specific rectype
                        $('<h2 style="margin-block:0;margin-bottom:0.2em">Choose existing base field(s)</h2>'
                            + '<div class="heurist-helper2" style="font-size:0.95em">Rather than defining every field from scratch, you can pick some frequently used pre-defined fields from the existing Base fields.<br>'
                            + 'However, please read the following notes carefully.</div>'
                            + '<span id="btn-basefields-list" style="margin:1em 0 1em 3em"></span>'
                            + '<div class="heurist-helper2" style="font-size:0.95em">The base fields chosen should have a <span style="text-decoration:underline">similar sense of meaning</span>, '
                            + 'e.g. use <em>Start date</em> for <em>Birth date</em>, <em>Creator</em> for <em>Author</em>, <em>Short description</em><br>'
                            + 'for <em>Abstract</em>, <em>Extended description</em> for <em>Notes</em>. You can rename the fields to what you actually want once selected - the new name applies<br>'
                            + 'to the current record type only (the base field retains its name).<br><br>'

                            + '<span style="text-decoration:underline">Do not completely redefine a base field</span> for a different purpose than it appears to be intended for, for instance redefining Family name as<br>'
                            + 'Street, Length as Count, or Format as Condition. Significant change to the meaning of a field may later lead to confusion.<br>'
                            + 'Fields which use the same base field will reference the same vocabulary (for term-list dropdowns and relationship type) or the same target<br>'
                            + 'record types (for record pointers and relationships) - you cannot change the vocabulary or target record types for one without changing it<br>'
                            + 'for all the others.</div><hr style="width:80%;margin:1em 10em 1em 0;"/>'

                            + '<h2 style="margin-block:0;margin-bottom:0.2em">Create a new field</h2>'
                            + '<div class="heurist-helper2" style="font-size:0.95em">If you can\'t find a suitable base field, type a new name. This will create a new base field and use it to create a new field in this record type.<br>'
                            + 'It is a good idea to use a rather generic name and description so you can re-use the base field in other record types<br>'
                            + 'and then customise the field appropriately for this record type.</div><br>').prependTo(main_container);

                        let btnBasefieldsList = $(main_container).find('span#btn-basefields-list').button({label: 'Choose base fields'})
                        let rty_ID = this.options.newFieldForRtyID;
                        let that = this;

                        function multiFieldPopup(){ // load multi-field popup

                            that._editing.setModified(0);

                            let sURL = window.hWin.HAPI4.baseURL + "hclient/widgets/entity/popups/selectMultiFields.html?&rtyID="+rty_ID;
                                    
                            window.hWin.HEURIST4.msg.showDialog(sURL, {
                                "close-on-blur": false,
                                title: 'Insert Base Fields',
                                window: window.hWin,
                                height: '735px',
                                width: '1020px',
                                padding: 0,
                                default_palette_class: 'ui-heurist-design',
                                callback: function(context) {
                                    if(!window.hWin.HEURIST4.util.isempty(context)) {

                                        let rst_fields = {
                                            rst_RequirementType: that._editing.getValue('rst_RequirementType')[0], 
                                            rst_MaxValues: that._editing.getValue('rst_MaxValues')[0], 
                                            rst_DisplayWidth: that._editing.getValue('rst_DisplayWidth')[0] 
                                        };

                                        that._trigger("multiselect", null, {selection:context.reverse(), rst_fields:rst_fields}); // handler in manageDefRecStructure

                                        that.closeDialog( true ); // close base field definition popup
                                    }
                                }
                            });
                        }

                        this._on(btnBasefieldsList, 
                            {'click': function(){ // warn the user about the loss of popup data
                                let isChanged = (this._editing.getFieldByName('dty_Type').find('.ui-selectmenu-text').text() != 'Select...'
                                                    || (!window.hWin.HEURIST4.util.isempty($(this._editing.getInputs('dty_HelpText')[0]).val())
                                                    && !window.hWin.HEURIST4.util.isempty($(this._editing.getInputs('dty_Name')[0]).val()))
                                                ); // Check if values have been placed/selected in required fields

                                if(isChanged){

                                    let $dlg, buttons = {};
                                    buttons['Yes'] = function(){ // close message, open multi-field popup
                                        $dlg.dialog('close'); 
                                        multiFieldPopup();
                                    }; 
                                    buttons['No'] = function(){
                                        $dlg.dialog('close'); 
                                    };

                                    $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                                        'Items entered into this form may be lost upon completing this process, would you still like to proceed?',
                                        buttons,
                                        {title:'Confirm',yes:'Yes',no:'No'}
                                    );

                                }else{ // in the odd chance that isChanged is false, I don't think it can be in this case
                                    multiFieldPopup();
                                }
                            }
                        });
                    }
                }

                if(!window.hWin.HEURIST4.util.isempty(this.options.newFieldName)){

                    name_field[0].val(this.options.newFieldName);

                    let desc_field = this._editing.getInputs('dty_HelpText');
                    if(desc_field.length > 0){
                        desc_field[0].val(this.options.newFieldName);
                    }

                    name_field[0].trigger('change');
                }
            }
        }

        //fill init values of virtual fields
        //add lister for dty_Type field to show hide these fields
        let elements = this._editing.getInputs('dty_Type');
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(elements)){
            
            if(this.options.newFieldType){

                let el = $(elements[0])[0];
                const _dty_Type = this.options.newFieldType;
                $(el).empty();
                window.hWin.HEURIST4.ui.addoption(el, _dty_Type,  $Db.baseFieldType[_dty_Type]);
                el.disabled = true;
                if($(el).hSelect("instance")!=undefined){
                    $(el).hSelect("refresh"); 
                }
                this._onDataTypeChange(_dty_Type);
                
                
                if(this.options.newFieldResource>0){
                    this._editing.setFieldValueByName('dty_PtrTargetRectypeIDs', this.options.newFieldResource, true);
                }
                
        
            }else
            if(this._currentEditID>0)
            {
                //limit list and disable in case one option
                let el = $(elements[0])[0];
                const _dty_Type = $Db.dty(this._currentEditID,'dty_Type');
                
                $(el).empty();
                el.disabled = false;
                
                window.hWin.HEURIST4.ui.addoption(el, _dty_Type,  $Db.baseFieldType[_dty_Type]);

                if(_dty_Type=='float' || _dty_Type=='date'){
                    window.hWin.HEURIST4.ui.addoption(el, 'freetext',  $Db.baseFieldType['freetext']);
                }else if(_dty_Type=='freetext'){
                    window.hWin.HEURIST4.ui.addoption(el, 'blocktext',  $Db.baseFieldType['blocktext']);
                    window.hWin.HEURIST4.ui.addoption(el, 'date',  $Db.baseFieldType['date']);
                    window.hWin.HEURIST4.ui.addoption(el, 'float',  $Db.baseFieldType['float']);
                }else if(_dty_Type=='blocktext'){
                    window.hWin.HEURIST4.ui.addoption(el, 'freetext',  $Db.baseFieldType['freetext']);
                    window.hWin.HEURIST4.ui.addoption(el, 'date',  $Db.baseFieldType['date']);
                    window.hWin.HEURIST4.ui.addoption(el, 'float',  $Db.baseFieldType['float']);
                }else{
                    el.disabled = true;
                }
                
                if($(el).hSelect("instance")!=undefined){
                    $(el).hSelect("refresh"); 
                }

                this._on( $(elements[0]), {    
                    'change': function(event){
                            let dt_type = $(event.target).val();
                            this._onDataTypeChange(dt_type);
                    }
                });
            }else {  // setup record type button and handler for selector
                
                let that = this;

                let ele = this._editing.getFieldByName('dty_Type');  
                ele = ele.find('.input-div');

                if(ele.find('select').hSelect("instance")){ 
                    let len = ele.find('select').find('option').length;
                    $(ele.find('select').find('option')[len-1]).attr({ // select... is listed last
                        "disabled": true,
                        "selected": true,
                        "hidden": true
                    }).hide();

                    ele.find('select').hSelect("refresh");
                }                    

                this._on(ele.find('select'), {
                    'change':function(event){
                        let new_dty = $(event.target).val();

                        if(!window.hWin.HEURIST4.util.isempty(new_dty)){
                            that._onDataTypeChange(new_dty);
                        }
                    }
                });
                
                this.set_detail_type_btn = $('<button>')
                    .button({icon:'ui-icon-circle-b-help'})
                    .css({'min-width':'25px','margin-left':'10px','padding-left':'5px','padding-right':'5px','background':'#523365','color':'white'});
                this.set_detail_type_btn.appendTo(ele);

                $('<span style="margin-left:5px;font-style:italic">guided choice</span>').appendTo(ele);
                
                ele.find('.btn_input_clear').appendTo(ele);
                
                this._on( this.set_detail_type_btn, {    
                    'click': function(event){

                        let dt_type = this._editing.getValue('dty_Type')[0];
                
                        let $dlg, buttons = [
                            {text:window.hWin.HR('Cancel'),
                                //class:'btnRecCancel',
                                css:{'float':'right',margin:'.5em .4em .5em 0px'},  
                                click: function() { $dlg.dialog( "close" ); }},
                            {text:window.hWin.HR('Use this field type'),
                                css:{'float':'right',margin:'.5em .4em .5em 0px'},  
                                class: 'ui-button-action',
                                click: function() { 
                                    
                                    let dt_type_new = $dlg.find('input[name="ft_type"]:checked').val();
                                    
                                    if(!window.hWin.HEURIST4.util.isempty(dt_type_new)) {
                                        if(((dt_type==="resource") || (dt_type==="relmarker") || 
                                            (dt_type==="enum"))  && dt_type!==dt_type_new)
                                        {

                                            window.hWin.HEURIST4.msg.showMsgDlg("If you change the type to '"
                                                + $Db.baseFieldType[dt_type_new] 
                                                + "' you will lose all your settings for type '"   //vocabulary 
                                                + $Db.baseFieldType[dt_type]+
                                                "'.\n\nAre you sure?",                                            
                                                function(){   
                                                    that._onDataTypeChange(dt_type_new);                                                   
                                                }, {title:'Change type for field',yes:'Continue',no:'Cancel'},
                                                {default_palette_class:that.options.default_palette_class});                                                
                                        }else{
                                            that._onDataTypeChange(dt_type_new);                                                   
                                        }                            
                                    }
                        
                                    $dlg.dialog( "close" ); 
                                }}
                        ];                
                        
                        
                        $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
                            +"hclient/widgets/entity/popups/selectFieldType.html?t="+(new Date().getTime()), 
                            buttons, 'Select data type of field', 
                            {  container:'detailtypes-type-popup',
                                width:800,
                                height:620,
                                default_palette_class: this.options.default_palette_class,
                                close: function(){
                                    $dlg.dialog('destroy');       
                                    $dlg.remove();
                                },
                                open: function(){
                                    $dlg.css({padding:0});
                                    
                                    window.hWin.HEURIST4.ui.initHelper( {button:$dlg.find('#hint_more_info1'), 
                                                    title:'Field data type: Record pointer', 
                                                    url: window.hWin.HRes('field_data_types #resource'),
                                                    position:{ my: "left top", at: "left top", of:$dlg}, no_init:true} ); 
                                    window.hWin.HEURIST4.ui.initHelper( {button:$dlg.find('#hint_more_info2'), 
                                                    title:'Field data type: Relationship marker', 
                                                    url: window.hWin.HRes('field_data_types #relmarker'),
                                                    position:{ my: "left top", at: "left top", of:$dlg}, no_init:true} ); 
                                    
                                }  //end open event
                        });

                }});
                
            }

            $(elements[0]).trigger('change'); //trigger
        }

        elements = this._editing.getInputs('dty_Name');
        this._on( $(elements[0]), {
                keypress: window.hWin.HEURIST4.ui.preventChars} );
        
        if(this.options.newFieldForRtyID>0){

            //disable all fields except field name
            this._on( $(elements[0]), {
                keyup: this._onFieldAddSuggestion });

            let depended_fields = this._editing.getFieldByClass('newFieldForRtyID');
            for(let idx in depended_fields){
                $(depended_fields[idx]).show();
            }

            //add extra click functionalities to save buttons
            this._toolbar.find('.btnSaveExt').show().on('click', function(){
                window.hWin.HAPI4.save_pref('edit_rts_open_formlet_after_add', 1);
            }); 

            // this is used to determine whether the user will customise the new field after creation
            window.hWin.HAPI4.save_pref('edit_rts_open_formlet_after_add', 0);
        }

        this.getUiPreferences();
        let ishelp_on = (this.usrPreferences['help_on']==true || this.usrPreferences['help_on']=='true');
        let ele = $('<div style="position:absolute;right:6px;top:4px;"><label><input type="checkbox" '
                        +(ishelp_on?'checked':'')+'/>explanations</label></div>').prependTo(this.editForm);
        this._on( ele.find('input'), {change: function( event){
            let ishelp_on = $(event.target).is(':checked');
            this.usrPreferences['help_on'] = ishelp_on;
            window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, this.editForm, '.heurist-helper1');
            window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, this.editForm, '.heurist-helper2');
        }});
        
        window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, this.editForm, '.heurist-helper1');
        window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, this.editForm, '.heurist-helper2');
        
        this._adjustEditDialogHeight();
    },    
    
    //
    //
    //
    _onDataTypeChange: function(dt_type)
    {
           /*
           let ele = this._editing.getFieldByName('dty_Type');
           ele.editing_input('setValue', dt_type);
           */
           if(this.set_detail_type_btn){
               let elements = this._editing.getInputs('dty_Type');
               $(elements[0]).val( dt_type );
               if($(elements[0]).hSelect("instance")!=undefined){
                   $(elements[0]).hSelect("refresh"); 
               }
               
               let ele = this._editing.getFieldByName('dty_Type');  
               ele.editing_input('showErrorMsg',null);
           }
           
        
           //hide all 
           let depended_fields = this._editing.getFieldByValue("rst_Class","[not empty]");
           for(let idx in depended_fields){
               $(depended_fields[idx]).hide();
           }
           //show specific
           depended_fields = this._editing.getFieldByClass(dt_type);
           for(let idx in depended_fields){
               $(depended_fields[idx]).show();
           }
           if(dt_type=='enum' || dt_type=='relmarker'){
                let ele = this._editing.getFieldByName('dty_Mode_enum');  
                this._activateEnumControls(ele);
           }else if(dt_type=='relationtype'){
                let ele = this._editing.getFieldByName('dty_Mode_enum');  
                this._activateRelationTypeControls(ele);
           }
           if(this.options.newFieldForRtyID>0){
                depended_fields = this._editing.getFieldByClass('newFieldForRtyID');
                for(let idx in depended_fields){
                    $(depended_fields[idx]).show();
                }
                let ele = this._editing.getFieldByName('rst_DisplayWidth');
                if(dt_type=='freetext' || dt_type=='blocktext' || dt_type=='float'){
                    ele.editing_input('setValue', dt_type=='float'?10:(dt_type=='freetext'?60:100));
                    ele.show();  
                }else{
                    if(dt_type=='enum'){
                        ele.editing_input('setValue', 100);
                    }
                    ele.hide();
                }
           }
    },
    
    //
    // show dropdown for field suggestions to be added
    //
    _onFieldAddSuggestion: function(event){
        
        let input_name = $(event.target);
        
       
        
        if(this.fields_list_div == null){
            //init for the first time
            this.fields_list_div = $('<div class="list_div" '
                +'style="z-index:999999999;height:auto;max-height:200px;padding:4px;cursor:pointer;overflow-y:auto"></div>')
                .css({border: '1px solid rgba(0, 0, 0, 0.2)', margin: '2px 0px', background:'#F4F2F4'})
                .appendTo(this.element);
            this.fields_list_div.hide();
            
            this._on( $(document), {click: function(event){  //click outside - hide list 
               if($(event.target).parents('.list_div').length==0) { 
                    this.fields_list_div.hide(); 
               };
            }});
        }
        
      
        //find base field to suggest
        if(input_name.val().length>2){
           
            let rty_ID = this.options.newFieldForRtyID; 
            let field_name, field_type;
                
            this.fields_list_div.empty();  
            let is_added = false;
            
            let that = this;
            //add current value as first
            let first_ele = $('<div class="truncate"><b>'+input_name.val()+' [new]</b></div>').appendTo(this.fields_list_div)
                            .on('click', function(event){
                                window.hWin.HEURIST4.util.stopEvent(event);
                                that.fields_list_div.hide(); 
                               
                            });

                            
            //list of fields that are already in record type
            let aUsage = $Db.rst(rty_ID); //return rectype
            if(!window.hWin.HEURIST4.util.isRecordSet(aUsage)){
                aUsage = null;
            }
            
            let entered = input_name.val().toLowerCase();
                            
            //find among fields that are not in current record type
            $Db.dty().each(function(dty_ID, rec){

                field_name = $Db.dty(dty_ID, 'dty_Name');
                field_type  = $Db.dty(dty_ID, 'dty_Type');
                let sub_rec_check = !that.options.create_sub_record || (field_type == 'resource' && !window.hWin.HEURIST4.util.isempty($Db.dty(dty_ID, 'dty_PtrTargetRectypeIDs')));

                if( $Db.dty(dty_ID, 'dty_ShowInLists')!='0'
                    && field_type!='separator'
                    && (!aUsage || !aUsage.getById(dty_ID))
                    && (field_name.toLowerCase().indexOf( entered )>=0)
                    && (field_name.toLowerCase().indexOf( entered )>=0)
                    && $Db.getConceptID('dty', dty_ID) != '2-247'
                    && sub_rec_check )
                {

                    let ele;
                    if(field_name.toLowerCase()==entered.toLowerCase()){
                        ele = first_ele;
                    }else{
                        ele = $('<div class="truncate">').appendTo(that.fields_list_div);
                    }

                    is_added = true;
                    ele.attr('dty_ID',dty_ID)
                    .text(field_name+' ['+ $Db.baseFieldType[field_type] +']')
                    .on('click', function(event){
                        window.hWin.HEURIST4.util.stopEvent(event);

                        let ele = $(event.target).hide();
                        let _dty_ID = ele.attr('dty_ID');


                        if(_dty_ID>0){
                            that.fields_list_div.hide();
                            input_name.val('').trigger('focus');

                            window.hWin.HEURIST4.msg.showMsgFlash('Field added to record structure');

                           
                           
                            let rst_fields = {rst_RequirementType: that._editing.getValue('rst_RequirementType')[0], 
                                rst_MaxValues: that._editing.getValue('rst_MaxValues')[0], 
                                rst_DisplayWidth: that._editing.getValue('rst_DisplayWidth')[0] };

                            if(ele.text().includes('Numeric') && !that._editing.getFieldByName('rst_DisplayWidth').is(':visible')){
                                rst_fields['rst_DisplayWidth'] = 10;
                            }

                            let sematic_url = that._editing.getValue('dty_SemanticReferenceURL')[0];
                            if(sematic_url){
                                rst_fields['rst_SemanticReferenceURL'] = sematic_url;
                            }

                            that._trigger( "onselect", null, {selection: [_dty_ID], rst_fields:rst_fields });
                            that.closeDialog( true ); //force without warning

                        }
                    });

                }                
            });


            if(is_added){
                this.fields_list_div.show();    
                this.fields_list_div.position({my:'left top', at:'left bottom', of:input_name})
                   
                    .css({'max-width':input_name.width()+60});
            }else{
                this.fields_list_div.hide();
            }

      }else{
            this.fields_list_div.hide();  
      }

    },

    //
    //
    //
    _activateRelationTypeControls: function( ele ){

            ele.find('.header').text('Relation types:');
            ele.find('.heurist-helper1').text('Relationship Type can be any term within any vocabulary '
            +'marked as a Relationships vocabulary. You can - and should - use Relationship Marker fields '
            +'to constrain the creation of relationships between particular record types');
        
            ele = ele.find('.input-div');
            //remove old content
            ele.empty();
            
            if(ele.find('#enumVocabulary').length>0) return; //already inited
    
            this.enum_container = ele;
            
            $('<div style="line-height:2ex;padding-top:4px">'
                        +'<div id="enumVocabulary" style="display:inline-block;padding-bottom:10px">'
                            +'<select id="selPreview"></select>'
                            +'<span style="padding:5px 3px">'
                                +'<a href="#" id="show_terms_1" style="padding-left:10px">edit terms tree</a>'
                            +'</span>'
                        +'</div>'
                +'</div>').appendTo(this.enum_container);
                
            
            this._on(this.enum_container.find('#show_terms_1'),{click: this._showOtherTerms}); //manage defTerms
            this._recreateTermsPreviewSelector();
            
    },

    
    //
    //
    //
    _activateEnumControls: function( ele, full_mode ){
        
            ele = ele.find('.input-div');
            //remove old content
            ele.empty();
            
            
            if(ele.find('#enumVocabulary').length>0) return; //already inited
            
            this.enum_container = ele;
            
            $('<div style="line-height:2ex;padding-top:4px">'
                    +'<div id="enumVocabulary" style="display:inline-block;">'
                        +'<select id="selVocab" class="sel_width"></select>'
                        +'<a href="#" id="add_vocabulary_2" style="padding-left:10px">'
                            +'<span class="ui-icon ui-icon-plus"/>add vocabulary</a>'
                        +'<a href="#" id="show_terms_1" style="padding-left:10px">'
                            +'<span class="ui-icon ui-icon-pencil"/>vocabularies editor</a>'
                        +'<br><span id="termsPreview1" style="display:none;padding:10px 0px">'
                            +'<label style="width:60px;min-width:60px">Preview:&nbsp;</label><select id="selPreview"></select>'
                        +'</span>'
                    +'</div>'
            +'</div>').appendTo(this.enum_container);

                
            //create event listeneres
            this._on(this.enum_container.find('input[name="enumType"]'),{change:
                function(event){
                    if($(event.target).val()=='individual'){
                        this.enum_container.find('#enumIndividual').css('display','inline-block');
                        this.enum_container.find('#enumVocabulary').hide();
                    }else{
                        this.enum_container.find('#enumIndividual').hide();
                        this.enum_container.find('#enumVocabulary').css('display','inline-block');
                    }
                }});
            this._on(this.enum_container.find('#add_vocabulary'),{click: this._onAddVocabOrTerms});
            this._on(this.enum_container.find('#show_terms_1'),{click: this._showOtherTerms}); //manage defTerms
            this._on(this.enum_container.find('#add_vocabulary_2'),{click: this._onAddVocabulary}); //add vocab via defTerms
            this._on(this.enum_container.find('#add_terms'),{click: this._onAddVocabOrTerms});
           
            
            this._recreateTermsVocabSelector();
           
    },
    
    /**
    * _onAddVocabOrTerms
    *
    * Add new vocabulary or add child to currently selected
    */
    _onAddVocabOrTerms: function(event){
        
        let is_add_vocab = ($(event.target).attr('id')=='add_vocabulary');

        
        let term_type = this._editing.getValue('dty_Type')[0];
        let dt_name = this._editing.getValue('dty_Name')[0];

        if(term_type!="enum"){
            term_type="relation";
        }

        let vocab_id =  this.enum_container.find("#selVocab").val();
        let that = this;
        
        
        //add new term to specified vocabulary
        let rg_options = {
                 isdialog: true, 
                 select_mode: 'manager',
                 edit_mode: 'editonly',
                 height: 240,
                 rec_ID: -1,
                 onClose: function( context ){
                    if(context>0){
                        that._editing.setFieldValueByName('dty_JsonTermIDTree', context);
                    }
                    that._recreateTermsVocabSelector();                      
                    
                 }
            };
            
            if(is_add_vocab){
                  rg_options['title'] = 'Add new vocabulary';
                  rg_options['auxilary'] = 'vocabulary';
                  rg_options['suggested_name'] = dt_name+' vocab';
            }else if(vocab_id>0){
                 
                  rg_options['trm_VocabularyID'] = vocab_id;
            }else{
                  window.hWin.HEURIST4.msg.showMsgFlash('Select of add vocabulary first');          
            }
        
            
            
        window.hWin.HEURIST4.ui.showEntityDialog('defTerms', rg_options); // it recreates  
        
        
    },

    _onAddVocabulary: function(){
        this._showOtherTerms('add_new');
    },    
    
    //
    // Opens defTerms manager
    //
    _showOtherTerms: function(event){
        

        let term_type = this._editing.getValue('dty_Type')[0];

        let vocab_id;
        if(event=='add_new'){
            vocab_id = 'add_new';
        }else {
            vocab_id = (term_type=='relationtype')
                            ?this.enum_container.find("#selPreview").val()
                            :this.enum_container.find("#selVocab").val();
            vocab_id = $Db.getTermVocab(vocab_id); //get vocabulary for selected term
            
            if(term_type=='relmarker' && !(vocab_id>0)){
                //get first vocab in relationship group
                let vocab_ids = $Db.trm_getVocabs('relation');
                vocab_id = vocab_ids[0];
    /*            
                $Db.vcg().each2(function(id,rec){
                    if(rec['vcg_Domain']=='relation'){
                        
                    }
                });
    */            
            }
            
        }

        // Get field name to display in the inner header
        let fieldname = '';

        if(this._currentEditID > 0){
            fieldname = $Db.dty(this._currentEditID, 'dty_Name');
        }else{
            fieldname = this._editing.getValue('dty_Name')[0];
        }

        if(window.hWin.HEURIST4.util.isempty(fieldname)){
            fieldname = 'New Field'
        }

        let that = this;
        window.hWin.HEURIST4.ui.showEntityDialog('defTerms', 
            {isdialog: true, 
                //auxilary: vocab_id>0?'term':'vocabulary',
                selection_on_init: vocab_id,  //selected vocabulary  
                innerTitle: false,
                innerCommonHeader: $('<div><span style="margin-left:260px" title="'+ fieldname +'">Field: <b>'
                    + fieldname
                    +'</b></span> '
                    + (vocab_id>0?('<span style="margin-left:110px">This field uses vocabulary: <b>'+$Db.trm(vocab_id,'trm_Label')+'</b></span>')
                        :'. Addition of new vocabulary')
                    +'</div>'),

                width: 1200, height:700,
                vocab_type: (term_type == 'relmarker') ? 'relation' : 'enum',
                onClose: function( context ){
                    if(context>0 && vocab_id=='add_new'){ //change vocabulary for new addition only 
                        that._editing.setFieldValueByName('dty_JsonTermIDTree', context);
                    }
                    if(term_type=='relationtype'){
                        that._recreateTermsPreviewSelector(); 
                    }else{
                        that._recreateTermsVocabSelector();    
                    }

                }
        });

    },    
    
    //
    //
    //
    _recreateTermsVocabSelector: function(newval){
        
        //selected vocabulary
        let allTerms = newval>0?newval:this._editing.getValue('dty_JsonTermIDTree')[0];
        
        let term_type = this._editing.getValue('dty_Type')[0];
        
        if(term_type!='enum'){
            term_type='relation';
        }
        
        let defaultTermID = null;
        let is_vocabulary = window.hWin.HEURIST4.util.isempty(allTerms) ||
                            window.hWin.HEURIST4.util.isNumber(allTerms);
        if(is_vocabulary){
            defaultTermID = allTerms; //vocabulary
            this.enum_container.find('input[name="enumType"][value="vocabulary"]').prop('checked',true).trigger('change');
        }else{
            this.enum_container.find('input[name="enumType"][value="individual"]').prop('checked',true).trigger('change');
        }
        
        let orig_selector = this.enum_container.find("#selVocab");
        
        let opts = {topOptions:'select...', defaultTermID:is_vocabulary?defaultTermID:0};
        
        if(term_type=='relation'){
            opts.domain = term_type;
        }
        
        window.hWin.HEURIST4.ui.createVocabularySelect(orig_selector[0], opts); 

        this._off(orig_selector, 'change');
        this._on(orig_selector, {change: function(event){
            this._editing.setFieldValueByName('dty_JsonTermIDTree', $(event.target).val());
            this._editing.setFieldValueByName('dty_TermIDTreeNonSelectableIDs', '');
            this._recreateTermsPreviewSelector();
        }});
    

        
        
       
       
        this._recreateTermsPreviewSelector();
        
    },
    
    //
    //
    //
    _recreateTermsPreviewSelector: function(){

        let allTerms = this._editing.getValue('dty_JsonTermIDTree')[0];

        //remove old selector
        let preview_sel = this.enum_container.find("#termsPreview1");
        
       
       
        
        let term_type = this._editing.getValue('dty_Type')[0];
        if(term_type=='relationtype'){
            allTerms = 'relation';
        }

        if(!window.hWin.HEURIST4.util.isempty(allTerms)) {
            
            //let disTerms = this._editing.getValue('dty_TermIDTreeNonSelectableIDs')[0];  //@todo remove - is not used anymore

            //append to first preview
            let new_selector = this.enum_container.find('#selPreview');
            
            window.hWin.HEURIST4.ui.createTermSelect(new_selector[0],
                    {vocab_id:allTerms, topOptions:false, supressTermCode:true});

            preview_sel.css({'display':'inline-block'});
            
        }else{
            preview_sel.hide();
        }
        
    },

    //--------------------------------------------------------------------------
    
    
    //
    // update list after save (refresh)
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){
        
        // close on addition of new record in select_single mode    
        if(this._currentEditID<0 && 
            (this.options.selectOnSave===true || this.options.select_mode=='select_single'))
        {
                $Db.dty().addRecord(recID, fieldvalues); 
                this._selection = new HRecordSet();
                this._selection.addRecord(recID, fieldvalues);
                this._selectAndClose();
                return;    
                    
        }
        
        $Db.dty().setRecord(recID, fieldvalues); 

        //update local definitions
        this._super( recID, fieldvalues );

        this._triggerRefresh('dty');    
        
        if(this.options.edit_mode == 'editonly'){
            this.closeDialog(true); //force to avoid warning
        }else if(this.it_was_insert){
            this.searchForm.searchDefDetailTypes('startSearch');
           
            
           
           
        }else{
            //this._triggerRefresh('dty');    
        }        
        
        
    },

    //
    // NOT USED ANYMORE
    //
    updateGroupCount:function(dtg_ID,  delta){
        
        if(dtg_ID>0){
            let cnt = parseInt($Db.dtg(dtg_ID,'dtg_FieldCount'));
            cnt = (isNaN(cnt)?0:cnt)+delta;
            if(cnt<0) cnt = 0;
            $Db.dtg(dtg_ID,'dtg_FieldCount',cnt);
            this._triggerRefresh('dtg');
        }
    },    
    
    
    //-----------------------------------------------------
    //
    // send update request and close popup if edit is in dialog
    // afteraction is used in overriden version of this method
    //
    _saveEditAndClose: function( fields, afterAction, onErrorAction ){
        
        let that_widget = this;
        
        if(!fields){
            fields = this._getValidatedValues();         
            
            if(fields!=null){
                let dt_type = fields['dty_Type'];
                if(window.hWin.HEURIST4.util.isempty(dt_type)){ //actually it is already checked in _getValidatedValues
                    window.hWin.HEURIST4.msg.showMsgDlg('Field "Data type" is required');
                    fields = null;
                }else if(this.options.create_sub_record && dt_type != 'resource'){
                    return;
                }else
                //last check for constrained pointer
                if(window.hWin.HEURIST4.util.isempty(fields['dty_PtrTargetRectypeIDs'])) 
                {
                    if(this.options.create_sub_record){
                        window.hWin.HEURIST4.msg.showMsgDlg(
                            'You must select a target record type for creating sub-record fields.',null, 'Warning',
                            {default_palette_class:this.options.default_palette_class});
                        return;
                    }else if(dt_type=='resource'){
    
                        window.hWin.HEURIST4.msg.showPrompt(
    'Please select target record type(s) for this entity pointer field before clicking the Create Field button.'
    +'<br><br>We strongly recommend NOT creating an unconstrained entity pointer unless you have a very special reason for doing so, as all the clever stuff that Heurist does with wizards for building facet searches, rules, visualisation etc. depend on knowing what types of entities are linked. It is also good practice to plan your connections carefully. If you really wish to create an unconstrained entity pointer - not recommended - check this box <input id="dlg-prompt-value" class="text ui-corner-all" '
                    + ' type="checkbox" value="1"/>', 
                        function(value){
                            if(value==1){
                                that_widget._saveEditAndClose( fields, afterAction );
                            }
                        }, {title:'Target record type(s) should be set',yes:'Continue',no:'Cancel'});
                        return;
                    }else if(dt_type=='relmarker'){
                        window.hWin.HEURIST4.msg.showMsgDlg(
                            'Please select target record type. Unconstrained relationship is not allowed',null, 'Warning',
                            {default_palette_class:this.options.default_palette_class});
                        return;
                    }
                    
                }else if(!(dt_type=='resource' || dt_type=='relmarker')){
                    fields['dty_PtrTargetRectypeIDs'] = '';
                }else if(!this.options.create_sub_record && this._currentEditID < 0 && dt_type=='resource' 
                    && window.hWin.HAPI4.get_prefs_def('edit_rts_open_formlet_after_add', 0)==0){ // show customisation for new record pointer fields

                    // Customisation options
                    let pointer_mode = this._editing.getFieldByName('rst_PointerMode');
					let pointer_mode_inpt = pointer_mode.editing_input('getInputs')[0];
                    let browser_filter = this._editing.getFieldByName('rst_PointerBrowseFilter');
                    let child_rec = this._editing.getFieldByName('rst_CreateChildIfRecPtr');
                    let resource_default = this._editing.getFieldByName('rst_DefaultValue_resource');
                    let default_val = this._editing.getValue('rst_DefaultValue')[0];
                    
                    // Setup default value
                    resource_default.editing_input('fset','rst_PtrFilteredIDs', fields['dty_PtrTargetRectypeIDs']);
                    resource_default.editing_input('fset','rst_PointerMode', 'dropdown_add');
                    this._editing.setFieldValueByName('rst_DefaultValue_resource', default_val, false);

                    pointer_mode_inpt.val(resource_default.editing_input('f', 'rst_PointerMode'));
                    if(pointer_mode_inpt.hSelect('instance') != undefined){
                        pointer_mode_inpt.hSelect('refresh');
                    }

                    // enable/disable rst_PointerMode depend on rst_CreateChildIfRecPtr                   
                    function __rst_PointerMode_Enable(is_enable){
                        
                        let inpt = pointer_mode.editing_input('getInputs');
                        inpt = inpt[0];

                        window.hWin.HEURIST4.util.setDisabled(inpt.find('option[value^="dropdown"]'), !is_enable);
                        window.hWin.HEURIST4.util.setDisabled(inpt.find('option[value^="browseonly"]'), !is_enable);

                        if(!is_enable){
                            inpt.val('addorbrowse');
                        }
                        inpt.hSelect('refresh');
                        let ele = inpt.hSelect('menuWidget');
                        ele.find('li').show();
                        ele.find('li.ui-state-disabled').hide();
                    }
                    
                    child_rec.editing_input('option','change', function(){
                        let value = this.getValues()[0];
                        __rst_PointerMode_Enable(value!=1);
                    }); 
                    
                    // Setup help button
                    let help_button = $('<span style="padding-left:40px;color:gray;cursor:pointer" class="ui-icon ui-icon-circle-info"></span>')
                            .appendTo(child_rec.find('.input-div'));
                    window.hWin.HEURIST4.ui.initHelper( {button:help_button, title:'Creation of records as children', 
                                url: window.hWin.HRes('parent_child_instructions #content'),
                                no_init:true} );

                    browser_filter.find('.heurist-helper1').before(
                        '<div style="display: inline-block; font-style: italic; font-size: 0.9em;">'
                        + 'Enter an old-style (non-JSon) filter string such as f:123:7245 to filter to term 7245 in field 123'
                        + '</div>'
                    );

                    // Setup dialog element
                    let $dlg;
                    let $ele = $('<fieldset>')
                        .append(pointer_mode.show())
                        .append(browser_filter.show())
                        .append(child_rec.show())
                        .append(resource_default.show()).hide().appendTo(this.element);

                    $ele.find('.heurist-helper1').show();

                    let btns = {};
                    btns['Apply'] = function(){ 
                        $dlg.dialog('close'); 
                        that_widget._saveEditAndClose(fields, afterAction, onErrorAction); 
                    };

                    $dlg = window.hWin.HEURIST4.msg.showElementAsDialog({
                        window:  window.hWin, //opener is top most heurist window
                        element: $ele[0], 
                        open: function(event){

                            let cur_val = pointer_mode_inpt.val(); // retain current value
                            // Reset mode selector
                            if(pointer_mode_inpt.hSelect('instance')!=undefined){

                                pointer_mode_inpt.hSelect('destroy');

                                if(!window.hWin.HEURIST4.browseRecordMax){
                                    window.hWin.HEURIST4.browseRecordMax = 1000;
                                }

                                $.each(pointer_mode_inpt.find('option'), function(idx, ele){
                                    let $ele = $(ele);
                                    let title = $ele.text();

                                    if(title.indexOf('#') != -1){
                                        $ele.text(title.replace('#', window.hWin.HEURIST4.browseRecordMax));
                                    }
                                });

                                pointer_mode_inpt.hSelect();

                                pointer_mode_inpt.hSelect('widget').css('width', '20em');
                                pointer_mode_inpt.hSelect('menuWidget').css('background', '#f2f2f2');

                                pointer_mode_inpt.val(cur_val); // restore original value
                                pointer_mode_inpt.hSelect('refresh');
                            }

                            $(event.target).parent().find('.ui-dialog-buttonset > .ui-button.ui-corner-all.ui-widget').addClass('ui-button-action');
                        },
                        buttons: btns,
                        title: 'Field customisation for new record pointer',
                        resizable: false,
                        width: 640,
                        height: 322,
                        default_palette_class: 'ui-heurist-design'
                    });

                    return;
                }
            }
        }
        if(fields==null) return; //validation failed
        
        if(this.options.create_sub_record){
            fields['rst_CreateChildIfRecPtr'] = 1;
            fields['rst_MaxValues'] = 0;
        }
        
        if(this._currentEditID>0 
            && !fields['pwd_ReservedChanges']
            && $Db.dty(this._currentEditID,'dty_Status')=='reserved')
        {
        
            if(window.hWin.HAPI4.sysinfo['pwd_ReservedChanges']){ //password defined
            
                window.hWin.HEURIST4.msg.showPrompt('<p>Reserved field changes '
                +'require a special system administrator password (not a normal login password)</p>Enter password: ',
                    function(password_entered){
                        
                        window.hWin.HAPI4.SystemMgr.action_password({action:'ReservedChanges', password:password_entered},
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK && response.data=='ok'){
                                   
                                    fields['pwd_ReservedChanges'] = password_entered;
                                    that_widget._saveEditAndClose( fields, afterAction );
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgFlash('Wrong password');
                                }
                            }
                        );
                        
                    },
                'Sysadmin override password required', {password:true});
            }else{
                window.hWin.HEURIST4.msg.showMsgDlg('Reserved field changes are not allowed' 
                + 'unless a special system administrator password is set - please consult system administrator.');
            }
            return;
        }
        fields['pwd_ReservedChanges'] = null;
        
        this._super( fields, afterAction, this._onSaveError );
        
    },
    
    _onSaveError: function(response){

        let that = this;

        if(response.sysmsg){
 
            let res = response.sysmsg;                
            if(res.reccount){ // attempted to delete vocab, is in use

                let sMsg = response.message;
                sMsg += '<p><a href="'+window.hWin.HAPI4.baseURL+'?db='
                            + window.hWin.HAPI4.database+'&q=ids:' + res['records'].join(',')
                            + '&nometadatadisplay=true" target="_blank">'
                    +'List of '+res.reccount+' records which use terms missing in the new vocabulary</a></p>';
                
                window.hWin.HEURIST4.msg.showMsgDlg(sMsg, null, {title:'Vocabulary in use'},
                    {default_palette_class:this.options.default_palette_class});
            }else if(this._currentEditID == -1 && res.dty_ID){ // attempted to create new field, name is already used

                let cannotEdit = $Db.dty(res.dty_ID, 'dty_Status') == 'reserved' && !window.hWin.HAPI4.sysinfo['pwd_ReservedChanges'];
                let exist_name = $Db.dty(res.dty_ID, 'dty_Name');
                let msg = 'The name "<span id="field_name">'+ exist_name +'</span>" which you have specified for the new field '
                    + 'is already in use for an existing base field (id = <span id="field_id">'+ res.dty_ID +'</span>).<br><br>';

                if(!cannotEdit){

                    if($Db.rst_usage(res.dty_ID).length == 0){ // check if in use
                        msg += '<span class="btnDelete">DELETE</span> <span style="margin-left: 20px">Delete the existing base field (it has not been used)</span><br><br>';
                    }

                    msg += '<span class="btnRename">RENAME</span> <span style="margin-left: 20px; display: inline-block; vertical-align: middle;">'
                            + 'Rename the existing base field so you can use this name for the new field<br>'
                            + '(this will not affect the name of the field in any other record type)'
                        + '</span><br><br>'
                        + 'Simply close this popup to edit the name for the new field.';
                }else{ // reserved and no special password
                    msg += 'The existing field is a reserved type and so cannot be edited at this time, please close this popup and give your new base field a different name.';
                }

                let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, null, {title: 'Name already in use'}, {default_palette_class: 'ui-heurist-design', dialogId: 'basefield-dup-name'});

                $dlg.find('.btnDelete').button().css('width', '50px').on('click', function(){

                    let request = {
                        'a'          : 'delete',
                        'entity'     : that.options.entity.entityName,
                        'request_id' : window.hWin.HEURIST4.util.random(),
                        'recID'      : res.dty_ID
                    };

                    window.hWin.HAPI4.EntityMgr.doRequest(request, 
                        function(response){
                            if(response.status == window.hWin.ResponseStatus.OK){

                                $Db.dty().removeRecord( res.dty_ID );

                                $dlg.dialog('close');
                                that._saveEditAndClose();

                                if(that.searchForm){
                                    that.searchForm.searchDefDetailTypes('startSearch');
                                }
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        }
                    );
                });

                $dlg.find('.btnRename').button().css('width', '50px').on('click', function(){

                    let $rdlg = window.hWin.HEURIST4.msg.showMsgDlg('Please enter a new name: <input class="text ui-corner-all" style="width: 150px" />', 
                        null, null, {
                            default_palette_class: 'ui-heurist-design', 
                            labels: {'ok': 'Proceed', 'cancel': 'Cancel', title: 'Rename existing base field'}, 
                            dialogId: 'rename-basefield',
                            buttons: {
                                'Proceed': function(){
                                    let new_name = $rdlg.find('input').val();

                                    if(window.hWin.HEURIST4.util.isempty(new_name)){
                                        window.hWin.HEURIST4.msg.showMsgFlash('Please enter a new name', 1500);
                                        return;
                                    }
                                    let fld_record = $Db.rst(that.options.newFieldForRtyID, res.dty_ID);

                                    that._currentEditID = res.dty_ID;
                                    that._saveEditAndClose({dty_Name: new_name, dty_ID: res.dty_ID}, 
                                        function(id, flds){

                                            $Db.dty().setRecord(id, flds);

                                            $rdlg.dialog('close');
                                            $dlg.dialog('close');

                                            if(fld_record && fld_record['rst_DisplayName'] == exist_name){
                                                // Update in record structure field name with new name, then save new field
                                                let req = {
                                                    a: 'save',
                                                    entity: 'defRecStructure',
                                                    fields: {
                                                        rst_DisplayName: new_name,
                                                        rst_DetailTypeID: res.dty_ID,
                                                        rst_RecTypeID: that.options.newFieldForRtyID
                                                    },
                                                    request_id: window.hWin.HEURIST4.util.random()
                                                };
                                                window.hWin.HAPI4.EntityMgr.doRequest(req, function(response){
                                                    if(response.status == window.hWin.ResponseStatus.OK){ // update cache, prepare rec structure tree for updating
                                                        $Db.rst(that.options.newFieldForRtyID).setRecord(res.dty_ID, {rst_DisplayName: new_name});
                                                        that.updatedRstField = res.dty_ID;
                                                    }
                                                    that._currentEditID = -1;
                                                    that._saveEditAndClose();
                                                });
                                            }else{ // just save new field
                                                that._currentEditID = -1;
                                                that._saveEditAndClose();
                                            }
                                        }, 
                                        function(response){
                                            if(!response.sysmsg){
                                                window.hWin.HEURIST4.msg.showMsgErr(response);
                                            }
                                            if(response.sysmsg.dty_id){
                                                $dlg.find('#field_name').text(new_name);
                                                $dlg.find('#field_id').text(response.sysmsg.dty_id);
                                                window.hWin.HEURIST4.msg.showMsgFlash('Name already taken', 2500);
                                            }
                                        }
                                    );
                                },
                                'Cancel': function(){
                                    $rdlg.dialog('close');
                                }
                            }
                        }
                    );
                });
            }
        }else{
            window.hWin.HEURIST4.msg.showMsgErr(response);    
        }
    },
    
    //  -----------------------------------------------------
    //
    // perform validation
    //
    _getValidatedValues: function(){
        
        //fieldvalues - is object {'xyz_Field':'value','xyz_Field2':['val1','val2','val3]}
        let fieldvalues = this._super();
        
        if(fieldvalues!=null){
            
            let dt_type = fieldvalues['dty_Type'];
            if(dt_type=='enum' || dt_type=='relmarker'){

                if(!fieldvalues['dty_JsonTermIDTree']){

                    if(dt_type=='enum'){    
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: 'Please select or add a vocabulary. Vocabularies must contain at least one term.',
                            error_title: 'Missing valid vocabulary',
                            status: window.hWin.ResponseStatus.INVALID_REQUEST
                        });
                    }else{
                        window.hWin.HEURIST4.msg.showMsgDlg(
                            'Please select or add relationship types',null, 'Warning',
                            {default_palette_class:this.options.default_palette_class});
                    }
                    return null;                    
                }
            }else{
                fieldvalues['dty_JsonTermIDTree'] = '';
                fieldvalues['dty_TermIDTreeNonSelectableIDs'] = '';
            }
        }           
        return fieldvalues;
        
    },
    
    
    //
    // event handler for select-and-close (select_multi)
    // or for any selection event for select_single
    // triger onselect event
    //
    _selectAndClose: function(){
        
        if(this.options.newFieldForRtyID>0){
            let rst_fields = {rst_RequirementType: this._editing.getValue('rst_RequirementType')[0], 
                              rst_MaxValues: this._editing.getValue('rst_MaxValues')[0], 
                              rst_DisplayWidth: this._editing.getValue('rst_DisplayWidth')[0],
                              rst_PointerMode: this._editing.getValue('rst_PointerMode')[0],
                              rst_PointerBrowseFilter: this._editing.getValue('rst_PointerBrowseFilter')[0],
                              rst_CreateChildIfRecPtr: this._editing.getValue('rst_CreateChildIfRecPtr')[0],
                              rst_DefaultValue_resource: this._editing.getValue('rst_DefaultValue_resource')[0]
                          };
                              
            this._resultOnSelection = { rst_fields:rst_fields, updatedRstField: this.updatedRstField };
        }
        
        this._super();
    },
   
    //
    //
    //                                
    changeDetailtypeGroup: function(params){                                    

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.recordList);

        let that = this;
        this._saveEditAndClose( params ,
            function(){
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                that.searchForm.searchDefDetailTypes('startSearch');
                that._triggerRefresh('dty');
        });
    },                                    
    
    //
    //
    getUiPreferences:function(){
        this.usrPreferences = window.hWin.HAPI4.get_prefs_def('prefs_'+this._entityName, {
            help_on: true
        });
        
        return this.usrPreferences;
    },
    
    //saveUiPreferences:function() { this._super(); }, 
   
    //
    // show warning
    //
    addEditRecord: function(recID, is_proceed){

        if(recID<0 && is_proceed !== true){
            this.coverMessage(recID);
        }else{
            this._super(recID, is_proceed); 
        }
    },

    //
    // listener of onfilter event generated by searchEtity. appicable for use_cache only       
    //
    filterRecordList: function(event, request){ 

        if(request['rtyID']){
            this._record_type = request['rtyID'];
            delete request['rtyID']; // remove from request
        }

        let results = this._super(event, request);

        if(results && Number.isInteger(+this._record_type) && this._record_type > 0){
            // Filter for record type
            results = results.getSubSetByIds($Db.rst(this._record_type).getIds());
            this.recordList.resultList('updateResultSet', results, request);
        }

        if(results!=null && results.count_total()==0){

            if(this.options.select_mode=="manager"){

                let s_title = this.element.find('#input_search').val();
                let s_all = this.element.find('#chb_show_all_groups').is(':checked');
                let s_type = this.element.find('#input_search_type').val();
                
                let sMsg;

                if(window.hWin.HEURIST4.util.isempty(s_title) && !s_all && s_type=='any'){
                    sMsg = '<div style="margin-top:1em;">There are no base fields defined  in this group.'
                            +'<br><br>Please drag base fields from other groups or add new<br>base fields to this group.</div>'
                }else{
                    sMsg = '<div style="padding: 10px">'
                            +'<h3 class="not-found" style="color:red;">Filter/s are active (see above)</h3><br>'
                            +'<h3 class="not-found" style="color:teal">No entities match the filter criteria</h3>'
                            +'</div>';
                }
                this.recordList.resultList('option','empty_remark', sMsg);
                this.recordList.resultList('renderMessage', sMsg);
                
            }
        }
    },

    //
    // cover message warning a standard user to not define base fields at this location
    //
    coverMessage: function(recID){
        let that = this;

        if(this.element.find('#base-field-warning').length == 0){ // Check if message already exists

            let font_size = (navigator.userAgent.indexOf('Firefox') >= 0) ? '0.95em' : '1.2em';

            this.element.append('<div id="base-field-warning" style="position:relative;background:rgb(0,0,0,0.6);z-index:60000;height:100%;">'
                + '<div style="background:lightgrey;border:2px solid black;color:black;position:absolute;top:50%;left:50%;transform:translate(-50%, -50%);height:200px;width:510px;font-size:'+font_size+';padding:20px;">'
                + 'The base fields editing function is provided for completeness and for<br>advanced data management. Most users will not need to use it.<br><br>'
                + '<strong>We strongly recommend NOT using this function to create new<br>base fields. It is much more intuitive to create them <em>in situ</em> while<br>designing your record structure.</strong><br></br>'
                + 'Recommended: Design > <span style="text-decoration:underline;cursor:pointer" '
                +'onclick="window.hWin.HAPI4.actionHandler.executeActionById(\'menu-structure-rectypes\');">Record Types</span><br><br>'
                + 'Click outside this box for access to base fields manager'
                + '</div></div>'); // Add message

            $('#base-field-warning').on('click', function(e){
                if(e.target !== this){ return; }

                $('#base-field-warning').hide();
            });
        }
        else{ // Show message
            this.element.find('#base-field-warning').show();

            $('#base-field-warning').on('click', function(e){
                if(e.target !== this){ return; }

                let id = (recID)?recID : -1;

                that.addEditRecord(id, true);
            });
        }
    },
    
    //
    //
    //
    _getEditDialogButtons: function(){

            let btn_array = this._super();
        
            // Add extra save button to new base field for a record, replaces the original checkbox
            if(this.options.newFieldForRtyID > 0 && !this.options.newFieldType && !this.options.create_sub_record){
                let that = this;
                
                btn_array.splice(1, 0, {
                    text:window.hWin.HR('Create and customise new field'),
                    css:{'display':'none','float':'right',margin:'.5em .8em .5em .4em'},
                    class:'ui-button-action btnSaveExt',
                    click: function() {
                        that._saveEditAndClose();
                    }
                });
            }
        
            return btn_array;
    },

    getRecordsetFromRemote: function( detailtypes, hideDisabled ){

        let rdata = { 
            entityName:'defDetailTypes',
            total_count: 0,
            fields:[],
            records:{},
            order:[] };

        detailtypes = window.hWin.HEURIST4.util.cloneJSON(detailtypes);

        rdata.fields = detailtypes.typedefs.commonFieldNames;
        rdata.fields.unshift('dty_ID');
        
        let idx_ccode = 0;
        if(this.options.import_structure){
            rdata.fields.push('dty_ID_local');
            idx_ccode = detailtypes.typedefs.fieldNamesToIndex.dty_ConceptID;
        }

        let idx_visibility = detailtypes.typedefs.fieldNamesToIndex.dty_ShowInLists;
        let idx_groupid = detailtypes.typedefs.fieldNamesToIndex.dty_DetailTypeGroupID;
        let hasFieldToImport = false;
        let trash_id = -1;

        for (let key in detailtypes.groups){
            if(detailtypes.groups[key].name == 'Trash'){
                trash_id = detailtypes.groups[key].id;
                break;
            }
        }

        for (let r_id in detailtypes.typedefs)
        {
            if(r_id>0){
                let detailtype = detailtypes.typedefs[r_id].commonFields;
                let isHidden = (detailtype[idx_visibility] == '0' || detailtype[idx_groupid] == trash_id);

                if(hideDisabled && isHidden){
                    continue;
                }
                
                if(this.options.import_structure){
                    let concept_code =  detailtype[ idx_ccode ];
                    let local_dtyID = $Db.getLocalID( 'dty', concept_code );
                    detailtype.push( local_dtyID );
                    hasFieldToImport = hasFieldToImport || !(local_dtyID>0);
                }
                
                detailtype.unshift(r_id);
                
                rdata.records[r_id] = detailtype;
                rdata.order.push( r_id );
                
            }
        }
        rdata.count = rdata.order.length;
        
        
        if(this.options.import_structure){
            this.recordList.resultList('option', 'empty_remark',
                                        '<div style="padding:1em 0 1em 0">'+
                                        (hasFieldToImport
                                        ?this.options.entity.empty_remark
                                        :'Your database already has all the fields available in this source'
                                        )+'</div>');
        }
        
        this._cachedRecordset = new HRecordSet(rdata);
        this.recordList.resultList('updateResultSet', this._cachedRecordset);
        
        this.searchForm.searchDefDetailTypes('startSearch');
        
        return this._cachedRecordset;
    },

    //
    // Extra details for the user when setting up sub-records
    //
    _setupSubRecordField: function(){ //create_sub_record

        // Force type to record pointer
        let $ele = this._editing.getFieldByName('dty_Type');
        $ele.editing_input('setValue', 'resource');
        $ele.editing_input('setDisabled', true);

        // Force child records
        $ele = this._editing.getFieldByName('rst_CreateChildIfRecPtr');
        $ele.editing_input('setValue', '1');
        $ele.editing_input('setDisabled', true);

        // Force repeatable
        $ele = this._editing.getFieldByName('rst_MaxValues');
        $ele.editing_input('setValue', 0);
        $ele.editing_input('setDisabled', true);

        // Set default help text
        $ele = this._editing.getFieldByName('dty_HelpText');
        $ele.editing_input('setValue', "This field points to sub-records of the current record type.\nFields from the current record type have been moved to these sub-records");
       

        // Add required class to header
        $ele = this._editing.getFieldByName('dty_PtrTargetRectypeIDs');
        $ele.find('.header').addClass('required');

        // Main message
        $('<h2 style="margin-block:0;margin-bottom:0.2em">Create a new field</h2>'
        + '<div style="color: red;display: block;margin-left: auto;margin-right: auto;width: 90%;font-size: 1.2em;"><strong>You are creating a new sub-record child pointer field</strong><br>'
        + 'In the next step you will select fields to be transferred to the sub-records</div><br>').prependTo($(this._editing.getContainer()[0]).find('fieldset')[0]);
    },

    importDetailTypes: function(){

        const that = this;

        let sURL = `${window.hWin.HAPI4.baseURL}import/delimited/importDefDetailTypes.php?db=${window.hWin.HAPI4.database}&dtg_ID=${this.options.dtg_ID}`;

        window.hWin.HEURIST4.msg.showDialog(sURL, {
            default_palette_class: 'ui-heurist-design',
            'close-on-blur': false,
            'no-resize': false,                  
            title: 'Import Base fields by CSV',
            height: 800,
            width: 1000,
            context_help: 'defDetailTypes #import',
            callback: function(context){ 

                if(context && context.result){

                    that._loadData(false);

                    let msg = context.result;;
                    if(Array.isArray(context.result) || context.result.refresh_terms){

                        msg = '<strong>Definitions imported</strong>, report:<br><br>';
                        if(context.result.refresh_terms){
                            delete context.result.refresh_terms;
                        }
    
                        msg += context.result.join('<br>');
                    }

                    window.hWin.HEURIST4.msg.showMsgDlg(msg, null, 'Base fields imported',
                        {default_palette_class:that.options.default_palette_class});
                }
            }
        });
    }

});