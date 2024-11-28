/**
* visualize.js: Visualisation plugin
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

/**
* Visualisation plugin
* Requirements:
* 
* Internal Javascript:
* - settings.js
* - overlay.js
* - gephi.js
* - visualize.js
* 
* External Javascript:
* - jQuery          http://jquery.com/
* - D3              http://d3js.org/
* - D3 fisheye      https://github.com/d3/d3-plugins/tree/master/fisheye
* - Colpicker       https://github.com/evoluteur/colorpicker
*
* Objects must have at least the following properties:
* - id
* - name
* - image
* - count
* 
* Available settings and their default values:
* - linetype: "straight",
* - linelength: 100,
* - linewidth: 15,
* - linecolor: "#22a",
* - markercolor: "#000",
* 
* - entityradius: 30,
* - entitycolor: "#b5b5b5",
* 
* - labels: true,
* - fontsize: "8px",
* - textlength: 60,
* - textcolor: "#000",
* 
* - formula: "linear",
* - fisheye: false,
* 
* - gravity: "off",
* - attraction: -3000,
* 
* - translatex: 0,
* - translatey: 0,
* - scale: 1
*/
/* global svg, data, settings, zoomBehaviour, force, iconSize, currentMode, circleSize, maxLinkWidth, maxEntityRadius, truncateText, 
getSetting, putSetting, checkStoredSettings, handleSettingsInUI, addSelectionBox, 
addNodes,  updateNodes,
createOverlay, getRelationOverlayData, removeOverlay,
isStandAlone */

window.settings = null;   // Plugin settings object
window.svg = null;        // The SVG where the visualisation will be executed on

window.data = null; // Currently visualised dataset
window.zoomBehaviour = null;
window.force = null;

//public settings
window.iconSize = 16; // The icon size
window.circleSize = 12; //iconSize * 0.75; // Circle around icon size
window.currentMode = 'infoboxes_full'; //or 'icons';
window.maxEntityRadius = 40;
window.maxLinkWidth = 25;

//private
let maxCountForNodes, maxCountForLinks; 

(function ( $ ) {
    // jQuery extension
    $.fn.visualize = function( options ) {
        
        // Select and clear SVG.
        window.svg = window.d3.select("#d3svg");
        svg.selectAll("*").remove();
        svg.append("text").text("Building graph ...").attr("x", "25").attr("y", "25");   
        
        
        // Default plugin settings
        window.settings = $.extend({
            // Custom functions
            getData: $.noop(), // Needs to be overriden with custom function
            getLineLength: function() { return getSetting('setting_linelength',200); },
            
            selectedNodeIds: [],
            onRefreshData: function(){},
            triggerSelection: function(selection){}, 
            
            isDatabaseStructure: false,
            
            showCounts: true,
            
            // UI setting controls
            showLineSettings: true,
            showLineType: true,
            showLineLength: true,
            showLineWidth: true,
            showLineColor: true,
            showMarkerColor: true, 
            
            showEntitySettings: true, 
            showEntityRadius: true,
            showEntityColor: true,
            
            showTextSettings: true,
            showLabels: true,
            showFontSize: true,
            showTextLength: true,
            showTextColor: true,
            
            showTransformSettings: true,
            showFormula: true,
            showFishEye: true,
            
            showGravitySettings: true,
            showGravity: true,
            showAttraction: true,
            
            
            // UI default settings
            advanced: false,
            linetype: "straight",
            line_show_empty: true,
            linelength: 100,
            linewidth: 3,
            linecolor: "#22a",
            markercolor: "#000",
            
            entityradius: 30,
            entitycolor: "#b5b5b5",
            
            labels: true,
            fontsize: "8px",
            textlength: 25,
            textcolor: "#000",
            
            formula: "linear",
            fisheye: false,
            
            gravity: "off",
            attraction: -3000,
            
            translatex: 200,
            translatey: 200,
            scale: 1
        }, options );
 
        // Handle settings (settings.js)
        checkStoredSettings();  //restore default settings
        handleSettingsInUI();

        // Check visualisation limit
        let amount = Object.keys(settings.data.nodes).length;
        const MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');
        
        visualizeData();    

        let ele_warn = $('#net_limit_warning');
        if(amount >= MAXITEMS) {
            ele_warn.html('These results are limited to '+MAXITEMS+' records<br>(limit set in your profile Preferences)<br>Please filter to a smaller set of results').show();//.delay(2000).fadeOut(10000);
        }else{
            ele_warn.hide();
        }

        $('#btnZoomIn').button({icon:'ui-icon-plus',showLabel:false}).on('click',
            function(){
                zoomBtn(true);
            }
        );

        $('#btnZoomOut').button({icon:'ui-icon-minus',showLabel:false}).on('click',
            function(){
                zoomBtn(false);
            }
        );

        $('#btnFitToExtent').button({icon:'ui-icon-fullscreen',showLabel:false}).on('click',
            function(){
                zoomToFit();
            }
        );

        $('#btnRefreshData').button({icon:'ui-icon-refresh'}).on('click',
            function(){
                location.reload();
            }
        );
 
        return this;
    };
}( jQuery ));
    

/*******************************START OF VISUALISATION HELPER FUNCTIONS*******************************/

function determineMaxCount(data) {
    maxCountForNodes = 1;
    maxCountForLinks = 1;
    if(data && data.nodes.length > 0) {
        for(let i = 0; i < data.nodes.length; i++) {
            if(data.nodes[i].count > maxCountForNodes) {
                maxCountForNodes = data.nodes[i].count;
            } 
        }
    }
    if(data && data.links.length > 0) {
        for(let i = 0; i < data.links.length; i++) {
            if(data.links[i].targetcount > maxCountForLinks) {
                maxCountForLinks = data.links[i].targetcount;
            } 
        }
    }
}

function getNodeDataById(id){
    if(data && data.nodes.length > 0) {
        for(let i = 0; i < data.nodes.length; i++) {
            if(data.nodes[i].id==id) {
                return data.nodes[i];
            } 
        }
    }
    return null;
}

/** Calculates log base 10 */
function log10(val) {
    return Math.log(val) / Math.LN10;
}

