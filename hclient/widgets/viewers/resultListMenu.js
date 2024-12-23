/**
* Menu for result list
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

$.widget( "heurist.resultListMenu", {

    // default options
    options: {
        is_h6style: false,
        // callbacks
        menu_class:null,
        resultList: null,  //reference to parent resultList
        search_realm: null
    },

    _query_request: {},   //keep current query request
    _selection: null,     //current set of selected records (not just ids)

    // the widget's constructor
    _create: function() {

        let that = this;

        this.element
        .css('font-size', this.options.is_h6style?'1.2em':'1.3em')
        // prevent double click to select text
        .disableSelection();
        
        this.divMainMenuItems = $('<ul>').addClass('horizontalmenu')
            //.css({'dispaly':'table-row'})
            .appendTo(this.element);

        this._initMenu('Selected');
        this._initMenu('Collected','Collect');
        this._initMenu('Recode');
        this._initMenu('Shared','Share');
        if(this.options.resultList){
            this._initMenu('Reorder');  
            this.options.search_realm = this.options.resultList.resultList('option', 'search_realm');
        } 
       
       
        this.divMainMenuItems.menu();

        this.divMainMenuItems.find('li').css({'padding':'0 3px 3px', 'width':'100px', 'text-align':'center'}); // center, place gap and setting width
        
        if(this.options.menu_class!=null){
             this.element.addClass( this.options.menu_class );   
        }else{
            this.divMainMenuItems.find('.ui-menu-item > a').addClass('ui-widget-content');    
        }

       
        this.divMainMenuItems.children('li').children('a').children('.ui-icon').css({right: '2px', left:'unset'});
       
        
        //-----------------------     listener of global events
        let sevents = window.hWin.HAPI4.Event.ON_CREDENTIALS+' '
                 +window.hWin.HAPI4.Event.ON_REC_SEARCHSTART+' '
                 +window.hWin.HAPI4.Event.ON_REC_COLLECT+' '
                 +window.hWin.HAPI4.Event.ON_REC_SELECT;

        $(window.hWin.document).on(sevents, function(e, data) {

            if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS){

                that._refresh();

            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){

                if(data && !data.reset && that._isSameRealm(data)) {
                    that._query_request = jQuery.extend({}, data); //keep current query request
                }

            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_COLLECT){
                
                that.collectionRender( data.collection );
                
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){

                if(data && data.source!=that.element.attr('id') && that._isSameRealm(data)) {

                    if(data.reset){
                        that._selection = null;
                    }else{
                        that._selection = window.hWin.HAPI4.getSelection(data.selection, false);
                    }
                    window.hWin.HAPI4.currentRecordsetSelection = that.getSelectionIds();
                }
            }
           
        });

        this._refresh();

        //get collection
        window.hWin.HEURIST4.collection.collectionUpdate();

    }, //end _create

    //
    //
    //
    _isSameRealm: function(data){
        return (!this.options.search_realm && (!data || window.hWin.HEURIST4.util.isempty(data.search_realm)))
        ||
        (this.options.search_realm && (data && this.options.search_realm==data.search_realm));
    },

    
    // Any time the widget is called with no arguments or with only an option hash,
    // the widget is initialized; this includes when the widget is created.
    _init: function() {

    },
    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    _setOptions: function( ) {
        this._superApply( arguments );
    },

    /*
    * private function
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

        if(window.hWin.HAPI4.has_access()){

            this.menu_Selected.find('.logged-in-only').show();
            this.menu_Collected.find('.logged-in-only').show();
            this.btn_Recode.show();
            this.btn_Shared.show();
            this.btn_Reorder.show();

            this['menu_Recode'].find('.logged-in-only:not([data-user-experience-level])').show();
           
            
            function ___set_menu_item_visibility(i,item){
                item = $(item);
                let lvl_user =  item.attr('data-user-admin-status');
                let is_visible = window.hWin.HAPI4.has_access(lvl_user);
                let elink = item.find('a');
                if(is_visible){
                    window.hWin.HEURIST4.util.setDisabled(elink, false);
                    item.attr('title', '');
                }else{
                    window.hWin.HEURIST4.util.setDisabled(elink, true);
                    
                    item.attr('title', 'Only for '
                    + (item.attr('data-user-admin-status')==2?'the database owner':'database managers')
                    + ' allowed');
                }
            }
            
            this['menu_Recode'].find('li[data-user-admin-status]').each(___set_menu_item_visibility);
            this['menu_Shared'].find('li[data-user-admin-status]').each(___set_menu_item_visibility);
            
            
        }else{
           
            this.menu_Selected.find('.logged-in-only').hide();
            this.menu_Collected.find('.logged-in-only').hide();
            this.btn_Recode.hide();
            this.btn_Shared.hide();
            this.btn_Reorder.hide();
			
            this['menu_Recode'].find('.logged-in-only:not([data-user-experience-level])').hide();			
        }
    },
    //
    // custom, widget-specific, cleanup.
    _destroy: function() {

        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_CREDENTIALS+' '
            +window.hWin.HAPI4.Event.ON_REC_SEARCHSTART+' '
            +window.hWin.HAPI4.Event.ON_REC_COLLECT+' '
            +window.hWin.HAPI4.Event.ON_REC_SELECT); 

        // remove generated elements
        if(this.btn_Search){
            this.btn_Search.remove();
            this.menu_Search.remove();
        }
        this.btn_Selected.remove();
        this.menu_Selected.remove();
        this.btn_Collected.remove();
        this.menu_Collected.remove();
        this.btn_Recode.remove();
        this.menu_Recode.remove();
        this.btn_Shared.remove();
        this.menu_Shared.remove();
        this.btn_Reorder.remove();
        this.divMainMenuItems.remove();
    },

    //
    //
    //
    _initMenu: function(name, menu_label, competency_level){

        let that = this;
        let myTimeoutId = -1;

        //show hide functions
        let _hide = function(ele) {
            myTimeoutId = setTimeout(function() {
                $( ele ).hide();
                }, 1);
           
        };
        
        let _show = function(ele, parent) {
            clearTimeout(myTimeoutId);

            $('.menu-or-popup').hide(); //hide other
            let menu = $( ele )
            //.css('width', this.btn_user.width())
            .show()
            .position({my: "left top", at: "left bottom", of: parent, collision:'none' });
           

            return false;
        };

        let link = $('<a href="#"'
                +(this.options.is_h6style?' style="padding-right:22px !important"':'')
                +'>'+window.hWin.HR(menu_label?menu_label:name)+'</a>')
       
        
        if(this.options.is_h6style){
           
            if(name=='Reorder'){
                $('<span class="ui-icon ui-icon-signal">').css({'transform':'rotate(90deg)'}).appendTo(link);  //caret-1-s
            }else{
                $('<span class="ui-icon ui-icon-carat-d">').appendTo(link);  //caret-1-s
            }
        }
        

        if(name=='Collected'){
            this.menu_Collected_link = link;
        }

        this['btn_'+name] = $('<li>').append(link)
        .appendTo( this.divMainMenuItems );
        
        
        let usr_exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);
        
        if(competency_level>=0){
            this['btn_'+name].addClass('heurist-competency'+competency_level);    
            if(usr_exp_level>competency_level){
                this['btn_'+name].hide();    
            }
        }

        if(name=='Reorder'){
            
            this['btn_'+name].attr('title', window.hWin.HR('menu_reorder_hint'));
            
            
            this._on( this['btn_'+name], {
                click : function(){
                    if(!this.isResultSetEmpty()){
                       $(this.options.resultList).resultList('setOrderAndSaveAsFilter'); 
                    }
                }
            });
            
        }else{
        
            this['menu_'+name] = $('<ul>')                               //add to avoid cache in devtime '?t='+(new Date().getTime())
            .load(window.hWin.HAPI4.baseURL+'hclient/widgets/viewers/resultListMenu'+name+'.html', function(){  
                
                let content = that['menu_'+name].find('ul');
                if(content.length>0){
                    that['menu_'+name].html(content.html());
                }
                
                that['menu_'+name].addClass('menu-or-popup')
                .css('position','absolute')
                .appendTo( that.document.find('body') )
                //.addClass('ui-menu-divider-heurist')
                .menu({
                    icons: { submenu: "ui-icon-circle-triangle-e" },
                    select: function(event, ui){ 
                    event.preventDefault(); 
                    that.menuActionHandler(ui.item.attr('id')); 
                    return false; }});

                if(window.hWin.HAPI4.has_access()){
                    that['menu_'+name].find('.logged-in-only').show();
                }else{
                    that['menu_'+name].find('.logged-in-only').hide();
                }
                
                that['menu_'+name].find('li[data-user-experience-level]').each(function(){
                    if(usr_exp_level > $(this).data('exp-level')){
                        $(this).hide();    
                    }else{
                        $(this).show();    
                    }
                });

                //localization                
                that['menu_'+name].find('li[id^="menu-"]').each(function(){
                    let menu_id = $(this).attr('id');
                    let item = $(this).find('a');
                    item.text(window.hWin.HR( menu_id ));
                    const hint = window.hWin.HR( menu_id+'-hint');
                    if(hint!=(menu_id+'-hint')){
                        item.attr('title',hint);    
                    }
                });
                
                that['menu_'+name].find('li').css('padding-left',0);
                
                
            })
            //.position({my: "left top", at: "left bottom", of: this['btn_'+name] })
            .hide();

            //{select: that.menuActionHandler}
            


            this._on( this['btn_'+name], {
                mouseenter : function(){_show(this['menu_'+name], this['btn_'+name])},
                mouseleave : function(){_hide(this['menu_'+name])}
            });
            this._on( this['menu_'+name], {
                mouseenter : function(){_show(this['menu_'+name], this['btn_'+name])},
                mouseleave : function(){_hide(this['menu_'+name])}
            });

        }
        
    },


    menuActionHandler: function(action){

        let that = this;

        if(action == "menu-selected-select-all"){

            this.selectAll();

        }else if(action == "menu-selected-select-none"){

            this.selectNone();

        }else if(action == "menu-selected-select-insearch"){

            this.selectShow();

        }else if(action == "menu-selected-select-show"){  //show selection as separate search

            this.selectShowNewTab();

        }else if(action == "menu-selected-merge"){  //show add relation dialog

            this.fixDuplicatesPopup();

        }else if(action == "menu-selected-tag" || action == "menu-selected-bookmark" || action == "menu-selected-wgtags"){
           
            if(this.isResultSetEmpty()) return;
            
            let opts = {
                width:700,
                groups: (action == "menu-selected-bookmark")?'personal':'all',
                onClose:
                   function( context ){
                       if(context){
                           //refresh search page
                           that.reloadSearch(); //@todo reloadPage                   
                       }
                   }
            };
            if(action == "menu-selected-bookmark"){
                opts['modes'] = ['bookmark'];
            }
            
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordTag', opts);

        }else if(action == "menu-selected-unbookmark"){

            if(this.isResultSetEmpty()) return;
            
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordBookmark', {onClose:
                   function( context ){
                       if(context){
                           //refresh search page
                           that.reloadSearch(); //@todo reloadPage                   
                       }
                   }
            });

        }else if(action == "menu-selected-rate"){

            if(this.isResultSetEmpty()) return;
            
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordRate');

        }else if(action == "menu-selected-delete"){

            if(this.isResultSetEmpty()) return;
            
            window.hWin.HAPI4.currentRecordsetSelection = this.getSelectionIds( window.hWin.HR('resultList_select_record') );
            if(window.hWin.HEURIST4.util.isempty(window.hWin.HAPI4.currentRecordsetSelection)) return;

            window.hWin.HAPI4.SystemMgr.verify_credentials(() => {

                window.hWin.HEURIST4.ui.showRecordActionDialog('recordDelete', {onClose:
                    function( context ){
                        if(context){
                            // refresh search
                            that.reloadSearch();
                        }
                    }
                });

            }, 0, null, null, 'delete');

        }else if(action == "menu-selected-email") {

            if(this.isResultSetEmpty()) return;
            
            this.openEmailForm();

        }else if(action == "menu-selected-ownership"){

            if(this.isResultSetEmpty()) return;
            
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordAccess', {height:450, width:540, show_modes: true,
                onClose: function( context ){
                    if(context){
                       //@todo refresh page
                       that.reloadSearch();
                    }
                }
            });

        }else if(action == "menu-selected-notify"){
            
            if(this.isResultSetEmpty()) return;

            window.hWin.HEURIST4.ui.showRecordActionDialog('recordNotify');

        }else if(action == "menu-selected-value-add"){

            this.detailBatchEditPopup('add_detail');

        }else if(action == "menu-selected-value-replace"){

            this.detailBatchEditPopup('replace_detail');

        }else if(action == "menu-selected-url-to-file"){

            this.detailBatchEditPopup('url_to_file');
            
        }else if(action == "menu-selected-local-to-repository"){

            this.detailBatchEditPopup('local_to_repository');

        }else if(action == "menu-selected-reset-thumbs"){

            this.detailBatchEditPopup('reset_thumbs', function(){
                that.reloadSearch();
            });

        }else if(action == "menu-selected-case-conversion"){

            this.detailBatchEditPopup('case_conversion');

        }else if(action == "menu-selected-nl2br"){

            this.detailBatchEditPopup('nl2br');

            
        }else if(action == "menu-selected-translation"){

            
            if(!window.hWin.HAPI4.sysinfo.api_Translator){
                window.hWin.HEURIST4.msg.showMsg(
                    '<span style="display:inline-block;margin-top:10px;">'
                                        + 'To enable automatic translation please ask your system administrator to<br>'
                                        + 'add a Deepl free or paid account API key to Heurist configuration'
                                     + '</span>');                
            }else{
                this.detailBatchEditPopup('translation');    
            }

        }else if(action == "menu-selected-value-delete"){

            this.detailBatchEditPopup('delete_detail');

        }else if(action == "menu-selected-add-link"){

            if(this.isResultSetEmpty()) return;
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordAddLink');
            
        }else if(action == "menu-selected-add-link-match"){

            if(this.isResultSetEmpty()) return;
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordAddLinkMatch');
            
        }else if(action == "menu-selected-extract-pdf"){

            this.detailBatchEditPopup('extract_pdf');
            
        }else if(action == "menu-selected-rectype-change"){

            this.detailBatchEditPopup('rectype_change');

        }else if(action == "menu-collected-add"){

            window.hWin.HEURIST4.collection.collectionAdd(null, this._selection);
            this.selectNone();

        }else if(action == "menu-collected-del"){

            window.hWin.HEURIST4.collection.collectionDel(null, this._selection);
            this.selectNone();

        }else if(action == "menu-collected-clear"){

            window.hWin.HEURIST4.collection.collectionClear();

        }else if(action == "menu-collected-show"){

            this.options.resultList.resultList('displayCollection', true);

        }else if(action == "menu-collected-tab"){

            window.hWin.HEURIST4.collection.collectionShow();

        }else if(action == "menu-collected-save"){

            window.hWin.HEURIST4.collection.collectionSave();
        
        }
//@todo? move to main menu
        else if(action == "menu-subset-set"){
            
            if(!window.hWin.HAPI4.currentRecordset ||
                    window.hWin.HAPI4.currentRecordset.length()==0)
            {
                        
                window.hWin.HEURIST4.msg.showMsg(
                '<p>The working subset is created from your current query results. You have no query results, so no subset was created.</p>' 
                +'<p>Please run a query which returns the set of records you wish to treat as the working subset and select this function again.</p>');
                    
            }else if(window.hWin.HAPI4.currentRecordset.length()>window.hWin.HAPI4.sysinfo.db_total_records*0.8){
                
                window.hWin.HEURIST4.msg.showMsg(
                '<p>You are trying to make a subset of everything or nearly everything (>=99%) of records  in the database. This does not make much sense.</p>'  
+'<p>Please apply a filter which returns the subset you wish to work with and select this function again.</p>');
                
            }else {
            
                let scope = window.hWin.HAPI4.currentRecordset.getIds();
                
                window.hWin.HAPI4.SystemMgr.user_wss({ids:scope.join(',')},
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            window.hWin.HAPI4.sysinfo.db_workset_count = response.data;
                            that.options.resultList.resultList('refreshSubsetSign');
                            window.hWin.HEURIST4.msg.showMsgFlash(response.data+' records has been added to work subset');
                            
                            $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CUSTOM_EVENT, 
                                {closeFacetedSearch:true, userWorkSetUpdated:true, 
                                    source:that.element.attr('id'), search_realm:that.options.search_realm} );
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response, true);
                        }
                    });
                    
            }
            

        }else if(action == "menu-subset-clear"){

            window.hWin.HAPI4.SystemMgr.user_wss({clear:1},
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        window.hWin.HAPI4.sysinfo.db_workset_count = 0;
                        that.options.resultList.resultList('refreshSubsetSign');
                        
                        $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CUSTOM_EVENT, 
                                {userWorkSetUpdated:true, 
                                    source:that.element.attr('id'), search_realm:that.options.search_realm} );
                        
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response, true);
                    }
                });
                
        }
    },

    //
    //
    // 
    reloadSearch: function(query){

        if(!window.hWin.HEURIST4.util.isempty(query)){
            this._query_request.q = query;
        }

        this._query_request.id = null;
        this._query_request.source = this.element.attr('id');
        window.hWin.HAPI4.RecordSearch.doSearch( this, this._query_request );
    },
    
    //
    // clean details for current recordset and force refresh current page
    // it will be faster than perform search again
    //
    reloadPage: function(){
    
    },
    
    //
    //
    //
    getSelectionIds: function(msg, limit){

        let recIDs_list = [];
        if (this._selection!=null) {
            recIDs_list = this._selection.getIds();
        }

        if (recIDs_list.length == 0 && !window.hWin.HEURIST4.util.isempty(msg)) {
            window.hWin.HEURIST4.msg.showMsg(msg, {default_palette_class:'ui-heurist-explore'});
            return null;
        }else if (limit>0 && recIDs_list.length > limit) {
            window.hWin.HEURIST4.msg.showMsg(window.hWin.HR('resultList_select_record')+limit, {default_palette_class:'ui-heurist-explore'});
        }else{
            return recIDs_list;
        }

    },

    //-------------------------------------- EMAIL FORM -------------------------------
    openEmailForm: function() {
        // Selection check
        let ids = this.getSelectionIds('resultList_select_record');
        if(window.hWin.HEURIST4.util.isempty(ids)) {
            return;
        }

        // Open URL
        let url = window.hWin.HAPI4.baseURL+ "hclient/framecontent/send_email.php?db=" + window.hWin.HAPI4.database;
        window.hWin.HAPI4.selectedRecordIds = ids;  //the only place it is assigned
        window.hWin.HEURIST4.msg.showDialog(url, { width:500, height:600, title: window.hWin.HR('Email information') });

    },

    //-------------------------------------- SELCT ALL, NONE, SHOW -------------------------------

    selectAll: function(){
        this._selection = window.hWin.HAPI4.getSelection('all', false);
        $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
            {selection:"all", source:this.element.attr('id'), search_realm:this.options.search_realm} );
    },

    selectNone: function(){
        this._selection = null;
        $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
            {selection:null, source:this.element.attr('id'), search_realm:this.options.search_realm} );
    },

    selectShow: function(){
        if(this._selection!=null){
            let recIDs_list = this._selection.getIds();
            if(recIDs_list.length > 0){
                $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                    {selection: recIDs_list, source: this.element.attr('id'), search_realm: this.options.search_realm, subset_only: true});
            }
        }
    },

    selectShowNewTab: function(){
        if(this._selection!=null){
            let recIDs_list = this._selection.getIds();
            if (recIDs_list.length > 0) {
                let url = window.hWin.HAPI4.baseURL + "?db=" + window.hWin.HAPI4.database + "&q=ids:"+recIDs_list.join(',') + '&nometadatadisplay=true';
                window.open(url, "_blank");
            }
        }
    },

    //-------------------------------------- COLLECTIONS -------------------------------
    //
    // render counter
    //
    collectionRender: function(_collection) {
        
        this.menu_Collected_link.html( 
                (_collection && _collection.length>0?(window.hWin.HR('Collected')+':'+_collection.length):window.hWin.HR('Collect')) 
                + '<span class="ui-icon ui-icon-carat-d" style="right: 2px; left: unset;">');
    },

    //-------------------------------------- RELATION, MERGE -------------------------------
    fixDuplicatesPopup: function(){


        let recIDs_list = this.getSelectionIds(null);
        if(window.hWin.HEURIST4.util.isempty(recIDs_list) || recIDs_list.length<2){
            window.hWin.HEURIST4.msg.showMsg('resultList_select_record2',{default_palette_class:'ui-heurist-explore'});
            return;
        }

        let that = this;
        let url = window.hWin.HAPI4.baseURL 
                    + 'admin/verification/combineDuplicateRecords.php?bib_ids='
                    + recIDs_list.join(",")+"&db=" + window.hWin.HAPI4.database;

        window.hWin.HEURIST4.msg.showDialog(url, {
            width:800, height:550,
            default_palette_class:'ui-heurist-explore',
            title: window.hWin.HR('Combine duplicate records'),
            callback: function(context) {
                that.reloadSearch();
            }
        });

    },

    
    isResultSetEmpty: function(){
        let recIDs_all = window.hWin.HAPI4.getSelection("all", true);
        if (window.hWin.HEURIST4.util.isempty(recIDs_all)) {
            window.hWin.HEURIST4.msg.showMsg('resultList_noresult', {default_palette_class:'ui-heurist-explore'});
            return true;
        }else{
            return false;
        }
    },
    

    //------ ADD, REPLACE, DELETE FIELD VALUES, CHANGE RECTYPE -------------------------------
    //
    //  MAIN  in use 
    //  
    detailBatchEditPopup: function(action_type, callback) {
        
        if(this.isResultSetEmpty()) return;

        let script_name = 'recordAction';
        
        if(action_type=='add_link'){
            script_name = 'recordAddLink';
            callback = function(context) {
                        if(context!="" && context!=undefined) {
                            let sMsg = (context==true)?'Link created...':context;
                            window.hWin.HEURIST4.msg.showMsgFlash(sMsg, 2000);
                        }
            };            
        /*}else if(action_type=='ownership'){

            var that = this;
            callback = function(context) {
                        if(context!="" && context!=undefined) {
                                that.executeAction( "set_wg_and_vis", context );
                        }
            };*/            
        }else if(!window.hWin.HEURIST4.util.isFunction(callback)){
            callback = function(context){
                window.hWin.HAPI4.NEED_TAG_REFRESH = true; //flag to reload tags in next manageUsrTags invocation
            }
        }
        
        let url = window.hWin.HAPI4.baseURL + 'hclient/framecontent/'+script_name+'.php?'
                +'db='+window.hWin.HAPI4.database+'&action='+action_type;

        let height = action_type == 'case_conversion' ? 750 : 510;
        let width = 900;

        window.hWin.HEURIST4.msg.showDialog(url, {height:height, width:width,
            padding: '0px',
            title: window.hWin.HR(action_type),
            callback: callback,
            default_palette_class:'ui-heurist-explore'} );
    },


});
