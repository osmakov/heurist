/**
* Class to work with OS Timemap.js and Vis timeline.
* It allows initialization of mapping and timeline controls and fills data for these controls
*
* @param _map - id of map element container
* @param _timeline - id of timeline element
* @param _basePath - need to specify path to timemap assets (images)
* #param mylayout - layout object that contains map and timeline
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
/* global temporalToHumanReadableString, TimeMap, TimeMapTheme, google, vis, HMappingControls, mxn, _adjustLegendHeight */

function hMapping(_mapdiv_id, _timeline, _options, _mylayout) {
    const _className = "Mapping",
    _version   = "0.4";

    let mapdiv_id = null,
    timelinediv_id = null,
    _current_stack_setting = true,

    all_mapdata = {}, // array of all datasets
    selection = [],   // array of selected record ids
    mylayout,         // layout object that contains map and timeline
    _curr_layout = null; // keep current layout sets to avoid redundant update

    let options = {
        mapVisible: true, //otherwise show timeline only
        legendVisible: true,
        defaultZoom: 2
        //to be extended
    };
    
    let tmap = null,  // timemap object
    vis_timeline = null, // vis timeline object
    vis_timeline_range = null,
    vis_timeline_label_mode = 0, //default full label
    drawingManager = null,     //manager to draw the selection rectnagle
    lastSelectionShape,

    keepMinDate = null,
    keepMaxDate = null,
    keepMinMaxDate = true,
    isApplyTimelineFilter = false,
    _keepLastTimeLineRange = null,

    _onSelectEventListener,

    // TimeMap theme
    customTheme = new TimeMapTheme({
            "color": "#0000FF",  //for lines and polygones
            "lineColor": "#0000FF",
            //"icon": "assets/star-red.png",
            "iconSize": [24,24],  //[16,16]
            "iconShadow": null,
            "iconAnchor":[12,12]
    });


    /**
    * Initialization
    */
    function _init(_mapdiv_id, _timeline, _options, _mylayout) {
        mapdiv_id = _mapdiv_id;
        timelinediv_id = _timeline;
        mylayout = _mylayout;
        
        $.extend(options, _options);

        /*if(_mapdata){
        _load(_mapdata);
        }*/
    }

    function _isEmptyDataset(_mapdata){
        return (window.hWin.HEURIST4.util.isnull(_mapdata) ||  (_mapdata['mapenabled']==0 && _mapdata['timeenabled']==0));
    }

    /**
    * show/hide panels map and timeline
    */
    function _updateLayout(){
        
        let ismap = false, istime = false;


        $.each(all_mapdata, function(dataset_id, _mapdata){

            ismap = ismap || (_mapdata.mapenabled>0);
            istime = istime || (_mapdata.timeenabled>0);

            return !(ismap && istime);

        });

        /*
        if(!(ismap || istime)) { //empty
               return false;
        }else if(!ismap){
            //always show the map - since map content can be loaded by selection of map document

            $(".ui-layout-north").hide();
            $(".ui-layout-center").hide();
            $(".ui-layout-resizer-south").hide();
            $(".ui-layout-south").show();
            $(".ui-layout-south").css({'width':'100%','height':'100%'});
            //mylayout.hide('center');


            //mylayout.changeOption('center','minSize',0);
            //$(".ui-layout-resizer-south").css('height',0);
            //mylayout.sizePane('south','100%');
        }*/
        let new_layout = ((ismap?'1':'0') + (istime?'1':'0'));

        if(_curr_layout != new_layout){

            //mylayout.changeOption('center','minSize',300);
            //$(".ui-layout-resizer-south").css('height',7);

            let tha = $('#mapping').height();    //entire height of layout
            let th = Math.floor(tha*0.2);
            th = th>200?200:th;

            $(".ui-layout-north").show();
            $(".ui-layout-center").show();
            $(".ui-layout-resizer-south").show();
            $(".ui-layout-south").css({'height':th+'px'});

            if(!istime){
                mylayout.hide('south');
            }else {
                
                mylayout.show('south', true);
                if(options.mapVisible){
                    if(ismap){
                        mylayout.sizePane('south', th);
                    }else{
                        mylayout.sizePane('south', tha-30);  //center panel with map - reduced to minimum (30px)
                    }
                }else{
                    $(".ui-layout-center").hide();
                    mylayout.sizePane('south', tha);
                }
            }
            if(ismap || !istime){
               $('#map_empty_message').hide();
               $('#map').show();
            }else{
               $('#map').hide();
               $('#map_empty_message').show();
            }

            _curr_layout = new_layout;
        }
        return true;

    }

    /**
    * Load timemap datasets
    * @see HRecordSet.toTimemap()
    *
    * @param _mapdata
    */
    function _addDataset(_mapdata){

        let res = false;

        if(!window.hWin.HEURIST4.util.isnull(_mapdata)){

            let dataset_id = _mapdata.id;

            if(_isEmptyDataset(_mapdata)){ //show/hide panels

                _deleteDataset( dataset_id );

            }else{

                all_mapdata[dataset_id] = _mapdata;  //keep

                _updateLayout();   //show hide panels

let MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');    
let ele_warn = $('#map_limit_warning');

if(_mapdata.limit_warning){
    //cnt = _mapdata.options.items.length;
    ele_warn.html('These results are limited to '+MAXITEMS+' records<br>(limit set in your profile Preferences)<br>Please filter to a smaller set of results').show();//.delay(2000).fadeOut(10000);
}else{
    ele_warn.hide();
}
                _reloadDataset( dataset_id );

                res = true;
            }

            //reload timeline content
            _loadVisTimeline();

        }
        return res;
    }

    //
    //
    //
    function _getDataset( dataset_id ){
        return all_mapdata[dataset_id];
    }

    //
    // change color and reload dataset 
    //
    function _changeDatasetColor( dataset_id, new_color, updateOnMap ){

            let mapdata = _getDataset( dataset_id );

            if(mapdata['color']!=new_color){
                
                let new_color2 = encodeURIComponent(new_color);

                for (let i=0; i<mapdata.options.items.length; i++){
                    
                    let iconId = mapdata.options.items[i].options.iconId;
                    if(typeof iconId=='string' && (iconId.indexOf('http:')==0 || iconId.indexOf('https:')==0)){
                        mapdata.options.items[i].options.icon = iconId;
                    }else{
                        
                        mapdata.options.items[i].options.icon =
                            window.hWin.HAPI4.iconBaseURL + iconId
                                + '&color='+new_color2 + '&bg='+encodeURIComponent('#ffffff')
                                + ((mapdata.options.items[i].options.linkedRecIDs)?'&circle='+new_color2:'');
                    }
                    
                    mapdata.options.items[i].options.color = new_color;
                    mapdata.options.items[i].options.lineColor = new_color;
                    mapdata.options.items[i].options.fillColor = new_color;

                }

                mapdata['color'] = new_color;

                if(updateOnMap){
                    _reloadDataset(dataset_id);
                }
            }
    }

    //
    //
    //
    function _reloadDataset(dataset_id){

                //tmap.opts.centerOnItems = false;
                
                let dataset = tmap.datasets[dataset_id];

                let mapdata = _getDataset(dataset_id);

                /*let datasetTheme = new TimeMapTheme({
                    color: mapdata['color'],
                    lineColor: mapdata['color'],
                    fillColor: mapdata['color']
                });*/

                if(!dataset){ //already exists with such name
                
                    dataset = tmap.createDataset(dataset_id);
                    //dataset.opts.theme = datasetTheme;
                }else{
                    dataset.clear();
                    //dataset.changeTheme(datasetTheme)
                }
                
                let minLat = 9999, maxLat = -9999, minLng = 9999, maxLng = -9999;

                let pnt_counts = 0; //in case MarkerClusterer we have to increase the delay

                dataset.loadItems(mapdata.options.items);
                dataset.each(function(item){
                    item.opts.openInfoWindow = _onItemSelection;  //event listener on marker selection
                    
                    if(item.placemark){
                        if(item.placemark.points){ //polygone or polyline
                            for(let i=0; i<item.placemark.points.length; i++){
                                let point = item.placemark.points[i];
                                
                                if (point.lat < minLat) minLat = point.lat;
                                if (point.lat > maxLat) maxLat = point.lat;
                                if (point.lon < minLng) minLng = point.lon;
                                if (point.lon > maxLng) maxLng = point.lon;
                            }
                        }else{
                                let pm = item.getNativePlacemark();
                                if(pm){
                                    let point = pm.getPosition();
                                    
                                    if (point.lat() < minLat) minLat = point.lat();
                                    if (point.lat() > maxLat) maxLat = point.lat();
                                    if (point.lng() < minLng) minLng = point.lng();
                                    if (point.lng() > maxLng) maxLng = point.lng();
                                    pnt_counts++;
                                }
                        }
                    }
                    
                });
                
                let forceZoom = false;
                if(minLat<-40 && maxLat>70 && minLng<-120 && maxLng>120){
                    minLat = -40;
                    maxLat = 70;
                    minLng = -120;
                    maxLng = 120;
                    forceZoom = true;
                }else{
                    
                    if(minLat<-60)  minLat = -60;
                    if(maxLat>70)   maxLat = 70;
                    if(minLng<-180) minLng = -180;
                    if(maxLng>180)  maxLng = 180;
                    
                }
                //forceZoom = true;
                
                
                
                if(mapdata.min_zoom>0){
                    if(maxLat-minLat<mapdata.min_zoom){
                        let d = minLat+(maxLat-minLat)/2;
                        maxLat = d + mapdata.min_zoom/2;
                        minLat = d - mapdata.min_zoom/2;
                    }
                    if(maxLng-minLng<mapdata.min_zoom){
                        let d = minLng+(maxLng-minLng)/2;
                        maxLng = d + mapdata.min_zoom/2;
                        minLng = d - mapdata.min_zoom/2;
                    }
                }
                if(Math.abs(maxLng-minLng)<0.06 && Math.abs(maxLat-minLat)<0.06){
                    forceZoom = true;  
                }
                
                let southWest = new google.maps.LatLng(minLat, minLng);
                let northEast = new google.maps.LatLng(maxLat, maxLng);
                mapdata.geoextent = new google.maps.LatLngBounds(southWest, northEast);

                dataset.hide();
                dataset.show();
                
                if(forceZoom || mapdata.forceZoom){
                    setTimeout( function(){ _zoomDataset( dataset_id );}, pnt_counts>1000?2000:500 );
                }
    }

    //
    //
    //
    function _deleteDataset(dataset_id){

            // remove from map
            let dataset = tmap.datasets[dataset_id];
            if(dataset){
                 tmap.deleteDataset(dataset_id);
            }

            let mapdata = _getDataset(dataset_id);

            //remove from storage and reload timeline
            if(mapdata){

                     let was_timeenabled = (mapdata.timeenabled>0);

                     delete all_mapdata[dataset_id];
                     //all_mapdata[dataset_id] = undefined;

                     if(was_timeenabled){
                         //reload timeline
                         _loadVisTimeline();
                     }

                     _updateLayout();
            }
    }

    //
    //
    //
    function _showDataset(dataset_id, is_show){
        let dataset = tmap.datasets[dataset_id];
        if(dataset){
            if(is_show){
                tmap.showDataset(dataset_id);
            }else{
                tmap.hideDataset(dataset_id);
            }
        }
        let mapdata = _getDataset(dataset_id);
        mapdata.visible = is_show;
        //remove from storage and reload timeline
        if(mapdata && mapdata.timeenabled>0){
            _loadVisTimeline();
        }
    }

    //
    //
    //
    function _zoomDataset(dataset_id){
        
        //let dataset = tmap.datasets[dataset_id];
        let mapdata = _getDataset(dataset_id);
        if(mapdata && mapdata.geoextent){

              //zoom to geo extent
              let nativemap = tmap.getNativeMap();
              
              let ne = mapdata.geoextent.getNorthEast();
              let sw = mapdata.geoextent.getSouthWest();
              if( Math.abs(ne.lat()-sw.lat())<0.06 && Math.abs(ne.lng()-sw.lng())<0.06 ){
                    nativemap.setCenter(mapdata.geoextent.getCenter()); 
                    nativemap.setZoom(14);      
              }else{
                    nativemap.fitBounds(mapdata.geoextent);
              }
        }
        
        //zoom to time extent
        if(mapdata && mapdata.timeenabled>0){
            //_timelineZoomToRange(map_bookmarks[val]['tmin'],map_bookmarks[val]['tmax']);
        }
    }
    
    
    // get unix timestamp from vis
    // item - record id or timelime item, field - start|end
    function _getUnixTs(item, field, ds){

        let item_id = 0;
        if(item && item['id']){
            item_id = item['id'];
        }else{
            item_id = item;
        }
        if(item_id){

            if(!ds) ds = vis_timeline.itemsData;

            let type = {};
            type[field] = 'Moment';
            let val = ds.get(item_id,{fields: [field], type: type });

            if(val!=null && val[field] && val[field]._isAMomentObject){
                //return val[field].toDate().getTime(); //unix
                return val[field].unix()*1000;
            }
        }
        return NaN;
    }

    function _timelineZoomToAll(){
            if(vis_timeline_range==null){
                vis_timeline_range = vis_timeline.getDataRangeHeurist();
            }
            //let range = vis_timeline.getItemRange(); //@todo calculate once
            _timelineZoomToRange( vis_timeline_range );
    }

    function _timelineZoomToRange(range){
                                                        
            if(!(range && range.min  && range.max && vis_timeline)) return;
        
            let min = vis_timeline.getDate(range.min), // new Date(range.min).getTime(),
                max = vis_timeline.getDate(range.max); //new Date(range.max).getTime();
            let delta = 0;

            if(isNaN(min) || isNaN(max) ) return;
            
            
            if(range['nofit']==undefined){
            let interval = max-min;
            let YEAR = 31536000000;
            let DAY = 86400000;

            let yearmax = (new Date(range.max)).getFullYear();
            let dt = range['omax'];
            let dta = [];
            if(dt!=undefined){
                dta = dt.split('-');
                if(dta.length>0)
                    yearmax = Number(dta[0]);
            }


            if(interval < 1000){ //single date
                    //detect detalization
                    if(dta.length>2){
                        interval = DAY;
                    }else if(dta.length>1){
                        interval = DAY*90;
                    }else{
                        interval = YEAR;
                    }
            }

            if(interval < DAY){  // day  - zoom to 4 days
                delta = DAY*2;
            }else if(interval < DAY*30) {
                delta = DAY*10;
            }else if(interval < DAY*90) { // month - zoom to year
                delta = YEAR/2;
            }else if(interval < YEAR) { // year - zoom to 5
                delta = YEAR*2;
            }else if(interval < YEAR*2){
                delta = YEAR*5;
            }else if(interval < YEAR*50){

                //zoom depend on epoch
                //yearmax = (new Date(range.max)).getFullYear();
                if(yearmax<0){
                    delta = interval*1.5;
                }else if(yearmax<1500){
                    delta = interval;
                }else{
                    delta = interval*0.5;
                }

            }else  if(interval < YEAR*2000){
                delta = interval*0.1;
            }else{
                delta = interval*0.05;
            }

            }
                //this.range.setRange(range.min, range.max, animation);
                vis_timeline.setWindow({
                    start: min-delta,
                    end: max+delta
                });
    }

    //
    //    isApplyTimelineFilter _timelineApplyRangeOnMap
    //
    function _timelineApplyRangeOnMap(params){
        
        if(params==null){
            params = _keepLastTimeLineRange;
        }else{
            _keepLastTimeLineRange = params;    
        }
        
        if (!isApplyTimelineFilter || !vis_timeline.itemsData || !params) return;
        
        //loop by timeline datasets
        
        //if fit range
        let items_visible = [], items_hidden=[];
                 
        
        vis_timeline.itemsData.forEach(function (itemData) {
                    
            let start = _getUnixTs(itemData, 'start');
            let end = 'end' in itemData ? _getUnixTs(itemData, 'end') :start;
            
            //intersection
            let res = false;
            if(start == end){
                res = (start>=params.start_stamp && start<=params.end_stamp);
            }else{
                res = (start==params.start_stamp) || 
                    (start > params.start_stamp ? start <= params.end_stamp : params.start_stamp <= end);
            }
            
            //find record among map items
            if (res) {
                items_visible.push(itemData.recID); // do not use id but item.id, id itself is stringified
            } else {
                items_hidden.push(itemData.recID);
            }

        });
        
        if(items_visible.length>0 || items_hidden.length>0)
        tmap.each(function(dataset){
                dataset.each(function(item){
                    if(items_visible.indexOf( item.opts.recid )>=0){
                        item.showPlacemark();
                    }else if(items_hidden.indexOf( item.opts.recid )>=0){
                        item.hidePlacemark();
                    }
                });
            });
            
    }

    //called once on first timeline load
    // it inits buttons on timeline toolbar
    //
    function _initVisTimelineToolbar(){



        /**
         * Zoom the timeline a given percentage in or out
         * @param {Number} percentage   For example 0.1 (zoom out) or -0.1 (zoom in)
         */
        function __timelineZoom (percentage) {
            let range = vis_timeline.getWindow();
            let interval = range.end - range.start;

            vis_timeline.setWindow({
                start: range.start.valueOf() - interval * percentage,
                end:   range.end.valueOf()   + interval * percentage
            });
        }

        function __timelineZoomToSelection(){

                    let sels = vis_timeline.getSelection();
                    if(sels && sels['length']>0){
                           let range = vis_timeline.getDataRangeHeurist(new vis.DataSet(vis_timeline.itemsData.get(sels)));
                           _timelineZoomToRange(range);
                    }
        }

        function __timelineMoveToLeft(){

            let range2 = vis_timeline.getWindow();
            let interval = range2.end - range2.start;

            if(vis_timeline_range==null){
                    vis_timeline_range = vis_timeline.getDataRangeHeurist();
            }

            _timelineZoomToRange({min:vis_timeline_range.min.getTime(), max:vis_timeline_range.min.getTime()+interval, nofit:true});
        }

        function __timelineMoveToRight(){

            let range2 = vis_timeline.getWindow();
            let interval = range2.end - range2.start;
            let delta = interval*0.1;
            if(vis_timeline_range==null){
                    vis_timeline_range = vis_timeline.getDataRangeHeurist();
            }

            _timelineZoomToRange({min:vis_timeline_range.max.getTime()-interval+delta, max:vis_timeline_range.max.getTime()+delta, nofit:true});
        }

        //not used
        function __timelineShowLabels(){
            if($("#btn_timeline_labels").is(':checked')){
                $(".vis-item-content").find("span").show();
            }else{
                $(".vis-item-content").find("span").hide();
            }
        }
        
        function __timelineEditProperties(){
            
            let $dlg_edit_layer = $('#timeline-edit-dialog').dialog({
                width: 450,
                modal: true,
                resizable: false,
                title: window.hWin.HR('Timeline options'),
                buttons: [
                    {text:window.hWin.HR('Apply'), click: function(){
                        
                        let mode = $( this ).find('input[type="radio"][name="time-label"]:checked').val();
                        
                        vis_timeline_label_mode = mode;
                        let spinner = $("#timeline_spinner");
                        if(mode==2){
                            spinner.show();
                        }else{
                            spinner.hide();
                        }
                        
                        let labelpos = $( this ).find('input[type="radio"][name="time-label-pos"]:checked').val();

                        _current_stack_setting = ($( this ).find('input[type="radio"][name="time-label-stack"]:checked').val()==1);

                        _applyTimeLineLabelsSettings(mode, labelpos);                
                        vis_timeline.redraw();    
                        
                        /*
                        let stack_mode = $( this ).find('input[type="radio"][name="time-label-stack"]:checked').val()==1;
                        if(_current_stack_setting != (stack_mode==1)){
                            _current_stack_setting = (stack_mode==1);
                            vis_timeline.setOptions({'stack':_current_stack_setting});
                        }else{
                            vis_timeline.redraw();    
                        }*/
                        
                        let newval = $( this ).find('input[type="checkbox"][name="time-filter-map"]').is(':checked');
                        
                        if(isApplyTimelineFilter !== newval){
                            isApplyTimelineFilter = newval;
                            if(newval){
                                _timelineApplyRangeOnMap( null );    
                            }else{
                                //reset all map elements to visible
                                tmap.each(function(dataset){
                                        dataset.each(function(item){
                                                item.showPlacemark();
                                        });
                                    });
                            }
                        }
                        window.hWin.HAPI4.save_pref('mapTimelineFilter', isApplyTimelineFilter?1:0);
                        $('#lbl_timeline_filter').html('Time filter '+(isApplyTimelineFilter?'on':'off'));
                        
                        $( this ).dialog( "close" );
                    }},
                    {text:window.hWin.HR('Cancel'), click: function() {
                        $( this ).dialog( "close" );
                    }}
                ]                
            });
            
        }
        
        let toolbar = $("#timeline_toolbar").css({'font-size':'0.8em', zIndex:3});

        $("<button>").button({icon:"ui-icon-circle-plus",showLabel:false, label:window.hWin.HR("Zoom In")})
            .on('click', function(){ __timelineZoom(-0.25); })
            .appendTo(toolbar);
        $("<button>").button({icon:"ui-icon-circle-minus",showLabel:false, label:window.hWin.HR("Zoom Out")})
            .on('click', function(){ __timelineZoom(0.5); })
            .appendTo(toolbar);
        $("<button>").button({icon:"ui-icon-arrowthick-2-e-w",showLabel:false, label:window.hWin.HR("Zoom to All")})
            .on('click', function(){ _timelineZoomToAll(); })
            .appendTo(toolbar);
        $("<button>").button({icon:"ui-icon-arrowthickstop-1-s",showLabel:false, label:window.hWin.HR("Zoom to selection")})
            .on('click', function(){ __timelineZoomToSelection(); })
            .appendTo(toolbar);
        $("<button>").button({icon:"ui-icon-arrowthickstop-1-w",showLabel:false, label:window.hWin.HR("Move to Start")})
            .on('click', function(){ __timelineMoveToLeft(); })
            .appendTo(toolbar);
        $("<button>").button({icon:"ui-icon-arrowthickstop-1-e",showLabel:false, label:window.hWin.HR("Move to End")})
            .on('click', function(){ __timelineMoveToRight(); })
            .appendTo(toolbar);
        $("<button>").button({icon:"ui-icon-gear",showLabel:false, label:window.hWin.HR("Timeline options")})
            .on('click', function(){ __timelineEditProperties(); })
            .appendTo(toolbar);
        $("<label>").attr('id','lbl_timeline_filter')
            .text('').css('font-style','italic').appendTo(toolbar);

            /*
        let menu_label_settings = $('<ul id="vis_timeline_toolbar"><li id="tlm0"><a href="#"><span class="ui-icon ui-icon-check"/>Full label</a></li>'
                        +'<li id="tlm1"><a href="#"><span/>Truncate to bar</a></li>'
                        +'<li id="tlm2"><a href="#"><span/>Fixed length</a></li>'
                        +'<li id="tlm3"><a href="#"><span/>Hide labels</a></li>'
                        //+'<li class="separator">-</li>'
                        //+'<li id="tlm10"><a href="#"><span/>No stack</a></li>'
                        //+'<li id="tlm11"><a href="#"><span/>Label after bar</a></li>'
                        +'</ul>')
                        //+'<li id="tlm4"><a href="#"><span/>Hide labels/No stack</a></li>
        .addClass('menu-or-popup')
        .css({'position':   'absolute', 'padding':'2px', zIndex:99999})
        .appendTo( $('body') )
        .menu({
            select: function( event, ui ) {

                let mode =  Number(ui.item.attr('id').substr(3));
                
                if(mode>=10){
                    return;
                }
                
                //remove all checks
                menu_label_settings.find('span').removeClass('ui-icon ui-icon-check');
                ui.item.find('span').addClass('ui-icon ui-icon-check');
                
                vis_timeline_label_mode = mode;
                let spinner = $("#timeline_spinner");
                if(mode==2){
                    spinner.show();
                }else{
                    spinner.hide();
                }

                _applyTimeLineLabelsSettings(mode);                

                vis_timeline.redraw();
        }})
        .hide();

        //secondary: "ui-icon-triangle-1-s"
        $("<button>").button({icon:"ui-icon-tag",showLabel:false, label:window.hWin.HR("Label settings")})
            .on('click', function(){
                $('.menu-or-popup').hide(); //hide other

                let menu = $( menu_label_settings )
                .show()
                .position({my: "right top", at: "right bottom", of: this });
                $( document ).one( "click", function() { menu.hide(); });
                return false;

            })
            //.css({width:'5em'})
            .appendTo(toolbar);
        */

        let spinner = $( "<input>", {id:"timeline_spinner", value:10} ).appendTo(toolbar);
        $("#timeline_spinner").spinner({
              value: 10,
              spin: function( event, ui ) {
                if ( ui.value > 100 ) {
                  $( this ).spinner( "value", 100 );
                  return false;
                } else if ( ui.value < 5 ) {
                  $( this ).spinner( "value", 5 );
                  return false;
                } else {
                    $(".vis-item-content").css({'width': ui.value+'em'});

                }
              }
            }).css('width','2em').hide();//rre.hide();

            
            
        isApplyTimelineFilter = (window.hWin.HAPI4.get_prefs_def('mapTimelineFilter', 1)==1);   
            
        if(isApplyTimelineFilter){    
            $('#timeline-edit-dialog').find('input[type="checkbox"][name="time-filter-map"]').prop('checked',true);
        }
       
    }
    
    //  mode 
    // 0 - full, 1 - truncate, 2- fixed width, 3 - hide
    //
    function _applyTimeLineLabelsSettings(mode, labelpos){
        
                let contents = $(".vis-item-content");
                let spinner = $("#timeline_spinner");

                /*if(mode==0){  //full length
                    $.each(contents, function(i,item){item.style.width = 'auto';});//.css({'width':''});
                }else if(mode==2){  //specific length 
                    contents.css({'width': spinner.spinner('value')+'em'});
                }*/

                if(mode==1){ //truncate
                    $('div .vis-item-overflow').css('overflow','hidden');
                    contents.css({'width': '100%'});
                }else{
                    $('div .vis-item-overflow').css('overflow','visible');
                    if(mode==0){  //full length
                        //$.each(contents, function(i,item){item.style.width = 'auto';});//.css({'width':''});
                        contents.css({'width': 'auto'});
                    }else if(mode==2){  //specific length 
                        contents.css({'width': spinner.spinner('value')+'em'});
                    }else{
                        contents.css({'width': '16px'});
                    }                    
                }
                
                if(labelpos==2){ //above
                    $(".vis-item-bbox").css('top','10px');
                }else{
                    $(".vis-item-bbox").css('top','25%');
                }
                /*
                if(labelpos==1){ //on right
                }else{ //on left
                }*/
                    
                
                

                //'label_in_bar':(mode==1),
                vis_timeline.setOptions({'margin':1,'stack':_current_stack_setting}); //(mode!=4)

                /*
                if(mode>=3){ //hide labels at all
                    contents.find("span").css('visibility','hidden'); //.hide();
                }else{
                    contents.find("span").css('visibility','visible'); //.show();
                }
                */
        
    }                

    //init visjs timeline
    function _loadVisTimeline(){

        let timeline_data = [],
            timeline_groups = [];

        $.each(all_mapdata, function(dataset_id, mapdata){

            let cnt = mapdata.timeline.items.length;
            if(mapdata.visible && cnt>0){

                timeline_data = timeline_data.concat( mapdata.timeline.items );
                timeline_groups.push({ id:dataset_id, content: mapdata.title});

            }
        });

        if(timeline_data){

        let groups = new vis.DataSet( timeline_groups );
        let items = new vis.DataSet( timeline_data ); //options.items );

        let is_stack = _current_stack_setting;//(timeline_groups.length<2 && timeline_data.length<250);

        let timeline_ele = document.getElementById(timelinediv_id)

        if(vis_timeline==null){
            timeline_ele = document.getElementById(timelinediv_id);
            // Configuration for the Timeline
            let options = {dataAttributes: ['id'],
                           orientation:'both', //scale on top and bottom
                           selectable:true, multiselect:true,
                           zoomMax:31536000000*500000,
                           stack:is_stack,  //how to display items: staked or in line 
                           margin:1,
                           minHeight: $(timeline_ele).height(),
                           order: function(a, b){
                               return a.start<b.start?-1:1;
                           }
                           };
                        //31536000000 - year
            // Create a Timeline
            vis_timeline = new vis.Timeline(timeline_ele, null, options);
            
            vis_timeline.on('rangechanged', function(params){
                    _timelineApplyRangeOnMap(params);  
            });
            //on select listener
            vis_timeline.on('select', function(params){
                selection = params.items;
                if(selection && selection.length>0){

                    let e = params.event;
                                                //div.vis-item.vis-dot.vis-selected.vis-readonly
                    e.cancelBubble = true;
                    if (e.stopPropagation) e.stopPropagation();
                    e.preventDefault();
                    if($(e.target).hasClass('vis-item vis-dot vis-selected')) return;

                    //remove dataset prefixes
                    $.each(selection,function(idx, itemid){
                        let k = itemid.indexOf('-');
                        if(k>0){
                            itemid = itemid.substring(k+1);
                            k = itemid.indexOf('-');
                            if(k>0){
                                itemid = itemid.substring(0,k);
                            }
                        }
                        selection[idx] = itemid;
                    });

                    $( document ).bubble( "option", "content", "" );
                    _showSelection(true, true, null); //show selection on map
                    if(_onSelectEventListener)_onSelectEventListener.call(that, selection); //trigger global selection event
                }
            });

            //init timeline toolbar
            _initVisTimelineToolbar();

        }else{
            //vis_timeline.setOptions({'stack':is_stack});
        }

        vis_timeline_range = null;
        
        let timeline_content = $(timeline_ele).find('.vis-itemset');
        timeline_content.hide();
        
        vis_timeline.setGroups(groups);
        vis_timeline.setItems(items);
        
        
        //apply label settings
        _applyTimeLineLabelsSettings(vis_timeline_label_mode, 0);
        
        if(isApplyTimelineFilter) _timelineApplyRangeOnMap(null);
        window.hWin.HAPI4.save_pref('mapTimelineFilter', isApplyTimelineFilter?1:0);
        $('#lbl_timeline_filter').html('Time filter '+(isApplyTimelineFilter?'on':'off'));
        
        
        _timelineZoomToAll();

        timeline_content.show();

        }
    }


    function _load(_mapdata, _selection, __startup_mapdocument, __onSelectEventListener, _callback){
        
            function __onDataLoaded(_tmap){  //this function is called only once after map initialization
                tmap = _tmap;

                //first map initialization
                let nativemap = tmap.getNativeMap();

                google.maps.event.addListenerOnce(nativemap, 'tilesloaded', function(){

                    let lt = window.hWin.HAPI4.sysinfo['layout'];  
                    let scrollwheel = true;
                    
                    // Add controls if the map is not initialized yet
                    let mapOptions = {
                        //panControl: true,
                        zoomControl: true,
                        mapTypeControl: true,
                        //scaleControl: true,
                        overviewMapControl: true,
                        //rotateControl: true,
                        scrollwheel: scrollwheel, // enable  zoom by mouse scroll
                        //gestureHandling: 'cooperative',
                        
                        mapTypeControlOptions: {
                            mapTypeIds: ["terrain","roadmap","hybrid","satellite","tile"],
                        }
                    };
                    
                    let nativemap = tmap.getNativeMap();
                    nativemap.setOptions(mapOptions);

                    // MapTypeStyleFeatureType 
                    let styledMapType = new google.maps.StyledMapType(
                            [{
                                "featureType": "poi",
                                //"elementType": "labels",
                                "stylers": [
                                    { "visibility": "off" }
                                ]
                            },
                            {
                                "featureType": "road",
                                "elementType": "labels.icon",
                                "stylers": [
                                    {
                                        "visibility": "off"
                                    }
                                ]
                            },
                            {
                                "featureType": "transit",
                                "stylers": [
                                    {
                                        "visibility": "off"
                                    }
                                ]
                            }]);
                    //Associate the styled map with the MapTypeId and set it to display.
                    nativemap.mapTypes.set('no_poi', styledMapType);
                    nativemap.setMapTypeId('no_poi');


                    if(window.hWin.HAPI4.get_prefs_def('mapSelectTools', 1)==1){
                        _initDrawListeners();    
                    }
                    

                    if (!__startup_mapdocument) { //zoom to whole world
                        let swBound = new google.maps.LatLng(-40, -120);
                        let neBound = new google.maps.LatLng(70, 120);
                        let bounds = new google.maps.LatLngBounds(swBound, neBound);
                        nativemap.fitBounds(bounds);
                    }
                    
                    // loading the list of map documents  see mapOverlay.js
                    that.map_control = new HMappingControls(that, __startup_mapdocument);

                    
                    $("#map-settingup-message").hide();
                    $(".map-inited").show();

                    if(_callback){
                        _callback.call();
                    }else{ //not used - to remove

                        //ART 20151026  _updateLayout();
                        //highlight selection
                        //ART 20151026  _showSelection(false);
                    }

                });

       }// __onDataLoaded

       
        
       
       
        //asign 2 global for mapping - on select listener and startup map document
        if(__onSelectEventListener) _onSelectEventListener = __onSelectEventListener;

        //_mapdata = _mapdata || [];
        selection = _selection || [];

        //timemap is already inited
        if(that.map_control!=null){

                if(window.hWin.HEURIST4.util.isFunction($( document ).bubble)){
                    $( document ).bubble('closeAll');  //close all popups    
                }

                if(__startup_mapdocument>0){
                    that.map_control.loadMapDocumentById(__startup_mapdocument);    //see mapOverlay.js
                }

                if(_callback){
                    _callback.call(); //call init completed and further addRecordsetLayer
                }else{
                    //ART 20151026  _updateLayout();
                    //highlight selection
                    _showSelection(false);
                }

        }else{

            // add fake/empty datasets for further use in mapdocument (it is not possible to add datasets dynamically)
            if(!_mapdata){
                _mapdata = [{id: "main", type: "basic", options: { items: [] }}];
            }
            
            let useMarkerClusterer = window.hWin.HEURIST4.util.getUrlParameter('mapcluster');
            let markerClustererOpts = {};
            
            if(!window.hWin.HEURIST4.util.isempty(useMarkerClusterer)){ //take all MarkerClusterer options from URL
            
                if(useMarkerClusterer=='off'){
                   useMarkerClusterer = false; 
                }else{
                    let params = useMarkerClusterer.split(',');
                    if(params.length>0 && params[0]>0){
                        markerClustererOpts['gridSize'] = parseInt(params[0]);
                    }
                    if(params.length>1 && params[1]>0){
                        markerClustererOpts['minimumClusterSize'] = parseInt(params[1]);
                    }
                    if(params.length>2 && params[2]>0 && params[2]<17){
                        markerClustererOpts['maxZoom'] = parseInt(params[2]);
                    }
                    markerClustererOpts['zoomOnClick'] = false;
                }
                
            }else{
                //MarkerClusterer options
                useMarkerClusterer = (window.hWin.HAPI4.get_prefs_def('mapcluster_on', 0)==1);
                markerClustererOpts = 
                    { gridSize: parseInt(window.hWin.HAPI4.get_prefs_def('mapcluster_grid', 25)), //The grid size of a cluster in pixels.
                      zoomOnClick: false,  
                      minimumClusterSize: parseInt(window.hWin.HAPI4.get_prefs_def('mapcluster_count', 2)), //The minimum number of markers to be in a cluster before the markers are hidden and a count is shown.
                      maxZoom: parseInt(window.hWin.HAPI4.get_prefs_def('mapcluster_zoom', 15))};   //The maximum zoom level that a marker can be part of a cluster.
            }
            
                    //return;
            
            // Initialize TimeMap
            tmap = TimeMap.init({
                mapId: mapdiv_id, //Id of gmap div element (required)
                timelineId: null, //timelinediv_id, // Id of timeline div element (required)
                datasets: _mapdata,

                options: {
                    mapZoom: options['defaultZoom'],
                    theme: customTheme,
                    eventIconPath: window.hWin.HAPI4.iconBaseURL,
                    useMarkerClusterer: useMarkerClusterer,
                    markerClustererOpts: markerClustererOpts
                }
                , dataLoadedFunction: __onDataLoaded
                }, tmap);


        }

    }

    //
    // adds draw button and init them for google map DrawingManager
    //
    function _initDrawListeners(){
        
            if(drawingManager!=null) return;

            let shift_draw = false;

            //addd drawing manager to draw rectangle selection tool
            let shapeOptions = {
                strokeWeight: 1,
                strokeOpacity: 1,
                fillOpacity: 0.2,
                editable: false,
                clickable: false,
                strokeColor: '#3399FF',
                fillColor: '#3399FF'
            };

            drawingManager = new google.maps.drawing.DrawingManager({
                drawingMode: null,
                drawingControlOptions: {
                    position: google.maps.ControlPosition.TOP_RIGHT, //LEFT_BOTTOM,
                    drawingModes: [google.maps.drawing.OverlayType.POLYGON, google.maps.drawing.OverlayType.RECTANGLE]
                },
                rectangleOptions: shapeOptions,
                polygonOptions: shapeOptions,
                map: tmap.getNativeMap()
            });

            
            function __clearCurrentShape(){
                if (!window.hWin.HEURIST4.util.isnull(lastSelectionShape)) {
                    lastSelectionShape.setMap(null);
                    lastSelectionShape = null;
                }
            }
            
            google.maps.event.addListener(drawingManager, 'overlaycomplete', function(e) {

                //clear previous
                __clearCurrentShape();

                // cancel drawing mode
                if (shift_draw == false) { drawingManager.setDrawingMode(null); }

                lastSelectionShape = e.overlay;
                lastSelectionShape.type = e.type;

                _selectItemsInShape();

                /*if (lastSelectionShape.type == google.maps.drawing.OverlayType.RECTANGLE) {

                    lastBounds = lastSelectionShape.getBounds();

                    //$('#bounds').html(lastBounds.toString());

                    //_mapdata.options.items[0].options.recid
                    //_mapdata.options.items[3].placemarks[0].polyline .lat .lon


                    //new google.maps.LatLng(25.774252, -80.190262),


                    // determine if marker1 is inside bounds:
                    if (lastBounds.contains(m1.getPosition())) {
                        //$('#inside').html('Yup!');
                    } else {
                        //$('#inside').html('Nope...');
                    }

                } else if (lastSelectionShape.type == google.maps.drawing.OverlayType.POLYGON) {

                    //$('#bounds').html('N/A');

                    // determine if marker is inside the polygon:
                    // (refer to: https://developers.google.com/maps/documentation/javascript/reference#poly)
                    if (google.maps.geometry.poly.containsLocation(m1.getPosition(), lastSelectionShape)) {
                        //$('#inside').html('Yup!');
                    } else {
                        //$('#inside').html('Nope...');
                    }

                }*/

            });

            /*let shift_draw = false;

            $(document).on('keydown', function(e) {
            if(e.keyCode==16 && shift_draw == false){
            map.setOptions({draggable: false, disableDoubleClickZoom: true});
            shift_draw = true; // enable drawing
            drawingManager.setDrawingMode(google.maps.drawing.OverlayType.RECTANGLE);
            }

            });

            $(document).on('keyup', function(e) {
            if(e.keyCode==16){
            map.setOptions({draggable: true, disableDoubleClickZoom: true});
            shift_draw = false // disable drawing
            drawingManager.setDrawingMode(null);
            }

            });*/

            //clear rectangle on any click or drag on the map
            // Clear the current overlay
            google.maps.event.addListener(drawingManager, 'drawingmode_changed', function(){
                __clearCurrentShape();
            }); 
            
            google.maps.event.addListener(tmap, 'mousedown', function () {
                __clearCurrentShape();
            });

            google.maps.event.addListener(tmap, 'drag', function () {
                __clearCurrentShape();
            });
            

            //define custom tooltips

            $(tmap.getNativeMap().getDiv()).one('mouseover','img[src="https://maps.gstatic.com/mapfiles/drawing.png"]',function(e){

                $(e.delegateTarget).find('img[src="https://maps.gstatic.com/mapfiles/drawing.png"]').each(function(){
                  $(this).closest('div[title]').attr('title',function(){
                     switch(this.title){
                      case 'Stop drawing':
                        return 'Drag the map or select / get information about an object on the map';
                      case 'Draw a rectangle':
                        return 'Select objects within a rectangle. Hold down Ctrl to add to current selection';
                      case 'Draw a shape':
                        return 'Select objects within a polygon - double click to finish the polygon';
                      default:return this.title;
                     }

                  });
                });
              });




    }

    //
    // FOR MAIN DATASET ONLY
    //
    function _selectItemsInShape(){

        selection = [];

        let isRect = (lastSelectionShape.type == google.maps.drawing.OverlayType.RECTANGLE);
        let lastBounds, i;
        if(isRect){
            lastBounds = lastSelectionShape.getBounds();
        }
        let selected_placemarks = [];

        let dataset = tmap.datasets.main;  //take main dataset
            dataset.each(function(item){ //loop trough all items

                        if(item.placemark){
                            let isOK = false;
                            if(item.placemark.points){ //polygone or polyline
                                for(let i=0; i<item.placemark.points.length; i++){
                                    let pnt = item.placemark.points[i];
                                    let pos = new google.maps.LatLng(pnt.lat, pnt.lon);
                                    if(isRect){
                                        isOK = lastBounds.contains( pos );
                                    }else{
                                        isOK = google.maps.geometry.poly.containsLocation(pos, lastSelectionShape);
                                    }
                                    if(isOK) break;
                                }

                            }else{

                                let placemarks = (Array.isArray(item.placemark))?item.placemark:[item.placemark];
                                for(let i=0;i<placemarks.length;i++){
                                    //let pos = placemarks[i].getPosition();
                                    //let pos = item.getNativePlacemark().getPosition();
                                    let pnt = placemarks[i].location;
                                    let pos = new google.maps.LatLng(pnt.lat, pnt.lon);
                                    let isInShape = false;
                                
                                    if(isRect){
                                        isInShape = lastBounds.contains( pos );
                                    }else{
                                        isInShape = google.maps.geometry.poly.containsLocation(pos, lastSelectionShape);
                                    }
                                    if(isInShape){
                                        selected_placemarks.push( placemarks[i] );
                                        isOK = true;
                                    }
                                }

                            }
                            if(isOK){
                                selection.push(item.opts.recid);
                            }


                        }
            });


        //reset and highlight selection
        _showSelection(true, false, selected_placemarks); 
        //trigger selection - to highlight on other widgets
        if(_onSelectEventListener)_onSelectEventListener.call(that, selection);
    }

    //
    // select items with the same coordinates  FOR MAIN DATASET AND POINTERS ONLY
    //
    function _selectItemsWithSameCoords(main_item, selected_placemark){

        //linkedRecIDs - recids that refers to same place id - see recordset.toTimemap            
        if(main_item.opts.linkedRecIDs){
            selection = main_item.opts.linkedRecIDs;
         
            _showSelection(true, false, [selected_placemark]);
            //trigger selection - to highlight on other widgets
            if(_onSelectEventListener)_onSelectEventListener.call(that, selection);
            return; 
        }

        //find markers with the same coordinates 
        let res = _getPlaceMarkFromItem( main_item, selected_placemark );
        if(res.placemark_type=='marker'){
        
            selection = [];
            
            let selected_placemarks = [];
            
            //main_item.getNativePlacemark()   
            /*let pos = res.placemark.getPosition();
            let lat = pos.lat();
            let lng = pos.lng();*/
            let lat = res.placemark.location.lat;
            let lng = res.placemark.location.lon;

            let dataset = tmap.datasets.main;  //take main dataset
                dataset.each(function(item){ //loop trough all items
                    if(item.placemark){
                        
                        let placemarks = (Array.isArray(item.placemark))?item.placemark:[item.placemark];
                        
                        for(let i=0;i<placemarks.length;i++){
                            if(!placemarks[i].points && placemarks[i] instanceof mxn.Marker){
                                
                                //let pos = placemarks[i].getPosition();
                                let pos = placemarks[i].location;
                                
                                if(pos.lat==lat && pos.lon==lng){
                                    selection.push(item.opts.recid);
                                    selected_placemarks.push(placemarks[i]);
                                }
                            }
                        }
                    }
                });

            //reset and highlight selection
            _showSelection(true, false, selected_placemarks);
            //trigger selection - to highlight on other widgets
            if(_onSelectEventListener)_onSelectEventListener.call(that, selection);
        
        }
    }

    //
    // Add clicked marker to array of selected - invoked from timemap
    //
    // (timemap)item.opts.openInfoWindow -> _onItemSelection  -> _showSelection (highlight marker) -> _showPopupInfo
    // this - selected item
    //
    function _onItemSelection( selected_placemark ){
        //that - hMapping
        //this - item (map item)
        //placemark_selected that was cliked - item may have several placemarks
        
        let res = _getPlaceMarkFromItem( this, selected_placemark );
        if(res.placemark_type=='marker' && 
            !this.placemark.points && this.dataset.id=='main'){
        
            _selectItemsWithSameCoords( this, selected_placemark );
        }else{

            selection = [this.opts.recid];
            _showSelection(true, false, [selected_placemark]); //highlight marker
            
            //trigger global selection event - to highlight on other widgets
            if(_onSelectEventListener) _onSelectEventListener.call(that, selection);
            //TimeMapItem.openInfoWindowBasic.call(this);
        }
    }

    //
    // highlight markers and show bubble
    //
    //  item.opts.openInfoWindow -> _onItemSelection  -> _showSelection -> _showPopupInfo
    //
    // isreset - true - remove previous selection
    //
    function _showSelection( isreset, fromtimeline, selected_placemarks ){

            //select items on timeline
            if(!fromtimeline && vis_timeline){

                let selection_vis = [];

                $.each(all_mapdata, function(dataset_id, _mapdata){
                   if(_mapdata.timeenabled>0) {
                        $.each(_mapdata.timeline.items, function(idx, titem){
                           if(selection.indexOf(titem.recID)>=0){
                                selection_vis.push( titem.id );
                           }
                        });
                   }
                });
                vis_timeline.setSelection( selection_vis );
            }

            if(selection && selection.length>0){

                let lastRecID = selection[selection.length-1];
                let lastSelectedItem = null;
                let selected_placemark = null;
                
                if(Array.isArray(selected_placemarks) && selected_placemarks.length>0){
                    $(selected_placemarks).each( function(i, pm){
                        if (pm.item.opts.recid == lastRecID){
                                lastSelectedItem = pm.item;
                                selected_placemark = pm;
                                return false;
                        }
                    });
                    if(lastSelectedItem==null){
                        selected_placemark = selected_placemarks[0];
                        lastSelectedItem = selected_placemark.item;
                    }
                    
                }else{

                    tmap.each(function(dataset){
                        dataset.each(function(item){ //loop trough all items

                        
                        
                            if(lastRecID==item.opts.recid || 
                                (item.opts.linkedRecIDs &&                             
                                 window.hWin.HEURIST4.util.findArrayIndex(lastRecID, item.opts.linkedRecIDs)>=0) ){
                                lastSelectedItem = item;
                                selected_placemark = item.placemark;
                                return false;
                            }
                        });
                        if(lastSelectedItem != null) return false; //exit from "each"
                    });
                
                }

                //find selected item in the dataset
                if(lastSelectedItem){
                    _showPopupInfo.call(lastSelectedItem, selected_placemark);
                }
                    
            }else if(!fromtimeline){
                
                _zoomDataset( 'main' );
            }
            
            
    }

    //
    // old version with highlight on map - need to implement in different way
    //
    function _showSelection_old( isreset ){

        let lastSelectedItem = null;
        let items_to_update = [];       //current item to be deleted
        let items_to_update_data = [];  // items to be added (replacement for previous)
        //let dataset = tmap.datasets.main;  //take main dataset

        if ( isreset || (selection && selection.length>0) ){

            let lastRecID = (selection)?selection[selection.length-1]:-1;

            tmap.each(function(dataset){

                dataset.each(function(item){ //loop trough all items

                    if(item.opts.places && item.opts.places.length>0){

                        let idx = selection ?selection.indexOf(item.opts.recid) :-1;

                        let itemdata = {
                        datasetid: dataset.id,
                        title: ''+item.opts.title,
                        start: item.opts.start,
                        end: item.opts.end,
                        placemarks: item.opts.places,
                        options:item.opts
                          /*{
                            description: item.opts.description,
                            //url: (record.url ? "'"+record.url+"' target='_blank'"  :"'javascript:void(0);'"), //for timemap popup
                            //link: record.url,  //for timeline popup
                            recid: item.opts.recid,
                            rectype: item.opts.rectype,
                            title: (item.opts.title+'+'),
                            //thumb: record.thumb_url,
                            eventIconImage: item.opts.rectype,
                            icon: window.hWin.HAPI4.iconBaseURL + item.opts.rectype,

                            start: item.opts.start,
                            end: item.opts.end,
                            places: item.opts.places
                            //,infoHTML: (infoHTML || ''),
                                }*/
                            };


                        if(idx>=0){ //this item is selected

                            items_to_update.push(item);

                            //was itemdata
                            itemdata.options.eventIconImage = item.opts.iconId;   //+ 's.png' it will have selected record (blue bg)
                            itemdata.options.icon = window.hWin.HAPI4.iconBaseURL + itemdata.options.eventIconImage; 
                                                                    //'&bg='+encodeURIComponent('#ffffff')
                            itemdata.options.color = "#FF0000";
                            itemdata.options.lineColor = "#FF0000";

                            items_to_update_data.push(itemdata);

                        }else{ //clear selection
                            //item.opts.theme
                            //item.changeTheme(customTheme, true); - dont work
                            let usual_icon = item.opts.iconId; // + 'm.png'; //it will have usual gray bg
                            if(usual_icon != itemdata.options.eventIconImage){

                                items_to_update.push(item);

                                //was itemdata
                                itemdata.options.eventIconImage = usual_icon;
                                itemdata.options.icon = window.hWin.HAPI4.iconBaseURL + itemdata.options.eventIconImage;
                                itemdata.options.color = "#0000FF";
                                itemdata.options.lineColor = "#0000FF";

                                items_to_update_data.push(itemdata);
                            }
                        }

                    }//has places
                    else{
                        //for vis timline only
                        if(lastRecID==item.opts.recid){
                            lastSelectedItem = item;
                        }
                    }
                });
            });
            /*
            if(items_to_update_data.length>0){
                dataset.clear();
                dataset.hide();
                dataset.loadItems(items_to_update_data);
                dataset.show();
            }     */


            if(items_to_update.length>0) {

                let tlband0 = $("#"+timelinediv_id).find("#timeline-band-0");
                let keep_height = (tlband0.length>0)?tlband0.height():0;

                //dataset.hide();

                let newitem,i,affected_datasets = [];
                for(let i=0;i<items_to_update.length;i++){
                        //items_to_update[i].clear();
                        let ds_id = items_to_update_data[i].datasetid;

                        let dataset = tmap.datasets[ ds_id ];

                        dataset.deleteItem(items_to_update[i]);

                        //dataset.items.push(items_to_update[i]);
                        newitem = dataset.loadItem(items_to_update_data[i]);

                        //art 020215
                        newitem.opts.openInfoWindow = _onItemSelection;

                        if(lastRecID==newitem.opts.recid){
                            lastSelectedItem = newitem;
                        }

                        if(affected_datasets.indexOf(ds_id)<0){
                            affected_datasets.push(ds_id);
                        }
                }
                //dataset.show();

                //hide and show the affected datasets - to apply changes
                for(let i=0;i<affected_datasets.length;i++){
                    let dataset = tmap.datasets[ affected_datasets[i] ];
                    if(dataset.visible){
                        dataset.hide();
                        dataset.show();
                    }
                }

                //_zoomTimeLineToAll(); //
                //tmap.timeline.layout();
                if(tlband0.length>0)
                    tlband0.css('height', keep_height+'px');
            }

            //item.timeline.layout();

            //select items on timeline
            if(vis_timeline){
                vis_timeline.setSelection( selection );
            }

        }

        /*let lastRecID = (selection)?selection[selection.length-1]:-1;
        // loop through all items - change openInfoWindow
        if(items_to_update.length>0 || !isreset){
            let k = 0;
            dataset.each(function(item){
                item.opts.openInfoWindow = _onItemSelection;
                //item.showPlacemark();
                if(lastRecID==item.opts.recid){
                        lastSelectedItem = item;
                }
            });
        }*/

        if(lastSelectedItem){
            _showPopupInfo.call(lastSelectedItem);
            //TimeMapItem.openInfoWindowBasic.call(lastSelectedItem);
            //lastSelectedItem.openInfoWindow();
        }
    }
    
    //
    //
    //
    function _getPlaceMarkFromItem( item, selected_placemark ){
    
            let placemark, placemark_type;
        
            if( selected_placemark ){
                placemark = selected_placemark;
            }else {
                let placemarks = (Array.isArray(item.placemark))?item.placemark:[item.placemark];
                
                for(let i=0;i<placemarks.length;i++){
                    if(placemarks[i] instanceof mxn.Marker){
                        placemark = placemarks[i];
                        placemark_type = "marker";
                        break;
                    }
                }
                if(!placemark){
                    placemark = placemarks[0];
                }
            }
            
            if(placemark instanceof mxn.Marker){
                placemark_type = "marker";
            }else{
                placemark_type = item.getType();
                //placemark_type = "object";    
            }
            /*}else{
                placemark = item.placemark;
                placemark_type = item.getType();
            }*/                
        
        
        return {placemark:placemark, placemark_type:placemark_type};
    }

    //
    //  item.opts.openInfoWindow -> _onItemSelection  -> _showSelection -> _showPopupInfo
    //
    function _showPopupInfo( selected_placemark ){

            //close others bubbles
            $( document ).bubble( "closeAll" );

            let item = this,
                html = item.getInfoHtml(),
                ds = item.dataset,
                placemark, placemark_type,
                show_bubble_on_map = false;
                
            let res = _getPlaceMarkFromItem( item,  selected_placemark );
            placemark = res.placemark;
            placemark_type = res.placemark_type;
                                                              
                                                              
            show_bubble_on_map = (placemark_type != "" && placemark.api!=null);
            let bubble_header = '<div style="width:99%;'+(show_bubble_on_map?'':'padding-right:10px;')+'">'
            let ed_html =  '';
            let popupURL = null;

            //popup can be shown
            // 1. as a result of mapdocument smarty template  (popupURL)  - expermental
            // 2. renderRecordData.php output (default)  (popupURL) - default
            // 3. item.opts.info - popupURL or html content - filled in _toTimemap as rec_Info field content 
            //              - this is main customization way for Digital Harlem
            // 4. html content is created from item.opts values
            

                let mapdocument = null;

                    
                if(mapdocument && mapdocument.popup_template){ //1. as smarty output 
                                               
                    popupURL = window.hWin.HAPI4.baseURL + '?snippet=1&h4=1&w=a&db='+window.hWin.HAPI4.database
                            +'&q=ids:'+item.opts.recid+'&template='+encodeURIComponent(mapdocument.popup_template);

            
                }else if(item.opts.info){
                    if(item.opts.info.indexOf('http://')==0 || item.opts.info.indexOf('https://')==0){
                        popupURL =  item.opts.info; //load content from url
                    }else{
                        html =  item.opts.info; //3. content already defined
                    }
                }else{
                    popupURL = window.hWin.HAPI4.baseURL + 'viewers/record/renderRecordData.php?mapPopup=1&recID='
                            +item.opts.recid+'&db='+window.hWin.HAPI4.database;
                            
                  if(selection.length>1){
                        popupURL = popupURL + '&ids=' + selection.join(',');    
                  }         
                    
                    //html =  bubble_header + item.opts.info + '</div>'; //predefined content
                }

                let marker = null;

            // scroll timeline if necessary
            if (!item.onVisibleTimeline()) {    //old
                ds.timemap.scrollToDate(item.getStart());
            }
            if(vis_timeline && item.opts.start){
                let stime = _getUnixTs(item.opts.recid, 'start');
                let range = vis_timeline.getWindow();
                if(stime<range.start || stime>range.end){
                        vis_timeline.moveTo(stime);
                }
                let ele = $("#"+timelinediv_id);
                marker = ele.find("[data-id="+ds.id+'-'+item.opts.recid +"]");
                //horizontal scroll
                if(marker && marker.position()) ele.scrollTop( marker.position().top );
            }

            // open window on MAP
            if (show_bubble_on_map)
            {

                if (placemark_type == "marker") {

                    if(popupURL){
                        $.get(popupURL, function(responseTxt, statusTxt, xhr){
                           if(statusTxt == "success"){
                                placemark.setInfoBubble(bubble_header+responseTxt);    //+'</div>'
                                placemark.openBubble();
                           }
                        });

                    }else{
                        placemark.setInfoBubble(html);
                        placemark.openBubble();
                    }
                    // deselect when window is closed
                    item.closeHandler = placemark.closeInfoBubble.addHandler(function() {
                        // deselect
                        ds.timemap.setSelected(undefined);
                        // kill self
                        placemark.closeInfoBubble.removeHandler(item.closeHandler);
                    });
                } else {
                    
                    if(popupURL){
                        $.get(popupURL, function(responseTxt, statusTxt, xhr){
                           if(statusTxt == "success"){
                                item.map.openBubble(item.getInfoPoint(), bubble_header+responseTxt);//+'</div>'
                                item.map.tmBubbleItem = item;
                           }
                        });

                    }else{
                        item.map.openBubble(item.getInfoPoint(), html);
                        item.map.tmBubbleItem = item;
                    }
                }
                

            } else {
                // open window on TIMELINE - replacement native Timeline bubble with our own implementation
                if(vis_timeline && marker){
                    
                    if(popupURL){
                        $( document ).bubble( "option", "content", popupURL );
                        /*
                        $.get(popupURL, function(responseTxt, statusTxt, xhr){
                           if(statusTxt == "success"){
                               $( document ).bubble( "option", "content", html );
                               //$( marker ).bubble('open');
                               //that.openBubbleOnTimeline( marker, html );
                           }
                        });
                        */

                    }else{
                        $( document ).bubble( "option", "content", html );
                    }

                    //marker.scrollIntoView();
                    //setTimeout(function(){ $( marker ).trigger('click');}, 500);

                }else if(item.event){    //reference to Simile timeline event   - NOT USED

                    let painter = item.timeline.getBand(0).getEventPainter();
                    let marker = painter._eventIdToElmt[item.event.getID()]; //find marker div by internal ID

                    //@todo - need to horizonatal scroll to show marker in viewport



                    //change content
                    $( document ).bubble( "option", "content", html );

                    /*$( document ).bubble({
                            items: ".timeline-event-icon",
                            content:html
                    });*/
                    //show
                    $( marker ).trigger('click');

                }else{
                    //neither map nor time data
                    //show on map center
                    // item.map.openBubble(item.getInfoPoint(), html);
                    // item.map.tmBubbleItem = item;
                }
            }

            if(window.hWin.HAPI4.currentUser.ugr_ID>0){
                $("#btnEditRecordFromBubble")
                    .button({icon:"ui-icon-pencil",showLabel:false})
                     .on('click', function( event ) {
                event.preventDefault();
                //@todo replce with new method => window.hWin.HEURIST4.ui.openRecordInPopup(recID, null, true, null)
                window.open(window.hWin.HAPI4.baseURL + "?fmt=edit&db="+window.hWin.HAPI4.database+"&recID="+item.opts.recid, "_new");
                    });
            }

    }
    
    /**
    *  Keeps timeline zoom, adjust timeline and MAP legend position
    */
    function _onWinResize(){

        if(tmap && keepMinDate && keepMinDate && tmap.timeline){
            keepMinMaxDate = false;
            setTimeout(function (){

                let band = tmap.timeline.getBand(0);
                band.setMinVisibleDate(keepMinDate);
                band.setMaxVisibleDate(keepMaxDate);
                tmap.timeline.layout();

                //fix bug with height of band
                //function not found _timeLineFixHeightBug();

                keepMinMaxDate = true;
                
                _adjustLegendHeight(); //global in map.php
                
                }, 1000);
        }
    }

    function _printMap() {

          if(!tmap) return;

          tmap.getNativeMap().setOptions({
                panControl: false,
                zoomControl: false,
                mapTypeControl: false,
                scaleControl: false,
                overviewMapControl: false,
                rotateControl: false
          });

          let popUpAndPrint = function() {
            let dataUrl = [];

            $('#map canvas').filter(function() {
                dataUrl.push(this.toDataURL("image/png"));
            })

            let container = document.getElementById('map'); //map-canvas
            let clone = $(container).clone();

            let width = container.clientWidth
            let height = container.clientHeight

            $(clone).find('canvas').each(function(i, item) {
              $(item).replaceWith(
                $('<img>')
                  .attr('src', dataUrl[i]))
                  .css('position', 'absolute')
                  .css('left', '0')
                  .css('top', '0')
                  .css('width', width + 'px')
                  .css('height', height + 'px');
            });

            let printWindow = window.open('', 'PrintMap',
              'width=' + width + ',height=' + height);
            printWindow.document.writeln($(clone).html());
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close();

            tmap.getNativeMap().setOptions({
                panControl: true,
                zoomControl: true,
                mapTypeControl: true,
                scaleControl: true,
                overviewMapControl: true,
                rotateControl: true
            });
          };

          setTimeout(popUpAndPrint, 500);
    }


    //public members
    let that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        load: function(_mapdata, _selection, _startup_mapdocument, _onSelectEventListener, _callback){
            $('#mapping').css('cursor','progress');
            _load(_mapdata, _selection, _startup_mapdocument, _onSelectEventListener, _callback);
        },

        getDataset: function ( dataset_id ){
            return _getDataset( dataset_id );
        },

        changeDatasetColor: function ( dataset_id, new_color, updateOnMap ){
            _changeDatasetColor( dataset_id, new_color, updateOnMap )
        },

        // _mapdata - recordset converted to timemap dataset
        addDataset: function(_mapdata){
            return _addDataset(_mapdata);
        },

        deleteDataset: function(dataset_name){
            _deleteDataset(dataset_name);
        },

        showDataset: function(dataset_name, is_show){
            _showDataset(dataset_name, is_show);
        },
        
        zoomDataset: function(dataset_name){
            _zoomDataset(dataset_name);
        },

        showSelection: function(_selection){
             selection = _selection || [];
             _showSelection( true );
        },

        onWinResize: function(){
            if(tmap && tmap.map){ //fix google map bug to force redraw on previously hidden area
                    let ele = $("#mapping").find('.ui-layout-center');
                    tmap.map.resizeTo(ele.width(),ele.height());
            }
            _onWinResize(); //adjust timeline and legend position
        },

        printMap: function(){
             _printMap();
        },

        setTimelineMinheight: function(){
            if(vis_timeline){
                  vis_timeline.setOptions( {minHeight: $("#"+timelinediv_id).height()} );
            }
        },
        
        timelineZoomToRange: function(tmin, tmax){
            _timelineZoomToRange({min:tmin, max:tmax, nofit:true});
        },

        getNativeMap: function(){
             return (tmap)?tmap.getNativeMap():null;
        },

        setTimeMapProperty: function(name, value){
            if(tmap){
                tmap.opts[name] = value;
            }
        },
        
        options: function(key, value){
            if(typeof value==="undefined"){
                return options[key];
            }else{
                options[key] = value;
            }
        },
        
        getMapContainerDiv: function(){
            return $('#'+mapdiv_id);
        },

        autoCenterAndZoom: function(){
            if(tmap){
                tmap.map.autoCenterAndZoom();
            }
        },
        
        //@todo - separate this functionality to different classes
        map_control: null,    //controls layers on map - add/edit/remove
        map_selection: null,  //@todo working with selection - search within selected area, highlight and popup info
        map_timeline: null,   //@todo vis timeline functionality
    }

    _init(_mapdiv_id, _timeline, _options, _mylayout);
    return that;  //returns object
}


