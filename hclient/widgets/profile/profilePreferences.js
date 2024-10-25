/**
* profilePreferences.js - edit user preferences
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
/* global cfg_layouts */

$.widget( "heurist.profilePreferences", $.heurist.baseAction, {

    // default options
    options: {
        height: 520,
        width:  400,
        title:  'Personal preferences (for this database)',
        default_palette_class: 'ui-heurist-populate',
        actionName: 'profilePreferences',
        path: 'widgets/profile/'
    },
    
    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){
            let that = this;
             
            //find all labels and apply localization
            this._$('label').each(function(){
                $(this).html(window.hWin.HR($(this).html()));
            })
            this._$('.header').css({'min-width':'300px', 'width':'300px'});

            //fill list of languages
            //fill list of layouts
            this.initProfilePreferences();

            //assign values to form fields from window.hWin.HAPI4.currentUser['ugr_Preferences']
            let prefs = window.hWin.HAPI4.currentUser['ugr_Preferences'];
            
            let allFields = this._$('input,select');

            //default
            prefs['userCompetencyLevel'] = window.hWin.HAPI4.get_prefs_def('userCompetencyLevel', 2);
            prefs['userFontSize'] = window.hWin.HAPI4.get_prefs_def('userFontSize', 12);
            prefs['searchQueryInBrowser'] = window.hWin.HAPI4.get_prefs_def('searchQueryInBrowser', 1);
            prefs['mapcluster_on'] = window.hWin.HAPI4.get_prefs_def('mapcluster_on', 1);
            prefs['mapcluster_zoom'] = window.hWin.HAPI4.get_prefs_def('mapcluster_zoom', 12);
            prefs['entity_btn_on'] = window.hWin.HAPI4.get_prefs_def('entity_btn_on', 1);
            
            let map_controls = window.hWin.HAPI4.get_prefs_def('mapcontrols', 'bookmark,geocoder,selector,print,publish');
            map_controls = map_controls.split(',');
            prefs['mctrl_bookmark'] = 0;prefs['mctrl_geocoder'] = 0;
            prefs['mctrl_selector'] = 0;prefs['mctrl_print'] = 0;
            prefs['mctrl_publish'] = 0;
            for(let i=0;i<map_controls.length;i++){
                prefs['mctrl_'+map_controls[i]] = 1;
            }

            // Map popup record view
            window.hWin.HEURIST4.ui.createTemplateSelector( this._$('#map_template'), 
                [{key:'',title:'Standard map popup template'},
                 {key:'standard',title:'Standard record info (in popup)'},
                 {key:'none',title:'Disable popup'}
                 ],
                 window.hWin.HAPI4.get_prefs_def('map_template', null));

            // Main record view
            window.hWin.HEURIST4.ui.createTemplateSelector( this._$('#main_recview'), [{key:'default',title:'Standard record view'}],
                            window.hWin.HAPI4.get_prefs_def('main_recview', 'default'));

            //from prefs to ui
            allFields.each(function(){
                if(prefs[this.id]){
                    if(this.type=="checkbox"){
                        this.checked = (prefs[this.id]=="1" || prefs[this.id]=="true")
                    }else{
                        $(this).val(prefs[this.id]);
                    }
                };
            });
            
            //change font size example
            this._$('#userFontSizeExample')
                .css('font-size', prefs['userFontSize']+'px')
                .position({
                    my: 'left+15 center',
                    at: 'right center',
                    of: this._$('#userFontSize')
                });

            this._on(this._$('#userFontSize'),{change: function(){ 
                let size = this._$('#userFontSize').val();
                this._$('#userFontSizeExample')
                    .css('font-size', size+'px')
                    .position({
                        my: 'left+15 center',
                        at: 'right center',
                        of: this._$('#userFontSize')
                    });
            }});

            //custom/user heurist theme
            let custom_theme_div = this._$('#custom_theme_div');
            
            let $btn_edit_clear2 = $('<span>')
            .addClass("smallbutton ui-icon ui-icon-circlesmall-close")
            .attr('tabindex', '-1')
            .attr('title', 'Reset default color settings')
            .appendTo( custom_theme_div )
            .css({'line-height': '20px',cursor:'pointer',
                outline: 'none','outline-style':'none', 'box-shadow':'none',  'border-color':'transparent'});
                
            this._on($btn_edit_clear2, { click: function(){ window.hWin.HEURIST4.msg.showMsgDlg('<br>Are you sure?',
                    function(){that._$('#custom_theme').val('');}); }});
                
            let $btn_edit_switcher2 = $( '<span>open editor</span>', {title: 'Open theme editor'})
                .addClass('smallbutton')
                .css({'line-height': '20px',cursor:'pointer','text-decoration':'underline'})
                .appendTo( custom_theme_div );
                
                
            let openThemeDialog = { click: function (){
                    let current_val = window.hWin.HEURIST4.util.isJSON( that._$('#custom_theme').val() );
                    if(!current_val) current_val = {};
                    
                    window.hWin.HEURIST4.ui.showEditThemeDialog(current_val, false, function(new_value){
                        that._$('#custom_theme').val(JSON.stringify(new_value));
                    });
            }};
                
            this._on($btn_edit_switcher2, openThemeDialog);
            this._on(this._$('#custom_theme').attr('readonly','readonly'), openThemeDialog );

            
            //map symbology editor            
            window.hWin.HEURIST4.ui.initEditSymbologyControl(this._$('#map_default_style'));
            window.hWin.HEURIST4.ui.initEditSymbologyControl(this._$('#map_select_style'));
            
            
            let useMapcluster = {change: function(){ that._$('#mapcluster_on').prop('checked', true); }};
            this._on(this._$('#mapcluster_grid'), useMapcluster);
            this._on(this._$('#mapcluster_count'), useMapcluster);
            this._on(this._$('#mapcluster_zoom'), useMapcluster);        
        
        return this._super();
    },
    
    
    initProfilePreferences: function(){

        let sel = this._$("#layout_language");

        sel.val(window.hWin.HAPI4.getLocale());

        sel = this._$("#layout_id");
        if(sel.length==1){ //global variable defined in layout_default.js

            sel.empty();
            sel = sel.get(0);
            for(let key in cfg_layouts){
                if(key){
                    window.hWin.HEURIST4.ui.addoption(sel, cfg_layouts[key]['id'], cfg_layouts[key]['name']);
                }
            }
        }
        

        //init bookmarklet
        // NOTE: bookmarklet source is duplicated in /import/bookmarklet/bookmarkletSource.js
        sel = this._$("#bookmarklet-link");
        if(sel.length==1){ //global variable defined in layout_default.js
            sel.attr('href',
"javascript:(function(){"
    + "e=encodeURIComponent;d=document;h='" + window.hWin.HAPI4.baseURL + "';"
    + "if(location.protocol=='https:'){"
        + "if(window.confirm('Only the URL, web page title and a page snapshot can be extracted from https:// pages."
                + "\\nAdditional information will need to be set manually."
                + "\\n\\nClick OK to add a record for this page.')){"
                    + "window.open(h+'?fmt=edit&db="+window.hWin.HAPI4.database
                    + "&rec_rectype=2-2&rec_owner=1&rec_visibility=viewable&t='+e(d.title)+'&u='+e(location.href));"
        + "}"
    + "return;}"

    + "c=d.contentType;"
    + "if(c=='text/html'||!c){"
        + "if(d.getElementById('__heurist_bookmarklet_div'))return%20Heurist.init();"
        
        + "s=d.createElement('script');s.type='text/javascript';"
        + "s.src=h+'import/bookmarklet/bookmarkletPopup.php?db="+window.hWin.HAPI4.database+"';"
        + "d.getElementsByTagName('head')[0].appendChild(s);"
    + "}"
    + "else{"
    +   "w=open(h+'?fmt=edit&db="+window.hWin.HAPI4.database+"&t='+e(d.title)+'&u='+e(location.href));"
                //+ "window.setTimeout('w.focus()',200);}"
    + "}})();"
            );
            
            sel.text('>> '+window.hWin.HAPI4.database)
        }
    },

    //    
    //
    //
    _getActionButtons: function(){
        let res = this._super();
        res[1].text = window.hWin.HR('Apply');
        res[1].disabled = null;
        return res;
    },

    //
    //
    //
    doAction: function(){
            
        let request = {};
        let val;
        let map_controls = [];
        let allFields = this._$('input,select');

        allFields.each(function(){
            if(this.type=="checkbox"){
                if(this.id.indexOf('mctrl_')<0){
                    request[this.id] = this.checked?"1":"0";
                }else if(this.checked){
                    map_controls.push(this.value);
                }
            }else{
                request[this.id] = $(this).val();
            }
        });
        request['mapcontrols'] = map_controls.length==0?'none':map_controls.join(',');

        let that = this;

        //save preferences in session
        window.hWin.HAPI4.SystemMgr.save_prefs(request,
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){

                    let prefs = window.hWin.HAPI4.currentUser['ugr_Preferences'];

                    let ask_reload = (prefs['layout_language'] != request['layout_language'] ||
                        //prefs['layout_theme'] != request['layout_theme'] ||
                        prefs['layout_id'] != request['layout_id']);

                    let reload_color_css = (prefs['custom_theme'] != request['custom_theme']);

                    let reload_map = (prefs['mapcluster_grid'] != request['mapcluster_grid'] ||    
                        prefs['mapcluster_on'] != request['mapcluster_on'] || 
                        prefs['search_detail_limit'] != request['search_detail_limit'] ||
                        prefs['mapcluster_count'] != request['mapcluster_count'] ||   
                        prefs['mapcluster_zoom'] != request['mapcluster_zoom'] ||
                        prefs['deriveMapLocation'] != request['deriveMapLocation'] || 
                        prefs['map_rollover'] != request['map_rollover'] || 
                        prefs['mapcontrols'] != request['mapcontrols']);

                    //check help toggler and bookmark search - show/hide
                    window.hWin.HEURIST4.ui.applyCompetencyLevel(request['userCompetencyLevel']);

                    if(prefs['bookmarks_on'] != request['bookmarks_on']){
                        $('.heurist-bookmark-search').css('display',
                            (request['bookmarks_on']=='1')?'inline-block':'none');
                    }
                    if(prefs['entity_btn_on'] != request['entity_btn_on']){
                        let is_vis = (request['entity_btn_on']=='1');
                        $('.heurist-entity-filter-buttons').css({'visibility':
                            is_vis?'visible':'hidden',
                            'height':is_vis?'auto':'10px'});
                    }

                    $.each(request, function(key,value){
                        window.hWin.HAPI4.currentUser['ugr_Preferences'][key] = value;    
                    });

                            that.closeDialog();

                            if(ask_reload){
                                window.hWin.HEURIST4.msg.showMsgFlash('Reloading page to apply new settings', 2000);
                                setTimeout(function(){
                                        window.location.reload();
                                    },2100);

                            }else {
                            
                                if(reload_map){
                                    //reload map frame forcefully
                                    let app = window.hWin.HAPI4.LayoutMgr.appGetWidgetById('heurist_Map2');
                                    if(app && app.widget){
                                        $(app.widget).app_timemap('reloadMapFrame'); //call method
                                    }
                                }
                                
                                window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE);
                                
                                //reload color scheme
                                if(reload_color_css){
                                    $('head').find('#heurist_color_theme')
                                        .load( window.hWin.HAPI4.baseURL 
                                        + 'hclient/framecontent/initPageTheme.php?db='
                                        + window.hWin.HAPI4.database);
                                }
                                
                                window.hWin.HEURIST4.msg.showMsgFlash('Preferences are saved');
                            }

                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);      
                        }
                    }
                );
            
    }
        
});

