
/**
* drag.js
* 
* Functions to add nodes and make them draggable
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

/* global svg, data, settings, force, currentMode, circleSize, iconSize, _editRecStructure, 
drag_link_source_id, drag_link_target_id, drag_link_line, selectionColor, determineColour, closeRectypeSelector,
getSetting, putSetting, createOverlay, getEntityRadius, truncateText, updateCircles, tick */

let currentNode = null;

/**
* Appends nodes to the visualisation
*/
function addNodes() {
    
   // Append nodes
   let nodes = window.d3.select("#container")
                  .selectAll(".node")
                  .data(data.nodes)
                  .enter()
                  .append("g")
                  .on("dblclick", (d) => {
                    if(!settings.isDatabaseStructure){ //Added Double Click to Edit Function - Travis Doyle 19/9
                        window.open(window.hWin.HAPI4.baseURL + '?fmt=edit&db=' + window.hWin.HAPI4.database + '&recID=' + d.id, '_blank');
                    }else if(window.hWin.HAPI4.is_admin()){
                        _editRecStructure(d.id);
                    }
                  });
                 
   // Dragging
   let drag = window.d3.behavior.drag()
                 .on("dragstart", dragstart)
                 .on("drag", dragmove)
                 .on("dragend", dragend);
     
   let entitycolor = getSetting('setting_entitycolor');
      
   // Details for each node            
   nodes.each(function(d, i) {
        // Restore location data
        let record = getSetting(d.id);
        if(record) {
            const obj = JSON.parse(record);
            if("x" in obj) {
                d.x = obj.x;
            }
            if("y" in obj) {
                d.y = obj.y;
            }
            if("px" in obj) {
                d.px = obj.px;
            }
            if("py" in obj) {
                d.py = obj.py;
            }
        }

        let node = window.d3.select(this);
        
        let icon_display = currentMode=='icons' ? 'initial' : 'none';
        
        //add infobox
        createOverlay(0, 0, "record", "id"+d.id, d, node);
        
        //add outer circle
        node.append("circle")
            .attr("r", function(d) {
                return getEntityRadius(d.count);
            })
            .attr("class", "background icon-background")
            .style({'fill-opacity': '0.5', 'display': icon_display})
            .attr("fill", determineColour); //entitycolor        
        
        //add internal circle
        node.append("circle")
            .attr("r", circleSize)
            .attr("class", 'foreground icon-foreground')
            .attr("fill", entitycolor)
            .style({"stroke": "#ddd", 'display': icon_display})
            .style("stroke-opacity", function(d) {
                if(d.selected == true) {
                    return 1;
                }
                return .25;
            });

        //add icon
        node.append("svg:image")
            .attr("class", "icon node-icon") 
            .attr("xlink:href", function(d) {
                if(d.image){
                    return d.image;
                }else{
                    return '';
                }
            })
            .attr("x", -iconSize/2)
            .attr("y", -iconSize/2)
            .attr("height", iconSize)
            .attr("width", iconSize)
            .on("mouseover", function(d) {
                if(drag_link_source_id!=null){
                    window.drag_link_target_id = d.id;
                    window.drag_link_line.attr("stroke","#00ff00");  //green
                }
            })
            .on("mouseout", function(d) {
                if(drag_link_source_id!=null){
                setTimeout(function(){
                    window.drag_link_target_id = null;
                    if(window.drag_link_line) window.drag_link_line.attr("stroke","#ff0000");  //red
                },200);
                }
            })
            .style('display', icon_display);
                           
        let gravity = getSetting('setting_gravity');
        
        // Attributes
        node  //window.d3.select(this)
          .attr("class", "node id"+d.id)
          .attr("transform", "translate(10, 10)")
          .attr("x", d.x) 
          .attr("y", d.y)
          .attr("px", d.px)
          .attr("py", d.py)
          .attr("fixed", function(d) {
              if(record && gravity == "off") {
                  d.fixed = true;
                  return true;
              }
              return false;
          })    
         .on("click", function(d) {
             
            closeRectypeSelector();
            // Check if it's not a click after dragging
            if(!window.d3.event.defaultPrevented) {
                // Load record details
                showNodeInformation(d);//Added by ISH
            }
         })
         .call(drag);

     });            
     return nodes;
}

