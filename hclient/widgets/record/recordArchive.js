/**
* recordArchive.js - Lookup and restore archive records
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

$.widget( "heurist.recordArchive", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 520,
        width:  800,
        modal:  true,
        
        title:  'Lookup and restore archive records',
        
        htmlContent: 'recordArchive.html',
        
        mapping:null, //maps external fields to heurist field details
        add_new_record: false  //if true it creates new record on selection
        //define onClose to get selected values
    },
    
    recordList:null,

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        let that = this;
        
       
        this._$('fieldset > div > .header').css({width:'80px','min-width':'120px'})
        
        this.options.resultList = $.extend(this.options.resultList, 
        {
               recordDivEvenClass: 'recordDiv_blue',
               eventbased: false,  //do not listent global events

               multiselect: false, //(this.options.select_mode!='select_single'), 

               select_mode: 'select_single', //this.options.select_mode,
               selectbutton_label: 'select!!', //this.options.selectbutton_label, for multiselect
               
               view_mode: 'list',
               show_viewmode:false,
               
               
               entityName: this._entityName,
               //view_mode: this.options.view_mode?this.options.view_mode:null,
               
               pagesize:(this.options.pagesize>0) ?this.options.pagesize: 9999999999999,
               empty_remark: '<div style="padding:1em 0 1em 0">Nothing found</div>',
               renderer: this._rendererResultList,
               rendererHeader: that._recordListHeaderRenderer
        });                

        //init record list
        this.recordList = this._$('#div_result');
        this.recordList.resultList( this.options.resultList );     
        
        this._on( this.recordList, {        
                "resultlistonselect": function(event, selected_recs){
                            window.hWin.HEURIST4.util.setDisabled( 
                                this.element.parents('.ui-dialog').find('.btnDoAction'), 
                                (selected_recs && selected_recs.length()!=1));
                        },
                "resultlistondblclick": function(event, selected_recs){
                            if(selected_recs && selected_recs.length()==1){
                                this.doAction();                                
                            }
                        }
                //,"resultlistonaction": this._onActionListener        
                });
        
        
        
        this._on(this._$('#btnStartSearch').button(),{
            'click':this._doSearch
        });
        
        this._on(this._$('input'),{
            'keypress':this.startSearchOnEnterPress
        });
        
        
        this._$('#inpt_date').datepicker({
                            showOn: "button",
                            showButtonPanel: true,
                            changeMonth: true,
                            changeYear: true,
                            dateFormat: 'yy-mm-dd'
                        });
        
        return this._super();
    },
    
    //
    //
    //
    startSearchOnEnterPress: function(e){
        
        let code = (e.keyCode ? e.keyCode : e.which);
        if (code == 13) {
            window.hWin.HEURIST4.util.stopEvent(e);
            e.preventDefault();
            this._doSearch();
        }

    },
    
    _recordListHeaderRenderer: function(){
/*    
        return '<div style="width:40px;font-size:0.9em"></div><div style="width:4ex">ID</div>'
                +'<div style="width:80ex;font-size:0.9em">Record</div>'
                +'<div style="width:60px;font-size:0.9em">was (action)</div>'
                +'<div style="width:6ex;font-size:0.9em">by user</div>'
                +'<div style="width:16ex;font-size:0.9em">On (datetime)</div>';
*/
        return '<div style="width:20px;"></div><div style="width:25px;">ID</div>'
                +'<div style="width:390px">Record</div>'
                +'<div style="width:66px">was (action)</div>'
                +'<div style="width:44px">by user</div>'
                +'<div style="width:120px">On (datetime)</div>';
                
    },
    
    //
    //
    //
    _rendererResultList: function(recordset, record){
        
        function fld(fldname, width){
            let s = recordset.fld(record, fldname);
            s = s?s:'';
            if(width>0){
                s = '<div style="display:inline-block;width:'+width+'px" class="truncate">'+s+'</div>';
            }
            return s;
        }
        
        let arcID = fld('arc_ID');
        let arcUser = fld('arc_ChangedByUGrpID',40);
        let arcDate = fld('arc_TimeOfChange',120);
        let arcMode = '<div style="display:inline-block;width:80px" class="truncate">'
            +(fld('arc_ContentType')=='del'?'deleted':'updated')+'</div>';
        
        let recID = fld('rec_ID');
        let rectypeID = fld('rec_RecTypeID');
        let recTitle = fld('rec_Title',400); 
        
        recTitle = fld('rec_ID',30) + recTitle + arcMode + arcUser + arcDate; 
        
        
        let recIcon = window.hWin.HAPI4.iconBaseURL + rectypeID;
        
        let html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'
                + window.hWin.HAPI4.iconBaseURL + rectypeID + '&version=thumb&quot;);"></div>';

        let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+arcID+'" rectype="'+rectypeID+'">'
            + html_thumb
            
                + '<div class="recordIcons">'
                +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
                +     '" class="rt-icon" style="background-image: url(&quot;'+recIcon+'&quot;);"/>' 
                + '</div>'
            
                //+ '<div class="recordTitle" style="left:30px;right:2px">'
                    +  recTitle
                //+ '</div>'
            + '</div>';
        return html;
    },

    //    
    //
    //
    _getActionButtons: function(){
        let res = this._super(); //dialog buttons
        res[1].text = window.hWin.HR('Restore');
       
        return res;
    },

    //
    // Either perform search or select entry in resultList and triggers addition of new record
    //
    doAction: function(){

            //detect selection
            let sel = this.recordList.resultList('getSelected', false);
            
            if(sel && sel.length() == 1){
                
                window.hWin.HEURIST4.msg.showMsgDlg('Are you sure?');
                
                /*
                if(this.options.add_new_record){
                    //create new record 
                    
                    //this._addNewRecord(this.options.rectype_for_new_record, sel);                     
                }else{
                    //pass mapped values and close dialog
                    this._context_on_close = sel;
                    this._as_dialog.dialog('close');
                }
                */
                
            }
        
    },
    
    //
    // create search url
    // perform search
    //
    _doSearch: function(){
        
        if(this._$('#inpt_recid').val()=='' && this._$('#inpt_user').val()==''){
            window.hWin.HEURIST4.msg.showMsgFlash('Define record ID or user', 500);
            return;
        }
        if(this._$('#inpt_recid').val()=='' && this._$('#inpt_date').val()==''){
            window.hWin.HEURIST4.msg.showMsgFlash('Define record ID or date', 500);
            return;
        }
        
        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());
        
        let request = {}
    
        if(this._$('#inpt_recid').val()!=''){
            request['arc_PriKey'] = this._$('#inpt_recid').val();
        }
        if(this._$('#inpt_user').val()!=''){
            request['arc_ChangedByUGrpID'] = this._$('#inpt_user').val();
        }
        if(this._$('#inpt_state').val()!=''){
            request['arc_ContentType'] = this._$('#inpt_state').val();
        }
        if(this._$('#inpt_date').val()!=''){
            request['arc_TimeOfChange'] = this._$('#inpt_date').val();
        }
        
        request['arc_Table'] = 'rec';
        request['sort:arc_TimeOfChange'] = '-1' 
        
        request['a']          = 'search'; //action
        request['entity']     = 'sysArchive';
        request['details']    = 'full';
        request['convert']    = 'records_list';

        //returns recordset of heurist records with additional fields
        let that = this;
        window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        window.hWin.HEURIST4.msg.sendCoverallToBack();
                        if(response.status == window.hWin.ResponseStatus.OK){
                            that._onSearchResult(new HRecordSet(response.data));
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
    },
    
    _onSearchResult: function(recordset){
        
        this.recordList.show();
                        
       if (recordset && recordset.length()>0){
/*            
            var res_records = {}, res_orders = [];
            var DT_GEO_OBJECT = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'];
            
            var fields = ['rec_ID','rec_RecTypeID'];
            var map_flds = Object.keys(this.options.mapping.fields);
            fields = fields.concat(map_flds);
            
            for(var k=0; k<map_flds.length; k++){
                map_flds[k] = map_flds[k].split('.'); 
            }
            
            //parse json
            var i=0;
            for(;i<geojson_data.features.length;i++){
                var feature = geojson_data.features[i];
                
                var recID = i+1;
                res_orders.push(recID);
                
                var values = [recID, this.options.mapping.rty_ID];
                for(var k=0; k<map_flds.length; k++){
                    
                    var val = feature[ map_flds[k][0] ];
                    
                    for(var m=1; m<map_flds[k].length; m++){
                        if(val && val[ map_flds[k][m] ]){
                            val = val[ map_flds[k][m] ];
                        }
                    }      
                    
                    if(DT_GEO_OBJECT == this.options.mapping.fields[map_flds[k]]){
                        if(!window.hWin.HEURIST4.util.isempty(val)){
                            val = {"type": "Feature", "geometry": val};
                            var wkt = stringifyMultiWKT(val);    
                            if(window.hWin.HEURIST4.util.isempty(wkt)){
                                val = '';
                            }else{
                                //@todo the same code mapDraw.php:134
                                var typeCode = 'm';
                                if(wkt.indexOf('GEOMETRYCOLLECTION')<0 && wkt.indexOf('MULTI')<0){
                                    if(wkt.indexOf('LINESTRING')>=0){
                                        typeCode = 'l';
                                    }else if(wkt.indexOf('POLYGON')>=0){
                                        typeCode = 'pl';
                                    }else {
                                        typeCode = 'p';
                                    }
                                }
                                val = typeCode+' '+wkt;
                            }
                        }
                    }
                        
                    values.push(val);    
                }
                res_records[recID] = values;
            }

            var res_recordset = new HRecordSet({
                count: res_orders.length,
                offset: 0,
                fields: fields,
                rectypes: [this.options.mapping.rty_ID],
                records: res_records,
                order: res_orders,
                mapenabled: true //???
            });              
*/            
            this.recordList.resultList('updateResultSet', recordset);            
       }else{
            //ele.text('ERROR '+geojson_data);                    
            this.recordList.resultList('updateResultSet', null);            
       }
    },

    
    //
    // 
    //
    _addNewRecord: function (record_type, field_values){
        
        window.hWin.HEURIST4.msg.sendCoverallToBack();
    }
    
        
});

