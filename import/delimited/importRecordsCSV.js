/**
* Class to import records from CSV
* 
* @param _imp_ID - id of import session
* @returns {Object}
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

function hImportRecordsCSV(_imp_ID, _max_upload_size, _format) {
    const _className = "ImportRecordsCSV",
    _version   = "0.4";

    let imp_ID,   //import session
    imp_session,  //json with session parameters
    
    currentSeqIndex = -1,  
    
    mode_display_separate = false,
    
    UTMzone = 0,

    uniq_fieldnames = [],
    
    currentStep, 
    currentId,  //currect record id in import tabel to PREVIEW data
    upload_file_name,  //file on server with original uploaded data
    encoded_file_name_id, //file on server with UTF8 encoded data
    
    exp_level = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2), //beginner default
    
    session_selector;
    
    
    function _init(_imp_ID, _max_upload_size, format){
    
        imp_ID = _imp_ID;
        
        let is_h6style = window.hWin.HAPI4.sysinfo['layout']=='H6Default';
        
        if(format=='kml'){
            $('.format-csv').hide();
            $('#lblUploadFile').text('Upload new file (KML)');
            
        }else{
            $('.format-csv').show();
        }
        
        
        //init STEP 1  - upload
        
        //buttons
        $('#btnUploadData')
                    .css({'width':'120px'})
                    .button({label: window.hWin.HR('Upload Data'), iconPosition:'end', icon: "ui-icon-circle-arrow-e"})
                    .on('click', _uploadData);

       $('#btnClearAllSessions').on('click', _doClearSession);
        
        //upload file to server and store intemp file
        let uploadData = null;
        let pbar_div = $('#progressbar_div');
        let pbar = $('#progressbar');
        let progressLabel = pbar.find('.progress-label').text('');
        pbar.progressbar({value:0});
                
        $('#progress_stop').button().on({click: function() {
                if(uploadData && uploadData.abort) uploadData.abort();
        }});
                
        let uploadWidget = $('#uploadFile');
        
        let btnUploadFile = $('#btnUploadFile')
                    .css({'width':'120px'})
                    .button({label: window.hWin.HR('Upload File'), iconPosition:'end', icon: "ui-icon-circle-arrow-n"})
                    .on('click', function(e) {
                            if( window.hWin.HAPI4.is_admin() ){
                                uploadWidget.trigger('click');    
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr({
                                    status:window.hWin.ResponseStatus.REQUEST_DENIED,
                                    message:'Administrator permissions are required'
                                });
                            }
                        });
                
                
                uploadWidget.fileupload({
        url: window.hWin.HAPI4.baseURL +  'hserv/controller/fileUpload.php', 
        formData: [ {name:'db', value: window.hWin.HAPI4.database}, //{name:'DBGSESSID', value:'424533833945300001;d=1,p=0,c=0'},
                    {name:'max_file_size', value: _max_upload_size},
                    {name:'entity', value:'temp'}, //just place file into scratch folder
                    {name:'autodect', value:1}], //try to detect line and field separator 
        //acceptFileTypes: /(\.|\/)(gif|jpe?g|png)$/i,
        autoUpload: true,
        sequentialUploads:false,
        dataType: 'json',
        //dropZone: $input_img,
        add: function (e, data) {  
        
            uploadData = data;//keep this object to use conviniece methods (abort for instance)
            data.submit(); 

        },
        //send: function (e, data) {},
        //start: function(e, data){},
        //change: function(){},
        error: function (jqXHR, textStatus, errorThrown) {
            //!!! $('#upload_form_div').show();
            _showStep(1);
            pbar_div.hide();
            if(textStatus!='abort'){

                let msg = textStatus+' '+errorThrown;
                if(textStatus == 'error'){
                    msg = 'An unknown error occurred while attempting to upload your CSV file.<br>This may be due to an unstable or slow internet connection.<br><br>';
                    msg += `Server status code ${jqXHR.status}: <strong>${errorThrown}</strong><br>`
                        + '<a href="https://en.wikipedia.org/wiki/List_of_HTTP_status_codes" target="_blank" rel="noopener">List of HTTP Status Codes</a>';
                }

                window.hWin.HEURIST4.msg.showMsgErr({message: msg, error_title: 'File upload error: Server HTTP Status error', status: window.hWin.ResponseStatus.UNKNOWN_ERROR});
            }
        },
        fail: function(e, data){

            _showStep(1);
            pbar_div.hide();
            
            if(!window.hWin.HEURIST4.util.isnull(uploadData) && uploadData.message){ // was aborted by user
                window.hWin.HEURIST4.msg.showMsgFlash(uploadData.message, 3000);
            }else if( data.message ) {
                window.hWin.HEURIST4.msg.showMsgErr( data );
            }else {
                
                let msg = 'An unknown error occurred while attempting to upload your file.'
                
                if(data._response && data._response.jqXHR) {
                    if(data._response.jqXHR.responseJSON){
                        msg = data._response.jqXHR.responseJSON;    
                    }else if(data._response.jqXHR.responseText){
                        msg = data._response.jqXHR.responseText;    
                        let k = msg.indexOf('<p class="heurist-message">');
                        if(k>0){
                            msg = msg.substring(k);   
                        }
                    }
                }
                
                window.hWin.HEURIST4.msg.showMsgErr({message: msg, error_title: 'File upload failed', status: window.hWin.ResponseStatus.UNKNOWN_ERROR});
            }

            uploadData = null;
        },
        done: function (e, response) {

                //!!! $('#upload_form_div').show();                
                pbar_div.hide();       //hide progress bar
                response = response.result;
                if(response.status==window.hWin.ResponseStatus.OK){  //after upload
                    let data = response.data;
                    $.each(data.files, function (index, file) {
                        if(file.error){
                            window.hWin.HEURIST4.msg.showMsgErr({message: file.error, error_title: 'File upload error'});
                        }else{
                            /*
                            $('#divParsePreview').load(file.url, function(){
                            //$.ajax({url:file.deleteUrl, type:'DELETE'});
                            }); */
                            _showStep(2);
                            
                            upload_file_name = file.name;
                            
                            if(file.isKML){
                                $('#divStep2').find('fieldset').hide();
                                _doParse(1);
                            }else{
                            
                                window.hWin.HEURIST4.util.setDisabled($('#csv_encoding'), false);
                                
                                if(file.csv_params){
                                    $('#csv_delimiter').val(file.csv_params['csv_delimiter']);
                                    $('#csv_linebreak').val(file.csv_params['csv_linebreak']);
                                    $('#csv_enclosure').val(2);
                                }
                            }
                            
                            window.hWin.HEURIST4.util.setDisabled( $('#btnParseStep2'), true );
                            $('#divFieldRolesHeader').hide();
                            $('#divParsePreview').empty();
                            $('#divFieldRoles > .tbfields > tbody').empty();
                            $('#divFieldRoles').hide();
                            $('#lblParseStep2').hide();
                            
                            //_doParse(1);
                        }
                    });
                }else{
                    //$('#divFieldRolesHeader').show();
                    _showStep(1);
                    window.hWin.HEURIST4.msg.showMsgErr({message: response.message, error_title: 'File upload error', status: response.status});
                }
                
                //need to reassign  event handler since widget creates temp input
                let inpt = this;
                btnUploadFile.off('click');
                btnUploadFile.on({click: function(){
                            $(inpt).trigger('click');
                }});                
           
        },//done                    
        progressall: function (e, data) { // to implement
                    let progress = parseInt(data.loaded / data.total * 100, 10);
                    progressLabel = pbar.find('.progress-label').text(
                                    Math.ceil(data.loaded/1024)+'Kb / '+Math.ceil(data.total/1024)) + 'Kb ';
                    pbar.progressbar({value: progress});
                    if (data.total>_max_upload_size && uploadData) {
                            uploadData.abort();
                            window.hWin.HEURIST4.msg.showMsgErr({
                                message:'Sorry, this file exceeds the upload '
                                       +` size limit set for this server (${Math.round(_max_upload_size/1024/1024)} MBytes). `
                                       +'Please reduce the file size, or ask your system administrator to increase the upload limit.',
                                error_title: 'Uploaded file is too large',
                                status: window.hWin.ResponseStatus.ACTION_BLOCKED
                            });
                    }else if(!pbar_div.is(':visible')){
                        //!!! $('#upload_form_div').hide();
                        _showStep(0);
                        pbar_div.show();
                    }
                }                            
                
                            });                        

                        
        //init STEP 2  - preview, select field roles and parse
        $('#btnBackToStart2')
                    .css({'width':'160px'})
                    .button({label: window.hWin.HR('Back to start'), icon: "ui-icon-circle-arrow-w"})
                    .on('click', function(e) {
                            //@todo - remove temp file
                            _showStep(1);
                            session_selector.val('').hSelect("refresh"); 
                        });
                        
        $('#btnParseStep1')
                    .css({'width':'160px'})
                    .button({label: window.hWin.HR('Analyse data'), iconPosition:'end', icon: "ui-icon-circle-arrow-e"})
                    .on('click', function(e) {
                            _doParse(1);
                        });

        $('#btnParseStep2')
                    .css({'width':'180px'})
                    .button({label: window.hWin.HR('Continue'), iconPosition:'end', icon: "ui-icon-circle-arrow-e"})
                    .on('click', function(e) {
                           _doParse(2); 
                        });
        $('#lblParseStep2').hide();
        window.hWin.HEURIST4.util.setDisabled( $('#btnParseStep2'), true );
        $('#divFieldRolesHeader').hide();
        
        //get list of sessions and fill selector                        
        window.hWin.HEURIST4.ui.createEntitySelector( $('#selImportId').get(0), 
                    {entity:'SysImportFiles', filter_group:'0,'+window.hWin.HAPI4.currentUser['ugr_ID']}, 
                    window.hWin.HR('select uploaded file...'),
                    function(sel){

                        window.hWin.HEURIST4.util.setDisabled($('#btnClearAllSessions'), $(sel).find('option').length<2 );
                        
                        session_selector = window.hWin.HEURIST4.ui.initHSelect(sel, false);
                        session_selector.on('change', function(event){
                           if($(event.target).val()>0){
                                imp_ID = $(event.target).val();
                                _loadSession();    
                           }
                        });
                        $('#selImportId-button').css('vertical-align','4px'); 
                    });
                
        
                        
        //init STEP 3 - matching and import
        $('#btnBackToStart')
                    .css({'width':'160px'})
                    .button({label: window.hWin.HR('Back to start'), icon: "ui-icon-circle-arrow-w"})
                    .on('click', function(e) {
                            _showStep(1);
                        });
        $('#btnDownloadFile')
                    .css({'width':'180px'})
                    .button({label: window.hWin.HR('Download data to file'), iconPosition:'end', icon: "ui-icon-circle-arrow-s"})
                    .on('click', function(e) {
                            _showRecords2('all', true)  
                        });
        $('#btnClearFile')
                    .css({'width':'160px'})
                    .button({label: window.hWin.HR('Clear uploaded file'), iconPosition:'end', icon: "ui-icon-circle-close"})
                    .on('click', function(e) {
                            _doClearSession(true);
                        });
                        
        $('#btnSetPrimaryRecType').on('click', _doSetPrimaryRecType); //reset primary rectype
        
        //init navigation links
        $.each($('.navigation'), function(idx, item){
            $(item).on('click', _getValuesFromImportTable );
        })

        //session id is defined - go to 3d step at once        
        if(imp_ID>0){
            _loadSession();
        }else{
            _showStep(1);
        }
        
        
        //--------------------------- action buttons init

        $('#btnMatchingStart')
                    //.css({'width':'250px'})
                    .css({'font-weight':'bold'})  //Match against existing records
                    .button({iconPosition:'end', icon: "ui-icon-circle-arrow-e"})
                    .on('click', function(e) {
                            _doMatchingInit();
                        });
/*
        $('#btnMatchingSkip')
                    .button({iconPosition:'end', icon: "ui-icon-circle-arrow-e"})
                    .on('click', function(e) {
                            _doMatching( 2, null );
                        });
*/                        
        $('#btnBackToMatching')
                    //.css({'width':'250px'})
                    .button({label: window.hWin.HR('step 1: Match Again'), icon: "ui-icon-circle-arrow-w"})
                    .on('click', function(e) {
                            _showStep(3);
                            _initFieldMapppingTable();
                        });
        
        $('#btnBackToMatching2')
                    //.css({'width':'250px'})
                    .button({label: window.hWin.HR('Match Again'), icon: "ui-icon-circle-arrow-w"})
                    .on('click', function(e) {
                            _showStep(3);
                            _initFieldMapppingTable();
                        });
                        
        $('#btnResolveAmbiguous')
                    //.css({'width':'250px'})
                    .css({'font-weight':'bold'})
                    .button({label: window.hWin.HR('Resolve ambiguous matches')})
                    .on('click', function(e) {
                            _showRecords('disamb');
                        });
                        
        $('#btnShowErrors')
                    .css({'font-weight':'bold'})
                    .button({label: window.hWin.HR('Show'), icon: "ui-icon-alert"})
                    .on('click', function(e) {
                            _showRecords('error');
                        });
        $('#btnShowWarnings')
                    .css({'font-weight':'bold'})
                    .button({label: window.hWin.HR('Show'), icon: "ui-icon-alert"})
                    .on('click', function(e) {
                            _showRecords('warning');
                        });
        $('#btnShowUTMWarnings')
                    .css({'font-weight':'bold'})
                    .button({label: window.hWin.HR('UTM coords?'), icon: "ui-icon-alert"})
                    .on('click', function(e) {
                        
                $('#btnShowUTMWarnings').text('UTM coords?');
                        
                                     let $ddlg, buttons = {};
                buttons['Yes, UTM'] = function(){ 
                    
                    UTMzone = $ddlg.find('#dlg-prompt-value').val();
                    if(!window.hWin.HEURIST4.util.isempty(UTMzone)){
                            let re = /s|n/gi;
                            let zone = parseInt(UTMzone.replace(re,''));
                            if(isNaN(zone) || zone<1 || zone>60){
                                UTMzone = 0;
                                setTimeout('alert("UTM zone must be within range 1-60");',500);
                                return false;
                            }
                            $('#btnShowUTMWarnings').text('UTM '+UTMzone);
                    }else{
                        UTMzone = 0;
                    }
                    $ddlg.dialog('close'); 
                }; 
                buttons['No'] = function(){ $ddlg.dialog('close'); };
                
                $ddlg = window.hWin.HEURIST4.msg.showMsgDlg( 
    '<p>We have detected coordinate values in the import '
    +'which we assume to be UTM coordinates/grid references.</p><br>'
    +'<p>Heurist will only import coordinates from one UTM zone at a time. Please '
    +'split into separate files if you have more than one UTM zone in your data.</p><br>'
    +'<p>UTM zone (1-60) and Hemisphere (N or S) for these data: <input id="dlg-prompt-value"></p>',
        buttons, 'UTM coordinates?');
        
                        });

                        
        $('#btnPrepareStart')
                    //.css({'width':'250px'})
                    .css({'font-weight':'bold'})
                    .button({label: window.hWin.HR('Prepare'), iconPosition:'end', icon: "ui-icon-circle-arrow-e"})
                    .on('click', function(e) {
                            _doPrepare();
                        });

        $('#btnImportStart')
                    //.css({'width':'250px'})
                    .css({'font-weight':'bold'})
                    .button({label: window.hWin.HR('Start Insert/Update'), iconPosition:'end', icon: "ui-icon-circle-arrow-e"})
                    .on('click', function(e) {
                            _doImport();
                        });