function _addDropShadowFilter(){

// filter chain comes from:
// https://github.com/wbzyl/d3-notes/blob/master/hello-drop-shadow.html
// cpbotha added explanatory comments
// read more about SVG filter effects here: http://www.w3.org/TR/SVG/filters.html

// filters go in defs element
let defs = svg.append("defs");

// create filter with id #drop-shadow
// height=130% so that the shadow is not clipped
let filter = defs.append("filter")
    .attr("id", "drop-shadow")
    .attr("height", "120%");

// SourceAlpha refers to opacity of graphic that this filter will be applied to
// convolve that with a Gaussian with standard deviation 3 and store result
// in blur
filter.append("feGaussianBlur")
    .attr("in", "SourceAlpha")
    .attr("stdDeviation", 3)
    .attr("result", "blur");

// translate output of Gaussian blur to the right and downwards with 2px
// store result in offsetBlur
filter.append("feOffset")
    .attr("in", "blur")
    .attr("dx", 3)
    .attr("dy", 3)
    .attr("result", "offsetBlur");

// overlay original SourceGraphic over translated blurred opacity by using
// feMerge filter. Order of specifying inputs is important!
let feMerge = filter.append("feMerge");

feMerge.append("feMergeNode")
    .attr("in", "offsetBlur")
feMerge.append("feMergeNode")
    .attr("in", "SourceGraphic");
}

/** Executes the chosen formula with a chosen count & max size */

function executeFormula(count, maxCount, maxSize) {
    // Avoid minus infinity and wrong calculations etc.
    if(count <= 0) {
        count = 1;
    }
    
    let formula = getSetting('setting_formula');
    if(formula == "logarithmic") { // Log                                                           
        return maxCount>1?(Math.log(count) / Math.log(maxCount)*maxSize):1;
    }
    else if(formula == "unweighted") { // Unweighted
        return maxSize;                                          
    }else {  // Linear
        return (maxCount>0)?((count/maxCount)* maxSize):1 ; 
    }       
}

/** Returns the line length */
function getLineLength(record) {
    return getSetting('setting_linelength',200);
}

/** Calculates the line width that should be used */
function getLineWidth(count) {

    count = Number(count);
    let maxWidth = Number(getSetting('setting_linewidth', 3));
    
    let maxSize = 1;
    if(maxWidth>maxLinkWidth) {maxSize = maxLinkWidth;}
    if(maxWidth<1) {maxSize = 1;}
    
    if(count > maxCountForLinks) {
        maxCountForLinks = count;
    }
    
    let val = (count==0)?0:executeFormula(count, maxCountForLinks, maxWidth);
    if(val<1) val = 1;
    return val;
}            

/** Calculates the marker width that should be used */
function getMarkerWidth(count) {
    if(isNaN(count)) count = 0;
    return 4 + getLineWidth(count)*10;
}

/** Calculates the entity raadius that should be used */
function getEntityRadius(count) {
    
    let maxRadius = getSetting('setting_entityradius');
    if(maxRadius>maxEntityRadius) {maxRadius = maxEntityRadius;}
    else if(maxRadius<1) {maxRadius = 1;}
    
    if(getSetting('setting_formula')=='unweighted'){
        return maxRadius;
    }else{
        if(count==0){
            return 0; //no records - no circle
        }else{
            
            if(count > maxCountForNodes) {
                maxCountForNodes = count;
            }
            
            let val = circleSize + executeFormula(count, maxCountForNodes, maxRadius);
            if(val<circleSize) val = circleSize;
            return val;
        }
    }
}

/***********************************START OF VISUALISATION FUNCIONS***********************************/
/** Visualizes the data */ 


function visualizeData() {
    
    svg.selectAll("*").remove();
    addSelectionBox();

    //define shadow filter
    _addDropShadowFilter();
    
    // SVG data  
    this.data = settings.getData.call(this, settings.data);
    determineMaxCount(data);

    // Container with zoom and force
    let container = addContainer();
    svg.call(zoomBehaviour); 
    window.force = addForce();

    // Markers
    addMarkerDefinitions(); // all marker/arrow types on lines

    // Lines 
    addLines("bottom-lines", getSetting('setting_linecolor', '#000'), 1); // larger than top-line, shows connections
    addLines("top-lines", "#FFF", 1); // small line that is for displaying direction arrows
    addLines("rollover-lines", "#FFF", 3); // invisible thicker line for rollover
   
    // Nodes
    addNodes();
    //addTitles();
    
    if(settings.isDatabaseStructure){
        
        let cnt_vis = data.nodes?data.nodes.length:0;
        let cnt_tot = (settings.data && settings.data.nodes)?settings.data.nodes.length:0;
        let sText;
        if(cnt_vis==0){
            sText = 'Select record types to show';
        }else{
            sText = 'Showing '+cnt_vis+' of '+cnt_tot;
        }
            
        $('#lblShowRectypeSelector').text(sText);

    }else{
        inIframe();
    }

    if(settings.isDatabaseStructure || isStandAlone){
        $('#embed-export').css('visibility','hidden');//hide();
    }else{
        $('#embed-export').button({icon:'ui-icon-globe',showLabel:false}).on('click',
            function(){
                 showEmbedDialog();
            }
        );
    }

    tick()// update display
    
} //end visualizeData

/****************************************** CONTAINER **************************************/
/**
* Adds a <g> container to the SVG, which all other elements will get added to.
* The previous translateX, translateY and scale is re-used.
*/
function addContainer() {

    // Zoom settings, these affect adding/removing nodes as well
    let scale = getSetting('setting_scale', 1);
    let translateX = getSetting('setting_translatex', 200);
    let translateY = getSetting('setting_translatey', 200);
    
    let s ='';
    if(isNaN(translateX) || isNaN(translateY) ||  translateX==null || translateY==null ||
        Math.abs(translateX)==Infinity || Math.abs(translateY)==Infinity){
        
        translateX = 0;
        translateY = 0;
    }
    s = "translate("+translateX+", "+translateY+")";    
    if(!(isNaN(scale) || scale==null || Math.abs(scale)==Infinity || scale < 0.5) ){
        s = s + "scale("+scale+")";
    }

    //s = "translate(1,1)scale(1)";    
    // Append zoomable container
    let container = svg.append("g")
                       .attr("id", "container")
                       .attr("transform", s);

    let scaleExtentVals = [0.9, 2]; ////[0.75, 7.5]

    if(!settings.isDatabaseStructure){
        //scaleExtentVals = [0.5, 3];

        //Travis Doyle 28/9 - Adjusted scale extent values to increase zoom out/in
        scaleExtentVals = [0.2, 15];
    }

    // Zoom behaviour                   
    this.zoomBehaviour = window.d3.behavior.zoom()
                           .translate([translateX, translateY])
                           .scale(scale)
                           .scaleExtent(scaleExtentVals)
                           .on("zoom", zoomed);
                    
    return container;
}

