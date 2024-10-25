/**
* recordExportCSV.js - select fields to be exported to CSV for current recordset
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

$.widget( "heurist.recordExportCSV", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 780,
        width:  800,
        modal:  true,
        title:  'Export records to comma or tab separated text files',
        default_palette_class: 'ui-heurist-publish', 
        
        htmlContent: 'recordExportCSV.html'
    },

    selectedFields:null,
    
    _collected_rtyid: null,
    _selected_rtyid: null,
    
    MAX_LEVEL:3,
    
    _destroy: function() {
        this._super(); 
        
        let treediv = this._$('.rtt-tree');
        if(!treediv.is(':empty') && treediv.fancytree("instance")){
            treediv.fancytree("destroy");
        }
        treediv.remove();
        
        if(this.toolbar)this.toolbar.remove();
    },
        
    _initControls: function() {


        let that = this;
        if(!window.hWin.HEURIST4.util.isFunction($('body')['configEntity'])){ //OK! widget script js has been loaded

            $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/entity/configEntity.js', 
                function(){ 
                    if(that._initControls()){
                        if(window.hWin.HEURIST4.util.isFunction(that.options.onInitFinished)){
                            that.options.onInitFinished.call(that);
                        }        
                    }
            } );
            return false;            
        }

        this._super();    

        if(window.hWin.HAPI4.has_access()){

            this._$('#divLoadSettings').configEntity({
                entityName: 'defRecTypes',
                configName: 'csvexport',
    
                getSettings: function(){ return that.getSettings(false); }, //callback function to retieve configuration
                setSettings: function( settings ){ that.setSettings( settings ); }, //callback function to apply configuration
    
                //divLoadSettingsName: this.element
                divSaveSettings: this._$('#divSaveSettings'),  //element
                showButtons: true
    
            });
    
            this._$('#divLoadSettings').configEntity( 'updateList', this.selectRecordScope.val() );    
        }else{
            this._$('#divLoadSettings, #divSaveSettings').hide();
        }

        // Initialize field advanced pane.
        this._resetAdvancedControls();
       
        
        if(!this.options.isdialog){
            let fele = this._$('.ent_wrapper:first');
            fele.css({top:'36px',bottom:'40px'});
            $('<div class="ui-heurist-header">'+this.options.title+'</div>').insertBefore(fele);    

            let toolbar_height = '20px';
            if(navigator.userAgent.indexOf('Firefox') >= 0){
                toolbar_height = '40px';
            }
            this.toolbar = $('<div class="ent_footer button-toolbar ui-heurist-header" style="height:'+ toolbar_height +'"></div>').insertAfter(fele);    

            //append action buttons
            this.toolbar.empty();
            let btns = this._getActionButtons();
            for(let idx in btns){
                this._defineActionButton2(btns[idx], this.toolbar);
            }
        }
        
        this._$('.export-to-bottom-button').on('click', function () {
            let container = $(this).parent();
            container.scrollTop(container[0].scrollHeight);
        });

        // Reorder tree nodes
        this._on($('[name="tree_order"]'), {
            change: () => {
                let order = $('[name="tree_order"]:checked').val();
                sessionStorage.setItem('heurist_ftorder_exportcsv', order);

                let treediv = that.element.find('.rtt-tree');

                if(treediv.length > 0 && treediv.fancytree('instance')!==undefined){
                    window.hWin.HEURIST4.ui.reorderFancytreeNodes_rst(treediv, order);
                }
            }
        });

        return true;
    },

    //
    //
    //
    setSettings: function(settings){
        
        this.selectedFields = [];
        
        if(settings){
        
            let that = this;
            //restore selection
            that.selectedFields = settings.fields; 

            that._assignSelectedFields(null);

            // Set advanced options.
            if (Object.hasOwn(settings,'advanced_options') && settings.advanced_options) {
                that._setFieldAdvancedOptions(settings.advanced_options);
            }
            
            that.element.find('#delimiterSelect').val(settings.csv_delimiter);
            that.element.find('#quoteSelect').val(settings.csv_enclosure);
            that.element.find('#cbNamesAsFirstRow').prop('checked',(settings.csv_header==1));
            that.element.find('#cbIncludeTermIDs').prop('checked',(settings.include_term_ids==1));
            that.element.find('#cbIncludeTermCodes').prop('checked',(settings.include_term_codes==1));
            that.element.find('#cbIncludeTermHierarchy').prop('checked',(settings.include_term_hierarchy==1));
            that.element.find('#cbIncludeResourceTitles').prop('checked',(settings.include_resource_titles==1));
            that.element.find('#chkJoinRecTypes').prop('checked',(settings.join_record_types==1));
            that.element.find('#cbIncludeMediaURL').prop('checked',(settings.include_file_url==1));
            that.element.find('#cbIncludeRecURLHTML').prop('checked',(settings.include_record_url_html==1));
            that.element.find('#cbIncludeRecURLXML').prop('checked',(settings.include_record_url_xml==1));
            that.element.find('#cbIncludeTemporals').prop('checked',(settings.include_temporals==1));

        }
    },

    
    //
    // assign selected fields in tree
    //
    _assignSelectedFields: function(rnode){
        
            let that = this;
            let tree = $.ui.fancytree.getTree( that.element.find('.rtt-tree') );           

            if(rnode==null){
                rnode = tree.getRootNode();  
                rnode.setExpanded(true);
            } 
            
            rnode.visit(function(node){
                node.setSelected(false);
                let has_child = node.hasChildren();
                if(has_child===undefined){
                    node.setExpanded(true);
                    setTimeout(function(){
                            that._assignSelectedFields( node );
                    },500);

                }else{
                    if(that.selectedFields && that.selectedFields.length>0 && has_child===false){
                            for(let i=0; i<that.selectedFields.length; i++){
                                if(that.selectedFields[i]==node.data.code){
                                    node.setSelected(true);
                                    break;
                                }
                            }
                    }
                }
            });

    },
    
    //    
    //
    //
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Download');
        res[0].text = window.hWin.HR('Close');
        return res;
    },    
        
    //
    // overwrite parent's method
    //
    _fillSelectRecordScope: function (){

        const that = this;

        this.selectRecordScope.empty();

        let selScope = this.selectRecordScope.get(0);
        
        let rectype_Ids = this._currentRecordset.getRectypes();
        
        if(rectype_Ids.length>1){
            
            let opt = window.hWin.HEURIST4.ui.addoption(selScope,'','select record type …');
            $(opt).attr('disabled','disabled').attr('visiblity','hidden').css({display:'none'});

            rectype_Ids.forEach(rty => {
                if(rty>0 && $Db.rty(rty,'rty_Name') ){
                    let name = $Db.rty(rty,'rty_Plural');
                    if(!name) name = $Db.rty(rty,'rty_Name');
                    window.hWin.HEURIST4.ui.addoption(selScope,rty,'only: '+name);
                }
            });
        }

        
        if (this._currentRecordset &&  this._currentRecordset.length() > 0) {
            
                let msg = (rectype_Ids.length>1)?'Basic record fields only':'Current result set';
                    
                window.hWin.HEURIST4.ui.addoption(selScope,
                    (rectype_Ids.length>1)?'current':rectype_Ids[0],
                    msg +' (count=' + this._currentRecordset.length()+')');
        }
        
        if (this._currentRecordsetColIds &&  this._currentRecordsetColIds.length > 0) {

            let common_id = null;

            // Check if collected records all share the same id
            $.each(this._currentRecordsetColIds, (idx, id) => {

                let record = that._currentRecordset.getRecord(id);
                if(!record){
                    return;
                }

                let cur_rectypeid = record['rec_RecTypeID'];

                if(!common_id){
                    common_id = cur_rectypeid;
                    return;
                }else if(common_id != cur_rectypeid){
                    common_id = -1;
                    return false;
                }
            });

            if(common_id > 0){
                this._collected_rtyid = common_id;
            }

            window.hWin.HEURIST4.ui.addoption(selScope, 'collected',
                'Collected records only (count=' + this._currentRecordsetColIds.length+')');
        }

        if (this._currentRecordsetSelIds &&  this._currentRecordsetSelIds.length > 0) {

            let common_id = null;

            // Check if selected records all share the same id
            $.each(this._currentRecordsetSelIds, (idx, id) => {

                let record = that._currentRecordset.getRecord(id);
                if(!record){
                    return;
                }

                let cur_rectypeid = record['rec_RecTypeID'];

                if(!common_id){
                    common_id = cur_rectypeid;
                    return;
                }else if(common_id != cur_rectypeid){
                    common_id = -1;
                    return false;
                }
            });

            if(common_id > 0){
                this._selected_rtyid = common_id;
            }

            window.hWin.HEURIST4.ui.addoption(selScope, 'selected',
                'Selected records only (count=' + this._currentRecordsetSelIds.length+')');
        }
        
        
        
        this._on( this.selectRecordScope, {
                change: this._onRecordScopeChange} );        
        this._onRecordScopeChange();
        
        window.hWin.HEURIST4.ui.initHSelect(selScope);
        
    },
            
    //
    // 0 - download, 1 - open in new window
    //
    doAction: function(mode){

        let scope_val = this.selectRecordScope.val();
        
        let scope = [], 
        rec_RecTypeID = 0;
        
        if(scope_val == 'selected'){
            scope = this._currentRecordsetSelIds;
        }else { //(scope_val == 'current'
            scope = this._currentRecordset.getIds();
            if(scope_val  >0 ){
                rec_RecTypeID = scope_val;
            }   
        }
        
        if(scope.length<1){
            window.hWin.HEURIST4.msg.showMsgFlash('No results found. '
            +'Please modify search/filter to return at least one result record.', 2000);
            return;
        }
        
        let settings = this.getSettings(true);
        if(!settings) return;

        let request = {
            'request_id' : window.hWin.HEURIST4.util.random(),
            'db': window.hWin.HAPI4.database,
            'ids'  : scope,
            'format': 'csv',
            'prefs': settings};
            
        if(rec_RecTypeID>0){
            request['rec_RecTypeID'] = rec_RecTypeID;
        }
        
        let url = window.hWin.HAPI4.baseURL + 'hserv/controller/record_output.php'

        //posting via form allows send large list of ids
        this._$('#postdata').val( JSON.stringify(request) );
        this._$('#postform').attr('action', url);
        this._$('#postform').trigger('submit');
            
    },
    
    //
    // mode_action true - returns fields for csv export, false - returns codes of selected nodes
    //
    getSettings: function( mode_action ){

        let header_fields = {ids:'rec_ID', title:'rec_Title', url:'rec_URL', modified:'rec_Modified', tag:'rec_Tags', 
            typeid: 'rec_RecTypeID', typename: 'rec_RecTypeName', added: 'rec_Added', addedby: 'rec_AddedByUGrpID', 
            owner: 'rec_OwnerUGrpID', access: 'rec_NonOwnerVisibility', notes: 'rec_ScratchPad'};

        function __removeLinkType(dtid){
            if(header_fields[dtid]){
                dtid = header_fields[dtid];
            }else{
                let linktype = dtid.substr(0,2); //remove link type lt ot rt  10:lt34
                if(isNaN(Number(linktype))){
                    dtid = dtid.substr(2);
                }
            }
            return dtid;
        }
        let mainRecordTypeIDs = [];
        function __addSelectedField(ids, lvl, constr_rt_id){
            
            if(ids.length < lvl) return;
            
            //take last two - these are rt:dt
            let rtid = ids[ids.length-lvl-1];
            let dtid = __removeLinkType(ids[ids.length-lvl]);

            if(!selectedFields[rtid]){
                selectedFields[rtid] = [];    
            }
            if(constr_rt_id>0){
                dtid = dtid+':'+constr_rt_id;
            }

            // Get main record type IDs.
            if (lvl === 1 && typeof ids[0] !== 'undefined' && mainRecordTypeIDs.indexOf(ids[0]) < 0) {
                mainRecordTypeIDs.push(ids[0]);
            }
            
            //window.hWin.HEURIST4.util.findArrayIndex( dtid, selectedFields[rtid] )<0
            if( selectedFields[rtid].indexOf( dtid )<0 ) {
                if(dtid == 'rec_Title' || dtid == 'rec_ID'){ // place title and id fields at the start
                    selectedFields[rtid].unshift(dtid);
                }else{
                    selectedFields[rtid].push(dtid);
                }
            } 
            //add resource (record pointer) field for parent recordtype
            __addSelectedField(ids, lvl+2, rtid);
        }
        
        //get selected fields from treeview
        let selectedFields = mode_action?{}:[];
        let tree = $.ui.fancytree.getTree( this._$('.rtt-tree') );
        let fieldIds = tree.getSelectedNodes(false);
        const len = fieldIds.length;
        
        if(len<1){
            window.hWin.HEURIST4.msg.showMsgFlash('No fields selected. '
                +'Please select at least one field in tree', 2000);
            return false;
        }

        for (let k=0;k<len;k++){

            let node =  fieldIds[k];
            
            if(window.hWin.HEURIST4.util.isempty(node.data.code)) continue;

            if(mode_action){

                let ids = node.data.code.split(":");

                if(isNaN(ids[ids.length - 1]) && ids[ids.length - 1].match(/\d/)){

                    let rty = node.data.rt_ids ? node.data.rt_ids : node.data.rtyID_local;

                    // Add title
                    ids.push(rty, 'title');
                    __addSelectedField(ids, 1, 0);

                    // Add id
                    ids.splice(-1, 1, 'ids');
                    __addSelectedField(ids, 1, 0);
                }else{
                    __addSelectedField(ids, 1, 0);
                }

            }else{
                selectedFields.push(node.data.code);
            }
        }
        if(mode_action && selectedFields.undefined){ // remove invalid rectype
            delete selectedFields.undefined;
        }
        return {
            'fields': selectedFields,
            'main_record_type_ids': mainRecordTypeIDs,
            'join_record_types': this._$('#chkJoinRecTypes').is(':checked')?1:0,
            'advanced_options': this._getFieldAdvancedOptions(mode_action),
            'csv_delimiter':  this._$('#delimiterSelect').val(),
            'csv_enclosure':  this._$('#quoteSelect').val(),
            'csv_mvsep':'|',
            'csv_linebreak':'nix', //not used at tne moment
            'csv_header': this._$('#cbNamesAsFirstRow').is(':checked')?1:0,
            'include_term_ids': this._$('#cbIncludeTermIDs').is(':checked')?1:0,
            'include_term_codes': this._$('#cbIncludeTermCodes').is(':checked')?1:0,
            'include_file_url': this._$('#cbIncludeMediaURL').is(':checked')?1:0,
            'include_record_url_html': this._$('#cbIncludeRecURLHTML').is(':checked')?1:0,
            'include_record_url_xml': this._$('#cbIncludeRecURLXML').is(':checked')?1:0,
            'include_term_hierarchy': this._$('#cbIncludeTermHierarchy').is(':checked')?1:0,
            'include_resource_titles': this._$('#cbIncludeResourceTitles').is(':checked')?1:0,
            'include_temporals':  this._$('#cbIncludeTemporals').is(':checked')?1:0

        };
        
    },

    //
    // overwritten
    //
    _onRecordScopeChange: function() 
    {
        let isdisabled = this._super();
        
        
        
        let rtyID = this.selectRecordScope.val();
        rtyID = rtyID == 'collected' && this._collected_rtyid ? this._collected_rtyid : rtyID;
        rtyID = rtyID == 'selected' && this._selected_rtyid ? this._selected_rtyid : rtyID;

        //reload treeview
        this._loadRecordTypesTreeView( rtyID );
        
        $('#divSaveSettings').hide();
        $('#divLoadSettings').hide();
        
        if(rtyID=='' || rtyID==null){
            $('.rtt-tree').parent().hide();
            this._$('#export_format_container').hide();
        }else{
            $('.rtt-tree').parent().show();
            this._$('#export_format_container').show();
            if(rtyID>0){
                this.selectedFields = [];
            }
        }
        
        if(this._$('#divLoadSettings').configEntity('instance')){
            this._$('#divLoadSettings').configEntity( 'updateList', rtyID );    
        }
        
        this._resetAdvancedControls();
   
        return isdisabled;
    },
    
    //
    // show treeview with record type structure
    //
    _loadRecordTypesTreeView: function(rtyID){
        
        let that = this;

        if(this._selectedRtyID!=rtyID ){
            
            this._selectedRtyID = rtyID;
            
            let node_order = sessionStorage.getItem('heurist_ftorder_exportcsv');
            if(window.hWin.HEURIST4.util.isempty(node_order) || !Number.isInteger(+node_order)){
                node_order = 0; // default to form order
            }
            this._$('[name="tree_order"]').filter('[value="'+ node_order +'"]').prop('checked', true);
            
            //generate treedata from rectype structure
            let treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, rtyID, ['header_ext','all','parent_link'], null, node_order );
            
            treedata[0].expanded = true; //first expanded
            
            //load treeview
            let treediv = this._$('.rtt-tree');
            if(!treediv.is(':empty') && treediv.fancytree("instance")){
                treediv.fancytree("destroy");
            }
            
            treediv.addClass('tree-csv').fancytree({
                //extensions: ["filter"],
                //            extensions: ["select"],
                checkbox: true,
                selectMode: 3,  // hierarchical multi-selection
                source: treedata,
                beforeSelect: function(event, data){
                    // A node is about to be selected: prevent this, for folder-nodes:
                    if( data.node.type== 'rectype' && data.node.hasChildren() ){
                        
                        if(data.node.isExpanded()){
                            for(let i=0; i<data.node.children.length; i++){
                                let node = data.node.children[i];
                                if(node.key=='rec_ID' || node.key=='rec_Title'){
                                    node.setSelected(true);
                                }
                            }
                        }
                        return false;
                    }
                },
                renderNode: function(event, data){

                    let order = that.element.find('[name="tree_order"]:checked').val();

                    let check_rty = data.node.data.rtyID_local ? data.node.data.rtyID_local : null;
                    check_rty = data.node.data.rt_ids ? data.node.data.rt_ids : check_rty;

                    if(check_rty){

                        let check_node = data.node;

                        check_rty = check_rty.indexOf(',') > 0 ? check_rty.split(',') : [check_rty];

                        while(check_node && check_node.parent){
    
                            let parent_rty = null;
    
                            if(check_node.parent.data.is_rec_fields){
                                parent_rty = check_node.parent.parent.data.rtyID_local;
                                check_node = check_node.parent.parent;
                            }else{
                                parent_rty = check_node.parent.data.rt_ids;
                                check_node = check_node.parent;
                            }
    
                            let found_idx = check_rty.indexOf(parent_rty);

                            if(found_idx >= 0){
                                check_rty.splice(found_idx, 1);//remove
                            }
    
                            if(check_rty.length == 0){
                                break;
                            }
                        }

                        if(check_rty.length == 0){
                            // remove fancytree-has-children, set lazy to false, remove child nodes

                            $(data.node.span).removeClass('fancytree-has-children');
                            data.node.lazy = false;
                            data.node.removeChildren();
                        }
                    }

                    if(data.node.parent && data.node.parent.type == 'resource' || data.node.parent.type == 'relmarker'){ // add left border+margin
                        $(data.node.li).attr('style', 'border-left: black solid 1px !important;margin-left: 9px;');
                    }
                    if(data.node.type == 'separator'){
                        $(data.node.span).attr('style', 'background: none !important;color: black !important;'); //stop highlighting
                        $(data.node.span.childNodes[1]).hide(); //checkbox for separators

                        if(order == 1){
                            $(data.node.li).addClass('fancytree-hidden');
                        }
                    }
                },
                lazyLoad: function(event, data){
                    let node = data.node;
                    let parentcode = node.data.code; 
                    let rectypes = node.data.rt_ids;
                    
                    if(parentcode.split(":").length< that.MAX_LEVEL*2+1){  //7 limit with 4 levels (till 2021-10-15 were 3 levels)
                    
                        let node_order = that.element.find('[name="tree_order"]:checked').val();

                        let res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, 
                                                            rectypes, ['header_ext','all','parent_link'], parentcode, node_order );
                        if(res.length>1){
                            data.result = res;
                        }else{
                            data.result = res[0].children;
                        }
                        
                    }else{
                        data.result = [];
                    }                            
                    
                    return data;                                                   
                },
                loadChildren: function(e, data){
                    setTimeout(function(){
                       
                    },500);
                },
                select: function(e, data) {
                    if(data.node.isSelected()){

                        // Expand parent nodes
                        let cur_parent = data.node.parent;
                        while(cur_parent){

                            if(!cur_parent.isExpanded()){
                                cur_parent.setExpanded(true);
                            }

                            cur_parent = cur_parent.parent;
                        }

                        let rectypeId = data.node.data.rtyID_local ? data.node.data.rtyID_local : null;
                        rectypeId = data.node.data.rt_ids && data.node.data.rt_ids.indexOf(',') < 0 ? data.node.data.rt_ids : rectypeId;

                        that._addFieldAdvancedOptions(data.node.title, data.node.type, data.node.data.code, data.node.li, rectypeId);
                    } else {
                        that._removeFieldAdvancedOptionsByCode(data.node.data.code);
                    }
                    if (that.element.find('.export-advanced-list-item').length > 0) {
                        that.element.find('.export-advanced-list').show();
                    } else {
                        that.element.find('.export-advanced-list').hide();
                    }
                },
                click: function(e, data){

                    if(data.node.type == 'separator'){
                        return false;
                    }

                    let isExpander = $(e.originalEvent.target).hasClass('fancytree-expander');

                    if($(e.originalEvent.target).is('span') && !isExpander && data.node.children && data.node.children.length > 0){
                        data.node.setExpanded(!data.node.isExpanded());
                    }else if(data.node.lazy && !isExpander) {
                        data.node.setExpanded(true);
                    }
                },
                expand: function(e, data) {
                    if(data.node.type== 'rectype' && data.node.children.length > 0){
                        for(let i = 0; i < data.node.children.length; i++){
                            let node = data.node.children[i];
                            if(node.key=='rec_ID' || node.key=='rec_Title'){
                                node.setSelected(true);
                            }
                        }
                    }
                    let selected_nodes = $.ui.fancytree.getTree( treediv ).getSelectedNodes();
                    for(let j = 0; j < selected_nodes.length; j++){
                        that._displayAdvOption(selected_nodes[j]['data']['code'], $(selected_nodes[j]['li']).is(':visible'), selected_nodes[j]['li']);
                    }
                },
                collapse: function(e, data) {
                    let selected_nodes = $.ui.fancytree.getTree( treediv ).getSelectedNodes();
                    for(let j = 0; j < selected_nodes.length; j++){
                        that._displayAdvOption(selected_nodes[j]['data']['code'], $(selected_nodes[j]['li']).is(':visible'), selected_nodes[j]['li']);
                    }
                },
                dblclick: function(e, data) {
                    if(data.node.type == 'separator'){
                        return false;
                    }
                    data.node.toggleSelected();
                },
                keydown: function(e, data) {
                    if( e.which === 32 ) {
                        data.node.toggleSelected();
                        return false;
                    }
                }
            });
        }   
    },
    
   /**
     * Get the content of a specified HTML template.
     *
     * @param {string} templateName The 'id' of the template HTML element.
     * @param {Object} variables The passed-in variables for the template. The variable
     *   placeholders in the template will be replaced to the value of the same
     *   property name.
     * @return {string}
     * @private
     */
    _getTemplateContent: function (templateName, variables) {
        let content = this._$('#' + templateName).html();
        if (typeof variables === 'object' && variables !== null) {
            for (let name in variables) {
                if (Object.hasOwn(variables,name)) {
                    content = content.replaceAll('{' + name + '}', variables[name]);
                }
            }
        }
        return content;
    },

    /**
     * Reset the advanced pane to its initial state.
     * @private
     */
    _resetAdvancedControls: function () {
        this._$('.export-advanced-list').html('');
        this._$('.export-advanced-list').hide();
    },

    /**
     * Populate the options for the total select control.
     *
     * @param {Object} totalSelectElement The DOM element of the total select control.
     * @param {bool} isNumeric Whether the field type is numeric.
     * @private
     */
    _populateFieldAdvancedTotalSelectOptions: function (totalSelectElement, isNumeric) {
        $(totalSelectElement).html('');
        $(totalSelectElement).append('<option value="" selected>Value</option>');
        $(totalSelectElement).append('<option value="group">Group By</option>');
        $(totalSelectElement).append('<option value="count">Count</option>');
        if (isNumeric) {
            $(totalSelectElement).append('<option value="sum">Sum</option>');
        }
    },

    _displayAdvOption: function(fieldCode, showField, item){
        let $ele = this._$('div[data-field-code="'+ fieldCode +'"]');
        if($ele.length == 0){
            return;
        }

        if(showField){
            $ele.show();
        }else{
            $ele.hide();
        }

        if(item){
            let pos_top = item.offsetTop;
            $ele.css({
                'position': 'absolute',
                'top': pos_top+'px',
                'left': '20px'
            });
        }
    },

    /**
     * Add the advanced options for a field in the UI.
     *
     * @param {string} fieldName The field label to display.
     * @param {string} fieldType The type of the field.
     * @param {string} fieldCode The code of the field.
     * @param {Object} item The selected node
     * @param {string} rectypeId The record type of the field
     * @private
     */
    _addFieldAdvancedOptions: function (fieldName, fieldType, fieldCode, item, rectypeId) {

        if(this._$('div[data-field-code="'+ fieldCode +'"]').length != 0){
            this._displayAdvOption(fieldCode, true, item);
            return;
        }

        let content = this._getTemplateContent('templateAdvancedFieldOptions', {
            "fieldName": fieldName,
            "fieldType": fieldType,
            "fieldCode": fieldCode
        });
        let fieldElement = $(content);
        this._$('.export-advanced-list').append(fieldElement);
        this._populateFieldAdvancedTotalSelectOptions(fieldElement.find('.export-advanced-list-item-total-select')[0], fieldType === 'float');
        this._$('.export-advanced-list-item-total-select').on('change', function () {
            let itemElement = $(this).closest('.export-advanced-list-item');
            itemElement.find('.export-advanced-list-item-percentage-checkbox').prop('checked', false);
            if ($(this).val() === 'count' || $(this).val() === 'sum') {
                itemElement.find('.export-advanced-list-item-percentage').show();
            } else {
                itemElement.find('.export-advanced-list-item-percentage').hide();
            }
        });

        if(!window.hWin.HEURIST4.util.isempty(rectypeId)){
            fieldElement.data('rectype-id', rectypeId);
        }

        let pos_top = item.offsetTop;
        fieldElement.css({
            'position': 'absolute',
            'top': pos_top+'px',
            'left': '20px'
        });

        this._on(fieldElement.find('.ui-icon'), {
            'click': function(event){
                let val = null;
                if($(event.target).hasClass('ui-icon-circle-b-arrow-n')){
                    val = 'asc';
                }else if($(event.target).hasClass('ui-icon-circle-b-arrow-s')){
                    val = 'desc';
                }else if($(event.target).hasClass('ui-icon-circle-b-minus')){
                    val = '';
                }
                if(val || val == ''){
                    this._handleSortByField($(event.target).parent().attr('data-code'), false, val);
                }
            }
        });
    },

    /**
     * Remove the options for a field in the UI by field code.
     *
     * @param {string} fieldCode The code of the field.
     * @private
     */
    _removeFieldAdvancedOptionsByCode: function (fieldCode) {
        this._$('.export-advanced-list-item').each(function () {
            if ($(this).data('field-code') === fieldCode) {
                $(this).remove();
            }
        });
    },

    _handleSortByField: function(code, isGet, new_value=''){

        let iconSet = $('div[data-code="'+code+'"]');
        if(iconSet.length == 0){
            return null;
        }

        let value = iconSet.attr('data-value');

        if(!value && value != ''){

            if(iconSet.find('span.ui-icon-circle-arrow-n').length == 1){
                value = 'asc';
            }else if(iconSet.find('span.ui-icon-circle-arrow-s').length == 1){
                value = 'desc';
            }else{
                value = '';
            }
            iconSet.attr('data-value', value);
        }

        if(isGet){
            return value;
        }

        iconSet.attr('data-value', new_value);
        new_value = !new_value || new_value == '' || (new_value != 'asc' && new_value != 'desc') ? '' : new_value;

        if(value == new_value){
            return value;
        }

        if(value == 'asc'){
            iconSet.find('span.ui-icon-circle-arrow-n').removeClass('ui-icon-circle-arrow-n').addClass('ui-icon-circle-b-arrow-n');
        }else if(value == 'desc'){
            iconSet.find('span.ui-icon-circle-arrow-s').removeClass('ui-icon-circle-arrow-s').addClass('ui-icon-circle-b-arrow-s');
        }else{
            iconSet.find('span.ui-icon-circle-minus').removeClass('ui-icon-circle-minus').addClass('ui-icon-circle-b-minus');
        }

        if(new_value == 'asc'){
            iconSet.find('span.ui-icon-circle-b-arrow-n').removeClass('ui-icon-circle-b-arrow-n').addClass('ui-icon-circle-arrow-n');
        }else if(new_value == 'desc'){
            iconSet.find('span.ui-icon-circle-b-arrow-s').removeClass('ui-icon-circle-b-arrow-s').addClass('ui-icon-circle-arrow-s');
        }else{
            iconSet.find('span.ui-icon-circle-b-minus').removeClass('ui-icon-circle-b-minus').addClass('ui-icon-circle-minus');
        }

        return new_value;
    },

    /**
     * Get the advanced options from the UI controls.
     *
     * @param {bool} for_export - whether to return values for CSV export, or saving settings
     * 
     * @return {Object} The object is keyed by the field code. Each element is an object which
     *   contains the following possible keys:
     *   - total: The total functions applied to the field: 'group', 'sum' or 'count'.
     *   - sort: The sorting option for the field: 'asc' or 'des'.
     *   - use_percentage: boolean value when the total is 'sum' or 'count'.
     * @private
     */
    _getFieldAdvancedOptions: function (for_export) {

        let that = this;

        if (this._$('.export-advanced-list-item').length > 0) {

            let options = {};

            this._$('.export-advanced-list-item').each(function () {

                let option = {};

                let totalSelectValue = $(this).find('.export-advanced-list-item-total-select').val();
                if (totalSelectValue) {
                    option.total = totalSelectValue;
                }

                let sortSelectValue = that._handleSortByField($(this).attr('data-field-code'), true);
                if (sortSelectValue) {
                    option.sort = sortSelectValue;
                }
                if (totalSelectValue === 'sum' || totalSelectValue === 'count') {
                    option.use_percentage = $(this).find('.export-advanced-list-item-percentage-checkbox').prop('checked');
                }

                let key = $(this).data('field-code');
                let rectype = $(this).data('rectype-id');

                if(for_export && !window.hWin.HEURIST4.util.isempty(rectype) && !isNaN(rectype)){
                    // Add title and id options

                    options[`${key}:${rectype}:title`] = option;
                    options[`${key}:${rectype}:ids`] = option;
                }else{
                    options[key] = option;
                }
            });
            return options;
        }
        return null;
    },

    /**
     * Set the advanced option UI controls by the options object.
     *
     * @param {Object} options
     * @private
     */
    _setFieldAdvancedOptions: function (options) {
        let that = this;
        if (options) {
            this._$('.export-advanced-list-item').each(function () {
                let fieldCode = $(this).data('field-code');
                let option;
                if (Object.hasOwn(options,fieldCode)) {
                    option = options[fieldCode];
                    if (Object.hasOwn(option,'total')) {
                        $(this).find('.export-advanced-list-item-total-select').val(option.total);
                        if (option.total === 'sum' || option.total === 'count') {
                            $(this).find('.export-advanced-list-item-percentage').show();
                        }
                    }
                    if (Object.hasOwn(option,'sort')) {
                        that._handleSortByField($(this).attr('data-code'), false, option.sort);
                    }
                    if (Object.hasOwn(option,'use_percentage') && option.use_percentage) {
                        $(this).find('.export-advanced-list-item-percentage-checkbox').prop('checked', true);
                    }
                }
            });
        }
    }

});