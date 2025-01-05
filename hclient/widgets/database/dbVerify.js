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

$.widget( "heurist.dbVerify", $.heurist.dbAction, {

    _verification_actions: {
            dup_terms:{name:'Invalid/Duplicate Terms'},           
            field_type:{name:'Field Types'},
            default_values:{name:'Default Values'},
            defgroups:{name:'Definitions Groups'},
            title_mask:{name:'Title Masks'},
            
            owner_ref:{name:'Record Owner/Creator'},
            pointer_targets:{name:'Pointer Targets'},
            target_parent:{name:'Invalid Parents'},
            empty_fields:{name:'Empty Fields'},
            nonstandard_fields:{name:'Non-Standard Fields'},
            
            dateindex:{name:'Date Index'},
            multi_swf_values:{name:'Multiple Workflow Stages'},
            
            geo_values:{name:'Geo Values'},
            term_values:{name:'Term Values'},
            expected_terms:{name:'Expected Terms'},
            
            target_types:{name:'Target Types', slow:1},
            required_fields:{name:'Required Fields', slow:1},
            single_value:{name:'Single Value Fields', slow:1},
            relationship_cache:{name:'Relationship Cache', slow:1},
            date_values:{name:'Date Values', slow:1},
            fld_spacing:{name:'Spaces in Values', slow:1},
            invalid_chars:{name:'Invalid Characters', slow:1}
            
    },
        
    //  
    // invoked from _init after loading of html content
    //
    _initControls:function(){
        this._initVerification();
        return this._super();
    },

    //
    //
    //
    doAction: function(){
        
        let request;

        let actions=[];
        let cont_steps = this._$('.progressbar_div > .loading > ol');
        cont_steps.empty();
        
        this._$('.verify-actions:checked').each((i, item)=>{
            let action = item.value
            actions.push(action);
            $('<li>'+this._verification_actions[action].name+'</li>').appendTo(cont_steps);
        });
        
        let btn_stop = $('<button class="ui-button-action" style="margin-top:10px">Terminate</button>').appendTo(cont_steps);
        btn_stop.button();
        this._on(btn_stop,{click:function(){
            let progress_url = window.hWin.HAPI4.baseURL + "hserv/controller/progress.php";
            let request = {terminate:1, t:(new Date()).getMilliseconds(), session:this._session_id};
            let that = this;
            window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
                that._session_id = 0;
                that._hideProgress();
            });
            
            
        }});
        
        request = {checks: actions.length==Object.keys(this._verification_actions).length?'all':actions.join(',')};

        this._sendRequest(request);        
    },

    
    //  -----------------------------------------------------
    //
    //  after save event handler
    //
    _afterActionEvenHandler: function( response, terminatation_message ){
        
        this._$('.ent_wrapper').hide();
        let div_res = this._$("#div_result").show();
        
        this._initVerificationResponse(response);
        
        if(terminatation_message){

            let error = window.hWin.HEURIST4.util.isObject(terminatation_message)
                        ? terminatation_message
                        : {message: terminatation_message};
            error['error_title'] = window.hWin.HEURIST4.util.isempty(error['error_title']) ? 'Verification terminated' : error['error_title'];

            window.hWin.HEURIST4.msg.showMsgErr(error)
        }
    },

    //
    //
    //
    _initVerification: function(){
        
        let cont1 = this._$('#actions');
        let cont2 = this._$('#actions_slow');
        
        for (const action in this._verification_actions){
           let is_slow = (this._verification_actions[action].slow==1); 
           let cont = (is_slow)?cont2:cont1;
           $('<li><label><input type="checkbox" '+(is_slow?'data-slow="1"':'checked')+' class="verify-actions" value="'+action+'">'
                +this._verification_actions[action].name+'</label></li>').appendTo(cont);
        } 

        //
        // Mark all checkbox
        //
        this._on(this._$('input[data-mark-actions]'),{click:(event)=>{
            let is_checked = $(event.target).is(':checked');
            this._$('input.verify-actions[data-slow!=1]').prop('checked',is_checked);
        }});

                
        this._$("#div_result").css('overflow-y','auto');
        
        if(window.hWin.HAPI4.sysinfo.db_total_records>100000){
            $('#notice_for_large_database').show();
        }
        
        //very slow reports in popup
        this._$('div.slow-checks-in-popup > button').button();
        this._on(this._$('div.slow-checks-in-popup > button'),{click:(event)=>{
            
                let type = $(event.target).attr('data-type');
                if(type != 'files' && type != 'urls') { return; }
            
                let body = $(window.hWin.document).find('body');

                let screen_height = window && window.innerHeight && window.innerHeight > body.innerHeight() ? 
                                    window.innerHeight : body.innerHeight();

                let opts = {height:screen_height*0.8, width:body.innerWidth()*0.8};

                window.hWin.HEURIST4.msg.showDialog(
                    `${window.hWin.HAPI4.baseURL}admin/verification/longOperationInit.php?type=${type}&db=${window.hWin.HAPI4.database}`
                    , opts);                
        }});
        
        this._$('#btnVerifyURLs').button();
        this._on(this._$('#btnVerifyURLs'),{click:(event)=>{
                window.hWin.HAPI4.actionHandler.executeActionById('menu-database-verifyURLs');
        }});
        
    },
    
    //
    //
    //
    _initVerificationResponse: function(response){
        
            this._session_id = 0;
    
            let div_res = this._$("#div_result");
            let is_reload = false;
            
            if(response['reload']){
                is_reload = response['reload'];
                delete response['reload'];
            }
            
            if(is_reload){
                
                let action = Object.keys(response)[0];
                let res = response[action];
                
                div_res.find('a[href="#'+action+'"]').parent()
                    .css("background-color", res['status']?'#6AA84F':'#E60000');                
                div_res.find('#'+action).empty().append($(res['message']));
                
                div_res.find('#linkbar').tabs('refresh');
                
            }else{            
            
                div_res.empty();
                
                let tabs = $('<div id="linkbar" style="margin:5px;"><ul id="links"></ul></div>').appendTo(div_res);
                
                let tab_header = div_res.find('#links');

                for (const [action, res] of Object.entries(response)) {
                    // add tab header
                    $('<li style="background-color:'+(res['status']?'#6AA84F':'#E60000')+'"><a href="#'+action
                        +'" style="white-space:nowrap;padding-right:10px;color:black;">'
                        + this._verification_actions[action].name +'</a></li>')
                        .appendTo(tab_header);
                    // add content
                    $('<div id="'+action+'" style="top:110px;padding:5px !important">'+res['message']+'</div>').appendTo(tabs);
                }
                tabs.tabs();
            
            }
            
            //
            // FIX button
            //
            this._on(this._$('button[data-fix]').button(),{click:(event)=>{
            
                let action = $(event.target).attr('data-fix');
                
                let request = {checks: action, fix:1, reload:1};
                
                let marker = $(event.target).attr('data-selected');
                let sel_ids = [];
                
                if(marker){
                    let sels = this._$('input[name="'+marker+'"]:checked');

                    sels.each((i,item)=>{
                        sel_ids.push(item.value);
                    });
                    if(sel_ids.length==0){
                        window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Select one record at least'));
                        return;
                    }else{
                        request['recids'] = sel_ids.join(',');
                    }
                }
                
                let cont_steps = this._$('.progressbar_div > .loading > ol');
                cont_steps.empty();
                $('<li>'+this._verification_actions[action].name+'</li>').appendTo(cont_steps);
                
                this._sendRequest(request);
            }});
            
            //
            // Mark all checkbox
            //
            this._on(this._$('input[data-mark-all]'),{click:(event)=>{
                
                let ele = $(event.target)
                let name = ele.attr('data-mark-all');
                let is_checked = ele.is(':checked');
                
                this._$('input[name="'+name+'"]').prop('checked',is_checked);
            }});

            //
            // Show selected link
            //
            this._on(this._$('a[data-show-selected]'),{click:(event)=>{
                
                let name = $(event.target).attr('data-show-selected');
                let sels = this._$('input[name="'+name+'"]:checked');
                let ids = [];

                sels.each((i,item)=>{
                    ids.push(item.value);
                });
                
                if(ids.length>0){
                    ids = ids.join(',');
                    window.open( window.hWin.HAPI4.baseURL_pro+'?db='
                                +window.hWin.HAPI4.database+'&w=all&q=ids:'+ids, '_blank' );
                }
                
                return false;
            }});

            //
            // Show All link
            //
            this._on(this._$('a[data-show-all]'),{click:(event)=>{
                
                let name = $(event.target).attr('data-show-all');
                let sels = this._$('input[name="'+name+'"]');
                let ids = [];

                sels.each((i,item)=>{
                    ids.push(item.value);
                });
                
                if(ids.length>0){
                    ids = ids.join(',');
                    //window.hWin.HEURIST4.util.windowOpenInPost(window.hWin.HAPI4.baseURL, '_blank', null,
                   
                    window.open( window.hWin.HAPI4.baseURL_pro+'?db='
                                +window.hWin.HAPI4.database+'&w=all&q=ids:'+ids, '_blank' );
                }
                
                return false;
            }});

            
    }            


});