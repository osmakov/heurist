
/**
* settings.js: Functions to handle the visualisation settings
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
* @todo change storage of settings to user session (instead of current usage of localStorage)
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/* global svg, settings, currentMode, force, visualizeData, circleSize, selectionMode, maxEntityRadius, getEntityRadius,
updateCircles, updateRectangles, tick, maxLinkWidth, getLineWidth, getEntityRadius, getMarkerWidth, updateLabels,
onVisualizeResize */

// Functions to handle the visualisation settings

//localStorage.clear();
/**
* Returns the current displayed URL
* 
*/
function getURL() {
    return window.location.href; 
}

/**
 * Returns a setting from the localStorage
 * @param setting The setting to retrieve
 */
function getSetting(key, defvalue) {
    let value = localStorage.getItem(window.hWin.HAPI4.database+key);
    
    if (   //(isNaN(value) && window.hWin.HEURIST4.util.isNumber(defvalue)) ||   //!isNaN(parseFloat(n)) && isFinite(n)
        (window.hWin.HEURIST4.util.isnull(value) && !window.hWin.HEURIST4.util.isnull(defvalue))){
        value = defvalue;
        putSetting(key, value);
    }
    return value;
}

/**
* Stores a value in the localStorage
*/
function putSetting(key, value) {
    localStorage.setItem(window.hWin.HAPI4.database+key, value);
}

/**
 * This function makes sure the default settings are stored in the localStorage.
 * @param settings The plugin settings object
 */
function checkStoredSettings() {
    getSetting(   'setting_linetype', 'straight'); //settings.linetype    );
    getSetting(   'setting_line_empty_link', 1); //settings.setting_line_empty_link );
    getSetting(   'setting_linelength',    200); //settings.linelength  );
    getSetting(   'setting_linewidth',     2); //settings.linewidth   );
    getSetting(   'setting_linecolor',     'blue'); //settings.linecolor   );
    getSetting(   'setting_markercolor',   settings.markercolor );
    getSetting(   'setting_entityradius',  settings.entityradius);
    getSetting(   'setting_entitycolor',   settings.entitycolor );
    getSetting(   'setting_labels',        settings.labels      );
    getSetting(   'setting_fontsize',      settings.fontsize    );
    getSetting(   'setting_textlength',    settings.textlength  );
    getSetting(   'setting_textcolor',     settings.textcolor   );
    getSetting(   'setting_formula',       settings.formula     );
    getSetting(   'setting_gravity',       settings.gravity     );
    getSetting(   'setting_attraction',    settings.attraction  );
    getSetting(   'setting_fisheye',       settings.fisheye     );
    getSetting(   'setting_translatex',    settings.translatex  );
    getSetting(   'setting_translatey',    settings.translatey  );
    getSetting(   'setting_scale',         settings.scale       );
    getSetting(   'setting_advanced',      settings.advanced    );
}

