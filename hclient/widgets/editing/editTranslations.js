/**
* Widget to define translations 
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

$.widget( "heurist.editTranslations", {

    // default options
    options: {
        is_dialog: true, //show in dialog or embedded
        
        fieldtype: 'freetext', //or blocktext

        values: [''],  //array of values 
        
        onclose: null
        
    },
    
    _as_dialog:null, //reference to itself as dialog (see options.is_dialog)
    
    _was_changed: false,

    // the constructor
    _init: function() {

        let that = this;

        
        this._container = $('<div class="ent_content_full" style="top:0;padding:10px"></div>')
                    .appendTo( $('<div class="ent_wrapper">').appendTo(this.element) );

        if(this.options.is_dialog){
            
            let $dlg;
            
            let arrButtons = {};
            arrButtons[window.hWin.HR('Apply')] = function() {
                that._onCloseDialog();
                if(that._as_dialog && that._as_dialog.dialog('instance')) that._as_dialog.dialog( "close" );
            };
            arrButtons[window.hWin.HR('Cancel')] = function() {
                if(that._as_dialog && that._as_dialog.dialog('instance')) that._as_dialog.dialog( "close" );
            };

            $dlg = this.element.dialog({
                autoOpen: true,
                height: 240,
                width: 840,
                modal: true,
                title: 'Define Translations',
                buttons: arrButtons,
                resizeStop: function( event, ui ) {
                    let pele = that.element.parents('div[role="dialog"]');
                    that.element.css({overflow: 'none !important', width:pele.width()-24 });
                },
                close:function(){
                    that._as_dialog.remove();    
                }
            });
            
            $dlg.parent().addClass('ui-heurist-populate');
            
            this._as_dialog = $dlg; 
        }
        
        //create edit form with list of inputs
        this._createForm();        
        
    }, //end _create
    
    //
    //
    //
    _createForm: function(){

        const that = this;
        
        //create - define new language button 
        this._btn_add = $('<div class="btn_lang_add" style="margin:10px 10px;color:#6A7C99;cursor:pointer;font-size:0.9em;display:inline-block;">'
        +'<span class="ui-icon ui-icon-plus" style="font-size:1em;margin-left: 95px;"></span> Add language'
        +'</div>')
        .appendTo( this._container );

        this._btn_translate = $('<div class="btn_translate_add" style="margin:10px 0px;color:#6A7C99;cursor:pointer;font-size:0.9em;display:inline-block;">'
        +'<span class="ui-icon ui-icon-plus" style="font-size:1em;margin-left:25px;"></span> Add translation'
        +'</div>')
        .appendTo( this._container ).hide();
        
        for (let i=0; i<this.options.values.length; i++){
            
            this._createEntry( this.options.values[i] , true);

        }//for
        
        this._adjustDimension();
        
        this._on(this._btn_add, {click:function(){
            //take defaul value
            let ele = this.element.find('[data-def=1]');
            
            this._createEntry(ele.val(), false);
            this._adjustDimension();
        }});

        this._on(this._btn_translate, {
            click: function(){

                let $dlg;

                let msg = 'Language: <select id="selLang"></select><br><br>'
                        + 'You may block translation of some part of the text by adding an html tag with translate="no",<br>'
                        + 'for example:  &lt;p translate=”no”&gt;text not to be translated&lt;/p&gt;';

                let btns = {};
                let labels = {yes: window.HR('Add'), no: 'Cancel', title: 'Add automatic translation'};

                btns[window.HR('Add')] = function(){

                    let first_val = that.element.find('[data-def=1]').val();
                    first_val = window.hWin.HEURIST4.util.isempty(first_val) ? this.element.find('input:first').val() : first_val;

                    let target = $dlg.find('#selLang').val();

                    if(window.hWin.HEURIST4.util.isempty(first_val)){
                        window.hWin.HEURIST4.msg.showMsgFlash('Please enter a value to translate in the first input...', 3000);
                        return;
                    }else if(window.hWin.HEURIST4.util.isempty(target)){
                        window.hWin.HEURIST4.msg.showMsgFlash('Select a language...', 3000);
                        return;
                    }

                    let source = '';

                    if(first_val.match(/^\w{3}:/)){ // check for a source language

                        // Pass as source language
                        source = first_val.match(/^\w{3}:/)[0];
                        source = source.slice(0, -1);

                        first_val = first_val.slice(4); // remove lang prefix
                    }

                    let request = {
                        a: 'translate_string',
                        string: first_val,
                        target: target,
                        source: source
                    };

                    window.hWin.HAPI4.SystemMgr.translate_string(request, function(response){

                        $dlg.dialog('close');

                        if(response.status == window.hWin.ResponseStatus.OK){
                            let new_value = target + ':' + response.data;
                            that._was_changed=true;
                            that._createEntry(new_value, true);
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    });

                };

                btns[window.HR('Cancel')] = function(){
                    $dlg.dialog('close');
                };


                $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, labels, {default_palette_class: 'ui-heurist-populate'});

                window.hWin.HEURIST4.ui.createLanguageSelect($dlg.find('#selLang'), null, null, true);
            }
        })
    },        
    
    //
    ///
    //
    _adjustDimension: function(){
        
        
        let ch = this.element.find('div.ent_content_full')[0].scrollHeight;
 
        if(ch<150) ch = 150;

        let topPos = 0;
        if(this.options.is_dialog){        
            let pos = this._as_dialog.dialog('option', 'position');
            if(pos && pos.of && !(pos.of instanceof Window)){
                let offset = $(pos.of).offset();
                topPos = (offset?offset.top:0)+40;
            }
            
            ch = ch + 80;

            let ht = Math.min(ch, window.innerHeight-topPos);

            this._as_dialog.dialog('option', 'height', ht);    
        }else{
            topPos = this.element.parent().offset().top + 10;
            
            if(ch > window.innerHeight-topPos){
                ch = window.innerHeight-topPos;
            }
            
            this.element.parent().height(ch);
        }
                
    },
    

    //
    //
    //
    _createEntry: function(value, check_default){

        let sel_container, values_container, input_ele, that = this;
        
        let cont = $('<div>').css({margin:'5px'}).insertBefore(this._btn_add);
        
        // selector container - to select language
        this.sel_container = $('<div>')
            .css({'display':'inline-block','vertical-align':'top','padding-top':'3px','min-width':'100px'})
            .appendTo(cont);
        // values container
        values_container = $( '<div>' )
            .css({'display':'inline-block','padding':'3px'}) //,'margin-bottom': '2px'
            .appendTo( cont );
            
        
        let _is_default = false;    
        let lang = '';
        
        if(check_default){
            
            if(!window.hWin.HEURIST4.util.isempty(value)){
                if(value.substr(3,1)==':'){ //ISO639-2
                    lang = value.substr(0,3);
                    value = value.substr(4).trim();
                }else if(value.substr(2,1)==':'){ //ISO639-1
                    lang = value.substr(0,2);
                    value = value.substr(3).trim();

                    lang = window.hWin.HAPI4.getLangCode3(lang); //convert to ISO639-2
                }
                lang = lang.toUpperCase();
            }
            
            _is_default = window.hWin.HEURIST4.util.isempty(lang);
        }
        
        if(this.options.fieldtype=='blocktext')
        {
            input_ele = $( "<textarea>",{rows:4}) //min number of lines
                    .css({'overflow-x':'hidden'})
                    .on('keydown', function(e){
                        if (e.keyCode == 65 && e.ctrlKey) {
                            e.target.select()
                        }    
                    });

        }else{
            input_ele = $( "<input>");
            
        }
        
        input_ele.uniqueId()
                    .addClass('text ui-widget-content ui-corner-all')
                    .css({width:'680px'})
                    .val(value)
                    .on('keyup', function(){
                        that._was_changed=true;
                        if(!window.hWin.HEURIST4.util.isempty($(this).val()) && window.hWin.HAPI4.sysinfo.api_Translator){
                            that._btn_translate.show();
                        }else{
                            that._btn_translate.hide();
                        }
                    })
                    .on('change', function(){
                        that._was_changed=true;
                        if(!window.hWin.HEURIST4.util.isempty($(this).val()) && window.hWin.HAPI4.sysinfo.api_Translator){
                            that._btn_translate.show();
                        }else{
                            that._btn_translate.hide();
                        }
                    })
                    .appendTo( values_container );
                    
        if(_is_default){
            input_ele.attr('data-def',1);
        }
        
        let inpt_id = input_ele.attr('id');

        if(_is_default){
            //label for first value
            $('<div class="header_narrow field_header" '
            +'style="min-width:90px;display:inline-block;text-align:right;padding-right: 9px;">'
            +'<label>Default language</label></div>')
                .appendTo( this.sel_container );
        }else{
            let ind = -1;

            //find last seleted
            if(!check_default){
                let ele = this.element.find('select').last();
                if(ele.length>0){
                    ind = ele[0].selectedIndex;
                    if(ind<0) ind = 0;
                }
            }
            
            // 2. field selector for field or links tokens
            let sel = $( '<select>' )
                .attr('title', 'Select language' )
                .attr('data-input-id', inpt_id)
                .addClass('text ui-corner-all')
                .css({'margin-left':'2em','min-width':'70px','max-width':'70px'})
                .hide()
                .appendTo( this.sel_container );
                
                
            window.hWin.HEURIST4.ui.createLanguageSelect(sel, null, lang, false);
            
            if(ind>=0){
                sel[0].selectedIndex = ind + 1;
                sel.hSelect('refresh');
            }
            
        }

        let first_val = this.element.find('[data-def=1]').val();
        first_val = window.hWin.HEURIST4.util.isempty(first_val) ? this.element.find('input:first').val() : first_val;
        if(!window.hWin.HEURIST4.util.isempty(first_val) && window.hWin.HAPI4.sysinfo.api_Translator){
            this._btn_translate.show();
        }else{
            this._btn_translate.hide();
        }
    },
    
    //
    //
    //
    _onCloseDialog: function(){

        if(this._was_changed){
            
            let that = this;
            
            let eles = this.element.find('textarea,input');
            
            let res = [];
            
            eles.each(function(i, item){
                item = $(item); 
                let val = item.val().trim();
                if(!window.hWin.HEURIST4.util.isempty(val)){
                    //find language
                    let sel = that.element.find('select[data-input-id="'+item.attr('id')+'"]');
                    if(sel.length>0 && sel.val() && !item.attr('data-def')){
                        res.push(sel.val()+':'+val);
                    }else{
                        res.push(val);
                    }
                }
                
            });

            if(window.hWin.HEURIST4.util.isFunction(this.options.onclose)){
                this.options.onclose.call(this, res);        
            }
                
        }
    },


    /* private function */
    _refresh: function(){
    },
    
    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        // remove generated elements
    },


});
