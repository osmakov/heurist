/**
* Search header for DefTerms manager
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

$.widget( "heurist.searchSysDatabases", $.heurist.searchEntity, {

    input_email: null,

    //
    _initControls: function() {
        this._super();
        
        this.input_search.parent().css('padding-top','15px'); 

        this.input_search_type = this.element.find('#input_search_type');
        this._on(this.input_search_type,  { change:this.startSearch });

        this.input_sort_type = this.element.find('#input_sort_type');
        this.input_sort_type.parent().hide();
        this._on(this.input_sort_type,  { change:this.startSearch });

        this._on(this.input_search,  { keydown: window.hWin.HEURIST4.ui.preventNonAlphaNumeric, keyup:this.startSearch });
        
        this.input_search.trigger('focus');         

        // Setup email filtering
        this.element.find('#input_import_only').show();
        this.input_email = this.element.find('.input_search_email');
        this._on(this.input_email, {
            keydown: (e) => {
                if(e.key == "Enter"){
                    this.startSearch();
                }
            }
        });
        this._on(this.element.find('#btn_filter_email').button(), {
            click: this.startSearch
        });
        
        if(this.options.subtitle){
            let ele = this.element.find('.sub-title');
            if(ele.length>0){
                ele.html('<h3 style="margin:1em 0 0 0">'+this.options.subtitle+'</h3>');
            }
        }
    },  

    //
    // public methods
    //
    startSearch: function(){
        
        let request = {};
        
        if(this.input_search.val() != ''){
            request['sys_Database'] = this.input_search.val();
        }
        if(this.input_email.val() != ''){
            request['ugr_eMail'] = this.input_email.val();
        }

        if(this.input_search_type.val()!='' && this.input_search_type.val()!='any'){
            request['sus_Role'] = this.input_search_type.val();
        }
        
        if(this.input_sort_type.val()=='name'){
            request['sort:sys_Database'] = 1;
        }else if(this.input_sort_type.val()=='register'){
            request['sort:sys_dbRegisteredID'] = -1;
        }else  if(this.input_sort_type.val()=='member'){
            request['sort:sus_Count'] = -1;
        }
        
        this._trigger( "onfilter", null, request);
    }


});
