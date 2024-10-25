/**
* recordAccess.js - apply ownership and access rights
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

$.widget( "heurist.recordTemplate", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 520,
        width:  800,
        modal:  true,
        title:  'Create comma separated template files',
        currentOwner: 0,
        currentAccess: null,
        currentAccessGroups: null,
        
        htmlContent: 'recordTemplate.html',
        
        recordType: 0
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
    // 0 - download, 1 - open in new window
    //
    doAction: function(mode){

            let header_fields = {ids:'rec_ID',title:'rec_Title',url:'rec_URL',modified:'rec_Modified',tag:'rec_Tags'};
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
                
                //window.hWin.HEURIST4.util.findArrayIndex( dtid, selectedFields[rtid] )<0
                if( selectedFields[rtid].indexOf( dtid )<0 ) {
                    
                    selectedFields[rtid].push(dtid);    
                    
                    //add resource (record pointer) field for parent recordtype
                    __addSelectedField(ids, lvl+2, rtid);
                }
            }
            
            //get selected fields from treeview
            let selectedFields = {};
            let tree = $.ui.fancytree.getTree( this._$('.rtt-tree') );
            let fieldIds = tree.getSelectedNodes(false);
            const len = fieldIds.length;
            
            if(len<1){
                window.hWin.HEURIST4.msg.showMsgFlash('No fields selected. '
            +'Please select at least one field in tree', 2000);
                return;
            }
            
            
            for (let k=0;k<len;k++){
                let node =  fieldIds[k];
                
                if(window.hWin.HEURIST4.util.isempty(node.data.code)) continue;
                
                let ids = node.data.code.split(":");
                
                __addSelectedField(ids, 1, 0);
            }
            let request = {
                'request_id' : window.hWin.HEURIST4.util.random(),
                'rec_RecTypeID': this.options.recordType,
                'db': window.hWin.HAPI4.database,
                'q'  : 't:'+this.options.recordType,
                'format': 'csv',
                'prefs':{
                'fields': selectedFields,
                'csv_delimiter':  ',',//'\t',
                'csv_enclosure':  '"',
                'csv_mvsep':'|',
                'csv_linebreak':'nix', //not used at tne moment
                'csv_header': true,
                'csv_headeronly': true,
                'include_term_ids': 0,
                'include_term_codes': 0,
                'include_term_hierarchy': 0,
                'include_resource_titles': 0
                }};
                
            
            let url = window.hWin.HAPI4.baseURL + 'hserv/controller/record_output.php'
            
            this._$('#postdata').val( JSON.stringify(request) );
            this._$('#postform').attr('action', url);
            this._$('#postform').trigger('submit');
    },
    
    _initControls: function(){
        
        this._super();
        
        $('#sel_record_type').text( $Db.rty(this.options.recordType,'rty_Name') );
        
        this._loadRecordTypesTreeView();
        
        $('.rtt-tree').parent().show();
        
        let that = this;

        this._$('#selectAll').on("click", function(e){
            let treediv = that.element.find('.rtt-tree');

            let check_status = $(e.target).is(":checked");

            if(!treediv.is(':empty') && treediv.fancytree("instance")){
                let tree = $.ui.fancytree.getTree(treediv);
                tree.visit(function(node){
                    if(!node.hasChildren() && node.type != "relmarker" && node.type != "resource" 
                        && (node.getLevel()==2 || (!window.hWin.HEURIST4.util.isempty(node.span) && $(node.span.parentNode.parentNode).is(":visible")))
                    ){    
                        node.setSelected(check_status);
                    }
                });
            }
        });
        
        return true;
    },
    
    //
    // show treeview with record type structure as popup
    //
    _loadRecordTypesTreeView: function(){
        
        let that = this;
        
        const rtyID = this.options.recordType;

            
            //generate treedata from rectype structure
            let treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, rtyID, ['ID','url','tags','all'] );
            
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
                    if( data.node.hasChildren() ){
                        
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

                    if(data.node.parent && data.node.parent.type == 'resource' || data.node.parent.type == 'relmarker'){ // add left border+margin
                        $(data.node.li).attr('style', 'border-left: black solid 1px !important;margin-left: 9px;');
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
                    
                    let res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, 
                                        rectypes, ['ID','url','tags','all'], parentcode );
                    if(res.length>1){
                        data.result = res;
                    }else{
                        data.result = res[0].children;
                    }
                    
                    return data;                                                   
                },
                select: function(e, data) {
                    let node = data.node;
                    let fieldIds = node.tree.getSelectedNodes(false);
                    let isdisabled = fieldIds.length<1;
                    window.hWin.HEURIST4.util.setDisabled( that.element.parents('.ui-dialog').find('#btnDoAction'), isdisabled );
                },
                click: function(e, data){

                    if(data.node.type == 'separator'){
                        return false;
                    }

                    let isExpander = $(e.originalEvent.target).hasClass('fancytree-expander');

                    if(isExpander){
                        return;
                    }

                    if(data.node.getLevel()<2){ //always expanded
                        data.node.setExpanded(true);
                    }else
                    if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){

                        data.node.setExpanded(!data.node.isExpanded());

                    }else if( data.node.lazy) {
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
    
  
});
