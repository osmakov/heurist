/**
* Search header for manageDefRecTypes manager
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

$.widget( "heurist.searchDefRecTypes", $.heurist.searchEntity, {

    //
    _initControls: function() {
        
        let that = this;
        
       
        
        this._super();
        
        
        window.hWin.HRA(this.element);

        //hide all help divs except current mode
        let smode = this.options.select_mode; 
        this.element.find('.heurist-helper1').find('span').hide();
        this.element.find('.heurist-helper1').find('span.'+smode+',span.common_help').show();
        
        this.btn_add_record = this.element.find('.btn_AddRecord');
        this.btn_find_record = this.element.find('#btn_find_record');
        this.btn_csv_import = this.element.find('#btn_csv_import');

        if(this.options.edit_mode=='none' || this.options.import_structure){
            this.btn_add_record.parent().hide();
           
           
            
            let ele = this.element.find('#div_show_all_groups');
            ele.parent().css('float','left');
            ele.hide();
        }else{
            
            this.btn_add_record
                    .button({label: window.hWin.HR('Add'), showLabel:true, 
                            icon:"ui-icon-plus"})
                    .addClass('ui-button-action')
                    .css({padding:'2px'})
                    .show();
                    
            this._on( this.btn_add_record, {
                click: function(){
                    this._trigger( "onadd" );
                }
            });
            
            this.btn_csv_import
                .button({label: window.hWin.HR('Import from CSV'), showLabel:true, 
                            icon:"ui-icon-upload"})
                .addClass('ui-button-action')
                .css({padding:'2.5px'});

            this._on(this.btn_csv_import, {
                click: function(){
                    this._trigger("onimport");
                }
            });
        }
        
        this._on(this.input_search_type,  { change:this.startSearch });
        
        this._on(this.input_search,  { keyup:this.startSearch });

        this.input_sort_type = this.element.find('#input_sort_type');
        this._on(this.input_sort_type,  { change:this.startSearch });
                      
        if( this.options.import_structure ){
            //this.element.find('#div_show_already_in_db').css({'display':'inline-block'});    
            this.chb_show_already_in_db = this.element.find('#chb_show_already_in_db');
            this._on(this.chb_show_already_in_db,  { change:this.startSearch });
            this.element.find('#div_group_information').hide();
            
            this.options.simpleSearch = true;
        }else{
            
            this.input_search.parent().hide();
            this.element.find('#div_group_information').show();
            this.element.find('#div_show_already_in_db').hide();
            this._on(this.element.find('#chb_show_all_groups'),  
                { 
                    change: function(){

                        if(that.options.select_mode=='manager'){
                            that.input_search.val('');
                        }
                        that.startSearch();
                    }
                });
            /*
            function(){
                this.input_search_group.val(this.element.find('#chb_show_all_groups').is(':checked')
                                            ?'any':this.options.rtg_ID).trigger('change');
            }});*/
                        
        }
        
        this.element.find('#div_search_group').hide();

        this.element.find('#inner_title').text( this.options.entity.entityTitlePlural );
        
        if( this.options.simpleSearch){
            
            this.element.find('#input_sort_type_div').hide();
        }else if(smode=='select_multi' || smode=='select_single'){
                
                this.element.find('#btn_ui_config').hide();
                this.element.find('#div_show_all_groups').hide();
                this.element.find('#div_group_information').hide();
                this.element.find('#input_sort_type').parent().hide();
                this.input_search.parent().show();
                
                this.element.find('#btn_ui_config').parent().css({'float':'none'});
                this.element.find('#inner_title').parent().css({'float':'none',position:'absolute',top:'55px'});
                
                this.element.find('#inner_title')
                    .css('font-size','smaller')
                    .text(window.hWin.HR('Not finding the record type you require?'));
                this.btn_add_record
                    .button({label: window.hWin.HR('Define new record type')});
                
                this.element.find('#div_search_group').show();
                this.input_search_group = this.element.find('#input_search_group');   //rectype group

                window.hWin.HEURIST4.ui.createRectypeGroupSelect(this.input_search_group[0], 
                            [{key:'any',title:'all groups'}]);
                this._on(this.input_search_group,  { change:this.startSearch });
                
        }else{
                
                this.btn_ui_config = this.element.find('#btn_ui_config')
                        //.css({'width':'6em'})
                        .button({label: window.hWin.HR('Configure'), showLabel:false, 
                                icon:"ui-icon-gear", iconPosition:'end'});
                if(this.btn_ui_config){
                    this._on( this.btn_ui_config, {
                            click: this.configureUI });
                }

        }
       
        if(window.hWin.HEURIST4.util.isFunction(this.options.onInitCompleted)){
            this.options.onInitCompleted.call();
        }else{
            this.startSearch();              
        }
    },  
    
    //
    //
    //
    _setOption: function( key, value ) {
        this._super( key, value );
        if(key == 'rtg_ID'){
            if(!this.element.find('#chb_show_all_groups').is(':checked'))
                this.startSearch();
                
                if(value==$Db.getTrashGroupId('rtg')){
                    this.btn_add_record.hide();
                }else{
                    this.btn_add_record.show();
                }
                
        }
    },
    
    //
    //
    //    
    configureUI: function(){
        
        let that = this;

        let popele = that.element.find('#div_ui_config');
        
        let flist = popele.find( ".toggles" );
        let opts = this.options.ui_params['fields'];
        
        flist.controlgroup( {
            direction: "vertical"
        } ).sortable();       
        
        popele.find('.ui-checkboxradio-icon').css('color','black');

        //rest all checkboxes
        popele.find('input[type="checkbox"]').prop('checked', '');
        $(opts).each(function(idx,val)
        {
            popele.find('input[name="'+val+'"]').prop('checked', 'checked');    
        });
        popele.find('input[name="name"]').prop('checked', 'checked');
        popele.find('input[name="edit"]').prop('checked', 'checked');
        
        //sort
        let cnt = flist.children().length;
        let items = flist.children().sort(
            function(a, b) {
                    let vA = opts.indexOf($(a).attr('for'));
                    let vB = opts.indexOf($(b).attr('for'));
                    if(!(vA>=0)) vA = cnt;
                    if(!(vB>=0)) vB = cnt;
                    return (vA < vB) ? -1 : (vA > vB) ? 1 : 0;
            });
        
        flist.append(items);    
        
        flist.controlgroup('refresh');
        
        let $dlg_pce = null;

        let btns = [
            {text:window.hWin.HR('Apply'),
                click: function() { 
                    
                    let fields = [];
                    /*popele.find('input[type="checkbox"]:checked').each(function(idx,item){
                        fields.push($(item).attr('name'));
                    });*/
                    flist.find('input[name="name"]').prop('checked', 'checked');
                    flist.find('input[name="edit"]').prop('checked', 'checked');
                    flist.children().each(function(idx,item){
                        item = $(item).find('input');
                        if(item.is(':checked')){
                            fields.push(item.attr('name'));    
                        }                        
                    });
                    
                    //get new parameters
                    let params = { 
                        fields: fields
                    };
                    
                    that.options.ui_params = params;
                    //trigger event to redraw list
                    that._trigger( "onuichange", null, params );
                   
                    $dlg_pce.dialog('close'); 
            }},
            {text:window.hWin.HR('Cancel'),
                click: function() { $dlg_pce.dialog('close'); }}
        ];            

        $dlg_pce = window.hWin.HEURIST4.msg.showElementAsDialog({
            window:  window.hWin, //opener is top most heurist window
            title: window.hWin.HR('Configure Interface'),
            width: 260,
            height: 500,
            element:  popele[0],
            //resizable: false,
            buttons: btns
        });

        $dlg_pce.parent().addClass('ui-heurist-design');

    },
    
    reloadGroupSelector: function(){
        
    },

    //
    // public methods
    //
    startSearch: function(){
        
            if(!this.input_search) return;
            
            let request = {}
            
            let is_search_one_group = (!this.element.find('#chb_show_all_groups').is(':checked') && this.options.rtg_ID>0)
        
            if(!is_search_one_group && this.input_search.val()!=''){
                let s = this.input_search.val();
                if(window.hWin.HEURIST4.util.isNumber(s) && parseInt(s)>0){
                     request['rty_ID'] = s;   
                     s = '';
                }else if (s.indexOf('-')>0){
                    
                    let codes = s.split('-');
                    if(codes.length==2 
                        && window.hWin.HEURIST4.util.isNumber(codes[0])
                        && window.hWin.HEURIST4.util.isNumber(codes[1])
                        && parseInt(codes[0])>0 && parseInt(codes[1])>0 ){
                        request['rty_OriginatingDBID'] = codes[0];
                        request['rty_IDInOriginatingDB'] = codes[1];
                        s = '';
                    }
                }
                
                if(s!='') request['rty_Name'] = s;
            }
            
            if(this.options.import_structure){

                if(this.chb_show_already_in_db && !this.chb_show_already_in_db.is(':checked')){
                        request['rty_ID_local'] = '=0';
                }
                
            }else if(this.options.select_mode=='select_multi' || this.options.select_mode=='select_single'){
                    if(this.input_search_group.val()>0){
                        request['rty_RecTypeGroupID'] = this.input_search_group.val();
                        this.options.rtg_ID = request['rty_RecTypeGroupID'];
                    }else{
                        this.options.rtg_ID = null;
                    }
                    
            }else{
            
                if( this.options.rtg_ID<0 ){
                    //not in given group
                    request['not:rty_RecTypeGroupID'] = Math.abs(this.options.rtg_ID);
                }
            
                let sGroupTitle = '<h4 style="margin:0;padding-bottom:5px;">';
                if(is_search_one_group)
                {
                    this.input_search.parent().hide();

                    request['rty_RecTypeGroupID'] = this.options.rtg_ID;
                    sGroupTitle += ($Db.rtg(this.options.rtg_ID,'rtg_Name')
                                        +'</h4><div class="heurist-helper3 truncate" style="font-size:0.7em">'
                                        +$Db.rtg(this.options.rtg_ID,'rtg_Description')+'</div>');
                }else{
                    this.input_search.parent().show();
                    sGroupTitle += window.hWin.HR('All Groups')+
                        '</h4><div class="heurist-helper3" style="font-size:0.7em">'+window.hWin.HR('All record type groups')+'</div>';
                }
                this.element.find('#div_group_information').html(sGroupTitle);
        
            }
            
            this.input_sort_type = this.element.find('#input_sort_type');
            if(this.input_sort_type.val()=='recent'){
                request['sort:rty_Modified'] = '-1' 
            }else if(this.input_sort_type.val()=='id'){
                request['sort:rty_ID'] = '1';   
            }else if(this.input_sort_type.val()=='count'){
                request['sort:rty_RecCount'] = '-1';   
            }else{
                request['sort:rty_Name'] = '1';   
            }
  
            if(this.options.use_cache){
            
                this._trigger( "onfilter", null, request);            
            }else{
                this._search_request = request;
                this._super();                
            }            
    }
});
