/**
 * lookupMPCE.js
 *
 *  1) This file loads html content from lookupMPCE.html
 *  2) This file outlines the functionality of the MPCE toolkit, including:
 *      - Assigning New Keywords to a Super Book (Work) record
 *      - Looking up a list of keywords that have been assigned with a selected keyword in other works
 *      - Display a list of keywords assigned to previously viewed works
 *
 *
 *
 * @package     Heurist academic knowledge management system
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Brandon McKay   <blmckay13@gmail.com>
 * @author      Martin Yldh   <martinsami@yahoo.com>
 * @author      Staphanson Hudson   <staphanson98@hotmail.com>
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @version     5.0
 */

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget( "heurist.lookupMPCE", $.heurist.lookupBase, {

    // default options
    options: {
        // Setting popup size, based on user preference
        height: (window.hWin.HAPI4.get_prefs_def('pref_lookupMPCE_h', 780)),
        width:  (window.hWin.HAPI4.get_prefs_def('pref_lookupMPCE_w', 1200)),

        title:  "Super Book (Work) Classification Tool for MPCE Project",

        htmlContent: "lookupMPCE.html"
    },

    action_button_label: 'Update Record',

    id_map: {   // ID Map for Record Types, Detail Types, and Vocabulary. Assigned in mapIds
        RT_Editions: null,
        RT_Work: null,
        RT_Keyword: null,

        DT_MPCEId: null,
        DT_Category: null,
        DT_Keywords: null,
        DT_Basis: null,
        DT_Notes: null,

        VI_Category: null,
        VI_Basis: null
    },

    // Main Variables
    project_keywords: null, // array of keyword ids
    parisian_category: null,    // id of selected parisian category
    basis_for_classification: null, // id of selected basis for classification
    classification_notes: null, // entry for classification notes

    full_keywords_list: null, // object of all keywords

    // Associated Keyword Variables
    assoc_search_id: null,  // current keyword of interest
    assoc_keywords: null,   // the keywords in association with the selected keyword
    assoc_work_count: null, // the number of works that contain the selected keyword
    assoc_startindex: null, // the index of the current start of list 
    assoc_endindex: null,   // the index of the current end of list
    assoc_selected: [],   // array of selected keyword ids from the associated keyword list

    // Previously Assigned Keywords Variables
    prev_works: null,       // ids of the last 5 previous works
    prev_keywords: null,    // object of keywords assigned to previous works

    // Editions Variables
    editions_info: null,    // basic information about editions for this work

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        let that = this;

        this.mapIds(this.options.mapping.fields, this.options.mapping.rty_ID); // Set the session variable 'id_map' to be an object of all record, detail and vocab type ids to simplify the replacement task

        let record = this.options.edit_fields;  // Retrieve Edit Fields

        /** Check if selected Work has: project keyword/s, parisian category, and a basis for classification **/

        this.project_keywords = !window.hWin.HEURIST4.util.isempty(record[this.id_map.DT_Keywords]) ? record[this.id_map.DT_Keywords] : null; // Project Keywords

        this.parisian_category = !window.hWin.HEURIST4.util.isempty(record[this.id_map.DT_Category]) ? record[this.id_map.DT_Category] : null; // Parisian Category

        this.basis_for_classification = !window.hWin.HEURIST4.util.isempty(record[this.id_map.DT_Basis]) ? record[this.id_map.DT_Basis] : null; // Basis for Classification

        this.classification_notes = !window.hWin.HEURIST4.util.isempty(record[this.id_map.DT_Notes]) ? record[this.id_map.DT_Notes] : null; // Classification Notes
        $('#notes_field').val(record[this.id_map.DT_Notes]);

        this.prev_works = !window.hWin.HEURIST4.util.isempty(localStorage.getItem("prev_classify")) 
                            ? window.hWin.HEURIST4.util.isJSON(localStorage.getItem("prev_classify"))
                            : null;

        $('#title_field').text(record[1]); // Work Title
        $('#work-code_field').text(record[this.id_map.DT_MPCEId]);   // Work MPCE_ID

        // Retrieve all Parisian Keywords (Term ID = 6380)
        if(this.id_map.VI_Category > 0 && $Db.trm(this.id_map.VI_Category)){

            window.hWin.HEURIST4.ui.createTermSelect( this.element.find('#category_field')[0], {
                    vocab_id: [this.id_map.VI_Category],    // Vocabulary ID/Term ID
                    defaultTermID: this.parisian_category,   //Default/Selected Term
                    topOptions: [{title:"Select a Parisian Classification...", key:''}],      //Top Options  [{key:0, title:'...select me...'},....]
                    useHtmlSelect: false     // use native select of jquery select
                }
            );
        }else{
            this.element.find('#category_field').append('<option>No categories</option>').hSelect();
        }

        // Retrieve all Basis for Classification (Term ID = 6498)
        if(this.id_map.VI_Basis > 0 && $Db.trm(this.id_map.VI_Basis)){

            window.hWin.HEURIST4.ui.createTermSelect( this.element.find('#basis_field')[0], {
                    vocab_id: [this.id_map.VI_Basis],    // Vocabulary ID/Term ID
                    defaultTermID: this.basis_for_classification,   // Default/Selected Term
                    useHtmlSelect: false    // use native select of jquery select
                }
            ); 
        }else{
            this.element.find('#basis_field').append('<option>No basis</option>').hSelect();
        }

        this.getKeywords('assigned');
        
        // Add msg next to save button
        this.element.parents('.ui-dialog').find('.btnDoAction').before('<span id="save-msg" style="display:none;font-size:1.2em;">Add or Uncheck Selections</span>');        

        // NEXT >> handler
        this._on($('#assoc_next'), {
            click: function(){
                $('#checkall-assoc').attr('checked', false);
    
                that.updateAssocDisplay(false);
            }
        });

        // << BACK handler
        this._on($('#assoc_prev'), {
            click: function(){

                let jump = that.assoc_endindex - that.assoc_startindex;

                if(jump < 13) { jump = 13; }

                that.assoc_startindex = that.assoc_startindex - jump;
                that.assoc_endindex = that.assoc_startindex;
                that.assoc_startindex = that.assoc_startindex - (jump * 2);

                that.assoc_startindex < 0 && that.assoc_endindex < 13 ? that.updateAssocDisplay(true) : that.updateAssocDisplay(false);

                $('#checkall-assoc').attr('checked', false);            
            }
        });


        // onClick Handlers

        // Assigning Keywords to Work
        this._on(this.element.find('#btnLookup').button(),{
            click: this.keywordLookup
        });

        this._on(this.element.find('#btnPrevAssign').button(),{
            click: this.addPrevtoAssigned
        });

        this._on(this.element.find('#btnAssocAssign').button(),{
            click: this.addAssoctoAssigned
        });

        this._on(this.element.find('#btnAssocRemove').button(),{
            click: this.unselectAssoc
        });

        // External Searches for Work Title
        this._on(this.element.find('#btnGoogle').button(),{
            click: this.lookupGoogle
        });
        this._on(this.element.find('#btnWorldCat').button(),{
            click: this.lookupWorldCat
        });
        this._on(this.element.find('#btnHathiTrust').button(),{
            click: this.lookupHathiTrust
        });
        this._on(this.element.find('#btnKarlsruhePortal').button(),{
            click: this.lookupKarlsruhePortal
        });

        // Other
        this._on(this.element.find('#btnEdition').button(),{
            click: this.lookupEditions
        });

        this._on(this.element.find('#checkall-prev'),{
            click: function(e){
                let check_status = $(e.target).is(':checked');
                that.checkAllOptions($('#prev_field')[0], check_status);
            }
        });

        this._on(this.element.find('#checkall-assoc'),{
            click: function(e){
                let check_status = $(e.target).is(':checked');
                that.checkAllOptions($('#associated_field')[0], check_status, true);
            }
        });

        // Disable the 'X' button, located top-right corner
        this.element.dialog('widget').find('.ui-dialog-titlebar-close').button().hide();

        // Detects the popup being resized, disable the mouseup as resize fires constantly
        this.element.parent().on('onresize',function() {
            that.element.parent().off("mouseup");

            that.element.parent().one("mouseup", function(){
                let width = that.element.parent().width();
                let height = that.element.parent().height();

                window.hWin.HAPI4.save_pref('pref_lookupMPCE_w', width);
                window.hWin.HAPI4.save_pref('pref_lookupMPCE_h', height);
            });
        });

        return this._super();
    },

    /*
        Update Record with selected Classifications and move back to Record Editor popup

        Param: None

        Return: VOID, closes classification tool
     */
    doAction: function(){

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent()); // use this function to show "in progress" animation and cover-all screen for long operation

        // assign values to be sent back to edit form - format is similar to this.options.edit_fields
        let res = {};
        res[this.id_map.DT_Category] = $('#category_field').val();
        res[this.id_map.DT_Basis] = $('#basis_field').val();
        res[this.id_map.DT_Notes] = $('#notes_field').val();
        res[this.id_map.DT_Keywords] = (this.project_keywords.length == 0) ? null : this.project_keywords;

        let idxRecent = (this.prev_works == null) ? -1 : this.prev_works.findIndex(id => id == this.options.edit_fields.rec_ID[0]);
        let hasKeywords = !window.hWin.HEURIST4.util.isempty(this.project_keywords);

        // Check if work can be added to list of recently viewed works
        if(idxRecent == -1 && hasKeywords){

            this.prev_works.unshift(this.options.edit_fields.rec_ID[0]);

            if(this.prev_works.length == 5){
                this.prev_works.splice(4, 1);
            }

            localStorage.setItem("prev_classify", JSON.stringify(this.prev_works));
        }else if(idxRecent != -1 && !hasKeywords){
            this.prev_works.splice(idxRecent, 1);
            localStorage.setItem("prev_classify", JSON.stringify(this.prev_works));
        }

        this.closingAction(res, false);
    },

    /** Keyword Assignment **/

    /*
        Creates a Heurist popup capable of searching all Project Keywords,
        When an keyword is selected, it is assigned to the work, if it is not already,
        this keyword is removed from the associated and previous (recent) keyword lists

        Param: None

        Return: VOID
     */
    keywordLookup: function(){

        let that = this;

        let popup_options = {
            select_mode: 'select_single', //(false)?'select_multi': enable multi select or not, current set to single select
            select_return_mode: 'recordset', //or 'ids' (default)
            edit_mode: 'popup',
            selectOnSave: true, // true = select popup will be closed after add/edit is completed
            title: 'Assign a New Keyword',
            rectype_set: [this.id_map.RT_Keyword], // record type ID
            pointer_mode: 'browseonly', // options = both, addonly or browseonly
            pointer_filter: null, // initial filter on record lookup, default = none
            parententity: 0,

            width: 700,
            height: 600,

            // onselect Handler for pop-up
            onselect: function(event, data){

                if(!window.hWin.HEURIST4.util.isRecordSet(data.selection)){
                    return;
                }

                let recordset = data.selection;
                let record = recordset.getFirstRecord();

                if(window.hWin.HEURIST4.util.isempty(record)){
                    // something is wrong with the record

                    msgToConsole('keywordLookup() Error: Selected Record is Invalid', record, 1);
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: 'The selected keyword is invalid',
                        error_title: 'Invalid selection',
                        status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                    });

                    return;
                }

                let targetID = recordset.fld(record,'rec_ID');
                
                let keyword_IDs = that.project_keywords;

                if(!window.hWin.HEURIST4.util.isempty(keyword_IDs)){

                    let result = keyword_IDs.find(row => row == targetID);
                
                    if(result != null){ // Check if Keyword is already a part of Work's Keyword Master List
                        window.hWin.HEURIST4.msg.showMsgDlg('Project Keyword Already Allocated to Work', null, 'Keyword already assigned');

                        return;
                    }
                }

                if(that.full_keywords_list == null){
                    that.getKeywords('add', targetID);
                    return;
                }

                let title = `Record ID - ${targetID}`;
                if(that.full_keywords_list[targetID] !== undefined){
                    title = that.full_keywords_list[targetID];
                }
                that.addKeyword(targetID, title); // Add Selected Keyword to Master Table+List
            }
        };

        window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);
    },

    /*
        Retrieve the list of checked keywords that need to be moved to the assigned keyword list

        Param: None

        Return: VOID, assignment of selected keyword
     */
    addPrevtoAssigned: function(){

        let list = $('#prev_field')[0];

        let items = this.getAllChecked(list);

        if(items == null || items.length == 0){
            window.hWin.HEURIST4.msg.showMsgDlg('There are No Recent Keywords Selected to Assign', null, 'No recent keywords');     
            return;
        }

        for(const id of items){

            list = $('#prev_field');

            let title = `Record ID - ${id}`;
            if(this.full_keywords_list[id] !== undefined){
                title = this.full_keywords_list[id];
            }

            // Remove from Previous Keyword Table
            list.find(`input#${id}_r`).parent().remove();

            this.addKeyword(id, title); // Add to Assigned Table + List
        } 

        this.disableUpdateBtn();
    },

    /*
        Retrieve the list of checked keywords that need to be moved to the assigned keyword list

        Param: None

        Return: VOID
     */
    addAssoctoAssigned: function(){

        let list = $('#associated_field')[0];

        let items = this.getAllChecked(list, true);

        if(this.assoc_selected){
            items = mergeArraysUnique(items, this.assoc_selected);
        }

        if(items == null || items.length == 0){
            window.hWin.HEURIST4.msg.showMsgDlg('There are No Associated Keywords Selected to Assign', null, 'No associated keywords');     
            return;
        }

        for(const id of items){

            list = $('#associated_field')[0];

            // Remove from Associated Keyword Table
            $(list).find(`input#${id}_a`).parent().remove();

            let title = `Record ID - ${id}`;
            if(this.full_keywords_list[id] !== undefined){
                title = this.full_keywords_list[id];
            }

            this.addKeyword(id, title); // Add to Assigned Table + List
        }

        this.assoc_selected = [];

        this.updateAssocDisplay(true);

        this.disableUpdateBtn();
    },

    /** External Searches **/

    /*
        Search for Editions of the Work within Heurist, displaying the results in a separate popup

        Param: None

        Return: VOID
     */
    lookupEditions: function(){

        let that = this;

        if(this.editions_info != null){
            this.showEditions();
            return;
        }

        this.editions_info = [];

        let query_request = {q: `t:${this.id_map.RT_Editions} linkedto:${this.options.edit_fields.rec_ID[0]}`, detail: 'detail', limit: 10};

        window.hWin.HAPI4.RecordMgr.search(query_request,
            function( response ){

                if(response.status != window.hWin.ResponseStatus.OK){
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    return;
                }
                if(window.hWin.HEURIST4.util.isempty(response.data)) { return; }

                let recordset = new HRecordSet(response.data);
                let records = recordset.getRecords();

                for(let i in records){

                    let record = records[i];

                    that.editions_info.push([record[5], record[2]]);
                }

                if(that.editions_info != null && that.editions_info.length > 0){
                    that.showEditions();
                }
            }
        );
    },


    /*
        Search for All Keywords

        Param: None

        Return: json -> all keywords, containing {id: 'keyword name', ...}
     */
    getKeywords: function(next_step='none', extra_ids = null){

        let that = this;

        if(this.full_keywords_list == null){
            this.full_keywords_list = {};
        }

        if(!this.id_map.RT_Keyword || !$Db.rty(this.id_map.RT_Keyword)){
            return;
        }

        // Retrieve master list of project keywords, we need to display their titles for the user
        let query_request = {q: `t:${this.id_map.RT_Keyword}`, detail: 'detail'};

        // Perform Search
        window.hWin.HAPI4.RecordMgr.search(query_request,
            function( response ){

                if(response.status != window.hWin.ResponseStatus.OK){
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }

                if(window.hWin.HEURIST4.util.isempty(response.data)) { return; }

                let recordset = new HRecordSet(response.data); // Retieve Search Results

                recordset.each2(function(id, record){
                    that.full_keywords_list[id] = record?.d[1] ? record.d[1] : record['rec_Title'];
                });

                if(extra_ids == null && (next_step == 'associated' || next_step == 'recent' || next_step == 'add')){
                    return;
                }

                this.getKeywordsNextStep(next_step, extra_ids);
            }
        );
    },

    getKeywordsNextStep: function(step, ids){
        let that = this;
        let title = null;
        switch(step){
            case 'assigned': // add assigned keywords to html list

                if(!window.hWin.HEURIST4.util.isempty(that.project_keywords)){

                    for(const id of that.project_keywords){

                        let title = `Record ID - ${id}`;
                        if(that.full_keywords_list[id] !== undefined){
                            title = that.full_keywords_list[id];
                        }

                        that.showKeyword(title, id); // Add to Keyword Table
                    }
                }

                that.setupRecentWorks(); // initialise recent keywords

                break;
            case 'associated': // retrieve associated keywords
                that.setupAssocKeywords(ids);
                break;
            case 'recent': // retrieve recent keywords
                that.getRecentKeywords(ids);
                break;
            case 'add': // add new assigned keyword

                title = that.full_keywords_list[ids] !== undefined ? `Record ID - ${ids}` : that.full_keywords_list[ids];
                that.addKeyword(ids, title);

                break;
            default:
                // Unknown/None, do nothing
                break;
        }
    },

    /*
        Search for Work Title in Google Books

        Param: None

        Return: (false), opens new tab for Google Books search
     */
    lookupGoogle: function(){
        let title = $($('#title_field')[0]).text();
        let url = `https://books.google.com.au/?q=${title}`;

        window.open(url, '_blank');

        return false;
    },

    /*
        Search for Work Title in World Cat

        Param: None

        Return: (false), opens new tab for World Cat search
     */
    lookupWorldCat: function(){
        let title = $($('#title_field')[0]).text();
        let url = `https://www.worldcat.org/search?q=ti%3A${title}`;

        window.open(url, '_blank');

        return false;
    },

    /*
        Search for Work Title in Hathi Trust

        Param: None

        Return: (false), opens new tab for Hathi Trust search
     */
    lookupHathiTrust: function(){
        let title = $($('#title_field')[0]).text();
        let url = `https://catalog.hathitrust.org/Search/Advanced?adv=1&lookfor%5B%5D=${title}`;

        window.open(url, '_blank');

        return false;
    },

    /*
        Search for Work Title in Karlsruhe Portal

        Param: None

        Return: (false), opens new tab for Karlsruhe Portal search form
     */
    lookupKarlsruhePortal: function(){
        let title = $($('#title_field')[0]).text();
        let url = `https://kvk.bibliothek.kit.edu/index.html?lang=en&digitalOnly=0&embedFulltitle=0&newTab=0&TI=${title}`;

        window.open(url, '_blank');

        return false;
    },

    /** Associated Keyword System **/

    /* 
        Searches for works that have the selected keyword,
        the program then goes through each returned work's keywords, 
        adding them to the associated keyword list, 
        finally displaying the result to the user.

        Param: id -> Keyword's Title Mask (or, the Keyword's ID)

        Return: VOID
     */
    setupAssocKeywords: function(id){

        let that = this;

        if(this.full_keywords_list == null){
            this.getKeywords('associated', id);
            return;
        }

        let list = $('#associated_field');

        this.assoc_search_id = id;
        this.assoc_keywords = [];
        this.assoc_selected = [];

        $('#assoc_prev').hide();
       
        $('#assoc_next').hide(); 

        list.html('<div style="font-size:1.5em">Loading List...</div>');

        let query_request = {q: `t:${this.id_map.RT_Work} linkedto: ${id}`, detail: 'detail'};  // Retrieve all works that have the selected keyword

        window.hWin.HAPI4.RecordMgr.search(query_request,
            function(response){

                if(response.status == window.hWin.ResponseStatus.OK){

                    if(window.hWin.HEURIST4.util.isempty(response.data)) { return; }

                    let recordset = new HRecordSet(response.data);
                    let records = recordset.getRecords();

                    let ids = [];

                    // For displaying the total number of works to user, i.e. (n = ...)
                    that.assoc_work_count = Object.keys(records).length;

                    // Travel through results to retrieve the each work's list of keywords
                    for(let i in records){

                        let record = records[i];

                        // To avoid results that are not from the correct table, usually happens if query is wrong
                        if (record[4] != that.id_map.RT_Work) { continue; }

                        let details = record.d;

                        if((details[that.id_map.DT_Keywords] == null) || (details[that.id_map.DT_Keywords].length < 2)){
                            continue;
                        }

                        ids.push(details[that.id_map.DT_Keywords]);
                    }

                    // Add the complete list retrieved to the associated keyword
                    that.updateAssocList(ids);
                } else {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            }
        );
    },
    
    /*
        Updates the Associate Keyword List (this.assoc_keywords)
        Performs a check if the current keyword is already part of the list, 
            if it is, increase occurances
            else, push into list
        The list is then sorted by occurrances
        Once completed, the corresponding table is updated

        Param: ids -> list of associated keywords

        Return: VOID
     */

    updateAssocList: function(ids){

        // Go through 2d array of keywords
        for(const ids of ids){

            for(const id of ids){

                let title = `Record ID - ${id}`;
                if(this.full_keywords_list[id] !== undefined){
                    title = this.full_keywords_list[id];
                }

                if(this.assoc_keywords.length < 1){ // don't need to check, if there are no associated keywords
                    this.assoc_keywords.push([id, 1, title]);
                    continue;
                }

                this.isAssocKeyword(id, title); // Check if current keyword is already in list
            }
        } 

        this.updateAssocDisplay(true); // Update UI display of associated keywords
    },
    
    /*
        Update the Associated Keywords List, UI, and display which kyeword was selected

        Param: move_to_start -> whether this is the next set of keywords, or starting from the start of list

        Return: VOID
     */
    updateAssocDisplay: function(move_to_start=true){

        let list = $('#associated_field');

        if(window.hWin.HEURIST4.util.isempty(this.assoc_keywords)){
            return;
        }

        let keywords = this.assoc_keywords;

        if(window.hWin.HEURIST4.util.isempty(this.project_keywords)){
            return;
        }

        keywords.sort(compareIndexes);

        this.assoc_startindex = list.find('li').length != 0 && !move_to_start ? this.assoc_endindex : 0;

        // Empty List, before use
        list.empty();

        let name = $('#keyword_field').find(`li#${this.assoc_search_id}_m`).find('label').text();
        $('#assoc_kywd').text(name);
        $('#assoc_total').text(`(n=${this.assoc_work_count})`);

        // Now add items into the HTML list
        this.assoc_endindex = this.renderAssocKeywods(keywords);

        //this.assoc_endindex = i;

        let jump = this.assoc_endindex - this.assoc_startindex;

        if(this.assoc_startindex-jump < 0 && this.assoc_endindex+13 > keywords.length && this.assoc_endindex <= keywords.length){
            $('#assoc_prev').hide();

            $('#assoc_next').hide();             
        }else if(this.assoc_startindex-jump < 0){ 
            $('#assoc_next').show();

            $('#assoc_prev').hide(); 
        }else if(this.assoc_endindex+jump > keywords.length && this.assoc_endindex == keywords.length){ 
            $('#assoc_prev').show();

            $('#assoc_next').hide(); 
        }else{
            $('#assoc_prev').show();

            $('#assoc_next').show(); 
        }

        this.disableUpdateBtn();
    },

    renderAssocKeywods: function(keywords){

        let list = $('#associated_field');

        let that = this;

        let max = this.assoc_startindex + 13;
        let assigned = this.project_keywords;

        let i = this.assoc_startindex;
        for(; i < max && keywords.length > i; i++){

            let item = $('<li>');

            if(keywords[i] == null){
                break; 
            }

            if(assigned.find(e => e == keywords[i][0])){
                max++;
                continue;
            }

            item.html(`<input type='checkbox' value='${keywords[i][0]}' style='vertical-align:middle;' id='${keywords[i][0]}_a'>`
                    + `<label for='${keywords[i][0]}_a' class='non-selectable key-label truncate' style='max-width:180px;vertical-align:middle;'`
                    + ` title="${keywords[i][2]}">${keywords[i][2]}</label>&nbsp;&nbsp;`
                    + `<button data-value='${keywords[i][0]}' class='btn btn-info ui-icon ui-icon-circle-b-info'`
                        + " style='float:right;font-size:1em;display:inline-block;width:20px;height:20px;color:white;' title='View keyword record'>&nbsp;"
                    +"</button>"
                    + `<label class='non-selectable' style='vertical-align:middle;float:right;margin-right:5px;'> [ ${keywords[i][1]} ] </label>`);

            if(this.assoc_selected && this.assoc_selected.length != 0 && this.assoc_selected.indexOf(keywords[i][0]) > -1){
                $(item).find('input').prop('checked', true);
            }

            list.append(item);
        }

        this._on(list.find('.btn-info.ui-icon-circle-b-info'), {
            click: (e) => {
                let rec_id = $(e.target).parent().find('input').attr('value');
                that.openRecordInTab(rec_id);
            }
        });
        this._on(list.find(`input[type="checkbox"]`), {
            click: (e) => {

                let id = $(e.target).val();
                this.handleAssocOption($(e.target).is(':checked'), id);

                that.disableUpdateBtn(); 
            }
        });

        return i;
    },
    
    /*
        Checks if keyword is already in arr,
            if it is, increase the counter
            else, push it in

        Param:
            arr -> arr of associated keywords
            id -> the current keyword
            title -> the keyword in english

        Return: VOID
     */
    isAssocKeyword: function(id, title){
        
        let found = 0;

        for(let assoc_keyword of this.assoc_keywords){

            if(assoc_keyword[0] == id){
                assoc_keyword[1] += 1;
                found = 1;
                break;
            }
        }

        if(found == 0){
            this.assoc_keywords.push([id, 1, title]);
        }
    },

    /*
        Unselects and removes all selected keywords from the associated keywords list

        Param: NONE

        Return: VOID
     */
    unselectAssoc: function(){

        this.assoc_selected = []; // Empty complete selection

        $('#checkall-assoc').attr('checked', false); // Remove any checked boxes in current view

        this.checkAllOptions($('#associated_field')[0], false); // Uncheck the 'Check all' option
    },
    
    /** Recent Keywords System **/

    /*
        Sets up the list of works and calls startup function for Recent Keywords,
        This will display a list of keywords from previously viewed works from within a session (life span of the tab),
        a list of 4 work titles is remembered and those four work's keywords are displayed to the UI.
        Keywords assigned to the current work are ignored, as are ones already displayed under the recent keyword section.

        Param: None

        Return: VOID
     */
    setupRecentWorks: async function(){

        this.prev_keywords = {};

        if (this.prev_works != null){ // Check if there are any previously viewed works

            let ch_set;

            // Which work will have it's keywords automatically set to checked, each work can only appear once in the list
            if (this.prev_works[0] != this.options.edit_fields.rec_ID[0]){
                ch_set = this.prev_works[0];
            }else if(this.prev_works.length != 1){
                ch_set = this.prev_works[1];
            }

            // Now retrieve and display the list
            for(const prev_work of this.prev_works){

                if(prev_work == this.options.edit_fields.rec_ID[0]) { continue; }

                // Additional information is sent, depending on whether the work's keywords are to be checked or not
                this.startRecentWork(prev_work, ch_set == prev_work);

                await sleep(50); // This is to allow the keywords to be displayed in the correct order, without this the default checked keywords can appear out of order
            }
        }else{
            this.prev_works = [];
        }

        // Check if current work can be added to the list of previously (recent) works
        if(this.prev_works.findIndex(id => id == this.options.edit_fields.rec_ID[0]) == -1 && !window.hWin.HEURIST4.util.isempty(this.project_keywords)){
            this.prev_works.unshift(this.options.edit_fields.rec_ID[0]);
        }

        if(this.prev_works.length == 5){ 
            this.prev_works.splice(4, 1);
        }

        localStorage.setItem("prev_classify", JSON.stringify(this.prev_works));
    },

    /*
        Retrieves the supplied work's keywords and add them to the displayed list

        Param: 
            work_code -> Work Code of Interest
            is_checked -> Bool whether to check new option

        Return: VOID
     */
    startRecentWork: function(id, is_checked=false){

        let that = this;   

        if(id == null){
            return;
        }

        let query_request = {q: `t:${this.id_map.RT_Work} ids:"${id}"`, detail: 'detail'}; // Retrieve the record for supplied work code

        window.hWin.HAPI4.RecordMgr.search(query_request,
            function( response ){
                if(response.status == window.hWin.ResponseStatus.OK){

                    if(window.hWin.HEURIST4.util.isempty(response.data)) { return; }

                    let recordset = new HRecordSet(response.data);
                    let record = recordset.getFirstRecord();
                    let details = record.d;

                    // Check if the record has project keywords to display
                    if (details[that.id_map.DT_Keywords] != null){
                        that.getRecentKeywords(details[that.id_map.DT_Keywords], is_checked);
                    }
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
            }
        );
    },

    /*
        From a list of keywords, retrieve their titles and display them to the user, or remove them if necessary

        Param: 
            keyword_IDs -> Array of Keyword IDs
            is_checked -> Bool whether to check new option

        Return: VOID
     */
    getRecentKeywords: function(keyword_IDs, is_checked=false){
        
        let that = this;

        if(this.full_keywords_list == null){
            this.getKeywords('recent', keyword_IDs);
            return;
        }

        for(const id of keyword_IDs){

            let title = `Record ID - ${id}`;
            if(this.full_keywords_list[id] !== undefined){
                title = this.full_keywords_list[id];
            }

            let found = $('#keyword_field').find(`li#${id}_m`); // Check if keyword is already assigned

            if(found.length == 0){

                found = $('#prev_field').find(`input#${id}_r`); // Check if keyword has been displayed as a recently used keyword

                if(found.length == 0){

                    that.updateRecentKeywordDisplay(id, title); // Add new keyword to recently used keyword list

                    this.prev_keywords[id] = title;

                    if(is_checked){
                        $('#prev_field').find(`input#${id}_r`).prop('checked', true);
                    }
                }
            }
        }
    },

    /*
        Add new keyword to recently used keyword list

        Param:
            id -> Keyword's Record ID
            title -> Keyword Title

        Return: VOID
     */
    updateRecentKeywordDisplay: function(id, title){

        let that = this;

        let list = $('#prev_field');
        let item = $('<li>');
        let html = '';

        html = `<input type='checkbox' style='vertical-align:middle;' value='${id}' id='${id}_r'>`;

        html += `<label for='${id}_r' class='non-selectable key-label truncate' style='vertical-align:middle;' title="${title}"> ${title} </label>`
                + "<button class='btn btn-info ui-icon ui-icon-circle-b-info'"
                    + " style='float:right;font-size:1em;display:inline-block;width:20px;height:20px;color:white;' title='View keyword record'>&nbsp;"
                + "</button>";

        item.html(html);

        $(item).find(`#${id}`).on('click', function(e){ that.disableUpdateBtn(); });
        $(item).find('.btn-info').on('click', function(e){ that.openRecordInTab(id); });

        list.append(item);
    },

    /*
        Check if provided keyword is a displayed as a recently used keyword

        Param: id -> Keyword's ID

        Return: VOID
     */
    isRecentKeyword: function(id){

        if (this.prev_works == null || $.isEmptyObject(this.prev_keywords) || this.prev_keywords[id] == undefined){ // Check for a list of previous keywords
            return;
        }

        this.updateRecentKeywordDisplay(id, this.prev_keywords[id]);
    },

    /** Assigned Keyword System **/

    /*
        Add Newly Assigned Keyword to Table UI

        Param: 
            keyword -> Keyword's Title
            id -> Keyword's Record ID

        Return: VOID
     */
    showKeyword: function(title, id){

        let that = this;

        let list = $('#keyword_field');

        let item = $(`<li id="${id}_m">`);

        item.html(`<label class='key-label truncate' title="${title}">${title}</label>`

        + `<button data-value='${id}' class='btn btn-info ui-icon ui-icon-circle-b-info'`
        + " style='float:right;font-size:1em;display:inline-block;width:20px;height:20px;color:white;' title='View keyword record'>&nbsp;</button>"

        + `<button data-value='${id}' class='btn btn-info btn-lookup'`
        + " style='float:right;margin-right:5px;font-size:0.75em;display:inline-block;height:20px;' title='Search for associated keywords'>Find Associated</button>"
        
        + `<button data-value='${id}' class='btn btn-delete ui-icon ui-icon-close'`
        + " style='float:right;margin-right:5px;font-size:1.1em;display:inline-block;width:20px;height:20px;' title='Remove keyword from list'>&nbsp;</button>");

        $(item).find('.btn-info.ui-icon-circle-b-info').on('click', function(e){ that.openRecordInTab(id); });
        $(item).find('.btn-lookup').on('click', function(e){ that.setupAssocKeywords(id); });
        $(item).find('.btn-delete').on('click', function(e){ that.removeKeyword($(e.target), id); });

        list.append(item);
    },

    /*
        Add new Keyword to Assigned Keywords List

        Param:
            title -> Keyword Title
            id -> Keyword's Record ID

        Return: VOID
     */
    addKeyword: function(id, title){

        /* Remove from Associated List */
        if($('#associated_field').find(`input#${id}_a`).parent().length > 0){
            $('#associated_field').find(`input#${id}_a`).parent().remove();
        }
        /* Remove from Recent List */
        if($('#prev_field').find(`input#${id}_r`).parent().length > 0){
            $('#prev_field').find(`input#${id}_r`).parent().remove();
        }

        if($('#keyword_field').find(`li#${id}_m`).length == 0){

            this.showKeyword(title, id); // Add to Table

            if(window.hWin.HEURIST4.util.isempty(this.project_keywords)){
                this.project_keywords = [];
            }

            this.project_keywords.push(id); // Add to List
        }
    },

    /*
        Remove selected keyword from Assigned Keyword Table and List

        Param: id -> Selected Keyword's Record ID

        Return: VOID
     */
    removeKeyword: function($ele, id){

        $ele.parent().remove(); // Remove keyword from list

        // Remove keywords from saved list
        let index = this.project_keywords.indexOf(id);
        if(index >= 0){
            this.project_keywords.splice(index, 1);
        }
        
        this.isRecentKeyword(id); // Check if keyword was a recent keyword
        this.updateAssocDisplay(); // Check if keyword was a associated keyword
    },

    /*
        Display all found editions for the selected work

        Param: works -> List of Edition's Title Masks

        Return: VOID
     */
    showEditions: function(){

        let editions = '';
        let title = 'List of Editions';

        for(const cur_edition of this.editions_info){
            let rec_url = `${window.hWin.HAPI4.baseURL}viewers/record/renderRecordData.php?db=${window.hWin.HAPI4.database}&recID=${cur_edition[1]}`;
            editions = editions.concat(`<div style='font-size: 1.2em;'>${cur_edition[0]} - <a href='${rec_url}' target='_blank' rel='noopener'> view record </a></div>`, "<br><br>");
        }

        if(editions == ''){
            editions = 'No Editions Found';
            title = 'No editions';
        }

        window.hWin.HEURIST4.msg.showMsgDlg(editions, null, title, {default_palette_class: 'ui-heurist-explore'});
    },

    /** Other Function **/

    /*
        Determines which keywords have been checked

        Param: list -> document element, unordered list

        Return: Array -> list of checked keywords
     */
    getAllChecked: function(list){

        let checked_items = [];

        let items = $(list).find('li');

        for(const item of items){

            let chkbox = $(item).find('input');

            if(chkbox.length == 1 && chkbox.is(':checked')){
                checked_items.push(chkbox.val());
            }
        }

        return checked_items;
    },

    /*
        Disable the Update Record button when a checkbox is checked, displaying message to uncheck or add selections

        Param: NONE

        Return: VOID
    */
    disableUpdateBtn: function(){

        if($('.mpce').find('input:checked').not('.check-all').length > 0 || this.assoc_selected.length > 0){
            $($('.mpce')[0].parentNode.parentNode).find('.btnDoAction').attr('disabled', true).css({'cursor': 'default', 'opacity': '0.5'});
            $($('.mpce')[0].parentNode.parentNode).find('#save-msg').css({'margin':'10px', 'display':'inline-block'});
        }
        else{
            $($('.mpce')[0].parentNode.parentNode).find('.btnDoAction').attr('disabled', false).css({'cursor': 'default', 'opacity': '1'});
            $($('.mpce')[0].parentNode.parentNode).find('#save-msg').css({'margin':'10px', 'display':'none'});
        }
    },

    /*
        Toggles the checkboxes based on whether the "Check All" options is set

        Param: 
            list -> list of interest
            isChecked -> whether we are checking all boxes or not
            isAssoc -> is this for the associated keywords, needs extra handling

        Return: VOID
    */
    checkAllOptions: function(list, isChecked, isAssoc=false){

        let items = $(list).find('li');

        for(const item of items){

            let chkbox = $(item).find('input');

            if(chkbox.length == 0){
                continue;
            }

            chkbox.prop('checked', isChecked);

            if(!isAssoc){
                continue;
            }

            this.handleAssocOption(isChecked, chkbox.val());
        }

        this.disableUpdateBtn();
    },

    handleAssocOption: function(is_checked, id){

        if(is_checked){
            this.assoc_selected.push(id);
        }else if(!is_checked){
            if(this.assoc_selected.length == 0){ return; }

            let index = this.assoc_selected.indexOf(id);
            if(index > -1){
                this.assoc_selected.splice(index, 1);
            }
        }
    },

    /*
        Open the provided record in a separate tab

        Param: id -> record id

        Return: VOID
     */
    openRecordInTab: function(id){

        let rec_url = `${window.hWin.HAPI4.baseURL}viewers/record/renderRecordData.php?db=${window.hWin.HAPI4.database}&recID=${id}`;

        window.open(rec_url, "_blank");
    },

    /*
        Mapping Ids for Detail and Record Types (Heurist Specific)

        Param: mapping -> mapped options

        Return: VOID
     */
    mapIds: function(mapping, main_rty_id){

        // Detail Type, Record Type and Vocab Id Map, for now you need to replace all instances of each value with the database correct one
        this.id_map.DT_MPCEId = (mapping['workID'] != null) ? mapping['workID'] : 952; // MPCE ID Details Index
        this.id_map.DT_Category = (mapping['parisianClassify'] != null) ? mapping['parisianClassify'] : 1060; // Parisian Category Details Index
        this.id_map.DT_Keywords = (mapping['projectKywds'] != null) ? mapping['projectKywds'] : 955; // Project Keywords Details Index
        this.id_map.DT_Basis = (mapping['basisClassify'] != null) ? mapping['basisClassify'] : 1034; // Basis for Classifcation Details Index
        this.id_map.DT_Notes = (mapping['classifyNotes'] != null) ? mapping['classifyNotes'] : 1035; // Classification Notes Details Index

        let dty_keywords = $Db.dty(this.id_map.DT_Keywords);
        let rty_keywords = (dty_keywords['dty_PtrTargetRectypeIDs'] != null) ? dty_keywords['dty_PtrTargetRectypeIDs'] : 56;

        this.id_map.RT_Editions = 54; // Editions Table 
        this.id_map.RT_Work = (main_rty_id != null) ? main_rty_id : 55; // Super Book (Works) Table
        this.id_map.RT_Keyword = rty_keywords; // Project Keywords Table

        let dty_category = $Db.dty(this.id_map.DT_Category);
        let trm_category = (dty_category['dty_JsonTermIDTree'] != null) ? dty_category['dty_JsonTermIDTree'] : 6953;
        let dty_basis = $Db.dty(this.id_map.DT_Basis);
        let trm_basis = (dty_basis['dty_JsonTermIDTree'] != null) ? dty_basis['dty_JsonTermIDTree'] : 6936;

        this.id_map.VI_Category = trm_category; // Parisian Category Vocab ID
        this.id_map.VI_Basis = trm_basis; // Basis for Classification Vocab ID
    }
});

