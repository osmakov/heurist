/**
* Builds and manages display of the main Heurist page - search and visualisation
*
* Before this widget was generic, however search on main page became very distinctive and got lot of additional ui comonents.
* Thus, we have the specific search widget and this one remains for main ui
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @note        Completely revised for Heurist version 4
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
/* global showSearchBuilder */

$.widget( "heurist.search", {

    // default options
    options: {
        is_h6style: false,

        search_domain: 'a', //current search domain all|bookmark|recently added|recently selected  or a|b|r|s
        search_domain_set: null, // comma separated list of allowed domains  a,b,c,r,s

        btn_visible_newrecord: false, // show add record button
        btn_visible_save: false,      // save search popup button  NOT USED
        btn_entity_filter: true,      // show buttons: filter by entity
        search_button_label: '',
        search_input_label: '',

        button_class: 'ui-heurist-btn-header1',
        
        language: 'def',

        // callbacks
        onsearch: null,  //on start search
        onresult: null,   //on search result

        search_page: null, //target page (for CMS)
        search_realm:  null  //accepts search/selection events from elements of the same realm only
    },

    _total_count_of_curr_request: 0, //total count for current request (main and rules) - NOT USED

    query_request:null,

    buttons_by_entity:[], //

    _is_publication:false, //this is CMS publication - take css from parent

    _use_global_query: false, // when on clicking 'filter', apply the global query instead

    // the constructor
    _create: function() {

        let that = this;

        if(this.element.parent().attr('data-heurist-app-id') || this.element.hasClass('cms-element')){

            this.options.button_class = '';
            this._is_publication = true;
            //this is CMS publication - take bg from parent
            this.element.addClass('ui-widget-content').css({'background':'none','border':'none'});
        }else if(!this.options.is_h6style){
            this.element.css({height:'100%','font-size':'0.8em'});
            this.element.addClass('ui-widget-content');
        }else {
            this.options.button_class = '';
        }

        let sz_search_padding = this.options.is_h6style?'5px':'20px';

        //------------------------------------------- filter by entities
        this.options.btn_entity_filter = this.options.btn_entity_filter && (window.hWin.HAPI4.get_prefs_def('entity_btn_on','1')=='1');

        if(this.options.btn_entity_filter){

            function __initEntityFilter(){

                if(window.hWin.HEURIST4.util.isFunction($('body')['searchByEntity'])){ //OK! widget script js has been loaded            
                    this.div_entity_fiter   = $('<div>').searchByEntity({is_publication:this._is_publication})
                    .css({'height':'auto','font-size':'1em'})
                    .appendTo( this.element );
                }else{

                    $.getScript( window.hWin.HAPI4.baseURL + 'hclient/widgets/search/searchByEntity.js', 
                        function() {  //+'?t='+(new Date().getTime())
                            if(window.hWin.HEURIST4.util.isFunction($('body')['searchByEntity'])){
                                __initEntityFilter();
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr({
                                    message: 'Widget searchByEntity not loaded. Verify your configuration',
                                    error_title: 'Script loading failed',
                                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                                });
                            }
                    });

                }
            }

            __initEntityFilter();



        }         
        //------------------------------------------- filter inputs                        

        // Search functions container
        //'height':'100%', 'float':'left'  
        this.div_search   = $('<div>').css({ 'width':'100%', display:'table' }).appendTo( this.element ); //was table-row


        //header-label
        this.div_search_header = $('<div>')
        .css({'width':'auto','text-align':'right','line-height':'31px','padding-left':sz_search_padding}) //'height':'6.88em',
        .addClass('div-table-cell')
        .appendTo( this.div_search );

        if(this.options.search_input_label){
            let lbl = window.hWin.HRJ('search_input_label', this.options, this.options.language);
            this.div_search_header.text( lbl ).css({'padding-right':'4px'});     
        }


        /* hidden on 2016-11-11        
        $( "<label>" ).text(window.hWin.HR("Filter"))
        .css({'font-weight':'bold','font-size':'1.2em','padding-left':'1em','padding-right':'1em','vertical-align': 'top', 'line-height':'20px'})
        .appendTo( this.div_search_header );
        */

        // Search field
        this.div_search_input = $('<div>')
        .addClass('div-table-cell')       
        .appendTo( this.div_search );

        //promt to be shown when input is empty        
        this.input_search_prompt = $( "<span>" )
        .text(this._is_publication?'':window.hWin.HR('search_filter_hint'))
        .addClass('graytext')
        .css({'font-size':'0.8em', 'margin': '18px 0 0 0.5em','position': 'absolute'})
        .appendTo( this.div_search_input );
        this._on( this.input_search_prompt, {click: function(){
            this._setFocus();
        }} );


        this.input_search = $( "<textarea>", {rows: 2} )
        .css({
            'max-width':'99%',
            'resize':'none', 
            'padding':'2px 50px 2px 5px',
            'line-height': '14px', 
            'min-width':'80px', 'width':'100%' }) 
        .addClass("text ui-widget-content ui-corner-all")
        .uniqueId()
        .appendTo(  this.div_search_input );

        let isNotFirefox = (navigator.userAgent.indexOf('Firefox')<0);

        //promt to be shown when input has complex search expression (json search)
        this.input_search_prompt2 = $( "<span>" )
        .html('<span style="font-size:1em;padding:3px;display:inline-block;">'+window.hWin.HR("filter")
            +'</span>&nbsp;&nbsp;<span class="ui-icon ui-icon-eye" style="font-size:1.8em;width: 1.7em;margin-top:1px"></span>')
        .css({ height:isNotFirefox?28:24, 'margin':'1px '+(isNotFirefox?'4px':'7px')+' 1px 2px',
            'position':'absolute', 'text-align':'left', display:'block'})
        .appendTo( this.div_search_input );
        this._on( this.input_search_prompt2, {click: function(){
            this.input_search_prompt2.css({visibility:'hidden'});
            this._setFocus();
        }} );

        if(this._is_publication || this.options.is_h6style){

            if(this._is_publication){
                this.input_search.css({'height':'27px','min-height':'27px','padding':'2px 0px','width':'100%'}); //, 'width':'400'
            }

            this.input_search_prompt2.addClass('ui-widget-content').css({border:'none',top:'52px'});

            this.input_search_prompt2.css({height:'calc(100%-2px)',
                width:'calc(100%-2px)'});    
        }else{
            this.input_search_prompt2.css({top:'52px'}); //'background':'#F4F2F4',
            this.input_search.css('padding-right', '28px');
        }

        // AAAA
        this._on( this.input_search, {
            click: function(){
                if(this.input_search_prompt2.is(':visible')){
                    this.input_search_prompt2.css({visibility:'hidden'}); 
                }
            },
            keyup: this._showhide_input_prompt, 
            change: this._showhide_input_prompt
        });

        //disable because of initial search
        if(this.options.btn_visible_newrecord){
            window.hWin.HEURIST4.util.setDisabled(this.input_search, true); 
            this.input_search.css({'width':'400px','height':'1.4em','max-width':'650px'});
            this.div_search.css({'float':'left'});
        }else{

            if(this.options.is_h6style){
                this.div_search_input.css({'width':'75%','min-width':'80px',height:'30px'}); //'max-width':'470px',  
            }else{
                this.div_search.css({'display':'table',height:'30px'});
            }
        }

        //help link and quick access of saved filters
        if(!this._is_publication && this.options.is_h6style){

            let link = $('<span title="'+window.hWin.HR('filter_help_hint')+'">'
                + window.hWin.HR('Filter help')
                + ' <span class="ui-icon ui-icon-info" style="font-size:0.8em"></span></span>')
            .attr('id', 'search_help_link')
            .addClass('graytext')
            .css({'font-size':'10px',display:'inline-block','text-decoration':'none','outline':'none', cursor:'pointer'})
            .appendTo(this.div_search_input);

            this._on( link, {  click: function(){
                window.open(window.hWin.HRes('advanced_search'),'_blank');
            } });

            let adjustTextareaRows = (context) => {

                if(context.input_search_prompt2.is(':visible') && context.input_search_prompt2.css('visibility') != 'hidden'){
                    return;
                }

                let cur_rows = parseInt(context.input_search.attr('rows'), 10);

                let row_height = context.input_search.attr('rows', '1').height();
                let content_height = context.input_search[0].scrollHeight;
                let rows_count = Math.ceil(content_height / row_height) + 1;
                rows_count = (rows_count > 10) ? 10 : rows_count;

                context.input_search.attr('rows', rows_count);

                if(rows_count != cur_rows){
                    $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE);
                }
            };

            this._on( this.input_search, { 
                focus: () => {
                    adjustTextareaRows(this);
                },
                keyup: () => {
                    adjustTextareaRows(this);
                }
            });
        }


        let menu_h = window.hWin.HEURIST4.util.em(1); //get current font size in em

        this.input_search.data('x', this.input_search.outerWidth());
        this.input_search.data('y', this.input_search.outerHeight());

        this._on(this.input_search, {
            mouseup: function(){

                if (this.input_search.outerWidth() != this.input_search.data('x') || this.input_search.outerHeight() != this.input_search.data('y')) {

                    if(this.input_search.outerHeight()<25){                   
                        this.input_search.height(23);
                    }else{

                        if(this.input_search.outerHeight()> that.element.height()-menu_h-8){    //, 'max-height': (this.element.height()-12)+':px'
                            this.input_search.height(that.element.height()-menu_h-10);
                        }
                    }

                    $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE);
                }
                // set new height/width
                this.input_search.data('x', this.input_search.outerWidth());
                this.input_search.data('y', this.input_search.outerHeight());
            }
        }); // this.input_search.mouseup

        //
        // quick filter builder buttons
        //
        this.div_buttons = $('<div>')
        .css({'text-align':'center','vertical-align':'baseline',display:'inline-block',position:'absolute'})
        .appendTo( this.div_search_input );        

        //
        // show saved filters tree
        //         
        if(!this._is_publication && this.options.is_h6style){

            this.div_buttons.css({'min-width':'70px'});

            // Saved filters 'dropdown'
            this.btn_saved_filters = 
            $('<span class="ui-main-color" '
                +'style="font-size: 9px;position: absolute;min-width: 50px;cursor:pointer;">'
                +'<span style="display:inline-block;width:30px;margin-top: 4px;">saved filters</span>'
                +'<span class="ui-icon ui-icon-carat-1-s" style="font-size: inherit;height: 11px;display: inline-block;vertical-align: super;">'
                +'</span></span>')
            .appendTo(this.div_buttons);

            this._on(this.btn_saved_filters, {click: function(){
                let widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('slidersMenu');
                if(widget){
                    let ele = this.btn_saved_filters;
                    widget.slidersMenu('show_ExploreMenu', null, 'search_filters', 
                        {top:ele.position().top+18 , left:ele.offset().left });
                }            
            }});

            // Filter builder button
            this.btn_filter_wiz = $('<a>',{href:'#',
                title:window.hWin.HR('filter_builder_hint')})
            .css({display:'inline-block'}) //,top:'-3px'
            .addClass('ui-main-color btn-aux')
            .append('<span class="ui-icon ui-icon-magnify-explore" style="height:15px;font-size:larger;" ></span>')
            .append('<span style="display:inline-block; text-decoration: none; font-size: smaller; margin-left: 5px">'
            + window.hWin.HR('Filter builder') 
            +'</span>')
            .appendTo(this.div_buttons);
            this._on( this.btn_filter_wiz, {  click: this.showSearchAssistant });

            // Facet builder button
            this.btn_faceted_wiz = $('<a>',{href:'#', 
                title:window.hWin.HR('filter_facetbuilder_hint')})
            .css({display:'inline-block',padding:'0px 15px'}) //width:90,,top:'-3px'
            .addClass('ui-main-color btn-aux')
            .append('<span class="ui-icon ui-icon-box" style="font-size: larger;" ></span>')
            .append('<span style="display:inline-block; text-decoration: none; font-size: smaller; margin-left: 5px">'
            + window.hWin.HR('Facet builder') +'</span>')
            .appendTo(this.div_buttons);
            this._on( this.btn_faceted_wiz, {  click: function(){

                let widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('slidersMenu');
                if(widget){
                    let ele = this.btn_faceted_wiz;
                    widget.slidersMenu('show_ExploreMenu', null, 'svsAddFaceted', 
                        {top:ele.offset().top , left:ele.offset().left });
                }            
            }});

            // Save filter button
            this.btn_save_filter = $('<a>', {href: '#', title: window.hWin.HR('filter_save_hint')})
            .addClass('ui-main-color btn-aux logged-in-only')
            .css({display:'inline-block'})  //width:'70px',
            .append('<span class="ui-icon ui-icon-save" ></span>')
            .append('<span style="display: inline-block; text-decoration: none; font-size: smaller; margin-left: 5px">'
            + window.hWin.HR('Save filter for re-use') +'</span>')
            .appendTo(this.div_buttons); // div_save_filter
            this._on(this.btn_save_filter, {click: function(){
                let widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('slidersMenu');
                if(widget){
                    let ele = this.btn_saved_filters;

                    widget.slidersMenu('show_ExploreMenu', null, 'svsAdd', 
                        {top:ele.offset().top , left:ele.offset().left-300 });
                }            
            }});

            this.div_search_input.find('#search_help_link')
            .css({'margin-left':'10px'})
            .appendTo(this.div_buttons);
        }

        //
        // search/filter buttons - may be Search or Bookmarks according to settings and whether logged in
        //
        this.div_search_as_user = $('<div>')
        .addClass('div-table-cell')  // logged-in-only
        .appendTo( this.div_search );

        this.btn_search_as_user = $( "<button>", {
            label: window.hWin.HR(this.options.search_button_label), 
            title: window.hWin.HR('filter_start_hint')
        })
        .css({'min-height':'30px','min-width':'90px'})
        .appendTo( this.div_search_as_user )
        .addClass(this.options.button_class+' ui-button-action')
        .button({showLabel:true, icon:this._is_publication?'ui-icon-search':'ui-icon-filter'});

        if(!(this._is_publication || this.options.is_h6style)){
            this.btn_search_as_user.css({'font-size':'1.3em','min-width':'9em'})      
        }else
            if(this.options.search_button_label){
                let w = window.hWin.HEURIST4.util.px(this.options.search_button_label, this.btn_search_as_user);
                this.btn_search_as_user.css({'width': (30+w)+'px'}); 
            }

        this.btn_search_domain = $( "<button>", {
            label: window.hWin.HR("filter option")
        })
        .css({'font-size':'1em','vertical-align':'top'}) 
        .appendTo( this.div_search_as_user )
        .addClass(this.options.button_class+' heurist-bookmark-search')
        .button({icon:'ui-icon-carat-1-s',  label: window.hWin.HR("filter domain"), showLabel:false});


        let dset = ((this.options.search_domain_set)?this.options.search_domain_set:'a,b').split(',');
        let smenu = "";
        $.each(dset, function(index, value){
            let lbl = that._getSearchDomainLabel(value);
            if(lbl){
                smenu = smenu + '<li id="search-domain-'+value+'"><a href="#">'+window.hWin.HR(lbl)+'</a></li>'
            }
        });

        this.menu_search_domain = $('<ul>'+smenu+'</ul>')   //<a href="#">
        .css({position:'absolute', zIndex:9999})
        .appendTo( this.document.find('body') )
        .menu({
            select: function( event, ui ) {
                let mode =  ui.item.attr('id').substr(14); 
                that.option("search_domain", mode);
                that._refresh();
        }})
        .hide();

        this._on( this.btn_search_domain, {
            click: function() {
                $('.ui-menu').not('.horizontalmenu').not('.heurist-selectmenu').hide(); //hide other
                let menu = $( this.menu_search_domain )
                .css('width', '100px')     //was this.div_search_as_user.width()
                .show()
                .position({my: "right top", at: "right bottom", of: this.btn_search_domain });
                $( document ).one( "click", function() { menu.hide(); });
                return false;
            }
        });

        // Add record button - NOT USED FOR A LONG TIME IN MAIN UI
        if(this.options.btn_visible_newrecord){

            /* on right hand side
            this.div_add_record = $('<div>')
            .addClass('logged-in-only')
            .css({'float': 'right', 'padding': '23px 23px 0 0'})
            .appendTo( this.element );
            */

            this.div_add_record = $('<div>').css({'min-width':'36em','padding-left':'40px'})
            .addClass('div-table-cell logged-in-only')
            .appendTo( this.div_search );


            this.btn_add_record = $( "<button>", {
                title: "Click to select a record type and create a new record (entity) in the database"
            })
            .css({'font-size':'1.3em','min-width':'110px','max-width':'250px'})  
            //.addClass('logged-in-only')
            //.addClass(this.options.button_class)
            .appendTo( this.div_add_record )
            .button({label: window.hWin.HR("Add Record"), icon:'ui-icon-plusthick'}) //"ui-icon-circle-plus"
            .addClass('truncate')
            .on('click', function(){ 
                window.hWin.HAPI4.SystemMgr.verify_credentials(function(){
                    if(that.select_rectype_addrec.val()>0){
                        window.hWin.HEURIST4.ui.openRecordEdit(-1, null, 
                            {new_record_params:{RecTypeID:that.select_rectype_addrec.val()}});
                    }else{
                        that.btn_select_rt.trigger('click');
                    }
                }); 
            });

            this.btn_select_rt = $( "<button>")
            .css({'font-size':'1.3em'})
            .appendTo( this.div_add_record )
            //.addClass(this.options.button_class+' heurist-bookmark-search')
            .button({label:window.hWin.HR("Select record type"), icon: "ui-icon-carat-1-s", showLabel:false});

            this.btn_add_record_dialog = $( "<button>")
            .css({'font-size':'0.8em'})
            .appendTo( this.div_add_record )
            .button({label: '<div style="text-align:left;display:inline-block">'
                +window.hWin.HR('Define Parameters') +'<br>'+window.hWin.HR('Add Record')+'</div>', 
                icon: "ui-icon-carat-1-s", iconPosition:'end',
                title:'Click to define parameters and add new record'})
            .on('click', function(){ 
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordAdd');            
            });

            this.btn_add_record_dialog.find('.ui-button-icon').css('vertical-align','baseline');

            this.btn_select_owner = $( "<button>")
            .css({'font-size':'0.8em'})
            .appendTo( this.div_add_record )
            .button({label:'owner', icon: "ui-icon-carat-1-s", iconPosition:'end',
                title:'Ownership and access rights for new record'}).hide();
           
            this.btn_select_owner.find('.ui-button-icon').css('vertical-align','baseline');

            this._on( this.btn_select_rt, {
                click:  function(){

                    this.select_rectype_addrec.hSelect('open');
                    this.select_rectype_addrec.hSelect('menuWidget')
                    .position({my: "left top", at: "left bottom", of: this.btn_add_record });
                    return false;

            }});

            this._on( this.btn_select_owner, {
                click:  function(){
                    let add_rec_prefs = window.hWin.HAPI4.get_prefs('record-add-defaults');
                    if(!Array.isArray(add_rec_prefs) || add_rec_prefs.length<4){
                        add_rec_prefs = [0, 0, 'viewable', '']; //rt, owner, access, tags  (default to Everyone)
                    }
                    if(add_rec_prefs.length<5){ //visibility groups
                        add_rec_prefs.push('');
                    }

                    //show dialog that changes ownership and view access                   
                    window.hWin.HEURIST4.ui.showRecordActionDialog('recordAccess', {
                        currentOwner:  add_rec_prefs[1],
                        currentAccess: add_rec_prefs[2],
                        currentAccessGroups: add_rec_prefs[4],
                        scope_types: 'none',
                        height:400, 
                        title: window.hWin.HR('Default ownership and access for new record'),
                        onClose:                         
                        function(context){

                            if(context && context.NonOwnerVisibility && 
                                (context.NonOwnerVisibility!=add_rec_prefs[2] || 
                                    context.OwnerUGrpID!=add_rec_prefs[1] ||
                                    context.NonOwnerVisibilityGroups!=add_rec_prefs[4])){

                                add_rec_prefs[1] = context.OwnerUGrpID;  
                                add_rec_prefs[2] = context.NonOwnerVisibility;  
                                add_rec_prefs[4] = context.NonOwnerVisibilityGroups;  

                                that.setOwnerAccessButtonLabel( add_rec_prefs );

                                window.hWin.HAPI4.save_pref('record-add-defaults', add_rec_prefs);
                                window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE, {origin:'search'});
                            }

                    }});

                    return false;

            }});

            this.div_add_record.controlgroup();

        } // add record button

        // bind click events
        this._on( this.btn_search_as_user, {
            click: function(){
                if(that._use_global_query){
                    that.input_search.val(window.hWin.HEURIST4.current_query_request.q);
                }
                that._doSearch(true);
            }
        });
        /* AAAA */        
        this._on( this.input_search, {
            keypress: function(e){
                let code = (e.keyCode ? e.keyCode : e.which);
                if (code == 13 && !e.shiftKey) { // run search if enter is pressed w/out shift
                    window.hWin.HEURIST4.util.stopEvent(e);
                    e.preventDefault();
                    that._doSearch(true);
                }
            },
            keydown: function(e){
                let code = (e.keyCode ? e.keyCode : e.which);
                if (code == 65 && (e.ctrlKey || e.metaKey)) {
                    e.target.select();
                }
            }
        });

        //-----------------------

        //global listeners
        $(this.document).on(
            window.hWin.HAPI4.Event.ON_CREDENTIALS+' '
            +window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE+' '
            +window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE, function(e, data) {

                if(e.type == window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE){
                    //@todo update btn_select_owner label
                }
                if(!data || data.origin!='search'){
                    that._refresh();
                }
        });

        $(this.document).on(
            window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
            + ' ' + window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            function(e, data) { that._onSearchGlobalListener(e, data) } );


        this.div_search.find('.div-table-cell').css({
            'vertical-align': 'top',
            'padding-left': '5px'
        });

        this._refresh();

    }, //end _create

    //
    // set label for default ownership/access button
    //   
    setOwnerAccessButtonLabel: function( add_rec_prefs ){

        let that = this;

        window.hWin.HAPI4.SystemMgr.usr_names({UGrpID:add_rec_prefs[1]},
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    let ownership = [], title = [], cnt = 0;
                    for(let ugr_id in response.data){
                        if(cnt<1){
                            ownership = response.data[ugr_id];    
                        }
                        title.push(response.data[ugr_id]);
                        cnt++;
                    }
                    if(cnt>1){
                        ownership = cnt + ' groups'; 
                        title = 'Default owners: '+title.join(', ')+'. ';
                    }else{
                        title = '';
                    }

                    let access = {hidden:'Owner only', viewable:'Logged-in', pending:'Public pending', public:'Public'};
                    if(add_rec_prefs[4]){
                        access = 'Groups';
                        title = title + 'Viewable for '+(add_rec_prefs[4].split(',').length)+' groups';
                    }else{
                        access = access[add_rec_prefs[2]];
                    }

                    that.btn_select_owner.button({'label':
                        '<div style="text-align:left;display:inline-block" title="'+title+'">'
                        +ownership+'<br>'+access+'</div>'});
                }
        });

    },

    //
    // help text inside input field
    //
    _showhide_input_prompt:function(event) {

        if(this.input_search.val()==''){
            this.input_search_prompt.show();    
            this.input_search_prompt2.css({'visibility':'hidden'});//hide();    
        }else{
            this.input_search_prompt.hide();     
        }

        if(this._use_global_query && event){
            // revert only if the query has been changed
            this._use_global_query = false;

            this.btn_search_as_user.attr('title', window.hWin.HR('filter_start_hint'));
            this.btn_search_as_user.button('option', 'label', window.hWin.HR(this.options.search_button_label));
        }
    },

    /* EXPERIMENTAL
    _initPagination: function(){
    this.div_paginator = $('<span>')
    .css('display', 'inline-block')
    .appendTo( this.div_search )
    .pagination();
    },
    */

    _setOption: function( key, value ) {
        this._super( key, value );

        if(key=='search_domain'){
            this._refresh();
        }
    },

    /* private function */
    _refresh: function(){

        if(window.hWin.HAPI4.has_access()){
            $(this.element).find('.logged-in-only').show();
            $(this.element).find('.div-table-cell.logged-in-only').css({'display':'table-cell'});

            if(this.options.is_h6style){
                $(this.element).find('.div-table-cell:visible').css({'display':'table-cell'});
            }

            $(this.element).find('.logged-out-only').hide();
        }else{
            $(this.element).find('.logged-in-only').hide();
           
           

            $(this.element).find('.logged-out-only').show();
            if(this.options.is_h6style){
                $(this.element).find('.div-table-cell:visible').css({'display':'table-cell'});
            }

        }

        this.input_search_prompt2.height(this.input_search.height()); // adjust veil height

        this.btn_search_as_user.button( "option", "label", window.hWin.HR(this._getSearchDomainLabel(this.options.search_domain)));

        this.btn_search_domain.css('display', (window.hWin.HAPI4.get_prefs('bookmarks_on')=='1')?'inline-block':'none');

        if(this.options.btn_visible_newrecord){

            if(!this.select_rectype_addrec){ //add record selector

                this.select_rectype_addrec = window.hWin.HEURIST4.ui.createRectypeSelect();
                if(this.select_rectype_addrec.hSelect("instance")!=undefined){
                    this.select_rectype_addrec.hSelect( "menuWidget" ).css({'max-height':'450px'});                        
                }

                let that = this;
                this.select_rectype_addrec.hSelect({change: function(event, data){

                    let selval = data.item.value;
                    that.select_rectype_addrec.val(selval);
                    let opt = that.select_rectype_addrec.find('option[value="'+selval+'"]');
                    that.btn_add_record.button({label: 'Add '+opt.text().trim()});

                    let prefs = window.hWin.HAPI4.get_prefs('record-add-defaults');
                    if(!Array.isArray(prefs) || prefs.length<4){
                        prefs = [selval, 0, 'viewable', '']; //default to everyone   window.hWin.HAPI4.currentUser['ugr_ID']
                    }else{
                        prefs[0] = selval; 
                    }
                    window.hWin.HAPI4.save_pref('record-add-defaults', prefs);

                    window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE, {origin:'search'});

                    window.hWin.HEURIST4.ui.openRecordEdit(-1, null, {new_record_params:{RecTypeID:selval}});
                    return false;
                    }
                });
                this.select_rectype_addrec.hSelect('hideOnMouseLeave', this.btn_select_rt);

            }

            let add_rec_prefs = window.hWin.HAPI4.get_prefs('record-add-defaults');
            if(!Array.isArray(add_rec_prefs) || add_rec_prefs.length<4){
                add_rec_prefs = [0, 0, 'viewable', '']; //rt, owner, access, tags  (default to Everyone)
            }
            if(add_rec_prefs.length<4){
                add_rec_prefs.push(''); //visibility groups
            }

            if(add_rec_prefs[0]>0) {
                this.select_rectype_addrec.val(add_rec_prefs[0]); 
                let opt = this.select_rectype_addrec.find('option[value="'+add_rec_prefs[0]+'"]');
                this.btn_add_record.button({label: 'Add '+opt.text()});
            }

            this.setOwnerAccessButtonLabel( add_rec_prefs );

        }

        this._showhide_input_prompt();

        if(!this._is_publication && this.options.is_h6style 
            && this.btn_search_as_user && this.btn_search_as_user.button('instance')){

                let showing_label = true;
                if(this.element.width()<440){
                    this.div_buttons.css('min-width',50);
                    this.div_buttons.find('.btn-aux').width(25);
                    this.div_buttons.find('.btn-aux :nth-child(2)').hide();
                    this.btn_search_as_user.button('option','showLabel',false);
                    this.btn_search_as_user.css('min-width',31);

                    showing_label = false;
                }else{
                   
                    this.div_buttons.find('.btn-aux :nth-child(2)').show();
                    this.div_buttons.find('.btn-aux').width('auto');
                   
                   
                   
                    this.btn_search_as_user.button('option','showLabel',true);
                    this.btn_search_as_user.css('min-width',90);
                }

                if(this.element.width()<201){
                    this.div_buttons.hide();
                }else{
                    this.div_buttons.show();
                }

                if(!this.options.btn_visible_newrecord){

                    let parent_width = this.element.width() * (showing_label ? 0.65 : 0.75);

                    this.input_search.parent().width(parent_width);
                    this.input_search.width(parent_width - 52);
                    this.input_search_prompt2.width(parent_width);
                }

                this.div_buttons.position({
                    my: 'left+10 top+10',
                    at: 'left bottom',
                    of: this.div_search_input
                });

            // Move 'saved filters' dropdown
            if(this.btn_saved_filters && this.btn_saved_filters.length != 0){

                this.btn_saved_filters.position({
                    my: 'right top',
                    at: 'right top',
                    of: this.div_search_input
                });
            }
        }

    },

    //
    //
    //
    _isSameRealm: function(data){
        return (!this.options.search_realm && (!data || window.hWin.HEURIST4.util.isempty(data.search_realm)))
        ||
        (this.options.search_realm && (data && this.options.search_realm==data.search_realm));
    },


    _onSearchGlobalListener: function(e, data){

        let that = this;

        if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART)
        {

            //accept events from the same realm only
            if(!that._isSameRealm(data)) return;

            //data is search query request
            if(data.reset){
                that.input_search.val('');
                that.input_search.trigger('change');
            }else            
                //topids not defined - this is not rules request
                if(window.hWin.HEURIST4.util.isempty(data.topids) && data.apply_rules!==true){

                    //request is from some other widget (outside)
                    if(data.source!=that.element.attr('id')){
                        let qs;
                        if(Array.isArray(data.q)){
                            qs = JSON.stringify(data.q);
                        }else{
                            qs = data.q;
                        }

                        if(!window.hWin.HEURIST4.util.isempty(qs)){

                            if(qs.length<10000){
                                that.input_search.val(qs);
                                that.options.search_domain = data.w;
                                that.query_request = data;
                                that._refresh();
                            }
                            
                            that.input_search_prompt2.css({'visibility':'visible'});
                            
                        }
                    }

                    let is_keep = window.hWin.HAPI4.get_prefs('searchQueryInBrowser');
                    is_keep = (is_keep==1 || is_keep==true || is_keep=='true');

                    if(is_keep && !this.options.search_realm){
                        let qs = window.hWin.HEURIST4.query.composeHeuristQueryFromRequest(data, true);
                        if(qs && qs.length<2000){
                            let s = location.pathname;
                            while (s.substring(0, 2) === '//') s = s.substring(1);

                            window.history.pushState("object or string", "Title", s+'?'+qs );
                        }
                    }

                    that.input_search.trigger('change');

                }


           
        }else 
            if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){ //search completed

                //accept events from the same realm only
                if(!that._isSameRealm(data)) return;

                window.hWin.HEURIST4.util.setDisabled(this.input_search, false);

                if(!this._is_publication && data.showing_subset && !window.hWin.HEURIST4.util.isempty(data.query)){
                    this.input_search.val(data.query).trigger('change');

                    if(window.hWin.HEURIST4?.current_query_request?.q){
                        that._use_global_query = true;

                        // Change filter button labelling to reflect temporary functionality
                        let query = window.hWin.HEURIST4.current_query_request.q.replace('"', '&quot;');
                        this.btn_search_as_user.attr('title', `Re-apply to the previous query:\n${query}`);
                        this.btn_search_as_user.button('option', 'label', 'Reset<br>filter').button('option', 'icon', '');
                    }
                }

                this._setFocus();
                //show if there is resulst
                if(this.btn_search_save){
                    if(window.hWin.HAPI4.currentRecordset && window.hWin.HAPI4.currentRecordset.length()>0) //
                    {
                        this.btn_search_save.show();
                    }else{
                        this.btn_search_save.hide();
                    }
                }

                if(that.btn_save_filter?.is(':visible')){ // 'flash' save filter button

                    that.btn_save_filter.fadeOut(100)
                                        .fadeIn(100)
                                        .effect('highlight', {color: '#4477B9'}, 1000);
                }
            }else 
                if(e.type == window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE){

                    //force recreate rectype selectors
                    if(this.select_rectype_addrec!=null){
                        this.select_rectype_addrec.remove();
                        this.select_rectype_addrec = null;
                        this._refresh();
                    }

                }



    },

    _setFocus: function(){

        if(this.input_search.is(':visible')) {
                this.input_search.trigger('focus');
        }

    },


    /*
    _handleKeyPress: function(e){
    let code = (e.keyCode ? e.keyCode : e.which);
    if (code == 13) {
    this._doSearch();
    }
    },
    */
    _getSearchDomainLabel: function(value){
        let lbl = null;
        if(value=='b' || value=='bookmark') { lbl = 'Bookmarks'; }
        else if(value=='r') { lbl = 'recently added'; } //not implemented
            else if(value=='s') { lbl = 'recently selected'; } //not implemented
                else if(value=='c') { lbl = 'Search (in current)'; } //todo
                    else { lbl = this.options.search_button_label; this.options.search_domain='a';}
        return lbl;
    },

    //
    // search from input - query is defined manually
    //
    _doSearch: function(fl_btn){

        let qsearch = this.input_search.val();

        qsearch = qsearch.replace(/,\s*$/, "");

        if ( qsearch ) {

            // q - query string
            // w  all|bookmark
            // stype  key|all   - key-search tags, all-title and pointer record title, by default rec_Title

            window.hWin.HAPI4.SystemMgr.user_log('search_Record_direct');

            let request = window.hWin.HEURIST4.query.parseHeuristQuery(qsearch);

            request.w  = this.options.search_domain;
            request.detail = 'ids';
            request.source = this.element.attr('id');
            request.search_realm = this.options.search_realm;
            request.search_page = this.options.search_page;

            this.query_request = request;

            window.hWin.HAPI4.RecordSearch.doSearch( this, request );

        }

    }



    /**
    *  public method
    *
    * @returns {Boolean}
    */
    , showSearchAssistant: function() {

        if(event && (event.detail === 2)){
            //ignore dbl click
            return;
        }

        let that = this;


        if(this.options.is_h6style){
            let widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('slidersMenu');
            if(widget){
                let pos = this.element.offset();
                widget.slidersMenu('show_ExploreMenu', null, 'searchBuilder', {top:pos.top+10, left:pos.left});
            }
        }else{

            if(!window.hWin.HEURIST4.util.isFunction($('body')['showSearchBuilder'])){ //OK! widget script js has been loaded

                let path = window.hWin.HAPI4.baseURL + 'hclient/widgets/search/';
                let scripts = [ path+'searchBuilder.js', 
                    path+'searchBuilderItem.js',
                    path+'searchBuilderSort.js'];
                $.getMultiScripts(scripts)
                .done(function() {
                    showSearchBuilder({ search_realm:that.options.search_realm, 
                                        search_page:that.options.search_page });
                }).fail(function(error) {
                    window.hWin.HEURIST4.msg.showMsg_ScriptFail();
                }).always(function() {
                    // always called, both on success and error
                });

                return;            
            }


            showSearchBuilder({search_realm:that.options.search_realm});
        }
    }

    // events bound via _on are removed automatically
    // revert other modifications here
    ,_destroy: function() {

        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_CREDENTIALS
            +' '+window.hWin.HAPI4.Event.ON_LAYOUT_RESIZE
            +' '+window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE);
        $(this.document).off(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
            + ' ' + window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);

        // remove generated elements
        //this.btn_search_allonly.remove();  // bookamrks search off
        if(this.btn_saved_filters) this.btn_saved_filters.remove();
        this.btn_search_as_user.remove();  // bookamrks search on
        this.btn_search_domain.remove();

        this.menu_search_domain.remove();
        this.input_search.remove();
        this.input_search_prompt.remove();

        this.div_search_as_user.remove();

        if(this.div_paginator) this.div_paginator.remove();

        this.div_search.remove();
    }



});
