/**
* dbAction.js - popup dialog or widget to define action parameters, 
*               send request to server, show progress and final report
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

$.widget( "heurist.dbVerifyURLs", $.heurist.dbAction, {
    
    prevSessionExists: false,

    //  
    // invoked from _init after loading of html content
    //
    _initControls:function(){
        // init controls
        
        //check that there is previous session
        this._checkPreviousSession();
        
        return this._super();
    },
    
    //
    //
    //
    _checkPreviousSession: function(){
        
        this._hideProgress();
        
        let request = {};
        request['action'] = this.options.actionName;       
        request['db'] = window.hWin.HAPI4.database;
        request['checksession'] = 1;       

        let that = this;
        
        window.hWin.HAPI4.SystemMgr.databaseAction( request,  function(response){

                if (response.status == window.hWin.ResponseStatus.OK) {
                    //returns either info about previous session or session id of current operation 
                    if(response.data.session_id>0){
                        //action in progress
                        that._session_id = response.data.session_id;
                        that._showProgress( that._session_id, false, 1000, that._checkPreviousSession );
                        
                    }else if(response.data.total_checked>0){
                        
                        that._$('#prevSessionExist').show();
                        that._$('#prevSessionNotExist').hide();
                        that._$('span.total_checked').text(response.data.total_checked);
                        that._$('span.total_bad').text(response.data.total_bad);

                        let btnCSV = that._$('.btnCSV').button();
                        that._on(btnCSV, {click:that._getPreviousSessionAsCSV});
                        
                        if(response.data.total_bad==0){
                            btnCSV.hide();
                        }else{
                            btnCSV.show();
                        }
                        

                        that._prevSessionExists = true;
                    }else{
                        that._$('#prevSessionExist').hide();
                        that._$('#prevSessionNotExist').show();
                        that._prevSessionExists = false;
                    }
                    
                } else {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
              
        });
    },
    
    //
    //
    //    
    _getPreviousSessionAsCSV: function(){

        let req = {
            'action' : this.options.actionName,
            'getsession': 1,
            'db': window.hWin.HAPI4.database
        };
        
        let url = window.hWin.HAPI4.baseURL + 'hserv/controller/databaseController.php?';
        window.open(url+$.param(req), '_blank');

    },

    //
    //
    //
    doAction: function(){
                                                
        let limit = this._$('#selCheckURLsLimit').val();
        
        const mode = this._prevSessionExists?this._$('input[name="mode"]:checked').val():0;
        
        let request = {limit: limit, verbose:0, mode:mode};

        this._sendRequest(request);        
    },
    
    _showProgress: function ( session_id, is_autohide, t_interval, onComplete ){
      
        this._$('.ent_wrapper').hide();
        let progress_div = this._$('.progressbar_div').show();
        
        window.hWin.HEURIST4.msg.showProgress({container: progress_div,
                        session_id: session_id, t_interval:2000, onComplete:onComplete});  
        
    },
    
    
    _hideProgress: function (){
        
        window.hWin.HEURIST4.msg.hideProgress();
        
        this._$('.ent_wrapper').hide();
        this._$('#div_header').show();
        
    },
    

    
    //  -----------------------------------------------------
    //
    //  after save event handler
    //
    _afterActionEvenHandler: function( response, terminatation_message ){
        
        this._$('.ent_wrapper').hide();
        let div_res = this._$("#div_result").show();
        
        if(response.output){
            div_res.find('#session_summary').html(response.output);
        }
        
        const isFinished = response.session_checked==0;
        let that = this;
        
        const types = ['record','text','file'];
        types.forEach(function(key) {
            
            that._$(`span.session_processed_${key}`).text(response[`session_processed_${key}`]);
            //that._$('span.session_bad_text').text(response.session_bad_text);
            const total_bad = response[`${key}_bad`];
            let ele_total_bad = that._$(`span.${key}_bad`);
            ele_total_bad.text(total_bad);
            ele_total_bad.css('color','red');
            if(total_bad>0){
               const ids = Object.keys(response[key]).join(',')
               const url = window.hWin.HAPI4.baseURL+'?db='+window.hWin.HAPI4.database+'&q=ids:'+ids;
               that._$(`span.links_${key}`).html('<a href="'+url+'" target="_blank" style="padding-left:10px;font-size:0.8em">show records as search  <span class="ui-icon ui-icon-linkext"></span></a>');
            }else if(isFinished){
                ele_total_bad.text('OK').css('color','green');
            } 
            
        });  

        this._$('span.total_checked').text(response.total_checked);
        this._$('span.total_bad').text(isFinished && response.total_bad==0?'OK':response.total_bad);
        this._$('span.total_bad').css('color',isFinished && response.total_bad==0?'green':'red');
        
        if(isFinished){ //check has been completed
            this._$('#all_urls_verified').show();
            if(response.total_bad==0){
                this._$('#all_urls_ok').show();
            }else{
                this._$('#all_urls_ok').hide();
            }
            this._$('button.ui-button-action').hide();
        }else{
            this._$('#all_urls_verified').hide();
            this._$('button.ui-button-action').show();
        }
        
        if(response.total_bad==0){
            div_res.find('button.btnCSV').hide();
        }else{
            div_res.find('button.btnCSV').show();
            this._on(div_res.find('.btnCSV').button(), {click:this._getPreviousSessionAsCSV});
        }
        
        this._prevSessionExists = false; //to active mode "continue"
        
        if(terminatation_message){

            terminatation_message = window.hWin.HEURIST4.util.isObject(terminatation_message)
                        ? terminatation_message.message
                        : terminatation_message;
            //error['error_title'] = window.hWin.HEURIST4.util.isempty(error['error_title']) ? 'Verification terminated' : error['error_title'];
            //window.hWin.HEURIST4.msg.showMsgErr(error)
            
            $(`<h3>${terminatation_message}</h3>`).appendTo(div_res.find('#session_summary'));
        }
    }
});