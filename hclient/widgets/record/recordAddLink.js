/**
* recordAddLink.js - create link or relationships for given scope of records
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

$.widget( "heurist.recordAddLink", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 520,
        width:  800,
        modal:  true,
        init_scope: 'selected',
        title:  'Add new link or create a relationship between records',
        
        htmlContent: 'recordAddLink.html',
        
        source_ID:null,
        target_ID:null,
        relmarker_dty_ID:null,//relmarker field type id  - this is creation of relationship only
        source_AllowedTypes:null,
        onlyReverse:false,

        relationtype: null
        
    },

    source_RecTypeID:null, 
    target_RecTypeID:null,
    sSourceName:'',
    sTargetName:'',
    _openRelationRecordEditor: false,
    
    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){
        
        
        if(window.hWin.HEURIST4.util.isempty(this.options.source_ID)){    
            //source reccord not defined - show scope selector
            
            this._$('#div_source1').hide();
            this._$('#div_source2').css('display','inline-block');
        }else{
            this._$('#div_source_header').css('vertical-align','top');
            this.getRecordValue(this.options.source_ID, 'source'); 
            
            //show hint 
            this._$('#edit_attrib_helper').show();
        }
       
        if(window.hWin.HEURIST4.util.isempty(this.options.target_ID)){
            //show record selector
            this._$('#div_target1').hide();
            this._$('#div_target2').css('display','inline-block');
        }else{
            this._$('#div_target_header').css('vertical-align','top');
            
            this.getRecordValue(this.options.target_ID, 'target');

            if(window.hWin.HEURIST4.util.isempty(this.options.source_ID) && this.options.source_AllowedTypes && this.options.relmarker_dty_ID>0){  //inward relation
                
                this.options.source_AllowedTypes = this.options.source_AllowedTypes.split(',');
                
                this._fillSelectFieldTypes('source', this.options.source_AllowedTypes[0], null);
                this._createInputElement_RecordSelector('source', this.options.source_AllowedTypes);
            }
        }
        
        return this._super();
    },


    //    
    //
    //
    _getActionButtons: function(){
        let res = this._super();

        res[1].text = window.hWin.HR((this.options.source_ID>0)?'Create link':'Create links');
        
        if(this.options.source_ID>0){ //this.options.relmarker_dty_ID>0){
        
            let that = this;
            //enable for relationships only
            res.splice(1, 0, 
                 {text:window.hWin.HR('Edit attributes'),
                    disabled:'disabled',
                    class: 'ui-button-action btnDoAction2',
                    css:{'float':'right'}, //'font-size':'0.82em', 'margin-top':'0.6em', 'padding':'6.1px' 
                    click: function() { 
                            that._openRelationRecordEditor = true;
                            that.doAction(); 
                    }});
        }
        
        return res;
    },

    //
    // select option by rectypes
    //
    _fillSelectRecordScope: function (){

        let scope_types = this.options.scope_types;
        this.selectRecordScope.empty();
        
        if(scope_types=='none' || this.options.source_ID>0){
            this.selectRecordScope.parent().hide();
            return;    
        }

        let opt, selScope = this.selectRecordScope.get(0); //selector
        window.hWin.HEURIST4.ui.addoption(selScope,0,'please select the records to be affected …');

        let rty = 0;
        let rectype_Ids = this._currentRecordset.getRectypes();
        
        if(rectype_Ids.length==1){

            rty = rectype_Ids[0];
            window.hWin.HEURIST4.ui.addoption(selScope,
                rty, 'All records: ' + $Db.rty(rty,'rty_Plural'));
        }
        
        rectype_Ids = [];
        let sels = this._currentRecordsetSelIds;
        if(sels && sels.length>0)
            for (let idx in sels){ //find all selected rectypes
              if(idx>=0){  
                let rec = window.hWin.HAPI4.currentRecordset.getById(sels[idx]);
                let rt = Number(window.hWin.HAPI4.currentRecordset.fld(rec, 'rec_RecTypeID'));
                if(rectype_Ids.indexOf(rt)<0) rectype_Ids.push(rt);
              }
            }

        if(rectype_Ids.length==1){

            rty = rectype_Ids[0];
            
            opt = new Option('Selected records: ' + $Db.rty(rty,'rty_Plural'), rty);
            $(opt).attr('data-select', 1);
            selScope.appendChild(opt);
            
            
        }
          
        if(!(rty>0)){
            window.hWin.HEURIST4.msg.showMsgDlg(
        '<b>Mixed record types</b>'
        +'<p>Relationship links can only be built for one record type at a time. </p>'
        +'<p>Please select records of a single type, either by individual selection or a revised filter, and repeat this action. </p>');
            return false;
        }
            
        
        this._on( this.selectRecordScope, {
                change: this._onRecordScopeChange} );        
        this.selectRecordScope.val(rty);    
        if(selScope.selectedIndex<0) selScope.selectedIndex=0;
        
        window.hWin.HEURIST4.ui.initHSelect(this.selectRecordScope, false);
        
        this._onRecordScopeChange();
        
        return true;
    },
    
    //
    // select option by rectypes
    //
    _fillSelectRecordScope_byRty: function (){

        let scope_types = this.options.scope_types;
        this.selectRecordScope.empty();
        
        let useHtmlSelect = false;
        
        if(scope_types=='none'){
            this.selectRecordScope.parent().hide();
            return;    
        }

        let selScope = this.selectRecordScope.get(0); //selector

        let rectype_Ids = this._currentRecordset.getRectypes();
        
        window.hWin.HEURIST4.ui.addoption(selScope,'','please select the records to be affected …');
       
        let hasSelection = (this._currentRecordsetSelIds &&  this._currentRecordsetSelIds.length > 0);

        if(hasSelection){            
                if(useHtmlSelect){
                    let new_optgroup = document.createElement("optgroup");//
                    new_optgroup.label = 'All records';
                    new_optgroup.depth = 0;
                    selScope.appendChild(new_optgroup);
                }else{
                    let opt = window.hWin.HEURIST4.ui.addoption(selScope, 0, 'All records');
                    $(opt).attr('disabled', 'disabled');
                    $(opt).attr('group', 1);
                }
        }
        
        
        rectype_Ids.forEach(rty => {
            let opt = new Option('only: '+$Db.rty(rty,'rty_Plural'), rty);
            if(hasSelection){
                opt.className = 'depth1';
                $(opt).attr('depth', 1);
                selScope.appendChild(opt);    //new_optgroup.
            }else{
                selScope.appendChild(opt);
            }
        });
        
        if(hasSelection){
            
            rectype_Ids = [];
            let sels = this._currentRecordsetSelIds;
            for (let idx in sels){ //find all selected rectypes
              if(idx>=0){  
                let rec = window.hWin.HAPI4.currentRecordset.getById(sels[idx]);
                let rt = Number(window.hWin.HAPI4.currentRecordset.fld(rec, 'rec_RecTypeID'));
                if(rectype_Ids.indexOf(rt)<0) rectype_Ids.push(rt);
              }
            }
            
            if(rectype_Ids.length>0){
                if(useHtmlSelect){
                    let new_optgroup = document.createElement("optgroup");//
                    new_optgroup.label = 'Selected';
                    new_optgroup.depth = 0;
                    selScope.appendChild(new_optgroup);
                }else{
                    let opt = window.hWin.HEURIST4.ui.addoption(selScope, 0, 'Selected');
                    $(opt).attr('disabled', 'disabled');
                    $(opt).attr('group', 1);
                }
            }
            
            rectype_Ids.forEach(rty => {
                    //need unique value - otherwise jquery selectmenu fails to recognize
                    let opt = new Option($Db.rty(rty,'rty_Plural'), 's'+rty); 
                    $(opt).attr('data-select', 1);
                    if(hasSelection){
                        $(opt).attr('depth', 1);
                        selScope.appendChild(opt); //new_optgroup   
                    }else{
                        selScope.appendChild(opt);
                    }
            });
            
        } //hasSelection
       
       
        this._on( this.selectRecordScope, {
                change: this._onRecordScopeChange} );        
        this.selectRecordScope.val(this.options.init_scope);    
        if(selScope.selectedIndex<0) selScope.selectedIndex=0;
        
        window.hWin.HEURIST4.ui.initHSelect(this.selectRecordScope, false);
        
        this._onRecordScopeChange();
    },       
       
 
    //
    // overwritten
    //
    _onRecordScopeChange: function () 
    {
        this.source_RecTypeID = this.selectRecordScope.val(); 
        if(this.source_RecTypeID && this.source_RecTypeID[0]=='s') this.source_RecTypeID = this.source_RecTypeID.substr(1);
        this._fillSelectFieldTypes('source', this.source_RecTypeID);
    },
    
  
 
    //
    // find pointer and relationships for selected record type 
    // party - source or target                                            
    // rty_ID - selected record type
    // 
    _fillSelectFieldTypes: function (party, recRecTypeID, oppositeRecTypeID) {
        
        this._$('#'+party+'_field').empty();
        
        //let $fieldset = $('#target_field').empty(); //@todo clear target selection only in case constraints were changed
            
        let details = $Db.rst(recRecTypeID);
            
        if(details)
        {   
            
            let that = this;
        // get structures for both record types and filter out link and relation maker fields
        details.each2(function(dty, detail) {
            
            let field_type = $Db.dty(dty, 'dty_Type');
            
            let req_type  = detail['rst_RequirementType'];
            
            if ((!(field_type=='resource' || field_type=='relmarker')) || req_type=='forbidden') {
                 return true;//continue
            }
            
            if(that.options.relmarker_dty_ID>0){  //detail id is defined in options
            
                if( that.options.relmarker_dty_ID==dty && (party=='source') ){  //&& (source_ID>0 || target_ID>0)
                    
                }else{
                    return true;//continue
                }
            }
            
            //get name, contraints
            let dtyName = detail['rst_DisplayName'];
            let dtyPtrConstraints = $Db.dty(dty, 'dty_PtrTargetRectypeIDs');
            let recTypeIds = null;
            if(!window.hWin.HEURIST4.util.isempty(dtyPtrConstraints)){
                recTypeIds = dtyPtrConstraints.split(',');
            }
            
            //check if constraints satisfy to opposite record type
            // no constraints,  opposite party is not specified yet,  opposit rty_ID satifies to the constraint
            if(recTypeIds==null || 
               window.hWin.HEURIST4.util.isempty(oppositeRecTypeID) || 
               recTypeIds.indexOf(oppositeRecTypeID)>=0 ){
                
                let isAlready = false;
                
                if(party=='target')
                 dtyName = dtyName + ' [ reverse link, target to source ]';
                
                //add UI elements
    $('<div class="field_item" style="line-height:2.5em;padding-left:20px">'
    +'<label style="font-style:italic">' //for="cb'+party+'_cb_'+dty+'"
    +'<input name="link_field" type="radio" id="cb'+party+'_cb_'+dty+'" '
    +(isAlready?'disabled checked="checked"'
        :' data-party="'+party+'" value="'+dty+'" data-type="'+field_type+'"')
    +' class="cb_addlink text ui-widget-content ui-corner-all"/>'                                     
    + dtyName+'</label>&nbsp;'
    + '<div style="display:inline-block;vertical-align:-webkit-baseline-middle;padding-left:20px">'
    + '<div id="rt_'+party+'_sel_'+dty+'" style="display:table-row;line-height:21px"></div></div>'
    +'<div>').appendTo(that.element.find('#'+party+'_field'));
    
    
                if(field_type=='relmarker'){
                    
                    that._createInputElement_Relation( party, recRecTypeID, dty ); 
                    
                }else{
                    //that.element.find('#rt_'+party+'_sel_'+dty).hide();   //hide relation type selector
                }
            }

            if(party=='source' && window.hWin.HEURIST4.util.isempty(that.options.target_ID)){
                    that._on(that.element.find('#cbsource_cb_'+dty),{change:that._createInputElement});
            }else{
                    //enable add link button
                    that._on(that.element.find('#cb'+party+'_cb_'+dty),{change:that._enableActionButton});
            }
            
            
            if( that.options.relmarker_dty_ID>0 && (party=='source' && that.options.source_ID>0) ){            
                return false;
            }
        });//for fields

        if(this.options.relmarker_dty_ID>0){
            //hide radio and field name - since it is the only one field in list
            this._$('#source_field').find('.field_item').css('padding-left',0);
            this._$('#source_field').find('.field_item > div').css('padding-left',0);
            this._$('#source_field').find('.field_item > label').hide();
            this._$('#source_field').find('input[type=radio]').hide().trigger('click'); //prop('checked',true).
        }
                            
        
        if(party=='source' && window.hWin.HEURIST4.util.isempty(this.options.source_ID)){

            if(this._$('input[type="radio"][name="link_field"]').length>0){
                $(this._$('input[type="radio"][name="link_field"]')[0]).attr('checked','checked').trigger('change');
            }

            if(window.hWin.HEURIST4.util.isempty(this.options.target_ID)){
                //add reverse link option
                let ele = $('<div style="line-height:2.5em;padding-left:20px"><input name="link_field" type="radio" id="cbsource_cb_0" '
                    + ' data-party="source" value="0"'
                    +' class="cb_addlink text ui-widget-content ui-corner-all"/>'                                     
                    +'<label style="font-style:italic;line-height: 1em;" for="cbsource_cb_0">Reverse links: Add links to the target record rather than the current selection<br><span style="width:1.5em;display:inline-block;"/>(where appropriate record pointer or relationship marker fields exist in the target record)</label><div>')
                .appendTo($('#source_field'));
                this._on(ele, {change:this._createInputElement});
                
            }
        }
        
        let ele = this._$('#source_field').find('input[type=radio]');
        if(ele.length==1){
            ele.trigger('click');
        }
        

        }//if rectype defined

    },  
    
    //
    // enable add link button
    //
    _enableActionButton: function (){
        
        let sel_field  = this._$('input[type="radio"][name="link_field"]:checked');
        
        let isEnabled = ((this.options.target_ID>0 || this.getFieldValue('target_record')>0) && 
                            sel_field.val()>0);
                            
        if(isEnabled && sel_field.attr('data-type')=='relmarker'){
            //in case relmarker check if reltype selected
            let isReverce = (sel_field.attr('data-party')=='target');
            let dtyID = sel_field.val()
            let termID = this.getFieldValue('rt_'+(isReverce?'target':'source')+'_sel_'+dtyID);        
            isEnabled = (termID>0);
        }                
                        
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('.btnDoAction'), !isEnabled );
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('.btnDoAction2'), !isEnabled );
        
    }, 
    
    //
    // record selector (for target mainly)
    //
    _createInputElement_RecordSelector: function(party, rt_constraints){
        
        if(!Array.isArray(rt_constraints)){
            if(window.hWin.HEURIST4.util.isempty(rt_constraints)){
                rt_constraints = [];
            }else{
                rt_constraints = rt_constraints.split(',');        
            }
        }
        
        this._$('#div_'+party+'1').hide();
        let $fieldset = this._$('#div_'+party+'2').empty();
        
        let dtFields = {};
        dtFields['dt_ID'] = 9999999;    
        dtFields['rst_PtrFilteredIDs'] = rt_constraints;    
        dtFields['rst_DisplayName'] = ''; //input_label
        dtFields['rst_RequirementType'] = 'optional';
        dtFields['rst_MaxValues'] = 1;
        dtFields['dty_Type'] = 'resource';
        
        let that = this;

        let ed_options = {
            recID: -1,
            dtID: 9999999,
            //rectypeID: rectypeID,
            values: '',// init_value
            readonly: false,

            showclear_button: false,
            suppress_prompts: true,
            show_header: false,
            dtFields:dtFields,
            
            change: function(){
                let rec_id = that.getFieldValue(party+'_record');
                if(party=='source'){
                    that.options.source_ID = rec_id;    
                }else{
                    that.options.target_ID = rec_id;    
                }
                
               
               
            }    
        };

        let ele = $("<div>").attr('id',party+'_record').appendTo($fieldset);
        ele.editing_input(ed_options);
        ele.find('input').css({'font-weight':'bold'});        
    },
  
    //
    // create input element to select target record
    //
    _createInputElement: function ( event ){ //input_id, input_label, init_value){

        this._$('#div_target1').hide();
    
        let $fieldset = this._$('#div_target2').empty();

        let dtID = $(event.target).val();//
        
        this._enableActionButton();

        if(window.hWin.HEURIST4.util.isempty(dtID)) return;

        
        let ptr_constraints = (dtID>0)?$Db.dty(dtID, 'dty_PtrTargetRectypeIDs'):'';
        
        let dtFields = {};
        dtFields['dt_ID'] = 9999999;    
        dtFields['rst_PtrFilteredIDs'] = ptr_constraints;
        dtFields['rst_DisplayName'] = '';
        dtFields['rst_RequirementType'] = 'optional';
        dtFields['rst_MaxValues'] = 1;
        dtFields['dty_Type'] = 'resource';
        
        let that = this;

        let ed_options = {
            recID: -1,
            dtID: 9999999,
            //rectypeID: rectypeID,
            values: '',// init_value
            readonly: false,

            showclear_button: false,
            suppress_prompts: true,
            show_header: false,
            dtFields:dtFields,
            
            change: function(){
                let rec_id = that.getFieldValue('target_record');
                that.getRecordValue(rec_id, 'target');
                that._enableActionButton();
            }    
        };

        let ele = $("<div>").attr('id','target_record').appendTo($fieldset)
        ele.editing_input(ed_options);
        ele.find('input').css({'font-weight':'bold'});
    },

    
    //
    // create input element for relation selecor
    //
    _createInputElement_Relation: function ( party, rectypeID, dtID ){ 

        if(window.hWin.HEURIST4.util.isempty(dtID)) return;
    
        let $field = this._$('#rt_'+party+'_sel_'+dtID).empty();

        let dt = $Db.dty(dtID);

        let vocab_id = dt['dty_JsonTermIDTree'];
        let trm_id = null;

        if(this.options.relationtype){
            trm_id = $Db.trm_InVocab(vocab_id, this.options.relationtype);

            if(!trm_id){
    
                trm_id = $Db.getTermByLabel(vocab_id, this.options.relationtype);

                if(!trm_id){
                    trm_id = $Db.getTermByCode(vocab_id, this.options.relationtype);
                }
            }else{
                trm_id = this.options.relationtype;
            }
        }

        if(!trm_id){
            trm_id = $Db.rst(rectypeID, dtID, 'rst_DefaultValue');
        }
        
       
        let dtFields = {};
        dtFields['rst_DisplayName'] = 'Relationship type:'; //input_label
        dtFields['rst_RequirementType'] = 'optional';
        dtFields['rst_MaxValues'] = 1;
        dtFields['rst_DisplayWidth'] = '25ex';
        dtFields['dty_Type'] = 'relationtype';
        dtFields['rst_PtrFilteredIDs'] = '';
        dtFields['rst_FilteredJsonTermIDTree'] = vocab_id;
        dtFields['rst_DefaultValue'] = trm_id;
        dtFields['dtID'] = dtID;
        
        let that = this;

        let ed_options = {
            recID: -1,
            dtID: dtID,

            values: '',// init_value
            readonly: false,

            showclear_button: false,
            showedit_button: true,
            suppress_prompts: true,
            useHtmlSelect:false, 
            //show_header: false,
            //detailtype: 'relationtype',  //overwrite detail type from db (for example freetext instead of memo)
            dtFields:dtFields,
            is_insert_mode: true,

            
            change: function(){
                that._enableActionButton();
            }                                                           
        };

        //$("<div>").attr('id','target_record')
                    //.css({'padding-left':'130px'})
        $field.editing_input(ed_options);
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

    getRecordValue: function (rec_id, party) {
        
        let request = {q:'ids:'+rec_id, w:'e',f:'detail'};  //w=e everything including temporary
        
        let that = this;
        
        window.hWin.HAPI4.RecordMgr.search(request, function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
                let resdata = new HRecordSet(response.data);
        
                //add SELECT and fill it with values
                let rec_titles = [];
            
                let record = resdata.getById(rec_id);
                let rec_title = window.hWin.HEURIST4.util.stripTags(resdata.fld(record, 'rec_Title'));
                if(!rec_title) rec_title = 'Record title is not defined yet';

                let recRecTypeID = resdata.fld(record, 'rec_RecTypeID');
                
                if(party=='source'){
                    that.sSourceName = rec_title;    
                    that.source_RecTypeID = recRecTypeID;
                }else{
                    that.sTargetName = rec_title;    
                    that.target_RecTypeID = recRecTypeID;
                }
                
                let rty_Name = window.hWin.HEURIST4.util.stripTags($Db.rty(recRecTypeID,'rty_Name'));

                rec_titles.push('<b>'+rty_Name+'</b>');
                $('#'+party+'_title').text(rec_title);
                $('#'+party+'_rectype').text(rty_Name);
                $('#'+party+'_rectype_img').css('background-image', 'url("'+top.HAPI4.iconBaseURL+recRecTypeID+'")');
                
                //find fields
                let oppositeRecTypeID = (party=='target')?that.source_RecTypeID:null; 
                
                if(!(that.options.relmarker_dty_ID>0))
                    that._fillSelectFieldTypes(party, recRecTypeID, oppositeRecTypeID);
                
                $('#div_'+party+'1').css('display','inline-block');
                if(party=='target'){
                    if(that.options.target_ID>0){
                        that.target_RecTypeID = recRecTypeID;
                        $('#target_title').show();    
                    }else{
                        $('#target_title').hide();    
                        $('#target_rectype_img').hide(); 
                        //DO NOT SHOW 
                        $('#target_rectype').hide(); //css({'margin-top':0,'margin-left':'5px'}); 
                        
                        $('#div_target1').css({'display':'block','padding-left':'120px'}); 
                        $('#div_target2 ').find('.link-div').css({'background':'none'}); //,'border':'none'
                        $('#div_target2').find('a').css({'font-weight':'bold','font-size':'1.05em'});
                    }
                }else{
                    if(that.options.source_ID>0){
                        that.source_RecTypeID = recRecTypeID;
                        that._fillSelectFieldTypes('source', that.source_RecTypeID);
                        if(that.options.target_ID>0){
                            that.getRecordValue(that.options.target_ID, 'target');
                        }
                    }
                }
    
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
            
            
        });
    }, 

    //
    //
    //
    doAction: function(){

     
        let RT_RELATION = window.hWin.HAPI4.sysinfo['dbconst']['RT_RELATION'], //1
            DT_TARGET_RESOURCE = window.hWin.HAPI4.sysinfo['dbconst']['DT_TARGET_RESOURCE'], //5
            DT_RELATION_TYPE = window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE'], //6
            DT_PRIMARY_RESOURCE = window.hWin.HAPI4.sysinfo['dbconst']['DT_PRIMARY_RESOURCE']; //7
        
        let idx, requests = [], targetIDs = [], sourceIDs=[], currentScope=[];
        
        let ele = this._$('input[type="radio"][name="link_field"]:checked');
        let dtyID = ele.val()
        let data_type = ele.attr('data-type');
        let isReverce = (ele.attr('data-party')=='target');

        if(!(this.options.source_ID>0)){
            
            let rty_ID = Number(this.source_RecTypeID);
                                
            let opt = this.selectRecordScope.find(":selected");
            let isSelection = ($(opt).attr('data-select')==1);

            if(isSelection){
                let sels = this._currentRecordsetSelIds;
                for (idx in sels){ //find all selected rectypes
                    if(idx>=0){  
                        let rec = this._currentRecordset.getById(sels[idx]);
                        let rt = Number(this._currentRecordset.fld(rec, 'rec_RecTypeID'));
                        if(rt==rty_ID){
                            currentScope.push(sels[idx]);  
                        }
                    }
                }
            }else{
                 currentScope = this._currentRecordset.getIdsByRectypeId(rty_ID);
            }
                
        }else{
            currentScope = [this.options.source_ID];
        }
        
        
        if(isReverce){
                        
            let kp = this.sSourceName;
            this.sSourceName = this.sTargetName;
            this.sTargetName = kp;
            
            targetIDs = currentScope;
            sourceIDs = [(this.options.target_ID>0) ?this.options.target_ID :this.getFieldValue('target_record')];
        }else{
            sourceIDs = currentScope;
            targetIDs = [(this.options.target_ID>0) ?this.options.target_ID :this.getFieldValue('target_record')];
        }
        
        let res = {};

        if(data_type=='resource'){
            
                for(idx in targetIDs){
                   if(idx>=0){ 
                        requests.push({a: 'add',
                            recIDs: sourceIDs,
                            dtyID:  dtyID,
                            val:    targetIDs[idx]});
                   }
                }
                
                res = {rec_ID: targetIDs[0], rec_Title:this.sTargetName, rec_RecTypeID:this.target_RecTypeID };
                
        }else{ //relmarker
                /*
                let rl_ele = $('#rec'+(isReverce?'target':'source')+'_sel_'+dtyID);
                let termID = rl_ele.val(),
                    sRelation = rl_ele.find('option:selected').text();
                */
                let termID = this.getFieldValue('rt_'+(isReverce?'target':'source')+'_sel_'+dtyID);
        
                for(idx in currentScope){
                
                    let details = {};
                    details['t:'+DT_PRIMARY_RESOURCE] = [ isReverce?sourceIDs[0]:sourceIDs[idx] ];
                    details['t:'+DT_RELATION_TYPE] = [ termID ];
                    details['t:'+DT_TARGET_RESOURCE] = [ isReverce?targetIDs[idx]:targetIDs[0] ];
                    
                    
                    if(window.hWin.HEURIST4.util.isempty(this.sSourceName)){
                       let record = this._currentRecordset.getById( isReverce?sourceIDs[0]:sourceIDs[idx] );
                       this.sSourceName =  this._currentRecordset.fld(record, 'rec_Title');
                       this.source_RecTypeID = this._currentRecordset.fld(record, 'rec_RecTypeID');
                    }
                    if(window.hWin.HEURIST4.util.isempty(this.sTargetName)){
                       let record = this._currentRecordset.getById( isReverce?targetIDs[idx]:targetIDs[0] );
                       this.sTargetName =  this._currentRecordset.fld(record, 'rec_Title');
                    }
                    
                    
                    requests.push({a: 'save',    //add new relationship record
                        ID:0, //new record
                        RecTypeID: RT_RELATION,
                        //RecTitle: 'Relationship ('+sSourceName+' '+sRelation+' '+sTargetName+')',
                        details: details });
                     
                }//for
                res = {
                    source:{rec_ID: sourceIDs[0], rec_Title:this.sSourceName, rec_RecTypeID:this.source_RecTypeID},
                    target:{rec_ID: targetIDs[0], rec_Title:this.sTargetName, rec_RecTypeID:this.target_RecTypeID},
                    //rec_ID: targetIDs[0], rec_Title:sTargetName, rec_RecTypeID:target_RecTypeID,
                    relation_recID:0, trm_ID:termID };
        }
        
        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());
        
        this.addLinkOrRelation(0, requests, res);                     
        
    },

    
    //
    // individual action
    //
    addLinkOrRelation: function (idx, requests, res){

        if(idx<requests.length){

            let request = requests[idx];
            
            let hWin = window.hWin;
            
            let that = this;
            
            function __callBack(response){
                    if(response.status == hWin.ResponseStatus.OK){
                        if(requests[idx].a=='s'){
                            res.relation_recID = response.data; //add rec id
                        }
                        idx = idx + 1;
                        that.addLinkOrRelation(idx, requests, res);
                    }else{
                        hWin.HEURIST4.msg.showMsgErr(response);
                    }
            }        
        
            if(request.a=='add'){  //add link - batch update - add new field
                window.hWin.HAPI4.RecordMgr.batch_details(request, __callBack);
            }else{ //add relationship - add new record
                window.hWin.HAPI4.RecordMgr.saveRecord(request, __callBack);
            }
        }else{
            window.hWin.HEURIST4.msg.sendCoverallToBack();
            if(requests.length>0){
                res.count = requests.length;
                window.hWin.HEURIST4.msg.showMsgFlash('Link created...', 3000);
                this._context_on_close = res;
                
                if(this._openRelationRecordEditor && res.count==1 && res.relation_recID>0){
                     window.hWin.HEURIST4.ui.openRecordEdit(res.relation_recID, null, 
                        {relmarker_field: this.options.relmarker_dty_ID, relmarker_is_inward: false});   
                }
                this._as_dialog.dialog('close');
               
            }
        }
    }
    
        
});

