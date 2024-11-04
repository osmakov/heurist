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

$.widget( "heurist.dbAction", $.heurist.baseAction, {

    // default options
    options: {
        actionName: '',
        default_palette_class: 'ui-heurist-admin',
        path: 'widgets/database/',
        entered_password: ''
    },
    
    _progressInterval:0,
    _session_id:0,
    _select_file_dlg:null,
    Heurist_Reference_Index: 'Heurist_Reference_Index',

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
        
    
    
    _init: function() {

        if(this.options.htmlContent=='' && this.options.actionName){
            this.options.htmlContent = this.options.actionName
                    +(window.hWin.HAPI4.getLocale()=='FRE'?'_fre':'')+'.html';
        }
        
        // dbCreate => create
        this.options.actionName = this.options.actionName.slice(2)
        this.options.actionName = this.options.actionName[0].toLowerCase()
                                    +this.options.actionName.slice(1)
        
        this._super();
    },
     
    //  
    // invoked from _init after loading of html content
    //
    _initControls:function(){
        
        this._$('button').button();
        this._on(this._$('button.ui-button-action'),{click:this.doAction});
        
        this._$('span.dbprefix').text(window.hWin.HAPI4.sysinfo.database_prefix);
        
        if(this.options.actionName=='create' &&
            window.hWin.HAPI4.sysinfo['pwd_DatabaseCreation'])
        {
            this._$('#div_need_password').show();
        }
        else if(this.options.actionName=='clone')
        {
            this._checkNewDefinitions();
            
            if(window.hWin.HAPI4.sysinfo.db_total_records<50000){
                this._$('.large-db').hide();
            }else{
                this._on(this._$('#nodata'), {click:()=>{
                    if(this._$('#nodata').is(':checked')){
                        this._$('.large-db').hide();
                    }else{
                        this._$('.large-db').show();
                    }
                }});
            }
            
        }else if(this.options.actionName=='restore')
        {
            this._on(this._$('#btnSelectZip'),{click:this._selectArchive});
            
        }else if(this.options.actionName=='verify')
        {
            this._initVerification();
        
        }else if(this.options.actionName=='register')
        {
            
            let that = this;
            window.hWin.HAPI4.EntityMgr.getEntityData('sysIdentification', false, function(response){
                if(!window.hWin.HEURIST4.util.isempty(response)){
                    let record = response.getFirstRecord();
                    that._$('.dbDescription').text(record[17]);
                    that._$('#dbTitle').val(record[17]).trigger('keyup');
                }});

            if(window.hWin.HAPI4.sysinfo['db_registeredid']>0){
 
                this._$('.dbDescription').text('');
                this._$('span.dbId').text(window.hWin.HAPI4.sysinfo['db_registeredid']);
                this._$('a.dbLink').attr('href',
                    window.hWin.HAPI4.sysinfo['referenceServerURL']
                        +'?fmt=edit&recID='+window.hWin.HAPI4.sysinfo['db_registeredid']
                        +'&db='+this.Heurist_Reference_Index)
                       
                this._$('.ent_wrapper').hide();
                this._$("#div_result").show();
                        
            }else{
            
                this._$('#serverURL').val(window.hWin.HAPI4.baseURL_pro+'?db='+window.hWin.HAPI4.database);
                this._$('#dbTitle').val('');
                
                if(window.hWin.HAPI4.user_id()!=2){
                    this._$('#div_need_password').show();
                }else{
                    this._$('#div_need_password').hide();
                }
                
                this._on(this._$('#dbTitle'),{keyup:
                        function ( event ){
                            let len = $(event.target).val().length;
                            let ele = this._$('#cntChars').text(len);
                            ele.parent().css('color',(len<40)?'red':'#6A7C99');
                        }
                });
                
            }

        }


        //user and database name inputs        
        let ele = this._$('#uname');
        if(ele.val()=='' && window.hWin.HAPI4.currentUser){
            ele.val(window.hWin.HAPI4.currentUser.ugr_Name.substr(0,5).replace(/[^a-zA-Z0-9$_]/g,''));
        }
        this._on(this._$('#newdblink'),{click:this.closeDialog});
        this._$('span.dbname').text(window.hWin.HAPI4.database);

        this._$('#dbname').trigger('focus');

        //find and activate event listeners for elements
        this._on(this._$('input[type=text]'),{keypress:window.hWin.HEURIST4.ui.preventNonAlphaNumeric});
        
        return this._super();
    },

    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements

    },
    
    //----------------------
    //
    // array of button defintions
    //
    _getActionButtons: function(){
        return this._super();
    },
    
    //
    //
    //
    doAction: function(){
        
        let request;
        
        if(this.options.actionName=='create' 
            || this.options.actionName=='rename'
            || this.options.actionName=='clone')
        {

           const dbname = this._$('#dbname').val().trim();
           if(dbname==''){
                window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define name of database'));
                return;  
           } 
           
           let ele = this._$('#uname');
           
           request = {uname : (ele.length>0?ele.val().trim():''), 
                      dbname: dbname}; 

           if(this.options.actionName=='clone'){ 
                if(this._$('#nodata').is(':checked')){
                    request['nodata'] = 1;
                }
                const pwd = this._$('#pwd').val().trim();
                if(pwd!=''){
                    request['pwd'] = pwd;
                } 
                
           }else if(this.options.actionName=='create' && window.hWin.HAPI4.sysinfo['pwd_DatabaseCreation']){
                const pwd = this._$('#create_pwd').val().trim();
                if(pwd==''){
                    window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define password'));
                    return;  
                } 
                request['create_pwd'] = pwd;
           }

                      
        }else if(this.options.actionName=='clear' || this.options.actionName=='delete'){

           //challenged word
           let chpwd = this._$('#db-password').val().trim();
           if(chpwd==''){
               window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define challenge word'));
               return;  
           } 
           
           request = {
                        //database : window.hWin.HAPI4.database, //current database
                        chpwd: chpwd
                     }; 

           if(this.options.entered_password){
                request['pwd'] = this.options.entered_password;
           }
              
        }else if(this.options.actionName=='restore'){
            
           const dbname = this._$('#dbname').val().trim();
           if(dbname==''){
                window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define name of database'));
                return;  
           } 
           
           //check for source/archive file
           let dbarchive_file = this._$('#selectedZip').text();
           if(dbarchive_file==''){
                window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define name of source zip archive'));
                return;  
           }
           
           let dbarchive_folder = this._$('input[name=selArchiveFolder]:checked').val();

           request = {file: dbarchive_file,
                      folder: dbarchive_folder, //id
                      dbname: dbname,
                      pwd: this.options.entered_password}; 
                      
        }else if(this.options.actionName=='register'){
            
            let description = this._$('#dbTitle').val().trim();
            if(description.length<40){
                window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define informative description (min 40 characters)'));
                return;  
            }

           request = {dbReg: window.hWin.HAPI4.database,
                      dbTitle: description,
                      dbVer: window.hWin.HAPI4.sysinfo['db_version'],
                      serverURL: this._$('#serverURL').val()
                      }; 
                      
           if(window.hWin.HAPI4.user_id()!=2){
                const pwd = this._$('#pwd').val().trim()    
                if(pwd==''){
                    window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define password'));
                    return;  
                } 
                request['pwd'] = pwd;
           }
           
        }else if(this.options.actionName=='verify'){
            
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

            
        }//end switch

        
        if(this.options.actionName=='delete' || this.options.actionName=='rename'){       
           if(!this._$('#db-archive').is(':checked')){
                request['noarchive'] = 1;
                this._$('.archive').hide();
           }else{
                this._$('.archive').show();
           }
        }

        this._sendRequest(request);        

    },
    
    //
    //
    //    
    _sendRequest: function(request)
    {
        //unique session id    ------------------------
        this._session_id = window.hWin.HEURIST4.util.random();
        
        request['action'] = this.options.actionName;       
        request['db'] = window.hWin.HAPI4.database;
        request['locale'] = window.hWin.HAPI4.getLocale();
        request['session'] = this._session_id;

        this._showProgress( this._session_id, false, (this.options.actionName=='register')?0:1000 );
        let that = this;
        
        window.hWin.HAPI4.SystemMgr.databaseAction( request,  function(response){

                that._hideProgress();
            
                if (response.status == window.hWin.ResponseStatus.OK) {
                    
                    that._afterActionEvenHandler(response.data, response.message);
                    
                } else {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    that._$('.ent_wrapper').hide();
                    that._$('#div_header').show(); //show first page
                }
              
        });
    },
    
    // Action: Clone
    // Verify new defintions in database to be cloned
    //
    _checkNewDefinitions: function(){

        let that = this;
      
        let request = {action: 'check_newdefs',        
                       db: window.hWin.HAPI4.database};
        
        window.hWin.HAPI4.SystemMgr.databaseAction( request,  function(response){

                if (response.status == window.hWin.ResponseStatus.OK) {
                    if(response.data!=''){
                        that._$('#div_need_password').show();
                        that._$('#new_defs_warning').text(response.data);
                    }
                } else {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
              
        });
        
    },

    // Action: Restore
    // select database zip archive to be restored
    //
    _selectArchive: function(){
        
        let that = this;
        let src_folder = this._$('input[name=selArchiveFolder]:checked').val();
        
        
        if(!this._select_file_dlg){
            this._select_file_dlg = $('<div>').hide().appendTo( this.element );
        
            this._select_file_dlg.selectFile({
               keep_dialogue: true,
               showFilter: true, 
               source: src_folder,     
               extensions: (src_folder==3)?'zip,bz2':'zip',
               title: window.HR('Select database archive'),
               onselect:function(res){
                    if(res && res.filename){
                        that._$('#selectedZip').text(res.filename);
                        that._$('#divSelectedZip').show();
                       
                        
                        //suggest database name
                        if(that._$('#dbname').val().trim()==''){
                            let sname = res.filename; 
                            if(sname.indexOf(window.hWin.HAPI4.sysinfo.database_prefix)==0){
                                sname = sname.substring(window.hWin.HAPI4.sysinfo.database_prefix.length);
                            }
                            if(sname.indexOf('.')>0){
                                sname = sname.substring(0,sname.indexOf('.'));
                            }
                            if(sname.length>24){
                                sname = sname.substring(0,23);
                            }
                            that._$('#dbname').val(sname);
                        }
                        
                    }else{
                        that._$('#selectedZip').text('');
                        that._$('#divSelectedZip').hide();
                    }
               }});        
        }else{
            this._select_file_dlg.selectFile({
               source: src_folder,     
               extensions: (src_folder==3)?'zip,bz2':'zip'
            });
        }

    },

    
    
    //  -----------------------------------------------------
    //
    //  after save event handler
    //
    _afterActionEvenHandler: function( response, terminatation_message ){
        
        this._$('.ent_wrapper').hide();
        let div_res = this._$("#div_result").show();
        
        if(response && response.newdbname){
            this._$('#newdbname').text(response.newdbname);  
        } 
            
        if(this.options.actionName=='delete'){

            window.hWin.HEURIST4.msg.showMsgDlg(div_res.html(),
               null, window.hWin.HR('Database deleted'),
               {
                    width:700,
                    height:'auto',
                    close: function(){
                        //redirects to startup page - list of all databases
                        window.hWin.document.location = window.hWin.HAPI4.baseURL; //startup page
                    }
               }
            );
            
        }else if(this.options.actionName=='rename')
        {
            if(response.warning){
                div_res.find('.warning').html(window.hWin.HEURIST4.util.stripTags(response.warning,'p,br'));
            }
            window.hWin.HEURIST4.msg.showMsgDlg(div_res.html(),
               null, window.hWin.HR('Database renamed'),
               {
                    width:700,
                    height:'auto',
                    close: function(){
                        //redirects to renamed database
                        window.hWin.document.location = response.newdblink;
                    }
               }
            );
            
        }else if(this.options.actionName=='register'){

            this._$('.dbDescription').text(response.dbTitle);
            this._$('span.dbId').text(response.dbID);
            this._$('a.dbLink').attr('href',
                window.hWin.HAPI4.sysinfo['referenceServerURL']
                    +'?fmt=edit&recID='+response.dbID
                    +'&db='+this.Heurist_Reference_Index);
            
            
            //reload page
            window.hWin.HEURIST4.msg.showMsgDlg(div_res.html(),
               null, window.hWin.HR('Database registered'),
               {
                    width:700,
                    height:'auto',
                    close: function(){
                        //redirects to renamed database
                        window.hWin.document.location.reload();
                    }
               }
            );
            
        }else if(this.options.actionName=='verify'){
            
            this._initVerificationResponse(response);
            
            if(terminatation_message){

                let error = window.hWin.HEURIST4.util.isObject(terminatation_message)
                            ? terminatation_message
                            : {message: terminatation_message};
                error['error_title'] = window.hWin.HEURIST4.util.isempty(error['error_title']) ? 'Verification terminated' : error['error_title'];

                window.hWin.HEURIST4.msg.showMsgErr(error)
            }
            
        }else{
            
            
            this._$('#newusername').text(response.newusername);
            this._$('#newdblink').attr('href',response.newdblink).text(response.newdblink);
            
            if(response.warnings && response.warnings.length>0){
                this._$('#div_warnings').html(response.warnings.join('<br><br>')).show();
                this._$('#div_login_info').hide();
            }
               
        }
                    
        //clear local list of databases   
        if(this.options.actionName!='clear'){
            window.hWin.HAPI4.EntityMgr.emptyEntityData('sysDatabases');
        }    
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
        
        //show wait screen
        window.hWin.HEURIST4.msg.bringCoverallToFront(null, {opacity: '0.3'}, window.hWin.HR(this.options.title));
        $('body').css('cursor','progress');
        
        let that = this;
       
        this._$('.ent_wrapper').hide();
        let progress_div = this._$('.progressbar_div').show();
        
        let div_loading = progress_div.find('.loading').show();
        let all_li = div_loading.find('li');
        if(all_li.length>0){
            all_li.css('color','lightgray');
            $(all_li[0]).css('color','black');
            $('<span class="processing"> <span class="ui-icon ui-icon-loading-status-balls"></span>  <span class="percentage">processing...</span></span>').appendTo( $(all_li[0]) );
        }
        
        let currStep = 0;
        
        if(t_interval>900){

            let progress_url = window.hWin.HAPI4.baseURL + "hserv/controller/progress.php";
                
            this._progressInterval = setInterval(function(){ 
                
                let request = {t:(new Date()).getMilliseconds(), session:session_id};            
                
                window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
                    
                    if(response && response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                        that._hideProgress();
                    }else if(response=='terminate' && is_autohide){
                        that._hideProgress();
                    }else if(response && currStep!=response){

                        currStep = response;
            
                        let percentage = 0, newStep = 0;
                        if(response.indexOf(',')>0){
                            [newStep, percentage] = response.split(',');
                        }else{
                            newStep = response;    
                        }
                        
                        if(window.hWin.HEURIST4.util.isNumber(newStep)){
                            //set finished step in solid black
                            newStep = parseInt(newStep);
                            let all_li = div_loading.find('li');
                            if(newStep>0){
                                let arr = all_li.slice(0,newStep);
                                arr.css('color','black');
                                arr.find('span.processing').remove(); //remove rotation icon
                                //set current step (if exists) with loading icon
                            }
                            if(newStep<all_li.length){
                                    if(percentage>0){
                                        if(percentage>100) percentage = 100;
                                        percentage = percentage+'%'; 
                                    }else{
                                        percentage = 'processing...'
                                    }
                                    let ele = $(all_li[newStep]).find('span.percentage');
                                    if(ele.length==0){
                                        percentage = '<span class="percentage">'+percentage+'</span>';
                                        $('<span class="processing"> <span class="ui-icon ui-icon-loading-status-balls"></span> '
                                            +percentage+'</span>')
                                            .appendTo( $(all_li[newStep]) );
                                        $(all_li[newStep]).css('color','black');
                                    }else{
                                        ele.text(percentage);
                                    }
                            }
                        }else{
                            let cont = div_loading.find('ol');
                            let li_ele = cont.find('li:contains("'+newStep+'")');
                            if(li_ele.length==0){
                                $('<li>'+newStep+'</li>').appendTo(cont);    
                            // }else if(percentage>0){
                            }
                        }
                            
                        
                    }
                },'text');
            
            }, t_interval);    
        
        }            
        
    },
    
    _hideProgress: function (){
        
        $('body').css('cursor','auto');
        window.hWin.HEURIST4.msg.sendCoverallToBack(true);
        
        if(this._progressInterval!=null){
            
            clearInterval(this._progressInterval);
            this._progressInterval = null;
        }
        this._$('.ent_wrapper').hide();
        this._$('#div_header').show();
        
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