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
                        
                    }else if(response.data.total_checked>0){
                        
                        that._$('#prevSessionExist').show();
                        that._$('#prevSessionNotExist').hide();
                        that._$('#total_checked').text(response.data.total_checked);
                        that._$('#total_bad').text(response.data.total_bad);

                        that._on(that._$('#btnCSV').button(), {click:that._getPreviousSessionAsCSV});

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
        
        let request = {limit: limit, verbose:1, mode:mode};

        this._sendRequest(request);        
    },
    
    _showProgress: function ( session_id, is_autohide, t_interval ){
      
        this._$('.ent_wrapper').hide();
        let progress_div = this._$('.progressbar_div').show();
        
        window.hWin.HEURIST4.msg.showProgress({container: progress_div,
                        session_id: session_id, t_interval:2000});  
        
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
        
        div_res.html(response?.output);
        
        let btnCsv = $('<button id="btnCSV">Download Bad URLs as CSV</button>');
        btnCsv.button().appendTo(div_res);
        
        this._on(btnCsv, {click:this._getPreviousSessionAsCSV});
        
        if(terminatation_message){

            let error = window.hWin.HEURIST4.util.isObject(terminatation_message)
                        ? terminatation_message
                        : {message: terminatation_message};
            error['error_title'] = window.hWin.HEURIST4.util.isempty(error['error_title']) ? 'Verification terminated' : error['error_title'];

            window.hWin.HEURIST4.msg.showMsgErr(error)
        }
    }
});