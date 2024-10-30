<?php
 $_is_new_cms_editor = true;

    /**
    *  Injection of Heuirst core scripts, styles and scripts to init CMS website template
    *
    *  It should be included in CMS template php sript in html header section
    *
    *  include_once 'websiteScriptAndStyles.php';
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



    /*
    Workflow on initialization:
    onHapiInit->initHeaderElements, onPageInit->initMainMenu->loadPageContent->afterPageLoad


    onHapiInit  - loads defintions and calls initHeaderElements and onPageInit

    initHeaderElements - substitute elements in header with values from CMS_HOME record
    onPageInit      -
    initMainMenu    - Inits main menu widget
    loadPageContent - Loads content of specified record to #main-content and inits all widgets
    afterPageLoad - applies custom css and js for loaded page, assign listeners for interpage binding
        assignPageTitle
        initLinksAndImages - add listeners for internal links and images

    _openCMSeditor - opens/hides side panel with CMS editor (listener of #btnOpenCMSeditor)
    */

 define('APOSTROPHE','&#039;');

 includeJQuery();

if (isLocalHost() && !@$_REQUEST['embed'])  {
?>
    <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/js/datatable/datatables.min.css"/>
    <script type="text/javascript" src="<?php echo PDIR;?>external/js/datatable/datatables.min.js"></script>
<?php
}else{
?>
<!-- old version
    <script type="text/javascript" src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css"/>
-->    
    <link href="https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-2.1.6/b-3.1.2/b-html5-3.1.2/datatables.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-2.1.6/b-3.1.2/b-html5-3.1.2/datatables.min.js" integrity="sha384-naBmfwninIkPENReA9wreX7eukcSAc9xLJ8Kov28yBxFr8U5dzgoed1DHwFAef4y" crossorigin="anonymous"></script>
<?php
}
?>
<script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/jquery.layout.js"></script>
<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery-ui-iconfont-master/jquery-ui.icon-font.css" />

<!-- CSS -->
<?php
    //PDIR.
    include_once dirname(__FILE__).'/../../framecontent/initPageCss.php';

    if(true || !$edit_OldEditor){ //creates new instance of heurist
        print '<script>window.hWin = window;</script>';
    }
    //init js variables from REQUEST
?>
<script>
    var editCMS_instance2 = null; //editor instance

    var page_first_not_empty = 0;
    var home_page_record_id=<?php echo $home_page_on_init; ?>;
    var init_page_record_id=<?php echo $open_page_or_record_on_init; ?>;
    var isWebPage = <?php echo $isWebPage ?'true':'false';?>;
    var current_page_id = 0;
    var current_language = '<?php echo $website_language_def?$website_language_def:'def';?>';
    var default_language = current_language; //is is needed for edit CMS
    var website_languages = '<?php echo $website_languages?implode(',',$website_languages):'';?>';
    var is_show_pagetitle_main = <?php echo $show_pagetitle?'true':'false';?>;//is show page title per website
    var isCMS_active = <?php echo @$_REQUEST['edit']?'true':'false';?>;//use new CMS editor and init it once
    var isCMS_InHeuristUI = <?php echo @$_REQUEST['edit']==4 ?'true':'false';?>;
    var isCMS_NewWebsite = <?php echo array_key_exists('newlycreated', $_REQUEST) ?'true':'false';?>;
    var is_embed =<?php echo array_key_exists('embed', $_REQUEST)?'true':'false';?>;
    var is_execute_homepage_custom_javascript = false;  //semaphore
    var isJsAllowed = <?php echo $website_custom_javascript_allowed?'true':'false';?>;
    var first_not_empty_page = 0;
    var website_title = <?php echo $website_title; ?>;

    var record_view_smarty_template = '<?php echo $record_view_smarty_template!=null?$record_view_smarty_template:'';?>';
    var record_view_target = '<?php echo $record_view_target!=null?$record_view_target:'';?>';
</script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/baseAction.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_query.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_dbs.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utilsCollection.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hapi.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/HSystemMgr.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/ActionHandler.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hRecordSearch.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/recordset.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/layout.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/core/temporalObjectLibrary.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>layout_default.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cpanel/navigation.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/svs_list.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/searchInput.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/search/search_faceted.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/selectMultiValues.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultList.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/recordListExt.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/resultListCollection.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/app_storymap.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cpanel/buttonsMenu.js"></script>

<!--
<script type="text/javascript" src="<?php echo PDIR;?>external/tinymce5/tinymce.min.js"></script>
-->
<?php
if($_is_new_cms_editor){
?>
<!-- @todo load these scripts dynamically if edit mode is ON -->
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/editCMS2.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/editCMS_SelectElement.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/editCMS_WidgetCfg.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/editCMS_ElementCfg.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/editCMS_SiteMenu.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/hLayoutMgr.js"></script>
<style>
.tox-toolbar{
    background-color: #b4eeff !important;
}
.ui-cms-mainmenu{
    background: rgb(135, 205, 118) !important;
}
</style>

<?php
}


if(!isEmptyArray($external_files)){
    foreach ($external_files as $ext_file){
        if(strpos($ext_file,'<link')===0 || strpos($ext_file,'<script')===0){
            print $ext_file."\n";
        }
    }
}

//do not include edit stuff for embed
if(!array_key_exists('embed', $_REQUEST)){
?>
    <script type="text/javascript" src="<?php echo PDIR;?>external/js/wellknown.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_geo.js"></script>

    <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-file-upload/js/jquery.iframe-transport.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-file-upload/js/jquery.fileupload.js"></script>

    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/selectFile.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing2.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_exts.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/ui.tabs.paging.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/evol.colorpicker.js" charset="utf-8"></script>
    <link href="<?php echo PDIR;?>external/jquery.widgets/evol.colorpicker.css" rel="stylesheet" type="text/css">
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageEntity.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchEntity.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/configEntity.js"></script>

    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecords.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecords.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecUploadedFiles.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecUploadedFiles.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageUsrTags.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchUsrTags.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/mediaViewer.js"></script>

    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/record/recordAction.js"></script>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/record/recordAccess.js"></script>

    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/admin/importStructure.js"></script>
<?php
}

