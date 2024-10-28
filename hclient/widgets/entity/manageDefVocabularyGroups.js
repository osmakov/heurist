/**
* manageDefRecTypeGroups.js - main widget mo record type groups
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


$.widget( "heurist.manageDefVocabularyGroups", $.heurist.manageDefGroups, {
    
    _entityName:'defVocabularyGroups',
    _entityPrefix: 'vcg',
    _title: 'Vocabularies Editor',
    
    _init: function() {

        this._super();
        
        this.element.addClass(this._entityName); //to find all exisiting editors in application
        this.options.edit_width = 550;        
    },
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {

        if(!this._super()){
            return false;
        }
        
        this.searchForm.find('h4').remove();

        $('<h3 style="margin:0;padding:0 8px;vertical-align: middle;width:100%;min-height: 32px; border-bottom: 1px solid gray; clear: both;">Vocabularies editor</h3>'
            +'<div class="action-buttons" style="height:40px;background:white;padding:3px 8px;">'
            +'<h4 style="display:inline-block;margin: 0 10px 0 0; vertical-align: middle;">Groups</h4></div>')
        .appendTo( this.searchForm );
        
        this.searchForm.find('.btnAddButton').css('float','none').appendTo(this.searchForm.find('.action-buttons'));
        //this._defineActionButton2(btn_array[0], this.searchForm.find('.action-buttons'));
        
        this.searchForm.css({padding:'6px 0 0 0'});
        this.recordList.css({ top:80});        
    },
    
    
    _addOnDrop: function(type_ID, group_ID){

        if(type_ID>0 && group_ID>0 && this.options.reference_vocab_manger){
            
            let params = {trm_ID:type_ID, trm_VocabularyGroupID:group_ID };
            
            this.options.reference_vocab_manger
                .manageDefTerms('changeVocabularyGroup', params);
        }
    }
});