/**
* Update label scaling
*/
function updateLabels() {

    //Gerard Zoom Scaling
    const nodeList = document.querySelectorAll('.nodelabel');  //.setAttribute('style', 'scale: 5 !important;');
    for (let i = 0; i < nodeList.length; i++) {
        nodeList[i].style.scale = "1";
        nodeList[i].style.transform = "translate(0px, 0px)";
    }
}

/**
* Called after a zoom-event takes place.
*/
function zoomed() { 

    updateLabels();

    //keep current setting Translate   
    let translateXY = [];
    let notDefined = false;
    let transform = "translate(0,0)";
    if(window.d3.event.translate !== undefined) {
        if(isNaN(window.d3.event.translate[0]) || !isFinite(window.d3.event.translate[0])) {           
            window.d3.event.translate[0] = 0;
            notDefined = true;
        }else{
            putSetting('setting_translatex', window.d3.event.translate[0]); 
        }

        if(isNaN(window.d3.event.translate[1]) || !isFinite(window.d3.event.translate[1])) {           
            window.d3.event.translate[1] = 0;
            notDefined = true;
        }else{
            putSetting('setting_translatey', window.d3.event.translate[1]);
        }

        transform = "translate("+window.d3.event.translate+')';
    }else{
        notDefined = true;
    }
    
    let scale = window.d3.event.scale; //Math.pow(window.d3.event.scale,0.75);
    
    //keep current setting Scale
    if(!isNaN(window.d3.event.scale) && isFinite(window.d3.event.scale)&& scale!=0){
        putSetting('setting_scale', scale);
        transform = transform + "scale("+scale+")";
    }

    onZoom(transform);
}  

function onZoom( transform ){
    window.d3.select("#container").attr("transform", transform);
    
    let scale = this.zoomBehaviour.scale();
    if(isNaN(scale) || !isFinite(scale) || scale==0) scale = 1;
}

//
// Fit current extent 
//
function zoomToFit(){

    let fullWidth = $("#divSvg").width();
    let fullHeight = $("#divSvg").height();
    
    const box = window.d3.select("#container").node().getBBox();
    
    let width  = box.width,
        height = box.height;
        
    let midX = box.x + width / 2,
        midY = box.y + height / 2;

    let scale = getFitToExtentScale();
    if (scale == null && isNaN(Number(scale)) ) return; // nothing to fit

    let translate = [
        fullWidth  / 2 - scale * midX,
        fullHeight / 2 - scale * midY
    ];

    let zoom = this.zoomBehaviour; 

    //reset
    zoom.scale(scale)
        .translate(translate);    
    let transform = "translate(" + zoom.translate() + ")scale(" + zoom.scale() + ")";   
    onZoom(transform);
}

//
// 
//
function getFitToExtentScale(){

    let fullWidth = $("#divSvg").width();
    let fullHeight = $("#divSvg").height();

    const box = window.d3.select("#container").node().getBBox();

    let width  = box.width,
        height = box.height;

    if (width == 0 || height == 0) return null; // nothing to fit
    return 0.85 / Math.max(width / fullWidth, height / fullHeight);
}

//handle the zoom buttons
function zoomBtn(zoom_in){
    let zoom = this.zoomBehaviour; 
    
    let scale = zoom.scale(),
        extent = zoom.scaleExtent(),
        translate = zoom.translate(),
        x = translate[0], y = translate[1],
        factor = zoom_in ? 1.3 : 1/1.3,
        target_scale = scale * factor;

    if(isNaN(x) || !isFinite(x)) x = 0;
    if(isNaN(y) || !isFinite(y)) y = 0;
        
    // If we're already at an extent, done
    if (target_scale === extent[0] || target_scale === extent[1]) { return false; }
    // If the factor is too much, scale it down to reach the extent exactly
    let clamped_target_scale = Math.max(extent[0], Math.min(extent[1], target_scale));
    if (clamped_target_scale != target_scale){
        target_scale = clamped_target_scale;
        factor = target_scale / scale;
    }

    let width = $("#divSvg").width();
    let height = $("#divSvg").height();
    let center = [width / 2, height / 2];
    // Center each vector, stretch, then put back
    x = (x - center[0]) * factor + center[0];
    y = (y - center[1]) * factor + center[1];

    zoom.scale(target_scale)
        .translate([x,y]);    
    let transform = "translate(" + zoom.translate() + ")scale(" + zoom.scale() + ")";   
    onZoom(transform);
}

/********************************************* FORCE ***************************************/
/**
* Constructs a force layout
*/
function addForce() {
    let width = parseInt(svg.style("width"));
    let height = parseInt(svg.style("height"));
    let attraction = getSetting('setting_attraction');
    
    let force = window.d3.layout.force()
                  .nodes(window.d3.values(data.nodes))
                  .links(data.links)
                  .charge(attraction)        // Using the attraction setting
                  .linkDistance(function(d) {         
                     let linkDist = settings.getLineLength.call(this, d.target);
                     return linkDist;//linkDist;
                  })  // Using the linelength setting 
                  .on("tick", tick)
                  .size([width, height])
                  .start();
                  
    return force;
}  

