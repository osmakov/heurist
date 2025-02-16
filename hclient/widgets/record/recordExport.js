/**
* recordExport.js - export to XML,JSON or GEPHI
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

$.widget( "heurist.recordExport", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 780,
        width:  800,
        modal:  true,
        title:  'Export records to ',
        
        format: 'xml',
        
        htmlContent: 'recordExport.html'
    },

    _initControls: function() {

        this._super();    
                    
        this.options.title += (' '+ this.options.format.toUpperCase());     

        if(!this.options.isdialog){
            
            //add action button to bottom bar
            let fele = this._$('.ent_wrapper:first');
            fele.css({top:'36px',bottom:'40px'});
            $('<div class="ui-heurist-header">'+this.options.title+'</div>').insertBefore(fele);    

            let toolbar_height = '20px';
            if(navigator.userAgent.indexOf('Firefox') >= 0){
                toolbar_height = '40px';
            }
            this.toolbar = $('<div class="ent_footer button-toolbar ui-heurist-header" style="height:'+ toolbar_height +'"></div>').insertAfter(fele);

            //append action buttons
            this.toolbar.empty();
            this._$('.kml-buttons').empty();
            let btns = this._getActionButtons();

            for(let idx in btns){
                
                let $cont = this.toolbar;
                if(this.options.format=='kml'){
                    $cont = this._$('.kml-buttons');
                }else if (this.options.format=='iiif'){
                    $cont = this._$('.iiif-buttons');
                }
                
                this._defineActionButton2(btns[idx], $cont);
            }
        }
        
        this.selectRecordScope.val('current');
        this.selectRecordScope.parent().hide();
        
        if(this.options.format=='kml'){
            this._$('.ent_content').hide();
            this._$('.kml-info').show();
        }else if(this.options.format=='iiif'){
            this._$('.ent_content').hide();
            this._$('.iiif-info').show();
        }
        

        return true;
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
    // 
    //
    doAction: function(){

            let scope_val = this.selectRecordScope.val();
            
            scope_val = 'current';
           
            let q;
            let isEntireDb = false;
            let scope = [], //ids to be exported
            rec_RecTypeID = 0;
            
            if(scope_val == 'selected'){
                scope = this._currentRecordsetSelIds;

                q = '?w=all&q=ids:'+scope.join(',');
                
            }else { //(scope_val == 'current'
                scope = this._currentRecordset.getIds();
                if(scope_val  >0 ){
                    rec_RecTypeID = scope_val;
                }else{
                    isEntireDb = (scope.length==window.hWin.HAPI4.sysinfo.db_total_records);
                }   
                
                //'+(rec_RecTypeID>0?('t:'+rec_RecTypeID+' '):'')+'
                
                q = window.hWin.HEURIST4.query.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, true);
                
            }
            
            if(scope.length<1){ //IDS
                window.hWin.HEURIST4.msg.showMsgFlash('No results found. '
                +'Please modify search/filter to return at least one result record.', 2000);
                return;
            }
            
            let request = {
                //'request_id' : window.hWin.HEURIST4.util.random(),
                'db': window.hWin.HAPI4.database,
                'format': this.options.format,
                'a': 1,
                'depth': isEntireDb?0:'all'};

            if(!isEntireDb){                
                
                let linksMode = this._$('input[name="links"]:checked').val();
                request['linkmode'] = linksMode; 
                
                if(rec_RecTypeID>0){
                    request['rec_RecTypeID'] = rec_RecTypeID;
                }
            }
           
            
            let url = window.hWin.HAPI4.baseURL;
                           
            if(this.options.format=='kml'){
                url += 'export/xml/kml.php';
            }else if(this.options.format=='hml' || this.options.format=='xml'){
                url += 'export/xml/flathml.php';
            }else{
                request['extended'] = 1;
                request['defs'] = 0; //don't include defintions
                url += 'hserv/controller/record_output.php';
            }

            const open_in_popup  = false;
            if(open_in_popup){
                request['ids'] = scope;
                
                //posting via form allows send large list of ids
                this._$('#postdata').val( JSON.stringify(request) );
                this._$('#postform').attr('action', url);
                this._$('#postform').trigger('submit');
            }else{
                
                url = url + q;
                for(let key in request){
                    url += ('&' + key + '=' + request[key]);
                }
                
                window.open(url, '_blank');
            }
            
            
    }
  
});

