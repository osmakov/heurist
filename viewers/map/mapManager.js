/**
* mapManager.js - manage layers and proejcts - map legend
* 
* Manager control - map legend
* Addmapdoc control - addition of new record with type "Map Document"
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
/* global L, hMapDocument */

L.Control.Manager = L.Control.extend({
    onAdd: function(map) {
        
        let container = this._container = L.DomUtil.create('div','leaflet-bar');

        L.DomEvent
          .disableClickPropagation(container)
          .disableScrollPropagation(container);              

        $(container)
            //.css({'width':'200px','overflow-y':'auto'})
            .resizable({ghost:true,handles:'w',minWidth:250, maxWidth:500,
                        stop:function(event, ui){ $(ui.element).css({left:0}); }})
            .resizable( "disable" );

                        
        return container;
    },
    
    onRemove: function(map) {
        // Nothing to do here
    }
});

L.control.manager = function(opts) {
    return new L.Control.Manager(opts);
}

//--------------------------------
// add map doc control - see createNewMapDocument
//
L.Control.Addmapdoc = L.Control.extend({
    
    _mapwidget: null,
    
    initialize: function(options) {

        options = options || {};
        
        L.Util.setOptions(this, options);
        
        this._mapwidget = options.mapwidget;
        
        L.Control.prototype.initialize.call(this, this.options);
    },

    
    onAdd: function(map) {
        
        let container = L.DomUtil.create('div','leaflet-bar');

        L.DomEvent
          .disableClickPropagation(container)
          .disableScrollPropagation(container);
          
        $('<a>').attr('title', window.hWin.HR('Create new map document'))
            .html('<span class="ui-icon ui-map-document" style="width:22px;margin:2px 0px">'
+'<span class="ui-icon ui-icon-plus" style="position:absolute;bottom:-2px;right:0px;font-size:12px;color:white;text-shadow: 2px 2px gray" />'
                +'</span>')
            .css({'width':'22px','height':'22px','border-radius': '2px','cursor':'pointer','margin':'0.1px'})
            //.addClass('ui-icon ui-map-document')
            .appendTo(container);
        
        L.DomEvent
            .on(container, 'click', this._onClick, this);
        
        return container;
    },

    onRemove: function(map) {
        // Nothing to do here
    },
    
    _onClick: function() {
       //this.mapPublish.openPublishDialog();
       this._mapwidget.mapManager.createNewMapDocument();
    }
});

L.control.addmapdoc = function(opts) {
    return new L.Control.Addmapdoc(opts);
}



//--------------------------------
//        
// creates accordion with 3(4) panes
// loads prededifned list of base layers
// calls refresh map documents
//        
//$.widget( "heurist.mapmanager", {
    
