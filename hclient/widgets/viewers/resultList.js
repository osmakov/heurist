/**
* Query result listing.
*
* Requires hclient/widgets/viewers/resultListMenu.js (must be preloaded)
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


$.widget( "heurist.resultList", {

    // default options
    options: {
        widget_id: null, //outdated: user identificator to find this widget custom js script on web/CMS page
        is_h6style: false,
        view_mode: null, //  list , icons , thumbs , thumbs3 , horizontal , vertical , icons_list , record_content, tabs  
        list_mode_is_table: false, //if table - adjust header columns width to column width in content div

        select_mode:null,//none, manager, select_single, select_multi
        selectbutton_label:'Select',
        action_select:null,  //array of actions
        action_buttons:null,
                           
        //action for onselect event - open preview inline, popup or none - used in cms for example
        recordview_onselect: false, //false/none, inline or popup - show record viewer/info on select
        multiselect: true,    //allows highlight several records

        eventbased:true, //if false it does not listen global events

        show_toolbar: true,   //toolbar contains menu,savefilter,counter,viewmode and pagination
        show_search_form: false,
        show_menu: false,       //@todo ? - replace to action_select and action_buttons
        support_collection: false,
        support_reorder: false,  // show separate reorder button
        show_counter: true,
        show_viewmode: true,
        show_inner_header: false, // show title of current search in header (above toolbar)
        show_fancybox_viewer: false, //opens fancybox viewer on click on thumbnail
        header_class: null,       //class name for menu
        show_url_as_link:false,
        show_action_buttons: true,

        title: null,  //see show_inner_header
        //searchsource: null,

        emptyMessageURL: null, //url of page to be loaded on empty result set 
        empty_remark: 'def', //html content for empty message (search returns no result)
        blank_empty_remark: false, // leave empty remark blank
        pagesize: -1,
        
        
        groupByMode: null, //[null|'none','tab','accordion'],
        groupByField:null,
        groupByRecordset: null,
        groupOnlyOneVisible:false,
        groupByCss:null, //css for group content

        renderer: null,    // custom renderer function to draw item
        rendererHeader: null,   // renderer function to draw header for list view-mode (for content)
        rendererGroupHeader: null,   // renderer function for group header (see groupByField)
        
        recordDivClass: '', // additional class that modifies recordDiv appearance (see for example "public" or "outline_supress" in h4styles.css) 
                            // it is used if renderer is null
        recordDivEvenClass:null,  //additional class for even entries recordDiv
        
        // smarty template or url (or todo function) to draw inline record details when recordview_onselect='inline'. (LINE view mode only)
        rendererExpandDetails: null,  //name of smarty template or function to draw expanded details
        rendererExpandInFrame: true, 
        expandDetailsOnClick: true,
        expandDetailsWithoutWarning: false,

        searchfull: null,  // custom function to search full data
        
        sortable: false, //allows drag and sort entries
        sortable_opts: null, //contains extra options for sortable function
        onSortStop: null,
        draggable: null, // callback function to init dragable - it is called after 
                         // finish render and assign draggable widget for all record divs
        droppable: null, //callback function to init dropable (see refreshPage)

        //events  
        onSelect: null,  //on select event 

        onScroll: null,  //on scroll ends
        
        onPageRender: null, //event listner on complete of page render

        onTooltip: null, // on retrieving content for the tooltip

        navigator:'auto',  //none, buttons, menu, auto

        entityName:'records',   //records by default
        
        search_realm:  null,  //accepts search/selection events from elements of the same realm only
        search_initial: null,  //query string or svs_ID for initial search
        
        supress_load_fullrecord: false, //do not load full record data
        
        transparent_background: false,
        
        aggregate_values: null, //supplementary values per record id - usually to store counts, sum, avg 
        aggregate_link: null,    //link to assigned to aggregate value label
		
        allow_record_content_view: false,   // show record_content mode as an option, for Webpages, 
                                            // can be overridden if the initial view mode is record_content or if set to blog mode

		blog_result_list: false,    //whether the result list is used for blog records, limiting pagesize if it is

        auto_select_first: false,   //automatically select first record within result list
        placeholder_text: null,     //text to display while no recordset is loaded (search is not prefromed yet)
        blank_placeholder: false,   // leave placeholder blank
        
        init_completed: false,   //flag to be set to true on full widget initializtion

        recviewer_images: 1, // show images in record viewer; 0 - show all images, 1 - no linked media, 2 - no images
        recview_dimensions: { // popup size and placement
            height: '80%',
            width: '60%',
            top: 'center',
            left: 'center'
        },
        recview_private_details: null, // how to handle the 'more...' section

        field_for_ext_classes: 20, // add class related to field value to record's row; 0 - disabled, n > 0 - detail type id

        show_export_button: false, // display to that opens the export menu, for exporting the current result set
        export_options: 'all', // export formats allowed

        check_linked_media: true, // check linked records (only type "media") for an image
        
        fontsize: 0, //base font size for renderRecordData otherwise it takes from user preferences
        
        language: 'def'
    },

    _is_publication:false, //this is CMS publication - take css from parent
    
    _query_request: null, //keep current query request

    _events: null,                                                   
    _lastSelectedIndex: null, //required for shift-click selection
    _count_of_divs: 0,

    //navigation-pagination
    current_page: 0,
    max_page: 0,
    count_total: null,  //total records in query - actual number can be less
    _current_view_mode: null,

    _currentRecordset:null,
    _currentMultiSelection:null, //for select_multi - to keep selection across pages and queries
    _fullRecordset: null, //keep full set for multiselection (to get records disregard current filters)
    _currentSubset: null, //subset of full record set (NOT USED for recordset of the collected records to display)
    _isCollectionUsed: false,

    _startupInfo:null,

    _init_completed:false,
    
    _expandAllDivs: false,
    
    _myTimeoutCloseRecPopup: 0,
    _myTimeoutOpenRecPopup: 0, 
    
    _grp_keep_status:{}, //expanded groups
    
    _mediaViewer_list: [],
    
    _rec_onpage: null,
    _is_fancybox_active: false,
    
    sortResultList: null,
    sortResultListDlg: null, //tab panel or dialog
    _is_sortResultList_tab_based: true,
    _sortResultList_need_fill: true,
    
    _sortResult_svsID: 0, //last saved svs ID for sort result
    _sortResult_was_changed: false,
    
    _currentSavedFilterID: 0,

    export_button: null, // button to export current recordset

    _collection: null, // current collection of record ids
    
    _auto_select_record: null, // record to auto select, retrieved from URL
    
    _cached_linked_images: {}, // cache of images linked to the record
    
    //to refresh icon after structure edit
    _icon_timer_suffix: ('&t='+window.hWin.HEURIST4.util.random()),
    
    // the constructor
    _create: function() {

        const that = this;
        
        if(this.options.widget_id){ //outdated
            this.element.attr('data-widgetid', this.options.widget_id);
        }
        
        this.element.css('overflow','hidden');

        if(this.options.blog_result_list==true){
            this.options.pagesize = 10;
        }else if(this.options.pagesize<50 || this.options.pagesize>5000){
            this.options.pagesize = window.hWin.HAPI4.get_prefs('search_result_pagesize');
        }

        this.options.empty_remark = this.options.empty_remark=='def' ? window.hWin.HR('resultList_empty_remark') : this.options.empty_remark;
        this.options.placeholder_text = this.options.placeholder_text=='def' ? '' : this.options.placeholder_text;

        this._is_publication = window.hWin.HAPI4.is_publish_mode;

        if(this.options.fontsize==0 && this.element.css('font-size')){
            this.options.fontsize = parseFloat(this.element.css('font-size'));
        }
        
        // Auto select record(s), retrieved from url
        let rec_ids = window.hWin.HEURIST4.util.getUrlParameter('rec_id', location.href);
        if(!rec_ids && window.hWin.HAPI4.sysinfo.use_redirect){

            let url = location.pathname;

            rec_ids = url.indexOf('/website/') > 0 ? url.substring(url.indexOf('/website/')+9) : url.substring(url.indexOf('/web/')+5);
            rec_ids = rec_ids.split('/');
            rec_ids.pop(); // last entry is empty
            rec_ids = rec_ids.length > 2 ? rec_ids.splice(0, 2) : null;
        }

        if(!window.hWin.HEURIST4.util.isempty(rec_ids)){
            this._auto_select_record = Array.isArray(rec_ids) ? rec_ids : rec_ids.split(',');
            this._auto_select_record = this._auto_select_record.filter((rec_ID) => !window.hWin.HEURIST4.util.isempty(rec_ID) && rec_ID > 0);
        }

        this._initControls();

        //-----------------------     listener of global events
        if(this.options.eventbased)
        {
            this._events = window.hWin.HAPI4.Event.ON_CREDENTIALS 
                + ' ' + window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE
                + ' ' + window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE
                + ' ' + window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE
                + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
                + ' ' + window.hWin.HAPI4.Event.ON_REC_SELECT
                + ' ' + window.hWin.HAPI4.Event.ON_REC_STATUS
                + ' ' + window.hWin.HAPI4.Event.ON_REC_COLLECT
                + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH;

            $(this.document).on(this._events, function(e, data) {

                if(e.type == window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE){

                    that._showHideOnWidth();

                }else  if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS)
                {
                    if(!window.hWin.HAPI4.has_access()){ //logout
                        that.updateResultSet(null);
                    }
                    that._refresh();

                }else 
                if(e.type == window.hWin.HAPI4.Event.ON_REC_COLLECT){
                
                    that.setCollected( data.collection );
                
                }else 
                if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART)
                {
                    
                    //accept events from the same realm only
                    if(!that._isSameRealm(data)) return;

                    that.span_pagination.hide();
                    that.span_info.hide();

                    that.setSelected(null);
                    $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                        {selection:null, source:that.element.attr('id'), 
                            search_realm:that.options.search_realm} );
                        
                    if(that.options.show_search_form){
                        if (data.primary_rt && !data.ispreview)
                        {
                            //this is faceted search - hide input search form
                            that.div_search_form.hide();
                        }else{
                            that.div_search_form.show();
                        }
                        that._adjustHeadersPos();
                    }

                    if(data.reset){
                        
                        //fake restart
                        that.clearAllRecordDivs('');
                       
                        that._renderStartupMessageComposedFromRecord();
                        
                    }else{
                        
                        if(that._query_request==null || data.id!=that._query_request.id) {  //data.source!=that.element.attr('id') ||
                            //new search from outside
                            let new_title = null;
                            if(data.qname>0 && window.hWin.HAPI4.currentUser.usr_SavedSearch && 
                                window.hWin.HAPI4.currentUser.usr_SavedSearch[data.qname])
                            {
                                that._currentSavedFilterID = data.qname;
                                new_title = window.hWin.HAPI4.currentUser.usr_SavedSearch[that._currentSavedFilterID][0];
                            }else{
                                if(data.qname>0 && that.div_header!=null){
                                    
                                    window.hWin.HAPI4.SystemMgr.ssearch_get( {svsIDs:[data.qname]},
                                        function(response){
                                            if(response.status == window.hWin.ResponseStatus.OK){
                                                that._currentSavedFilterID = data.qname;
                                                
                                                if(!window.hWin.HAPI4.currentUser.usr_SavedSearch){
                                                    window.hWin.HAPI4.currentUser.usr_SavedSearch = {};
                                                }
                                                window.hWin.HAPI4.currentUser.usr_SavedSearch[that._currentSavedFilterID] = 
                                                                    response.data[that._currentSavedFilterID];
                                                
                                                let new_title = response.data[that._currentSavedFilterID][0];
                                                that.setHeaderText(new_title);
                                            }
                                    });
                                    
                                }
                                
                                that._currentSavedFilterID = 0;
                                if(data.qname>0 || window.hWin.HEURIST4.util.isempty(data.qname)){
                                    new_title = window.hWin.HR('Filtered Result');            
                                }else{
                                    new_title = window.hWin.HR(data.qname);
                                }
                            }
                            
                            that.clearAllRecordDivs(new_title);
                            
                            if(that.search_save_hint){
                                that.search_save_hint.attr('show_hint', data.qname?0:1);
                               
                            }
                            

                            if(!window.hWin.HEURIST4.util.isempty(data.q)){
                                that.loadanimation(true);
                                that._renderProgress();
                            }else{
                                that.renderMessage('<div style="font-style:italic;">'+window.hWin.HR(data.message)+'</div>');
                            }

                        }

                        that._query_request = data;  //keep current query request

                    }

                    that._renderSearchInfoMsg(null);

                }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){

                    //accept events from the same realm only
                    if(!that._isSameRealm(data) || data?.showing_subset) return;

                    that._currentSubset = null; // override current subset
                    that._isCollectionUsed = false;
                    
                    that.loadanimation(false);
                    
                    let recset = data.recordset;
                    if(recset==null){
                        
                        that._currentRecordset = recset;
						
                        if(data.empty_remark){

                            let msg = data.is_facet && !window.hWin.HEURIST4.util.isempty(that.options.placeholder_text) ? 
                                            that.options.placeholder_text : '';

                            msg = window.hWin.HEURIST4.util.isempty(msg) && !that.options.blank_placeholder && data.empty_remark ? 
                                            data.empty_remark : msg;

                            that.div_content.html( msg );
                        }else{

                            let recID_withStartupInfo = window.hWin.HEURIST4.util.getUrlParameter('Startinfo');
                            if(recID_withStartupInfo>0){
                                that._renderStartupMessageComposedFromRecord();
                                
                            }else if(that.options.emptyMessageURL){
                                    that.div_content.load( that.options.emptyMessageURL );
                            }else{
                                    that._renderEmptyMessage(0);
                            }
                        }

                        if(that.btn_search_save){
                            that.btn_search_save.hide();
                        }
                        if(that.export_button){
                            that.export_button.hide();
                        }
                    }else if(that._query_request!=null && recset.queryid()==that._query_request.id) {
                        
                        //it accepts only results that has the same query id as it was set in ON_REC_SEARCHSTART
                        
                            
                        if(that._query_request.viewmode){
                                that.applyViewMode( that._query_request.viewmode );
                        }

                        that._renderRecordsIncrementally(recset); //HRecordSet - render record on search finish
                        
                        if(that.btn_search_save) {
                            that.btn_search_save.show();
                            if(that.search_save_hint.attr('show_hint')==1){
                                that.search_save_hint.show('fade',{},1000);
                                setTimeout(function(){that.search_save_hint.hide('slide', {}, 6000);}, 5000);    
                            }
                        }
                        if(that.export_button){
                            that.export_button.show();
                        }
                    }
                    
                    that._showHideOnWidth();
                    that._renderPagesNavigator();
                    that._renderSearchInfoMsg(recset);
                    
                    that.options.init_completed = true;
                }
                else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){

                    //this selection is triggered by some other app - we have to redraw selection
                    if(data && data.source!=that.element.attr('id')) {
                        if(!that._isSameRealm(data)){
                            that.setSelected(null);
                        }else if(data.reset){ //clear selection
                            that.setSelected(null);
                        }else if(data.subset_only){
                            that._currentSubset = that._currentRecordset.getSubSetByIds(data.selection);
                            that._renderPage(0);

                            const query = that._currentSubset.length() > 0 ? `ids:${that._currentSubset.getIds().join(',')}` : '';

                            $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH, {
                                recordset: that._currentSubset,
                                showing_subset: true,
                                search_realm: that.options.search_realm,
                                query: query
                            });
                        }else{
                            that.setSelected(data.selection);        
                        }
                        
                        
                    }
                } 
                else if(e.type == window.hWin.HAPI4.Event.ON_REC_STATUS){
                    
                    if(data.map_layer_status){  //visible hidden loading error
                                if(data.selection && data.selection.length==1){
                                    
                                    let rdiv = that.div_content.find('.recordDiv[recid="'+data.selection[0]+'"]');
                                    if(rdiv.length>0)
                                    {
                                        let sLabel;

                                        rdiv.find('.rec_expand_on_map > .ui-icon').removeClass('rotate');
                                        if(data.map_layer_status=='visible'){
                                            sLabel = 'Hide data';
                                            
                                            //zoom to loaded data
                                            $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                                            {selection:data.selection, 
                                                map_layer_action: 'zoom',
                                                source:that.element.attr('id'), 
                                                search_realm:that.options.search_realm
                                                //,search_page:that.options.search_page
                                            } );
                                            
                                        }else if(data.map_layer_status=='loading'){
                                            sLabel = 'loading';
                                            rdiv.find('.rec_expand_on_map > .ui-icon').addClass('rotate');
                                        }else if(data.map_layer_status=='error'){
                                            sLabel = 'Error';
                                            rdiv.find('.rec_expand_on_map > .ui-icon').removeClass('ui-icon-globe').addClass('ui-icon-alert');
                                        }else{
                                            sLabel = 'Show data';
                                        }
                                        
                                        rdiv.find('.rec_expand_on_map').attr('data-loaded', data.map_layer_status);
                                        rdiv.find('.rec_expand_on_map > .ui-button-text').text( window.hWin.HR(sLabel) );
                                    }
                                    
                                }
                    }                    
                }else 
                if(e.type == window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE)
                {
                    that.options.pagesize = window.hWin.HAPI4.get_prefs('search_result_pagesize');
                }else 
                if(e.type == window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE)
                {
                    //update record type icon
                    that._icon_timer_suffix = ('&t='+window.hWin.HEURIST4.util.random());
                    that._renderPage(that.current_page);
                }
                
               
            });

        }

        
        this._init_completed = true;

        this._refresh();

        this._renderStartupMessageComposedFromRecord();
        
        // 
        if(this.options.search_initial){
            
            let request = {q:this.options.search_initial, w: 'a', detail: 'ids', 
                        source:'init', search_realm: this.options.search_realm, is_inital_search: true };
            setTimeout(() => { window.hWin.HAPI4.RecordSearch.doSearch(this.document, request); }, 1000);
            
        }else{
            this.setCollected(null); //to obtain collection
            
            if(!window.hWin.HEURIST4.util.isempty(this.options.placeholder_text)){
                this.div_content.html(this.options.placeholder_text);
            }
             
            this.options.init_completed = true;
        }    
    }, //end _create
    
    //
    //
    //
    _isSameRealm: function(data){
        return (!this.options.search_realm && (!data || window.hWin.HEURIST4.util.isempty(data.search_realm)))
        ||
        (this.options.search_realm && (data && this.options.search_realm==data.search_realm));
    },

    //
    //
    //
    _initControls: function() {

        const that = this;

        let right_padding = window.hWin.HEURIST4.util.getScrollBarWidth()+1;

        //------------------------------------------       

        this.div_coverall = $( "<div>" )                
        .addClass('coverall-div-bare')
        .css({'zIndex':10000,'background':'white'})
        .hide()
        .appendTo( this.element );

        //------------------------------------------       

        this.div_header =  $( "<div>" ).css({'height':'auto'}).appendTo( this.element ); //41px
        
        if(this.options.is_h6style){

            $('<div class="result-list-header ui-widget-content" ' //was ui-heurist-heade
            + 'style="font-size:1em;text-align:left;padding-left:12px;position:relative;'
            + 'font-weight: bold;letter-spacing: 0.26px;padding:10px;"></div>')
                .appendTo( this.div_header );
        }else{
        
            //padding left to align to search input field
            $('<div class="result-list-header">')
                .css({'padding':'0.7em 0 0 20px', 'font-size': '1.17em'})   
                .appendTo(this.div_header);
        }
        
        
        //add label to display number of selected, button and selected onlu checkbox
        if(this.options.select_mode=='select_multi'){
            this.show_selected_only = $( "<div>" )
            .addClass('ent_select_multi')  //ui-widget-content 
            .css({'right':right_padding+2})
            .html(
                '<label style="padding:0 0.4em;">'
                +'<input id="cb_selected_only" type="checkbox" style="vertical-align:-0.3em;"/>'
                +'&nbsp;'+window.hWin.HR('show selected only')+'</label>'
                +'<div id="btn_select_and_close"></div>')
            .appendTo( this.div_header );

            //init checkbox and button
            this.btn_select_and_close = this.element.find('#btn_select_and_close')
            .css({'min-width':'11.9em'})
            .button({label: window.hWin.HR( this.options.selectbutton_label )})
            .on('click', function(e) {
                that._trigger( "onaction", null, 'select-and-close' );
            });

            this.cb_selected_only = this.element.find('#cb_selected_only')
            this._on( this.cb_selected_only, {
                change: this.showRetainedSelection} );

        }

        // -------------------------------------------

        this.div_toolbar = $( "<div>" )
        .addClass('div-result-list-toolbar ent_header')
        .css({'width':'100%','top':'0px','min-height':'23px'})
        .appendTo( this.element );

        this.div_content = $( "<div>" )
        .addClass('div-result-list-content')
        //.css({'border-top':'1px solid #cccccc'})  //,'padding-top':'1em'
        .css({'overflow-y':'auto'})
        .appendTo( this.element );
        
        if(this.element.css('position')=='relative' && this.element[0].style.height=='100%'){
            this.div_content.css('height','100%');
        }else{                                          
            this.div_content.addClass('ent_content_full');    
        }
        
        if(window.hWin.HEURIST4.util.isFunction(this.options.onScroll)){
            this._on(this.div_content, {'scroll':this.options.onScroll});
        }
        
        this.div_loading = $( "<div>" )
        .css({ 'width': '50%', 'height': '50%', 'top': '25%', 'margin': '0 auto', 'position': 'relative',
            'z-index':'99999999', 'background':'url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center' })
        .appendTo( this.element ).hide();

        if(window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.action_buttons)){

            this.action_buttons_div.css({'display':'inline-block', 'padding':'0 0 4px 1em'})
                .hide().appendTo( this.div_toolbar );    
            
            for(let idx in this.options.action_buttons){

                const key = this.options.action_buttons[idx].key;
                const title = this.options.action_buttons[idx].title;

                let btn_icon = null;
                if(key=='add') btn_icon = 'ui-icon-plus'
                else if(key=='edit') btn_icon = 'ui-icon-pencil'
                    else if(key=='edit_ext') btn_icon = 'ui-icon-newwin'
                        else if(key=='delete') btn_icon = 'ui-icon-minus';

                $('<div>',{'data-key':key}).button({icon: btn_icon, showLabel:true, label:window.hWin.HR(title) })
                .appendTo(this.action_buttons_div)
                .on('click', function( event ) {
                    let key = $(event.target).parent().attr('data-key');
                    that._trigger( "onaction", null, key );
                });
            }

        }
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.action_select)){

            let smenu = "";
            for(let idx in this.options.action_select){
                const key = this.options.action_select[idx].title
                const title = this.options.action_select[idx].title;
                smenu = smenu + '<li data-key="'+key+'"><a href="#">'+window.hWin.HR(title)+'</a></li>';
            }

            this.menu_actions = $('<ul>'+smenu+'</ul>')   //<a href="#">
            .css({position:'absolute', zIndex:9999})
            .appendTo( this.document.find('body') )
            .menu({
                select: function( event, ui ) {
                    const key =  ui.item.attr('data-key');
                    that._trigger( "onaction", null, key );
            }})
            .hide();

            this.btn_actions = $( "<button>" )
            .appendTo( this.action_buttons_div )
            .button({iconPosition:'end', icon:'ui-icon-triangle-1-s', showLabel:true, label: window.hWin.HR("Actions")});

            this._on( this.btn_actions, {
                click: function() {
                    $('.ui-menu').not('.horizontalmenu').not('.heurist-selectmenu').hide(); //hide other
                    const menu = $( this.menu_actions )
                    //.css('width', this.div_search_as_user.width())
                    .show()
                    .position({my: "right top", at: "right bottom", of: this.btn_actions });
                    $( document ).one( "click", function() { menu.hide(); });
                    return false;
                }
            });
        }

        //------------------ moved to menu
        this.reorder_button = $( '<button>' )
                .button({icon: "ui-icon-signal", showLabel:false, label:window.hWin.HR('reorder')})
                .css({'font-size':'0.93em','float':'right','margin':'2px '+right_padding+'px'})
                .hide()
                .appendTo( this.div_toolbar );
        this.reorder_button.find('span.ui-icon').css({'transform':'rotate(90deg)'});
                
        this._on(this.reorder_button, {click: this.setOrderAndSaveAsFilter});
        
        //media carousel/viewer --------------
        if(this.options.show_fancybox_viewer){
            this.fancybox_button = $( '<button>' )
                .button({icon: "ui-icon-arrow-4-diag", showLabel:false, label:window.hWin.HR('Clicking on thumbnail opens full screen media viewer')})
                .css({'float':'right','width':'28px', height:'28px', 'font-size':'1em', 'margin-right':'15px'})
                .appendTo( this.div_toolbar );
            
            if(this._is_fancybox_active){
                this.fancybox_button.css({'border':'1px solid', background: '#dbdcfd'}); //, background: '#ddd'
            }
            
            this._on( this.fancybox_button, {
                click: function(event) {
                    this._is_fancybox_active = !this._is_fancybox_active;
                    if(this._is_fancybox_active){
                        if(this._rec_onpage){
                            this.div_content.mediaViewer({selector:'.realThumb', search_initial:'ids:'+this._rec_onpage.join(',') });            
                           
                        }
                        this.fancybox_button.css({'border':'1px solid', background: '#dbdcfd'}); //, background: '#ddd'
                    }else{
                        this.fancybox_button.css({'border':'none', background: 'none'});
                        
                        if(this.div_content.mediaViewer('instance')) this.div_content.mediaViewer('clearAll');
                    }
                    
                    
            }});
        }

        // Check if allow_record_content_view needs to be overwritten, always hide in backend
        this.options.allow_record_content_view = this._is_publication && (this.options.blog_result_list || this.options.allow_record_content_view);
        this.options.export_options = !this._is_publication ? 'csv' : this.options.export_options;

        //------------------
        let smodes = '<button value="list" class="btnset_radio"/>'
            +'<button value="icons" class="btnset_radio"/>'
            +'<button value="thumbs" class="btnset_radio"/>'
            +'<button value="thumbs3" class="btnset_radio"/>';

        if(this.options.entityName=='records' && this.options.allow_record_content_view){
            smodes += '<button value="record_content" class="btnset_radio"/>';
           
        }
        
        
        this.view_mode_selector = $( "<div>" )
        //.css({'position':'absolute','right':right_padding+'px'})
        .css({'float':'right','padding':'2px '+right_padding+'px'})
        .html(smodes)
        .appendTo( this.div_toolbar );

        if(this.options.show_fancybox_viewer){
            let r_padding = right_padding + 3;
            this.view_mode_selector.css('padding', '2px '+r_padding+'px 2px '+right_padding+'px');
        }

        this.view_mode_selector.find('button[value="list"]')
            .button({icon: "ui-icon-menu", showLabel:false, label:window.hWin.HR('Single lines')})
            .css('font-size','1em');
        this.view_mode_selector.find('button[value="icons"]')
            .button({icon: "ui-icon-list", showLabel:false, label:window.hWin.HR('Single lines with icon')})
            .css('font-size','1em'); //ui-icon-view-icons-b
        this.view_mode_selector.find('button[value="thumbs"]')
            .button({icon: "ui-icon-view-icons", showLabel:false, label:window.hWin.HR('Small images')})
            .css('font-size','1em');
        this.view_mode_selector.find('button[value="thumbs3"]')
            .button({icon: "ui-icon-stop", showLabel:false, label:window.hWin.HR('Large image')})
            .css('font-size','1em');
        this.view_mode_selector.find('button[value="record_content"]')
            .button({icon: "ui-icon-template", showLabel:false, label:window.hWin.HR('Record contents')})
            .css('font-size','1em'); //ui-icon-newspaper
        //this.view_mode_selector.find('button[value="tabs"]')
        //    .button({icon: "ui-icon-windows", showLabel:false, label:window.hWin.HR('Tab controls')})
        //    .css('font-size','1em'); //ui-icon-newspaper
        this.view_mode_selector.controlgroup();
        
        this._on( this.view_mode_selector.find('button'), {
            click: function(event) {
                let btn = $(event.target).parent('button');
                let view_mode = btn.attr('value');

                let total_inquery = (this._currentRecordset!=null) ? this._currentRecordset.count_total() : 0;
                total_inquery = (this._currentSubset) ? this._currentSubset.count_total() : total_inquery;

                if(view_mode == 'record_content' && total_inquery > 100){
                    // Block record content view at high result counts
                    return;
                }

                this.applyViewMode(view_mode);
                window.hWin.HAPI4.save_pref('rec_list_viewmode_'+this.options.entityName, view_mode);
        }});

        this.span_pagination = $( "<div>")
        .css({'float':'right','padding': '3px 0.5em 0 0'})
        //'vertical-align':'top',
        //.css({'float':'right','padding':'6px 0.5em 0 0'})
        .appendTo( this.div_toolbar );

        this.span_info = $( "<div>")
        .css({'float':'right','padding': '6px 0.5em 0 0','font-style':'italic'})
        //'vertical-align':'top',
        //.css({'float':'right','padding':'0.6em 0.5em 0 0','font-style':'italic'})
        .appendTo( this.div_toolbar );

        
        if(this.options.is_h6style && this.options.show_search_form){

            this.div_search_form = $('<div>').search({
                    is_h6style: this.options.is_h6style,
                    btn_visible_newrecord: false,
                    search_button_label: window.hWin.HR('Filter'),
                    btn_entity_filter: false})
                .css({  //display:'block','max-height':'55px','height':'55px',
                        padding:'15px 10px 35px 4px','border-bottom':'1px solid gray'}) //,width:'100%'
                .appendTo(this.div_header);
        
        }
        
        if(this.options.show_menu){
            if(window.hWin.HEURIST4.util.isFunction($('body').resultListMenu)){

                this.div_actions = $('<div>').resultListMenu({
                        is_h6style: this.options.is_h6style,
                        menu_class: this.options.header_class,
                        resultList: this.element});
                
                if(this.options.is_h6style){
                    
                    this.div_actions.css({display:'inline-block','padding':'4px','min-height':'30px'})
                    .appendTo(this.div_header);
                    
                }else{
                    this.div_actions.css({display:'inline-block','padding-bottom':'4px','padding-left':'6px'})
                    //.css({'position':'absolute','top':3,'left':2})
                    .appendTo(this.div_toolbar);
                }
            }
        }

        if(this.options.show_export_button){

            this.export_button = $('<button>', {
                text: window.hWin.HR('Export'), title: window.hWin.HR('Export current results'), 
                class: 'btnExportRecords ui-button-action', style: 'margin: 6px 10px 2px 0px; float: right;'
            }).button({icon: 'ui-icon-download'}).prependTo(this.div_toolbar);

            this.export_button[0].style.setProperty('color', '#FFF', 'important');

            this._on(this.export_button, {
                click: this._exportRecords
            });
        }else if(this.options.entityName=='records' && !this._is_publication){ // show CSV export button on backend

            this.export_button = $('<button>', {
                text: window.hWin.HR('CSV'), title: 'Export current results in CSV format',
                class: 'ui-main-color', style: 'padding: 8px; float: right; margin-right: 10px;'
            }).button({icon: 'ui-icon-arrowthick-1-s'}).insertBefore(this.view_mode_selector);

            this._on(this.export_button, {
                click: this._exportRecords
            });
        }

        if(this.options.header_class){
            this.div_header.addClass(this.options.header_class);
            this.div_toolbar.removeClass('ui-heurist-bg-light').addClass(this.options.header_class);
            this.view_mode_selector.find('button').addClass(this.options.header_class).css({'border':'none'});
            this.span_pagination.find('button').addClass(this.options.header_class).css({'border':'none'});
        }

        
        this._showHideOnWidth();

        //-----------------------
        this.setHeaderText(window.hWin.HR('Filtered Result'));

    },
    
    _setOptions: function() {
        /*if(!(arguments['pagesize']>0)){
        arguments['pagesize'] = 9999999999999;
        }*/
        // _super and _superApply handle keeping the right this-context
        this._superApply( arguments );
        this._refresh();
    },

    _setOption: function( key, value ) {
        if(key=='onScroll'){
            this.options.onScroll = value;

            this._off(this.div_content, 'scroll');
            if(window.hWin.HEURIST4.util.isFunction(value)){
                this._on(this.div_content, {'scroll':value});
            }
                          
            
        }else{
            this._super( key, value );
            if(key == 'rendererHeader' || key == 'view_mode'){
                this.applyViewMode(this.options.view_mode, true);
            }
        }
    },


    /* private function */
    _refresh: function(){

        if(!this._init_completed) return;

        this.applyViewMode(this.options.view_mode);

        if(window.hWin.HAPI4.currentUser.ugr_ID > 0){
            $(this.div_toolbar).find('.logged-in-only').css('visibility','visible');
            $(this.div_content).find('.logged-in-only').css('visibility','visible');
            $(this.div_content).find('.recordDiv').removeClass('rl-not-logged-in');
        }else{
            $(this.div_toolbar).find('.logged-in-only').css('visibility','hidden');
            $(this.div_content).find('.logged-in-only').css('visibility','hidden');
            $(this.div_content).find('.recordDiv').addClass('rl-not-logged-in');
        }

        if(this._is_publication || this.options.transparent_background){
            //this is CMS publication - take css from parent
            this.element.removeClass('ui-heurist-bg-light').addClass('ui-widget-content').css({'background':'none','border':'none'});
            this.div_toolbar.css({'background':'none'});
            this.div_content.removeClass('ui-heurist-bg-light').css({'background':'none'});
        }else{
            this.element.addClass('ui-heurist-bg-light');
            
            this.element.parent().css({'background':'none','border':'none'});

           
            this.div_content.addClass('ui-heurist-bg-light');
        }
        
        //show/hide elements on toolbar
        if(this.div_actions){
            if(this.options.show_menu){        
                this.div_actions.show();
            }else{
                this.div_actions.hide();
            }
        }
        
        //counter and pagination        
        this._showHideOnWidth();
    },

    //adjust top,height according to visibility settings -----------
    _adjustHeadersPos: function(){

        if(!this.element.is(':visible')) return;
        
        let top = 0;    
        if(this.options.show_inner_header || !window.hWin.HEURIST4.util.isempty(this.options.title)){
            this.div_header.show();
            top = this.div_header.height();
        }else{
            this.div_header.hide();
        }

        let override_option = this.options.support_collection || (this.options.show_export_button && this.export_button.is(':visible'));
        if(this.options.show_toolbar || override_option){
            this.div_toolbar.css({'top':(top-1)+'px', height:'auto'});
            this.div_toolbar.show();
            top = top + this.div_toolbar.height();
        }else{
            this.div_toolbar.hide();
        }

        let has_content_header = this.div_content_header && this.div_content_header.is(':visible');
       

        if(has_content_header){ //table_header
                    
            //adjust columns width in header with columns width in div_content
            if(!this.options.list_mode_is_table){
                let header_columns = this.div_content_header.find('.item');
                let cols = this.div_content.find('.recordDiv:first').find('.item');
                let tot = 0;
                cols.each(function(i, col_item){
                    if(i>0 && i<header_columns.length){ //skip recordSelector
                        $(header_columns[i-1]).width( $(col_item).width() );    
                        tot = tot + $(col_item).width() + 4;
                    }
                });
                //adjust last column
                if(header_columns.length>0){
                    $(header_columns[header_columns.length-1]).width( this.div_content_header.width()-tot-20 );    
                }
            }
            
            top = top + this.div_content_header.height();
        }
   
        //move content down to leave space for header
        if(this.div_content.css('position')=='absolute'){
            this.div_content.css({'top': top+'px'});    
        }else{
            this.div_content.css({'margin-top': top+'px'});    
        }
        
		
		if(has_content_header){
            this.div_content_header
                    .position({my:'left bottom', at:'left top', of:this.div_content});
        }
    },
    //
    // show hide pagination and info panel depend on width
    //
    _showHideOnWidth: function(){      
        
        let total_inquery = (this._currentRecordset!=null)?this._currentRecordset.count_total():0;
        
        if(this.options.show_viewmode==true && total_inquery>0){
            this.view_mode_selector.show();
        }else{
            this.view_mode_selector.hide();
        }
        if(this.options.support_reorder==true && window.hWin.HAPI4.has_access()){
            this.reorder_button.show();
        }else{
            this.reorder_button.hide();
        }
        
        let handleCollections = this._is_publication && this.options.support_collection;
        if(this.options.show_counter || handleCollections){

            if(this.max_page>1) this.span_pagination.css({'display':'inline-block'});
            this.span_info.css({'display':'inline-block'});
            

            this._updateInfo();

        }else{
            this.span_pagination.hide();
            this.span_info.hide();
        }
        
        if(this.options.view_mode=='tabs' && this.div_content.tabs('instance')){
            try{
                this.div_content.tabs('pagingResize');
            }catch(ex){
                console.log('pagingResize');
            }
        }
        
        this._adjustHeadersPos();

    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {

        if(this._events){
            $(this.document).off(this._events);
        }

        const that = this;

        if(this.div_header) this.div_header.remove();
        if(this.div_content_header) this.div_content_header.remove();

        // remove generated elements
        if(this.action_buttons_div){
        this.action_buttons_div.remove();
        } 
        if(this.btn_search_save){
            this.btn_search_save.remove(); 
            //this.btn_search_save_withorder.remove(); 
            if(this.sortResultListDlg){
                    this.sortResultList.remove();
                    this.sortResultListDlg.remove();
            }
        }
        if(this.export_button) { this.export_button.remove(); }
        if(this.div_actions) this.div_actions.remove();
        if(this.div_search_form) this.div_search_form.remove();
        if(this.fancybox_button) this.fancybox_button.remove();
        if(this.reorder_button) this.reorder_button.remove();
        if(this.div_toolbar) this.div_toolbar.remove();
        if(this.div_content) this.div_content.remove();
        if(this.div_coverall) this.div_coverall.remove();

        if(this.menu_tags) this.menu_tags.remove();
        if(this.menu_share) this.menu_share.remove();
        if(this.menu_more) this.menu_more.remove();
        if(this.menu_view) this.menu_view.remove();

        this._removeNavButtons();


        this._currentMultiSelection = null;

    },

    //
    //
    //
    _removeNavButtons: function(){
        if(this.btn_page_menu){
            this._off( this.btn_page_menu, 'click');
            this._off( this.btn_page_prev, 'click');
            this._off( this.btn_page_next, 'click');
            this.btn_page_menu.remove();
            this.btn_page_prev.remove();
            this.btn_page_next.remove();
            this.menu_pages.remove();
        }
    },

    //
    // switcher listener - list;icons;thumbs
    //
    applyViewMode: function(newmode, forceapply){

        
        let allowed = ['list','icons','thumbs','thumbs3','horizontal','vertical','icons_list','record_content','tabs'];
        
        if(newmode=='icons_expanded') newmode='record_content'; //backward capability 

        if(newmode == 'record_content' && !this.options.allow_record_content_view){ // switch to list mode and hide button
            newmode = 'list';
            this.view_mode_selector.find('button[value="record_content"]').hide();
        }

        if(window.hWin.HEURIST4.util.isempty(newmode) || allowed.indexOf(newmode)<0) {
            newmode = window.hWin.HAPI4.get_prefs('rec_list_viewmode_'+this.options.entityName);
        }

        if(window.hWin.HEURIST4.util.isempty(newmode) 
            || (newmode=='record_content' && this.options.entityName!='records')){
            newmode = 'list'; //default
        }
        if(newmode=='record_content') {
            newmode = 'icons';    
            this._expandAllDivs = true;
            forceapply = true;
        }else{
            forceapply = this._expandAllDivs;
            this._expandAllDivs = false;
        }
        
        let old_mode = this.options.view_mode;

        if(this._is_publication && (newmode == 'thumbs' || newmode == 'thumbs3')){
            this.div_content.css('text-align', 'center');
        }else{
            this.div_content.css('text-align', '');
        }

        if(!this.div_content.hasClass(newmode) || forceapply===true){
            
            this.closeExpandedDivs();
            this.options.view_mode = newmode;
            
            /*
            if(newmode){
                this.options.view_mode = newmode;
            }else{
                //load saved value
                if(!this.options.view_mode){
                    this.options.view_mode = window.hWin.HAPI4.get_prefs('rec_list_viewmode_'+this.options.entityName);
                }
                if(!this.options.view_mode){
                    this.options.view_mode = 'list'; //default value
                }
                newmode = this.options.view_mode;
            }
            */
            
            this.div_content.removeClass('list icons thumbs thumbs3 horizontal vertical icons_list tabs');
            this.div_content.addClass(newmode);
            
            this._current_view_mode = newmode;
            
            if(newmode=='horizontal'){ // || newmode=='icons_list'){
            
                this.div_content.css('overflow-y','hidden');
                
                this._on(this.div_content,
                        {'mousewheel':this._recordDivNavigateUpDown
                /*
                function(event) {
                    if(event.originalEvent){
                        this.scrollLeft += (event.originalEvent.deltaY);
                        event.preventDefault();
                    }
                }*/
                });
                
                if(newmode=='horizontal'){
                
                    let h = this.div_content.height();
                        h = (((h<60) ?60 :((h>200)?230:h))-30) + 'px';
                    this.div_content.find('.recordDiv').css({
                        height: h,
                        width: h,
                        'min-width': h
                    });
                    
                    this.div_content.find('.recordTitleInPlaceOfThumb').css({height: h});
                    
                }
                
            }else if(newmode=='vertical'){ 

                this.div_content.css('overflow-x','hidden');
                
                let w = this.div_content.width();
                    w = (((w<60) ?60 :((w>200)?230:w))-30) + 'px';
                    
                this.div_content.find('.recordDiv').css({
                        height: w,
                        width: w,
                        'min-height':w 
                    });
                
            }
            else{
                this.div_content.css('overflow-y','auto');
                
                if(newmode=='list' && this.options.list_mode_is_table){
                    this.div_content.css({'display':'table','width':'100%'});
                }else{
                    this.div_content.css({'display':'block'});
                    
                    if(this._expandAllDivs){
                        this.expandDetailsInlineALL();   
                    }
                }
                    
            
                this.div_content.find('.recordDiv').attr('style',null);
                this._off(this.div_content, 'mousewheel');
            }
        }
        
        //show hide table header
        if(window.hWin.HEURIST4.util.isFunction(this.options.rendererHeader)){
            
            let header_html = (this.options.view_mode=='list')
                    ?this.options.rendererHeader():'';
            
            //create div for table header
            if( window.hWin.HEURIST4.util.isnull(this.div_content_header )){
                    this.div_content_header = $('<div>')
                        .addClass('table_header')
                        .insertBefore(this.div_content);
                    if(this.options.list_mode_is_table){
                        this.div_content_header.css('font-size', '10px');
                    }
                        
            }
            if(window.hWin.HEURIST4.util.isempty(header_html)){
                this.div_content_header.hide();
            }else{
                this.div_content_header.html( header_html ).show();
            }
        } 
        if(this.options.view_mode=='tabs' || old_mode=='tabs' || window.hWin.HEURIST4.util.isFunction(this.options.renderer)){
            //this._renderTabHeader(); //add <ul> with list of records to div_content
            this._renderPage(this.current_page);
        }
    
        this._adjustHeadersPos();
       

        if(this.view_mode_selector){
            
            this.view_mode_selector.find('button')
                //.removeClass(this.options.is_h6style?'':'ui-heurist-btn-header1')
                .removeClass('ui-heurist-btn-header1')
                .css({'border':'none'});
            if(this._expandAllDivs){
                newmode='record_content';
                this._current_view_mode = newmode;
            } 
          
               
            let btn =   this.view_mode_selector.find('button[value="'+newmode+'"]');
            
            if(this.options.header_class==null) btn.addClass('ui-heurist-btn-header1')                
            btn.css({'border':'1px solid'});
        }
        
        $('.password-reminder-popup').dialog('close');
    },

    //
    //
    //
    clearAllRecordDivs: function(new_title, message){

        $('.password-reminder-popup').dialog('close');
       
        this._lastSelectedIndex = null;

        if(this.div_coverall){
            this.div_coverall.hide();
        }
        

        if(this.div_content){
            
            let eles = this.div_content.find('div.recordTitle');
            $.each(eles,function(i,e){if($(e).tooltip('instance')) $(e).tooltip('destroy');});
            eles = this.div_content.find('div.rolloverTooltip');
            $.each(eles,function(i,e){if($(e).tooltip('instance')) $(e).tooltip('destroy');});
            
            let $allrecs = this.div_content.find('.recordDiv');
            this._off( $allrecs, "click");
           
            
            if(this.div_content.tabs('instance')){
                //this.div_content.tabs('paging', {}); 
                let keep_top = this.div_content.css('top');
                let keep_classes = this.div_content.attr('class');
                
                this.div_content.tabs( 'destroy' ); 
                this.div_content.remove();
                this.div_content = $( "<div>" )
                //.addClass('div-result-list-content ent_content_full')
                .addClass(keep_classes)
                .css({'overflow-y':'auto',top:keep_top})
                .insertAfter( this.div_toolbar );
            }
            
            this.div_content[0].innerHTML = window.hWin.HEURIST4.util.isempty(message)?'':message;//.empty();  //clear
        }

        if(new_title!=null){

            if(this.div_header!=null) {
                this.setHeaderText(new_title);
            }
            if(new_title==''){
                this.triggerSelection();
            }
        }


        this._count_of_divs = 0;
    },
    
    //
    // this is public method, it is called on search complete (if events are not used)
    //
    updateResultSet: function( recordset, request ){

        this.loadanimation(false);
        
        if(this.options.list_mode_is_table){
            this.applyViewMode('list',true); //redraw header
        }
        
        this.clearAllRecordDivs(null);
        this._renderPagesNavigator();
        this._renderRecordsIncrementally(recordset);
        this._showHideOnWidth()
    },

    //
    // Add new divs for current page
    //
    // @param recordset
    //
    _renderRecordsIncrementally: function( recordset ){

        this._currentRecordset = recordset;

        let total_count_of_curr_request = 0;

        if(this._currentRecordset){
            total_count_of_curr_request = (recordset!=null)?recordset.count_total():0;
            this._renderProgress();
        }

        if( total_count_of_curr_request > 0 )
        {
            if(this._count_of_divs<this.options.pagesize){ // DRAW CURRENT PAGE
                this._renderPage(0, recordset);
            }

        }else if(this._count_of_divs<1) {   // EMPTY RESULT SET

            this._renderPagesNavigator();

            if(this.options.emptyMessageURL){
                this.div_content.load( this.options.emptyMessageURL );
            }else{
                this._renderEmptyMessage( 1 );
            }

        }

    },

    //
    // Add message on div_content 
    // for search start and empty result
    //
    renderMessage: function(msg){

        this.clearAllRecordDivs('');
        
        let $emptyres = $('<div>')
        .css('padding','1em')
        .html(msg)
        .appendTo(this.div_content);

        return $emptyres;
    },
    
    //
    // mode 
    // 0 - no startup filter
    // 1 - no result
    //
    _renderEmptyMessage: function(mode){

        if( !window.hWin.HEURIST4.util.isempty(this.options['empty_remark']) ){
            
            $('<div>').css('padding','8px').html(this.options['empty_remark']).appendTo(this.div_content);

        }else
        if(this.options.entityName!='records'){

            window.hWin.HRes('resultListEmptyEntity', this.div_content);
            
        }else if(!this.options.blank_empty_remark){

            let that = this;
            
            let $emptyres = $('<div>')
            .css('padding','1em')
            .load(window.hWin.HRes('resultListEmptyMsg'),
                function(){
                    $emptyres.find('.acc')
                    .accordion({collapsible:true,heightStyle:'content',
                            activate: function( event, ui ) {
                               
                            }});
                    $emptyres.find('p').css({'padding-top':'10px'});
                    
                    $emptyres.find('.acc > div').css({
                        background: 'none', border: 'none'});    
                        
                    if(mode==0){   //no filter
                        $emptyres.find('.no-filter').show();
                        $emptyres.find('.not-found').hide();
                        $emptyres.find('.acc1').accordion({active:false});
                    }else{       //no result
                        $emptyres.find('.no-filter').hide();
                        $emptyres.find('.not-found').show();
                        $emptyres.find('.acc2').accordion({active:false});

                         //logged in and current search was by bookmarks
                        if(window.hWin.HAPI4.currentUser.ugr_ID>0 && that._query_request){
                            let domain = that._query_request.w
                            if((domain=='b' || domain=='bookmark')){
                                let $al = $('<a href="#">')
                                .text(window.hWin.HR('Click here to search the whole database'))
                                .appendTo($emptyres);
                                that._on(  $al, {
                                    click: that._doSearch4
                                });
                            }
                            let q = that._query_request.q, rt = 0;
                            if ($.isPlainObject(q) && Object.keys(q).length==1 && !q['t']){
                                rt = q['t'];
                            }else if (typeof q === 'string' && q.indexOf('t:')==0){
                                  q = q.split(':');
                                  if(window.hWin.HEURIST4.util.isNumber(q[1])){
                                      rt = q[1];
                                  }
                            }
                            if(rt>0 && $Db.rty(rt, 'rty_Name')){
                                $('<span style="padding: 0 10px;font-weight:bold">('
                                        +$Db.rty(rt,'rty_Plural')+')</span>')
                                    .appendTo($emptyres.find('.not-found2'));
                                $('<div>').button({label:window.hWin.HR('Add')+' '+$Db.rty(rt,'rty_Name'), icon:'ui-icon-plusthick'})
                                    .on('click', function(){
                                        window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                                            {new_record_params:{RecTypeID:rt}});                                        
                                    })
                                    .appendTo($emptyres.find('.not-found2'));
                            }
                        }
                    
                    }
                    
                    $emptyres.find('.acc > h3')
                        //.removeClass('ui-state-active')
                        //.addClass('ui-widget-no-background')
                        .css({
                                border: 'none',
                                'font-size': 'larger',
                                'font-weight': 'bold'
                            });
                }
            ).appendTo(this.div_content);

        }        
    },
    
    //
    //  
    //
    _renderStartupMessageComposedFromRecord: function(){

        const recID = window.hWin.HEURIST4.util.getUrlParameter('Startinfo');
        if(recID>0){

            if(this._startupInfo){
                this.div_coverall.show();
            }else if(recID>0){

                let details = [window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
                    window.hWin.HAPI4.sysinfo['dbconst']['DT_SHORT_SUMMARY'],
                    window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION']];

                const request = {q: 'ids:'+recID, w: 'all', detail:details };

                let that = this;

                window.hWin.HAPI4.RecordSearch.doSearchWithCallback( request, function( new_recordset )
                    {
                        if(new_recordset!=null){
                            let record = new_recordset.getFirstRecord();

                            let title = new_recordset.fld(record, window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME']);
                            let summary = new_recordset.fld(record, window.hWin.HAPI4.sysinfo['dbconst']['DT_SHORT_SUMMARY']);
                            let extended = new_recordset.fld(record, window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION']);

                            //compose
                            that._startupInfo = '<div style="padding:20px 2em"><h2>'+title+'</h2><div style="padding-top:10px">'
                            +(summary?summary:'')+'</div><div>'
                            +(extended?extended:'')+'</div></div>';

                            that.div_coverall.empty();
                            $(that._startupInfo).appendTo(that.div_coverall);
                            that.div_coverall.show();
                        }
                });

            }


        }        
    },

    //
    //  div for not loaded record
    //
    _renderRecord_html_stub: function(recID){

        let html = '<div class="recordDiv" recid="'+recID+'" >' //id="rd'+recID+'" 
        + '<div class="recordIcons">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif">'
        + '</div>'
        + '<div class="recordTitle">' + recID
        + '...</div>'
/*        
        + '<div title="Click to edit record (opens new tab)" class="rec_edit_link logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
        + '</div>'
        + '<div title="Click to view record (opens as popup)" class="rec_view_link ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" role="button" aria-disabled="false">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-comment"></span><span class="ui-button-text"></span>'
        + '</div>'
*/        
        + '</div>';

        return html;

    },

    //
    // Render div for particular record
    // it can call external renderer if it is defined in options
    //
    _renderRecord_html: function(recordset, record){

        //call external/custom function to render
        if(window.hWin.HEURIST4.util.isFunction(this.options.renderer)){
            return this.options.renderer.call(this, recordset, record);
        }

        //@todo - move render for Records into separate method of manageRecords

        function fld(fldname){
            return recordset.fld(record, fldname);
        }
        
        /*
        0 .'bkm_ID,'
        1 .'bkm_UGrpID,'
        2 .'rec_ID,'
        3 .'rec_URL,'
        4 .'rec_RecTypeID,'
        5 .'rec_Title,'
        6 .'rec_OwnerUGrpID,'
        7 .'rec_NonOwnerVisibility,'
        8. rec_ThumbnailURL

        9 .'rec_URLLastVerified,'
        10 .'rec_URLErrorMessage,'
        11 .'bkm_PwdReminder ';
        11  thumbnailURL - may not exist
        */
        
        let is_logged = window.hWin.HAPI4.has_access();

        let recID = fld('rec_ID');
        let rectypeID = fld('rec_RecTypeID');
        let bkm_ID = fld('bkm_ID');
        let recTitle = fld('rec_Title'); 
        let recTitle_strip_all = window.hWin.HEURIST4.util.htmlEscape(window.hWin.HEURIST4.util.stripTags(recTitle))+' id:'+recID;
        let recTitle_strip1 = window.hWin.HEURIST4.util.stripTags(recTitle,'u, i, b, strong, em');
        let recTitle_strip2 = window.hWin.HEURIST4.util.stripTags(recTitle,'a, u, i, b, strong, em');
        let recIcon = fld('rec_Icon');
        if(!recIcon) recIcon = rectypeID;
        recIcon = window.hWin.HAPI4.iconBaseURL + recIcon + this._icon_timer_suffix;


        //get thumbnail if available for this record, or generic thumbnail for record type
        let html_thumb = '', rectypeTitleClass = '';
        if(fld('rec_ThumbnailURL')){
            html_thumb = '<div class="recTypeThumb realThumb" title="'+
                recTitle_strip_all+'" style="background-image: url(&quot;'
                + fld('rec_ThumbnailURL') + '&quot;);" data-id="'+recID+'"></div>';
        }else{
            rectypeTitleClass = 'recordTitleInPlaceOfThumb';
            if(this.options.view_mode=='horizontal' || this.options.view_mode=='vertical'){
                html_thumb = '<div class="recordTitleInPlaceOfThumb">' // '+rectypeTitleClass+'
                    +recTitle_strip2
                    + '</div>';
                
            }else{
                html_thumb = '<div class="recTypeThumb rectypeThumb" title="'
                    +recTitle_strip_all+'" style="background-image: url(&quot;'
                    + window.hWin.HAPI4.iconBaseURL  + rectypeID + this._icon_timer_suffix + '&version=thumb&quot;);"></div>';
            }
            
        }
        // Show a key icon and popup if there is a password reminder string
        let html_p_reminder = '';
        let p_reminder = window.hWin.HEURIST4.util.htmlEscape(fld('bkm_PwdReminder'));
        if(p_reminder){
            html_p_reminder =  '<span class="logged-in-only ui-icon ui-icon-key rec_p_reminder" style="display:inline;left:14px;font-size:0.99em"></span>';
            p_reminder = ' data-reminder="'+p_reminder+'" ';
        }else{
            p_reminder = '';
        }

        function __getOwnerName(ugr_id){ //we may use SystemMgr.usr_names however it calls server
            if(ugr_id==0){
                return 'Everyone';
            }else if(ugr_id== window.hWin.HAPI4.currentUser.ugr_ID){
                return window.hWin.HAPI4.currentUser.ugr_FullName;
            }else if(window.hWin.HAPI4.sysinfo.db_usergroups[ugr_id]){
                return window.hWin.HAPI4.sysinfo.db_usergroups[ugr_id];    
            }else{
                return 'user# '+ugr_id;
            }
        }

        // Show owner group and accessibility to others as colour code
        let html_owner = '';
        let owner_id = fld('rec_OwnerUGrpID');
        if(owner_id){  // && owner_id!='0'
            // 0 owner group is 'everyone' which is treated as automatically making it public (although this is not logical)
            // TODO: I think 0 should be treated like any other owner group in terms of public visibility
            let visibility = fld('rec_NonOwnerVisibility');
            // gray - hidden, green = viewable (logged in user) = default, orange = pending, red = public = most 'dangerous'
            let clr  = 'blue';
            
            if(visibility=='hidden'){
                clr = 'red';
                visibility = 'private - hidden from non-owners';
            }else if(visibility=='viewable'){
                clr = 'orange';
                visibility = 'visible to any logged-in user';
            }else if(visibility=='pending'){
                clr = 'green';
                visibility = 'pending (viewable by anyone, changes pending)';
            }else { //(visibility=='public')
                clr = 'blue';
                visibility = 'public (viewable by anyone)';
            }
            
            let hint = __getOwnerName(owner_id)+', '+window.hWin.HR(visibility);

            // Displays owner group ID, green if hidden, gray if visible to others, red if public visibility
            html_owner =  '<span class="rec_owner logged-in-only" style="width:20px;padding-top:2px;display:inline-block;color:'
                     + clr + '" title="' + hint + '"><b>' + owner_id + '</b></span>';

            if(is_logged){ // hide eye if user not logged in

                // Display eye if record is publicly viewable, otherwise use a crossed out eye - Both icons come from: iconoir.com
                let vis_icon = '<span class="ui-icon ui-icon-eye-open"></span>';
                let vis_title = window.hWin.HR('Publicly visible');

                if(clr != 'blue'){

                    vis_icon = '<span class="ui-icon ui-icon-eye-crossed"></span>';

                    vis_title = window.hWin.HR('resultList_private_record');
                }
                html_owner = html_owner 
                + '<span title="'+vis_title+'" style="position:relative;left:-8px;">'
                    + vis_icon
                + '</span>';
            }
        }
        
        let btn_icon_only = window.hWin.HEURIST4.util.isempty(this.options.recordDivClass)
                                ?' ui-button-icon-only':'';

        let sCount = '';
        if(this.options.aggregate_values){
            sCount = this.options.aggregate_values[recID];
            if(!(sCount>0)) {
                sCount = ''
            }else {
                
                if(this.options.aggregate_link){    
                    sCount = '<a href="'
                    + window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                    + '&q=' + encodeURIComponent(this.options.aggregate_link.replace('[ID]',recID))
                    + '&nometadatadisplay=true" target="_blank" title="Count of records which reference this record. Opens list in new tab">'+sCount+'</a>';
                }
                sCount = '<span style="margin-right:10px">'+sCount+'</span>';
            }
        }                   

        // Apply user pref font size
        let usr_font_size = window.hWin.HAPI4.get_prefs_def('userFontSize', 0);
        let title_font_size = '';
        if(usr_font_size != 0 && !this._is_publication){
            usr_font_size = (usr_font_size < 8) ? 8 : (usr_font_size > 18) ? 18 : usr_font_size;
            title_font_size = ' style="font-size: '+usr_font_size+'px"';
        }

        // construct the line or block
        let html = '<div class="recordDiv '+this.options.recordDivClass
        +'" recid="'+recID+'" '+p_reminder+' rectype="'+rectypeID+'" bkmk_id="'+bkm_ID+'">' //id="rd'+recID+'" 
        + html_thumb
        
        + '<div class="recordIcons">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" class="rt-icon" style="background-image: url(&quot;'+recIcon+'&quot;);"/> '
        +     '<span class="logged-in-only ui-icon ui-icon-bookmark" style="color:'+(bkm_ID?'#ff8844':'#dddddd')+';display:inline-block;"></span>'
        +     html_owner
        +     html_p_reminder
        + '</div>'


        // it is useful to display the record title as a rollover in case the title is too long for the current display area
        + '<div title="'+(is_logged?'dbl-click to edit: ':'')+recTitle_strip_all+'" class="recordTitle" '+title_font_size+'>' //  '+rectypeTitleClass+'
        +   sCount;

        let show_as_link = rectypeID > 0 && $Db.rty(rectypeID, 'rty_ShowURLOnEditForm') != 0;
        if(this.options.show_url_as_link && show_as_link && fld('rec_URL')){
            if(window.hWin.HEURIST4.util.isempty(fld('rec_URLErrorMessage'))){
                html = html + '<a href="'+fld('rec_URL')+'" target="_blank">'+ recTitle_strip1 + '</a>';
            }else{
                html = html + '<span class="ui-icon ui-icon-alert" title="'
                + window.hWin.HEURIST4.util.htmlEscape(fld('rec_URLErrorMessage'))
                +'"></span>'+recTitle_strip1;
            }
        }else{
            html = html + recTitle_strip2;
        }
            
            
            
        html = html + '</div>'

        
        //action button container
        + (this.options.show_action_buttons?(
         '<div class="action-button-container">' 

        + '<div title="'+window.hWin.HR('resultList_action_edit')+'" '
        + 'class="rec_edit_link action-button logged-in-only ui-button ui-widget ui-state-default ui-corner-all'+btn_icon_only+'" '
        + 'role="button" aria-disabled="false" data-key="edit">'
        + '<span class="ui-button-text">Edit</span>'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span>'
        + '</div>'

        + '<div title="'+window.hWin.HR('resultList_action_edit2')+'" '
        + ' class="rec_edit_link_ext action-button logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only"'
        + ' role="button" aria-disabled="false" data-key="edit_ext">'
        + '<span class="ui-button-text">New tab</span>'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-newwin"></span>'
        + '</div>'  // Replace ui-icon-pencil with ui-icon-extlink and swap position when this is finished 
        
        /* Ian removed 5/2/2020. TODO: Need to replace with Select, Preview and Download buttons */
        + ((this.options.recordview_onselect===false || this.options.recordview_onselect==='none')
          ?('<div title="'+window.hWin.HR('resultList_action_view')+'" '
        + 'class="rec_view_link action-button ui-button ui-widget ui-state-default ui-corner-all'+btn_icon_only+'" '
        + 'role="button" aria-disabled="false">'
        + '<span class="ui-button-text">Preview</span>'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-comment"></span>'
        + '</div>'):'')
        
        //toadd and toremove classes works with div.collected see h4styles
        + ((this.options.support_collection)
          ?('<div title="Click to collect/remove from collection" '
        + 'class="rec_collect action-button ui-button ui-widget ui-state-default ui-corner-all'+btn_icon_only+'" '
        + 'role="button" aria-disabled="false">'
        + '<span class="ui-button-text toadd" style="min-width: 28px;">Collect</span>'
        + '<span class="ui-button-text toremove" style="display:none;min-width: 28px;">Remove</span>'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-circle-plus toadd" style="font-size:11px"></span>'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-circle-minus toremove" style="display:none;font-size:11px"></span>'
        + '</div>'):'')

        // Icons at end allow editing and viewing data for the record when the Record viewing tab is not visible
        // TODO: add an open-in-new-search icon to the display of a record in the results list
        + ((!this.options.show_url_as_link && fld('rec_URL'))
            ?
        '<div title="'+window.hWin.HR('resultList_action_view2')+'" '
        + 'class="rec_view_link_ext action-button ui-button ui-widget ui-state-default ui-corner-all'+btn_icon_only+'" '
        + 'role="button" aria-disabled="false">'
        + '<span class="ui-button-text">Link</span>'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-extlink"></span>'
        + '</div>'
            :'')

        + ((rectypeID==window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_LAYER'] ||
            rectypeID==window.hWin.HAPI4.sysinfo['dbconst']['RT_TLCMAP_DATASET'])
            ?
        '<div title="'+window.hWin.HR('resultList_action_map')+'" '
        + 'class="rec_expand_on_map action-button ui-button ui-widget ui-state-default ui-corner-all'+btn_icon_only+'" '
        + 'role="button" aria-disabled="false">'
        + '<span class="ui-button-text">'+window.hWin.HR('Show data')+'</span>'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-globe"></span>'
        + '</div>'
        +'<div title="'+window.hWin.HR('resultList_action_dataset')+'" '
        + 'class="rec_download action-button ui-button ui-widget ui-state-default ui-corner-all'+btn_icon_only+'" '
        + 'role="button" aria-disabled="false">'
        + '<span class="ui-button-text">'+window.hWin.HR('Download')+'</span>'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-arrowstop-1-s"></span>'
        + '</div>'
            :'')
        
        + ((rectypeID==window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT'])
            ?
        '<div title="'+window.hWin.HR('resultList_action_embed')+'" '
        + 'class="rec_view_link_ext action-button ui-button ui-widget ui-state-default ui-corner-all'+btn_icon_only+'" '
        + 'role="button" aria-disabled="false">'
        + '<span class="ui-button-text">'+window.hWin.HR('Embed')+'</span>'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-globe"></span>'
        + '</div>'
        + '<div title="'+window.hWin.HR('resultList_action_delete')+'" '
        + 'class="rec_delete action-button logged-in-only ui-button ui-widget ui-state-default ui-corner-all'+btn_icon_only+'" '
        + 'role="button" aria-disabled="false">'
        + '<span class="ui-button-text">'+window.hWin.HR('Delete')+'</span>'
        + '<span class="ui-button-icon-primary ui-icon ui-icon-trash"></span>'
        + '</div>'
            :'')
        
        + '</div>'):'') //END action button container
        
        + '</div>';


        return html;
    },

    //
    // assign tooltip (title) for recordDiv
    //
    _recordDivOnHover: function(event){
        
        if(window.hWin.HEURIST4.util.isFunction(this.options.renderer)) return;
    
        let $rdiv = $(event.target);
        if($rdiv.hasClass('rt-icon') && !$rdiv.attr('title')){

            $rdiv = $rdiv.parents('.recordDiv')
            let rectypeID = $rdiv.attr('rectype');
            let title = $Db.rty(rectypeID,'rty_Name') + ' [' + rectypeID + ']';
            $rdiv.attr('title', title);
        }
    },
    
    
    //
    //
    //
    setMultiSelection: function(ids){
         this._currentMultiSelection = ids;
    },
    //
    //
    //
    _manageMultiSelection: function(recID, is_add){
        let idx = this._currentMultiSelection==null 
                    ? -1
                    :window.hWin.HEURIST4.util.findArrayIndex(recID, this._currentMultiSelection);
        if(is_add){
              if(idx<0){
                  if(this._currentMultiSelection==null){
                      this._currentMultiSelection = [];
                  }
                  this._currentMultiSelection.push( recID );
              }
        }else if(idx>=0){
            this._currentMultiSelection.splice(idx,1);
        } 
    },
    

    //
    //
    //
    _recordDivNavigateUpDown:function(event){
        
          //this is scroll event  
          let key;
          if(event.originalEvent && event.originalEvent.deltaY){
              key = (event.originalEvent.deltaY>0)?40:38;
          }else{
              key = event.which || event.keyCode;
              if(this.options.view_mode=='horizontal'){  //|| this.options.view_mode=='icons_list'){
                        if (key == 37) { 
                            key = 38;
                        }else if (key == 39) { //right
                            key=40;
                        }
              }
          }
          
          if(key==38 || key==40){ //up and down
              
              let curr_sel = null;
              
              if(this.options.select_mode=='select_multi'){
                   curr_sel = this.div_content.find('.selected');
              }else{
                   curr_sel = this.div_content.find('.selected_last');
              }
              
              if(curr_sel.length > 0)
              { 
                  //find next of previous div
                  if(key==38){
                     curr_sel = $( curr_sel ).prev( '.recordDiv' );
                  }else{
                     curr_sel = $( curr_sel ).next( '.recordDiv' );    
                  }
              }
              
              if(curr_sel.length==0)  //not found - take first
              {
                  curr_sel = this.div_content.find('.recordDiv');
              }
              
              if(curr_sel.length > 0)
              { 
                event.target = curr_sel[0];
                
                this._recordDivOnClick(event);    
                
                if(this.options.view_mode=='horizontal'){ //|| this.options.view_mode=='icons_list'){
                    let spos = this.div_content.scrollLeft();
                    let spos2 = curr_sel.position().left;
                    let offh = spos2 + curr_sel.width() - this.div_content.width() + 10;
                   
                    if(spos2 < 0){
                        this.div_content.scrollLeft(spos+spos2);
                    }else if ( offh > 0 ) {
                        this.div_content.scrollLeft( spos + offh );
                    }
                }else{
                    this.scrollToRecordDiv(curr_sel);
                }
                
                window.hWin.HEURIST4.util.stopEvent(event);
                
                return false;
              }
          }
    },
    
    
    //
    // close expanded recordDivs
    //
    closeExpandedDivs: function(){
        let exp_div = this.div_content.find('.record-expand-info');
        
        let spos = this.div_content.scrollTop();
        if(exp_div.length>0){
            exp_div.remove();
            let rdivs = this.div_content.find('.recordDiv');
            if(this.options.view_mode=='thumbs'){
                rdivs.css({height:'154px', width:'128px'});
            }
            rdivs.removeClass('expanded').show();
                                        
            $.each(rdivs, function(i,rdiv){ 
                $(rdiv).children().not('.recTypeThumb').show();
                $(rdiv).find('.action-button').addClass('ui-button-icon-only');
            });
            
            this.div_content.scrollTop(spos);
        }
                            
        this.div_content.find('.hide-recview').remove();
                            
        this.div_content.find('.recordDiv .action-button-container').css('display','');
    },
    
    //
    //
    //
    _recordDivOnClick: function(event){

        let $target = $(event.target),
        that = this,
        $rdiv;

        if($target.is('a')) return;

        if(!$target.hasClass('recordDiv')){
            $rdiv = $target.parents('.recordDiv');
        }else{
            $rdiv = $target;
        }

        let selected_rec_ID = $rdiv.attr('recid');

        let action =  $target.attr('data-key') || $target.parents().attr('data-key');
        if(!window.hWin.HEURIST4.util.isempty(action)){ //action_btn && action_btn.length()>0){
            if(this.options.renderer){
                //custom handler
                this._trigger( "onaction", null, {action:action, recID:selected_rec_ID, target:$target});
                return;

            }else if (action=='edit'){

                let ordered_recordset = null;
                if(this._currentRecordset){
                    ordered_recordset = this._currentRecordset;
                }else{
                    ordered_recordset = this._query_request;
                }

                window.hWin.HEURIST4.ui.openRecordInPopup(selected_rec_ID, ordered_recordset, true, null);
                //@todo callback to change rectitle

            }else if (action=='edit_ext'){

                const url = window.hWin.HAPI4.baseURL + "?fmt=edit&db="+window.hWin.HAPI4.database+"&recID="+selected_rec_ID;
                window.open(url, "_new");
            }
            
            // remove this remark to prevent selection on action button click
           
        }

        let ispwdreminder = $target.hasClass('rec_p_reminder'); //this is password reminder click
        if (ispwdreminder){
            let pwd = $rdiv.attr('data-reminder');
            let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HEURIST4.util.htmlEscape(pwd),
                    null, window.hWin.HR('Password reminder'), 
                {my: "left top", at: "left bottom", of: $target, modal:false}
            );
            $dlg.addClass('password-reminder-popup'); //class all these popups on refresh
            return;
        }else{
            
            function __selectOnClick(map_layer_action){
                        $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                        {selection:[selected_rec_ID], 
                            map_layer_action: map_layer_action,  //dataset_visibility: true, 
                            source:that.element.attr('id'), 
                            search_realm:that.options.search_realm
                            //,search_page:this.options.search_page
                        } );
            }
            
            
            //this.options.recordview_onselect=='popup'
             //(this.options.recordview_onselect!==false && this.options.view_mode!='list')
            let isview = (this.options.recordview_onselect=='popup' ||
                            $target.parents('.rec_view_link').length>0); //this is VIEWER click
                
            if(isview){ //popup record view
            
                this._clearTimeouts();
                this._showRecordViewPopup( selected_rec_ID );

                if(!(this.options.recordview_onselect=='popup')){
                   
                    return;
                }
                
            }else
            if($target.hasClass('rec_expand_on_map') || $target.parents('.rec_expand_on_map').length>0){
                if(this._currentRecordset){
                    
                    let btn = $target.hasClass('rec_expand_on_map')?$target:$target.parents('.rec_expand_on_map');
                    if(btn.attr('data-loaded')=='loading') return; 
                    
                    __selectOnClick('trigger_visibility');
                }            
                return;            
            }else
            if($target.hasClass('rec_download') || $target.parents('.rec_download').length>0){
                
                if(this._currentRecordset){
                    __selectOnClick('download');
                }
                return;            
                
            }else
            if($target.hasClass('rec_view_link_ext') || $target.parents('.rec_view_link_ext').length>0){
                //View external link (opens in new window)
                //OR Embed map document
                if(this._currentRecordset){
                    let record = this._currentRecordset.getById(selected_rec_ID);
                    const rectypeID = this._currentRecordset.fld(record, 'rec_RecTypeID' );
                    //show embed dialog
                    if(rectypeID==window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT']){
                        window.hWin.HEURIST4.ui.showPublishDialog({mode:'mapspace',mapdocument_id: selected_rec_ID});
                    }else{
                        const url = this._currentRecordset.fld(record, 'rec_URL' );
                        if(url) window.open(url, "_new");
                    }
                }
                return;
            }else 
            if($target.hasClass('rec_remove') || $target.parents('.rec_remove').length>0){ //remove from record set - not from db
                if(this._currentRecordset){
                    this._currentRecordset.removeRecord(selected_rec_ID);
                    this.updateResultSet(this._currentRecordset);
                }
                return;
            }else
            if($target.hasClass('rec_delete') || $target.parents('.rec_delete').length>0){
                if(this._currentRecordset){
                    let record = this._currentRecordset.getById(selected_rec_ID);
                    const rectypeID = this._currentRecordset.fld(record, 'rec_RecTypeID' );
                    //show delete dialog
                    if(rectypeID==window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT']){

                        window.hWin.HAPI4.currentRecordsetSelection = [selected_rec_ID];
                        if(window.hWin.HEURIST4.util.isempty(window.hWin.HAPI4.currentRecordsetSelection)) return;
                        
                        window.hWin.HEURIST4.ui.showRecordActionDialog('recordDelete', {
                            hide_scope: true,
                            title: window.hWin.HR('resultList_action_delete_hint'),
                            onClose:
                           function( context ){
                               if(context){
                                   // refresh search
                                   window.hWin.HAPI4.RecordSearch.doSearch( that, that._query_request );
                               }
                           }
                        });
                        
                        
/* old version                        
                        window.hWin.HEURIST4.ui.showRecordActionDialog('recordDelete', {
                            map_document_id: selected_rec_ID,
                            title: 'Delete map document and associated map layers and data sources',
                            onClose:
                           function( context ){
                               if(context){
                                   // refresh search
                                   window.hWin.HAPI4.RecordSearch.doSearch( this, this._query_request );
                               }
                           }
                        });
*/                        
                    }
                }
                return;
                
            }else 
            if($target.hasClass('rec_collect') || $target.parents('.rec_collect').length>0){

                if($rdiv.hasClass('collected')){
                    window.hWin.HEURIST4.collection.collectionDel(selected_rec_ID);        
                }else{
                    window.hWin.HEURIST4.collection.collectionAdd(selected_rec_ID);
                }
                return;
            }
        }

        //select/deselect on click
        if(this.options.select_mode=='none'){
          //do nothing
        }else 
        if(this.options.select_mode=='select_multi'){
            
            if($rdiv.hasClass('selected')){
                $rdiv.removeClass('selected');
                $rdiv.find('.recordSelector>input').prop('checked', '');
               
                this._manageMultiSelection(selected_rec_ID, false);
            }else{
                $rdiv.addClass('selected')
                $rdiv.find('.recordSelector>input').prop('checked', 'checked');
                this._manageMultiSelection(selected_rec_ID, true);
                /*if(this._currentSelection==null){
                    this._currentSelection = [selected_rec_ID];
                }else{
                    this._currentSelection.addRecord(selected_rec_ID, this._currentRecordset.getById(selected_rec_ID));
                }*/
            }
            this._updateInfo();

        }else{

            this.div_content.find('.selected_last').removeClass('selected_last');

            if(this.options.multiselect && (event.ctrlKey || event.metaKey)){

                if($rdiv.hasClass('selected')){
                    $rdiv.removeClass('selected');
                    this._lastSelectedIndex = null;
                   
                }else{
                    $rdiv.addClass('selected');
                    $rdiv.addClass('selected_last');
                    this._lastSelectedIndex = selected_rec_ID;
                   
                }
               

            }else if(this.options.multiselect && event.shiftKey){

                if(this._lastSelectedIndex!=null){
                    let nowSelectedIndex = selected_rec_ID;

                    this.div_content.find('.selected').removeClass('selected');

                    let isstarted = false;

                    this.div_content.find('.recordDiv').each(function(ids, rdiv){
                        let rec_id = $(rdiv).attr('recid');

                        if(rec_id == that._lastSelectedIndex || rec_id==nowSelectedIndex){
                            if(isstarted){ //stop selection and exit
                                $(rdiv).addClass('selected');
                                return false;
                            }
                            isstarted = true;
                        }
                        if(isstarted) {
                            $(rdiv).addClass('selected');
                        }
                    });

                    $rdiv.addClass('selected_last');
                    that._lastSelectedIndex = selected_rec_ID;

                }else{
                    that._lastSelectedIndex = selected_rec_ID;
                }


            }else{
                //remove selection from all recordDiv
                this.div_content.find('.selected').removeClass('selected');
                $rdiv.addClass('selected');
                $rdiv.addClass('selected_last');
                this._lastSelectedIndex = selected_rec_ID;

            }

        }
        
        //window.hWin.HEURIST4.util.isFunction(this.options.renderer) && 
        if((this.options.view_mode!='horizontal' && this.options.view_mode!='vertical')   // && this.options.view_mode!='icons_list'
            && this.options.recordview_onselect=='inline'
            && this.options.expandDetailsOnClick){ // && this.options.view_mode=='list'

            this.expandDetailsInline( selected_rec_ID );
            
            //adjust selection in focus
            this.scrollToRecordDiv( selected_rec_ID, true );
        }
        

        
        
        this.triggerSelection();
    },
    //_recordDivOnClick
    
    //
    // expand ALL recordDivs
    //
    expandDetailsInlineALL: function(can_proceed){
        
        const that = this;
        
        if(can_proceed!==true && this.allowedPageSizeForRecContent(function(){
            that.expandDetailsInlineALL(true);
        })) return;
    
        let $allrecs = this.div_content.find('.recordDiv');
        $allrecs.each(function(idx, item){
            that.expandDetailsInline($(item).attr('recid'));
        });
        
    },
    
    //
    //
    //
    expandDetailsInline: function(recID){
        
        let $rdiv = this.div_content.find('div[recid='+recID+']');
        if($rdiv.length==0) return; //no such recod in current page
        
        
        let that = this;
        
            let exp_div = this.div_content.find('.record-expand-info[data-recid='+recID+']');
            let is_already_opened = (exp_div.length>0);
            
            if(is_already_opened){
                if(!this._expandAllDivs) this.closeExpandedDivs();
            }else{
                //close other expanded recordDivs
                if(!this._expandAllDivs) this.closeExpandedDivs();
                
                let rendererTemplate = null;
                //expand selected recordDiv and draw record details inline
                if(window.hWin.HEURIST4.util.isFunction(this.options.rendererExpandDetails)){
                    rendererTemplate = this.options.rendererExpandDetails.call(this, this._currentRecordset, recID);
                }else {
                    rendererTemplate = this.options.rendererExpandDetails?this.options.rendererExpandDetails:'default'; //use renderRecordData.php
                }
                if(!window.hWin.HEURIST4.util.isempty(rendererTemplate))
                {
                    //add new record-expand-info 
                    let ele = $('<div>')
                        .attr('data-recid', recID)
                        .css({'overflow':'hidden','padding-top':'5px','height':'25px'}) //'max-height':'600px','width':'100%',
                        .addClass('record-expand-info');
                    
                    if(this.options.view_mode=='list'){
                        ele.appendTo($rdiv);
                    }else{
                        
                        if(this.options.recordDivClass){
                            ele.addClass(this.options.recordDivClass);
                        }else{
                            ele.css({'box-shadow':'0px 3px 8px #666','margin':'6px',
                                 //'width':'97%',
                                  padding:'5px',
                                 'border-radius': '3px 3px 3px 3px',
                                 'border':'2px solid #62A7F8'});
                        }
                        
                        
                                 
                        if(this.options.view_mode=='icons' && this._expandAllDivs){

                            $rdiv.addClass('expanded');
                            $rdiv.children().not('.recTypeThumb').hide();
                            //show on hover as usual 
                            let action_buttons = $rdiv.find('.action-button-container');
                            if(action_buttons.length>0 && window.hWin.HAPI4.has_access()){
                                action_buttons.show();
                                $rdiv.find('.action-button').removeClass('ui-button-icon-only');
                                ele.css({'margin':'0px 0px 0px 80px'});
                            }else{
                                ele.css({'margin':'0px'});
                            }
                            ele.appendTo($rdiv);
                        }else if((this.options.view_mode == 'thumbs' || this.options.view_mode == 'thumbs3') && !this._expandAllDivs){ // append to 'end of row'

                            let upper_limit = $rdiv.position().top + $rdiv.height();
                            let $next_record = $rdiv;
    
                            let $divs = this.div_content.find('div[recid]');
                            $.each($divs, (idx, ele) => {
    
                                let $ele = $(ele);
    
                                if($ele.position().top <= upper_limit){
                                    return;
                                }
    
                                $next_record = $ele;
                                return false;
                            });
    
                            if($next_record.attr('recid') != recID){
    
                                // add up arrow to close record details
                                let $close_arrow = $('<span>')
                                    .addClass('ui-icon ui-icon-caret-2-n hide-recview')
                                    .css({
                                        cursor: 'pointer',
                                        float: 'left',
                                        fontSize: 'large',
                                        margin: '10px'
                                    })
                                    .attr('title', 'Close record details');
    
                                this._on($close_arrow, {
                                    'click': () => {
                                        this.closeExpandedDivs();
                                    }
                                });
    
                                $close_arrow.insertBefore($next_record);
                                ele.insertBefore($next_record); // insert after 'row'
                            }else{
                                ele.insertAfter($rdiv); // just insert after record    
                            }
                        }else{
                            ele.insertAfter($rdiv);
                        }
                                 
                    }
                        
                    
                    let infoURL;
                    let isSmarty = false;
                    
                    if( typeof rendererTemplate === 'string' 
                            && rendererTemplate.substr(-4)=='.tpl' ){

                        infoURL = window.hWin.HAPI4.baseURL + '?snippet=1&q=ids:'
                        + recID 
                        + '&db='+window.hWin.HAPI4.database+'&template='
                        + encodeURIComponent(rendererTemplate);
                                
                        isSmarty = true;
                    }else{
                        //content is record view 
                        infoURL = window.hWin.HAPI4.baseURL + 'viewers/record/renderRecordData.php?recID='
                        +recID
                        +'&db='+window.hWin.HAPI4.database;

                        if(that._is_publication && that.options.recviewer_images != 0){
                            infoURL += '&hideImages=' + that.options.recviewer_images;
                        }
                    }
                    
                    if(that.options.language && that.options.language!='def'){
                        infoURL = infoURL + '&lang='+that.options.language;
                    }
                    if(this.options.fontsize>0){
                        infoURL = infoURL + '&fontsize=' + this.options.fontsize;
                    }
                    
                    
                    //content is smarty report
                    if( this.options.rendererExpandInFrame ||  !isSmarty)
                    {
                        
                        ele.addClass('loading');
                        
                        $('<iframe>').attr('data-recid',recID)
                            .appendTo(ele)
                            .css('opacity',0)
                            .attr('src', infoURL).on('load',function()
                            { 
                                let _recID = $(this).attr('data-recid');
                                let ele2 = that.div_content.find('.record-expand-info[data-recid='+_recID+']');
                                let h = 300;

                            try{
                                
                                let cw = this.contentWindow.document;
                                
                                let cw2  = this.contentWindow.document.documentElement;//.scrollHeight

                                function __adjustHeight(){
                                   
                                    if(cw2){
                                        let bh = cw.body?cw.body.scrollHeight:0;
                                        let h = cw2.scrollHeight;                               

                                        if(bh>0 && h>0){
                                            h = Math.max(bh,h);
                                        }else{
                                            h = 300 //default value
                                        }
                                        ele2.height(h);//+(h*0.05)    
                                        
                                    }
                                }
                                
                               __adjustHeight();

                                setTimeout(__adjustHeight, 2000);
                                setTimeout(__adjustHeight, 4000);
                                setTimeout(function(){
                                    ele2.removeClass('loading');
                                    ele2.find('iframe').css('opacity',1);
                                }, 2100);
                               
                                
                            }catch(e){
                                ele2.removeClass('loading').height(400);    
                                console.error(e);
                            }

                        });
                        

                    }else{

                        ele.addClass('loading').css({'overflow-y':'auto'}).load(infoURL, function(){ 

                            let ele2 = $(this);
                            let h = ele2[0].scrollHeight+10;
                            ele2.removeClass('loading').height('auto');    
                            
                            ele2.find('img').each(function(i,img){window.hWin.HEURIST4.util.restoreRelativeURL(img);});
                        });   
                    }  
                    
                }
            }        
        
    },
    
    //
    // keeps full set for multiselection 
    //
    fullResultSet: function(recset){
        this._fullRecordset = recset;
    },
    
    //
    // trigger global event
    //
    triggerSelection: function(){

        let selected_ids;

        if(this.options.eventbased){
            selected_ids = this.getSelected( true );
            $(this.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                {selection:selected_ids, source:this.element.attr('id'), 
                    search_realm:this.options.search_realm,
                    search_page: this.options.search_page} );
        }else{
            let selected_recs = this.getSelected( false );
            this._trigger( "onselect", null, selected_recs );
        }
        
        if(window.hWin.HEURIST4.util.isFunction(this.options.onSelect)){
            if(!this.options.eventbased){
                selected_ids = this.getSelected( true );
            }
            this.options.onSelect.call(this, selected_ids);    
        }
        
    },

    /**
    * return HRecordSet of selected records
    */
    getSelected: function( idsonly ){


        if(this.options.select_mode == 'select_multi'){

            if(this._currentMultiSelection==null){
                return null;
            }else if(idsonly){
                return this._currentMultiSelection;
            }else if(this._fullRecordset){
                return this._fullRecordset.getSubSetByIds(this._currentMultiSelection);
            }else{
                return this._currentRecordset.getSubSetByIds(this._currentMultiSelection);
            }


        }else{

            let selected = []
            if(this._currentRecordset){
                let that = this;
                this.div_content.find('.selected').each(function(ids, rdiv){
                    let rec_ID = $(rdiv).attr('recid');
                    if(that._lastSelectedIndex!=rec_ID){
                        selected.push(rec_ID);
                    }
                });
                if(this._lastSelectedIndex!=null){
                    selected.push(""+this._lastSelectedIndex);
                }
            }

            if(idsonly){
                return selected;
            }else if(this._currentRecordset){
                return this._currentRecordset.getSubSetByIds(selected);
            }else{
                return null;
            }

        }

    },

    /**
    * selection - HRecordSet or array of record Ids
    *
    * @param record_ids
    */
    setSelected: function(selection){

        //clear selection
        this.div_content.find('.selected').removeClass('selected');
        this.div_content.find('.selected_last').removeClass('selected_last');
        this._lastSelectedIndex = null;    

        if (selection == "all") {
            this.div_content.find('.recordDiv').addClass('selected');

            window.hWin.HAPI4.currentRecordsetSelection = this.getSelected(true);
        }else{

            let recIDs_list = window.hWin.HAPI4.getSelection(selection, true); //need to rewrite since it works with global currentRecordset
            if( window.hWin.HEURIST4.util.isArrayNotEmpty(recIDs_list) ){

                this.div_content.find('.recordDiv').each(function(ids, rdiv){
                    let rec_id = $(rdiv).attr('recid');
                    let idx = window.hWin.HEURIST4.util.findArrayIndex(rec_id, recIDs_list);
                    if(idx>=0){ 
                        $(rdiv).addClass('selected');
                    }
                });
                if(recIDs_list.length==1){
                    this.scrollToRecordDiv(recIDs_list[0]);
                }

            }
        }

    },
    
    scrollToRecordDiv: function(selected, to_top_of_viewport){
        
        let rdiv = null;
        if( window.hWin.HEURIST4.util.isPositiveInt(selected) ){
            rdiv = this.div_content.find('.recordDiv[recid="'+selected+'"]');    
        }else if($(selected).length>0 && $(selected).is('div.recordDiv')){
            rdiv = $(selected)
        }
        
        if(rdiv && rdiv.length==1){
            
            if(this.options.view_mode=='tabs'){
                
                let active_tab_index = this.div_content
                    .find('ul[role="tablist"]')
                    .find('a[href="#rec_'+selected+'"]').parent('li').index();
                if(active_tab_index>=0)
                    this.div_content.tabs({active:active_tab_index});
                
            }else{
                let spos = this.div_content.scrollTop(); //current pos
                let spos2 = rdiv.position().top; //relative position of record div
                let offh = spos2 + rdiv.height() - this.div_content.height() + 10;
               
                if(spos2 < 0 || to_top_of_viewport===true){ //above viewport
                    this.div_content.scrollTop(spos+spos2);
                }else if ( offh > 0 )
                {
                    let newpos = spos + offh;
                    if(newpos<0) newpos = 0;
                    
                    this.div_content.scrollTop( newpos );
                }
            }
        }
    },
    
    //
    // assign collection
    //
    setCollected: function(collection){
        
        this.div_content.find('.collected').removeClass('collected');
        let hasCollection = window.hWin.HEURIST4.util.isArrayNotEmpty(collection);

        if(this.options.support_collection){

            if(hasCollection){
                //highlight collected in current record set
                this.div_content.find('.recordDiv').each(function(ids, rdiv){
                    let rec_id = $(rdiv).attr('recid');
                    let idx = window.hWin.HEURIST4.util.findArrayIndex(rec_id, collection);
                    if(idx>=0){ 
                        $(rdiv).addClass('collected');
                    }
                });

            }else if(collection==null){
                window.hWin.HEURIST4.collection.collectionUpdate();
            }

            // update local cache
            this._collection = hasCollection ? collection : [];
            window.hWin.HAPI4.currentRecordsetCollected = hasCollection ? collection : [];

            // update 'cart' count
            this._updateInfo();
        }

    },


    loadanimation: function(show){
        if(show){
            this.div_loading.show();
           
            this.element.css('cursor', 'progress');
        }else{
            this.div_loading.hide();
            this.element.css('cursor', 'auto');
           
        }
    },

    // 
    // in case nothing found for bookmarks, we offer user to search entire db (w=a)
    //
    _doSearch4: function(){

        if ( this._query_request ) {

            this._query_request.w = 'a';
            this._query_request.source = this.element.attr('id');

            window.hWin.HAPI4.RecordSearch.doSearch( this, this._query_request );
        }

        return false;
    }

    //
    //
    //
    , _renderProgress: function(){

    },

    //
    // alternative info function that is invoked ONLY ONCE on search finish
    // see EN
    //
    _renderSearchInfoMsg: function(){

    },

    //
    // number of records in result set (query total count) and number of selected records
    // this function is invoked many times (depends on width, page, selection etc)
    //
    _updateInfo: function(){

        let total_inquery = (this._currentRecordset!=null) ? this._currentRecordset.count_total() : 0;
        total_inquery = (this._currentSubset) ? this._currentSubset.count_total() : total_inquery;

        //IJ wants just n=
        let sinfo = this.options.show_counter ? 'n = '+total_inquery : '';

        this.span_pagination.attr('title', sinfo);

        let w = this.element.width();

        let handleCollections = this._is_publication && this.options.support_collection;

        if(handleCollections){

            let hasCollection = this._collection && this._collection.length ? this._collection.length : 0;
            let is_subset = this._isCollectionUsed;

            sinfo = `<a href="#" id="collectSelected" style="padding-right:5px;" title="Add selected records to the collection">Add selected</a> -&gt; Collected: ${hasCollection} `
                  + `<a href="#" id="searchCollected" style="padding-left:5px;" title="Show ${is_subset?'the original search result':'collected records'}">Show ${is_subset?'results':''}`
                  + `<span class="ui-icon ui-icon-arrowthick-1-s" style="font-size:18px;top:3px;"></span></a>`
                  + `<a href="#" id="clearCollected" style="padding:0px 5px;" title="Clear record collection">Clear</a> | ${sinfo}`;
        }

        if(this.options.select_mode=='select_multi' && this._currentMultiSelection!=null && this._currentMultiSelection.length>0){
            sinfo = sinfo + " | Selected: "+this._currentMultiSelection.length;
            if(w>600){
                sinfo = sinfo+' <a href="#">'+window.hWin.HR('Clear')+'</a>';
            }                                       
        }

        this.span_info.prop('title','');
        this.span_info.html(sinfo);

        if(this.options.select_mode=='select_multi'){
            let that = this;
            this.span_info.find('a').on('click', function(){
                that._currentMultiSelection = null;
                that._updateInfo();

                that.div_content.find('.recordDiv').removeClass('selected');
                that.div_content.find('.recordDiv .recordSelector>input').prop('checked', '');

                that.triggerSelection();

                return false; });
        }

        if(handleCollections){ // add event handles for in widget collection controls

            // Show 'shopping cart' of collected records, or reset to complete record list
            this._on(this.span_info.find('a#searchCollected'), {
                click: function(){
                    this.displayCollection(!this._isCollectionUsed);
                }
            });

            // Add selected records to collection
            this._on(this.span_info.find('a#collectSelected'), {
                click: function(){
                    /*
                    let recIDs = this.getSelected(true);
                    if(recIDs.length == 0){ return; }
                    window.hWin.HEURIST4.collection.collectionUpdate(recIDs.join(','));
                    */
                    this.div_content.find('.selected:not(.collected) .rec_collect').trigger('click');
                }
            });

            // Remove all records from collection
            this._on(this.span_info.find('a#clearCollected'), {
                click: function(){
                    window.hWin.HEURIST4.collection.collectionClear();
                }
            });
        }

        let $content_view = this.view_mode_selector.find('button[value="record_content"]');
        if($content_view.length > 0 && total_inquery > 100){
            total_inquery <= 100
                ? $content_view.attr('title', window.hWin.HR('Record contents'))
                               .find('.ui-icon').css('color', '')
                : $content_view.attr('title', 'This function is disabled for over 100 records as it is really only usable with a limited record count')
                               .find('.ui-icon').css('color', 'grey');
        }
    },

    //
    // redraw list of pages
    //
    _renderPagesNavigator: function(){

        this.count_total = (this._currentRecordset!=null)?this._currentRecordset.length():0;
        // length() - downloaded records, count_total() - number of records in query
        let total_inquery = (this._currentRecordset!=null)?this._currentRecordset.count_total():0;

        this.max_page = 0;
       

        if(this.count_total>0){

            this.max_page = Math.ceil(this.count_total / this.options.pagesize);
            if(this.current_page>this.max_page-1){
                this.current_page = 0;
            }
        }

        let pageCount = this.max_page;
        let currentPage = this.current_page;
        let start = 0;
        let finish = 0;

       
        this._removeNavButtons();

        let span_pages = $(this.span_pagination);
        span_pages.empty();

        this._updateInfo();

        if (pageCount > 1) {
            
            if(this.options.navigator=='none'){
                this._renderPage(0);
            }else{
                
                // KJ's patented heuristics for awesome useful page numbers
                if (pageCount > 9) {
                    if (currentPage < 5) { start = 1; finish = 8; }
                    else if (currentPage < pageCount-4) { start = currentPage - 2; finish = currentPage + 4; }
                        else { start = pageCount - 7; finish = pageCount; }
                } else {
                    start = 1; finish = pageCount;
                }


                /*if (currentPage == 0) {
                this.btn_goto_prev.hide();
                }else{
                this.btn_goto_prev.show();
                }
                if (currentPage == pageCount-1) {
                this.btn_goto_next.hide();
                }else{
                this.btn_goto_next.show();
                }*/

                let that = this;

                let ismenu = that.options.navigator!='buttons' && (that.options.navigator=='menu' || (that.element.width()<450 || pageCount > 5));

                let smenu = '';

                if (start != 1) {    //force first page
                    if(ismenu){
                        smenu = smenu + '<li id="page0"><a href="#">1</a></li>'
                        if(start!=2){                                                                              
                            smenu = smenu + '<li>...</li>';
                        }
                    }else{
                        $( "<button>", { text: "1", id:'page0'}).css({'font-size':'0.7em'}).button()
                        .appendTo( span_pages ).on("click", function(){ 
                            that._renderPage(0); 
                        } );
                        if(start!=2){
                            $( "<span>" ).html("..").appendTo( span_pages );
                        }
                    }
                }
                for (let i=start; i <= finish; ++i) {
                    if(ismenu){
                        smenu = smenu + '<li id="page'+(i-1)+'"><a href="#">'+i+'</a></li>'
                    }else{

                        let $btn = $( "<button>", { text:''+i, id: 'page'+(i-1) }).css({'font-size':'0.7em'}).button()
                        .appendTo( span_pages )
                        .on('click', function(event){
                            let page = Number(this.id.substring(4));
                            that._renderPage(page);
                        } );
                    }
                }
                if (finish != pageCount) { //force last page
                    if(ismenu){
                        if(finish!= pageCount-1){
                            smenu = smenu + '<li>...</li>';
                        }
                        smenu = smenu + '<li id="page'+(pageCount-1)+'"><a href="#">'+pageCount+'</a></li>';
                    }else{
                        if(finish!= pageCount-1){
                            $( "<span>" ).html("..").appendTo( span_pages );
                        }
                        $( "<button>", { text: ''+pageCount, id:'page'+finish }).css({'font-size':'0.7em'}).button()
                        .appendTo( span_pages ).on("click", function(){ that._renderPage(pageCount-1); } );
                    }
                }

                if(ismenu){
                    //show as menu
                    this.btn_page_prev = $( "<button>", {text:currentPage} )
                    .appendTo( span_pages )
                    .css({'font-size':'0.7em', 'width':'1.6em'})
                    .button({icon:"ui-icon-triangle-1-w", showLabel:false});

                    this.btn_page_menu = $( "<button>", {
                        text: (currentPage+1)
                    })
                    .appendTo( span_pages )
                    .css({'font-size':'0.7em'})
                    .button({iconPosition:'end', icon:'ui-icon-triangle-1-s'});

                    this.btn_page_menu.find('.ui-icon-triangle-1-s').css({'font-size': '1.3em', right: 0});

                    this.btn_page_next = $( "<button>", {text:currentPage} )
                    .appendTo( span_pages )
                    .css({'font-size':'0.7em', 'width':'1.6em'})
                    .button({icon:"ui-icon-triangle-1-e", showLabel:false});


                    this.menu_pages = $('<ul>'+smenu+'</ul>')   //<a href="#">
                    .css({position:'absolute', zIndex:9999999, 'font-size':'0.7em'})
                    .appendTo( this.element )  //this.document.find('body')
                    .menu({
                        select: function( event, ui ) {
                            let page =  Number(ui.item.attr('id').substring(4)); 
                            that._renderPage(page);
                    }})
                    .hide();

                    this._on( this.btn_page_prev, {
                        click: function() {  that._renderPage(that.current_page-1)  }});
                    this._on( this.btn_page_next, {
                        click: function() {  that._renderPage(that.current_page+1)  }});

                    this._on( this.btn_page_menu, {
                        click: function() {
                            //show menu with list of pages
                            $('.ui-menu').not('.horizontalmenu').not('.heurist-selectmenu').hide(); //hide other
                            let menu = $( this.menu_pages )
                            //.css('min-width', '80px')
                            .show()
                            .position({my: "right top", at: "right bottom", of: this.btn_page_menu });
                            $( document ).one( "click", function() { menu.hide(); });
                            return false;
                        }
                    });

                }
                
                
                if(this.options.header_class){
                    this.span_pagination.find('button').addClass(this.options.header_class).css({'border':'none'});
                }else if(this.options.is_h6style){
                    this.span_pagination.find('button').removeClass('ui-heurist-btn-header1')
                        .css({'background':'none'});
                }
                
                if(!ismenu){
                    if(this.options.is_h6style){
                        span_pages.find('#page'+currentPage).addClass('ui-heurist-btn-header1')
                    }else{
                        span_pages.find('#page'+currentPage).css({'border':'1px solid white'});
                    }
                }
                    

            }
        }

        this._showHideOnWidth();
    }

    //
    //
    //    
    , refreshPage: function( callback ){
        
        let keep_selection = this.getSelected(true);
        this._renderPage(this.current_page);
        if(keep_selection && keep_selection.length>0){
            /* implement later */
        }
    }
    
    , allowedPageSizeForRecContent: function( callback ){

        if(!this._currentRecordset) return true;

        if(this.options.expandDetailsWithoutWarning) return false;
        
        let n = Math.min(this._currentRecordset.length(),this.options.pagesize);
        
        if(n>10){
                let that = this;
                
                let s = '';
                if(window.hWin.HAPI4.has_access()){
                    s = '<p style="color:green">'+window.hWin.HR('resultList_view_content_hint1')+'</p>'; // (edit here to change)
                }
                
                let $__dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                '<p>' + window.hWin.HR('resultList_view_content_hint2')+' '+n
                + window.hWin.HR('resultList_view_content_hint3')+'</p>'
                +s,
                {'Proceed as is' :function(){ 
                    callback.call();
                    $__dlg.dialog( "close" );
                },
                'Single Line Display Mode (this time)':function(){
                    that.applyViewMode('list');
                    $__dlg.dialog( "close" );
                },
                'Switch to Single Display Mode':function(){
                    that.applyViewMode('list');
                    window.hWin.HAPI4.save_pref('rec_list_viewmode_'+that.options.entityName, 'list');
                    $__dlg.dialog( "close" );
                }
                }, {title:window.hWin.HR('Warning')});
				
            return true;          
        }else{
            return false;
        }
        
    }

    //
    // render the given page (called from navigator and on search finish)
    //
    , _renderPage: function(pageno, recordset, is_retained_selection){

        let len, pagesize;
        let that = this;
        let idx;

        if(is_retained_selection){ //draw retained selection

            recordset = this._currentRecordset.getSubSetByIds(this._currentMultiSelection);
            this._removeNavButtons();
            idx = 0;
            len = recordset.length();
            pagesize = len;

        }else{

            if(this.cb_selected_only) this.cb_selected_only.prop('checked', '');

            if(!recordset){
                recordset = this._currentSubset ? this._currentSubset : this._currentRecordset;
            }

            if(!recordset) return;

            if(pageno>=this.max_page){
                pageno= this.max_page - 1;
            }
            if(pageno<0){
                pageno = 0;
            }

            this.current_page = pageno<0?0:pageno;

            this._renderPagesNavigator(); //redraw paginator

            idx = pageno*this.options.pagesize;
            len = Math.min(recordset.length(), idx+this.options.pagesize)
            pagesize = this.options.pagesize;
            
        }
        
        
        
        let recs = recordset.getRecords();
        let rec_order = recordset.getOrder();
        let rec_toload = [];
        let rec_onpage = [];
        
        //for active tab
        let tab_active_index = 0;
        let curr_idx = 0;
        let selected_recid = this.getSelected(true);
        selected_recid = (selected_recid && selected_recid.length>0)?selected_recid[0]:0;

        this.clearAllRecordDivs(null);
        
        let html = '', html_groups = {}, tab_header = '', stitle;
        for(; (idx<len && this._count_of_divs<pagesize); idx++) {
            const recID = rec_order[idx];
            if(recID){
                if(this.options.view_mode=='tabs'){
                    
                    if(recs[recID]){
                        if(selected_recid==recID) tab_active_index = curr_idx;
                        
                        stitle = (recs[recID]?window.hWin.HEURIST4.util.htmlEscape(
                                    window.hWin.HEURIST4.util.stripTags(recordset.fld(recs[recID], 'rec_Title')))
                                    :recID);
                        
                        tab_header += ('<li><a href="#rec_'+recID+'" title="'+stitle+'">'
                            +stitle+'</a></li>');    

                        html  += '<div class="recordDiv" id="rec_'+recID+'"' 
                            +' recid="'+recID+'"></div>';
                    }else{
                        if(this.options.supress_load_fullrecord===false){
                            rec_toload.push(recID);  
                        }else{
                            break;
                        } 
                    }
                    curr_idx++;    
                }else{
            
                    if(recs[recID]){
                        let rec_div = this._renderRecord_html(recordset, recs[recID]);
                        if(this.options.groupByField){
                            let grp_val = recordset.fld(recs[recID], this.options.groupByField);
                            if(!html_groups[grp_val]) html_groups[grp_val] = '';
                            html_groups[grp_val] += rec_div;
                        }else{
                            html  += rec_div;
                        }
                        rec_onpage.push(recID);
                        
                    }else{
                        //record is not loaded yet
                        html  += this._renderRecord_html_stub( recID );    
                        if(this.options.supress_load_fullrecord===false) rec_toload.push(recID);
                    }
                }
                this._count_of_divs++;
                /*this._on( recdiv, {
                click: this._recordDivOnClick
                });*/
            }
        }

        //activate tab mode        
        if(this.options.view_mode=='tabs'){
            if(rec_toload.length>0){
                this._loadFullRecordData( rec_toload );    
            }else if(html){
                
                
                function __loadTabContent( event, ui ) {
                    
                        let recID;
                        if(ui && ui.newPanel){
                            recID = ui.newPanel.attr('recid');
                        }else{
                            recID = that.div_content.find('div.recordDiv:first').attr('recid');
                        }
                    
                        //load content for record 
                        if(recID>0){
                            
                            that.expandDetailsInline( recID );
                            //trigger selection
                            that.div_content.find('div.recordDiv').removeClass('selected');
                            that.div_content.find('div.recordDiv[recid="'+recID+'"]').addClass('selected');
                            that._currentMultiSelection = [recID];

                            that.triggerSelection();
                            //overview that.div_content.find('div.recordDiv[recid="0"]').hide(); 
                        }else if(recID==0){
                            that.closeExpandedDivs();
                        }
                }                
                
                this.div_content[0].innerHTML += '<ul>'+tab_header+'</ul>';
                this.div_content[0].innerHTML += html;
                this.div_content.tabs({activate: __loadTabContent, active:-1});
                if( this.div_content.find('div.recordDiv').length==1 ){
                    __loadTabContent();
                }else{
                    this.div_content.tabs({active:tab_active_index});
                }

                tab_header = this.div_content.find('ul[role="tablist"]');
                tab_header.css('height','auto'); //33px
                let $tabs = tab_header.find('a');
                let max_char = 20;
                $tabs.css({
                    'max-width': max_char+'ex',
                    'width': 'auto',
                    'margin-right': '20px',
                    'font-size': '1em',
                    'line-height': '1.5em',
                    'padding': '0.5em 1em !important'
                }).addClass('truncate');

                this.div_content.tabs('paging',{
                    nextButton: '<span style="font-size:2em;font-weight:900;line-height:5px;vertical-align: middle">&#187;</span>', // Text displayed for next button.
                    prevButton: '<span style="font-size:2em;font-weight:900;line-height:5px;vertical-align: middle">&#171;</span>' // Text displayed for previous button.
                });
                
            }
            return;
        }
                
        if(this.options.groupByField){
           
            let hasRender = window.hWin.HEURIST4.util.isFunction(this.options.rendererGroupHeader);

            //
            if(this.options.groupOnlyOneVisible && 
                $.isEmptyObject(this._grp_keep_status))
            { //initially expand first only
                let isfirst = true;
                for (let grp_val in html_groups){
                    if(!window.hWin.HEURIST4.util.isempty(html_groups[grp_val])){
                        this._grp_keep_status[grp_val] = isfirst?1:0;
                        isfirst = false;
                    }
                }   
            }

            function __drawGroupHeader(i, grp_val){
                
                    if(!html_groups[grp_val]){
                        html_groups[grp_val] = 'empty';  
                        that._grp_keep_status[grp_val] = 0;
                    }
                    
                    let is_expanded = ($.isEmptyObject(that._grp_keep_status) || that._grp_keep_status[grp_val]==1);
                    
                    let gheader = (hasRender)
                        ?that.options.rendererGroupHeader.call(that, grp_val, is_expanded)
                        :'<div style="width:100%">'+grp_val+'</div>';  
                        
                    html += (gheader+'<div data-grp-content="'+grp_val
                        +'" style="display:'+(is_expanded?'block':'none')
                        +'">'+html_groups[grp_val]+'</div>');
            }
            
            if(this.options.groupByRecordset){
                $.each(this.options.groupByRecordset.getOrder(),__drawGroupHeader);
            }else{
                //
                for (let grp_val in html_groups){
                    __drawGroupHeader(0, grp_val);
                }
            }
        }
        
        //special div for horizontal
        if(this.options.view_mode == 'horizontal' || this.options.view_mode == 'vertical'){  //|| this.options.view_mode == 'icons_list'){
            html = '<div>'+html+'</div>';
        }
        

        this.div_content[0].innerHTML += html;

        if(this.options.groupByField){ //init show/hide btn for groups
            if(this.options.groupByCss!=null){
                this.div_content.find('div[data-grp-content]').css( this.options.groupByCss );
            }
        
            this.div_content.find('div[data-grp]')
                    .on('click', function(event){
                        let btn = $(event.target);
                        let grp_val = btn.attr('data-grp');
                        if(!grp_val){
                            btn = $(event.target).parents('div[data-grp]');
                            grp_val = btn.attr('data-grp');
                        }
                        let ele = that.div_content.find('div[data-grp-content="'+grp_val+'"]');
                        if(ele.is(':visible')){
                            that._grp_keep_status[grp_val] = 0;
                            ele.hide();
                            btn.find('.expand_button').removeClass('ui-icon-triangle-1-s').addClass('ui-icon-triangle-1-e');
                        }else{
                            if(that.options.groupOnlyOneVisible){
                                //collapse other groups
                                that.div_content.find('div[data-grp]')
                                        .find('.expand_button')
                                        .removeClass('ui-icon-triangle-1-s').addClass('ui-icon-triangle-1-e');
                                that.div_content.find('div[data-grp-content]').hide();
                                that._grp_keep_status = {};
                                that._grp_keep_status[grp_val] = 1;    
                            }else{
                                that._grp_keep_status[grp_val] = 1;    
                            }
                            
                            
                            ele.show();
                            btn.find('.expand_button').removeClass('ui-icon-triangle-1-e').addClass("ui-icon-triangle-1-s");
                        }
                    });
        }
        
        function ___ontooltip(callback){

            let s = '';
            if(window.hWin.HEURIST4.util.isFunction(that.options.onTooltip)){
                s = that.options.onTooltip.call(this, callback); // pass content to callback, if a server call is required
            }else{
                let ele = $( this );
                s = ele.attr('title');
            }
            return window.hWin.HEURIST4.util.isempty(s)?'':s;
        }
        
        this.div_content.find('div.recordTitle').tooltip({content: ___ontooltip}); //title may have html format - use jquery tooltip
        this.div_content.find('div.rolloverTooltip').tooltip({content: ___ontooltip}); //use jquery tooltip

        if(this.options.select_mode!='select_multi'){
            this.div_content.find('.recordSelector').hide();
            
            if(this.options.auto_select_first !== false){ // this.options.view_mode == 'vertical' || 
                let ele = this.div_content.find('.recordDiv:first');
                if(ele.length>0){
                    setTimeout(() => {that._recordDivOnClick({target:ele[0]});}, 1500); // allow later widgets to catch up
                }
            }

        }else if(this._currentMultiSelection!=null) { //highlight retained selected records

            for(let idx=0; idx<rec_onpage.length; idx++){
                const recID = rec_onpage[idx];
                let index = window.hWin.HEURIST4.util.findArrayIndex(recID, this._currentMultiSelection);
                if(index>=0){ //this._currentSelection.getById(recID)!=null
                    let $rdiv = this.div_content.find('.recordDiv[recid="'+recID+'"]');
                    $rdiv.find('.recordSelector>input').prop('checked','checked');
                    $rdiv.addClass('selected');
                }
            }
        }

        // Auto select record(s)
        let auto_select = this._auto_select_record && this._auto_select_record.length > 0 && !this.options.auto_select_first;
        if(auto_select){

            this._auto_select_record = this.options.select_mode!='select_multi' ? [ this._auto_select_record[0] ] : this._auto_select_record;
            let enum_multi = this._auto_select_record.length > 1;

            setTimeout(() => {
                for(const rec_id of that._auto_select_record){
                    let $div = that.div_content.find('.recordDiv[recid="'+rec_id+'"]');
                    if($div.length > 0){
                        that._recordDivOnClick({target: $div, ctrlKey: enum_multi});
                    }
                }
            }, 1500);
        }
        
        let $allrecs = this.div_content.find('.recordDiv');
        
        if(this.options.view_mode == 'horizontal'){ // || this.options.view_mode == 'icons_list'
            let h = this.div_content.height();
                h = (((h<60) ?60 :((h>200)?230:h))-30) + 'px';
            $allrecs.css({
                        height: h,
                        width: h,
                        'min-width': h
            });
            this.div_content.find('.recordTitleInPlaceOfThumb').css({height: h});
            
        }
        else if(this.options.view_mode == 'vertical'){
            let w = this.div_content.width();
                w = (((w<60) ?60 :((w>200)?230:w))-30) + 'px';
            $allrecs.css({
                        height: w,
                        width: w,
                        'min-height': w
            });
        }
        
        //tabindex is required to keydown be active
        $allrecs.each(function(idx, item){
            $(item).prop('tabindex',idx);
        });

        //
        // show record info on mouse over
        //
        if(this.options.recordview_onselect===false || this.options.recordview_onselect==='none'){
            
            this._on( this.div_content.find('.rec_view_link'), {
                mouseover: function(event){
                    
                    let ele = $(event.target).parents('.recordDiv');
                    let rec_id = ele.attr('recid');
                    
                    this._clearTimeouts();
                    
                    this._myTimeoutOpenRecPopup = setTimeout(function(){
                        that._showRecordViewPopup( rec_id );
                    },1000);

                }
                ,mouseout: function(){
                    this._clearTimeouts();
                    this._closeRecordViewPopup();
                }

            });
        }
        
        //
        //        
        this._on( $allrecs, {
            click: this._recordDivOnClick,
            //keypress: this._recordDivNavigateUpDown,
            keydown: this._recordDivNavigateUpDown,
            //keyup: this._recordDivNavigateUpDown,
            mouseover: this._recordDivOnHover,
            /* enable but specify entityName to edit in options */
            dblclick: function(event){ //start edit on dblclick
                if(!window.hWin.HEURIST4.util.isFunction(this.options.renderer)){
                    
                    if(window.hWin.HAPI4.has_access()){
                        
                        let $rdiv = $(event.target);
                        if(!$rdiv.hasClass('recordDiv')){
                            $rdiv = $rdiv.parents('.recordDiv');
                        }
                        let selected_rec_ID = $rdiv.attr('recid');

                        event.preventDefault();
                        
                        let ordered_recordset = null;
                        if(this._currentRecordset){
                            ordered_recordset = this._currentRecordset;
                        }else{
                            ordered_recordset = this._query_request;
                        }

                        let existing_dlg = $(`[id^="heurist-dialog-Record"][data-recid="${selected_rec_ID}"]`);
                        if(selected_rec_ID > 0 && existing_dlg.length > 0 && existing_dlg.dialog('instance') !== undefined){ // record already opened
                            return;
                        }

                        window.hWin.HEURIST4.ui.openRecordInPopup(selected_rec_ID, ordered_recordset, true, null);                        
                        
                        //@todo callback to change rectitle    
                    }else{
                        this._recordDivOnClick(event);
                    }
                }else{
                    let selected_recs = this.getSelected( false );
                    this._trigger( "ondblclick", null, selected_recs );
                }
            }
        });
        let inline_selectors = this.div_content.find('.recordDiv select');
        if(inline_selectors.length>0){

            inline_selectors.find('option[value=""]').remove();
            $.each(inline_selectors, function(idx, ele){
                $(ele).val($(ele).attr('data-grpid')); 
            });

            this._on( inline_selectors, {
                //click: function(){ return false; },
                change: function(event){
                    let $rdiv = $(event.target).parents('.recordDiv');
                    let recID = $rdiv.attr('recid');
                    this._trigger( "onaction", null, {action:'group-change', recID:recID, grpID: $(event.target).val()});
                }
            });
        }
        
        if(this.options.sortable){

            if(this.options.sortable_opts == null){
                this.options.sortable_opts = {};
            }

            $.extend(this.options.sortable_opts, {
                stop:function(event, ui){

                    let rec_order = that._currentRecordset.getOrder();
                    let idx = that.current_page*that.options.pagesize;
                    that.div_content.find('.recordDiv').each(function(index, rdiv){
                        let rec_id = $(rdiv).attr('recid');
                        rec_order[idx+index] = rec_id;
                    });
                    that._currentRecordset.setOrder(rec_order);

                    if(window.hWin.HEURIST4.util.isFunction(that.options.onSortStop)){
                        that.options.onSortStop.call(that, this.div_content);    
                    }
                }
            });

            this.div_content.sortable(this.options.sortable_opts);
            //$allrecs.draggable({containment:this.div_content});    
        }
        if(window.hWin.HEURIST4.util.isFunction(this.options.draggable)){
            this.options.draggable.call();
        }
        if(window.hWin.HEURIST4.util.isFunction(this.options.droppable)){
            this.options.droppable.call();
        }
        
        // hide logged in only actions
        if(!window.hWin.HAPI4.has_access()){
            $(this.div_content).find('.logged-in-only').css('visibility','hidden');
            $(this.div_content).find('.recordDiv').addClass('rl-not-logged-in');
        }

        //rec_toload - list of ids
        //load full record info - record header
        this._loadFullRecordData( rec_toload );
        
        this.setCollected( null );
        
        this._trigger( "onpagerender", null, this );
        
        //@todo replace it to event listener in manageRecUploadedFiles as in manageSysGroups
        if(window.hWin.HEURIST4.util.isFunction(this.options.onPageRender)){
            this.options.onPageRender.call(this);
        }

        if(this.options.recordDivEvenClass){
            //$allrecs.addClass(this.options.recordDivEvenClass);    
            $allrecs.each(function(idx, item){
                if(idx % 2 == 0)
                {
                    $(item).addClass(that.options.recordDivEvenClass);    
                }
            });
        }
        
        if(this.div_content_header){
            this._adjustHeadersPos();
        }
        
        
        if(this._expandAllDivs){
            this.expandDetailsInlineALL();   
        }
        
        this._rec_onpage = null;
        if(this.options.show_fancybox_viewer && rec_onpage.length>0){
            this._rec_onpage = rec_onpage;
            if(this._is_fancybox_active){
                this.div_content.mediaViewer({selector:'.realThumb', search_initial:'ids:'+rec_onpage.join(',') });        
            }
        }

        // Replace default rectype thumbnail with linked media thumbnail
        if(this.options.entityName=='records' && rec_toload.length==0
            && this.options.check_linked_media 
            && $allrecs.find('.recTypeThumb.rectypeThumb').length > 0){

            let rec_images = [];

            // Check if any records need checking
            $allrecs.find('.recTypeThumb.rectypeThumb').each((idx, ele) => {

                let cur_rec = $(ele).parent().attr('recid');

                if(Object.hasOwn(that._cached_linked_images, cur_rec)){
                    if(!window.hWin.HEURIST4.util.isempty(that._cached_linked_images[cur_rec])){
                        $(ele).css('background-image', `url("${that._cached_linked_images[cur_rec]}")`)
                              .css('opacity',1);
                    }
                }else{
                    rec_images.push(cur_rec);
                    that._cached_linked_images[cur_rec] = ''; // default fill in
                }
            });

            if(rec_images.length > 0){

                // Get linked media thumbnail(s)
                let request = {
                    a: 'get_linked_media',
                    ids: rec_images.join(',')
                };

                window.hWin.HAPI4.RecordMgr.search(request, (response) => {

                    if(response.status != window.hWin.ResponseStatus.OK){
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                        return;
                    }

                    for(let rec_id in response.data){

                        let url = response.data[rec_id]
                        let $rec = that.div_content.find(`.recordDiv[recid="${rec_id}"]`);

                        that._cached_linked_images[rec_id] = url;

                        if($rec.length <= 0 || window.hWin.HEURIST4.util.isempty(url)){
                            continue;
                        }

                        // Use linked media thumbnail
                        $rec.find('.recTypeThumb.rectypeThumb')
                            .removeClass('rectypeThumb')
                            .addClass('realThumb')
                            .css('background-image', `url("${url}")`)
                            .css('opacity',1);
                    }
                });
            }
        }

        if(this.options.field_for_ext_classes > 0){
            this._addCustomClasses();
        }
    },
    
    //
    //
    //
    _loadFullRecordData: function( rec_toload ) {
        if(rec_toload.length>0){
            let that = this;

            that.loadanimation(true);

            if(window.hWin.HEURIST4.util.isFunction(this.options.searchfull)){
                //call custom function 
                this.options.searchfull.call(this, rec_toload, this.current_page, 
                    function(response){ 
                        that._onGetFullRecordData(response, rec_toload); 
                });

            }else{

                let ids = rec_toload.join(',');
                let request = { q: '{"ids":"'+ ids+'"}',
                    w: 'a',
                    detail: 'header',
                    id: window.hWin.HEURIST4.util.random(),
                    pageno: that.current_page,
                    source:this.element.attr('id') };

                window.hWin.HAPI4.RecordMgr.search(request, function(response){
                    that._onGetFullRecordData(response, rec_toload);   
                });
            }
        }
    },
    
    //
    //
    //
    _onGetFullRecordData: function( response, rec_toload ){

        this.loadanimation(false);
        
        if(!this._currentRecordset) return;
        
        if(response.status == window.hWin.ResponseStatus.OK){

            if(response.data.pageno==this.current_page) { //response.data.queryid==this.current_page || 

                let resp = new HRecordSet( response.data );
                this._currentRecordset.fillHeader( resp );

                //remove records that we can't recieve data
                let i;
                for(i in rec_toload){
                    let recID = rec_toload[i];
                    if(resp.getById(recID)==null){
                        this._currentRecordset.removeRecord(recID);
                       
                    }        
                }

                this._renderPage( this.current_page );
            }

        }else{
            window.hWin.HEURIST4.msg.showMsgErr(response);
        }

    },

    showRetainedSelection: function(){

        let need_show = this.cb_selected_only.is(':checked');


        if(need_show && this._currentMultiSelection!=null && this._currentMultiSelection.length>0){
            this._renderPage(0, null, true);
        }else{
            this._renderPage(this.current_page);
        }
    },

    getRecordSet: function(){
        return this._currentRecordset;    
    },

    //
    //
    //
    getRecordsById: function (recIDs){
        if(this._currentRecordset){
            return this._currentRecordset.getSubSetByIds(recIDs);
        }else{
            return null;
        }        
    },

    //
    // NOT USED
    //
    applyFilter:function(request){
        /*
        $.each(this._currentRecordset)
        this.recordList.find
        */
    },
    
    resetGroups: function(){
        this._grp_keep_status = {};
    },
    
    setHeaderText: function(newtext, headercss){
        
        let stext
        if(this.options.is_h6style){
            stext = window.hWin.HEURIST4.util.isempty(newtext) ?this.options.title:newtext;
            if(window.hWin.HEURIST4.util.isempty(stext)) stext = window.hWin.HR('Filtered Result');
        }else{
            stext = '<h3 style="margin:0">'+(this.options.title ?this.options.title :newtext)+'</h3>';
            if(this.options.show_inner_header && this.options.title && newtext) stext = stext +  '<h4 style="margin:0;font-size:0.9em">'+newtext+'</h4>';
        }

        this.div_header.find('div.result-list-header').html( stext );
        if(headercss){
            this.div_header.css(headercss);    
        }
        this._adjustHeadersPos();
        
        this.refreshSubsetSign();    
    },
    
    //
    //
    //
    refreshSubsetSign: function(){
        if(this.div_header){
            let container = this.div_header.find('div.result-list-header');
            container.find('span').remove();
            
            let s = '<span class="subset-sign" style="position:absolute;left:10px;top:10px;font-size:0.6em;">';    
            if(window.hWin.HAPI4.sysinfo.db_workset_count>0){
                
                if(this.options.show_menu){ 
                  s = s
                      +'<span class="ui-icon ui-icon-arrowrefresh-1-w clear_subset" style="font-size:1em;" '
                      +'title="'+window.hWin.HR('Click to revert to whole database')+'"></span>&nbsp;';
                }    
                
                $(s
                +'<span style="padding:.4em 1em 0.3em;background:white;color:red;vertical-align:sub;font-size: 11px;font-weight: bold;"'
                +' title="'+window.hWin.HAPI4.sysinfo.db_workset_count+' '+window.hWin.HR('records')+'"'
                +'>'+window.hWin.HR('SUBSET ACTIVE')+' n='+window.hWin.HAPI4.sysinfo.db_workset_count+'</span></span>')
                    .appendTo(container);
                    
                let w = container.find('span.subset-sign').width()+20;

                container.css('padding','10px 10px 10px '+w+'px');            
                    
            }else if(this.options.show_menu) { 
            
                container.css('padding','10px');            
            }
            if(this.options.show_menu){
                this._on(container.find('span.set_subset').button(),
                    {click: function(){this.callResultListMenu('menu-subset-set');}} );
                this._on(container.find('span.clear_subset').css('cursor','pointer'),
                    {click: function(){this.callResultListMenu('menu-subset-clear');}} );
            }
            
            
        }
    },
    
    //
    //
    //
    setOrderAndSaveAsFilter: function(bypass=false){

        let that = this;

        if(this._query_request.q.indexOf('sortby:set') < 0 && bypass === false){
            let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                'Do you want to use the current selection as the ordered list (which will be saved as a new filter),<br>'
                + 'or create a new blank list?<br><br>'
                + 'Records can be added to the list by doing further filters and dragging additional records into the list.',
                {
                    'Current records': function(){
                        that._sortResultList_need_fill = true;
                        that.setOrderAndSaveAsFilter(true);
                        $dlg.dialog('close');
                    },
                    'Blank list': function(){
                        that._sortResultList_need_fill = false;
                        that.setOrderAndSaveAsFilter(true);
                        $dlg.dialog('close');
                    },
                    'Cancel': function(){
                        $dlg.dialog('close');
                    }
                }, {title: window.hWin.HR('New ordered list')}
            );
            return;
        }
            
        this.setOrderManually(
            function(recordset, record){ 
                let recID = recordset.fld(record, 'rec_ID');
                return '<div class="recordDiv" recid="'+recID+'">'  //id="rd'+recID+'" 
                //+'<span style="min-width:150px">'
                //+ recID + '</span>'
                + window.hWin.HEURIST4.util.htmlEscape( recordset.fld(record, 'rec_Title') ) 
                + (that._is_sortResultList_tab_based?
('<div class="action-button-container">'
+'<span class="ui-button-icon-primary ui-icon ui-icon-circle-minus rec_remove" style="cursor:pointer;font-size:11px"></span></div>')
                :'')
                + '</div>';
            },
            function( new_rec_order ){

                if(new_rec_order.length>0){

                    let svsID;
                    if(that._sortResult_svsID>0){
                        svsID = that._sortResult_svsID;
                    }else
                    if(that._currentSavedFilterID>0 && window.hWin.HAPI4.currentUser.usr_SavedSearch && 
                        window.hWin.HAPI4.currentUser.usr_SavedSearch[that._currentSavedFilterID]){

                        //if current saved search has sortby:set - just edit with new query
                        let squery = window.hWin.HAPI4.currentUser.usr_SavedSearch[that._currentSavedFilterID][Hul._QUERY];
                        if(squery.indexOf('sortby:set')>=0){
                            svsID = that._currentSavedFilterID;
                        }else{
                            let groupID =  window.hWin.HAPI4.currentUser.usr_SavedSearch[that._currentSavedFilterID][Hul._GRPID];
                            window.hWin.HAPI4.save_pref('last_savedsearch_groupid', groupID);
                        }
                    }

                    //call for saved searches dialog
                    let squery = 'ids:'+new_rec_order.join(',')+' sortby:set';
                    let  widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('svs_list');
                    if(widget){
                        widget.svs_list('editSavedSearch', 'saved', null, svsID, squery, null, true, 
                        function(new_svs_id){
                            that._sortResult_svsID = new_svs_id;
                            that._sortResult_was_changed = false;
                        }); //call public method
                    }
                }
            }                   
        );
    },
    
    //
    //
    //
    setOrderManually: function(renderer, save_callback){
        
        let that = this;

        if(!this.sortResultList){
            
            let list_parent;
            
            if(this._is_sortResultList_tab_based){
                //show as separate tab on tabcontrol
                let app = window.hWin.HAPI4.LayoutMgr.appGetWidgetById('heurist_Graph');
                
                let ele = $(app.widget);  //find panel with widget
                if( ele.hasClass('ui-tabs-panel') ){
                    //get parent tab and make it active
                    ele = $(ele.parent());
                    
                    let num_tabs = ele.find('ul li').length + 1;

                    ele.find('ul').append(
                        '<li><a href="#tab' + num_tabs + '">' + window.hWin.HR('Reorder') + '</a></li>');
        
                    this.sortResultListDlg = $('<div id="tab' + num_tabs + '" style="position:absolute;inset:38px 4px 0px 2px">'
                    +'</div>').appendTo(ele);
                    
                    ele.tabs('refresh');
                    
                    
                    $('<div class="ent_header">'
                        +'<span style="padding-top: 5px;display: inline-block;">'
                            +window.hWin.HR('Drag records to position in list, drag into list to add them')+'</span>'
                        +'<button id="btn-clear" style="float:right">'+window.hWin.HR('Close')+'</button>'
                        +'<button id="btn-save-order" style="float:right">'+window.hWin.HR('Save')+'</button>'
                        +'</div>').appendTo(this.sortResultListDlg);

                    this.sortResultListDlg.css('top',ele.find('ul').height()+4);
                    
                    function __closeReorderTab(){
                        let tabs = $(that.sortResultListDlg.parent());
                        tabs.find('a[href="#'
                            +that.sortResultListDlg.attr('id')+'"]')
                            .closest('li').hide();
                        tabs.tabs('option','active',0);
                        that._last_saved_set = 0;
                        that._sortResultList_need_fill = true;
                        that._sortResult_was_changed = false;
                    }
                    
                    function __saveReorderTab(){
                        //get new order of records ids
                        let recordset = that.sortResultList.resultList('getRecordSet');
                        let new_rec_order = recordset.getOrder();
                        if(new_rec_order.length>0){
                            save_callback.call(that, new_rec_order )       
                        }
                    }
                    
                    
                    ele = this.sortResultListDlg.find('#btn-clear').button();
                    this._on(ele,{click: function(){
                     
                        if(that._sortResult_was_changed){
                            //show warning
                            let $__dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                            '<p>' + window.hWin.HR('resultList_reorder_list_changed')+'</p>',
                            {'Save' :function(){ 
                                __saveReorderTab();
                                $__dlg.dialog( "close" );
                            },
                            'Cancel':function(){
                                $__dlg.dialog( "close" );
                            },
                            'Close':function(){
                                __closeReorderTab();
                                $__dlg.dialog( "close" );
                            }
                            }, {title:window.hWin.HR('Warning')});
                        }else{
                            __closeReorderTab();    
                        }    
                        
                    }});

                    ele = this.sortResultListDlg.find('#btn-save-order').button();
                    this._on(ele,{click:__saveReorderTab});
                    
                }
            }else{
                this.sortResultListDlg = $('<div>').appendTo(this.element);
            }

            //init result list
            this.sortResultList = $('<div>').appendTo(this.sortResultListDlg)
                .resultList({
                   recordDivEvenClass: 'recordDiv_blue',
                   eventbased: false, 
                   multiselect: false,
                   view_mode: 'list',
                   sortable: true,
                   onSortStop: function(){
                        that._sortResult_was_changed = true;
                   },
                   show_toolbar: false,
                   select_mode: 'select_single',
                   entityName: this._entityName,
                   pagesize: 9999999999999,
                   renderer: renderer
                });
        }
        
        if(this._sortResultList_need_fill){
            //fill result list with current page ids
            //get all ids on page
            let ids_on_current_page = [];
            this.div_content.find('.recordDiv').each(function(ids, rdiv){
                ids_on_current_page.push($(rdiv).attr('recid'));
            });
            if(ids_on_current_page.length==0) return;
            //get susbet
            let page_recordset = this._currentRecordset.getSubSetByIds(ids_on_current_page);
            page_recordset.setOrder(ids_on_current_page); //preserve order
            this.sortResultList.resultList('updateResultSet', page_recordset);
            this._sortResult_was_changed = false;
        }

        if(this.sortResultList.resultList('getRecordSet') == null){
            this.sortResultList.resultList('updateResultSet', new HRecordSet());
        }

        if(this._is_sortResultList_tab_based){

            this._sortResultList_need_fill = false;
            
            let tabs = $(this.sortResultListDlg.parent());
            let num_tabs = tabs.find('ul li').length;
            
            tabs.find('a[href="#'
                            +this.sortResultListDlg.attr('id')+'"]')
                            .closest('li').show();
            tabs.tabs('option','active',num_tabs-1);
            
            this.sortResultList.css({top:'40px',bottom: '4px',position: 'absolute', width: '100%'});
            
            //init drag and drop
            if(!this.options.draggable){

                this.options.draggable = 
                        function(){

                            that.element.find('.recordDiv').draggable({ // 
                                        revert: 'invalid',
                                        helper: function(){ 
                                            
                                            //get selection
                                            let rec_ids = that.getSelected(true);
                                            if (window.hWin.HEURIST4.util.isempty(rec_ids)){
                                                rec_ids = [];
                                            }
                                            let r_id = ($(this).hasClass('recordDiv')
                                                            ?$(this)
                                                            :$(this).parent('.recordDiv')).attr('recid');
                                            
                                            if(r_id>0 && rec_ids.indexOf(r_id)<0){
                                                rec_ids.push(r_id);
                                            }
                                            
                                            if(rec_ids.length>0){
                                                return $('<div class="rt_draggable ui-drag-drop" recid="'+
                                                    rec_ids.join(',')
                                                +'" style="width:300;padding:4px;text-align:center;font-size:0.8em;background:#EDF5FF"'
                                                +'>'
                                                +'Drop '+(rec_ids.length>1?(rec_ids.length+' selected records'):'record')
                                                +' at desired position in list</div>'); 
                                            }else{
                                                return null;
                                            }
                                        },
                                        stop: function(event, ui){

                                            // Check that drop happened over list area
                                            let ele = document.elementFromPoint(ui.position.left, ui.position.top);
                                            if(ele == null || !$(ele).is('div.div-result-list-content.list')){
                                                return;
                                            }

                                            let rec_IDs = $(ui.helper).attr('recid').split(',');
                                            if(!window.hWin.HEURIST4.util.isempty(rec_IDs)){

                                                let to_be_added = that._currentRecordset.getSubSetByIds(rec_IDs); // records to be added

                                                let cur_recset = that.sortResultList.resultList('getRecordSet'); // existing records
                                                let cur_cnt = cur_recset.length();
                                                let add_cnt = 0;

                                                if(cur_cnt > 0){
                                                    cur_recset = cur_recset.doUnite(to_be_added, -1); // add to end
                                                    add_cnt = cur_recset.length() - cur_cnt; // new count
                                                }else{
                                                    cur_recset = to_be_added; // replace empty recordset
                                                    add_cnt = cur_recset.length(); // new count
                                                }

                                                if(add_cnt > 0){

                                                    let msg = add_cnt + (rec_IDs.length > 0 ? ' of ' + rec_IDs.length : '')
                                                        + ' record' + (add_cnt > 1 ? 's' : '') + ' added';

                                                    window.hWin.HEURIST4.msg.showMsgFlash(msg, 2000);

                                                    that.sortResultList.resultList('updateResultSet', cur_recset);
                                                    that._sortResult_was_changed = true;
                                                }else{
                                                    window.hWin.HEURIST4.msg.showMsgFlash('Record'+(rec_IDs.length > 1 ? 's' : '') + ' already in list', 2000);
                                                }
                                            }
                                        },
                                        cursorAt:{top: 0, left: 5},                                    
                                        zIndex:100,
                                        appendTo:'body',
                                        containment: 'window',
                                        scope: 'sort_order_change'
                                        //delay: 200
                                    })
                        };

                this.options.draggable.call();

                this.sortResultList.resultList('option','droppable',
                function(){

                        that.sortResultList.find('.recordDiv')
                            .droppable({
                                //accept: '.rt_draggable',
                                scope: 'sort_order_change',
                                hoverClass: 'ui-drag-drop',
                                drop: function( event, ui ){

                                    let trg = $(event.target).hasClass('recordDiv')
                                                ?$(event.target)
                                                :$(event.target).parents('.recordDiv');
                                                
                                    let rec_IDs = $(ui.helper).attr('recid').split(',');
                                    let before_rec_ID = trg.attr('recid');
                        
                                    if(!window.hWin.HEURIST4.util.isempty(rec_IDs) && before_rec_ID>0){

                                            //get subset
                                            let to_be_added = that._currentRecordset.getSubSetByIds(rec_IDs);
                                            
                                            //merge
                                            let curr_recset = that.sortResultList.resultList('getRecordSet');
                                            
                                            let cnt_0 = curr_recset.length();
                                            
                                            curr_recset = curr_recset.doUnite(to_be_added, before_rec_ID);
                                            
                                            let cnt_added = (curr_recset.length()-cnt_0);
                                            
                                            if(cnt_added>0){
                                                let msg = cnt_added+(rec_IDs.length>0?' of '+rec_IDs.length:'')
                                                +' record'+(cnt_added>1?'s':'')+' added';
                                                
                                                window.hWin.HEURIST4.msg.showMsgFlash(msg, 2000, null, trg);  
                                                
                                                //refresh
                                                that.sortResultList.resultList('updateResultSet', curr_recset);           
                                                that._sortResult_was_changed = true;
                                            }else{
                                                window.hWin.HEURIST4.msg.showMsgFlash('Record'
                                                    +(rec_IDs.length>1?'s':'')+' already in list'
                                                    , 2000, null, trg);  
                                            }
                                                
                                    }
                            }});
                });
                
                let foo = this.sortResultList.resultList('option','droppable')
                foo.call();
            }

        } else {
        
            let $dlg = window.hWin.HEURIST4.msg.showElementAsDialog({element: $(this.sortResultListDlg)[0],
                title: window.hWin.HR('menu_reorder_title'),
                height:500,
                default_palette_class:'ui-heurist-explore',
                buttons:[
                    {text:window.hWin.HR('menu_reorder_save'), click: function(){

                        //get new order of records ids
                        let recordset = that.sortResultList.resultList('getRecordSet');
                        let new_rec_order = recordset.getOrder();
                        $dlg.dialog( "close" );
                        if(new_rec_order.length>0){
                            save_callback.call(this, new_rec_order )       
                        }
                    }},
                    {text:window.hWin.HR('Cancel'), click: function(){$dlg.dialog( "close" );}}
                ]
                });
        }        
    },
    
    //
    //
    //
    callResultListMenu: function( action ){
        if(this.div_actions){
            this.div_actions.resultListMenu('menuActionHandler', action);
        }
    },
    
    //
    // 
    //
    _closeRecordViewPopup: function(){
        
        let crs = $('#recordview_popup').css('cursor');
        if(crs && crs.indexOf('resize')>0) return;
        
        this._myTimeoutCloseRecPopup = setTimeout(function(){
            let dlg = $('#recordview_popup');
            let crs = dlg.css('cursor');
            if(crs && crs.indexOf('resize')>0) return;

            if(window.hWin.record_viewer_popups && window.hWin.record_viewer_popups.length > 0){
                // Keep open if nested record viewers are open

                let has_viewer_open = false;
                $.each(window.hWin.record_viewer_popups, (idx, ele) => {
                    if(ele.is(':visible')){
                        has_viewer_open = true;
                        return false;
                    }
                });

                if(has_viewer_open) { return; }
            }
            
            if(dlg.dialog('instance')) dlg.dialog('close');
        },  2000); //600
                        
                        
        
    },
    
    _clearTimeouts: function(){
            clearTimeout(this._myTimeoutOpenRecPopup);
            this._myTimeoutOpenRecPopup = 0;
            clearTimeout(this._myTimeoutCloseRecPopup);
            this._myTimeoutCloseRecPopup = 0;
    },
    
    /**
     * Display record details within a popup
     *  using either the default record viewer or a selected custom report
     * 
     * @param {int} rec_ID - record ID 
     * @returns none
     */
    _showRecordViewPopup: function( rec_ID ){

        const that = this;

        let recInfoUrl = null;
        let is_template = false;
        let coverMsg = 'Loading ';
        let rec_Title = this._currentRecordset.fld( this._currentRecordset.getById(rec_ID), 'rec_Title' );
        let rec_Type = this._currentRecordset.fld( this._currentRecordset.getById(rec_ID), 'rec_RecTypeID' );

        if(this._currentRecordset && rec_ID>0){
            recInfoUrl = this._currentRecordset.fld( this._currentRecordset.getById(rec_ID), 'rec_InfoFull' );
        }else{
            return;
        }

        if(this._currentRecordset && rec_ID>0){
            coverMsg += rec_Title + ' ';
        }
        coverMsg += '...';

        let lt = 'WebSearch';//window.hWin.HAPI4.sysinfo['layout'];  
        if( !recInfoUrl ){
            
            if ( typeof this.options.rendererExpandDetails === 'string' && this.options.rendererExpandDetails.substr(-4)=='.tpl' ){

                recInfoUrl = window.hWin.HAPI4.baseURL + '?q=ids:'
                + rec_ID
                + '&db='+window.hWin.HAPI4.database+'&template='
                + encodeURIComponent(this.options.rendererExpandDetails);

                is_template = true;
            }else{
                recInfoUrl = window.hWin.HAPI4.baseURL + "viewers/record/renderRecordData.php?db="
                        +window.hWin.HAPI4.database+"&ll="+lt+"&recID="+rec_ID;  
                
                if(this._is_publication && this.options.recviewer_images != 0){
                    recInfoUrl += '&hideImages=' + this.options.recviewer_images;
                }
                if(this._is_publication && this.options.recview_private_details !== null){
                    recInfoUrl += '&privateDetails=' + this.options.recview_private_details;
                }
            }
            
            if(this.options.language && this.options.language!='def'){
                recInfoUrl = recInfoUrl + '&lang='+this.options.language;
            }
        }
        
        let pos = null;
        let dlg = $('#recordview_popup');

        rec_Title = !window.hWin.HEURIST4.util.isempty(rec_Title) ? `${$Db.rty(rec_Type, 'rty_Name')}: ${rec_ID} ${rec_Title}` : '';
        let popup_title = window.hWin.HEURIST4.util.isempty(rec_Title) ? window.hWin.HR('Record Info') : rec_Title;
        popup_title += `<em style="font-size:10px;font-weight:normal;position:absolute;right:4em;top:35%;">${window.hWin.HR('drag to rescale')}</em>`;

        let opts = {
            is_h6style: true,
            modal: false,
            dialogid: 'recordview_popup',    
            onmouseover: function(){
                that._clearTimeouts();
            },
            title: popup_title,
            default_palette_class: 'ui-heurist-explore',
            coverMsg: coverMsg
        }

        if(dlg.length <= 0){

            if(this._is_publication){

                let popup_dims = this.options.recview_dimensions;
                
                pos = {at: 'left top', of: window};

                if(popup_dims.left == 'center' && popup_dims.top == 'center'){
                    pos['my'] = 'center center';
                }else{
                    let pos_my_left = 'center';
                    let pos_my_top = 'center';
    
                    if(popup_dims.left){
                        pos_my_left = window.hWin.HEURIST4.util.isempty(popup_dims.left) || popup_dims.left == 'center' ? 
                                        pos_my_left : `left+${popup_dims.left}`;
                    }
                    if(popup_dims.top){
                        pos_my_top = window.hWin.HEURIST4.util.isempty(popup_dims.top) || popup_dims.top == 'center' ? 
                                        pos_my_top : `top+${popup_dims.top}`;
                    }
                    pos['my'] = `${pos_my_left} ${pos_my_top}`;
                }

                // Set width and height
                let prop_h = popup_dims.height.replace(/\D+/, '');
                let prop_w = popup_dims.width.replace(/\D+/, '');
    
                if(popup_dims.height.indexOf('%') > 0){ // percentage of available space
                    opts.height = window.hWin.innerHeight * (prop_h / 100);
                }else{ // specific amount of pixels
                    opts.height = prop_h;
                }
                if(popup_dims.width.indexOf('%') > 0){ // percentage of available space
                    opts.width = window.hWin.innerWidth * (prop_w / 100);
                }else{ // specific amount of pixels
                    opts.width = prop_w;
                }
            }else{
                //set intial position right to result list - for main interface only!
                pos = { my: "left top", at: "right top+100", of: $(this.element) };
            }

            if(pos.my.indexOf('center') !== -1){
                pos.at = 'center center';
            }

            opts.position = pos;
        }else if(dlg.dialog('instance') !== undefined){
            dlg.dialog('option', 'title', popup_title);
        }

        window.hWin.HEURIST4.msg.showDialog(recInfoUrl, opts);

        if(pos!=null){
            if(this.options.recordview_onselect===false || this.options.recordview_onselect==='none'){            
                dlg = $('#recordview_popup').css('padding',0);
                this._on(dlg,{
                    mouseout:function(){
                        that._closeRecordViewPopup();
                    }
                });
                let dlg_header = dlg.parent().find('.ui-dialog-titlebar');
                dlg_header.find('.ui-dialog-title').css({width: '80%', 'font-size': '1em'});
                this._on(dlg_header,{mouseout:function(){
                    that._closeRecordViewPopup();
                }});
            }
        }
    },

    //
    // Add class to record div based on field value; currently set for term values only
    //
    _addCustomClasses: function(){
        
        let that = this;
        let dty_id = this.options.field_for_ext_classes;

        if(dty_id == 0 || dty_id < 1 || this.div_content.find('.recordDiv').length == 0){
            return;
        }

        let dty_dtls = $Db.dty(dty_id);
        if(!dty_dtls || dty_dtls['dty_Type'] != 'enum'){
            return;
        }

        let recids = [];

        $.each(this.div_content.find('.recordDiv'), (idx, ele) => {
            recids.push($(ele).attr('recid'));
        });

        if(recids.length == 0){
            return;
        }

        let rec_ids = recids.join(',');

        let request = {
            q: '{"ids":"'+ rec_ids +'"}',
            w: 'a',
            detail: 'rec_ID,'+dty_id,
            id:window.hWin.HEURIST4.util.random(),
            pageno: that.current_page,
            source: this.element.attr('id')
        }; // retrieve field values

        window.hWin.HAPI4.RecordMgr.search(request, (response) => {
            if(response.status == window.hWin.ResponseStatus.OK){

                let records = new HRecordSet(response.data);

                records.each2((id, record) => {
                    let rec_div = that.div_content.find('.recordDiv[recid="'+ id +'"]');

                    if(rec_div.length == 0){
                        return;
                    }

                    if(Object.hasOwn(record, 'd')){
                        rec_div.addClass('c' + record.d[dty_id].join(' c'));  
                    }
                });
            }
        });
    },

    displayCollection: function(showCollection = true){

        if(showCollection && this._collection.length > 0){

            this._isCollectionUsed = true;

            this._fullRecordset = this._currentRecordset;

            let cnt = this._collection.length;
            let rs = {count:cnt,entityName:"Records",offset:0,reccount:cnt,records:this._collection};           
            this._currentRecordset = new HRecordSet(rs);

        }else{
            this._isCollectionUsed = false;
            this._currentRecordset = this._fullRecordset;
        }

        const query = this._currentRecordset.length() > 0 ? `ids:${this._currentRecordset.getIds().join(',')}` : '';

        $(this.document).trigger(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH, {
            recordset: this._currentRecordset,
            showing_subset: true,
            search_realm: this.options.search_realm,
            query: query
        });

        this._renderPage(0);
    },
    
    getCurrentViewMode: function(){
        return this._current_view_mode;
    },

    _exportRecords: function(){

        if(!this._currentRecordset || this._currentRecordset.length() == 0){
            window.hWin.HEURIST4.msg.showMsgFlash('No records to export...', 3000);
            return;
        }

        // Set current query and current recordset
        window.hWin.HEURIST4.current_query_request = this._query_request;
        window.hWin.HAPI4.currentRecordset = this._currentRecordset;

        if(this._collection && this._collection.length > 0){
            window.hWin.HAPI4.currentRecordsetCollected = this._collection;
        }

        let selected = this.getSelected(true);
        if(selected){
            window.hWin.HAPI4.currentRecordsetSelection = selected;
        }
        
        if(this.options.export_options=='csv'){
            window.hWin.HEURIST4.ui.showRecordActionDialog('recordExportCSV', {});    
        }else{
            // open export menu in dialog/popup
            let url = `${window.hWin.HAPI4.baseURL}hclient/framecontent/exportMenu.php?db=${window.hWin.HAPI4.database}`;

            if(typeof this.options.export_options !== 'string'){
                this.options.export_options = 'all';
            }
            
            let handle_formats = !window.hWin.HEURIST4.util.isempty(this.options.export_options) && this.options.export_options != 'all';
            if(handle_formats){
                url += `&output=${this.options.export_options}`
            }

            window.hWin.HEURIST4.msg.showDialog(url, {width: 650, height: 568, dialogid: 'export_record_popup', 
                onpopupload: function(){
                    if(handle_formats){
                        $('#export_record_popup').dialog('widget').hide();
                    }
                }
            });
        }
    }
    
});