/**
* This function sets the settings in the UI
*/
function handleSettingsInUI() {
    
    //add elements on toolbar
    let tBar = $('#toolbar');
    
    
    let is_advanced = getSetting('setting_advanced');
    
    $('#setAdvancedMode').css({cursor:'hand'}).on('click',
        function(){
              let is_advanced = getSetting('setting_advanced');
              is_advanced = (is_advanced==='false');
                if(is_advanced){
                    $('.advanced').show();
                    $('#setAdvancedMode').find('a').hide();
                    if(settings.isDatabaseStructure){
                        $('#setDivExport').hide();
                    }
                }else{
                    $('.advanced').hide();
                    $('#setAdvancedMode').find('a').show();
                }
              putSetting('setting_advanced', is_advanced); 
              onVisualizeResize();
        }
    );
    
    if(is_advanced!=='false'){
        $('.advanced').show();
        $('#setAdvancedMode').find('a').hide();
        if(settings.isDatabaseStructure){
            $('#setDivExport').hide();
        }
    }else{
        $('.advanced').hide();
        $('#setAdvancedMode').find('a').show();
    }
    
    //-------------------------------
    
    $('#btnSingleSelect').button({icon:'ui-icon-cursor' , showLabel:false})
        .on('click', function(){ window.selectionMode = 'single'; $("#d3svg").css("cursor", "default"); _syncUI();});
    $('#btnMultipleSelect').button({icon: 'ui-icon-select', showLabel:false})
        .on('click', function(){ window.selectionMode = 'multi'; $("#d3svg").css("cursor", "crosshair"); _syncUI();});
    $('#selectMode').controlgroup();
        
    $('#btnViewModeIcon').button({icon: 'ui-icon-circle' , showLabel:false})
        .on('click', function(){changeViewMode('icons');} );
    $('#btnViewModeInfo').button({icon: 'ui-icon-circle-b-info' , showLabel:false})
        .on('click', function(){changeViewMode('infoboxes');} );
    $('#btnViewModeFull').button({icon: 'ui-icon-circle-info' , showLabel:false})
        .on('click', function(){changeViewMode('infoboxes_full');} );
    $( "#setViewMode" ).controlgroup();    

    $('#gravityMode0').button(/*{icon: 'ui-icon-gravity0' , showLabel:false}*/)
        .on('click', function(){setGravity('off');} );
    $('#gravityMode1').button(/*{icon: 'ui-icon-gravity1' , showLabel:false}*/)
        .on('click', function(){setGravity('touch');} );
    /*$('#gravityMode2').button(/*{icon: 'ui-icon-gravity2' , showLabel:false})
        .on('click', function(){setGravity('aggressive');} );*/
    $("#setGravityMode").controlgroup();    
    
    //------------ NODES ----------
    
    let radius = getSetting('setting_entityradius');
    if(radius<circleSize) radius = circleSize  //min
    else if(radius>maxEntityRadius) radius = maxEntityRadius;
    $('#nodesRadius').val(radius).on('change', function(){
        putSetting('setting_entityradius', $(event.target).val());
        //visualizeData();
        window.d3.selectAll(".node > .background").attr("r", function(d) {
                        return getEntityRadius(d.count);
                    })
    });
    
    //$("input[name='nodesMode'][value='" +getSetting('setting_formula')+ "']").attr("checked", true);
    
    $('#nodesMode0').button().css('width','35px')
        .on('click', function(){ setFormulaMode('linear'); });
    $('#nodesMode1').button().css('width','40px')
        .on('click', function(){ setFormulaMode('logarithmic'); }); 
    $('#nodesMode2').button().css('width','50px')
        .on('click', function(){ setFormulaMode('unweighted'); });
    $( "#setNodesMode" ).controlgroup();    

    if($('#entityColor').length > 0){
        $("#entityColor")
        //.addClass('ui-icon ui-icon-bullet')
        //.css({'font-size':'3.5em','color':getSetting('setting_entitycolor')})
        .val(getSetting('setting_entitycolor'))
        .colorpicker({
                        hideButton: false, //show button right to input
                        showOn: "button",
                        val:getSetting('setting_entitycolor')})
        .on('change.color', function(event, color){
            if(color){
                putSetting('setting_entitycolor', color);
                //$(".background").attr("fill", color);
                updateCircles(".node", null, getSetting('setting_entitycolor'));
                updateRectangles(".node", getSetting('setting_entitycolor'));
                visualizeData();
            }
        });
    }

    //------------ LINKS ----------

    //$("input[name='linksMode'][value='" +getSetting('setting_linetype')+ "']").attr("checked", true);
    
    $('#linksMode0').button({icon: 'ui-icon-link-streight', showLabel:false})
        .on('click', function(){ setLinkMode('straight');} );
    $('#linksMode1').button({icon: 'ui-icon-link-curved', showLabel:false})
        .on('click', function(){ setLinkMode('curved');} );
    $('#linksMode2').button({icon: 'ui-icon-link-stepped', showLabel:false})
        .on('click', function(){ setLinkMode('stepped');} );
        
    $('#linksEmpty').on('change', function(e){
        putSetting('setting_line_empty_link', $(e.target).is(':checked')?1:0);
        visualizeData();
        _syncUI();
    });
	$('#expand-links').on('change', function(){ // expand single links
        tick(); 
	});
    if(settings.isDatabaseStructure){ // show all links by default for database structure vis
        $('#expand-links').prop('checked', true);
    }
        
    $( "#setLinksMode" ).controlgroup();    
    
    putSetting('setting_linecolor', '#0070c0');  //2022-01-01
    setLinkMode('straight'); //2022-01-01
    //_syncUI();

    let linksLength = 200; //2022-01-01 getSetting('setting_linelength', 200);    
    $('#linksLength').val(linksLength).on('change', function(){
        let newval = $(event.target).val();
        putSetting('setting_linelength', newval);
        if(getSetting('setting_gravity') != "off"){
            visualizeData();    
        }
    });
    
    let linksWidth = 2; //2022-01-01 getSetting('setting_linewidth');    
    if(linksWidth<1) linksWidth = 1  //min
    else if(linksWidth>maxLinkWidth) linksWidth = maxLinkWidth;
    
    $('#linksWidth').val(linksWidth).on('change',
    function(){
        let newval = $(event.target).val();
        putSetting('setting_linewidth', newval);
        
        refreshLinesWidth();
    
    });
    
    $("#linksPathColor")
        //.addClass('ui-icon ui-icon-loading-status-circle')
        .css({'font-size':'1.8em','font-weight':'bold','color':getSetting('setting_linecolor')})
        .on('click', function(e){
                window.hWin.HEURIST4.util.stopEvent(e);
                $("#linksPathColor_inpt").colorpicker("showPalette");
        });
        
    $("#linksPathColor_inpt")
        .val('blue')  //getSetting('setting_linecolor')
        .colorpicker({
                        hideButton: true, //show button right to input
                        showOn: "both",
                        val:getSetting('setting_linecolor')})
        .on('change.color', function(event, color){
            if(color){
                putSetting('setting_linecolor', color);
                $(".bottom-lines.link").attr("stroke", color);
                $('#linksPathColor').css('color', color);
                visualizeData();
            }
        });
        
      
    $("#linksMarkerColor")
        .addClass('ui-icon ui-icon-triangle-1-e')
        .css({'color':getSetting('setting_markercolor')})
        .on('click', function(e){
                window.hWin.HEURIST4.util.stopEvent(e);
                $("#linksMarkerColor_inpt").colorpicker("showPalette");
        });
        
    $("#linksMarkerColor_inpt")
        .val(getSetting('setting_markercolor'))
        .colorpicker({
                        hideButton: true, //show button right to input
                        showOn: "focus",
                        val:getSetting('setting_markercolor')})
        .on('change.color', function(event, color){
            if(color){
                putSetting('setting_markercolor', color);
                $("marker").attr("fill", color);
                $('#linksMarkerColor').css('color', color);
                visualizeData();
            }
        });
    
    
    //------------ LABELS ----------
    
    putSetting('setting_labels', 'on'); //always on
    let isLabelVisible = (getSetting('setting_labels', 'on')=='on');
    
    $('#textOnOff').attr('checked',isLabelVisible).on('change', function(){
        
        let newval = $(event.target).is(':checked')?'on':'off';
        putSetting('setting_labels', newval);

        if(window.currentMode=='icons'){
            let isLabelVisible = (newval=='on');
            
            if(isLabelVisible) {
                visualizeData();
            }else{
                window.d3.selectAll(".nodelabel").style('display', 'none');
            }
        }
        // visualizeData();
    });
    
    let textLength = getSetting('setting_textlength', 200);    
    $('#textLength').val(textLength).on('change', function(){
        let newval = $(event.target).val();
        putSetting('setting_textlength', newval);
        let isLabelVisible = (window.currentMode!='icons' || (getSetting('setting_labels', 'on')=='on'));
        if(isLabelVisible) visualizeData();    
    });
    
    
    let fontSize = getSetting('setting_fontsize', 12);    
    if(isNaN(fontSize) || fontSize<8) fontSize = 8  //min
    else if(fontSize>25) fontSize = 25;

    $('#fontSize').val(fontSize).on('change',
    function(){
        let newval = $(event.target).val();
        putSetting('setting_fontsize', newval);
        let isLabelVisible = (window.currentMode!='icons' || (getSetting('setting_labels', 'on')=='on'));
        if(isLabelVisible) visualizeData();    
    });

    if(settings.isDatabaseStructure){
        initRecTypeSelector();    
        $('#setDivExport').hide();
    }else{
        $('#setDivExport').show();
        $('#gephi-export').button();
    }
    
    tBar.show();
}

