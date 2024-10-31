/**
* recordFindDuplicates.js - Find duplicates by record type and selected fields
*                           It uses levenshtein function on server side
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

$.widget( "heurist.recordFindDuplicates", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 780,
        width:  800,
        modal:  true,
        title:  'Find duplicate records',
        
        htmlContent: 'recordFindDuplicates.html'
    },
    
    //results
    dupes:null,
    summary:null,

    selectedFields:null,
    
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

        this._super();    

        //add header and action buttons for container/inline mode
        if(!this.options.isdialog)
        {
            
            this._$('.ent_wrapper').css({top:'36px'});
            
            let fele = this._$('.ent_wrapper:first');
            
            $('<div class="ui-heurist-header">'+this.options.title+'</div>').insertBefore(fele);    
            
            //append action buttons
            this.toolbar =  this._$('#div_button-toolbar');
            let btns = this._getActionButtons();
            for(let idx in btns){
                this._defineActionButton2(btns[idx], this.toolbar);
            }
        }
        return true;
    },

    //    
    //
    //
    _getActionButtons: function(){
        let res = this._super();
        let that = this;
        res[1].text = window.hWin.HR('Find Duplications');
        res[1].css = {'margin-left':'20px'};
        
        
        res[0].css = {float: 'right', 'margin-top': 10};
        res[0].text = window.hWin.HR('Clear ignoring list');    
        res[0].click = function() { 
                        that._ignoreClear();
                    };
        

        return res;
    },    
        
    //
    // Fills record type selector. Parent method is overwritten
    //
    _fillSelectRecordScope: function (){

        this.selectRecordScope.empty();

        let selScope = this.selectRecordScope.get(0);
        
        this.selectRecordScope = window.hWin.HEURIST4.ui.createRectypeSelectNew( selScope,
        {
            topOptions: [{key:'-1',title:'select record type to search...'}],
            useHtmlSelect: false,
            useCounts: true,
            showAllRectypes: true
        });
        
        
        
        this._on( this.selectRecordScope, {
                change: this._onRecordScopeChange} );        
        this._onRecordScopeChange();
        
        selScope = this.selectRecordScope.get(0);
        window.hWin.HEURIST4.ui.initHSelect(selScope);
    },
            
    //
    // 
    //
    doAction: function(){

            let rty_ID = this.selectRecordScope.val();

            this._$('#div_result').empty();
            
            if(rty_ID>0){


                let settings = this.getSettings(true);            
                if(!settings) return;

                settings.fields = settings.fields[rty_ID];

                //unique session id    
                let session_id = Math.round((new Date()).getTime()/1000);
                this._showProgress( session_id, false, 1000 );

                let request = {
                    a        : 'dupes',
                    db       : window.hWin.HAPI4.database,
                    rty_ID   : rty_ID,
                    fields   : settings.fields,
                    session  : session_id,
                    startgroup: settings.startgroup,
                    sort_field: settings.sort_field,
                    distance : settings.distance};

                let url = window.hWin.HAPI4.baseURL + 'hserv/controller/recordVerify.php'
                let that = this;

                window.hWin.HEURIST4.util.sendRequest(url, request, null, function(response){
                    that._hideProgress();
                    
                    //render groups
                    if(response.status == window.hWin.ResponseStatus.OK){

                        that.summary = response.data['summary'];
                        response.data['summary'] = null;
                        delete response.data['summary'];
                      
                        that.dupes = response.data;
                        that._renderDuplicates();

                    }else{
                        
                        if(response.status==window.hWin.ResponseStatus.ACTION_BLOCKED){

                            let sMsg = `<p>Finding duplicates in ${response.message} records will be extremely slow and could overload our server under some circumstances.</p>` 
+'<p>In order to streamline the process, please specify a field on which to sort the records. Typically use the constructed title, or a name or title field which will ensure that potential duplicates sort close to one-another. The sort is alphabetical.</p>' 
+'<p>We then search for duplicates in a sliding window of 10,000 records within this sorted list</p>'
+'<p>You may further increase speed by setting "Group by beginning"</p>';

                            
                            window.hWin.HEURIST4.msg.showMsgErr({
                                message: sMsg,
                                error_title: 'Request too large',
                                status: window.hWin.ResponseStatus.ACTION_BLOCKED 
                            });    
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);    
                        }
                        
                    }
                });

            }

    },
    
    //
    //
    //
    _hideProgress: function (){
        this._super(); 
        this._$('#div_search').show();  
    },
    
    //
    // mode_action true - returns fields to compare
    //
    getSettings: function( mode_action ){
        
            let header_fields = {id:'rec_ID',title:'rec_Title',url:'rec_URL',addedby:'rec_AddedBy',notes:'rec_ScratchPad'};
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
                    __addSelectedField(ids, 1, 0);
                }else{
                    selectedFields.push(node.data.code);
                }
            }
        return {
                fields: selectedFields,
                distance: this._$('#distance').val(),
                startgroup: this._$('#startgroup').val(),
                sort_field: this._$('#sort_field').val()
                };
        
    },

    //
    // overwritten - reload treeview
    //
    _onRecordScopeChange: function() 
    {
        let isdisabled = this._super();
        
        
        
        let rtyID = this.selectRecordScope.val();
        
        if(this._selectedRtyID!=rtyID ){
            if(rtyID>0){
                //reload treeview
                this._loadRecordTypesTreeView( rtyID );
                $('.rtt-tree').parent().show();
            }else{
                $('.rtt-tree').parent().hide();
            }
            this.selectedFields = [];
        }
        
        return isdisabled;
    },
    
    //
    // show treeview with record type structure
    //
    _loadRecordTypesTreeView: function(rtyID){
        
        let that = this;

        if(this._selectedRtyID!=rtyID ){
            
            this._selectedRtyID = rtyID;
            
            
            let allowed_fieldtypes = ['rec_Title','rec_AddedBy','rec_URL','rec_ScratchPad',
                'enum','freetext','blocktext',
                'year','date','integer','float','resource'];
            
            //generate treedata from rectype structure
            let treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, rtyID, allowed_fieldtypes );
            
            treedata = treedata[0].children;
            //treedata[0].selected = true;  - select first field by default - disabled 2024-10-31
            //treedata[0].expanded = true; //first expanded
            
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
                /*
                lazyLoad: function(event, data){
                    var node = data.node;
                    var parentcode = node.data.code; 
                    var rectypes = node.data.rt_ids;
                    
                    var res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, 
                                                        rectypes, ['header_ext','all'], parentcode );
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
                */
                select: function(e, data) {
                    that._fillSortField();
                },
                click: function(e, data){
                   if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){
                       data.node.setExpanded(!data.node.isExpanded());
                      
                       
                   }
                   //else if( false && data.node.lazy  ) { 
                  
                   //}
                },
                
                dblclick: function(e, data) {
                    data.node.toggleSelected();
                },
                keydown: function(e, data) {
                    if( e.which === 32 ) {
                        data.node.toggleSelected();
                        return false;
                    }
                }
            });
            
            let tree = $.ui.fancytree.getTree( treediv );
            tree.visit(function(node){
                if(node.lazy){
                    node.folder = false;
                    node.lazy = false;
                    node.removeChildren();
                }
            });
            setTimeout(function(){
                tree.render();
                that._fillSortField();
            },1000);
            
        }   
    },
    
    //
    //
    //
    _fixDuplicatesPopup: function(event){
        
        let sGroupID = $(event.target).attr('data-action-merge');
        let sRecIds = Object.keys(this.dupes[sGroupID]);

        let url = window.hWin.HAPI4.baseURL
                + 'admin/verification/combineDuplicateRecords.php?bib_ids='
                + sRecIds.join(',')
                + '&db=' + window.hWin.HAPI4.database;
                
        let that = this;

        window.hWin.HEURIST4.msg.showDialog(url, {
            width:800, height:600,
            default_palette_class:'ui-heurist-explore',
            title: window.hWin.HR('Combine duplicate records'),
            callback: function(context) {
                if(context=='commited'){

                    that.element.find('.group_'+sGroupID).hide();

                    let cur_query = $.extend(true, {}, window.hWin.HEURIST4.current_query_request);
                    cur_query.id = null;
                    cur_query.source = null;
                    cur_query.no_menu_switch = 1;

                    window.hWin.HAPI4.RecordSearch.doSearch(that, cur_query);
                }
            }
        });
        
        return false;
    },
    
    //
    //
    //
    _ignoreGroup: function(event){

        let sGroupID = $(event.target).attr('data-action-ignore');
        let sRecIds = Object.keys(this.dupes[sGroupID]);

        let request = {
            a        : 'dupes',
            db       : window.hWin.HAPI4.database,
            ignore   : sRecIds.join(',')};

        let url = window.hWin.HAPI4.baseURL + 'hserv/controller/recordVerify.php'

        window.hWin.HEURIST4.util.sendRequest(url, request, null, function(response){

            if(response.status == window.hWin.ResponseStatus.OK){
                $(event.target).parents('div.group').hide();
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);    
            }
        });

        return false;
    },

    //
    //
    //
    _ignoreClear: function(){

        let request = {
            a        : 'dupes',
            db       : window.hWin.HAPI4.database,
            ignore   : 'clear'};

        let url = window.hWin.HAPI4.baseURL + 'hserv/controller/recordVerify.php'

        window.hWin.HEURIST4.util.sendRequest(url, request, null, function(response){

            if(response.status == window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgFlash('cleared',1000);
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);    
            }
        });

        return false;
    },

    
    //
    //
    //
    _renderDuplicates: function(){
        
        let dupes = this.dupes;

        let s = `<div style="padding:10px;">${this.summary['scope']} records have been checked.`
                    + '<p>'
                        + `There are ${this.summary['cnt_records']} potential duplicates grouped in ${this.summary['cnt_groups']} groups`
                        + `<span style="display: inline-block;padding-left: 10px;text-decoration: underline; cursor: pointer;" id="download_list">Download list as spreadsheet (TSV) file</span>`
                    + '</p>';

        if(this.summary['cnt_records']>this.summary['limit']){
            s = s + '<p>Operation has been terminated since the number of possible duplicaions is more than limit in '
                    +this.summary['limit']+' records. Reduce the distance or add additional search field</p>';   
        }else if(this.summary['is_terminated']){

            s = s + '<p>Operation has been terminated by user</p>';
        }
        
        s = s +'<p><b>Merge this group</b> link will ask which members of the group to merge before any changes are made.</p>'
            + '</div>'

        let grp_cnt = Object.keys(dupes).length;
        for(let i=0; i<grp_cnt; i++) {

            let rec_ids = Object.keys(dupes[i]);

            s += `<div style="padding: 10px 20px;" class="group group_${i}">`;

            s += `<a href="#" data-action-merge="${i}">merge this group</a>&nbsp;&nbsp;&nbsp;&nbsp;`

                +`<a target="_new" href="${window.hWin.HAPI4.baseURL}?db=${window.hWin.HAPI4.database}`
                    +`&w=all&q=ids:${rec_ids.join(',')}">view as search</a>&nbsp;&nbsp;&nbsp;&nbsp;`

                +`<a href="#" data-action-ignore="${i}">ignore in future</a>&nbsp;&nbsp;&nbsp;&nbsp;`

                +`<input type="checkbox" class="enable_instant_merge" title="This function retains data ONLY from the record you select,&#013;and redirects pointers from the other records to that record" value="${i}"> `
                +`<a href="#" class="instant_merge_records" style="cursor: pointer; text-decoration: underline;" data-action-instant="${i}">instant merge</a>`
                +`<span data-msg-idx="${i}" style="display: none;color: red;font-size: 10px;padding-left: 10px;vertical-align: top;">Warning: Instant merge only conserves values<br>from the selected record</span>`;

            //list of records
            s += `<ul style="padding: 10px 30px;" class="group_${i}">`;
            for(let j=0; j<rec_ids.length; j++) {

                s += '<li>'
                + `<input type="radio" name="instant_merge_${i}" value="${rec_ids[j]}" style="display: none;">`
                + `<a target="_new" href="${window.hWin.HAPI4.baseURL}viewers/record/viewRecord.php?db=${window.hWin.HAPI4.database}`
                + `&recID=${rec_ids[j]}">${rec_ids[j]}`
                + `: ${window.hWin.HEURIST4.util.stripTags(dupes[i][rec_ids[j]])}</a></li>`;

            }
            s = s + '</ul></div>';
        }

        this._off(
            this._$('#div_result').find('a[data-action-merge]'),'click');

        
        this._$('#div_result').html(s);
        
        this._on(
            this._$('#div_result').find('a[data-action-merge]'),
            {click: this._fixDuplicatesPopup });

        this._on(
            this._$('#div_result').find('a[data-action-ignore]'),
            {click: this._ignoreGroup });

        window.hWin.HEURIST4.util.setDisabled(this._$('.instant_merge_records'), true);
        this._on(this._$('.enable_instant_merge'), {
            change: (e) => {

                let idx = $(e.target).val();
                let enable = !$(e.target).is(':checked');

                window.hWin.HEURIST4.util.setDisabled(this._$(`a[data-action-instant="${idx}"]`), enable);

                !enable ? this._$(`span[data-msg-idx="${idx}"]`).css('display', 'inline-block') : this._$(`span[data-msg-idx="${idx}"]`).hide();
                !enable ? this._$(`input[name="instant_merge_${idx}"]`).show() : this._$(`input[name="instant_merge_${idx}"]`).hide();
            }
        });

        this._on(this._$('.instant_merge_records'), {
            click: this._instantMergeRecords
        });

        this._on(this._$('#download_list'), {
            click: this._downloadList
        });
    },

    _fillSortField: function(){
        
        let tree = $.ui.fancytree.getTree( this._$('.rtt-tree') );
        let fieldIds = tree.getSelectedNodes(false);
        let k, len = fieldIds.length;
        
        let sel = this._$('#sort_field');
        let keep_val = sel.val();
        sel.empty();
        
        for (k=0;k<len;k++){
            let node =  fieldIds[k];
            if(window.hWin.HEURIST4.util.isempty(node.data.code)) continue;
            
            if(node.type=='freetext' || node.type=='blocktext'){
                let key = node.key.split(':');
                key = key[key.length-1];
                window.hWin.HEURIST4.ui.addoption(sel[0], key, node.data.name);
            }
        }
        sel.val(keep_val);
        if(!(sel[0].selectedIndex>0)) sel[0].selectedIndex = 0;

    },

    _instantMergeRecords: function(event){

        let that = this;

        let $link = $(event.target);
        let group_idx = $link.attr('data-action-instant');
        let $parent_record = this._$(`input[name="instant_merge_${group_idx}"]:checked`);

        if($link.hasClass('ui-state-disabled') || $parent_record.length == 0){
            window.hWin.HEURIST4.msg.showMsgFlash('Select the record to keep...', 3000);
            return;
        }

        // Get parent record id
        let rec_id = $parent_record.val();
        if(rec_id < 1){
            return;
        }

        let params = new URLSearchParams({
            db: window.hWin.HAPI4.database,
            instant_merge: 1,
            master_rec_id: rec_id,
            bib_ids: Object.keys(this.dupes[group_idx]).join(',')
        });

        let url = `${window.hWin.HAPI4.baseURL}admin/verification/combineDuplicateRecords.php?${params.toString()}`;

        window.hWin.HEURIST4.msg.showDialog(url, {
            title: 'Quick merging records',
            afterclose: () => {

                that.element.find(`.group_${group_idx}`).hide();

                let cur_query = $.extend(true, {}, window.hWin.HEURIST4.current_query_request);
                cur_query.id = null;
                cur_query.source = null;
                cur_query.no_menu_switch = 1;

                window.hWin.HAPI4.RecordSearch.doSearch(that, cur_query);
            }
        });

    },

    _downloadList: function(){

        let rty_ID = this.selectRecordScope.val();
        let settings = this.getSettings(true);
        if(rty_ID < 1 || !settings){
            return;
        }

        settings.fields = settings.fields[rty_ID];

        let params = new URLSearchParams({
            a: 'dupes',
            export: 1,
            db: window.hWin.HAPI4.database,
            rty_ID: rty_ID,
            session: window.hWin.HEURIST4.util.random(),
            fields: settings.fields,
            startgroup: settings.startgroup,
            sort_field: settings.sort_field,
            distance : settings.distance
        });

        let url = `${window.hWin.HAPI4.baseURL}hserv/controller/recordVerify.php?${params.toString()}`;

        window.open(url, '_blank');
    }
});

