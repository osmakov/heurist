/**
* Widget to select 
*       1) image file (thumb and icon) from image library
*       2) tiled image mbtiles from uploaded_tilestacks
* Used in editing_input and as popup for selected records in rec_list
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

$.widget( "heurist.selectFile", {

    // default options
    options: {
        showFilter: false, //simple filter by name
        
        isdialog: true, //show in dialog or embedded

        onselect: null,
        
        source:'assets', //or uploaded_tilestacks
        
        extensions: null,
        
        title: 'Select Image',
        
        size: 64
    },
    
    _as_dialog:null, //reference to itself as dialog (see options.isdialog)
    _cachedRecordset: null,

    // the constructor
    _init: function() {

        var that = this;
        
        var sFilter = '';
        
        if(this.options.showFilter){
            sFilter = '<div class="ent_header">'
            +'<div class="header4" style="display: inline-block;width:7em;text-align:right;">'+window.hWin.HR('Find')+' </div>'
            +'<input class="input_search text ui-widget-content ui-corner-all" style="width:90px; margin-right:0.2em" autocomplete="off" autocorrect="off" autocapitalize="none" spellcheck="false">'
            +'</div>';
        }

        $('<div class="ent_wrapper">'
                +sFilter
                +'<div class="ent_content_full recordList"></div>'
                +'</div>').appendTo( this.element );

        if(this.options.showFilter){
            this._on(this.element.find('.input_search'),  { keyup:this.filterRecordList });            
        }else{
            this.element.find('.recordList').css('top',0);
        }

        var emptyMessage = `Specified files (${this.options.extensions}) are not found in `
            +(parseInt(this.options.source)>0?'given foldeer':this.options.source);
        
        //resultList with images
//init record list
        this.recordList = this.element.find('.recordList');
        this.recordList
                    .resultList({
                       recordDivEvenClass: 'recordDiv_blue',
                       eventbased: false, //do not listent global events
                       multiselect: false,

                       select_mode: 'select_single',
                       show_toolbar: false,
                       
                       entityName: this._entityName,
                       view_mode: 'thumbs',
                       
                       pagesize: 500,
                       empty_remark: emptyMessage,
                       renderer: function(recordset, record){ 
                           
                           var recID   = recordset.fld(record, 'file_id');
                           var recThumb;
                           if(recordset.fld(record, 'file_url')){
                                recThumb = recordset.fld(record, 'file_url')+recordset.fld(record, 'file_name');    
                           }else{
                                recThumb = window.hWin.HAPI4.baseURL 
                                            + recordset.fld(record, 'file_dir')
                                            + recordset.fld(record, 'file_name');
                           }
        
                           if(that.options.source.indexOf('assets')<0) {
                               
                               var sz = (that.options.extensions=='zip')
                               ? Math.round(recordset.fld(record, 'file_size')/1024/1024)+'MB'
                               : Math.round(recordset.fld(record, 'file_size')/1024)+'KB';

                               var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID
                               + '" style="width:250px !important;height:50px !important"><p>'
                               + recordset.fld(record, 'file_name')+'</p>size: '
                               + sz+'</div>';

                           }else{

                               var html_thumb = '<div class="recTypeThumb" style="top:0px !important;background-image: url(&quot;'
                               +recThumb+'&quot;);opacity:1;height:'+that.options.size+'px !important">'
                               +'</div>';

                               var html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID
                               + '" style="width:'+(that.options.size+4)+'px !important;height:'
                               + (that.options.size+4)+'px !important">'
                               + html_thumb + '</div>';
                           }
                           return html;  
                       }
                    });     

                this._on( this.recordList, {        
                        "resultlistonselect": function(event, selected_recs){
                            
                                    //var recordset = that.recordList.resultList('getRecordSet');
                                    //recordset = recordset.getSubSetByIds(selected_recs);
                                    var recordset = selected_recs;
                                    var record = recordset.getFirstRecord();
                                    var filename = recordset.fld(record, 'file_name')
                                    var res = { filename: filename,
                                                url:recordset.fld(record, 'file_url')+filename,
                                                path:recordset.fld(record, 'file_dir')+filename};

                                    that.options.onselect.call(that, res);           
                                    if(that._as_dialog){
                                        that._as_dialog.dialog('close');
                                    }
                                }
                        });        
         

            //search for images in given array of folder
            var that = this;                                                
       
            window.hWin.HAPI4.SystemMgr.get_foldercontent(this.options.source, this.options.extensions,
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        
                        let recset = new hRecordSet(response.data);
                        if(recset.length()>0){
                            
                            if(that.options.isdialog){

                                var $dlg = that.element.dialog({
                                    autoOpen: true,
                                    height: 640,
                                    width: 840,
                                    modal: true,
                                    title: window.hWin.HR(that.options.title),
                                    resizeStop: function( event, ui ) {
                                        var pele = that.element.parents('div[role="dialog"]');
                                        that.element.css({overflow: 'none !important', width:pele.width()-24 });
                                    },
                                    close:function(){
                                        that._as_dialog.remove();    
                                    }
                                });
                                
                                that._as_dialog = $dlg; 
                            }
                            
                            that._cachedRecordset = recset;
                            
                            that.recordList.resultList('updateResultSet', recset);
                        }else{
                            if(that._as_dialog) that._as_dialog.dialog('close');
                            window.hWin.HEURIST4.msg.showMsgFlash(emptyMessage);    
                        }

                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
     
         
         
    }, //end _create

    /* private function */
    _refresh: function(){
    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        // remove generated elements
        this.recordList.remove();
    },
    
    //
    //
    //
    filterRecordList: function(event){
        
        var val = this.element.find('.input_search').val().trim();
        var subset;
        if(val==''){
            subset = this._cachedRecordset;
        }else{
            subset = this._cachedRecordset.getSubSetByRequest({'file_name':val}, null);
        }
            
        this.recordList.resultList('updateResultSet', subset);
    },

});
