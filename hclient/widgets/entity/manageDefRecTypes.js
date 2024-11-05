/**
* manageDefRecTypes.js - main widget to manage defRecTypes users
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

/*
we may take data from 
1) use_cache = false  from server on every search request (live data) 
2) use_cache = true   from client cache - it loads once per heurist session (actually we force load)
*/
$.widget( "heurist.manageDefRecTypes", $.heurist.manageEntity, {
   
    _entityName:'defRecTypes',
    fieldSelectorLast:null,
    fieldSelector:null,
    fieldSelectorOrig:null,
    
    is_new_icons: true,
    
    rst_links: null, //reference to links data $Db.rst_links

    usrPreferences:{},
    defaultPrefs:{
        width:(window.hWin?window.hWin.innerWidth:window.innerWidth)*0.95,
        height:(window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95,
//rtyid,'ccode','addrec','filter','count','group','icon','edit','editstr','name','description','show','duplicate','fields','status'        
        fields:['icon','fields','edit','name','count','filter','addrec','show','duplicate','status','rtyid','ccode','description'] 
        //'editstr',
        },
    fields_width: {rtyid:30,ccode:80,addrec:34,filter:34,count:40,group:34,icon:40,edit:34,editstr:34,
                name:150,description:34,show:34,duplicate:34,fields:34,status:34},
    //fields_name:{rtyid:'ID',ccode:'Code',addrec:'Add',filter:'Filter',count:'Count',group:'Group',icon:'Icon',edit:'Attr',editstr:'Edit',
    //             name:'Name',description:'Description',show:'Show',duplicate:'Dup',fields:'Info',status:'Del'},


    //
    //                                                  
    //    
    _init: function() {

        this.element.addClass(this._entityName); //to find all exisiting editors in application
        
        this.options.default_palette_class = 'ui-heurist-design';
        
        this.options.innerTitle = false;
        
        //define it to load recordtypes from other server/database - if defined it allows selection only
        if(this.options.import_structure){ //for example HEURIST_INDEX_BASE_URL?db=Heurist_Reference_Set
            if(this.options.select_mode=='manager') this.options.select_mode='select_single';
            this.options.use_cache = true;
            this.options.use_structure = true; //use HEURIST4.remote.rectypes for import structures    
        }else{
            this.options.use_cache = true;
            this.options.use_structure = false
        }
        
        
        if(!this.options.layout_mode) this.options.layout_mode = 'short';
        
       
        this.options.edit_height = 640;
        this.options.edit_width = 1000;
        this.options.height = 640;

        if(this.options.edit_mode=='editonly'){
            this.options.select_mode = 'manager';
            this.options.layout_mode = 'editonly';
            this.options.width = 1000;
           
        }else
        //for selection mode set some options
        if(this.options.select_mode!='manager' && this.options.select_mode!='select_multi'){
            this.options.width = (isNaN(this.options.width) || this.options.width<750)?750:this.options.width;                    
            //this.options.edit_mode = 'none'
        }
        
        //this.options.edit_mode=='popup'
        if(this.options.select_mode=='select_multi' || this.options.select_mode=='select_single'){ //special compact case
            this.options.width = 540;
        }
            
            
        this._super();
        
        
        let that = this;
        
        //
        if(!this.options.import_structure){        
            this.rst_links = $Db.rst_links_base();
        
            window.hWin.HAPI4.addEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
                function(data) { 
                    if(!data || (data.source != that.uuid && data.type == 'rty'))
                    {
                        that._loadData();    
                    }else if(data && (data.type == 'dty' || data.type == 'rst')){
                        that.rst_links = $Db.rst_links_base();
                    }
                });
                
            window.hWin.HAPI4.addEventListener(this, window.hWin.HAPI4.Event.ON_REC_UPDATE,
                function(data) { 

                    if(that.element.is(':visible'))
                    $Db.get_record_counts(function(){
                        that._loadData();
                    });                                
                });
                
            if(this.options.isFrontUI && this.options.select_mode!='select_multi' && this.options.select_mode!='select_single'){ //adjust table widths

                window.hWin.HAPI4.addEventListener(this, window.hWin.HAPI4.Event.ON_WINDOW_RESIZE, 
                    function(){
                        if(that.recordList && that.recordList.resultList('instance')){
                            that.recordList.resultList('applyViewMode','list', true);
                            that.recordList.resultList('refreshPage');
                        }
                    });
            
            /*
                that._delayOnResize = 0;
                window.onresize = function(){
                    if(that._delayOnResize) clearTimeout(that._delayOnResize);
                    that._delayOnResize = setTimeout(function(){
                        if(that.recordList && that.recordList.resultList('instance')){
                            that.recordList.resultList('applyViewMode','list', true);
                            that.recordList.resultList('refreshPage');
                        }
                    },500);
                };
                */
            }
            
        }
                
        
    },
    
    _destroy: function() {
        
       window.hWin.HAPI4.removeEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);        
       window.hWin.HAPI4.removeEventListener(this, window.hWin.HAPI4.Event.ON_WINDOW_RESIZE);        
       window.hWin.HAPI4.removeEventListener(this, window.hWin.HAPI4.Event.ON_REC_UPDATE);        
        
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
        
       
       
        this.searchForm.parent().css({'overflow-x':'auto'});

        this.options.ui_params = window.hWin.HEURIST4.util.cloneJSON( this.getUiPreferences() );
        
        //init viewer 
        let that = this;
        
        if(this.options.select_mode=='manager'){
   
            if(this.options.ui_params && Array.isArray(this.options.ui_params.fields)){
                let fields = this.options.ui_params.fields;
                if(fields.indexOf('name')<0) fields.unshift('name');
                if(fields.indexOf('edit')<0) fields.unshift('edit');
            }    
            
            if(this.options.isFrontUI){
                
                this.searchForm.css({padding:'10px 5px 0 10px'});
                
                window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, {'background-color':'#fff', opacity:1});   
                
                //add record type group editor
                this.element.addClass('ui-suppress-border-and-shadow');
                
                this.element.find('.ent_wrapper:first').addClass('ui-dialog-heurist').css('left',328);
                
                this.rectype_groups = $('<div>').addClass('ui-dialog-heurist')
                    .css({position: 'absolute',top: 0, bottom: 0, left: 0, width:320, overflow: 'hidden'})
                    .appendTo(this.element);
            }                
            
          
            this.recordList.resultList({ 
                    empty_remark: 'There are no record types defined in this group',
                    show_toolbar: false,
                    list_mode_is_table: true,
                    rendererHeader:function(){ return that._recordListHeaderRenderer() },
                    draggable: function(){
                        
                        that.recordList.find('.rt_draggable > .item').draggable({ // 
                                    revert: 'invalid',
                                    helper: function(){ 
                                        return $('<div class="rt_draggable ui-drag-drop" recid="'+
                                            $(this).parent().attr('recid')
                                        +'" style="width:300;padding:4px;text-align:center;font-size:0.8em;background:#EDF5FF"'
                                        +'>Drag and drop to group item to change record type group</div>'); 
                                    },
                                    zIndex:100,
                                    appendTo:'body',
                                    scope: 'rtg_change',
                                    containment: 'window'
                                    //containment: that.element,
                                    //delay: 200
                                });   
                    }});
                    
                    
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
                    
                    
        }else if(this.options.select_mode=='select_multi' || this.options.select_mode=='select_single'){
            this.recordList.resultList({ 
                    show_toolbar: true,
                    view_mode:'list',
                    show_viewmode: false,
                    list_mode_is_table: true});
                   
            this.searchForm.css({padding:'6px', 'min-width': '420px'});
            this.recordList.css({'min-width': '420px'});
                   
         
            this.options.ui_params.width = 440;
            this.options.ui_params.fields = ['icon','name','fields'];
            
            this.recordList.resultList('fullResultSet', $Db.rty());   
        }
                    

        //may overwrite resultList behaviour
        if(this.options.recordList){
            this.recordList.resultList( this.options.recordList );
        }


        this.options.onInitCompleted =  function(){

            if(that.options.isFrontUI){
                    let rg_options = {
                        isdialog: false, 
                        isFrontUI: true,
                        container: that.rectype_groups,
                        title: 'Record type groups',
                        layout_mode: 'short',
                        select_mode: 'manager',
                        reference_rt_manger: that.element,
                        onSelect:function(res){

                            if(window.hWin.HEURIST4.util.isRecordSet(res)){
                                res = res.getIds();                     
                            }
                            
                            if(res && Array.isArray(res) && res.length>0){
                                that.options.rtg_ID = res[0];
                                that.searchForm.searchDefRecTypes('option','rtg_ID', that.options.rtg_ID);
                            }

                            if(!that.getRecordSet()){
                                that._loadData( true ); //not yet loaded
                            }

							if(that.options.select_mode == 'manager'){ // reset search to default for manager

                                that.searchForm.find('#input_search').val('');
                                that.searchForm.find('#chb_show_all_groups').prop('checked', false);
                                that.searchForm.find('#input_sort_type').val('name');

                                that.searchForm.searchDefRecTypes('startSearch');
                            }
                        },
                        add_to_begin: true
                    };
                window.hWin.HEURIST4.ui.showEntityDialog('defRecTypeGroups', rg_options);
            }else{
                that._loadData( true );
            }

            
            let iheight = that.options.import_structure?0:80;
            that.searchForm.css({'height':iheight});
            that.recordList.css({'top':iheight});     
            //!!!! that.changeUI(null, that.options.ui_params);    
        }
        
        this.searchForm.searchDefRecTypes(this.options);
        
        if(this.options.use_cache){
           
            //if there are many widgets need to use base searchentityonfilter
           this._on( this.searchForm, {
                "searchdefrectypesonfilter": this.filterRecordList,
                "searchdefrectypesonadd": function() {
                        this._onActionListener(null, 'add');
                },
                "searchdefrectypesonuichange": this.changeUI,  //grouping, visible columns  
                "searchdefrectypesonimport": this.importRecordTypes
           });
        }else{
            this._on( this.searchForm, {
                "searchdefrectypesonresult": this.updateRecordList,
                "searchdefrectypesonadd": function() { this.addEditRecord(-1); },
                "searchdefrectypesonuichange": this.changeUI,  //grouping, visible columns
                "searchdefrectypesonimport": this.importRecordTypes
            });
        }
        
        
        return true;
    },            
    
    //
    // invoked after all elements are inited 
    //
    _loadData: function(is_first){
        
        let that = this;
      
        if(this.options.use_cache){
           
           /*this._on( this.searchForm, {
                "searchdefrectypesonfilter": this.filterRecordList  
           });*/
            if(this.options.use_structure){  //get recordset from HEURIST4.rectypes
                
                if(this.options.import_structure){
                    //take recordset from REMOTE HEURIST.rectypes format     
                    window.hWin.HEURIST4.msg.bringCoverallToFront(this.element);
                    
                    this.recordList.resultList('resetGroups');
                    this.recordList.resultList('clearAllRecordDivs',null, '<div style="padding: 10px;cursor: wait;">'
                        + '<span class="ui-icon ui-icon-loading-status-balls"></span> &nbsp;&nbsp;'
                        + 'Loading record types from template database</div>');
                    this.recordList.resultList('option', 'empty_remark', '');
                    
                    let sMsg = window.hWin.HR('manageDefRectypes_longrequest');
                    sMsg = sMsg.replaceAll( '[url]', this.options.import_structure.database_url); 
                    
                    let _too_long = 0;
                    let _too_long_dlg = null;
                    
                    let buttons = {};
                    buttons[window.hWin.HR('Continue')]  = function() {
                                //continue
                                _too_long = setTimeout(__executionTooLong, 5000);
                                _too_long_dlg.dialog('close')
                    };
                    buttons[window.hWin.HR('Abort')]  = function() {
                                window.hWin.HEURIST4.msg.sendCoverallToBack();
                                _too_long = -1; //terminate
                                _too_long_dlg.dialog('close');
                    };
                    
                    function __executionTooLong(){
                        _too_long = 0; 
                        _too_long_dlg = window.hWin.HEURIST4.msg.showMsgDlg(sMsg,
                            buttons, 'Confirm',
                            {default_palette_class:that.options.default_palette_class});                        
                    }
                    
                    _too_long = setTimeout(__executionTooLong, 10000);
                    
                    
                    //@todo - obtain rectypes and detailtypes in new format
                    //get rectypes (in old format) from REMOTE database
                    window.hWin.HAPI4.SystemMgr.get_defs(
                            {rectypes:'all', mode:2, remote:this.options.import_structure.database_url}, function(response)
                    {
                            window.hWin.HEURIST4.msg.sendCoverallToBack();
                            
                            if(_too_long_dlg && _too_long_dlg.dialog('instance')){
                                _too_long_dlg.dialog('close');
                                _too_long_dlg = null;
                            }
                            if(_too_long>0){
                                clearTimeout(_too_long);
                            }else if(_too_long<0){ // && response.status != window.hWin.ResponseStatus.OK
                                return; //terminated
                            }
                            _too_long = 0;
                        
                            if(response.status == window.hWin.ResponseStatus.OK){
                                
                                if(!window.hWin.HEURIST4.remote){
                                    window.hWin.HEURIST4.remote = {};
                                }

                                window.hWin.HEURIST4.remote.rectypes = response.data.rectypes;
                                
                                that._cachedRecordset = that.getRecordsetFromStructure( response.data.rectypes, true ); //change to true to hide where rty_ShowInList=0

                                if(that.options.import_structure.load_detailstypes){ // retrieve base fields
                                    window.hWin.HAPI4.SystemMgr.get_defs( //only basefield names
                                        {detailtypes:'all', mode:0, remote:that.options.import_structure.database_url}, function(response){
                                            if(response.status == window.hWin.ResponseStatus.OK){
                                                window.hWin.HEURIST4.remote.detailtypes = response.data.detailtypes;
                                            }
                                        }
                                    );
                                }
                                
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                    });                    
                    
                }else{
                    this._loadData();
                    
                    //take recordset from LOCAL HEURIST.rectypes format     
                    this._cachedRecordset = this.getRecordsetFromStructure();
                }
            }
            else{
                if( is_first && $Db.needUpdateRtyCount>0 ){
                    $Db.get_record_counts(function(){
                        that._loadData(true);
                    });                                
                    return;
                }

                //usual via entity
                //shorter
                this.updateRecordList(null, {recordset:$Db.rty()});
                if(this.searchForm.searchDefRecTypes('instance')){ //is_first!==true && 

                    this.searchForm.searchDefRecTypes('option','rtg_ID', this.options.rtg_ID);                    
                    this.searchForm.searchDefRecTypes('startSearch');    
                }
                                

                
                /*
                //longer but safer - since it reloads data if it is missed locally
                let that = this;
                window.hWin.HAPI4.EntityMgr.getEntityData(this._entityName, false,
                    function(response){
                        that.updateRecordList(null, {recordset:response});
                        that.searchForm.searchDefRecTypes('startSearch');
                    });
                */
                
            }
                
        }    
        
    },

    //
    // get recordset from HEURIST4.rectypes - it is used for import structure only
    //
    getRecordsetFromStructure: function( rectypes, hideDisabled ){
        
        let rdata = { 
            entityName:'defRecTypes',
            total_count: 0,
            fields:[],
            records:{},
            order:[] };
      

        if(!rectypes){
            //take from local definitions = NOT USED
            rectypes = window.hWin.HEURIST4.util.cloneJSON(window.hWin.HEURIST4.rectypes); //NOT USED
        }else{
            rectypes = window.hWin.HEURIST4.util.cloneJSON(rectypes);
        }

        rdata.fields = rectypes.typedefs.commonFieldNames;
        rdata.fields.unshift('rty_ID');
        
        let idx_ccode = 0;
        if(this.options.import_structure){
            rdata.fields.push('rty_ID_local');
            idx_ccode = rectypes.typedefs.commonNamesToIndex.rty_ConceptID;
        }else{
            rdata.fields.push('rty_RecCount');
        }

        let idx_visibility = rectypes.typedefs.commonNamesToIndex.rty_ShowInLists;
        let idx_groupid = rectypes.typedefs.commonNamesToIndex.rty_RecTypeGroupID;
        let hasRtToImport = false;
        let trash_id = -1;

        for (let key in rectypes.groups){
            if(rectypes.groups[key].name == 'Trash'){
                trash_id = rectypes.groups[key].id;
                break;
            }
        }

        for (let r_id in rectypes.typedefs)
        {
            if(r_id>0){
                let rectype = rectypes.typedefs[r_id].commonFields;
                let isHidden = (rectype[idx_visibility] == '0' || rectype[idx_groupid] == trash_id);

                if(hideDisabled && isHidden){
                    continue;
                }
                
                if(this.options.import_structure){
                    let concept_code =  rectype[ idx_ccode ];
                    let local_rtyID = $Db.getLocalID( 'rty', concept_code );
                    rectype.push( local_rtyID );
                    hasRtToImport = hasRtToImport || !(local_rtyID>0);
                }
                
                rectype.unshift(r_id);
                
                if(rectypes.counts) rectype.push(rectypes.counts[r_id]);
                
                rdata.records[r_id] = rectype;
                rdata.order.push( r_id );
                
            }
        }
        rdata.count = rdata.order.length;
        
        
        if(this.options.import_structure){
            this.recordList.resultList('option', 'empty_remark',
                                        '<div style="padding:1em 0 1em 0">'+
                                        (hasRtToImport
                                        ?this.options.entity.empty_remark
                                        :'Your database already has all the entity types available in this source. Check the Show All box to update existing types.'
                                        )+'</div>');
        }
        
        this._cachedRecordset = new HRecordSet(rdata);
        this.recordList.resultList('updateResultSet', this._cachedRecordset);
        
        this.searchForm.searchDefRecTypes('startSearch');
        
        return this._cachedRecordset;
    },
    
    //----------------------
    //
    //
    //
    _recordListHeaderRenderer: function(){

        let max_width = this.recordList.find('.div-result-list-content').width() - 33;
        let used_width = 0;
        
        function fld2(col_width, value, hint, style){
            
            if(!style) style = '';
            if(col_width>0){
                col_width = col_width - 1;
                used_width = used_width + col_width + 4;
                style += (';width:'+col_width+'px'); 
            }
            if(style!='') style = 'style="'+style+'"';
            
            if(!value){
                value = '';
            }
            return '<div class="item truncate" title="'
                        +hint+'" '+style+'>'
                        +window.hWin.HEURIST4.util.htmlEscape(value)
                    +'</div>';
        }
        
        let html = '';
        if (!(this.usrPreferences && this.usrPreferences.fields)) return '';
        let fields = this.options.ui_params.fields;
        
        let i = 0;
        for (;i<fields.length;i++){
            switch ( fields[i] ) {
                case 'rtyid': html += fld2(30,'ID','Local ID','text-align:center'); break;
                case 'ccode': 
                    html += fld2(80,'ConceptID','Concept code','text-align:center');     
                    break;
                case 'addrec': 
                    html += fld2(34,'Add','Add record','text-align:center');
                    break;
                case 'filter':
                    html += fld2(34,'Filter','Filter records','text-align:center');
                    break;
                case 'count': 
                    html += fld2(40,'Count','Count','text-align:center');
                    break;
                case 'group': 
                    html += fld2(34,'Group','','text-align:center');
                    break;
                case 'icon': 
                    html += fld2(40,'Icon','','text-align:center');
                    break;
                case 'edit':  
                    html += fld2(34,'Edit','Edit','text-align:center;'); //font-size:12px
                    break;
//                case 'editstr': 
                case 'name':  
                    html += '$$NAME$$';
                    break;
                case 'description':  
                    html += '$$DESC$$'; //fld2(null,'Description',''); 
                    break;
                case 'show': 
                    html += fld2(34,'Show','Show','text-align:center;'); //font-size:12px
                    break;
                case 'duplicate': 
                    html += fld2(34,'Dup','Duplicate','text-align:center;'); //font-size:12px
                    break;
                case 'fields': 
                    html += fld2(34,'Fld list','List fields','text-align:center;'); //font-size:12px
                    break;
                case 'status': 
                    html += fld2(34,'Del','Delete / protected','text-align:center;'); //font-size:12px
                    break;
            }   
        }
        
        //if Description presents it takes all possible width
        // otherwise this is Name
        let w_desc = 0, i_desc = fields.indexOf('description');
        if(i_desc>=0){
            w_desc = max_width-used_width-250;
            if(w_desc<30) w_desc = 30;
            html = html.replace('$$DESC$$',fld2(w_desc, 'Description', 'Description', 'text-align:left'))
        }
        let name_width = (this.options.select_mode=='select_multi')?400:250;
        html = html.replace('$$NAME$$',fld2(name_width, 'Name', 'Name', 'text-align:left'))
        
        return html;
        
        /*
        let s = '<div style="width:40px"></div><div style="width:3em">ID</div>'
                    +'<div style="width:13em">Name</div>'
                    +'<div style="width:20em;border:none;">Description</div>';
            
            if (window.hWin.HAPI4.is_admin()){
                s = s+'<div style="position:absolute;right:4px;width:60px">Edit</div>';
            }
        */    
    },
    
    
    _recordListItemRenderer:function(recordset, record){
        
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, col_width, value, style){
            
            if(!style) style = '';
            if(col_width>0){
                style += (';max-width:'+col_width+'px;min-width:'+col_width+'px');
            }
            if(style!='') style = 'style="'+style+'"';
            
            if(value==null){
                value = recordset.fld(record, fldname);
            }
            return '<div class="item truncate" '+style+'>'+window.hWin.HEURIST4.util.htmlEscape(value)+'</div>';
        }
        
        //ugr_ID,ugr_Type,ugr_Name,ugr_Description, ugr_eMail,ugr_FirstName,ugr_LastName,ugr_Enabled,ugl_Role
        
        const recID   = fld('rty_ID');
        if(this.options.import_structure){

            const recTitle = fld2('rty_Name','15em')
                + ' : <div class="item" style="font-style:italic;width:45em">'
                + window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, 'rty_Description'))+'</div>'

            
            //IJ dwi
            let rtIcon = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'icon', 2, this.options.import_structure.database);
            rtIcon = '';
            
            
            let html_icon = '<div class="recordIcons" style="min-width:16px;text-align:center;">'
            +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
            +     '"  class="rt-icon" style="background-image: url(&quot;'+rtIcon+'&quot;);">'
            + '</div>';
            
            
            let html = '<div class="recordDiv" recid="'+recID+'">'
            + '<div class="recordSelector"><input type="checkbox" /></div>'
            + html_icon
            + '<div class="recordTitle recordTitle2" title="'+fld('rty_Description')
                            +'" style="right:10px">'
            +     recTitle
            + '</div>';
            
            return html;
        }

        // Skip items in the Trash group, unless in manager mode
        const isTrash = (recordset.fld(record, 'rty_RecTypeGroupID') == $Db.getTrashGroupId('rtg'));
        if(isTrash && this.options.select_mode!='manager'){
            return '';
        }
        
        let rtIcon = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'icon');
        //let recThumb = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'thumb');
        if(this.is_new_icons){
            rtIcon = window.hWin.HAPI4.iconBaseURL+recID; 
            //recThumb = window.hWin.HAPI4.iconBaseURL+recID+'&version=thumb'; 
        }
		
        let random_id = window.hWin.HEURIST4.util.random(); // force php request to redo		
        
        //let html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+recThumb+'&'+random_id+'&quot;);">'
        //+'</div>';
        
        //recordIcons 
        let html_icon = '<div class="item" style="vertical-align: middle;max-width:40px;min-width:40px;text-align:center">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '"  class="rt-icon" style="background-image: url(&quot;'+rtIcon+'&'+random_id+'&quot;);">'       //opacity:'+recOpacity+'
        + '</div>';        

        let html = '';
        
        let fields = this.options.ui_params?this.options.ui_params.fields:['rtyid'];

        //fields = ['rtyid','ccode','addrec','filter','count','group','icon','edit','editstr','name','description','show','duplicate','fields','status'];        

        function __action_btn(action, icon, title, color){
            if(!color) color = '#555555';            
            let sbg = '';
            
            return '<div class="item" style="min-width:34px;max-width:34px;text-align:center;'+sbg+'">'
                    +'<div title="'+title+'" '
                    +'class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" '
                    +'role="button" aria-disabled="false" data-key="'+action+'" style="height:18px;">'
                    +     '<span class="ui-button-icon-primary ui-icon '+icon+'" style="color:'+color+'"></span>'
                    + '</div></div>'            
        }
        
        let used_width = 0;

        let grayed = '';
        let i = 0;
        for (;i<fields.length;i++){
            
            if(fields[i]!='name' && fields[i]!='description'){
                used_width = used_width + this.fields_width[fields[i]] + 4;    
            }
            
            switch ( fields[i] ) {
                case 'rtyid': html += fld2('rty_ID',30,null,'text-align:center'); break;
                case 'ccode': 
                    html += ('<div class="item truncate" style="min-width:80px;max-width:80px;text-align:center">'
                            +$Db.getConceptID('rty',recID,true)+'</div>');
                    break;
                case 'addrec': 
                    html += __action_btn('addrec','ui-icon-plus',window.hWin.HR('Click to add new')+' '+fld('rty_Name'));    
                    break;
                case 'filter':
                    html += __action_btn('filter','ui-icon-search',window.hWin.HR('Click to launch search for')+' '+fld('rty_Name'));
                    break;
                case 'count': 
                    html += fld2('rty_RecCount',40,null,'text-align:center  '); break;
                case 'group': 
                    html += __action_btn('group','ui-icon-carat-d', 'Change group' );
                    break;                         
                case 'icon': 
                    html += html_icon; 
                    break;
                case 'edit':  
                    html += __action_btn('edit','ui-icon-pencil',window.hWin.HR('Click to edit record type'));
                    break;
//                case 'editstr': 
                case 'name':  
                    html += '$$NAME$$'; 
                    break;
                case 'description':  
                    html += '$$DESC$$';
                    break;
                case 'show': 
                
                    if(recordset.fld(record, 'rty_ShowInLists')==1){
                        html += __action_btn('hide_in_list','ui-icon-check-on',window.hWin.HR('Click to hide in lists'));    
                    }else{
                        html += __action_btn('show_in_list','ui-icon-check-off',window.hWin.HR('Click to show in lists'));
                        grayed = 'background:lightgray';
                    }
                    
                    break;
                case 'duplicate': 
                    html += __action_btn('duplicate','ui-icon-copy',window.hWin.HR('Duplicate record type'));
                    break;
                case 'fields': 
                    html += __action_btn('fields','ui-icon-circle-b-info',window.hWin.HR('List of fields'));
                    break;
                case 'status': 
                
                    if(recordset.fld(record, 'rty_Status')=='reserved'){
                        html += __action_btn('','ui-icon-lock',window.hWin.HR('manageDefRectypes_reserved'),'gray');
                    }else{
                        if(recordset.fld(record,'rty_RecCount')>0){
                            html += __action_btn('delete_hasrecs','ui-icon-trash-b',window.hWin.HR('manageDefRectypes_hasrecs'), 'darkgray');
                        }else{
                            //let links = window.hWin.HAPI4.EntityMgr.getEntityData('rst_Links')
                           
                            const is_referenced = !window.hWin.HEURIST4.util.isnull(this.rst_links[recID]);
                                        //(this.rst_links.reverse[recID] || this.rst_links.rel_reverse[recID]); 
                            if(is_referenced){
                                html += __action_btn('delete','ui-icon-trash-b', window.hWin.HR('manageDefRectypes_referenced'));    
                            }else{
                                html += __action_btn('delete','ui-icon-trash', window.hWin.HR('manageDefRectypes_delete'));
                            }
                        }
                    }    
                
                    break;
            }    
        }
        
        //if Description presents it takes all possible width
        // otherwise this is Name
        let max_width = this.recordList.find('.div-result-list-content').width() 
                - ((this.options.select_mode=='select_multi') ?40:27);

        let w_desc = 0, i_desc = fields.indexOf('description');
        if(i_desc>=0){
            w_desc = max_width-used_width-250;
            if(w_desc<30) w_desc = 30;
            
            html = html.replace('$$DESC$$', fld2('rty_Description',null,null,
                    'min-width:'+w_desc+'px;max-width:'+w_desc+'px;font-style:italic;font-size:smaller')); 
        }
        
        let name_width = (this.options.select_mode=='select_multi')?400:250;
        html = html.replace('$$NAME$$',fld2('rty_Name', name_width, null,'text-align:left'))

        html = '<div class="recordDiv rt_draggable white-borderless" recid="'
            +recID+'" style="display:table-row;height:28px;padding:0.4em 0px;'+grayed+'">'
                    + '<div class="recordSelector item"><input type="checkbox" /></div>'
                    + html+'</div>';
        
