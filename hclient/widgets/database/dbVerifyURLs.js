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

    //  
    // invoked from _init after loading of html content
    //
    _initControls:function(){
        // init controls
        
        //check that there is previous session
        
        
        return this._super();
    },

    //
    //
    //
    doAction: function(){
                                                
        let limit = this._$('#selCheckURLsLimit').val();
        
        let request = {limit: limit, verbose:true};

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
        
        if(terminatation_message){

            let error = window.hWin.HEURIST4.util.isObject(terminatation_message)
                        ? terminatation_message
                        : {message: terminatation_message};
            error['error_title'] = window.hWin.HEURIST4.util.isempty(error['error_title']) ? 'Verification terminated' : error['error_title'];

            window.hWin.HEURIST4.msg.showMsgErr(error)
        }
    }
});