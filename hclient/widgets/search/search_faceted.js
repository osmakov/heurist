/**
*  Apply faceted search
* TODO: Check that this is what it does and that it is not jsut an old version
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

/* global TDate, temporalSimplifyDate */

/*
main methods
    _initFacetQueries - creates facet searches (counts and values for particular facets) and main query
    _fillQueryWithValues - fille queries with values
    doSearch - performs main search
    _recalculateFacets - search for facet values as soon as main search finished
    _redrawFacets - called from _recalculateFacets then call _recalculateFacets for next facet

*/

/* Explanation of faceted search

There are two types of queries: 1) to search facet values 2) to search results

Examples
1) No levels:   

search results: t:10 f:1:"XXX" - search persons by name
facet search:   f:1  where t:10 + other current queries

2) One level:

search results: t:10 linked_to:5-61 [t:5 f:1:"XXX"] - search persons where multimedia name is XXX
facet search:   f:1  where t:5 linkedfrom:10-61 [other current queries(parent query)]

3) Two levels

search results: t:10 linked_to:5-61 [t:5 linked_to:4-15 [t:4 f:1:"XXX"]] - search persons where multimedia has copyright of organization with name is XXX
facet search:   f:1 where t:4 linkedfrom:5-15 [t:5 linkedfrom:10-61 [other current queries for person]]   - find organization who is copyright of multimedia that belong to person


Thus, our definition has the followig structure
rectype - main record type to search
domain
facets:[ [
code:  10:61:5:15:4:1  to easy init and edit    rt:ft:rt:ft:rt:ft  if link is unconstrained it will be empty  61::15
title: "Author Name < Multimedia"
id:  1  - field type id 
type:  "freetext"  - field type
levels: [t:4 linkedfrom:5-15, t:5 linkedfrom:10-61]   (the last query in this array is ommitted - it will be current query)

search - main query to search results
            [linked_to:5-61, t:5 linked_to:4-15, t:4 f:1]    (the last query in the top most parent )

currentvalue:
history:  - to keep facet values (to avoid redundat search)

],
//the simple (no level) facet
[
id: 1
code: 10:1 
title: "Family Name"
type:  "freetext"
levels: []
search: [t:10 f:1] 
orderby: count|null
groupby
multisel
],
//multi field facet ???
[
id: [1,2]
code: [10:1,10:2]
title: "Family Name"
type:  "freetext"
levels: []
search: [t:10 f:1] 
orderby: count|null
groupby
],
]


--------------
NOTE - to make search for facet value faster we may try to omit current search in query and search for entire database

---------------
TOP PARAMETERS for entire search
facet search general parameters are the same to saved search plus several special

domain
rules
rulesonly
ui_title - title in user interface
ui_viewmode - result list viewmode
title_hierarchy - show hierarchy in facet header
sup_filter - suplementary (preliminary) filter that is set in design time
add_filter - additional filter that can be set in run time (via input field - search everything)
add_filter_original - original search string for add_filter if it is json  - NO USED
spatial_filter  - spatial filter (optional)
search_on_reset - search for empty form (on reset and on init)

ui_prelim_filter_toggle   - allow toggle on/off "sup_filter"
ui_prelim_filter_toggle_mode - direct or reverse mode (0|1)
ui_prelim_filter_toggle_label - label on UI

ui_spatial_filter - show spatial filter
ui_spatial_filter_label
ui_spatial_filter_initial - initial spatial search
ui_spatial_filter_init - apply spatial search at once

ui_temporal_filter_initial - initial temporal search for empty form only

ui_additional_filter - show search everything input (for add_filter)
ui_additional_filter_label

viewport - collapse facet to limit count of items
accordion_view - make each facet a togglable accordion container
show_accordion_icons - show or hide toggle arrow in accordion header

rectypes[0] 
*/            

