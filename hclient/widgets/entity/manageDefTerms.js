/**
* manageDefRecTypes.js - main widget to manage defRecTypes users
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

/*
we may take data from 
1) use_cache = false  from server on every search request (live data) 
2) use_cache = true   from client cache - it loads once per heurist session (actually we force load)
3) use_cache = true + use_structure - use HEURSIT4.rectypes
*/
$.widget( "heurist.manageDefTerms", $.heurist.manageEntity, {

    /*
    options
    initial_filter - initial vocabulary ID
    filter_groups -  restrict domain: null-any, enum or relation
    view_mode 
    */

    //options for special mode 
    // select_mode_reference - reference | inverse 
    // select_mode_target  - name of target term or vocabulary (for header at the top of search form)


    _entityName: 'defTerms',
    fields_list_div: null,  //term search result
    scrollInterval: 0,
    
    vocabulary_groups:null, //reference from vocabs tp group widget
    vocabularies_div:null, //reference from terms to vocabulary widget
    
    last_added_vocabulary: 0,

    options:{
        vocab_type:null, // vocab type, enum or relation
        create_one_term: false, // only allow the creation of one term, returns new term's ID
        edit_need_load_fullrecord: true
    },

    use_remote: false,

    _cachedUsages: {}, // cached term usages

    //
    //                                                  
    //    
    _init: function() {

        this.options.default_palette_class = 'ui-heurist-design';

        if(!window.hWin.HEURIST4.ui.collapsed_terms){
            window.hWin.HEURIST4.ui.collapsed_terms = [];
        }

        this.options.innerTitle = false;

        this.options.use_cache = true;
        this.options.use_structure = false

        if(this.options.import_structure){
            if(this.options.select_mode=='manager') this.options.select_mode='select_single';
            this.options.use_cache = true;
            this.use_remote = true; //use HEURIST4.remote.detailtypes for import structures
            this.options.auxilary = 'vocabulary';
        }

        if(!this.options.layout_mode) this.options.layout_mode = 'short';
        if(this.options.auxilary!='vocabulary') this.options.auxilary='term';

        if(this.options.edit_mode=='editonly'){
            this.options.select_mode = 'manager';
            this.options.layout_mode = 'editonly';
        }else{

            if(this.options.isFrontUI &&
                this.options.select_mode=='manager' && this.options.auxilary!='vocabulary'){
                window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, {'background-color':'#fff', 'opacity':1});                   
            }

        }
        /*
        if(this.options.auxilary=='vocabulary'){
        this.options.edit_height = 440;
        }else{
        this.options.edit_height = 660;
        }*/
        this.options.edit_height = 440;
        this.options.edit_width = (this.options.auxilary=='term')?600:660;

        this._super();
        
        if(this.recordList.is(':visible') && this.options.auxilary=='term' && this.options.select_mode == 'manager')
        this.space_for_drop = $('<span class="space_for_drop heurist-helper3" '
                        +'style="position:absolute;top:90px;text-align:left;left:0;right:0;font-size: 0.8em;display:block;margin:0px;padding:5px 0 0 3px;background:white">'
                        +'<span class="ui-icon ui-icon-arrowthick-1-e"></span>&nbsp;top</span>') //drop here to move term to top level&nbsp;<span class="ui-icon ui-icon-arrowthick-1-w"/>
                            .insertBefore(this.recordList);
        

        let that = this;

        this.editForm.css({'top':0, 'padding-top':'20px'});

        window.hWin.HAPI4.addEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            function(data) { 

                if(!data || 
                    (data.source != that.uuid && data.type == this.options.auxilary))
                {
                    that.refreshRecordList();
                }else
                    if(data && (((data.type == 'vcg'||data.type == 'vocabulary') && that.options.auxilary=='vocabulary') 
                        || ((data.type == 'vocabulary'||data.type == 'term') && that.options.auxilary=='term')
                    )){
                        that._filterByVocabulary();
                    }


        });
        
        
    },

    _destroy: function() {

        if(this.fields_list_div){
            this.fields_list_div.remove();
        }
        if(this.space_for_drop) this.space_for_drop.remove();

        window.hWin.HAPI4.removeEventListener(this, window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);     

        if(this.recordTree) this.recordTree.remove();

        this._super(); 
    },

    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {

        if(!this._super()){
            return false;
        }

        this.usrPreferences = this.getUiPreferences();

        if(this.options.edit_mode=='editonly'){
            this._initEditorOnly( this.options.edit_recordset );
            return;
        }

        //init viewer 
        let that = this;

        if(this.options.select_mode=='manager'){

            this.searchForm.css({padding:0});

            if(this.options.auxilary=='vocabulary'){
                //vocabulary groups

                this.main_element = this.element.find('.ent_wrapper:first').addClass('ui-dialog-heurist').css({'left':248});

                this.vocabulary_groups = $('<div>').addClass('ui-dialog-heurist')
                .css({position: 'absolute',top: 0, bottom: 0, left: 0, width:240, overflow: 'hidden'})
                .uniqueId()
                .appendTo(this.element);


                this._toolbar = this.searchForm;

                $('<div id="div_group_information" '
                    +'style="vertical-align: middle;width: 100%;min-height: 32px; border-bottom: 1px solid gray; clear: both;"></div>'
                    +'<div class="action-buttons" style="height:40px;background:white;padding:10px 8px;">'
                    +'<h4 style="display:inline-block;margin: 0 10px 0 0; vertical-align: middle;">Vocabularies</h4></div>')
                .appendTo(this.searchForm);

                let btn_array = [

                    {showLabel:true, icon:'ui-icon-plus', text:window.hWin.HR('Add'), //Add Vocab
                        css:{'margin-right':'0.5em','display':'inline-block',padding:'2px'}, class:'btnAddButton',
                        click: function() { that._onActionListener(null, 'add'); }},
                    {showLabel:false, icon:'ui-icon-download', text:window.hWin.HR('Export Vocabularies'),
                        css:{'margin-right':'0.5em','display':'inline-block',padding:'2px'}, class:'btnExportVocab',
                        click: function() { that._onActionListener(null, 'term-export'); }},
                    {showLabel:false, icon:'ui-icon-upload', padding:'2px', text:window.hWin.HR('Import Vocabularies'),
                        css:{'margin-right':'0.5em','display':'inline-block',padding:'2px'}, class:'btnImportVocab',
                        click: function() { that._onActionListener(null, 'term-import'); }}
                ];

                //add, import buttons
                let c1_btns = this.searchForm.find('div.action-buttons');
                for(let idx in btn_array){
                    this._defineActionButton2(btn_array[idx], c1_btns);
                }



                this.searchForm.css({'padding-top':this.options.isFrontUI?'6px':'4px', height:80});
                this.recordList.css({ top: '80px' });

                this.options.recordList = {
                    empty_remark: 'No vocabularies in this group.<br><br>Please drag vocabulary from other groups or add new vocabulary to this group.',
                    show_toolbar: false,
                    pagesize:99999,
                    recordDivEvenClass:'', //suppress highlight for even lines
                    view_mode: 'list',

                    draggable: function(){ //function is called after finish render and assign draggable widget for all record divs

                        that.recordList.find('.rt_draggable > .item').draggable({ // 
                            revert: 'invalid',
                            helper: function(){ 
                                return $('<div class="rt_draggable ui-drag-drop" recid="'+
                                    $(this).parent().attr('recid')
                                    +'" style="width:300;padding:4px;text-align:center;font-size:0.8em;background:#EDF5FF"'
                                    +'>Drag and drop to group item to change vocabulary group</div>'); 
                            },
                            zIndex:100,
                            appendTo:'body',
                            containment: 'window',
                            scope: 'vcg_change'
                        });   
                    },

                    droppable: function(){

                        that.recordList.find('.recordDiv') // change vocabualry for term
                        .droppable({                  
                            scope: 'vocab_change',
                            hoverClass: 'ui-drag-drop',
                            drop: function( event, ui ){

                                let trg = $(event.target).hasClass('recordDiv')
                                ?$(event.target)
                                :$(event.target).parents('.recordDiv');

                                let trm_ID = $(ui.draggable).parent().attr('recid');
                                let vocab_id = trg.attr('recid');
                                if(trm_ID>0 && vocab_id>0 && that.options.reference_trm_manger){

                                    if(that.options.reference_trm_manger.find('#rbDnD_merge').is(':checked')){
                                        window.hWin.HEURIST4.msg.showMsgFlash('Merge is allowed within vocabulary only');
                                        return false;  
                                    } 

                                    that.options.reference_trm_manger
                                    .manageDefTerms('changeVocabularyGroup',{trm_ID:trm_ID, trm_ParentTermID:vocab_id });
                                }
                        }});
                    }

                };

                this.recordList.uniqueId();

                let rg_options = {
                    isdialog: false, 
                    isFrontUI: this.options.isFrontUI,
                    container: that.vocabulary_groups,
                    title: 'Vocabulary groups',
                    layout_mode: 'short',
                    select_mode: 'manager',
                    reference_vocab_manger: that.element,
                    onSelect:function(res){

                        if(window.hWin.HEURIST4.util.isRecordSet(res)){
                            res = res.getIds();                     
                            if(res && res.length>0){
                                //filter by vocabulary group
                                that.options.trm_VocabularyGroupID = res[0];
                            }
                        }else if(res>0){
                            that.options.trm_VocabularyGroupID = res; 
                        }else{
                            that.options.trm_VocabularyGroupID = -1  
                        }

                        if(that.getRecordSet()!==null){
                            that._filterByVocabulary();
                        }

                    },
                    add_to_begin: true
                };

                
                //initially selected vocabulary
                if(this.options.selection_on_init=='add_new'){
                    this.options.selection_on_init = null;
                    this.addEditRecord(-1);
                }else  if(this.options.selection_on_init>0){
                    //is vocabulary
                    let vcg_ID = $Db.trm(this.options.selection_on_init, 'trm_VocabularyGroupID');
                    rg_options['selection_on_init'] = vcg_ID; //select group
                }

                window.hWin.HEURIST4.ui.showEntityDialog('defVocabularyGroups', rg_options);

            }
            else{ //terms ------------------------------------------

                //add vocab group and vocabs panels
                this.element.addClass('ui-suppress-border-and-shadow');

                this.main_element = this.element.find('.ent_wrapper:first').addClass('ui-dialog-heurist').css({'left':544}); //

                this.vocabularies_div = $('<div>').addClass('ui-dialog-heurist')
                .css({position: 'absolute',top: 0, bottom: 0, left: 0, width:536, overflow: 'hidden'}) //280
                .uniqueId()
                .appendTo(this.element);

                if(this.options.innerCommonHeader){
                    $(this.options.innerCommonHeader).css({position:'absolute',height:'38px',left:0,right:0,top:0,
                        background: 'white','line-height': '38px'}).appendTo(this.element);
                    this.main_element.css('top','38px');
                    this.vocabularies_div.css('top','38px');
                }
                //show particular terms for vocabulary 
                let btn_array = [

                    {showLabel:true, icon:'ui-icon-plus', text:window.hWin.HR('Add'), //Add Term
                        css:{'margin-right':'0.5em','display':'inline-block',padding:'2px'}, class:'btnAddButton ui-button-action',
                        click: function() { that._onActionListener(null, 'add'); }},

                    {showLabel:true, icon:'ui-icon-link', text:window.hWin.HR('Ref'), //Add Term
                        css:{'margin-right':'0.5em','display':'inline-block',padding:'2px'}, class:'btnAddButton2',
                        click: function() { that._onActionListener(null, 'add-reference'); }},

                    {showLabel:false, icon:'ui-icon-download', text:window.hWin.HR('Export Terms'), //ui-icon-arrowthick-1-e
                        css:{'margin-right':'0.5em','display':'inline-block',padding:'2px'}, class:'btnExportVocab',
                        click: function() { that._onActionListener(null, 'term-export'); }},

                    {showLabel:false, icon:'ui-icon-upload', text:window.hWin.HR('Import Terms'), //ui-icon-arrowthick-1-w  padding:'2px',
                        css:{'margin-right':'0.5em','display':'inline-block',padding:'2px'}, class:'btnImportVocab',
                        click: function() { that._onActionListener(null, 'term-import'); }},
                        
                    {showLabel:false, icon:'ui-icon-translate', text:window.hWin.HR('Import Translations'),  //padding:'2px'
                        css:{'margin-right':'0.5em','display':'inline-block',padding:'2px'}, class:'btnImportTranslations',
                        click: function() { that._onActionListener(null, 'term-import-translations'); }}
                ];

                let btn_array2 = [
                    {showLabel:false, icon:'ui-icon-menu', text:window.hWin.HR('Show as plain list'),
                        css:{'margin-right':'0.5em','display':'inline-block'}, class:'btnViewMode_List',
                        click: function(event) { event.target.blur(); that._onActionListener(null, 'viewmode-list'); }},
                    /*{showLabel:false, icon:'ui-icon-structure', text:window.hWin.HR('Show as tree'),
                    css:{'margin-right':'0.5em','display':'inline-block'}, class:'btnViewMode_Tree',
                    click: function() { that._onActionListener(null, 'viewmode-tree'); }},*/
                    {showLabel:false, icon:'ui-icon-view-icons', text:window.hWin.HR('Show as images'),
                        css:{'margin-right':'0.5em','display':'inline-block'}, class:'btnViewMode_List',
                        click: function(event) { event.target.blur(); that._onActionListener(null, 'viewmode-thumbs'); }}
                ];

                this._toolbar = this.searchForm;

                //padding:'6px'
                this.searchForm.css({'min-width': '470px', 'padding-top':this.options.isFrontUI?'6px':'4px', height:80})
                .empty();                                     
                this.recordList.css({'min-width': '315px', top:'107px'});
                this.searchForm.parent().css({'overflow-x':'auto'});

                $('<div style="vertical-align: middle;width: 100%;min-height: 32px; border-bottom: 1px solid gray; clear: both;">'
                    +'<div id="div_group_information" style="margin-right:150px;">A</div>'
                    +'<div style="position:absolute;right:10px;top:8px;"><label>Find: </label>'
                    +'<input type="text" style="width:175px" class="find-term text ui-widget-content ui-corner-all"/></div>'
                    +'</div>'
                    +'<div class="action-buttons" style="height:60px;background:white;padding:10px 8px;">'
                    +'<h4 style="display:inline-block;margin: 0 10px 0 0; vertical-align: middle;">Terms</h4>'
                    +'<div style="min-width:70px;text-align:right;float:right" id="btn_container"></div></div>')
                .appendTo(this.searchForm);


                /*$('<div>'
                +'<h3 style="display:inline-block;margin: 0 10px 0 0; vertical-align: middle;">Terms</h3>'
                +'</div>'
                +'<div style="display:table;width:100%;">'
                +'<div id="div_group_information" style="padding-top:13px;min-height:3em;max-width:350px;display:table-cell;"></div>'
                +'<div style="min-width:70px;text-align:right;display:table-cell;" id="btn_container"></div>'
                +'</div>')
                .appendTo(this.searchForm);*/

                //add, import buttons
                let c1_btns = this.searchForm.find('div.action-buttons');
                for(let idx in btn_array){

                    this._defineActionButton2(btn_array[idx], c1_btns);

                    if(btn_array[idx]['class'] == 'btnAddButton'){ // remove bold effect from button
                        this.searchForm.find('.btnAddButton')[0].style.setProperty('font-weight', 'normal', 'important');
                    }
                }

                let del_multi_btn = {showLabel:true, text:window.hWin.HR('Delete selected'), //Delete selected terms
                                    css:{'margin-left':'0.75em','display':'inline-block',padding:'2px'}, class:'btnDelMulti',
                                    click: function() { that._onActionListener(null, 'term-delete-mutliple'); }};
                this._defineActionButton2(del_multi_btn, c1_btns);

                $('<br><span style="font-size:10px">drag to <label><input type="radio" name="rbDnD" id="rbDnD_move" checked></span>move as sub-term<label> '
                    +'<label><input type="radio" name="rbDnD" id="rbDnD_merge"/>merge into target term<label></span>')
                .appendTo(c1_btns);

                //add input search
                this._on(this.searchForm.find('.find-term'), {
                    //keypress: window.hWin.HEURIST4.ui.preventChars,
                    keyup: this._onFindTerms }); //keyup

                //view mode
                let c2_btns = this.searchForm.find('#btn_container');
                for(let idx in btn_array2){
                    this._defineActionButton2(btn_array2[idx], c2_btns);
                }

                this.rbMergeOnDnD = this.searchForm.find('#rbDnD_merge');

                this._dropped = false;

                let trm_empty_remark = 'No terms in selected vocabulary.<br><br>Add or import new ones<br><br>'
                    + '<div style="display: inline-block;margin: 10px 0 0 35px;color: green;font-style: italic;">'
                        + 'Terms can be added directly when editing the data for a record.<br><br>'
                        + 'Terms are added automatically when importing data from a<br>CSV, XML, or JSON file containing terms.'
                    + '</div>';

                this.options.recordList = {
                    empty_remark: trm_empty_remark,
                    show_toolbar: false,
                    view_mode: 'list',
                    pagesize: 999999,
                    recordDivEvenClass:'', //suppress highlight for even lines
                    draggable: function(){

                        that.recordList.find('.rt_draggable > .item').draggable({ //    
                            revert: 'invalid',
                            helper: function(){ 
                                that._dropped = false;
                                return $('<div class="rt_draggable ui-drag-drop" recid="'+
                                    $(this).parent().attr('recid')
                                    +'" style="width:300;padding:4px;text-align:center;font-size:0.8em;background:#EDF5FF"'
                                    +'>Drag and drop to vocabulary item to change it</div>'); 
                            },
                            zIndex:99999,
                            appendTo:'body',
                            containment: 'window',
                            scope: 'vocab_change',

                            /*start: function(event,ui){
                                if(that.space_for_drop){
                                }
                            },*/
                            drag: function(event,ui){
                                //let trg = $(event.target);trg.hasClass('ui-droppable')
                                if($('.ui-droppable.ui-drag-drop').is(':visible')){
                                    $(ui.helper).css("cursor", 'grab');
                                }else{
                                    $(ui.helper).css("cursor", 'not-allowed');
                                }
                                
                                let ele = that.recordList.find('.div-result-list-content');
                                
                                let bot = ele.offset().top+ele.height();
                                if(that.scrollInterval>0) clearInterval(that.scrollInterval);
                                that.scrollInterval = 0;

                                if(ui.offset.top>bot-20){
                                    that.scrollInterval = setInterval(function(){ if(!that._dropped) ele[0].scrollTop += 20}, 50); 
                                }else if(ui.offset.top<ele.offset().top+((that.space_for_drop && that.space_for_drop.is(':visible'))?-60:-20)) {
                                    that.scrollInterval = setInterval(function(){ if(!that._dropped) ele[0].scrollTop -= 20}, 50); 
                                }

                            }
                        });   
                    },
                    droppable: function(){

                        that.recordList.find('.recordDiv')  //change parent for term (within the same vocab tree) OR merge
                        .droppable({
                            scope: 'vocab_change',
                            hoverClass: 'ui-drag-drop', //highlight
                            drop: function( event, ui ){
                                let trg = $(event.target).hasClass('recordDiv')
                                ?$(event.target)
                                :$(event.target).parents('.recordDiv');

                                if(that.space_for_drop){
                                   
                                    //that.recordList.css('top','80px');                                
                                }
                                that._dropped = true;
                                if(that.scrollInterval>0) clearInterval(that.scrollInterval);
                                that.scrollInterval = 0;
                                window.hWin.HEURIST4.util.stopEvent(event);

                                let trm_ID = $(ui.draggable).parent().attr('recid');
                                let trm_ParentTermID = trg.attr('recid');
                                if(trm_ID!=trm_ParentTermID && trm_ID>0 && trm_ParentTermID>0){

                                    if(that.rbMergeOnDnD.is(':checked')){
                                        that.mergeTerms({trm_ID:trm_ID, trm_ParentTermID:trm_ParentTermID });    
                                    }else{
                                        that.changeVocabularyGroup({trm_ID:trm_ID, trm_ParentTermID:trm_ParentTermID });        
                                    }
                                }
                            }
                        });

                        // move to root of vocabulary                                
                        let _move_to_top = {
                            scope: 'vocab_change',
                            hoverClass: 'ui-drag-drop',
                            drop: function( event, ui ){
                                
                                if(that.space_for_drop){
                                   
                                    //that.recordList.css('top','80px');                                
                                }
                                if(that.scrollInterval>0) clearInterval(that.scrollInterval);
                                that.scrollInterval = 0;
                                
                                if (that._dropped) return;

                                let trm_ID = $(ui.draggable).parent().attr('recid');
                                let trm_ParentTermID = that.options.trm_VocabularyID;
                                if(trm_ID>0){
                                    if(!that.rbMergeOnDnD.is(':checked')){
                                        that.changeVocabularyGroup({ trm_ID:trm_ID, trm_ParentTermID:trm_ParentTermID }, true);
                                    }
                                }
                        }};
                        that.recordList.find('.div-result-list-content').droppable(_move_to_top);
                        if(that.space_for_drop){ that.space_for_drop.droppable(_move_to_top); }

                        //collapses 
                        if(window.hWin.HEURIST4.ui.collapsed_terms.length>0){
                            $.each(that.recordList.find('.recordDiv'),
                                function(i,pitem){
                                    pitem = $(pitem);
                                    let recID =pitem.attr('recID');
                                    if(window.hWin.HEURIST4.ui.collapsed_terms.indexOf(recID)>=0){

                                        pitem.find('span.ui-icon-triangle-1-s') 
                                        .removeClass('ui-icon-triangle-1-s')
                                        .addClass('ui-icon-triangle-1-e');

                                        $.each(that.recordList.find('div.recordDiv[data-parent]'),
                                            function(i,item){
                                                item = $(item);
                                                if(item.attr('data-parent').indexOf(';'+recID+';')>=0){
                                                    item.hide();
                                                }
                                        });
                                    }
                                }
                            );    

                        }

                    },

                    onTooltip: function(callback){

                        let content = $(this).attr('title');
                        let trmid = $(this).parent().attr('recid');

                        if(that._cachedUsages[trmid]){ // add usage count
                            content += `<p>Usage count: ${that._cachedUsages[trmid]}</p>`;
                        }
                        if(window.hWin.HEURIST4.util.isPositiveInt(trmid) && $Db.trmHasIcon(trmid)){ // add term image

                            let icon = window.hWin.HAPI4.getImageUrl(that._entityName, trmid, 'icon', null, null, true);
                            content += `<br><img src='${window.hWin.HAPI4.baseURL}hclient/assets/16x16.gif' style='background-size:contain; background-repeat:no-repeat; background-image: url("${icon}")' height=64 width=64 />`;
                            /*
                            const ele_context = this;

                            window.hWin.HAPI4.checkImage(that._entityName, trmid, 'icon', function(response){
                                if(response.status == window.hWin.ResponseStatus.OK && response.data == 'ok'){

                                    let icon = window.hWin.HAPI4.getImageUrl(that._entityName, trmid, 'icon', null, null, true);
                                    content += `<br><img src='${window.hWin.HAPI4.baseURL}hclient/assets/16x16.gif' style='background-size:contain; background-repeat:no-repeat; background-image: url("${icon}")' height=64 width=64 />`;
                                }

                                callback.call(ele_context, content);
                            });

                            return '';
                            */
                        }

                        return content;
                    }
                };

                //vocabularies options
                let rg_options = {
                    isdialog: false, 
                    isFrontUI: this.options.isFrontUI,
                    container: that.vocabularies_div,
                    title: 'Vocabularies',
                    select_mode: 'manager',
                    reference_trm_manger: that.element,
                    auxilary: 'vocabulary',
                    onSelect:function(res){

                        /*
                        if(that.options.selection_on_init>0){

                        that.options.trm_VocabularyID = that.options.selection_on_init;
                        that.options.selection_on_init = null;

                        }else */
                        if(window.hWin.HEURIST4.util.isRecordSet(res)){
                            res = res.getIds();                     
                            if(res && res.length>0){
                                //filter by vocabulary
                                that.options.trm_VocabularyID = res[0];
                            }
                        }else if(res>0){
                            that.options.trm_VocabularyID = res;
                        }else{
                            that.options.trm_VocabularyID = -1;
                        }

                        if(that.recordList.resultList('instance') !== undefined && that.options.trm_VocabularyID != -1){
                            let vocab_name = $Db.trm(that.options.trm_VocabularyID, 'trm_Label');
                            if(!vocab_name){
                                vocab_name = 'Vocabulary #' + that.options.trm_VocabularyID;
                            }else{
                                vocab_name += '(#'+ that.options.trm_VocabularyID +')';
                            }
                            that.recordList.resultList('clearAllRecordDivs',null, '<div style="padding: 10px;cursor: wait;">'
                                            + '<span class="ui-icon ui-icon-loading-status-balls"></span> &nbsp;&nbsp;'
                                            + 'Loading: '+ vocab_name +'</div>');
                        }

                        if(that.getRecordSet()!==null){
                            setTimeout(() => {
                                that._filterByVocabulary();
                            }, 100);
                        }
                    }
                };

                if(this.options.selection_on_init=='add_new' || this.options.selection_on_init>0){
                    //initially selected vocabulary
                    rg_options['selection_on_init'] = this.options.selection_on_init;
                    this.options.selection_on_init = null;
                }

                if(this.options.vocab_type && rg_options['selection_on_init']=='add_new'){
					// Pre-check the relation checkbox for definig new relmarker vocab
                    rg_options['vocab_type'] = this.options.vocab_type;
                }

                this.recordList.uniqueId();

                window.hWin.HEURIST4.ui.showEntityDialog('defTerms', rg_options);

                this._on( this.recordList, {        
                    "resultlistondblclick": function(event, selected_recs){
                        this.selectedRecords(selected_recs); //assign

                        if(window.hWin.HEURIST4.util.isRecordSet(selected_recs)){
                            let recs = selected_recs.getOrder();
                            if(recs && recs.length>0){
                                let recID = recs[recs.length-1];
                                this._onActionListener(event, {action:'edit',recID:recID}); 
                                //edit-inline disabled 2020-12-01            
                            }
                        }
                    }
                });
                
            }//aux=term


        }else 
        if(this.use_remote){ // add show all and search field
            let $show_all = $('<div style="display:inline-block;padding:0.2em" id="div_show_already_in_db">'
                + '<div class="header4" style="display: inline-block;text-align:right;">'
                    + '<label for="input_search" class="slocale">All Find</label>'
                + '</div>'
                + '<input id="input_search" class="text ui-widget-content ui-corner-all" slocale-title="Find by name, ID or concept code" style="width: 250px; margin-right:0.2em"/>'

                + '<label for="chb_show_already_in_db" title="Show all record types (including that are already in database: allows updating of field list)" >Show All&nbsp;'
                + '<input id="chb_show_already_in_db" class="text ui-widget-content ui-corner-all" style="margin-right:0.2em" type="checkbox"/></label>'
            + '</div>');

            this.searchForm.append($show_all);

            this._on(this.searchForm.find('#input_search, #chb_show_already_in_db'), {
                change: function(){
                    let req = {};
                    if(!this.searchForm.find('#chb_show_already_in_db').is(':checked')){
                        req['trm_ID_local'] = '=0';
                    }
                    if(this.searchForm.find('#input_search').val() != ''){
                        req['trm_Label'] = this.searchForm.find('#input_search');
                    }

                    this.filterRecordList(null, req);
                }
            });
        }
        else {
           //SELECT MODE

            if(this.options.select_mode_target){

                if(this.options.select_mode_reference=='inverse'){
                    $('<h3 style="margin:10px 0">Select inverse term for: <i>'
                        +this.options.select_mode_target+'</i></h3>').appendTo(this.searchForm);
                }else{
                    $('<h3 style="margin:10px 0">Adding to vocabulary:'
                    +'<div class="truncate" style="max-width:330px;font-style:italic">'
                    +this.options.select_mode_target+'</div></h3>').appendTo(this.searchForm);
                }

            }

            //add vocabulary
            let sel = $('<div style="float:left"><label>From vocabulary: </label><br>'
                +'<select type="text" style="max-width:330px;min-width:15em;" '
                +'class="text ui-widget-content ui-corner-all"></select></div>')
            .appendTo(this.searchForm);

            //add input search
            /*
            c1 = $('<div style="float:left"><label>Find: </label>'
            +'<input type="text" style="width:10em" class="text ui-widget-content ui-corner-all"/></div>')
            .appendTo(c1);

            this._on(c1.find('input'), {
            //keypress: window.hWin.HEURIST4.ui.preventChars,
            keyup: this._onFindTerms }); //keyup
            */    

            if(!this.options.title){
                this.setTitle('Select '+(this.options.filter_groups=='enum'?'term':'relation'));    
            }
           

            sel = sel.find('select');
            this.vocabularies_sel = 
            window.hWin.HEURIST4.ui.createVocabularySelect(sel[0], {useGroups:true, 
                domain: this.options.filter_groups,
                defaultTermID: this.options.initial_filter});
            this._on(this.vocabularies_sel, {
                change:function(event){
                    this.options.trm_VocabularyID = $(event.target).val();
                    this._filterByVocabulary();
            } });
            this.vocabularies_sel.css('margin', '10px 0px 0px 17px');

            this.options.trm_VocabularyID = this.options.initial_filter;

            this.options.recordList = {
                empty_remark: this.options.empty_remark ? this.options.empty_remark : 'Select vocabulary',
                show_toolbar: false,
                view_mode: this.options.view_mode ? this.options.view_mode : 'list',
                pagesize: 999999
            };

            this.options.trm_VocabularyID = this.options.initial_filter;
           

            if( !window.hWin.HEURIST4.util.isempty(this.options.select_mode_reference) ){
                this.searchForm.css('height','7.5em');
                this.recordList.css('top','8.5em');
                this.options.recordList.transparent_background = true;
                this.options.recordList.recordDivEvenClass = null;
               
            }

        }

        this.recordList.resultList( this.options.recordList );
        if(this.options.hide_searchForm){
            this.searchForm.hide();
            this.recordList.css('top', '0px');
        }

        that._loadData(true);

        return true;
    },//_initControls   


    //
    //
    //
    selectVocabulary: function(vocab_id){
        
        let vcg_ID = $Db.trm(vocab_id, 'trm_VocabularyGroupID');

        if(vcg_ID>0){
            this.vocabulary_groups.manageDefVocabularyGroups('selectRecordInRecordset', vcg_ID);    
            let that = this;
            setTimeout(function(){that.selectRecordInRecordset(vocab_id);},300);
        }

    },         


    //
    // invoked after all elements are inited 
    //
    _loadData: function( is_first_call ){

        let that = this;

        if(this.use_remote && this.options.import_structure){
            this.recordList.resultList('resetGroups');
            
            this.recordList.resultList('clearAllRecordDivs',null, '<div style="padding: 10px;cursor: wait;">'
                + '<span class="ui-icon ui-icon-loading-status-balls"></span> &nbsp;&nbsp;'
                + 'Loading terms from template database</div>');
            this.recordList.resultList('option', 'empty_remark', '');

            window.hWin.HAPI4.SystemMgr.get_defs(
                {terms:'all', mode: 0, remote: that.options.import_structure.database_url}, function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){

                        if(!window.hWin.HEURIST4.remote){
                            window.hWin.HEURIST4.remote = {};
                        }
                        window.hWin.HEURIST4.remote.terms = response.data.terms;

                        that._cachedRecordset = that.getRecordsetFromRemote(response.data.terms, true, true);

                       
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                }
            );

            return;
        }else if(this.options.auxilary=='vocabulary'){
            //show vocabs only
            let recset = $Db.trm()
                    .getSubSetByRequest({'trm_ParentTermID':'=0', 'sort:trm_Label':1},
                                            this.options.entity.fields);
            this.updateRecordList(null, {recordset:recset});

        }else{
           
            this._cachedRecordset = $Db.trm(); //updateRecordList is not used to avoid delay    
        }
        this._filterByVocabulary();
        
    },

    //
    //
    //
    _filterByVocabulary: function(){

        if(!this.getRecordSet()) return;

        const that = this;

        let sGroupTitle = '<h3 style="margin:0;padding:0 8px" class="truncate">';
        if(this.options.auxilary=='vocabulary'){
            //filter by group

            if(this.options.trm_VocabularyGroupID>0){

                let vcg_id = this.options.trm_VocabularyGroupID;
                this.filterRecordList(null, {'trm_VocabularyGroupID':vcg_id, 'sort:trm_Label':1});

                sGroupTitle += (window.hWin.HEURIST4.util.htmlEscape($Db.vcg(vcg_id,'vcg_Name'))
                    +'</h3><div class="heurist-helper3 truncate" style="font-size:0.7em;padding:0 8px;">'
                    + window.hWin.HEURIST4.util.htmlEscape($Db.vcg(vcg_id,'vcg_Description'))
                    +'</div>');

                    
                //initial selection
                if(this.options.selection_on_init>0){
                    this.selectRecordInRecordset( this.options.selection_on_init );    
                    this.options.selection_on_init = null;
                }else{
                    let rdiv = this.recordList.find('.recordDiv:first');
                    if(rdiv.length){
                        rdiv.trigger('click');
                    }else if(window.hWin.HEURIST4.util.isFunction(this.options.onSelect)){
                        this.options.onSelect.call( this, null );
                    }
                }                    

            }else{
                sGroupTitle += '</h3>';
            }


        }else{

            if(this.options.trm_VocabularyID>0){ //filter by vocabulary

                let vocab_id = this.options.trm_VocabularyID;

               
               

                let subset = $Db.trm_TreeData(vocab_id, 'flat'); //returns recordset

                if(!this.recordList.resultList('instance')) return;
                
                this.recordList.resultList('updateResultSet', subset, null);

                if(this.recordTree && this.recordTree.fancytree('instance')){

                    //filtered
                    let treedata = $Db.trm_TreeData(vocab_id, 'tree'); //tree data
                    
                   
                    let tree = $.ui.fancytree.getTree( this.recordTree );
                    tree.reload(treedata);
                }

                sGroupTitle += ($Db.trm(vocab_id,'trm_Label')
                    +'</h3><div class="heurist-helper3 truncate" style="font-size:0.7em;padding:0 8px">'
                    +$Db.trm(vocab_id,'trm_Description')+'&nbsp;</div>');

                // Retrieve Term usages
                let trm_ids = $Db.trm_TreeData(vocab_id, 3);
                if(window.hWin.HEURIST4.util.isempty(trm_ids)){
                    return;
                }
                trm_ids = trm_ids.join(',');

                let req = {
                    'trmID': trm_ids,
                    'a': 'counts',
                    'mode': 'term_usage',
                    'entity': 'defTerms',
                    'request_id': window.hWin.HEURIST4.util.random()
                };
                window.hWin.HAPI4.EntityMgr.doRequest(req, function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        that.updateTermUsage(response.data);
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });

            }else{
                this.recordList.resultList('clearAllRecordDivs',null,
                    '<div style="padding: 8px;">Select vocabulary'+
                    ((this.options.select_mode!='manager')?'':' on the left')+'</div>');

                sGroupTitle += '</h4>';
            }

            if(this.options.isFrontUI){
                let ele = this.element.find('.coverall-div');
                if(ele.length>0) ele.remove();
               
               
            }
        }
        this.searchForm.find('#div_group_information').html(sGroupTitle);

    },

    //
    //
    //
    _recordListItemRenderer:function(recordset, record){

        //ugr_ID,ugr_Type,ugr_Name,ugr_Description, ugr_eMail,ugr_FirstName,ugr_LastName,ugr_Enabled,ugl_Role
        let recID = recordset.fld(record, 'trm_ID');

        let sBold = '';
        let sWidth = '250';
        let sPad = '';
        let sRef = '';
        let sHint = '';
        let sLabel = window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, 'trm_Label'));
        let sDesc = window.hWin.HEURIST4.util.htmlEscape($Db.trm(recID, 'trm_Description'));
        let recTitle = '', sFontSize='';
        let html;


        if(this.options.auxilary=='vocabulary'){
            sBold = 'font-weight:bold;';
            if(recordset.fld(record, 'trm_Domain')=='relation'){
                sWidth = '205';
            }
            sWidth = 'max-width:'+sWidth+'px;min-width:'+sWidth+'px;';

            recTitle = '<div class="item truncate" style="'+sWidth+sBold+'" title="'+sLabel+'">'
            + sLabel+'</div>'; //recID+':'+

            html = '<div class="recordDiv rt_draggable white-borderless" style="padding-right:0" recid="'+recID+'">'
            + '<div class="recordSelector item"><input type="checkbox" /></div>'
            + recTitle 

            +((recordset.fld(record, 'trm_Domain')=='enum')
                ?'':'<div style="display: table-cell;font-size:smaller" class="item">(relation)</div>')

            + this._defineActionButton({key:'edit',label:'Edit Vocabulary', 
                title:'', icon:'ui-icon-pencil',class:'rec_actions_button'}, 
                null,'icon_text') 
            + this._defineActionButton({key:'delete',label:'Remove Vocabulary', 
                title:'', icon:'ui-icon-delete',class:'rec_actions_button'},
                null,'icon_text')
            + '<div class="selection_pointer" style="display:table-cell">'
            +'<span class="ui-icon ui-icon-carat-r"></span></div>'
            +'</div>';

        }else{
            let parents = recordset.fld(record, 'trm_Parents').split(',');
            let lvl = (parents.length);

            if(lvl>0){
                parents = ' data-parent=";'+parents.join(';')+';"';
            }else{
                parents = '';
            }

            let ref_lvl = $Db.isTermByReference(this.options.trm_VocabularyID, recID);
            let real_vocab_name = '';
            
            if(ref_lvl!==false){
                let vocab_id = $Db.getTermVocab(recID); //real vocab
                real_vocab_name = 
                    window.hWin.HEURIST4.util.htmlEscape($Db.vcg($Db.trm(vocab_id,'trm_VocabularyGroupID'), 'vcg_Name'))
                    +'.'+window.hWin.HEURIST4.util.htmlEscape($Db.trm(vocab_id, 'trm_Label'));
            
                sRef = '<span style="color:blue;font-size:smaller;">&nbsp; →'+real_vocab_name+'</span>.';
            }

            let sLabelHint = sLabel;
            sLabel = sRef + sLabel;

            let inv_id = $Db.trm(recID, 'trm_InverseTermID');
            if ( inv_id>0 ){
                let sInvLabel = window.hWin.HEURIST4.util.htmlEscape($Db.trm(inv_id, 'trm_Label'));
                if(sInvLabel) {
                    sLabel = sLabel + 
                    '&nbsp;&nbsp;&nbsp;<span style="font-size:smaller;font-style:italic;">inv: '+sInvLabel+'</span>';    
                    sLabelHint = sLabelHint + '    [inv: '+sInvLabel+' ]';
                }
            }

            sWidth = 'display:inline-block;padding-top:4px;max-width:320px;';

            sPad = '<span style="font-size:'+(1+lvl * 0.1)+'em">'+('&nbsp;'.repeat(lvl*4))+'</span>';
            sFontSize = 'font-size:'+(1.1 - lvl * 0.1)+'em;';

            let sCode = $Db.trm(recID, 'trm_Code');
            let sURI = $Db.trm(recID, 'trm_SemanticReferenceURL');

            sHint = 'title="'+sLabelHint+'<br>'
            + (sDesc?('<p><i>'+sDesc+'</i></p>'):'')
            + (sCode?('<p>Code: '+sCode+'</p>'):'')
            + (sURI?('<p>URI: '+sURI+'</p>'):'')
            + '<p>ID: '+recID+' ('+$Db.getConceptID('trm',recID)+')</p>';

            if(ref_lvl!==false){

                sHint = sHint +     
                '<p>Reference to: <i>'+real_vocab_name+'</i> vocabulary.</p>'
                +'<p style=&quot;color:orange&quot;>The term can only be edited in that vocabulary.</p>';
            }
            sHint = sHint + '"';
            
            const hasIcon = $Db.trmHasIcon(recID);

            let recIcon = hasIcon?window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'icon', null, null, true):'';

            recTitle = '<div class="item truncate label_term rolloverTooltip"'
            +' style="'+sFontSize+sWidth+sBold+'" '+sHint+'>'
            +sLabel+'&nbsp;&nbsp;'
            //+`<img src='${window.hWin.HAPI4.baseURL}hclient/assets/16x16.gif' style='background-image: url("${recIcon}"); background-size:contain; background-repeat:no-repeat; vertical-align:bottom;' />`
            //+'&nbsp;&nbsp;'
            +'<span class="term_usage"></span></div>';

            let html_thumb = '';
            
            let recThumb = hasIcon?window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'thumb', null, null, true):'';

            html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'
                    +recThumb+'&quot;);opacity:1;top:45px;"></div>';
                    
            sPad = lvl==1?0:(lvl==2?(lvl-0.5):lvl);                                                                         
            let exp_btn_style = 'width:20px;display:inline-block;vertical-align:bottom;margin-left:'+sPad+'em;';

            let sclass = 'white-borderless', sstyle = '';
            if( !window.hWin.HEURIST4.util.isempty(this.options.select_mode_reference) ){
                sclass = '';
                sstyle = 'style="background:transparent;border:none;"';
            }

            let alt_btn_content = '&nbsp;';
            if(this.options.edit_mode == 'popup'){
                alt_btn_content = '<span class="ui-icon ui-icon-triangle-1-e" style="color: black;opacity: 0.2;" ></span>';
            }

            html = '<div class="recordDiv densed '+sclass+(!(ref_lvl>0)?' rt_draggable':'')
            +'" '+sstyle+' recid="'+recID+'"'+parents+'>'
            + ($Db.trm_HasChildren(recID)
                ?this._defineActionButton(
                    {key:'expand',label:'Show/hide children',            
                        title:'', icon:'ui-icon-triangle-1-s', class: 'has_child_terms'},
                    null,'icon_text',exp_btn_style)
                : '<div style="'+exp_btn_style+'visibility:hidden" class="no_child_terms" title="Drag terms onto this term or create with + icon to create child terms">'
                        + alt_btn_content 
                     +'</div>') 
            + '<div class="recordSelector" style="display:inline-block;"><input type="checkbox" /></div>'
            + html_thumb + recTitle;

            /* 2020-12-10                      
            html = html + '<div class="truncate description_term">'  //item truncate 
            + sDesc
            + '</div>';
            */
            if(this.options.select_mode=='manager' && !(ref_lvl>0)){ //

                html = html + '<div class="rec_action_link2" style="margin-left:1em;'
                +'width:'+(sRef?20:
                        ($Db.trm(recID, 'trm_ParentTermID')!=this.options.trm_VocabularyID?100:80))
                +'px;height:20px;background:lightgray;display:inline-block;vertical-align:middle;">';

                if(ref_lvl===0){
                    html = html 
                    + this._defineActionButton({key:'delete',label:'Remove Reference', 
                        title:'', icon:'ui-icon-delete',class:'rec_actions_button'},
                        null,'icon_text')

                }else if(ref_lvl===false){
                    html = html 
                    + this._defineActionButton({key:'edit',label:'Edit Term',  //was  edit-inline disabled 2020-12-01            
                        title:'', icon:'ui-icon-pencil',class:'rec_actions_button'},
                        null,'icon_text') 
                    + this._defineActionButton({key:'delete',label:'Remove Term', 
                        title:'', icon:'ui-icon-delete',class:'rec_actions_button'},
                        null,'icon_text')
                    + this._defineActionButton({key:'add-child',label:'Add child', 
                        title:'', icon:'ui-icon-plus',class:'rec_actions_button'},
                        null,'icon_text')
                    + this._defineActionButton({key:'sort-branch',label:'Sort branch', 
                        title:'', icon:'ui-icon-sorting',class:'rec_actions_button'},
                        null,'icon_text');
                        
                    if($Db.trm(recID, 'trm_ParentTermID')!=this.options.trm_VocabularyID) {
                        html = html + this._defineActionButton({key:'moveup',label:'Move to level up', 
                        title:'', icon:'ui-icon-arrow-u',class:'rec_actions_button'},
                        null,'icon_text')
                    }    
                        
                }else{
                    html = html + ref_lvl;
                }
                html = html + '</div>';
            }
            html = html 
            + '</div>';
        }

        return html;

    },

    //
    //
    //
    _onPageRender: function(){

        if(this.options.edit_mode == 'popup'){

            let record_divs = this.recordList.find('.recordDiv');
            if(this.options.auxilary=='vocabulary'){ // add mouseenter/mouseleave
                
                this._on(record_divs, {
                    'mouseenter': function(event){ // reduce size

                        let trm_id = $(event.target).attr('recid');
                        let widths = ($Db.trm(trm_id, 'trm_Domain')=='relation' ? '178' : '225') + 'px';

                        $(event.target).find('.item.truncate').css({
                            'max-width': widths,
                            'min-width': widths
                        });
                    },
                    'mouseleave': function(event){ // increase size

                        let trm_id = $(event.target).attr('recid');
                        let widths = ($Db.trm(trm_id, 'trm_Domain')=='relation' ? '205' : '250') + 'px';

                        $(event.target).find('.item.truncate').css({
                            'max-width': widths,
                            'min-width': widths
                        });
                    }
                });
            }
        }
    },

    //
    //
    //
    _deleteAndClose: function(unconditionally, recIDs = null){

        let that = this;

        recIDs = recIDs == null ? [ this._currentEditID ] : recIDs;
        let is_multi = recIDs.length > 1;

        if(unconditionally===true){

            //this._super(); 

            if(recIDs==null || recIDs.length == 0) return;

            let request = {
                'a'          : 'delete',
                'entity'     : this.options.entity.entityName,
                'request_id' : window.hWin.HEURIST4.util.random(),
                'recID'      : recIDs
            };

            let it_was_vocab = recIDs.length == 1 && $Db.trm(recIDs[0], 'trm_ParentTermID') == 0;

            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){

                    if(response.status == window.hWin.ResponseStatus.OK){

                        for(const recID of recIDs){

                            if(that.options.use_cache){
                                that._cachedRecordset.removeRecord( recID );
                            }

                            that._afterDeleteEvenHandler( recID, it_was_vocab );
                        }

                       
                        that._triggerRefresh(that.options.auxilary);
                    }else{
                        onTermSaveError(response);
                    }
            });            

        }else{

            let trm_labels = [];
            let has_child = false;

            recIDs = recIDs.filter((recID) => {

                let trm = $Db.trm(recID);
                let is_vocab = trm?.trm_ParentTermID == 0;

                if(!trm || (recIDs.length > 1 && is_vocab)){
                    return false;
                }

                has_child = has_child || $Db.trm_HasChildren(recID);

                trm_labels.push(trm.trm_Label);

                return true;
            });

            if(has_child){
                trm_labels.push('And all child/nested terms');
            }

            let msg = 'Are you sure you wish to delete the ';
            msg += is_multi ? 'following terms:<br>' : this.options.auxilary;
            msg += ' <strong>' + (!is_multi ? trm_labels[0] : trm_labels.join('<br>')) + '</strong>' + (is_multi ? '' : '?');

            window.hWin.HEURIST4.msg.showMsgDlg( 
                msg, 
                function(){ that._deleteAndClose(true, recIDs) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'},
                {default_palette_class:this.options.default_palette_class});        
        }
    },

    //
    //
    //
    _afterDeleteEvenHandler: function(recID, it_was_vocab){

        this._super(recID); 

        //get all children
        let all_ids = $Db.trm_TreeData(recID, 'set', true);

        //remove defs on client side
        $Db.trm_RemoveLinks(recID);
        $Db.trm().removeRecord(recID);  //from record set

        $.each(all_ids, function(i,id){
            $Db.trm_RemoveLinks(id);
            $Db.trm().removeRecord(id);  //from record set
        });

        if(it_was_vocab){ 
            this.options.trm_VocabularyID = -1;
            this._filterByVocabulary();
        }
    },


    //-----
    //
    // show hide some elements on edit form according to type: 
    //    vocab/term and enum/relation
    //
    _afterInitEditForm: function(){
        this._super();

        this.inverse_termid_old = 0;

        let ele, currentDomain = null;
        let isVocab = !(this.options.trm_VocabularyID>0);
        if(isVocab){

            if(this._edit_dialog && this._edit_dialog.dialog('instance')){
                this._edit_dialog.dialog('option', 'title', (this._currentEditID>0?'Edit':'Add')+' Vocabulary');
            }

            //hide fields for vocab    
            this._editing.getFieldByName('trm_InverseTermID').hide();
            this._editing.getFieldByName('trm_InverseSymmetrical').hide();
            this._editing.getFieldByName('trm_Code').hide();
            this._editing.getFieldByName('trm_Thumb').hide();

            //change label            
            this._editing.getFieldByName('trm_Label').find('.header').text('Vocabulary Name');

            //assign default values
            ele = this._editing.getFieldByName('trm_VocabularyGroupID');
            if(this._currentEditID<0 && this.options.trm_VocabularyGroupID>0){
                ele.editing_input('setValue', this.options.trm_VocabularyGroupID, true);
                currentDomain = $Db.vcg( this.options.trm_VocabularyGroupID, 'vcg_Domain' );
            }else if(this._currentEditID==-1 && window.hWin.HEURIST4.util.isempty(this.options.trm_VocabularyGroupID)){
                // Select the first vocab group option, avoid default 'select...'
                let first_id = $(ele.find('.input-div option')[1]).val();
                if(!window.hWin.HEURIST4.util.isempty(first_id)){
                    ele.editing_input('setValue', first_id, true);
                    currentDomain = $Db.vcg(first_id, 'vcg_Domain');

                    // Hide 'select...' option
                    $(ele.find('.input-div option')[0]).attr('hidden', true);
                    if($(ele.find('.input-div select')).hSelect('instance')!=undefined){
                        $(ele.find('.input-div select')).hSelect('refresh');
                    }
                }

                if(!window.hWin.HEURIST4.util.isempty(this.options.vocab_type)){
                    currentDomain = this.options.vocab_type;
                }

                if(this.options.reference_trm_manger.find('div').length > 0){
                    let divs = this.options.reference_trm_manger.find('div');
                    let $title_container = $(divs[divs.length-1]);

                    if($title_container.find('span').length > 0){

                        let label = $title_container.find('span').attr('title');
                        label = label + (label.search(/vocab/i) == -1 ? ' vocab' : '');

                        this._editing.setFieldValueByName('trm_Label', label, true);
                    }
                }
            }

            ele.show();
            ele.editing_input('fset', 'rst_RequirementType', 'required');
            ele.find('.header').removeClass('recommended').addClass('required');
        }else
        {

            let dlg = this._getEditDialog(true);
            if(dlg && dlg.dialog('instance')){
                let s = (this._currentEditID>0?'Edit':'Add')+' Term';
                if(this.options.trm_VocabularyID>0){
                    s = s + ' to vocabulary "'
                    +window.hWin.HEURIST4.util.htmlEscape($Db.trm(this.options.trm_VocabularyID,'trm_Label'))+'"';
                    
                    if(!(this._currentEditID>0)){
                    $('<div class="heurist-helper1" style="padding:0 0 10px 20px;max-width:410px">'
+'If the terms you need already exist in other vocabularies, '
+'for example a subset of countries or languages, close this popup,'
+'click on the gearwheel icon or the vocabularies editor link, '
+'then use the Ref(erence) button to add references in the vocabulary'
+'which point to existing terms</div>').insertBefore($(this.editForm.find('fieldset')[0]));
                    }
                    
                }
                dlg.dialog('option', 'title', s);
            }

            let $trm_image = this._editing.getFieldByName('trm_Thumb');
            if(this._currentEditID<0){
                //assign vocabulary or parent id for new term only
                ele = this._editing.getFieldByName('trm_ParentTermID');
                ele.editing_input('setValue', this.options.trm_ParentTermID>0
                    ?this.options.trm_ParentTermID
                    :this.options.trm_VocabularyID, true);
            }else if(this.options.select_mode == 'manager' && $trm_image && $trm_image.parents('div.ui-accordion:first').length > 0){
                // Expand 'more...' accordion if there is a term image
                window.hWin.HAPI4.checkImage('defTerms', this._currentEditID, 'thumb', function(response){
                    if(response.data=='ok'){
                        $trm_image.parents('div.ui-accordion:first').accordion('option', 'active', 0);
                    }
                });
            }
            this.options.trm_ParentTermID = -1;

            ele = this._editing.getFieldByName('trm_VocabularyGroupID');
            if(ele){
                ele.hide();
            }

            let vocab_ID = $Db.getTermVocab(this.options.trm_VocabularyID);
            currentDomain = $Db.trm(vocab_ID, 'trm_Domain');

            if( currentDomain=='enum' ){
                this._editing.getFieldByName('trm_InverseTermID').hide();
                this._editing.getFieldByName('trm_InverseSymmetrical').hide();
            }else{
                ele = this._editing.getFieldByName('trm_InverseTermID');
                ele.show();
                let cfg = ele.editing_input('getConfigMode');
                cfg.initial_filter = vocab_ID;
                cfg.popup_options = {};
                cfg.popup_options.width = 400;
                cfg.popup_options.select_mode_reference = 'inverse';
                cfg.popup_options.select_mode_target = this._currentEditID>0?$Db.trm(this._currentEditID,'trm_Label'):'new term';
                ele.editing_input('setConfigMode', cfg);

                ele = this._editing.getFieldByName('trm_InverseSymmetrical');
                ele.show();

                if(this._currentEditID>0){
                    //detect: is it symmetrical?
                    let val = '1';
                    let trm_InverseTermID = $Db.trm(this._currentEditID, 'trm_InverseTermID');
                    if(trm_InverseTermID>0){
                        this.inverse_termid_old = $Db.trm(trm_InverseTermID, 'trm_InverseTermID');
                        val = (this._currentEditID == this.inverse_termid_old)?'1':'0';
                    }
                    ele.editing_input('setValue', val, true);
                }
                window.hWin.HEURIST4.util.setDisabled(ele, (this._currentEditID>0));

            }

        }

        // Add suggested/provided label
        ele = this._editing.getFieldByName('trm_Label');
        let suggested_name = this.options.suggested_name;
        if(!window.hWin.HEURIST4.util.isempty(suggested_name) && this._currentEditID <= 0 && ele.val() == ''){
            if(isVocab && suggested_name.search(/vocab/i) == -1){
                suggested_name += ' vocab';
            }
            this._editing.setFieldValueByName('trm_Label', this.options.suggested_name, false);
            this.options.suggested_name = null;
        }

        ele = this._editing.getFieldByName('trm_Domain')
        if(currentDomain!==null) ele.editing_input('setValue', currentDomain, true);
        if(isVocab) ele.show();

        ele = this._editing.getFieldByName('trm_ID');
        if(this._currentEditID>0){
			let conceptID = $Db.getConceptID('trm',this._currentEditID);
            ele.find('div.input-div').html(this._currentEditID+'&nbsp;&nbsp;( '
                +(!window.hWin.HEURIST4.util.isnull(conceptID) ? conceptID : "Concept ID is not defined")+' )');
            //$('<span>&nbsp;&nbsp;('+this._getField('trm_OriginatingDBID')+'-'+this._getField('trm_IDInOriginatingDB')+')</span>') 
        }else{
            ele.hide();   
        }

        //on ENTER save
        this._on( this.editForm.find('input.text,textarea.text'), { keypress: function(e){        
            let code = (e.keyCode ? e.keyCode : e.which);
            if (code == 13) {
                window.hWin.HEURIST4.util.stopEvent(e);
                e.preventDefault();
                this._saveEditAndClose();
            }
        }});                        

        //btnRecSave
        //defaultBeforeClose


        let ishelp_on = (this.usrPreferences['help_on']==true || this.usrPreferences['help_on']=='true');
        ele = $('<div style="position:absolute;right:6px;top:4px;"><label><input type="checkbox" '
            +(ishelp_on?'checked':'')+'/>explanations</label></div>').prependTo(this.editForm);
        this._on( ele.find('input'), {change: function( event){
            let ishelp_on = $(event.target).is(':checked');
            this.usrPreferences['help_on'] = ishelp_on;
            window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, this.editForm, '.heurist-helper1');
        }});

        if(!isVocab){
            this.editForm.find('.header').css({'min-width':80,width:80});
            this.editForm.css({'overflow-x': 'hidden'});

            this._toolbar.addClass('ui-heurist-bg-light');
        }

       
        window.hWin.HEURIST4.ui.switchHintState2(ishelp_on, this.editForm, '.heurist-helper1');

        this._editing.getFieldByName('trm_Label').find('.input-cell').css({'padding-bottom': '20px'});

        this._adjustEditDialogHeight();

    },   
    
    //
    //
    //
    getLastAddedVocabulary: function(){
        return this.last_added_vocabulary;    
    },
    
    //
    // returns context value that is passed as parameter to function options.onClose
    //
    contextOnClose: function(){
        
        let last_vocab_id = this.vocabularies_div?this.vocabularies_div.manageDefTerms('getLastAddedVocabulary'):0;
        
        return last_vocab_id>0?last_vocab_id:this.options.trm_VocabularyID;
    },


    //
    //
    //
    _getEditDialogButtons: function(){
        let btns = this._super();

        if(this.options.edit_mode=='editonly'){ //for popup

            let that = this;

            if(this.options.container){
                //inlin/inside resultList - edit terms

                btns[0].text = 'Close';
                btns[0].css['visibility'] = 'visible';
                btns[0].class = btns[0].class+' alwaysvisible';

                btns[0].click = function(){
                    if(that.defaultBeforeClose()){
                        if(window.hWin.HEURIST4.util.isFunction(that.options.onClose)){
                            that.options.onClose.call(that, that.contextOnClose() );
                        } 
                    }
                };

                btns.push(
                    {text:window.hWin.HR('Add Child'),
                        icon:'ui-icon-plus',
                        css:{'float':'left',margin:'.5em .4em .5em 0'},  
                        click: function() { 
                            if(that.options.reference_trm_manger && that.options.reference_trm_manger.manageDefTerms('instance')){
                                that.options.reference_trm_manger.manageDefTerms('option','trm_ParentTermID',that._currentEditID);
                                that.options.reference_trm_manger.manageDefTerms('addEditRecord',-1);
                            }
                }});
                btns.push(    
                    {text:window.hWin.HR('Import terms'),
                        icon:'ui-icon-download',
                        showLabel:false,
                        css:{'float':'left',margin:'.5em .4em .5em 0'},  
                        click: function() { 
                            if(that.options.reference_trm_manger && that.options.reference_trm_manger.manageDefTerms('instance')){
                                that.options.reference_trm_manger.manageDefTerms('importTerms',that._currentEditID,false);
                            }
                    }}
                );

            }else{

                btns[0].text = 'Close';
                btns[0].css['visibility'] = 'visible';
                btns[0].class = 'alwaysvisible';

                btns[0].click = function(){
                    if(that.defaultBeforeClose()){
                        /*
                        if(window.hWin.HEURIST4.util.isFunction(that.options.onClose)){
                            //currently selected vocavulary
                            that.options.onClose.call( this, that.options.trm_VocabularyID );
                        }
                        */ 
                        that._currentEditID = null; 
                        that.closeDialog(true);
                        that.options.onClose = null;
                    }
                };
             
            }

        }
        return btns;  
    },

    onEditFormChange: function(changed_element){

        this._super(changed_element);

        if(this._toolbar && this.options.edit_mode=='editonly'){
            let isChanged = this._editing.isModified();
            this._toolbar.find('.btnEditAll').css('visibility', isChanged?'hidden':'visible');
        }

    },

    //
    // update list after save (refresh)
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){

        let that = this;
        if(this.options.edit_mode=='editonly'){
            if(!this.options.container){ //for popup
            
                let keepParent = $Db.trm(recID,'trm_ParentTermID');
                let isVocab = !(keepParent>0); //new term is added
                let sName = (isVocab)?'Vocabulary':'Term';
                if(isVocab){
                    this.options.trm_VocabularyGroupID = $Db.trm(recID,'trm_VocabularyGroupID');
                    this.options.trm_VocabularyID = recID;    
                }else{
                    this.options.trm_ParentTermID = keepParent; //it was reset to -1 in afterInitEditForm
                }

                window.hWin.HEURIST4.msg.showMsgFlash(sName+' '+window.hWin.HR('has been saved'),300);

                if(this.options.create_one_term){
                    if(that.defaultBeforeClose()){
                        if(window.hWin.HEURIST4.util.isFunction(that.options.onClose)){
                            that._currentEditID = null; 
                            that.closeDialog(true);
                            that.options.onClose.call(that, recID);
                        } 
                    }
                }else{
                    this._currentEditID = -1;
                    this._initEditForm_step3(this._currentEditID); //reload edit form 
                }

                setTimeout(function(){that._editing.setFocus();},1000);
            }
            return;
//        }else if(this.it_was_insert && this.options.auxilary=='vocabulary' && this.options.edit_mode=='popup'){
            
        }else if(this.it_was_insert && this.options.auxilary=='term' && this.options.edit_mode=='popup'){

            this.options.trm_ParentTermID = $Db.trm(recID,'trm_ParentTermID'); //it was reset to -1 in afterInitEditForm

            //reload edit page
            window.hWin.HEURIST4.msg.showMsgFlash('Added',300);
           
            this._currentEditID = -1;
            this._initEditForm_step3(this._currentEditID); //reload 
            setTimeout(function(){that._editing.setFocus();},500);
            this.refreshRecordList();
            return;
        }

        // close on addition of new record in select_single mode    
        //this._currentEditID<0 && 
        if(this.options.select_mode=='select_single'){

            this._selection = new HRecordSet();
           
            this._selection.addRecord(recID, fieldvalues);
            this._selectAndClose();

            return;    
        }else if(this.it_was_insert && this.options.auxilary=='vocabulary'){
            
            this.last_added_vocabulary = recID;
            this.selectVocabulary(recID, true);
            
            this._loadData(); // update cached recset			
			
           
        }

        this._super( recID, fieldvalues );
    },

    // 
    // this event handler is always called
    // 
    _afterSaveEventHandler2: function( recID, fieldvalues ){  

        if(this.it_was_insert){
            if(recID>0){ 
                if($Db.trm(recID)==null)
                    $Db.trm().addRecord(recID, fieldvalues);                

                let parent_id = $Db.trm(recID, 'trm_ParentTermID');                
                if(parent_id>0){
                    
                    if(!$Db.trm_IsChild(parent_id, recID)){
                        //add - to avoid duplication                    
                        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
                        if(!t_idx[parent_id]) t_idx[parent_id] = []; 
                        t_idx[parent_id].push(recID);        
                    }
                }
            }
           
           
            /* INLINE EDITOR disabled 2020-12-01            
            //expand formlet after addition
            if(this.options.auxilary=='term'){
            setTimeout(function(){
            that.recordList.resultList('expandDetailsInline',recID); 
            },1000);
            }
            */            
            /*}else if(this.options.auxilary=='vocabulary' && recID>0){
            //highlight in list 
            this.recordList.resultList('setSelected', [recID]);
            }else{*/
        }

        if(this.options.auxilary=='term' && fieldvalues &&
            !window.hWin.HEURIST4.util.isempty(fieldvalues['trm_InverseSymmetrical']))
        {

            //update inverse terms
            let inverse_termid = fieldvalues['trm_InverseTermID'];
            let is_symmetrical = (fieldvalues['trm_InverseSymmetrical']!=0);

            if(this.inverse_termid_old!=inverse_termid && is_symmetrical)
            {
                if(inverse_termid>0){
                    //set mutual inversion for inverse term
                    $Db.trm(inverse_termid, 'trm_InverseTermID', recID);
                }
                if (this.inverse_termid_old>0){
                    //clear mutual inversion for previous inversion
                    let invid = $Db.trm(this.inverse_termid_old, 'trm_InverseTermID');
                    if(invid==recID){
                        $Db.trm(this.inverse_termid_old, 'trm_InverseTermID', null);
                    }
                }
            }

        }

        this._triggerRefresh(this.options.auxilary);    

    },
    
    _saveEditAndClose: function( fields, afterAction, onErrorAction ){

        if(window.hWin.HAPI4.is_callserver_in_progress()) {
            //prevent repeatative call
            return;   
        }

        if(!fields){
            fields = this._getValidatedValues(); 
            if(fields) fields['isfull'] = true;
        }
        if(fields==null) return; //validation failed

        if(!window.hWin.HEURIST4.util.isempty(fields['trm_Parents'])){
            let parents = fields['trm_Parents'].split(',');
           

            // set parent field to acutal parent field, to avoid moving it
            fields['trm_ParentTermID'] = parents[parents.length - 1]; 
        }
        
        let lbl = Array.isArray(fields['trm_Label'])?fields['trm_Label'][0]:fields['trm_Label'];

        if(this._currentEditID == -1 && this.options.auxilary == 'vocabulary' && lbl.search(/vocab/i) == -1){ // add 'vocab' to the end of new vocabulary
            lbl += ' vocab';
            if(Array.isArray(fields['trm_Label'])){
                fields['trm_Label'][0] = lbl;
            }else{
                fields['trm_Label'] = lbl;
            }
        }

        this._super( fields, afterAction, onErrorAction );
    },

    //
    // params: {trm_ID:trm_ID, trm_ParentTermID:trm_ParentTermID }
    //
    mergeTerms: function(params){

        let that = this;   


        let trm_ID = params['trm_ID'];
        let target_id = params['trm_ParentTermID'];

        let vocab_id = $Db.getTermVocab(target_id);
        let vocab_id2 = $Db.getTermVocab(trm_ID);
        if((this.options.trm_VocabularyID!=vocab_id) || (this.options.trm_VocabularyID!=vocab_id2))
        {
            window.hWin.HEURIST4.msg.showMsgFlash( 'Merge with reference is not allowed' ); 
            return;                
        }


        let parents = $Db.trm(target_id, 'trm_Parents');
        if(parents){
            parents = parents.split(',');
            if(window.hWin.HEURIST4.util.findArrayIndex(trm_ID, parents)>=0){
                window.hWin.HEURIST4.msg.showMsgFlash( 'Recursion is not allowed' ); 
                return;
            }
        }else{
            return;
        }    

        let $dlg, buttons = [
            {text:window.hWin.HR('Cancel'),
                css:{'float':'right',margin:'.5em .4em .5em 0'},  
                click: function() { $dlg.dialog( "close" ); }},
            {text:window.hWin.HR('Merge'),
                css:{'float':'right',margin:'.5em .4em .5em 0'},  
                class: 'ui-button-action',
                click: function() { 

                    let request = {
                        'a'          : 'action',
                        'entity'     : that.options.entity.entityName,
                        'request_id' : window.hWin.HEURIST4.util.random(),
                        'merge_id'   : trm_ID,
                        'retain_id'  : target_id                 
                    };

                    let fieldvalues = {};

                    if($dlg.find('#term1_code_cb').is(':checked')){ 
                        request['trm_Code'] = $dlg.find('#term1_code').text();
                    }else if($dlg.find('#term2_code_cb').is(':checked')){ 
                        request['trm_Code'] = $dlg.find('#term2_code').text();
                    }
                    if($dlg.find('#term1_desc_cb').is(':checked')){ 
                        request['trm_Description'] = $dlg.find('#term1_desc').text();
                    }else if($dlg.find('#term2_desc_cb').is(':checked')){ 
                        request['trm_Description'] = $dlg.find('#term2_desc').text();
                    }     

                    if(request['trm_Code']) fieldvalues['trm_Code'] = request['trm_Code'];
                    if(request['trm_Description']) fieldvalues['trm_Description'] = request['trm_Description'];

                    window.hWin.HEURIST4.msg.bringCoverallToFront($dlg.parents('.ui-dialog'));                                             

                    window.hWin.HAPI4.EntityMgr.doRequest(request, 
                        function(response){
                            window.hWin.HEURIST4.msg.sendCoverallToBack();

                            if(response.status == window.hWin.ResponseStatus.OK){

                                //change parent for all children
                                $Db.trm_ChangeChildren(trm_ID, target_id);

                                $Db.trm_RemoveLinks(trm_ID);
                                $Db.trm().removeRecord(trm_ID);  //from record set

                                // Update term usages
                                let count = 0;
                                if(that._cachedUsages[trm_ID]>0){
                                    count = count + that._cachedUsages[trm_ID];
                                    delete that._cachedUsages[trm_ID];
                                }
                                if(that._cachedUsages[target_id]>0){
                                    count = count + that._cachedUsages[target_id];
                                }
                                that._cachedUsages[target_id] = count;

                                if(!$.isEmptyObject(fieldvalues))
                                    $Db.trm().addRecord(target_id, fieldvalues);                
                                that._filterByVocabulary();
                                that._triggerRefresh(that.options.auxilary);


                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);                        
                            }
                    });   


                    $dlg.dialog( "close" ); }}
        ];                


        $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
            +"hclient/widgets/entity/popups/manageDefTermsMerge.html?t="+(new Date().getTime()), 
            buttons, 'Merge Terms', 
            {  container:'terms-merge-popup',
                width:500,
                height:400,
                close: function(){
                    $dlg.dialog('destroy');       
                    $dlg.remove();
                },
                open: function(){
                    //$('#terms-merge-popup')
                    $dlg.css({padding:0});

                    //init elements on dialog open
                    let val1 = $Db.trm(target_id,'trm_OriginatingDBID');
                    if(val1>0){
                        val1 = ' ['+val1+'-'+$Db.trm(target_id,'trm_IDInOriginatingDB')+']';
                    }else val1 = '';
                    let val2 = $Db.trm(trm_ID,'trm_OriginatingDBID');
                    if(val2>0){
                        val2 = ' ['+val2+'-'+$Db.trm(trm_ID,'trm_IDInOriginatingDB')+']';
                    }else val2 = '';

                    $dlg.find('#term1_id').text($Db.trm(target_id,'trm_Label')+val1);
                    $dlg.find('#term2_id').text($Db.trm(trm_ID,'trm_Label')+val2);

                    val1 = $Db.trm(target_id,'trm_Code');
                    $dlg.find('#term1_code').text(val1?val1:'<none>');
                    if(window.hWin.HEURIST4.util.isempty(val1)) window.hWin.HEURIST4.util.setDisabled($dlg.find('#term1_code_cb'),true);
                    $dlg.find('#term1_code_cb').prop('checked',!window.hWin.HEURIST4.util.isempty(val1));

                    val2 = $Db.trm(trm_ID,'trm_Code');
                    $dlg.find('#term2_code').text(val2?val2:'<none>');
                    if(window.hWin.HEURIST4.util.isempty(val2)) window.hWin.HEURIST4.util.setDisabled($dlg.find('#term2_code_cb'),true);
                    $dlg.find('#term2_code_cb').prop('checked',val2 && !val1);


                    val1 = $Db.trm(target_id,'trm_Description');
                    $dlg.find('#term1_desc').text(val1?val1:'<none>');
                    if(window.hWin.HEURIST4.util.isempty(val1)) window.hWin.HEURIST4.util.setDisabled($dlg.find('#term1_desc_cb'),true);
                    $dlg.find('#term1_desc_cb').prop('checked',!window.hWin.HEURIST4.util.isempty(val1));

                    val2 = $Db.trm(trm_ID,'trm_Description');
                    $dlg.find('#term2_desc').text(val2?val2:'<none>');
                    if(window.hWin.HEURIST4.util.isempty(val2)) window.hWin.HEURIST4.util.setDisabled($dlg.find('#term2_desc_cb'),true);
                    $dlg.find('#term2_desc_cb').prop('checked',val2 && !val1);

                    $dlg.find('input[type="checkbox"]').on({change:function(e){
                        let id = $(e.target).attr('id');
                        let id2 = id.indexOf('1')>0 ?id.replace('1','2') :id.replace('2','1');
                        if(!$dlg.find('#'+id2).is(':disabled')){
                            $dlg.find('#'+id2).prop('checked', !$(e.target).is(':checked'));
                        }                   
                    }});




                }  //end open event
        });


    },

    //
    // Change vocab group (for vocabularies) or parent for term
    //                                
    changeVocabularyGroup: function(params, no_check){                                    

        if(params['trm_ParentTermID']>0){

            const trm_ID = params['trm_ID']; //term to be moved
            let new_parent_id = params['trm_ParentTermID']; //destination
            let old_parent_ids = [];

            const vocab_id = $Db.getTermVocab(trm_ID); //real vocabulary
            
            //real vocabulary is different from current - this is reference
            let isRef = (this.options.trm_VocabularyID!=vocab_id); //current vocabulary 
            if (isRef) {
                let parents = $Db.trm(trm_ID, 'trm_Parents');
                if(parents){
                    old_parent_ids = parents.split(',');
                }
            }else{
                old_parent_ids = [$Db.trm(trm_ID, 'trm_ParentTermID')];    
            }

            if(old_parent_ids.length<1){
                console.error('Error !!! Parent not found for '+trm_ID);
                return;
            }

            //change parent within vocab
            if(window.hWin.HEURIST4.util.findArrayIndex(new_parent_id, old_parent_ids)>=0){ //the same
                return;
            }

            let that = this;
            let new_vocab_id;

            //if new parent is vocabulary
            if( !($Db.trm(new_parent_id, 'trm_ParentTermID')>0) ){
                
                new_vocab_id = new_parent_id;

                if(no_check!==true){    
                    //1. check that selected terms are already in this vocabulary
                    let trm_ids = $Db.trm_TreeData(new_parent_id, 'set'); //ids
                    if(window.hWin.HEURIST4.util.findArrayIndex(trm_ID, trm_ids)>=0){
                        window.hWin.HEURIST4.msg.showMsgDlg( (isRef?'Reference':'Term')
                            + ' <b>"'+$Db.trm(trm_ID, 'trm_Label')
                            +'"</b> is already in vocabulary <b>"'+$Db.trm(new_parent_id,'trm_Label')+'"</b>',null,'Duplication',
                            {default_palette_class:this.options.default_palette_class}); 
                        return;
                    }
                    //2. check there is not term with the same name
                    let trm_labels = $Db.trm_TreeData(new_parent_id, 'labels'); //labels in lowcase
                    let lbl = $Db.trm(trm_ID, 'trm_Label');
                    if(trm_labels.indexOf(lbl.toLowerCase())>=0){

                        let $dlg;
                        let msg = (isRef?'Reference':'Term')
                                    + ' with name <b>"'+lbl
                                    +'"</b> is already among children of <b>"'+$Db.trm(new_parent_id,'trm_Label')+'"</b>'
                                    +'<p>To make this move, edit the term label so that it is disambiguated (2, 3, ...) or slightly different '
                                    +'from any term in the top level of the vocabulary to which you wish to move it.'
                                    +'Once moved, you can merge within the vocabulary or reposition the term and edit it appropriately.</p>';

                        let btns = {};
                        btns['Cancel'] = function(){
                            $dlg.dialog('close');
                        };
                        btns['Move term with disambiguation'] = function(){

                            params['trm_Label'] = lbl;
                            let i = 2;
                            while(true){

                                let new_label = lbl + ' ' + i;

                                if(trm_labels.indexOf(new_label.toLowerCase()) < 0){
                                    params['trm_Label'] = new_label;
                                    break;
                                }
                            }

                            let old_parent_id = old_parent_ids[old_parent_ids.length-1];
                            let old_vocab_id = old_parent_ids[0];

                            if(isRef){
                                that._saveEditAndClose( {'trm_ID': params['trm_ID'], 'trm_Label': params['trm_Label'] },
                                    function(){
                                        //change parent for reference  @todo - take correct old_parent_ids
                                        $Db.setTermReferences(trm_ID, new_vocab_id, new_parent_id, old_vocab_id, old_parent_id,
                                                function(){
                                                    that.it_was_insert = true;
                                                    that._afterSaveEventHandler2();//to reset filter and trigger global refresh
                                                });
                                    },
                                    onTermSaveError
                                );
                            }else{

                                that._saveEditAndClose( params ,  //change in defTerms
                                    function(){  
                                        if(params.trm_ParentTermID>0){
                                            $Db.changeParentInIndex(new_parent_id, trm_ID, old_parent_id);
                                            that._filterByVocabulary();
                                        }
                                        that._triggerRefresh('term');
                                    },
                                    onTermSaveError
                                );
                            }

                            $dlg.dialog('close');
                        };

                        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, 
                            {title: 'Duplication', no: 'Cancel', yes: 'Move term with disambiguation'},
                            {default_palette_class:this.options.default_palette_class}); 
                        return;
                    }
                }

            }else{
                
                new_vocab_id = this.options.trm_VocabularyID;

                const vocab_id = $Db.getTermVocab(new_parent_id); //get real vocab
                if(this.options.trm_VocabularyID!=vocab_id){
                    window.hWin.HEURIST4.msg.showMsgDlg( 'Reference can\'t have children<br><br>'
                        + 'Please EDIT/move the term <i>'
                        + $Db.trm(new_parent_id, 'trm_Label') 
                        + '</i> within its original vocabulary <i>'
                        + $Db.trm(vocab_id, 'trm_Label') +'</i>',null,'Warning',
                            {default_palette_class:this.options.default_palette_class}); 
                    return;
                }

                if(no_check!==true){    
                    //1. check that selected terms are already in this vocabulary
                    let trm_ids = $Db.trm_TreeData(new_parent_id, 'set'); //ids
                    
                    if(isRef && window.hWin.HEURIST4.util.findArrayIndex(trm_ID, trm_ids)>=0){
                        window.hWin.HEURIST4.msg.showMsgDlg( (isRef?'Reference':'Term')
                            + ' <b>"'+$Db.trm(trm_ID, 'trm_Label')
                            +'"</b> is already in vocabulary <b>"'+$Db.trm(new_parent_id,'trm_Label')+'"</b>',null,'Duplication',
                            {default_palette_class:this.options.default_palette_class}); 
                        return;
                    }
                    //2. check there is not term with the same name
                    let lbl = $Db.trm(trm_ID, 'trm_Label');
                    if($Db.trm_HasChildWithLabel(new_parent_id, lbl))                    
                    {
                        let $dlg;
                        let msg = (isRef?'Reference':'Term')
                                    + ' with name <b>"'+lbl
                                    +'"</b> is already among children of <b>"'+$Db.trm(new_parent_id,'trm_Label')+'"</b>'
                                    +'<p>To make this move, edit the term label so that it is disambiguated (2, 3, ...) or slightly different '
                                    +'from any term in the top level of the vocabulary to which you wish to move it.'
                                    +'Once moved, you can merge within the vocabulary or reposition the term and edit it appropriately.</p>';

                        let btns = {};
                        btns['Cancel'] = function(){
                            $dlg.dialog('close');
                        };
                        btns['Move term with disambiguation'] = function(){
                            params['trm_Label'] = lbl;
                            let i = 2;
                            while(true){

                                let new_label = lbl + ' ' + i;

                                if(!$Db.trm_HasChildWithLabel(new_parent_id, new_label)){
                                    params['trm_Label'] = new_label;
                                    break;
                                }
                            }

                            let old_parent_id = old_parent_ids[old_parent_ids.length-1];
                            let old_vocab_id = old_parent_ids[0];

                            if(isRef){
                                that._saveEditAndClose( {'trm_ID': params['trm_ID'], 'trm_Label': params['trm_Label'] },
                                    function(){
                                        //change parent for reference  @todo - take correct old_parent_ids
                                        $Db.setTermReferences(trm_ID, new_vocab_id, new_parent_id, old_vocab_id, old_parent_id,
                                                function(){
                                                    that.it_was_insert = true;
                                                    that._afterSaveEventHandler2();//to reset filter and trigger global refresh
                                                });
                                    },
                                    onTermSaveError
                                );
                            }else{

                                that._saveEditAndClose( params ,  //change in defTerms
                                    function(){  
                                        if(params.trm_ParentTermID>0){
                                            $Db.changeParentInIndex(new_parent_id, trm_ID, old_parent_id);
                                            that._filterByVocabulary();
                                        }
                                        that._triggerRefresh('term');
                                    },
                                    onTermSaveError
                                );
                            }

                            $dlg.dialog('close');
                        };

                        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, 
                            {title: 'Duplication', no: 'Cancel', yes: 'Move term with disambiguation'},
                            {default_palette_class:this.options.default_palette_class}); 
                        return;
                    }
                }

                let parents = $Db.trm(new_parent_id, 'trm_Parents');
                if(parents){
                    parents = parents.split(',');
                    if(window.hWin.HEURIST4.util.findArrayIndex(trm_ID, parents)>=0){
                        window.hWin.HEURIST4.msg.showMsgFlash( 'Recursion is not allowed' ); 
                        return;
                    }
                }else{
                    return;
                }
            }
            
            let old_parent_id = old_parent_ids[old_parent_ids.length-1];

            if(isRef){
                let old_vocab_id = old_parent_ids[0];
                //change parent for reference  @todo - take correct old_parent_ids
                $Db.setTermReferences(trm_ID, new_vocab_id, new_parent_id, old_vocab_id, old_parent_id,
                            function(){
                                that.it_was_insert = true;
                                that._afterSaveEventHandler2();//to reset filter and trigger global refresh
                            });
            }else{

                this._saveEditAndClose( params ,  //change in defTerms
                    function(){  
                        if(params.trm_ParentTermID>0){
                            $Db.changeParentInIndex(new_parent_id, trm_ID, old_parent_id);
                            that._filterByVocabulary();
                        }
                        that._triggerRefresh('term');
                    },
                    onTermSaveError
                );

            }
        }else{
            //change vocabulary group
            let that = this;
            this._saveEditAndClose( params ,
                function(){
                    that._triggerRefresh('vcg');
            });

        }

    },
    
    //
    // extend for group actions
    //
    _onActionListener: function(event, action){

        let that = this;

        let keep_action = action;
        let recID;
        if(action && action.action){
            recID =  action.recID;
            action = action.action;
        }

        if(action=='add'){

            if(this.options.auxilary=='vocabulary'){
                if(this.options.trm_VocabularyGroupID<0){
                    window.hWin.HEURIST4.msg.showMsgFlash('Vocabulary group is not defined');
                    return true;        
                }
            }else if(this.options.trm_VocabularyID<0){
                window.hWin.HEURIST4.msg.showMsgFlash('Vocabulary is not defined');                
                return true;        
            }
           
            this.addEditRecord(-1);        
            return true;
        }else if(action=='moveup'){
            //move term to level up
            let parent_id = $Db.trm(recID, 'trm_ParentTermID');
            parent_id = $Db.trm(parent_id, 'trm_ParentTermID');
            if(parent_id>0)
                this.changeVocabularyGroup({trm_ID:recID, trm_ParentTermID:parent_id}, true);            
            
        }else if(action=='add-child'){

            this.options.trm_ParentTermID = recID;
            this.addEditRecord(-1);        
            return true;

        }else if(action=='sort-branch'){

            let $ele = this.element.find('div[recid="'+ recID +'"]');
            let parent_codes = $ele.attr('data-parent').split(';');

            let ids = [];
            this.element.find('[data-parent="'+parent_codes.join(';')+'"]').each((idx, ele) => {
                ids.push($(ele).attr('recID'));
            });

            if(ids.length <= 1){
                return;
            }

            this.reoderBranch(ids);
        }else if(action=='add-reference'){

            if(this.options.trm_VocabularyID<0){
                window.hWin.HEURIST4.msg.showMsgFlash('Vocabulary is not defined');                
                return true;        
            }            
            //open multi selector
            let popup_options = {
                select_mode:'select_multi',
                select_mode_reference: 'reference', 
                select_mode_target: $Db.trm(this.options.trm_VocabularyID,'trm_Label'),
                selectbutton_label: 'Add Reference',
                initial_filter: this.options.trm_VocabularyID,
                width: 350,
                height: Math.min(600,(window.hWin?window.hWin.innerHeight:window.innerHeight)*0.75),
                title: 'Add terms by reference',
                onselect:function(event, data){

                    let sels;
                    if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                        sels = data.selection.getIds();
                    }else{
                        sels = data.selection;
                    }
                    //add new term to vocabulary by reference
                    $Db.setTermReferences(sels, that.options.trm_VocabularyID, 0, 0, 0,
                            function(){
                                that.it_was_insert = true;
                                that._afterSaveEventHandler2();//to reset filter and trigger global refresh
                            });
                }
            }

            // to vocabulary "'+ $Db.trm(this.options.trm_VocabularyID,'trm_Label')+ '"'

            window.hWin.HEURIST4.ui.showEntityDialog('defTerms', popup_options);

        }else if(action=='edit-inline'){

            this.recordList.resultList('expandDetailsInline', recID);

            return true;

        }else if(action=='delete'){

            if(this.options.auxilary=='vocabulary'){


                //find usage of this vocab in enum base fields
                let refs = [];               
                $Db.dty().each2(function(dty_ID, record){ 

                    let dty_Type = record['dty_Type'];
                    if((dty_Type=='enum' || dty_Type=='relmarker') && 
                        (recID == record['dty_JsonTermIDTree'])) 
                    {
                        refs.push( dty_ID );        
                    }
                });

                if(refs.length>0){

                    showWarningAboutTermUsage(recID, refs);
                    return;
                }

                //check for children 
                if($Db.trm_HasChildren(recID)){

                    window.hWin.HEURIST4.msg.showMsgDlg(
                        'Delete vocabulary <b>'+$Db.trm(recID, 'trm_Label')+'</b> and all its child terms?',
                        function(){ that._currentEditID = recID; that._deleteAndClose(true) }, 
                        {title:'Deletion of vocabulary',yes:'Proceed',no:'Cancel'},
                        {default_palette_class:this.options.default_palette_class});        
                    return false;                    
                }               


            }else{

                //check for reference    
                let ref_lvl = $Db.isTermByReference(this.options.trm_VocabularyID, recID);

                if(ref_lvl===false){//this term is not a reference

                    let sMsg = '';

                    //check usage as reference
                    let v_ids = $Db.trm_getAllVocabs(recID);
                    if(v_ids.length>1){
                        let names = [];
                        for(let i=0; i<v_ids.length; i++){
                            names.push($Db.trm(v_ids[i],'trm_Label'));    
                        }

                        sMsg = '<p>The term you are deleting is referenced by the '+names.join(', ')
                        +' vocabularies. If you delete it it will be removed from those vocabularies.</p>';
                    }

                    //check for children 
                    if($Db.trm_HasChildren(recID)){
                        sMsg += '<p>Delete branch <b>'+$Db.trm(recID, 'trm_Label')+'</b> and all its child terms?</p>'
                    }               

                    if(sMsg){
                        window.hWin.HEURIST4.msg.showMsgDlg(
                            sMsg,
                            function(){ that._currentEditID = recID; that._deleteAndClose(true) }, 
                            {title:'Deletion of branch',yes:'Proceed',no:'Cancel'},
                            {default_palette_class:this.options.default_palette_class});        
                        return false;                    
                    }

                }else if(ref_lvl>0){

                    window.hWin.HEURIST4.msg.showMsgDlg('This term is added as reference via its parent. '
                        +'You can remove this reference along with it parents only',null,null,
                        {default_palette_class:this.options.default_palette_class});
                    return;

                }else{

                    window.hWin.HEURIST4.msg.showMsgDlg(
                        'You are going to remove the term reference. Actual term is retained.', 
                        function(){ 
                            //find parent 
                            let parents = $Db.trm(recID, 'trm_Parents');
                            if(parents){
                                parents = parents.split(',');
                                let parent_id = parents[parents.length-1]; 
                                if(parent_id>0){
                                    //removing refterm entry in trm links
                                    $Db.setTermReferences(recID, 0, 0, that.options.trm_VocabularyID, parent_id,
                                        function(){
                                            that.it_was_insert = true;
                                            that._afterSaveEventHandler2();//to reset filter and trigger global refresh
                                        });
                                }
                            }
                        }, 
                        {title:'Info',yes:'Proceed',no:'Cancel'},
                        {default_palette_class:this.options.default_palette_class});        
                    return true;

                }

            }

            //remove unconditionally
            this._currentEditID = recID; 
            this._deleteAndClose();
            return true;
        }
       

        else if(action=='expand'){
            
            this._toogleTermsBranch(recID);
        }

        let is_resolved = this._super(event, keep_action);

        if(!is_resolved){
            /*
            if(action && action.action){
            recID =  action.recID;
            action = action.action;
            }
            */            
            /*
            if(action=='add-group' || action=='edit-group'){

            if(action=='add-group') recID = -1;

            let entity_dialog_options = {
            select_mode: 'manager',
            edit_mode: 'editonly', //only edit form is visible, list is hidden
            //select_return_mode:'recordset',
            title: (action=='add-group'?'Add':'Edit')+' Vocabulary Group',
            rec_ID: recID,
            selectOnSave:true,
            onselect:function(res){
            if(res && window.hWin.HEURIST4.util.isArrayNotEmpty(res.selection)){
           
           
            }
            }
            };            
            window.hWin.HEURIST4.ui.showEntityDialog('defVocabularyGroups', entity_dialog_options);
            }else if(action=='delete-group' && recID>0){

            window.hWin.HEURIST4.msg.showMsgDlg(
            'Are you sure you wish to delete this vocabulary type?', function(){

            let request = {
            'a'          : 'delete',
            'entity'     : 'defVocabularyGroups',
            'recID'      : recID                     
            };
            window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
            if(response.status == window.hWin.ResponseStatus.OK){
            $Db.vcg().removeRecord( recID );
            that._triggerRefresh('vcg', recID);
            }else{
            window.hWin.HEURIST4.msg.showMsgErr(response);
            }
            });

            }, 
            {title:'Warning',yes:'Proceed',no:'Cancel'});        


            }else 
            */            
            if(action=='term-import'){

                if(this.options.auxilary=='vocabulary'){
                    this.importTerms(this.options.trm_VocabularyGroupID, true);
                }else{
                    this.importTerms(this.options.trm_VocabularyID, false);    
                }

            }else if(action=='term-import-translations'){

                this.importTermsTranslations(this.options.trm_VocabularyID);    
                
            }else if(action=='term-export'){

                if(this.options.auxilary=='vocabulary'){
                    this.exportTerms(this.options.trm_VocabularyGroupID, true);
                }else{
                    this.exportTerms(this.options.trm_VocabularyID, false);    
                }


            }else if(action=='viewmode-list'){

                if(this.recordTree) this.recordTree.hide();      
                this.recordList.show();
                this.recordList.resultList('applyViewMode','list');

            }else if(action=='viewmode-thumbs'){

                if(this.recordTree) this.recordTree.hide();      
                this.recordList.show();
                this.recordList.resultList('applyViewMode','thumbs');

            }else if(action=='viewmode-tree'){ //NOT USED - @todo rempove all recordTree mentions

                if(!this.recordTree){
                    this.recordTree = $('<div class="ent_content_full" style="display:none;"></div>')
                    .addClass('ui-heurist-bg-light')
                    .css({'font-size':'0.8em'})
                    .insertAfter(this.recordList);

                    //this.getRecordSet()
                    /*                                   
                    let treedata = this.recordList.resultList('getRecordSet')
                    .getTreeViewData('trm_Label', 'trm_ParentTermID',
                    this.options.trm_VocabularyID);
                    */                                                 
                    let treedata = $Db.trm_TreeData(this.options.trm_VocabularyID, 'tree');

                    this.recordTree.fancytree(
                        {
                            activeVisible:true,
                            checkbox: false,
                            source:treedata,
                            activate: function(event, data){
                                // A node was activated: display its details
                               
                            },
                    })
                    .css({'font-weight':'normal !important'});
                }

                this.recordTree.css({top:this.recordList.position().top}).show();      
                this.recordList.hide();

            }else if(action == 'term-delete-mutliple'){ // delete all selected terms

                let selected_recs = this.recordList.resultList('getSelected', true);
                if(selected_recs.length == 0){
                    return;
                }

                that._deleteAndClose(false, selected_recs);
            }
        }
    },

    //
    // force_expand_collapse - 0 toggle, 1 expand, -1 collapse
    //
    _toogleTermsBranch: function(recID, force_expand_collapse)
    {
        let is_collapsed = window.hWin.HEURIST4.ui.collapsed_terms.indexOf(recID)>=0;
        
        if(force_expand_collapse==1) is_collapsed = true;
        else if(force_expand_collapse==-1) is_collapsed = false;

        //find all record divs with given parent and show them
        $.each(this.recordList.find('div.recordDiv[data-parent]'),
            function(i,item){
                item = $(item);
                if(item.attr('data-parent').indexOf(';'+recID+';')>=0){
                    
                    if(is_collapsed){
                        item.show();
                    }else{
                        item.hide();
                    }
                }
        });

        is_collapsed =!is_collapsed;

        let ele = this.recordList.find('div.recordDiv[recID='+recID+']')
        .find('span.ui-icon-triangle-1-'+(is_collapsed?'s':'e'));
        
        let k = window.hWin.HEURIST4.ui.collapsed_terms.indexOf(recID);
        
        if(is_collapsed){
            if(k<0) window.hWin.HEURIST4.ui.collapsed_terms.push(recID);
            ele.removeClass('ui-icon-triangle-1-s').addClass('ui-icon-triangle-1-e');
        }else{
            if(k>=0) window.hWin.HEURIST4.ui.collapsed_terms.splice(k, 1);
            ele.removeClass('ui-icon-triangle-1-e').addClass('ui-icon-triangle-1-s');
        }
    },
            

    //
    // show dropdown for field suggestions to be added
    //
    _onFindTerms: function(event){

        let input_name = $(event.target);

        if(this.fields_list_div == null){
            //init for the first time
            let max_h = this.element.height() - 75;
            this.fields_list_div = $('<div class="list_div" '
                +`style="z-index:999999999;height:auto;max-height:${max_h}px;padding:4px;cursor:pointer;overflow-y:auto"></div>`)
            .css({border: '1px solid rgba(0, 0, 0, 0.2)', margin: '2px 0px', background:'#F4F2F4'})
            .appendTo(this.element);
            this.fields_list_div.hide();

            //click outside
            this._on( $(document), {click: function(event){
                if($(event.target).parents('.list_div').length==0) { 
                    this.fields_list_div.hide(); 
                };
            }});
        }

        //find terms
        if(input_name.val().length>1){

            let term_name, term_code, is_added = false;

            this.fields_list_div.empty();  

            let that = this;

            let entered = input_name.val().toLowerCase();

            //find among fields that are not in current record type
            $Db.trm().each(function(trm_ID, rec){

                term_name = $Db.trm(trm_ID, 'trm_Label');
                term_code  = $Db.trm(trm_ID, 'trm_Code');

                if(term_name.toLowerCase().indexOf( entered )>=0 || 
                    (term_code && term_code.toLowerCase().indexOf( entered )>=0))
                {
                    let ele = $('<div>').css({'padding-top':'4px','border-bottom':'0.5px solid lightgray'})
                                .appendTo(that.fields_list_div);

                    //find parents
                    let s = '', ids = [trm_ID];
                    let term_parent = 0;
                    do{
                        term_parent = $Db.trm(trm_ID, 'trm_ParentTermID');
                        if(term_parent>0){
                            ids.push(term_parent);
                            s = $Db.trm(term_parent, 'trm_Label') + ' - ' + s;
                        }
                        trm_ID = term_parent;
                    }while(term_parent>0);

                    let is_valid_domain = true;
                    //get vocabulary group
                    if(that.options.filter_groups){
                        is_valid_domain = ($Db.trm(ids[ids.length-1], 'trm_Domain')==that.options.filter_groups);
                    }

                    if(is_valid_domain){

                        is_added = true;
                        ele.attr('trm_IDs',ids.join(','))
						.css({'padding-bottom':'5px'})
                        .html(` ${s} <strong>${term_name}</strong> ${(term_code?(' ('+term_code+')'):'')} `)
                        .on('click', function(event){
                            //start search the particular term
                            
                            window.hWin.HEURIST4.util.stopEvent(event);

                            let ele = $(event.target).hide();
                            let trm_IDs = ele.attr('trm_IDs');
                            if(!trm_IDs) trm_IDs = ele.parent().attr('trm_IDs');
                            if(trm_IDs) trm_IDs = trm_IDs.split(',');
                            
                            if(Array.isArray(trm_IDs) && trm_IDs.length>0){
                                
                                that.fields_list_div.hide();
                                //select vocabulary group, select vocab
                                let vocab_id  = trm_IDs[trm_IDs.length-1];

                                if(that.options.select_mode!='manager'){
                                    that.vocabularies_sel.val(vocab_id); 
                                }else{
                                    that.vocabularies_div.manageDefTerms('selectVocabulary', vocab_id);
                                }

                                if(trm_IDs.length>1){
                                    setTimeout(function(){
                                        //find all parents and expand them in resultList
                                        let trm_ID = trm_IDs[0];
                                        let parents = $Db.trm(trm_ID, 'trm_Parents');
                                        if(parents){
                                            parents = parents.split(',');
                                            $.each(parents, function(i, parent_ID){
                                                that._toogleTermsBranch(parent_ID, 1);    
                                            });
                                        }
                                        
                                        that.selectRecordInRecordset(trm_IDs[0]);
                                    },500);    
                                }



                                input_name.val('').trigger('focus');
                            }
                        });
                    }

                }                
            });


            if(is_added){
                this.fields_list_div.position({my:'right top', at:'right bottom', of:input_name})
                .css({'max-width':input_name.width()+250+'px'});
                this.fields_list_div.show();    
            }else{
                this.fields_list_div.hide();
            }

        }else{
            this.fields_list_div.hide();  
        }

    },

    _getValidatedValues: function(){

        let fields = this._super();  
        if(fields!==null){

            let trm_id = fields['trm_ID'];
            let vocab_id = fields['trm_ParentTermID'];
            if(vocab_id>0){

                let lbl_orig = Array.isArray(fields['trm_Label'])?fields['trm_Label'][0]:fields['trm_Label'];
                let lbl = lbl_orig.toLowerCase();
                let code = Array.isArray(fields['trm_Code'])?fields['trm_Code'][0]:fields['trm_Code'];

                // check if parent term has child with same label
                if($Db.trm_HasChildWithLabel(vocab_id, lbl, trm_id)){ 
                    window.hWin.HEURIST4.msg.showMsgFlash('Term with label "'
                        +lbl_orig+'" already exists in parent ' + $Db.trm(vocab_id, 'trm_Label'),1500);
                    return null;
                }

                // check if parent term has child with same code
                if(!window.hWin.HEURIST4.util.isempty(code) && $Db.trm_HasChildWithCode(vocab_id, code, trm_id)){
                    window.hWin.HEURIST4.msg.showMsgFlash('Term with code "'
                        +code+'" already exists in parent ' + $Db.trm(vocab_id, 'trm_Label'),1500);
                    return null;
                }
            }
        }

        return fields;
    },


    //
    //
    getUiPreferences:function(){
        this.usrPreferences = window.hWin.HAPI4.get_prefs_def('prefs_'+this._entityName, {
            help_on: true
        });
        return this.usrPreferences;
    },

    //saveUiPreferences:function() { this._super(); }, 
    
    //
    // invokes popup to import list of terms from file
    //
    importTerms: function(parent_ID, isVocab) {

        let sTitle;
        if(isVocab){
            sTitle = 'Import vocabularies (excluding terms) for vocabulary group: '+
            window.hWin.HEURIST4.util.htmlEscape($Db.vcg(parent_ID,'vcg_Name'));                
        }else{
            const isTerm = ($Db.trm(parent_ID,'trm_ParentTermID')>0);

            sTitle = 'Import terms '+(isTerm?'as children of term: ' :'of vocabulary: ')+
            window.hWin.HEURIST4.util.htmlEscape($Db.trm(parent_ID,'trm_Label'));                
        }

        let that = this;

        let sURL = window.hWin.HAPI4.baseURL + "import/delimited/importDefTerms.php?db="
        + window.hWin.HAPI4.database +
        (isVocab?'&vcg_ID=':'&trm_ID=')+parent_ID;

        window.hWin.HAPI4.SystemMgr.user_log(`imp_${isVocab ? 'Vocabulary' : 'Terms'}`);

        window.hWin.HEURIST4.msg.showDialog(sURL, {
            default_palette_class: 'ui-heurist-design',
            "close-on-blur": false,
            "no-resize": false,                  
            title: sTitle,
            height: 600,
            width: 900,
            'context_help': 'defTerms #import',
            callback: function(context){ 

                if(context && context.result)
                {
                    if(that.options.auxilary=='vocabulary'){
                        that._loadData(); //reload
                    }else{
                        that._filterByVocabulary();    
                    }

                    window.hWin.HEURIST4.msg.showMsgDlg(context.result.length
                        + ' term'
                        + (context.result.length>1?'s were':' was')
                        + ' added.', null, 'Terms imported',
                        {default_palette_class:that.options.default_palette_class});

                    that._triggerRefresh('trm');
                }
            }
        });

    },

    //
    // invokes popup to import translations of terms from file
    //
    importTermsTranslations: function(parent_ID) {

        let isTerm = ($Db.trm(parent_ID,'trm_ParentTermID')>0);
        let sTitle = 'Import terms translations for '+(isTerm?'children of term: ' :'vocabulary: ')+
        window.hWin.HEURIST4.util.htmlEscape($Db.trm(parent_ID,'trm_Label'));                

        let that = this;

        let sURL = window.hWin.HAPI4.baseURL + "import/delimited/importDefTerms.php?trn=1&db="
        + window.hWin.HAPI4.database + '&trm_ID=' +parent_ID;

        window.hWin.HAPI4.SystemMgr.user_log('imp_TermTranslations');

        window.hWin.HEURIST4.msg.showDialog(sURL, {
            default_palette_class: 'ui-heurist-design',
            "close-on-blur": false,
            "no-resize": false,                  
            title: sTitle,
            height: 600,
            width: 900,
            'context_help': 'defTerms #import',
            callback: function(context){ 

                if(context && context.result)
                {
                    window.hWin.HEURIST4.msg.showMsgDlg(
                    context.result.cnt_added+' translations added<br>'+
                    context.result.cnt_updated+' translations updated<br>'+
                    context.result.cnt_error+' errors on addition<br>'+
                    context.result.cnt_lang_missed+' language not detected<br>'+
                    context.result.cnt_not_found+' terms not found<br>',
                         null, 'Terms translations imported',
                        {default_palette_class:that.options.default_palette_class});

                    that._triggerRefresh('trm');
                }
            }
        });

    },
    
    //
    // invokes popup to import list of terms from file
    //
    exportTerms: function(parent_ID, isVocab) {
        let s;
        let trm_Children = [];
        if(isVocab){
            let vocabs = $Db.trm_getVocabs();
            $.each(vocabs, function(i,trm_ID){  
                if($Db.trm(trm_ID, 'trm_VocabularyGroupID')==parent_ID){
                    trm_Children.push(trm_ID);
                }
            });

            s = 'Vocabulary,Internal code,Vocabulary group,Group internal code,Standard Code,Description\n';
        }else{
            trm_Children = $Db.trm_TreeData(parent_ID, 'set');
            s = 'Term,Internal code,Parent term,Parent internal code,Standard Code,Description\n';
        }

        let vocab_name = $Db.trm(parent_ID, 'trm_Label');
        vocab_name = (!vocab_name) ? 'heurist' : window.hWin.HAPI4.database + '_v' + parent_ID + '_' + vocab_name;

        for(let i=0; i<trm_Children.length; i++){
            const trm_ID = trm_Children[i];
            let term = $Db.trm(trm_ID);

            let aline = ['"'+term['trm_Label']+'"',trm_ID,'',0,'"'+term['trm_Code']+'"','"'+term['trm_Description']+'"'];

            if(isVocab){
                const parent_ID = term['trm_VocabularyGroupID'];
                aline[2] = '"'+$Db.vcg(parent_ID,'vcg_Name')+'"';
                aline[3] = parent_ID;
            }else{
                const parent_ID = term['trm_ParentTermID'];
                aline[2] = '"'+$Db.trm(parent_ID,'trm_Label')+'"';
                aline[3] = parent_ID;
            }

            s = s + aline.join(',') + '\n';
        }

        window.hWin.HEURIST4.util.downloadData(vocab_name + '_vocabulary.csv', s, 'text/csv');
    },

    //
    // Retrieve term usage and update record list with values, placed with square brackets '[' & ']'
    //  For terms with zero usage, set text color to grey 
    //
    updateTermUsage: function(term_usages){

        if(window.hWin.HEURIST4.util.isempty(term_usages)){
            term_usages = {};
        }

        const that = this;

        this._cachedUsages = $.extend(this._cachedUsages, term_usages);

        if(window.hWin.HEURIST4.util.isempty(this._cachedUsages)){
            return;
        }

        this._off(this.recordList.find('.term_usage.has-usage'), 'click');

        $.each(this.recordList.find('.recordDiv'), function(idx, record){

            let $record = $(record);
            let $label = $record.find('.label_term');
            let $usage = $label.find('.term_usage');

            const trm_id = $record.attr('recid');

            if(trm_id && that._cachedUsages[trm_id] && that._cachedUsages[trm_id] > 0){ // has usage
                $usage.text('[' + that._cachedUsages[trm_id] + ']')
                      .addClass('has-usage')
                      .css({
                        'text-decoration': 'underline',
                        cursor: 'pointer'
                      });
                $label.css('color', '');
            }else{ // not used
                $usage.text('-')
                      .removeClass('has-usage')
                      .attr('style', '');
                $label.css('color', 'gray');
            }
        });

        this._on(this.recordList.find('.term_usage.has-usage'), {
            click: (e) => {

                let $parent_div = $(e.target).parents('.recordDiv');
                if($parent_div.length == 0){
                    return;
                }

                const id = $parent_div.attr('recid');

                let query = `[{"f":"=${id}"}]`;
                window.open(window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database + '&q=' + encodeURIComponent(query), '_blank');
            }
        });
    },

    //
    // Show popup with listed terms, and allow sorting
    //
    reoderBranch: function(term_ids){

        if(!term_ids || term_ids.length <= 1){
            return;
        }

        let that = this;
        let $dlg, content;
        let btns = {};

        // Popup content - Draggable list of branch terms
        content = '<div style="padding-bottom: 5px"><span class="heurist-helper3">drag and drop terms to re-arrange the branch<br></span>' //'any child terms will remain with its parent term'
            + '<div class="div-result-list-content list" style="font-size: larger; max-height: 500px; overflow: hidden auto; margin: 10px 0;">';

        for (let i = 0; i < term_ids.length; i++) {

            let trm_id = term_ids[i];
            let trm_name = $Db.trm(trm_id, 'trm_Label');

            content += '<div class="recordDiv" data-id="' + trm_id + '" title="' + trm_name + '">' + trm_name + '</div>';
        }

        content += '</div></div>';
        // Popup buttons
        btns['Save order'] = () => {
            // Set + Save trm_OrderInBranch from 1 up
            let terms = $dlg.find('div.recordDiv');
            let records = [];
            let ordering = 1;

            $.each(terms, (idx, term) => {

                term = $(term);
                let record = {};
                record['trm_ID'] = term.attr('data-id');
                record['trm_OrderInBranch'] = ordering;

                ordering ++;
                records.push(record);
            });

            let request = {
                'a': 'save',
                'entity': 'defTerms',
                'fields': records,
                'isfull': 0,
                'request_id': window.hWin.HEURIST4.util.random()
            };

            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    $dlg.dialog('close');
                    if(response.status == window.hWin.ResponseStatus.OK){
                        for (let i = 0; i < records.length; i++) {
                            $Db.trm(records[i]['trm_ID'], 'trm_OrderInBranch', records[i]['trm_OrderInBranch']);
                        }
                       
                        that._triggerRefresh(that.options.auxilary);
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                }
            );
        };
        btns['Alphabetic'] = () => {
            // Set + Save trm_OrderInBranch to NULL
            let terms = $dlg.find('div.recordDiv');
            let records = [];

            $.each(terms, (idx, term) => {

                term = $(term);
                let record = {};
                record['trm_ID'] = term.attr('data-id');
                record['trm_OrderInBranch'] = null;

                records.push(record);
            });

            let request = {
                'a': 'save',
                'entity': 'defTerms',
                'fields': JSON.stringify(records),
                'isfull': 0,
                'request_id': window.hWin.HEURIST4.util.random()
            };

            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    $dlg.dialog('close');
                    if(response.status == window.hWin.ResponseStatus.OK){
                        for (let i = 0; i < records.length; i++) {
                            $Db.trm(records[i]['trm_ID'], 'trm_OrderInBranch', null);
                        }
                       
                        that._triggerRefresh(that.options.auxilary);
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                }
            );
        };
        btns['Cancel'] = () => {
            $dlg.dialog('close');
        };

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(content, btns, {title: 'Reodering Term Branch'}, {default_palette_class: 'ui-heurist-design', width: '450px'});

        $dlg.find('div.list').sortable({
            axis: 'y',
            containment: $dlg.find('div.list').parent(),
            forcePlaceholderSize: true,
            placeholder: 'ui-drag-drop',
            cursor: 'grabbing'
        });
    },
    
    getRecordsetFromRemote: function( terms, hideDisabled, vocab_only ){
        
        let rdata = { 
            entityName:'defTerms',
            total_count: 0,
            fields:[],
            records:{},
            order:[] };

        terms = window.hWin.HEURIST4.util.cloneJSON(terms);

        rdata.fields = terms.commonFieldNames;
        rdata.fields.unshift('trm_ID');
        
        let idx_ccode = 0;
        let idx_parent_terms = 0;
        if(this.options.import_structure){
            rdata.fields.push('trm_ID_local');
            idx_ccode = terms.fieldNamesToIndex.trm_ConceptID;
            idx_parent_terms = terms.fieldNamesToIndex.trm_ParentTermID;
        }

        let idx_groupid = terms.fieldNamesToIndex.trm_VocabularyGroupID;
        let hasFieldToImport = false;
        let trash_id = -1;

        for (let key in terms.groups){
            if(terms.groups[key].name == 'Trash'){
                trash_id = terms.groups[key].id;
                break;
            }
        }

        for (let r_id in terms.termsByDomainLookup.enum)
        {
            if(r_id>0){
                let term = terms.termsByDomainLookup.enum[r_id];
                let isHidden = (term[idx_groupid] == trash_id || (vocab_only && term[idx_parent_terms] != null));

                if(hideDisabled && isHidden){
                    continue;
                }
                
                if(this.options.import_structure){
                    let concept_code =  term[ idx_ccode ];
                    let local_trmID = $Db.getLocalID( 'trm', concept_code );
                    term.push( local_trmID );
                    hasFieldToImport = hasFieldToImport || !(local_trmID>0);
                }
                
                term.unshift(r_id);

                rdata.records[r_id] = term;
                rdata.order.push( r_id );
                
            }
        }
        rdata.count = rdata.order.length;
        
        
        if(this.options.import_structure){
            this.recordList.resultList('option', 'empty_remark',
                                        '<div style="padding:1em 0 1em 0">'+
                                        (hasFieldToImport
                                        ?this.options.entity.empty_remark
                                        :'Your database already has all the vocabularies available in this source'
                                        )+'</div>');
        }
        
        this._cachedRecordset = new HRecordSet(rdata);
        this.recordList.resultList('updateResultSet', this._cachedRecordset);
        
        return this._cachedRecordset;
    }

});

