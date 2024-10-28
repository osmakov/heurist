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

$.widget( "heurist.searchSysUsers", $.heurist.searchEntity, {

    //
    _initControls: function() {
        
        let that = this;

        if(this.options.subtitle){
            let ele = this.element.find('.sub-title');
            if(ele.length>0){
                ele.html('<h3>'+this.options.subtitle+'</h3>');
            }
        }
        
        this.input_search_group = this.element.find('#input_search_group');   //user group
        if(window.hWin.HAPI4.is_admin()){
            window.hWin.HEURIST4.ui.createUserGroupsSelect(this.input_search_group[0], 'all_my_first' , 
                        [{key:'any',title:'any group'}]);
        }else{
            window.hWin.HEURIST4.ui.createUserGroupsSelect(this.input_search_group[0], null, 
                        [{key:'any',title:'any group'}]);
        }
        
        this._super();

        //hide all help divs except current mode
        let smode = this.options.select_mode; 
        this.element.find('.heurist-helper1').find('span').hide();
        this.element.find('.heurist-helper1').find('span.'+smode+',span.common_help').show();
        
        this.btn_add_record = this.element.find('.btn_AddRecord');
        this.btn_find_record = this.element.find('#btn_find_record');

        if(this.options.edit_mode=='none'){
            this.btn_add_record.hide();
            this.btn_find_record.hide();
        }else{
            this.btn_add_record.css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Add New User"), icon: "ui-icon-plus"})
                .on('click', function(e) {
                    that._trigger( "onadd" );
                }); 

            this.btn_find_record.css({'min-width':'9m','z-index':2})
                    .button({label: window.hWin.HR("Find/Add User"), icon: "ui-icon-search"})
                .on('click', function(e) {
                    that._trigger( "onfind" );
                }); 
                
            //@todo proper alignment
            if(this.options.edit_mode=='inline'){
                this.btn_add_record.css({'float':'left','border-bottom':'1px lightgray solid',
                'min-height': '2.4em', 'margin-bottom': '0.4em'});    
            }else if(this.options.ugl_GroupID > 0){
                this.btn_add_record.parent().css({
                    top: '10px',
                    right: '0px',
                    left: ''
                });
            }
        }
        
        this.input_search_inactive = this.element.find('#input_search_inactive');
        this.input_search_role = this.element.find('#input_search_role');

        this._on(this.input_search_group,  { change:this.startSearch });
        this._on(this.input_search_role,  { change:this.startSearch });
        this._on(this.input_search_inactive,  { change:this.startSearch });

        if( this.options.ugl_GroupID>0 ){
            this.input_search_group.parent().hide();
            this.input_search_group.val(this.options.ugl_GroupID);
            
            this.input_search_role.parent().show();
            
            if(!window.hWin.HAPI4.is_admin()){
                this.btn_add_record.hide();
                this.btn_find_record.hide();
            }
        }else if( this.options.ugl_GroupID<0 ){  //addition of users to group
            //find any user not in given group
            //exclude this group from selector
            this.input_search_group.find('option[value="'+Math.abs(this.options.ugl_GroupID)+'"]').remove();
        }else{
            this.btn_find_record.hide();
        }
             
        this.input_sort_type = this.element.find('#input_sort_type');
        this._on(this.input_sort_type,  { change:this.startSearch });
                      
        this.startSearch();            
    },  

    
    //
    // public methods
    //
    startSearch: function(){
            
            let request = {}
        
            if(this.input_search.val()!=''){
                request['ugr_Name'] = this.input_search.val();
            }
            
            if( this.options.ugl_GroupID<0 ){
                //find any user not in given group
                request['not:ugl_GroupID'] = Math.abs(this.options.ugl_GroupID);
            }
        
            if(this.input_search_group.val()>0){
                
                request['ugl_GroupID'] = this.input_search_group.val();
                
                this.input_search_role.parent().show();

                let gr_role = this.input_search_role.val();
                if(gr_role!='' && gr_role!='any'){
                    
                    if(gr_role=='admin'){
                        request['ugl_Role'] = 'admin';
                    }else
                    if(gr_role=='member'){  
                        request['ugl_Role'] = 'member';
                    }
                }
                
                if( window.hWin.HAPI4.has_access( this.input_search_group.val() )
                    && this.options.edit_mode!='none'){
                    this.btn_find_record.show();
                }
            }else{
                this.input_search_role.parent().hide();
                this.btn_find_record.hide(); 
            }
            
            if(this.input_search_inactive.is(':checked')){
                request['ugr_Enabled'] = 'n';
            }     

            if(this.options.ugl_GroupID < 0)
            {
                request['ugr_Enabled'] = '-n';
                this.input_search_inactive.prop('disabled', true);
            }       
            
            this.input_sort_type = this.element.find('#input_sort_type');
            if(this.input_sort_type.val()=='lastname'){
                request['sort:ugr_LastName'] = '1' 
            }else if(this.input_sort_type.val()=='recent'){
                request['sort:ugr_ID'] = '-1' 
            }else{
                request['sort:ugr_Name'] = '1';   
            }
            
            this._search_request = request;
            this._super();
                           
    }
});