/*Shows the record details in an iframe when a node is clicked
  Added by "ISH"
*/
function showNodeInformation(d){

    if(settings.isDatabaseStructure){
        return;
    }

    let iframeDiv = window.d3.select("#iframeDiv");//select the parent div
    let infoBox = window.d3.select("#iframeInfo");//select the iframe

    if(iframeDiv.length == 0 || infoBox.length == 0){
        return;
    }

    iframeDiv.style("display", "block");// make iframe visible

    if(infoBox.attr("recid") == d.id){ // block retrival of last record in quick succession
        return;
    }

    window.hWin.HEURIST4.msg.bringCoverallToFront(iframeDiv, {'background-color': 'white', 'opacity': 1, 'font-weight': 'bold', 'font-size': 'smaller', 'color': 'black'}, 
        'Loading<br><br>'+ window.hWin.HEURIST4.util.stripTags(truncateText(d.name, 40)));

    const srcURL = window.hWin.HAPI4.baseURL + 'viewers/record/renderRecordData.php?noclutter=1&recID=' 
                + d.id + '&db=' + window.hWin.HAPI4.database;//URL for source of information iframe
    infoBox.attr("src", srcURL)
           .attr("recid", d.id)
           .attr("data-recid", d.id)
           .on('load', () => {
                window.hWin.HEURIST4.msg.sendCoverallToBack(true);
           });//supply document to iframe

    // Remove block after 5 seconds
    setTimeout((id) => {
        if(infoBox.attr("recid") == id){
            infoBox.attr("recid", "");
        }
    }, 5000, d.id);
}

/*Hides record details shown by showNodeInformation
  Added by "ISH"
*/
function handleNodeAction(action = 'close'){

    if(action == 'close'){
        window.d3.select('#iframeDiv').style('display', 'none');//close the box when clicked 
        return;
    }

    let rec_ID = window.d3.select('#iframeInfo').attr('data-recid');
    let recviewer_URL = `${window.hWin.HAPI4.baseURL}viewers/record/renderRecordData.php?recID=${rec_ID}&db=${window.hWin.HAPI4.database}`;

    action == 'popup' ? window.hWin.HEURIST4.ui.openRecordInPopup(rec_ID, null, false) : window.open(recviewer_URL, '_blank');
}

/**
* Updates the locations of all nodes
*/
function updateNodes() {
    window.d3.selectAll(".node").attr("transform", function(d) { 
        // Store new position
        if(d.x==null || d.y==null || isNaN(d.x) || isNaN(d.y)){
            d.x=0;
            d.y=0;
        }
        const obj = {px: d.px, py: d.py, x: d.x, y: d.y};
        putSetting(d.id, JSON.stringify(obj));
        return "translate(" + d.x + "," + d.y + ")"; 
    });
}

// Functions to make dragging, moving and zooming possible

/** Called when a dragging event starts */
function dragstart(d, i) {
    
    window.d3.event.sourceEvent.stopPropagation();
    window.d3.event.sourceEvent.preventDefault();

    force.stop();

    // Fixed node positions?
    const gravity = getSetting('setting_gravity');
    svg.selectAll(".node")
       .attr("fixed", function(d, i) {
            d.fixed = (gravity == "off");
            return d.fixed;
       }); 
    d.fixed = true; 
    currentNode = d.id;
    
    updateCircles(".node.id"+d.id, selectionColor, selectionColor);
}

/** Caled when a dragging move event occurs */
function dragmove(d, i) {  
    
    // Update all selected nodes. A node is selected when the .foreground color is 190,228,248
    svg.selectAll(".node").each(function(d, i) {
        //const color = window.d3.select(this).select(".foreground").style("fill");
        if(d.id == currentNode) {
            // Update locations
            d.px += window.d3.event.dx;
            d.py += window.d3.event.dy;
            d.x += window.d3.event.dx;
            d.y += window.d3.event.dy;
        }   
    });

    // Update nodes & lines
    tick();                                                          

}

/** Called when a dragging event ends */
function dragend(d, i) {
    
    // Update nodes & lines
    const gravity = getSetting('setting_gravity');
    d.fixed = ( gravity !== "aggressive");
    
    // Update the location in localstorage
    const record = getSetting(d.id); 

    let obj;
    if(record === null) {
        obj = {}; 
    }else{
        obj = JSON.parse(record);
    }  
    
    // Set attributes 'x' and 'y' and store object
    obj.px = d.px;
    obj.py = d.py;
    obj.x = d.x;
    obj.y = d.y;
    putSetting(d.id, JSON.stringify(obj));
    
    // Check if force may resume
    if(gravity !== "off") {
        force.resume(); 
    }

    if(currentNode == d.id){
        currentNode = null;
    }
/*    setTimeout(function(){    //tick();
        window.d3.select("#container").attr("transform","scale(1)");
    },500); */
}