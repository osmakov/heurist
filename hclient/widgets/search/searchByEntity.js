/**
* Filter by enity (record type)
* It takes entity id either from "by usage" list or from pre-selected list of record types
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


$.widget( "heurist.searchByEntity", {

    // default options
    options: {
        is_publication: false,
        use_combined_select: false,  // component is combination of 3 selectors (otherwise favorires are buttons)
        
        by_favorites: true,  // show buttons: filter by entity (show selected (favorires) entities)
        by_usage: true, // show dropdown entity filter (by usage)
        
        search_realm: null,
        
        menu_locked: null // callback to prevent close in h6
    },

    selected_rty_ids:[], //

    combined_select: null, // if use_combined_select  usage>,config>,list of favorites
    
    config_btn: null, // button to open config selector
    config_select: null, //configuration selector
    config_select_options: null,
    
    usage_btn: null, //by usage selector
    usage_select: null, 
    usage_select_options: null,
    
    _waiting_server_response: false,

    // the constructor
    _create: function() {
        
        let that = this;
        
        if(this.options.is_publication){
            
            this.options.button_class = '';
            //this is CMS publication - take bg from parent
            this.element.addClass('ui-widget-content').css({'background':'none','border':'none'});
        }else{
            this.element.addClass('ui-widget-content');
        }
        
        this.config_select_orig = $('<select>').appendTo(this.element).hide();
        this.config_select_orig = this.config_select_orig[0];
        
        //configuration - select favorites
        if(this.options.use_combined_select){
            
            this.combined_select = $('<div class="ui-heurist-header" style="top:0px;">'+window.hWin.HR('Filter by entity')+'</div>'
                +'<div style="top:37px;position:absolute;width:100%">' 
                    +'<div class="ui-heurist-title favorites" style="width: 100%;padding:12px 0px 0px 6px;">'
                                +window.hWin.HR('Favorites')+'</div>'
                    +'<ul class="by-selected" style="list-style-type:none;margin:0;padding:6px"></ul>'
                    +'<div class="ui-heurist-title" style="width: 100%;border-top:1px gray solid; padding:12px 0px 0px 6px;">'
                                +window.hWin.HR('By Usage')+'</div>'
                    +'<ul class="by-usage" style="list-style-type:none;margin:0;padding:6px"></ul>'
                    )
                .appendTo(this.element);

            this.config_btn = $('<span>').addClass('ui-icon ui-icon-gear')
                .css({'margin-left':'10px'})   //'font-size':'0.8em',
                .appendTo(this.combined_select.find('div.favorites'));        
                
        }else{
        
            this.element.css({height:'100%','font-size':'0.8em'});

            //------------------------------------------- filter by entities
            this.options.by_favorites = this.options.by_favorites && (window.hWin.HAPI4.get_prefs_def('entity_btn_on','1')=='1');
            
            //container for buttons
            this.div_entity_btns   = $('<div>').addClass('heurist-entity-filter-buttons') //to show on/off in preferences
                                    .css({ 'display':(this.options.is_publication?'none':'block'),
                                        'padding':'10px 20px', //this.options.is_publication?0:('20px 5 20px '+sz_search_padding),
                                        'visibility':this.options.by_favorites?'visible':'hidden',
                                        'height':this.options.by_favorites?'auto':'10px'})
                                    .appendTo( this.element );
            //Main label
            let $d2 = $(`<div><span>${window.hWin.HR('Entities')}</span></div>`).css('float','left');
            
            //quick filter by entity  "by usage" 
            if(this.options.by_usage)
            {
                //Label for search by usage
                this.usage_btn = $('<span title="Show list of entities to filter">'
                +'by usage <span class="ui-icon ui-icon-triangle-1-s"></span></span>')  
                .addClass('graytext')
                .css({'text-decoration':'none','padding':'0 10px','outline':'none','font-weight':'bold','font-size':'1.1em', cursor:'pointer'})
                .appendTo( $d2 ); //was div_search_help_links
        
                //click on label "by usage" - opens selector
                this._on( this.usage_btn, {  click: function(){
                        this._openSelectRectypeFilter( this.usage_select_options );
                        return false;
                } });
            }
        
            $d2.appendTo(this.div_entity_btns);
            
            this.config_btn = $('<button>').button({label: window.hWin.HR("Show list of entities to filter"), 
                      showLabel:false, icon:'ui-icon-gear'})
            .css({'font-size':'0.915em'})
            .appendTo($d2);        
        }        
        //click on button - opens rectype selector with checkboxes
        this._on( this.config_btn, {  click: function(){
                this._openSelectRectypeFilter( this.config_select_options );
                return false;
        } });
            
        
        
        if(this.options.use_combined_select || this.options.by_usage){
            
                this.usage_select_options = {select_name:'usage_select', 
                        useIcons: true, useCounts:true, useGroups:false, 
                        ancor:this.usage_btn, 
                        onselect: function __onSelectRectypeFilter(event, data){
                                       let selval = data.item.value;
                                       if(selval>0){
                                           that._doSearch(selval);
                                       }
                                       return false;
                                   }};            
        }                
        
        //selector with checkboxes to select filter by entity buttons
        this.config_select_options = {select_name:'config_select', 
                useIcons: true, useCounts:true, useGroups:true, useCheckboxes:true, 
                ancor: this.config_btn, 
                marked: this.selected_rty_ids,
                showAllRectypes: true, 
                onmarker: function (ele){
                    let is_checked = !ele.hasClass('ui-icon-check-on');
                    let rty_ID = ele.attr('data-id');
                    
                    ele.removeClass('ui-icon-check-'+(is_checked?'off':'on'))
                        .addClass('ui-icon-check-'+(is_checked?'on':'off'));                    
                    
                    let idx = window.hWin.HEURIST4.util.findArrayIndex(rty_ID, this.selected_rty_ids);
                    if(is_checked){
                        if(idx<0) this.selected_rty_ids.push(rty_ID);    
                    }else{
                        if(idx>=0) this.selected_rty_ids.splice(idx, 1);    
                    }
                    this._redraw_buttons_by_entity();
                },
                onselect: function __onSelectRectypeFilter(event, data){
                               return false;
                           }};            
        
         
         
        //-----------------------

        //global listeners
            
        window.hWin.HAPI4.addEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            function(data) { 
                if(!data || data.type=='rty'){
                    that.recreateRectypeSelectors();
                }
            });
            
            
        // Refreshing
        this.element.on("myOnShowEvent", function(){ 
            if ($Db.needUpdateRtyCount>=0) {
                    setTimeout(function(){that.refreshOnShow()},500);
            }} );
            
       

        this.recreateRectypeSelectors();
        
        this._refresh();

    }, //end _create
    
    //
    //
    //    
    refreshOnShow: function(){
            if( $Db.needUpdateRtyCount==0 ){
                $Db.needUpdateRtyCount = -1;    
                this.recreateRectypeSelectors();
            }else if( $Db.needUpdateRtyCount>0 && !this._waiting_server_response){
                let that = this;
                
                $Db.needUpdateRtyCount = -1;    
                this._waiting_server_response = true;
                
                $Db.get_record_counts(function(){
                    that._waiting_server_response = false;
                    that.recreateRectypeSelectors();
                });
            }
    },
             
    //
    // recreate list of buttons or recreate combined_select
    //
    _redraw_buttons_by_entity: function(is_init){
        
        let needs_saving = false;
        let that = this;

        if(is_init===true){
            //get selected from preferences
            this.selected_rty_ids = window.hWin.HAPI4.get_prefs_def('entity_filter_btns','');
            
            if(window.hWin.HEURIST4.util.isempty(this.selected_rty_ids)){
                this.selected_rty_ids = [];
                
                    //get 5 from first group
                    let rtgID = $Db.rtg().getOrder()[0];
                    $Db.rty().each2(function(rtyID,rectype){
                        if(rectype['rty_RecTypeGroupID']==rtgID){
                            that.selected_rty_ids.push(rtyID);
                            if(that.selected_rty_ids.length>4) return false;
                        }
                    });
                   /*
                    //get 5 top most used rectypes
                    let sorted = [];
                    $Db.rty().each2(function(rtyID,rectype){
                        sorted.push({ 'id':rty_ID, 'cnt':rectype['rty_RecCount']});
                    });
                    sorted.sort(function(a,b){
                         return Number(a['cnt'])<Number(b['cnt'])?1:-1;
                    });
                    for(let idx=0; idx<sorted.length && idx<5; idx++){
                        this.selected_rty_ids.push(sorted[idx]['id']);    
                    }
                    */
            }else{
                this.selected_rty_ids = this.selected_rty_ids.split(',');    

                for (let i = 0; i < this.selected_rty_ids.length; i++) {
                    const rty_ID = this.selected_rty_ids[i];
                    if(rty_ID <= 0 || $Db.rty(rty_ID) == null){ // invalid rectype, or has been deleted
                        this.selected_rty_ids.splice(i, 1);
                        needs_saving = true;
                    }
                }
            }
            
        }
        

        let container;
        if(this.options.use_combined_select){


                this._off(this.combined_select.find('li[data-id]'), 'click');
                container = this.combined_select.find('.by-usage');
                container.empty();
                
                $.each(this.usage_select.find('option'),function(i, item){
                    item = $(item);
                    $('<li data-id="'+item.attr('entity-id')+'" style="font-size:smaller;padding:4px 0px 2px 0px">'
                        +'<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
                            + '" class="rt-icon" style="vertical-align:bottom;background-image: url(&quot;'+item.attr('icon-url')+ '&quot;);"/>'
                        //+'<img src="'+item.attr('icon-url')+'"/>'
                        +'<div class="menu-text truncate" style="max-width:80%;display:inline-block;">'
                        +item.text()+'</div>'
                        +'<span style="float:right;min-width:20px">'+(item.attr('rt-count')>=0?item.attr('rt-count'):'')+'</span>'
                       +'</li>').appendTo(container);    
                });
            
                container = this.combined_select.find('.by-selected');
                container.empty();
        }else{ 
            this._off( this.div_entity_btns.find('.entity-filter-button'), 'click');
            this.div_entity_btns.find('.entity-filter-button').remove();
        }
        
        
        
        let idx=this.selected_rty_ids.length-1;
        while(idx>=0){
            
            const rty_ID = this.selected_rty_ids[idx];
            
            if(rty_ID>0) {           

                let cnt = $Db.rty(rty_ID,'rty_RecCount');
                if(!(cnt>0)) cnt = 0;    
                
                if(this.options.use_combined_select){
                    
                    $('<li data-id="'+rty_ID+'" style="font-size:smaller;padding:4px 6px 2px 4px">'
                        +'<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
                            + '" class="rt-icon" style="vertical-align:bottom;background-image: url(&quot;'
                            + window.hWin.HAPI4.iconBaseURL + rty_ID+ '&quot;);"/>'
                        +'<div class="menu-text truncate" style="max-width:80%;display:inline-block;">'
                        + $Db.rty(rty_ID,'rty_Name')+'</div>'
                        +'<span style="float:right;">'
                        +(cnt>=0?cnt:'')+'</span>'
                       +'</li>').appendTo(container);
                    
                }else{
            
                    $('<div>').button({label:
                    '<img src="'+window.hWin.HAPI4.iconBaseURL + rty_ID + '" height="12">'
                    +'<span class="truncate" style="max-width:100px;display:inline-block;margin-left:8px">'
                            + $Db.rty(rty_ID,'rty_Name') + '</span>'
                            + '<span style="float:right;padding:2px;font-size:0.8em;">['   
                            +  cnt
                            +']</span>'}) 
                        .attr('data-id', rty_ID)
                        .css({'margin-left':'6px','font-size':'0.9em'})        
                        .addClass('entity-filter-button')  // ui-state-active
                        .insertAfter(this.config_btn.parent());
                    
                }
            
            }else{
                //remove wrong(removed) rectypes
                is_init = false;
                this.selected_rty_ids.splice(idx,1);
            }
            idx--;
            
        }//for
        
                
        if(this.options.use_combined_select){
            
            this._on( this.combined_select.find('li[data-id]'), {click: function(e){
                   let selval = $(e.target).is('li')?$(e.target) :$(e.target).parent('li');
                   selval = selval.attr('data-id');
                   if(selval>0){
                       this._doSearch(selval);
                   }
            },
            mouseover: function(e){ 
                let li = $(e.target).is('li')?$(e.target) :$(e.target).parent('li');
                li.addClass('ui-state-active'); },
            mouseout: function(e){ 
                let li = $(e.target).is('li')?$(e.target) :$(e.target).parent('li');
                li.removeClass('ui-state-active'); }
            });
            
        }else{
         
            this._on( this.div_entity_btns.find('div.entity-filter-button'), {  click: function(e){
                   let selval = $(e.target).hasClass('entity-filter-button')
                            ?$(e.target):$(e.target).parent('.entity-filter-button');
                   selval = selval.attr('data-id');
                   if(selval>0){
                       this._doSearch(selval);
                   }
            } });
            
            this.div_entity_btns.sortable({
                //containment: 'parent',
                items: '.entity-filter-button',
                cursor: 'move',
                handle:'img',
                delay: 250,
                axis: 'x',
                stop:function(){
                    that.selected_rty_ids = [];
                    $.each(that.div_entity_btns.find('.entity-filter-button'),function(idx, item){
                      that.selected_rty_ids.push( $(item).attr('data-id') );
                    })
                    window.hWin.HAPI4.save_pref('entity_filter_btns', that.selected_rty_ids.join(','));
                }}
            );
        }
        
        
        if(is_init!==true || needs_saving){
            //save in user preferences
            window.hWin.HAPI4.save_pref('entity_filter_btns', this.selected_rty_ids.join(','));
        }
            
    },

    
    /* private function */
    _refresh: function(){
    },

    //
    // creates selectors usage_select or config_select
    //
    _recreateSelectRectypeFilter: function(opts){
        
            let that = this;

            let select_rectype = opts['select_name'];
            
            opts.useIds = true;
            
            opts.useHtmlSelect = (select_rectype=='usage_select' && that.options.use_combined_select);
            
            this[select_rectype] = window.hWin.HEURIST4.ui.createRectypeSelectNew(
                    (select_rectype=='config_select')?this.config_select_orig:null, opts);
            
            if(this[select_rectype].hSelect("instance")!=undefined){
                let menu = this[select_rectype].hSelect( "menuWidget" );
                menu.css({'max-height':'450px'});                        
                this[select_rectype].hSelect({
                        change: opts.onselect,
                        close: function(){
                                if(window.hWin.HEURIST4.util.isFunction(that.options.menu_locked)){
                                    that.options.menu_locked.call( that, false ); //unlock
                                }
                        }
                });
                this[select_rectype].hSelect('hideOnMouseLeave', opts.ancor);
            }
            
    },
    

    //
    // recreate rectype selectors and filter button set
    // 1. searches counts by rectype
    // 2. redraw buttons by entiry
    // 3. recres selectors for config and "by usage"
    //
    recreateRectypeSelectors: function(){

        if(this._waiting_server_response || !$Db.rty()) return;

        //selector to filter by entity
        if(this.options.use_combined_select || this.options.by_usage){
            this._recreateSelectRectypeFilter(this.usage_select_options);
        }

        //buttons - filter by entity
        if(this.options.by_favorites){
            this._redraw_buttons_by_entity(true);
            this.config_select_options.marked = this.selected_rty_ids;

            this._recreateSelectRectypeFilter(this.config_select_options);
        }
        
    },      
        
    //
    // opens selector on correct position
    //
    _openSelectRectypeFilter: function( opts ){
        
                let select_rectype = opts['select_name'];
       
                let that = this;
                function __openSelect(){
                    
                    that[select_rectype].hSelect('open');
                    that[select_rectype].val(-1);
                    that[select_rectype].hSelect('menuWidget')
                        .position({my: "left top", at: "left top", of: opts['ancor']}); //left+10 bottom-4
            
                    let menu = $(that[select_rectype].hSelect('menuWidget'));
                    let ele = $(menu[0]);
                    ele.scrollTop(0);        
                   
                    if(opts.useCheckboxes && window.hWin.HEURIST4.util.isFunction(opts.onmarker)){
                        let spans = menu.find('span.rt-checkbox');
                        that._off(spans,'click');
                        that._on(spans,{'click':function(e){
                            if($(event.target).is('span')){
                                opts.onmarker.call(that, $(event.target) );
                                window.hWin.HEURIST4.util.stopEvent(e);
                            }}});
                        /*
                        menu.find('span.rt-checkbox').on('click', function(e){
                            if($(event.target).is('span')){
                                opts.onmarker.call(that, $(event.target) );
                                window.hWin.HEURIST4.util.stopEvent(e);
                            }
                        });
                        */
                    }
                    
                }
                
                if(this[select_rectype]){
                    
                    if(window.hWin.HEURIST4.util.isFunction(this.options.menu_locked)){
                        this.options.menu_locked.call( this, true); //lock
                    }
                    __openSelect();
                }

    },
        
    //
    // search from input - query is defined manually
    //
    _doSearch: function(rty_ID){

            let request = {};
            request.q = 't:'+rty_ID;
            request.w  = 'a';
            request.qname = $Db.rty(rty_ID, 'rty_Plural');
            request.detail = 'ids';
            request.source = this.element.attr('id');
            request.search_realm = this.options.search_realm;
            
            window.hWin.HAPI4.RecordSearch.doSearch( this, request );
            
            if(window.hWin.HEURIST4.util.isFunction(this.options.onClose)){
                this.options.onClose();
            }
    }

    // events bound via _on are removed automatically
    // revert other modifications here
    ,_destroy: function() {

        window.hWin.HAPI4.removeEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);        
        //window.hWin.HAPI4.removeEventListener(this, window.hWin.HAPI4.Event.ON_REC_UPDATE);        
        
        this.div_entity_btns.find('.entity-filter-button').remove();

        if(this.usage_btn) this.usage_btn.remove();
        
        if(this.config_select) {
            if(this.config_select.hSelect("instance")!=undefined){
               this.config_select.hSelect("destroy"); 
            }
            this.config_select.remove();   
        }
        if(this.usage_select) {
            if(this.usage_select.hSelect("instance")!=undefined){
               this.usage_select.hSelect("destroy"); 
            }
            this.usage_select.remove();   
        }        
        
        this.div_entity_btns.remove();
        
        if(this.combined_select) this.combined_select.remove();
    }

});
