/**
* Select element to be inserted into CMS page - opens popup
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
//
//
//
function editCMS_SelectElement( callback ){

    let $dlg;

    let selected_element = null, selected_name='';

    let t_components = {
        
        header1:{name:'Web pages', description:'#web_pages', is_section_header: true},

        //grp4:{name:'Composite Pages', description:'#composite_pages', is_group_header:true}, //Content layouts or templates
            tpl_default: {name:'Simple Page', description:'#simple_page'}, //Simple blank page
            tpl_discover: {name:'Discover (filters/results/map)', description:'#discover'}, //3 columns layout
            tpl_database: {name:'Database description', description:'#database'},
            tpl_blog: {name:'Blog', description:'#blog'},

        separator:{name:' ', description:'#', is_separator: true},

        header2:{name:'Page Content', description:'#page_content', is_section_header: true},

            grp3:{name:'Layout', description:'#containers', is_group_header:true},

                group:{name:'Group', description:'#group'}, //Container for elements
                accordion:{name:'Accordion', description:'#accordion'}, //Set of collapsable groups
                tabs:{name:'Tabs', description:'#tabs'}, //Tab/Page control. Each page may have group of elements
                cardinal:{name:'Cardinal', description:'#cardinal'}, //Container for five groups or elements placed orthogonally (N-S-E-W,-Center panels)

            grp1:{name:'Content', description:'Content layouts or templates', is_group_header:true},
                
                //text_media:{name:'Text with media', description:'media and text '},
                text_1:{name:'Simple text box', description:'#simple_text'},
                text_2:{name:'Text in 2 columns', description:'#2_columns'}, //2 columns layout
                text_3:{name:'Text in 3 columns', description:'#3_columns'}, //3 columns layout
                //group_2:{name:'Groups as 2 columns', description:'2 columns layout'},
                text_banner:{name:'Text on banner', description:'#text_banner'}, //Text over background image

            grp2:{name:'Widgets', description:'#widgets', is_group_header:true}, //Heurist Widgets for dynamic content or interaction
                
                heurist_SearchInput:{name:'Filter', description:'#filter'}, //Search field (with standard filter builder)
                heurist_SearchTree:{name:'Saved filters', description:'#saved_filter'}, //Simple &amp; facet filters, selection or tree            

                heurist_resultList:{name:'Standard filter result', description:'#result_list'}, //Switchable modes, action controls            
                heurist_resultListExt:{name:'Custom report', description:'#custom_report'}, //Also use for single record view            
                heurist_resultListDataTable:{name:'Table format', description:'#data_table'}, //Result list as data table            

                heurist_Map:{name:'Map and timeline', description:'#map_timeline'}, //Map and timeline widgets
                heurist_StoryMap:{name:'Story Map', description:'#story_map'}, //Storyline/map controller widgets
                heurist_Graph:{name:'Network graph', description:'#network_graph'}, //Visualization for records links and relationships            
                
                heurist_Navigation:{name:'Menu', description:'#menu'}, //Navigation Menu
                heurist_recordAddButton:{name:'Add Record', description:'#add_record'}, //Button to addition of new Heurist record
                heurist_emailForm:{name:'Email Us Form', description:'#email_form'}, //Form to send email to addrees specified in home page as site owner email
        
    };


    let buttons= [
        {text:window.hWin.HR('Cancel'), 
            class:'btnCancel',
            css:{'float':'right','margin-left':'30px','margin-right':'20px'}, 
            click: function() { 
                $dlg.dialog( "close" );
        }},
        {text:window.hWin.HR('Insert'), 
            class:'ui-button-action btnDoAction',
            disabled:'disabled',
            css:{'float':'right'}, 
            click: function() { 
                if(selected_element){

                    if(selected_element.indexOf('tpl_') !== -1){

                        let $comp_dlg;
                        let msg = '<div>'
                            + '<label><input type="radio" name="insertMethod" value="new_" checked="checked"> insert as a separate page (at the end of the menu)</label><br>'
                            + '<label><input type="radio" name="insertMethod" value=""> insert as components at the end of the current page</label>'
                        + '</div>';

                        let btns = {};
                        btns[window.hWin.HR('OK')] = () => {

                            let option = $comp_dlg.find('input:checked');

                            if(option.val() == "new_"){
                                selected_element = "new_" + selected_element;
                            }

                            $comp_dlg.dialog( "close" );
                            callback.call(this, selected_element, selected_name);
                            $dlg.dialog( "close" );

                        };
                        btns[window.hWin.HR('Cancel')] = () => { $comp_dlg.dialog( "close" ); };

                        $comp_dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, {title: 'New composite page', yes: window.hWin.HR('OK'), no: window.hWin.HR('Cancel')}, {default_palette_class: 'ui-heurist-publish'})
                    }else{
                        callback.call(this, selected_element, selected_name);
                        $dlg.dialog( "close" );
                    }
                }
    }}];

    $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL
        +"hclient/widgets/cms/editCMS_SelectElement.html?t="+(new Date().getTime()), 
        buttons, window.hWin.HR('Insert component into web page'), 
        {  container:'cms-add-widget-popup',
            default_palette_class: 'ui-heurist-publish',
            width: 680,
            height: 724,
            close: function(){
                $dlg.dialog('destroy');       
                $dlg.remove();
            },
            open: function(){
                $dlg.find('.heurist-online-help').attr('href',
                window.hWin.HAPI4.sysinfo.referenceServerURL+'?db=Heurist_Help_System&website&id=39&pageid=708');

                //load list of groups and elements and init selector
                let sel = $dlg.find('#components');
                $.each(t_components, function(key, item){
                    if(item.is_group_header || item.is_section_header){
                        let grp = document.createElement("optgroup");
                        grp.label = item.name;
                        sel[0].appendChild(grp);

                        grp.classList.add(item.is_group_header ? 'group-header' : 'section-header');
                    }else if(item.is_separator){
                        let opt = window.hWin.HEURIST4.ui.addoption(sel[0], null, ' ', true);
                        opt.classList.add('separator-opt')
                    }else{
                        window.hWin.HEURIST4.ui.addoption(sel[0], key, item.name);
                    }

                });

                sel.on('change',function(e){
                    window.hWin.HEURIST4.util.setDisabled( $dlg.parents('.ui-dialog').find('.btnDoAction'), false );
                    let sel = e.target;
                    let t_name = $(sel).val();
                    selected_element  = t_name;
                    selected_name = sel.options[sel.selectedIndex].text;

                    let component = t_components[t_name];
                    let desc_id = component.description;

                    let $desc_ele = $dlg.find('#descriptions ' + desc_id);
                    let $desc_missing = $dlg.find('#descriptions #missing');

                    $dlg.find('#descriptions > div').addClass('hide-desc'); // Hide all component help
                    $dlg.find('#help_name').text(component.name); // Update component name
                    if(desc_id == '#' || $desc_ele.length == 0){
                        $desc_missing.removeClass('hide-desc'); // No help text currently
                    }else{
                        $desc_ele.removeClass('hide-desc'); // Show help text
                    }
                });

                sel.val('group').trigger('change');
                selected_element = 'group';

            }
    });

}



