/**
* HEURIST QUERY utility functions
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

if (!window.hWin.HEURIST4){
    window.hWin.HEURIST4 = {};
}
//init only once
if (!window.hWin.HEURIST4.query) 
{

window.hWin.HEURIST4.query = {

    //--- HEURIST QUERY ROUTINES -------
    
    //
    // from object to query string
    // JSON to URL
    // hQueryComposeURL
    composeHeuristQueryFromRequest: function(query_request, encode){
            let query_string = 'db=' + window.hWin.HAPI4.database;
            
            let mapdocument = window.hWin.HEURIST4.util.getUrlParameter('mapdocument', window.hWin.location.search);
            if(mapdocument>0){
                query_string = query_string + '&mapdocument='+mapdocument;
            }
        
            if(!window.hWin.HEURIST4.util.isnull(query_request)){

                if(!window.hWin.HEURIST4.util.isempty(query_request.w)){
                    query_string = query_string + '&w='+query_request.w;
                }
                
                if(!window.hWin.HEURIST4.util.isempty(query_request.q)){
                    
                    let sq;

                    if(Array.isArray(query_request.q) || $.isPlainObject(query_request.q)){
                        sq = JSON.stringify(query_request.q);
                    }else{
                        sq = query_request.q;
                    }
                    
                    if(encode){
                        sq = encodeURIComponent(sq);
                    }
                    
                    query_string = query_string + '&q=' + sq;
                }
                
                let rules = query_request.rules;
                if(!window.hWin.HEURIST4.util.isempty(rules)){
                    if(Array.isArray(query_request.rules) || $.isPlainObject(query_request.rules)){
                        rules = JSON.stringify(query_request.rules);
                    }
                    //@todo simplify rules array - rempove redundant info
                    query_string = query_string + '&rules=' + 
                        (encode?encodeURIComponent(rules):rules);
                        
                    if(query_request.rulesonly==true) query_request.rulesonly=1;    
                    if(query_request.rulesonly>0){
                        query_string = query_string + '&rulesonly=' + query_request.rulesonly;
                    }
                }
                
                        
            }else{
                query_string = query_string + '&w=all';
            }        
            return query_string;        
    },

    /* 
                JSON to URL
    params:{
    q,
    w or domain,
    rules,
    rulesonly
    notes
    viewmode
    }}
    
    */
    composeHeuristQuery2: function(params, encode){
        if(params){

            let query, rules = params.rules;
            let query_to_save = [];

            if(!(window.hWin.HEURIST4.util.isempty(params.w) || params.w=='all' || params.w=='a')){
                query_to_save.push('w='+params.w);
            }

            if(!window.hWin.HEURIST4.util.isempty(params.q)){

                if(Array.isArray(params.q) || $.isPlainObject(params.q)){
                    query = JSON.stringify(params.q);
                } else{
                    query = params.q;
                }
                query_to_save.push('q='+ (encode?encodeURIComponent(query):query) );
            }


            if(!window.hWin.HEURIST4.util.isempty(rules)){


                if(Array.isArray(params.rules) || $.isPlainObject(params.rules)){
                    rules = JSON.stringify(params.rules);
                } else{
                    rules = params.rules;
                }
                query_to_save.push('rules='+ (encode?encodeURIComponent(rules):rules));
                
                if(params.rulesonly==true) params.rulesonly=1;    
                if(params.rulesonly>0){
                    query_to_save.push('rulesonly=' + params.rulesonly);
                }
            }

            if(!window.hWin.HEURIST4.util.isempty(params.notes)){
                query_to_save.push('notes='+ (encode?encodeURIComponent(params.notes):params.notes));
            }

            if(!window.hWin.HEURIST4.util.isempty(params.viewmode)){
                query_to_save.push('viewmode='+ params.viewmode);
            }

            return '?'+query_to_save.join('&');

        }else
            return '?';
    },

    //
    // removes codes section and empty levels
    //
    cleanRules: function(rules){
        
        if(window.hWin.HEURIST4.util.isempty(rules)){
            return null;
        }
        
        rules = window.hWin.HEURIST4.util.isJSON(rules); //parses if string
        
        if(rules===false){
            return null;
        }
        
        for(let k=0; k<rules.length; k++){
            delete rules[k]['codes'];
            let rl = null;
            if(rules[k]['levels'] && rules[k]['levels'].length>0){
                rl = window.hWin.HEURIST4.query.cleanRules(rules[k]['levels']);
            }
            if(rl==null){
                delete rules[k]['levels'];    
            }else{
                rules[k]['levels'] = rl;    
            }
            
        }
        
        return rules;        
    },

    //
    // both parameter should be JSON array or Object (rules are ignored)
    //
    mergeHeuristQuery: function(){
        
        let res_query = [];
        
        if(arguments.length>0){

            let idx=1, len = arguments.length;
            
            res_query = arguments[0];
            for (;idx<len;idx++){
                if(arguments[idx])
                   res_query = window.hWin.HEURIST4.query.mergeTwoHeuristQueries(res_query, arguments[idx]);
            }     
        }   
        
        return res_query;
    },
    
    mergeTwoHeuristQueries: function(query1, query2){

        //return object  {q:, rules:, plain:}
        function __prepareQuery(query){
            
            let rules = false, sPlain = false;
            let isJson = false;
            
            let query_a = window.hWin.HEURIST4.util.isJSON(query);
            if( query_a ){
                query = query_a; //converted to json    
                
                if(query_a['q']){
                    query = query_a['q'];
                    if(query_a['rules']){
                        rules = query_a['rules'];    
                    }
                    query_a = window.hWin.HEURIST4.util.isJSON(query);
                    if( query_a ){
                        query = query_a;
                        isJson = true;
                    }
                }else{
                    isJson = true;    
                }
            }
                    
            if(!isJson){
                if(window.hWin.HEURIST4.util.isempty(query)){
                    query = {};    
                }else{
                    sPlain = query;
                    query = {plain: encodeURIComponent(query)}; //query1.split('"').join('\\\"')};    
                }
            }
            let res = {q:query};    
            if(rules){
                res['rules'] = rules;
            }
            if(sPlain){
                res['plain'] = sPlain;
            }else{
                res['plain'] = false;
            }
            
            return res;
        }

/*        
        var sPlain1 = false, sPlain2 = false;
        if(typeof query2 === "string"){
            var notJson = true;
            try{
               
                var query2a = window.hWin.HEURIST4.util.isJSON(query2);
                if( query2a ){
                    if(query2a['q']){
                        query2 = query2a['q'];    
                        if(query2a['rules']){
                            rules2 = query2a['rules'];    
                        }
                        if(window.hWin.HEURIST4.util.isJSON(query2)){
                            notJson = false;
                        }
                    }else{
                        query2 = query2a;
                        notJson = false;
                    }
                }
            }catch (ex2){
            }
            if(notJson){
                if(window.hWin.HEURIST4.util.isempty(query2)){
                    query2 = {};    
                }else{
                    sPlain2 = query2;
                    query2 = {plain: encodeURIComponent(query2)}; //query2.split('"').join('\\\"')};    
                }
            }
        }
*/        

        let q1 = __prepareQuery(query1);
        let q2 = __prepareQuery(query2);

        if(q1['plain'] && q2['plain'])
        {
            return q1['plain']+' '+q2['plain'];
        }else{
            query1 = q1['q'];
            query2 = q2['q'];
            
            if(window.hWin.HEURIST4.util.isnull(query1) || $.isEmptyObject(query1)){
                return query2;
            }
            if(window.hWin.HEURIST4.util.isnull(query2) || $.isEmptyObject(query2)){
                return query1;
            }
            if(!Array.isArray(query1)){
                query1 = [query1];
            }
            if(!Array.isArray(query2)){
                query2 = [query2];
            }
        
            return query1.concat(query2)    
        }
        
    },
    
    //
    // converts query string to object
    // URL to JSON
    // hQueryParseURL
    //
    parseHeuristQuery: function(qsearch)
    {

        let res = {};
        let type = -1;
        
        let query = '', domain = null, rules = '', rulesonly = 0, notes = '', primary_rt = null, viewmode = '', db='';
        if(qsearch){
            
            if(typeof qsearch === 'string' && qsearch.indexOf('?')==0){ //this is query in form of URL params 
                domain  = window.hWin.HEURIST4.util.getUrlParameter('w', qsearch);
                rules   = window.hWin.HEURIST4.util.getUrlParameter('rules', qsearch);
                rulesonly = window.hWin.HEURIST4.util.getUrlParameter('rulesonly', qsearch);
                notes   = window.hWin.HEURIST4.util.getUrlParameter('notes', qsearch);
                viewmode = window.hWin.HEURIST4.util.getUrlParameter('viewmode', qsearch);
                query = window.hWin.HEURIST4.util.getUrlParameter('q', qsearch);
                db = window.hWin.HEURIST4.util.getUrlParameter('db', qsearch);
                
                res.ui_notes = notes;
                
            }else{ //it may be aquery in form of json
            
                let r = window.hWin.HEURIST4.util.isJSON(qsearch);
                if(r!==false){
                    
                    if(Array.isArray(r.rectypes)){
                        r.type = 3; // faceted
                        r.w = (r.domain=='b' || r.domain=='bookmark')?'bookmark':'all';
                        r.domain = r.w;
                        return r;
                    }
                    
                    if(r.rules){
                        rules = r.rules;
                    }
                    if(r.q){
                        query = r.q;
                    }else if(r.type!=3 && !r.rules) {
                        query = r;
                    }
                    
                    if(r.db){
                        db = r.db;
                    }
                    domain = r.w?r.w:'all';
                    primary_rt = r.primary_rt; 
                    rulesonly = r.rulesonly;
                    
                    //localized name and note
                    $(Object.keys(r)).each(function(i,key){
                        if(key.indexOf('ui_name')==0 || key.indexOf('ui_notes')==0){
                            res[key] = r[key];
                        }
                    });
                }else{ //usual string
                    query = qsearch;
                }
            }
            
        }
        
        if(window.hWin.HEURIST4.util.isempty(query)){
            type = window.hWin.HEURIST4.util.isempty(rules) ?-1:2; //empty, rulesonly 
        }else {
            type = window.hWin.HEURIST4.util.isempty(rules) ?0:1; //searchonly, both
        }
        
        domain = (domain=='b' || domain=='bookmark')?'bookmark':'all';
        
        res = $.extend(res, {q:query, w:domain, domain:domain, rules:rules, rulesonly:rulesonly, 
                            primary_rt:primary_rt, viewmode:viewmode, type:type});    
        
        if(!window.hWin.HEURIST4.util.isempty(db)){
            res.db = db;
        }
        
        return res;
    },

    //
    // get combination query and rules as json array for map query layer
    // Returns current search request as stringified JSON
    //    
    hQueryStringify: function(request, query_only){
        
        let res = {};
        
        if(window.hWin.HEURIST4.util.isempty(request.q)){
            return '';
        }else {
            let r = window.hWin.HEURIST4.util.isJSON(request.q);
            if(r!==false){
                if(r.facets) return ''; //faceted search not allowed for map queries
                res['q'] = r;
            }else{
                res['q'] = request.q;
            }
        }
        
        if(query_only===true){
            res = res['q'];  
        }else{ 
        
            if(!window.hWin.HEURIST4.util.isempty(request.rules)){
                //cleanRules?
                let r = window.hWin.HEURIST4.util.isJSON(request.rules);
                if(r!==false){
                    if(r.facets) return ''; //faceted search not allowed for map queries
                    res['rules'] = r;
                }else{
                    res['rules'] = request.rules;
                }
            }

            if(!window.hWin.HEURIST4.util.isempty(request.w) && !(request.w=='a' || request.w=='all')){
                    res['w'] = request.w;
            }
            
            if(request.rulesonly==1 || request.rulesonly==true){
                    res['rulesonly'] = 1;
            }else if(request.rulesonly==2){
                    res['rulesonly'] = 2;
            }

            if(request.database){
                    res['db'] = request.database;
            }else if(request.db){
                    res['db'] = request.db;
            }
        }
        
        return JSON.stringify(res);;
    },
    
    //
    //
    //
    hQueryCopyPopup: function(request, pos_element){
        
        let res = window.hWin.HEURIST4.query.hQueryStringify(request);
        
        let buttons = {};
        buttons[window.hWin.HR('Copy')]  = function() {
            
            let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
            let target = $dlg.find('#dlg-prompt-value')[0];
            target.trigger('focus');
            target.setSelectionRange(0, target.value.length);
            let succeed;
            try {
                document.execCommand("copy");
                $dlg.dialog( "close" );
            } catch(e) {
               alert('Not supported by browser');
            }                            
            
        }; 
        buttons[window.hWin.HR('Close')]  = function() {
            let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
            $dlg.dialog( "close" );
        };
        
        let opts = {width:450, buttons:buttons, default_palette_class: 'ui-heurist-explore'}
        if(pos_element){
            if(pos_element.my){
                opts.my = pos_element.my;
                opts.at = pos_element.at;
                opts.of = pos_element.of;
            }else{
                opts.my = 'left top';
                opts.at = 'right bottom';
                opts.of = pos_element
            }
        }        
        
        window.hWin.HEURIST4.msg.showPrompt(
            '<label for="dlg-prompt-value">Edit and copy the string and paste into the Mappable Query filter field</label>'
            + '<textarea id="dlg-prompt-value" class="text ui-corner-all" '
            + ' style="min-width: 200px; margin-left:0.2em;margin-top:10px;" rows="3" cols="70">'
            + res
            +'</textarea>',null,'Copy query string', opts);
        
    },

    //
    // Converts string 10:123 to heurist query {t:10, f123: }
    //
    createFacetQuery: function(code, need_query, respect_relation_direction){

        let result = {};
        
        code = code.split(':');

        let dtid = code[code.length-1];
        let linktype = dtid.substr(0,2);
        if(linktype=='lt' || linktype=='lf' || linktype=='rt' || linktype=='rf'){
            //unconstrained link
            code.push('0');         //!!!!!!!!
            code.push('title');
        }

        result['id']   = code[code.length-1]; //last dty_ID
        result['rtid'] = code[code.length-2];
        
        //creates lists of queries to search facet values
        if(need_query===true){  //not direct input

            //create query to search facet values
            function __crt( idx ){
                let res = null;
                if(idx>0){  //this is relation or link

                    res = [];

                    let pref = '';
                    let qp = {};

                    if(code[idx]>0){ //if 0 - unconstrained
                        qp['t'] = code[idx];
                        res.push(qp);
                    }

                    let fld = code[idx-1]; //link field
                    if(fld.indexOf('lf')==0){
                        pref = 'linked_to';    
                    }else if(fld.indexOf('lt')==0){
                        pref = 'linkedfrom';    
                    }else if(fld.indexOf('rf')==0){
                        pref = respect_relation_direction?'related_to':'related';    
                    }else if(fld.indexOf('rt')==0){
                        pref = respect_relation_direction?'relatedfrom':'related';
                    }

                    qp = {};
                    qp[pref+':'+fld.substr(2)] = __crt(idx-2);    
                    res.push(qp);
                }else{ //this is simple field
                    res = '$IDS';
                }
                return res;
            }

            /*if(code.length-2 == 0){
            res['facet'] = {ids:'$IDS'};
            }else{}*/
            result['facet'] = __crt( code.length-2 );
        }

        code.pop();
        result['code'] = code.join(':');  //qcode without last dty_ID
        
        return result;
    },

    jsonQueryToPlainText: function(query, is_sub_query = false){

        let plain_text = '';
        if(window.hWin.HEURIST4.util.isempty(query) || !window.hWin.HEURIST4.util.isJSON(query)){
            return plain_text;
        }

        query = window.hWin.HEURIST4.util.isJSON(query);
        let rty_ID = null;
        let deconstructed = [];
        let sortby = [];

        function handleRectype(rty_IDs){

            if(rty_IDs.match(/\d, ?\d/)){
                rty_IDs = window.hWin.HEURIST4.util.isPositiveInt(rty_IDs) ? [rty_IDs] : rty_IDs.split(',').filter((id) => window.hWin.HEURIST4.util.isPositiveInt(id) && id > 0);
            }else{
                rty_IDs = [rty_IDs];
            }

            let labels = [];
            for(const id of rty_IDs){
                labels.push($Db.rty(id, 'rty_Name') ?? id);
            }
            rty_ID = rty_IDs.join(',');

            deconstructed.unshift(`Searching for <em>${window.hWin.HEURIST4.util.stripTags(labels.join(', '))}</em> records`);
        }

        function handleDefault(key, field, value){

            let type = '';
            let conditional = '';
            if(window.hWin.HEURIST4.util.isPositiveInt(field)){
                type = $Db.dty(field, 'dty_Type');
                let field_name = $Db.rst(rty_ID, field, 'rst_DisplayName');
                field_name = field_name ?? $Db.dty(field, 'dty_Name');
                field = field_name;
            }

            if(key === 'r' && !field){ // Relation type field handling

                let cond = value.startsWith('-') ? 'not' : '';
                if(window.hWin.HEURIST4.util.isPositiveInt(value) || value.match(/\d, ?\d/)){
                    value = value.split(',');
                    value = value.filter((id) => window.hWin.HEURIST4.util.isPositiveInt(id));
                    value = value.map((id) => $Db.trm(id, 'trm_Label'));
                    value = value.filter((trm) => !window.hWin.HEURIST4.util.isempty(trm)).join(', ');
                }

                conditional = `<em>Relationship type</em> that is ${cond} a match or is ${cond} a child of "${value}"`;
            }else if(key === 'r' || key === 'relf' || key === 'rf'){ // Relation field
                field = `Relationship ${field}`;
            } // other

            if(key.startsWith('link') || key.startsWith('related')){ // linked_to,linkedfrom,related_to,relatedfrom,links

                let sub_query = window.hWin.HEURIST4.query.jsonQueryToPlainText(value, true) ?? 'Missing sub query';
                let linking = key.indexOf('link') >= 0 ? 'Linked' : 'Related';
                let direction = key.indexOf('from') >= 0 ? 'from' : 'to';
                conditional = `<br>Search ${linking} Records ${direction} ${field}:<br><div style="padding:5px;">${sub_query}</div>`;
                field = '';
            }

            return [field, conditional, type];
        }

        function handleAnyAll(type, value){
            let sub_text = window.hWin.HEURIST4.query.jsonQueryToPlainText(value, true) ?? 'Missing sub query';
            deconstructed.push(`${type === 'any' ? 'Meets one of the following filters:<div style="margin-left:5px;">' : ''}${sub_text}${type === 'any' ? '</div>' : ''}`);
        }

        let idx = query.findIndex((obj) => Object.hasOwn(obj, 't'));
        if(idx > 0){
            query.unshift(query.splice(idx, 1)[0]);
        }

        for(const idx in query){

            let part = query[idx];
            let key = Object.keys(part)[0];
            let field_key = key;
            let value = part[key];
            let cond = '';

            let parts = null;
            try{                
                parts = key.split(':');
            }catch{
                continue;
            }

            key = parts.shift();

            let field = '';
            let type = 'freetext';

            switch(field_key){
                case 'ids':
                case 'id':
                    field = 'Record IDs';
                    break;
                case 'title':
                    field = 'Record Titles';
                    break;
                case 'url':
                case 'u':
                    field = 'Record URLs';
                    break;
                case 'notes':
                case 'n':
                    field = 'Record Notes';
                    break;
                case 'added':
                    field = 'Record Creation date';
                    break;
                case 'date':
                case 'modified':
                    field = 'Record Last Modification';
                    break;
                case 'addedby':
                    field = 'Record Creator';
                    break;
                case 'owner':
                case 'workgroup':
                case 'wg':
                    field = 'Record Owner';
                    break;
                case 'tag':
                case 'keyword':
                case 'kwd':
                    field = 'Record Tags';
                    break;
                case 'visibility':
                case 'access':
                    field = 'Record Accessibility';
                    break;
                case 'user':
                    cond = `Records Bookmarked by user(s) in "${value}"`;
                    break;
                case 'before':
                case 'after':
                    cond = `Records last modified ${field_key} the ${value}`;
                    break;
                case 'sortby':
                    value = window.hWin.HEURIST4.query.sortbyValue(value, rty_ID);
                    !value || sortby.push(value);
                    break;
                case 't':
                case 'type':
                    handleRectype(value);
                    break;
                case 'all':
                case 'any':
                    handleAnyAll(field_key, value);
                    break;

                default:

                    // key === 'f' || key === 'field' || key === 'fc' || key === 'count'
                    [field, cond, type] = handleDefault(key, parts.join(':'), value);
                    break;
            }

            if(window.hWin.HEURIST4.util.isempty(field) && window.hWin.HEURIST4.util.isempty(cond)){
                continue;
            }

            cond = window.hWin.HEURIST4.util.isempty(cond) ? window.hWin.HEURIST4.query.extractCondition(value, type) : cond;
            if(window.hWin.HEURIST4.util.isempty(cond)){
                continue;
            }

            deconstructed.push(cond.replace('__FIELD__', field));
        }

        if(rty_ID){
            plain_text = `${deconstructed.shift()}${deconstructed.length > 0 ? ', refined by:<br>' : ''}`;
        }else if(!is_sub_query){
            plain_text = `Searching all records${deconstructed.length > 0 ? ', refined by:<br>' : ''}`;
        }

        plain_text += deconstructed.join(', AND <br>');

        return window.hWin.HEURIST4.util.stripTags(plain_text, 'br, em, b, strong, u, i, div');
    },

    extractCondition: function(value, type){

        let res = 'Filter by ';
        let ext = '<em>__FIELD__</em> values';

        if(typeof value !== 'string' && typeof value !== 'number'){
            res = '';
        }

        if(type === 'enum' && (window.hWin.HEURIST4.util.isPositiveInt(value) || value.match(/\d, ?\d/))){
            value = value.split(',');
            value = value.filter((id) => window.hWin.HEURIST4.util.isPositiveInt(id));
            value = value.map((id) => $Db.trm(id, 'trm_Label'));
            value = value.filter((trm) => !window.hWin.HEURIST4.util.isempty(trm)).join(', ');
        }

        let val = '';
        if(value === 'NULL'){
            res += 'records that do not have any <em>__FIELD__</em>';
        }else if(window.hWin.HEURIST4.util.isempty(value)){
            res += 'records that have a <em>__FIELD__</em> value';
        }else if(value.startsWith('=') || value.startsWith('-')){
            val = value.substring(1);
            res += `${ext} that ${value.startsWith('-') ? 'doesn\'t' : ''} extactly match "<em>${val}</em>"`;
        }else if(value.startsWith('@++') || value.startsWith('@--')){
            val = value.substring(3);
            res = `${ext} that contain ${value.startsWith('@++') ? 'all' : 'none'} of the words in "<em>${val}</em>"`;
        }else if(value.startsWith('@')){
            val = value.substring(1);
            res = `${ext} that contain all of the words in "<em>${val}</em>"`;
        }else if(value[0] === '%' || value.endsWith('%')){
            val = value[0] === '%' ? value.substring(1) : value.slice(0, -1);
            res = `${ext} that ${value[0] === '%' ? 'start' : 'end'} with "<em>${val}</em>"`;
        }else if(value.startsWith('<=') || value.startsWith('>=')){
            val = value.substring(2);
            let compare = '';
            if(value.startsWith('<')){
                compare = type == 'date' ? 'before' : 'less than';
            }else{
                compare = type == 'date' ? 'after' : 'greater than';
            }
            res = `${ext} that are ${compare} ${val}`;
        }else if(value.indexOf('<>') > 0 || value.indexOf('><') > 0){
            let compare = '';
            if(value.indexOf('<>') > 0){
                compare = type == 'date' ? 'overlaps within' : 'between';
                value = value.split('<>');
            }else{
                compare = type == 'date' ? 'falls between' : '???';
                value = value.split('><');
            }
            res = `${ext} that ${compare} ${value[0]} and ${value[1]}`;
        }else{
            res = `${ext} that contains "<em>${value}</em>"`;
        }

        return res;
    },

    sortbyValue: function(value, rty_ID){

        let res = '';
        const is_negate = value[0] === '-';
        value = is_negate ? value.substring(1) : value;

        switch(value){
            case 'id':
                res = 'Record ID';
                break;
            case 'url':
                res = 'Record URL';
                break;
            case 'm':
            case 'modified':
                res = 'Last Modified';
                break;
            case 'a':
            case 'added':
                res = 'Created Date';
                break;
            case 't':
            case 'title':
                res = 'Record Title';
                break;
            case 'rt':
            case 'record type':
                res = 'Record Type';
                break;
            case 'r':
            case 'rating':
                res = 'Your Ratings';
                break;
            case 'p':
            case 'popularity':
                res = 'Your Bookmarks';
                break;
            default:
                if(value.startsWith('f:') || value.startsWith('field:')){

                    let parts = value.split(':');
                    parts.shift();
                    value = parts.join(':');

                    res = value;
                    if(window.hWin.HEURIST4.util.isPositiveInt(value)){
                        if(rty_ID>0){
                            res = $Db.rst(rty_ID, value, 'rst_DisplayName');    
                        }else{
                            res = null;
                        }
                        res = res ?? $Db.dty(value, 'dty_Name');
                    }
                }
                break;
        }

        return res;
    }
    
}
}
