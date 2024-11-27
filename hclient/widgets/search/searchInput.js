/**
* Simplified Search input form with possible preliminary filter
* This widget is not used in main interface. It is listed aming available widgets in CMS
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

$.widget( "heurist.searchInput", {

    // default options
    options: {
        _is_publication: false,

        sup_filter: null, //additional filter
        
        search_domain: 'a', //current search domain all|bookmark|recently added|recently selected  or a|b|r|s
        search_domain_set: null, // comma separated list of allowed domains  a,b,c,r,s

        search_button_label: '',
        search_input_label: '',
        placeholder_text: 'def', // textarea placeholder text
        show_search_assistant: true,
        
        button_class: 'ui-heurist-btn-header1',
        
        preliminary_filter: null,
        suppress_default_search: false,
        
        // callbacks
        onsearch: null,  //on start search
        onresult: null,   //on search result
        
        search_page: null, //target page (for CMS) - it will navigate to this page and pass search results to search_realm group
        search_realm:  null,  //accepts search/selection events from elements of the same realm only

        update_on_external_search: false, // update search box value on ON_REC_SEARCHSTART from facet/other filters

        append_all_to_search: false // prepend all: to search
    },

    query_request:null,

    _is_publication:false, //this is CMS publication - take css from parent
    
    // the constructor
    _create: function() {
        
        let that = this;
        
        this._is_publication = window.hWin.HAPI4.is_publish_mode;
        
        if(this._is_publication){
            this.options.button_class = '';
            //this is CMS publication - take bg from parent
            this.element.addClass('ui-widget-content').css({'background':'none','border':'none'});
        }else if(!this.options.is_h6style){
            this.element.css({height:'100%','font-size':'0.8em'});
            this.element.addClass('ui-widget-content');
        }else {
            this.options.button_class = '';
        }

        if(this.options.placeholder_text == 'def'){
            this.options.placeholder_text = window.hWin.HR('filter_placeholder');
        }
        
        //------------------------------------------- filter inputs                        

        //1> Search functions container
        this.div_search   = $('<div>')
                .css({ 'width':'100%', display: 'flex' })
                .appendTo( this.element ); 
        
        //header-label
        this.div_search_header = $('<div>')
        .css({'width':'auto', flex: '0 1 50px', 'text-align':'right'})
        .appendTo( this.div_search );
        
        // Search field
        this.div_search_input = $('<div>')
        .css({'width':'auto', flex: '2 1 200px', 'text-align':'right'}) 
        .appendTo( this.div_search );

        this.input_search = $( "<textarea>" )
        .css({//'margin-right':'0.2em', 
            'height':'27px', 'width':'99%' , 
            'max-height':'70px', 
			'resize':'none', 
            'min-height':'27px', 'line-height': '14px', 
            'min-width':'80px' }) 
        .attr('placeholder', this.options.placeholder_text)   
        .addClass("text ui-widget-content ui-corner-all")
        .appendTo(  this.div_search_input );
        
        //
        // quick filter builder buttons
        //
        this.div_buttons = $('<div>')
            .css({'text-align': 'center', flex: '0 0 35px'})
            .appendTo( this.div_search );
        
        let linkGear = $('<a>',{href:'#', 
            title:window.hWin.HR('filter_builder_hint')})
            .css({'display':'inline-block','opacity':'0.5','margin-top': '0.6em', width:'20px'})
            .addClass('ui-icon ui-icon-magnify-explore') //was ui-icon-gear was ui-icon-filter-form
            .appendTo(this.div_buttons);
        this._on( linkGear, {  click: this.showSearchAssistant });
        
        //
        // search/filter buttons - may be Search or Bookmarks according to settings and whether logged in
        //
        this.div_search_as_user = $('<div>') //.css({'min-width':'18em','padding-right': '10px'})
        .css({'text-align':'right','min-width':'40px'})  //flex: '0 1 90px',
        .appendTo( this.div_search );

        this.btn_start_search = $( "<button>", {
            label: window.hWin.HRJ('search_button_label', this.options, this.options.language), 
            title: window.hWin.HR('filter_start_hint')
        })
        .css({'min-height':'30px','width':'100%'})
        .appendTo( this.div_search_as_user )
        .addClass(this.options.button_class+' ui-button-action')
        .button({showLabel:true, icon:this._is_publication?'ui-icon-search':'ui-icon-filter'});


        // bind click events
        this._on( this.btn_start_search, {
            click:  function(){
               
                that._doSearch();}
        });
   
        this._on( this.input_search, {
            keypress: function(e){
                let code = (e.keyCode ? e.keyCode : e.which);
                if (code == 13) {
                    window.hWin.HEURIST4.util.stopEvent(e);
                    e.preventDefault();
                    that._doSearch();
                }
            },
            keydown: function(e){
                let code = (e.keyCode ? e.keyCode : e.which);
                if (code == 65 && e.ctrlKey) {
                    e.target.select();
                }
            }
        });
        
        
        $(this.document).on(
            window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH, 
            function(e, data) { 
                that._onSearchGlobalListener(e, data) 
            } );
        

        this._refresh();
        
        if(!window.hWin.HEURIST4.util.isempty(that.options.preliminary_filter)){
            setTimeout(function(){
                    that.input_search.val(that.options.preliminary_filter);
                    that._doSearch();
            },1000);
        }

    }, //end _create

   /* private function */
   _refresh: function(){
       
       this.btn_start_search.button('option','label', window.hWin.HRJ('search_button_label', this.options, this.options.language));

       let lbl = null;
       if(this.options.search_input_label){
            lbl = window.hWin.HRJ('search_input_label', this.options, this.options.language);
       }
       if(lbl) {
            this.div_search_header.show();
            this.div_search_header.text( lbl ).css({'padding-right':'4px'});     
       }else{
            this.div_search_header.hide();
       }
       
        
       if(this.options.show_search_assistant) {
            this.div_buttons.show();
       }else{
            this.div_buttons.hide();
       }
       
   },
  
   _setFocus: function(){
      
        if(this.input_search.is(':visible')) {
                try{
                    this.input_search.trigger('focus');
                }catch(e){
                    /* continue regardless of error */
                }
        }

   },


   //
   // search from input - query is defined manually
   //
   _doSearch: function(){

        let qsearch = this.input_search.val();
        
        qsearch = qsearch.replace(/,\s*$/, "");

        if(this.options.append_all_to_search && !window.hWin.HEURIST4.util.isJSON(qsearch) && qsearch.indexOf(":") === -1){
            // force search all fields

            if(window.hWin.HEURIST4.util.isempty(qsearch)){ // do nothing
                return;
            }

            qsearch = `all:"${qsearch}"`;
        }

        if ( qsearch ) {

            // q - query string
            // w  all|bookmark
            // stype  key|all   - key-search tags, all-title and pointer record title, by default rec_Title

            let that = this;

            if(this.options.sup_filter){
                qsearch = window.hWin.HEURIST4.query.mergeHeuristQuery(this.options.sup_filter, qsearch);    
            }
            
            window.hWin.HAPI4.SystemMgr.user_log('search_Record_direct');
            
            let request = {}; 

            request.q = qsearch;
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
            
            if(!window.hWin.HEURIST4.util.isFunction($('body')['showSearchBuilder'])){ 
            
                let path = window.hWin.HAPI4.baseURL + 'hclient/widgets/search/';
                let scripts = [ path+'searchBuilder.js', 
                                path+'searchBuilderItem.js',
                                path+'searchBuilderSort.js'];
                $.getMultiScripts(scripts)
                .done(function() {  //OK! widget script js has been loaded
                    showSearchBuilder({ search_realm:that.options.search_realm, 
                                        search_page:that.options.search_page});
                }).fail(function(error) {
                    window.hWin.HEURIST4.msg.showMsg_ScriptFail();
                }).always(function() {
                    // always called, both on success and error
                });
            
                return;            
            }

            
            showSearchBuilder({search_realm:that.options.search_realm,
                               search_page:that.options.search_page});
        }
        
    }
 

    // events bound via _on are removed automatically
    // revert other modifications here
    ,_destroy: function() {

       
        this.btn_start_search.remove();  // bookamrks search on
        this.input_search.remove();
        
        this.div_search_as_user.remove();
        this.div_search.remove();
        
        
        $(this.document).off(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
          + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH);
        
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

        if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART) {

            //accept events from the same realm only
            if(!that._isSameRealm(data)) return;

            //data is search query request
            if(data.reset){
               that.input_search.val('');
               that.input_search.trigger('change');
            }
            else if(window.hWin.HEURIST4.util.isempty(data.topids) && data.apply_rules!==true){ //topids not defined - this is not rules request

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

                            if(this.options.update_on_external_search == true){
                                that.input_search.val(qs);
                            }
                           
                            that.query_request = data;
                            that._refresh();
                        }
                    }
                }

                that.input_search.trigger('change');
            }
        }
        else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){ //search completed

            //accept events from the same realm only
            if(!that._isSameRealm(data)) return;

            window.hWin.HEURIST4.util.setDisabled(this.input_search, false);

            this._setFocus();
        }
    }


});
