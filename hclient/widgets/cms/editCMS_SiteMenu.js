/**
* editCMS_SiteMenu.js - website structure/menu configuration
* for widgets it uses editCMS_WidgetCfg.js 
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

/* global page_cache */
//
//
//
function editCMS_SiteMenu( $container, editCMS2 ){

    const _className = 'editCMS_SiteMenu';
    
    const RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'],
        DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
        DT_EXTENDED_DESCRIPTION = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'],
        DT_CMS_TOP_MENU = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TOP_MENU'],
        DT_CMS_MENU  = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_MENU'],
        DT_CMS_PAGETITLE   = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETITLE'],
        DT_CMS_PAGETYPE   = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETYPE'];

    function _init(){

        /*not used as dialog 
        var buttons= [
            {text:window.hWin.HR('Cancel'), 
                class:'btnCancel',
                css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
                click: function() { 
                    $dlg.dialog( "close" );
            }},
            {text:window.hWin.HR('Apply'), 
                class:'ui-button-action btnDoAction',
                //disabled:'disabled',
                css:{'float':'right'}, 
                click: function() { 
                        var config = _getValues();
                        main_callback.call(this, config);
                        $dlg.dialog( "close" );    
        }}];
        */
        
        /*
        $container.empty().load(window.hWin.HAPI4.baseURL
                +'hclient/widgets/cms/editCMS_ElementCfg.html',
                _initControls
            );
        */
        
        _initControls();
    }
    
    //
    // Init/reload treeview
    //
    function _initControls(){

        let tree_element = $container;        
        
        //get treedata from main menu
        let treedata = $('#main-menu > div[widgetid="heurist_Navigation"]').navigation('getMenuContent','treeview');
        
        //add node for home page
/*
0:
children: (3) [{…}, {…}, {…}]
expanded: false
has_access: true
key: "3"
page_id: "3"
page_showtitle: 1
page_target: null
parent_id: 7
title: "Overview"
*/
        if(tree_element.fancytree('instance')){
            
            let tree = $.ui.fancytree.getTree( tree_element );

            //keep_expanded_nodes
            let keep_expanded_nodes = [];
            tree.visit(function(node){
                    if(node.isExpanded()){
                        keep_expanded_nodes.push(node.key)
                    }});
            
            tree.reload(treedata);
            
            tree.visit(function(node){
                    if(keep_expanded_nodes.indexOf(node.key)>=0){
                        node.setExpanded(true);
                    }
                    node.setSelected((node.data.page_id==window.hWin.current_page_id));
            });

        }else{

            let fancytree_options =
            {
                checkbox: false,
                //titlesTabbable: false,     // Add all node titles to TAB chain
                source: treedata,
                quicksearch: false, //true,
                selectMode: 1, //1:single, 2:multi, 3:multi-hier (default: 2)
                renderNode: function(event, data) {
                    
                        let item = data.node;
                        _defineActionIcons( item );
                    
                },
                extensions:["edit", "dnd"],
                dnd:{
                    preventVoidMoves: true,
                    preventRecursiveMoves: true,
                    autoExpandMS: 400,
                    dragStart: function(node, data) {
                        return data.has_access;
                    },
                    dragEnter: function(node, data) {
                        //data.otherNode - dragging node
                        //node - target node
                        return true;
                    },
                    dragDrop: function(node, data) {
                        //data.otherNode - dragging node
                        //node - target node
                        let source_parent = data.otherNode.parent.data.page_id;
                        if(!(source_parent>0))
                            source_parent = window.hWin.home_page_record_id;

                        data.otherNode.moveTo(node, data.hitMode);

                        let target_parent = data.otherNode.parent.data.page_id;
                        if(!(target_parent>0))
                            target_parent = window.hWin.home_page_record_id;
                        data.otherNode.data.parent_id = target_parent;

                        let request = {actions:[]};
                        if(source_parent!=target_parent){
                            //remove from source
                            request.actions.push(
                                {a: 'delete',
                                    recIDs: source_parent,
                                    dtyID: source_parent==window.hWin.home_page_record_id?DT_CMS_TOP_MENU:DT_CMS_MENU,
                                    sVal:data.otherNode.data.page_id}); 

                        }
                       
                        //change order in target
                        
                        //at first - remove all current children
                        request.actions.push(
                            {a: 'delete',
                                recIDs: target_parent,
                                dtyID: target_parent==window.hWin.home_page_record_id?DT_CMS_TOP_MENU:DT_CMS_MENU});

                        //add children in new order        
                        for (let i=0; i<data.otherNode.parent.children.length; i++){

                            let menu_node = data.otherNode.parent.children[i];
                            request.actions.push(
                                {a: 'add',
                                    recIDs: target_parent,
                                    dtyID: target_parent==window.hWin.home_page_record_id?DT_CMS_TOP_MENU:DT_CMS_MENU,
                                    val:menu_node.data.page_id}                                                   
                            );
                        }                    

                        //window.hWin.HEURIST4.msg.bringCoverallToFront(edit_dialog.parents('.ui-dialog')); 
                        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                            
                            if(response.status == window.hWin.ResponseStatus.OK){
                                window.hWin.HEURIST4.msg.showMsgFlash('saved');
                                //reload main menu
                                _refreshMainMenu( false ); //after DnD
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        });             

                    }
                },
                activate: function(event, data) { 
                    //loads another page
                    let page_id = data.node.data.page_id;

                    if(page_id>0){
                        _refreshCurrentPage(page_id);
                        data.node.setActive( false );

                        editCMS2.switchMode('page');

                        $('#main-menu > div[widgetid="heurist_Navigation"]').navigation('highlightTopItem', data.node.key);
                    }
                },
                edit:{
                    triggerStart: ["clickActive", "dblclick", "f2", "mac+enter", "shift+click"],
                    close: function(event, data){
                        // Editor was removed
                        if( data.save ) {
                            // Since we started an async request, mark the node as preliminary
                            $(data.node.span).addClass("pending");
                        }
                    },                                    
                    save:function(event, data){
                        if(''!=data.input.val()){
                            let new_name = data.input.val();
                            _renameMenuEntry(data.node.data.page_id, new_name, function(){
                                
                            });
                        }else{
                            $(data.node.span).removeClass("pending");    
                        }
                    }
                }
            };

            tree_element.fancytree(fancytree_options).addClass('tree-cms');
            
            let tree = $.ui.fancytree.getTree( tree_element );
            tree.visit(function(node){
                node.setExpanded(true);
            });            
            
        }        
        
        setTimeout(_highlightCurrentPage, 1000);
    }  
   
    //
    //
    //
    function _defineActionIcons(item)
    {
        
        let tree_element = $container;
        
        let item_li = $(item.li), 
        menu_id = item.data.page_id,

        is_top = (item.data.parent_id==window.hWin.home_page_record_id);

        if($(item).find('.svs-contextmenu3').length==0){

            let parent_span = item_li.children('span.fancytree-node');

            //add,edit menu,edit page,remove
            let actionspan = $('<div class="svs-contextmenu3" style="padding: 0px 20px 0px 0px;" data-parentid="'
                +item.data.parent_id+'" data-menuid="'+menu_id+'">'
                //since 12-05 +'<span class="ui-icon ui-icon-structure" title="Edit page"></span>'
                +'<span class="ui-icon ui-icon-plus" title="Add new page/menu item"></span>'
                +'<span class="ui-icon ui-icon-pencil" title="Edit menu record"></span>'
                //+'<span class="ui-icon ui-icon-document" title="Edit page record"></span>'
                +'<span class="ui-icon ui-icon-trash" '
                +'" title="Remove menu entry from website"></span>'
                +'</div>').appendTo(parent_span);

            $('<div class="svs-contextmenu4"></div>').appendTo(parent_span); //progress icon

            actionspan.find('.ui-icon').on('click', function(event){
                let ele = $(event.target);
                window.hWin.HEURIST4.util.stopEvent(event);
                
                let parent_span = ele.parents('span.fancytree-node');
                
                function __in_progress(){
                    parent_span.find('.svs-contextmenu4').show();
                    parent_span.find('.svs-contextmenu3').hide();
                }

                //timeout need to activate current node    
                setTimeout(function(){                         
                    let ele2 = ele.parents('.svs-contextmenu3');
                    let menuid = ele2.attr('data-menuid');
                    let parent_id = ele2.attr('data-parentid');

                    if(ele.hasClass('ui-icon-plus')){ //add new menu to 

                        _selectMenuRecord(menuid);

                    }else if(ele.hasClass('ui-icon-pencil')){ //edit menu record

                        function __editPageRecord(record_id){
                            __in_progress();
                            //edit menu item
                            window.hWin.HEURIST4.ui.openRecordEdit(record_id, null,
                                {selectOnSave:true,
                                    onClose: function(){ 
                                        parent_span.find('.svs-contextmenu4').hide();
                                    },
                                    onselect:function(event, data){
                                        if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                            
                                            let recordset = data.selection;
                                            let page_id = recordset.getOrder()[0];
                                            page_cache[page_id] = null; //remove from cache
                                            delete page_cache[page_id];
                                            
                                            if(page_id == window.hWin.current_page_id){
                                                _refreshCurrentPage(window.hWin.current_page_id);
                                            }

                                            // Update website tree and in site menu
                                            let new_name = recordset.fld(recordset.getFirstRecord(), DT_NAME);
                                            let refresh_menus = false;

                                            if(page_cache[page_id]) page_cache[page_id][DT_NAME] = new_name;

                                            // Update tree nodes
                                            $.ui.fancytree.getTree( $container ).visit((node) => {

                                                let old_name = node.title;
                                                if(node.data.page_id == page_id){

                                                    if(old_name == new_name){ return false; } // name wasn't updated

                                                    node.setTitle( new_name );
                                                    _defineActionIcons(node);
                                                    refresh_menus = true;
                                                }

                                            });

                                            if(refresh_menus){ _refreshMainMenu(false); } // update menu
                                        }
                                    }
                                }
                            );
                        }
                        
                        if( (menuid == window.hWin.current_page_id)
                            && editCMS2.warningOnExit(function(){ __editPageRecord(menuid) }))
                        {                                    
                                return;
                        }else{
                                __editPageRecord(menuid);
                        }

                    }
                    else if(ele.hasClass('ui-icon-structure')){ //not used - now any click on tree opens edit page

                        editCMS2.switchMode('page');
                        //open page structure 
                        if( menuid != window.hWin.current_page_id ){
                            _refreshCurrentPage( menuid );
                        }

                    }else 
                        if(ele.hasClass('ui-icon-trash')){    //remove menu entry

                            function __doRemove(){
                                let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                                let isDelete = $dlg.find('#del_menu').is(':checked');
                                $dlg.dialog( "close" );

                                let to_del = [];
                                item.visit(function(node){
                                    to_del.push(node.data.page_id);
                                },true);

                                if(!isDelete){ // Check if the menu and related records are to be deleted, or just removed
                                    to_del = null;
                                }
                                
                                editCMS2.resetModified();
                                
                                _removeMenuEntry(parent_id, menuid, to_del, function(){
                                    item.remove();    
                                    
                                    //after deletion select home page
                                    window.hWin.current_page_id = window.hWin.home_page_record_id;
                                    _refreshMainMenu( false ); //after delete
                                });
                            }

                            let menu_title = ele.parents('.fancytree-node').find('.fancytree-title')[0].innerText; // Get menu title
                            
                            let buttons = {};
                            buttons[window.hWin.HR('Remove menu entry and sub-menus (if any)')]  = function() {
                                __doRemove();
                            };
                            buttons[window.hWin.HR('Cancel')]  = function() {
                                let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
                                $dlg.dialog( "close" );
                            };

                            window.hWin.HEURIST4.msg.showMsgDlg(
                                'This removes the menu entry from the website, as well as all sub-menus of this menu (if any).<br><br>'
                                + 'To avoid removing sub-menus, move them out of this menu before removing it.<br><br>'
                                + '<input type="checkbox" id="del_menu">'
                                + '<label for="del_menu" style="display: inline-flex;">If you want to delete the actual web pages from the database, not simply remove<br>'
                                + 'the menu entreis from this website, check this box. Note that this is not reversible.</label>', buttons,
                                'Remove "'+ menu_title +'" Menu');

                        }

                },500);
            });

            //hide icons on mouse exit
            function _onmouseexit(event){
                let node;
                if($(event.target).is('li')){
                    node = $(event.target).find('.fancytree-node');
                }else if($(event.target).hasClass('fancytree-node')){
                    node =  $(event.target);
                }else{
                    //hide icon for parent 
                    node = $(event.target).parents('.fancytree-node');
                    if(node) node = $(node[0]);
                }
                let ele = node.find('.svs-contextmenu3');
                ele.hide();
            }               

            function _onmouseenter(event){
                let node;
                if($(event.target).hasClass('fancytree-node')){
                    node =  $(event.target);
                }else{
                    node = $(event.target).parents('.fancytree-node');
                }
                if(! ($(node).hasClass('fancytree-loading') || $(node).find('.svs-contextmenu4').is(':visible')) ){
                    let ele = $(node).find('.svs-contextmenu3');
                    ele.css({'display':'inline-block'});
                }
            }

            $(parent_span).on('mouseenter',
                _onmouseenter
            ).on('mouseleave',
                _onmouseexit
            );                                                  
            
        }
    } //end _defineActionIcons

    
    //
    //
    //
    function _renameMenuEntry(rec_id, newvalue, callback){

        let request = {a: 'replace',
            recIDs: rec_id,
            dtyID:  DT_NAME,
            rVal:    newvalue};

        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
                //refresh treeview
                if($container.fancytree('instance')){                                 
                    let node = $.ui.fancytree.getTree( $container ).getNodeByKey(''+rec_id);
                    if(node){
                        $(node.span).removeClass("pending");
                        node.setTitle( newvalue ); 
                        _defineActionIcons( node );   
                    }
                }                                
                if(page_cache[rec_id]) page_cache[rec_id][DT_NAME] = newvalue;
                _refreshMainMenu( false ); //after Rename   
                
                
                if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call();
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });                                        

    }
    
    //
    //
    //
    function _highlightCurrentPage(){
        
        if(window.hWin.current_page_id==window.hWin.home_page_record_id){
            $('.btn-website-homepage').css({'text-decoration':'underline'});
        }else
        if( $container.fancytree('instance')){
                let tree = $.ui.fancytree.getTree( $container );
                
                $('.btn-website-homepage').css({'text-decoration':'none'});
                
                tree.visit(function(node){
                    if(node.data.page_id==window.hWin.current_page_id){
                        $(node.li).find('.fancytree-title').css({'text-decoration':'underline'});    
                    }else{
                        $(node.li).find('.fancytree-title').css({'text-decoration':'none'});
                    }
                });            
        }
    }

    //
    // Create new cms menu record 
    //
    function _createMenuRecord(parent_id, page_name, template_name, callback, $dlg_element){
        
        let details = {};
        details['t:'+DT_NAME] = [ page_name ];
        details['t:'+DT_CMS_PAGETYPE] = [ window.hWin.HAPI4.sysinfo['dbconst']['TRM_PAGETYPE_MENUITEM'] ];
        if(DT_CMS_PAGETITLE>0 && window.hWin.HAPI4.sysinfo['dbconst']['TRM_NO']){
            details['t:'+DT_CMS_PAGETITLE] = [ window.hWin.HAPI4.sysinfo['dbconst']['TRM_NO'] ];
        }

        let sURL = window.hWin.HAPI4.baseURL+'hclient/widgets/cms/templates/snippets/'+template_name+'.json';
        $.getJSON(sURL, 
        function( template_json ){

            if($dlg_element && $dlg_element.dialog('instance') !== undefined){
                $dlg_element.dialog( "close" );
            }
            if(!window.hWin.HEURIST4.util.isFunction(callback)){
                callback = function(new_page_id){
                    window.hWin.current_page_id = new_page_id;
                    _refreshMainMenu(); //after addition of new page
                };
            }

            function ___continue_addition(tmp_json){
                
                details['t:'+DT_EXTENDED_DESCRIPTION] = [ JSON.stringify(tmp_json) ];
                //add new record
                let request = {a: 'save', 
                    ID:0, //new record
                    RecTypeID: RT_CMS_MENU,
                    details: details };     

                window.hWin.HAPI4.RecordMgr.saveRecord(request, 
                    function(response){
                        let  success = (response.status == window.hWin.ResponseStatus.OK);
                        if(success){
                            let menu_id = response.data;
                            if(menu_id > 0){
                                _addMenuEntry(parent_id, menu_id, callback)
                            }
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    }
                );
            }
            
            if(template_name=='blog'){
                window.hWin.layoutMgr.prepareTemplate(template_json, ___continue_addition);
            }else{
                ___continue_addition(template_json)
            }
        });
    }

    //
    // define title and content only
    //
    function _defineMenuRecordSimple(parent_id, callback){
        
        let $dlg;
        
        let buttons= [
            {text:window.hWin.HR('Cancel'), 
                id:'btnCancel',
                css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
                click: function() { 
                    $dlg.dialog( "close" );
            }},
            {text:window.hWin.HR('Select'), 
                id:'btnSelect',
                css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
                click: function() { 
                    $dlg.dialog( "close" );
                    _defineMenuRecord(parent_id, callback);
            }},
            {text:window.hWin.HR('Insert'), 
                id:'btnDoAction',
                class:'ui-button-action',
                disabled:'disabled',
                css:{'float':'right'}, 
                click: function() { 
                    _createMenuRecord(parent_id, $dlg.find('#pageName').val(), $dlg.find('#pageContent').val(), callback, $('#cms-add-widget-popup'));
                }
            }
        ];
    
        $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
            +"hclient/widgets/cms/editCMS_AddPage.html?t="+(new Date().getTime()), 
            buttons, window.hWin.HR('Define new web page'), 
            {  container:'cms-add-widget-popup',
                default_palette_class: 'ui-heurist-publish',
                width: 600,
                height: 250
                , close: function(){
                    $dlg.dialog('destroy');       
                    $dlg.remove();
                }
                , open: function(){
                    $dlg.find('#pageName').on('keyup', function(e){
                        window.hWin.HEURIST4.util.setDisabled($dlg.parent().find('.btnDoAction'), $(e.taget).val()=='');
                    } );
                }
        });
        
    }
    
    //
    // select among existing or define new record in full edit form
    //
    function _defineMenuRecord(parent_id, callback)
    {
        let popup_options = {
            select_mode: 'select_single', //select_multi
            select_return_mode: 'recordset',
            edit_mode: 'popup',
            selectOnSave: true, //it means that select popup will be closed after add/edit is completed
            title: window.hWin.HR('Select or create a website menu record'),
            rectype_set: RT_CMS_MENU,
            parententity: 0,
            default_palette_class: 'ui-heurist-publish',
            onselect:function(event, data){
                if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                    let recordset = data.selection;
                    let record = recordset.getFirstRecord();
                    let menu_id = recordset.fld(record,'rec_ID');

                    _addMenuEntry(parent_id, menu_id, callback)
                }
            }
        };//popup_options


        let usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_records', 
            {width: null,  //null triggers default width within particular widget
                height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });

        popup_options.width = Math.max(usrPreferences.width,710);
        popup_options.height = usrPreferences.height;

        window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);
    }

    
    
    //
    // Select or create new menu item for website
    //
    // Opens record selector popup and adds selected menu given mapdoc or other menu
    //
    function _selectMenuRecord(parent_id, callback){
        
        if(editCMS2.warningOnExit(function(){ _selectMenuRecord(parent_id, callback) })) return;
        
        if(!callback){
                callback = function(new_page_id){
                        window.hWin.current_page_id = new_page_id;
                        _refreshMainMenu(); //after addition of new page
                };
        }

        _defineMenuRecordSimple(parent_id, callback);
        
    }
        
    //
    // Add new menu(page) menu_id to  parent_id
    //
    function _addMenuEntry(parent_id, menu_id, callback){

        let request = {a: 'add',
            recIDs: parent_id,
            dtyID:  (parent_id==window.hWin.home_page_record_id)?DT_CMS_TOP_MENU:DT_CMS_MENU,
            val:    menu_id};

        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
                //refresh treeview
                if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call( this, menu_id );
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });                                        

    }

    //
    // Update database
    //
    function _removeMenuEntry(parent_id, menu_id, records_to_del, callback){

        //delete detail from parent menu
        let request = {a: 'delete',
            recIDs: parent_id,
            dtyID:  (parent_id==window.hWin.home_page_record_id)?DT_CMS_TOP_MENU:DT_CMS_MENU,
            sVal:   menu_id};

        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
                if(records_to_del && records_to_del.length>0){

                    //delete children 
                    window.hWin.HAPI4.RecordMgr.remove({ids:records_to_del},
                        function(response){
                            if(response.status == window.hWin.ResponseStatus.OK){
                                //refresh treeview
                                if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call();
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        }      
                    );

                }else{
                    //refresh treeview
                    if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call();
                }
            }else{                                                     
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });                                        

    }
    
    //
    // refresh main menu and reload current page
    //
    function _refreshMainMenu( need_refresh_tree ){
        
        //call global function from websiteScriptAndStyles
        window.hWin.initMainMenu( function(){
            if(need_refresh_tree!==false){
                _initControls();
            }
            _refreshCurrentPage();
        });  
    }

    
    //
    // reload current (or given page)
    //
    function _refreshCurrentPage(page_id){
        
        if(!(page_id>0)) page_id = window.hWin.current_page_id;
        
        //call global function from websiteScriptAndStyles
        window.hWin.loadPageContent(page_id); 
    
    }

    //
    // reload entire website 
    //
    function _refreshWebsite(){
        window.hWin.location.reload();
    }

    //
    // Get parent page id for provided page id
    //
    function _getParentPage(page_id){

        if(window.hWin.HEURIST4.util.isempty(page_id) || page_id <= 0 || window.hWin.home_page_record_id == page_id){
            return page_id;
        }

        let tree = $.ui.fancytree.getTree( $container );
        let page_node = tree.getNodeByKey(''+page_id);
        let parent_id = window.hWin.home_page_record_id;

        if(page_node == null){
            tree.visit((node) => {
                if(node.data.page_id == page_id){
                    parent_id = node.data.parent_id; //node.parent.data.page_id
                    return false;
                }
            });
        }else{
            parent_id = page_node.data.parent_id; //page_node.parent.data.page_id
        }

        return parent_id;
    }
        

    //public members
    let that = {

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        },
        
        highlightCurrentPage: function(){
            _highlightCurrentPage();
        },
        
        // add new menu
        selectMenuRecord: function(parent_id, callback){
            _selectMenuRecord(parent_id, callback);
        },
        
        renameMenuEntry: function (rec_id, newvalue, callback){
            _renameMenuEntry(rec_id, newvalue, callback);
        },
        
        refreshWebsite: function(){
            _refreshWebsite();
        },

        // create new menu
        createMenuRecord: function(parent_id, page_name, template_name, callback){
            _createMenuRecord(parent_id, page_name, template_name, callback);
        },

        getParentPage: function(page_id){
            return _getParentPage(page_id);
        }

    }

    _init();
    
    return that;
}



