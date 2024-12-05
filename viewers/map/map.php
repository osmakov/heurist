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

<?php
if(isLocalHost()){
?>
    <link rel="stylesheet" href="<?php echo PDIR;?>external/leaflet/leaflet.css"/>
    <script type="text/javascript" src="<?php echo PDIR;?>external/leaflet/leaflet.js"></script>
<?php
}else{
?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<?php
}
?>
<script type="text/javascript"  src="https://cdnjs.cloudflare.com/ajax/libs/dom-to-image/2.6.0/dom-to-image.js"></script>

<!-- leaflet plugins -->
<script type="text/javascript" src="<?php echo PDIR;?>external/leaflet.plugins/leaflet-tileLayerPixelFilter.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/leaflet.plugins/geocoder/Control.Geocoder.js"></script>
<link rel="stylesheet" href="<?php echo PDIR;?>external/leaflet.plugins/geocoder/Control.Geocoder.css" />
<script type="text/javascript" src="<?php echo PDIR;?>external/leaflet.plugins/leaflet-tileLayerPixelFilter.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/leaflet.plugins/leaflet-providers.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/leaflet.plugins/bookmarks/Leaflet.Bookmarks.min.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/leaflet.plugins/draw/leaflet.draw-src.js"></script>
<link type="text/css" rel="stylesheet" href="<?php echo PDIR;?>external/leaflet.plugins/bookmarks/leaflet.bookmarks.css">
<script type="text/javascript"  src="<?php echo PDIR;?>external/leaflet.plugins/leaflet.browser.print.min.js"></script>
<link type="text/css" rel="stylesheet" href="<?php echo PDIR;?>external/leaflet.plugins/draw/leaflet.draw.css">
<link type="text/css" rel="stylesheet" href="<?php echo PDIR;?>external/leaflet.plugins/markercluster/MarkerCluster.css">
<link type="text/css" rel="stylesheet" href="<?php echo PDIR;?>external/leaflet.plugins/markercluster/MarkerCluster.Default.css">
<script type="text/javascript" src="<?php echo PDIR;?>external/leaflet.plugins/markercluster/leaflet.markercluster.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/leaflet.plugins/wise-leaflet-pip.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/leaflet.plugins/leaflet.circle.topolygon-src.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/leaflet.plugins/leaflet-iiif.js"></script>

<!--  geotiff support plugin is more than 1MB - it is loaded on demand
<script src="https://unpkg.com/proj4"></script>
<script src="https://unpkg.com/georaster"></script>
<script src="https://unpkg.com/georaster-layer-for-leaflet"></script>
-->
 
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/temporalObjectLibrary.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/accessTokens.php"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapping.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/timeline.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapManager.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapPublish.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapDocument.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapLayer2.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/js/cheapRuler.js" charset="utf-8"></script>

<!-- this scripts can be omitted for publishing -->
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing2.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_exts.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageEntity.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchEntity.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecords.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecords.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultList.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultListMenu.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_color.js"></script>


<!--
// WARNING: CHANGES MADE TO vis.js
// These changes are not in our repository
// line:285 remove margin for item's label
// line:345,15758, 15865, 16113 correct orientation for bottom order
// line: 13612 getDataRangeHeurist function
// line: 13598 catch exception of datetime convertation
//
-->

<?php
if (!(@$_REQUEST['notimeline']=='true' || @$_REQUEST['notimeline']=='1')) { ?>
<script type="text/javascript" src="<?php echo PDIR;?>external/vis/dist/vis.js"></script>
<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/vis/dist/vis.css" />
<?php } ?>
<style>
    body{
        margin:0px;
    }

    .leaflet-div-icon {
        background: none;
        border: none;
    }
    .leaflet-editing-icon{
        background: #fff;
        border: 1px solid #666;
    }
    .leaflet-attribution-flag{
        visibility:hidden;
        display:none !important;
    }

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
    /* was rgb(142, 169, 185) */
    .vis-item-bbox, .vis-item-bbox-start, .vis-item-bbox-end{
        background-color: #5e7fe4;
    }

    #term_tree{
         background:none;
         border:none;
    }

    div.svs-contextmenu3{
        right:7px;
        display:none;
        background:#95A7B7;
        color:black;
    }

    /* 'Add layer' button */
    div.svs-contextmenu3 .ui-map-layer{
        transform: scale(0.9);
    }

    .leaflet-control-browser-print{
        width: 22px !important;
        height: 22px !important;
    }

    .leaflet-browser-print{
        width: 22px !important;
        height: 22px !important;
        line-height: 22px !important;
        background-position: 3px !important;
    }

    .leaflet-zoom-anim
    .leaflet-zoom-animated {
            transition-timing-function: linear;
            transition-duration: 100ms;
    }
    /* print options popup: Landscape, Portrait, Auto */
    .v1 .browser-print-mode{
        padding: 3px 10px;
    }

    span.ui-selectmenu-text{
        background-color: transparent !important;
    }

    .activated-mapdoc{
        background-color: #4477B9 !important;
        color: white;
    }

    /*
    .leaflet-layer {
        filter: sepia(100%);
        filter: invert(100%); grayscale(100%) sepia(100%) hue-rotate(180deg)
    }
    */