/**
* Correction of invalid term in record details OR addition missed term to vocabulry
*/
function correctionOfInvalidTerm(trm_ID, wrong_vocab_id, correct_vocab_id,  dty_ID, callback){
    
    let $dlg, buttons = [
        {text:window.hWin.HR('Cancel'),
            //class:'btnRecCancel',
            css:{'float':'right',margin:'.5em .4em .5em 0px'},  
            click: function() { $dlg.dialog( "close" ); }},
        {text:window.hWin.HR('Apply'),
            css:{'float':'right',margin:'.5em .4em .5em 0px'},  
            class: 'ui-button-action',
            click: function() { 
                
                let mode = $dlg.find('input[name="corr_mode"]:checked').val();
                //move,ref,use
                if(mode=='ref'){
                    // add new term by reference into correct vocabulary 
                    // no need change recDetails
                    $Db.setTermReferences(trm_ID, correct_vocab_id, 0,0,0, callback);    
                }else if(mode=='move'){
                    // move term
                    
                    let request = {
                        'a'          : 'save',
                        'entity'     : 'defTerms',
                        'request_id' : window.hWin.HEURIST4.util.random(),
                        'fields'     : {trm_ID:trm_ID, trm_ParentTermID:correct_vocab_id},
                        'isfull'     : 0
                        };
                        window.hWin.HAPI4.EntityMgr.doRequest(request, 
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    let old_parent_id = $Db.trm(trm_ID, 'trm_ParentTermID'); 
                                    //update on client side
                                    $Db.changeParentInIndex(correct_vocab_id, trm_ID, old_parent_id);
                                    $Db.trm(trm_ID, 'trm_ParentTermID',correct_vocab_id);
                                    if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call(trm_ID);
                                }else{
                                    onTermSaveError(response)
                                }
                            });
                    
                }
                
                
    
                $dlg.dialog( "close" ); 
            }}
    ];                
    
    //open dialog
    $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
        +"hclient/widgets/entity/popups/manageDefTermsCorrect.html?t="+(new Date().getTime()), 
        buttons, 'Correction of invalid term', 
        {  container:'terms-merge-popup',
            width:500,
            height:280,
            close: function(){
                $dlg.dialog('destroy');       
                $dlg.remove();
            },
            open: function(){
                //fill element
                $dlg.find('#termName').html($Db.trm(trm_ID,'trm_Label'));
                $dlg.find('#vocabName').html($Db.trm(wrong_vocab_id,'trm_Label'));
                $dlg.find('#vocabNameCorrect').html($Db.trm(correct_vocab_id,'trm_Label'));
            }
        });
    
    
    
}

