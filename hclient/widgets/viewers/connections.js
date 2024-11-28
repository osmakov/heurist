/**
* Widget for network diagram of a result set. Calls viewers/visualize/springDiagram.php
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Jan Jaap de Groot    <jjedegroot@gmail.com>
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


$.widget( "heurist.connections", {

    // default options
    options: {
        title: '',
        recordset: null,
        selection: null, //list of record ids
        search_realm:  null,  //accepts search/selection events from elements of the same realm only
        init_at_once: false
    },

    _events: null,
    recordset_changed: true,

    // the constructor
    _create: function() {

        let that = this;
        
        this.framecontent = $('<div>')
                   .css({
                        width:'100%', height:'100%', overflow:'hidden',
                       // position:'absolute', top:'2.5em', bottom:0, left:0, right:0,
                        'background':'url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center'})
                   .appendTo( this.element );
                   
        this.graphframe = $( "<iframe>" ).css({overflow: 'none !important', width:'100% !important'}).appendTo( this.framecontent );


        //-----------------------     listener of global events
        this._events = window.hWin.HAPI4.Event.ON_CREDENTIALS
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH 
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHSTART 
            + ' ' + window.hWin.HAPI4.Event.ON_REC_SELECT
            + ' ' + window.hWin.HAPI4.Event.ON_SYSTEM_INITED;

        $(this.document).on(this._events, function(e, data) {
            
            if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS) { 
                
                if(!window.hWin.HAPI4.has_access()){ //logout
                    that.recordset_changed = true;
                    that.options.recordset = null;
                    that._refresh();
                }
                

            // Search results
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){

                //accept events from the same realm only
                if(!that._isSameRealm(data)) return;

                //find all relation within given result set
                that.recordset_changed = true;
                that.options.relations = null;
                that.options.recordset = data.recordset; //HRecordSet
                that._lastRequest = data.request; //last request, contains query

                that._refresh();

            // Search start
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){

                //accept events from the same realm only
                if(!that._isSameRealm(data)) return;
                
                that.options.relations = null;
                that.options.recordset = null;
                that.options.selection = null;

                if(data && !data.reset && data.q!=''){
                    that.loadanimation(true);
                }else{
                    that.recordset_changed = true;
                    that._refresh();
                }
            // Record selection  
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){
                
                if(that._isSameRealm(data) && data.source!=that.element.attr('id')) { //selection happened somewhere else
                  
                    if(data.reset){
                        that.options.selection = null;
                    }else{
                        that._doVisualizeSelection( window.hWin.HAPI4.getSelection(data.selection, true) );
                    }
                }            
            }else if (e.type == window.hWin.HAPI4.Event.ON_SYSTEM_INITED){
                    that._refresh();
            }
        });

        
        this.graphframe.on('load', function(){
                that._refresh();
        });
        
        // Refreshing
        this.element.on("myOnShowEvent", function(event){
            if( event.target.id == that.element.attr('id')){
                that._refresh();
            }
        });
        
        if(this.options.init_at_once){
            this._refresh();  
        }
        
        
    }, //end _create


    /*
    _setOptions: function() {
        // _super and _superApply handle keeping the right this-context
        this._superApply( arguments );
        this._refresh();
    },
    */  

    /* private function */
    _refresh: function(){

        /* change title
        if(this.options.title!=''){
            var id = this.element.attr('id');
            $(".header"+id).html(this.options.title);
            $('a[href="#'+id+'"]').html(this.options.title);
        }*/
        
        //refesh if element is visible only - otherwise it costs much resources        
        if( this.element.is(':visible') && this.recordset_changed) {

            if( window.hWin.HEURIST4.util.isempty(this.graphframe.attr('src')) || this.graphframe.attr('src')!==this.options.url)
            {
                
                this.options.url = window.hWin.HAPI4.baseURL + 'viewers/visualize/springDiagram.php?db=' + window.hWin.HAPI4.database;
                this.graphframe.attr('src', this.options.url);
              
            // Content loaded already    
            }else{
                // SPRING DIAGRAM CODE
                
                if(this.options.recordset !== null) {
                    
                    if(this.options.relations == null){ //relation not yet loaded
                        
                        this._getRelations(this.options.recordset);
                        
                    }else{
                        
                        let MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');
                    
                        let records_ids = this.options.recordset.getIds(MAXITEMS);
                        let relations = this.options.relations;
                        
                        // Parse response to spring diagram format
                        let data = this._parseData(records_ids, relations);
                        this._doVisualize(data);
                    
                    }
                }else if(this.graphframe[0] && this.graphframe[0].contentWindow 
                        && window.hWin.HEURIST4.util.isFunction(this.graphframe[0].contentWindow.showData)){
                    //clear
                    this.graphframe[0].contentWindow.showData(null);
                }
                
            }
        
        }
    },

    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {

        this.element.off("myOnShowEvent");
        $(this.document).off(this._events);

        let that = this;

        // remove generated elements
        this.graphframe.remove();
        this.framecontent.remove();
    },
    
    loadanimation: function(show){
        if(show){
           
            this.framecontent.css('background','url('+window.hWin.HAPI4.baseURL+'hclient/assets/loading-animation-white.gif) no-repeat center center');
        }else{
            this.framecontent.css('background','none');
           
        }
    },
    
    //
    //
    //
    _isSameRealm: function(data){
        return (!this.options.search_realm && (!data || window.hWin.HEURIST4.util.isempty(data.search_realm)))
        ||
        (this.options.search_realm && (data && this.options.search_realm==data.search_realm));
    },
    
    /**
    * private - send request to server side to find all relation withing given recordset
    * @param recordset
    */
    _getRelations: function( recordset ){
        
        if(window.hWin.HEURIST4.util.isnull(recordset)) return;

        this.options.relations = null;
        
        if(!this.element.is(':visible')){
            return;
        }
        
        let that2 = this; 
        //get first MAXITEMS records and send their IDS to server to get related record IDS
        let MAXITEMS = window.hWin.HAPI4.get_prefs('search_detail_limit');
        let records_ids = recordset.getIds(MAXITEMS);
        if(records_ids.length>0){
            
            window.hWin.HAPI4.RecordMgr.search_related({ids:records_ids.join(',')}, function(response)
            {
                let resdata = null;
                if(response.status == window.hWin.ResponseStatus.OK){
                    // Store relationships
                    that2.option("relations", response.data);
                    
                    // Parse response to spring diagram format
                    let data = that2._parseData(records_ids, response.data);
                    that2._doVisualize(data);
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
                
                that2.option("recordset", recordset); //HRecordSet
                that2.loadanimation(false);
                
            });
        }
    }
    

    //@todo - move inside widget


    /**
    * Parses record data and relationship data into usable D3 format
    * 
    * @param records    Object containing all record
    * @param relations  Object containing direct & reverse links
    * 
    * @returns {Object}
    */
    , _parseData: function (records_ids, relations) {
        let data = {}; 
        let nodes = {};                         
        let links = [];

        if(records_ids !== undefined && relations !== undefined) {
            // Construct nodes for each record
            let i;
            for(i=0;i<records_ids.length;i++) {
                let recId = records_ids[i];
                if(relations.headers[recId]){
                    let node = {id: parseInt(recId),
                                name: relations.headers[recId][0],  //record title   records[id][5]
                                image: window.hWin.HAPI4.iconBaseURL+relations.headers[recId][1],  //rectype id  records[id][4]
                                count: 0,
                                depth: 1,
                                rty_ID: relations.headers[recId][1]
                               };
                    nodes[recId] = node;
                }
            }
            
            
            /**
            * Determines links between nodes
            * 
            * @param nodes      All nodes
            * @param relations  Array of relations
            */
            function __getLinks(nodes, relations) {
                let links = [];
                
                // Go through all relations
                for(let i = 0; i < relations.length; i++) { 
                    // Null check
                    let source = relations[i].recID;
                    let target = relations[i].targetID;
                    let dtID = relations[i].dtID;
                    let trmID = relations[i].trmID;
                    let relationName = "Floating relationship";
                    if(dtID > 0) {
                        relationName = $Db.dty(dtID, 'dty_Name');
                    }else if(trmID > 0) {
                        relationName = $Db.trm(trmID, 'trm_Label');
                    }

                    // Link check  - both source and target must be in main result set (nodes)
                    if(source !== undefined && nodes[source] !== undefined && target !== undefined && nodes[target] !== undefined) { 
                        // Construct link
                        let link = {source: nodes[source],
                                    target: nodes[target],
                                    targetcount: 1,
                                    relation: {id: dtID>0?dtID:trmID, 
                                               name: relationName,
                                              type: dtID>0?'resource':'relationship'} 
                                   };
                        links.push(link); 
                    }      
                }   
                
                return links;
            }
                    
                   
            
            // Links
            links = links.concat( __getLinks(nodes, relations.direct)  ); // Direct links
            links = links.concat( __getLinks(nodes, relations.reverse) ); // Reverse links
        }

        // Construct data object with nodes as array
        let array = [];
        for(let id in nodes) {
            array.push(nodes[id]);
        }
        return {nodes: array, links: links};
    }

    /** Calls the visualisation plugin */
    , _doVisualize: function (data) {
        
        if( !window.hWin.HEURIST4.util.isnull(this.graphframe) && this.graphframe.length > 0 ){
            let that = this;
            this.graphframe[0].contentWindow.showData(data, this.options.selection, this._lastRequest,
                    function(selected){
                        $(that.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
                        { selection:selected, source:that.element.attr('id'), search_realm:that.options.search_realm } );
                    },
                    function(selected){
                        that._getRelations(that.options.recordset);
                    }
            );
            this.recordset_changed = false;
        }
        /* Call showData method of the springDiagram iFrame
        var iframe = $("iframe[src*=springDiagram]");
        if(iframe != null && iframe !== undefined && iframe.length >= 1) {
            iframe[0].contentWindow.showData(data);
        }*/
    }    

    , _doVisualizeSelection: function (selection) {

            if(window.hWin.HEURIST4.util.isnull(this.options.recordset)) return;

            this.options.selection = selection;
            
            if(!this.element.is(':visible')
                || window.hWin.HEURIST4.util.isnull(this.graphframe) || this.graphframe.length < 1){
                    return;
            }
            
            this.graphframe[0].contentWindow.showSelection(this.options.selection);
    }    

});