/**
* manageDefTerms.js - main widget to manage defTerms
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


$.widget( "heurist.manageRecords", $.heurist.manageEntity, {
   
    _entityName:'records',
    
    _currentEditRecTypeID:null,
    _currentEditRecordset:null,
    
    toolbar:null,
    
    _edit_dialog:null, //keep reference to popup dialog
    
    _init: function() {
        if(this.options.layout_mode=='basic'){ //replace default short 
        this.options.layout_mode = //slightly modified 'short' layout
                        '<div class="ent_wrapper">'
                            +'<div class="ent_wrapper" style="width:200px">'
                                +    '<div class="ent_header searchForm"/>'     
                                +    '<div class="ent_content_full recordList"/>'
                            +'</div>'

                            + '<div class="editFormDialog ent_wrapper">'
                                + '<div class="ui-layout-center"><div class="editForm"/></div>'
                                + '<div class="ui-layout-east"><div class="editFormSummary">empty</div></div>'
                        
                            +'</div>'
                        +'</div>';
        }

        
        
        this.options.use_cache = false;
        this.options.edit_height = 640;
        this.options.edit_width = 1200;

        //for selection mode set some options
        if(this.options.select_mode!='manager'){
            if(this.options.edit_mode != 'inline'){
                this.options.width = 640;      
            }              
        }else{
            this.options.width = 1200;                    
        }
    
        this._super();
        
        /*
        if(!(this.options.select_mode!='manager' || this.options.edit_mode!='inline')){
            //edit form is not visible
            this.recordList.parent().width(640);
            this.editForm.parent().css('left',641);
        }
        */
        this.editForm.parent().show(); //restore 
        this.editFormPopup = this.element.find('.editFormDialog').hide();
        this.editFormSummary = this.element.find('.editFormSummary');
        //this.element.find('#editFormSummary').hide(); //temp
        
        this.element.find('#editFormDialog').hide();
        
        //-----------------
        this.recordList.css('top','5.5em');
        if(this.searchForm && this.searchForm.length>0)this.searchForm.height('7.5em').css('border','none');
        
        
    },
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {
        
        if(!this._super()){
            return false;
        }

        // init search header
        if(this.searchForm && this.searchForm.length>0)this.searchForm.searchRecords(this.options);
/*        
        var iheight = 2;
        //if(this.searchForm.width()<200){  - width does not work here  
        if(this.options.select_mode=='manager'){            
            iheight = iheight + 4;
        }
        
        this.searchForm.css({'height':iheight+'em'});
        this.recordList.css({'top':iheight+0.4+'em'});
*/        
        this.recordList.resultList({
                searchfull:null,
                renderer:true //use default renderer but custom onaction see _onActionListener
        }); //use default recordList renderer
        
        if(this.options.select_mode=='manager'){
            this.recordList.parent().css({'border-right':'lightgray 1px solid'});
        }
        
        if(this.searchForm && this.searchForm.length>0){
        this._on( this.searchForm, {
                "searchrecordsonresult": this.updateRecordList,
                "searchrecordsonaddrecord": function( event, _rectype_id ){
                    this._currentEditRecTypeID = _rectype_id;
                    this.addEditRecord(-1);
                }
        });
        }
                
       //---------    EDITOR PANEL - DEFINE ACTION BUTTONS
       //if actions allowed - add div for edit form - it may be shown as right-hand panel or in modal popup
       if(this.options.edit_mode!='none'){
/*
           //define add button on left side
           this._defineActionButton({key:'add', label:'Add New Vocabulary', title:'', icon:'ui-icon-plus'}, 
                        this.editFormToolbar, 'full',{float:'left'});
                
           this._defineActionButton({key:'add-child',label:'Add Child', title:'', icon:''},
                    this.editFormToolbar);
           this._defineActionButton({key:'add-import',label:'Import Children', title:'', icon:''},
                    this.editFormToolbar);
           this._defineActionButton({key:'merge',label:'Merge', title:'', icon:''},
                    this.editFormToolbar);
               
           //define delete on right side
           this._defineActionButton({key:'delete',label:'Remove', title:'', icon:'ui-icon-minus'},
                    this.editFormToolbar,'full',{float:'right'});
*/                    
       }
        
       return true;
    },


    _navigateToRec: function(dest){
        if(this._currentEditID>0){
                var recset = this.recordList.resultList('getRecordSet');
                var order  = recset.getOrder();
                var idx = order.indexOf(Number(this._currentEditID));
                idx = idx + dest;
                if(idx>=0 && idx<order.length){
                    this.toolbar.find('#divNav').html( (idx+1)+' of '+order.length);
                    if(dest!=0){
                        this.addEditRecord(order[idx]);
                    }
                }
        }
    },    
    //override some editing methods
    
    //
    // open popup edit dialog if need it
    //
    _initEditForm_step1: function(recID){
    
        if(recID!=null && this.options.edit_mode!='none'){ //show in popup 
        
            var isOpenAready = false;
            if(this.options.edit_mode=='popup' && this._edit_dialog){
                try{
                    isOpenAready = this._edit_dialog.dialog('isOpen');
                }catch(e){}
            }

            if(!isOpenAready){            
        
                var that = this; 
                this._currentEditID = recID;
                
                var recset = this.recordList.resultList('getRecordSet');
                var recset_length = recset.length();

                var btn_array = [
                                  /*{text:window.hWin.HR('Reload'), id:'btnRecReload',icons:{primary:'ui-icon-refresh'},
                            click: function() { that._initEditForm_continue(that._currentEditID) }},  //reload edit form*/
                                  {text:window.hWin.HR('Duplicate'), id:'btnRecDuplicate',
                                  css:{'display':((that._currentEditID>0)?'inline-block':'none')},
                            click: function(event) { 
                                var btn = $(event.target);
                                btn.hide();
                                window.hWin.HAPI4.RecordMgr.duplicate({id: that._currentEditID}, 
                                    function(response){
                                        btn.css('display','inline-block');
                                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                                            window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Record has been duplicated'));
                                            var new_recID = ''+response.data.added;
                                            that._initEditForm_continue(new_recID);
                                        }else{
                                            window.hWin.HEURIST4.msg.showMsgErr(response);
                                        }
                                    }); 
                            }},
                                  {text:window.hWin.HR('Previous'),icons:{primary:'ui-icon-circle-triangle-w'},
                                  css:{'display':((recset_length>1 && recID>0)?'inline-block':'none')}, id:'btnPrev',
                            click: function() { that._navigateToRec(-1); }},
                                  {text:window.hWin.HR('Next'),icons:{secondary:'ui-icon-circle-triangle-e'},
                                  css:{'display':((recset_length>1 && recID>0)?'inline-block':'none')},
                            click: function() { that._navigateToRec(1); }},
                                  {text:window.hWin.HR('Cancel'), id:'btnRecCancel', 
                                  css:{'visibility':'hidden'},
                            click: function() { that._initEditForm_continue(that._currentEditID) }},  //reload edit form
                                  {text:window.hWin.HR('Save and new record'), id:'btnRecSaveAndNew',
                                  css:{'visibility':'hidden'},
                            click: function() { that._saveEditAndClose( 'newrecord' ); }},
                                  {text:window.hWin.HR('Save'), id:'btnRecSave',
                                  css:{'visibility':'hidden'},
                            click: function() { that._saveEditAndClose( 'none' ); }},
                                  {text:window.hWin.HR('Save and Close'), id:'btnRecSaveAndClose',
                                  css:{'visibility':'hidden'},
                            click: function() { that._saveEditAndClose( 'close' ); }},
                                  {text:window.hWin.HR('Close'), 
                            click: function() { that.closeEditDialog(); }}]; 
                
                if(this.options.edit_mode=='popup'){
                
                    this.editForm.css({'top': 0, overflow:'auto !important'});
                 
                    this._edit_dialog =  window.hWin.HEURIST4.msg.showElementAsDialog({
                            window:  window.hWin, //opener is top most heurist window
                            element:  this.editFormPopup[0],
                            height: this.options['edit_height']?this.options['edit_height']:640,
                            width:  this.options['edit_width']?this.options['edit_width']:1200,
                            title: this.options['edit_title']
                                        ?this.options['edit_title']
                                        :window.hWin.HR('Edit') + ' ' +  this.options.entity.entityName,                         
                            buttons: btn_array
                        });
                    
                 
/*                    this.editFormPopup.dialog({
                        autoOpen: true,
                        height: this.options['edit_height']?this.options['edit_height']:400,
                        width:  this.options['edit_width']?this.options['edit_width']:740,
                        modal:  true,
                        title: this.options['edit_title']
                                    ?this.options['edit_title']
                                    :window.hWin.HR('Edit') + ' ' +  this.options.entity.entityName,
                        resizeStop: function( event, ui ) {//fix bug
                            that.element.css({overflow: 'none !important','width':that.element.parent().width()-24 });
                        },
                        buttons: btn_array
                    });        
*/                    
                    //help and tips buttons on dialog header
                    window.hWin.HEURIST4.ui.initDialogHintButtons(this._edit_dialog,
                     window.hWin.HAPI4.baseURL+'context_help/'+this.options.entity.helpContent+' #content');
            
                    this.toolbar = this._edit_dialog.parent(); //this.editFormPopup.parent();
            
                }//popup
                else if(this.editFormToolbar){ //initialize action buttons
                    
                    if(!this.options.in_popup_dialog){
                        btn_array.pop();btn_array.pop(); //remove to last buttons about close
                    }else{
                         //this.editFormPopup.css({'top': 0});
                         this.editFormToolbar
                         .addClass('ui-dialog-buttonpane')
                         .css({
                            padding: '0.8em 1em .2em .4em',
                            background: 'none',
                            'background-color': '#95A7B7 !important',
                            'text-align':'right'
                         });
                    }
                    
                    this.toolbar = this.editFormToolbar;
                    this.editFormToolbar.empty();
                    for(var idx in btn_array){
                        this._defineActionButton2(btn_array[idx], this.editFormToolbar);
                    }
                }

                if(recset_length>1 && recID>0){
                    $('<div id="divNav" style="min-width:40px;padding:0 1em 0 0;display:inline-block;text-align:center">')
                        .insertAfter(this.toolbar.find('#btnPrev'));
                    this._navigateToRec(0);
                }
                    
                if(this.editFormSummary && this.editFormSummary.length>0){    
                    var layout_opts =  {
                        applyDefaultStyles: true,
                        togglerContent_open:    '<div class="ui-icon ui-icon-triangle-1-e"></div>',
                        togglerContent_closed:  '<div class="ui-icon ui-icon-triangle-1-w"></div>',
                        //togglerContent_open:    '&nbsp;',
                        //togglerContent_closed:  '&nbsp;',
                        east:{
                            size: 400,
                            maxWidth:800,
                            spacing_open:6,
                            spacing_closed:16,  
                            togglerAlign_open:'center',
                            togglerAlign_closed:'top',
                            togglerLength_closed:16,  //makes it square
                            initClosed:true,
                            slidable:false,  //otherwise it will be over center and autoclose
                            contentSelector: '.editFormSummary'    
                        },
                        center:{
                            minWidth:800,
                            contentSelector: '.editForm'    
                        }

                    };

                    this.editFormPopup.addClass('ui-heurist-bg-light').show().layout(layout_opts);

                    //load content for editFormSummary
                    if(this.editFormSummary.text()=='empty'){
                        this.editFormSummary.empty();
                        var headers = ['Admin','Links','Scratchpad','Private','Workgroup Tags','Text','Discussion'];
                        for(var idx in headers){
                            $('<h3>').text(top.HR(headers[idx])).appendTo(this.editFormSummary);
                            //content
                                $('<div>').attr('data-id', idx).addClass('summary-content').appendTo(this.editFormSummary);
                        }
                        this.editFormSummary.accordion({
                            collapsible: true,
/*                            
                            beforeActivate: function(event, ui) {
        
                                if(ui.newPanel.length>0){
                                    panelToActivate = ui.newPanel;
                                }else{
                                    panelToActivate = ui.oldPanel;
                                }
                                if(panelToActivate.text()=='' && Number(panelToActivate.attr('data-id'))>=0 ){
                                    //load content for panel to be activated
                                    that._fillSummaryPanel(panelToActivate);
                                }
                                
                                 // The accordion believes a panel is being opened
                                if (ui.newHeader[0]) {
                                    var currHeader  = ui.newHeader;
                                    var currContent = currHeader.next('.ui-accordion-content');
                                 // The accordion believes a panel is being closed
                                } else {
                                    var currHeader  = ui.oldHeader;
                                    var currContent = currHeader.next('.ui-accordion-content');
                                }
                                 // Since we've changed the default behavior, this detects the actual status
                                var isPanelSelected = currHeader.attr('aria-selected') == 'true';

                                 // Toggle the panel's header
                                currHeader.toggleClass('ui-corner-all',isPanelSelected).toggleClass('accordion-header-active ui-state-active ui-corner-top',!isPanelSelected).attr('aria-selected',((!isPanelSelected).toString()));

                                // Toggle the panel's icon
                                currHeader.children('.ui-icon').toggleClass('ui-icon-triangle-1-e',isPanelSelected).toggleClass('ui-icon-triangle-1-s',!isPanelSelected);

                                 // Toggle the panel's content
                                currContent.toggleClass('accordion-content-active',!isPanelSelected)    
                                if (isPanelSelected) { currContent.slideUp(); }  else { currContent.slideDown(); }

                                return false; // Cancels the default action
                            },                            
*/                            
                            heightStyle: "content",
                            beforeActivate:function(event, ui){
                                if(ui.newPanel.text()==''){
                                    //load content for panel to be activated
                                    that._fillSummaryPanel(ui.newPanel);
                                }
                            }
                        });
                    }
                }
            }//!isOpenAready
            
        }
        this._initEditForm_continue(recID); 
    },
    
    //
    //
    //
    closeEditDialog:function(){
        if(this.options.in_popup_dialog==true){
            window.close(this._currentEditRecordset);
        }else if(this._edit_dialog && this._edit_dialog.dialog('isOpen')){
            this._edit_dialog.dialog('close');
        }
    },
    
    //
    // fill one of summary tab panels
    //
    _fillSummaryPanel: function(panel){
        
        var sContent = '';
        var idx = Number(panel.attr('data-id'));
        var that = this;
        
        var ph_gif = window.hWin.HAPI4.baseURL + 'hclient/assets/16x16.gif';
        
        switch(idx){
            case 0:   //admins
                var recRecTypeID = that._getField('rec_RecTypeID');
                sContent =  
'<div style="margin:10px;bacground:none;"><div style="padding-bottom:0.5em">'

+'<h2 class="truncate rectypeHeader" style="display:inline-block;" style="max-width:400px;margin-left:5px;">'
                + '<img src="'+ph_gif+'" style="vertical-align:top;margin-left:10px;background-image:url(\''
                + top.HAPI4.iconBaseURL+recRecTypeID+'\');"/>'
                + window.hWin.HEURIST4.rectypes.names[recRecTypeID]+'</h2>'
+'<select class="rectypeSelect" style="display:none"></select>'
+'<div class="btn-config2"/><div class="btn-config"/><div class="btn-modify"/></div>'

+'<div><label class="small-header">Owner:</label><span id="recOwner">'
    +that._getField('rec_OwnerUGrpID')+'</span><div class="btn-access"/></div>'
+'<div style="padding-bottom:0.5em"><label class="small-header">Access:</label><span id="recAccess">'
    +that._getField('rec_NonOwnerVisibility')+'</span></div>'

+'<div><label class="small-header">Added By:</label><span id="recAddedBy">'+that._getField('rec_AddedByUGrpID')+'</span></div>'
+'<div><label class="small-header">Added:</label>'+that._getField('rec_Added')+'</div>'
+'<div><label class="small-header">Updated:</label>'+that._getField('rec_Modified')+'</div>';

                $(sContent).appendTo(panel);
                //activate buttons
                panel.find('.btn-config2').button({text:false,label:top.HR('Modify record type structure in new window'),
                        icons:{primary:'ui-icon-extlink'}})
                    .css({float: 'right','font-size': '0.8em', height: '18px'})
                    .click(function(){
                        that.editRecordTypeOnNewTab();
                    });
                    
                panel.find('.btn-config').button({text:false,label:top.HR('Modify record type structure'),
                        icons:{primary:'ui-icon-gear'}})
                    .css({float: 'right','font-size': '0.8em', height: '18px'})
                    .click(function(){that.editRecordType();});

                    
                panel.find('.btn-modify').button({text:false, label:top.HR('Change record type'),
                        icons:{primary:'ui-icon-pencil'}})
                    .css({float: 'right','font-size': '0.8em', height: '18px'})
                    .click(function(){
                         var selRt = panel.find('.rectypeSelect');
                         var selHd = panel.find('.rectypeHeader');
                         if(selRt.is(':visible')){
                             selRt.hide();
                             selHd.css({'display':'inline-block'});
                             
                         }else{
                             selRt.css({'display':'inline-block'});
                             selHd.hide();
                             if(selRt.is(':empty')){
                                window.hWin.HEURIST4.ui.createRectypeSelect(selRt.get(0));    
                                selRt.change(function(){
                                    
                                      that._editing.assignValuesIntoRecord();
                                      var record = that._currentEditRecordset.getFirstRecord();
                                      that._currentEditRecordset.setFld(record, 'rec_RecTypeID', selRt.val());
                                      that._initEditForm_finalize(null);
                                });
                             }
                             selRt.val(recRecTypeID);
                         }
                        
                         
                    });
                    

                panel.find('.btn-access').button({text:false,label:top.HR('Change ownership and access right'),
                        icons:{primary:'ui-icon-eye'}})
                    .css({float: 'right','margin-top': '0.8em', 'font-size': '0.8em', height: '18px'})
                    .click(function(){
                    
        var url = window.hWin.HAPI4.baseURL + 'hclient/framecontent/recordAction.php?db='+window.hWin.HAPI4.database+'&action=ownership&owner='+that._getField('rec_OwnerUGrpID')+'&scope=noscope&access='+that._getField('rec_NonOwnerVisibility');

        window.hWin.HEURIST4.msg.showDialog(url, {height:300, width:500,
            padding: '0px',
            resizeable:false,
            title: window.hWin.HR('ownership'),
            callback: function(context){

                if(context && context.owner && context.access){
                    
                    var ele = that._editing.getFieldByName('rec_OwnerUGrpID');
                    var vals = ele.editing_input('getValues');
                    
                    if(vals[0]!=context.owner){
                        ele.editing_input('setValue',[context.owner]);
                        ele.editing_input('isChanged', true);
                        
                        if(context.owner == window.hWin.HAPI4.currentUser['ugr_ID']){
                            sUserName = window.hWin.HAPI4.currentUser['ugr_FullName'];
                        }else{
                            sUserName = window.hWin.HAPI4.currentUser.usr_GroupsList[Number(context.owner)][1];
                        }
                        
                        panel.find('#recOwner').html(sUserName);
                    }

                    ele = that._editing.getFieldByName('rec_NonOwnerVisibility');
                    vals = ele.editing_input('getValues');
                    if(vals[0]!=context.access){
                        ele.editing_input('setValue',[context.access]);
                        ele.editing_input('isChanged', true);
                        panel.find('#recAccess').html(context.access);
                    }
                    that.onEditFormChange();
                }
                
            },
            class:'ui-heurist-bg-light'} );                    
                    
                    });
            

            window.hWin.HAPI4.SystemMgr.usr_names({UGrpID:[that._getField('rec_OwnerUGrpID'),that._getField('rec_AddedByUGrpID')]},
                function(response){
                    if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                        panel.find('#recOwner').text(response.data[that._getField('rec_OwnerUGrpID')]);
                        panel.find('#recAddedBy').text(response.data[that._getField('rec_AddedByUGrpID')]);
                    }
            });
            
            
            
                break;
            case 1:   //find all reverse links
            
                var relations = that._currentEditRecordset.getRelations();    
                var direct = relations.direct;
                var reverse = relations.reverse;
                var headers = relations.headers;
                var ele1=null, ele2=null;
                
                //relations                            
                var sRel_Ids = [];
                for(var k in direct){
                    if(direct[k]['trmID']>0){ //relation    
                        var targetID = direct[k].targetID;
                        sRel_Ids.push(targetID);
                        
                        var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo(panel, 
                            {rec_ID: targetID, 
                             rec_Title: headers[targetID][0], 
                             rec_RecTypeID: headers[targetID][1], 
                             relation_recID: direct[k]['relationID'], 
                             trm_ID: direct[k]['trmID']}, true);
                        if(!ele1) ele1 = ele;     
                    }
                }
                for(var k in reverse){
                    if(reverse[k]['trmID']>0){ //relation    
                        var sourceID = reverse[k].sourceID;
                        sRel_Ids.push(sourceID);
                        
                        var invTermID = window.hWin.HEURIST4.ui.getInverseTermById(reverse[k]['trmID']);
                        
                        var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo(panel, 
                            {rec_ID: sourceID, 
                             rec_Title: headers[sourceID][0], 
                             rec_RecTypeID: headers[sourceID][1], 
                             relation_recID: reverse[k]['relationID'], 
                             trm_ID: invTermID}, true);
                        if(!ele1) ele1 = ele;     
                    }
                }

                var sLink_Ids = [];
                for(var k in reverse){
                    if(!(reverse[k]['trmID']>0)){ //links    
                        var sourceID = reverse[k].sourceID;
                        sLink_Ids.push(sourceID);
                        
                        var ele = window.hWin.HEURIST4.ui.createRecordLinkInfo(panel, 
                            {rec_ID: sourceID, 
                             rec_Title: headers[sourceID][0], 
                             rec_RecTypeID: headers[sourceID][1]}, true);
                        if(!ele2) ele2 = ele;     
                    }
                }
                
                if(sRel_Ids.length>0){
                    $('<div class="detailRowHeader">Related</div>').insertBefore(ele1);
                }
                if(sLink_Ids.length>0){
                    $('<div class="detailRowHeader">Linked from</div>').insertBefore(ele2);
                }

                panel.css({'font-size':'0.9em','line-height':'1.5em','overflow':'hidden !important'});
                            
/*            
                window.hWin.HAPI4.RecordMgr.search_related({ids:this._currentEditID}, //direction: -1}, 
                    function(response){
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                                    
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    }
                );
*/                
                break;
            case 2:   //scrtachpad
            
                //find field in hEditing
                var ele = that._editing.getFieldByName('rec_ScratchPad');
                ele.editing_input('option',{showclear_button:false, show_header:false});
                ele[0].parentNode.removeChild(ele[0]);                
                ele.css({'display':'block','width':'99%'});
                ele.find('textarea').attr('rows', 10).css('width','100%');
                ele.show().appendTo(panel);
            
                break;
            case 3:   //private
            
                break;
            default:
                sContent = '<p>to be implemented</p>';
        }

        if(idx>1) $(sContent).appendTo(panel);
        
    },


    //
    // Open Edit record structure on new tab
    //
    editRecordTypeOnNewTab: function(){

        var that = this;
        
        var smsg = "<p>Changes made to the record type will not become active until you reload this page (hit page reload in your browser).</p>";
        
        if(this._editing.isModified()){
            var smsg = smsg + "<br/>Please SAVE the record first in order not to lose data";
        }
        window.hWin.HEURIST4.msg.showMsgDlg(smsg);

        var url = window.hWin.HAPI4.baseURL + 'admin/adminMenuStandalone.php?db='
            +window.hWin.HAPI4.database
            +'&mode=rectype&rtID='+that._currentEditRecTypeID;
        window.open(url, '_blank');
    },
    
    //
    //
    //
    editRecordType: function(){

        var that = this;
        
        if(this._editing.isModified()){
            
                var sMsg = "Click YES to save changes and modify the record structure.<br>"
                            +"If you are unable to save changes, click Cancel and open<br>"
                            +"structure modification in a new tab (button next to clicked one)";
                window.hWin.HEURIST4.msg.showMsgDlg(sMsg, function(){
                    
                        that._saveEditAndClose(function(){
                            that._editing.initEditForm(null, null); //clear edit form
                            that._initEditForm_continue(that._currentEditID); //reload edit form                       
                            that.editRecordType();
                        })
                });   
                return;         
        }
        

        var url = window.hWin.HAPI4.baseURL + 'admin/structure/fields/editRecStructure.html?db='+window.hWin.HAPI4.database
            +'&rty_ID='+that._currentEditRecTypeID;

        var body = $(window.hWin.document).find('body');
            
        window.hWin.HEURIST4.msg.showDialog(url, {
            height: body.innerHeight()*0.9,
            width: 860,
            padding: '0px',
            title: window.hWin.HR('Edit record structure'),
            callback: function(context){
                    if(!top.HEURIST.util.isnull(context) && context) {
                        that._initEditForm_continue(that._currentEditID); //reload form
                    }
            }
        });        
        
    },
    
    //
    // get field for currently editing record
    //
    _getField: function(fname){
        
        if(this._currentEditRecordset){
            var record = this._currentEditRecordset.getFirstRecord();
            var value  = this._currentEditRecordset.fld(record, fname);
            return value;
        }else{
            return '';
        }
    },
    
    //
    //
    //
    _initEditForm_continue: function(recID){
        
        //fill with values
        this._currentEditID = recID;
        
        var that = this;
        
        //clear content of accordion
        if(this.editFormSummary && this.editFormSummary.length>0){
            this.editFormSummary.find('.summary-content').empty();
            this.editFormSummary.accordion({active: false});
        }
        
        if(recID==null){
            this._editing.initEditForm(null, null); //clear and hide
        }else if(recID>0){ //edit existing record
            
            window.hWin.HAPI4.RecordMgr.search({q: 'ids:'+recID, w: "all", f:"complete", l:1}, 
                        function(response){ that._initEditForm_finalize(response); });

        }else if(recID<0 && this._currentEditRecTypeID>0){ //add new record
            //this._currentEditRecTypeID is set in add button
            window.hWin.HAPI4.RecordMgr.add( {rt:this._currentEditRecTypeID, temp:1}, //ro - owner,  rv - visibility
                        function(response){ that._initEditForm_finalize(response); });
        }
        
        
        

        return;
    },
    
    //
    //
    //
    _getFakeRectypeField: function(detailTypeID){
        
        var dt = window.hWin.HEURIST4.detailtypes.typedefs[detailTypeID]['commonFields'];
        
        var fieldIndexMap = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex;
        var dtyFieldNamesIndexMap = window.hWin.HEURIST4.detailtypes.typedefs.fieldNamesToIndex;
           
        //init array 
        var ffr = [];
        var l = window.hWin.HEURIST4.rectypes.typedefs.dtFieldNames.length;
        var i;
        for (i=0; i<l; i++){ffr.push("");}
            
        ffr[fieldIndexMap['rst_DisplayName']] = dt?dt[dtyFieldNamesIndexMap['dty_Name']]:'Fake field';
        ffr[fieldIndexMap['dty_FieldSetRectypeID']] = dt?dt[dtyFieldNamesIndexMap['dty_FieldSetRectypeID']] : 0;
        ffr[fieldIndexMap['dty_TermIDTreeNonSelectableIDs']] = (dt?dt[dtyFieldNamesIndexMap['dty_TermIDTreeNonSelectableIDs']]:"");
        ffr[fieldIndexMap['rst_TermIDTreeNonSelectableIDs']] = (dt?dt[dtyFieldNamesIndexMap['dty_TermIDTreeNonSelectableIDs']]:"");
        ffr[fieldIndexMap['rst_MaxValues']] = 1;
        ffr[fieldIndexMap['rst_MinValues']] = 0;
        ffr[fieldIndexMap['rst_CalcFunctionID']] = null;
        ffr[fieldIndexMap['rst_DefaultValue']] = null;
        ffr[fieldIndexMap['rst_DisplayDetailTypeGroupID']] = (dt?dt[dtyFieldNamesIndexMap['dty_DetailTypeGroupID']]:"");
        ffr[fieldIndexMap['rst_DisplayExtendedDescription']] = (dt?dt[dtyFieldNamesIndexMap['dty_ExtendedDescription']]:"");
        ffr[fieldIndexMap['rst_DisplayHelpText']] = (dt?dt[dtyFieldNamesIndexMap['dty_HelpText']]:"");
        ffr[fieldIndexMap['rst_DisplayOrder']] = 999;
        ffr[fieldIndexMap['rst_DisplayWidth']] = 50;
        ffr[fieldIndexMap['rst_FilteredJsonTermIDTree']] = (dt?dt[dtyFieldNamesIndexMap['dty_JsonTermIDTree']]:"");
        ffr[fieldIndexMap['rst_LocallyModified']] = 0;
        ffr[fieldIndexMap['rst_Modified']] = 0;
        ffr[fieldIndexMap['rst_NonOwnerVisibility']] = (dt?dt[dtyFieldNamesIndexMap['dty_NonOwnerVisibility']]:"viewable");
        ffr[fieldIndexMap['rst_OrderForThumbnailGeneration']] = 0;
        ffr[fieldIndexMap['rst_OriginatingDBID']] = 0;
        ffr[fieldIndexMap['rst_PtrFilteredIDs']] = (dt?dt[dtyFieldNamesIndexMap['dty_PtrTargetRectypeIDs']]:"");
        ffr[fieldIndexMap['rst_RecordMatchOrder']] = 0;
        ffr[fieldIndexMap['rst_RequirementType']] = 'optional';
        ffr[fieldIndexMap['rst_Status']] = (dt?dt[dtyFieldNamesIndexMap['dty_Status']]:"open");
        ffr[fieldIndexMap['dty_Type']] = (dt?dt[dtyFieldNamesIndexMap['dty_Type']]:"freetext");
        
        return ffr;
    },
    
    //
    // prepare fields and init editing
    //
    _initEditForm_finalize: function(response){
        
        var that = this;
        
        if(response==null || response.status == window.hWin.HAPI4.ResponseStatus.OK){
            
            if(response){
                that._currentEditRecordset = new hRecordSet(response.data);
            }
            
            var rectypeID = that._getField('rec_RecTypeID');
            var rectypes = window.hWin.HEURIST4.rectypes;
            var rfrs = rectypes.typedefs[rectypeID].dtFields;
            
            //pass structure and record details
            that._currentEditID = that._getField('rec_ID');;
            that._currentEditRecTypeID = rectypeID;
            
            //@todo - move it inside editing
            //convert structure - 
            var fields = window.hWin.HEURIST4.util.cloneJSON(that.options.entity.fields);
            var fieldNames = rectypes.typedefs.dtFieldNames;
            var fi = rectypes.typedefs.dtFieldNamesToIndex;

            /*
            function __findFieldIdxById(id){
                for(var k in fields){
                    if(fields[k]['dtID']==id){
                        return k;
                    }
                }
                return -1;
            }
            //hide url field
            var fi_url = rectypes.typedefs.commonNamesToIndex['rty_ShowURLOnEditForm'];
            if(rectypes.typedefs[rectypeID].commonFields[fi_url]=='0'){
                fields[__findFieldIdxById('rec_URL')]['rst_Visible'] = false;
            }
            */
            
            var fi_type = fi['dty_Type'],
                fi_name = fi['rst_DisplayName'],
                fi_order = fi['rst_DisplayOrder'],
                fi_maxval = fi['rst_MaxValues']; //need for proper repeat
            
            var s_fields = []; //sorted fields
            var fields_ids = [];
            for(var dt_ID in rfrs){ //in rt structure
                if(dt_ID>0){
                    rfrs[dt_ID]['dt_ID'] = dt_ID;
                    s_fields.push(rfrs[dt_ID]);
                    fields_ids.push(Number(dt_ID));
                }
            }
            //sort by order
            s_fields.sort(function(a,b){ return a[fi_order]<b[fi_order]?-1:1});

            //add non-standard fields that are not in structure
            var field_in_recset = that._currentEditRecordset.getDetailsFieldTypes();
            var addhead = true;
            for(var k=0; k<field_in_recset.length; k++){
                if(fields_ids.indexOf(field_in_recset[k])<0){
                    if(addhead){                    
                        var rfr = that._getFakeRectypeField(1);
                        rfr[fi_name] = 'Non-standard record type fields for this record';
                        rfr[fi_type] = 'separator';
                        s_fields.push(rfr);
                        addhead = false;
                    }
                    s_fields.push(that._getFakeRectypeField(field_in_recset[k]));
                }
                
                
            }           
            
             
            
            var group_fields = null;
            
            for(var k=0; k<s_fields.length; k++){
                
                rfr = s_fields[k];
                
                if(rfr[fi_type]=='separator'){
                    if(group_fields!=null){
                        fields[fields.length-1].children = group_fields;
                    }
                    var dtGroup = {
                        groupHeader: rfr[fi_name],
                        groupType: 'group', //accordion, tabs, group
                        groupStyle: {},
                        children:[]
                    };
                    fields.push(dtGroup);
                    group_fields = [];
                }else {
                
                    var dtFields = {};
                    for(idx in rfr){
                        if(idx>=0){
                            dtFields[fieldNames[idx]] = rfr[idx];
                            
                            if(idx==fi_type){ //fieldNames[idx]=='dty_Type'){
                                if(dtFields[fieldNames[idx]]=='file'){
                                    dtFields['rst_FieldConfig'] = {"entity":"records", "accept":".png,.jpg,.gif", "size":200};
                                }
                                
                            }else if(idx==fi_maxval){
                                if(window.hWin.HEURIST4.util.isnull(dtFields[fieldNames[idx]])){
                                    dtFields[fieldNames[idx]] = 0;
                                }
                            }
                        }
                    }//for
                    
                    if(group_fields!=null){
                        group_fields.push({"dtID": rfr['dt_ID'], "dtFields":dtFields});
                    }else{
                        fields.push({"dtID": rfr['dt_ID'], "dtFields":dtFields});
                    }
                }
            }//for s_fields
            //add children to last group
            if(group_fields!=null){
                fields[fields.length-1].children = group_fields;
            }
            
            that._editing.initEditForm(fields, that._currentEditRecordset);
            that._afterInitEditForm();

            //show rec_URL 
            var fi_url = rectypes.typedefs.commonNamesToIndex['rty_ShowURLOnEditForm'];
            if(rectypes.typedefs[rectypeID].commonFields[fi_url]=='1'){
                var ele = that._editing.getFieldByName('rec_URL');
                ele.show();
            }
            
            if(that.editFormSummary && that.editFormSummary.length>0){
                that.editFormSummary.accordion({
                    active:0    
                });
            }
            
        }else{
            window.hWin.HEURIST4.msg.showMsgErr(response);
        }
    },        
    

    
    
    //  -----------------------------------------------------
    //
    //  send update request and close popup if edit is in dialog
    // OVERRIDE
    //
    _saveEditAndClose: function( afterAction ){

            var fields = this._getValidatedValues(); 
            
            if(fields==null) return; //validation failed
       
            var request = {ID: this._currentEditID, 
                           RecTypeID: this._currentEditRecTypeID, 
                           URL: fields['rec_URL'],
                           OwnerUGrpID: fields['rec_OwnerUGrpID'],
                           NonOwnerVisibility: fields['rec_NonOwnerVisibility'],
                           ScratchPad: fields['rec_ScratchPad'],
                           'details': fields};
        
            var that = this;                                    

            that.onEditFormChange(true); //forcefully hide all "save" buttons
            
                //that.loadanimation(true);
                window.hWin.HAPI4.RecordMgr.save(request, 
                    function(response){
                        
                        //that.onEditFormChange();
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){

                            var recID = ''+response.data[0];
                            var rec_Title = response.rec_Title;
                            
                            //that._afterSaveEventHandler( recID, fields);
                            //
                            
                            if($.isFunction(afterAction)){
                               
                               afterAction.call(); 
                                
                            }else if(afterAction=='close'){
                                that._currentEditID = null;
                                that._currentEditRecordset.setFld(
                                        that._currentEditRecordset.getFirstRecord(),'rec_Title',rec_Title);
                                that.closeEditDialog();            
                            }else if(afterAction=='newrecord'){
                                that._initEditForm_continue(-1);
                            }else{
                                //reload after save
                                that._initEditForm_continue(that._currentEditID)
                            }
                            
                            window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Record has been saved'));
                            
                        }else{
                            that.onEditFormChange(); //restore save buttons visibility
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });
    },   
    
    //
    //
    //
    onEditFormChange:function(force_hide){
        var mode = 'hidden';
        if(force_hide!==true){
            var isChanged = this._editing.isModified();
            mode = isChanged?'visible':'hidden';
        }
        //show/hide save buttons
        var ele = this.toolbar;
        ele.find('#btnRecCancel').css('visibility', mode);
        ele.find('#btnRecSaveAndNew').css('visibility', mode);
        ele.find('#btnRecSave').css('visibility', mode);
        ele.find('#btnRecSaveAndClose').css('visibility', mode);
        
        //ele.find('#btnRecReload').css('visibility', !mode);
    },
    
    //
    //
    //    
    _afterInitEditForm: function(){
        this.onEditFormChange();
    },
    
    
});

//
// Show as dialog - to remove
//
function showManageRecords( options ){

    var manage_dlg; // = $('#heurist-records-dialog');  //@todo - unique ID

    if(true){ //manage_dlg.length<1){
        
        options.isdialog = true;

        manage_dlg = $('<div id="heurist-records-dialog-'+window.hWin.HEURIST4.util.random()+'">')
                .appendTo( $('body') )
                .manageRecords( options );
    }

    manage_dlg.manageRecords( 'popupDialog' );
}
