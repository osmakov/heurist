/**
* editCMS_WidgetCfg.js - configuration dialog for widget properties - this is either popup or use provided container
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

/* global layoutMgr */

//
// widget_cfg -json cfg for widget to be edited 
// _layout_content- json cfg for website
//
function editCMS_WidgetCfg( widget_cfg, _layout_content, $dlg, main_callback, on_change = null ){

    const _className = 'editCMS_WidgetCfg';

    const _def_labels = {
        heurist_SearchInput: {
            placeholder_def: 'filter_placeholder',
            empty_remark_def: 'No default'
        },
        heurist_resultList: {
            placeholder_def: 'No default',
            empty_remark_def: 'resultList_empty_remark'
        },
        heurist_resultListExt: {
            placeholder_def: 'resultListExt_placeholder_text',
            empty_remark_def: 'resultListExt_empty_remark'
        },
        heurist_resultListDataTable: {
            placeholder_def: 'No default',
            empty_remark_def: 'No default'
        },
        heurist_StoryMap: {
            placeholder_def: 'Please select a story in the list',
            elementsPlaceholder_def: 'There are no story elements to display for the selected item',
            elementsPlaceholderSub_def: 'Story elements may exist but not be publicly visible'
        }
    };

    let _using_dialog = !$dlg || $dlg.length == 0;
    
    function _init(){

        let buttons= [
            {text:window.hWin.HR('Cancel'), 
                class:'btnCancel',
                css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
                click: function() { 
                    $dlg.dialog( "close" );
            }},
            {text:window.hWin.HR('Apply'), 
                class:'ui-button-action btnDoAction',
                //disabled:'disabled',
                css:{'float':'right'}, 
                click: function() { 
                        let config = _getValues();
                        if(config!==false){
                            main_callback.call(this, config);
                            $dlg.dialog( "close" );    
                        }
        }}];

        if(!window.hWin.HEURIST4.util.isFunction(on_change)){
            on_change = $.noop();
        }
        
        if($dlg && $dlg.length>0){
            //container provided

            $dlg.empty().load(window.hWin.HAPI4.baseURL
                +'hclient/widgets/cms/editCMS_WidgetCfg.html',
                _initControls
            );

        }else{
            //open as popup

            let height = 700;
            let width = 800;
            if(widget_cfg.appid == 'heurist_StoryMap'){
                height = 900;
            }else if(widget_cfg.appid == 'heurist_resultList'){
                height = 850;
                width = 850;
            }else if(widget_cfg.appid == 'heurist_resultListExt'){
                height = 710;
            }

            $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
                +"hclient/widgets/cms/editCMS_WidgetCfg.html?t="+(new Date().getTime()), 
                buttons, `Modify ${widget_cfg.name ? widget_cfg.name : 'Widget'} Properties`, 
                {  container:'cms-add-widget-popup',
                    default_palette_class: 'ui-heurist-publish',
                    width: width,
                    height: height,
                    close: function(){
                        $dlg.dialog('destroy');       
                        $dlg.remove();
                    },
                    open: _initControls
            });

        }



    }
    
    //
    // Assign widget properties to UI
    //
    function _initControls(){
        
        let widget_name = widget_cfg.appid;
        let opts = window.hWin.HEURIST4.util.isJSON(widget_cfg.options);

        if(_using_dialog){

            $dlg.find('.header').css('width', '160px');

            $dlg.find('.cms-widget-config').parent()
                .addClass('ent_content_full')
                .css({
                    top: '0px',
                    'overflow-x': 'hidden'
                });
        }

        $dlg.find('.cms-widget-config').tabs();

        let disabled_tabs = [];
        let active_idx = null;

        let last_idx = $dlg.find('.ui-tabs-panel').length - 1; // Connections tab, all widgets have connection field(s)

        $dlg.find('.ui-tabs-panel').each(function(idx, ele){
            if($(ele).find(`div.${widget_name}`).length == 0 && idx != last_idx){
                disabled_tabs.push(idx);
            }else if(active_idx == null){
                active_idx = idx;
            }
        });
        active_idx = active_idx == null ? 0 : active_idx;

        $dlg.find('.cms-widget-config').tabs('option', 'disabled', disabled_tabs);
        $dlg.find('.cms-widget-config').tabs('option', 'active', active_idx);

        $dlg.find('div[class^="heurist_"], hr[class^="heurist_"]').hide(); //hide all
        $dlg.find('div.'+widget_name+', hr.'+widget_name).show();
        
        //fill page list
        let selPage = $dlg.find('select[name="search_page"]');
        
        let main_menu = $('#main-menu > div[widgetid="heurist_Navigation"]');
        
        if(main_menu.length>0 && widget_name!='heurist_StoryMap'){
            let pages = main_menu.navigation('getMenuContent','list');
            if(!pages){
                selPage.parent().hide();
            }else{
                pages.unshift({key:'',title:''});   
                window.hWin.HEURIST4.ui.createSelector(selPage[0], pages);
            }
           
        }else{
           
            selPage.parent().hide();
        }
        
        if(opts!==false){
            
            //find map widget on this page
            if(widget_name=='heurist_StoryMap'){
                if(!opts.map_widget_id){
                    const ele = layoutMgr.layoutContentFindWidget(_layout_content, 'heurist_Map');
                    
                    if(ele && ele.options.search_realm=='' && ele.dom_id){
                        opts.map_widget_id = ele.dom_id;
                    }
                }
            }
            
            //find and assign prevail search group (except heurist_Map if heurist_StoryMap exists)
            if(!opts.search_realm){ //not defined yet
            
                if(!(widget_name=='heurist_Map' && layoutMgr.layoutContentFindWidget(_layout_content, 'heurist_StoryMap')!=null)){
                    let sg = layoutMgr.layoutContentFindMainRealm(_layout_content);    
                    if(sg=='') sg = 'search_group_1';
                    opts.search_realm = sg;
                }
            }
            
        
            
            if(opts.search_page) {
                $(selPage).val(opts.search_page);        
               
            }
            $dlg.find('input[name="search_realm"]').val(opts.search_realm);    
            $dlg.find('input[name="widget_id"]').val(opts.widget_id);    

            if(widget_name=='heurist_Map'){
                //special behaviour for map widget
                if(!opts.layout_params){
                    opts.layout_params = {};
                }
                
                //map symbology editor            
                window.hWin.HEURIST4.ui.initEditSymbologyControl($dlg.find('#map_default_style'), opts.layout_params.style);
                window.hWin.HEURIST4.ui.initEditSymbologyControl($dlg.find('#map_select_style'), opts.layout_params.selection_style );

                if(opts.layout_params){
                    
                    $dlg.find("#use_timeline").prop('checked', !opts.layout_params.notimeline);    
                    $dlg.find("#map_rollover").prop('checked', opts.layout_params.map_rollover);    
                    $dlg.find("#use_cluster").prop('checked', !opts.layout_params.nocluster);    
                    $dlg.find("#editstyle").prop('checked', opts.layout_params.editstyle);    
                    $dlg.find("#smooth_zoom").prop('checked', opts.layout_params.smooth_zoom);    
                    $dlg.find("#zoom_delta").val(opts.layout_params.zoom_delta>0?opts.layout_params.zoom_delta:1);    
                    $dlg.find("#zoom_to_selected").prop('checked', opts.layout_params.zoom_to_selected);    

                    let ctrls = (opts.layout_params.controls)?opts.layout_params.controls.split(','):[];
                    $dlg.find('input[name="controls"]').each(
                        function(idx,item){$(item).prop('checked',ctrls.indexOf($(item).val())>=0);}
                    );
                                                                
                    let legend = (opts.layout_params.legend)?opts.layout_params.legend.split(','):[];
                    if(legend.length>0){

                        $dlg.find('input[name="legend_exp2"]').prop('checked',legend.indexOf('off')<0); //expand on start

                        $.each(legend, function(i, val){
                            if(parseInt(val)>0){
                                $dlg.find('input[name="legend_width"]').val(val);        
                            }else if(val!='off'){
                                let is_exp = (val[0]!='-')
                                if (!is_exp) legend[i] = val.substring(1);
                                $dlg.find('input[name="legend_exp"][value="'+legend[i]+'"]').prop('checked',is_exp);
                            }
                        });

                        if(legend.indexOf('basemap')>=0 || legend.indexOf('mapdocs')>=0 || legend.indexOf('search')>=0){
                            $dlg.find('input[name="legend"]').each(
                                function(idx,item){
                                    $(item).prop('checked',legend.indexOf($(item).val())>=0);
                                }
                            );
                        }else{
                            $dlg.find('input[name="legend"]').prop('checked', true);    
                        }
                        
                        

                    }
                    if(opts.layout_params['template']){
                        $dlg.find('select[name="map_template"]').attr('data-template', opts.layout_params['template']);        
                    }
                    let popup = 'standard';
                    if(opts.layout_params['template']=='none'){
                        popup = 'none';
                    }else if(opts.layout_params['popup']) {
                        popup = opts.layout_params['popup'];
                    }
                    $dlg.find('input[name="map_popup"][value="'+popup+'"]').prop('checked', true);        
                    
                    if(opts.layout_params['basemap']){
                        $dlg.find('select[name="map_basemap"]').attr('data-basemap', opts.layout_params['basemap']);    
                    }
                    if(opts.layout_params['basemap_filter']){
                        $dlg.find('input[name="map_basemap_filter"]').val(opts.layout_params['basemap_filter']);        
                    }

                    if(opts.layout_params['popup_behaviour']){
                        $dlg.find('input[name="popup_behaviour"][value="'+ opts.layout_params['popup_behaviour'] +'"]').prop('checked', true);

                        if(opts.layout_params['popup_behaviour'] == 'scale'){
                            $dlg.find('span#pw_label').text('Max width:');
                        }
                        if(opts.layout_params['popup_behaviour'] == 'scale' || opts.layout_params['popup_behaviour'] == 'fixed_width'){
                            $dlg.find('span#ph_label').text('Max length:');
                        }
                    }
                    if(opts.layout_params['popup_width']){

                        let value = opts.layout_params['popup_width'];
                        const unit = (value.indexOf('px') > 0) ? value.slice(-2) : value.slice(-1);
                        value = (value.indexOf('px') > 0) ? value.slice(0, -2) : value.slice(0, -1);

                        $dlg.find('input[name="popup_width"]').val(value); // first index
                        $dlg.find('select[name="popup_wunit"]').val(unit); // second index
                    }
                    if(opts.layout_params['popup_height']){

                        let value = opts.layout_params['popup_height'];
                        const unit = (value.indexOf('px') > 0) ? value.slice(-2) : value.slice(-1);
                        value = (value.indexOf('px') > 0) ? value.slice(0, -2) : value.slice(0, -1);

                        $dlg.find('input[name="popup_height"]').val(value); // first index
                        $dlg.find('select[name="popup_hunit"]').val(unit); // second index
                    }
                    if(opts.layout_params['popup_resizing']){
                        $dlg.find('input[name="popup_resizing"]').prop('checked', false); //opts.layout_params['popup_resizing']
                    }
                    
                    $dlg.find('input[name="ignore_mapdoc_zoom"]').prop('checked', false);
                    if(opts.layout_params['ignore_mapdoc_zoom']){
                        $dlg.find('input[name="ignore_mapdoc_zoom"]').prop('checked', opts.layout_params['ignore_mapdoc_zoom']);
                    }
                    if(opts.layout_params['maxzoom']>0){
                        $dlg.find('input[name="map_maxzoom"]').val(opts.layout_params['maxzoom']);        
                    }
                    if(opts.layout_params['minzoom']>0){
                        $dlg.find('input[name="map_minzoom"]').val(opts.layout_params['minzoom']);        
                    }
                    if(opts.layout_params['pntzoom']>0){
                        $dlg.find('input[name="map_pntzoom"]').val(opts.layout_params['pntzoom']);        
                    }
                    if(opts.layout_params['mapdocuments']){
                        $dlg.find('input[name="mapdocuments"]').attr('data-mapdocuments', opts.layout_params['mapdocuments']);        
                    }
                    if(opts.layout_params['basemaps']){
                        $dlg.find('input[name="basemaps"]').val(opts.layout_params['basemaps']);        
                    }
                }
                if(opts['mapdocument']>0){
                    $dlg.find('select[name="mapdocument"]').attr('data-mapdocument', opts['mapdocument']);        
                }
                
                if(opts['custom_links']){
                    $dlg.find('textarea[name="custom_links"]').val(opts['custom_links']);    
                }
                if(opts['current_search_filter']){
                    $dlg.find('input[name="current_search_filter"]').val(opts['current_search_filter']);    
                }

                $dlg.find('button[name="basemap_filter"]')
                    .button()
                    .css('font-size','0.7em')
                    .on( { click: function(){
                        let cfg = window.hWin.HEURIST4.util.isJSON($dlg.find('input[name="map_basemap_filter"]').val());
                        if(!cfg) cfg = null;
                        window.hWin.HEURIST4.ui.showImgFilterDialog(cfg, function(filter){
                            $dlg.find('input[name="map_basemap_filter"]').val( JSON.stringify(filter) );
                        });   
                    }});
                $dlg.find('button[name="basemap_filter_reset"]')
                    .button()
                    .css('font-size','0.7em')
                    .on( { click: function(){
                        $dlg.find('input[name="map_basemap_filter"]').val('');
                    }});
                
            }else{

                $dlg.find('div.'+widget_name+' input').each(function(idx, item){
                    item = $(item);
                    if(item.attr('name')){
                        if(item.attr('type')=='checkbox'){
                            item.prop('checked', opts[item.attr('name')]===true || opts[item.attr('name')]=='true');
                        }else if(item.attr('type')=='radio'){
                            item.prop('checked', item.val()== String(opts[item.attr('name')]));
                        }else {
                            item.val( opts[item.attr('name')] );
                        }
                    }
                });
                $dlg.find('div.'+widget_name+' select').each(function(idx, item){
                    item = $(item);
                    item.val( opts[item.attr('name')] );
                });

                //
                // assign paramters
                //
                if(widget_name=='heurist_resultListExt'){ // custom report
                    if(opts['template']){
                        $dlg.find('select[name="rep_template"]').attr('data-template', opts['template']);        
                    }
                    
                    $dlg.find('#empty_remark').val(opts['empty_remark']?opts['empty_remark']:opts['emptysetmessage']); //for empty selection
                    $dlg.find('#placeholder_text').val(opts['placeholder_text']); //for empty result

                    if(!opts['selection_mode']){

                        if(opts['is_single_selection']){
                            opts['selection_mode'] = 'is_single_selection';
                        }else{
                            opts['selection_mode'] = 'show_all';
                        }

                        $dlg.find(`input[value="${opts['selection_mode']}"]`).prop('checked', true);
                    }
                }else if(widget_name=='heurist_resultList'){ // standard result list
                    if(opts['rendererExpandDetails']){
                        $dlg.find('select[name="rendererExpandDetails"]').attr('data-template', opts['rendererExpandDetails']);        
                    }
                    $dlg.find('#empty_remark').val(opts['empty_remark']);
                    $dlg.find('#placeholder_text').val(opts['placeholder_text']);
                    if(opts['field_for_ext_classes']){
                        $dlg.find('select[name="field_for_ext_classes"]').attr('fld-id', opts['field_for_ext_classes']);
                    }
                    if(opts['recview_dimensions']){

                        let popup_dims = opts['recview_dimensions'];

                        let h_unit = popup_dims.height.indexOf('px') !== -1 ? 'px' : '%';
                        let w_unit = popup_dims.width.indexOf('px') !== -1 ? 'px' : '%';
                        let l_unit = popup_dims.left.indexOf('px') !== -1 ? 'px' : '%';
                        let t_unit = popup_dims.top.indexOf('px') !== -1 ? 'px' : '%';

                        $dlg.find('[name="dialog_hunit"]').val(h_unit);
                        $dlg.find('[name="dialog_wunit"]').val(w_unit);
                        $dlg.find('[name="dialog_lunit"]').val(l_unit);
                        $dlg.find('[name="dialog_tunit"]').val(t_unit);

                        let h_val = popup_dims.height.replace(/\D+/, '');
                        let w_val = popup_dims.width.replace(/\D+/, '');
                        let l_val = popup_dims.left.replace(/\D+/, '');
                        let t_val = popup_dims.top.replace(/\D+/, '');

                        $dlg.find('[name="dialog_height"]').val(h_val);
                        $dlg.find('[name="dialog_width"]').val(w_val);
                        $dlg.find('[name="dialog_left"]').val(l_val);
                        $dlg.find('[name="dialog_top"]').val(t_val);
                    }

                    $dlg.find('[name="field_for_ext_classes"]').prev().css('width', '210px');
                }else if(widget_name=='heurist_resultListDataTable'){ // datatable
                    $dlg.find('#dataTableParams').val(opts['dataTableParams']);
                    $dlg.find('#empty_remark').val(opts['emptyTableMsg']);
                    $dlg.find('#placeholder_text').val(opts['placeholder_text']);
                }else if(widget_name=='heurist_SearchInput'){
                    $dlg.find('#si_placeholder_text').val(opts['placeholder_text']);
                }else if(widget_name=='heurist_StoryMap'){
                    window.hWin.HEURIST4.ui.initEditSymbologyControl($dlg.find('#def_map_symbology'), opts.def_map_symbology);
                    window.hWin.HEURIST4.ui.initEditSymbologyControl($dlg.find('#def_story_symbology'), opts.def_story_symbology);
                    $dlg.find('#placeholder_text').val(opts['storyPlaceholder']);
                    $dlg.find('#elementsPlaceholder').val(opts['elementsPlaceholder']);
                }

                if(_def_labels[widget_name]){ // fill in default labels

                    let def_placeholder = window.hWin.HR(_def_labels[widget_name]['placeholder_def']);

                    let def_remark = window.hWin.HR(_def_labels[widget_name]['empty_remark_def']);

                    $dlg.find(`.${widget_name} #placeholder_def`).text(def_placeholder).attr('title', def_placeholder);
                    $dlg.find(`.${widget_name} #empty_remark_def`).text(def_remark).attr('title', def_remark);

                    if(widget_name == 'heurist_StoryMap'){
                        let label = window.HR(_def_labels[widget_name]['elementsPlaceholder_def']);
                        $dlg.find('#elementsPlaceholder_def').text(label).attr('title', label);
                    }
                }

                if(opts['blank_placeholder'] || $dlg.find('#placeholder_text').val() == 'def'){ // replace 'def' with a blank
                    $dlg.find('#placeholder_text').val('');
                }
                if(opts['si_blank_placeholder'] || $dlg.find('#si_placeholder_text').val() == 'def'){ // replace 'def' with a blank
                    $dlg.find('#si_placeholder_text').val('');
                }
                if(opts['blank_empty_remark'] || $dlg.find('#empty_remark').val() == 'def'){ // replace 'def' with a blank
                    $dlg.find('#empty_remark').val('');
                }
                if(widget_name == 'heurist_StoryMap' && 
                    (opts['blank_elementsPlaceholder'] || $dlg.find('#elementsPlaceholder').val() == 'def')){ // replace 'def' with a blank
                    $dlg.find('#elementsPlaceholder').val('');
                }

                if(opts['export_options']){

                    let export_options = [];
                    
                    if(opts['export_options']===true){
                        opts['export_options'] = 'all';
                    }

                    if(!window.hWin.HEURIST4.util.isempty(opts['export_options']) && opts['export_options'] != 'all'){
                        export_options = opts['export_options'].split(',');
                    }else{
                        export_options = 'all';
                    }

                    $.each($dlg.find(`.${widget_name} input[name="export_options"]`), function(idx, ele){
                        let is_checked = export_options.indexOf($(ele).val()) !== -1;
                        $(ele).prop('checked', is_checked);
                    });
                }
            }

            
            // init special edit elements            
            if(widget_name=='heurist_Navigation'){

                let rval = $dlg.find('input[name="menu_recIDs"]').val();
                rval =  rval?rval.split(','):[];

                let ele = $dlg.find('#menu_recIDs');

                if(!ele.editing_input('instance')){

                    const ed_options = {
                        recID: -1,                                                                                       
                        dtID: ele.attr('id'), //'group_selector',
                        //show_header: false,
                        values: rval,
                        readonly: false,
                        showclear_button: true,
                        dtFields:{
                            dty_Type:"resource", rst_MaxValues:0,
                            rst_DisplayName: 'Top level menu items', rst_DisplayHelpText:'',
                            rst_PtrFilteredIDs: [window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'],
                                window.hWin.HAPI4.sysinfo['dbconst']['RT_WEB_CONTENT']],
                            rst_FieldConfig: {entity:'records', csv:false}
                        },
                        change: on_change
                    };

                    ele.editing_input(ed_options);
                    ele.parent().css('display','block');
                }

                $dlg.find('select[name="orientation"]')
                .on('change', function(e){

                    let is_horiz = ($(e.target).val()=='horizontal');
                    let is_vertical = ($(e.target).val()=='vertical');

                    if($(e.target).val()=='treeview'){
                        $dlg.find('#expandLevels').show();
                    }else{
                        $dlg.find('#expandLevels').hide();
                    }

                    if($dlg.find('#widgetCss').length>0){
                        let vals = $dlg.find('#widgetCss').val().split(';');
                        for (let i=0; i<vals.length; i++){
                            let vs = vals[i].split(':');
                            if(vs && vs.length==2){
                                vs[0] = vs[0].trim();
                                if(is_horiz){
                                    if(vs[0]=='width'){
                                        vals[i] = ('\nwidth: 100%');
                                    }else if(vs[0]=='height'){
                                        vals[i] = ('\nheight:50px');
                                    }
                                }else if(is_vertical){
                                    if(vs[0]=='width'){
                                        vals[i] = ('\nwidth: 50px');
                                    }else if(vs[0]=='height'){
                                        vals[i] = ('\nheight:100%');
                                    }
                                }else{
                                    if(vs[0]=='width'){
                                        vals[i] = ('\nwidth: 200px');
                                    }else if(vs[0]=='height'){
                                        vals[i] = ('\nheight:300px');
                                    }
                                }
                            }
                        }

                        $dlg.find('#widgetCss').val( vals.join(';'));
                    }
                });



            }else 
            if (widget_name=='heurist_recordAddButton'){

                    let ele = $dlg.find('button[name="add_record_cfg"]');

                    if(!ele.button('instance')){
                        
                        function __human_readble(){
                            
                            const rty_ID = $dlg.find('input[name="RecTypeID"]').val();
                            if(rty_ID>0){
                                $('#add_record_desc').html(
                                '&nbsp;&nbsp;<i>Record type: </i>'+$Db.rty(rty_ID,'rty_Name')
                                //+' <i>editable by user/group# </i>'+$dlg.find('input[name="OwnerUGrpID"]').val()
                                //+' <i>viewable by: </i>'+$dlg.find('input[name="NonOwnerVisibility"]').val()
                                );                                
                            }else{
                                $('#add_record_desc').html('');
                            }
                        }

                        ele.button().on('click',
                            function(){
                                window.hWin.HEURIST4.ui.showRecordActionDialog('recordAdd',{
                                    title: 'Select type and other parameters for new record',
                                    height: 520, width: 540,
                                    get_params_only: true,
                                    onClose: function(context){
                                        if(context && context.RecTypeID>0){
                                            $dlg.find('input[name="RecTypeID"]').val(context.RecTypeID);
                                            $dlg.find('input[name="OwnerUGrpID"]').val(context.OwnerUGrpID);
                                            $dlg.find('input[name="NonOwnerVisibility"]').val(context.NonOwnerVisibility);
                                            __human_readble();
                                        }

                                    },
                                    default_palette_class: 'ui-heurist-publish'                                        
                                    }
                                );    
                            }
                        );
                        __human_readble();
                    }

            }else
            if(widget_name=='heurist_SearchTree'){

                let ele_rb = $dlg.find('input[name="searchTreeMode"]')
                .on('change', function(e){

                    let selval = __getSearchTreeMode();

                    $dlg.find('#simple_search_header').parent().css('display','none');
                    $dlg.find('#simple_search_text').parent().css('display','none');

                    if(selval==0){
                        //buttons
                        $dlg.find('#allowed_UGrpID').css('display','table-row');
                        $dlg.find('#allowed_svsIDs').css('display','table-row');
                        $dlg.find('#allowed_UGrpID').editing_input('setDisabled', false);
                        $dlg.find('#allowed_svsIDs').editing_input('setDisabled', false);

                        let is_vis = $dlg.find('input[name="simple_search_allowed"]').is(':checked');
                        if(is_vis){
                            $dlg.find('#simple_search_header').parent().css('display','table-row');
                            $dlg.find('#simple_search_text').parent().css('display','table-row');
                        }else{
                            $dlg.find('#simple_search_header').parent().css('display','none');
                            $dlg.find('#simple_search_text').parent().css('display','none');
                        }
                    }else if(selval==1){
                        //tree
                        $dlg.find('#allowed_UGrpID').css('display','table-row');
                        $dlg.find('#allowed_svsIDs').css('display','none');
                        $dlg.find('#allowed_UGrpID').editing_input('setDisabled', false);
                        $dlg.find('#allowed_svsIDs').editing_input('setDisabled', true);
                        $dlg.find('input[name="allowed_svsIDs"]').val('');
                    }else{
                        //full
                        $dlg.find('#allowed_UGrpID').css('display','none');
                        $dlg.find('#allowed_svsIDs').css('display','none');
                        $dlg.find('#allowed_UGrpID').editing_input('setDisabled', true);
                        $dlg.find('#allowed_svsIDs').editing_input('setDisabled', true);
                        $dlg.find('input[name="allowed_UGrpID"]').val('');
                        $dlg.find('input[name="allowed_svsIDs"]').val('');
                    }
                });

                let selval = __getSearchTreeMode();
                if(window.hWin.HEURIST4.util.isempty(selval)
                    || !(selval==0 || selval==1 || selval==2)){
                    selval = 0;
                    $dlg.find('#heurist_SearchTreeMode1').prop('checked',true);
                }

                //visible for buttons and tree mode
                let ele = $dlg.find('#allowed_UGrpID');
                if(!ele.editing_input('instance')){

                    let init_val = $dlg.find('input[name="allowed_UGrpID"]').val();
                    if(init_val=='' && selval!=2 && $dlg.find('input[name="allowed_svsIDs"]').val()==''){
                        init_val = 5;//web search  by default
                    }

                    const ed_options = {
                        recID: -1,
                        dtID: ele.attr('id'), 
                        values: [init_val], 
                        readonly: false,
                        showclear_button: true,
                        dtFields:{
                            dty_Type:"resource", rst_MaxValues:1,
                            rst_DisplayName: 'EITHER Show all filters in these workgroups', 
                            rst_DisplayHelpText:'',
                            rst_FieldConfig: {entity:'sysGroups', csv:true}
                        },
                        change:function(){
                            $dlg.find('#allowed_svsIDs').editing_input('setValue','');
                            $dlg.find('#init_svsID').editing_input('setValue','');
                            __restFilterForInitSearch();
                            on_change();
                        }
                    };

                    ele.editing_input(ed_options);
                    ele.parent().css('display','block');
                    ele.find('.header').addClass('header_wide');
                }

                //visible for buttons mode only
                ele = $dlg.find('#allowed_svsIDs');
                if(!ele.editing_input('instance')){

                    const ed_options = {
                        recID: -1,
                        dtID: ele.attr('id'), 
                        values: [$dlg.find('input[name="allowed_svsIDs"]').val()],
                        readonly: false,
                        showclear_button: true,
                        dtFields:{
                            dty_Type:"resource", rst_MaxValues:1,
                            rst_DisplayName: 'OR Choose specific filters', rst_DisplayHelpText:'',
                            rst_FieldConfig: {entity:'usrSavedSearches', csv:true}
                        },
                        change:function(){
                            $dlg.find('#allowed_UGrpID').editing_input('setValue','');
                            $dlg.find('#init_svsID').editing_input('setValue','');
                            __restFilterForInitSearch();
                            on_change();
                        }
                    };

                    ele.editing_input(ed_options);
                    ele.parent().css('display','block');
                    ele.find('.header').addClass('header_wide');
                }

                ele = $dlg.find('#init_svsID');
                if(!ele.editing_input('instance')){

                    const ed_options = {
                        recID: -1,
                        dtID: ele.attr('id'), 
                        values: [$dlg.find('input[name="init_svsID"]').val()],
                        readonly: false,
                        showclear_button: true,
                        dtFields:{
                            dty_Type:"resource", rst_MaxValues:1,
                            rst_DisplayName: 'Trigger this filter on page load', rst_DisplayHelpText:'',
                            rst_FieldConfig: {entity:'usrSavedSearches', csv:false} 
                        },
                        change: on_change
                    };


                    ele.editing_input(ed_options);
                    ele.parent().css('display','block');
                    ele.find('.header').addClass('header_wide');
                }

                function __getSearchTreeMode(){
                    let ele_rb = $dlg.find('input[name="searchTreeMode"]')
                    let selval = '';
                    $.each(ele_rb, function(idx, item){
                        item = $(item);
                        if(item.is(':checked')){
                            selval = item.prop('value');
                            return false;
                        }
                    });
                    return selval
                }

                function __restFilterForInitSearch(){

                    let ifilter = null; 
                    let val = $dlg.find('#allowed_svsIDs').editing_input('getValues');
                   
                    if(!window.hWin.HEURIST4.util.isempty(val) && val[0]!=''){
                        if(Array.isArray(val)) val = val.join(',');
                        ifilter = {svs_ID:val};
                    }else{
                        val = $dlg.find('#allowed_UGrpID').editing_input('getValues');
                       
                        if(!window.hWin.HEURIST4.util.isempty(val) && val[0]!=''){
                            if(Array.isArray(val)) val = val.join(',');
                            ifilter = {svs_UGrpID:val};
                        }
                    }


                    ele.editing_input({
                        dtFields:{
                            dty_Type:"resource", rst_MaxValues:1,
                            rst_DisplayName: 'Initial filter', rst_DisplayHelpText:'',
                            rst_FieldConfig: {entity:'usrSavedSearches', csv:false,
                                initial_filter:ifilter, search_form_visible:(ifilter==null)    
                            } 
                        },
                        change: on_change
                    }); 
                }

                __restFilterForInitSearch();

                ele_rb.trigger('change');

                $dlg.find('input[name="simple_search_allowed"]').on('change', function(){
                    let is_vis = $dlg.find('input[name="simple_search_allowed"]').is(':checked');
                    if(is_vis){
                        $dlg.find('#simple_search_header').parent().css('display','table-row');
                        $dlg.find('#simple_search_text').parent().css('display','table-row');
                    }else{
                        $dlg.find('#simple_search_header').parent().css('display','none');
                        $dlg.find('#simple_search_text').parent().css('display','none');
                    }
                });

                let is_vis = $dlg.find('input[name="simple_search_allowed"]').is(':checked');
                if(is_vis){
                    $dlg.find('#simple_search_header').parent().css('display','table-row');
                    $dlg.find('#simple_search_text').parent().css('display','table-row');
                }else{
                    $dlg.find('#simple_search_header').parent().css('display','none');
                    $dlg.find('#simple_search_text').parent().css('display','none');
                }
            }else
            if(widget_name=='heurist_Map' && 
                $dlg.find('select[name="mapdocument"]').find('options').length==0){
                    
                let allMapDocuments = [];
                
                let $selectedDocuments =  $('input[name="mapdocuments"]');
                let $selectedBasemaps =  $('input[name="basemaps"]');
                
                function __assignMapDocNames(newsel){
                    if(newsel){
                        newsel = newsel.split(';');
                        let newsel_names = [];
                        $.each(allMapDocuments, function(i,item){
                            if(newsel.indexOf(item.key)>=0){
                                newsel_names.push(item.title)    
                            }
                        });
                        $selectedDocuments.val(newsel_names.join(';'));
                    }else{
                        $selectedDocuments.val('');
                    }
                }

                let $selectMapDoc = $dlg.find('select[name="mapdocument"]');
                //fill list of mapdpcuments
                let request = {
                    q: 't:'+window.hWin.HAPI4.sysinfo['dbconst']['RT_MAP_DOCUMENT'],w: 'a',
                    detail: 'header',
                    source: 'cms_edit'};
                //perform filter
                window.hWin.HAPI4.RecordMgr.search(request,
                    function(response){

						if(response.status == window.hWin.ResponseStatus.OK){

							let resdata = new HRecordSet(response.data);
							let idx, records = resdata.getRecords(), opts = [{key:'',title:'none'}];
							for(idx in records){
								if(idx)
								{
									let record = records[idx];
									opts.push({key:resdata.fld(record, 'rec_ID'), title:resdata.fld(record, 'rec_Title')});
								}
							}//for

							window.hWin.HEURIST4.ui.fillSelector($selectMapDoc[0], opts);
							if($selectMapDoc.attr('data-mapdocument')>0){
								$selectMapDoc.val( $selectMapDoc.attr('data-mapdocument') );
							}
							window.hWin.HEURIST4.ui.initHSelect($selectMapDoc[0], false, null, null, {menu_parent: $dlg});

                            allMapDocuments = opts;
                            allMapDocuments.splice(0,1); //remove none
                            
                            __assignMapDocNames($selectedDocuments.attr('data-mapdocuments'));
                            
						}else {
							window.hWin.HEURIST4.msg.showMsgErr(response);
						}
                    }
                );
                
                let $gicon = $('<span>').addClass('ui-icon ui-icon-gear')
                    .css({position:'absolute',margin:'7px 0 0 0px',cursor:'hand'})
                    .insertBefore($selectedDocuments);
                let $select_documents_dlg = $('<div/>').hide().appendTo( $selectedDocuments.parent() );
                
                $gicon.on({ click: function(){                                 
                       $select_documents_dlg.selectMultiValues({
                       onselect:function(newsel){
                            if(newsel){
                                newsel = newsel.join(';');
                                $selectedDocuments.attr('data-mapdocuments', newsel);
                                __assignMapDocNames(newsel);
                            }
                        }, 
                       allValues: allMapDocuments,
                       selectedValues: $selectedDocuments.attr('data-mapdocuments'), 
                       multiselect: true});
                    }} );                
                
                //======================================

                let $selectMapTemplate = $dlg.find('select[name="map_template"]'); 

                window.hWin.HEURIST4.ui.createTemplateSelector( $selectMapTemplate
                    ,[{key:'',title:'Standard popup template'}], $selectMapTemplate.attr('data-template')
                    , {extraOptions: {menu_parent: $dlg}});
                    //,{key:'none',title:'Disable popup'}

                //======================================
                    
                let $selectBaseMap = $dlg.find('select[name="map_basemap"]');

                const baseMapOptions = [
                    {key:'OpenStreetMap', title:'OpenStreetMap'},
                    {key:'OpenTopoMap', title:'OpenTopoMap'},
                    {key:'MapBox.StreetMap', title:'MapBox.StreetMap'},
                    {key:'MapBox.Satellite', title:'MapBox.Satellite'},
                    {key:'MapBox.Combined',title:'MapBox.Combined'},
                    {key:'MapBox.Relief', title:'MapBox.Relief'},
                    {key:'Esri.WorldStreetMap', title:'Esri.WorldStreetMap'},
                    {key:'Esri.WorldTopoMap', title:'Esri.WorldTopoMap'},
                    {key:'Esri.WorldImagery', title:'Esri.WorldImagery'},
                    {key:'Esri.WorldShadedRelief', title:'Esri.WorldShadedRelief'},
                    {key:'Stamen.Toner', title:'Stamen.Toner'},
                    {key:'Stamen.TerrainBackground', title:'Stamen.TerrainBackground'},
                    {key:'Esri.NatGeoWorldMap',title:'Esri.NatGeoWorldMap'},
                    {key:'Esri.WorldGrayCanvas',title:'Esri.WorldGrayCanvas'},
                    {key:'MapTilesAPI.OSMEnglish',title:'MapTilesAPI.OSMEnglish'}
                ];

                window.hWin.HEURIST4.ui.fillSelector($selectBaseMap[0], baseMapOptions);
                
                if($selectBaseMap.attr('data-basemap') != null){
                    $selectBaseMap.val($selectBaseMap.attr('data-basemap'));
                }
                $selectBaseMap = window.hWin.HEURIST4.ui.initHSelect($selectBaseMap[0], false, null, null, {menu_parent: $dlg});
                
                if($selectBaseMap.hSelect('instance') != undefined){
                    $selectBaseMap.hSelect('widget').css('width', '200px');
                }
                
                $gicon = $('<span>').addClass('ui-icon ui-icon-gear')
                    .css({position:'absolute',margin:'7px 0 0 0px',cursor:'hand'})
                    .insertBefore($selectedBasemaps);
                let $select_basemaps_dlg = $('<div/>').hide().appendTo( $selectedBasemaps.parent() );
                
                $gicon.on({ click: function(){                                 
                       $select_basemaps_dlg.selectMultiValues({
                       onselect:function(newsel){
                            if(newsel){
                                newsel = newsel.join(';');
                                $selectedBasemaps.val(newsel);
                            }
                        }, 
                       allValues: baseMapOptions,
                       selectedValues: $selectedBasemaps.val().split(';'), 
                       multiselect: true});
                    }} );
                    
                //======================================================
                    
                $dlg.find('input[name="popup_behaviour"]')
                    .on('change', function(event){

                        let val = $dlg.find('input[name="popup_behaviour"]:checked').val();

                        if(val == 'scale'){
                            $dlg.find('span#pw_label').text('Max width:');
                        }else{
                            $dlg.find('span#pw_label').text('Width:');
                        }

                        if(val == 'scale' || val == 'fixed_width'){
                            $dlg.find('span#ph_label').text('Max length:');
                        }else{
                            $dlg.find('span#ph_label').text('Length:');
                        }
                    });
            }else
            if(widget_name=='heurist_StoryMap'){
                
                if(!opts['reportOverviewMode']) opts['reportOverviewMode'] = 'inline';
                if(!opts['reportEndPageMode']) opts['reportEndPageMode'] = 'inline';
                if(!opts['reportElementMode']) opts['reportElementMode'] = 'vertical';
                if(!opts['reportElementMapMode']) opts['reportElementMapMode'] = 'linked';

                
                if(!opts['elementsPlaceholder']) opts['elementsPlaceholder'] = '<br><br>There are no story elements to display for the selected item';
                if(!opts['elementsPlaceholderSub']) opts['elementsPlaceholderSub'] = '<i>Story elements may exist but not be publicly visible</i>';

                $dlg.find('input[name="elementsPlaceholder"]').val(opts['elementsPlaceholder']);
                $dlg.find('input[name="elementsPlaceholderSub"]').val(opts['elementsPlaceholderSub']);
                
                $dlg.find('select[name="reportOverviewMode"]').val(opts['reportOverviewMode']);
                $dlg.find('select[name="reportEndPageMode"]').val(opts['reportEndPageMode']);
                
                let $selectTemplate = $dlg.find('select[name="reportOverview"]'); 

                window.hWin.HEURIST4.ui.createTemplateSelector( $selectTemplate
                    ,[{key:'',title:'Standard record view'}], opts['reportOverview'], {extraOptions: {menu_parent: $dlg}});
                    
                $selectTemplate = $dlg.find('select[name="reportEndPage"]'); 

                window.hWin.HEURIST4.ui.createTemplateSelector( $selectTemplate
                    ,[{key:'',title:'Standard record view'}], opts['reportEndPage'], {extraOptions: {menu_parent: $dlg}});
                    
                $selectTemplate = $dlg.find('select[name="reportElement"]'); 

                window.hWin.HEURIST4.ui.createTemplateSelector( $selectTemplate
                    ,[{key:'',title:'Standard record view'}], opts['reportElement'], {extraOptions: {menu_parent: $dlg}});

                    
                let $elementOrder = $dlg.find('select[name="elementOrder"]'); 

                window.hWin.HEURIST4.ui.createRectypeDetailSelect( $elementOrder[0], null, ['date','year','integer']
                    ,[{key:'',title:'As is (order in record)'},{key:'def',title:'Common date fields (##9,10,11)'}]
                    ,{selectedValue:opts['elementOrder']});
                    
                /*
                var selectFields = $dlg.find('select[name="storyFields"]'); 
                window.hWin.HEURIST4.ui.createRectypeDetailSelect(selectFields[0], 
                        null, 'resource', 
                        [{key:'',title:''}], {selectedValue:opts['storyFields']});
                */
                let defValue = $dlg.find('input[name="storyFields"]').val();
                
                if(window.hWin.HEURIST4.util.isempty(defValue)){
                    let DT_STORY_FIELD = $Db.getLocalID('dty','1414-1089');// Story element field    
                    if(DT_STORY_FIELD>0) defValue = DT_STORY_FIELD;
                }
                
                
                let ele = $dlg.find('#storyFields');

                const ed_options = {
                        recID: -1,
                        dtID: ele.attr('id'), 
                        values: [defValue],
                        readonly: false,
                        showclear_button: true,
                        dtFields:{
                            dty_Type:"resource", rst_MaxValues:1,
                            rst_DisplayName: 'Story element fields', 
                            rst_DisplayHelpText: 'Defines the field that points to story element records (such as Life event, Ocuppation etc)',
                            rst_FieldConfig: {entity:'defDetailTypes', csv:true, filters:{types:['resource']}}
                        },
                        change: on_change
                    };

                ele.editing_input(ed_options);
                ele.parent().css('display','block');
                
                //----------------
                /*
                ele = $dlg.find('#storyRectypes');

                var ed_options = {
                        recID: -1,
                        dtID: ele.attr('id'), 
                        values: [$dlg.find('input[name="storyRectypes"]').val()],
                        readonly: false,
                        showclear_button: true,
                        dtFields:{
                            dty_Type:"resource", rst_MaxValues:1,
                            rst_DisplayName: 'Story element record types', rst_DisplayHelpText:'',
                            rst_FieldConfig: {entity:'defRecTypes', csv:true}
                        },
                        change:function(){
                        }
                    };

                ele.editing_input(ed_options);
                ele.parent().css('display','block');
                ele.find('.header').css({'width':'150px','text-align':'right'});
                */
                
                $dlg.find('select[name="reportElementMode"]')
                .on('change', function(e){

                    $dlg.find('select[name="reportElementSlideEffect"]').parent().hide();
                    $dlg.find('select[name="reportElementDistinct"]').parent().hide();
                    
                    if($(e.target).val()=='vertical'){
                        $dlg.find('select[name="reportElementDistinct"]').parent().show();
                    }else if($(e.target).val()=='slide'){
                        $dlg.find('select[name="reportElementSlideEffect"]').parent().show();
                    }
                    
                })
                .val(opts['reportElementMode']).trigger('change');

                $dlg.find('select[name="reportElementMapMode"]')
                .on('change', function(e){

                    if($(e.target).val()=='filtered'){
                        $dlg.find('input[name="reportElementMapFilter"]').parent().show();
                    }else{
                        $dlg.find('input[name="reportElementMapFilter"]').parent().hide();
                    }
                    
                })
                .val(opts['reportElementMapMode']).trigger('change');
                
                
            }else
            if(widget_name=='heurist_resultListExt' && 
                $dlg.find('select[name="rep_template"]').find('options').length==0){

                    let $select3 = $dlg.find('select[name="rep_template"]'); 

                    window.hWin.HEURIST4.ui.createTemplateSelector( $select3 
                        ,[{key:'',title:'Standard record view template'}], $select3.attr('data-template'), {extraOptions: {menu_parent: $dlg}});

                     $dlg.find('#is_popup_report').on('change', function(e){
                        if($dlg.find('#is_popup_report').is(':checked')){
                            $dlg.find('#popup_report_position').parent().show();       

                            $dlg.find('#is_popup_report').parent().find('.header').removeClass('row-spacing');
                        }else{
                            $dlg.find('#popup_report_position').parent().hide();

                            $dlg.find('#is_popup_report').parent().find('.header').addClass('row-spacing');
                        }    
                     });
                     $dlg.find('#is_popup_report').prop('checked', opts['is_popup']).trigger('change');
                     $dlg.find('#popup_report_position').val(opts['popup_position']);

            }else 
            if(widget_name=='heurist_resultList'){

                if($dlg.find('select[name="rendererExpandDetails"]').find('options').length==0){
                    let $select4 = $dlg.find('select[name="rendererExpandDetails"]'); 

                    window.hWin.HEURIST4.ui.createTemplateSelector( $select4
                        ,[{key:'',title:'Standard record view template'}], $select4.attr('data-template'), {extraOptions: {menu_parent: $dlg}});
                }

                if($dlg.find('select[name="field_for_ext_classes"]').find('options').length==0){ // list of record fields that are enum
                    let $select5 = $dlg.find('select[name="field_for_ext_classes"]');

                    window.hWin.HEURIST4.ui.createRectypeDetailSelect($select5[0], null, ['enum'], [{key: 0, title: 'None'}], 
                        {'selectedValue': $select5.attr('fld-id')}, {extraOptions: {menu_parent: $dlg}});
                }
            }

            $dlg.find(`div.${widget_name} select`).on('change', on_change);
            $dlg.find(`div.${widget_name} input, div.${widget_name} textarea`).on('input', on_change);
        }
    }
    
   
    //
    // from UI to widget properties
    //
    function _getValues(){
        
        
        function __prepareVal(val){
            if(val==='false'){
                val = false;
            }else if(val==='true'){
                val = true;
            }
            return val;
        }
        
        
        let widget_name = widget_cfg.appid;

        let opts = {};

        if(widget_name=='heurist_Map'){

            let layout_params = {};//special option for leaflet mapping
            //parameters for controls
            layout_params['notimeline'] = !$dlg.find("#use_timeline").is(':checked');
            layout_params['nocluster'] = !$dlg.find("#use_cluster").is(':checked');
            layout_params['editstyle'] = $dlg.find("#editstyle").is(':checked');
            layout_params['map_rollover'] = $dlg.find("#map_rollover").is(':checked');
            layout_params['smooth_zoom'] = $dlg.find("#smooth_zoom").is(':checked');
            layout_params['zoom_to_selected'] = $dlg.find("#zoom_to_selected").is(':checked');
            layout_params['zoom_delta'] = $dlg.find("#zoom_delta").val();

            let ctrls = [];
            $dlg.find('input[name="controls"]:checked').each(
                function(idx,item){ctrls.push($(item).val());}
            );
            if(ctrls.length>0){
                layout_params['controls'] = ctrls.join(',');    
            }else{
                layout_params['controls'] = 'none';
            }
            if(ctrls.indexOf('legend')>=0){ //legend is visible
                ctrls = [];
                $dlg.find('input[name="legend"]').each( //visible section in legend
                    function(idx,item){
                        if($(item).is(':checked')){
                            let is_exp = $dlg.find('input[name="legend_exp"][value="'+$(item).val()+'"]').is(':checked')?'':'-'
                            ctrls.push(is_exp+$(item).val());
                        }
                    }
                );
                if(ctrls.length>0){
                    layout_params['legend'] = ctrls.join(',');    
                    if(!$dlg.find('input[name="legend_exp2"]').is(':checked')){
                        layout_params['legend'] += ',off';    
                    }
                    let w = $dlg.find('input[name="legend_width"]').val();
                    if(w>100 && w<600){
                        layout_params['legend'] += (','+w);
                    }
                } 
            }
            
            layout_params['published'] = 1;
            layout_params['template'] = $dlg.find('select[name="map_template"]').val();
            layout_params['basemap'] = $dlg.find('select[name="map_basemap"]').val();
            layout_params['basemaps'] = $dlg.find('input[name="basemaps"]').val();
            layout_params['basemap_filter'] = $dlg.find('input[name="map_basemap_filter"]').val();

            layout_params['popup_behaviour'] = $dlg.find('input[name="popup_behaviour"]:checked').val();
            layout_params['popup_width'] = $dlg.find('input[name="popup_width"]').val() + $dlg.find('select[name="popup_wunit"]').val();
            layout_params['popup_height'] = $dlg.find('input[name="popup_height"]').val() + $dlg.find('select[name="popup_hunit"]').val();
            layout_params['popup_resizing'] = false;//$dlg.find('input[name="popup_resizing"]').is(':checked')
            
            if($dlg.find('input[name="ignore_mapdoc_zoom"]').is(':checked')){
                layout_params['ignore_mapdoc_zoom'] = $dlg.find('input[name="ignore_mapdoc_zoom"]').is(':checked');
            }
            if($dlg.find('input[name="map_maxzoom"]').val()>0){
                layout_params['maxzoom'] = $dlg.find('input[name="map_maxzoom"]').val();
            }
            if($dlg.find('input[name="map_minzoom"]').val()>0){
                layout_params['minzoom'] = $dlg.find('input[name="map_minzoom"]').val();
            }
            if($dlg.find('input[name="map_pntzoom"]').val()>0){
                layout_params['pntzoom'] = $dlg.find('input[name="map_pntzoom"]').val();
            }

  
//  use_timeline use_cluster editstyle map_rollover controls legend legend_exp legend_exp2 map_popup  mapdocument      
//  map_template map_basemap_filter  map_template
  
            let popup = $dlg.find('input[name="map_popup"]:checked').val();
            if(popup!='standard'){
                layout_params['popup'] = popup;
            }

            let mapdoc_id = $dlg.find('select[name="mapdocument"]').val();
            if(mapdoc_id>0) {opts['mapdocument'] = mapdoc_id;}

            if($dlg.find('input[name="mapdocuments"]').attr('data-mapdocuments')){
                layout_params['mapdocuments'] = $dlg.find('input[name="mapdocuments"]').attr('data-mapdocuments');    
            }

            if($dlg.find('input[name="basemaps"]').val()){
                layout_params['basemaps'] = $dlg.find('input[name="basemaps"]').val();    
            }
            
            opts['custom_links'] = $dlg.find('textarea[name="custom_links"]').val(); 
            opts['current_search_filter'] = $dlg.find('input[name="current_search_filter"]').val();   
            
            layout_params['style'] = $dlg.find('#map_default_style').val();   
            layout_params['selection_style'] = $dlg.find('#map_select_style').val();   
            
            opts['layout_params'] = layout_params;
            opts['leaflet'] = true;
        }//heurist_Map

        let cont = $dlg.find('div.'+widget_name);

        if(widget_name=='heurist_SearchTree'){
            cont.find('input[name="allowed_UGrpID"]').val( 
                cont.find('#allowed_UGrpID').editing_input('getValues') );
            cont.find('input[name="allowed_svsIDs"]').val( 
                cont.find('#allowed_svsIDs').editing_input('getValues') );
            cont.find('input[name="init_svsID"]').val( 
                cont.find('#init_svsID').editing_input('getValues') );
        }else 
        if(widget_name=='heurist_Navigation'){
            let menu_recIDs = cont.find('#menu_recIDs').editing_input('getValues');
            if(window.hWin.HEURIST4.util.isempty(menu_recIDs) || 
                (Array.isArray(menu_recIDs)&& (menu_recIDs.length==0||window.hWin.HEURIST4.util.isempty(menu_recIDs[0]))))
            {
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: 'Please set at least one top level menu item',
                    error_title: 'Missing required setting'
                });
                return false;   
            }
            cont.find('input[name="menu_recIDs"]').val( menu_recIDs );
        }else
        if(widget_name=='heurist_StoryMap')
        {
            let storyFields = cont.find('#storyFields').editing_input('getValues');
            cont.find('input[name="storyFields"]').val( storyFields );
            
            if(window.hWin.HEURIST4.util.isempty(storyFields) || 
                (Array.isArray(storyFields)&& (storyFields.length==0||window.hWin.HEURIST4.util.isempty(storyFields[0]))))
            {
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: 'Please set at least one story field',
                    error_title: 'Missing story field'
                });                     
                return false;   
            }
        }
        