function initRecTypeSelector(){

    let hidePane = getSetting('startup_rectype_'+window.hWin.HAPI4.database) != 1;

    let layout_options = { 
        applyDefaultStyles: true,
        center:{
            size: $('#main_content').width(),
            contentSelector: '#main_content'
        },
        west:{
            size:400,
            maxWidth:400,
            spacing_open:15,
            spacing_closed:15,  
            togglerAlign_open:40, // button top value
            togglerAlign_closed:40,
            initClosed:true,
            slidable:false,  // disable sliding
            resizable:false, // disable resizing
            contentSelector: '#list_rectypes',
            onopen_end: function(){ 
                $('#list_rectypes').show();
                $('#lblShowRectypeSelector').show();
            },
            onclose_start: function(){
                $('#list_rectypes').hide();
                $('#lblShowRectypeSelector').hide();
            },
            togglerContent_open: '<div class="ui-icon ui-icon-carat-2-w" style="margin-left: 0px;font-size:20px;"></div>',
            togglerContent_closed: '<div class="ui-icon ui-icon-carat-2-e" style="font-size:20px;"></div>'
        }
    };

    let layout = $($('body.popup div.layout-container')[0]).layout(layout_options);
    
    if(!hidePane){ // initClosed option is inconsistent
        setTimeout(function(){
            layout.open('west');
            $('#list_rectypes').show();
            $('#lblShowRectypeSelector').show();
        }, 1000);
    }
}

