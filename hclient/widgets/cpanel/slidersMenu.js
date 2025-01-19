/**
* slidersMenu.js : side menu with sections as popup sliders
* 
* It loads slidersMenuXxx.html for every section
* They took icons, titles and rollovers in core/actions.json via window.hWin.HAPI4.actionHandler
* This object handles all actions via executeActionById method
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

$.widget( "heurist.slidersMenu", {

    // default options
    options: {
    },
    
    sections: ['design','populate','explore','publish','admin'],
    
    menues:{}, //section menu - div with menu actions
    containers:{}, //operation containers (next to section menu)
    introductions:{}, //context help containers
    
    _myTimeoutId: 0,  //delay on collapse main menu (_expandMainMenuPanel/_collapseMainMenuPanel)

    _myTimeoutId2: 0, //delay on close explore popup menu
    _myTimeoutId3: 0, //delay on show explore popup menu
    
    _myTimeoutId5: 0, //delay on prevent expand main menu (after search)
    
    _delayOnCollapseMainMenu: 800,
    _delayOnCollapse_ExploreMenu: 600,
    
    _delayOnShow_ExploreMenu: 500, //5 500,
    _delayOnShow_AddRecordMenu: 500, //10 1000,
    
    _widthMenu: 170, //left menu
    
    _is_prevent_expand_mainmenu: false,
    _explorer_menu_locked: false,
    _active_section: null,
    _current_explore_action: null,
    
    divMainMenu: null,  //main div
    
    currentSearch: null,
    reset_svs_edit: true,
    
    svs_list: null,
    
    coverAll: null,
    
    menues_explore_popup: null,  //popup next to explore menu (filters)
    menues_explore_gap: null,
    search_faceted: null,
    edit_svs_dialog: null,

    _left_position: 91, //normal width for most languages, for German it is 115

    _show_quick_tips: false, // popup quick tips on next moving to the explore menu

    _menu_colours: {
        admin: '#676E80',
        design: '#523365',
        populate: '#307D96',
        explore: '#4477B9',
        publish: '#627E5D'
    },

    // the widget's constructor
    _create: function() {

        let that = this;
        
        //make it wider for Deutsch
        this._left_position = ('de'== window.hWin.HAPI4.getLocale())?115:91;

        
        this.element.addClass('ui-menu6')
        .addClass('selectmenu-parent')
        .disableSelection();// prevent double click to select text

        this.coverAll = $('<div>').addClass('coverall-div-bare')
            .css({'background-color': '#000', opacity: '0.6',zIndex:102,  
            filter: 'progid:DXImageTransform.Microsoft.Alpha(opacity=60)'})
            .hide()
            .appendTo( this.element );
        //91 200    
        this.divMainMenu = $('<div>')
        .addClass('slidersMenu')
        .css({position:'absolute',width: (this._left_position+'px'),
                top:'2px',left:'0px',bottom:'4px', 
                cursor:'pointer','z-index':104})
        .appendTo( this.element );
        
        this.divMainMenu.load(
            window.hWin.HAPI4.baseURL+'hclient/widgets/cpanel/slidersMenu.html',
            function(){ 
                
                window.hWin.HAPI4.HRA(that.divMainMenu);

                //init all menues
                $.each(that.sections, function(i, section){
                    that._loadSectionMenu(section);
                    that._initIntroductory(section);
                });
                
                //explore menu in main(left) menu  -  quicklinks
                that._on(that.divMainMenu.children('.ui-heurist-quicklinks').find('li.menu-explore, div.menu-explore'),
                {
                    mouseenter: that._expandMainMenuPanel,
                    mouseleave: that._collapseMainMenuPanel,
                });
                that._on(that.divMainMenu.children('.ui-heurist-quicklinks'),{
                    mouseleave: that._collapseMainMenuPanel,
                });
                
                that._on(that.divMainMenu.find('.ui-heurist-header'),{
                    click: that._openSectionMenu
                });
                
                const urlParams = new URLSearchParams(window.hWin.location.search);
                
                if(urlParams.has('welcome')){ //window.hWin.HEURIST4.util.getUrlParameter('welcome')
                    //open explore by default, or "design" if db is empty
                    that._active_section = 'explore';
                    that.switchContainer( 'design' );
                    that._loadStartHints();
                    that._show_quick_tips = true;
                }else{
                    that.switchContainer( 'explore' );
                }

                //init menu items in ui-heurist-quicklinks
                //init ui-heurist-quicklinks menu items
                that._on(that.divMainMenu.find('.menu-explore'),{
                    mouseenter: that._mousein_ExploreMenu,
                    mouseleave: function(e){
                        if($(e.target).parent('#filter_by_groups').length==0){
                            clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0; //clear timeout on show section menu
                            
                            if (!this._isCurrentActionFilter()) { //close on mouse exit
                            
                                //this._resetCloseTimers();//reset
                                this._myTimeoutId2 = setTimeout(function(){
                                            that._closeExploreMenuPopup();
                                        },  this._delayOnCollapse_ExploreMenu); //600
                            }
                        }
                    }
                });
                that._on(that.divMainMenu.find('li[data-action-popup="recordAddSettings"]'), {
                    click: that._mousein_ExploreMenu
                });
                that._on(that.divMainMenu.find('#filter_by_groups'),{ //, #filter_by_groups
                    mouseenter: that._mousein_ExploreMenu,
                    mouseleave: function(e){
                            clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0; //clear timeout on show section menu
                            //this._resetCloseTimers();//reset
                            this._myTimeoutId2 = setTimeout(function(){
                                        that._closeExploreMenuPopup();
                                    },  this._delayOnCollapse_ExploreMenu); //600
                    }
                });
                that._on(that.menues['explore'].find('li[data-action-popup="databaseOverview"]'), {
                    click: that.showDatabaseOverview
                });
                
                //forcefully hide coverAll on click
                that._on(that.coverAll, {
                    click: function(){
                            that._closeExploreMenuPopup();
                            that._collapseMainMenuPanel(true);
                    }
                });
                
                that._on(window.hWin.document, { //that.element
                    click: function(e){
                        //get current filter dialog
                        let ele = $('.save-filter-dialog:visible')
                        if(ele.length>0){
                                if (ele.parents('.ui-menu')) return;
                            
                                let prnt = ele.parent();
                                if(prnt.hasClass('ui-dialog') || prnt.hasClass('ui-menu6-section')){
                                    ele = prnt;
                                }
                                let x = e.pageX;
                                let y = e.pageY;
                                let x1 = $(ele).offset().left;
                                let y1 = $(ele).offset().top;
                                
                                if(x>0 && y>0){
                                if(x<x1 || x>x1+$(ele).width() ||
                                   y<y1 || y>y1+$(ele).height())
                                {
                                   that._closeExploreMenuPopup();
                                }
                                }
                        }                        
                    }
                });
                    
/*                
                that._on(that.divMainMenu.find('.menu-explore[data-action-onclick="svsAdd"]'), 
                {click: function(e){
                    that.addSavedSearch();
                }});
*/                
        });
        
        //find all saved searches for current user
        if(!window.hWin.HAPI4.currentUser.usr_SavedSearch){  
            window.hWin.HAPI4.SystemMgr.ssearch_get( null,
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        window.hWin.HAPI4.currentUser.usr_SavedSearch = response.data;
                    }
            });
        }
        
       
        
        //fix bug for tinymce popups - it lost focus if it is called from dialog
        $(window.hWin.document).on('focusin', function(e) {
            if ($(e.target).closest(".tox-tinymce-aux").length) {
                e.stopImmediatePropagation();
            }
        });

        $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE
                +' '+window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE
                +' '+window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
                +' '+window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
                +' '+window.hWin.HAPI4.Event.ON_CUSTOM_EVENT
                +' '+window.hWin.HAPI4.Event.ON_CREDENTIALS, 
            function(e, data) {
                
                if(e.type == window.hWin.HAPI4.Event.ON_CUSTOM_EVENT){
                    if(data?.userWorkSetUpdated){
                            that._refreshSubsetSign();
                    }
                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){
                    
                    that._onSearchStart(data);
                    
                }
                else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){

                    that._onSearchFinish(data);
                    
                }
                else if(e.type == window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE || e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS){
                    
                    that._onPreferencesChange(e, data);

                }
                else{  //ON_STRUCTURE_CHANGE
                    //refresh list of rectypes after structure edit
                    that._updateDefaultAddRectype();
                    window.hWin.HEURIST4.browseRecordCache = {};
                    window.hWin.HEURIST4.browseRecordTargets = {};
                }
        });
        
    }, //end _create

    _onPreferencesChange: function (e, data){                    

        if(data?.origin=='recordAdd'){
            this._updateDefaultAddRectype( data.preferences );
        }else{
            this._updateDefaultAddRectype();
        }
        if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS || data?.refresh_favourites){
            this.populateFavouriteFilters();
        }        
    },               

    _onSearchFinish: function (data){                    

        if(data?.request && (data.request.ispreview || data.request.increment || data.search_realm)) return;
        
        this.coverAll.hide();
        // window.hWin.HAPI4.currentRecordset is the same as data.recordset
        if(data.recordset && data.recordset.length()>0){
            this._updateSaveFilterButton(2);
        }else{
            this._updateSaveFilterButton(0);
        } 
        
        this._refreshSubsetSign();                    
    },          
    
    _onSearchStart: function (data){                    
        //not need to check realm since this widget the only per instance
        if(data?.ispreview || data?.increment || data?.search_realm) return;

        // Check whether to block auto switch to explore menu
        let move_to_explore = !data.no_menu_switch;
        if(Object.hasOwn(data, 'no_menu_switch')){
            delete data.no_menu_switch;
            delete window.hWin.HEURIST4.current_query_request.no_menu_switch;
        }
        
        this.reset_svs_edit = true;
        if(!data?.reset){
            //keep current search for "Save Filter"
            this.currentSearch = window.hWin.HEURIST4.util.cloneJSON(data);
            this._updateSaveFilterButton(1);

            if(move_to_explore){
                this.switchContainer('explore');
                this._mouseout_SectionMenu();
                this._collapseMainMenuPanel(true, 1000);
            }
            
        }else if(data?.reset){
            this.currentSearch = null;
            this._updateSaveFilterButton(0);
        }
    },
    
    //
    //
    //
    _isCurrentActionFilter: function(){
            return (this._current_explore_action=='searchBuilder' ||
                    this._current_explore_action=='svsAdd' || 
                    this._current_explore_action=='svsAddFaceted' );
    },
    
    //
    // 0 - disabled
    // 1 - search in progress
    // 2 - bounce and ready to save
    //
    _updateSaveFilterButton: function( mode ){
        
        let btn = this.divMainMenu.find('.menu-explore[data-action-popup="svsAdd"]');
        
        if(mode==0){ //disabled
           
            
            //btn.hide();//
            btn.find('span.ui-icon')
                .removeClass('ui-icon-loading-status-lines rotate')
                .addClass('ui-icon-filter-plus');
            window.hWin.HEURIST4.util.setDisabled(btn, true);
            
        }else if(mode==1){ //search in progress
            
           
            btn.find('span.ui-icon')
                .removeClass('ui-icon-filter-plus')
                .addClass('ui-icon-loading-status-lines rotate');
        }else{
            
            window.hWin.HEURIST4.util.setDisabled(btn, false);
           
            
            btn.find('span.ui-icon')
                .removeClass('ui-icon-loading-status-lines rotate')
                .addClass('ui-icon-filter-plus');
          
            let that = this;       
            btn.effect( 'pulsate', null, 4000, function(){
                btn.css({'padding':'6px 2px 6px '+(that.divMainMenu.width()==that._widthMenu?16:30)+'px'});
               
            } );
            
        }
        
        
        
    },
    
    //
    // Change label for add record link 
    //
    _updateDefaultAddRectype: function( preferences ){

        //show/hide bookmarks section in saved filters list
        let bm_on = (window.hWin.HAPI4.get_prefs('bookmarks_on')=='1');
        let ele_bookmark = this.divMainMenu.find('.menu-explore[data-action-popup="svs_list"][data-id="bookmark"]')
        if(bm_on) ele_bookmark.show();
        else ele_bookmark.hide();      
        
        let prefs = preferences || window.hWin.HAPI4.get_prefs('record-add-defaults');
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(prefs)){
            return;   
        }    
        
            let rty_ID = prefs[0];

            let ele = this.element.find('li[data-action-popup="recordAdd"]');

            if(ele.length > 0){

                if(rty_ID>0 && $Db.rty(rty_ID,'rty_Name')){

                    ele.find('.newrec-text').html(window.hWin.HR('add_new_record2')).css('font-size', '');
                    ele.find('.rectype-name').html('[<i>'+window.hWin.HEURIST4.util.htmlEscape($Db.rty(rty_ID,'rty_Name'))+'</i>]');
                    this.element.find('li[data-action-popup="recordAdd"]').css('width', '');

                    ele.attr('data-id', rty_ID);
                    ele.attr('title', 'New ' + window.hWin.HEURIST4.util.htmlEscape($Db.rty(rty_ID,'rty_Name')) + ' record');
                    this._off(ele, 'click');
                    this._on(ele, {click: function(e){
                        let ele = $(e.target).is('li')?$(e.target):$(e.target).parents('li');
                        let rty_ID = ele.attr('data-id');

                        this._collapseMainMenuPanel(true); 
                        setTimeout('window.hWin.HEURIST4.ui.openRecordEdit(-1, null,{new_record_params:{RecTypeID:'+rty_ID+'}})',200);
                    }});
                }else{
                    ele.find('.newrec-text').text('New record').css('font-size', '10px');
                    ele.find('.rectype-name').text('');
                    this.element.find('li[data-action-popup="recordAdd"]').css('width', '85px');

                    ele.attr('data-id','');
                    this._off(ele, 'click');
                }
            }

            ele = this.menues['populate'].find('li[data-action-popup="recordAdd"]');

            if(ele.length > 0){

                if(rty_ID>0 && $Db.rty(rty_ID,'rty_Name')){
                    ele.find('span.menu-text').html(`New <i>${window.hWin.HEURIST4.util.htmlEscape($Db.rty(rty_ID,'rty_Name'))}</i>`);
                }else{
                    ele.find('span.menu-text').html(`New record`);
                }

            }
        

    },
    
    _refresh: function(){
    },
    
    _destroy: function() {
        
        this.divMainMenu.remove();
        
        if(this.edit_svs_dialog) this.edit_svs_dialog.remove();
        if(this.search_faceted) this.search_faceted.remove();
        
        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE
                +' '+window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE
                +' '+window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
                +' '+window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
                +' '+window.hWin.HAPI4.Event.ON_CREDENTIALS);
    },
    
    // 
    // returns true if explore menu popup should remain open (even on mouse out)
    //
    _isExplorerMenu_locked: function(){
        
        return (this._explorer_menu_locked    
                || this.element.find('.ui-selectmenu-open').length>0
                || $('.list_div').is(':visible')      //tag selector dropdown      
                || $('.ui-widget-overlay.ui-front').is(':visible')   //some modal dialog is open
                );
    },

    //
    // collapse main menu panel on explore mouseout
    //    
    _collapseMainMenuPanel: function(is_instant, is_forcefully) {

        let that = this;
        if(is_forcefully>0){
            this._is_prevent_expand_mainmenu = true;
            this._myTimeoutId5 = setTimeout(function() { that._is_prevent_expand_mainmenu = false },is_forcefully);
        }else if(this._isExplorerMenu_locked() ){
            return;  
        } 
        
        if(is_instant && this._myTimeoutId>0){
            clearTimeout(this._myTimeoutId);
        }
        clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0;
        
        this._myTimeoutId = setTimeout(function() {
            that._myTimeoutId = 0;

            that.coverAll.hide();
            
            if(that.menues_explore_gap) that.menues_explore_gap.hide();

            that.divMainMenu.find('li.menu-explore, div.menu-explore').css('background','none'); // remove leftover highlight
            that.divMainMenu.find('.rectype-name').css({'width': '80px', 'max-width': '80px', 'margin-left': '10px'});

            that.divMainMenu.find('.menu-explore[data-action-popup="recordAdd"]').css('padding', '6px 20px 6px 0px');
            that.divMainMenu.find('.menu-explore[data-action-popup="recordAddSettings"]').css({padding:'6px 2px 6px 0px', width: '85px'});

            that.divMainMenu.find('.ui-heurist-quicklinks').css({'text-align':'center'});
            
            $.each(that.divMainMenu.find('.section-head'), function(i,ele){
                ele = $(ele);
                ele.css({'padding-left': ele.attr('data-pl')+'px'});
            });
            
            that.divMainMenu.find('#svs_list').hide();
            //that.divMainMenu.find('#filter_by_groups').show(); IJ 2020-11-23 always show in explore menu only

            if(that.divMainMenu.width()>that._left_position)
                that.divMainMenu.stop().effect('size',  { to: { width: that._left_position } }, 
                    is_instant===true?10:300, function(){ //91
                    that.divMainMenu.css({bottom:'4px',height:'auto'});
                    that._closeExploreMenuPopup();
                });

            if (that.menues[that._active_section]) 
            {
                that.menues[that._active_section].css({left:(that._left_position+5)});
            }   
            
            that._switch_SvsList( 0 );
                
        }, is_instant===true?10:this._delayOnCollapseMainMenu); //800
    },
    
    //
    // expand main menu panel on explore mouse in
    //
    _expandMainMenuPanel: function(e) {

        if(this._is_prevent_expand_mainmenu) return; // || this._active_section=='explore'

        clearTimeout(this._myTimeoutId); //terminate collapse
        this._myTimeoutId = 0;
        if(this.divMainMenu.width()==this._widthMenu) return; //200 already expanded
        
        this.coverAll.show();
        
        let that = this;
        this._mouseout_SectionMenu();
        this.divMainMenu.stop().effect('size',  { to: { width: that._widthMenu } }, 300, //300
            function(){
                that.divMainMenu.find('ul').css({'padding-right':'0px'});
                that.divMainMenu.find('.ui-heurist-quicklinks').css({'text-align':'left'});
                that.divMainMenu.find('.section-head').css({'padding-left':'12px'});

                that.divMainMenu.find('.rectype-name').css({'width': '', 'max-width': '', 'margin-left': '0px'});

                that.divMainMenu.css({bottom:'4px',height:'auto'});

                that.divMainMenu.find('.menu-explore[data-action-popup="recordAdd"]').css('padding', '6px 2px 6px 16px');
                that.divMainMenu.find('.menu-explore[data-action-popup="recordAddSettings"]').css({padding:'6px 2px 6px 16px', width: ''});
                
                //change parent for cont? 
                that.divMainMenu.find('#filter_by_groups').hide();
                that._switch_SvsList( 1 );
                
                if (! window.hWin.HEURIST4.ui.isVisible(that.containers[that._active_section]) )
                {
                    that.menues[that._active_section].css({left:that._widthMenu+5});
                }   
            });
    },
    
    // RENAME 
    // leave explore popup
    //    
    _mouseout_SectionMenu: function(e) {

        if( this._isExplorerMenu_locked() ) return;
        
        let that = this;
        
        clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0;
        
        function __closeAllsectionMenu() {
            
            let section_name = that._getSectionName(e);
            if(that._active_section!=section_name)
            {
                $.each(that.sections, function(i, section){
                    if(that._active_section!=section){
                        that._closeSectionMenu(section); 
                    }
                });
                that._closeExploreMenuPopup();
            }
        }        
        
        if(e){
            this._resetCloseTimers();//reset
            if (!this._isCurrentActionFilter()){
                this._myTimeoutId2 = setTimeout(function(){
                                                that._closeExploreMenuPopup();
                                                that._collapseMainMenuPanel();                                        
                                        },  this._delayOnCollapse_ExploreMenu);
            }
        }else{
            __closeAllsectionMenu();
        }
    },
    
    //
    // prevent close section and main menu
    //
    _resetCloseTimers: function(){

        clearTimeout(this._myTimeoutId2); this._myTimeoutId2 = 0; //delay on close explore popup menu
        clearTimeout(this._myTimeoutId); this._myTimeoutId = 0; //delay on collapse main menu (_expandMainMenuPanel/_collapseMainMenuPanel)
    },
    //
    // show explore menu popup (show_ExploreMenu) next to slidersMenu_explore or mainMany quick links
    //
    _mousein_ExploreMenu: function(e) {

        if( this._isExplorerMenu_locked() ) return;
        this._explorer_menu_locked = false;

        clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0; //clear timeout on show section menu
        
        this._resetCloseTimers();
        
        this.divMainMenu.find('li.menu-explore, div.menu-explore').css('background','none');

        const target = $(e.target);
        const ele = target.is('li, div.menu-explore') ? target : target.parents('li');
        let hasAction = ele?.attr('data-action-popup');    

        if (target.attr('id') === 'filter_by_groups' || hasAction === 'search_recent' || hasAction === 'databaseOverview') {
            return;
        }

        if (ele?.parents('.ui-heurist-quicklinks').length > 0) {
            ele.css('background', 'aliceblue');
        }
        
        if (target.parents('.ui-heurist-quicklinks').length==0) {
            this._collapseMainMenuPanel(true); // close instantly
        }
        
        if(hasAction){
            this.show_ExploreMenu(e);    
        } else {
            let that = this;
            this._myTimeoutId2 = setTimeout(function(){
                                        that._closeExploreMenuPopup();
                                    },  this._delayOnCollapse_ExploreMenu); //600
        }
    },
    
    // helper
    _getDelay: function getDelay(action_name, menu_item) {
        return action_name === 'recordAdd' && menu_item?.attr('data-id') > 0
            ? this._delayOnShow_AddRecordMenu
            : this._delayOnShow_ExploreMenu;
    },
    
    // Helper functions
    _getMenuItem: function (e, action_name) {
        if (!action_name) {
            const target = $(e.target);
            return target.is('li, div.menu-explore') ? target : target.parents('li');
        }
        return null;
    },
        
    //
    // show popup extension of explore menu
    //        
    show_ExploreMenu: function(e, action_name, position) {
        
        let menu_item = this._getMenuItem(e, action_name);

        action_name = menu_item?.attr('data-action-popup') || action_name;

        if(this._current_explore_action==action_name) return;

        const that = this;
        let expandRecordAddSetting = false;
        if (action_name === 'recordAddSettings') {
            action_name = 'recordAdd';
            expandRecordAddSetting = e.type === 'click';
        }
        
        
        const delay = this._getDelay(action_name, menu_item);
            
        if(action_name != 'recordAdd'){
            this.menues_explore_popup
                    .removeClass('ui-heurist-populate record-addition')
                    .addClass('ui-heurist-explore');
        }      

        clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0; //clear previous delay before open
        
        //that.open_ExploreMenu(action_name, position);
        //delay before open explore section menu
        this._myTimeoutId3 = setTimeout(
        function(){

            that._current_explore_action = action_name;
            that.hideDatabaseOverview();
  
            //menu section has several containers with particular widgets
            let cont = that.menues_explore_popup.find('#'+action_name);
            if(cont.length==0){
                //create new one
                cont = $('<div id="'+action_name+'" class="explore-widgets">').appendTo(that.menues_explore_popup);
            }

            //stop show animation and hide others
            that.menues_explore_popup.find('.explore-widgets').finish().hide();
          
            if(action_name!='svsAdd'){
                that.closeSavedSearch();
            }
            if(action_name!='svsAddFaceted'){
                that.closeFacetedWizard();
            }
            
            let { explore_top, explore_left, explore_height, explore_width } = that._getMenuPosition(menu_item, action_name, position);
            
            if(action_name=='svsAdd'){
                that._closeExploreMenuPopup();
                that.addSavedSearch( 'saved', false, explore_left, explore_top );
                return;
            }
            else if(action_name=='svsAddFaceted'){
                that._closeExploreMenuPopup();
                that.addSavedSearch( 'faceted', false, explore_left );
                return;
            }

            that._handleActionInit(action_name, cont, expandRecordAddSetting);
                
            that.menues_explore_popup.css({
                left: explore_left,
                top: explore_top,
                height: explore_height,
                width: expandRecordAddSetting?'500px':explore_width,
                'z-index': 103,
                overflow: action_name === 'searchBuilder' ? 'hidden' : 'hidden auto',
            }).show();
            
            if(explore_left+that.menues_explore_popup.outerWidth() > that.element.innerWidth()){
                    explore_left = that.element.innerWidth() - that.menues_explore_popup.outerWidth();
                    that.menues_explore_popup.css({
                        left: explore_left
                    });
            }
                        
            cont.fadeIn(delay+200, function(){
                let action_name = $(this).attr('id');
                that.menues_explore_popup.find('.explore-widgets[id!="'+action_name+'"]').hide();
                
                if(action_name=='searchByEntity'){
                    //trigger refresh  myOnShowEvent
                    $(this).searchByEntity('refreshOnShow');
                }
            });

        }, delay);
        
    },    
    
    _getMenuPosition: function(menu_item, action_name, position){
      
        let explore_left = ((this.divMainMenu.width()>this._left_position)?this._widthMenu:this._left_position)+4; 
        let explore_top = '2px';
        let explore_height = 'auto';
        let explore_width = '300px';

        if (action_name === 'searchBuilder') {
            explore_height = 450;
            explore_width = '850px';
        }
        
        if(position){
            explore_top = position.top;
            explore_height = position.left;
            return { explore_top, explore_left, explore_height, explore_width };
        }
        
        const qlinks_cnt = menu_item?menu_item.parents('.ui-heurist-quicklinks').length:-1; 
        if (qlinks_cnt === 0 && 
                (this._active_section === 'explore' || this._active_section === 'populate')) 
        {
            explore_left = this._left_position + 211;
        } else if (qlinks_cnt === 1) {
            explore_left = this._widthMenu + 4;
        }

        if (action_name === 'searchBuilder') {
            
            let widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('resultList');
            if(widget){
                explore_top = widget.position().top + 100;
            }else if(menu_item){
                explore_top = menu_item.offset().top; //if called from menu   
            }
            
            if(this.element.innerHeight()>0 && explore_top+explore_height>this.element.innerHeight()){
                explore_top = this.element.innerHeight() - explore_height;
            }
        }
        
        explore_top = Math.max(0, explore_top);
        
        return { explore_top, explore_left, explore_height, explore_width };        
    },
    
    _handleActionInit: function(action_name, cont, expandRecordAddSetting){
        
            let that = this;
        
            if(action_name=='searchByEntity'){

                if(!cont.searchByEntity('instance'))
                    cont.searchByEntity({use_combined_select:true, 
                        mouseover: function(){that._resetCloseTimers()}, //NOT USED
                        onClose: function() { 
                                //start search on close
                                that.switchContainer('explore'); 
                        },
                        menu_locked: function(is_locked, is_mouseleave){ 
                            if(!is_mouseleave){
                                that._resetCloseTimers();    
                                that._explorer_menu_locked = is_locked;     
                            }
                        }
                    });    
            }
            else if(action_name=='searchBuilder'){
                
                if(!cont.searchBuilder('instance')){
                    //initialization
                    this.search_builder = cont.searchBuilder({
                        is_h6style: true,
                        onClose: function() { that._closeExploreMenuPopup(); },
                        menu_locked: function(is_locked, is_mouseleave){ 
                            if(!is_mouseleave){
                                that._resetCloseTimers();    
                                if(is_locked=='delay'){
                                    that.coverAll.show();
                                    that._delayOnCollapse_ExploreMenu = 2000;        
                                }else{
                                    that._explorer_menu_locked = is_locked;     
                                }
                            }
                    }  });    
                    
                    cont.addClass('save-filter-dialog');
                }else{
                    //refresh rectype dropdown
                    cont.searchBuilder('refreshRectypeMenu');
                }
                
            }
            else if(action_name=='search_filters' || action_name=='search_rules'){ //list of saved filters

                that._init_SvsList(cont, (action_name=='search_rules')?2:1);                
                
                that.menues_explore_popup.css({bottom:'4px'}); //,'overflow-y':'auto','overflow-x':'hidden'});
            }
            else if(action_name=='recordAdd'){
                
                that.menues_explore_popup
                    .css({bottom:'4px'})  //,overflow:'hidden'
                    .removeClass('ui-heurist-explore').addClass('ui-heurist-populate record-addition');

                //let expandRecordAddSetting = false;
                 
                if(!cont.recordAdd('instance')){
                    cont.recordAdd({
                        is_h6style: true,
                        innerTitle: true,
                        onClose: function() { 
                            that._closeExploreMenuPopup();
                        },
                        isExpanded: expandRecordAddSetting, //false - show list, true - show preferences dialog
                        mouseover: function() { that._resetCloseTimers()},
                        menu_locked: function(is_locked, is_mouseleave){ 
                            if(!is_mouseleave){
                                that._resetCloseTimers();
                                that._explorer_menu_locked = is_locked; 
                            }
                    }  });  
                }else{
                    cont.recordAdd('doExpand', expandRecordAddSetting);                        
                }
               

            }//endif        
        
    },

    //
    // List user's favourite filters
    //
    populateFavouriteFilters: function(favourite_filters, resize_only = false){

        const that = this;
        const explore_loaded = this.menues?.explore && this.menues.explore.find('ul.favourite-filters').length != 0;
        const savedsearches_loaded = window.hWin.HAPI4.currentUser.usr_SavedSearch;

        if(!explore_loaded){
            setTimeout(function(){ that.populateFavouriteFilters(favourite_filters); }, 1000);
        }else if(!savedsearches_loaded){

            window.hWin.HAPI4.SystemMgr.ssearch_get(null, (response) => {
                if(response.status == window.hWin.ResponseStatus.OK){
                    window.hWin.HAPI4.currentUser.usr_SavedSearch = response.data;
                    that.populateFavouriteFilters(favourite_filters, resize_only);
                }
            });
        }

        let $favourite_container = explore_loaded ? this.menues.explore.find('ul.favourite-filters') : [];
        if($favourite_container.length==0 || !explore_loaded || !savedsearches_loaded){
            return;
        }

        function updateHeight(height){
            $favourite_container.css('height', `${height}px`);
        }

        if(resize_only){ // just resize container
            let cont_height = this.menues.explore.height() - $favourite_container.position().top - 60;
            updateHeight(cont_height);
            return;
        }

        // clear list
        $favourite_container.find('li').remove();
        this.menues.explore.find('.favour-help').show();

        let cont_height = this.menues.explore.height() - $favourite_container.position().top;
        updateHeight(cont_height);

        if(this.menues.explore[0].clientHeight <= this.menues.explore[0].scrollHeight){ 
            cont_height -= (this.menues.explore[0].scrollHeight - this.menues.explore[0].clientHeight) + 40;
            updateHeight(cont_height);
        }

        favourite_filters = favourite_filters ?? window.hWin.HAPI4.get_prefs_def('favourite_filters', ['']);

        if(favourite_filters[0] == ''){
            return;   
        }

        if(!this.svs_list){
            this.getSvsList();
        }

        let idx = 0;
        while(idx < favourite_filters.length){

            const filter = favourite_filters[idx];

            if(window.hWin.HEURIST4.util.isempty(filter) || !Object.hasOwn(window.hWin.HAPI4.currentUser.usr_SavedSearch, filter[0])){
                // remove missing filters
                favourite_filters.splice(idx, 1);
                continue;
            }

            // Update label
            let name = window.hWin.HAPI4.currentUser.usr_SavedSearch[filter[0]][0];
            name = typeof name !== 'string' ? filter[1] : name;
            favourite_filters[idx][1] = name;

            let $remove_btn = $('<span>', {
                class: 'smallbutton ui-icon ui-icon-redo',
                style: 'display: none; float: right; margin-top: 1px; color: black',
                title: 'Remove filter from favourites',
                'data-fid': filter[0]
            });

            let $txt = $('<span>', {
                class: 'truncate',
                style: 'font-size: 12px; display: inline-block; max-width: 85%; vertical-align: text-top',
                title: name,
                text: name
            });

            $('<li>', {
                class: 'fancytree-node',
                style: 'padding: 6px; cursor: pointer;',
                'data-fid': filter[0]
            })
            .append($txt) // attach text
            .append($remove_btn) // attach remove btn
            .appendTo($favourite_container);

            idx++;
        }

        window.hWin.HAPI4.save_pref('favourite_filters', favourite_filters);

        if($favourite_container.find('li').length == 0){
            cont_height = this.menues.explore.height() - $favourite_container.position().top - 60;
            updateHeight(cont_height);
            return;
        }

        let block_filter = false;

        this._on($favourite_container.find('li'), {
            click: function(event){

                if(block_filter) { return; } // user current re-ordering favourite filters

                let $ele = $(event.target);
                if($ele.is('span') && !$ele.hasClass('smallbutton')){ // filter text clicked
                    $ele = $ele.parents('li.fancytree-node');
                }
                let id = $ele.attr('data-fid');

                if($ele.is('li.fancytree-node')){

                    this.hideDatabaseOverview();

                    this.svs_list.svs_list('doSearchByID', id, $ele.text()); // perform filter
                }else if($ele.is('span')){

                    // remove from list and preferences
                    $ele.parent().remove(); // remove from list

                    if(favourite_filters.length > 1){
                        // remove from prefs
                        let idx = favourite_filters.findIndex(filter => filter[0] == id);
                        favourite_filters.splice(idx, 1);
                    }else{
                        favourite_filters = [''];
                        this.menues.explore.find('.favour-help').show();
                    }

                    window.hWin.HAPI4.save_pref('favourite_filters', favourite_filters); // save prefs
                    this.populateFavouriteFilters(null, true); // fix container height
                }
            },
            mouseover: function(event){

                if($(event.target).hasClass('smallbutton')){
                    $(event.target).show();
                }else if($(event.target).hasClass('truncate')){
                    $(event.target).parent().find('span.smallbutton').show();
                }else{
                    $(event.target).find('span.smallbutton').show();
                }
            },
            mouseout: function(event){
                $favourite_container.find('span.smallbutton').hide();
            }
        });

        $favourite_container.sortable({
            start: function(event, ui){
                block_filter = true; // disable filtering
            },
            stop: function(event, ui){
                
                let new_order = [];

                $favourite_container.find('li').each(function(idx, ele){
                    let $ele = $(ele);
                    new_order.push([$ele.attr('data-fid'), $ele.find('span.truncate').text()]);
                });

                window.hWin.HAPI4.save_pref('favourite_filters', new_order); // save prefs

                setTimeout(function(){ block_filter = false; }, 1000); // re-enable filtering
            }
        });

        cont_height = this.menues.explore.height() - $favourite_container.position().top - 60;
        $favourite_container.css('height', cont_height + 'px');

        this.menues.explore.find('.favour-help').hide();
    },

    //
    // mode - filter mode - 0 all , 1 - filters only, 2 rules only
    //
    _init_SvsList: function(cont, mode){  //, group_ID
        
        if(!cont.svs_list('instance')){
            let that = this;
            
            cont.svs_list({
                is_h6style: true,
                hide_header: false,
                container_width: 300,
                filter_by_type: mode,
                onClose: function(noptions) { 
                    //!!! that.switchContainer('explore'); 

                    if(noptions==null){
                        //close faceted search
                        that._onCloseSearchFaceted();
                    }else{
                        noptions.onclose = function(){ that._onCloseSearchFaceted(); };
                        noptions.is_h6style = true;
                        noptions.maximize = true;

                        //open faceted search
                        that.search_faceted.show();
//BBB                                that.containers['explore'].css({left:'332px'}); //move to the right
//BBB                                that.containers['explore'].layout().resizeAll();  //update layout

                        if(that.search_faceted.search_faceted('instance')){ 
                            that.search_faceted.search_faceted('option', noptions ); //assign new parameters
                        }else{
                            //not created yet
                            that.search_faceted.search_faceted( noptions );
                        }
                        
                        that._closeExploreMenuPopup();
                        that._collapseMainMenuPanel(true);

                    } 
                },
                //show all groups! allowed_UGrpID:group_ID,
                menu_locked: function(is_locked, is_mouseleave){ 
                    if(!is_mouseleave){
                        that._resetCloseTimers();
                        that._explorer_menu_locked = is_locked; 
                    }
                },
                handle_favourites: function(filter_id, filter_name, is_drop=false){

                    let hasChanged = false;

                    let cur_favs = window.hWin.HAPI4.get_prefs_def('favourite_filters', ['']);
                    if(cur_favs[0] == '' || cur_favs.findIndex(filter => filter[0] == filter_id) == -1){ // add new
                        if(cur_favs[0] == ''){
                            cur_favs[0] = [filter_id, filter_name];
                        }else{
                            cur_favs.push([filter_id, filter_name]);
                        }

                        hasChanged = true;
                    }else if(!is_drop){ // remove existing

                        // remove from prefs
                        let idx = cur_favs.findIndex(filter => filter[0] == filter_id);
                        if(idx >= 0) { cur_favs.splice(idx, 1); } 

                        if(cur_favs.length == 0){
                            cur_favs = [''];
                        }

                        hasChanged = true;
                    }

                    if(hasChanged){ // save and re-make list
                        window.hWin.HAPI4.save_pref('favourite_filters', cur_favs);
                        that.populateFavouriteFilters(cur_favs);
                    }
                }
                //mouseover: function() { that._resetCloseTimers()},
            });
            
            this._on(cont,{mouseenter: this._resetCloseTimers}); 
            
        }else{
            cont.svs_list('option', 'filter_by_type', mode);
        }

        this.svs_list = cont;

        return cont;
    },
    
    //
    // mode = 0 - in menues['explore'],  1 in ui-heurist-quicklinks
    //    
    _switch_SvsList: function( mode ){
        
        return;//2020-12-15

    },

    //
    //
    //    
    getSvsList: function(){
        
        let cont = this.menues_explore_popup.find('#search_filters');
        
        if(cont.length==0){
            //create new one
            cont = $('<div id="search_filters" class="explore-widgets">').appendTo(this.menues_explore_popup);
        }
        return this._init_SvsList(cont, 1);
     
    },

    //
    //
    //
    _onCloseSearchFaceted: function(){
        if(window.hWin.HEURIST4.ui.isVisible( this.search_faceted )){
            $(this.document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, [ 
                {reset:true, search_realm:this.options.search_realm} ]);  //global app event to clear views
            this.search_faceted.hide();
        }
    },
    
    //
    //
    //
    _closeExploreMenuPopup: function(){

        if(this.menues_explore_popup){
            
            this.menues_explore_popup.hide();  
                
            //attempt for non modal 
            
            this._current_explore_action = null;
            this.closeSavedSearch();
            this.closeFacetedWizard();
            
        }
        // restore default values
        this._explorer_menu_locked = false;
        this._delayOnCollapse_ExploreMenu = 600;
        this.coverAll.hide();

    },
    
    //
    //
    //
    _closeSectionMenu: function( section ){
        
        if(this.menues[section]) {
            this.menues[section].css({'z-index':0}).hide();
        }
        if(this.menues_explore_gap){
            this.menues_explore_gap.hide();    
        }
        //this.menues[section].css({'z-index':2,left:'200px'}).show(); 
        if(section=='explore'){
            this._closeExploreMenuPopup();
        }
    },
    
    //
    //
    //
    _getSectionName: function(e){
        
        let section_name = null;
        if(e){
            let ele;
            if($(e.target).hasClass('ui-heurist-header') || $(e.target).hasClass('ui-heurist-quicklinks')){
                ele = $(e.target);
            }else{
                ele = $(e.target).parents('.ui-heurist-quicklinks');
                if(ele.length==0){
                    ele = $(e.target).parents('.ui-heurist-header');
                }
            }
            if(ele.length>0){
            $.each(this.sections, function(i, section){
                if(ele.hasClass('ui-heurist-'+section)){
                    section_name = section;
                    return false; //exit loop
                }
            });
            }
        }
        
        return section_name;
    },
    
    
    //
    // opens section menu permanently and switches container 
    //
    _openSectionMenu: function(e){
        
        let section = this._getSectionName(e);
        if(section=='explore' && this._active_section==section){
            this._onCloseSearchFaceted();
        }
        this.switchContainer( section );
        
        this._collapseMainMenuPanel(true, 200);
        
    },
    
    //
    // loads content of section from slidersMenuXxx.html
    //
    _loadSectionMenu: function( section ){
        
        this.menues[section] = $('<div>')
            .addClass('ui-menu6-section ui-heurist-'+section)
            .css({width:'200px'})   //,'border-left':'4px solid darkgray'})
            .appendTo( this.element );
            
        this.containers[section] = $('<div>')
            .addClass('ui-menu6-widgets ui-menu6-container ui-heurist-'+section) //ui-menu6-widgets to distinguish with introduction
            .appendTo( this.element );
            
        this.containers[section].css('left',(this._left_position+211)+'px');
            
        if(section=='explore'){

            this.menues_explore_popup = $('<div>')
                    .addClass('ui-menu6-section ui-heurist-explore')
                    .css({width:'200px'})
                    .hide()
                    .appendTo( this.element );
                    
            this._on(this.menues_explore_popup,{
                mouseover: function(e){
                    that._resetCloseTimers();
                    //clearTimeout(this._myTimeoutId2); this._myTimeoutId2 = 0; //prevent collapse of section menu popup
                },
                mouseleave: function(e){
                    if(this._active_section=='explore' || this._active_section=='populate'){
                        this._mouseout_SectionMenu(e);       
                    }else{
                        this._collapseMainMenuPanel()    
                    }
                }
            });
                    
                    
                    
            
            this.menues_explore_gap = $('<div>')
                    .css({'width':'4px', position:'absolute', opacity: '0.8', 'z-index':103, left:this._widthMenu+'px'}) //200
                    //.addClass('ui-heurist-explore-fade')
                    .hide()
                    .appendTo( this.element );
                    
            this.search_faceted = $('<div>')
                    .addClass('ui-menu6-container ui-heurist-explore')
                    .css({left:(this._left_position+5)+'px', width:'200px', 'z-index':102})
                    .hide()
                    .appendTo( this.element );
            
            this.containers[section]
                //.css({left:'206px'})
                .show();
                
            this.menues[section].show();

                
            //init explore container    
            window.hWin.HAPI4.LayoutMgr.appInitAll('SearchAnalyze3', this.containers[section] );
            //2024-12-08 const layout_cfg = window.hWin.HAPI4.LayoutMgr2.layoutGetById('SearchAnalyze4');
            //2024-12-08 window.hWin.HAPI4.LayoutMgr2.layoutInit(layout_cfg, this.containers[section] );
        }
        
        let that = this;
        this.menues[section].load(
                window.hWin.HAPI4.baseURL+'hclient/widgets/cpanel/slidersMenu'+section.capitalize()+'.html',
                function(){ 
                    
                    window.hWin.HAPI4.HRA( that.menues[section] );
                    
                    if(section=='explore'){
                        that._initSectionMenuExplore();                        
                    }else{
                        that._initSectionMenu(section);    
                    }
                    
                });
        
        
    },
    
    //
    // special behaviour form slidersMenuExplore
    //
    _initSectionMenuExplore: function(){
        
            let that = this;
            this._on(this.menues['explore'].find('.menu-explore'),{
                mouseenter: this._mousein_ExploreMenu,
                mouseleave: function(e){
                        clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0; //clear timeout on show section menu
                        //this._resetCloseTimers();//reset
                        this._myTimeoutId2 = setTimeout(function(){
                                    that._closeExploreMenuPopup();
                                },  this._delayOnCollapse_ExploreMenu); //600
                }
            });
            this._on(this.menues['explore'].find('li[data-action-popup="recordAddSettings"]'), {
                click: this._mousein_ExploreMenu
            });
            this._on(this.menues['explore'].find('li[data-action-popup="databaseOverview"]'), {
                click: this.showDatabaseOverview
            });
            this._updateDefaultAddRectype();

            let exp_img = $(this.element.find('img[data-src="gs_explore_cb.png"]')[0]);
            exp_img.attr('src', window.hWin.HAPI4.baseURL+'hclient/assets/v6/' + exp_img.attr('data-src'));

            this._on(this.element.find('li[data-action-popup="search_recent"]'),{
                click: function(event){

                    this.hideDatabaseOverview();

                    let q = '?w=a&q=sortby:-m';
                    let qname = 'All records';
                    if(!$(event.target).attr('data-search-all')){
                         q = q + ' after:"1 week ago"';
                         qname = 'Recent changes';
                    }
                    let request = window.hWin.HEURIST4.query.parseHeuristQuery(q); 
                    request.qname = qname;
                    window.hWin.HAPI4.RecordSearch.doSearch( this, request );
                }
            });

           
            //init 
            this._switch_SvsList( 0 );
            //this.svs_list = this._init_SvsList(this.menues['explore'].find('#svs_list'));  
            this._initSectionMenu( 'explore' );
    },

    //
    // finds menu actions via actionsHandler and assigns icon and title 
    // 
    // see sildersMenuXXX.html snippets for list of action for particular section 
    // and actions.json for list of all actions
    //
    _initSectionMenu: function( section ){

        //loop by <li> elements - search action and init item        
        $.each(this.menues[section].find('li[data-action]'),
            function(i, item){
                item = $(item);
                let action_id = item.attr('data-action');
                if( !action_id ){
                    return;
                }
                    
                    let action = window.hWin.HAPI4.actionHandler.findActionById(action_id);
                    
                    item.addClass('fancytree-node');
                    
                    if(action==null){
                        return;
                    }
                        
                        let action_icon = action.data?.icon || '';

                        let action_label = window.hWin.HR( action_id ); 
                        if(!action_label){ //localized version not found
                            action_label = action.text;
                        }
                    
                        $('<span class="ui-icon '+action_icon+'"></span>'
                         +'<span class="menu-text truncate" style="max-width: 109px;">'+action_label+'</span>')
                        .appendTo(item);

                        if(action_id=='menu-import-get-template'){
                            item.find('.ui-icon').addClass('ui-icon-gear');
                            item.css({'font-size':'10px', padding:'0 0 0 25px','margin-top':'-1px', 'margin-left': '0.25em'});
                        }else{
                            item.css({'font-size':'smaller', padding:'6px'})    

                            if(action_id=='menu-statistics-cms' && !window.hWin.HAPI4.sysinfo.matomo_api_key){
                                window.hWin.HEURIST4.util.setDisabled(item, true);
                            }
                        }

                        let action_hint = window.hWin.HR( action_id+'-hint' ); 
                        if(!action_hint){ //localized version not foind
                            action_hint = action.title;
                        }
                        if(!action_hint){
                            item.attr('title',action_hint);
                        }
                    
                
            });

        let $recAddSettings = this.menues[section].find('li[data-action-popup="recordAddSettings"]');
        if($recAddSettings.length > 0){
            $recAddSettings.css({'font-size':'10px', padding:'0 0 0 30px','margin-top':'-1px'});
        }
            
        $(this.menues[section].children()[1]).find('.ui-icon')
                    .addClass('ui-heurist-title')  //apply color
                    .css({cursor:'pointer'});
                    
        this.menues[section].find('.ui-icon-circle-b-help').css({cursor:'pointer'});
        this._on(this.menues[section].find('.ui-icon-circle-b-help'),
            {click: this._loadIntroductoryGuide});
        
        //execute menu on click           
        this._on(this.menues[section].find('li[data-action]'),{click:function(e){
            let li = $(e.target);
            if(!li.is('li')) li = li.parents('li');
            
            if(li.attr('data-action')=='menu-admin-server'){
                this.menues[section].find('li').removeClass('ui-state-active');
                li.addClass('ui-state-active');
            }
            
            
            if(section=='design'){    
                    $(this.containers[section])
                        .css({left:(this._left_position+211)+'px',right: '4px',top:'2px',bottom:'4px',width:'auto',height:'auto'});
            }

            window.hWin.HAPI4.actionHandler.executeActionById(li.attr('data-action')); 
        }});
        
        if(section=='publish'){
            this._on(this.menues[section].find('li[data-cms-action]'),{click:function(e){
                let li = $(e.target);
                if(!li.is('li')) li = li.parents('li');
                
                this.menues[section].find('li').removeClass('ui-state-active');
                li.addClass('ui-state-active');
                
                let btn = this.containers['publish'].find('#'
                                                    +li.attr('data-cms-action'));
                if(btn.length>0) btn.trigger('click');
                
            }});
        }else if (section=='populate'){
        
            this._updateDefaultAddRectype();
            
            //special behavior for 
            let ele = this.menues['populate'].find('li[data-action-popup="recordAdd"], li[data-action-popup="recordAddSettings"]');
            const that = this;
            this._on(ele,{
                mouseenter: function(e){

                    this._resetCloseTimers();
                    this.show_ExploreMenu(e);
                },
                mouseleave: function(e){

                    clearTimeout(this._myTimeoutId3); this._myTimeoutId3 = 0; //clear timeout on show section menu
                    this._resetCloseTimers();//reset
                    this._myTimeoutId2 = setTimeout(function(){  //was 6

                        that._closeExploreMenuPopup();
                    },  this._delayOnCollapse_ExploreMenu); 
                }
            });

            ele = this.menues['populate'].find('li[data-action-popup="recordAddSettings"]');

            this._on(ele, {
                click: function(e){
                    this._resetCloseTimers();
                    this.show_ExploreMenu(e);
                }
            });
        }

        if(this.menues[section].find('ul.accordionContainer').length > 0){ // check if current menu has any accordion groupings

            this.menues[section].find('ul.accordionContainer').accordion({
                header: '.accordionHeader',
                collapsible: true,
                active: false,
                beforeActivate: function(event, ui){

                    $(ui.newPanel).css({ // remove special styling of accordion content
                        'background': 'none',
                        'border': 'none',
                        'height': '',
                        'font-size': '14px'
                    });
                }
            });

            this.menues[section].find('li.accordionHeader').css('padding', '0px 0px 8px');
        }
    },

    //
    //
    //    
    _closeActiveSection:function(section, force_show){

            this._closeExploreMenuPopup();
            this._onCloseSearchFaceted();
            
            if(this._active_section && this.menues[this._active_section])
            {
                this.containers[this._active_section].hide();
                this.menues[this._active_section].hide();
                this.element.removeClass('ui-heurist-'+this._active_section+'-fade');
                this.menues_explore_gap.removeClass('ui-heurist-'+this._active_section+'-fade');
            }
            this._current_explore_action = null;
            this._active_section = section;

            //show menu and section 
            if(this.menues[section]){
                this.menues[section].css('z-index',101).show();    
            }

            if(force_show || (this.containers[section] && !this.containers[section].is(':empty'))){
                this.containers[section].show();    
            }else if(this.introductions?.section){
                this.introductions[section].css('left', (this._left_position+211)+'px').show();    
            }
            
            //change main background
            this.element.addClass('ui-heurist-'+section+'-fade');    
            if(this.menues_explore_gap){
                this.menues_explore_gap.addClass('ui-heurist-'+section+'-fade');    
            }
    },
    
    //
    // switch section on section menu click
    //
    switchContainer: function( section, force_show ){

        //hide all intros
        $.each(this.introductions, function(i, item){$(item).hide();});
        
        let that = this;
        if(that._active_section!=section ){
            that._closeActiveSection( section, force_show );
        }else if(force_show || section=='explore'){
            that.containers[section].show();    
        }else{
            return;
        }
        
        if(section != 'explore' || !that.containers[section]) {
            return;
        }
        
        if(that.containers[section].hasClass('ui-layout-container')){
             that.containers[section].layout().resizeAll();
        }
             
        that._switch_SvsList( 0 );

        this.hideDatabaseOverview();

        if(this._show_quick_tips){
            this._show_quick_tips = false; //show once
            window.hWin.HAPI4.actionHandler.executeActionById('menu-help-quick-tips');
        }

        this.populateFavouriteFilters(null, true); // resize favourite filters section
        

    },

    //-----------------------------------------------------------------
    //
    // SAVED FILTERS
    //
    closeSavedSearch: function(){
        if(this.edit_svs_dialog){
            this.edit_svs_dialog.closeEditDialog();
        }
    },
    closeFacetedWizard: function(){
        let faceted_search_wiz = $('#heurist-search-faceted-dialog');
        if(faceted_search_wiz && faceted_search_wiz.length>0){
            faceted_search_wiz.dialog('close');
        }
    },
        
    //
    // define new saved filter/search
    // mode - saved or faceted
    //
    addSavedSearch: function( mode, is_modal, left_position, top_position ){

        let that = this;

        if(this.edit_svs_dialog==null){
            this.edit_svs_dialog = new HSvsEdit();    
        }
        
        if( !window.hWin.HEURIST4.util.isPositiveInt(left_position) ){
            left_position = ((that.divMainMenu.width()>this._left_position)?this._widthMenu:this._left_position) + 4; 
            if(this._active_section=='explore'){
                left_position = this._left_position + 211;
            }
        }
        if( !window.hWin.HEURIST4.util.isPositiveInt(top_position) ){
            top_position = 40;
        }

        is_modal = (is_modal!==false);
  
/*        
        //find all saved searches for current user
        if(!window.hWin.HAPI4.currentUser.usr_SavedSearch){  
            window.hWin.HAPI4.SystemMgr.ssearch_get( null,
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        window.hWin.HAPI4.currentUser.usr_SavedSearch = response.data;
                    }
            });
        }
*/        
        //for faceted wizard and save filter delay is increased to 2sec
        this._delayOnCollapse_ExploreMenu = 2000;

        this.edit_svs_dialog.showSavedFilterEditDialog( mode, null, null, this.currentSearch , false, 
            { my: 'left+'+left_position+' top+'+top_position, at: 'left top', of:this.divMainMenu},
            function(){  //after save - trigger refresh of saved filter tree
                
                window.hWin.HAPI4.currentUser.usr_SavedSearch = null;
               
                if(that.svs_list){ 
                    that.svs_list.svs_list('option','hide_header',true);//to trigger refresh
                }
            }, 
            is_modal, 
            true, //is_h6style                                                                                                         
            function(is_locked, is_mouseleave){  //menu_locked
                if(is_mouseleave){
                    that._resetCloseTimers();

                }else if(is_locked=='close'){
                    that.coverAll.hide();                 
                    
                }else{
                    that._resetCloseTimers();    
                    
                    if(is_locked=='delay'){
                        that.coverAll.show();
                        that._delayOnCollapse_ExploreMenu = 2000;        
                    }else{
                        that._explorer_menu_locked = is_locked;     
                    }
                }
            },
            that.reset_svs_edit
        );  
        
        /*
        setTimeout(function(){
            $dlg.parent('.ui-dialog').css({top:that.divMainMenu.offset().top, left:(that._widthMenu+4)});    
        },300);
        */
        
        that.reset_svs_edit = false;
    },
    
    //
    //
    //
    initHelpDiv: function(){
        this.helper_div = $('<div>').addClass('ui-helper-popup').hide().appendTo(this.element);
        
        let _innerTitle = $('<div>').addClass('ui-heurist-header').appendTo(this.helper_div);  
                                
        $('<span>').appendTo(_innerTitle);
        let btn = $('<button>')
                    .button({icon:'ui-icon-closethick',showLabel:false, label:'Close'}) 
                    .css({'position':'absolute', 'right':'4px', 'top':'6px', height:24, width:24})
                    .appendTo(_innerTitle);
                    
                    
        this._on( btn, {click : function(){
                    this.helper_div.hide();
        }});
                                
        $('<div>').css({top:38}).addClass('ent_wrapper').appendTo(this.helper_div);  
        //this.containers[this._active_section]
    },
    
    //
    //
    //
    _refreshSubsetSign: function(){
        
            let container = this.menues['explore'].find('li[data-action="menu-subset-set"]');
           
            let ele = container.find('span.subset-info');
            if(window.hWin.HAPI4.sysinfo.db_workset_count>0){
                if(ele.length==0){
                    ele = $('<span class="subset-info"><span '
+'style="display:inline-block;color:red;font-size:smaller;padding-left:22px"></span>' //font-style:italic ;color:lightgray
+'<span class="ui-icon ui-icon-arrowrefresh-1-w clear_subset" style="font-size:0.7em;color:black;" '
+'title="'+window.hWin.HR('Click to revert to whole database')+'">'+
'</span></span>')
                        .appendTo(container);
                        
                    this._on(ele.find('span.clear_subset').css('cursor','pointer'),
                        {click: function(e){
                            window.hWin.HEURIST4.util.stopEvent(e);
                            let widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('resultList');
                            if(widget){
                                widget.resultList('callResultListMenu', 'menu-subset-clear'); //call method
                            }
                        }});
                
                }
                ele.find('span:first').html(window.hWin.HR('Current subset')
                        +' n&nbsp;&nbsp;=&nbsp;&nbsp;'+window.hWin.HAPI4.sysinfo.db_workset_count);
                ele.show();
                
            }else if(ele.length>0){
                ele.hide();
            }
    },

    //
    //
    //
    _initIntroductory: function( section ){
        
        if(!this.introductions[section]){

            let sname;
            if(section=='populate'){
                sname = 'Populate';
            }else{
                sname = section[0].toUpperCase()+section.substr(1);
            }

            this.introductions[section] = $('<div><div class="gs-box" style="margin:10px;max-width:500px;height:100px;cursor:pointer">'
                +'<div style="display:inline-block"><img width="110" height="60" alt="" src="'
                +window.hWin.HAPI4.baseURL+'hclient/assets/v6/gs_'+section+'.png"></div>'

                +'<span class="ui-heurist-title header" id="start-hints" style="display: inline-block; font-weight: normal;padding-left:20px;cursor: pointer">'
                    +'<span class="ui-icon ui-icon-help"></span>&nbsp;Startup hints</span>'

                +'<div class="ui-heurist-title" style="font-size: large !important;width: 80px;padding-top: 6px;">'+sname+'</div>'
            +'</div></div>')
                .addClass('ui-menu6-container AAA'+this._left_position+' ui-heurist-'+section)
                .css({'background':'none'})
                .appendTo( this.element );

        this._on(this.introductions[section].find('#start-hints'),{click:this._loadStartHints});

    }
    },

    //
    // Landing Page when not loading the Welcome page - change to widgets/admin/databaseOverview
    //
    showDatabaseOverview: function(){

        let that = this;
        let editingProperties = false;

        // Move to explore section
        if(this._active_section != 'explore'){
            this.switchContainer('explore');
        }

        function openDBProperties(event){
            if($(event.target).is('a') || editingProperties){
                return;
            }
            editingProperties = true;
            window.hWin.HAPI4.SystemMgr.verify_credentials(function(e){
                window.hWin.HEURIST4.ui.showEntityDialog('sysIdentification', {
                    beforeClose: function(){
                        editingProperties = false;
                        that.showDatabaseOverview();
                    }
                });
            }, 1);
        }

        function changeFuncAccess(){

            // DB Logo container
            let $thumb_container = $('div#db-thumb')
            .css({
                'display': 'block',
                'margin-left': 'auto',
                'margin-right': 'auto'
            })
            .off('click');

            $('#title_cont, #owner_cont, #rights_cont').off('click');
            $('div#description, button#btnEdit').off('click');

            // Check if logged in user has access to DB Props
            if(window.hWin.HAPI4.has_access(1)){ // Has access

                $('h3#title, span#owner, span#rights')
                .parent()
                .css('cursor', 'pointer')
                
                $('#title_cont, #owner_cont, #rights_cont')
                .one('click', openDBProperties);

                $('div#description')
                .css({'cursor': 'pointer', 'white-space': 'pre-wrap'})
                .on('click', openDBProperties);

                $('button#btnEdit')
                .prop('disabled', false)
                .button({label: 'Edit Metadata'})
                .addClass('ui-button-action')
                .css({
                    'display': 'inline-block'
                })
                .on('click', openDBProperties);

                $thumb_container.on('click', openDBProperties);

            }else{ // Has no access

                $('h3#title, span#owner, span#rights')
                .parent()
                .css({'cursor': 'default', 'resize': 'none'});

                $('button#btnEdit')
                .hide()
                .prop('disabled', true);

                $thumb_container
                .css('cursor', 'default');
            }
        }

        function changeStyles(){

            let owner = $('span#owner');
            let rights = $('span#rights');
            let desc = $('div#description');

            if(!window.hWin.HEURIST4.util.isempty(owner.html()) && owner.html() != 'Database Ownership'){
                owner.parent()
                .css({
                    'background': 'white',
                    'border': 'none',
                    'width': 'auto'
                });
            }else{
                owner.parent()
                .css({
                    'background': '#F4F2F4',
                    'border': '1px solid gray',
                    'width': '90%'
                });
            }

            if(!window.hWin.HEURIST4.util.isempty(rights.html()) && rights.html() != 'Database Rights'){
                rights.parent()
                .css({
                    'background': 'white',
                    'border': 'none',
                    'width': 'auto'
                });
            }else{
                rights.parent()
                .css({
                    'background': '#F4F2F4',
                    'border': '1px solid gray',
                    'width': '90%'
                });
            }

            if(!window.hWin.HEURIST4.util.isempty(desc.html()) && desc.html() != 'Database Description'){
                desc
                .css({
                    'background': 'white',
                    'border': 'none', 
                    'height': 'auto'
                });

               
            }else{
                desc
                .css({
                    'background': '#F4F2F4',
                    'border': '1px solid gray',
                    'height': '150px'
                });
            }
        }
        
        function updateDetails(){

            // Add DB Logo
            let $thumb_container = $('div#db-thumb')
            .text('')
            .css({
                'display': 'block',
                'margin-left': 'auto',
                'margin-right': 'auto',
                'border': 'none',
                'background': 'none'
            });

            let thumb_url = window.hWin.HAPI4.getImageUrl('sysIdentification', 1, 'thumb', 1);
            let date = new Date();

            thumb_url += '&ts=' + date.getTime();
            $('<img src='+ thumb_url +' class="image_input"></img>').appendTo($thumb_container);

            window.hWin.HAPI4.checkImage('sysIdentification', 1, 'thumb', 
                function(response){

                    if(response.status != 'ok' || response.data != 'ok'){
                        $thumb_container.find('img').remove();

                        $thumb_container
                        .text('No Logo')
                        .css({
                            'border': '1px solid gray', 
                            'background': '#F4F2F4',
                            'display': 'flex',
                            'justify-content': 'center',
                            'align-items': 'center',
                            'width': 'fit-content',
                            'color': 'gray'
                        });
                    }
                }
            );

            // Fill in System Identification info {DB Name, Owner, Copyright and Description}
            window.hWin.HAPI4.EntityMgr.getEntityData('sysIdentification', false, function(response){
                
                if(!window.hWin.HEURIST4.util.isempty(response)){
                
                    let record = response.getFirstRecord();

                    let name = record[14];
                    let ownership = record[15];
                    let rights = record[16];
                    let desc = record[17];

                    if(!window.hWin.HEURIST4.util.isempty(name) && name != 'Please enter a DB name ...'){
                        $('h3#title').text(name);
                    }else{
                        $('h3#title').text('Database Title');
                    }

                    if(!window.hWin.HEURIST4.util.isempty(ownership)){
                        $('span#owner').html("Owner: " + ownership);
                    }else{
                        $('span#owner').html('Database Ownership');
                    }

                    if(!window.hWin.HEURIST4.util.isempty(rights) && rights != 'Please define ownership and rights here ...'){
                        $('span#rights').html("Rights: " + rights);
                    }else{
                        $('span#rights').html('Database Rights');
                    }

                    if(!window.hWin.HEURIST4.util.isempty(desc)){
                        $('div#description').html(desc);
                    }else{
                        $('div#description').html('Database Description');
                    }
                }

                changeStyles();

            });
        }

        // Check if the page already exists
        if(this.containers['explore'].find('div#db_overview').length>0){

            this.containers['explore'].find('div#db_overview').css('z-index', '10').show();

            updateDetails();
            changeFuncAccess();

            return;
        }

        // Create main container
        let $ele = $('<div id="db_overview" class="ent_wrapper" style="background: white;">')
            .css('z-index', '10')
            .appendTo(this.containers['explore']);
        
        // Load Content
        // @later implement as widgets/admin/databaseOverview (on baseAction)
        $ele.load(window.hWin.HAPI4.baseURL+'hclient/widgets/cpanel/database_overview.html',
            function(){

                // Section headers within Content
                $('div.mock-header')
                .css({
                    'font-weight': 'bold',
                    'text-align': 'center',
                    'padding': '10px 0px',
                    'width': '85px',
                    'display': 'inline-block',
                    'color': 'white',
                    'border': '1px solid lightgray',
                    'cursor': 'pointer'
                })
                .on('click', function(e){
                    let option = $(e.target).is('img') ? 'explore' : $(e.target).attr('id');

                    if(window.hWin.HEURIST4.util.isempty(option)){
                        option = $(e.target).parent().attr('id');
                    }

                    $ele.hide();
                    
                    if(option != 'explore'){
                        that.switchContainer(option[0].toLowerCase() + option.substr(1));
                    }
                });
                // Add image to explore header
                let $explore_img = $('img#explore-img');
                $explore_img.attr('src', window.hWin.HAPI4.baseURL+'hclient/assets/v6/' + $explore_img.attr('data-src'));
                // "New" and "Add record"
                $('div.add-new')
                .css({
                    'background': 'lightgray',
                    'display': 'inline-block',
                    'margin-left': '10px',
                    'padding': '10px',
                    'cursor': 'pointer'
                })
                .on('click', function(){
                    $('li.menu-explore[data-action-popup="recordAdd"]').trigger('mouseover');
                });

                // Explanation Text for each section
                $('span.flavour-text')
                .css({
                    'display': 'inline-block',
                    'margin-left': '10px'
                });

                // Link to quick tips popup
                $('div#quick_tips')
                .css({
                    'text-decoration': 'underline',
                    color: 'blue',
                    width: '100px',
                    'text-align': 'center',
                    'margin-left': 'auto',
                    'margin-right': 'auto',
                    'margin-top': '40px',
                    cursor: 'pointer'
                })
                .on('click', function(){
                    window.hWin.HAPI4.actionHandler.executeActionById('menu-help-quick-tips');
                });

                // Commonly used entities/rectypes
                let entity_container = $('ul#entity-usage').css({
                    'list-style-type': 'none',
                    'padding-left': '30px',
                    'margin-top': '0px'
                });

                let options = {
                    select_name: 'usage_select', 
                    useCounts: true, 
                    useGroups: false, 
                    useHtmlSelect: true, 
                    useIcons: true, 
                    useIds: true, 
                    ancor: null
                };

                let entities_usage = window.hWin.HEURIST4.ui.createRectypeSelectNew(null, options);

                entities_usage.find('option').each(function(idx, item){

                    if(idx >= 11){ // Max of 10
                        return false;
                    }

                    let $opt = $(item);
                    let count = $opt.attr('rt-count') >= 0 ? $opt.attr('rt-count') : '';

                    $('<li data-id="'+$opt.attr('entity-id')+'" style="font-size:smaller;padding:4px 0px 2px 0px">'
                        +'<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
                            + '" class="rt-icon" style="vertical-align:bottom;background-image: url(&quot;'+$opt.attr('icon-url')+ '&quot;);"/>'
                        +'<div class="menu-text truncate" style="max-width:130px;display:inline-block;" title="Search for '+$opt.text()+' records">'
                        +$opt.text()+'</div>'
                        +'<span style="float:right;min-width:20px;margin-left:10px;">'+count+'</span>'
                       +'</li>')
                    .appendTo(entity_container); 

                });

                entity_container.find('li[data-id]').on('click', function(e){
                    let $ele = $(e.target).is('li') ? $(e.target) : $(e.target).parent('li');

                    let rectype_id = $ele.attr('data-id');

                    if(rectype_id > 0){

                        let request = {
                            q: 't:'+rectype_id,
                            w: 'a',
                            qname: $Db.rty(rectype_id, 'rty_Plural'),
                            detail: 'ids'
                        };

                        window.hWin.HAPI4.RecordSearch.doSearch(this, request);

                        that.switchContainer('explore');
                    }
                });

                updateDetails();
                changeFuncAccess();
            }
        );
    },

    //
    // Hide landing Page
    //
    hideDatabaseOverview: function(){

        if(this.containers['explore'].find('div#db_overview').length > 0){
            this.containers['explore'].find('div#db_overview').hide();
        }
    },

    //
    //
    //
    _loadIntroductoryGuide: function(e){
        
        let section = this._active_section;

        this._off(this.introductions[section].find('.gs-box'),'click');
        
        let that = this;
        this.introductions[section]
                .load(window.hWin.HAPI4.baseURL+'startup/gettingStarted.html div.gs-box.ui-heurist-'+section,
                function(){
                    //init images and video
                    that.introductions[section].find('img').each(function(i,img){
                        img = $(img);
                        img.attr('src',window.hWin.HAPI4.baseURL+'hclient/assets/v6/'+img.attr('data-src'));
                    });

                    that.introductions[section].find('div.gs-box.ui-heurist-'+section)
                    .prepend( '<span class="ui-heurist-title header" id="start-hints" style="padding-top:57px;font-weight:normal;padding-left:20px;cursor:pointer">'
                                +'<span class="ui-icon ui-icon-help"></span>&nbsp;Startup hints</span>' ).on('click', function(){ that._loadStartHints(null); });					

                    that.introductions[section].find('.gs-box')
                        .css({position:'absolute', left:'10px', right:'10px', top:'10px', 'min-width':'700px', margin:0}) //,'padding-left':20
                        .show();
                    that.introductions[section].find('.gs-box > div:first').css('margin','23px 0');
                    that.introductions[section].find('.gs-box .ui-heurist-title.header')
                        .css({position:'absolute', left:'160px', top:'40px', right:'400px', 'max-width':'540px'});

                    $('<div class="gs-box">')
                        .css({position:'absolute', left:10, right:10, top:180, bottom:10, 'min-width':400, overflow: 'auto'})
                        .load( window.hWin.HRes('menu_'+section)+' #content' )
                        .appendTo( that.introductions[section] );
                })
                .css({left:(that._left_position+211)+'px',right: '4px',top:'2px',bottom:'4px',width:'auto',height:'auto'})  //,'z-index':104
                .show();
                    
        this.containers[section].hide();
    },
    
    _loadStartHints: function(e){

        let section = this._active_section;

        this._off(this.introductions[section].find('#start-hints'),'click');
        this.introductions[section].find('#start-hints').hide();

        let that = this;

        this.introductions[section]
                .load(window.hWin.HAPI4.baseURL+'startup/gettingStarted.html div.gs-box.ui-heurist-'+section,
                function(){
                    // Display Section Img, hide link to YouTube video
                    that.introductions[section].find('img').each(function(i,img){
						img = $(img);
						img.attr('src',window.hWin.HAPI4.baseURL+'hclient/assets/v6/'+img.attr('data-src'));
                    });

                    // Display Content
                    that.introductions[section].find('.gs-box')
							.css({position:'absolute', left:10, right:10, top:10, 'min-width':700, margin:0}) //,'padding-left':20
							.show();
                    that.introductions[section].find('.gs-box > div:first').css('margin','23px 0');

                    that.introductions[section].find('.gs-box .ui-heurist-title.header')
							.css({position:'absolute', left:160, top:40, right:400, 'max-width':'540px'});

                    // Load Welcome Content
                    $('<div class="gs-box">')
						.css({position:'absolute', left:10, right:10, top:180, bottom:10, 'min-width':400, overflow: 'auto'})
						.load(window.hWin.HAPI4.baseURL+'hclient/widgets/cpanel/welcome.html', function(){
							
							// Bookmark Link
							let url = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database;
							$('.bookmark-url').html('<a href="#">'+url+'</a>').on('click', function(e){
								window.hWin.HEURIST4.util.stopEvent(e);
								window.hWin.HEURIST4.msg.showMsgFlash('Press Ctrl+D to bookmark this page',1000);
								return false;
							});

                            $('.ui-icon-bookmark').css('color', that._menu_colours[section]);
						})
						.appendTo( that.introductions[section] );
                })
                .css({left: ((that._left_position+211)+'px'),
                      right: '4px',top:'2px',bottom:'4px',width:'auto',height:'auto'})  //,'z-index':104
                .show();

        this.containers[section].hide();
    },

    closeContainer: function(section){
        this.containers[section].empty().hide();
    }
});
