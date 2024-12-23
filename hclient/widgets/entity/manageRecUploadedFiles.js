/**
* manageRecUploadedFiles.js - main widget to manage recUploadedFiles
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
/* global HEditing */

$.widget( "heurist.manageRecUploadedFiles", $.heurist.manageEntity, {
   
    _entityName:'recUploadedFiles',
    
    _editing_uploadfile:null, //hidden edit form to upload file
    
    _additionMode:'local',// remote, all, tiled
    
    _previousURL:null,
    _requestForMimeType:false,

    _init_ExternalFileReference: null,
    _init_MimeExt: null,
    
    _external_repositories: {}, // list of external repositories
    _last_upload_details: [], // last uploaded file details

    _selectAllFiles: false, // to keep all files (across all tabs) selected

    _lastFileDetails: null, // holds the saved final details for the current file, to be returned

    //
    //
    //
    _init: function() {
        
        if(!this.options.default_palette_class){
            this.options.default_palette_class = 'ui-heurist-populate';    
        }
        
        this.options.coverall_on_save = true;
        this.options.layout_mode = 'short';
        this.options.use_cache = false;
       
        this.options.edit_need_load_fullrecord = true;
        this.options.edit_height = 700;
        this.options.edit_width = 950;
        this.options.height = 800;
        
        //this.options.edit_addrecordfirst = true; //special behaviour - show editor first
        
        if(this.options.edit_addrecordfirst){
            this.options.select_mode='select_single';
        }
        

        //for selection mode set some options
        if(this.options.select_mode!='manager'){
            this.options.width = (isNaN(this.options.width) || this.options.width<815)?900:this.options.width;                    
            //this.options.edit_mode = 'none'
        }
        this.options.height = (isNaN(this.options.height) || this.options.height<800)
                        ?(window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95:this.options.height;

        if($.isPlainObject(this.options.selection_on_init)){

            this._init_ExternalFileReference = this.options.selection_on_init.ulf_ExternalFileReference 
                                                ? this.options.selection_on_init.ulf_ExternalFileReference :null;
            this._init_MimeExt = this.options.selection_on_init.ulf_MimeExt
                                        ?this.options.selection_on_init.ulf_MimeExt :null; 
                                        
            this.options.selection_on_init = [this.options.selection_on_init.ulf_ID];
        }

        this._super();
    },
    
/* @todo - add this selector to search form
        this.select_order = $( "<select><option value='1'>"+
            window.hWin.HR("by name")+"</option><option value='5'>"+
            window.hWin.HR("by size")+"</option><option value='6'>"+
            window.hWin.HR("by usage")+"</option><option value='7'>"+
            window.hWin.HR("by date")+"</option><option value='8'>"+
            window.hWin.HR("marked")+"</option></select>", {'width':'80px'} )
*/    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        if(this.options.edit_addrecordfirst){
            this._additionMode = this.options.additionMode?this.options.additionMode:'any'; 
            this.addEditRecord(-1);
            
            if(this.options.isdialog){
                //hide
                this._as_dialog.dialog("close");   
            }
            return;
        }

        if(!this._super()){
            return false;
        }
        
        if(this.options.edit_mode=='editonly'){
            this.addEditRecord( this.options.rec_ID );
            return;
        }

        // init search header
        this.editForm.css('padding-top',20);
        this.searchForm.searchRecUploadedFiles(this.options);

        if(this._additionMode=='tiled'){
            
            this.searchForm.hide();
            this.recordList.css({'top':'0px'});
            
        }else{
        
            let iheight = 7.4;
            
            if(this.options.edit_mode=='inline'){            
                iheight = iheight + 8;
            }
            this.searchForm.css({'height':iheight+'em', padding:'10px', 'min-width': '730px'});
            this.recordList.css({'top':iheight+2+'em'});
        
        }
        
        //init viewer 
        if(this.options.select_mode=='manager'){
            this.recordList.parent().css({'border-right':'lightgray 1px solid'});
        }
        
        this._on( this.searchForm, {
            
                "searchrecuploadedfilesonaction": function(event, action) { 
                    if(action=='menu-file-add-local'){
                        this._uploadFileAndRegister();
                        
                    }else if(action=='menu-file-add-ext'){ 
                        this._additionMode='remote'; this.addEditRecord(-1);
                        
                    }else if(action=='menu-file-import-csv'){ 

                        window.hWin.HAPI4.actionHandler.executeActionById('menu-files-data');
                        
                    }else if(action=='menu-file-select-all'){ 
                        this._selectAllFiles = true;
                        this.recordList.resultList('setSelected', 'all');
                        
                    }else if(action=='menu-file-select-none'){ 
                        this._selectAllFiles = false;
                        this.recordList.resultList('setSelected', '');
                        
                    }else if(action=='menu-file-refrec-show'){ 
                        
                        this._showMediaRecords();

                    }else if(action=='menu-file-refrec-add'){ 
                        
                        this._createMediaRecords();
                        
                    }else if(action=='menu-file-export-csv'){ 
                        
                        this._downloadFileRefs()

                    }else if(action=='menu-file-delete-selected'){ 
                        
                        this._deleteSelected();
                        
                    }else if(action=='menu-file-merge-dupes'){ 
                        
                        this._combineDups();
                        
                    }else if(action=='menu-file-refresh-index'){ 
                        
                        this._refreshIndex();
                        
                    }else if(action=='menu-file-check-files'){ 
                        
                        this._checkFiles();
                        
                    }
                },
                "searchrecuploadedfilesonresult": this.updateRecordList
        });

        return true;
    },
 
    //
    // show hide elements in edit form according to local/external new/edit mode
    //
    _initEditForm_step4: function(recordset){
        
        this._currentEditRecordset = recordset; 

        let i_id = this.getEntityFieldIdx('ulf_ID');
        
        let isLocal = true;
        if(recordset!=null){
            //edit
            this.options.entity.fields[i_id].dtFields['rst_Display'] = 'readonly'; //path to download
            isLocal = window.hWin.HEURIST4.util.isempty(this._getField('ulf_ExternalFileReference'));
        }else{
            //new record
            this.options.entity.fields[i_id].dtFields['rst_Display'] = 'hidden';
            isLocal = (this._additionMode=='local');
        }
        
        let i_url = this.getEntityFieldIdx('ulf_HeuristURL');
        let i_url_ext = this.getEntityFieldIdx('ulf_ExternalFileReference');
        let i_filename = this.getEntityFieldIdx('ulf_OrigFileName');
        let i_filesize = this.getEntityFieldIdx('ulf_FileSizeKB');

        let i_mime_loc = this.getEntityFieldIdx('fxm_MimeType'); // for local
        let i_mime_ext = this.getEntityFieldIdx('ulf_MimeExt');   // for external

        let i_file_upl = this.getEntityFieldIdx('ulf_FileUpload');   
        let i_descr = this.getEntityFieldIdx('ulf_Description');   // for external
        
        this.options.entity.fields[i_url_ext].dtFields['rst_DisplayHelpText'] =
            'URL of an external file. This must DIRECTLY point to a renderable file or stream eg. image, video.<br>'
            +'Note: the URL MUST load the image alone without any page furniture or labelling<br>'
            +'NOT the page containing the image (the extension at the end of the URL can be misleading)';

        if(this._additionMode=='any' ||  this._additionMode=='tiled'){ //uncertain addition show both upload and url
            //only addition
            this.options.entity.fields[i_file_upl].dtFields['rst_Display'] = 'hidden'; //show DnD zone
            this.options.entity.fields[i_url_ext].dtFields['rst_Display']  = 'visible'; //edit url //

            if(this._additionMode=='tiled'){
                this.options.entity.fields[i_url_ext].dtFields['rst_DisplayHelpText'] =
                '<br>URL to TileMapService (for example openstreetmap.org or maptiler.com) or image service (iiif manifest).'
                +'<br>For uploaded tile stack this field will be filled automatically with name of selected stack (folder).';
            }

            this.options.entity.fields[i_url].dtFields['rst_Display'] = 'hidden';            
            this.options.entity.fields[i_filename].dtFields['rst_Display'] = 'hidden';
            this.options.entity.fields[i_filesize].dtFields['rst_Display'] = 'hidden';
            this.options.entity.fields[i_descr].dtFields['rst_Display'] = 'hidden';

            this.options.entity.fields[i_mime_ext].dtFields['rst_Display'] = 'hidden'; //temp till ext will be defined
            this.options.entity.fields[i_mime_loc].dtFields['rst_Display'] = 'hidden'; //readonly fxm_MimeType

            this._edit_dialog.dialog('option', 'height', 600);

        }else
        if(isLocal){ //local
            this.options.entity.fields[i_url].dtFields['rst_DefaultValue'] = window.hWin.HAPI4.baseURL
                                                    + '?db=' + window.hWin.HAPI4.database 
                                                    + '&file='+this._getField('ulf_ObfuscatedFileID');
            this.options.entity.fields[i_url_ext].dtFields['rst_Display']  = 'hidden'; 
            
            this.options.entity.fields[i_mime_ext].dtFields['rst_Display'] = 'hidden';
            this.options.entity.fields[i_descr].dtFields['rst_Display'] = 'visible';
            
            if(this._currentEditRecordset){
                //edit
                this.options.entity.fields[i_url].dtFields['rst_Display'] = 'readonly';
                this.options.entity.fields[i_filename].dtFields['rst_Display'] = 'readonly';
                this.options.entity.fields[i_filesize].dtFields['rst_Display'] = 'readonly';
                this.options.entity.fields[i_mime_loc].dtFields['rst_Display'] = 'readonly';
                this.options.entity.fields[i_file_upl].dtFields['rst_Display'] = 'hidden';
            }else{
                //add new file
                this.options.entity.fields[i_file_upl].dtFields['rst_Display'] = 'visible'; //show DnD zone
                this.options.entity.fields[i_url].dtFields['rst_Display'] = 'hidden';            
                this.options.entity.fields[i_filename].dtFields['rst_Display'] = 'hidden';
                this.options.entity.fields[i_filesize].dtFields['rst_Display'] = 'hidden';
                this.options.entity.fields[i_mime_loc].dtFields['rst_Display'] = 'hidden';
                
                this._edit_dialog.dialog('option','width',800);
            }
            
            
        }else{ //remote
            this.options.entity.fields[i_url].dtFields['rst_Display'] = 'hidden';  

            this.options.entity.fields[i_url_ext].dtFields['rst_Display']  = 'visible'; //edit url
            this.options.entity.fields[i_filename].dtFields['rst_Display'] = 'hidden';
            this.options.entity.fields[i_filesize].dtFields['rst_Display'] = 'hidden';

            this.options.entity.fields[i_mime_loc].dtFields['rst_Display'] = 'hidden';
            this.options.entity.fields[i_mime_ext].dtFields['rst_Display'] = 'visible';
            
            this.options.entity.fields[i_file_upl].dtFields['rst_Display'] = 'hidden'; //DnD
            this.options.entity.fields[i_descr].dtFields['rst_Display'] = 'visible';
        }
        
        this._super(recordset);
    },
    
    //-----
    // perform required after edit form init modifications (show/hide fields, assign event listener )
    // for example hide/show some fields based on value of field
    //
    _afterInitEditForm: function(){

        let that = this;

        this._super();

        let isLocal = true;

        if(this._currentEditRecordset){ //edit       

            //add media viewer below edit form and load media content
            this.mediaviewer = $('<div>').addClass('media-content').css({
                'text-align': 'center',
                padding: '20px',
                'background-color': 'lightgray',
                'border-top': '1px solid lightgray',
                margin: '10px'});
            this.editForm.append( this.mediaviewer );
            this.mediaviewer.mediaViewer({rec_Files:[{
                    id: this._editing.getValue('ulf_ObfuscatedFileID')[0], 
                    filename: this._editing.getValue('ulf_OrigFileName')[0], 
                    mimeType: this._editing.getValue('fxm_MimeType')[0]}]}); //nonce + memtype
                
            //list of records that refer to this file    
            let relations = this._currentEditRecordset.getRelations();    
            if(relations?.direct?.length>0){
                $('<div class="detailRowHeader">Records that refer this file</div>').appendTo(this.editForm);
                
                let direct = relations.direct;
                let headers = relations.headers;

                for(let k in direct){
                    let targetID = direct[k].targetID;
                    
                    if(!headers[targetID]){
                        //there is not such record in database
                        continue;                                            
                    }
                    
                    window.hWin.HEURIST4.ui.createRecordLinkInfo(this.editForm, 
                                {rec_ID: targetID, 
                                 rec_Title: headers[targetID][0], 
                                 rec_RecTypeID: headers[targetID][1]
                                }, true);
                }//for
            }

            isLocal = window.hWin.HEURIST4.util.isempty(this._getField('ulf_ExternalFileReference'));

            if(window.hWin.HEURIST4.util.isempty(this._editing.getValue('ulf_WhoCanView')[0])){
                // force option to public, without changes
                let ele = this._editing.getFieldByName('ulf_WhoCanView');
                ele.editing_input('setValue', 'viewable', true);
            }
        }else{
            //new record
            isLocal = (this._additionMode=='local');
            
            if(isLocal){
                //find file uploader and make entire dialogue as a paste zone - to catch Ctrl+V globally
                let ele = this._edit_dialog.find('input[type=file]');
                if(ele.length>0){
                    ele.fileupload('option','pasteZone',this._edit_dialog);
                }
            }else if(this._additionMode=='any' || this._additionMode=='tiled'){
                //add two button on top
                //select and register at once + close this dialog and open file selector

                let sAdditional_Controls = '<h2 style="margin: 0;">Existing</h2><div><div class="header optional" style="vertical-align: top; display: table-cell;">'
                    +'<label>Select:</label></div><span class="editint-inout-repeat-button" style="min-width: 22px; display: table-cell;"></span>'
                    +'<div class="input-cell" style="padding-bottom: 12px;">'
                    +'<div id="btn_select_file"></div></div></div>';

                let sHelp = '<div class="heurist-helper1" style="padding: 0.2em 0px;">'
                    +'<br>Store as a file on the Heurist server. '
                    +((this._additionMode=='tiled')?
                    ('<br>Note: you can upload a tile stack generated by a tiling program such as gdal2tiles, MapTiler, MapWarper. '
                    +'<br>The tile stack must be uploaded as a zip file, which cannot exceed the size limit for uploaded files.'
                    +'<br>If you require a larger tile stack please consult the Heurist team.'):'')
                    +'</div>';

                sAdditional_Controls += '<h2 style="margin: 0;">New</h2><div><div class="header optional" style="vertical-align: top; display: table-cell;">'
                    +'<label>Upload to Heurist:</label></div><span class="editint-inout-repeat-button" style="min-width: 22px; display: table-cell;"></span>'
                    +'<div class="input-cell" style="padding-bottom: 12px;"><div id="btn_upload_file"></div>'+sHelp+'</div></div>';

                sHelp = '<div class="heurist-helper1" style="padding: 0.2em 0px;"></div>';

                if(this._additionMode=='tiled'){
/*
                    sAdditional_Controls += '<div><div class="header optional" style="vertical-align: top; display: table-cell;">'
                        +'<label>Upload tiled image stack:</label></div><span class="editint-inout-repeat-button" style="min-width: 22px; display: table-cell;"></span>'
                        +'<div class="input-cell" style="padding-bottom: 12px;"><div id="btn_upload_file_stack"></div>'
                        + sHelp                    
                        + '</div></div>';
                        sHelp = '';
*/                
                    sAdditional_Controls += '<div id="register_stack_container"><div class="header optional" style="vertical-align: top; display: table-cell;">'
                        +'<label>Register previously uploaded:</label></div><span class="editint-inout-repeat-button" style="min-width: 22px; display: table-cell;"></span>'
                        +'<div class="input-cell" style="padding-bottom: 12px;">'
                        +'<div id="btn_register_stack" style="display: inline-block;"></div>'
                        +'<div id="btn_register_single_file_stack" style="display: inline-block;"></div>'+sHelp+'</div></div>';
                }

                if(this._additionMode!='tiled'){
                    sHelp = '<div class="heurist-helper1" style="padding: 0.2em 0px;">'
                            + '<br>Store as a file in the chosen repository and linked to Heurist via its URL'
                        +'</div>';

                    sAdditional_Controls += '<div><div class="header optional" style="vertical-align: top; display: table-cell;"><label>Upload to external repository:</label></div>'
                            + '<span class="editint-inout-repeat-button" style="min-width: 22px; display: table-cell;"></span>'
                            + '<div class="input-cell" style="padding-bottom: 12px;">'
                                + '<select id="external_repos"><option value="" selected>select repository...</option></select>'
                                + '<input type="file" id="upload_file_repository" style="display:none;" filename="">'
                                + '<div id="btn_upload_file_repository" style="margin-left: 25px;"></div>'
                            + sHelp + '</div>'
                        + '</div>';
                }

                $(sAdditional_Controls)
                    .insertBefore(this._edit_dialog.find('fieldset > div:first'));
                
                this._edit_dialog.find('#btn_upload_file').css({'min-width':'9em','z-index':2})
                    .button({label: window.hWin.HR((this._additionMode=='tiled')?'Choose zip or mbtiles file':'Choose file')
                    ,icon:'ui-icon-upload'})
                    .on('click', function(e) {
                        that._uploadFileAndRegister(false);
                    }); 

                this._edit_dialog.find('#btn_upload_file_stack').css({'min-width':'9em','z-index':2})
                    .button({label: window.hWin.HR('Choose zip or mbtiles file')
                    ,icon:"ui-icon-upload"})
                    .on('click', function(e) {
                        that._uploadFileAndRegister( true );
                    }); 
                    
                if(this._additionMode!='tiled'){

                    let $select = this._edit_dialog.find('#external_repos');

                    this._edit_dialog.find('#upload_file_repository').fileupload({
                        url: window.hWin.HAPI4.baseURL +  'hserv/controller/fileUpload.php', 
                        formData: [ {name:'db', value: window.hWin.HAPI4.database}, 
                                    {name:'entity', value:'temp'}, //to place file into scratch folder
                                    {name:'max_file_size', value:1024*1024}],
                        autoUpload: true,
                        sequentialUploads:true,
                        dataType: 'json',
                        done: function (e, response) {
                            response = response.result;
                            that._last_upload_details = [];
                            if(response.status==window.hWin.ResponseStatus.OK){
                                let data = response.data;
                                $.each(data.files, function (index, file) {

                                    if(file.error){
                                        $('#type-err').html(file.error); // display under section
                                        return;
                                    }

                                    that._edit_dialog.find('#upload_file_repository').attr('filename', file.name);
                                    that._last_upload_details.push($.extend({}, file));

                                    if($select.val() != ''){
                                        // file uploaded
                                        that._handleExternalRepository();
                                    }
                                });
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                                
                            let inpt = this;
                            that._edit_dialog.find('#btn_upload_file_repository').off('click');
                            that._edit_dialog.find('#btn_upload_file_repository').on({click: function(){
                                $(inpt).trigger('click');
                            }});                
                        },
                        fail: function (e, response) {
                            response = response.message ? response : {message: response, error_title: 'File upload error'};
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
                    
                    // Dropdown of available repositories
                    window.hWin.HAPI4.SystemMgr.repositoryAction({'a': 'list', 'include_test': 1}, (response) => {

                        if(response.status != window.hWin.ResponseStatus.OK){
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                            return;
                        }

                        $.each(response.data, (idx, repo_details) => {

                            let repo_name = repo_details[1];
                            repo_name = repo_name.charAt(0).toUpperCase() + repo_name.slice(1);

                            if(Object.hasOwn(that._external_repositories, repo_name)){
                                that._external_repositories[repo_name].push(repo_details);
                                return;
                            }

                            that._external_repositories[repo_name] = [ repo_details ];

                            window.hWin.HEURIST4.ui.addoption($select[0], repo_name, repo_name);
                        });

                        if($select.hSelect('instance') !== undefined){
                            $select.hSelect('refresh');
                        }
                    });

                    window.hWin.HEURIST4.ui.initHSelect($select, false, {width: '150px'});
                    this._on($select, {
                        'change': () => {

                            let file_ele = that._edit_dialog.find('#upload_file_repository');

                            if(file_ele.attr('filename') != '' || that._last_upload_details.length > 0){
                                that._handleExternalRepository();
                            }
                        }
                    });
                }//external repos
                    
                this._edit_dialog.find('#btn_select_file').css({'min-width':'9em','z-index':2})
                    .button({label: window.hWin.HR('Choose previously referenced '
                            +(this._additionMode=='tiled'?'image stack or IIIF':'file'))
                    ,icon:"ui-icon-grid"})
                    .on('click', function(e) {
                         that._currentEditID = null;
                         that.editFormPopup.dialog('close');
                         if(that.options.edit_addrecordfirst){
                             that.options.edit_addrecordfirst = false;
                             that._initControls();
                         }
                    }); 
                
                // Handle for file upload - to be uploaded to a repository
                this._edit_dialog.find('#btn_upload_file_repository').css({'min-width':'9em','z-index':2})
                    .button({label: window.hWin.HR('Choose file')
                    ,icon:'ui-icon-upload'})
                    .on('click',(e) => {
                        if(that._edit_dialog.find('#external_repos').val() != ''){
                            that._edit_dialog.find('#upload_file_repository').trigger('click');
                        }
                    });
                
                if(this._additionMode=='tiled')
                {
                    this._edit_dialog.find('#btn_select_file').css('display','inline-block');
                    
                    this._edit_dialog.find('#btn_register_stack')
                        .css({'min-width':'9em','z-index':2,'margin-left':'5px'})
                        .button({label: window.hWin.HR('Tielstack directory (eg. Zoomify)')
                        ,icon:"ui-icon-grid"})
                        .on('click', function(e) {
                            
                            if(!that.select_folder_dlg){
                                that.select_folder_dlg = $('<div/>').hide().appendTo( that._edit_dialog );
                            }
                                
                            that.select_folder_dlg.selectFolders({
                               onselect:function(newsel){
                                    if(newsel){
                                        if(that.options.edit_addrecordfirst){
                                           
                                            that._initControls();
                                             
                                            that._currentEditID = null;
                                            that._editing.setFieldValueByName2('ulf_ExternalFileReference', newsel+'/', false);
                                            
                                            let ele2 = that._editing.getFieldByName('ulf_MimeExt');
                                            ele2.editing_input('setValue', 'png' );
                                            ele2.show();
                                           
                                            let interval = setInterval(function(){
                                                if(!window.hWin.HAPI4.is_callserver_in_progress()){
                                                    clearInterval(interval);
                                                    interval = 0;
                                                    that._saveEditAndClose(null);        
                                                }
                                            },500);
                                            
                                        }
                                    }
                               }, 
                               root_dir: 'uploaded_tilestacks',
                               allowEdit: false,
                               selectedValues: '', 
                               title: 'Select Uploaded tilestack',
                               emptyMessage: 'No previously uploaded map tilestacks found',
                               multiselect: false});
                            
                            
                        });
                        
                    this._edit_dialog.find('#btn_register_single_file_stack')
                        .css({'min-width':'9em','z-index':2,'margin-left':'5px'})
                        .button({label: window.hWin.HR('Single file tilestack (eg. MBtiles)')
                        ,icon:"ui-icon-grid"})
                        .on('click', function(e) {
                            
                            if(!that.select_file_dlg){
                                that.select_file_dlg = $('<div/>').hide().appendTo( that._edit_dialog );
                            }
                                
                            that.select_file_dlg.selectFile({
                               source: 'uploaded_tilestacks',     
                               extensions: 'mbtiles',
                               title: window.HR('Select single file tilestack'),
                               onselect:function(res){
                                    if(res){
                                        
                                        if(that.options.edit_addrecordfirst){
                                           
                                            that._initControls();
                                             
                                            that._currentEditID = null;
                                            that._editing.setFieldValueByName2('ulf_ExternalFileReference', res.filename, false);
                                            
                                            let ele2 = that._editing.getFieldByName('ulf_MimeExt');
                                            ele2.editing_input('setValue', 'png' );
                                            ele2.show();
                                           
                                            let interval = setInterval(function(){
                                                if(!window.hWin.HAPI4.is_callserver_in_progress()){
                                                    clearInterval(interval);
                                                    interval = 0;
                                                    that._saveEditAndClose(null);        
                                                }
                                            },500);
                                            
                                        }
                                    }
                               }});
                            
                        });
                        
                        
                    this._edit_dialog.find('#register_stack_container').css('display', 'table-row');
                }else{
                    this._edit_dialog.find('#register_stack_container').hide();
                }
            }
        }

        if(!isLocal){ //remote - detect mimetype when URL is changed

            let ele = that._editing.getFieldByName('ulf_ExternalFileReference');
            let inpt = ele.editing_input('getInputs');
            //ele.editing_input('option', 'change', function(){
                this._on($(inpt[0]), {
                    blur:function(){
                        
                        let ele = that._editing.getFieldByName('ulf_ExternalFileReference');    
                    
                        if (ele.editing_input('instance')==undefined) return;
                        
                        //auto detect extension of external service
                        let curr_url = ele.editing_input('getValues'); 

                        if( !window.hWin.HEURIST4.util.isempty(curr_url[0]) && curr_url[0]!=that._previousURL ){    
    
                            that._previousURL = curr_url[0];
                            
                            that._requestMimeTypeByURL();
                        }
                    }

                });
            
            ele.editing_input('focus');
        }else{
            //force show save button
            let ele = this._toolbar;
            if(ele){
                ele.find('.btnRecSave').css('visibility', 'visible');
            }
        }

        // If already existing, add the current external reference value 
        if(this._init_ExternalFileReference){
            this._editing.setFieldValueByName2('ulf_ExternalFileReference', this._init_ExternalFileReference, false);
            this._previousURL = this._init_ExternalFileReference;
            
            if(this._init_MimeExt){
                //this._editing.setFieldValueByName2('ulf_MimeExt', this._init_MimeExt, false);    
                let ele3 = this._editing.getFieldByName('ulf_MimeExt');
                ele3.editing_input('setValue', this._init_MimeExt, false );
                ele3.show();
            }
            
        }else{
            let urls = this._editing.getValue('ulf_ExternalFileReference');
            if(urls){
                this._previousURL = urls[0];    
            }else{
                this._previousURL = null;
            }
        }

        //hide after edit init btnRecRemove
        this._toolbar.find('.btnRecRemove').hide();
    },    
    
    _getValidatedValues: function(){
        
        let res = this._super();
        
        let val = this._editing.getValue('ulf_ExternalFileReference');
        let isLocal = window.hWin.HEURIST4.util.isempty(val[0]);
        if(!isLocal && res){
            
            let mimeext = this._editing.getValue('ulf_MimeExt');
            let err_msg = this._validateExt( mimeext[0] );        
            if(err_msg){
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: err_msg,
                    error_title: 'File type error'
                });
                res = null;
            }
        }
        
        return res;
    },
    
    _validateExt: function( ext ){
        
        let msg_error = '';
        if(ext==null) ext = '';

        if(!ext){

            msg_error = '<b>Inaccessible web resource</b>The URL you have entered does not point to an accessible web resource. '
        +'It could be incorrect, or the URL is not openly accessible.';

        }else if (ext=='html' || ext=='htm'){

            msg_error = '<b>URL points to a web page</b>'
            +'The URL points to a web page or web application rather than a remote file. '
            +'The URL must point to a FILE resource; web pages are not supported by this field type.'
            +'Web page URLs should simply be entered as a text string which will be correctly interpreted if it uses http:// or https:// ';

        }else{

            //allowed extensions
            let allowed_ext = window.hWin.HAPI4.sysinfo.media_ext+',soundcloud,vimeo,youtube,json'.split(',');

            if(allowed_ext.indexOf(ext)<0){

                msg_error = 
                '<b>Unsupported file type</b>'
                +'<p>The URL you have entered does not appear to point to a file type that we currently support. It must point to a FILE resource which can be rendered eg an image, streaming data source or data file. If you would like this file type supported, please contact the Heurist team (by email or Bug reporter at top of page).</p>'
                +'Currently supported file types include: <ul><li>'
                +'Web compatible images (JPG, PNG, GIF, JPEG2000; note that other image formats may be supported but cannot be rendered in web pages without special software) </li>'
                +'<li>Composite image resources (Tiled image stacks, IIIF Manifests) </li>'
                +'<li>Media files in common formats (MPG, MPEG4, AVI, Quiktime etc.) </li>'
                +'<li>Streaming media such as YouTube, Vimeo, SoundCloud </li>'
                +'<li>PDF and other text files, spreadsheets </li>'
                +'<li>Vector GIS data (Shapefiles, KML, CSV) </li></ul>';



                // if(ext=='bin'){
                // ele2.editing_input('showErrorMsg', 'Cannot retrieve content type for given url '
                // +' or mimetype was not found among allowed types.'
               

                /*
                window.hWin.HEURIST4.msg.showMsgDlg();
                */    
            }else{
                msg_error =  ''; 
            }
        }

        return msg_error;
    },
    
    //
    //
    //
    _requestMimeTypeByURL:function(){
        
        if(this._additionMode=='tiled' || this._editing.getValue('ulf_OrigFileName')[0].indexOf('_tiled')==0 ){
            //special case for tiled image stack            
            let ele2 = this._editing.getFieldByName('ulf_MimeExt');
            if(this._previousURL){
                ele2.show();
            }else{
                ele2.hide();
            }
            return;               
        }

  
        window.hWin.HEURIST4.msg.showMsgFlash('Getting resource type', false);
  
        let that = this;

        let url = that._previousURL;

        that._requestForMimeType_Timeout = 0;
        that._requestForMimeType = true;                          
        //request server to detect content type
        window.hWin.HAPI4.SystemMgr.get_url_content_type(url, function(response){
            
            that._requestForMimeType = false;
            let ele2 = that._editing.getFieldByName('ulf_MimeExt');
            
            let ext = '';
            if(response.status == window.hWin.ResponseStatus.OK){
                ext = response.data.extension;
                
                if(response.data.needrefresh){
                    let cfg = ele2.editing_input('getConfigMode');
                    window.hWin.HAPI4.EntityMgr.clearEntityData( cfg.entity );
                }
                
            }
            if(ext==null) ext = '';

            
            let msg_error = that._validateExt( ext );
            
            ele2.editing_input('setValue', ext );
            ele2.show();
            that.onEditFormChange();
            
            if(msg_error){
                ele2.editing_input('showErrorMsg', msg_error);    
                that.editForm.animate({scrollTop: ele2.offset().top}, 1);
            }else{
                ele2.editing_input('showErrorMsg', ''); //hide
            }
            /*
            let ele = that._toolbar;
            if(ele){
                ele.find('.btnRecSave').css('visibility', msg_error?'hidden':'visible');
            }*/
            
    
            window.hWin.HEURIST4.msg.closeMsgFlash();
            
            
        });
        
        
    },
        
    //----------------------
    //
    //  overwrite standard render for resultList
    //
    _recordListItemRenderer:function(recordset, record){
        
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function fld2(fldname, col_width){
            let swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = ' style="width:'+col_width+'"';
            }
            return '<div class="item" '+swidth+'>'+window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname))+'</div>';
        }
        
        let recID   = fld('ulf_ID');
        
        let rectype = fld('ulf_ExternalFileReference')?'external':'local';
       
        
        let recTitle;
        let recTitleHint;
        if(rectype=='external'){
            let val = fld('ulf_OrigFileName');
            if(val.indexOf('_tiled')==0){
                rectype = 'tiled';
                if(val.indexOf('_tiled@')==0){ 
                        val = val.substr(7);
                }
                if(!window.hWin.HEURIST4.util.isempty(val)){
                    recTitle = '<div class="item" style="width:auto">'+val+'</div>';    
                }
            }else if(val.indexOf('_iiif')==0){
                val = (val=='_iiif') ?'IIIF Manifest' :'IIIF Image';
                
                if(!window.hWin.HEURIST4.util.isempty(val)){
                    recTitle = '<div class="item" style="width:auto">'+val+'</div>';    
                }
            }
            if(!recTitle){
                recTitle = fld2('ulf_ExternalFileReference','auto');//,'20em'
            }
            recTitleHint = recID+' : '+fld('ulf_ExternalFileReference');
        }else{
            recTitleHint = recID+' : '+fld('ulf_OrigFileName') + ' @ ' + fld('ulf_FilePath');
            recTitle = '<div class="item" style="width:auto">'+fld('ulf_OrigFileName')+' &nbsp;&nbsp; [ '+fld('ulf_FilePath')+' ] &nbsp;&nbsp; [ '+fld('ulf_FileSizeKB')+'KB ]</div>';
        }
        
        let recIcon = '';//@todo take default icon from extensions table and or for default image/audio/video
        
        let html_thumb = '<div class="recTypeThumb realThumb" style="background-image: url(&quot;'+ 
        window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database + '&thumb='+
                    fld('ulf_ObfuscatedFileID') + '&quot;);opacity:1"></div>';
            
        if(this.options.select_mode=='manager'){
        html_thumb = '<a href="'+            
window.hWin.HAPI4.baseURL+'?db=' + window.hWin.HAPI4.database  //(needplayer?'&player=1':'')
 + '&file='+fld('ulf_ObfuscatedFileID')+'" target="yoxview" class="yoxviewLink">' +  html_thumb + '</a>';                   
        }

        let classes = `recordDiv${this._selectAllFiles ? ' selected' : ''}`;

        let view_mode = this.recordList.resultList('option', 'view_mode');
        let left_pos = view_mode == 'list' ? 102 : 86;

        let html = '<div class="'+classes+'" id="rd'+recID+'" recid="'+recID+'" rectype="'+rectype+'">'
        + html_thumb
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + '<div class="recordIcons" style="display:none;">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" style="background-image: url(&quot;'+recIcon+'&quot;);">'   //class="rt-icon" 
        + '</div>'
        + `<div class="recordTitle" title="${recTitleHint}" style="left: ${left_pos}px">`
        +     recTitle
        + '</div>';
        
        let action_style = 'style="height:20px;margin-left:0px;"';
        let url_icon = `<div title="Click to copy the file\'s Heurist URL" ${action_style} role="button" aria-disabled="false" data-key="url" `
            + 'class="action-button logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only">'
                + '<span class="ui-button-icon-primary ui-icon ui-icon-link"></span><span class="ui-button-text"></span>'
            + '</div>';
        // add edit/remove action buttons
        if(this.options.select_mode=='manager' && this.options.edit_mode!='none'){

            html = html 
                + `<div title="Click to edit file" ${action_style} role="button" aria-disabled="false" data-key="edit" `
                +   'class="action-button logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-pencil"></span><span class="ui-button-text"></span>'
                + '</div>&nbsp;&nbsp;'
                + `${url_icon}&nbsp;&nbsp;`
                + `<div title="Click to delete file" ${action_style} role="button" aria-disabled="false" data-key="delete" `
                +   'class="action-button logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-circle-close"></span><span class="ui-button-text"></span>'
                + '</div>';
        }else{

            html += `<div title="Click to view file" ${action_style} role="button" aria-disabled="false" data-key="view" `
                +   'class="action-button logged-in-only ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only">'
                +     '<span class="ui-button-icon-primary ui-icon ui-icon-search"></span><span class="ui-button-text"></span>'
                + `</div>&nbsp;&nbsp;${url_icon}`;
        }
        
        /*+ '<div title="Click to view record (opens in popup)" '
        + '   class="rec_view_link ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only" '
        + '   role="button" aria-disabled="false">'
        +     '<span class="ui-button-icon-primary ui-icon ui-icon-comment"></span><span class="ui-button-text"></span>'
        + '</div>'*/
        html = html + '</div>';

        return html;
        
    },

    _onActionListener: function(event, action){

        let is_resolved = this._super(event, action);

        if(is_resolved){
            return true;
        }else if(!window.hWin.HEURIST4.util.isObject(action)){
            return false;
        }

        let ulf_ID = action.recID;
        action = action.action;

        is_resolved = true;

        switch (action) {

            case 'view':

                let popup_opts = {
                    isdialog: true, 
                    select_mode: 'manager',
                    edit_mode: 'editonly',
                    rec_ID: ulf_ID,
                    default_palette_class: 'ui-heurist-populate',
                    width: 950
                };

                window.hWin.HEURIST4.ui.showEntityDialog('recUploadedFiles', popup_opts);

                break;

            case 'url':

                let recordset = this.getRecordSet();
                let record = recordset.getById(ulf_ID);
                let ulf_ObfuscatedFileID = recordset.fld(record, 'ulf_ObfuscatedFileID');

                window.hWin.HEURIST4.util.copyStringToClipboard(`${window.hWin.HAPI4.baseURL_pro}?db=${window.hWin.HAPI4.database}&file=${ulf_ObfuscatedFileID}`);

                window.hWin.HEURIST4.msg.showMsgFlash('Copied URL to clipboard', 3000);

                break;

            default:

                is_resolved = false;

                break;
        }

        return is_resolved;
    },

    addEditRecord: function(recID, is_proceed){

        if(recID<0){
            if(this._additionMode=='local'){
                this.options['edit_title'] = 'Upload file';
            }else if(this._additionMode=='remote'){
                this.options['edit_title'] = 'Specify external file/URL';
            }else if(this._additionMode=='tiled'){
                this.options['edit_title'] = 'Upload tiled image stack';
            }else{
                this.options['edit_title'] = 'Upload file, Select existing or specify URL';
            }
        }else{
            this.options['edit_title'] = null;
        }
        
        this._super(recID, is_proceed);
    },
    
    //
    // open empty edit dialog
    // upload file (editing_input type file - uploads file, register it and get new ulf_ID)
    // get new ulf_ID
    // open edit form with this new record
    //
    _uploadFileAndRegister: function( is_tiled ){
        
        //find file element
        let that = this;
        
    
        function __initEditForm_cont(){
                //init hidden edit form  that contains the only field - file uploader
                that._editing_uploadfile.initEditForm([{
                        "dtID": "ulf_FileUpload",
                        "dtFields":{
                            "dty_Type":"file",
                            "rst_DisplayName":"File upload:",
                            "rst_FieldConfig":{"entity":"recUploadedFiles", 
                                               "registerAtOnce":1, 
                                               "tiledImageStack": (is_tiled===true || that._additionMode=='tiled')?1:0},
                            "dty_Role":"virtual",
                            "rst_Display":"hidden"
                        }
                    }], null);
                that._editing_uploadfile.getContainer().hide(); //this form is hidden
                let ele = that._editing_uploadfile.getFieldByName('ulf_FileUpload');    
                ele.find('.fileupload').trigger('click'); //open file select dialog
        }        
        
        if(!this._editing_uploadfile){ //form is not yet defined

                let container = $('<div>').css({width:0,height:0}).appendTo(this.editForm.parent());
                
                this._editing_uploadfile = new HEditing({entity:this.options.entity, container:container, 
                 onchange:
                function(){
                    //registerAtOnce is true, so we get new file id
                    
                    let ele = that._editing_uploadfile.getFieldByName('ulf_FileUpload');
                    let res = ele.editing_input('getValues'); 
                    let ulf_ID = res[0];

                    if(ulf_ID>0){
                        if(that.options.edit_addrecordfirst){
                            
                            let fields = that._editing_uploadfile.getValues(false);     
                            fields['ulf_ID'] = (''+ulf_ID);
                            that._afterSaveEventHandler(ulf_ID, fields ); //trigger onselect
                            
                           
                           
                        }else{
                            that._currentEditID = null;//to prevent warn about save
                            that.addEditRecord(ulf_ID, true);
                        }
                    }
                },
                oninit:function(){
                    that._editing_uploadfile = this;
                    __initEditForm_cont();        
                }}); //pass container
                return;
        }
            
        __initEditForm_cont();        
    },

    
    _saveEditAndClose: function(fields, afterAction, ignoreCheck){

        let that = this;
        
        const ignoreCheck2 = true; //ARTEM 2021-07-27 - always ignore check for rendereability

        if(this._previousURL && !ignoreCheck2){

            let ele = this._editing.getFieldByName('ulf_MimeExt');
            let extension = ele.editing_input("getValues");

            if(!window.hWin.HEURIST4.util.isempty(extension) && extension[0] == "bin"){

                let btns = {};

                btns[window.hWin.HR('Re-Specify URL')] = function(){
                    let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                    $dlg.dialog('close');
                };
                btns[window.hWin.HR('Accept as is')] = function(){
                    let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                    $dlg.dialog('close');
                    that._saveEditAndClose(fields, afterAction, true);
                };

                let labels = {};

                labels['title'] = 'Invalid URL for renderable media';
                labels['no'] = window.hWin.HR('Re-Specify URL');
                labels['yes'] = window.hWin.HR('Accept as is');

                window.hWin.HEURIST4.msg.showMsgDlg(
                      'This file cannot be rendered as an image or playable media.<br>'
                    + 'You may have referenced a web page (e.g. htm or html) rather than an image or video.<br>'
                    + 'The URL may appear to have a file extension, but you have most likely referenced a wrapper for the file.<br>'
                    + 'You must reference the file directly.<br>',
                    btns, labels,
                    {default_palette_class: 'ui-heurist-populate'}
                );
            }
				
        }else{
            
            if(this._additionMode=='tiled'){
                //check mime type
                let ele = this._editing.getFieldByName('ulf_MimeExt');
                let extension = ele.editing_input("getValues");
                if(!extension || !extension[0] || 
                    extension[0].match(/(gif|jpg|jpeg|png|json)?/i)[0]=='')
                {
                    window.hWin.HEURIST4.msg.showMsgDlg(  
                        'You have to define the correct image type for tile image stack (normally image/jpeg or png)',
                        null, null,
                        {default_palette_class: 'ui-heurist-populate'});
                    return;
                }
                
                this._editing.setFieldValueByName2('ulf_OrigFileName',extension[0]=='json'?'':'_tiled',false);
            }

            this._super(fields, afterAction);
        }

    },	
    
    //
    //
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){
    

        if(recID>0){
            
            if(this.options.edit_addrecordfirst){
                    
                this._currentEditID = null; //to avoid warning
                this.editFormPopup.dialog('close');
               
            }
            if(this.options.edit_addrecordfirst || this._additionMode=='local'){ //close dialog after save
            
                this._additionMode = null; //reset
                if(this.options.select_mode=='select_single'){
                    this._selection = new HRecordSet();
                    this._selection.addRecord(recID, fieldvalues);
                    this._selectAndClose();
                    return;        
                }else if(this.searchForm && this.searchForm.searchRecUploadedFiles('instance')){ // trigger search refresh
                    
                    let domain = (window.hWin.HEURIST4.util.isempty(fieldvalues['ulf_ExternalFileReference']))?'local':'external';
                    //it was insert - select recent and search
                    this.searchForm.searchRecUploadedFiles('searchRecent', domain);
                }else if(this.options.select_mode == 'manager' && this.options.edit_mode == 'editonly'){

                    // update cached record set
                    this._lastFileDetails = new HRecordSet();
                    this._lastFileDetails.addRecord(recID, fieldvalues);
                }
            }
        }
        this._super( recID, fieldvalues );
    },
    
    _deleteAndClose: function(unconditionally){
    
        if(unconditionally===true){
            this._super(); 
        }else{
            
            let that = this;
            
            //get full field info to update local definitions
            const request = {
                'a': 'batch',
                'entity': this.options.entity.entityName,
                'get_media_records': this._currentEditID};
            
            window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    let recs = response.data;
                    if(recs.length==0){
                        
                        window.hWin.HEURIST4.msg.showMsgDlg(
                            'Are you sure you wish to delete this file?', function(){ that._deleteAndClose(true) }, 
                            {title:'Warning',yes:'Proceed',no:'Cancel'},
                            {default_palette_class:that.options.default_palette_class});        
                        
                    }else{
                        let url = window.hWin.HAPI4.baseURL + "?db=" + window.hWin.HAPI4.database + "&q=ids:"+recs.join(',') + '&nometadatadisplay=true';
                        
                        window.hWin.HEURIST4.msg.showMsgDlg(
                        ((recs.length==1)?'There is a reference':('There are '+recs.length+' references'))
                        +' from record(s) to this File.<br>You must delete the records'
                        +' or the File field values in order to be able to delete the file.'
                        +'<br><br>Click <a href="#" onclick="window.open(\''+url+'\', \'_blank\');">here</a> '
                        +'for records which reference this image',null,
                        {title:'Delete blocked'},
                        {default_palette_class:that.options.default_palette_class});
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            });
            
            
        }
    },

    //
    // Download file references for current resultset
    //
    _downloadFileRefs: function(_download_entire_set){
        
        if(!this._checkUserPermissions(1)){
            return;
        }
        
        let ids = 'all';
        
        if(_download_entire_set){
            ids = 'all'
        }else{
        
            ids = this._getSelected(0);
            
            const that = this;

            if(!window.hWin.HEURIST4.util.isArrayNotEmpty(ids)){
                window.hWin.HEURIST4.msg.showMsg('There are not selected files/url references. Download CSV for entire set?',
                            {buttons:function(){that._downloadFileRefs(true);}, 
                            labels:{title:'Warning',yes:'Proceed',no:'Cancel'},
                            default_palette_class:this.options.default_palette_class});
                    
                return;
            }else if(ids.length > 5000){
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: "Please limit selection to approximately 5000 files, or else select none, and<br>"
                            +"click the download again, in which case the information for all files is downloaded.",
                    error_title: "Too many files selected",
                    status: window.hWin.ResponseStatus.ACTION_BLOCKED
                });
                return;
            }
            ids = ids.join(',');
        }

        let url = `${window.hWin.HAPI4.baseURL}hserv/controller/record_output.php?db=${window.hWin.HAPI4.database}&file_refs=1&ids=${ids}`;
        window.open(url, '_blank');
    },

    //
    // Prepare details + upload a local file to the selected external repository
    //
    _handleExternalRepository: function(){

        let that = this;
        let selected_repo = this._edit_dialog.find('#external_repos').val();
        let uploaded_file = this._edit_dialog.find('#upload_file_repository').attr('filename');

        if((selected_repo == '' && uploaded_file == '' || that._last_upload_details.length == 0) ){
            return;
        }

        switch (selected_repo) {
            case 'Nakala':
            {
                let $dlg;
                let content = '<div style="margin-bottom: 15px;">' // Warning text
                                + '<strong>Please note</strong>, that in order for Heurist to utilise the uploaded file as a remote resource from Nakala that it will be published on Nakala.<br>'
                                + 'Please <strong>DO NOT</strong> upload personal or private files, or any documents you do not wish to be publicly available.<br>'
                                + 'We also recommend that you check the uploaded file\'s metadata on the Nakala website afterwards.'
                            + '</div>'

                            // Form content
                            + '<fieldset>'

                                + '<div>'
                                    + '<div class="header required" style="vertical-align: top; display: table-cell;"><label>Account:</label></div>'
                                    + '<span class="editint-inout-repeat-button" style="min-width: 22px;"></span>'
                                    + '<div class="input-cell" style="padding-bottom: 12px;">'
                                        + '<select class="text ui-widget-content ui-corner-all" id="account"></select>'
                                        + '<span id="acc-helper" class="heurist-helper1" style="display:inline-block;width:300px;padding-bottom:5px;">'
                                            + 'Please use one of these for testing purposes. If you use a personal login and API code the data will be permanently stored in Nakala'
                                        + '</span>'
                                        + '<br>'
                                        + '<span class="heurist-helper1">Select a Nakala account to use</span>'
                                    + '</div>'
                                + '</div>'

                                + '<div id="nakala-url">'
                                    + '<div class="header recommended" style="vertical-align: top; display: table-cell;"><label>Use test server:</label></div>'
                                    + '<span class="editint-inout-repeat-button" style="min-width: 22px; display: table-cell;"></span>'
                                    + '<div class="input-cell" style="padding-bottom: 12px;">'
                                        + '<input type="checkbox" class="text ui-widget-content ui-corner-all" id="use_test_url">'
                                        + '<br>'
                                        + '<span class="heurist-helper1">Use Nakala\'s test server, instead of the standard version</span>'
                                    + '</div>'
                                + '</div>'

                                + '<div>'
                                    + '<div class="header required" style="vertical-align: top; display: table-cell;"><label>File title:</label></div>'
                                    + '<span class="editint-inout-repeat-button" style="min-width: 22px; display: table-cell;"></span>'
                                    + '<div class="input-cell" style="padding-bottom: 12px;">'
                                        + '<input class="text ui-widget-content ui-corner-all" id="title">'
                                        + '<span class="heurist-helper1">Nakala record title</span>'
                                    + '</div>'
                                + '</div>'

                                + '<div>'
                                    + '<div class="header recommended" style="vertical-align: top; display: table-cell;"><label>Creator\'s name:</label></div>'
                                    + '<span class="editint-inout-repeat-button" style="min-width: 22px; display: table-cell;"></span>'
                                    + '<div class="input-cell" style="padding-bottom: 12px;">'
                                        + '<label>First name: <input class="text ui-widget-content ui-corner-all" id="fcreator"></label>'
                                        + '<label style="margin-left: 10px;">Last name: <input class="text ui-widget-content ui-corner-all" id="lcreator"></label> <br><br>'
                                        + '<label>Author ID: <input class="text ui-widget-content ui-corner-all" id="idcreator" style="margin-left:5px;"></label>'
                                        + '<span class="ui-icon ui-icon-search" id="lookup_author" style="vertical-align:bottom;padding-left:10px;cursor:pointer;">&nbsp;</span>'
                                        + '<label style="display: none !important;">orcid <input id="orcid"></label>'
                                        + '<br><span class="heurist-helper1">Full name of the person who created this file<br>Can be blank</span>'
                                    + '</div>'
                                + '</div>'

                                + '<div>'
                                    + '<div class="header recommended" style="vertical-align: top; display: table-cell;"><label>Year/date created:</label></div>'
                                    + '<span class="editint-inout-repeat-button" style="min-width: 22px; display: table-cell;"></span>'
                                    + '<div class="input-cell" style="padding-bottom: 12px;">'
                                        + '<input class="text ui-widget-content ui-corner-all" id="created">'
                                        + '<br><span class="heurist-helper1">Date of file creation (YYYY-MM-DD, YYYY-MM, or YYYY)<br>Can be blank</span>'
                                    + '</div>'
                                + '</div>'

                                + '<div>'
                                    + '<div class="header required" style="vertical-align: top; display: table-cell;"><label>File type:</label></div>'
                                    + '<span class="editint-inout-repeat-button" style="min-width: 22px; display: table-cell;"></span>'
                                    + '<div class="input-cell" style="padding-bottom: 12px;">'
                                        + '<select class="text ui-widget-content ui-corner-all" id="type"></select>'
                                        + '<br><span class="heurist-helper1">Type of file being uploaded</span>'
                                    + '</div>'
                                + '</div>'

                                + '<div>'
                                    + '<div class="header required" style="vertical-align: top; display: table-cell;"><label>License:</label></div>'
                                    + '<span class="editint-inout-repeat-button" style="min-width: 22px; display: table-cell;"></span>'
                                    + '<div class="input-cell" style="padding-bottom: 12px;">'
                                        + '<select class="text ui-widget-content ui-corner-all" id="license"></select>'
                                        + '<br><span class="heurist-helper1">License for file being uploaded</span>'
                                    + '</div>'
                                + '</div>'

                            + '</fieldset>';

                let btns = {};
                btns['Proceed'] = () => {

                    let title = $dlg.find('#title').val();
                    let type = $dlg.find('#type').val();
                    let fname = $dlg.find('#fcreator').val();
                    let lname = $dlg.find('#lcreator').val();
                    let authorid = $dlg.find('#idcreator').val();
                    let orcid = $dlg.find('#orcid').val();
                    let created = $dlg.find('#created').val();
                    let license = $dlg.find('#license').val();
                    let account = $dlg.find('#account').val();

                    if(window.hWin.HEURIST4.util.isempty(account)){
                        window.hWin.HEURIST4.msg.showMsgFlash('Please select an account to use...', 3000);
                        return;
                    }else
                    if(window.hWin.HEURIST4.util.isempty(title) || window.hWin.HEURIST4.util.isempty(type) || window.hWin.HEURIST4.util.isempty(license)){
                        window.hWin.HEURIST4.msg.showMsgFlash('Please make sure the required fields are filled', 3000);
                        return;
                    }

                    // Check type
                    if(type.indexOf('http') === -1){
                        type = 'http://purl.org/coar/resource_type/' + type;
                    }

                    // Validate created
                    if(!window.hWin.HEURIST4.util.isempty(created)){

                        let length_valid = (created.length == 4 || created.length == 7 || created.length == 10);
                        let dash_valid = (created.length == 4 || created.indexOf('-') !== false);
                        let year_valid = (created.length == 4 && Number.isNaN(created));
                        let err_msg = '';

                        if(!length_valid || !year_valid){
                            err_msg = 'Invalid Year/date created';
                        }else if(!dash_valid){
                            err_msg = 'Year/date created should be dash separated';
                        }else if(created.length > 4){

                            let date = new Date(created);
                            let time = date.getTime();
                            if(Number.isNaN(time)){
                                err_msg = 'Invalid Year/date created';
                            }
                        }

                        if(err_msg != ''){
                            window.hWin.HEURIST4.msg.showMsgFlash(err_msg, 3000);
                            $dlg.find('#created').trigger('focus');
                            return;
                        }
                    }else{
                        created = null;
                    }

                    let request = {
                        a: 'upload_file_nakala',
                        db: window.hWin.HAPI4.database,
                        request_id: window.hWin.HEURIST4.util.random(),
                        file: that._last_upload_details,
                        meta: { // required meta data
                            title: title,
                            creator: {
                                givenname: fname,
                                surname: lname,
                                orcid: orcid == '' ? null : orcid,
                                authorId: authorid
                            },
                            created: created,
                            type: type,
                            license: license
                        },
                        api_key: account,
                        use_test_url: $dlg.find('#use_test_url').is(':checked') || account.indexOf('_') == -1 ? 1 : 0
                    };

                    window.hWin.HEURIST4.msg.bringCoverallToFront(that._edit_dialog);

                    window.hWin.HAPI4.SystemMgr.upload_to_nakala(request, function(response){

                        window.hWin.HEURIST4.msg.sendCoverallToBack();
                        if(response.status == window.hWin.ResponseStatus.OK){ // returned URL

                            /*that._editing.setFieldValueByName2('ulf_ExternalFileReference', response.data, false);
                            let ele = that._editing.getFieldByName('ulf_ExternalFileReference');
                            if(ele.editing_input('instance') !== undefined){ // trigger blur event
                                ele.editing_input('getInputs')[0].trigger('blur');
                            }*/

                            that._afterExternalUpload(response.data);
                            $dlg.dialog('close');
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
                };
                btns['Cancel'] = () => {
                    $dlg.dialog('close');
                };

                $dlg = window.hWin.HEURIST4.msg.showMsgDlg(content, btns, {title: 'Prepare file metadata'}, {default_palette_class: 'ui-heurist-publish', dialogId: 'nakala_metadata'});

                // Click handler for author search
                $dlg.find('#lookup_author').on('click',() => {
                    let dlg_opts = {
                        mapping: {
                            dialog: 'lookupNakalaAuthor',
                            service: 'nakala_author',
                            service_id: 'nakala_author_gen',
                            fields: {
                                givenname: 1,
                                surname: 2,
                                orcid: 3,
                                authorId: 4,
                                fullName: 5 // ignore field
                            },
                            rty_ID: 0
                        }, 
                        path: 'widgets/lookup/',
                        onClose: (recset) => {
                            if(Object.keys(recset).length > 0){ // has response
                                $dlg.find('#fcreator').val(recset[1]);
                                $dlg.find('#lcreator').val(recset[2]);
                                $dlg.find('#idcreator').val(recset[4]);
                                $dlg.find('#orcid').val(recset[3]);
                            }
                        }
                    };
                    window.hWin.HEURIST4.ui.showRecordActionDialog('lookupNakalaAuthor', dlg_opts);
                });

                // Hide dialog while getting license and type values
                $dlg.dialog('widget').hide();

                // Enter default values
                let file_title = that._last_upload_details[0].original_name.split('.'); // remove extension
                file_title.pop();
                $dlg.find('#title').val(file_title.join('.')); // title

                let fullname = window.hWin.HAPI4.currentUser.ugr_FullName.split(' ');
                $dlg.find('#fcreator').val(fullname[0]);
                $dlg.find('#lcreator').val((fullname.length == 2) ? fullname[1] : '');

                let request = {
                    serviceType: 'nakala',
                    service: 'nakala_get_metadata' // file types used by Nakala
                };
                window.hWin.HAPI4.RecordMgr.lookup_external_service(request, (data) => {

                    data = window.hWin.HEURIST4.util.isJSON(data);
                    let can_assign = 0;

                    if(data.status && data.status != window.hWin.ResponseStatus.OK){
                        $dlg.dialog('close');
                        window.hWin.HEURIST4.msg.showMsgErr(data);
                        return;
                    }

                    let selected_type = 'c_1843'; // other - by default
                    let $select = $dlg.find('#type'); 
                    if(Object.hasOwn(data, 'types') && Object.keys(data['types']).length > 0){

                        $.each(data['types'], (idx, type) => {

                            window.hWin.HEURIST4.ui.addoption($select[0], type[1], type[0]);

                            if(that._last_upload_details && that._last_upload_details.length > 0 
                                && that._last_upload_details[0].type.indexOf(type[0].toLowerCase()) !== -1)
                            {
                                selected_type = type[1];
                            }
                        });
                        window.hWin.HEURIST4.ui.initHSelect($select, false);
                        can_assign ++;
                    }
                    $select.val(selected_type).hSelect('refresh');

                    $select = $dlg.find('#license');
                    if(Object.hasOwn(data, 'licenses') && data['licenses'].length > 0){
                        $.each(data['licenses'], (idx, license) => {
                            window.hWin.HEURIST4.ui.addoption($select[0], license, license);
                        });
                        window.hWin.HEURIST4.ui.initHSelect($select, false);
                        can_assign ++;
                    }

                    if(can_assign == 2){
                        $dlg.dialog('widget').show();
                    }else{
                        $dlg.dialog('close');
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: 'An unknown error has occurred while attempting to retrieve the data types and license metadata values.<br>'
                                    +'If this problem persists, please contact the Heurist team.',
                            error_title: 'Unable to retrieve Nakala metadata values',
                            status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                        });
                    }
                });

                let $accounts = $('#account');
                $.each(that._external_repositories[selected_repo], (idx, details) => {

                    let lbl = `${details[0].indexOf('_') >= 0 ? '' : 'Test - '}${details[3]}`;
                    window.hWin.HEURIST4.ui.addoption($accounts[0], details[0], lbl);
                });

                window.hWin.HEURIST4.ui.initHSelect($accounts, false, null, {
                    onSelectMenu: () => {

                        let value = $accounts.val();

                        value != '' && value.indexOf('_') >= 0 ? $dlg.find('#nakala-url').show() : $dlg.find('#nakala-url').hide();
                        value != '' && value.indexOf('_') >= 0 ? $dlg.find('#acc-helper').hide() : $dlg.find('#acc-helper').show();
                    }
                });

                $accounts.trigger('change');

                break;
            }    
            default:
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: `The external service "${selected_repo}" is not supported.`,
                    error_title: 'Service not supported',
                    status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                });
                break;
        }
    },

    //
    // Register new external file
    //
    _afterExternalUpload: function(external_url){

        let that = this;

        if(window.hWin.HEURIST4.util.isempty(external_url)){
            return;
        }

        let request = {
            'a': 'batch',
            'entity': 'recUploadedFiles',
            'request_id': window.hWin.HEURIST4.util.random(),
            'regExternalFiles': JSON.stringify({0: [external_url]})
        };

        window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){

            if(response.status == window.hWin.ResponseStatus.OK){

                let file_data = response.data;

                let invalid_file = '';
                let error_save = '';
                let error_id = '';

                let cur_file = file_data[0];
                if(cur_file['err_save']){
                    error_save += '<br>' + cur_file['err_save'].join('<br>');
                    delete cur_file['err_save'];
                }
                if(cur_file['err_id']){
                    error_id += '<br>' + cur_file['err_id'].join('<br>');
                    delete cur_file['err_id'];
                }
                if(cur_file['err_invalid']){
                    for(let f_id in cur_file['err_invalid']){
                        invalid_file += '<br>' + f_id + ' => ' + cur_file['err_invalid'][f_id];
                    }
                    delete cur_file['err_invalid'];
                }

                if(invalid_file != '' || error_save != '' || error_id != ''){
                    let msg = '';

                    if(invalid_file != ''){
                        msg += 'URL is invalid: ' + invalid_file;
                    }
                    if(error_save != ''){
                        msg += 'Failed to save url: ' + error_save;
                    }
                    if(error_id != ''){
                        msg += 'Unable to retrieve File details for ' + invalid_file;
                    }

                    let $err_dlg;
                    $err_dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, {'Ok': () => { 
                        $err_dlg.dialog('close');
                    }}, {title: 'File saving error'}, {default_palette_class: 'ui-heurist-explore'});
                }else{
                    that._afterSaveEventHandler(cur_file['ulf_ID'], cur_file);
                }
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    },

    //
    // Merge duplicate files into one record
    //
    _combineDups: function(){

        let that = this;

        if(!this._checkUserPermissions(1)){
            return;
        }
        
        const session_id = window.hWin.HEURIST4.msg.showProgress( {interval:900, 
            steps:['Uploaded files by fullpath','External resources','Uploaded files by name and checksum','Merging, Update reference records, Delete']} );

        let request = {
            'session': session_id,
            'a': 'batch',
            'entity': that.options.entity.entityName,
            'merge_duplicates': 'all' //ids
        };

        window.hWin.HAPI4.EntityMgr.doRequest(request, 
        function(response){
            
            window.hWin.HEURIST4.msg.hideProgress();
                
            if(response.status == window.hWin.ResponseStatus.OK){
                
                let res = response.data;
                let msg = '';

                if(res?.local > 0){
                    msg += res.local + ' duplications by fullpath<br><br>';
                } 
                if(res?.local_checksum > 0){
                    msg += res.local_checksum + ' duplications by checksum and original file name<br><br>';
                } 
                if(res?.remote > 0){
                    msg += res.remote + ' duplications for remote resources<br>';
                } 
                if(res?.tumbnails > 0){
                    msg += res.tumbnails + ' redundant thumbnails<br>';
                } 

                if(msg == ''){
                    window.hWin.HEURIST4.msg.showMsgFlash('No duplicates found', 3000);
                }else{
                    msg = `<div style="font-size:1.em">${msg}</div>`;
                    window.hWin.HEURIST4.msg.showMsg(msg, {title:window.hWin.HR('Combine duplicates')});
                    that.searchForm.searchRecUploadedFiles('searchRecent', null); // refresh    
                }
                
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    },

    //
    //
    //
    _refreshIndex: function(){
        let that = this;
        window.hWin.HEURIST4.ui.showRecordActionDialog('recordUploadedFilesIndex',
            {onClose:()=>that.searchForm.searchRecUploadedFiles('searchRecent', null)});
    },
    
    _getSelected: function(limit){
        
        let ids;
        
        if(this._selectAllFiles){
            ids = this.getRecordSet().getIds();
        }else{
            ids = this.recordList ? this.recordList.resultList('getSelected', true) : [];
        }

        if(limit>0){
            if(!window.hWin.HEURIST4.util.isArrayNotEmpty(ids)){
                window.hWin.HEURIST4.msg.showMsgFlash('Select some files first...', 4000);
                return false;
            }else if(ids.length > limit){
                window.hWin.HEURIST4.msg.showMsgFlash('The number of selected files exceeds the limit', 2000);
                return false;
            }
        }
        
        return ids;
        
    },
    
    //
    //
    //
    _showMediaRecords: function(){

        let ids = this._getSelected(5000);
        if(!ids){
            return;
        }        

        let request = {
            'a': 'batch',
            'entity': this.options.entity.entityName,
            'get_media_records': ids.join(',')
        };

        window.hWin.HAPI4.EntityMgr.doRequest(request, 
        function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
                
                let rec_ids = response.data;

                if(rec_ids?.length > 0){
                    
                    if(rec_ids.length > 5000){
                        window.hWin.HEURIST4.msg.showMsgFlash('The number ('+rec_ids.length
                            +') of referencing records exceeds the limit. Try to reduce the number of selected files', 2000);
                    }else{                    
                        let url = window.hWin.HAPI4.baseURL+"?db="+window.hWin.HAPI4.database+"&q=ids:"+rec_ids.join(',');
                        window.open(url, '_blank');
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgFlash('No referencing records found for selected files/urls');
                    
                }
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });        
    },
    
    //
    //
    //
    _createMediaRecords: function(){

        let that = this;

        if(!this._checkUserPermissions(1)){
            return;
        }
        
        let ids = this._getSelected(5000);
        if(!ids){
            return;
        }        

        let request = {
            'a': 'batch',
            'entity': that.options.entity.entityName,
            'create_media_records': ids.join(',')
        };

        window.hWin.HAPI4.EntityMgr.doRequest(request, 
        function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
                
                //returns ('new' => ids of new records, 'error' , 'skipped')
                
                let counts = response.data;
                let msg = '';
                if(counts['new'].length > 0){
                    
                    let url = window.hWin.HAPI4.baseURL+"?db="+window.hWin.HAPI4.database+"&q=ids:"+counts['new'].join(',');
                    msg = counts['new'].length 
                            + ' new media record(s) created (search <a href="#" onclick="window.open(\''+url+'\', \'_blank\')">here</a>)<br>';
                }
                if(counts.skipped > 0){
                    msg += counts.skipped + ' selected file/url reference already have a media record<br>';
                }
                if(counts.error.length > 0){
                    msg += counts.error.length + ' skipped due to an error (file id(s): '+ counts.error.join(', ') +')';
                }

                window.hWin.HEURIST4.msg.showMsgDlg(msg, null, null, {default_palette_class: 'ui-heurist-admin'});
                //that.searchForm.searchRecUploadedFiles('searchRecent', null); // refresh - not need in this case
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    },

    //
    //
    //
    _checkUserPermissions: function(level){

        if(!window.hWin.HAPI4.has_access(level)){

            let msg = 'You do not have permission to perform this action';
            msg = (level == 1) ? 'You must be an administrator of the database managers group to use this feature' : msg;
            
            window.hWin.HEURIST4.msg.showMsgErr({
                message: msg,
                error_title: 'Invalid permissions'
            });
                    
            return false;
        }

        return true;
    },

    //
    //
    //
    contextOnClose: function(){
        return this._lastFileDetails;
    },
    
    //
    //
    //
    _checkFiles: function(){
        
        /*
        let body = $(window.hWin.document).find('body');

        let screen_height = window && window.innerHeight && window.innerHeight > body.innerHeight() ? 
                            window.innerHeight : body.innerHeight();
        let opts = {height:screen_height*0.8, width:body.innerWidth()*0.8};
        */

        window.hWin.HEURIST4.msg.showDialog(
            `${window.hWin.HAPI4.baseURL}admin/verification/longOperationInit.php?type=files&db=${window.hWin.HAPI4.database}`
            , {height:'80%', width:'80%'} );                
    },
    
    //
    //
    //
    _deleteSelected: function(){

        let that = this;

        if(!this._checkUserPermissions(1)){
            return;
        }

        let ids = this._getSelected(100);
        if(!ids){
            return;
        }        
        
        let request = {
            'a': 'batch',
            'entity': that.options.entity.entityName,
            'delete_selected': ids.join(','),
            'mode': 'get'
        };

        window.hWin.HAPI4.EntityMgr.doRequest(request, 
        function(response){
            if(response.status == window.hWin.ResponseStatus.OK){

                const files = response.data.files;
                
                let msg = '<br>You must delete the records or the File field values in order to be able to delete selected files/urls.'
                        +'<br><br>Click <a href="#" id="show_referencing">here</a> '
                        +'for records which reference selected files/urls';
                let $dlg;
                        
                if(Object.keys(files).length > 0){

                    const keys = Object.keys(files);
                    
                    if(response.data.cnt_in_use>0){
                        msg = `${response.data.cnt_in_use} files are referencing in ${response.data.cnt_ref_recs} records. ${msg}<br><br>`;
                    }else{
                        msg = '';
                    }
                    
                    msg = msg + keys.length + ' file(s) are ready to delete: <br>';

                    let del_func = function() {

                        let req = {
                            'a': 'batch',
                            'entity': that.options.entity.entityName,
                            'delete_selected': keys.join(','),
                            'mode': 'delete'
                        };

                        window.hWin.HAPI4.EntityMgr.doRequest(req, function(res){
                            if(res.status == window.hWin.ResponseStatus.OK){
                                let text = '';
                                if(res.data > 0){
                                     text = res.data + ' file' + (res.data > 1 ? 's' : '') + ' deleted';
                                }
                
                                window.hWin.HEURIST4.msg.showMsgFlash(text, 3000);
                                that.searchForm.searchRecUploadedFiles('searchRecent', null); // refresh
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(res);
                            }
                        });
                    };

                    for (const ulf_ID in files) {
                        if (Object.hasOwn(files, ulf_ID)) {

                            const file = files[ulf_ID];
                            let name = file['filename'];
                            let extra = '';

                            if(name == '_remote'){
                                name = file['url'];
                                extra = '<a href="'+ name +'" target="_blank"><span class="ui-icon ui-icon-extlink"></span></a>';
                            }

                            msg += '<br><span class="file-line" data-fid="'+ ulf_ID +'">'+ name +'</span> ' + extra;
                        }
                    }

                    msg += '<br><br>The above files will be <strong>deleted and unavailable for use</strong> unless it is re-uploaded to Heurist.<br>Do you wish to proceed?';

                    $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, del_func, {title: 'Deleting files'}, {default_palette_class: 'ui-heurist-admin'});

                    $dlg.parent().css('min-width', '250px');
                    $dlg.find('span.file-line').css({'display': 'inline-block', 'margin-bottom': '5px', 'text-decoration': 'underline', 'cursor': 'pointer'}).on('click', function(event){
                        const id = $(this).attr('data-fid');

                        const popup_opts = {
                            isdialog: true, 
                            select_mode: 'manager',
                            edit_mode: 'editonly',
                            rec_ID: id,
                            default_palette_class: 'ui-heurist-populate',
                            width: 950,
                            onClose: () => {}
                        };

                        window.hWin.HEURIST4.ui.showEntityDialog('recUploadedFiles', popup_opts);
                    });
                }else{
                    $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                        'Nothing to delete. All selected files have referencing records.'+msg,null,
                        {title:'Delete blocked'},
                        {default_palette_class:that.options.default_palette_class});
                }
                that._on($dlg.find('#show_referencing'),{click:that._showMediaRecords});
                
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    },
    
    
});