if($_is_new_cms_editor || $edit_OldEditor){ //$edit_OldEditor defined in websiteRecord.php - if true we use old CMS editor
?>
    <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editorCodeMirror.js"></script>

    <link rel="stylesheet" href="<?php echo PDIR;?>external/codemirror-5.61.0/lib/codemirror.css">

    <script src="<?php echo PDIR;?>external/codemirror-5.61.0/lib/codemirror.js"></script>
    <script src="<?php echo PDIR;?>external/codemirror-5.61.0/lib/util/formatting.js"></script>
    <script src="<?php echo PDIR;?>external/codemirror-5.61.0/mode/xml/xml.js"></script>
    <script src="<?php echo PDIR;?>external/codemirror-5.61.0/mode/javascript/javascript.js"></script>
    <script src="<?php echo PDIR;?>external/codemirror-5.61.0/mode/css/css.js"></script>
    <script src="<?php echo PDIR;?>external/codemirror-5.61.0/mode/htmlmixed/htmlmixed.js"></script>

    <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>hclient/assets/css/marching_ants.css" />

    <style>
.cms-element-active{
    -webkit-box-shadow: inset 0px 0px 38px 10px rgb(201, 194, 249), 0px 0px 8px 10px rgba(0,0,0,0);
    box-shadow: inset 10px 10px 124px 14px rgb(201, 194, 249), 0px 0px 8px 10px rgba(0,0,0,0);
}
.cms-element-editing{
    /* frame around editing element
    -webkit-box-shadow: inset 0px 0px 0px 5px rgb(201, 194, 249);
    box-shadow: inset 0px 0px 0px 5px rgb(201, 194, 249);
    */
    -webkit-box-shadow: 0px 0px 0px 5px rgb(201, 194, 249);
    box-shadow: 0px 0px 0px 5px rgb(201, 194, 249);
}

/*     box-shadow: inset 0px 0px 38px 10px rgb(201, 194, 249), 0px 0px 8px 10px rgba(0,0,0,0);*/
.cms-element-overlay{
  visibility: hidden;
  position: absolute;
  top: 0;
  left: 0;
  background: rgba(201, 194, 249, 0.5);
}


.ui-heurist-publish .fancytree-active, .ui-heurist-publish .fancytree-editing, .ui-heurist-publish .fancytree-hover{
  background: rgba(201, 194, 249, 1) !important;
}
/*
.ui-heurist-publish .fancytree-node:hover
*/
.ui-heurist-publish span.fancytree-node {
    padding: 3px 0px !important;
}

/* use pseudo elmenent for overlay
  right: 0;
  width: 100%;
  height: 100%;
  z-index: 3;
.image::before {
  content: '';
  visibility: hidden;
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  width: 100%;
  height: 100%;
  z-index: 3;
  background: rgba(100, 81, 246, 0.9);
  border-radius: 10px;
  -webkit-transition: all 0.7s ease-out;
  transition: all 0.7s ease-out;
}

.image:hover::before {
  visibility: visible;
}
*/

    </style>

<?php
}
?>

<script>
// global
var RT_CMS_HOME, RT_CMS_MENU, DT_NAME, DT_EXTENDED_DESCRIPTION, DT_CMS_SCRIPT, DT_CMS_CSS, DT_CMS_PAGETITLE, DT_CMS_TOPMENUSELECTABLE, TRM_NO, TRM_NO_OLD;
var timeout_count = 0;

//
// Inits page for publication version
// It is invoked from onHapiInit
//
//  1. Inits hLayoutMgr
//  2. Calls initMainMenu
//
function onPageInit(success)
{
    try{
        //bootstrap workaround
        $.fn.button.noConflict();
        $.fn.tooltip.noConflict();
    }catch{

    }

    RT_CMS_HOME = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'];
    RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'];
    DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'];
    DT_EXTENDED_DESCRIPTION = window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'];
    DT_CMS_SCRIPT = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_SCRIPT'];
    DT_CMS_CSS = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_CSS'],
    DT_CMS_PAGETITLE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETITLE'],
    DT_CMS_TOPMENUSELECTABLE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TOPMENUSELECTABLE'],
    TRM_NO = window.hWin.HAPI4.sysinfo['dbconst']['TRM_NO'],
    TRM_NO_OLD = window.hWin.HAPI4.sysinfo['dbconst']['TRM_NO_OLD'];

    if(!success) {return;}

    $('#main-menu').hide();

    hLayoutMgr();//init global var layoutMgr

    //cfg_widgets is from layout_defaults.js
    window.hWin.HAPI4.LayoutMgr.init(cfg_widgets, null);

    //reload website by click on logo or title, opens first page with content
    $("#main-logo,#custom-logo,#main-title").on('click', function(event){

        var load_initially = home_page_record_id;
        <?php if($isEmptyHomePage){
            echo 'if(typeof first_not_empty_page !== "undefined" && first_not_empty_page>0){ load_initially=first_not_empty_page;}';
        }?>
        is_execute_homepage_custom_javascript = true;
        loadPageContent( load_initially );//on logo click
    });

    //fix bug for tinymce popups - it lost focus if it is called from dialog
    $(document).on('focusin', function(e) {
        if ($(e.target).closest(".tox-tinymce-aux").length) {
            e.stopImmediatePropagation();
        }
    });

    setTimeout(function(){
        //init main menu in page header
        //add menu definitions to main-menu

        //callback function from init menu
        function __onInitComplete(not_empty_page){
            //load given page or home page content
            var load_initially = home_page_record_id;
            first_not_empty_page = not_empty_page; //assign to global
            <?php if($isEmptyHomePage){
                echo 'if(typeof first_not_empty_page !== "undefined" && first_not_empty_page>0){ load_initially=first_not_empty_page;}';
            }?>
            is_execute_homepage_custom_javascript = true;

            //if url has "q" parameter - load page with initial search
            var initial_query_from_url = window.hWin.HEURIST4.util.getUrlParameter('q');
            var eventdata = null;
            if(initial_query_from_url){

                    eventdata = {detail:'ids', neadall:1, w:'a',
                                 q:initial_query_from_url,
                                 source: 'search_on_page_load',
                                 event_type: window.hWin.HAPI4.Event.ON_REC_SEARCHSTART,
                                 search_realm: 'search_group_1'
                                 };
            }
            loadPageContent( init_page_record_id>0 ?init_page_record_id :load_initially, eventdata);//on page init

        }

        if(current_language!='def'){
            window.hWin.HR = window.hWin.HAPI4.setLocale(current_language);
        }

        var topmenu = $('#main-menu');

        if(topmenu.length==0){ //menu-less page

            __onInitComplete();

        }else{
            initMainMenu( __onInitComplete );
        }

        $(document).trigger(window.hWin.HAPI4.Event.ON_SYSTEM_INITED, []);

        var itop = $('#main-header').height();


    },300);

}

//
// Inits main menu widget (element #main-menu)
//
function initMainMenu( afterInitMainMenu ){

    var topmenu = $('#main-menu');

    var lopts = {
                menu_recIDs: home_page_record_id,
                main_menu: true, //search for RT_CMS_HOME as root
                use_next_level: true,
                orientation: 'horizontal',
                toplevel_css: {background:'none'}, //bg_color 'rgba(112,146,190,0.7)'
                onInitComplete: afterInitMainMenu,
                onmenuselect: loadPageContent,  //on main menu select
                language: current_language
                };

    lopts = {heurist_Navigation:lopts};

    topmenu.attr('data-heurist-app-id','heurist_Navigation');
    window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, topmenu.parent(), lopts);

    topmenu.show();

    $('#main-languages').find('a[data-lang]').removeClass('lang-selected');
    $('#main-languages').find(`a[data-lang=${current_language}]`).addClass('lang-selected');
}