</style>

<!-- map print layout with custom title -->
<style leaflet-browser-print-content>
    .grid-print-container {
        grid-template: auto 1fr auto / 1fr;
        background-color: none;
    }
    .grid-map-print {
        grid-row: 2;
        /*scale: 75%;
        border: 2px solid red;
        */
        height: 75% !important;
        justify-self: center;
    }
    .grid-map-print-title {
        grid-row: 1;
        justify-self: center;
        color: black;
    }
    .sub-content {
        grid-row: 5;
        padding-left: 10px;
    }
</style>

<!-- Initializing -->

<script type="text/javascript">

    var mapping, menu_datasets, btn_datasets;

    // Callback function on page initialization - see initPage.php
    function onPageInit(success){

        if(!success) {return;}

        //since 2022-06-09 layout_params as passed via option on map init

        function __gp(name){
            return window.hWin.HEURIST4.util.getUrlParameter(name, location.search);
        }

        var layout_params = null;

        //take layout parameters from url
        // params:
        //   nomap, notimeline
        //   controls: [all,none,zoom,bookmark,geocoder,print,publish,legend]
        //   legend: [basemaps,search,mapdocs|onedoc]
        //   basemap: name of initial basemap
        //   extent: fixed extent
        layout_params = {};
        layout_params['ui_main'] = __gp('ui_main');//separate toolbar for map controls
        layout_params['nomap'] = __gp('nomap');
        layout_params['notimeline'] = __gp('notimeline');
        layout_params['nocluster'] = __gp('nocluster');
        layout_params['editstyle'] = __gp('editstyle');
        layout_params['smooth_zoom'] = __gp('smooth_zoom');
        layout_params['zoom_delta'] = __gp('zoom_delta');
        layout_params['zoom_to_selected'] = __gp('zoom_to_selected');
        layout_params['mapdocuments'] = __gp('mapdocuments');//visible mapdocuments
        layout_params['basemaps'] = __gp('basemaps');//visible basemaps
        layout_params['basemap'] = __gp('basemap');//name of basemap
        layout_params['basemap_filter'] = __gp('basemap_filter');//name of basemap
        layout_params['extent'] = __gp('extent');//@todo

        layout_params['controls'] = __gp('controls');//comma separated list of visible controls
        layout_params['legend'] = __gp('legend');//legend configuration: csv basemaps,mapdocs,search,off,width
        layout_params['template'] = __gp('template');//smarty template for popup info

        layout_params['popup_behaviour'] = __gp('popup_behaviour');// fixed size, fixed width, scale to content
        layout_params['popup_width'] = __gp('popup_width');// = width, for scale = max-width
        layout_params['popup_height'] = __gp('popup_height');// = height, for scale = max-height
        layout_params['popup_resizing'] = __gp('popup_resizing');// whether to enable resizing, currently turned off

        layout_params['published'] = __gp('published');
        layout_params['popup'] = __gp('popup');
        layout_params['map_rollover'] = __gp('map_rollover');
        layout_params['ignore_mapdoc_zoom'] = __gp('ignore_mapdoc_zoom');
        layout_params['maxzoom'] = __gp('maxzoom');
        layout_params['minzoom'] = __gp('minzoom');
        layout_params['pntzoom'] = __gp('pntzoom');
        layout_params['style'] = window.hWin.HEURIST4.util.isJSON(__gp('style'));//default style
        layout_params['selection_style'] = window.hWin.HEURIST4.util.isJSON(__gp('selection_style'));

        mapping = $('#mapping').mapping({
            element_layout: '#mapping',
            element_map: '#map',
            layout_params:layout_params,
            oninit: onMapInit,
            drawMode: layout_params['ui_main']?'full':''
        });
    }

    //
    // init map data based on url parameters
    //
    function onMapInit( mapwdiget ){

        //take url parameters and open mapdocument or/and perform query
            //take from frame
            var mapdocument = window.hWin.HEURIST4.util.getUrlParameter('mapdocument', location.search);
            //take from top most
            if( window.hWin.HEURIST4.util.isempty(mapdocument) ){
                mapdocument = window.hWin.HEURIST4.util.getUrlParameter('mapdocument', window.hWin.location.search);
                if(!(mapdocument>0)){
                    mapdocument = null;
                }
            }

            var with_mapdoc = !window.hWin.HEURIST4.util.isempty(mapdocument);

            if( with_mapdoc ){
                mapwdiget.mapping('getMapManager').toggleMapDocument( mapdocument );//load map document
            }

            var request = window.hWin.HEURIST4.query.parseHeuristQuery(location.search );
            if( !window.hWin.HEURIST4.util.isempty(request['q']) ){
                //do not zoom to current search if mapdoc is defined - preserve viewport
                mapwdiget.mapping('addSearchResult', request, 'Current results', with_mapdoc);
            }

            var app_timemap_widget = window.hWin.HEURIST4.util.getUrlParameter('widget', location.search);
            if(app_timemap_widget && window.hWin && window.hWin.HAPI4){
                window.hWin.HAPI4.LayoutMgr.executeWidgetMethod(app_timemap_widget,'app_timemap','onMapInit');
            }

    }

    //
    // Add group headers to record viewer
    //
    function createRecordGroups(groups){

        var $group_container = $('div#div_public_data');
        var $data = $group_container.find('div[data-order]');

        var $g_ele = null, $g_header = null;
        var current_type = null;

        if(groups == null || $data.length < 0 || $group_container.length < 0){
            return;
        }else{
            $.each(groups, function(idx, group){

                var group_name = group[0];
                var order = group[1];

                var next_group = groups[Number(idx)+1];
                var key = (next_group == null) ? null : next_group[0];
                var next_order = (key == null) ? null : next_group[1];
                var $field_container = $('<fieldset>').attr('id', order);

                $.each($data, function(idx, detail){

                    var $detail = $(detail);
                    var detail_order = $detail.attr('data-order');
                    if(detail_order < order){ // detail belongs in previous group
                        return;
                    }else if(detail_order > order && (next_order == null || order < next_order)){
                        $detail.appendTo($field_container);
                    }else{ // detail belongs in next group
                        return false;
                    }
                });

                if(group_name != '-'){
                    $('<h4>').attr('data-order', order).css({'margin': '5px 0px 2px', 'font-size': '1.1em'}).text(group_name).appendTo($group_container);
                }else{
                    $('<hr>').attr('data-order', order).css({'margin': '5px 0px 5px', 'border-top': '1px solid black'}).appendTo($group_container);
                }

                $field_container.appendTo($group_container);
            });
        }
    }