function _syncUI(){
    $('#toolbar').find('button').removeClass('ui-heurist-btn-header1');
    
    $('#toolbar').find('button[value="'+window.selectionMode+'"]').addClass('ui-heurist-btn-header1');
    $('#toolbar').find('button[value="'+window.currentMode+'"]').addClass('ui-heurist-btn-header1');

    let grv = getSetting('setting_gravity','off');
    if(grv=='agressive') grv = 'touch';
    $('#toolbar').find('button[name="gravityMode"][value="'+grv+'"]').addClass('ui-heurist-btn-header1');
    
    let formula = getSetting('setting_formula','linear')
    $('#toolbar').find('button[name="nodesMode"][value="'+formula+'"]').addClass('ui-heurist-btn-header1');
    
    let linetype = 'straight'; //getSetting(setting_linetype, 'straight');
    $('#toolbar').find('button[name="linksMode"][value="'+linetype+'"]').addClass('ui-heurist-btn-header1');
    
    
    let is_show_empty = (getSetting('setting_line_empty_link', 1)==1);
    $('#toolbar').find('#linksEmpty').prop('checked', is_show_empty);
}

function changeViewMode(mode){
    $(".offset_line").remove();
    if(mode!=window.currentMode){
        if(mode=='infoboxes'){ // && window.currentMode=='icons'
            window.currentMode = 'infoboxes';
            
            window.d3.selectAll(".info-mode").style('display', 'initial');
            window.d3.selectAll(".info-mode-full").style('display', 'none');
            window.d3.selectAll("line.inner_divider").style('display', 'none'); // hide inner line dividers
            
            window.d3.selectAll(".rect-info-full").style('display', 'none');
            window.d3.selectAll(".rect-info").style('display', 'initial');

            window.d3.selectAll("circle.icon-background, circle.icon-foreground, image.node-icon").style('display', 'none');

            window.d3.selectAll("text.nodelabel.namelabel").attr("x", 10);
        }else if(mode=='infoboxes_full'){
            
            window.currentMode = 'infoboxes_full';
            window.d3.selectAll(".info-mode").style('display', 'initial');
            window.d3.selectAll(".info-mode-full").style('display', 'initial');
            window.d3.selectAll("line.inner_divider").style('display', 'initial');

            window.d3.selectAll(".rect-info-full").style('display', 'initial');
            window.d3.selectAll(".rect-info").style('display', 'none');
            
            window.d3.selectAll("circle.icon-background, circle.icon-foreground, image.node-icon").style('display', 'none');

            window.d3.selectAll("text.nodelabel.namelabel").attr("x", 10);
        }else{
            
            window.currentMode = 'icons';
            
            window.d3.selectAll(".info-mode").style('display', 'none');
            window.d3.selectAll(".info-mode-full").style('display', 'none');
            window.d3.selectAll("line.inner_divider").style('display', 'none'); // hide inner line dividers

            window.d3.selectAll("circle.icon-background, circle.icon-foreground, image.node-icon").style('display', 'initial');

            window.d3.selectAll("text.nodelabel.namelabel").attr("x", 29);
        }
        let isLabelVisible = (window.currentMode != 'icons') || (getSetting('setting_labels')=='on');
        window.d3.selectAll(".nodelabel").style('display', isLabelVisible?'block':'none');

        $.each(window.d3.selectAll("image.menu-open")[0], function(idx, ele){

            let event = new MouseEvent("mouseup");
            ele.dispatchEvent(event);
        });

        _syncUI();

        tick();

        updateLabels(); // update labels
    }
}

//
//
//
function setGravity(gravity) {
    
    putSetting('setting_gravity',  gravity);
    
    // Update gravity impact on nodes
    svg.selectAll(".node").attr("fixed", function(d, i) {
        if(gravity == "aggressive") {
            d.fixed = false;
            return false;
        }else{
            d.fixed = true;
            return true;
        }
    });
    
    //visualizeData();

    if(gravity !== "off") {
        force.resume(); 
    }     
    
    _syncUI();
}
//
//
//
function setFormulaMode(formula) {
    putSetting('setting_formula', formula);
    //visualizeData();
    window.d3.selectAll(".node > .background").attr("r", function(d) {
                        return getEntityRadius(d.count);
                    })
    refreshLinesWidth();
    _syncUI();
}

//
//
//
function refreshLinesWidth(){

    window.d3.selectAll(".bottom-lines").style("stroke-width", //thickness);
            function(d) { return getLineWidth(d.targetcount); });

    window.d3.selectAll("marker").attr("markerWidth", function(d) {    
                    return getMarkerWidth(d?d.targetcount:0);             
                })
                .attr("markerHeight", function(d) {
                    return getMarkerWidth(d?d.targetcount:0);
                });
    
}


//
// straight or curverd links type
//
function setLinkMode(formula) {
    putSetting('setting_linetype', formula);
    visualizeData();
    _syncUI();
}
