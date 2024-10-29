<?php

/**
* Mapping page. It can be loaded in app_timemap widget or launched as standalone page
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

//&callback=initMap" async defer  for gmap
//<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?libraries=drawing"></script>
//
define('PDIR','../../');//need for proper path to js and css
require_once dirname(__FILE__).'/../../hclient/framecontent/initPage.php';

$system->defineConstants();
?>
<script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/jquery.layout.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/evol.colorpicker.js" charset="utf-8"></script>
<link type="text/css" href="<?php echo PDIR;?>external/jquery.widgets/evol.colorpicker.css" rel="stylesheet"/>

<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo $accessToken_GoogleAPI;?>&libraries=drawing,geometry,marker&callback=initMap" async></script>
<!--
<script src="https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/markerclusterer.js"></script>

<script>
  (g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.${c}apis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({
    key: "<?php echo $accessToken_GoogleAPI;?>",
    // Add other bootstrap parameters as needed, using camel case.
    // Use the 'v' parameter to indicate the version to load (alpha, beta, weekly, etc.)
  });
</script>

-->

<!-- Timemap -->
<script type="text/javascript" src="<?php echo PDIR;?>external/timemap.js/2.0.1/lib/mxn/mxn.js?(googlev3)"></script>

<script type="text/javascript" src="<?php echo PDIR;?>external/timemap.js/2.0.1/markerclusterer.js"></script>

<!-- script type="text/javascript" src="<?php echo PDIR;?>external/timemap.js/2.0.1/lib/timeline-2.3.0.js"></script -->

<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/temporalObjectLibrary.js"></script>
<!-- timeline -->

<!--
// WARNING: CHANGES MADE TO vis.js
// These changes are not in our repository
// line:285 remove margin for item's label
// line:345,15753, 15860, 16108 correct orientation for bottom order
// line: 13607 getDataRangeHeurist function
// line: 13594 catch exception of datetime convertation
//
-->
<script type="text/javascript" src="<?php echo PDIR;?>external/vis/dist/vis.js"></script>
<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/vis/dist/vis.css" />

<script type="text/javascript" src="<?php echo PDIR;?>external/timemap.js/2.0.1/src/timemap2.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/timemap.js/2.0.1/src/param.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/timemap.js/2.0.1/src/loaders/xml.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/timemap.js/2.0.1/src/loaders/kml.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/timemap.js/2.0.1/src/manipulation2.js"></script>

<!-- Shape file converting -->
<script type="text/javascript" src="<?php echo PDIR;?>external/js/shapefile/stream.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/js/shapefile/shapefile.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/js/shapefile/dbf.js"></script>

<!-- Mapping -->
<script type="text/javascript" src="<?php echo PDIR;?>viewers/gmap/map.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/gmap/mapOverlay.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/gmap/mapBubble.js"></script>


<style>
    .ui-map-document { background-image: url('<?php echo PDIR;?>hclient/assets/mapdocument.png') !important;}
    .ui-map-layer { background-image: url('<?php echo PDIR;?>hclient/assets/maplayer.png') !important;}

    /*
    .vis-item-overflow{
    position:absolute;
    }
    .vis-item-overflow .vis-item{
    left:0px;
    top:0px;
    height:100%;
    border:none;
    }
    .vis-item-overflow .vis-item-content{
    position:relative;
    left: 4px;
    }
    .vis-item-overflow .vis-selected{
    z-index:2;
    background-color:#bee4f8 !important;
    border:none;
    }
    .vis-item.vis-selected{
    background-color:#bee4f8 !important;
    }
    */

    .vis-panel.vis-left, .vis-time-axis.vis-foreground{
        background-color:#DDDDDD;
    }
    .vis-item-content{
        width: 10em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .vis-item-bbox{
        background-color:rgb(142, 169, 185);
    }
</style>

<!-- Initializing -->

<script type="text/javascript">

    var mapping, menu_datasets, btn_datasets,
        is_googlemap_loaded = false, is_page_loaded = false;

    function initMap(){
        is_googlemap_loaded = true;
        if(is_page_loaded) onPageInit(true);
    }

    // Callback function on page initialization - see initPage.php
    function onPageInit(success){

        is_page_loaded = success;
        if(!success || !is_googlemap_loaded) {return;}

        // Layout options
        var layout_opts =  {
                    applyDemoStyles: false,
            togglerContent_open:    '<div class="ui-icon"></div>',
            togglerContent_closed:  '<div class="ui-icon"></div>',
            onresize_end: function(){
                //global
                if(mapping) mapping.onWinResize();
            }

        };

        // Setting layout
        layout_opts.center__minHeight = 30;
        layout_opts.center__minWidth = 200;
        layout_opts.north__size = 30;
        layout_opts.north__spacing_open = 0;
        var th = Math.floor($('#mapping').height*0.2);
        layout_opts.south__size = th>200?200:th;
        layout_opts.south__spacing_open = 7;
        layout_opts.south__spacing_closed = 12;
        layout_opts.south__onresize_end = function() {
            if(mapping) mapping.setTimelineMinheight();
            _adjustLegendHeight();
        };

        var _options = {};

        if(window.hWin.HEURIST4.util.getUrlParameter('notimeline', location.search)){
            layout_opts.south__size = 0;
            layout_opts.south__spacing_open = 0;
            layout_opts.south__spacing_closed = 0;
        }else if(window.hWin.HEURIST4.util.getUrlParameter('nomap', location.search)){
            _options['mapVisible'] = false;
            layout_opts.center__minHeight = 0;
            layout_opts.south__spacing_open = 0;
        }
        if(window.hWin.HEURIST4.util.getUrlParameter('noheader', location.search) ||
           window.hWin.HEURIST4.util.getUrlParameter('header', location.search)=='off'){
            layout_opts.north__size = 0;
        }
        if(window.hWin.HEURIST4.util.getUrlParameter('legend', location.search)=='off'){
            _options['legendVisible'] = false;
        }

        var mylayout = $('#mapping').layout(layout_opts);

        $('#map').css('padding',0);
        //$('#map').attr('data-mapid', 'code provided by google');//google mapid

        // Mapping data
        var mapdata = [];
        mapping = new hMapping("map", "timeline", _options, mylayout);

        //take from frame
        var mapdocument = parseInt(window.hWin.HEURIST4.util.getUrlParameter('mapdocument', location.search));
        //take from top most
        if(!(mapdocument>0)){
            mapdocument = parseInt(window.hWin.HEURIST4.util.getUrlParameter('mapdocument', window.hWin.location.search));
            if(!(mapdocument>0)){
                mapdocument = null;
            }
        }


        var noinit = window.hWin.HEURIST4.util.getUrlParameter('noinit', location.search);
        //init performed in app_timemap
        if(noinit!='1'){
            //this case only for initialization of mapping when it is loaded independently
            //otherwise initialization is performed in app_timemap.js

            var q = window.hWin.HEURIST4.util.getUrlParameter('q', location.search);

            //t:26 f:85:3313  f:1:building
            // Perform database query if possible (for standalone mode - when map.php is separate page)
            if( !window.hWin.HEURIST4.util.isempty(q) )
            {
                var rules = window.hWin.HEURIST4.util.getUrlParameter('rules', location.search);

                if(!window.hWin.HEURIST4.util.isempty(rules)){
                    try{
                        rules = JSON.parse(rules);
                    }catch(ex){
                        rules = null;
                    }
                }else{
                    rules = null;
                }

                window.hWin.HAPI4.RecordMgr.search({q: q, rules:rules, w: "a", detail:(rules?'detail':'timemap'), l:3000},
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){

                            // Show info on map    @todo reimplement as map init callback IMPORTANT!!!!
                            var recset = new HRecordSet(response.data);

                            mapping.load( null, //mapdataset,
                                null,  //array of record ids
                                mapdocument,    //map document on load
                                function(selected){  //callback if something selected on map
                                },
                                function(){ //callback function on native map init completion
                                    var params = {id:'main', recordset:recset, title:'Current results' };
                                    mapping.map_control.addRecordsetLayer(params);
                                }
                            );


                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    }
                );
            } else {

                if(mapdocument>0){
                    mapping.load(null, null, mapdocument);//load with map document
                }else{
                    mapping.load();//load empty map
                }
            }

        }//noinit!='1'

        //init popup for timeline  ART14072015
        $( document ).bubble({
            items: ".timeline-event-icon"
        });
        $( document ).bubble({
            items: ".vis-item,.vis-item-overflow"
        });

        $( window ).on('onresize',function() {
            var w = $(this).width();
            if (w < 400) {
                $("#mapSelectorBtn").button({showLabel:false}).width(20);
            }else{
                $("#mapSelectorBtn").button({showLabel:true}).width(w-360);
            }

            mapping.onWinResize();
        });

        $( "#timeline" ).on('mousedown', function(){
            $( document ).bubble("closeAll");
        });

        //init buttons
        $("#mapToolbarDiv").css('background','none');

        $("#btnExportKML").button().on('click',exportKML);

        $("#btnPrint").button({showLabel:false, icon: "ui-icon-print"})
        .on('click',mapping.printMap);

        $("#btnEmbed").button({showLabel:false, icon: "ui-icon-globe-b"})
        .on('click',showEmbedDialog);

        $('#btn_help').button({icon: "ui-icon-help", showLabel:false}).on('click', 3, function(){
            var $helper = $("#helper");
            if($helper.dialog( "isOpen" )){
                $helper.dialog( "close" );
            }else{
                $helper.dialog( "open" );
            }
        });
        $( "#helper" ).load(window.hWin.HRes('mapping_overview. #content'));
        //$( "#helper" ).find('p').css('padding','10px');
        $( "#helper" ).dialog({
            autoOpen: false, //(window.hWin.HAPI4.get_prefs('help_on')=='1'),
            width:'90%', height:570,
            position: { my: "right top", at: "right bottom", of: $('#btn_help') },
            show: {
                effect: "slide",
                direction : 'right',
                duration: 1000
            },
            hide: {
                effect: "slide",
                direction : 'right',
                duration: 1000
            }
        });

        <?php
            function checkRt($rt){
                global $rtDefines;
                if(defined($rt) && constant($rt)>0){
                    return constant($rt);
                }else{
                    if(@$rtDefines[$rt]){

                        switch ($rt) {
                            case "RT_TILED_IMAGE_SOURCE":
                                $msg = "Map image file (tiled)";
                                break;
                            case "RT_KML_SOURCE":
                                $msg = "KML";
                                break;
                            case "RT_SHP_SOURCE":
                                $msg = "Shapefile";
                                break;
                            case "RT_GEOTIFF_SOURCE":
                                $msg = "Map image file (non-tiled)";
                                break;
                            case "RT_QUERY_SOURCE":
                                $msg = "Mappable query";
                                break;
                            case "RT_MAP_DOCUMENT":
                                $msg = "Map Document";
                                break;
                            case "RT_MAP_LAYER":
                            case "RT_TLCMAP_DATASET":
                                $msg = "Heurist Map Layer";
                                break;
                            default;
                        }

                        return "(".$msg.", Concept ".$rtDefines[$rt][0]."-".$rtDefines[$rt][1].")";
                    }else{
                        return "";
                    }
                }
            }


            $pref_li = '<li rtid="';

            $items = '';
            $items = $items.$pref_li.checkRt('RT_KML_SOURCE').'"><a href="#">KML</a></li>';
            $items = $items.$pref_li.checkRt('RT_SHP_SOURCE').'"><a href="#">SHP</a></li>';
            $items = $items.$pref_li.checkRt('RT_GEOTIFF_SOURCE').'"><a href="#">GeoTiff</a></li>';
            $items = $items.$pref_li.checkRt('RT_TILED_IMAGE_SOURCE').'"><a href="#">Tiled image</a></li>';
            $items = $items.$pref_li.checkRt('RT_QUERY_SOURCE').'"><a href="#">Query layer</a></li>';
        ?>
            $("#btnMapRefresh").button({ showLabel:false, icon:"ui-icon-arrowrefresh-1-e" })
            .on('click', refreshMapDocument );
            $("#btnMapNew").button({ showLabel:false, icon: "ui-map-document" })
            .on('click', function(){ addNewRecord('<?=checkRt('RT_MAP_DOCUMENT')?>');} )
            .append('<span class="ui-icon ui-icon-plus" style="margin-left:0px;margin-top:-2px" />');
            $("#btnMapEdit").button({showLabel:false, icon: "ui-icon-pencil"})
            .on('click', mapEdit );
            $("#btnMapLayer").button({showLabel:false, icon: "ui-map-layer"})
            .on('click', function(){ addNewRecord('<?=checkRt('RT_MAP_LAYER')?>');})
            .append('<span class="ui-icon ui-icon-plus" style="margin-left:0px;margin-top:-2px" />');

            menu_datasets = $('<ul><?=$items?></ul>')
            .addClass('menu-or-popup')
            .css('position','absolute')
            .appendTo( $('body') )
            .menu({
                select: function( event, ui ) {
                    var mode = ui.item.attr('rtid');
                    addNewRecord(mode);
            }})
            .hide();


            function __drodown_mapDataSources(e) {
                $('.menu-or-popup').hide();//hide other
                var $menu_layers = $( menu_datasets )
                .show()
                .position({my: "right top", at: "right bottom", of: btn_datasets });
                $( document ).one( "click", function() { $menu_layers.hide();});
                return false;
            }

            btn_datasets = $("#btnMapDataSourceArrow").button({showLabel:false, icon: 'ui-icon-triangle-1-s'})
                .css({'padding':'0.4em', 'max-width':'12px'})
                .on('click',__drodown_mapDataSources);
            $("#btnMapDataSource").button({showLabel:false,icon: "ui-icon-bars"}).on('click',__drodown_mapDataSources);


            $("#mapToolbar").controlgroup();

    }

    function _adjustLegendHeight() {

        setTimeout(function(){
            var legend = $('#map_legend');
            var ch = $("#map_legend .content").height()+30;


            var nt = parseInt(legend.css('bottom'), 10);
            nt = 30; //bottom
            var mh = $("#mapping").find('.ui-layout-center').height();

            var is_collapsed = (legend.find('#collapse').text() == "+");
            if(is_collapsed){
                ch = 14;
                legend.css('overflow-y','hidden');
            } else {
                legend.css('overflow-y','auto');
            }

           /*
            if(is_collapsed===true){
                $(legend).css('top', mh-nt-50);
            }else{

                if(mh-nt-ch < 70){
                    $(legend).css('top', 50);
                }else{
                    $(legend).css('top', mh-nt-ch);
                }
            }
            */

            legend.css('bottom', nt);

            if(mh-nt-ch<50){
                ch = mh-nt-50; //reduce height
            }
            var top = mh-nt-ch;
            legend.css('top',top).height(ch);
        },500);
    }


    function showEmbedDialog(){

        var query = window.hWin.HEURIST4.query.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, false);
        query = query + ((query=='?')?'':'&') + 'db='+window.hWin.HAPI4.database;
        if(mapping.map_control.getCurrentMapdocumentId()>0){
            query = query + '&mapdocument=' + mapping.map_control.getCurrentMapdocumentId();
        }

        var url = window.hWin.HAPI4.baseURL+'viewers/gmap/map.php' + query;

        //document.getElementById("linkTimeline").href = url;

        document.getElementById("code-textbox3").value = url;


        document.getElementById("code-textbox").value = '<iframe src=\'' + url +
        '\' width="800" height="650" frameborder="0"></iframe>';

        //document.getElementById("linkKml").href = url_kml;

        //encode
        query = window.hWin.HEURIST4.query.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, true);
        query = query + ((query=='?')?'':'&') + 'db='+window.hWin.HAPI4.database;
        url = window.hWin.HAPI4.baseURL+'viewers/gmap/map.php' + query;
        if(mapping.map_control.getCurrentMapdocumentId()>0){
            url = url + '&mapdocument=' + mapping.map_control.getCurrentMapdocumentId();
        }

        document.getElementById("code-textbox2").value = '<iframe src=\'' + url +
        '\' width="800" height="650" frameborder="0"></iframe>';

        window.hWin.HEURIST4.msg.showElementAsDialog({
            element: document.getElementById('map-embed-dialog'),
            height: 420,
            width: 700,
            title: window.hWin.HR('Publish Map')
        });

        /*
        var $dlg = $("#embed-dialog");
        $dlg.dialog({
            autoOpen: true,
            height: 320,
            width: 700,
            modal: true,
            resizable: false,
            title: window.hWin.HR('Publish Map')
        });
        */
    }

    function exportKML(){

        var query = window.hWin.HEURIST4.query.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, false);
        if(query=='?'){
            window.hWin.HEURIST4.msg.showMsgDlg("Define filter and apply to database");
        }else{
            query = query + '&a=1&depth=1&db='+window.hWin.HAPI4.database;
            var url_kml = window.hWin.HAPI4.baseURL+"export/xml/kml.php" + query;

            var win = window.open(url_kml, "_new");
        }
    }

    function refreshMapDocument(){
        var recID = $("#mapSelectorBtn").attr('mapdoc-selected');
        mapping.map_control.loadMapDocumentById(recID, true);
    }

    function mapEdit(){
        var recID = $("#mapSelectorBtn").attr('mapdoc-selected');
        if(recID>0){
            editRecord(parseInt(recID), window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT']);
        }
    }
    function editRecord(recID, rt){
            window.hWin.HEURIST4.ui.openRecordEdit(recID, null,
                {new_record_params:{rt:rt},
                 selectOnSave:true,
                 onselect:function(event, data){
                    if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                        if(rt==window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT']){
                            var recordset = data.selection;
                            var record = recordset.getFirstRecord();
                            var recID = recordset.fld(record,'rec_ID');
                            //reload mapdocument list and load added mapdoc
                            mapping.map_control.loadMapDocuments(recID);
                        }
                    }
                 }});
    }
    function addNewRecord(rt){

        if(parseInt(rt)>0){
            editRecord(-1, rt);
        }else{
            window.hWin.HEURIST4.msg.showMsgDlg(
                "The required record type "+rt+" has not been defined.<br><br>"+
                "Please download from the HeuristReferenceSet database using Manage &gt; Structure &gt; Browse templates.",
                {'Show Mapping Help':function() {  $("#helper").dialog( "open" ); var $dlg = window.hWin.HEURIST4.msg.getMsgDlg(); $dlg.dialog( "close" ) },
                    'Close':function() { var $dlg = window.hWin.HEURIST4.msg.getMsgDlg(); $dlg.dialog( "close" ) }
                }
                , "Missing record type"
            );


        }
    }

