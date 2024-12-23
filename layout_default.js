/**
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

/**
* Layout configuration fails  ????? files ????
* @see js/layout.js
*
* @type Array widgets - list of widgets/applications
* @type Array layouts - list of layouts
*/

/**
* List of applications (widgets)
*  id - unique identificator
*  name - title
*  widgetname - name of jquery widget (init function)
*  script - link to jquery widget file
*  minsize - array width,height
*  size   - array width,height
*  isframe - widget or link will be loaded in iframe
*  url - link to be loaded (if not widget)
*
* @type Array
*/

window.cfg_widgets = [

    {id:'heurist_Search', name:'Search', widgetname:'search', script:'hclient/widgets/search/search.js', minh:80, minw:150},
    {id:'heurist_SearchInput', name:'Filter', widgetname:'searchInput', script:'hclient/widgets/search/searchInput.js', minh:27, minw:150},
    {id:'heurist_SearchTree', name:'Saved searches', widgetname:'svs_list', script:'hclient/widgets/search/svs_list.js', minh:300, minw:200},
    {id:'heurist_Navigation', name:'Navigation', widgetname:'navigation', script:'hclient/widgets/cpanel/navigation.js'},
    {id:'heurist_Groups', name:'Groups'},{id:'heurist_Cardinals', name:'Cardinal layout'},


    {id:'heurist_controlPanel', name:'Control Panel', widgetname:'controlPanel', script:'hclient/widgets/cpanel/controlPanel.js'},
    {id:'heurist_slidersMenu', name:'Main Side Menu', widgetname:'slidersMenu', script:'hclient/widgets/cpanel/slidersMenu.js'},
    {id:'heurist_resultList', name:'Search Result', widgetname:'resultList', script:'hclient/widgets/viewers/resultList.js', minh:150, minw:150},
    {id:'heurist_resultListDataTable', name:'List View', widgetname:'resultListDataTable', script:'hclient/widgets/viewers/resultListDataTable.js'},
    {id:'heurist_resultListExt', name:'&nbsp;&nbsp;&nbsp;', widgetname:'recordListExt', script:'hclient/widgets/viewers/recordListExt.js'},
    {id:'heurist_resultListCollection', name:'Records Collection', widgetname:'resultListCollection', script:'hclient/widgets/viewers/resultListCollection.js'},

    {id:'heurist_reportViewer', name:'Report', widgetname:'reportViewer', script:'hclient/widgets/viewers/reportViewer.js'},
    
    
    {id:'heurist_Map', name:'Map (old)', title:'Map and timeline', widgetname:'app_timemap', script:'hclient/widgets/viewers/app_timemap.js'},  // map in iframe
    {id:'heurist_Map2', name:'Map', title:'Map and timeline',
                widgetname:'app_timemap', script:'hclient/widgets/viewers/app_timemap.js', minh:300, minw:300},  // map in iframe
    {id:'heurist_StoryMap', name:'StoryMap', title:'Story Map',
                widgetname:'app_storymap', script:'hclient/widgets/viewers/app_storymap.js', minh:300, minw:200},
    {id:'heurist_Frame', name:'Static Page', widgetname:'staticPage', script:'hclient/widgets/viewers/staticPage.js'},

    {id:'heurist_Graph', name:'Network', widgetname:'connections', script:'hclient/widgets/viewers/connections.js'},

    {id:'heurist_recordAddButton', name:'Add Record', widgetname:'recordAddButton', script:'hclient/widgets/record/recordAddButton.js'},
    {id:'heurist_emailForm', name:'Email Us Form', widgetname:'emailForm', script:'hclient/widgets/admin/emailForm.js'},
        
    //fake app - reference to another layout to include
    {id:'include_layout',name:'Inner Layout', widgetname:'include_layout'}

];


/**
entire layout may be divided into 5 panes  : north  west  center  east south
each pane may have: size, minsize, resizable (true), dropable(false)

each pane contains applications, application may be grouped into tabs
tab's properties  dockable,dragable,resizable applied to all children applications

dockable - (false) placed into tabcontrol and allows to dock other apps
if true and not in tabgroup, tabgroup is created by default
hasheader - (if isdocking false)
css - list of css parameters - mostly for position
resizable - (false)
dragable - (false) it is possible to drag around  otherwise fixed position
options - parameters to init application

*/

