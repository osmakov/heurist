/**


@todo Later?
KML is loaded as a single collection (without access to particular feature) Need to use our kml parser to parse and load each kml' placemark individually
Altough our kml parser supports Placemarks only. It means no external links, image overlays and other complex kml features. 
Selector map tool (rectangle and polygon/lasso)
Editor map tool (to replace mapDraw.js based on gmap)
Thematic mapping
 
* options for preferences
* markercluster on/off 
*     showCoverageOnHover, zoomToBoundsOnClick, maxClusterRadius(80px)
* Derive map location of non-geolocalised entities from connected Places
* Map select tools (by click, in rect, in shape)
* Default symbology (for search result)
* Default base layer
* 
* mapping base widget
*
* It manipulates with map component (google or leaflet is implemented in descendants)
*      all native mathods for mapping must be defined here
* 
* mapManager.js is UI with list (tree) of mapdocuments, result sets and base maps
* timeline.js for Vis timeline 
* mapDocument.js maintains map document list and their layers
* mapLayer2.js loads data for particular layer
* 
* 
* options:
* element_layout
* element_map    #map by default
* element_timeline  #timeline by default 
* 
* notimeline - hide timeline (map only)
* nomap - timeline only
* map_rollover - show title of market as tooltip
* map_popup_mode - show map info as standard map control, in popup dialog or supress (standard,dialog,none)
* 
* callback events:
* onselect
* onlayerstatus - arguments datasetid and status (visible,hidden,out,error)see mapManager treeview select event
* oninit
* style: default style for current query
* 
* 
* init (former _load)
* printMap
* 
* 
*     
*   loadBaseMap
*   defineCRS
    addSearchResult - loads geojson data based on heurist query, recordset or json to current search (see addGeoJson)
    addRecordSet - converts recordset to geojson
    addLayerRecords - add layer records to search result mapdocument
    addGeoJson - adds geojson layer to map, apply style and trigger timeline update
    addTileLayer - adds image tile layer to map
    addImageOverlay - adds image overlay to map
    updateTimelineData - adds/replaces timeline layer_data in this.timeline_items and triggers timelineRefresh
    applyStyle - applies style for given top layer
    getStyle
    
    setFeatureSelection - triggers redraw for path and polygones (assigns styler function)  and creates highlight circles for markers
    setFeatureVisibility - applies visibility for given set of heurist recIds (filter from timeline and filter by zoom)
    zoomToSelection
    zoomToLayer
    setLayerVisibility - show hide entire layer
    setVisibilityAndZoom - show susbset of given recordset and zoom 
    convertZoomToNative - Converts zoom in km to nativemap zoom (0-22)
    
    _onLayerClick - map layer (shape) on click event handler - highlight selection on timeline and map, opens popup
    _clearHighlightedMarkers - removes special "highlight" selection circle markers from map
    setStyleDefaultValues - assigns default values for style (size,color and marker type)
    _createMarkerIcon - creates marker icon for url(image) and fonticon (divicon)
    _stylerForPoly - returns style for every path and polygone, either individual feature style of parent layer style.
    _getMarkersByRecordID - returns markers by heurist ids (for filter and highlight)
    
    
    DRAW Methods
    drawLoadGeometry - detects format and calls the appropriate method to load geo data for drawing (WKT, simple points or JSON)
    drawLoadSimplePoints - converts coordinate pairs (points) to WKT and loads for drawing
    drawLoadWKT - parses WKT, converts to JSON and calls drawLoadJson to adds to drawItems
    drawLoadJson - adds geojson to drawnItems 
    
    drawGetWkt - Gets drawn items as json and converts to WKT string
    drawGetJson -  returns current drawn items as geojson
    
    drawClearAll - remove all drawn items fromm map 
    drawZoomTo - zoom to drawn items
    
    
* Events: 
* onInitComplete - triggers options.oninit event handler
* 
* 
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
/* global L, HMapManager, hMapDocument, accessToken_MapTiles, simplePointsToWKT, parseWKT, stringifyMultiWKT, cheapRuler, hexToFilter */

