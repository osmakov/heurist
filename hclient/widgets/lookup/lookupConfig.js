/**
* lookupConfig.js - configuration for record lookup services
*                       original config is hserv/controller/record_lookup_config.json
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
/* global stringifyMultiWKT, accessToken_GeonamesAPI */

$.widget( "heurist.lookupConfig", {

    // default options
    options: {
    
        //DIALOG section       
        isdialog: false,     // show as dialog @see  _initDialog(), popupDialog(), closeDialog
        height: 640,
        width:  900,
        modal:  true,
        title:  '',
        htmlContent: 'lookupConfig.html',
        helpContent: null,
        
        //parameters
        service_config: {}, // all assigned services
        
        //listeners
        onInitFinished:null,  //event listener when dialog is fully inited - use to perform initial search with specific parameters
        beforeClose:null,     //to show warning before close
        onClose:null       
    },

    _urls: null,
    
    _as_dialog:null, //reference to itself as dialog (see options.isdialog)
    
    _need_load_content:true,
    
    _current_cfg: null, // current set service details
    _is_modified: false, // is current service modified
    _available_services:null, // list of available services
    _services_modified: false, // has any service been removed/added
    _isNewCfg: false,

    //controls
    selectRecordType:null, //selector for rectypes
    selectServiceType: null, //selector for lookup service types
    serviceList: null, //left panel list

    example_results: {},    
    
    save_btn: null,
    close_btn: null,
    
    // the widget's constructor
    _create: function() {
        // prevent double click to select text
        //it prevents inputs in FF this.element.disableSelection();
    }, //end _create
    
    //
    //  load configuration and call _initControls
    //
    _init: function() {

        this._urls = {
            tlcmap: {
                lookup: 'https://tlcmap.org/ghap/search?format=csv&paging=10&fuzzyname=London',
                service: 'https://ghap.tlcmap.org/places?containsname=London&searchausgaz=on&searchncg=on&searchpublicdatasets=on'
            },
            geoName: {
                lookup: `http://api.geonames.org/searchJSON?username=${accessToken_GeonamesAPI}&maxRows=10&q=London`,
                service: 'https://www.geonames.org/search.html?q=London&country='
            },
            postalCodeSearch: {
                lookup: `http://api.geonames.org/postalCodeLookupJSON?username=${accessToken_GeonamesAPI}&maxRows=10&placename=London`,
                service: 'https://www.geonames.org/postalcode-search.html?q=London&country='
            },
            bnfLibrary: {
                lookup: `https://catalogue.bnf.fr/api/SRU?version=1.2&operation=searchRetrieve&maximumRecords=10&startRecord=1&query=${encodeURIComponent('(bib.anywhere any "Vincent")')}`,
                service: 'https://catalogue.bnf.fr/rechercher.do?motRecherche=Vincent&critereRecherche=0&depart=0&facetteModifiee=ok'
            },
            bnfLibraryAut: {
                lookup: `https://catalogue.bnf.fr/api/SRU?version=1.2&operation=searchRetrieve&recordSchema=unimarcxchange&maximumRecords=10&startRecord=1&query=${encodeURIComponent('(aut.anywhere any "Vincent")')}`,
                service: 'https://catalogue.bnf.fr/resultats-auteur.do?nomAuteur=Vincent&filtre=1&pageRech=rau'
            },
            nomisma: {
                lookup: {
                    getMints: 'https://nomisma.org/apis/getMints?id=denarius',
                    getHoards: 'https://nomisma.org/apis/getHoards?id=denarius',
                    getFindspots: 'https://nomisma.org/apis/getFindspots?id=denarius'
                },
                service: 'https://nomisma.org/browse?q=denarius'
            },
            nakala: {
                lookup: 'https://api.nakala.fr/search?q=Literature&fq=scope%3Ddatas&order=relevance&page=1&size=15',
                service: 'https://nakala.fr/search/?q=Literature'
            },
            nakala_author: {
                lookup: 'https://api.nakala.fr/authors/search?q=John&order=asc&page=1&limit=15',
                service: 'https://nakala.fr/'
            }
        };
        
        this._available_services = window.hWin.HAPI4.sysinfo['services_list'];
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(this._available_services)){
            window.hWin.HEURIST4.msg.showMsgErr('There are no available services, or the configuration file was not found or is broken');
            return;
        }
        
        let that = this;

        this.options.service_config = window.hWin.HEURIST4.util.isJSON(this.options.service_config);
        if(!this.options.service_config){ // Invalid value / None
            this.options.service_config = {};    
        } 
        
        this.element.addClass('ui-heurist-design');
        
        if(this.options.isdialog){  //show this widget as popup dialog
            this._initDialog();
        }
        
        //load html from file
        if(this._need_load_content && this.options.htmlContent){        
            this.element.load(window.hWin.HAPI4.baseURL+'hclient/widgets/lookup/'+this.options.htmlContent
                            +'?t='+window.hWin.HEURIST4.util.random(), 
            function(response, status, xhr){
                that._need_load_content = false;
                if ( status == "error" ) {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }else{
                    if(that._initControls()){
                        if($.isFunction(that.options.onInitFinished)){
                            that.options.onInitFinished.call(that);
                        }        
                    }
                }
            });
            return;
        }else{
            if(that._initControls()){
                if($.isFunction(that.options.onInitFinished)){
                    that.options.onInitFinished.call(that);
                }        
            }
        }
    },
    
    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    //
    _setOptions: function( ) {
        this._superApply( arguments );
    },

    /* 
    * private function 
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

    },

    // 
    // custom, widget-specific, cleanup.
    //
    _destroy: function() {
        // remove generated elements
        if(this.selectRecordType) this.selectRecordType.remove();
        if(this.serviceList) this.serviceList.remove();

    },
    
    //
    // array of button defintions
    //
    _getActionButtons: function(){

        let that = this;

        return [
            {
                text:window.hWin.HR('Close'), 
                id:'btnClose',
                css:{'float':'right','margin-left':'30px'}, 
                click: function() { 
                    that._closeHandler(false, false, null);
                }
            },
            {
                text:window.hWin.HR('Save'),
                id:'btnSave',
                css:{'float':'right'},
                click: function() {
                    that._closeHandler(true, false, null);
                },
                class: "ui-button-action"
            }
        ];
    },

    //
    // init dialog widget
    // see also popupDialog, closeDialog 
    //
    _initDialog: function(){
        
        let options = this.options,
            btn_array = this._getActionButtons(), 
            position = null;
        const that = this;
    
        if(!options.beforeClose){
                options.beforeClose = function(){
                    return true;
                };
        }
        
        if(position==null) position = { my: "center", at: "center", of: window };
        let maxw = (window.hWin?window.hWin.innerWidth:window.innerWidth);
        if(options['width']>maxw) options['width'] = maxw*0.95;
        let maxh = (window.hWin?window.hWin.innerHeight:window.innerHeight);
        if(options['height']>maxh) options['height'] = maxh*0.95;
        
        let $dlg = this.element.dialog({
            autoOpen: false ,
            //element: this.element[0],
            height: options['height'],
            width:  options['width'],
            modal:  (options['modal']!==false),
            title: 'Lookup services configuration',
            position: position,
            beforeClose: options.beforeClose,
            resizeStop: function( event, ui ) {//fix bug
                that.element.css({overflow: 'none !important','width':that.element.parent().width()-24 });
            },
            close:function(){
                if($.isFunction(that.options.onClose)){
                  //that.options.onClose(that._currentEditRecordset);  
                  that.options.onClose( that.options.service_config );
                } 
                that._as_dialog.remove();    
                    
            },
            buttons: btn_array
        }); 
        this._as_dialog = $dlg; 
        
        $dlg.parent().addClass('ui-dialog-heurist ui-heurist-design');
    },
    
    //
    // show itself as popup dialog
    //
    popupDialog: function(){
        if(this.options.isdialog){

            this._as_dialog.dialog("open");
            
            if(this.options.helpContent){
                let helpURL = window.hWin.HRes( this.options.helpContent )+' #content';
                window.hWin.HEURIST4.ui.initDialogHintButtons(this._as_dialog, null, helpURL, false);    
            }
            
            this.save_btn = this._as_dialog.find('#btnSave');
            this.close_btn = this._as_dialog.find('#btnClose');
        }
    },
    
    //
    // close dialog
    //
    closeDialog: function(is_force){
        if(this.options.isdialog){
            
            if(is_force===true){
                this._as_dialog.dialog('option','beforeClose',null);
            }
            
            this._as_dialog.dialog("close");
        }
    },
    
    
    
     
    //  
    // invoked from _init after loading of html content
    //
    _initControls:function(){
        
        let that = this;

        // check that all assigned services contain valid details
        this.updateOldConfigurations();

        //fill record type selector
        this.selectRecordType = this.element.find('#sel_rectype').css({'list-style-type': 'none'});
        this.selectRecordType = window.hWin.HEURIST4.ui.createRectypeSelectNew(this.selectRecordType.get(0),
            {topOptions:'select record type'});
        // on change handler
        this._on(this.selectRecordType, { change: this._onRectypeChange });
        
        //fill service configuration list
        this.serviceList = this.element.find('#sel_service');
        this._reloadServiceList();
        // on selected handler
        this.serviceList.selectable( {
            cancel: '.ui-icon-circle-b-close',  // service delete "button"
            selected: function( event, ui ) {
                if($(ui.selected).is('li')){
                    
                    if($(ui.selected).hasClass('unfinished')){
                        window.hWin.HEURIST4.msg.showMsgFlash('Complete or discard unfinished one',700);
                        return;  
                    }

                    that.serviceList.find('li').removeClass('ui-state-active');
                    that.serviceList.find('div').removeClass('ui-state-active');
                    $(ui.selected).addClass('ui-state-active');

                    //load configuration into right hand form
                    that._fillConfigForm( $(ui.selected).attr('data-service-id') );
                }
            }
        });

        //fill service types   (selector)
        this.selectServiceType = this.element.find('#sel_servicetype').css({'list-style-type': 'none'});
        this._getServiceSelectmenu();
        // on change handler
        this._on(this.selectServiceType[0], {
            change: function(event, ui){

                let service = that.selectServiceType.val(); // selected service

                if(service == 'ESTC_editions' || service == 'ESTC_works' || service == 'ESTC'){
                    let req = {
                        a: 'check_allow_estc',
                        db: window.hWin.HAPI4.database,
                        ver: service
                    };
                    window.hWin.HAPI4.SystemMgr.check_allow_estc(req, function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            that._changeService( service ); // setup
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                            return false;
                        }
                    });
                }else{
                    that._changeService( service ); // setup
                }
            }
        });

        let ele = this.element.find('#inpt_label');
        this._on(ele, {input: this._updateStatus });

        ele = this.element.find('#btnAddService').button({ icon: "ui-icon-plus" }).css('left', '165px');
        this._on(ele, {click: this._addNewService});

        this.btnApply = this.element.find('#btnApplyCfg').button().css("margin-right", "10px");
        this._on(this.btnApply, {click: this._applyConfig});            

        this.btnDiscard = this.element.find('#btnDiscard').button().hide();
        this._on(this.btnDiscard, {click: function(){this._removeConfig(null)}});            

        this._updateStatus();
        
        if(this.options.isdialog){
            
            this.element.find('.popup_buttons_div, .ui-heurist-header').hide();
            this.element.find('div.ent_content').toggleClass(['ent_content', 'ent_content_full']).css('top', '-0.2em');

            this.popupDialog();
        }else{

            // add title/heading
            this.element.find('.ui-heurist-header').text(this.options.title);

            // bottom bar buttons
            this.save_btn = this.element.find('#btnSave').button().on('click', function() {that._closeHandler(true, false, null);} );
            this.close_btn = this.element.find('#btnClose').button().on('click', function() {that._closeHandler(false, false, null);} );

            // mouse leaves container
            this.element.find('.ent_wrapper:first').on('mouseleave', function(event) {

                if($(event.target).is('div') && (that._is_modified || that._services_modified) && !that._isNewCfg){
                    that._closeHandler(false, true, $(event.target));
                }
            } );
        }
        
        window.hWin.HEURIST4.util.setDisabled(this.save_btn, !this._services_modified);
        //show hide hints and helps according to current level
        window.hWin.HEURIST4.ui.applyCompetencyLevel(-1, this.element); 

        this._on(this.element.find('#example_records .ui-icon'), {
            'click': function(event){

                let idx = that.element.find('#tbl_matches').attr('data-idx');
                let service = that.selectServiceType.val();
                let max = 0;

                if(window.hWin.HEURIST4.util.isArray(that.example_results[service])){
                    max = that.example_results[service].length - 1;
                }else if(window.hWin.HEURIST4.util.isPlainObject(that.example_results[service])){
                    max = Object.keys(that.example_results[service]).length - 1;
                }

                if($(event.target).hasClass('ui-icon-arrowthick-1-e')){
                    idx = idx == max ? 0 : parseInt(idx) + 1;
                }else{
                    idx = idx == 0 ? max : parseInt(idx) - 1;
                }

                that.element.find('#current_idx').text(parseInt(idx)+1);
                that.element.find('#tbl_matches').attr('data-idx', idx);

                that._displayTestResults(service);
            }
        });

        let rpanel_width = this.element.find('#editing_panel').width() - 40;
        this.element.find('#service_mapping').width(rpanel_width);

        return true;
    },

    updateOldConfigurations: function(){

        let that = this;
        let has_changes = false;

        $.each(this.options.service_config, function(key, value){

            // Check that the service property has been defined
            if(that.options.service_config[key]['service'] == null){

                // Likely has service_name instead
                if(that.options.service_config[key]['service_name'] != null){
                    that.options.service_config[key]['service'] = that.options.service_config[key]['service_name'];
                    delete that.options.service_config[key]['service_name'];
                }else{ // invalid configuration, missing a service name
                    delete that.options.service_config[key];
                }

                has_changes = true;
            }else if(that.options.service_config[key]['service_name'] != null){
                delete that.options.service_config[key]['service_name'];

                has_changes = true;
            }

            // Check that the service id (serviceName_rtyID) has been defined
            if(value.service_id == null){
                that.options.service_config[key]['service_id'] = that.options.service_config[key]['service'] + '_' + that.options.service_config[key]['rty_ID'];

                has_changes = true;
            }

            if(that.options.service_config[key]['dialog'] == 'recordLookup' || that.options.service_config[key]['dialog'] == 'lookupTCL'){
                that.options.service_config[key]['dialog'] = 'lookupTLC';

                has_changes = true;
            }else if(that.options.service_config[key]['dialog'] == 'recordLookupBnFLibrary' || that.options.service_config[key]['dialog'] == 'lookupBnFLibrary'){
                that.options.service_config[key]['dialog'] = 'lookupBnFLibrary_bib';

                has_changes = true;
            }else if(that.options.service_config[key]['dialog'].includes('recordLookup')){
                that.options.service_config[key]['dialog'] = that.options.service_config[key]['dialog'].replace('recordLookup', 'lookup');

                has_changes = true;
            }

            // Update configurations (Add missing fields, additional options, remove fields no longer handled)
            let n_fields = that.options.service_config[key]['fields'];
            let service_details = that._available_services.find((service) => service['service'] == that.options.service_config[key]['service']);

            let fld_removes = Object.keys(n_fields).filter((key) => !Object.hasOwn(service_details['fields'], key));

            if(fld_removes.length > 0){ // remove fields not part of configuration

                for(let field of fld_removes){
                    delete n_fields[field];
                }

                that.options.service_config[key]['fields'] = n_fields;

                has_changes = true;
            }

            // Add missing fields
            let has_field_changes = false;
            for(let field in service_details['fields']){
                if(Object.hasOwn(n_fields, field)){
                    continue;
                }

                n_fields[field] = service_details['fields'][field];

                has_field_changes = true;
            }

            if(has_field_changes){
                that.options.service_config[key]['fields'] = n_fields;

                has_changes = true;
            }

            if(that.options.service_config[key]['service'] == 'bnfLibrary'){

                if(!Object.hasOwn(that.options.service_config[key], 'options')){ // add default options

                    that.options.service_config[key]['options'] = {
                        'author_codes': '', //'contributor_codes': ''
                        'dump_receord': true,
                        'dump_field': 'rec_ScratchPad'
                    };
                    has_changes = true;
                }
            }else if(that.options.service_config[key]['service'] == "bnfLibraryAut"){

                if(!Object.hasOwn(that.options.service_config[key], 'options')){ // add default options
                    that.options.service_config[key]['options'] = {
                        'dump_receord': true,
                        'dump_field': 'rec_ScratchPad'
                    };
                    has_changes = true;
                }
            }

            // Correct service's key (to allow the service to be assigned to multiple record types)
            if(key.includes("_") === false){

                let new_key = that.options.service_config[key]['service_id'];
                that.options.service_config[new_key] = window.hWin.HEURIST4.util.cloneJSON(that.options.service_config[key]);

                delete that.options.service_config[key];

                has_changes = true;
            }
        });

        // Update with new changes
        if(has_changes){
            this.saveConfigrations();            
        }
    },

    saveConfigrations: function(){

        let that = this;

        let fields = {
            'sys_ID': 1,
            'sys_ExternalReferenceLookups': JSON.stringify(this.options.service_config)
        };

        // Update sysIdentification record
        let request = {
            'a': 'save',
            'entity': 'sysIdentification',
            'request_id': window.hWin.HEURIST4.util.random(),
            'isfull': 0,
            'fields': fields
        };

        window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){

            if(response.status == window.hWin.ResponseStatus.OK){
                window.hWin.HAPI4.sysinfo['service_config'] = window.hWin.HEURIST4.util.cloneJSON(that.options.service_config); // update local copy

                that._is_modified = false;
                that._services_modified = false;

                window.hWin.HEURIST4.util.setDisabled(that.save_btn, !that._services_modified);
                window.hWin.HEURIST4.msg.showMsgFlash('Saved lookup configurations...', 3000);
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    },

    //
    //
    //
    _closeHandler: function(isSave=false, isMouseLeave=false, trigger){

        let that = this;

        let hasChanges = (this._is_modified || this._services_modified);

        let $dlg, buttons = {};

        buttons['Save'] = function(){
            
            if(that._is_modified){
                that._applyConfig();
            }

            $dlg.dialog('close');

            // Update sysIdentification record
            that.saveConfigrations();
        };

        buttons['Ignore and close'] = function(){ 
            $dlg.dialog('close');
            that.element.empty().hide();
        };

        if(!isSave && trigger && !trigger.is('button') && hasChanges){

            let wording = this._is_modified ? 'current configuration' : 'available services';
            let button = this._is_modified ? '"Apply"' : '"Save"'

            $dlg = window.hWin.HEURIST4.msg.showMsgDlg('You have made changes to the '+wording+'. Click '+button+' otherwise all changes will be lost.', 
                buttons, {title: 'Unsaved Changes', yes: 'Save', no: 'Ignore and Close'});
        }else{
            if(isSave){
                this.saveConfigrations();
            }else{
                if(this.options.isdialog && this._as_dialog.dialog('instance') !== undefined){
                    this._as_dialog.dialog('close'); // this.closeDialog(true);
                }else{
                    this.element.empty().hide();
                }
            }
        }
    },

    //
    // Get list of available services
    //
    _getServiceSelectmenu: function(){

        let options = [];

        let values = {};

        for(let idx in this._available_services){

            values = {
                title: this._available_services[idx].label,
                key: this._available_services[idx].service,
                disabled: false,
                selected: false,
                hidden: false
            };

            options.push(values);
        }

        options.sort((a, b) => { return a.title > b.title; });

        values = {
            title: 'select a service...',
            key: '',
            disabled: true,
            selected: true,
            hidden: true
        }; // top option

        options.unshift(values);

        this.selectServiceType = window.hWin.HEURIST4.ui.createSelector(this.selectServiceType.get(0), options); // create dropdown
        this.selectServiceType = $(this.selectServiceType);
        window.hWin.HEURIST4.ui.initHSelect(this.selectServiceType, false); // initial selectmenu

        if(this.selectServiceType.hSelect('instance')!=undefined){

            this.selectServiceType.hSelect('widget').css('width', 'auto');
            this.selectServiceType.hSelect('menuWidget').css('max-height', ''); // remove to force dropdown to scroll
        }

    },    

    //
    // prepare forms for new service
    //
    _addNewService: function(){

        if(this._isNewCfg){
            window.hWin.HEURIST4.msg.showMsgFlash('Complete or discard unfinished one',700);
            return;
        }
        
        // empty all inputs
        this.serviceList.find('li').removeClass('ui-state-active');
        

        // empty control variables
        this._fillConfigForm('new');
        
        let ele = this._reloadServiceList_item( 'new', 'assign on right ...' );
    },
    
    //
    // fill in contents of right panel
    //    
    _fillConfigForm: function( service_id, cfg0 ){
        
        if(service_id && this.options.service_config[service_id]){
            cfg0 = this.options.service_config[service_id];
        }

        if( cfg0 ){

            this._current_cfg = cfg0;
            
            this.element.find('#service_name').html(cfg0.label);
            this.element.find('#service_description').html('<strong>' + cfg0.service + '</strong>: ' + cfg0.description);
            this.element.find('#inpt_label').val(cfg0.label);
            
            let tbl = this.element.find('#tbl_matches');
            tbl.empty();

            $.each(this._current_cfg.fields, function(field, code){
                $('<tr><td>'+field+'</td><td><select data-field="'+field+'"></select></td><td class="lookup_data" data-field="'+field+'"></td></tr>').appendTo(tbl);
            });

            let rty_ID = this._current_cfg.rty_ID>0 ?$Db.getLocalID('rty',this._current_cfg.rty_ID) :'';
            
            //select service and type
            if(cfg0.service) {
                this.selectServiceType.val(cfg0.service);
            }

            this.selectRecordType.val( rty_ID );
            this._onRectypeChange();
        }else{
            
            this.selectServiceType.val('');
            this.selectRecordType.val('');
            this.element.find('#inpt_label').val('');

            this.element.find('#service_name').html('');
            
            if(service_id=='new'){
                this._isNewCfg = true;
                this._current_cfg = {};
            }else{
                this._current_cfg = null;
            }
        }
        
        this._updateStatus();
    },
    
    //
    // set _is_modified flag
    //
    _updateStatus: function(){
        
        this._is_modified = false;

        if(this._current_cfg==null){
            
            this.element.find('#service_name').html('<span class="ui-icon ui-icon-arrowthick-1-w"></span>Select a service to edit or click the assign button');
            this.element.find('#service_config').hide();
            
        }else{
            this.element.find('#service_config').show();
            
            if($.isEmptyObject(this._current_cfg) || this._isNewCfg){ //new cfg

                this.element.find('#assign_fieldset').show();
                this._is_modified = true;
            }else{

                this.element.find('#assign_fieldset').hide();  //hide service selector
                this.element.find('.service_details').show();
                
                //verify if modified
                this._is_modified =  (this._current_cfg.rty_ID != this.selectRecordType.val())
                               || (this._current_cfg.label != this.element.find('#inpt_label').val()); 
                if(!this._is_modified){

                    let tbl = this.element.find('#tbl_matches');
                    let fields = {};
                    let that = this;
                    $.each(tbl.find('select'), function(i, ele){ // get mapped fields
                
                        let field = $(ele).attr('data-field');
                        let dty_ID = $(ele).val();

                        if(dty_ID == ""){
                            //dty_ID = null;
                        }
                        
                        if(that._current_cfg.fields[field]!=dty_ID){

                            if(!(that._current_cfg.fields[field] == null && dty_ID == "")){
                                that._is_modified = true;
                                return false; //break
                            }
                        }
                    });
                }
            }

            if(!$.isEmptyObject(this._current_cfg) || this.selectServiceType.val()){
                this.element.find('.service_details').show();
            }else{
                this.element.find('.service_details').hide();
                this.element.find('#example_records').hide();
            }

            if(this.selectRecordType.val()){

                this.element.find('#service_mapping').show();
                this.btnApply.show();
            }else{

                this.element.find('#service_mapping').hide();
                this.btnApply.hide();
                this.element.find('#example_records').hide();
            }
        }
            
        // refresh dropdowns
        this.selectMenuRefresh(this.selectServiceType);
        this.selectMenuRefresh(this.selectRecordType);

        this.btnDiscard.show();

        window.hWin.HEURIST4.util.setDisabled(this.btnApply, !this._is_modified);

        if(this._is_modified){
            this.btnApply.addClass('ui-button-action');
        }else{
            this.btnApply.removeClass('ui-button-action');
        }
    },
    
    //
    // prepare form for service type change
    //
    _changeService: function( service_name ){

        const that = this;
        let cfg0 = null;

        $.each(this._available_services, function(i, srv){ // get new service info
          if(srv.service==service_name){
              cfg0 = window.hWin.HEURIST4.util.cloneJSON(srv);
              return false;
          }
        });

        if(this._urls[service_name]){

            this._off($('#a_lookup_url'), 'click');
            if(service_name != 'geoName' && service_name != 'postalCodeSearch'){

                this._on($('#a_lookup_url'), {
                    click: function(){

                        let url = that._urls[service_name];

                        if($.isPlainObject(url)){
                            for(let type in url) {
                                window.open(url[type], '_blank');
                            }
                        }else{
                            window.open(url, '_blank');
                        }
                    }
                });
            }else{

                this._on($('#a_lookup_url'), {
                    click: function(){
                        window.hWin.HEURIST4.msg.showMsgErr('Due to security reasons this url cannot be provided.');
                        return false;
                    }
                });
            }
    
            $('#a_service_url').html(this._urls[service_name].service).attr('href', this._urls[service_name].service);

            this.element.find('.service_urls').show();
        }else{
            this.element.find('.service_urls').hide();
        }
        
        this._fillConfigForm(null, cfg0);
    },

    //
    // Display example records available services
    //
    _displayTestResults: function(service_name){

        let that = this;

        const handled_services = ['bnfLibrary', 'bnfLibraryAut', 'tlcmap', 'geoName', 'postalCodeSearch', 'nomisma', 'nakala', 'nakala_author'];

        this.element.find('#example_records').hide();

        if(handled_services.indexOf(service_name) == -1 || window.hWin.HEURIST4.util.isempty(this.selectRecordType.val())){
            return;
        }

        if(!this.example_results[service_name]){
            // Retrieve data
            let url = this._urls[service_name].lookup;

            let serviceType = service_name;
            let request = {};
            switch (service_name) {
                case 'bnfLibrary':
                    serviceType = 'bnflibrary_bib';
                    break;
                case 'bnfLibraryAut':
                    serviceType = 'bnflibrary_aut';
                    break;
                case 'nomisma':
                    this._runTestNomisma('getMints'); // run all nomisma services
                    url = '';
                    break;
                case 'geoName':
                case 'postalCodeSearch':
                    serviceType = 'geonames';
                    break;
                default:
                    break;
            }

            if(url == ''){
                return;
            }

            request = {
                service: url, // request url
                serviceType: serviceType // requesting service, otherwise no
            };

            window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

                if(response.status != window.hWin.ResponseStatus.OK){
                    return;
                }

                if(service_name.indexOf('bnfLibrary') != -1){
                    response = response.result;
                }else if(service_name == 'geoName'){
                    response = response.geonames;
                }else if(service_name == 'postalCodeSearch'){
                    response = response.postalcodes;
                }else if(service_name == 'nakala'){
                    response = response.records;
                }

                that.example_results[service_name] = response;

                that._displayTestResults(service_name);
                return;
            });

            return;
        }

        // Display data
        let $tbl_cells = this.element.find('.lookup_data');

        let idx = this.element.find('#tbl_matches').attr('data-idx');
        let data = this.example_results[service_name] ? this.example_results[service_name][idx] : null;

        if(service_name == 'nakala'){
            let rec_IDs = Object.keys(this.example_results[service_name]);
            idx = rec_IDs[idx];
            data = this.example_results[service_name][idx];
        }

        if(data){

            $.each($tbl_cells, function(idx, cell){
                let $cell = $(cell);
                let field = $cell.attr('data-field');
                let value = null;

                if(!field){
                    return;
                }

                if(field.indexOf('.') != -1){

                    let fld_parts = field.split('.');
                    value = data[fld_parts[0]];

                    if(window.hWin.HEURIST4.util.isempty(value)){
                        return;
                    }

                    for(let i = 1; i < fld_parts.length; i++){

                        if(window.hWin.HEURIST4.util.isempty(value[fld_parts[i]]) && !window.hWin.HEURIST4.util.isempty(value[0])){
                            value = value[0];
                        }

                        value = value[fld_parts[i]];

                        if(window.hWin.HEURIST4.util.isempty(value)){
                            break;
                        }
                    }

                }else{
                    value = data[field];
                }

                if(value){

                    if(service_name == 'bnfLibrary'){

                        if(field == 'author'){

                            let creator_val = '';
            
                            for(let idx in value){
            
                                let cur_string = '';
                                let cur_obj = value[idx];
            
                                if($.isPlainObject(cur_obj)){
                                    if(Object.hasOwn(cur_obj,'firstname') && cur_obj['firstname'] != ''){
                                        cur_string = cur_obj['firstname'];
                                    }
                                    if(Object.hasOwn(cur_obj,'surname') && cur_obj['surname'] != ''){
                                        cur_string = (cur_string != '') ? cur_obj['surname'] + ', ' + cur_string : cur_obj['surname'];
                                    }
                                    if(Object.hasOwn(cur_obj,'active') && cur_obj['active'] != ''){
                                        cur_string += ' (' + cur_obj['active'] + ')';
                                    }
            
                                    if(cur_string == ''){
                                        Object.values(cur_obj);
                                    }
                                }else{
                                    cur_string = cur_obj;
                                }
            
                                if(!cur_string || $.isArray(cur_string) || $.isPlainObject(cur_string)){
                                    creator_val += 'Missing author; ';
                                }else{
                                    creator_val += cur_string + '; ';
                                }
                            }
            
                            value = creator_val;
                        }else if(field == 'publisher'){

                            let pub_val = '';
            
                            for(let idx in value){
            
                                let cur_string = '';
                                let cur_obj = value[idx];
            
                                if($.isPlainObject(cur_obj)){
                                    if(Object.hasOwn(cur_obj,'name') && cur_obj['name'] != ''){
                                        cur_string = cur_obj['name'];
                                    }
                                    if(Object.hasOwn(cur_obj,'location') && cur_obj['location'] != '' && cur_string == ''){
                                        cur_string = cur_obj['location'];
                                    }
            
                                    if(cur_string == ''){
                                        Object.values(cur_obj);
                                    }
                                }else{
                                    cur_string = cur_obj;
                                }
            
                                if(!cur_string || $.isArray(cur_string) || $.isPlainObject(cur_string)){
                                    pub_val += 'Missing publisher; ';
                                }else{
                                    pub_val += cur_string + '; ';
                                }
                            }
            
                            value = pub_val;
                        }
                    }else if(service_name == 'tlcmap' || service_name == 'nomisma'){

                        if(field == 'geometry'){

                            value = {"type": "Feature", "geometry": value};
                            let wkt = stringifyMultiWKT(value);    
                            if(window.hWin.HEURIST4.util.isempty(wkt)){
                                value = '';
                            }else{
                                let typeCode = 'm';
                                if(wkt.indexOf('GEOMETRYCOLLECTION')<0 && wkt.indexOf('MULTI')<0){
                                    if(wkt.indexOf('LINESTRING')>=0){
                                        typeCode = 'l';
                                    }else if(wkt.indexOf('POLYGON')>=0){
                                        typeCode = 'pl';
                                    }else {
                                        typeCode = 'p';
                                    }
                                }
                                value = typeCode+' '+wkt;
                            }
                        }
                    }

                    if($.isPlainObject(value)){
                        value = window.hWin.HEURIST4.util.htmlEscape(Object.values(value).join(' '));
                    }else if(window.hWin.HEURIST4.util.isArray(value) && value.length >= 1){
                        value = window.hWin.HEURIST4.util.htmlEscape(value.join('; '));
                    }else{
                        value = window.hWin.HEURIST4.util.htmlEscape(value?value:'');
                    }

                    if(!window.hWin.HEURIST4.util.isempty(value)){
                        $cell.html('<span style="display: inline-block;">&lArr;</span><span title="'+value+'" class="truncate">'+value+'</span>');
                    }
                }else{
                    $cell.html('');
                }
            });

            if(service_name == 'nomisma'){
                let type = this.example_results[service_name][idx]['properties']['type'];
                this.element.find('#extra_fluff').html('Currently showing a <strong>'+ type +'</strong> record');
            }else{
                this.element.find('#extra_fluff').html('');
            }

            this.element.find('#example_fluff').text('Search example records: ');
            this.element.find('#example_records').show();
        }
    },

    //
    // Run all handled requests to Nomisma API
    //
    _runTestNomisma: function(type = ''){

        let that = this;
        let service_name = 'nomisma';
        const nomismaServices = ['getMints', 'getHoards', 'getFindspots'];

        if(type == '' && Object.hasOwn(this.example_results, service_name)){
            this._displayTestResults(service_name);
            return;
        }

        if(! Object.hasOwn(this.example_results, service_name)){
            this.example_results[service_name] = [];
        }

        type = (type == '') ? 'getMints' : type;

        if(nomismaServices.indexOf(type) == -1){
            window.hWin.HEURIST4.msg.showMsgErr('An invalid request was made in attempting to retrieve sample Nomisma records.<br>Attempting to retrieve "'+ type +'"');
            return;
        }

        let url = 'https://nomisma.org/apis/'+ type +'?id=denarius';

        let request = {
            service: url,
            serviceType: service_name
        };

        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            if(window.hWin.HEURIST4.util.isGeoJSON(response, true)){
                const value = response.features.slice(0, 5);
                that.example_results[service_name].push(...value);
            }

            if(type == 'getMints'){
                that._runTestNomisma('getHoards');
            }else if(type == 'getHoards'){
                that._runTestNomisma('getFindspots');
            }else{
                that._runTestNomisma('');
            }

            return;
        });
    },

    //
    // create map fields dropdowns
    //
    _onRectypeChange: function(){
     
        let rty_ID = this.selectRecordType.val();   
        
        let tbl = this.element.find('#tbl_matches');
        
        let that = this;
        
        $.each(tbl.find('select'), function(i,selObj){

            
            if($(selObj).hSelect("instance")!=undefined){
               that._off($(selObj).hSelect("instance"),'change');
               $(selObj).hSelect("destroy"); 
            }
            $(selObj).empty();
        });

        if(rty_ID>0){
            $.each(tbl.find('select'), function(i, ele){
                
                let field = $(ele).attr('data-field');
                let dty_ID;
              
                if(!window.hWin.HEURIST4.util.isempty(that._current_cfg)){
                    
                    dty_ID = that._current_cfg.fields[field];
                    
                }else if(!window.hWin.HEURIST4.util.isempty(that.selectServiceType.val())){
                    
                    for(let idx in that._available_services){

                        if(that._available_services[idx] == that.selectServiceType.val()){
                            dty_ID = that._available_services[idx].fields[field];
                        }
                    }
                }

                if (!window.hWin.HEURIST4.util.isempty(dty_ID) && dty_ID.indexOf('-') >= 0){ // concept id - default mapping

                    let extra = '_';
                    if(dty_ID.indexOf('_') > 0){
                        let parts = dty_ID.split('_');
                        dty_ID = parts[0]; // concept id
                        extra = parts[1]; // long | lat
                    }

                    dty_ID = $Db.getLocalID('dty', dty_ID);

                    if(!window.hWin.HEURIST4.util.isempty(dty_ID) && extra != '_'){
                        dty_ID += extra;
                    }
                }
                
                let sel = window.hWin.HEURIST4.ui.createRectypeDetailSelect(ele, rty_ID, 
                    ['freetext','blocktext','enum','date','geo','float','year','integer','resource','file','relmaker'], '...',
                    {show_latlong:true, show_dt_name:true, selectedValue:dty_ID} );
                    
                that._on($(sel), {change:function(){that._updateStatus();}});
            });
            
            this.element.find('#service_mapping').show();
            this.btnApply.show();
            
        }else{
			
            this.element.find('#service_mapping').hide();
            this.btnApply.hide();
        }
        
        
        if(this._isNewCfg && this._current_cfg.label){

            let s = this._current_cfg.label + '<span class="ui-icon ui-icon-arrowthick-1-e"></span> ' 
                    +  (rty_ID>0?$Db.rty(rty_ID, 'rty_Name'):'select record type');
            this.serviceList.find('li[data-service-id="new"]').html(s);
        }
        
        this._displayTestResults(this.selectServiceType.val());
    },
    
    //
    // refresh assigned service list, popup's left panel
    //
    _reloadServiceList: function(){
      
        let that = this;

        this._off(this.serviceList.find('span[data-service-id]'),'click');
        this.serviceList.empty(); // empty list

        for(let idx in this.options.service_config){ // display all assigned services

            let cfg = this.options.service_config[idx];

            if(window.hWin.HEURIST4.util.isempty(cfg)){
                continue;
            }

            let name = cfg.label;
            
            for(let j in this._available_services){
                if(cfg.service == this._available_services[j].service){
                    name = this._available_services[j].label;
                    break;
                }
            }

            let s = name + ' <span class="ui-icon ui-icon-arrowthick-1-e"></span> ' 
                    + $Db.rty(cfg.rty_ID, 'rty_Name');
            s = s + '<span data-service-id="'+idx+'" style="float:right;padding-top: 5px" class="ui-icon ui-icon-circle-b-close"></span>';

            this._reloadServiceList_item( idx, s ); //add to list
        }
        
        this.serviceList.find('li').hover(function(event){ // service list hover event
            let ele = $(event.target);
            if(!ele.is('li')) ele = ele.parent();
            ele.addClass('ui-state-hover');
        }, function(event){
            let ele = $(event.target);
            if(!ele.is('li')){ 
                ele.removeClass('ui-state-hover'); // ensure that this element does not have the hover state
                ele = ele.parent();
            }
            ele.removeClass('ui-state-hover');
        });

        let eles = this.serviceList.find('span[data-service-id]');
        this._on(eles,{'click':function(event)
        { // remove service button
            that._removeConfig($(event.target).attr('data-service-id'));
        }}); 
        
        

    },
    
    _reloadServiceList_item: function( service_id, s ){
        
            let s_active = '';
            if(service_id=='new' || (this._current_cfg && this._current_cfg.service_id==service_id)){
                s_active = ' ui-state-active';
            }

            return $('<li class="ui-widget-content'+s_active+'" data-service-id="'+service_id+'">'+s+'</li>')  
                .css({margin: '5px 2px 2px', padding: '0.4em', cursor:'pointer', background:'#e0dfe0'}) 
                .appendTo(this.serviceList);    
    
    },
    
    //
    // save current service details
    //
    _applyConfig: function(){

        let rty_ID = this.selectRecordType.val();
        let service_name = this.selectServiceType.val();
        let label = this.element.find('#inpt_label').val();

        if(window.hWin.HEURIST4.util.isempty(this._current_cfg)){

            // no service and no service information is available
            window.hWin.HEURIST4.msg.showMsgFlash('Select or define new service first');

        }else if(rty_ID>0 && !window.hWin.HEURIST4.util.isempty(service_name)){ // check if a service and table have been selected

            let that = this;
            let tbl = this.element.find('#tbl_matches');
            let is_field_mapped = false;

            let fields = {};

            $.each(tbl.find('select'), function(i, ele){ // get mapped fields

                let field = $(ele).attr('data-field');
                let dty_ID = $(ele).val();
                fields[field] = dty_ID; 

                if(dty_ID>0) is_field_mapped = true;

            });

            if(is_field_mapped){

                this.options.service_config = window.hWin.HEURIST4.util.isJSON(this.options.service_config); // get existing assigned services
                if(!this.options.service_config){ // Invalid value / None
                    this.options.service_config = {};    
                } 

                let t_name = service_name + '_' + rty_ID;

                if(window.hWin.HEURIST4.util.isempty(label)){ // set label to default, if none provided
                    label = service_name;
                }

                // save changes

                //if rectype has been changed - remove previous one                
                if(t_name != this._current_cfg.service_id && this.options.service_config[t_name]){
                    delete this.options.service_config[t_name];
                }

                this._current_cfg.service_id = t_name;
                this._current_cfg.rty_ID = rty_ID;
                this._current_cfg.label = label;
                this._current_cfg.service = service_name;
                this._current_cfg.fields = fields;

                this.options.service_config[t_name] = this._current_cfg;

                this._isNewCfg = false;

                this._services_modified = true;
                window.hWin.HEURIST4.util.setDisabled(this.save_btn, !this._services_modified);

                this._reloadServiceList(); // reload left panel

                this._updateStatus(); // update is modified

            }else{
                window.hWin.HEURIST4.msg.showMsgFlash('Map at least one field listed', 3000);
            }
        }else{ 
            window.hWin.HEURIST4.msg.showMsgFlash('Select a service and a record type to map', 2000);
        }
    },
    
    //
    // Remove service's details, thus removing it completely
    //
    _removeConfig: function(service_id){

        let is_del = false;
        if(window.hWin.HEURIST4.util.isempty(service_id)) { // check if a service was provided
            if(this._isNewCfg){
                this._isNewCfg = false;
                is_del = true;
            }
        }

        if(this.options.service_config[service_id]!=null) { // check if service has been assigned
            delete this.options.service_config[service_id]; // remove assigned service
            is_del = true;

            this._services_modified = true;
            window.hWin.HEURIST4.util.setDisabled(this.save_btn, !this._services_modified);
        }

        if(is_del){
            this._reloadServiceList();
            this._current_cfg = null;
            this._updateStatus();
        }else if(this._current_cfg){
            //reload
            this._fillConfigForm(this._current_cfg.service_id);
        }
        
    },

    //
    // Refresh the element if it is an instance of selectmenu/hSelect
    //
    selectMenuRefresh: function(selectMenu){

        if(selectMenu.hSelect('instance')){
            selectMenu.hSelect('refresh');
        }
    },    

});