/*************************************************** MARKERS ******************************************/
/**
* Adds marker definitions to a container
*/
function addMarkerDefinitions() {

    let markercolor = getSetting('setting_markercolor', '#000');

    let markers = window.d3.select('#container').append('defs'); // create container

    // *** Marker Mid ***
    markers.append('svg:marker') // Single arrow, pointing from field to rectype (for resources/pointers)
           .attr('id', 'marker-ptr-mid')
           .attr("markerWidth", 30)
           .attr("markerHeight", 30)
           .attr("refX", -1)
           .attr("refY", 0)
           .attr("viewBox", [-20, -20, 30, 30])
           .attr("markerUnits", "userSpaceOnUse")
           .attr("orient", "auto")
           .attr("fill", markercolor)
           .attr("opacity", 0.6)
           .append("path")                
           .attr("d", 'M0,5 L10,0 L0,-5');

    markers.append('svg:marker') // Double arrows, pointing opposite directions (for relmarkers)
           .attr('id', 'marker-rel-mid')
           .attr("markerWidth", 30)
           .attr("markerHeight", 30)
           .attr("refX", -1)
           .attr("refY", 0)
           .attr("viewBox", [-20, -20, 30, 30])
           .attr("markerUnits", "userSpaceOnUse")
           .attr("orient", "auto")
           .attr("fill", markercolor)
           .attr("opacity", 0.6)
           .append("path")                
           .attr("d", 'M1,-5 L9,0 L1,5 M-1,-5 L-9,0 L-1,5');

    markers.append("svg:marker") // Large and Small (child records) single arrows, pointing at each other
           .attr("id", "marker-childptr-mid")
           .attr("markerWidth", 40)
           .attr("markerHeight", 40)
           .attr("refX", -1)
           .attr("refY", 0)
           .attr("viewBox", [-30, -30, 40, 40])
           .attr("markerUnits", "userSpaceOnUse")
           .attr("orient", "auto")
           .attr("fill", markercolor)
           .attr("opacity", 0.6)
           .append("path")
           .attr("d", 'M-30,5 L-20,0 L-30,-5 M6,3 L-2,0 L6,-3');

    // *** Marker-End ***
    markers.append('svg:marker') // Single arrow, pointing from field to rectype (for resources/pointers)
           .attr('id', 'marker-ptr-end')
           .attr("markerWidth", 30)
           .attr("markerHeight", 30)
           .attr("refX", 50)
           .attr("refY", 0)
           .attr("viewBox", [-20, -20, 30, 30])
           .attr("markerUnits", "userSpaceOnUse")
           .attr("orient", "auto")
           .attr("fill", markercolor)
           .attr("opacity", 0.6)
           .append("path")                
           .attr("d", 'M0,5 L10,0 L0,-5');

    markers.append('svg:marker') // Double arrows, pointing opposite directions (for relmarkers)
           .attr('id', 'marker-rel-end')
           .attr("markerWidth", 30)
           .attr("markerHeight", 30)
           .attr("refX", 50)
           .attr("refY", 0)
           .attr("viewBox", [-20, -20, 30, 30])
           .attr("markerUnits", "userSpaceOnUse")
           .attr("orient", "auto")
           .attr("fill", markercolor)
           .attr("opacity", 0.6)
           .append("path")                
           .attr("d", 'M1,-5 L9,0 L1,5 M-1,-5 L-9,0 L-1,5');

    markers.append("svg:marker") // Large and Small (child records) single arrows, pointing at each other
           .attr("id", "marker-childptr-end")
           .attr("markerWidth", 40)
           .attr("markerHeight", 40)
           .attr("refX", 20)
           .attr("refY", 0)
           .attr("viewBox", [-30, -30, 40, 40])
           .attr("markerUnits", "userSpaceOnUse")
           .attr("orient", "auto")
           .attr("fill", markercolor)
           .attr("opacity", 0.6)
           .append("path")
           .attr("d", 'M-30,5 L-20,0 L-30,-5 M6,3 L-2,0 L6,-3');

    // *** Misc ***
    markers.append("svg:marker") // Circle blob, for end of lines/extra connectors
           .attr("id", "blob")
           .attr("markerWidth", 5)
           .attr("markerHeight", 5)
           .attr("refX", 5)
           .attr("refY", 5)
           .attr("viewBox", [0, 0, 20, 20])
           .append("circle")
           .attr("cx", 5)
           .attr("cy", 5)
           .attr("r", 5)
           .style("fill", "darkgray");
		   
    markers.append("svg:marker") // Text, for self linking nodes
           .attr("id", "self-link")
           .attr("markerWidth", 10)
           .attr("markerHeight", 10)
           .attr("refX", 0)
           .attr("refY", 0)
           .attr("viewBox", [0, 0, 20, 20])
           .attr("overflow", "visible")
           .append("text")
           .attr("x", -6)
           .attr("y", -1)
           .style("fill", "black")
           .style("font-size", "6.1px")
           .text("Self");

    return markers;
}

/************************************ LINES **************************************/      
/**
* Constructs lines, either straight or curved based on the settings 
* @param name Extra class name 
*/
function addLines(name, color, thickness) {
    // Add the chosen lines [using the linetype setting]
    let lines;
    
    let linetype = getSetting('setting_linetype', 'straight');
    let hide_empty = (getSetting('setting_line_empty_link', 1)==0);
    
    lines = window.d3.select("#container")
           .append("svg:g")
           .attr("id", name)
           .selectAll("path")
           .data(data.links)
           .enter()
           .append("svg:path");

    let scale = this.zoomBehaviour.scale(); //current scale
    
    // Adding shared attributes
    lines.attr("class", function(d) {
            return name + " link s"+d.source.id+"r"+d.relation.id+"t"+d.target.id;
         })
         .attr("stroke", function (d) {
            if(hide_empty && d.targetcount == 0 || name === 'rollover-lines' || name == 'top-lines'){
                return 'rgba(255, 255, 255, 0.0)'; //hidden
            }else if(d.targetcount == 0 && name === 'bottom-lines') {
                return '#d9d8d6';
            }else{
                return color;
            }
         })
         .attr("stroke-linecap", "round")
         .style("stroke-width", function(d) { 
             let w = getLineWidth(d.targetcount)+thickness; //width for scale 1
             if(name == 'top-lines'){
                w = w*0.2;
             }else if(name == 'rollover-lines'){
                w = w*3;
             }
             return (scale>1)?w:(w/scale);
         });

    // visible line, pointing from one node to another
    if(name=='top-lines' && linetype == "straight" && currentMode == 'infoboxes_full'){

        lines.attr("marker-end", function(d) {
            if(!(hide_empty && d.targetcount == 0)){
                // reference to marker id
                if($Db.rst(d.source.id, d.relation.id, 'rst_CreateChildIfRecPtr') == 1){ // double different size arrows
                    return "url(#marker-childptr-end)";
                }else if(d.relation.type == 'resource'){ // single arrow
                    return "url(#marker-ptr-end)";
                }else{ // other/error
                    return null;
                }
            }
        });

        lines.attr("marker-mid", function(d) {
            // reference to marker id
            if(!(hide_empty && d.targetcount == 0) && (d.relation.type == 'relmarker' || d.relation.type == 'relationship')){ // double same size arrows
                return "url(#marker-rel-mid)";
            }else{ // other/error
                return null;
            }
        });
    }else if(name=='top-lines' && linetype != "stepped"){

        lines.attr("marker-mid", function(d) {
            if(!(hide_empty && d.targetcount == 0)){
                // reference to marker id
                if($Db.rst(d.source.id, d.relation.id, 'rst_CreateChildIfRecPtr') == 1){ // double different size arrows
                    return "url(#marker-childptr-mid)";
                }else if(d.relation.type == 'resource'){ // single arrow
                    return "url(#marker-ptr-mid)";
                }else if(d.relation.type == 'relmarker' || d.relation.type == 'relationship'){ // double same size arrows
                    return "url(#marker-rel-mid)";
                }else{ // error
                    return null;
                }
            }
        });
    }

    if(name == 'rollover-lines'){

        lines.on("mouseover", function(d) {
            if(!(hide_empty && d.targetcount == 0)){
                let selector = "s"+d.source.id+"r"+d.relation.id+"t"+d.target.id;
                createOverlay(window.d3.event.offsetX, window.d3.event.offsetY, "relation", selector, getRelationOverlayData(d));
            }
        })
        .on("mouseout", function(d) {
            let selector = "s"+d.source.id+"r"+d.relation.id+"t"+d.target.id;
            removeOverlay(selector, 0);
        });
    }

    return lines;
}