let recordPopupFrame = null;
//
// function to open link from record viewer
//
function link_open(link) {

    let target = !link.getAttribute("target") ? '_popup' : link.getAttribute("target"); // without a defined target, by default attempt to open in a popup
    link.href = link.href+'&reloadPopup=1';

    if(target !== '_popup'){
        return true;
    }

    try{
        if(recordPopupFrame && recordPopupFrame.is(':visible')){
            recordPopupFrame.attr('src', link.href);
            return false;
        }
    }catch(e){
    }
 
    try{
        if (top.HEURIST  &&  top.HEURIST.util  &&  top.HEURIST.util.popupURL) {
        
            recordPopupFrame = top.HEURIST.util.popupURL(top, link.href, { title:'.', width: 600, height: 500, modal:false });
            return false;
        }
        else return true;
    }catch(e){
    }
}
function sane_link_opener(link) {
    if (window.frameElement  &&  window.frameElement.name == 'viewer') {
        top.location.href = link.href;
        return false;
    }
}

// this function is required for printing local time form map popup info
function printLTime(sdate, ele){
    let date = new Date(sdate+"+00:00");
    ele = document.getElementById(ele)
    ele.innerHTML = (''+date.getHours()).padStart(2, "0")
                        +':'+(''+date.getMinutes()).padStart(2, "0")
                        +':'+(''+date.getSeconds()).padStart(2, "0");
}