/*
requires:
editing_input
*/
$.widget( "heurist.search_faceted", {

    _MIN_DROPDOWN_CONTENT: 50,//0, //min number in dropdown selector, otherwise facet values are displayed in explicit list
    _FT_INPUT: 0,  //direct search input
    _FT_SELECT: 1, //slider for numeric and date  (for freetext it is considered as _FT_LIST)
    _FT_LIST: 2,    //list view mode  
    _FT_COLUMN: 3,  //wrapped list view mode

    
    // default options
    options: {
        is_h6style: true,
        params: {},
        ispreview: false,
        showclosebutton: true,
        showresetbutton: true,
        svs_ID: null,
        onclose: null,// callback
        is_publication: false,
        respect_relation_direction: false,
        language: 'def',  //use default

        hide_no_value_facets: true, // hide facets with no values, default true
        
        search_page: null, //target page (for CMS) - it will navigate to this page and pass search results to search_realm group
        search_realm:  null  //accepts search/selection events from elements of the same realm only
    },
    
    _current_query_request_id:null,

    cached_counts:[], //stored results of get_facets by stringified query index
    _input_fields:{},
    _request_id: 0, //keep unique id to avoid redraw facets for old requests
    _first_query:[], //query for all empty values
    _isInited: true,
    _current_query: null,
    _hasLocation: null, //has primary rt geo fields or linked location - for spatial search
    
    _currentRecordset:null,
    
    _use_sup_filter:null, 
    
    _use_multifield: false, //HIE - search for several fields per facet
    
    ui_spatial_filter_image:null,
    
    _warned_missing_fields: false, // warn user about fields no longer within record structure, but still in facet
    _terminateFacetCalculation: false, //stop flag

    _date_range_dialog: null,
    _date_range_dialog_instance: null,

    terms_drawn: 0, // for rendering terms
    
    no_value_facets: [], // {facet_id: facet_name}, for facets with no values
    
    _last_active_facet: -1,
    
    _last_term_value: null, // latest term value selected, to be passed to custom report widgets

    _expanded_count_facets: {}, // facet counts to be expanded by ruleset, these usually start with 1 record then open up to several more
    _expanded_count_order: [], // order of retrieval for above
    _expanded_count_cancel: false,
    
    // the widget's constructor
    _create: function() {
        
        if(!this.options.language) this.options.language = 'def'; //"xx" means use current language

        this._is_publication = window.hWin.HAPI4.is_publish_mode;
        
        if(!this.element.attr('id')){
            this.element.uniqueId();
        }
        
        this._use_multifield = window.hWin.HAPI4.database=='johns_hamburg' &&
                window.hWin.HEURIST4.util.findArrayIndex(this.options.svs_ID,[20,21,22,23,24,28,30,31])>=0;

        let that = this;
        
        if(!window.hWin.HEURIST4.util.isFunction($('body')['editing_input'])){
            $.getScript( window.hWin.HAPI4.baseURL + 'hclient/widgets/editing/editing_input.js', function() {
                that._create();
            });
            return;
        }
        // Sets up element to apply the ui-state-focus class on focus.
        //this._focusable($element);   

        this.div_header = $( "<div>" ).css({height: 'auto',
            position: 'absolute', left: 0, right: 0}).appendTo( this.element );
        
        if(!this.options.ispreview){     
        
            if(this.options.is_h6style && !this.options.is_publication){
                
                this.div_title = $('<div class="ui-heurist-header truncate" '
                    +'style="position:relative;padding:10px;font-size: 0.9em; max-width:90%">') 
                    .appendTo( this.div_header );
                
            }else{
                             
                this.div_title = $('<div>')
                .css({padding:'0.4em 0.2em 0.2em 1em','font-size':'1.4em','font-weight':'bold','max-width':'90%'})
    //style='text-align:left;margin:4px 0 0 0!important;padding-left:1em;width:auto, max-width:90%'></h3")
                        .addClass('truncate svs-header').appendTo( this.div_header );
            }
        }
        
        this.refreshSubsetSign();
        
        //"font-size":"0.7em",
        this.div_toolbar = $( "<div>" ).css({'font-size': '0.9em', //"float":"right",
                    "padding":"0px 5px"})
                .appendTo( this.div_header );

                
        this.btn_submit = $( "<button>", { text: window.hWin.HR('filter_facet_submit') })
        .appendTo( this.div_toolbar )
        .button();
        
        this.btn_reset = $( "<button>", {title:window.hWin.HR('filter_facet_resetall_hint') })
        .appendTo( this.div_toolbar )
        .css({"z-index":"100","float":"left"})
        .button({label: window.hWin.HR('filter_facet_resetall'), icon: 'ui-icon-arrowreturnthick-1-w', iconPosition:'end' }).hide();
        
        this.btn_save = $( "<button>", { text: window.hWin.HR('filter_facet_savestate') })
        .appendTo( this.div_toolbar )
        .button().hide(); //@todo

        
        let lbl = window.hWin.HRJ('ui_exit_button_label', this.options.params, this.options.language);
        if(!lbl) lbl = window.hWin.HR('filter_facet_exit');
        
        this.btn_close = $( "<button>", { 
                    title:window.hWin.HR('filter_facet_exit_hint') })
        .css({"z-index":"100","float":"right"})
        .appendTo( this.div_toolbar )
        
        .button({icon: "ui-icon-close", iconPosition:'end', label:lbl}); //was Close
        
        if(this.options.is_publication){
            this.btn_close.addClass('ui-button-action');
        }
        

        this.btn_close.find('.ui-icon-close').css({right: 0}); //'font-size': '1.3em', 
        
        if(this.options.params.ui_exit_button===false) this.options.showclosebutton = false;
        
        this.btn_terminate = $( "<button>").appendTo( this.div_toolbar )
        .button({icon: "ui-icon-cancel", iconPosition:'end', label:window.hWin.HR('filter_facet_interrupt')}).hide();
        
        $('<span>', {id: 'facet_process_msg', class: 'heurist-helper2', 'data-interrupt': 0})
            .text(window.hWin.HR('filter_facet_processing'))
            .css({display: 'inline-block', 'padding-right': '10px'})
            .insertBefore(this.btn_terminate).hide();
        
        this._on( this.btn_submit, { click: "doSearch" });
        this._on( this.btn_reset, { click: "doResetAll" });
        this._on( this.btn_save, { click: "doSaveSearch" });
        this._on( this.btn_close, { click: "doClose" });
        this._on( this.btn_terminate, { click: function(){

            this.btn_terminate.hide();
            this.div_toolbar.find('#facet_process_msg')
                            .attr('data-interrupt', 1)
                            .text('some facets not processed')
                            .css('color', 'red').insertBefore(this.btn_close);

            this._terminateFacetCalculation = true; 
        }});

        this.facets_list_container = $( "<div>" )
        .attr('data-fid','facets_list_container')
        .css({"top":((this.div_title)?'6em':'2em'),"bottom":0,"position":"absolute"}) //was top 3.6
        .appendTo( this.element );
        
        if(this.options.is_h6style && !this.options.is_publication){
            this.facets_list_container.css({left:0,right:0,'font-size':'0.9em'});    
        }else{
            this.facets_list_container.css({left:0,right:0});     //{left:'1em',right:'0.5em'}
        }

        this.facets_list = $( "<div>" )
        .addClass('svs-list-container')
        .css({"overflow-x":"hidden","overflow-y":"auto","height":"100%"}) //"font-size":"0.9em", 
        .appendTo( this.facets_list_container );

        //was this.document
        $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH+' '+window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
            +' '+window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE+' '+window.hWin.HAPI4.Event.ON_CUSTOM_EVENT, 
        
        function(e, data) {
            
            if(e.type == window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE){

                let w = that.element.width();
                
                that.element.find('div.facet-item > a > span.truncate').width(w-100); //was 80
                
                if(that.btn_reset) that.btn_reset.button({showLabel:(w>250)});
                that.btn_close.button({showLabel:(w>250)});
                  
            }else {

                if(data && that.options.search_realm && that.options.search_realm!=data.search_realm) return;
                if(data.reset) return; //ignore


                if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){
                
                        if(data){
                            
                            if(data.source && data.source==that.element.attr('id') ){   //search from this widget
                                  that._current_query_request_id = data.id;
                            }else if(!data.is_inital_search){
                                //search from outside - close this widget
                                that.doClose();
                            }
                        }
                    
                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){
                    
                    let recset = data.recordset;
                    if(recset && recset.queryid()==that._current_query_request_id) {
                        //search from this widget
                        that._current_query_request_id = null;
                        that._currentRecordset = recset;
                        that._isInited = false;
                        that.no_value_facets = [];
                        that._expanded_count_order = [];
                        that._expanded_count_facets = {};

                        that._recalculateFacets(-1);       
                        that.refreshSubsetSign();

                        if(window.hWin.HEURIST4.util.isFunction(that.options.params.callback_on_search_finish) && recset){
                            that.options.params.callback_on_search_finish.call(this, recset.count_total());
                        }
                    }         
                }else if(e.type == window.hWin.HAPI4.Event.ON_CUSTOM_EVENT && data){
                    
                    if(data.userWorkSetUpdated){
                        that.refreshSubsetSign();
                    }
                    if(data.closeFacetedSearch){
                        that.doClose();
                    }else
                    if(data.restartSearch){
                        that.doSearch();
                    }
                    
                }
            }
        });
        
        //apply spacial filter at once
        if(that.options.params.ui_spatial_filter_initial && that.options.params.ui_spatial_filter_init){
               that.options.params.spatial_filter = that.options.params.ui_spatial_filter_initial;
              
        }
        
        setTimeout(function(){that._adjustSearchDivTop();},500);

        this.doReset();            

    }, //end _create

    //
    //
    //
    _adjustSearchDivTop: function(){
        if(this.facets_list_container && this.div_header){
            let iAdd = 4;
            if(this.options.params.ui_spatial_filter){
                iAdd = -25;    
            }
            this.facets_list_container.css({top: this.div_header.height()+iAdd});
        }
    },
    
    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
    },

    _setOption: function( key, value ) {
        this._super( key, value );
        if(key=='add_filter'){
            this.options.params.add_filter = value;
        }else if(key=='spatial_filter'){
            this.options.params.spatial_filter = value;
        }else if(key=='add_filter_original'){
            this.options.params.add_filter_original = value;
        }
    },
    
    _setOptions: function( options ) {
        this._superApply( arguments );
        this._hasLocation = null;
        if(window.hWin.HEURIST4.util.isnull(options['add_filter']) && window.hWin.HEURIST4.util.isnull(options['spatial_filter'])){
            this.cached_counts = [];
            this._expanded_count_order = [];
            this._expanded_count_facets = {};
           
            this.doReset();
        }
    },
    
    _refreshTitle: function(){    
        let new_title = '';
        if(this.div_title) {
            
            let stitle = window.hWin.HRJ('ui_title', this.options.params, this.options.language) ||
                         window.hWin.HRJ('ui_name', this.options.params, this.options.language);
            
            if(stitle){ //from settings
                new_title = stitle;
            }else{
                let svsID = this.options.query_name;
                if(svsID > 0){
                    
                    if (window.hWin.HAPI4.currentUser.usr_SavedSearch && 
                                window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID])
                    {
                         new_title = window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID][0];//Hul._NAME];                
                    }else if(window.hWin.HAPI4.has_access()){
                        let that = this;
                        window.hWin.HAPI4.SystemMgr.ssearch_get( null,
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    window.hWin.HAPI4.currentUser.usr_SavedSearch = response.data;
                                    that._refreshTitle();
                                }
                        });
                    }
                    
                }else{
                    new_title = svsID;
                }
            }
            if(window.hWin.HEURIST4.util.isnull(new_title)) new_title='';
            else if(this.options.is_h6style && !this.options.is_publication){
                new_title = '<span style="font-size:smaller">'
                +'<span class="ui-icon ui-icon-filter" style="width:16px;height:16px;background-size:contain;"></span>'
                + window.hWin.HR('filter_facet_titleprefix') + '</span>'
                + new_title;
            }
            

            this.div_title.html(new_title);
        }
    },
    
    /* 
    * private function    - NOT USED
    * show/hide buttons depends on current login status
    */
    _refresh: function(){
        
        this._refreshTitle();
        this._refreshButtons();
                            
        
        this.doRender();
       
    },
    
    _refreshButtons: function(){
        
        if(this.options.ispreview){
            this.btn_save.hide(); 
            this.btn_close.hide(); 
        }else{
            
            let facets = this.options.params.facets;
            let hasHistory = false, facet_index, len = facets?facets.length:0;
            for (facet_index=0;facet_index<len;facet_index++){
                if( !window.hWin.HEURIST4.util.isempty(facets[facet_index].history) ){
                    hasHistory = true;
                    break;
                }
            }

            let query = window.hWin.HEURIST4.util.cloneJSON( this.options.params.q ); //clone 
            let isform_empty = this._fillQueryWithValues(query);
 
            if((hasHistory || !isform_empty) && !this.options.params.ui_spatial_filter) {
                if(this.options.showresetbutton && this.btn_reset){
                    this.btn_reset.show()   
                }
            }else{
                if(this.btn_reset) this.btn_reset.hide()   
                this.btn_save.hide(); 
            }
            
            if(this.options.showclosebutton){
                this.btn_close.show(); 
            }else{
                this.btn_close.hide(); 
            }
        }
        
    },
    
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {

        $(this.document).off( window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
            +' '+window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
            +' '+window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE
            +' '+window.hWin.HAPI4.Event.ON_CUSTOM_EVENT );

        // remove generated elements
        if(this.div_title) this.div_title.remove();

        this.cached_counts = [];
        this._expanded_count_order = [];
        this._expanded_count_facets = {};

        this.btn_submit.remove();
        this.btn_close.remove();
        this.btn_save.remove();
        if(this.btn_reset) this.btn_reset.remove();
        this.div_toolbar.remove();

        this.facets_list.remove();
        this.facets_list_container.remove();
    }

    //Methods specific for this widget---------------------------------

    //
    // 1. creates lists of queries to search facet values
    //       Converts field['code'] to heurist querty and store it in field['facet']
    // 2. creates main JSON query
    //
    ,_initFacetQueries: function(){

        let that = this, mainquery = [];

        $.each(this.options.params.facets, function(index, field){

            //value is defined - it will be used to create query
            if( !window.hWin.HEURIST4.util.isnull(field['var']) && field['code']){
                //1. creates query to retrieve facet values
                let res = window.hWin.HEURIST4.query.createFacetQuery(field['code'], 
                                                    (field['isfacet']!=that._FT_INPUT),
                                                    that.options.respect_relation_direction);
                field['id'] = res['id']; //last id in the code - dty_ID
                field['rtid'] = res['rtid'];
                if(res['facet']) field['facet'] = res['facet'];
                
                //
                // 2. creates main JSON query
                //
                function __checkEntry(qarr, key, val){
                    let len0 = qarr.length, notfound = true, isarray;

                    for (let i=0;i<len0;i++){
                        if(! window.hWin.HEURIST4.util.isnull( qarr[i][key] ) ){ //such key already exsits

                            if(Array.isArray(qarr[i][key])){ //next level
                                return qarr[i][key];   
                            }else if(qarr[0][key]==val){ //already exists
                                return qarr;
                            }else if (i==0 && key=='t') {
                                let rtids = qarr[0][key].split(',');
                                let j = window.hWin.HEURIST4.util.findArrayIndex(val,rtids);
                                if(j<0) {
                                    rtids.push(val);
                                    qarr[0][key] = rtids.join(',');
                                    return qarr;
                                }else{
                                    return qarr; //already in list
                                }
                                
                            }
                        }
                    }

                    let predicat = {};
                    predicat[key] = val;
                    qarr.push(predicat);

                    if(Array.isArray(val)){
                        return val;
                    }else{
                        return qarr;
                    }
                }//__checkEntry            

                let code = field['code'];
                code = code.split(':')
                const linktype = code[code.length-1].substr(0,2);
                if(linktype=='lt' || linktype=='lf' || linktype=='rt' || linktype=='rf'){
                    //unconstrained link
                    code.push('0');         //!!!!!!!!
                    code.push('title');
                }
                
                let curr_level = mainquery;     
                let j = 0;    
                while(j<code.length){

                    let rtid = code[j];
                    let dtid = code[j+1];

                    //first level can be multi rectype
                    //add recordtype
                    if(rtid>0 ||  rtid.indexOf(',')>0){  //AA!!  ||  rtid.indexOf(',')>0
                    
                        if(rtid!=1){ //not relationship 
                            curr_level = __checkEntry(curr_level,"t",rtid);
                        }
                    }
                    const linktype = dtid.substr(0,2);
                    let slink = null;

                    if(linktype=='rt'){
                        slink = (that.options.respect_relation_direction?'related_to':'related')+':';
                    }else if(linktype=='rf'){
                        slink = (that.options.respect_relation_direction?'relatedfrom':'related')+':'; 
                    }else if(linktype=='lt'){
                        slink = "linked_to:";
                    }else if(linktype=='lf'){
                        slink = "linkedfrom:";
                    }else{
                        slink = null;
                    }

                    let key, val;                         
                    if(slink!=null){

                        const rtid_linked = code[j+2];  //linked record type, if null or 0 - unconstrained
                        key  = slink+rtid_linked+":"+dtid.substr(2); //rtid need to distinguish links/relations for various recordtypes
                        val = [];
                    }else{
                        //multifield search for datetime
                        if(dtid==9 && that._use_multifield){
                            dtid = '9,10,11';
                        }else if(dtid==1  && that._use_multifield){ //for name
                            dtid = '1,18,231,304';
                        }
                        
                        if(dtid.indexOf('r.')==0){
                            key = "r:"+dtid.substr(2);
                        }else if(dtid>0){
                            key = "f:"+dtid;
                        }else{
                            key = dtid;
                        }

                        val = "$X"+field['var']; 
                    }
                    curr_level = __checkEntry(curr_level, key, val);


                    j=j+2;
                }//while               

            }
        });

        this.options.params['q'] = mainquery;
    }

    ,doResetAll: function(){
       
        this.options.params.add_filter = null;
        this.options.params.add_filter_original = null;
        this._last_term_value = null; // reset last selected term(s)
        this.doReset();
    }
    //
    // reset current search 
    // recreate facet elements/ or form inputs
    //
    ,doReset: function(){

        let that= this; 
        
        if(!this.options.language) this.options.language = 'def'; //"xx" means use current language
        if(!window.hWin.HAPI4.EntityMgr.getEntityData2('trm_Translation')){ 
            // retrieve translations
            window.hWin.HAPI4.EntityMgr.getTranslatedDefs('defTerms', 'trm', null, function(){
                that.doReset();
            });
            return;
        }

        this._last_active_facet = -1;

        $(this.document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, [ 
            {reset:true, 
             search_realm:this.options.search_realm, 
             primary_rt:9999, 
             ispreview: this.options.ispreview
            } ]);  //global app event to clear views
        
        let facets = this.options.params.facets;

        if(window.hWin.HEURIST4.util.isArrayNotEmpty(facets)){
            let facet_index, len = facets.length;
            let invalid_fields = [];
            let check_fields = !this._warned_missing_fields && !this.options.ispreview && 
                !this.options.is_publication && (window.hWin.HAPI4.currentUser.ugr_ID !== 0);

            for (facet_index=0;facet_index<len;facet_index++){
                facets[facet_index].history = [];
                facets[facet_index].selectedvalue = null;
                facets[facet_index].last_count_query = null;
                
                //support old format
                if(window.hWin.HEURIST4.util.isnull(facets[facet_index].isfacet) || facets[facet_index].isfacet==true){
                            facets[facet_index].isfacet = this._FT_SELECT;
                }else if (facets[facet_index].isfacet==false){
                            facets[facet_index].isfacet = this._FT_INPUT;
                }

                if(check_fields){

                    let codes = facets[facet_index]['code'].split(':');
                    let rtyid = codes[codes.length-2];
                    let dtyid = codes[codes.length-1];

                    rtyid = rtyid.indexOf(',') >= 0 ? rtyid.split(',')[0] : rtyid; // take first rectype id

                    if(rtyid && dtyid && Number.isInteger(+dtyid) && !$Db.rst(rtyid, dtyid)){
                        let fld_name = !window.hWin.HEURIST4.util.isempty(facets['title']) ? facets['title'] : null;
                        invalid_fields.push(fld_name);
                    }
                }
            }
            
            if(Object.keys(invalid_fields).length > 0 && check_fields){

                let several_fields = invalid_fields.length > 1;

                let msg = '';
                let fld_name = invalid_fields[0];
                if(several_fields){
                    msg = 'Several fields referenced by this facet filter are no longer part of their respective record type(s).';
                }else{
                    msg = (window.hWin.HEURIST4.util.isempty(fld_name) ? 'A field' : `The field ${fld_name}`)
                        + ' referenced in this facet filter is no longer part of the record type on which this filter is based.';
                }

                if(msg !== ''){
                    msg += '<br>Please edit the facet search and remove the field (this will occurr automatically if you open the facet filter for editing and save)';
                    window.hWin.HEURIST4.msg.showMsgDlg(msg, null, {title: 'Missing field(s) referenced in facet filter'}, {default_palette_class: this.options.is_publication ? 'ui-heurist-publish' : 'ui-heurist-explore'});
                }

                this._warned_missing_fields = true;
            }
        }
        
        this._current_query = window.hWin.HEURIST4.query.mergeHeuristQuery(
                            (this._use_sup_filter)?this.options.params.sup_filter:'', 
                            //this.options.params.add_filter,
                            this._prepareSpatial(this.options.params.spatial_filter));
                            
       
       // create list of queries to search facet values 
        this._initFacetQueries();
        
       
       if(this.facets_list) this.facets_list.empty();
       
       let $fieldset = $("<fieldset>").css({'font-size':'0.9em','background-color':'white', padding:'0 5px'})
                    .addClass('fieldset_search').appendTo(this.facets_list);

       //hide submit button will be displayed in case all fields are input fields (not facets)
       this.btn_submit.hide();
       
        if(this.options.ispreview || !this.options.showclosebutton){
            this.btn_close.hide(); 
        }else{
            this.btn_close.show(); 
        }
        
        this._refreshTitle();

        if(this.btn_reset) this.btn_reset.hide()   
        this.btn_save.hide(); 
       
       
       that._input_fields = {};
       
      
       //add toggle for supplementary filter
       if(this.options.params.sup_filter){
           if(this.options.params.ui_prelim_filter_toggle){
               
               if(this._use_sup_filter==null){
                    this._use_sup_filter = (that.options.params.ui_prelim_filter_toggle_mode==0);
               }
               
               let lbl = window.hWin.HRJ('ui_prelim_filter_toggle_label', this.options.params, this.options.language);
               if(!lbl) lbl = window.hWin.HR('filter_facet_apply_preliminary');
               
               let ele = $("<div>").html(
                            '<h4 style="margin:0;"><div class="input-cell" style="display:block;">'
                                +'<input type="checkbox" '+(((this.options.params.ui_prelim_filter_toggle_mode==0 && this._use_sup_filter)
                                || (this.options.params.ui_prelim_filter_toggle_mode!=0 && !this._use_sup_filter))
                                ?'checked':'')+'/>'                            
                                +lbl
                            +'</h4>').css({'border-bottom': '1px solid lightgray'}).appendTo($fieldset);
                            
               this._on( ele.find('input[type="checkbox"]'), { change:                         
               function(event){
                   
                   if(that.options.params.ui_prelim_filter_toggle_mode==0){
                        that._use_sup_filter = $(event.target).is(':checked');    
                   }else{
                        that._use_sup_filter = !$(event.target).is(':checked');                   
                   }
                   
                   that.doSearch();
               }});             
           }else{
               this._use_sup_filter = true;
           }
       }
       
       if(this.options.params.ui_spatial_filter){
           
           let lbl = window.hWin.HRJ('ui_spatial_filter_label', this.options.params, this.options.language);
           if(!lbl) lbl = window.hWin.HR('filter_facet_mapsearch');
                        
           let ele = $("<div>").html(
           '<div class="header" title="" style="vertical-align: top; display: block; width: 100%; padding: 5px;">'
                +'<h4 style="display:inline-block;margin:0;">'+lbl+'</h4></div>'
                +'<div style="padding-left:21px;display:inline-block;width:100%" class="spatial_filter">' 
                    +'<div style="float:left;max-height:120px;">' //class="input-div" 
                    +'<img class="map_snapshot" style="display:none;width:150px"/>'
                    +'</div>'
                    +'<div style="display:inline-block;">'
                        +'<button title="'+ window.hWin.HR('filter_facet_mapsearch_hint')
                        +'" class="ui-button ui-corner-all ui-widget define_spatial" style="height:20px">'
                            +'<span class="ui-button-icon ui-icon ui-icon-globe"></span>'
                            +'&nbsp;<span class="ui-button-text" style="font-size:11px">'
                            +window.hWin.HR('Define')+'</span></button>'
                        +'<button title="Click this button to reset spatial search limits" class="smallbutton ui-button ui-corner-all ui-widget reset_spatial  ui-button-icon-only" style="display:none;">'
                            +'<span class="ui-button-icon ui-icon ui-icon-arrowreturnthick-1-w"></span>'
                            +'<span class="ui-button-text"> </span></button>'                    
                    +'</div>'    
                    +'</div>').css({'border-bottom': '1px solid lightgray','margin-right':'10px',
                                    'margin-bottom':'25px', 'padding-bottom':'5px'}).appendTo($fieldset);
                    
           let btn_reset2 = $( "<button>", {title:window.hWin.HR('filter_facet_resetall_hint') })
           .css({float:'right','margin-top':'10px','margin-right':'2px'})
           .appendTo( ele )
           .button({label: window.hWin.HR('filter_facet_resetall'), icon: 'ui-icon-arrowreturnthick-1-w', iconPosition:'end' });
           this._on( btn_reset2, { click: "doResetAll" });

           if(this.btn_reset){
               this.btn_reset.hide();
              
           }

           this._on( ele.find('button.reset_spatial'), { click:                         
           function(event){
               __setUiSpatialFilter(null, null);
                //ele.find('input').val('');  
                /*that.element.find('.map_snapshot').attr('src',null).hide();
                that.element.find('.define_spatial').show();
                that.element.find('.reset_spatial').hide();
                that.options.params.spatial_filter = null;
                that.ui_spatial_filter_image = null;
                */
                that.doSearch();
           }});
           
           function __setUiSpatialFilter(wkt, imagedata){

                if( wkt ){
                    if(imagedata){
                        that.ui_spatial_filter_image = imagedata;
                       
                        that.element.find('.map_snapshot').attr('src',imagedata).show();
                        that.element.find('.define_spatial').hide();
                    }else{
                        that.ui_spatial_filter_image = null;
                       
                        that.element.find('.map_snapshot').attr('src',null).hide();
                        that.element.find('.define_spatial').show();
                    }
                    that.element.find('.reset_spatial').show();
                    
                    that.options.params.spatial_filter = (wkt['geo']) ?wkt :{geo:wkt}; 
                }else{
                    that.element.find('.map_snapshot').attr('src',null).hide();
                    that.element.find('.define_spatial').show();
                    that.element.find('.reset_spatial').hide();
                    that.options.params.spatial_filter = null;
                    that.ui_spatial_filter_image = null;
                }
           }
           
           this._on( [ele.find('button.define_spatial')[0],that.element.find('.map_snapshot')[0]],
            { click:                         
           function(event){
                
                //open map digitizer - returns WKT rectangle 
                let rect_wkt = that.options.params.spatial_filter
                        ?that.options.params.spatial_filter
                        :that.options.params.ui_spatial_filter_initial;
                
                if(rect_wkt && rect_wkt['geo']){
                    rect_wkt = rect_wkt['geo'];
                }
                let url = window.hWin.HAPI4.baseURL 
                +'viewers/map/mapDraw.php?db='+window.hWin.HAPI4.database;

                let wkt_params = {wkt: rect_wkt, geofilter:true, need_screenshot:true};

                window.hWin.HEURIST4.msg.showDialog(url, {height:'540', width:'600',
                    window: window.hWin,  //opener is top most heurist window
                    dialogid: 'map_digitizer_filter_dialog',
                    params: wkt_params,
                    title: window.hWin.HR('filter_facet_spatial_search'),
                    class:'ui-heurist-bg-light',
                    callback: function(location){
                        if( !window.hWin.HEURIST4.util.isempty(location) ){
                            __setUiSpatialFilter(location.wkt, location.imgData);
                            that.doSearch();
                        }
                    }
                } );
           }});   
           
           if(that.options.params.ui_spatial_filter_init && !that.options.params.spatial_filter){
               that.options.params.spatial_filter = that.options.params.ui_spatial_filter_initial;
               that.options.params.ui_spatial_filter_init = false;
           }
           
           __setUiSpatialFilter(that.options.params.spatial_filter, that.ui_spatial_filter_image);
           
       }

       if(this.options.params.ui_additional_filter){
           
           let lbl = window.hWin.HRJ('ui_additional_filter_label', this.options.params, this.options.language);
           if(!lbl) lbl = window.hWin.HR('filter_facet_general_search'); 
                        
                        
          
           
           let ele = $("<div>").html(
           '<div class="header" title="" style="vertical-align: top; display: block; width: 100%; padding: 5px;">'
                +'<h4 style="display:inline-block;margin:0;">'+lbl+'</h4></div>'
                +'<div style=" padding:5px 0 20px 21px;display: block;">'
                    +'<div class="input-div" style="display: inline-block;padding:0px">'
                    +'<input class="ui-widget-content ui-corner-all" style="width: 150px;">'
                    +'</div><button title="'
                    + window.hWin.HR('filter_facet_reset')
                    + '" class="smallbutton ui-button ui-corner-all ui-widget ui-button-icon-only">'
                        +'<span class="ui-button-icon ui-icon ui-icon-search"></span><span class="ui-button-icon-space"> </span></button>'
                    +'</div>').css({'border-bottom': '1px solid lightgray','margin-bottom':'10px'}).appendTo($fieldset);
           
           let w = that.element.width();
           if(!(w>0) || w<200) w = 200;
           ele.find('input').removeClass('textarea, ui-widget-content')
                        .addClass('ui-selectmenu-button') //thin border
                        .css({'background':'none',
                              'width':'auto',
                              'max-width': (w-90)+'px',
                              'min-width':'100px'});
                        
           /*ele.find(".start-search") class="input-cell"
                        .button({icon:"ui-icon-search", showLabel:false})
                        .attr('title', window.hWin.HR('Start search'))
                        .css({'height':'16px', 'width':'16px'})*/
                        
           this._on( ele.find('button'), { click:                         
           function(event){
               that.options.params.add_filter = ele.find('input').val();
               //$(event.target).parents('.input-cell').find('input').val();  

               that.doSearch();
           }});   
           
           window.hWin.HEURIST4.ui.disableAutoFill(ele.find('input'));
           
           this._on( ele.find('input'), {
                            keypress:
                            function(e){
                                let code = (e.keyCode ? e.keyCode : e.which);
                                if (code == 13) {
                                    that.options.params.add_filter = $(e.target).val();
                                    window.hWin.HEURIST4.util.stopEvent(e);
                                    e.preventDefault();
                                    that.doSearch();
                                }
                            }});
           
                     

       }
       
       
       $.each(this.options.params.facets, function(idx, field){
       
          let codes = field['code'].split(':');
          let j = 0;
          let harchy = [];
          while (j<codes.length){
               harchy.push($Db.rty(codes[j],'rty_Name'));
               j = j + 2;
          }
          
          harchy = '<span class="truncate" style="display:inline-block;width:99%;font-weight:normal !important">'
                + harchy.join(" &gt; ") + "</span><br>&nbsp;&nbsp;&nbsp;";
           
           if(!window.hWin.HEURIST4.util.isnull(field['var']) && field['code'] ){
               
             let facet_title = window.hWin.HEURIST4.util.htmlEscape(window.hWin.HRJ('title', field, that.options.language));
             let facet_rollover = window.hWin.HEURIST4.util.htmlEscape(window.hWin.HRJ('help', field, that.options.language));
             if(!facet_rollover) facet_rollover = '';
               
             if(field['isfacet']!=that._FT_INPUT){

                let $container = $("<div>",{id: "fv_"+field['var'] }).html(      //!!!!
                    '<div class="header" title="'+facet_rollover+'" data-index="'+idx+'">'   // style="width: 100%; background-color: lightgray; padding: 5px; width:100%"
                          +(that.options.params.title_hierarchy?harchy:'')
                          +'<h4 style="display:inline-block;margin:0;">'
                          + facet_title + '</h4>'+  //field['order']+'  '+
                          ((facet_rollover)?'<span class="bor-tooltip ui-icon ui-icon-circle-help" '
                          +'style="width:17px;height:17px;margin-left:4px;display:inline-block;vertical-align:text-bottom;" title="'
                          +facet_rollover+'"></span>':'')+
                    '</div>'+
                    '<div class="input-cell" style="display:block;border:none;background:none;"></div>').appendTo($fieldset);    //width:100%

                // Setup as an accordion
                if(that.options.params.accordion_view){
                    $container.accordion({
                        collapsible: true,
                        heightStyle: 'content'
                    });
                    if(that.options.params.show_accordion_icons === false){ // hide expand/collapse icons
                        $container.accordion('option', 'icons', false);
                    }
                    if(field['accordion_hide'] === true){
                        $container.accordion('option', 'active', false);
                    }
                }
            }else{
                 //instead of list of links it is possible to allow enter search value directly into input field
                 let rtid = field['rtid'];
                 if(rtid.indexOf(',')>0){  //if multirectype use only first one
                        rtid = rtid.split(',')[0];
                 }
                 
                 let dty_ID = field['id'];
                 if(dty_ID.indexOf('r.')==0){
                    dty_ID = dty_ID.substr(2);    
                 }
                 
                 let fld_type = (field['type'] == 'blocktext') ? 'freetext' : field['type'];
                 let ed_options = {
                                varid: field['var'],  //content_id+"_"+
                                recID: -1,
                                rectypeID: rtid,
                                dtID: dty_ID,
                                
                                values: [''],
                                readonly: false,
                                title:  (that.options.params.title_hierarchy?harchy:'')
                                        + "<span style='font-weight:bold'>" + facet_title + "</span>",
                                detailtype: fld_type,//overwrite detail type from db (for example freetext instead of memo)
                                showclear_button: false,
                                showedit_button: false,
                                suppress_prompts: true,  //supress help, error and required features
                                suppress_repeat: (fld_type == 'freetext' && field['multisel']) ? 'force_repeat' : true,
                                is_faceted_search: true,
                                onrecreate: () => {

                                    if(fld_type != 'freetext'){
                                        return;
                                    }

                                   
                                    if(!Object.hasOwn(that._input_fields,'$X'+field['var'])){ //  $field == null || $field.length == 0
                                        return;
                                    }

                                    let $fields = that._input_fields['$X'+field['var']].find('.input-div');
                                    if($fields.length < 1){
                                        return;
                                    }

                                    let $org_last = $fields.eq(-2);
                                    let $new_fld = $fields.last();

                                    $org_last.after(() => {
                                        return "<br><br>";
                                    }); // add space between inputs

                                    let $new_input = $new_fld.find('input.ui-widget-content'); // new text input

                                    that._handleNewInput($new_input);

                                    // Add extra customisation
                                    let w = that.element.width();
                                    if(!(w>0) || w<200) w = 200;

                                    $new_input.removeClass('ui-widget-content').addClass('ui-selectmenu-button')
                                            .css({
                                                'background':'none',
                                                'width':'auto',
                                                'max-width': `${w-90}px`,
                                                'min-width':'100px'
                                            });
                                }
                        };
                        
                   if(isNaN(Number(dty_ID))){
                       ed_options['dtFields'] = {
                           dty_Type: field['type'],
                           rst_RequirementType: 'optional',
                           rst_MaxValues: 1,   //non repeatable
                           rst_DisplayWidth:0
                           //rst_DisplayHelpText: facet_rollover
                       };
                   }

                    let inpt = $("<div>",{id: "fv_"+field['var'] }).editing_input(   //this is our widget for edit given fieldtype value
                            ed_options
                        );

                    inpt.appendTo($fieldset);
                    that._input_fields['$X'+field['var']] = inpt;
                    
                   
                    inpt.find('.header').attr('title', facet_rollover)
                        .css({'font-size':'','display':'block'})
                        .html('<h4 style="display:inline-block;margin:0;">'+facet_title
                        
                        +((facet_rollover)?'<span class="bor-tooltip ui-icon ui-icon-circle-help" '
                          +'style="width:17px;height:17px;margin-left:4px;display:inline-block;vertical-align:text-bottom;" title="'
                          +facet_rollover+'"></span>':'')                        
                        +'</h4>');
                        
                    inpt.find('.input-cell').css({
                        border: 'none',
                        background: 'none',
                        display: 'block'
                    });
                    // Setup as an accordion
                    if(that.options.params.accordion_view){

                        // remove/move to avoid confusing the accordion
                        if(ed_options.suppress_repeat === true){
                            inpt.find('.editint-inout-repeat-button').remove();
                        }else{
                            let $rep_ele = inpt.find('.editint-inout-repeat-button').parent();
                            if(!$rep_ele.parent().hasClass('input-cell')){
                                $rep_ele.prependTo(inpt.find('.input-cell'));
                            }
                        }

                        inpt.accordion({
                            collapsible: true,
                            heightStyle: 'content'
                        });
                        if(that.options.params.show_accordion_icons === false){ // hide expand/collapse icons
                            inpt.accordion('option', 'icons', false);
                        }
                        if(field['accordion_hide'] === true){
                            inpt.accordion('option', 'active', false);
                        }
                    }

                    if(inpt.find('.editint-inout-repeat-button').length > 0){

                        let btn_container_css = {
                            'min-width': '15px', 
                            'display': 'inline-block',
                            'width': 'auto'
                        };

                        if(that.options.params.accordion_view){
                            btn_container_css['display'] = 'block';
                            btn_container_css['padding-left'] = '0px';
                        }
                                                               
                        inpt.find('.header').css(btn_container_css);         //padding-top
                        inpt.find('.editint-inout-repeat-container')
                            .css(btn_container_css)
                            .css({'padding-top':'15px'});
                        inpt.find('.editint-inout-repeat-button').css({
                            'min-width': '15px',
                            'margin': '0px',
                            'padding-left': '3px'
                        });
                    }
           
                    that._handleNewInput(inpt.find('input,select'));
                    
                    
                    let w = that.element.width();
                    if(!(w>0) || w<200) w = 200;
                    
                    inpt.find('input,select,span[role="combobox"]').removeClass('textarea, ui-widget-content')
                        .addClass('ui-selectmenu-button') //thin border
                        .css({'background':'none',
                              'width':'auto',
                              'max-width': (w-90)+'px',
                              'min-width':'100px'});

                    let btn_add = $( "<button>",{title:window.hWin.HR('filter_facet_resetall_hint')})
                        .addClass("smallbutton")
                        .insertBefore( inpt.find('.input-cell .heurist-helper1') )
                        .button({icon: "ui-icon-search", showLabel:false});

                    that._on( btn_add, { click: "doSearch" });
             }
           }
       });
       
       //'background-color': 'lightgray', 
       //2023-05-02 
       $fieldset.find('.header').css({width: 'auto', padding: '15px 0px 5px 0px'});
       if(this.options.params.ui_separate_line){
            $fieldset.find('.header').parent().css('border-top',$fieldset.find('.ui-widget-content:first').css('border'));    
       }
       
       //2023-05-02 
       $fieldset.find('.input-cell').css({ 'padding':'5px 0px' });
       
       $fieldset.find('.bor-tooltip').tooltip({
            position: { my: "center bottom", at: "center top-5" },
            /* does not work
            classes: {
                "ui-tooltip": "ui-corner-all tooltip-inner"
            },*/
            tooltipClass:"tooltip-inner",
            hide: { effect: "explode", duration: 500 }
        });
       
       this._isInited = true;
       //get empty query
       this._first_query = window.hWin.HEURIST4.util.cloneJSON( this.options.params.q ); //clone 
       this._fillQueryWithValues( this._first_query );
            
        if(this.options.params.search_on_reset || 
           !window.hWin.HEURIST4.util.isempty(this.options.params.add_filter)){
            //search at once - even none facet value provided
            this.doSearch();
        }else{
            this.no_value_facets = []; // reset no value facets
            this._expanded_count_order = [];
            this._expanded_count_facets = {};
            this._recalculateFacets(-1);     
            
            //trigget empty fake event to update messge in result list
            $(this.document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH, 
                [ {empty_remark:'<div style="padding:10px;"><h3><span class="ui-icon ui-icon-arrowthick-1-w"></span>'
                +'Please select from facets on left</h3></div>', search_realm:this.options.search_realm, is_facet: true} ]);
        }
       
    }

    //
    // Apply events and styling to normal input and multi-select
    //
    ,_handleNewInput: function ($input){

        let that = this;
        let is_select = $input.is('select');
        let $input_div = $input.parent();
        let $input_cell = $input_div.parent();

        //@todo make as event listeneres
        //assign event listener
        if(!is_select){
            that._on( $input, {
                keypress: function(e){
                    let code = (e.keyCode ? e.keyCode : e.which);
                    if (code == 13) {
                        window.hWin.HEURIST4.util.stopEvent(e);
                        e.preventDefault();
                        that.doSearch();
                    }
                },
                keyup: function(e){
                    let btn_reset = $input_div.prev();
                    if($(e.target).val()==''){
                        btn_reset.css('visibility','hidden');   
                    }else{
                        btn_reset.css('visibility','visible');   
                    }
                }
            });
        }else{
            that._on( $input, {
                change: function(e){
                    let btn_reset = $input_div.prev();
                    if($(e.target).val()==''){
                        btn_reset.css('visibility','hidden');   
                    }else{
                        btn_reset.css('visibility','visible');   
                    }
                    that.doSearch();
                }
            });
        }

        $input_div.css({'display':'inline-block',padding:0}); // !important

        //since it takes default width from field definitions
        //force width for direct input and selectors to 150px
        let w = that.element.width();
        if(!(w>0) || w<200) w = 200;

        let input_style = {};
        if(is_select){
            input_style = {'width':(w-45)+'px','min-width': (w-45)+'px'}; // was 30
        }else{
            input_style = {'width':(w-70)+'px','max-width':(w-70)+'px','min-width':'auto'};
        }

        $input.removeClass('text ui-widget-content').css(input_style);

        let btn_clear = $( "<span>")  //for direct input
            .insertBefore( $input_div )
            .addClass("ui-icon ui-icon-arrowreturnthick-1-w resetbutton")
            .css({'display':'inline-block', 'visibility':'hidden', 'font-size':'11px', 'vertical-align':'middle'});//1.2em

        that._on( btn_clear, { click: function(){
            $input.val('');

            if($input_div.parent().find('.input-div').length > 1){
                let i = 0;
                while(i < 3){
                    $input_div.prev().remove();
                    i++;
                }
                $input_div.remove();
            }else{
                $input_div.prev().css('visibility','hidden');
            }

            that.doSearch();
        } });
    }

    ,doSaveSearch: function(){
       
    }

    ,doClose: function(){
        //$(this.document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, [ {reset:true, search_realm:this.options.search_realm} ]);  //global app event to clear views
        this._trigger( "onclose");
        if(window.hWin.HEURIST4.util.isFunction(this.options.onclose)){
            this.options.onclose(this);
        }
        if(!this.options.is_publication){
            setTimeout((context) => { $(context.document).trigger(window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE); }, 500, this);
        }
    }


    ,doRender: function(){


    }
    
    //
    // return array of pairs - code:value
    //
    ,getFacetsValues: function(){
        
        let _inputs = this._input_fields;
        let res = [];
        
        let facets = this.options.params.facets;
        let facet_index, len = facets.length;
        for (facet_index=0;facet_index<len;facet_index++){

                if(facets[facet_index]['isfacet']==this._FT_INPUT){  //this is direct input
                
                     let val = '$X'+facets[facet_index]["var"];
                     let sel = $(_inputs[val]).editing_input('getValues');
                     if(sel && sel.length>0){
                         facets[facet_index].selectedvalue = {value:sel[0]};
                     }else{
                         facets[facet_index].selectedvalue = null;
                     }
                }
                
                let selval = facets[facet_index].selectedvalue;
                
                if(selval && !window.hWin.HEURIST4.util.isempty(selval.value)){
                    res.push({code:facets[facet_index].code, value:selval.value});
                }

        }//for

        return res;                
    }
    
    // we have a query (that searches main recordtype) it is created once (onReset)
    // for example {"t": "3"},{"f:1":"$X73105"},{"linked_to:4:15":[{t: "4"},{f:22: "$X47975"}]}
    // $Xn - is facet values to be substituted in this query
    //
    // 1. substitute Xn variable in query array with value from input form
    // 2. remove branch in query if all variables are empty (except root)
    //
    //  facet_index_do_not_touch - $Xn will be relaced with $FACET_VALUE - to use on server side for count calculations
    // 
    ,_fillQueryWithValues: function( q, facet_index_do_not_touch ){
        
        let _inputs = this._input_fields;
        let that = this;
        let isbranch_empty = true;

        let predicates_to_add = [];

        //$(q).each(function(idx, predicate){ 
        // [{"f:1":$X680},....]
        
        //loop through all predicates of main query 
        let idx = 0
        while (idx<q.length){
            
            let predicate = q[idx];
        
            $.each(predicate, function(key,val)
            {
                if( Array.isArray(val) ) { //|| $.isPlainObject(val) ){
                    let is_empty = that._fillQueryWithValues(val, facet_index_do_not_touch);
                    isbranch_empty = isbranch_empty && is_empty;

                    if(is_empty){
                        //remove entire branch if none of variables are defined
                        delete predicate[key];  
                    }
                }else{
                    if(typeof val === 'string' && val.indexOf('$X')===0){ //replace $xn with facet value
                        
                        //find facet by variable 
                        let facets = that.options.params.facets;
                        let facet_index, len = facets.length;
                        for (facet_index=0;facet_index<len;facet_index++){
                            if(facets[facet_index]["var"] == val.substring(2)){ //find facet by variable

                                if(facets[facet_index]['isfacet']==that._FT_INPUT){  //this is direct input
                                    let sel = $(_inputs[val]).editing_input('getValues');
                                    if(sel && sel.length>0){

                                        let next_idx = false;
                                        for (let k = 0; k < sel.length; k++) {

                                            let val = sel[k];
                                            let search_all_words = false;
                                            let pred = [];

                                            if(val.length>2 && val[0]=='"' && val[val.length-1]=='"'){
                                                val = val.substring(1,val.length-1);
                                            }else if(!window.hWin.HEURIST4.util.isempty(val) && (val.indexOf(' ')>0 || sel.length > 1)){
                                                search_all_words = true;
                                            }
                                            
                                            facets[facet_index].selectedvalue = {value:val}; // TODO - Handle multivalue, store in selectedvalue.value as comma list
    
                                            //search for words, ANDed by default check for OR and handle as needed
                                            let values = val.split(' ');
                                            if(search_all_words && (values.length > 1 || sel.length > 1)){

                                                if(values.length == 1){ // multi-input value

                                                    pred.push({[key]: val});

                                                    next_idx = true;

                                                    continue;
                                                }
                                                
                                                let predicates = [];
                                                let i=0;
                                                while (i<values.length) {
                                                    let pred_parts = {};
                                                    if(window.hWin.HEURIST4.util.isempty(values[i])
                                                        || values[i].toLowerCase() == 'or' 
                                                        || values[i].toLowerCase() == 'and'){ // Ignore the AND, follows default behaviour
    
                                                        if(values[i].toLowerCase() == 'or' && values[i-1] && values[i+1]){ // check that both sides of the OR exist
    
                                                            let or_pred = [];
                                                            let j = i-1; // starting point
    
                                                            for(; j < values.length; j++){
    
                                                                let pre_temp = {};
                                                                
                                                                if(window.hWin.HEURIST4.util.isempty(values[j]) || values[j].toLowerCase() == 'and'){ // end of OR statement
                                                                    break;
                                                                }else if(values[j].toLowerCase() != 'or'){
                                                                    pre_temp[key] = values[j];
                                                                    or_pred.push(pre_temp);
                                                                }
                                                            }
    
                                                            if(or_pred.length > 1){ // add to query
                                                                pred.push({"any":or_pred});
                                                                i = j-1; // outter search's continuing point
                                                                continue;
                                                            }
                                                        }
                                                    }else if(!(values[i+1] && values[i+1].toLowerCase() == "or")){ // Skip if predicate is part of an OR statement
                                                            pred_parts[key] = values[i];
                                                            predicates.push(pred_parts);
                                                    }
                                                    i++;
                                                }//while

                                                if(predicates.length > 0){
                                                    pred.push(...predicates);
                                                }
                                            }

                                            if(pred.length > 0){
                                                predicates_to_add.push({"all": pred});
                                                next_idx = true;
                                            }
                                        }

                                        if(next_idx){

                                            isbranch_empty = false;
                                            delete predicate[key];

                                            continue;
                                        }
                                        
                                    }else{
                                        facets[facet_index].selectedvalue = null;
                                    }
                                }else if(facet_index_do_not_touch==facet_index){ //this is for count calculation query
                                    predicate[key] = '$FACET_VALUE';
                                    isbranch_empty = false;
                                    break;
                                }
                                
                                let selval = facets[facet_index].selectedvalue;
                                
                                if(selval && !window.hWin.HEURIST4.util.isempty(selval.value)){
                                    if(facets[facet_index].multisel){
                                        let vals = selval.value.split(',');
                                        for(let k=0;k<vals.length; k++){
                                            if(k==0){
                                                predicate[key] = vals[k];
                                            }else{
                                                let new_pred = {};
                                                new_pred[key] = vals[k];
                                                q.splice(idx,k==0?1:0,new_pred);
                                                idx++;
                                            }
                                            
                                        }
                                    }else{
                                        
                                        //search for dates grouped by
                                        if(facets[facet_index].type=='date' && facets[facet_index].groupby){
                                            if(selval.value.indexOf('<>')<0 && selval.value.indexOf('><')<0){
                                                // <> - range in database overlaps the specified interval
                                                // >< - range in database between/within the specified interval
                                                let op_compare = facets[facet_index].srange=='between'?'><':'<>';
                                                
                                                let nyear = Number(selval.value);
                                                
                                                if(facets[facet_index].groupby=='month'){
                                                    let y_m = selval.value.split('-');
                                                    selval.value = y_m[0]+'-'+y_m[1]+'-01'+op_compare+y_m[0]+'-'+y_m[1]+'-31';

                                                }else if(facets[facet_index].groupby=='year'){
                                                    
                                                    if(nyear>0) nyear = (nyear+'-12-31');
                                                    selval.value = selval.value + op_compare + nyear;
                                                    
                                                }else if(facets[facet_index].groupby=='decade'){
                                                    nyear = nyear+9;
                                                    if(nyear>0) nyear = (nyear+'-12-31');
                                                    selval.value = selval.value + op_compare + nyear;

                                                }else if(facets[facet_index].groupby=='century'){
                                                    nyear = nyear+99;
                                                    if(nyear>0) nyear = (nyear+'-12-31');
                                                    selval.value = selval.value + op_compare + nyear;
                                                }
                                            }
                                        }
                                        predicate[key] = selval.value;
                                    }
                                    isbranch_empty = false;
                                }else{
                                    delete predicate[key];
                                }
                                
                                break;
                            }
                        }

                    }
                }
            });//each

            idx++;    
        }//while

        if(predicates_to_add.length > 0){
            q.push(...predicates_to_add);
        }

        idx = 0
        while (idx<q.length){
            if(Object.keys(q[idx]).length==0){
                q.splice(idx, 1);
            }else{
                idx++;
            }
        }

        return isbranch_empty;
    }
    
    //
    //
    //
    ,doSearch: function(){

        let query = window.hWin.HEURIST4.util.cloneJSON( this.options.params.q ); //clone 
        let isform_empty = this._fillQueryWithValues(query);
        
        if(isform_empty && 
            window.hWin.HEURIST4.util.isempty(this.options.params.add_filter) && 
            window.hWin.HEURIST4.util.isempty(this.options.params.spatial_filter) &&
            !this.options.params.search_on_reset){
            
            //clear main result set
            this.doReset();
            
        
            return; 
        }else if(!this.options.ispreview && this.options.showresetbutton && !this.options.params.ui_spatial_filter){
           
            if(this.btn_reset) this.btn_reset.show()   
            //@todo this.btn_save.show(); 
        }
        
        let div_facets = this.facets_list.find(".facets");
        if(div_facets.length>0)  div_facets.empty();
        
        
        let search_any_filter = window.hWin.HEURIST4.util.isJSON(this.options.params.add_filter);
        if(search_any_filter==false){
            if(this.options.params.add_filter){
                //check that this is not old search format
                let s = this.options.params.add_filter;
                let colon_pos = s.indexOf(':');
                if(colon_pos>0){
                   
                    search_any_filter = s;
                }
                    
                if(!search_any_filter) search_any_filter = {f:s};
            }else{
                search_any_filter = '';
            }
        }

        //this approach adds supplemntary(preliminary) filter to every request 
        //it works however 
        //1) it requires that this filter must be a valid json  - FIXED
        //2) it makes whole request heavier
        //adds additional/supplementary and spatial filters
        this._current_query = window.hWin.HEURIST4.query.mergeHeuristQuery(query, 
                        (this._use_sup_filter)?this.options.params.sup_filter:'', 
                        search_any_filter,
                        this._prepareSpatial(this.options.params.spatial_filter),
                        
                        (isform_empty && this.options.params.ui_temporal_filter_initial)
                            ?this.options.params.ui_temporal_filter_initial: ''
                        );

//{"f:10":"1934-12-31T23:59:59.999Z<>1935-12-31T23:59:59.999Z"}            
        let sort_clause;
        if(this.options.params.sort_order){
            
            sort_clause = {sortby:this.options.params.sort_order};
        
        }else if(window.hWin.HAPI4.database=='johns_hamburg' &&
            //special order by date fields 
            window.hWin.HEURIST4.util.findArrayIndex(this.options.svs_ID,[21,23,24])>=0){
                
            sort_clause = {sortby:'hie'};
            
        }else {
            sort_clause = {sortby:'t'};
        }

        this._current_query.push(sort_clause);

        let request = { q: this._current_query, 
                        w: this.options.params.domain, 
                        detail: 'ids', 
                        source:this.element.attr('id'), 
                        qname: this.options.query_name,
                        rules: this.options.params.rules,
                        rulesonly: this.options.params.rulesonly,
                        viewmode: this.options.params.ui_viewmode,
                        //to keep info what is primary record type in final recordset
                        primary_rt: this.options.params.rectypes[0],
                        ispreview: this.options.ispreview,
                        search_realm: this.options.search_realm,
                        search_page: this.options.search_page,
                        facet_value: this._last_term_value
                    }; //, facets: facets
                        
        if(this.options.ispreview){
            request['limit'] = 1000;    
        }

        this._expanded_count_cancel = this._expanded_count_order.length > 0;

        //perform search
        window.hWin.HAPI4.RecordSearch.doSearch( this, request );
        
        //perform search for facet values
       
    }
    
    //-------------------------------------------------------------------------------
    //
    // called on ON_REC_SEARCH_FINISH
    // perform search for facet values and counts and redraw facet fields
    // @todo query - current query - if resultset > 1000, use query
    // _recalculateFacets (call server) -> as callback _redrawFacets -> _recalculateFacets (next facet)
    //
    , _recalculateFacets: function(field_index){
     
//@todo need to check that the sequence is not called more than once - otherwise we get multiple controls on facets
        
       
        // this._currentquery
        // this._resultset
        if(isNaN(field_index) || field_index<0){
            //first call
            field_index = -1;  
            
            this._request_id =  window.hWin.HEURIST4.util.random();
        
            this._terminateFacetCalculation = false;
            this.btn_terminate.show();
            this.div_toolbar.find('#facet_process_msg').show();
            if(this.btn_reset) this.btn_reset.hide()    
            this.btn_close.hide();

            let div_facets = this.facets_list.find(".facets");
            if(div_facets.length>0)
                div_facets.empty()
                .css('background','url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white20.gif) no-repeat center center');

            // Re-display all facets
            this.facets_list.find("[id^='fv_']").show();
        }
        if(this._terminateFacetCalculation){
            field_index  = this.options.params.facets.length;
        }
        
        let that = this;
        
        let i = field_index;
        for(;i< this.options.params.facets.length; i++)
        {
            let field = this.options.params.facets[i];
            
            if(i>field_index && field['isfacet']!=that._FT_INPUT && field['facet']){
                
                if(field['type']=='enum' && field['groupby']=='firstlevel' && 
                                !window.hWin.HEURIST4.util.isnull(field['selectedvalue'])){
                        this._redrawFacets({status:window.hWin.ResponseStatus.OK,  facet_index:i}, false );
                        break;
                }
                
                let subs_value = null; //either initial query OR rectype+current result set
                
                if(this.options.params.ui_temporal_filter_initial || this._isInited || (field.multisel && field.selectedvalue!=null)){ 
                    //replace with current query   - @todo check for empty 
                    subs_value = window.hWin.HEURIST4.query.mergeHeuristQuery(this._first_query, 
                                    (this._use_sup_filter)?this.options.params.sup_filter:'',
                                    this.options.params.add_filter,
                                    this._prepareSpatial(this.options.params.spatial_filter));
                    
                }else{
                    
                    //replace with list of ids
                    subs_value = window.hWin.HEURIST4.query.mergeHeuristQuery(this._first_query,
                                        {ids:this._currentRecordset.getMainSet().join(',')});
                    
                }
                
                //
                // substitute $IDS in facet query with list of ids OR current query(todo)
                // 
                function __fillQuery(q){
                            $(q).each(function(idx, predicate){
                                
                                $.each(predicate, function(key,val)
                                {
                                        if( Array.isArray(val) || $.isPlainObject(val) ){
                                            __fillQuery(val);
                                         }else if( (typeof val === 'string') && (val == '$IDS') ) {
                                            //substitute with array of ids
                                            predicate[key] = subs_value;
                                         }
                                });                            
                            });
                }

                //get other parameters for given rectype
                function __getOtherParameters(query, rt){
                    
                            let res = null;

                            if(Array.isArray(query)){
                            
                            $(query).each(function(idx, predicate){
                                
                                $.each(predicate, function(key,val)
                                {
                                        if(key=='t' && val==rt){

                                            query.splice(idx,1);
                                            res = query
                                            return false;
                                            
                                        }else if( Array.isArray(val) || $.isPlainObject(val) ){
                                            
                                            res = __getOtherParameters(val, rt);
                                            return false;
                                        }
                                });                            
                            });
                            
                            }
                    
                            return res;
                    
                }
     
                let query, needcount = 2;
                if( (typeof field['facet'] === 'string') && (field['facet'] == '$IDS') ){ //this is field form target record type
                
                    if(this.options.params.ui_temporal_filter_initial || this._isInited){
                        //replace with current query   - @todo check for empty 
                        query = this._first_query;

                        //add additional/supplementary filter
                        query = window.hWin.HEURIST4.query.mergeHeuristQuery(query, 
                                        (this._use_sup_filter)?this.options.params.sup_filter:'',   //suplementary filter defined in wiz
                                        this.options.params.add_filter,
                                        this._prepareSpatial(this.options.params.spatial_filter));  //dynaminc addition filter
                        
                    }else{
                        
                        //replace with list of ids, and add rectype id

                        let rtyid = field?.rtyid !== undefined ? field.rtid : null;
                        rtyid = !rtyid ? field.code.split(':')[0] : rtyid;

                        query = {t: rtyid, ids: this._currentRecordset.getMainSet().join(',')};
                    }
                
                    needcount = 1;
                    
                }else{
                    query = window.hWin.HEURIST4.util.cloneJSON(field['facet']); //clone 
                    
                    //change $IDS for current set of target record type
                    __fillQuery(query);                
                    
                }

                let count_query = window.hWin.HEURIST4.util.cloneJSON(this.options.params.q);
                count_query = count_query.splice(1); //remove t:XX                
                
                //this is query to calculate counts for facet values
                // it is combination of a) currect first query plus ids of result OR first query plus supplementary filters
                // b) facets[i].query  with replacement of $Xn to value
                count_query = window.hWin.HEURIST4.query.mergeHeuristQuery(subs_value, count_query)

                
               
                this._fillQueryWithValues( count_query, field['multisel']?-1:i );
                        
                /* alas, ian want to get count on every step
                if( (!window.hWin.HEURIST4.util.isnull(field['selectedvalue'])) 
                    && (field['type']=="float" || field['type']=="integer" || field['type']=="date" || field['type']=="year")){  //run only once to get min/max values
                
                       let response = {status:window.hWin.ResponseStatus.OK, facet_index:i, data:[field['selectedvalue']]};
                       that._redrawFacets(response)
                       break;
                }
                */
                
                
                let fieldid = field['id'];
                if(fieldid.indexOf('r.')==0){
                    fieldid = fieldid.substr(2);    
                }
                

                let step_level = field['selectedvalue']?field['selectedvalue'].step:0;
                let vocabulary_id = null;
                if(field['type']=='enum' && field['groupby']=='firstlevel'){
                    
                    vocabulary_id = $Db.dty(fieldid, 'dty_JsonTermIDTree');    

                    //it does work for vocabularies only!
                    if(isNaN(Number(vocabulary_id)) || !(vocabulary_id>0)){
                            vocabulary_id = null;
                            field['groupby'] = null;
                    }
                }
                
                if(field['type']=='freetext'){
                    if(!field['groupby']){
                        step_level = 1;
                    }
                }else{
                    step_level = 1;
                }
        
                
                if(fieldid==9 && that._use_multifield){
                    fieldid = '9,10,11';
                }else if(fieldid==1  && that._use_multifield){
                    fieldid = '1,18,231,304';
                }
                
                let request = {q: query, count_query:count_query, w: 'a', a:'getfacets',
                                     facet_index: i, 
                                     field:  fieldid,
                                     type:   field['type'],
                                     step:   step_level,
                                     facet_type: field['isfacet'], //0 direct search search, 1 - select/slider, 2 - list inline, 3 - list column
                                     facet_groupby: field['groupby'], //by first char for freetext, by year for dates, by level for enum
                                     vocabulary_id: vocabulary_id, //special case for firstlevel group - got it from field definitions
                                     needcount: needcount,         
                                     qname:this.options.query_name,
                                     request_id:this._request_id,
                                     source:this.element.attr('id') }; //, facets: facets

                if(this.options.ispreview){
                    request['limit'] = 1000;    
                }
                                     
                // try to find in cache by facet index and query string
                
                let hashQuery = window.hWin.HEURIST4.util.hashString(JSON.stringify(request.count_query));
                let stored_counts = this._getCachedCounts( hashQuery, i );
                if(stored_counts){
                    that._redrawFacets(stored_counts, false);
                    return;
                }
                field.last_count_query = hashQuery;

                window.HAPI4.RecordMgr.get_facets(request, function(response){ 
                    
                    //ignore results of passed sequence
                    if(response.request_id != that._request_id){
                        
                        if(response.status != window.hWin.ResponseStatus.OK){
                            console.error('ERROR: get_facets', response.message);
                        }
                        return;
                    }
                    that._redrawFacets(response, true) 
                });                                            
                break;
            }

        }
        
        
        if(i >= this.options.params.facets.length){
            
            this.options.params.ui_temporal_filter_initial = null; //used only once

            this.btn_terminate.hide();
            if(this.div_toolbar.find('#facet_process_msg').attr('data-interrupt') != 1){
                this.div_toolbar.find('#facet_process_msg').hide();
            }

            if(this.element.find('.no_value_facets').length > 0){
                this.element.find('.no_value_facets').remove();
            }

            if(this.options.hide_no_value_facets && this.no_value_facets.length > 0){

                let empty_facets = [];
                for(let j = 0; j < this.no_value_facets.length; j ++){

                    let f_idx = this.no_value_facets[j];
                    let field = this.options.params.facets[f_idx];

                    let f_title = window.hWin.HEURIST4.util.htmlEscape(window.hWin.HRJ('title', field, this.options.language));

                    this.facets_list.find("#fv_" + field['var']).hide();
                   
                    empty_facets.push(f_title);
                }
                empty_facets = empty_facets.join(', ');

                let $ele = $('<div>', {title: empty_facets, class: 'no_value_facets'}).text('Empty facets:')
                          .css({
                            width: '100%',
                            padding: '10px 5px 5px',
                            'border-top': '1px black solid', // or <hr>
                            'font-size': 'smaller'
                          })
                          .appendTo(this.facets_list);

                let max_w = $ele.width() - 80;

                $('<span>', {class: 'truncate'}).css({
                    'display': 'inline-block',
                    'vertical-align': 'top',
                    'margin-left': '5px',
                    'max-width': max_w + 'px'
                }).text(empty_facets).appendTo($ele);

            }

            this._refreshButtons();

            if(this._expanded_count_order.length > 0){
                this._getExpandedFacetCount();
            }
        }
    }
    
    //
    //
    //
    , _getCachedCounts: function(hashQuery, facet_index){
        
        for (let k=0; k<this.cached_counts.length; k++){
            if( parseInt(this.cached_counts[k].facet_index) == facet_index && 
                this.cached_counts[k].count_query == hashQuery) // && this.cached_counts[k].dt == dt)
            {
                return this.cached_counts[k];
            }
        }        
        return null;
    }
 
    //
    // draw facet values
    //
    , _redrawFacets: function( response, keep_cache ) {
        
                if(this.options.params.viewport==0){
                    this.options.params.viewport = Number.MAX_SAFE_INTEGER;
                }else if(!(this.options.params.viewport>0)){
                    this.options.params.viewport = 5; //default viewport
                }
        
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    if(keep_cache && response.count_query){
                        response.count_query = window.hWin.HEURIST4.util.hashString(JSON.stringify(response.count_query));
                        this.cached_counts.push(response);
                    }
                    
                    let facet_index = parseInt(response.facet_index); 

                    let field = this.options.params.facets[facet_index];
                    
                    let $input_div = $(this.element).find("#fv_"+field['var']);
                    let needsDropdown = false;
                   

                    //create fasets container if it does not exists
                    let $facet_values = $input_div.find('.facets');
                    if( $facet_values.length < 1 ){
                        let dd = $input_div.find('.input-cell');
                        //'width':'inherit',
                        $facet_values = $('<div>').addClass('facets').appendTo( $(dd[0]) );
                        //AAA strange padding .css({'padding':'4px 0px 10px 5px'})
                    }else{
                        $facet_values.empty();
                    }
                    $facet_values.css('background','none');
                    
                    //add current value to history
                    if(window.hWin.HEURIST4.util.isnull(field.selectedvalue)){ //reset history
                        field.history = []; 
                    }else{
                        //replace/add for current step and remove that a bigger
                        if( window.hWin.HEURIST4.util.isArrayNotEmpty(field.history) ){
                            field.history = field.history.slice(0, field.selectedvalue.step);
                        }else{
                            field.history = [];
                            field.history.push({title:window.hWin.HR('all'), value:null});
                        }
                        field.history.push(field.selectedvalue);
                    }
                    //
                    //draw show more/less toggler for long lists
                    //
                    function __drawToggler($facet_values, display_mode){
                        
                        $('<div class="bor-filter-expand bor-toggler">'
                            +'<span class="bor-toggle-show-on" style="display:none;margin-bottom: 5px;"><span>&nbsp;less...&nbsp;</span></span>'
                            +'<span class="bor-toggle-show-off" style="margin-bottom: 5px;"><span>&nbsp;more...&nbsp;</span></span>'
                         +'</div>').on('click', function(event){
                                if($(event.target).is('span[class^="ui-selectmenu"],select')){
                                    return;
                                }
                                let ele = $(event.target).parents('div.bor-toggler');
                                let mode = ele.attr('data-mode');
                                let d_mode;
                                if(mode=='on'){
                                    ele.find('.bor-toggle-show-on').hide();
                                    ele.find('.bor-toggle-show-off').show();

                                    if(ele.parent().find('div span[class^="ui-selectmenu"]').length > 0){
                                        ele.parent().find('div span[class^="ui-selectmenu"]').show();    
                                    }else{
                                        ele.parent().find('div select').show();
                                    }
                                    d_mode = 'none';
                                    mode = 'off';
                                }else{
                                    ele.find('.bor-toggle-show-on').show();
                                    ele.find('.bor-toggle-show-off').hide();
                                    
                                    if(ele.parent().find('div span[class^="ui-selectmenu"]').length > 0){
                                        ele.parent().find('div span[class^="ui-selectmenu"]').hide();    
                                    }else{
                                        ele.parent().find('div select').hide();
                                    }
                                    d_mode = display_mode;
                                    mode = 'on';
                                }
                                
                                ele.parent().find('div.in-viewport').css('display',d_mode);
                                ele.attr('data-mode', mode);
                           })
                         .appendTo($facet_values)
                    }                    
               
                    let that = this;

                    let dty_ID = field['id']; 
                    if(dty_ID.indexOf('r.')==0){
                        dty_ID = dty_ID.substr(2);    
                    }

                    if((field['type']=='enum' || field['type']=='reltype') && field['groupby']!='firstlevel'){
                        
                        let is_first_level = false;
                        if(!field['step0_vals']){
                            //keep all terms with values for first level
                            field['step0_vals'] = {};  
                            is_first_level = true;
                        } 
                        
                        let term; 
                        if(dty_ID=='owner' || dty_ID=='addedby'){
                                
                                if(response.users){
                                    
                                    let users = [];
                                    for(let id in response.users){
                                        users.push({key:id, title:response.users[id]});
                                    }
                                    term = {key: null, title: "all", children:users};
                                    
                                }else{
                                    let user_ids = [];
                                    for (let j=0; j<response.data.length; j++){
                                        user_ids.push(response.data[j][0]);
                                    }
                                    window.hWin.HAPI4.SystemMgr.usr_names({UGrpID:user_ids},function(res){
                                            if(res.status==window.hWin.ResponseStatus.OK){
                                                   response.users = res.data;
                                            }else{
                                                   response.users = [];
                                            }
                                            that._redrawFacets( response, keep_cache );
                                    });
                                    return;
                                }
                            
                        }
                        else if(dty_ID=='access'){
                            
                            term = {key: null, title: "all",
                                    children:[{title:'viewable', key:'viewable'},
                                     {title:'hidden', key:'hidden'},
                                     {title:'public', key:'public'},
                                     {title:'pending', key:'pending'}]};
                            
                        }else {    
                            //enumeration
                            let vocab_id = (field['type']=='reltype')?'relation':$Db.dty(dty_ID, 'dty_JsonTermIDTree');    
                                                  
                            if(field['type']!='reltype' && !(vocab_id>0)){
                                console.error('ERROR: Field '+dty_ID+' not found');
                                //search next facet
                                this._recalculateFacets( facet_index );
                                return;
                            }
                            
                            term = $Db.trm_TreeData(vocab_id, 'tree', false, this.options.language);
                            term = {key: null, title: "all", children: term};
                            //field.selectedvalue = {title:label, value:value, step:step};                    
                        
                        }
                        
                        //
                        // verify that term is in response - take count from response
                        //
                        function __checkTerm(term){
                                let j;
                                for (j=0; j<response.data.length; j++){
                                    if(response.data[j][0] == term.key){
                                        return {title:term.title, 
                                             value:term.key, count:parseInt(response.data[j][1])};
                                    }
                                }
                                return null; //no entries
                        }
                        
                        this.terms_drawn = 0; //it counts all terms as plain list 
                        
                        //
                        // returns counts for itself and children
                        //
                        function __calcTerm(term, level, groupby){
                            
                            let res_count = 0;
                            term.supress_count_draw = false;
                            
                            if(window.hWin.HEURIST4.util.isArrayNotEmpty(term.children)){ //is root or has children

                                //find total count for this term and its children
                                let k, ch_cnt=0;
                                if(term.children)
                                for (k=0; k<term.children.length; k++){
                                    let cnt = __calcTerm(term.children[k], level+1, groupby);
                                    if(cnt>0){
                                        res_count = res_count + cnt;    
                                        ch_cnt++;
                                    }
                                }
                                
                                term.supress_count_draw = (ch_cnt==1);
                                
                                //
                                // some of children have counts 
                                // creates
                                //
                                //old way
                                /*
                                let term_value = null;
                                if(res_count>0){ 
                                    
                                    if(term.termssearch){
                                        if(term.termssearch.indexOf(term.key)<0){
                                            term.termssearch.push(term.key);
                                        }
                                        term_value = term.termssearch;
                                    }else{
                                        term_value = term.key;
                                    }
                                    if(!window.hWin.HEURIST4.util.isempty(term_value) || 
                                        !window.hWin.HEURIST4.util.isnull(field.selectedvalue)){                               
                                    
                                        term.value = term_value;
                                        term.count = 0;
                                        res_count++;
                                    
                                    }
                                }
                                */ 
                                
                                //note: sometimes value may be equal to header
                                let headerData = __checkTerm(term);
                                
                                
                                if(headerData!=null){//this term itself has counts
                                
                                        //search for this term only
                                       
                                       

                                        //search for this term and all its children
                                        
                                        if(res_count>0){
                                            term.children.push({title:'other', count:headerData.count, value:'='+headerData.value});
                                            term.value = term.termssearch?term.termssearch:term.key;
                                            term.count = res_count + headerData.count;  
                                        } else {
                                            term.value = '='+headerData.value;
                                            term.count = headerData.count;
                                        }

                                       
                                }else{
                                       
                                       
                                        let val = term.termssearch ?term.termssearch :term.key;
                                        if(res_count>0){
                                            term.value = val;
                                            term.count = res_count;
                                            
                                            if(is_first_level && val && field['multisel']){
                                                //keep counts for level 0 - to show all terms for multisel mode
                                                field['step0_vals'][val] = 1;
                                            }
                                            
                                        }else{
                                            if(!is_first_level && field['step0_vals'][val]>0){
                                                term.value = val;
                                            }else{
                                                term.value = null;
                                            }
                                            
                                            term.count = 0;
                                        }
                                    
                                }
                                
                                
                            }
                            else {
                                //no children
                                let termData =__checkTerm(term);
                                if(termData!=null){
                                    //leave
                                    term.value = termData.value;
                                    term.count = termData.count;
                                    res_count = 1; 
                                    
                                    if(is_first_level && field['multisel']){
                                        //keep counts for level 0 - to show all terms for multisel mode
                                        field['step0_vals'][term.value] = 1;
                                    }
                                    
                                }else{
                                    if(!is_first_level && field['step0_vals'][term.key]>0){
                                        term.value = term.key;
                                    }else{
                                        term.value = null;
                                    }
                                    term.count = 0;
                                }
                            }
                            
                            return term.count;
                        }//__calcTerms
                        
                        
                       
                        //calculate the total number of terms with value
                        let tot_cnt = __calcTerm(term, 0, field['groupby']);
                        term.supress_count_draw = true; //for root
                        let as_list = (field['isfacet']==this._FT_COLUMN || field['isfacet']==this._FT_LIST);    //is list
                                            //is dropdown but too many entries
//this feature is remarked on 2017-01-26 || (field['isfacet']==2 && tot_cnt > that._MIN_DROPDOWN_CONTENT)); 

                        if(window.hWin.HEURIST4.util.isArrayNotEmpty(field.history)){
                            let $span = $('<span>').css({'display':'inline-block','vertical-align':'middle'});
                            let f_link = this._createFacetLink(facet_index, term, 'inline-block');
                            $span.append(f_link).appendTo($facet_values);
                        }                        

                        if (field['isfacet']==this._FT_COLUMN || field['isfacet']==this._FT_LIST) { // List/Wrapped List, or Accordion for Terms

                            if(field['trm_tree'] && field['trm_tree'] === true){
                                this._drawTermAsTree(term['children'], 0, $facet_values, facet_index);
                            }else{
                                this.__drawData(term, 0, $facet_values, facet_index, field); //term is a tree for vocabulary
                                
                                //show viewport collapse/exand control
                                if(this.options.params.viewport < this.terms_drawn){
                                    let d_mode = field['isfacet']==this._FT_COLUMN ? 'block':'inline-block'; 
                                    __drawToggler($facet_values, d_mode);

                                    needsDropdown = field['isfacet'];
                                }
                            }                                
                        }else{
                            needsDropdown = true;
                        }
                        // Add dropdown 
                        if(needsDropdown){
                            
                            let need_small_dropdown = false;
                            let w = that.element.width();
                            if(!(w>0) || w<200) w = 200;

                            let $sel = $('<select>') // style="font-size: 0.6em !important;"
                                    .css({'width':(w-65)+'px',
                                          'max-width':(w-65)+'px'}); // was 30

                            if(needsDropdown !== true && $facet_values.find('span.bor-toggle-show-off').length > 0){
                                $sel.appendTo( $("<div>").css({"display":"inline-block","padding":"0px"}).appendTo($facet_values.find('span.bor-toggle-show-off')) );
                                $sel.css('width', ((w - 66) * 0.8)+'px');

                                $facet_values.css('margin-bottom', '15px');
                            }else{
                                //add placeholder in place of reset button
                                if(!window.hWin.HEURIST4.util.isArrayNotEmpty(field.history)){
                                    $('<span style="display: inline-block; width:18px"></span>')
                                                            .appendTo($facet_values);
                                }
                                
                                $sel.appendTo( $("<div>").css({"display":"inline-block","padding":"0px"})
                                    .appendTo($facet_values) );
                                    
                                need_small_dropdown = false;
                            }
                            if(term && Object.hasOwn(term, 'count') && term.count == 0){
                                this._createOption( facet_index, 0, {title:window.hWin.HR('facet_search_no_values'), value:null, count:0} ).appendTo($sel);
                                this.no_value_facets.push(facet_index);
                            }else{
                                this._createOption( facet_index, 0, {title:window.hWin.HR('facet_search_select'), value:null, count:0} ).appendTo($sel);
                            }
                            this.__drawData(term, 0, $sel, facet_index, field);

                            if(field.selectedvalue && field.selectedvalue.value){
                                let $opt = $sel.find('option[facet_value="'+field.selectedvalue.value+'"]');
                                $opt.attr('selected',true);
                            }

                            //convert to jquery selectmenu
                            let selObj = window.hWin.HEURIST4.ui.initHSelect($sel, false);
                            selObj.hSelect( "menuWidget" ).css({'font-size':'0.9em'});
                            selObj.hSelect( "widget" ).css({'background':'none',
                                                                'width':'auto',
                                                                'min-width':'100px',
                                                                'max-width':(w-65)+'px'});
                            let ele = selObj.hSelect( "widget" ).find('.ui-selectmenu-text');
                            ele.css({'min-height':'','padding-right':'0px','margin-right':'12px'});
                            
                            //change appearance for dropdown button
                            let btn_dropdown = selObj.hSelect( "widget" );
                            if(need_small_dropdown || this.options.params.viewport < this.terms_drawn){
                                btn_dropdown.css({"font-size": "0.96em", width: 'auto', color:"#999999", 
                                    'min-width':'', background: 'none'});
                                btn_dropdown.addClass('borderless');
                                btn_dropdown.find('.ui-selectmenu-text').html(window.hWin.HR(need_small_dropdown
                                    ?'facet_search_expand_select'
                                    :'facet_search_expand_dropdown'))
                                    .css({'min-height':'', padding:'', 'padding-right':'16px'});
                            }else{
                                btn_dropdown.css({"font-size": "0.9em", "min-width": "8em"});
                            }
                            btn_dropdown.find('.ui-selectmenu-icon').css('right', '0px');
                            $sel.on('change',function(event){ that._onDropdownSelect(event); });
                        }
                        
                    }else 
                    if(field['type']=="rectype"){  //@todo

                        for (let i=0;i<response.data.length;i++){
                            let cterm = response.data[i];

                            if(facet_index>=0){
                                let rtID = cterm[0];
                                let f_link = this._createFacetLink(facet_index, 
                                    {title:$Db.rty(rtID,'rty_Name'), query:rtID, count:cterm[1]}, 'inline-block');
                                $("<div>").css({"display":"inline-block","padding":"0px"})
                                  .addClass('facet-item')
                                  .append(f_link).appendTo($facet_values);
                            }
                        }

                    }else 
                    if ((field['type']=="float" || field['type']=="integer" 
                        || field['type']=='date' || field['type']=="year") && field['isfacet']==this._FT_SELECT)
                    {  //add slider
                    
                        $input_div.find('.input-cell').css({'padding-bottom': '25px', 'padding-left': '0px'});
                    
                        $facet_values.parent().css({'display':'block'});
                        //AAA strange padding ,'padding-left':'1em','padding-right':'2em'
                       

                        let cterm = response.data[0];
                        
                        if(window.hWin.HEURIST4.util.isArrayNotEmpty(field.history)){
                            let f_link = this._createFacetLink(facet_index, {title:'', value:null, step:0}, 'inline-block');
                                    $('<span>').css({'display':'inline-block','vertical-align':'middle','margin-left':'0px'}) //-15px
                                        .append(f_link).appendTo($facet_values);
                        }
                        let sl_count = (cterm && cterm.length==3)?cterm[2]:0;
                        
                        if(field.selectedvalue){ //currently selected value - some range was already set
                                if(window.hWin.HEURIST4.util.isNumber(field.selectedvalue.value) ||  
                                        (field.selectedvalue.value.indexOf('<>')<0 && 
                                         field.selectedvalue.value.indexOf('><')<0) ){
                                    cterm = [field.selectedvalue.value, field.selectedvalue.value];
                                }else{
                                    let s_op = field.selectedvalue.value.indexOf('><')<0?'<>':'><';
                                    cterm = field.selectedvalue.value.split(s_op);
                                }
                        }
                        let mmin  = cterm[0];
                        let mmax  = cterm[1];
                        let daymsec = 86400000; //24*60*60*1000;   1day

                        let date_type = '';

                        const w = that.element.width();
                        const tiny_ui = w <= 100;
                        const small_ui = !tiny_ui && w < 200;
                        let date_format = '';

                        if(!(window.hWin.HEURIST4.util.isempty(mmin) || window.hWin.HEURIST4.util.isempty(mmax))){
                            
                            if(field['type']=='date'){

                                function __toDt(val, is_max){ //from decimal to datetime
                                    
                                    if(Math.round(val) == val){ //years
                                        if(typeof val === 'string'){
                                            val = parseInt(val);
                                        }
                                        val = (val<0?'-':'')+(''+Math.abs(Math.round(val))).lpad('0',val<0?6:4)
                                        val = val+(is_max?'-12-31':'-01-01');
                                    }else{
                                        //
                                       
                                        let parts = val.split('.');
                                        let year = parts[0];
                                        let month = parts[1]?parts[1].substr(0,2):0;
                                        let day = parts[1]?parts[1].substr(2):0;
                                        
                                        val = (year<0?'-':'')+(''+Math.abs(year)).lpad('0',parseInt(year)<0?6:4)
                                            +'-'+((month==0)?'01':month.lpad('0',2))
                                            +'-'+((day==0)?'01':day.lpad('0',2))
                                    }
                                    
                                    return val;
                                }

                                if(field.date_type=='years_only'){
                                    if(typeof mmin==='string' && mmin.indexOf('-12-31')>0){
                                        mmin = mmin.substring(0, mmin.indexOf('-12-31')); 
                                    }
                                    if(typeof mmax==='string' && mmax.indexOf('-12-31')>0){
                                        mmax = mmax.substring(0, mmax.indexOf('-12-31')); 
                                    }
                                    mmin = ''+Math.round(mmin);    
                                    mmax = ''+Math.round(mmax);
                                }
                                
                                if( (mmin.match(/-?(\d*[.])?\d+/g) || []).length==1 ) {
                                    //mmin.indexOf('-')<1 && (mmin.match(/-/g) || []).length<2 ){
                                    mmin = __toDt(mmin, false);    
                                }else{
                                    if(mmin.indexOf("-00")>0){
                                        mmin = mmin.replaceAll("-00","-01");
                                    }
                                    mmin = mmin.replace(' ','T');                                                                     
                                }
                                
                                if( (mmax.match(/-?(\d*[.])?\d+/g) || []).length==1 ) {
                                    //mmax.indexOf('-')<1 && (mmax.match(/-/g) || []).length<2 ){
                                    mmax = __toDt(mmax, true);
                                }else{
                                    if(mmax.indexOf("-00")>0){ //|| mmax.indexOf("-01-01")>0
                                        let to_replace = mmax.indexOf("-00-00")>0 ? "-00-00" : "-01-01";
                                        mmax = mmax.replaceAll('-00','-01');
                                    }
                                    mmax = mmax.replace(' ','T');
                                }
                                
                                let date_min, date_max;
                                try{
                                    date_min = TDate.parse(mmin);
                                } catch(e) {
                                    mmin = NaN;
                                }
                                try{
                                    date_max = TDate.parse(mmax);
                                } catch(e) {
                                    mmax = NaN;
                                }
                                /*
                                if(isNaN(mmin) || isNaN(mmax)){ // date_min && date_max && (date_max.getYear()-date_min.getYear())>3){
                                    
                                    mmin = Number(date_min.getYear());
                                    mmax = Number(date_max.getYear());
                                    
                                    date_format = "yyyy";
                                    date_type = "year";
                                */    
                                if(date_min && date_max){
                                    
                                    if(field.date_type=='years_only' || date_min.getYear()<-2500 || date_max.getYear()<-2500){
                                        date_format = "yyyy";
                                        date_type = 'years_only';
                                        mmin = parseInt(date_min.getYear());
                                        mmax = parseInt(date_max.getYear());
                                    }else{
                                
                                        mmin = Date.parse(mmin); 
                                        mmax = Date.parse(mmax); 

                                        if(field.history.length == 0){ // Account for possible loss of a day
                                            mmax += 1000 * 60 * 60 * 24;
                                        }

                                        //find date interval for proper formating
                                        let delta = mmax-mmin;
                                        date_format = "dd MMM yyyy HH:mm";
                                        
                                        if(delta>3*365*daymsec){ //3 years
                                            date_format = "yyyy";
                                            date_type = "year";
                                        }else if(delta>365*daymsec){ //6 month
                                            date_format = "MMM yyyy";
                                            date_type = "month";
                                        }else if(delta>daymsec){ //1 day
                                            date_format = "dd MMM yyyy";
                                            date_type = "day";
                                        }
                                        
                                    }
                                }
                                
                            }else{
                                mmin = Number(mmin);
                                mmax = Number(mmax);
                            }
                            
                            let delta = window.hWin.HEURIST4.util.isArrayNotEmpty(field.history)?(mmax-mmin)/2:0; //not used
                            
                            if(field['type']=='date' && mmax-mmin<daymsec){
                                delta = daymsec;
                            }else if(mmin==mmax){ //years
                                delta = 10;
                            }                              
                            
                        /*if(mmin==mmax){
                            $("<span>").text(cterm[0]).css({'font-style':'italic', 'padding-left':'10px'}).appendTo($facet_values);
                        }else */
                        if(isNaN(mmin) || isNaN(mmax)){
                            
                            let s = "Server returns invalid "+field['type'];
                            if(isNaN(mmin)&&isNaN(mmax)){
                                s = s + " min and max values: "+cterm[0]+" and "+cterm[1];
                            }else{
                                s = s + " " +(isNaN(mmin)?"min":"max")+" value: "+(isNaN(mmin)?cterm[0]:cterm[1]);
                            }
                           
                           $("<span>").text(s)
                            .css({'font-style':'italic', 'padding-left':'10px'})
                            .appendTo($facet_values); 
                            
                        }else if(!field.selectedvalue && cterm[0]==cterm[1]){ //range was not set and initial
                            
                            //show the only date without slider
                            let s_date = temporalSimplifyDate(cterm[0]);
                            
                            if(s_date.match(/^-\d+/)){
                                s_date = Math.abs(parseInt(s_date))+' bce';
                            }
                            
                            let s_counts = this._getCountSpan(sl_count); //for slider
                            
                            $("<span>").html(s_date + s_counts).appendTo($facet_values); 

                            if(sl_count > 0){ //was =='1'
                                this._addFacetToExpandedCount(facet_index, $facet_values, $input_div, $(s_counts));
                            }
                            
                        }else if(field.srange && field.srange == 'text'){ // replace slider with two inputs

                            let $min_date, $max_date;

                            function __performSearch(){

                                let facet = that.options.params.facets[facet_index];

                                let min = $min_date.val();
                                let max = $max_date.val();
                                let has_min = !window.hWin.HEURIST4.util.isempty(min);
                                let has_max = !window.hWin.HEURIST4.util.isempty(max);

                                try{
                                    //year must be four digit
                                    let smin = ''+min;
                                    let smax = ''+max;
                                    let tDate;
                                    
                                    if(facet.date_type=='years_only'){

                                        if(max>0) max = (Math.round(max)+'-12-31');
                                    }else{

                                        if(!window.hWin.HEURIST4.util.isempty(min) && 
                                            (!smin.match(/^-?\d+/) || Math.abs(min)>2200)){

                                            tDate = new TDate((new Date(min)).toISOString());
                                            min = tDate.toString();
                                        }
                                        if(!window.hWin.HEURIST4.util.isempty(max) && 
                                            (!smax.match(/^-?\d+/) || Math.abs(max)>2200)){

                                            tDate = new TDate((new Date(max)).toISOString());
                                            max = tDate.toString();
                                        }else if(smax.match(/^-?\d+/)){ //year
                                            max = (max>0?(smax+'-12-31'):smax);
                                        }
                                    }
                                }catch(err){
                                    window.hWin.HEURIST4.msg.showMsgFlash('Unrecognized date format');
                                }

                                let value_str = '';

                                if(!has_min && !has_max){
                                    value_str = null;
                                }else if(!has_min || !has_max || min == max){
                                    value_str = has_min ? min : max;
                                }else{

                                    if(min > max){ // ensure min is smaller than max
                                        let temp = min;
                                        min = max;
                                        max = temp;
                                    }

                                    value_str = `${min}<>${max}`;
                                }

                                facet.selectedvalue = !value_str ? null : {title: '???', value: value_str, step: 1};

                                that.doSearch();
                            }

                            // Setup inputs
                            let min = temporalSimplifyDate(cterm[0]);
                            let max = temporalSimplifyDate(cterm[1]);

                            if(min == max){
                                max = '';
                            }

                            let $inner_div = $('<div>', {class: 'input-div', style: 'font-size: 12px; display: inline-block; padding: 0px;'}).appendTo($facet_values);

                            // Text inputs
                            $min_date = $('<input>', {name: `min-date-${facet_index}`, class: 'ui-corner-all ui-selectmenu-button'}).val(min).appendTo($inner_div);

                            $max_date = $('<input>', {name: `max-date-${facet_index}`, class: 'ui-corner-all ui-selectmenu-button'}).val(max).appendTo($inner_div);

                            // Styling
                            let w = that.element.width();
                            if(!(w>0) || w<200) w = 200;

                            $min_date.css({
                                'background':'none',
                                'width':'auto',
                                'max-width': (w-90)+'px',
                                'min-width':'100px'
                            }).after($('<br>'));

                            $max_date.css({
                                'background':'none',
                                'width':'auto',
                                'max-width': (w-90)+'px',
                                'min-width':'100px',
                                'margin-top': '10px'
                            });

                            // Search button
                            let $search = $('<button>', {
                                        title: window.hWin.HR('filter_facet_reset'), 
                                        class: 'smallbutton ui-button ui-corner-all ui-widget ui-button-icon-only'})
                                        .html('<span class="ui-button-icon ui-icon ui-icon-search"></span>')
                                        .insertAfter($max_date);

                            let $gap1 = $('<span>', {style: 'display:inline-block;width:16px;'}).insertBefore($min_date);
                            let $gap2 = $gap1.clone().insertBefore($max_date);

                            this._on($search, {
                                click: __performSearch
                            });
                            this._on($($min_date[0]).add($max_date), {
                                keypress: function(e){
                                    let code = (e.keyCode ? e.keyCode : e.which);
                                    if (code == 13) {
                                        window.hWin.HEURIST4.util.stopEvent(e);
                                        e.preventDefault();
                                        __performSearch();
                                    }
                                }
                            });

                            $facet_values.closest('.input-cell').css('padding-bottom', '10px');

                            if($facet_values.children().length > 1){
                                $($facet_values.children()[0]).css('vertical-align', 'top');
                                $gap1.hide();
                                $gap2.hide();
                            }

                        }else{
                            
                            if(isNaN(field.mmin0) || isNaN(field.mmax0)){
                                //on first request set limits
                                field.mmin0 = mmin;
                                field.mmax0 = mmax;
                                field.date_type = date_type;
                            }
                            
                            function __roundNumericLabel(val) {
                                let prefix = '';
                                if(val>=10e21){
                                    prefix = 'Z'; //Sextillion
                                    val = val/1e21;
                                }else if(val>=10e18){
                                    prefix = 'E'; //Quintillion
                                    val = val/1e18;
                                }else if(val>=10e15){
                                    prefix = 'P'; //Quadrillion
                                    val = val/1e15;
                                }else if(val>=10e12){
                                    prefix = 'T'; //Trillion  
                                    val = val/1e12;
                                }else if(val>=10e9){
                                    prefix = 'G'; //Billion 
                                    val = val/1e9;
                                }else if(val>=10e6){
                                    prefix = 'M'; //Million
                                    val = val/1e6;
                                }else if(val>=10e3){
                                    prefix = 'k'; //Thousand
                                    val = val/1e3;
                                }
                                if(prefix!=''){
                                    return Math.round(val)+prefix;    
                                }else{
                                    return val;
                                }
                                
                            }

                            //
                            // Create histogram above date slider, calls getDateHistogramData() in db_recsearch.php
                            // lower -> min value, higher -> ma x value
                            //
                            function setupDateHistogram(lower, higher) {
                                // Get dates in ms
                                if(date_type=='years_only'){
                                    lower = Math.round(lower);
                                    higher = Math.round(higher);
                                }else{
                                    let t_min = new Date(lower);
                                    let t_max = new Date(higher);
                                    lower = t_min.toISOString();
                                    higher = t_max.toISOString();
                                }

                                let ids = response.data.length > 1 ? response.data[1] : response.q.ids; // ids of all relavent records, string separated by commas

                                if(!window.hWin.HEURIST4.util.isempty(ids)){

                                    let dty_ID = field['id'];
                                    if(dty_ID.indexOf('r.')==0){
                                        dty_ID = dty_ID.substr(2);    
                                    }
                                    
                                    let request = {
                                        a: 'gethistogramdata',  // Get histogram data
                                        db: window.hWin.HAPI4.database, // database
                                        recids: ids,            // record/s of interest
                                        dtyid: dty_ID,     // detail type id
                                        range: [lower, higher], 
                                        format: date_type,      // year, month, day
                                        interval: 25,            // interval size
                                        is_between: (field.srange=='between')?1:0
                                    };

                                    let $slide_range = $facet_values.find('div.ui-slider-range');

                                    window.HAPI4.RecordMgr.get_date_histogram_data(request, function(response){

                                        if(response.status == window.hWin.ResponseStatus.OK){
                                            
                                            let data = response.data;
                                            
                                            $slide_range.parent().parent().css('margin-top', '50px'); // Add space above slider

                                            // Get available width
                                            let slider_width = $slide_range.width();

                                            // Diagram's Container
                                            let $diagram = $('<div id="facet_histo_'+facet_index+'">')
                                            .css({
                                                'height': '50px', 
                                                'max-height': '50px', 
                                                'width': slider_width+'px', 
                                                'max-width': slider_width+'px', 
                                                'display': 'flex',
                                                'flex-direction': 'row'
                                            })
                                            .appendTo($slide_range.parent())
                                            .position({my: 'bottom left', at: 'top left', of: $slide_range});

                                            // Object doesn't exist
                                            if($diagram.length == 0){
                                                return;
                                            }

                                            let position = $diagram.position();

                                            // Cautionary check before continuing
                                            if(window.hWin.HEURIST4.util.isempty(position)){
                                                return;
                                            }

                                            let left = position.left - 1;
                                            let top = position.top - 32;

                                            $diagram.css({
                                                'top': top+'px', 
                                                'max-width': $diagram.width()-4, 
                                                'width': $diagram.width()-4,
                                                'position': 'absolute'
                                            });

                                            // Column sizing
                                            let col_width = $diagram.width() / data.length;
                                            let col_gap = col_width * 0.25;
                                            col_width -= col_gap;

                                            if(col_width < 3) {
                                                col_width = $diagram.width() / data.length;
                                                col_gap = 0;
                                            }
                                            if(data.length == 1){
                                                col_gap = 0;
                                            }

                                            let max_height = 0, max_value = 0;

                                            for(let i = 0; i < data.length; i++){
                                                const count = data[i][2];
                                                if(max_value < count) max_value = count;
                                            }
                                            // Adding individual columns
                                            for(let i = 0; i < data.length; i++){
                                                const count = data[i][2];
                                                let height = 0;

                                                if(count > 0){
                                                    height = (count / max_value) * 50;
                                                    if(height < 10){
                                                        height = 10;
                                                    }
                                                }

                                                $('<div id="histo_col_'+i+'">')
                                                .css({
                                                    'background-color': 'gray', 
                                                    'width': col_width+'px', 
                                                    'margin-right': col_gap+'px', 
                                                    'display': 'inline-block', 
                                                    'height': ((height == 0) ? 2 : height) +'px',
                                                    'visibility': (height == 0) ? 'hidden' : 'visible',
                                                    'margin-top': 'auto'
                                                }).appendTo($diagram);
                                            }

                                           
                                            if(small_ui){

                                                let $slide_handle = $slide_range.parent().find('.ui-icon-triangle-1-w-stop');
                                                if($slide_handle.length>0)
                                                $facet_values.find('.ui-icon-triangle-1-w').position({my: 'right-6 center+5', at: 'right bottom', of: $($slide_handle)});
                                                
                                                $slide_handle = $slide_range.parent().find('.ui-icon-triangle-1-e-stop');
                                                if($slide_handle.length>0)
                                                $facet_values.find('.ui-icon-triangle-1-e').position({my: 'left+6 center+5', at: 'left bottom', of: $($slide_handle)});
                                            }
                                        }else if(window.hWin.HAPI4.has_access()){ //display error message, only if the user is logged in
                                            response.message = 'An error occurred with generating the time graph data<br>' + response.message;
                                            window.hWin.HEURIST4.msg.showMsgErr(response);
                                        }
                                    });
                                }
                            }
                            
                            function __updateSliderLabel() {
                                      
                                let min, max, cnt;      
                                if(arguments && arguments.length>1){
                                    if(isNaN(arguments[0])){
                                        min = arguments[1].values[ 0 ];
                                        max = arguments[1].values[ 1 ];
                                        cnt = 0;
                                    }else{
                                        min = arguments[0];
                                        max = arguments[1];
                                        cnt = arguments[2];
                                    }

                                    if(min<field.mmin0) {
                                        min = field.mmin0;
                                    }
                                    if(max>field.mmax0) {
                                        max = field.mmax0;
                                    }

                                    if(field['type']=="date"){
                                        min = __dateToString(min);
                                        max = __dateToString(max);
                                    }else{
                                        min = __roundNumericLabel(min);
                                        max = __roundNumericLabel(max);
                                    }
                                    let range_ele = that.element.find( "#facet_range"+facet_index )
                                        .html('<a href="#" class="link2">'+min
                                            +'</a> - <a href="#" class="link2">'+max+'</a>');
                                            
                                    let sl_count = that._getCountSpan(cnt); //for slider
                                    if(sl_count!=''){
                                        $(sl_count).insertAfter(range_ele);
                                    }
                                }

                                // Show handle's value while dragging
                                let have_handle = isNaN(arguments[0]) && arguments[1].handle instanceof HTMLElement && arguments[1].handle.classList.contains('ui-slider-handle');
                                if(have_handle){

                                    let handle = $(arguments[1].handle);
                                    let is_max = arguments[1].handleIndex == 1;

                                    that.element.find(`#facet_tracker${facet_index}`).text(is_max ? max : min).position({
                                        my: 'center top+10', // +10 so the cursor doesn't cover the value
                                        at: 'center bottom',
                                        of: handle
                                    }).show();
                                }
                            }
                            
                            function __dateToString(val){
                                try{
                                    let sval = ''+val;

                                    if(field.date_type=='years_only'){
                                        if(val<0){
                                            val = sval.substring(1)+' bce';
                                        }
                                    }else
                                    {
                                        let tDate = new TDate((new Date(val)).toISOString());
                                        val = tDate.toString(date_format);
                                        
                                        if(val.match(/^-\d+/)){
                                            val = Math.abs(parseInt(val))+' bce';
                                        }
                                    }
                                }catch(err) {
                                   val = ""; 
                                }
                                return val;
                            }
                            
                            //preapre value to be sent to server and start search
                            function __onSlideStop( event, ui){

                                let min = ui.values[ 0 ];
                                let max = ui.values[ 1 ];
                                
                                let field = that.options.params.facets[facet_index];
                                
                                if(min<field.mmin0) {
                                    min = field.mmin0;   
                                    slider.slider( "values", 0, min);
                                }
                                if(max>field.mmax0) {
                                    max = field.mmax0;
                                    slider.slider( "values", 1, max);
                                }

                                if(field['type'] == 'date' && !field.hide_histogram){

                                    setupDateHistogram(min, max);
                                }

                                __onSlideStartSearch(min, max);
                            }
                            
                            //
                            function __onSlideStartSearch( min, max ){
                                
                                let field = that.options.params.facets[facet_index];
                                
                                if(field['type']=="date"){
                                    try{
                                        //year must be four digit
                                       
                                        //max = (new TDate(max)).toString(); 
                                        let smin = ''+min;
                                        let smax = ''+max;
                                        let tDate;
                                        
                                        if(field.date_type=='years_only'){
                                            //
                                            if(max>0) max = (Math.round(max)+'-12-31');
                                        
                                        }else{
                                            if(!smin.match(/^-?\d+/) || Math.abs(min)>2200){
                                                tDate = new TDate((new Date(min)).toISOString());
                                                min = tDate.toString();
                                            }
                                            if(!smax.match(/^-?\d+/) || Math.abs(max)>2200){
                                                tDate = new TDate((new Date(max)).toISOString());
                                                max = tDate.toString();
                                            }else if(smax.match(/^-?\d+/)){ //year
                                                max = (max>0?(smax+'-12-31'):smax);
                                            }
                                        }
                                       
                                        //max = (new Date(max)).toISOString(); 
                                    }catch(err) {
                                       window.hWin.HEURIST4.msg.showMsgFlash('Unrecognized date format');
                                    }
                                }

                                let op_compare = field.srange=='between'?'><':'<>' //between or overlap
                                
                                let value = (min==max)?min :(min + op_compare + max);                            
                             
                                if(window.hWin.HEURIST4.util.isempty(value)){
                                    value = '';
                                    field.selectedvalue = null;
                                }else{
                                    field.selectedvalue = {title:'???', value:value, step:1};                    
                                }
                                
                                that.doSearch();
                            }
                            
                            function __onDateRangeDialogClose() {
                                        
                                let startDate = that._date_range_dialog.find('#date-start').editing_input('getValues')[0];
                                let endDate = that._date_range_dialog.find('#date-end').editing_input('getValues')[0];
                                
                                __onSlideStartSearch(startDate, endDate);
                            
                                if(that._date_range_dialog_instance && 
                                   that._date_range_dialog_instance.dialog('instance')){
                                   that._date_range_dialog_instance.dialog( 'close' );
                                }
                            }
                            
                            function __showDateRangeDialog(event){
                                
                                if(!that._date_range_dialog){
                                    
                                    that._date_range_dialog = $(
                                    '<div><div style="padding:10px 0 5px 5px;" id="date-range"></div>'                      
                                    +'<fieldset class="narrow"><div id="date-start"></div>'
                                    +'<div id="date-end"></div>'
                                    +'</fieldset></div>')
                                    .hide()
                                    .appendTo(this.element);
                                    
                                    let dtFields = {};
                                    dtFields['rst_DisplayName'] = 'Date start';
                                    dtFields['rst_RequirementType'] = 'optional';
                                    dtFields['rst_MaxValues'] = 1;
                                    dtFields['rst_DisplayWidth'] = 20; 
                                    dtFields['dty_Type'] = 'date';
                                    
                                    let ed_options = {
                                        recID: -1,
                                        dtID: 'dStart',
                                        //readonly: false,
                                        showclear_button: false,
                                        dtFields:dtFields
                                    };

                                    that._date_range_dialog.find('#date-start').editing_input(ed_options);
                                    
                                    dtFields['rst_DisplayName'] = 'Date end';
                                    ed_options['dtID'] = 'dEnd';
                                    that._date_range_dialog.find('#date-end').editing_input(ed_options);
                                    
                                    that._date_range_dialog.find('.editint-inout-repeat-button').hide();
                                 
                                }
                                
                                that._date_range_dialog.find('#date-start').editing_input('setValue', 
                                        temporalSimplifyDate(cterm[0])); //__dateToString(mmin)
                                that._date_range_dialog.find('#date-end').editing_input('setValue', 
                                        temporalSimplifyDate(cterm[1]));
                                that._date_range_dialog.find('#date-range')
                                    .text('Range '+__dateToString(field.mmin0)+' - '+__dateToString(field.mmax0));
                                
                                let buttons = {};
                                buttons[window.hWin.HR('Apply')]  = __onDateRangeDialogClose;
                                
                                //window.hWin.HEURIST4.msg.showMsgDlg('Define data range <>',
                                that._date_range_dialog_instance = window.hWin.HEURIST4.msg.showElementAsDialog(
                                {
                                   element: that._date_range_dialog[0], 
                                   close: function(){
                                        //let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();      
                                   },
                                   buttons: buttons,
                                   title:'Define selection range',
                                   resizable: false,
                                   width:300,
                                   height:212,
                                   position:{my:'bottom left',at:'top left',of:$(event.target)} 
                                });
                            }

                            if(w > 200 && (mmin != field.mmin0 || mmax != field.mmax0)){

                                let min = field.mmin0;
                                let max = field.mmax0;

                                if(field['type']=="date"){
                                    min = __dateToString(min);
                                    max = __dateToString(max);
                                }else{
                                    min = __roundNumericLabel(min);
                                    max = __roundNumericLabel(max);
                                }

                                $("<span>", {class: 'heurist-helper2'})
                                    .css({'font-size': '10px', color: 'gray'})
                                    .text(`${min} - ${max}`)
                                    .appendTo($facet_values);
                            }

                            let flbl = $("<div>",{id:"facet_range"+facet_index})
                                        .css({display: 'inline-block', 'padding-bottom': '1em', position: 'relative', left: '15px'})
                                        .appendTo($facet_values);

                            $("<span>", {id: `facet_tracker${facet_index}`, class: 'heurist-helper2'})
                                .css({display: 'none', 'font-size': '10px', color: 'gray', position: 'absolute'})
                                .appendTo($facet_values);
                                        
                            if(field['type']=="date"){
                                flbl.css({cursor:'pointer'});
                                that._on(flbl,{click: __showDateRangeDialog});
                            }

                            let rwidth = 70;
                            rwidth = (small_ui ? 60 : 70);
                            rwidth = (tiny_ui ? 10 : rwidth);

                            let btn_w = 10; //12
                            let ele2 = $('<div>'
                                +'<span class="ui-icon ui-icon-triangle-1-w-stop" title="Reset to minimum date"'
                                    +'style="cursor:pointer;font-size:smaller;float:left;color:gray;width:'+btn_w+'px;"></span>'
                                +'<span class="ui-icon ui-icon-triangle-1-w" title="Half step"'
                                    +'style="cursor:pointer;font-size:smaller;float:left;color:gray;width:'+btn_w+'px;"></span>'
                                +'<div style="height:0.4em;margin:2px 2px 0px 2px;float:left;width:'+(w - rwidth)+'px"></div>'
                                +'<span class="ui-icon ui-icon-triangle-1-e" title="Half step"'
                                    +'style="cursor:pointer;font-size:smaller;float:left;color:gray;width:'+btn_w+'px;"></span>'
                                +'<span class="ui-icon ui-icon-triangle-1-e-stop" title="Reset to maximum date"'
                                    +'style="cursor:pointer;font-size:smaller;float:left;color:gray;width:'+btn_w+'px;"></span>'
                                //+'<span class="heurist-helper1 min-val" title="Original minimum value" style="font-size:smaller;cursor:default;" ></span>'
                                //+'<span class="heurist-helper1 max-val" title="Original maximum value" style="font-size:smaller;cursor:default;" ></span>'
                            +'</div>'
                            ).appendTo($facet_values);
                                        
                            let range_min = field.mmin0;
                            let range_max = field.mmax0;

                            if(mmin != range_min){
                                let ten_percent = Math.abs(range_min) * 0.05; //0.1
                                range_min = mmin - ten_percent;
                            }
                            if(mmax != range_max){
                                let ten_percent = Math.abs(range_max) * 0.05; //0.1
                                range_max = mmax + ten_percent;
                            }

                            let slider = ele2.find('div')
                                .attr('facet_index',facet_index)
                                .slider({
                                    range: true,
                                    min: range_min, //field.mmin0 (mmin-delta<field.mmin0)?field.mmin0:(mmin-delta)
                                    max: range_max, //field.mmax0 (mmax+delta>field.mmax0)?field.mmax0:(mmax+delta)
                                    values: [ mmin, mmax ],
                                    slide: __updateSliderLabel,
                                    stop: __onSlideStop,
                                    start: function(){
                                        ele2.find('.ui-icon-triangle-1-w, .ui-icon-triangle-1-e').css('visibility', 'hidden'); //, .min-val, .max-val
                                    },
                                    create: function(){
                                        $(this).find('.ui-slider-handle').css({width:'4px',background:'black'});

                                        if(small_ui){
                                            ele2.find('span.ui-icon-triangle-1-w').position({my: 'right-6 center+5', at: 'right bottom', of: $($(this).find('.ui-slider-handle')[0])});
                                            ele2.find('span.ui-icon-triangle-1-e').position({my: 'left+6 center+5', at: 'left bottom', of: $($(this).find('.ui-slider-handle')[1])});
                                        }
                                    }
                                });
                                    
                            that._on( ele2.find('span.ui-icon-triangle-1-w-stop'),
                                {click: function(){
                                    __onSlideStartSearch(field.mmin0, mmax);
                                }});
                            that._on( ele2.find('span.ui-icon-triangle-1-w'),
                                {click: function(){
                                    let diff = (field.mmin0 - mmin) / 2;
                                    __onSlideStartSearch(mmin + diff, mmax);
                                }});
                            that._on( ele2.find('span.ui-icon-triangle-1-e'),
                                {click: function(){
                                    let diff = (field.mmax0 - mmax) / 2;
                                    __onSlideStartSearch(mmin, mmax + diff);
                                }});
                            that._on( ele2.find('span.ui-icon-triangle-1-e-stop'),
                                {click: function(){
                                    __onSlideStartSearch(mmin, field.mmax0);
                                }});

                            
                                 
                            if(mmin==field.mmin0){
                                ele2.find('span.ui-icon-triangle-1-w-stop, span.ui-icon-triangle-1-w').css('visibility','hidden');
                               
                            }
                            if(mmax==field.mmax0){
                                ele2.find('span.ui-icon-triangle-1-e-stop, span.ui-icon-triangle-1-e').css('visibility','hidden');
                               
                            }
                            if(tiny_ui){ // hide skip and step buttons
                                ele2.find('[class="ui-icon-triangle-1-"]').css('display', 'none');
                            }
                                 
                            //show initial values
                            __updateSliderLabel(mmin, mmax, sl_count);

                            if(field['type'] == 'date' && !field.hide_histogram){
                                //build histogram
                                setupDateHistogram(mmin, mmax);
                            }
                            
                        }
                        }
                    }
                    else if( (field['type']=='enum' || field['type']=='reltype') && field['groupby']=='firstlevel' 
                                && !window.hWin.HEURIST4.util.isnull(field['selectedvalue'])){
                        
                                    let cterm = field.selectedvalue;
                                    let f_link = this._createFacetLink(facet_index, {title:cterm.title, value:cterm.value, count:'reset'}, 'block');
                        
                        let ditem = $("<div>").css({'display':'block',"padding":"0px"})
                                                .addClass('facet-item')
                                                .append(f_link).appendTo($facet_values);
                    }
                    else{   //freetext  or enum groupby firstlevel
                        
                        //draw history
                        if(window.hWin.HEURIST4.util.isArrayNotEmpty(field.history)){
                            
                            const len = field.history.length;
                            let k=0;
                            //while(k<1)
                                if(len>2){
                                    k=len-2; //instead of reset show last one    
                                }
                                
                                let cvalue = field.history[k];
                                
                                let $span = $('<span>').css('display','block'); //was inline
                                if(k==len-1){ //last one
                                    $span.text(cvalue.title).appendTo($facet_values);
                                   
                                }else{
                                    cvalue.reset_link = true;
                                    let f_link = this._createFacetLink(facet_index, cvalue, 'inline-block');
                                    $span.css({'display':'inline-block','vertical-align':'middle'}).append(f_link).appendTo($facet_values);
                                   
                                }
                                
                            
                        }
                        
                        //sort by count
                        if(field['orderby']=='count'){
                            response.data.sort(function(a, b){ return (Number(a[1])>Number(b[1]))?-1:1;});
                        }else if(field['orderby']=='desc'){
                            
                            if(field['type']=="float" || field['type']=="integer" || field['type']=="year"
                              || (field['type']=="date" && field['groupby']!='month')) {
                                 
                                response.data.sort(function(a, b){ return (parseFloat(a[0])>parseFloat(b[0])?-1:1);}); 
                            }else{
                                response.data.sort(function(a, b){ return (a[0]>b[0]?-1:1);});    
                            }
                        }
                        
                        let display_mode = (field['isfacet']==this._FT_LIST || field['isfacet']==this._FT_SELECT || (field['groupby']=='firstchar' && field['isfacet']==this._FT_LIST))
                                                        ?'inline-block':'block';
                        
                        needsDropdown = false;
                        if(field['isfacet']==this._FT_COLUMN || field['isfacet']==this._FT_LIST){ // Listed/Wrapped
                            
                            display_mode = (field['isfacet'] == this._FT_COLUMN) ? 'block' : 'inline-block';

                            this.__drawData(response.data, 0, $facet_values, facet_index, field);

                            //show viewport collapse/exand control
                            if(that.options.params.viewport < response.data.length){ // List is longer than allowed, add dropdown with all options
                                let diff = response.data.length - this.options.params.viewport;
                                __drawToggler($facet_values, display_mode);

                                needsDropdown = true;
                            }

                        }else{
                            needsDropdown = true;
                        }

                        // Add dropdown 
                        if(needsDropdown){

                            let w = that.element.width();
                            if(!(w>0) || w<200) w = 200;

                            let $sel = $('<select>').css('width', (w-65)+'px'); // was 30  style="font-size: 0.6em !important;"

                            needsDropdown = (field['isfacet'] != this._FT_SELECT);

                            if(needsDropdown && $facet_values.find('span.bor-toggle-show-off').length > 0){
                                
                                $sel.appendTo( $("<div>").css({"display":"inline-block","padding":"0px"})
                                .appendTo($facet_values.find('span.bor-toggle-show-off')) );
                                $sel.css('width', ((w - 66) * 0.8)+'px');

                                $facet_values.css('margin-bottom', '15px');
                            }else{
                                //add placeholder in place of reset button
                                if(!window.hWin.HEURIST4.util.isArrayNotEmpty(field.history)){
                                    $('<span style="display: inline-block; width:18px"></span>')
                                                            .appendTo($facet_values);
                                }
                                
                                $sel.appendTo( $("<div>").css({"display":"inline-block","padding":"0"}).appendTo($facet_values) );
                            }

                            if(response.data.length==0){
                                this._createOption( facet_index, 0, {title:window.hWin.HR('facet_search_no_values'), value:null, count:0} ).appendTo($sel);
                                this.no_value_facets.push(facet_index);
                            }else{
                                this._createOption( facet_index, 0, {title:window.hWin.HR('facet_search_select'), value:null, count:0} ).appendTo($sel);
                                this.__drawData(response.data, 0, $sel, facet_index, field);

                                if(field.selectedvalue && field.selectedvalue.value){
                                    let $opt = $sel.find('option[facet_value="'+field.selectedvalue.value+'"]');
                                    $opt.attr('selected',true);
                                }
                            }

                           
                            let selObj = window.hWin.HEURIST4.ui.initHSelect($sel, false);
                            selObj.hSelect( "menuWidget" ).css({'font-size':'0.9em'});
                            selObj.hSelect( "widget" ).css({'background':'none',
                                                                'width':'auto',
                                                                'min-width':'100px',
                                                                'max-width':(w-65)+'px'});
                            let ele = selObj.hSelect( "widget" ).find('.ui-selectmenu-text');
                            ele.css({'min-height':'','padding-right':'0px','margin-right':'12px'});

                            let btn_dropdown = selObj.hSelect( "widget" );
                            //change appearance for dropdown button
                            if(needsDropdown){
                                btn_dropdown.css({"font-size": "0.96em", width: 'auto', color:"#999999", 
                                    'min-width':'', background: 'none'});
                                btn_dropdown.addClass('borderless');
                                btn_dropdown.find('.ui-selectmenu-text').html('dropdown')
                                    .css({'min-height':'', padding:'', 'padding-right':'16px'});
                            }else{
                                btn_dropdown.css({"font-size": "0.9em", "min-width": "8em"});
                            }                            
                            

                            $sel.on('change',function(event){ that._onDropdownSelect(event); });
                        }
                    }

                    if($facet_values.is(':empty')){
                        $("<span>").text(window.hWin.HR('facet_search_no_values')).css({'font-style':'italic', 'padding-left':'10px'}).appendTo($facet_values);
                        this.no_value_facets.push(facet_index);
                    }

                    //search next facet
                    this._recalculateFacets( facet_index );

                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
                
                
    }

    //
    //
    //
    ,_getCountSpan: function(sl_count){          
        let s_counts = '';                  
        if((sl_count>0) && this.options.params.ui_counts_mode!='none'){
            let s_slign = (this.options.params.ui_counts_align=='left')?'margin-left:3px;text-decoration:none':'float:right';
            if(this.options.params.ui_counts_mode=='bracket'){
                s_counts = '<span class="facet-count" style="'+s_slign+'">('+sl_count+')</span>';
            }else{
                s_counts = '<span class="badge facet-count" style="'+s_slign+'">'+sl_count+'</span>';
            }
        }
        return s_counts; 
    }

    //
    //
    //
    , __drawData: function(data, level, $container, f_index, field) {

        let display_mode = (field['isfacet'] == this._FT_COLUMN) ? 'block' : 'inline-block';
        let isListOrColumn = (field['isfacet'] == this._FT_COLUMN) || (field['isfacet'] == this._FT_LIST);

        //draw itslef - draw children
        if(data.value){
            
            if(isListOrColumn && $container.not('select').length > 0){ // LIST

                data.level = level;
                let f_link = this._createFacetLink( f_index, 
                    $.extend(data, {level: level}),
                    display_mode );
                
                if(data.count > 0){ // check that facet link has been drawn
                    this.terms_drawn++;  //global
                }
                
                let ditem = $("<div>").css({'display':(this.terms_drawn>this.options.params.viewport?'none':display_mode),
                                'padding':"0 0px 0 "+((level-1)*10)+"px"})
                        .addClass('facet-item')        
                        .append(f_link)
                        .appendTo($container);

                if(this.terms_drawn>this.options.params.viewport){
                        ditem.addClass('in-viewport');
                }                    
                
            }else{
                // DROPDOWN
                let $f_link = this._createOption( f_index, level, {title:data.title, 
                    value:data.value, 
                    count:data.count} );
                $f_link.appendTo($container);

                if(data.count > 0){ //was =='1'
                    this._addFacetToExpandedCount(f_index, data.value, $container, $f_link);
                }
            }


        }else if(window.hWin.HEURIST4.util.isArrayNotEmpty(data)){

            for(let i = 0; i < data.length; i++){

                let value = data[i];
                let title = value[0];

                //for enum get term label w/o code
                if((field['type']=='enum' || field['type']=='reltype') && value[0]>0){
                    title = $Db.getTermValue(value[0], false);    
                }else if( field['type']=='date' && field['groupby']=='month' ){
                    
                    let tDate = new TDate((new Date(''+value[0])).toISOString());
                    title = tDate.toString('MMM yyyy');
                }

                if(!title){
                    title = value[0];
                }

                if(isListOrColumn && $container.not('select').length > 0){ // LIST
                    
                    let f_link = this._createFacetLink(f_index, {title:title, value:value[2], count:value[1], 'level': level}, display_mode);
                    
                    //@todo draw first level for groupby firs tchar always inline
                    let step_level = (field['groupby']=='firstchar' && field['selectedvalue'])
                                        ?field['selectedvalue'].step:0;
                                                                                              
                    let ditem = $("<div>").css({'display':(i>this.options.params.viewport-1?'none':display_mode),"padding":"0px"})
                                        .addClass('facet-item')
                                        .append(f_link).appendTo($container);
                                        
                    if(i>this.options.params.viewport-1){
                         ditem.addClass('in-viewport');
                    }                    
                    if(i>2000){  //was 250
                        $("<div>").css({"display":"none","padding":"0 3px"})
                         .addClass('in-viewport')
                         .html('still more...( '+(data.length-i)+' results )').appendTo($container);
                         break;       
                    }

                }else{
                    // DROPDOWN
                    let $f_link = this._createOption(f_index, level, {title: title, value: value[2], count: value[1]});
                    $f_link.appendTo($container);

                    if(value[1] > 0){ //was =='1'
                        this._addFacetToExpandedCount(f_index, value[2], $container, $f_link);
                    }
                }


            }
        }

        if(data.children){
            //sort by count per level
            if(field['orderby']=='count'){
                data.children.sort(function(a, b){ 
                    return (Number(a.count)>Number(b.count))?-1:1;
                });
            }
            
            for (let k=0; k<data.children.length; k++){
                this.__drawData(data.children[k], level+1, $container, f_index, field);
            }
        }
    }

    //
    // Draw term tree as a FancyTree
    //  TODO - Fix isfacet to not rely on FT_LIST (3) in search_faceted & search_faceted_wiz
    //
    , _drawTermAsTree: function(data, level, $facet_container, facet_index){

        let that = this;

        let nodes = [];
        for(let i = 0; i < data.length; i++){

            let cur_data = data[i];
            let node = {};

            let key = cur_data['value'];
            if(key === null){
                continue;
            }

            let sl_count = that._getCountSpan(cur_data['count']); //in tree

            sl_count = sl_count.replace('style="', 'style="padding-left:10px;');

            if(cur_data['count'] > 0){ //was =='1'
                this._addFacetToExpandedCount(facet_index, key, $facet_container, null)
            }

            let title = '<span title="' + cur_data['title'] + '" data-value="' + key + '">' + cur_data['title'];

            if(this.options.params.ui_counts_align=='left'){
                title = title + sl_count + '</span>';
            }else{
                title = title  + '</span>' + sl_count;
            }

            node['title'] = title;
            node['key'] = key;
            node['expanded'] = false;
            node['data'] = {
                'index': facet_index,
                'value': key,
                'label': cur_data['title']
            };

            if(cur_data['children'] && cur_data['children'].length > 0){
                let child_nodes = this._drawTermAsTree(cur_data['children'], level+1, null, facet_index); //$container
                node['children'] = child_nodes;
            }

            nodes.push(node);
        }

        if(level == 0){

            $('<div class="tree facet-item">').appendTo($facet_container);
            $facet_container.find('div.tree').fancytree({
                checkbox: false,
                source: nodes,
                click: (e, data) => {

                    let isExpander = $(e.originalEvent.target).hasClass('fancytree-expander');

                    if(isExpander){
                        return;
                    }

                    if($(e.originalEvent.target).is('span') && !data.node.isExpanded() && data.node.children && data.node.children.length>0){
                        data.node.setExpanded(!data.node.isExpanded());
                    }else{

                        let f_index = data.node.data['index'];
                        let value = data.node.data['value'];
                        let label = data.node.data['label'];
                        let step = data.node.data['step'];

                        let field = this.options.params.facets[f_index];
                        
                        if(window.hWin.HEURIST4.util.isempty(value)){
                            value = '';
                            field.selectedvalue = null;
                        }else if(field.multisel && field.selectedvalue!=null){
                            
                            let vals = field.selectedvalue.value.split(',');
                            let k = window.hWin.HEURIST4.util.findArrayIndex(value, vals);
                            if(k < 0){ //add
                                vals.push(value);
                            }else{ //remove
                                vals.splice(k,1);
                            }
                            if(value.length==0){
                                field.selectedvalue = null;
                            }else{
                                field.selectedvalue.value = vals.join(',');    
                            }
                        }else{
                            field.selectedvalue = {title:label, value:value, step:step};                    
                        }

                        that._last_active_facet = facet_index;
                        that.doSearch();
                        
                        return false;
                    }
                }
            });
        }else{
            return nodes;
        }
    }

    ,_createOption: function(facet_index, indent, cterm){

        let hist = this.options.params.facets[facet_index].history;
        if(!hist) hist = [];
        let step = hist.length+1;
        
        let lbl = cterm.title; //(new Array( indent + 1 ).join( " . " )) + 
        if(cterm.count>0){
            lbl =  lbl + " ("+cterm.count+")";
        } 

        let f_link = $("<option>",{facet_index:facet_index, facet_value:cterm.value, facet_label:lbl, step:step})
                            //.css('padding-left', ((indent-1)*2)+'em' )
                            .text(lbl).addClass("facet_link")
        f_link.attr('depth',indent-1);
        
        return f_link;
    }
    
    , _onDropdownSelect: function(event){
        
        let link = $(event.target).find( "option:selected" );
        let facet_index = Number(link.attr('facet_index'));
        let value = link.attr('facet_value');                  
        let label = link.attr('facet_label');                  
        let step = link.attr('step');
        
        let field = this.options.params.facets[facet_index];
        
        let prevvalue = field.selectedvalue?field.selectedvalue.value:null;
        
        if(window.hWin.HEURIST4.util.isempty(value)){
            value = '';
            field.selectedvalue = null;
        }else{
            field.selectedvalue = {title:label, value:value, step:step};                    
        }
        
        if(prevvalue!=value){
            this._last_active_facet = facet_index;
            this.doSearch();
        }
    }

    // cterm - {title, value, count}
    ,_createFacetLink: function(facet_index, cterm, display_mode){
        
        let content_max_width = 0;
        
        if(isNaN(content_max_width) || content_max_width<10){
            
            if(this.options.is_publication && this.element.parents('.heurist-widget').width()>10){
                content_max_width = this.element.parents('.heurist-widget').width();
            }else{
                content_max_width = this.element.width();
            }

            if(content_max_width<10){
                content_max_width = 250;
            }
        } 

        let field = this.options.params.facets[facet_index];
       
        let hist = field.history;
        if(!hist) hist = [];
        let step = (cterm && cterm.reset_link)?cterm.step:(hist.length+1);
        let iscurrent = false;
        
        let currval = field.selectedvalue?field.selectedvalue.value:null;
        let hideEle = false;

        if(cterm.count != 'reset' && cterm.count <= 0){
            hideEle = true;
        }

        let f_link = $("<a>",{href:'#', facet_index:facet_index, 
                        facet_value: (cterm.count=='reset')?'':cterm.value, 
                        facet_label: cterm.title, 
                        step:step})
                    .addClass("facet_link")
        
        //----
        let f_link_content;
        
        if(window.hWin.HEURIST4.util.isempty(cterm.value)){
            f_link_content = $("<span>").addClass("ui-icon ui-icon-arrowreturnthick-1-w")
                .attr('title','Reset facet value')
                .css({'font-size':'11px','font-style':'normal'}); //1.2em
        }else{
            f_link_content = $("<span>").html(cterm.title);
            
            if(display_mode=='block'){         

                let width = content_max_width < 200 ? content_max_width * 0.6 : content_max_width - 60;
                width = cterm.level ? width - (cterm.level - 1) * 10 : width;

                f_link.css({display:'block'});
                
                f_link_content.css('max-width', width)
                              .addClass('truncate')
                              .attr('title', cterm.title);
            }
            
            if(!window.hWin.HEURIST4.util.isempty(currval)){
                
                if(field.multisel){
                    iscurrent = (window.hWin.HEURIST4.util.findArrayIndex(cterm.value, currval.split(','))>=0);
                }else{
                    iscurrent = (currval == cterm.value);    
                }
                if(iscurrent) 
                    //do not highlight if initals selected
                    //|| (currval.length==2 &&  currval.substr(1,1)=='%' && currval.substr(0,1)==cterm.value.substr(0,1)) )
                {
                     
                     f_link_content.css({ 'font-weight': 'bold', 'font-size':'1.1em', 'font-style':'normal' });   
                }
            
            }
        }
        f_link_content.addClass('facet-label').appendTo(f_link);
        
        //---
        if(cterm.count=='reset' || cterm.count>0){
            //.css('float','right')
            let txt = '';
            if(cterm.count=='reset'){
                txt = 'X';
            }else if(cterm.count>0 && cterm.supress_count_draw!==true){
                txt = cterm.count;
            }
            
            if(txt!='' && field['isfacet']!=this._FT_INPUT){ // This is what is creating the listing
 
                let s_counts = this._getCountSpan(txt);
                let dcount = $(s_counts); 
                
                if(this.options.params.ui_counts_align=='left' || display_mode=='inline-block'){
                    dcount.appendTo(f_link);
                    dcount.appendTo(f_link_content);
                }else{
                    if(this.options.params.ui_counts_mode!='bracket'){
                        dcount.addClass('truncate')
                              .css('max-width', this.options.is_publication ? '3em' : '45px')
                              .attr('title', dcount.text());
                    }
                    dcount.appendTo(f_link);    
                }

                if(txt > 0){ //was =='1'
                    this._addFacetToExpandedCount(facet_index, cterm.value, f_link_content, dcount);
                }

                if(display_mode!=='inline-block' && cterm.level > 1){
                         let label_width = this.facets_list_container.width();
                         if(label_width<10) label_width = content_max_width - 80;
                         label_width = label_width - 30;
                         f_link_content.css('width', label_width);
                }
            }
        }
        
        if( field.multisel || !iscurrent || cterm.count=='reset'){ 

            let that = this;

            this._on( f_link, {
                click: function(event) { 

                    let link = $(event.target);
                    if(!link.hasClass('facet_link')){
                        link = link.parents('.facet_link');    
                    }
                    let facet_index = Number(link.attr('facet_index'));
                    let value = link.attr('facet_value');                  
                    let label = link.attr('facet_label');                  
                    let step = link.attr('step');
                    
                    let field = this.options.params.facets[facet_index];
                    
                    if(!field) return;
                    
                    if(window.hWin.HEURIST4.util.isempty(value)){
                        value = '';
                        field.selectedvalue = null;
                    }else if(field.multisel && field.selectedvalue!=null){
                        
                        let vals = field.selectedvalue.value.split(',');
                        let k = window.hWin.HEURIST4.util.findArrayIndex(value, vals);
                        if(k<0){ //add
                            vals.push(value);
                        }else{ //remove
                            vals.splice(k,1);
                        }
                        if(value.length==0){
                            field.selectedvalue = null;
                        }else{
                            field.selectedvalue.value = vals.join(',');    
                        }
                    }else{
                        field.selectedvalue = {title:label, value:value, step:step};                    
                    }

                    if(cterm.count=='reset'){  //field.multisel || 
                        that._last_active_facet = facet_index;
                    } 

                    that._last_term_value = value != '' ? value : null;

                    that.doSearch();
                    
                    return false;
                }
            });
        }

        if(hideEle){
            f_link.hide();
        }

        return f_link;
    },
    
    //
    // instead of list of links it is possible to allow enter search value directly into input field
    // NOT USED
    /*
    _createInputField :function(field_index){

        let field = this.options.params.facets[field_index];

        let rtid = field['rtid'];
        if(rtid.indexOf(',')>0){
            rtid = rtid.split(',')[0];
        }

        let dty_ID = field['id']; 
        if(dty_ID.indexOf('r.')==0){
            dty_ID = dty_ID.substr(2);    
        }
        
        let facet_title = window.hWin.HEURIST4.util.htmlEscape(window.hWin.HRJ('title', field, this.options.language));
        
        let ed_options = {
            varid: field['var'],  //content_id+"_"+
            recID: -1,
            rectypeID: rtid,
            dtID: dty_ID,
            
            values: [''],
            readonly: false,
            title:  "<span style='font-weight:bold'>" + facet_title + "</span>",
            showclear_button: false,
            suppress_prompts: true,  //supress help, error and required features
            suppress_repeat: true,
            detailtype: field['type']  //overwrite detail type from db (for example freetext instead of memo)
        };

        if(isNaN(Number(dty_ID))){ //field id not defined
            ed_options['dtFields'] = {
                dty_Type: field['type'],
                rst_RequirementType: 'optional',
                rst_MaxValues: 1,
                rst_DisplayWidth: 0
            };
        }

        //rst_DefaultValue

        let inpt = $("<div>",{id: "fv_"+field['var'] }).editing_input(   //this is our widget for edit given fieldtype value
            ed_options
        );

        inpt.appendTo($fieldset);
        that._input_fields['$X'+field['var']] = inpt;


    },
    */
    //
    // if main record type has linked or related place rectype search by location
    // if main field has geo field search by this field
    // otherwise ignore spatial search 
    //
    _prepareSpatial: function(wkt){
       
       if(!window.hWin.HEURIST4.util.isempty(wkt))
       {
           if(this._hasLocation==null){
                this._hasLocation = 'none';   
   
                let primary_rt = this.options.params.rectypes[0];
                
                if(window.hWin.HEURIST4.dbs.hasFields(primary_rt, 'geo', null)){
                    this._hasLocation = 'yes';
                }else{

                    let linked_rt = window.hWin.HEURIST4.dbs.getLinkedRecordTypes_cache(primary_rt, true);

                    if(linked_rt['linkedto'].length > 0){

                        for(let rty_ID of linked_rt['linkedto']){

                            if(window.hWin.HEURIST4.dbs.hasFields(rty_ID, 'geo', null)){
                                this._hasLocation = 'linkedto';
                                break;
                            }
                        }
                    }
                    if(this._hasLocation == 'none' && linked_rt['relatedto'].length > 0){

                        for(let rty_ID of linked_rt['relatedto']){

                            if(linked_rt['linkedto'].length > 0 && linked_rt['linkedto'].indexOf(rty_ID) != -1){
                                continue;
                            }

                            if(window.hWin.HEURIST4.dbs.hasFields(rty_ID, 'geo', null)){
                                this._hasLocation = 'relatedto';
                                break;
                            }
                        }
                    }
                }
                
                if(this._hasLocation == 'none'){
                    window.hWin.HEURIST4.msg.showMsgFlash('There is no spatial data to filter on. Please ask the owner of the filter to hide the spatial component.',4000);
                }
           }
           
           if(this._hasLocation=='yes'){ 
                return wkt;           
           }else if(this._hasLocation!='none'){
               let res = {};
               res[this._hasLocation] = wkt;
               return res;
           }           
       }
        
       return null; 
    },
    
    //
    // info message in the header to indicate that user work set is active
    //
    refreshSubsetSign: function(){
        
        if(this.div_header){

            let container = this.div_header.find('div.subset-active-div');
            
            if(container.length==0){
                let ele = $('<div>').addClass('subset-active-div').css({'padding-left':'1.3em','padding-top':'4px'}) //css({'padding':'0.1em 0em 0.5em 1em'})
                      .appendTo(this.div_header);
            }
            container.find('span').remove();
            //let s = '<span style="position:absolute;right:10px;top:10px;font-size:0.6em;">';    
         
            if(window.hWin.HAPI4.sysinfo.db_workset_count>0){
                
                $('<span style="padding:0.3em 1em;background:white;color:red;vertical-align:sub;font-size: 11px;font-weight: bold;"'
                  +' title="'+window.hWin.HAPI4.sysinfo.db_workset_count+' records"'
                  +'>'+window.hWin.HR('SUBSET ACTIVE')+' n='+window.hWin.HAPI4.sysinfo.db_workset_count+'</span>')
                    .appendTo(container);
            }
            this._adjustSearchDivTop();
        }
        
    },
    
    _addFacetToExpandedCount: function(facet_index, facet_value, $container, $facet){

        if(!this.options.params.rules){
            return;
        }

        if(!Object.hasOwn(this._expanded_count_facets, facet_index)){

            this._expanded_count_facets[facet_index] = {
                'container': $container,
                'items': []
            };

            this._expanded_count_order.push(facet_index);
        }

        let found = false;
        for(let arr of this._expanded_count_facets[facet_index]['items']){
            if(arr[0] == facet_value && ($facet == arr[1] || $facet.is(arr[1])) ){
                found = true;
                break;
            }
        }
        if(!found){
            this._expanded_count_facets[facet_index]['items'].push([facet_value, $facet]);
        }

    },

    _getExpandedFacetCount: function(){

        const that = this;

        if(!this.options.params.rules || this._expanded_count_order.length == 0){
            //rules not defined
            return;
        }

        let f_idx = this._expanded_count_order[0];

        if(!this._expanded_count_facets[f_idx] || this._expanded_count_facets[f_idx].items.length == 0){

            this._expanded_count_order.shift();

            this._getExpandedFacetCount();
            return;
        }

        let $parent_container = this._expanded_count_facets[f_idx].container;
        let current_facet = this._expanded_count_facets[f_idx].items.shift();
        let facet_placeholder = `$X${this.options.params.facets[f_idx].var}`; // placeholder for field, e.g. $X76917
        let field = ''; // field id, e.g. f:10

        for(let q_facet of this.options.params.q){

            field = Object.keys(q_facet)[0];

            if((field.startsWith('f:')||field=='title') && q_facet[field] == facet_placeholder){
                break;
            }

            field = null;
        }

        let query = window.hWin.HEURIST4.util.cloneJSON( this._current_query );
        let facet_query = {};
        facet_query[field] = current_facet[0];

        if(window.hWin.HEURIST4.util.isempty(query)){
            // Construct a basic query
            query = window.hWin.HEURIST4.query.mergeHeuristQuery(
                this.options.params.q[0],
                this._use_sup_filter ? this.options.params.sup_filter : '',
                this._prepareSpatial(this.options.params.spatial_filter),
                facet_query,
                {sortby: this.options.params.sort_order ? this.options.params.sort_order : 't'}
            );
        }else{
            // Insert value into query
            let sortby = query.pop();
            query.push(facet_query, sortby);
        }
        
        let rulesonly = 1; //all exts
        if(this.options.params.rulesonly==3){ //original+last rules
            rulesonly = 2; //last only
        }else if(this.options.params.rulesonly>0){
            rulesonly = this.options.params.rulesonly; //2 or 1 - last or all rules
        }

        let request = {
            q: query,
            detail: 'ids',
            rules: this.options.params.rules,
            rulesonly: rulesonly 
        };

        window.hWin.HAPI4.RecordMgr.search(request, (response) => {

            if(that._expanded_count_order.length == 0 || that._expanded_count_cancel){ // cancel
                that._expanded_count_cancel = false;
                return;
            }
            if(response.status != window.hWin.ResponseStatus.OK || response.data.count < 1){
                that._getExpandedFacetCount(); //no extension - next 
                return;
            }

            // Update label
            let $count_lbl = current_facet[1];
            let ext_count = `+${response.data.count}`;

            let treediv = $parent_container.find('.tree.facet-item');
            
            if(!$count_lbl && treediv.length > 0 && treediv.fancytree('instance')){ // tree

                let tree = $.ui.fancytree.getTree( treediv );

                let node = tree.getNodeByKey('' + current_facet[0]);

                let $title = $($(node.title)[0]);
                let $count_lbl = $($(node.title)[1]);

                $count_lbl.removeClass('badge');
                
                if(that.options.params.rulesonly==1 || that.options.params.rulesonly==2){
                    //remove original 
                    if($count_lbl.text().startsWith('(')){
                        ext_count = `(${ext_count})`;
                    }
                    $count_lbl.text(ext_count);
                }else{
                    //keep original
                    let orig_count = $count_lbl.find('.badge');
                    orig_count = (orig_count.length==0)?$count_lbl.text():orig_count.text();
                    $count_lbl.html(`<span class="badge">${orig_count}</span>${ext_count}`);
                }

                node.setTitle($title[0].outerHTML + $count_lbl[0].outerHTML);

            }else if($count_lbl.hasClass('facet-count') || $count_lbl.find('.facet-count').length > 0){ // separate span

                $count_lbl = $count_lbl.hasClass('facet-count') ? $count_lbl : $count_lbl.find('.facet-count');
                
                
                if(that.options.params.rulesonly==1 || that.options.params.rulesonly==2){
                    //remove original 
                    $count_lbl.text(ext_count);
                }else{
                    //keep original
                    let txt = $count_lbl.text();
                    if( /\+\d+$/.test(txt) ) {
                        txt = txt.replace(/\+\d+$/, ext_count);    
                    }else{
                        txt = txt+ext_count;
                    }

                    $count_lbl.text(txt);
                }

                $count_lbl.attr('title', `Expands to ${response.data.count} records`);

            }else if($count_lbl.is('option')){ // dropdown option
                //repalce (n) or (n+e)  
                
                let txt = $count_lbl.text();

                if(that.options.params.rulesonly==1 || that.options.params.rulesonly==2){
                    //remove original 
                    if( /\(\d+\)$/.test(txt) ) {
                        txt = txt.replace(/\(\d+\)$/, `(${ext_count})`);    
                    }else{
                        txt = `(${ext_count})`;
                    }
                }else{                
                    if(/\(\d+\+\d+\)$/.test(txt)){
                        txt = txt.replace(/\+\d+\)$/, `${ext_count})`);    
                    }else if( /\(\d+\)$/.test(txt) ) {
                        txt = txt.replace(/\)$/, `${ext_count})`);    
                    }else{
                        txt = `(${ext_count})`;
                    }
                }
                $count_lbl.text(txt);

                if($count_lbl.closest('select').hSelect('instance') !== undefined){
                    $count_lbl.closest('select').hSelect('refresh');
                }

            }

            that._getExpandedFacetCount();
        });
    }

});