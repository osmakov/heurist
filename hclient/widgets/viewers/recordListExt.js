/**
* Integration with existing vsn 3 applications - mapping and smarty reports
* Working with current result set and selection
* External application are loaded in iframe
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
/* global initLinksAndImages */

$.widget( "heurist.recordListExt", {

    // default options
    options: {
        widget_id: null, //outdated: user identificator to find this widget custom js script on web/CMS page
        title: '',

        is_single_selection: false, //work with the only record - reloads content on every selection event
        is_multi_selection: false, //work with all selectd records
        init_show_all: false, //show complete recordset at initialisation

        recordset: null,
        selection: null,  //list of selected record ids
        url:null,               //
        is_frame_based: true,
        is_popup: false, //show popup dialog on every refresh
        popup_position: null,
        
        reload_for_recordset: false, //refresh every time recordset is changed - for smarty report from CMS

        search_page: null, //target page (for CMS)
        search_realm: null,
        search_initial: null,  //Query or svs_ID for initial search
        
        custom_css_for_frame: null,
        record_with_custom_styles: 0, //record id with custom css and style links DT_CMS_CSS and DT_CMS_EXTFILES
        
        onLoadComplete: null,  //callback

        empty_remark: null, //html content for empty message  (search returns empty result)
        placeholder_text: null, //text to display while no record/recordset is loaded  (search is not performed)
        
        show_export_button: false, // show button to export current record set
        show_print_button: false, // show button to print current record set
        export_options: 'all', // export formats allowed

        fontsize: 0, //base font size for renderRecordData otherwise it takes from user preferences
        
        language: 'def'
    },

    _current_url: null, //keeps current url - see loadURL 
    _query_request: null, //keeps current query request
    _events: null,
    _dataTable: null,
    
    _is_publication:false, //this is CMS publication - take css from parent

    placeholder_ele: null, //element holding the placeholder text
    
    export_button: null, // export button
    print_button: null, // print button

    _print_frame: null, // for printing
    
    // the constructor
    _create: function() {

        if(this.options.widget_id){ //outdated
            this.element.attr('data-widgetid', this.options.widget_id);
        }
        
        this._is_publication = window.hWin.HAPI4.is_publish_mode;
        
        let that = this;

        this.div_content = this.element;
        if(this.div_content.parent('.tab_ctrl').length==0 && !this.element.attr('data-widgetid')){
            this.div_content.css({width:'100%', height:'100%'}); 
        }
        
        if(this.options.empty_remark=='def'){
            this.options.empty_remark = window.hWin.HR('resultListExt_empty_remark');
        }
        if(this.options.placeholder_text=='def'){
            this.options.placeholder_text = window.hWin.HR('resultListExt_placeholder_text');
        }
       
        if(this.options.css){
            this.div_content.css( this.options.css );
        }
        if(this.options.fontsize==0){
            if(this.element.css('font-size')){
                this.options.fontsize = parseFloat(this.element.css('font-size'));
            }else if(this.div_content.css('font-size')){
                this.options.fontsize = parseFloat(this.div_content.css('font-size'));
            }
        }
        
        
        if(this.options.is_frame_based){
            this.dosframe = $( "<iframe>" ).css({overflow: 'none !important', width:'100% !important'})
            //.attr('src',window.hWin.HAPI4.baseURL+"common/html/msgNoRecordsSelected.html")
            .appendTo( this.div_content );
        }
        if(this.options.is_popup){
            if(!this.options.popup_width) this.options.popup_width = this.element.css('width');
            if(!this.options.popup_height) this.options.popup_height = this.element.css('height');
            this.element.hide();
        }

        if(this.options.show_print_button){

            this._print_frame = $('<iframe>', {style: 'width:0px;height:0px;'}).appendTo(this.div_content);

            this.print_button = $('<button>', {
                text: window.hWin.HR('Print'), title: window.hWin.HR('Print current results'), 
                class: 'btnPrintRecords', style: `height:25px;float:right;${this.options.show_export_button ? 'margin-right:15px;' : ''}`
            })
            .button({
                icon: 'ui-icon-print',
                showLabel: false
            })
            .prependTo(this.div_content)
            .hide();

            this._on(this.print_button, {
                click: function(){

                    let has_frame = this._print_frame && this._print_frame.length > 0;
                    let has_records = this.options.recordset && this.options.recordset.length() > 0;
                    if(!has_records){
                        return;
                    }

                    let content = null;
                    if(this.options.is_frame_based && this.dosframe.contents().length > 0){

                        let frame = this.dosframe[0].contentWindow;
                        let frame_doc = this.dosframe[0].contentDocument || this.dosframe[0].contentWindow.document;

                        // Check for content to print
                        if(!frame_doc || frame_doc.body.childElementCount == 0){
                            return;
                        }

                        frame.print();

                        return;
                    }

                    content = this.div_content.html();

                    // Check for content to print
                    if(window.hWin.HEURIST4.util.isempty(content)){
                        return;
                    }

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

        if(this.options.show_export_button){

            this.export_button = $('<button>', {
                text: window.hWin.HR('Export'), title: window.hWin.HR('Export current results'), 
                class: 'btnExportRecords ui-button-action', style: 'height:25px;float:right;'
            })
            .button({
                icon: 'ui-icon-download'
            })
            .prependTo(this.div_content)
            .hide();

            this.export_button[0].style.setProperty('color', '#FFF', 'important');

            this._on(this.export_button, {
                click: function(){

                    if(!this.options.recordset || this.options.recordset.length() == 0){
                        window.hWin.HEURIST4.msg.showMsgFlash('No records to export...', 3000);
                        return;
                    }

                    // Set current query and current recordset
                    if(!this._query_request && this.options.selection && this.options.selection.length > 0){
                        window.hWin.HEURIST4.current_query_request = {
                            q: `[{"ids":"${this.options.selection.join(',')}"}]`,
                            w: 'a',
                            db: window.hWin.HAPI4.database,
                            search_realm: this.options.search_realm
                        };
                    }else{
                        window.hWin.HEURIST4.current_query_request = this._query_request;
                    }

                    if(this.options.selection && this.options.selection.length > 0){ // filter complete subset by selected records

                        let records = this.options.recordset.getSubSetByIds(this.options.selection);
                        window.hWin.HAPI4.currentRecordset = records;
                    }else{
                        window.hWin.HAPI4.currentRecordset = this.options.recordset;
                    }

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
            });

        }

        //-----------------------     listener of global events
        this._events = window.hWin.HAPI4.Event.ON_CREDENTIALS 
        + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
        + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
        + ' ' + window.hWin.HAPI4.Event.ON_REC_SELECT;

        $(this.document).on(this._events, function(e, data) {

            if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS)
            {
                if(!window.hWin.HAPI4.has_access()){ //logout
                    that.options.recordset = null;
                    that._refresh();
                }
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){ 

                if(!that._isSameRealm(data)) return;
                
                that.options.recordset = data.recordset; //HRecordSet

                that._run_initial = true;

                that._refresh();
                that.loadanimation(false);

            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){

                if(!that._isSameRealm(data)) return;
                
                if(data && !data.reset){
                    that.updateDataset( jQuery.extend(true, {}, data) ); //keep current query request (clone)
                }

                if(data && data.facet_value){
                    that._facet_value = data.facet_value;
                }

            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){

                //selection happened somewhere else
                if((that.options.is_single_selection || that.options.is_multi_selection) && that._isSameRealm(data) && data.source!=that.element.attr('id')){
                    if(data.reset){
                       
                        that.options.selection = null;
                    }else{
                        let sel = window.hWin.HAPI4.getSelection(data.selection, true); //get ids
                        that.options.selection = sel;
                       
                    }
                    
                    let smarty_template = window.hWin.HAPI4.get_prefs_def('main_recview', 'default'); // default = standard record viewer
                    if(window.hWin.HEURIST4.util.isArrayNotEmpty(that.options.selection) && that.options['url'] 
                        && that.options['url'].indexOf('renderRecordData') != -1 && smarty_template != 'default'){

                        let recIDs_list = that.options.selection;

                        if(recIDs_list.length>0){
                            let recID = recIDs_list[recIDs_list.length-1];

                            // check if the custom report exists
                            window.hWin.HAPI4.SystemMgr.reportAction({action:'check', template:smarty_template}, 
                                function(response){
                                    if(response?.data == 'exist'){
                                        
                                        let newurl = '?template=' + encodeURIComponent(smarty_template)
                                            + '&q=ids:' + recID
                                            + '&db=' + window.hWin.HAPI4.database;
                                            
                                        newurl = that._assignLang(newurl, that.options.language);
                                        
                                        if(that._current_url != newurl){    
                                            that.loadURL(newurl);
                                        }
                                        
                                    }else{
                                        
                                        let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                                            "You have specified a custom report format '"+ smarty_template.slice(0, -4) +"' to use in this view,<br>"
                                            + "however this format no longer exists.<br><br>Please go to Design > My preferences to choose a new format.", 
                                            null,
                                            {ok: 'Close', title: 'Custom format unavailable'},
                                            {default_palette_class: 'ui-heurist-explore'}
                                        );

                                        that._refresh(); // display normal record view - custom report missing
                                        
                                    }
                                    //window.hWin.HEURIST4.msg.showMsgErr(response);
                            });

                        }
                    }else{
                        that._refresh();
                    }
                    
                }
            }
           
        });

       

        this.element.on("myOnShowEvent", function(event){
            if( event.target.id == that.element.attr('id')){
                that._refresh();
            }
        });
            
        if(this.options.is_frame_based){
            this.dosframe.on('load', function(){
                that.onLoadComplete();
            });
        }
        
        if(this.options.search_initial){
            this.doSearch( this.options.search_initial );
            this.options.search_initial = null;
        }        
        
        if(!window.hWin.HEURIST4.util.isempty(this.options.placeholder_text)
        || !window.hWin.HEURIST4.util.isempty(this.options.empty_remark)){
            this.placeholder_ele = $('<div>')
                .css({'white-space': 'pre-wrap', 'padding-top': '20px'})
                .prependTo(this.element)
                .html(window.hWin.HEURIST4.util.isempty(this.options.empty_remark)
                    ?this.options.placeholder_text:this.options.empty_remark);
        }

        // Force single selection for normal record viewer
        if(!window.hWin.HEURIST4.util.isempty(this.options.url) && this.options.url.indexOf('renderRecordData.php') != -1){
            this.options.is_single_selection = true;
        }
        
    }, //end _create

    //
    // Used when it reloads contents for every selection or for every update of recordset
    // see reload_for_recordset and is_single_selection
    //
    loadURL: function( newurl ){
        
        let that = this;

        this._current_url = newurl;
        this.loadanimation(true);

        if(this._print_frame){
            this._print_frame.attr('src', '');
        }
        
        if(this.options.is_frame_based){
            this.dosframe.attr('src', newurl).show();
        }else{
            this.div_content.load(newurl, function(){ that.onLoadComplete(); })
        }
        
    },
    
    //
    // Callback event listener on load of content into div_content or iframe
    //
    onLoadComplete: function(){
        this.loadanimation(false);
        if(!this.options.reload_for_recordset && this.options.is_frame_based && !this.options.is_single_selection && !this.options.is_multi_selection){
              this._refresh();
        }
        
        if(window.hWin.HEURIST4.util.isFunction(this.options.onLoadComplete)){
            this.options.onLoadComplete.call(this);
        }
        
        //add custom css to iframe  besides see cssid parameter
        if(this.options.is_frame_based && this.options.custom_css_for_frame){
            
            let fdoc = this.dosframe[0].contentWindow.document;
            
            let style = document.createElement('style');
            style.type = 'text/css'; 
            style.innerHTML = this.options.custom_css_for_frame;
            fdoc.getElementsByTagName('head')[0].appendChild(style);
            
        }
        
        if(this.options.is_frame_based){                
            
            let fdoc = this.dosframe[0].contentWindow.document;
            let smarty_template = window.hWin.HEURIST4.util.getUrlParameter('template', this.options.url);
            
            if(this._is_publication && window.hWin.HEURIST4.util.isFunction(initLinksAndImages))
            {
                //init "a href" for CMS pages
                if(!window.hWin.HEURIST4){
                    let script = document.createElement('script');
                    script.type = 'text/javascript';
                    script.src = window.hWin.HAPI4.baseURL + 'hclient/core/detectHeurist.js';
                    fdoc.getElementsByTagName('head')[0].appendChild(script);
                }
                
                initLinksAndImages($(fdoc.body), {
                        search_page: this.options.search_page, 
                        search_realm: this.options.search_realm,
                        smarty_template: smarty_template
                });
            }else if(smarty_template){
                
                $(fdoc.body).find('a').each(function(i,link){
                    let href = $(link).attr('href');
                    if ((href && href.indexOf('q=')===0) || $(link).attr('data-query')) 
                    {
                        let query = $(link).attr('data-query')
                                ? $(link).attr('data-query')
                                : href.substring(2);
                        
                        let request = {detail:'ids', neadall:1, w:'a', q:query};
                        if(this.options.search_realm) request['search_realm'] = this.options.search_realm;
                        
                        if(!href || href=='#' || href.indexOf('q=')===0){
                            //need for right click - open link in new tab
                            //href = '/' + window.hWin.HAPI4.database+'/tpl/'+smarty_template+'/'+encodeURIComponent(query);
                            href = window.hWin.HEURIST4.ui.getTemplateLink(smarty_template, query);
                            $(link).attr('href', href);
                        }
                                    
                        $(link).on('click', function(event){
                            window.hWin.HEURIST4.util.stopEvent(event);
                            window.hWin.HAPI4.RecordSearch.doSearch(window.hWin,request);
                            return false;
                        });

                        
                        
                    }
                    if (!window.hWin.HEURIST4.util.isempty(href) && href!='#' && (href.indexOf('./')==0 || href.indexOf('/')==0)){ //relative path
                            href = window.hWin.HAPI4.baseURL + href.substring(href.indexOf('/')==0?1:2);
                            $(link).attr('href',href);
                    }
                    
                });
            }

            // Toggle display of buttons
            let hasContent = !window.hWin.HEURIST4.util.isempty(this.dosframe.attr('src')) || fdoc.body.childElementCount > 0;
            let hasRecords = this.options.recordset && this.options.recordset.length() > 0;

            this.dosframe.css('height', '');
            let new_height = this.element.height() - 25;

            if(this.export_button){
                // Allow print if there is content to print
                if(!hasRecords){
                    this.export_button.hide();
                }else{
                    this.export_button.show();
                    this.dosframe.css('height', `${new_height}px`);
                }
            }

            if(this.print_button){
                // Allow export if there are records
                if(!hasContent){ //!hasRecords && 
                    this.print_button.hide();
                }else{
                    this.print_button.show().css('margin-right', `${this.export_button && this.export_button.is(':visible') ? '15px' : ''}`);
                    this.dosframe.css('height', `${new_height}px`);
                }
            }
        }
        
    },
    
    // 
    // Execute search and update dataset independently
    //
    doSearch: function(query){
        let request = {q:query, w: 'a', detail: 'ids', 
                        source: 'init', search_realm: this.options.search_realm };
        window.hWin.HAPI4.RecordSearch.doSearch(this.document, request);
    },
    
    //
    // refresh 
    //
    updateDataset: function(request){
        this._query_request = request;
        this.options.selection = null;
        this.options.recordset = null;
        if(request.q!=''){
            this.loadanimation(true);
        }
        this._refresh();
    },
    
    //
    //
    //
    _isSameRealm: function(data){
        return (!this.options.search_realm && (!data || window.hWin.HEURIST4.util.isempty(data.search_realm)))
        ||
        (this.options.search_realm && (data && this.options.search_realm==data.search_realm));
    },


    _setOptions: function() {
        // _super and _superApply handle keeping the right this-context
        this._superApply( arguments );
       
    },
    
    _setOption:function(key, value){
        if(key == 'selection'){
            this.options.selection = value;
            this._refresh();
        }else{
            this._superApply( arguments );
        }
    },

    /* private function */
    _refresh: function(){

        if(this.options.title!=''){
            let id = this.element.attr('id');
            $(".header"+id).html(this.options.title);
            $('a[href="#'+id+'"]').html(this.options.title);
        }

        if(this.placeholder_ele != null){
            this.placeholder_ele.hide();
           
        }

        //refesh if element is visible only - otherwise it costs much resources
        if(  (!this.element.is(':visible') && !this._is_publication) 
            || window.hWin.HEURIST4.util.isempty(this.options.url)){
            return;  
        }

        let empty_results = this.options.recordset==null || this.options.recordset.length()==0;
        let content_updated = false;

        if(this.options.is_single_selection || this.options.is_multi_selection){ //reload content on every selection event

            let newurl = null;
            let show_all = this._run_initial && this.options.init_show_all && !empty_results;
            this._run_initial = false;

            if(window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.selection) || show_all){

                let recIDs_list = !show_all ? this.options.selection : this.options.recordset.getIds().join(',');

                if(recIDs_list.length>0){
                    
                    let recID = recIDs_list;
                    if(!show_all){
                        recID = this.options.is_single_selection ? recIDs_list[recIDs_list.length-1] : recIDs_list.join(',');
                    }
                    
                    newurl = this.options.url;
                    
                    if(newurl.indexOf('[recID]')>0){
                        newurl = newurl.replace("[recID]", recID);
                    }else{
                        newurl = newurl.replace("[query]", ('q=ids:'+recID));
                    }
                    if(newurl.indexOf('[dbname]')>0){
                        newurl = newurl.replace('[dbname]', window.hWin.HAPI4.database);
                    }else if(newurl.indexOf('db=')<0){
                        newurl = newurl + '&db=' + window.hWin.HAPI4.database;    
                    }
                    
                    
                    newurl = this._assignLang(newurl, this.options.language);
                    
                    if(!window.hWin.HEURIST4.util.isempty(this._facet_value)){
                        newurl += `&facet_val=${this._facet_value}`;
                    }
                    
                    if(this.options.record_with_custom_styles){
                        newurl = newurl + '&cssid=' + this.options.record_with_custom_styles;
                    }
                    if(this.options.fontsize>0){
                        newurl = newurl + '&fontsize=' + this.options.fontsize;
                    }
                }
            }
            if(newurl==null){

                this._current_url = null;
                
                if(!window.hWin.HEURIST4.util.isempty(this.options.placeholder_text)){
                    if(this.placeholder_ele != null) {
                        this.placeholder_ele.html(this.options.placeholder_text).show();   
                    }
                    if(this.options.is_frame_based){
                        this.dosframe.attr('src', null).hide();
                    }else{
                        this.div_content.empty();
                    }
                }else if(!this.options.blank_placeholder){
                    
                    if(this.options.is_frame_based){
                        this.dosframe.attr('src', window.hWin.HRes('recordSelectMsg'));
                    }else{
                        this.div_content.load(window.hWin.HRes('recordSelectMsg'));
                    }
                }
            }else{
                newurl = window.hWin.HAPI4.baseURL +  newurl;

                if(this._current_url!==newurl){
                    this.loadURL(newurl);
                    content_updated = true;
                }
            }

        }else 
        if(!this.options.reload_for_recordset && this._current_url!==this.options.url){

            this.options.url = window.hWin.HAPI4.baseURL +  this.options.url.replace("[dbname]",  window.hWin.HAPI4.database);

            this.options.url = this._assignLang(this.options.url, this.options.language);
            
            this.loadURL( this.options.url );
            
            content_updated = true;

        }else if(empty_results && this.placeholder_ele!=null){

            if(!window.hWin.HEURIST4.util.isempty(this.options.empty_remark)){
                this.placeholder_ele.html(this.options.empty_remark);
            }

            if(this.options.is_frame_based){
                this.dosframe.hide();
            }else{
                this.div_content.empty();
            }

            this.placeholder_ele.show();

            content_updated = true;

        }else{ //content has been loaded already ===============================

            let query_string_all = null,
            query_string_sel = null,
            query_string_main = window.hWin.HEURIST4.query.composeHeuristQueryFromRequest( this._query_request, true );

            if(this.options.reload_for_recordset){ //reloads content entirely

                if(this.options.show_all && !empty_results && query_string_main.indexOf('q=') === -1){
                    query_string_main = `q=ids:${this.options.recordset.getIds().join(',')}&${query_string_main}`;
                }

                let newurl = window.hWin.HAPI4.baseURL +  this.options.url.replace("[query]", query_string_main);
                
                if(this.options.record_with_custom_styles){ //to load custom css and style links
                    newurl = newurl + '&cssid=' + this.options.record_with_custom_styles;
                }
                
                newurl = this._assignLang(newurl, this.options.language);
                
                this.loadURL( newurl );
                content_updated = true;
                return;    
            }
            

            if (window.hWin.HEURIST4.util.isArrayNotEmpty(this.options.selection)) {
                let recIDs_list = this.options.selection;
                if(!window.hWin.HEURIST4.util.isempty(recIDs_list.length)){
                    //NOT USED
                    query_string_sel = 'db=' + window.hWin.HAPI4.database
                    + '&w=' + window.hWin.HEURIST4.util.isnull(this._query_request)?this._query_request.w:'all'
                    + '&q=ids:'+recIDs_list.join(',');
                }
            }

            if(this.options.is_frame_based){
                //there is heurist apps in iframe - smarty report, crosstabs analysis or mapping

                this.dosframe.show();

                if (this.dosframe[0].contentWindow.crosstabsAnalysis) {
                    
                    if (this.options.recordset!=null){
                        this._checkRecordsetLengthAndRunCrosstabsAnalysis(6000, query_string_main);
                    }
                    
                }else{
                    let showMap = this.dosframe[0].contentWindow.showMap;
                    if(showMap){ //not used anymore
                        showMap.processMap();
                    }else if(this.dosframe[0].contentWindow.updateRuleBuilder && this.options.recordset) {

                        //todo - swtich to event trigger????
                        this.dosframe[0].contentWindow.updateRuleBuilder(this.options.recordset.getRectypes(), this._query_request);
                    }
                }
            }
            
            this.loadanimation(false);
        }
        
        if(this.options.is_popup && content_updated){
            if(this.reportPopupDlg && this.reportPopupDlg.dialog('instance')){
                this.reportPopupDlg.dialog('open');
            }else{
                this.element.width()
                
                let opts = {
                    window:  window.hWin, //opener is top most heurist window
                    title: window.hWin.HR('Record Info'),
                    width: this.options.popup_width,
                    height: this.options.popup_height,
                    modal: false,
                    element: this.element[0],
                    resizable: true
                    //h6style_class: 'ui-heurist-publish'
                    //buttons: codeEditorBtns,
                    //default_palette_class: 'ui-heurist-publish'
                    //close: function(){}
                };   
                if(this.options.popup_position){
                    opts.position = { my: "center center", at: "center center", of: $(document) };                    
                    if(this.options.popup_position=='left' || this.options.popup_position=='right'){
                        opts.position.my = this.options.popup_position+' center';    
                    }else{
                        opts.position.my = 'center '+this.options.popup_position;    
                    }
                    opts.position.at = opts.position.my;
                }
                
                this.reportPopupDlg = window.hWin.HEURIST4.msg.showElementAsDialog(opts);
                
                this.element.parent().css('padding',0);
                this.element.width('100%');
                this.element.height('100%');
                
            }         
        }        

    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {

        this.element.off("myOnShowEvent");
        $(this.document).off(this._events);

        let that = this;
        
        if(this.reportPopupDlg){
            if(this.reportPopupDlg.dialog('instance')){
                this.reportPopupDlg.dialog('close');
            }
            this.reportPopupDlg.remove();
        }

        // remove generated elements
        if(this.dosframe) this.dosframe.remove();
        this.div_content.remove();
    },
    
    //
    //
    //
    _assignLang: function (newurl, lang){
        if(lang && lang!='def'){
            if(newurl.indexOf('[lang]')>0){
                newurl = newurl.replace('[lang]', lang);
            }else if(newurl.indexOf('lang=')<0){
                newurl = newurl + '&lang=' + lang;    
            }
        }
        return newurl;
    },

    loadanimation: function(show){

        if(show){
            this.div_content.css('background','url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center');
        }else{
            this.div_content.css('background','none');
        }
    },

    //
    //
    //
    _checkRecordsetLengthAndRunCrosstabsAnalysis: function(limit, query_string_main){
/* @todo */
        if(!this.options.is_frame_based) return;
        
        let crosstabs = this.dosframe[0].contentWindow.crosstabsAnalysis;
        if(!crosstabs) return;

        let recordset, recIDs_list = [];

        if (this.options.recordset!=null) {

            let tot_cnt = this.options.recordset.length();
            window.hWin.HEURIST4.totalQueryResultRecordCount = tot_cnt;

            recIDs_list = this.options.recordset.getIds();//limit

            let rectype_first = 0;
            if(recIDs_list.length>0){
                let rec = this.options.recordset.getFirstRecord();
                rectype_first = this.options.recordset.fld(rec, 'rec_RecTypeID');
            }

            recordset = {
                resultCount: tot_cnt, recordCount: recIDs_list.length,
                query_main:  query_string_main,
                recIDs: recIDs_list.join(','), recTypes: this.options.recordset.getRectypes(),
                first_rt: rectype_first
            };

        }else{
            window.hWin.HEURIST4.totalQueryResultRecordCount = 0;
            
            recordset = {resultCount: 0, recordCount: 0, recIDs: "", recTypes: ""};
        }

        crosstabs.assignRecordset(recordset);

    }
    

});
