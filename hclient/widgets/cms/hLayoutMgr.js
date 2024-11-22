/*
* hLayoutMgr.js - web page generator based on json configuration
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
/* global cfg_widgets, prepareTemplateBlog, layoutMgr */

window.layoutMgr = null;  

function hLayoutMgr(){

    const _className = "hLayoutMgr";

    let pnl_counter = 1;   //counter to assign unique key for layout elements

    let body = $(this.document).find('body');
    
    let isEditMode = false;
    
    let _supp_options = {}; //defined in layoutInit dynamic options with current status params
    
    let _main_layout_cfg = null;

    // layout.key    - unique key within page - it is assigned from scratch every time page is loaded
    // it is assigned to data-lid - attribute in layout title and data-hid for div element
    // data-lid and data-hid has the same value and used to link elements in cms editor and cms structure treeview
    //
    // layout.dom_id - unique id within DOM (it is generated automatically on first creation as
    //                 combination cms-content-KEY or cms-widget-KEY and can be changed 
    //                 by user (website designer) manually
    //
    // for cardinal, tabs and accordion it assign cms-tabs-{layout.key}            
    //
    // Assigns unique key property for all layer elements 
    // (in json array "key" in html "data-lid" and "data-hid")
    //
    function _layoutInitKey(layout, i){
        
        if(!layout[i].key){
            layout[i].key = pnl_counter;
            //data-lid is required to find title in treeveiew
            layout[i].title = '<span data-lid="'+pnl_counter+'">' + layout[i].name 
                                +'</span>';
            layout[i].folder = (layout[i].children && layout[i].children.length>0);
        
            pnl_counter++;
        }
    }
    
    //---------------------------------------
    // 
    //
    //
    // layout - JSON config or HTML string
    // container - id or element
    // forStorage - if true do not init widgets and store options as a content for div 
    //
    function _layoutInitFromJSON(layout, container, forStorage, isFirstLevel){
        
        if(container==null){
            container = document.createElement('div');
        }
        
        container = $(container);
        
        if(layout==null){
            //take layout from container
            layout = container.text();
        }
        
        container.empty();
        
        const res = window.hWin.HEURIST4.util.isJSON(layout);
        if(res===false){
            //this is not json - HTML
            
            if(forStorage){
                //do not init and returns html
                return layout; //returns html 
            }else if(typeof layout === 'string' && layout.indexOf('data-heurist-app-id')>0){
                //old format with some widgets
                container.html(layout);
                //'#main-content'
                window.hWin.HAPI4.LayoutMgr.appInitFromContainer( null, container, _supp_options );
                return false;
            }
            
            layout = [{name:'Page', type:'group',
                    children:[
                        {name:'Content', type:'text', css:{}, content: layout}
                    ] 
                }]; 
        }else{
            layout = res;    
        }
        
        if(!Array.isArray(layout)){
            layout = [layout];    
        }

        if(isFirstLevel===true){
            
            if(_supp_options.page_name){
                layout[0].name  = 'Page';
            }
            if(_supp_options.keep_top_config && isEditMode){
                _main_layout_cfg = layout;
            }
        }
        
        
        for(let i=0; i<layout.length; i++){
            
            _layoutInitKey(layout, i);
            
            let ele = layout[i];
            
            if(ele.type=='cardinal'){
                
                _layoutInitCardinal(ele, container, forStorage);
                
            }else if(ele.type=='tabs'){
                
                _layoutInitTabs(ele, container, forStorage);
                
            }else if(ele.type=='accordion'){
             
                _layoutInitAccordion(ele, container, forStorage);
                
            }else if(ele.children && ele.children.length>0){ //free, flex or group
                
                _layoutInitGroup(ele, container, forStorage);
                
            }else if( (ele.type && ele.type.indexOf('text')==0) || ele.content){
                //text elements
                _layoutInitText(ele, container, forStorage);
                
            }else if(ele.type=='widget' || ele.appid){
                //widget element
                
                _layoutAddWidget(ele, container, forStorage);
                
            }
        }//for
        
        if(forStorage){
            return container.html();
        }else{
            //remove all javascript event attributes
            if(isFirstLevel && _supp_options && !_supp_options.heurist_isJsAllowed){
                
                _layoutSanitize( container );
                
            }
            
            return layout;    
        }
        
    }//_layoutInitFromJSON
    
    //
    // Removes all javascript event attributes
    //
    function _layoutSanitize( container ){
        
        $.each(container.children(), function(idx, ele){
            ele = $(ele);
            _layoutSanitize( ele );
        });
        
        let ele2 = container.get(0);
        
        for (let i = 0; i < ele2.attributes.length; i++) {
            if(ele2.attributes[i].name.indexOf('on')===0){
                ele2.removeAttribute(ele2.attributes[i].name);
            }
        }        
        
    }
    
    
    //
    // creates new div
    //
    function _layoutCreateDiv( layout, classes, forStorage ){

        if(layout.dom_id && layout.dom_id.indexOf('cms-tabs-')===0){
            //assign unique identificator (for cardinal, tabs, accordion)
            //id is reassigned on every page reload
            layout.dom_id = 'cms-tabs-' + layout.key;  
        }
        
        let $d; //result
        
        if(forStorage){
            //attributes
            // key - unique id withing edit session - it is assigned every time layout recreated in edit mode   
            // dom_id - unique html id                                                                          
            // name - dats-cms-name
            // type - data-cms-type
            // css - css  
            // classes - classes
            
            $d = $(`<div id="${layout.dom_id}" data-cms-name="${layout.name}" data-cms-type="${layout.type}"></div>`);
        }else{

        $d = $(document.createElement('div'));
        
        if(!layout.dom_id){
        
        
            if(layout.appid && _main_layout_cfg!=null){
                //assign search_realm and map_widget_id
                let widget_name = layout.appid;
                if(!layout.options) layout.options = {};
                //find map widget on this page
                if(widget_name=='heurist_StoryMap'){
                    if(!layout.options.map_widget_id){
                        let ele = layoutMgr.layoutContentFindWidget(_main_layout_cfg, 'heurist_Map');

                        if(ele && ele.dom_id){                        
                            window.hWin.HEURIST4.msg.showMsgDlg('For full fnctionality Story map has to be linked with Map/timeline element. '
                            +' Connect it to map widget "'+ele.dom_id+'" ?', 
                            function(){ 
                                ele.options.search_realm = '';
                                layout.options.map_widget_id = ele.dom_id;
                            },null,{default_palette_class: 'ui-heurist-publish'});
                        }
                    }
                }
                
                //find and assign prevail search group (except heurist_Map if heurist_StoryMap exists)
                if(!layout.options.search_realm){ //not defined yet
                
                    let need_assign = true;
                    
                    if(widget_name=='heurist_Map')
                    {
                        let ele = layoutMgr.layoutContentFindWidget(_main_layout_cfg, 'heurist_StoryMap');
                        if(ele && !ele.options.map_widget_id != layout.dom_id){

                            need_assign = false;        
                            window.hWin.HEURIST4.msg.showMsgDlg('For full fnctionality Story map has to be linked with Map/timeline element. '
                                +' Link map widget to Story map "'+ele.dom_id+'" ?', 
                                function(){ 
                                    layout.options.search_realm = '';
                                    ele.options.map_widget_id = layout.dom_id;
                            },null,{default_palette_class: 'ui-heurist-publish'});
                        }
                        
                    }
                    //assign search realm
                    if(need_assign){
                        let sg = layoutMgr.layoutContentFindMainRealm(_main_layout_cfg);    
                        if(sg=='') sg = 'search_group_1';
                        layout.options.search_realm = sg;
                    }
                }
            }
         
            // assign id for new content and widgety divs
            // it is saved in configuration
            let uid = ''+window.hWin.HEURIST4.util.random();

            do{
                if(layout.appid){
                    layout.dom_id = 'cms-widget-' + uid;
                }else{
                    layout.dom_id = 'cms-content-' + uid;
                }
            }while (body.find('#'+layout.dom_id).length>0);
                  
        }                         
        
        $d.attr('id', layout.dom_id)
          .attr('data-hid', layout.key);
          
        if(classes){
            $d.addClass(classes);
        } 

        }
        if(layout.classes){ //custom classes
            $d.addClass(layout.classes);
        }
        
        return $d;        
    }

    //
    //
    //
    function _layoutInitGroup(layout, container, forStorage){
        
        //create parent div
        let $d = _layoutCreateDiv(layout, 'cms-element brick', forStorage);
        
        $d.appendTo(container);
                
        if(isEditMode){
            //$d.css({'border':'2px dotted gray','border-radius':'4px','margin':'4px'});  
        }
        
        if(!layout.css) layout.css = {};
        if($.isEmptyObject(layout.css)){ //default
           
        }

        if(layout.css && !$.isEmptyObject(layout.css)){
            $d.css(layout.css);
        }
        
        _layoutInitFromJSON(layout.children, $d, forStorage);
        
    }
    
    //
    // layout - JSON config
    // container - parent element
    // 
    function _layoutInitText(layout, container, forStorage){
        
        let $d = _layoutCreateDiv(layout, 'editable tinymce-body cms-element brick', forStorage);

        $d.appendTo(container);
            
        if(!layout.css) layout.css = {};
        if($.isEmptyObject(layout.css)){ //default
           
        }
            
        if(layout.css && !$.isEmptyObject(layout)){
            $d.css( layout.css );    
        }
        
        let content = 'content'; //default name of attribute
        
        if(forStorage){
            //keep content for all languages
            let aLangs = [];
            Object.keys(layout).forEach(key => {
                if(key.indexOf('content')===0){
                    aLangs.push(key);
                }
            });
            
            if(aLangs.length>1){
                aLangs.forEach((lang) => {
                    let lang_code = lang.substring(7);
                    if(!lang_code) lang_code = 'def';
                    $(`<div css="${lang_code=='def'?'':'display:none'}" data-lang="${lang_code}">${layout[lang]}</div>`).appendTo($d);    
                });
            }else{
               $d.html(layout[aLangs[0]]);    
            }                                
            
        }else{
        
            if(_supp_options['lang']){ //current language
                let lang = window.hWin.HAPI4.getLangCode3(_supp_options['lang'], 'def'); //returns 'def' if not found
                if(layout[content+lang]){ //if not found use the default
                    content = content+lang;
                }
                $d.attr('data-lang', lang);
            }

            $d.html(layout[content]);
            
        }
        
    }
    
    //
    // layout - json configuration
    // container - if not defined - it tries to find current one
    //
    function _layoutAddWidget(layout, container, forStorage){

        let $d = _layoutCreateDiv(layout, 'editable heurist-widget cms-element brick');

        //remove previous one
        let old_widget = container.find('div[data-hid='+layout.key+']');
        if(old_widget.length>0){
            $d.insertBefore(old_widget);
            old_widget.remove();
        }else{
            $d.appendTo(container);    
        }
        
        
        if(!layout.css){
            layout.css  = {};    
            layout.css['minHeight'] = '100px';
           
        } 
        if(!layout.css['position']) layout.css['position'] = 'relative';
        
        //default values for various widgets
        /*
        if(layout.appid=='heurist_Map' ||  layout.appid=='heurist_SearchTree' || 
           layout.appid=='heurist_resultList' || layout.appid=='heurist_resultListExt'){
        }
        
        if(layout.appid=='heurist_Search'){
            if(layout.css['display']!='flex'){
               
            }
            if(!layout.css['width']){
               
            }
        }else if(layout.appid=='heurist_Map'){
            if(!layout.css['height']){
               
            }
        }*/

        
        //default min-height position depends on widget
        let app = _getWidgetById(layout.appid);
        if(app.minw>0 && !layout.css['minWidth']){
            layout.css['minWidth'] = app.minw;
        }
        if(app.minh>0 && !layout.css['minHeight']){
            layout.css['minHeight'] = app.minh;
        }

        if(layout.css && !$.isEmptyObject(layout)){
            
            $d.removeAttr('style');
            $d.css( layout.css );    
        }
        
        _layoutInitWidget(layout, container.find('div[data-hid='+layout.key+']'));

    }
    
    //
    // returns widget descrition/definitions from cfg_widgets
    // this object contains name of widget, path to js, some default options
    //
    function _getWidgetById(id){

        let i;
        for(i=0; i<cfg_widgets.length; i++){
            if(cfg_widgets[i].id==id){
                return cfg_widgets[i];
            }
        }
        return null;
    }
    
    //
    //
    //
    function _layoutInitWidget(layout, container){

        let app = _getWidgetById(layout.appid); //find in app array (appid is heurist_Search for example)

        if(!layout.options) layout.options = {};
        
        if(layout.appid=='heurist_Map'){
            layout.options['leaflet'] = true;
            layout.options['init_at_once'] = true;
        }
        
        if(_supp_options[layout.appid]){
            layout.options = $.extend(layout.options, _supp_options[layout.appid]);        
            
            if(layout.appid=='heurist_Navigation'){
                //keep supp_options separately for Navigation - since they are required for page init 
                layout.options['supp_options'] = _supp_options;
            }
        }
        
        if(_supp_options['lang']){
            // xx - means it will use current language
            layout.options['language'] = window.hWin.HAPI4.getLangCode3(_supp_options['lang'],'def');    
        }
        
        if (app && app.script && app.widgetname) { //widgetname - function name to init widget

            if(window.hWin.HEURIST4.util.isFunction($('body')[app.widgetname])){ //OK! widget script js has been loaded            

                container[app.widgetname]( layout.options );   //call function
                
                container.attr('data-widgetname',app.widgetname);

            }else{

                $.getScript( window.hWin.HAPI4.baseURL + app.script, function() {  //+'?t='+(new Date().getTime())
                    if(window.hWin.HEURIST4.util.isFunction(container[app.widgetname])){
                        container[app.widgetname]( layout.options );   //call function
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: `Widget ${app.widgetname} not loaded. Verify your configuration`,
                            error_title: 'Widget loading failed',
                            status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                        });
                    }
                });

            }

        }
        

    }

    //
    // groups of containers    
    //
    function _layoutInitCardinal(layout, container, forStorage){
        
        let $d, $parent;
        
        layout.dom_id = 'cms-tabs-'+layout.key;
        
        if(container.attr('id')==layout.dom_id){
            $d = container;    
        }else{
            $d = container.find('#'+layout.dom_id);
        }
        
        if($d.length>0){
            container = $d.parent();            
            $d.remove(); //remove itself
        }
        
        //create parent div
        $parent = _layoutCreateDiv(layout, '', forStorage);
        
        if( layout.css && !$.isEmptyObject(layout.css) ){
            $parent.css( layout.css );
        }
        
        $parent.appendTo(container);
        
        
        let layout_opts = {applyDefaultStyles: true, maskContents: true};
    
        for(let i=0; i<layout.children.length; i++){
            
            _layoutInitKey(layout.children, i);
            
            let lpane = layout.children[i];
            let pos = lpane.type;
            
            let opts = lpane.options;
            if(!opts) opts = {};
            
            if(!$.isEmptyObject(opts)){
            
                if(opts.init){
                    layout_opts[pos+'__initHidden'] = (opts.init=='hidden');
                    layout_opts[pos+'__initClosed'] = (opts.init=='closed');
                }
                
                if(opts.size){
                    layout_opts[pos+'__size'] = opts.size;
                }
                if(window.hWin.HEURIST4.util.isnull(opts.resizable) || opts.resizable ){
                    if(opts.minSize){
                        layout_opts[pos+'__minSize'] = opts.minSize;
                    }
                    if(opts.maxSize){
                        layout_opts[pos+'__maxSize'] = opts.maxSize;
                    }
                    layout_opts[pos+'__resizable'] = true;
                }else{
                    layout_opts[pos+'__spacing_open'] = 0;
                    layout_opts[pos+'__resizable'] = false;
                }
            }
            
            let $d2;

            if(forStorage){
                
                $d2 = _layoutCreateDiv( layout.children[i], '', forStorage )
            
                if(!$.isEmptyObject(layout.children[i].options)){
//console.log('assign css ', layout.children[i].options);                    
                    $d2.attr('data-cms-options',JSON.stringify(layout.children[i].options));
                }
            
                $d2.appendTo($parent);
            }else{
                //create cardinal div
                $d = $(document.createElement('div'));
            
                $d.addClass('ui-layout-'+pos)
                  .appendTo($parent);


                lpane.dom_id = 'cms-tabs-'+lpane.key;
                //@todo additional container for children>1        
                layout_opts[pos+'__contentSelector'] = '#'+lpane.dom_id;
                
                $d2 =_layoutCreateDiv(lpane, 'ui-layout-content2');  
                $d2.appendTo($d);
            }
                    
            //init                    
            _layoutInitFromJSON(layout.children[i].children, $d2, forStorage);
                    
        }//for
    
        if(!forStorage){
            $parent.layout( layout_opts );
        }
        
       
    }
    
    //
    //
    //
    function _layoutInitTabs(layout, container, forStorage){
        
        
        let $d;
        
        layout.dom_id = 'cms-tabs-'+layout.key;
        
        if(container.attr('id')==layout.dom_id){
            $d = container;    
        }else{
            $d = container.find('#'+layout.dom_id);
        }
        
        if($d.length>0){
            container = $d.parent();            
            $d.remove();
        }
        
        //create parent div
        $d = _layoutCreateDiv(layout, '', forStorage);
        
        $d.appendTo(container);
          
        if($d.parent().hasClass('layout-content')){
            $d.addClass('ent_wrapper');    
        }

        //tab panels    
        _layoutInitFromJSON(layout.children, $d, forStorage);
               
        if(!forStorage) {
            //tab header
            $d = body.find('#'+layout.dom_id);
            let groupTabHeader = $('<ul>').prependTo($d);
            
            for(let i=0; i<layout.children.length; i++){
          
                //.addClass('edit-form-tab')
                $('<li>').html('<a href="#'+layout.children[i].dom_id
                                    +'"><span style="font-weight:bold">'
                                    +layout.children[i].name+'</span></a>')
                            .appendTo(groupTabHeader);
            }
            
            $d.tabs();
        }
    }
    
    //
    //
    //
    function _layoutInitAccordion(layout, container, forStorage){
       
        let $d;
        
        layout.dom_id = 'cms-tabs-'+layout.key;
        
        if(container.attr('id')==layout.dom_id){
            $d = container;    
        }else{
            $d = container.find('#'+layout.dom_id);
        }
        
        if($d.length>0){
            container = $d.parent();            
            $d.remove();
        }
            
        //create parent div
        $d = _layoutCreateDiv(layout, '', forStorage);
        
        $d.appendTo(container);
       
        //accordion panels    
        _layoutInitFromJSON(layout.children, $d, forStorage);
        
        if(!forStorage){
       
        //accordion headers
        for(let i=0; i<layout.children.length; i++){
      
            $d = body.find('#'+layout.children[i].dom_id);
            
            $('<h3>').html( layout.children[i].name )
                     .insertBefore($d);
            
        }
        
        $d = body.find('#'+layout.dom_id);
        $d.accordion({heightStyle: "content", 
                      active:false,
                //active:(currGroupType == 'expanded')?0:false,
                      collapsible: true });
                      
        }
    }
    
    //
    // Find configuration for element in array by internal key property
    //
    function _layoutContentFindElement(content, ele_key){

        if(!Array.isArray(content)){
            if(content.children && content.children.length>0){
                return _layoutContentFindElement(content.children, ele_key);    
            }else{
                return null;
            }
        }
        
        for(let i=0; i<content.length; i++){
            if(content[i].key == ele_key){
                return  content[i];
            }else if(content[i].children && content[i].children.length>0){
                let res = _layoutContentFindElement(content[i].children, ele_key);    
                if(res) return res;
            }
        }
        return null; //not found
    }
    
    //
    // Find widget bt application/widget name in cfg_widgets sush as "heurist_SearchInput"
    //
    function _layoutContentFindWidget(content, widget_name){
        
        if(!Array.isArray(content)){
            if(content.children && content.children.length>0){
                return _layoutContentFindWidget(content.children, widget_name);    
            }else{
                return null;
            }
        }
        
        for(let i=0; i<content.length; i++){
            if(content[i].appid == widget_name){
                return  content[i];
            }else if(content[i].children && content[i].children.length>0){
                let res = _layoutContentFindWidget(content[i].children, widget_name);    
                if(res) return res;
            }
        }
        return null; //not found
    }

    //
    //
    //
    function _layoutContentFindAllWidget(content){

        let res = [];
        
        if(!Array.isArray(content)){
            if(content.children && content.children.length>0){
                let res2 =  _layoutContentFindAllWidget(content.children);    
                if(res2) res = res.concat(res2);
            }else{
                return null;
            }
        }
        
        for(let i=0; i<content.length; i++){
            if(content[i].appid){
                res.push(content[i]);
            }else if(content[i].children && content[i].children.length>0){
                let res2 = _layoutContentFindAllWidget(content[i].children);    
                if(res2) res = res.concat(res2);
            }
        }
        return res;
    }
    
    //
    //
    //
    function _layoutContentFindMainRealm(content){
        //find all widgets on page
        let res = {};
        let widgets = _layoutContentFindAllWidget(content);
        for(let i=0; i<widgets.length; i++){
            if(!widgets[i].options.search_page && widgets[i].options.search_realm){
                if(res[widgets[i].options.search_realm]>0){
                    res[widgets[i].options.search_realm]++;
                }else{
                    res[widgets[i].options.search_realm]=1;
                }
            }
        }
        //find max usage
        let max_usage = 0; 
        let max_sg = ''
        widgets = Object.keys(res);
        for(let i=0; i<widgets.length; i++){
            if(res[widgets[i]]>max_usage){
                max_usage = res[widgets[i]];
                max_sg = widgets[i];
            }
        }
        return max_sg;
    }

    //
    // Find parent element for given key
    //
    function _layoutContentFindParent(parent, ele_key){
        
        let children;
        if(Array.isArray(parent)){
            children = parent;
            parent = 'root';
        }else{
            children = parent.children;    
        }
        
        for(let i=0; i<children.length; i++){
            if(children[i].key == ele_key){
                return  parent;
            }else if(children[i].children && children[i].children.length>0){
                let res = _layoutContentFindParent(children[i], ele_key);    
                if(res) return res;
            }
        }
        return false; //not found
    }
    
    //
    // Replace element
    //    
    function _layoutContentSaveElement(content, new_cfg){
            
        let ele_key = new_cfg.key;
        
        for(let i=0; i<content.length; i++){
            if(content[i].key == ele_key){
                if(new_cfg.type && new_cfg.type.indexOf('text')==0){
                   content[i].content = new_cfg.content;
                }
                content[i] = new_cfg;
                return true 
            }else if(content[i].children && content[i].children.length>0){
                if (_layoutContentSaveElement(content[i].children, new_cfg)){
                    return true;
                }
            }
        }

        return false;            
    }
    
    //
    //
    //
    function _prepareTemplate(layout, callback){ 
       
        if(layout.template=='default'){
        
           callback.call(this, layout.children[0]); 
            
        }else if(layout.template=='blog'){
            
           let ele = _layoutContentFindWidget(layout, 'heurist_SearchTree');
           if (ele && ele.options.init_svsID=='????') {
                layout.template = null;

                try{
                
                let sURL2 = window.hWin.HAPI4.baseURL+'hclient/widgets/cms/templates/snippets/blog.js';
                // 3. Execute template script to replace template variables, adds filters and smarty templates
                    $.getScript(sURL2, function(data, textStatus, jqxhr){ //it will trigger oncomplete
                          //function in blog.js
                          prepareTemplateBlog(layout, callback);
                          
                    }).fail(function( jqxhr, settings, exception ) {
                        console.error( 'Error in template script: '+exception );
                    });
                    
                    return true;    
                    
                }catch(e){
                    alert('Error in blog template script');
                }
           }
        }
    }
        
    
    //
    //
    //
   
    function _convertOldCmsFormat(container, lvl){


        let res = [];

        $.each(container.children(), function(idx, ele){

            ele = $(ele);

            let child;

            if(ele.attr('data-heurist-app-id')){
                //this is widget
                let opts = window.hWin.HEURIST4.util.isJSON(ele.text());

                child = {appid: ele.attr('data-heurist-app-id'),
                    options: opts};

                if(opts.__widget_name){
                    child.name = opts.__widget_name.replaceAll('=','').trim();
                }
                if(!child.name) child.name = "Widget "+lvl+'.'+idx;
            }else 
                if(ele.find('div[data-heurist-app-id]').length==0){ //no widgets

                    let tag = ele[0].nodeName;
                    let s = '<' + tag + '>'+ele.html()+'</' + tag + '>';

                    child = {name:"Content "+lvl+'.'+idx, 
                        type:"text", 
                        content: s };
                }else{

                    //if(ele[0].nodeName=='TABLE'){}

                    //there are widgets among children
                    child = {name:"Group "+lvl+'.'+idx,
                        type:"group", 
                        folder:true, 
                        children:_convertOldCmsFormat(ele, lvl+1) };
                }

            if(child){
                if(ele.attr('style')){


                    let styles = ele.attr('style').split(';'),
                    i= styles.length,
                    css = {},
                    style, k, v;


                    while (i--)
                    {
                        style = styles[i].split(':');
                        k = String(style[0]).trim();
                        v = String(style[1]).trim();
                        if (k.length > 0 && v.length > 0)
                        {
                            css[k] = v;
                        }
                    }                 

                    if(!$.isEmptyObject(css)) child['css'] = css;
                }
                res.push(child);
            }
        });

        if(lvl == 0){
            res = [{name:"Name of this page",type:"group",folder:true, children:res }];
        }

        return res;
    }

    
    // 1. Save result of CMS edit as human-readble html
    // <div id="cms-content-23" data-cms-name="Page" data-cms-type="text|group|accordion|tabs|cardianl|app" css=""> content </div>
    // <div id="cms-widget-51" data-cms-name="Menu"  data-cms-type="app" css=""> options:{} </div>
    //
    // 2. Convert html to json (to edit)
    //     id=>dom_id, data-cms-name=>name, data-cms-type=>type, css=>css, folder: true if it has children, 
    //        children|options|content , appid  
    // 
    // 3. Init layout from html (as from json), if there are not accordion|tabs|cardianl|app it will be loaded "as is"
    // 4. CMS editor for header and footer
    //   a) create html content as Group+MainMenu   
    // 
    //
    function _convertHTMLtoJSON(ele, lvl){
        
        ele = $(ele);

        let res;
        
        if(ele.length>1){

            if(ele.find('[data-cms-type]').length>0 || ele.attr('data-lang') || ele.find('div[data-lang]').length>0){
                res = [];
                ele.each((i, item)=>{
                    res.push(_convertHTMLtoJSON(item, lvl));
                });
                return res;
            }else{
                return {content:ele.html()};
            }
        }
        
        
        if(!ele.attr('data-cms-type')){
            if(lvl==0){
                res = [{name:'Page', type:'group',
                        children:[
                            {name:'Content', type:'text', css:{}}
                        ] 
                    }];
            }else{
                res = {};
            }
            
            let translations = ele.children('[data-lang]');
            if(translations.length>0){
                translations.each((i,item)=>{
                    res['content'+item.getAttribute('data-lang')] = item.html();                    
                });
            }else{
                if(ele.attr('data-lang') && ele.attr('data-lang')!='def'){
                    res['content'+ele.attr('data-lang')] = ele.html();
                }else{
                    res.content = ele.html();    
                }
                
            }
             
            
        }else{
        
            res = {dom_id: ele.attr('id'), 
                   name: ele.attr('data-cms-name'),
                   type: ele.attr('data-cms-type')};
                   
            if(ele.attr('style')){
                if(res.type=='north' || res.type=='south' || res.type=='west' || res.type=='east'){
                    let cardinal_opts = window.hWin.HEURIST4.util.isJSON(ele.attr('data-cms-options'));
                    if(cardinal_opts){
                        res['options'] = cardinal_opts;        
                    }
                    
                }else{
                    res['css'] = css2json(ele.attr('style'));    
                }
            }
            if(ele.attr('class')){
                res['classes'] = ele.attr('class');
            }
                   
            if(res.type == 'app'){
                res.options = window.hWin.HEURIST4.util.isJSON(ele.text());
                res.appid = res.options.appid;
            }else{
                
                let children = ele.children('[data-cms-type]');
                if(children.length>0){
                    
                    res.children = [];
                    children.each((i,item)=>{
                        res.children.push(_convertHTMLtoJSON(item, lvl+1));                    
                    });
                    res.folder = true;    
                    
                }else{
                    //no more css layout elements 
                    if(ele.attr('data-lang') || ele.find('div[data-lang]').length>0){
                            res = $.extend(res, _convertHTMLtoJSON(ele.html(), lvl+1));
                    }else{
                            res.content = ele.html();
                    }
                }
            }
        
        }
        
        return res;
    }
    
    function css2json(css) {
        let s = {};
        if (!css) return s;
        if (css instanceof CSSStyleDeclaration) {
            for (let i in css) {
                if ((css[i]).toLowerCase) {
                    s[(css[i]).toLowerCase()] = (css[css[i]]);
                }        
            }
        } else if (typeof css == "string") {
            css = css.split("; ");
            for (let i in css) {
                let l = css[i].split(": ");
                s[l[0].toLowerCase()] = (l[1]);
            }
        }
        return s;
    }    

    //
    // Convert from JSON to human readable HTML string 
    // (without widget initialization)
    // <div id="cms-content-23" data-cms-name="Page" data-cms-type="text|group|accordion|tabs|cardianl|app" css=""> content </div>
    // <div id="cms-widget-51" data-cms-name="Menu"  data-cms-type="app" css=""> options:{} </div>
    // 
    function _convertJSONtoHTML(content){
        
        //from json
        console.log(content);
        
        //to html
        let res = _layoutInitFromJSON(content, null, true, true);
        console.log(res);
        
        //and back to json
        res = _convertHTMLtoJSON(res, 0);
        
        console.log(res);
        
        return res;
    }
    
    
    //
    //public members
    window.layoutMgr = {

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        },
        
        layoutInitTabs: function(layout, container){
            _layoutInitTabs(layout, container);    
        },
       
        layoutInitAccordion: function(layout, container){
            _layoutInitAccordion(layout, container);    
        },
        
        layoutInitCardinal: function(layout, container) {
            _layoutInitCardinal(layout, container);
        },
        
        //
        // supp_options - additional parameters that refelect current status - for example page record id, current language
        //
        layoutInit: function(layout, container, supp_options){
            
            _supp_options = supp_options?supp_options:{};
            return _layoutInitFromJSON(layout, container, false, true);
        },
        
        // 
        //
        //
        convertOldCmsFormat: function(layout, container){
            container = $(container);
            container.empty();   
            container.html(layout);
            return _convertOldCmsFormat(container, 0)
        },
        
        layoutInitKey: function(layout, i){
            _layoutInitKey(layout, i);
        },
        
        layoutAddWidget: function( layout, container ){
            _layoutAddWidget(layout, container)            
        },
        
        layoutContentFindElement: function(_layout_cfg, ele_key){
            return _layoutContentFindElement(_layout_cfg, ele_key);    
        },
        
        layoutContentFindParent: function(parent, ele_key){
            return _layoutContentFindParent(parent, ele_key);
        },

        //
        //  Find widget bt application/widget name in cfg_widgets sush as "heurist_SearchInput"
        //
        layoutContentFindWidget: function(_layout_cfg, widget_name){
            return _layoutContentFindWidget(_layout_cfg, widget_name);    
        },
        
        //
        // Find most used search realm for current layout
        //
        layoutContentFindMainRealm: function(_layout_cfg){
            return _layoutContentFindMainRealm(_layout_cfg);
        },
        
        
        //replace element in layout
        layoutContentSaveElement: function(_layout_cfg, new_cfg){
            return _layoutContentSaveElement(_layout_cfg, new_cfg);    
        },
        
        setEditMode: function(newmode){
            isEditMode = newmode;            
        },
        
        prepareTemplate: function(layout, callback){
            _prepareTemplate(layout, callback);
        },
        
        
        //
        // check that all widgets are inited completely
        //
        layoutCheckWidgets: function(){
            
            let widgets = body.find('div.heurist-widget');
            let are_all_widgets_inited = true;
            
            $.each(widgets, function(i, item){
                let widgetname = $(item).attr('data-widgetname');
                if(widgetname){
                    let is_inited = $(item)[widgetname]('instance') && $(item)[widgetname]('option', 'init_completed');
                    if(is_inited===false){
                        are_all_widgets_inited = false;
                        return false;
                    }
                }
            });        
            return are_all_widgets_inited;
        },
        
        //
        //
        //
        convertJSONtoHTML:function(content){
            return _convertJSONtoHTML(content);
        }
        
        
    }
}
   
