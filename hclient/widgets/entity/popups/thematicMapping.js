/**
* thematicMapping.js - Find duplicates by record type and selected fields
*                           It uses levenshtein function on server side
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

/*

It creates thematic map in the following format

{
"title":"name of thematic map",
"symbol":{}, //base symbology. if it is not defined use default layer symbology
"fields":[
{"code":"10:123",  //rules: field id or rt:dt to search field in linked records
    "title":"Person weight","type":"integer",  //besides standard field type we can use aggregate types: sum, count, avg
    "range_type": "exact|equal|log"  // exact (default) - one value or list of values or exact range (suitable for all), 
                                     // equal - values be split by ranges according to current min/max suitable for numeric and dates
                                     // log - logariphmic
    "ranges":[  //sample of exact ranges
       {"value":"40", "title":"","symbol":{ } }, //symbology that overwrites some properties of base symbology
       {"value":"40<>50", "symbol":{ } },
       {"value":"69,71,75", "symbol":{ } }
    
    //sample equal of log ranges - need to find min/max values beforehand
    "range_count":5,   
    "ranges":[  
       {"value":0,symbol":{ } }, //symbology that overwrites some properties of base symbology
       ....
       {"value":5,symbol":{ } }
    ]
},
{"code":"10:15","title":"Gender","type":"enum",
....
}
],
"rectypes":["10"]
}

{
"fields":[}
12:1109



*/
$.widget( "heurist.thematicMapping", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 780,
        width:  1100,
        modal:  true,
        title:  'Define thematic mapping',
        default_palette_class: 'ui-heurist-design', 
        
        maplayer_query: null,
        thematic_mapping: null, //json with all symbols/ranges
        
        path: 'widgets/entity/popups/', //location of this widget
        
        htmlContent: 'thematicMapping.html'
    },
    
    baseLayerSymbol: null, //from layer 
    currentField: 0,
    selectedFields:{},
    enumValues: null, 
    fieldSelected: null,
    popele: null, //element for popup dialog
    maplayer_ids: null, //list of ids from map layer - result of maplayer_query 
    
    _destroy: function() {
        this._super(); 
        
        let treediv = this.element.find('.rtt-tree');
        if(!treediv.is(':empty') && treediv.fancytree("instance")){
            treediv.fancytree("destroy");
        }
        treediv.remove();
        
    },
        
    //    
    //
    //
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Save thematic map');
        res[0].text = window.hWin.HR('Cancel');
        return res;
    },    
        
    //
    // Fills record type selector. Parent method is overwritten
    //
    _fillSelectRecordScope: function (){

        this.selectRecordScope.empty();

        
        let fields_sel = this.element.find('#selected_fields');
        
        this._on(fields_sel,{change: this._onThemeFieldSelect});

        this._on(this.element.find('button[id^="btn_f"]').button(), 
                                    {click:this._onThemeFieldAction});
        this.element.find('button[id^="btn_f_range_add"]').button({icon:'ui-icon-circle-b-plus'});
        
        this._initSymbolEditor(this.element.find('#tm_symbol'));
        
        this._on(this.element.find('#tm_symbol'), {change:function(){
            this._renderSymbolPreview( null, //update all 
                        this.mapDefaultSymbol, this.element.find('#tm_symbol').val(), null);
        }});
        
        
        this.options.thematic_mapping = window.hWin.HEURIST4.util.isJSON( this.options.thematic_mapping );
        
        //load list of thematic maps
        let themes_list = this.element.find('#thematic_maps_list');
        this._on(themes_list,{change: this._onThematicMapSelect});
        themes_list.empty();
        
        if(this.options.thematic_mapping)
        {
            if(!Array.isArray(this.options.thematic_mapping)) this.options.thematic_mapping = [this.options.thematic_mapping];
        }else{
            this.options.thematic_mapping = [];//
        }
            
        let i=0;
        while(i<this.options.thematic_mapping.length){
            let t_map = this.options.thematic_mapping[i];
            if(t_map.fields){ //with fields - thematic map
                window.hWin.HEURIST4.ui.addoption(themes_list[0], i, t_map.title);
                i++;
            }else{ //without fields - this is base symbol
                this.baseLayerSymbol = t_map;
                this.options.thematic_mapping.splice(i,1);
            }
        }
        
        //default layer symbol
        let def_style = window.hWin.HEURIST4.util.isJSON(this.baseLayerSymbol);
        if(!def_style){
            def_style = window.hWin.HAPI4.get_prefs('map_default_style');
            if(def_style) def_style = window.hWin.HEURIST4.util.isJSON(def_style);
        }
        this.mapDefaultSymbol = window.hWin.HEURIST4.ui.prepareMapSymbol(def_style, null);

        if(themes_list.find('option').length==0){
            this._addThematicMap();
        }else{
            themes_list[0].selectedIndex = 0;
            themes_list.trigger('change');
        }
        
        this._on(this.element.find('#btn_map_remove').button(), 
                                    {click:this._onThematicMapDelete});

        this._on(this.element.find('#btn_tm_add'),
                                    {click:this._addThematicMap});
                        
        this.popele = this.element.find('#divAutoRanges');
        this._on(this.popele.find('input,select'),{change:this._definePreviewRanges});
        
        
        //
        //
        //
        let selScope = this.selectRecordScope.get(0);
        this.selectRecordScope = window.hWin.HEURIST4.ui.createRectypeSelectNew( selScope,
        {
            topOptions: [{key:'-1',title:'select record type...'}],
            useHtmlSelect: false,
            useCounts: false,
            showAllRectypes: true
        });
        this._on( this.selectRecordScope, {
                change: this._onRecordScopeChange} );        
        
        selScope = this.selectRecordScope.get(0);
        window.hWin.HEURIST4.ui.initHSelect(selScope);
        
        
        //
        // search record types in map query
        //
        if(this.options.maplayer_query){
            
            let request = { q: this.options.maplayer_query,
                    w: 'a',
                    detail: 'count_by_rty'};

            let that = this;
            window.HAPI4.RecordMgr.search(request, function(response){ 

                if(response.status == window.hWin.ResponseStatus.OK){

                    if(response.data && $.isPlainObject(response.data.recordtypes)){
                        let rty_IDs = Object.keys(response.data.recordtypes);
                        
                        if(rty_IDs.length>0){

                            for(let i=0; i<rty_IDs.length; i++){
                                let name = window.hWin.HEURIST4.util.htmlEscape($Db.rty(rty_IDs[i], 'rty_Name'));
                                
                                let option = document.createElement("option");
                                option.text = name;
                                option.value = rty_IDs[i];
                                $(option).attr('depth', 1);
                                selScope.insertBefore(option, selScope.options[1]);
                            }
                            
                            let option = document.createElement("option");
                            option.text = 'Record types in layer';
                            option.disabled = 'disabled'
                            $(option).attr('group', 1);
                            selScope.insertBefore(option, selScope.options[1]);
                            
                            that.selectRecordScope.val(rty_IDs[0])
                            that.selectRecordScope.hSelect('refresh');
                            that.selectRecordScope.trigger('change');
                            
                            
                            if(response.data.count<1000){
                                //search ids
                                let request2 = { q: that.options.maplayer_query,
                                        w: 'a',
                                        detail: 'ids'};
                                window.HAPI4.RecordMgr.search(request2, function(response){ 
                                    if(response && response.data && Array.isArray(response.data.records)){
                                        that.maplayer_ids = response.data.records.join(',');                                    
                                    }
                                    
                                });
                            }                            
                            
                        }
                    }
                    
                }else{
                    console.error(response.message);
                }
            });            
        
        }else{
            this._onRecordScopeChange();
        }
        
    },
            
    //
    // Save thematic mapping
    //
    doAction: function(){
        
        //get values
        if(this._saveThematicMap()){
            if(this.baseLayerSymbol){
                this.options.thematic_mapping.unshift(this.baseLayerSymbol);
            }
            this._context_on_close =  this.options.thematic_mapping;
            this.closeDialog();
        }
        
        
    },
    
    //
    //
    //
    _hideProgress: function (){
        this._super(); 
        this.element.find('#div_search').show();  
    },
    
    //
    // overwritten - reload treeview
    //
    _onRecordScopeChange: function() 
    {
        let isdisabled = this._super();
        
        
        
        let rtyID = this.selectRecordScope.val();
        
        if(this._selectedRtyID!=rtyID ){
            if(rtyID>0){
                //reload treeview
                this._loadRecordTypesTreeView( rtyID );
            }
        }
        
        return isdisabled;
    },
    
    //
    // show treeview with record type structure
    //
    _loadRecordTypesTreeView: function(rtyID){
        
        let that = this;

        if(this._selectedRtyID!=rtyID ){
            
            this._selectedRtyID = rtyID;

            let allowed_fieldtypes = [//'rec_Title','rec_ID',
                'enum','year','date','integer','float','resource']; //'freetext',
            
            //generate treedata from rectype structure
            let treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, rtyID, allowed_fieldtypes );
            
        //load treeview
        let treediv = this.element.find('.rtt-tree');
        if(!treediv.is(':empty') && treediv.fancytree("instance")){
            treediv.fancytree("destroy");
        }
        
        treedata[0].expanded = true;

        treediv.fancytree({
            //extensions: ["filter"],
            //            extensions: ["select"],
            //checkbox: true,
            //selectMode: 3,  // single
            checkbox: false,
            selectMode: 1,  // single
            source: treedata,
            beforeSelect: function(event, data){
                // A node is about to be selected: prevent this, for folder-nodes:
                if( data.node.hasChildren() ){

                    if(data.node.isExpanded()){
                        for(let i = 0; i < data.node.children.length; i++){
                            let node = data.node.children[i];

                            if(node.key == 'term'){ // if node is a term
                                node.setSelected(true); // auto select 'term' option to add term name
                            }
                        }
                    }
                    return false;
                }
            },
            renderNode: function(event, data){
                /*
                if(false && data.node.type == "enum") { 
                    // hide blue and expand arrows for terms
                    $(data.node.span.childNodes[0]).hide();
                    $(data.node.span.childNodes[1]).hide();
                }*/
                if(data.node.parent && (data.node.parent.type == 'resource' || data.node.parent.type == 'rectype')){ // add left border+margin
                    $(data.node.li).attr('style', 'border-left: black solid 1px !important;margin-left: 9px;');
                }
                if(!(data.node.type == 'resource' || data.node.type == 'rectype'))
                {
                    //define action button
                   
                    let item = $(data.node);
                    let item_li = $(data.node.li);
                    if($(item).find('.svs-contextmenu3').length==0){
                     
                        let parent_span = item_li.children('span.fancytree-node');

                        //add icon
                        let actionspan = $('<div class="svs-contextmenu3" style="padding: 0px 20px 0px 0px;" data-parentid="'
                        +item.data.parent_id+'" data-code="'+data.node.key+'">'
                        +'<span class="ui-icon ui-icon-circle-b-plus" title="Add field" style="font-size:0.9em"></span>'
                        +'</div>').appendTo(parent_span);
                        
                        actionspan.find('.ui-icon-circle-b-plus').on('click', function(event){
                            let ele = $(event.target);
                            window.hWin.HEURIST4.util.stopEvent(event);
                            that._addThemeField( ele.parents('[data-code]').attr('data-code') );                           
                        });
                        
                        //hide icons on mouse exit
                        function _onmouseexit(event){
                            let node;
                            if($(event.target).is('li')){
                                node = $(event.target).find('.fancytree-node');
                            }else if($(event.target).hasClass('fancytree-node')){
                                node =  $(event.target);
                            }else{
                                //hide icon for parent 
                                node = $(event.target).parents('.fancytree-node');
                                if(node) node = $(node[0]);
                            }
                            let ele = node.find('.svs-contextmenu3');
                            ele.hide();
                        }               

                        function _onmouseenter(event){
                                let node;
                                if($(event.target).hasClass('fancytree-node')){
                                    node =  $(event.target);
                                }else{
                                    node = $(event.target).parents('.fancytree-node');
                                }
                                if(! ($(node).hasClass('fancytree-loading') )){
                                    let ele = $(node).find('.svs-contextmenu3');
                                    ele.css({'display':'inline-block'});//.css('visibility','visible');
                                }
                            }

                        $(parent_span).on('mouseenter',
                            _onmouseenter
                        ).on('mouseleave',
                            _onmouseexit
                        );               
                        
                    }
                    
                    /*
                    if(data.node.parent && data.node.parent.type == 'enum'){ // make term options inline and smaller
                        $(data.node.li).css('display', 'inline-block');
                        $(data.node.span.childNodes[0]).css('display', 'none');

                        if(data.node.key == 'term'){
                            $(data.node.parent.ul).css({'transform': 'scale(0.8)', 'padding': '0px', 'position': 'relative', 'left': '-12px'});
                        }
                    }
                    */
                }
                if(data.node.type == 'separator'){
                    $(data.node.span).attr('style', 'background: none !important;color: black !important;'); //stop highlighting
                    $(data.node.span.childNodes[1]).hide(); //checkbox for separators
                }
            },
            lazyLoad: function(event, data){
                let node = data.node;
                let parentcode = node.data.code; 
                let rectypes = node.data.rt_ids;

                let res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, 
                    rectypes, allowed_fieldtypes, parentcode );
                if(res.length>1){
                    data.result = res;
                }else{
                    data.result = res[0].children;
                }

                return data;                                                   
            },
            loadChildren: function(e, data){
                setTimeout(function(){
                   
                    },500);
            },
            select: function(e, data) {
               
            },
            click: function(e, data){

                if(data.node.type == 'separator'){
                    return false;
                }

                let isExpander = $(e.originalEvent.target).hasClass('fancytree-expander');
                let setDefaults = !data.node.isExpanded();

                if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){
                    
                    if(!isExpander){
                        data.node.setExpanded(!data.node.isExpanded());
                    }
                
                    if(setDefaults){
                        for(let i = 0; i < data.node.children.length; i++){
                            let node = data.node.children[i];

                            if(node.key == 'term'){ // if node is a term
                                node.setSelected(true); // auto select 'term' option to add term name
                            }
                        }
                    }
                }else if( data.node.lazy && !isExpander) {
                    data.node.setExpanded( true );
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
            
        }   
    },

    //
    //
    //
    _addThematicMap: function(){
        
        if(this._saveThematicMap()){
        
            let last_idx = this.options.thematic_mapping.length;
            const newname = 'New Thematic map';
            this.options.thematic_mapping.push({title:newname, active:false, fields:[]});

            
            let themes_list = this.element.find('#thematic_maps_list');
            window.hWin.HEURIST4.ui.addoption(themes_list[0], last_idx, newname);
           
           
            
            themes_list[0].selectedIndex = last_idx;
            themes_list.trigger('change');
        }
    },
    
    
    //
    // 
    //
    _onThematicMapDelete: function(unconditional){
        if(this.currentThemeIdx>=0){
            
            let that = this;

            if(unconditional!==true){    
                window.hWin.HEURIST4.msg.showMsgDlg('<br>Are you sure?',
                    function(){that._onThematicMapDelete(true)});
                return;
            }
            
            let themes_list = this.element.find('#thematic_maps_list');
            this.options.thematic_mapping.splice(this.currentThemeIdx, 1);
            //remove from select
            themes_list.find('option:eq(' + this.currentThemeIdx + ')').remove();
            
            if(this.options.thematic_mapping.length==0){
                //offer exit
                
                window.hWin.HEURIST4.msg.showMsgDlg('<br>You removed all thematic maps. Exit this form?',
                    function(){
                        that._context_on_close =  (that.baseLayerSymbol)?that.baseLayerSymbol:'';    
                        that.closeDialog();
                    });
                
            }else{
                this.currentField = 0;
                this.currentThemeIdx = -1;
                themes_list[0].selectedIndex = 0;
                themes_list.trigger('change');
            }
        }
    },
    
    //
    // 
    //
    _saveThematicMap: function(){
        
        if(this.currentThemeIdx>=0){
        
            let t_map = this.options.thematic_mapping[this.currentThemeIdx];
        
            t_map.title = this.element.find('#tm_name').val();
            t_map.active = this.element.find('#tm_active').is(':checked');
            t_map.symbol = window.hWin.HEURIST4.util.isJSON(this.element.find('#tm_symbol').val());
            if(!t_map.symbol) t_map.symbol =  '';

            t_map.fields = [];
            
            this._saveThemeField();
            
            this.currentField = 0;
            
            let len = Object.keys(this.selectedFields).length;
            for (let k=0;k<len;k++){
                const key = Object.keys(this.selectedFields)[k];
                if(this.selectedFields[key].ranges.length>0){
                    t_map.fields.push( this.selectedFields[key] );    
                }
            }
            
            if(window.hWin.HEURIST4.util.isempty(t_map.title)){
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: 'Title is mandatory',
                    error_title: 'Missing title'
                });
                return false;
            }
            if(t_map.fields.length==0){
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: 'Need to define at least one field with ranges/categories',
                    error_title: 'Missing field'
                });
                return false;
            }

            //rename in the list
            this.element.find('#thematic_maps_list').find('option:eq(' + this.currentThemeIdx + ')').html(t_map.title);
            
            this.options.thematic_mapping[this.currentThemeIdx] = t_map;
            
        }
        
        return true;
    },

    //
    // load thematic map on selection in the list
    //
    _onThematicMapSelect: function( event ){
        
        if(this._saveThematicMap()){
        
            this.currentThemeIdx = event?event.target.selectedIndex:0;
            
            let t_map = this.options.thematic_mapping[this.currentThemeIdx];
        
            this.element.find('#tm_name').val(t_map.title);
            this.element.find('#tm_active').prop('checked', t_map.active);
            
            let base_symbol = window.hWin.HEURIST4.util.isJSON( t_map.symbol );
            base_symbol = (!base_symbol)?'':JSON.stringify(base_symbol);
            this.element.find('#tm_symbol').val(base_symbol);
            
            this.selectedFields = {};
            
            let flds = t_map.fields;
            
            let fields_sel = this.element.find('#selected_fields');
            fields_sel.empty();
            
            if(flds){
                for(let i=0; i<flds.length; i++){
                    let fld = flds[i];
                    let key = fld.code.split(':');
                    key = key[key.length-1];//dty_ID
                    
                    this.selectedFields[key] = fld;
                    
                    window.hWin.HEURIST4.ui.addoption(fields_sel[0], key, fld.title+' ('+fld.code+'  '+key+')');
                }
                
                fields_sel[0].selectedIndex = 0;
                fields_sel.trigger('change');
            }        
        }else{
             document.getElementById('thematic_maps_list').selectedIndex = this.currentThemeIdx;
        }
        
    },
    
    //-----------------------
    //
    //
    //
    _addThemeField: function( nodekey )
    {
        let tree = $.ui.fancytree.getTree( this.element.find('.rtt-tree') );
        
        let node = tree.getNodeByKey(nodekey);
        
        let key = node.key.split(':');
        key = key[key.length-1];
        
        if(!this.selectedFields[key]){
            this.selectedFields[key] = {code:node.data.code, title:node.data.name, ranges:[]};
            let sel = this.element.find('#selected_fields');
            
            //+' ('+this.selectedFields[key].code+'  '+key+')'
            window.hWin.HEURIST4.ui.addoption(sel[0], key, this.selectedFields[key].title);
            
            if(!(sel[0].selectedIndex>0)){
                sel[0].selectedIndex = 0;  
                sel.trigger('change');
            } 
        } 
        
    },
    
    
    
    //
    //
    //
    _saveThemeField: function(){
        
        if(this.currentField>0){
            let selfield = this.selectedFields[this.currentField];
            //get values from UI
            let main_area = this.element.find('#div_work_area');        
            let f_ranges = main_area.find('#f_ranges');
            
            if(f_ranges.children().length>0){
                $.each(f_ranges.children(), function(i, ele){

                    ele = $(ele);
                    
                    let range_uid = ele.attr('id');

                    $.each(selfield.ranges,function(i,item){
                        if(item.uid  == range_uid){
                            
                            let val1;
                            if(ele.find('select.val1').length>0){
                                val1 = ele.find('select.val1').val();
                            }else{
                                val1 = ele.find('input.val1').val();
                                let val2 = ele.find('input.val2').val();
                                if(val1 && val2) {
                                    val1 = val1+'<>'+val2
                                }else if(val2) {
                                    val1 = val2;
                                }
                            }
                            
                                
                            if(val1){
                                selfield.ranges[i].value = val1;
                                let symb = window.hWin.HEURIST4.util.isJSON(ele.find('.field-symbol').val());
                                selfield.ranges[i].symbol = symb?symb:'';
                                selfield.ranges[i].uid = null;
                                delete selfield.ranges[i].uid;
                            }else{
                                //remove empty value ranges
                                selfield.ranges.splice(i,1);        
                            }
                            
                            return false;
                        }
                    });
                });
            }else{
                selfield.ranges = [];
            }

            selfield.title = main_area.find('#f_title').val();
            
            this.selectedFields[this.currentField] = selfield;
        }
        
    },
    
    //
    //  saves previous field/range settings 
    //  and shows title and ranges for current field
    //
    _onThemeFieldSelect: function(event){                                                           
        
        let main_area = this.element.find('#div_work_area');        
        let f_ranges = main_area.find('#f_ranges');
        let selfield;
        
        //save previous
        this._saveThemeField();
        
        this.currentField = event?$(event.target).val():0;
        
        f_ranges.empty()
        
        if(this.currentField>0){
            this.element.find('#div_work_area').show();    
        
            selfield = this.selectedFields[this.currentField];
            
            //add title
            main_area.find('#f_title').val(selfield.title);
            
            //add ranges elements
            for (let k=0;k<selfield.ranges.length;k++){
                this._defThemeFieldRange(k, selfield.ranges[k]);
            }
        }else{
            this.element.find('#div_work_area').hide();    
        }
    },
    
    //
    // inits and returns range element
    //
    _defThemeFieldRange: function(idx, range){   

        let selfield = this.selectedFields[this.currentField];
        let key = selfield.code.split(':');
        key = key[key.length-1];//dty_ID
        let dty_Type = $Db.dty(key, 'dty_Type');
        let vocab_id = $Db.dty(key, 'dty_JsonTermIDTree');

        let ele = $('<div style="padding:5px" class="field-range">'
            +'<span class="ui-icon ui-icon-circle-b-close" style="margin:2px 0 0 12px;cursor:pointer"/>'
            + ((dty_Type=='enum')
            ? '<select class="val1 text ui-widget-content ui-corner-all" style="width:100px;margin-left:5px"/>'
            : ('<input class="val1 text ui-widget-content ui-corner-all" style="width:100px;margin-left:5px"/>'
              +'<span>&nbsp;&lt;&gt;&nbsp;</span><input class="val2 text ui-widget-content ui-corner-all" style="width:100px"/>'))
            +'<span class="field-symbol-preview" style="position:relative;top:9px;display:inline-block;width:40px;height:40px;margin:2px"/>'
            +'<input class="field-symbol text ui-widget-content ui-corner-all" style="width:250px"/>'
            +'</div>').appendTo(this.element.find('#f_ranges'));

        ele.uniqueId();
        let uid = ele.attr('id');
        range.uid = uid;

        let val1 = range.value, val2 = '';
        if(val1 && val1.indexOf('<>')>0){
            let vals = val1.split('<>');
            val2 = (vals && vals.length==2)?vals[1]:'';
            val1 = (vals && vals.length==2)?vals[0]:'';
        }

        this._on(ele.find('.field-symbol'), {change:function(event){
            
            this._renderSymbolPreview( $(event.target).parent().find('.field-symbol-preview'), 
                    this.mapDefaultSymbol, this.element.find('#tm_symbol').val(), $(event.target).val());
        }});
        
        if(dty_Type=='enum'){
            window.hWin.HEURIST4.ui.createTermSelect(ele.find('select.val1')[0],
                {vocab_id:vocab_id, //headerTermIDsList:headerTerms,
                    defaultTermID:val1, supressTermCode:true, 
                    useHtmlSelect:false});                
        }else{
            ele.find('input.val1').val(val1)
            ele.find('input.val2').val(val2);
        }
        
        if(range.symbol){
            ele.find('.field-symbol').val($.isPlainObject(range.symbol)?JSON.stringify(range.symbol):range.symbol);    
        }
        ele.find('.field-symbol').trigger('change');

        this._initSymbolEditor( ele.find('.field-symbol') ); 
        
        
        this._on(ele.find('.ui-icon-circle-b-close'),{click:function(event){
            let ele = $(event.target).parents('.field-range');
            let range_uid = ele.attr('id');
            ele.remove();

            let selfield = this.selectedFields[this.currentField];

            $.each(selfield.ranges,function(i,item){
                if(item.uid  == range_uid){
                    selfield.ranges.splice(i,1);     
                    return false;
                }
            });
        }});

    },
    
    //
    //
    //
    _initSymbolEditor: function(fele){
        
        let f_ranges = this.element.find('#f_ranges');
        fele.attr('readonly','readonly');
        
        $('<span>')
        .addClass("smallbutton ui-icon ui-icon-circlesmall-close")
        .attr('tabindex', '-1')
        .attr('title', 'Reset default symbology')
        .appendTo( f_ranges.find(fele.parent()) )
        .css({'line-height': '20px',cursor:'pointer',
            outline: 'none','outline-style':'none', 'box-shadow':'none',  'border-color':'transparent'})
            .on( { click: function(){ window.hWin.HEURIST4.msg.showMsgDlg('<br>Are you sure?',
                function(){fele.val('');}); }});
        
        let $btn_edit_switcher = $( '<span>open editor</span>', {title: 'Open symbology editor'})
            .addClass('smallbutton btn_add_term')
            .css({'line-height': '20px',cursor:'pointer','text-decoration':'underline'}) //'vertical-align':'top'
            .appendTo( fele.parent('div') );
        
        $btn_edit_switcher.on( { click: function(){
                let current_val = window.hWin.HEURIST4.util.isJSON( fele.val() );
                if(!current_val) current_val = {};
                window.hWin.HEURIST4.ui.showEditSymbologyDialog(current_val, 4, function(new_value){
                    fele.val(JSON.stringify(new_value)).trigger('change');
                });
        }});
            
    },//_initSymbolEditor
    
    //
    //  toolbar button action
    //
    _onThemeFieldAction: function(event){
        
        if(this.currentField>0){
            
            let that = this;
        
            let key = $(event.target).is('button')
                                ?$(event.target).attr('id')
                                :$(event.target).parent().attr('id');
            
            if(key=='btn_f_remove'){
                window.hWin.HEURIST4.msg.showMsgDlg('<br>Are you sure?',
                function(){

                that.element.find('#selected_fields').find('option[value="'+that.currentField+'"]').remove();                
                that.selectedFields[that.currentField] = null;
                delete that.selectedFields[that.currentField];
                that.currentField = 0;
                that._onThemeFieldSelect();
                
                });
                
            }else if(key=='btn_f_range_add'){
                
                let selfield = this.selectedFields[this.currentField];
                let idx = selfield.ranges.length
                selfield.ranges.push({value:'', symbol:''});
                
                this._defThemeFieldRange(idx, selfield.ranges[idx]);
            
            }else if(key=='btn_f_range_auto'){
                
                //find min/max and unique values show ranges dialog
                let selfield = this.selectedFields[this.currentField];
                this._defineAutoRanges(selfield.code);
            
            }else if(key=='btn_f_range_reset'){
                
                window.hWin.HEURIST4.msg.showMsgDlg('<br>Are you sure?',
                function(){
                    that.selectedFields[that.currentField].ranges = [];
                    that.element.find('#f_ranges').empty();
                });
                
            }else if(key=='btn_f_range_symb'){

                let selfield = this.selectedFields[this.currentField];
                let cnt = selfield.ranges.length;
                
                window.hWin.HEURIST4.ui.showEditSymbologyDialog({}, 5, function(new_value){
                    let fillGradient = [], colorGradient = [], strokeOpacity = [], fillOpacity = [], iconSize = [];
                    if(new_value.fillColor1 && new_value.fillColor2){
                        fillGradient = window.hWin.HEURIST4.ui.getColourGradient(new_value.fillColor1, new_value.fillColor2, cnt);
                    }
                    if(new_value.strokeColor1 && new_value.strokeColor2){
                        colorGradient = window.hWin.HEURIST4.ui.getColourGradient(new_value.strokeColor1, new_value.strokeColor2, cnt);
                    }
                    
                    function __prepareInt(val){
                        if(!window.hWin.HEURIST4.util.isNumber(val)){
                            val = 0
                        }else{
                            val = parseInt(val);
                        }
                        return val;
                    }

                    new_value.fillOpacity1 = __prepareInt(new_value.fillOpacity1);
                    new_value.fillOpacity2 = __prepareInt(new_value.fillOpacity2);

                    if(new_value.fillOpacity1>0 || new_value.fillOpacity2>0){
                        let step = (new_value.fillOpacity2 - new_value.fillOpacity1)/cnt;
                        let val = new_value.fillOpacity1;
                        for(let i=0; i<cnt; i++){
                            fillOpacity.push((i==cnt-1 || val>new_value.fillOpacity2)?new_value.fillOpacity2:val);
                            val = Math.round(val + step);
                        }
                    }

                    new_value.strokeOpacity1 = __prepareInt(new_value.strokeOpacity1);
                    new_value.strokeOpacity2 = __prepareInt(new_value.strokeOpacity2);

                    if(new_value.strokeOpacity1>0 || new_value.strokeOpacity2>0){
                        let step = (new_value.strokeOpacity2 - new_value.strokeOpacity1)/cnt;
                        let val = new_value.strokeOpacity1;
                        for(let i=0; i<cnt; i++){
                            strokeOpacity.push((i==cnt-1 || val>new_value.strokeOpacity2)?new_value.strokeOpacity2:val);
                            val = Math.round(val + step);
                        }
                    }

                    new_value.iconSize1 = __prepareInt(new_value.iconSize1);
                    new_value.iconSize2 = __prepareInt(new_value.iconSize2);

                    if(new_value.iconSize1>0 || new_value.iconSize2>0){
                        let step = (new_value.iconSize2 - new_value.iconSize1)/cnt;
                        let val = new_value.iconSize1;
                        for(let i=0; i<cnt; i++){
                            iconSize.push((i==cnt-1 || val>new_value.iconSize2)?new_value.iconSize2:val);
                            val = Math.round(val + step);
                        }
                    }

                    
                    let f_ranges = that.element.find('#f_ranges');
                    
                    for(let i=0; i<cnt; i++){
                        
                        let symbol = window.hWin.HEURIST4.util.isJSON(selfield.ranges[i].symbol);
                        if(!symbol) symbol = {};
                        if(fillGradient.length>0){
                            symbol.fillColor = fillGradient[i];
                        }
                        if(colorGradient.length>0){
                            symbol.color = colorGradient[i];
                        }
                        if(fillOpacity.length>0){
                            symbol.fillOpacity = fillOpacity[i];
                        }
                        if(strokeOpacity.length>0){
                            symbol.opacity = strokeOpacity[i];
                        }
                        if(iconSize.length>0){
                            symbol.iconSize = iconSize[i];
                        }
                        
                        selfield.ranges[i].symbol = symbol;
                        //assign to UI
                        f_ranges.find('.field-range[id='+selfield.ranges[i].uid+'] > .field-symbol')
                                        .val(JSON.stringify(symbol)).trigger('change');
                    }
                });
                
                
            }
            
        }
    },
    
    //
    // Searches for min/max and unique values for given field
    //
    _defineAutoRanges: function(code){
        
        let $dlg;
        
        let field = window.hWin.HEURIST4.query.createFacetQuery(code, true, false);
        field['type'] = $Db.dty(field['id'], 'dty_Type');
         
        this.popele.find('.numeric').hide();

        if (field['type']=='enum'){
            this.popele.find('.enum').show();
        }else{ 
            //'year','date','integer','float','resource'
            this.popele.find('.enum').hide();
            if(field['type']=='date'){
                this.popele.find('.date').show();    
            }else{
                this.popele.find('.numeric').show();    
            }
        }
        
        this.fieldSelected = null;
        this.enumValues = null;
        this.popele.find('input').val('');
        this.popele.find('#int_count').val(10);
        
        let that = this;
        //
        // substitute $IDS in facet query with list of ids OR current query(todo)
        // 
        if(this.options.maplayer_query){

            function __fillQuery(q){
                $(q).each(function(idx, predicate){

                    $.each(predicate, function(key,val)
                        {
                            if( Array.isArray(val) || $.isPlainObject(val) ){
                                __fillQuery(val);
                            }else if( (typeof val === 'string') && (val == '$IDS') ) {
                                //substitute with array of ids
                                predicate[key] = that.maplayer_ids?that.maplayer_ids:that.options.maplayer_query;
                            }
                    });                            
                });
            }        


            let query;
            if( (typeof field['facet'] === 'string') && (field['facet'] == '$IDS') ){ //this is field form target record type
                //replace with list of ids
                query = this.options.maplayer_query;

            }else{
                if(!this.maplayer_ids && !window.hWin.HEURIST4.util.isJSON(this.options.maplayer_query)){
                    window.hWin.HEURIST4.msg.showMsgDlg('To allow thematic map be based on values from linked records, '
                    +'Map layer query must be in JSON format');
                    return;
                }
                
                query = window.hWin.HEURIST4.util.cloneJSON(field['facet']); //clone 
                //change $IDS for current set of target record type
                __fillQuery(query);                
            }

            let request = {q: query, count_query:null, w: 'a', a:'getfacets',
                facet_index: 0, 
                field:  field['id'],
                type:   field['type'],
                step:   0,
                facet_type: 1, //0 direct search search, 1 - select/slider, 2 - list inline, 3 - list column
                facet_groupby: null, //by first char for freetext, by year for dates, by level for enum
                vocabulary_id: null, //special case for firstlevel group - got it from field definitions
                needcount: 0,         
                qname:'',
                //request_id:this._request_id,
                source:this.element.attr('id') }; //, facets: facets

            window.HAPI4.RecordMgr.get_facets(request, function(response){ 
                if(response.status == window.hWin.ResponseStatus.OK){

                    that.fieldSelected = field;

                    if(field['type']=='enum'){
                        that.enumValues = response.data;
                    }else{
                        try{
                            that.popele.find('#int_min').val(response.data[0][0]);
                            that.popele.find('#int_max').val(response.data[0][1]);
                        }catch{
                            console.error('cannot assign min max values');
                        }
                    }

                    that._definePreviewRanges();
                    
                }else{
                    console.error(response.message);
                }
            });            
        }
        
            
        
        let btns = [
                    {text:window.hWin.HR('Apply'),
                        click: function(){

                            $dlg.dialog('close');

                            //create new ranges
                            if(that.preview_ranges.length>0){
                                
                                let selfield = that.selectedFields[that.currentField];
                                selfield.ranges = [];
                                
                                let main_area = that.element.find('#div_work_area');        
                                main_area.find('#f_ranges').empty();
                                
                                //add ranges elements
                                for (let k=0;k<that.preview_ranges.length;k++){
                                    let range = that.preview_ranges[k];
                                    selfield.ranges.push({value:$.isPlainObject(range)?(range.min+'<>'+range.max):range, symbol:''})
                                    that._defThemeFieldRange(k, selfield.ranges[k]);
                                }                                
                                
                            }
                        }
                    },
                    {text:window.hWin.HR('Close'),
                        click: function() { $dlg.dialog('close'); }
                    }
                ];

                $dlg = window.hWin.HEURIST4.msg.showElementAsDialog({
                    window:  window.hWin, //opener is top most heurist window
                    title: window.hWin.HR('Define ranges'),
                    width: 400,
                    height: 600,
                    element:  this.popele[0],
                    resizable: true,
                    buttons: btns,
                    default_palette_class: 'ui-heurist-design'
                });        
    },
    
    //
    // Event listener - recalc preview ranges
    //
    _definePreviewRanges: function(){

        this.preview_ranges = [];
        let ranges = [];
        let div_preview = this.popele.find('#ranges_preview').empty();
        
        if(this.fieldSelected==null) return;
        
        let dty_Type = this.fieldSelected['type'];

        if(dty_Type=='enum'){
            
            if(this.popele.find('#enum_db').is(':checked')){
                //actual db values
                for (let i=0; i<this.enumValues.length; i++){
                    ranges.push(this.enumValues[i][0]);
                }
            }else{
                //all available enums 
                let vocab_id = $Db.dty(this.fieldSelected['id'],'dty_JsonTermIDTree');
               
                ranges = $Db.trm_TreeData(vocab_id, 'set');
            }

            for (let i=0; i<ranges.length; i++){
                $('<div style="padding:5px" class="field-range">'
                +'<span style="display:inline-block;width:100px;">'+ranges[i]+'</span>'
                +'<span>'+$Db.trm(ranges[i], 'trm_Label')+'</span>'  
                +'</div>').appendTo(div_preview);
            }
            
        }
        else{

            let minVal = parseFloat(this.popele.find('#int_min').val());
            let maxVal = parseFloat(this.popele.find('#int_max').val());
            let count = parseInt(this.popele.find('#int_count').val());
            let int_round = parseInt(this.popele.find('#int_round').val());
            
            if(isNaN(minVal) || isNaN(maxVal) || isNaN(count) || count<=0 || minVal>maxVal) return;
            
            let step = (maxVal-minVal)/count;
            
            if(dty_Type=='integer'){
                step = Math.round(step);
            }
            
            function __rnd(original){
                if(dty_Type=='float' && int_round<10){
                   
                    //return Math.round(original*multiplier)/multiplier;   
                    return int_round==0?Math.round(original): parseFloat( original.toFixed(int_round) );
                }else if(int_round>=10){
                    return Math.round(original/int_round)*int_round;   
                }else{
                    return original;
                }
            }
            
            if(int_round>=10 && int_round>=maxVal){
                ranges.push({min:minVal, max:maxVal});        
            }else{
                if(int_round>=10 &&  int_round>step){
                    step = int_round;
                }
                
                let cnt = 0;
                let val0 = minVal;
                while (val0<maxVal && cnt<count){
                    
                    let val1 = (val0+step>maxVal)?maxVal:val0+step;
                    if(cnt==count-1 && val1!=maxVal){
                        val1 = maxVal;
                    }else{
                        val1 = __rnd(val1);
                    }
                    
                    ranges.push({min:__rnd(val0), max:val1});    
                    val0 = val1;
                    cnt++;;
                }
            }

            for (let i=0; i<ranges.length; i++){
                $('<div style="padding:5px" class="field-range">'
                +'<span style="display:inline-block;width:100px;">'+ranges[i].min+'</span>'
                +('<span style="display:inline-block;width:50px;">&nbsp;to&nbsp;&lt;&nbsp;</span>'
                +'<span style="display:inline-block;width:100px">'+ranges[i].max+'</span>')
                +'</div>').appendTo(div_preview);
            }
        
        }
        
        this.preview_ranges = ranges;
    },
    
    //
    //
    //
    _renderSymbolPreview: function(ele, layer_symbol, base_symbol, range_symbol){
        
            if(ele==null){
                //update all ranges
                let that = this;
                let f_ranges = this.element.find('#f_ranges');
                if(f_ranges.children().length>0){
                    $.each(f_ranges.children(), function(i, ele){
                        ele = $(ele);

                        that._renderSymbolPreview( ele.find('.field-symbol-preview'), 
                            that.mapDefaultSymbol, that.element.find('#tm_symbol').val(), 
                            ele.find('.field-symbol').val());
                    });
                }
                return;
            }
        
            //theme symbology
            base_symbol = window.hWin.HEURIST4.util.isJSON( base_symbol );
            base_symbol = (base_symbol)?base_symbol:layer_symbol;
            
            base_symbol = window.hWin.HEURIST4.ui.prepareMapSymbol(base_symbol, null)
            
            function __mergeThematicSymbol(basesymbol, fsymb){
                
                    let use_style = window.hWin.HEURIST4.util.cloneJSON( basesymbol );
                    if($.isPlainObject(range_symbol)){
                        let keys = Object.keys(fsymb);
                        for(let j=0; j<keys.length; j++){
                            use_style[keys[j]] = fsymb[keys[j]];
                        }
                    }
                    
                    return use_style;
            }    

            range_symbol = window.hWin.HEURIST4.util.isJSON( range_symbol );
        
            let style = __mergeThematicSymbol(base_symbol, range_symbol);
        
            let dcss = {'display':'inline-block', 'background-image':'none'};
            if(style['stroke']!==false){
                
                let opacity = style['opacity']>0?style['opacity']:1;
                let weight = (style['weight']>0&&style['weight']<10)?style['weight']:10;
                dcss['width']  = 22-weight*2; 
                dcss['height'] = 22-weight*2;
                
                dcss['border'] = weight+'px solid '
                                + window.hWin.HEURIST4.ui.hexToRgbStr(style['color'], opacity);
                if ( style['opacity']>0 && style['opacity']<1 ) {
                    dcss['-webkit-background-clip'] = 'padding-box'; //for Safari
                    dcss['background-clip'] = 'padding-box'; //for IE9+, Firefox 4+, Opera, Chrome
                }
                
            } else {
                dcss['border'] = 'none';
            }

            if(style['fill']!==false){
                let fillColor = style['fillColor']?style['fillColor']:style['color'];
                let fillOpacity = style['fillOpacity']>0?style['fillOpacity']:0.2;
                dcss['background-color'] = window.hWin.HEURIST4.ui.hexToRgbStr(fillColor, fillOpacity);
            }else{
                dcss['background'] = 'none';
            }
                                                
            ele.css(dcss);
                                                        
    }
    
    

});