/**
* Updates the correct lines based on the linetype setting 
*/
function tick() {
    
    //grab each set of lines
    let topLines = window.d3.selectAll(".top-lines"); 
    let bottomLines = window.d3.selectAll(".bottom-lines");
    let rolloverLines = window.d3.selectAll(".rollover-lines");

    //$(".offset_line").hide(); // hide additional lines

    let linetype = getSetting('setting_linetype', 'straight');
    if(linetype == "curved") {
        updateCurvedLines(topLines);
        updateCurvedLines(bottomLines);
        updateCurvedLines(rolloverLines);
    }else if(linetype == "stepped") {
        updateSteppedLines(topLines, 'top');
        updateSteppedLines(bottomLines, 'bottom');
        updateSteppedLines(rolloverLines, 'rollover');
    }else{
        updateStraightLines(bottomLines, "bottom-lines");
        updateStraightLines(topLines, "top-lines");
        updateStraightLines(rolloverLines, "rollover-lines");
    }
    
    // Update label scaling
    //updateLabels();

    // Update node locations
    updateNodes();

    // Update the furthest possible zoom
    if(!settings.isDatabaseStructure){

        let cur_scaleExtend = zoomBehaviour.scaleExtent();
        let lower_extent = getFitToExtentScale();

        if(lower_extent != null && !isNaN(Number(lower_extent))){
            zoomBehaviour.scaleExtent([lower_extent, cur_scaleExtend[1]]);
        }
        if(zoomBehaviour.scale() < lower_extent){
            zoomBehaviour.scale(lower_extent);
        }
    }
}

/**
* Updates all curved lines
* @param lines Object holding curved lines
*/
function updateCurvedLines(lines) {
    
    let pairs = {};
    
    // Calculate the curved segments
    lines.attr("d", function(d) {
        
        let key = d.source.id+'_'+d.target.id; 
        if(!pairs[key]){
            pairs[key] = 1.5;
        }else{
            pairs[key] = pairs[key]+0.25;
        } 
        let k = pairs[d.source.id+'_'+d.target.id];
        
        let target_x = d.target.x,
            target_y = d.target.y;

        if(d.target.id==d.source.id){
            // Self Link, Affects Loop Size
            target_x = d.source.x+70;
            target_y = d.source.y-70;
        }

        let dx = target_x - d.source.x,
            dy = target_y - d.source.y,
            dr = Math.sqrt(dx * dx + dy * dy)/k,
            mx = d.source.x + dx,
            my = d.source.y + dy;

        if(d.target.id==d.source.id){ // Self Linking Node

            return `M ${d.source.x} ${d.source.y} `
                 + `A ${dr} ${dr} 0 0 1 ${mx} ${my} `
                 + `A ${dr} ${dr} 0 0 1 ${target_x} ${target_y} `
                 + `A ${dr} ${dr} 0 0 1 ${d.source.x} ${d.source.y}`;
            
        }else{ // Node to Node Link
            
            return `M ${d.source.x} ${d.source.y} `
                 + `A ${dr} ${dr} 0 0 1 ${mx} ${my} `
                 + `A ${dr} ${dr} 0 0 1 ${target_x} ${target_y}`;
        }
    });

}

