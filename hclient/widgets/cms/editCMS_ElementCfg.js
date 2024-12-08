/**
* editCMS_ElementCfg.js - configuration dialog for css and cardinal properties,
* for widgets it uses editCMS_WidgetCfg.js 
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

/* global editCMS_WidgetCfg, CodeMirror, default_language, current_language, website_languages */

//
//
//
function editCMS_ElementCfg( element_cfg, _layout_content, _layout_container, $container, main_callback, already_changed ){

    const _className = 'editCMS_ElementCfg';
    let element;
    let l_cfg, //copy of json config
        widget_cfg; //WidgetCfg object
    let codeEditor = null,
        codeEditorDlg = null,
        codeEditorBtns = null;
    let textAreaCss;
    let margin_mode_full = true;
    
    function _init(){

        /* not used as dialog
        var buttons= [
            {text:window.hWin.HR('Cancel'), 
                id:'btnCancel',
                css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
                click: function() { 
                    $dlg.dialog( "close" );
            }},
            {text:window.hWin.HR('Apply'), 
                id:'btnDoAction',
                class:'ui-button-action',
                //disabled:'disabled',
                css:{'float':'right'}, 
                click: function() { 
                        var config = _getValues();
                        main_callback.call(this, config);
                        $dlg.dialog( "close" );    
        }}];
        */
        
        
        element = _layout_container.find('div[data-hid='+element_cfg.key+']'); //element in main-content
        l_cfg = window.hWin.HEURIST4.util.cloneJSON(element_cfg);

        $container.empty().load(window.hWin.HAPI4.baseURL
                +'hclient/widgets/cms/editCMS_ElementCfg.html',
                _initControls
            );
    }
    
    //
    // Assign widget properties to UI
    //
    function _initControls(){

        let cont = $container;

        cont.find('input[data-type="element-name"]').val(l_cfg.name);
        cont.find('input[data-type="element-id"]').val(l_cfg.dom_id); //duplication for options.widget_id
        cont.find('textarea[name="elementClasses"]').val(l_cfg.classes);

        let etype = (l_cfg.type?l_cfg.type:(l_cfg.appid?'widget':'text'));

        cont.find('h4').css({margin:0});
        cont.find('.props').hide(); //hide all
        cont.find('#widget-config').parent().hide();
        cont.find('input[data-type="element-id"]').parent().show();
        cont.find('.props.'+etype).show(); //show required only

        let activePage = (etype=='group'?0:(etype=='widget'?false:(etype=='cardinal'?1:2)));

        cont.find('fieldset:first .heurist-helper3').position({
            my: 'left top', at: 'left bottom', of: cont.find('fieldset:first div:nth(1)')
        });

        cont.find('#properties_form').accordion({header:'h3',heightStyle:'content',active:activePage,collapsible:true});
        cont.find('h3').css({'font-size': '1.1em', 'font-weight': 'bold'});

        if(!l_cfg.css) l_cfg.css = {display:'block'};

        _assignCssToUI();

        //load and init widget properties
        if(etype=='widget'){

            let dom_id = window.hWin.HEURIST4.util.stripTags(cont.find('input[data-type="element-id"]').val());
            if(dom_id!=l_cfg.options.widget_id){
                l_cfg.options.widget_id = dom_id;
            }

            widget_cfg = editCMS_WidgetCfg(l_cfg, _layout_content, cont.find('#widget-config'), null, function(){

                let new_cfg = widget_cfg.getValues();

                if(JSON.stringify(l_cfg.options) != JSON.stringify(new_cfg)){
                    _enableSave();    
                }
            });

            cont.find('#widget-config').parent().show();
            cont.find('input[data-type="element-id"]').parent().hide();

        }else
        if(etype=='group' && l_cfg.children && l_cfg.children.length>0){

            //4a.add list of children with flex-grow and flex-basis
                let item_ele = cont.find('div[data-flexitem]');
                let item_last = item_ele;
                for(let i=0; i<l_cfg.children.length; i++){

                    let child = l_cfg.children[i];

                    let item = item_ele.clone().insertAfter(item_last);
                    item.attr('data-flexitem',i).show();
                    let lbl = item.find('.header_narrow');
                    lbl.text((i+1)+'. '+lbl.text());

                    let val = (child.css)?child.css['flex']:null;
                    if(val){
                        val = val.split(' '); //grow shrink basis
                    }else{
                        val = [0,1,'auto'];
                    }
                    if(val[0]) item.find('input[data-type="flex-grow"]').val(val[0]);
                    if(val.length==3 && val[2]) item.find('input[data-type="flex-basis"]').val(val[2]);

                    item.find('input').on('change', function(e){
                        let item = $(e.target).parent();
                        let k = item.attr('data-flexitem');

                        if(!l_cfg.children[k].css) l_cfg.children[k].css = {};

                        l_cfg.children[k].css['flex'] = item.find('input[data-type="flex-grow"]').val()
                        +' 1 '+ item.find('input[data-type="flex-basis"]').val();

                        /*l_cfg.children[k].css['border'] = '1px dotted gray';
                        l_cfg.children[k].css['border-radius'] = '4px';
                        l_cfg.children[k].css['margin'] = '4px';*/

                        let child_ele = _layout_container.find('div[data-hid='+l_cfg.children[k].key+']');
                        child_ele.removeAttr('style');
                        child_ele.css(l_cfg.children[k].css);
                    });

                    item_last = item;
                }//for
        }else 
        if(etype=='cardinal'){ //assign cardinal properties
            
            for(let i=0; i<l_cfg.children.length; i++){
                let lpane = l_cfg.children[i];
                let pane = lpane.type;

                if(lpane.options){
                   let keys = Object.keys(lpane.options); 
                   for(let k=0; k<keys.length; k++){
                       let key = keys[k];
                       let ele = cont.find('[data-type="cardinal"][data-pane="'+pane+'"][name="'+key+'"]');
                       if(ele.length>0){
                            const val = lpane.options[key];   
                            if(ele.attr('type')=='checkbox'){
                                ele.attr('checked', (val=='true' || val===true));
                            }else {
                                ele.val(val);    
                            }
                       }
                   }//for
                }
            }//for
        }

        if(etype=='cardinal' || etype=='tabs' || etype=='accordion'){
            cont.find('input[data-type="element-id"]').parent().hide();
        }
        
        
        //4. listeners for selects    
        cont.find('select').hSelect({change:function(event){
            
            let ele = $(event.target);
            
            if((ele).attr('data-type')=='cardinal') return;
            
            let name = ele.attr('id');
            
            if(name=='display'){
                if(ele.val()=='flex'){
                    cont.find('.flex-select').each(function(i,item){ $(item).parent().show(); })
                }else{
                    cont.find('.flex-select').each(function(i,item){ $(item).parent().hide(); })
                }
            }
            _getCss();
            
            _enableSave();
        }});


        //4b. listeners for styles (border,bg,margin)
        cont.find('input[data-type="css"]').on('change',_getCss);
        cont.find('input[data-type="css"]').on('keyup',_getCss);
        cont.find('input[name="background"]').on('change',_getCss);
        /*
            var css = _getCss();
           
           
        });*/
        
        //4c. button listeners
        cont.find('.margin-mode').button()
            .css({'font-size':'0.7em'})
            .on('click', function(e){
            //show hide short and full margin/padding
            margin_mode_full = !margin_mode_full;
            _onMarginMode();
        });
        
        cont.find('.cb_sync').parent().css({'font-size':'0.8em'});
        cont.find('.cb_sync').on('change',_onMarginSync);
        cont.find('input[name="padding-left"]').on('change',_onMarginSyncVal);
        cont.find('input[name="margin-left"]').on('change',_onMarginSyncVal);

        function __saveWidgetConfig(){
            if(widget_cfg){
                let new_cfg = widget_cfg.getValues();
                l_cfg.options = new_cfg;

                if(new_cfg.widget_id){
                    l_cfg.dom_id = new_cfg.widget_id;
                    cont.find('input[data-type="element-id"]').val(l_cfg.dom_id);
                }
            }
        }

        //save entire page (in background)
        cont.find('.btn-save-page').button().css('border-radius','4px').on('click', function(){
            __saveWidgetConfig();
            _getCfgFromUI();
            main_callback.call(this, l_cfg, 'save_close'); //save and close
        });

        cont.find('.btn-save-element').button().css('border-radius','4px').on('click', function(){
            __saveWidgetConfig();
            //5. save in layout cfg
            _getCfgFromUI();
            main_callback.call(this, l_cfg, 'save'); //save only
            window.hWin.HEURIST4.util.setDisabled(cont.find('.btn-save-element'), true);
        });
        cont.find('.btn-cancel').css('border-radius','4px').button().on('click', function(){
            //6. restore old settings 
            element.removeAttr('style');
            if(element_cfg.css) element.css(element_cfg.css);
            main_callback.call(this, null);
        });
        
        window.hWin.HEURIST4.util.setDisabled(cont.find('.btn-save-page'), already_changed!==true);
        window.hWin.HEURIST4.util.setDisabled(cont.find('.btn-save-element'), true);
        
        
        //direct editor        
        textAreaCss = $container.find('textarea[name="elementCss"]');
        
        _assignCssTextArea();
        
        textAreaCss.on('change',function(){

            let vals = textAreaCss.val();
           
            vals = vals.replace(/"/g, ' ');
            
            vals = vals.split(';')
            let new_css = {};
            for (let i=0; i<vals.length; i++){
                let vs = vals[i].split(':');
                if(vs && vs.length==2){
                     let key = vs[0].trim();
                     let val = vs[1].trim();
                     new_css[key] = val;
                }
            }
            
            element.removeAttr('style');
            element.css(new_css);
            l_cfg.css = new_css;

           
           

            _assignCssToUI();
           
        }).trigger('change');
        
        
        let btnDirectEdit = cont.find('div.btn-html-edit');
        if(etype=='text'){
             btnDirectEdit.parent().show();               
             btnDirectEdit.button().on('click',_initCodeEditor);
        }else{
             btnDirectEdit.parent().hide();               
        }
        
        $container.find('textarea').on({keyup:_enableSave});
        $container.find('input').on({keyup:_enableSave});
        $container.find('input').on({change:_enableSave});
    }


    //
    //
    //
    function _getCfgFromUI(){            
        
            let cont = $container;
            let css = _getCss();
            l_cfg.css = css;
            l_cfg.name = window.hWin.HEURIST4.util.stripTags(cont.find('input[data-type="element-name"]').val());
            if(!l_cfg.name) l_cfg.name = 'Define name of element';
            l_cfg.title = '<span data-lid="'+l_cfg.key+'">'+l_cfg.name+'</span>';
            
            l_cfg.dom_id = window.hWin.HEURIST4.util.stripTags(cont.find('input[data-type="element-id"]').val());
            if(l_cfg.appid && l_cfg.options){
                l_cfg.options.widget_id = l_cfg.dom_id;
                if(l_cfg.options.is_popup){
                    l_cfg.options.popup_width = l_cfg.css.width;
                    l_cfg.options.popup_height = l_cfg.css.height;
                }
            }

            if(window.hWin.HEURIST4.util.isempty(cont.find('textarea[name="elementClasses"]').val())){
                if(l_cfg.classes) delete l_cfg['classes'];
            }else{
                l_cfg.classes = cont.find('textarea[name="elementClasses"]').val();    
            }
        
            //get cardinal parameters  
            if(l_cfg.type=='cardinal')
            for(let i=0; i<l_cfg.children.length; i++){
                    let lpane = l_cfg.children[i];
                    let pane = lpane.type;

                    l_cfg.children[i].options = {}; //reset
            
                    $.each(cont.find('[data-type="cardinal"][data-pane="'+pane+'"]'), function(k, item){
                         item = $(item);
                         let name = item.attr('name');
                         let val = item.val();
                         if(item.attr('type')=='checkbox'){
                             val = item.is(':checked'); 
                             l_cfg.children[i].options[name] = val;    
                         }else if(val!=''){
                             l_cfg.children[i].options[name] = val;    
                         }
                    });
            }//for
            
    }            
    
    //
    //
    //
    function _getCss()
    {
        let cont = $container;
        let css = {};
        if(cont.find('#display').val()=='flex'){
            css['display'] = 'flex';

            cont.find('.flex-select').each(function(i,item){
                if($(item).val()){
                    css[$(item).attr('id')] = $(item).val();       
                }
            });
        }else if(cont.find('#display').val()=='table'){
            css['display'] = 'table';
        }else{
            css['display'] = 'block';
        }

        //style - border
        let val = cont.find('#border-style').val();
        css['border-style'] = val;

        let fieldset = cont.find('fieldset[data-section="border"] > div:not(:first)');
        if(val=='none'){
            fieldset.hide();

        }else{
            fieldset.css('display','table-row');

            cont.find('input[name^="border-"]').each(function(i,item){
                if($(item).val()){
                    css[$(item).attr('name')] = $(item).val()
                    +($(item).attr('type')=='number'?'px':'');       
                }
            });

            if(!css['border-width']) css['border-width'] = '1px';
            if(!css['border-color']) css['border-color'] = 'black';
        }

        //style - background
        val = cont.find('input[name="background"]').is(':checked');
        fieldset = cont.find('fieldset[data-section="background"] > div:not(:first)');
        if(!val){
            fieldset.hide();
            css['background'] = 'none';
        }else{

            fieldset.css('display','table-row');
            val = cont.find('input[name="background-color"]').val();
            if(val) css['background-color'] = val;

            val = cont.find('input[name="background-image"]').val();
            if(val){
                css['background-image'] = val;  
                css['bg-image'] = cont.find('input[name="bg-image"]').val();
                val = cont.find('select[name="background-position"]').val();
                css['background-position'] = val;  
                val = cont.find('select[name="background-repeat"]').val();
                css['background-repeat'] = val;  
            } 
        }


        function __setDim(name){
            let ele = cont.find('input[name="'+name+'"]');
            let val = ele.val();
            if( (val!='' || val!='auto') && parseInt(val)>0){
                if(!(val.indexOf('%')>0 || val.indexOf('px')>0)){
                    val = val + 'px';
                }
                css[name] = val;
            }
        }

        __setDim('width');
        __setDim('height');

        if(margin_mode_full){
            __setDim('margin-left');
            __setDim('margin-top');
            __setDim('margin-bottom');
            __setDim('margin-right');
            __setDim('padding-left');
            __setDim('padding-top');
            __setDim('padding-bottom');
            __setDim('padding-right');
        }else{
            __setDim('margin');
            __setDim('padding');
        }

        if(l_cfg.css){
            let old_css = l_cfg.css;
            //remove these parameters from css and assign from form
            let params = ['display','width','height',
                'padding','padding-left','padding-top','padding-bottom','padding-right',
                'margin','margin-left','margin-top','margin-bottom','margin-right',
                'background','background-image','bg-image',
                'flex-direction','flex-wrap','justify-content','align-items','align-content'];
            for(let i=0; i<params.length; i++){
                let prm = params[i];
                if (old_css[prm] && (prm.indexOf('margin')<0 || old_css[prm]!='auto')){ //drop old value
                    old_css[prm] = null;
                    delete old_css[prm];
                };
            }
            css = $.extend(old_css, css);
        }

        l_cfg.css = css;
        _assignCssTextArea();
        element.removeAttr('style');
        element.css(css); //assign changed css at once

        return css;
    }

    //
    //
    //
    function _onMarginSync(event){
        
        let type = $(event.target).attr('data-type');
        
        if($(event.target).is(':checked')){
            
            //disable
            
            

            $container.find('input[name^="'+type+'-"]').prop('readonly',true);
            $container.find('input[name^="'+type+'-left"]').removeProp('readonly');
            
            _onMarginSyncVal(null, type)
            
        }else{
            $container.find('input[name^="'+type+'-"]').removeProp('readonly');
            
        }       
    }
    
    //
    //
    //
    function _onMarginSyncVal(event, type){
        
        if(!type){
            type = $(event.target).attr('name');
            type = type.substr(0,type.indexOf('-'));
        }
        
        if($container.find('.cb_sync[data-type="'+type+'"]').is(':checked')){
        
                let val = $container.find('input[name="'+type+'-left"]').val();
                $container.find('input[name="'+type+'-top"]').val(val);
                $container.find('input[name="'+type+'-bottom"]').val(val);
                $container.find('input[name="'+type+'-right"]').val(val);
                _getCss();
        }
    }

    //
    //
    //
    function _onMarginMode(){
        let cont = $container;
        let btn = cont.find('.margin-mode').hide();
        if(margin_mode_full){
            btn.text('short');
            cont.find('.margin-short').hide();
            cont.find('.margin-full').css({display: 'inline-block'});
        }else{
            btn.text('full');
            cont.find('.margin-short').show();
            cont.find('.margin-full').hide();
        }
    }

    //
    //
    //
    function _enableSave(){
        window.hWin.HEURIST4.util.setDisabled($container.find('.btn-save-element'), false);
        window.hWin.HEURIST4.util.setDisabled($container.find('.btn-save-page'), false);
    }
    
    //
    //
    //
    function _assignCssTextArea(){

        let s = '';
        let has_border_prop = false;
        if(l_cfg.css){
            
            let border_styles = [];
            $("#border-style option").each(function()
            {
                border_styles.push($(this).val());
            });

            s = [];
            for(const [style, value] of Object.entries(l_cfg.css)){

                if(style == 'border'){ // translate border property to individual components

                    if(Object.hasOwn(l_cfg.css, 'border-style')){
                        has_border_prop = true;
                        continue;
                    }

                    let parts = value.split(' ');

                    let part_zero_style = (parts.length == 2 && border_styles.indexOf(parts[0]) !== -1);
                    let part_one_style = (parts.length == 2 && border_styles.indexOf(parts[1]) !== -1);

                    // Width
                    if(parts.length == 3 || part_one_style){

                        if(parts[0].indexOf('px') === -1 && isNaN(parts[0])){ // something else
                            s.push(`${style}: ${value}`);
                            continue;
                        }

                        let px = parts[0].indexOf('px') === -1 ? `${parts[0]}px` : parts[0];
                        s.push(`border-width: ${px}`);

                        l_cfg.css['border-width'] = px;
                    }

                    // Style
                    if(parts.length == 1 || part_zero_style){
                        s.push(`border-style: ${parts[0]}`);
                        l_cfg.css['border-style'] = parts[0];
                    }else if(parts.length == 3 || part_one_style){
                        s.push(`border-style: ${parts[1]}`);
                        l_cfg.css['border-style'] = parts[1];
                    }

                    // Colour
                    if(parts.length == 3 || part_one_style){

                        let idx = parts.length - 1;

                        if(parts[idx].indexOf('rgb') !== -1){ // change rgb to hex

                            let rgb = parts[idx];
                            let matches = rgb.matches(/rgb\(\s*(\d+),\s*(\d+),\s*(\d+)\)/);

                            let r = matches?.[1] ? parseInt(matches[1]) : '00';
                            let g = matches?.[2] ? parseInt(matches[2]) : '00';
                            let b = matches?.[3] ? parseInt(matches[3]) : '00';

                            parts[idx] = window.hWin.HEURIST4.ui.rgbToHex(r, b, g);
                        }

                        s.push(`border-color: ${parts[idx]}`);

                        l_cfg.css['border-color'] = parts[idx];
                    }

                    has_border_prop = true;

                }else{
                    s.push(`${style}: ${value}`);
                }
            }

            if(has_border_prop){
                delete l_cfg.css['border'];
            }

            s = s.join(';\n');
            s += !window.hWin.HEURIST4.util.isempty(s) ? ';' : '';
        }
        
        $container.find('textarea[name="elementCss"]').val(s);    
    }
    
    //
    //
    //
    function _assignCssToUI(){        
            
            let cont = $container;
            
            //assign flex css parameters
            let params = ['display','flex-direction','flex-wrap','justify-content','align-items','align-content'];
            for(let i=0; i<params.length; i++){
                let prm = params[i];
                if (l_cfg.css[prm]) cont.find('#'+prm).val(l_cfg.css[prm]);
            }

            let no_margin_values = true, mode_full = false;    
            //assign other css parameters
            cont.find('[data-type="css"]').each(function(i,item){
                let key = $(item).attr('name');
                let val = l_cfg.css[key];
                if(key=='background'){
                    $(item).prop('checked', val='none');
                }else if(val){
                    $(item).val($(item).attr('type')=='number'?parseInt(val):val);
                }
                
                if(!mode_full && !window.hWin.HEURIST4.util.isempty(val)){
                    if(key.indexOf('padding')===0 || 
                       key.indexOf('margin')===0){
                           
                       no_margin_values = false;
                           
                       mode_full = (key.indexOf('-') > 0);    
                    }
                }
            });
            margin_mode_full = true;
            //init file picker
            cont.find('input[name="bg-image"]')
                    .on('click',_selecHeuristMedia);
            cont.find('#btn-background-image').button()
                    .css({'font-size':'0.7em'})
                    .on('click',_selecHeuristMedia);

            cont.find('#btn-background-image-clear')
                    .button() //{icon:'ui-icon-close',showLabel:false})
                    .css({'font-size':'0.7em'})
                    .on('click',_clearBgImage);
            
            //init color pickers
            cont.find('input[name$="-color"]').colorpicker({
                hideButton: false, //show button right to input
                showOn: "both"});//,val:value
            cont.find('input[name$="-color"]').parent('.evo-cp-wrap').css({display:'inline-block',width:'100px'});

            //initially hide-show        
            if(cont.find('#display').val()=='flex'){
                cont.find('.flex-select').each(function(i,item){ $(item).parent().show(); })
            }else{
                cont.find('.flex-select').each(function(i,item){ $(item).parent().hide(); })
            }
            
            let fieldset = cont.find('fieldset[data-section="border"] > div:not(:first)');
            if(cont.find('#border-style').val()=='none'){
                fieldset.hide();
            }else{
                fieldset.css('display','table-row');
                fieldset.find('[name="border-color"]').trigger('keyup'); // trigger colour change
            }
            if(cont.find('#border-style').hSelect('instance') !== undefined){ // update border style dropdown
                cont.find('#border-style').hSelect('refresh');
            }
            fieldset = cont.find('fieldset[data-section="background"] > div:not(:first)');
            if(cont.find('input[name="background"]').is(':checked')){
                fieldset.css('display','table-row');
            }else{
                fieldset.hide();
            }
            
            _onMarginMode();
    }

    
   
    // NOT USED
    // from UI to element properties/css
    //
    function _getValues(){
        return '';
    }//_getValues


    //
    // init codemirror editor - direct html editor
    //
    function _initCodeEditor() {
        
        let $dlg;
        
        let ce_container = $container.find('#codemirror-body');

        if(codeEditor==null){
            
                //document.getElementById('codemirror-container')
                codeEditor = CodeMirror(ce_container[0], {
                    mode           : "htmlmixed",
                    tabSize        : 2,
                    indentUnit     : 2,
                    indentWithTabs : false,
                    lineNumbers    : false,
                    matchBrackets  : true,
                    smartIndent    : true,
                    /*extraKeys: {
                        "Enter": function(e){
                            insertAtCursor(null, "");
                        }
                    },*/
                    onFocus:function(){},
                    onBlur:function(){}
                });
        }        

        let contents = null; //keep translations
                
        let codeEditorBtns = [
                    {text:window.hWin.HR('Cancel'), 
                        class:'btnCancel',
                        css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
                        click: function() { 
                            codeEditorDlg.dialog( "close" );
                    }},
                    {text:window.hWin.HR('Apply'), 
                        class:'ui-button-action btnDoAction',
                        //disabled:'disabled',
                        css:{'float':'right'}, 
                        click: function() { 
                            let newval = codeEditor.getValue();

                            if(contents==null){ //no languages defined
                                if(l_cfg.content != newval){
                                    _enableSave();                
                                    element.html(newval);    
                                    l_cfg.content = newval;
                                }
                            }else{ //multilang
                                let cur_lang = ce_container.attr('data-lang');
                                contents[cur_lang] = newval;
                                let langs = Object.keys(contents);
                                for(let i=0; i<langs.length; i++){
                                    let lang_key = 'content'+langs[i];
                                    if(default_language.toUpperCase()==langs[i]){
                                        lang_key = 'content';
                                    }
                                    if(l_cfg[lang_key] != contents[langs[i]]){
                                        
                                        l_cfg[lang_key] = contents[langs[i]];
                                        _enableSave();
                                        if(current_language.toUpperCase()==langs[i]){
                                            element.html(l_cfg[lang_key]);    
                                        }
                                    }
                                }
                            }
                            
                            codeEditorDlg.dialog( "close" );    
                }}]; 
                
        //add language buttons
        if(website_languages!=''){
            let langs = website_languages.split(',');
            if(langs.length>0){
                contents ={};
                
                for(let i=0;i<langs.length;i++){

                     let lang = langs[i].toUpperCase();                     
                     if(Object.hasOwn(l_cfg, 'content'+lang)){
                            contents[lang] = l_cfg['content'+lang];    
                     }else{
                            contents[lang] = l_cfg['content'];
                     }
                    
                     //swticth language buttons   
                     codeEditorBtns.push({
                text: lang,
                'data-lang': lang,
                css:{'float':'left'}, 
                click: function(event) {  //switch language
                    
                    //keep previous
                    let newval = codeEditor.getValue();
                    let cur_lang = ce_container.attr('data-lang');
                    
                    if(contents[cur_lang]!=newval){
                        contents[cur_lang] = newval; 
                    }
                    
                    let new_lang = $(event.target).text();
                    if(window.hWin.HEURIST4.util.isempty(contents[new_lang])) contents[new_lang] = ' ';
                    codeEditor.setValue(contents[new_lang]);
                    ce_container.attr('data-lang',new_lang);
                    
                    if(new_lang==''){
            codeEditorDlg.parent().find('.ui-dialog-buttonset').find('[data-lang]').hide();            
                    }else{
            codeEditorDlg.parent().find('.ui-dialog-buttonset').find('[data-lang]').show();
            codeEditorDlg.parent().find('.ui-dialog-buttonset').find('[data-lang]').removeClass('ui-button-action');
            codeEditorDlg.parent().find('.ui-dialog-buttonset').find(`[data-lang=${new_lang}]`).addClass('ui-button-action');
                    }
                    
                    
                    }});
                    
                }//for
            }
        }
        
        
        codeEditorDlg = window.hWin.HEURIST4.msg.showElementAsDialog({
            window:  window.hWin, //opener is top most heurist window
            title: window.hWin.HR('Edit HTML source for element '+l_cfg.name),
            width: 800,
            height: 600,
            element: $container.find('#codemirror-container')[0] ,
            resizable: true,
            buttons: codeEditorBtns,
            //h6style_class: 'ui-heurist-publish',
            default_palette_class: 'ui-heurist-publish'
            //close: function(){}
        });     
        
        codeEditorDlg.parent().find('.ui-dialog-buttonset').css({width:'100%'});
        
        //preformat - break lines for widget options
        /*
        var ele = $('<div>').html(l_cfg.content);
        $.each(ele.find('div[data-heurist-app-id]'),function(i,el){
            var s = $(el).text();
            s = "\n"+s.replace(/,/g, ", \n");
            $(el).text(s);
        });
        var content = ele.html();
        */


        let initial_content, init_lang;
        if(contents==null){
            //languages not defined
            //assign content to editor (default language)
            initial_content = l_cfg.content;
            
        }else{
            if(default_language && contents[default_language.toUpperCase()]){
                init_lang = default_language.toUpperCase();
            }else{
                init_lang = Object.keys(contents)[0];
            }
            
            initial_content = contents[init_lang];
            ce_container.attr('data-lang', init_lang);
            
                    if(init_lang==''){
            codeEditorDlg.parent().find('.ui-dialog-buttonset').find('[data-lang]').hide();            
                    }else{
            codeEditorDlg.parent().find('.ui-dialog-buttonset').find('[data-lang]').show();            
            codeEditorDlg.parent().find('.ui-dialog-buttonset').find('[data-lang]').removeClass('ui-button-action');
            codeEditorDlg.parent().find('.ui-dialog-buttonset').find(`[data-lang=${init_lang}]`).addClass('ui-button-action');
                    }
            
        }
        if(window.hWin.HEURIST4.util.isempty(initial_content)) initial_content = ' ';
        codeEditor.setValue(initial_content);

        //autoformat
        setTimeout(function(){
                   
                    
                    let totalLines = codeEditor.lineCount();  
                    codeEditor.autoFormatRange({line:0, ch:0}, {line:totalLines});                    
                    codeEditor.scrollTo(0,0);
                    codeEditor.setCursor(0,0); //clear selection
                    
                    codeEditor.focus()
                   
                },500);
    }
    
    function _clearBgImage(){
        $container.find('input[name="background-image"]').val('');
        $container.find('input[name="bg-image"]').val('');
        
        _getCss();
        _enableSave();
    }
    
    //
    //
    //
    function _selecHeuristMedia(){

        let popup_options = {
            isdialog: true,
            select_mode: 'select_single',
            edit_addrecordfirst: false, //show editor atonce
            selectOnSave: true,
            select_return_mode:'recordset', //ids or recordset(for files)
            filter_group_selected:null,
            filter_types: 'image',
            //filter_groups: this.configMode.filter_group,
            onselect:function(event, data){

                if(data){

                    if( window.hWin.HEURIST4.util.isRecordSet(data.selection) ){
                        let recordset = data.selection;
                        let record = recordset.getFirstRecord();
                        
                        let sUrl = recordset.fld(record,'ulf_ExternalFileReference');
                        if(!sUrl){
                            //always add media as reference to production version of heurist code (not dev version)
                            sUrl = window.hWin.HAPI4.baseURL_pro+'?db='+window.hWin.HAPI4.database
                            +"&file="+recordset.fld(record,'ulf_ObfuscatedFileID');
                            $container.find('input[name="bg-image"]').val(recordset.fld(record,'ulf_OrigFileName'));
                        }else{
                            $container.find('input[name="bg-image"]').val(sUrl);
                        }
                        
                        sUrl = 'url(\'' + sUrl + '\')';
                        $container.find('input[name="background-image"]').val(sUrl);
                        
                        _getCss();
                        _enableSave();

                    }

                }//data

            }
        };//popup_options        

        window.hWin.HEURIST4.ui.showEntityDialog('recUploadedFiles', popup_options);
    }

    //
    //
    //    
    function _warningOnExit( callback ){
        
        if($container.find('.btn-save-element').attr('disabled')!='disabled'){
            
            let $dlg;
            let _buttons = [
                {text:window.hWin.HR('Save'), 
                    click: function(){
                        $container.find('.btn-save-element').trigger('click');
                        $dlg.dialog('close');
                        if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call(this);
                    }
                },
                {text:window.hWin.HR('Discard'), 
                    click: function(){
                        $container.find('.btn-cancel').trigger('click');
                        $dlg.dialog('close'); 
                        if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call(this);
                    }
                },
                {text:window.hWin.HR('Cancel'), 
                    click: function(){$dlg.dialog('close');}
                }
            ];            
            
            let sMsg = '"'+ window.hWin.HEURIST4.util.stripTags(l_cfg.name) 
                    +'" '+window.hWin.HR('element has been modified');
            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(sMsg, _buttons, {title:window.hWin.HR('Element changed')});   

            return true;     
        }else{
            return false;     
        }
        
        
    }
    
        

    //public members
    let that = {

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        },
        
        warningOnExit: function( callback ){
            return _warningOnExit( callback );
        },
        
        isModified: function(){
            return $container.find('.btn-save-element').attr('disabled')!='disabled';
        },
        
        //update from main editor
        updateContent: function(new_content, lang){
            l_cfg['content'+lang] = new_content;            
        },
        
        onContentChange: function(){
            _enableSave();
        },
        
        getKey: function(){
            return element_cfg.key;
        }
    }

    _init();
    
    return that;
}