/** Misc Function **/

/*
    Comparison Function for the arr.sort() function for the 2D of associated keywords
    Note: arr[n][1] == the number of occurrances

    Param:
        a -> Array Index 1
        b -> Array Index 2

    Return: Boolean -> whether the second element within index A is larger than B
 */
function compareIndexes(a, b){

    if(a[1] == b[1]){
        return 0;
    }

    return (a[1] > b[1]) ? -1 : 1;
}

/*
    Merge two arrays, keeping only unqiue values

    Param:
        a -> base array
        b -> array to merge to 'a'

    Return: Array -> Merged array

 */
function mergeArraysUnique(a, b){
    
    if(!a && !Array.isArray(a)){
        msgToConsole('mergeArraysUnique() Error: arguement \'a\' is not an array', a, 1);
    }

    if(!b && !Array.isArray(b)){
        msgToConsole('mergeArraysUnique() Error: arguement \'b\' is not an array', b, 1);
    }

    if(a.length == 0){
        return b;
    }

    for(const value of b){
        if(a.indexOf(value) == -1){
            a.push(value);
        }
    }

    return a;
}

/*
    Message/Data pair for printing to the console

    Param:
        msg -> primary message to console log
        data -> can be null, additional information
        type -> type of message 0 = warn, 1 = error

    Return: VOID
 */
function msgToConsole(msg, data, type=0){

    type == 0 ? console.warn(msg) : console.error(msg);

    if(data != null){
        console.info(data);
    }
}

/*
    Sleep Function

    Param: ms -> time to wait, in mmilliseconds

    Return: VOID
 */
function sleep(ms){
    return new Promise(r => setTimeout(r, ms));
}