/**
* Updates a straight line             
* @param lines Object holding straight lines
*/
function updateStraightLines(lines, type) {
    
    let pairs = {};
    let isExpanded = $('#expand-links').is(':Checked');
    
    $(".icon_self").each(function() {
        $(this).remove();
    });
    let container = window.d3.select('#container');
    
    // Calculate the straight points
    lines.attr("d", function(d) {

        if(d == null){
            return '';
        }
        
        //are source and target defined
        if(d.source.id && d.target){
            if(isNaN(d.source.x) || isNaN(d.source.y) || isNaN(d.target.x) || isNaN(d.target.y)){
                return false;
            }
        }
        
        let key = d.source.id+'_'+d.target.id,
            indent = 20;

        if(pairs[d.target.id+'_'+d.source.id]){
            key = d.target.id+'_'+d.source.id;
        }else if(!pairs[key]){
            indent = 0;
        }

        if(indent>0){ // This controls how far apart lines will be when going to and from the same node

            if(isExpanded){ // This is for the expanded option, displays all lines
                pairs[key] = pairs[key] + indent;
            }else{ // This will hide all other lines, default behaviour
                return [''];
            }
        }else{
            pairs[key] = 1;
        }

        let R = pairs[key];
        let pnt = '';

        let s_x = d.source.x,
            s_y = d.source.y,
            t_x = d.target.x,
            t_y = d.target.y;

        let ismultivalue = settings.isDatabaseStructure && $Db.rst(d.source.id, d.relation.id, 'rst_MaxValues') != 1 && $Db.rst(d.source.id, d.relation.id, 'rst_MaxValues') != null;

        if(d.target.id==d.source.id){ // Self Linking Node
        
            let target_x, target_y, dx, dy, dr, mx, my;

            if(currentMode == 'infoboxes_full'){

                let $detail = $('.id'+d.source.id).find('[dtyid="'+ d.relation.id +'"]'),
                    $source_rect = $($('.id'+d.source.id).find('rect[rtyid="'+ d.source.id +'"]')[0]);

                if($detail.length == 1){

                    // Get detail's y location within the source object
                    const detail_y = $detail[0].getBBox().y;
                    s_y += detail_y - iconSize * 0.6;
                }

                // Reduce x and y locations
                s_x -= (iconSize / 1.5);

                // Prepare extra lines
                const s_x2 = s_x;
                s_x -= 12;

                if(type == 'bottom-lines'){

                    let id = `selfibfbtlinesrc_${d.source.id}_${d.relation.id}`;
                    let selectedLine = container.select(`#${id}`);
                    //add extra starting line
                    if (selectedLine.empty()) {
                        selectedLine = container.insert("svg:line", `.id${d.source.id} + *`)
                        .attr("class", "offset_line")
                        .attr("id", id)
                        .attr("stroke", "darkgray")
                        .attr("stroke-linecap", "round")
                        .style("stroke-width", "3px")
                        .attr("marker-end", "url(#blob)")
                        .attr("marker-start", "url(#self-link)");
                    }

                    selectedLine.style('display', 'inline')
                            .attr("x1", s_x)
                            .attr("y1", s_y)
                            .attr("x2", s_x2)
                            .attr("y2", s_y);
                }
            }else{

                // Affects Loop Size
                target_x = s_x+70;
                target_y = s_y-70;

                dx = target_x - s_x;
                dy = target_y - s_y;
                dr = Math.sqrt(dx * dx + dy * dy)/1.5;
                mx = s_x + dx;
                my = s_y + dy;

                return `M ${s_x} ${s_y} `
                     + `A ${dr} ${dr} 0 0 1 ${mx} ${my} `
                     + `L ${s_x + 35} ${s_y - 35} `
                     + `L ${s_x} ${s_y}`;
            }
        }else{ // Node to Node Link

            let dx, dy, tg, dx2, dy2, mdx, mdy, s_x2, t_x2, t_y2;
            let elevation_diff = false;
            let threshold = 60;

            if(currentMode == 'infoboxes_full'){

                // Relevant svg Elements/Items
                let $source_rect = $($('.id'+d.source.id).find('rect[rtyid="'+ d.source.id +'"]')[0]),
                    $target_rect = $($('.id'+d.target.id).find('rect[rtyid="'+ d.target.id +'"]')[0]),
                    $detail = $('.id'+d.source.id).find('[dtyid="'+ d.relation.id +'"]');

                // Get the width for source and target rectangles
                let source_width = Number($source_rect.attr('width')),
                    target_width = Number($target_rect.attr('width'));

                if($detail.length > 0){ // Check that the location of the detail can be found

                    // Get detail's y location within the source object
                    const detail_y = $detail[0].getBBox().y;
                    s_y += detail_y - iconSize * 0.6;
                }

                // Get target's bottom y location
                let b_target_y = t_y + Number($target_rect.attr('height')) - iconSize + 2;

                // Left Side: x Point for starting and ending nodes
                s_x -= iconSize;
                t_x -= iconSize;
                // Right Side: x Point for starting and ending nodes
                let r_source_x = s_x + source_width + iconSize / 4;
                let r_target_x = t_x + target_width + iconSize / 4;

                if(r_source_x + threshold < t_x){ // Right to Left Connection, Change source x location
                    
                    s_x = r_source_x;

                    s_x2 = s_x - 5;
                    t_x2 = t_x;

                    s_x += 7;
                    t_x -= 7;
                }else if(s_x > r_target_x + threshold){ // Left to Right Connection, Change target x location

                    t_x = r_target_x;

                    s_x2 = s_x + 5;
                    t_x2 = t_x;

                    s_x -= 7;
                    t_x += 7;
                }else{ // target is above/below source and was same side connectors

                    t_x += (target_width / 2);
                    t_x2 = t_x;

                    if(t_y < s_y){ // target is higher than source
                        t_y2 = b_target_y;
                        t_y = b_target_y + 10;
                    }else{
                        t_y2 = t_y - iconSize;
                        t_y -= iconSize + 10;
                    }

                    // Differences between points (x coord)
                    let left_diff = (t_x - s_x > s_x - t_x) ? t_x - s_x : s_x - t_x;
                    let right_diff = (t_x - r_source_x > r_source_x - t_x) ? t_x - r_source_x : r_source_x - t_x;

                    if(right_diff < left_diff){ // right 2 right

                        s_x = r_source_x;

                        s_x2 = s_x - 5;

                        s_x += 7;
                    }else{ // left 2 left

                        s_x2 = s_x + 5;
                        s_x -= 7;
                    }

                    elevation_diff = true;
                }

                if(type == 'bottom-lines'){
                    // Junze: Node2NodeInfoBoxesFullBottomLineSource
                    let id = `n2nibfbtlinesrc_${d.source.id}_${d.relation.id}_${d.target.id}`;
                    let selectedLine = container.select(`#${id}`);
                    if (selectedLine.empty()) {
                        //add extra starting line + blob
                        selectedLine = container.insert("svg:line", `.id${d.source.id} + *`)
                        .attr("class", "offset_line")
                        .attr("id", id)
                        .attr("stroke", "darkgray")
                        .attr("stroke-linecap", "round")
                        .style("stroke-width", "3px")
                        .attr("marker-end", "url(#blob)");
                    }

                    selectedLine.style('display', 'inline')
                            .attr("x1", s_x)
                            .attr("y1", s_y)
                            .attr("x2", s_x2)
                            .attr("y2", s_y);
                    
                    let linecolour = (!ismultivalue) ? 'darkgray' : 'dimgray';
                    let linewidth = (!ismultivalue) ? '3px' : '2px';
                    // Junze: Node2NodeInfoBoxesFullBottomLineTarget
                    id = `n2nibfbltgt_${d.target.id}_${d.relation.id}_${d.source.id}`;
                    selectedLine = container.select(`#${id}`);

                    if (!elevation_diff) {
                        // add extra ending line
                        // Junze: check the line exist
                        if (selectedLine.empty()) {
                            // Junze: if not exist create the line
                            selectedLine = container.insert("svg:line", `.id${d.target.id} + *`)
                            .attr("class", "offset_line")
                            .attr("id", id)
                            .attr("stroke", linecolour)
                            .attr("stroke-linecap", "round")
                            .style("stroke-width", linewidth);
                        }
                        // Junze: update the coordinates
                        selectedLine.style('display', 'inline')
                                .attr("x1", t_x)
                                .attr("y1", t_y)
                                .attr("x2", t_x2)
                                .attr("y2", t_y);

                        //add crows foot, if multi value
                        if(ismultivalue){

                            let hideId = `#n2nibfsrc_${d.target.id}_${d.relation.id}_${d.source.id}`;
                            let hideLine = container.select(hideId);
                            if (!hideLine.empty())
                                hideLine.style("display", "none")
                            // Node2NodeInfoBoxesFullBottomLineSourceMultiValue
                            id = `n2nibfblsrcmv_${d.source.id}_${d.relation.id}_${d.target.id}`;
                            selectedLine = container.select(`#${id}`);
                            if (selectedLine.empty()) {
                                selectedLine = container.insert("svg:path", `.id${d.source.id} + *`)
                                .attr("id", id)
                                .attr("class", "offset_line")
                                .attr("stroke-linecap", "round")
                                .attr("fill", "none");
                            }

                            selectedLine.style('display', 'inline')
                                .attr("stroke-width", linewidth)
                                .attr("stroke", linecolour)
                                .style("display", null)
                                .attr("d", `M ${t_x2} ${t_y + 5} L ${t_x} ${t_y} L ${t_x2} ${t_y - 5}`);
                        }
                    }else{

                        //add crows foot, if multi value
                        if(ismultivalue){
                            if (selectedLine.empty()) {
                                selectedLine = container.insert("svg:line", `.id${d.target.id} + *`)
                                .attr("class", "offset_line")
                                .attr("id", id)
                                .attr("stroke", linecolour)
                                .attr("stroke-linecap", "round")
                                .style("stroke-width", linewidth);
                            }
                            // add extra ending line
                            selectedLine.style('display', 'inline')
                                    .attr("x1", t_x)
                                    .attr("y1", t_y)
                                    .attr("x2", t_x)
                                    .attr("y2", t_y2);
                            
                            let hideId = `#n2nibfblsrcmv_${d.source.id}_${d.relation.id}_${d.target.id}`;
                            let hideLine = container.select(hideId);
                            if (!hideLine.empty()) 
                                hideLine.style("display", "none");
                            id = `n2nibfsrc_${d.target.id}_${d.relation.id}_${d.source.id}`;
                            selectedLine = container.select(`#${id}`);
                            if (selectedLine.empty()) {
                                selectedLine = container.insert("svg:path", `.id${d.source.id} + *`)
                                .attr("id", id)
                                .attr("class", "offset_line")
                                .attr("stroke-linecap", "round")
                                .attr("fill", "none");
                            }

                            selectedLine.style("display", 'inline')
                                .attr("stroke", linecolour)
                                .attr("stroke-width", linewidth)
                                .attr("fill", "none")
                                .attr("d", `M ${t_x + 5} ${t_y2} L ${t_x} ${t_y} L ${t_x - 5} ${t_y2}`);
                        }else{

                            if(!settings.isDatabaseStructure){
                                let hideId = `#n2nibfbltgt_${d.target.id}_${d.relation.id}_${d.source.id}`;
                                let hideLine = container.select(hideId);
                                if (!hideLine.empty()) {
                                    hideLine.style("display", "none");
                                }
                            }

                            t_y = t_y2;
                        }
                    }
                }

                dx = (t_x-s_x)/2;
                dy = (t_y-s_y)/2;

                mdx = s_x + dx;
                mdy = s_y + dy;

            }else{

                dx = (t_x-s_x)/2;
                dy = (t_y-s_y)/2;

                tg = (dx!=0)?Math.atan(dy/dx):0;

                dx2 = dx-R*Math.sin(tg);
                dy2 = dy+R*Math.cos(tg);

                mdx = s_x + dx2;
                mdy = s_y + dy2;

            }

            pnt = `M ${s_x} ${s_y} `
                + `L ${mdx} ${mdy} `
                + `L ${t_x} ${t_y}`;

        }
       
        return pnt; 
    });
    
}

