/**
* recordImportAnnotations.js - import annotations from registered IIIF manifests
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

$.widget( "heurist.recordImportAnnotations", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 780,
        width:  800,
        modal:  true,
        title:  'Import annotations from registered IIIF manifests',
        
        htmlContent: 'recordImportAnnotations'
    },
    
    _init: function() {
        this.options.htmlContent = this.options.htmlContent
                    +(window.hWin.HAPI4.getLocale()=='FRE'?'_fre':'')+'.html';
        this._super();    
    },
    
    _initControls:function(){
        
        this._$('.btnAction').button();
        
        this._on(this._$('.btnAction'), {click:this.doAction});
        
        return this._super();
    },
    
    
    //    
    //
    //
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Proceed');
        res[0].text = window.hWin.HR('Close');
        return res;
    },    
    
    //
    //
    //
    _renderReport: function(data)
    {
        this._$('#div_header').hide();
        this._$('#div_result').show();
        
        this._$('#total').text( data['total'] );
        this._$('#processed').text( data['processed'] );
        this._$('#missed').text( data['missed'] );
        
        let link = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&q=ids:';
        
        let ids = [];
        let s = ' ';
        
        for (const ulfID in data['without_annotations']) {
           ids.push(data['without_annotations'][ulfID]); 
        }
        if(ids.length>0){
           s = `<a href="${link+ids.join(',')}" target="_blank">${ids.length} <span class="ui-icon ui-icon-extlink">&nbsp;</span></a><br>`;
        }
        this._$('#without_annotations').html( s);

        
        if(data['added'].length>0){
            this._$('#added').html( `<a href="${link+data['added'].join(',')}" target="_blank">${data['added'].length}</a>` );
        }else{
            this._$('#added').text( '0' );
        }
        
        if(data['updated'].length>0){
            this._$('#updated').html( `<a href="${link+data['updated'].join(',')}" target="_blank">${data['updated'].length}</a>` );
        }else{
            this._$('#updated').text( '0' );
        }

        if(data['retained'].length>0){
            this._$('#retained').html( `<a href="${link+data['retained'].join(',')}" target="_blank">${data['retained'].length}</a>` );
        }else{
            this._$('#retained').text( '0' );
        }
        
        s = ' ';
        ids = [];
        for (const recID in data['issues']) {
           s = s +  `<a href="${link+recID}" target="_blank">${data['issues'][recID]}</a><br>`;
           ids.push(recID);
        }
        if(ids.length>1){
           s = s + `<a href="${link+ids.join(',')}" target="_blank">all issues ( ${ids.length} ) <span class="ui-icon ui-icon-extlink">&nbsp;</span></a><br>`;
        }
        
        this._$('#issues').html( s );
        
    },
        
    //
    // 
    //
    doAction: function(){

            let request = {
                db: window.hWin.HAPI4.database,
                controller: 'ImportAnnotations',
                session  : window.hWin.HEURIST4.msg.showProgress(),
                direct_link: this._$('#chb_direct_link').is(':checked')?1:0,
                create_thumb: this._$('#chb_create_thumbs').is(':checked')?1:0
            };
            
            let url = window.hWin.HAPI4.baseURL;
            
            let that = this;

            window.hWin.HEURIST4.util.sendRequest(url, request, null, function(response){
                
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                window.hWin.HEURIST4.msg.hideProgress();
                
                //render groups
                if(response.status == window.hWin.ResponseStatus.OK){
                    that._renderReport( response.data );
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);    
                }
            });
        
                           
            
    }
  
});