//
//
//
function showWarningAboutTermUsage(recID, refs){
    let sList = '';
    for(let i=0; i<refs.length; i++) if(refs[i]>0){
        sList += ('<a href="#" data-dty_ID="'+refs[i]+'">'+$Db.dty(refs[i],'dty_Name')+'</a><br>');
    }

    let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
        '<p>Vocabulary <b>'+$Db.trm(recID,'trm_Label')+'</b> is referenced by the following fields:</p>'
        + sList
        +'<p>Please remove these fields altogether, or click the links above <br>to modify base field (will affect all record types which use it).</p>'
        , null, {title:'Warning'},
        {default_palette_class: 'ui-heurist-design'});        

    $dlg.find('a[data-dty_ID]').on('click', function(e){

        let rg_options = {
            isdialog: true, 
            edit_mode: 'editonly',
            select_mode: 'manager',
            rec_ID: $(e.target).attr('data-dty_ID'),
            onSelect:function(res){
            }
        };
        window.hWin.HEURIST4.ui.showEntityDialog('defDetailTypes', rg_options);
        return false;                    
    });
}

function onTermSaveError(response){
  
        if(response.sysmsg && response.sysmsg.reccount){

            //children detailtypes reccount records
            let res = response.sysmsg;    
            const recID = response.sysmsg.recID;

            if(window.hWin.HEURIST4.util.isArrayNotEmpty(res.detailtypes)){
                showWarningAboutTermUsage( recID, res.detailtypes );                                  
                return;
            }

            const is_vocab = !($Db.trm(recID, 'trm_ParentTermID')>0);
            let s = '';
            if(res['fields']){
                $.each(res['fields'],function(i,dty_ID){
                    s = s + $Db.dty(dty_ID,'dty_Name'); 
                });
                s = ' in fields ('+s+')';
            }
            

            let sMsg = '<p>'+(res.children==0?'Term':('Terms in '+(is_vocab?'Vocabulary':'Branch'))) 
            + ' <b>'+$Db.trm(recID, 'trm_Label') + '</b> ' 
            + (res.children==0?'is':'are') +  ' in use'+s
            + ' by '+res.reccount+' record'+(res.reccount>1?'s':'')+' in the database.</p>'

            +'<p>Before you can move or delete the '
            +(res.children==0?'term':(is_vocab?'vocabulary':'branch')+' and its child terms')
            +', you will need to delete the records which use '+(res.children==0?'this term':'these terms')
            +', or delete the values from the records.</p>';

            if(window.hWin.HEURIST4.util.isArrayNotEmpty(res.records)){
                sMsg += '<p><a href="#" class="records-list"'
                +'>List of '+response.sysmsg.reccount+' records which use '+(res.children==0?'this term':'these terms')+'</a></p>';
            }
            let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(sMsg, null, {title:'Terms in use'},
                {default_palette_class: 'ui-heurist-design'});        

            let url = window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database + '&w=a&q=ids:' + res.records.join(',') + '&nometadatadisplay=true';
            $dlg.find('a.records-list').attr('href', url);
            $dlg.find('a.records-list').on({click:function(e){
                $dlg.dialog('close');
            }});

        }else{
            window.hWin.HEURIST4.msg.showMsgErr(response);    
        }
    
}