/*        
        const has_buttons = (this.options.select_mode=='manager' && this.options.edit_mode=='popup');

        let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'">'
        + html_thumb
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + html_icon
        + '<div class="recordTitle recordTitle2" title="'+fld('rty_Description')
                        +'" style="right:'+(has_buttons?'60px':'10px')
                        + (this.options.import_structure?';left:30px':'')+'">'
        +     recTitle
        + '</div>';
        
        // add edit/remove action buttons in record lisr
        if(has_buttons){
        
                
               html = html 
                + '<div class="rec_actions" style="top:4px;width:120px;">'
                    + '<div title="Click to edit record type" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="edit" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                    + '</div>&nbsp;'
                    + '<div title="Click to edit structure" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="structure" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-list"></span><span class="ui-button-text"></span>'
                    + '</div>&nbsp;'
                    + '<div title="Duplicate record type" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="duplicate" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-copy"></span><span class="ui-button-text"></span>'
                    + '</div>&nbsp;'
                    + '<div title="List of fields" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="fields" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-b-info"></span><span class="ui-button-text"></span>'
                    + '</div>&nbsp;'
                    + '<div title="Click to delete record type" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false" data-key="delete" style="height:16px">'
                    +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                    + '</div></div>';
        }
        

        html = html + '</div>';
*/
        return html;
        
    },
    
    //
    //
    //
    _onActionListener:function(event, action){
        
        if(action && (action.action=='delete' || action.action=='delete_hasrecs')){

			if(action.action == 'delete_hasrecs'){

                const rectype_id = action.recID;
                let $ele = $(event.target);

                const sMsg = 'The record type <b>'+ $Db.rty(rectype_id, 'rty_Name') +'</b> has existing records.<br>These records must be deleted in order to delete this record type.<br><br>'
                    + 'Click the <a href="#" data-rty_ID="'+ rectype_id +'">Filter</a> button or use Explore > Entities to find and delete all records.';

                let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(sMsg, null, {title: 'Warning'}, {default_palette_class:this.options.default_palette_class});

                this._on($dlg.find('a[data-rty_ID]'),{click:function(e){

                    $ele.find('div[recid="'+ rectype_id +'"]').find('div[data-key="filter"]').trigger('click');

                    $dlg.dialog('close');
                    return false;
                }});

                return;
            }else if(!window.hWin.HEURIST4.util.isnull(this.rst_links[action.recID])){

                let res = this.rst_links[action.recID];
                /*
                if(this.rst_links.reverse[action.recID])
                    res = res.concat(Object.keys(this.rst_links.reverse[action.recID]));
                if(this.rst_links.rel_reverse[action.recID])
                    res = res.concat(Object.keys(this.rst_links.rel_reverse[action.recID]));
                */    
                let sList = '';
                for(let i=0; i<res.length; i++) if(res[i]!='all' && res[i]>0){
                    sList += ('<a href="#" data-dty_ID="'+res[i]+'">'+$Db.dty(res[i],'dty_Name')+'</a><br>');
                }
                
                let sMsg = window.hWin.HR('manageDefRectypes_delete_stop');
                
                sMsg = sMsg.replaceAll( '[rtyName]', $Db.rty(action.recID,'rty_Name'));
                sMsg = sMsg.replaceAll( '[FieldList]', sList);
                
                let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(sMsg
                , null, {title: 'Warning'},
                {default_palette_class:this.options.default_palette_class});        
                
                this._on($dlg.find('a[data-dty_ID]'),{click:function(e){
                    
                    let rg_options = {
                         isdialog: true, 
                         edit_mode: 'editonly',
                         select_mode: 'manager',
                         rec_ID: $(e.target).attr('data-dty_ID'),
                         onSelect:function(res){
                         }
                    };
                    window.hWin.HEURIST4.ui.showEntityDialog('defDetailTypes', rg_options);
                    return false;                    
                }});
                
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
                
                if(action=='addrec'){
                    
                    let new_record_params = {RecTypeID: recID};
                    window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                        {new_record_params:new_record_params,
                        onClose:function(){
                            /*   
                            if($Db && $Db.needUpdateRtyCount){
                                $Db.get_record_counts(function(){
                                    that._triggerRefresh('rty');
                                });                                
                            }
                            */
                        }});
                    
                }else if(action=='filter'){
                    
                    window.hWin.HAPI4.RecordSearch.doSearch( this, 
                        {q:'{"t":"'+recID+'"}',detail:'ids', source:this.element.attr('id')} );
                    if(this.options.parent_dialog == null){
                        $('.ui-menu6').slidersMenu('switchContainer', 'explore', true);
                    }
                   
                    
                //}else if(action=='group'){
                    
                }else if(action=='editstr'){
                    //edit structure (it opens fake record and switches to edit structure mode)
                    let new_record_params = {RecTypeID: recID};
                    let opts = {new_record_params:new_record_params, edit_structure:true};

                    if(this.it_was_insert && this.options.parent_dialog !== null){
                        opts['parent_dialog'] = this.options.parent_dialog;
                    }

                    window.hWin.HEURIST4.ui.openRecordEdit(-1, null, opts);
                }else if(action=='show_in_list' || action=='hide_in_list'){
                    
                    
                    let newVal = (action=='show_in_list')?1:0;
                    this._saveEditAndClose({rty_ID:recID, rty_ShowInLists:newVal });
                    
                }else if(action=='duplicate'){
                    
                    this._duplicateType(recID);
                    
                }else if(action=='fields'){
                    //show selectmenu with list of fields
                    if(this.fieldSelectorLast!=recID){
                        this.fieldSelectorLast   = recID;
                        let details = $Db.rst(recID); //get all fields for given rectype
                        if(!details) return;
                        let options = [];
                        details.each2(function(dty_ID, detail){
                            if($Db.dty(dty_ID,'dty_Type')!='separator')
                                options.push({key:dty_ID, title:detail['rst_DisplayName']});
                        });
                        
                        if(!this.fieldSelector){
                            this.fieldSelectorOrig = document.createElement("select");    
                            $(this.fieldSelectorOrig).appendTo(this.element);
                            window.hWin.HEURIST4.ui.fillSelector(this.fieldSelectorOrig, options);
                            this.fieldSelector = window.hWin.HEURIST4.ui.initHSelect(this.fieldSelectorOrig, false);
                            
                            let menu = this.fieldSelector.hSelect( "menuWidget" );
                            menu.css({'max-height':'350px'});                        
                                this.fieldSelector.hSelect({change: function(event, data){
                                let rg_options = {
                                     isdialog: true, 
                                     edit_mode: 'editonly',
                                     select_mode: 'manager',
                                     rec_ID: data.item.value,
                                     onSelect:function(res){
                                     }
                                };
                                window.hWin.HEURIST4.ui.showEntityDialog('defDetailTypes', rg_options);
    
                            }});
                            
                        }else{
                            $(this.fieldSelectorOrig).empty();
                            window.hWin.HEURIST4.ui.fillSelector(this.fieldSelectorOrig, options);
                            this.fieldSelector.hSelect('refresh');
                        }
                    }
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

    },
    
    
    _deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this.deleted_from_group_ID = $Db.rty(this._currentEditID,'rty_RecTypeGroupID');
            this._super(); 
        }else{
            this.deleted_from_group_ID = 0;
            let that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'manageDefRectypes_delete_warn '
                , function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'},
                {default_palette_class:this.options.default_palette_class});        
        }
    },
    
    //
    //
    //
    _afterDeleteEvenHandler: function(recID){

        
            this._super(recID);
            
            this.updateGroupCount(this.deleted_from_group_ID, -1);
            
            //select first
            this.selectRecordInRecordset();
            
    },
    
    //-----
    //
    // adding group ID value for new rectype
    // open select icon dialog for new record
    //
    _afterInitEditForm: function(){

        this._super();
        
        let rty_RecTypeGroupID = this.options.rtg_ID;
        if(this._currentEditID<0){ //rty_RecTypeGroupID>0 && !this._currentEditRecordset){ //insert       

        
            if(!(rty_RecTypeGroupID>0)){ //take first from list of groups
                rty_RecTypeGroupID = $Db.rtg().getOrder()[0];                
            }
        
            let ele = this._editing.getFieldByName('rty_RecTypeGroupID');
            if(rty_RecTypeGroupID>0) ele.editing_input('setValue', rty_RecTypeGroupID);
            
            //open select icon
            ele = this._editing.getFieldByName('rty_Icon');
            ele.editing_input('openIconLibrary');
            
            //hide save button
            if(this._toolbar){
                this._toolbar.find('.btnRecSave').css('visibility', 'visible');
            }
            
            //hide title mask
            ele = this._editing.getFieldByName('rty_TitleMask');
            ele.editing_input('setValue', 'Please edit any <b>XXXXX</b> record to choose fields for the constructed title'); //'record [ID]'
            ele.hide();
            
        }else{
            
            let ele = this._editing.getFieldByName('rty_ID');
            if(ele && ele.length>0){
                ele.find('div.input-div').html(this._currentEditID
                        +'&nbsp;&nbsp;<span style="font-weight:normal">Code: </span>'
                        +$Db.getConceptID('rty',this._currentEditID, true));
            }
            let that = this;

            function __extendTitleMaskInput(){
                
                let ele_mask = that._editing.getFieldByName('rty_TitleMask');
                let inputs = ele_mask.editing_input('getInputs');
                let $input = inputs[0];

                $input.removeClass('text').attr('readonly','readonly');

                let $btn_editmask = $( '<span>', {title: window.hWin.HR('Edit Title Mask')})
                .addClass('smallicon ui-icon ui-icon-pencil')
                .insertAfter( $input );

                that._on( $btn_editmask, { click: function(){

                    let maskvalue = ele_mask.editing_input('getValues');
                    maskvalue = maskvalue[0];

                    window.hWin.HEURIST4.ui.showRecordActionDialog('rectypeTitleMask',
                        {rty_ID:that._currentEditID, 
                            rty_TitleMask:maskvalue, path: 'widgets/entity/popups/',
                            onClose: function(newvalue){
                                if(!window.hWin.HEURIST4.util.isempty(newvalue) && newvalue != 'false'){
                                    ele_mask.editing_input('setValue', newvalue);
                                    that._editing.setModified(true); //restore flag after autosave
                                    that.onEditFormChange();
                                }
                    }});


                }} );
                
            }

            // extent editing for record title
            let ele_mask = that._editing.getFieldByName('rty_TitleMask');
            if(ele_mask){
                ele_mask.editing_input('option', 'onrecreate', __extendTitleMaskInput);
                __extendTitleMaskInput();
            }
            
            //
            // add edit structure button
            //
            if(this.options.suppress_edit_structure!==true){
                
            let $s = $('<div style="margin: 15px 0 20px 175px;'
            +'padding: 10px 10px 5px;display: block;">'
            +'<div class="input-cell"><span style="display:inline-block"><button></button></span>'
            +'<span class="heurist-helper3" style="display:inline-block;vertical-align: middle;padding-left: 20px;">'
            + window.hWin.HR('manageDefRectypes_edit_fields_hint')
            +'</span>'
            +'</div></div>');
            
            let edit_ele = this._editing.getFieldByName('rty_ShowURLOnEditForm');
            $s.insertAfter(edit_ele);
        
            let btn = $s.find('button');
            let new_record_params = {RecTypeID: this._currentEditID};
            btn.button({icon:'ui-icon-pencil',label: window.hWin.HR('manageDefRectypes_edit_fields')})
                            .css({'font-weight': 'bold','font-size':'12px'})
                            .addClass('ui-heurist-button')
                            .width(150)
                            .on('click', function(){
                                //close this form and open edit structure
                                function __openEditStructure(){
                                    that._currentEditID = null;
                                    that._getEditDialog(true).dialog('close');
                                    
                                    window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                                        {new_record_params:new_record_params, 
                                            edit_structure:true});
                                }
                                
                                if(that._editing.isModified()){
                                    that._saveEditAndClose( null , __openEditStructure );
                                }else{
                                    __openEditStructure();
                                }
                            });
            }
            
            let edit_ele = this._editing.getFieldByName('rty_ID');
            $('<div style="display:block"><span style="margin-top: 15px;display: inline-block;font-size: 12px;">'
            + window.hWin.HR('Define')+': </span><h1 style="display: inline-block;margin:10px;">'
            +$Db.rty(this._currentEditID,'rty_Name')+'</h1></div>').insertBefore(edit_ele);            
        }
        
        let ishelp_on = (this.usrPreferences['help_on']==true || this.usrPreferences['help_on']=='true' || this._currentEditID == -1);
        let ele = $('<div style="position:absolute;right:6px;top:4px;"><label><input type="checkbox" '
                        + (ishelp_on?'checked':'')+'/>'
                        + window.hWin.HR('explanations')
                        + '</label></div>').prependTo(this.editForm);
        this._on( ele.find('input'), {change: function( event){
            let ishelp_on = $(event.target).is(':checked');
            this.usrPreferences['help_on'] = ishelp_on;
            window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, this.editForm, '.heurist-helper1');
        }});
        
        ele = this._editing.getInputs('rty_Name');
        if(ele && ele.length>0){
            this._on( $(ele[0]), {
                keypress: window.hWin.HEURIST4.ui.preventChars} );
        }
        
        window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, this.editForm, '.heurist-helper1');
        
        this.editForm.find('div.header').css({'min-widht':'160px', width:'160px'});
        
        this._adjustEditDialogHeight();

        if(this.options.select_mode=='manager'){
            this.mergeIconThumbnailFields();
        }
    },   

    //
    // Combine Icon and Thumbnail fields into one, in popup form
    //
    mergeIconThumbnailFields: function(){
        // fields
        let $icon = this._editing.getFieldByName('rty_Icon');
        let $thumb = this._editing.getFieldByName('rty_Thumb');
        
        if(!$thumb || !$icon){
            return; //not yet loaded
        }
        $thumb.hide();
        let thumb_header = $thumb.find('div.header.optional > label').text(); // thumbnail header
        
        // Alter icon field
        $icon.css('width', '');

        $icon.find('div.image_input').css({
            'min-width': '50px',
            'min-height': '50px',
            'margin-left': '25px',
            'vertical-align': 'top' // for Firefox
        }); // make visual smaller

        $icon.find('div.header.optional').append($('<br><label>'+ thumb_header +'</label>')); // add thumbnail header text to icon header
        $icon.find('div.heurist-helper1').text('Images to represent this record type'); // replace help text

        // Move library and upload links
        $icon.find('.file-options-container')
             .insertBefore($icon.find('div.image_input.fileupload'))
             .css('padding-top', '4px');

        // Move thumbnail field
        let $thumb_img = $thumb.find('div.image_input');
        $thumb_img.css('margin-left', '25px').insertAfter($icon.find('div.image_input.fileupload'));

        // Hide open folder icon, usually is hidden by icon image
        $icon.find('.input-div span:first').hide();

        // link fields
        $icon.editing_input('linkIconThumbnailFields', $thumb_img, $thumb);
        $thumb.editing_input('linkIconThumbnailFields', $icon.find('div.image_input'), $icon);
        // link clear buttons, only icon clear button is visible
        this._on($icon.find('span.btn_input_clear'), {
            'click': function(){
                $thumb.find('span.btn_input_clear').trigger('click');
            }
        });
    },

    onEditFormChange: function(changed_element){
        
       this._super(changed_element);
       
       if(changed_element && changed_element!==true 
            && this._editing.isModified() 
            && changed_element.options.dtID=='rty_Name')
       {
            let val = this._editing.getValue('rty_Name');
            let ele = this._editing.getInputs('rty_Plural');
            $(ele[0]).val(val+'s');
           
       }
    },    
        
    //
    // show warning
    //
    addEditRecord: function(recID, is_proceed){
    
        if(recID<0 && is_proceed !== true){
            let that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                    'manageDefRectypes_new_hint'
                    , function(){
                        that.addEditRecord(recID, true); 
                        //that._super(recID); 
                    }, {title: 'Confirm', yes:'Continue',no:'Cancel'},
                    {default_palette_class:this.options.default_palette_class});
        
        }else{
               this._super(recID); 
        }
    },
	
    //
    // listener of onfilter event generated by searchEtity. appicable for use_cache only       
    //
    filterRecordList: function(event, request){ 
        
        let results = this._super(event, request);

        if(results!=null && results.count_total()==0){
            
            if(this.options.select_mode=='manager'){
                
                let sMsg;
                let s_all = this.element.find('#chb_show_all_groups').is(':checked');
                if(!s_all){
                    sMsg = '<div style="margin-top:5em;">'
                        +'<b>There are no record types (entity types) defined within this group.</b>'
                        +'<br><br>Please drag record types from other groups or add new record types to this group.'
                        +'<br><br>We suggest renaming the "My record types" group to something that suits your project.'
                        +'</div>';   
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
    
    //overwritten     NOT USED
    _recordListGetFullData:function(arr_ids, pageno, callback){

        let request = {
                'a'          : 'search',
                'entity'     : this.options.entity.entityName,
                'details'    : 'list',
                'pageno'     : pageno,
                'db'         : this.options.database  
                
        };
        let rty_RecTypeGroupID = this.searchForm.find('#input_search_group').val();
        if(rty_RecTypeGroupID>0){
            request['rty_RecTypeGroupID'] = rty_RecTypeGroupID;
        }
        
        
        request[this.options.entity.keyField] = arr_ids;
        window.hWin.HAPI4.EntityMgr.doRequest(request, callback);
    },
    
    

    //
    // @todo
    //
    /*
    testTitleMask: function()
    {
        if(!rectypeID || rectypeID < 0){
            let val = "record [ID]";
            if(document.getElementById("definit").checked && window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME']){
                val = "["+ $Db.dty(window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'], 'dty_Name') +"]";
            }

            document.getElementById("rty_TitleMask").value = val
            titleMaskIsOk = true;
            updateRectypeOnServer_continue();

        }else{

            let mask = document.getElementById("rty_TitleMask").value;
            
            let baseurl = window.hWin.HAPI4.baseURL + "hserv/controller/rectype_titlemask.php";

            let request = {rty_id:rectypeID, mask:mask, db:window.hWin.HAPI4.database, check:1}; //verify titlemask
            
            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, 
                function (response) {
                    if(response.status != window.hWin.ResponseStatus.OK || response.message){
                        titleMaskIsOk = false;
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }else{
                        titleMaskIsOk = true;
                        updateRectypeOnServer_continue();
                    }                                        
                }
            );

        }
    },
    */

    _saveEditAndClose: function(fields, afterAction, onErrorAction){

        if(window.hWin.HAPI4.is_callserver_in_progress()) {
            //prevent repeatative call
            return;   
        }

        if(!fields){
            fields = this._getValidatedValues(); 
            if(fields) fields['isfull'] = 1;
        }

        if(fields==null) return; //validation failed

        if(fields['rty_TitleMask'] && fields['rty_TitleMask'].indexOf('<b>XXXXX</b>') != -1) { // add rectype name to default title mask
            fields['rty_TitleMask'] = fields['rty_TitleMask'].replace('XXXXX', fields['rty_Name']);
        }

        this._super(fields, afterAction, onErrorAction);
    },
    
    //
    // update list after save (refresh)
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){

        // close on addition of new record in select_single mode    
        //this._currentEditID<0 && 
        if(this.options.select_mode=='select_single'){
            
                this._selection = new HRecordSet();
               
                this._selection.addRecord(recID, fieldvalues);
                this._selectAndClose();
                this._triggerRefresh('rty');
                return;    
        }
        
        //add to local definitions
        $Db.rty().setRecord(recID, fieldvalues);
        this._super( recID, fieldvalues );
        
        if(this.it_was_insert){
            this.searchForm.searchDefRecTypes('startSearch'); //refresh

            if(this.options.select_mode=='select_multi'){ // auto select new rectype, and force close
                this.recordList.find('div[recid="'+ recID +'"]').trigger('click');
                this._selectAndClose();
            }

            this._addInitialTabs(recID);
        }
        this._triggerRefresh('rty');
        
/*        
        this.getRecordSet().setRecord(recID, fieldvalues);
        
        if(this.options.edit_mode == 'editonly'){
            this.closeDialog(true); //force to avoid warning
        }else if(this._currentEditID<0){
            this.searchForm.searchDefRecTypes('startSearch');
        }else{
            this.recordList.resultList('refreshPage');  
        }
*/        
    },

	//
	// Display dialog with set of default tab options for new rectypes
	//
    _addInitialTabs: function(rty_ID){

        let that = this;
        let $dlg;
        let msg = '';

        let dty_ids = [];
        let def_tabs = ['General Info', 'General', 'Identification', 'Description', 'Categorisation', 'Localisation', 'Dating', 'Images', 'Links', 'References'];

        $Db.dty().each(function(dty_ID, rec){
           if($Db.dty(dty_ID,'dty_Type')=='separator'){
               dty_ids.push(dty_ID);
           } 
        });

        msg = 'Unless the record type is very simple with few fields, we suggest using tabs to<br>organsie the fields describing this record type.<br>'
                + 'Please choose from this list of frequently used headings (tabs can be added or<br>removed later, and the labels can be easily edited).<br><br>';

        for(let i = 0; i < def_tabs.length; i++){

            let checked = '';
            if(i == 0){
                checked = ' checked="true"';
            }

            msg += '<label><input type="checkbox" value="'+ def_tabs[i] +'"'+ checked +'>'+ def_tabs[i] +'</label><br>';
        }

        let btns = {};
        btns['Create tabs'] = function(){

            let headings = [];

            let $checked_opts = $dlg.find('input:checked');
            
            if($checked_opts.length == 0){ // no options
                window.hWin.HEURIST4.msg.showMsgFlash('Please select a suggested tab, or click "No tabs"', 3000);
            }

            for(let j = 0; j < $checked_opts.length; j++){
                headings.push($($checked_opts[j]).val());
            }

            $dlg.dialog('close');

            if(headings.length > dty_ids.length){ // not enough ids for selected number of headers, create new separators
                that._makeAdditionalHeaders(rty_ID, headings, dty_ids.length, $Db.dty(dty_ids[0], 'dty_DetailTypeGroupID'));
            }else{ // add selected headers to new rectype
                that._addNewFields(rty_ID, headings);
            }
        };
        btns['No tabs'] = function(){
            that._addNewFields(rty_ID, null);
            $dlg.dialog('close');
        };

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, {title: 'Suggested tabs', yes: 'Create tabs', no: 'No tabs'}, {default_palette_class: 'ui-heurist-design'});
    },

	//
	// Create additional separators if more are needed for selected default headers
	//
    _makeAdditionalHeaders: function(rty_ID, headings, cur_dty_count, dtg_id){

        let that = this;

        cur_dty_count++;

        let fields = {
            dty_DetailTypeGroupID: dtg_id,
            dty_ID: -1,
            dty_Name: 'Header '+ cur_dty_count +' - edit the name', // give default name
            dty_HelpText: '',
            dty_NonOwnerVisibility: "viewable",
            dty_Status: "open",
            dty_Type: "separator",
            dty_ShowInLists: 0
        };
            
        let request = {
            a: 'save',
            entity: 'defDetailTypes',
            'fields': fields
        };

        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){

                    let dty_ID = response.data[0];
                    fields['dty_ID'] = dty_ID;

                    $Db.dty(dty_ID, null, fields); //update cache

                    if(headings.length > cur_dty_count){
                        that._makeAdditionalHeaders(rty_ID, headings, cur_dty_count, dtg_id); // create additional separators
                    }else{
                        that._addNewFields(rty_ID, headings); // move forward with adding tabs
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }

            });

    },

    //
    // Add default tab separators to new record type
    //    
    _addNewFields: function(rty_ID, tab_headings){
      
		this._selected_fields = {};
		
		this._selected_fields['fields'] = [];
		this._selected_fields['values'] = {};
		
		let that = this;

        if(!window.hWin.HEURIST4.util.isempty(tab_headings)){
    		
            let i = 0;
    		$Db.dty().each(function(dty_ID, rec){
    		    if($Db.dty(dty_ID,'dty_Type')=='separator'){

                    if(i == 0){
                        that._selected_fields['fields'].unshift(dty_ID);
                        that._selected_fields['values'][dty_ID] = {dty_Name: tab_headings[i], rst_DefaultValue: 'tabs'};
                    }else{                    
                        that._selected_fields['fields'].push(dty_ID);
                        that._selected_fields['values'][dty_ID] = {dty_Name: tab_headings[i], rst_DefaultValue: 'tabs'};
                    }
                    i++;

                    if(i >= tab_headings.length) return false;
    		    } 
    		});
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront();
        window.hWin.HEURIST4.msg.coverallKeep = true;
        window.hWin.HEURIST4.msg.showMsgFlash('loading structure', false);
                   
		let request = {};
		request['a']        = 'action'; //batch action
		request['entity']   = 'defRecStructure';
		request['rtyID']    = rty_ID;
		request['newfields']  = that._selected_fields;
		request['request_id'] = window.hWin.HEURIST4.util.random();
		
		window.hWin.HAPI4.EntityMgr.doRequest(request, 
			function(response){
                
				if(response.status == window.hWin.ResponseStatus.OK){
					
                    //@todo since it adds only separators - we may update local definitions without request for full update
                    
					//refresh local defs and show edit structure popup
					window.hWin.HAPI4.EntityMgr.getEntityData('defRecStructure', true,
					function(){
                        //open edit structurre
						that._onActionListener(null, {recID:rty_ID, action:'editstr'} );
					});
					
				}else{
					window.hWin.HEURIST4.msg.showMsgErr(response);      
				}
			}
		);
    },

    //
    //
    //
    updateGroupCount:function(rtg_ID,  delta){
    /*
        if(rtg_ID>0){
            let cnt = parseInt($Db.rtg(rtg_ID,'rtg_RtCount'));
            cnt = (isNaN(cnt)?0:cnt)+delta;
            if(cnt<0) cnt = 0;
            $Db.rtg(rtg_ID,'rtg_RtCount',cnt);
            this._triggerRefresh('rtg');
        }
    */
    },    
    
    //
    //
    //
    getUiPreferences:function(){
        this.usrPreferences = window.hWin.HAPI4.get_prefs_def('prefs_'+this._entityName, this.defaultPrefs);
        return this.usrPreferences;
    },
    
    saveUiPreferences:function(new_params){

        if(this.options.select_mode=='select_multi' || this.options.select_mode=='select_single') return;
        
        if(new_params){
            let params = this.getUiPreferences();
            
            params['fields'] = new_params['fields']; 
        
            window.hWin.HAPI4.save_pref('prefs_'+this._entityName, params);
            
            this.usrPreferences = params;
        }
    },
    
    //
    // update ui and call save prefs
    //
    changeUI: function( event, params ){
        
        if(this.options.edit_mode=='editonly') return;

        if(event) this.saveUiPreferences( params );
        
        this.options.ui_params = window.hWin.HEURIST4.util.cloneJSON( this.usrPreferences);
        
        //refresh result list
        this.recordList.resultList('applyViewMode','list', true);
        this.recordList.resultList('refreshPage');
        
    },
    
    
    //
    // duplicate record type and then call edit type dialogue
    //
    _duplicateType: function (rectypeID) {

        window.hWin.HEURIST4.msg.showMsgDlg(
        window.hWin.HR('manageDefRectypes_duplicate_warn')+$Db.rty(rectypeID,'rty_Name')+'?'
        , function(){ 
                
        
                function _editAfterDuplicate(response) {

                    if(response.status == window.hWin.ResponseStatus.OK){

                        let rty_ID = Number(response.data.id);
                        if(rty_ID>0){   
                            //refresh the local heurist
                            window.hWin.HAPI4.EntityMgr.refreshEntityData('rty,rst',function(){
                                 window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);
                            }); 

                        }
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }                                        
                }
                
                //@todo change to sys_structure controller
                let baseurl = window.hWin.HAPI4.baseURL + "hserv/structure/edit/duplicateRectype.php";
                window.hWin.HEURIST4.util.sendRequest(baseurl, { rtyID:rectypeID }, null, _editAfterDuplicate);

        }, {title:'Confirm',yes:'Continue',no:'Cancel'},
        {default_palette_class:this.options.default_palette_class});
    },


    // Change group for rectype
    // params:  {rty_ID:rty_ID, rty_RecTypeGroupID:rtg_ID }
    //                                
    changeRectypeGroup: function(params){                                    
        window.hWin.HEURIST4.msg.bringCoverallToFront(this.recordList);

        let that = this;
        this._saveEditAndClose( params ,
            function(){
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                that.searchForm.searchDefRecTypes('startSearch');
                that._triggerRefresh('rty');
                /*
                window.hWin.HAPI4.EntityMgr.refreshEntityData('rtg',
                    function(){
                        that._triggerRefresh('rtg');
                    }
                )*/
        });
    },

    importRecordTypes: function(){

        const that = this;

        let sURL = `${window.hWin.HAPI4.baseURL}import/delimited/importDefRecTypes.php?db=${window.hWin.HAPI4.database}&rtg_ID=${this.options.rtg_ID}`;

        window.hWin.HAPI4.SystemMgr.user_log('imp_RecordTypes');

        window.hWin.HEURIST4.msg.showDialog(sURL, {
            default_palette_class: 'ui-heurist-design',
            'close-on-blur': false,
            'no-resize': false,                  
            title: 'Import Record types by CSV',
            height: 800,
            width: 1000,
            context_help: 'defRecTypes #import',
            callback: function(context){ 

                if(context && context.result){

                    that._loadData(false);

                    let msg = context.result;;
                    if(Array.isArray(context.result) || context.result.refresh_terms){

                        msg = '<strong>Definitions imported</strong>, report:<br><br>';
                        msg += context.result.join('<br>');    
                    }

                    window.hWin.HEURIST4.msg.showMsgDlg(msg, null, 'Record types imported',
                        {default_palette_class:that.options.default_palette_class});
                }
            }
        });
    }

});
