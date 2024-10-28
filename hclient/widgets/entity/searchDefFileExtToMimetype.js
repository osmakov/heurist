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

$.widget( "heurist.searchDefFileExtToMimetype", $.heurist.searchEntity, {

    //
    _initControls: function() {
        this._super();

        /*
        this.btn_add_record = this.element.find('.btn_AddRecord');
        if(this.options.edit_mode=='none'){
            this.btn_add_record.hide();
        }else{
            this.btn_add_record.css({'min-width':'11.9em','z-index':2})
                    .button({label: window.hWin.HR("Add New File Type"), icon:"ui-icon-plus"})
                .on('click', function(e) {
                    that._trigger( "onaddrecord" );
                }); 
            if(this.options.edit_mode=='inline'){
                this.btn_add_record.parent().css({'float':'left','border-bottom':'1px lightgray solid',
                'width':'100%', 'min-height': '2.4em', 'margin-bottom': '0.4em'});    
            }                       
        }
        */
            
        this.input_search_type = this.element.find('#input_search_type');
        this._on( this.input_search_type, { change: this.startSearch });
        
    },  

    //
    // public methods
    //
    startSearch: function(){
        
            let request = {};
            
            if(this.input_search.val()!=''){
                request['fxm_Extension'] = this.input_search.val();
            }
            
            if(this.input_search_type.val()!='' && this.input_search_type.val()!='any'){
                request['fxm_MimeType'] = this.input_search_type.val();
            }
            
            this._trigger( "onfilter", null, request);
    }


});