function updateSteppedLines(lines, type){

    let pairs = {};

    $(".hidden_line_for_markers").remove();

    // Calculate the straight points
    lines.attr("d", function(d) {

        let dx = (d.target.x-d.source.x)/2,
            dy = (d.target.y-d.source.y)/2;

        let indent = ((Math.abs(dx)>Math.abs(dy))?dx:dy)/4;

        let key = d.source.id+'_'+d.target.id;
        if(pairs[d.target.id+'_'+d.source.id]){
            key = d.target.id+'_'+d.source.id;
        }else if(!pairs[key]){
            pairs[key] = 1-indent;
        }

        pairs[key] = pairs[key]+indent;
        let k = pairs[key];

        let target_x = d.target.x,
            target_y = d.target.y;
        let res = [];

        let marker_type = (d.relation.type == 'resource') ? 'url(#marker-ptr-mid)' : 'url(#marker-rel-mid)';

       if(d.target.id==d.source.id){ // Self Linking Node
            // Affects Loop Size
            target_x = d.source.x+65;
            target_y = d.source.y-65;

            dx = target_x - d.source.x;
            dy = target_y - d.source.y;
            
            let dr = Math.sqrt(dx * dx + dy * dy)/1.5,
                mx = d.source.x + dx,
                my = d.source.y + dy;

            res = `M ${d.source.x} ${d.source.y} `
                + `A ${dr} ${dr} 0 0 1 ${mx} ${my} `
                + `L ${d.source.x + 35} ${d.source.y -35} `
                + `L ${d.source.x} ${d.source.y}`;

            if(window.hWin.HEURIST4.util.isFunction($(this).attr)){
                $(this).attr("marker-mid", marker_type);
            }

       }else{  // Node to Node Link

            let dx2 = 45*(dx==0?0:((dx<0)?-1:1));
            let dy2 = 45*(dy==0?0:((dy<0)?-1:1));

            //path
            res = `M ${d.source.x} ${d.source.y} `
                + `L ${d.source.x + dx2} ${d.source.x + dy2} `
                + `L ${d.source.x + dx2 + dx + k} ${d.source.y + dy2} `
                + `L ${d.source.x + dx2 + dx + k} ${target_y} `
                + `L ${target_x} ${target_y}`;

            if(type=='bottom'){
                //add 3 lines - specially for markers
                let g = window.d3.select("#container").append("svg:g").attr("class", "hidden_line_for_markers");
                
                let pnt = `M ${d.source.x + dx2} ${d.source.y + dy2} `
                        + `L M ${d.source.x + dx2 + dx / 2 + k} ${d.source.y + dy2}`;

                g.append("svg:path")
                        .attr("d", pnt)
                        //reference to marker id
                        .attr("marker-end", marker_type);

                pnt = `M ${d.source.x + dx2 + dx + k} ${d.source.y + dy2} `
                    + `L ${d.source.x + dx2 + dx + k} ${d.source.y + dy2 + (target_y - d.source.y - dy2) / 2}`;
                g.append("svg:path")
                        //.attr("class", "hidden_line_for_markers")
                        .attr("d", pnt.join(' '))
                        //reference to marker id
                        .attr("marker-end", marker_type);
            }
            dx = dx + k;
        }
        
        return res;      
    });
    
}

