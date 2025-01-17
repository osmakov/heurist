/**
* app_storymap.js - story map controller widget - it loads storyline and manage
* story viewer (recordList with smarty report output), map and timeline (app_timemap.js)
* 
* It may init timemap internally or use some existing map widget via search_realm
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
/* global layoutMgr, hLayoutMgr */

$.widget( "heurist.app_storymap", {

    // default options
    options: {
        search_realm:  null,  //accepts search/selection events from elements of the same realm only
        tabpanel: false,  //if true located on tabcontrol need top:30

        
        storyFields: [],   // field ids that will be considered as story elements (superseed storyRectypes)
        storyRectypes: [], // ids of rectypes that will be considered as story elements (1414-100 or 9-24 or 3-1006 or similar)
        elementOrder: '', // field ID (dty_ID) to order story elements

        
        // general options
        storyRecordID: null, // record with story elements (it has links to)
        reportOverview: null, // smarty report for overview. It uses storyRecordID
        reportOverviewMapFilter: null, //filter for events/story elements in initial map
        reportEndPage: null, // smarty report for the end page. It uses storyRecordID
        
        reportElement: null,  // smarty report to draw items in resultList
        //story/result list parameters
        reportOverviewMode: 'inline', // tab | header (separate panel on top), no
        reportEndPageMode: 'no', // inline | tab | header (separate panel at bottom)
        reportElementMode: 'vertical', //vertical | slide | tabs
        reportElementDistinct: 'unveil', //none, heighlight, unveil (veil others)
        reportElementSlideEffect: '', 
        reportElementMapMode:'linked', //filtered, all
        reportElementMapFilter:'',
        reportElementCss: null,

        // timemap parameters
        keepCurrentTime: true, //keep current time on story change and load appropriate element
        //NOT USED use_internal_timemap: false, 
        //NOT USED mapDocumentID: null, //map document to be loaded (3-1019)
        
        zoomAnimationTime: 5000 //default value is 5000ms, it can be overwritten by animation parameters per story element
        
        //by default story element loads linked or internal places, or linked map layers
        //if story element has start (1414-1092 or 2-134), transition (1414-1090) and end places (1414-1088 or 2-864) they are preferable
        
        //show/animation parameters per story element
        , mapLayerID: null  // record ids to load on map (3-1020)
        , mapKeep: false    // keep elements on map permanently otherwise it will be unload for next story element
        , markerStyleID: null // record id (2-99) to define style (unless it is defined in map layers)
        , markerStyle: null   // 
        , storyActions: null  // zoom_in, zoom_out, follow_path, ant_path, fade_out, bounce, highlight, show_report
        
        , init_completed: false   //flag to be set to true on full widget initializtion
        
        , onClearStory: null

        , storyPlaceholder: 'Please select a story in the list' // placeholder text
        , blank_placeholder: false // leave placeholder blank
        , elementsPlaceholder: '<br><br>There are no story elements to display for the selected item'
        , elementsPlaceholderSub: '<i>Story elements may exist but not be publicly visible</i>'

        , show_print_button: false // show button to print storymaps
        
        , language: 'def'

        , def_map_symbology: null
        , def_story_symbology: null
    },

    _resultset_main: null, // current all stories
    _resultset: null, // current story element list - story elements
    _resultList: null, 
    _tabs: null, //tabs control for overview/stories/end page
    _mapping: null,    // mapping widget
    _mapping_onselect: null, //mapping event listener
    _L: null, //refrence to leaflet
    
    _all_stories_id: 0, //leaflet layer id with all stories

    _storylayer_id: 0,
    _cache_story_geo: {},
    _cache_story_time: {},
    _cache_story_places: null,
    
    _currentElementID: 0,
    _initialElementID: 0,
    _currentTime: null,
    _nativelayer_id: 0, //current layer for Story Elements
    
    _terminateAnimation: false,
    _animationResolve: null, 
    _animationReject: null,
    
    _initial_div_message:null,
    
    _expected_onScroll_timeout: 0,
    _expected_onScroll: 0,
    
    _timeout_count:0,
    
    _btn_clear_story: null,

    _print_button: null,

    pnlOverview: null,
    pnlStory: null,
    
    // the constructor
    _create: function() {

        let that = this;
        
        this._cache_story_places = {};
        
        let cssOverview = {};
        
        if(this.options.reportOverviewMode=='no' || this.options.reportOverviewMode=='inline' || this.options.reportEndPageMode == 'tabs'){
            cssOverview = {display:'none'};
        }else if(this.options.reportOverviewMode=='header'){
            cssOverview = {height: '200px'};
        }
        let cssEndPage;
        if(this.options.reportEndPageMode=='no' || this.options.reportEndPageMode=='inline' || this.options.reportOverviewMode == 'tabs'){
            cssEndPage = {display:'none'};
        }else if(this.options.reportEndPageMode=='footer'){
            cssEndPage = {height: '100px'};
        }

        if(this.options.elementsPlaceholder == 'def'){
            this.options.elementsPlaceholder = '<br><br>There are no story elements to display for the selected item';
        }
        
        let layout = [{"name":"StoryMap","type":"group","css":{}, //"position":"relative","height":"100%"
            "children":
            [{"name":"TabControl","type": ((this.options.reportOverviewMode=='tab' || this.options.reportEndPageMode=='tab')?"tabs":"group"),
                "css":{},"folder":true,"dom_id":"tabCtrl","children":
                [{"name":top.HR('Overview'),"type":"group","css":cssOverview,"folder":true,
                    "children":[{"name":"Overview content","type":"text","css":{},"content":"","dom_id":"pnlOverview"}]},
                 {"name":top.HR('Story'),"type":"group","css":{},"folder":true,
                        "children":[{"appid":"heurist_resultList","name":"Story list","css":{"position":"relative","minWidth":150}, //"minHeight":400
                            "options":{
                                "select_mode": "none",
                                "support_collection":false,
                                "support_reorder":false,
                                "blog_result_list":false,
                                "recordview_onselect":"no",
                                "rendererExpandInFrame":false,
                                "recordDivClass":"outline_suppress",
                                "show_url_as_link":true,
                                "view_mode":"record_content",
                                "onSelect": function(selected_ids){
                                    //if(recID==that.options.storyRecordID)
                                    if(selected_ids && selected_ids.length>0 
                                    &&that.options.reportOverviewMode=='inline' && that.options.reportElementMode=='tabs'){
                                        that._startNewStoryElement( selected_ids[0] );
                                    }
                                },
                                "rendererExpandDetails": function(recset, recID){
                                    let rep = (recID==that.options.storyRecordID)
                                        ?that.options.reportOverview
                                        :(recID=='0'+that.options.storyRecordID?that.options.reportEndPage
                                         :that.options.reportElement);
                                    if(window.hWin.HEURIST4.util.isempty(rep)){
                                        rep = 'default';
                                    }
                                    return rep;
                                                
                                },
                                "empty_remark": 
                                '<h3 class="not-found" style="color:teal;">'
                                    +  this.options.elementsPlaceholder + '</h3>'
                                    +  this.options.elementsPlaceholderSub,
                                "onScroll": function(event){ that._onScroll(event, that) },
                                "expandDetailsWithoutWarning": true,
                                "show_toolbar":false,
                                /*"show_inner_header":false,
                                "show_counter":false,
                                "show_viewmode":false,*/
                                "show_action_buttons":false,
                                "init_at_once":true,
                                "eventbased": false},
                            "dom_id":"storyList"}]
                    },
                    {"name":top.HR('End page'),"type":"group","css":cssEndPage,"folder":true,
                    "children":[{"name":"End page content","type":"text","css":{},"content":"","dom_id":"pnlEndPage"}]}
                ]
            }]
        }];
        
        if(!layoutMgr) hLayoutMgr();
        layoutMgr.layoutInit(layout, this.element);

        let placeholder = !window.hWin.HEURIST4.util.isempty(this.options.storyPlaceholder) && this.options.storyPlaceholder != 'def' ? 
                            this.options.storyPlaceholder : '';
        placeholder = this.options.storyPlaceholder == 'def' 
            ? '<br><h3 class="not-found" style="color:teal;display:inline-block">Please select a story in the list</h3>' : placeholder;
        
        this._initial_div_message = 
        $(`<div class="ent_wrapper" style="padding: 1em;background: white;">${placeholder}</div>`)
        .appendTo(this.element);
        
        
        //add overview panel
        this.pnlOverview = this.element.find('#pnlOverview');
        
        //add story panel and result list
        this._resultList = this.element.find('#storyList');
        this.pnlStory = this._resultList.parent();

        //add end page panel
        this.pnlEndPage = this.element.find('#pnlEndPage');

        this._resultList.resultList('option', 'language', this.options.language);
        this._resultList.resultList('option', 'allow_record_content_view', true);
        this._resultList.resultList('applyViewMode', 'record_content', true);

        this._tabs = this.element.find('.ui-tabs:first');
        if(this._tabs.length>0 && this._tabs.tabs('instance')){  //TAB VIEW
            
            let h = this.element.find('.ui-tabs-nav:first').height(); //find('#tabCtrl').
            this.pnlOverview.height(this.element.height() - h);
            this._resultList.height(this.element.height() - h); //465
            this.pnlStory.height(this.element.height() - h); //465
            this.pnlEndPage.height(this.element.height() - h);
            
            this._tabs.tabs('option','activate',function(event, ui){
                if(that._resultset && that._resultset.length()>0){
                    
                    if(!(that._currentElementID>0) && that._tabs.tabs('option','active')==1)
                    {
                        if(that.options.reportElementMode=='vertical'){
                            that._addStubSpaceForStoryList();
                        }
                        if(that._initialElementID==0){
                            that._onNavigateStatus(0);
                            that._startNewStoryElement( that._resultset.getOrder()[0] );
                        }
                    }
                    if(that.options.reportElementMode=='tabs'){
                        that._resizeStoryTabPages();
                    }
                }
            });
            
        }else{  //INLINE
            this.pnlStory.css({'position':'absolute',top:0, bottom:'0px', left:0, right:0});
            
            if(this.options.reportOverviewMode=='header'){
                this.pnlOverview.height(cssOverview.height);
                this.pnlStory.css({top:(this.pnlOverview.height()+'px')});
            }else 
            if(this.options.reportOverviewMode=='inline'){
               
                //this._resultList.height('100%');    
            }

            if(this.options.reportEndPageMode=='footer'){
                this.pnlEndPage.height(cssEndPage.height).parent().css({'position': 'relative', 'top': '200px'});
                this.pnlStory.css({bottom:(this.pnlEndPage.height()+'px')});
            }
        }
        
        if(this.options.reportElementMode=='slide')
        {
            this.pnlStoryReport = $('<div>').css({overflow:'auto'})
                .appendTo(this.pnlStory);
                
            let css = ' style="height:28px;line-height:28px;display:inline-block;'
                +'text-decoration:none;text-align: center;font-weight: bold;color:black;'
                
            let navbar = $('<div style="top:2px;right:46px;position:absolute;z-index: 800;border: 2px solid #ccc; background:white;'
                +'background-clip: padding-box;border-radius: 4px;">'
            +'<a id="btn-prev" '+css+'width:30px;border-right: 1px solid #ccc" href="#" '
                +'title="Previous" role="button" aria-label="Previous">&lt;</a>'
            +'<span id="nav-status" '+css+';width:auto;padding:0px 5px;border-right: 1px solid #ccc" href="#" '
                +'>1 of X</span>'
            +'<a id="btn-next" '+css+'width:30px;" href="#" '
                +'title="Next" role="button" aria-label="Next">&gt;</a></div>')        
                .appendTo(this.pnlStory);
                
            this.pnlStoryReport.css({width:'100%',height:'100%'});   
                
            this._on(this.pnlStory.find('#btn-prev'),{click:function(){ this._onNavigate(false); }});    
            this._on(this.pnlStory.find('#btn-next'),{click:function(){ this._onNavigate(true); }});    
                
            this._resultList.hide();
        }else 
        {
            //vertical
            
            if(this.options.reportOverviewMode=='header' || this.options.reportEndPageMode=='footer'){
                this._resultList.css({'position':'absolute',top:0, bottom:0, left:0, right:0});
                //this.pnlOverview.height()+'px')
            }else{
                this._resultList.height('100%');    
            }
            
            
            if(this.options.reportElementMode=='tabs'){
                this._resultList.resultList('applyViewMode', 'tabs', true);
            }else{
                //add pointer to show activate zone for story element switcher (see in onScroll event)
                $('<span>')
                    .addClass('ui-icon ui-icon-triangle-1-e')
                    .css({position:'absolute', top: (this._resultList.position().top+100) + 'px', left:'-4px'})
                    .appendTo(this.pnlStory)
            }
        }
        
        
        /*        
        if(this.options.tabpanel){
            this.framecontent.css('top', 30);
        }else if ($(".header"+that.element.attr('id')).length===0){
            this.framecontent.css('top', 0);
        }*/
        
        //find linked mapping
        if(this.options.map_widget_id){
            this._mapping = $('#'+this.options.map_widget_id);
        }
        
        this._btn_clear_story = $('<button style="position:absolute;top:2px;right:12px;z-index:999;'
        +'border: 2px solid #ccc;background: white;background-clip: padding-box; border-radius: 4px;height: 31px;"'        
        +'">Close</button>')
        .button()
        .hide()
        .insertBefore((this.options.reportOverviewMode=='tab')?this._tabs:this.element.find('#tabCtrl'));
        this._on(this._btn_clear_story, {click:this.clearStory});

        // Print storymap content
        if(this.options.show_print_button){

            this._print_button = $('<button>', {
                text: window.HR('Print'), 
                title: window.hWin.HR('Print current story'),
                class: 'btnPrintStory'
            })
            .button({
                label: window.HR('Print'), 
                icon: 'ui-icon-print', 
                showLabel: false
            })
            .hide()
            .css({position: 'absolute', top: '2px', right: '75px', 'z-index': '999', border: '2px solid rgb(204, 204, 204)', background: 'padding-box white'})
            .insertBefore(this._btn_clear_story);

            this._print_frame = $('<iframe>', {style: 'width:0px;height:0px;'}).insertBefore(this._print_button);

            this._on(this._print_button, {
                click: function(){

                    let content = this._resultList.find('.div-result-list-content').html();

                    let print_doc = this._print_frame[0].contentDocument || this._print_frame[0].contentWindow.document;
                    print_doc = print_doc.document ? print_doc.document : print_doc;

                    print_doc.write('<head><title></title>');
                    print_doc.write('</head><body onload="this.focus(); this.print();">');
                    print_doc.write(content);
                    print_doc.write('</body>');
                    print_doc.close();
                }
            });
        }
        
        if(window.hWin.HEURIST4.util.isempty(this.options.storyPlaceholder) && !this.options.blank_placeholder){
            this.options.storyPlaceholder = 'Please select a story in the list';
        }

        this._initCompleted();
        
    }, //end _create
    
    
    
    //
    // It is called after associated map init
    // it init global listeners
    //
    _initCompleted: function(){
        
        let that = this;
        
        if(this._mapping){
            
            if(this._mapping.length==0){
                this._mapping = $('#'+this.options.map_widget_id);
            }
            
            if(window.hWin.HEURIST4.util.isFunction(this._mapping.app_timemap) && this._mapping.app_timemap('instance')){
                //widget inited
                if(!this._mapping.app_timemap('isMapInited')){
                    this._mapping.app_timemap('option','onMapInit', function(){
                        that._initCompleted();
                    });
                    return;
                }
        
            }else{
                this._timeout_count++;
                if(this._timeout_count<100){
                    setTimeout(function(){ that._initCompleted(); },200);
                    return;
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: 'Mapping widget for story map is not inited properly',
                        error_title: 'Map not initialised',
                        status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                    });
                }
            }
        }
        
        if(this.options.storyRecordID){ //story is fixed and set as an option
            this._checkForStory( this.options.storyRecordID, true );
        }else{
            //take from selected result list
            
            this._events = window.hWin.HAPI4.Event.ON_REC_SELECT
                + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH;                       
            
            $(this.document).on(this._events, function(e, data) {
                
                if(!that._isSameRealm(data)) return;
                
                if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){
                    
                    if(data && data.source!=that.element.attr('id')) {

                        if(data.selection && data.selection.length==1){
                            
                            let recID = data.selection[0];
                        
                            that._checkForStory(recID); //load certain story
                        
                        }
                    }
                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){
                
                    if(!that._initial_div_message){
                        return;
                    }
                    
                    let recset = data.recordset; //record in main result set (for example Persons)

                    let placeholder = (recset.length()>0)?'Please select a story in the list'
                                                         :'No records match the filter criteria'
                    if(!window.hWin.HEURIST4.util.isempty(that.options.storyPlaceholder) && that.options.storyPlaceholder != 'def'){
                        placeholder = that.options.storyPlaceholder;
                    }else{
                        placeholder = `<br><h3 class="not-found" style="color:teal;display:inline-block">${placeholder}</h3>`;
                    }
                    
console.log('on  search finish ', placeholder);
                    if(that._initial_div_message.html()!=placeholder){
                        that._initial_div_message.html(placeholder).show();    
                    }
                    
                    that._resultset_main = recset;
                    
                    //find filtered Story Elements
                    that.updateInitialMap( recset );
                }
                
                
            });
        }

        if(window.hWin.HEURIST4.util.isempty(this.options.def_map_symbology)){

            let storymap_def = {"stroke":"1","color":"#00009b","fill":"1","fillColor":"#0000fa", "fillOpacity":"0.8"}; //blue
            try{
                let mapwidget = this._mapping?.app_timemap('getMapping');
                this.options.def_map_symbology = mapwidget && mapwidget.options.default_style ? mapwidget.options.default_style : storymap_def;
            }catch{
                this.options.def_map_symbology = storymap_def;
            }
        }
        if(window.hWin.HEURIST4.util.isempty(this.options.def_story_symbology)){
            this.options.def_story_symbology = this.options.def_map_symbology;
        }

        this.options.init_completed = true;
    },

    //
    //
    //
    _destroy: function() {

        if(this._events){
            $(this.document).off(this._events);
        }
    },
    
    _setOption: function( key, value ) {
        if(key=='storyRecordID'){
            this.options.storyRecordID = value;
            if(value>0){
                this._checkForStory( this.options.storyRecordID, true );
            }
        }else{
            this._super( key, value );
        }
    },
    
    _setOptions: function() {
        // _super and _superApply handle keeping the right this-context
        if(arguments && ((arguments[0] && arguments[0]['storyRecordID']>0) || arguments['storyRecordID']>0) ){
            this._checkForStory((arguments['storyRecordID']>0)?arguments['storyRecordID']:arguments[0].storyRecordID, true); 
        }else{
            this._superApply( arguments );    
        }
    },

    //
    //
    //
    _isSameRealm: function(data){
        
        if (this.options.search_realm && data && data.search_realm){
            
            if(!Array.isArray(this.options.search_realm)){
                this.options.search_realm = this.options.search_realm.split(',');
            }
            return (this.options.search_realm.indexOf(data.search_realm)>=0);
            
        }else{
            return true; //search realm not defined
        }
    },
    
    //
    // Change current story element - resultList listener
    //     
    _onScroll: function(event, that) {
        
        let ele = $(event.target);
        $.each(ele.find('.recordDiv'), function(i,item){
            let tt = $(item).position().top;
            let h = -($(item).height()-50);
            if(tt>h && tt<50){
                if(!(that._expected_onScroll>0 && that._expected_onScroll!=$(item).attr('recid'))){
                    that._startNewStoryElement( $(item).attr('recid') );
                }
                return false;
            }
        });
    },
    
    //
    // scroll to story element after selection on map
    //
    _scrollToStoryElement: function(recID){
        
        if(this.options.reportOverviewMode=='tab' && this._tabs){
            //switch to Story
            this._tabs.tabs('option', 'active', 1);   
        }
        
        if(this.options.reportElementMode=='vertical'){
            if(this._expected_onScroll_timeout>0){
                clearTimeout(this._expected_onScroll_timeout);
                this._expected_onScroll_timeout = 0;
            }
            
            //scroll result list
            this._expected_onScroll = recID;
            this._resultList.resultList('scrollToRecordDiv', recID, true);
            //sometimes it is called before addition of stub element at the end of list
            if(this._currentElementID!=recID){
                let that = this;
                this._expected_onScroll_timeout =  setTimeout(function(){that._scrollToStoryElement(that._expected_onScroll)},300);
                return;
            }
            this._expected_onScroll = 0;
            
        }else if(this.options.reportElementMode=='tabs'){
            
            this._resultList.resultList('scrollToRecordDiv', recID, true);
            this._startNewStoryElement( recID );
            
        }else
        {
            let order = this._resultset.getOrder();
            let idx = window.hWin.HEURIST4.util.findArrayIndex(recID, order);
        
            this._onNavigateStatus( idx );    
            this._startNewStoryElement( recID );
        }
        
    },
    
    // for slide mode
    // navigate between story elements
    //
    _onNavigate: function(is_forward){
        
        let order = this._resultset.getOrder();
        let recID = 0, idx=-1;
        
        if(this._currentElementID>0){
            idx = window.hWin.HEURIST4.util.findArrayIndex(this._currentElementID, order);
            if(is_forward){
                idx++;
            }else{
                idx--;
            }
        }else if(this._currentElementID == -1){
            idx = order.length - 1;
        }else{
            idx = 0;
        }
        if(idx>=0 && idx<order.length){
            recID = order[idx];
        }
        
        if(recID>0){
            this._startNewStoryElement( recID );    
        }else
        if(this.options.reportEndPageMode=='inline' && idx == order.length){
            //show end page for current story in inline mode
            this.updateEndPagePanel(this.options.storyRecordID);
        }else
        if(this.options.reportOverviewMode=='inline'){
            //show overview for current story in inline mode
            this.updateOverviewPanel(this.options.storyRecordID);
        }
        this._onNavigateStatus(idx);
    },

    // for slide mode
    // enable/disable next/prev buttons
    //
    _onNavigateStatus: function(idx){

        let order = this._resultset.getOrder();
        let dis_next = false, dis_prev = false;
        let len = order.length;
        let is_inline_overview = (this.options.reportOverviewMode=='inline');
        let is_inline_endpage = (this.options.reportEndPageMode=='inline');

        if(idx >= (is_inline_endpage?len:len-1)){
          idx = is_inline_endpage?len:len-1; 
          dis_next = true;      
        } 
        if(idx < (is_inline_overview?0:1)){
            idx = is_inline_overview?-1:0;
            dis_prev = true;      
        }
        
        window.hWin.HEURIST4.util.setDisabled(this.element.find('#btn-prev'), dis_prev );
        window.hWin.HEURIST4.util.setDisabled(this.element.find('#btn-next'), dis_next );
        
        if(is_inline_endpage){
            len ++;
        }
        
        this.element.find('#nav-status').text((idx+1)+' of '+len);
    },

        
    //
    // Loads story elements for given record from server side
    //
    _checkForStory: function(recID, is_forced){
        
        if(this.options.storyRecordID != recID || is_forced){

            let that = this;
            
            this._initial_div_message.hide();
            
            this._currentTime = null;
            
            if(this.options.keepCurrentTime && this.options.storyRecordID>0 && this._currentElementID>0){
                
                if(this._cache_story_time && this._cache_story_time[this.options.storyRecordID]){

                    let story_time = this._cache_story_time[this.options.storyRecordID];
                    $.each(story_time, function(i,item){
                        if(item.rec_ID == that._currentElementID){
                            that._currentTime = item.when[0];
                        } 
                    });
                }
            }
            
            
            this.options.storyRecordID = recID;
        
            if(!Array.isArray(this.options.storyFields) && typeof this.options.storyFields === 'string'){
                this.options.storyFields = this.options.storyFields.split(',');
            }
            if(!Array.isArray(this.options.storyRectypes) && typeof this.options.storyRectypes === 'string'){ //NOT USED
                this.options.storyRectypes = this.options.storyRectypes.split(',');
            }
            
            let request;
            
            const DT_STORY_ANIMATION = $Db.getLocalID('dty', '2-1090'); //configuration field for animation and style
            const DT_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_DATE'];     //9
            const DT_START_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_START_DATE']; //10
            const DT_END_DATE = window.hWin.HAPI4.sysinfo['dbconst']['DT_END_DATE']; //11

            if(this.options.storyFields.length>0){
                //search for story fields for given record
                request = {q:{ids:this.options.storyRecordID}, detail:this.options.storyFields.join(',')};

                window.hWin.HAPI4.RecordMgr.search(request,
                    function(response) {
                        if(response.status == window.hWin.ResponseStatus.OK){
                            
                            let details = response.data.records[recID]['d'];
                            let recIDs = [];
                            for(let dty_ID in details){
                                if(dty_ID>0){
                                    recIDs = recIDs.concat(details[dty_ID]);
                                }
                            }
                            
                            if(recIDs.length>0){
                            
                                recIDs = recIDs.join(',');
                                
                                //returns story elements in exact order
                                let request = {q:[{ids:recIDs},{sort:('set:'+recIDs)}]};
                                
                                let detail_fields = [];
                                if(that.options.elementOrder=='def'){
                                    detail_fields = [DT_DATE,DT_START_DATE,DT_END_DATE];
                                }else if(that.options.elementOrder){
                                    detail_fields = [that.options.elementOrder];
                                }
                                detail_fields.push(DT_STORY_ANIMATION);
                                
                                request['detail'] = detail_fields;
                                
                                if(that.options.storyRectypes.length>0){
                                    request['q'].push({t:that.options.storyRectypes.join(',')});
                                }
                                
                                window.hWin.HAPI4.RecordMgr.search(request,
                                    function(response) {
                                        that._resultset = new HRecordSet(response.data);
                                        
                                        //sort
                                        if(that.options.elementOrder){
                                            let sortFields = {};
                                            if(that.options.elementOrder=='def'){
                                                
                                                that._resultset.each(function(recID, record){
                                                    let dt_st = that._resultset.fld(record, DT_START_DATE);
                                                    let dt_end = that._resultset.fld(record, DT_END_DATE);
                                                    if(!dt_st){
                                                        dt_st = that._resultset.fld(record, DT_DATE);
                                                    }
                                                    let dres = window.hWin.HEURIST4.util.parseDates(dt_st, dt_end);
                                                    if(window.hWin.HEURIST4.util.isArrayNotEmpty(dres) && dres.length==2){
                                                        that._resultset.setFld(record, DT_START_DATE, dres[0]);
                                                        that._resultset.setFld(record, DT_END_DATE, dres[1]);
                                                    }
                                                });        
                                                
                                               
                                               
                                                sortFields[DT_START_DATE] = 1;
                                                sortFields[DT_END_DATE] = 1;
                                            }else{
                                                sortFields[that.options.elementOrder] = 1;
                                            }
                                            
                                            that._resultset.sort(sortFields);
                                        }
                                        
                                        
                                        that._startNewStory(recID);        
                                    });
                            }else{
                                that._resultset = new HRecordSet();
                                that._startNewStory(recID);
                            }
                            
                        }else{
                            that._resultset = null;
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    }
                );
            /*
            }else if (this.options.storyRectypes.length>0){
                //search for linked records
                request = {q:[{t:this.options.storyRectypes.join(',')},{lf:recID}], detail:'header'};

                window.hWin.HAPI4.RecordMgr.search(request,
                    function(response) {
                        if(response.status == window.hWin.ResponseStatus.OK){
                            that._resultset = new HRecordSet(response.data);
                            that._startNewStory(recID);
                            
                        }else{
                            that._resultset = null;
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    }
                );
                
            */    
            }else{
                //show warning on overview panel
                this.pnlOverview.html(top.HR('No story fields defined'));
                this.pnlEndPage.html('');
            }
                
        }
    },
    
    //
    //
    //
    _stopAnimeAndClearMap: function(){

        if(this._mapping){
            //stop animation        
            if(this._animationResolve!=null){ //animation active
                this._terminateAnimation = this._currentElementID>0?this._currentElementID:true;
            }
            
            //clear map
            if(this._nativelayer_id>0 && this._mapping.app_timemap('instance')){
                let mapwidget = this._mapping.app_timemap('getMapping');
                
                mapwidget.removeLayer( this._nativelayer_id );
                this._nativelayer_id = -1;
            }
        }
        
        this._currentElementID = 0;
    },
    
    //
    //
    //
    clearStory: function( trigger_event ){
        
        //remove previous story layer
        if(this._mapping){
            let mapwidget = this._mapping.app_timemap('getMapping');
            if(this._all_stories_id>0){
                mapwidget.removeLayer( this._all_stories_id );
                this._all_stories_id = 0;
            }
            if(this._storylayer_id>0){
                mapwidget.removeLayer( this._storylayer_id );
                this._storylayer_id = 0;
            }
        }
        this._stopAnimeAndClearMap();

        //switch to Overview
        if(this._tabs){
            this._tabs.tabs('option', 'active', 0);   
        }
        if(this.options.reportElementMode!='slide'){   
            this._resultList.resultList('clearAllRecordDivs');
        }
        
        this.pnlOverview.html('');
        this.pnlEndPage.html('');        
        
        this.options.storyRecordID = null;

        this._btn_clear_story.hide();
        if(this.options.show_print_button){
            this._print_button.hide();
        }

        if(this.options.reportOverviewMode=='tab' || this.options.reportEndPageMode=='tab') this._tabs.hide(); else this.element.find('#tabCtrl').hide();
        
        if(trigger_event !== false && window.hWin.HEURIST4.util.isFunction(this.options.onClearStory)){
            this.options.onClearStory.call(this);
        }
    },
    
    //
    //
    //
    _resizeStoryTabPages: function(){
        if(this.options.reportElementMode=='tabs'){  
                let div_content = this._resultList.find('.div-result-list-content');
                if(div_content.tabs('instance')){
                    try{
                        div_content.tabs('pagingResize');
                    }catch(ex){
                        /* continue regardless of error */
                    }
                }
        }
    },
    
    
    // 1. Loads all story elements time data
    // 2. loads list of story elements (this._resultset) into reulst list
    // 3. Render overview as smarty report or renderRecordData
    // 4. Render end page as smarty report or renderRecordData
    //
    _startNewStory: function(recID){
    
        if(this.options.storyRecordID != recID) return; //story already changed

        this.clearStory( false );
        
        this.options.storyRecordID = recID;
        
        if(this.options.reportOverviewMode=='tab') this._tabs.show(); else this.element.find('#tabCtrl').show();
        
        //loads list of story elements into reulst list
        if(this.options.reportElementMode=='vertical' || this.options.reportElementMode=='tabs'){   

            if(this.options.reportOverviewMode=='inline' && this.options.reportElementMode=='tabs'){
                //show overview for current story in inline mode
                this._resultset.addRecord(recID, {rec_ID:recID, rec_Title:'Overview'}, true);
            }
            
            if(this.options.reportEndPageMode=='inline' && this.options.reportElementMode=='tabs'){
                //show end page for current story in inline mode
                this._resultset.addRecord('0' + recID, {rec_ID:recID, rec_Title:'End page'}, false);
            }

            this._resultList.resultList('updateResultSet', this._resultset);
            //add last stub element to allow proper onScroll event for last story element
            if(this.options.reportElementMode=='vertical'){
                this._addStubSpaceForStoryList( 2000 );    
            }else{
                this._resizeStoryTabPages();
            }
        }
        
        if(this._resultset && this._resultset.length()>0){
            
            //1. Render overview panel
            this.updateOverviewPanel(recID);
            
            //2. Loads time data for all story elements - into special layer "Whole Store Timeline"
            this.updateTimeLine(recID);
            
            //3. Render end page panel
            if(!(this.options.reportEndPageMode == 'inline' && this.options.reportElementMode == 'slide')){
                this.updateEndPagePanel(recID);
            }
        }else{
            //clear 
           
            this.pnlOverview.html(
            '<h3 class="not-found" style="color:teal;">'
            +  this.options.elementsPlaceholder + '</h3>'
            +  this.options.elementsPlaceholderSub);
        }
        
        if(this._btn_clear_story){
            if(this.options.reportOverviewMode=='inline' && this.options.reportElementMode!='vertical'){
                this._btn_clear_story.button({icon:'ui-icon-circle-b-close', showLabel:false});
            }else{
                this._btn_clear_story.button({label:top.HR('Close'),showLabel:true,icon:null});
            }
            this._btn_clear_story.show();  
        } 
        if(this.options.show_print_button){
            this._print_button.show();
        }
        
    },
    
    //
    // add last stub element to allow proper onScroll event for last story element
    //
    _addStubSpaceForStoryList: function( delay ){
        
        if(this._resultList.is(':visible')){
            
            if(!(delay>0)) delay = 10; 
            
            let rh = this._resultList.height();
            let rdiv = this._resultList.find('.recordDiv');
            rdiv.css('padding','20px 0px');
            if(rdiv.length>1){
                let rdiv0 = $(rdiv[0]);
                rdiv = $(rdiv[rdiv.length-1]);
                let that = this;
                setTimeout(function(){ 
                    if(rdiv0.height() < 101){
                        rdiv0.css({'min-height':'100px'});
                    }
                    let stub_height = (rdiv.height() < rh-100)? rh-100 :0;
                    
                    that._resultList.find('.div-result-list-content').find('.stub_space').remove();
                    if(stub_height>0){
                        $('<div>').addClass('stub_space').css({'min-height':stub_height+'px'})
                                .appendTo(that._resultList.find('.div-result-list-content'));
                    }
                    
                }, delay);
            }
        }
    },

    //
    // Loads Overview info (called after loading of story)
    //
    updateOverviewPanel: function(recID){

            let infoURL;
            let isSmarty = false;
            
            if( typeof this.options.reportOverview === 'string' 
                            && this.options.reportOverview.substr(-4)=='.tpl' ){
            
                infoURL = window.hWin.HAPI4.baseURL + '?snippet=1&q=ids:'
                        + recID 
                        + '&db='+window.hWin.HAPI4.database+'&template='
                        + encodeURIComponent(this.options.reportOverview);
                        
                isSmarty = true;
            }else{
                infoURL = window.hWin.HAPI4.baseURL + 'viewers/record/renderRecordData.php?mapPopup=1&recID='  // mapPopup=1 returns html snippet
                        +recID
                        +'&db='+window.hWin.HAPI4.database;
            }
            
            if(this.options.language && this.options.language!='def'){
                    infoURL = infoURL + '&lang='+this.options.language;
            }
            
            //reportOverviewMode: inline | tab | header | no
            //reportElementMode: vertical | slide | tab
            
            if (!((this.options.reportOverviewMode=='no') ||
              (this.options.reportOverviewMode=='inline' && this.options.reportElementMode=='tabs')))
            { //inline, tab, header
                let that = this;
                this.pnlOverview.addClass('loading').css({'overflow-y':'auto'})
                    .load(infoURL, function(){ 
                        
                        let ele2 = $(this);
                        ele2.removeClass('loading').css('min-height','200px');//.height('auto');    

                        if(ele2.find('div[data-recid]').length>0){ //for standard view
                            ele2.find('div[data-recid]')[0].style = null;
                        }
                            
                        if(that.options.reportOverviewMode=='inline'){
                            //loads overview as first element in story list
                            
                            if(that.options.reportElementMode=='slide'){ //as first slide     
                                that._currentElementID = 0;
                                that._onNavigateStatus( -1 );    
                                that.pnlStoryReport.html(that.pnlOverview.html())
                            }else
                            if(that.options.reportElementMode!='tabs'){

                                let ele = that._resultList.find('.div-result-list-content');    
                                $('<div class="recordDiv outline_suppress expanded" recid="0" tabindex="0">')
                                    .html(that.pnlOverview.html()).prependTo(ele);
                            }
                            
                        }else if(that.options.reportOverviewMode=='header'){
                            
                            if(that._initialElementID==0){
                                that._onNavigateStatus( 0 );    
                                that._startNewStoryElement( that._resultset.getOrder()[0] );
                            }
                                
                        }else{
                            //tab
                            let h = ele2[0].scrollHeight+10;
                            if(ele2.find('div[data-recid]').length>0){
                                ele2.find('div[data-recid]').css('max-height','100%');
                            }
                        }

                        if(that._initialElementID>0){
                            that._scrollToStoryElement( that._initialElementID );
                            that._initialElementID = 0;   
                        }
                    });   
                    
            }else 
            {
                if(this._initialElementID>0){
                    this._scrollToStoryElement( this._initialElementID );
                    this._initialElementID = 0;   
                }else{
                    this._onNavigateStatus( 0 );    
                    this._startNewStoryElement( this._resultset.getOrder()[0] );
                }
            }
                        
            
    },

    //
    // Loads End page info (called after loading of story)
    //
    updateEndPagePanel: function(recID){

        let infoURL;
        
        if( typeof this.options.reportEndPage === 'string' 
                        && this.options.reportEndPage.substr(-4)=='.tpl' ){
        
            infoURL = window.hWin.HAPI4.baseURL + '?snippet=1&q=ids:'
                    + recID 
                    + '&db='+window.hWin.HAPI4.database+'&template='
                    + encodeURIComponent(this.options.reportEndPage);
                    
        }else{
            infoURL = window.hWin.HAPI4.baseURL + 'viewers/record/renderRecordData.php?mapPopup=1&recID='  //mapPopup=1 returns html snippet
                    +recID
                    +'&db='+window.hWin.HAPI4.database;
        }
        if(this.options.language && this.options.language!='def'){
            infoURL = infoURL + '&lang='+this.options.language;
        }
                    
        
        //reportEndPageMode: inline | tab | footer | no
        //reportElementMode: vertical | slide | tab
        
        if (!((this.options.reportEndPageMode=='no') || (this.options.reportOverviewMode=='inline' && this.options.reportElementMode=='tabs')))
        { //inline, tab, footer
            let that = this;
            this.pnlEndPage.addClass('loading').css({'overflow-y':'auto'})
                .load(infoURL, function(){ 
                    
                    let ele2 = $(this);
                    ele2.removeClass('loading');//.height('auto');    

                    if(that.options.reportEndPageMode == 'footer'){
                        ele2.css('min-height','100px');
                    }else{
                        ele2.css('min-height','200px');
                    }

                    if(ele2.find('div[data-recid]').length>0){ //for standard view
                        ele2.find('div[data-recid]')[0].style = null;
                    }
                        
                    if(that.options.reportEndPageMode=='inline'){
                        //loads end page as first element in story list
                        const is_tabs_enabled = false;
                        
                        if(that.options.reportElementMode=='slide'){ //as last slide
                            that._currentElementID = -1;
                            that._onNavigateStatus( that._resultset.getOrder().length );    
                            that.pnlStoryReport.html(that.pnlEndPage.html())
                        }else
                        if(is_tabs_enabled){// && that.options.reportElementMode=='tabs'
                            // Works, but would be better if part of _resultset
                            let $tabs = that._resultList.find('.div-result-list-content.tabs');
                            if($tabs.length == 0 || $tabs.tabs('instance') === undefined){
                                return;
                            }

                            let $tabs_nav = $tabs.find('.ui-tabs-nav');
                            if($tabs_nav.find('#end_page').length != 0){
                                return;
                            }

                            $('<li><a href="#end_page" class="truncate" style="max-width: 20ex; width: auto; margin-right: 20px; font-size: 1em; line-height: 1.5em;">End page</a></li>')
                                .appendTo($tabs_nav);

                            $('<div id="end_page" recid="0">').html(that.pnlEndPage.html()).appendTo($tabs); //recid = 0, to 'close' currently open record, resultList.js [func __loadTabContent]

                            $tabs.tabs('refresh');
                        }else{

                            let $ele = that._resultList.find('.recordDiv');
                            let $div = $('<div class="recordDiv outline_suppress expanded" recid="0" tabindex="0">').html(that.pnlEndPage.html());

                            if($ele.length == 0 && that._resultList.find('.stub_space').length == 0){
                                $ele = that._resultList.find('.div-result-list-content');
                            }

                            if($ele.length > 0){
                                $div.insertAfter($ele.last());
                            }else if(that._resultList.find('.stub_space').length > 0){
                                $ele = that._resultList.find('.stub_space');
                                $div.insertBefore($ele);
                            }else{
                                // something has gone wrong
                            }
                        }
                        
                    }else if(that.options.reportEndPageMode=='footer'){
                        
                        /*if(that._initialElementID==0){
                            that._onNavigateStatus( 0 );    
                            that._startNewStoryElement( that._resultset.getOrder()[0] );
                        }*/
                            
                    }else{
                        //tab
                        let h = ele2[0].scrollHeight+10;
                        if(ele2.find('div[data-recid]').length>0){
                            ele2.find('div[data-recid]').css('max-height','100%');
                        }
                    }

                    if(that._initialElementID>0){
                        that._scrollToStoryElement( that._initialElementID );
                        that._initialElementID = 0;   
                    } 
                });   
                
        }else 
        {
            if(this._initialElementID>0){
                this._scrollToStoryElement( this._initialElementID );
                this._initialElementID = 0;   
            }else{
                let idx = this._resultset.getOrder().length-1;
                this._onNavigateStatus( this._resultset.getOrder().length );    
                this._startNewStoryElement( this.options.storyRecordID );
            }
        }
    },

    //
    // show all elements on map for initial state
    //        
    updateInitialMap: function( recset ){
        
        if(!this._mapping) return; //there is not associated map widget

        let that = this;
        
        let mapwidget = this._mapping.app_timemap('getMapping');
        
        if(!this._mapping_onselect){ //assign event listener
            
            this._mapping_onselect = function( rec_ids ){
                /*                            
                if(that._all_stories_id>0){
                    //initial map is loaded
                    $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                        {selection:rec_ids, source:that.element.attr('id'), 
                            search_realm:that.options.search_realm} ); //highlight in main resultset
                    that._checkForStory(rec_ids[0]); //load first story
                }else*/

                if(rec_ids.length>0){
                    //find selected record id among stories
                    let rec = that._resultset_main.getById(rec_ids[0]);
                    if(rec){
                        if(that.options.storyRecordID != rec_ids[0]){
                            that._checkForStory(rec_ids[0]); //load first story
                            $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                                {selection:[rec_ids[0]], source:that.element.attr('id'), 
                                    search_realm:that.options.search_realm} ); //highlight in main resultset
                        }
                    }else{
                        //find selected record id among story elements    
                        rec = that._resultset.getById(rec_ids[0]);
                        if(rec){
                            that._scrollToStoryElement(rec_ids[0]); //scroll to selected story element
                        }    
                    }
                }
                
            }
            mapwidget.options.onselect = this._mapping_onselect;
        }
        
        //clear map
        if(this._all_stories_id>0){
            mapwidget.removeLayer( this._all_stories_id );
            this._all_stories_id = 0;
        }else if(this._storylayer_id>0){
            mapwidget.removeLayer( this._storylayer_id );
            this._storylayer_id = 0;
        }else{
            this._stopAnimeAndClearMap();
        }
        
        if(recset.length()==0) return;
        
        if(recset.length()==1){
            const selids = recset.getIds();
            if(that.options.storyRecordID != selids[0]){
                that._checkForStory(selids[0]); //load certain story
                //select the only story at once
                $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                    {selection:selids, source:that.element.attr('id'), 
                        search_realm:that.options.search_realm} );
            }
            return;            
        }
        
        //Find story elements ids
        if(!Array.isArray(this.options.storyFields) && typeof this.options.storyFields === 'string'){
            this.options.storyFields = this.options.storyFields.split(',');
        }
        
        //---------
        // find all story elements
        let request = {q:{ids:recset.getIds()}, detail:this.options.storyFields.join(',')};

        window.hWin.HAPI4.RecordMgr.search(request,
            function(response) {
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    let storyIDs = [];
                    let storiesByRecord = {};
                    for (let recID in response.data.records){
                        if(recID>0){
                            let details = response.data.records[recID]['d'];
                            storiesByRecord[recID] = [];
                            for(let dty_ID in details){
                                if(dty_ID>0){
                                    storyIDs = storyIDs.concat(details[dty_ID]);
                                    storiesByRecord[recID] = storiesByRecord[recID].concat(details[dty_ID]);
                                }
                            }
                        }
                    }//for
                    
                    //find filtered stories
                    let query = [{ids:storyIDs}];

                    if( !window.hWin.HEURIST4.util.isempty(that.options.reportOverviewMapFilter)){
                        query = window.hWin.HEURIST4.query.mergeTwoHeuristQueries( query, that.options.reportOverviewMapFilter );    
                    }
                    
        
                    let server_request = {
                                    q: query, 
                                    leaflet: 1, 
                                    simplify: 1, //simplify paths with more than 1000 vertices
                                    //suppress_linked_places: 1, //do not load assosiated places
                                    zip: 1,
                                    format:'geojson'};
                                    
                    window.hWin.HAPI4.RecordMgr.search_new(server_request,
                        function(response){
                            let geojson_data = null
                            if(response['geojson']){
                                geojson_data = response['geojson'];
                            }else{
                                geojson_data = response;
                            }
                            //REPLACE rec id and title to main result set
                            function __findMainId(storyID){
                                let rec = null;
                                recset.each2(function(recID, record){
                                    if(recID>0 && storiesByRecord[recID]){
                                        let idx = window.hWin.HEURIST4.util.findArrayIndex(storyID, storiesByRecord[recID]);
                                        if(idx>=0){
                                            rec = record;
                                            return false;
                                        }
                                    }
                                });
                                return rec;
                            }
                            
                            if( !window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true) ){
                                geojson_data = null;
                            }else{
                                
                                for(let i=0; i<geojson_data.length; i++){
                                    const storyID = geojson_data[i].id
                                    let record  = __findMainId(storyID);
                                    if(record){
                                        geojson_data[i].id = record['rec_ID'];
                                        geojson_data[i]['properties'].rec_ID = record['rec_ID']; 
                                        geojson_data[i]['properties'].rec_RecTypeID = record['rec_RecTypeID']; 
                                        geojson_data[i]['properties'].rec_Title = record['rec_Title']; 
                                    }
                                }
                            }

                            if(response['timeline']){
                                let aused = [];
                                for(let i=0; i<response['timeline'].length; i++){
                                    const storyID = response['timeline'][i]['rec_ID'];
                                    //find original resulet set it
                                    let record  = __findMainId(storyID);
                                    if(record && aused.indexOf(record['rec_ID'])<0){
                                        aused.push(record['rec_ID']);
                                        response['timeline'][i]['rec_ID'] = record['rec_ID'];
                                        response['timeline'][i]['rec_RecTypeID'] = record['rec_RecTypeID'];
                                        response['timeline'][i]['rec_Title'] = record['rec_Title'];
                                    }
                                }
                            }


                            
                            that._all_stories_id = mapwidget.addGeoJson(
                                {geojson_data: geojson_data,
                                 timeline_data: response['timeline'],
                                    //layer_style: layer_style,
                                    //popup_template: layer_popup_template,
                                 dataset_name: 'All Stories',
                                 preserveViewport: false });
                        });                    
                    
                    
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
                
            });
        //----------
    },


    //
    // update Whole store timeline
    //
    updateTimeLine: function(recID){
        
        let that = this;
        
        if(this.options.storyRecordID != recID) return; //story already changed to different one
        
        if(!this._mapping) return; //there is not associated map widget
        
        if(that._cache_story_geo[that.options.storyRecordID]=='no data'){
            //no time data for this story
            
        }else if(that._cache_story_geo[that.options.storyRecordID] 
                || that._cache_story_time[that.options.storyRecordID]) 
        {
            // loads from cache
            
            if(!(this._mapping.app_timemap('instance') && this._mapping.app_timemap('isMapInited'))){
                   
                /*    
                this._mapping.app_timemap('option','onMapInit', function(){
                    that.updateTimeLine(recID);
                });
                */
                return;
            }
            
            //update timeline
            let mapwidget = this._mapping.app_timemap('getMapping');
            mapwidget.isMarkerClusterEnabled = false;
            
            this._storylayer_id = mapwidget.addGeoJson(
                {geojson_data: that._currentElementID>0?null:that._cache_story_geo[that.options.storyRecordID], //story element is loaded already
                 timeline_data: that._cache_story_time[that.options.storyRecordID],
                    //popup_template: layer_popup_template,
                    layer_style:that.options.def_map_symbology,
                    
                 selectable: false,
                 dataset_name: 'Story Timeline',
                 preserveViewport: false });
                
            //
            //     
            if(this._currentTime!=null){

                let start0 = that._currentTime[0];
                let end0 = that._currentTime[3] ?that._currentTime[3] :start0;
                
                $.each(that._cache_story_time[that.options.storyRecordID],function(i,item){
               
                    if(item.when && item.when[0]){
                    
                        let start = item.when[0][0];
                        let end = item.when[0][3] ?item.when[0][3] :start;
                    
                        //intersection
                        let res = false;
                        if(start == end){
                            res = (start>=start0 && start<=end0);
                        }else{
                            res = (start==start0) || 
                                (start > start0 ? start <= end0 : start0 <= end);
                        }                    
                        
                        if(res){
                            that._initialElementID = item.rec_ID;
                            //open story element
                           
                            return false;                        
                        }
                    }
                });
                
            }     
                 
            
        }else{
            
            let server_request = {
                            q: {ids: this._resultset.getIds()}, //list of story elements/events
                            leaflet: 1, 
                            simplify: 1, //simplify paths with more than 1000 vertices
                            //suppress_linked_places: 1, //do not load assosiated places
                            zip: 1,
                            format:'geojson'};
            window.hWin.HAPI4.RecordMgr.search_new(server_request,
                function(response){
                    let geojson_data = null
                    if(response['geojson']){
                        geojson_data = response['geojson'];
                    }else{
                        geojson_data = response;
                    }
                    if( window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true) ){
                        that._cache_story_geo[recID] = geojson_data;
                    }else{
                        that._cache_story_geo[recID] = null;
                    }
                    
                    if(response['timeline']){
                        that._cache_story_time[recID] = response['timeline']; //timeline data
                    }else {
                        that._cache_story_time[recID] = null;
                    }   
                    
                    if(!(that._cache_story_geo[recID] 
                        || that._cache_story_time[recID])){
                        that._cache_story_geo[recID] = 'no data';     
                    }
                    
                    that.updateTimeLine(recID);
                });
        }
    },

    //
    // 1. Loads story for slide (if reportElementMode=='slide')
    // 2. Executes animation on map
    // recID - story element
    //
    _startNewStoryElement: function(recID){

        if(this._currentElementID != recID){
          
            if(this._storylayer_id>0){
                let mapwidget = this._mapping.app_timemap('getMapping');
                mapwidget.setLayerVisibility(this._storylayer_id, false);
            }
            
            
            this._stopAnimeAndClearMap();

            this._currentElementID = recID;
            
            if(this.options.reportElementMode=='slide'){   //one by one   

                
                let infoURL;
                let isSmarty = false;
                
                if( typeof this.options.reportElement === 'string' 
                                && this.options.reportElement.substr(-4)=='.tpl' ){
                
                    infoURL = window.hWin.HAPI4.baseURL + '?snippet=1&q=ids:'
                            + recID 
                            + '&db='+window.hWin.HAPI4.database+'&template='
                            + encodeURIComponent(this.options.reportElement);
                    isSmarty = true;
                }else{
                    infoURL = window.hWin.HAPI4.baseURL + 'viewers/record/renderRecordData.php?recID='  //mapPopup=1&
                            +recID
                            +'&db='+window.hWin.HAPI4.database;
                }

                if(this.options.language && this.options.language!='def'){
                    infoURL = infoURL + '&lang='+this.options.language;
                }
                
                
                let that = this;

                function __load_content(){
                    that.pnlStoryReport.addClass('loading').css({'overflow-y':'auto'})
                        .load(infoURL, function(){ 
                            
                            
                            let ele2 = $(this);
                            ele2.removeClass('loading').css('min-height','200px');//.height('auto');    

                            if(ele2.find('div[data-recid]').length>0){ //for standard view
                                ele2.find('div[data-recid]')[0].style = null;
                            }
                            
                            ele2.find('img').each(function(i,img){window.hWin.HEURIST4.util.restoreRelativeURL(img);});
                            
                        });
                }
                
                if(this.options.reportElementSlideEffect && 
                    this.options.reportElementSlideEffect!='none' &&
                    !this.pnlStoryReport.is(':empty'))
                {
                    this.pnlStoryReport.effect( this.options.reportElementSlideEffect, {}, 1000, function(){
                            that.pnlStoryReport.empty().show();
                            __load_content();
                    } );
                }else{
                    __load_content();
                }
                
                
            }
            else {
            
                if(this.options.reportElementDistinct=='highlight'){
                    this._resultList.find('.recordDiv').removeClass('selected');
                    this._resultList.find('.recordDiv[recid='+recID+']').addClass('selected');
                }else if(this.options.reportElementDistinct=='unveil'){
                    
                    $.each(this._resultList.find('.recordDiv'),function(i,item){
                        if($(item).attr('recid')==recID){
                            $(item).find('.veiled').remove();   
                        }else if($(item).find('.veiled').length==0){
                            $('<div>').addClass('veiled').appendTo($(item));
                        }
                    });
                }
                
            }
            
            if(this._mapping && this._mapping.length>0){
               
                if(recID==0 || Number.parseInt(recID)==this.options.storyRecordID){
                    //zoom for entire story
                    
                    if(this._mapping){
                        let mapwidget = this._mapping.app_timemap('getMapping');
                        mapwidget.setLayerVisibility(this._storylayer_id, true);
                        mapwidget.zoomToLayer(this._storylayer_id );
                    }
                    
                }else{
                    this._animateStoryElement_B(recID);    
                }
            

            }  
        }
    },
    
    //
    // Every place is separate object on map - animate sequence - begin, transition, end places
    // 1. find all resource (record pointer) fields that points to places
    // 2. retrieve all places from server side as geojson
    // 3. create links between points
    // 4. update map
    // 5. execute animation
    _animateStoryElement_B: function(recID){

        let that = this;

        if ( that._cache_story_places[recID] ){ //cache is loaded already
            
            let pl = that._cache_story_places[recID]['places'];
            if( pl.length==0){
                //no geodata, zoom to story element on timeline
                let mapwidget = that._mapping.app_timemap('getMapping');
                mapwidget.vistimeline.timeline('zoomToSelection', [recID]); //select and zoom 
            }else{
                //map is already cleared in _startNewStoryElement
                that._animateStoryElement_B_step2(recID);
            }
            return;    
        }

        let request = {q: 'ids:'+recID, detail:'detail'};

        window.hWin.HAPI4.RecordMgr.search(request,
            function(response){

                if(response.status == window.hWin.ResponseStatus.OK){
                    
                   
                    // 1. find all resource (record pointer) fields that points to places               
                    that._cache_story_places[recID] = {};
                    that._cache_story_places[recID]['places'] = [];
                    const RT_PLACE  = window.hWin.HAPI4.sysinfo['dbconst']['RT_PLACE'];
                    if(response.data.count==1){
                        let details = response.data.records[recID]['d'];
                        let dty_ID;   
                        for(dty_ID in details){
                            let field = $Db.dty(dty_ID);
                            if(field['dty_Type']=='resource'){
                                let ptr = field['dty_PtrTargetRectypeIDs'];
                                if(ptr && window.hWin.HEURIST4.util.findArrayIndex(RT_PLACE, ptr.split(','))>=0){
                                    that._cache_story_places[recID][dty_ID] = details[dty_ID];   
                                } 
                            } 
                        }
                        //concatenate all places in proper order
                        // Begin '2-134', Transition '1414-1090', End '2-864'
                        let DT_BEGIN_PLACES = $Db.getLocalID('dty', '2-134');
                        let DT_BEGIN_PLACES2 = $Db.getLocalID('dty', '1414-1092');
                        let DT_END_PLACES = $Db.getLocalID('dty', '2-864');
                        if(DT_BEGIN_PLACES>0 && that._cache_story_places[recID][DT_BEGIN_PLACES]){
                            that._cache_story_places[recID]['places'] = that._cache_story_places[recID][DT_BEGIN_PLACES];
                        }
                        if(DT_BEGIN_PLACES2>0 && that._cache_story_places[recID][DT_BEGIN_PLACES2]){
                            that._cache_story_places[recID]['places'] = that._cache_story_places[recID][DT_BEGIN_PLACES2];
                        }
                        for(dty_ID in that._cache_story_places[recID]){
                            if(dty_ID!=DT_BEGIN_PLACES && dty_ID!=DT_BEGIN_PLACES2 && dty_ID!=DT_END_PLACES && dty_ID!='places'){
                                    that._cache_story_places[recID]['places'] = that._cache_story_places[recID]['places']
                                    .concat(that._cache_story_places[recID][dty_ID]);
                            }
                        }
                        if(DT_END_PLACES>0 && that._cache_story_places[recID][DT_END_PLACES]){
                            that._cache_story_places[recID]['places'] = that._cache_story_places[recID]['places']
                                    .concat(that._cache_story_places[recID][DT_END_PLACES]);
                        }
                        
                        
                        if (that._cache_story_places[recID]['places'].length==0){
                            //no geodata, zoom to story element on timeline
                            let mapwidget = that._mapping.app_timemap('getMapping');
                            mapwidget.vistimeline.timeline('zoomToSelection', [recID]); //select and zoom 
                            return;
                        }

                        let qq = {ids:that._cache_story_places[recID]['places']};
                        
                        if(that.options.reportElementMapMode=='filtered'){ //additional filter for places 
                            qq = window.hWin.HEURIST4.query.mergeTwoHeuristQueries( qq, that.options.reportElementMapFilter );
                        }
                        


                        // 2. retrieve all places from server side as geojson
                        let server_request = {
                            q: qq,
                            leaflet: 1, 
                            simplify: 1, //simplify paths with more than 1000 vertices
                            zip: 1,
                            format:'geojson'};
                        window.hWin.HAPI4.RecordMgr.search_new(server_request,
                            function(response){

                                let geojson_data = null;
                                let layers_ids = [];
                                if(response['geojson']){
                                    geojson_data = response['geojson'];
                                    //not used timeline_data = response['timeline']; 
                                }else{
                                    geojson_data = response;
                                }
                                if( window.hWin.HEURIST4.util.isGeoJSON(geojson_data, true) )
                                {
                                     
                                    that._cache_story_places[recID]['geojson'] = geojson_data;
                                   

                                    // 3. create links between points
                                    if(that.options.reportElementMapMode!='all'){
                                        that._createPointLinks(recID);
                                    }
                                    
                                    // 4. update map
                                    that._animateStoryElement_B_step2(recID);
                                    

                                }else {
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                }


                        });
                    }

                }else {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            }
        );  


    },
    
    //
    // 4. update map
    //
    _animateStoryElement_B_step2: function(recID){
        
        if(this._currentElementID != recID) return; //user can switch to different story
        
        let that = this;
        
        let mapwidget = that._mapping.app_timemap('getMapping');
        
        if(window.hWin.HEURIST4.util.isnull(this._L)) this._L = mapwidget.getLeaflet();
        
        let DT_STORY_ANIMATION = $Db.getLocalID('dty', '2-1090');
        let record = this._resultset.getRecord(recID);
        let anime = this._resultset.fld(record, DT_STORY_ANIMATION);

        let default_story_element_style = 
        window.hWin.HEURIST4.util.isempty(anime)
                ?this.options.def_story_symbology
                :null;
        
        mapwidget.isMarkerClusterEnabled = false;
        this._nativelayer_id = mapwidget.addGeoJson(
            {geojson_data: that._cache_story_places[this._currentElementID]['geojson'],
                timeline_data: null, //that._cache_story_places[this._currentElementID]['timeline'],
                layer_style: default_story_element_style,
                //popup_template: layer_popup_template,
                dataset_name: 'Story Map',
                selectable: false,
                preserveViewport: true });
        //possible sequences
        // gain: begin-visible, trans fade in, end-visible
        // loses: trans-visible, trans fade out
        // path grow: fade by 1 or group
        // path move: fade in and out by 1 or group
        
        // json to describe animation
        // [{scope:begin|trans|end|all, range:0~n, actions:[{ action: duration: , steps:},..]},....] 

        /*  examples:     
        let anime = [{scope:'all',action:'hide'},{scope:'all',range:1,action:'fade_in',duration:1000}]; //show in sequence

        anime = [{scope:'all',range:1,action:'fade_out',duration:1000}]; //hide in sequence
        anime = [{scope:'all',action:'hide'},{scope:'all',range:1,action:'fade_in_out',duration:1000}];

        anime = [{scope:'all',range:1,actions:[{action:'fly'}]}];
        anime = [{scope:'all',action:'hide'},{scope:'all',range:1,actions:[{action:'center'},{action:'fade_in'}]}];
        anime = [{"scope":"all","actions":[{"action":"blink","duration":2000}]}];
        
        anime = [{"scope":"begin","actions":[{"action":"style","style":  }]}];
        
[{"scope":"all","actions":[{"action":"zoom"},{"action":"blink","steps":10,"duration":2000}] }]        
        */

        //or several actions per scope
       
        
        //let anime = [{scope:'all',range:1,action:'fade_in_out',duration:500}]; //show one by one
        
        
        //zoom to story element on timeline
        // @todo  It would be brilliant if 
        // the current time were calculated as a proportion of the change in time between start and end of the Story Element, perhaps in increments of say 1/20t
        mapwidget.vistimeline.timeline('zoomToSelection', [recID]); //select and zoom 
        
        //by default first action is fly to extent of story element
        this.actionBounds(recID, [mapwidget.all_layers[this._nativelayer_id]], 'fly' );
        
        if(!window.hWin.HEURIST4.util.isempty(anime)){
    
            anime = window.hWin.HEURIST4.util.isJSON(anime);
            if(anime){        

                function __startNewAnimation(){

                    if(that._terminateAnimation!==false){ 
                        //do not start new animation - waiting for termination of previous animation
                        // wait for stopping of previous animation 
                        if(that._currentElementID==recID){
                            setTimeout(__startNewAnimation, 500);                        
                        }else{
                            that._terminateAnimation = false;
                        }
                            
                    }else if(that._currentElementID==recID){
                        //start animation/actions
                        that._animateStoryElement_B_step3(recID, anime);
                    }
                }
                
                setTimeout(__startNewAnimation, 1700); //wait for flyto stop
                
            }
        }
    },
    
    //
    // performs animation for current range
    //
    _animateStoryElement_B_step3: function(recID, aSteps, step_idx, aRanges, range_idx, aActions, action_idx ){        

        //find ranges of places for animation
        if(window.hWin.HEURIST4.util.isempty(aRanges) || range_idx>=aRanges.length){ 
            //ranges not defined or all ranges are executed - go to new step
        
            //1.loop for steps - fill aRanges for current step
            step_idx = (step_idx>=0) ?step_idx+1:0;
            
            if(step_idx>=aSteps.length) return; //animation is completed
            
            let step = aSteps[step_idx];
            
            aActions = step['actions'];
            if(!aActions && step['action']){
                aActions = [{action:step['action']}];  
                if(step['duration']>0){
                    aActions[0]['duration'] = step['duration'];
                }
            } 
            if(window.hWin.HEURIST4.util.isempty(aActions)){ //actions are not defined for this step
                //actions are not defined - go to next step
                this._animateStoryElement_B_step3(recID, aSteps, step_idx );   
                return;
            }
            
            range_idx = 0;
            action_idx = 0;
            
            //2.find places for current step
            
            let places, scope = null;
            
            //2a get scope - @todo array/combination of scopes
            if(step['scope']=='begin'){
                scope = '2-134';
            }else if(step['scope']=='trans'){
                scope = '1414-1090';
            }else if(step['scope']=='end'){
                scope = '2-864';
            }else if( typeof step['scope'] === 'string' && step['scope'].indexOf('-')>0 ){ //dty concept code
                scope = step['scope'];
            }else if( Array.isArray(step['scope']) ){
                //array of record ids
                places = step['scope'];
            }else if( parseInt(step['scope'])>0 ){
                //particular record id
                places = [step['scope']]; 
                
            }else{
                scope = 'places'; //all places ids in proper order
            }
            
            if(scope){
                let scope2 = scope;
                if(scope.indexOf('-')>0){
                    scope = $Db.getLocalID('dty', scope);
                }
                places = this._cache_story_places[this._currentElementID][scope];    
                
                //special case - we have to fields for begin places
                if(window.hWin.HEURIST4.util.isempty(places) && scope2=='2-134'){
                    scope = $Db.getLocalID('dty', '1414-1092');
                    places = this._cache_story_places[this._currentElementID][scope];    
                }
            }
            
            //2b get ranges within scope
            aRanges = []; //reset
            let range = places.length;
            if(step['range'] && step['range']>0 && step['range']<places.length){
                range = step['range'];
            
                let start = 0, end = 0;
                while (end<places.length){
                    
                    end = start+range;
                    
                    aRanges.push(places.slice(start, end));
                    start = end;
                    
                }
            }else{
                aRanges.push(places);
            }
            
            if(window.hWin.HEURIST4.util.isempty(aRanges)){ //ranges are not defined for this step
                //rangess are not defined - go to next step
                this._animateStoryElement_B_step3(recID, aSteps, step_idx );   
                return;
            }

        }//search for ranges for current step
        
        //3 execute action(s)    
        if(action_idx>=aActions.length){ 
            //all actions are executed
            action_idx = 0;
            this._animateStoryElement_B_step3(recID, aSteps, step_idx, aRanges, range_idx+1, aActions, action_idx );
            return;
        }
        
        
        let range_of_places = aRanges[range_idx];
        
        let mapwidget = this._mapping.app_timemap('getMapping');
        let L = this._L;
        let top_layer = mapwidget.all_layers[this._nativelayer_id];
        
        let layers = this._getPlacesForRange(top_layer, range_of_places);
       
        let action = aActions[action_idx];
               
        // 
        //take list of required actions on story element change
        // ??zoom out - show entire bounds (all places within story element?)
        // zoom - zoom to scope
        // *fly - fly to scope 
        // center
        // *show
        // *hide
        // *fade_in - show marker, path or poly
        // *fade_in_out - show marker, path or poly
        // *blink
        // *gradient
        // *style - assign new style
        // show popup
        
        // follow_path - move marker along path
        // ant_path - css style
        // show_report - popup on map


        let that = this;

        let promise = new Promise(function(_resolve, _reject){
            
            that._animationResolve = _resolve;
            that._animationReject = _reject;
               
            switch (action['action']) {
               case 'fade_in':
                    that.actionFadeIn(recID, mapwidget.nativemap, layers, 0, 1, 0.05, action['duration'], action['delay']);
               break;
               case 'fade_out':
                    that.actionFadeIn(recID, mapwidget.nativemap, layers, 1, 0, -0.05, action['duration'], action['delay']);
               break;
               case 'fade_in_out':
                    that.actionFadeIn(recID, mapwidget.nativemap, layers, 0, 1, 0.05, action['duration'], action['delay'], true);
               break;
               case 'hide':
                    that.actionHide(layers); //, action['duration']
               break;
               case 'show':
                    that.actionShow(mapwidget.nativemap, layers); //, action['duration']
               break;
               case 'fly':
               case 'zoom':
               case 'center':
                    that.actionBounds(recID, layers, action['action'], action['duration']);       
               break;
               case 'blink':

                    that.actionBlink(recID, layers, action['steps'], action['duration']);
               
               break;
               case 'gradient':
                    //change color from one color to another
                    if(!action['from']) action['from'] = '#ff0000';
                    if(!action['to']) action['to'] = '#00ff00';
                    that.actionGradient(recID, layers, action['from'], action['to'], action['steps'], action['duration']);
               break;
               case 'style':

                    that.actionSetStyle(recID, layers, action['style'], action['duration']);
               
               break;
            }
        });
        
        promise
        .then(function(res){
            that._animationResolve = null;
            if(that._terminateAnimation===true || that._terminateAnimation==recID){
                that._terminateAnimation = false;
            }else{
                //next step
                that._animateStoryElement_B_step3(recID, aSteps, step_idx, aRanges, range_idx, aActions, action_idx+1 );    
            }
        },
        function(res){ //termination
            that._terminateAnimation = false;
            that._animationResolve = null;
            
        });     
        
    },
    
    //
    // returns layers for given record ids
    //
    _getPlacesForRange: function(top_layer, range_of_places){

        let layers = [];
        let L = this._L;


        top_layer.eachLayer(function(layer){
              if (layer instanceof L.Layer && layer.feature)  //(!(layer.cluster_layer_id>0)) &&
              {
                    if(layer.feature.properties.rec_ID>0){
                        let idx = window.hWin.HEURIST4.util.findArrayIndex(layer.feature.properties.rec_ID, range_of_places);
                        if(idx>=0) layers.push(layer);
                    }

                    /*                  
                    if(layer.feature.properties.rec_ID==recID){
                        layers.push(layer);
                        if(hide_before_show){
                            layer.remove();    
                        }else if(layer._map==null){
                            layer.addTo( nativemap );           
                        }
                    }else 
                    if (window.hWin.HEURIST4.util.findArrayIndex(layer.feature.properties.rec_ID, all_events)>=0)
                    {
                        layer.remove();
                    }
                    */
              }
        });        
        
        return layers;
    },


    //
    // fly, zoom, center
    //
    actionBounds: function(recID, layers, mode, duration){
        
        let mapwidget = this._mapping.app_timemap('getMapping');
        let useRuler = (layers.length==1);
        let bounds = [];
        
        $.each(layers, function(i, layer){
            let bnd = mapwidget.getLayerBounds(layer, useRuler);
            bounds.push( bnd );    
        });

        //.nativemap
        bounds = mapwidget._mergeBounds(bounds);
        
        //
        if(mode=='center'){
            mapwidget.nativemap.panTo(bounds.getCenter());    //setView
        }else{
            if(!(duration>0)){
                duration = this.options.zoomAnimationTime>=0
                            ?this.options.zoomAnimationTime
                            :5000;
                if(duration==0 && mode=='fly') mode = 'zoom'; //if duration is zero mode is "zoom"
            } 
            if(mode == 'fly'){
                mode = {animate:true, duration:duration/1000};
            }else{
                mode = false;
            }
            
            mapwidget.zoomToBounds(bounds, mode);
        }        
        
        
        if(window.hWin.HEURIST4.util.isFunction(this._animationResolve)){
            if(duration==0){
                this._animationResolve();
            }else{
                let that = this;
                setTimeout(function(){
                        if(that._terminateAnimation===true || that._terminateAnimation==recID){
                            //animation terminated actionBounds
                            if(window.hWin.HEURIST4.util.isFunction(that._animationReject)) that._animationReject();
                        }else{
                            if (window.hWin.HEURIST4.util.isFunction(that._animationResolve)) that._animationResolve();
                        }                
                    }, duration);                        
            }
        }
    },
    
    //
    //
    //    
    actionHide: function( layers ){        
            //hide all layer elements
            $.each(layers, function(i, layer){
                      layer.remove();
            });
            if(window.hWin.HEURIST4.util.isFunction(this._animationResolve)) this._animationResolve();
    },

    //
    //
    //    
    actionShow: function( nativemap, layers ){        
            //hide all layer elements
            $.each(layers, function(i, layer){
                  if (layer._map==null){
                      layer.addTo( nativemap )                    
                  }
            });
            if(window.hWin.HEURIST4.util.isFunction(this._animationResolve)) this._animationResolve();
    },

    //
    // Fade-in function for Leaflet
    // if opacityStep<0 - fade out
    // need_reverce - true - fade in and then out
    // show_delay - delay before hide or after show
    actionFadeIn: function(recID, nativemap, layers, startOpacity, finalOpacity, opacityStep, duration, show_delay, need_reverce) 
    {
        
        let steps = Math.abs(finalOpacity-startOpacity)/Math.abs(opacityStep);
        if(need_reverce) steps = steps * 2;
        
        if(!(duration>0)) duration = 1000;        
        let delay = duration/steps;
        
        if(!show_delay) show_delay = 0;

        
        let that = this;
        let L = this._L;
        let opacity = startOpacity;
        function __changeOpacity() {
            
            let iOK = (opacityStep>0)
                    ?(opacity < finalOpacity)
                    :(finalOpacity < opacity);

            if ( iOK ) {
                $.each(layers, function(i, lyr){
                    
                    if(that._terminateAnimation===true || that._terminateAnimation==recID){
                        //animation terminated actionFadeIn
                        if(window.hWin.HEURIST4.util.isFunction(that._animationReject)) that._animationReject();
                        return false;
                    }
               
                    if(lyr instanceof L.Marker){
                        lyr.setOpacity( opacity );                        
                    }else{
                        lyr.setStyle({
                            opacity: opacity,
                            fillOpacity: opacity
                        });
                    }
                    if(lyr._map==null) lyr.addTo( nativemap )                    
                });
                opacity = opacity + opacityStep;
                setTimeout(__changeOpacity, delay);
            }else{
                if(need_reverce===true){
                    need_reverce = false
                    opacityStep = -opacityStep;
                    opacity = finalOpacity;
                    finalOpacity = startOpacity;
                    startOpacity = opacity; 
                    //delay before hide
                   
                    setTimeout(__changeOpacity, show_delay>0?show_delay:delay);
                }else{
                    if(opacityStep>0 && show_delay>0){
                        //delay after show
                        setTimeout(function(){
                             if(window.hWin.HEURIST4.util.isFunction(that._animationResolve)) that._animationResolve();   
                        }, show_delay);
                    }else{
                        if(window.hWin.HEURIST4.util.isFunction(that._animationResolve)) that._animationResolve();        
                    }
                    
                    
                }
            }
        }//__changeOpacity
        
        if(opacityStep<0 && show_delay>0){
            //delay before hide
            setTimeout(__changeOpacity, show_delay);
        }else{
            __changeOpacity();    
        }
        
        
    },
    
    //
    //
    //
    actionGradient: function(recID, layers, startColour, endColour, steps, duration){

        if(!duration) duration = 2000;
        if(!steps) steps = 20;
        
        let that = this;
        let delay = duration/steps;
        
        let colors = window.hWin.HEURIST4.ui.getColourGradient(startColour, endColour, steps);
        let color_step = 0;
        
        let mapwidget = this._mapping.app_timemap('getMapping');
        let top_layer = mapwidget.all_layers[this._nativelayer_id];
        
        function __changeColor() {
            if ( color_step<colors.length ) {
                $.each(layers, function(i, lyr){
                    
                    if(that._terminateAnimation===true || that._terminateAnimation==recID){
                        //animation terminated actionGradient
                        if(window.hWin.HEURIST4.util.isFunction(that._animationReject)) that._animationReject();
                        return false;
                    }
                    
                    let clr = colors[color_step];
                    
                    let style = {color:clr, fillColor:clr};
                    
                    mapwidget.applyStyleForLayer(top_layer, lyr, style);
                    
                });
                color_step++;
                setTimeout(__changeColor, delay);
            }else{
                if(window.hWin.HEURIST4.util.isFunction(that._animationResolve)) that._animationResolve();    
            }                
        }
        
        __changeColor();
        
        
    },
    
    //
    //
    //
    actionBlink: function(recID, layers, steps, duration){
        
        if(!duration) duration = 1000;
        if(!steps) steps = 10;
        
        let that = this;
        let delay = duration/steps;
        let count = 0;
        let mapwidget = this._mapping.app_timemap('getMapping');
        let is_visible = [];
        let is_terminated = false;

        let interval = window.setInterval(function() {
            
            $.each(layers, function(i, lyr){
                
                if(count==0){
                    //keep initial visibility
                    is_visible.push((lyr._map!=null));
                }
            
                if(that._terminateAnimation===true || that._terminateAnimation==recID){
                    //animation terminated actionBlink
                    clearInterval(interval);
                    interval = 0;
                    if(window.hWin.HEURIST4.util.isFunction(that._animationReject)) that._animationReject();
                    is_terminated = true;
                    return false;
                }else 
                if(lyr._map==null){
                    lyr.addTo( mapwidget.nativemap );                      
                }else{
                    lyr.remove();
                }
                
            });
            
            count++;
            if(count>steps){
                clearInterval(interval);
                interval = 0;
                if(window.hWin.HEURIST4.util.isFunction(that._animationResolve)) that._animationResolve();    
            }
        },delay);
        
        //restore initial visibility
        if(!is_terminated){
            $.each(layers, function(i, lyr){
                if(is_visible[i]){
                    if(lyr._map==null) lyr.addTo( mapwidget.nativemap );                    
                }else{
                    lyr.remove()
                }
                
            });
        }
        
    },
    
    //
    //
    //
    actionSetStyle: function(recID, layers, newStyle, delay){

        if(newStyle){
        
            if(!delay) delay = 500;

            let that = this;
            let mapwidget = this._mapping.app_timemap('getMapping');
            let top_layer = mapwidget.all_layers[this._nativelayer_id];
            
            setTimeout(function(){

                $.each(layers, function(i, lyr){
                    mapwidget.applyStyleForLayer(top_layer, lyr, newStyle);
                });
                
                if(that._terminateAnimation===true || that._terminateAnimation==recID){
                    //animation terminated actionSetStyle
                    if(window.hWin.HEURIST4.util.isFunction(that._animationReject)) that._animationReject();
                    return false;
                }else
                    if(window.hWin.HEURIST4.util.isFunction(that._animationResolve)) that._animationResolve();            
                }, delay);
            
        }else{
            if(window.hWin.HEURIST4.util.isFunction(this._animationResolve)) this._animationResolve();            
        }
        
    },
    
    //
    // recID - 
    //
    _createPointLinks: function( recID ){

        // Begin '2-134', Transition '1414-1090', End '2-864'
        let DT_BEGIN_PLACES = $Db.getLocalID('dty', '2-134');
        let DT_BEGIN_PLACES2 = $Db.getLocalID('dty', '1414-1092');
        let DT_END_PLACES = $Db.getLocalID('dty', '2-864');
        let DT_TRAN_PLACES = $Db.getLocalID('dty', '1414-1090');
        
        let gd = this._cache_story_places[recID]['geojson'];
        //gather all verties
        let begin_pnt = [], end_pnt = [], tran_pnt = [];
        
        function _fillPnts(ids, pnt){

            if(!window.hWin.HEURIST4.util.isempty(ids))
            for (let k=0; k<=ids.length; k++){
                for (let i=0; i<gd.length; i++){
                    if(gd[i]['id']==ids[k] && gd[i]['geometry']['type']=='Point'){
                        pnt.push(gd[i]['geometry']['coordinates']);
                        break;
                    }
                }
            }
            
        }
        
        _fillPnts(this._cache_story_places[recID][DT_BEGIN_PLACES], begin_pnt);
        _fillPnts(this._cache_story_places[recID][DT_BEGIN_PLACES2], begin_pnt);
        _fillPnts(this._cache_story_places[recID][DT_END_PLACES], end_pnt);
        _fillPnts(this._cache_story_places[recID][DT_TRAN_PLACES], tran_pnt);
        
        let path = null;
        //create link path from begin to end place
        if (begin_pnt.length>0 || end_pnt.length>0 || tran_pnt.length>0){
           
            
            //PAIRS: many start points and transition points - star from start points to first transition
            if(begin_pnt.length>1 || end_pnt.length>1){
                path = {geometry:{coordinates:[], type:'MultiLineString'}, id:'xxx', type:'Feature', properties:{rec_ID:0}};
                
                if(tran_pnt.length>0){

                    //adds lines from start to first transition    
                    if(begin_pnt.length>0){
                        for(let i=0; i<begin_pnt.length; i++){
                            path.geometry.coordinates.push([begin_pnt[i], tran_pnt[0]]);
                        }                
                    }
                    //transition
                    path.geometry.coordinates.push(tran_pnt);
                    
                    //lines from last transition to end points
                    if(end_pnt.length>0){
                        let last = tran_pnt.length-1;
                        for(let i=0; i<end_pnt.length; i++){
                            path.geometry.coordinates.push([tran_pnt[last], end_pnt[i]]);
                        }                
                    }
                    
                }else if(end_pnt.length==begin_pnt.length){ //PAIRS
                    //adds lines from start to end
                    for(let i=0; i<begin_pnt.length; i++){
                        path.geometry.coordinates.push([begin_pnt[i], end_pnt[i]]);
                    }                
                }
                
                
            }else{
                path = {geometry:{coordinates:[], type:'LineString'}, id:'xxx', type:'Feature', properties:{rec_ID:0}};
                
                if(begin_pnt.length>0) path.geometry.coordinates.push(begin_pnt[0]);

                if(tran_pnt.length>0)
                    for(let i=0; i<tran_pnt.length; i++){
                        path.geometry.coordinates.push(tran_pnt[i]);
                    }                

                if(end_pnt.length>0) path.geometry.coordinates.push(end_pnt[0]);
            }
            
            
            if(path.geometry.coordinates.length>0){
                this._cache_story_places[recID]['geojson'].push(path);
            }
        }    
    
    }

});
