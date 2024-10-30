/**
* baseConfig.js - base widget for configuration widgets (lookups and repositories)
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2024 University of Sydney
* @author      Brandon McKay <blmckay13@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget("heurist.baseConfig", {

    // default options
    options: {

        //DIALOG section       
        isdialog: false, // show as dialog @see _initDialog(), popupDialog(), closeDialog()
        height: 640,
        width:  900,
        modal:  true,
        title:  'Configure services',
        htmlContent: '',
        helpContent: null,

        //parameters
        service_config: {}, // all assigned services
        service_remove: [], // services to be removed

        //listeners
        onInitFinished:null, //event listener when dialog is fully inited - use to perform initial search with specific parameters
        beforeClose:null, //to show warning before close
        onClose:null,

        type: ''
    },

    _as_dialog: null, //reference to itself as dialog (see options.isdialog)

    _need_load_content:true,

    _current_cfg: null, // current set service details
    _is_modified: false, // is current service modified
    _available_services:null, // list of available services
    _services_modified: false, // has any service been removed/added
    _isNewCfg: false,

    //controls
    selectRecordType:null, //selector for rectypes
    selectUserGroups:null, //selector user and groups
    selectServiceType: null, //selector for lookup service types
    serviceList: null, //left panel list

    example_results: {},    

    save_btn: null,
    close_btn: null,

    // the widget's constructor
    _create: function(){
        // prevent double click to select text

    }, //end _create

    _init: function(){

        let that = this;

        this.element.addClass('ui-heurist-design');

        if(this.options.isdialog){  //show this widget as popup dialog
            this._initDialog();
        }

        //load html from file
        if(this._need_load_content && this.options.htmlContent){

            let sub_dir = this.options.type === 'service' ? 'lookup' : 'admin';
            let html = `${window.hWin.HAPI4.baseURL}hclient/widgets/${sub_dir}/${this.options.htmlContent}?t=${window.hWin.HEURIST4.util.random()}`;

            this.element.load(html,
            function(response, status, xhr){
                that._need_load_content = false;
                if(status == "error"){

                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: response,
                        error_title: 'Failed to load HTML content',
                        status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                    });

                    return;
                }

                that._initControls();
            });
            return;
        }

        that._initControls();
    },

    _initControls: function(){

        let that = this;

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

        //fill service types
        this.selectServiceType = this.element.find('#sel_servicetype').css({'list-style-type': 'none'});
        this._getServiceSelectmenu();

        // on change handler
        this._on(this.selectServiceType[0], {
            change: function(event, ui){
                let service = that.selectServiceType.val(); // selected service
                that._changeService( service ); // setup
            }
        });

        if(this.options.isdialog){
            
            this.element.find('.popup_buttons_div, .ui-heurist-header').hide();
            this.element.find('div.ent_content').toggleClass(['ent_content', 'ent_content_full']).css('top', '-0.2em');

            this.popupDialog();
        }else{

            // add title/heading
            this.element.find('.ui-heurist-header').text(this.options.title);

            // bottom bar buttons
            this.save_btn = this.element.find('.btnSave').button();
            this.close_btn = this.element.find('.btnClose').button();

            this._on(this.save_btn, {
                click: () => {
                    that._closeHandler(true, false, null);
                }
            });
            this._on(this.close_btn, {
                click: () => {
                    that._closeHandler(false, false, null);
                }
            });

            // mouse leaves container
            this._on(this.element.find('.ent_wrapper:first'), {
                mouseleave: (event) => {
                    if($(event.target).is('div') && (that._is_modified || that._services_modified) && !that._isNewCfg){
                        that._closeHandler(false, true, $(event.target));
                    }
                }
            });
        }

        this._updateStatus();

        window.hWin.HEURIST4.util.setDisabled(this.save_btn, !this._services_modified);

        let rpanel_width = this.element.find('#editing_panel').width() - 40;
        this.element.find('#service_mapping').width(rpanel_width);

        if(window.hWin.HEURIST4.util.isFunction(that.options.onInitFinished)){
            that.options.onInitFinished.call(that);
        }

        return true;
    },

    //
    // array of button defintions
    //
    _getActionButtons: function(){

        let that = this;

        return [{
            text:window.hWin.HR('Close'), 
            class:'btnClose',
            css:{'float':'right','margin-left':'30px'}, 
            click: function() { 
                that._closeHandler(false, false, null);
            }
        },
        {
            text:window.hWin.HR('Save'),
            class:'ui-button-action btnSave',
            css:{'float':'right'},
            click: function() {
                that._closeHandler(true, false, null);
            }
        }];
    },

    //
    // init dialog widget
    // see also popupDialog, closeDialog 
    //
    _initDialog: function(){

        let options = this.options,
            btn_array = this._getActionButtons(), 
            position = null,
            that = this;

        if(!options.beforeClose){
            options.beforeClose = function(){
                return true;
            };
        }

        if(position==null) position = { my: "center", at: "center", of: window };
        let maxw = (window.hWin ? window.hWin.innerWidth : window.innerWidth);
        if(options['width'] > maxw) options['width'] = maxw * 0.95;
        let maxh = (window.hWin ? window.hWin.innerHeight : window.innerHeight);
        if(options['height'] > maxh) options['height'] = maxh * 0.95;

        let $dlg = this.element.dialog({
            autoOpen: false ,
            //element: this.element[0],
            height: options['height'],
            width:  options['width'],
            modal:  (options['modal']!==false),
            title: this.options.title,
            position: position,
            beforeClose: options.beforeClose,
            resizeStop: function( event, ui ) {//fix bug
                that.element.css({overflow: 'none !important','width':that.element.parent().width()-24 });
            },
            close:function(){
                if(window.hWin.HEURIST4.util.isFunction(that.options.onClose)){
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

        if(!this.options.isdialog){
            return;
        }

        this._as_dialog.dialog("open");

        if(this.options.helpContent){
            let helpURL = window.hWin.HRes( this.options.helpContent )+' #content';
            window.hWin.HEURIST4.ui.initDialogHintButtons(this._as_dialog, null, helpURL, false);    
        }

        this.save_btn = this._as_dialog.find('.btnSave');
        this.close_btn = this._as_dialog.find('.btnClose');
    },

    //
    // close dialog
    //
    closeDialog: function(is_force){

        if(!this.options.isdialog){
            return;
        }

        if(is_force===true){
            this._as_dialog.dialog('option','beforeClose',null);
        }

        this._as_dialog.dialog("close");
    },

    //
    // on close 
    //
    _closeHandler: function(isSave=false, isMouseLeave=false, trigger = null){

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

            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(`You have made changes to the ${wording}. Click ${button} otherwise all changes will be lost.`, 
                buttons, {title: 'Unsaved Changes', yes: 'Save', no: 'Ignore and Close'}, {default_palette_class: 'ui-heurist-design'});
        }else if(isSave){
            this.saveConfigrations();
        }else if(isMouseLeave){
            return;
        }else if(this.options.isdialog && this._as_dialog.dialog('instance') !== undefined){
            this._as_dialog.dialog('close');
        }else{
            this.element.empty().hide();
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
            title: `select a ${this.options.type}...`,
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
            window.hWin.HEURIST4.msg.showMsgFlash('Complete or discard unfinished one', 700);
            return;
        }

        // empty all inputs
        this.serviceList.find('li').removeClass('ui-state-active');

        // empty control variables
        this._fillConfigForm('new');

        this._reloadServiceList_item('new', 'assign on right ...');
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

            let service_name = this.options.type == 'repository'
                                ? window.hWin.HAPI4.SystemMgr.getUserNameLocal(cfg.usr_ID) : $Db.rty(cfg.rty_ID, 'rty_Name');

            let s = `${name} <span class="ui-icon ui-icon-arrowthick-1-e"></span> ${service_name}`;
            s += `<span data-service-id="${idx}" style="float:right;padding-top: 5px" class="ui-icon ui-icon-circle-b-close"></span>`;

            this._reloadServiceList_item( idx, s ); //add to list
        }
        
        this._on(this.serviceList.find('li'), {
            mouseenter: (event) => {
                let ele = $(event.target);
                if(!ele.is('li')) ele = ele.parent();
                ele.addClass('ui-state-hover');
            },
            mouseleave: (event) => {
                let ele = $(event.target);
                if(!ele.is('li')){ 
                    ele.removeClass('ui-state-hover'); // ensure that this element does not have the hover state
                    ele = ele.parent();
                }
                ele.removeClass('ui-state-hover');
            }
        });

        let eles = this.serviceList.find('span[data-service-id]');
        this._on(eles,{
            click: function(event){ // remove service button
                that._removeConfig($(event.target).attr('data-service-id'));
            }
        }); 

    },

    getServiceDefInfo: function(service_id){

        let config = null;

        for(const srv of Object.values(this._available_services)){ // get new service info
            if(srv.service == service_id){
                config = window.hWin.HEURIST4.util.cloneJSON(srv);
                break;
            }
        }
        return config;
    },

    //
    //
    //
    _reloadServiceList_item: function( service_id, s ){

        let s_active = '';
        if(service_id=='new' || this._current_cfg?.service_id==service_id){
            s_active = ' ui-state-active';
        }

        return $('<li>', {
            class: `ui-widget-content${s_active}`,
            'data-service-id': service_id,
            style: 'margin: 5px 2px 2px; padding: 0.4em; cursor: pointer; background: #e0dfe0',
            html: s
        }).appendTo(this.serviceList);
    },

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
    // Called whenever the option() method is called
    // Overriding this is useful if you can defer processor-intensive changes for multiple option change
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
        if(this.selectUserGroups) this.selectUserGroups.remove();
        if(this.selectServiceType) this.selectServiceType.remove();
        if(this.serviceList) this.serviceList.remove();
    },

    //
    // Refresh the element if it is an instance of selectmenu/hSelect
    //
    selectMenuRefresh: function(selectMenu){

        if(selectMenu.hSelect('instance')){
            selectMenu.hSelect('refresh');
        }
    },

    _checkModification: function(){

        let tbl = this.element.find('#tbl_matches');
        let that = this;

        let values = this.options.type === 'service' ? that._current_cfg.fields : that._current_cfg.params;

        $.each(tbl.find('input, select'), function(i, ele){ // get mapped fields

            let field = $(ele).attr('data-field');
            let value = $(ele).val();

            if(values[field] != value && (values[field] !== null || value !== "")){
                that._is_modified = true;
                return false; //break
            }
        });
    }
});