//
// Global reload of page with new language
//
function switchLanguage(event){

    var lang_code = $(event.target).attr('data-lang');

    if(lang_code && current_language != lang_code){
        //add url parameter
        current_language = lang_code;
        window.hWin.HR = window.hWin.HAPI4.setLocale(current_language);
        initHeaderTitle();
        loadPageContent(current_page_id);
        initMainMenu();

        //change footer
        let ele = $('div.page-footer-content');
        if(ele.length>1){
            ele.hide();//hide all
            let ele2 = $(`div.page-footer-content[data-lang=${lang_code}]`);
            if(ele2.length==0){ //not found
                $('div.page-footer-content[data-lang=""]').show();
            }else{
                ele2.show();
            }
        }


    }
    window.hWin.HEURIST4.util.stopEvent(event);
    return false;
}

//
// Loads content of specified record to #main-content and inits all widgets
// pageid    - record id to be loaded
// eventdata - data to be passed to afterPageLoad (to perform initial search or other action) - it may be call from another page
//
function loadPageContent(pageid, eventdata){
    var topmenu = $('#main-menu').find('div[widgetid="heurist_Navigation"]');

    // this is not website page, this is ordinary record - show it in main-recordview or popup
    if(window.hWin.HEURIST4.util.isNumber(pageid) &&  !page_cache[pageid]){

       if (! ((topmenu &&  topmenu.navigation('instance') && topmenu.navigation('isMenuItem',pageid))
              ||
              (eventdata && eventdata['isMenuItem'])) )
       {

           //check that pageid is cms page
           if(!usual_heurist_records[pageid]){

                var server_request = {
                    q: 'ids:'+pageid,
                    restapi: 1,
                    columns:
                    ['rec_ID', 'rec_RecTypeID'],
                    zip: 1,
                    format:'json'};
                //search for record type
                window.hWin.HAPI4.RecordMgr.search_new(server_request,
                        function(response){

                           if(window.hWin.HEURIST4.util.isJSON(response)) {
                               if(response['records'] && response['records'].length>0){
                                   var res = response['records'][0]['rec_RecTypeID'];
                                   if(res == RT_CMS_MENU || res == RT_CMS_HOME){
                                       if(!eventdata) eventdata = {};
                                       eventdata['isMenuItem'] = true;
                                       loadPageContent(pageid, eventdata);
                                   }else{
                                       usual_heurist_records.push(pageid);
                                       loadRecordContent(pageid);
                                   }
                               }
                           }
                });
                return;
           }
           loadRecordContent(pageid);
           return;
       }
    }


    $('#main-recordview').hide();
    $('#main-content').show();

    if(pageid>0){

        var page_target = $('#main-content');

        var supp_options = {
            heurist_emailForm: {website_record_id: home_page_record_id},
            heurist_resultListExt: {record_with_custom_styles: home_page_record_id},
            heurist_Navigation: {aftermenuselect: initLinksAndImages},
            lang: current_language,
            heurist_isJsAllowed: isJsAllowed
        };
        if(eventdata && (eventdata.event_type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
            || eventdata.event_type == window.hWin.HAPI4.Event.ON_REC_SELECT))
        {
            supp_options['heurist_SearchTree'] = {suppress_default_search:true};
            supp_options['heurist_SearchInput'] = {suppress_default_search:true};
        }

            //after load event listener
            function __loadPageContent(){

                window.hWin.HEURIST4.msg.sendCoverallToBack();
                $('body').find('#main-content').css('min-height', '');// remove min height

                if(!window.hWin.HAPI4.is_admin()){
                    isCMS_active = false;
                }

                $('#btnOpenCMSeditor').html(isCMS_active?'close editor':'website editor');

                if(isCMS_active){
                    if(!editCMS_instance2) {
                        editCMS_instance2 = editCMS2(this.document);//editCMS_Init
                    }

                    if (! editCMS_instance2.startCMS({
                                    record_id:pageid,
                                    container:'#main-content',
                                    isCMS_NewWebsite: isCMS_NewWebsite,
                                    close: function(){
                                        isCMS_active = false;
                                        $('#btnOpenCMSeditor').html('website editor');
                                    }})) //see editCMS2.js
                    {
                        //page is not loaded (previous page has been modified and not saved
                        return;
                    }

                }else{
                    layoutMgr.layoutInit( page_cache[pageid][DT_EXTENDED_DESCRIPTION], '#main-content', supp_options );
                }

                current_page_id = pageid;

                var page_footer = page_target.find('#page-footer');
                if(page_footer.length>0){  //adjust page footer height
                    page_footer.detach();
                    page_footer.appendTo( page_target );
                    page_target.css({'min-height':page_target.parent().height()-page_footer.height()-10 });
                }

                timeout_count = 0;
                afterPageLoad( document, pageid, eventdata);//execute custom script and custom css, assign page title
            } // END __loadPageContent

            if(page_cache[pageid]){ //this page has been already loaded
                __loadPageContent();
            }else{

                var server_request = {
                    q: 'ids:'+pageid,
                    restapi: 1,
                    columns:
                    ['rec_ID', DT_NAME, DT_EXTENDED_DESCRIPTION, DT_CMS_PAGETITLE, DT_CMS_TOPMENUSELECTABLE],
                    zip: 1,
                    format:'json'};

                if(isJsAllowed){
                    server_request.columns.push(DT_CMS_SCRIPT);
                }
                    server_request.columns.push(DT_CMS_CSS);

                //perform search see record_output.php
                window.hWin.HAPI4.RecordMgr.search_new(server_request,
                    function(response){

                        if(window.hWin.HEURIST4.util.isJSON(response)) {
                            if(response['records'] && response['records'].length>0){
                                var res = response['records'][0]['details'];
                                var keys = Object.keys(res);
                                for(var idx in keys){
                                    var key = keys[idx];

                                    if(key == DT_EXTENDED_DESCRIPTION){
                                        //the size content can be big so it stores in db as 64K chunks
                                        //implode all parts of page
                                        res[key] = Object.values(res[key]).join('');
                                    }else if(key != DT_NAME){
                                        //takes only first value
                                        res[key] = res[key][ Object.keys(res[key])[0] ];
                                    }
                                }
                                if(window.hWin.HEURIST4.util.isBase64(res[DT_EXTENDED_DESCRIPTION])){
                                    res[DT_EXTENDED_DESCRIPTION] = new TextDecoder().decode(
                                            window.hWin.HEURIST4.util.base64ToBytes(res[DT_EXTENDED_DESCRIPTION]));
                                }

                                page_cache[pageid] = res; //assign to cache after load from server side
                                __loadPageContent();
                            }else if(pageid!=home_page_record_id){ //page not found - load home page by default
                                loadPageContent(home_page_record_id);
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr({
                                    message: `Web Page not found (record #${pageid})`,
                                    error_title: 'Failed to load page'
                                });
                            }
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr({
                                message: response,
                                error_title: 'Webpage search failed'
                            });
                        }
                    });

            }

    }
} // loadPageContent