</script>

</head>

<!-- HTML -->

<body>
    <div id="mapping" style="min-height:1px;height:100%; width:100%;cursor:progress;position:absolute;">
        <!-- Map -->
        <div class="ui-layout-center">
                <div id="map" style="width:100%; height:100%" class="ui-layout-content"><span id="map-loading">Mapping...</span></div>
                <div id="map_empty_message" style="margin:7px;display: none;position: absolute;left: 50px;top: 0px;z-index: 1000;">There are no spatial objects to plot on map</div>

        </div>

        <!-- Toolbar -->
        <div class="ui-layout-north ui-heurist-explore" id="mapToolbarDiv" style="display: block; height: 30px; z-index:999;">
            <div id="mapToolbarContentDiv">

                <span style="font-size: small;cursor: pointer;">
                    Map
                    <select id="mapDocumentSel"></select>
                </span>

                <a id="btn_add_mapdoc" style="display:inline-block; width:10px; height:15px; padding:0px;"</a>

                <button id="btn_layout_map" class="ui-heurist-button" style="height: 24px;display:inline-block;margin-left: 20px;">Map</button>
                <button id="btn_layout_timeline" class="ui-heurist-button" style="height: 24px;display:inline-block;">Timeline</button>

                <a id="btn_legend_map" class="toggle-legend ui-icon ui-icon-list"
                        style="width: 22px; height: 22px;padding:0px;display:inline-block;margin-left: 5px;"></a>
                <a class="toggle-legend">Legend</a>

                <a class="ui-icon ui-icon-plus" style="width: 22px; height: 22px;padding:0px;display:inline-block;margin-left: 20px;"></a>
                <a class="ui-icon ui-icon-minus" style="width: 22px; height: 22px;padding:0px;display:inline-block;"></a>

                <a class="ui-icon ui-icon-bookmark" style="width: 22px; height: 22px;padding:0px;display:inline-block;margin-left: 20px;"></a>
                <a class="ui-icon ui-icon-search" style="width: 22px; height: 22px;padding:0px;display:inline-block;"></a>
                <a id="btn_digitizing" class="ui-icon ui-icon-fullscreen-off" style=" min-width:0px;width: 22px; height: 22px;padding:0px;display:inline-block;"></a>


                <a class="ui-icon ui-icon-print" style="width: 22px; height: 22px;padding:0px;display:inline-block;margin-left: 20px;"></a>
                <a class="ui-icon ui-icon-globe" style="width: 22px; height: 22px;padding:0px;display:inline-block;"></a>
                <a class="ui-icon ui-icon-help" style="width: 22px; height: 22px;padding:0px;display:inline-block;"></a>


            </div>
        </div>

        <!-- Timeline -->
        <div class="ui-layout-south">
            <div id="timeline" style="width:100%;height:100%;overflow-y:auto;"></div>
            <div id="timeline_toolbar" style="position:absolute;top:1;left:1;height:20px;"></div>
        </div>
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

    <!-- Heurist map printing out -->
    <div class="grid-map-print-title" style="display:none;" leaflet-browser-print-content><h3></h3></div>
</body>
</html>