$.widget( "heurist.mapping", {

    // default options
    options: {
        
        element_layout: null,
        element_map: 'map',
        element_timeline: 'timeline',
 
        //various layout and behaviour settings
        // they are assigned in map.php onPageInit from url parameters
        // which in turn can be set in app_timemap.js
        layout_params:{}, 
        
        // callbacks
        onselect: null,
        onlayerstatus:null,
        oninit: null,
        ondraw_addstart:null,
        ondraw_editstart:null,
        ondrawend:null,
        ondraw_save_on_addition: false, //if true save digitizing at once (on addition of geometry to map)
        drawMode: null,
        
        isEditAllowed: true,
        isPublished: false,
        
        map_rollover: false,
        map_popup_mode: 'standard', //,'dialog'
        
        useWidgetZoomSettingsOnly: false, //overwrite widget min,max zoom setting by mapdocument values
        zoomToPointInKM: 5,  //is set either from map documet DT_ZOOM_KM_POINT or from url parameters
        zoomMaxInKM: 0,
        zoomMinInKM: 0,
        
        default_style:null,
        default_selection_style:null,
        
        map_margins: {padding: L.point(50, 50)},

        recviewer_images: 1  // show images in record viewer; 0 - show all images, 1 - no linked media, 2 - no images
    },
    
    /* expremental 
    isHamburgIslamicImpire: false,
    hie_places_with_events: [],
    hie_places_wo_events_style: null,
    */
    
    //reference to google or leaflet map
    
    //main elements
    nativemap: null,     //map container
    vistimeline: null,   //timeline container 
    mapManager: null,    //legend
    
    is_crs_simple: false,
    crs_current: '',
    
    basemap_layer_maxzoom: 7, //required for conversion pixels to coordinates for Cortesian CRS
    
    _inited_mapdocs: false,
    _inited_basemap: false,
    
    available_maxzooms: [], //name of restrictions(widget, basemap, layer id, mapdoc id) => max zoom level, min zoom level
    available_minzooms: [], 

    //record from search results mapdoc
    current_query_layer:null, 
    
    //controls
    map_legend: null,
    map_zoom: null,
    map_bookmark: null,
    map_geocoder: null,
    map_print: null,
    map_publish: null,
    map_draw: null,
    map_help: null,
    map_scale: null,
    
    printScaleMode: 'none', //position of scale control for printing out
    printLegend: false,
    
    //popup element
    main_popup: null,
    mapPopUpTemplate: null,  //name of popup template (from map params)
    
    //addition and edit object on map
    // full - full edit mode
    // image - 
    // filter - to define filter extent 
    currentDrawMode:'none',  //full,image,filter 
    
    //
    drawnItems: null,
    //default draw style
    map_draw_style: {color: '#3388ff',
             weight: 4,
             opacity: 0.5,
             fill: true, //fill: false for polyline
             fillColor: null, //same as color by default
             fillOpacity: 0.2},
    
    selection_style: null,
    need_zoom_on_selection: true,

    //storages
    all_layers: {},    // array of all loaded TOP layers by leaflet id   
    all_clusters: {},  // markerclusters
    all_markers: {},   // markers for top layer if it is clustered
    
    timeline_items: {},
    timeline_groups: [], 
    
    nomap: false,
    notimeline: false,
    //current status
    is_timeline_disabled:0,
    is_map_disabled:0, 
    timeline_height: 0,
    
    selected_rec_ids:[],
    highlightedMarkers:[],

    myIconRectypes:{},  //storage for rectype icons by color and rectype id

    //  settings
    isMarkerClusterEnabled: true,
    markerClusterGridSize: 50,
    markerClusterMaxZoom: 18,
    markerClusterMaxSpider: 5,
    isEditAllowed: true,
    
    
    //base maps
    basemaplayer_filter: null,
    basemaplayer_name: null,
    basemaplayer: null,
    basemap_providers: [
    {name:'OpenStreetMap'},
    //{name:'OpenPtMap'},
    {name:'OpenTopoMap'},
    /* account disableb - it is a risk of cost overruns
    {name:'MapBox.StreetMap', options:{accessToken: accessToken_MapBox}},
    {name:'MapBox.Satellite', options:{accessToken: accessToken_MapBox}},
    {name:'MapBox.Combined', options:{accessToken: accessToken_MapBox}},
    {name:'MapBox.Relief', options:{accessToken: accessToken_MapBox}},
    //{name:'MapBox.AncientWorld', options:{accessToken: accessToken_MapBox}},
    */
    {name:'Esri.WorldStreetMap'},
    {name:'Esri.WorldTopoMap'},
    {name:'Esri.WorldImagery'},
    //{name:'Esri.WorldTerrain'},
    {name:'Esri.WorldShadedRelief'},
    {name:'Stamen.Toner'},
    {name:'Stamen.TonerLite'},
    {name:'Stamen.Terrain'},  //terrain with labels
    {name:'Stamen.TerrainBackground'}, //terrain w/o labels
    //{name:'Stamen.TopOSMRelief'},    // doesn't work
    //{name:'Stamen.TopOSMFeatures'},  // doesn't work
    {name:'Stamen.Watercolor'},
    //{name:'OpenWeatherMap'}
    {name:'Esri.NatGeoWorldMap'},
    {name:'Esri.WorldGrayCanvas'},
    {name:'MapTilesAPI.OSMEnglish', options:{accessToken: accessToken_MapTiles}}, 
    {name:'DARE.RomanEmpire', url: 'https://dh.gu.se/tiles/imperium/{z}/{x}/{y}.png', options:{
        attribution: '&copy; <a href="https://dh.gu.se/dare/" target="_blank">Digital Atlas of the Roman Empire (DARE)</a> '
            + 'by <a href="https://www.gu.se/digital-humaniora" target="_blank"> Johan Åhlfeldt, Centre for Digital Humanities, University of Gothenburg</a> '
            + '(Licensed under <a href="https://creativecommons.org/licenses/by/4.0/">CC-BY-4.0</a>)',
        minZoom: 4,
        maxZoom: 11
    }},
    {name:'GeoportailFrance.plan'},
    {name:'GeoportailFrance.parcels'},
    {name:'GeoportailFrance.orthos'},
    {name:'None'}
    ],

    // ---------------    
    // the widget's constructor
    //
    _create: function() {

        let that = this;

        this.element
        // prevent double click to select text
        .disableSelection();

        // Sets up element to apply the ui-state-focus class on focus.
        //this._focusable($element);   

        this._refresh();

    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
    
        let that = this;    
        //1. INIT LAYOUT
        
        // Layout options
        let layout_opts =  {
            applyDefaultStyles: true,
            togglerContent_open:    '<div class="ui-icon"></div>',
            togglerContent_closed:  '<div class="ui-icon"></div>',
            onresize_end: function(){
                //global 
                that._adjustLegendHeight();
            }
            
        };

        
        let is_ui_main = this.options.layout_params && this.options.layout_params['ui_main'];

        // Setting layout
        if(this.options.element_layout)
        {
            layout_opts.center__minHeight = 0;
            layout_opts.center__minWidth = 200;
            layout_opts.north__size = 0;//30;
            layout_opts.north__spacing_open = 0;

            layout_opts.south__onresize_end = function() {
                that._adjustLegendHeight();
            };
        
            if(is_ui_main){ //show toolbar as north panel
                
                window.hWin.HAPI4.LayoutMgr.cardinalPanel('sizePane',
                                    ['east', (top ?  '75%' : window.innerWidth)]);
                
                layout_opts.north__size = 36;
            }

            this.mylayout = $(this.options.element_layout).layout(layout_opts);
        }

        
        //2. INIT MAP
        let map_element_id = 'map';
        if(this.options.element_map && this.options.element_map.indexOf('#')==0){
            map_element_id = this.options.element_map.substr(1);
        }
        
        $('#'+map_element_id).css('padding',0); //reset padding otherwise layout set it to 10px
        
        let map_options = {zoomControl:false, tb_del:true, worldCopyJump: false};
        
        this.nativemap = L.map( map_element_id,  map_options)
            .on('load', function(){ } );

        this.nativemap.on('zoomend', function (e) {
            if(that.mapManager){
                let md = that.mapManager.getMapDocuments();
                if(md) {
                    let currZoom = that.nativemap.getZoom();
                    md.updateLayerVisibility(currZoom);   
                }
                
            }
        });
                        
        //3. INIT TIMELINE 
        //moved to updateLayout

        //LONDON this.nativemap.setView([51.505, -0.09], 13); //@todo change to bookmarks
        this.nativemap.setView([20, 20], 1); //@todo change to bookmarks
        
        //4. INIT CONTROLS
        if(this.main_popup==null) this.main_popup = L.popup({maxWidth: 'auto'});
        
        //zoom plugin
        this.map_zoom = L.control.zoom({ position: 'topleft' });//.addTo( this.nativemap );
        
        //legend contains mapManager
        this.map_legend = L.control.manager({ position: 'topright' }).addTo( this.nativemap );

        //map scale
        this.map_scale = L.control.scale({ position: 'bottomleft' }).addTo( this.nativemap );
        $(this.map_scale._container).css({'margin-left': '20px', 'margin-bottom': '20px'});
        
        //content for legend
        this.mapManager = new HMapManager(
            { container:this.map_legend._container, 
              mapwidget:this.element, 
              is_ui_main:is_ui_main
              ,visible_basemaps: this.options.layout_params?this.options.layout_params['basemaps']:null
              ,visible_mapdocuments: this.options.layout_params?this.options.layout_params['mapdocuments']:null
            });
        
        this.updateLayout();

        $(window).on('onresize',function(){
            that.adjustToolbarHeight();    
        });
        
        this.adjustToolbarHeight();
        this._adjustLegendHeight();
        
    },
    
    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    _setOptions: function( ) {

                
        this._superApply( arguments );
        
        if(arguments && ((arguments[0] && arguments[0]['map_margins']) || arguments['map_margins']) ){
            if(Array.isArray(this.options.map_margins.paddingTopLeft) && !(this.options.map_margins.paddingTopLeft instanceof L.Point)){
                this.options.map_margins.paddingTopLeft = L.point(this.options.map_margins.paddingTopLeft);
            }
            if(Array.isArray(this.options.map_margins.paddingBottomRight) && !(this.options.map_margins.paddingBottomRight instanceof L.Point)){
                this.options.map_margins.paddingBottomRight = L.point(this.options.map_margins.paddingBottomRight);
            }
        }
        
        if(arguments && ((arguments[0] && arguments[0]['layout_params']) || arguments['layout_params']) ){
            this.updateLayout();
        }
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
        // remove generated elements
        //this.select_rectype.remove();
    },
    
    //
    //
    //
    setMapMargins: function(value){
        this._setOptions({map_margins:value});
    },
    
    //-------
    adjustToolbarHeight: function(){
        
        
        let is_ui_main = this.options.layout_params && this.options.layout_params['ui_main'];
        let toolbar = $('#mapToolbarContentDiv');
        
        if(is_ui_main && toolbar.length>0){
        
            
            let h = toolbar.height() + 5;

            if($('#mapToolbarDiv').height()!=h){
                this.mylayout.sizePane('north', h);
            }
        
        }

    },
    //-------
    _adjustLegendHeight: function(){
        let ele = $('#map');
        if(this.mapManager) this.mapManager.setHeight(ele.height()-50); //adjust legend height    
    //invalidateSize
    },
    
    invalidateSize: function(){
        if(this.nativemap) this.nativemap.invalidateSize();
    },
    
    getLeaflet: function(){
        return L;
    },  
    
    //that.onInitComplete();
    //
    // triggers options.oninit event handler
    //    it is invoked on completion of HMapManager initialization - map is inited and all mapdocuments are loaded
    //
    onInitComplete:function(mode_complete){

        if(mode_complete=='mapdocs'){  //mapDocuments.loadMapDocuments is completed
            this._inited_mapdocs = true;
        }else if(mode_complete=='basemap'){ //basemap layer has been added and loaded
            this._inited_basemap = true;
        }
            
        if(window.hWin.HEURIST4.util.isFunction(this.options.oninit) && this._inited_mapdocs && this._inited_basemap){
                this.options.oninit.call(this, this.element);
        }
    },
    
    

    //---------------- 
    //
    // returns mapManager object
    //
    getMapManager: function(){
        return this.mapManager;
    },
    
    //--------------------
    
    //
    // css filter for base map
    // 
    setBaseMapFilter: function(cfg){
        if(typeof cfg === 'string'){
            this.basemaplayer_filter = window.hWin.HEURIST4.util.isJSON(cfg);
        }else{
            this.basemaplayer_filter = cfg;    
        }
        if(!this.basemaplayer_filter){
            this.basemaplayer_filter = null;
        }
        
        this.applyImageMapFilter('heurist-imageoverlay-basemap',this.basemaplayer_filter);
    },
    
    //
    //
    //
    applyImageMapFilter: function(layerClassName, image_filter=null)
    {
        let filter = '';
        if(image_filter==null){
            image_filter = this.basemaplayer_filter;
        }

        if(image_filter && $.isPlainObject(image_filter)){
            $.each(image_filter, function(key, val){
                filter = filter + key+'('+val+') ';
            });
        }

        let $img = null;

        if(layerClassName instanceof jQuery){
            $img = layerClassName;
        }else{
            $img = $(`.${layerClassName}`);
        }

        $img.css('filter', filter);
    },

    //
    //
    //
    getImageMapFilter: function(layerClassName){

        let $img = null;

        if(layerClassName instanceof jQuery){
            $img = layerClassName;
        }else{
            $img = $(`.${layerClassName}`);
        }

        let filter = $img.css('filter');

        if(!window.hWin.HEURIST4.util.isempty(filter)){

            let parts = filter.split(' ');
            let filter = {};

            for(let part of parts){

                let val = part.match(/\d+\.?\d*/);
                if(!val){ continue; }

                val = val[0];
                let prop = part.replace(`(${val})`, '');

                filter[prop] = val;
            }
        }else{
            filter = this.basemaplayer_filter;
        }

        return filter;
    },

    getBaseMapFilter: function(){
        return this.basemaplayer_filter;
    },

    
    //
    // loads as a base map Heurist Image Layer record (called from updateLayout)
    //
/*    
    loadBaseMapImage: function(record_id){
    
        if( record_id==0 || this.basemap_layer_id==record_id) return; //base map is not changed

        //continuousWorld
        this.basemap_layer = HMapLayer2({record_id:record_id, mapwidget:this.element});
        this.basemap_layer_id = record_id;
    
        var cnt = 0;
        var that = this;
        var interval = setInterval(function()
        {
            var id = that.basemap_layer.getNativeId();
            
            if(that.all_layers[id]){

                cnt = 50;
                var bounds2 = that.basemap_layer.getBounds();
                that.basemap_layer_maxzoom =  that.basemap_layer.getMaxZoomLevel();

                if(that.basemap_layer_maxzoom>0 && bounds2 && bounds2.isValid()){
                    if(that.currentDrawMode == 'none' || that.currentDrawMode == null ){
                        that.nativemap.setMaxBounds(bounds2); //to avoid pan out of extent
                    }
                    that.nativemap.fitBounds(bounds2); //initial zoom         
                }
                
                that.onInitComplete('basemap');

            }
            cnt++;
            if(cnt>=50){
                clearInterval(interval);
                interval = 0;
            }
            
        },200);
        
    },
*/    
    //
    //
    //
    getBaseMapProviders: function(basemap_id){
        return this.basemap_providers;
    },
    
    
    //
    //
    //
    getCurrentCRS: function(){
        return this.crs_current;
    },
    
    //
    //
    //
    defineCRS: function(CRS){

        if(CRS && CRS.indexOf(':')>0){
            CRS = CRS.replace(':','');
        }
        
        if(this.crs_current!=CRS){
            this.crs_current = CRS;

            if(CRS=='' || !L.CRS[CRS] || CRS=='EPSG4326'){
                //default L.CRS.EPSG3857
                CRS = 'EPSG3857';
            }

            
            this.nativemap.options.crs = L.CRS[CRS];
            
            if(CRS=='Simple'){        
                this.nativemap.setMinZoom(0);
                this.nativemap.setMaxZoom(7); //@todo - take from 
                this.nativemap.worldCopyJump = true;
                this.nativemap.options.maxBounds = null;
                this.nativemap.options.maxBoundsViscosity = 0;
                
                this.is_crs_simple = true;
                
                $(this.map_scale._container).hide();
            }else{
                this.is_crs_simple = false;
                this.nativemap.worldCopyJump = false;
                this.nativemap.setMinZoom(0);
                this.nativemap.setMaxZoom(20);
                
                $(this.map_scale._container).show();
            }
            
            
            //map.setView(center); //we need this, because after changing crs the center is shifted (as mentioned above probably it's an issue to)
            try{
                this.nativemap._resetView(this.nativemap.getCenter(), this.nativemap.getZoom(), true);                
            }catch(e){
                this.nativemap.options.crs = '';
                console.error(e);
                this.defineCRS('');
            }
            
        }
        
    },
    
    //
    // basemap_id index in mapprovider array OR provider name
    //
    loadBaseMap: function(basemap_id){

        let provider = this.basemap_providers[0]; //first by default
        if(window.hWin.HEURIST4.util.isNumber(basemap_id)){
            if(basemap_id>=0){
                provider = this.basemap_providers[basemap_id];    
            }else{
                provider = {name:'None'};
            }
        }else{
            //find by name
            $(this.basemap_providers).each(function(idx, item){
                if(item['name']==basemap_id){ 
                    provider = item;
                    return;        
                }
            });
        }
        
        if(this.basemaplayer_name!=provider['name']) {
            
            this.basemaplayer_name = provider['name'];
            
            if(this.basemaplayer!=null){ //remove previous
                this.basemaplayer.remove();
            }

            if(provider['name']=='None' || provider['name']=='_NONE'){
                //remove zoom restrictions for basemap
                this.defineMaxZoom('basemap', -1);
                this.defineMinZoom('basemap', -1);
                return;
            }
            
            
            if(provider['name']!=='None'){

                let bm_opts = provider['options'] || {};
                
                //it prevents continous/repeatative world
                let is_no_wrap = window.hWin.HAPI4.get_prefs('map_no_wrap');         
                if(is_no_wrap==1){
                    bm_opts.noWrap = true; 
                }
                
                bm_opts.className = 'heurist-imageoverlay-basemap';
                
                try{ // use leaflet-provider
                    this.basemaplayer = L.tileLayer.provider(provider['name'], bm_opts)
                        .addTo(this.nativemap);

                }catch(e){

                    try{ // attempt without leaflet-provider

                        if(provider && !window.hWin.HEURIST4.util.isempty(provider['url'])){
                            
                            this.basemaplayer = L.tileLayer(provider['url'], bm_opts).addTo(this.nativemap);
                        }else{
                            throw e;
                        }

                    }catch(e){
                        // display error
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: 'We were unable to load your selected base map.<br>'
                                    +'If this problem persists, please report this through the bug reporter under Help at the top right of the main screen or,<br>'
                                    +'via email directly to support@heuristnetwork.org so we can fix this quickly.<br><br>'
                                    +'Base map values:<br>'
                                    +`Base map id: ${basemap_id}<br>`
                                    +`Base map name: ${(provider['name'] != '' ? provider['name'] : 'missing')}`,
                            error_title: 'Unable to load basemap'
                        });

                        return;
                    }
                }
                
                //to avoid pan out of extent
                if(is_no_wrap){
                    let sw = L.latLng(-100, -190),
                        ne = L.latLng(100, 190);
                    let bbox2 = L.latLngBounds(sw, ne);             
                    
                    this.nativemap.options.maxBounds = bbox2;
                    this.nativemap.options.maxBoundsViscosity = 1;
                }
                
                this.basemaplayer.bringToBack(); // ensure basemap is below all map documents
                
                if(this.basemaplayer_filter){
                    this.applyImageMapFilter('heurist-imageoverlay-basemap', this.basemaplayer_filter);
                }

                let layer_maxZoom = (this.basemaplayer['options'] && this.basemaplayer['options']['maxZoom']) ? 
                                        this.basemaplayer['options']['maxZoom'] : 19;
                
                let layer_minZoom = (this.basemaplayer['options'] && this.basemaplayer['options']['minZoom']) ? 
                                        this.basemaplayer['options']['minZoom'] : 0;

                this.defineMaxZoom('basemap', layer_maxZoom);
                this.defineMinZoom('basemap', layer_minZoom);
            }            
            
        }   
    },
    
    //
    // Adds layer to searchResults mapdoc
    // data - recordset, heurist query or json
    // this method is invoked on global onserachfinish event in app_timemap
    //
    addSearchResult: function(data, dataset_options ) {
        this.current_query_layer = this.mapManager.addSearchResult( data, dataset_options );
    },

    //
    // Adds layer to searchResults mapdoc
    // recset - recordset to be converted to geojson
    // it was used in Digital Harlem and Expert Nation where recordset is generated and prepared in custom way on client side
    //
    addRecordSet: function(recset, dataset_name) {
        //it is not publish recordset since it is prepared localy 
        this.current_query_layer = null;
        this.mapManager.addRecordSet( recset, dataset_name );
    },
    
    //
    // adds image tile layer to map
    //
    addTileLayer: function(layer_url, layer_options, dataset_name){
    
        let new_layer;
        
        let HeuristTilerLayer = L.TileLayer.extend({
                        getBounds: function(){
                            return this.options._extent;  
                        }});
        if(layer_options['IIIF']){ 
                //IIIF layer can work as a basemap for CRS.Simple
        
                HeuristTilerLayer = L.TileLayer.Iiif.extend({
                        getBounds: function(){
                            return this.options._extent;  
                        }});
                
                layer_options['fitBounds'] = false;

                new_layer = new HeuristTilerLayer(layer_url, layer_options).addTo(this.nativemap);                
                //new L.TileLayer.iiif
        
        }else
        if(layer_options['BingLayer'])
        {
                let BingLayer = HeuristTilerLayer.extend({
                    getTileUrl: function (tilePoint) {
                        //this._adjustTilePoint(tilePoint);
                        return L.Util.template(this._url, {
                            s: this._getSubdomain(tilePoint),
                            q: this._quadKey(tilePoint.x, tilePoint.y, this._getZoomForUrl())
                        });
                    },
                    _quadKey: function (x, y, z) {
                        let quadKey = [];
                        for (let i = z; i > 0; i--) {
                            let digit = '0';
                            let mask = 1 << (i - 1);
                            if ((x & mask) != 0) {
                                digit++;
                            }
                            if ((y & mask) != 0) {
                                digit++;
                                digit++;
                            }
                            quadKey.push(digit);
                        }
                        return quadKey.join('');
                    }
                });    
                
                if(!layer_options.subdomains) layer_options.subdomains = ['0', '1', '2', '3', '4', '5', '6', '7'];
                
                if(!layer_options.attribution) layer_options.attribution = '&copy; <a href="http://bing.com/maps">Bing Maps</a>';
                
                new_layer = new BingLayer(layer_url, layer_options).addTo(this.nativemap);  
                /*{
                   detectRetina: true
                }*/
                           
        }else if(layer_options['TMS']) // && layer_url.indexOf('{q}')>0
        {
                // TMS naming scheme (Y is flipped)
                // Tile Map Service: an early standard supported by OpenLayers. 
                // One difference is the y axis is positive southwards in TMS
            
                let TMS_Layer = HeuristTilerLayer.extend({
                    getTileUrl: function (tilePoint) {
                        //this._adjustTilePoint(tilePoint);
                        let zoom = this._getZoomForUrl();
                        return L.Util.template(this._url, {
                            s: this._getSubdomain(tilePoint),
                            q: this._maptiler(tilePoint.x, tilePoint.y, zoom),
                            z: zoom,
                            x: tilePoint.x,
                            y: Math.pow(2, zoom) - tilePoint.y - 1
                        });
                    },
                    _maptiler: function (x, y, z) { //invert Y
                        
                        let bound = Math.pow(2, z);
                        let s = ''+z+'/'+x+'/'+(bound - y - 1); 
                     
                        return s;
                    }
                });    
                
            new_layer = new TMS_Layer(layer_url, layer_options).addTo(this.nativemap);  

            /*            
              layer_url = 'http://127.0.0.1/heurist/external/php/tileserver.php?/index.json?/c:/xampp/htdocs/HEURIST_FILESTORE/tileserver/mapa/{z}/{x}/{y}.png';
              new_layer = new HeuristTilerLayer(layer_url,
               layer_options).addTo(this.nativemap);         
            */
                
        }else{
            // Google Map/OSM
            
            //transparency for jpeg  layer_options['OSM'] && 
            if(layer_options['extension']=='.jpg'){
                layer_options['matchRGBA'] = [ 0,  0,  0, 0  ]; //replace that match
                layer_options['missRGBA'] =  null; //replace that not match
                layer_options['pixelCodes'] = [ [255, 255, 255] ]; //search for
                layer_options['getBounds'] = function(){
                            return this._extent;  
                        };
                        
                new_layer = new L.tileLayerPixelFilter(layer_url, layer_options).addTo(this.nativemap);
                
            }else{
                new_layer = new HeuristTilerLayer(layer_url, layer_options).addTo(this.nativemap);             
            }
        }
        
        this.all_layers[new_layer._leaflet_id] = new_layer;
        
        this._updatePanels();
        
        /* layer does not global min and max zooms
        if(layer_options){
            if(layer_options['maxZoom']>0)
            {
                this.defineMaxZoom(new_layer._leaflet_id, layer_options['maxZoom']); //from tile layer
            }
            if(layer_options['minZoom']>=0)
            {
                this.defineMinZoom(new_layer._leaflet_id, layer_options['minZoom']); //from tile layer
            }
        }
        */

        return new_layer._leaflet_id;
    }, //addTileLayer

    //
    // adds image overlay to map
    //
    addImageOverlay: function(image_url, image_extent, dataset_name, className=''){
    
        let new_layer = L.imageOverlay(image_url, image_extent).addTo(this.nativemap);
        
        if(!window.hWin.HEURIST4.util.isempty(className)){
            new_layer.getElement().classList.add(className);
        }
      
        this.all_layers[new_layer._leaflet_id] = new_layer;
        
        this._updatePanels();
        
        return new_layer._leaflet_id;
    },



    //
    // adds geotiff image overlay to map
    //
    addGeoTiffImageOverlay: function(url_to_geotiff_file, callback){

//        url_to_geotiff_file = 'https://s3.amazonaws.com/geotiff.io/PuertoRicoTropicalFruit.tiff';
//        url_to_geotiff_file = "https://raw.githubusercontent.com/GeoTIFF/test-data/main/files/LisbonElevation.tif";
      
        fetch(url_to_geotiff_file)
                .then(res => res.arrayBuffer())
                .then(arrayBuffer => {
        parseGeoraster(arrayBuffer).then(georaster => {          
        //direct load: parseGeoraster(url_to_geotiff_file).then(georaster => {
                console.log("georaster:", georaster);

                var new_layer = new GeoRasterLayer({
                    //attribution: "Unknown",  
                    georaster: georaster,
                    //opacity: 0.7,
                    resolution: 512 // optional parameter for adjusting display resolution  256, 128
                });
                
                new_layer.addTo(this.nativemap);

                //this.nativemap.fitBounds(new_layer.getBounds());
                
                this.all_layers[new_layer._leaflet_id] = new_layer;
                
                this._updatePanels();
                
                callback.call(this, new_layer._leaflet_id);
          })
        });        
    },

    
    // if to_pixels is true 
    // for simple crs - from latlong to pixels
    // for epsg       - from epsg to wgs
    //  --------------------
    // if to_pixels is false
    // for simple crs  - convert from pixels to latlong
    // for EPSG   - convert from WGS(latlong) to target EPSG projection
    //
    // leaflet project (from coordinates to pixels) and unproject (from pixels to coordinates)
    //
    projectGeoJson:function(gjson, to_pixels){
        
        let that = this;
        
        
        
        if(gjson.type == 'FeatureCollection'){
            for (let k=0; k<gjson.features.length; k++){
                this.projectGeoJson(gjson.features[k], to_pixels); //another collection or feature
            }
        }else if(Array.isArray(gjson)){
            for (let k=0; k<gjson.length; k++){
                this.projectGeoJson(gjson[k], to_pixels); //another collection or feature
            }
        }else{
            
            let ftypes = ['Point','MultiPoint','LineString','MultiLineString','Polygon','MultiPolygon','GeometryCollection'];
        
            function __convert_primitive(geometry){

                if($.isEmptyObject(geometry)) return;

                if(geometry.type=="GeometryCollection"){
                    let l;
                    for (l=0; l<geometry.geometries.length; l++){
                        __convert_primitive(geometry.geometries[l]); //another collection or feature
                    }
                }else{

                    
                    function _is_point(pnt){
                            let isValid = (Array.isArray(pnt) && pnt.length==2 && 
                                window.hWin.HEURIST4.util.isNumber(pnt[0]) && window.hWin.HEURIST4.util.isNumber(pnt[1]));
                            return isValid;
                    }                    
                    
                    //
                    // 
                    //
                    function _convertXY(pnt){
                        
                        if(to_pixels){
                            //from lat long to pixels
                            let pix = that.nativemap.project(pnt, that.basemap_layer_maxzoom);

                            return [Math.round(-pix.y), Math.round(-pix.x)];

                        }else{
                            
                            pnt[1] = -pnt[1];
                            pnt[0] = -pnt[0];
                            
                            let latlong = that.nativemap.unproject([pnt[1],pnt[0]], that.basemap_layer_maxzoom);
                            
                            return [latlong.lat, latlong.lng];
                        }
                    }
                    
                    //for timemap
                    function __convertCoords(coords){
                        
                        let res = [];

                        if(_is_point(coords)){ 
                        
                            res = _convertXY(coords); //lat long
                        
                        }else {
                            
                            for (let m=0; m<coords.length; m++){
                                
                                if(_is_point(coords[m])){
                                    const pnt = _convertXY(coords[m]);
                                    res.push(pnt);
                                }else{
                                    res.push(__convertCoords(coords[m]));   
                                }
                            }
                        }
                        return res;
                    }

                    let res_coords = __convertCoords(geometry.coordinates);
                    if(res_coords.length>0){
                            geometry.coordinates = res_coords;
                    }
                }

            }//__convert_primitive
        
        
            if(gjson.type == 'Feature' && !$.isEmptyObject(gjson.geometry)){
                __convert_primitive(gjson.geometry);
            }else if (gjson.type && ftypes.indexOf(gjson.type)>=0){                      
                __convert_primitive(gjson);
            }
        }
        
    },
    
    //
    // adds geojson layer to map ( see mapLayer2 )
    // returns nativemap id
    // options:
    //      geojson_data
    //      timeline_data
    //      timeline_dty_ids
    //      dataset_name
    //      dataset_type: db|shp|kml
    //      preserveViewport
    //      layer_style
    //      popup_template   - smarty template for popup info
    //      origination_db
    //      selectable
    //
    addGeoJson: function(options){
            
            let geojson_data = options.geojson_data,
                timeline_data = options.timeline_data,
                layer_style = options.layer_style,
                popup_template = options.popup_template,
                dataset_name = options.dataset_name,
                selectable = options.selectable,
                preserveViewport = options.preserveViewport;

        if (window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true) || 
            window.hWin.HEURIST4.util.isArrayNotEmpty(timeline_data)){
              
            if(this.is_crs_simple){
                this.projectGeoJson( geojson_data, false ); //from pixels to latlong
            }
                
            let that = this;
            
            let new_layer = L.geoJSON(geojson_data, {
                    default_style: null
                    , layer_name: dataset_name
                    , popup_template: popup_template
                    , origination_db: options.origination_db
                    , dataset_type: options.dataset_type
                    , selectable: selectable
                    //The onEachFeature option is a function that gets called on each feature before adding it to a GeoJSON layer. A common reason to use this option is to attach a popup to features when they are clicked.
                   /* 
                    , onEachFeature: function(feature, layer) {

                        //each feature may have its own feature.style
                        //if it is not defined then layer's default_style will be used
                        feature.default_style = layer.options.default_style;

                        layer.on('click', function (event) {
                            that.vistimeline.timeline('setSelection', [feature.properties.rec_ID]);

                            that.setFeatureSelection([feature.properties.rec_ID]);
                            if(window.hWin.HEURIST4.util.isFunction(that.options.onselect)){
                                that.options.onselect.call(that, [feature.properties.rec_ID]);
                            }
                            //open popup
                            var popupURL = window.hWin.HAPI4.baseURL + 'viewers/record/renderRecordData.php?mapPopup=1&recID='
                            +feature.properties.rec_ID+'&db='+window.hWin.HAPI4.database;

                            $.get(popupURL, function(responseTxt, statusTxt, xhr){
                                if(statusTxt == "success"){
                                    that.main_popup.setLatLng(event.latlng)
                                    .setContent(responseTxt) //'<div style="width:99%;">'+responseTxt+'</div>')
                                    .openOn(that.nativemap);
                                }
                            });

                            //that._trigger('onselect', null, [feature.properties.rec_ID]);

                        });
                    }*/

                    /* , style: function(feature) {
                    if(that.selected_rec_ids.indexOf( feature.properties.rec_ID )>=0){
                    return {color: "#ff0000"};
                    }else{
                    //either specific style from json or common layer style
                    return feature.style || feature.default_style;
                    }
                    }
                    */
                    /*
                    pointToLayer: function (feature, latlng) {
                    return L.circleMarker(latlng, geojsonMarkerOptions);
                    },                
                    //The filter option can be used to control the visibility of GeoJSON features
                    filter: function(feature, layer) {
                    return feature.properties.show_on_map;
                    }                
                    */
                })
                /*.bindPopup(function (layer) {
                return layer.feature.properties.rec_Title;
                })*/
                .addTo( this.nativemap );            

             if(that.options.map_rollover){
                new_layer.bindTooltip(function (layer) {
                    if(layer.feature && layer.feature.properties){
                        return layer.feature.properties.rec_Title;
                    }
                })
             }                


            /* not implemented - idea was store template in mapdocument and excute is on _onLayerClick    
            if(popup_template){
                new_layer.eachLayer( function(child_layer){ 
                        child_layer.feature.popup_template = popup_template;
                });
            } 
            */   

            this.all_layers[new_layer._leaflet_id] = new_layer;
                
            if(!this.notimeline){
                this.updateTimelineData(new_layer._leaflet_id, timeline_data, options.timeline_dty_ids, dataset_name);
            }
            
            this._updatePanels();

            //apply layer ot default style and fill markercluster
            this.applyStyle( new_layer._leaflet_id, layer_style ? layer_style : this.setStyleDefaultValues(), null ); //{color: "#00b0f0"}

            
            if(!preserveViewport){
                this.zoomToLayer(new_layer._leaflet_id);           
            }
            
            return new_layer._leaflet_id;
        }        

        else{
            return 0;
        }
        
    }, //addGeoJson

    //
    //
    //
    updateTimelineLayerName: function(layer_id, new_dataset_name){

        if(this.notimeline) return;
        
        let group_idx = this.getTimelineGroup(layer_id);
        if(group_idx>=0){
            this.timeline_groups[group_idx].content = new_dataset_name;
            
            this.vistimeline.timeline('timelineUpdateGroupLabel', this.timeline_groups[group_idx]);
        }
        
    },

    
    //
    // add/replace layer time data to timeline_groups/timeline_items
    //  layer_id - leaflet id
    //  layer_data - timeline data from server
    //
    updateTimelineData: function(layer_id, layer_data, timeline_dty_ids, dataset_name){
        
            if(this.notimeline) return;
      
            let titem, k, ts, iconImg;

            if(window.hWin.HEURIST4.util.isArrayNotEmpty(layer_data) ){

                
                //list of fields - filter by date field
                if(timeline_dty_ids && timeline_dty_ids.length>1){
                    let sfields = '<span style="font-size:0.9em">'
                    for(let i=0;i<timeline_dty_ids.length;i++){  
                        let id = timeline_dty_ids[i];
                        let lbl = (id==10)?'Start/end dates':$Db.dty(id,'dty_Name');
                        
                        sfields = sfields
                        + '<br><label><input type="checkbox" data-layer_id="'+layer_id+'" data-dty_id="'+id+'" checked/>'     
                        + lbl + '</label>';
                    } 
                    sfields = sfields + '</span>'
                    dataset_name = dataset_name + sfields;
                }
                
                let group_idx = this.getTimelineGroup(layer_id);
                if(group_idx<0){
                    this.timeline_groups.push({ id:layer_id, content:dataset_name });        
                    group_idx = this.timeline_groups.length-1;
                }else{
                    this.timeline_groups[group_idx].content = dataset_name;
                    //this.timeline_groups[group_idx].field_ids = timeline_dty_ids;
                }
                /*if(dataset_name=='Current query' && group_idx>0){ //swap
                    var swap = this.timeline_groups[0];
                    this.timeline_groups[0] = this.timeline_groups[group_idx];
                    this.timeline_groups[group_idx] = swap;
                }*/
                
                this.timeline_items[layer_id] = []; //remove/reset previous data
                
                let that = this;

                $.each(layer_data, function(idx, tdata){

                    iconImg = window.hWin.HAPI4.iconBaseURL + tdata.rec_RecTypeID;

                    ts = tdata.when;

                    for(k=0; k<tdata.when.length; k++){
                        ts = tdata.when[k];
                        
                        if(!ts[0]){
                            //Start date not defined for tdata.when,tdata.rec_ID                            
                            
                        }else{
                            
                            titem = {
                                id: layer_id+'-'+tdata.rec_ID+'-'+k, //unique id
                                group: layer_id,
                                content: '<img src="'+iconImg 
                                + '"  align="absmiddle" style="padding-right:3px;" width="12" height="12"/>&nbsp;<span>'
                                + tdata.rec_Title+'</span>',
                                //'<span>'+recName+'</span>',
                                title: ts[4], //tdata.rec_Title,
                                start: ts[0],
                                profile_start: ts[5],
                                profile_end: ts[6], 
                                recID: tdata.rec_ID,
                                dtyID: ts[8]
                            };

                            if(ts[3] && ts[0]!=ts[3]){
                                titem['end'] = ts[3];
                                if(ts[1]) titem['start0'] = ts[1];
                                if(ts[2]) titem['end0'] = ts[2];
                            }else{
                                titem['type'] = 'point';
                                //titem['title'] = singleFieldName+': '+ dres[0] + '. ' + titem['title'];
                            }

                            that.timeline_items[layer_id].push(titem); 
                        }
                        
                    }//for timespans

                });
                
            }else if(!this.removeTimelineGroup(layer_id)){
                // no timeline data - remove entries from timeline_groups and items
                // if no entries no need to redraw
                return;
            }
                
            this.vistimeline.timeline('timelineRefresh', this.timeline_items, this.timeline_groups);          
            
            //this._updatePanels();
    },
    
    //
    //
    //
    getTimelineGroup:function(layer_id){
        
        for (let k=0; k<this.timeline_groups.length; k++){
            if(this.timeline_groups[k].id==layer_id){
                return k;
            }
        }
        return -1;
    },
    
    //
    //
    //
    removeTimelineGroup:function(layer_id){
        let idx = this.getTimelineGroup(layer_id);
        if(idx>=0){
            this.timeline_groups.splice(idx,1);
            this.timeline_items[layer_id] = null;
            delete this.timeline_items[layer_id];
            return true;
        }else{
            return false;
        }
    },
    
    //
    //
    //
    _mergeBounds: function(bounds){

        let res = null;

        for(let i=0; i<bounds.length; i++){
            if(bounds[i]){

                if(!(bounds[i] instanceof L.LatLngBounds)){
                    if(Array.isArray(bounds[i]) && bounds[i].length>1 ){
                        bounds[i] = L.latLngBounds(bounds[i]);
                    }else{
                        continue;
                    }
                }

                if( bounds[i].isValid() ){
                    if(res==null){
                        res = L.latLngBounds(bounds[i].getSouthWest(), bounds[i].getNorthEast() );
                    }else{
                        res.extend(bounds[i]);
                        //bounds[i].getNorthWest());
                        //res.extend(bounds[i].getSouthEast());
                    } 
                }
            }
        }

        return res;

    },

    //
    // get summary bounds for set of TOP layers
    //
    getBounds: function(layer_ids){
        
        if(!Array.isArray(layer_ids)){
            layer_ids = [layer_ids];
        }
        
        let bounds = [];
        
        for(let i=0; i<layer_ids.length; i++){
            
            let layer_id = layer_ids[i];
            
            let affected_layer = this.all_layers[layer_id];
            if(affected_layer){
                let bnd;
                
                if(window.hWin.HEURIST4.util.isFunction(affected_layer.getBounds)){
                    bnd = affected_layer.getBounds();
                }else if(window.hWin.HEURIST4.util.isFunction(affected_layer.options.getBounds)){
                    bnd = affected_layer.options.getBounds();
                }
                if(bnd){
                    bounds.push( bnd );
                }
                
                if(window.hWin.HEURIST4.util.isArrayNotEmpty( this.all_markers[layer_id] ) 
                        && this.all_clusters[layer_id])
                {
                    bnd = this.all_clusters[layer_id].getBounds();
                    bounds.push( bnd );
                }
            }
            
        }
        
        bounds = this._mergeBounds(bounds);
        
        return bounds;
    },
    
    //
    // zoom to TOP layers
    // layer_ids - native ids
    //
    zoomToLayer: function(layer_ids){
        
        let bounds = this.getBounds(layer_ids);
        this.zoomToBounds(bounds);
        
    },

    //
    // get or save map bounds in usr prefs/restore and set map
    // (used to store extent in mapDraw intersession)
    //    
    getSetMapBounds: function(is_set){
        
        if(is_set){
            let bounds = this.nativemap.getBounds();
            window.hWin.HAPI4.save_pref('map_saved_extent', bounds.toBBoxString());
        }else{
            let bounds = window.hWin.HAPI4.get_prefs_def('map_saved_extent', null);
            
            if(bounds){
                //'southwest_lng,southwest_lat,northeast_lng,northeast_lat'
                bounds = bounds.split(',');
                if(bounds.length==4){
                    let corner1 = L.latLng(bounds[1], bounds[0]),
                        corner2 = L.latLng(bounds[3], bounds[2]);
                    bounds = L.latLngBounds(corner1, corner2);            
                    this.zoomToBounds(bounds);
                }
            }
            
        }
        
    },
    
    //
    // Returns leaflet 
    //
    getNativeMap: function(){
        return this.nativemap;
    },
    
    //
    // Converts zoom in km to nativemap zoom (0-22)
    //
    convertZoomToNative: function( zoomInKM, bounds ){
        
        if(this.is_crs_simple){
            return this.nativemap.getBoundsZoom(bounds);    
        }
        
        let nativeZoom = -1;
        
        if(typeof zoomInKM == 'string'){ //in km
            zoomInKM = parseFloat(zoomInKM); 
        }
        
        if(zoomInKM>0){
            
            let ll;
            if(!bounds){
                ll = L.latLng(45, 0);
            }else if(Object.hasOwn(bounds, 'lng') && Object.hasOwn(bounds,'lat')){ //  instanceof L.latLng
                ll = bounds;
            }else{
                ll = bounds.getCenter();
            }
            let ruler = cheapRuler(ll.lat);
            let bbox = ruler.bufferPoint([ll.lng, ll.lat], zoomInKM/4);
            //w, s, e, n
            let corner1 = L.latLng(bbox[1], bbox[0]),
                corner2 = L.latLng(bbox[3], bbox[2]);
            let bbox2 = L.latLngBounds(corner1, corner2);            
    

                
            let sz = this.nativemap.getSize();
            let fz = this.nativemap.getSize; //keep
            
            L.Map.include({
                getSize: function () {
                    return new L.Point(parseInt(sz.x), parseInt(sz.y));
                }
            });             
            let cmap = new L.Map(document.createElement('div'), {
                'center': [0, 0],
                'zoom': 0
            });

            nativeZoom = cmap.getBoundsZoom(bbox2); //this.nativemap
            
            //console.log('Convert', zoomInKM,'=>',nativeZoom);          
            
            //restore getSize function
            cmap = null;
            L.Map.include({getSize:fz});
            
        }
        
        return nativeZoom; 
    },
    
    _getZoomPriority: function(layer_name){
        
        if(layer_name=='basemap'){
            return 2;
        }else if(layer_name=='widget'){
            return 1;
        }else{
            return 0;
        }
    },
    
    //
    // Sets maximum possible zoom
    // layer_name - name of restrictions(widget, basemap, or mapdoc id)
    // priority level: map document, widget, basemap.
    //
    defineMaxZoom: function(layer_name, layer_maxZoom)
    {            
            if(this.is_crs_simple) return;
    
            let idx = this.available_maxzooms.findIndex(arr => arr[0] == layer_name); //find restrictions for basemap

            layer_maxZoom = parseInt(layer_maxZoom);
            
            if(!(layer_maxZoom>=0)){ //remove this layer
                if(idx != -1){
                    this.available_maxzooms.splice(idx, 1);
                }else{
                    return;
                }
            }else {
                if(idx != -1){ 
                    //found - update max zoom value
                    if(this.available_maxzooms[idx][1]==layer_maxZoom) return; //the same value - no changes
                    this.available_maxzooms[idx] = [layer_name, layer_maxZoom, this._getZoomPriority(layer_name)]; 
                }else{ 
                    // add max zoom value
                    this.available_maxzooms.push([layer_name, layer_maxZoom, this._getZoomPriority(layer_name)]);
                }
            }
            
            if(this.available_maxzooms.length>0){
                //sort asc
                this.available_maxzooms.sort((a, b) => (a[2]==b[2])?(a[1] - b[1]):(a[2] - b[2]));
                //take first - lowest restriction
                this.nativemap.setMaxZoom(this.available_maxzooms[0][1]>320?32:this.available_maxzooms[0][1]);
            }else{
                this.nativemap.setMaxZoom(20);
            }
    },
    

    //
    // Sets minimum possible zoom
    // layer_name - name of restrictions(widget, basemap, layer id, mapdoc id)
    //
    defineMinZoom: function(layer_name, layer_minZoom)
    {                

            let idx = this.available_minzooms.findIndex(arr => arr[0] == layer_name); //find restrictions for basemap
            
            layer_minZoom = parseInt(layer_minZoom);
            
            if(!(layer_minZoom>=0)){ //remove this layer
                if(idx != -1){
                    this.available_minzooms.splice(idx, 1);
                }else{
                    return;
                }
            }else {
                if(idx != -1){ 
                    //found - update min zoom value
                    if(this.available_minzooms[idx][1]==layer_minZoom) return; //the same value - no changes
                    this.available_minzooms[idx] = [layer_name, layer_minZoom, this._getZoomPriority(layer_name)]; 
                }else{ 
                    // add min zoom value
                    this.available_minzooms.push([layer_name, layer_minZoom, this._getZoomPriority(layer_name)]);
                }
            }
            
            if(this.available_minzooms.length>0){
                //sort desc
                this.available_minzooms.sort((a, b) => (a[2]==b[2])?(b[1] - a[1]):(a[2]-b[2]));
                //take first - max restriction
                this.nativemap.setMinZoom(this.available_minzooms[0][1]>=0?this.available_minzooms[0][1]:0);
            }else{
                this.nativemap.setMinZoom(0);
            }
    },    

    _zoom_timeout: 0,
    //
    // zoom map to given bounds
    // fly_params - true, false or {animate:true, duration:duration, maxZoom: maxZoom}
    //   default duration is 5 seconds, maxZoom from options.zoomMaxInKM and native map getMaxZoom
    //
    zoomToBounds: function(bounds, fly_params){
        
            if(bounds && !(bounds instanceof L.LatLngBounds)){
                if(Array.isArray(bounds) && bounds.length>1 ){
                    bounds = L.latLngBounds(bounds);
                }
            }
            
            if(this._zoom_timeout>0){
                clearTimeout(this._zoom_timeout);   
                this._zoom_timeout = 0;
            }
                        
            if(bounds && bounds.isValid()){
                
                let maxZoom = this.nativemap.getMaxZoom();
                
                let nativeZoom = this.convertZoomToNative(this.options.zoomMaxInKM, bounds); //adjust for current lat
                if(nativeZoom>=0 && nativeZoom<maxZoom){
                    maxZoom = nativeZoom;
                } 
                if(window.hWin.HEURIST4.util.isObject(fly_params) && (!fly_params['maxZoom'] || fly_params['maxZoom'] > maxZoom)){
                    fly_params['maxZoom'] = maxZoom;
                }
                
                let zoom_params = $.extend({maxZoom: maxZoom}, this.options.map_margins);

                if(fly_params){
                    let duration = 5;
                    if(fly_params===true){
                        fly_params = {animate:true, duration:duration, maxZoom: maxZoom};
                    }else{
                        if(fly_params.duration>0){
                            duration = fly_params.duration;
                        }else{
                            fly_params.duration = duration;
                        }
                    }
                    fly_params = $.extend(fly_params, this.options.map_margins);
                    this.nativemap.flyToBounds(bounds, fly_params);
                    
                    let that = this; //fly to bounds fits bounds wrong
                    this._zoom_timeout = setTimeout(function(){
                            that.nativemap.fitBounds(bounds, zoom_params);
                            that._zoom_timeout = 0;
                    }
                    ,duration*1000+200);      
            
                }else{
                    this.nativemap.fitBounds(bounds, zoom_params);
                    //paddingTopLeft:L.point(500,50),paddingBottomRight:L.point(50,0)});
                    //padding: L.point(50, 50)});  //padding - margins for map 
                    //this.nativemap.fitBounds(bounds, {maxZoom: 0});   
                }             
            }
    },

    //
    // Return both zoom for provided bounds and the current zoom
    //
    getBoundsZooms: function(bounds){

        if(bounds && !(bounds instanceof L.LatLngBounds)){
            if(Array.isArray(bounds) && bounds.length>1 ){
                bounds = L.latLngBounds(bounds);
            }
        }

        const current_zoom = this.nativemap.getZoom();

        if(bounds && bounds.isValid()){
            return {zoom: this.nativemap.getBoundsZoom(bounds), cur_zoom: current_zoom};
        }

        return {zoom: null, cur_zoom: current_zoom};
    },

    //
    // remove top layer
    // layer_id -  native it
    //
    removeLayer: function(layer_id)
    {
        let affected_layer = this.all_layers[layer_id];

        if(affected_layer){
           
            if(!this.isImageLayer(affected_layer)){
            
                this._clearHighlightedMarkers(layer_id);
                
                if(this.all_clusters[layer_id]){
                    this.all_clusters[layer_id].clearLayers();
                    this.all_clusters[layer_id].remove();
                    this.all_clusters[layer_id] = null;
                    delete this.all_clusters[layer_id];
                }
                if(this.all_markers[layer_id]){
                    this.all_markers[layer_id] = null;
                    delete this.all_markers[layer_id];
                }
            }
            this.nativemap.removeLayer( affected_layer );
            affected_layer.remove();
            affected_layer = null;
            this.all_layers[layer_id] = null;
            delete this.all_layers[layer_id];
            
            if( this.removeTimelineGroup(layer_id) && !this.notimeline){
                //update timeline
                this.vistimeline.timeline('timelineRefresh', this.timeline_items, this.timeline_groups);
            }

            this._updatePanels();

            this.defineMaxZoom(layer_id, -1); //on layer remove
            this.defineMinZoom(layer_id, -1);
        }
    },

    //
    // switch layer entire visibility on off
    //
    setLayerVisibility: function(nativelayer_id, visiblity_set)
    {
        let affected_layer = this.all_layers[nativelayer_id];
        if(affected_layer){
            
            if(visiblity_set===false){
                //hide all
                if(!this.isImageLayer(affected_layer)){
                    this._clearHighlightedMarkers( nativelayer_id );
                }
                affected_layer.remove();
                if(this.all_clusters[nativelayer_id]) this.all_clusters[nativelayer_id].remove();
                
            }else if(!affected_layer._map){  //not on map
                affected_layer.addTo(this.nativemap);
                if(this.all_clusters[nativelayer_id]) this.all_clusters[nativelayer_id].addTo(this.nativemap);
            }
        }
    },
    
    //
    //
    //
    isLayerVisibile: function(nativelayer_id){
        let affected_layer = this.all_layers[nativelayer_id];
        if(affected_layer){
            return this.nativemap.hasLayer(affected_layer);
        }else{
            return false;
        }
    },
    
    //
    //
    //
    isSomethingOnMap: function(){
        
            let len = Object.keys(this.all_layers).length;
            //all_layers
            for (let layer_id in this.all_layers){
                let layer = this.all_layers[layer_id]
                if(window.hWin.HEURIST4.util.isArrayNotEmpty( this.all_markers[layer_id] ) || this.all_clusters[layer_id]){
                    return true;   
                }else if(layer instanceof L.ImageOverlay || layer instanceof L.TileLayer){
                    return true;   
                }else if ( layer instanceof L.LayerGroup ) {
                    let layers = layer.getLayers();
                    if(layers.length>0) return true;
                }
            }
            
            return false;
            
            /*
            is_found = false;
            this.nativemap.eachLayer(function(layer){
                if(layer instanceof L.ImageOverlay || layer instanceof L.TileLayer){
                    is_found = true;
                    return false;
                }else if (layer instanceof L.Polygon || layer instanceof L.Circle || layer instanceof L.Rectangle){
                    is_found = true;
                    return false;
                }
            });
            return is_found;
            */
            
        
    },

    addImage2: function( imageurl, image_extent ){
        return L.imageOverlay(imageurl, image_extent).addTo(this.nativemap);    
    },
    
    //
    // returns style for layer (defined in layer record or via legend)
    //
    getStyle: function(layer_id) {

        let affected_layer = this.all_layers[layer_id];
        if(!affected_layer) return null;

        if(this.isImageLayer(affected_layer)){
            let element = affected_layer instanceof L.ImageOverlay ? affected_layer.getElement() : affected_layer.getContainer();
            return this.getImageMapFilter(element);
        }

        let style = window.hWin.HEURIST4.util.isJSON(affected_layer.options.default_style);
        if(!style){ //layer style not defined - get default style
            return this.setStyleDefaultValues({});    
        }else{
            return style;
        }
    },    
    
    isImageLayer: function(affected_layer){

        if(window.hWin.HEURIST4.util.isNumber(affected_layer)){
            affected_layer = this.all_layers[affected_layer];
        }

        return !affected_layer 
            || affected_layer instanceof L.ImageOverlay
            || affected_layer instanceof L.TileLayer
            || (typeof GeoRasterLayer === 'function' && affected_layer instanceof GeoRasterLayer);
    },
    
    //
    // Applies style for given TOP layer
    // it takes style from this.options.default_style (defined user prereferences)
    //   or from affected_layer.options.default_style
    //   or each feature may have its own feature.style that overwrites layer's one
    // 
    // It is invoked from addGeoJson or after symbology editor
    // Besides the style, it assigns "selectable" property for child layer
    //
    //  newStyle - style object 
    //  newThematicMap - thematic map array {rec_ID=>symbol}
    //
    applyStyle: function(layer_id, newStyle, newThematicMap) {
        
        let affected_layer = this.all_layers[layer_id];
        
        if(this.isImageLayer(affected_layer)){
            
            if( affected_layer instanceof GeoRasterLayer ){
                L.setOptions(affected_layer, newStyle);
                return;
            }
            // Get html element (img => non-tiled)
            let element = affected_layer instanceof L.ImageOverlay ? affected_layer.getElement() : affected_layer.getContainer();

            // Get class list, look for class that starts with heurist-imageoverlay-
            if($(element).attr('class')){
                let classes = $(element).attr('class').split(' ');
                let layer_class = '';

                for(const class_name of classes){
                    if(class_name.match(/heurist-imageoverlay-/)){
                        layer_class = class_name;
                        break;
                    }
                }

                if(!window.hWin.HEURIST4.util.isempty(layer_class)){
                    this.applyImageMapFilter(layer_class, newStyle); // apply image filter
                }
            }

            return;  
        } 

        this._clearHighlightedMarkers(layer_id);

        
        let that = this;
        let theme_has_changed = false;
        
        
        
        /* REMOVE
        if(newThematicMap!=null){
            theme_has_changed = window.hWin.HEURIST4.util.isnull( affected_layer.options.thematic_map );
            affected_layer.options.thematic_map = newThematicMap;
        }else{
            theme_has_changed = !window.hWin.HEURIST4.util.isnull( affected_layer.options.thematic_map );
            affected_layer.options.thematic_map = null;
        }*/
        
        //create icons (@todo for all themes and rec types)
        let style = window.hWin.HEURIST4.util.isJSON(newStyle);
        if(!style && affected_layer.options.default_style && !newThematicMap){
            //new style is not defined and layer already has default one - no need action
            return;
        }
       
        if(style || !affected_layer.options.default_style){
       
            // set default values -------       
            style = this.setStyleDefaultValues( style );
            
            affected_layer.options.default_style = style;
        }else{
            style = affected_layer.options.default_style;
        }
        
        let is_new_markercluster = false;
        if(this.isMarkerClusterEnabled){

            is_new_markercluster = window.hWin.HEURIST4.util.isnull(this.all_clusters[layer_id]);
            if(is_new_markercluster){
                let opts = {showCoverageOnHover:false, 
                                maxClusterRadius:this.markerClusterGridSize,
                                spiderfyOnMaxZoom: false,
                                zoomToBoundsOnClick: false
                                //disableClusteringAtZoom:this.markerClusterMaxZoom
                };
                                
                this.all_clusters[layer_id] = L.markerClusterGroup(opts);
                

                // a.layer is actually a cluster
                this.all_clusters[layer_id].on('clusterclick', function (a) {
                    
                    let maxZoom = Math.min(that.nativemap.getMaxZoom(),that.markerClusterMaxZoom);
                    
                    if(that.nativemap.getZoom()>=maxZoom ||
                        that.nativemap.getBoundsZoom(a.layer.getBounds())>=maxZoom ){
                        if(a.layer.getAllChildMarkers().length>that.markerClusterMaxSpider){
                            let markers = a.layer.getAllChildMarkers();
                            
                            let latlng = a.layer.getLatLng();
                            let selected_layers = {};
                            let sText = '';
                            
                            //scan all markers in this cluster
                            $.each(markers, function(i, top_layer){    
                                if(top_layer.feature){
                                    selected_layers[top_layer._leaflet_id] = top_layer;
                                    let title = top_layer.feature.properties.rec_Title;
                                    sText = sText + '<div class="leaflet_layer_opt" title="'+ title +'" data-id="'+top_layer._leaflet_id+'">'+ title +'</div>';
                                }
                            });
                            
                            that._showMultiSelectionPopup(latlng, sText, selected_layers, false);
                            
                        }else{
                           a.layer.spiderfy(); 
                        }
                    }else{
                        a.layer.zoomToBounds({padding: L.point(20, 20)});
                    }
                });
                
            }else{
                this.all_clusters[layer_id].clearLayers(); 
            }
            
        }else{
            is_new_markercluster = window.hWin.HEURIST4.util.isnull(this.all_markers[layer_id]);
        }
        
        if(is_new_markercluster){

            //all markers per top layer            
            this.all_markers[layer_id] = []; //reset

            //get all markers (fill all_markers) within layer group and apply new style
            function __extractMarkers(layer, parent_layer, feature)
            {
                layer.options.selectable = parent_layer.options.selectable;
                
                //let feature = layer.feature;    
                if(layer instanceof L.LayerGroup){
                    layer.eachLayer( function(child_layer){__extractMarkers(child_layer, layer, feature);} );
                    
                }else if(layer instanceof L.Marker || layer instanceof L.CircleMarker){
                    layer.feature = feature;
                    
                    if(that.isMarkerClusterEnabled){
                        parent_layer.removeLayer( layer ); //remove from original parent/top layer  
                        layer.cluster_layer_id = layer_id;
                    }else{
                        //need to store reference to add/remove marker on style change  
                        //CircleMarker <> Marker
                        layer.parent_layer = parent_layer;  
                    }
                    
                    if(that.options.map_rollover){
                        layer.bindTooltip(function (layer) {
                            return layer.feature.properties.rec_Title;
                        })
                    }              
                    
                    /* expremental HARDCODE for HIE
                    if(that.isHamburgIslamicImpire){
                        if( window.hWin.HEURIST4.util.findArrayIndex(layer.feature.properties.rec_ID, that.hie_places_with_events)<0 ){ 
                        //that.hie_places_with_events.indexOf(layer.feature.properties.rec_ID)<0){
                            layer.feature.style = that.hie_places_wo_events_style;
                        }
                    }*/
                    that.all_markers[layer_id].push( layer );  
                      
                }else if(layer instanceof L.Polyline && !(layer instanceof L.Polygon)){
                    let use_style = window.hWin.HEURIST4.util.cloneJSON( style );
                    use_style.fill = false;
                    if(!layer.feature){
                        layer.feature = {properties:feature.properties};
                    } 
                    layer.feature.default_style = use_style;
                }

                layer.on('click', function(e){that._onLayerClick(e)} );
            }

            //loop all children and fill all_markers[layer_id]
            affected_layer.eachLayer( function(child_layer){ 
                    child_layer.options.selectable = affected_layer.options.selectable;
                    child_layer.feature.default_style = style;  //default style for feature is parent layer style
                    
                    __extractMarkers(child_layer, affected_layer, child_layer.feature);
            } );
            
        }else{
            //assign default style          
            this._assignDefaultStyleToFeature(affected_layer, style);
            
        }
        
        let myIcon = this._createMarkerIcon( style );
        
        //apply marker style
        let all_visible_markers = [];
        $(this.all_markers[layer_id]).each(function(idx, layer){
            
                let feature = layer.feature;
                let markerStyle;
                let setIcon;
                
                layer.feature.default_style = style; //default style for feature is parent layer style

                if(layer.feature.thematic_style){ //thematic map is active
                    markerStyle = layer.feature.thematic_style; 
                    setIcon = that._createMarkerIcon( markerStyle );
                }else    
                if(layer.feature.style){ //indvidual style per record
                    markerStyle = that.setStyleDefaultValues(layer.feature.style);
                    setIcon = that._createMarkerIcon( markerStyle );
                }else{
                    //heurist layer common style
                    markerStyle = style;
                    setIcon = myIcon;
                }

                that.applyStyleForMarker(layer_id, layer, markerStyle, setIcon);
                
                layer = that.all_markers[layer_id][idx]; //need reassign sine previous function may replace styles
                
                if(layer.feature.thematic_style===false){ //thematic map is active and this feature does not fit to conditions
                    //hide on map
                    layer.hidden_by_theme = true;
                    layer.remove(); 
                }else{
                    
                    if(layer.hidden_by_filter!==true && layer.hidden_by_zoom!==true){
                        all_visible_markers.push(layer); 
                           
                        if(layer.hidden_by_theme===true && !that.isMarkerClusterEnabled && !layer._map){
                            layer.addTo( that.nativemap );     
                        } 
                         
                    }
                    layer.hidden_by_theme = false;
                }
            
        });
     
        //add all markers to cluster
        if(this.isMarkerClusterEnabled){
            this.all_clusters[layer_id].addLayers(all_visible_markers); //was this.all_markers[layer_id]                                  
            if(is_new_markercluster){
                this.all_clusters[layer_id].addTo( this.nativemap );
            }
        }
        
        //apply style for polygons and polylines 
        affected_layer.setStyle(function(feature){ return that._stylerForPoly(feature); });
        
        //show/hide polygons and polylines if they do not fit thematic map conditions
        this.eachLayerFeature(affected_layer, function(layer){
                if(layer.feature.thematic_style===false){ //thematic map is active and this feature does not fit to conditions
                    //hide on map
                    layer.hidden_by_theme = true;
                    layer.remove(); 
                }else{
                    if(!layer._map && layer.hidden_by_theme===true && !(layer.hidden_by_filter || layer.hidden_by_zoom)){
                        layer.addTo( that.nativemap );
                    }
                    layer.hidden_by_theme = false;
                    
                }
        });    
        
    },
    
    //
    // callback obtains as parameter child element (child layer)
    //
    eachLayerFeature: function(nativelayer_id, callback){
        
        let affected_layer;
        if(nativelayer_id instanceof L.Layer){
            affected_layer = nativelayer_id;   
        }else{
            affected_layer = this.all_layers[nativelayer_id];
        }
        
        if(affected_layer){
            
            let layer_id = affected_layer._leaflet_id;

            let that = this;
            function __childLayers(layer, feature){
                if(layer instanceof L.LayerGroup){
                    layer.eachLayer( function(child_layer){__childLayers(child_layer, feature) } );
                }else {
                    if(!layer.feature){
                        layer.feature = {properties: feature.properties};
                    } 
                    
                    callback.call(that, layer);
                }
            }
            
            if(affected_layer instanceof L.LayerGroup){
                affected_layer.eachLayer( function(child_layer){ __childLayers(child_layer, child_layer.feature) });
                
                //for markers
                if(Object.hasOwn(this.all_markers,layer_id)){
                        let markers = this.all_markers[layer_id];
                        $(markers).each(function(idx, layer){
                            if (layer.feature){
                                callback.call(that, layer);
                            }
                        });
                }
                
            }else{
                __childLayers(affected_layer, affected_layer.feature);
            }        
        }        
    },
    
    //
    // assign "style" to feature.default_style - to use in _stylerForPoly 
    // individual style per feature (from DT_SYMBOLOGY) is stored in feature.style
    // if feature.style is not defined feature.default_style will be used
    //
    // feature.default_style - default style (used if individual style or thematic_style are not defined)
    // feature.style - individual style for feature (from DT_SYMBOLOGY)
    // feature.thematic_style - style assigned by applyThematicMap in mapLayer 
    //
    _assignDefaultStyleToFeature: function(affected_layer, style)
    {

        let that = this;
        this.eachLayerFeature(affected_layer, function(layer){
                let use_style = style;
                if(layer instanceof L.Polyline && !(layer instanceof L.Polygon)){
                    use_style = window.hWin.HEURIST4.util.cloneJSON( style );
                    use_style.fill = false;
                }
                
                layer.feature.default_style = use_style;
                layer.options.selectable = affected_layer.options.selectable;
        });
        
    },
    
    //
    // apply style for particular layer (map element)  (currently used in app_storemap)
    // it does not take thematic map into account
    //
    applyStyleForLayer: function(top_layer, layer, newStyle) {
        
        let style = this.setStyleDefaultValues(newStyle);
        
        this._assignDefaultStyleToFeature(layer, style);
        
        //for markers
        this.applyStyleForMarker(top_layer, layer, style);
        
        //for other (polygones, polylines)
        let that = this;
        top_layer.setStyle(function(feature){ return that._stylerForPoly(feature); });
        
    },

    //
    // for marker "layer" that belongs to given "top_layer"
    //
    applyStyleForMarker: function(top_layer, layer, markerStyle, setIcon) 
    {
        
        let parent_id = 0;

        if(top_layer instanceof L.Layer){
            parent_id = top_layer._leaflet_id;   
        }else if(top_layer>0){
            parent_id = top_layer;
        }

        if(parent_id>0 && !this.all_markers[parent_id]) return;  //there is no markers for this parent layer

        let that = this;
        
        markerStyle = this.setStyleDefaultValues(markerStyle);
        
        if(!setIcon){
            setIcon = this._createMarkerIcon( markerStyle );
        }

        //define icon for record type                                        
        if(markerStyle.iconType=='rectype' )
        {
            let feature = layer.feature;
            
            let rty_ID = feature.properties.rec_RecTypeID;
            if(that.myIconRectypes[rty_ID+'_'+markerStyle.iconSize+'_'+markerStyle.color]){ //cache
                setIcon = that.myIconRectypes[rty_ID+'_'+markerStyle.iconSize+'_'+markerStyle.color];
            }else{
                let fsize = markerStyle.iconSize;
                if(markerStyle.color){
                    setIcon = L.divIcon({  
                        html: '<img src="'
                        +window.hWin.HAPI4.iconBaseURL + rty_ID
                        +'" style="width:'+fsize+'px;height:'+fsize+'px;filter:'
                        +hexToFilter(markerStyle.color)+'"/>',
                        iconSize:[fsize, fsize]
                        //iconAnchor:[fsize/2, fsize/4]
                    });
                }else{
                    setIcon = L.icon({
                        iconUrl: window.hWin.HAPI4.iconBaseURL + rty_ID, 
                            //+ '&color='+encodeURIComponent(markerStyle.color)
                            //+ '&bg='+encodeURIComponent('#ffffff')),
                        iconSize: [fsize, fsize]                        
                    });
                }
                
                
                
                that.myIconRectypes[rty_ID+'_'+markerStyle.iconSize+'_'+markerStyle.color] = setIcon;
            }
        }
        
        let new_layer = null;
        if(layer instanceof L.Marker){
            if(markerStyle.iconType=='circle'){
                //change to circleMarker
                markerStyle.radius = markerStyle.iconSize/2;
                new_layer = L.circleMarker(layer.getLatLng(), markerStyle);    
                new_layer.feature = layer.feature;
            }else{
                layer.setIcon(setIcon);    
                layer.setOpacity( markerStyle.opacity );
            }

        }else if(layer instanceof L.CircleMarker){
            if(markerStyle.iconType!='circle'){
                //change from circle to icon marker
                new_layer = L.marker(layer.getLatLng(), {icon:setIcon, opacity:markerStyle.opacity });
                new_layer.feature = layer.feature;
            }else{
                markerStyle.radius = markerStyle.iconSize/2;
                layer.setStyle(markerStyle);
                //layer.setRadius(markerStyle.iconSize);                    
            }
        }   
        
        if(new_layer!=null){ //replace old marker to new one
            that._replaceMarker(parent_id, layer, new_layer);
            //that.all_markers[parent_id][idx] = new_layer; 
        }
    },

    
    //
    // map layer (shape) on click event handler - highlight selection on timeline and map, opens popup
    //
    // content of popup can be retrieved by rec_ID via 
    //  1. renderRecordData.php script
    //  2. field rec_Info can have url, content or be calculated field (function)
    // 
    _onLayerClick: function(event){
        
        let layer = (event.target);
        if(layer && layer.feature){

            //add selected to other selected features
            let add_to_selection = (event.originalEvent.ctrlKey);
            
            if(layer.feature.properties.rec_ID>0){
                //find all overlapped polygones under click point
                if(layer instanceof L.Polygon || layer instanceof L.Circle || layer instanceof L.Rectangle){
                    
                        let selected_layers = {};
                        let sText = '';
                        let latlng = event.latlng;
                        
                        //scan all visible layers
                        this.nativemap.eachLayer(function(top_layer){    
                            if(top_layer.feature && //top_layer.feature.properties.rec_ID!=layer.feature.properties.rec_ID && 
                                (top_layer instanceof L.Polygon || top_layer instanceof L.Circle || top_layer instanceof L.Rectangle)){
                                
                                    if(top_layer.contains(latlng)){
                                        selected_layers[top_layer._leaflet_id] = top_layer;
                                        let title = top_layer.feature.properties.rec_Title;
                                        sText = sText + '<div class="leaflet_layer_opt" title="'+title+'" data-id="'+top_layer._leaflet_id+'">'+title+'</div>';
                                    }
                                    
                            }
                        });
                        
                        let found_cnt = Object.keys(selected_layers).length;
                        
                        if(found_cnt>1){
                            //show popup with selector
                            this._showMultiSelectionPopup(latlng, sText, selected_layers, add_to_selection);
                            return;
                        }
                        
                }

            }                

            this._onLayerSelect( layer, event.latlng, add_to_selection );
            
        }
    },

    //
    // select layer among several selected
    //    
    _showMultiSelectionPopup: function(latlng, sText, selected_layers, add_to_selection){
        
        let found_cnt = Object.keys(selected_layers).length;        
        
        this.main_popup.setLatLng(latlng)
                        .setContent('<p style="margin:12px;font-style:italic">'
                                +found_cnt+' map objects found here. Select desired: </p>'
                                +'<div style="width:100%;max-height: 170px;overflow-y: auto;border: none;outline: none; cursor:pointer">'
                                +sText+'</div>') 
                        .openOn(this.nativemap);

        $(this.main_popup.getElement()).css({
            width: '300px'
        })

        let that = this;
            
        let ele = $(this.main_popup._container).find('.leaflet_layer_opt');
        ele.on({'click':function(evt){
            let $ele = $(evt.target);
            if(!$ele.hasClass('leaflet_layer_opt')){
                $ele = $ele.parents('.leaflet_layer_opt:first');
            }

            let leaflet_id = $ele.attr('data-id');
            that._onLayerSelect(selected_layers[leaflet_id], latlng, add_to_selection);
        },'mousemove':function(evt){
            let $ele = $(evt.target);
            if(!$ele.hasClass('leaflet_layer_opt')){
                $ele = $ele.parents('.leaflet_layer_opt:first');
            }

            let leaflet_id = $ele.attr('data-id');
            if(leaflet_id>0){
                $ele.siblings().removeClass('selected');
                $ele.addClass('selected');
                let layer = selected_layers[leaflet_id];
                that.setFeatureSelection([layer.feature.properties.rec_ID], false, false, add_to_selection); //highlight from popup
            }
        }});
    },

    //
    // highlight and show info popup
    //  see that.options.map_popup_mode - show in map popup or in jquery dialog
    // content can be taken from
    //   1. rec_Info field
    //   2. layer.options.popup_template
    //   3. mapPopUpTemplate
    //
    _onLayerSelect: function(layer, latlng, add_to_selection){

        if(layer.options && layer.options.selectable===false)
        {
            return;  
        } 
        
        let that = this;
        let popupURL;

        function __showPopup(content, latlng){
            
            if(that.options.map_popup_mode=='standard'){ //show in map popup control
                
                that.main_popup.setLatLng(latlng)
                            .setContent(content)
                            .openOn(that.nativemap);

                let $popup_ele = $(that.main_popup.getElement()); // popup container
                let $content = $popup_ele.find('.leaflet-popup-content'); // content container

                // Default options
                let width = 'auto',
                    height = 'auto',
                    resizable = true,
                    maxw = '',
                    maxh = '94%';

                let behaviour = that.options.layout_params['popup_behaviour'];

                // For CMS websites
                if(behaviour == 'fixed'){
                    width = (that.options.layout_params['popup_width'] != null) ? that.options.layout_params['popup_width'] : width;
                    height = (that.options.layout_params['popup_height'] != null) ? that.options.layout_params['popup_height'] : height;
                }else if(behaviour == 'fixed_width'){
                    width = (that.options.layout_params['popup_width'] != null) ? that.options.layout_params['popup_width'] : width;
                    maxh = (that.options.layout_params['popup_height'] != null) ? that.options.layout_params['popup_height'] : '94%';
                }else if(behaviour == 'scale'){
                    maxw = (that.options.layout_params['popup_width'] != null) ? that.options.layout_params['popup_width'] : '';
                    maxh = (that.options.layout_params['popup_height'] != null) ? that.options.layout_params['popup_height'] : '94%';
                }

                // user preference, session cached only
                if(window.hWin.HEURIST4.leaflet_popup){
                    width = window.hWin.HEURIST4.leaflet_popup.width;
                    height = window.hWin.HEURIST4.leaflet_popup.height;
                }                

                if(that.options.layout_params['popup_resizing'] != null){
                    resizable = that.options.layout_params['popup_resizing'];
                }

                $popup_ele.css({
                    'width': width,
                    'height': height
                });

                $content.css({
                    'max-width': maxw,
                    'max-height': maxh,
                    'overflow': 'auto'
                });

                /*if(!(that.mapPopUpTemplate || layer.options.popup_template) && width == 'auto' && height == 'auto'){
                    $popup_ele.css({'height': '300px'});
                }*/

                that.main_popup.update();

                if(resizable == 'false' || resizable === false){

                    $popup_ele.find('.leaflet-popup-content-wrapper').resizable({
                        ghost: true,
                        stop: function(event, ui){

                            let dims = ui.size;
                            window.hWin.HEURIST4.leaflet_popup = dims; // cache width and height

                            $popup_ele.css(dims); // update popup's dimensions
                            $popup_ele.find('.leaflet-popup-content-wrapper').css({left: '', top: ''}); // remove top and left changes

                            that.main_popup.update();
                        },
                        handles: 'all'
                    });
                }else if($popup_ele.resizable('instance') !== undefined){
                    $popup_ele.resizable('destroy');
                }

                this._on($popup_ele.find('.login-viewer'), {
                    click: () => window.hWin.HEURIST4.ui.checkAndLogin(true, () => {location.reload();})
                });

            }else if(that.options.map_popup_mode=='dialog'){

                window.hWin.HEURIST4.msg.showMsg(content);
            }
        }

        if(layer.feature.properties.rec_ID>0){
            
            that.setFeatureSelection([layer.feature.properties.rec_ID], false, false, add_to_selection); //highlight without zoom
            
            if(!add_to_selection){

            let info = layer.feature.properties.rec_Info; //popup info may be already prepared
            if(info){
                if(info.indexOf('http://')==0 || info.indexOf('https://')==0){
                    popupURL =  info; //load content from url
                }
            }else{
                
                if(layer.options.popup_template=='none' || (layer.options.popup_template==null && that.mapPopUpTemplate=='none') ){
                    that.main_popup.closePopup();
                    return;
                }
                //take database from dataset origination database
                let db = layer.options.origination_db!=null
                                ?layer.options.origination_db
                                :window.hWin.HAPI4.database;
                    
                
                if((that.mapPopUpTemplate && that.mapPopUpTemplate!='standard') || layer.options.popup_template){
                    
                    popupURL = window.hWin.HAPI4.baseURL + '?snippet=1&publish=1&debug=0&q=ids:'
                            + layer.feature.properties.rec_ID
                            + '&db='+db+'&template='
                            + encodeURIComponent(layer.options.popup_template || that.mapPopUpTemplate);
                }else{
                    
                    popupURL = window.hWin.HAPI4.baseURL + 'viewers/record/renderRecordData.php?recID='
                            +layer.feature.properties.rec_ID
                            +'&db='+db;
                    
                    if(that.options.map_popup_mode=='dialog' || that.mapPopUpTemplate=='standard'){
                        that.options.map_popup_mode='dialog';
                        popupURL = popupURL + '&ll=WebSearch';
                    }else{
                        popupURL = popupURL+'&mapPopup=1&ll='+window.hWin.HAPI4.sysinfo['layout'];    
                    }
                    
                    if(that.options.recviewer_images!=0){
                        popupURL = popupURL + '&hideImages=' + that.options.recviewer_images;
                    }
                }  
            }              
            //open popup
            if(popupURL){
                
                if(that.options.map_popup_mode=='dialog'){
                    
                        let opts = { 
                                is_h6style: true,
                                modal: false,
                                dialogid: 'recordview_popup',    
                                //onmouseover: function(){that._clearTimeouts();},
                                title:window.hWin.HR('Info')}                
                    
                        window.hWin.HEURIST4.msg.showDialog(popupURL, opts);
                        
                }else if(that.options.map_popup_mode!='none'){
                    $.get(popupURL, function(responseTxt, statusTxt, xhr){
                        if(statusTxt == "success"){
                            __showPopup(responseTxt, latlng);
                        }
                    });
                }
            }else{
                __showPopup(info, latlng);
            }
            
            }  // !add_to_selection
    
        }else{
            // show multiple selection
            let sText = '';    
            for(let key in layer.feature.properties) {
                if(Object.hasOwn(layer.feature.properties,key) && key!='_deleted'){
                       sText = sText 
                        + '<div class="detailRow fieldRow" style="border:none 1px #00ff00;">'
                        + '<div class="detailType">'+key+'</div><div class="detail truncate">'
                        + window.hWin.HEURIST4.util.htmlEscape(layer.feature.properties[key])
                        + '</div></div>';                
                }
            }
            if(sText!=''){
                sText = '<div class="map_popup">' + sText + '</div>';
                __showPopup(sText, latlng);
            }
        }
    },
    
    //
    // remove special "highlight" selection circle markers from map
    //
    _clearHighlightedMarkers: function(affected_layer_id){

        let idx = 0;
        if(this.highlightedMarkers){
            while(idx < this.highlightedMarkers.length){
                if( !(affected_layer_id>0) || this.highlightedMarkers[idx].parent_layer_id == affected_layer_id){
                    this.highlightedMarkers[idx].remove();  
                    if(affected_layer_id>0){
                        this.highlightedMarkers.splice(idx,1);
                        continue;        
                    }
                }
                idx++;    
            }
        }
        if(!(affected_layer_id>0)){
            this.highlightedMarkers = [];  
        }

    },

    //
    // assigns default values for style (size,color and marker type)
    // default values priority: widget options, topmost mapdocument, user preferences
    //
    //  suppress_prefs - false - take default style values from user preference
    //
    setStyleDefaultValues: function(style, suppress_prefs, is_selection_style){
        
        //take map style from user preferences
        let def_style = null;
        if(suppress_prefs!==true){
            
            if(this.options.default_style){
                //take default style from widget parameters
                def_style = this.options.default_style;
            }else{
                //take default style from topmost map document
                def_style = this.mapManager.getSymbology();
            }
            
            if(!def_style){
                //otherwise from user preferences
                def_style = window.hWin.HAPI4.get_prefs('map_default_style');
                if(def_style) def_style = window.hWin.HEURIST4.util.isJSON(def_style);
            }
            def_style = this.setStyleDefaultValues(def_style, true, is_selection_style);
        }
        else if(is_selection_style){
            //options for selection style
            // 1. from widget parameters
            // 2. from user preferences
            // 3. default (light blue)
            
            if(this.options.default_selection_style){
                //take default style from widget parameters
                def_style = this.options.default_selection_style;
            }
            if(!def_style){
                def_style = window.hWin.HAPI4.get_prefs('map_select_style');
                if(def_style) def_style = window.hWin.HEURIST4.util.isJSON(def_style);
            }
            if(!def_style){
                def_style = {iconType:'circle', color:'#62A7F8', fillColor:'#e6fdeb', weight:3, opacity:1, 
                        dashArray: '',
                        fillOpacity:0.3, iconSize:18, stroke:true, fill:true};
            }
            def_style.weight = (window.hWin.HEURIST4.util.isNumber(def_style.opacity) && def_style.weight>=0) ?def_style.weight :3;
            def_style.opacity = (window.hWin.HEURIST4.util.isNumber(def_style.opacity) && def_style.opacity>=0) ?def_style.opacity :1;
            def_style.fillOpacity = (window.hWin.HEURIST4.util.isNumber(def_style.fillOpacity) && def_style.fillOpacity>=0) ?def_style.fillOpacity :0.3;
            def_style.fill = true;
            def_style.stroke = true;
            
           
        }
        
        style = window.hWin.HEURIST4.ui.prepareMapSymbol(style, def_style);
        
        return style;
    },        
    
    //
    // creates marker icon for url(image) and fonticon (divicon)
    //
    _createMarkerIcon: function(style){
        
        let myIcon;
      
        if(style.iconType=='url'){
            
            let fsize = style.iconSize;
            
            if( typeof fsize == 'string' && fsize.indexOf(',')>0){
                fsize = fsize.split(',');
                style.iconWidth = fsize[0];
                style.iconHeight = fsize[1];
            }
            if (style.iconWidth>0 && style.iconHeight>0){
                fsize = [style.iconWidth, style.iconHeight];
            }else{
                fsize = [fsize, fsize];
            }
            
            myIcon = L.icon({
                iconUrl: style.iconUrl
                ,iconSize: fsize
                /*iconAnchor: [22, 94],
                popupAnchor: [-3, -76],
                shadowUrl: 'my-icon-shadow.png',
                shadowSize: [68, 95],
                shadowAnchor: [22, 94]*/
            });      
            //marker_style = {icon:myIcon};
            
        }else if(style.iconType=='iconfont'){
            
            if(!style.iconFont) style.iconFont = 'location';
            
            let cname = (style.iconFont.indexOf('ui-icon-')==0?'':'ui-icon-')+style.iconFont;
            let fsize = style.iconSize;
            let isize = 6+fsize;
            let bgcolor = (style.fillColor0?(';background-color:'+style.fillColor):';background:none');
            
            myIcon = L.divIcon({  
                html: '<div class="ui-icon '+cname+'" style="border:none;font-size:'    //padding:2px;;width:'+isize+'px;
                        +fsize+'px;width:'+fsize+'px;height:'+fsize+'px;color:'+style.color+bgcolor+'"/>',
                iconSize:[fsize, fsize],
                iconAnchor:[fsize/2, fsize/4]
            });
            
            //marker_style = {icon:myIcon};
        }  
        
        return myIcon;       
    },
    
    //
    // returns style for every path and polygone, either individual feature style of parent layer style.
    // for markers style is defined in applyStyle
    //
    _stylerForPoly: function(feature){
        
        //feature.style - individual style (set in symbology field per record)
        //feature.default_style - style of parent heurist layer (can be changed via legend)
        
        let use_style = feature.thematic_style || feature.style || feature.default_style;

        if(feature.thematic_style===false){ //hidden
            return use_style;
        }
       
        /* expremental HARDCODE for HIE
        if(this.isHamburgIslamicImpire){
            if( window.hWin.HEURIST4.util.findArrayIndex(feature.properties.rec_ID, this.hie_places_with_events)<0 ){ 
            //if(that.hie_places_with_events.indexOf(child_layer.feature.properties.rec_ID)<0){
                use_style = this.hie_places_wo_events_style;
            }
        }
        */
        
        if(feature.geometry && feature.geometry.type=='GeometryCollection'){
            use_style = window.hWin.HEURIST4.util.cloneJSON( use_style );
            use_style.fill = false;
        }
        
        //change color for selected features
        if( feature.properties && this.selected_rec_ids.indexOf( feature.properties.rec_ID )>=0)
        {
            use_style = window.hWin.HEURIST4.util.cloneJSON( use_style );
            use_style.color = this.selection_style.color; //'#62A7F8'; 
            use_style.fillColor = this.selection_style.fillColor; //'#e6fdeb';
            use_style.fillOpacity = this.selection_style.fillOpacity; //0.3;
        }
        
        return use_style;
        
    },
    
    //
    // highlight and zoom (if external call)
    //
    // triggers redraw for path and polygones (assigns styler function)  and creates highlight circles for markers
    // is_external - true - public call (from app_timemap for example)  - perform zoom
    //    
    setFeatureSelection: function( _selection, _need_zoom, _from_timeline, add_to_selection){
        let that = this;
        
        if(add_to_selection){
            if(window.hWin.HEURIST4.util.isArrayNotEmpty(_selection)){
                //to avoid duplication in highlightedMarkers
                _selection = _selection.filter(function(i) {return that.selected_rec_ids.indexOf(i) < 0;});
                this.selected_rec_ids = this.selected_rec_ids.concat(_selection);
            }
        }else{
            this._clearHighlightedMarkers();
            this.highlightedMarkers = [];
            this.selected_rec_ids = (window.hWin.HEURIST4.util.isArrayNotEmpty(_selection)) ?_selection:[];
        }
        
        that.nativemap.eachLayer(function(top_layer){    
            if(top_layer instanceof L.LayerGroup){  //apply only for geojson
                top_layer.setStyle(function(feature) { return that._stylerForPoly(feature); });
            }
        });
        
        //find selected markers by id
        let selected_markers = this._getMarkersByRecordID(_selection); //new selected markers
        for(let idx in selected_markers){
            let layer = selected_markers[idx];
            if(!(layer.hidden_by_filter || layer.hidden_by_theme || layer.hidden_by_zoom)){

                //create special hightlight marker below this one
                let use_style = layer.feature.style || layer.feature.default_style;
                let iconSize = ((use_style && use_style.iconSize>0)?use_style.iconSize:16);
                let radius = iconSize/2+3;
                //iconSize = ((layer instanceof L.CircleMarker) ?(iconSize+2) :(iconSize/2+4));
                
                let new_layer = L.circleMarker(layer.getLatLng(), {color: this.selection_style.color} );//'#62A7F8'   
                
                new_layer.setRadius(radius);
                new_layer.addTo( this.nativemap );
                new_layer.bringToBack();
                if(layer.parent_layer) {
                    new_layer.parent_layer_id = layer.parent_layer._leaflet_id;   
                }
                this.highlightedMarkers.push(new_layer);
            }
        }        
          
        //this.main_layer.remove();
        //this.main_layer.addTo( this.nativemap );
        if (!(_from_timeline===true || this.notimeline)) {
            this.vistimeline.timeline('setSelection', this.selected_rec_ids);
        }
        if(_need_zoom){    
            this.zoomToSelection();        
        }
        
        if(window.hWin.HEURIST4.util.isFunction(this.options.onselect)){
            this.options.onselect.call(this, this.selected_rec_ids );
        }
        
    },
    
    //
    // 
    //    
    findLayerByRecID: function(recIDs){
        
        let that = this;
        let res = [];
        
        function __eachLayer(layer, method) {
            for (let i in layer._layers) {
                let res = method.call(this, layer._layers[i]);
                if(res===false){
                    return false;
                }
            }
            return true;
        }
        
        function __validateLayer(layer){
            if (layer instanceof L.Layer && layer.feature && layer.feature.properties &&
                (window.hWin.HEURIST4.util.findArrayIndex(layer.feature.properties.rec_ID, recIDs)>=0)){
                
                res.push(layer);
                if(recIDs.length==1) return false;
            }
            return true;
        }
        
        that.nativemap.eachLayer(function(top_layer){    
            if(top_layer instanceof L.LayerGroup)   //geojson only
            {
                let r = top_layer.eachLayer(function(layer){
                    if (layer instanceof L.Layer && layer.feature && //(!(layer.cluster_layer_id>0)) &&
                        (window.hWin.HEURIST4.util.findArrayIndex(layer.feature.properties.rec_ID, recIDs)>=0)) 
                    {
                        res.push(layer);
                    }
                });
            }    
        });
        

        return res;        
    },

    //
    // Testing for fadeIn and fadeOut
    //
    fadeInLayers: function( _selection){
        let layers = this.findLayerByRecID( _selection );

        let opacity = 0, finalOpacity=1, opacityStep=0.1, delay=200;
        let timer = setTimeout(function changeOpacity() {
            if (opacity < finalOpacity) {
                $.each(layers,function(i, lyr){
                    
                    if(lyr instanceof L.Marker){
                        lyr.setOpacity( opacity );                        
                    }else{
                        lyr.setStyle({
                            opacity: opacity,
                            fillOpacity: opacity
                        });
                    }
                });
                opacity = opacity + opacityStep
            }

            timer = setTimeout(changeOpacity, delay);
        }, delay);

    },
    
    //
    // get bounds for selection
    //
    zoomToSelection: function( _selection, _fly_params ){
    
        if(!this.need_zoom_on_selection) return;
        
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(_selection)){
            _selection  =  this.selected_rec_ids;
        }
        
        let that = this, bounds = [], bnd;

        let useRuler = (_selection.length==1);
        
        that.nativemap.eachLayer(function(top_layer){    
            if(top_layer instanceof L.LayerGroup)   //geojson only
                top_layer.eachLayer(function(layer){
                    if (layer instanceof L.Layer && layer.feature && //(!(layer.cluster_layer_id>0)) &&
                        (!(layer.hidden_by_filter || layer.hidden_by_theme || layer.hidden_by_zoom)) &&
                        (window.hWin.HEURIST4.util.findArrayIndex(layer.feature.properties.rec_ID, _selection)>=0)) 
                    {
                        bounds.push( that.getLayerBounds(layer, useRuler) );
                    }
                });
        });

        bounds = this._mergeBounds(bounds);
        
        this.zoomToBounds(bounds, _fly_params);    
    },

    
    //
    // returns markers by heurist ids (for filter and highlight)
    //
    _getMarkersByRecordID: function( _selection ){
        
        let selected_markers = [];
        
        if(_selection && _selection.length>0)
        for(let layer_id in this.all_markers) {
            if(Object.hasOwn(this.all_markers,layer_id)){
                    let markers = this.all_markers[layer_id];
                    $(markers).each(function(idx, layer){
                        if (_selection===true || (layer.feature &&
                         window.hWin.HEURIST4.util.findArrayIndex(layer.feature.properties.rec_ID, _selection)>=0)){
                              selected_markers.push( layer );
                         }
                    });
            }
        }        
        
        return selected_markers;
    },

    //
    // Replace marker
    //    
    _replaceMarker: function( parent_id, old_marker, new_marker ){

        let is_found = false;    
        let that = this;
        for(let layer_id in this.all_markers) {
            if(Object.hasOwn(this.all_markers,layer_id))
            {
                    let markers = this.all_markers[layer_id];
                    $(markers).each(function(idx, layer){
                        if(layer._leaflet_id == old_marker._leaflet_id){
                            that.all_markers[layer_id][idx] = new_marker; 
                            
            
                            if(!that.isMarkerClusterEnabled){
                                layer.parent_layer.addLayer(new_marker);
                                layer.parent_layer.removeLayer(layer);
                                layer.remove();
                                layer = null;
                            }
                            new_marker.on('click', function(e){that._onLayerClick(e)});
                         
                            if(that.options.map_rollover){
                                new_marker.bindTooltip(function (layer) {
                                    return layer.feature.properties.rec_Title;
                                })
                            }                
                            is_found = true;
                            return false;
                        }
                    });
                    if(is_found) break;
            }
        }        
        
    },

    
    //
    //
    //
    getLayerBounds: function (layer, useRuler){
        
        let that = this;

        function __extendBoundsForPoint(ll){
            
            if(useRuler && that.options.zoomToPointInKM>0){ //zoom to single point
            
                let ruler = cheapRuler(ll.lat);
                let bbox = ruler.bufferPoint([ll.lng, ll.lat], that.options.zoomToPointInKM/4);   //0.01          
                //w, s, e, n
                const corner1 = L.latLng(bbox[1], bbox[0]),
                    corner2 = L.latLng(bbox[3], bbox[2]);
                    
                return L.latLngBounds(corner1, corner2);            
            }else{
                //for city 0.002 for country 0.02
                const corner1 = L.latLng(ll.lat-0.02, ll.lng-0.02),
                    corner2 = L.latLng(ll.lat+0.02, ll.lng+0.02);
                return L.latLngBounds(corner1, corner2);            
            }
        }
        
        if(layer instanceof L.Marker || layer instanceof L.CircleMarker){    
            let ll = layer.getLatLng();
            
            //if field 2-925 is set (zoom to point in km) use it
            return __extendBoundsForPoint(ll);
            
        }else{
            let bnd = layer.getBounds();
            if(bnd && bnd.isValid()){
                let p1 = bnd.getSouthWest();
                let p2 = bnd.getNorthEast();
                if(Math.abs(p1.lat-p2.lat)<0.01 && Math.abs(p1.lng-p2.lng)<0.01){
                    return __extendBoundsForPoint(p1);
                }
            }else{
                bnd = null
            }
            
            return bnd;
        }
    },
    
    //
    // show (and zoom)/hide the specific set of objects for layer
    // dataset_id -  {mapdoc_id:, dataset_name:, dataset_id:  or native_id}
    // _selection - show_all/hide_all|or array of ids
    //
    setVisibilityAndZoom: function( dataset_id, _selection, need_zoom ){
        
        let check_function = null;

        if(_selection=='show_all'){
            
            check_function = function(rec_ID){return true};
            
        }else if (_selection=='hide_all'){

            check_function = function(rec_ID){return false};
            
        }else if(window.hWin.HEURIST4.util.isArrayNotEmpty(_selection)) {
            check_function = function(rec_ID){
                return (window.hWin.HEURIST4.util.findArrayIndex(rec_ID, _selection)>=0);
            }
        }

        if(check_function!=null){
    
            this._clearHighlightedMarkers();
            
            let _leaflet_id = this.mapManager.getLayerNativeId(dataset_id); //get _leaflet_id by mapdoc and dataset name
            
            //use  window.hWin.HEURIST4.util.findArrayIndex(layer.properties.rec_ID, _selection)
            let that = this, bounds = [];
        
            that.nativemap.eachLayer(function(top_layer){    
                if((top_layer instanceof L.LayerGroup) && (_leaflet_id==0 || _leaflet_id==top_layer._leaflet_id)){
                    top_layer.eachLayer(function(layer){
                          if (layer instanceof L.Layer && layer.feature && (!(layer.cluster_layer_id>0)) &&
                                check_function( layer.feature.properties.rec_ID )
                            ) 
                          {
                                if(!layer._map){
                                    layer.addTo( that.nativemap );   //to show  
                                }
                                bounds.push( that.getLayerBounds(layer) );
                                
                          }else{
                                layer.remove(); //to hide    
                          }
                          
                    });
                }
            });
            
            
            if(this.isMarkerClusterEnabled){
                /*  @todo
                var selected_markers = this._getMarkersByRecordID(_selection);
                for(var idx in selected_markers){

                    var layer = selected_markers[idx];
                    if(layer.cluster_layer_id>0 && that.all_clusters[layer.cluster_layer_id]){
                        
                            if(!that.all_clusters[layer.cluster_layer_id].hasLayer(layer)){
                                that.all_clusters[layer.cluster_layer_id].addLayer(layer);
                            }
                    }else                        
                        that.all_clusters[layer.cluster_layer_id].removeLayer(layer);
                    }
                }
                */
            }

            if(need_zoom!==false){
                bounds = this._mergeBounds(bounds);
                this.zoomToBounds(bounds);
            }
                
        }
        
        
        
    },

    //
    //  applies visibility for given set of heurist recIds (filter from timeline)
    // _selection - true - apply all layers, or array of rec_IDs
    // origin: 0 -timeline, 1 - zoom
    //
    setFeatureVisibility: function( _selection, is_visible, origin ){
        
        if(_selection===true || window.hWin.HEURIST4.util.isArrayNotEmpty(_selection)) {
            
            let vis_val = (is_visible==false)?'none':'block';
            
            this._clearHighlightedMarkers();
            
            //use  window.hWin.HEURIST4.util.findArrayIndex(layer.properties.rec_ID, _selection)
            let that = this;
        
            that.nativemap.eachLayer(function(top_layer){    
                if(top_layer instanceof L.LayerGroup)
                    top_layer.eachLayer(function(layer){
                        if (layer instanceof L.Layer && layer.feature && (!(layer.cluster_layer_id>0)) &&
                            ( _selection===true || 
                                window.hWin.HEURIST4.util.findArrayIndex(layer.feature.properties.rec_ID, _selection)>=0)) 
                        {
                            if(is_visible==false){
                                if(origin==0){
                                    layer.hidden_by_filter = true;    
                                }else{
                                    layer.hidden_by_zoom = true;        
                                }
                                layer.remove();    
                            }else{
                                if(origin==0){
                                    layer.hidden_by_filter = false;    
                                }else{
                                    layer.hidden_by_zoom = false;        
                                }
                                if(!layer._map && !(layer.hidden_by_theme || layer.hidden_by_zoom || layer.hidden_by_zoom)){
                                    layer.addTo( that.nativemap );
                                }
                            }

                            /*
                            if(window.hWin.HEURIST4.util.isFunction(layer.getElement)){
                            var ele = layer.getElement();
                            if(ele) ele.style.display = vis_val;
                            }else{
                            layer.setStyle({display:vis_val});
                            }
                            */
                        }
                    });
            });
            
            
            if(this.isMarkerClusterEnabled){
                
                let selected_markers = this._getMarkersByRecordID(_selection);
                for(let idx in selected_markers){

                    let layer = selected_markers[idx];
                    if(layer.cluster_layer_id>0 && that.all_clusters[layer.cluster_layer_id]){
                        if(is_visible==false){
                            if(origin==0){
                                layer.hidden_by_filter = true;    
                            }else{
                                layer.hidden_by_zoom = true;        
                            }
                            layer.hidden_by_filter = true;
                            that.all_clusters[layer.cluster_layer_id].removeLayer(layer);
                        }else {
                            layer.hidden_by_filter = false; 
                            if(origin==0){
                                layer.hidden_by_filter = false;    
                            }else{
                                layer.hidden_by_zoom = false;        
                            }
                            if(!(layer.hidden_by_theme || layer.hidden_by_zoom || layer.hidden_by_zoom) && !that.all_clusters[layer.cluster_layer_id].hasLayer(layer)){
                                that.all_clusters[layer.cluster_layer_id].addLayer(layer);
                            }
                        }
                    }
                }
                
                /*
                for(var layer_id in this.all_markers) {
                    if(Object.hasOwn(this.all_markers,layer_id)){
                            var markers = this.all_markers[layer_id];
                            $(markers).each(function(idx, layer){
                                if (_selection===true || (layer.feature &&
                                 window.hWin.HEURIST4.util.findArrayIndex(layer.feature.properties.rec_ID, _selection)>=0)){
                                     
                                      if(is_visible==false){
                                          that.all_clusters[layer.cluster_layer_id].removeLayer(layer);
                                      }else {
                                            if(!that.all_clusters[layer.cluster_layer_id].hasLayer(layer)){
                                                that.all_clusters[layer.cluster_layer_id].addLayer(layer);
                                            }
                                      }
                                 }
                            });
                    }
                }*/
                
                
            }
            
        }
        
    },
    
    //
    // show/hide layout panels and map controls
    // params: 
    //   nomap, notimeline
    //   controls: [all,none,zoom,bookmark,geocoder,print,publish,legend]
    //   legend: [basemaps,search,mapdocs|onedoc]
    //   basemaps: list of available basemaps
    //   basemap: name of initial basemap
    //   basemap_filter: css filter for basemap layer
    //   extent: fixed extent    
    //
    updateLayout: function(){
        
        let params = this.options.layout_params;
        
        let that = this;
       
        function __parseval(val){
            if(val===false || val===true) return val;
            if(!window.hWin.HEURIST4.util.isempty(val)){
                if(typeof val == 'string') val = val.toLowerCase();
                return !(val==0 || val=='off' || val=='no' || val=='n' || val=='false');
            }else{
                return false;
            }
        }
        function __splitval(val){
            
            let res = window.hWin.HEURIST4.util.isJSON(val);
            if(res === false){
            
                res = [];
                if(!Array.isArray(val)){
                    if(!val) val = 'all';
                    val = val.toLowerCase();
                    res = val.split(',');
                }
                if(!(res.length>0)) res = val.split(';');
                if(!(res.length>0)) res = val.split('|');
                
                if(res.length==0) res.push('all');
                
            }
            
            return res;
        }


        this.options.isPublished = (params && __parseval(params['published'])) || !window.hWin.HAPI4.has_access();
        
        //if parameters are not defined - takes default values from user preferences
        if(window.hWin.HEURIST4.util.isempty(params) || !this.options.isPublished){
            //this is not publish take params from preferences
            if(window.hWin.HEURIST4.util.isempty(params)) params = {};
            
            if(window.hWin.HEURIST4.util.isempty(params['map_rollover']))
                params['map_rollover'] = (window.hWin.HAPI4.get_prefs_def('map_rollover', 0)==1);
            if(window.hWin.HEURIST4.util.isempty(params['template']))
                params['template'] = window.hWin.HAPI4.get_prefs_def('map_template', null);
            if(window.hWin.HEURIST4.util.isempty(params['nocluster']))
                params['nocluster'] = (window.hWin.HAPI4.get_prefs_def('mapcluster_on', 0)!=1);
            if(window.hWin.HEURIST4.util.isempty(params['controls']))
                params['controls'] = window.hWin.HAPI4.get_prefs_def('mapcontrols', 'all');
            if(window.hWin.HEURIST4.util.isempty(params['nocluster']))
                params['nocluster'] = (window.hWin.HAPI4.get_prefs_def('mapcluster_on', 1)==0);
            
            params['controls'] = params['controls']+',legend'; //is always visible for non-published maps
            
            this.markerClusterGridSize = parseInt(window.hWin.HAPI4.get_prefs_def('mapcluster_grid', 50));
            this.markerClusterMaxZoom = parseInt(window.hWin.HAPI4.get_prefs_def('mapcluster_zoom', 18));
            this.markerClusterMaxSpider = parseInt(window.hWin.HAPI4.get_prefs_def('mapcluster_spider', 5));
        }
        
        
        //@todo deriveMapLocation
        
        //maxClusterRadius
        this.isMarkerClusterEnabled = !__parseval(params['nocluster']);
        this.options.isEditAllowed = !this.options.isPublished || __parseval(params['editstyle']);
        if(params['zoom_to_selected']!=null){
            this.need_zoom_on_selection = __parseval(params['zoom_to_selected']);    
        }

        if(this.options.layout_params['smooth_zoom']){
            this.nativemap.options.zoomSnap = 0.1;
            //this.nativemap.options.wheelDebounceTime = 100;
        }else{
            this.nativemap.options.zoomSnap = 1;
        }
        
        this.zoom_delta = params['zoom_delta'];
        if(this.zoom_delta>0){ //step (in levels) for zoom buttons (+/-)
            this.nativemap.options.zoomDelta = this.zoom_delta;
        }else{
            this.nativemap.options.zoomDelta = 1;
        }
        
        this.options.map_popup_mode = params['popup']; //standard, none, in dialog
        if(!this.options.map_popup_mode) this.options.map_popup_mode = 'standard';
        
        this.options.map_rollover = __parseval(params['map_rollover']);
        this.options.default_style = window.hWin.HEURIST4.util.isJSON(params['style']);
        this.options.default_selection_style = window.hWin.HEURIST4.util.isJSON(params['selection_style']);
        
        //default selection style
        this.selection_style = this.setStyleDefaultValues(null, true, true);
        
        this.options.useWidgetZoomSettingsOnly = __parseval(params['ignore_mapdoc_zoom']);
        
        //these settings may be overwritten by map document, by basemap or by tiled layer
        if(params['maxzoom']>0){
            this.options.zoomMaxInKM = params['maxzoom'];
        }
        if(params['minzoom']>0){
            this.options.zoomMinInKM = params['minzoom'];
        }
        
        if(this.options.zoomMaxInKM>0){
            const zoomNative = this.convertZoomToNative(this.options.zoomMaxInKM);
            if(zoomNative>0){
                this.defineMaxZoom('widget', zoomNative); //on widget init
            }
        }
        if(this.options.zoomMinInKM>0){
            const zoomNative = this.convertZoomToNative(this.options.zoomMinInKM);
            if(zoomNative>=0){
                this.defineMinZoom('widget', zoomNative); //on widget init
            }
        }
        
        if(params['pntzoom']>0){
            this.options.zoomToPointInKM = parseFloat(params['pntzoom']); 
            if(!(this.options.zoomToPointInKM>0)){
                this.options.zoomToPointInKM  = 5; //default value
            }
        }

        //special case - till thematic map is not developed - for custom style
        /* expremental 
        this.isHamburgIslamicImpire = (params['search_realm']=='hie_places');
        if(this.isHamburgIslamicImpire){
            this.hie_places_with_events = 
        [122030,121870,121869,121974,121125,132793,121915,121948,121124,121978,122012,121878,121880,121873,121130,122006,121913,121924,121891,122021,121934,131700,132092,121972,121958,132244,121956,121968,121893,121923,121876,122041,121908,121885,132090,121999,122044,121998,121992,121904,122025,121946,121906,131680,121882,121895,131828,132197,121988,121921,121940,121986,122008,121961,132715,122010,122035,132030,121966,121976,121917,121984,121911,132053,132098,121919,121126,121849,122003,121899,121928,132112,121936,121954,121127,132214,121950,121932,132543,131882,121194,132745,122037,122016,121964,121980,121926,121887,121129,122039,122019,122014,121990,121952,132088,121897,132757,121883,121875,121889,131652,133081,131865,121902,121997,131934,121982,121942,121930,131643,132138,132013,121970];
            this.hie_places_wo_events_style =
            {"iconType":"iconfont","iconFont":"location","iconSize":"12","stroke":"1","color":"#4f6128","weight":"0","fillColor":"#4f6128","fillOpacity":"0.1"};
        }
        */
            

        
        //show/hide map or timeline
        this.nomap = __parseval(params['nomap']);
        this.notimeline = __parseval(params['notimeline']);
        
        let layout_opts = {};
        if(this.notimeline){
            layout_opts.south__size = 200;
            layout_opts.south__spacing_open = 0;
            layout_opts.south__spacing_closed = 0;
        }else{
            
            if(!this.vistimeline){
                this.vistimeline = $(this.element).find('.ui-layout-south').timeline({
                    element_timeline: this.options.element_timeline,
                    onselect: function(selected_rec_ids){
                        that.setFeatureSelection(selected_rec_ids, true, true, false); //timeline select - highlight on map and zoom
                    },                
                    onfilter: function(show_rec_ids, hide_rec_ids){
                        
                        that.setFeatureVisibility(show_rec_ids, true, 0);
                        that.setFeatureVisibility(hide_rec_ids, false, 0);
                    }});
            }
            
            
            if(this.options.element_layout){
                let th = Math.floor($(this.options.element_layout).height()*0.2);
                layout_opts.south__size = th>200?200:th;
                
                if(this.nomap){
                    layout_opts.center__minHeight = 0;
                    layout_opts.south__spacing_open = 0;
                    layout_opts.south__spacing_closed = 0;
                }else{
                    layout_opts.south__spacing_open = 7;
                    layout_opts.south__spacing_closed = 12;
                    layout_opts.center__minHeight = 30;
                    layout_opts.center__minWidth = 200;
                }
            }
        }
        
        let is_main_ui = false;
        
        if(this.options.element_layout){
            
            is_main_ui = params['ui_main']; //if true show separate toolbar for map controls
           
            if(is_main_ui){
                layout_opts.north__size = 36;
            }else if(__parseval(params['noheader'])){ //outdated
                layout_opts.north__size = 0;
            }
            
            let mylayout = $(this.options.element_layout).layout(layout_opts);
            if(this.notimeline){
                mylayout.hide('south');
            }
            if(is_main_ui){
                mylayout.show('north');
            }else{
                mylayout.hide('north');
            }
        }
        
    
        //map controls {all,none,zoom,bookmark,geocoder,print,publish,legend}
        let controls = [];
        if(!window.hWin.HEURIST4.util.isempty(params['controls']) && params['controls']!='none'){
            controls = __splitval(params['controls']);
        }
        
        controls.push('zoom'); //zoom is always visible
        if(!this.options.isPublished){
            controls.push('addmapdoc'); //add map doc is always visible for "non published" ui
            controls.push('help');
        }
        
        function __controls(val){
            let is_visible = (controls.indexOf('all')>=0  
                                || controls.indexOf(val)>=0);
            
            if(is_visible){
                
                if(!that['map_'+val])  //not yet created
                {
                    //not yet created
                    if(val=='bookmark'){ //bookmark plugin
                        that.map_bookmark = new L.Control.Bookmarks({ position: 'topleft', 
                            //formPopup:{templateOptions: {submitTextCreate:'add'}},
                            bookmarkTemplate: '<li class="{{ itemClass }}" data-id="{{ data.id }}">' +
                              '<span class="{{ removeClass }}">&times;</span>' +
                              '<span class="{{ nameClass }}">{{ data.name }}</span>' +
                              //'<span class="{{ coordsClass }}">{{ data.coords }}</span>' +
                              '</li>'
                        });
                    }else
                    if(val=='geocoder'){ //geocoder plugin
                        that.map_geocoder = L.Control.geocoder({ position: 'topleft', 
                            geocoder: L.Control.Geocoder.nominatim()
                            //geocoder: L.Control.Geocoder.photon()
                            //geocoder: new L.Control.Geocoder.Google('key') 
                        });
                            
                            /*
                            'Bing': L.Control.Geocoder.bing({
                                        apiKey: ''}),
                            'Mapbox': L.Control.Geocoder.mapbox({ apiKey: LCG.apiToken }),
                            */
                            
                    }else
                    if(val=='print'){
                        //browser.print plugin
                        that.map_print = L.control.browserPrint(
                            { position: 'topleft', 
                              /*documentTitle: 'Heurist map',
                              printModesNames: {Portrait: window.hWin.HR('Portrait'),
                                                Landscape: window.hWin.HR('Landscape'),
                                                Auto: window.hWin.HR('Auto'),
                                                Custom: 'Select Area'},
                              */
                              manualMode: false
                            });
                            
                        $(that.map_print).find('.browser-print-mode').css({padding: '2px 10px !important'}); //.v1 .browser-print-mode
                        
                        that.nativemap.on("browser-print-start", function(e){ //browser-pre-print
                            window.hWin.HEURIST4.msg.showMsgFlash('Rendering....', 5000);
                            if(that.printScaleMode!='none'){
                                    L.control.scale({
                                        position: that.printScaleMode,
                                        //imperial: false,
                                        maxWidth: 200
                                    }).addTo(e.printMap);
                            }
                            
                            if(that.printLegend){
                                let legend_content = that.mapManager.getActiveMapDocumentLegend();
                                if(legend_content){
                                    let mleg = L.control.manager({ position: 'topright' }).addTo( e.printMap );
                                    let cont = $(mleg._container);
                                //cont.css({border: '2px solid rgba(0,0,0,0.2)','background-clip': 'padding-box', width:'200px', height:'400px'}); 
                                    $(legend_content).appendTo(cont);
                                }
                            }
                            
                                });                        
                                        
                        
                        
                    }else
                    if(val=='publish'){ //publish plugin
                        that.map_publish = L.control.publish({ position: 'topleft', mapwidget:that });
                    }else
                    if(val=='addmapdoc'){ //addmapdoc plugin
                        that.map_addmapdoc = L.control.addmapdoc({ position: 'topleft', mapwidget:that });
                    }else
                    if(val=='help' && window.hWin.HEURIST4.util.isFunction(L.control.help)){ //publish plugin
                        that.map_help = L.control.help({ position: 'topleft', mapwidget:that });
                    }else
                    if(val=='draw') //draw plugin
                    {
                        that.drawSetControls( that.options.drawMode );
                        
                    }//draw events
                    
                }
                
                if(that['map_'+val] && !that['map_'+val]._map) that['map_'+val].addTo(that.nativemap);
                
                if(val=='print'){
                    $('ul.browser-print-holder').css('display','none'); //hide default print modes popup
                    $('a.leaflet-browser-print').css('cursor','pointer').on({click:function(){that.onPrintStart(that)}}); //redefine action
                }
                
            }else if(that['map_'+val]){
                that['map_'+val].remove();
            }

        }
        __controls('legend');
        __controls('zoom');
        __controls('bookmark');
        __controls('geocoder');
        __controls('print');
        //__controls('scale');
        if(controls.indexOf('draw')>=0){
             __controls('draw');   
        }
        __controls('publish');
        __controls('addmapdoc');    
        __controls('help');
        
        if(is_main_ui){
            this.initToolbarInMainUI();
        }
        
            
        //   legend: [basemaps,search,mapdocs|onedoc,off,width]
        this.mapManager.updatePanelVisibility(__splitval(params['legend']));
        
        //show/hide available basemaps
        this.mapManager.filterListBaseMap( params['basemaps'] );  

        //show/hide available basemaps
        this.mapManager.filterListMapDocuments( params['mapdocuments'] );  

        
        // basemap: name of initial basemap
        if(params['basemap']){
            
            this._inited_basemap = true;
            this.mapManager.loadBaseMap( params['basemap'] );  
            this.setBaseMapFilter( params['basemap_filter'] );
        }else{
            
            this._inited_basemap = true;
            this.mapManager.loadBaseMap( 0 ); //load default basemap 
        }

        if(params['template']){
            this.mapPopUpTemplate = params['template'];
        }
        
        $('#map').find('#map-loading').empty();
        
        // extent: fixed extent    
    },//updateLayout
    
    //
    // Toolbar is active in main UI only
    // Hides all controls (plugins) on map and assign their actions to buttons on toolbar
    //    
    initToolbarInMainUI: function(){
    
        if(this.ui_main_inited===true) return;
        
        this.ui_main_inited = true;
        
        let that = this;    
        //need to init only once!

        let toolbar = $('#mapToolbarDiv');

        $('.leaflet-control').css({clear:'none','margin-top':'0px'});

        that._on(toolbar, {click:function(e){
            if(!$(e.target).hasClass('ui-icon-bookmark')){
                that.map_bookmark.collapse();    
            }
            if(!$(e.target).hasClass('ui-icon-print')){
                $('.browser-print-mode').hide();
            }
        }});

        toolbar.find('#btn_legend_map').button();
        toolbar.find('.toggle-legend') 
        .on({click:function(){that.mapManager.toggle();}});

        if(that.map_bookmark){

            toolbar.find('.ui-icon-bookmark').attr('title','Manage spatial bookmarks')
            .button()
            .on({click:function(){
                let ele = $('.bookmarks-container');
                if(ele.is(':visible')){
                    that.map_bookmark.collapse();    
                }else{
                    that.map_bookmark.expand();    
                    //ele.css({top: that.map_bookmark.position().y+10});
                    //left: $('a.ui-icon-bookmark').position().x});
                }

                //position({of:$('a.ui-icon-bookmark'),my:'top left', at:'bottom left' });
            }});


            let ele2 = that.map_bookmark.getContainer();
            $(ele2).css({'margin-top':'10px'});

            $(that.map_bookmark.getContainer()).css({border:'none',height:'1px !important', padding: '0px', background: 'none'});
            $('.bookmarks-header').hide();
        }else{
            toolbar.find('.ui-icon-bookmark').hide();
        }

        if(that.map_print){
            // browser.print plugin
            toolbar.find('.ui-icon-print').button()
            .attr('title', window.hWin.HR('Print map'))
            .on({click:function(){that.onPrintStart(that)}});

            $(that.map_print.getContainer()).css({border:'none',height:'0px !important',
                width:'0px !important','margin-left':'200px'});
            $('.leaflet-browser-print').hide();
            
        }else{
            toolbar.find('.ui-icon-print').hide();
        }


        //publish plugin
        if(that.map_publish){
            $(that.map_publish.getContainer()).hide();
            that._on(
                toolbar.find('.ui-icon-globe').button()
                .attr('title', window.hWin.HR('Publish map')),
                {click:function(){  
                    window.hWin.HEURIST4.ui.showPublishDialog( {mode:'mapquery', mapwidget:this} );
            }});
        }else{
            toolbar.find('.ui-icon-globe').hide();
        }

        if(that.map_help){
            $(that.map_help.getContainer()).hide();
            if(window.parent){
            window.hWin.HEURIST4.ui.initHelper({ button:toolbar.find('.ui-icon-help').button(),
                url: window.hWin.HRes('mapping_overview.html #content'),
                position: { my: 'center center', at: 'center center', 
                    of: $(window.parent.document).find('#map-frame').parent() } 
                , no_init:true} ); //this.element
            }
        }else{
            toolbar.find('.ui-icon-help').hide();
        }

        
        toolbar.find('.ui-icon-plus').button()
        .attr('title', window.hWin.HR('Zoom in'))
        .on({click:function(){  that.nativemap.zoomIn(); }});

        toolbar.find('.ui-icon-minus').button()
        .attr('title', window.hWin.HR('Zoom out'))
        .on({click:function(){  that.nativemap.zoomOut(); }});

        $(that.map_zoom.getContainer()).hide();
        

        //addmapdoc plugin
        $(that.map_addmapdoc.getContainer()).hide();

        // btn_add_mapdoc are hidden since 2023-03-16
        toolbar.find('#btn_add_mapdoc').hide()
        .attr('title', window.hWin.HR('Create new map document'))
        .html('<span class="ui-icon ui-map-document" style="width:22px;margin:0px;height:22px">'
            +'<span class="ui-icon ui-icon-plus" style="position:absolute;right:0px;font-size:12px;color:white;text-shadow: 2px 2px gray;bottom:0px" />'
            +'</span>')                        
        .button()
        .on({click:function(){
            that.mapManager.createNewMapDocument();
        }});

        if(that.map_geocoder){
            $(that.map_geocoder.getContainer()).hide();

            toolbar.find('.ui-icon-search').button()
            .attr('title', window.hWin.HR('Search for a place'))
            .on({click:function(){  
                $(that.map_geocoder.getContainer()).show();
                that.map_geocoder._expand();
            }});

            L.DomEvent.addListener(that.map_geocoder, 'collapse', 
                function(){
                    $(that.map_geocoder.getContainer()).hide();
                }
            );
        }else{
            toolbar.find('.ui-icon-search').hide();
        }

        toolbar.find('#mapDocumentSel').parent().hide();
        /* Hidden since 2023-03-16
        var $mapdocSel = toolbar.find('#mapDocumentSel');
        this._on(toolbar.find('#btn_layout_map').button({label:'Map'}),
            {click:function(e){  
                this.nomap = !this.nomap;
                if(this.notimeline && this.nomap) this.notimeline = false;
                this._updatePanels()
            }});
        this._on(toolbar.find('#btn_layout_timeline').button({label:'Timeline'}),
            {click:function(e){  
                this.notimeline = !this.notimeline;
                if(this.notimeline && this.nomap) this.nomap = false;
                this._updatePanels()
            }});
        this.mapManager.populateMapDocuments($mapdocSel);
        */
        
        const allow_digit_btn = true;
        if(allow_digit_btn){ //init digitizing tool button
            let btn = toolbar.find('#btn_digitizing')
                        .button()
                        .attr('title','Create map annotation');
            
            this._on(btn, {click:function(e){
                
                let this_btn = $(e.target);
                //add new record 
                if(!(window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_ANNOTATION']>0)){
                    
                    window.hWin.HAPI4.SystemMgr.checkPresenceOfRectype('2-101',2,
                        'You will need record types '
                        +'2-101 "Map/Image Annotation" which are available as part of Heurist_Core_Definitions.',
                            function(){
                               this_btn.trigger('click'); //call itself again 
                            }
                        );

                    return;
                }
                
                        
                if(this.currentDrawMode=='none'){
                    
                    $(e.target).addClass('ui-state-active');

                    this.options.ondraw_save_on_addition = true;
                    
                    this.drawSetControls('full');
                    
                    this.options.ondraw_editsave = function(e){
                        let res = that.drawGetWkt(false);

                        if( res!==false ){    
                        
                            let typeCode = 'm';
                            if(res.indexOf('GEOMETRYCOLLECTION')<0 && res.indexOf('MULTI')<0){
                                if(res.indexOf('LINESTRING')>=0){
                                    typeCode = 'l';
                                }else if(res.indexOf('POLYGON')>=0){
                                    typeCode = 'pl';
                                }else {
                                    typeCode = 'p';
                                }
                            }
                            
                            let new_record_params = {};
                            new_record_params['RecTypeID'] = window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_ANNOTATION'];
                            new_record_params['details'] = {};
                            new_record_params['details'][window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT']] = (typeCode+' '+res);

                            window.hWin.HEURIST4.ui.openRecordEdit(-1, null,{new_record_params:new_record_params});                       
                            that.drawClearAll();
                        
                        }else{
                            window.hWin.HEURIST4.msg.showMsgFlash('You have to draw a shape', 2000);
                        }
                                             
                    };
                    
                    
                }else {
                    
                    if(this.map_draw){
                    
                        function __remove_all_map_edits(){
                            that.drawClearAll();
                            that.map_draw.remove();
                            that.map_draw = null;
                            that.currentDrawMode='none';
                            //this.nativemap.removeControl( this.map_draw ); 
                        }
                        
                        
                        if(this.drawnItems && this.drawnItems.length>0) {
                            window.hWin.HEURIST4.msg.showMsgDlg(
                                'You are about to delete all map edits<br><br>Are you sure?',
                                 function(){
                                     __remove_all_map_edits();
                                 });
                        }else{
                            __remove_all_map_edits();
                        } 
                    
                    }
                    
                    $(e.target).removeClass('ui-state-active');
   
                }

            }});

        }else{
            toolbar.find('#btn_digitizing').hide();
        }
        
        
        
        
    },
    
    //
    //
    //
    onPrintStart: function(that){
                //$('.browser-print-mode').css('display','inline-block'); //show print modes
                //return;
                
                window.hWin.HEURIST4.msg.showMsgDlg(
'<fieldset style="line-height: 22px;">'
+'<div><label class="header_narrow">'+window.hWin.HR('Title')+':</label>'
    +'<input id="dlg-prompt-title" class="text ui-corner-all" style="max-width: 250px; min-width: 10em; width: 250px; margin-left:0.2em"/></div><div><label class="header_narrow">'
+ window.hWin.HR('Scale')+':</label><select id="dlg-prompt-scale"  class="text ui-corner-all">'
    +'<option value="topleft">'+window.hWin.HR('Top left')+'</option>'
    +'<option value="topright">'+window.hWin.HR('Top right')+'</option>'
    +'<option value="bottomleft">'+window.hWin.HR('Bottom left')+'</option>'
    +'<option value="none">'+window.hWin.HR('None')+'</option>'
    +'</select></div><div><label class="header_narrow">'
+window.hWin.HR('Legend')+':</label><input type="checkbox" id="dlg-prompt-legend"  class="text ui-corner-all"/> visible for active Map Document'    
    +'</div><div><label class="header_narrow">'
+window.hWin.HR('Mode')+':</label><select id="dlg-prompt-mode"  class="text ui-corner-all">'
    +'<option value="Custom">'+window.hWin.HR('Select Area')+'</option>'
    +'<option value="Auto">'+window.hWin.HR('Auto')+'</option>'
    +'<option value="Portrait">'+window.hWin.HR('Portrait')+'</option>'
    +'<option value="Landscape">'+window.hWin.HR('Landscape')+'</option></select></div><div>'
    +'<label class="header_narrow">'+window.hWin.HR('Note:')+'</label><p style="max-width:270px">'
    + window.hWin.HR('map_print_note')
    +'</p></div></fieldset>',
                function(){
                        let $dlg = window.hWin.HEURIST4.msg.getMsgDlg('dialog-common-messages');      
                        let sTitle = $dlg.find('#dlg-prompt-title').val().trim();
                        $('div.grid-map-print-title > h3').text(sTitle);

                        !window.hWin.HEURIST4.util.isempty(sTitle) ? // show print title if there is a title
                            $('div.grid-map-print-title').show() : $('div.grid-map-print-title').hide();

                        that.printScaleMode = $dlg.find('#dlg-prompt-scale').val();
                        
                        that.printLegend = $dlg.find('#dlg-prompt-legend').is(':checked');
                        
                        let sMode = $dlg.find('#dlg-prompt-mode').val();
                        
                        let modeToUse = L.BrowserPrint.Mode[sMode](); 
                       
                        that.map_print.browserPrint.print(modeToUse);
                },
                {title:window.hWin.HR('Print map'), yes:'Print'});
                
    },
    
    //
    // event handler to update status 
    // 1) in linked resultList (see app_timemap)
    // 2) inn map legend
    //
    onLayerStatus: function( layer_ID, status ){
        if(window.hWin.HEURIST4.util.isFunction(this.options.onlayerstatus)){
            this.options.onlayerstatus.call(this, layer_ID, status);
        }
        
        if(this.mapManager){
            this.mapManager.updateLayerStatus(layer_ID, status);    
        }
        
        //this._updatePanels();
    },    
    
    
    /**
    * show/hide panels map and timeline
    */
    _updatePanels: function(){
        
        let no_map_data = !this.isSomethingOnMap(), 
            no_time_data = (this.timeline_groups.length==0);
        
        let toolbar = $('#mapToolbarDiv');
        if(this.nomap){
            toolbar.find('#btn_layout_map').removeClass('ui-state-active');
        }else{
            toolbar.find('#btn_layout_map').addClass('ui-state-active').trigger('blur');
        }
        if(this.notimeline){
            toolbar.find('#btn_layout_timeline').removeClass('ui-state-active');
        }else{
            toolbar.find('#btn_layout_timeline').addClass('ui-state-active').trigger('blur');
        }

        let is_main_ui = this.options.layout_params && this.options.layout_params['ui_main'];

        let new_1 = this.notimeline || (!is_main_ui && no_time_data);
        let new_2 = this.nomap || (!is_main_ui && no_map_data);
        
        if(this.is_timeline_disabled!==new_1 || this.is_map_disabled!==new_2)        
        {
            //status has been changed - action
            if(this.options.element_layout){
                if(!this.is_timeline_disabled && !this.is_map_disabled){
                    this.timeline_height = $(this.options.element_layout).find('.ui-layout-south').height() + 7;
                    //this.timeline_height = window.hWin.HAPI4.LayoutMgr.cardinalPanel('getSize', ['south','layoutHeight']
                    //    , $(this.options.element_layout) );
                }
            }

            this.is_timeline_disabled = new_1;
            this.is_map_disabled = new_2;
            
            if(this.options.element_layout){
            
                let layout_opts = {};
                let tha, th;
                if(this.is_timeline_disabled){
                    
                    //keep current timeline 
                    //hide resize control
                    layout_opts.south__size = 0;
                    layout_opts.south__spacing_open = 0;
                    layout_opts.south__spacing_closed = 0;
                }else {
                    //default height of timeline is 20%
                    tha = $(this.options.element_layout).height();
                    th = Math.floor(tha*0.2);
                    
                    if(this.is_map_disabled){
                        layout_opts.south__size = tha-30;
                        
                        layout_opts.center__minHeight = 0;
                        layout_opts.south__spacing_open = 0;
                        layout_opts.south__spacing_closed = 0;
                    }else{
                        layout_opts.south__size = this.timeline_height>30?this.timeline_height:(th>200?200:th);
                        
                        //show resize control when both map and timeline are visible
                        layout_opts.south__spacing_open = 7;
                        layout_opts.south__spacing_closed = 12;
                        layout_opts.center__minHeight = 30;
                        layout_opts.center__minWidth = 200;
                    }                    
                }
                let mylayout = $(this.options.element_layout).layout(layout_opts);
                
                if(this.is_timeline_disabled){
                    mylayout.hide('south');
                }else{
                    mylayout.show('south');
                    mylayout.sizePane('south', layout_opts.south__size);    
                }
            }
          
          
            //refresh map
            if(!this.is_map_disabled) this.invalidateSize();
              
        }
        
        if(no_map_data && !this.options.isPublished){
           $('#map_empty_message').show();
        }else{
           $('#map_empty_message').hide();
           $('#map').show();
        }
    },
    
//----------------------- draw routines ----------------------    
    
    //
    //  detects format and calls the appropriate method to load geo data for drawing (WKT, simple points or JSON)
    // 
    drawLoadGeometry: function(data){

        if (!data) {
            return;   
        }
        
        let gjson = window.hWin.HEURIST4.util.isJSON(data);

        if(gjson===false){
            //wkt or simple points
            if(data.indexOf('POINT')>=0 || data.indexOf('LINE')>=0 || data.indexOf('POLY')>=0){
                this.drawLoadWKT(data, false);
            }else {
                this.drawLoadSimplePoints(data); //parses, UTM to LatLng and converts to WKT 
            }         
        }else{
            this.drawLoadJson(gjson, false);
        }
        
        
    },
    
    //
    // converts coordinate pairs (points) to WKT and loads for drawing (adds to drawItems)
    // requires mapDraw.js - called from doigitizer only
    //
    drawLoadSimplePoints: function(sCoords, type, UTMzone){
        
        let that = this;
        
        simplePointsToWKT(sCoords, type, UTMzone, function(wkt){ that.drawLoadWKT(wkt, false); });
    },

    //
    //  Addition of wkt to drawItems
    //  Parses WKT, converts to JSON and calls drawLoadJson to adds to drawItems
    //  force_clear - remove all current shapes on map
    //
    drawLoadWKT: function(wkt, force_clear){
        
        if (! wkt) {
            //wkt = decodeURIComponent(document.location.search);
            return;
        }
        
        //remove heurist prefix with type
        let typeCode;
        let matches = wkt.match(/\??(\S{1,2})\s+(.*)/);
        if (! matches) {
            return;
        }
        if(matches.length>2){
            typeCode = matches[1];
            if( (['m','pl','l','c','r','p']).indexOf(typeCode)>=0 ){
                wkt = matches[2];    
            }
        }else{
            wkt = matches[1];
        }        
        
        let gjson = parseWKT(wkt); //see wellknown.js
        
        if(gjson && (gjson.coordinates || gjson.geometries)){
            this.drawLoadJson(gjson, force_clear);
        }else if(force_clear){
            this.drawClearAll();
        }else{
            window.hWin.HEURIST4.msg.showMsgFlash('The text entered is not valid WKT'); 
        }        
        
    },

    
//{"type":"LineString","coordinates":[[-0.140634,51.501877],[-0.130785,51.525804],[-0.129325,51.505243],[-0.128982,51.5036]]}}    
    
    //
    // json -> map - adds draw items to map
    //
    drawLoadJson: function( gjson, force_clear ){
        
            gjson = window.hWin.HEURIST4.util.isJSON(gjson);

            if(gjson!==false || force_clear){
                this.drawClearAll();    
            } 
            
            if(gjson===false){
                window.hWin.HEURIST4.msg.showMsgFlash('The text entered is not valid GeoJSON');    
                return;
            }

            let that = this;
            
            if(this.is_crs_simple){
                this.projectGeoJson( gjson, false );
            }            

            function __addDrawItems(lg){
                if(lg instanceof L.LayerGroup){
                    lg.eachLayer(function (layer) {
                        __addDrawItems(layer);    
                    });
                }else{
                    
                    function __addDrawItem(item){
                            that.nativemap.addLayer(item);                        
                            that.drawnItems.addLayer(item);
                            item.editing.enable();
                    }
                    
                    if(lg instanceof L.Polygon){
                        
                        let coords = lg.getLatLngs();
                        function __isRect( coords ){
                                if(coords.length==4){
                                     let l1 = Math.round(coords[0].distanceTo(coords[2]));
                                     let l2 = Math.round(coords[1].distanceTo(coords[3]));
                                     return (l1==l2);
                                }
                                return false;
                        }
                        

                        if(coords.length>0 && coords[0] instanceof L.LatLng ){
                            //simple polygon
                            if(__isRect( coords )){
                                __addDrawItem(new L.Rectangle(coords));
                            }else{
                                coords.push(coords[0]); //add last
                                __addDrawItem(new L.Polygon(coords));
                            }
                        }else{
                            //multipolygon
                            if(Array.isArray(coords) && coords.length==1) coords = coords[0];
                            if(coords.length>0 && coords[0] instanceof L.LatLng ){
                                if(__isRect( coords )){
                                    __addDrawItem(new L.Rectangle(coords));
                                }else{
                                    coords.push(coords[0]);
                                    __addDrawItem(new L.Polygon(coords));
                                }
                            }else{
                                for(let i=0;i<coords.length;i++){
                                      coords[i].push(coords[i][0]);
                                      __addDrawItem(new L.Polygon(coords[i]));
                                }
                            }
                        }
                        
                        
                    }else if(lg instanceof L.Polyline){
                        let coords = lg.getLatLngs();
                        if(coords.length>0 && coords[0] instanceof L.LatLng ){
                            __addDrawItem(new L.Polyline(coords));
                        }else{
                            for(let i=0;i<coords.length;i++){
                                  __addDrawItem(new L.Polyline(coords[i]));
                            }
                        }
                        
                    }else{ 

                        that.nativemap.addLayer(lg);
                        that.drawnItems.addLayer(lg);
                        lg.editing.enable();
                    }
                        
                }                
            }
            
            
            let l2 = null
            try{
                l2 = L.geoJSON(gjson);
                __addDrawItems(l2);
                this.drawZoomTo();
            }catch(e){
                /* continue regardless of error */
            }
    },   
    
    //
    // zoom to drawn items
    // callback - single use callback, for using dom-to-image to avoid invalid images
    //    
    drawZoomTo: function(callback = null){

        let bounds = this.drawnItems.getBounds();

        if(window.hWin.HEURIST4.util.isFunction(callback)){//window.hWin.HEURIST4.util.isFunction(callback)
            this.nativemap.once('zoomend moveend', callback); // call once
        }

        this.zoomToBounds(bounds);
    },

    //
    // remove all drawn items fromm map
    // 
    drawClearAll: function(){
    
        if(this.drawnItems) {
            this.drawnItems.eachLayer(function (layer) {
                layer.remove();
            });
            this.drawnItems.clearLayers();
        }    
    },

    //
    // Gets drawn items as json and converts to WKT string
    //
    drawGetWkt: function( show_warning ){
        
        let res = '', msg = null;
                    
        let gjson = this.drawGetJson(); //mapping.mapping( 'drawGetJson');
        
        gjson = window.hWin.HEURIST4.util.isJSON(gjson);
                
        if(gjson===false || !window.hWin.HEURIST4.util.isGeoJSON(gjson)){
            msg = 'You have to draw a shape';
        }else{
            res = stringifyMultiWKT(gjson);
            
            if(window.hWin.HEURIST4.util.isempty(res)){
                msg = 'Cannot convert GeoJSON to WKT. '
                +'Please click "Get Geometry", copy and send GeoJSON to development team';
            }
        }
        
        if(msg!=null){
            if(show_warning===true){
                    window.hWin.HEURIST4.msg.showMsgDlg(msg);    
            }else if(show_warning===false){
                    res = false;
            }else {
                    res = msg;
            }
        }
        
        return res;
    },
       
    //
    // returns current drawn items as geojson
    //
    drawGetJson: function( e ){
    
        let res_gjson = []; //reset
        let that = this;
        
        function __to_gjson( layer ){

            let lr;
            
            if(layer instanceof L.Circle){
               //L.Circle.toPolygon(layer, 40, this.nativemap)
               let points = layer.toPolygon(40, this.nativemap);
               lr = L.polygon(points);
               
            /*}else if(layer instanceof L.Rectangle){
               
               let points = layer.toPolygon(40, this.nativemap);
               lr = L.polygon(points);
            */    
            }else{ 
                lr = layer;
            }
            
            let gjson = lr.toGeoJSON(8);
            
            if(that.is_crs_simple){
                that.projectGeoJson( gjson, true );
            }
            
            if(window.hWin.HEURIST4.util.isJSON(gjson)){
                res_gjson.push(gjson);
            }        
        }
        
        if(e){
            let layers = e.layers;
            if(layers){
                layers.eachLayer(__to_gjson);
            }else if(e.layer) {
               __to_gjson(e.layer);
            }
        }else if(this.drawnItems) {
            this.drawnItems.eachLayer(__to_gjson);
        } 
        
        if(res_gjson.length==1){
            res_gjson = res_gjson[0];
        }else if (res_gjson.length>1) {
            res_gjson = {"type": "FeatureCollection", "features": res_gjson};
        }
        
        return res_gjson;
    },
    
    drawGetBounds: function(){
        if(this.drawnItems) {
            return this.drawnItems.getBounds(); 
        }else{
            return null;
        }
    },
    
    //
    // set draw style
    //
    drawSetStyle: function(){

         let that = this;
        
         let current_value = this.map_draw_style;
         window.hWin.HEURIST4.ui.showEditSymbologyDialog(current_value, 2, function(new_style){
            
            that.map_draw_style = new_style; 
             
            that.drawSetStyle2( new_style );
            
         });

    },
                       
    drawSetStyleTransparent: function(){
        
        let current_value = this.map_draw_style;
        
        current_value.fillOpacity = 0;
        
        this.drawSetStyle2( current_value );
        
    },
    
    //
    // apply new style for all drawnItems 
    //
    drawSetStyle2: function(new_style){
        
        let that = this;
        
            that.map_draw.setDrawingOptions({
                polygon: {shapeOptions: new_style},
                rectangle: {shapeOptions:new_style},
                circle: {shapeOptions:new_style}
            });                
            
            let new_style2 = window.hWin.HEURIST4.util.cloneJSON(new_style);
            new_style2.fill = false;
            new_style2.fillColor = null;

            that.map_draw.setDrawingOptions({
                polyline: {shapeOptions:new_style2},
            });

            that.drawnItems.eachLayer(function (layer) {
                if(layer instanceof L.Polygon ){
                      layer.setStyle(new_style);
                }else if(layer instanceof L.Polyline ){
                      layer.setStyle(new_style2);
                }
            });
    },
    
    //
    // mode full - all draw controls
    //      filter - rectangle and polygon only
    //      image  - rectangle only
    //      none
    //
    drawSetControls: function( mode ){
        
        let that = this;
        
        that.drawClearAll();
        
        that.drawnItems = L.featureGroup().addTo(that.nativemap);
        
        
        if(this.currentDrawMode == mode) return;

        if(this.currentDrawMode=='image'){
            that.nativemap.off('draw:editmove draw:editmove');
        }
        
        this.currentDrawMode = mode;
        
        if(this.map_draw){
            //remove previous
            this.map_draw.remove();
            this.map_draw = null;
            //this.nativemap.removeControl( this.map_draw ); 
        }
        
        if( mode == 'none' || mode == null ) return;

        //
        // create new control
        // 
        this.map_draw = new L.Control.Draw({
            position: 'topleft',
            edit: {
                featureGroup: that.drawnItems,
                poly: {
                    allowIntersection: false
                },
                edit: !this.options.ondraw_save_on_addition,
                remove: !this.options.ondraw_save_on_addition
            },
            draw: {
                polygon: (mode=='image' || mode=='rectangle')?false:{
                    allowIntersection: false,
                    showArea: true,
                    shapeOptions: {
                        //color: '#bada55'
                    },
                    drawError: {
                        color: '#e1e100', // Color the shape will turn when intersects
                        message: '<strong>Oh snap!<strong> you can\'t draw that!' // Message that will show when intersect
                    },
                },
                rectangle: {
                    shapeOptions: {
                        clickable: true
                    }
                },                                
                polyline: (mode=='full')?
                        {
                            shapeOptions: {
                                //color: '#f357a1',
                                weight: 4
                            }                                    
                        }:false,
                circle: (mode=='full'),
                circlemarker: false,
                marker: (mode=='full')
            }
        }); 
        
        this.map_draw.addTo( this.nativemap );
        
        if(this.currentDrawMode=='image'){
            
            that.nativemap.on('draw:editmove draw:editresize', function (e) {
                   if(window.hWin.HEURIST4.util.isFunction(that.options.ondrawend)){
                       that.options.ondrawend.call(that, e);
                   }
            });     
            
            this.drawSetStyleTransparent();
        }
        
        if(!this.options.ondraw_save_on_addition && 
            L.EditToolbar.Delete){
           
            that.nativemap.tb_del = new L.EditToolbar.Delete(that.nativemap, {featureGroup: that.drawnItems});
            that.nativemap.tb_del.enable();
            
            let ele = $('.leaflet-draw-edit-remove').attr('title','Save edits or clear all')
                .css('background-image','none');
            ele.find('span.sr-only').html('Finalize');
            $('<span class="ui-icon ui-icon-circle-b-check"/>')
                .css('padding-top','21px')
                .addClass('ui-icon ui-icon-circle-b-check')
                .appendTo(ele);
        }

        /*                        
        L.Map.addInitHook('addHandler', 'tb_del', L.EditToolbar.Delete, {featureGroup: that.drawnItems});
        that.nativemap.tb_del.enable();
        */
        
        function __set_btn_title(){
            $('.leaflet-draw-edit-remove').attr('title','Save edits or clear all');
        }
        
        //adds  new shape to drawnItems
        that.nativemap.on(L.Draw.Event.CREATED, function (e) {
            let layer = e.layer;
            that.drawnItems.addLayer(layer);
            layer.editing.enable();
            if(window.hWin.HEURIST4.util.isFunction(that.options.ondrawend)){
                that.options.ondrawend.call(that, e);
            }
            __set_btn_title();
            
            if(that.options.ondraw_save_on_addition && window.hWin.HEURIST4.util.isFunction(that.options.ondraw_editsave)){
                that.options.ondraw_editsave.call(that, e);
            }
            
        });        
        that.nativemap.on('draw:drawstart', function (e) {
               if(window.hWin.HEURIST4.util.isFunction(that.options.ondraw_addstart)){
                   that.options.ondraw_addstart.call(that, e);
               }
               __set_btn_title();
        });
        that.nativemap.on('draw:editstart', function (e) {
               if(window.hWin.HEURIST4.util.isFunction(that.options.ondraw_editstart)){
                   that.options.ondraw_editstart.call(that, e);
               }
               __set_btn_title();
        });
        that.nativemap.on('draw:edited', function (e) {
               if(window.hWin.HEURIST4.util.isFunction(that.options.ondrawend)){
                   that.options.ondrawend.call(that, e);
               }
               __set_btn_title();
        });     
        //on save event       
        that.nativemap.on(L.Draw.Event.DELETED, function (e) {
           if(window.hWin.HEURIST4.util.isFunction(that.options.ondraw_editsave)){
               that.options.ondraw_editsave.call(that, e);
           }
        });
        
        
        that.drawSetStyle2(that.map_draw_style);
        
    },
    
    //
    //
    //
    injectLinks: function(links){
        
        if(!Array.isArray(links)){
            links = links.split("\n");
        }
        for(let i=0; i<links.length; i++){
            if(links[i].indexOf('.js')>0){
                let scr = document.createElement('script');
                scr.type = 'text/javascript';
                scr.src = (links[i].indexOf(window.hWin.HAPI4.baseURL)==0?'':window.hWin.HAPI4.baseURL)+links[i];
                document.getElementsByTagName('head')[0].appendChild(scr);
            }else{
                let link = document.createElement('link');
                link.rel = 'stylesheet';
                link.type = 'text/css';
                link.href = (links[i].indexOf(window.hWin.HAPI4.baseURL)==0?'':window.hWin.HAPI4.baseURL)+links[i];
                document.getElementsByTagName('head')[0].appendChild(link);
            }
        }
        
    },

    //
    // Set nativemap's zoom
    //
    setMapZoom: function(zoom_value, is_KM){

        let bounds = this.drawnItems.getBounds();
        let zoom = null;

        if(is_KM){ // convert KM to map native value
            zoom = this.convertZoomToNative(zoom_value, bounds);
        }else{
            zoom = zoom_value;
        }

        if(zoom && zoom <= this.nativemap.getMaxZoom() && zoom >= this.nativemap.getMinZoom()){
            this.nativemap.setZoom(zoom);
        }
    }
    
});



