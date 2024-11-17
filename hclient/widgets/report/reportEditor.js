/**
* reportEditor.js - edit smarty report temaplate
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

/* global CodeMirror */

$.widget( "heurist.reportEditor", $.heurist.baseAction, {

    // default options
    options: {
        height: 640,
        width:  1000,
        title:  'Edit Report Template',
        default_palette_class: 'ui-heurist-populate',
        actionName: 'reportEditor',
        htmlContent: 'reportEditor.html',
        path: 'widgets/report/',

        is_snippet_editor: false,
        rty_ID:null, 
        
        keep_instance: true,
        template: null
    },
    
    usrPreferences:{
            insertForm_width:300, 
            insertForm_closed:false, 
            testForm_width:400, 
            testForm_closed:false,
            width:(window.hWin?window.hWin.innerWidth:window.innerWidth)*0.95,
            height:(window.hWin?window.hWin.innerHeight:window.innerHeight)*0.95
    },
    
    _keepTemplateValue:'',
    codeEditor: null,
    _currentTemplate: '',
    
    _addVariableDlg: null,
    
    _tempForm: null,
    
    _last_disabled_message: null,
    
    _create: function() {
        this._super();
        if(this.options.is_snippet_editor){
            this.options.width  = (window.hWin?window.hWin.innerWidth:window.innerWidth)*0.7
            this.options.height = (window.hWin?window.hWin.innerHeight:window.innerHeight)*0.7
           
        }else{
            this.options.height = this.usrPreferences.height;
            this.options.width = this.usrPreferences.width;
        }
        
        let that = this;
        this.options.beforeClose = function(){
            return that._beforeClose();
        };

    }, //end _create
    
    _init: function() {
        
        this._super();
        
        if( this._is_inited ){
            this._loadTemplate();
        }
    },
    
    _destroy: function() {
        if(this._tempForm){
            this._tempForm.remove();
        }
    },

   
    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){
        
        let res  = this._super();
        if(!res) return false;
        
        this.editSmarty = this._$('.editSmarty');
        
        let that = this;
        
                let layout_opts =  {
                    applyDefaultStyles: true,
                    maskContents: true,
                    //togglerContent_open:    '&nbsp;',
                    //togglerContent_closed:  '&nbsp;',
                    west:{
                        size: this.usrPreferences.insertForm_width,
                        maxWidth:500,
                        minWidth:300,
                        spacing_open:6,
                        spacing_closed:40,  
                        togglerAlign_open:'center',
                        //togglerAlign_closed:'top',
                        togglerAlign_closed:16,   //top position   
                        togglerLength_closed:80,  //height of toggler button
                        initHidden: false,   //show structure list at once 
                        initClosed: this.usrPreferences.insertForm_closed,
                        slidable:false,  //otherwise it will be over center and autoclose
                        contentSelector: '.insertForm',   
                        onopen_start : function( ){ 
                            let tog = that.editSmarty.find('.ui-layout-toggler-west');
                            tog.removeClass('prominent-cardinal-toggler togglerVertical');
                            tog.find('.heurist-helper2.westTogglerVertical').hide();
                        },
                        onclose_end : function( ){ 
                            let tog = that.editSmarty.find('.ui-layout-toggler-west');
                            tog.addClass('prominent-cardinal-toggler togglerVertical');

                            if(tog.find('.heurist-helper2.westTogglerVertical').length > 0){
                                tog.find('.heurist-helper2.westTogglerVertical').show();
                            }else{
                                $('<span class="heurist-helper2 westTogglerVertical" style="width:200px;margin-top:220px;">Insert Patterns / Variables</span>').appendTo(tog);
                            }
                        },
                        onresize_end: function(){
                            //
                        },
                        togglerContent_open:    '<div class="ui-icon ui-icon-triangle-1-w"></div>',
                        togglerContent_closed:  '<div class="ui-icon ui-icon-carat-2-e"></div>',
                    },
                    east:{
                        size: this.usrPreferences.testForm_width,
                        minWidth:400,
                        maxWidth:800,
                        spacing_open:6,
                        spacing_closed:40,  
                        togglerAlign_open:'center',
                        //togglerAlign_closed:'top',
                        togglerAlign_closed:16,   //top position   
                        togglerLength_closed:40,  //height of toggler button
                        initClosed: this.usrPreferences.testForm_closed,
                        slidable:false,  //otherwise it will be over center and autoclose
                        contentSelector: '.testForm',   
                        onopen_start : function(){ 
                            let tog = that.editSmarty.find('.ui-layout-toggler-east');
                            tog.removeClass('prominent-cardinal-toggler togglerVertical');
                            tog.find('.heurist-helper2.eastTogglerVertical').hide();
                        },
                        onclose_end : function(){ 
                            let tog = that.editSmarty.find('.ui-layout-toggler-east');
                            tog.addClass('prominent-cardinal-toggler togglerVertical');

                            if(tog.find('.heurist-helper2.eastTogglerVertical').length > 0){
                                tog.find('.heurist-helper2.eastTogglerVertical').show();
                            }else{
                                $('<span class="heurist-helper2 eastTogglerVertical" style="width:200px;">Test Area</span>').appendTo(tog);
                            }
                        },
                        togglerContent_open:    '<div class="ui-icon ui-icon-triangle-1-e"></div>',
                        togglerContent_closed:  '<div class="ui-icon ui-icon-carat-2-w"></div>',
                    },
                    center:{
                        minWidth:400,
                        contentSelector: '.editForm',
                        //pane_name, pane_element, pane_state, pane_options, layout_name
                        onresize_end : function(){
                            //that.handleTabsResize();                            
                        }    
                    }
                };

        this.editSmarty.layout(layout_opts); //.addClass('ui-heurist-bg-light')
        
        this._on(this._$('.closeInsertForm'), {click:()=>{this.editSmarty.layout().close("west");}});
        this._on(this._$('.closeTestForm'), {click:()=>{this.editSmarty.layout().close("east");}});
        
        //init Insert Pattern controls
        let rtSelect = this._$('#rectype_selector');
        let $rec_select = window.hWin.HEURIST4.ui.createRectypeSelect( rtSelect.get(0), 
                                        this.options.rty_ID,
                                        this.options.rty_ID>0?null:window.hWin.HR('select record type'), true );
        this._on($rec_select,{change: this._loadRecordTypeTreeView});
        
        this._on(this._$('#btnInsertPattern').button(), {click:this._insertPattern});

        //init test panel
        this._on(this._$('.btnStartTest').button({icon: 'ui-icon-circle-arrow-s'}), 
            {click:()=>{this._doTest();}});
        

        if(this.options.is_snippet_editor){
            this._$('.editForm').css({top:'90px'});
            this._$('.insertForm > .ent_content_full').css({top:'50px'});
            this._$('.hide-for-snippet').hide();
            this._$('.show-for-snippet').show();
            
            this._loadRecordTypeTreeView();
            this._loadTestRecords();
            
            this._initEditor(this.options.template_body);
        }else{
            //init editor (load codeMirror)
            this._loadTemplate();
        }        

        
        
        return true;
    },
    
    //
    //
    //
    _doTest:function(){

        let template_body = this.codeEditor.getValue();

        if(template_body?.length<=10){
            window.hWin.HEURIST4.msg.showMsgFlash('Nothing to execute. Define code');
            return;            
        }

        let recset;
        let request = {db:window.hWin.HAPI4.database, 
                       action: 'execute', 
                       recordset: 1,
                       template_body:1};
        
        if(this.options.is_snippet_editor){
                let rec_ID = this._$('#listRecords').val();
                if(!window.hWin.HEURIST4.util.isPositiveInt(rec_ID)){
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: 'Select record to test on',
                        error_title: 'Missing record'
                    });
                    return;
                }
                request['publish'] = 4;
                recset = JSON.stringify({records:[rec_ID], reccount:1});
                
        }else if(window.hWin.HAPI4.currentRecordset?.length()==0){
            window.hWin.HEURIST4.msg.showMsgFlash('Perform search to get record set to test against');
            return;
        }else{
        
            let debug_limit = document.getElementById('cbDebugReportLimit').value;
            if(debug_limit<0){
                debug_limit = 2000;
            }
            
            recset = {recIDs:window.hWin.HAPI4.currentRecordset.getIds().slice(0, debug_limit-1)};
        }

        let replevel = document.getElementById('cbErrorReportLevel').value;
        if(replevel<0) {
            document.getElementById('cbErrorReportLevel').value = 0;
            replevel = 0;
        }
        request['replevel'] = replevel;
        
        window.hWin.HEURIST4.msg.bringCoverallToFront(this._$('.testForm'));
        
        let inputs = '';
        for (let [key, value] of Object.entries(request)) {
          inputs += `<input type="hidden" name="${key}" value="${value}"/>`;
        }       
        
        if(this._tempForm){
            this._tempForm.empty();
        }else{
            const url = window.hWin.HAPI4.baseURL+'hserv/controller/index.php';
            this._tempForm = $(`<form target="test_container_frame" action="${url}" method="post"></form>`)
                .appendTo(this.element);
                
            this._on(this._$('#test_container_frame'),{load:()=>{
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                this._showWarningAboutDisabledFunction();
            }});
        }
        
        this._tempForm.html(inputs);
        this._tempForm.find('input[name="recordset"]').val(JSON.stringify(recset));
        this._tempForm.find('input[name="template_body"]').val(template_body);
        this._tempForm.trigger('submit');
        
    },
    
    
    _showWarningAboutDisabledFunction: function(){    
        
        let txt = this._$('#test_container_frame')[0].contentDocument.body.innerHTML;
        
        if(this._last_disabled_message != txt && 
            window.hWin.HEURIST4.msg.showWarningAboutDisabledFunction(txt)){
            this._last_disabled_message = txt;
        }
        
    },
    
    //
    //
    //
    _loadTemplate: function(){    
        
        if(this.options.is_snippet_editor){
            this._initEditor(this.options.template_body);
        }else
        // null means new template
        if(this._currentTemplate!=this.options.template){
            this._currentTemplate = this.options.template;

            let that = this;
            window.hWin.HAPI4.SystemMgr.reportAction({action:'get', template:this._currentTemplate}, 
                function(response){
                    that._initEditor(response.message);
            });
           
            this.changeTitle();
        }
    },
    
    //
    //
    //
    changeTitle: function( new_title ){
        if(!new_title){
           new_title = window.hWin.HR('Edit Report Template')+': '+
                    (this._currentTemplate?this._currentTemplate:'new template');
        }
        this._super(new_title);
    },
    
    //
    //
    // 
    _initEditor: function(content){
    
        let that = this;
        
        if(this.codeEditor==null){
            
            //!window.hWin.HEURIST4.util.isFunction($(document.body)['CodeMirror'])
            if(typeof CodeMirror !== 'function' )
            {
                
                let path = window.hWin.HAPI4.baseURL + 'external/codemirror-5.61.0/';
                let scripts = [ 
                                'lib/codemirror.js',
                                'mode/xml/xml.js',
                                'mode/javascript/javascript.js',
                                'mode/css/css.js',
                                'mode/htmlmixed/htmlmixed.js',
                                'mode/smarty/smarty.js',
                                'mode/smartymixed/smartymixed.js'
                ];
                
                $.getMultiScripts2(scripts, path)
                .then(function() {  //OK! widget script js has been loaded
                    that._initEditor( content );
                }).catch(function(error) {
                    window.hWin.HEURIST4.msg.showMsg_ScriptFail();
                });
                
                return;
            }

                this._$('.editForm').empty().css({padding:0});
            
                this.codeEditor = CodeMirror( this._$('.editForm')[0], {
                    mode           : "smartymixed",
                    tabSize        : 2,
                    indentUnit     : 2,
                    indentWithTabs : false,
                    lineNumbers    : true,
                    smartyVersion  : 3,
                    matchBrackets  : true,
                    smartIndent    : true,
                    extraKeys: {
                        "Enter": function(e){
                            that._insertAtCursor('');
                        }
                    },
                    onFocus:function(){},
                    onBlur:function(){}
                });
        }//codeMirror init

        let using_default = false;
        if(window.hWin.HEURIST4.util.isempty(content)){
            //if(this._currentTemplate!=this.options.template){
            //      this._loadTemplate();
            //      return;
            //}
            
            content = "{ }";
            using_default = true;
        }

        this.codeEditor.setValue(content);
        this._keepTemplateValue = content;

        setTimeout(function(){
            $('div.CodeMirror').css('height','100%').show();
            $('div.CodeMirror .CodeMirror-scroll').css('padding-top', '5px');
            that.codeEditor.refresh();
            //that._keepTemplateValue = that.codeEditor.getValue();

            if(using_default){
                that.codeEditor.setCursor({line: 0, char: 0});
            }
        },1000);
    },
    
    
    //
    // "IF" for root rectypes
    //
    _insertPatternRectypeIf: function(_nodep, parent, rectypeId){
        
        let _remark = '{* ' + this._getRemark(_nodep) + ' *}';
        
        return '{if ($'+parent+'.recTypeID=="'+rectypeId+'")}'+_remark+ ' \n  \n{/if}'+ _remark +' \n';  

    },
    
    //
    // NEW
    //    
    _insertPatternIfOperator: function(_nodep, varname, language_handle = '', file_handle = ''){
        let _remark = '{* ' + this._getRemark(_nodep) + ' *}';
        let inner_val = language_handle !== '' ? language_handle : "{$"+varname+"}";
        inner_val = file_handle !== '' ? file_handle : inner_val;
        return "\n{if ($"+varname+")}"+_remark+"\n\n   "+inner_val+" \n\n{/if}\n"+_remark+" {* you can also add {/else} before {/if}} *}\n";
    },
    
    //
    // insert foreach operator
    //
    _insertPatternMagicLoop: function(_nodep, varname, language_handle = '', file_handle = ''){
        
        let _remark = '{* ' + this._getRemark(_nodep) + ' *}';
        
        let codes = varname.split('.');
        let field = codes[codes.length-1];
        
        let loopname = (_nodep.parent?.type=='enum')?'ptrloop':'valueloop';
        let getrecord = (_nodep.parent?.type=='resource')? ('{$'+field+'=$heurist->getRecord($'+field+')}') :'';

        if(!window.hWin.HEURIST4.util.isempty(language_handle)){
            language_handle = '\n\t' + language_handle.replace('replace_id', field) + '\n';
        }
        if(!window.hWin.HEURIST4.util.isempty(file_handle)){
            file_handle = '\n\t' + file_handle.replace('replace_id', field) + '\n';
        }
        
        if(codes[1]=='Relationship'){
            this._insertGetRelatedRecords();
            
            return '{foreach $r.Relationships as $Relationship name='+loopname+'}'+_remark +'\n\n{/foreach}'+_remark;
            
        }else{
            
            if(varname.indexOf('_originalvalue')<0){
                varname = varname+'s';
            }else if(field.indexOf('_originalvalue')>0){
                field = field.substring(0, field.length-14);
            }
            
            return '{foreach $'+varname+' as $'+field+' name='+loopname+'}'+_remark
                    +'\n\t'+getrecord+'\n'  //' {* '+_remark + '*}'
                    + language_handle
                    + file_handle
                    +'\n{/foreach} '+_remark;
        }

    },
    
    //
    //
    //
    _getRemark: function(_nodep){

        let s = _nodep.title;
        let key = _nodep.key;

        if(key=='label' || key=='term' || key=='code' || key=='conceptid' || key=='internalid' || key=='desc'){
            s = _nodep.parent.title + '.' + s;
        }

        s =  window.hWin.HEURIST4.util.stripTags(s);
        if(_nodep.parent?.data.codes ){ //!_nodep.parent.isRootNode()
            s = window.hWin.HEURIST4.util.stripTags(_nodep.parent.title) + ' >> ' + s;
        }
        return s;
    },
    
    //
    // _addVariable2
    //
    _insertPatternVariable: function(_nodep, varname, insertMode, inLoop, language_handle = '', file_handle = ''){
        
        let res= '';
        
        let remark = this._getRemark(_nodep);

        if(insertMode==0){ //variable only

            let inner_val = language_handle !== '' ? language_handle : "{$"+varname+"}";
            inner_val = file_handle !== '' ? file_handle : inner_val;
            res = inner_val + " {*" +  remark + "*}";

        }else if (insertMode==1){ //label+field

            res = _nodep.title+": {$"+varname+"}";  //not used

        }else if(_nodep){ // insert with 'wrap' function which provides URL and image handling
            let dtype = _nodep.type;
            res = '{wrap var=$'+varname;
            if(!(_nodep.data.code && _nodep.data.code.indexOf('Relationship')==0))
            {
                const origvalue = inLoop?'':'_originalvalue';
                
                if(_nodep.parent?.type!='enum' && (window.hWin.HEURIST4.util.isempty(dtype) || _nodep.key === 'recURL')){
                    res = res + ' dt="url"';
                }else if(dtype === 'geo'){
                    res = res + origvalue+' dt="'+dtype+'"';
                }else if(dtype === 'date'){
                    res = res + origvalue+' dt="date" mode="0" calendar="native"';
                    
                    remark = remark+' mode: 0-simple,1-full,2-all fields; calendar: native,gregorian,both';
                    
                }else if(dtype === 'file'){
                    res = res + origvalue+' dt="'+dtype+'"';
                    res = res + ' width="300" height="auto" auto_play="0" show_artwork="0"';
                }
            }
            res = res +'}{*' +  remark + '*}';
        }

        return (res+((insertMode==0)?' ':'\n'));
    },

    //
    // returns false if token not found in current and lines until first "if" or "for" above
    //
    _findAboveCursor: function(token) {
        
        //for codemirror
        let crs = this.codeEditor.getCursor();
        //calculate required indent
        let l_no = crs.line;
        let line = "";
        
        token = token.trim();
        
        while (l_no>0){
            line = this.codeEditor.getLine(l_no);
            l_no--;
            if(line.trim()=='') continue;

            if(line.indexOf(token)>=0){
                return true;   
            }
        
            if(line.indexOf("{if")>=0 || line.indexOf("{foreach")>=0){
                return false;   
            }
        }
        
        return false;   
    },
    
    //
    //
    //
    _insertGetRelatedRecords: function(){
        
        //find main loop and {$r = $heurist->getRecord($r)}
        let l_count = this.codeEditor.lineCount();
        let l_no = 0, k = -1;
            
        while (l_no<l_count){
            let line = this.codeEditor.getLine(l_no);
            if(line.indexOf('$heurist->getRelatedRecords($r)}')>0){
                return;//already inserted
            }
            l_no++;
        }
        
        l_no = 0;    
        while (l_no<l_count){
            let line = this.codeEditor.getLine(l_no);
            k = line.indexOf('$heurist->getRecord($r)}');
            if(k>=0){
                
                let s = '\n{$r.Relationships = $heurist->getRelatedRecords($r)}\n'+
                '{$Relationship = (count($r.Relationships)>0)?$r.Relationships[0]:array()}\n';
                
                this.codeEditor.replaceRange(s, {line:l_no, ch:k+24}, {line:l_no, ch:k+24});
                
                break;
            }
            l_no++;
        }
    },
   
    
    _insertPattern: function(){
        
        let pattern_id = Number(this._$('#selInsertPattern').val());
    
        this._closeInsertPopup();
        
        let _text = '';
        let that = this;        

        // Update these patterns in synch with pulldown 
        switch(pattern_id) {

            case 1: // Heading for record type
                _text= "{* Section heading *} \n" +
                "\n{* Make sure your search results are sorted by record type. \n" +
                "   Move the following instruction near the top of the file: {$lastRecordType = 0}\n" +
                "   Modify the sorting variable and the test according to your needs.*} \n\n" +

                "{if $lastRecordType != $r.recTypeID} {$lastRecordType = $r.recTypeID}\n" +
                "      <hr> \n" +
                "      <p/> \n" +
                "      <h1>{$r.recTypeName}</h1> {* Replace this with whatever you want as a heading *} \n" +
                "{/if} {* end of section heading *} " +
                "\n\n";
                break;

            case 2: // simple table
                _text='\n\n{* Put narrow specified-width columns at the start and any long text columns at the end *} \n' +
                '<table style="text-align:left;margin-left:20px;margin-top:2px;" border="0" cellpadding="2"> \n' +
                '   <tr> \n' +
                '      <td style="width: 50px"> {$r.recID}    </td> \n' +
                '      <td style="width:400px"> {$r.recTitle} </td> \n' +
                '      <td style=" "> </td> \n' +
                '      <td style=" "> </td> \n' +
                '      <td style=" "> </td> \n' +
                '   </tr> \n' +
                '</table>' +
                '\n\n';
                break;

            case 3: // information on first element of a loop
                _text='\n\n{* Information before first element of a loop (nothing output if loop is empty). \n' +
                '   Place this before the fields output in the loop. Replace \'valueloop\' with the name of the loop. *}\n\n' +
                '{if $smarty.foreach.valueloop.first}\n' +
                ' \n' +
                ' {* Add the information you want output before the first iteration here *}}\n' +
                ' \n' +
                '{/if}' +
                '\n\n';
                break;

            case 4: // information on first element of a loop
                _text='\n\n{* Information after last element of a loop (nothing output if loop is empty). \n' +
                '   Place this after the fields output in the loop. Replace \'valueloop\' with the name of the loop. *}\n' +
                '{if $smarty.foreach.valueloop.last}\n' +
                ' \n' +
                ' {* Add the information you want output after the last iternation here *}}\n' +
                ' \n' +
                '{/if}' +
                '\n\n';
                break;

            case 5: // using a div to control spacing
                _text=  '\n\n{* You can use style= on divs, spans, table rows and cells etc. to control spacing *} \n' +
                '<div style="padding-top:5px; margin-left:10px;"> \n' +
                '   {* Put content here *} \n' +
                '</div>' +
                '\n\n';
                break;

            case 6: //
                _text='\n\n   TO DO   ' +
                ' content to add here ' +
                '\n\n';
                break;


            case 99: // outer records loop
                _text=  '\n\n{*------------------------------------------------------------*} \n' +
                '{foreach $results as $r} {* Start records loop, do not remove *} \n' +
                '{$r = $heurist->getRecord($r)}\n'+
                '{*------------------------------------------------------------*} \n' +
                ' \n\n' +
                '  {* put the data you want output for each record here - insert the *} \n' +
                '  {* fields using the tree of record types and fields on the right *} \n' +
                ' \n' +
                '<br> {* line break between each record *} \n' +
                ' \n' +
                '{*------------------------------------------------------------*} \n' +
                '{/foreach} {* end records loop, do not remove *} \n' +
                '{*------------------------------------------------------------*} ' +
                '\n\n';
                break;

            case 98: // add record link
                window.hWin.HEURIST4.ui.showRecordActionDialog('recordAdd',{
                    title: 'Select type and other parameters for new record',
                    height: 520, width: 540,
                    get_params_only: true,
                    onClose: function(context){
                        if(context && !window.hWin.HEURIST4.util.isempty(context.RecAddLink)){
                            _text = '\n<a href="'+context.RecAddLink+'&guest_data=1" target="_blank">Add Record</a>\n';
                            that._insertAtCursor(_text); // insert text into editor
                        }
                    },
                    default_palette_class: 'ui-heurist-publish'                                        
                    }
                );    
                
                return;
                
            default:
                _text = 'It appears that this choice has not been implemented. Please ask the Heurist team to add the required pattern';
        }

        this._insertAtCursor(_text); // insert text into editor
        
    },

    //
    //
    //
    _loadRecordTypeTreeView: function(){
        
        let rty_ID = this._$('#rectype_selector').val();

        //load treeview
        let treediv = this._$('.rtt-tree');
        if(!treediv.is(':empty') && treediv.fancytree("instance")){
            treediv.fancytree("destroy");
        }
        
        if(!window.hWin.HEURIST4.util.isPositiveInt(rty_ID)){
            treediv.text('Please select a record type from the pulldown above');
            return;
        }
        
        treediv.empty();
        
        //generate treedata from rectype structure
        let treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 7, rty_ID, 
                        ['ID','title','typeid','typename','modified','url','tags','all','parent_link'] );

        treedata[0].expanded = true; //first expanded

        if(this.options.is_snippet_editor){
            //hide root - record type title
            treedata = treedata[0];
        }
        
        let that = this;

        treediv.fancytree({
            checkbox: false,
            selectMode: 1,  // single
            source: treedata,
            beforeSelect: function(event, data){
                // A node is about to be selected: prevent this, for folder-nodes:
                if( data.node.hasChildren() ){
                    return false;
                }
            },
            lazyLoad: function(event, data){
                let node = data.node;
                let parentcode = node.data.code; 
                let rectypes = node.data.rt_ids;

                let res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 7, 
                    rectypes, ['ID','title','typeid','typename','modified','url','tags','all'], parentcode );

                if(res.length>1){
                    data.result = res;
                }else{
                    data.result = res[0].children;
                }

                return data;                                                   
            },
            loadChildren: function(e, data){
            },
            select: function(e, data) {
            },
            click: function(e, data){

                if(data.node.type == 'separator'){
                    return false;
                }

                let ele = $(e.originalEvent.target);
                if(ele.is('a')){
                    
                    if(ele.text()=='insert'){
                        if(that.options.is_snippet_editor){
                            that._insertSelectedVars2(data.node, 0, false, 0);
                        }else{
                            //insert-popup
                            that._showInsertPopup2( data.node, ele );
                        }
                    }else{
                        that._closeInsertPopup();
                        
                        if(ele.text()=='repeat'){
                            that._insertSelectedVars2( data.node, 1, false );
                        }else if(ele.text()=='if'){
                            that._insertSelectedVars2( data.node, 0, true );
                        }
                    }
                }
                
               
                /*
                if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){
                    data.node.setExpanded(!data.node.isExpanded());
                    //treediv.find('.fancytree-expander').hide();

                }else if( data.node.lazy) {
                    data.node.setExpanded( true );
                }
                */
            },
            renderNode: function(event, data) {
                // Optionally tweak data.node.span
                let node = data.node;

                let $span = $(node.span);
                let new_title = node.title;//debug + '('+node.data.code+'  key='+node.key+  ')';

                if(data.node.type == 'separator'){
                    $(data.node.span).attr('style', 'background: none !important;color: black !important;'); //stop highlighting
                    $(data.node.span.childNodes[1]).hide(); //checkbox for separators
                }else if(node.type!='enum' && node.data.is_rec_fields == null && node.data.is_generic_fields == null){
                    let op = '';
                    if(node.type=='resource' || node.title=='Relationship'){ //resource (record pointer)
                        op = 'repeat';
                    }else if(node.children){
                        op = 'if';
                    }else{
                        op = 'insert';
                    }
                    if(op){
                        new_title = new_title + ' (<a href="#">'+op+'</a>)'; 
                    }
                }

                if(data.node.parent && data.node.parent.type == 'resource' || data.node.parent.type == 'relmarker'){ // add left border+margin
                    $(data.node.li).attr('style', 'border-left: black solid 1px !important;margin-left: 9px;');
                }
                
                $span.find("> span.fancytree-title").html(new_title);
            }            
        });
        
        
    },
    
    //
    //
    //
    _insertAtCursor: function(myValue){

        
        //for codemirror
        let crs = this.codeEditor.getCursor();
        //calculate required indent
        let l_no = crs.line;
        let line = "";
        let indent = 0;

        while (line=="" && l_no>0){
            line = this.codeEditor.getLine(l_no);

            l_no--;
            if(line=="") continue;

            indent = CodeMirror.countColumn(line, null, this.codeEditor.getOption("tabSize"));

            if(line.indexOf("{if")>=0 || line.indexOf("{foreach")>=0){
                indent = indent + 2;
            }
        }

        let off = new Array(indent + 1).join(' ');

        myValue = "\n" + myValue;
        myValue = myValue.replace(/\n/g, "\n"+off);

        this.codeEditor.replaceSelection(myValue);

        if(myValue.indexOf("{if")>=0 || myValue.indexOf("{foreach")>=0){
            crs.line = crs.line+2;
            crs.ch = indent + 2;
            //crs.ch = 0;
        }else{
            crs = this.codeEditor.getCursor();
        }

        this.codeEditor.setCursor(crs);
        let that = this;
        setTimeout(function(){that.codeEditor.focus();},200);
        
    },
    
    //
    //
    //
    _insertSelectedVars2: function( _nodep, inloop, isif, _insertMode, language_code, file_field ){

        let _text = "",
        _varname = '',
        rectypeId = 0,
        key = '',
        _getrec = '',
        language_handle = '',
        file_handle = '';
        
        if(_nodep){
            
            key = _nodep.key;
/*            
code:  rt:dtid   like   10:lt134:12:ids3
key 

id            : "r.f15.f26.term"
labelonly     : "Term"
parent_full_id: "r.f15.f26"
parent_id     : "f26"
this_id       : "term"          

  
*/

                
                _varname = '';
                
                    let codes = _nodep.data.code;
                    if(!codes) codes = key;
                    
                    let prefix = 'r';
                    
                        codes = codes.split(':');
                        
                        if(key.startsWith('rec_')){
                            _varname = key.replace('_','');
                        }
                        
                        if(codes[0]=='Relationship'){ //_nodep.type == 'relationship'){
                            this._insertGetRelatedRecords();
                            if(_varname!='') {
                                if(inloop!=1) inloop = 2; //Relationship will be without prefix $r
                            }else if(codes[1]){
                                _varname = codes[1];
                            }
                            
                            _varname = codes[0]+(_varname!=''?('.'+_varname):'');
                        }else{

                            let offset = 3;
                            let lastcode = codes[codes.length-1];
                                                
                            if(_nodep.type == 'rectype'){
                                rectypeId = _nodep.data.rtyID_local;
                                _varname = '';
                            }else if(!key.startsWith('rec_'))
                            {
                                if(key=='label' || key=='term' || key=='code' || key=='conceptid' || key=='internalid' || key=='desc'){ //terms
                                    if( inloop!=1 ){
                                        _varname = ('.'+key);
                                    }
                                    offset = 4;
                                    lastcode = codes[codes.length-2];
                                }else if (lastcode.indexOf('lt')==0) {
                                    lastcode = lastcode.substring(2);
                                }
                                
                                if(inloop==1 && (_nodep.type == 'date' || _nodep.type == 'geo' || _nodep.type == 'file')){
                                    _varname = '_originalvalue'; //for loop it contains all value
                                }
                                
                                _varname = 'f'+lastcode+_varname;    
                            }
/*
0: "5"   rt
1: "lt15"   -5
2: "10"  rt
3: "lt240"  -3
4: "48"  rt
5: "title"

0: "5"
1: "lt15"  -4
2: "10"
3: "263"
4: "Term"
*/                            
                            if(codes.length>3){ //second level (isif && codes.length==2) || 
                                
                                let parent_key = '';
                                let pkeys = [];
                                while(codes.length-offset>0){
                                    let pkey = codes[codes.length-offset];
                                    if(pkey.indexOf('lt')==0){ //resource
                                        pkey = 'f'+pkey.substring(2);
                                    }else{
                                        pkey = 'f'+pkey;
                                    }
                                    offset = offset + 2;
                                    //prefix = prefix + '.' + pkey;
                                    
                                    pkeys.unshift(pkey);
                                    
                                    if(!parent_key) parent_key = pkey;
                                    if(pkeys.length==2) break;
                                }
                                if(pkeys.length<2) pkeys.unshift(prefix);
                                prefix = pkeys.join('.');
                                //prefix = prefix + '.' + pkey;
                                //prefix = parent_key; 
                                
                                if( inloop<2 ){
                                    
                                    //r.
                                    _getrec = '{$' + parent_key + '=$heurist->getRecord($'+prefix+')}\n';
                                    let _getrec2 = '{$' + parent_key + '=$heurist->getRecord($'+parent_key+')}\n';
                                    //find if above cursor code already has such line             
                                    if(this._findAboveCursor(_getrec) || this._findAboveCursor(_getrec2)) {
                                            _getrec = '';
                                    }
                                    
                                    //_getrec = _getrec+''+_getrec2;
                                    
                                    
                                    _varname = parent_key +  (_varname?('.' + _varname):'');
                                }
                                prefix = '';
                            }
                        }
                    
                    // 0 - outside loop
                    // 1 - insert loop operator
                    // 2 - in loop
                    if( inloop<2 ){
                        _varname = prefix + ((prefix && _varname)?'.':'') + _varname;

                        if(language_code && language_code != '' && (key == 'term' || key == 'desc')){

                            let id_fld = _varname.replace(`.${key}`, '.id');
                            let fld = (inloop==1) ? 'replace_id.id' : id_fld;
                            let trm_fld = key == 'term' ? 'label' : 'desc';

                            language_handle = `{$translated_label = $heurist->getTranslation("trm", $${fld}, "${trm_fld}", "${language_code}")} {* Get translated label *}\n\n`
                                + (inloop==1 ? '\n\t' : '') + `{$translated_label} {* Print translated label *}`;
                        }else if(file_field && _nodep.type == 'file'){

                            let fld = (inloop==1) ? 'replace_id' : _varname;
                            file_handle = `{$file_details = $${fld}_originalvalue|file_data:${file_field}} {* Get the requested field *}\n\n`
                                + (inloop==1 ? '\n\t' : '') + `{$file_details} {* Print the field *}`;
                        }
                    }
                    
                    _nodep.data.varname = _varname;
                    //_nodep.data.key = _varname;
                
            if( inloop==1 ){
                
                //** _getrec = '';
                _text = this._insertPatternMagicLoop(_nodep, _varname, language_handle, file_handle);
                
            }else if(isif){
                
                if(rectypeId>0){
                    _text = this._insertPatternRectypeIf(_nodep, _varname, rectypeId);
                }else{
                    _text = this._insertPatternIfOperator(_nodep, _varname, language_handle, file_handle);    
                }
                
                
            }else{
                _text = this._insertPatternVariable(_nodep, _varname, _insertMode, (inloop==2), language_handle, file_handle);
            }
        
        
            if(_text!=='')    {
                _text = _getrec + _text;
                this._insertAtCursor(_text);
            }
        }
    },


    _closeInsertPopup: function(){
        if(this._addVariableDlg?.dialog('instance')){
            this._addVariableDlg.dialog('close');
        }
    },
    
    
    _showInsertPopup2: function( _nodep, elt ){
        
        let that = this;

        // show hide         
        let no_loop = (_nodep.type=='enum' || _nodep.key.indexOf('rec_')==0 || 
                    (_nodep.data.code && _nodep.data.code.indexOf('Relationship')==0));
        let show_languages = _nodep.key=='term' || _nodep.key=='desc';
        let show_file_data = _nodep.type=='file';
        let h;
        if(no_loop){
            h = 260;
        }else{
            h = 360;
        }

        let field_name = _nodep.data.name;
        if(window.hWin.HEURIST4.util.isempty(field_name)){
            let codes = _nodep.data.code.split(':');

            if(codes.length >= 3){
                let rtyid = codes[codes.length-3];
                let dtyid = codes[codes.length-2];

                field_name = $Db.rst(rtyid, dtyid, 'rst_DisplayName');
            }
        }
        if(window.hWin.HEURIST4.util.isempty(field_name)){
            field_name = 'field';
        }
        
        if(this._addVariableDlg?.dialog('instance')){
            this._addVariableDlg.dialog('close');
        }
        
        function __on_add(event){

            let $ele = $(event.target);
            if($ele.is('strong')){
                $ele = $ele.parent();
            }

            let $dlg2 = $ele.parents('.ui-dialog-content');
            let insertMode = $dlg2.find("#selInsertMode").val();
            let language = $dlg2.find('#selLanguage').val();
            let file_data = $dlg2.find('#selFileData').val();
            
            let bid = $ele.attr('id');
            let inloop = 0;
            
            if(bid=='btn_insert_loop'){
                inloop = 1;
            }else if(bid.indexOf('_loop')>0){
                inloop = 2;
            }
            
            that._insertSelectedVars2(_nodep, inloop, bid.indexOf('_if')>0, insertMode, language, file_data);
            //this._addVariableDlg.dialog('close');
        }
        
        
        function __on_add2(event){

            let $dlg2 = $(event.target).parents('.ui-dialog-content');
            let sel = $dlg2.find("#selInsertModifiers")
            let modname = sel.val();

            if(modname !== ''){
                that._insertAtCursor("|"+modname);
            }

            sel.val('');
        }           
        // init buttons
        let $ele_popup = $('#insert-popup');
        $ele_popup.find('#btn_insert_var').attr('onclick',null).button()
            .off('click')
            .on('click', __on_add);
        $ele_popup.find('#btn_insert_if').attr('onclick',null).button()
            .off('click')
            .on('click', __on_add);
            
        $ele_popup.find('#btn_insert_loop').attr('onclick',null).button()
            .off('click')
            .on('click', __on_add);
        $ele_popup.find('#btn_insert_loop_var').attr('onclick',null).button()
            .off('click')
            .on('click', __on_add);
        $ele_popup.find('#btn_insert_loop_if').attr('onclick',null).button()
            .off('click')
            .on('click', __on_add);
            
        $ele_popup.find('#selInsertModifiers').attr('onchange',null)
            .off('change')
            .on('change', __on_add2);

        let $langSel = $ele_popup.find('#selLanguage');
        if($langSel.find('option').length == 1){ // fill select with available languages

            let lang_opts = window.hWin.HEURIST4.ui.createLanguageSelect();
            $langSel.html($langSel.html() + lang_opts);
        }
        $langSel.val(''); // reset
        h = !show_languages && !show_file_data ? h - 10 : h;
        
        this._addVariableDlg = window.hWin.HEURIST4.msg.showElementAsDialog(   
            {element: $ele_popup[0],
            modal: false,
            width:450,
            height:h,
            resizable: false,
            title:`Insert ${field_name}`,
            buttons:null,
            open: null,
            beforeClose:null,
            close:function(){
                return true; //remove
            },
            position:{my:'top left',at:'bottom left', of: elt},
            borderless: false,
            default_palette_class:null});

        let grid_temp_cols = (!show_languages && !show_file_data ? '' : '75px ') + '130px 180px'

        this._addVariableDlg.find('.insert-field-grid').css({'display': 'grid', 'grid-template-columns': '100%'});
        this._addVariableDlg.find('.insert-field-grid > div:not(.header)').css({'display': 'grid', 'grid-template-columns': grid_temp_cols, 'margin': '5px 0'});
        this._addVariableDlg.find('.insert-field-grid > div.header').css({'display': 'grid', 'grid-template-columns': grid_temp_cols, 'margin': '15px 0 5px'});

        this._addVariableDlg.find('button').css({
            'padding': '0px', 
            'width': '100px', 
            'height': '25px'
        });
        this._addVariableDlg.find('button').not('#btn_insert_var, #btn_insert_loop_var').css('margin-left', '10px');
        this._addVariableDlg.find('#btn_insert_var, #btn_insert_loop_var').css('width', '110px');

        if(no_loop){
            this._addVariableDlg.find('.ins_isloop').hide();
        }else{
            this._addVariableDlg.find('.ins_isloop').show();
        }

        if(show_languages){

            this._addVariableDlg.find('.language_row, .empty_ele').show();
            this._addVariableDlg.find('.file_row').hide();
        }else if(show_file_data){

            this._addVariableDlg.find('.language_row').hide();
            this._addVariableDlg.find('.file_row, .empty_ele').show();
        }else{

            this._addVariableDlg.find('.language_row, .file_row, .empty_ele').hide();
        }
        
    },
    
    //
    //
    //
    isModified: function(){
        return (this._keepTemplateValue && this._keepTemplateValue!=this.codeEditor.getValue());  
    },
    

    _beforeClose: function() {
        if(this.isModified()){
        
            window.hWin.HEURIST4.msg.showMsgOnExit(window.hWin.HR('Warn_Lost_Data'),
                ()=>{this.doAction(false, true);}, //save
                ()=>{this._keepTemplateValue=false; this.closeDialog();}); //ignore and close
           
            return false;
        }else{
            return true;
        }
    },
    

    //    
    //
    //
    _getActionButtons: function(){
        let res = this._super();

        let that = this;
        
        res[0].text = window.hWin.HR('Close');
        
        res[1].text = window.hWin.HR('Save');
        res[1].disabled = null;
        
        if(!this.options.is_snippet_editor){
        res.splice(1,0,{text:window.hWin.HR('Save As'),
                    class:'ui-button-action btnDoAction2',
                    css:{'float':'right'},  
                    click: function() { 
                            that.doAction(true); 
                    }}
                    );
        }
        
        return res;
    },

    //
    // Save template
    //
    doAction: function(is_save_as, need_close){

        let that = this;
        
        if(this.options.is_snippet_editor)
        {
            if(this.isModified()){
                this._context_on_close = this.codeEditor.getValue();    
            }
            this._keepTemplateValue=false;
            this.closeDialog();
            return;    
        }

        if(!this._currentTemplate || is_save_as){

            setTimeout(()=>{    
            window.hWin.HEURIST4.msg.showPrompt('Please enter template name', function(tmp_name){
                if(!window.hWin.HEURIST4.util.isempty(tmp_name)){
                    that._currentTemplate = tmp_name;
                    that._context_on_close = true;
                    that.doAction(false);
                }
                }, {title:'Save template as',yes:'Save as',no:"Cancel"});
            }, is_save_as?10:500);
            return;
        }

        window.hWin.HAPI4.SystemMgr.reportAction({action:'save', 
            template: this._currentTemplate, 
            template_body: this.codeEditor.getValue()
            }, 
            function(response){
                if (response.status == window.hWin.ResponseStatus.OK) {
                    that._keepTemplateValue = that.codeEditor.getValue()
                    that.changeTitle();
                    window.hWin.HEURIST4.msg.showMsgFlash('Report template has been saved');
                    if(need_close){
                        that.closeDialog();
                    }
                } else {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });

    },
    
    //
    // Load limited list of records of given record types (to test template)
    //
    _loadTestRecords: function()
    {
        let selector = this._$('#listRecords')[0];
        selector.innerHTML = '';
        //load list of records for testing 
        if(this.options.rty_ID>0){
            
                const server_request = {
                    q: 't:'+this.options.rty_ID,
                    restapi: 1,
                    columns: ['rec_ID', 'rec_Title'],
                    limit:10,
                    zip: 1,
                    format:'json'};
                    
                
                //search for record type
                window.hWin.HAPI4.RecordMgr.search_new(server_request,
                        function(response){

                           if(window.hWin.HEURIST4.util.isJSON(response)) {
                               let options = [];
                               response.records.forEach((item) => {
                                    options.push({key:item.rec_ID, 
                                    title:window.hWin.HEURIST4.util.stripTags(item.rec_Title)});
                               });
                               window.hWin.HEURIST4.ui.createSelector(selector, options);
                           }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                           }
                });            
        }
    }    
        
});

