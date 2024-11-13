/*
* HLayoutMgr.js - web page generator based on json configuration
* 
*/

/* global cfg_widgets, prepareTemplateBlog, layoutMgr */


/*
* HLayoutMgr.js - web page generator based on JSON configuration
*/
class HLayoutMgr {
    
    pnl_counte;
    body;
    isEditMode = false;
    _supp_options = {};
    _main_layout_cfg = null;
    
    
  constructor() {

    this.pnl_counter = 1;
    this.body = $(document).find("body");
    this.isEditMode = false;
    this._supp_options = {};
    this._main_layout_cfg = null;
  }

  #layoutInitKey(layout, i) {
    if (!layout[i].key) {
      layout[i].key = this.pnl_counter;
      layout[i].title = `<span data-lid="${this.pnl_counter}">${layout[i].name}</span>`;
      layout[i].folder = layout[i].children && layout[i].children.length > 0;
      this.pnl_counter++;
    }
  }

  #layoutInitFromJSON(layout, container, forStorage, isFirstLevel) {
    if (container == null) {
      container = document.createElement("div");
    }
    container = $(container);

    if (layout == null) {
      layout = container.text();
    }

    container.empty();

    const res = window.hWin.HEURIST4.util.isJSON(layout);
    if (res === false) {
      if (forStorage) {
        return layout;
      } else if (typeof layout === "string" && layout.indexOf("data-heurist-app-id") > 0) {
        container.html(layout);
        window.hWin.HAPI4.LayoutMgr.appInitFromContainer(null, container, this._supp_options);
        return false;
      }

      layout = [
        {
          name: "Page",
          type: "group",
          children: [{ name: "Content", type: "text", css: {}, content: layout }],
        },
      ];
    } else {
      layout = res;
    }

    if (!Array.isArray(layout)) {
      layout = [layout];
    }

    if (isFirstLevel === true) {
      if (this._supp_options.page_name) {
        layout[0].name = "Page";
      }
      if (this._supp_options.keep_top_config && this.isEditMode) {
        this._main_layout_cfg = layout;
      }
    }

    for (let i = 0; i < layout.length; i++) {
      this.#layoutInitKey(layout, i);

      const ele = layout[i];
      switch (ele.type) {
        case "cardinal":
          this.#layoutInitCardinal(ele, container, forStorage);
          break;
        case "tabs":
          this.#layoutInitTabs(ele, container, forStorage);
          break;
        case "accordion":
          this.#layoutInitAccordion(ele, container, forStorage);
          break;
        default:
          if (ele.children && ele.children.length > 0) {
            this.#layoutInitGroup(ele, container, forStorage);
          } else if ((ele.type && ele.type.indexOf("text") === 0) || ele.content) {
            this.#layoutInitText(ele, container, forStorage);
          } else if (ele.type === "widget" || ele.appid) {
            this.#layoutAddWidget(ele, container, forStorage);
          }
      }
    }

    if (forStorage) {
      return container.html();
    } else {
      if (isFirstLevel && this._supp_options && !this._supp_options.heurist_isJsAllowed) {
        this.#layoutSanitize(container);
      }
      return layout;
    }
  }

  #layoutSanitize(container) {
    $.each(container.children(), (idx, ele) => {
      ele = $(ele);
      this.#layoutSanitize(ele);
    });

    const ele2 = container.get(0);
    for (let i = 0; i < ele2.attributes.length; i++) {
      if (ele2.attributes[i].name.indexOf("on") === 0) {
        ele2.removeAttribute(ele2.attributes[i].name);
      }
    }
  }

  #layoutCreateDiv(layout, classes, forStorage) {
    if (layout.dom_id && layout.dom_id.indexOf("cms-tabs-") === 0) {
      layout.dom_id = `cms-tabs-${layout.key}`;
    }

    let $d;

    if (forStorage) {
      $d = $(
        `<div id="${layout.dom_id}" data-cms-name="${layout.name}" data-cms-type="${layout.type}"></div>`
      );
    } else {
      $d = $(document.createElement("div"));

      if (!layout.dom_id) {
        let uid = "" + window.hWin.HEURIST4.util.random();

        do {
          layout.dom_id = layout.appid
            ? `cms-widget-${uid}`
            : `cms-content-${uid}`;
        } while (this.body.find(`#${layout.dom_id}`).length > 0);
      }

      $d.attr("id", layout.dom_id).attr("data-hid", layout.key);

      if (classes) {
        $d.addClass(classes);
      }
    }

    if (layout.classes) {
      $d.addClass(layout.classes);
    }

    return $d;
  }

  #layoutInitGroup(layout, container, forStorage) {
    const $d = this.#layoutCreateDiv(layout, "cms-element brick", forStorage);
    $d.appendTo(container);

    if (!layout.css) layout.css = {};
    if (layout.css && !$.isEmptyObject(layout.css)) {
      $d.css(layout.css);
    }

    this.#layoutInitFromJSON(layout.children, $d, forStorage);
  }

  #layoutInitText(layout, container, forStorage) {
    const $d = this.#layoutCreateDiv(
      layout,
      "editable tinymce-body cms-element brick",
      forStorage
    );
    $d.appendTo(container);

    if (!layout.css) layout.css = {};
    if (layout.css && !$.isEmptyObject(layout.css)) {
      $d.css(layout.css);
    }

    let content = "content";
    if (forStorage) {
      const aLangs = Object.keys(layout).filter((key) =>
        key.indexOf("content") === 0
      );

      if (aLangs.length > 1) {
        aLangs.forEach((lang) => {
          const lang_code = lang.substring(7) || "def";
          $(
            `<div css="${
              lang_code === "def" ? "" : "display:none"
            }" data-lang="${lang_code}">${layout[lang]}</div>`
          ).appendTo($d);
        });
      } else {
        $d.html(layout[aLangs[0]]);
      }
    } else {
      if (this._supp_options["lang"]) {
        const lang = window.hWin.HAPI4.getLangCode3(
          this._supp_options["lang"],
          "def"
        );
        if (layout[content + lang]) {
          content = content + lang;
        }
        $d.attr("data-lang", lang);
      }
      $d.html(layout[content]);
    }
  }
  
 //
    // layout - json configuration
    // container - if not defined - it tries to find current one
    //
 #layoutAddWidget(layout, container, forStorage){

        let $d = this.#layoutCreateDiv(layout, 'editable heurist-widget cms-element brick');

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
        let app = this.#getWidgetById(layout.appid);
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
        
        this.#layoutInitWidget(layout, container.find('div[data-hid='+layout.key+']'));

    }
    
    //
    // returns widget descrition/definitions from cfg_widgets
    // this object contains name of widget, path to js, some default options
    //
    #getWidgetById(id){

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
    #layoutInitWidget(layout, container){

        let app = this.#getWidgetById(layout.appid); //find in app array (appid is heurist_Search for example)

        if(!layout.options) layout.options = {};
        
        if(layout.appid=='heurist_Map'){
            layout.options['leaflet'] = true;
            layout.options['init_at_once'] = true;
        }
        
        if(this._supp_options[layout.appid]){
            layout.options = $.extend(layout.options, this._supp_options[layout.appid]);        
            
            if(layout.appid=='heurist_Navigation'){
                //keep supp_options separately for Navigation - since they are required for page init 
                layout.options['supp_options'] = this._supp_options;
            }
        }
        
        if(this._supp_options['lang']){
            // xx - means it will use current language
            layout.options['language'] = window.hWin.HAPI4.getLangCode3(this._supp_options['lang'],'def');    
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
  
  
  #layoutInitCardinal(layout, container, forStorage){
      
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
        $parent = this.#layoutCreateDiv(layout, '', forStorage);
        
        if( layout.css && !$.isEmptyObject(layout.css) ){
            $parent.css( layout.css );
        }
        
        $parent.appendTo(container);
        
        
        let layout_opts = {applyDefaultStyles: true, maskContents: true};
    
        for(let i=0; i<layout.children.length; i++){
            
            this.#layoutInitKey(layout.children, i);
            
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
                
                $d2 = this.#layoutCreateDiv( layout.children[i], '', forStorage )
            
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
                
                $d2 = this.#layoutCreateDiv(lpane, 'ui-layout-content2');  
                $d2.appendTo($d);
            }
                    
            //init                    
            this.#layoutInitFromJSON(layout.children[i].children, $d2, forStorage);
                    
        }//for
    
        if(!forStorage){
            $parent.layout( layout_opts );
        }
        
      
      
  }
  
   //
    //
    //
  #layoutInitTabs(layout, container, forStorage){
        
        
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
        $d = this.#layoutCreateDiv(layout, '', forStorage);
        
        $d.appendTo(container);
          
        if($d.parent().hasClass('layout-content')){
            $d.addClass('ent_wrapper');    
        }

        //tab panels    
        this.#layoutInitFromJSON(layout.children, $d, forStorage);
               
        if(!forStorage) {
            //tab header
            $d = this.body.find('#'+layout.dom_id);
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
    #layoutInitAccordion(layout, container, forStorage){
       
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
        $d = this.#layoutCreateDiv(layout, '', forStorage);
        
        $d.appendTo(container);
       
        //accordion panels    
        this.#layoutInitFromJSON(layout.children, $d, forStorage);
        
        if(!forStorage){
       
        //accordion headers
        for(let i=0; i<layout.children.length; i++){
      
            $d = this.body.find('#'+layout.children[i].dom_id);
            
            $('<h3>').html( layout.children[i].name )
                     .insertBefore($d);
            
        }
        
        $d = this.body.find('#'+layout.dom_id);
        $d.accordion({heightStyle: "content", 
                      active:false,
                //active:(currGroupType == 'expanded')?0:false,
                      collapsible: true });
                      
        }
    }
    
    //
    // Find configuration for element in array by internal key property
    //
    #layoutContentFindElement(content, ele_key){

        if(!Array.isArray(content)){
            if(content.children && content.children.length>0){
                return this.#layoutContentFindElement(content.children, ele_key);    
            }else{
                return null;
            }
        }
        
        for(let i=0; i<content.length; i++){
            if(content[i].key == ele_key){
                return  content[i];
            }else if(content[i].children && content[i].children.length>0){
                let res = this.#layoutContentFindElement(content[i].children, ele_key);    
                if(res) return res;
            }
        }
        return null; //not found
    }
    
    //
    // Find widget bt application/widget name in cfg_widgets sush as "heurist_SearchInput"
    //
    #layoutContentFindWidget(content, widget_name){
        
        if(!Array.isArray(content)){
            if(content.children && content.children.length>0){
                return this.#layoutContentFindWidget(content.children, widget_name);    
            }else{
                return null;
            }
        }
        
        for(let i=0; i<content.length; i++){
            if(content[i].appid == widget_name){
                return  content[i];
            }else if(content[i].children && content[i].children.length>0){
                let res = this.#layoutContentFindWidget(content[i].children, widget_name);    
                if(res) return res;
            }
        }
        return null; //not found
    }

    //
    //
    //
    #layoutContentFindAllWidget(content){

        let res = [];
        
        if(!Array.isArray(content)){
            if(content.children && content.children.length>0){
                let res2 =  this.#layoutContentFindAllWidget(content.children);    
                if(res2) res = res.concat(res2);
            }else{
                return null;
            }
        }
        
        for(let i=0; i<content.length; i++){
            if(content[i].appid){
                res.push(content[i]);
            }else if(content[i].children && content[i].children.length>0){
                let res2 = this.#layoutContentFindAllWidget(content[i].children);    
                if(res2) res = res.concat(res2);
            }
        }
        return res;
    }
    
    //
    //
    //
    #layoutContentFindMainRealm(content){
        //find all widgets on page
        let res = {};
        let widgets = this.#layoutContentFindAllWidget(content);
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
    #layoutContentFindParent(parent, ele_key){
        
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
                let res = this.#layoutContentFindParent(children[i], ele_key);    
                if(res) return res;
            }
        }
        return false; //not found
    }
    
    //
    // Replace element
    //    
    #layoutContentSaveElement(content, new_cfg){
            
        let ele_key = new_cfg.key;
        
        for(let i=0; i<content.length; i++){
            if(content[i].key == ele_key){
                if(new_cfg.type && new_cfg.type.indexOf('text')==0){
                   new_cfg.content =  content[i].content;
                }
                content[i] = new_cfg;
                return true 
            }else if(content[i].children && content[i].children.length>0){
                if (this.#layoutContentSaveElement(content[i].children, new_cfg)){
                    return true;
                }
            }
        }

        return false;            
    }
    
    //
    //
    //
    #prepareTemplate(layout, callback){ 
       
        if(layout.template=='default'){
        
           callback.call(this, layout.children[0]); 
            
        }else if(layout.template=='blog'){
            
           let ele = this.#layoutContentFindWidget(layout, 'heurist_SearchTree');
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
                    
                }catch{
                    alert('Error in blog template script');
                }
           }
        }
    }
        
    
    //
    //
    //
   
    #convertOldCmsFormat(container, lvl){


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
                        children: this.#convertOldCmsFormat(ele, lvl+1) };
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
    #convertHTMLtoJSON(ele, lvl){
        
        ele = $(ele);

        let res;
        
        if(ele.length>1){

            if(ele.find('[data-cms-type]').length>0 || ele.attr('data-lang') || ele.find('div[data-lang]').length>0){
                res = [];
                ele.each((i, item)=>{
                    res.push(this.#convertHTMLtoJSON(item, lvl));
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
                    res['css'] = this.#css2json(ele.attr('style'));    
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
                        res.children.push(this.#convertHTMLtoJSON(item, lvl+1));                    
                    });
                    res.folder = true;    
                    
                }else{
                    //no more css layout elements 
                    if(ele.attr('data-lang') || ele.find('div[data-lang]').length>0){
                            res = $.extend(res, this.#convertHTMLtoJSON(ele.html(), lvl+1));
                    }else{
                            res.content = ele.html();
                    }
                }
            }
        
        }
        
        return res;
    }
    
    //
    //
    //
    #css2json(css) {
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
    #convertJSONtoHTML(content){
        
        //from json
        console.log(content);
        
        //to html
        let res = this.#layoutInitFromJSON(content, null, true, true);
        console.log(res);
        
        //and back to json
        res = this.#convertHTMLtoJSON(res, 0);
        
        console.log(res);
        
        return res;
    }
      
  

  // Additional methods converted here...

  // Public API

  layoutInitTabs(layout, container) {
    this.#layoutInitTabs(layout, container);
  }

  layoutInitAccordion(layout, container) {
    this.#layoutInitAccordion(layout, container);
  }

  layoutInitCardinal(layout, container) {
    this.#layoutInitCardinal(layout, container);
  }

  layoutInit(layout, container, supp_options) {
    this._supp_options = supp_options || {};
    return this.#layoutInitFromJSON(layout, container, false, true);
  }

  convertOldCmsFormat(layout, container) {
    container = $(container);
    container.empty();
    container.html(layout);
    return this.#convertOldCmsFormat(container, 0);
  }

  layoutInitKey(layout, i) {
    this.#layoutInitKey(layout, i);
  }

  layoutAddWidget(layout, container) {
    this.#layoutAddWidget(layout, container);
  }

  layoutContentFindElement(_layout_cfg, ele_key) {
    return this.#layoutContentFindElement(_layout_cfg, ele_key);
  }

  layoutContentFindParent(parent, ele_key) {
    return this.#layoutContentFindParent(parent, ele_key);
  }

  layoutContentFindWidget(_layout_cfg, widget_name) {
    return this.#layoutContentFindWidget(_layout_cfg, widget_name);
  }

  layoutContentFindMainRealm(_layout_cfg) {
    return this.#layoutContentFindMainRealm(_layout_cfg);
  }

  layoutContentSaveElement(_layout_cfg, new_cfg) {
    return this.#layoutContentSaveElement(_layout_cfg, new_cfg);
  }

  setEditMode(newmode) {
    this.isEditMode = newmode;
  }

  prepareTemplate(layout, callback) {
    this.#prepareTemplate(layout, callback);
  }

  layoutCheckWidgets() {
    const widgets = this.body.find("div.heurist-widget");
    let are_all_widgets_inited = true;

    $.each(widgets, (i, item) => {
      const widgetname = $(item).attr("data-widgetname");
      if (widgetname) {
        const is_inited =
          $(item)[widgetname]("instance") &&
          $(item)[widgetname]("option", "init_completed");
        if (is_inited === false) {
          are_all_widgets_inited = false;
          return false;
        }
      }
    });
    return are_all_widgets_inited;
  }

  convertJSONtoHTML(content) {
    return this.#convertJSONtoHTML(content);
  }
}

window.layoutMgr = new HLayoutMgr();