/************************************************** NODE CHILDREN ********************************************/
/**
* Adds <title> elements to all nodes 
*/
function addTitles() {
    let titles = window.d3.selectAll(".node")
                   .append("title")
                   .text(function(d) {
                        return d.name;
                   });
    return titles;
}

/**
* Adds background <circle> elements to all nodes
* These circles can be styled in the settings bar
*/
function addBackgroundCircles() {
    let entitycolor = getSetting('setting_entitycolor');
    let circles = window.d3.selectAll(".node")
                    .append("circle")
                    .attr("r", function(d) {
                        return getEntityRadius(d.count);
                    })
                    .attr("class", "background")
                    //.attr("fill", entitycolor);
    return circles;
}

/**
* Adds foreground <circle> elements to all nodes
* These circles are white
*/
function addForegroundCircles() {
    let entitycolor = getSetting('setting_entitycolor');

    let circles = window.d3.selectAll(".node")
                    .append("circle")
                    .attr("r", circleSize)
                    .attr("fill", entitycolor)
                    .attr("class", 'foreground')
                    .style("stroke", "#ddd")
                    .style("stroke-opacity", function(d) {
                        if(d.selected == true) {
                            return 1;
                        }
                        return .25;
                    });
    return circles;
}

/**
* Adds icon <img> elements to all nodes
* The image is based on the "image" attribute
*/
function addIcons() {
    let icons = window.d3.selectAll(".node")
                  .append("svg:image")
                  .attr("class", "icon")
                  .attr("xlink:href", function(d) {
                       return d.image;
                  })
                  .attr("x", iconSize/-2)
                  .attr("y", iconSize/-2)
                  .attr("height", iconSize)
                  .attr("width", iconSize);  
                  
    return icons;
}

/**
* Adds <text> elements to all nodes
* The text is based on the "name" attribute
* Task is performed when the nodes are added
*/
function addLabels(name, color) {
    let maxLength = getSetting('setting_textlength');
    let labels = window.d3.selectAll(".node")
                  .append("text")
                  .attr("x", iconSize)
                  .attr("y", iconSize/4)
                  .attr("class", name + " bold")
                  .attr("fill", color)
                  .style("font-size", settings.fontsize, "important")
                  .text(function(d) {
                      return truncateText(d.name, maxLength);
                  });
    return labels;
}

//
//
//
function showEmbedDialog(){

    let query = window.hWin.HEURIST4.query.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, false);
    query = query + ((query=='?')?'':'&') + 'db='+window.hWin.HAPI4.database;
    let url = window.hWin.HAPI4.baseURL+'viewers/visualize/springDiagram.php' + query;

    //encode
    query = window.hWin.HEURIST4.query.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, true);
    query = query + ((query=='?')?'':'&') + 'db='+window.hWin.HAPI4.database;
    let url_enc = window.hWin.HAPI4.baseURL+'viewers/visualize/springDiagram.php' + query;

    window.hWin.HEURIST4.ui.showPublishDialog({mode:'graph', url: url, url_encoded: url_enc});

}            

function inIframe() { 

    let fullscreenbtn = document.getElementById("windowPopOut");
    let closewindowbtn = document.getElementById("closegraphbutton");
    let refreshData = document.getElementById("resetbutton");

    let gravitymodeZero = document.getElementById("gravityMode0");
    let gravitymodeOne = document.getElementById("gravityMode1");

    if (window.location !== window.parent.location) {
        //Page is in iFrame
        fullscreenbtn.style.visibility = 'visible';
        closewindowbtn.style.display = 'none';
        refreshData.style.visibility = 'visible';

        gravitymodeZero.style.visibility = 'visible';
        gravitymodeOne.style.visibility = 'visible';

    } else {
        //Page is not in iFrame
        fullscreenbtn.style.display = 'none';
        closewindowbtn.style.visibility = 'visible';
        refreshData.style.display = 'visible';

        gravitymodeZero.style.display = 'visible';
        gravitymodeOne.style.display = 'visible';

    }

}

//New graph refresh button - Created by Travis Doyle 24/9/2022
function refreshButton() {
    if(window.location !== window.parent.location){ // handle iframe

        let query = settings.request ? settings.request : window.hWin.HEURIST4.current_query_request;
        query = window.hWin.HEURIST4.query.composeHeuristQuery2(query, false);
        query = query + ((query == '?') ? '' : '&') + 'db=' + window.hWin.HAPI4.database;

        location.href = query;
    }else{
        location.reload();    
    }
}

//open graph in fullscreen - Travis Doyle 28/9
function openWin() {
    let hrefnew = window.hWin.HEURIST4.query.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, false);
    hrefnew = hrefnew + ((hrefnew == '?') ? '' : '&') + 'db=' + window.hWin.HAPI4.database;
    let url2 = window.hWin.HAPI4.baseURL + 'viewers/visualize/springDiagram.php' + hrefnew;
    window.open(url2);
}
//close fullscreen graph - Travis Doyle 28/9
function closeWin() {
    window.close();
    return;
}

function filterData(json_data) {
    
    if(!json_data) json_data = settings.data; 
    let names = [];
    $(".show-record").each(function() {
        const name = $(this).attr("name");
        if(!$(this).is(':checked')){ //to exclude
            names.push(name);
        }
    });    
    
    // Filter nodes
    let map = {};
    let size = 0;
    let nodes = json_data.nodes.filter(function(d, i) {
        if($.inArray(d.name, names) == -1) {
            map[i] = d;
            return true;
        }
        return false;
    });      
    
    // Filter links
    let links = [];
    json_data.links.filter(function(d) {
        if(Object.hasOwn(map, d.source) && Object.hasOwn(map, d.target)) {
            let link = {source: map[d.source], target: map[d.target], relation: d.relation, targetcount: d.targetcount};
            links.push(link);
        }
    });

    let data_visible = {nodes: nodes, links: links};
    settings.getData = function(all_data) { return data_visible; }; 
    visualizeData();
}