//
// loads record view template into #main-recordview or popup dialog
//
function loadRecordContent(url_or_record_id, target){

    if(!url_or_record_id){
        console.error('url_or_record_id not defined');
        return;
    }
    var url, is_smarty = false;
    if(!isNaN(parseInt(url_or_record_id)) && url_or_record_id>0){

        var record_id = url_or_record_id;

        if(record_view_smarty_template!=null && record_view_smarty_template!=''){

            url = window.hWin.HAPI4.baseURL+window.hWin.HAPI4.database+'/tpl/'+record_view_smarty_template+'/'+record_id;

            is_smarty = true;
        }else{
            //default renderRecordData.php
            url = window.hWin.HAPI4.baseURL+'?recID='+record_id+'&fmt=html&db='+window.hWin.HAPI4.database;
        }

    }else{
        url = url_or_record_id;
        var parts = url.split('/');
        is_smarty = ((window.hWin.HEURIST4.util.isArrayNotEmpty(parts)
            && parts.length>3 && parts[parts.length-3]=='tpl')
            ||
            (url.indexOf('template=')>0) || (url.indexOf('showReps.php')>0)
           );

    }

    if(record_view_target=='popup'){
        //in popup
        var width = is_smarty?(window.hWin?window.hWin.innerWidth:window.innerWidth)*0.8:600;
        var height = is_smarty?(window.hWin?window.hWin.innerHeight:window.innerHeight)*0.8:500;
        window.hWin.HEURIST4.msg.showDialog(url, { title:'.', width: width, height: height, modal:false });
                      
    }else {

        if(is_smarty && record_view_target!=''){
            var container = $('#'+record_view_target);
            if(container.length>0){

                var main_content = $('#main-content')
                container.fadeOut(500);
                container.parent()[0].scrollTop = 0;
                main_content.show();
                window.hWin.HEURIST4.msg.bringCoverallToFront(main_content.parent());
                var frm = container.find('iframe')
                frm.attr('src',url);
                frm.off('load');
                frm.on('load', function(){
                   container.show();
                   main_content.hide();
                   window.hWin.HEURIST4.msg.sendCoverallToBack(true);

                   frm = frm[0];
                   container.height(frm.contentWindow.document.body.scrollHeight);
                   initLinksAndImages($(frm.contentWindow.document.body));

                   container.find('button.keywords').on('click', function(){
                        $('#main-recordview').hide();
                        $('#main-content').show();
                   });
                });
                return;
            }
        }

        //default case
        if(!target) target = '_blank';
        window.open(url, target);
    }

} //loadRecordContent

var page_cache = {};
var usual_heurist_records = [];//record ids that are not CMS_PAGE - see loadPageContent

var previous_page_id = -1;

var datatable_custom_render = null;

//
// assign page title to #main-pagetitle
// is_show_pagetitle_main - for cms home
//
function assignPageTitle(pageid){

    var pagetitle = '';

    if(!window.hWin.HEURIST4.util.isempty(page_cache[pageid][DT_NAME])){
        pagetitle = window.hWin.HAPI4.getTranslation(page_cache[pageid][DT_NAME], current_language);
        pagetitle = window.hWin.HEURIST4.util.stripTags(pagetitle,'br,hr,p,i,b,u,em,strong,sup,sub,small,span');//<br>
    }

    var is_show_pagetitle = (is_show_pagetitle_main ||
         (!window.hWin.HEURIST4.util.isempty(page_cache[pageid][DT_CMS_PAGETITLE]) &&
          page_cache[pageid][DT_CMS_PAGETITLE]!=TRM_NO && page_cache[pageid][DT_CMS_PAGETITLE]!=TRM_NO_OLD));
    var title_container = $('#main-pagetitle');

    if(!window.hWin.HEURIST4.util.isempty(pagetitle)  && title_container.length>0 && is_show_pagetitle)
    {
        title_container.html( '<h2 style="margin:0px">'+pagetitle+'</h2>' ).show();
    }else{
        title_container.empty().hide();
        is_show_pagetitle = false;
    }

    // if page title is visible - increase height of header
    if($('#main-header').length>0 && $('#main-content-container').length>0){

        const h = $('#main-header').height();
        if(h==144 || h==180){ //default values
            $('#main-header').height(is_show_pagetitle?180:144);
            $('#main-content-container').css({top:is_show_pagetitle?187:151});
        }else if(h == 137 && navigator.userAgent.indexOf('Firefox') > 0){ //default value on Firefox
            $('#main-content-container').css({top:144});
        }

        $('#main-menu').css('bottom',is_show_pagetitle?40:0);

    }
}

