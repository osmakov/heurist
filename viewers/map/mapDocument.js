/**
* mapDocument.js - working with map document and dependent record types
* 
* loads list of map documents and theirs content (layers and datasources), 
* opens map document - creates mapLayers
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

/* global HMapLayer2 */

/**
* Manages list of map documents
* 
* 
* @param _options

  private 
    _loadMapDocuments - loads all or filtered map document records
    _getTreeData - converts mapdocument recordset (list of layers) to treeview data
    _loadMapDocumentContent - loads all linked layer and datasources for mapdocument - store it in map_documents_content 
    _openMapDocument - call _loadMapDocumentContent add layer to map
    _addLayerRecord - add new layer to map_documents_content and on map
    _getSymbology - returns symbology for layer (or if not defined it returns general mapdocument symbology)
    _editSymbology - opens symbology editor for layer and then call layer.applyStyle
    
* 
* @returns {Object}
*/
function hMapDocument( _options )
{    
    const _className = "MapDocument",
    _version   = "0.4";

    const RT_MAP_DOCUMENT = window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT'],
    RT_TLCMAP_DATASET = window.hWin.HAPI4.sysinfo['dbconst']['RT_TLCMAP_DATASET'],
    RT_MAP_LAYER = window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_LAYER'],
    DT_MAP_LAYER = window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_LAYER'],
    DT_DATA_SOURCE = window.hWin.HAPI4.sysinfo['dbconst']['DT_DATA_SOURCE'],
    DT_QUERY_STRING = window.hWin.HAPI4.sysinfo['dbconst']['DT_QUERY_STRING'],
    RT_QUERY_SOURCE = window.hWin.HAPI4.sysinfo['dbconst']['RT_QUERY_SOURCE'],
    RT_IMAGE_SOURCE = window.hWin.HAPI4.sysinfo['dbconst']['RT_IMAGE_SOURCE'],
    RT_TILED_IMAGE_SOURCE = window.hWin.HAPI4.sysinfo['dbconst']['RT_TILED_IMAGE_SOURCE'],
    DT_SYMBOLOGY = window.hWin.HAPI4.sysinfo['dbconst']['DT_SYMBOLOGY'],
    DT_NAME      = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
    DT_GEO_OBJECT = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'],
    DT_MAP_BOOKMARK = window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_BOOKMARK'],
    DT_MINIMUM_ZOOM = window.hWin.HAPI4.sysinfo['dbconst']['DT_MINIMUM_ZOOM'],
    DT_MAXIMUM_ZOOM = window.hWin.HAPI4.sysinfo['dbconst']['DT_MAXIMUM_ZOOM'],
    DT_ZOOM_KM_POINT = window.hWin.HAPI4.sysinfo['dbconst']['DT_ZOOM_KM_POINT'],
    DT_WORLD_BASEMAP = window.hWin.HAPI4.sysinfo['dbconst']['DT_WORLD_BASEMAP'],
    DT_CRS = window.hWin.HAPI4.sysinfo['dbconst']['DT_CRS'],
    DT_LEGEND_OUT_ZOOM = window.hWin.HAPI4.sysinfo['dbconst']['DT_LEGEND_OUT_ZOOM'],
    TRM_LEGEND_OUT_ZOOM_HIDDEN = window.hWin.HAPI4.sysinfo['dbconst']['TRM_LEGEND_OUT_ZOOM_HIDDEN'],
    TRM_LEGEND_OUT_ZOOM_DISABLED = window.hWin.HAPI4.sysinfo['dbconst']['TRM_LEGEND_OUT_ZOOM_DISABLED'];    
        


    // default options
    let options = {
        container:null,  //@todo all ui via mapcontrol
        mapwidget:null,  //reference back to mapping.js
    },
    


    
    map_documents = null, //recordset - all loaded documents
    map_documents_content = {}, //array mapdoc_id=>recordset with all layers and datasources of document
    //mapdoc_id - 0 current search, temp temporal mapspace, or record id
    
    _uniqueid = 9000000;   
    
    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    function _init(_options) {
        
        options = $.extend(options, _options);
        
        options.container = $(options.container);
        

        
        //_loadMapDocuments();
    }
    
    //
    // loads all map documents from server
    //
    function _loadMapDocuments( qFilter, onRefreshList ){
        
            if(!(RT_MAP_DOCUMENT>0)) return;
            
            let that = this;
            
            let details = [DT_GEO_OBJECT,DT_MAP_BOOKMARK,DT_SYMBOLOGY,DT_MINIMUM_ZOOM,DT_MAXIMUM_ZOOM,DT_ZOOM_KM_POINT];
            if(DT_WORLD_BASEMAP>0){
                details.push( DT_WORLD_BASEMAP );
            }
            if(DT_CRS>0){
                details.push( DT_CRS );
            }
            
            if(!window.hWin.HEURIST4.util.isempty(qFilter)){
                let aIds = [];
                $.each(qFilter.split(';'),function(i,item){
                    if(Number.isInteger(item) && item>0){
                        aIds.push(item);
                    }
                });
                if(aIds.length>0){
                    qFilter = 'ids:'+aIds.join(',');
                }else{
                    qFilter = null;
                }
            }
            if(window.hWin.HEURIST4.util.isempty(qFilter)){
                qFilter = 't:'+RT_MAP_DOCUMENT;
            }
            
            let request = {
                        q:qFilter, w: 'a',
                        detail: details, //fields_to_be_downloaded
                        source: 'map_document'};
            //perform search        
            window.hWin.HAPI4.RecordMgr.search(request,
                function(response){
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        let resdata = new HRecordSet(response.data);
                        map_documents = resdata;
                        
                        if(window.hWin.HEURIST4.util.isFunction(onRefreshList)) onRefreshList.call(that, resdata);
                    }else {
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }

                }
            );           
        
    }
    
    //
    // returns content of mapdocument (map_documents_content) in fancytree data format
    // converts recordset to treeview data
    //
    function _getTreeData( mapdoc_id ){
        
        let treedata = [];
        
        let limit_layers_for_current_result_set = 0; //number of map layers visible in current result set as separate items (was 10)
        let limit_layers_for_temp_mapdoc = 11; //search result set from clearinghouse
        
        if(_isDocumentLoaded(mapdoc_id)){
            let resdata = map_documents_content[mapdoc_id];
            let idx, records = resdata.getRecords();
            let order2 = resdata.getOrder();
            for(idx in order2){
                if(idx)
                {
                    let recid = order2[idx];
                    let record = records[recid];
                    
                    if(resdata.fld(record, 'rec_RecTypeID')==RT_MAP_LAYER
                        || resdata.fld(record, 'rec_RecTypeID')==RT_TLCMAP_DATASET)
                    { //ignore sources
                        const recID  = resdata.fld(record, 'rec_ID');
                        let recName = '';
                        if(record['d'] && record['d'][1]){
                            recName = record['d'][1][0];
                        }else{
                            recName = resdata.fld(record, 'rec_Title');
                        }

                        let $res = {};  
                        $res['key'] = recID;
                        $res['title'] = '<span style="'+(mapdoc_id==0?'font-size:1.1em':'font-style:italic;')+'">' + recName + '</span>';
                        $res['type'] = 'layer';
                        $res['mapdoc_id'] = mapdoc_id; //reference to parent mapdoc
                        
                        let layer_rec = that.getLayer(mapdoc_id, recID);
                        if(layer_rec && layer_rec['layer']){ // && mapdoc_id==0
                            $res['selected'] = (layer_rec['layer']).isVisible();  
                        }else{
                            $res['selected'] = true;
                        } 

                        if(DT_SYMBOLOGY>0){
                            let layer_themes = resdata.fld(record, DT_SYMBOLOGY);
                            layer_themes = window.hWin.HEURIST4.util.isJSON(layer_themes);
                            
                            if(layer_themes){
                                
                                let $themes = [];
                            
                                if($.isPlainObject(layer_themes)){
                                    layer_themes = [layer_themes];
                                }
                                
                                $.each(layer_themes, function(i, theme){
                                    if(theme && theme.fields){ //object with field is theme
                                        
                                        let themeName = theme.title?theme.title:'Thematic map';

                                        let $theme = {};  
                                        $theme['key'] = 'theme'+recID+'_'+i;
                                        $theme['title'] = "<span style='font-style:italic;'>" + themeName + "</span>";
                                        $theme['type'] = 'theme';
                                        $theme['layer_id'] = recID; //reference to parent layer
                                        $theme['mapdoc_id'] = mapdoc_id; //reference to parent mapdoc
                                        $theme['theme'] = theme;     
                                        $theme['selected'] = (theme.active===true);
                                        
                                        
                                        
                                        

                                        $themes.push($theme);
                                        
                                    }
                                });
                                
                                if($themes.length>0){
                                    if(mapdoc_id==0 && $themes.length==1){
                                        //do not add the only theme for current search
                                    }else{
                                        $res['expanded'] = true;
                                        $res['children'] = $themes;
                                    }
                                
                                }
                            }
                        }
                        
                        treedata.push($res);
                    }
                }
            }//for
            
            if(mapdoc_id==0 && treedata.length > limit_layers_for_current_result_set){
                //remove invisible 
                let i = 0;
                while(i<treedata.length && treedata.length > limit_layers_for_current_result_set){
                    if(!treedata[i]['selected'] && treedata[i]['key']<9000000){
                        treedata.splice(i,1);
                    }else{
                        i++;
                    }
                }
            }
            
        }
        return treedata;
    }
    

    //
    // load all linked layers, tlcmapdataset and datasources records for given map document
    // invoked from _openMapDocument and call the same method when data are recieved from server side
    // deferred object is required for treeview, it returns treeview data
    //    
    function _loadMapDocumentContent(mapdoc_id, deferred, order){

//{"any":[{"ids":mapdoc_id},{"all":{"t":RT_MAP_LAYER,"linkedfrom":mapdoc_id}}]},    //mapdoc and layer linked to given mapdoc     
//{"t":RT_MAP_LAYER,"linkedfrom":mapdoc_id},  //layers linked to given mapdoc

            let request = {
                        w: 'a',
                        detail: 'detail',
                        source: 'map_document',
                        rules: [{"query":"linkedfrom:"+RT_MAP_LAYER+"-"+DT_DATA_SOURCE}]
            };

            if(Array.isArray(mapdoc_id)){ //this is load of temp mapspace with given set of layers
                //"t":RT_MAP_LAYER+','+RT_TLCMAP_DATASET,
                request['q'] = {"ids":mapdoc_id.join(',')};
                mapdoc_id = 'temp';
            }else{
                if(RT_TLCMAP_DATASET>0){
                    request['q'] = {"t":RT_MAP_LAYER+','+RT_TLCMAP_DATASET,"linkedfrom":mapdoc_id};
                }else{
                    request['q'] = {"t":RT_MAP_LAYER,"linkedfrom":mapdoc_id};  //layers linked to given mapdoc
                    
                    if(order){
                        
                        if(Array.isArray(order)){
                            //simplify request if order is set
                            request['q'] = {"ids":order.join(','),"sortby":'set:'+order.join(',')};
                            //request['sortby'] = 'set:'+order.join(',');
                        }
                    
                    }else if(DT_MAP_LAYER>0){
                        let request2 = {
                                    w: 'a',
                                    detail: DT_MAP_LAYER,
                                    q:{'ids':mapdoc_id}
                        };
                        window.hWin.HAPI4.RecordMgr.search(request2,
                            function(response){
              
                                let order = 1;                  
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    let resdata = new HRecordSet(response.data);
                                    let rec = resdata.getFirstRecord();
                                    order = resdata.values(rec, DT_MAP_LAYER);
                                    if(!order) order = 1;
                                              
                                }
                                _loadMapDocumentContent(mapdoc_id, deferred, order);
                            });
                        return;
                    }
                }
            }
            if(RT_TLCMAP_DATASET>0){
                //additional rule for tlc datasets
                request['rules'].push({"query":"linkedfrom:"+RT_TLCMAP_DATASET+"-"+DT_DATA_SOURCE});
            }

            //perform search        
            window.hWin.HAPI4.RecordMgr.search(request,
                function(response){
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        let resdata = new HRecordSet(response.data);
                        map_documents_content[mapdoc_id] = resdata;

                        _openMapDocument( mapdoc_id, deferred);
                        
                    }else {
                        map_documents_content[mapdoc_id] = 'error';
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                        if(deferred) deferred.reject(); //deferred.resolve( [] );
                    }

                }
            );           
        
    }

    //
    // opens map document 
    // 1. loads content (resolve deferred - to updated treeview) 
    // 2. Set CRS (if defined) otherwise use default Leaflet CRS
    // 3. Set world base map (if defined)
    // 4. Adds layers on map
    //
    function _openMapDocument(mapdoc_id, deferred){

        //1. Gets list of layers and datasets
        if(Array.isArray(mapdoc_id) || !map_documents_content[mapdoc_id]){ //if array this is set of layers for temp mapspace
            //map doc is not loaded yet
            if(!Array.isArray(mapdoc_id)) map_documents_content[mapdoc_id] = 'loading';
            _loadMapDocumentContent(mapdoc_id, deferred);    
            return;
        }else if(map_documents_content[mapdoc_id]=='error' || map_documents_content[mapdoc_id]=='loading'){
            //prevent loop
            return;
        }
        
        //2.Set CRS
        if (_defineCRS( mapdoc_id )){
            //3. Set world base map (if defined)
            _loadBaseMap( mapdoc_id );
            
            _defineZooms( mapdoc_id );
        }else{
            that.zoomToMapDocument( mapdoc_id );
        }
        
        //4. Adds layers on map
        let resdata = map_documents_content[mapdoc_id];
        
        let idx, records = resdata.getRecords(), order = resdata.getOrder();  //layers
        for(idx=order.length-1; idx>=0; idx--){
            if(idx>=0)
            {
                let recID = order[idx];
                let record = resdata.getById(recID);
                
                if(resdata.fld(record, 'rec_RecTypeID')==RT_MAP_LAYER
                    ||resdata.fld(record, 'rec_RecTypeID')==RT_TLCMAP_DATASET){
                    
                    let datasource_recID = resdata.fld(record, DT_DATA_SOURCE);    

                    let datasource_record = resdata.getById( datasource_recID );
                    
                    //creates and add layer to nativemap
                    //returns mapLayer object
                    record['source_rectype'] = resdata.fld(datasource_record, 'rec_RecTypeID');  //for icon in legend
                    record['layer'] = new HMapLayer2({rec_layer: record,      //on mapdoc open
                                                      rec_datasource: datasource_record, 
                                                      mapdoc_recordset: resdata, //need to get fields
                                                      mapwidget: options.mapwidget});
                }
            }
        }//for
        
        
        if(deferred){
            let treedata = _getTreeData(mapdoc_id);
            
/*
console.log(treedata);
    [{type:layer,title:'aaa',
        children:[{type:theme.theme:{title:'bbb',
                fields:[{ranges:[{title:'ccc',value:'0<>1',symbol:{}}]}]}  },..] }]            
*/            
            deferred.resolve( treedata ); //returns data to fancytree to render child layers for given mapdocument
        }
                                
    }

    //
    //
    //
    function _loadBaseMap( mapdoc_id ){
        let basemap_name = null;
        if(mapdoc_id=='None'){
            basemap_name = 'None';
        }else
        if(mapdoc_id!='temp'){
            let record2 = map_documents.getById( mapdoc_id );
            if(DT_WORLD_BASEMAP>0){
                basemap_name = map_documents.fld(record2, DT_WORLD_BASEMAP);
                if(basemap_name>0){
                    basemap_name = $Db.trm(basemap_name, 'trm_Label');    
                }
            }
        }
        //todo: fix restore basemap to default (0) if current basemap 'None'
        
        if(basemap_name!=null){
            let mapManager = options.mapwidget.mapping('getMapManager');            
            mapManager.loadBaseMap(basemap_name);
        }
    }

    //
    // Defines CRS. It returns true if CRS is not set or not simple
    //
    function _defineCRS( mapdoc_id ){
        let crs = '';
        if(mapdoc_id!='temp'){
            let record2 = map_documents.getById( mapdoc_id );
            if(DT_CRS>0){
                let crs_id = map_documents.fld(record2, DT_CRS);
                if(crs_id){
                    crs = $Db.trm(crs_id, 'trm_Code');
                    if(crs=='XY') crs = 'Simple'
                    else if(!crs) crs = '';
                    
                    if(crs && crs.indexOf(':')>0){
                        crs = crs.replace(':','');
                    }
                }
            }
        }
        
        //EPSG3857  EPSG4326 - Mercator
        let is_standard_projection = (crs=='' || crs=='EPSG3857' || crs=='EPSG4326');
        
        if(!is_standard_projection){
            _loadBaseMap('None');
        }
        
        options.mapwidget.mapping('defineCRS', crs);            
        
        return is_standard_projection;
    }
    
    //
    // Converts min/max zoom in km to nativemap zoom levels and assign to map
    //
    function _defineZooms( mapdoc_id ){
        if(mapdoc_id!='temp'){
            
            if(options.mapwidget.mapping('option','useWidgetZoomSettingsOnly')){
                return;    
            } 
            
            let record2 = map_documents.getById( mapdoc_id );
            if(DT_ZOOM_KM_POINT>0){
                const val = map_documents.fld(record2, DT_ZOOM_KM_POINT);
                if(parseFloat(val)>0){
                    options.mapwidget.mapping('option','zoomToPointInKM', parseFloat(val));    
                }
            }
            
            if(DT_MAXIMUM_ZOOM >0){
                const val = parseFloat(map_documents.fld(record2,DT_MAXIMUM_ZOOM  ));
                if(val>0){ 
                    let zoomNative = 32;
                    if(val>0.0001){ //0.1 meter
                        zoomNative = options.mapwidget.mapping('convertZoomToNative', val);
                    }
                    if(zoomNative>0){
                        options.mapwidget.mapping('defineMaxZoom', 'doc'+mapdoc_id, zoomNative);
                    }
                }
            }
            if(DT_MINIMUM_ZOOM >0){
                const val = parseFloat(map_documents.fld(record2,DT_MINIMUM_ZOOM ));
                if(val>0 && val!=90){ //90 old def value when this field was in degrees
                    const zoomNative = options.mapwidget.mapping('convertZoomToNative', val);
                    if(zoomNative>=0){
                        options.mapwidget.mapping('defineMinZoom', 'doc'+mapdoc_id, zoomNative);
                    }
                }
            }
        }
    }
    
    //
    // adds layer and datasource records to mapdocument recordset, adds map on map, refresh tree
    //
    // mapdoc_id - target map document
    // rec_ids - array of layer record ids, or single layer record id
    //
    function _addLayerRecord(mapdoc_id, rec_ids, callback){
        
            if(!_isDocumentLoaded(mapdoc_id)) return;
            
            if(!rec_ids) return;
            if(!Array.isArray(rec_ids)) rec_ids = [rec_ids];
        
            let request = {
                        q: {"ids":rec_ids.join(',')},  
                        rules:[{"query":"linkedfrom:"+RT_MAP_LAYER+"-"+DT_DATA_SOURCE}], //data sources linked to layers
                        w: 'a',
                        detail: 'detail',
                        source: 'map_document'};
                        
            if(RT_TLCMAP_DATASET>0){
                //additional rule for tlc datasets
                request['rules'].push({"query":"linkedfrom:"+RT_TLCMAP_DATASET+"-"+DT_DATA_SOURCE});
            }
                        
            //perform search        
            window.hWin.HAPI4.RecordMgr.search(request,
                function(response){
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        let resdata = new HRecordSet(response.data);
                        
                        //add to map_document recordset
                        resdata.each(function(recID, record){
                            
                                if(!map_documents_content[mapdoc_id].getById(recID)){
                                    //not exists in this mapdocument
                                
                                    let record2 = {rec_ID:recID,  
                                              rec_Title: resdata.fld(record, 'rec_Title'), 
                                              rec_RecTypeID: resdata.fld(record, 'rec_RecTypeID'),  
                                              d: record['d']};
                                    
                                    
                                    map_documents_content[mapdoc_id].addRecord2(recID, record2);
                                    
                                    if(resdata.fld(record, 'rec_RecTypeID')==RT_MAP_LAYER 
                                        || resdata.fld(record, 'rec_RecTypeID')==RT_TLCMAP_DATASET)
                                    {
                                        let datasource_recID = resdata.fld(record, DT_DATA_SOURCE);    
                                        let datasource_record = resdata.getById( datasource_recID );
                                        
                                        //creates and add layer to nativemap
                                        //returns mapLayer object
                                        record2['source_rectype'] = resdata.fld(datasource_record, 'rec_RecTypeID'); //for icon in legend
                                        record2['layer'] = new HMapLayer2({rec_layer: record,  //on add layer
                                                                          rec_datasource: datasource_record, 
                                                                          mapdoc_recordset: resdata, //need to get fields
                                                                          mapwidget: options.mapwidget,
                                                                          not_init_atonce:true});
                                    }
                                }
                            
                        });
                                
                        
                        if(window.hWin.HEURIST4.util.isFunction(callback)){
                            callback.call(that, _getTreeData(mapdoc_id));
                        }
                        
                    }else {
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                        if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call(that);
                    }

                }
            );           
        
    }
    
    //
    //  returns sybology for given layer of map document, if layer not defined 
    //  if returns default symbology defined in map document
    //
    function _getSymbology( mapdoc_id, rec_id ){
        
        function __extractStyle( def_style ){
            
            let layer_themes = window.hWin.HEURIST4.util.isJSON(def_style);
            let layer_default_style = null;
            
            if(layer_themes!==false){
                if($.isPlainObject(layer_themes)){
                    layer_themes = [layer_themes];
                }

                $.each(layer_themes, function(i, theme){
                    if(!theme.fields){
                        //object without fields is layer's default symbol
                        layer_default_style = theme.symbol?theme.symbol:theme;
                        return false;
                    }
                });
            }
            return layer_default_style;
        }
        
        
        if(!rec_id){
            
            if(map_documents){
                let record2 = (mapdoc_id>0) ? map_documents.getById( mapdoc_id ) 
                                            : map_documents.getFirstRecord();
                return __extractStyle(map_documents.fld(record2, DT_SYMBOLOGY));
            }else{
                return false;
            }
        }
        
        let _recset = map_documents_content[mapdoc_id]; //layers
        let _record = _recset.getById( rec_id );
        let layer_style;
        
        if(!_record['source_rectype'] || _record['source_rectype'] == RT_QUERY_SOURCE){
            //for query of recordset symbology is stored in details
            
            layer_style = __extractStyle(_recset.fld(_record, DT_SYMBOLOGY));

            if(!layer_style){
                if(_record['layer']){
                    layer_style = (_record['layer']).getStyle();    
                }else{
                    layer_style = {};
                }
            } 
        }else{
            layer_style = {rectypeIconUrl:window.hWin.HAPI4.iconBaseURL + _record['source_rectype'] };
        }
        
        return layer_style;
    }

    // get layer record, take symbology field and title 
    // open symbology editor
    // on exit 1) call mapLayer.applyStyles
    //         2) change title in tree and timeline
    function _editSymbology( mapdoc_id, rec_id, callback ){


        let _recset = map_documents_content[mapdoc_id];
        let _record = _recset.getById( rec_id );
        
        let layer_title = _recset.fld(_record, 'rec_Title');
        let layer_style = _getSymbology(  mapdoc_id, rec_id );

        let current_value = layer_style;//affected_layer.options.default_style;
        current_value.sym_Name = layer_title; //affected_layer.options.layer_name;
        //open edit dialog to specify symbology
        window.hWin.HEURIST4.ui.showEditSymbologyDialog(current_value, 1, function(new_value){

            let new_title = null, new_style = null;
            
            //rename in list
            if(!window.hWin.HEURIST4.util.isempty(new_value.sym_Name)
                && current_value.sym_Name!=new_value.sym_Name)
            {
                new_title = new_value.sym_Name;
                _recset.setFld(_record, 'rec_Title', new_value.sym_Name);

                //update label in timeline                                                
                options.mapwidget.mapping('updateTimelineLayerName', (_record['layer']).getNativeId(), new_value.sym_Name);
                
                delete new_value.sym_Name;
            }
            
            //update style
            _recset.setFld(_record, DT_SYMBOLOGY, new_value);
            (_record['layer']).applyStyle(new_value);
            
           //callback to update ui in mapManager
            if(window.hWin.HEURIST4.util.isFunction(callback)){
                callback( new_title, new_style );
            }
        });        
    }

    function _editImageFilter( mapdoc_id, layer_id, callback ){

        const _recset = map_documents_content[mapdoc_id];
        const _record = _recset.getById( layer_id );

        let image_filter = _recset.fld(_record, DT_SYMBOLOGY);

        window.hWin.HEURIST4.ui.showImgFilterDialog(image_filter, (new_value) => {

            _recset.setFld(_record, DT_SYMBOLOGY, new_value);
            (_record['layer']).applyStyle(new_value);

            if(window.hWin.HEURIST4.util.isFunction(callback)){
                callback(new_value);
            }
        });
    }

    /**
     * Apply and save styling (symbology or image filtering) to map layer
     *  and save to record set
     * 
     * @param {int} mapdoc_id - affected map document
     * @param {int} layer_id - layer to be updated
     * @param {string|json} new_style - new style to apply and save 
     * 
     * @returns {bool} - whether the process was successful
     */
    function _updateLayerSymbology(mapdoc_id, layer_id, new_style){

        const _recset = map_documents_content[mapdoc_id];
        const _record = _recset.getById( layer_id );

        if(!_record){
            return false;
        }

        new_style = window.hWin.HEURIST4.util.isJSON(new_style);

        _recset.setFld(_record, DT_SYMBOLOGY, new_style);

        _record['layer'].applyStyle(new_style);

        return true;
    }
    
    //
    //
    //
    function _isDocumentLoaded(mapdoc_id){
            let recset = map_documents_content[mapdoc_id];
            return window.hWin.HEURIST4.util.isRecordSet(recset);
    }
        
    
    //public members
    let that = {
        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        //
        // Loads list of map documents
        //
        loadMapDocuments: function( qFilter, onRefreshList ){
            _loadMapDocuments( qFilter, onRefreshList );    
        },
        
        //
        // Load content for given document - called once in mapManager tree lazy load 
        //
        openMapDocument: function(mapdoc_id, deferred){
            _openMapDocument(mapdoc_id, deferred);
        },
        
        isLoaded: function(mapdoc_id){
           return _isDocumentLoaded(mapdoc_id);
        },
        
        //
        // create new virtual map document for given layer_ids
        //
        createVirtualMapDocument: function(layer_ids, deferred){
            that.closeMapDocument('temp'); //clear old one
            if(!Array.isArray(layer_ids)){
                if(window.hWin.HEURIST4.util.isNumber(layer_ids)){
                    layer_ids = [parseInt(layer_ids)];
                }else{
                    layer_ids = layer_ids.split(',');
                }
            }
            if(window.hWin.HEURIST4.util.isArrayNotEmpty(layer_ids)){
                _openMapDocument(layer_ids, deferred);    
                return;
            }else{
                return;
            }
            
        },
        
        //
        // adds search results or query as a new layer to mapdoc (usuallly "Search Results")
        // data - recordset, heurist query or json
        //
        // mapdoc_id - target map document
        // data - record set or heurist query
        //
        // returns record to use in mapping widget
        //
        addSearchResult: function(mapdoc_id, data, dataset_options){

            let curr_request, original_heurist_query;
            
            const dataset_name = dataset_options.name;
            
            if( window.hWin.HEURIST4.util.isRecordSet(data) ){
                    
                    let recset = data;
                    
                    original_heurist_query = recset.getRequest(); //keep for possible 'save as layer'
                    
                    if(recset.length()<2001){ //limit query by id otherwise use current query
                        let MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');
                        curr_request = { w:'all', q:'ids:'+recset.getIds(MAXITEMS).join(',') };
                    }else{
                        curr_request = original_heurist_query;
                    }            
                
            }else if( window.hWin.HEURIST4.util.isObject(data)&& data['q']) {
                
                 original_heurist_query = data;
                 curr_request = data;
            }
                
            if(!_isDocumentLoaded(mapdoc_id)){
                map_documents_content[mapdoc_id] = new HRecordSet(); //create new recordset - list of layers for mapdocument
            }
            
            //recset - list of layers in mapdoc
            let recset = map_documents_content[mapdoc_id];
            
            //dataset_name is unique within mapdoc
            let search_res = recset.getSubSetByRequest({'rec_Title':('='+dataset_name)}); 
            //let _record = recset.getById(dataset_name);
            let _record;
            if(search_res && search_res.length()>0){  //layer with such name already exists - replace
                
                let recID = search_res.getOrder();
                recID = recID[0];
                _record = recset.getById(recID);
                recset.setFld(_record, DT_QUERY_STRING, curr_request);
                //remove previous result set from map
                if(_record['layer']){ //ref to HMapLayer2
                    _record['layer'].removeLayer();    
                    delete _record['layer']; //clear
                }
            }else{
                //add new layer to mapdocument
                _record = {rec_ID:_uniqueid,  rec_Title:dataset_name, rec_RecTypeID:RT_MAP_LAYER,  d:{}};
                recset.setFld(_record, DT_QUERY_STRING, curr_request);
                _record = recset.addRecord(_uniqueid, _record);
                _uniqueid++;
            }
            _record['original_heurist_query'] = original_heurist_query;
            
            _record['source_rectype'] = RT_QUERY_SOURCE;
            
            //always preserve for mapdoc layers 
            const preserveViewport = (mapdoc_id!=0) || (dataset_options.viewport===true);
            
            recset.setFld(_record, 'layer',
                        new HMapLayer2({rec_datasource: _record,   //adds search results or query as a new layer
                                        mapdoc_recordset: recset, //need to get fields
                                        mapwidget: options.mapwidget,  //need to call back addGeoJson when data ara obtained from server
                                        mapdocument_id: mapdoc_id,
                                        is_current_search: dataset_options.is_current_search,
                                        preserveViewport:preserveViewport })); //zoom to current search 
                                              
                                              
            return _record;
        },
        
        //
        // returns reference to HMapLayer by mapdoc id and name
        //
        getLayerByName: function( mapdoc_id, dataset_name, dataset_id ){
            
            //dataset_name is unique within mapdoc
            if (_isDocumentLoaded(mapdoc_id)){

                let recset = map_documents_content[mapdoc_id];
                
                let recID;
                if(dataset_id>0){
                    recID = dataset_id;
                }else{
                    let search_res = recset.getSubSetByRequest({'rec_Title':('='+dataset_name)}); 
                    if(search_res && search_res.length()>0){
                        recID = search_res.getOrder();
                        recID = recID[0];
                    }    
                }
                let _record = recset.getById(recID);
                return _record?_record['layer']:null;
                
            }
            
            return null;
        },
        
        //
        // adds arbitrary recordset
        // it was used in Digital Harlem and Expert Nation when recordset is generated and prepared in custom way on client side
        // it converts given recordset to geojson and pass it to to mapping.addGeoJson
        //
        // mapdoc_id - target map document
        // recordset - record set
        //
        // returns record to use in mapping widget
        //
        addRecordSet: function(mapdoc_id, recordset, dataset_name){

            if(!_isDocumentLoaded(mapdoc_id)){
                map_documents_content[mapdoc_id] = new HRecordSet(); //create new recordset
            }
            
            let recset = map_documents_content[mapdoc_id];
            
            //dataset_name is unique within mapdoc
            let search_res = recset.getSubSetByRequest({'rec_Title':('='+dataset_name)}); 
            
            let _record;
            if(search_res && search_res.length()>0){  //layer with such name already exists - replace
                
                let recID = search_res.getOrder();
                recID = recID[0];
                _record = recset.getById(recID);
                recset.setFld(_record, DT_QUERY_STRING, 'N/A');
                //remove previous result set from map
                if(_record['layer']){
                    _record['layer'].removeLayer();    
                    delete _record['layer']; //clear
                }
            }else{
                _record = {rec_ID:_uniqueid,  rec_Title:dataset_name, rec_RecTypeID:RT_MAP_LAYER,  d:{}};
                recset.setFld(_record, DT_QUERY_STRING, 'N/A');
                _record = recset.addRecord(_uniqueid, _record);
                _uniqueid++;
            }
            
            _record['source_rectype'] = RT_QUERY_SOURCE;  //for icon in legend
            recset.setFld(_record, 'layer',
                        new HMapLayer2({recordset: recordset,  //adds recordset
                                        rec_datasource: _record, 
                                        mapdoc_recordset: recset, //need to get fields
                                        mapwidget: options.mapwidget,
                                        preserveViewport:(mapdoc_id!=0) })); //zoom to current search
                                              
                                              
            return _record;
        },        
         
         
        //
        //
        // 
        getTreeData: function( mapdoc_id )
        {
            return _getTreeData( mapdoc_id );
        },
        
        //
        // show/hide entire map document
        //
        setMapDocumentVisibility: function( mapdoc_id, is_visibile ){
            //loop trough all 
            if(!_isDocumentLoaded(mapdoc_id)) return;
            
            let resdata = map_documents_content[mapdoc_id];
            let idx, records = resdata.getRecords();
            for(idx in records){
                if(idx)
                {
                    let record = records[idx];
                    let rtype = resdata.fld(record, 'rec_RecTypeID');
                    if( (rtype==RT_MAP_LAYER || rtype==RT_TLCMAP_DATASET) && record['layer']){
                        (record['layer']).setVisibility( is_visibile ); //set all layers visible
                    }
                }
            }
            
            if(is_visibile){
                if (_defineCRS( mapdoc_id )){
                    //not simple
                    _loadBaseMap( mapdoc_id );
                    _defineZooms( mapdoc_id );
                }else{
                    that.zoomToMapDocument( mapdoc_id );
                }
            }else{
                options.mapwidget.mapping('defineMaxZoom', 'doc'+mapdoc_id, -1); //remove
                options.mapwidget.mapping('defineMinZoom', 'doc'+mapdoc_id, -1); //remove
            }
            
        },


        //
        // update layer visibility for all active map documents according to current zoom 
        // this method is called from zoomend event of native map
        //
        updateLayerVisibility: function( current_zoom, mapdoc_id ){
            
            if(!(DT_MAXIMUM_ZOOM>0 && DT_MINIMUM_ZOOM>0)) return;

            function __updateLayers(resdata){
                if(resdata){
                    let idx, records = resdata.getRecords();
                    for(idx in records){
                        if(idx)
                        {
                            let record = records[idx];
                            let rtype = resdata.fld(record, 'rec_RecTypeID');
                            if( (rtype==RT_MAP_LAYER || rtype==RT_TLCMAP_DATASET) && record['layer'])
                            {
                                (record['layer']).setVisibilityForZoomRange( current_zoom );
                            }
                        }
                    }
                }
            }
            
            if(mapdoc_id>0){
                if(_isDocumentLoaded(mapdoc_id )){
                    let resdata = map_documents_content[mapdoc_id];
                    __updateLayers(resdata);
                }
            }else{ 
                //loop trough all 
                let m_ids = Object.keys(map_documents_content);
                for(let k in m_ids){
                    if(m_ids[k]!='temp'){
                        if(_isDocumentLoaded( m_ids[k] )){
                            let resdata = map_documents_content[ m_ids[k] ];
                            __updateLayers(resdata);
                        }
                    }
                }
            }

        },

        
        //
        // remove map document data and remove it from map
        //
        closeMapDocument: function( mapdoc_id ){
            //loop trough all 
            if(_isDocumentLoaded(mapdoc_id )){
                let resdata = map_documents_content[mapdoc_id];
                let idx, records = resdata.getRecords();
                for(idx in records){
                    if(idx)
                    {
                        let record = records[idx];
                        let rtype = resdata.fld(record, 'rec_RecTypeID');
                        if( (rtype==RT_MAP_LAYER || rtype==RT_TLCMAP_DATASET) && record['layer']){
                            (record['layer']).removeLayer();
                            delete record['layer'];
                        }
                    }
                }
            }
            if(map_documents_content[mapdoc_id]){
                map_documents_content[mapdoc_id] = null;
                delete map_documents_content[mapdoc_id];
            }
         
            if(mapdoc_id!='temp'){
                options.mapwidget.mapping('defineMaxZoom', 'doc'+mapdoc_id, -1); //remove
                options.mapwidget.mapping('defineMinZoom', 'doc'+mapdoc_id, -1); //remove
            }
            
        },

        //
        //
        //
        removeLayer: function(mapdoc_id, rec_id){
            
            if(rec_id>0){
                let layer_rec = that.getLayer(mapdoc_id, rec_id);
                if(layer_rec){
                    (layer_rec['layer']).removeLayer();
                    delete layer_rec['layer']; 
                } 
                (map_documents_content[mapdoc_id]).removeRecord( rec_id );
            }
        },
        
        // 
        // returns default style for mapdoc or layer
        //
        getSymbology: function( mapdoc_id, rec_id ){
            return _getSymbology( mapdoc_id, rec_id );    
        },
        
        // get layer record, take symbology field and title 
        // open symbology editor
        // on exit 1) call mapLayer.applyStyles
        //         2) change title in tree and timeline
        editSymbology: function( mapdoc_id, rec_id, callback ){
            _editSymbology( mapdoc_id, rec_id, callback );    
        },
        
        editImageFilter: function(mapdoc_id, rec_id, callback){
            _editImageFilter( mapdoc_id, rec_id, callback );
        },

        updateLayerSymbology: function(mapdoc_id, rec_id, style){
            return _updateLayerSymbology(mapdoc_id, rec_id, style);
        },
        
        //
        //
        //
        zoomToMapDocument: function(mapdoc_id){

            let ext = null;
            
            ext = that.getMapDocumentBounds(mapdoc_id);
                
            if(ext!=null){ 

                    //extent is taken from mapdocument
                    options.mapwidget.mapping('zoomToBounds', ext);
                
            }else{
                //neither bbox nor bookmark are defined
                //find all layer ids and zoom to summary extent
                let ids = that.getNativeIdsForDocument( mapdoc_id );
                
                if(ids.length>0){
                    options.mapwidget.mapping('zoomToLayer', ids);
                }
            }
        },
        
        //
        // Get map doc bounds
        //
        getMapDocumentBounds: function(mapdoc_id){

            let ext = null;

            if(map_documents!=null && mapdoc_id!='temp'){ //for temp always zoom to real extent
            
                let record2 = map_documents.getById( mapdoc_id );

                ext = window.hWin.HEURIST4.geo.getWktBoundingBox(
                            map_documents.getFieldGeoValue(record2, DT_GEO_OBJECT));
                            
                if(options.mapwidget.mapping('getCurrentCRS')=='Simple'){
                    
                    if(Array.isArray(ext) && ext.length==2){
                        let max_dim = Math.max(ext[1][0]-ext[0][0], ext[1][1]-ext[0][1]);

                        let maxzoom =  Math.ceil(
                            Math.log(
                                max_dim /
                                256
                            ) / Math.log(2)
                        );        
                    
                        if(maxzoom>0 && max_dim>512){
                            let nativemap = options.mapwidget.mapping('getNativeMap');
                            let latlong1 = nativemap.unproject([ext[0][1],ext[0][0]], maxzoom);
                            let latlong2 = nativemap.unproject([ext[1][1],ext[1][0]], maxzoom);
                            
                            ext = [latlong1, latlong2];
                        }          
                    }
                            
                }else            
                if(ext==null){
                    ext = window.hWin.HEURIST4.geo.getHeuristBookmarkBoundingBox(
                            map_documents.fld(record2, DT_MAP_BOOKMARK));
                }
            }

            return ext;
        },

        //
        // returns layer record OR mapdocument record (if rec_id is not defined)
        //
        getLayer: function(mapdoc_id, rec_id){
            
            if(_isDocumentLoaded(mapdoc_id)){
                let _record = null;
                if(rec_id>0){
                    _record = (map_documents_content[mapdoc_id]).getById( rec_id );
                }else if(map_documents) {
                    _record = map_documents.getById( mapdoc_id );
                }
                if(_record) return _record; 
            }
            return null;
        },

        //
        // returns all records for given mapspace (layers and datasources)
        //
        getMapDocumentRecordset: function(mapdoc_id){
            return map_documents_content[mapdoc_id];
        },
        
        
        //
        //
        //
        getNativeIdsForDocument: function(mapdoc_id){

                let ids = [];

                if(_isDocumentLoaded(mapdoc_id)){
                    let resdata = map_documents_content[mapdoc_id];
                    let idx, records = resdata.getRecords();
                    for(idx in records){
                        if(idx)
                        {
                            let record = records[idx];
                            let rtype = resdata.fld(record, 'rec_RecTypeID');
                            if( (rtype==RT_MAP_LAYER || rtype==RT_TLCMAP_DATASET) && record['layer']){
                                ids.push((record['layer']).getNativeId());
                            }
                        }
                    }
                }
                return ids;            
        },
        
        //
        // Add new layer record to map document
        //
        // Opens record selector popup and adds selected layer to given mapdoc
        //
        selectLayerRecord: function(mapdoc_id, callback){
            
            let popup_options = {
                select_mode: 'select_single', //select_multi
                select_return_mode: 'recordset',
                edit_mode: 'popup',
                selectOnSave: true, //it means that select popup will be closed after add/edit is completed
                title: window.hWin.HR('Select or create a layer record'),
                rectype_set: [RT_MAP_LAYER, RT_TLCMAP_DATASET],
                parententity: 0,
                
                onselect:function(event, data){
                         if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                            let recordset = data.selection;
                            let record = recordset.getFirstRecord();
                            let targetID = recordset.fld(record,'rec_ID');
                            
                            let request = {a: 'add',
                                        recIDs: mapdoc_id,
                                        dtyID:  DT_MAP_LAYER,
                                        val:    targetID};
                            
                            window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                                    if(response.status == window.hWin.ResponseStatus.OK){
                                        //refresh treeview - add layer to mapdocument
                                        _addLayerRecord(mapdoc_id, targetID, null);
                                    }else{
                                        window.hWin.HEURIST4.msg.showMsgErr(response);
                                    }
                            });                             
                         }
                },

                onClose:function(event){

                    if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call(that);
                }
            };//popup_options

                    
            let usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_records', 
                {width: null,  //null triggers default width within particular widget
                height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });

            popup_options.width = Math.max(usrPreferences.width,710);
            popup_options.height = usrPreferences.height;
            
            window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);
        },
        
        //
        // adds new layers (by record ids) to mapdocument
        //
        addLayerRecords: function(mapdoc_id, layers_ids, callback ){

            if(!_isDocumentLoaded(mapdoc_id)){

               map_documents_content[mapdoc_id] = new HRecordSet();
            }else{
                
                let to_remove = [];
                //remove from map_document
                map_documents_content[mapdoc_id].each(function(recID, record){
                     if(recID<9000000 && window.hWin.HEURIST4.util.findArrayIndex(recID, layers_ids)<0){
                         let rtype = record['rec_RecTypeID'];
                         if(rtype==RT_MAP_LAYER || rtype==RT_TLCMAP_DATASET){
                             to_remove.push(recID);
                         }
                     }
                });
                for (let idx=0; idx<to_remove.length; idx++){
                    that.removeLayer(mapdoc_id, to_remove[idx]);    
                }
            }
            if(window.hWin.HEURIST4.util.isArrayNotEmpty(layers_ids)){
                _addLayerRecord(mapdoc_id, layers_ids, callback);
            }else{
                callback.call();
            }
        },
        
        //
        //  saves current result set as layer+dataset records
        //
        saveResultSetAsLayerRecord: function(rec_id, callback){
            
            let recset = map_documents_content[0];
            if(!recset) return;
            
            let _record = recset.getById(rec_id);

            let r_name =  recset.fld(_record, 'rec_Title'),
                r_query = _record['original_heurist_query'], //recset.fld(_record, DT_QUERY_STRING),
                r_style = recset.fld(_record, DT_SYMBOLOGY);
                
                
            r_query = window.hWin.HEURIST4.query.hQueryStringify(r_query);
            if(window.hWin.HEURIST4.util.isJSON(r_style)) r_style = JSON.stringify(r_style);
            
            if(window.hWin.HEURIST4.util.isempty( r_query )) return;
            let bnd = _record['layer'].getBounds('wkt');
            if(!bnd){
                return;
            }
            
            
            let details = {};
            details['t:'+DT_NAME] = [ r_name ];
            details['t:'+DT_QUERY_STRING] = [ r_query ];
            details['t:'+DT_GEO_OBJECT] = ['r '+bnd ];
            
            let request = {a: 'save',    //add new relationship record
                        ID:0, //new record
                        RecTypeID: RT_QUERY_SOURCE,
                        //RecTitle: recset.fld(_record,'rec_Title'),
                        details: details};
            
            
            //save datasource record
            window.hWin.HAPI4.RecordMgr.saveRecord(request, function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        let datasource_recID = response.data; //add rec id
                        
                        let details = {};
                        details['t:'+DT_NAME] = [ r_name ];
                        details['t:'+DT_SYMBOLOGY] = [ r_style ];
                        details['t:'+DT_DATA_SOURCE] = [ datasource_recID ];
                            
                        let request = {a: 'save',    //add new relationship record
                                    no_validation: true,
                                    ID:0, //new record
                                    RecTypeID: RT_MAP_LAYER,
                                    details: details};
                        
                        window.hWin.HAPI4.RecordMgr.saveRecord(request, function(response){
                            if(response.status == window.hWin.ResponseStatus.OK){
                                //open new record editor
                                window.hWin.HEURIST4.ui.openRecordEdit(response.data);
                                window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Records saved'));
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                            if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call(that);
                        });
                        
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                        if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call(that);
                    }
            });
        },

        isImageLayer: function(mapdoc_id, layer_id){

            const _recset = map_documents_content[mapdoc_id];
            const _record = _recset.getById( layer_id );

            return window.hWin.HEURIST4.util.isNumber(_record['source_rectype']) && 
                   (_record['source_rectype'] == RT_IMAGE_SOURCE || 
                    _record['source_rectype'] == RT_TILED_IMAGE_SOURCE);
        }
          
    }//end public methods

    _init( _options );
    return that;  //returns object
};

               