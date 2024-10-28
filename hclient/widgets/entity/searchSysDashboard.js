/**
* Search header for manageSysUsers manager
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

$.widget( "heurist.searchSysDashboard", $.heurist.searchEntity, {

    //
    _initControls: function() {
        
        let that = this;
        
        this._super();

        //hide all help divs except current mode
        /*
        var smode = this.options.select_mode; 
        this.element.find('.heurist-helper1').find('span').hide();
        this.element.find('.heurist-helper1').find('span.'+smode+',span.common_help').show();
        */
        this.btn_add_record = this.element.find('.btn_AddRecord')
                .css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Add New Entry"), icon: "ui-icon-plus"})
                .on('click', function(e) {
                    that._trigger( "onadd" );
                }); 

        this.btn_apply_order = this.element.find('#btn_apply_order')
                .hide()
                .css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Save New Order"), icon: "ui-icon-move-v"})
                .on('click', function(e) {
                    that._trigger( "onorder" );
                }); 

        this.btn_set_mode = this.element.find('#btn_set_mode')
                .css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("View shortcuts")})
                .on('click', function(e) {
                    window.hWin.HAPI4.save_pref('prefs_sysDashboard', 
                        {show_as_ribbon:1, 
                         show_on_startup: 1 });     
                    that._trigger( "onclose" );
                    
                   
                }); 

        this.btn_close_mode = this.element.find('#btn_close_mode')
                .css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Hide shortcuts")})
                .on('click', function(e) {
                    window.hWin.HAPI4.save_pref('prefs_sysDashboard', 
                        {show_as_ribbon:1, 
                         show_on_startup:0 });     
                    that._trigger( "onclose" );
                }); 
                
                
        this.btn_show_on_startup = this.element.find('#btn_show_on_startup2')
                .css({'min-width':'9m'})
                    .button({label: window.hWin.HR("Don't show again")})
                .on('click', function(e) {
                    
                    //don't show  dashboard on startup
                    let params = window.hWin.HAPI4.get_prefs_def('prefs_sysDashboard', {show_as_ribbon:0} );
                    params['show_on_startup'] = 0;
                    window.hWin.HAPI4.save_pref('prefs_sysDashboard', params);     
                    
                    that._trigger( "onclose" );
                }); 
                
                
        this.input_search_inactive = this.element.find('#input_search_inactive');
        this._on(this.input_search_inactive,  { change:this.startSearch });
        
        this.input_sort_type = this.element.find('#input_sort_type');
        this._on(this.input_sort_type,  { change:this.startSearch });
                     
        this._trigger( "oninit", null );
                      
        this.startSearch();            
    },  

    
    //
    // public methods
    //
    startSearch: function(){
        
            let request = {}
            
            if(this.options.isViewMode){
                
                request['dsh_Enabled'] = 'y';
                request['sort:dsh_Order'] = '1' 
                
                //if database empty - hide some entries
                if(window.hWin.HAPI4.sysinfo['db_total_records']<1){
                    request['dsh_ShowIfNoRecords'] = 'y';
                }
                
            }else{
                /*
                if(this.input_search.val()!=''){
                    request['dsh_Label'] = this.input_search.val();
                }
                
                this.input_sort_type = this.element.find('#input_sort_type');
                if(this.input_sort_type.val()=='order'){
                    request['sort:dsh_Order'] = '1' 
                }else {
                    request['sort:dsh_Label'] = '1';   
                }
                */
                if(this.input_search_inactive.is(':checked')){
                    request['dsh_Enabled'] = 'n';
                }
                request['sort:dsh_Order'] = '1' 
            }
            
            this._search_request = request;
            this._super();
    }
});
