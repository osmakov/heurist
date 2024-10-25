/**
*  Wizard to define advanced search
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @designer    Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget( "heurist.searchBuilder", {

    // default options
    options: {
        is_dialog: false,
        default_palette_class: 'ui-heurist-explore', 
        is_h6style: false,
        svsID: null,
        domain: null, // bookmark|all or usergroup ID
        is_modal: true,
        menu_locked: null,
        onsave: null,
        onClose: null,
        beforeClose: null,
        
        rty_ID: null, //init with specified record type
        input_element: null,  //fill result to this element, instead of search
        is_for_rules: false
    },

    is_advanced: false,
    
    _dialog: null,
    is_edit_continuing: false, //???
    _lock_mouseleave: false,
    _save_in_porgress: false,

    //controls
    select_main_rectype: null,
    select_additional_rectypes: null,
    svs_MultiRtSearch: null,
    //field_selector: null,
    select_language: null,

    current_tree_rectype_ids:null, //to avoid reload
    
    group_items:{}, //groups - to be implemented
    field_array:[], //defined fields for current groups
    sort_array:[],
    
    select_field_for_id: null,

    enum_fields: ['term','code','conceptid','desc','internalid'],
    
    running_filter: false,
    
    // the widget's constructor
    _create: function() {

        // prevent double click to select text
       

        // Sets up element to apply the ui-state-focus class on focus.
       

        let that = this;

        this.element.css({overflow: 'hidden !important'}).addClass('ui-heurist-bg-light');

        let ht = $(window).height();
        if(ht>700) ht = 700;

       
        if(this.options.is_dialog){
        
            this._dialog = this.element.dialog({
                autoOpen: false,
                height: 450,
                width:950,
                modal: this.options.is_modal,

                resizable: true, //!is_h6style,
                draggable: this.options.is_modal, //!this.options.is_h6style,
                //position: this.options.position,
                
                title: window.hWin.HR('Filter builder'),
                resizeStop: function( event, ui ) {//fix bug
                        let pele = that.element.parents('div[role="dialog"]');
                        that.element.css({overflow: 'none !important', 'width':pele.width()-24 });
                },
         
                buttons: [

                    {text:window.hWin.HR(this.options.is_for_rules?'Apply':'Filter'), id:'btnSearch',
                        class:'ui-button-action', 
                        click: function() {
                            that._doSearch()
                    }}
                ],
                show: {
                    effect: 'fade',
                    duration: 500
                }
            });
        
            this.element.parent().addClass('ui-dialog-heurist');
            this.element.parent().find('.ui-dialog-buttonset').css('margin-right','260px');
            
            
            if(this.options.default_palette_class){
                this._dialog.attr('data-palette', this.options.default_palette_class);
                this._dialog.parent().addClass(this.options.default_palette_class);
            }else{
                this._dialog.attr('data-palette', null);
            }
        }
        
        this.element.load(window.hWin.HAPI4.baseURL+"hclient/widgets/search/searchBuilder.html", function(){
            that._initControls();
        });

        
        //click outside - hide treeview
        this._on(window.hWin.document, { 
            click: function(e){
                if(this.pnl_Tree) this.pnl_Tree.hide();
            }}
        );
        

        this._refresh();

    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash,
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
    },

    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    /*
    _setOptions: function( options ) {
    },*/

    /*
    * private function
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

    },
    //
    // custom, widget-specific, cleanup.
    _destroy: function() {
        
        if(this.select_main_rectype){
            if(this.select_main_rectype.hSelect("instance")!=undefined){
               this.select_main_rectype.hSelect("destroy"); 
            }
            this.select_main_rectype.remove();   
            this.select_main_rectype = null;
            
            this.select_additional_rectypes.remove();   
            this.select_additional_rectypes = null;
        }
        
        if(this.select_language){
            if(this.select_language.hSelect('instance') !== undefined){
                this.select_language.hSelect('destroy');
            }
            this.select_language.remove();
            this.select_language = null;
        }
        
        
        // remove generated elements
    }
    
    //
    //
    //
    , adjustDimension: function(){
        
        if(this.field_array.length==0){
            this.btnAddFieldItem.trigger('click');
        }else{
            this.pnl_Items.find('.field_header:first').css('visibility','visible');
        }
        if(this.field_array.length>1){
            this.pnl_Items.find('.search_conjunction').css('visibility','visible');    
        }else{
            this.pnl_Items.find('.search_conjunction').css('visibility','hidden');    
        }
        
        if(this.options.is_for_rules){
            let sele = this.element.find('#sortby_accordion');
            sele.hide();
            sele.prev().hide(); //hide <hr>
            sele.next().hide();
            
            sele = this.element.find('#ruleset_accordion');
            sele.hide();
        }else
        if(this.sort_array.length==0){
            this.addSortItem();
        }else{
            this.pnl_Items.find('.sort_header').css('visibility','hidden');
            this.pnl_Items.find('.sort_header:first').css('visibility','visible');
        }
            

        let ch = this.btnAddSortItem.position().top + 24;   
            
        ch = this.pnl_Rectype.height() + ch + 115; 
        if(ch<500) ch = 500;

        let topPos = 0;
        if(this.options.is_dialog){        
            let pos = this._dialog.dialog('option', 'position');
            if(pos && pos.of && !(pos.of instanceof Window)){
                let offset = $(pos.of).offset();
                topPos = (offset?offset.top:0)+40;
            }

            let ht = Math.min(ch, window.innerHeight-topPos);

            this._dialog.dialog('option', 'height', ht);    
        }else{
            topPos = this.element.parent().offset().top + 10;
            
            if(ch > window.innerHeight-topPos){
                ch = window.innerHeight-topPos;
            }
            
            this.element.parent().height(ch);
        }
                
    }
    
    //
    //  show either treeview or menuWidget field selector
    //
    ,showFieldSelector: function( ele_id ){
        
        if(!(this.select_main_rectype.val()>0)){
            this.pnl_Tree.hide();
        }else{
            this.select_field_for_id = ele_id;
            
            // Close all option menus
            $.each(this.pnl_Tree.find('.fancytree-submenu'), function(idx, ele){
                ele = $(ele);

                if(ele.menu('instance') !== undefined){
                    ele.menu('collapseAll');
                }
            });

            this.pnl_Tree.show();
        }        
    }

    //
    //
    //
    ,show: function( )
    {
        this.current_tree_rectype_ids = null;
        
        this.element.dialog( 'option', 'modal', this.options.is_modal);
        this.element.dialog( 'option', 'draggable', this.options.is_modal);
        
        if(this.options.position!=null){
            this._dialog.dialog( 'option', 'position', this.options.position );   
        }
        this._dialog.dialog('open');                           
        
        if(this.options.is_modal || !this.is_edit_continuing){
            
            if(!(this.options.is_modal && this.options.svsID>0) || !this.is_edit_continuing){
               
            }
            
            this.is_edit_continuing = !this.options.is_modal;
        }
        
        if(this.options.is_h6style){
            this.element.parent().addClass('ui-heurist-explore');
        }
        
        if(window.hWin.HEURIST4.util.isFunction(this.options.menu_locked)){
            
            this._on(this.element.parent('.ui-dialog'), {
                        mouseover:function(){ this.options.menu_locked.call( this, false, false );},  //just prevent close
                        mouseleave: function(e){ 
                            this.options.menu_locked.call( this, false, true ); //close after delay
                        }});
            
        }else{
            
            this._off(this.element.parent('.ui-dialog'), 'mouseover mouseleave');
        }

        if(this.select_main_rectype!=null && this.options.rty_ID>0){
            this.refreshRectypeMenu();       
        }
        
        
        //window.hWin.HEURIST4.ui.applyCompetencyLevel(-1, $dlg); 
    }

    //
    //
    //
    , addSortItem: function(){
        
        let rty_ID = this.select_main_rectype.val();
                                                   
        let ele = $('<div>').uniqueId().insertBefore(this.btnAddSortItem);
        this.sort_array.push(ele);
        
        let that = this;

        ele.searchBuilderSort({ rty_ID: rty_ID ,
                        onchange: function(){
                            that._doCompose();
                        },
                        onremove: function(){
                            let id = this.element.attr('id');
                            $.each(that.sort_array,function(k,item){
                                if(item.attr('id')==id){
                                    that.sort_array.splice(k,1);
                                    that.pnl_Items.find('#'+id).remove();    
                                    return false;
                                }
                            });

                            that.adjustDimension();
                            that._doCompose();
                            
                        }
        });
     
        if(this.sort_array.length>1){
            ele.find('.sort_header').css('visibility','hidden');
        }
     
        this.adjustDimension();
        
    } 
    
    //
    // add or change selected field
    //    
    , addFieldItem: function( code, codes, ele_id, reverse_RtyID  ){

        if(!codes) codes = code.split(':');
        
        let enum_field = null;
        if (this.enum_fields.indexOf(codes[codes.length-1])>=0){
            enum_field = codes[codes.length-1];
            codes.splice(-1);
            code = codes.join(':');
            if(enum_field=='internalid') enum_field = null;
        }

        let rty_ID = codes[codes.length-2];
        let dty_ID = codes[codes.length-1];
        let top_rty_ID = codes[0];
        let lang = this.select_language.val();

        if(!(top_rty_ID>0)) top_rty_ID = 0;
        if(!(rty_ID>0)) rty_ID = 0;
        
        if(ele_id){
            
            let ele = this.pnl_Items.find('#'+ele_id)
            ele.searchBuilderItem('changeOptions',{
                    code: code,
                    top_rty_ID: top_rty_ID, 
                    rty_ID: rty_ID,
                    dty_ID: dty_ID,
                    enum_field:enum_field,
                    language: lang,
                    reverse_RtyID: reverse_RtyID
                });

        }else{
            let ele = $('<div>').uniqueId().attr('data-code',code).insertBefore(this.btnAddFieldItem);
            this.field_array.push(ele);
        
            let that = this;
            ele.searchBuilderItem({
                    //token:  dty_ID>0?'f':dty_ID,
                    hasFieldSelector: !this.is_advanced,
                    code: code,
                    top_rty_ID: top_rty_ID, 
                    rty_ID: rty_ID,
                    dty_ID: dty_ID,
                    enum_field: enum_field,
                    language: lang,
                    reverse_RtyID: reverse_RtyID,
                    onremove: function(){
                        let id = this.element.attr('id');
                        $.each(that.field_array,function(k,item){
                            if(item.attr('id')==id){
                                that.field_array.splice(k,1);
                                that.pnl_Items.find('#'+id).remove();    
                                
                                return false;
                            }
                        });

                        that.adjustDimension();
                        that._doCompose();
                    },
                    onchange: function(){
                        that._doCompose();
                        that.sortbySection.find('#sortby_header #sortby_values').text('record title');
                    },
                    onselect_field: function(){
                        let id = this.element.attr('id');
                        that.showFieldSelector( id );
                    }
            });
            
            if(this.field_array.length>1){
                let conjunct = (this.search_conjunction.val()=='any')?'OR':'AND';

                ele.find('.field_header').text(conjunct).attr('title', 'Change value in dropdown above fields');
            }

            if(this.field_array.length == 2){

                this.element.find('.search_conjunction').position({
                    my: 'right center',
                    at: 'right center',
                    of: ele.find('.field_header')
                });

                this.element.find('.search_conjunction').css('left', 3);
            }
        }
        
        this.adjustDimension();
    }

    //
    //
    //
    , refreshRectypeMenu: function(){
        let that = this;

        let selected = -1;

        if(this.select_main_rectype){
            selected = (this.select_main_rectype.val() > 0 || this.select_main_rectype.val() == '') ? this.select_main_rectype.val() : -1;
            this._off(this.select_main_rectype,'change');
            this.select_main_rectype.hSelect('destroy'); 
            this.select_main_rectype = null;
        }

        //
        //
        function __onRectypeChange(){
            if(that.select_additional_rectypes){
                //reset
                that.select_additional_rectypes.editing_input('setValue', '');
            }

            if(that.select_main_rectype.val()<0){
               
                that.pnl_CoverAll 
                .css({ top:that.pnl_Items.css('top'),bottom:that.pnl_Items.css('bottom') })
                .show();
            }else{
               
                that.pnl_CoverAll.hide();
                //load list of field types
               
                that._initTreeView([that.select_main_rectype.val()]);
            }

            that.clearAll();

        }     
        
        this._on(this.element.find("#opt_rectypes"), {change: __onRectypeChange});
        
        this.select_main_rectype = window.hWin.HEURIST4.ui.createRectypeSelectNew( this.element.find("#opt_rectypes").get(0),
            {
                topOptions: this.options.rty_ID>0?null
                :[{key:'-1',title:'select record type to search...'},
                    {key:'',title:'any record type'}],
                rectypeList: this.options.rty_ID>0?[this.options.rty_ID]:null,
                useHtmlSelect: false,
                showAllRectypes: true,
                eventHandlers: {onSelectMenu:__onRectypeChange}
        });

        

        if(this.options.rty_ID>0){
            selected = this.options.rty_ID;
        }
        if(selected == '' || this.select_main_rectype.find('option[value=' + selected + ']').length > 0){ 
            this.select_main_rectype.val(selected);

            if(this.select_main_rectype.hSelect('instance')!=undefined){
                this.select_main_rectype.hSelect('refresh'); 
                
                if(this.options.rty_ID>0) __onRectypeChange();
            }
        }else{
            this.pnl_CoverAll 
            .css({ top:this.pnl_Items.css('top'),bottom:this.pnl_Items.css('bottom') })
            .show();
        }
    }

    //
    //
    //
    , _initControls: function(){
        
            let that = this;
            
            if(this.select_main_rectype==null){
                
                //additional rectypes                
                this.select_additional_rectypes = this._createInputElement_RecordTypeSelector();
                this.select_additional_rectypes.hide();
                
                this.svs_MultiRtSearch = this.element.find('#svs_MultiRtSearch');
                
                this._on(this.svs_MultiRtSearch, {change:function(event){
                    if(this.select_additional_rectypes.editing_input('instance')){
                        if(this.svs_MultiRtSearch.is(':checked')){
                            this.select_additional_rectypes.show();
                        }else{
                            
                            //reset flag - facet was changed - need to proceed all steps of wizard
                            if(this.select_additional_rectypes.editing_input('getValues')[0]){
                               
                            }
                            this.select_additional_rectypes.editing_input('setValue', '');
                            this.select_additional_rectypes.hide();
                        }
                       
                    }}});
            
                this.pnl_Rectype  = this.element.find('#pnl_Rectype');
                this.pnl_Tree  = this.element.find('#pnl_Tree');
                this.pnl_Items = this.element.find('#pnl_Items');
                this.pnl_CoverAll = this.element.find('#pnl_CoverAll');
                
                this.pnl_Result = this.element.find('#pnl_Result');
                this.btnAddFieldItem = this.pnl_Items.find('.search_field_add');

                this._on(this.btnAddFieldItem, {click:function(event){
                    
                    let rty_ID = that.select_main_rectype.val();
                    that.addFieldItem( 'any:anyfield', [rty_ID , 'anyfield'] );
                }});
                
                this._on(this.pnl_Tree, {click:function(event){
                        event.stopPropagation(); 
                }});

                // sortby accordion header
                this.sortbySection = this.pnl_Items.find('#sortby_accordion').accordion({heightStyle: "content",active: false,collapsible: true});

                //
                //
                this.btnAddSortItem = this.pnl_Items.find('.sort_field_add');

                this._on(this.btnAddSortItem, {click:function(event){
                    
                    that.addSortItem();
					this.sortbySection.find('#sortby_header #sortby_values').text(this.sortbySection.find('#sortby_header #sortby_values').text() + ', record title');
                }});
                
                this.search_conjunction = this.pnl_Items.find('.search_conjunction').find('select');
                this._on(this.search_conjunction, {change:this._doCompose});
                
                this._on(this.pnl_Rectype.find('#btn-clear').button(), { click:this.clearAll });

                // ruleset accordion headers
                this.rulesetSection = this.pnl_Items.find('#ruleset_accordion').accordion({heightStyle: 'content', active: false, collapsible: true});

                this._on(this.rulesetSection.find("#svs_RulesOnly"),{
                    'change': function(event){
                        this.rulesetSection.find("#divRulesOnly").css('display', $(event.target).is(':checked') ? 'inline-block' : 'none');
                    }
                });

                this.rulesetSection.find("#svs_Rules_edit")
                .button({icon:"ui-icon-pencil", showLabel:false})
                .attr('title', window.hWin.HR('Edit RuleSet'))
                .css({'height':'16px', 'width':'16px'})
                .on('click', function( event ) {
                    that._editRules();
                });

                this.rulesetSection.find("#svs_Rules_clear")
                .button({icon:"ui-icon-close", showLabel:false})
                .attr('title', window.hWin.HR('Clear RuleSet'))
                .css({'height':'16px', 'width':'16px'})
                .on('click', function( event ) {
                    that.rulesetSection.find('#svs_Rules').val('');
                });
            }
            
            
            if(this.select_main_rectype==null || this.options.rty_ID>0){            
                this.refreshRectypeMenu();
            }
            
            if(this.select_language == null){

                this.select_language = this.element.find('#opt_language');
                let options = [{title: 'ANY', key: '*', selected: true}, {title: 'Default', key: ''}];
                this.select_language = window.hWin.HEURIST4.ui.createLanguageSelect(this.select_language, options, '*', false,
                {onSelectMenu: function(){
                        // Update language of dropdowns
                        let lang = that.select_language.val();
                        $.each(this.field_array, function(i, ele){

                            let code = ele.searchBuilderItem('getCodes');
                            let codes = code.split(':');

                            if($Db.dty(codes[codes.length-1], 'dty_Type') == 'enum'){
                                ele.searchBuilderItem('changeOptions',{
                                    language: lang
                                });
                            }
                        });
                    }});


                this.select_language.hSelect('widget').css({width: '100px', 'min-width': '100px'});

                this.element.find('.filter-language').attr('title', 'Specify the language of the values dropdown and of the search.\n'
                    + 'ANY will search across the default language and all translated terms or texts.\n'
                    + 'Default is the default language used in construction of the database.');
            }
            
            if(!this.options.is_dialog){
                //add header and button set for inline mode
                let h = this.element.find('.btn-preview').is(':checked') ?'88px':'50px';

                this.element.css({'font-size':'0.9em'});
                this.pnl_Rectype.css({top:'35px'}); //,height:'30px'
                this.pnl_Tree.css({top:35}); //, bottom:h
                this.pnl_Items.css({bottom:h});
                this.pnl_CoverAll.css({top:'85px', bottom:h});
                this.pnl_Result.css({bottom:'40px'});
                let _innerTitle = $('<div class="ui-heurist-header" style="top:0px;padding-left:10px;text-align:left">Filter builder</div>')
                    .insertBefore(this.pnl_Rectype);
                
                this._on(    
                $('<button>').button({icon:'ui-icon-closethick',showLabel:false, label:'Close'}) 
                     .css({'position':'absolute', 'right':'4px', 'top':'6px', height:20, width:20})
                     .appendTo(_innerTitle),
                     {click:function(){
                         that.closeDialog();
                     }});
                    
                    
                //button panel on the botom                        
                let ele = this.element.find('.popup_buttons_div').show();
            
                ele.find('.btn-search').button({icon:'ui-icon-filter'});
                this._on(ele.find('.btn-search'),{click:this._doSearch});

                ele.find('.btn-save').button().hide();
                this._on(ele.find('.btn-save'),{click:this._doSaveSearch});
                
                this._on(ele.find('.btn-preview'),{change:function(e){
                    
                    let h;
                    if(this.element.find('.btn-preview').is(':checked')){
                        h = this.options.is_dialog ? '50px':'88px';                       
                        this.pnl_Result.show();
                        this._doCompose();
                    }else{
                        h = this.options.is_dialog ? '0px':'50px';                       
                        this.pnl_Result.hide();
                    }
                        this.pnl_Items.css('bottom',h);
                        this.pnl_CoverAll.css('bottom',h);
                       
                }});
                
                
                this._on(ele.find('.btn-copy'),{click:function(e){
                        let s = this.pnl_Result.text();
                        if(s) window.hWin.HEURIST4.util.copyStringToClipboard(s);
                }});
                
                $(this.document).on(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH, function(e, data){
                    if(that.running_filter){
                        that.running_filter = false;
                        if(that.element.find('.save-filter').is(':checked')){
                            that._doSaveSearch();
                        }
                    }
                });
            }
                
                
       
        //window.hWin.HEURIST4.ui.applyCompetencyLevel(-1, $dlg); 

        this.adjustDimension();
        
        
        
    },

    
    clearAll: function()
    {
        this._doCompose();        
        
        this.pnl_Items.children('div:not(.btn_field_add, #sortby_accordion, #ruleset_accordion)').remove();
        this.sortbySection.find('#sortby_body').children('div:not(.btn_field_add)').remove();
        this.rulesetSection.find("#svs_Rules").val('');
        this.field_array = [];
        this.sort_array = [];
        this.adjustDimension();
        
    },
    
    //
    // nulti record type selector
    //
    _createInputElement_RecordTypeSelector: function(){
        
        let that = this;

        let ed_options = {
            recID: -1,
            dtID: "dty_PtrTargetRectypeIDs",
            dtFields:{
                "dty_Type":"resource",
                "rst_DisplayName":"Also search for:",
                "rst_DisplayHelpText": "", 
//This determines the record type which will be retrieved by the search. The facets can, however, be based on attributes of other record types linked from this type                
                "rst_FieldConfig": {"entity":"DefRecTypes","csv":true}
            },
            change: function(){
                    let val = this.getValues();
                    val = val[0].split(',');
            }    
        };

        return $("<div>").editing_input(ed_options).insertAfter( this.element.find('.main-rectype') );
    }
  

    // init fieldtreeview
    , _initTreeView: function(rectypeIds){
        
        
        if(window.hWin.HEURIST4.util.isArrayNotEmpty(rectypeIds) && this.current_tree_rectype_ids != rectypeIds.join(',') ){

            let that = this;
           
            let treediv = this.element.find('#field_treeview');
            let rectype = rectypeIds.join(',');

            /*
            if(this.options.params.rectypes){
                rectype = this.options.params.rectypes.join(',');
            }
            */
            
            let node_order = sessionStorage.getItem('heurist_ftorder_filterbuilder');
            if(window.hWin.HEURIST4.util.isempty(node_order) || !Number.isInteger(+node_order)){
                node_order = 0; // default to form order
            }
            this.element.find('[name="tree_order"]').filter('[value="'+ node_order +'"]').prop('checked', true);

            

            //'title','modified',
            let allowed_fieldtypes = ['header_ext','anyfield','enum','freetext','blocktext',
                            'geo','year','date','integer','float','resource','relmarker','relationtype','file','separator'];
                    
            let treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree_new( 
                            {
                                mode:5, rectypeids:rectype, fieldtypes:allowed_fieldtypes, field_order:node_order //, enum_mode:'expanded' 
                            } );

                        treedata[0].expanded = true; //first expanded

                        if(!treediv.is(':empty') && treediv.fancytree('instance')){
                            treediv.fancytree('destroy');
                        }

            if(rectypeIds.length == 1){
                treedata[0]['children'].unshift({code: `${rectype}:exists`, key: 'exists', name: `${$Db.rty(rectype, 'rty_Name')}`, title: `${$Db.rty(rectype, 'rty_Name')} records`, type: 'freetext'});
            }

            //setTimeout(function(){
            treediv.addClass('tree-filter hidden_checkboxes').fancytree({
                //extensions: ["filter"],
                //            extensions: ["select"],
                checkbox: true,
                selectMode: 1,  // single
                source: treedata,
                beforeSelect: function(event, data){
                    // A node is about to be selected: prevent this, for folder-nodes:
                    if( data.node.hasChildren() ){
                        return false;
                    }
                },
                renderNode: function(event, data){
                    
                    let order = that.element.find('[name="tree_order"]:checked').val();

                    if(data.node.data.is_generic_fields || data.node.data.is_rec_fields){
                        $(data.node.span.childNodes[0]).css('display', 'inline-block');
                        $(data.node.span.childNodes[1]).hide();
                        $(data.node.span.childNodes[3]).css('font-weight', 'normal');

                        if(data.node.parent && data.node.parent.type == 'resource' || data.node.parent.type == 'relmarker'){ // add left border+margin
                            $(data.node.li).attr('style', 'border-left: black solid 1px !important;margin-left: 9px;');
                        }
                    }
                    if(data.node.type == 'separator'){
                        $(data.node.span).attr('style', 'background: none !important;color: black !important;'); //stop highlighting
                        $(data.node.span.childNodes[1]).hide(); //checkbox for separators

                        if(order == 1){
                            $(data.node.li).addClass('fancytree-hidden');
                        }
                    }else if(data.node.type == 'enum'){ // add options dropdown menu

                        const key = data.node.key;

                        let $ele = $('<ul>', {class: 'horizontalmenu fancytree-submenu'})
                            .attr('data-type', 'enum').attr('data-code', data.node.data.code)
                            .html('<li data-id="internalid"><span>option</span><ul>' //default is internalid = trm_ID
                                        + '<li data-id="internalid">Internal ID</li>'
                                        + '<li data-id="term">Term</li>'
                                        + '<li data-id="code">Code</li>'
                                        + '<li data-id="conceptid">Concept ID</li>'
                                        + '<li data-id="desc">Description</li>'
                                + '</ul></li>')
                            .appendTo($(data.node.span.childNodes[3]));

                        $ele.menu({
                            icons: { submenu: "ui-icon-triangle-1-s" },
                            select: function(e, ui){

                                window.hWin.HEURIST4.util.stopEvent(e);

                                let code = $ele.attr('data-code');
                                code += ':' + ui.item.attr('data-id');
                                let codes = code.split(':');
                                let codes2 = code.split(':');

                                codes2[0] = 'any';
                                code = codes2.join(':');

                                //add or replace field in builderItem
                                that.addFieldItem( code, codes, that.select_field_for_id);
                                that.select_field_for_id = null;
                                that.pnl_Tree.hide();

                                if(treediv.fancytree('instance') !== undefined){
                                    const tree = $.ui.fancytree.getTree( treediv );
                                    const node = tree.getNodeByKey(key);

                                    node.setActive(true);

                                    $ele.menu('collapseAll');
                                }
                            },
                            focus: function(e, ui){

                                if(!ui.item.parent().is('ul.horizontalmenu')){
                                    return;
                                }

                                let code = $ele.attr('data-code');
                                $.each(that.pnl_Tree.find('.fancytree-submenu'), function(idx, ele){
                                    ele = $(ele);

                                    if(ele.attr('data-code') == code){
                                        return;
                                    }

                                    if(ele.menu('instance') !== undefined){
                                        ele.menu('collapseAll');
                                    }
                                });

                                let $parent = that.element.find('#field_treeview').children();

                                // getComputedStyle(ele) | getBoundingClientRect()
                                let cont_rect = $parent[0].getBoundingClientRect();
                                let ele_rect = $ele[0].getBoundingClientRect();

                                let bottom_val = ele_rect.bottom + 110;

                                if(bottom_val > cont_rect.bottom){
                                    $ele.menu('option', 'position', {my: 'left bottom-5', at: 'left top'});
                                }else{
                                    $ele.menu('option', 'position', {my: 'left top+5', at: 'left bottom'});
                                }

                            },
                            position: {
                                my: 'left top+5',
                                at: 'left bottom'
                            }
                        });
                    }
                },
                lazyLoad: function(event, data){
                    
                    let node = data.node;
                    let parentcode = node.data.code; 
                    let rectypes = node.data.rt_ids;

                    let node_order = that.element.find('[name="tree_order"]:checked').val();
                                                                            
                    let res = window.hWin.HEURIST4.dbs.createRectypeStructureTree_new( 
                    {
                        mode:5, rectypeids:rectypes, fieldtypes:allowed_fieldtypes, 
                        parentcode: parentcode,
                        field_order:node_order//, enum_mode:'expanded' 
                    } );
                                                                            
                                                                            
                    if(res.length>1){
                        data.result = res;
                    }else{
                        data.result = res[0].children;
                    }
                    
                    let ptr_fld = window.hWin.HEURIST4.util.cloneJSON(data.node.data);
                    if(ptr_fld.type=='resource'){
                        //adds as a first in the list of pointer rectype fields
                        if(ptr_fld.isreverse){
                            ptr_fld.title = '<span style="font-size:smaller">Source record: '+$Db.rty(ptr_fld.rtyID_local, 'rty_Name')+'</span>'; 
                        }else{
                            ptr_fld.title = '<span style="font-size:smaller">Target record: '+ptr_fld.name+'</span>';    
                        }
                        ptr_fld.lazy = false;
                        data.result.unshift(ptr_fld);
                    }
                                                                            

                    return data;                                                   
                },
                expand: function(e, data){
                    that.showHideReverse(data);
                },
                collapse: function(e, data){
                    that._update_GenericField(data.node);
                },
                loadChildren: function(e, data){
                    setTimeout(function(){
                        that.showHideReverse(data);   
                       
                    },500);
                },
                /* select: function(e, data) {
                   // Get a list of all selected nodes, and convert to a key array: 
                },*/
                click: function(e, data){

                    if(data.node.type == 'separator'){
                        return false;
                    }

                    let isExpander = $(e.originalEvent.target).hasClass('fancytree-expander');

                    if(isExpander) return;

                    if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){
                        data.node.setExpanded(!data.node.isExpanded());
                    }else if( data.node.lazy){
                        data.node.setExpanded( true );
                    }else{
                        let code = data.node.data.code;
                        if(code){
                            let codes = code.split(':');

                            if(codes.length == 2 && $Db.dty(codes[1], 'dty_Type') == 'enum'){
                                // by default, handle as internal id
                               
                               
                            }

                            let codes2 = code.split(':');
                            codes2[0] = 'any';
                            code = codes2.join(':');

                            let reverse_RtyID = 0;
                            if(data.node.data.isreverse){
                                reverse_RtyID = data.node.data.rtyID_local;
                            }
                            
                            //add or replace field in builderItem
                            that.addFieldItem( code, codes, that.select_field_for_id, reverse_RtyID);
                            that.select_field_for_id = null;
                            that.pnl_Tree.hide();
                        }else{
                            console.error('ERROR: code not defined for',data);
                        }
                    }
                },
                dblclick: function(e, data) {
                    if(data.node.type == 'separator'){
                        return false;
                    }
                    data.node.toggleSelected();
                },
                keydown: function(e, data) {
                    if( e.which === 32 ) {
                        data.node.toggleSelected();
                        return false;
                    }
                }
            });

            //hide all folder triangles
           

            that.current_tree_rectype_ids = rectypeIds.join(',');


            $("#fsw_showreverse").on('change', function(event){
                that.showHideReverse();
            });

           
           
            $("#fsw_showreverse").attr('checked', false);
            $("#fsw_showreverse").trigger('change');

            this._off($('[name="tree_order"]'), 'change');
            this._on($('[name="tree_order"]'), {
                change: () => {
                    let order = $('[name="tree_order"]:checked').val();
                    sessionStorage.setItem('heurist_ftorder_filterbuilder', order);

                    if(treediv.fancytree('instance')!==undefined){

                        window.hWin.HEURIST4.ui.reorderFancytreeNodes_rst(treediv, order);
                        that.showHideReverse();
                    }
                }
            });
        }
    }
    
    , showHideReverse: function(data){
        
        let showrev = $('#fsw_showreverse').is(":checked");
        let treediv = $('#field_treeview');
        let tree = $.ui.fancytree.getTree(treediv);
        let that = this;
        tree.visit(function(node){

            if(node.data.isreverse==1){ 

                if(showrev===true){
                    $(node.li).removeClass('fancytree-hidden');
                }else{
                    $(node.li).addClass('fancytree-hidden');
                }
            }
            if(node.data.is_generic_fields){ 
                   that._update_GenericField(node);
            }
        });
        
       
    }
    
    ,_update_GenericField: function(node){
        
            if(!node) return;
        
            if(node.data.is_generic_fields){ 
                    let ele = $(node.li).find('.fancytree-checkbox');
                    if(node.isExpanded()){
                       ele.css({'background-position': '-32px -80px'});   //-48px -80px  
                    }else{
                       ele.css({'background-position': '0px -80px'});     
                    }
            }
            
        
    }
    
     //
    // close dialog
    //
    , closeDialog: function(is_force){
        if(this.options.is_dialog){
            
            if(is_force===true){
                this._dialog.dialog('option','beforeClose',null);
            }
            this._dialog.dialog("close");
            
        }else{
            
            let canClose = true;
            if(window.hWin.HEURIST4.util.isFunction(this.options.beforeClose)){
                canClose = this.options.beforeClose();
            }
            if(canClose){
                if(window.hWin.HEURIST4.util.isFunction(this.options.onClose)){
                    this.options.onClose();
                }
            }
        }
    }

    //
    // popup save filter dialog for current query
    //
    ,_doSaveSearch: function(prevent_real_save=false){

        const widget = window.hWin.HAPI4.LayoutMgr.getWidgetByName('slidersMenu');
        if(widget && !prevent_real_save){

            const top = this.element.parent().css('top');
            const left = this.element.parent().css('left');
            setTimeout(() => {
                widget.slidersMenu('show_ExploreMenu', null, 'svsAdd', {'top': top, 'left': left});
            }, 2000);
        }

        this.element.find('.save-filter').prop('checked', false);
    }

    ,_doSearch: function(){
        
        this._doCompose();
        
        let query = this.pnl_Result.text();
        let ruleset = this.rulesetSection.find('textarea').val();
        let ruleset_only = this.rulesetSection.find("#svs_RulesOnly");
        
        if(query){
            
            if(this.options.input_element){
                
                if(this.options.is_for_rules){
                    //remove main t: and sortby:
                    let filter = window.hWin.HEURIST4.util.isJSON(query);
                    let res = [];

                    for (let i=1; i<filter.length; i++){
                        if(!filter[i]['sortby']){
                            res.push(filter[i])    
                        }
                    }
                    if(res.length>0){
                        query = JSON.stringify(res);
                    }else{
                        query = '';
                    }
                }
                
                this.options.input_element.val(query);
                
            }else{
        
                let request = {};
                    request.q = query;
                    request.w  = 'a';
                    request.detail = 'ids';
                    request.source = this.element.attr('id');
                    request.search_realm = this.options.search_realm;
                    request.search_page = this.options.search_page;

                if(!this.options.is_for_rules){    
                    if(!window.hWin.HEURIST4.util.isempty(ruleset)){
                        request.rules = ruleset;
                    }
                    if(ruleset_only.is(':checked')){
                        request.rulesonly = this.rulesetSection.find('input[name="svs_RulesOnly"]:checked').val();
                    }
                }

                this.running_filter = true;
                window.hWin.HAPI4.RecordSearch.doSearch( this, request );
            }
            
            this.closeDialog();
        }else{
           window.hWin.HEURIST4.msg.showMsgFlash('Define at least one criterion');
        }

    }
    
    ,_doCompose: function(){
        
        this.pnl_Result.empty()
        
        let mainquery = [];
        
        let rty_IDs = this.select_main_rectype.val();
        
        if(rty_IDs<0){
            return '';
        }
        

        //sort by code
        this.field_array.sort( function(a, b){
            return (a.searchBuilderItem('getCodes')<b.searchBuilderItem('getCodes'))?-1:1;            
        });
        
/*
0: "10:20"
1: "10:lt134:12:1147"
2: "10:lt134:12:26"
3: "10:lt241:12:133"
4: "10:lt241:12:rt:17:135"
     0    1    2   3    4
*/ 

        function __convertLink(code){
                       
                let key;     
                let dtid = code;
                let is_fc = dtid.substring(0, 3) == 'fc:';
                let linktype = is_fc ? dtid.substring(3,5) : dtid.substring(0,2);
                let slink = '';
                
                if(linktype=='rt'){
                    slink = "related_to:";
                }else if(linktype=='rf'){
                    slink = "relatedfrom:";
                }else if(linktype=='related'){
                    slink = "related:";
                }else if(linktype=='lt'){
                    slink = "linked_to:";
                }else if(linktype=='lf'){
                    slink = "linkedfrom:";
                }
                if(slink!=''){
                    dtid = dtid.substring(is_fc ? 5 : 2);
                    key = (is_fc?'fc:':'')+slink+dtid;    
                }else{
                    key = 'f:'+dtid;    
                } 
                
                return key;           
        }

        let fields_query = [];

        let that = this;

        let existing_records = false;
        
        $.each(this.field_array, function(i, ele){
            
            let code = ele.searchBuilderItem('getCodes');
            
            let value = ele.searchBuilderItem('getValues');
            let branch;
            let is_relationship = false; //is current branch is relationship (rt,rf)

            if(code == 'any:exists'){
                existing_records = true;
                return;
            }
            
            if(value!=null){
                let codes = code.split(':');

                let enum_field = null;
                if (that.enum_fields.indexOf(codes[codes.length-1])>=0){
                    enum_field = codes[codes.length-1];
                    if(enum_field=='internalid') enum_field = null;
                    codes.splice(-1);
                }

                branch = fields_query;    
                //find branch
                if(codes.length>2){

                    //add new ones if not found
                    for(let k=1; k<codes.length-1; k++){
                        if(k%2 == 0){ //even element in codes is rectype
                            
                            if(codes[k]!=''){ //constrainded
                            
                                if(codes[k]==window.hWin.HAPI4.sysinfo['dbconst']['RT_RELATION']
                                         && is_relationship) continue; //ignore t:1 for relationships
                            
                                let not_found = true;
                                if(Array.isArray(branch)){
                                
                                    $.each(branch,function(m,item){
                                       if(item['t']==codes[k]){
                                           not_found = false; 
                                           return false;
                                       }else if(item['t']){
                                            not_found = false; 
                                            if(item['t'].split(',').indexOf(codes[k])<0){
                                                item['t'] = item['t']+','+codes[k];
                                            }
                                            return false;
                                       }
                                    });
                                
                                }else{
                                    /*@todo test
                                    let newbranch = [{t:codes[k]},{ids:branch}];
                                    branch = newbranch;   
                                    fields_query = newbranch;   
                                    not_found = false;                                  
                                    */
                                }
                                
                                if(not_found){
                                    branch.push({t:codes[k]});    
                                }
                            }
                        }else{
                            //odd element is field
                            
                            let key = __convertLink(codes[k]);

                            is_relationship = key.indexOf('related_to:')==0
                                              || key.indexOf('relatedfrom:')==0;
                                              
                            if(is_relationship && k>0 && k+1<codes.length){
                                //change to "related" if both sides of relationship are the rectype
                                //key.indexOf('related:')==0
                                if(codes[k+1]==codes[k-1] || (k-1==0 && codes[k+1]==that.select_main_rectype.val())){
                                    key = 'related:'+key.split(':')[1];        
                                }
                            }
                            
                            if(enum_field!=null){
                                key = key+':'+enum_field;
                            }
                        
                            //find
                            let not_found = true;
                            $.each(branch, function(i,item){
                                if(item[key]){
                                   
                                    if(!Array.isArray(item[key])){
                                        item[key] = [{ids:item[key]}];
                                    }
                                    branch = item[key];    
                                    not_found = false;
                                    return false;
                                }
                            });

                            //add new branch 
                            if(not_found){
                                
                               
                                
                                let newbranch = {};
                                newbranch[key] = [];
                                branch.push(newbranch);
                                branch = newbranch[key];
                            }   
                        }   

                    }//for
                    
                    
                    //replace f: to r: for relationship record in rf and rt
                    if(value && is_relationship){
                        
                        let dtid = codes[codes.length-1];
                        if(dtid.indexOf('r.')==0){
                            //replace f to r for value
                            dtid = dtid.substr(2);
                            let nkey = 'r';
                            if(dtid!=window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE']) nkey = 'r:'+dtid;
                            
                            let newvalue = {}
                            newvalue[nkey] = value[Object.keys(value)[0]];
                            value = newvalue;
                        }
                    }
                
                }
                else{
                   //key = __convertLink(codes[1]); 
                }
                
                let old_key = Object.keys(value)[0];
                let key = __convertLink(old_key); 
                let idx = key.indexOf('fc:')==0 ? 3 : 0;
                if(key.indexOf('linked_to')==idx || key.indexOf('linkedfrom')==idx){
                   let newvalue = {};
                   newvalue[key] = value[old_key]; 
                   value = newvalue;
                }
                
                
                branch.push(value);        
            }
            
        });
        
        if(fields_query.length>0){
        
            let fields_conjunction = this.search_conjunction.val();
            if(fields_query.length>1 && fields_conjunction=='any'){
                mainquery.push({any:fields_query});
            }else{
                mainquery = mainquery.concat(fields_query)
            }
        }

        if(mainquery.length>0 || existing_records || !window.hWin.HEURIST4.util.isempty(that.rulesetSection.find('#svs_Rules').val())){

        
            if(rty_IDs>0){
                
                if(this.svs_MultiRtSearch.is(':checked')){
                    let s = this.select_additional_rectypes.editing_input('getValues')[0];
                    if(s){
                        if(s.split(',').indexOf(rty_IDs)<0){
                            rty_IDs = rty_IDs+','+s;    
                        }else{
                            rty_IDs = s;    
                        }
                    }
                }
                
                mainquery.unshift({t:rty_IDs});
            }            
            
            $.each(this.sort_array, function(i, ele){
                let val = ele.searchBuilderSort('getValue');
                if(val){
                    mainquery.push({sortby:val});    
                }    
            });
            
            this.pnl_Result.text( JSON.stringify(mainquery) );    
        } 
        
        let conjunct = (this.search_conjunction.val()=='any')?'OR':'AND';
        let $fields_headers = $('.field_header');
        let cnt = $fields_headers.length;

        for(let i=1; i<cnt; i++){
            $($fields_headers[i]).text(conjunct);
        }
        
        // Update accordion header
        let sortby_header = '';
        $.each(this.sort_array, function(i, ele){
            let lbl = ele.searchBuilderSort('getLabel');
            if(lbl != ''){
                sortby_header += lbl + ', ';
            }
        });

        sortby_header = sortby_header.substring(0, sortby_header.length - 2);
        this.sortbySection.find('#sortby_header #sortby_values').text(sortby_header);
    }

    //
    // Show ruleset editor popup
    //
    ,_editRules: function() {

        let that = this;
        let ruleset = this.rulesetSection.find('textarea').val();

        let url = window.hWin.HAPI4.baseURL+ "hclient/widgets/search/ruleBuilderDialog.php?db=" + window.hWin.HAPI4.database;
        if(!window.hWin.HEURIST4.util.isempty(ruleset)){
            url = url + '&rules=' + encodeURIComponent(ruleset);
        }else if (this.select_main_rectype.val()>0){
            url = url + '&rty_ID=' + this.select_main_rectype.val();
        }
        
        if(window.hWin.HEURIST4.util.isFunction(this.options.menu_locked)){
            this.options.menu_locked.call( this, true, false); //lock
        }

        window.hWin.HEURIST4.msg.showDialog(url, { 
            is_h6style:this.options.is_h6style, 
            closeOnEscape:true, width:1200, height:600, 
            title:'Ruleset Editor', 
            close:function(){
                if(window.hWin.HEURIST4.util.isFunction(this.options.menu_locked)){
                    this.options.menu_locked.call( this, false, false); //unlock
                }
            },
            callback: function(res){
                if(!window.hWin.HEURIST4.util.isempty(res)) {
                    that.rulesetSection.find('textarea').val( JSON.stringify(res.rules) ); //assign new rules
                }
            }
        });
    }
});

//
//
//
function showSearchBuilder( params ){
    
        let manage_dlg = $('#heurist-searchBuilder');
        
        params = (!params)?{is_h6style:true}:params;
        
        params.is_dialog = true;

        let need_create = (manage_dlg.length<1);
        
        if( need_create ){

            manage_dlg = $('<div id="heurist-searchBuilder">')
            .addClass('save-filter-dialog')
            .appendTo( $('body') );
            manage_dlg.searchBuilder( params );
        }else{
            if(!params.is_modal && params.params==null){
                params.params = manage_dlg.searchBuilder('option', 'params');
            }
            
            manage_dlg.searchBuilder('option', params);
        }

        manage_dlg.searchBuilder( 'show' );
}