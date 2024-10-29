/**
* Widget for input controls on edit form
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

/*global Temporal, TDate, fixCalendarPickerCMDs, temporalToHumanReadableString, tinymce, EditorCodeMirror, 
translationSupport, selectRecord,browseRecords,browseTerms, correctionOfInvalidTerm, calculateImageExtentFromWorldFile */

$.widget( "heurist.editing_input", {

    // default options
    options: {
        varid:null,  //id to create imput id, otherwise it is combination of index and detailtype id
        recID: null,  //record id for current record - required for relation marker and file
        recordset:null, //reference to parent recordset 
        editing:null, //reference to parent editing form

        rectypeID: null, //only for recDetail to get field description from $Db.rst
        dtID: null,      // field type id (for recDetails) or field name (for other Entities)

        //  it is either from window.hWin.HEURIST4.rectype.typedefs.dtFields - retrieved with help rectypeID and dtID
        // object with some mandatory field names
        dtFields: null,

        values: null,
        readonly: false,
        title: '',  //label (overwrite Display label from record structure)
        suppress_repeat: false, //true,false or 'force_repeat'
        showclear_button: true,
        showedit_button: true,
        show_header: true, //show/hide label
        suppress_prompts:false, //supress help, error and required features
        useHtmlSelect: false, //NOTE !!!! native select produce double space for option  Chrome touch screen
        detailtype: null,  //overwrite detail type from db (for example freetext instead of memo)
        
        change: null,  //onchange callback
        onrecreate: null, //onrefresh callback
        is_insert_mode:false,
        
        is_faceted_search:false, //is input used in faceted search or filter builder
        is_between_mode:false,    //duplicate input for freetext and dates for search mode 

        language: null, // language for term values (3 character ISO639-2 code)

        force_displayheight: null // for textareas
    },

    //newvalues:{},  //keep actual value for resource (recid) and file (ulfID)
    detailType:null,
    configMode:null, //configuration settings, mostly for enum (terms) and resouc=rce (record pointer) types (from field rst_FieldConfig)
    customClasses:null, //custom classes to manipulate visibility and styles in editing
       
    isFileForRecord:false,
    entity_image_already_uploaded: false,

    enum_buttons:null, // null = dropdown/selectmenu/none, radio or checkbox

    is_disabled: false,
    new_value: '', // value for new input

    linkedImgInput: null, // invisible textbox that holds icon/thumbnail value
    linkedImgContainer: null, // visible div container displaying icon/thumbnail
    
    selObj: null, //shared selector for all enum field values
    child_terms: null, // array of child terms for current vocabulary 
    _enumsHasImages: false, 
    
    is_sortable: false, // values are sortable

    block_editing: false,

    _external_relmarker: {
        target: null,
        relation: null,
        callback: null
    }, // pre-select a record target, possible relation type and setup a callback for relmarkers handled from external lookup

    // the constructor
    _create: function() {

        //for recDetails field description can be taken from $Db.rst
        if(this.options.dtFields==null && this.options.dtID>0 && this.options.rectypeID>0) //only for recDetails
        {
            this.options.dtFields = window.hWin.HEURIST4.util.cloneJSON($Db.rst(this.options.rectypeID, this.options.dtID));
        
            //field can be removed from rst - however it is still in faceted search
            if(this.options.dtFields==null){
               this.options.dtFields = {}; 
            }
            
            if(this.options.is_faceted_search){
                
                if(window.hWin.HEURIST4.util.isempty(this.options['dtFields']['rst_FilteredJsonTermIDTree'])){
                    this.options['dtFields']['rst_FilteredJsonTermIDTree'] = $Db.dty(this.options.dtID,'dty_JsonTermIDTree');
                } 
                if(window.hWin.HEURIST4.util.isempty(this.options['dtFields']['rst_PtrFilteredIDs'])){
                    this.options['dtFields']['rst_PtrFilteredIDs'] = $Db.dty(this.options.dtID,'dty_PtrTargetRectypeIDs');
                }
                this.options['dtFields']['rst_DefaultValue'] = '';
                this.options['dtFields']['rst_PointerMode'] = 'browseonly';
            }

        }

        if(window.hWin.HAPI4.sysinfo['dbconst']['DT_TIMELINE_FIELDS'] &&
           this.options.dtID==window.hWin.HAPI4.sysinfo['dbconst']['DT_TIMELINE_FIELDS']){

            this.options.detailtype = 'resource';
            this.options['dtFields']['rst_FieldConfig']= {entity:'DefDetailTypes',csv:true};
        }

        
        if(this.options.dtFields==null){ //field description is not defined
            return;
        }
        
        if(this.options.suppress_repeat=='force_repeat'){
            this.options['dtFields']['rst_MaxValues'] = 100;
            this.options.suppress_repeat = false;
        }
        
        this.detailType = this.options.detailtype ?this.options.detailtype :this.f('dty_Type');
        
        if((!(this.options.rectypeID>0)) && this.options.recordset){ //detect rectype for (heurist data) Records/recDetails
            this.options.rectypeID = this.options.recordset.fld(this.options.recID, 'rec_RecTypeID'); //this.options.recordset.getFirstRecord()
        }
        
        //custom classes to manipulate visibility and styles in editing space separated
        this.customClasses = this.f('rst_Class'); 
        if(!window.hWin.HEURIST4.util.isempty(this.customClasses)){
            this.element.addClass(this.customClasses);
        }
        
        //configuration settings, mostly for enum (terms) and resource (record pointer) types (from field rst_FieldConfig)
        this.configMode = this.f('rst_FieldConfig');
        if(!window.hWin.HEURIST4.util.isempty(this.configMode)){
            this.configMode = window.hWin.HEURIST4.util.isJSON(this.configMode);
            if(this.configMode===false) this.configMode = null;
        }
        //by default
        if((this.detailType=="resource" || this.detailType=='file') 
            && window.hWin.HEURIST4.util.isempty(this.configMode))
        {
            this.configMode= {entity:'records'};
        }

        this.isFileForRecord = (this.detailType=='file' && this.configMode.entity=='records');
        if(this.isFileForRecord){
            this.configMode = {
                    entity:'recUploadedFiles',
            };
        }

        let that = this;

        let required = "";
        if(this.isReadonly()) {
            required = "readonly";
        }else{
            if(!this.options.suppress_prompts && this.f('rst_Display')!='hidden'){
                required = this.f('rst_RequirementType');
            }
        }
        
        let lblTitle = (window.hWin.HEURIST4.util.isempty(this.options.title)?this.f('rst_DisplayName'):this.options.title);

        //header
        const is_show_header = true; // this.options.show_header
        if(is_show_header){ 
            this.header = $( "<div>")
            .addClass('header '+required)
            //.css('width','150px')
            .css({'vertical-align':'top'})  //, 'line-height':'initial'
            .html('<label>' + lblTitle + '</label>')
            .appendTo( this.element );

            // Apply user pref font size
            let usr_font_size = window.hWin.HAPI4.get_prefs_def('userFontSize', 0);
            if(usr_font_size != 0){
                usr_font_size = (usr_font_size < 8) ? 8 : (usr_font_size > 18) ? 18 : usr_font_size;
                this.header.css('font-size', usr_font_size+'px');
            }
        }
        
        this.is_sortable = false;
       
        //repeat button        
        if(this.isReadonly()) {

            //spacer
            $( "<span>")
            .addClass('editint-inout-repeat-button')
            .css({'min-width':'40px', display:'table-cell'})
            .appendTo( this.element );

        }else{

            //hardcoded list of fields and record types where multivalues mean translation (multilang support)
            let is_translation = this.f('rst_MultiLang') || 
               ((that.options.rectypeID==window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'] ||
                that.options.rectypeID==window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'])
                && that.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME']);
            
            //saw TODO this really needs to check many exist
            let repeatable = (Number(this.f('rst_MaxValues')) != 1  || is_translation)? true : false;
            
            if(!repeatable || this.options.suppress_repeat){  
                //spacer
                $( "<span>")
                .addClass('editint-inout-repeat-button editint-inout-repeat-container')
                .css({'min-width':'40px', display:'table-cell'})
                .appendTo( this.element );
                
            }else{ //multiplier button
            
                this.is_sortable = !that.is_disabled && !that.isReadonly() 
                        && (this.detailType!="relmarker") && !that.options.is_faceted_search; 
            
                let btn_cont = $('<span>', {class: 'editint-inout-repeat-container'})
                    .css({display:'table-cell', 'vertical-align':'top', //'padding-top':'2px',
                            'min-width':'22px',  'border-color':'transparent'})
                    .appendTo( this.element );

                //translation for text field only    
                let rec_translate = this.options.recordset && this.options.recordset.entityName == 'Records' && !is_translation
                                    && (this.detailType == 'freetext' || this.detailType == 'blocktext');

                let styles = {
                    display:'block', 
                    'font-size': (is_translation?'1em':'1.9em'), cursor:'pointer', 
                    //'vertical-align':'top', //'padding-top':'2px',
                    'min-width':(rec_translate ? '16px' : '22px'),
                    'margin-top': '5px',
                    //outline_suppress does not work - so list all these props here explicitely
                    outline: 'none','outline-style':'none', 'box-shadow':'none'
                }
                    
                this.btn_add = $( "<span>")
                    .addClass('smallbutton editint-inout-repeat-button ui-icon ui-icon-'
                        +(is_translation?'translate':'circlesmall-plus'))
                    .appendTo( btn_cont )
                //.button({icon:"ui-icon-circlesmall-plus", showLabel:false, label:'Add another ' + lblTitle +' value'})
                .attr('tabindex', '-1')
                .attr('title', 'Add another ' + window.hWin.HEURIST4.util.stripTags(lblTitle) +(is_translation?' translation':' value' ))                    
                .css(styles);

                if(rec_translate){ // add translate icon

                    styles['font-size'] = '1.1em';
                    styles['float'] = 'left';

                    $('<span>')
                        .addClass('smallbutton editint-inout-repeat-button ui-icon ui-icon-translate')
                        .attr('tabindex', '-1')
                        .attr('title', 'Add another translation')
                        .css(styles)
                        .prependTo(btn_cont);

                    this.btn_add = btn_cont.find('span');
                }

                if(this.detailType=="blocktext"){
                    this.btn_add.css({'margin-top':'3px'});    
                }
                
               
                
                // bind click events
                this._on( this.btn_add, {
                    click: function(event){

                        if(this.is_disabled) return;

                        if(is_translation){

                            if(typeof translationSupport!=='undefined' && window.hWin.HEURIST4.util.isFunction(translationSupport)){
                                translationSupport(this); //see editing_exts
                            }
                            
                        }else if($(event.target).hasClass('ui-icon-translate') && (that.detailType == 'freetext' || that.detailType == 'blocktext')){ // request language, then create new input with language prefix

                            let $dlg;
                            let msg = 'Language: <select id="selLang"></select><br>';

                            let first_val = that.inputs.length > 0 ? that.inputs[0].val() : '';

                            let btns = {};

                            let labels = {
                                title: window.HR('Insert translated value')
                            };

                            if(!window.hWin.HEURIST4.util.isempty(first_val) && window.hWin.HAPI4.sysinfo.api_Translator){ // allow external API translations

                                msg += '<span style="display:inline-block;margin-top:10px;">'
                                        + 'Translate will translate the first value'
                                        + 'You may block translation of some part of the text by adding an html tag with translate="no",<br>'
                                        + 'for example:  &lt;p translate=”no”&gt;text not to be translated&lt;/p&gt;'
                                    + '</span>';

                                btns[window.HR('Translate')] = function(){

                                    let source = '';
                                    let target = $dlg.find('#selLang').val();
    
                                    if(first_val.match(/^\w{3}:/)){ // check for a source language
    
                                        // Pass as source language
                                        source = first_val.match(/^\w{3}:/)[0];
                                        source = source.slice(0, -1);
    
                                        first_val = first_val.slice(4); // remove lang prefix
                                    }
    
                                    let request = {
                                        a: 'translate_string',
                                        string: first_val,
                                        target: target,
                                        source: source
                                    };

                                    window.hWin.HAPI4.SystemMgr.translate_string(request, function(response){

                                        $dlg.dialog('close');

                                        if(response.status == window.hWin.ResponseStatus.OK){
                                            that.new_value = target + ':' + response.data;
                                            $(that.btn_add[1]).trigger('click'); // 'click' normal repeat
                                            that.onChange(); // trigger change
                                        }else{
                                            window.hWin.HEURIST4.msg.showMsgErr(response);
                                        }
                                    });
                                };

                                labels['ok'] = window.HR('Translate');
                            }else{
                                msg += '<span style="display:inline-block;margin-top:10px;">'
                                        + 'To enable automatic translation please ask your system administrator to<br>'
                                        + 'add a Deepl free or paid account API key to Heurist configuration'
                                     + '</span>';
                            }

                            btns[window.HR('Insert blank')] = function(){
                                that.new_value = $dlg.find('#selLang').val() + ':';
                                $dlg.dialog('close');
                                $(that.btn_add[1]).trigger('click'); // 'click' normal repeat
                            };
                            labels['yes'] = window.HR('Insert blank');

                            btns[window.HR('Cancel')] = function(){
                                $dlg.dialog('close');
                            };
                            labels['cancel'] = window.HR('Cancel');

                            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, labels, {default_palette_class: 'ui-heurist-populate'});

                            window.hWin.HEURIST4.ui.createLanguageSelect($dlg.find('#selLang'), null, null, true);

                            $dlg.parent().find('.ui-dialog-buttonpane button').css({
                                'margin-left': '10px', 'margin-right': '10px'
                            });

                        }else{
                            
                            if(window.hWin.HEURIST4.util.isempty(this.new_value) && this.new_value != '') this.new_value = '';

                            if( !(Number(this.f('rst_MaxValues'))>0)  || this.inputs.length < this.f('rst_MaxValues')){
                                this._addInput(this.new_value);
                                this._refresh();
                                
                                if(window.hWin.HEURIST4.util.isFunction(this.options.onrecreate)){
                                    this.options.onrecreate.call(this);
                                }
                            }
                            
                        }
                }});
            }
            
            
            if(this.options.dtID != 'rst_DefaultValue_resource'){
                if(this.detailType=="resource" && this.configMode.entity=='records'){
                    
                    $('<div style="float:right;padding-top:1px;width: 14px;"><span class="ui-icon ui-icon-triangle-1-e"></span></div>')                
                        .appendTo( this.header );
                        this.header.css({'padding-right':0, width:154});
                        this.header.find('label').css({display:'inline-block', width: 135});
                        
                }else if(this.detailType=="relmarker"){
                    
                    $('<div style="float:right;padding-top:1px;width: 14px;"><span style="font-size:11px" class="ui-icon ui-icon-triangle-2-e-w"></span></div>')                
                        .appendTo( this.header )
                        this.header.css({'padding-right':0, width:154});
                        this.header.find('label').css({display:'inline-block', width: 135});
                }
            }
        }


        //input cell
        this.input_cell = $( "<div>")
        .addClass('input-cell')
        .appendTo( this.element );
        if(this.is_sortable){

            this.input_cell.sortable({
                //containment: "parent",
                delay: 250,
                items: '.input-div',
                axis: 'y',
                stop:function(event, ui){
                    
                    let isparententity = (that.f('rst_CreateChildIfRecPtr')==1);
                    if(isparententity){ //remove parent entity flag to avoid autosave
                        that.fset('rst_CreateChildIfRecPtr', 0);
                    }
                    
                    //reorganize
                    that.isChanged(true);
                    that.onChange();
                    that.btn_cancel_reorder.show();
                    
                    if(isparententity){//restore parent entity flag
                        that.fset('rst_CreateChildIfRecPtr', 1);
                    }
                    
                }});            
                
                
            $('<br>').appendTo( this.header );
            this.btn_cancel_reorder = $("<div title='Cancel reorder'>")
                .appendTo( this.header ).hide()
                .css({'padding':'1px', 'margin-top':'4px', 'font-size':'0.7em', width: '40px', float: 'right'})
                .button({label:'Cancel'});
            this._on( this.btn_cancel_reorder, {
                click: this._restoreOrder} );
        } 
        
        //add hidden error message div
        this.firstdiv = $( "<div>").hide().appendTo( this.input_cell );
        
        this.error_message = $( "<div>")
        .hide()
        .addClass('heurist-prompt ui-state-error')
        .css({'height': 'auto',
            'width': 'fit-content',
            'padding': '0.2em',
            'border': 0,
            'margin-bottom': '0.2em'})
        .appendTo( this.input_cell );

        //add prompt/help text
        let help_text = window.hWin.HEURIST4.ui.getRidGarbageHelp(this.f('rst_DisplayHelpText'));
        
        this.input_prompt = $( "<div>")
            .html( help_text && !this.options.suppress_prompts ?help_text:'' )
            .addClass('heurist-helper1').css('padding','0.2em 0');
        this.input_prompt.appendTo( this.input_cell );

        // Add extended description, if available, viewable via clicking more... and collapsible with less...
        let extend_help_text = window.hWin.HEURIST4.util.htmlEscape(this.f('rst_DisplayExtendedDescription'));
        if(help_text && !this.options.suppress_prompts 
            && extend_help_text && this.options.recordset && this.options.recordset.entityName == 'Records'){

            let $extend_help_eles = $("<span id='show_extended' style='color:blue;cursor:pointer;'> more...</span>"
                + "<span id='extended_help' style='display:none;font-style:italic;'><br>"+ extend_help_text +"</span>"
                + "<span id='hide_extended' style='display:none;color:blue;cursor:pointer;'> less...</span>")
                .appendTo(this.input_prompt);

            // Toggle extended description
            this._on($extend_help_eles, {
                'click': function(event){
                    $extend_help_eles.toggle()
                }
            });
        }

        //values are not defined - assign default value
        let values_to_set;
        
        if( !Array.isArray(this.options.values) ){
            let def_value = this.f('rst_DefaultValue');
            
            let isparententity = (this.f('rst_CreateChildIfRecPtr')==1);

            if( !this.options.is_insert_mode || window.hWin.HEURIST4.util.isempty(def_value) || isparententity){
                // reset default value - default value for new record only
                // do not assign default values in edit mode                
                values_to_set = [''];        
            }else if(Array.isArray(def_value)){
                //exclude duplication
                values_to_set = window.hWin.HEURIST4.util.uniqueArray(def_value);
            }else{
                values_to_set = [def_value];
            }
            
            if(values_to_set=='increment_new_values_by_1'){
                
                   //find incremented value on server side
                   window.hWin.HAPI4.RecordMgr.increment(this.options.rectypeID, this.options.dtID, 
                     function(response){
                      if(!window.hWin.HEURIST4.util.isnull(response)){
                            if(response.status == window.hWin.ResponseStatus.OK){
                                
                                that.setValue(response.result);
                                that.options.values = that.getValues();
                                that._refresh();
                                
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                      }
                  });
                  this.setValue(0);
                  this.options.values = [0]; //zero value
                  return;
            }
            
        }else if(this.detailType=='file' || this.detailType=='geo'){
            values_to_set = this.options.values;
        }else {
            values_to_set = this.options.values; 
        }
        
        //recreate input elements and assign given values
        this.setValue(values_to_set);
        this.options.values = this.getValues();

        this._refresh();

        if(this.f('rst_MayModify') == 'discouraged'){ // && !window.hWin.HAPI4.is_admin()

            this.block_editing = true;
            that.setDisabled(true);

            this.input_cell.find('.ui-state-disabled').removeClass('ui-state-disabled'); // remove gray 'cover'

            if(this.input_cell.sortable('instance') !== undefined){ // disable sorting, if sortable
                this.input_cell.sortable('disable');
            }

            let $eles = this.element.find('.input-cell, .editint-inout-repeat-button');
            this._on($eles, {
                click: function(event){

                    if(!that.block_editing || $(event.target).hasClass('ui-icon-extlink')){
                        return;
                    }

                    window.hWin.HEURIST4.util.stopEvent(event);
                    event.preventDefault();

                    let msg = 'The designer of this database suggests that you do not edit the value in this field (<strong>'+ lblTitle +'</strong>)<br>'
                        + 'unless you are very sure of what you are doing.<br><br>'
                        + '<label><input type="checkbox" id="allow_edit"> Let me edit this value</label>';

                    let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, function(){

                        that.block_editing = false;
                        //that._off($eles, 'click'); - Also removes click from repeat button
                        that.setDisabled(false);
                        if(that.input_cell.sortable('instance') !== undefined){ // re-enable sorting inputs
                            that.input_cell.sortable('enable');
                        }
                    }, {title: 'Editing is discouraged', yes: 'Proceed', no: 'Cancel'}, {default_palette_class: 'ui-heurist-populate'});

                    window.hWin.HEURIST4.util.setDisabled($dlg.parent().find('.ui-dialog-buttonpane button:first-child'), true);
                    $dlg.find('#allow_edit').on('change', function(){
                        window.hWin.HEURIST4.util.setDisabled($dlg.parent().find('.ui-dialog-buttonpane button:first-child'), !$dlg.find('#allow_edit').is(':checked'));
                    });
                }
            });
        }else if(this.isReadonly()){
            this.input_cell.attr('title', 'This field has been marked as non-editable');
        }
    }, //end _create------------------------------------------------------------

    /* private function */
    _refresh: function(){
        if(this.f('rst_Display')=='hidden'){
            this.element.hide();    
        }else{
            this.element.show();    
        }
        
        if(this.options.showedit_button){
            this.element.find('.btn_add_term').css({'visibility':'visible','max-width':16});
        }else{
            this.element.find('.btn_add_term').css({'visibility':'hidden','max-width':0});
        }
        
        if(this.options.showclear_button){
            this.element.find('.btn_input_clear').css({'visibility':'visible','max-width':16});
        }else{
            this.element.find('.btn_input_clear').css({'visibility':'hidden','max-width':0});
        }
        
        this._setVisibilityStatus();
    
        if(this.options.show_header){
            if(this.header.css('display')=='none'){
                this.header.css('display','table-cell');
            }
        }else{
            this.header.hide();
        }      
        
        //refresh filter for resourse popup 
        let val = this.f('rst_FieldConfig');
        if(!window.hWin.HEURIST4.util.isempty(val)){
            val = window.hWin.HEURIST4.util.isJSON(val);
            if(val!==false && this.configMode.entity){
                this.configMode.initial_filter = val.initial_filter;
                this.configMode.search_form_visible = val.search_form_visible;
            }
        }
    },
    
    _setOptions: function( ) {
        this._superApply( arguments );
        
        if(this.options.recreate===true){
            this.options.recreate = null;
            this.element.empty();
            this._destroy();
            this._create();
        }else{
            this._refresh();    
        }
    },
    
    //
    //
    //
    _removeTooltip: function(id){

        if(this.tooltips && this.tooltips[id]){
            let $tooltip = this.tooltips[id];
            if($tooltip && $tooltip.tooltip('instance') != undefined){
                $tooltip.tooltip('destroy');
                $tooltip = null;
            }
            this.tooltips[id] = null;
            delete this.tooltips[id];
        }
    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        if(this.btn_add){
            this.btn_add.remove();
        }
        // remove generated elements
        if(this.imagelib_select_dialog){
            this.imagelib_select_dialog.remove();
        }
        if(this.header){
            this.header.remove();
        }
        this._off(this.element.find('span.field-visibility'), 'click');
        this._off(this.element.find('div.field-visibility2'), 'click');
        this.element.find('span.field-visibility').remove();
        this.element.find('div.field-visibility2').remove();
        
        let that = this;
        if(this.inputs){
            $.each(this.inputs, function(index, input){ 

                    that._removeTooltip(input.attr('id'));

                    if(that.detailType=='blocktext'){
                        let eid = '#'+input.attr('id')+'_editor';
                        //tinymce.remove('#'+input.attr('id')); 
                        tinymce.remove(eid);
                        $(eid).parent().remove(); //remove editor element
                        //$(eid).remove(); 

                        eid = '#'+input.attr('id')+'_codemirror';
                        $(eid).parent().remove(); //remove editor element

                        
                    }else if(that.detailType=='file'){
                        if($(input).fileupload('instance')!==undefined) $(input).fileupload('destroy');
                    }else{
                        if($(input).hSelect('instance')!==undefined) $(input).hSelect('destroy');
                    }
                    //check for "between" input
                    that.element.find('#'+$(input).attr('id')+'-2').remove();
                    
                    input.remove();
                    
                    
            } );
            this.input_cell.remove();
        }
        this.tooltips = {};
    },

    /**
    * get value for given record type structure field
    *
    * dtFields - json with parameters that describes this input field
    *            for recDetails it is taken from $Db.rst for other entities from config files in hserv/entities
    * 
    dty_Type,
    rst_DisplayName,  //label
    rst_DisplayHelpText  (over dty_HelpText)           //hint
    rst_DisplayExtendedDescription  (over dty_ExtendedDescription) //rollover

    rst_RequirementType,  //requirement
    rst_MaxValues     //repeatability

    rst_DisplayWidth - width in characters

    rst_PtrFilteredIDs (over dty_PtrTargetRectypeIDs)
    rst_FilteredJsonTermIDTree  (over dty_JsonTermIDTree)     
    
    rst_TermIDTreeNonSelectableIDs   
    dty_TermIDTreeNonSelectableIDs
    *
    *
    * @param fieldname
    */
    f: function(fieldname){

        let val = this.options['dtFields'][fieldname]; //try get by name
        
        if(window.hWin.HEURIST4.util.isnull(val) && this.options.dtID>0 && this.options.rectypeID>0){ //try get from $Db
            val = $Db.rst(this.options.rectypeID, this.options.dtID, fieldname);
        }
        if(window.hWin.HEURIST4.util.isempty(val)){ //some default values
            if(fieldname=='rst_RequirementType') val = 'optional'
            else if(fieldname=='rst_MaxValues') val = 1
            else if(fieldname=='dty_Type') val = 'freetext'
            else if(fieldname=='rst_DisplayHeight' && this.f('dty_Type')=='blocktext') 
                val = 8 //height in rows
            else if(fieldname=='rst_DisplayWidth'
                && (this.f('dty_Type')=='freetext' || this.f('dty_Type')=='url' || 
                    this.f('dty_Type')=='blocktext' || this.f('dty_Type')=='resource'))   
                        val = this.f('dty_Type')=='freetext'?20:80;  //default minimum width for input fields in ex
            else if(fieldname=='rst_TermsAsButtons')
                val = 0;
            else if(fieldname=='rst_Spinner')
                val = 0;
            else if(fieldname=='rst_SpinnerStep')
                val = 1;
        }
        if(window.hWin.HEURIST4.util.isempty(val)){
            return null;
        }else{
            return val;    
        }
        

    },
    
    //
    // assign parameter by fieldname
    //
    fset: function(fieldname, value){
        this.options['dtFields'][fieldname] = value;
    },

    //
    //
    //
    _removeInput: function(input_id){

        let that = this;
        
        this._removeTooltip(input_id);        

        if(this.inputs.length>1 && this.enum_buttons == null){

            //find in array
            $.each(this.inputs, function(idx, item){

                let $input = $(item);
                if($input.attr('id')==input_id){
                    if(that.newvalues[input_id]){
                        delete that.newvalues[input_id];
                    }
                    
                    if(that.detailType=='file'){
                        if($input.fileupload('instance')){
                            $input.fileupload('destroy');
                        }
                        let $parent = $input.parents('.input-div');
                        $input.remove();
                        $parent.remove();
                        
                        that.entity_image_already_uploaded = false;
                    }else{
                        if($input.hSelect('instance')!==undefined) $input.hSelect('destroy');

                        //remove element
                        $input.parents('.input-div').remove();
                    }
                    //remove from array
                    that.inputs.splice(idx,1);
                    that.onChange();
                    return;
                }

            });

        }else if(this.inputs.length >= 1 && this.enum_buttons == 'checkbox'){ // uncheck all checkboxes

            $(this.inputs[0]).val(''); // Set first value to empty

            if(this.inputs.length > 1){
                for (let i = 1; i < this.inputs.length; i++) {
                    
                    let $input = $(this.inputs[i]);

                    this._off($input, 'change');

                    $input.parents('.input-div').remove();
                }
                that.inputs = [that.inputs[0]];
            }

            $(this.inputs[0]).parents('.input-div').find('input[type="checkbox"]').prop('checked', false);

            that.onChange();
        }else{  //and clear last one
            this._clearValue(input_id, '');
            if(this.options.is_between_mode){
                this.newvalues[input_id+'-2'] = '';
                this.element.find('#'+input_id+'-2').val('');
            }
        }
        
    },
    
    //
    //
    //
    _setAutoWidth: function(){

        if(this.options.is_faceted_search) return;

        let units = this.options.recordset && this.options.recordset.entityName == 'Records' ? 'ch' : 'ex';
        let $parent_container = this.inputs.length > 0 ? $(this.inputs[0]).parents('.editForm.recordEditor') : [];

        //auto width
        if ( this.detailType=='freetext' || this.detailType=='integer' || 
             this.detailType=='float' || this.detailType=='url' || this.detailType=='file'){

            $.each(this.inputs, function(index, input){

                input = $(input);

                let ow = input.width(); // current width
                let max_w = $parent_container.length > 0 ? $parent_container.width() - 280 : 600;
                max_w = !max_w || max_w <= 0 ? 600 : max_w;

                if(Math.ceil(ow) < Math.floor(max_w)){

                    let nw = `${input.val().length+3}${units}`;
                    $(input).css('width', nw);

                    if(input.width() < ow) input.width(ow); // we can only increase - restore
                    else if(input.width() > max_w) input.width(max_w); // set to max
                }
            });
        }
        
    },
    
    //
    // returns max width for input element
    //
    getInputWidth: function(){
        
        if(this.detailType=='file' && this.configMode.use_assets){
            return 300;
        }
        
        let maxW = 0;
        $.each(this.inputs, function(index, input){ 
            maxW = Math.max(maxW, $(input).width());
        });
        return maxW;
    },
   
    //
    //
    //
    onChange: function(event){
    
        let repeatable = (Number(this.f('rst_MaxValues')) != 1); 
        if(this.options.values && this.options.values.length>1 && !repeatable && this.f('rst_MultiLang')!=1){
            this.showErrorMsg('Repeated value for a single value field - please correct');
        }else{
            this.showErrorMsg(null);
        }
        
        this._setAutoWidth();
        
        if(window.hWin.HEURIST4.util.isFunction(this.options.change)){
            this.options.change.call( this );    
        }
    },

    /**
    * add input according field type
    *
    * @param value
    * @param idx - index for repetative values
    */
    _addInput: function(value) {

        if(!this.inputs){//init
            this.inputs = [];
            this.newvalues = {};
        }

        let that = this;

        let $input = null;
       
        //repalce to uniqueId() if need
        value = window.hWin.HEURIST4.util.isnull(value)?'':value;

        let $inputdiv = $( "<div>" ).addClass('input-div').insertBefore(this.error_message); //was this.input_prompt

        // Apply user pref font size
        let usr_font_size = window.hWin.HAPI4.get_prefs_def('userFontSize', 0);
        if(usr_font_size != 0){
            usr_font_size = (usr_font_size < 8) ? 8 : (usr_font_size > 18) ? 18 : usr_font_size;
            $inputdiv.css('font-size', usr_font_size+'px');
        }

        if(this.detailType=='blocktext'){//----------------------------------------------------

            $input = $( "<textarea>",{rows:2}) //min number of lines
            .uniqueId()
            .val(value)
            .addClass('text ui-widget-content ui-corner-all')
            .css({'overflow-x':'hidden'})
            .on('keydown',function(e){
                if (e.keyCode == 65 && e.ctrlKey) {
                    e.target.select();
                }    
            })
            .on('keyup',function(){that.onChange();})
            .on('change', function(){that.onChange();})
            .appendTo( $inputdiv );

            //IJ 2021-09-09 - from now dheight is max height in lines - otherwise the height is auto
            function __adjustTextareaHeight(){
                $input.attr('rows', 2);
                let dheight = that.f('rst_DisplayHeight');  //max height 
                let lht = parseInt($input.css('lineHeight'),10); 
                if(!(lht>0)) lht = parseInt($input.css('font-size')); //*1.3
                
                let cnt = ($input.prop('scrollHeight') / lht).toFixed(); //visible number of lines
                if(cnt>0){
                    if(cnt>dheight && dheight>2){
                        $input.attr('rows', dheight);    
                    }else{
                        $input.attr('rows', cnt);        
                    }
                }
            }
            
            //count number of lines
            if(!this.options.force_displayheight){
                setTimeout(__adjustTextareaHeight, 1000);
            }else{
                $input.attr('rows', this.options.force_displayheight);
            }
            
            if(this.configMode && this.configMode['thematicmap']){ //-----------------------------------------------

                    let $btn_edit_switcher = $( '<span>themes editor</span>', {title: 'Open thematic maps editor'})
                        //.addClass('smallicon ui-icon ui-icon-gear btn_add_term')
                        .addClass('smallbutton btn_add_term')
                        .css({'line-height': '20px','vertical-align':'top',cursor:'pointer','text-decoration':'underline'})
                        .appendTo( $inputdiv );
                    
                    this._on( $btn_edit_switcher, { click: function(){
                        
                            let current_val = window.hWin.HEURIST4.util.isJSON($input.val());
                            if(!current_val) current_val = [];
                            window.hWin.HEURIST4.ui.showRecordActionDialog(
                            'thematicMapping',
                            {maplayer_query: this.configMode['thematicmap']===true?null:this.configMode['thematicmap'], //query from map layer
                            thematic_mapping: current_val,
                                onClose: function(context){
                                    if(context){
                                        let newval = window.hWin.HEURIST4.util.isJSON(context);
                                        newval = (!newval)?'':JSON.stringify(newval);
                                        $input.val(newval);
                                        that.onChange();
                                    }
                                }}                     
                            );
                    }});
            }else
            if( this.options.dtID != window.hWin.HAPI4.sysinfo['dbconst']['DT_SYMBOLOGY']
            //&& this.options.dtID != window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_IMAGE_WORLDFILE']
            && this.options.dtID > 0)
            {
                
                let eid = $input.attr('id')+'_editor';
                
                //hidden textarea for tinymce editor
                let $editor = $( "<textarea>")
                .attr("id", eid)
                //.addClass('text ui-widget-content ui-corner-all')
                .css({'overflow':'auto',display:'flex',resize:'both'})
                .appendTo( $('<div>').css({'display':'inline-block'}).appendTo($inputdiv) );
                $editor.parent().hide();

                //hidden textarea for codemirror editor
                let codeEditor = null;
                if(typeof EditorCodeMirror !== 'undefined'){
                    codeEditor = new EditorCodeMirror($input);
                }
                
                let $btn_edit_switcher;

                if(this.options.recordset && this.options.recordset.entityName == 'Records'){

                    let $clear_container = $('<span id="btn_clear_container"></span>').appendTo( $inputdiv );

                    $btn_edit_switcher = $('<div class="editor_switcher">').appendTo( $inputdiv );

                    $('<span>text</span>')
                        .attr('title', 'plain text or source, showing markup')
                        .addClass('smallbutton')
                        .css({cursor: 'pointer', 'text-decoration': 'underline'})
                        .appendTo($btn_edit_switcher);

                    $('<span>wysiwyg</span>')
                        .attr('title', 'rendering of the text, taken as html')
                        .addClass('smallbutton')
                        .css({cursor: 'pointer', 'margin-left': '10px'})
                        .appendTo($btn_edit_switcher);

                    if(codeEditor){
                        $('<span>codeeditor</span>')
                            .attr('title', 'direct edit html in code editor')
                            .addClass('smallbutton')
                            .css({cursor: 'pointer', 'margin-left': '10px'})
                            .appendTo($btn_edit_switcher);
                            

                        /*DEBUG  
                        let btn_debug = $('<span>debug</span>')
                            .addClass('smallbutton')
                            .css({cursor: 'pointer', 'margin-left': '10px'})
                            .appendTo($btn_edit_switcher);
                            
                        this._on( btn_debug, {       
                            click:function(event){
                            
                            if(!window.hWin.layoutMgr){
                                hLayoutMgr(); //init global variable layoutMgr
                            }
                                    
                            //cfg_widgets is from layout_defaults.js
                            window.hWin.layoutMgr.convertJSONtoHTML(that.getValues()[0]);
                        }});
                        */
                            
                    }
                        
                    $('<span>table</span>')
                        .attr('title', 'treats the text as a table/spreadsheet and opens a lightweight spreadsheet editor')
                        .addClass('smallbutton')
                        .css({cursor: 'pointer', 'margin-left': '10px'})
                        .hide() // currently un-available
                        .appendTo($btn_edit_switcher);
                }else{
                    $btn_edit_switcher = $( '<span>wysiwyg</span>', {title: 'Show/hide Rich text editor'})
                        //.addClass('smallicon ui-icon ui-icon-gear btn_add_term')      btn_add_term
                        .addClass('smallbutton')
                        .css({'line-height': '20px','vertical-align':'top', cursor:'pointer','text-decoration':'underline'})
                        .appendTo( $inputdiv );
                }

                function __openRecordLink(node){

                    const org_href = $(node).attr('href');
                    let href = '';

                    if(org_href.indexOf('/') !== -1){ // check if href is in the format of record_id/custom_report

                        let parts = org_href.split('/');

                        if(parts.length == 2 && window.hWin.HEURIST4.util.isNumber(parts[0]) && parts[0] > 0){
                            href = `${window.hWin.HAPI4.baseURL}?db=${window.hWin.HAPI4.database}&q=ids:${parts[0]}&template=${parts[1]}`
                        }
                    }else 
                    if(window.hWin.HEURIST4.util.isNumber(org_href) && org_href > 0){ // check if href is just the record id
                        href = `${window.hWin.HAPI4.baseURL}?recID=${org_href}&fmt=html&db=${window.hWin.HAPI4.database}`;
                    }

                    if(!window.hWin.HEURIST4.util.isempty(href)){ // use different url
                        $(node).attr('href', href);
                        setTimeout((ele, org_href) => { $(ele).attr('href', org_href); }, 500, node, org_href);
                    }
                }

                function __showEditor(is_manual){
                    
                    if(typeof tinymce === 'undefined') return false; //not loaded yet

                    if(!Object.hasOwn(window.hWin.HAPI4.dbSettings, 'TinyMCE_formats')){ // retrieve custom formatting

                        window.hWin.HAPI4.SystemMgr.get_tinymce_formats({a: 'get_tinymce_formats'}, function(response){

                            if(response.status != window.hWin.ResponseStatus.OK){

                                window.hWin.HEURIST4.msg.showMsgErr(response);
                                window.hWin.HAPI4.dbSettings['TinyMCE_formats'] = {};
                            }else if(!window.hWin.HEURIST4.util.isObject(response.data)){
                                window.hWin.HAPI4.dbSettings['TinyMCE_formats'] = {};
                            }else{
                                window.hWin.HAPI4.dbSettings['TinyMCE_formats'] = response.data;
                            }

                            __showEditor(is_manual);
                        });

                        return;
                    }

                    let eid = '#'+$input.attr('id')+'_editor';

                    $(eid).parent().css({display:'inline-block'}); //.height($input.height()+100)
                    //to show all toolbar buttons - minimum 768
                    $(eid).width(Math.max(768, $input.width())).height($input.height()).val($input.val()); 

                    let custom_formatting = window.hWin.HAPI4.dbSettings.TinyMCE_formats;

                    let style_formats = Object.hasOwn(custom_formatting, 'style_formats') && custom_formatting.style_formats.length > 0 
                                            ? [ { title: 'Custom styles', items: custom_formatting.style_formats } ] : [];

                    if(Object.hasOwn(custom_formatting, 'block_formats') && custom_formatting.block_formats.length > 0){
                        style_formats.push({ title: 'Custom blocks', items: custom_formatting.block_formats });
                    }

                    let is_grayed = $input.hasClass('grayed') ? 'background: rgb(233 233 233) !important' : '';
                    
                                          
                        /*
                        "webfonts":{
                            "LinuxLibertine":"@import url('settings/linlibertine-webfont.css');"
                        }
                        */
                    let font_family = 'Helvetica,Arial,sans-serif';
                    let webfonts = '';
                    if(Object.hasOwn(custom_formatting, 'webfonts')){
                        let fams = Object.keys(custom_formatting.webfonts);
                        for(let i=0; i<fams.length; i++){
                            if(Object.hasOwn(custom_formatting.webfonts, fams[i])){
                                webfonts = webfonts + custom_formatting.webfonts[fams[i]];
                                font_family = fams[i];
                            }
                        }
                    }
                    
                    let custom_webfonts = `${webfonts} body { font-size: 8pt; font-family: ${font_family}; ${is_grayed} }`;

                    tinymce.init({
                        //target: $editor, 
                        //selector: '#'+$input.attr('id'),
                        selector: eid,
                        menubar: false,
                        inline: false,
                        branding: false,
                        elementpath: false,
                        statusbar: true,        
                        resize: 'both', 

                        //relative_urls : false,
                        //remove_script_host : false,
                        //convert_urls : true, 
                        
                        relative_urls : true,
                        remove_script_host: false,
                        //document_base_url : window.hWin.HAPI4.baseURL,
                        urlconverter_callback : 'tinymceURLConverter',

                        entity_encoding:'raw',
                        inline_styles: true,    
                        content_style: `${custom_webfonts} ${custom_formatting.content_style}`,
                        
                        min_height: ($input.height()+110),
                        max_height: ($input.height()+110),
                        autoresize_bottom_margin: 10,
                        autoresize_on_init: false,
                        image_caption: true,

                        setup:function(editor) {

                            if(editor.ui){
                                // ----- Custom buttons -----
                                // Insert Heurist media
                                editor.ui.registry.addButton('customHeuristMedia', {
                                    icon: 'image',
                                    text: 'Media',
                                    onAction: function (_) {  //since v5 onAction in v4 onclick
                                        that._addHeuristMedia();
                                    }
                                });
                                // Insert figcaption to image/figure
                                editor.ui.registry.addButton('customAddFigCaption', {
                                    icon: 'comment',
                                    text: 'Caption',
                                    tooltip: 'Add caption to current media',
                                    onAction: function (_) {
                                        that._addMediaCaption();
                                    },
                                    onSetup: function (button) {

                                        const activateButton = function(e){

                                            //let is_disabled = e.element.nodeName.toLowerCase() !== 'img'; // is image element
                                            button.setDisabled(e.element.nodeName.toLowerCase() !== 'img');
                                        };

                                        editor.on('NodeChange', activateButton);
                                        return function(button){
                                            editor.off('NodeChange', activateButton);
                                        }
                                    }
                                });
                                // Insert link to Heurist record
                                editor.ui.registry.addButton('customHeuristLink', {
                                    icon: 'link',
                                    text: 'Record',
                                    tooltip: 'Add link to Heurist record',
                                    onAction: function (_) {  //since v5 onAction in v4 onclick
                                        selectRecord(null, function(recordset){
                                            
                                            let record = recordset.getFirstRecord();
                                            const record_id = recordset.fld(record,'rec_ID');
                                            let href = `${record_id}_${window.hWin.HEURIST4.util.random()}`;
                                            tinymce.activeEditor.execCommand('mceInsertLink', false, href);
                                            
                                            let $link = $(tinymce.activeEditor.selection.getNode());
                                            if(!$link.is('a')){
                                                $link = $link.find(`a[href="${href}"]`);
                                            }
                                            if($link.length == 0){
                                                $link = $(tinymce.activeEditor.contentDocument).find(`a[href="${href}"]`);
                                            }

                                            $link.attr('href', record_id).attr('data-mce-href', record_id);

                                            // Customise link's target and whether to open in default rec viewer or custom report
                                            let $dlg;
                                            let msg = `Inserting a link to ${recordset.fld(record,'rec_Title')}<br><br>`
                                                    + 'Open record in: <select id="a_recview"></select><br><br>'
                                                    + 'Open link as: <select id="a_target"><option value="_blank">New tab</option><option value="_self">Within window</option><option value="_popup">Within popup</option></select><br>';

                                            let btns = {};
                                            btns[window.HR('Insert')] = function(){

                                                let target = $dlg.find('#a_target').val();
                                                let template = $dlg.find('#a_recview').val();

                                                $link.attr('target', target);

                                                if(!window.hWin.HEURIST4.util.isempty(template)){

                                                    let new_href = record_id + '/' + template;

                                                    $link.attr('href', new_href).attr('data-mce-href', new_href);
                                                }

                                                if(!$link.text().match(/\w/)){ // if content is empty, replace with href
                                                    $link.text($link.attr('href'));
                                                }

                                                $dlg.dialog('close');
                                            };
                                            btns[window.HR('Cancel')] = function(){ $dlg.dialog('close'); }

                                            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, {title: 'Inserting link to Heurist record', ok: window.HR('Insert link'), cancel: window.HR('Cancel')}, 
                                                {default_palette_class: 'ui-heurist-populate'});

                                            window.hWin.HEURIST4.ui.createTemplateSelector($dlg.find('#a_recview'), [{key: '', title: 'Default record viewer'}]);

                                        });
                                    }
                                });
                                // Insert horizontal rule
                                editor.ui.registry.addButton('customHRtag', {
                                    text: '&lt;hr&gt;',
                                    onAction: function (_) {
                                        tinymce.activeEditor.insertContent( '<hr>' );
                                    }
                                });
                                // Clear text formatting - to replace the original icon
                                editor.ui.registry.addIcon('clear-formatting', `<img style="padding-left: 5px;" src="${window.hWin.HAPI4.baseURL}hclient/assets/clear_formatting.svg" />`)
                                editor.ui.registry.addButton('customClear', {
                                    text: '',
                                    icon: 'clear-formatting',
                                    tooltip: 'Clear formatting',
                                    onAction: function (_) {
                                        tinymce.activeEditor.execCommand('RemoveFormat');
                                    }
                                });
                            }else{
                                editor.addButton('customHeuristMedia', {
                                    icon: 'image',
                                    text: 'Media',
                                    onclick: function (_) {  //since v5 onAction in v4 onclick
                                        that._addHeuristMedia();
                                    }
                                });
                            }

                            // ----- Event handlers -----
                            let has_initd = false, is_blur = false;
                            editor.on('init', function(e) {
                                let $container = $(editor.editorContainer);

                                if($container.parents('.editForm').length == 1){
                                    let max_w = $container.parents('.editForm').width(); 
                                    if($container.width() > max_w - 200){
                                        $container.css('width', (max_w - 245) + 'px');
                                    }
                                }

                                has_initd = true;
                            });

                            editor.on('change', function(e) {

                                let newval = editor.getContent();
                                let nodes = $.parseHTML(newval);
                                if(nodes && nodes.length==1 &&  !(nodes[0].childElementCount>0) &&
                                    (nodes[0].nodeName=='#text' || nodes[0].nodeName=='P'))
                                { 
                                    //remove the only tag
                                    $input.val(nodes[0].textContent);
                                }else{
                                    $input.val(newval);     
                                }

                                // check if editor is 'expanded'
                                if(editor.settings.max_height != null){
                                    editor.settings.max_height = null;
                                    tinymce.activeEditor.execCommand('mceAutoResize');
                                }

                               
                                that.onChange();
                            });

                            editor.on('focus', (e) => { // expand text area
                                editor.settings.max_height = null;
                                tinymce.activeEditor.execCommand('mceAutoResize');
                            });

                            editor.on('blur', (e) => { // collapse text area
                                is_blur = true;
                                editor.settings.max_height = editor.settings.min_height;
                                editor.settings.autoresize_min_height = null;
                                tinymce.activeEditor.execCommand('mceAutoResize');
                            });

                            editor.on('ResizeContent', (e) => {
                                if(is_blur){
                                    is_blur = false;
                                }else if(has_initd){
                                    editor.settings.max_height = null;
                                    editor.settings.autoresize_min_height = $(editor.container).height();
                                }
                            });

                            // Catch links opening
                            editor.on('contextmenu', (e) => {
                                setTimeout(() => {

                                    $(document).find('.tox-menu [title="Open link"]').on('click', function(e){

                                        let node = tinymce.activeEditor.selection.getNode();

                                        __openRecordLink(node);
                                    });

                                }, 500);
                            });

                            editor.on('click', (e) => {

                                let node = tinymce.activeEditor.selection.getNode();

                                if((e.ctrlKey || e.metaKey) && node.tagName == 'A'){
                                    __openRecordLink(node);
                                }
                            });
                        },
                        init_instance_callback: function(editor){
                            let html = '<span class="tox-tbtn__select-label">URL</span>';
                            $(editor.container).find('.tox-tbtn[title="Insert/edit link"]').append(html);

                            $(editor.container).find('.tox-split-button[title="Background color"]').attr('title', 'Highlight text');
                        },
                        plugins: [ //contextmenu, textcolor since v5 in core
                            'advlist autolink lists link image preview ', //anchor charmap print 
                            'searchreplace visualblocks code fullscreen',
                            'media table paste help autoresize'  //insertdatetime  wordcount
                        ],      
                        //undo redo | code insert  |  fontselect fontsizeselect |  forecolor backcolor | media image link | alignleft aligncenter alignright alignjustify | fullscreen            
                        toolbar: ['styleselect | fontselect fontsizeselect | bold italic forecolor backcolor customClear customHRtag | customHeuristMedia customAddFigCaption customHeuristLink link | align | bullist numlist outdent indent | table | help'],
                        formats: custom_formatting.formats,
                        style_formats_merge: true,
                        style_formats: style_formats,
                        //block_formats: 'Paragraph=p;Heading 1=h1;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6;Preformatted=pre;Quotation=blockquote',
                        content_css: [
                            '//fonts.googleapis.com/css?family=Lato:300,300i,400,400i'
                            //,'//www.tinymce.com/css/codepen.min.css'
                        ]
                    });
                    $input.hide();

                    if($btn_edit_switcher.is('span')){
                        $btn_edit_switcher.text('text'); 
                    }else{
                        cur_action = 'wysiwyg';
                        $btn_edit_switcher.find('span').css('text-decoration', '');
                        $btn_edit_switcher.find('span:contains("wysiwyg")').css('text-decoration', 'underline');
                    }
                    
                    return true;
                } // _showEditor()

                // RT_ indicates the record types affected, DT_ indicates the fields affected
                // DT_EXTENDED_DESCRIPTION (field concept 2-4) is the page content or header/footer content
                let isCMS_content = (( 
                         this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'] ||
                         this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME']) &&
                        (this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'] || 
                         this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_HEADER'] || 
                         this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_FOOTER']));

                let cur_action = 'text', cms_div_prompt = null, cms_label_edit_prompt = null;

                if( isCMS_content ){
                    
                    cur_action = '';
                    
                    let fstatus = '';
                    let fname = 'Page content';
                    
                    if (this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'] &&
                       this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_HEADER']){
                        fname = 'Custom header';
                        fstatus = (window.hWin.HEURIST4.util.isempty(value))
                            ?'No custom header defined'
                            :'Delete html from this field to use default page header.';
                    }
                    
                    if (this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'] &&
                       this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_FOOTER']){
                        fname = 'Custom footer';
                        fstatus = (window.hWin.HEURIST4.util.isempty(value))
                            ?'No custom footer defined'
                            :'Delete html from this field to use default page footer.';
                    }
                                
                    // Only show this for the CMS Home record type and home page content            
                    if (this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME']
                        && this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION']){
                            fstatus = 'Leave this field blank if you wish the first menu entry to load automatically on startup.';    
                    }
                    
                    // Only show this message for CONTENT fields (of home page or menu pages) which can be directly edited in the CMS editor 
                    cms_div_prompt = $('<div style="line-height:20px;display:inline-block;"><b>Please edit the content of the '
                                + fname
                                + ' field in the CMS editor.<br>'
                                + fstatus+'</b></div>')
                                .insertBefore($input);
                    $input.hide();
                    
                    $('<br>').insertBefore($btn_edit_switcher);

                    cms_label_edit_prompt = $('<span>Advanced users: edit source as </span>')
                        .css({'line-height': '20px'}).addClass('smallbutton')
                        .insertBefore( $btn_edit_switcher );
                    $btn_edit_switcher.css({display:'inline-block'});
                                        
                    let $cms_dialog = window.hWin.HEURIST4.msg.getPopupDlg();
                    if($cms_dialog.find('.main_cms').length>0){ 
                        //opened from cms editor
                       
                    }else{
                        //see manageRecords for event handler
                        cms_div_prompt.find('span')
                            .css({cursor:'pointer','text-decoration':'underline'})
                            .attr('data-cms-edit', 1)
                            .attr('data-cms-field', this.options.dtID)
                            .attr('title','Edit website content in the website editor');   
                            
                    }
                }
                
                if($btn_edit_switcher.is('div')){

                    this._on($btn_edit_switcher.find('span'), { 
                        click: function(event){
                            
                            if(cms_label_edit_prompt){
                               cms_label_edit_prompt.hide(); 
                               cms_div_prompt.hide(); 
                            }

                            let sel_action = $(event.target).text();
                            sel_action = sel_action == 'codeeditor' && !codeEditor ? 'text' : sel_action;
                            if(cur_action == sel_action) return;
                            
                            $btn_edit_switcher.find('span').css('text-decoration', '');
                            $(event.target).css('text-decoration', 'underline');

                            let eid = '#'+$input.attr('id')+'_editor';

                            //hide previous
                            if(cur_action=='wysiwyg'){
                                tinymce.remove(eid);
                                $(eid).parent().hide();
                            }else if(cur_action=='codeeditor'){
                                codeEditor.hideEditor();
                            }
                            //show now
                            if(sel_action == 'codeeditor'){
                                codeEditor.showEditor();
                            }else if(sel_action == 'wysiwyg'){
                                __showEditor(true); //show tinymce editor
                            }else if(sel_action == 'text'){
                                $input.show();
                                __adjustTextareaHeight();
                            }
                           
                            cur_action = sel_action;
                        }
                    });

                }else{
                    
                    this._on( $btn_edit_switcher, { 
                        click: function(){
                            let eid = '#'+$input.attr('id')+'_editor';                    
                            if($input.is(':visible')){
                                if (__showEditor(true)) //show tinymce editor
                                    $btn_edit_switcher.text('text');
                            }else{
                                $btn_edit_switcher.text('wysiwyg');
                                $input.show();
                                tinymce.remove(eid);
                                $(eid).parent().hide();
                                __adjustTextareaHeight();
                            }
                        }
                    });
                }

                //what is visible initially
                if( !isCMS_content && this.options.dtID != window.hWin.HAPI4.sysinfo['dbconst']['DT_KML'] ) {
                    let nodes = $.parseHTML(value);
                    if(nodes && (nodes.length>1 || (nodes[0] && nodes[0].nodeName!='#text'))){ //if it has html - show editor at once
                        setTimeout(__showEditor, 1200); 
                    }
                }
                
            } 

        }
        // || this.options.dtID=='tag'
        else 
        if(this.detailType=='enum' || this.detailType=='relationtype'){//--------------------------------------

            let dwidth;
            if(this.configMode && this.configMode.entity!='records'){
                dwidth = this.f('rst_DisplayWidth');
                if(parseFloat(dwidth)>0){
                    dwidth = dwidth+'ex';
                }
            }

            $input = $('<select>').uniqueId()
                .addClass('text ui-widget-content ui-corner-all')
                .css('width',(dwidth && dwidth!='0')?dwidth:'0px')
                .val(value)
                .appendTo( $inputdiv );
            
            if(this.options.dtID=='access'){
                const sel_options = [
                    {key: '', title: ''}, 
                    {key: 'viewable', title: 'viewable'}, 
                    {key: 'hidden', title: 'hidden'}, 
                    {key: 'public', title: 'public'}, 
                    {key: 'pending', title: 'pending'}
                ];

                window.hWin.HEURIST4.ui.createSelector($input.get(0), sel_options);
                window.hWin.HEURIST4.ui.initHSelect($input, false);
            }
            else if(this.options.dtID=='tag'){
                let groups = [];
                let req = {};
                req['a'] = 'search';
                req['details'] = 'name'; // Get group id and name
                req['entity'] = 'sysGroups';
                req['request_id'] = window.hWin.HEURIST4.util.random();

                /* Retrieve List of User Groups, mostly the names for displaying */
                window.hWin.HAPI4.EntityMgr.doRequest(req, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            let recset = new HRecordSet(response.data);
                            if(recset.length()>0){
                                recset.each2(function(id, val){
                                    groups.push([val['ugr_ID'], val['ugr_Name']]);
                                });
                            }
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    }
                );

                let sel_options = [];
                let u_id = window.hWin.HAPI4.currentUser['ugr_ID'];

                req = {};
                req['a'] = 'search';
                req['details'] = 'name'; // Get tag id, name, and gorup id
                req['entity'] = 'usrTags';
                req['sort:tag_Text'] = 2; // Order tags by tag name
                req['request_id'] = window.hWin.HEURIST4.util.random();

                /* Retrieve Tags */
                window.hWin.HAPI4.EntityMgr.doRequest(req, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            let gIDs = [];
                            let recset = new HRecordSet(response.data);
                            if(recset.length()>0){
                                let records = recset.getSubSetByRequest({'sort:tag_UGrpID':1});
                                
                                let u_tags = records.getSubSetByRequest({'tag_UGrpID':'='+u_id});
                                u_tags.each2(function(id, val){ // Get User Tags first
                                    const tag_name = val['tag_Text'];
                                   

                                    let values = {};
                                    values['key'] = tag_name;
                                    values['title'] = tag_name;

                                    sel_options.push(values);
                                });  

                                let w_tags = records.getSubSetByRequest({'tag_UGrpID':'!='+u_id});
                                w_tags.each2(function(id, val){ // Get Workgroup Tags second
                                    let tag_name = val['tag_Text'];
                                    const filter_val = val['tag_Text'];
                                    let tag_group = val['tag_UGrpID'];

                                    for(let i=0; i<groups.length; i++){
                                        if(groups[i][0] == tag_group){
                                            tag_name = groups[i][1] + '.' + tag_name;
                                        }
                                    }

                                    let values = {};
                                    values['key'] = filter_val;
                                    values['title'] = tag_name;

                                    sel_options.push(values);
                                });
                                window.hWin.HEURIST4.ui.createSelector($input.get(0), sel_options);
                                window.hWin.HEURIST4.ui.initHSelect($input, false);
                            }else{ // No Tags Found
                                window.hWin.HEURIST4.ui.createSelector($input.get(0), [{key: '', title: 'No Tags Found'}]);
                            }
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    }
                );
            }
            else{
                
                if(window.hWin.HEURIST4.util.isempty(this.f('rst_FieldConfig'))){

                    browseTerms(this, $input, value);

                    
                    
                    $input.hSelect({
                        'open': (e) => {
                            window.hWin.HEURIST4.util.stopEvent(e);
                            e.preventDefault();

                            $input.trigger('click');
                            $input.hSelect('close');
                        }
                    });

                    $input.hSelect('widget').css({
                        'width': 'auto',
                        'min-width': '14em'
                    });

                }else{
                    $input = this._recreateSelector($input, value); //initial create
                }
            }
            $input = $($input);
            
            this._on( $input, {change:this._onTermChange} );
            
            let allTerms = this.f('rst_FieldConfig');    
            
            if($.isPlainObject(allTerms)){
                this.options.showclear_button = (allTerms.hideclear!=1);
            }
            
            //allow edit terms only for true defTerms enum and if not DT_RELATION_TYPE
            if(window.hWin.HEURIST4.util.isempty(allTerms)) {
                allTerms = this.f('rst_FilteredJsonTermIDTree');

                if (!(window.hWin.HEURIST4.util.isempty(allTerms) && 
                    this.options.dtID==window.hWin.HAPI4.sysinfo['dbconst']['DT_RELATION_TYPE'])) {

                    let isVocabulary = !isNaN(Number(allTerms)); 

                    let $btn_termsel = $( '<span>', {title: 'Select Term By Picture'})
                    .addClass('smallicon ui-icon ui-icon-image show-onhover')
                    .css({
                        'margin-top': '2px',
                        'cursor': 'pointer'
                    })
                    .appendTo( $inputdiv )
                    .hide();
                    
                    if(that.child_terms==null){
                        
                        const vocab_id = that.f('rst_FilteredJsonTermIDTree');    
                        that.child_terms = $Db.trm_TreeData(vocab_id, 'set');
                        
                        that._checkTermsWithImages(); //show hide $btn_termsel
                    }else if(that._enumsHasImages){
                        $btn_termsel.show();
                    }

                    this._on( $btn_termsel, { click: function(){

                        let vocab_id = Number(this.f('rst_FilteredJsonTermIDTree'));    
                        
                        if(this.is_disabled || !(vocab_id>0)) return;

                            let selectmode = that.enum_buttons == 'checkbox' ? 'select_multi' : 'select_single';
                            let dlg_title = 'Term selection for ' + that.f('rst_DisplayName');

                            window.hWin.HEURIST4.ui.showEntityDialog('defTerms', {
                                empty_remark: 'No terms available',
                                title: dlg_title,
                                hide_searchForm: true,
                                select_mode: selectmode, 
                                view_mode: 'icons',
                                initial_filter: vocab_id,
                                default_palette_class: 'ui-heurist-populate',
                                onselect:function(event, data){
                                    if(data && data.selection && data.selection.length > 0){

                                        if(selectmode == 'select_multi'){
                                            that.setValue(data.selection, false);
                                        }else{
                                            browseTerms(that, $input, data.selection[0]);                                    
                                        }
                                        that.onChange();
                                    }
                                }
                            });
                                                                    
                    }});

                    let vocab_id = Number(allTerms);

                    if(window.hWin.HAPI4.is_admin()){            
                        
                        let $btn_termedit2 = $( '<span>', {title: 'Edit term tree'})
                        .addClass('smallicon ui-icon ui-icon-gear btn_add_term show-onhover')
                        .css({'margin-top':'2px',cursor:'pointer'})
                        .appendTo( $inputdiv );
                        
                        this._on( $btn_termedit2,{ click: function(){ this._openManageTerms(vocab_id); }});
                            
                    }
                
                    if(!window.hWin.HAPI4.is_guest_user()){
                    
                    let $btn_termedit = $( '<span>', {title: 'Add new term to this list'})
                    .addClass('smallicon ui-icon ui-icon-plus btn_add_term show-onhover')
                    .css({'margin-top':'2px',cursor:'pointer','font-size':'11px'})
                    .appendTo( $inputdiv );

                    //
                    // open add term popup
                    //
                    this._on( $btn_termedit, { click: function(){
                        
                    if(this.is_disabled) return;
                    
                    //add new term to specified vocabulary
                    let rg_options = {
                            isdialog: true, 
                            select_mode: 'manager',
                            edit_mode: 'editonly',
                            height: 240,
                            rec_ID: -1,
                            trm_VocabularyID: vocab_id,
                            onClose: function(){
                                that._recreateEnumField(vocab_id);
                            }
                        };

                        window.hWin.HEURIST4.ui.showEntityDialog('defTerms', rg_options); // it recreates  

                        return;
                    }} ); //end btn onclick
                    
                    }//not guest user                    
                }
            }//allow edit terms only for true defTerms enum
            
            // Display term selector as radio buttons/checkboxes
            let asButtons = this.options.recordset && this.options.recordset.entityName=='Records' && this.f('rst_TermsAsButtons') == 1;
            if(asButtons && this.child_terms  && this.child_terms.length<=20){

                    this.enum_buttons = (Number(this.f('rst_MaxValues')) != 1) ? 'checkbox' : 'radio';
                    let inpt_id = $input.attr('id');
                    let dtb_res = false;

                    if(this.enum_buttons == 'checkbox' && $inputdiv.parent().find('input:checkbox').length > 0){ // Multi value, check if checkboxes exist

                        $inputdiv.parent().find('input:checkbox[data-id="'+value+'"]').prop('checked', true); // Check additional value
                        $inputdiv.hide();

                        dtb_res = true;
                    }else{ // Create input elements
                        dtb_res = this._createEnumButtons(false, $inputdiv, [value]);
                    }

                    if(dtb_res){

                        if($input.hSelect('instance') != undefined){
                            $input.hSelect('destroy');
                        }
                        this._off($input, 'change');
                        $input.remove();

                        $input = $('<input type="text" class="text ui-widget-content ui-corner-all">')
                                    .attr('id', inpt_id)
                                    .val(value)
                                    .prependTo($inputdiv)
                                    .hide();

                        this._on( $input, {change:this.onChange} );

                        if(this.btn_add){
                            this.btn_add.hide(); // Hide repeat button, removeClass('smallbutton ui-icon-circlesmall-plus')
                        }
                    }
            }
        }
        else if(this.detailType=='boolean'){//----------------------------------------------------

            $input = $( '<input>',{type:'checkbox'} )
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .css('vertical-align','-3px')
            .on('change', function(){that.onChange();})
            .appendTo( $inputdiv );
            
            if(Array.isArray(this.configMode)){
                $input.prop('value', this.configMode[0]);
                $input.prop('checked', (this.configMode.indexOf(value)==0) );
            }else{
                $input.prop('value', '1');
                if(!(value==false || value=='0' || value=='n')){
                    $input.prop('checked','checked');
                }
            } 

        }
        else if(this.detailType=='rectype'){  //@todo it seems NOT USED, need refer via resource type and entity mgr

            $input = $( "<select>" )
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .css('width','auto')
            .val(value)
            .appendTo( $inputdiv );

            window.hWin.HEURIST4.ui.createRectypeSelect($input.get(0),null, this.f('cst_EmptyValue'), true);
            if(value){
                $input.val(value);
            }
            $input.on('change',function(){that.onChange();})

        }
        else if(this.detailType=="user"){ //special case - only groups of current user

            $input = $( "<select>")
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .css('width','auto')
            .val(value)
            .on('change', function(){that.onChange();})
            .appendTo( $inputdiv );
            let mode = null;
            
            let topOptions;
            if(this.configMode && (this.configMode.mode=='all_users' || this.configMode.mode=='all_users_and_groups')){
                topOptions = this.configMode.topOptions;
                mode = this.configMode.mode;
            }else{
                topOptions = [{key:'',title:window.hWin.HR('select user/group...')},
                    {key:window.hWin.HAPI4.currentUser['ugr_ID'], title:window.hWin.HAPI4.currentUser['ugr_FullName'] }];
            }

            window.hWin.HEURIST4.ui.createUserGroupsSelect($input.get(0), mode, topOptions );
            if(value){
                $input.val(value);
            }
        }
        /* todo
        else if(this.detailType=="keyword"){ 

            $input = $( "<select>")
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .css('width','auto')
            .val(value)
            .on('change', function(){that.onChange();})
            .appendTo( $inputdiv );

            window.hWin.HEURIST4.ui.createUserGroupsSelect($input.get(0),null,
                [{key:'',title:window.hWin.HR('select user/group...')},
                    {key:window.hWin.HAPI4.currentUser['ugr_ID'], title:window.hWin.HAPI4.currentUser['ugr_FullName'] }] );
            if(value){
                $input.val(value);
            }
            
        }*/
        else if(this.detailType=='relmarker'){ //---------------------------------------------------- 
            
                this.options.showclear_button = false;
               
                $inputdiv.css({'display': 'table','vertical-align': 'middle', 'border-spacing': '0px'}); //was '0px 4px'
            
                if(this.inputs.length==0){ //show current relations
                
                    //these are relmarker fields from other rectypes that points to this record
                    let isInwardRelation = (that.f('rst_DisplayOrder')>1000);
                
                
                    function __onRelRemove(){
                        let tot_links = that.element.find('.link-div').length;
                        let rev_links = that.element.find('.reverse-relation').length; 
                        if( tot_links-rev_links==0){ //hide this button if there are links
                            that.element.find('.rel_link').show();
                        }else{
                            that.element.find('.rel_link').hide();
                        }
                        if( rev_links==0){
                            that.element.find('.reverse-relation-header').remove();
                        }
                    }
                    
                    let isOpened = false;
                    
                    let rts = [];
                    const ptrset = that._prepareIds(that.f('rst_PtrFilteredIDs'));
                    
                    for (let k=0; k<ptrset.length; k++) {
                        const sname = $Db.rty(ptrset[k],'rty_Name');
                        if(!window.hWin.HEURIST4.util.isempty(sname)){
                            rts.push(sname);
                        }
                    }
                    
                
                    let __show_addlink_dialog = function(){
                        if(isOpened || that.is_disabled) return;
                        
                        isOpened = true;
                        
                        if(that.options.editing && (that.options.editing.editStructureFlag()===true)){
                            window.hWin.HEURIST4.msg.showMsgFlash('This feature is disabled in edit structure mode, you can use it in normal record editing',3000);                     return;
                        }
                        
                        function __onCloseAddLink(context){
                            isOpened = false;
                            
                            if(context && context.count>0){
                                
                                let link_info = isInwardRelation?context.source:context.target;
                                link_info.relation_recID = context.relation_recID; //existing relationship record
                                link_info.relmarker_field = that.options.dtID;
                                link_info.trm_ID = context.trm_ID;
                                link_info.is_inward = isInwardRelation;
                                
                                let ele = window.hWin.HEURIST4.ui.createRecordLinkInfo($inputdiv,
                                    link_info, true);
                                ele.insertBefore(that.element.find('.rel_link'));
                                that.element.find('.rel_link').hide();//hide this button if there are links
                                ele.on('remove', __onRelRemove);

                            }

                            if(window.hWin.HEURIST4.util.isFunction(that._external_relmarker.callback)){
                                that._external_relmarker.callback(context);
                            }

                            // Reset relmarker details for lookups
                            that._external_relmarker = {
                                target: null,
                                relation: null,
                                callback: null
                            };
                        }
                        
                        let rty_names = '';
                        if(rts.length>0 && that.options.rectypeID>0){
                            rty_names = $Db.rty(that.options.rectypeID,'rty_Name') 
                                        + ' and ' + rts.join(', ');
                        }else{
                            rty_names = 'records';
                        }
                        
                        let opts = {
                            height:480, width:750, 
                            title: 'Create relationship between '+rty_names+' ( Field: "'
                                +$Db.dty(that.options.dtID, 'dty_Name')+'" )',
                            relmarker_dty_ID: that.options.dtID,
                            default_palette_class: 'ui-heurist-populate',
                            onClose: __onCloseAddLink 
                        };

                        if(isInwardRelation){
                            opts['source_AllowedTypes'] = that.f('rst_PtrFilteredIDs');
                            opts['target_ID'] = that.options.recID;
                        }else{
                            opts['source_ID'] = that.options.recID;
                        }

                        if(that._external_relmarker.target){ // setup from external source (currently from external lookup)
                            opts['target_ID'] = that._external_relmarker.target;
                        }
                        if(that._external_relmarker.relation){ // setup from external source (currently from external lookup)
                            opts['relationtype'] = that._external_relmarker.relation;
                        }

                        window.hWin.HEURIST4.ui.showRecordActionDialog('recordAddLink', opts);
                    };
                    
                    let sRels = '';
                    if(that.options.recordset){
                    
                    let relations = that.options.recordset.getRelations();
                  
                    if(relations && (relations.direct || relations.reverse)){
                        
                        const ptrset = that._prepareIds(that.f('rst_PtrFilteredIDs'));
                        
                        let vocab_id = this.f('rst_FilteredJsonTermIDTree');        

                        let ph_gif = window.hWin.HAPI4.baseURL + 'hclient/assets/16x16.gif';
                        let headers = relations.headers;
                        let dtID = this.options.dtID;
                        
                      if(!isInwardRelation){
                            let direct = relations.direct; //outward
                            
                        //take only those that satisify to allowed terms and pointer constraints
                        for(let k in direct){
                            //direct[k]['dtID']==this.options.dtID && 
                            if(direct[k]['trmID']>0){ //relation   
                            
                                
                                if($Db.trm_InVocab(vocab_id, direct[k]['trmID']))
                                { //it satisfies to allowed relationship types

                                        //verify that target rectype is satisfy to constraints and trmID allowed
                                        let targetID = direct[k].targetID;
                                        
                                        if(!headers[targetID]){
                                            //there is not such record in database
                                            continue;                                            
                                        }
                                        
                                        let targetRectypeID = headers[targetID][1];
                                        if( headers[targetID]['used_in_reverse'+dtID]!=1 &&
                                           (ptrset.length==0 || 
                                            window.hWin.HEURIST4.util.findArrayIndex(targetRectypeID, ptrset)>=0))
                                        {
                                            
                                            let ele = window.hWin.HEURIST4.ui.createRecordLinkInfo($inputdiv, 
                                                {rec_ID: targetID, 
                                                 rec_Title: headers[targetID][0], 
                                                 rec_RecTypeID: headers[targetID][1], 
                                                 relation_recID: direct[k]['relationID'], 
                                                 relmarker_field: that.options.dtID,
                                                 trm_ID: direct[k]['trmID'],
                                                 dtl_StartDate: direct[k]['dtl_StartDate'], 
                                                 dtl_EndDate: direct[k]['dtl_EndDate'],
                                                 is_inward: false
                                                }, !this.isReadonly());
                                            ele.on('remove', __onRelRemove);
                                            
                                            headers[targetID]['used_in_direct'+dtID] = 1;
                                        }
                                }
                            }
                        }
                        
                      }//!isInwardRelation

                        
                        //small subheader before reverse entries
                        let isSubHeaderAdded = isInwardRelation;
                        
                        //now scan all indirect /inward relations
                        let reverse = relations.reverse; //outward
                        //take only those that satisify to allowed terms and pointer constraints
                        for(let k in reverse){
                            //direct[k]['dtID']==this.options.dtID && 
                            if(reverse[k]['trmID']>0){ //relation   
                                
                                if($Db.trm_InVocab(vocab_id, reverse[k]['trmID']))
                                { //it satisfies to allowed relationship types
                                
                                        //verify that target rectype is satisfy to constraints and trmID allowed
                                        let targetID = reverse[k].sourceID;
                                        
                                        if(!headers[targetID]){
                                            //there is not such record in database
                                            continue;                                            
                                        }
                                        
                                        let targetRectypeID = headers[targetID][1];
                                        
                                        if (headers[targetID]['used_in_direct'+dtID]!=1 && (ptrset.length==0) ||
                                                (window.hWin.HEURIST4.util.findArrayIndex(targetRectypeID, ptrset)>=0))
                                        {
                                            if(!isSubHeaderAdded){
                                                isSubHeaderAdded = true;
                                            }
                                            
                                            let ele = window.hWin.HEURIST4.ui.createRecordLinkInfo($inputdiv, 
                                                {rec_ID: targetID, 
                                                 rec_Title: headers[targetID][0], 
                                                 rec_RecTypeID: targetRectypeID, 
                                                 relation_recID: reverse[k]['relationID'], 
                                                 relmarker_field: that.options.dtID,
                                                 trm_ID: reverse[k]['trmID'], //invTermID,
                                                 dtl_StartDate: reverse[k]['dtl_StartDate'], 
                                                 dtl_EndDate: reverse[k]['dtl_EndDate'],
                                                 is_inward: true
                                                }, !this.isReadonly());
                                            ele.addClass('reverse-relation', 1)
                                                .on('remove', __onRelRemove);
                                            
                                            headers[targetID]['used_in_reverse'+dtID] = 1;
                                        }
                                }
                            }
                        }
                        
                        
                    }
                }
                
                    /*
                    $input = $( "<div>")
                        .uniqueId()
                        .html(sRels)
                        //.addClass('ui-widget-content ui-corner-all')
                        .appendTo( $inputdiv );
                   */  
                if(this.isReadonly()){
                   $('<span>readonly</span>').appendTo( $inputdiv );
                   return 0;
                }else{
                   $inputdiv
                        .uniqueId();
                   $input = $inputdiv;

                   let rty_names = '';
                   if(rts.length>0){
                        rty_names = '<div class="truncate" style="max-width:200px;display:inline-block;vertical-align:top">&nbsp;to '
                                +rts.join(', ') +'</div>';
                   }
                   
                   //define explicit add relationship button
                   let $btn_add_rel_dialog = $( "<button>", {title: "Click to add new relationship"})
                        .addClass("rel_link") //.css({display:'block'})
                        .button({icon: "ui-icon-circle-plus",label:'&nbsp;&nbsp;&nbsp;Add Relationship'
                                +rty_names});
                       
                   let rheader = that.element.find('.reverse-relation-header');     
                   if(rheader.length>0){
                        $btn_add_rel_dialog.insertBefore( rheader );
                   }else{
                        $btn_add_rel_dialog.appendTo( $inputdiv );   
                   }
                        
                   this._on($btn_add_rel_dialog,{click:__show_addlink_dialog});
                  
                   
                   __onRelRemove();                   
                   /*if( this.element.find('.link-div').length>0){ //hide this button if there are links
                        $btn_add_rel_dialog.hide();
                   }*/
                }//not readonly   
                
                }else{
                    //this is second call - some links are already defined
                    //show popup dialog at once
                   
                    if(this.element.find('.rel_link').is(':visible')){
                        window.hWin.HEURIST4.msg.showMsgFlash('Please define the first relationship before adding another', 2000);                        
                    }
                    
                    this.element.find('.rel_link').show();
                    
                    return;
                }

            /* IJ asks to show button                 
            if( this.element.find('.link-div').length>0){ //hide this button if there are links
                $inputdiv.find('.rel_link').hide();
            }else{
                $inputdiv.find('.rel_link').show();
            }                
            */
                

        }
        else if(this.detailType=='resource' && this.configMode.entity=='records'){//---------------------------------

            /*
            if(value=='' && this.element.find('.sel_link2').is(':visible')){
                window.hWin.HEURIST4.msg.showMsgFlash('Please select record before adding another pointer',2000);
                return;
            }
            */
            
            let isparententity = (that.f('rst_CreateChildIfRecPtr')==1);
            
            //replace input with div
            $input = $( "<div>").css({'display':'inline-block','vertical-align':'middle','min-wdith':'25ex'})
                            .uniqueId().appendTo( $inputdiv );
                            
            const ptrset = that._prepareIds(that.f('rst_PtrFilteredIDs'));
            
            let rts = [];
            for (let k=0; k<ptrset.length; k++) {
                const sname = $Db.rty(ptrset[k],'rty_Name');
                if(!window.hWin.HEURIST4.util.isempty(sname)){
                    rts.push(sname);
                }
            }
            rts = (rts.length>0)?rts.join(', '):'record';
            let classes = 'sel_link2';

            if(isparententity){
                classes = classes + ' child_rec_fld';
                $input.addClass('child_rec_input');    
            }

            
            //define explicit add resource button
            $( "<button>", {title: "Select record to be linked"})
                        .button({icon:"ui-icon-triangle-1-e",
                               label:('&nbsp;&nbsp;&nbsp;<span style="color: #55555566;">'+(isparententity?'create child':'select')+'&nbsp: '
                               +'<div class="truncate" style="max-width:200px;display:inline-block;vertical-align:middle">'
                               +rts+'</div></span>')})
                        .addClass(classes).css({'max-width':'300px'}) //, 'background': 'lightgray'})
                        .appendTo( $inputdiv );
            
            let __show_select_function = null;
            if(typeof browseRecords!=='undefined' && window.hWin.HEURIST4.util.isFunction(browseRecords)){
                __show_select_function = browseRecords(that, $input);//see editing_exts
            }
            
            that._findAndAssignTitle($input, value, __show_select_function);
            
            if(value){
                this.newvalues[$input.attr('id')] = value;  //for this type assign value at init    
                $input.attr('data-value', value);
            } 
        } 
        
        else if(this.detailType=='resource' && 
                (this.configMode.entity=='DefRecTypes' || this.configMode.entity=='DefDetailTypes')){ //-----------
            //it defines slightly different select dialog for defRecTypes
            let __show_select_dialog = function(event){
        
                if(that.is_disabled) return;
                event.preventDefault();
                
                let sels = that.newvalues[$input.attr('id')];
                
                let rg_options = {
                    select_mode: (this.configMode.csv!==false?'select_multi':'select_single'),
                    edit_mode: 'popup',
                    isdialog: true,
                    width: 440,
                    selection_on_init:sels?sels.split(','):[],
                    parent_dialog: this.element.closest('div[role="dialog"]'),
                    onselect:function(event, data){
                        
                        if(data && data.selection){
                            let newsel = data.selection;
                            that._findAndAssignTitle($input, newsel);
                            that.newvalues[$input.attr('id')] = newsel.join(',');
                            that.onChange();
                        }
                    }
                }

                if(this.options.dtID==window.hWin.HAPI4.sysinfo['dbconst']['DT_TIMELINE_FIELDS']){
                    rg_options['filters']= {types: ['date','year']};
                }
                
                window.hWin.HEURIST4.ui.showEntityDialog(this.configMode.entity, rg_options);
            }
            
            //replace input with div
            $input = $( "<div>").css({'display':'inline-block','vertical-align':'middle','min-wdith':'25ex'})
                            .uniqueId().appendTo( $inputdiv );
                            
                            
            //define explicit add relationship button
            $( "<button>", {title: "Select"})
                        .button({icon:"ui-icon-triangle-1-e",
                               label:('&nbsp;&nbsp;&nbsp;select')})
                        .addClass('sel_link2').hide()
                        .appendTo( $inputdiv );
            
            let $input_img;
            let select_return_mode = 'ids';
            
            if(this.configMode.select_return_mode &&
               this.configMode.select_return_mode!='ids'){
                 select_return_mode = 'recordset'
            }
                
            let $gicon = $('<span class="ui-icon ui-icon-triangle-1-e sel_link" '
            +'style="display:inline-block;vertical-align:top;margin-left:8px;margin-top:2px;cursor:hand"></span>')
            .insertBefore( $input );
            
            $input.addClass('entity_selector').css({'margin-left': '-24px'});

            $input.css({'min-wdith':'22ex'});

            $input.hide();
            that._findAndAssignTitle($input, value);

           
            this._on( $input, { keypress: __show_select_dialog, click: __show_select_dialog } );
            this._on( $gicon, { click: __show_select_dialog } );
            this._on( $inputdiv.find('.sel_link2'), { click: __show_select_dialog } );
            
            if(value){
                this.newvalues[$input.attr('id')] = value;  //for this type assign value at init  
                $input.attr('data-value', value);
            }
        
        }
        else if(this.detailType=='resource') //----------------------------------------------------
        {
            //replace input with div
            $input = $( "<div>").css({'display':'inline-block','vertical-align':'middle','min-wdith':'25ex'})
                            .uniqueId().appendTo( $inputdiv );
                            
                            
            //define explicit add relationship button
            $( "<button>", {title: "Select"})
                        .button({icon:"ui-icon-triangle-1-e",
                               label:('&nbsp;&nbsp;&nbsp;select')})
                        .addClass('sel_link2').hide()
                        .appendTo( $inputdiv );
            
            let $input_img;
            let select_return_mode = 'ids';
            
            if(this.configMode.select_return_mode &&
               this.configMode.select_return_mode!='ids'){
                 select_return_mode = 'recordset'
            }
                
            let $gicon = $('<span class="ui-icon ui-icon-triangle-1-e sel_link" '
            +'style="display:inline-block;vertical-align:top;margin-left:8px;margin-top:2px;cursor:hand"></span>')
            .insertBefore( $input );
            
            $input.addClass('entity_selector').css({'margin-left': '-24px'});

            $input.css({'min-wdith':'22ex'});

            const ptrset = that.f('rst_PtrFilteredIDs');

            let popup_options = {
                isdialog: true,
                select_mode: (this.configMode.csv==true?'select_multi':'select_single'),
                select_return_mode:select_return_mode, //ids or recordset(for files)
                filter_group_selected:null,
                filter_groups: this.configMode.filter_group,
                filters: this.configMode.filters,
                onselect:function(event, data){

                    if(data){

                        if(select_return_mode=='ids'){


                            let newsel = window.hWin.HEURIST4.util.isArrayNotEmpty(data.selection)?data.selection:[];

                            //config and data are loaded already, since dialog was opened
                            that._findAndAssignTitle($input, newsel);
                            newsel = newsel.join(',')
                            that.newvalues[$input.attr('id')] = newsel;
                            $input.attr('data-value', newsel);

                            that.onChange();

                        }else if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                            //todo

                        }
                    }//data

                }
            };//popup_options
            
            $input.hide();
            that._findAndAssignTitle($input, value);

            let __show_select_dialog = function(event){
                
                    if(that.is_disabled) return;

                    event.preventDefault();
                    
                    let usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_'+this.configMode.entity, 
                        {width: null,  //null triggers default width within particular widget
                        height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });
        
                    popup_options.width = usrPreferences.width;
                    popup_options.height = usrPreferences.height;
                    let sels = this.newvalues[$input.attr('id')];
                    
                    if(!window.hWin.HEURIST4.util.isempty(sels)){
                        popup_options.selection_on_init = sels.split(',');
                    } else {
                        popup_options.selection_on_init = null;    
                    }                                
                    
                    if(this.configMode.initial_filter){
                        popup_options.initial_filter = this.configMode.initial_filter;    
                    }
                    if(!window.hWin.HEURIST4.util.isnull(this.configMode.search_form_visible)){
                        popup_options.search_form_visible = this.configMode.search_form_visible;    
                    }

                    let popup_options2 = popup_options;
                    if(this.configMode.popup_options){
                         popup_options2  = $.extend(popup_options, this.configMode.popup_options);
                    }
                    //init dialog to select related entities
                    window.hWin.HEURIST4.ui.showEntityDialog(this.configMode.entity, popup_options2);
            }
            
            
           
            this._on( $input, { keypress: __show_select_dialog, click: __show_select_dialog } );
            this._on( $gicon, { click: __show_select_dialog } );
            this._on( $inputdiv.find('.sel_link2'), { click: __show_select_dialog } );
            
            if(value){
                this.newvalues[$input.attr('id')] = value;  //for this type assign value at init  
                $input.attr('data-value', value);
            }

        }
        else{              //----------------------------------------------------
            $input = $( "<input>")
            .uniqueId()
            .addClass('text ui-widget-content ui-corner-all')
            .val(value)
            .on('keyup', function(){that.onChange();})
            .on('change', function(){
                    that.onChange();
            })
            .appendTo( $inputdiv );
            
            window.hWin.HEURIST4.ui.disableAutoFill( $input );
            
            if(!(this.options.dtID=='file' || this.detailType=='resource' || 
                 this.detailType=='date' || this.detailType=='geo' || this.detailType=='action')){
                     
                $input.on('keydown',function(e){  //Ctrl+A - select all
                    if (e.keyCode == 65 && e.ctrlKey) {
                                        e.target.select()
                    }    
                });
                if(this.detailType=='password'){
                    $input.prop('type','password');
                }
            }
            
            if(this.options.dtID=='rec_URL' || this.detailType=='url'){//----------------------------------
                
                    let $btn_extlink = null, $btn_editlink = null;
                
                    function __url_input_state(force_edit){
                    
                        if($input.val()=='' || force_edit===true){
                            $input.removeClass('rec_URL').addClass('text').attr("readonly",false);
                            that._off( $input, 'click');
                            if(!window.hWin.HEURIST4.util.isnull( $btn_extlink)){
                                
                               
                               
                                if($btn_editlink!=null){
                                    $btn_editlink.remove();
                                    $btn_editlink = null;
                                }
                            }
                            if(force_edit===true){
                                $input.trigger('focus');   
                            }
                        }else if(window.hWin.HEURIST4.util.isnull($btn_extlink)){
                            
                            if($input.val()!='' && !($input.val().indexOf('http://')==0 || $input.val().indexOf('https://')==0)){
                                $input.val( 'https://'+$input.val());
                            }
                            $input.addClass('rec_URL').removeClass('text').attr('readonly','readonly');
                            
                            $btn_editlink = $( '<span>', {title: 'Edit URL'})
                                .addClass('smallicon ui-icon ui-icon-pencil')
                                .appendTo( $inputdiv );
                               
                        
                            that._on( $btn_editlink, { click: function(){ __url_input_state(true) }} );
                        }
                
                    }

                    if($btn_extlink==null){
                        $btn_extlink = $( '<span>', {title: 'Open URL in new window'})
                            .addClass('smallicon ui-icon ui-icon-extlink')
                            .appendTo( $inputdiv );
                    
                        that._on( $btn_extlink, { click: function(){ window.open($input.val(), '_blank') }} );
                        that._on( $input, { click: function(){ if ($input.val()!='') window.open($input.val(), '_blank') }} );
                    }

                    //$input.focusout( __url_input_state ); 
                    __url_input_state(true);               
                
            }
            else if(this.detailType=="integer" || this.detailType=="year"){//-----------------------------------------

                 
                $input.on('keypress', function (e) {
                    let code = e.charCode || e.keyCode;
                    let charValue = String.fromCharCode(code);
                    let valid = false;

                    if(charValue=='-' && this.value.indexOf('-')<0){
                        this.value = '-'+this.value;
                    }else{
                        valid = /^[0-9]+$/.test(charValue);
                    }

                    if(!valid){
                        window.hWin.HEURIST4.util.stopEvent(e);
                        e.preventDefault();
                        window.hWin.HEURIST4.msg.showTooltipFlash(window.hWin.HR('Numeric field'),1000,$input);
                    }

                });
                
                $input.on('paste', function(e){
                    if(!Number.isInteger(+e.originalEvent.clipboardData.getData('text'))){
                        window.hWin.HEURIST4.util.stopEvent(e);
                        e.preventDefault();
                        window.hWin.HEURIST4.msg.showTooltipFlash(window.hWin.HR('Numeric field'),1000,$input);
                    }
                });

                if(this.f('rst_Spinner') == 1){

                    let spinner_step = this.f('rst_SpinnerStep');

                    $input.prop('type', 'number').prop('step', spinner_step);

                    // Set minimum and maximum values
                    let max_val = this.f('rst_MaxValue');
                    let min_val = this.f('rst_MinValue');
    
                    if(window.hWin.HEURIST4.util.isNumber(min_val)){
                        $input.prop('min', min_val);
                    }
                    if(window.hWin.HEURIST4.util.isNumber(max_val)){
                        $input.prop('max', max_val);
                    }
                }

            }else
            if(this.detailType=="float"){//----------------------------------------------------

                $input.on('keypress', function (e) {
                    let code = e.charCode || e.keyCode; //(e.keyCode ? e.keyCode : e.which);
                    let charValue = String.fromCharCode(code);
                    let valid = false;

                    if(charValue=='-' && this.value.indexOf('-')<0){
                        this.value = '-'+this.value;
                    }else if(charValue=='.' && this.value.indexOf('.')<0){
                        valid = true;
                    }else{
                        valid = /^[0-9]+$/.test(charValue);
                    }

                    if(!valid){
                        window.hWin.HEURIST4.util.stopEvent(e);
                        e.preventDefault();
                        window.hWin.HEURIST4.msg.showTooltipFlash(window.hWin.HR('Numeric field'),1000,$input);
                    }

                });

                $input.on('paste', function(e){
                    if(!window.hWin.HEURIST4.util.isNumber(e.originalEvent.clipboardData.getData('text'))){
                        window.hWin.HEURIST4.util.stopEvent(e);
                        e.preventDefault();
                        window.hWin.HEURIST4.msg.showTooltipFlash(window.hWin.HR('Numeric field'),1000,$input);
                    }
                });

                if(this.f('rst_Spinner') == 1){

                    let spinner_step = this.f('rst_SpinnerStep');

                    $input.prop('type', 'number').prop('step', spinner_step);

                    // Set minimum and maximum values
                    let max_val = this.f('rst_MaxValue');
                    let min_val = this.f('rst_MinValue');
    
                    if(window.hWin.HEURIST4.util.isNumber(min_val)){
                        $input.prop('min', min_val);
                    }
                    if(window.hWin.HEURIST4.util.isNumber(max_val)){
                        $input.prop('max', max_val);
                    }
                }

            }else
            if(this.detailType=='date'){//----------------------------------------------------
                
                this._createDateInput($input, $inputdiv);
                
                $input.val(value);    
                $input.trigger('change'); 

                let css = 'display: block; font-size: 0.8em; color: #999999; padding: 0.3em 0px;';

                if(this.options.recordset?.entityName=='Records' && this.element.find('.extra_help').length == 0){
                    // Add additional controls to insert yesterday, today or tomorrow

                    let $help_controls = $('<div>', { style: css, class: 'extra_help' })
                        .html('<span class="fake_link">Yesterday</span>'
                            + '<span style="margin: 0px 5px" class="fake_link">Today</span>'
                            + '<span class="fake_link" class="fake_link">Tomorrow</span>'
                            + '<span style="margin-left: 10px;">yyyy, yyyy-mm or yyyy + click calendar (remembers last date)</span>');
    
                    $help_controls.insertBefore(this.input_prompt);
    
                    this._on($help_controls.find('span.fake_link'), {
                        click: function(e){
                            $input.val(e.target.textContent).trigger('change');
                        }
                    });
                }
            }
            else 
            if(this.isFileForRecord){ //----------------------------------------------------
                
				let $input_img;
                
                let select_return_mode = 'recordset';

                /* File IDs, needed for processes below */
                let f_id = value.ulf_ID;
                let f_nonce = value.ulf_ObfuscatedFileID;

                let $clear_container = $('<span id="btn_clear_container"></span>').appendTo( $inputdiv );
                
                $input.css({'padding-left':'30px', cursor:'hand'});
                //folder icon in the begining of field
                let $gicon = $('<span class="ui-icon ui-icon-folder-open"></span>')
                    .css({position: 'absolute', margin: '5px 0px 0px 8px', cursor:'hand'}).insertBefore( $input ); 

                /* Image and Player (enalrged image) container */
                $input_img = $('<br><div class="image_input ui-widget-content ui-corner-all thumb_image" style="margin:5px 0px;border:none;background:transparent;">'
                + '<img id="img'+f_id+'" class="image_input" style="max-width:none;">'
                + '<div id="player'+f_id+'" style="min-height:100px;min-width:200px;display:none;"></div>'
                + '</div>')
                .appendTo( $inputdiv )
                .hide();

				/* Record Type help text for Record Editor */
				$('<br><div class="smallText" style="display:block;color:gray;font-size:smaller;">'
                    + 'Click image to freeze in place</div>')
                .clone()
                .insertAfter( $clear_container )
                .hide();

                /* urls for downloading and loading the thumbnail */
                let dwnld_link = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&debug=1&download=1&file='+f_nonce;
                let url = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&file='+f_nonce+'&mode=tag&origin=recview'; 

                /* Anchors (download and show thumbnail) container */
                let $dwnld_anchor = $('<div class="download_link" style="font-size: smaller;"><br>'
                    + '<a id="lnk'+f_id+'" href="#" oncontextmenu="return false;" style="display:none;padding-right:5px;text-decoration:underline;color:blue"'
                    + '>show thumbnail</a>'
                    + '<a id="dwn'+f_id+'" href="'+window.hWin.HEURIST4.util.htmlEscape(dwnld_link)+'" target="_surf" class="external-link image_tool'
                        + '"style="display:inline-block;text-decoration:underline;color:blue" title="Download image"><span class="ui-icon ui-icon-download" />download</a>'
                    + '</div>')
                .clone()
                .appendTo( $inputdiv )
                .hide();
                
                // Edit file's metadata
                let $edit_details = $('<span class="ui-icon ui-icon-pencil edit_metadata" title="Edit image metadata" style="cursor: pointer;padding-left:5px;">')
                .insertBefore($clear_container);
                this._on($edit_details, {
                    click: function(event){

                        let popup_opts = {
                            isdialog: true, 
                            select_mode: 'manager',
                            edit_mode: 'editonly',
                            rec_ID: f_id,
                            default_palette_class: 'ui-heurist-populate',
                            width: 950,
                            onClose: function(recset){

                                // update external reference, if necessary
                                if(window.hWin.HEURIST4.util.isRecordSet(recset)){

                                    let record = recset.getFirstRecord();

                                    let newvalue = {
                                        ulf_ID: recset.fld(record,'ulf_ID'),
                                        ulf_ExternalFileReference: recset.fld(record,'ulf_ExternalFileReference'),
                                        ulf_OrigFileName: recset.fld(record,'ulf_OrigFileName'),
                                        ulf_MimeExt: recset.fld(record,'fxm_MimeType'),
                                        ulf_ObfuscatedFileID: recset.fld(record,'ulf_ObfuscatedFileID')
                                    };

                                    that.newvalues[$input.attr('id')] = newvalue;
                                    that._findAndAssignTitle($input, newvalue);
                                }
                            }
                        };

                        window.hWin.HEURIST4.ui.showEntityDialog('recUploadedFiles', popup_opts);
                    }
                });//edit details click
                if(!f_id || f_id < 0){
                    $edit_details.hide();
                }

                // Use camera
                let $camera = $('<span>', {class: 'ui-icon ui-icon-camera use_camera', title: 'Take a photo with your camera', style: 'cursor: pointer; padding-left: 5px;'})
                    .insertBefore($clear_container);
                this._on($camera, {
                    click: this._photoMode
                });
                let check_camera = typeof navigator?.mediaDevices?.enumerateDevices === 'function' && window.self === window.top;
                if(check_camera){ // check for camera input
                    navigator.mediaDevices.enumerateDevices()
                    .then((devices) => {
                        let show_camera = false;
                        for(const device of devices){
                            if(device.kind === 'videoinput'){
                                show_camera = true;
                                break;
                            }
                        }
                        if(!show_camera){
                            $camera.hide();
                            this._off($camera, 'click');
                        }
                    }).catch(() => {
                        $camera.hide();
                        this._off($camera, 'click');
                    });
                }else{
                    $camera.hide();
                    this._off($camera, 'click');
                }
                
                /* Change Handler */
                this._on($input,{change: 
                function(event){
					
                    /* new file values */
                    let val = that.newvalues[$input.attr('id')];

                    if(window.hWin.HEURIST4.util.isempty(val) || !(val.ulf_ID >0)){
                        $input.val('');
                    }else{
                        let n_id = val['ulf_ID'];
                        let n_nonce = val['ulf_ObfuscatedFileID'];
                        let n_dwnld_link = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&debug=2&download=1&file='+n_nonce;
                        let n_url = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&file='+n_nonce+'&mode=tag&origin=recview';
                    
                        if(f_id != n_id){// If the image has been changed from original/or has been newly added

                            let $container = $(event.target.parentNode);

                            let $show = $($container.find('a#lnk'+f_id)[0]);
                            let $dwnld = $($container.find('a#dwn'+f_id)[0]);
                            let $player = $($container.find('div#player'+f_id)[0]);
                            let $thumbnail = $($container.find('img#img'+f_id)[0]);
                            let $edit_metadata = $($container.find('.edit_metadata')[0]);

                            $show.attr({'id':'lnk'+n_id});

                            $dwnld.attr({'id':'dwn'+n_id, 'href':n_dwnld_link});

                            $player.attr({'id':'player'+n_id});

                            $thumbnail.attr({'id':'img'+n_id});                       

                            f_id = n_id;
                            f_nonce = n_nonce;
                            dwnld_link = n_dwnld_link;
                            url = n_url;

                            if(!n_id || n_id < 1){
                                $edit_metadata.hide();
                            }else{
                                $edit_metadata.show();
                            }
                        }
                        
                    }
                    
                    //clear thumb rollover
                    if(window.hWin.HEURIST4.util.isempty($input.val())){
                        $input_img.find('img').attr('src','');
                    }

                    that.onChange(); 
                } });//input change
                
                /* Handler Variables */
                let hideTimer = 0, showTimer = 0;  //  Time for hiding thumbnail
                let isClicked = 0;  // Number of image clicks, one = freeze image inline, two = enlarge/srink

                /* Input element's hover handler */
                function __showImagePreview(event){
                    let imgAvailable = !window.hWin.HEURIST4.util.isempty($input_img.find('img').attr('src'));
                    let invalidURL = $inputdiv.find('div.smallText').hasClass('invalidImg');

                    if((imgAvailable || invalidURL) && isClicked == 0){
                        if (hideTimer) {
                            window.clearTimeout(hideTimer);
                            hideTimer = 0;
                        }
                        
                        if($input_img.is(':visible')){
                            $input_img.stop(true, true).show();    
                        }else{
                            if(showTimer==0){
                                showTimer = window.setTimeout(function(){
                                    $input_img.show();
                                    $inputdiv.find('div.smallText').show();
                                    showTimer = 0;
                                },500);
                            }
                        }
                    }
                }
                this._on($input,{mouseover: __showImagePreview});
                this._on($input_img,{mouseover: __showImagePreview}); //mouseover

                /* Input element's mouse out handler, attached and dettached depending on user preferences */
                function __hideImagePreview(event){
                    if (showTimer) {
                        window.clearTimeout(showTimer);
                        showTimer = 0;
                    }

                    if($input_img.is(':visible')){
                        
                        let ele = event.toElement || event.relatedTarget;
                        ele = $(ele);
                        if(ele.hasClass('image_input') || ele.parent().hasClass('image_input')){
                            return;
                        }
                                                
                        hideTimer = window.setTimeout(function(){
                            if(isClicked==0){
                                $input_img.fadeOut(1000);
                                $inputdiv.find('div.smallText').hide(1000);
                            }
                        }, 500);
                    }
                }
                this._on($input, {mouseout:__hideImagePreview});
                this._on($input_img, {mouseout:__hideImagePreview});

                /* Source has loaded */
                function __after_image_load(){
                    setTimeout(() => {

                        let $img = $input_img.find('img');
                        let $close_icon = $inputdiv.find('.ui-icon-window-close');

                        $close_icon.css('left', $img.outerWidth(true) + 10);
                    }, 500);
                };

                /* Thumbnail's click handler */
                this._on($input_img,{click:
                function(event){

                    let elem = event.target;
                    
                    if($(elem).hasClass('ui-icon-window-close')){
                        return;
                    }

                    if (isClicked==0 && !$inputdiv.find('div.smallText').hasClass('invalidImg')){
                        isClicked=1;
                        
                        that._off($input_img,'mouseout');

                        $inputdiv.find('div.smallText').hide(); // Hide image help text

                        let $dwnld_anchor = $($(elem.parentNode.parentNode).find('div.download_link')); // Find the download anchors
                        
                        $dwnld_anchor.show();
                        $inputdiv.find('.ui-icon-window-close').show();

                        if ($dwnld_anchor.find('a#dwnundefined')){  // Need to ensure the links are setup
                            $dwnld_anchor.find('a#dwnundefined').attr({'id':'dwn'+f_id, 'href':dwnld_link});
                            $dwnld_anchor.find('a#lnkundefined').attr({'id':'lnk'+f_id, 'onClick':'window.hWin.HEURIST4.ui.hidePlayer('+f_id+', this.parentNode)'})
                        }

                        $input_img.css('cursor', 'zoom-in');

                        if($input_img.find('img')[0].complete){
                            __after_image_load();
                        }else{
                            $input_img.find('img')[0].addEventListener('load', __after_image_load);
                        }

                        window.hWin.HAPI4.save_pref('imageRecordEditor', 1);
                    }
                    else if (isClicked==1) {

                        /* Enlarge Image, display player */
                        if ($(elem.parentNode).hasClass("thumb_image")) {
                            $(elem.parentNode.parentNode).find('.hideTumbnail').hide();
                            $inputdiv.find('.ui-icon-window-close').hide();

                            $input_img.css('cursor', 'zoom-out');

                            window.hWin.HEURIST4.ui.showPlayer(elem, elem.parentNode, f_id, url);
                        }
                        else {  // Srink Image, display thumbnail
                            $($input_img[1].parentNode).find('.hideTumbnail').show();
                            $inputdiv.find('.ui-icon-window-close').show();

                            $input_img.css('cursor', 'zoom-in');
                        }
                    }
                }}); 

				// for closing inline image when 'frozen'
                let $hide_thumb = $('<span class="hideTumbnail" style="padding-right:10px;color:gray;cursor:pointer;" title="Hide image thumbnail">'
                                + 'close</span>').prependTo( $($dwnld_anchor[1]) ).show();
                // Alternative button for closing inline image
                let $alt_close = $('<span class="ui-icon ui-icon-window-close" title="Hide image display (image shows on rollover of the field)"'
                    + ' style="display: none;cursor: pointer;">&nbsp;</span>').appendTo( $input_img[1] ); // .filter('div')

                this._on($hide_thumb.add($alt_close), {
                    click:function(event){

                        isClicked = 0;

                        that._on($input, {mouseout:__hideImagePreview});
                        that._on($input_img, {mouseout:__hideImagePreview});

                            $dwnld_anchor.hide();
                            $inputdiv.find('.ui-icon-window-close').hide();

                        if($inputdiv.find('div.smallText').find('div.smallText').hasClass('invalidImg')){
                            $input_img.hide().css('cursor', '');
                        }else{
                            $input_img.hide().css('cursor', 'pointer');
                        }
                    }
                });

				/* Show Thumbnail handler */
                $('#lnk'+f_id).on("click", function(event){
                    window.hWin.HEURIST4.ui.hidePlayer(f_id, event.target.parentNode.parentNode.parentNode);
					
                    $(event.target.parentNode.parentNode).find('.hideTumbnail').show();
				});
                
                let $mirador_link = $('<a href="#" data-id="'+f_nonce+'" class="miradorViewer_link" style="color: blue;" title="Open in Mirador">'
                    +'<span class="ui-icon ui-icon-mirador" style="width:12px;height:12px;margin-left:5px;font-size:1em;display:inline-block;vertical-align: middle;'
                    +'filter: invert(35%) sepia(91%) saturate(792%) hue-rotate(174deg) brightness(96%) contrast(89%);'
                    +'"></span>&nbsp;Mirador</a>').appendTo( $dwnld_anchor ).hide();
                    
                this._on($mirador_link, {click:function(event){
                    let ele = $(event.target)

                    if(!ele.attr('data-id')){
                        ele = ele.parents('[data-id]');
                    }
                    let obf_recID = ele.attr('data-id');
                    let is_manifest = (ele.attr('data-manifest')==1);

                    let url =  window.hWin.HAPI4.baseURL
                    + 'hclient/widgets/viewers/miradorViewer.php?db=' 
                    +  window.hWin.HAPI4.database
                    + '&recID=' + that.options.recID
                    + '&' + (is_manifest?'iiif':'iiif_image') + '=' + obf_recID;

                    const show_mirador_in_popup = true;
                    if(show_mirador_in_popup){
                        //borderless:true, 
                        window.hWin.HEURIST4.msg.showDialog(url, 
                            {dialogid:'mirador-viewer',
                                //resizable:false, draggable: false, 
                                //maximize:true, 
                                default_palette_class: 'ui-heurist-explore',
                                width:'90%',height:'95%',
                                allowfullscreen:true,'padding-content':'0px'});   

                        let $dlg = $(window.hWin?window.hWin.document:document).find('body #mirador-viewer');

                        $dlg.parent().css('top','50px');
                    }else{
                        window.open(url, '_blank');        
                    }                      

                    //data-id
                }});

                /* Check User Preferences, displays thumbnail inline by default if set */
                if (window.hWin.HAPI4.get_prefs_def('imageRecordEditor', 0)!=0 && value.ulf_ID) {

                    $input_img.show();
                    $dwnld_anchor.show();

                    $inputdiv.find('.ui-icon-window-close').show();

                    $input_img.css('cursor', 'zoom-in');

                    $input.off("mouseout");

                    if($input_img.find('img')[0].complete){
                        __after_image_load();
                    }else{
                        $input_img.find('img')[0].addEventListener('load', __after_image_load);
                    }

                    isClicked=1;
                }

                const isTiledImage = this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_TILED_IMAGE_SOURCE']     
                    && this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_SERVICE_URL'];
                 
                let popup_options = {
                    isdialog: true,
                    select_mode: 'select_single',
                    additionMode: isTiledImage?'tiled':'any',  //AAAA
                    edit_addrecordfirst: true, //show editor at once
                    select_return_mode:select_return_mode, //ids or recordset(for files)
                    filter_group_selected:null,
                    filter_groups: this.configMode.filter_group,
                    default_palette_class: 'ui-heurist-populate',
                    onselect:function(event, data){

                     if(data){
                        
                            if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                                let recordset = data.selection;
                                let record = recordset.getFirstRecord();
                                
                                let newvalue = {ulf_ID: recordset.fld(record,'ulf_ID'),
                                                ulf_ExternalFileReference: recordset.fld(record,'ulf_ExternalFileReference'),
                                                ulf_OrigFileName: recordset.fld(record,'ulf_OrigFileName'),
                                                ulf_MimeExt: recordset.fld(record,'fxm_MimeType'),
                                                ulf_ObfuscatedFileID: recordset.fld(record,'ulf_ObfuscatedFileID')};
                                
                                that.newvalues[$input.attr('id')] = newvalue;
                                that._findAndAssignTitle($input, newvalue);
                            }
                        
                     }//data

                    }
                };//popup_options

                that._findAndAssignTitle($input, value);

                let __show_select_dialog = function(event){
                    
                        if(that.is_disabled) return;

                        event.preventDefault();
                        
                        let usrPreferences = window.hWin.HAPI4.get_prefs_def('select_dialog_'+this.configMode.entity, 
                            {width: null,  //null triggers default width within particular widget
                            height: (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95 });
            
                        popup_options.width = usrPreferences.width;
                        popup_options.height = usrPreferences.height;
                        let sels = this.newvalues[$(event.target).attr('id')];
                        if(!sels && this.options.values && this.options.values[0]){
                             sels = this.options.values[0];    //take selected value from options
                        } 

                        if($.isPlainObject(sels)){
                            popup_options.selection_on_init = sels;
                        }else if(!window.hWin.HEURIST4.util.isempty(sels)){
                            popup_options.selection_on_init = sels.split(',');
                        } else {
                            popup_options.selection_on_init = null;    
                        }                                                                                       
                        //init dialog to select related uploaded files
                        window.hWin.HEURIST4.ui.showEntityDialog(this.configMode.entity, popup_options);
                }
                
                if(__show_select_dialog!=null){
                   
                    this._on( $input, { keypress: __show_select_dialog, click: __show_select_dialog } );
                    this._on( $gicon, { click: __show_select_dialog } );
                }
                
                if(this.isFileForRecord && value){
                    //assign value at once
                    this.newvalues[$input.attr('id')] = value;
                }
            }
            else
            if( this.detailType=='folder' ){ //----------------------------------------------------
                
                $input.css({'padding-left':'30px'});
                
                let $gicon = $('<span>').addClass('ui-icon ui-icon-gear')
                    .css({position:'absolute',margin:'2px 0 0 8px',cursor:'hand'})
                    .insertBefore($input);
                let $select_folder_dlg = $('<div/>').hide().appendTo( $inputdiv );
                
                that.newvalues[$input.attr('id')] = value;
                    
                this._on( $gicon, { click: function(){                                 
                       $select_folder_dlg.selectFolders({
                       onselect:function(newsel){
                            if(newsel){
                                newsel = newsel.join(';');
                                that.newvalues[$input.attr('id')] = newsel;
                                $input.val(newsel);
                                that.onChange();
                            }
                        }, 
                       selectedValues: that.newvalues[$input.attr('id')], 
                       multiselect: that.configMode && that.configMode.multiselect});
                    }} );
            }
            else
            if( this.detailType=='file' ){ //----------------------------------------------------
                
                        let fileHandle = null; //to support file upload cancel
                
                        this.options.showclear_button = (this.configMode.hideclear!=1);
                        
                        if(!this.configMode.version) this.configMode.version = 'thumb';
                
                        //url for thumb
                        let urlThumb = window.hWin.HAPI4.getImageUrl(this.configMode.entity, 
                                                        this.options.recID, this.configMode.version, 1);
                        let dt = new Date();
                        urlThumb = urlThumb+'&ts='+dt.getTime();
                        
                        $input.css({'padding-left':'30px'});
                        $('<span class="ui-icon ui-icon-folder-open"></span>')
                                .css({position: 'absolute', margin: '5px 0px 0px 8px'}).insertBefore( $input ); 
                        
                        let sz = 0;
                        if(that.options.dtID=='rty_Thumb'){
                            sz = 64;
                        }else if(that.options.dtID=='rty_Icon'){
                            sz = 16;
                        }
                        
                        //container for image
                        let $input_img = this.input_img = $('<div tabindex="0" contenteditable class="image_input fileupload ui-widget-content ui-corner-all" style="border:dashed blue 2px;">'
                            + '<img src="'+urlThumb+'" class="image_input" style="'+(sz>0?('width:'+sz+'px;'):'')+'">'
                            + '</div>').appendTo( $inputdiv );                
                        if(this.configMode.entity=='recUploadedFiles'){
                           this.input_img.css({'min-height':'320px','min-width':'320px'});
                           this.input_img.find('img').css({'max-height':'320px','max-width':'320px'});
                        }
                         
                        window.hWin.HAPI4.checkImage(this.configMode.entity, this.options.recID, 
                            this.configMode.version,
                            function(response){
                                  if(response.data=='ok'){
                                      that.entity_image_already_uploaded = true;
                                  }
                        });
                        
                        //change parent div style - to allow special style for image selector
                        if(that.configMode.css){
                            that.element.css(that.configMode.css);
                        }
                        
                        //library browser and explicit file upload buttons
                        if(that.configMode.use_assets){
                            
                            if(value){
                                that.newvalues[$input.attr('id')] = value; 
                            }
                            
                            let ele = $('<div style="display:inline-block;vertical-align:top;padding-left:4px" class="file-options-container" />')
                                .appendTo( $inputdiv );                            

                            $('<a href="#" title="Select from a library of images"><span class="ui-icon ui-icon-grid"/>Library</a>')
                                .on('click', function(){that.openIconLibrary()}).appendTo( ele );

                            $('<br><br>').appendTo( ele );

                            $('<a href="#" title="or upload a new image"><span class="ui-icon ui-icon-folder-open"/><span class="upload-file-text">Upload file</span></a>')
                                .on('click', function(){ $input.trigger('click') }).appendTo( ele );
                        }
                            
                        //set input as file and hide
                        $input.prop('type','file').hide();
                        
                        //temp file name  it will be renamed on server to recID.png on save
                        let newfilename = '~'+window.hWin.HEURIST4.util.random();

                        //crate progress dialog
                        let $progress_dlg = $('<div title="File Upload"><div class="progress-label">Starting upload...</div>'
                        +'<div class="progressbar" style="margin-top: 20px;"></div>'
                        +'<div style="padding-top:4px;text-align:center"><div class="cancelButton">Cancel upload</div></div></div>')
                        .hide().appendTo( $inputdiv );
                        let $progress_bar = $progress_dlg.find('.progressbar');
                        let $progressLabel = $progress_dlg.find('.progress-label');
                        let $cancelButton = $progress_dlg.find('.cancelButton');

                        this.select_imagelib_dlg = $('<div/>').hide().appendTo( $inputdiv );//css({'display':'inline-block'}).

                        $progress_bar.progressbar({
                            value: false,
                            change: function() {
                                $progressLabel.text( "Current Progress: " + $progress_bar.progressbar( "value" ) + "%" );
                            },
                            complete: function() {
                                $progressLabel.html( "Upload Complete!<br>processing on server, this may take up to a minute" );
                                $cancelButton.hide().off('click');
                            }
                        });

                        // Setup abort button
                        $cancelButton.button();
                        this._on($cancelButton, {
                            click: function(){

                                if(fileHandle && fileHandle.abort){
                                    fileHandle.message = 'File upload was aborted';
                                    fileHandle.abort();
                                }

                               
                            }
                        });
                        
        let max_file_size = Math.min(window.hWin.HAPI4.sysinfo['max_post_size'], window.hWin.HAPI4.sysinfo['max_file_size']);

        let fileupload_opts = {
    url: window.hWin.HAPI4.baseURL + 'hserv/controller/fileUpload.php',
    formData: [ {name:'db', value: window.hWin.HAPI4.database}, 
                {name:'entity', value:this.configMode.entity},
                {name:'version', value:this.configMode.version},
                {name:'maxsize', value:this.configMode.size}, //dimension
                {name:'registerAtOnce', value:this.configMode.registerAtOnce},
                {name:'recID', value:that.options.recID}, //need to verify permissions
                {name:'newfilename', value:newfilename }], //unique temp name
    //acceptFileTypes: /(\.|\/)(gif|jpe?g|png)$/i
    //autoUpload: true,
    //multipart: (window.hWin.HAPI4.sysinfo['is_file_multipart_upload']==1),
    //to check file size on client side
    max_file_size: max_file_size,
    sequentialUploads: true,
    dataType: 'json',
    pasteZone: $input_img,
    dropZone: $input_img,
    
    add: function (e, data) {
        if (e.isDefaultPrevented()) {
            return false;
        }

        if(window.hWin.HAPI4.sysinfo['is_file_multipart_upload']!=1 && 
            data.files && data.files.length>0 && data.files[0].size>max_file_size)
        {
                data.message = `The upload size of ${data.files[0].size} bytes exceeds the limit of ${max_file_size}`
                +` bytes.<br><br>If you need to upload larger files please contact the system administrator ${window.hWin.HAPI4.sysinfo.sysadmin_email}`;

                data.abort();
                
        }else if (data.autoUpload || (data.autoUpload !== false &&
                $(this).fileupload('option', 'autoUpload'))) 
        {
            fileHandle = data;
            data.process().done(function () {
                data.submit();
            });
        }

    },
    submit: function (e, data) { //start upload
    
        $progress_dlg = $progress_dlg.dialog({
            autoOpen: false,
            modal: true,
            closeOnEscape: false,
            resizable: false,
            buttons: []
          });                        
        $progress_dlg.dialog('open'); 
        $progress_dlg.parent().find('.ui-dialog-titlebar-close').hide();
    },
    done: function (e, response) {
        
            //hide progress bar
            $progress_dlg.dialog( "close" );
        
            if(response.result){//file upload places our data to 'result'
                response = response.result;
            }
            if(response.status==window.hWin.ResponseStatus.OK){
                let data = response.data;

                $.each(data.files, function (index, file) {
                    if(file.error){ //it is not possible we should cought it on server side - just in case
                        $input_img.find('img').prop('src', '');
                        if(that.linkedImgContainer !== null){
                            that.linkedImgContainer.find('img').prop('src', '');
                        }

                        window.hWin.HEURIST4.msg.showMsgErr({message: file.error, error_title: 'File upload error'});
                    }else{

                        if(file.ulf_ID>0){ //file is registered at once and it returns ulf_ID
                            that.newvalues[$input.attr('id')] = file.ulf_ID;
                            if(that.linkedImgInput !== null){
                                that.newvalues[that.linkedImgInput.attr('id')] = file.ulf_ID;
                            }
                        }else{
                            
                            let urlThumb =
                            (that.configMode.entity=='recUploadedFiles'
                                ?file.url
                                :file[(that.configMode.version=='icon')?'iconUrl':'thumbnailUrl'])
                                +'?'+(new Date()).getTime();
                            
                            // file.thumbnailUrl - is correct but inaccessible for heurist server
                            // we get image via fileGet.php
                            $input_img.find('img').prop('src', '');
                            $input_img.find('img').prop('src', urlThumb);
                            if(that.configMode.entity=='recUploadedFiles'){
                                that.newvalues[$input.attr('id')] = file;
                            }else{
                                that.newvalues[$input.attr('id')] = newfilename;  //keep only tempname
                            }
                        }
                        $input.attr('title', file.name);
                        that.onChange();//need call it manually since onchange event is redifined by fileupload widget
                    }
                });
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);// .message
            }
            let inpt = this;
            $input_img.off('click');
            $input_img.on({click: function(){
                        $(inpt).trigger('click');
            }});
    },
    fail: function(e, data){

        if($progress_dlg.dialog('instance')){
            $progress_dlg.dialog("close");   
        }
        
        if(!window.hWin.HEURIST4.util.isnull(fileHandle) && fileHandle.message){ // was aborted by user
            window.hWin.HEURIST4.msg.showMsgFlash(fileHandle.message, 3000);
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
            
            window.hWin.HEURIST4.msg.showMsgErr({message: msg, error_title: 'File upload error'});
        }

        fileHandle = null;
    },
    progressall: function (e, data) { //@todo to implement
        let progress = parseInt(data.loaded / data.total * 100, 10);
       
        $progress_bar.progressbar( "value", progress );        
    }                            
                        };      
                        
    if(window.hWin.HAPI4.sysinfo['is_file_multipart_upload']==1){
        fileupload_opts['multipart'] = true;
        fileupload_opts['maxChunkSize'] = 10485760; //10M
    }
        
    const isTiledImage = that.configMode.tiledImageStack ||
                        (that.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_TILED_IMAGE_SOURCE']     
                        && that.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_SERVICE_URL']);
    if(isTiledImage){
        fileupload_opts['formData'].push({name:'tiledImageStack', value:1});
        fileupload_opts['formData'].push({name: 'acceptFileTypes', value:'zip|mbtiles'});
        
        $input.attr('accept','.zip, .mbtiles');
    }                
       
                        //init upload widget
                        $input.fileupload( fileupload_opts );
                
                        //init click handlers
                       
                        $input_img.on({click: function(e){ //find('a')
                            $input.trigger('click'); //open file browse
                        }});
            }
            else //------------------------------------------------------------------------------------
            if(this.detailType=='action'){
                
                $input.css({'width':'62ex','padding-left':'30px',cursor:'hand'});
                   
                let $gicon = $('<span>').addClass('ui-icon ui-icon-gear')
                    .css({position:'absolute',margin:'2px 0 0 8px',cursor:'hand'})
                    .insertBefore($input);
            
                //parse and return json object
                that.newvalues[$input.attr('id')] = window.hWin.HEURIST4.util.isJSON(value);
                if(that.newvalues[$input.attr('id')]==false){
                    that.newvalues[$input.attr('id')] = {};
                }
                $input.val(JSON.stringify(that.newvalues[$input.attr('id')])).css('cursor','hand');
                
                      
                let __show_action_dialog = function (event){
                        event.preventDefault();
                        
                        if(that.is_disabled) return;
                        
                        let dlg_options = that.newvalues[$input.attr('id')];
                        if(  window.hWin.HEURIST4.util.isempty(dlg_options) ){
                            dlg_options = {};
                        }
                        dlg_options.title = that.configMode.title;
                        dlg_options.get_params_only = true;
                        dlg_options.onClose = function(value){
                            if(value){
                                that.newvalues[$input.attr('id')] = window.hWin.HEURIST4.util.isJSON(value);
                                if(that.newvalues[$input.attr('id')]==false){
                                    that.newvalues[$input.attr('id')] = {};
                                }
                                $input.val(JSON.stringify(that.newvalues[$input.attr('id')])).trigger('change');
                            }
                        };
                        dlg_options.default_palette_class = 'ui-heurist-populate';

                        window.hWin.HEURIST4.ui.showRecordActionDialog( this.configMode.actionName, dlg_options );
                };

                this._on( $input, { keypress: __show_action_dialog, click: __show_action_dialog } );
                this._on( $gicon, { click: __show_action_dialog } );
            }
            else 
            if(this.detailType=='geo'){   //----------------------------------------------------
                
                $input.css({'width':'62ex','padding-left':'30px',cursor:'hand'});
                
                let $gicon = $('<span>').addClass('ui-icon ui-icon-globe')
                    .css({position:'absolute',margin:'4px 0 0 8px',cursor:'hand'})
                    .insertBefore($input);

                let geovalue = window.hWin.HEURIST4.geo.wktValueToDescription(value);
            
                that.newvalues[$input.attr('id')] = value;

                if(geovalue.summary && geovalue.summary != ''){
                    $input.val(geovalue.type+'  '+geovalue.summary).css('cursor','hand');
                }else if(!window.hWin.HEURIST4.util.isempty(value)){
                    let parsedWkt = window.hWin.HEURIST4.geo.getParsedWkt(value, true);
                    if(parsedWkt == '' || parsedWkt == null){
                        $input.val('');
                        $('<span>').addClass('geo-badvalue').css({'display': 'inline-block', 'margin-left': '5px'}).text('Bad value: ' + value).appendTo($inputdiv);
                    }else{
                        if(parsedWkt.type == 'Point'){

                            let invalid = '';
                            if(Math.abs(parsedWkt.coordinates[0]) > 180){
                                invalid = 'longitude is';
                            }
                            if(Math.abs(parsedWkt.coordinates[1]) > 90){
                                invalid = (invalid != '') ? 'longitude and latitude are' : 'latitude is';
                            }
                            $('<span>').addClass('geo-badvalue').css({'display': 'inline-block', 'margin-left': '5px', color: 'red'}).text(invalid + ' outside of range').appendTo($inputdiv);
                        }
                    }
                }
                      
                let __show_mapdigit_dialog = function (event){
                        event.preventDefault();
                        
                        if(that.is_disabled) return;
                    
                        let url = window.hWin.HAPI4.baseURL 
                            +'viewers/map/mapDraw.php?db='+window.hWin.HAPI4.database;
                       
                        let wkt_params = {'wkt': that.newvalues[$input.attr('id')] };
                        if(that.options.is_faceted_search){
                            wkt_params['geofilter'] = true;
                        }

                        if(this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_GEOTIFF_SOURCE']){

                            let ele = that.options.editing.getFieldByName(window.hWin.HAPI4.sysinfo['dbconst']['DT_FILE_RESOURCE']);
                            let vals = ele.editing_input('getValues');
                            if(Array.isArray(vals) && vals.length>0){
                                vals = vals[0];
                                if(vals['ulf_ExternalFileReference']){
                                    wkt_params['imageurl'] = vals['ulf_ExternalFileReference'];
                                }else{
                                    wkt_params['imageurl'] = window.hWin.HAPI4.baseURL
                                        +'?db='+window.hWin.HAPI4.database
                                        +'&file='+vals['ulf_ObfuscatedFileID'];
                                }
                                wkt_params['tool_option'] = 'image';
                            }else{
                                wkt_params['tool_option'] = 'rectangle';
                            }
                        }

                        if(this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_KML_SOURCE'] || this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_FILE_SOURCE'] || 
                        this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_SHP_SOURCE'] || this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT'] || 
                        this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_LAYER'] || this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_QUERY_SOURCE'] || 
                        this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_TILED_IMAGE_SOURCE']) {
                            // assume bounding box, rectangle tool only
                            wkt_params['tool_option'] = 'rectangle';
                        }

                        let d_width = (window.hWin?window.hWin.innerWidth:window.innerWidth)*0.95,
                        d_height = (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95;

                        window.hWin.HEURIST4.msg.showDialog(url, {
                            height:that.options.is_faceted_search?540:d_height,
                            width:that.options.is_faceted_search?600:d_width,
                            window: window.hWin,  //opener is top most heurist window
                            dialogid: 'map_digitizer_dialog',
                            default_palette_class: 'ui-heurist-populate',
                            params: wkt_params,
                            title: window.hWin.HR('Heurist map digitizer'),
                            //class:'ui-heurist-bg-light',
                            callback: function(location){
                                if( !window.hWin.HEURIST4.util.isempty(location) ){
                                    //that.newvalues[$input.attr('id')] = location
                                    that.newvalues[$input.attr('id')] = (that.options.is_faceted_search
                                                ?'':(location.type+' '))
                                                +location.wkt;
                                    let geovalue = window.hWin.HEURIST4.geo.wktValueToDescription(location.type+' '+location.wkt);
                                    if(that.options.is_faceted_search){
                                        $input.val(geovalue.summary).trigger('change');
                                    }else{
                                        $input.val(geovalue.type+'  '+geovalue.summary);
                                        $input.trigger('change');

                                        $inputdiv.find('span.geo-badvalue').remove();
                                    }
                                    
                                    //$input.val(location.type+' '+location.wkt)
                                }
                            }
                        } );
                };

               
               
                this._on( $input, { keypress: __show_mapdigit_dialog, click: __show_mapdigit_dialog } );
                this._on( $gicon, { click: __show_mapdigit_dialog } );

            }
            else if(this.configMode && this.configMode['colorpicker']){ //-----------------------------------------------

                $input.colorpicker({
                    hideButton: false, //show button right to input
                    showOn: "both",
                    val:value
                }).css('max-width', '130px');

                $input.parent('.evo-cp-wrap').css({display:'inline-block',width:'180px'});

            }
            else 
            if(this.options.dtID && this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_BOOKMARK']){ // Geo Bookmark, five input form, experimental 

                $input.css({cursor:'hand'});

                let __show_geoBookmark_dialog = function(event) {
                    event.preventDefault();

                    if(that.is_disabled) return;

                    let current_val = $input.val();

                    // split current_val into parts based on , 
                    let setup_val = current_val.split(",");

                    let $dlg = null;

                    let pdiv = '<div style="display:grid;grid-template-columns:100%;">'

                            + '<div style="margin-bottom:10px;display:grid;grid-template-columns:150px 200px;">'
                            + '<label class="required">Bookmark Name:</label><input type="text" id="bkm_name"></div>'

                            + '<div style="margin-bottom:10px;display:grid;grid-template-columns:150px 200px;">'
                            + '<label class="required">Bottom left (X, Y):</label><input type="text" id="bkm_long" class="bkm_points" style="cursor:pointer;"></div>'

                            + '<div style="margin-bottom:10px;display:grid;grid-template-columns:150px 200px;">'
                            + '<label class="required">Top right (X, Y):</label><input type="text" id="bkm_lat" class="bkm_points" style="cursor:pointer;"></div>'

                            + '<div style="margin-bottom:10px;display:grid;grid-template-columns:150px 200px;">'
                            + '<label style="color:#6A7C99">Starting Date:</label><input type="text" id="bkm_sdate"></div>'

                            + '<div style="margin-bottom:10px;display:grid;grid-template-columns:150px 200px;">'
                            + '<label style="color:#6A7C99">Ending Date:</label><input type="text" id="bkm_edate"></div>'

                    let popele = $(pdiv);

                    popele.find('input[class="bkm_points"]').on('click', function(e){
                        let url = window.hWin.HAPI4.baseURL 
                            +'viewers/map/mapDraw.php?db='+window.hWin.HAPI4.database;

                        let wkt_points = $('input[id="bkm_long"]').val() + ',' + $('input[id="bkm_lat"]').val();
                        let points = wkt_points.split(/[\s,]+/);

                        let geo_points = points[0] + ',' + points[2] + ' ' + points[1] + ',' + points[3];

                        let wkt_params = {'wkt': geo_points};
                        wkt_params['tool_option'] = 'rectangle';

                        let d_width = (window.hWin?window.hWin.innerWidth:window.innerWidth)*0.95,
                        d_height = (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95;

                        window.hWin.HEURIST4.msg.showDialog(url, {
                            height:that.options.is_faceted_search?540:d_height,
                            width:that.options.is_faceted_search?600:d_width,
                            window: window.hWin,  //opener is top most heurist window
                            dialogid: 'map_digitizer_dialog',
                            default_palette_class: 'ui-heurist-populate',
                            params: wkt_params,
                            title: window.hWin.HR('Heurist map digitizer'),
                            callback: function(location){
                                if( !window.hWin.HEURIST4.util.isempty(location) ){
                                    
                                    let geovalue = window.hWin.HEURIST4.geo.wktValueToDescription(location.type+' '+location.wkt, true);
                                    let geocode = geovalue.summary;
                                    geocode = geocode.replace('X', '');
                                    geocode = geocode.replace('Y', '');
                                    geocode = geocode.replace(' ', '');

                                    let points = geocode.split(/[\s,]+/);

                                    $('input[id="bkm_long"]').val(points[0] + ',' + points[2]).trigger('change');
                                    $('input[id="bkm_lat"]').val(points[1] + ',' + points[3]).trigger('change');
                                }
                            }
                        } );
                    });

                    popele.find('input[id="bkm_name"]').val(setup_val[0]);
                    popele.find('input[id="bkm_long"]').val(setup_val[1] +','+ setup_val[2]);
                    popele.find('input[id="bkm_lat"]').val(setup_val[3] +','+ setup_val[4]);

                    if(setup_val.length == 7){
                        popele.find('input[id="bkm_sdate"]').val(setup_val[5]);
                        popele.find('input[id="bkm_edate"]').val(setup_val[6]);
                    }

                    let btns = [
                        {text:window.hWin.HR('Apply'),
                            click: function(){

                                let title = popele.find('input[id="bkm_name"]').val();
                                let long_points = popele.find('input[id="bkm_long"]').val();
                                let lat_points = popele.find('input[id="bkm_lat"]').val();
                                let sdate = popele.find('input[id="bkm_sdate"]').val();
                                let edate = popele.find('input[id="bkm_edate"]').val();

                                let geo_points = long_points + ',' + lat_points;

                                if(window.hWin.HEURIST4.util.isempty(title) || window.hWin.HEURIST4.util.isempty(geo_points)){
                                    window.hWin.HEURIST4.msg.showMsgFlash('A title and map points must be provided', 2500);
                                    return;
                                }

                                let points = geo_points.split(/[\s,]+/);

                                if(points.length != 4){
                                    window.hWin.HEURIST4.msg.showMsgFlash('You need 2 sets of geographical points', 2500);
                                    return;
                                }

                                geo_points = "";
                                for(let i = 0; i < points.length; i++){
                                    let n = points[i];
                                    geo_points = geo_points + ',' + parseFloat(n).toFixed(2);
                                }

                                let has_start_date = window.hWin.HEURIST4.util.isempty(sdate);
                                let has_end_date = window.hWin.HEURIST4.util.isempty(edate);

                                if(has_start_date && has_end_date){
                                    $input.val(title + geo_points);
                                }
                                else if(!has_start_date && !has_end_date){
                                    $input.val(title + geo_points +','+ sdate +','+ edate);
                                }
                                else{
                                    window.hWin.HEURIST4.msg.showMsgFlash('You must provide both a start and end date, or neither', 2500);
                                    return;
                                }

                                $dlg.dialog('close');
                            }
                        },
                        {text:window.hWin.HR('Close'),
                            click: function() { $dlg.dialog('close'); }
                        }
                    ];

                    $dlg = window.hWin.HEURIST4.msg.showElementAsDialog({
                        window:  window.hWin, //opener is top most heurist window
                        title: window.hWin.HR('Geographical bookmark form'),
                        width: 575,
                        height: 260,
                        element:  popele[0],
                        resizable: false,
                        buttons: btns,
                        default_palette_class: 'ui-heurist-populate'
                    });                 
                }
                this._on( $input, { keypress: __show_geoBookmark_dialog, click: __show_geoBookmark_dialog } );   
            } // end of geo bookmark
            
        }//end if by detailType

        //----------------- color or symbology editor
        if( this.options.dtID > 0 && this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_SYMBOLOGY']){

                if(that.options.rectypeID!=window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_LAYER']){
                    $input.attr('readonly','readonly');
                }
                
                if(this.options.rectypeID == window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME']){
                    
                        //custom/user heurist theme
                        let $btn_edit_switcher2 = $( '<span>open editor</span>', {title: 'Open color sheme editor'})
                            .addClass('smallbutton btn_add_term')
                            .css({'line-height': '20px','vertical-align':'top',cursor:'pointer','text-decoration':'underline'})
                            .appendTo( $inputdiv );

                        let $btn_edit_clear2 = $( '<span>reset colors</span>', {title: 'Reset default color settings'})
                            .addClass('smallbutton btn_add_term')
                            .css({'line-height': '20px','vertical-align':'top',cursor:'pointer','text-decoration':'underline'})
                            .appendTo($inputdiv )
                            .on( { click: function(){ $input.val('');that.onChange(); } });
                            
                        function __openThemeDialog(){
                                let current_val = window.hWin.HEURIST4.util.isJSON( $input.val() );
                                if(!current_val) current_val = {};
                                window.hWin.HEURIST4.ui.showEditThemeDialog(current_val, false, function(new_value){
                                    $input.val(JSON.stringify(new_value));
                                    that.onChange();
                                });
                        }                
                        
                        $input.css({'max-width':'400px'}).on({ click: __openThemeDialog });
                        $btn_edit_switcher2.on( { click: __openThemeDialog });
                    
                }else{
                
                    let $btn_edit_switcher = $( '<span>style editor</span>', {title: 'Open symbology editor'})
                        //.addClass('smallicon ui-icon ui-icon-gear btn_add_term')
                        .addClass('smallbutton btn_add_term')
                        .css({'line-height': '20px','vertical-align':'top',cursor:'pointer','text-decoration':'underline'})
                        .appendTo( $inputdiv );
                    
                    this._on( $btn_edit_switcher, { click: function(){
                        
                            let mode_edit = 0;
                            let current_val = window.hWin.HEURIST4.util.isJSON($input.val());
                            if(!current_val) current_val = {};
                        
                            if(that.options.rectypeID==window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_LAYER']){
                                
                                //get query from linked datasource
                                let ele = that.options.editing.getFieldByName(window.hWin.HAPI4.sysinfo['dbconst']['DT_DATA_SOURCE']);
                                let vals = ele.editing_input('getValues');
                                let dataset_record_id = vals[0];
                                
                                if(dataset_record_id>0){
                                    
                                    const DT_QUERY_STRING = window.hWin.HAPI4.sysinfo['dbconst']['DT_QUERY_STRING'];
                                
                                    let server_request = {
                                        q: 'ids:'+dataset_record_id,
                                        restapi: 1,
                                        columns: 
                                        ['rec_ID', 'rec_RecTypeID', DT_QUERY_STRING],
                                        zip: 1,
                                        format:'json'};
                    
                                    //perform search see record_output.php       
                                    window.hWin.HAPI4.RecordMgr.search_new(server_request,
                                        function(response){
                                               if(window.hWin.HEURIST4.util.isJSON(response)) {
                                                    let hquery = null;
                                                    mode_edit = 3;
                                                    if(response['records'] && response['records'].length>0){
                                                        let rectype = response['records'][0]['rec_RecTypeID']; 
                                                        
                                                        if (rectype==window.hWin.HAPI4.sysinfo['dbconst']['RT_IMAGE_SOURCE']
                                                        || rectype==window.hWin.HAPI4.sysinfo['dbconst']['RT_TILED_IMAGE_SOURCE']){
                                                            //show image filter dialogue
                                                            window.hWin.HEURIST4.ui.showImgFilterDialog(current_val, function(new_value){
                                                                $input.val(JSON.stringify(new_value));
                                                                that.onChange();
                                                            });
                                                            return;                                                            
                                                        }else if (rectype==window.hWin.HAPI4.sysinfo['dbconst']['RT_QUERY_SOURCE']){
                                                            
                                                            let res = response['records'][0]['details'];
                                                            if(res[DT_QUERY_STRING]){
                                                                //{12:{4407:"t:10"}}
                                                                hquery = res[DT_QUERY_STRING][ Object.keys(res[DT_QUERY_STRING])[0] ];
                                                            }
                                                            
                                                        }
                                                    }
                                                    
                                                    current_val.maplayer_query = hquery;
                                                    
                                                    window.hWin.HEURIST4.ui.showEditSymbologyDialog(current_val, 
                                                            hquery==null?1:3, function(new_value){
                                                        $input.val(JSON.stringify(new_value));
                                                        that.onChange();
                                                    });
                                                    
                                                }
                                            });
                                    return;        
                                }
                            }
                        
                            window.hWin.HEURIST4.ui.showEditSymbologyDialog(current_val, mode_edit, function(new_value){
                                $input.val(JSON.stringify(new_value));
                                that.onChange();
                            });
                    }});
            
                }
             
        }//end color/symbol editor
        
        else if( this.options.dtID > 0 && this.options.dtID == window.hWin.HAPI4.sysinfo['dbconst']['DT_MAP_IMAGE_WORLDFILE']){

            let $btn_edit_switcher = $( '<span>calculate extent</span>', 
                {title: 'Get image extent based on worldfile parameters and image width and height'})
                    .addClass('smallbutton btn_add_term')
                    .css({'line-height': '20px','vertical-align':'top',cursor:'pointer','text-decoration':'underline'})
                    .appendTo( $inputdiv );

            this._on( $btn_edit_switcher, { click: function(){
                calculateImageExtentFromWorldFile( that.options.editing );
            }});
        }

        // Freetext value that is a url
        let freetext_url = this.detailType=="freetext" && ($input.val().indexOf('http://')==0 || $input.val().indexOf('https://')==0);
        // Semantic url links, separated by semi-colons, for RecTypes, Vocab+Terms, DetailTypes
        let semantic_uri = this.options.dtID && (typeof this.options.dtID === 'string' || this.options.dtID instanceof String)
                            && this.options.dtID.indexOf('ReferenceURL') !== -1;
        if($inputdiv.find('.ui-icon-extlink').length == 0 && (freetext_url || semantic_uri)){

            let $btn_extlink = $( '<span>', {title: 'Open URL(s) in new window'})
                .addClass('smallicon ui-icon ui-icon-extlink')
                .appendTo( $inputdiv );

            that._on($btn_extlink, { 
                click: function(){
                    let cur_val = $input.val();
                    if(!window.hWin.HEURIST4.util.isempty(cur_val)){ // check for value
                        let urls = cur_val.split(';');
                        urls = urls.map((url, idx) => { 
                            if(!window.hWin.HEURIST4.util.isempty(url)){
                                url = url.trim();
                                window.open(url);
                                return url;
                            }
                        });
                    }
                } 
            });
        }

        //for calculated field
        if(window.hWin.HAPI4.is_admin() && this.options.dtFields && this.options.dtFields['rst_CalcFunctionID']>0){            
            
            let $btn_calcfield = $( '<span>', {title: 'Edit calculated field formula'})
            .addClass('smallicon ui-icon ui-icon-calculator-b btn_add_term')
            .css({'margin-top':'2px',cursor:'pointer'})
            .appendTo( $inputdiv );
            
            this._on( $btn_calcfield,{ click: function(){ 
                window.hWin.HEURIST4.dbs.editCalculatedField( this.options.dtFields['rst_CalcFunctionID'], 
                    function(){
                        //refresh value
                        if(!(that.options.recID>0)) return;

                        const request = {q: 'ids:'+that.options.recID, w: 'all', detail:[that.options.dtID] };

                        window.hWin.HAPI4.RecordSearch.doSearchWithCallback( request, function( recordset )
                            {
                                if ( recordset!=null ){
                                    let val = recordset.fld(recordset.getFirstRecord(), that.options.dtID);
                                    that.setValue(val);
                                    that.options.values = that.getValues();
                                }
                        });
                        
                        
                    } );
            }});
                            
        }
        
        
        this.inputs.push($input);
        
        const dwidth = this.f('rst_DisplayWidth');
        
        if( typeof dwidth==='string' && dwidth.indexOf('%')== dwidth.length-1){ //set in percents
            
            $input.css('width', dwidth);
            
        }else if ( this.detailType=='freetext' || this.detailType=='url' || this.detailType=='blocktext'  
                || this.detailType=='integer' || this.detailType=='float') {  

              //if the size is greater than zero
              let nw = (this.detailType=='integer' || this.detailType=='float')?40:120;
              if (parseFloat( dwidth ) > 0){ 
                  nw = Math.round( 3+Number(dwidth) );
                   
              }
              $input.css({'min-width':nw+'ex','width':nw+'ex'}); //was *4/3

        }
        
        if(this.options.is_faceted_search){
            
            if(this.options.is_between_mode && 
                (this.detailType=='freetext' || this.detailType=='date'
                || this.detailType=='integer' || this.detailType=='float')){

                    
                this.addSecondInput( $input.attr('id') );
                    
            }else {
                this.options.is_between_mode = false;
                if(this.detailType!='date') 
                        $input.css({'max-width':'33ex','min-width':'33ex'});
            }
        }
        
        //name="type:1[bd:138]"
        
        //clear button
        if(this.options.showclear_button && this.options.dtID!='rec_URL')
        {
            if(!(this.detailType == 'enum' && this.inputs.length > 1 && this.enum_buttons == 'checkbox')){

                let $btn_clear = $('<span>')
                .addClass("smallbutton ui-icon ui-icon-circlesmall-close btn_input_clear show-onhover")//   ui-icon
                .attr('tabindex', '-1')
                .attr('title', 'Clear entered value')
                .attr('data-input-id', $input.attr('id'))
                .appendTo( $inputdiv )
               
                .css({'margin-top': '3px', position: 'absolute',
                     cursor:'pointer',             //'font-size':'2em',
    //outline_suppress does not work - so list all these props here explicitely                
                        outline: 'none','outline-style':'none', 'box-shadow':'none',  'border-color':'transparent'
                });
    			
                if($inputdiv.find('#btn_clear_container').length > 0){ // Check if button needs to be placed within a container, or appended to input
                    $inputdiv.find('#btn_clear_container').replaceWith( $btn_clear );
                }			
                
                // bind click events
                this._on( $btn_clear, {
                    click: function(e){
                                                
                        if(that.is_disabled) return;
                        
                        //if empty
                        if(that.getValues()[0] == '') { 

                            let delete_images = that.configMode && that.configMode.entity == 'defTerms' && that.input_img && // only for defTerms for now
                                                    !window.hWin.HEURIST4.util.isempty(that.input_img.find('img').attr('src'));
                            if($(that.inputs[0]).fileupload('instance') !== undefined && delete_images){

                                // Check there is an image to delete
                                window.hWin.HAPI4.checkImage(that.configMode.entity, that.options.recID, 'thumb', function(response){
                                    if(response.data=='ok'){
                                        that.newvalues[$input.attr('id')] = 'delete'; // tell php script to delete image files
                                        that.input_img.find('img').attr('src', ''); // remove from field input
        
                                        that.onChange(); // trigger modified flag
                                    }
                                });
                            }

                            return;
                        }

                        let input_id = $(e.target).attr('data-input-id');  //parent(). need if button
                        
    					if (this.isFileForRecord) /* Need to hide the player and image containers, and the download link for images */
                        {
                            let $parentNode = $(e.target.parentNode);
                            $parentNode.find('.thumb_image').hide();
                            $parentNode.find('.fullSize').hide();
                            $parentNode.find('.download_link').hide();
                            $parentNode.find('#player'+value.ulf_ID).hide();
                            
                            $parentNode.find(".smallText").text("Click image to freeze in place").css({
                                "font-size": "smaller", 
                                "color": "grey", 
                                "position": "", 
                                "bottom": ""
                            });						
    						
                        }
    					
                        if(that.detailType=="resource" && that.configMode.entity=='records' 
                                && that.f('rst_CreateChildIfRecPtr')==1){
                            that._clearChildRecordPointer( input_id );
                        }else{
                            that._removeInput( input_id );
                        }
                        
                        
                        that.onChange(); 
                    }
                });
            }
        }
        
        // add visible icon for dragging/sorting field values
        if(this.is_sortable && !that.isReadonly() && !this.is_disabled 
            && (this.detailType!="relmarker")
            && !this.enum_buttons && this.f('rst_MultiLang')!=1){

            let $btn_sort = $('<span>')
                .addClass('ui-icon ui-icon-arrow-2-n-s btn_input_move smallicon')
                .attr('title', 'Drag to re-arrange values')
                .css('display', 'none');

            if($inputdiv.find('.btn_input_clear').length > 0){
                $btn_sort.insertBefore($inputdiv.find('.btn_input_clear'));
            }else{
                $btn_sort.appendTo($inputdiv);
            }

            this._on($inputdiv, {
                'mouseenter': function(){
                    if(that.is_disabled) return;
                    if($inputdiv.parent().find('.input-div').length > 1){
                        $inputdiv.find('.btn_input_move').css('display', 'inline-block');
                    }
                },
                'mouseleave': function(){
                    if(that.is_disabled) return;
                    $inputdiv.find('.btn_input_move').css('display', 'none');
                }
            });
        }
        
        //adds individual field visibility button
        let btn_field_visibility = $( '<span>', {title: 'Show/hide value from public'})
                    .addClass('field-visibility smallicon ui-icon ui-icon-eye-open')
                    .attr('data-input-id', $input.attr('id'))
                    .css({
                        'margin-top': '3px',
                        'cursor': 'pointer',
                        'vertical-align': 'top'
                    });


        if($inputdiv.find('.btn_input_clear').length > 0){
           btn_field_visibility.insertBefore($inputdiv.find('.btn_input_clear'));
        }else{
           btn_field_visibility.insertAfter( $input );
        }
        btn_field_visibility.hide();
                    
                    
        let chbox_field_visibility = $( '<div><span class="smallicon ui-icon ui-icon-check-off" style="font-size:1em"></span> '
                    +'Hide this value from public<div>', 
                    {title: 'Per record visibility'})
                    .addClass('field-visibility2 graytext')
                    .attr('data-input-id', $input.attr('id'))
                    .css({
                        'margin-top': '2px',
                        'cursor': 'pointer',
                        'font-size': '10px',
                        'font-style': 'italic',
                        'padding-left': '10px'
                    })
                    .appendTo( $inputdiv )
                    .hide();   //$inputdiv.find('.smallicon:first')

        this._on(chbox_field_visibility, {
            'click': function(e){
                if(that.is_disabled) return;

                let chbox = $(e.target);
                if(chbox.is('span')) chbox = chbox.parent();
                
                let btn = this.element.find('span.field-visibility[data-input-id="'+chbox.attr('data-input-id')+'"]');
                
                btn.trigger('click');
            }});
                    
                    
        this._on(btn_field_visibility, {
            'click': function(e){

                let vis_mode = this.f('rst_NonOwnerVisibility');

                if(that.is_disabled || vis_mode == 'viewable' || vis_mode == 'hidden') return;
                
                let btn = $(e.target);
                
                if(btn.attr('hide_field')=='1'){
                    btn.attr('hide_field',0);
                }else{
                    btn.attr('hide_field',1);
                }
                
                this._setVisibilityStatus(btn.attr('data-input-id'));
                
                this.isChanged(true);
                this.onChange();
            }
        });


        //move term error message to last 
        let trm_err = $inputdiv.find('.term-error-message');
        if(trm_err.length>0){
           trm_err.appendTo($inputdiv);
        }

        return $input.attr('id');

    }, //addInput
    
    //
    //
    //
    _setVisibilityStatus: function(input_id){

        let vis_mode = this.f('rst_NonOwnerVisibility');

        if(this.options.showedit_button && this.detailType!="relmarker" &&
           (this.options.recordset && this.options.recordset.entityName == 'Records') && 
           (!window.hWin.HEURIST4.util.isempty(vis_mode)))
        {
        
            let that = this;
            let vis_btns = this.element.find('span.field-visibility'+
                    (input_id?'[data-input-id="'+input_id+'"]':'')); 
            
            $.each(vis_btns, function(idx, btn){

                btn = $(btn);
                let chbox = that.element.find('div.field-visibility2[data-input-id="'+btn.attr('data-input-id')+'"]');
                let $input_div =  btn.parent('.input-div');

                let $first_icon = $input_div.find('.show-onhover:first');
                if($first_icon.length == 1 && !$first_icon.hasClass('field-visibility')){ // make eye the first icon
                    $first_icon.before(btn);
                    if($input_div.find('.vis_text_help').length > 0){
                        $first_icon.before($input_div.find('.vis_text_help'));
                    }
                }
                
                if(btn.attr('hide_field')=='1' || vis_mode == 'viewable' || vis_mode == 'hidden'){

                    if($input_div.find('.link-div').length > 0){
                        $input_div.find('.link-div').css('background-color', ''); // remove existing property (it's set to important)
                    }

                    $input_div.find('.text, .sel_link2, .link-div, .ui-selectmenu-button').addClass('grayed');
                    btn.removeClass('ui-icon-eye-open');            
                    btn.addClass('ui-icon-eye-crossed');
                    btn.attr('title', 'This value is not visible to the public');
                    

                    if(vis_mode=='public' || vis_mode == 'viewable' || vis_mode == 'hidden'){ 

                        btn.removeClass('show-onhover'); //show always for invisible field   
                        btn.css('display','inline-block');

                        if(vis_mode != 'public'){ // change rollover for eye icon

                            const mini_text = vis_mode == 'viewable' ? 'logged-in only' : 'owner only';
                            const vis_title = vis_mode == 'viewable' ? 'This value is only visible to logged-in users' : 'This value is only visible to the owner/owner group';

                            btn.attr('title', vis_title);

                            if($input_div.find('.vis_text_help').length == 0){
                                let $vis_text = $('<span>', {title: vis_title, class: 'vis_text_help', style: 'vertical-align: 3px; padding-left: 5px; font-size: 10px; color: #999;'})
                                                .text(mini_text)
                                                .appendTo($input_div); //insertAfter(btn) - inserts multiple instances
                                $vis_text.before(btn);
                            }
                        }
                    }else{
                        chbox.find('span.ui-icon').removeClass('ui-icon-check-off').addClass('ui-icon-check-on');
                    }
                }else{

                    if($input_div.find('.link-div').length > 0){
                        $input_div.find('.link-div')[0].style.setProperty('background-color', '#F4F2F4', ' !important');
                    }

                    $input_div.find('.text, .sel_link2, .link-div, .ui-selectmenu-button').removeClass('grayed');
                    btn.removeClass('ui-icon-eye-crossed');            
                    btn.addClass('ui-icon-eye-open');
                    btn.attr('title', 'Show/hide value from public');
                    chbox.find('span.ui-icon').removeClass('ui-icon-check-on').addClass('ui-icon-check-off');

                    if(vis_mode=='public'){
                        btn.css('display','');    
                        btn.addClass('show-onhover');
                    }
                }

                if(vis_mode=='public' || vis_mode == 'viewable' || vis_mode == 'hidden'){
                    chbox.hide();
                }else{
                    //pending
                    chbox.show();
                    btn.removeClass('show-onhover'); //show always for pending
                    btn.css('display','inline-block');                        
                }

            });//each
        
        }else{
            //hide for all exept public status
            this.element.find('span.field-visibility').hide();
            this.element.find('div.field-visibility2').hide();
        }  
    },

    //
    // Link to image fields together, to perform actions (e.g. add, change, remove) on both fields, mostly for icon and thumbnail fields
    //
    linkIconThumbnailFields: function($img_container, $img_input){
        this.linkedImgContainer = $img_container;
        this.linkedImgInput = $img_input;
    },

    //
    //
    //
    openIconLibrary: function(){                                 
        
        if(!(this.detailType=='file' && this.configMode.use_assets)) return;
        
        let that = this;
        
        this.select_imagelib_dlg.selectFile({
                source: 'assets'+(that.options.dtID=='rty_Icon'?'16':''), 
                extensions: 'png,svg',
                //size: 64, default value
                onselect:function(res){
            if(res){
                that.input_img.find('img').prop('src', res.url);
                that.newvalues[$(that.inputs[0]).attr('id')] = res.path;  //$input
                that.onChange(); 
                
                
                //HARDCODED!!!! sync icon or thumb to defRecTypes
                if(res.path.indexOf('setup/iconLibrary/')>0){
                    //sync paired value
                    let tosync = '', repl, toval;
                    if(that.options.dtID=='rty_Thumb'){ tosync = 'rty_Icon'; repl='64'; toval='16';}
                    else if(that.options.dtID=='rty_Icon'){tosync = 'rty_Thumb'; repl='16'; toval='64';}
               
                    if(tosync!=''){
                        
                        let ele = that.options.editing.getFieldByName(tosync);
                        if(ele){
                            let s_path = res.path;
                            let s_url  = res.url;
                            if(s_path.indexOf('icons8-')>0){
                                s_path = s_path.replace('-'+repl+'.png','-'+toval+'.png')
                                s_url = s_url.replace('-'+repl+'.png','-'+toval+'.png')
                            }
                            
                            let s_path2 = s_path.replace(repl,toval)
                            let s_url2 = s_url.replace(repl,toval)
                            
                            if(that.linkedImgContainer !== null && that.linkedImgInput !== null)
                            {
                                if(ele){
                                    ele.editing_input('setValue', s_path2 );
                                    ele.hide();
                                } 
                                
                                that.linkedImgInput.val( s_path2 );
                                that.linkedImgContainer.find('img').prop('src', s_url2 );
                            }else if(ele && ele.find('.image_input').length > 0){// elements in correct location
                                ele.find('.image_input').find('img').prop('src', s_url2); 
                            }                                

                        }
                    }
                }
                
            }
        }, assets:that.configMode.use_assets, size:that.configMode.size});
    },
    
    //
    //
    //
    _clearChildRecordPointer: function( input_id ){
        
            let that = this;
        
            let popele = that.element.find('.child_delete_dlg');
            if(popele.length==0){
                let sdiv = '<div class="child_delete_dlg">'
                +'<div style="padding:15px 0">You are deleting a pointer to a child record, that is a record which is owned by/an integral part of the current record, as identified by a pointer back from the child to the current record.</div>'
                //Actions:<br>
                +'<div><label><input type="radio" value="1" name="delete_mode" style="outline:none"/>'
                            +'Delete connection between parent and child</label><br><br>'
                        +'<label><input type="radio" value="2" name="delete_mode" checked="checked" style="outline:none"/>'
                            +'Delete the child record completely</label></div>'
                +'<div style="padding:15px 0">Warning: If you delete the connection between the parent and child, this will often render the child record useless as it may lack identifying information.</div></div>';
                
//<label><input type="radio" value="0" name="delete_mode"/>Leave child record as-is</label><br>
//<p style="padding:0 0 15px 0">If you leave the child record as-is, it will remain as a child of the current record and retain a pointer allowing the parent record information to be used in the child\'s record title, custom reports etc.</p>                
                popele = $(sdiv).appendTo(that.element);
            }
            
            let $dlg_pce = null;
            
            let btns = [
                    {text:window.hWin.HR('Proceed'),
                          click: function() { 
                          
                          let mode = popele.find('input[name="delete_mode"]:checked').val();     
                          if(mode==2){
                              //remove child record
                              let child_rec_to_delete = that.newvalues[input_id];
                              window.hWin.HAPI4.RecordMgr.remove({ids: child_rec_to_delete}, 
                                function(response){
                                    if(response.status == window.hWin.ResponseStatus.OK){
                                        
                                        let delcnt = response.data.deleted.length, msg = '';
                                        if(delcnt>1){
                                            msg = delcnt + ' records have been removed.';
                                            if(response.data.bkmk_count>0 || response.data.rels_count>0){
                                               msg = ' as well as '+
                                                (response.data.bkmk_count>0?(response.data.bkmk_count+' bookmarks'):'')+' '+
                                                (response.data.rels_count>0?(response.data.rels_count+' relationships'):'');
                                            }
                                        }else{
                                            msg = 'Child record has been removed';
                                        }
                                        window.hWin.HEURIST4.msg.showMsgFlash(msg, 2500);
                                        
                                        that._removeInput( input_id );
                                    }
                                });
                          } else {
                              that._removeInput( input_id );
                          }
                          
                          $dlg_pce.dialog('close'); 
                    }},
                    {text:window.hWin.HR('Cancel'),
                          click: function() { $dlg_pce.dialog('close'); }}
            ];            
            
            $dlg_pce = window.hWin.HEURIST4.msg.showElementAsDialog({
                window:  window.hWin, //opener is top most heurist window
                title: window.hWin.HR('Child record pointer removal'),
                width: 500,
                height: 300,
                element:  popele[0],
                resizable: false,
                buttons: btns
            });
        
    },

    //
    // assign title of resource record or file name or related entity
    //
    _findAndAssignTitle: function(ele, value, selector_function){
        
        let that = this;
        
        if(this.isFileForRecord){   //FILE FOR RECORD
            
            if(!value){   //empty value
                window.hWin.HEURIST4.ui.setValueAndWidth(ele, '');
                return;
            }

            if($.isPlainObject(value) && value.ulf_ObfuscatedFileID){

                let rec_Title = value.ulf_ExternalFileReference;
                if(window.hWin.HEURIST4.util.isempty(rec_Title)){
                    rec_Title = value.ulf_OrigFileName;
                }
                window.hWin.HEURIST4.ui.setValueAndWidth(ele, rec_Title, 10);

                //url for thumb
                if(!window.hWin.HEURIST4.util.isempty(value['ulf_ExternalFileReference']) && value.ulf_MimeExt == 'youtube'){ // retrieve youtube thumbnail

                    let youtube_id = window.hWin.HEURIST4.util.get_youtube_id(value.ulf_ExternalFileReference);

                    if(youtube_id){

                        ele.parent().find('.image_input > img').attr('src', 'https://img.youtube.com/vi/'+ youtube_id +'/default.jpg');
                        ele.parent().find('.smallText').text("Click image to freeze in place").css({
                            "font-size": "smaller", 
                            "color": "grey", 
                            "position": "", 
                            "top": ""
                        })
                        .removeClass('invalidImg');

                        that.newvalues[ele.attr('id')] = value;
                    }else{

                        ele.parent().find('.image_input > img').removeAttr('src');
                        ele.parent().find('.smallText').text("Unable to retrieve youtube thumbnail").css({
                            "font-size": "larger", 
                            "color": "black", 
                            "position": "relative", 
                            "top": "60px"
                        })
                        .addClass('invalidImg');

                        ele.parent().find('.hideTumbnail').trigger('click');
                    }

                    ele.trigger('change');
                }else{ // check if image that can be rendered

                    window.hWin.HAPI4.checkImage("Records", value["ulf_ObfuscatedFileID"], null, function(response) {

                        if(response.data && response.status == window.hWin.ResponseStatus.OK) {
                            
                            ele.attr('data-mimetype', response.data.mimetype);
                            
                            const isMiradorManifest = response.data.original_name=='_iiif';
                            
                            if ((response.data.mimetype && response.data.mimetype.indexOf('image/')===0)
                                || isMiradorManifest)
                            {
                                ele.parent().find('.image_input > img').attr('src',
								    window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database + '&thumb='+
									    value.ulf_ObfuscatedFileID);
                                        
                                if((response.data.width > 0 && response.data.height > 0) || isMiradorManifest) {

                                    ele.parent().find('.smallText').text('Click image to freeze in place').css({
                                        "font-size": "smaller", 
                                        "color": "grey", 
                                        "position": "", 
                                        "top": ""
                                    })
                                    .removeClass('invalidImg');

                                    that.newvalues[ele.attr('id')] = value;
                                }else{

                                    ele.parent().find('.image_input > img').removeAttr('src');
                                    ele.parent().find(".smallText").text("This file cannot be rendered").css({
                                        "font-size": "larger", 
                                        "color": "black", 
                                        "position": "relative", 
                                        "top": "60px"
                                    })
                                    .addClass('invalidImg');

                                    ele.parent().find('.hideTumbnail').trigger('click');
                                    ele.parent().find('.hideTumbnail').hide();
                                }
                                
                            }else{
                                ele.parent().find('.image_input').hide();
                                ele.parent().find('.hideTumbnail').hide();
                            }
                            
                            let mirador_link = ele.parent().find('.miradorViewer_link');
                            let mimetype = response.data.mimetype;
                            if(response.data.original_name.indexOf('_iiif')===0){
                                
                                if(isMiradorManifest){
                                    mirador_link.attr('data-manifest', '1');    
                                }
                                
                                mirador_link.show();
                                ele.parent().find('div.download_link').show();                                
                            }else
                            if(mimetype.indexOf('image/')===0 || (
                                    (mimetype.indexOf('video/')===0 || mimetype.indexOf('audio/')===0) &&
                                 ( mimetype.indexOf('youtube')<0 && 
                                   mimetype.indexOf('vimeo')<0 && 
                                   mimetype.indexOf('soundcloud')<0)) ){
                                   
                                mirador_link.show();
                                ele.parent().find('div.download_link').show();                                
                            }else{
                                mirador_link.hide();           
                            }
                            
                            
                            ele.trigger('change');
                        }
                    });
                }
            }else{
                 //call server for file details
                 let recid = ($.isPlainObject(value))?value.ulf_ID :value;
                 if(recid>0){
                     
                     let request = {};
                        request['recID']  = recid;
                        request['a']          = 'search'; //action
                        request['details']    = 'list';
                        request['entity']     = 'recUploadedFiles';
                        request['request_id'] = window.hWin.HEURIST4.util.random();
                        
                        window.hWin.HAPI4.EntityMgr.doRequest(request,
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){

                                    let recordset = new HRecordSet(response.data);
                                    let record = recordset.getFirstRecord();

                                    if(record){
                                        let newvalue = {ulf_ID: recordset.fld(record,'ulf_ID'),
                                                    ulf_ExternalFileReference: recordset.fld(record,'ulf_ExternalFileReference'),
                                                    ulf_OrigFileName: recordset.fld(record,'ulf_OrigFileName'),
                                                    ulf_ObfuscatedFileID: recordset.fld(record,'ulf_ObfuscatedFileID')};

                                        that.newvalues[ele.attr('id')] = newvalue;
                                        that._findAndAssignTitle(ele, newvalue, selector_function);
                                    }
                                }
                            });
                 }
            }
                    
        }else if(this.detailType=='file'){  // FILE FOR OTHER ENTITIES - @todo test
            
            window.hWin.HEURIST4.ui.setValueAndWidth(ele, value, 10);
            
        }else if(this.configMode.entity==='records'){     //RECORD
        
                let isChildRecord = that.f('rst_CreateChildIfRecPtr');
        
                //assign initial display value
                if(Number(value)>0){
                    let sTitle = null;
                    if(that.options.recordset){
                        let relations = that.options.recordset.getRelations();
                        if(relations && relations.headers && relations.headers[value]){
                            
                            sTitle = relations.headers[value][0];
                            
                            ele.empty();
                            window.hWin.HEURIST4.ui.createRecordLinkInfo(ele, 
                                            {rec_ID: value, 
                                             rec_Title: relations.headers[value][0], 
                                             rec_RecTypeID: relations.headers[value][1],
                                             rec_IsChildRecord: isChildRecord,
                                             rec_OwnerUGrpID: relations.headers[value][2],
                                             rec_NonOwnerVisibility: relations.headers[value][3]
                                             },
                                             selector_function);
                                             
                            
                        }
                    }
                    if(!sTitle){
                        window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+value, w: "e", f:"header"},  //search for temp also
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    ele.empty();

                                    let recordset = new HRecordSet(response.data);
                                    if(recordset.length()>0){
                                        let record = recordset.getFirstRecord();
                                        let rec_Title = recordset.fld(record,'rec_Title');
                                        if(!rec_Title) {rec_Title = 'New record. Title is not defined yet.';}
                                        
                                        let rec_RecType = recordset.fld(record,'rec_RecTypeID');
                                        window.hWin.HEURIST4.ui.createRecordLinkInfo(ele, 
                                                {rec_ID: value, 
                                                 rec_Title: rec_Title, 
                                                 rec_RecTypeID: rec_RecType,
                                                 rec_IsChildRecord: isChildRecord
                                                 }, selector_function);
                                                 
                                       ele.show();
                                       ele.parent().find('.sel_link').show();
                                       ele.parent().find('.sel_link2').hide(); //hide big button to select new link
                                                 
                                    }else{
                                       
                                        window.hWin.HEURIST4.ui.createRecordLinkInfo(ele, 
                                                {rec_ID: value, 
                                                 rec_Title: 'Target record '+value+' does not exist', 
                                                 rec_RecTypeID: 0,
                                                 rec_IsChildRecord: isChildRecord
                                                 }, selector_function);
                                        ele.show();
                                        ele.parent().find('.sel_link2').hide(); //hide big button to select new link
                                    }
                                    
                                }
                            }
                        );
                    }
                    
                    
                }else{
                    window.hWin.HEURIST4.ui.setValueAndWidth(ele, '');
                }
                
                //hide this button if there are links
                if( ele.parent().find('.link-div').length>0 ){ 
                    ele.show();
                    ele.parent().find('.sel_link2').hide();
                }else{
                    ele.hide();
                    ele.parent().find('.sel_link2').show();
                }
                    
                
        }else{    
            //related entity                 
            if(window.hWin.HEURIST4.util.isempty(value)) value = [];
            value = Array.isArray(value)?value
                :((typeof  value==='string')?value.split(','):[value]);
                
            if(value.length==0){
                ele.empty();
                ele.hide();
                ele.parent().find('.sel_link').hide();
                ele.parent().find('.sel_link2').show();
                
            }else{
                window.hWin.HAPI4.EntityMgr.getTitlesByIds(this.configMode.entity, value,
                   function( display_value ){
                       ele.empty();
                       let hasValues = false;
                       if(display_value && display_value.length>0){
                           for(let i=0; i<display_value.length; i++){
                               if(display_value[i]){
                                    $('<div class="link-div">'+display_value[i]+'</div>').appendTo(ele);     
                                    hasValues = true;
                               }
                           }
                       }
                       if(hasValues){
                           ele.show();
                           ele.parent().find('.sel_link').show();
                           ele.parent().find('.sel_link2').hide();
                       }else{
                           ele.hide();
                           ele.parent().find('.sel_link').hide();
                           ele.parent().find('.sel_link2').show();
                       }
                       
                   });
            }
        }
        
    },
    
    //
    //
    //
    _onTermChange: function( orig, data ){
        
        let $input = (orig.target)? $(orig.target): orig;
                
                if(! $input.attr('radiogroup')){
                
                    if($input.hSelect("instance")!=undefined){
                        
                        let opt = $input.find('option[value="'+$input.val()+'"]');
                        let parentTerms = opt.attr('parents');
                        if(parentTerms){
                            $input.hSelect("widget").find('.ui-selectmenu-text').html( parentTerms+'.'+opt.text() );    
                        }    
                           
                    }else{
                        //restore plain text value               
                        $input.find('option[term-view]').each(function(idx,opt){
                            $(opt).text($(opt).attr('term-view'));
                        });
                        
                        //assign for selected term value in format: parent.child 
                        let opt = $input.find( "option:selected" );
                        let parentTerms = opt.attr('parents');
                        if(parentTerms){
                             opt.text(parentTerms+'.'+opt.attr('term-orig'));
                        }
                    }
                }

                //hide individual error                
                $input.parent().find('.term-error-message').hide();

                this.onChange();
    },
    
    //
    // Open defTerms manager
    //
    _openManageTerms: function( vocab_id ){
        
        let that = this;
        
        let rg_options = {
            height:800, width:1300,
            selection_on_init: vocab_id,
            innerTitle: false,
            innerCommonHeader: $('<div>'
                +(that.options.dtID>0?('<span style="margin-left:260px">Field: <b>'+$Db.dty(that.options.dtID,'dty_Name')+'</b></span>'):'')
                +'<span style="margin-left:110px">This field uses vocabulary: <b>'+$Db.trm(vocab_id,'trm_Label')+'</b></span></div>'),
            onInitFinished: function(){
                let that2 = this;
                setTimeout(function(){
                    that2.vocabularies_div.manageDefTerms('selectVocabulary', vocab_id);
                },500);
            },
            onClose: function(){
                that._recreateEnumField(vocab_id);
            }
        };
        
        window.hWin.HEURIST4.ui.showEntityDialog('defTerms', rg_options);
    },

    //
    // recreate SELECT for enum/relation type
    //
    _recreateSelector: function($input, value){

        let that = this;

        if(value===true){
            //keep current
            value = ($input)?$input.val():null;
        }

        if($input) $input.empty();

        let allTerms = this.f('rst_FieldConfig');

        if(!window.hWin.HEURIST4.util.isempty(allTerms)){

            if($.isPlainObject(this.configMode))    { //this is not vocabulary ID, this is something more complex

                if(this.configMode.entity){ //this lookup for entity
                    
                    //create and fill SELECT
                    //this.configMode.entity
                    //this.configMode.filter_group

                    let selObj = window.hWin.HEURIST4.ui.createEntitySelector($input.get(0), this.configMode, 'select...', null);
                    window.hWin.HEURIST4.ui.initHSelect(selObj, false); 
                    
                }else{
                    //type: select, radio, checkbox
                    //hideclear   
                    //values                 
                    $input = window.hWin.HEURIST4.ui.createInputSelect($input, allTerms);
                    
                }
                

            }
            else{

                if (!Array.isArray(allTerms) && !window.hWin.HEURIST4.util.isempty(allTerms)) {
                    //is it CS string - convert to array
                    allTerms = allTerms.split(',');
                }

                if(window.hWin.HEURIST4.util.isArrayNotEmpty(allTerms)){
                    if(window.hWin.HEURIST4.util.isnull(allTerms[0]['key'])){
                        //plain array
                        let idx, options = [];
                        for (idx=0; idx<allTerms.length; idx++){
                            options.push({key:allTerms[idx], title:allTerms[idx]});
                        }
                        allTerms = options;
                    }
                    //add empty value as a first option
                   
                    
                    //array of key:title objects
                    let selObj = window.hWin.HEURIST4.ui.createSelector($input.get(0), allTerms);
                    window.hWin.HEURIST4.ui.initHSelect(selObj, this.options.useHtmlSelect);

                    // move menuWidget to current dialog/document 
                    // (sometimes, within CMS pages for example, it places it before the current dialog thus hiding it)
                    let $menu = $input.hSelect('menuWidget');
                    let $parent_ele = this.element.closest('div[role="dialog"]');
                    $parent_ele = $parent_ele.length == 0 ? document : $parent_ele;

                    if($parent_ele.length > 0) $menu.parent().appendTo($parent_ele);
                }
            }
            
            if(!window.hWin.HEURIST4.util.isnull(value)){
                
                if($($input).attr('radiogroup')){
                    $($input).find('input[value="'+value+'"]').attr('checked', true);
                }else {
                    $($input).val(value); 
                }
            }  
            if($($input).hSelect("instance")!=undefined){
                           $($input).hSelect("refresh"); 
            }

        }
        else{ //this is usual enumeration from defTerms
            
            //show error message on init -----------                   
            //ART0921 - todo in browseTerms
            let err_ele = $input.parent().find('.term-error-message');
            if(err_ele.length>0){
                err_ele.remove();
            }
            
            //value is not allowed
            if( !window.hWin.HEURIST4.util.isempty(allTerms) &&
                window.hWin.HEURIST4.util.isNumber(value) && $input.val()!=value){
                
                this.error_message.css({'font-weight': 'bold', color: 'red'});    
                let sMsg = null;
                let name = $Db.trm(value,'trm_Label');
                if(window.hWin.HEURIST4.util.isempty(name)){
                    //missed
                    sMsg = 'The term code '+value+' recorded for this field is not recognised. Please select a term from the dropdown.';
                }else{
                    //exists however in different vocabulary
                    //get name for this vocabulary
                    let vocName = $Db.trm(allTerms,'trm_Label');
                    //get name for term vocabulary
                    let vocId2 = $Db.getTermVocab(value);
                    let vocName2 = $Db.trm(vocId2, 'trm_Label');
                    //check that the same name vocabulary exists in this vocabualry
                    let code2 = $Db.getTermByLabel(allTerms, name);
                    
                    sMsg = '';
                    if(code2>0){
                        
                        sMsg = '<span class="heurist-prompt ui-state-error">'
                            +'This term references a duplicate outside the <i>'
                            +vocName+'</i> vocabulary used by this field. ';
                            
                        if(window.hWin.HAPI4.is_admin()){
                            sMsg = sMsg + '<a href="#" class="term-sel" '
                                +'data-term-replace="'+value+'" data-vocab-correct="'+allTerms
                                +'" data-vocab="'+vocId2+'" data-term="'+code2+'">correct</a></span>';
                        }else{
                            sMsg = sMsg 
                            +'</span><br><span>Either ask database manager to replace term for all records</span>';    
                        }

                    }else{
                        
                        sMsg = '<span class="heurist-prompt ui-state-error">'
                            +'This term is not in the <i>'+vocName+'</i> vocabulary used by this field. '                        
                        
                        if(window.hWin.HAPI4.is_admin()){
                            
                            sMsg = sMsg + '<a href="#" class="term-fix" '
                                +'data-term="'+value+'" data-vocab-correct="'+allTerms
                                +'" data-vocab="'+vocId2+'" data-dty-id="'+this.options.dtID
                                +'">correct</a></span>';
                        }else {
                            sMsg = sMsg + 
                            '.</span><br><span>Ask database manager to correct this vocabulary</span>';    
                        }
                    }
                    
                    let opt = window.hWin.HEURIST4.ui.addoption($input[0], value, '!!! '+name); 
                    $(opt).attr('ui-state-error',1);
                    $input.val(value);
                    $input.hSelect('refresh');
 
                    this.error_message.css({'font-weight': 'normal', color: '#b15f4e'}); 
                }

                if(!window.hWin.HEURIST4.util.isempty(sMsg)){
                    //add error message per every term
                    
                    err_ele = $( "<div>")
                        .addClass('term-error-message')
                        .html(sMsg)
                        .css({'height': 'auto',
                            'width': 'fit-content',
                            'padding': '0.2em',
                            'border': 0,
                            'margin-bottom': '0.2em'})
                        .appendTo( $input.parent() );
                    
                    err_ele.find('.ui-state-error')
                        .css({color:'red', //'#b36b6b',
                              background:'none',
                              border: 'none',
                             'font-weight': 'normal'        
                        });
                    
                
                   
                    

                   
                    if(window.hWin.HAPI4.is_admin()){  

                        //
                        // select term (with the same name) in all fields
                        //
                        this._on(err_ele.find('.term-sel'),{click:function(e){
                            
                            let trm_id = $(e.target).attr('data-term');
                            let trm_id_re = $(e.target).attr('data-term-replace');
                            let fieldName = this.f('rst_DisplayName');
                            
                            let request = {a:'replace', rtyID:this.options.rectypeID,
                                dtyID:this.options.dtID, sVal:trm_id_re, rVal:trm_id, tag:0, recIDs:'ALL'};                
                                
                            window.hWin.HEURIST4.msg.showMsgDlg(
'<div  style="line-height:20px">'
+'<div>Term: <span id="termName" style="font-style:italic">'
    +$Db.trm(trm_id,'trm_Label')+'</span></div>'
+'<div>In vocabulary: <span id="vocabName" style="font-style:italic">'
    +$Db.trm($(e.target).attr('data-vocab'),'trm_Label')+'</span></div>'
+'<hr>'
+'<div>Vocabulary for this field is: <span id="vocabNameCorrect" style="font-style:italic">'
    +$Db.trm($(e.target).attr('data-vocab-correct'),'trm_Label')+'</span></div>'
+'<p>Use the version of the term in this vocabulary for this field in all records of this type</p></div>'
/*                                    'You are about to convert tag #'+trm_id_re+' to #'+trm_id
                                    +' in field "'+fieldName+'" for all records'
                                    + '<br><br>Are you sure?'*/,
                                    function(){
                                        window.hWin.HEURIST4.msg.bringCoverallToFront();                                             
                            
                                        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response){
                                            window.hWin.HEURIST4.msg.sendCoverallToBack();
                                            if(response.status == window.hWin.ResponseStatus.OK){
                                                
                                                $input.val(trm_id);
                                                $input.hSelect('refresh');
                                                $input.trigger('change');
                                                
                                            }else{
                                                $('#div_result').hide();
                                                window.hWin.HEURIST4.msg.showMsgErr(response);
                                            }
                                        });
                                    },
                                    {title:'Correction of invalid term',yes:'Apply',no:'Cancel'});
            
                        }});
                    
                        this._on(err_ele.find('.term-fix'),{click:function(e){
                            //see manageDefTerms.js
                            let trm_ID = $(e.target).attr('data-term');
                            correctionOfInvalidTerm(
                                trm_ID,
                                $(e.target).attr('data-vocab'),
                                $(e.target).attr('data-vocab-correct'),
                                $(e.target).attr('data-dty-id'),
                                function(newvalue){ //callback
                                    window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
                                        { source:this.uuid, type:'trm' });    
                                    if(!(newvalue>0)) newvalue = trm_ID;
                                    that._recreateSelector($input, newvalue); //after correction of invalid term
                                }
                            );
                            
                        }});
                        
                    }//is_admin
                }
                
                
            }    
            //end of error messages ---------------
        }                                                                   
        
        return $input;
    },//_recreateSelector
    
    //
    //
    //
    showErrorMsg: function(sMsg){
        if(!window.hWin.HEURIST4.util.isempty(sMsg)){
            this.error_message.html(sMsg).show();    
        }else{
            this.error_message.hide();
            $(this.inputs).removeClass('ui-state-error');
            
            $(this.element).find('.ui-state-error').each(function(idx,item){
               if(!$(item).hasClass('heurist-prompt')){
                    $(item).removeClass('ui-state-error');    
               }
            });
            
        }
    },

    showValueErrors: function(errors){

        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(errors)){
            return;
        }

        $.each(this.inputs, (idx, ele) => {

            if(idx >= errors.length){
                return false;
            }

            const err = errors[idx];
            if(window.hWin.HEURIST4.util.isempty(err)){
                return;
            }

            ele = $(ele).parents('.input-div');

            $('<div>', {class: `heurist-prompt ui-state-error`, style: 'margin-bottom: 0.2em; padding: 2px; width: fit-content;'})
                .text(err)
                .insertAfter(ele);
        });
    },
    
    //
    // internal - assign display value for specific input element
    //
    _clearValue: function(input_id, value, display_value){

        let that = this;
        $.each(this.inputs, function(idx, item){

            let $input = $(item);
            if($input.attr('id')==input_id){
                if(that.newvalues[input_id]){
                    that.newvalues[input_id] = '';
                }
                $input.removeAttr('data-value');
                
                if(that.detailType=='file'){
                    that.input_cell.find('img.image_input').prop('src','');

                    if(that.linkedImgInput !== null){
                        that.linkedImgInput.val('');
                        that.newvalues[that.linkedImgInput.attr('id')] = '';
                        that.linkedImgInput.removeAttr('data-value');
                    }
                    if(that.linkedImgContainer !== null){
                        that.linkedImgContainer.find('img').prop('src', '');
                    }
                }else if(that.detailType=='resource'){
                    
                    $input.parent().find('.sel_link').hide();
                    $input.parent().find('.sel_link2').show();
                    $input.empty();
                    $input.hide();
                    
                }else if(that.detailType=='relmarker'){    
                    this.element.find('.rel_link').show();
                }else{
                    $input.val( display_value?display_value :value);    

                    if(that.detailType=='enum' || that.detailType=='relationtype'){    
                        //selectmenu
                        if($($input).hSelect("instance")!=undefined){
                           $($input).hSelect("refresh"); 
                        }

                        if(that.enum_buttons != null){
                            $input.parent().find('input:'+that.enum_buttons).prop('checked', false);
                        }
                    }
                }
                if(that.detailType=='date' || that.detailType=='file'){
                    $input.trigger('change');
                }else{
                    that.onChange();
                }
                return;
            }

        });

    },

    //
    // recreate input elements and assign values
    //
    setValue: function(values, make_as_nochanged){
        //clear ALL previous inputs
        this.input_cell.find('.input-div').remove();
        this.inputs = [];
        this.newvalues = {};
        
        if(!Array.isArray(values)) values = [values];

        let isReadOnly = this.isReadonly();
        
        let i;
        for (i=0; i<values.length; i++){
            if(isReadOnly && this.detailType!='relmarker'){
                this._addReadOnlyContent(values[i]);
            }else{
                let inpt_id = this._addInput(values[i]);
            }
        }
        if (isReadOnly || (make_as_nochanged==true)) {
            this.options.values = values;
        }

        let repeatable = (Number(this.f('rst_MaxValues')) != 1);
        if(values.length>1 && !repeatable && this.f('rst_MultiLang')!=1){
            this.showErrorMsg('Repeated value for a single value field - please correct');
        }else{
           
        }
        
        this._setAutoWidth();            
        
        if(window.hWin.HEURIST4.util.isFunction(this.options.onrecreate)){
            this.options.onrecreate.call(this);
        }
        
        /*
        if(make_as_nochanged){
            this._setAutoWidth();            
        }else{
            this.onChange();
        }
        */
    },
    
    //
    // get value for particular input element
    //  input_id - id or element itself
    //
    _getValue: function(input_id){

        if(this.detailType=="relmarker") return null;
        
        let res = null;
        let $input = $(input_id);

        if(!(this.detailType=="resource" || this.detailType=='file' 
            || this.detailType=='date' || this.detailType=='geo'))
        {
            if($input.attr('radiogroup')>0){
                res = $input.find('input:checked').val();
            }else if(this.detailType=='boolean'){
                if(Array.isArray(this.configMode) && this.configMode.length==2) {
                    res = this.configMode[ $input.is(':checked')?0:1 ];
                }else{
                    res = $input.is(':checked') ?$input.val() :0;        
                }       
                
            }else{
                res = $input.val();
            }
            
            if(!window.hWin.HEURIST4.util.isnull(res) && res!=''){
                res = res.trim();

                // strip double spacing from freetext fields
                res = this.detailType == 'freetext' ? res.replaceAll(/  +/g, ' ') : res;
            }
        }else {
            res = this.newvalues[$input.attr('id')];    
            if(!res && $input.attr('data-value')){
                res = $input.attr('data-value');
            }
        }

        return res;
    },

    
    //
    //
    //
    getConfigMode: function(){
        return this.configMode;
    },

    setConfigMode: function(newval){
        return this.configMode = newval;
    },
    
    //
    //restore original order of repeatable elements
    //    
    _restoreOrder: function(){
        
        this.btn_cancel_reorder.hide();
        
        if(this.isReadonly()) return;
        let idx, ele_after = this.firstdiv;
        for (idx in this.inputs) {
            let ele = this.inputs[idx].parents('.input-div');
            ele.insertAfter(ele_after);
            ele_after = ele;
        }    
    },
    
    //
    // returns individual visibilities (order is respected)
    //
    getVisibilities: function(){
        
        let ress2 = [];
        let visibility_mode = this.f('rst_NonOwnerVisibility');
        if(visibility_mode=='public' || visibility_mode=='pending')
        {
            let ress = {};
            
            for (let idx in this.inputs) {
                let $input = this.inputs[idx];
                
                let val = this._getValue($input);
                if(!window.hWin.HEURIST4.util.isempty( val )){                 
                
                    let res = 0;
                    
                    let ele = this.element.find('span.field-visibility[data-input-id="'+$input.attr('id')+'"]');
                    res = (ele.attr('hide_field')=='1')?1:0; //1: hide this field from public
                                        
                    ele = $input.parents('.input-div');
                    let k = ele.index();
                    ress[k] = res;
                }
            }
            
            ress2 = [];
            for(let idx in ress){
                ress2.push(ress[idx]);
            }
        }
        
        return ress2;  
    },

    //
    // applies visibility status 
    //
    setVisibilities: function(vals){
        
        let vis_mode = this.f('rst_NonOwnerVisibility');
        
        if(this.options.showedit_button && this.detailType!="relmarker" && 
            !window.hWin.HEURIST4.util.isempty(vis_mode))
        {
            let idx, k=0;
            
            for (idx in this.inputs) {

                let $input = this.inputs[idx];
                let btn = this.element.find('span.field-visibility[data-input-id="'+$input.attr('id')+'"]');
                
                if(vals && k<vals.length && vals[k]==1){
                    btn.attr('hide_field',1);

                    this._setHiddenField($input, this.is_disabled);
                }else{
                    btn.attr('hide_field',0);
                }
                k++;
            }

            this._setVisibilityStatus();

        }else{
            this.element.find('span.field-visibility').hide();
            this.element.find('div.field-visibility2').hide();
        }
    },
    
    //
    //
    //
    getDetailType: function(){
        return this.detailType;
    },
    
    //
    //
    //
    isReadonly: function(){
        return this.options.readonly || this.f('rst_Display')=='readonly' || this.f('rst_MayModify')=='locked';
    },
    
    //
    // get all values (order is respected)
    //
    getValues: function( ){

        if(this.isReadonly()){
            return this.options.values;
        }else{
            let idx;
            let ress = {};
            let ress2 = [];
            
            for (idx in this.inputs) {
                let $input = this.inputs[idx];
                
                let res = this._getValue($input);


                if(!window.hWin.HEURIST4.util.isempty( res )){ 

                    if(this.options.is_between_mode){
                        let res2;
                        if(this.detailType=='date'){
                            res2 = this.newvalues[$input.attr('id')+'-2'];    
                        }else{
                            res2 = this.element.find('#'+$input.attr('id')+'-2').val();
                        }
                        if(window.hWin.HEURIST4.util.isempty( res2 )){ 
                            if(this.detailType!='date') res = '';
                        }else{
                            if(this.detailType=='date'){
                                res  = res+'/'+res2;
                            }else{
                                res  = res+'<>'+res2;   
                            }
                        }
                    }
                                    
                    let ele = $input.parents('.input-div');
                    let k = ele.index();
                    
                    ress[k] = res;
                   
                }
            }
            
            ress2 = [];
            for(idx in ress){
                ress2.push(ress[idx]);
            }
            if(ress2.length==0) ress2 = [''];//at least one empty value

            return ress2;
        }

    },
    
    _setHiddenField($input, is_hidden){
     
        if(is_hidden){
            $input.addClass('input-with-invisible-text');   
            if($input.is('select')){
                $input.nextAll('.ui-selectmenu-button').addClass('input-with-invisible-text');
            }
        }else{
            $input.removeClass('input-with-invisible-text');       
            if($input.is('select')){
                $input.nextAll('.ui-selectmenu-button').removeClass('input-with-invisible-text');
            }
        }
        
    },

    
    //
    //
    //
    setDisabled: function(is_disabled){
       
        if(!this.isReadonly()){
            
            let check_ind_visibility = this.options.showedit_button 
                    && this.detailType!="relmarker"
                    && !window.hWin.HEURIST4.util.isempty(this.f('rst_NonOwnerVisibility'));
            
            let idx;
            for (idx in this.inputs) {
                if(!this.isFileForRecord) {  //this.detailType=='file'
                    let input_id = this.inputs[idx];
                    let $input = $(input_id);
                    window.hWin.HEURIST4.util.setDisabled($input, is_disabled);
                    
                    if(check_ind_visibility){
                        let btn = this.element.find('span.field-visibility[data-input-id="'+$input.attr('id')+'"]');

                        this._setHiddenField($input, (is_disabled && btn.attr('hide_field')==1));
                    }
                }
            }
            this.is_disabled = is_disabled;
            
            if(this.input_cell.sortable('instance')){
               this.input_cell.sortable('option', 'disabled', is_disabled );
            }
        }

    },
    
    //
    //
    //
    isChanged: function(value){

        if(value===true){
            this.options.values = [''];
            
            return true;
        }else{

            if(this.isReadonly()){
                return false;
            }else{
                if(this.options.values.length!=this.inputs.length){
                    return true;
                }
                
                let idx;
                for (idx in this.inputs) {
                    let res = this._getValue(this.inputs[idx]);
                    //both original and current values are not empty
                    if (!(window.hWin.HEURIST4.util.isempty(this.options.values[idx]) && window.hWin.HEURIST4.util.isempty(res))){
                        if (this.options.values[idx]!=res){
                            return true;
                        }
                    }
                }
            }

            return false;
        }
    },

    //
    //   Restore values
    //    
    setUnchanged: function(){
        
        if(this.isReadonly()) return;
        
        this.options.values = [];
                
        let idx;
        for (idx in this.inputs) {
            this.options.values.push(this._getValue(this.inputs[idx]));
        }
    },
    
    //
    // returns array of input elements
    //
    getInputs: function(){
        return this.inputs;
    },

    //
    //
    //
    validate: function(){

        if (this.f('rst_Display')=='hidden' || this.isReadonly()) return true;
        
        let req_type = this.f('rst_RequirementType');
        let max_length = this.f('dty_Size');
        let data_type = this.f('dty_Type');
        let errorMessage = '';

        if(req_type=='required'){
            
            if(data_type=='relmarker'){
                    if(this.element.find('.link-div').length==0){
                        $(this.inputs[0]).addClass( "ui-state-error" );
                        //add error message
                        errorMessage = 'Define a relationship. It is required.';
                    }
            }else{
                let ress = this.getValues();

                if(ress.length==0 || window.hWin.HEURIST4.util.isempty(ress[0]) || 
                    ($.isPlainObject(ress[0]) &&  $.isEmptyObject(ress[0])) || 
                    ( (typeof ress[0] ==='string') && ress[0].trim()=='')) {
                    
                    
                    if( data_type=='file' && !this.isFileForRecord && this.entity_image_already_uploaded){
                        //special case for entity image
                        
                    }else{
                    
                        //error highlight
                        $(this.inputs[0]).addClass( "ui-state-error" );
                        //add error message
                        errorMessage = 'Field is required';
                    }

                }else if((data_type=='freetext' || data_type=='url' || data_type=='blocktext') && ress[0].length<4){
                   
                }
            }
        }
        //verify max alowed size
        if(max_length>0 &&
            (data_type=='freetext' || data_type=='url' || data_type=='blocktext')){

            for (let idx in this.inputs) {
                let res = this._getValue(this.inputs[idx]);
                if(!window.hWin.HEURIST4.util.isempty( res ) && res.length>max_length){
                    //error highlight
                    $(this.inputs[idx]).addClass( "ui-state-error" );
                    //add error message
                    errorMessage = 'Value exceeds max length: '+max_length;
                }
            }
        }
        /*if(data_type=='integer' || this.detailType=='year'){
            //@todo validate 
            
        }else if(data_type=='float'){
            //@todo validate 
            
        }else */
        if(data_type=='resource'){
            
            const ptrset = this._prepareIds(this.f('rst_PtrFilteredIDs'));
            
            let snames = [];
            if(ptrset.length>0){
                for (let idx in ptrset) {
                    snames.push($Db.rty(ptrset[idx],'rty_Name'));
                }
            }
            snames = snames.join(', ');
            
            for (let idx in this.inputs) {
                let res = this._getValue(this.inputs[idx]);
                //check record type
                let rty_ID = $(this.inputs[idx]).find('.related_record_title').attr('data-rectypeid')
                
                if(rty_ID>0  && ptrset.length>0 && 
                    window.hWin.HEURIST4.util.findArrayIndex(rty_ID, ptrset)<0)
                {
                    //error highlight
                    $(this.inputs[idx]).addClass( "ui-state-error" );
                    //add error message
                    errorMessage = 'Target type "'+$Db.rty(rty_ID,'rty_Name')+'" is not allowed.'
                    +' Field expects target type'+((ptrset.length>1)?'s ':' ')+snames;
                    
                }
            }
            
        }
        

        this.showErrorMsg(errorMessage);

        return (errorMessage=='');
    },

    //
    //
    //
    focus: function(){
        if(!this.isReadonly() && this.inputs && this.inputs.length>0 
            && $(this.inputs[0]).is(':visible') 
            && !$(this.inputs[0]).hasClass('ui-state-disabled') )
        {
            $(this.inputs[0]).trigger('focus');   
            return $(this.inputs[0]).is(':focus');
        } else {
            return false;
        }
    },

    //
    //
    //
    _addReadOnlyContent: function(value, idx) {

        let disp_value ='';
        

        let $inputdiv = $( "<div>" ).addClass('input-div')
                .css({'font-weight':'bold','padding-top':'4px'})
                .insertBefore(this.input_prompt);

        let dwidth = this.f('rst_DisplayWidth');
        if (parseFloat( dwidth ) > 0 
            &&  this.detailType!='boolean' && this.detailType!='date' && this.detailType!='resource' ) {
             $inputdiv.css('max-width', Math.round(2 + Math.min(80, Number(dwidth))) + "ex");
        }
                
        if(Array.isArray(value)){

            disp_value = value[1]; //record title, relation description, filename, human readable date and geo

        }else if(this.detailType=="enum" || this.detailType=="relationtype"){

            disp_value = $Db.getTermValue(value);

            if(window.hWin.HEURIST4.util.isempty(value)) {
                disp_value = 'No value'; //'term missing. id '+value
            }
        } else if(this.detailType=='file'){

            $inputdiv.addClass('truncate').css({'max-width':'400px'});
            
            this._findAndAssignTitle($inputdiv, value);
            return;

        } else if(this.detailType=="resource"){

            $inputdiv.html("....resource "+value);

            this._findAndAssignTitle($inputdiv, value);
            return;

        } else if(this.detailType=="relmarker"){  //combination of enum (terms) and resource (record pointer)

            disp_value = ''; //not used 

            //@todo NEW datatypes
        } else if(this.detailType=="geo"){

            /*if(detailType=="query")
            if(detailType=="color")
            if(detailType=="bool")
            if(detailType=="password")*/

            disp_value = "@todo geo "+value;


        } else if(this.detailType=="url"){

            let def_value = this.f('rst_DefaultValue');
            if(window.hWin.HEURIST4.util.isempty(value)) value = def_value;
            
            if(!window.hWin.HEURIST4.util.isempty(value) &&
               !(value.indexOf('http://')==0 || value.indexOf('https://')==0)){
                value = 'https://'+ value;
            }
            disp_value = '<a href="'+value+'" target="_blank" title="'+value+'">'+value+'</a>';
            
            $inputdiv.addClass('truncate').css({'max-width':'400px'});
        }else{
            disp_value = value;
            
            $inputdiv.addClass('truncate').css({'max-width':'400px'});
        }

        if(this.detailType=="blocktext"){
            this.input_cell.css({'padding-top':'0.4em'});
        }

        $inputdiv.html(disp_value);

    },
    
    //
    // browse for heurist uploaded/registered files/resources and add player link
    //         
    _addHeuristMedia: function(){
        let that = this;

        let popup_options = {
            isdialog: true,
            select_mode: 'select_single',
            edit_addrecordfirst: false, //show editor atonce
            selectOnSave: true,
            select_return_mode:'recordset', //ids or recordset(for files)
            filter_group_selected:null,
            //filter_groups: this.configMode.filter_group,
            onselect:function(event, data){

                if(data){

                    if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                        let recordset = data.selection;
                        let record = recordset.getFirstRecord();

                        let thumbURL = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database
                        +"&thumb="+recordset.fld(record,'ulf_ObfuscatedFileID');

                        let playerTag = recordset.fld(record,'ulf_PlayerTag');

                        that._addMediaCaption(playerTag);

                    }

                }//data

            }
        };//popup_options        

        window.hWin.HEURIST4.ui.showEntityDialog('recUploadedFiles', popup_options);
    },

    //
    // Add caption to media
    //
    _addMediaCaption: function(content = null){

        let is_insert = false;
        let node = null;

        if(content){
            is_insert = true;
        }else{

            node = tinymce.activeEditor.selection.getNode();
            if(node.parentNode.nodeName.toLowerCase() == 'figure'){ // insert new figcaption
                node = document.createElement('figcaption');
                tinymce.activeEditor.selection.getNode().parentNode.appendChild(node);
            }else{ // replace selected content with new wrapper
                node = null;
            }

            content = tinymce.activeEditor.selection.getContent();
        }

        let $dlg;
        let msg = 'Enter a caption below, if you want one:<br><br>'
            + '<textarea rows="6" cols="65" id="figcap"></textarea>';
        
        let btns = {};
        btns[window.HR('Add caption')] = () => {
            let caption = $dlg.find('#figcap').val();

            if(caption){

                if(node != null){
                    node.innerText = caption;
                    return;
                }
                content = '<figure>'+ content +'<figcaption>'+ caption +'</figcaption></figure>';

                if(is_insert){
                    tinymce.activeEditor.insertContent( content );
                }else{
                    tinymce.activeEditor.selection.setContent( content );
                }
            }

            $dlg.dialog('close');
        };
        btns[window.HR('No caption')] = () => {
            if(is_insert){
                tinymce.activeEditor.insertContent( content );
            }
            $dlg.dialog('close');
        };

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, 
            {title: 'Adding caption to media', yes: window.HR('Add caption'), no: window.HR('No caption')}, 
            {default_palette_class: 'ui-heurist-populate'}
        );
    },

    //
    //
    //
    _prepareIds: function(ptrset)
    {
        if(!Array.isArray(ptrset)){
            if(window.hWin.HEURIST4.util.isempty(ptrset)){
                ptrset = [];
            }else if(window.hWin.HEURIST4.util.isNumber(ptrset)){
                ptrset = [ptrset];
            }else
                ptrset = ptrset.split(',')
        }
        return ptrset;
    },
    
    //
    //
    //
    _createDateInput: function($input, $inputdiv){
      
        $input.css('width', this.options.is_faceted_search?'13ex':'20ex');
        
        let that = this;

        function __onDateChange(){

            let value = $input.val();
            
            that.newvalues[$input.attr('id')] = value; 
            
            if(that.options.dtID>0){
                
                let isTemporalValue = value && value.search(/\|VER/) != -1; 
                if(isTemporalValue) {
                    window.hWin.HEURIST4.ui.setValueAndWidth($input, temporalToHumanReadableString(value));    

                    let temporal = new Temporal(value);
                    let content = '<p>'+temporal.toReadableExt('<br>')+'</p>';
                    
                    let $tooltip = $input.tooltip({
                        items: "input.ui-widget-content",
                        position: { // Post it to the right of $input
                            my: "left+20 center",
                            at: "right center",
                            collision: "none"
                        },
                        show: { // Add slight delay to show
                            delay: 500,
                            duration: 0
                        },
                        content: function(){ // Provide text
                            return content;
                        },
                        open: function(event, ui){ // Add custom CSS + class
                            ui.tooltip.css({
                                "width": "200px",
                                "background": "rgb(209, 231, 231)",
                                "font-size": "1.1em"
                            });
                        }
                    });
                    if(!that.tooltips) that.tooltips = {};
                    that.tooltips[$input.attr('id')] = $tooltip; 
                    
                    $input.addClass('Temporal').removeClass('text').attr('readonly','readonly');
                }else{
                    $input.removeClass('Temporal').addClass('text').removeAttr("readonly").css('width','20ex');
                    that._removeTooltip($input.attr('id'));
                }
            }
            
            that.onChange();
        }

        function translateDate(date, from_calendar, to_calendar){

            if(!window.hWin.HEURIST4.util.isFunction($('body').calendarsPicker)){
                return date;
            }

            if(typeof date == 'string'){
                let date_parts = date.split('-');
                date = {};
                date['year'] = date_parts[0];

                if(date_parts.length >= 2){
                    date['month'] = date_parts[1];
                }
                if(date_parts.length == 3){
                    date['day'] = date_parts[2];
                }
            }

            let new_cal = from_calendar.newDate(date['year'], date['month'], date['day']);
            if(!new_cal){
                return date;
            }

            let julian_date = new_cal._calendar.toJD(Number(new_cal.year()), Number(new_cal.month()), Number(new_cal.day()));
            return to_calendar.fromJD(julian_date);
        }

        let defDate = $input.val();
        let $tinpt = $('<input type="hidden" data-picker="'+$input.attr('id')+'">')
                        .val(defDate).insertAfter( $input );

        if(window.hWin.HEURIST4.util.isFunction($('body').calendarsPicker)){ // third party extension for jQuery date picker, used for Record editing

            let temporal = null;

            try {
                temporal = Temporal.parse($input.val());
            } catch(e) {
                temporal = null;
            }
            let cal_name = temporal ? temporal.getField('CLD') : null;
            let tDate = temporal ? temporal.getTDate("DAT") : null;

            if(cal_name){
                cal_name = cal_name.toLowerCase();
            }else{
                cal_name = 'gregorian';
            }

            // change calendar to current type
            let calendar = $.calendars.instance(cal_name);
            let g_calendar = $.calendars.instance('gregorian');

            if(!window.hWin.HEURIST4.util.isempty($input.val()) && tDate){

                if(tDate && tDate.getYear()){
                    let hasMonth = tDate.getMonth();
                    let hasDay = tDate.getDay();

                    let month = hasMonth ? tDate.getMonth() : 1;
                    let day = hasDay ? tDate.getDay() : 1;

                    defDate = translateDate({'year': tDate.getYear(), 'month': month, 'day': day}, g_calendar, calendar);
                }
            }else if(tDate){
                // remove padding zeroes from year
                let year = Number(tDate.getYear());
                defDate = tDate.toString('yyyy-MM-dd');
                defDate = defDate.replace(tDate.getYear(), year);
            }

            $tinpt.val(defDate);

            let minDate = '';
            let maxDate = '';
            let current_era = 0;
            let value_era = current_era; // to reset current era

            function setMinMaxDatesJPN(calendar, era, resetDefDate = false){

                let limits = calendar.getEraLimits(era);

                let new_options = {
                    minDate: calendar.newDate(...limits[0]),
                    maxDate: limits[1].length > 0 ? calendar.newDate(...limits[1]) : ''
                };

                let defDate = null;
                if(resetDefDate === true){
                    defDate = '';
                }else if(resetDefDate !== false){
                    defDate = resetDefDate;
                }
                if(resetDefDate !== null) { new_options['defaultDate'] = defDate; }

                current_era = era;

                $tinpt.calendarsPicker('option', new_options);
            }

            let $japanese_era = $('<select>', {class: 'calendars-eras'});
            let eras = $.calendars.instance('japanese').getEras();
            for(let i = 0; i < eras.length; i ++){
                window.hWin.HEURIST4.ui.addoption($japanese_era[0], i, eras[i]);
            }

            fixCalendarPickerCMDs();
            
            let calendarsPicker = $.calendarsPicker || $.calendars.picker; //v2 or v1

            $tinpt.calendarsPicker({
                calendar: calendar,
                defaultDate: defDate,
                //selectDefaultDate: false,
                showOnFocus: false,
                dateFormat: 'yyyy-mm-dd',
                pickerClass: 'calendars-jumps',
                minDate: minDate,
                maxDate: maxDate,
                onShow: function($calendar, calendar_locale, config){
                    config.div.css('z-index', 9999999);

                    if(calendar_locale.local.name.toLowerCase() === 'japanese'){ // Add eras dropdown to calendar

                        let $year_dropdown = $($calendar.find('.calendars-month-year')[1]);
                        let $era_sel = $japanese_era.clone().insertAfter($year_dropdown);

                        function updateYearTitle(){ // update year's rollover

                            let era_name = $era_sel.find(`option[value="${$era_sel.val()}"]`).text();
                            if(window.hWin.HEURIST4.util.isempty(era_name)){
                                $year_dropdown.attr('title', 'Change the year');
                                return;
                            }
                            era_name = era_name.split(' (')[0];

                            let cur_year = $year_dropdown.find('option[selected="selected"]').text();
                            if(window.hWin.HEURIST4.util.isempty(cur_year)){
                                $year_dropdown.attr('title', 'Change the year');
                                return;
                            }
                            cur_year = cur_year.split(' (');
                            cur_year[1] = cur_year[1].slice(0, -1);

                            $year_dropdown.attr('title', `Change the year\n${era_name} ${cur_year[0]}\nGregorian year: ${cur_year[1]}`);
                        }

                        $year_dropdown.find('option').each((idx, option) => {
                            let year = $(option).text();
                            $(option).text(`${idx+1} (${year})`);
                        });

                        current_era = current_era >= 0 ? current_era : 0;

                        $era_sel.val(current_era).attr('title', `Change the era\nCurrent era: ${eras[current_era]}`);
                        updateYearTitle();

                        that._on($year_dropdown, {
                            change: updateYearTitle
                        });
                        that._on($era_sel, {
                            change: () => {
                                // Update min + max dates
                                setMinMaxDatesJPN(calendar_locale, $era_sel.val(), true);
                            }
                        });
                    }
                },
                onSelect: function(date){

                    let cur_cal = $tinpt.calendarsPicker('option', 'calendar');
                    let value = $tinpt.val();
                    const org_value = value;
                    if(cur_cal.name.toLowerCase() === 'japanese'){
                        value_era = cur_cal.getEraFromJapaneseStr(value);
                        value = cur_cal.japaneseToGregorianStr(value);
                    }
                    let val_parts = value != '' ? value.split('-') : '';
                    let new_temporal = new Temporal();

                    if(val_parts.length == 4 && val_parts[0] == ''){ // for BC years
                        val_parts.shift();
                        val_parts[0] = '-'+val_parts[0];
                    }

                    if(window.hWin.HEURIST4.util.isArrayNotEmpty(val_parts) && val_parts.length == 3 && cur_cal.local.name.toLowerCase() != 'gregorian'){

                        let g_value = translateDate({'year': val_parts[0], 'month': val_parts[1], 'day': val_parts[2]}, cur_cal, g_calendar);
                        g_value = g_calendar.formatDate('yyyy-mm-dd', g_value);

                        if(g_value != ''){//translated value
                            try {

                                let new_tdate = TDate.parse(g_value);

                                new_temporal.setType('s');
                                new_temporal.setTDate('DAT', new_tdate);
                                new_temporal.addObjForString('CLD', cur_cal.local.name);
                                new_temporal.addObjForString('CL2', org_value);

                                value = new_temporal.toString();
                            } catch(e) {}
                        }
                    }

                    $input.val(value);
                    window.hWin.HAPI4.save_pref('edit_record_last_entered_date', $input.val());
                    __onDateChange();
                },
                onClose: function(){
                    let cur_cal = $tinpt.calendarsPicker('option', 'calendar');
                    if(cur_cal && cur_cal.local.name.toLowerCase() === 'japanese' && current_era != value_era){ // Reset calendarPicker options

                        let date = true;
                        try{
                            date = new Temporal(that.newvalues[$input.attr('id')]).getTDate('DAT').toString('y-M-d');
                        }catch(error){}
                        setMinMaxDatesJPN(cur_cal, value_era, date);
                    }
                },
                renderer: $.extend({}, calendarsPicker.defaultRenderer,
                        {picker: calendarsPicker.defaultRenderer.picker.
                            replace(/\{link:prev\}/, '{link:prevJump}{link:prev}').
                            replace(/\{link:next\}/, '{link:nextJump}{link:next}')}),
                showTrigger: '<span class="smallicon ui-icon ui-icon-calendar" style="display:inline-block" data-picker="'+$input.attr('id')+'" title="Show calendar" />'}
            );

            if(cal_name === 'japanese'){
                value_era = calendar.getEraFromGregorian(...$tinpt.val().split('-'));
                setMinMaxDatesJPN(calendar, value_era);
            }

            this._on($input, {
                'blur': function(event){ //update to changed value
                    $tinpt.val($input.val());
                }
            });
        }else{ // we use jquery datepicker for general use

                let $btn_datepicker = $( '<span>', {title: 'Show calendar'})
                    .attr('data-picker',$input.attr('id'))
                    .addClass('smallicon ui-icon ui-icon-calendar')
                    .insertAfter( $tinpt );
                    
                
                let $datepicker = $tinpt.datepicker({
                    /*showOn: "button",
                    buttonImage: "ui-icon-calendar",
                    buttonImageOnly: true,*/
                    showButtonPanel: true,
                    changeMonth: true,
                    changeYear: true,
                    dateFormat: 'yy-mm-dd',
                    beforeShow: function(){
                        
                        if(that.is_disabled) return false;
                        let cv = $input.val();
                        
                        let prev_dp_value = window.hWin.HAPI4.get_prefs('edit_record_last_entered_date'); 
                        if(cv=='' && !window.hWin.HEURIST4.util.isempty(prev_dp_value)){
                            //$datepicker.datepicker( "setDate", prev_dp_value );    
                            $datepicker.datepicker( "option", "defaultDate", prev_dp_value); 
                        }else if(cv!='' && cv.indexOf('-')<0){
                            $datepicker.datepicker( "option", "defaultDate", cv+'-01-01'); 
                        }else if(cv!='') {
                            $tinpt.val($input.val());
                            //$datepicker.datepicker( "option", "setDate", cv); 
                        }
                    },
                    onClose: function(dateText, inst){
                        
                        if($tinpt.val()!=''){
                            $input.val($tinpt.val());
                            window.hWin.HAPI4.save_pref('edit_record_last_entered_date', $input.val());
                            __onDateChange();
                        }else{
                            $tinpt.val($input.val());
                        }
                    }
                });
                
                this._on( $input, {
                    keyup: function(event){
                        if(!isNaN(String.fromCharCode(event.which))){
                            let cv = $input.val();
                            if(cv!='' && cv.indexOf('-')<0){
                                $datepicker.datepicker( "setDate", cv+'-01-01');   
                                $input.val(cv);
                            }
                        }
                    },
                    keypress: function (e) {
                        let code = e.charCode || e.keyCode;
                        let charValue = String.fromCharCode(code);
                        let valid = false;

                        if(charValue=='-'){
                            valid = true;
                        }else{
                            valid = /^[0-9]+$/.test(charValue);
                        }

                        if(!valid){
                            window.hWin.HEURIST4.util.stopEvent(e);
                            e.preventDefault();
                        }

                    },
                    dblclick: function(){
                        $btn_datepicker.trigger('click');
                    }
                });

               
               
                
                this._on( $btn_datepicker, { click: function(){
                    
                        if(that.is_disabled) return;
                        
                        $datepicker.datepicker( 'show' ); 
                        $("#ui-datepicker-div").css("z-index", "999999 !important"); 
                        //$(".ui-datepicker").css("z-index", "999999 !important");   
                }} );
        } 

        if(this.options.is_faceted_search){
            
                $input.css({'max-width':'13ex','min-width':'13ex'});
            
        }else if(this.options.dtID>0){ //this is details of records

            // temporal widget button
            let $btn_temporal = $( '<span>', {
                title: 'Pop up widget to enter compound date information (uncertain, fuzzy, radiometric etc.)', 
                class: 'smallicon', 
                style: 'margin-left: 1em;width: 55px !important;font-size: 0.8em;cursor: pointer;'
            })
            .text('range')
            .appendTo( $inputdiv );

            $('<span>', {class: 'ui-icon ui-icon-date-range', style: 'margin-left: 5px;'}).appendTo($btn_temporal); // date range icon

            this._on( $btn_temporal, { click: function(){
                
                if(that.is_disabled) return;

                let url = window.hWin.HAPI4.baseURL 
                    + 'hclient/widgets/editing/editTemporalObject.php?'
                    + encodeURIComponent(that.newvalues[$input.attr('id')]
                                ?that.newvalues[$input.attr('id')]:$input.val());
                
                window.hWin.HEURIST4.msg.showDialog(url, {height:570, width:750,
                    title: 'Temporal Object',
                    class:'ui-heurist-populate-fade',
                    //is_h6style: true,
                    default_palette_class: 'ui-heurist-populate',
                    callback: function(str){
                        if(!window.hWin.HEURIST4.util.isempty(str) && that.newvalues[$input.attr('id')] != str){
                            $input.val(str);    
                            $input.trigger('change');
                        }

                        if(window.hWin.HEURIST4.util.isFunction($('body').calendarsPicker) && $tinpt.hasClass('hasCalendarsPicker')){

                            let new_temporal = null;
                            let new_cal = null;
                            let new_date = null;
                            try {
                                new_temporal = Temporal.parse(str);
                                new_cal = new_temporal.getField('CLD');
                                new_cal = $.calendars.instance(new_cal);
                                new_date = new_temporal.getTDate("DAT");
                            } catch(e) {
                                new_cal = null;
                                new_date = null;
                            }

                            // Update calendar for calendarPicker
                            if(new_cal && new_date && typeof $tinpt !== 'undefined'){

                                if(new_date.getYear()){
                                    let hasMonth = new_date.getMonth();
                                    let hasDay = new_date.getDay();

                                    let month = hasMonth ? new_date.getMonth() : 1;
                                    let day = hasDay ? new_date.getDay() : 1;

                                    let g_calendar = $.calendars.instance('gregorian');

                                    new_date = translateDate({'year': new_date.getYear(), 'month': month, 'day': day}, g_calendar, new_cal);
                                    new_date = new_date.formatDate('yyyy-mm-dd', new_cal);
                                }

                                let cur_cal = $tinpt.calendarsPicker('option', 'calendar');
                                if(cur_cal.local.name.toLowerCase() != new_cal.local.name.toLowerCase()){
                                    $tinpt.calendarsPicker('option', 'calendar', new_cal);
                                }

                                if(typeof new_date == 'string'){
                                    $tinpt.val(new_date);
                                }
                            }
                        }
                    }
                } );
            }} );

        }//temporal allowed
        
        this._on($input, {'change':__onDateChange});
    },

    //
    //
    //
    setBetweenMode: function(mode_val){
        
        if(this.options.is_faceted_search && 
           this.options.is_between_mode!=mode_val && 
                (this.detailType=='freetext' || this.detailType=='date'
                || this.detailType=='integer' || this.detailType=='float')){
            
           this.options.is_between_mode = mode_val;
           
           if(this.options.is_between_mode){
                this.addSecondInput();           
           }else{
               let that = this;
               this.element.find('.span-dash').remove();
               $.each(this.inputs, function(idx, item){
                    let id = $(item).attr('id')+'-2';
                    that.element.find('#'+id).remove();
                    if(that.detailType=='date') {
                        that.element.find('input[data-picker="'+id+'"]').remove();
                        that.element.find('span[data-picker="'+id+'"]').remove();
                    }
               });
           }
        }
    },
    
    //
    //
    //
    addSecondInput: function(input_id){

        let that = this;
        $.each(this.inputs, function(idx, item){

            let $input = $(item);
            if(input_id==null || $input.attr('id')==input_id){
                
                let $inputdiv = $input.parents('.input-div');
                
                let edash = $('<span class="span-dash">&nbsp;-&nbsp;</span>')
                //duplicate input for between mode
                if(that.detailType=='date') {
                    
                    
                    let dpicker_btn = that.element.find('span[data-picker="'+$input.attr('id')+'"]');
                    
                    edash.insertAfter(dpicker_btn);
                    
                    let inpt2 = $('<input>').attr('id',$input.attr('id')+'-2')
                            .addClass('text ui-widget-content ui-corner-all')
                            .on('change', function(){
                                that.onChange();
                            })
                            .insertAfter(edash);
                            
                    window.hWin.HEURIST4.ui.disableAutoFill( inpt2 );
                            
                    that._createDateInput(inpt2, $inputdiv);
            
                }else{
                    edash.insertAfter($input);
                    
                    $input.css({'max-width':'20ex','min-width':'20ex'});   
                    
                    $input.clone(true).attr('id',$input.attr('id')+'-2').insertAfter(edash);
                }
                if(input_id!=null){
                    return false;
                }
            }
        });
        
    },
	
    
	//
	// Recreate dropdown or checkboxes|radio buttons, called by adding new term and manage terms onClose
	//
	_recreateEnumField: function(vocab_id){

        let that = this;

        this.child_terms = $Db.trm_TreeData(vocab_id, 'set'); //refresh
        let asButtons = this.options.recordset && this.options.recordset.entityName=='Records' && this.f('rst_TermsAsButtons') == 1;

        if(asButtons && this.child_terms.length <= 20){ // recreate buttons/checkboxes

            this.enum_buttons = (Number(this.f('rst_MaxValues')) != 1) ? 'checkbox' : 'radio';
            let dtb_res = this._createEnumButtons(true);

            if(dtb_res){

                // Change from select to input text
                $.each(this.inputs, function(idx, input){

                    let $input = $(input);
                    let value = $input.val();
                    let inpt_id = $input.attr('id');

                    if($input.is('select')){

                        if($input.hSelect('instance') != undefined){
                            $input.hSelect('destroy');
                        }
                        that._off($input, 'change');
                        let $inputdiv = $input.parent();
                        $input.remove();

                        $input = $('<input type="text" class="text ui-widget-content ui-corner-all">')
                                    .attr('id', inpt_id)
                                    .val(value)
                                    .prependTo($inputdiv)
                                    .hide();

                        $inputdiv.find('input[data-id="'+ value +'"]').prop('checked', true);

                        that._on( $input, {change:that.onChange} );

                        that.inputs[idx] = $input;

                        if(idx != 0){
                            $inputdiv.hide();
                        }

                        if(that.btn_add){
                            that.btn_add.hide(); // Hide repeat button, removeClass('smallbutton ui-icon-circlesmall-plus')
                        }
                    }
                });
            }
        }else{

            this.enum_buttons = null;

            $.each(this.inputs, function(idx, input){ 

                let $input = $(input);
                let value = $input.val();

                if($input.is('input')){

                    that._off($input, 'change');
                    let $inputdiv = $input.parent();
                    if(idx == 0){
                        $inputdiv.find('label.enum_input, br').remove();
                        $inputdiv.find('.smallicon').css({'top': '', 'margin-top': '2px'});
                    }

                    let inpt_id = $input.attr('id');
                    $input.remove();

                    $input = $('<select>')
                                .attr('id', inpt_id)
                                .addClass('text ui-widget-content ui-corner-all')
                                .prependTo( $inputdiv );

                    $inputdiv.show();

                    that.inputs[idx] = $input;

                    if(that.btn_add){
                        that.btn_add.show(); // Show repeat button, removeClass('smallbutton ui-icon-circlesmall-plus')
                    }
                }

                if(window.hWin.HEURIST4.util.isempty(value) && value != ''){
                    value = true;
                }

                $input.css('width','auto');
                
                if(window.hWin.HEURIST4.util.isempty(that.f('rst_FieldConfig'))) {


                    if(that.selObj) {
                        that.selObj.remove();    
                        that.selObj = null;
                    }
                    browseTerms(that, $input, value);
                    
                }else{
                    $input = that._recreateSelector($input, value); //in _recreateEnumField
                    $input.hSelect('widget').css('width','auto');
                }
                
                that._on( $input, {change:that._onTermChange} );
            });//each
        }

        
        if(that.input_cell.find('.ui-icon-image').length>0){ //if edit allowed
            this._checkTermsWithImages();    
        }
    },

    
    //
    // Show/Hide select by picture  
    //
    _checkTermsWithImages: function(){ 
                           
        this._enumsHasImages = false;
        
        if(this.child_terms.length>0){

                let trm_img_req = {
                    'a': 'search',
                    'entity': 'defTerms',
                    'details': 'list',
                    'trm_ID': this.child_terms.join(','),
                    'withimages': 1,
                    'request_id': window.hWin.HEURIST4.util.random()
                };

                let that = this;

                window.hWin.HAPI4.EntityMgr.doRequest(trm_img_req, function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        let recset = new HRecordSet(response.data);
                        that._enumsHasImages = (recset.length() > 0);
                        if(that._enumsHasImages){
                            that.input_cell.find('.ui-icon-image').show();
                        }else{
                            that.input_cell.find('.ui-icon-image').hide();
                        }
                    }
                });
                
          }

          this.input_cell.find('.ui-icon-image').hide();
    },

    
    //
    // Set up checkboxes/radio buttons for enum field w/ rst_TermsAsButtons set to 1
    // Params:
    //	isRefresh (bool): whether to clear $inputdiv first
    //	terms_list (array): array of term ids
    //	$inputdiv (jQuery Obj): element where inputs will be placed
    //	values (array): array of existing values to check by default
    //
    _createEnumButtons: function(isRefresh, $inputdiv, values){

        let that = this;
        
        let terms_list = this.child_terms;

        if($inputdiv == null){
            $inputdiv = $(this.inputs[0]).parent();
        }
        if(values == null){

            values = [];

            $.each(that.inputs, function(idx, ele){
                let $ele = $(ele);

                values.push($ele.val());
            });
        }

        if(window.hWin.HEURIST4.util.isempty(terms_list) || window.hWin.HEURIST4.util.isempty($inputdiv)){
            // error
            return false;
        }

        if(isRefresh){

            let $eles = $(this.inputs[0]).parent().find('label.enum_input, br');

            if($eles.length > 0){
                $eles.remove();
            }
        }

        // input div's width
        let f_width = parseInt(this.f('rst_DisplayWidth'));
        f_width = (window.hWin.HEURIST4.util.isempty(f_width) || f_width < 100) ? 110 : f_width + 10; // +10 for extra room

        $inputdiv.css({'max-width': (f_width + 20) + 'ex', 'min-width': f_width + 'ex'});

        for(let i = 0; i < terms_list.length; i++){

            let trm_label = $Db.trm(terms_list[i], 'trm_Label');
            let trm_id = terms_list[i];
            let isChecked = (values && values.includes(trm_id)) ? true : false;

            let $btn = $('<input>', {'type': this.enum_buttons, 'title': trm_label, 'value': trm_id, 'data-id': trm_id, 'checked': isChecked, name: this.options.dtID})
                .on('change', function(event){ 

                    let isNewVal = false;
                    let changed_val = $(event.target).val();

                    if($(event.target).is(':checked')){
                        isNewVal = true;
                    }

                    if(that.enum_buttons == 'radio'){

                        if(isNewVal){
                            $(that.inputs[0]).val(changed_val);
                        }else{
                            $(that.inputs[0]).val('');
                        }
                    }else{

                        if(!isNewVal){

                            if(that.inputs.length == 1){
                                $(that.inputs[0]).val('');
                            }else{
                                $.each(that.inputs, function(idx, ele){

                                    let $ele = $(ele);

                                    if($ele.val() == changed_val){

                                        if(idx != 0){
                                            $ele.parents('.input-div').remove();
                                            that.inputs.splice(idx, 1);

                                            return false;
                                        }else{

                                            let last_idx = that.inputs.length - 1;
                                            let $last_ele = $(that.inputs[last_idx]);

                                            $(that.inputs[0]).val($last_ele.val());

                                            $last_ele.parents('.input-div').remove();
                                            that.inputs.splice(last_idx, 1);

                                            return false;
                                        }
                                    }
                                });
                            }
                        }else{

                            that.new_value = changed_val;

                            that.btn_add.trigger('click');
                        }
                    }

                    that.onChange();
                });

            $('<label>', {'title': trm_label, append: [$btn, trm_label]})
                    .addClass('truncate enum_input')
                    .css({
                        'max-width': '120px',
                        //'min-width': '120px',
                        'display': 'inline-block',
                        'margin-right': '15px'
                    })
                    .appendTo($inputdiv);
        }

        let $other_btns = $inputdiv.find('.smallicon, .smallbutton');

        if($other_btns.length > 0){
            $other_btns.appendTo($inputdiv);

            $other_btns.filter('.smallicon').css({'top': '-4px', 'margin-top': ''});
        }

        return true;
    },

    //
    // In preparation for creating a new relationship marker from an external lookup
    //
    setup_Relmarker_Target: function(target_id, relation_value, callback){

        target_id = parseInt(target_id, 10);

        if(isNaN(target_id) || target_id < 1){
            return;
        }

        this._external_relmarker.target = target_id;

        this._external_relmarker.relation = relation_value;

        this._external_relmarker.callback = callback;
    },

    //
    // Take a photo using the user's camera
    //
    _photoMode: async function(){

        // Check if camera permissions have been granted
        const permissions = await navigator.permissions.query({ name: "camera" });
        if(permissions.state !== 'granted'){

            const chrome = 'https://support.google.com/chrome/answer/2693767?hl=en&co=GENIE.Platform%3DDesktop#:~:text=On%20your%20computer%2C%20open%20Chrome.,-At%20the%20top&text=Settings.,-Select%20Privacy%20and&text=Site%20settings.,%2C%22%20select%20Camera%20or%20Microphone.';
            const firefox = 'https://support.mozilla.org/en-US/kb/how-manage-your-camera-and-microphone-permissions';
            const opera = 'https://help.opera.com/en/latest/web-preferences/#camera';

            window.hWin.HEURIST4.msg.showMsgErr({
                status: window.hWin.ResponseStatus.ACTION_BLOCKED,
                message: 'Heurist is unable to access your camera due to a lack of permissions.<br>'
                        +`Please grant camera permission for "${window.hWin.HAPI4.baseURL}" to use your camera before trying again.<br>`
                        +`<a href="${chrome}" target="_blank" rel="noopener">Google Chrome</a>&nbsp;&nbsp;&nbsp;<a href="${firefox}" target="_blank" rel="noopener">Firefox</a>&nbsp;&nbsp;&nbsp;<a href="${opera}" target="_blank" rel="noopener">Opera</a>`
            });

            return;
        }

        let that = this;
        let video_strean = null;

        let $dlg, $canvas, $video, $img, $doAction;
        let width = 1280, height;

        //
        // Stop/close video streams
        //
        let stop_stream = () => {

            if(!video_strean){
                return;
            }

            video_strean.getTracks().forEach(track => {
                if(track.readyState === 'live' && track.kind === 'video'){
                    track.stop();
                }
            });
        };

        //
        // Handle height and width for camera
        //
        let stream_started = false;
        let start_stream = () => {

            if(stream_started){
                $video[0].removeEventListener('canplay', start_stream); // remove event listener
                return;
            }

            height = $video[0].videoHeight / ($video[0].videoWidth / width);

            if(!window.hWin.HEURIST4.util.isPositiveInt(height)){
                height = width / (4 / 3);
            }

            $video.prop('width', width);
            $video.prop('height', height);
            $canvas.prop('width', width);
            $canvas.prop('height', height);

            stream_started = true;
            
            $dlg.css('max-width', 'none');

            $dlg.dialog('option', 'position', {my: "center", at: "center"});
        };

        // Retrieve video stream
        try{
            video_strean = await navigator.mediaDevices.getUserMedia({
                video: true,
                audio: false
            });
        }catch{

            window.hWin.HEURIST4.msg.showMsgErr({
                status: window.hWin.ResponseStatus.ACTION_BLOCKED,
                message: `Heurist is unable to access your camera, it may already be in use by another program.`
            });

            return;
        }

        // Setup popup
        let content = '<div id="feed">'
                        + '<video id="video-stream"> Stream unavailable </video>'
                        + '<button class="take-photo ui-button-action">Take photo</button>'
                    + '</div>'
                    + '<canvas id="photo-canvas" style="display:none;"></canvas>'
                    + '<div id="photo-taken" style="display:none;">'
                        + '<img id="photo" alt="Photo taken appears here" />'
                        + '<button class="retake-photo ui-button-action">Retake photo</button>'
                    + '</div>';
        
        let btns = {};
        btns[window.hWin.HR('Use photo')] = () => {

            const encoded_str = $img.prop('src');
            const name = `snapshot_${window.hWin.HEURIST4.util.random()}`;

            let request = {
                entity: 'recUploadedFiles',
                a: 'batch',
                regRawImages: 1,
                files: [{encoded: encoded_str, name: name}]
            };

            window.hWin.HEURIST4.msg.bringCoverallToFront(null, null, 'Registering snapshot...');

            window.hWin.HAPI4.EntityMgr.doRequest(request, (response) => {

                window.hWin.HEURIST4.msg.sendCoverallToBack();

                if(window.hWin.ResponseStatus.OK != response.status){
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    return;
                }else if(!window.hWin.HEURIST4.util.isArrayNotEmpty(response?.data)){
                    window.hWin.HEURIST4.msg.showMsgErr({
                        status: window.hWin.ResponseStatus.UNKNOWN_ERROR,
                        message: 'Image was not registered due to unknown issue, please report this to the Heurist team'
                    });
                    return;
                }

                // Set field value
                let values = that.getValues();
                values = [...values, ...response.data];
                values = values.filter((ulf_ID) => !window.hWin.HEURIST4.util.isempty(ulf_ID));
                that.setValue(values);

                // End video stream and close dialog
                stop_stream();
                $dlg.dialog('close');
            });

        };
        btns[window.hWin.HR('Close')] = () => {
            stop_stream();
            $dlg.dialog('close');
        };

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(content, btns, {title: 'Photo mode'}, {default_palette_class: 'ui-heurist-populate', dialogId: 'photo-mode', beforeClose: stop_stream});

        $video = $dlg.find('#video-stream');
        $canvas = $dlg.find('#photo-canvas');
        $img = $dlg.find('#photo');
        $doAction = $($dlg.parent().find('.ui-dialog-buttonset .ui-button')[0]);

        // Connect stream to video element
        $video.prop('srcObject', video_strean);
        $video[0].play();
        $video[0].addEventListener('canplay', start_stream, false);

        $dlg.find('.take-photo').button().on('click', () => {

            // Set canvas
            let context = $canvas[0].getContext('2d');

            $canvas.prop('width', width);
            $canvas.prop('height', height);

            context.drawImage($video[0], 0, 0, width, height);

            // Set snapshot image
            const url = $canvas[0].toDataURL('image/png');
            $img.prop('src', url);

            $dlg.find('#photo-taken').show();
            $dlg.find('#feed').hide();

            window.hWin.HEURIST4.util.setDisabled($doAction, false);
        });

        $dlg.find('.retake-photo').button().on('click', () => {

            // Reset canvas
            let context = $canvas[0].getContext('2d');
            context.fillStyle = '#AAA';
            context.fillRect(0, 0, $canvas.width(), $canvas.height());

            // Reset snapshot image
            $img.prop('src', '');

            $dlg.find('#photo-taken').hide();
            $dlg.find('#feed').show();

            window.hWin.HEURIST4.util.setDisabled($doAction, true);
        });

        $dlg.find('.take-photo, .retake-photo').css({
            float: 'right',
            'font-size': '12px',
            'font-weight': 'bold',
            'margin-left': '10px'
        });

        window.hWin.HEURIST4.util.setDisabled($doAction, true);
    }
});