</script>

</head>

<!-- HTML -->

<body>
    <div id="mapping" style="min-height:1px;height:100%; width:100%;cursor:progress">
        <!-- Map -->
        <div class="ui-layout-center">
                <div id="map" style="width:100%; height:100%" class="ui-layout-content">Mapping</div>
                <div id="map_empty_message" style="width:100%; height:100%;margin:7px;display: none;">There are no spatial objects to plot on map</div>

        </div>

        <!-- Toolbar -->
        <div class="ui-layout-north" id="mapToolbarDiv" style="display: block !important; height: 30px; z-index:999;">

            <span id="map-settingup-message" style="padding-left:1em;line-height:2em">
                Setting up map ...
            </span>

            <!-- Map document selector -->

            <div id="mapSelector" class="map-inited" style="float:left;">
                <label id="map-doc-select-lbl" style="padding: 4px;line-height: 2.2em;"><i>Map document:</i></label>
                <button id="mapSelectorBtn" class="truncate" style="max-width:300px;text-align:left"></button>
            </div>
            <div id="mapToolbar" class="map-inited" style="float:left;display:none">
                <button id="btnMapRefresh" xxxdisabled="disabled" title="Refresh/reload current Map Document">Refresh current map</button>
                <button id="btnMapEdit" xxxdisabled="disabled" title="Edit current Map Document record (Select the desired map in the dropdown)">Edit current map</button>
                <button id="btnMapNew" title="Create new Map Document - a record that describes map features and defines what layers will be visible (will be included)">New map document</button>
                <button id="btnMapLayer" title="Create new Map Layer - a record that describes map layer behaviour (visibility, color scheme) and refers to particular geodata source">New Map Layer</button>
                <button id="btnMapDataSource" title="Define new Map geodata source. It may be either raster (Tiled image, geoTiff) or vector (shp, kml) data">New Data Source</button>
                <button id="btnMapDataSourceArrow" title="Define new Map geodata source. It may be either raster (Tiled image, geoTiff) or vector (shp, kml) data">New Data Source</button>
            </div>

            <div style="position: absolute; right: 0px; top:0px;display:none" class="map-inited">
                <button id="btnPrint">Print</button>
                <button id="btnEmbed">Embed</button>
                <button id="btn_help">Help</button>
            </div>

            <!-- TODO: Explain, use or remove -->
            <!-- Menu -->
            <!--<select id="menu" style="position: fixed; right: 10">
            <option value="-1" selected="selected" disabled="disabled">Menu</option>
            <option>Google Map/Timeline</option>
            <option>Google Earth</option>
            <option>Embed Map Code</option> style="position: fixed; right: 40"
            </select>-->

            <!-- Legend overlay -->
            <div id="map_legend"
                style="background-color: rgba(200, 200, 200, 0.7); color:black; padding:4 8;overflow-y:auto; display:none;">
                <div id="map_extents"  style="font-size: 0.9em;display:inline-block;visibility:hidden;padding-bottom:1em;">Zoom to:&nbsp;
                        <select id="selMapBookmarks" style="font-size:1.0em;"></select>
                </div>
                <div style="float:right;">
                    <span style="font-size: 1.25em">&nbsp;</span>
                    <span id="collapse" style="font-size: 1.25em; float:right; padding: 0px 5px; cursor: pointer">-</span>
                </div>
                <div class="content"></div>
            </div>
            <div id="map_limit_warning" style="border-radius:6px;background-color: rgb(172, 231, 255);font-weight:bold; color:red; padding:8px; display:none;">
            </div>
        </div>

        <!-- Timeline -->
        <div class="ui-layout-south">
            <div id="timeline" style="width:100%;height:100%;overflow-y:auto;"></div>
            <div id="timeline_toolbar" style="position:absolute;top:1;left:1;height:20px;"></div>
        </div>
    </div>
    <div id="map-embed-dialog" style="display:none">
        <p>Embed this Google Map (plus timeline) in your own web page. Enclose within &lt;code&gt; &lt;/code&gt; for Wordpress sites (the use of &lt;code&gt; may need to be enabled for your site).</p>
        <p style="padding:1em 0 1em 0;font-size:0.9em">Copy the following html code into your page where you want to place the map, or use the URL on its own. The map will be generated live from the database using the current search criteria whenever the map is loaded. Use the web-safe version if the readable version does not work</p>
        <label style="font-size:0.9em">Readable code:</label>
        <textarea id="code-textbox" onclick="select(); if (window.clipboardData) clipboardData.setData('Text', value);" style="border: 1px dotted gray; padding: 3px; margin: 2; font-family: times; width: 100%; height: 60px;" readonly=""></textarea>
        <label style="font-size:0.9em">Web-safe code:</label>
        <textarea id="code-textbox2" onclick="select(); if (window.clipboardData) clipboardData.setData('Text', value);" style="border: 1px dotted gray; padding: 3px; margin: 2; font-family: times; width: 100%; height: 40px;" readonly=""></textarea>
        <label style="font-size:0.9em">URL:</label>
        <textarea id="code-textbox3" onclick="select(); if (window.clipboardData) clipboardData.setData('Text', value);" style="border: 1px dotted gray; padding: 3px; margin: 2; font-family: times; width: 100%; height: 40px;" readonly=""></textarea>

        <p style="padding-top:1em">Note: records will only appear on the map if they include geographic objects. You may get an empty or sparsely populated map if the search results do not contain map data. Records must have public status - private records will not appear on the map, which could therefore be empty. </p>
        <p style="padding-top:1em"><button id="btnExportKML">Create KML for Google Earth</button></p>
    </div>

    <div id="helper" title="Mapping Overview">
    </div>

    <div id="layer-edit-dialog"  style="display:none" class="ui-heurist-bg-light">
        <fieldset><legend style="display:none"></legend>
            <div>
                <!-- What would you like to call<br>the new map layer -->
                <div class="header"><label for="layer_name">Name for map layer:</label></div>
                <input type="text" id="layer_name" class="text ui-widget-content ui-corner-all" />
            </div>
            <div>
                <div class="header"><label for="layer_color">Color:</label></div>
                <input id="layer_color"/>
            </div>
        </fieldset>
        <div class="messages"></div>
    </div>

    <div id="timeline-edit-dialog"  style="display:none" class="ui-heurist-bg-light">

            <div style="padding:5px">
                <label><input type="radio" name="time-label" checked value="0">Full length labels</label><br>
                <label><input type="radio" name="time-label" value="1">Truncate label to bar</label><br>
                <label><input type="radio" name="time-label" value="2">Fixed label width</label><br>
                <label><input type="radio" name="time-label" value="3">Hide labels</label>
            </div>

            <div style="padding:5px">
                <label><input type="radio" name="time-label-pos" checked value="0">Label within the bar</label><br>
                <!-- <label><input type="radio" name="time-label-pos" value="1">Label to the right of the bar</label><br> -->
                <label><input type="radio" name="time-label-pos" value="2">Label above the bar</label>
            </div>

            <div style="padding:5px">
                <label><input type="radio" name="time-label-stack" checked value="0">Bars stacked on the above the other</label><br>
                <label><input type="radio" name="time-label-stack" value="1">Bars wrapped to minimise height of timeline</label>
            </div>

            <div style="padding:5px">
                <label><input type="checkbox" name="time-filter-map" value="1">Filter map with current timeline range</label>
            </div>

    </div>

</body>
</html>