window.cfg_layouts = [

    // Default layout - the standard Heurist interface, used if no parameter provided
    // TODO: change the id and name to jsut HeuristDefault and Heurist Default - h4 and h3 are hangovers from old versions
    {id:'H5Default', name:'Heurist Def v5', theme:'heurist', type:'free',
        north_pane:{ dropable:false, dragable:false, 
                css:{position:'absolute', top:0,left:0,height:'6em',right:0, 
                     'min-width':'75em'}, 
            apps:[{appid:'heurist_controlPanel', hasheader:false, css:{height:'100%', border:'solid'}}] 
        },
        center_pane:{ dockable:false, dropable:false, dragable:false, 
                css:{position:'absolute', top:'6em',left:0,bottom:0,right:0},
            apps:[{appid:'include_layout', 
                        name: 'Filter-Analyse-Publish',
                        layout_id:'FAP',dragable:false,
                        options:{ref: 'SearchAnalyze'}
                        ,css:{position:'absolute', top:'0',left:0,bottom:'0.1em',right:0}}]
        }    
    },

    {id:'H6Default', name:'Heurist Def v6', theme:'heurist', type:'free',
        north_pane:{ dropable:false, dragable:false, 
                css:{position:'absolute', top:0,left:0,height:'50px',right:'-2px', 
                     'min-width':'77em'}, 
            apps:[{appid:'heurist_controlPanel', hasheader:false, css:{height:'100%', border:'solid'}}] 
        },
        center_pane:{ dockable:false, dropable:false, dragable:false, 
                css:{position:'absolute', top:'50px',left:0,bottom:'0.1em',right:'2px'},
            apps:[{appid:'heurist_slidersMenu', hasheader:false, css:{width:'100%'}}]
        }    
    },
    
    // WebSearch to embed into other websites
    {id:'WebSearch', name:'Heurist Embed', theme:'heurist', type:'cardinal',
        west:{size:300, minsize:150, apps:[{appid:'heurist_SearchTree', hasheader:false,
                options:{buttons_mode: true},
                css:{border:'none','font-size':'14px'} }]},  //saved searches
                
        center:{minsize:300, dropable:false,
            tabs:[{dockable:false, dragable:false, resizable:false,
                apps:[
                    {appid:'heurist_resultList', hasheader:true, name:'List', layout_id:'list',
                            css:{'background-color':'white','font-size':'14px'}, 
                            options:{title:'List', view_mode:'thumbs', recordview_onselect: 'popup', 
                            show_inner_header: true, show_url_as_link:true} },  //search result
                    {appid:'heurist_Map', layout_id:'map', options:{layout:['map','timeline'],tabpanel:true}, 
                                    css:{'background-color':'white'} } //mapping
                ]
            }]
        }
     },

    // 3 main tabs on top with accordion menu on each one - most of admin/import/export in iframes
    {id:'SearchAnalyze', name:'Search Analyze Publish', theme:'heurist', type:'cardinal',
    
        west:{size:260, Xminsize:150, apps:[{appid:'heurist_SearchTree', hasheader:false, 
                css:{border:'none', 'background':'none'},
                options:{btn_visible_dbstructure:true} }]},  //saved searches
                
        center:{Xminsize:300, dropable:false,
            apps:[{appid:'include_layout', name: 'AAA', layout_id:'FAP2',dragable:false,
                        options:{ref: 'SearchAnalyze2'}
                        ,css:{position:'absolute', top:0,left:0,bottom:'0.1em',right:0}}] //,'font-size':'0.9em'
    
        }
    },

    // old version - search panel on top, center - result list, east - tabs        
    {id:'SearchAnalyze2', name:'Search Analyze Publish2', theme:'heurist', type:'cardinal',
        north:{size:'8em', resizable:false, overflow:'hidden',
            apps:[
                {appid:'heurist_Search', hasheader:false, 
                css:{position:'absolute', top:0, left:0, right:0,bottom:0,
                border:'none', 'background':'white', 'min-width':'75em'}, 
            options:{has_paginator:false, btn_visible_newrecord:true, search_button_label:'Filter'} }, 
        ]},
        center:{Xminsize:300, dropable:false, apps:[{appid:'heurist_resultList', hasheader:false, 
                     dockable:false, dragable:false, css:{'background-color':'white','font-size':'0.9em'}, //AO 2020-01-30 ,'font-size':'12px'
                     options:{empty_remark:null, show_menu:true, support_collection:true, 
                     show_savefilter:true, show_inner_header:true, header_class:'ui-heurist-header2',show_url_as_link:true} }]},
        east:{size:'50%', Xminsize:300, dropable:false,
            tabs:[{dockable:true, dragable:false, resizable:false, adjust_positions:true, //css:{'font-size':'0.95em'},
                apps:[
                    {appid:'heurist_resultListExt', name: 'Record View', 
                                options:{url: 'viewers/record/renderRecordData.php?recID=[recID]&db=[dbname]', 
                                is_single_selection:true, 'data-logaction':'open_Record'}
                    },    // H3 record viewer
                    {appid:'heurist_resultListDataTable', name: 'List View', options:{ dataTableParams:{}, show_export_buttons:true } },
                    {appid:'heurist_Map', options:{'data-logaction':'open_MapTime'}}, // map viewer (map.php) inside widget (app_timemap.js)
                    {appid:'heurist_Map2', options:{'data-logaction':'open_MapTime', leaflet:true
                        , layout_params:{legend:'search,-basemaps,-mapdocs,250,off'} }}, 
                    
                    {appid:'heurist_reportViewer', name: 'Custom Reports'},

                    {appid:'heurist_Frame', name: 'Export',
                        options:{url: 'hclient/framecontent/exportMenu.php?db=[dbname]',
                                         isframe:true, 'data-logaction':'open_Export'}
                        ,css:{position:'absolute', top:0,left:0,bottom:0,right:0,'min-width':'75em'}},
                    
                    {appid:'heurist_Graph',   options:{title:'Network Diagram',
                                     url: 'hclient/framecontent/visualize/springDiagram.php?db=[dbname]',
                                     'data-logaction':'open_Network'}},
 
                    {appid:'heurist_resultListExt', name: 'Crosstabs', options:{title:'Crosstabs', 
                                url: 'viewers/crosstab/crosstabs.php?db=[dbname]','data-logaction':'open_Crosstabs'}}
                    
            ]}]
        }
    },

    // Heurist v6 version.
    {id:'SearchAnalyze3', name:'Search Analyze Publish2', theme:'heurist', type:'cardinal',
        center:{minsize:156, dropable:false, apps:[{appid:'heurist_resultList', hasheader:false,
                     dockable:false, dragable:false, css:{'background-color':'white','font-size':'0.9em'}, //AO 2020-01-30 ,'font-size':'12px'
                     options:{empty_remark:null, show_menu:true, support_collection:true, is_h6style:true, show_fancybox_viewer:true,
                     XXXrecordDivEvenClass: 'ui-widget-content',
                     show_savefilter:false, show_search_form:true, show_inner_header:true, 
                     show_url_as_link:true} }]},
        east:{size:'50%', Xminsize:300, dropable:false,
            tabs:[{dockable:true, dragable:false, resizable:false, adjust_positions:true, keep_width:true,
                        //css:{padding:'0px',width:'100%',height:'100%'},
                apps:[
                    {appid:'heurist_resultListExt', name: 'Record', 
                                options:{url: 'viewers/record/renderRecordData.php?recID=[recID]&db=[dbname]', 
                                is_single_selection:true, 'data-logaction':'open_Record',css:{overflow:'hidden'}}
                    },    // H3 record viewer
                    {appid:'heurist_resultListDataTable', name: 'List View', 
                        options:{ dataTableParams:{}, show_export_buttons:true } },
                    //{appid:'heurist_Map', options:{'data-logaction':'open_MapTime'}}, // map viewer (map.php) inside widget (app_timemap.js)
                    {appid:'heurist_Map2', options:{'data-logaction':'open_MapTime', leaflet:true
                        , layout_params:{legend:'search,-basemaps,-mapdocs,250,off', ui_main:true} }}, 
                    
                    {appid:'heurist_reportViewer', name: 'Report'},
                        
                    {appid:'heurist_Frame', name: 'Export',
                        options:{url: 'hclient/framecontent/exportMenu.php?db=[dbname]',
                                         isframe:true, 'data-logaction':'open_Export'}
                        ,css:{position:'absolute', top:0,left:0,bottom:0,right:0,'min-width':'75em'}},
                        
                    {appid:'heurist_Graph',   options:{title:'Network',
                                     url: 'hclient/framecontent/visualize/springDiagram.php?db=[dbname]',
                                     'data-logaction':'open_Network'}},
 
                    {appid:'heurist_resultListExt', name: 'Crosstabs', options:{title:'Crosstabs', 
                                url: 'viewers/crosstab/crosstabs.php?db=[dbname]','data-logaction':'open_Crosstabs',
                                css:{overflow:'hidden'}}}
            ]}]
        }
    },    
    
    
    {id:'H6Default2', name:'Heurist Def v6', theme:'heurist', type:'group', children:[
        {appid:'heurist_controlPanel', css:{position:'absolute', top:0,left:0,height:'50px',right:'-2px', 
                     'min-width':'77em'} },
        {appid:'heurist_slidersMenu', css:{position:'absolute', top:'50px',left:0,bottom:'0.1em',right:'2px'}}
        ]
    },
        
    {id:'SearchAnalyze4', name:'Search Analyze Publish2', theme:'heurist', type:'cardinal', 
    css:{height:'100%', width:'100%', position:'abdolute'},
    children:[
    {type:"center","children":[
        {appid:'heurist_resultList', hasheader:false,
                     css:{'background-color':'white','font-size':'0.9em',height:'100%'}, //AO 2020-01-30 ,'font-size':'12px'
                     options:{empty_remark:null, show_menu:true, support_collection:true, is_h6style:true, show_fancybox_viewer:true,
                     XXXrecordDivEvenClass: 'ui-widget-content',
                     show_savefilter:false, show_search_form:true, show_inner_header:true, 
                     show_url_as_link:true} }    
    ],"folder":true, "options":{minSize:509}},
    {"type":"east","children":[
        {type:"tabs","children":[
                    {appid:'heurist_resultListExt', name: 'Record', 
                                options:{url: 'viewers/record/renderRecordData.php?recID=[recID]&db=[dbname]', 
                                is_single_selection:true, 'data-logaction':'open_Record',css:{overflow:'hidden'}}
                    },    // H3 record viewer
                    {appid:'heurist_resultListDataTable', name: 'List View', 
                        options:{ dataTableParams:{}, show_export_buttons:true } },
                    {appid:'heurist_Map2', name:'Map', options:{'data-logaction':'open_MapTime', leaflet:true
                        , layout_params:{legend:'search,-basemaps,-mapdocs,250,off', ui_main:true} }}, 
                    
                    {appid:'heurist_reportViewer', name: 'Report'},
                        
                    {appid:'heurist_Frame', name: 'Export',
                        options:{url: 'hclient/framecontent/exportMenu.php?db=[dbname]',
                                         isframe:true, 'data-logaction':'open_Export'}
                        ,css:{position:'absolute', top:0,left:0,bottom:0,right:0,'min-width':'75em'}},
                        
                    {appid:'heurist_Graph', name:'Network', options:{title:'Network',
                                     url: 'hclient/framecontent/visualize/springDiagram.php?db=[dbname]',
                                     'data-logaction':'open_Network'}},
 
                    {appid:'heurist_resultListExt', name: 'Crosstabs', options:{title:'Crosstabs', 
                                url: 'viewers/crosstab/crosstabs.php?db=[dbname]','data-logaction':'open_Crosstabs',
                                css:{overflow:'hidden'}}}        
        
        ],"folder":true, css:{height:'100%'}},
    ],"folder":true, "options":{"init":"initally open","resizable":true, size:"50%"}}],"folder":true} 
        

];


