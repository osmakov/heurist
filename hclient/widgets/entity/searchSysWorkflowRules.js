/**
* Search header for SysWorkflowRules manager
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

$.widget( "heurist.searchSysWorkflowRules", $.heurist.searchEntity, {

    //
    _initControls: function() {
        this._super();
        
        let that = this;
        
        this.element.find('#inner_title').text( this.options.entity.entityTitlePlural );
        
        //this.btn_search_start.css('float','right');   
        
        this.btn_add_record = this.element.find('.btn_AddRecord');
        this.btn_add_record
                    .button({label: window.hWin.HR('Add set of Rules'), showLabel:true, 
                            icon:"ui-icon-plus"})
                    .addClass('ui-button-action')
                    .css({padding:'2px'})
                    .show();
                    
        this._on( this.btn_add_record, {
                        click: function(){
                                this._trigger( "onadd" );    
                        }} );

        this._on( this.element.find('#edit_swf_vocab'), {
                        click: function(){
                            this._trigger('onvocabedit');
                        }
        });
        
        this.input_search_rectype = this.element.find('#input_search_rectype');
        let rty_selector = window.hWin.HEURIST4.ui.createRectypeSelectNew(this.input_search_rectype.get(0), 
                {rectypeList:  null, 
                 showAllRectypes: true}); //topOptions:'select record type'
        this._on(rty_selector,  { change:this.startSearch });

        if(!window.hWin.HEURIST4.allUsersCache || !window.hWin.HEURIST4.util.isArrayNotEmpty(window.hWin.HEURIST4.allUsersCache)){

            //get all users
            let request = {a:'search', entity:'sysUsers', details:'fullname', 'sort:ugr_LastName': '1'};
            //Note: it searches for all users - including disabled
            window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    let recordset = new HRecordSet(response.data);
                    window.hWin.HEURIST4.allUsersCache = [];                    
                    recordset.each2(function(id,rec){
                        window.hWin.HEURIST4.allUsersCache.push({id: id, name: rec['ugr_FullName']});
                    });
                    
                    that.startSearch();
                    
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
        
        }else{
            this.startSearch();     
        }
    },  
    
    //
    // public methods
    //
    startSearch: function(){
        
            let request = {};
        
            if(this.input_search_rectype.val() && this.input_search_rectype.val()!='any'){
                request['swf_RecTypeID'] = this.input_search_rectype.val();        
                request['sort:swf_Order'] = 1;
            }else{
                
                let recset = $Db.swf();
                let id;
                if(recset.length()>0){
                    id = recset.fld(recset.getFirstRecord(),'swf_RecTypeID');
                }else{
                    //get first
                    id = this.input_search_rectype.find('option[value!=0]:first').attr('value');
                }

                this._setOption('rty_ID', id);
                return;
            }
            
            this._trigger( "onfilter", null, request); 
            
    },
    
    //
    //
    //    
    getSelectedRty: function(){
        return this.input_search_rectype.val();
    },

    //
    //
    //    
    setButton: function(is_empty){

        if(is_empty){
            this.btn_add_record.button({label:window.hWin.HR('Add set of Rules')});    
        }else{
            this.btn_add_record.button({label:window.hWin.HR('Add Stage')});
        }
        
    },

    //
    //
    //
    _setOption: function( key, value ) {
        this._super( key, value );
        if(key == 'rty_ID'){
            this.input_search_rectype.val(value);
            this.input_search_rectype.hSelect('refresh');
            this.startSearch();
        }
    },

    //
    // Refresh list of rectypes with sys workflow setup
    //
    refreshRectypeList: function(){

        // List rectypes that have swf assigned
        let rectype_names = [];

        $Db.swf().each2(function(id, record){

            let rty_name = $Db.rty(record['swf_RecTypeID'], 'rty_Name');

            if(!rectype_names.includes(rty_name)){ 
                rectype_names.push(rty_name); 
            }
        });

        this.element.find('span#existing_swr').text(rectype_names.join(', '));
    }
});