/* repalced to help text                        
        $('#btnNextRecType1')
                    .button({label: window.hWin.HR('Skip to next record type'), iconPosition:'end', icon: "ui-icon-circle-arrow-e"})
                    .on('click', function(e) {
                            _skipToNextRecordType();
                        });
*/                                                
        $('#btnNextRecType2')
                    .button({label: window.hWin.HR('Skip update')}) //iconPosition:'end', icon: "ui-icon-circle-arrow-e"})
                    .on('click', function(e) {
                            _skipToNextRecordType();
                        });

        window.hWin.HEURIST4.ui.createEncodingSelect($('#csv_encoding'), [{key: 'auto detect', title: 'auto detect'}]);

        $(window).on('resize', function(e)
        {
            _adjustTablePosition();
        });
                       
                       
          //TEST            _doSetPrimaryRecType(); 
    }

    //
    // Remove all sessions
    //
    function _doClearSession(is_current){
        
        if( !window.hWin.HAPI4.is_admin() ){
            window.hWin.HEURIST4.msg.showMsgErr({
                status:window.hWin.ResponseStatus.REQUEST_DENIED,
                message:'Administrator permissions are required'});    
            return;
        }

        let recID = 0;
        if(is_current==true){
             recID = imp_ID;
        }
        
        window.hWin.HEURIST4.util.setDisabled($('#btnClearAllSessions'), true);
        window.hWin.HAPI4.EntityMgr.doRequest({a:'delete', entity:'sysImportFiles', recID:recID},
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            if(is_current==true){
                                $('#selImportId > option[value="'+recID+'"]').remove();
                                
                                if($('#selImportId > option').length>1){
                                    window.hWin.HEURIST4.util.setDisabled($('#btnClearAllSessions'), false);
                                }
                                 
                                _showStep(1);
                                window.hWin.HEURIST4.msg.showMsgDlg('Import session was cleared');
                            }else{
                                $('#selImportId').empty();
                                //IJ asked 2016-11-15 window.hWin.HEURIST4.msg.showMsgDlg('Import sessions were cleared');
                            }
                            session_selector.hSelect("refresh");
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                            window.hWin.HEURIST4.util.setDisabled($('#btnClearAllSessions'), false);
                        }
                    });
    }

    //
    //
    //
    function _loadSession(){
        
        if(imp_ID>0){
        
            let request = {
                'a'          : 'search',
                'entity'     : 'sysImportFiles',
                'details'    : 'list',
                'sif_ID'     : imp_ID
            };

            window.hWin.HEURIST4.msg.bringCoverallToFront($(document.body), null, 'Retrieving and loading previous session...');

            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){

                    window.hWin.HEURIST4.msg.sendCoverallToBack();
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                    
                        //clear selectors
                        $('#dependencies_preview').empty();
                        $('#lblPrimaryRecordType').empty();
                        $('#sa_rectype_sequence').empty();
                        
                        $('#dependencies_preview').css('background',$('#sa_primary_rectype').css('background'));
                        
                        let resp = new HRecordSet( response.data );
                        let record = resp.getFirstRecord();
                        let ses = resp.fld(record, 'sif_ProcessingInfo');
                        
                        imp_session = (typeof ses == "string") ? JSON.parse(ses) : null;
                        
                        //init field mapping table
                        if(imp_session){
                            let sequence = imp_session['sequence'];
                            if(!sequence || $.isEmptyObject(sequence)){
                                _doSetPrimaryRecType(true);
                            }else{
                                _renderRectypeSequence();
                            }
                        }
                        
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                }
            );        
        
        
        }
    }
    
    //
    //  open dialog to choose primary record type
    //
    function _doSetPrimaryRecType(is_initial){
        
            let $dlg, buttons = {}, _is_CancelClose = true;
        
            //apply selection of primary and dependent record types
            buttons[window.hWin.HR('OK')]  = function() {
                _is_CancelClose = false;    
                $dlg.dialog( "close" );

                //prepare sequence object - based on selected rectypes and field names
                //find marked rectype checkboxes 
                let treeElement = $('#dependencies_preview');
                let recTypeID, field_key;
                let rectypes = treeElement.find('.rt_select:checked');
                rectypes.sort(function(a,b){ return $(a).attr('data-lvl') - $(b).attr('data-lvl')});

                let sequence = [];
                for(let i=0;i<rectypes.length;i++){
                    if($(rectypes[i]).attr('data-mode')==2) continue;
                    
                    recTypeID = $(rectypes[i]).attr('data-rectype');
                    field_key = $(rectypes[i]).attr('data-rt');

                    //find names of identification fields
                    let ele = treeElement.find('.id_fieldname[data-res-rt="'+field_key+'"][data-mode="0"]');
                    if(ele.length>0){
                        for(let j=0;j<ele.length;j++){
                            let isfound = false;
                            for(let k=0;k<sequence.length;k++){
                                if(sequence[k].field == $(ele[j]).text()){
                                    isfound = true;
                                    break;
                                }
                            }
                            if(!isfound){
                                sequence.push({field:$(ele[j]).text(), rectype:recTypeID, 
                                    hierarchy:$(rectypes[i]).attr('data-tree') });    
                            }
                        }
                    }else{
                        let fname = _getColumnNameForPresetIndex(recTypeID);//AAA
                        sequence.push({field: fname, rectype:recTypeID, 
                                hierarchy:$(rectypes[i]).attr('data-tree') });    
                    }
                    
                }
                if(sequence.length==0){ //no dependencies - add main rectype only
                    recTypeID = $('#sa_primary_rectype').val()>0?
                                        $('#sa_primary_rectype').val()
                                        :imp_session['primary_rectype'];
                    const fname = _getColumnNameForPresetIndex(recTypeID);
                    sequence.push({field:fname, rectype:recTypeID, hierarchy:recTypeID});    
                }
                         
                //compare current and new sequences
                let isSomethingChanged = ($('#sa_primary_rectype').val()!=imp_session['primary_rectype'])
                            || (!imp_session['sequence'] || imp_session['sequence'].length!=sequence.length);
                
                if(!isSomethingChanged){
                    
                     for(let i=0;i<sequence.length;i++){
                         
                        isSomethingChanged = (imp_session['sequence'][i].field != sequence[i].field ||  
                                              imp_session['sequence'][i].rectype != sequence[i].rectype); 
                        if(isSomethingChanged) break;
                     }
                }
                    
                if (isSomethingChanged) {

                        imp_session['primary_rectype'] = $('#sa_primary_rectype').val();    
                        
                        imp_session['sequence'] = sequence;
                        
                        //save session - new primary rectype and sequence
                        let request = { action: 'set_primary_rectype',
                            sequence: imp_session['sequence'],
                            rty_ID: imp_session['primary_rectype'],
                            imp_ID: imp_ID,
                                id: window.hWin.HEURIST4.util.random()
                               };
                               
                            window.hWin.HAPI4.doImportAction(request, function( response ){
                                
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    //and render sequence
                                    _renderRectypeSequence();
                                }else{
                                    _showStep(1);
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                }
                            });
                    }
                    
            }; 
            buttons[window.hWin.HR('Cancel')]  = function() {
                    $dlg.dialog( "close" );
                /*
                    $dlg.parent().find('#sa_primary_rectype').off('change');
                    $dlg.dialog( "close" );
                    if(is_initial==true){
                         _showStep(1);
                    }
                */    
            };

            let dlg_options = {
                title:'Select primary record type and dependencies',
                height: 640,
                width: 900,
                default_palette_class: 'ui-heurist-populate',
                element: document.getElementById('divSelectPrimaryRecType'),
                buttons: buttons,
                open:function(){
                    
                    let select_rectype = $(this).find('#sa_primary_rectype');
                    select_rectype = window.hWin.HEURIST4.ui.createRectypeSelect( select_rectype.get(0), 
                                null, window.hWin.HR('select...'), false);

                    //reload dependency tree on select change
                    select_rectype.hSelect({change: 
                    function(event, data){ 
                            let treeElement = $dlg.find('#dependencies_preview');
                            let selval = data.item.value;
                            _loadRectypeDependencies( $dlg, treeElement, selval ); 
                    }});                

                    let selval = imp_session['primary_rectype'];
                    select_rectype.val( selval );
                    select_rectype.hSelect('refresh');
                    
                    let treeElement = $(this).find('#dependencies_preview');
                    _loadRectypeDependencies( $(this), treeElement, selval ); 
                    
                },
                close: function(event, ui){
                    
                    if(_is_CancelClose){
                        if(is_initial==true){
                             _showStep(1);
                        }
                    }else{
                        //parent()
                        $dlg.find('#sa_primary_rectype').off('change');    
                        $dlg.find('#sa_primary_rectype').hSelect("destroy");
                    }
                }
                };
            $dlg = window.hWin.HEURIST4.msg.showElementAsDialog(dlg_options);
            //$dlg = window.hWin.HEURIST4.msg. 'importRecordsCSV_selectRectype.html'
            
            $dlg.addClass('ui-heurist-bg-light');
            //disable OK button
            if(!(imp_session['primary_rectype']>0)){
                let btn = $dlg.parent().find('.ui-dialog-buttonset > button').first();
                window.hWin.HEURIST4.util.setDisabled(btn, true);
            }
            

                
            
        
    }

    //
    // render sequence ribbon
    //
    function _renderRectypeSequence(){

        let i, sequence = imp_session['sequence'];

        $('#sa_rectype_sequence').empty();

        //sequence is not defined - select primary record type and define index field names
        if(!sequence || $.isEmptyObject(sequence)){
            _doSetPrimaryRecType(true);
            return false;
        }

        _showStep(3);

        $('img[id*="img_arrow"]').hide();
        let ele1 = $('#sa_rectype_sequence').empty();
        let s = '';

        let initial_selection = 0;

        for (i=0;i<sequence.length;i++){
            let recTypeID = sequence[i].rectype;
            let fieldname = sequence[i].field;
            let hierarchy = sequence[i].hierarchy;

            let title = $Db.rty(recTypeID, 'rty_Name');

            let counts = _getInsertUpdateCounts( i );
            if(!(counts[2]==0 && counts[0]>0)){ //not completely matching
                initial_selection = i;
            }
            let s_count = _getInsertUpdateCountsForSequence( i ); //todo change to field

            let sArr = '';
            if(i<sequence.length-1){
                sArr = '<div style="display:inline-block;min-height:2em"><span class="ui-icon ui-icon-arrowthick-1-e rt_arrow" style="vertical-align:super"></span>';
            }

            let sid_fields =  sArr 
                        + '<div style="border:1px solid black;-size:0.9em" '
                        + 'class="select_rectype_seq" data-seq-order="' + i + '">';

            let fields = hierarchy;
            if(!Array.isArray(hierarchy) && !window.hWin.HEURIST4.util.isempty(hierarchy)){
                fields = hierarchy.split(',');
            }

            if(Array.isArray(fields) && fields.length>0){

                let prev_rt = fields[fields.length-1];

                for(let j=fields.length-1;j>=0;j--){
                    let field = fields[j], k = field.indexOf('.');
                    if(k>0){
                        let field_id = field.substr(0,k);
                        let rt_id = field.substr(k+1);

                        let field_title = $Db.rst(prev_rt, field_id, 'rst_DisplayName');
                        let rt_title = $Db.rty(rt_id,'rty_Name');
                        prev_rt = rt_id;

                        sid_fields = sid_fields + ((j<fields.length-2)?'<br class="hid_temp">':'')
                                + '<span class="hid_temp"><i>' + field_title + '</i></span>'
                                + '<span class="ui-icon ui-icon-arrowthick-1-e rt_arrow" style="padding:0"></span>'
                                + '<b'+(j>0?' class="hid_temp"':'') +'>' + rt_title + '</b>';
                    }else if(fields.length==1){
                        sid_fields = sid_fields + '<b>' + title + '</b>'; 
                    }
                }
            }

            s =  sid_fields +  '<span data-cnt="1" id="rt_count_'
                    + i + '">' + s_count + '</span><br><span data-cnt="1" class="id_fieldname" data-mode="0" style="padding-left:0em">'
                    +fieldname+'</span></div>'+(sArr!=''?'</div>':'')+s;

            if(i==0){
                $('#lblPrimaryRecordType').text(title);
            }
        } //for

        $('#ignore_rectype').parent().position({
            my: 'left-15 top', at: 'right top', of: $('#lblPrimaryRecordType').parent()
        })

        $(s).appendTo(ele1);

        $('.select_rectype_seq').on('click', function(event){

            let sp = $(event.target)

            //get next id field
            if(!sp.hasClass('select_rectype_seq')){
                sp = sp.parents('.select_rectype_seq');
            }

            let idx = sp.attr('data-seq-order');

            _skipToNextRecordType(idx);
        });

        //select first in sequence
        $('.select_rectype_seq[data-seq-order="'+initial_selection+'"]').trigger('click');    

        _adjustTablePosition();

        //_initFieldMapppingTable();    
        return true;
    }

    //
    //  get hierarchy (parents) for given field
    //
    function _getHierarchy(fields, field_key){

        //get parent
        let i, res = [field_key];

        let field_keys = Object.keys(fields);
        for (i=0;i<field_keys.length;i++){ 
            
             if(fields[field_keys[i]].depend.indexOf(field_key)>=0){
                 //res.push(field_keys[i]);
                 let res2 = _getHierarchy(fields, field_keys[i]);
                 res = res.concat(res2);
                 break;
             }
            
        }//for

        return res;
    }
    
    //
    //  selected_fields - fields marked initially
    //
    function _getCrossDependencies(rtOrder, selected_fields, result_depend){

        let fields = rtOrder['fields'], levels = rtOrder['levels'];
        
        let i, j, new_selected=[];

        for (i=0;i<selected_fields.length;i++){ //loop all selected
            
             let field = fields[selected_fields[i]];
             
             if(!field){
                 continue;
             }
             
             if(field['depend'] && field['depend'].length>0){
                
                let rt_id = field['rt_id']; 
                let ft_ids = [];
               
                //todo! if any of required field keys are checked do not include
                //now it always include first item 
                
                for (j=0;j<field['depend'].length;j++){ 
                    let f_key = field['depend'][j];
                    let ft_id = _getFt_ID(f_key);
                    if($Db.rst(rt_id, ft_id, 'rst_RequirementType')=='required' 
                        && levels[selected_fields[i]]<levels[f_key]) {
                        //&& ft_ids.indexOf(ft_id)<0){
                        
                        result_depend.push(f_key);
                        new_selected.push(f_key);   
                        ft_ids.push(ft_id);
                    }
                } 
             }
        }//for
        if(new_selected.length>0){
            result_depend = _getCrossDependencies(rtOrder, new_selected, result_depend);            
        }
        return result_depend;     
            
    }
    
    
    //
    // show dependecies list in popup dialog where we choose primary rectype
    //
    function _loadRectypeDependencies($dlg, treeElement, preview_rty_ID ){

        //request to server to get all dependencies for given primary record type
        let request = { action: 'set_primary_rectype',
            sequence: null,
            rty_ID: preview_rty_ID,
            imp_ID: imp_ID,
            id: window.hWin.HEURIST4.util.random()
        };
        
        
        treeElement.empty();
        let btn = $dlg.parent().find('.ui-dialog-buttonset > button').first();
        window.hWin.HEURIST4.util.setDisabled(btn, !(preview_rty_ID>0));
        
        if(preview_rty_ID>0){                

        treeElement.addClass('loading');
        treeElement.html('<div style="font-style:italic;padding-bottom:20px">loading...</div>');
        window.hWin.HAPI4.doImportAction(request, function( response ){

            treeElement.removeClass('loading');
            window.hWin.HEURIST4.util.setDisabled(btn, false);
        
            
            //that.loadanimation(false);
            if(response.status == window.hWin.ResponseStatus.OK){

                let rectypes = response.data;
                uniq_fieldnames = [];
                let rtOrder = _fillDependencyList(rectypes, {levels:{}, fields:{} }, 0);    
                //rt_fields - resource (record pointer) fields
                //depend - only required dependencies 

                let i, j, rt_resource, field_keys = Object.keys(rtOrder['levels']),
                isfound, depth=0, prev_depth = 0, found_levels=0;

                let prev_rt = 0, primary_rt = 0;
                let field_key;

                //fill dependecies list in popup dialog where we choose primary rectype
                treeElement.empty();
                
                let depList = $('<div id="dep_list">').appendTo(treeElement);
                
                let isfirst_dep = true;
                let top_offset = 0; //padding for label
                
                //Dependency list==================================================
                do{
                    //find all record types for particular level 
                    isfound = false;
                    let fields_for_level = [];
                    for (i=0;i<field_keys.length;i++){
                        field_key = field_keys[i];
                        if(rtOrder['levels'][field_key] == depth){
                            isfound = true;
                            fields_for_level.push(field_key);
                        }
                    }//for

                    if(isfound){
                        //sort  according to order of fields of last rectype from previous level
                        if(prev_rt!=0){
                            let orders = rtOrder['fields'][prev_rt]['depend'];
                            fields_for_level.sort(function(a,b){
                                let k1 = orders.indexOf(a);
                                let k2 = orders.indexOf(b);
                                if(k1>=0 && k2>=0){
                                    return (k1-k2);
                                }else{
                                    return (k1<k2)?1:-1;
                                }
                            });
                        }
                        for (i=0;i<fields_for_level.length;i++){

                            field_key = fields_for_level[i];
                            let depth_separator = '';
                            if(i==0 && depth>0){
                                depth_separator = ';border-top:1px gray solid';
                            }

                            //get hierarchy
                            let hierarchy = _getHierarchy(rtOrder['fields'], field_key);

                            let field = rtOrder['fields'][field_key];

                            let sRectypeItem = '<div style="padding:3px;'+(depth>0?'padding-top:1em':'')
                            + depth_separator+'">' 
                            + '<input type="checkbox" class="rt_select" data-rectype="'+field['rt_id']
                            +  '" data-rt="'+field_key+'" '
                            +  ' data-lvl="'+depth+'" data-tree="' 
                            + (hierarchy.join(','))+ '" '
                            + (depth==0?'checked="checked" disabled="disabled"':'')
                            + '>';
                            
                            if(isfirst_dep && depth_separator!=''){
                                isfirst_dep = false;
                                sRectypeItem = '<div style="background:white;width:100%;min-height:2em;margin-top:4px"></div>'
                                    +sRectypeItem;
                                $dlg.parent().find('#lbl_dependencies_preview').css('padding-top',(top_offset+2)*2 +'em');
                            }

                            //get field name that refers to this recordtype
                            if(depth!=0){

                                if(field){
                                    
                                    let is_required = false;
                                    if(hierarchy.length>1){
                                        const field_id = _getFt_ID(field_key)
                                        const parent_field_key = hierarchy[1];
                                        const parent_rt = _getRt_ID(parent_field_key);

                                        is_required = ($Db.rst(parent_rt, field_id, 'rst_RequirementType')=='required');
                                    }
                                    
                                    sRectypeItem = sRectypeItem + '<div style="display:inline-block">'
                                    + '<i'+(is_required?' style="color:red"':'')+'>'  + field['title'] + '</i>'
                                    + '</div>'                                     
                                    + '<span class="ui-icon ui-icon-arrowthick-1-e rt_arrow"></span>'; 
                                }
                            }

                            sRectypeItem = sRectypeItem + field['rt_title'];

                            if(depth==0){ //add PRIMARY field

                                //check if index field already defined in preset

                                sRectypeItem = sRectypeItem 
                                + ' <span style="font-size:0.8em;font-weight:bold">(primary record type)</span>' 
                                + '<span class="id_fieldname" style="float:right"  data-res-rt="'+field_key+'">'
                                + field['id_field'] + '</span>';

                                primary_rt = field_key;

                            }


                            sRectypeItem = sRectypeItem + '</div>';
                            $(sRectypeItem).appendTo(depList);

                            //resorce fields
                            let rt_fields = field['depend'], sid_fields = '', prev_field='';
 
                            if(rt_fields && rt_fields.length>0){
                                for (let j=0;j<rt_fields.length;j++){

                                    const rt_field_key = rt_fields[j];

                                    let rt_field = rtOrder['fields'][rt_field_key];

                                    const field_id = _getFt_ID(rt_field_key);

                                    const field_title = $Db.rst(field['rt_id'], field_id, 'rst_DisplayName');
                                    const is_required = ($Db.rst(field['rt_id'], field_id, 'rst_RequirementType')=='required');
                                    
                                    sid_fields =  
                                    '<div style="display:inline-block;">'
                                    + '<div style="min-width:150px;display:inline-block">'
                                    + (prev_field!=field_title  //rt_field['title']
                                        ?'<i style="'+(is_required?'color:red':'')+'">' + field_title + '</i>'
                                        :'') + '</div>'
                                    + '<span class="ui-icon ui-icon-arrowthick-1-e rt_arrow"></span>'
                                    + rt_field['rt_title'] + '</div>' 

                                    + '<span style="float:right" class="id_fieldname rename" data-mode="0" data-res-rt="'
                                    + rt_field_key +'">' + rt_field['id_field'] + '</span><br>';

                                    prev_field = field_title;//rt_field['title'];

                                    $('<div style="padding:0 3px 0 2em;">'
                                        //+'<span class="ui-icon ui-icon-triangle-1-e rt_arrow"></span>'

                                        //+ '<span class="ui-icon ui-icon-arrowthick-1-e rt_arrow"></span>'
                                        //+ field['rt_title']
                                        + sid_fields
                                        +'</div>')
                                    .appendTo(depList);

                                    top_offset++;
                                }
                            }else{
                                
                                $('<div style="padding-left:4em;">'
                                    + '<i>No pointer fields defined</i>'
                                    +'</div>')
                                .appendTo(depList);

                            }

                        }//for
                        prev_rt = field_key;
                        found_levels++;
                    } //is found
                    depth++;

                }while(found_levels<4 && depth<10);

                if(field_keys.length==1){
                    $dlg.parent().find('#lbl_dependencies_preview').css('padding-top',0);
                    treeElement.text('No dependencies found');
                }else{

                    let rt_added = [];
                    let treeList = $('<div id="tree_list">').appendTo(treeElement).hide();
                    
                //Treview==========================================================
                function __renderTree(field_key, depth){
                    
                    let field = rtOrder['fields'][field_key];
                    
                    let parent_rt = field['rt_id'];
                    
                    if(depth==0){ //add PRIMARY field
                            let sRectypeItem = '<div>'
                            + field['rt_title']
                            + ' <span style="font-size:0.8em;font-weight:bold">(primary record type)</span>' 
                            + '<span class="id_fieldname" style="float:right"  data-res-rt="'+field_key+'">'
                            + field['id_field'] 
                            
                            + '&nbsp;<input type="checkbox" class="rt_select" data-mode="2" data-rectype="'+field['rt_id']
                            +  '" data-rt="'+field_key+'" '
                            +  ' data-lvl="'+depth+'" checked="checked" disabled="disabled">'  
                            +  '</span></div>';
                            $(sRectypeItem).appendTo(treeList);                            

                    }
                    
                    let rt_fields = field['depend'];
                    
                    if(rt_fields && rt_fields.length>0){
                        for (let j=0;j<rt_fields.length;j++){

                            let rt_field_key = rt_fields[j];

                            let rt_field = rtOrder['fields'][rt_field_key];
                            
                            if(rt_added.indexOf(parent_rt+'.'+rt_field_key)>=0) continue;
                            rt_added.push(parent_rt+'.'+rt_field_key);

                            let field_id = _getFt_ID(rt_field_key);

                            let field_title = $Db.rst(field['rt_id'], field_id, 'rst_DisplayName');
                            let is_required = ($Db.rst(field['rt_id'], field_id, 'rst_RequirementType')=='required');
                            
                            let sid_fields =  
                            '<div style="padding-left:'+(depth*2+1)+'em;display:inline-block;">'
                            + '<div style="min-width:150px;display:inline-block">'
                            //+ (prev_field!=field_title? :'')  //rt_field['title']
                                + '<i style="'+(is_required?'color:red':'')+'">' + field_title + '</i>'
                                + '</div>'
                            + '<span class="ui-icon ui-icon-arrowthick-1-e rt_arrow"></span>'
                            + rt_field['rt_title'] + '</div>' 

                            + '<span style="float:right">&nbsp;<input type="checkbox" class="rt_select" data-mode="2" data-rectype="'+rt_field['rt_id']
                            +  '" data-rt="'+rt_field_key+'"></span>'                            
                            + '<span style="float:right" class="id_fieldname rename" data-mode="2" data-res-rt="'
                            + rt_field_key +'">' + rt_field['id_field']  
                            + '</span><br>';

                            $('<div style="padding-left:1em;">'
                                + sid_fields
                                +'</div>')
                            .appendTo(treeList);   
                            
                            if(depth<4)
                                __renderTree(rt_field_key, depth+1);

                        }
                    }                    
                }
                
                __renderTree(primary_rt, 0);
                    
                    
                //
                // auto select dependent requried fields
                //    
                function __rt_checkbox_click(rt_checkbox, mode){
                    
                    //keep id of clicked to avoid reverse disability
                    let i, j;
                    
                    let recTypeID = rt_checkbox.attr('data-rectype');
                    let keep_field_key = rt_checkbox.attr('data-rt');
                    let cb;
                    
                    if(rt_checkbox.attr('data-mode')==2){
                        cb = treeElement.find('.rt_select[data-rt="'+keep_field_key+'"][data-mode!="2"]');
                    }else{
                        cb = treeElement.find('.rt_select[data-rt="'+keep_field_key+'"][data-mode="2"]');
                    }
                    if(rt_checkbox.is(':checked')){
                        cb.prop('checked', true);     
                    }else{
                        cb.prop('checked', false);     
                    }
                    if(rt_checkbox.attr('data-mode')==2){
                        rt_checkbox = cb;
                    }
                    
                    //all rt that will be checked and disabled
                    let f_key_selected_all = [];

                    //get all selected (marked) fields
                    $.each(treeElement.find('.rt_select:checked'),function(idx, item){
                        let f_key = $(item).attr('data-rt');    
                        f_key_selected_all.push(f_key);
                    });


                    window.hWin.HEURIST4.util.setDisabled( treeElement.find('.rt_select[data-rt!="'+primary_rt+'"]'), false);

                    // find all dependent rectypes to be marked and disabled
                    let fields_affected;
                    if(recTypeID==primary_rt){
                        fields_affected = _getCrossDependencies(rtOrder, f_key_selected_all, []);    
                    }else{
                        fields_affected = _getCrossDependencies(rtOrder, [recTypeID], []);    
                    }
                    

                    //mark all of them
                    for(let i=0;i<fields_affected.length;i++){
                        cb = treeElement.find('.rt_select[data-rt="'+fields_affected[i]+'"]');
                        cb.prop('checked',true);
                        if(keep_field_key!=fields_affected[i]){ //do not disable itself
                            //NO MORE DISABLITY since 2016-11-29
                            
                            //cb.css({'opacity': 1, 'filter': 'Alpha(Opacity=100)'});
                        }
                    }
                    
                    //always disable primary key
                    cb = treeElement.find('.rt_select[data-rt="'+primary_rt+'"]');
                    cb.prop('checked',true);
                    window.hWin.HEURIST4.util.setDisabled(cb, true);
                    cb.css({'opacity': 1, 'filter': 'Alpha(Opacity=100)'});
                }//end  __rt_checkbox_click                           

                treeElement.find('.rt_select').on('click', 
                    function(event){ __rt_checkbox_click( $(event.target)) }
                );
                
                //click and disable first (primary) record type

                let cb = treeElement.find('.rt_select[data-rt="'+primary_rt+'"]');
                cb.prop('checked',true);
                __rt_checkbox_click( cb, '' );
                window.hWin.HEURIST4.util.setDisabled(cb, true);
                cb.css({'opacity': 1, 'filter': 'Alpha(Opacity=100)'});

                //click to remame identification field                            
                function __idfield_rename(ele_span){

                    //keep id of clicked to avoid disability
                    let i, j, field_key = ele_span.attr('data-res-rt');
                    let idfield_name_old = ele_span.text();

                    //show popup to rename
                    window.hWin.HEURIST4.msg.showPrompt('Name of identifiecation field', function(idfield_name){
                        if(!window.hWin.HEURIST4.util.isempty(idfield_name)){

                            //set span content
                            treeElement.find('span[data-res-rt="'+field_key+'"]').html(idfield_name);
                            
                            //set span content
                            //ele_span.html(idfield_name);
                            //change value in rtStruct
                            //rtOrder['idfields'][dtyID] = idfield_name;

                        }
                    });



                } 

                treeElement.find('.rename').on('click', 
                    function(event){ __idfield_rename($(event.target)) }
                );

                function __switchViewMode(event){ 
                        if($('#mode_view0').is(':checked')){
                            $('#tree_list').hide();
                            $('#dep_list').show();
                        }else{
                            $('#tree_list').show();
                            $('#dep_list').hide();
                        }        
                }

                $('input[name="mode_view"]').on('click',  __switchViewMode );

                __switchViewMode();

            } //endif

        }else{
            //error on server response
            _showStep(1);
            window.hWin.HEURIST4.msg.showMsgErr(response);
        }

        });        

        }
    }   

    //
    //
    //
    function _getFt_ID(field_key){
        return field_key.substr(0, field_key.indexOf('.'));
    }
    //
    //
    //
    function _getRt_ID(field_key){
        let k = field_key.indexOf('.');
        if(k<0){
            return field_key;
        }else{
            return field_key.substr(k+1);
        }
        
    }
    
    //
    //
    //
    function _skipToNextRecordType(seq_index_next){
        
            seq_index_next = Number(seq_index_next);
            currentSeqIndex = Number(currentSeqIndex);
            let seq_index_prev = -1;
            
            if(seq_index_next>currentSeqIndex){
                
                __changeRectype();
                
            }else{
            
            if(seq_index_next>=0){ 
                if(seq_index_next<imp_session['sequence'].length-1){
                    seq_index_prev = seq_index_next+1;
                }
            }else{//not defined - take current and next
                seq_index_prev = currentSeqIndex;
                seq_index_next = currentSeqIndex>0?currentSeqIndex-1:0;
            }
        
            //is previous record type have matched records?
            let counts = _getInsertUpdateCounts( seq_index_prev ); 
            if(counts==null || counts[0]>0){
                 __changeRectype();
            }else{
                let sWarning = 
                'By skipping earlier steps in the workflow you may not have the required '
                +'record identifiers to use in record pointer fields. This may be OK if your aim '
                +'is simply to update fields other than pointer fields';
                
                window.hWin.HEURIST4.msg.showMsgDlg(sWarning, __changeRectype, 
                        {title:'Confirmation',yes:'Proceed',no:'Cancel'} );                                                             
            }
            }
            
            function __changeRectype(){
                _showStep(3);
                
                currentSeqIndex = seq_index_next;
                _redrawArrow();
                _initFieldMapppingTable();
            }        
    } 
    
    //
    //
    //
    function _redrawArrow(){
        if(currentSeqIndex>=0){
                $('div.select_rectype_seq').removeClass('ui-state-focus');
                let selitem = $('div.select_rectype_seq[data-seq-order="'+currentSeqIndex+'"]').addClass('ui-state-focus');
                
                if(selitem && selitem.length>0){
                
                //position of arrow image
                let ileft = $('#divheader').offset().left+25;
                let iline_top = $('#divheader').offset().top-4;
                let iright = selitem.offset().left + selitem.width()/2;
                let itop = selitem.offset().top + selitem.height() + 7;

                if(currentStep>3){
                    ileft = $('#btnBackToMatching').offset().left+$('#btnBackToMatching').width()+25;
                }else{
                    ileft = $('#divheader').offset().left+112;
                }

                $('#img_arrow4').css({left: ileft, top: iline_top+1 });  //down to arrow
                $('#img_arrow3').css({left: ileft, top: iline_top+16 });  //arrow
                $('#img_arrow2').css({left: iright, top: itop, height: iline_top-itop+2 }); //line down
                
                if(ileft<iright){                
                    $('#img_arrow1').css({left: ileft, top: iline_top, width:(iright-ileft+2)});  //line horizontal
                }else{
                    $('#img_arrow1').css({left: iright, top: iline_top, width:(ileft-iright+2)});  //line horizontal
                }
                
                $('img[id*="img_arrow"]').show();
                
                }
        }
    }
    //
    // returns list of field/rectypes 
    
    //  with level (depth) 
    //    list of resource (record pointer) fields
    //    list of dependent rectype     
    //
    //  rtOrder 
    //     key is concatenation  field_type.record_type (resource rectype)
    //
    //  levels:{},   key:level - list of rectypes with level value - need for proper order in sequence
    //  fields:{}
    //        key:{title,rt_title,rt_id,id_field(in import table),required}
    //
    //
    function _fillDependencyList(rectypeTree, rtOrder, depth, parent_field_key){


         let i, j, k, recTypeID, parent_rectype_id = '';
         
         if(depth==0){
            if(window.hWin.HEURIST4.util.isArrayNotEmpty(rectypeTree)){
                rectypeTree = rectypeTree[0];
                parent_field_key = rectypeTree.key; //'0.'+
                parent_rectype_id = rectypeTree.key;
                rtOrder['levels'][parent_field_key] = 0;  //primary rectype
                
                rtOrder['fields'][parent_field_key] = {
                                    title:  window.hWin.HEURIST4.util.trim_IanGt(rectypeTree.title),
                                    rt_id:  rectypeTree.key,
                                    rt_title: $Db.rty(rectypeTree.key,'rty_Name'),
                                    id_field: _getColumnNameForPresetIndex(parent_rectype_id),
                                    depend:[]
                                }                
                                
            }
         }else{
             //parent_field_key = parent_field_key+'.'+currentTypeID;
             /*if(parent_field_key.indexOf('.')>0){
                parent_rectype_id = parent_field_key.substr(parent_field_key.indexOf('.')+1);
             }else{
                parent_rectype_id = parent_field_key;
             }*/
         }         
         
         //rectype tree saves one level if constraint=1
         if(rectypeTree.type=='rectype'){
            parent_rectype_id = rectypeTree.key;
         }else  if (rectypeTree.constraint==1) { 
            parent_rectype_id = rectypeTree.rt_ids;   
         }  
         
                        
         //list all resource (record pointer) fields
         if(window.hWin.HEURIST4.util.isArrayNotEmpty(rectypeTree.children)){
             
             for (j=0;j<rectypeTree.children.length;j++){
                 
                   let field = rectypeTree.children[j];
                   if(field.type!='rectype'){
                       
                       let field_title = window.hWin.HEURIST4.util.trim_IanGt(field.title); 
                       
                       let ids = (field.rt_ids)?field.rt_ids.split(','):[];
                       let field_id = field['key'];
                       field_id = field_id.substr(2);//remove prefix "f:"
                      
                       let rectypeNames = [], idfields={};
             
                       for (i=0;i<ids.length;i++){
                                                      
                            recTypeID = ids[i];
                            
                            //parent_rectype_id +'.'+ 
                            let key_ft_rt =  field_id +'.'+ recTypeID;
                            
                            //adjust position in hierarchy
                            if(window.hWin.HEURIST4.util.isnull(rtOrder['levels'][key_ft_rt]) ||
                                (rtOrder['levels'][key_ft_rt]>0 && rtOrder['levels'][key_ft_rt] < depth+1))
                            {
                                rtOrder['levels'][key_ft_rt] = depth+1;    
                            }
                            
                            //get unique id field name for import table
                            let id_fieldname = _getColumnNameForPresetIndex(recTypeID, field_title);
                            if(imp_session['primary_rectype']!=recTypeID){
                                //add count to be unique
                                let pos = id_fieldname.indexOf('H-ID');
                                if(id_fieldname.indexOf('H-ID')== id_fieldname.length-4){
                                    if(uniq_fieldnames[id_fieldname]>0){
                                        uniq_fieldnames[id_fieldname] = uniq_fieldnames[id_fieldname] + 1;
                                        id_fieldname = id_fieldname + ' ' + uniq_fieldnames[id_fieldname];
                                    }else{
                                        uniq_fieldnames[id_fieldname] = 1;
                                    }
                                }
                            }
                            
                            if(window.hWin.HEURIST4.util.isnull(rtOrder['fields'][key_ft_rt])){
                                
                                rtOrder['fields'][key_ft_rt] = {
                                    title:  field_title,
                                    parent_rt_id: parent_rectype_id,
                                    rt_id:  recTypeID,
                                    rt_title: $Db.rty(recTypeID,'rty_Name'),
                                    id_field:  id_fieldname,
                                    required: field['required'],
                                    depth: depth,
                                    depend:[]
                               }
                            }else{ //change to required if it needs
                                
                               if(depth < rtOrder['fields'][key_ft_rt]['depth']){
                                   rtOrder['fields'][key_ft_rt]['id_field'] = id_fieldname;
                                   rtOrder['fields'][key_ft_rt]['depth'] = depth;
                               } 
                                
                            }


                            if(rtOrder['fields'][parent_field_key]['depend'].indexOf(key_ft_rt)<0){
                                rtOrder['fields'][parent_field_key]['depend'].push(key_ft_rt);
                            }
                             
                            
                            if( field.children.length>0){
                                for(k=0; k<field.children.length; k++){
                                    if(field.children[k].type=='rectype' && field.children[k].key==recTypeID){
                                        rtOrder = _fillDependencyList(field.children[k], rtOrder, depth+1, key_ft_rt);
                                        break;
                                    }else if(field.children[k].type!='rectype') {
                                        rtOrder = _fillDependencyList(field, rtOrder, depth+1, key_ft_rt);
                                    }
                                }
                            }
                            

                       } //for resource recordtypes
                       
                       
                   } //not rectype
                   else{
                        rtOrder = _fillDependencyList(field, rtOrder, depth, parent_field_key);
                   }
             }//for children
             
             /*
             for (j=0;j<rectypeTree.children.length;j++){
                 
                   let field = window.hWin.HEURIST4.util.cloneJSON(rectypeTree.children[j]);
                   if(field.type!='rectype'){
                            if( field.children.length>0){
                                for(k=0; k<field.children.length; k++){
                                    if(field.children[k].type=='rectype' && field.children[k].key==recTypeID){
                                        rtOrder = _fillDependencyList(field.children[k], rtOrder, depth+1, key_ft_rt);
                                        break;
                                    }else if(field.children[k].type!='rectype') {
                                        rtOrder = _fillDependencyList(field, rtOrder, depth+1, key_ft_rt);
                                    }
                                }
                            }
                   }else{
                        rtOrder = _fillDependencyList(field, rtOrder, depth, parent_field_key);
                   }
             }*/
 
             
         }//has children
         

         return rtOrder;
    }
    
    //check if index field already defined in preset
    //
    // imp_session['indexes'] = {field_1:10}  fieldname:recordtype_id
    //
    function _getColumnNameForPresetIndex(recTypeID, sname){
        
        sname = (sname ?sname :$Db.rty(recTypeID,'rty_Name')) +' H-ID'; // this is default name for index field 
                                                                              // to be added into import table
        let rts = Object.keys(imp_session['indexes']);
        for(let k=0; k<rts.length; k++){
            if(imp_session['indexes'][rts[k]]==recTypeID){
                let idx_id_fieldname = rts[k].substr(6); //'field_'
                sname = imp_session['columns'][idx_id_fieldname];
                break;
            }
        }
        
        return sname;
    }

    
    //
    // init field mapping table - main table to work with 
    //
    function _initFieldMapppingTable(){

        $('#tblFieldMapping > tbody').empty();
                        
        let sIndexes = "",
            sRemain = "",
            sProcessed = "", sID_field = '',
            sAllFields = [],
            i = 0,
            len = (imp_session && imp_session['columns'])?imp_session['columns'].length:0;

        //find index field for selected id field
        $('#mapping_column_header').text((currentStep==3)
                                                    ?'Column to Field mapping (record match)'
                                                    :'Fields to update');
        let stype = (currentStep==3)?'mapping_keys':'mapping_flds'; //for matching and for import
        let mapping_flds = imp_session['sequence'][currentSeqIndex][stype];  //field index=>dty_ID
        if(!mapping_flds) mapping_flds = {};
        
        
        //all mapped fields - to place column in "processed" section - gather for all sequences
        let all_mapped = [];
        for  (let i=0; i < imp_session['sequence'].length; i++) {
            for  (let fid in imp_session['sequence'][i][stype]) {
                if(all_mapped.indexOf(fid)<0) all_mapped.push(Number(fid));
            }
        }

        // Scale 'Column' column up to 50 characters long
        let column_width = 15;
        for(const i in imp_session['columns']){

            if(column_width >= 50){
                column_width = 50;
                break;
            }

            column_width = imp_session['columns'][i].length > column_width 
                            ? imp_session['columns'][i].length : column_width;
        }
        column_width += 5;
        $($('.tbmain')[0]).find('th:nth-child(3)').css({
            width: `${column_width}ch`,
            'max-width': `${column_width}ch`
        })

        let idx_id_fieldname = _getFieldIndexForIdentifier(currentSeqIndex);
        
        if (idx_id_fieldname<0) { //id field is not created

            sID_field = '<tr><td width="75px" align="center">&nbsp;'
                    //+ '<input type="checkbox" checked="checked" disabled="disabled"/>'
                    + '</td>'
                    + '<td  width="75px" align="center">0</td>' // count of unique values
                    + `<td style="width:${column_width}ch;max-width:${column_width}ch;class="truncate">${imp_session['sequence'][currentSeqIndex]['field']}</td>` // column name
                    + '<td style="width:300px;">&nbsp;New column to hold Heurist record IDs</td><td>&nbsp;</td></tr>';
        }        

        for (i=0; i < len; i++) {

            let isIDfield = (i==idx_id_fieldname);
            let isIndex =  !window.hWin.HEURIST4.util.isnull(imp_session['indexes']['field_'+i]);
            let isProcessed = !(isIDfield || isIndex || all_mapped.indexOf(i)<0 ); // window.hWin.HEURIST4.util.isnull(mapping_flds[i]) );
            
            //checkbox that marks 'in use'
            let s = '<tr><td width="75px" align="center">&nbsp;<span style="display:none">'
                    +'<input type="checkbox" id="cbsa_dt_'+i+'" value="'+i+'" data-type="'
                    +(isIDfield?'id':(isIndex?'index':(isProcessed?'processed':'remain')))+'"/></span></td>';

            // count of unique values
            s = s + '<td  width="75px" align="center">'+imp_session['uniqcnt'][i]+'</td>';

            // column names
            s += `<td style="width:${column_width}ch;max-width:${column_width}ch;${(isIndex?'color:#b36ae2;"':'')}" class="truncate">${imp_session['columns'][i]}</td>`;

            // mapping selector
            s = s + '<td style="width:300px;">'
                + (isIDfield && !mode_display_separate?'<span style="padding:4px 0px">&lt; Heurist IDs for records being added/updated &gt;</span>':'')
                + '&nbsp;<span style="display:none;">'
                + '<select id="sa_dt_'+i+'" style="width:230px;max-width:230px;font-size:1em;" data-field="'+i+'" '
                //+ ' title="Only matchable fields - text, numeric, date, terms - are shown" '
                + (isIndex||isIDfield?'class="indexes"':'')+'></select>';
            
            // define new field button
            s += (isIndex||isIDfield ? '' : 
                    `<button id="btn_dt_${i}" style="margin-left:5px;padding:2px;" title="Add a new field to the record type">Add field</button>`) + '</span>';

            s = s + '</td>';

            // cell for value
            s = s + '<td id="impval'+i+'" style="text-align: left;padding-left: 16px;" data-isindex="'+(isIndex?1:0)+'">&nbsp;</td></tr>';

            if(isIDfield){
                      sID_field = sID_field +s;
            }else if(isIndex){
                      sIndexes = sIndexes +s;
            }else if(isProcessed){
                      sProcessed = sProcessed +s;
            }else{
                      sRemain = sRemain +s;
            }
            
            if(!isIDfield && !isIndex){
                sAllFields = sAllFields + s;
            }
            
            /*if(!isIDfield){  //variant: put index fields above first mapping key
                if(isIndex){
                    var mf_idx = null;
                    for  (var j=0; j < imp_session['sequence'].length; j++) {
                        var id_idx = _getFieldIndexForIdentifier(j);
                        if(id_idx==i){
                            //get mapping keys
                            if(imp_session['sequence'][j]['mapping_keys']){
                                //take first
                                mf_idx = Object.keys(imp_session['sequence'][j]['mapping_keys'])[0];
                                
                                mf_idx = 'value="'+mf_idx+'"';
                                
                                for  (var k=0; k < sAllFields.length; k++) {
                                    if(sAllFields[k].indexOf(mf_idx)>0){
                                        sAllFields.splice(k, 0, s);
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    if(mf_idx==null){
                        sAllFields.push(s);
                    }
                    
                }else{
                    //for streamlining version
                    sAllFields.push(s);
                }
            }*/
            
        }//for
        
        if(mode_display_separate){
        
            if(sID_field!=''){
                sID_field = '<tr height="40"><td class="subh" colspan="5"><br><b>Heurist ID</b></td></tr>'
                    +sID_field;
            }
            if(sIndexes!=''){
                sIndexes = '<tr height="40"><td class="subh" colspan="5"><br><b>Heurist identifiers (record pointers)</b></td></tr>'
                    +sIndexes;
            }
            if(sRemain!=''){
                sRemain = '<tr height="40"><td class="subh" colspan="5"><br><b>'
                + ((currentStep==3) ?'Matching - not yet used'
                                    :'Not yet Imported')
                +'</b>'
                + ((currentStep==3)
                    ?''
                    :('<span style="font-size:0.7em;font-style:italic"> You only need to map all required fields (red in dropdown) if you plan to create new records</span>'
                     +'<br><br><a href="#" class="lnk_SelectAll_remain" style="font-size:smaller">Select all/none</a>') )
                +'</td></tr>'
                    +sRemain;
            }
            if(sProcessed!=''){
                sProcessed = '<tr height="40"><td class="subh" colspan="5"><br>'
                +'<b>Already used</b>'
                 + ((currentStep==3)?'':'<span style="font-size:0.7em;font-style:italic"> You only need to map all required fields (red in dropdown) if you plan to create new records</span>')
                +'<br><br><a href="#" class="lnk_SelectAll_processed" style="font-size:smaller">Select all/none</a></td></tr>'
                    +sProcessed;
            }
            
            $('#tblFieldMapping > tbody').html(sID_field+sIndexes+sRemain+sProcessed);
        
        }else{
            //without sections and separators
            $('#tblFieldMapping > tbody').html(
                '<tr height="40" class="helper" style="display:none"><td class="subh" colspan="5" style="padding:0 20px">'
                    +'<span style="background:rgba(151, 244, 128, 0.83);display:inline-block;padding:3px;">MATCHING</span> Choose only the fields you need to match &nbsp;&nbsp;'
                    +'<span style="color:green;">Note: ONLY fields suitable for matching are shown in this step.</span>'
                +'</td></tr>'
                +sID_field+sIndexes+sAllFields
                +'<tr height="40" style="border-bottom:1px solid lightgray">'
                + '<td class="subh" colspan="2"><a href="#" class="lnk_SelectAll" style="font-size:smaller">Select all/none</a></td>'
                + '<td class="subh" id="add_new_col" colspan="3" style="border-left: none; padding: 10px 0px;">'
                    + '<input id="txtColName" placeholder="Column name..." size="20">'
                    + '<button id="btnInsertData" style="margin-left: 10px; vertical-align: bottom;">Add</button><br><br>'
                    + '<input id="txtColData" placeholder="Value..." size="30">'
                + '</td>'
                +'</tr>');

            $('#btnInsertData').button({icon: 'ui-icon-plus'});

            $('#btnInsertData').on('click', _insertNewColumn);
            $('#txtColName, #txtColData').on('keyup', (event) => {
                if(event.key == 'Enter' || event.code == 'Enter'){
                    _insertNewColumn();
                }
            });
        }
        
        //init listeners
        $("input[id^='cbsa_dt_']").on('change', function(e){
            let cb = $(e.target);
            let idx = cb.val();//attr('id').substr(8);
            if(cb.is(':checked')){
                $('#sa_dt_'+idx).parent().show();
                //autoMapField(idx);
            }else{
                $('#sa_dt_'+idx).parent().hide();
            }
            
            if(currentStep==5){
                _showStep(4); //reset to prepare step
            }
        });
        
        //init selectors
        _initFieldMapppingSelectors();
        //init 'create field' buttons
        _initCreateFieldButtons();
        //load data
        _getValuesFromImportTable();            

        //
        
        if(sProcessed!=''){
            
            let is_all_checked = true;
            $("input[id^='cbsa_dt_']").each(function(i,item){
               if(!$(item).is(':checked') && $(item).attr('data-type')=='processed'){
                   is_all_checked = false;
                   return false
               }
            });
            
            $('.lnk_SelectAll_processed').attr('data-checked',is_all_checked?1:0)
                .text( is_all_checked?'Select none':'Select all');
        
            $('.lnk_SelectAll_processed').on('click', function(e){
                let cb = $(e.target);
                let was_checked = (cb.attr('data-checked')==1);
                $("input[id^='cbsa_dt_']").each(function(i,item){
                   if($(item).attr('data-type')=='processed')   //!$(item).attr('disabled'))
                        $(item).prop('checked',was_checked?0:1).trigger('change'); 
                });
                cb.attr('data-checked',(was_checked?0:1) );
                cb.text(was_checked?'Select all':'Select none');
            });
        
        }
        if(sRemain!=''){
            
            let is_all_checked = true;
            $("input[id^='cbsa_dt_']").each(function(i,item){
               if(!$(item).is(':checked') && $(item).attr('data-type')=='remain'){
                   is_all_checked = false;
                   return false
               }
            });
            
            $('.lnk_SelectAll_remain').attr('data-checked',is_all_checked?1:0)
                .text( is_all_checked?'Select none':'Select all');
        
            $('.lnk_SelectAll_remain').on('click', function(e){
                let cb = $(e.target);
                let was_checked = (cb.attr('data-checked')==1);
                $("input[id^='cbsa_dt_']").each(function(i,item){
                   if($(item).attr('data-type')=='remain')   //!$(item).attr('disabled')
                        $(item).prop('checked',was_checked?0:1).trigger('change'); 
                });
                cb.attr('data-checked',(was_checked?0:1) );
                cb.text(was_checked?'Select all':'Select none');
            });
        
        }

        let is_all_checked = true;
        $("input[id^='cbsa_dt_']").each(function(i,item){
            let dt = $(item).attr('data-type');
            if(!$(item).is(':checked') && (dt=='remain' || dt=='processed')){
                is_all_checked = false;
                return false
            }
        });

        $('.lnk_SelectAll').attr('data-checked',is_all_checked?1:0)
            .text( is_all_checked?'Select none':'Select all');

        $('.lnk_SelectAll').on('click', function(e){
            let cb = $(e.target);
            let was_checked = (cb.attr('data-checked')==1);
            $("input[id^='cbsa_dt_']").each(function(i,item){
                let dt = $(item).attr('data-type');
                if(dt=='remain' || dt=='processed')
                    $(item).prop('checked',was_checked?0:1).trigger('change'); 
            });
            $('.lnk_SelectAll').attr('data-checked',(was_checked?0:1) );
            $('.lnk_SelectAll').text(was_checked?'Select all':'Select none');
        });
    }

    //
    // _adjustButtonPos
    //    
    function _adjustTablePosition(){
        
        let headerdiv = $('#helper1').parent();
        if(headerdiv.width()<1245){
            $('#helper1').hide();    
        }else{
            $('#helper1').show();    
        }
        if(headerdiv.width()<1000){
            $('#btnClearFile').button({showLabel:false}).css({'width':'auto'})
        }else{
            $('#btnClearFile').button({showLabel:true}).css({'width':'160px'})
        }

        _redrawArrow();
        
        let h = 300
        if(currentStep==3){
            h = 333
        }else if(currentStep==4 || (currentStep==5 && !$('#sa_update').prop('checked')) ){ //+28
            h = 340 //328
        }else if(currentStep==5){ //+100
            h = 430
        }

        $('#divStep3 > .ent_header').height(h);

        $('#divStep3 > .ent_content').css({top:(h+5+'px')});

        let $insert_col = $('#add_new_col');
        if($insert_col.length > 0){
            currentStep >= 3 || currentStep <= 5 ? $insert_col.find('input, button').show() : $insert_col.find('input, button').hide();
        }

        /* todo - check how it works with new layout
        //adjust position of footer with action buttons  
        var content = $('#divFieldMapping');
        var btm = $('#divStep3').height() - (content.position().top + $('#tblFieldMapping').height());
        btm = (btm<80) ?80 :btm;

        content.css('bottom', btm-20);
        $('#divImportActions').height(btm-20);
        */
    }
    
    //
    // by recordtype ID 
    //
    function _getFieldIndexForIdentifier(idx){
        
        if(imp_session['sequence'] && idx>=0 && idx<imp_session['sequence'].length && imp_session['sequence'][idx]){
            let id_fieldname = imp_session['sequence'][idx]['field'];
            let idx_id_fieldname = imp_session['columns'].indexOf(id_fieldname);
            return idx_id_fieldname;
        }else{
            return -1;
        }
    }
    
    //
    // Update detail type dropdown's widget and menu
    //
    function _onRecordDetailMenuOpen(ele_id){

        let $widget = $('span[id="'+ ele_id +'-button"]');
        let $menu = $('ul[id="'+ ele_id +'-menu"]');
        let $menu_parent = $menu.parent();

        $widget.addClass('ui-heurist-populate ui-heurist-header'); // highlight current dropdown
        $menu.addClass('ui-heurist-populate').css('background', '');

        // Get parent containers bottom/end
        let div_h = parseFloat($('#divFieldMapping').css('height'));
        let div_t = parseFloat($('#divFieldMapping').position().top);
        let div_b = div_h+div_t;

        // Get select menu bottom/end
        let menu_h = parseFloat($menu_parent.css('height'));
        let menu_t = parseFloat($menu_parent.position().top);
        let menu_b = menu_h+menu_t;

        let widget_t = parseFloat($widget.position().top) + div_t;
        let menu_mh = parseFloat($menu.css('max-height'));

        if(div_b < menu_b || menu_t < widget_t){ // flip menu to top
            $menu.css('max-height', (widget_t-20)+'px');
            $menu_parent.position({my: 'left-10 bottom', at: 'left top', of: $widget});
        }else if(widget_t < menu_b){ // Add height to menu to bottom of page
            $menu.css('max-height', (menu_mh + (div_b-menu_b-10))+'px');
            $menu_parent.position({my: 'left-10 top', at: 'left bottom', of: $widget});
        }
    }

    function _onRecordDetailMenuClose(ele_id){
        let $widget = $('span[id="'+ ele_id +'-button"]');
        $widget.removeClass('ui-heurist-populate ui-heurist-header'); // remove highlight dropdown
    }

    //
    // Attempt to automatically loosely match fields, excat matches happen earlier
    //
    function _performPartialMatching(file_fields){

        if(file_fields.length == 0){
            return;
        }

        let mapped_fields = [];
        let needs_mapping = {};
        let do_partial_check = false;
        let $selects = $("select[id^='sa_dt_']").filter(function(){

            if($(this).val() != ''){
                mapped_fields.push($(this).val());
                return false;
            }

            let field_idx = $(this).attr('data-field');
            needs_mapping[field_idx] = $(this);

            do_partial_check = true;
            return true; 
        }); // retrieve selects w/ value

        if($selects.length == 0 || !do_partial_check){
            return;
        }

        let rec_results = {};

        $selects.find('option').each(function(i, option){

            let cur_val = $(option).val();
            if(mapped_fields.indexOf(cur_val) >= 0 || isNaN(cur_val) || cur_val < 1){
                return;
            }

            rec_results[cur_val] = {};

            $.each(needs_mapping, function(idx, select){

                let $select = $(select);
                if($select.find('option[value="'+ cur_val +'"]').length != 1 || $select.val() != ''){
                    return;
                }

                let rec_column_parts = option.text.split(' [');
                let rcol_name = '';

                if(rec_column_parts.length > 2){
                    for(let j = 0; j < rec_column_parts.length-1; j++){
                        rcol_name += rec_column_parts[j];
                    }
                }else{
                    rcol_name = rec_column_parts[0].trim();
                }

                if(!rec_results[cur_val][idx]){
                    rec_results[cur_val][idx] = 0;
                }

                let fcol_name = file_fields[idx];

                if(fcol_name.length >= 3 || fcol_name.length == rcol_name.length){
                    // perform partial matching, based on the Sørensen–Dice Coefficient

                    let rcol_bigrams = [];
                    let fcol_bigrams = [];

                    let bigger_len = (rcol_name.length > fcol_name.length) ? rcol_name.length : fcol_name.length;

                    // Create bigrams of both column names
                    for(let i = 0; i < bigger_len; i++){

                        if(i < rcol_name.length){
                            rcol_bigrams.push(rcol_name.substring(i, i + 2).toLowerCase());
                        }
                        if(i < fcol_name.length){
                            fcol_bigrams.push(fcol_name.substring(i, i + 2).toLowerCase());
                        }
                    }

                    let shared_bigrams = rcol_bigrams.filter(bigram => fcol_bigrams.includes(bigram));
                    let sdc_rating = (2 * shared_bigrams.length / (rcol_bigrams.length + fcol_bigrams.length) * 100).toFixed(2);

                    if(sdc_rating >= 50 && sdc_rating > rec_results[cur_val][idx]){
                        rec_results[cur_val][idx] = sdc_rating;
                    }else if(rec_results[cur_val][idx] < 50 && fcol_name.length < rcol_name.length && rcol_name.indexOf(fcol_name) !== -1){ // check if word chunk appears
                        rec_results[cur_val][idx] = 50;
                    }

                    // Check without bracketed text, in case of slight differences in field names
                    if((rcol_name.includes('(') || fcol_name.includes('(')) && rec_results[cur_val][idx] < 90){

                        rcol_name = rcol_name.split('(')[0].trim();
                        fcol_name = fcol_name.split('(')[0].trim();

                        if(rcol_name.toLowerCase() == fcol_name.toLowerCase()){

                            $select.val(cur_val);
                            if($select.hSelect('instance') != undefined){
                                $select.hSelect('refresh');
                            }else{
                                let events = {};
                                events['onOpenMenu'] = function(){
                                    let ele_id = $(this).attr('id');
                                    _onRecordDetailMenuOpen(ele_id);
                                };
                                events['onCloseMenu'] = function(){
                                    let ele_id = $(this).attr('id');
                                    _onRecordDetailMenuClose(ele_id);
                                };

                                window.hWin.HEURIST4.ui.initHSelect($select, false, null, events);
                            }

                            delete rec_results[cur_val];
                            return false;
                        }else if(fcol_name.length >= 4 || fcol_name.length == rcol_name.length){
                            rcol_bigrams = [];
                            fcol_bigrams = [];

                            bigger_len = (rcol_name.length > fcol_name.length) ? rcol_name.length : fcol_name.length;

                            // Create bigrams of both column names
                            for(let i = 0; i < bigger_len; i++){

                                if(i < rcol_name.length){
                                    rcol_bigrams.push(rcol_name.substring(i, i + 2).toLowerCase());
                                }
                                if(i < fcol_name.length){
                                    fcol_bigrams.push(fcol_name.substring(i, i + 2).toLowerCase());
                                }
                            }

                            shared_bigrams = rcol_bigrams.filter(bigram => fcol_bigrams.includes(bigram));
                            sdc_rating = (2 * shared_bigrams.length / (rcol_bigrams.length + fcol_bigrams.length) * 100).toFixed(2);

                            if(sdc_rating >= 50 && sdc_rating > rec_results[cur_val][idx]){
                                rec_results[cur_val][idx] = sdc_rating;
                            }else if(rec_results[cur_val][idx] < 50 && fcol_name.length < rcol_name.length && rcol_name.indexOf(fcol_name) !== -1){ // check if word chunk appears
                                rec_results[cur_val][idx] = 50;
                            }
                        }
                    }
                }
            });
        });

        $.each(rec_results, function(opt_id, field_res){
            let result = Object.entries(field_res); 
            result.sort((a, b) => b[1] - a[1]);

            for (let i = 0; i < result.length; i++) {

                let $ele = $("select[id='sa_dt_"+ result[i][0] +"']");
                if(result[i][1] == 0){ // reached end of useful data
                    return;
                }else if($ele.length == 0 || $ele.val() != ''){ // dropdown has assigned value
                    continue;
                }

                $ele.val(opt_id);
                if($ele.hSelect('instance') != undefined){
                    $ele.hSelect('refresh');
                }else{
                    let events = {};
                    events['onOpenMenu'] = function(){
                        let ele_id = $(this).attr('id');
                        _onRecordDetailMenuOpen(ele_id);
                    };
                    events['onCloseMenu'] = function(){
                        let ele_id = $(this).attr('id');
                        _onRecordDetailMenuClose(ele_id);
                    };

                    window.hWin.HEURIST4.ui.initHSelect($ele, false, null, events);
                }
                return;
            }
        });
    }
    
    //
    // init field mapping selectors after change of rectype
    //
    function _initFieldMapppingSelectors( preset_mapping ){
        
        let sels = $("select[id^='sa_dt_']"); //all selectors
        
        let cbs = $("input[id^='cbsa_dt_']"); //all checkboxes
        cbs.attr('checked', false).attr('disabled', false);
        sels.parent().hide();
        
        //fill selectors with fields of selected record type
        let keyfield_sel = '';
        
        let mapping_flds = null;
        if(currentSeqIndex>=0){

            if(currentStep!=3){
                if(preset_mapping){
                    mapping_flds = preset_mapping;
                }else{
                    mapping_flds = imp_session['sequence'][currentSeqIndex]['mapping_flds'];
                }
            }else{
                _onMatchModeSet();    
            }
            if(!mapping_flds || $.isEmptyObject(mapping_flds)){
                mapping_flds = imp_session['sequence'][currentSeqIndex]['mapping_keys'];
            }
        
            cbs.parent().show(); //show checkboxes
            
            //find key/index field for selected record type
            let idx = _getFieldIndexForIdentifier(currentSeqIndex);
            if(idx>=0){
                    keyfield_sel = 'sa_dt_'+idx;
                    $("#cbsa_dt_"+idx).prop('checked', true).attr('disabled',true);
                    $("#cbsa_dt_"+idx).parent().hide();
                    $('#sa_dt_'+idx).parent().show(); //show selector
            }
            
        }else{
            cbs.parent().hide();
        }

        if(!mapping_flds) mapping_flds = {};

        
        let allowed = Object.keys($Db.baseFieldType);
        allowed.splice(allowed.indexOf("separator"),1);
        //allowed.splice(allowed.indexOf("file"),1);
        allowed.splice(allowed.indexOf("resource"),1);
        //allowed.splice(allowed.indexOf("relmarker"),1);
        if(currentStep==3){ //matching step
            allowed.splice(allowed.indexOf("geo"),1);
        }
        
        let topitems = [{key:'',title:(currentStep==3)?'Matching - select field ...':'select field ...'},{key:'url',title:'Record URL'},{key:'scratchpad',title:'Record Notes'}];
        
        let allowed2 = ['resource'];
        let topitems2 = [{key:'',title:(currentStep==3)?'Matching - select field ...':'select field ...'}]; 

        let exact_matches = [];

        $.each(sels, function (idx, item){
            let $item = $(item);
            if($item.attr('id')==keyfield_sel){
                let selObj = window.hWin.HEURIST4.ui.createSelector(item, [{key:'id',title:'Record ID'}] );  //the only option for current id field
                if(!mode_display_separate){
                    $(selObj).parent().hide();
                }
                selObj = window.hWin.HEURIST4.ui.initHSelect(selObj, false);
            }else{
                
                if(currentStep==3){
                    $(item).attr('title','Only matchable fields - text, numeric, date, terms - are shown');
                }else{
                    $(item).attr('title','');
                }
                
                let field_idx = $(item).attr('data-field');
                let dt_id = mapping_flds[field_idx];
                let selected_value = (!window.hWin.HEURIST4.util.isnull(dt_id))?dt_id:null;
                
                let rtyID = imp_session['sequence'][currentSeqIndex]['rectype'];
                
                let sel = window.hWin.HEURIST4.ui.createRectypeDetailSelect(item, rtyID, 
                    $item.hasClass('indexes')?allowed2:allowed, 
                    $item.hasClass('indexes')?topitems2:topitems,
                    {useHtmlSelect:false, 
                     show_dt_name:true, 
                     show_parent_rt:true, 
                     show_latlong:(currentStep==4), 
                     show_required:(currentStep==4),
                     eventHandlers: {
                        onOpenMenu: function(){
                            let ele_id = $(this).attr('id');
                            _onRecordDetailMenuOpen(ele_id);
                        },
                        onCloseMenu: function(){
                            let ele_id = $(this).attr('id');
                            _onRecordDetailMenuClose(ele_id);
                        }
                     }
                    }
                );

               if(sel.hSelect("instance")!=undefined){
                    sel.hSelect( "menuWidget" ).css({'font-size':'0.9em'});
               }
               
               if(!window.hWin.HEURIST4.util.isnull(selected_value)){
                    $(item).val(dt_id);
                    if(sel.hSelect("instance")!=undefined){
                        sel.hSelect("refresh"); 
                    }
               }else{

                    // Compare fieldnames with column names within CSV data
                    let options = $item.find('option');
                    let fcol_name = imp_session['columns'][field_idx];

                    $.each(options, function(i, option){

                        if(exact_matches.includes(option.value)){ // column has already been exactly matched
                            return true;
                        }

                        let rec_column_parts = option.text.split(' [');
                        let rcol_name = '';

                        if(rec_column_parts.length > 2){
                            for(let j = 0; j < rec_column_parts.length-1; j++){
                                rcol_name += rec_column_parts[j];
                            }
                        }else{
                            rcol_name = rec_column_parts[0];
                        }

                        if(rcol_name == fcol_name || rcol_name.toLowerCase() == fcol_name.toLowerCase()){ // Extact match

                            $item.val(option.value);

                            if(sel.hSelect("instance") != undefined){
                                sel.hSelect("refresh");
                            }

                            exact_matches.push(option.value);

                            return false;
                        }
                    });
               }

               $(sel).on('change', function(){
                    if(currentStep==5){
                        _showStep(4); //reset to prepare step
                    }
               })
                    
            }
        });
        _performPartialMatching(imp_session['columns']);        
        
        //show counts
        if(currentSeqIndex>=0 && 
            !window.hWin.HEURIST4.util.isnull(imp_session['sequence'][currentSeqIndex]['counts'])){
            
            //show counts in count table 
            let counts = _getInsertUpdateCounts( currentSeqIndex );

            $('#mrr_big').html('Existing: ' +counts[0]+'&nbsp;&nbsp;&nbsp;New: '+counts[2]);                                 
            
            $('#mrr_cnt_update').text('');//counts[0]);                                 
            $('#mrr_cnt_insert').text('');//counts[2]);                                 
            $('#mrr_cnt_ignore').text('');//counts[2]);                                 
            if(counts[1]>0){
                $('#mrr_cnt_update_rows').text(counts[1]);                                     
                $('.mrr_update').show();                                     
            }else{
                $('.mrr_update').hide();                                     
            }
            if(counts[3]>0){
                $('#mrr_cnt_insert_rows').text(counts[3]);                                     
                $('.mrr_insert').show();                                     
            }else{
                $('.mrr_insert').hide();                                     
            }
            if(counts[4]>0){
                $('#mrr_cnt_ignore_rows').text(counts[4]);                                     
                $('.mrr_ignore').show();                                     
            }else{
                $('.mrr_ignore').hide();                                     
            }
            
            //show counts in sequence list
            let s_count = _getInsertUpdateCountsForSequence(currentSeqIndex);
            $('#rt_count_'+currentSeqIndex).html(s_count);
        
            //2018-02-01 $('#divFieldMapping2').show();

            
            //show hide skip to next rectype buttons if no insert, only update
            if(counts[2]==0 && counts[0]>0){
                $('.skip_step').css('display','inline-block');                
            }else{
                $('.skip_step').hide();                
            }
            
        }else{
            $('#divFieldMapping2').hide();
            $('.skip_step').hide();
        }
        
    }

    //
    // Allow users to add/define new fields to record structure
    //
    function _initCreateFieldButtons(){

        let $btns = $('button[id^="btn_dt_"]');
        if($btns.length == 0){
            return;
        }

        $btns.button({
            icon: 'ui-icon-plus'
        });

        $btns.find('span.ui-button-icon').css({
            'font-size': '10px',
            width: '10px',
            height: '10px'
        });

        $btns.on('click', (event) => {

            let $btn = $(event.target);
            if($btn.is('span')){
                $btn = $btn.closest('button');
            }

            let fld_id = $btn.attr('id');
            fld_id = fld_id.replace('btn', 'sa');

            let $sel = $(`select[id^="${fld_id}"]`);
            if($sel.length == 0){
                return;
            }

            let fld_idx = fld_id.split('_').pop();

            let rty_ID = imp_session['sequence'][currentSeqIndex]['rectype'];

            let options = {
                select_mode: 'manager',
                edit_mode: 'editonly',
                rec_ID: -1,
                title: `Define new field for ${$Db.rty(rty_ID,'rty_Name')}`,
                newFieldForRtyID: rty_ID,
                selectOnSave: true,
                newFieldName: imp_session['columns'][fld_idx],
                onselect: (event, res) => {

                    if(!res || (!res.selection && !res.updatedRstField)){
                        return;
                    }

                    let recset = $Db.rst(rty_ID);
                    recset.sort({'rst_DisplayOrder': -1});
                    let last_rec = recset.getFirstRecord();

                    let order = parseInt(last_rec.rst_DisplayOrder) + 1

                    let dty_ID = res.selection ? res.selection[0] : res.updatedRstField;
                    let dty = $Db.dty(dty_ID);
                    let record = {
                        rst_ID: dty_ID,
                        rst_RecTypeID: rty_ID,
                        rst_DisplayOrder: String(order).padStart(3, '0'),
                        rst_DetailTypeID: dty_ID,
                        rst_DisplayName: dty['dty_Name'],
                        rst_DisplayHelpText: dty['dty_HelpText'],
                        rst_DisplayExtendedDescription: dty['dty_ExtendedDescription']
                    };
                    if(res.rst_fields){
                        record = $.extend(record, res.rst_fields);
                    }else{
                        record['rst_MaxValues'] = 1;
                        record['rst_RequirementType'] = 'optional';
                        record['rst_DisplayWidth'] = '0';
                    }

                    let request = {
                        'a': 'save',
                        'entity': 'defRecStructure',
                        'request_id': window.hWin.HEURIST4.util.random(),
                        'fields': record,
                        'isfull': false
                    };

                    window.hWin.HAPI4.EntityMgr.doRequest(request, (response) => {

                        if(response.status != window.hWin.ResponseStatus.OK){
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                            return;
                        }

                        // update cached record
                        let rst_ID = response.data[0];
                        if(rst_ID>0){

                            record['rst_ID'] = String(rst_ID);
                            $Db.rst(rty_ID).addRecord(rst_ID, record);

                            // Set field selection to new field
                            if(!imp_session['sequence'][currentSeqIndex]['mapping_flds']){
                                imp_session['sequence'][currentSeqIndex]['mapping_flds'] = {};
                            }
                            let fld_index = fld_id.replace('sa_dt_', '');
                            imp_session['sequence'][currentSeqIndex]['mapping_flds'][fld_index] = rst_ID;

                            let $checked_boxes = $("input[id^='cbsa_dt_']:checked"); // save checked boxes
                            _initFieldMapppingSelectors(); // recreate selects

                            $checked_boxes.prop('checked', true).trigger('change'); // re-check boxes, they get reset by the above recreate
                        }
                    });
                }
            };

            window.hWin.HEURIST4.ui.showEntityDialog('defDetailTypes', options);
        });
    }
    
    //
    // show insert/update count in sequence of record types
    //
    function _getInsertUpdateCountsForSequence(idx){
    
         let counts = _getInsertUpdateCounts(idx);                                     
         let s_count = '';
         if(counts[2]!=imp_session['reccount']){  //record to be inserted
            
             s_count = '<span style="font-size:0.8em"> [ '
             + ((counts[0]>0)?'<span title="Records matched">Matched='+counts[0]+'</span> ':'')
             + (counts[2]>0? ((counts[0]>0?', ':'')+'<span title="New records to create">New='+counts[2]+'</span>'):'')
             + (counts[4]>0? (((counts[0]>0||counts[2]>0)?', ':'')+'<span title="Blank match fields">Ignore='+counts[4]+'</span>'):'')
             + ']</span>';
         }
         return s_count;
    }
    
    //
    // return counts array from imp_session, set it to default values if it is not defined
    //
    // 0 records to be updated
    // 1 rows matched
    // 2 records to be inserted
    // 3 rows source for insert 
    // 4 rows ignored
    function _getInsertUpdateCounts(idx){
        
        if(idx>=0 && idx<imp_session['sequence'].length){
            let counts = imp_session['sequence'][idx]['counts'];
            
            if(!counts) {
                counts = [0,0,0,0,0];
                //reccount - total records in import table
                //uniqcnt - unique values per column
                idx = _getFieldIndexForIdentifier(idx); 
                if(idx>=0){
                    //id column already exists in import table
                    counts[2] = counts[3] = imp_session['uniqcnt'][idx];
                }else{
                    //all to be inserted
                    counts[2] = counts[3] = imp_session['reccount'];
                }
            }
            return counts;
        }else{
            return null;
        }
    }
    
    //
    // Loads values for record from import table (preview values in main table)
    //
    function _getValuesFromImportTable(event){
        
        if(!imp_session) return;
        
        let currentTable = imp_session['import_table']; 
        let recCount     = Number(imp_session['reccount']); 
        
        
        if(currentTable && recCount>0){
            
            let dest = 0;
            if(event){
                dest = $(event.target).attr('data-dest');  
            } 
            
            if(Number(dest)==0){
                currentId=1;
            }else if(dest=='last'){
                currentId = recCount;
            }else{
                dest = Number(dest);
                if(isNaN(dest)){
                    currentId = 1;
                }else{
                    currentId = currentId + dest;
                }
            }
            if(currentId<1) {
                currentId=1;
            }else if (currentId>recCount){
                currentId = recCount;
            }
            
            
            let request = { action: 'records',
                            imp_ID: currentId,
                            table:currentTable,
                            id: window.hWin.HEURIST4.util.random()
                               };
            
            window.hWin.HAPI4.doImportAction(request, function( response ){
                
                //that.loadanimation(false);
                if(response.status == window.hWin.ResponseStatus.OK){
                
                    response = response.data;
                
                    $("#current_row").html(response[0]);

                    for(let i=1; i<response.length;i++){
                        let sval;
                        if(window.hWin.HEURIST4.util.isnull(response[i])){
                            sval = "&nbsp;";
                        }else{

                            let idx_id_fieldname = _getFieldIndexForIdentifier(currentSeqIndex);
                            
                            let isIndex =  (idx_id_fieldname==(i-1)) || !window.hWin.HEURIST4.util.isnull(imp_session['indexes']['field_'+(i-1)]);
                            
                            sval = response[i].substr(0,100);

                            if(isIndex && response[i]<0){
                                sval = "&lt;New Record&gt;";
                            }else if(sval==""){
                                sval = "&nbsp;";
                            }else if(response[i].length>100){ //add ... 
                                sval = sval + '&#8230;';
                            }
                        }

                        if($("#impval"+(i-1)).length>0){

                            if($("#impval"+(i-1)).attr('data-isindex')==1){
                                sval = '<span class="ui-icon ui-icon-arrowthick-1-e" style="color:#b36ae2"></span>&nbsp;' + sval;
                            }
                            $("#impval"+(i-1)).html(sval);
                        }
                    }

                }else{
                    _showStep(1);
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }

            });        

        }
        
        _adjustTablePosition();
        
        return false;
    }
    
    //
    // upload data that was pasted in textarea
    //
    function _uploadData(){
        
        let csvdata = $('#sourceContent').val();
        
        if(csvdata){
            
            _showStep(0);
        
            let request = { action: 'step0',
                              data: csvdata,
                                id: window.hWin.HEURIST4.util.random()
                               };
            window.hWin.HAPI4.doImportAction(request, function( response ){
                
                //that.loadanimation(false);
                if(response.status == window.hWin.ResponseStatus.OK){
                
                    upload_file_name = response.data.filename; //filename only
                    $('#csv_encoding').val('UTF-8');
                    window.hWin.HEURIST4.util.setDisabled($('#csv_encoding'), true);
                    _showStep(2);
                    
                    
                    window.hWin.HEURIST4.util.setDisabled( $('#btnParseStep2'), true );
                    $('#divFieldRolesHeader').hide();
                    $('#divParsePreview').empty();
                    $('#divFieldRoles > .tbfields > tbody').empty();
                    $('#divFieldRoles').hide();
                    $('#lblParseStep2').hide();
                    
                    //_doParse(1);
                    
                }else{
                    _showStep(1);
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }

            });        
        
        }else{
            window.hWin.HEURIST4.msg.showMsgErr({
                message: 'Please paste comma or tab-separated data into the content area below',
                error_title: 'No data to import'
            });
        }
    }
    
    //
    // parse CSV on server side
    // step1 - encode and get preview
    // step2 - field roles, prepare and save into file 
    //
    function _doParse(step){
        /*    
        keyfield
        datefield
        memofield
        */
        
                _showStep(0);
                
                let encoding = $('#csv_encoding').val();
                if(encoding=='auto detect'){
                    encoding = null;
                }
        
                let request = { action: step==2?'step2':'step1',
                                csv_delimiter: $('#csv_delimiter').val(),
                                csv_linebreak: $('#csv_linebreak').val(),
                                csv_enclosure: $('#csv_enclosure').val(),
                                csv_mvsep: $('#csv_mvsep').val(),
                                csv_dateformat: $('#csv_dateformat').val(),
                                csv_encoding: encoding,
                                id: window.hWin.HEURIST4.util.random()
                               };

                let container  = $('#divParsePreview');
                let container2 = $('#divFieldRoles');
                               
                if(step==1){
                    request['upload_file_name'] = upload_file_name; //filename only
                    
                    encoded_file_name_id = '';
                    
                    window.hWin.HEURIST4.util.setDisabled( $('#btnParseStep2'), true );
                    $('#divFieldRolesHeader').hide();
                    container.empty();
                    container2.find('.tbfields > tbody').empty();
                    container2.hide();
                    $('#lblParseStep2').hide();
                }else{
                    request['encoded_filename_id'] = encoded_file_name_id; //full path
                    request['original_filename'] = upload_file_name; //filename only
                    request['keyfield'] = {};
                    request['datefield'] = [];
                    let ele = $.find("input[id^='id_field_']");
                    $.each(ele, function(idx, item){
                            if($(item).is(':checked')){
                                let rectypeid = $('#id_rectype_'+item.value).val();
                                if(!rectypeid){
                                    rectypeid = 0;
                                }
                                request['keyfield']['field_'+item.value] = rectypeid;
                            }
                    }); 
                    
                    ele = $.find("input[id^='d_field_']");
                    $.each(ele, function(idx,item){
                            if($(item).is(':checked')){
                                request['datefield'][item.value] = 1;
                            }
                    }); 
                }               

                window.hWin.HAPI4.doImportAction(request, function( response ){
                    
                    _showStep(2);                    
                    //that.loadanimation(false);
                    if(response.status == window.hWin.ResponseStatus.OK){


                        //something went wrong - index fields have wrong values, problem with encoding or column number mismatch
                        let container3 = container.find('#error_messages');
                        if(container3.length==0){
                            container3 = $('<div>',{id:'error_messages'}).appendTo(container);
                        }else{
                            container3.empty();
                        }
                       
                        if(typeof response.data === "string"){
                            $( window.hWin.HEURIST4.msg.createAlertDiv(response.data)).appendTo(container3);
                            return;
                        }
                        
                        
//{filename,col_count,errors:  , err_encoding: , fields:, values:}                            
//errors:  "cnt"=>count($fields), "no"=>$line_no, "line"
//err_encoding: "no"=>$line_no, "line"
                        let tbl,i,j,
                            haveErrors = false;


                        if(window.hWin.HEURIST4.util.isArrayNotEmpty(response.data['err_colnums'])){

                            let msg = 'Wrong field count in parsed data. Expected field count '
                                        + '(determined by the first line of the file) = '
                                        + response.data['col_count']
                                        //+ '. Either change parse parameters or correct source data'
+'. Check that the field and line separators have been correctly set, then click Analyse data again';

                            $( window.hWin.HEURIST4.msg.createAlertDiv(msg)).appendTo(container3);

                            tbl  = $('<table><tr><th>Line#</th><th>Field count</th><th>Raw data</th></tr>')
                                    .addClass('tbpreview')
                                    .appendTo(container3);
                                    
                            for(let i in response.data.err_colnums){

                                $('<tr><td>'+response.data.err_colnums[i]['no']+'</td>'
                                    +'<td>'+response.data.err_colnums[i]['cnt']+'</td>'
                                    +'<td>'+response.data.err_colnums[i]['line']+'</td></tr>').appendTo(tbl);
                            }         
                            $('<hr>').appendTo(container3);
                            haveErrors = true;
                        }
                        if(window.hWin.HEURIST4.util.isArrayNotEmpty(response.data['err_encoding'])){

                            let msg = 'Your file can\'t be converted to UTF-8. '
                            +'Please open it in a text editor such as Notepad++ and save with UTF-8 text encoding.<br>'
                            +response.data['err_encoding_count']+' lines have such issue';
                                
                            $( window.hWin.HEURIST4.msg.createAlertDiv(msg)).appendTo(container3);

                            tbl  = $('<table><tr><th>Line#</th><th>Raw data</th></tr>')
                                    .addClass('tbpreview')
                                    .appendTo(container3);
                                    
                            for(let i in response.data.err_encoding){
                                $('<tr><td>'+response.data.err_encoding[i]['no']+'</td>'
                                    +'<td>'+response.data.err_encoding[i]['line']+'</td></tr>').appendTo(tbl);
                            }         
                            $('<hr>').appendTo(container3);
                            haveErrors = true;
                        }
                        
                        if(response.data['err_keyfields']){
                            let keyfields = Object.keys( response.data['err_keyfields'] );
                            if(keyfields.length>0){

                                let msg = 'Field you marked as identifier contain wrong or out of range values. Table below shows the number of wrong values';
                                let many_values = false;

                                tbl  = $('<table class="tberror"><tr><th>Field</th><th align=center>Non integer values</th><th align=center>Out of range</th></tr>')
                                    .addClass('tbpreview')
                                    .appendTo(container3);
                                
                                for (i=0;i<keyfields.length;i++){
                                    
                                    let issues = response.data['err_keyfields'][keyfields[i]];
                                    let non_int_issue = [];
                                    let range_issue = [];

                                    if(window.hWin.HEURIST4.util.isArrayNotEmpty(issues[0])){
                                        non_int_issue = issues[0];
                                    }else if($.isPlainObject(issues[0])){
                                        non_int_issue = Object.values(issues[0]);
                                    }
                                    if(non_int_issue.length > 20){
                                        non_int_issue.splice(20, non_int_issue.length);
                                        many_values = true;
                                    }

                                    if(window.hWin.HEURIST4.util.isArrayNotEmpty(issues[1])){
                                        range_issue = issues[1];
                                    }else if($.isPlainObject(issues[1])){
                                        range_issue = Object.values(issues[1]);
                                    }
                                    if(range_issue.length > 20){
                                        range_issue.splice(20, range_issue.length);
                                        many_values = true;
                                    }

                                    $('<tr><td>'+window.hWin.HEURIST4.util.htmlEscape(response.data['fields'][keyfields[i]])+'</td>'
                                        +'<td>'+non_int_issue.join(' ; ')+'</td><td>'+range_issue.join(' ; ')+'</td></tr>').appendTo(tbl);
                                }

                                if(many_values){
                                    msg += ' (showing first 20 values only)';
                                }

                                $( window.hWin.HEURIST4.msg.createAlertDiv(msg)).insertBefore(tbl);
                            }
                            $('<hr>').appendTo(container3);
                            haveErrors = true;
                        }

                        
                        window.hWin.HEURIST4.util.setDisabled( $('#btnParseStep2'), haveErrors);
                        if(haveErrors){
                                $('#divFieldRolesHeader').hide();
                                $('#lblParseStep2').hide();
                        }else{
                                $('#divFieldRolesHeader').show();
                                container2.show();
                        }

                        //preview parser
                        if(response.data.step==1 && response.data.values){
                                
                            encoded_file_name_id = response.data.encoded_filename_id;
                                      
                            $('<h2 style="margin:10px">'+window.hWin.HR('Parse results.')
                                    +(response.data.values.length<1000?'':' First 1000 rows')
                                    +'</h2>').appendTo(container);

                            $('<span style="display: inline-block; padding-left: 10px; font-size: 1.1em;">'
                                + 'Should show cleanly rendered data in a table with column names in first row.<br>If not, try changing choice of separator characters and encoding.'
                            +'</span>').appendTo(container);

                            let no_values = response.data.values.length == 0;
                            let give_column_warning = false;

                            if(!no_values && response.data.fields.length == 1){

                                $('#csv_delimiter option').each((idx, option) => {
                                    if($('#csv_delimiter').val() == option.value){
                                        return;
                                    }
                                    let sep = option.value;
                                    if(sep == 'tab'){
                                        sep = "\t";
                                    }

                                    if(response.data.fields[0].indexOf(sep) > 0){
                                        give_column_warning = true;
                                        return false;
                                    }
                                });
                            }

                            if(no_values){
                                window.hWin.HEURIST4.msg.showMsgErr({
                                    message: 'No values were detected.<br>'
                                            +'Please select a different field separator or setting line separator to "auto detect", if not already set so, and hit Analyse data again.',
                                    error_title: 'No values to import'
                                });
                            }else if(give_column_warning){
                                window.hWin.HEURIST4.msg.showMsgErr({
                                    message: 'Only one column was detected.<br>'
                                            +'Please select a different field separator or setting line separator to "auto detect", if not already set so, and hit Analyse data again.',
                                    error_title: 'No fields found'
                                });
                            }
                            tbl  = $('<table>').addClass('tbpreview').appendTo(container);
                            
                            let _parseddata = response.data.values;
                            let maxcol = 0;
                            
                            let tr  = '<tr>'
                            for(let i in response.data.fields){
                                tr = tr+'<th>'+response.data.fields[i]+'</th>';
                            }                   
                            tr  = tr+'</tr>';     
                            $(tr).appendTo(tbl);
                            for(let i in response.data.values){
                                
                                if(window.hWin.HEURIST4.util.isArrayNotEmpty(_parseddata[i])){
                                    tr  = '<tr>';
                                    for(let j in _parseddata[i]){
                                        tr = tr+'<td>'+window.hWin.HEURIST4.util.htmlEscape(_parseddata[i][j])+'</td>';
                                    }
                                    tr  = tr+'</tr>';
                                    maxcol = Math.max(maxcol,_parseddata[i].length);
                                    $(tr).appendTo(tbl);
                                }
                            }
                            
                            
                            //<tr><th>Column</th><th>Is date?</th><th>Is key?</th><th>For record type</th></tr>
                            //add header for list of columns
                            tbl = container2.find('.tbfields > tbody');
                            tbl.empty();
                            //tbl  = $('<table>').addClass('tbfields').appendTo(container2);
                            
                            let id_suggestions = []; 
                            //fill list of columns
                            for(let i in response.data.fields){
                                
                                if(response.data.fields[i] && response.data.fields[i].indexOf('H-ID')>=0 || 
                                response.data.fields[i].toLowerCase().indexOf('identifier')>=0){
                                    id_suggestions.push(i);
                                }
                                
                                let stype = 'Text';
                                if(response.data.int_fields && response.data.int_fields[i]){
                                   stype = 'Integer'; 
                                }else if (response.data.num_fields && response.data.num_fields[i]){
                                   stype = 'Numeric';
                                }else if (response.data.empty_fields && response.data.empty_fields[i]){
                                   stype = 'Empty';
                                }else if (response.data.empty75_fields && response.data.empty75_fields[i]){
                                   stype = 'Empty &gt;75%';
                                }
                                
                                //since 2018-03-01 only H-ID fields can be identifiers
                                let is_id_field = (response.data.fields[i].indexOf('H-ID')>=0);
                                
                                $('<tr><td style="min-width:150px">'+response.data.fields[i]+'</td>'
                                +'<td style="width:50px">'+stype+'</td>'
                                +'<td style="min-width:50px;text-align:center">'
                                    +(is_id_field?'<input type="checkbox" id="id_field_'+i+'" value="'+i
                                            +'" checked disabled/>':'-')+'</td>'
                                +'<td style="min-width:50px;text-align:center" class="date-column">'
                                    //date field
                                    +(is_id_field?'':'<input type="checkbox" id="d_field_'+i+'" value="'+i+'"/>')+'</td>' 
                                +'<td style="width:200px">'
                                +(is_id_field?('<select id="id_rectype_'+i
                                    +'" class="text ui-widget-content ui-corner-all"></select>'):'')
                                +'</td></tr>').appendTo(tbl);
                            }         
                            
                            if(exp_level>0){
                                container2.find('.tbfields .date-column').hide();                                    
                            }
                            
                            
                            function __isAllRectypesSelectedForIdFields(){
                                    
                                    let res = false;
                                    let ele = $.find("input[id^='id_field_']");
                                    $.each(ele, function(idx, item){
                                        
                                            if($(item).is(':checked')){
                                                let rectypeid = $('#id_rectype_'+item.value).val();
                                                if(!rectypeid){
                                                    res = true;
                                                    return false;
                                                }
                                            }
                                    }); 
                                    return res;                                    
                            }
                            
                            let select_rectype = $("select[id^='id_rectype']");
                            $.each(select_rectype, function(idx, item){
                                let sel = window.hWin.HEURIST4.ui.createRectypeSelect( item, null, 'select...', false);    
                                sel.on('change', function(evt){
                                    let is_disabled = __isAllRectypesSelectedForIdFields();
                                    if(is_disabled){ $('#lblParseStep2').show(); } else $('#lblParseStep2').hide();
                                    window.hWin.HEURIST4.util.setDisabled( $('#btnParseStep2'), is_disabled );
                                });                                
                            });

                            
                            $("input[id^='d_field']").on('change', function(evt){
                                let cb = $(evt.target); 
                                window.hWin.HEURIST4.util.setDisabled( $('#id_field_'+cb.val()), cb.is(':checked') );
                            });
                            
                            $("input[id^='id_field']").on('change', function(evt){
                                let cb = $(evt.target);
                                cb.prop('checked',true);

                                $('#divFieldRolesHeader').show();

                                window.hWin.HEURIST4.util.setDisabled( $('#d_field_'+cb.val()), cb.is(':checked') );
                                
                                //$("select[id='id_rectype_"+ cb.val()+"']").hSelect(cb.is(':checked')?'show':'hide');
                                //.css('visibility',  cb.is(':checked')?'visible':'hidden');            
                            
                                let is_visible = $("input[id^='id_field']:checked").length>0;
                            
                                if(is_visible){$('#lbl_ID_select').show();}else{$('#lbl_ID_select').hide();}
                            
                                let is_disabled = __isAllRectypesSelectedForIdFields();
                                if(is_disabled){ $('#lblParseStep2').show(); } else $('#lblParseStep2').hide();
                                window.hWin.HEURIST4.util.setDisabled( $('#btnParseStep2'), is_disabled );
                            });
                            
                            for(let i=0; i<id_suggestions.length; i++){
                                $('#id_field_'+id_suggestions[i]).prop('checked',true);
                                $('#id_field_'+id_suggestions[i]).trigger('change');
                            }
                            
                        }else if(response.data.import_id>0){
                            
                            imp_ID = response.data.import_id;      
                            if($('#selImportId > option').length<1){
                                window.hWin.HEURIST4.ui.addoption($('#selImportId').get(0), null, window.hWin.HR('select uploaded file...'));    
                            }
                            window.hWin.HEURIST4.ui.addoption($('#selImportId').get(0), imp_ID, response.data.import_name);
                            session_selector.hSelect("refresh");
                            window.hWin.HEURIST4.util.setDisabled($('#btnClearAllSessions'), false );
                            _loadSession();    
                        }
                        
                    }else{
                        if(step==1 && response.status=='unknown' && response.message=='Error_Connection_Reset'){
                            window.hWin.HEURIST4.msg.showMsgErr({
                                message: 'It appears that your file is not in UTF8. Please select correct encoding',
                                error_title: 'Invalid file encoding',
                                status: window.hWin.ResponseStatus.INVALID_REQUEST
                            });
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    }

                });
    }
    
    //
    // Show remarks/help according to current match mode and mapping in main table
    //
    function _onMatchModeSet(){
        
        let shelp = '';
        
        $('#divFieldMapping2').hide();
        
        if(currentSeqIndex>=0 && imp_session['sequence'][currentSeqIndex]){
            let key_idx = _getFieldIndexForIdentifier(currentSeqIndex); 
            
            let rtyID = imp_session['sequence'][currentSeqIndex]['rectype'];
            
            if(key_idx<0){ //key field not defined
                if($('#sa_match1').is(':checked')) $('#sa_match0').prop('checked', true);
                
                //$('label[for="sa_match1"]').css('color','lightgray');
                $('#sa_match1').hide();
                $('label[for="sa_match1"]').hide();
            }else{
                $('#sa_match1').show();
                $('label[for="sa_match1"]').show();
                $('#lbl_sa_match1').html('Use <i>'+imp_session['columns'][key_idx]+'</i>'); //Skip matching
                
                //$('label[for="sa_match1"]').css('color','');
                
            }
            
            if($('#sa_match0').is(':checked')){ // normal matching
                
                 shelp = 'Please select one or more columns on which to match <b>'
                 + $Db.rty(rtyID,'rty_Name')
    + '</b> in the incoming data against records already in the database.'
    + '<br>Note: do not use columns containing multiple values for matching, as this will generate multiple records per input line.'
    + '<br>To force creation of all new records, select columns and map to a field which will never match<br><br>';
                 
                if(key_idx>=0){
                    shelp = shelp + 'The existing identification field "'+imp_session['columns'][key_idx]+'" will be overwritten.' 
                }else{
                    shelp = shelp + 'New identification field will be created.' 
                }

                shelp = shelp + ' Matching sets this ID field for existing records and allows '
                    +'the creation of new records for unmatched rows.';
                
            }else if($('#sa_match1').is(':checked')){ // use id field
                
                shelp = 'The existing identification field "'+imp_session['columns'][key_idx]+'" will be used.<br><br>';
                
                let counts = _getInsertUpdateCounts( currentSeqIndex );
                
                if(counts && counts[1]>0){
                    
                     shelp = shelp + 'Data (n = '+counts[1]
                        + ' rows) will be assigned to the existing record where the Heurist ID field ('
                        + imp_session['columns'][key_idx]+') is set (n = '+counts[0]
                        + ' records).';
                     if(counts[2]>0){
                        shelp = shelp 
                            + ' A new record will be created where the Heurist ID field is not set (n = '
                            + counts[2]+' records)';                    
                     }
                     /*old version of message
                       shelp = shelp + 'It appears that you already have <b>'
                            + $Db.rty(rtyID,'rty_Name')
                            + '</b>. '+counts[1]+' rows in import table that match for '
                            + (counts[0]!=counts[1]?counts[0]:'')+' existing records';
                      if(counts[2]>0){
                            shelp = shelp + ' and '+counts[2]+' records will be added';       
                      }
                      shelp = shelp + '.';   
                      */
                }else{
                
                    shelp = shelp + 'It does not match any <b>'
                            +$Db.rty(rtyID,'rty_Name')+'</b> record, hence '
                                +(key_idx>=0 && imp_session['uniqcnt'][key_idx]>0
                                        ?imp_session['uniqcnt'][key_idx]:imp_session['reccount'])
                                +' records will be added.';
                    
                }
                
            }else if($('#sa_match2').is(':checked')){  //skip matching

                shelp = 'By choosing not to match the incoming data, you will create '
                    +imp_session['reccount']+' new <b>'
                    +$Db.rty(rtyID,'rty_Name')+'</b> records - that is one record for every row in import file?<br><br>';

                if(key_idx>=0){
                    shelp = shelp + ' The identification field "'+imp_session['columns'][key_idx]+'" will be filled with new record IDs.' 
                }
                
            }
            
            if($('#sa_match2').is(':checked')){  //skip matching
                $('#btnMatchingStart').button({label:'Import all as new'}); //Skip matching
            }else if($('#sa_match1').is(':checked')){  //use id column
                $('#btnMatchingStart').button({label:'Use <i>'+imp_session['columns'][key_idx]+'</i>'}); //Skip matching
            }else{
                $('#btnMatchingStart').button({label:'Match against existing records'});
            }
        
            if($('#sa_match0').is(':checked')){ // normal matching
                $("input[id^='cbsa_dt_']").show();
                $("select[id^='sa_dt_']").css('visibility','visible');
                $('tr.helper').show();//#divFieldRoles > .tbfields > tbody > 
            }else{
                $("input[id^='cbsa_dt_']").hide();
                $("select[id^='sa_dt_']").css('visibility','hidden');
                $('tr.helper').hide();
            }
        
        
        }
        $('#divMatchingSettingHelp').html(shelp);
        
        
    }
 
    //
    //
    //
    function _doMatchingInit(){
        
        let matchMode = 0;    

        if($('#sa_match2').is(':checked')){
            matchMode = 2;    
        }else if($('#sa_match1').is(':checked')){
            matchMode = 1;    
        }
        
        _doMatching( matchMode, null );
    }
        
    //
    // Find records in Heurist database and assign record id to identifier field in import table
    //
    // matchMode - 0 - match by mapped fields, 1- match by id column, 2 - skip matching
    // disamb_resolv -  [{recid: heurist record id,key: import_id},....]
    //
    function _doMatching( matchMode, disamb_resolv ){
        
        if(currentSeqIndex>=0){
        
            let sWarning = '';
            let haveMapping = false; 
            //do we have id field?
            let key_idx = _getFieldIndexForIdentifier(currentSeqIndex); 
            //do we have field to match?
            let field_mapping = {};
            let multifields = []; //indexes of multivalue fields
            
            if(matchMode==0){ //find mapped fields
                let ele = $("input[id^='cbsa_dt_']");
                
                $.each(ele, function(idx, item){
                    item = $(item);
                    if(item.is(':checked')){ // && item.val()!=key_idx){
                        let field_type_id = $('#sa_dt_'+item.val()).val();
                        let field_idx = Number(item.val());
                        if(field_type_id && field_idx!=key_idx){ //except id field
                            field_mapping[field_idx] = field_type_id;
                            haveMapping = true;
                            
                            if(imp_session['multivals'].indexOf(field_idx)>=0){
                                multifields.push(field_idx);    
                            }
                        }
                    }
                });
            }
            
            if(matchMode==0 && !haveMapping){
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: 'Please select the fields on which you wish to match the data read '
                            +'with records already in the database (if any)',
                    error_title: 'No fields selected for matching'
                });
                return;
            }

            //detect multivalue field among mapped fields - we may allow the ONLY multivalue field for matching
            //otherwise we can't search exactly
            if(multifields.length>1){
                //imp_session['columns'][key_idx]
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: 'It is possible to select the only field with multivalues (separated with '
                            +`${imp_session['csv_mvsep']}). You selected ${multifields.length}  multivalue fields`,
                    error_title: 'Multiple multivalue fields selected'
                });
                return;
            }
            
                //verify setting
                // id is defined
                // mapping defined -> values in id field will be overwritten
                //         not defined -> use value in id field
                // id is not defined
                // mapping defined ->  new field will be created
                //         not defined -> import as new     
            
                if(key_idx>=0){
                    if(haveMapping){
                        if(disamb_resolv==null){
                        sWarning = 'You chose to match the incoming data and selected the existing identification field "'
                                +imp_session['columns'][key_idx]
                                +'".<br>This means it will be filled with new values.<br><br>';
                        }
                    }else{
//@todo here and on server side
// match against id field, set to -1 if not found ???                       
                        /*
                        sWarning = '<h3>WARNING</h3>'
     + (isSkipMatch?'':'You have not selected any colulm to field mappping<br><br>')                   
     +'By choosing not to match the incoming data, you will create '
     + 'XYZ(TODO!!!!)' //imp_session['uniqcnt'][key_idx]
     +' new records - that is one record for every unique value in identification field.<br><br>Proceed without matching?';
     */
     //+'If a value is repeated in many rows of your input data it will create multiple rows even though you are using it as the key field';     
                    }
                }else{
                    if(haveMapping){
                        sWarning = '';
                    }else{
                        sWarning = '<h3>WARNING</h3>'
     + (matchMode==2?'':'You have not selected any colulm to field mappping<br><br>')                   
     +'By choosing not to match the incoming data, you will create '
     +imp_session['reccount']+' new records - that is one record for every row.<br><br>Proceed without matching?';
                    }
                }
            
            __doMatchingStart();
            

       function __doMatchingStart(){    
         
           
            let session_id = window.hWin.HEURIST4.msg.showProgress( {container:$('#progressbar_div'), interval:100, content:false});
            
            let handle_rectype = $('#ignore_rectype').is(':checked') ? '1' : '0';
           
            let request = {
                session   : session_id,
                imp_ID    : imp_ID,
                action    : 'step3',
                sa_rectype: imp_session['sequence'][currentSeqIndex]['rectype'],
                seq_index: currentSeqIndex,
                mapping   : field_mapping,  //index => field_type
                match_mode: matchMode,  //0 - mapping, 1 - id, 2 - skip match 
                ignore_rectype: handle_rectype
            };
            if(multifields.length>0){
                request['multifield'] = multifields[0];  //get index of multivalue field
            }
            if(key_idx>=0){
                request['idfield']=imp_session['columns'][key_idx]; //key_idx;            
            }
            if(disamb_resolv!=null){
                request['disamb_resolv'] = JSON.stringify( disamb_resolv );          
            }
            
            
            _showStep(0);
        
            window.hWin.HAPI4.doImportAction(request, 
                    function(response){
                        window.hWin.HEURIST4.msg.hideProgress();

                        if(response.status == window.hWin.ResponseStatus.OK){
                            
                            _showStep(3);
                            
                            imp_session = response.data; //assign to global var

                            let res = imp_session['validation'];
                            
                            $('#mr_cnt_update').text(res['count_update']);                                 
                            $('#mr_cnt_insert').text(res['count_insert']);                                 
                            if(res['count_update_rows']>0){
                                $('#mr_cnt_update_rows').text(res['count_update_rows']);                                     
                                $('.mr_update').show();                                     
                            }else{
                                $('.mr_update').hide();                                     
                            }
                            if(res['count_insert_rows']>0){
                                $('#mr_cnt_insert_rows').text(res['count_insert_rows']);                                     
                                $('.mr_insert').show();                                     
                            }else{
                                $('.mr_insert').hide();                                     
                            }
                            
                            if(res['count_ignore_rows']>0){
                                $('#mr_cnt_ignore_rows').text(res['count_ignore_rows']);                                     
                                $('.mr_ignore').show();                                     
                            }else{
                                $('.mr_ignore').hide();                                     
                            }
                            
                            let disambig_keys = Object.keys(res['disambiguation']);
                            
                            if(disambig_keys.length>0){
                                //imp_session = (typeof ses == "string") ? JSON.parse(ses) : null;
                                /*
                                $('#mr_cnt_disamb').text(disambig_keys.length);                                 
                                $('#mr_cnt_disamb').parent().show();
                                */
                                
                                window.hWin.HEURIST4.msg.showMsgErr({
                                    message: 'One or more rows in your file match multiple records in the database.<br>'+
                                             'Please click <b>Resolve ambiguous matches</b> to view and resolve these ambiguous matches.<br><br> '+
                                             'If you have many such ambiguities you may need to select adidtional key fields or edit the incoming '+
                                             'data file to add further matching information.',
                                    error_title: 'Ambiguous record matches found'
                                });

                                $('.step3 > .normal').hide();
                                $('.step3 > .skip_step').hide();
                                $('.step3 > .need_resolve').show();
                        
                        
                            }else{
                                //ok - go to next step
                                let counts = _getInsertUpdateCounts( currentSeqIndex );
                                if(currentSeqIndex>0 && counts[0]>0 && counts[2]==0){ //all records exsit and this is not primary record type
                                    _skipToNextRecordType();   
                                }else{
                                    _showStep(4);
                                    
                                    //load mapping  preset/suggestions
                                    let request = { action: 'get_matching_samples',
                                        rty_ID: imp_session['sequence'][currentSeqIndex]['rectype'],
                                        imp_ID: imp_ID,
                                            id: window.hWin.HEURIST4.util.random()
                                           };
                                           
                                        window.hWin.HAPI4.doImportAction(request, function( response ){
                                            
                                            if(response.status == window.hWin.ResponseStatus.OK && Object.keys(response.data).length>0 ){
                                                //and render sequence
                                                $('#presetMapping').show();
                                                
                                                let preset_mappings = response.data;
                                                let sel = $('#sel_presetMapping').css({'max-width':'200px'});
                                                sel.empty();
                                                window.hWin.HEURIST4.ui.addoption(sel[0], null, window.hWin.HR('select...'));    
                                                for(let sesionName in preset_mappings)
                                                if(sesionName){
                                                    window.hWin.HEURIST4.ui.addoption(sel[0], sesionName, sesionName)
                                                }
                                                
                                                //sel = window.hWin.HEURIST4.ui.initHSelect(sel, false);
                                                sel.on('change', function(event){
                                                   let sessionName = $(event.target).val();
                                                   if(sessionName && preset_mappings[sessionName]){
                                                       _initFieldMapppingSelectors( preset_mappings[sessionName] );
                                                   }
                                                });


                                            }else{
                                                $('#presetMapping').hide();
                                            }
                                        });
                                    
                                    
                                    _initFieldMapppingTable();
                                    //$('#mr_cnt_disamb').parent().hide();
                                }
                                
                            }
                            
                        }else{
                            _showStep(3);
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    }
            );        
        }
        
            
        
        }else{
            window.hWin.HEURIST4.msg.showMsgErr({
                message: window.hWin.HR('You have to select record type'),
                error_title: 'Missing record type'
            });
        }
        
    }

    //
    //
    //
    function _doPrepare(){

        currentSeqIndex = Number(currentSeqIndex);
        if(!(Number(currentSeqIndex)>=0)){
            window.hWin.HEURIST4.msg.showMsgErr({
                message: window.hWin.HR('You have to select record type'),
                error_title: 'Missing record type'
            });
            return;
        }

        let rtyID = imp_session['sequence'][currentSeqIndex]['rectype'];
        let key_idx = _getFieldIndexForIdentifier(currentSeqIndex); 
        if(!(key_idx>=0)){
            window.hWin.HEURIST4.msg.showMsgErr({
                message: `You must select a record identifier column for <b>${$Db.rty(rtyID,'rty_Name')}</b>`
                        +' in the first section below. This is used to identify the records to be created/updated',
                error_title: 'No record identifier selected'
            });
            return;
        }

        //do we have field to match? Also check if both Longitude and Latitude columns are needed
        let haveMapping = false, hasLongitude = false, hasLatitude = false;
        let field_mapping = {};
        let geo_col = null;
        let ele = $("input[id^='cbsa_dt_']");

        $.each(ele, function(idx, item){
            item = $(item);
            if(item.is(':checked')){ // && item.val()!=key_idx){

                let field_type_id = $('#sa_dt_'+item.val()).val();
                if(field_type_id){
                    field_mapping[item.val()] = field_type_id;
                    if(item.val()!=key_idx) haveMapping = true;
                }

                let field_type_name = $('#sa_dt_'+item.val()+' option:selected').text();
                if(field_type_name.indexOf('[X / Longitude]') >= 0){
                    hasLongitude = true;
                } else if(field_type_name.indexOf('[Y / Latitude]') >= 0){
                    hasLatitude = true;
                }

                if(field_type_name.indexOf('[Geospatial]') >= 0){
                    geo_col = $(item.parents('tr').find('td')[2]).text();
                }
            }
        });

        if(hasLongitude != hasLatitude){
            window.hWin.HEURIST4.msg.showMsgErr({
                message: 'You must map both X/Longitude and Y/Latitude if the coordinates are in separate columns,<br>'
                        +'or else a single Geospatial field if the they are in WKT format in a single column.',
                error_title: 'Invalid geospatial field mapping'
            });

            return;
        }

        if(!haveMapping){
            window.hWin.HEURIST4.msg.showMsgErr({
                message: 'You have not mapped any columns in the incoming data to fields in the record, '
                        +'so the records created would be empty. Please select the fields which should '
                        +`be imported into "${$Db.rty(rtyID,'rty_Name')}" records.`,
                error_title: 'No fields mapped'
            });

            return;
        }

        //check if any field besides matching fields is selected
        let insertFieldsNotYetSet = true;

        //if there is new record and no new fields set besides mapping_keys
        let counts = _getInsertUpdateCounts( currentSeqIndex );
        if(counts[2]>0 && imp_session['sequence'][currentSeqIndex]['mapping_keys']){
            let cols = Object.keys(field_mapping)
            for(let i=0;i<cols.length;i++){
                if(cols[i]!=key_idx && !imp_session['sequence'][currentSeqIndex]['mapping_keys'][cols[i]]){
                     insertFieldsNotYetSet = false;
                     break;
                }
            }

        }else{
            insertFieldsNotYetSet = false;
        }
 
        if(insertFieldsNotYetSet){
            window.hWin.HEURIST4.msg.showMsgDlg(
                'You have not selected any additional fields to insert into the "'+$Db.rty(rtyID,'rty_Name')
                +'" records to be created (n = '+counts[2]+'). This may result in placeholder records containing incomplete data. '
                +'Are you sure?', 
                __doPrepareStart, {title:'Warning',yes:'Proceed',no:'Cancel'});
            
        }else{
           __doPrepareStart(); 
        }
        return;
        
        function __doPrepareStart(){

            let session_id = window.hWin.HEURIST4.msg.showProgress( {container:$('#progressbar_div'), interval:100, content:false});
            
            let request = {
                db        : window.hWin.HAPI4.database,
                session   : session_id,
                imp_ID    : imp_ID,
                action    : 'step4',
                sa_rectype: rtyID,
                seq_index: currentSeqIndex,
                mapping   : field_mapping,
                ignore_insert: $('#sa_insert').prop('checked')?0:1,
                ignore_update: $('#sa_update').prop('checked')?0:1,
                recid_field: 'field_'+key_idx //imp_session['columns'][key_idx]
            };

//request['DBGSESSID']='425288446588500001;d=1,p=0,c=0';
        
        _showStep(0);
    
        window.hWin.HAPI4.doImportAction(request,  
                function(response){
                    
                    window.hWin.HEURIST4.msg.hideProgress();
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        
                        _showStep(4);
                        
                        imp_session = response.data; //assign to global var

                        let res = imp_session['validation'];
                        
                        if(res['missed_required_fields_map'] && res['missed_required_fields_map'].length>0){
                                window.hWin.HEURIST4.msg.showMsgDlg('The following fields are required fields. We recommend mapping them to incoming data before you can import new records:<br><br>'
                                +res['missed_required_fields_map'].join(', '),null,{title:'Warning'});
                        }
                        
                        $('#mr_cnt_update').text(res['count_update']);                                 
                        $('#mr_cnt_insert').text(res['count_insert']);   
                                                      
                        if(res['count_update_rows']>0){
                            $('#mr_cnt_update_rows').text(res['count_update_rows']);                                     
                            $('.mr_update').show();                                     
                        }else{
                            $('.mr_update').hide();                                     
                        }
                        if(res['count_insert_rows']>0){
                            $('#mr_cnt_insert_rows').text(res['count_insert_rows']);                                     
                            $('.mr_insert').show();                                     
                        }else{
                            $('.mr_insert').hide();                                     
                        }
                        
                        if(res['count_ignore_rows']>0){
                            $('#mr_cnt_ignore_rows').text(res['count_ignore_rows']);                                     
                            $('.mr_ignore').show();                                     
                        }else{
                            $('.mr_ignore').hide();                                     
                        }

                        //reset
                        $('#prepareWarnings').css('display','none');    
                        $('#mrr_warning').text('');
                            
                        if(res['utm_warning']>0){
                            $('#prepareWarnings').show();
                            $('#btnShowUTMWarnings').show();
                        }else{
                            $('#btnShowUTMWarnings').hide();
                        }

                        if(res['count_warning']>0){

                            $('#mrr_warning').text('Data errors: '+res['count_warning']);
                            $('#prepareWarnings').show();//.css('display','inline-block');
                            $('#btnShowWarnings').show();

                            if(res['missed_required']==true){
                                window.hWin.HEURIST4.msg.showMsgErr({
                                    message: (res['count_warning']==1?'There is one row':('There are '+res['count_warning']+' rows'))
                                            +' missing or contains wrong values for the fields. Including Required fields.<br><br> '
                                            +' You may continue to import required data with missing or invalid values.'
                                            +' After import, you can find and correct these values using Admin > Verify integrity<br><br>'
                                            + 'Click "Show" button  for a list of rows with missing values',
                                    error_title: 'Missing and invalid required fields'
                                });
                            }

                            // auto check 'ignore errors', these errors can be picked up and fixed by verify integrity or manually
                            $('#sa_ignore_errors').prop('checked', true).trigger('change');
                        }else{
                            $('#btnShowWarnings').hide();
                        }
                        
                        if(res['count_error']>0){
                            
                            $('#mrr_error').text('Rows with unrecognised terms: '+res['count_error']);
                            $('#prepareErrors').show();//.css('display','inline-block');
                            
                            window.hWin.HEURIST4.msg.showMsgErr({
                                message: (res['count_error']==1?'There is one row':('There are '+res['count_error']+' ROWS '))
                                        +'with warnings in your input (the same error may occur in many rows).'
                                        +'<br><br>These could include unrecognised terms, invalid dates, unknown record pointers '
                                        +'(no record with given ID), missing required values and so forth.'
                                        + '<br><br>The data can be imported as-is and new terms can be added automatically, '
                                        + 'but if the data has too many mistakes it may be better to fix these in the source '
                                        + 'file and then process it again. Open Refine is a useful free tool for fixing errors.'
                                        + '<br><br> On the other hand, Admin > Verify Integrity can be used to find many common '
                                        + 'errors once the data is loaded. Click "Show" button for a list of errors.',
                                error_title: 'Invalid values'
                            });

                            $('.step3 > .normal').hide();
                            $('.step3 > .skip_step').hide();
                            $('.step3 > .need_resolve').show();
                        }else{

                            if(res['geo_invalid'] && (res['geo_invalid']['invalid'].length > 0 || res['geo_invalid']['outOfBounds'].length > 0)){

                                let hasInvalidGeo = res['geo_invalid']['invalid'].length > 0;
                                let hasOOBGeo = res['geo_invalid']['outOfBounds'].length > 0;

                                let msg = 'There appears to be data within the ' + geo_col + ' field that contains ' + ((hasInvalidGeo) ? 'invalid data (example. values without any coordinates) ' : '') 
                                    + ((hasOOBGeo) ? ((hasInvalidGeo) ? 'and geo data out of bounds (invalid longitude and latitude values)' : 'out of bounds (invalid longitude and latitude values)') : '')
                                    + '.<br>If you continue with the import these values will be added to there respective records as is.<br>'
                                    + ' We highly recommend running Admin &gt; Verify integrity (after finishing the import) to find these fields and fix them manually.<br><br>';

                                if(res['geo_invalid']['invalid'].length > 0){
                                    msg += '<div style="margin-bottom: 5px;">Lines with invalid values: ' + res['geo_invalid']['invalid'].join(', ') + '</div>';
                                }
                                if(res['geo_invalid']['outOfBounds'].length > 0){
                                    msg += '<div>Lines with out of bounds values: ' + res['geo_invalid']['outOfBounds'].join(', ') + '</div>';
                                }

                                window.hWin.HEURIST4.msg.showMsgDlg(msg, null, null, {title: 'Invalid geographical data', default_palette_class: 'ui-heurist-populate'});
                            }

                            $('#prepareErrors').css('display','none');
                            _showStep(5);
                        }
                    }else{
                        _showStep(4);
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                
                });
                
        }
        
    } 
    
    
    function _doImport(){

        currentSeqIndex = Number(currentSeqIndex);
        if(!(Number(currentSeqIndex)>=0)){
            window.hWin.HEURIST4.msg.showMsgErr({
                message: window.hWin.HR('You have to select record type'),
                error_title: 'Missing record type'
            });
            return;
        }
        let rtyID = imp_session['sequence'][currentSeqIndex]['rectype'];
        let key_idx = _getFieldIndexForIdentifier(currentSeqIndex); 

        if(!(key_idx>=0)){
            window.hWin.HEURIST4.msg.showMsgErr({
                message: window.hWin.HR('You have to define identifier field for selected record type'),
                error_title: 'Missing identifer field'
            });
            return;
        }
        
        //do we have field to match?
        let haveMapping = false; 
        let field_mapping = {};
        let ele = $("input[id^='cbsa_dt_']");
        
        $.each(ele, function(idx, item){
            item = $(item);
            if(item.is(':checked')){ // && item.val()!=key_idx){
                let field_type_id = $('#sa_dt_'+item.val()).val();
                if(field_type_id && item.val()!=key_idx){ //do not include id field into mapping
                    field_mapping[item.val()] = field_type_id;
                    haveMapping = true;
                }
            }
        });
            
        if(!haveMapping){
            window.hWin.HEURIST4.msg.showMsgErr({
                message: 'You have not mapped any columns in the incoming data to fields in the record, '
                        +'so the records created would be empty. Please select the fields which should '
                        +`be imported into "${$Db.rty(rtyID,'rty_Name')}" records.`,
                error_title: 'No fields mapped'
            });
            return;
        }
        
        let session_id = window.hWin.HEURIST4.msg.showProgress( {container:$('#progressbar_div'), interval:100, content:false});
        
        let update_mode = $("input[name='sa_upd']:checked"). val();
        let retain_existing = 0
        if(update_mode>=20){
            retain_existing = update_mode==21?1:0;
            update_mode = update_mode==22?3:2;
        }else{
            //pre 2020-06-08
            retain_existing = $("input[name='sa_upd2']:checked"). val();
        }
        
        let handle_rectype = $('#ignore_rectype').is(':checked') ? '1' : '0';
        
        let request = {
            db        : window.hWin.HAPI4.database,
            session   : session_id,
            imp_ID    : imp_ID,
            action    : 'step5',
            sa_rectype: rtyID,
            seq_index : currentSeqIndex,
            mapping   : field_mapping,
            ignore_insert: $('#sa_insert').prop('checked')?0:1,
            ignore_update: $('#sa_update').prop('checked')?0:1,
            ignore_errors: $('#sa_ignore_errors').prop('checked')?1:0,

            is_csv_import: 1, // identify this request as a CSV import

            utm_zone  : UTMzone,
            recid_field: 'field_'+key_idx, //imp_session['columns'][key_idx]
            //0 - Retain existing values and append distinct new data as repeat values
            //1 - Add new data only if field is empty
            //2 - Replace all existing value(s) 
            sa_upd: update_mode, 
            sa_upd2: retain_existing,  //if no data: retain existing (0) or remove existing (1)
            ignore_rectype: handle_rectype
        };
        
        _showStep(0);
    
        window.hWin.HAPI4.doImportAction(request, 
                function(response){
                    
                    window.hWin.HEURIST4.msg.hideProgress();
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        
                        _showStep(5);
                        
                        imp_session = response.data; //assign to global var

                        let imp_result = imp_session['import_report'];
                        
                        let msg = ''
                          +'<table class="tbresults"><tr><td>Total rows in import table:</td><td>'+ imp_result['total']
                          +'</td></tr><tr><td>Processed:</td><td>'+ imp_result['processed']
                          +'</td></tr><tr><td'+(imp_result['skipped']>0?' style="color:red"':'')
                            +'>Not processed (data errors):</td><td>'+ imp_result['skipped']
                          +'</td></tr><tr><td>Records added:</td><td>'+ imp_result['inserted']
                          +'</td></tr><tr><td>Records updated:</td><td>'+ imp_result['updated'];
                          
                        if (imp_result['permission']>0){
                            msg = msg 
                            +'</td></tr><tr><td colspan="2" style="color:red"><br>' + imp_result['permission'] 
                            +' records could not be updated as you do not have adequate rights to modify them '
                            +'(workgroup administrator rights required)';
                        }   
                        if (imp_result['skipped']>0){
                            
                            let suggestions = 'If you see "missing data" message you may need to make fields optional.<br>'
                            +'If record cannot be updated due to an incorrect resource or parent links, run Verify integrity to fix problems with records<br><br>';
                            
                            msg = msg 
                            +'</td></tr><tr><td colspan="2" style="color:red"><br><a href="#" onclick="'
                            +'{$(\'#div_error_import_report\').show();$(event.target).hide()}'
                            +'">error report</a>'
                            +'<div id="div_error_import_report" '
                            +'style="display:none;max-width:800px;max-height:200px;overflow:auto;font-size0.7em;padding:10px;border:dotted 1px black;">'
                            +suggestions
                            +imp_result['skipped_details'].join('<br>')
                            +'</div>';
                            
                            //skipped_details
                        }
                          
                        msg = msg +'</td></tr></table>';

                        // Add line about invalid geo fields
                        if (imp_result['invalid_geo']>0){
                            let t_msg = (imp_result['invalid_geo'] == 1) ? ' error' : ' errors';
                            msg = msg +'<div style="color:red;">' + imp_result['invalid_geo'] + t_msg + ' encountered in geographical data fields. Please run Admin &gt; Verify integrity</div>';
                        }

                        window.hWin.HEURIST4.msg.showMsgDlg(msg, null, 
                                'Import of '+$Db.rty(rtyID,'rty_Name')+' complete.');
                        
                        //if everything is added - skip to next step
                        let counts = _getInsertUpdateCounts( currentSeqIndex );
                        if(counts[2]==0 && counts[0]>0){ //completely matching - go to next rectype
                                _showStep(3);
                                _renderRectypeSequence();
                                //$('.select_rectype_seq[data-seq-order="'+(currentSeqIndex-1)+'"]').trigger('click');    
                        }
                                                
                    }else{
                        _showStep(5);
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                
                });
        
    } 

    //
    //  Request to server side and get records for insert ot update from import table
    // 
    function _showRecords2(mode, is_download){
     
        let s = '';
        let container = $('#divPopupPreview');
        let dlg_options = {};
        let $dlg;
        let offset = 0, limit=10, 
            recCount = _getInsertUpdateCounts(currentSeqIndex),
            start_idx = 0;
            
        if(recCount==null) return;
        
        recCount = mode=='all'?imp_session['reccount']:recCount[mode=='insert'?3:1];

        let id_field = null;         
        
        let index_field_idx = _getFieldIndexForIdentifier(currentSeqIndex);
        if(index_field_idx>=0){
             id_field = 'field_'+index_field_idx;
        }
     
        
        let mapping_flds = null;
        if(currentStep!=3){  //import step
            mapping_flds = imp_session['sequence'][currentSeqIndex]['mapping_flds'];
        }
        if(!mapping_flds || $.isEmptyObject(mapping_flds)){
            mapping_flds = imp_session['sequence'][currentSeqIndex]['mapping_keys'];
        }
        if(!mapping_flds) mapping_flds = {};
        
        let header_flds = null;
        
        if(is_download){
            
            let k;
            header_flds = ['ID'];
            for(k=0; k<imp_session['columns'].length; k++){
                header_flds.push(imp_session['columns'][k]);
            }
                                                              
            let rts = Object.keys(imp_session['indexes']);
            for(k=0; k<rts.length; k++){
                
                let idx_id_fieldname = rts[k].substr(6); //'field_'
                if(idx_id_fieldname>imp_session['columns'].length){
                    let rtyID = imp_session['indexes'][rts[k]];
                    let sname = $Db.rty(rtyID,'rty_Name') +' H-ID';
                    header_flds.push(sname);
                }
            }
            
        }else{
        
            dlg_options['title'] = 'Records to be '+(mode=='insert'?'inserted':'updated');
            
            s = ''
                +'<div class="ent_wrapper"><div style="padding:8px 0 0 10px" class="ent_header">'
                +'<a href="#" class="navigation2" style="display: inline-block;"><span data-dest="first" class="ui-icon ui-icon-seek-first"/></a>'
                +'<a href="#" class="navigation2" style="display:inline-block;"><span data-dest="-1" class="ui-icon ui-icon-triangle-1-w"/></a>'
                +'<div style="display: inline-block;vertical-align: super;">Range <span id="current_range"></span></div>'
                +'<a href="#" class="navigation2" style="display: inline-block;"><span data-dest="1" class="ui-icon ui-icon-triangle-1-e"/></a>'
                +'<a href="#" class="navigation2" style="display: inline-block;"><span data-dest="last" class="ui-icon ui-icon-seek-end"/></a></div>';
            
                
            s = s + '<div class="ent_content_full"><table class="tbmain" style="font-size:0.9em" width="100%"><thead><tr>'; 

            //HEADER - field names
            let i=0, fieldnames = Object.keys(mapping_flds);

            if(mode=="update"){
                s = s + '<th style="text-align:left">Record ID</th>';
            }else if(fieldnames.length==0) {
                s = s + '<th style="text-align:left">Line #</th>';
            }else{
                start_idx = 1;
            }
            
            if(fieldnames.length>0){
                for(;i<fieldnames.length;i++){
                    let colname = imp_session['columns'][fieldnames[i]];
                    if(fieldnames[i]!=index_field_idx){
                        s = s + '<th style="text-align:left">'+colname+'</th>';
                    }
                }
            }else{  //all
            
                fieldnames = imp_session['columns'];
                for(;i<fieldnames.length;i++){
                    let colname = fieldnames[i];
                    s = s + '<th style="text-align:left">'+colname+'</th>';
                }
            }

            s = s + '</tr></thead><tbody></tbody></table></div></div>'
            +'<div class="loading semitransparent" style="display:none;width:100%;height:100%;poistion:absolute"></div>';
            
            dlg_options['element'] = container.get(0);
            container.html(s);
                
            $dlg = window.hWin.HEURIST4.msg.showElementAsDialog(dlg_options);
            
            $.each($dlg.find('.navigation2'), function(idx, item){
                $(item).on('click',  __loadRecordsFromImportTable );
            })
        
        }
        
        __loadRecordsFromImportTable();
        
        
        function __loadRecordsFromImportTable(event){
        
            if(!imp_session) return;
            
            let currentTable = imp_session['import_table']; 
        
            if(currentTable && recCount>0){
            
                    let dest = 0;
                    if(event){
                        dest = $(event.target).attr('data-dest');  
                    } 
                    
                    
                    if(Number(dest)=='first'){
                        offset = 0;
                    }else if(dest=='last'){
                        offset = Math.floor(recCount/limit) * limit;
                    }else{
                        dest = Number(dest);
                        if(isNaN(dest)){
                            offset = 0;
                        }else{
                            offset = offset + dest*limit;
                        }
                    }
                    if (offset>recCount){
                        offset = Math.floor(recCount/limit) * limit;
                    }                    
                    if(offset<0){        
                        offset = 0;
                    }

                    let request = { action: 'records',
                                    id_field: id_field,
                                    mapping: (is_download)?null:mapping_flds,
                                    header_flds: header_flds,
                                    mode: mode,
                                    output: (is_download)?'csv':'json',
                                    offset: is_download?0:offset,
                                    limit: is_download?0:limit,
                                    table:currentTable,
                                    id: window.hWin.HEURIST4.util.random()
                                       };
                                       
                    if(is_download){

                       request['db'] = window.hWin.HAPI4.database;
                       request['mapping'] = JSON.stringify(request['mapping']);
                       request['header_flds'] = encodeURIComponent(JSON.stringify(request['header_flds']));
                        
                       let keys = Object.keys(request) 
                       let params = [];
                       for(let k=0;k<keys.length;k++){
                           if(!window.hWin.HEURIST4.util.isempty(request[keys[k]])){
                                params.push(keys[k]+'='+request[keys[k]]);
                           }
                       }
                       params = params.join('&');
                       let url = window.hWin.HAPI4.baseURL + 'hserv/controller/importController.php?'+params;
                        
                       window.hWin.HEURIST4.util.downloadURL(url);
                        
                    }else{
                    
                        $dlg.find('.loading').show();
                        $dlg.find('.ent_wrapper').hide();
                        
                        
                        window.hWin.HAPI4.doImportAction(request, function( response ){
                            
                            $dlg.find('.loading').hide();
                            $dlg.find('.ent_wrapper').show();
                            
                            if(response.status == window.hWin.ResponseStatus.OK){
                            
                                    response = response.data;
                                    
                                    let table = $dlg.find('.tbmain > tbody');
                                
                                    let s = '';
                                    $dlg.find("#current_range").html( (offset+1)+':'+(offset+limit) );

                                    for(let i=0; i<response.length;i++){
                                        let row = response[i];
                                        if(start_idx>0) row.shift();
                                        s = s+'<tr>';
                                        s = s+'<td>'+ row.join('</td><td>') +'</td>';
                                        s = s+'</tr>';
                                    }
                                    
                                    table.html(s);
                                
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }

                        });        
                    }
            }
        }
        
     
    }
    
    //
    //  Compose table and show it in popup
    // 
    function _showRecords(mode){
        
        let res = imp_session['validation'];
        let s = '';
        let container = $('#divPopupPreview');
        let dlg_options = {};
        let $dlg;
        
        if(mode=='disamb'){
            
            s = '<div style="padding-bottom:0.5em">The following rows match with multiple records.</div><div style="padding-bottom:0.5em">This may be due to the existence of duplicate '
            + 'records in your database, but you may not have chosen</div><div style="padding-bottom:0.5em">all the fields required to correctly disambiguate '
            + 'the incoming rows against existing data records.</div>'
            + '<button style="float:right" onclick="{$(\'.sel_disamb\').val(-1);}">Create New</button>'
            + '<button style="float:right;margin-right:10px" '
            + 'onclick="{$(\'.sel_disamb\').each(function(i,item){ '
                    +'var opts = $(item).find(\'option\');'
                    +'$(item).val( $(opts[opts.length-2]).attr(\'value\') ); }); }">Last choice</button>'
            + '<button style="float:right;margin-right:10px" '
            + 'onclick="{$(\'.sel_disamb\').each(function(i,item){$(item).val( $(item).find(\'option:first\').attr(\'value\') ) });}">First choice</button>'
            + '<span style="float:right; padding: 3px;">Set all to: </span>'
            + '<br><br>'
            + '<table class="tbmain" width="100%">';
            
            let buttons = {};
            
            buttons[window.hWin.HR('Save ambiguities list to file')]  = function() {
                
                 window.hWin.HEURIST4.util.downloadInnerHtml('ambiguities.csv', $('#csv_disambig'), 'text/csv');
            };
            
            buttons[window.hWin.HR('Apply choices above')]  = function() {
                    
                    let keyvalues = Object.keys(res['disambiguation']);

                    let disamb_resolv = [];  //recid=>keyvalue
                    $dlg.find('.sel_disamb').each(function(idx, item){
                         disamb_resolv.push({recid:$(item).val(),key:keyvalues[$(item).attr('data-key')]});
                         //disamb_resolv[$(item).val()] = keyvalues[$(item).attr('data-key')];
                    });

                    $dlg.dialog( "close" );
                    
                    //perform matching again with resolved disambiguations
                    _doMatching(0, disamb_resolv);
                }; 
            buttons[window.hWin.HR('Close')]  = function() {
                    $dlg.dialog( "close" );
            };
            
            dlg_options = {
                title:'Disambiguation',
                buttons: buttons
            };
            
            let keyvalues = Object.keys(res['disambiguation']);
            
            let csv_output = '';

            s += '<thead><tr>';

            
            let fieldnames = Object.keys(res['mapped_fields']);
            for(let i=0;i<fieldnames.length;i++){
                
                let colname = imp_session['columns'][fieldnames[i].substring(6)];
                csv_output += colname+',';
                s += ('<th align="left">'+colname+'</th>');
            }
            
            s += '<th style="text-align:center">Matched Record</th><th style="text-align:center">Count</th><th style="text-align:left">Potential Matching Records in Heurist</th></tr>';
            
            csv_output += 'Record ID,Record Title\n';
            
            for(let i=0;i<keyvalues.length;i++){

                let keyvalue = keyvalues[i].split('▫');//imp_session['csv_mvsep']);
                //WHY???!!! keyvalue.shift(); //remove first element 
                csv_output +=('"'+keyvalue.join('","')+'"');
                let keys_prefix = ','.repeat(keyvalue.length);

                let str_length = keyvalue.join(' ').length;
                str_length = str_length > 75 ? 75 : str_length;
                
                keyvalue = keyvalue.join('</div></td><td><div class="truncate" style="max-width:100px">'); //';&nbsp;&nbsp;');
                
                //list of heurist records
                let disamb = res['disambiguation'][keyvalues[i]];
                let disambig_imp_id = res['disambiguation_lines'][keyvalues[i]];

                let recIds = Object.keys(disamb);
                        
                s = s + `<tr><td><div class="truncate" style="max-width:${str_length}ch;text-align:center" title="${keyvalue}">${keyvalue}</div></td><td style="text-align:center">`
                + '<a href="#" '
                + 'style="text-decoration:underline;color:blue" '
                + ' onclick="{window.hWin.HEURIST4.util.findObjInFrame(\'importRecordsCSV\').showImportLineInPopup(\''+disambig_imp_id+'\');}">'
                + 'view</a>'  // <span class="ui-icon ui-icon-popup">
                + '</td><td style="text-align:center">'+recIds.length+'</td><td>'+
                        '<select class="sel_disamb" style="max-width:300px" data-key="'+i+'">';                

                for(let j=0;j<recIds.length;j++){
                    s = s +  '<option value="'+recIds[j]+'">[rec# '+recIds[j]+'] '+disamb[recIds[j]]+'</option>';
                    
                    csv_output += (keys_prefix + '"'+recIds[j]+'","'+disamb[recIds[j]]+'"\n');
                }
                s = s + '<option value="-1">[create new record] None of these</option>';
                s = s + '</select>&nbsp;'
                + '<a href="#" onclick="{window.open(\''+window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                + '&q=ids:' + recIds.join(',') + '&nometadatadisplay=true\', \'_blank\');}">view records <span class="ui-icon ui-icon-extlink"></a></td></tr>';
            }
            
            s = s + '</table><br><br><div id="csv_disambig" style="display:none">'+csv_output+'</div>'
            +'<div>Please select from the possible matches in the dropdowns. You may not be able to determine the correct records'
            +' if you have used an incomplete set of fields for matching.</div>';

        }
        else if(mode=='error' || mode=='warning'){    //----------------------------------------------------------------------------------- 

            let is_missed = false;
            let tabs = res[mode];
            
            let dt_to_col = {};

            if(mode == 'warning'){
                s = '<span style="color:red;">You MUST either change the field to a more lenient field, such as text, or check the \'Ignore validation. Add data "as is"\' box,<br>'
                  + 'otherwise the rows containing these data will not be processed.</span>';
            }

            if(tabs.length>1){

                s = s + '<div id="tabs_records"><ul>';
                let k=0;            
                for (;k<tabs.length;k++){
                    
                    let cnt = (Array.isArray(tabs[k]['values_error']) && tabs[k]['values_error'].length>0)
                                    ?(tabs[k]['values_error'].length+' '):'';
                
                    let checked_field = tabs[k]['field_checked'];
                    if(checked_field && Array.isArray(checked_field)){
                        checked_field = checked_field[0];
                    }
                
                    let colname = imp_session['columns'][checked_field.substr(6)]; //field_
                        s = s + '<li><a href="#rec__'+k+'" style="color:red">'
                                    +colname+'<br><span style="font-size:0.7em">'
                                    +cnt+tabs[k]['short_message']+'</span></a></li>';
                    
                }
                s = s + '</ul>';
            }
   
            for (let k=0;k<tabs.length;k++){
                let rec_tab = tabs[k];
                s = s + '<div id="rec__'+k+'">'

                let cnt = rec_tab['count_error'];
                let records = rec_tab['recs_error'];

                if(cnt>records.length){
                    s = s + "<div class='error'><b>Only the first "+records.length+" of "+cnt+" rows are shown</b></div>";
                }

                let ismultivalue = false;
                let checked_field  = rec_tab['field_checked'];
                if(checked_field && Array.isArray(checked_field)){
                    checked_field = checked_field[0];
                }
                if(checked_field){
                    ismultivalue = imp_session['multivals'][checked_field.substr(6)];//highlight errors individually
                }
                /*
                if(checked_field && checked_field.length>0){
                    for (let m=0;m<checked_field.length;m++){    
                        ismultivalue = imp_session['multivals'][checked_field[m].substr(6)];//highlight errors individually
                        if(ismultivalue) break;
                    }
                }*/
                
                s = s + "<div><span class='error'>Values in red are invalid: </span> "+rec_tab['err_message']+"<br><br></div>";
                
                let is_missed = (rec_tab['err_message'].indexOf('a value must be supplied')>0);

                //all this code only for small asterics
                let rtyID = imp_session['sequence'][currentSeqIndex]['rectype'];

                let colname = imp_session['columns'][checked_field.substr(6)];
                let dt_id = res['mapped_fields'][checked_field]; //from validation
            
                    
                if(dt_id>0 && $Db.dty(dt_id, 'dty_Type')=='enum'){ //button to add terms
                
                    let cnt = (Array.isArray(tabs[k]['values_error']) && tabs[k]['values_error'].length>0)
                                    ?tabs[k]['values_error'].length:0;
                    if(cnt>0){                 
                        s = s + '<button class="add_terms" tab_id="'+k+'" dt_id="'+dt_id+'" style="padding: 4px 8px !important;">'
                        +'Adds '+cnt+' new terms to this field</button>';
                            //'"'+$Db.dty(dt_id, 'dty_Name')+'"</button>';
                        
                        s = s + '&nbsp;<button class="add_all_terms" style="padding: 4px 8px !important;display:none">'
                                +'Adds new terms to all fields</button>';
                            
                        s += '<br><br>';     
                    }

                    if(cnt >= 1000 || cnt == imp_session['reccount']){

                        s += '<span style="color:red;">'
                            + 'Heurist has determined that there is a very large amount of terms to be imported that do not exist.<br>'
                            + 'We recommend reviewing this field\'s mapping and changing it to a field type that is less restrictive (e.g. freetext or blocktext).<br>'
                        + '</span>';

                        s += '<br><br>';
                    }
                }
    
    
                s = s + '<table class="tbmain" width="100%"><thead><tr>' 
                        + '<th width="20px">Line #</th>';

                //HEADER - only error field
                let err_col = 0;
                
                //let mapped_fields = imp_session['validation']['mapped_fields'];
                let fieldnames = Object.keys(res['mapped_fields']);

                for(let i=0;i<fieldnames.length;i++){
                
                    
                    if(fieldnames[i] == checked_field){
                        
                        let dt_id2 = (dt_id>0) ?dt_id :dt_id.substr(0,dt_id.indexOf('_'));
                        
                        if($Db.rst(rtyID, dt_id2)==null){
                            console.error('ERROR: field '+dt_id2+' not found for '+rtyID);
                        }else
                        if($Db.rst(rtyID, dt_id2, 'rst_RequirementType') == "required"){
                            colname = colname + "*";
                        }
                        
                        
                        /* @TODO generate term selector
                        if(is_enum){
                            $showlink = '&nbsp;<a href="#" onclick="{showTermListPreview('.$dt_id.')}">show list of terms</a>';
                        }else{
                            $showlink = '';
                        }
                        
                        if($is_enum){
                            $colname = $colname."<div id='termspreview".$dt_id."'></div>"; //container for
                        }
                        */
                        
                        
                        s = s + '<th style="color:red">'+colname
                             + '<br><span style="font-size:10px;font-weight:normal">'
                             + (dt_id>0?$Db.baseFieldType[$Db.dty(dt_id, 'dty_Type')] :dt_id)
                             + '</span></th>';
                             
                        err_col = i;
                        
                        if(dt_id>0) dt_to_col[dt_id] = (err_col+1);
                        
                        break;
                    }
                }

                s = s + '<th>Record content</th></tr></thead>';
                
                //BODY
                for(let i=0;i<records.length;i++){

                    let row = records[i];
                    s = s + '<tr><td class="truncate">'+(Number(row[0]))+'</td>'; //line#
                    if(is_missed){
                        s = s + "<td style='color:red'>&lt;missing&gt;</td>";
                    } else if(ismultivalue){
                        s = s + "<td class='truncate'>"+row[err_col+1]+"</td>";
                    } else {
                        s = s + "<td class='truncate'>"+row[err_col+1]+"</td>";     //style='color:red'
                    }
                    //TODO - print row content of line
                    /*
                    for(let j=0;j<row.length;j++){
                        s = s +  '<td class="truncate">'+(row[j]?row[j]:"&nbsp;")+'</td>';
                    }
                    */
                    s = s + "<td>&nbsp;</td></tr>";
                }
            
                s = s + '</table></div>';
                
            }//tabs

            if(tabs.length>1){
                s = s + '</div>';
            }
            
            dlg_options['title'] = `Records with ${mode == 'error' ? 'warning' : 'error'}s`; // 
            
        }
        else if(res['count_'+mode+'_rows']>0)
        { //-------------------------------------------------------------------------------
            
            dlg_options['title'] = 'Records to be '+(mode=='insert'?'inserted':'updated');
            
            s = '<table class="tbmain" width="100%"><thead><tr>'; 

            if(mode=="update"){
                s = s + '<th width="30px">Record ID</th>';
            }       
            s = s + '<th width="20px">Line #</th>';

            //HEADER - field names
            let i=0, fieldnames = Object.keys(res['mapped_fields']);
            for(;i<fieldnames.length;i++){
                
                let colname = imp_session['columns'][fieldnames[i].substr(6)];
                s = s + '<th>'+colname+'</th>';
            }

            s = s + '</tr></thead>';
            
            //BODY
            let records = res['recs_'+mode];
            for(let i=0;i<records.length;i++){

                let row = records[i];
                s = s + "<tr>";
                for(let j=0;j<row.length;j++){
                    s = s +  '<td class="truncate">'+(row[j]?row[j]:"&nbsp;")+'</td>';
                }
                s = s + "</tr>";
            }
            
            s = s + '</table>';
        }
        
        if(s == ''){ return; }

        dlg_options['window'] = window;  //current frame
        dlg_options['element'] = container.get(0);
        container.html(s);
        container.css({'overflow-x':'hidden'});

        if(container.find("#tabs_records").length>0){
                $("#tabs_records").tabs();
        }

        $dlg = window.hWin.HEURIST4.msg.showElementAsDialog(dlg_options);

        const TABS = imp_session['validation']['error'];

        //activate add terms buttons
        let btn_ads = $dlg.find('.add_terms');
        btn_ads.on('click', function(event){

            let ele = $(event.target);
            let tab_idx = ele.attr('tab_id');
            let dty_ID = ele.attr('dt_id');
            let wrong_values = TABS[tab_idx]['values_error'];

            let has_peroids = false;

            for(const TERM of wrong_values){
                if(TERM.indexOf('.') > 0){
                    has_peroids = true;
                    break;
                }
            }

            _importTerms_ParentID(dty_ID, null, (trm_ParentTermID) => {

                let field = [[TABS[tab_idx]['field_checked'], dty_ID, trm_ParentTermID]];

                has_peroids ? _importTerms_Separator(field, $dlg, false) : _importTerms_Import(field, '', $dlg, false);
            });
        });
        
        //activate add ALL terms buttons
        if(btn_ads.length>1){
            $dlg.find('.add_all_terms').show().on('click', function(event){

                let has_peroids = false;
                let fields = [];

                $dlg.find('.add_terms').each((idx, button) => {

                    let tab_idx = button.getAttribute('tab_id');
                    let dty_ID = button.getAttribute('dt_id');
                    let wrong_values = TABS[tab_idx]['values_error'];

                    if(!has_peroids){
                        for(const TERM of wrong_values){
                            if(TERM.indexOf('.') > 0){
                                has_peroids = true;
                                break;
                            }
                        }
                    }

                    fields.push([TABS[tab_idx]['field_checked'], dty_ID, null]);
                });

                let idx = 0;
                let def_ParentTermID = null;

                let _handle_callback = (trm_ParentTermID) => {

                    fields[idx][2] = trm_ParentTermID;
                    def_ParentTermID = !def_ParentTermID ? trm_ParentTermID : def_ParentTermID;
                    idx ++;

                    if(fields.length > idx){
                        _importTerms_ParentID(fields[0][1], def_ParentTermID, _handle_callback);
                        return;
                    }

                    has_peroids ? _importTerms_Separator(fields, $dlg, true) : _importTerms_Import(fields, '', $dlg, true);
                };

                _importTerms_ParentID(fields[0][1], null, _handle_callback);
            });
        }
    } 

    function _importTerms_ParentID(dty_ID, def_ParentTermID, callback){

        if(typeof callback !== 'function'){
            return false;
        }

        let trm_ParentTermID = def_ParentTermID;
        let terms = $Db.dty(dty_ID, 'dty_JsonTermIDTree');

        if(window.hWin.HEURIST4.util.isNumber(terms) && terms>0){ //this is vocabulary
            trm_ParentTermID = Number(terms);
        }else{
            //this is a set of terms - find special vocabulary  'Auto-added terms'
            let vocabs = $Db.trm_getVocabs('enum');
            for (let i=0; i<vocabs.length; i++){
                let trmID = vocabs[i];
                if(trmID > 0 && $Db.trm(trmID,'trm_Label') == 'Auto-added terms'){
                    trm_ParentTermID = trmID;
                    break;
                }
            }

            if(trm_ParentTermID <= 0){ //add new vocabulary to root

                let request = {
                    'a': 'save',
                    'entity': 'defTerms',
                    'request_id': window.hWin.HEURIST4.util.random(),
                    'fields': [{trm_Label:'Auto-added terms', trm_ParentTermID:'', trm_Depth:0, trm_Domain:'enum'}]                     
                };

                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){

                    if(response.status == window.hWin.ResponseStatus.OK){
                        let recIDs = response.data;
                        trm_ParentTermID = Number(recIDs[0]);
                        callback.call(this, trm_ParentTermID);
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });                    
                
                return;
            }
        }

        callback.call(this, trm_ParentTermID);
    }
    function _importTerms_Separator(fields, $dlg, is_all){

        let $dlg_term_warning;

        let btns = {};
        btns['Periods as separators'] = function(){

            $dlg_term_warning.dialog('close');

            _importTerms_Import(fields, '.', $dlg, is_all);
        };

        btns['Periods as part of terms'] = function(){

            $dlg_term_warning.dialog('close');

            _importTerms_Import(fields, '', $dlg, is_all);
        };

        $dlg_term_warning = window.hWin.HEURIST4.msg.showMsgDlg(
            'You have term(s) which contain periods (.). These are often used as separators between levels of a hierarchical term tree.<br><br>'
            + 'Do you want to treat periods as hierarchical separators?<br>(note: will apply to ALL terms in the import which contain periods)',
            btns, 
            {title: 'Presence of periods in terms', yes: 'Periods as separators', no: 'Periods as part of terms'}, {default_palette_class: 'ui-heurist-populate'}
        );
    }
    function _importTerms_Import(fields, separator, $dlg, is_all){

        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(fields) || fields[0].length != 3){
            window.hWin.HEURIST4.msg.showMsgErr({
                message: 'No terms to import',
                error_title: 'Invalid term data',
                status: window.hWin.ResponseStatus.UNKNOWN_ERROR
            });
            return false;
        }

        let session_id = window.hWin.HEURIST4.msg.showProgress( {container:$('#progressbar_div'), interval:100, content:false});

        let request = {
            db: window.hWin.HAPI4.database,
            session: session_id,
            imp_ID: imp_ID,
            action: 'import_terms',
            fields: fields,
            trm_Separator: separator,
            request_id: window.hWin.HEURIST4.util.random()
        };

        const RETURN_STEP = currentStep;
        _showStep(0);

        window.hWin.HAPI4.doImportAction(request, (response) => {

            window.hWin.HEURIST4.msg.hideProgress();
            
            _showStep(RETURN_STEP);

            if(response.status !== window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            let cnt = $dlg.find('.add_terms').length;
            let added_count = response.data.success.length;

            let s = `${added_count} new term${(added_count==1)?' was':'s were'} imported. `;

            let error = ``;
            if(response.data.error.length > 0){
                error = '<br><br><strong>The following errors occurred:</strong><br><br>';

                for(const fld_idx in response.data.error){

                    if(!Object.hasOwn(response.data.error, fld_idx)){
                        continue;
                    }

                    error += response.data.error[fld_idx];
                }
            }

            window.hWin.HAPI4.EntityMgr.refreshEntityData('trm', () => {

                if(cnt == 1 || is_all){

                    $dlg.dialog('close');
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: `${s}Please repeat "Prepare" action.${error}`,
                        error_title: 'Terms imported'
                    });
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: `${s}Check other "error" tabs `
                                +'to add missing terms for other enumeration fields. '
                                +`And finally close this dialog and repeat "Prepare" action.${error}`,
                        error_title: 'Terms imported'
                    });
                }
            });
        });
    }

    //
    // Add new column + default data to each row
    //
    function _insertNewColumn(){

        let col_name = $('#txtColName').val();
        let col_data = $('#txtColData').val();

        if(window.hWin.HEURIST4.util.isempty(col_name) || window.hWin.HEURIST4.util.isempty(col_data)){
            window.hWin.HEURIST4.msg.showMsgFlash('Both a column name and data are required...', 3000);
            return;
        }

        let request = {
            db: window.hWin.HAPI4.database,
            imp_ID: imp_ID,
            action: 'insert_column',
            column_name: col_name,
            column_data: col_data,
            request_id: window.hWin.HEURIST4.util.random()
        };

        window.hWin.HAPI4.doImportAction(request, (response) => {

            if(response.status !== window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            switch(currentStep){
                case 3:
                    _loadSession();
                    break;
                case 4:
                case 5:
                    _doMatchingInit();
                    _doPrepare();
                    break;

                default:
                    break;
            }

        });
    }

    //
    // get import line on server and show it on popup
    //
    function _showImportLineInPopup(imp_ID){
        
            if(!imp_session) return;
            let currentTable = imp_session['import_table']; 
        
            let request = { action: 'records',
                            imp_ID: imp_ID,
                            table:currentTable,
                            id: window.hWin.HEURIST4.util.random()
                               };
            
            window.hWin.HAPI4.doImportAction(request, function(response){
                
                //that.loadanimation(false);
                if(response.status == window.hWin.ResponseStatus.OK){
                    
                        response = response.data;
                        let res = '<table>';
                        
                        for(let i=1; i<response.length;i++){
                            let sval;
                            if(window.hWin.HEURIST4.util.isnull(response[i])){
                                sval = "&nbsp;";
                            }else{

                                let idx_id_fieldname = _getFieldIndexForIdentifier(currentSeqIndex);
                                
                                let isIndex =  (idx_id_fieldname==(i-1)) || !window.hWin.HEURIST4.util.isnull(imp_session['indexes']['field_'+(i-1)]);
                                
                                sval = response[i].substr(0,100);

                                if(isIndex && response[i]<0){
                                    sval = "&lt;New Record&gt;";
                                }else if(sval==""){
                                    sval = "&nbsp;";
                                }else if(response[i].length>100){ //add ... 
                                    sval = sval + '&#8230;';
                                }
                            }
                            
                            res = res + '<tr><td>'+imp_session['columns'][i-1]+'</td><td>'+sval+'</td></tr>';
                        }
                    res = res + '</table>';     

                    let $dlg2, buttons={};
                    buttons[window.hWin.HR('Close')]  = function() {
                            $dlg2.dialog( "close" );
                    };
                    
                    let container = $('#divPopupPreview2');
                    container.html(res);
                    
                    let dlg_options = {
                        title:'Import source',
                        buttons: buttons,
                        element:container.get(0)
                        };
            
                    $dlg2 = window.hWin.HEURIST4.msg.showElementAsDialog(dlg_options);
                
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }

            });        
    
    }
    //
    //
    //
    function _onUpdateModeSet(){
        
            $('#divFieldMapping2').show();
        
            /* pre 2020-06-08
            if ($("#sa_upd2").is(":checked")) { 
                $("#divImport2").css('display','block');
            }else{
                $("#divImport2").css('display','none');
            }
            */
            if ($("#sa_upd1").is(":checked") || $("#sa_upd20").is(":checked")) { 
                $("#divImport3").css('display','block');
                $('#divImport3_marker').removeClass('ui-icon-triangle-1-e').addClass('ui-icon-triangle-1-s');
            }else{
                $("#divImport3").css('display','none');
                $('#divImport3_marker').removeClass('ui-icon-triangle-1-s').addClass('ui-icon-triangle-1-e');
            }
                
            if(currentStep==4){ //prepare
            
                $('h2.step4').css('display','inline-block');
            
                let rtyID = imp_session['sequence'][currentSeqIndex]['rectype'];
                let counts = _getInsertUpdateCounts(currentSeqIndex);
                
                let to_be_inserted = counts[2];
                let to_be_updated = counts[0];
                let both_insert_and_update = (to_be_updated>0 && to_be_inserted>0);

                window.hWin.HEURIST4.util.setDisabled($('#sa_insert'), !both_insert_and_update);
                window.hWin.HEURIST4.util.setDisabled($('#sa_update'), !both_insert_and_update);
                if(!both_insert_and_update){
                    $('#sa_insert').css('opacity',1);
                    $('#sa_update').css('opacity',1);
                }
                if(!both_insert_and_update || 
                   (!$('#sa_insert').prop('checked') && !$('#sa_update').prop('checked'))
                ){
                    $('#sa_insert').prop('checked', (to_be_inserted>0));
                    $('#sa_update').prop('checked', (to_be_updated>0));
                }
                $('#btnImportStart').button({label:('Start '
                    +($('#sa_insert').prop('checked')?'Insert':'')
                    +($('#sa_insert').prop('checked')&&$('#sa_update').prop('checked')?'/':'')
                    +($('#sa_update').prop('checked')?'Update':''))});
                $('#head_step3').text('step 3: '
                    +($('#sa_insert').prop('checked')?'INSERT':'')
                    +($('#sa_insert').prop('checked')&&$('#sa_update').prop('checked')?'/':'')
                    +($('#sa_update').prop('checked')?'UPDATE':''));   

                let shelp = 'Now select the columns which you wish to import into fields in the <b>'
                + ($Db.rty(rtyID,'rty_Name')?$Db.rty(rtyID,'rty_Name'):'')
                + '</b>  records which are '
                + (to_be_inserted>0 ?'created ':'')
                + (both_insert_and_update?' or ':'')
                + (to_be_updated>0 ?'updated':'')
                + (to_be_inserted>0 && $('#sa_insert').prop('checked') 
                        ?'. <br>Since new records are to be created, make sure you select all relevant columns; '
                                +'all Required fields should be mapped to a column.':'');
                
                shelp = shelp + '<br>Note that no changes are made to the database when you click the Prepare button.';
                
                $('#divPrepareSettingHelp').html(shelp);
                
                window.hWin.HEURIST4.util.setDisabled($('#btnPrepareStart'), false);
                window.hWin.HEURIST4.util.setDisabled($('#btnImportStart'), true);
            }else{ //import
            
                window.hWin.HEURIST4.util.setDisabled($('#sa_insert'), true);
                window.hWin.HEURIST4.util.setDisabled($('#sa_update'), true);
                $('#sa_insert').css('opacity',1);
                $('#sa_update').css('opacity',1);
            
                $('h2.step5').css('display','inline-block');
                window.hWin.HEURIST4.util.setDisabled($('#btnPrepareStart'), true);
                window.hWin.HEURIST4.util.setDisabled($('#btnImportStart'), false);
                //$('#divImportSettingHelp').html();
                
                if(!$('#sa_update').prop('checked')){
                    $('#divImportSetting').hide();
                }
                
            }
            
            _adjustTablePosition();
    }
    
    /*
    navigation steps
    0 - progress
    1 - select file to upload
    2 - preview and field roles as data and id
    --
    3 - matching: assign rec ids 
    4 - validate import   
    5 - import
    */
    function _showStep(page){
        currentStep = page;

        if(page>1){
            let container = $(window.hWin.document).find('.ui-menu6-container.ui-heurist-populate');
            if(container.find('.ui-helper-popup').is(':visible')){
                container.find('.ui-helper-popup').hide();    
                //container.find('.ui-helper-popup-button').button({icon:"ui-icon-circle-help"});
                container.find('.ui-helper-popup-button').find('.ui-button-icon')
                    .removeClass('ui-icon-carat-2-e').addClass('ui-icon-circle-help');
                container.find('.ent_content_full').css({right:1,width:'auto'});
            }
        }

        if(page == 5 && $('#sa_update').prop('checked')){
            $('#divheader').css('background','rgba(200, 100, 200, 0.83)');

            $('tr.helper').show().children().html(
                '<span style="background:rgba(200, 100, 200, 0.83);display:inline-block;padding:3px;">UPDATE</span> Allocate <span style="color:purple;">purple fields</span> to '
                +'<span style="color:purple;">record pointer fields</span> which connect to entities created in previous steps'
            );
        }else{
            $('#divheader').css('background','rgba(151, 244, 128, 0.83)');

            if(page != 3){
                $('tr.helper').hide();
            }else{
                $('tr.helper').show().children().html(
                    '<span style="background:rgba(151, 244, 128, 0.83);display:inline-block;padding:3px;">MATCHING</span> Choose only the fields you need to match &nbsp;&nbsp;'
                    +'<span style="color:green;">Note: ONLY fields suitable for matching are shown in this step.</span>'
                );
            }
        }
        
        $("div[id^='divStep']").hide();
        $("#divStep"+(page>2?3:page)).show();
        $('#prepareErrors').hide();
        if(!(page==4 || page==5)){
            $('#prepareWarnings').hide();
            $('#divFieldMapping2').show();
        }else{
            $('#divFieldMapping2').hide();
        }

        if(page==1){
            $("#selImportId").val('');  //clear selection
            //if(session_selector && session_selector.hSelect('instance')) session_selector.hSelect("refresh");
            if(session_selector && session_selector.hSelect('instance')) {
                let sw = $("#selImportId").width();        
                if(sw>500) sw = 500;
                session_selector.hSelect("refresh");
                $("#selImportId-button").css('width', sw);   
            }
            imp_ID = 0;
            
        }else if(page>2){  //matching and import
        
            $("div[class*='step'],h2[class*='step']").hide();
            $('.step'+page).show();
            
            _redrawArrow();            
            
            if(page==3){
                $('.step3 > .normal').show();
                $('.step3 > .need_resolve').hide();
                _onMatchModeSet();
            }else{
                //show either prepare import or start import
                _onUpdateModeSet();
            }
        }

        if(page>=2){

            if((!upload_file_name || upload_file_name == undefined) && imp_session && !window.hWin.HEURIST4.util.isempty(imp_session['import_file'])){
                let filename_parts = imp_session['import_file'].split(' ').slice(0, -2);
                upload_file_name = filename_parts.join(' ');
            }
            if(!window.hWin.HEURIST4.util.isempty(upload_file_name) && upload_file_name != undefined){
                $('.curr_file_name').text(upload_file_name).attr('title', upload_file_name);
                $('.curr_file_name').parent().show();
            }else{
                $('.curr_file_name').parent().hide();
            }

            let position_params = {
                my: 'left+15 center',
                at: 'right center'
            };
            if(page == 2){
                position_params['of'] = '#btnBackToStart2';
                $('#file_name2').position(position_params);
            }else{
                position_params['of'] = '#btnBackToStart';
                $('#file_name').position(position_params);
            }
        }
        _adjustTablePosition();
    }

    //
    // Disable create new records option while ignoring record type
    //
    function _onIgnoreRectype(){

        let $ignore_rectype = $('#ignore_rectype');
        let isChecked = $ignore_rectype.is(':checked');
        window.hWin.HEURIST4.util.setDisabled($('#sa_match2'), isChecked);

        if(isChecked){
            $('#sa_match2, [for="sa_match2"]').attr('title', 'Option is disabled while ignoring record type')
        }else{
            $('#sa_match2, [for="sa_match2"]').attr('title', '');
        }
    }
    
    //public members
    let that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        showRecords: function (mode) {_showRecords(mode)},
        showRecords2: function (mode, is_download) {_showRecords2(mode, is_download)},

        showImportLineInPopup: function (imp_ID) {_showImportLineInPopup(imp_ID)},
        
        onUpdateModeSet:function (event){
            _onUpdateModeSet();
        },
        onMatchModeSet:function (event){
            _onMatchModeSet();
        },
        doMatchingInit:function (event){
            _doMatchingInit();
        },
        onIgnoreRectype: function(event){
            _onIgnoreRectype();
        }
        
        
    }

    _init(_imp_ID, _max_upload_size, _format);
    return that;  //returns object
}
    
    