// 0. assign page title
// 1. Replaces old custom css per page with new one (from DT_CMS_CSS)
// 2. Adds custom script (DT_CMS_SCRIPT) to header and executes it
//      it wraps this script into function afterPageLoad_[pageid](args)
// 3. Adds listeners for all "a" elements with href="pageid" for intepage website links (converts links)
// 4. Adds global listerner for ON_REC_SEARCHSTART and ON_REC_SELECT for interpage widget links
//
// eventdata - are arguments to be passed to custom javascript function
//             this object will be extented with url_params from current page url
//
function afterPageLoad(document, pageid, eventdata){

    //waiting till all widgets are inited
    var is_inited = layoutMgr.layoutCheckWidgets();
    if (is_inited===false) {
        timeout_count++;
        if(timeout_count<100){
            setTimeout(function(){ afterPageLoad(document, pageid, eventdata) },500);
            return;
        }else{

        }
    }

    assignPageTitle(pageid);

    if(typeof pageid==='undefined' || pageid==null ) {return;}

    //remove old style and custom style per page ===========================
    if(DT_CMS_CSS>0){
        if(previous_page_id>0
            && previous_page_id!=home_page_record_id
            && page_cache[previous_page_id]
            && $(page_cache[previous_page_id][DT_CMS_CSS]).is('style')){
            //remove previous
            var style = page_cache[previous_page_id][DT_CMS_CSS];

            document.getElementsByTagName('head')[0].removeChild(style);
        }

        //custom website css from home page has beem added already
        if(page_cache[pageid][DT_CMS_CSS] && pageid!=home_page_record_id)
        {

            if(typeof page_cache[pageid][DT_CMS_CSS]==='string'){

                var style = document.createElement('style');
                style.type = 'text/css';
                style.innerHTML = page_cache[pageid][DT_CMS_CSS];
                page_cache[pageid][DT_CMS_CSS] = style;
                document.getElementsByTagName('head')[0].appendChild(style);

            }else{
                //add style to page
                document.getElementsByTagName('head')[0].appendChild(page_cache[pageid][DT_CMS_CSS]);
            }
        }
    }
    previous_page_id = pageid;

    //pass url params to custom javascript
    var params = window.hWin.HEURIST4.util.getUrlParams(location.href);
    params['db'] = window.hWin.HAPI4.database;
    if(!eventdata) eventdata = {};
    eventdata['url_params'] = params;

    //execute custom javascript per loaded page =========================
    if(DT_CMS_SCRIPT>0){
        var func_name = 'afterPageLoad'+pageid;

        if(!window.hWin.HEURIST4.util.isFunction(window[func_name])){
            var script_code = page_cache[pageid][DT_CMS_SCRIPT];
            if(script_code && script_code !== false){ //false means it is already inited

                //add script to header

                var script = document.createElement('script');
                script.type = 'text/javascript';
                script.innerHTML = 'function '+func_name
                +'(document, pageid, eventdata){\n'
                +'try{\n' + script_code + '\n}catch(e){console.error(e)}}';

                $("head").append(script);

            }
        }

        if(window.hWin.HEURIST4.util.isFunction(window[func_name])){  //window[func_name] &&
            //script may have event listener that is triggered on page exit
            //disable it
            $( "#main-content" ).off( "onexitpage");
            //execute the script
            window[func_name]( document, pageid, eventdata );
        }
    }
    //execute custom javascript for home page =========================
    if(pageid!=home_page_record_id && is_execute_homepage_custom_javascript){
        var func_name = 'afterPageLoad'+home_page_record_id;
        if(window.hWin.HEURIST4.util.isFunction(window[func_name])){
            //script may have event listener that is triggered on page exit
            //disable it
            $( "#main-content" ).off( "onexitpage");
            //execute the script
            window[func_name]( document, pageid, eventdata );
        }
    }
    is_execute_homepage_custom_javascript = false;

    $('#main-content-container').scrollTop(0);// reset scroll

    // add current page as url parameter in browser url
    if(!is_embed){

        var spath= location.pathname;

        while (spath.substring(0, 2) === '//') spath = spath.substring(1);

        var surl;

        if(spath.endsWith('/web') || spath.endsWith('/website')) spath = spath + '/';//add last slash

        if(spath.search(/\/([A-Za-z0-9_]+)\/(website|web)\/.*/)>=0 || spath.indexOf('/web/')===0 ){
            //folder style parameters [database]/web/[site id]/[page id]/?q=[query params]

            const org_spath = spath;

            //remove after web
            if(spath.indexOf('/website/')>0){
                spath = spath.substring(0,spath.indexOf('/website/')+9);
            }else{
                spath = spath.substring(0,spath.indexOf('/web/')+5);
            }

            surl = spath + home_page_record_id;
            if(pageid!=home_page_record_id){
                surl = surl + '/' + pageid;
            }

            let remaining_path = org_spath.replace(surl, '');
            remaining_path = remaining_path.length > 0 ? remaining_path.split('/') : [];

            const handle_query = eventdata?.event_type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
                              && eventdata?.q;
            const handle_recids = remaining_path.length > 0;

            if(handle_query){
                surl += `/?q=${eventdata.q}`;
            }

            if(handle_recids){
                remaining_path = remaining_path.filter((rec_id) => !window.hWin.HEURIST4.util.isempty(rec_id) && rec_id > 0);
                surl += `${(handle_query ? '/?' : '&')}rec_id=${remaining_path.join(',')}`;
            }

            if(current_language && current_language!='def'){ //!= current_language_def
                surl += `${!handle_query && !handle_recids ? '/?' : '&'}lang=${current_language}`;
            }

        }else{
            //usual url parameters

            var params = window.hWin.HEURIST4.util.getUrlParams(location.href);

            params['db'] = window.hWin.HAPI4.database;
            params['website'] = '';
            params['id'] = home_page_record_id;
            if(current_language && current_language!='def'){ //!= current_language_def
                params['lang'] = current_language;
            }

            /* IJ Oct 2021 - Hide page id in URL, and cause reloads to move back to website homepage */
            if(pageid!=home_page_record_id){
                params['pageid'] = pageid;
            }

            s = [];

            $.each(Object.keys(params),function(i,key){
                if(key){
                    var v = encodeURIComponent(params[key]);
                    if(v!='') v = '=' + v;
                    if(key!='q'){
                        s.push(key + v);
                    }
                }
            });
            if(eventdata &&
                eventdata.event_type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART &&
                eventdata.q){
                s.push('q=' + eventdata.q);
            }
            surl = spath + '?' + s.join('&');



        }

        window.history.pushState({}, "Title", surl);

    }

    // add listeners for internal links and images
    initLinksAndImages();

    //Execute event - this search has been inited from different page
    if(eventdata && eventdata.event_type){
        if(eventdata.event_type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
            || eventdata.event_type == window.hWin.HAPI4.Event.ON_REC_SELECT)
        {
            window.hWin.HAPI4.RecordSearch.doSearch( this, eventdata );
        }else{
            $(document).trigger(eventdata.event_type, eventdata);//for select
        }
    }

    // Init search on different page  data.search_page!=current_page_id
    $(this.document).on(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
            +' '+window.hWin.HAPI4.Event.ON_REC_SELECT, function(e, eventdata) {


                if(eventdata && eventdata.search_page>0){

                    if(eventdata.search_page!=current_page_id){

                        var new_pageid = eventdata.search_page;
                        eventdata.search_page = null

                        if(e.type==window.hWin.HAPI4.Event.ON_REC_SELECT){
                            if(Array.isArray(eventdata.selection) && eventdata.selection.length>0){
                                //convert SELECT to SEARCHSTART
                                eventdata = {detail:'ids', neadall:1, w:'a',
                                     q:'ids:'+eventdata.selection.join(','),
                                     source: 'search_on_page_load',
                                     search_realm: eventdata.search_realm};

                            }else{
                                return; //ignore empty selection
                            }
                        }

                        eventdata.event_type = window.hWin.HAPI4.Event.ON_REC_SEARCHSTART;
                        loadPageContent(new_pageid, eventdata);//on link or selection - execute search on different page

                    }else{

                        eventdata.search_page = 0;
                        $('#main-recordview').hide();
                        $('#main-content').show();
                        window.hWin.HAPI4.RecordSearch.doSearch( this, eventdata );

                    }
                }

            });

    // Log interaction
    window.hWin.HAPI4.SystemMgr.user_log('VisitPage', pageid);
} //afterPageLoad

