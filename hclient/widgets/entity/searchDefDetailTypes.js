/**
* Search header for manageDefDetailTypes manager
*
* 
*  @package     Heurist academic knowledge management system
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

$.widget( "heurist.searchDefDetailTypes", $.heurist.searchEntity, {

    //
    _initControls: function() {

        this.input_search_type = this.element.find('#input_search_type');   //field type
        let vals = []; 
        let filter_types = [];
        if(this.options.filters && this.options.filters.types){
            filter_types = this.options.filters.types;
            if(Array.isArray(filter_types) && filter_types.length>0){
                this.input_search_type.val(filter_types[0]);
            }else{
                filter_types = [];
            }
        }else{
            vals = [{key:'any',title:'any type'}];
        }
        
        for (let key in $Db.baseFieldType)
        if(!window.hWin.HEURIST4.util.isempty($Db.baseFieldType[key])){
            if( key!='calculated' &&  (filter_types.length==0 || filter_types.indexOf(key)>=0))
                vals.push({key:key,title:$Db.baseFieldType[key]});
        }

        window.hWin.HEURIST4.ui.createSelector(this.input_search_type[0], vals);

        this.input_search_group = this.element.find('#input_search_group');   //detail group
        window.hWin.HEURIST4.ui.createDetailtypeGroupSelect(this.input_search_group[0],
                                        [{key:'any',title:'any group'}]);

        this._super();

        //hide all help divs except current mode
        let smode = this.options.select_mode; 
        this.element.find('.heurist-helper1').find('span').hide();
        this.element.find('.heurist-helper1').find('span.'+smode+',span.common_help').show();
        
        this.btn_add_record = this.element.find('.btn_AddRecord');
        this.btn_find_record = this.element.find('#btn_find_record');
        this.btn_csv_import = this.element.find('#btn_csv_import');

        if(this.options.edit_mode=='none' || this.options.import_structure){
            this.btn_add_record.hide();
            this.btn_find_record.hide();
        }else{
            
            this.btn_add_record
                    .button({label: window.hWin.HR("Add"), showLabel:true, 
                            icon:"ui-icon-plus"})
                    .addClass('ui-button-action')
                    .css({padding:'2px'})
                    .show();
                    
            this._on( this.btn_add_record, {
                        click: function(){
                            this._trigger( "onadd" );
                        }} );

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
        
        //always hide        
        this.input_search_group.parent().hide();
        this.btn_find_record.hide();
        
        //on start search
        this._on(this.input_search,  { keyup:this.startSearch });
        this._on(this.input_search_type,  { change:this.startSearch });
        this._on(this.input_search_group,  { change:this.startSearch });
        
        //@todo - possible to remove
        
        if( this.options.dtg_ID>0 ){
            this.input_search_group.val(this.options.dtg_ID);
        }else if( this.options.dtg_ID<0 ){  //addition of rectype to group
            //find any rt not in given group
            //exclude this group from selector
            this.input_search_group.find('option[value="'+Math.abs(this.options.dtg_ID)+'"]').remove();
        }else{
            this.btn_find_record.hide();
        }
             
        if(this.options.import_structure){
            this.chb_show_already_in_db = this.element.find('#chb_show_already_in_db');
            this._on(this.chb_show_already_in_db,  { change:this.startSearch });
            this.element.find('#div_group_information').hide();
            
            this.options.simpleSearch = true;
        }else{
            this.element.find('#div_show_already_in_db').hide();
        }
             
        this.input_sort_type = this.element.find('#input_sort_type');
        this._on(this.input_sort_type,  { change:this.startSearch });
        
        this._on(this.element.find('#chb_show_all_groups'),  { change:function(){
                this.input_search_group.val(this.element.find('#chb_show_all_groups').is(':checked')
                                            ?'any':this.options.dtg_ID).trigger('change');
        }});

        this.input_filter_rectype = this.element.find('#input_filter_rectype');

        if(this.options.use_cache && this.options.select_mode != 'manager'){

            window.hWin.HEURIST4.ui.createRectypeSelectNew(this.input_filter_rectype[0], {useHtmlSelect: true, topOptions: [{key:'any', title:'All record types'}]});

            this._on(this.input_filter_rectype, {
                change: function(){
                    this.startSearch();
                }
            });
            this.input_filter_rectype.parent().show();
        }else{
            this.input_filter_rectype.parent().hide();
        }
        
        if(this.options.simpleSearch){
            this.element.find('#input_field_type_div').hide();
            this.element.find('#input_sort_type_div').hide();
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
        if(key == 'dtg_ID'){
            if(!this.element.find('#chb_show_all_groups').is(':checked'))
                this.element.find('#input_search_group').val(value).trigger('change');
        }
    },

    //
    // public methods
    //
    startSearch: function(){
        
            let request = {}
        
            if(this.input_search.val()!=''){

                let s = this.input_search.val();
                this.element.find('#chb_show_all_groups').prop('checked', true);

                if(window.hWin.HEURIST4.util.isNumber(s) && parseInt(s)>0){
                     request['dty_ID'] = s;   
                     s = '';
                }else if (s.indexOf('-')>0){
                    
                    let codes = s.split('-');
                    if(codes.length==2 
                        && window.hWin.HEURIST4.util.isNumber(codes[0])
                        && window.hWin.HEURIST4.util.isNumber(codes[1])
                        && parseInt(codes[0])>0 && parseInt(codes[1])>0 ){
                        request['dty_OriginatingDBID'] = codes[0];
                        request['dty_IDInOriginatingDB'] = codes[1];
                        s = '';
                    }
                }
                
                if(s!='') request['dty_Name'] = s;
            }
        
            if(this.input_search_type.val()!='' && this.input_search_type.val()!='any'){
                request['dty_Type'] = this.input_search_type.val();
            }   
            
            
            let sGroupTitle = '';
            if(this.options.import_structure){
                if(this.chb_show_already_in_db && !this.chb_show_already_in_db.is(':checked')){
                    request['dty_ID_local'] = '=0';
                }
            }else if( this.options.dtg_ID<0 ){
                //find not in given group
                request['not:dty_DetailTypeGroupID'] = Math.abs(this.options.dtg_ID);
            }else{
        
                sGroupTitle = '<h4 style="margin:0;padding-bottom:5px;">';
                if(!this.element.find('#chb_show_all_groups').is(':checked') && this.options.dtg_ID>0){ //this.input_search_group.val()
                    let dtg_id = this.options.dtg_ID;
                    request['dty_DetailTypeGroupID'] = dtg_id;
                    sGroupTitle += ($Db.dtg(dtg_id,'dtg_Name')
                                        +'</h4><div class="heurist-helper3 truncate" style="font-size:0.7em">'
                                        +$Db.dtg(dtg_id,'dtg_Description')+'</div>');
                }else{
                    sGroupTitle += 'Base field types</h4><div class="heurist-helper3" style="font-size:0.7em">All base field type groups</div>';
                }
            }
            this.element.find('#div_group_information').html(sGroupTitle);
            
            this.input_sort_type = this.element.find('#input_sort_type');
            if(this.input_sort_type.val()=='recent'){
                request['sort:dty_Modified'] = '-1' 
            }else if(this.input_sort_type.val()=='type'){
                request['sort:dty_Type'] = '1' 
            }else{
                request['sort:dty_Name'] = '1';   
            }
  
            if(this.options.use_cache){

                if(this.options.select_mode != 'manager'){
                    request['rtyID'] = this.input_filter_rectype.val();
                }
            
                this._trigger( "onfilter", null, request);            
                
            }else{
                this._search_request = request;
                this._super();                
            }            
            
    },
    

});
