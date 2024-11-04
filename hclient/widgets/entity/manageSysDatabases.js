/**
* manageSysDatabases.js - work with list of all databases on current server
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


$.widget( "heurist.manageSysDatabases", $.heurist.manageEntity, {
    
    
    _entityName:'sysDatabases',
    
    _init: function() {
  
        this.options.default_palette_class = 'ui-heurist-design';
        
        this.options.width = 800;
        this.options.height = 600;
        this.options.edit_mode = 'none';

        this._super();
    },
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        this.options.use_cache = true;
        
        if(!this._super()){
            return false;
        }
        
        if(this.searchForm && this.searchForm.length>0){
            this.searchForm.searchSysDatabases(this.options);   
            if(this.options.subtitle){
                this.recordList.css('top',80);
            }
        }
        
        this.recordList.resultList('option','rendererHeader',
                    function(){
       let sHeader = '<div style="width:60px"></div><div style="border-right:none">Db Name</div>';
        /*
                //+'<div style="width:3em">Ver</div>'
                +'<div style="width:3em">Reg#</div>'
                +'<div style="width:20em">Title</div>'
                +'<div style="width:5em">Role</div>'
                +'<div style="width:5em">Users</div>'; */
                
                return sHeader;
                    }
                );
        
        
        if(this.options.isdialog){
            window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parents('.ui-dialog')); 
        }
        

        //load at once everything to _cachedRecordset
        let that = this;
        function __onDataResponse(response){

                window.hWin.HEURIST4.msg.sendCoverallToBack();
                
                that._cachedRecordset = response;
                
               
                that.recordList.resultList('updateResultSet', response);
            };
            
        let entityData = window.hWin.HAPI4.EntityMgr.getEntityData2( this.options.entity.entityName );

        
        if($.isEmptyObject(entityData)){
        
            window.hWin.HAPI4.EntityMgr.doRequest(
                {a:'search', 'entity':this.options.entity.entityName, 'details':'ids'},
                       function(response){
                            if(response.status == window.hWin.ResponseStatus.OK){
                                entityData = new HRecordSet(response.data);
                                window.hWin.HAPI4.EntityMgr.setEntityData(
                                            that.options.entity.entityName,
                                            entityData);

                                __onDataResponse(entityData);
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                       });
        
        }else{
            __onDataResponse(entityData);
        }
        
        /*
        window.hWin.HAPI4.EntityMgr.getEntityData(this.options.entity.entityName, false,
            function(response){
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                
                that._cachedRecordset = response;
                
                that.filterRecordList(null, {});
               
            });
        */    
            
        //and then filter locally    
        this._on( this.searchForm, {
                "searchsysdatabasesonfilter": this.filterRecordList
                });
                
        return true;
    },    
    
    //----------------------
    //
    // customized item renderer for search result list
    //
    _recordListItemRenderer: function(recordset, record){
        
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function frm(value, col_width){
            let swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = ' style="width:'+col_width+'"';
            }
            //value = window.hWin.HEURIST4.util.htmlEscape(value);
            return '<div class="item" '+swidth+'>'+value+'</div>';  //title="'+val+'"
        }
        
        
        let recID   = fld('sys_Database');
        
        let dbName = fld('sys_dbName');
        if(dbName=='Please enter a DB name ...') dbName = '';
        
        let recTitle = recID; //remove prefix hdb_
        if(recTitle.indexOf(window.hWin.HAPI4.sysinfo.database_prefix)==0){
            recTitle = recTitle.substring(window.hWin.HAPI4.sysinfo.database_prefix.length);
        }
        recTitle= frm(recTitle, '40em');
        
       
        let rtIcon = window.hWin.HAPI4.getImageUrl(this._entityName, 0, 'icon');
        let recThumb = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'thumb');
        
        let html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+recThumb+'&quot;);opacity:1">'
        +'</div>';
        
        let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" data-value="'+ fld('sus_Role')+'">'
        + html_thumb
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + '<div class="recordIcons">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" style="background-image: url(&quot;'+rtIcon+'&quot;);">'
        + '</div>'
        + '<div class="recordTitle">'  // title="'+recTitleHint+'"
        +     recTitle 
        + '</div>';


        return html+'</div>';
        
    },

    updateRecordList: function( event, data ){
       
        if (data){
            if(this.options.use_cache){
                this._cachedRecordset = data.recordset;
                //there is n filter feature in this form - thus, show list directly
            }
            this.recordList.resultList('updateResultSet', data.recordset, data.request);
        }
    },
    
    filterRecordList: function(event, request){
        if(this.options.except_current==true){
            let subset = this._cachedRecordset.getSubSetByRequest(request, this.options.entity.fields);
            //except current
            subset = subset.getSubSetByRequest({'sys_Database':'!='+window.hWin.HAPI4.sysinfo.database_prefix+window.hWin.HAPI4.database}, 
                            this.options.entity.fields);
            //update
            this.recordList.resultList('updateResultSet', subset, request);   
        }else{
            this._super(event, request); 
        }
    },
    
});