//
// Adds listeners for all "a" elements with href="pageid" for inter page website links (converts links)
// this is global function - see recordListExt
//  search_data - parameters from recordListExt with target page
//
function initLinksAndImages($container, search_data){

    if(!$container){
        $container = $('body');
    }else{
        $container = $($container);
    }


    /*

    1) attribute "data-query" - execute search with parameters where to show the result
    2) database/tpl/template/recid - to recordview

    3) recid - either popup or if pageid it loads the specified page

    to popup:
    database/view/recid
    ?recID=123&fmt=html&db=db
    renderRecordData

    to _blank
    all external links


    */

    // create internal links
    //find all link elements for loading another page and define onclick handler - loadPageContent
    $container.find('a').each(function(i,link){

        var href = $(link).attr('href');
        var parts = href?href.split('/'):null;

        //1. special case for search links in smarty reports
        if($(link).attr('data-query') ){ //href && href.indexOf('q=')===0 ||

                var query = $(link).attr('data-query');


                var current_template = '__def';
                var request = {detail:'ids', neadall:1, w:'a', q:query};

                if(search_data){
                        if(search_data.search_page) request['search_page'] = search_data.search_page;
                        if(search_data.search_realm) request['search_realm'] = search_data.search_realm;
                        if(search_data.smarty_template) current_template = search_data.smarty_template;
                }else{
                    if($(link).attr('data-search-page'))  request['search_page'] = $(link).attr('data-search-page');
                    if($(link).attr('data-search-realm'))  request['search_realm'] = $(link).attr('data-search-realm');
                }

                if(!href || href=='#' || href.indexOf('q=')===0){
                    //change href for right click - to open this link in new tab

                    href = [window.hWin.HAPI4.baseURL,window.hWin.HAPI4.database,'web',
                            home_page_record_id, current_page_id,encodeURIComponent(query)];
                    href = href.join('/');
                    $(link).attr('href', href);
                }

                $(link).on('click', function(event){
                    window.hWin.HEURIST4.util.stopEvent(event);
                    window.hWin.HAPI4.RecordSearch.doSearch(window.hWin,request);
                    return false;
                });

        }else
        //2. Open template links in main-recordview div. If this div is missed in popup
        //       tpl/template/recid
        if( ((window.hWin.HEURIST4.util.isArrayNotEmpty(parts)
            && parts.length>3 && parts[parts.length-3]=='tpl')
            ||
            (href?.indexOf('template=')>0 || href?.indexOf('showReps.php')>0))
            &&
            ( !($(link).attr('target')=='_blank' || $(link).attr('target')=='_self')  || record_view_target!='')
          )
        {
                $(link).on('click', function(event){

                    var link;
                    if($(event.target).is('a')){
                       link = $(event.target);
                    }else{
                       link = $(event.target).parents('a');
                    }

                    var url = link.attr('href');
                    window.hWin.loadRecordContent(url, $(link).attr('target'));
                    window.hWin.HEURIST4.util.stopEvent(event);
                    return false;
                });

        }else

        // 3. link to website page or heurist record in this database
        //
        if (href && href!='#')    //$(link).attr('target')!='_blank'
        {
            var rec_id = 0;

            if(window.hWin.HEURIST4.util.isArrayNotEmpty(parts)
                && parts.length>2 && parts[parts.length-2]=='view'){

                rec_id = parts[parts.length-1];
            }else if(  (href.indexOf(window.hWin.HAPI4.baseURL)===0 || href[0] == '?'
                || href.indexOf('../heurist/?')===0  || href.indexOf('./?')===0)
                && window.hWin.HEURIST4.util.getUrlParameter('db',href) == window.hWin.HAPI4.database )
            {
                    //internal website navigation
                    rec_id = window.hWin.HEURIST4.util.getUrlParameter('pageid',href);
                    if(rec_id>0){
                        //@todo: if more than 2 parameters (pageid and database) reload the page entirely
                        if($(link).attr('target')!='_blank'){
                            return;
                        }
                    }else{
                        rec_id  = window.hWin.HEURIST4.util.getUrlParameter('recID',href);
                    }
            }else if(href.indexOf('./')===0){
                    //
                    rec_id = href.substring(2);
            }else if(!isNaN(parseInt(href)) && href>0){ //integer
                    // href="123" - it can be record or page id
                    rec_id = href;
            }

            if(!isNaN(parseInt(rec_id)) && rec_id>0){

                $(link).attr('data-pageid', rec_id);

                var eventdata = null;

                $(link).on('click', function(event){

                    var pageid = $(event.target).attr('data-pageid');
                    window.hWin.loadPageContent(pageid, eventdata);
                    window.hWin.HEURIST4.util.stopEvent(event);
                    return false;

                });
            }


        }
        if (!window.hWin.HEURIST4.util.isempty(href) && href!='#' && (href.indexOf('./')==0 || href.indexOf('/')==0)){ //relative path
              href = window.hWin.HAPI4.baseURL + href.substring(href.indexOf('/')==0?1:2);
              $(link).attr('href',href);
        }

    });

    // Ensure each image and embedded source is correct for current server + database
    $('img, embed').each(function(i,ele){window.hWin.HEURIST4.util.restoreRelativeURL(ele);});

    // Handle login buttons within webpage content
    let $btn_signin = $($container[0].querySelectorAll('#btn_signin:not(.cms-button)'));
    if($btn_signin.length>0){
        $btn_signin.on('click', () => {
            window.hWin.HEURIST4.ui.checkAndLogin(true, () => {location.reload();});
        });

        $('#btn_signin.cms-button').hide();
    }else{
        $('#btn_signin.cms-button').show();
    }
}

