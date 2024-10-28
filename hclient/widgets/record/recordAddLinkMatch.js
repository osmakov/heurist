/**
* recordAddLinkMatch.js - create links by matching selected fields
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

$.widget( "heurist.recordAddLinkMatch", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 520,
        width:  800,
        modal:  true,
        init_scope: 'selected',
        title:  'Foreign Key matching. Add links between records by matching field values',
        
        htmlContent: 'recordAddLinkMatch.html',
        
        relationtype: null
        
    },

    source_RecTypeID:null, 
    target_RecTypeID:null,
    sSourceName:'',
    sTargetName:'',
    
    //    
    //
    //
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Create links');
        return res;
    },

    //
    // select option by rectypes
    //
    _fillSelectRecordScope: function (){
        
        this._$('select').css({width:'30em','max-width':'35em'});

        let scope_types = this.options.scope_types;
        this.selectRecordScope.empty();
        
        if(scope_types=='none'){
            this.selectRecordScope.parent().hide();
            return;    
        }

        let selScope = this.selectRecordScope.get(0); //selector

        let rty_ID = 0;
        let rectype_Ids = this._currentRecordset.getRectypes();
        
        if(rectype_Ids.length==1){

            rty_ID = rectype_Ids[0];
            this.source_RecTypeID = rty_ID;
            
            window.hWin.HEURIST4.ui.addoption(selScope,
                'all', 'All records: ' + $Db.rty(rty_ID,'rty_Plural'));
                
            if(this._currentRecordsetSelIds.length>0){
                window.hWin.HEURIST4.ui.addoption(selScope,
                    'selected', 'Selected records: ' + $Db.rty(rty_ID,'rty_Plural'));
            }
        }
          
        if(!(rty_ID>0)){
            window.hWin.HEURIST4.msg.showMsgDlg(
        '<b>Mixed record types</b>'
        +'<p>The current query must contain only a single record type (this is enforced to avoid accidental errors).</p>' 
        +'<p>Please select records of a single type, either by individual selection or a revised filter, and repeat this action. </p>');

            return false;
        }
            
        
        this._on( this.selectRecordScope, { change: this._onRecordScopeChange} );        
        //this.selectRecordScope.val(rty_ID);    
        if(selScope.selectedIndex<0) selScope.selectedIndex=0;
        
        window.hWin.HEURIST4.ui.initHSelect(this.selectRecordScope, false);
        
        this._onRecordScopeChange();
        
        this.selectRecordScope.parent().hide();
        
        return true;
    },

    //
    // overwritten
    //
    _onRecordScopeChange: function () 
    {
        this._fillSelectFieldTypes('source', this.source_RecTypeID);
    },
 
    //
    // find pointer and relationships for selected record type 
    // party - source or target                                            
    // rty_ID - selected record type
    // 
    _fillSelectFieldTypes: function (party, recRecTypeID) {

        // create matching field
        let fieldSelect = $('#sel_fieldtype_'+party);
        
        let details = $Db.rst(recRecTypeID);
        if(details)
        {   

        if(party=='source')
        {
            
            let fieldPointerSel = this._$('#sel_pointer_field');
            fieldPointerSel.empty();

            let that = this;
            let has_fields = false;
            // get structures for both record types and filter out link and relation maker fields for links
            //                                      and text and numeric fields for matching             
            details.each2(function(dtyID, detail) {
            
                let field_type = $Db.dty(dtyID, 'dty_Type');
                let req_type  = detail['rst_RequirementType'];
                
                //|| field_type=='relmarker')
                if ( (field_type!='resource') || req_type=='forbidden' ) {
                     return true;//continue
                }
                
                //get name, contraints
                let dtyName = detail['rst_DisplayName'];
                if(!has_fields){
                    window.hWin.HEURIST4.ui.addoption(fieldPointerSel.get(0), 0, window.hWin.HR('select'));    
                }
                window.hWin.HEURIST4.ui.addoption(fieldPointerSel.get(0), dtyID, dtyName);
                has_fields = true;
            });//for fields
            
            if(!has_fields){
                //There are no record pointer fields in current query record type into which matched record IDs can be inserted
                window.hWin.HEURIST4.ui.addoption(fieldPointerSel.get(0), 0, 'There are no record pointer fields');
            }
        
            this._on( fieldPointerSel, { change: that._fillTargetRecordTypes} );        
            window.hWin.HEURIST4.ui.initHSelect(fieldPointerSel, false);
            fieldPointerSel.trigger('change');
            
        }//for source 
        
        window.hWin.HEURIST4.ui.createRectypeDetailSelect(fieldSelect.get(0), recRecTypeID, 
                                    ['freetext','blocktext'], window.hWin.HR('select'));
                    
        if(fieldSelect.find('option').length==1){
            fieldSelect.empty();
            window.hWin.HEURIST4.ui.addoption(fieldSelect.get(0), 0, 'There are no text fields');
        }
                     
        window.hWin.HEURIST4.ui.initHSelect(fieldSelect, false);
        this._on(fieldSelect,{change:this._findMatchesCount});
        fieldSelect.trigger('change');
        
        }else{
            fieldSelect.empty();
            if(fieldSelect.hSelect("instance")!=undefined){
                fieldSelect.hSelect("destroy"); 
            }
        }
    },  

    //
    //
    //    
    _findMatchesCount: function(event){
        
        let fieldSelect = $(event.target);
        
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('.btnDoAction'), true);
        
        if(fieldSelect.attr('id')=='sel_fieldtype_source'){

            let cnt_info = this._$('#count_source_unique').text('');
            
            if(fieldSelect.val()>0){
                cnt_info.addClass('ui-icon ui-icon-loading-status-balls rotate')
            
                //search all and unique detail values
                window.HAPI4.RecordMgr.get_aggregations({a:'count_details',
                    rec_IDs: this._getRecordsScope().join(','),
                    rty_ID:this.source_RecTypeID, 
                    dty_ID:fieldSelect.val()}, 
                function(response){     
                    cnt_info.removeClass('ui-icon ui-icon-loading-status-balls rotate')
                    if(response.status == window.hWin.ResponseStatus.OK){
                        cnt_info.text(response.data.total+' values ('+response.data.unique+' unique)');                
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
            }
        }
        
        if(this.target_RecTypeID>0){

            let fieldSelectTrg = this._$('#sel_fieldtype_target');
            let fieldSelectSrc = this._$('#sel_fieldtype_source');

            let cnt_info2 = this._$('#count_target_matches').text('');
            
            if(fieldSelectSrc.val()>0 && fieldSelectTrg.val()>0){
                cnt_info2.addClass('ui-icon ui-icon-loading-status-balls rotate')
                
                let that = this;
            
                window.HAPI4.RecordMgr.get_aggregations({a:'count_matches',
                                rec_IDs: this._getRecordsScope().join(','),
                                rty_src:this.source_RecTypeID, 
                                dty_src:fieldSelectSrc.val(),
                                rty_trg:this.target_RecTypeID, 
                                dty_trg:fieldSelectTrg.val()
                                                        }, 
                function(response){     
                    cnt_info2.removeClass('ui-icon ui-icon-loading-status-balls rotate')
                    if(response.status == window.hWin.ResponseStatus.OK){
                        cnt_info2.text(response.data+' matches');                
                        that._enableActionButton();
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
            }
        }
        
    },
    
    //
    // source field has been selectd
    // 1) fill target record selector
    // 2) find unique values for selected field 
    //
    _fillTargetRecordTypes: function(event){

        let dtID = $(event.target).val();
        
        if(!(dtID>0)) return;
        
        let rt_constraints = (dtID>0)?$Db.dty(dtID, 'dty_PtrTargetRectypeIDs'):'';
        
        if(!Array.isArray(rt_constraints)){
            if(window.hWin.HEURIST4.util.isempty(rt_constraints)){
                rt_constraints = [];
            }else{
                rt_constraints = rt_constraints.split(',');        
            }
        }
        
        this.targetRtySelect = $('#target_record_type');
        window.hWin.HEURIST4.ui.createRectypeSelectNew(this.targetRtySelect.get(0), 
            {rectypeList:rt_constraints, useHtmlSelect:true, useCounts:true});
        
        this._on( this.targetRtySelect, {
                change: this._onTargetRtySelectChange} );        
        
        window.hWin.HEURIST4.ui.initHSelect(this.targetRtySelect, false);
        
        this._onTargetRtySelectChange();
        
    },
    
    _onTargetRtySelectChange: function(){
        this._$('#count_target_rty').text('');
        this.target_RecTypeID = this.targetRtySelect.val(); 
        if(this.target_RecTypeID>0){
            let rty_usage_cnt = $Db.rty(this.target_RecTypeID,'rty_RecCount');
            if(rty_usage_cnt>0){
                this._$('#count_target_rty').text( rty_usage_cnt + ' records' );
            }
        }     
        this._fillSelectFieldTypes('target', this.target_RecTypeID);
        if(this._$('#count_target_rty').text()==''){
            this._$('#sel_fieldtype_target').empty();
            this._$('#count_target_matches').empty();
            this._enableActionButton();
        }
        
    },
  
    //
    //
    //
    getFieldValue: function (input_id) {
        let ele =  this._$('#'+input_id);
        if(ele.length>0){
            let sel = ele.editing_input('getValues');
            if(sel && sel.length>0){
                return sel[0];
            }
        }
        return null;
    },

    
    //
    // enable add link button
    //
    _enableActionButton: function (){
        
        let isEnabled = (parseInt($('#count_target_matches').text())>0);
        
        if(isEnabled){
            let sel_field  = this._$('input[type="radio"][name="link_field"]:checked');
            
            if(sel_field.attr('data-type')=='relmarker'){
                //in case relmarker check if reltype selected
                let dtyID = sel_field.val()
                let termID = this.getFieldValue('rt_source_sel_'+dtyID);        
                isEnabled = (termID>0);
            }                
        }  
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('.btnDoAction'), !isEnabled );
    }, 
    
    _getRecordsScope: function()
    {
        let isSelection = (this.selectRecordScope.val()=='selected');
        let currentScope = isSelection?this._currentRecordsetSelIds:this._currentRecordset.getIds();
        return currentScope;
        
    },
    
    //
    //
    //
    doAction: function(){
        
        if(this._$('#div_result').is(':visible')){
            this._setBtnLabels(false);
            this._$('#div_result').hide();
            this._$('#div_fieldset').show();
            return;
        }

        let dty_ID = this._$('#sel_pointer_field').val();
        /*
        let ele = this._$('input[type="radio"][name="link_field"]:checked');
        let dty_ID = ele.val();
        let trm_ID = 0;
        let data_type = ele.attr('data-type');   //resource (record pointer) or relmarker
        if(data_type!='resource'){
            trm_ID = this.getFieldValue('rt_source_sel_'+dty_ID);
        }*/
        
        let currentScope = this._getRecordsScope();
        
        
        let div_res = this._$('#div_result');
        div_res.empty();
        let that = this;
        
        if ($('input[name="to_replace"]:checked').val()=='nonmatch') {
                window.HAPI4.RecordMgr.get_aggregations({a:'count_matches',
                                nonmatch: 1,
                                rec_IDs: this._getRecordsScope().join(','),
                                rty_src:this.source_RecTypeID, 
                                dty_src:$('#sel_fieldtype_source').val(),
                                rty_trg:this.target_RecTypeID, 
                                dty_trg:$('#sel_fieldtype_target').val()
                                                        }, 
                function(response){     
                    if(response.status == window.hWin.ResponseStatus.OK){
                        that.element.find('#div_fieldset').hide();
                        let csv_res = '<div style="padding:10px;height:100%;overflow:auto;">UNMATCHED VALUES<br><pre>H-ID&#9;Value&#9;Record title<br>';
                        for(let idx in response.data){
                            let row = response.data[idx];
                            for(let idx2 in row){
                                row[idx2] = window.hWin.HEURIST4.util.stripTags(row[idx2]).trim();
                            }
                            csv_res = csv_res + row.join("&#9;")+"<br>";
                        }
                        div_res.html(csv_res+'</pre></div>').show();
                        that._setBtnLabels(true);
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
        }else{
            
            let session_id = Math.round((new Date()).getTime()/1000);
        
            let request = {a: 'add_links_by_matching',
                                session: session_id,
                                dty_ID:  dty_ID,
                                //trm_ID: trm_ID,
                                rec_IDs: currentScope.join(','),
                                rty_src:  this.source_RecTypeID,
                                dty_src: $('#sel_fieldtype_source').val(),
                                rty_trg: this.target_RecTypeID,
                                dty_trg: $('#sel_fieldtype_target').val(),
                                replace: ($('input[name="to_replace"]:checked').val()=='replace'?1:0)
                        };

            this._showProgress( session_id, false, 1000 );
            
            window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                
                that._hideProgress();
                
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    that.element.find('#div_fieldset').hide();
                    div_res.html(
    '<div style="padding:10px;display:table">'
    +`<span class="table-cell">Records passed to process</span><span class="table-cell">&nbsp;&nbsp;${currentScope.length}</span><br><br>`
    +`<span class="table-cell">Records updated</span><span class="table-cell">&nbsp;&nbsp;${response.data['records_updated']}</span><br><br>`
    +`<span class="table-cell">Links added</span><span class="table-cell">&nbsp;&nbsp;${response.data['added']}</span><br><br>`
    +`<span class="table-cell">Links already exist</span><span class="table-cell">&nbsp;&nbsp;${response.data['exist']}</span></div>`)
                    .show();
                    
                    that._setBtnLabels(true);
                    
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response); 
                }
            });
            
        }
    },
    
    _setBtnLabels: function(is_done){
        let lab1, lab2;
        if(is_done){
            lab1 = 'New Action';
            lab2 = 'Done';
        }else{
            lab1 = 'Create links';
            lab2 = 'Cancel';
        }
        this.element.parents('.ui-dialog').find('.btnDoAction').button({label:window.hWin.HR(lab1)});
        this.element.parents('.ui-dialog').find('.btnCancel').button({label:window.hWin.HR(lab2)});
    }
    
        
});

