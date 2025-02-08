/**
* Class to perform action on set of records in popup dialog
*
* @param action_type - name of action - used to access help, widget name and method on server side
* @returns {Object}
* @see  hclient/framecontent/record for widgets
* @see  migrated/search/actions
* @see  record_action_help_xxxx in localization.txt for description and help

IT USES
    window.hWin.HAPI4.currentRecordset
    window.hWin.HAPI4.currentRecordsetSelection


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

/*

1) record detail batch update
2) record type change

see    
_createInputElements - create custom input elements specific for particular action
_startAction - start the action

*/
function hRecordAction(_action_type, _scope_type, _field_type, _field_value) {
    const _className = "RecordAction",
    _version   = "0.4";

    let selectRecordScope, allSelectedRectypes;

    let action_type = _action_type,
        init_scope_type = _scope_type,
        init_field_type = _field_type,
        init_field_value = _field_value,
        //repositories = ['Nakala'], // list of repositories - RETRIEVED FROM SERVER SIDE
        _allow_empty_replace = false,
        _default_exceptions = [], // array of default exceptions for case conversions
        _check_field_repeat = false; // check if the field to be used is repeatable


    /*
    header that describes the action
    selector of records: all, selected, by record type
    widget to enter data
    request to server
    results
    given
    processed
    rejected (rights)
    error
    */
    function _init(){
        
        //fill header with description
        $('#div_header').html(window.hWin.HR('record_action_'+action_type));
        
        let btn_start_action = $('#btn-ok').button({label:window.hWin.HR('Go')});
        
        selectRecordScope = $('#sel_record_scope')
        .on('change',
            function(e){
                _onRecordScopeChange();
            }
        );
        btn_start_action.addClass('ui-state-disabled'); //.on('click',_startAction);        
        
        _fillSelectRecordScope();
        
        $('#btn-cancel').button({label:window.hWin.HR('Cancel')}).on('click', function(){window.close();});
    }

    //
    // Retrieve license types from Nakala
    //
    function _popuplateNakalaLicense(){

        let $sel_license = $('#sel_license');

        if($sel_license.attr('data-init') == 'Nakala' && $sel_license.find('option').length > 0){ // already has values
            return;
        }

        let request = {
            serviceType: 'nakala',
            service: 'nakala_get_metadata',
            type: 'licenses'
        };

        window.hWin.HEURIST4.msg.bringCoverallToFront($('body'), null, 'Retrieving available licenses...');

        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, (data) => {

            window.hWin.HEURIST4.msg.sendCoverallToBack();

            data = window.hWin.HEURIST4.util.isJSON(data);

            if(data.status && data.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(data);
                window.close();
                return;
            }

            if(data.length > 0){
                $.each(data, (idx, license) => {
                    window.hWin.HEURIST4.ui.addoption($sel_license[0], license, license);
                });

                $sel_license.attr('data-init', 'Nakala');
            }else{
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: 'An unknown error has occurred while attempting to retrieve the licenses for Nakala records.',
                    error_title: 'Unable to retrieve Nakala licenses',
                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                });
                window.close();
            }
        });
    }

    //
    // fill selector with scope options - all (currentRecordset), selected (currentRecordsetSelection), by record type 
    //
    function _fillSelectRecordScope(){

        selectRecordScope.empty();

        if(!window.hWin.HAPI4.currentRecordset){
            window.hWin.HAPI4.currentRecordset = new HRecordSet({count:"0",offset: 0,reccount: 1,records:[], rectypes:[]});
        }

        let opt, selScope = selectRecordScope.get(0);

        opt = new Option("please select the records to be affected …", "");
        selScope.appendChild(opt);

        let is_initscope_empty = window.hWin.HEURIST4.util.isempty(init_scope_type);
        let inititally_selected = '';
        
        if(init_scope_type=='all'){
            opt = new Option("All records", "All");
            selScope.appendChild(opt);
            inititally_selected = 'All';
        }else if(init_scope_type>0 && $Db.rty(init_scope_type,'rty_Plural')){
            opt = new Option($Db.rty(init_scope_type,'rty_Plural'), init_scope_type);
            selScope.appendChild(opt);
            inititally_selected = init_scope_type;
        }else{

            if(is_initscope_empty || init_scope_type=='current'){
                //add result count option default
                opt = new Option("Current results set (count="+ window.hWin.HAPI4.currentRecordset.length()+")", "Current");
                selScope.appendChild(opt);
                inititally_selected = 'Current';
            }
            //collected count option
            if((action_type=='rectype_change' || is_initscope_empty || init_scope_type=='collected') &&
                window.hWin.HAPI4?.currentRecordsetCollected?.length > 0)
            {
                opt = new Option("Collected results set (count=" + window.hWin.HAPI4.currentRecordsetCollected.length+")", "Collected");
                selScope.appendChild(opt);
                inititally_selected = 'Collected';
            }
            //selected count option
            if((action_type=='rectype_change' || is_initscope_empty || init_scope_type=='selected') &&
                window.hWin.HAPI4?.currentRecordsetSelection?.length > 0)
            {
                opt = new Option("Selected results set (count=" + window.hWin.HAPI4.currentRecordsetSelection.length+")", "Selected");
                selScope.appendChild(opt);
                inititally_selected = 'Selected';
            }

            if(is_initscope_empty){
                //find all types for result and add option for each with counts.
                let rectype_Ids = window.hWin.HAPI4.currentRecordset.getRectypes();
                rectype_Ids.forEach(rty => {
                        let opt = new Option('only: '+$Db.rty(rty,'rty_Plural'), rty);
                        selScope.appendChild(opt);
                });
            }
        }

        //$(selScope)
        selectRecordScope.val(inititally_selected);
        
        _onRecordScopeChange();
        
        if(action_type=='rectype_change'){
            $('#div_sel_rectype').show();
            _fillSelectRecordTypes();
        }else if(action_type=='reset_thumbs'){
            $('#cb_add_tags').parent().hide();
        }
    }

    //
    // scope selector listener
    //
    function _onRecordScopeChange() {
        
        let isdisabled = (selectRecordScope.val()=='');
        
        let ele = $('#btn-ok');
        ele.off('click');
        if(isdisabled){
            ele.addClass('ui-state-disabled');
        }else{
            ele.removeClass('ui-state-disabled');
            ele.on('click',_startAction);
        }
        switch(action_type) {
            case 'add_detail':
            case 'replace_detail':
            case 'delete_detail':
            case 'extract_pdf':
            case 'url_to_file':
            case 'local_to_repository':
            case 'case_conversion':
            case 'nl2br':
            case 'translation':
                $('#div_sel_fieldtype').show();
                _fillSelectFieldTypes();
                break;
            default:
                $('#div_sel_fieldtype').hide();
/*                
            case 'rectype_change':
                $('#div_sel_rectype').show();
                _fillSelectRecordTypes();
                break;
*/                
        }

    }

    //
    // record type selector for change record type action
    // 
    function _fillSelectRecordTypes() {
        let rtSelect = $('#sel_recordtype');
        rtSelect.empty();
        return window.hWin.HEURIST4.ui.createRectypeSelect( rtSelect.get(0), null, window.hWin.HR('select record type'), false );
    }
    //
    // fill all field type selectors
    //
    function _fillSelectFieldTypes() {

        let fieldSelect = $('#sel_fieldtype').get(0);
        if(init_scope_type>0){
            window.hWin.HEURIST4.ui.createSelector(fieldSelect, 
                {key:init_scope_type, title: $Db.dty(init_scope_type, 'dty_Name')});
                
        }else{

            let scope_type = selectRecordScope.val();
        
            let rtyIDs = [], dtys = {}, dtyNames = [],dtyNameToID = {},dtyNameToRty={};
            let rtys = {};

            //get record types
            if(scope_type=="All"){
                rtyIDs = null; //show all details
            }else if(scope_type=="Current"){
                rtyIDs = window.hWin.HAPI4.currentRecordset.getRectypes();
            }else if(scope_type=="Selected" || scope_type=="Collected"){
                rtyIDs = [];

                let rec_IDs = scope_type == 'Selected' ? window.hWin.HAPI4.currentRecordsetSelection : window.hWin.HAPI4.currentRecordsetCollected;

                //loop all selected records
                for(const recID of rec_IDs){

                    let rty_total_count = window.hWin.HAPI4.currentRecordset.getRectypes().length;
                    const record  = window.hWin.HAPI4.currentRecordset.getById(recID) ;
                    let rty = window.hWin.HAPI4.currentRecordset.fld(record, 'rec_RecTypeID');

                    if (!rtys[rty]){
                        rtys[rty] = 1;
                        rtyIDs.push(rty);
                        if(rtyIDs.length==rty_total_count) break;
                    }
                }

                allSelectedRectypes = rtyIDs;
                                                
            }else{
                rtyIDs = [scope_type];
            }

            let allowed = Object.keys($Db.baseFieldType);
            allowed.splice(allowed.indexOf("separator"),1);
            allowed.splice(allowed.indexOf("relmarker"),1);
           
            allowed.splice(allowed.indexOf("file"),1);
            
            if(action_type=='extract_pdf' || action_type=='nl2br'){
                allowed = ['blocktext'];    
            }else if(action_type=='url_to_file' || action_type=='local_to_repository'){
                allowed = ['file'];    
            }else if(action_type=='case_conversion' || action_type=='translation'){
                allowed = ['freetext','blocktext'];
            }

            window.hWin.HEURIST4.ui.createRectypeDetailSelect(fieldSelect, rtyIDs, allowed, null);
        }
        
        fieldSelect.onchange = _createInputElements;
        _createInputElements();
    }
    
    //
    // create editing_input element for selected field type
    // create custom input elements specific for particular action
    //
    function _createInputElements(){

        let $fieldset = $('#div_widget>fieldset');
        $fieldset.empty();

        if(action_type=='add_detail'){
            _createInputElement('fld-1', window.hWin.HR('Value to be added'));
        }else if(action_type=='replace_detail'){                              

            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'
                +'<label for="cb_replace_all">Replace all values</label></div>'
                +'<input id="cb_replace_all" name="replace_type" type="radio" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').appendTo($fieldset);

            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'
                +'<label for="cb_whole_value">Replace complete value</label></div>'
                +'<input id="cb_whole_value" name="replace_type" type="radio" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').appendTo($fieldset);

            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'
                +'<label for="cb_sub_string">Replace substring</label></div>'
                +'<input id="cb_sub_string" name="replace_type" type="radio" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px" checked="checked">'
                +'</div>').appendTo($fieldset);

            $('input[name="replace_type"]').on('change', () => {

                if ($('#cb_replace_all').is(':checked')){
                    $('#cb_add_value').parent().show();
                    $('#fld-1').hide();
                }else{
                    $('#cb_add_value').parent().hide();
                    $('#fld-1').show();    
                }
            });

            $('<div style="padding: 0.2em; width: 100%; display: none;" class="input">'
                +'<div class="header" style="padding-bottom: 10px;">'
                +'<label for="cb_add_value">Insert as new value,<br><span style="font-size: smaller;">if none exist</span></label></div>'
                +'<input id="cb_add_value" type="checkbox" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').appendTo($fieldset);
            
            _createInputElement('fld-1', window.hWin.HR('Value to find'));
            _createInputElement('fld-2', window.hWin.HR('Replace with'));
            
        }else if(action_type=='delete_detail'){

            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'
                +'<label for="cb_sub_string">Remove search string only</label></div>'
                +'<input id="cb_sub_string" name="delete_type" type="radio" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px" checked="checked">'
                +'</div>').appendTo($fieldset);
                
            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'
                +'<label for="cb_whole_value">Remove complete value</label></div>'
                +'<input id="cb_whole_value" name="delete_type" type="radio" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').appendTo($fieldset);

            _createInputElement('fld-1', window.hWin.HR('Remove value matching'));

            $('#delete_type').on('change', function(){ 
                if ($('#cb_delete_all').is(':checked')){
                    $('#fld-1').hide();
                }else{
                    $('#fld-1').show();    
                }
            });

        }else if(action_type=='url_to_file'){

                $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'  // style="padding-left: 16px;"
                +'<label>URL contains substring</label></div>'
                +'<input id="url_substring" class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'</div>').appendTo($fieldset);            
            
                $('<div style="padding: 0.2em; width: 100%;" class="input">'
                +'<div class="header">'  // style="padding-left: 16px;"
                +'<label for="cb_match_only">Match file name only</label></div>'
                +'<input id="cb_match_only" type="checkbox" checked class="text ui-widget-content ui-corner-all" style="margin:0 0 10px 24px">'
                +'<div class="heurist-helper1 style="padding: 0.2em 0px;">Looks for existing uploaded files based solely on name, and uses these rather than fetching a new copy. This will produce unwanted results if the names are re-used eg. in different folders.'
                +'</div></div>').appendTo($fieldset);            
            
        }else if(action_type=='local_to_repository'){ //upload local file to external repository (Nakala)

            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                + '<div class="header" style="padding-right: 16px;"><label for="sel_repository">Repository</label></div>'
                + '<select id="sel_repository" style="max-width:30em"><option value="">select a repository...</option></select>'
            + '</div>').appendTo($fieldset);

            $('<div style="padding: 0.2em; width: 100%;display: none;" class="input">'
                + '<div class="header" style="padding-right: 16px;"><label for="sel_repository">Use test server</label></div>'
                + '<input type="checkbox" id="ch_use_test_server" class="text ui-widget-content ui-corner-all" style="margin-bottom:10px">'
            + '</div>').appendTo($fieldset);

            $('<div style="padding: 0.2em; width: 100%;display: none;" class="input">'
                + '<div class="header" style="padding-right: 16px;"><label for="sel_license">License</label></div>'
                + '<select id="sel_license" style="max-width:30em" data-init="0"></select>'
            + '</div>').appendTo($fieldset);

            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                + '<div class="header" style="padding-right: 16px;"><label for="cb_del_local_file">Delete local file on success </label></div>'
                + '<input id="cb_del_local_file" type="checkbox" class="text ui-widget-content ui-corner-all" style="margin-bottom:10px">'
                + '<div class="heurist-helper1 style="padding: 0.2em 0px;">Delete locally stored file(s) after successfully uploading to repository</div>'
            + '</div>').appendTo($fieldset);

            
            if($fieldset.find('#sel_repository').length != 0){

                window.hWin.HAPI4.SystemMgr.repositoryAction({'a': 'list', 'include_test': 1}, function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        let repositories = window.hWin.HEURIST4.util.isJSON(response.data);
                        
                        //service_id, service_label, usr_ID, usr_Name

                        let $sel_repos = $fieldset.find('#sel_repository');
                        for (let i = 0; i < repositories.length; i++) {
                            let repo = repositories[i];
                            let repo_name = repo[1];
                            let usr_name = repo[3];
                            //usr_name = window.hWin.HAPI4.SystemMgr.getUserNameLocal(repo[2]);    
                            
                            window.hWin.HEURIST4.ui.addoption($sel_repos[0], 
                                    repo[0], 
                                    repo_name+' > '+usr_name);
                        }
                        $sel_repos.on('change', () => {
                            let repo = $sel_repos.val();
                            if(repo.indexOf('nakala')===0 || repo.indexOf('nakala')===1){
                                $('#sel_license').parent().show();
                                _popuplateNakalaLicense();
                                repo.indexOf('nakala')===0 ? $('#ch_use_test_server').parent().show() : $('#ch_use_test_server').parent().hide();
                            }
                        });
                        
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
            
            }
        }else if(action_type=='merge_delete_detail'){ //@todo
            _createInputElement('fld-1', window.hWin.HR('Value to remove'), init_field_value);
            _createInputElement('fld-2', window.hWin.HR('Or repalce it with'));
        }else if(action_type=='case_conversion'){

            if($('#case_convert_op').length == 0){ // add extra field
                $('<div style="padding: 0.2em; width: 100%;" class="input">'
                    + '<div class="header" style="padding-right: 16px;"><label>Conversion type:</label></div>'
                    + '<select id="case_convert_op" class="ui-widget-content ui-corner-all">'
                        + '<option value="1">Lowercase, capital at start of field, capitalise after fullstop followed by newline or space</option>'
                        + '<option value="2">Lowercase, capitalise start of each word</option>'
                        + '<option value="3">All lowercase</option>'
                        + '<option value="4">All capitals</option>'
                    + '</select>'
                + '</div>').insertAfter('#div_sel_fieldtype');
            }else{
                $('#case_convert_op').parent().show();
            }

            $('<h3 style="margin: 0px;">Exceptions</h3>'
            + `<div style="font-size: 12px;display: block; padding: 10px 0px;">${window.hWin.HR('case_conversion_add')}</div>`
            + '<div style="display: block; padding: 5px 0px;"> OR '
                + '<input id="uploadWidget" type="file" style="display:none;"><button id="uploadFile">Upload file</button> encoding: '
                + '<select id="except_encode" class="ui-widget-content ui-corner-all"></select>'
            + '</div>'
            + '<div style="display: inline-block;padding: 5px 20px 5px 50px;">'
                + '<div style="display: block;"><strong>Configurable</strong></div>'
                + '<textarea id="except_user" rows="25" cols="40"></textarea>'
            + '</div>'
            + '<div style="display: inline-block;padding: 5px 50px 5px 20px;">'
                + '<div style="display: block;"><strong>Pre-defined</strong> <span style="font-size: 10px">(may be temporarily edited)</span></div>'
                + '<textarea id="except_default" rows="25" cols="40"></textarea>'
            + '</div>').appendTo($fieldset);

            window.hWin.HEURIST4.ui.initHSelect($('#case_convert_op')[0], true);
            window.hWin.HEURIST4.ui.createEncodingSelect($('#except_encode'));

            let $widget_upload = $('#uploadWidget').hide();
            let $btn_upload = $('#uploadFile').button().on('click', function(e){
                $widget_upload.trigger('click'); // trigger file upload
            });
            $widget_upload.fileupload({
                url: window.hWin.HAPI4.baseURL +  'hserv/controller/fileUpload.php',
                formData: [ {name:'db', value: window.hWin.HAPI4.database}, 
                            {name:'entity', value:'temp'}, //to place file into scratch folder
                            {name:'max_file_size', value:1024*1024}], //'1024*1024'
                autoUpload: true,
                sequentialUploads:true,
                dataType: 'json',
                done: function (e, response) {
                    response = response.result;
                    if(response.status==window.hWin.ResponseStatus.OK){
                        let data = response.data;
                        $.each(data.files, function (index, file) {
                            if(file.error){
                                $('#except_user').val(file.error);
                            }else{
                                let url_get = file.deleteUrl.replace('fileUpload.php','fileGet.php')
                                    +'&encoding='+$('#except_encode').val()+'&db='+window.hWin.HAPI4.database;
                                
                                $('#except_user').load(url_get, null);
                            }
                        });
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr({message: response.message, error_title: 'File upload error', status: response.status});
                    }
                     
                    let inpt = this;
                    $btn_upload.off('click');
                    $btn_upload.on({click: function(){
                        $(inpt).trigger('click');
                    }});                
                }
            });

            if(_default_exceptions.length > 0){
                $('#except_default').val(_default_exceptions.join('\n'));
            }

            $('#div_widget').css('padding-left', '0px');
            
        }else if(action_type=='translation'){
            
            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                + '<div class="header" style="padding-right: 16px;"><label for="sel_language">'
                + window.hWin.HR('Target language')+'</label></div>'
                + '<select id="sel_language" style="max-width:30em" data-init="0"></select>'
            + '</div>').appendTo($fieldset);

            $('<div style="padding: 0.2em; width: 100%;" class="input">'
                + '<div class="header" style="padding-right: 16px;"><span>Existing translations: </span></div>'
                + '<label><input id="cb_translation_asis" type="radio" name="tr_act" checked class="text ui-widget-content ui-corner-all" style="margin-bottom:10px">as is</label>&nbsp;&nbsp;&nbsp;'
                + '<label><input id="cb_translation_replace" type="radio" name="tr_act" class="text ui-widget-content ui-corner-all" style="margin-bottom:10px">Replace</label>&nbsp;&nbsp;&nbsp;'
                + '<label><input id="cb_translation_delete" type="radio" name="tr_act" class="text ui-widget-content ui-corner-all" style="margin-bottom:10px">Delete</label>'
            + '</div>').appendTo($fieldset);
          
            window.hWin.HEURIST4.ui.createLanguageSelect($fieldset.find('#sel_language'), null, null, true);

            _check_field_repeat = true;
        }

    }

    //
    // 
    //
    function _createInputElement(input_id, input_label, init_value){

        let $fieldset = $('#div_widget>fieldset');

        let dtID = $('#sel_fieldtype').val();//
        
        let rectypeID;

        if(window.hWin.HEURIST4.util.isempty(dtID)) return;

        let scope_type = selectRecordScope.val();
        if(Number(scope_type)>0){
            rectypeID = Number(scope_type)
        }else{
            let i, rtyIDs
            if(scope_type=="Current"){
                rtyIDs = window.hWin.HAPI4.currentRecordset.getRectypes();
            }else{
                rtyIDs = allSelectedRectypes;
            }
            //find first rectype with specified dtID
            for (i in rtyIDs){
                if($Db.rst(rtyIDs[i], dtID)){
                    rectypeID = rtyIDs[i];
                    break;
                }
            }
        }

        if(window.hWin.HEURIST4.util.isempty(rectypeID)) return;

   
        let field_type = $Db.dty(dtID, 'dty_Type');
        if(field_type=='geo'){
            
            $('#cb_remove_all').prop('checked',true).addClass('ui-state-disabled');;
            $('#cb_replace_all').prop('checked',true).addClass('ui-state-disabled');;
            $('#fld-1').hide();
           
           if(action_type=='delete_detail') return;
        }else{
            $('#cb_remove_all').removeClass('ui-state-disabled');;
            $('#cb_replace_all').removeClass('ui-state-disabled');;
            
        }
        
        if(field_type=='freetext' || field_type=='blocktext'){
            $('#cb_sub_string').parent().show();
        }else{
            $('#cb_sub_string').parent().hide();
        }
        

        //window.hWin.HEURIST4.util.cloneObj(
        let dtFields = $Db.rst(rectypeID, dtID);

        dtFields['rst_DisplayName'] = input_label;
        dtFields['rst_RequirementType'] = 'optional';
        dtFields['rst_MaxValues'] = 1;
        dtFields['rst_DisplayWidth'] = 50; 
        dtFields['dty_Type'] = $Db.dty(dtID, 'dty_Type');
        dtFields['rst_PtrFilteredIDs'] = $Db.dty(dtID, 'dty_PtrTargetRectypeIDs')
        dtFields['rst_FilteredJsonTermIDTree'] = $Db.dty(dtID, 'dty_JsonTermIDTree');
        dtFields['dtID'] = dtID;
        
        if($Db.dty(dtID, 'dty_Type') == 'blocktext'){
            dtFields['rst_DisplayWidth'] = 80;
        }

        // Allow DB Admins to modify readonly fields
        let update_maymodify = dtFields['rst_MayModify'] == 'locked' && window.hWin.HAPI4.is_admin();
        dtFields['rst_MayModify'] = (update_maymodify) ? 'open' : dtFields['rst_MayModify'];
        
        if(window.hWin.HEURIST4.util.isnull(init_value)) init_value = '';

        let ed_options = {
            recID: -1,
            dtID: dtID,
            values: init_value,
            readonly: false,

            showclear_button: false,
            dtFields:dtFields,

            force_displayheight: (field_type=='blocktext') ? 10 : null
        };

        let ele = $("<div>").attr('id',input_id).appendTo($fieldset);
        ele.editing_input(ed_options);

        // special case for selects, menuWidget needs to be moved down closer to the widget element
        if(ele.find('select').length > 0){

            let id = ele.find('select').attr('id');
            let widget_ele, menu_parent;

			// check that the select is supposed to be a hSelect/selectmenu
            if(ele.find('select').hSelect('instance') != undefined){ 

                const selObj = ele.find('select');
                widget_ele = selObj.hSelect('widget');
                menu_parent = selObj.hSelect('menuWidget').parent();
            }else if($('#'+id+'-button').length > 0){ // widget exists in current document

				if(parent.document && $('#'+id+'-menu', parent.document).length > 0){ // check if current menuWidget can be accessed

					widget_ele = $('#'+id+'-button');
					menu_parent = $('#'+id+'-menu', parent.document).parent();
				}else{

					$('#'+id+'-button').remove();
					
					const selObj = window.hWin.HEURIST4.ui.initHSelect(ele.find('select')[0], false);

					widget_ele = selObj.hSelect('widget');
					menu_parent = selObj.hSelect('menuWidget').parent();
				}
            }

            if(widget_ele && menu_parent){
				widget_ele.on("click", function(e){
                    menu_parent.css('top', widget_ele.offset().top + 54);
                });

                widget_ele.css({'font-size': '1em'}); //'width': 'auto', 'max-width': '30em'
            }
        }
    }

    //
    //
    //
    function getFieldValue(input_id) {
        let sel = $('#'+input_id).editing_input('getValues');
        if(sel && sel.length>0){
            return sel[0];
        }else{
            return null;
        }
    }

    // 
    //  Main action 
    //
    function _startAction(){
        
        if(window.hWin.HEURIST4.util.isempty(selectRecordScope.val())){
            alert('Select records scope to be affected');
            return;
        }

        if ($('#div_result').is(':visible')){
            $('#div_result').hide();
            $('#div_parameters').show();
            $('#btn-ok').button('option','label',window.hWin.HR('Go'));
            //to reseet all selectors 
            selectRecordScope.val('').trigger('change');
            return;
        }

        let request = { tag: $('#cb_add_tags').is(':checked')?1:0 };

        if(action_type=='reset_thumbs'){
           request['a'] = action_type; 
        }else
        if(action_type!='rectype_change'){

            let dtyID = $('#sel_fieldtype').val();
            if(window.hWin.HEURIST4.util.isempty(dtyID) && action_type!='extract_pdf') {
                alert('Field is not defined');
                return;
            }

            request['dtyID'] = dtyID;

            if(action_type=='add_detail'){
                request['a'] = 'add';
                request['val'] = getFieldValue('fld-1'); 
                if(window.hWin.HEURIST4.util.isempty(request['val'])){
                    alert('Define value to add');
                    return;
                }

            }else if(action_type=='replace_detail'){

                request['a'] = 'replace';

                if(!$('#cb_replace_all').is(':checked')){
                    request['sVal'] = getFieldValue('fld-1');
                    if(window.hWin.HEURIST4.util.isempty(request['sVal'])){
                        alert('Define value to search');
                        return;
                    }

                    $('#cb_sub_string').is(':checked') ? request['substr'] = 1 : request['wholeval'] = 1;
                }else{
                    request['insert_new_values'] = $('#cb_add_value').is(':checked') ? 1 : 0;
                }
                request['rVal'] = getFieldValue('fld-2');
                if(!_allow_empty_replace && window.hWin.HEURIST4.util.isempty(request['rVal'])){

                    let msg_part = request['substr'] == 1 ? '(only the search string is deleted)' : '(the whole value is deleted)';
                    let msg = 'You have not defined a replacement value<br><br>'
                            + `Click "${window.hWin.HR('OK')}" to delete the search string ${msg_part}<br>`
                            + `Click "${window.hWin.HR('Cancel')}" if you want to replace the search string with a new string`;

                    window.hWin.HEURIST4.msg.showMsgDlg(msg, 
                        () => {
                            _allow_empty_replace = true;
                            _startAction();
                            return;
                        },
                        {title: window.hWin.HR('Empty replace value'), yes: window.hWin.HR('OK'), no: window.hWin.HR('Cancel')}, 
                        {default_palette_class: 'ui-heurist-explore'}
                    );

                    return;
                }else if(_allow_empty_replace){
                    request['replace_empty'] = 1;
                    _allow_empty_replace = false;
                }
            
            }else if(action_type=='url_to_file'){

                request['a'] = 'url_to_file';

                if($('#cb_match_only').is(':checked')){
                    request['match_only'] = 1;
                }
                let url_substring = $('#url_substring').val();
                if(!window.hWin.HEURIST4.util.isempty(url_substring)){
                    request['url_substring'] = url_substring;
                }
                

            }else if(action_type=='local_to_repository'){

                request['a'] = 'local_to_repository';

                request['repository'] = $('#sel_repository').val();

                if($('#cb_del_local_file').is(':checked')){
                    request['delete_file'] = 1;
                }

                if(request['repository'].indexOf('nakala')===0 || request['repository'].indexOf('nakala')===1){
                    request['license'] = $('#sel_license').val();
                    if(window.hWin.HEURIST4.util.isempty(request['license'])){
                        window.hWin.HEURIST4.msg.showMsgFlash('Please select a license', 3000);
                        return;
                    }
                    request['use_test_url'] = $('#ch_use_test_server').is(':checked') || request['repository'].indexOf('nakala')===1 ?
                                                1 : 0;
                }

            }else if(action_type=='delete_detail'){

                request['a'] = 'delete';
                if(!$('#cb_remove_all').is(':checked')){
                    request['sVal'] = getFieldValue('fld-1');
                    if(window.hWin.HEURIST4.util.isempty(request['sVal'])){
                        alert('Define value to delete');
                        return;
                    }

                    $('#cb_sub_string').is(':checked') ? request['substr'] = 1 : request['wholeval'] = 1;
                }
            }else if(action_type=='extract_pdf' || action_type=='nl2br'){
                
                request['a'] = action_type;
            }else if(action_type=='case_conversion'){

                request['a'] = action_type;

                request['op'] = $('#case_convert_op').val();

                let except = $('#except_user').val();
                except = except.split('\n').join('|');
                except += $('#except_default').val().split('\n').join('|');

                request['except'] = except;
                
            }else if(action_type=='translation'){

                request['a'] = action_type;
            
                request['lang'] = $('#sel_language').val(); 
                
                if($('#cb_translation_delete').is(':checked')){
                        request['delete'] = 1;
                }else 
                if($('#cb_translation_replace').is(':checked')){
                        request['replace'] = 1;
                }
                
            }

            if(_check_field_repeat && _check_field_repeatability()){
                return;
            }

        }

        let scope_type = selectRecordScope.val();
        let scope;

        if(scope_type=="Selected" || scope_type=="Collected"){
            scope = scope_type == 'Selected' ? window.hWin.HAPI4.currentRecordsetSelection : window.hWin.HAPI4.currentRecordsetCollected;
        }else{
            scope = window.hWin.HAPI4.currentRecordset.getIds();
            if(scope_type!="Current"){
                request['rtyID'] = scope_type;
            }
        }
        request['recIDs'] = scope.join(',');

        if(action_type=='rectype_change'){
            
            let rtyID = $('#sel_recordtype').val();
            if(!(rtyID>0)){
                alert('Select new record type');
                return;
            }
            
            if(request['rtyID']==rtyID){
                alert('Selected and new record types are the same');
                return;
            }
            
            request['a'] = 'rectype_change';
            request['rtyID_new'] = rtyID;
          
            window.hWin.HEURIST4.msg.showMsgDlg(
                'You are about to convert '
                + (request['rtyID']>0 ?('"'+$Db.rty(request['rtyID'],'rty_Name')+'"'):scope.length)
                +' records from their original record (entity) type into "'
                + $Db.rty(rtyID, 'rty_Name') 
                + '" records.  This can result in invalid data for these records.<br><br>Are you sure?',
                function(){_startAction_continue(request);},
                 {title:'Warning',yes:'Proceed',no:'Cancel'});
            
        }else{
            _startAction_continue(request)
        }
    }

    /*
    * recIDs - list of records IDS to be processed
    * rtyID - optional filter by record type
    * dtyID  - detail field to be added
    * for add: val, geo or ulfID
    * for replace: sVal - search value, rVal - replace value
    * for delete:  sVal - search value
    * for rectype change rtyID_new - new record type
    * tag 0|1  - add system tag to mark processed records
    */
     function _startAction_continue(request)
     {   

        // show hourglass/wait icon
        $('body > div:not(.loading)').hide();
        $('.loading').show();
        
       

        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){

            $('body > div:not(.loading)').show();
            $('body > #ui-datepicker-div').hide();
            $('.loading').hide();
            let  success = (response.status == window.hWin.ResponseStatus.OK);
            if(success){
                $('#div_parameters').hide();
                
                /*
                $('select').each(function(idx, item){
                   if($(item).hSelect('instance')!==undefined) $(item).hSelect('destroy'); //destroy all hSelects 
                });
                */
                $('.ui-selectmenu-menu').remove();

                $('#div_result').empty();

                response = response['data'];
 
                /*
                *       passed - count of given rec ids
                *       noaccess - no rights to edit
                *       processed - success
                _tag   _tag_error
                *       undefined - value not found (no assosiated pdf files)
                *       limited - skipped
                *       errors     - sql error on search or updata
                errors_list
                */
                let sResult = '';
                for(let key in response){
                    if(key && key.indexOf('_')<0 && response[key]>0){
                        //main report entry
                        const lbl_key = 'record_action_'+key;
                        let lbl = window.hWin.HR(lbl_key);
                        if(lbl==lbl_key){ //not translated
                            //not found - try to find specified for particular action
                            lbl = window.hWin.HR(lbl_key+'_'+action_type);
                        }
                        let tag_link = '';
                        if(response[key+'_tag']){
                            tag_link = '<span><a href="'+
                            encodeURI(window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                                +'&q=tag:"'+response[key+'_tag']+'"')+
                            '&nometadatadisplay=true" target="_blank">view</a></span>';
                            
                        }else if(response[key+'_tag_error']){
                            tag_link = '<span>'+response[key+'_tag_error']['message']+'</span>';
                            
                        }else if(key=="processed"){
                            
                            if(action_type!='reset_thumbs'){
                                tag_link = '<span><a href="'+
                                encodeURI(window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                                    +'&q=sortby:-m after:"5 minutes ago"')+
                                '&nometadatadisplay=true" target="_blank">view recent changes</a></span>';
                            }
                            
                        }else if(key=='fails' && response['fails_list'] && response['fails_list'].length>0){

                            tag_link = '<span style="background-color:#ffcccc"><a href="'+
                            encodeURI(window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                                +'&q=ids:'+response['fails_list'].join(','))+
                            '&nometadatadisplay=true" target="_blank">view</a></span>';
                        }else if(key == 'limited' && action_type == 'add_detail' && response[key] > 0){
                            tag_link = `<span style="display: block; font-size: 0.9em; padding: 5px 5px;">
                                            For single value fields which were skipped because they already have a value,<br>
                                            use "Recode > Replace field value" to replace all, or selected, existing values with the new value.
                                        </span>`;
                        }
                        
                        sResult = sResult + '<div style="padding:4px"><span>'+lbl+'</span><span>&nbsp;&nbsp;'
                        +response[key]+'</span>'
                        +tag_link+'</div>';
                        
                        if(key=='errors' && response['errors_list']){
                            let recids = Object.keys(response['errors_list']);
                            if(recids && recids.length>0){
                                sResult += '<div style="max-height:300;overflow-y:auto;background-color:#ffcccc">';
                                for(let key2 in response['errors_list']){
                                    let text = response['errors_list'][key2];
                                    if(Array.isArray(text)){
                                        text = text.join('<br>');
                                    }
                                    sResult += (key2+': '+ text + '<br>');   
                                }
                                sResult += '</div>';   
                            }
                        }
                        
                        
                        
                    }
                }

                $('#div_result').html(sResult);
                $('#div_result').css({padding:'10px'}).show();
                $('#btn-ok').button('option','label',window.hWin.HR('New Action'));
                $('#btn-cancel').button('option','label',window.hWin.HR('Close'));

            }else{
                $('#div_result').hide();
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });

    }

    /**
     * Check if current record type + base field is repeatable; currently for bulk translating
     * 
     * @returns {bool} - true on success, false on failure
     */
    function _check_field_repeatability(){

        let rty_ID = selectRecordScope.val();
        if(!window.hWin.HEURIST4.util.isNumber(rty_ID)){ // multiple rectypes
            return false;
        }

        let dty_ID = $('#sel_fieldtype').val();
        if($Db.rst(rty_ID, dty_ID, 'rst_MaxValues') != 1){
            return false;
        }

        // Warn about repeating fields
        let $dlg = null;
        let msg = `To avoid issues with editing the affected records in the future, we first recommend making the field "${$Db.rst(rty_ID, dty_ID, 'rst_DisplayName')}" repeatable.<br><br>Would you like to make the field repeatable?`;

        let btns = {};

        btns[window.hWin.HR('Yes')] = function(){

            window.hWin.HEURIST4.msg.bringCoverallToFront($('body'), null, 'Updating field definition...');

            let fields = {
                'rst_DetailTypeID': dty_ID,
                'rst_RecTypeID': rty_ID,
                'rst_MaxValues': 0
            };

            let request = {
                a: 'save',
                entity: 'defRecStructure',
                fields: fields,
                request_id: window.hWin.HEURIST4.util.random()
            };

            window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){

                window.hWin.HEURIST4.msg.sendCoverallToBack();
                $dlg.dialog('close');

                if(response.status != window.hWin.ResponseStatus.OK){
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    return;
                }

                window.hWin.HAPI4.EntityMgr.refreshEntityData('rst', _startAction);
            });
        };

        btns[window.hWin.HR('No, and continue with recode')] = function(){
            $dlg.dialog('close');
            _check_field_repeat = false;
            _startAction();
        };

        btns[window.hWin.HR('Cancel')] = function(){
            $dlg.dialog('close');
        };

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, {title: 'Field repeatability', yes: window.hWin.HR('Yes'), no: window.hWin.HR('No, and continue with recode'), cancel: window.hWin.HR('Cancel')}, {default_palette_class: 'ui-heurist-design'});

        return true;
    }

    //public members
    let that = {
        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

    }

    
    _init();
    return that;  //returns object
}