//
// After initialization of HAPI it checks database version, refreshes local definitions
// and calss   ->initHeaderElements and ->onPageInit
//
function onHapiInit(success){

    if(!success){
        window.hWin.HEURIST4.msg.showMsgErr({
            message: 'Cannot initialize system on client side, please consult Heurist developers',
            error_title: 'Unable to initialise Heurist'
        });
        window.hWin.HEURIST4.msg.sendCoverallToBack();
        return;
    }

    var res = window.hWin.HEURIST4.util.versionCompare(window.hWin.HAPI4.sysinfo.db_version_req,
                                                window.hWin.HAPI4.sysinfo.db_version);
    if(res==-2){ //-2= db_version_req newer
        window.hWin.HEURIST4.msg.showMsgErr({
            message: '<h3>Old version database</h3>'
                    +'<p>You are trying to load a website using a more recent version of Heurist than the one used for the database being accessed.</p>'
                    +'<p>Please ask the owner of the database to open it in the latest version of Heurist which will apply the necessary updates.</p>',
            error_title: 'Database is out-of-date'
        });
        window.hWin.HEURIST4.msg.sendCoverallToBack();
        return;
    }

    var lang_from_url = window.hWin.HEURIST4.util.getUrlParameter('lang');
    if(lang_from_url){
        current_language = lang_from_url;
    }

    function __init_completed(success){
        if(success){

            //substitute values in header
            initHeaderElements();
            onPageInit(success);

            if(window.hWin.HAPI4.sysinfo.host_logo && $('#host_info').length>0){


                $('<div><a href="'+(window.hWin.HAPI4.sysinfo.host_url?window.hWin.HAPI4.sysinfo.host_url:'#')
                    +'" target="_blank" style="text-decoration:none;color:black;">'
                            +'<span>at: </span>'
                            +'<img src="'+window.hWin.HAPI4.sysinfo.host_logo
                            +'" height="35" align="center"></a></div>')
                .appendTo( $('#host_info') );
            }



<?php
if(isset($customTemplateNotFound)){
    print 'window.hWin.HEURIST4.msg.showMsgDlg("Custom website template '
        .$customTemplateNotFound.' not found. Default template will be used");';
}?>

        }
    }

    window.hWin.HAPI4.EntityMgr.refreshEntityData('all', __init_completed);
}