function HMapManager( _options )
{    
    const _className = "MapManager",
    _version   = "0.4",

    DT_GEO_OBJECT = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'],
    DT_LEGEND_OUT_ZOOM = window.hWin.HAPI4.sysinfo['dbconst']['DT_LEGEND_OUT_ZOOM'],
    TRM_LEGEND_OUT_ZOOM_HIDDEN =  window.hWin.HAPI4.sysinfo['dbconst']['TRM_LEGEND_OUT_ZOOM_HIDDEN'],
    TRM_LEGEND_OUT_ZOOM_DISABLED = window.hWin.HAPI4.sysinfo['dbconst']['TRM_LEGEND_OUT_ZOOM_DISABLED'];
    


    let options = {
        container: null,  
        mapwidget: null,   
        visible_panels: null,
        visible_basemaps: null,
        visible_mapdocuments: null,
        hasTempMap: false,  //show hide this panel
        is_ui_main: false   //if true - hide on collapse
    },
        
    mapDocuments = null, //hMapDocument for db map docs,  Note: mapdocument with index=0 is search results
    mapdoc_treeview = null,
    mapdoc_select = null,  //selector in map toolbar
    mapdoc_visible = {}, //since 2022-07-29 the mapdocument is visible
    btn_expand = null,
    btn_collapse = null,
    
    maxHeight = 9999999,
    
    isExpanded = false,
    keepWidth = 250,
    
    basemaplayer =null,  //current basemap layer
    
    _suppress_select_event = false,

    _mapdoc_layers = {}; // cached toggle setting for each layer within each mapdoc

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    function _init (_options ) {
       
        options = $.extend(options, _options);

        options.container = $(options.container);
        
        options.container.css({border: '2px solid rgba(0,0,0,0.2)','background-clip': 'padding-box'}); 

        btn_expand = $('<a>').attr('title', window.hWin.HR('Map Legend'))
            .css({'width':'70px','height':'22px','border-radius': '2px','cursor':'pointer','margin':'0.1px'})
            .on({click: _onExpand })
            .appendTo(options.container);

        // Legend text
        $('<span>', {style: 'margin-right:5px;font-weight:bold;text-align:unset;line-height:26px;'})
            .text(window.hWin.HR('Legend'))
            .appendTo(btn_expand);

        // Legend icon
        $('<span>', {class: 'ui-icon ui-icon-list'})
            .appendTo(btn_expand);

        btn_collapse = $('<a>').attr('title', 'Minimize legend')
            .css({'width':'16px','height':'16px','z-index':1001,background:'none',border:'none',cursor:'pointer',position:'absolute',right:'3px'})
            .addClass('ui-icon ui-icon-caret-1-ne')
            .on({click: _onCollapse }).hide()
            .appendTo(options.container);
        
        options.container.find('.ui-resizable-handle')
            .css({'border-right': '1px solid lightgray', 'width': '7px', 'height': '99%'})
            .append($('<span class="ui-icon ui-icon-carat-1-w" style="height:100%;width:11px;color:gray;top:3px;left:-3px;font-size:10px;">'));
        
        mapDocuments = hMapDocument( { mapwidget:options.mapwidget } ); 
        
        $('<div>').attr('grpid','search').addClass('svs-acordeon outline_suppress')
                .css('border-bottom-color','lightgray')
                .append( _defineHeader('Result Sets', 'search'))
                .append( _defineContent('search') ).appendTo(options.container);
        $('<div>').attr('grpid','mapdocs').addClass('svs-acordeon outline_suppress')
                .css('border-bottom-color','lightgray')
                .append( _defineHeader('Map Documents', 'mapdocs'))
                .append( _defineContent('mapdocs') ).appendTo(options.container);
        $('<div>').attr('grpid','tempmap').addClass('svs-acordeon outline_suppress')
                .append( _defineHeader('Temp Map', 'tempmap'))
                .append( _defineContent('tempmap') ).appendTo(options.container);        
        $('<div>').attr('grpid','basemaps').addClass('svs-acordeon outline_suppress')
                .css('border-bottom','none')
                .append( _defineHeader('Base Maps', 'basemaps'))
                .append( _defineContent('basemaps') ).appendTo(options.container);        
                
        
        //restore expand/collapse status
        let keep_status = window.hWin.HAPI4.get_prefs('map_control_status');
        if(keep_status){
            keep_status = window.hWin.HEURIST4.util.isJSON(keep_status);   
        }
        if(!keep_status) {
            keep_status = { 'search':true };
        }
        
        //init list of accordions
        let cdivs = options.container.find('.svs-acordeon');
        $.each(cdivs, function(i, cdiv){

            cdiv = $(cdiv);
            let groupid = cdiv.attr('grpid');
            cdiv.accordion({
                active: ( ( keep_status && keep_status[ groupid ] )?0:false),
                header: "> h3",
                heightStyle: "content",
                collapsible: true,
                activate: function(event, ui) {
                    //save status of accordions - expandad/collapsed
                    if(ui.newHeader.length>0 && ui.oldHeader.length<1){ //activated
                        keep_status[ ui.newHeader.attr('grpid') ] = true;
                    }else{ //collapsed
                        keep_status[ ui.oldHeader.attr('grpid') ] = false;
                    }
                    //save
                    window.hWin.HAPI4.save_pref('map_control_status', JSON.stringify(keep_status));
                    //replace all ui-icon-triangle-1-s to se
                    cdivs.find('.ui-icon-triangle-1-e').removeClass('ui-icon-triangle-1-se');
                    cdivs.find('.ui-icon-triangle-1-s').removeClass('ui-icon-triangle-1-s').addClass('ui-icon-triangle-1-se');
                    
                    that.setHeight();

                }
            });
        });
        //replace all ui-icon-triangle-1-s to se
        cdivs.find('.ui-accordion-header-icon').css('font-size','inherit');
        cdivs.find('.ui-icon-triangle-1-s').removeClass('ui-icon-triangle-1-s').addClass('ui-icon-triangle-1-se');        
       
        //that.loadBaseMap(0);
        
        options.container.addClass('ui-widget-content')
            .css({'margin-right':'5px','font-size':'0.97em'});
            
        /*  
        if(Array.isArray(options.visible_panels) && options.visible_panels.indexOf('off')<0){
            _onExpand(); //expand at once
        }else{
            that.updatePanelVisibility();  
        }
        */
        //
        
    }
    
    //
    //
    //    
    function _defineHeader(name, domain){

        let sIcon = '';
        //<span class="ui-icon ui-icon-'+sIcon+'" ' + 'style="display:inline-block;padding:0 4px"></span>
        
        let $header = $('<h3 grpid="'+domain+'" class="hasmenu">' + sIcon + '<span>'
            + window.hWin.HR(name) + '</span></h3>')
            .addClass('tree-accordeon-header outline_suppress svs-header');

         let isPublished = options.mapwidget.mapping('option','isPublished');
         if(domain=='mapdocs' && !isPublished){

            let append_link = $('<a title="create new map document">',{href:'#'})
                .html('Add <span class="ui-icon ui-map-document" style="width:22px">'
+'<span class="ui-icon ui-icon-plus" style="position:absolute;bottom:-2px;right:-2px;font-size:12px;color:white;text-shadow: 2px 2px gray" />'
                +'</span>')
                .css({'line-height':'15px',height:'14px',width:'50px',background: 'none',float:'right'})
                .on('click',_createNewMapDocument)
                //.on('click', function(){that.filterListMapDocuments(true);})
                .appendTo($header);
                
           //$header.addClass('with_supplementals');
        }

        return $header

    }
    
    //
    //  adds new mapdocument record
    //
    function _createNewMapDocument(event){

        window.hWin.HEURIST4.util.stopEvent(event);

        //edit layer or mapdocument record
        window.hWin.HEURIST4.ui.openRecordEdit(-1, null,
            {selectOnSave:true,
                onClose: function(){ 
                    //parent_span.find('.svs-contextmenu4').hide();
                },
                onselect:function(event, data){
                    if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){

                        _addToMapDocumentTree( data.selection );

                    }
                },
                new_record_params:{rt:window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT']}
        });
    }
    
    //
    // redraw legend entries
    //
    function _defineContent(groupID, data, container){

        let content = null;
        
        if(groupID=='search' || groupID=='tempmap'){
            
            content = $('<div>');
            
            let resdata = mapDocuments.getTreeData((groupID=='search')?0:'temp');
            
            _refreshMapDocumentTree( resdata, content, groupID);
    
        }else if(groupID=='basemaps'){
            // load list of predefined base layers 
            // see extensive list in leaflet-providers.js
            let map_providers = options.mapwidget.mapping('getBaseMapProviders');

            if(typeof options.visible_basemaps === 'string'){
                options.visible_basemaps = options.visible_basemaps.split(';');
            }
            if(!Array.isArray(options.visible_basemaps)){
                options.visible_basemaps = [];
            }

            content = '';
            for (let k=0; k<map_providers.length; k++){
                if(options.visible_basemaps.length==0 || options.visible_basemaps.indexOf(map_providers[k]['name'])>=0){
                    content = content + '<label><input type="radio" name="basemap" data-mapindex="'+k
                                  + '" data-mapid="'+map_providers[k]['name']+'">'
                                  + map_providers[k]['name']+'</label><br>';
                }
            }
            
            if(options.is_ui_main){
                content = content + '<span style="color: darkgray; padding-left: 5px;" class="heurist-helper1">Email team for '
                + '<a href="http://leaflet-extras.github.io/leaflet-providers/preview/index.html" target="_blank" '
                    + 'style="display: inline; background: transparent; text-decoration: underline; color: black;">other base maps</a>'
                + '</span>';
            }
            content = content + '<div style="text-align:center; display: inline-block;margin-left: 5px;" id="basemap_filter_btns">'
                +'<button name="basemap_filter">filters</button>'
                +'<button name="basemap_filter_reset">reset</button></div>';
            
            content = $(content);
            content.find('input').on( { change: that.loadBaseMap });
            
            content.find('button[name="basemap_filter"]')
                .button()
                .css({'font-size': '0.8em', 'padding': '0em 1em'})
                .on( { click: function(){
                        let cfg = options.mapwidget.mapping('getBaseMapFilter');
                        window.hWin.HEURIST4.ui.showImgFilterDialog(cfg, function(filter){
                            options.mapwidget.mapping('setBaseMapFilter', filter);
                        });   
                }});
            content.find('button[name="basemap_filter_reset"]')
                .button()
                .css({'font-size': '0.8em', 'padding': '0em 1em'})
                .on( { click: function(){
                    options.mapwidget.mapping('setBaseMapFilter', null);
                }});
            
        }else  if(groupID=='mapdocs'){
            //load list of mapddocuments
            mapdoc_treeview = $('<div>');
            
            mapDocuments.loadMapDocuments(null, function(resdata){
                        _refreshMapDocumentTree( resdata, mapdoc_treeview, 'mapdocs' );
                        options.mapwidget.mapping('onInitComplete', 'mapdocs');
                    } );
            
            content = mapdoc_treeview;        
        }
        
        if(window.hWin.HEURIST4.util.isempty(content) ){
            content = '';
        }
        if(window.hWin.HEURIST4.util.isnull(container)){
            return $('<div>', {style: 'width: 98%;'}).append(content);
        }else{
            container.empty();
            container.append(content);
            return container;
        }
        
    }
    
    //
    //
    //
    function _refreshSearchContent(){
        
            let grp_div = options.container.find('.svs-acordeon[grpid="search"]');
            _defineContent('search', null, grp_div.find('.ui-accordion-content'));
            //defineContent( 'search' );
            
            //hide invisible layers and allow max 5 entries
            that.setHeight();
        
    }
    
    //
    // adds new mapdoc entry in mapdoc tree
    //
    function _addToMapDocumentTree( resdata ){
        let $res = {};

        if(resdata==null){

            $res['key'] = 99999999;
            $res['title'] = 'temp';
            $res['type'] = 'mapdocument';
            $res['lazy'] = true;
                
        }else{

            let rec = resdata.getFirstRecord();
        
            $res['key'] = resdata.fld(rec, 'rec_ID');
            $res['title'] = resdata.fld(rec, 'rec_Title');
            $res['type'] = 'mapdocument';
            $res['lazy'] = true;
        }

        if(mapdoc_select !== null){
            that.populateMapDocuments(mapdoc_select);
        }

        //mapdoc_treeview.find('.ui-fancytree').show();
        mapdoc_treeview.find('.empty_msg').remove();
        let tree = $.ui.fancytree.getTree( mapdoc_treeview );
        tree.getRootNode().addChildren( [$res] ).setSelected(true);
    }
    
    //
    // it is invoked on retieve of map document list 
    // or on update of search results
    //
    function _refreshMapDocumentTree( resdata, tree_container, groupid ){

        //create treeview data
        let treedata = [];
        
        if( window.hWin.HEURIST4.util.isRecordSet(resdata) ){
        
            let idx, records = resdata.getRecords();
            for(idx in records){
                if(idx)
                {
                    let record = records[idx];
                    let recID  = resdata.fld(record, 'rec_ID'),
                    recName = resdata.fld(record, 'rec_Title'),
                    extent = resdata.fld(record, DT_GEO_OBJECT); //initial extent
                    
                    let $res = {};  
                    $res['key'] = recID;
                    $res['title'] = recName;
                    $res['type'] = 'mapdocument';
                    $res['extent'] = extent;
                    $res['lazy'] = true;
                    //$res['checkbox'] = 'radio';
                    //$res['checkbox'] = false;
                    //$res['radiogroup'] = true;

                    treedata.push($res);
                    //ele.append("<option value='"+recID+"'>"+recName+"</option>");
                    //mapdocs = mapdocs + "<li mapdoc_id='"+recID+"'><a>"+recName+"</a></li>";
                }
            }//for
            
        }else{
            treedata = resdata;
        }
                
        tree_container.empty();    
        
        if(window.hWin.HEURIST4.util.isFunction($('body').fancytree)){
     
            tree_container.fancytree({  //addClass('tree-facets').

                checkbox: true,
                selectMode: 2,  // hierarchical multi-selection
                source: treedata,
                lazyLoad: function(event, data){
                //load: function(forceReload){
                    //load content of mapdocument
                    let node = data.node;
                    let dfd = new $.Deferred();
                    data.result = dfd.promise();
                    mapDocuments.zoomToMapDocument(node.key);
                    mapDocuments.openMapDocument(node.key, dfd);
                    //return dfd.promise();
                    
                },
                extensions: ["filter"],
                expand: function(e, data){
                    
                },
                loadChildren: function(e, data){

                    setTimeout(function(){
                        
                        if(data.node.type=='mapdocument'){
                            data.node.setSelected(true, {noEvents:true} );
                            //enable buttons
                            let btns = $(data.node.li).find('.svs-contextmenu3');
                            btns.find('span.ui-icon-arrow-4-diag').css({color:'black'});    
                            btns.find('span.ui-icon-refresh').css({color:'black'});
                        }

                    }, 500);                                           
                },
                select: function(e, data) {  //show/hide   checkbox event listener

                    let node = data.node;
                    let is_selected = node.isSelected();
                    if(node.type=='mapdocument'){

                        const mapdoc_id = node.key;

                        //if not expanded, expand, it loads layers (opens mapdocument)
                        if(is_selected){

                            if(!mapDocuments.isLoaded(mapdoc_id)){ // remove context menu to refresh it with extra controls
                                $(node.span).find('.svs-contextmenu3').remove();
                            }

                            delete mapdoc_visible[mapdoc_id];
                            //hide all other mapdocs
                            that.toggleMapDocument(Object.keys(mapdoc_visible).join(','), false); 
                            mapdoc_visible = {};

                            node.setExpanded(true); //load content - init lazy load - see lazyLoad ->  mapDocuments.openMapDocument
                        }else{

                            if(node.isExpanded() && node.hasChildren()){ // save checked layers
                                $.each(node.getChildren(), function(i, layer_node){

                                    let is_checked = layer_node.isSelected();
                                    let node_key = layer_node.key;

                                    if(!Object.hasOwn(_mapdoc_layers, mapdoc_id)){ _mapdoc_layers[mapdoc_id] = {}; }

                                    _mapdoc_layers[mapdoc_id][node_key] = is_checked;
                                });
                            }

                            node.setExpanded(false);
                        }

                        if(node.isExpanded() || mapDocuments.isLoaded(mapdoc_id)){

                            mapDocuments.setMapDocumentVisibility(mapdoc_id, is_selected);
                        
                            if(node.hasChildren()){
                                let mapdoc_vis = is_selected;
                                //set selection in legend for child layers
                                _suppress_select_event = true;
                                $.each(node.getChildren(), function(i, layer_node){

                                    let node_key = layer_node.key;

                                    if(!Object.hasOwn(_mapdoc_layers, mapdoc_id)){ _mapdoc_layers[mapdoc_id] = { node_key: true }; }

                                    let display_layer = mapdoc_vis && _mapdoc_layers[mapdoc_id][node_key];
                                    layer_node.setSelected( display_layer );
                                });
                                _suppress_select_event = false;
                            }
                        }
                        
                        if(is_selected){
                            mapdoc_visible[mapdoc_id] = node.title; // add to list
                        }else{
                            //restore default CRS if mapdoc is off
                            options.mapwidget.mapping('defineCRS', '');
                            that.loadBaseMap(0); //loads default
                            delete mapdoc_visible[mapdoc_id]; // remove from list
                        }

                        if(mapdoc_select !== null){

                            let selected_opts = Object.values(mapdoc_visible);
                            selected_opts = selected_opts.length == 0 ? ['Current result set'] : selected_opts;
                            if(!mapdoc_select.hSelect('instance')){
                                mapdoc_select.hSelect();
                            }
                            mapdoc_select.hSelect('widget').attr('title', selected_opts.join(', '));

                            if(selected_opts.length == 1){
                                mapdoc_select.hSelect('widget').find('span.ui-selectmenu-text').text(selected_opts[0]);
                            }else{
                                mapdoc_select.hSelect('widget').find('span.ui-selectmenu-text').text(selected_opts.length+' documents displayed');
                            }
                        }

                        //
                        //mapDocuments.openMapDocument(node.key, dfd);
                    }
                    else if(node.type=='layer'){   //show/hide layer
                        
                        if(_suppress_select_event) return;
                        
                        let mapdoc_id = node.data.mapdoc_id;
                        let not_visible = true;
                        if((mapdoc_id>0 && mapdoc_visible[mapdoc_id]) || mapdoc_id==0 || mapdoc_id=='temp'){
                            //node.key - heurist layer record id
                            let layer_rec = mapDocuments.getLayer(mapdoc_id, node.key);
                            if(layer_rec){
                                //set layer visibility on map
                                (layer_rec['layer']).setVisibility( is_selected );  
                                not_visible = !is_selected;
                            }
                        }
                        if(mapdoc_id>0 && !mapdoc_visible[mapdoc_id]){
                            not_visible = true; //mapdocument is not visible
                        }
                        if(not_visible && node.parent){ // toggle map doc

                            let parent_key = node.parent.key;
                            let node_key = node.key

                            if(!Object.hasOwn(_mapdoc_layers, parent_key)) {
                                _mapdoc_layers[parent_key] = { node_key: true }; 
                            }else{
                                _mapdoc_layers[parent_key][node_key] = true;
                            }

                            node.parent.setSelected(true);
                            not_visible = false;
                        }

                        if(not_visible){
                            node.setSelected(false);
                        }else if(mapdoc_id==0 || mapdoc_id=='temp'){
                            //need to make all themes visible if none of them marked
                            let themes = node.children;
                            if(themes && themes.length>0){
                                let all_hidden = true;
                                for(let i=0; i<themes.length; i++){
                                    if(themes[i].isSelected()){
                                        all_hidden = false;
                                        break;
                                    }
                                }
                                if(all_hidden)
                                for(let i=0; i<themes.length; i++){
                                    themes[i].setSelected(true);
                                }
                            }
                        }
                    }
                    else if(node.type=='theme'){ //on show/hide theme
                    
                        //theme is obtained from resdata.fld(record, DT_SYMBOLOGY); see _getTreeData


                        let mapdoc_id = node.data.mapdoc_id;
                        let layer_id = node.data.layer_id;
                        let not_visible = true; 
                        if( layer_id>0 && 
                            ((mapdoc_id>0 && mapdoc_visible[mapdoc_id])|| mapdoc_id==0 || mapdoc_id=='temp')){
                        
                            let active_themes = [];
                            let layer_rec = mapDocuments.getLayer(mapdoc_id, layer_id);
                            if(layer_rec){
                                let siblings = node.parent.children;
                                
                                for(let i=0; i<siblings.length; i++){
                                        if(siblings[i].isSelected()){
                                            active_themes.push(siblings[i].data.theme);
                                            not_visible = false;
                                        }
                                }
                            }
                            
                            (layer_rec['layer']).applyThematicMap( active_themes );
                        }
                        if(not_visible){ //all themes are invisible - hide parent layer as well
                            mapdoc_id = node.parent.data.mapdoc_id;
                            if(mapdoc_id==0 || mapdoc_id=='temp'){ //current search
                                node.parent.setSelected(false);    
                            }
                        }else{
                            //something is visible but parent layer is not - set it visible
                            if(!node.parent.isSelected()){
                                node.parent.setSelected(true);    
                            }
                        }
                        
                    }

                },
                dblclick: function(e, data) {
                    data.node.toggleSelected();
                },
                keydown: function(e, data) {
                    if( e.which === 32 ) {
                        data.node.toggleSelected();
                        return false;
                    }
                },
                renderNode: function(event, data) {
                    // Optionally tweak data.node.span
                    
                    let item = data.node;
                    let $span = $(item.span);
                    if(item.type=='layer'){
                        let rec_id = item.key;
                        let mapdoc_id = item.data.mapdoc_id;
                        
                        //get symbology for layer (rec_id)
                        let style = mapDocuments.getSymbology( mapdoc_id, rec_id );
                        //convert json to css
                        let dcss = _prepareSymbologyForLegendItem(style);

                        $span.find("> span.fancytree-icon")
                        .css(dcss);

                    }else 
                    if(item.type=='theme'){ //render theme label in treeview
                    
                        
                    

                    }else if(item.type=='mapdocument'){
                        $span.find("> span.fancytree-checkbox").addClass('fancytree-radio');
                    }

                    if(item.type!='theme'){
                        _defineActionIcons( item );                                        
                    }
                }
            });     
            
            tree_container.addClass('tree-map');
        }

        if(treedata.length==0){    
            //tree_container.find('.ui-fancytree').hide();
            tree_container.append('<span class="empty_msg" style="font-style:italic;padding-left:10px;line-height:10px">none</span>');
        }else{
            tree_container.css('padding-left', '15px');
        }
        that.setHeight();
    
    }
    
    //
    // Converts symbology json to css
    //
    function _prepareSymbologyForLegendItem(style){
        
        let dcss = '';
        if(style['rectypeIconUrl']){
            dcss = {'display':'inline-block', 'background-image':'url('+style['rectypeIconUrl']+')'};
        }else{
            
            dcss = {'display':'inline-block', 'background-image':'none'};
            if(style['stroke']!==false){
                
                let opacity = style['opacity']>0?style['opacity']:1;
                let weight = (style['weight']>0&&style['weight']<4)?style['weight']:3;
                dcss['width']  = 16-weight*2; 
                dcss['height'] = 16-weight*2;
                
                const clr = window.hWin.HEURIST4.ui.hexToRgbStr(style['color'], opacity);
                dcss['border'] = (clr!=null)?(weight+'px solid '+clr):'none';
                                
                if ( style['opacity']>0 && style['opacity']<1 ) {
                    dcss['-webkit-background-clip'] = 'padding-box'; //for Safari
                    dcss['background-clip'] = 'padding-box'; //for IE9+, Firefox 4+, Opera, Chrome
                }
                
            } else {
                dcss['border'] = 'none';
            }

            let fillColor = null;
            if(style['fill']!==false){
                fillColor = style['fillColor']?style['fillColor']:style['color'];
                let fillOpacity = style['fillOpacity']>0?style['fillOpacity']:0.2;
                fillColor = window.hWin.HEURIST4.ui.hexToRgbStr(fillColor, fillOpacity);
            }
            if (fillColor != null){
                dcss['background-color'] = fillColor;
            }else{
                dcss['background'] = 'none';
            }
        }   
        
        return dcss;     
    }
    
    //
    //  Adds menu buttons to treeview items
    //
    function _defineActionIcons(item)
    { 
        let item_li = $(item.li), 
            recid = item.key, 
            mapdoc_id = 0;
            
        if($(item.span).find('.svs-contextmenu3').length==0){
            
            if(item.type=='layer'){
                mapdoc_id = item.data.mapdoc_id;
            }else{
                mapdoc_id = item.key;
                recid = -1;
            }
            
            let parent_span = item_li.children('span.fancytree-node');
            
            let isEditAllowed = options.mapwidget.mapping('option','isEditAllowed');
            let is_image_layer = recid > 0 && recid < 9000000 ? mapDocuments.isImageLayer(mapdoc_id, recid) : false;
            let is_logged_in = window.hWin.HAPI4.has_access();

            let actionspan = '<div class="svs-contextmenu3" '
                    +((mapdoc_id>=0 || mapdoc_id=='temp')?('" data-mapdoc="'+mapdoc_id+'"'):'')
                    +(recid>0?('" data-recid="'+recid+'"'):'')+'>'
                +(is_image_layer || recid > 0 ? '<span class="ui-icon ui-icon-opacity" title="Change layer\'s opacity"></span>' : '')
                +'<span class="ui-icon ui-icon-zoom zoom-to-extent" '
                    +((item.type=='mapdocument' && !item.data.extent)?'style="color:gray"':'')
                    +' title="Zoom to '+item.type+' extent"></span>';
                    
            if(isEditAllowed){        
                
                if(item.type=='mapdocument' && mapdoc_id>0){

                    actionspan += (
                        '<span class="ui-icon ui-icon-pencil" title="Modify the map document"></span>'
                        + (window.hWin.HAPI4.has_access() ? 
                        '<span class="ui-icon ui-map-layer" title="Add map layer">'
                            +'<span class="ui-icon ui-icon-plus" style="position:absolute;bottom:-2px;right:-2px;font-size:12px;color:white;text-shadow: 2px 2px gray" />'
                        +'</span>' : '')
                        +'<span class="ui-icon ui-icon-refresh" style="color:gray" title="Reload map document"></span>'
                        //+'<span class="ui-icon ui-icon-trash" style="color:gray" title="Close map document"></span>'
                    )

                }else if(mapdoc_id>0){

                    actionspan +=
                        `<span class="ui-icon ui-icon-pencil" title="Change symbology${is_logged_in ? ' and behaviour of map layer' : ''}"></span>`;

                }else{
                    
                    if(recid<9000000){
                    actionspan +=
                        `<span class="ui-icon ui-icon-pencil" title="Change symbology${is_logged_in ? ' and behaviour of map layer' : ''}"></span>`
                        +'<span class="ui-icon ui-icon-trash" title="Remove map layer"></span>';
                    
                    }else{
                    actionspan +=
                        '<span class="ui-icon ui-icon-pencil" title="Change symbology"></span>'
                        +'<span class="ui-icon ui-icon-arrowstop-1-s" title="Save result set as layer"></span>'
                        +'<span class="ui-icon ui-icon-trash" title="Remove map layer"></span>';
                    }
                }

            }
            actionspan = $(actionspan+'</div>').appendTo(parent_span);

            $('<div class="svs-contextmenu4"></div>').appendTo(parent_span);
                
                
            actionspan.find('.ui-icon').on('click', function(event){
                let ele = $(event.target);
                let parent_span = ele.parents('span.fancytree-node');
                
                function __in_progress(){
                    if(parent_span.find('.svs-contextmenu4').is(':visible')) {
                        return true; 
                    }
                    parent_span.find('.svs-contextmenu4').show();
                    parent_span.find('.svs-contextmenu3').hide();
                    return false;
                }

                //timeout need to activate current node    
                setTimeout(function(){                         
                    let recid = ele.parents('.svs-contextmenu3').attr('data-recid');
                    let mapdoc_id = ele.parents('.svs-contextmenu3').attr('data-mapdoc');

                    let is_logged_in = !window.hWin.HAPI4.has_access();
                    let is_image_layer = recid > 0 ? mapDocuments.isImageLayer(mapdoc_id, recid) : false;
                    
                    if(ele.hasClass('zoom-to-extent')){

                        //zoom to extent
                        if(recid>0){
                            
                            if(mapdoc_id>=0 || mapdoc_id=='temp'){
                                let layer_rec = mapDocuments.getLayer(mapdoc_id, recid);
                                if(layer_rec) (layer_rec['layer']).zoomToLayer();
                            } 
                            
                        }else if(mapdoc_id>0){
                            mapDocuments.zoomToMapDocument(mapdoc_id);
                        }

                        // Update zoom icon
                        ele.removeClass('ui-icon-zoom ui-icon-zoomin ui-icon-zoomout');
                        ele.addClass('ui-icon-zoom');

                    }else if(ele.hasClass('ui-icon-plus')){ //add new layer to map document
                    
                        let in_progress = __in_progress();
                        if(mapdoc_id>0 && !in_progress){

                            mapDocuments.selectLayerRecord(mapdoc_id, function(data){
                                    parent_span.find('.svs-contextmenu4').hide();
                                    if(data){
                                        item.removeChildren();
                                        item.addChildren( data );
                                        /*setTimeout(function(){
                                        $.each(item.children, function( idx, item_ch ){
                                            _defineActionIcons( item_ch );
                                            });},500);*/
                                    }
                                });
                            /*
                            let dfd = new $.Deferred();
                            mapDocuments.selectLayerRecord(mapdoc_id, dfd);
                            $.when( dfd.promise() ).done(
                                function(data){
                                    item.removeChildren();
                                    item.addChildren( data );
                                }
                            );
                            */
                        }
                        
                    }else if(ele.hasClass('ui-icon-arrowstop-1-s')){ 
                    
                            mapDocuments.saveResultSetAsLayerRecord(recid, function(data){
                                    ele.hide();
                                    parent_span.find('.svs-contextmenu4').hide();
                            });
                        
                    }else if(ele.hasClass('ui-icon-pencil')){

                        let symbology_only = mapdoc_id > 0 && recid < 9000000 && is_logged_in; // only allow user to edit symbology

                        if(!symbology_only && (mapdoc_id>0 || recid<9000000)){

                            __in_progress();
                            //edit layer or mapdocument record
                            window.hWin.HEURIST4.ui.openRecordEdit(recid>0?recid:mapdoc_id, null,
                            {selectOnSave:true,
                                onClose: function(){ 
                                    parent_span.find('.svs-contextmenu4').hide();
                                },
                                onselect:function(event, data){
                                if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                    let recset = data.selection;
                                    let rec = recset.getFirstRecord();
                                    item.title = recset.fld(rec, 'rec_Title');
                                    parent_span.find('span.fancytree-title').text( item.title );
                                    
                                    let symbology = recset.fld(rec
                                                    , window.hWin.HAPI4.sysinfo['dbconst']['DT_SYMBOLOGY']);
                                    if(recid>0 && symbology){
                                        //apply symbolgy on map
                                        let layer_rec = mapDocuments.getLayer(mapdoc_id, recid);
                                        if(layer_rec) (layer_rec['layer']).applyStyle( symbology );
                                        //render new symbology in legend
                                        item.render(true);
                                    }
                                    
                                }
                            }});
                        }else if(is_image_layer){

                            mapDocuments.editImageFilter(mapdoc_id, recid, function(new_style){

                                let layer_rec = mapDocuments.getLayer(mapdoc_id, recid);
                                if(layer_rec) { layer_rec['layer'].applyStyle(new_style); }

                                item.render(true);
                            });
                        }else{
                            // get layer record, take symbology field and title 
                            // open symbology editor
                            // on exit 1) call mapLayer.applyStyles
                            //         2) change title in tree and timeline
                            mapdoc_id = symbology_only ? mapdoc_id : 0;
                            mapDocuments.editSymbology(mapdoc_id, recid, function(new_title, new_style){
                                //update layer title in legend and timeline
                                if(new_title && item.title != new_title){
                                    item.title = new_title;
                                    parent_span.find('span.fancytree-title').text( new_title );
                                }
                                
                                //render new symbology in legend
                                item.render(true);
                                
                            });
                        }
                    }else if(ele.hasClass('ui-icon-refresh') && (mapdoc_id>0) && mapDocuments.isLoaded(mapdoc_id)){
                            
                            mapDocuments.closeMapDocument(mapdoc_id);
                            //remove children from treeview
                            //item.setSelected(false, {noEvents:true});
                            item.removeChildren();
                            item.resetLazy();
                            
                            item.selected = false;
                            parent_span.removeClass('fancytree-selected fancytree-partsel');
                            
                            that.toggleMapDocument(mapdoc_id, true);
                    }else if(ele.hasClass('ui-icon-trash')){
                        
                        //mapdocument - remove from map and unload from memory
                        if(mapdoc_id>0){
                            mapDocuments.closeMapDocument(mapdoc_id);
                            //remove children from treeview
                            //item.setSelected(false, {noEvents:true});
                            item.removeChildren();
                            item.resetLazy();
                            
                            item.selected = false;
                            parent_span.removeClass('fancytree-selected fancytree-partsel');
                        }else if(recid>0){
                            //search result
                            mapDocuments.removeLayer(0, recid);
                            item.remove();
                        }
                        
                    }else if(ele.hasClass('ui-icon-opacity')){
                        // quick style edit
                        // image - opacity, vector - opacity + fillOpacity

                        let layer_rec = mapDocuments.getLayer(mapdoc_id, recid);
                        const DT_SYMBOLOGY = window.hWin.HAPI4.sysinfo['dbconst']['DT_SYMBOLOGY'];

                        if($('#quick-layer-custom').length > 0){ // remove existing popup
                            $('#quick-layer-custom').remove();
                        }

                        let $container = $('#quick-layer-custom');
                        if($container.length == 0){
                            $container = $('<div>', {
                                id: 'quick-layer-custom',
                                class: 'ui-widget-content',
                                style: `font-size: 10px; width: 200px; padding: 5px; z-index: 1000; border: 1px black solid`
                            }).appendTo($('body'));
                        }
                        $container.empty();

                        let style = null;
                        let opacity = 1;
                        let fillOpacity = 1;

                        if(is_image_layer){
                            
                            style = layer_rec['d'][DT_SYMBOLOGY];
                            if(style){
                                style = window.hWin.HEURIST4.util.isJSON(style[0]);
                            }

                            opacity = style?.opacity ? parseFloat(style.opacity) : 1;
                            
                        }else{
                            
                            style = mapDocuments.getSymbology( mapdoc_id, recid );

                            opacity = style && style.opacity ? parseFloat(style.opacity) : 1;
                            fillOpacity = style && style.fillOpacity ? parseFloat(style.fillOpacity) : 1;

                        }

                        // Opacity
                        let lbl_opacity = is_image_layer ? 'Opacity: ' : 'Outline opacity: ';

                        $('<div>').text(lbl_opacity).appendTo($container);
                        $('<input>', {
                            type: 'range', 
                            min: 0, max: 1, 
                            step: 0.01, 
                            value: opacity, 
                            name: 'opacity', 
                            style: 'width:85%;vertical-align: middle;'
                        }).appendTo($container);
                        $('<span>', {id: 'opacity-slider-val', style: 'padding-left: 5px;'}).text(opacity).appendTo($container);

                        if(!is_image_layer){
                            // Fill opacity
                            $('<div>').text('Fill Opacity: ').appendTo($container);
                            $('<input>', {
                                type: 'range', 
                                min: 0, max: 1, 
                                step: 0.01, 
                                value: fillOpacity, 
                                name: 'fill', 
                                style: 'width:85%;vertical-align: middle;'
                            }).appendTo($container);
                            $('<span>', {id: 'fill-slider-val', style: 'padding-left: 5px;'}).text(fillOpacity).appendTo($container);
                        }

                        const updateStyle = () => {
                            
                            style['opacity'] = parseFloat($container.find('[name="opacity"]').val());
                            $container.find('#opacity-slider-val').text(style['opacity']);
                            
                            if(!is_image_layer){
                                style['fillOpacity'] = parseFloat($container.find('[name="fill"]').val());
                                $container.find('#fill-slider-val').text(style['fillOpacity']);
                            }

                            mapDocuments.updateLayerSymbology(mapdoc_id, recid, style);

                            item.render(true);
                        };
                        const closePopup = (event) => {

                            let $ele = $(event.target);

                            if($ele.attr('id') != 'quick-layer-custom' &&
                                $ele.parents('#quick-layer-custom').length == 0){

                                    $container.hide(); // hide popup

                                    updateStyle(); // update styling options

                                    $(document).off('click', closePopup); // remove click handler
                                }
                        };

                        let timeout = null;
                        $container.find('input').on('input', () => { //change

                            if(timeout) { clearTimeout(timeout); }

                            timeout = setTimeout(() => {
                                updateStyle();
                            }, 200); // 1/5th of a second delay
                        });
                        $container.find('input').on('wheel', (event) => {

                            window.hWin.HEURIST4.util.stopEvent(event);

                            let $ele = $(event.target);
                            let cur_val = parseFloat($ele.val());

                            if(event.deltaY < 0){
                                $ele.val(cur_val + 0.01);
                            }else{
                                $ele.val(cur_val - 0.01);
                            }

                            $ele.trigger('input');
                        });

                        $container.position({
                            my: 'right top',
                            at: 'right bottom',
                            of: parent_span
                        });

                        $(document).on('click', closePopup);
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

                        let mapdoc_id = ele.attr('data-mapdoc');
                        let rec_id = ele.attr('data-recid');

                        // Get bounds
                        let bounds = null;
                        let zooms = null;
                        if(rec_id > 0 && (mapdoc_id>=0 || mapdoc_id=='temp')){ // layer

                            let layer_rec = mapDocuments.getLayer(mapdoc_id, recid);
                            bounds = (layer_rec['layer']).getBounds();
                        }else if(mapdoc_id > 0){ // map document
                            bounds = mapDocuments.getMapDocumentBounds(mapdoc_id);
                        }

                        // Get zoom details
                        if(bounds){
                            zooms = options.mapwidget.mapping('getBoundsZooms', bounds); // possible zoom level and current zoom
                        }

                        // Update zoom icon
                        let $zoom = ele.find('.zoom-to-extent');
                        $zoom.removeClass('ui-icon-zoom ui-icon-zoomin ui-icon-zoomout');
                        if(zooms){

                            zooms = (zooms.zoom > zooms.cur_zoom) ? 'ui-icon-zoomin' : zooms;

                            zooms = (window.hWin.HEURIST4.util.isJSON(zooms) && zooms.zoom < zooms.cur_zoom) ? 
                                        'ui-icon-zoomout' : zooms;

                            zooms = (window.hWin.HEURIST4.util.isJSON(zooms)) ? 'ui-icon-zoom' : zooms;

                        }else{
                            zooms = 'ui-icon-zoom';
                        }
                        $zoom.addClass(zooms);

                        ele.css({'display':'inline-block'});//.css('visibility','visible');
                    }
            }
            
            $(parent_span).on('mouseenter',
                _onmouseenter
            ).on('mouseleave',
                _onmouseexit
            );

        }
    }
    
    function _onExpand(){
        isExpanded = true;
        
        btn_expand.hide();
        btn_collapse.show();
        options.container.css({'width':keepWidth,'overflow-y':'auto',padding:'4px'}).resizable( "enable" ).show();
        that.updatePanelVisibility(); //refresh
        that.setHeight();
    }

    function _onCollapse(){
        isExpanded = false;

        keepWidth = options.container.width();
        btn_expand.show();
        btn_collapse.hide();

        let width_dims = (navigator.userAgent.toLowerCase().indexOf('firefox') >= 0 ? 76 : 70) + 'px';
        let height_dims = (navigator.userAgent.toLowerCase().indexOf('firefox') >= 0 ? 28 : 22) + 'px';
        options.container.css({'width':width_dims,'height':height_dims,'overflow-y':'hidden','overflow-x':'hidden',padding:'1px'})
            .resizable( "disable" );
            
        if(options.is_ui_main){
            options.container.hide();
        }
            
        that.updatePanelVisibility();
    }

    

    //public members
    let that = {
        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        /*
        // data - layer name and reference
        // 
        defineContent: function(groupID, data){
            //find group div
            let grp_div = options.container.find('.svs-acordeon[grpid="'+groupID+'"]');
            //define new
            _defineContent(groupID, data, grp_div.find('.ui-accordion-content'));
        },
        */
        setHeight: function( val ){

            if(isExpanded){

                if(val>0) maxHeight  = val;

                let ele = options.container.find('.svs-acordeon:visible');
                let h = 20;
                $(ele).each(function(idx,item){h=h+$(item).height()});

                options.container.height( Math.min(h, maxHeight) );

            }else{

                let width_dims = (navigator.userAgent.toLowerCase().indexOf('firefox') >= 0 ? 76 : 70) + 'px';
                let height_dims = (navigator.userAgent.toLowerCase().indexOf('firefox') >= 0 ? 28 : 22) + 'px';

                options.container.height( height_dims );
                options.container.width( width_dims );
            }

        },
        
        //
        // params
        // either json: {width:200,open:1|0,basemaps:0|1|-1,mapdocs: ,  }   -1 hidden,0-close,1-open
        // or   off,width(numeric),-basemaps,mapdocs,search  
        //
        updatePanelVisibility: function(params)
        {
            if(params){
                if(Array.isArray(params)){
                    if(params.indexOf('all')>=0){ //default
                        params = {basemaps:0,mapdocs:0,search:1};
                    }else{
                        let defWidth = 250;
                        $.each(params,function(i,item){
                           if(window.hWin.HEURIST4.util.isNumber(item) && item>0){
                               defWidth = item;
                               return false;
                           } 
                        });
                        params = {basemaps:params.indexOf('-basemaps')<0?(params.indexOf('basemaps')<0?-1:1):0,
                                  mapdocs:params.indexOf('-mapdocs')<0?(params.indexOf('mapdocs')<0?-1:1):0,
                                  search:params.indexOf('-search')<0?(params.indexOf('search')<0?-1:1):0,
                                  tempmap:params.indexOf('-tempmap')<0?(params.indexOf('tempmap')<0?-1:1):0,
                                  open:params.indexOf('off')<0?1:0,
                                  width:defWidth};
                    }
                }
                
                keepWidth  = params.width>0?params.width:250;
                isExpanded = window.hWin.HEURIST4.util.istrue(params.open, true);
                
                options.visible_panels = params; //array of visible panels
                
                if(isExpanded){
                    _onExpand();
                }else{
                    _onCollapse();
                    keepWidth  = params.width>0?params.width:250;
                }
                
                return;
            }
            
            if(!options.visible_panels) options.visible_panels = {basemaps:0,mapdocs:0,search:1};//['all'];
            
            function __set(val){
                let is_visible = true;//(options.visible_panels.indexOf('all')>=0 || options.visible_panels.indexOf(val)>=0);
                let ele = options.container.find('.svs-acordeon[grpid="'+val+'"]');
                let is_collapsed;
                if(val=='tempmap'){
                    is_visible = options.hasTempMap;
                    is_collapsed = !window.hWin.HEURIST4.util.istrue(options.visible_panels[val], true);
                }else{
                    val = options.visible_panels[val];
                    is_collapsed = !window.hWin.HEURIST4.util.istrue(val, false);
                    is_visible = (val!=-1);
                }
                
                if(is_visible){
                    ele.show();
                    ele.accordion("option", "active", is_collapsed?false:0);
                }else{
                    ele.hide();
                }
            }
            
            if(isExpanded){
                __set('basemaps');            
                __set('search');            
                __set('mapdocs'); 
                __set('tempmap'); 
                options.container.find('.ui-resizable-handle').show();
            }else{
                options.container.children('div').hide(); 
            }       
        
            //__set('onedoc');            
        },
        
        //
        //
        //
        populateMapDocuments: function($select){

            if($select == null || $select.length == 0){
                return;
            }

            if($select.hSelect('instance') != undefined){
                $select.hSelect('destroy');
            }

            $select.empty();

            mapdoc_select = $select;

            mapDocuments.loadMapDocuments(null, function(resdata){
                
                window.hWin.HEURIST4.ui.addoption(mapdoc_select[0], '', 'None');

                resdata.each2(function(id, record){
                    window.hWin.HEURIST4.ui.addoption(mapdoc_select[0], record['rec_ID'], record['rec_Title']);
                });

                mapdoc_select.hSelect({
                    'open': function(event, ui){ // get active mapdoc(s), highlight visible map doc(s)

                        let selected_opts = Object.values(mapdoc_visible);
                        let $menu_items = mapdoc_select.hSelect('menuWidget').find('li');
                        for(let i = 0; i < $menu_items.length; i++){

                            let title = $($menu_items[i]).find('div').text();

                            if(selected_opts.indexOf(title) > -1){
                                $($menu_items[i]).addClass('activated-mapdoc');
                            }else{
                                $($menu_items[i]).removeClass('activated-mapdoc');
                            }
                        }

                        if(selected_opts.length < 2){
                            mapdoc_select.removeClass('multi-select');
                        }else{
                            mapdoc_select.addClass('multi-select');
                        }

                        mapdoc_select.hSelect('menuWidget').css('width', '250px');
                        mapdoc_select.hSelect('menuWidget').find('div.ui-menu-item-wrapper').addClass('truncate');
                    },
                    /*'change': function(event, ui){

                    },*/
                    'close': function(event, ui){ // check if we need to keep it open, only if multi-select

                        let $selected_opt = $(event.currentTarget).is('li') ? $(event.currentTarget) : $(event.currentTarget).parent();

                        if(mapdoc_select.hasClass('multi-select') && $selected_opt.hasClass('ui-menu-item')){
                            mapdoc_select.hSelect('open');
                        }
                    },
                    'select': function(event, ui){

                        let $selected_opt = $(event.currentTarget).is('li') ? $(event.currentTarget) : $(event.currentTarget).parent();

                        if(ui.item.value == ''){ //current result set - all mapdocs are off

                            mapdoc_select.hSelect('menuWidget').find('.activated-mapdoc').removeClass('activated-mapdoc');

                            that.toggleMapDocument(Object.keys(mapdoc_visible).join(','), false); //hide all other mapdocs
                            mapdoc_visible = {};

                            mapdoc_select.removeClass('multi-select');
                        }else if($selected_opt.hasClass('activated-mapdoc')){ // 
                            //on the second click - switch off current map document 
                            /* 2022-07-29
                            $selected_opt.removeClass('activated-mapdoc');
                            that.toggleMapDocument(mapdoc_select.val(), false);
                            */
                        }else{

                            if(mapdoc_select.hasClass('multi-select')){ // enable selected option
                                $selected_opt.addClass('activated-mapdoc');
                                that.toggleMapDocument(mapdoc_select.val(), true);
                            }else{ // disable previous selection, enabled current selection

                                mapdoc_select.hSelect('menuWidget').find('.activated-mapdoc').removeClass('activated-mapdoc');

                                that.toggleMapDocument(Object.keys(mapdoc_visible).join(','), false);
                                mapdoc_visible = {};

                                $selected_opt.addClass('activated-mapdoc');
                                that.toggleMapDocument(mapdoc_select.val(), true);
                            }
                        }

                        let selected_opts = Object.values(mapdoc_visible);
                        selected_opts = selected_opts.length == 0 ? ['Current result set'] : selected_opts;
                        mapdoc_select.hSelect('widget').attr('title', selected_opts.join(', '));

                        if(selected_opts.length == 1){
                            mapdoc_select.hSelect('widget').find('span.ui-selectmenu-text').text(selected_opts[0]);
                        }else{
                            mapdoc_select.hSelect('widget').find('span.ui-selectmenu-text').text(selected_opts.length+' documents displayed');
                        }
                    }
                });

                mapdoc_select.hSelect('widget').find('span.ui-selectmenu-text').text('Current result set');
            });
            
            options.mapwidget.mapping('adjustToolbarHeight');
        },

        //
        // select map document in treeview - it makes it visible or triggers loading of content
        //
        toggleMapDocument: function(mapdoc_id, show_doc=true){

            mapdoc_id = mapdoc_id.split(',');
            
            if(window.hWin.HEURIST4.util.isFunction($('body').fancytree)){
            
                let tree = $.ui.fancytree.getTree( mapdoc_treeview );
                let selected = 0;

                tree.visit(function(node){
                    if( window.hWin.HEURIST4.util.findArrayIndex(node.key, mapdoc_id)>=0){
                        node.setSelected( show_doc );
                        selected++;
                        if(selected==mapdoc_id.length) return false;
                    }
                });
            }
        },
        
        //
        // adds new mapdoc record
        //
        createNewMapDocument: function(event){
            _createNewMapDocument(event);
        },

        //
        // creates virtual mapspace from given layer_ids
        //
        createVirtualMapDocument: function(layer_ids, dfd_top){
            
            options.hasTempMap = false;
            let dfd = new $.Deferred();
            mapDocuments.createVirtualMapDocument(layer_ids, dfd);
            
            $.when( dfd.promise() ).done(
                function(data){
                    options.hasTempMap = true;
                    //refresh list of tempmap
                    let grp_div = options.container.find('.svs-acordeon[grpid="tempmap"]');
                    _defineContent('tempmap', null, grp_div.find('.ui-accordion-content'));
                    that.updatePanelVisibility();
                    that.setHeight();
                    
                    setTimeout(function(){
                        mapDocuments.zoomToMapDocument('temp');
                        
                        if(dfd_top) dfd_top.resolve();
                    },500);
                }
            );
            
        },

        // 
        // returns default style for mapdoc or layer
        //
        getSymbology: function(mapdoc_id, layer_id) {
            return mapDocuments.getSymbology( mapdoc_id, layer_id );
        },
        
        //
        //  get all layers and datasources of document
        //
        getMapDocumentRecordset: function(mapdoc_id){
            return mapDocuments.getMapDocumentRecordset(mapdoc_id);
        },
        
        //
        //
        //
        getMapDocuments: function(){
            return mapDocuments;
        },

        //
        // returns list of mapdocuments ids
        // mode - all|loaded|visible
        // 
        getMapDocumentsIds: function( mode ) {
            let res = [];
            let tree = $.ui.fancytree.getTree( mapdoc_treeview );
            tree.visit(function(node){
                if(node.type=='mapdocument'){
                    if((mode=='visible'&& node.isSelected()) ||
                       (mode=='loaded'&& node.hasChildren()) || (mode=='all'))
                    {
                        res.push(node.key);
                    }
                }
            });
            return res;
        },
        
        //
        // adds new layer to search results mapdoc (index=0)
        // data - recordset, heurist query or json
        // it sends request to server and obtain geojson to be passed to mapping.addGeoJson
        //
        addSearchResult: function( data, dataset_options )
        {
            let record = mapDocuments.addSearchResult( 0, data, dataset_options );
            
            //refresh search results
            _refreshSearchContent(); 
            
            return record;
        },

        //
        // converts given recordset to geojson and pass it to to mapping.addGeoJson
        //
        addRecordSet: function(recset, dataset_name) {
            
            let record = mapDocuments.addRecordSet( 0, recset, dataset_name );
            
            //refresh search results 
            _refreshSearchContent(); 
            
            return record;
            
        },
        
        //
        //  add entries into search result mapdocument (id=0) and refresh legend
        // (see HMapLayer2._addQueryLayer)        
        //
        addLayerRecords: function( layers_ids ) {
            
            mapDocuments.addLayerRecords( 0, layers_ids, function(){
                _refreshSearchContent();     
            } );
            
        },
        
        //
        // show/hide datasets
        // visibility - true or false to show/hide entire layer or array of record ids 
        //
        setLayersVisibility: function (mapdoc_ID, _selection, visibility_set){
            
            if(!(mapdoc_ID>=0)) mapdoc_ID = 0;
            
            function __setVis(recID, record){
                
                    let layer_rec = mapDocuments.getLayer(mapdoc_ID, recID);
                    if(layer_rec){
                        let curr_visible = (layer_rec['layer']).isVisible();
                        if(visibility_set!==curr_visible){
                            (layer_rec['layer']).setVisibility( visibility_set );  
                        }
                        
                    } 
            }
            
            if(Array.isArray(_selection)){
                for(let i=0; i<_selection.length; i++){
                     __setVis(_selection[i]);
                }
            }else{
                _selection.each(__setVis);
            }
            _refreshSearchContent();
        },

        //
        //
        // returns leaflet layer_id by database mapdoc and name 
        // dataset = {mapdoc_id:, dataset_name:, dataset_id: }
        //
        getLayerNativeId: function( dataset ){

            if(dataset){
                if(dataset.native_id>=0){
                    return dataset.native_id;
                
                }else if(dataset.mapdoc_id>=0){
                    let layer = mapDocuments.getLayerByName(dataset.mapdoc_id, dataset.dataset_name, dataset.dataset_id);
                    if(layer){
                        return layer.getNativeId();
                    }
                }
            }
            return 0;
            
        },
        
        //
        // returns layer OR map document (if recid is not defined)
        //
        getLayer: function( mapdoc_id, recid ){
            return mapDocuments.getLayer(mapdoc_id, recid);
        },
        
        
        //
        //
        //
        filterListBaseMap: function (visible_basemaps){
            if(options.visible_basemaps != visible_basemaps){
                options.visible_basemaps = visible_basemaps;
                let grp_div = options.container.find('.svs-acordeon[grpid="basemaps"]');
                _defineContent('basemaps', null, grp_div.find('.ui-accordion-content'));
                that.setHeight();
            }
        },
        
        //
        //
        //
        filterListMapDocuments: function(visible_mapdocuments){
            if(window.hWin.HEURIST4.util.isFunction($('body').fancytree) 
                && ((visible_mapdocuments===true)  //force
                || options.visible_mapdocuments != visible_mapdocuments)){
                
                    if(visible_mapdocuments===true){
                        visible_mapdocuments = options.visible_mapdocuments;
                    }else{
                        options.visible_mapdocuments = visible_mapdocuments;
                    }
                        
        
                let mapdoc_ids = visible_mapdocuments?visible_mapdocuments.split(';'):[];
                
                let tree = $.ui.fancytree.getTree( mapdoc_treeview );
                if(mapdoc_ids && mapdoc_ids.length>0){
                    tree.filterBranches(function(node){  //filterNodes
                        let res = mapdoc_ids.indexOf(node.key)>=0;
                        if(res){
                            $(node.li).show();    
                        }else{
                            $(node.li).hide()
                        }
                        return res;
                    }, {mode: "hide"});
                }else{
                    tree.clearFilter();                
                }
                
                that.setHeight();
            }
        },
        
        //
        // switch base map layer
        //
        loadBaseMap: function( e ){

            let idx = 0;

            if(window.hWin.HEURIST4.util.isNumber(e) && e>=0){
                idx = e;
            }else if(typeof e == 'string'){  //find by map name
            
                if(e=='_NONE'){
                    e = 'None';
                }
                idx = options.container.find('input[data-mapid="'+e+'"]').attr('data-mapindex');    
                
            }else{
                idx = $(e.target).attr('data-mapindex');
            }

            options.mapwidget.mapping('loadBaseMap', idx);
            if(idx>=0){
                options.container.find('input[data-mapindex="'+idx+'"]').prop('checked',true);
                options.container.find('input[data-mapindex="'+idx+'"]').parent().after(options.container.find('#basemap_filter_btns'));
            }else{
                options.container.find('input[data-mapindex]').prop('checked',false); //mark off all
            }
        },
        
        //
        //
        //
        toggle: function(){
            
            if(isExpanded){
                _onCollapse();
            }else{
                _onExpand();
            }
        },
        
        //
        // returns html content for current active map document (used in printable version)
        //
        getActiveMapDocumentLegend(){
        
            let tree = $.ui.fancytree.getTree( mapdoc_treeview );
            
            let res = null;

            tree.visit(function(node){
                    if(node.type=='mapdocument' && node.isSelected()){
                        res = $(node.li).html();
                        return false;
                    }
                });
            return res;
        },
        //
        // update layer status for mapdocument
        //
        updateLayerStatus: function(layer_id, status){

            if(DT_LEGEND_OUT_ZOOM>0){

                let res = that.getMapDocumentsIds('visible');
                if(res && res.length>0){
                    let mapdoc_id = res[0];
                    
                    let layer_rec = mapDocuments.getLayer(mapdoc_id, layer_id);
                    if(!layer_rec || !layer_rec['d']) return;
                    let val = layer_rec['d'][DT_LEGEND_OUT_ZOOM];
                    
                    if(val==TRM_LEGEND_OUT_ZOOM_HIDDEN || val==TRM_LEGEND_OUT_ZOOM_DISABLED){

                        //find node
                        let tree = $.ui.fancytree.getTree( mapdoc_treeview );
                        tree.visit(function(node){
                                if(node.type=='layer' && node.key==layer_id && node.data.mapdoc_id===mapdoc_id){
        
                                    let ele = $(node.li).find('.fancytree-title');
                                    
                                    if(val==TRM_LEGEND_OUT_ZOOM_DISABLED){
                                        if(status=='out'){
                                            ele.addClass('ui-state-disabled');
                                        }else{
                                            ele.removeClass('ui-state-disabled');    
                                        }
                                    }else{
                                        // show/hide node
                                        if(status=='out'){
                                            $(node.li).hide()
                                        }else{
                                            $(node.li).show();    
                                        }
                                    }
                                    return false;
                                }
                            });
                    }
                }
            }

        }
        
    }

    _init( _options );
    return that;  //returns object
};

        
        