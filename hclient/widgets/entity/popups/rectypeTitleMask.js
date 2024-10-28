/**
* rectypeTitleMask.js - select fields to be exported to CSV for current recordset
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

$.widget( "heurist.rectypeTitleMask", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 700,
        width:  800,
        modal:  true,
        title:  'Record Type Title Mask Edit',
        default_palette_class: 'ui-heurist-design', 
        
        path: 'widgets/entity/popups/', //location of this widget
        rty_ID: 0, 
        rty_TitleMask: '',
        
        htmlContent: 'rectypeTitleMask.html'
    },

    action_in_progress: false,
    
    _destroy: function() {
        this._super(); 
        
        let treediv = this.element.find('.rtt-tree');
        if(!treediv.is(':empty') && treediv.fancytree("instance")){
            treediv.fancytree("destroy");
        }
        treediv.remove();
        
    },

    //
    // overwrite
    //        
    _initControls: function() {
        
        let that = this;
        
        if(!(this.options.rty_ID>0)){
            window.hWin.HEURIST4.msg.showMsgDlg('Record type ID is not defined');
            return;
        }
        
        // Change dialog title
        this.element.dialog('option', 'title', `Edit title mask for ${$Db.rty(this.options.rty_ID, 'rty_Name')}`);

        //init tree
        this._loadRecordTypeTreeView();
        
        //init buttons
        let btn = this.element.find('#btnInsertField').button();
        this._on(btn, {click: this._doInsert});

        btn = this.element.find('#btnTestMask').button();
        this._on(btn, {click: this._doTest});

        this._on(this.element.find('#selectAll'), {click: 
            function(e){

                let treediv = that.element.find('.rtt-tree');

                let check_status = $(e.target).is(":checked");

                if(!treediv.is(':empty') && treediv.fancytree("instance")){
                    let tree = $.ui.fancytree.getTree(treediv);
                    tree.visit(function(node){ 

                        if(!node.hasChildren() && node.type != "relmarker" && node.type != "resource" 
                            && (node.getLevel()==1 || (!window.hWin.HEURIST4.util.isempty(node.span) && $(node.span.parentNode.parentNode).is(":visible")))
                        ){    
                            node.setSelected(check_status);
                        }
                    });
                }
            }
        });

        this.element.find('#rty_TitleMask').val(this.options.rty_TitleMask);
        
        //load list of records for testing 
        let request = {q: 't:'+this.options.rty_ID, w: 'all', detail:'header', limit:100 };
         
        window.hWin.HAPI4.RecordSearch.doSearchWithCallback( request, function( recordset )
        {
            if(recordset!=null){
                
                // it returns several record of given record type to apply tests
                //fill list of records
                let sel = that.element.find('#listRecords')[0];
                //clear selection list
                while (sel.length>1){
                    sel.remove(1);
                }

                let recs = recordset.getRecords();
                for(let rec_ID in recs) 
                if(rec_ID>0){
                    window.hWin.HEURIST4.ui.addoption(sel, rec_ID, 
                        window.hWin.HEURIST4.util.stripTags(recordset.fld(recs[rec_ID], 'rec_Title')));
                }

                sel.selectedIndex = 0;
                
            }
        });
        
        
        this.popupDialog();
        
        //show hide hints and helps according to current level
        window.hWin.HEURIST4.ui.applyCompetencyLevel(-1, this.element); 
        
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('.btnDoAction'), false );
        
        return true;
    },
    
    //    
    //
    //
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Save Mask');
        res[0].text = window.hWin.HR('Cancel');
        return res;
    },    
        
    //
    // Save title mask
    //
    doAction: function(){
        this._doSave_Step1_Verification();
    },
    
    //
    // Insert field(s)
    //
    _doInsert: function(){
        
        let textedit = this.element.find('#rty_TitleMask')[0],
        _text = '';

        
        let tree = $.ui.fancytree.getTree( this.element.find('.rtt-tree') );
        let fieldIds = tree.getSelectedNodes(false);
        let k, len = fieldIds.length;
        
        if(len<1){
            window.hWin.HEURIST4.msg.showMsgFlash('No fields selected. '
                +'Please select at least one field in tree', 2000);
            return false;
        }
        
        
        for (k=0;k<len;k++){
            let node =  fieldIds[k];
            
            if(window.hWin.HEURIST4.util.isempty(node.data.code)) continue;

            _text = _text + '['+node.data.code+']\r\n'; //node.data.full_path
        }
        if(_text!=='')    {
            this._insertAtCursor(textedit, _text);
            
            //clear selection
            tree.visit(function(node){
                node.setSelected(false);
            });
        }
        
        
    },

    //
    // utility function - TODO: move to HEURIST4.ui
    //
    _insertAtCursor: function(myField, myValue) {
        //IE support
        if (document.selection) {
            myField.focus();
            let sel = document.selection.createRange();
            sel.text = myValue;
        }
        //MOZILLA/NETSCAPE support
        else if (myField.selectionStart || myField.selectionStart == '0') {
            let startPos = myField.selectionStart;
            let endPos = myField.selectionEnd;
            myField.value = myField.value.substring(0, startPos)
            + myValue
            + myField.value.substring(endPos, myField.value.length);
        } else {
            myField.value += myValue;
        }
    },
    
    
    //
    // Verify title mask
    //
    _doTest: function(){
        
        let that = this;

        //verify text title mask    
        let mask = this.element.find('#rty_TitleMask').val().replace(/  +/g, ' '); // condense multiple spaces into one /[\t ]+/g
        this.element.find('#rty_TitleMask').val(mask);

        let baseurl = window.hWin.HAPI4.baseURL + "hserv/controller/rectype_titlemask.php";

        let request = {rty_id:this.options.rty_ID, mask:mask, db:window.hWin.HAPI4.database, check:1}; //verify titlemask
        
        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, 
            function (response) {
                if(response.status != window.hWin.ResponseStatus.OK || response.message){

                    
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    
                }else{
                    
                    
                    
                    if(that.element.find('#listRecords > option').length>1){
                        
                        let sel = that.element.find("#listRecords")[0];
                        if (sel.selectedIndex>0){

                            let rec_id = sel.value;
                            
                            let request2 = {rty_id:that.options.rty_ID, rec_id:rec_id, mask:mask, 
                                     db:window.hWin.HAPI4.database}; //verify titlemask
                                     
                            window.hWin.HEURIST4.util.sendRequest(baseurl, request2, null,
                                function (response) {
                                    if(response.status == window.hWin.ResponseStatus.OK){
                                        that.element.find('#testResult').html(response.data);
                                    }else{
                                        window.hWin.HEURIST4.msg.showMsgErr(response);
                                    }
                                });
                        }else{
                            window.hWin.HEURIST4.msg.showMsgFlash('Select a record from the pulldown to test your title mask');
                        }
                    }
                    
                }                                        
            }
        );
        
    },

    //
    // First step: check title mask
    //
    _doSave_Step1_Verification: function()
    {
        if(this.action_in_progress) return;
        this.action_in_progress = true;

        let that = this;
        
        //verify text mask 
        let mask = this.element.find('#rty_TitleMask').val().replace(/  +/g, ' '); // condense multiple spaces into one
        this.element.find('#rty_TitleMask').val(mask);

        let baseurl = window.hWin.HAPI4.baseURL + 'hserv/controller/rectype_titlemask.php';

        let request = {rty_id:that.options.rty_ID, mask:mask, db: window.hWin.HAPI4.database, check:1}; //verify titlemask
        
        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, 
            function (response) {
                
                if(response.status != window.hWin.ResponseStatus.OK || response.message){
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    that.action_in_progress = false;
                }else{
                    that._doSave_Step2_SaveRectype();
                }                                        
            }
        );
        
    },
    
    //
    // Second step - update record type definition
    //
    _doSave_Step2_SaveRectype: function(){
                    
            let newvalue = this.element.find('#rty_TitleMask').val();
            if(newvalue != this.options.rty_TitleMask){
                
                let that = this;

                window.hWin.HEURIST4.dbs.rty(this.options.rty_ID, 'rty_TitleMask', newvalue); //update in cache
                
                // NEW - @todo
                let fields = {rty_ID:this.options.rty_ID, rty_TitleMask:newvalue};
                
                let request = {
                    'a'          : 'save',
                    'entity'     : 'defRecTypes',
                    'request_id' : window.hWin.HEURIST4.util.random(),
                    'fields'     : fields,
                    'isfull'     : false
                    };
                
                window.hWin.HAPI4.EntityMgr.doRequest(request, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK ){
                            that._updateTitleMask();        
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                            that.action_in_progress = false;
                        }
                    });                
                
                /*
                var _defs = {};
                _defs[this.options.rty_ID] = [{common:[newvalue],dtFields:[]}];
                var oRectype = {rectype:{colNames:{common:['rty_TitleMask'],dtFields:[]},
                            defs:_defs}}; //{_rectypeID:[{common:[newvalue],dtFields:[]}]}
                
                var baseurl = window.hWin.HAPI4.baseURL + "admin/structure/saveStructure.php"; //saveRT
                
                var request = {method:'saveRT', db:window.hWin.HAPI4.database, data:oRectype, no_purify:1 }; //styep
                
                window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, 
                    function (response) {
                        if(response.status == window.hWin.ResponseStatus.OK ){
                            that._updateTitleMask();        
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                            that.action_in_progress = false;
                        }
                    }                
                );
                */
                
            }else{
                this.action_in_progress = false;
                this._context_on_close = newvalue;
                this.closeDialog();
            }                    
            
    },
    
    /**
    * Third step - update records - change title
    */
    _updateTitleMask: function(){
        
        let that = this;
        
        //recalcTitlesSpecifiedRectypes.php
        let sURL = window.hWin.HAPI4.baseURL + 'admin/verification/longOperationInit.php?type=titles&db='
                                +window.hWin.HAPI4.database+"&recTypeIDs="+this.options.rty_ID;

        window.hWin.HEURIST4.msg.showDialog(sURL, {

                "close-on-blur": false,
                "no-resize": true,
                height: 400,
                width: 400,
                callback: function(context) {
                    that.action_in_progress = false;
                }
        });
        
        this._context_on_close = this.element.find('#rty_TitleMask').val();
        this.closeDialog();
    },
    
    
    //
    // show treeview with record type structure
    //
    _loadRecordTypeTreeView: function(rtyID){
        
        //generate treedata from rectype structure
        let treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 3, this.options.rty_ID, ['all','parent_link'] );

        //load treeview
        let treediv = this.element.find('.rtt-tree');
        if(!treediv.is(':empty') && treediv.fancytree("instance")){
            treediv.fancytree("destroy");
        }

        treediv.fancytree({
            //extensions: ["filter"],
            //            extensions: ["select"],
            checkbox: true,
            selectMode: 3,  // hierarchical multi-selection
            source: treedata,
            beforeSelect: function(event, data){
                // A node is about to be selected: prevent this, for folder-nodes:
                if( data.node.hasChildren() ){

                    if(data.node.isExpanded()){
                        for(let i = 0; i < data.node.children.length; i++){
                            let node = data.node.children[i];

                            if(node.key == 'term'){ // if node is a term
                                node.setSelected(true); // auto select 'term' option to add term name
                            }
                        }
                    }
                    return false;
                }
            },
            renderNode: function(event, data){
                if(data.node.type == "enum") { // hide blue and expand arrows for terms
                    $(data.node.span.childNodes[0]).hide();
                    $(data.node.span.childNodes[1]).hide();
                }
                if(data.node.parent && (data.node.parent.type == 'resource' || data.node.parent.type == 'rectype')){ // add left border+margin
                    $(data.node.li).attr('style', 'border-left: black solid 1px !important;margin-left: 9px;');
                }else{

                    if(data.node.parent && data.node.parent.type == 'enum'){ // make term options inline and smaller
                        $(data.node.li).css('display', 'inline-block');
                        $(data.node.span.childNodes[0]).css('display', 'none');

                        if(data.node.key == 'term'){
                            $(data.node.parent.ul).css({'transform': 'scale(0.8)', 'padding': '0px', 'position': 'relative', 'left': '-12px'});
                        }
                    }
                }
                if(data.node.type == 'separator'){
                    $(data.node.span).attr('style', 'background: none !important;color: black !important;'); //stop highlighting
                    $(data.node.span.childNodes[1]).hide(); //checkbox for separators
                }
            },
            lazyLoad: function(event, data){
                
                let node = data.node;
                let parentcode = node.data.code; 
                let rectypes = node.data.rt_ids;

                let res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 3, 
                    rectypes, ['all'], parentcode );
                if(res.length>1){
                    data.result = res;
                }else{
                    data.result = res[0].children;
                }

                return data;                                                   
            },
            loadChildren: function(e, data){
                setTimeout(function(){
                   
                    },500);
            },
            select: function(e, data) {
            },
            click: function(e, data){

                if(data.node.type == 'separator'){
                    return false;
                }

                let isExpander = $(e.originalEvent.target).hasClass('fancytree-expander');
                let setDefaults = !data.node.isExpanded();

                if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){
                    
                    if(!isExpander){
                        data.node.setExpanded(!data.node.isExpanded());
                    }
                
                    if(setDefaults){
                        for(let i = 0; i < data.node.children.length; i++){
                            let node = data.node.children[i];

                            if(node.key == 'term'){ // if node is a term
                                node.setSelected(true); // auto select 'term' option to add term name
                            }
                        }
                    }
                }else if( data.node.lazy && !isExpander) {
                    data.node.setExpanded( true );
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
    },
    
    //
    // Test purposes only
    //
    _doCanonical: function(mode){

        let mask = (mode==2)?this.element.find('#rty_TitleMask').val()
                            :this.element.find('#rty_CanonincalMask').val()
        
        let baseurl = window.hWin.HAPI4.baseURL + "hserv/controller/rectype_titlemask.php";

        let request = {rty_id:this.options.rty_ID, mask:mask, db:window.hWin.HAPI4.database, check:1}; //verify titlemask
        
        let that = this;
        
        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, 
            function (response) {
                if(response.status != window.hWin.ResponseStatus.OK || response.message){

                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    
                }else{
                    if(mode==2){
                        that.element.find('#rty_CanonincalMask').val(response.data);
                    }else{
                        that.element.find('#rty_TitleMask').val(response.data);
                    }
                }                                        
            }
        );        
    }
    
    

});
