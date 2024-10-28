/**
* reportViewer - smarty report viewer
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


$.widget( "heurist.reportViewer", {

    // default options
    options: {

        eventbased:true, //if false it does not listen global events
        search_realm: null,
        search_initial: null,  //Query or svs_ID for initial search
        recordset: null,
        language: 'def'
    },

    _$: $, //shorthand for this.element.find
    _need_load_content: true,
    _currentTemplate: null,
    _tempForm: null,
    _last_disabled_message: null,
    
    // the widget's constructor
    _create: function() {
        
        this._$ = selector => this.element.find(selector);
        

        this.element
        // prevent double click to select text
        .disableSelection();
    
        this._loadContent();    

        //this._refresh();
        
    }, //end _create

    _init: function(){
    },

    
    //
    //
    //    
    _loadContent:function(){
        if(this._need_load_content){  //load general layout      
            this._need_load_content = false;

            let url = window.hWin.HAPI4.baseURL+'hclient/widgets/report/reportViewer.html';
            
            //let container = $('<div>').appendTo(this.element);
            let that = this;
            this.element.load(url, 
            function(){
                that._initControls();
            });
            
            this.element.on("myOnShowEvent", function(event){
                if( event.target.id == that.element.attr('id')){
                    that.executeTemplate();
                }
            });
        }            
    },
    
    
    /* 
    * show/hide buttons depends on current credentials
    */
    _refresh: function(){

    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        
        this.element.off("myOnShowEvent");
        
        // remove generated elements
        if(this._events){
            $(this.document).off(this._events);    
        }
        
        let file_upload = this._$('#fileupload')
        
        if(file_upload.fileupload('instance')){
           file_upload.fileupload('destroy');
        }
        
        if(this._tempForm){
            this._tempForm.remove();
        }
    },
    
    _initControls: function(){
        this._updateTemplatesList();    
        this._initToolbar();    

        
        if(!this.options.eventbased){
            return;
        }
        
        this._events = window.hWin.HAPI4.Event.ON_CREDENTIALS 
        + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
        + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
        + ' ' + window.hWin.HAPI4.Event.ON_REC_SELECT;

        let that = this;
        
        $(this.document).on(this._events, function(e, data) {

            if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS)
            {
                if(!window.hWin.HAPI4.has_access()){ //logout
                    that.options.recordset = null;
                    that._refresh();
                }
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){ 

                if(!that._isSameRealm(data)) return;
                
                that.options.recordset = data.recordset; //HRecordSet
                that.executeTemplate();

            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){

                if(!that._isSameRealm(data)) return;

            //}else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){
                
            }
        });
    },
    
    _initToolbar: function() {
        let that = this;
        $.each(this._$('.toolbar > button'), (i, item)=>{
            that._on($(item).button({showLabel:false, icon: "ui-icon-"+$(item).attr('data-icon')}),
                {click: that._handleToolbarAction});
        });
        this._$('button[data-action="import"]').find('span.ui-icon').css({'transform':'rotate(180deg)','margin-top':'-9px'});        
    },
    
    _handleToolbarAction: function(event){
        
        let ele = $(event.target);
        if(!ele.is('button')) ele = ele.parent('button');
        
        let action = ele.attr('data-action');
        
        switch ( action ) {
            case 'edit':
                this.onTemplateEdit(false);
                break;
            case 'new':
                this.onTemplateEdit(true);
                break;
            case 'delete':
                this.onTemplateDelete();
                break;
            case 'import':
                this.onTemplateImport();
                break;
            case 'export':
                this.onTemplateExport();
                break;
            case 'publish':
                this.onTemplatePublish();
                break;
            case 'print':
                this.onTemplatePrint();
                break;
            case 'refresh':
                this.onRefresh();
                break;
        }
        
    },

    //
    // Show popup with template editor
    //
    onTemplateEdit: function(isNew) {
        let that = this;
        
        let popup_dialog_options = {path: 'widgets/report/', 
                    keep_instance:true, 
                    template: isNew?null:this._currentTemplate,
                    
                    onClose: function(is_update_list){
                        if(is_update_list){
                            that._updateTemplatesList();
                        }
                    }
        };
        window.hWin.HEURIST4.ui.showRecordActionDialog('reportEditor', popup_dialog_options);
    },

    //
    //
    //
    onTemplateDelete: function(unconditionally) {

        let that = this;

        if(unconditionally===true){

            window.hWin.HAPI4.SystemMgr.reportAction({action:'delete', template:this._currentTemplate}, 
                function(response){
                    if (response.status == window.hWin.ResponseStatus.OK) {
                        window.hWin.HAPI4.SystemMgr.save_prefs({'viewerCurrentTemplate': null});
                        that._currentTemplate = null;
                        that._updateTemplatesList();
                    } else {
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
            });

        }else{
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete template "'+this._currentTemplate+'"?', 
                function(){ that.onTemplateDelete(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'});        
        }
    },

    //
    //
    //
    onTemplateImport: function() {
        
        let file_upload = this._$('#fileupload')
        
        if(!file_upload.fileupload('instance')){
            
            let that = this;
            
            let baseurl = window.hWin.HAPI4.baseURL + "hserv/controller/index.php";
        
            file_upload.fileupload({
                    url: baseurl,
                    formData: [{name:'db', value: window.hWin.HAPI4.database}, {name:'controller', value:'ReportController'}, {name:'action', value:'import'}],
                    dataType: 'json',
                    done: function (e, response) {

                        if(response.result){//file upload place our respose to 'result'
                            response = response.result;
                        }
                        
                        if(response.status==window.hWin.ResponseStatus.OK){

                            let data = response.data;
                                                        
                            that._updateTemplatesList();
                            //open editor
                            that._currentTemplate = data?.filename;
                            that.onTemplateEdit(false);
                            
                            if(data?.details_not_found){

                                const list_of_notfound = data.details_not_found.join(', ');
                                
                                window.hWin.HEURIST4.msg.showMsgDlg(
'Unable to convert IDs for following concept codes: '+list_of_notfound
+'<p style="padding-top:1.5em">Concept IDs which failed to convert are enclosed in [[  ]] in the template file eg. f[[2-27]]. You will need to edit the template in order to remove these IDs or to replace them with the internal code of an equivalent concept.</p>'
+'<p style="padding-top:1.5em">Failure to convert Concept IDs (global codes for particular record types, fields and terms) to local codes indicates that the Concept IDs are not known within your database.</p>'
+'<p style="padding-top:1.5em">You may wish to import the missing concepts using Database > Structure > Import from databases. The first part of the code indicates the database in which the concept was originally defined.</p>'
                                ,null,'Conversion of template file to internal codes');
                            }
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    }
                });        
        }
        file_upload.trigger('click');
    },

    //
    // Converts template to global (all local field ids will be replaced with concept codes)
    //
    onTemplateExport: function() {
        
        if(!window.hWin.HEURIST4.util.isPositiveInt(  window.hWin.HAPI4.sysinfo['db_registeredid'] )){
            window.hWin.HEURIST4.msg.showMsgErr({
                message: 'Database must be registered to allow translation of local template to global template.',
                error_title: 'Cannot convert to global template'
            });
            return;
        }

        let template_file = this._$('#selTemplates').val();
        if( window.hWin.HEURIST4.util.isempty(template_file) ) return;
        
        window.hWin.HAPI4.SystemMgr.reportAction({action:'export', check:1 ,template:template_file}, 
            function(response){
                if (response.status == window.hWin.ResponseStatus.OK) {
                    let baseurl = window.hWin.HAPI4.baseURL + "hserv/controller/index.php";
                    let squery = 'db='+window.hWin.HAPI4.database+'&action=export&template='+template_file;
                    window.hWin.HEURIST4.util.downloadURL(baseurl+'?'+squery);
                } else {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });        

        
    },

    //
    //
    //
    onRefresh: function() {

        //keep current template    
        let template_file = this._$('#selTemplates').val();
        
        //remove editor
        let ele = $('#heurist-dialog-reportEditor');
        if(ele.length>0){
            if(ele.reportEditor('instance')){
                ele.reportEditor('destroy');
            }
            ele.remove();
        }
        
        //update list
        this._updateTemplatesList( template_file );
        
        //restart template
        this.executeTemplate( template_file );
        
    },

    //
    //
    //
    onTemplatePublish: function() {
        
        let template_file = $('#selTemplates').val();
        if(window.hWin.HEURIST4.util.isempty(template_file)) return;
        
         
        let request = window.hWin.HEURIST4.util.cloneJSON(this._currentQuery
                    ?this._currentQuery :window.hWin.HEURIST4.current_query_request);
        
        //let mode = window.hWin.HAPI4.get_prefs('showSelectedOnlyOnMapAndSmarty'); //not used
        let squery = window.hWin.HEURIST4.query.composeHeuristQueryFromRequest( request, true );

        let q = 'hquery='+encodeURIComponent(squery)+'&template='+template_file;
        
        
        let params = {mode:'smarty'};
        params.url_schedule = window.hWin.HAPI4.baseURL + "export/publish/manageReports.html?"
                                    + q + "&db="+window.hWin.HAPI4.database;

        params.url = window.hWin.HAPI4.baseURL + "?"+ 
            squery.replace('"','%22') + '&template='+encodeURIComponent(template_file);
        
        
        window.hWin.HEURIST4.ui.showPublishDialog( params );
    },

    //
    //
    //
    onTemplatePrint: function() {
        try{
            let oIframe = this._$("#ifrmPrint")[0];
            let iframe = this._$("#rep_container_frame")[0];
            let iframeDocument = iframe.contentDocument || iframe.contentWindow.document;
            let oContent = iframeDocument.body.innerHTML;
            //.innerHTML
            let oDoc = (oIframe.contentWindow || oIframe.contentDocument);
            if (oDoc.document) oDoc = oDoc.document;
            oDoc.write("<head><title></title>");
            oDoc.write("</head><body onload=\"this.focus(); this.print();\">");
            oDoc.write(oContent);
            oDoc.write('</body>');
            oDoc.close();
        }
        catch(e){
            console.error(e)
        }
    },

    
    _updateTemplatesList: function(template_to_select) {
        
        this._currentTemplate = template_to_select || window.hWin.HAPI4.get_prefs('viewerCurrentTemplate');
        
        let sel = this._$('#selTemplates');
        sel.empty();
        this._off(sel,'change');
        
        window.hWin.HEURIST4.ui.createTemplateSelector(sel, null, this._currentTemplate, null);
        
        this._on(sel,{change:(event)=>{
                let template_file = $(event.target).val();
                window.hWin.HAPI4.SystemMgr.save_prefs({'viewerCurrentTemplate': template_file});
                this.executeTemplate(template_file);
        }});
        
    },        
    
    //
    //
    //
    _isSameRealm: function(data){
        return (!this.options.search_realm && (!data || window.hWin.HEURIST4.util.isempty(data.search_realm)))
        ||
        (this.options.search_realm && (data && this.options.search_realm==data.search_realm));
    },
    
    assignRecordsetAndQuery: function(recordset, query_request, facet_value = null){
        this._currentRecordset = recordset;
        this._currentQuery = query_request;
        this._facet_value = facet_value;
        
    },

    //
    //
    //
    executeTemplate: function(template_file){
        
        if(!this.element.is(':visible')){
              return;
        }

        if(window.hWin.HEURIST4.msg._progressInterval>0){
            window.hWin.HEURIST4.msg.showMsgFlash('Previous report is not completed yet');
            return;   
        }
        
        if(window.hWin.HEURIST4.util.isnull(template_file)){
            template_file = this._$('#selTemplates').val();    
        }
        this._currentTemplate = template_file;

        let _currentRecordset = this._currentRecordset?this._currentRecordset :window.hWin.HAPI4.currentRecordset;
        //this.options.recordset;

        if(_currentRecordset==null){
            return;   
        }
        
        if(!template_file){
            return;    
        }

        //limit to  records  smarty-output-limit
        let recset = _currentRecordset.getIds();
        let rec_count = _currentRecordset.count_total();
        //rec_count = recset['recIDs'].length;
        
        let limit = window.hWin.HAPI4.get_prefs_def('smarty-output-limit',50);
        if(limit>2000) limit = 2000;
        
        if(limit<recset.length){
            recset = {recIDs:_currentRecordset.getIds().slice(0, limit-1), recordCount:rec_count};
        }else{
            recset = {recIDs:_currentRecordset.getIds()};    
        }
        rec_count = recset.recIDs.length;

        let request = {db:window.hWin.HAPI4.database, 
                       publish: 0, 
                       action: 'execute', 
                       template:template_file, 
                       recordset:'1'}; //JSON.stringify(recset)};
                       
        if(this._facet_value){
            request['facet_val'] = this._facet_value;
        }
        if(rec_count>500){
            request['session'] = window.hWin.HEURIST4.msg.showProgress();
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._$('#rep_container'));
        
        let inputs = '';
        for (let [key, value] of Object.entries(request)) {
          inputs += `<input type="hidden" name="${key}" value="${value}"/>`;
        }       
        
        if(this._tempForm){
            this._tempForm.empty();
        }else{
            const url = window.hWin.HAPI4.baseURL+'hserv/controller/index.php';
            this._tempForm = $(`<form target="rep_container_frame" action="${url}" method="post"></form>`)
                .appendTo(this.element);
                
            this._on(this._$('#rep_container_frame'),{load:()=>{
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                window.hWin.HEURIST4.msg.hideProgress();
                
                this._showWarningAboutDisabledFunction();
            }});
        }
        
        this._tempForm.html(inputs);
        
        this._tempForm.find('input[name="recordset"]').val(JSON.stringify(recset));
        this._tempForm.trigger('submit');
    },
    
    
    _showWarningAboutDisabledFunction: function(){    
        
        let txt = this._$('#rep_container_frame')[0].contentDocument.body.innerHTML;
        
        if(this._last_disabled_message != txt && 
            window.hWin.HEURIST4.msg.showWarningAboutDisabledFunction(txt)){
            this._last_disabled_message = txt;
        }
        
    },
    

});
