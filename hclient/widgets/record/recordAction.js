/**
* recordAction.js - BASE widget for actions for scope of records
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

$.widget( "heurist.recordAction", $.heurist.baseAction, {

    // default options
    options: {
        default_palette_class: 'ui-heurist-explore', 
        path: 'widgets/record/',
        
        //parameters
        scope_types: null, // [all, selected, collected, current, rectype ids, none]
        init_scope: '',    // inital selection
        currentRecordset: null,
        htmlContent: 'recordAction.html'
    },  
      
    _currentRecordset:null,
    _currentRecordsetSelIds:null,
    _currentRecordsetColIds: null,
    
    _progressInterval:null,
    
    //selector control for scope of records to be treated
    selectRecordScope:null,
    
    //
    //  load configuration and call _initControls
    //
    _init: function() {
        
        if(this.options.currentRecordset){  //take recordset from options
            this._currentRecordset  = this.options.currentRecordset;
            this._currentRecordsetSelIds = null;
            this._currentRecordsetColIds = null;
        }else if(window.hWin.HAPI4.currentRecordset){ //take global recordset
            this._currentRecordset = window.hWin.HAPI4.currentRecordset;
            this._currentRecordsetSelIds = window.hWin.HAPI4.currentRecordsetSelection;
            this._currentRecordsetColIds = window.hWin.HAPI4.currentRecordsetCollected;
        }else{
            //Testing
            this._currentRecordset = new HRecordSet({count: "0",offset: 0,reccount: 0,records: [], rectypes:[]});
            this._currentRecordsetSelIds = null;
            this._currentRecordsetColIds = null;
        }
        
        this._super();
    },
    
     
    //  
    // invoked from _init after loading of html content
    //
    _initControls:function(){
        
        this._$('label[for="sel_record_scope"]').text(window.hWin.HR('recordAction_select_lbl'));
        
        this.selectRecordScope = this._$('#sel_record_scope');
        if(this.selectRecordScope.length>0 && this._fillSelectRecordScope()===false){
            this.closeDialog();                
            return false;
        }

        return this._super();
    },

    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements
        if(this.selectRecordScope) this.selectRecordScope.remove();
    },


    //  -----------------------------------------------------
    //
    //
    //
    _fillSelectRecordScope: function (){

        let scope_types = this.options.scope_types;
        this.selectRecordScope.empty();
        
        if(scope_types=='none'){
            this.selectRecordScope.parent().hide();
            return;    
        }

        let selScope = this.selectRecordScope.get(0);

        window.hWin.HEURIST4.ui.addoption(selScope,'',window.hWin.HR('recordAction_select_hint'));
        
        let is_initscope_empty = window.hWin.HEURIST4.util.isempty(scope_types);
        if(is_initscope_empty) scope_types = [];   
        
        if(scope_types.indexOf('all')>=0){
            window.hWin.HEURIST4.ui.addoption(selScope,'all',window.hWin.HR('All records'));
        }
        
        if ((is_initscope_empty || scope_types.indexOf('selected')>=0)
            && (this._currentRecordsetSelIds &&  this._currentRecordsetSelIds.length > 0)){
                
            window.hWin.HEURIST4.ui.addoption(selScope,'selected',
                window.hWin.HR('Selected results set (count=') + this._currentRecordsetSelIds.length+')');
        }

        if ((is_initscope_empty || scope_types.indexOf('collected')>=0)
            && (this._currentRecordsetColIds &&  this._currentRecordsetColIds.length > 0)){
                
            window.hWin.HEURIST4.ui.addoption(selScope,'selected',
                window.hWin.HR('Collected results set (count=') + this._currentRecordsetColIds.length+')');
        }
        
        if ((is_initscope_empty || scope_types.indexOf('current')>=0)
            && (this._currentRecordset &&  this._currentRecordset.length() > 0)){
                
            window.hWin.HEURIST4.ui.addoption(selScope,'current',
                window.hWin.HR('Current results set (count=') + this._currentRecordset.length()+')');
        }

        let rectype_Ids = [];
        if (!is_initscope_empty){
            for (let rty in scope_types)
            if(rty>=0 && scope_types[rty]>0 && $Db.rty(scope_types[rty],'rty_Name')){ 
                rectype_Ids.push(scope_types[rty]);
            }
        }else if(this._currentRecordset &&  this._currentRecordset.length() > 0){
            rectype_Ids = this._currentRecordset.getRectypes();
        }
        
        rectype_Ids.forEach(rty => {
                let name = $Db.rty(rty,'rty_Plural');
                if(!name) name = $Db.rty(rty,'rty_Name');
                
                window.hWin.HEURIST4.ui.addoption(selScope,rty,window.hWin.HR('only:')+' '+name);
        });

        this._on( this.selectRecordScope, {
                change: this._onRecordScopeChange} );        
        this.selectRecordScope.val(this.options.init_scope);    
        if(selScope.selectedIndex<0) selScope.selectedIndex=0;
        this._onRecordScopeChange();
    },

    //
    //
    //    
    _onRecordScopeChange: function () 
    {
        let isdisabled = (this.selectRecordScope.val()=='');
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('.btnDoAction'), isdisabled );
        
        return isdisabled;
    },
    
    
    //   @todo use msg.showProgress
    //
    // Requests reportProgress every t_interval ms 
    // is_autohide 
    //    true  - stops progress check if it returns null/empty value
    //    false - it shows rotating(loading) image for null values and progress bar for n,count values
    //                  in latter case _hideProgress should be called explicitely
    //
    _showProgress: function ( session_id, is_autohide, t_interval ){

        if(!(session_id>0)) {
             this._hideProgress();
             return;
        }
        let that = this;
       
        let progress_url = window.hWin.HAPI4.baseURL + "hserv/controller/progress.php";

        this._$('#div_fieldset').hide();
        this._$('.ent_wrapper').hide();
        let progress_div = this._$('.progressbar_div').show();
        $('body').css('cursor','progress');
        let btn_stop = progress_div.find('.progress_stop').button({label:window.hWin.HR('Abort')});
        
        this._on(btn_stop,{click: function() {
            
                let request = {terminate:1, t:(new Date()).getMilliseconds(), session:session_id};
                window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
                    that._hideProgress();
                });
            }});
        
        let div_loading = progress_div.find('.loading').show();
        let pbar = progress_div.find('#progressbar');
        let progressLabel = pbar.find('.progress-label').text('');
        pbar.progressbar({value:0});
        
        this._progressInterval = setInterval(function(){ 
            
            let request = {t:(new Date()).getMilliseconds(), session:session_id};            
            
            window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
               
                if(response && response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                    that._hideProgress();
                }else{
                    //it may return terminate,done,
                    let resp = response?response.split(','):[];
                    if(response=='terminate' || resp.length!=2){
                        if(response=='terminate' || is_autohide){
                            that._hideProgress();
                        }else{
                            div_loading.show();    
                           
                           
                        }
                    }else{
                        div_loading.hide();
                        if(resp[0]>0 && resp[1]>0){
                            let val = resp[0]*100/resp[1];
                            pbar.progressbar( "value", val );
                            progressLabel.text(resp[0]+' of '+resp[1]);
                        }else{
                            progressLabel.text(window.hWin.HR('preparing')+'...');
                            pbar.progressbar( "value", 0 );
                        }
                    }
                    
                }
            },'text');
          
        
        }, t_interval);                
        
    },
    
    _hideProgress: function (){
        
        $('body').css('cursor','auto');
        
        if(this._progressInterval!=null){
            
            clearInterval(this._progressInterval);
            this._progressInterval = null;
        }
        this._$('.progressbar_div').hide();
        this._$('#div_fieldset').show();
        
    },
    
  
});