//controls":false,"legend":true,"legend_width":"250","legend_exp":false,"legend_exp2":false,
        //find INPUT elements and fill opts with values
        cont.find('input').each(function(idx, item){
            item = $(item);
            if(item.attr('name') && item.parents('.heurist_Map').length==0){ //item.attr('name').indexOf('map')<0){
                if(item.attr('type')=='checkbox'){
                    opts[item.attr('name')] = item.is(':checked');    
                }else if(item.attr('type')=='radio'){
                    if(item.is(':checked')) opts[item.attr('name')] = __prepareVal(item.val());    
                }else if(item.val()!=''){
                    opts[item.attr('name')] = __prepareVal(item.val());    
                }
            }
        });
        //find SELECT
        $dlg.find('div.'+widget_name+' select').each(function(idx, item){
            item = $(item);
            if(item.parents('.heurist_Map').length==0){
                opts[item.attr('name')] = item.val();     
            }
        });

        let empty_remark = $dlg.find('#empty_remark').val();
        if(window.hWin.HEURIST4.util.isempty(empty_remark) && !opts['blank_empty_remark']){
            empty_remark = 'def';
        }else if(opts['blank_empty_remark']){
            empty_remark = '';
        }

        let placeholder = $dlg.find('#placeholder_text').val();
        if(window.hWin.HEURIST4.util.isempty(placeholder) && !opts['blank_placeholder']){
            placeholder = 'def';
        }else if(opts['blank_placeholder']){
            placeholder = '';
        }

        if(widget_name=='heurist_resultListExt'){
            opts['template'] = $dlg.find('select[name="rep_template"]').val();
            opts['reload_for_recordset'] = true;
            opts['emptysetmessage'] = empty_remark; //to remove
            opts['empty_remark'] = empty_remark;

            let selection_mode = opts['selection_mode'];

            opts['is_single_selection'] = false;
            opts['is_multi_selection'] = false;
            opts['show_all'] = false;

            opts[selection_mode] = true;

            if(opts['template'] != ''){
                opts['url'] = '?template='+encodeURIComponent(opts['template'])
                +'&emptysetmessage='+encodeURIComponent(opts['empty_remark'])
                +'&[query]';
            }else{
                opts['url'] = 'viewers/record/renderRecordData.php?db=[dbname]&recID=[recID]';
                opts['is_single_selection'] = true; // force single selection
            }

            opts['placeholder_text'] = placeholder;
            opts['is_popup'] = $dlg.find('#is_popup_report').is(':checked');
            opts['popup_position'] = $dlg.find('#popup_report_position').val();

        }else if(widget_name=='heurist_resultList'){
            opts['show_toolbar'] = opts['show_counter'] || opts['show_viewmode'] || opts['show_export_button'] || opts['support_collection'];
            if(window.hWin.HEURIST4.util.isempty(opts['recordview_onselect'])){
                opts['recordview_onselect']  = 'inline'; //default value    
            }
            opts['empty_remark'] = empty_remark;
            opts['placeholder_text'] = placeholder;

            let h_unit = opts['dialog_height'] > 0 ? opts['dialog_hunit'] : '';
            let w_unit = opts['dialog_width'] > 0 ? opts['dialog_wunit'] : '';
            let l_unit = opts['dialog_left'] > 0 ? opts['dialog_lunit'] : '';
            let t_unit = opts['dialog_top'] > 0 ? opts['dialog_tunit'] : '';

            opts['recview_dimensions'] = {
                height: `${opts['dialog_height']}${h_unit}`,
                width: `${opts['dialog_width']}${w_unit}`,
                left: `${opts['dialog_left']}${l_unit}`,
                top: `${opts['dialog_top']}${t_unit}`
            };

            // Remove un-needed keys
            delete opts['dialog_height'];
            delete opts['dialog_hunit'];
            delete opts['dialog_width'];
            delete opts['dialog_wunit'];
            delete opts['dialog_left'];
            delete opts['dialog_lunit'];
            delete opts['dialog_top'];
            delete opts['dialog_tunit'];

        }else if(widget_name=='heurist_resultListDataTable'){
            opts['dataTableParams'] = $dlg.find('#dataTableParams').val();
            opts['emptyTableMsg'] = empty_remark;
            opts['placeholder_text'] = placeholder;
        }else if(widget_name=='heurist_SearchInput'){
            
            placeholder = $dlg.find('#si_placeholder_text').val();
            if(window.hWin.HEURIST4.util.isempty(placeholder) && !opts['si_blank_placeholder']){
                placeholder = 'def';
            }else if(opts['si_blank_placeholder']){
                placeholder = '';
            }
            
            opts['placeholder_text'] = placeholder;
        }else if(widget_name=='heurist_StoryMap'){

            opts['storyPlaceholder'] = placeholder;
            delete opts['placeholder_text'];

            opts['elementsPlaceholder'] = $dlg.find('#elementsPlaceholder').val();
            if(window.hWin.HEURIST4.util.isempty(opts['elementsPlaceholder']) && !opts['blank_elementsPlaceholder']){
                // replace with default
                opts['elementsPlaceholder'] = 'def';
            }
        }

        if($dlg.find(`.${widget_name} input[name="export_options"]`).is(':visible')){

            let export_options = [];
            $.each($dlg.find(`.${widget_name} input[name="export_options"]`), function(idx, ele){
                if($(ele).is(':checked')){
                    export_options.push($(ele).val());
                }
            });

            if(export_options.length > 0){
                opts['export_options'] = export_options.join(',');
            }else{
                opts['export_options'] = 'all';
            }
        }

        let selval = opts.searchTreeMode;
        if(window.hWin.HEURIST4.util.isempty(opts.allowed_UGrpID)){ //groups are not defined

            if(selval==1){
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: 'For "tree" mode you have to select groups to be displayed',
                    error_title: 'Missing required setting'
                });
                return false;
            }else if (window.hWin.HEURIST4.util.isempty(opts.allowed_svsIDs) && selval==0) { //individual filters are not defined
                window.hWin.HEURIST4.msg.showMsgErr({
                    message: 'For "button" mode you must select either workgroups or filters individually',
                    error_title: 'Missing required setting(s)'
                });
                return false;
            }
        }

        opts['init_at_once'] = true;
        opts['search_realm'] = $dlg.find('input[name="search_realm"]').val();
        opts['search_page'] = $dlg.find('select[name="search_page"]').val();
        opts['widget_id'] = $dlg.find('input[name="widget_id"]').val();

        return opts;
    }//_getValues



    //public members
    let that = {

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            return (strClass === _className);
        },

        getValues: function(){
            return _getValues();
        }
    }

    _init();
    
    return that;
}



