/**
* recordDelete.js - delete selected records
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

$.widget( "heurist.recordDelete", $.heurist.recordAction, {

    // default options
    options: {
        map_document_id: null, //special case - remove mapdocument and all its dependencies
    
        height: 340,
        width:  640,
        modal:  true,
        init_scope: 'selected',
        hide_scope: true,
        title:  'Delete Records',
        htmlContent: 'recordDelete.html'
    },
    
    header_div:null,
    recordList:null,

    //
    //
    //
    _initControls:function(){
        
        this._super();
        
        let cnt_selected = this._currentRecordsetSelIds.length;
        
        this.header_div = this._$('#div_header').css({'line-height':'21px'});

        if(cnt_selected > 8){
            this._$('#div_1').show();
        }            
        
        if (window.hWin.HAPI4.is_admin()) {
            if(cnt_selected>0){
                this._$('#div_2').show();
                this._$('#div_2 > a').attr('href',
                             window.hWin.HAPI4.baseURL+'admin/verification/combineDuplicateRecords.php?db='
                             + window.hWin.HAPI4.database
                             +'&bib_ids=' + this._currentRecordsetSelIds.join(','));
            }
        } else {
            this._$('#div_3').show(); //show 
        }
        
        //hide scope selector
        if(this.options.hide_scope || this.options.map_document_id>0){
            this._$('#div_fieldset').hide();    
        }
        
        
        this.recordList = this._$('.recordList');
        
        //init record list
        this.recordList
                    .resultList({
                       eventbased: false, //do not listen global events
                       multiselect: true,
                       select_mode: 'select_multi',
                       view_mode: 'list',
                       
                       pagesize:1000,
                       empty_remark: '<div style="padding:1em 0 1em 0">No records selected for deletion</div>',
                       show_toolbar: false,
                       /*
                       searchfull: function(arr_ids, pageno, callback){
                           that._recordListGetFullData(arr_ids, pageno, callback);
                       },//this._recordListGetFullData,    //search function 
                       renderer: function(recordset, record){ 
                                return that._recordListItemRenderer(recordset, record);  //custom render for particular entity type
                            },
                       */     
                                });     

        this._on( this.recordList, {
                        "resultlistonselect": function(event, selected_recs){
                                    /*this.selectedRecords(selected_recs);
                                    
                                    if (this.options.edit_mode=='inline'){
                                            this._onActionListener(event, {action:'edit'}); //default action of selection
                                    }*/
                                },
                        //"resultlistonaction": this._onActionListener        
                        });
        
        if(this.options.map_document_id>0){
            //find mapdocument content
            this._findMapDocumentContent();
        }else{
            let scope = this._currentRecordset.getSubSetByIds(this._currentRecordsetSelIds);
            this.recordList.resultList('updateResultSet', scope, null); //render
        }
        
        
        
        //adjust height
        let that = this;
        setTimeout(function(){
            let h = that.header_div[0].scrollHeight;
            if(h>33){
                that.header_div.css({height: h});
                that.recordList.css({top: (h-1)});
            }
                              },300);
                              
        return true;
    },
    
    //
    //
    //
    _findMapDocumentContent: function(){
        
        let mapdoc_id = this.options.map_document_id;
        
        let RT_TLCMAP_DATASET = window.hWin.HAPI4.sysinfo['dbconst']['RT_TLCMAP_DATASET'],
            RT_MAP_DOCUMENT = window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT'],
            RT_MAP_LAYER = window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_LAYER'],
            DT_MAP_LAYER = window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_LAYER'],
            DT_DATA_SOURCE = window.hWin.HAPI4.sysinfo['dbconst']['DT_DATA_SOURCE'];
        
            let request = {
                        w: 'a',
                        detail: 'header',
                        source: 'map_document',
                        q: {"ids":mapdoc_id}
                        //rules: [{"query":"linkedfrom:"+RT_MAP_LAYER+"-"+DT_DATA_SOURCE}]
            };
            
            
            if(RT_TLCMAP_DATASET>0){
                   
                    request['rules'] = [{"query":"t:"+RT_MAP_LAYER+","+RT_TLCMAP_DATASET+" linkedfrom:"+RT_MAP_DOCUMENT+"-"+DT_MAP_LAYER
                                        ,"levels":[{"query":"linkedfrom:"+RT_MAP_LAYER+"-"+DT_DATA_SOURCE},
                                                   {"query":"linkedfrom:"+RT_TLCMAP_DATASET+"-"+DT_DATA_SOURCE}]}];
            }else{
                    request['rules'] = [{"query":"t:"+RT_MAP_LAYER+" linkedfrom:"+RT_MAP_DOCUMENT+"-"+DT_MAP_LAYER
                                        ,"levels":[{"query":"linkedfrom:"+RT_MAP_LAYER+"-"+DT_DATA_SOURCE}]}];
            }
            
            let that = this;

            //perform search        
            window.hWin.HAPI4.RecordMgr.search(request,
                function(response){
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        let resdata = new HRecordSet(response.data);
                        
                        that.recordList.resultList('updateResultSet', resdata, null); //render

                        window.hWin.HEURIST4.util.setDisabled( that.element.parents('.ui-dialog').find('.btnDoAction'), false );
                        
                    }else {
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }

                }
            );           
        
    },
    
    /*
    // not implemented
    //
    _onLinkedCount:function(){
        
            let cnt_selected = this._currentRecordsetSelIds.length;
            let cnt_target = 0;
            let cnt_source = 0;
            let merged_ids = this._currentRecordsetSelIds;
            
       
        
        if(cnt_source>0){

            let ele = this._$('#div_4').show();
            
            if(cnt_selected==1){
                msg = 'This record is';
            }else if(cnt_target==1){
                msg = 'One of '+cnt_selected+' selected records is';
            }else if(cnt_target<cnt_selected){
                msg = cnt_target+' records of '+cnt_selected+' selected records are';
            }else{
                msg = 'These '+cnt_selected+' selected records are';
            }
            msg = msg
            +' pointed to by '+cnt_source+' other records. If you delete '+(cnt_target==1?'it':'them')
            +' you will leave '+((cnt_source>1)?'these records':'this record')+' with invalid data.<br>'
            +'Invalid records can be found through the Verify > Verify integrity function.';
        
            ele.find('p').first().html(msg);
                        
            ele.find('a').attr('href',
                window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                +'&tab=connections&q=ids:'+merged_ids.join(','));
            
        }
    },
    */
    
    _destroy: function() {
        // remove generated elements
        if(this.selectRecordScope) this.selectRecordScope.remove();
        if(this.recordList) this.recordList.remove();
    },
    
    
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Delete Records');
        return res;
    },    
    
    //
    //
    //
    doAction: function( isconfirm, check_source ){

        let scope_val = this.selectRecordScope.val();
        if (scope_val=='') return;
        
        let that = this;       
            
        if(scope_val=='selected' && isconfirm!==true){
            
            let recset = this.recordList.resultList('getRecordSet');           
            let r1 = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'];
            let r2 = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'];
            let cnt = 0;
            recset.each(function(recID, record){
                let rt = this.fld(record, 'rec_RecTypeID');
                if(rt==r1 || rt==r2){
                    cnt++;
                }
            });
            if(cnt>0){
            window.hWin.HEURIST4.msg.showMsgDlg(
                '<span class="ui-icon ui-icon-alert" style="display:inline-block">&nbsp;</span>&nbsp;'
                +'<b>Potential harm to website</b>'
        +'<p>There are '+cnt+' CMS website records among the records being deleted.'
        +'Deleting them may irreparably damage the website generated from this database.</p>'
        +'<p>Are you sure you want to delete these records?</p>',
                function(){
                    that.doAction(true);
                    },'Confirm');
                return;        
            }
            
            window.hWin.HEURIST4.msg.showMsgDlg(
                '<span class="ui-icon ui-icon-alert" style="display:inline-block">&nbsp;</span>&nbsp;'
                +'Please confirm that you really wish to delete the selected records, <br>along with all associated bookmarks?', 
            function(){
                    that.doAction(true);
                    },'Confirm');
            return;
        }   
        
        //window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('.btnDoAction'), true );
            
            let scope = [], 
            rec_RecTypeID = 0;
            
            
            if(scope_val == 'selected'){
                scope = this._currentRecordsetSelIds;
            }else {
                scope = this._currentRecordset.getIds();
                if(scope_val  >0 ){ //filter by record type
                    rec_RecTypeID = scope_val;
                }   
            }
            
            //unique session id    
            let session_id = Math.round((new Date()).getTime()/1000);
        
            let request = {
                request_id : window.hWin.HEURIST4.util.random(),
                ids        : scope.join(','),
                session    : session_id
            };
                
            if(rec_RecTypeID>0){
                request['rec_RecTypeID'] = rec_RecTypeID;
            }
            
            this._showProgress( session_id, false, 1000 );
            
            //check source links   
            if(check_source!==true){                            
                request['check_links'] = true;                   
            }
            window.hWin.HAPI4.SystemMgr.user_log('rec_Delete', scope.length); //+' recs: '+request.ids.substr(0,100)));
            
            window.hWin.HAPI4.RecordMgr.remove(request, 
                    function(response){
                        that._hideProgress();
                        
                        if(response.status == window.hWin.ResponseStatus.OK){
                            
                            if(response.data.source_links_count>0 && response.data.source_links){
                                
                                    let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
'<p>You are asking to delete '+scope.length+' records ('
+'<a href="'+window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&q=ids:'+scope.join(',')
+'&nometadatadisplay=true" target="_blank">see list</a>)'
+' which are the target of record pointer fields in other'
+' records (<a href="'+window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                +'&q=ids:'+response.data.source_links+'&nometadatadisplay=true" target="_blank">see list</a>). '
+'This will also delete child records of the records you are deleting (if any) which may, in rare circumstances, themselves be the targets of record'
+' pointer fields from other records.</p>'

+'<p>Deleting a record which is the target of a record pointer field will delete the field value, which could render the record'
+' containing it invalid (if it is the only value remaining in a required field). If you wish to proceed, check the box below.</p>'

+'<p><label><input type="checkbox"> Delete records</label> which are the target of a record pointer field (along with their children, '
+'if any) and delete the record pointer values which reference them. Please see notes above. '
+'If you do this, we suggest using Admin > Verify integrity to check for missing values in required fields (it never does any harm to run thsi function from time to time).', 
function(){
    that.doAction(true, true);
},
{title:'Deleting target records ('+response.data.source_links_count+')',yes:'Delete records',no:'Cancel'});
    
  let btn = $dlg.parent().find('button:contains("Delete records")');
  let chb = $dlg.find('input[type="checkbox"]').on('change', function(){
      window.hWin.HEURIST4.util.setDisabled(btn, !chb.is(':checked') );
  })
  window.hWin.HEURIST4.util.setDisabled(btn, true);
                                
                                
                                return;
                            }

                            that._context_on_close = (response.data.deleted>0);
                            
                            that.closeDialog();
                            
                            let msg = 'Processed : '+response.data.processed + ' record'
                                + (response.data.processed>1?'s':'') +'. Deleted: '
                                + response.data.deleted  + ' record'
                                + (response.data.deleted>1?'s':'');
                                
                           if(response.data.bkmk_count>0){
                               msg += ('<br><br>Bookmark'+(response.data.bkmk_count>1?'s':'')
                                            +' removed: '+response.data.bkmk_count);
                           }
                           if(response.data.rels_count>0){
                               msg += ('<br><br>Relationship'+(response.data.rels_count>1?'s':'')
                                        +' removed: '+response.data.rels_count);
                           }
                           if(response.data.noaccess>0){
                               msg += ('<br><br>Not enough rights (logout/in to refresh) for '+response.data.noaccess+
                                        ' record' + (response.data.noaccess>1?'s':''));
                           }     
                            
                            
                            window.hWin.HEURIST4.msg.showMsgDlg(msg);
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
        
    },
  
});

