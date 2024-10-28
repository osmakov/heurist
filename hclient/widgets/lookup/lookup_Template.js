/**
* lookup_Template.js - Lookup values in third-party web service for Heurist record 
* 
*   It consists of search form and result list to select one or several values of record
* 
*       descendants of this widget 
*   1) perform search on external third-part web service (see manageRecords.js _setupExternalLookups)
*   2) render result in our resultList (custom record renderer, found at hclient/widget/viewers/resultList.js)
*   3) map external results with our field details (see options.mapping)
*   4) either returns these mapped fields (to edit record form) 
*       or trigger addition of new record with selected values
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Brandon McKay   <blmckay13@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget( "heurist.lookup_Template", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 520,
        width:  800,
        modal:  true,
        
        title:  "Template lookup",
        
        htmlContent: 'lookup_Template.html',

        mapping: null, //configuration from record_lookup_config.json
               
        add_new_record: false, //if true it creates new record on selection
        
        pagesize: 20 // result list's number of records per page
    },
    
    recordList:null,

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        //this.element => dialog inner content
        //this._as_dialog => dialog container

        let that = this;

        // Extra field styling
        this.element.find('#search_container > div > div > .header.recommended').css({width:'100px', 'min-width':'100px', display: 'inline-block'});
        this.element.find('#btn_container').position({my: 'left top', at: 'right top', of: '#search_container'});
        // Action button styling
        this.element.find('#btnStartSearch').addClass("ui-button-action");

        // Prepare result list options
        this.options.resultList = $.extend(this.options.resultList, 
        {
               recordDivEvenClass: 'recordDiv_blue',
               eventbased: false,  //do not listent global events

               multiselect: false, // allow only one record to be selected
               select_mode: 'select_single', // only accept one record for selection

               selectbutton_label: 'select!!', // not used

               view_mode: 'list', // result list viewing mode [list, icon, thumb]
               show_viewmode:false,
               
               entityName: this._entityName,
               
               pagesize: this.options.pagesize, // number of records to display per page, 
               empty_remark: '<div style="padding:1em 0 1em 0">No records match the search</div>', // For empty results
               renderer: this._rendererResultList // Record render function, is called on resultList updateResultSet
        });                

        // Init record list
        this.recordList = this.element.find('#div_result');
        this.recordList.resultList( this.options.resultList );
        this.recordList.resultList('option', 'pagesize', this.options.pagesize); // so the pagesize doesn't get set to a different value

        // Init select & double click events for result list
        this._on( this.recordList, {        
            "resultlistonselect": function(event, selected_recs){
                window.hWin.HEURIST4.util.setDisabled( 
                    this.element.parents('.ui-dialog').find('.btnDoAction'), 
                    (selected_recs && selected_recs.length()!=1));
            },
            "resultlistondblclick": function(event, selected_recs){
                if(selected_recs && selected_recs.length()==1){
                    this.doAction();                                
                }
            }
        });        

        // Handling for 'Search' button        
        this._on(this.element.find('#btnStartSearch').button(),{
            'click':this._doSearch
        });

        // For capturing the 'Enter' key while typing
        this._on(this.element.find('input'),{
            'keypress':this.startSearchOnEnterPress
        });

        return this._super();
    },
    
    /**
     * Function handler for pressing the enter button while focused on input element
     * 
     * Param:
     *  e (event trigger)
     */
    startSearchOnEnterPress: function(e){
        
        let code = (e.keyCode ? e.keyCode : e.which);
        if (code == 13) {
            window.hWin.HEURIST4.util.stopEvent(e);
            e.preventDefault();
            this._doSearch();
        }

    },
    
    /**
     * Result list rendering function called for each record
     * 
     * Param:
     *  recordset (HRecordSet) => Heurist Record Set
     *  record (json) => Current Record being rendered
     * 
     * Return: html
     */
    _rendererResultList: function(recordset, record){

        /**
         * Get field details for displaying
         * 
         * Param:
         *  fldname (string) => mapping field name
         *  width (int) => width for field
         * 
         * Return: html
         */
        function fld(fldname, width){

            let s = recordset.fld(record, fldname);

            if(fldname == 'author'){ // special handling for author details

                if(!s){ 
                    return '<div style="display:inline-block;width:'+width+'ex" class="truncate"">No provided creator</div>';
                }

                let creator_val = '';

                for(let idx in s){

                    let cur_string = '';
                    let cur_obj = s[idx];

                    if(Object.hasOwn(cur_obj,'firstname') && cur_obj['firstname'] != ''){
                        cur_string = cur_obj['firstname'];
                    }
                    if(Object.hasOwn(cur_obj,'surname') && cur_obj['surname'] != ''){
                        cur_string = (cur_string != '') ? cur_obj['surname'] + ', ' + cur_string : cur_obj['surname'];
                    }
                    if(Object.hasOwn(cur_obj,'active') && cur_obj['active'] != ''){
                        cur_string += ' (' + cur_obj['active'] + ')';
                    }
                    if(!cur_string){
                        creator_val += 'Missing author; ';
                    }else{
                        creator_val += cur_string + '; ';
                    }
                }

                s = creator_val;
            }else if(Array.isArray(s)){
                s = window.hWin.HEURIST4.util.htmlEscape(s.join('; '));
            }else if(window.hWin.HEURIST4.util.isObject(s)){

            	let display_val = '';

            	for(let key in s){

                    if(display_val != ''){
                        display_val += ', ';
                    }
                    display_val += s[key];
                }

                s = display_val;
            }else{
                s = window.hWin.HEURIST4.util.htmlEscape(s?s:'');
            }

            let title = s;

            if(fldname == 'biburl'){ // create anchor tag for link to external record
                s = '<a href="' + s + '" target="_blank"> view here </a>';
                title = 'View bibliographic record';
            }
            
            if(width>0){
                s = '<div style="display:inline-block;width:'+width+'ex" class="truncate" title="'+title+'">'+s+'</div>';
            }
            return s;
        }

        // Generic details, not completely necessary
        let recID = fld('rec_ID');
        let rectypeID = fld('rec_RecTypeID');
        let recIcon = window.hWin.HAPI4.iconBaseURL + rectypeID;
        let html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;' + window.hWin.HAPI4.iconBaseURL + rectypeID + '&version=thumb&quot;);"></div>';

        let recTitle = fld('author', 50) + fld('date', 7) + fld('title', 75) + fld('biburl', 12); 

        let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" rectype="'+rectypeID+'">'
            + html_thumb
                + '<div class="recordIcons">'
                +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
                +     '" class="rt-icon" style="background-image: url(&quot;'+recIcon+'&quot;);"/>' 
                + '</div>'
                +  recTitle
            + '</div>';
        return html;
    },

    /**
     * Initial dialog buttons on bottom bar, _getActionButtons() under recordAction.js
     */
    _getActionButtons: function(){
        let res = this._super(); //dialog buttons
        res[1].text = window.hWin.HR('Select');
        return res;
    },

    /**
     * Return record field values in the form of a json array mapped as [dty_ID: value, ...]
     * For multi-values, [dty_ID: [value1, value2, ...], ...]
     * It is preferable to return the values as an array, as that's how they are handled by editing_inputs
     * 
     * Additional processing for terms, record pointers, file and relationship markers are handled in manageRecords.js
     *  For terms return one of the following: 
     *      an existing term id or label (the set vocabulary will be searched using these values), 
     *      a single value that will act as the term's label, or 
     *      an object with the keys ['label', 'desc', 'code', 'uri', 'translations']
     * 
     *  For record pointers one of the following:
     *      an object with the keys ['value', 'search'] 
     *          - here search is what is entered into the record selection popup and value is the initial data to be filled into a new record (if the user decides to make one)
     *      a single value that will act as both 'value' and 'search'
     * 
     *  For relationship markers one of the following:
     *      an object with the keys ['value', 'search', 'relation']
     *          - both value and search works the same here as they do with record pointers, relation is a term value to act as the relation type
     *      a single value that will act as 'value', 'search' and 'relation'
     * 
     *  For files: a url to the file, the file will be registered as an external file (currently, the file(s) will not be downloaded due to potential copyright problems)
     * 
     * New term values are given to the user where they will either create a completely new term with the given details, or select an existing term
     * Record pointers and relationship markers are presented to the user for them to handle, allowing them to choose what values to keep, skip duplicates/un-wanted values, or even combine values
     * 
     * All other fields are inserted directly into their respective fields.
     * 
     * Include a url to an external record that will appear in the record pointer guiding popup, add 'ext_url' to res
     *  the value must ONLY BE THE URL
     *  e.g. res['ext_url'] = 'www.example.com'
     * 
     * Param: None
     */
    doAction: function(){

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        // Retrieve the selected record(s) as a record set from the result list widget
        let recset = this.recordList.resultList('getSelected', false);

        if(recset && recset.length() == 1){

            let res = {};
            let rec = recset.getFirstRecord(); // Get first record, otherwise use getRecords() to retrieve all selected records as an array

            let map_flds = Object.keys(this.options.mapping.fields); // mapped fields names, to access fields of record

            if(this.options.mapping.options.dump_record == true){
                res['BnF_ID'] = recset.fld(rec, 'BnF_ID'); // add BnF ID
            }
            res['ext_url'] = recset.fld(rec, 'biburl'); // add BnF URL

            // Assign individual field values, here you would perform any additional processing for selected values (example. get ids for vocabulrary/terms and record pointers)
            for(let k=0; k<map_flds.length; k++){

                let field_name = map_flds[k];
                let dty_ID = this.options.mapping.fields[field_name];
                let val = recset.fld(rec, field_name);
                let field_type = $Db.dty(dty_ID, 'dty_Type');

                if(val != null && dty_ID != ''){

                    // Convert to array
                    if(window.hWin.HEURIST4.util.isObject(val)){
                       
                        val = Object.values(val);
                    }else if(!Array.isArray(val)){
                        val = window.hWin.HEURIST4.util.isnull(val) ? '' : val;
                        val = [val];
                    }

                    if(field_name == 'author' || field_name == 'contributor'){ // special treatment for author field

                        for(let i = 0; i < val.length; i++){

                            let value = '';
                            let search = '';
                            let role = '';
                            let cur_val = val[i];

                            if($.isPlainObject(cur_val)){
                                if(cur_val['firstname']){
                                    value = cur_val['firstname'];
                                }
                                if(cur_val['surname']){
                                    value = (value != '') ? value + ' ' + cur_val['surname'] : cur_val['surname'];
                                }
                                search = value;
                                if(cur_val['active']){
                                    value = (value != '') ? value + ' [' + cur_val['active'] + ']' : 'No Name, years active: ' + cur_val['active'];
                                }
                                if(cur_val['id']){
                                    value = (value != '') ? value + ' (id: ' + cur_val['id'] + ')' : 'id: ' + cur_val['id'];
                                }
                                if(cur_val['role']){
                                    role = (value != '') ? cur_val['role'] : '';
                                }
                            }else{
                                value = cur_val;
                                search = cur_val;
                            }

                            if(value != '' && !Array.isArray(value) && !$.isPlainObject(value)){
                                if(field_type == 'resource'){ // Record pointer
                                    val[i] = {'value': value, 'search': search};
                                }else if(field_type == 'relmarker'){ // Relationship marker
                                    val[i] = {'value': value, 'search': search, 'relation': role};
                                }else{
                                    val[i] = value;
                                }
                            }
                        }

                        if(!res[dty_ID]){
                            res[dty_ID] = [];
                        }
                        res[dty_ID] = res[dty_ID].concat(val);

                        continue;
                    }else if(field_name == 'publisher'){

                        for(let i = 0; i < val.length; i++){

                            let value = '';
                            let search = '';
                            let cur_val = val[i];

                            if($.isPlainObject(cur_val)){
                                if(cur_val['name']){
                                    value = cur_val['name'];
                                    search = value;
                                }
                                if(cur_val['location']){
                                    value = (value != '') ? value + ' ' + cur_val['location'] : cur_val['location'];
                                    search = (search != '') ? search : value;
                                }
                            }else{
                                let completed_val = cur_val; //????
                            }

                            if(value != '' && !Array.isArray(value) && !$.isPlainObject(value)){

                                if(field_type == 'resource'){ // Record pointer
                                    val[i] = {'value': value, 'search': search};
                                }else if(field_type == 'relmarker'){ // Relationship marker
                                    val[i] = {'value': value, 'search': search, 'relation': ''};
                                }else{
                                    val[i] = value;
                                }
                            }
                        }

                        if(!res[dty_ID]){
                            res[dty_ID] = [];
                        }
                        res[dty_ID] = res[dty_ID].concat(val);

                        continue;
                    }

                    if(field_type == 'enum'){ 
                        // For the BnF lookups the term field values are sometimes placed within the trm_Code field
                        // So we check those first, returning the term id if found, otherwise the value is returned as the term label

                        if(window.hWin.HEURIST4.util.isObject(val)){ 
                            val = Object.values(val);
                        }else if(!Array.isArray(val)){
                            val = [val];
                        }

                        let vocab_ID = $Db.dty(dty_ID, 'dty_JsonTermIDTree');
                        let term_Ids = $Db.trm_TreeData(vocab_ID, 'set');

                        for(let i=0; i<val.length; i++){

                            if(!Number.isInteger(+val[i])){
                                continue;
                            }

                            for(let j = 0; j < term_Ids.length; j++){
                                let trm_code = $Db.trm(term_Ids[j], 'trm_Code');
                                if(trm_code == val[i]){
                                    val[i] = term_Ids[j];
                                    break;
                                }
                            }
                        }
                    }
                }

                // Check that val and id are valid, add to response object
                if(dty_ID > 0 && val){

                    if(!res[dty_ID]){
                        res[dty_ID] = [];
                    }

                    if(window.hWin.HEURIST4.util.isObject(val)){
                        res[dty_ID] = res[dty_ID].concat(Object.values(val));
                    }else{
                        res[dty_ID] = res[dty_ID].concat(val);    
                    }
                }
            }

            this.closingAction(res);
        }
    },


    /**
     * Perform final actions before exiting popup
     * 
     * Param:
     *  dlg_reponse (json) => mapped values to fields
     */
    closingAction: function(dlg_response){

        let that = this;

        if(window.hWin.HEURIST4.util.isempty(dlg_response)){
            dlg_response = {};
        }

        this.toggleCover('');

        // Pass mapped values back and close dialog
        this._context_on_close = dlg_response;
        this._as_dialog.dialog('close');
    },
    
    /**
     * Create search URL using user input within form
     * Perform server call and handle response
     * 
     * Params: None
     */
    _doSearch: function(){

        let that = this;

        // Construct base url for external request
        let sURL = 'https://catalogue.bnf.fr/api/SRU?version=1.2&operation=searchRetrieve&recordSchema=intermarcxchange&maximumRecords=20&startRecord=1'; // base URL for BnF request

        // Check that something has been entered
        if(this.element.find('#inpt_any').val()==''){
            window.hWin.HEURIST4.msg.showMsgFlash('Please enter a value in the search fields...', 1000);
            return;
        }
        
        // Construct query portion of url
        let query = '(';

        // any field
        if(this.element.find('#inpt_any').val()!=''){
            query += 'bib.anywhere all "' + this.element.find('#inpt_any').val() + '"';
        }

        // Close off and encode query portion, then add to request url
        if(query.length != 1){

            query += ')';
            query = encodeURIComponent(query);

            sURL += '&query=' + query;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent()); // show loading cover

        // for record_lookup.php
        let request = {
            service: sURL, // request url
            serviceType: 'bnflibrary_bib' // requesting service, otherwise the request will result in an error
        };

        // calls /heurist/hserv/controller/record_lookup.php
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack(); // hide loading cover

            if(window.hWin.HEURIST4.util.isJSON(response)){

                if(response.result != null){ // Search result
                    that._onSearchResult(response);
                }else if(response.status && response.status != window.hWin.ResponseStatus.OK){ // Error return
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }else{ // No results
                    that.recordList.show();
                    that.recordList.resultList('updateResultSet', null);
                }
            }
        });
        /* 
         * Alternatively, you can search Heurist records by a call to window.hWin.HAPI4.RecordMgr.search(request, callback),
         *  where here, request = { q: Heurist query, w: a|b (all or bookmarked), f: format to return, db: which Heurist DB to query }
         * (hclient/core/hapi.js, search: function(request, callback))
         * Examples of this can be found in the MPCE and LRC18C lookups
         */
    },
    
    /**
     * Prepare json for displaying via the Heuirst resultList widget
     * 
     * Param:
     *  json_data (json) => search response
     */
    _onSearchResult: function(json_data){

        this.recordList.show();

        let is_wrong_data = true;

        let maxRecords = $('#rec_limit').val(); // limit number of returned records
        maxRecords = (!maxRecords || maxRecords <= 0) ? 20 : maxRecords;

        json_data = window.hWin.HEURIST4.util.isJSON(json_data);

        if (json_data) {
            
            if(!json_data.result) return false;

            let res_records = {}, res_orders = [];

            // Prepare fields for mapping
            // the fields used here are defined within /heurist/hserv/controller/record_lookup_config.json where "service" = bnfLibrary
            let fields = ['rec_ID', 'rec_RecTypeID']; // added for record set
            let map_flds = Object.keys(this.options.mapping.fields);
            fields = fields.concat(map_flds);            
            
            // Parse json to Record Set
            let i=0;
            for(;i<json_data.result.length;i++){

                let record = json_data.result[i];                
                let recID = i+1;
                let values = [recID, this.options.mapping.rty_ID];

                // Add current record details, field by field
                for(let k=0; k<map_flds.length; k++){

                    // With the current setup for API search, the 'Rights' field is no longer sent
                    if(map_flds[k]=='rights'){
                        values.push(null);
                    }else if(map_flds[k] == 'date' && record['publisher'] && record['publisher'][map_flds[k]]){ // Strip non-Numeric chars from dates
                        values.push(record['publisher'][map_flds[k]].replace(/\D/g, ''));
                    }else{ // just add field details
                        values.push(record[map_flds[k]]);
                    }
                }

                res_orders.push(recID);
                res_records[recID] = values;
            }

            if(res_orders.length>0){

                res_records = this.removeDupAuthors(fields.indexOf('author'), res_records); // just removing duplicates

                // Create the record set for the resultList
                let res_recordset = new HRecordSet({
                    count: res_orders.length,
                    offset: 0,
                    fields: fields,
                    rectypes: [this.options.mapping.rty_ID],
                    records: res_records,
                    order: res_orders,
                    mapenabled: true
                });
                this.recordList.resultList('updateResultSet', res_recordset);            
                is_wrong_data = false;
            }

            if(json_data.numberOfRecords > maxRecords){
                window.hWin.HEURIST4.msg.showMsgDlg(
                    "There are " + json_data.numberOfRecords + " records satisfying these criteria, only the first "+ maxRecords +" are shown.<br>Please narrow your search.",
                );
            }
        }

        if(is_wrong_data){
            this.recordList.resultList('updateResultSet', null);
            window.hWin.HEURIST4.msg.showMsgErr({
                message: 'Service did not return data in an appropriate format',
                status: window.hWin.ResponseStatus.UNKNOWN_ERROR
            });
        }
    },

    //
    // Just to remove duplicates from the author field
    //
    removeDupAuthors: function(author_key, records){

        if(records == null || !window.hWin.HEURIST4.util.isObject(records) || window.hWin.HEURIST4.util.isempty(author_key)){
            return records;
        }

        for(let i in records){

            let author_details = records[i][author_key];

            if(author_details){

                records[i][author_key] = author_details.filter(function(val, idx, arr){

                    return idx === arr.findIndex(function(obj){

                        if(val.id && obj.id){
                            return val.id == obj.id;
                        }else if(val.firstname && val.surname && obj.firstname && obj.surname){
                            return val.firstname == obj.firstname && val.surname == obj.surname;
                        }
                    });
                });
            }
        }

        return records;
    },
});