/**
* Accordeon/treeview in navigation panel: saved, faceted and tag searches
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
/* global HSvsEdit */

$.widget( "heurist.svs_list", {

    // default options
    options: {
        is_h6style: false,

        btn_visible_filter: false, //filter for treeview
        btn_visible_save: false,

        buttons_mode: false,
        searchTreeMode: -1, //0-buttons, 1-tree, 2-full tree (all groups)
        allowed_UGrpID: [], // allowed groups
        allowed_svsIDs: [], // allowed searches - for buttons only
        init_svsID:null,    // launch search on init
        
        onclose_search:null,  //function to be called on close faceted search
        sup_filter:null,       //suplementary filter for faceted search
        
        menu_locked: null,
        hide_header: false,  //todo rename - inline main menu
        container_width:0,
        
        filter_by_type: 1,  //0 all, 1 filters only, 2 rules only

        handle_favourites: null, // function to add/remove favourite filters

        simple_search_allowed: 0, // enable 'search everything' filter
        simple_search_header: 'Simple search', // header text for 'search everything' filter
        simple_search_text: 'Search everything:', // field label for the simple search filter
        
        language: 'def',  //use default
        
        suppress_default_search: false, //if true prevents default search (init_svsID) execution - useful in cms

        hide_no_value_facets: true, // for facet searches, hide facets that have no available values
        
        search_page: null, //target page (for CMS) - it will navigate to this page and pass search results to search_realm group
        search_realm:  null  //accepts search/selection events from elements of the same realm only
        
    },

    isPublished: false,
    loaded_saved_searches: null,   //loaded searches for button mode - based on options.allowed_XXX
    missed_saved_searches: null,   //empty groups and missed filters for button mode
    svs_order: null,
    search_faceted: null,
    
    showclosebutton: true, //for faceted search
    
    groups_desc:{}, //cache for groups descriptions

    currentSearch: null,
    hSvsEdit: null,
    treeviews:{},

    _HINT_RULESET:'It does not perform the search. However it applies rules to current result set and  expand the initial search to a larger set of records by following a set of rules specifying which pointers and relationships to follow (including relationship type and target record types)',
    _HINT_WITHRULES:'Searches with addition of a RuleSet automatically expand the initial search to a larger set of records by following a set of rules specifying which pointers and relationships to follow (including relationship type and target record types)',
    _HINT_FACETED:'Faceted searches allow the user to drill-down into the database on a set of pre-selected database fields',

    // the constructor
    // create filter+button and div for tree
    _create: function() {

        let tab_td = this.element.parents('td');
        if(tab_td.length>0){
            $(tab_td[0]).css('height','1px');
        }
        
        if(!this.options.language) this.options.language = 'def'; //"xx" means use current language
        
        if(this.options.allowed_svsIDs && !Array.isArray(this.options.allowed_svsIDs)){
            if(window.hWin.HEURIST4.util.isNumber(this.options.allowed_svsIDs)){
                this.options.allowed_svsIDs = [this.options.allowed_svsIDs];
            }else{
                this.options.allowed_svsIDs = this.options.allowed_svsIDs.trim().replace(/\s+/g,'').split(',');    
            }
        }
       
        if(this.options.allowed_UGrpID && !Array.isArray(this.options.allowed_UGrpID)){
            this.options.allowed_UGrpID = this.options.allowed_UGrpID.trim().replace(/\s+/g,'').split(',');
        }
        
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.allowed_UGrpID))
            this._setOptionFromUrlParam('allowed_UGrpID', 'groupID');
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.allowed_svsIDs))
            this._setOptionFromUrlParam('allowed_svsIDs', 'searchIDs');

        this.isPublished = !this.options.is_h6style && 
            (window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.allowed_UGrpID) ||
             window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.allowed_svsIDs)
            || (this.options.searchTreeMode>=0));
            
            
        if( this.isPublished )
        {
            if(!(this.options.searchTreeMode>=0)) this.options.searchTreeMode = 0;
            
            this.options.buttons_mode = (this.options.searchTreeMode==0);
            
            if(this.options.searchTreeMode==2){   //full treeview
            
                if(window.hWin.HAPI4.has_access()){ //logged in
                    this.options.allowed_UGrpID = null;
                }else{
                    this.options.allowed_UGrpID = [4]; //web searches - by default
                }
            }
        }
            
        if(window.hWin.HAPI4.has_access() && this.options.buttons_mode){
            this._setOptionFromUrlParam('treeview_mode','treeViewLoggedIn', 'bool');
            if(this.options.treeview_mode){
                this.options.buttons_mode= false;
            }
        }

        let that = this;
        
        if(this.element.parent().attr('data-heurist-app-id')){
            //this is publication
            this.element.addClass('ui-widget-content').css({'background':'none','border':'none'});
        }
        
        this.element.parent().css({'overflow':'hidden'});
        
        //panel to show list of saved filters
        this.search_tree = $( "<div>" ).css('width','100%').appendTo( this.element );
        //panel to show faceted search when it is activated
        this.search_faceted = $( "<div>", {id:this.element.attr('id')+'_search_faceted'} )
                    .css({'height':'100%'}).appendTo( this.element ).hide();
        
        if(this.options.is_h6style){
            this.element.css({'overflow':'hidden'});
            //add title 
            this.div_header =  $('<div class="ui-heurist-header" style="top:0px;">'+window.hWin.HR('Saved Filters')+'</div>') 
            // <span style="font-style:italic;font-size:x-small">by workgroups</span>
                .hide()
                .appendTo(this.element);
                
            this.div_header_sub = $('<div style="top:46px;font-style:italic;font-size:9px;right:10px;height:auto;position: absolute;left: 20px;">'
                    +'</div>')
                .hide()
                .appendTo(this.element);
        
           
            
            this.accordeon = $( "<div>" )
                .addClass('svs-list-container')
                .css({'top':36, 'bottom':0, 'width':'100%','position': 'absolute', 'overflow-y': 'auto',
                        'overflow-x': 'hidden','font-size':'0.9em'})
                        .appendTo( this.search_tree );
                        
                        
        }else{
            this.element.css({'overflow-y':'auto','font-size':'0.8em'}).attr('data-fid','svs-list');
            
            this.div_header = $( "<div>" ).css({'width':'100%', 'padding-top':'1em', 'font-size':'0.9em'}) 
                                    .appendTo( this.search_tree );
            this.search_tree.css({'height':'100%','font-size':'1em'});

            let toppos = 1;

            if(window.hWin.HAPI4.sysinfo['layout']!='original' && !this.options.buttons_mode){
                toppos = toppos + 4;
                $('<div>'+window.hWin.HR('Saved Filters')+'</div>')
                .css({'padding': '0.5em 1em', 'font-size': '1.4em', 'font-weight': 'bold'})
                .addClass('svs-header')
                .appendTo(this.div_header);

                if(this.options.btn_visible_save){

                    this.btn_search_save = $( "<button>", {
                        label: window.hWin.HR('Save Filter'),
                        title: window.hWin.HR('Save the current filter and rules as a link in the navigation tree')
                    })
                    .css({'min-width': '110px','vertical-align':'top','margin-left': '32px','font-size':'1.2em', 'font-weight': 'bold'})
                    .addClass('ui-state-focus')
                    .appendTo(this.div_header)
                    .button({icon: 'ui-icon-circle-arrow-s'  //"ui-icon-disk"
                    })
                    .hide();

                    this._on( this.btn_search_save, {  click: function(){
                        window.hWin.HAPI4.SystemMgr.verify_credentials(function(){
                            that.editSavedSearch('saved');
                        });
                    } });

                    //toppos = toppos + 2.5
                }

                if(!this.isPublished){
                    /*
                    this.helper_top = $( '<div>'+window.hWin.HR('right-click in list for menu')+'</div>' )
                    .appendTo( $( "<div>" )
                        .css({'padding':'0.2em 0 0 1.2em','font-size':'1em','font-style':'italic'})
                        .addClass('svs-header')
                        .appendTo(this.div_header) );
                    */    
                }else{
                    toppos = 2;
                }
            }

            if(this.options.btn_visible_filter && !this.isPublished){

                this.filter_div = $( "<div>" ).css({'height':'2em', 'width':'100%'}).appendTo( this.search_tree );

                this.filter_input = $('<input name="search" placeholder="Filter...">')
                .css('width','100px').appendTo(this.filter_div);
                this.btn_reset = $( "<button>" )
                .appendTo( this.filter_div )
                .button({icon:"ui-icon-close",
                    title: window.hWin.HR("Reset"),
                    showLabel:false})
                .css({'font-size': '0.8em','height':'18px','margin-left':'2px'})
                .attr("disabled", true);
                
                this.btn_save = $( "<button>" )
                .appendTo( this.filter_div )
                .button({icon: "ui-icon-disk",
                    title: window.hWin.HR("Save"),
                    showLabel:false})
                .css({'font-size': '0.8em','height':'18px','margin-left':'2px'})

            }

            let hasHeader = ($(".header"+that.element.attr('id')).length>0);

            if(this.options.btn_visible_filter && !this.isPublished) toppos = toppos + 2;
            if(hasHeader) toppos = toppos + 2;

            if(this.options.buttons_mode){
               
                toppos = 1;
                if(this.filter_div) this.filter_div.hide();
                if(this.btn_search_save) this.btn_search_save.hide();
                this.options.btn_visible_filter = false;
            }
            
            //main container  toppos+'em'
            this.accordeon = $( "<div>" ).css({'top':0, 'bottom':0, 'left':'1em', 'right':'0.5em', 'position': 'absolute', 'overflow-y': 'auto',
                        'overflow-x':'hidden'}).appendTo( this.search_tree );
        }


        this.edit_dialog = null;

        if(this.options.btn_visible_filter && !this.isPublished){
            // listeners
            this.filter_input.on('keyup', function(e){
                let leavesOnly = true; //$("#leavesOnly").is(":checked"),
                let match = $(this).val();

                if(e?.which === $.ui.keyCode.ESCAPE || String(match).trim() === ''){
                    that.btn_reset.trigger('click');
                    return;
                }
                // Pass a string to perform case insensitive matching
                for (let groupID in that.treeviews)
                    if(groupID){
                        let n = that.treeviews[groupID].filterNodes(match, leavesOnly); //n - found
                }

                that.btn_reset.attr("disabled", false);
            });

            this._on( this.btn_reset, { click: function(){
                this.filter_input.val("");
                for (let groupID in this.treeviews)
                    if(groupID){
                        this.treeviews[groupID].clearFilter();
                }
            } });
            this._on( this.btn_save, { click: "_saveTreeData"} );
        }


        //global listener
        $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_CREDENTIALS, function(e, data) {
            that.accordeon.empty();
           
            that.helper_btm = null;
            that._refresh();
        });
        $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_CUSTOM_EVENT, function(e, data) {
            if(data && data.userWorkSetUpdated){
                that.refreshSubsetSign();
                that._adjustAccordionTop();
            }
        });
        $(this.document).on(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, function(e, data){
            if(data && !data.increment && !data.reset){
                that.currentSearch = window.hWin.HEURIST4.util.cloneJSON(data);
            }
        });
        $(this.document).on(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH, function(e, data){
                //show if there is resulst
                that.refreshSubsetSign();
                that._adjustAccordionTop();
            });


        this._refresh();
    }, //end _create

    //
    //
    //
    _adjustAccordionTop: function(){
        
        if(!this.accordeon) return;
        
        if(this.options.hide_header){
            this.div_header.hide();
            this.accordeon.css({'top':0});
        }else{
            this.div_header.show();
            
            let is_vis = -1;

            if(this.btn_search_save){
                if(window.hWin.HAPI4.currentRecordset && window.hWin.HAPI4.currentRecordset.length()>0)
                {
                    this.btn_search_save.show();
                    is_vis = 0;
                }else{
                    this.btn_search_save.hide();
                }
            }
            
            if(this.options.is_h6style){
                this.accordeon.css('top', 36);
            }else{
                let additional_height = 0;

                if(this.direct_search_div){
                    additional_height = this.direct_search_div.outerHeight();
                }

                this.accordeon.css('top', this.div_header.height() + additional_height + 5);
            }
        }
    },

    _setOption: function( key, value ) {
        this._super( key, value );
        /*
        if(key=='rectype_set'){
        this._refresh();
        }*/
        if(key=='onclose_search' && this.search_faceted && 
            window.hWin.HEURIST4.util.isFunction(this.search_faceted.search_faceted) && this.search_faceted.search_faceted('instance'))
        {
            this.search_faceted.search_faceted('option', 'onclose', value);
        }else if(key=='allowed_UGrpID' || key=='hide_header'){
            this._refresh();
        }else if(key=='filter_by_type'){
            this.div_header.text(window.hWin.HR(value=='2'?'RuleSets':'Saved Filters'));
            let that = this;            
            $(this.treeviews, function(groupID, tree){
                that._applyTreeViewFilter(groupID, value);            
            });
            if(this.helper_btm){
                if(value==2){
                    this.helper_btm.hide();
                }else{
                    this.helper_btm.show();
                }
            }
        }

    },

    _setOptionFromUrlParam: function( key, param_name, dtype ){

        let param_value = window.hWin.HEURIST4.util.getUrlParameter(param_name);
        //overwrite with param values
        if(!window.hWin.HEURIST4.util.isempty(param_value)){

            if(dtype=='bool'){
                this.options[key] = (param_value==1 || param_value=='true');
            }else{
                param_value = param_value.split(',');
                if(window.hWin.HEURIST4.util.isArrayNotEmpty(param_value)){
                    this.options[key] = param_value;
                }
            }

        }

    },

    /* private function */
    _refresh: function(){

        
        let that = this;
        
        if(!window.hWin.HAPI4.currentUser.ugr_Groups){
            window.hWin.HAPI4.currentUser.ugr_Groups = {}
        }
        
        if(this.isPublished){
            //add all groups 
            if(window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.allowed_UGrpID)){
                
                for (let i=0; i<this.options.allowed_UGrpID.length; i++){
                    if(!window.hWin.HAPI4.currentUser.ugr_Groups[this.options.allowed_UGrpID[i]]){
                        window.hWin.HAPI4.currentUser.ugr_Groups[this.options.allowed_UGrpID[i]] = 'member';
                    }    
                }
                
            }else if($.isEmptyObject(window.hWin.HAPI4.currentUser.ugr_Groups)){ //   !window.hWin.HAPI4.currentUser.ugr_Groups[4]
                //in published mode "Website searches" is always visible
                window.hWin.HAPI4.currentUser.ugr_Groups[4] = 'member';
            }
                
        }
        this._updateAccordeon();

    },

    //
    // save current treeview layout
    //
    _saveTreeData: function( groupToSave, treeData, callback ){
        
        if(this.isPublished || !window.hWin.HAPI4.has_access()) return; //do not save for not logged and publish mode

        let isPersonal = (groupToSave=="all" || groupToSave=="bookmark");

        if(!treeData){
            treeData = {};
            for (let groupID in this.treeviews)
                if(groupID){
                    if ( (isPersonal && isNaN(groupID)) || groupToSave == groupID){

                        let d = this.treeviews[groupID].toDict(true);
                        treeData[groupID] = d;
                    }
            }
        }
      
        
        let that = this;
        let request = { data:JSON.stringify(treeData) };
        window.hWin.HAPI4.SystemMgr.ssearch_savetree( request, function(response){

            if(response.status == window.hWin.ResponseStatus.OK){
                
                window.hWin.HAPI4.currentUser.ugr_SvsTreeData[groupToSave] = treeData[groupToSave];
                
                window.hWin.HAPI4.currentUser.ugr_SvsTreeData[groupToSave].modified = response.data;
                
                that._activateMenuAndTruncate( groupToSave );
                
                if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call(this);
                
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response, true);
            }

        } );
    },
    
    //
    //
    //
    reloadSavedSearches: function( callback ){
        
        let that = this;
        
        window.hWin.HAPI4.SystemMgr.ssearch_get( {UGrpID: this.options.allowed_UGrpID},
                function(response){

                    if(response.status == window.hWin.ResponseStatus.OK){
                        if(response.data.order && response.data.svs){
                            that.loaded_saved_searches = response.data.svs; //svs_id=>array()
                        }else{
                            that.loaded_saved_searches = response.data; //svs_id=>array()
                        }
                        /* IAN request 2022-09-19 Just display that 'Website filters' doesn't exist/has no filters
                        if(window.hWin.HEURIST4.util.isempty(that.loaded_saved_searches) &&
                            that.options.allowed_UGrpID.length==1 && that.options.allowed_UGrpID[0]==4){
                                //special case if allowed_UGrpID is #4 (Website filters) and this group is missed - replace it to group#2
                                that.options.allowed_UGrpID[0] = 1;
                                if(!window.hWin.HAPI4.currentUser.ugr_Groups[1]) {
                                    window.hWin.HAPI4.currentUser.ugr_Groups[1] = 'member';   
                                }
                                that.reloadSavedSearches(callback);
                                return;
                        }
                        */

                        window.hWin.HAPI4.currentUser.usr_SavedSearch = that.loaded_saved_searches
                        
                        if(that.options.buttons_mode){
                            that._updateAccordeon();
                        }else{
                            that.getFiltersTreeData(that.options.allowed_UGrpID, function(data){
                                window.hWin.HAPI4.currentUser.ugr_SvsTreeData = data; 
                                that._updateAccordeon();
                            });
                        }
                    }
                });
        
        
    },

    //
    // redraw accordeon - list of workgroups, all, bookmarked
    //
    _updateAccordeon: function(){

        // show saved searches as a list of buttons
        if(this.options.buttons_mode){
            this._updateAccordeonAsListOfButtons();
            return;
        }

        if(!window.hWin.HAPI4.currentUser.usr_SavedSearch 
            || !window.hWin.HAPI4.currentUser.ugr_SvsTreeData)
            //|| $.isEmptyObject( window.hWin.HAPI4.currentUser.usr_SavedSearch))
        {
            this.reloadSavedSearches();
            return;
        }
        
        if(this.accordeon){
           
        }
        if(this.helper_btm){
            this.helper_btm.remove()   
            this.helper_btm = null;
        }
        this.accordeon.empty();
        this.accordeon.hide();
        
        if (!this.helper_btm) {

            //new
            let t1 = '<div title="'+this._HINT_FACETED+'">'
            //+'<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'+'" style="background-image: url(&quot;'+window.hWin.HAPI4.baseURL+'hclient/assets/fa-cubes.png&quot;);vertical-align:middle">'
            +'<span class="ui-icon ui-icon-box" style="color:orange;display:inline-block; vertical-align: bottom; font-size:1em"></span>'
            +'&nbsp;Faceted search</div>'

            +'<div title="'+this._HINT_WITHRULES+'">'
            +'<span class="ui-icon ui-icon-shuffle" style="color:orange;display:inline-block; vertical-align: bottom; font-size:1em;width:0.9em;"></span>'
            +'&nbsp;&nbsp;Rules</div>';
            this.helper_btm = $( '<div class="heurist-helper3" style="float:right;padding:2.5em 0.5em 0 0;">'+t1+'</div>' )
            //IAN request 2015-06-23 .addClass('heurist-helper1')
            .appendTo( this.accordeon );
           
            
            if(this.options.filter_by_type==2){
                this.helper_btm.hide();    
            }
        }

        let islogged = (window.hWin.HAPI4.has_access());
        //if not logged in show only "my searches/all records"

        this.treeviews = {};

        let that = this;
       
/*        
        if( (!islogged || this.isPublished) && !window.hWin.HAPI4.currentUser.ugr_SvsTreeData){ //!(islogged || window.hWin.HAPI4.currentUser.ugr_SvsTreeData)){
        
            this.reloadSavedSearches();

            return;
            
        }else if(!$.ui.fancytree._extensions["dnd"]){
           
            alert('drag-n-drop extension for tree not loaded')
            return;
        }else if(!window.hWin.HAPI4.currentUser.ugr_SvsTreeData){ //not loaded yet - load from sysUgrGrps.ugr_NavigationTree

            this.getFiltersTreeData( null, function(data){
                window.hWin.HAPI4.currentUser.ugr_SvsTreeData = data; 
                that._updateAccordeon();
            })

            return;
        }
*/

        this.refreshSubsetSign();
        
        this._adjustAccordionTop();
        
        
        if(!(this.options.container_width>0)){
            this.options.container_width = this.accordeon.width();
        }
        
            if(islogged && this.options.searchTreeMode!=1 && this.helper_btm){    
                
                this.helper_btm.before(
                    $('<div>')
                    .attr('grpid',  'bookmark').addClass('svs-acordeon')
                    .addClass('heurist-bookmark-search')  //need find in preferences
                    .css('display', (window.hWin.HAPI4.get_prefs('bookmarks_on')=='1')?'block':'none')
                    .append( this._defineHeader(window.hWin.HR('My Bookmarks'), 'bookmark'))
                    .append( this._defineContent('bookmark',this.options.container_width) ) );

                this.helper_btm.before(
                    $('<div>')
                    .attr('grpid',  'all').addClass('svs-acordeon')
                    //.css('border','none')
                    .append( this._defineHeader(window.hWin.HR('My Searches'), 'all'))
                    .append( this._defineContent('all',this.options.container_width) ));
                
            }
                
            let groups = window.hWin.HAPI4.currentUser.ugr_SvsTreeData;
            for (let groupID in groups)
            if(groupID>0){
                    //tree
                    if(this.options.searchTreeMode==1 && this.options.allowed_UGrpID.length>0 ) //show only allowed groups
                    {
                        if(window.hWin.HEURIST4.util.findArrayIndex(groupID, this.options.allowed_UGrpID)<0){
                            continue;
                        }
                    }
                
                    let name = window.hWin.HAPI4.sysinfo.db_usergroups[groupID];
                    if(!window.hWin.HEURIST4.util.isnull(name))
                    {   
                        this.helper_btm.before(
                            $('<div>')
                            .attr('grpid',  groupID).addClass('svs-acordeon')
                            .append( this._defineHeader(name, groupID))
                            .append( this._defineContent(groupID,this.options.container_width) ));
                        
                        //get description for user    
                        window.hWin.HAPI4.SystemMgr.user_get( { UGrpID: groupID},
                            function(response){
                                let  success = (response.status == window.hWin.ResponseStatus.OK);
                                if(success){
                                    that.element.find('div[grpid='+response.data['ugr_ID']+']').attr('title',
                                        that.options.edit_data = response.data['ugr_Description']);
                                }else{
                                    
                                }
                            }
                        );                            
                    }else if(this.isPublished){

                        this.helper_btm.before(
                            $('<div>')
                            .attr('grpid',  groupID).addClass('svs-acordeon')
                            .append( this._defineHeader('Group '+groupID+' not found', groupID))
                            .append( this._defineContent(groupID,this.options.container_width) ));
                        
                    }
            }//for


        //init list of accordions
        let keep_status = {};
        if(!this.isPublished){
            keep_status = window.hWin.HAPI4.get_prefs('svs_list_status');
            if(keep_status){
                keep_status = window.hWin.HEURIST4.util.isJSON(keep_status);   
            }
            if(!keep_status) {
                keep_status = { 1:true, 'all':true }; //expanded by default
            }
        }
        

        let cdivs = this.accordeon.find('.svs-acordeon');
        $.each(cdivs, function(i, cdiv){

            cdiv = $(cdiv);
            let groupid = cdiv.attr('grpid');
            
            
            cdiv.accordion({
                active: ( (that.isPublished || ( keep_status && keep_status[ groupid ] ))?0:false),
                header: "> h3",
                heightStyle: "content",
                collapsible: true,
                activate: function(event, ui) {
                    if(!that.isPublished){
                        //save status of accordions - expandad/collapsed
                        if(ui.newHeader.length>0 && ui.oldHeader.length<1){ //activated
                            keep_status[ ui.newHeader.attr('grpid') ] = true;
                        }else{ //collapsed
                            keep_status[ ui.oldHeader.attr('grpid') ] = false;
                        }
                        //save
                        window.hWin.HAPI4.save_pref('svs_list_status', JSON.stringify(keep_status));
                    }
                    //replace all ui-icon-triangle-1-s to se
                    cdivs.find('.ui-icon-triangle-1-e').removeClass('ui-icon-triangle-1-se');
                    cdivs.find('.ui-icon-triangle-1-s').removeClass('ui-icon-triangle-1-s').addClass('ui-icon-triangle-1-se');

                }
            });
            
            //context menu for group header
            if(!this.isPublished){
                let context_opts = that._getAddContextMenu(groupid);
                cdiv.contextmenu(context_opts);
            }

            
            //replace all ui-icon-triangle-1-s to se
            cdivs.find('.ui-accordion-header-icon').css('font-size','inherit');
            cdivs.find('.ui-icon-triangle-1-s').removeClass('ui-icon-triangle-1-s').addClass('ui-icon-triangle-1-se');

        });

        this.accordeon.show();

        // add search everything above tree, only for cms widget
        if(this.isPublished && this.options.simple_search_allowed){
            this.addSearchEverything(false);
            this._adjustAccordionTop();
        }

        //
        if(this.isPublished && this.options.init_svsID && !this.options.suppress_default_search){
            this.doSearchByID( this.options.init_svsID );
        }
    },
    
    //
    //  
    //
    _updateTreeViewByGroup: function(){
        
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.allowed_UGrpID)) return;
        
        let groupID = this.options.allowed_UGrpID[0];
        
        let treeData = window.hWin.HAPI4.currentUser.ugr_SvsTreeData[groupID];
        
        if(!treeData) return;
        
        let name = treeData.title;
        
        if (groupID>0 && groupID!=window.hWin.HAPI4.currentUser.ugr_ID) {
            name = window.hWin.HAPI4.sysinfo.db_usergroups[groupID];        
            let that = this;

            function __asjustSubHeader(desc){            
                    if(!window.hWin.HEURIST4.util.isempty(desc)){
                        that.div_header_sub.empty().text(desc);
                        that.div_header_sub.show();
                        setTimeout(function(){
                        that.search_tree.css({ top:(that.div_header_sub.height()+46) });    
                        },50);
                    }else{
                        that.search_tree.css({ top:36 });    
                        that.div_header_sub.hide();
                    }
            }
            if(window.hWin.HEURIST4.util.isnull(that.groups_desc[groupID]))
            {
            
            //search for group description
            let request = {
                'a'          : 'search',
                'entity'     : 'sysGroups',
                'details'    : 'full',
                'ugr_ID'     : groupID
            };
            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    let desc = '';
                    if(response.status == window.hWin.ResponseStatus.OK){
                        let resp = new HRecordSet( response.data );
                        
                        let rec = resp.getFirstRecord();
                        if(rec){
                            desc = resp.fld(rec, 'ugr_Description');
                            if(window.hWin.HEURIST4.util.isempty(desc)){
                                desc  = resp.fld(rec, 'ugr_LongName');    
                            }
                        }

                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                    that.groups_desc[groupID] = desc;
                    __asjustSubHeader(desc);
                }
            );
            }else{
                __asjustSubHeader( that.groups_desc[groupID] );
            }
            
        }else if (groupID=='all' || groupID=='bookmark'){
            name = window.hWin.HR((groupID=='bookmark')?'My Bookmarks':'My Searches');
            this.div_header_sub.text('These filters are only visible to you').show();
            this.search_tree.css({top:56});    
        }else{
            this.search_tree.css({top:36});    
            this.div_header_sub.hide();
        }
        
        this.div_header.text( name );
        
        this._off(this.search_tree,'click');
        this.search_tree.empty();
        
        //create treeview
        this._defineContent( groupID, this.element.parent().width(), this.search_tree );        
        
        this.search_tree.find('.ui-fancytree').css('padding',0);
    },

    //
    //
    //
    refreshSubsetSign: function(){
        
        if(this.div_header && !this.options.is_h6style){

            let container = this.div_header.find('div.subset-active-div');
            
            if(container.length==0){
                let ele = $('<div>').addClass('subset-active-div').css({'padding':'0 30px 10px 40px'})
                      .insertBefore($(this.div_header.children()[0]));
            }
            container.find('span').remove();
         
            if(window.hWin.HAPI4.sysinfo.db_workset_count>0){
                $('<span style="padding:.4em 1em 0.3em;background:white;color:red;vertical-align:sub;font-size: 11px;font-weight: bold;"'
                  +' title="'+window.hWin.HAPI4.sysinfo.db_workset_count+' records"'
                  +'>'+window.hWin.HR('SUBSET ACTIVE')+' n='+window.hWin.HAPI4.sysinfo.db_workset_count+'</span>')
                    .appendTo(container);
            }
        }
    },
    
    //
    // draw list of buttons (for publish mode)
    //
    _updateAccordeonAsListOfButtons: function(){
        
        let that = this;
        if(!this.loaded_saved_searches){  //find all saved searches for current user
        
            that.svs_order = null;
        
            if(this.options.allowed_svsIDs.length>0 || this.options.allowed_UGrpID.length>0){

                //if allowed_svsIDs is defined it has precendece over allowed_UGrpID
                window.hWin.HAPI4.SystemMgr.ssearch_get( {svsIDs: this.options.allowed_svsIDs,
                    UGrpID: this.options.allowed_UGrpID, keep_order:true},
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            
                            if(response.data.order && response.data.svs){
                                that.svs_order = response.data.order;
                                that.loaded_saved_searches = response.data.svs; //svs_id=>array()
                            }else{
                                that.loaded_saved_searches = response.data; //svs_id=>array()
                                that.svs_order = Object.keys(that.loaded_saved_searches);
                            }
                            
                            let svsID = Object.keys(that.loaded_saved_searches);
                            that.missed_saved_searches = [];
                            //verify
                            for(let i=0; i<that.options.allowed_svsIDs.length; i++){
                                if(window.hWin.HEURIST4.util.findArrayIndex(that.options.allowed_svsIDs[i],svsID)<0){
                                    that.missed_saved_searches.push(that.options.allowed_svsIDs[i]);
                                }
                            }
                            /* old way - now this message in the end of the list
                            if(missed.length>0){
                                window.hWin.HEURIST4.msg.showMsgErr(
                                'Saved filter'+(missed.length>1?'s':'')+' (ID '
                                + missed.join(', ')
                                + ') specified in parameters '
                                + (missed.length>1?'does':'do')+' not exist in the database.<br><br>Please advise the database owner ('
                                + window.hWin.HAPI4.sysinfo['dbowner_email'] +')');
                            }
                            */
                            
                            that._updateAccordeonAsListOfButtons();
                        }
                });
                
                return;
            }else{
                this.loaded_saved_searches = [];
            }
        }

        this.accordeon.hide();
        this.accordeon.css('top',this.options.hide_header?0:this.div_header.height());
        this.accordeon.empty();
        this.search_tree.css('overflow','hidden');
        if(this.direct_search_div) {
            this.direct_search_div.remove();
            this.direct_search_div = null;    
        }
        
        
        let i, svsIDs = this.svs_order, //Object.keys(this.loaded_saved_searches),
            visible_cnt = 0, visible_svsID;

            
        if(svsIDs && svsIDs.length>0){ 
            
           

            for (i=0; i<svsIDs.length; i++)
            {
                let svsID = svsIDs[i];
                
                let params = window.hWin.HEURIST4.query.parseHeuristQuery(this.loaded_saved_searches[svsID][Hul._QUERY]);

                let iconBtn = 'ui-icon-search';
                if(params.type==3){
                    iconBtn = 'ui-icon-box';
                }else {
                    if(params.type==1){ //with rules
                        iconBtn = 'ui-icon-shuffle'; //ui-icon-plus 
                    }else if(params.type==2){ //rules only
                        iconBtn = 'ui-icon-shuffle';
                    }else  if(params.type<0){ //broken empty
                        iconBtn = 'ui-icon-alert';
                    }
                }


                let sname = window.hWin.HRJ('ui_name', params, this.options.language);
                if(window.hWin.HEURIST4.util.isempty(sname)){
                    sname = this.loaded_saved_searches[svsID][Hul._NAME];
                } 
                
                if(sname.toLowerCase().indexOf('placeholder')===0) continue;

                let shint = window.hWin.HRJ('ui_notes', params, this.options.language);

                $('<button>', {'data-svs-id':svsID})
                .attr('title', shint)
                .css({'width':'100%','margin-top':'0.8em','max-width':'300px','text-align':'left'})
                .button({label: sname, icon: iconBtn}).on("click", function(event){

                    let svs_ID = $(this).attr('data-svs-id');
                    if (svs_ID){
                        let qsearch = that.loaded_saved_searches[svs_ID][Hul._QUERY];
                        let qname   = that.loaded_saved_searches[svs_ID][Hul._NAME];
                        
                        that.showclosebutton = !($(this).attr('data-only-one')==1);

                        that.doSearch( svs_ID, svs_ID, qsearch, event.target ); //qname replaced with svs_ID
                        that.accordeon.find('#search_query').val('');
                    }
                })
                .appendTo(this.accordeon);
                $('<br>').appendTo(this.accordeon);

                visible_svsID = svsID;
                visible_cnt++;
            }//for

           
            $(this.accordeon).css({'overflow':'hidden',position:'unset','padding':'4px'});
            $(this.accordeon).parent().css({'overflow-y':'auto'});

            if(this.options.simple_search_allowed){ // add search everything below buttons
                this.addSearchEverything(true);
            }
        }
        else{
            
            $('<span style="padding:10px">No filters defined</span>').appendTo(this.accordeon);
        }
        this.accordeon.show();
        
        //if the only search - start search at once
        if(!this.options.suppress_default_search){
            if(visible_cnt==1){//this.loaded_saved_searches &&
                let btn = $(this.accordeon).find('button[data-svs-id="'+visible_svsID+'"]');
                btn.attr('data-only-one',1).trigger('click'); //only one is visible
            }else if(this.options.init_svsID){
                $(this.accordeon).find('button[data-svs-id="'+this.options.init_svsID+'"]').trigger('click');
            }
        }
        
        //messages for not found groups and filters
        if(this.missed_saved_searches && this.missed_saved_searches.length>0){
            
            $('<span style="padding:10px;" class="heurist-helper3">'
                    +this._getNotFoundMessage(null, this.missed_saved_searches)+'</span>')
                    .appendTo(this.accordeon);
            
        }else if(this.options.allowed_UGrpID.length>0){
            
            let empty_grp = window.hWin.HEURIST4.util.cloneJSON(this.options.allowed_UGrpID);
            
            $.each(this.loaded_saved_searches,function(i,svs){
                let k = window.hWin.HEURIST4.util.findArrayIndex(svs[Hul._GRPID], empty_grp);
                if(k>=0){
                    empty_grp.splice(k,1);
                    if(empty_grp.length==0) return false;
                }
            })
            if(empty_grp.length>0){
                $('<span style="padding:10px;" class="heurist-helper3">'
                        +this._getNotFoundMessage(empty_grp)+'</span>')
                        .appendTo(this.accordeon);
            }
            
        }


    },

    //
    //it adds context menu for evey group header 
    //
    _getAddContextMenu: function(groupID){
        
        let arr_menu = [];
        
        if(this.options.filter_by_type<2){
            arr_menu.push({title: "New", cmd: "addSearch", uiIcon: "ui-icon-plus" });
            arr_menu.push({title: "New faceted", cmd: "addSearch2", uiIcon: "ui-icon-box" });
        }            
        if(this.options.filter_by_type!=1){
            arr_menu.push({title: "New RuleSet", cmd: "addSearch3", uiIcon: "ui-icon-shuffle" });
        }
        arr_menu.push({title: "New folder", cmd: "addFolder", uiIcon: "ui-icon-folder-open" });

        let that = this;

        let context_opts = {
            delegate: ".hasmenu2",
            menu: arr_menu,
            open: function(){
                if(window.hWin.HEURIST4.util.isFunction(that.options.menu_locked)){
                    that.options.menu_locked.call( this, true );
                }
            },
            close: function(){
                if(window.hWin.HEURIST4.util.isFunction(that.options.menu_locked)){
                    that.options.menu_locked.call( this, false );
                }
            },
            select: function(event, ui) {

                that._avoidConflictForGroup(groupID, function(){

                    if(ui.cmd=="addFolder"){

                        setTimeout(function(){
                            that._addNewFolder(groupID);
                            }, 300);
                       
                    }else{
                        that.editSavedSearch((ui.cmd=="addSearch2")?'faceted':((ui.cmd=="addSearch3")?'rules':'saved'), groupID);
                    }
                });
            }
        };
        return context_opts;
    },

    //
    //
    //
    _addNewFolder: function(groupID){
        let tree = this.treeviews[groupID];
        let node = tree.rootNode;
        node.folder = true;
        
        let dt = {title:"", folder:true};
        if(this.options.filter_by_type==2) dt.data = {isrule:true};

        node.editCreateNode( "child", dt); //New folder
        this._saveTreeData( groupID );
        $("#addlink"+groupID).css('display', 'none');
    },
    
    //
    //
    //
    _defineHeader: function(name, domain){
        
        let sColor='', sIcon;

        if(domain=='all' || domain=='bookmark'){
            sIcon = 'user';
        }else if(domain=='dbs' || domain==1){
            sIcon = 'database';
        }else if(domain==5){
            sIcon = 'globe';
        }else {
            sIcon = 'group';
        }
        if(this.options.hide_header){ //IJ don't want inverted color anymore
           
        }

        let sWidth = '60%';
        let sInfo = '';
        if(this.options.container_width>199){
            sWidth = this.options.container_width - 100;
            sInfo = '<span style="font-size:0.8em;font-weight:normal;vertical-align:top;line-height: 1.8em;display:inline-block;"> ('
            + ((sIcon=='user')?'private':'workgroup')
            + ')</span>';
        }else if(this.options.container_width>0){
            sWidth = this.options.container_width - 55;
        }
        
        let $header = $('<h3 class="hasmenu2" grpid="'+domain
            +'" style="outline:none;margin:10px 0 0 0;background:none !important;'+sColor+'"><span class="ui-icon ui-icon-'+sIcon+'" '
            + 'style="display:inline-block;padding:0 4px"></span><span style="display:inline-block;vertical-align:top;width:'+sWidth+'px" class="truncate">'
            + name+'</span>'+sInfo+'</h3>').addClass('tree-accordeon-header svs-header');

        return $header
    },

    //
    // it invokes before each attempt of group tree modification (delete, drag, add, rename)
    // it verifies date of last modification on server and compare with date stored in treedata
    //
    _avoidConflictForGroup: function(groupID, continueFunc){


        if(isNaN(groupID)){
            continueFunc();
            return;
        }

        let that = this;

        window.hWin.HAPI4.SystemMgr.ssearch_gettree( {UGrpID:groupID}, function(response){

            if(response.status == window.hWin.ResponseStatus.OK){
                let newdata = {};
                try {
                    newdata = JSON.parse(response.data);
                }
                catch (err) {
                    window.hWin.HEURIST4.msg.showMsgErrJson(response.data);
                    return;
                }

                if( !newdata[groupID] ||
                    !window.hWin.HAPI4.currentUser.ugr_SvsTreeData[groupID] ||
                    (window.hWin.HAPI4.currentUser.ugr_SvsTreeData[groupID].modified &&
                        newdata[groupID].modified <= window.hWin.HAPI4.currentUser.ugr_SvsTreeData[groupID].modified))
                {

                    continueFunc();

                }else{
                    window.hWin.HAPI4.currentUser.ugr_SvsTreeData[groupID] = newdata[groupID];

                    that._redefineContent( groupID );

                    window.hWin.HEURIST4.msg.showMsgDlg('The tree structure for the "'+
                        window.hWin.HAPI4.sysinfo.db_usergroups[groupID]
                        +'" group has been modified by another user. The tree will be reloaded. '
                        +'Please repeat your operation with the new tree');
                }
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });

    },

    //
    //
    //
    _redefineContent: function(groupID){
        //find group div
        let grp_div = this.accordeon.find('.svs-acordeon[grpid="'+groupID+'"]');
        //define new
        this._defineContent(groupID, this.accordeon.width(), grp_div.find('.ui-accordion-content'));
    },


    //
    // main method to draw content for particular group
    // redraw treeview with the list of saved searches for given groupID and domain
    // add tree as a accordeon content
    //
    _defineContent: function(groupID, container_width, container){
        //
        let res;
        let that = this;
        let CLIPBOARD = null;

        let treeData = window.hWin.HAPI4.currentUser.ugr_SvsTreeData[groupID] 
                        && window.hWin.HAPI4.currentUser.ugr_SvsTreeData[groupID].children
        ?window.hWin.HAPI4.currentUser.ugr_SvsTreeData[groupID].children 
        :[];

        let tree = $("<div>").attr('tree-group', groupID).addClass('hasmenu').css('padding-bottom','0em');
        
        let fancytree_options =
        {
            groupID: groupID,    
            checkbox: false,
            //titlesTabbable: false,     // Add all node titles to TAB chain
            source: treeData,
            quicksearch: true,
            selectMode: 1, //1:single, 2:multi, 3:multi-hier (default: 2)

            //
            renderNode: function(event, data) {
                // Optionally tweak data.node.span
                    let node = data.node;

                    let $span = $(node.span);
                    let s = '', s1='';

                    if(node.folder){
                        //
                        if($span.find('span.ui-icon-folder-open').length==0){
                            s1 = '<span class="ui-icon-folder-open ui-icon" style="display:inline-block;color:orange;top: 3px;"></span>';
                            $(s1).insertBefore($span.find("> span.fancytree-title"));
                        }
                        //.html(s1 +
                       
                        
                        $span.attr('filter_type', node.data.isrule?2:0);

                    }else{

                        let s_hint = '';  //NOT USED this hint shows explanatory text about mode of search:faceted,with rules,rules only
                        let s_hint2 = ''; //this hint shows notes and RAW text of query,rules
                        let hasRules = false;

                        let svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[node.key];
                        
                        let squery = '';
                        if(node.data.url){
                            s_hint2 = node.title;
                            squery = node.data.url;
                        }else{
                            if(window.hWin.HAPI4.currentUser.usr_SavedSearch[node.key]){
                                squery = svs[Hul._QUERY];
                            }
                            s_hint2 = node.key+':'+node.title;
                        }

                        let prms = window.hWin.HEURIST4.query.parseHeuristQuery(squery);
                        
                        let s = window.hWin.HRJ('ui_name', prms, that.options.language);
                        
                        if(!window.hWin.HEURIST4.util.isempty(s)){
                            node.title = s;
                        }else if(svs && svs[Hul._NAME]){
                            node.title = svs[Hul._NAME];
                        } 
                        s_hint2 = node.key+':'+node.title
                        
                        s = window.hWin.HRJ('ui_notes', prms, that.options.language);
                        
                        if(!window.hWin.HEURIST4.util.isempty(s)){
                            s_hint2 = s_hint2 + '\nNotes: '+s;
                        }
                        if(!window.hWin.HEURIST4.util.isempty(prms.q)){
                            s_hint2 = s_hint2 + '\nFilter: '+prms.q;
                        }
                        if(!window.hWin.HEURIST4.util.isempty(prms.rules)){
                            s_hint2 = s_hint2 + '\nRules: '+prms.rules;
                            hasRules = true;
                        }
            
                        s = '';
                        if(prms.type==3){ //node.data.isfaceted
                            s = '<span class="ui-icon ui-icon-box svs-type-icon" title="faceted" ></span>';
                            if(hasRules){
                                s = s + '<span class="ui-icon ui-icon-shuffle svs-type-icon"></span>';
                            }
                            s_hint = this._HINT_FACETED;
                        }else if(prms.type==1){ //withrules
                                s = //'<span class="ui-icon ui-icon-plus svs-type-icon"></span>'
                                '<span class="ui-icon ui-icon-shuffle svs-type-icon"></span>';
                                s_hint = this._HINT_WITHRULES;
                        }else if(prms.type==2){ //rules only
                                s = '<span/>';
                                s_hint = this._HINT_RULESET;
                        }else if(prms.type<0){ //broken
                                s = '<span class="ui-icon ui-icon-alert svs-type-icon" title="broken"></span>';
                                s_hint = 'Broken filter. Remove and re-create it';
                        }

                        $span.find("> span.fancytree-title").html(node.title+' '+s);
                        $span.attr('title', s_hint2)
                        $span.attr('filter_type', prms.type);
                        
                    }

            },


            click: function(event, data) {
                if(!data.node.folder){
                    let qname, qsearch, svs_ID = 0;
                    if(data.node.data && data.node.data.url){
                        qsearch = data.node.data.url;
                        qname   = (data.node.key>0)?data.node.key:data.node.title; //qname replaced with svs_ID
                    }else{
                        if (data.node.key && 
                            window.hWin.HAPI4.currentUser.usr_SavedSearch && 
                            window.hWin.HAPI4.currentUser.usr_SavedSearch[data.node.key]){
                                
                            svs_ID = data.node.key; 
                            qsearch = window.hWin.HAPI4.currentUser.usr_SavedSearch[data.node.key][Hul._QUERY];
                            qname   = data.node.key; 
                        }
                    }

                   
                    //remove highlight from others
                    that.element.find('.ui-fancytree').find('li.ui-state-active').removeClass('ui-state-active');
                    that.element.find('.ui-fancytree').find('span.fancytree-active').removeClass('fancytree-active');
                    
                    that.doSearch( svs_ID, qname, qsearch, event.target );
                    setTimeout(function(){
                        that.search_tree.find('div.svs-contextmenu2').parent().addClass('leaves');
                        $(data.node.li).css('border','none').addClass('ui-state-active leaves');
                        },500);
                }

            }
        };

        if(window.hWin.HAPI4.has_access() && !this.isPublished){

            fancytree_options['extensions'] = ["edit", "dnd", "filter"];
            
            fancytree_options['dnd'] = {
                preventVoidMoves: true,
                preventRecursiveMoves: true,
                autoExpandMS: 400,
                dragStart: function(node, data) {
                    //return (node.key && node.key[0]=='_')?false:true; //disable folder dnd
                    return true;
                },
                dragEnter: function(node, data) {
                    //data.otherNode - dragging node
                    //node - target node
                    if(data.otherNode.folder && node.tree._id != data.otherNode.tree._id){
                        //do not allow drag folders between trees
                        return false;
                    }else{
                        return node.folder ?true :["before", "after"];
                    }

                },
                dragDrop: function(node, data) {
                    
                    //check that tree was not modified by other user
                    that._avoidConflictForGroup(groupID, function(){
                        //data.otherNode - dragging node
                        //node - target node
                        let newGroupID = node.tree.options.groupID;

                        if(node.tree._id != data.otherNode.tree._id){
                            //group is changed
                            let mod_node = data.otherNode;
                            that._moveSavedSearch(mod_node, newGroupID, node, data);
                            
                        }else{
                            //the same group
                            data.otherNode.moveTo(node, data.hitMode);
                            that._saveTreeData( newGroupID );
                        }
                    });
                }
            };
            fancytree_options['edit'] = {
                save:function(event, data){ //after edit end
                    if(''!=data.input.val()){
                        let new_name = data.input.val();
                        that._avoidConflictForGroup(groupID, function(){
                            data.node.setTitle( new_name );
                            that._saveTreeData( groupID );
                        });
                    }
                }
            };

            fancytree_options['filter'] = 
            {
                //autoApply: true,   // Re-apply last filter if lazy data is loaded
                autoExpand: false, // Expand all branches that contain matches while filtered
                counter: false,     // Show a badge with number of matching child nodes near parent icons
                fuzzy: false,      // Match single characters in order, e.g. 'fb' will match 'FooBar'
                hideExpandedCounter: true,  // Hide counter badge if parent is expanded
                hideExpanders: false,       // Hide expanders if all child nodes are hidden by filter
                //highlight: true,   // Highlight matches by wrapping inside <mark> tags
                leavesOnly: true, // Match end nodes only
                nodata: true,      // Display a 'no data' status node if result is empty
                mode: 'hide'       // dimm Grayout unmatched nodes (pass "hide" to remove unmatched node instead)
            };

            if(window.hWin.HEURIST4.util.isFunction(that.options.handle_favourites)){ // add extra dragging handle for saving filters as a favourite

                fancytree_options['dnd']['draggable'] = {
                    helper: function(event){

                        let sNode = $.ui.fancytree.getNode(event.target);

                        return $('<div class="fancytree-drag-helper"><span class="fancytree-drag-helper-img" ></span></div>')
                                    .append($(sNode.span).find('span.fancytree-title').clone())
                                    .data('ftSourceNode', sNode);
                    },
                    start: function(event, ui){
                        if(window.hWin.HEURIST4.util.isFunction(that.options.menu_locked)){
                            that.options.menu_locked.call( that, true );
                        }
                    },
                    stop: function(event, ui){

                        if(window.hWin.HEURIST4.util.isFunction(that.options.menu_locked)){ // wait 2.5 seconds to disable menu lock
                            setTimeout(function(){ that.options.menu_locked.call( that, false ); }, 2000);
                        }

                        let $ele = $(document.elementFromPoint(ui.position.left, ui.position.top));
                        let node = ui.helper.data('ftSourceNode');

                        let procFavourites = !node.folder && node.key && !node.data.url;

                        if(procFavourites && ($ele.is('ul.favourite-filters-container') || $ele.parents('ul.favourite-filters-container').length > 0)){

                            let name = window.hWin.HAPI4.currentUser.usr_SavedSearch[node.key][Hul._NAME];
                            if(window.hWin.HEURIST4.util.isempty(name)){
                                name = node.title;
                            }

                            that.options.handle_favourites.call(that, node.key, name, true);
                        }
                    },
                    appendTo: 'body',
                    containment: 'window',
                    revert: 'invalid',
                    cursorAt: {top: 0, left: 5},
                    zIndex: 2001
                };
            }

            tree.fancytree(fancytree_options)
            //.css({'height':'100%','width':'100%'})
            .on("nodeCommand", function (event, data){
                that._avoidConflictForGroup(groupID, function(){

                    // Custom event handler that is triggered by keydown-handler and
                    // context menu:
                    let refNode, moveMode;
                    let wtree = $.ui.fancytree.getTree(tree);
                    let node = wtree.getActiveNode();

                    switch( data.cmd ) {
                        case "moveUp":
                            node.moveTo(node.getPrevSibling(), "before");
                            node.setActive();
                            break;
                        case "moveDown":
                            node.moveTo(node.getNextSibling(), "after");
                            node.setActive();
                            break;
                        case "indent":
                            refNode = node.getPrevSibling();
                            node.moveTo(refNode, "child");
                            refNode.setExpanded();
                            node.setActive();
                            break;
                        case "outdent":
                            node.moveTo(node.getParent(), "after");
                            node.setActive();
                            break;
                        case "copycb":
                            break;    
                        case "query":
                            that._getFilterString(node.key, $(node.li));
                            break;    
                        case "embed":   //EMBED - IJ 2022-10-04 Block use here, Publish alternative are to be used instead
                            that._showEmbedDialog(node.key); // show message about alternatives
                            break;
                        case "copy":   //duplicate saved search
                        
                            if(!node.folder && node.key>0){
                                that.duplicateSavedSearch(groupID, node.key, node);
                            }
                            break;
                            
                        case "rename":   //EDIT

                            if(!node.folder && node.key>0){

                                that.editSavedSearch(null, groupID, node.key, null, node);
                            }else{
                                //rename folder
                                node.editStart();
                            }

                            break;
                        case "remove":
                            {
                                function __removeNode(){
                                    node.remove();
                                    that._saveTreeData( groupID );
                                    if(that.treeviews[groupID].count()<1){
                                        $("#addlink"+groupID).css('display', 'block');
                                    }
                                }

                                if(node.folder){
                                    if(node.countChildren()>0){
                                        window.hWin.HEURIST4.msg.showMsgDlg('Cannot delete non-empty folder. Please delete dependent entries first.',null,window.hWin.HR('Warning'));
                                    }else{
                                        __removeNode();
                                    }
                                }else{
                                    //saved search may have several entries - try to find

                                    //loop all nodes
                                    let cnt = 0;
                                    that.treeviews[groupID].visit(function(node2){
                                        if(node2.key==node.key){
                                            cnt++;
                                            if(cnt>1) return false;
                                        }
                                    });

                                    if(cnt==1){
                                        that._deleteSavedSearch(node, __removeNode);
                                    }else{
                                        __removeNode();
                                    }
                                }
                                break;
                            }
                        case "addFolder":  //always create sibling folder
                            {
                            /*if(!node.folder){
                            node = wtree.rootNode;
                            }*/
                           
                            let dt = {title:"", folder:true};
                            if(that.options.filter_by_type==2) dt.data = {isrule:true};
                            
                            node.editCreateNode( node.folder?"child":"after", dt); //New folder

                            break;
                            }
                        case "addSearch":  //add new saved search
                        case "addSearch2": //add new faceted search
                        case "addSearch3": //add new RuleSet

                            that.editSavedSearch( (data.cmd=="addSearch2")?'faceted':((data.cmd=="addSearch3")?'rules':'saved')
                                , groupID, null, null, node);

                            break;

                        case "addChild":
                            {
                           let dt = {title:"", folder:true};
                            if(that.options.filter_by_type==2) dt.data = {isrule:true};
                        
                            node.editCreateNode("child", dt); //New folder
                            break;
                            }
                        case "addSibling":
                            node.editCreateNode("after", "New node");
                            break;

                        case "favourite":
                            {
                            let name = window.hWin.HAPI4.currentUser.usr_SavedSearch[node.key][Hul._NAME];
                            if(window.hWin.HEURIST4.util.isempty(name)){
                                name = node.title;
                            }

                            that.options.handle_favourites.call(this, node.key, name);
                            break;
                            }
                        default:
                            alert("This command not handled: " + data.cmd);
                            return;
                    }

                });
                }
            )
            .on("keydown", function(e){
                let code = e.charCode || e.keyCode; //e.which
                let c = String.fromCharCode(code),
                cmd = null;

                if( c === "N" && e.ctrlKey && e.shiftKey) {     //add new folder
                    cmd = "addFolder";
                } else if( c === "C" && e.ctrlKey ) {
                    cmd = "copy";
                } else if( c === "V" && e.ctrlKey ) {
                    cmd = "paste";
                } else if( c === "X" && e.ctrlKey ) {
                    cmd = "cut";
                } else if( c === "N" && e.ctrlKey ) {
                    cmd = "addSearch";
                } else if( e.which === $.ui.keyCode.DELETE ) {
                    cmd = "remove";
                } else if( e.which === $.ui.keyCode.F2 ) {
                    cmd = "rename";
                } else if( e.which === $.ui.keyCode.UP && e.ctrlKey ) {
                    cmd = "moveUp";
                } else if( e.which === $.ui.keyCode.DOWN && e.ctrlKey ) {
                    cmd = "moveDown";
                } else if( e.which === $.ui.keyCode.RIGHT && e.ctrlKey ) {
                    cmd = "indent";
                } else if( e.which === $.ui.keyCode.LEFT && e.ctrlKey ) {
                    cmd = "outdent";
                }
                if( cmd ){
                    $(this).trigger("nodeCommand", {cmd: cmd});
                    return false;
                }
            });
            
            
            let arr_menu = []
            if(that.options.filter_by_type<2){
                    arr_menu.push({title: "New", cmd: "addSearch", uiIcon: "ui-icon-plus" });
                    arr_menu.push({title: "New Faceted", cmd: "addSearch2", uiIcon: "ui-icon-box" });
            }
            if(that.options.filter_by_type!=1){
                    arr_menu.push({title: "New RuleSet", cmd: "addSearch3", uiIcon: "ui-icon-shuffle" });
            }
            arr_menu.push({title: "Dupe", cmd: "copy", uiIcon: "ui-icon-copy" });
            arr_menu.push({title: "Edit", cmd: "rename", uiIcon: "ui-icon-pencil" });
            if(that.options.filter_by_type<2){
                arr_menu.push({title: "----"});
                arr_menu.push({title: "Get filter+rules", cmd: "query", uiIcon: "ui-icon-copy" });
                arr_menu.push({title: "Embed", cmd: "embed", uiIcon: "ui-icon-globe" }); // displays message about alternatives
            }
            arr_menu.push({title: "----"});
            arr_menu.push({title: "New folder", cmd: "addFolder", uiIcon: "ui-icon-folder-open" });
            arr_menu.push({title: "Delete", cmd: "remove", uiIcon: "ui-icon-trash" });
            arr_menu.push({title: "Favourite", cmd: "favourite", uiIcon: 'ui-icon-star-b'});

            /*
            * Context menu (https://github.com/mar10/jquery-ui-contextmenu)
            */
            tree.contextmenu({
                delegate: "li", //span.fancytree-node
                menu: arr_menu,

                open: function(){
                    //prevent collapse heurist main menu 
                    if(window.hWin.HEURIST4.util.isFunction(that.options.menu_locked)){
                        that.options.menu_locked.call( this, true );
                    }
                },
                close: function(){
                    if(window.hWin.HEURIST4.util.isFunction(that.options.menu_locked)){
                        that.options.menu_locked.call( this, false );
                    }
                },
                beforeOpen: function(event, ui) {

                    let node = $.ui.fancytree.getNode(ui.target);
                    tree.contextmenu("enableEntry", "paste", node.folder && !!CLIPBOARD);

                    let showFavourite = window.hWin.HEURIST4.util.isFunction(that.options.handle_favourites) && !node.folder && node.key && !node.data.url;
                    tree.contextmenu('enableEntry', 'favourite', showFavourite);
                    tree.contextmenu('showEntry', 'favourite', showFavourite);

                    if(showFavourite){ // check if filter is already favourited

                        let cur_fav = window.hWin.HAPI4.get_prefs_def('favourite_filters', ['']);

                        if(cur_fav[0] != '' && cur_fav.findIndex(filter => filter[0] == node.key) != -1){
                            tree.contextmenu('updateEntry', 'favourite', {title: 'Unfavourite', uiIcon: 'ui-icon-star'});
                        }else{
                            tree.contextmenu('updateEntry', 'favourite', {title: 'Faviourite', uiIcon: 'ui-icon-star-b'});
                        }
                    }

                    let is_filter_rules = !node.folder && node.key;
                    if(is_filter_rules){

                        let svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[node.key];
                        if(svs){
                            let qsearch = svs[Hul._QUERY];
                            let prms = window.hWin.HEURIST4.query.parseHeuristQuery(qsearch); //url to json

                            is_filter_rules = prms && prms.type != 3;
                        }
                    }
                    tree.contextmenu('enableEntry', 'query', is_filter_rules);
                    tree.contextmenu('showEntry', 'query', is_filter_rules);

                    node.setActive();
                },
                select: function(event, ui) {
                    
                    if(ui.cmd=='copycb'){
                            let wtree = $.ui.fancytree.getTree(tree);
                            let node = wtree.getActiveNode();
                            that._getFilterString(node.key);
                    }else{
                        let that2 = this;
                        // delay the event, so the menu can close and the click event does
                        // not interfere with the edit control
                        setTimeout(function(){
                            $(that2).trigger("nodeCommand", {cmd: ui.cmd});
                            }, 100);
                            
                    }
                }
            });

            let context_opts = this._getAddContextMenu(groupID);
            let tree_links;

            let append_link = $("<a>",{href:'#'})
                .html('<span class="ui-icon ui-icon-plus hasmenu2 droppable" '
                    +' style="display:inline-block; vertical-align: bottom"></span>'
                    +'<span class="hasmenu2 droppable">add</span>')
                .on('click', function(event){
                    append_link.contextmenu('open', append_link.find('span.ui-icon') );
                   
             });
             append_link.contextmenu(context_opts);


            //treedata is empty - add div - to show add links
            tree_links = $('<div>', {id:"addlink"+groupID, 'data-groupid':groupID})
            .css({'display': treeData && treeData.length>0?'none':'block', 'padding-left':'1em'} )
            .append( append_link );

            tree_links.droppable({
                classes: {
                    "ui-droppable-hover": "ui-state-active"
                }, 
                accept: function(){ return true },
                drop: function( event, ui ) {
                    
                        let mod_node = $(ui.helper).data("ftSourceNode");
                        let newGroupID = $(this).attr('data-groupid');
                        
                        that._moveSavedSearch(mod_node, newGroupID);
                    
            }});

            
            if(window.hWin.HEURIST4.util.isnull(container)){
                let sColor = '';
                if(this.options.hide_header){ //IJ don't want inverted color anymore
                    //sColor = ' style="color:white !important"';    
                }

                res = $('<div'+sColor+'>').append(tree).append(tree_links);
            }else{
                container.empty();
                container.append(tree).append(tree_links);
                res = container;
            }
            
            this._activateMenuAndTruncate(null, tree, container_width);

        }
        else{
            //not logged in
            tree.fancytree(fancytree_options);

            //treedata is empty - add div - to show empty message
            let tree_links = $('<div class="heurist-helper3">'+this._getNotFoundMessage(groupID)+'</div>')
            .css({'display': treeData && treeData.length>0?'none':'block', 'padding-left':'1em'} );
            
            if(window.hWin.HEURIST4.util.isnull(container)){
                res = $('<div>').append(tree_links).append(tree);
            }else{
                container.empty();
                container.append(tree_links).append(tree);
                res = container;
            }
            
            $.each( tree.find('span.fancytree-node'), function( idx, item ){

                let ele = $(item);
                ele.css({display: 'block', width:'99%'});         

                ele.find('.fancytree-title').css({display: 'inline-block', width:'90%'}).addClass('truncate');

                if($(item).find('span.fancytree-folder').length==0)
                {
                    $(item).addClass('leaves');
                }
            });            
            
            if(this.isPublished){
                res.css({background:'none', border:'none'});
            }
        }

        this.treeviews[groupID] = $.ui.fancytree.getTree( tree );

        if(this.options.is_h6style){
            this._applyTreeViewFilter(groupID);
            res.css({'background':'transparent','overflow':'hidden',border:'none'});
        }
        
        
        return res;

    },
    
            
    //
    //
    //
    _activateMenuAndTruncate: function(groupID, tree, container_width)
    {            
            if(!(container_width>0)) container_width = this.options.container_width;
            
            if(groupID!=null){
                tree = this.element.find('div[tree-group='+groupID+']');
            }
            if(!tree || tree.length==0) return;
        
            $.each( tree.find('span.fancytree-node'), function( idx, item ){

                let ele = $(item);
                ele.css({display: 'block', width:'99%'});         
                
                let is_folder = ele.hasClass('fancytree-folder');

                ele.find('.fancytree-title')
                    .css({display: 'inline-block', width:container_width>0?((container_width-(is_folder?52:30))+'px'):'80%'})
                    .addClass('truncate');
                //'80%'

                if(ele.find('.svs-contextmenu2').length==0){
                    $('<div class="svs-contextmenu2 ui-icon ui-icon-menu"></div>')
                    .on('click', function(event){ tree.contextmenu("open", $(event.target) ); window.hWin.HEURIST4.util.stopEvent(event); return false;})
                    .appendTo(ele);
                }

                if(is_folder){
                    
                    if(ele.find('span.ui-icon-folder-open').length==0){
                        let s1 = '<span class="ui-icon-folder-open ui-icon" style="display:inline-block;color:orange;top: 3px;"></span>';
                        $(s1).insertBefore(ele.find("> span.fancytree-title"));
                    }
                    
                }else
                {
                    $(item).addClass('leaves');
                }
                
                $(item).on('mouseenter',
                    function(event){
                        let ele = $(item).find('.svs-contextmenu2');
                        ele.css('display','inline-block');
                }).on('mouseleave',
                    function(event){
                        let ele = $(item).find('.svs-contextmenu2');
                        ele.hide();
                });
                
            });
    },
    
    //
    //
    //
    _applyTreeViewFilter: function(groupID, value){
        if(this.options.is_h6style){
            
            this.treeviews[groupID].clearFilter();
            if(!(value>=0)) value = this.options.filter_by_type;
    
            if(value!=0){
                    
                this.treeviews[groupID].filterNodes(function(node){
                    
                    let res = true;
                    if(value==2){ //rules only
                        res = ($(node.span).attr('filter_type')==2);    
                    }else{
                        res = ($(node.span).attr('filter_type')!=2);
                    }
                    if(!res){
                        $(node.span).parent('li').hide();
                    }
                    return res;
                }, {mode:'hide'});// //highlight:false,
                
            }

            let hasVisibleNode = false;
            this.treeviews[groupID].$container.find("li").each(function(idx, li_ele){
                
                if($(li_ele).css("display") != "none"){

                    hasVisibleNode = true;
                    return false;
                }
            });

            if(!hasVisibleNode){
                $(this.treeviews[groupID].$div[0].parentNode).find("#addlink"+groupID).show();
            }			
        }  
    },

    //
    // NOT USED
    //
    _saveSearch: function(request, node){

        let that = this;

        window.hWin.HAPI4.SystemMgr.ssearch_save(request,
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){

                    let svsID = response.data;

                    if(!window.hWin.HAPI4.currentUser.usr_SavedSearch){
                        window.hWin.HAPI4.currentUser.usr_SavedSearch = {};
                    }

                    window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID] = [request.svs_Name, request.svs_Query, request.svs_UGrpID];

                    window.hWin.HAPI4.save_pref('last_savedsearch_groupid', request.svs_UGrpID);

                    request.new_svs_ID = svsID;

                    let new_node = node.addNode( { title:request.svs_Name, key: request.new_svs_ID }
                        , node.folder?"child":"after" );

                    $(new_node.li).find('.fancytree-node').addClass('fancytree-match');
                        
                        that._saveTreeData( request.svs_UGrpID );
                    $("#addlink"+request.svs_UGrpID).css('display', 'none');

                   
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response, true);
                }
            }

        );


    },
    //
    //
    //
    doSearchByID: function(svs_ID, query_name){
    
        if(window.hWin.HAPI4.currentUser.usr_SavedSearch && 
            window.hWin.HAPI4.currentUser.usr_SavedSearch[svs_ID]){
                                
            let qsearch = window.hWin.HAPI4.currentUser.usr_SavedSearch[svs_ID][Hul._QUERY];
            let qname   = query_name || svs_ID; 
            
            this.doSearch( svs_ID, qname, qsearch, null );
        }else{
            //not found - try to find
            let that = this;
            window.hWin.HAPI4.SystemMgr.ssearch_get( { svsIDs:svs_ID },
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        if(response.data[svs_ID]){
                            let qsearch = response.data[svs_ID][Hul._QUERY];
                            that.doSearch( svs_ID, query_name || svs_ID, qsearch, null );
                        }else{
                            window.hWin.HEURIST4.msg.showMsgFlash('Saved filter not found ( ID: '+svs_ID+' )');    
                        }
                    }
            });
            
        }
        
    },
    
    //
    //
    //
    doSearch: function(svs_ID, qname, qsearch, ele){

        if ( qsearch ) {

            let params = window.hWin.HEURIST4.query.parseHeuristQuery( qsearch );
            
            let context_on_exit = null;
            
            let s = window.hWin.HRJ('ui_name', params, this.options.language);
            if(!window.hWin.HEURIST4.util.isempty(s)){
                 qname = s;
            } 
            
            if(params.type==3){ //isfaceted

                /*if(facet_params==null){
                    // Do something about the exception here
                    window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('Cannot initialise this faceted search due to corrupted parameters. Please remove and re-create this search.'), null, window.hWin.HR('Warning'));
                    return;
                }*/

                let that = this;


                if(params['version']==2){

                    //suplementary filter for faceted search
                    if(that.options.sup_filter){
                        params.sup_filter = that.options.sup_filter;
                    }
                    
                    //options for faceted search
                    let noptions = { 
                        svs_ID: svs_ID,
                        query_name:qname, 
                        params:params, 
                        showclosebutton: this.showclosebutton,
                        showresetbutton: (this.options.showresetbutton!==false),
                        search_realm: this.options.search_realm,
                        search_page: this.options.search_page,
                        language: this.options.language,
                        hide_no_value_facets: this.options.hide_no_value_facets
                    };
                    
                    if(that.options.is_h6style){
                        context_on_exit = noptions;
                    }else {
                        
                        if(this.isPublished){
                            this.element.css('overflow-y','hidden');
                        }
                        
                        this.search_faceted.show();
                        if(this.search_faceted.height() == 0 && this.search_tree.height() != 0){ // set facet container height to avoid invisible search
                            let min_height = this.search_tree.height();
                            this.search_faceted.css('min-height', min_height);
                        }

                        this.search_tree.hide();
                    
                        //function to be called on close faceted search
                        if(window.hWin.HEURIST4.util.isFunction(that.options.onclose_search)){
                            noptions.onclose = that.options.onclose_search;
                        }else{
                            noptions.onclose = function(event){

                                if(that.search_faceted.is(':visible')){

                                    $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, [ 
                                        {reset:true, search_realm:that.options.search_realm} ]);  //global app event to clear views

                                    that.search_faceted.hide();
                                    that.search_tree.show();
                                    that._adjustAccordionTop();
                                    if(that.isPublished){
                                        that.element.css('overflow-y','auto');
                                    }
                                }
                            };
                        }

                        if(!window.hWin.HEURIST4.util.isFunction($('body')['search_faceted'])){
                            $.getScript( window.hWin.HAPI4.baseURL + 'hclient/widgets/search/search_faceted.js', function() {
                                that.doSearch( 0, qname, qsearch, ele );
                            });
                            return;
                        }else
                            if(this.search_faceted.html()==''){ //not created yet
                                this.search_faceted.search_faceted( noptions );
                            }else{
                                this.search_faceted.search_faceted('option', noptions ); //assign new parameters
                            }

                        window.hWin.HAPI4.SystemMgr.user_log('search_Record_faceted');
                    }

                }else{

                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: "This faceted search is in an old format. "
                                + "Please delete it and add a new one (right click in the saved search list). "
                                + "We apologise for this inconvenience, but we have added many new features to the facet search "
                                + "function and it was not cost-effective to provide backward compatibility (given the relative "
                                + "ease of rebuilding searches and the new features now available).",
                        error_title: 'Out dated facet formatting'
                    });
                    return;
                }
                
            }else if(params.type<0){

                window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('Cannot initialise search due to corrupted parameters. '
                +'Please remove and re-create this search.'), null, window.hWin.HR('Warning'));
                return;
            }else{

                let request = params;

                request.rules = window.hWin.HEURIST4.query.cleanRules(request.rules);
                
                //query is not defenied, but rules are - this is pure RuleSet - apply it to current result set
                if(window.hWin.HEURIST4.util.isempty(request.q)&&!window.hWin.HEURIST4.util.isempty(request.rules)){

                    if(this.currentSearch){
                        this.currentSearch.rules = window.hWin.HEURIST4.util.cloneJSON(request.rules);
                    }
                    
                    if(request.rulesonly===true) request.rulesonly = 1;
                    
                    //target is required
                    if(! window.hWin.HAPI4.RecordSearch.doApplyRules( this, request.rules, 
                                        (request.rulesonly>0)?request.rulesonly:0, this.options.search_realm ) ){
                        window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('RuleSets require an initial search result as a starting point.'),
                            3000, window.hWin.HR('Warning'), ele);
                    }else{
                        window.hWin.HAPI4.SystemMgr.user_log('search_Record_applyrules');
                    }
                    
                }else if(window.hWin.HEURIST4.util.isempty(request.q)){

                    window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('Cannot initialise this search due to corrupted parameters. '
                        +'Please redefine filter parameters.'), null, window.hWin.HR('Warning'));                    
                
                    return;    
                }else{
                    //additional params
                    request.detail = 'detail';
                    request.source = this.element.attr('id');
                    request.qname = qname;
                    request.search_realm = this.options.search_realm;
                    request.search_page = this.options.search_page;
                    
                    window.hWin.HAPI4.SystemMgr.user_log('search_Record_savedfilter');
                    
                    //get hapi and perform search
                    window.hWin.HAPI4.RecordSearch.doSearch( this, request );
                    
                }
                
            }
            if(window.hWin.HEURIST4.util.isFunction(this.options.onClose)){
                this.options.onClose( context_on_exit );
            }
        }

    },
    
    //
    //
    //
    _deleteSavedSearch: function(node, callback){

        let svsID = node.key;
        let svsTitle = node.title;

        let svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID];
        if(!svs) return;

        window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR("Delete '"+ svsTitle  +"'? Please confirm"),  function(){

            window.hWin.HAPI4.SystemMgr.ssearch_delete({ids:svsID, UGrpID: svs[2]},
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){

                        //remove from UI
                        callback.apply(this);
                        //remove from
                        delete window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID];

                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response, true);
                    }
                }

            );
            }, "Confirmation",
            {
                default_palette_class: 'ui-heurist-explore',
                position: { my: "left top", at: "left bottom", of: $(node.li) }
            });

    }

    //
    //
    //
    , duplicateSavedSearch: function(groupID, svsID, node){
        
        let svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID];
        if(!svs) return;
        let that = this;

        window.hWin.HAPI4.SystemMgr.ssearch_copy({svs_ID:svsID},
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    response = response.data;

                    window.hWin.HAPI4.currentUser.usr_SavedSearch[response.svs_ID] = 
                        [response.svs_Name, response.svs_Query, response.svs_UGrpID];

                    let new_node = node.addNode( { title:response.svs_Name, key: response.svs_ID}
                        , 'after' );
                        
                    $(new_node.li).find('.fancytree-node').addClass('fancytree-match');
                        
                    that._saveTreeData( groupID );

                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response, true);
                }
            }

        );
        
    }
    
    // open edit dialog
    // mode: saved, rules, faceted
    // groupID - current user or workgroup
    // svsID - saved search id
    // squery
    , editSavedSearch: function(mode, groupID, svsID, squery, node, is_short, after_save_callback){

        let that = this;
        let currGroupId = 0;
        let isPrivate = false;
        if(window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID]){
            currGroupId = window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID][Hul._GRPID];
            if(currGroupId == window.hWin.HAPI4.currentUser.ugr_ID){
                 currGroupId = (node==null || that.treeviews['all']._id == node.tree._id)?'all':'bookmark';
                 isPrivate = true;
            }
            if(node==null){
                let tree = that.treeviews[currGroupId];
                node = tree.getNodeByKey(svsID);
            }
        }

        let callback = function(event, response) {
            
            let svs_ID = 0;
            
            if(response.isNewSavedFilter){     //new saved search

                //update tree after addition - add new search to root
                if(window.hWin.HEURIST4.util.isnull(node)){
                    groupID = response.svs_UGrpID
                    if(groupID == window.hWin.HAPI4.currentUser.ugr_ID){
                        groupID = response.domain?response.domain:'all'; //all or bookmarks
                    }
                    let tree = that.treeviews[groupID];
                    node = tree.rootNode;
                    node.folder = true;
                }

                let new_node = node.addNode( { title:response.svs_Name, key: response.new_svs_ID}
                    , node.folder?"child":"after" );

                $(new_node.li).find('.fancytree-node').addClass('fancytree-match');
                    
                that._saveTreeData( groupID );
                $("#addlink"+groupID).css('display', 'none');
                
                svs_ID = response.new_svs_ID;
                
                that._applyTreeViewFilter( groupID );
                
            }else 
            {
            
                if(node){ //edit is called from this widget only - otherwise we have to implement search node by svsID
                    //if group is changed move node to another tree
                    groupID = response.svs_UGrpID
                    if(groupID == window.hWin.HAPI4.currentUser.ugr_ID){
                        groupID = response.domain?response.domain:'all'; //all or bookmarks
                    }else{
                        isPrivate = false;
                    }

                    if( currGroupId != groupID){
                        //remove from old group
                        node.remove();
                        if(!isPrivate){
                            that._saveTreeData( currGroupId );   
                        }
                        if(that.treeviews[currGroupId].count()<1){
                            $("#addlink"+currGroupId).css('display', 'block');
                        }
                        
                        //add to to new tree
                        let tree = that.treeviews[groupID];
                        node = tree.rootNode;
                        let new_node = node.addNode( { title:response.svs_Name, key: response.svs_ID}, 'child' );
                        $(new_node.li).find('.fancytree-node').addClass('fancytree-match');
                        
                       
                        $("#addlink"+groupID).hide();
                        
                    }else{
                        node.setTitle(response.svs_Name);
                        node.render(true);
                        //edit - changed only title in treeview
                       
                    }

                    that._saveTreeData( groupID );
                    
                    svs_ID = response.svs_ID;
                    
                }
            }
            
            if(groupID && svs_ID>0){
                that._activateMenuAndTruncate(groupID);
                
                if(window.hWin.HEURIST4.util.isFunction(after_save_callback)){
                    after_save_callback.call( this, svs_ID );
                }
            }

            let favourite_filters = window.hWin.HAPI4.get_prefs_def('favourite_filters', false);
            if(!window.hWin.HEURIST4.util.isArrayNotEmpty(favourite_filters) || window.hWin.HEURIST4.util.isempty(favourite_filters[0])){
                return;
            }

            // Update label
            const idx = favourite_filters.findIndex((filter) => filter[0] == svs_ID);
            if(idx >= 0){
                favourite_filters[idx][1] = response.svs_Name;
                window.hWin.HAPI4.save_pref('favourite_filters', favourite_filters);
                window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE, {refresh_favourites: true});
            }

        };

        const svs_edit_js_loaded = true;
        if( svs_edit_js_loaded ) { //}!window.hWin.HEURIST4.util.isnull(this.hSvsEdit) && window.hWin.HEURIST4.util.isFunction(this.hSvsEdit)){ //already loaded     @todo - load dynamically

            if(window.hWin.HEURIST4.util.isnull(svsID) && window.hWin.HEURIST4.util.isempty(squery)){
                squery = window.hWin.HEURIST4.util.cloneJSON(this.currentSearch);
            }

            if(null == this.edit_dialog){
                this.edit_dialog = new HSvsEdit();
            }
            let is_lock = window.hWin.HEURIST4.util.isFunction(this.options.menu_locked);
            if(is_lock) {
                this.options.menu_locked.call( this, true );
                setTimeout(function(){that.options.menu_locked.call( that, false );}, 300);
            }
            
            let pos = null;
            if(this.options.is_h6style && this.element.is(':visible')){
                pos = { my: "left top", at: "left top", of: this.element, collision:'none'};
            }else{
                pos = { my: "center", at: "center", of: window, collision:'none'};
            }
            
           
            this.edit_dialog.showSavedFilterEditDialog( mode, groupID, svsID, squery, is_short, 
                pos, 
                callback, 
                true,  //modal
                this.options.is_h6style );

        }else{
            $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/search/svs_edit.js',
                function(){ that.hSvsEdit = HSvsEdit; that.editSavedSearch(mode, groupID, svsID, squery); } );
        }

    },


    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        // remove generated elements
        if(this.edit_dialog) {
            this.edit_dialog.remove();
        }

        if(this.filter_div){
            this.btn_save.remove();
            this.btn_reset.remove();
            this.filter_div.remove();
        }
        if(this.btn_search_save) this.btn_search_save.remove();
        this.accordeon.remove();
       
        if(this.direct_search_div) this.direct_search_div.remove();

        this.search_tree.remove();
        this.search_faceted.remove();
    }

    //
    //
    //
    , _getFilterStrin_OLD: function( svs_ID ){
        
        let svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[svs_ID];
        if(svs ){
            let qsearch = svs[Hul._QUERY];
            let prms = window.hWin.HEURIST4.query.parseHeuristQuery(qsearch); //url to json
            if(prms.type!=3){
                let res = window.hWin.HEURIST4.query.hQueryStringify(prms); //json to string

                if(!window.hWin.HEURIST4.util.isempty(res)){
                            let dummy = document.createElement("input");
                            dummy.value = res;
                            document.body.appendChild(dummy);
                            dummy.select();
                            try {

                                if(document.execCommand("copy"));  // Security exception may be thrown by some browsers.
                                {
                                    window.hWin.HEURIST4.msg.showMsgFlash('Query is in clipboard');
                                }
                                
                            } catch (ex) {
                                console.warn("Copy to clipboard failed.", ex);
                            } finally {
                                document.body.removeChild(dummy);
                            }        
                }    
            }
        }
        
    },
    
    //
    //  open dialog to copy filter+rules as json query
    //
    _getFilterString: function(svs_ID, pos_element){

        let svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[svs_ID];
        if(!svs) return;
        
        let qsearch = svs[Hul._QUERY];
        let prms = window.hWin.HEURIST4.query.parseHeuristQuery(qsearch); //url to json
        if(prms.type!=3){
            
            
            let crules = window.hWin.HEURIST4.query.cleanRules( prms.rules );                                                      
            prms.rules = crules==null?'':crules;
            
            prms.db = window.hWin.HAPI4.database;
            
            window.hWin.HEURIST4.query.hQueryCopyPopup(prms, pos_element);
        }
    },

    
    //
    // IJ 2022-10-04 Block use here, Publish alternative are to be used instead
    //
    _showEmbedDialog: function(svs_ID){
        window.hWin.HEURIST4.msg.showMsgErr({
            message: 'Embed is no longer supported. Please use the much more flexible standalone web page builder or the website builder, which are located under the Publish menu.',
            error_title: 'Function no longer supported',
            status: window.hWin.ResponseStatus.ACTION_BLOCKED
        });
    },

    //--------------------------------------------------------------------------------------
    
    //
    //
    //    
    getFiltersTreeData: function( allowed_groups, callback ){
        
            let that = this;
            
            let request = {};
            
            if(allowed_groups && allowed_groups.length>0){
                request = {UGrpID:allowed_groups};
            }
        
            window.hWin.HAPI4.SystemMgr.ssearch_gettree( request, function(response){
                
                let resTreeData = null

                if(response.status == window.hWin.ResponseStatus.OK){
                    try {
                        resTreeData = window.hWin.HEURIST4.util.isJSON(response.data);
                        if(resTreeData){
                            resTreeData = that.__clean_TreeData(resTreeData, 0, 0);    
                        }else{
                            resTreeData = null;
                            window.hWin.HEURIST4.msg.showMsgErr({
                                message: 'Server returns saved filters tree data in wrong format.'
                                        +'<br>New default treeview will be created',
                                error_title: 'Invalid formatting',
                                status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                            });
                        }
                    }
                    catch (err) {
                        console.error(err);
                        
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }

                if(resTreeData==null) //treeview was not saved - define tree data by default
                    resTreeData = that.__default_TreeData();
                else{
                    resTreeData = that.__validate_TreeData(resTreeData);  //add missed filters to the end of treedata
                }
                
                callback(resTreeData)
            } );  //ssearch_gettree
    },

    //
    //
    //
    __default_TreeData: function(){
        
        let treeData;
        
        if(window.hWin.HAPI4.has_access()){
            treeData = {
                all: { title: window.hWin.HR('My Searches'), folder: true, expanded: true, 
                        children: this.__define_SVSlist(window.hWin.HAPI4.currentUser.ugr_ID, 'all') },
                bookmark:{ title: window.hWin.HR('My Bookmarks'), folder: true, expanded: true, 
                        children: this.__define_SVSlist(window.hWin.HAPI4.currentUser.ugr_ID, 'bookmark') }
            };
            /*
            if(window.hWin.HAPI4.is_admin()){
                treeData['guests'] = { title: window.hWin.HR('Searches for guests'), 
                    folder: true, expanded: false, children: this.__define_SVSlist(0) };
            }
            */
            
        }else{
            treeData = {
                all: { title: window.hWin.HR('Searches'), folder: true, expanded: true, 
                    children: this.__define_SVSlist(0, 'all') }
            };
        }

        let groups = window.hWin.HAPI4.currentUser.ugr_Groups;
        for (let groupID in groups)
        {
            if(groupID>0){
                let name = window.hWin.HAPI4.sysinfo.db_usergroups[groupID];
                treeData[groupID] = {title: name, folder: true, expanded: false, 
                    children: this.__define_SVSlist(groupID) };
            }
        }
            

        return treeData;
       
    },
    
        
    //
    // remove nodes that refers to missed search
    //it returns null if leaf is not found
    //
    __clean_TreeData: function (data, level, groupID){

            if(level==0){ //this is top level  data['all'] && data['bookmark'] && data['entity']
                for(groupID in data){
                    data[groupID] = this.__clean_TreeData(data[groupID], level+1, groupID);
                    if(data[groupID].was_cleaned==true){
                        data[groupID].was_cleaned = null;
                        let treeData = {};
                        treeData[groupID] = data[groupID];
                        this._saveTreeData( groupID, treeData );
                    }
                }
                return data;
            }

            if(data.children){
                let newchildren = [];
                for (let idx in data.children){
                    if(idx>=0){
                        let node = this.__clean_TreeData(data.children[idx], level+1, groupID);
                        if(node!=null){
                            newchildren.push(node);
                            data.was_cleaned = data.was_cleaned || node.was_cleaned; 
                            node.was_cleaned = null;
                        }else{
                            data.was_cleaned = true;
                        }
                    }
                }
                data.children = newchildren;
                return data;
                
            }else if(data.key>0){
                if(window.hWin.HAPI4.currentUser.usr_SavedSearch[data.key]){
                    //search exists, check that it belong to proper group
                    return (window.hWin.HAPI4.currentUser.usr_SavedSearch[data.key][Hul._GRPID] == groupID)?data:null;
                }else{
                    return null;
                }
            }else{
                return data;
            }
    },//end __clean_TreeData
    
    //
    // add missed groups and saved searches to treeview (to the end of tree)
    //
    __validate_TreeData: function( treeDataF ){

        let treeData = this.__default_TreeData();

        if(!treeDataF) treeDataF = {};


        for (let groupID in treeData){
            if(!treeDataF[groupID]){ //group bot found
                //direct copy entire group
                treeDataF[groupID] = treeData[groupID];
            }else{
                if(!treeDataF[groupID]['children']){
                    treeDataF[groupID]['children'] = [];
                }


                function __findInTreeDataF(nodes, key){

                    let res = false;

                    for (let idx in nodes){
                        if(idx>=0){
                            let node = nodes[idx];
                            if(node['key'] == key){
                                return true;
                            }else if(node['children']){
                                res = __findInTreeDataF( node.children, key );
                                if(res) return res;
                            }
                        }
                    }

                    return res;

                }

                //add missed saved searches
                let svs = treeData[groupID].children;
                for(let i=0; i<svs.length; i++){
                    //find in treeview from file
                    if(svs[i]['key']){
                        if(!__findInTreeDataF( treeDataF[groupID]['children'], svs[i]['key'])){ //if not found - add
                            treeDataF[groupID]['children'].push(svs[i]);
                        }
                    }
                }
            }
        }

        return treeDataF;
    },

    //
    // for default tree data
    // create list of saved searches for given user/group
    // domain - all or bookmark
    //
    __define_SVSlist: function(ugr_ID, domain){

        let ssearches = window.hWin.HAPI4.currentUser.usr_SavedSearch;

        let res = [];

        domain = (domain && (domain=='b' || domain=='bookmark'))?'bookmark':'all';
        
        //add predefined searches
        if(ugr_ID == window.hWin.HAPI4.currentUser.ugr_ID){  //if current user - it adds 2 special searches: all or bookmark

            let s_recent = "?w="+domain+"&q=sortby:-m after:\"1 week ago\"&label=Recent changes";
            let s_all = "?w="+domain+"&q=sortby:-m&label=All records";

            res.push( { title: window.hWin.HR('Recent changes'), folder:false, url: s_recent}  );
            res.push( { title: window.hWin.HR('All (date order)'), folder:false, url: s_all}  );
        }

        for (let svsID in ssearches)
        {
            if(svsID && ssearches[svsID][Hul._GRPID]==ugr_ID){

                let prms = window.hWin.HEURIST4.query.parseHeuristQuery(ssearches[svsID][Hul._QUERY]);

                if(!domain || domain==prms.w){
                    let sname = ssearches[svsID][Hul._NAME];
                    res.push( { title:sname, folder:false, key:svsID } );
                }
            }
        }

        return res;

    },

    //
    // on drag drop listener - move folder or filter to another workgroup
    //
    _moveSavedSearch: function(mod_node, newGroupID, node, data)                                                             
    {
        let oldGroupID = mod_node.tree.options.groupID;
        let newGroupID_for_db = (newGroupID=='all' || newGroupID=='bookmark')
        ? window.hWin.HAPI4.currentUser.ugr_ID :newGroupID; 

        let affected = [];
        if(mod_node.folder){
            mod_node.visit( function(node){
                if(!node.folder) affected.push(node.key);    
            });
        }else{
            affected = [mod_node.key];
        }

        let that = this;

        let request = { svs_ID: affected, 
            svs_UGrpID: newGroupID_for_db };

        window.hWin.HAPI4.SystemMgr.ssearch_save(request,
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){

                    for(let i=0; i<affected.length; i++){
                        window.hWin.HAPI4.currentUser.usr_SavedSearch[affected[i]][Hul._GRPID] = newGroupID;    
                    }


                    if(data){
                        mod_node.moveTo(node, data.hitMode);    
                    }else{
                        $("#addlink"+newGroupID).hide();    
                        //target tree                    
                        let tree = that.treeviews[newGroupID];
                        node = tree.rootNode;
                        node.folder = true;
                        mod_node.moveTo(node);
                    }
                    
                    if(that.treeviews[oldGroupID].count()<1){
                        $("#addlink"+oldGroupID).css('display', 'block');
                    }

                    that._saveTreeData( oldGroupID, null, function(){
                        that._saveTreeData( newGroupID, null, function(){
                        } );
                    } );

                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response, true);
                }
        });
    },
    
    //
    // no filters defined message (for publish node)
    //
    _getNotFoundMessage: function(groupIDs, svsIDs){
        let is_logged = window.hWin.HAPI4.has_access();
        
        let sMsg = 'no filters defined';
        
        if(!svsIDs){
            
            if(!Array.isArray(groupIDs)){
                groupIDs = [groupIDs];
            }
            
            let missed =  [];
            let empty = [];
            for (let i=0; i<groupIDs.length; i++){
                let grp_name = window.hWin.HAPI4.sysinfo.db_usergroups[groupIDs[i]];
                if(window.hWin.HEURIST4.util.isnull(grp_name)){
                    missed.push(groupIDs[i]);
                }else{
                    empty.push(grp_name);
                }
            }
            
            sMsg = '';
            
            if(missed.length>0){
                sMsg += ('<br>&nbsp;&nbsp;Unable to load workgroup'+(missed.length>1?'s':'')
                            +' #' + missed.join(', '));
                if(is_logged){
                    sMsg += '. Please edit the web page, click edit on the Saved searches widget, and modify the parameters.';
                }else{
                    sMsg += '. Please advise website owner.';
                }
            }
            if(empty.length>0){
                if(sMsg!='') sMsg = sMsg + '<br><br>';
                sMsg += ('&nbsp;There are no saved filters defined for the workgroup '
                         +(empty.length>1?'s':'')
                         + empty.join(', '));
                
                if(is_logged){
                    sMsg += '. Please create some saved filters there or '
                    + 'edit this widget to indicate another workgroup or a specific search).';
                }else{
                    sMsg += '. Please advise website owner.';
                }
            }
            
        }else{
           
            sMsg = ('<br>&nbsp;&nbsp;Unable to load saved filter'+(svsIDs.length>1?'s':'')
                        +' #' + svsIDs.join(', '));
            if(is_logged){
                sMsg += '. Please edit the web page, click edit on the Saved searches widget, and modify the parameters.';
            }else{
                sMsg += '. Please advise website owner.';
            }
            
        }
        
        return sMsg;
    },

    //
    // Add 'search everything' container
    //
    addSearchEverything: function(is_buttons=false){

        let that = this;

        if(this.direct_search_div){
            this.direct_search_div.remove(); 
            this.direct_search_div = null;
        }

        let header_label = !window.hWin.HEURIST4.util.isempty(this.options.simple_search_header) ? this.options.simple_search_header : 'Simple search';
        let field_text = !window.hWin.HEURIST4.util.isempty(this.options.simple_search_text) ? this.options.simple_search_text : 'Search everything:';

        this.direct_search_div = $('<div style="height:6em;padding:4px 4px 4px 15px;width:100%">'
            +'<h4 style="padding:10px 0px;margin:0">'+ header_label +'</h4><label>'+ field_text +'</label>'
            +'&nbsp;<input id="search_query" style="display:inline-block;width:40%" type="search" value="">'
            +'&nbsp;<button id="search_button"/></div>');

        if(is_buttons){
            this.direct_search_div.insertAfter(this.accordeon);
        }else{
            this.direct_search_div.insertBefore(this.accordeon);
        }

        let ele_search = this.direct_search_div.find('#search_query');
        if(ele_search.length>0){

            this._on( ele_search, {
                keypress: function(e){
                    let code = (e.keyCode ? e.keyCode : e.which);
                    if (code == 13) {
                        window.hWin.HEURIST4.util.stopEvent(e);
                        e.preventDefault();
                        that.doSearch(0, '', ele_search.val(), ele_search);
                    }
                }
            });

            let btn_search = this.direct_search_div.find('#search_button')
            .button({icon:'ui-icon-search',showLabel:false})
            .css({width:'18px', height:'18px', 'margin-bottom': '5px'});
            this._on( btn_search, {
                click:  function(){
                    that.doSearch(0, '', ele_search.val(), ele_search);
                }
            });
        }
        
        return this.direct_search_div;
    }
});

/*

jQuery(document).ready(function(){
$('.accordion .head').on('click', function() {
$(this).next().toggle();
return false;
}).next().hide();
});

*/
