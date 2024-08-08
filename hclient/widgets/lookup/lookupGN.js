/**
* lookupGN.js - GeoNames lookup service
* 
* This file:
*   1) Loads the content of the corresponding html file (lookupGN_postakCode.html)
*   2) Performs an api call to the Geoname service using the User's input, displaying the results within a Heurist result list
*   3) map external results with our field details (see options.mapping) and returns the mapped results to the record edit form
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

/* global accessToken_GeonamesAPI */

$.widget( "heurist.lookupGN", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 520,
        width:  800,
        modal:  true,
        
        title:  'Lookup values Postal codes for Heurist record',
        
        htmlContent: 'lookupGN.html',
        //helpContent: 'lookupGN.html', //in context_help folder
        
        mapping:null, //configuration from sys_ExternalReferenceLookups
               
        add_new_record: false  //if true it creates new record on selection
        //define onClose to get selected values
    },
    
    recordList:null,
    _country_vocab_id: 0,

    tabs_container: null,

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        let that = this;

        //fill countries dropdown
        let ele = this.element.find('#inpt_country');
        this._country_vocab_id = $Db.getLocalID('trm','2-509');
        if(this._country_vocab_id > 0){
            window.hWin.HEURIST4.ui.createTermSelect(ele.get(0), {vocab_id:this._country_vocab_id,topOptions:'select...',useHtmlSelect:false});
        }

        if(ele.hSelect('instance') != 'undefined'){
            ele.hSelect('widget').css({'max-width':'30em'});
        }
        
        this.options.resultList = $.extend(this.options.resultList, 
        {
               recordDivEvenClass: 'recordDiv_blue',
               eventbased: false,  //do not listent global events

               multiselect: false, //(this.options.select_mode!='select_single'), 

               select_mode: 'select_single', //this.options.select_mode,
               selectbutton_label: 'select!!', //this.options.selectbutton_label, for multiselect
               
               view_mode: 'list',
               show_viewmode:false,
               
               entityName: this._entityName,
               //view_mode: this.options.view_mode?this.options.view_mode:null,
               
               pagesize:(this.options.pagesize>0) ?this.options.pagesize: 9999999999999,
               empty_remark: '<div style="padding:1em 0 1em 0">No Locations Found</div>',
               renderer: this._rendererResultList      
        });                

        //init record list
        this.recordList = this.element.find('#div_result');
        this.recordList.resultList( this.options.resultList );     
        
        this._on( this.recordList, {        
                "resultlistonselect": function(event, selected_recs){
                            window.hWin.HEURIST4.util.setDisabled( 
                                this.element.parents('.ui-dialog').find('#btnDoAction'), 
                                (selected_recs && selected_recs.length()!=1));
                        },
                "resultlistondblclick": function(event, selected_recs){
                            if(selected_recs && selected_recs.length()==1){
                                this.doAction();                                
                            }
                        }
                //,"resultlistonaction": this._onActionListener        
                });
        
        
        
        this._on(this.element.find('#btnStartSearch').button(),{
            'click':this._doSearch
        });
        
        this._on(this.element.find('input'),{
            'keypress':this.startSearchOnEnterPress
        });
        
        this.tabs_container = this.element.find('#tabs-cont').tabs();
        
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
        
        function fld(fldname, width){

            let s = recordset.fld(record, fldname);
            s = window.hWin.HEURIST4.util.htmlEscape(s?s:'');

            let title = s;

            if(fldname == 'recordLink'){
                s = '<a href="' + s + '" target="_blank"> view here </a>';
                title = 'View geoname record';
            }

            if(width>0){
                s = '<div style="display:inline-block;width:'+width+'ex" class="truncate" title="'+title+'">'+s+'</div>';
            }
            return s;
        }

        let recID = fld('rec_ID');
        let rectypeID = fld('rec_RecTypeID'); 

        let recTitle = fld('name', 40) + fld('adminName1', 20) + fld('countryCode', 6) + fld('fcodeName', 40) + fld('fclName', 20) + fld('recordLink', 12);

        let recIcon = window.hWin.HAPI4.iconBaseURL + rectypeID;

        let html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'
                + window.hWin.HAPI4.iconBaseURL + rectypeID + '&version=thumb&quot;);"></div>';

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
        //res[1].disabled = null;
        return res;
    },

    /**
     * Return record field values in the form of a json array mapped as [dty_ID: value, ...]
     * For multi-values, [dty_ID: [value1, value2, ...], ...]
     * 
     * To trigger record pointer selection/creation popup, value must equal [dty_ID, default_searching_value]
     * 
     * Include a url to an external record that will appear in the record pointer guiding popup, add 'ext_url' to res
     *  the value must be the complete html (i.e. anchor tag with href and target attributes set)
     *  e.g. res['ext_url'] = '<a href="www.example.com" target="_blank">Link to Example</a>'
     * 
     * Param: None
     */
    doAction: function(){

        //detect selection
        let recset = this.recordList.resultList('getSelected', false);
        
        if(recset && recset.length() == 1){
            
            let res = {};
            let rec = recset.getFirstRecord();
            
            let map_flds = Object.keys(this.options.mapping.fields);
            
            for(let k=0; k<map_flds.length; k++){
                let dty_ID = this.options.mapping.fields[map_flds[k]];
                let val = recset.fld(rec, map_flds[k]);
                
                if(map_flds[k]=='countryCode' && this._country_vocab_id>0){
                    val = $Db.getTermByCode(this._country_vocab_id, val);
                }
                
                if(dty_ID>0 && val){
                    res[dty_ID] = val;    
                }
            }

            //pass mapped values and close dialog
            this._context_on_close = res;
            this._as_dialog.dialog('close');
        }        
    },
    
    /**
     * Create search URL using user input within form
     * Perform server call and handle response
     * 
     * Params: None
     */
    _doSearch: function(){
        
        if(this.element.find('#inpt_query').val()=='' && this.element.find('#inpt_id').val()==''){
            window.hWin.HEURIST4.msg.showMsgFlash('Please enter a geoname id or a search term to perform a search', 1000);
            return;
        }

        let sURL = 'http://api.geonames.org/';
        let xml_response = 0;

        if(this.element.find('#inpt_id').val()!=''){
            sURL += 'get?geonameId=' + encodeURIComponent(this.element.find('#inpt_id').val());
            xml_response = 1;
        }else{

            sURL += 'searchJSON?';

            if(this.element.find('#inpt_query').val()!=''){
                sURL += '&q=' + encodeURIComponent(this.element.find('#inpt_query').val());
            }
            if(this.element.find('#inpt_country').val()!=''){
    
                let term_label = $Db.trm(this.element.find('#inpt_country').val(), 'trm_Label');
                let _countryCode = $Db.trm(this.element.find('#inpt_country').val(), 'trm_Code');
    
                if(_countryCode == ''){
                    
                    switch (term_label) {
                        case 'Iran':
                            _countryCode = 'IR';
                            break;
                        case 'Kyrgistan': // Kyrgzstan
                            _countryCode = 'KG';
                            break;
                        case 'Syria':
                            _countryCode = 'SY';
                            break;
                        case 'Taiwan':
                            _countryCode = 'TW';
                            break;
                        case 'UAE':
                            _countryCode = 'AE';
                            break;
                        case 'UK':
                            _countryCode = 'GB';
                            break;
                        case 'USA':
                            _countryCode = 'US';
                            break;
                        case 'Vietnam':
                            _countryCode = 'VN';
                            break;
                        default:
                            break;
                    }
                }
    
                if(_countryCode != ''){
                    sURL += '&country=' + _countryCode; 
                }
            }
        }
        sURL += ('&username='+accessToken_GeonamesAPI);

        window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parent());

        let that = this;
        let request = {service:sURL, serviceType:'geonames', is_XML: xml_response};             
        //loading as geojson  - see controller record_lookup.php
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request,
            function(response){
                window.hWin.HEURIST4.msg.sendCoverallToBack();

                if(response){
                    if(response.status && response.status != window.hWin.ResponseStatus.OK){
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }else{
                        that._onSearchResult(response);
                    }
                }
            }
        );
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

        json_data = window.hWin.HEURIST4.util.isJSON(json_data);

        if (json_data) {

            let res_records = {}, res_orders = [];

            let DT_GEO_OBJECT = window.hWin.HAPI4.sysinfo['dbconst']['DT_GEO_OBJECT'];
            if(DT_GEO_OBJECT>0 && !this.options.mapping.fields['location']){
                this.options.mapping.fields['location'] = DT_GEO_OBJECT;
            }

            let fields = ['rec_ID', 'rec_RecTypeID'];
            let map_flds = Object.keys(this.options.mapping.fields);

            fields = fields.concat(map_flds);
            fields = fields.concat('recordLink');

            if(!json_data.geonames) json_data.geonames = json_data;
            
            //parse json
            let i=0;
            let data = json_data.geonames;

            if(!Array.isArray(data)){
                data = [data];
            }

            for(;i<data.length;i++){
                let feature = data[i];
                
                let recID = i+1;
                
                let val;
                let values = [recID, this.options.mapping.rty_ID];
                
                for(let k=0; k<map_flds.length; k++){
                    
                    if(map_flds[k]=='location'){
                        if(feature[ 'lng' ] && feature[ 'lat' ]){
                            val = 'p POINT('+feature[ 'lng' ]+' '+feature[ 'lat' ]+')';
                        }else{
                            val = '';
                        }
                    }else{
                        val = feature[ map_flds[k] ];
                    }
                        
                    values.push(val);    
                }

                // Push additional information, GeoName: www.geonames.org/geoname_rec_id/
                values.push('https://www.geonames.org/' + feature['geonameId'] + '/');

                res_orders.push(recID);
                res_records[recID] = values;
            }

            if(res_orders.length>0){        
                let res_recordset = new HRecordSet({
                    count: res_orders.length,
                    offset: 0,
                    fields: fields,
                    rectypes: [this.options.mapping.rty_ID],
                    records: res_records,
                    order: res_orders,
                    mapenabled: true //???
                });              
                
                this.recordList.resultList('updateResultSet', res_recordset);            
                is_wrong_data = false;
            }
        }
       
        if(is_wrong_data){

            this.recordList.resultList('updateResultSet', null);
            window.hWin.HEURIST4.msg.showMsgErr('Service did not return data in an appropriate format');
        }else{
            this.tabs_container.tabs('option', 'active', 1); // switch to results tab
        }
    }
});