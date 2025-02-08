/**
* navigation.js : menu based on RT_CMS_MENU records
* it is used for CMS
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

/* global layoutMgr */

$.widget( "heurist.navigation", {

    options: {
       menu_recIDs:[],  //top level menu records
       main_menu: false, //search for RT_CMS_HOME as root
       orientation: 'horizontal', //vertical or treeview
       target: 'inline', // inline (#page-content) or poup or target element id
       use_next_level: false,  //if top level consists of the single entry use next level of menues
       onmenuselect: null,   //for cms edit mode it performs special behavior
       selectable_if_submenu: true, //if item has submenu it is selectable by default
       aftermenuselect: null,
       toplevel_css:null,  //css for top level items
       expand_levels:0,  //expand levels for treeview
       onInitComplete: null,
       language: 'def',   //"xx" means take default - without code: prefix
       supp_options: null  //options to init page after load
    },
    
    menuData: null, //HRecordSet

    pageStyles:{},  //menu_id=>styles
    pageStyles_original:{}, //keep to restore  element_id=>css

    //to avoid recusion
    ids_cached_entries: {}, 
    ids_menu_entries: {},
    ids_recurred: [],

    //menu external urls
    menu_item_urls: {},
    
    first_not_empty_page_id:0,

    _current_query_string:'',

    // the widget's constructor
    _create: function() {

        let that = this;
        
        if(!this.options.language) this.options.language = 'def'; //"xx" means use current language

        if(this.element.parent().attr('data-heurist-app-id') || this.element.attr('data-heurist-app-id')){
            //this is CMS publication - take bg from parent
            if(this.element.parent().attr('data-heurist-app-id')){
                this.element.parent().css({'background':'none','border':'none'});
            }
           
        }else{
            this.element.css('height','100%');
            if(this.element.parents('.main-header').length>0){
                this.element.addClass('ui-heurist-header2');
            }
        }
        
        this.element.disableSelection();// prevent double click to select text
      
        if(this.options.orientation=='treeview'){

            let fancytree_options =
            {
                checkbox: false,
                //titlesTabbable: false,     // Add all node titles to TAB chain
                source: null,
                quicksearch: false, //true,
                selectMode: 1, //1:single, 2:multi, 3:multi-hier (default: 2)
                renderNode: null,
                extensions:[],
                activate: function(event, data) { 
                    //main entry point to start edit rts field - open formlet
                    if(data.node.data.page_id>0){
                        that._onMenuItemAction(data.node.data);    
                    }
                }
            };

            this.element.fancytree(fancytree_options).addClass('tree-cms');

        }else{
            
            this.divMainMenu = $("<div>").appendTo(this.element);
            
            // MAIN MENU-----------------------------------------------------
            this.divMainMenuItems = $('<ul>').attr('data-level',0)
                    //.css({'float':'left', 'padding-right':'4em', 'margin-top': '1.5em'})
                    .appendTo( this.divMainMenu );
                    
            if(this.options.orientation=='horizontal'){
                this.divMainMenuItems.addClass('horizontalmenu');
            }
        }

        
        this.reloadMenuData();

    },
    
    //
    //find menu contents by top level ids    
    //
    reloadMenuData:function(){
        
        //find menu contents by top level ids    
        let ids = this.options.menu_recIDs;
        if(ids==null){
            this.options.menu_recIDs = [];
            ids = '';    
        } else {
            if(Array.isArray(ids)) {ids = ids.join(',');}
            else if(window.hWin.HEURIST4.util.isNumber(ids)){
                this.options.menu_recIDs = [ids];
            }else{
                this.options.menu_recIDs = ids.split(',')  
            } 
        }

        //retrieve menu content from server side
        /*let request = { q: 'ids:'+ids,
            detail: //'detail'
               [window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'], 
                window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_MENU'], 
                window.hWin.HAPI4.sysinfo['dbconst']['DT_SHORT_SUMMARY'],
                window.hWin.HAPI4.sysinfo['dbconst']['DT_TARGET_ELEMENT']],
            id: window.hWin.HEURIST4.util.random(),
            source:this.element.attr('id') };
            */
        let request = {ids:ids, a:'cms_menu', main_menu: this.options.main_menu?1:0 };
        let that = this;
            
            
        window.hWin.HAPI4.RecordMgr.search(request, function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
                that.menuData = new HRecordSet(response.data);
                that._onGetMenuData();   
            }else{
                $('<p class="ui-state-error">Can\'t init menu: '+response.message+'</p>').appendTo(that.divMainMenu);
                
            }
        });
    },
    
    //
    //
    //
    isMenuItem: function(rec_id){

        if(this.menuData && rec_id){
            return !window.hWin.HEURIST4.util.isnull(this.menuData.getById(rec_id));
        }else{
            return false;
        }
        
    },
    
    // recursive function
    // resdata - result of request to server side
    // orientation - treeview, horizontal, vertical, list
    //
    getMenuContent: function(orientation, parent_id, menuitems, lvl){
        
        if(window.hWin.HEURIST4.util.isnull(parent_id)) parent_id = '0';
        if(window.hWin.HEURIST4.util.isnull(orientation)) orientation = this.options.orientation;
        if(window.hWin.HEURIST4.util.isnull(menuitems)) menuitems = this.options.menu_recIDs; //top menu items
        if(!lvl>0){
            lvl = 0;
            //to avoid recursion
            this.ids_menu_entries = {};
            this.ids_cached_entries = {};
            this.ids_recurred = [];
        } 
        
        let resdata = this.menuData;
        parent_id = ''+parent_id;
        
        
        let DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
            DT_SHORT_SUMMARY = window.hWin.HAPI4.sysinfo['dbconst']['DT_SHORT_SUMMARY'],
            DT_EXTENDED_DESCRIPTION = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'],
            DT_CMS_TOP_MENU = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TOP_MENU'],
            DT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_MENU'],
            DT_CMS_CSS = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_CSS'],
            DT_CMS_TARGET = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TARGET'],//target element on page or popup
            DT_CMS_PAGETITLE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETITLE'],//show page title above content
            DT_CMS_TOPMENUSELECTABLE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TOPMENUSELECTABLE'],//top menu selectable, if a submenu is available
            DT_THUMBNAIL = window.hWin.HAPI4.sysinfo['dbconst']['DT_THUMBNAIL'],
            DT_CMS_MENU_FORMAT = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_MENU_FORMAT'],
            
            TERM_NO = window.hWin.HAPI4.sysinfo['dbconst']['TRM_NO'], //$Db.getLocalID('trm','2-531'),
            TERM_NO_old = window.hWin.HAPI4.sysinfo['dbconst']['TRM_NO_OLD'],

            TRM_NAME_ONLY = window.hWin.HAPI4.sysinfo['dbconst']['TRM_NAME_ONLY'],
            TRM_ICON_ONLY = window.hWin.HAPI4.sysinfo['dbconst']['TRM_ICON_ONLY'];

        
        let res = (orientation=='list')?[]:'';
        let resitems = [];

        //submenu selectable is taken from home page
        if(parent_id==0 && menuitems.length==1){ //home page
            let record = resdata.getById(menuitems[0]);
            let selectable = resdata.fld(record, DT_CMS_TOPMENUSELECTABLE);
            if(selectable!==null){
                this.options.selectable_if_submenu = (selectable!==TERM_NO && selectable!==TERM_NO_old);
            }
        }

        for(let i=0; i<menuitems.length; i++)
        {
            
            let record = resdata.getById(menuitems[i]);
            
            if(!record) continue; //record may be non-public or deleted
            
            let page_id = menuitems[i];

            if(Object.hasOwn(this.ids_menu_entries, page_id) && this.ids_menu_entries[page_id].length > 0){ // check recursive references

                let parent_ids = parent_id.split(',');
                /*if(parent_ids.length > 0){
                    parent_ids.filter((id) => this.ids_menu_entries[page_id].indexOf(id));
                }*/
                if(parent_ids.indexOf(page_id) > 0){
                    this.ids_recurred.push(page_id);
                    continue;
                }
            }

            if(Object.hasOwn(this.ids_cached_entries, page_id)){ // retrieve cached menu item, available

                // first update parent
                let menu_value = this.ids_cached_entries[page_id];

                if(orientation == 'treeview'){

                    function _updateChildNodes(menu_items, new_id){

                        for(let i = 0; i < menu_items.length; i++){
                            menu_items[i]['parent_id'] = ''+new_id;
                            menu_items[i]['key'] = new_id + ',' + menu_items[i]['page_id'];

                            if(menu_items[i]['children'] && menu_items[i]['children'].length > 0){
                                menu_items[i]['children'] = _updateChildNodes(menu_items[i]['children'], menu_items[i]['key']);
                            }
                        }

                        return menu_items;
                    }

                    menu_value['parent_id'] = parent_id;
                    menu_value['key'] = parent_id + ',' + page_id;

                    if(menu_value['children'] && menu_value['children'].length > 0){
                        menu_value['children'] = _updateChildNodes(menu_value['children'], menu_value['key']);
                    }

                    resitems.push(menu_value);
                }else if(orientation != 'list'){

                    let old_parents = menu_value.match(/data-parentid="([\d,]+)"/g);
                    const parent_id_length = parent_id.split(',').length;
                    for(let cur_parent of old_parents){

                        let old_parent = cur_parent.match(/[\d,]+/)[0].split(',');
                        let new_parent = parent_id;

                        if(old_parent.length >= parent_id_length){
                            old_parent = old_parent.slice(parent_id_length);
                            new_parent += (old_parent.length > 0 ? ',' + old_parent.join(',') : '');
                        }

                        menu_value = menu_value.replace(cur_parent, `data-parentid="${new_parent}"`);
                    }

                    res = res + menu_value;
                }else if(orientation == 'list'){
                   
                    continue;
                }

            }else{
            
                let menuName = resdata.fld(record, DT_NAME, this.options.language);
                let menuTitle = resdata.fld(record, DT_SHORT_SUMMARY, this.options.language);
                let menuIcon = resdata.fld(record, DT_THUMBNAIL);

                let menuFormat = resdata.fld(record, DT_CMS_MENU_FORMAT);

                if(Array.isArray(menuIcon)){ // remove empty indexes
                    menuIcon = menuIcon.filter((icon) => icon?.length>4);//!window.hWin.HEURIST4.util.isempty(icon);
                }

                //target and position
                let pageTarget = resdata.fld(record, DT_CMS_TARGET);
                let pageStyle = resdata.fld(record, DT_CMS_CSS);
                let showTitle = resdata.fld(record, DT_CMS_PAGETITLE); 
                
                showTitle = (showTitle!==TERM_NO && showTitle!==TERM_NO_old);
                
                let hasContent = !window.hWin.HEURIST4.util.isempty(resdata.fld(record, DT_EXTENDED_DESCRIPTION));

                if(!(this.first_not_empty_page_id>0) && hasContent){
                    this.first_not_empty_page_id = page_id;
                }

                if(pageStyle){
                    this.pageStyles[page_id] = window.hWin.HEURIST4.util.cssToJson(pageStyle);    
                }
                 
                this.ids_menu_entries[page_id] = [];
                let $res = null;

                if(orientation=='treeview'){
                    $res = {};  
                    $res['key'] = parent_id + ',' + page_id; // set unique key
                    $res['title'] = menuName;
                    $res['parent_id'] = parent_id; //reference to parent menu(or home)
                    $res['page_id'] = page_id;
                    $res['page_showtitle'] = showTitle?1:0;
                    $res['page_target'] = (this.options.target=='popup')?'popup':pageTarget;
                    $res['expanded'] = (this.options.expand_levels>0 || lvl<this.options.expand_levels); 
                    $res['has_access'] = (window.hWin.HAPI4.is_admin() 
                                || window.hWin.HAPI4.is_member(resdata.fld(record,'rec_OwnerUGrpID')));
                                       
                    resitems.push($res);

                }else if(orientation=='list'){
                    
                    $res = {key:page_id, title:window.hWin.HEURIST4.util.htmlEscape(menuName) };

                    res.push($res);
                    
                }else{

                    let iconOnly = false;
                    let nameOnly = false;
                    let iconStyle = 'height:16px;width:16px;vertical-align:text-bottom;';

                    if(menuFormat){
                        iconOnly = menuFormat == TRM_ICON_ONLY;
                        nameOnly = menuFormat == TRM_NAME_ONLY;
                    }
                    iconOnly = iconOnly && !nameOnly && window.hWin.HEURIST4.util.isArrayNotEmpty(menuIcon);
                    
                    if(menuName && menuName.indexOf('<a') !== -1 && menuName.indexOf('</a>') !== -1){

                        let $temp_ele = $('<span>', {style: 'display:none'}).html(menuName);
                        let $a = $temp_ele.find('a[href]:first');

                        if($a.length > 0){

                            let link = $a.attr('href');
                            link = !link.match(/^\w+:\/\//) ? `https://${link}` : link;

                            let is_link = link.match(/^https?|^ftps?|^mailto/);
                            
                            this.menu_item_urls[page_id] = !is_link ? null : link;
                            menuName = !is_link ? menuName : $a.text();
                        }
                    }

                    menuName = window.hWin.HEURIST4.util.htmlEscape(menuName);
                    menuName = !window.hWin.HEURIST4.util.isempty(menuName) ? menuName.replace('&amp;', '&') : menuName;
                    menuName = iconOnly ? `<span style="display:none;">${menuName}</span>` : menuName;

                    menuTitle = window.hWin.HEURIST4.util.htmlEscape(menuTitle);
                    menuTitle = !window.hWin.HEURIST4.util.isempty(menuTitle) ? menuTitle.replace('&amp;', '&') : menuTitle;

                    iconStyle += !iconOnly ? 'padding-right:4px;' : '';

                    $res = '<li><a href="#" style="padding:2px 1em;'
                            +(hasContent?'':'cursor:default;')
                            +(iconOnly?'width:20px;':'')
                            +'" data-pageid="'+ page_id + '" data-parentid="'+ parent_id +'"'
                            + (pageTarget?' data-target="' + pageTarget +'"':'')
                            + ' title="'+menuTitle+'">'

                            + (!nameOnly && menuIcon?('<span><img src="'+window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                                +'&thumb='+menuIcon+'" '
                                +`style="${iconStyle}"></span>`):'')
                            + menuName+'</a>';
                    res = res + $res;
                }
                    
                let subres = '';
                let submenu = resdata.values(record, DT_CMS_MENU);
                if(!submenu){
                    submenu = resdata.values(record, DT_CMS_TOP_MENU);
                }
                //has submenu
                if(submenu){
                    if(!Array.isArray(submenu)) submenu = submenu.split(',');
                    
                    if(submenu.length>0){ 

                        this.ids_menu_entries[page_id] = submenu;

                        //next level
                        let submenu_parent_id = parent_id != 0 ? parent_id + ',' + page_id : page_id;
                        subres = this.getMenuContent(orientation, submenu_parent_id, submenu, lvl+1);
                        
                        if(orientation=='treeview'){
                            
                            $res['children'] = subres;
                            
                        }else if(orientation=='list'){
                           
                            res = res.concat(subres);
                            
                        } else if(subres!='') {
                            
                            res = res + '<ul style="min-width:200px"' 
                                      + (lvl==0?' class="level-1"':'') + '>'+subres+'</ul>';

                            $res = $res + '<ul style="min-width:200px"' 
                                        + (lvl==0?' class="level-1"':'') + '>'+subres+'</ul>';
                        }
                    }
                }
                
                if(orientation!='list' && orientation!='treeview'){
                    res = res + '</li>';
                }

                this.ids_cached_entries[page_id] = $res;
                
                //if parent has the only child use next level - (for top menu only)
                if(lvl==0 && menuitems.length==1 && this.options.use_next_level){
                        return subres;    
                }
                
            
            }
        }//for
        
        return (orientation=='treeview') ?resitems :res;
        
    },
    
    //
    // callback function on getting menu records
    // resdata - recordset with menu records (full data)
    //
    _onGetMenuData:function(){
            
        //reset
        this.ids_menu_entries = {}; 
        this.ids_cached_entries = {};
        this.ids_recurred = [];
        this.first_not_empty_page_id = 0;
        
        //get either treedata or html for jquery menu
        let menu_content = this.getMenuContent(null, 0, this.options.menu_recIDs, 0);     
        let DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'];
        
        if(this.ids_recurred.length>0 && window.hWin.HAPI4.has_access()){
            let s = [];
            for(let i=0;i<this.ids_recurred.length;i++){
                s.push(this.ids_recurred[i]+' '
                    +this.menuData.fld(this.menuData.getById(this.ids_recurred[i]), DT_NAME));
            }
            window.hWin.HEURIST4.msg.showMsgDlg('Some menu items are recursive references to a menu containing themselves. <br>'
            +'Such a structure is not permissible for obvious reasons. Ask website author to fix this issue. <div style="margin: 10px 0px">'
            +(s.join('<br>'))
            +'</div>If you are the author, simply edit the CMS Home record through the website editor (Site tab, then the Edit website layout/properties button), and delete duplicates (this will not delete the page content, only the extra reference to the menu entry)'
            +'<p>If you can\'t fix this problem yourself, please send a bug report and we will take care of it.</p>'
            ,null,null,{dialogId:'dialog-common-messages222',removeOnClose:true});
            
            /*+'<p>How to fix:<ul><li>Open in record editor</li>'
            +'<li>Find parent menu(s) in "Linked From" section</li>'
            +'<li>Open parent menu record and remove link to this record</li></ul>');*/
            /*window.hWin.HEURIST4.msg.showMsgDlg('Some menu items are recurred.<p>'
            +(s.join('<br>'))
            +'</p>Ask website author to fix this issue');*/            
        }
        
        //
        //
        //
        if(this.options.orientation=='treeview'){
            
            let tree = $.ui.fancytree.getTree( this.element );
            tree.reload( menu_content );
            this.element.find('.ui-fancytree').show();
            
        }else{

            $(menu_content).appendTo(this.divMainMenuItems);

            let opts = {};
            if(this.options.orientation=='horizontal'){
                //opts = {position:{ my: "left top", at: "left bottom" }}; //+20
                
                opts = { position:{ my: "left top", at: "left bottom" },
                        focus: function( event, ui ){
                            
                   if(!$(ui.item).parent().hasClass('horizontalmenu')){
                        //indent for submenu
                        let ele = $(ui.item).children('ul.ui-menu');
                        if(ele.length>0){
                            setTimeout(function() { ele.css({top:'0px',  left:'200px'}); }, 300);      
                        }
                   }else {
                        //show below
                        let ele = $(ui.item).children('ul.ui-menu');
                        if(ele.length>0){
                            setTimeout(function() { ele.css({top:'29px',  left:'0px'}); }, 500);      
                        }
                   } 
                }};
                
            }

            
            opts['icons'] = {submenu: "ui-icon-carat-1-e" }; 
            //init jquery menu widget
            this.divMainMenuItems.menu( opts );

/*            
            let myTimeoutId = 0;
            //show hide function
            let _hide = function(ele) {
                myTimeoutId = setTimeout(function() {
                    $( ele ).hide();
                    }, 800);
            };
            
            let _show = function(ele, parent) {
                clearTimeout(myTimeoutId);
                return false;
            };

            let all_menues = this.divMainMenuItems.find('ul.ui-menu');
            this._on( all_menues, {
                mouseenter : function(){ _show(); },
                mouseleave : function(){ 
                    _hide(all_menues) 
                }
            });
*/
          //prevents default jquery delay         
          this.divMainMenuItems.children('li.ui-menu-item')
            .on( "mouseenter", function(event) {
                    event.preventDefault();
                    $(this).children('.ui-menu').show();  
                } )
            .on( "mouseleave", function(event) {
                    event.preventDefault();
                    $(this).find('.ui-menu').hide();
                } );
 
            if(this.options.toplevel_css!==null){
                this.divMainMenuItems.children('li.ui-menu-item').children('a').css(this.options.toplevel_css);
            }

            if(this.options.orientation=='horizontal'){
                this.divMainMenuItems.children('li.ui-menu-item').children('a').find('span.ui-menu-icon').hide();
            }
                        
            //
            // if onmenuselect function define it is used for action
            // otherwise it loads content to page_target (#main-content by default)
            //
            this._on(this.divMainMenuItems.find('a').addClass('truncate'),{click:this._onMenuClickEvent});
        }

        
        if(window.hWin.HEURIST4.util.isFunction(this.options.onInitComplete)){
            this.options.onInitComplete.call(this, this.first_not_empty_page_id);
        }

        
        
       
    }, //end _onGetMenuData

    //
    //
    //
    _onMenuClickEvent: function(event){

        let $target = $(event.target);
        
        window.hWin.HEURIST4.util.stopEvent(event);

        if($target.is('span') || $target.is('img')){
            $target = $target.parents('[role="menuitem"]');
        }

        let data = {
            page_id: $target.attr('data-pageid'), 
            page_target: $target.attr('data-target')
        };

        //hide submenu
        $target.parents('.ui-menu[data-level!=0]').hide();

        const record = this.menuData.getRecord(data.page_id);
        const DT_EXTENDED_DESCRIPTION = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'],
              DT_CMS_PAGETITLE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETITLE'],
              TERM_NO = window.hWin.HAPI4.sysinfo['dbconst']['TRM_NO'],
              TERM_NO_old = window.hWin.HAPI4.sysinfo['dbconst']['TRM_NO_OLD'];

        // show page title
        let showTitle = this.menuData.fld(record, DT_CMS_PAGETITLE);
        data.page_showtitle = (showTitle!==TERM_NO && showTitle!==TERM_NO_old);
        // page has content
        data.hasContent = !window.hWin.HEURIST4.util.isempty(this.menuData.fld(record, DT_EXTENDED_DESCRIPTION));

        // menu is selectable
        let is_selectable = this.menuData.fld(record, window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TOPMENUSELECTABLE']);
        is_selectable = data.hasContent && 
                        is_selectable !== TERM_NO && is_selectable !== TERM_NO_old && 
                        this.options.selectable_if_submenu;

        if(Object.hasOwn(this.menu_item_urls, data.page_id) && 
            !window.hWin.HEURIST4.util.isempty(this.menu_item_urls[data.page_id])){ // open url in new window

            window.open(this.menu_item_urls[data.page_id], '_blank', 'noopener');
            return;
        }else if(!is_selectable && $target.parent().find('ul').length != 0){ // stop click if a submenu exists
            return;
        }
        if(!data.hasContent && !window.hWin.HEURIST4.util.isFunction(this.options.onmenuselect)){
            //no action if content is not defined
            
        }else if(data.page_id>0){

            let page_id = data.page_id;
            if($target.attr('data-parentid')){
                page_id = $target.attr('data-parentid') + ',' + page_id;
            }

            //highlight top most menu
            this.highlightTopItem(page_id);

            this._onMenuItemAction(data);                

        }

    },
    
    //
    // highlight top most menu
    //
    highlightTopItem: function(page_id){

        //dim all
        this.divMainMenuItems.find('a').trigger('mouseout').removeClass('selected');

        // find item
        let $ele = null;
        if(typeof page_id === 'string' && page_id.indexOf(',') > 0){

            let page_ids = page_id.split(',');
            page_id = page_ids.pop();
            let parent_id = page_ids.join(',');

            $ele = this.element.find(`a[data-pageid="${page_id}"][data-parentid="${parent_id}"]`).parents('.ui-menu-item');
        }else if(page_id>0){

            $ele = this.element.find('a[data-pageid="'+page_id+'"]');
            $ele = $ele.parents('.ui-menu-item');
        }

        if($ele && $ele.length>0){
            $($ele[$ele.length-1].firstChild).addClass('selected');
            setTimeout(() => {
                    if(this.divMainMenuItems.menu('instance'))
                        this.divMainMenuItems.menu('collapseAll');
            }, 1000);
        }
    },
    
    //
    //
    //
    _onMenuItemAction: function(data){

        let that = this;

        if(window.hWin.HEURIST4.util.isFunction(that.options.onmenuselect)){

            this.options.onmenuselect( data.page_id );

        }else{

            // redirected to websiteRecord.php 
            // with field=1 it loads DT_EXTENDED_DESCRIPTION
            let page_url = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
            +'&field=1&recid='+data.page_id;

            let pageCss = that.pageStyles[data.page_id];

            if(data.page_target=='popup' || this.options.target=='popup'){


                let opts =  {  container:'cms-popup-'+window.hWin.HEURIST4.util.random(),
                    close: function(){
                        $dlg.dialog('destroy');       
                        $dlg.remove();
                    },
                    open: function(){

                        let pagetitle = $dlg.find('h2.webpageheading');
                        if(pagetitle.length>0){ //find title - this is first children
                           
                            if(!data.page_showtitle){
                                pagetitle.hide();
                            }
                        }

                        window.hWin.HAPI4.LayoutMgr.appInitFromContainer2( $dlg );
                    }
                };

                let dlg_css = null;
                if(pageCss){

                    if(pageCss['position']){
                        let val = window.hWin.HEURIST4.util.isJSON(pageCss['position']);
                        if(val==false){
                            delete pageCss['position'];
                        }else{
                            pageCss['position'] = val;
                        }
                    }  
                    opts = $.extend(opts, pageCss);

                    dlg_css = window.hWin.HEURIST4.util.cloneJSON(pageCss);
                    if(dlg_css['width']) delete dlg_css['width'];
                    if(dlg_css['height']) delete dlg_css['height'];

                }else{
                    opts['width']= 750;                            
                }                                


                let $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(page_url, null, 
                    'Heurist', opts, dlg_css);

                if(dlg_css){
                    $dlg.css(dlg_css);
                }


            }
            else{

                let page_target = '#main-content';   
                
                if(this.options.target=='inline_page_content'){
                    page_target = '#page-content';
                }else if(!window.hWin.HEURIST4.util.isempty(data.page_target)) {
                    page_target = data.page_target;
                }

                //load page content to page_target element 
                if(page_target[0]!='#') page_target = '#'+page_target;

                
                let continue_load_page = function() {
                    
                    if(pageCss && Object.keys(pageCss).length>0){
                        if(!that.pageStyles_original[page_target]){ //keep to restore
                            that.pageStyles_original[page_target] = $(page_target).clone();
                           
                        }
                        $(page_target).css(pageCss);
                    }else if(that.pageStyles_original[page_target]){ //restore
                       
                        $(page_target).replaceWith(that.pageStyles_original[page_target]);                            
                    }
                    
                    let page_footer = $(page_target).find('#page-footer');
                    if(page_footer.length>0) page_footer.detach();
                
                    const DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
                    DT_EXTENDED_DESCRIPTION = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'];
        
                    const server_request = {
                        q: 'ids:'+data.page_id,
                        restapi: 1,
                        columns: 
                        ['rec_ID', DT_NAME, DT_EXTENDED_DESCRIPTION],
                        zip: 1,
                        format:'json'};
                    
                    //perform search see record_output.php       
                    window.hWin.HAPI4.RecordMgr.search_new(server_request,
                        function(response){
                          
                            if(window.hWin.HEURIST4.util.isJSON(response)) {
                                if(response['records'] && response['records'].length>0){
                                    let res = response['records'][0]['details'];
                                    let keys = Object.keys(res);
                                    for(let idx in keys){
                                        let key = keys[idx];
                                        res[key] = res[key][ Object.keys(res[key])[0] ];
                                    }
                                    //res[DT_NAME] = res[DT_NAME]
                                    //res[DT_NAME, DT_EXTENDED_DESCRIPTION, DT_CMS_SCRIPT, DT_CMS_CSS, DT_CMS_PAGETITLE]
                                    
                                    if(page_footer.length>0){
                                        page_footer.appendTo( $(page_target) );
                                        $(page_target).css({'min-height':$(page_target).parent().height()-page_footer.height()-10 });
                                    } 
                                    
                                    layoutMgr.layoutInit( res[DT_EXTENDED_DESCRIPTION], $(page_target), that.options.supp_options ); 

                                    if(window.hWin.HEURIST4.util.isFunction(that.options.aftermenuselect)){
                                        that.options.aftermenuselect( document, data.page_id );
                                        /*setTimeout(function(){
                                        that.options.aftermenuselect( data.page_id );
                                        },2000);*/
                                    }
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr({
                                        message: `Web Page not found (record #${data.page_id})`,
                                        error_title: 'Failed to load page'
                                    });
                                }
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        });                
                

                    /*
                    $(page_target).empty().load(page_url,
                        function(){

                            if(page_footer.length>0){
                                page_footer.appendTo( $(page_target) );
                                $(page_target).css({'min-height':$(page_target).parent().height()-page_footer.height()-10 });
                            } 
                            

                            layoutMgr.layoutInit( null, $(page_target) );
                            
                           
                            
                            if(window.hWin.HEURIST4.util.isFunction(that.options.aftermenuselect)){
                                that.options.aftermenuselect( document, data.page_id );
                            }
                    });*/
                };

                //before load we trigger  function

                let event_assigned = false;

                $.each($._data( $( page_target )[0], "events"), function(eventname, event) {
                    if(eventname=='onexitpage'){
                        event_assigned = true;
                        return false;
                    }
                });                        

                if(event_assigned){
                    $( page_target ).trigger( "onexitpage", continue_load_page );
                }else{
                    continue_load_page();
                }
            }                
        }
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

   },

   //
   // custom, widget-specific, cleanup.
   _destroy: function() {
        if(this.divMainMenu) this.divMainMenu.remove();
   },
    
   getFirstPageWithContent: function(){
        return this.first_not_empty_page_id;
   }
    
});