//
// substitute elements in header with values from CMS_HOME record
// for example $image_logo->#main-logo
//
function initHeaderElements(){

/*
$image_logo  -> #main-logo
$image_altlogo -> #main-logo-alt
$website_title -> #main-title>h2
$title_alt -> #main-title-alt
$title_alt2 -> #main-title-alt2
$website_languages_links ->#main-languages
*/

    // Load and Add banner image
    <?php if($image_banner){ ?>

        // Load image
        let banner_img = new Image();
        banner_img.onload = function(){
            // Load background image and remove background: none
            let styles = $('#main-header').attr('style');
            styles = styles.replace('background: none !important;', '');
            $('#main-header').attr('style', 'background-image: url(\'<?php print $image_banner; ?>\') !important;' + styles);
            initHeaderTitle();
        };
        banner_img.src = '<?php print $image_banner; ?>';

    <?php } ?>

    //main logo image
    if($('#main-logo').length>0){
        $('#main-logo').empty();
        $('<a href="#" style="text-decoration:none;"><?php print $image_logo;?></a>')
        .appendTo($('#main-logo'));

        let img = $('#main-logo img');
        if(img.length > 0 && !img[0].complete && !window.hWin.HEURIST4.util.isempty(img.attr('src'))){
            img.css('max-height',$('#main-logo').css('max-height'));
            img.on('load', () => {
                $('#main-title').css({ left:$('#main-logo').width()+10 });
                $('#main-title').fadeIn(500);
                $('#main-title').attr('data-adjusted',1)
            });
        }else  if(img.length == 0){
            $('#main-title').css({
                left: '10px',
                top: '30px'
            });
        }
    }

    if($('#main-logo-alt').length>0){
    <?php if($image_altlogo){ ?>
        var ele = $('#main-logo-alt').css({'background-size':'contain',
                'background-position': 'top',
                'background-repeat': 'no-repeat',
                'background-image':'url(\'<?php print $image_altlogo;?>\')'}).show();
    <?php if($image_altlogo_url){ ?>
        ele.css('cursor','pointer').on({click:function(){window.open("<?php print $image_altlogo_url;?>",'_blank')}});
    <?php }}else{ ?>
        $('#main-logo-alt').hide();
    <?php } ?>
    }
    if($('#main-title-alt').length>0){
        $('#main-title-alt').html('<?php print str_replace("'",APOSTROPHE, $title_alt);?>');
    }
    if($('#main-title-alt2').length>0){
        $('#main-title-alt2').html('<?php print str_replace("'",APOSTROPHE, $title_alt2);?>');
    }

    if($('#main-languages').length>0){
        $('#main-languages').html('<?php print str_replace("'",APOSTROPHE, $website_languages_links);?>');
    }

    // Setup login button, if needed
    if($('#btn_signin').length>0){
        $('#btn_signin').on('click', () => {
            window.hWin.HEURIST4.ui.checkAndLogin(true, () => {location.reload();});
        });
    }

    initHeaderTitle(website_title);


  $('.header-element').css({'border':'none'});

} //initHeaderElements

//
// uses global var website_title
//
function initHeaderTitle(){

    if(website_title){

        var headertitle = window.hWin.HAPI4.getTranslation(website_title, current_language);

        document.title = window.hWin.HEURIST4.util.stripTags(headertitle);
        headertitle = window.hWin.HEURIST4.util.stripTags(headertitle,'br,hr,p,i,b,u,em,strong,sup,sub,small,span');


        var ele = $('#main-title');
        var isFirstInit = (ele.length>0 && ele.children().length==0);

        // show shadow for title if there is header background image (banner)
        let bg_img = $('#main-header').css('background-image');
        let css_shadow = '';
        if(!(bg_img=='' || bg_img=='none')){
            css_shadow = ' style="text-shadow: 3px 3px 5px black"';
        }

        ele.empty().append(`<h2${css_shadow}>${headertitle}</h2>`);

        if(isFirstInit && ele.parent().is('#main-header')){
            ele.hide();
            if(!$('#main-logo-alt').is(':visible')){
                ele.css({right:10});
            }

            let img = $('#main-logo img');
            if(img.length < 1){ // logo element missing, show title
                ele.show();

            //show in the same time with logo image
            }else if(img[0].complete){ // already loaded logo

                ele.css({left:$('#main-logo').width()+10 });
                ele.fadeIn(500);
            }else if(ele.attr('data-adjusted')!=1){ // add onload for logo

                img.on('load', () => {
                    ele.css({ left:$('#main-logo').width()+10 });
                    ele.fadeIn(500);
                });
            }
            return;
        }



        ele.show();
    }

}

//
// load popup with simple math problem, success leads to the creation of the report email
//
function performCaptcha(){

    var rand1 = Math.floor(Math.random() * 9) + 1;
    var rand2 = Math.floor(Math.random() * 9) + 1;
    var res = rand1 + rand2 + 1;

    window.hWin.HEURIST4.msg.showPrompt(rand1 +" + "+ rand2 +" + 1 = <input id=\'dlg-prompt-value\' class=\'text ui-corner-all\'"
        +" style=\'max-width: 250px; min-width: 10em; width: 250px; margin-left:0.2em\' autocomplete=\'off\'/>",
        function(val){
            if(res != val){

                window.hWin.HEURIST4.msg.showMsgFlash("Report Failed, Incorrect Answer", 2500);
            }else{

                window.hWin.HEURIST4.msg.showMsgFlash("Preparing Email", 2500);

                var url = window.location.href;
                url = url.replace(/&/g, '%26');

                var subject = "Heurist Website Content report for DB: " + window.hWin.HAPI4.database;
                var body = "Content reported: " + url;

                var link = encodeURI("mailto:support@heuristnetwork.org?subject=" + subject + "&body=" + body);

                window.open(link, '_blank');
            }
        }, {title: "Captcha Test", yes: "Proceed", no: "Cancel"});
}

//
//  opens/hides side panel with NEW CMS editor controls  (see link #btnOpenCMSeditor in cmsTemplate.php)
//
function _openCMSeditor(event){

    var btn = $(event.target);

    if(window.hWin.HAPI4.is_admin()){

        if(isCMS_active){
            //close
            isCMS_active = false;
            editCMS_instance2.closeCMS();

        }else{
            $('#main-recordview').hide();
            $('#main-content').show();

            isCMS_active = true;
            if(!editCMS_instance2) editCMS_instance2 = editCMS2(this.document);//editCMS_Init
            editCMS_instance2.startCMS({
                record_id: current_page_id,
                //content: page_cache[current_page_id],  //html or json
                isCMS_NewWebsite: isCMS_NewWebsite,
                container:'#main-content',
                close: function(){
                    isCMS_active = false;
            }});//see editCMS2.js
        }

        btn.html(isCMS_active?'close editor':'website editor');

    }else{
        isCMS_active = false;
        if(editCMS_instance2) editCMS_instance2.closeCMS();
        btn.hide();
    }
}

var prepared_params = {guest_data:true};//allow guest login
//
// Init HAPI
//
$(document).ready(function() {

    let ele = $('body').find('#main-content');
    ele.css('min-height', '70px');// set min height to ensure the coverall is some what viewable
    window.hWin.HEURIST4.msg.bringCoverallToFront(ele);
    ele.show();

    $('body').find('#main-menu').hide();//will be visible after menu init

    // Standalone check
    if(!window.hWin.HAPI4){
        window.hWin.HAPI4 = new hAPI('<?php echo htmlspecialchars($_REQUEST['db'])?>',
                    onHapiInit<?php print array_key_exists('embed', $_REQUEST)?",'".PDIR."'":'';?>);
    }else{
        // Not standalone, use HAPI from parent window
        initHeaderElements();
        onPageInit( true );
    }
});
</script>

<style>
<?php
if(!$edit_OldEditor){
?>
div.coverall-div {
    background-position: top;
    background-color: white;
    opacity: 1;
}

div.CodeMirror{
    height:100%;
}
.CodeMirror *{
    /* font-family: Courier, Monospace !important; */
    font-family: Arial, sans-serif !important;
    font-size: 14px;
}
.CodeMirror div.CodeMirror-cursor {
    visibility: visible;
}

<?php
}
//inject custom css and script for home page at once
//for other pages script will be added and executed dynamically

// style from field DT_CMS_CSS of home record
if($website_custom_css!=null){
    print $website_custom_css;
}
?>
</style>
<?php
// javascript from field DT_CMS_SCRIPT of home record
if($website_custom_javascript!=null){

    print '<script>function afterPageLoad'.$home_page_on_init.'(document, pageid){'."\n";
    print "try{\n".$website_custom_javascript."\n}catch(e){console.error(e)}}</script>";
}

//generate main menu on server side - for bootstrap menu
$mainmenu_content = null;

$ids_was_added = array();
$resids = array();
$records = recordSearchMenuItems($system, array($home_page_on_init), $resids, true, true);
if(is_array($records) && !@$records['status']){
$mainmenu_content = _getMenuContent(0, array($home_page_on_init), 0);
$mainmenu_content = '<ul>'.$mainmenu_content.'</ul>';
}

function _getFld($record,$dty_ID){
    $res = @$record['details'][$dty_ID];
    $ret = (!isEmptyArray($res))?array_shift($res):null;
    return $ret;
}

function _getMenuContent($parent_id, $menuitems, $lvl){
   global $system, $records, $ids_was_added, $home_page_on_init;

            $res = '';
            $resitems = array();

            $fields = array(DT_NAME,DT_SHORT_SUMMARY,DT_CMS_TARGET, //DT_CMS_CSS,
            DT_CMS_PAGETITLE,DT_EXTENDED_DESCRIPTION,DT_CMS_TOP_MENU,DT_CMS_MENU );

            foreach($menuitems as $page_id){

                if(in_array($page_id, $ids_was_added)){
                    //already was included - recursion

                }else{

                    $record = recordSearchByID($system,$page_id,$fields,'rec_ID,rec_RecTypeID');

                    $menuName = _getFld($record, DT_NAME);
                    $menuTitle = _getFld($record, DT_SHORT_SUMMARY);
                    $recType = $record['rec_RecTypeID'];

                    //target and position
                    $pageTarget = _getFld($record,DT_CMS_TARGET);

                    $showTitle = _getFld($record,DT_CMS_PAGETITLE);

                    $showTitle = true;

                    $hasContent = (_getFld($record,DT_EXTENDED_DESCRIPTION)!=null);

                    array_push($ids_was_added, $page_id);

                    $url = 'javascript:{loadPageContent('.$page_id.');window.hWin.HEURIST4.util.stopEvent(event);}';

                    $res = $res.'<li><a href="'.$url.'" data-pageid="'.$page_id.'"'
                                        .($pageTarget?' data-target="'.$pageTarget.'"':'')
                                        .($showTitle?' data-showtitle="1"':'')
                                        .($hasContent?' data-hascontent="1"':'')
                                        .' title="'.htmlspecialchars($menuTitle).'">'
                                        .htmlspecialchars($menuName).'</a>';

                    $subres = '';
                    $submenu = @$record['details'][DT_CMS_MENU];
                    if(!$submenu){
                        $submenu = @$record['details'][DT_CMS_TOP_MENU];
                    }
                    //has submenu
                    if(is_array($submenu)){

                        if(!empty($submenu)){

                            $subrec = array();
                            foreach($submenu as $id=>$rec){
                                $subrec[] = $rec['id'];
                            }

                            //next level
                            $subres = _getMenuContent($page_id, $subrec, $lvl+1);

                            if($subres!='') {
                                $res = $res.'<ul>'.$subres.'</ul>';//'.($lvl==0?' class="level-1"':'').'
                            }
                        }
                    }

                    $res = $res.'</li>';

                    if($lvl==0 && count($menuitems)==1){ // && that.options.use_next_level
                            return $subres;
                    }


                }
            }//for

            return $res;
}//_getMenuContent
?>