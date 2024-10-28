/**
* Storage of records and its definitions (fields and structure)
* 
* requires temporalOBjectLibrary.js to validate and convert date for timeline
* 
* @see recordSearch in db_recsearch.php
* @param initdata
* @returns {Object}
* @see editing_input.js
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
/* global parseWKT */

function HRecordSet(initdata) {
    const _className = "HRecordSet",
    _version   = "0.4";

    let total_count = 0,   //number of records in query  - not match to  length()
    queryid = null, //unique query id
    offset = 0,
    //limit = 1000, use length()
    fields = [],       //array of field names
    fields_detail = [], //array of fieldtypes ids in details 
    records = {},      //list of records objects {recID:[], ....}
    order = [], //array of record IDs in specified order
    mainset = null, //array of record IDs that belong to main result set (without applied rules)
    
    rectypes = [],      // unique list of record types with counts
    structures = null,  //record structure definitions for all rectypes in this record set
    relationship = null, //relationship records within this recordset
    relations_ids = null,  //this is object "direct","reverse","headers"

    limit_warning = null;
    
    let _progress = null,
        _isMapEnabled = false,
        _request = null;
    
    /**
    * Initialization
    */
    function _init(response) {
        
        if(Array.isArray(response)){
            response = {entityName:'Records',count:response.length,offset:0,records:response};
        }
        
        if(response){

            that.entityName = response.entityName;           
            queryid = response.queryid;
            total_count = Number(response.count);
            offset = Number(response.offset);
            
            if(response['limit_warning']){
                limit_warning = response.limit_warning;    
            }
            
            if( !$.isEmptyObject(response.mainset) ){
                mainset = response.mainset;
            }
            
            if( !$.isEmptyObject(response['fields']) ){
                fields = response.fields;
                rectypes = response.rectypes;
                structures = response.structures;
                records = response.records;  //Array.isArray(records)
                order = Array.isArray(response.order)?response.order:[response.order];
                relationship = response.relationship;
                relations_ids = response.relations;
                
                fields_detail = response.fields_detail;
                
                _isMapEnabled = response.mapenabled;

            }else{
                    
                if(response.order){
                    order = Array.isArray(response.order)?response.order:[response.order];    
                    records = response.records?response.records:{};   
                }else{
                    order = response.records;  //ids only   
                    records = {};
                }
                if(response.rectypes) rectypes = response.rectypes;
                _isMapEnabled = false;
            }
        }
        else {
            //if response not defined this is "Heurist Records" 
            that.entityName = 'Records';           
            fields = [];
        }
    }

    /**
    * Converts recordSet to key:title array (for selector)
    */
    function _makeKeyValueArray(namefield){
        
        let result = [];
        
        for(let idx in order){
            if(idx)
            {
                const key = order[idx];
                
                let record = records[key];
                const rec_title = _getFieldValue(record, namefield);
                
                result.push({key:key, title:rec_title});
            }
        }        
        return result;
    }    
    

    //
    //
    //
    function _getDetailsFieldTypes(){
        
        let dty_ids = null;
        if(fields_detail){
              dty_ids = fields_detail;
        }else{
            if(order.length>0){     
                let rec = records[order[0]];
                if(!isnull(rec) && rec['d']){
                    dty_ids = Object.keys(rec['d']);
                }
            }
        }
        return dty_ids;
        
    }
/**
Life cycle for geodata in record

DB - mysql.asWKT ->
recordset.toTimemap - utils_geo.parseWKTCoordinates (wellknown.js parseWKT -> GeoJSON) -> timemap.js (DISPLAY on MAP) 
@todo use google.maps.Data and get rid timemap.js

EDIT   
editing_input.js
mapDraw.js initial_wkt -> _loadWKT (parsing) -> _applyCoordsForSelectedShape  
                          _getWKT  (only one selected shape) returns to edit
@todo 
mapDraw.js initial_wkt -> parseWKT -> GeoJSON -> _loadGeoJSON (as set of separate overlays)
                         _getGeoJSON -> (wellknown.js) stringifyWKT ->  back to input
*/    
    /**
    * @todo - move gmap folder
    * Converts recordSet to OS Timemap dataset
    * 
        * geoType 
        * 0, undefined - all
        * 1 - main geo only (no links)
        * 2 - rec_Shape only (coordinates defined in field rec_Shape)
    */
    function _toTimemap(dataset_name, filter_rt, symbology, geoType){

        let aitems = [], titems = [];
        let item, titem, idx;
        let mapenabled = 0,
            timeenabled = 0;
            
        const MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');    
        
        const localIds = window.hWin.HAPI4.sysinfo['dbconst'];
        const DT_SYMBOLOGY_POINTMARKER = localIds['DT_SYMBOLOGY_POINTMARKER']; //3-1091
        const DT_SYMBOLOGY_COLOR = localIds['DT_SYMBOLOGY_COLOR'];
        const DT_BG_COLOR = localIds['DT_BG_COLOR'];
        const DT_OPACITY = localIds['DT_OPACITY'];
        
        //make bounding box for map datasource transparent and unselectable
        let disabled_selection = [localIds['RT_TILED_IMAGE_SOURCE'],
                                    localIds['RT_GEOTIFF_SOURCE'],
                                    localIds['RT_KML_SOURCE'],
                                    localIds['RT_MAPABLE_QUERY'],
                                    localIds['RT_SHP_SOURCE']];
        
            
        dataset_name = dataset_name || "main";
        
        let iconColor, fillColor, lineColor, fillOpacity, iconMarker = null;
        if(symbology){
            iconColor = symbology.iconColor;
            iconMarker = symbology.iconMarker;
            
            //fill: red; stroke: blue; stroke-width: 3  , fill-opacity, stroke-opacity
            fillColor   = symbology.fill;
            lineColor   = symbology.stroke;
            fillOpacity = symbology['fill-opacity'];
        }
        iconColor = iconColor || 'rgb(255, 0, 0)'; 
        fillColor = fillColor || 'rgb(255, 0, 0)';
        lineColor = lineColor || 'rgb(255, 0, 0)'; 
        fillOpacity = fillOpacity || 0.1;
/*      
                                fillOpacity:0.3,// 0.3,
                                lineColor:lineColor,
                                strokeOpacity:1,
                                strokeWeight:6,
*/         

        let geofields = [], timefields = [];
        
        let dty_ids = _getDetailsFieldTypes(); 
        
        if(!isnull(dty_ids) && window.hWin.HEURIST4){

            //detect geo and time fields from recordset        
            dty_ids.forEach((dty_id)=>{
                const dtype = $Db.dty(dty_id,'dty_Type');
                if(dtype=='date' || dtype=='year'){
                    timefields.push(dty_id);
                }else if(dtype=='geo'){
                    geofields.push(dty_id);
                }
            });
        }
        
        let linkedPlaceRecId = {}; //placeID => array of records that refers to this place (has the same coordinates)
        //linkedRecs - records linked to this place
        //shape - coordinates
        //item - item object to be added to timemap 
        let linkedPlaces = {};//placeID => {linkedRecIds, shape, item}
        
        for(idx in records){
            if(idx)
            {
                let record = records[idx];
                let recTypeID   = Number(_getFieldValue(record, 'rec_RecTypeID'));
                
                if(filter_rt && recTypeID!=filter_rt) continue;

                let
                recID       = _getFieldValue(record, 'rec_ID'),
                recName     = _getFieldValue(record, 'rec_Title'),

                //startDate   = _getFieldValue(record, 'dtl_StartDate'),
                //endDate     = _getFieldValue(record, 'dtl_EndDate'),
                description = _getFieldValue(record, 'dtl_Description'),
                recThumb    = _getFieldValue(record, 'rec_ThumbnailURL'),
                recShape    = _getFieldValue(record, 'rec_Shape'),  //additional shapes - special field created on client side
                
                
                iconId      = _getFieldValue(record, 'rec_Icon');  //used if icon differ from rectype icon
                if(!iconId) iconId = recTypeID; //by default 
                
                //symbology per record overwrite layer symbology
                let pr_iconMarker = _getFieldValue(record, DT_SYMBOLOGY_POINTMARKER);
                let pr_iconColor  = window.hWin.HEURIST4.dbs.getColorFromTermValue(_getFieldValue(record, DT_SYMBOLOGY_COLOR));
                let pr_fillColor  = window.hWin.HEURIST4.dbs.getColorFromTermValue(_getFieldValue(record, DT_BG_COLOR));
                let pr_Opacity    = _getFieldValue(record, DT_OPACITY);

                if(pr_iconMarker){
                    pr_iconMarker  = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                        +'&file='+pr_iconMarker[0];
                }
                
                let fillOpacity_thisRec = pr_Opacity?pr_Opacity:fillOpacity;
                
                
                let html_thumb = '';
                if(recThumb){                             //class="timeline-event-bubble-image" 
                    html_thumb = '<img alt src="'+recThumb+'" style="float:left;padding-bottom:5px;padding-right:5px;">'; 
                    //'<div class="recTypeThumb" style="background-image: url(&quot;'+ fld('rec_ThumbnailURL') + '&quot;);opacity:1"></div>'
                }
                
                let dates = [], startDate=null, endDate=null, dres=null, singleFieldName;
                for(let k=0; k<timefields.length; k++){
                    const datetime = _getFieldValues(record, timefields[k]);
                    if(!isnull(datetime)){   
                        for(let m=0; m<datetime.length; m++){
                            if(timefields[k]==DT_START_DATE){
                                startDate = datetime[m];
                                if(singleFieldName==null){
                                     singleFieldName = $Db.dty(timefields[k], 'dty_Name');
                                }    
                            }else if(timefields[k]==DT_END_DATE){
                                endDate  = datetime[m]; 
                            }else{
                                dres = window.hWin.HEURIST4.util.parseDates(datetime[m]);
                                if(dres){
                                    dates.push(dres);
                                    singleFieldName = $Db.dty(timefields[k], 'dty_Name');
                                }     
                            }
                        }
                    }
                }
                
                if(startDate==null && endDate!=null){
                    if(dres==null){
                        startDate = endDate;    
                        endDate = null;
                    }else{
                        startDate = dres[0];
                    }
                }
                
                //need to verify date and convert from Temporal
                dres = window.hWin.HEURIST4.util.parseDates(startDate, endDate);
                if(dres){
                    dates.push(dres);
                }
                for(let k=0; k<dates.length; k++){
                        
                        if(timeenabled<MAXITEMS){
                     
                            dres = dates[k];
                            
                            if(typeof iconId=='string' && (iconId.indexOf('http:')==0 || iconId.indexOf('https:')==0)){
                                iconImg = iconId;
                            }else if(pr_iconMarker){
                                //icon is set per record
                                iconImg = pr_iconMarker;   
                            }else if(iconMarker){
                                //icon is set per map layer
                                iconImg = iconMarker;    
                            }else{
                                iconImg = window.hWin.HAPI4.iconBaseURL + iconId;
                            }
                            
                            titem = {
                                id: dataset_name+'-'+recID+'-'+k, //unique id
                                group: dataset_name,
                                content: 
                                '<img alt src="'+iconImg + 
                                           '"  align="absmiddle" style="padding-right:3px;" width="12" height="12"/>&nbsp;<span>'+recName+'</span>',
                                //'<span>'+recName+'</span>',
                                title: recName,
                                start: dres[0],
                                recID:recID
                            }
                            
                            if(dres[1] && dres[0]!=dres[1]){
                                titem['end'] = dres[1];
                            }else{
                                titem['type'] = 'point';
                                titem['title'] = singleFieldName+': '+ dres[0] + '. ' + titem['title'];
                            }
                            titems.push(titem);
                        }
                        timeenabled++;
                }


                //a record may have several geofields/placemarks for example place of birth and death
                //besides it may be linked (several times) to "place" record types
                //we need to treat them as separate timemap item
                let shapes = (recShape && geoType!=1)?recShape:[];
                if(!Array.isArray(shapes)){
                    shapes = [];
                }
                
                let has_linked_places = [];
                
                if(geoType!=2){ //get coordinates from geo fields
                    
                    for(let k=0; k<geofields.length; k++){
                        
                        let geodata = _getFieldGeoValue(record, geofields[k]);
                        if(geodata){
                            for(let m=0; m<geodata.length; m++){
                                const shape = window.hWin.HEURIST4.geo.wktValueToShapes( geodata[m].wkt, geodata[m].geotype, 'timemap' );

                                if(shape){ //main shape
                                        
                                    //recID is place record ID - add it as separate item
                                    if(geodata[m].recID>0){ //reference to linked place record
                                        if(!linkedPlaceRecId[geodata[m].recID]){
                                            linkedPlaceRecId[geodata[m].recID] = [];
                                        }else{
                                            //to avoid dark fill for several polygones on the same spot
                                            fillOpacity_thisRec = 0.001;
                                        }
                                        linkedPlaceRecId[geodata[m].recID].push(recID);
                                        
                                        if(linkedPlaces[geodata[m].recID]){
                                            linkedPlaces[geodata[m].recID]['linkedRecs'].push(recID);
                                        }else{
                                            linkedPlaces[geodata[m].recID] = {linkedRecs:[recID],
                                                                                shape:Array.isArray(shape)?shape:[shape]}
                                        }
                                        has_linked_places.push(geodata[m].recID); //one person can be linked to several places
                                        
                                    }else{
                                        //this is usual geo
                                        if(Array.isArray(shapes)){
                                            if(Array.isArray(shape)){
                                                shapes = shapes.concat(shape);                                            
                                            }else{
                                                shapes.push(shape);    
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                    }
                }else{
                    recID = recID + "_link"; //for Digital Harlem
                }
                
                        let iconImgEvt, iconImg;
                        if(typeof iconId=='string' && (iconId.indexOf('http:')==0 || iconId.indexOf('https:')==0) ){
                            //icon is set in data (top prioriry)
                            iconImgEvt = iconId;    
                            iconImg = iconId;    
                        }else if(pr_iconMarker){
                            //icon is set per record
                            iconImg = pr_iconMarker;   
                            iconImgEvt = pr_iconMarker;    
                        }else if(iconMarker){
                            //icon is set per map layer
                            iconImgEvt = iconMarker;    
                            iconImg = iconMarker;    
                        }else{
                            //default icon of record type
                            iconImgEvt = iconId;
                            iconImg = window.hWin.HAPI4.iconBaseURL + iconId + '&color='
                                        + encodeURIComponent(pr_iconColor ?pr_iconColor:iconColor);
                                        
                            if(pr_fillColor){
                                iconImg = iconImg + '&bg='+encodeURIComponent(pr_fillColor);
                            }else{
                                iconImg = iconImg + '&bg='+encodeURIComponent('#ffffff');
                            }
                        }
                        
                        
                        //disable selection for map source databaset bboxes
                        let is_disabled = (window.hWin.HEURIST4.util.findArrayIndex(recTypeID, disabled_selection)>=0);
                        
                        item = {
                            title: recName,
                            start: (startDate || ''),
                            end: (endDate && endDate!=startDate)?endDate:'',
                            placemarks:[],
                            options:{
                                eventIconImage: iconImgEvt,
                                icon: iconImg,
                                iconId: iconId,

                                description: description,
                                //url: (record.url ? "'"+record.url+"' target='_blank'"  :"'javascript:void(0);'"), //for timemap popup
                                //link: record.url,  //for timeline popup
                                recid: recID,
                                bkmid: _getFieldValue(record, 'bkm_ID'),
                                rectype: recTypeID,
                                
                                title: recName,
                                
                                //color on dataset level works once only - timemap bug
                                color: pr_iconColor ?pr_iconColor:iconColor,
                                fillColor: pr_fillColor ?pr_fillColor:fillColor,
                                fillOpacity: (is_disabled)?0.00001:fillOpacity_thisRec,
                                lineColor: pr_iconColor ?pr_iconColor:lineColor,
                                clickable:(!is_disabled),

                                /* neither work
                                strokeOpacity:0.3,
                                strokeWeight:6,
                                width:10,
                                opacity:0.1,
                                */
                                
                                start: (startDate || ''),
                                end: (endDate && endDate!=startDate)?endDate:'',
                                
                                URL: _getFieldValue(record, 'rec_URL'),
                                thumb: html_thumb,
                                
                                info: _getFieldValue(record, 'rec_Info')  //prepared content for map bubble or URL to script
                                
                                //was need for highlight selection on map places: null
                                
                                //,infoHTML: (infoHTML || ''),
                            }
                        }; 
                        
                //keep item object for addition separate items for places
                if(has_linked_places.length>0){
                    for(let k=0; k<has_linked_places.length;k++){
                        if(!linkedPlaces[ has_linked_places[k] ]['item']){
                            linkedPlaces[ has_linked_places[k] ]['item'] = window.hWin.HEURIST4.util.cloneJSON(item);    
                        }
                    }
                }
                        
                                          
                if(shapes.length>0){
                    if(mapenabled<=MAXITEMS){
                        item.placemarks = shapes;
                    }
                    mapenabled++;
                    aitems.push(item);
                }
        }}//for records
      
        //add linked places as separate items 
        //if place was found separetely consider is as one of linked records
        for(let placeID in linkedPlaces){
            if(placeID>0){
                
                if(records[placeID]){
                    linkedPlaces[placeID]['linkedRecs'].push(placeID);
                }
                
                let item = linkedPlaces[placeID]['item']; //item that linked to this place
                item.placemarks = linkedPlaces[placeID]['shape'];
                item.options.linkedRecIDs = linkedPlaces[placeID]['linkedRecs'];
                if(item.options.linkedRecIDs.length>1 && item.options.icon.indexOf('s.png&color=')>0){
                    let clr = encodeURIComponent(item.options.color);
                    item.options.icon = item.options.icon +'&circle='+clr;
                }
                aitems.push(item);
                mapenabled++;
            } 
        }
            
            
        let dataset = 
            {
                id: dataset_name, 
                title: dataset_name, 
                type: "basic",
                timeenabled: timeenabled,
                color: iconColor,
                fillColor: fillColor, 
                lineColor: lineColor, 
                visible: true, 
                mapenabled: mapenabled,
                options: { items: aitems },
                timeline:{ items: titems }, //, start: min_date  ,end: max_date  }
                limit_warning:limit_warning
            };

        return dataset;
    }//end _toTimemap


    /**
    * Converts recordSet to GeoJSON
    * 
        * geoType 
        * 0, undefined - all
        * 1 - main geo only (no links)
        * 2 - rec_Shape only (coordinates defined in field rec_Shape)
        * 
    */
    function _toGeoJSON(filter_rt, geoType, max_limit){
            
        let localIds = window.hWin.HAPI4.sysinfo['dbconst'];
        let DT_SYMBOLOGY = localIds['DT_SYMBOLOGY'];
        
        //make bounding box for map datasource transparent and unselectable
                                    
        let geofields = [], timefields = [];
        
        let dty_ids = _getDetailsFieldTypes(); 
        
        if(!isnull(dty_ids) && window.hWin.HEURIST4){

            //detect geo and time fields from recordset        
            for (let i=0; i<dty_ids.length; i++) {
                let dtype = $Db.dty(dty_ids[i], 'dty_Type');
                if(dtype=='date' || dtype=='year'){
                    timefields.push(dty_ids[i]);
                }else if(dtype=='geo'){
                    geofields.push(dty_ids[i]);
                }
            }
        }
        
        //linkedRecs - records linked to this place
        //shape - coordinates
        //item - item object to be added to timemap 
        //{"geojson":[]}
        function __getGeoJsonFeature(record, extended, simplify){
                 
            let rec_ID = _getFieldValue(record, 'rec_ID');
            
            let res = {type:'Feature', id: rec_ID, properties: _getAllFields(record), geometry:null};

                       
            //time --------------------------           
            let dates = [], startDate=null, endDate=null, dres=null, singleFieldName;
                for(let k=0; k<timefields.length; k++){
                    let datetime = _getFieldValues(record, timefields[k]);
                    if(!isnull(datetime)){   
                        for(let m=0; m<datetime.length; m++){
                            if(timefields[k]==DT_START_DATE){
                                startDate = datetime[m];
                                if(singleFieldName==null){
                                     singleFieldName = $Db.dty(timefields[k], 'dty_Name');
                                }    
                            }else if(timefields[k]==DT_END_DATE){
                                endDate  = datetime[m]; 
                            }else{
                                dres = window.hWin.HEURIST4.util.parseDates(datetime[m]);
                                if(dres){
                                    dates.push(dres);
                                    singleFieldName = $Db.dty(timefields[k], 'dty_Name');
                                }     
                            }
                        }
                    }
                }
                
                if(startDate==null && endDate!=null){
                    if(dres==null){
                        startDate = endDate;    
                        endDate = null;
                    }else{
                        startDate = dres[0];
                    }
                }
                
                //need to verify date and convert from Temporal
                dres = window.hWin.HEURIST4.util.parseDates(startDate, endDate);
                if(dres){
                    dates.push(dres);
                }
                let timevalues = [];
                for(let k=0; k<dates.length; k++){
                        
                            dres = dates[k];
                            
                            let date_start = (dres[0]==null)?dres[1]:dres[0];
                            let date_end = null;
                            if(dres[1] && date_start!=dres[1]){
                                date_end = dres[1];
                            }
                            if(date_start==null) date_start = '';
                            if(date_end==null) date_end = '';
                            timevalues.push([date_start, '', '', date_end, '']);
                }                      
                if(timevalues.length>0){
                    res['when'] = {timespans:timevalues};    
                }
            //END time --------------------------
 
                       
            //geo -----------------------           
            let recShape = _getFieldValue(record, 'rec_Shape');  //additional shapes - special field created on client side
            
                let geovalues = [];  
                if(recShape && geoType!=1){  //geoType==1 from geo fields only, ignore recShape
                    geovalues = [recShape];
                }
                
                if(geoType!=2){ //get coordinates from geo fields geoType==2 form recShape only - ignore native geo coords   
                    
                    for(let k=0; k<geofields.length; k++){
                        
                        let geodata = _getFieldGeoValue(record, geofields[k]);
                        if(geodata){
                            for(let m=0; m<geodata.length; m++){
                                
                                let geo_json = parseWKT(geodata[m].wkt);

                                if(geo_json){ //main shape
                                    geovalues.push(geo_json);
                                    /*    
                                    //recID is place record ID - add it as separate item
                                    if(geodata[m].recID>0){ //reference to linked place record
                                        if(!linkedPlaceRecId[geodata[m].recID]){
                                            linkedPlaceRecId[geodata[m].recID] = [];
                                        }else{
                                            //to avoid dark fill for several polygones on the same spot
                                            fillOpacity_thisRec = 0.001;
                                        }
                                        linkedPlaceRecId[geodata[m].recID].push(recID); //this recID linked to place
                                        
                                        if(linkedPlaces[geodata[m].recID]){
                                            //place already defined - 
                                            linkedPlaces[geodata[m].recID]['linkedRecs'].push(recID);
                                        }else{
                                            linkedPlaces[geodata[m].recID] = {linkedRecs:[recID], shape:geo_json};
                                                                                //art Array.isArray(shape)?shape:[shape]}
                                        }
                                        has_linked_places.push(geodata[m].recID); //one person can be linked to several places
                                        
                                    }else{
                                        geovalues.push(geo_json);
                                    }
                                    */
                                }
                            }
                        }
                        
                    }//for geo fields
                }
                                               
                if(geovalues.length>1){
                    res['geometry'] = {type:'GeometryCollection', geometries:geovalues};
                }else if(geovalues.length==1){
                    res['geometry'] = geovalues[0];
                }
                
                let symbology = _getFieldValue(record, DT_SYMBOLOGY);
                symbology = window.hWin.HEURIST4.util.isJSON(symbology);
                if(symbology){
                    res['style'] = symbology;
                }
            
                return res;
        }//end __getGeoJsonFeature
        
        
        let res_geo = [],
            res_time = [],
            res_geo_ids = [];
        
        for(let idx in records){
            if(idx)
            {
                const record = records[idx];

                const recTypeID   = Number(_getFieldValue(record, 'rec_RecTypeID'));
                if(filter_rt && recTypeID!=filter_rt) continue;
                
                let feature = __getGeoJsonFeature(record, 2, true);
                if(feature['when']){
                            res_time.push({rec_ID: feature.id, 
                                            when: feature['when']['timespans'], 
                                            rec_RecTypeID: feature.properties.rec_RecTypeID, 
                                            rec_Title: feature.properties.rec_Title});
                            feature['when'] = null;
                            delete feature['when'];
                }
                if(!feature['geometry']) continue;
                res_geo.push(feature);
                res_geo_ids.push(feature.id);
                
                if(max_limit>0 && res_geo.length>max_limit) break;
            }
        }//for records    
        
        return {geojson:res_geo, timeline:res_time, geojson_ids:res_geo_ids};
                
    }//end _toGeoJSON
    
   
    // some important id for record and detail types in local values
    const RT_RELATION = window.hWin.HAPI4.sysinfo['dbconst']['RT_RELATION'], //1
        DT_TARGET_RESOURCE = window.hWin.HAPI4.sysinfo['dbconst']['DT_TARGET_RESOURCE'], //5
        DT_PRIMARY_RESOURCE = window.hWin.HAPI4.sysinfo['dbconst']['DT_PRIMARY_RESOURCE'], //7
        DT_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_DATE'],     //9
        //DT_YEAR = window.hWin.HAPI4.sysinfo['dbconst']['DT_YEAR'],     //73
        DT_START_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_START_DATE'], //10
        DT_END_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_END_DATE'], //11
        DT_SHORT_SUMMARY = window.hWin.HAPI4.sysinfo['dbconst']['DT_SHORT_SUMMARY'], //3
        DT_GEO_OBJECT = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT']; //28
        

    //
    // find linked records of specified rectype for given recID
    //
    function _getLinkedRecords(forRecID, forRecTypeID){
        
        
        let record = records[forRecID];
        let dty_ids = _getDetailsFieldTypes(); 
        let links = []; //{related, relation:0, rel_rt}
        
        if(!isnull(record) && !isnull(dty_ids) && window.hWin.HEURIST4){

            //find record pointer fields and its values
            
            for (let i=0; i<dty_ids.length; i++) {
                let dtype = $Db.dty(dty_ids[i], 'dty_Type');
                if(dtype=='resource'){
                    
                    let fldvalue = _getFieldValues(record, dty_ids[i]);
                    
                    if(!isnull(fldvalue)){   

                         for(let m=0; m<fldvalue.length; m++){
                            let g = fldvalue[m].split(',');
                            for(let n=0; n<g.length; n++){
                                let relRec_ID = g[n];
                                let relRec = records[relRec_ID];
                                if(!isnull(relRec)){
                                    let relRec_RecTypeID = Number(_getFieldValue(relRec, 'rec_RecTypeID'));
                                    if(isnull(forRecTypeID) || forRecTypeID == relRec_RecTypeID)
                                    {
                                        links.push({related:relRec_ID, relation:0, rel_rt:relRec_RecTypeID}); 
                                    }
                                }
                            }
                         }
                    }
                    
                    
                }
            }
        }
        return links;        
    }
   
    // find relation records of given type for recID
    // 1. search all relationship records
    // 2. check target or source fields
    // 3. check record type
    //
    // returns array of objects  {relation:recID, related:recTarget, relrt:relRecTypeID}
    //
    function _getRelationRecords(forRecID, forRecTypeID){
        let relations = [];
        
        for(let idx in relationship){
            if(idx)
            {
                const record = relationship[idx];
                const recID = _getFieldValue(record, 'rec_ID');
                const recTypeID   = _getFieldValue(record, 'rec_RecTypeID');
                let recTarget, recSource, relRecTypeID; 
                    
                if(recTypeID == RT_RELATION){
                    
                    recTarget = _getFieldValue(record, DT_TARGET_RESOURCE);
                    recSource = _getFieldValue(record, DT_PRIMARY_RESOURCE);
                
                    if(recTarget==forRecID){
                        
                          if(records[recSource]){
                        
                              relRecTypeID = _getFieldValue(records[recSource], 'rec_RecTypeID');
                              
                              if(forRecTypeID && forRecTypeID != relRecTypeID) {
                                  continue;
                              }
                            
                              relations.push({relation:recID, related:recSource, relrt:relRecTypeID});
                          
                          }
                          
                    }else if(recSource==forRecID){
                        
                          if(records[recTarget]){
                        
                              relRecTypeID = _getFieldValue(records[recTarget], 'rec_RecTypeID');
                            
                              if(forRecTypeID && forRecTypeID != relRecTypeID) {
                                  continue;
                              }
                            
                              relations.push({relation:recID, related:recTarget, relrt:relRecTypeID});
                          
                          }
                    }
                }
            }
        }
        
        return relations;
    
    }
    
    //to be implemented
    /*
    function _getRelationRecordByID(forRecID, relRecID){

            var i, rels = _getRelationRecords(forRecID, null);
            for(i=0; i<rels.length; i++){
                var recID = rels[i]['related'];
                return relationships[rels[i]['relation']]
            }        
    }
    */

    //
    // geo value is in format
    // geotype:recID WKT
    // geotype - p,pl,c,l
    // recID optional reference to real geo record (linked place)
    // WKT - coordinates
    function _getFieldGeoValue(record, fldname){

        let geodata = _getFieldValues(record, fldname);
        if(!isnull(geodata)){   
             let m, res = [];
             for(m=0; m<geodata.length; m++){
                let g = geodata[m].split(' ');
                let gt = g[0].split(':');
                let geoRecID = (gt && gt.length==2)?gt[1]:0;
                gt = gt[0];  //geotype
                
                g.shift(); //remove first
                let wkt = g.join(' ');           
                let oRes = {geotype:gt, wkt:wkt};
                
                if(geoRecID>0){
                    oRes['recID'] = geoRecID;
                }
                
                res.push(oRes);
             }
             return res;
        }else{
            return null;
        }
    }
    
    /**
    * public method "values"
    * record - recId or record object
    */
    function _getFieldValues(record, fldname){
        if(window.hWin.HEURIST4.util.isempty(fldname)) return null;

        if( (!$.isPlainObject(record)) && !isnull(record) && !Array.isArray(record)){
            if(records[record]){
                record = records[record];    
            }else{
                return null;
            }
        }
        
        if(isnull(record)){
            return null
        }else{  //@todo calcfields
        
            let idx = $.inArray(fldname, fields);
            if(idx>-1){
                return record[idx];
            }else if(  (isNaN(Number(fldname)) && fldname.indexOf("dtl_")!=0) && record[fldname] ){
                return record[fldname];
            }else if(record['d'] && record['d'][fldname]){   
                return record['d'][fldname]
            }else{
                return null;   
            }
        }
    }

    /**
    * return record as proper json (with field names rather than indexes
    *     
    * @param record
    * @param fldname
    */
    function _getAllFields(record){
        
        let res = {};
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(fields)){
        
            for(let idx in fields)
            if(idx>-1){
                //field to index
                if(typeof record[idx]!=='undefined'){
                    res[fields[idx]] = record[idx];    
                }else if(record[fields[idx]]){
                    res[fields[idx]] = record[fields[idx]];    
                }
            }
            
            if(record['d']){
                res['d'] = window.hWin.HEURIST4.util.cloneJSON(record['d']);     
            }
        }else{
            res = window.hWin.HEURIST4.util.cloneJSON(record);
        }
        return res;
    }

    
    /**
    * public method "fld"
    * Returns field value by fieldname
    * WARNING for multivalues it returns first value ONLY
    * @todo - obtain fieldtype codes from server side
    */
    function _getFieldValue(record, fldname, lang){

        
        if( (!$.isPlainObject(record)) && !isnull(record) && records[record]){
            record = records[record]; //record id is assumed
        }
        
        if(isnull(record) || window.hWin.HEURIST4.util.isempty(fldname)){
            return null;
        }
        
        if(that.calcfields && window.hWin.HEURIST4.util.isFunction(that.calcfields[fldname])){
            return that.calcfields[fldname].call(that, record, fldname);
        }
        
        //this is field type ID  or field name (nominal for most common fields)
        let d = record['d'];
        if(d){   
            //if fieldname is numeric index of starts with dtl_
            if(!isNaN(Number(fldname)) || fldname.indexOf("dtl_")==0){  //@todo - search detail by its code
            
                if(!isNaN(Number(fldname))){ //dt code
                    if(d[fldname] && d[fldname][0]){
                        
                        /*
                        var dt = __getDataType(fieldName);
                        if(dt=='integer' || dt=='float'){
                            return Number(d[fldname][0]);
                        }else{
                            return d[fldname][0];    
                        }
                        */
                        //"xx" means take current system language
                        return window.hWin.HAPI4.getTranslation(d[fldname], lang);

                    }
                }else if(fldname=="dtl_StartDate"){
                    if(d[DT_START_DATE] && d[DT_START_DATE][0]){
                        return d[DT_START_DATE][0];
                    }else if(d[DT_DATE]){
                        return d[DT_DATE][0];
                    }
                }else if(fldname=="dtl_EndDate"){
                    return _getFieldValue(record, DT_END_DATE);

                }else if(fldname=="dtl_Description"){
                    return _getFieldValue(record, DT_SHORT_SUMMARY);
                    
                }else if(fldname.indexOf("dtl_Geo")==0 && d[DT_GEO_OBJECT] && d[DT_GEO_OBJECT][0]){
                    let g = d[DT_GEO_OBJECT][0].split(' ');

                    if(fldname=="dtl_Geo"){
                        g.shift();
                        return g.join(' '); //return coordinates only
                    }else{
                        return g[0];  //return geotype - first part of dtl_Geo field - "p wkt"
                    }
                }
                
                return null;
            }
        }

        //either take value by index or by name
        // record can be either array or object
        let idx = $.inArray(fldname, fields);
        if(idx>-1){
            return record[idx];
        }else{
            return isnull(record[fldname])?null:record[fldname];
        }
    }
    
    function isnull(obj){
        return ( (typeof obj==="undefined") || (obj===null));
    }
    
    function _setFieldValue(record, fldname, newvalue){

        if(!isNaN(Number(fldname))){  //@todo - search detail by its code
            let d = record['d'];
            if(!d){
                record['d'] = {};
            }
            if(Array.isArray(newvalue)){
                record['d'][fldname] = newvalue;
            }else{
                record['d'][fldname] = [newvalue];    
            }
            
            
        }else {
            //header fields always single values except rec_Shape

            if(Array.isArray(newvalue) && fldname!='rec_Shape'){
                newvalue = (newvalue.length>0)?newvalue[0]:null;
            }
            
            let idx = $.inArray(fldname, fields);
            if(idx>-1){
                record[idx] = newvalue;
            }else{
                record[fldname] = newvalue;
            }
        }
    }
    
    //public members
    let that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className || strClass === 'hRecordSet');},
        getVersion: function () {return _version;},
        entityName:'',
        calcfields:{}, //set of callback functions for calculation fields
                       // is is used tp generate value for rec_Info field for mapping popup

        //
        //
        //                                      
        getFieldVisibilites: function(record, fldId){
            let res = null;
            
            if(!isnull(record) && fldId>0 && 
                record['v'] && record['v'][fldId])
            {   
                res = record['v'][fldId];
            }
            return res;
        },
                       
        /**
        * Returns field value by fieldname for given record
        */
        fld: function(record, fldName, lang){
            return _getFieldValue(record, fldName, lang);
        },

        values: function(record, fldName){
            return _getFieldValues(record, fldName);
        },
        
        getFieldGeoValue: function(record, fldName){
            return _getFieldGeoValue(record, fldName);
        },
        
        setFld: function(record, fldName, value){
            _setFieldValue(record, fldName, value);  
        },

        setFldById: function(recID, fldName, value){
            if(records[recID])
                _setFieldValue(records[recID], fldName, value);  
        },

        getFldById: function(recID, fldName){
            if(records[recID]){
                return _getFieldValue(records[recID], fldName);
            }else{
                return null;
            }
        },
        
        //
        // assign value of field from one record to another
        //
        transFld: function(recordTo, recordFrom, fldName, isNoNull){
            
            let value = _getFieldValue(recordFrom, fldName);
            if( window.hWin.HEURIST4.util.isempty(value) && isNoNull) {
                return false
            }else{
                _setFieldValue(recordTo, fldName, value);  
                return true;
            }
        },
        
        //
        // returns record by id
        //
        getById: function(recID){
            return records[recID];
        },

        
        //
        // returns array of {recid:fieldvalue} for all records
        //
        makeKeyValueArray:function(titlefield){
            return _makeKeyValueArray(titlefield);
        },
        
        //
        // returns record as JSON object
        //
        getRecord: function(recID){
            let record = this.getById(recID);
            if(record){
                return _getAllFields(record);
            }else{
                return null;
            }
        },

        
        
        /**
        * returns all record ids from recordset
        * 
        * @returns {Array}
        */
        getIds: function( limit ){
            
            if(limit>0){
                return order.slice(0, limit);
            }
            
            return order;
        },
        
        //get ids for given array of records
        getIds2: function( recs, limit ){
            
            let aitems = [];
            let recID;
            if(limit>0){
                for(recID in recs)
                    if(recID){
                        aitems.push(recID);
                        if(aitems.length>limit) break
                    }
            }else{
                aitems = Object.keys(recs);
                /*
                for(recID in recs)
                    if(recID){
                        aitems.push(recID);
                    }*/
            }

                
            return aitems;
        },
        
        
        getIdsByRectypeId: function(rty_ID){

            rty_ID = Number(rty_ID);
            let res = [];
            
            if(rty_ID>0)
            for(let recID in records)
                if(recID){
                    let rec = records[recID];
                    let recTypeID = Number(_getFieldValue(rec, 'rec_RecTypeID'));
                    if(rty_ID==recTypeID){
                        res.push(recID);
                    }
                }
                
            return res;
        },
        
        
        /*
        getIdsChunked: function(chunk){
            var res = [];
            var aitems = [];
            var recID;
            for(recID in records)
                if(recID){
                    aitems.push(recID);
                    if(aitems.length==chunk){
                        res.push(aitems);
                        aitems = [];
                    }
                }
            if(aitems.length>0){
                res.push(aitems);
            }
            return res;
        },*/

        getBookmarkIds: function(){
            let aitems = [];
            let recID, bkmID;
            for(recID in records)
            if(recID){
                bkmID = _getFieldValue(records[recID], 'bkm_ID');
                if(bkmID>0) aitems.push(bkmID);
            }
            return aitems;
        },
        
        /**
        * traverce all records - pass to callback  recID and record
        * 
        * @param callback
        */
        each: function( callback ){
        
            for(let i=0; i<order.length; i++){
                let recID = order[i];
                let record = records[recID];
                let res = callback.call(that, recID, record);
                if(res === false){
                    break;
                }
            }
            
        },

        // returns record in callback as json with fieldnames
        each2: function( callback ){
        
            for(let i=0; i<order.length; i++){
                let recID = order[i];
                let record = that.getRecord(recID);
                let res = callback.call(that, recID, record);
                if(res === false){
                    break;
                }
            }
            
        },

            
        /**
        * Returns recordSet with the same field and structure definitions
        * 
        * @param  _records - list of objects/records
        * 
        * @returns {HRecordSet}
        */
        getSubSet: function(_records, _order){
            
            if(_records==null){
                _records = {};
            }
            if(!window.hWin.HEURIST4.util.isArrayNotEmpty(_order)){
                _order = that.getIds2(_records);    
            }
            
            return new HRecordSet({
                entityName: that.entityName,
                queryid: queryid,
                count: _order.length,
                total_count: _order.length,
                offset: 0,
                fields: fields,
                fields_detail:fields_detail,
                rectypes: rectypes,
                structures: structures,
                records: _records,
                order: _order
            });
        },

        //
        //
        //
        getSubSetByIds: function(rec_ids){
            let _records = {};
            let _order = [];
            //find all records
            
            if($.isEmptyObject(records)) return null;
            
            let recID;
            if(Object.keys(records).length<rec_ids.length){

                for(recID in records)
                    if(recID && window.hWin.HEURIST4.util.findArrayIndex(recID, rec_ids)>-1) {
                        _records[recID] = records[recID];
                        _order.push(recID);
                    }

            }else{
                for(let idx=0; idx<rec_ids.length; idx++)
                {
                    recID = rec_ids[idx];
                    if(records[recID]){
                        _records[recID] = records[recID];    
                        _order.push(recID);
                    }
                }

            }
            
            return this.getSubSet(_records, _order);
        },

        //
        // sortFields:  { "fieldName":-1|1 }
        //
        sort: function(sortFields){
            
            let fieldName, dataTypes={};
            
            if(sortFields==null || $.isEmptyObject(sortFields)) return
            
            for (fieldName in sortFields) {
                if (Object.hasOwn(sortFields,fieldName) ){
                    let dt_type = 'freetext';
                    if(fieldName=='rec_RecTypeID' || fieldName=='rec_ID'){
                        dt_type = 'integer';
                    }else 
                    if(Number(fieldName)>0){
                        dt_type = $Db.dty(fieldName,'dty_Type');
                    }
                    if(dt_type=='resource'){
                        dt_type = 'integer';
                    }
                    dataTypes[fieldName] = dt_type;
                }
                
            }
            
            if(Object.keys(dataTypes).length>0){

                order.sort(function(a,b){  
                        let res = 0;                        
                        for (fieldName in sortFields) {
                            if (Object.hasOwn(sortFields, fieldName) ){
                                let val1 = that.fld(records[a], fieldName);
                                let val2 = that.fld(records[b], fieldName);
                                if(dataTypes[fieldName]=='integer' || dataTypes[fieldName]=='float'){
                                    if(Number(val1)!=Number(val2)){
                                        res = sortFields[fieldName]*(Number(val1)<Number(val2)?-1:1);
                                    }
                                }else{
                                    if(dataTypes[fieldName]=='date'){
                                        let dres = window.hWin.HEURIST4.util.parseDates(val1, val2);
                                        val1 = dres[0];
                                        val2 = dres[1];
                                    }
                                    if(val1) val1 = val1.toLowerCase();
                                    if(val2) val2 = val2.toLowerCase();
                                    if(val1!=val2){
                                        res = sortFields[fieldName]*(val1<val2?-1:1);
                                    }
                                }
                                if(res!=0){
                                    break;
                                }
                            }
                        }//for
                        return res;
                    });
            }
            
        },
        
        //
        //  returns subset by request/filter
        //
        // request:  { "sort:fieldName":-1|1 , fieldName:=value, fieldName:value, fieldName:'NULL' }
        // structure [{dtID:fieldname, dtFields:{dty_Type: } }]
        //    if structure not defined - default type is freetext
        getSubSetByRequest: function(request, structure){
            
            let _records = {}, _order=[], that = this;
            
            if(request==null || $.isEmptyObject(request)) return this;

            // if structure not defined - default type is freetext            
            function __getDataType(fieldname, struct){
                let idx;
                if(struct!=null){
                    for (idx in struct){
                        if(struct[idx]['children']){
                            return __getDataType(fieldname, struct[idx]['children']);
                        }else
                        if(struct[idx]['dtID']==fieldname){
                              let res = struct[idx]['dtFields']['dty_Type'];  
                              return (res=='resource' 
                                    || (res=='enum' && that.entityName=='Records') )
                                        ?'integer':res;
                        }
                    }
                    return null;
                }else{
                    return 'freetext';
                }
            }
            
            let fieldName, dataTypes={}, sortFields = [], sortFieldsOrder=[];
            let isexact = {};
            let isnegate= {};
            let isless= {};
            let isgreat= {};
            //remove empty fields from request
            for (fieldName in request) {
                if (Object.hasOwn(request, fieldName) ){
                    if(window.hWin.HEURIST4.util.isempty(request[fieldName])) {
                        delete request[fieldName];    
                    }else if(fieldName.indexOf('sort:')<0){
                        
                        //find data type
                        dataTypes[fieldName] = __getDataType(fieldName, structure);
                        
                        if(dataTypes[fieldName]=='freetext' 
                            || dataTypes[fieldName]=='blocktext' 
                            || dataTypes[fieldName]=='integer'
                            || dataTypes[fieldName]=='enum')
                        {
                            request[fieldName] = String(request[fieldName]).trim();
                            
                            request[fieldName] = request[fieldName].toLowerCase();
                            
                            if(request[fieldName].substring(0,2)=='!='){
                                request[fieldName] = request[fieldName].substring(2);
                                isnegate[fieldName] = true;
                            }else
                            if(request[fieldName][0]=='='){
                                request[fieldName] = request[fieldName].substring(1);
                                isexact[fieldName] = true;
                            }else
                            if(request[fieldName][0]=='<'){
                                request[fieldName] = request[fieldName].substring(1);
                                isless[fieldName] = true;
                            }else
                            if(request[fieldName][0]=='>'){
                                request[fieldName] = request[fieldName].substring(1);
                                isgreat[fieldName] = true;
                            }else 
                            if(dataTypes[fieldName]=='integer' || dataTypes[fieldName]=='enum'){
                                isexact[fieldName] = true;    
                            }
                            
                        }
                    }else{
                        let realFieldName = fieldName.substr(5);
                        sortFieldsOrder.push(Number(request[fieldName])); //1 - ASC, -1 DESC
                        sortFields.push(realFieldName);
                        dataTypes[realFieldName] = __getDataType(realFieldName, structure);
                    }
                }
            }            

            if($.isEmptyObject(request)) return this; //return all

            
            //search
            for(let recID in records){
                let record = records[recID];
                let isOK = true;
                for(fieldName in request){
                    if(fieldName.indexOf('sort:')<0 && Object.hasOwn(request, fieldName)){

                        let fldvalue = this.fld(record,fieldName);
                            
                        if(dataTypes[fieldName]=='freetext' 
                            || dataTypes[fieldName]=='blocktext'
                            || dataTypes[fieldName]=='integer'
                            || dataTypes[fieldName]=='enum'){
                                
                            if(window.hWin.HEURIST4.util.isnull(fldvalue)){
                                isOK = (fldvalue=='NULL');
                                break;                            
                            }else{
                                let cmp_value;
                                if(dataTypes[fieldName]=='integer' || dataTypes[fieldName]=='float'){
                                    fldvalue = Number(fldvalue);
                                    cmp_value = Number(request[fieldName]);
                                }else{
                                    fldvalue = fldvalue.toLowerCase();
                                    cmp_value = request[fieldName];
                                }
                                
                                if(isnegate[fieldName]){
                                    isOK = (fldvalue != cmp_value);
                                    if(!isOK) break;                            
                                }else 
                                if(isexact[fieldName]){
                                    isOK = (fldvalue == cmp_value);
                                    if(!isOK) break;                            
                                }else
                                if(isless[fieldName]){
                                    isOK = (fldvalue < cmp_value);
                                    if(!isOK) break;                            
                                }else
                                if(isgreat[fieldName]){
                                    isOK = (fldvalue > cmp_value);
                                    if(!isOK) break;                            
                                }else
                                if(fldvalue.indexOf(cmp_value)<0){ //contain
                                    isOK = false;
                                    break;                            
                                }
                            }
                            
                        }else if(fldvalue!=request[fieldName]){
                            isOK = false;
                            break;                            
                        }
                    }
                }
                if(isOK){
                    _records[recID] = record;    
                    _order.push(recID);
                }
            }
            
            if(sortFields.length>0){
                if(dataTypes[sortFields[0]]=='integer' || dataTypes[sortFields[0]]=='float'){

                    _order.sort(function(a,b){  
                        return sortFieldsOrder[0]*(Number(that.fld(records[a], sortFields[0]))<Number(that.fld(records[b], sortFields[0]))
                                ?-1:1);
                    });
                    
                }else{
                    _order.sort(function(a,b){  
                        let val1 = that.fld(records[a], sortFields[0]);
                        let val2 = that.fld(records[b], sortFields[0]);
                        if(val1) val1 = val1.toLowerCase();
                        if(val2) val2 = val2.toLowerCase();
                        return sortFieldsOrder[0]*(val1<val2?-1:1);
                    });
                }
            }
            
            return this.getSubSet(_records, _order);
        },
        
        //
        // take records from given recordset
        //
        fillHeader: function( recordset2 ){
            
            if(recordset2==null){
                return;
            }
            
            if($.isEmptyObject(fields)) fields = recordset2.getFields();
            if(!$.isEmptyObject(rectypes)) {
                let rectypes2 = recordset2.getRectypes();
                if(!$.isEmptyObject(rectypes2)) {
                    jQuery.merge( rectypes2, rectypes );
                    rectypes = jQuery.uniqueSort( rectypes2 );
                }
            }else{
                rectypes = recordset2.getRectypes();
            }    
           
            
            let records2 = recordset2.getRecords();
            let order2 = recordset2.getOrder();
            let idx, recid;
            
            for (idx=0;idx<order2.length;idx++){
                recid = order2[idx];
                //todo - check that this id is in order
                if(recid){ //&& records2[recid]){ 
                    records[recid] = records2[recid];
                }
            }
            
        },
        
        /**
        * Returns new recordSet that is the join from current and given recordset
        */
        doUnite: function(recordset2, before_rec_id){
            if(recordset2==null){
                return that;
            }
            
            let insert_at = -1;
            if(before_rec_id>0){
                insert_at = window.hWin.HEURIST4.util.findArrayIndex(before_rec_id, order);
            }
            
            //join records
            let records2 = recordset2.getRecords();
            let order2 = recordset2.getOrder();
            
            let order_new = order, records_new = records, idx, recid;
            
            for (idx=0;idx<order2.length;idx++){
                recid = order2[idx];
                //for (recid in records2){
                if(recid && !records[recid]){ //there is not such record in target
                    records_new[recid] = records2[recid];
                    if(insert_at>=0){
                        order_new.splice(insert_at,0,recid);
                        insert_at++;
                    }else{
                        order_new.push(recid);    
                    }
                }
            }
            //join structures
            /* var structures2 = recordset2.getRecords();
            @todo
            for (rt_id in structures){
                if(rt_id && !structures2[rt_id]){
                    structures2[rt_id] = structures[rt_id];
                }
            }
            typedefs, names, pluralNames
            */
            
            
            /*var fields2 = recordset2.getFields();
            jQuery.merge( fields2, fields );
            fields2 = jQuery.uniqueSort( fields2 );*/

            let rectypes2 = recordset2.getRectypes();
            if(!rectypes2) {
                rectypes2 = rectypes;
            }else{
                jQuery.merge( rectypes2, rectypes );
                rectypes2 = jQuery.uniqueSort( rectypes2 );
            }
            
            let relationship2 = recordset2.getRelationship();
            if(!relationship2) {
                relationship2 = relationship;   
            }else{
                jQuery.merge( relationship2, relationship );
                relationship2 = jQuery.uniqueSort( relationship2 );
            }
            
            return new HRecordSet({
                entityName: that.entityName,
                queryid: queryid,
                count: Math.max(order_new.length,total_count), //keep from original
                offset: 0,
                fields: fields,
                rectypes: rectypes2,
                structures: structures,
                records: records_new,
                order: order_new,
                relationship: relationship2
            });
        },
        
        /**
        * Returns the actual number of records 
        * 
        * @type Number
        */
        length: function(){
            //return Object.keys(records)
            return order.length;
        },

        /**
        * get count of all records for current request
        */
        count_total: function(){
            return total_count;
        },

        offset: function(){
            return offset;
        },
        
        queryid:function(){
            return queryid;
        },

        /**
        * Get all records s
        */
        getRecords: function(){
            return records;
        },
        
        getOrder: function(){
            return order;
        },

        setOrder: function(_order){
            order = _order;
        },
        
        /**
        * Returns first record from recordSet
        */
        getFirstRecord: function(){
            
            if(order.length>0){
                return records[order[0]];
            }
            return null;
        },
        
        getLastRecord: function(){
            if(order.length>0){
                return records[order.length-1];
            }
            return null;
        },

        /**
        * record structure definitions for all rectypes in this record set
        * optional - may be empty
        */
        getStructures: function(){
            return structures;
        },
        
        getRectypes: function(){
            return rectypes;
        },
        
        //
        // set fields defintions
        //
        getFields: function(){
            return fields;
        },

        setFields: function(_fields){
            fields = _fields;
        },
        
        //
        // returns dty_IDs
        // applicable for Heurist records only
        //
        getDetailsFieldTypes:function(){
            return _getDetailsFieldTypes();    
        },
        
        
        //  
        //  list of record ids that belong to main request (search)
        //  since some records may belong to results of rules requests (linked, related records) 
        //  or relationship records
        //
        getMainSet: function(){
            if( !$.isEmptyObject(mainset) ){
                return mainset;
            }else{
                return order;
            }
        },

        setMainSet: function(_mainset){
            if( !$.isEmptyObject(_mainset) ){
                mainset = _mainset;
            }else{
                mainset = null;
            }
        },
        
        //
        // flag that marks if recordset has detail data for mapping and timeline
        //
        isMapEnabled: function(){
            return _isMapEnabled;
        },
        
        setMapEnabled: function(){
            _isMapEnabled = true;
        },

        //
        // keep search request that results this recordset
        //
        setRequest: function(request){
            _request = request;
        },
        
        getRequest: function(){
            return _request;
        },
        
        /**
        * Converts recordSet to geoJSON (for leaflet mapping)
        * 
        * geoType 
        * 0, undefined - all
        * 1 - main geo only
        * 2 - rec_Shape only
        */
        toGeoJSON: function(filter_rt, geoType, max_limit){
            return _toGeoJSON(filter_rt, geoType, max_limit);
        },
        
        /**
        * Converts recordSet to GoogleMap Timemap dataset
        * 
        * geoType 
        * 0, undefined - all
        * 1 - main geo only
        * 2 - rec_Shape only
        */
        toTimemap: function(dataset_name, filter_rt, symbology, geoType){
            return _toTimemap(dataset_name, filter_rt, symbology, geoType);
        },
        
        setProgressInfo: function(data){
            _progress = data;
        },

        getProgressInfo: function(){
            return _progress;
        },
        
        getLinkedRecords: function(forRecID, forRecTypeID){
            return _getLinkedRecords(forRecID, forRecTypeID);
        },
        
        getRelationRecords: function(forRecID, forRecTypeID){
            return _getRelationRecords(forRecID, forRecTypeID);
        },

        /*to be implemented
        getRelationRecordByID: function(forRecID, relRecID){
            return _getRelationRecordByID(forRecID, relRecID);
        },*/
        
        getRelationship: function(){
            return relationship;
        },
        
        getRelations:function(){
            return relations_ids;    
        },

        
        removeRecord:function(recID){
            delete records[recID];           //@todo check how it affect select_multi
            let idx = window.hWin.HEURIST4.util.findArrayIndex(recID, order);
            if(idx>=0){
                order.splice(idx,1);
                total_count = total_count-1;
            }
        },

        /*
        // add new record and returns new record id
        //
        addRecord3:function(record){
            
            var recID = order[order.length-1];
            while (true){
                recID++;
                if(records[recID]==null) break;
            }  
            
            this.addRecord2(recID, record);
            
            return recID;
        },*/
        
        //
        // add/replace record with given ID
        //    
        addRecord:function(recID, record, add_to_begin){
            let idx = window.hWin.HEURIST4.util.findArrayIndex(recID, order);
            if(idx<0){ //add new
                
                if(fields && fields.length>0){
                    records[recID] = [];
                    records[recID][fields.length-1] = undefined;    
                }else{
                    records[recID] = {};
                }
                if(add_to_begin===true){
                    order.unshift(recID);
                }else{
                    order.push(recID);    
                }
                
                total_count = total_count+1;
            }
            return this.setRecord(recID, record);
        },

        //
        // direct copy record
        //
        addRecord2:function(recID, record){
            let idx = window.hWin.HEURIST4.util.findArrayIndex(recID, order);
            if(idx<0){ //add new
                order.push(recID);
                total_count = total_count+1;
            }
            records[recID] = record;
        },
        
        //
        // add/replace record with given ID
        //    
        setRecord:function(recID, record){
            let idx = window.hWin.HEURIST4.util.findArrayIndex(recID, order);
            if(idx>=0){
                
                if($.isPlainObject(record)){
                    let fldname;
                    for (fldname in record) {
                        if (Object.hasOwn(record,fldname) ){
                            _setFieldValue(records[recID], fldname, record[fldname]);    
                        }
                    }
                }else if(Array.isArray(record)){
                    records[recID] = record;
                }
                return records[recID];
            }else{
                return this.addRecord(recID, record);
            }
        },
        
        //
        //returns data as JSON array for fancytree
        // {key: title:  children: folder:}
        // fieldTitle,  - field name for title
        // fieldLink  -  field name for hierarchy link
        //
        getTreeViewData:function(fieldTitle, fieldLink, rootID){
            
            /*
            source: [
    {title: "Node 1", key: "1"},
    {title: "Folder 2", key: "2", folder: true, children: [
      {title: "Node 2.1", key: "3", myOwnAttr: "abc"},
      {title: "Node 2.2", key: "4"}
    ]}
  ]
            */
            
            //find vocabs only - ids that have children
            let recID, vocabs = [];
            for(recID in records){
                let record = records[recID];
                let id = this.fld(record, fieldLink);
                if(!window.hWin.HEURIST4.util.isempty(id) && id>0 
                    && window.hWin.HEURIST4.util.findArrayIndex(recID,vocabs)<0) { //  $.inArray(recID, vocabs)
                    vocabs.push(id);
                }
            }
            
            function __addChilds(that, parentId){
                let recID, res = [];
                for(recID in records){
                    let record = records[recID];
                    
                    let id = that.fld(record, fieldLink);
                    if(window.hWin.HEURIST4.util.isempty(id) || id==0) id = null;
                    
                    if(parentId==id){
                        let node = {title: that.fld(record,fieldTitle), key: recID};
                        if(window.hWin.HEURIST4.util.findArrayIndex(recID, vocabs)>-1){  //$.inArray(recID, vocabs)>-1
                            let children = __addChilds( that, recID );
                            if(children.length>0){
                                node['children'] = children;
                                node['folder'] = true;
                            }
                        }
                        res.push( node );
                    }
                }
                //sort by fieldTitle
                res.sort(function(a,b){
                    return a.title>b.title ?1:-1;    
                });
                
                return res;
            }
            
            let res = __addChilds(this, rootID);
            
            if(rootID>0){
                //res = [{key:rootID, title:'root', folder:true, children:res }];    
            }
            
 /*           
            for(recID in records){
                var record = records[recID];
                
                var node = {title: this.fld(record,fieldTitle), key: this.fld(record,fieldId)};
                
                var parentId = this.fld(record, fieldLink);
                
                
                if(window.hWin.HEURIST4.util.isempty(parentId)){
                   res.push(node); //root
                   refs[recID] = [res.length-1];
                }else{
                    //find parent
                    var parentNodeRef = refs[recID];
                    var parentNode;
                    if(!parentNodeRef){
                        parentNode = {title:'', key:parentId, children:[]};
                        res.push(node); //root
                        refs[recID] = [res.length-1];
                    }else{
                        parentNode = res[]
                    }
                    if(!parentNode.children){
                        parentNode.children = [];
                    }
                    parentNode.children.push(node);
                }
            
            }//for
*/            
            return res;
        },
        
        //
        // get flat array of all linked children records
        //
        getAllChildrenIds:function(fieldLink, rootID){
            
            if(rootID>0){
                
                //find vocabs only - ids that have children
                let recID, vocabs = [];
                for(recID in records){
                    let record = records[recID];
                    let id = this.fld(record, fieldLink);
                    if(!window.hWin.HEURIST4.util.isempty(id) && id>0 
                        && window.hWin.HEURIST4.util.findArrayIndex(recID,vocabs)<0) { //  $.inArray(recID, vocabs)
                        vocabs.push(id);
                    }
                }
                
                function __addChilds(that, parentId){
                    let recID, res = [];
                    for(recID in records){
                        let record = records[recID];
                        
                        let id = that.fld(record, fieldLink);
                        if(window.hWin.HEURIST4.util.isempty(id) || id==0) id = null;
                        
                        if(parentId==id){
                            
                            res.push(recID);
                            
                            if(window.hWin.HEURIST4.util.findArrayIndex(recID, vocabs)>-1){  //$.inArray(recID, vocabs)>-1
                                let children = __addChilds( that, recID );
                                if(children.length>0){
                                    res = res.concat(children);
                                }
                            }
                        }
                    }
                    return res;
                }
                
                let res = __addChilds(this, rootID);
                return res;
            }
        }

    }

    _init(initdata);
    return that;  //returns object
}
