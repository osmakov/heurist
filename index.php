<?php

/**
* Main script initializing Heurist layout and performing initial search of parameter q is defined
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
use hserv\utilities\USystem;
use hserv\utilities\USanitize;
use hserv\controller\FrontController;

require_once dirname(__FILE__).'/autoload.php';

$isLocalHost = isLocalHost();

//validate that instance is ok and database is accessible
if( @$_REQUEST['isalive']==1){

    $system = new hserv\System();
    $is_inited = $system->init(@$_REQUEST['db'], true, false);
    if($is_inited){
        $mysqli = $system->getMysqli();
        $mysqli->close();
        print 'ok';
    }else{
        $error = $system->getError();
        print 'error: '.@$error['message'];
    }
    exit;

}elseif( @$_REQUEST['recID'] || @$_REQUEST['recid'] || array_key_exists('website', $_REQUEST) || array_key_exists('embed', $_REQUEST)){
    //redirection for CMS

    $recid = 0;
    if(@$_REQUEST['recID']){
        $recid = $_REQUEST['recID'];
    }elseif(@$_REQUEST['recid']){
        $recid = $_REQUEST['recid'];
    }elseif(@$_REQUEST['id']){
        $recid = $_REQUEST['id'];
    }
    if(strpos($recid, '-')>0){
        list($database_id, $recid) = explode('-', $recid, 2);
        $database_id = intval($database_id);
        $recid = intval($database_id).'-'.intval($recid);
    }else{
        $recid = intval($recid);
    }


    if(@$_REQUEST['fmt']){
        $format = filter_var($_REQUEST['fmt'], FILTER_SANITIZE_STRING);
    }elseif(@$_REQUEST['format']){
        $format = filter_var($_REQUEST['format'], FILTER_SANITIZE_STRING);
    }elseif (array_key_exists('website', $_REQUEST) || array_key_exists('embed', $_REQUEST)
    || (array_key_exists('field', $_REQUEST) && $_REQUEST['field']>0) )
    {
        $format = 'website';

        //embed - when heurist is run on page on non-heurist server
        if(array_key_exists('embed', $_REQUEST)){
            //require_once dirname(__FILE__).'/hserv/System.php';
            define('PDIR', HEURIST_INDEX_BASE_URL);
        }else{
            if(!defined('PDIR')) {define('PDIR','');}
        }
        include_once dirname(__FILE__).'/hclient/widgets/cms/websiteRecord.php';
        exit;

        if(intval(@$_REQUEST['field'])>0){
            $redirect = $redirect.'&field='.intval($_REQUEST['field']);
        }


    }elseif (array_key_exists('field', $_REQUEST) && intval($_REQUEST['field'])>0) {
        $format = 'web&field='.intval($_REQUEST['field']);
    }else{
        $format = 'xml';
    }

    redirectURL('redirects/resolver.php?db='.@$_REQUEST['db'].'&recID='.$recid.'&fmt='.$format
            .(@$_REQUEST['noheader']?'&noheader=1':''));
    return;

}elseif (@$_REQUEST['ent']){

    //to avoid "Open Redirect" security warning
    parse_str($_SERVER['QUERY_STRING'], $vars);
    $query_string = http_build_query($vars);

    redirectURL('hserv/controller/api.php?'.$query_string);
    return;

}elseif (@$_REQUEST['rty'] || @$_REQUEST['dty'] || @$_REQUEST['trm']){
        //download xml template for given db defintion

        if(@$_REQUEST['rty']) {$s = 'rty='.$_REQUEST['rty'];}
        elseif(@$_REQUEST['dty']) {$s = 'dty='.$_REQUEST['dty'];}
            elseif(@$_REQUEST['trm']) {$s = 'trm='.$_REQUEST['trm'];}

                redirectURL('redirects/resolver.php?db='.@$_REQUEST['db'].'&'.$s);
    return;


}elseif (@$_REQUEST['controller']=='ReportController' || array_key_exists('template',$_REQUEST) || array_key_exists('template_id',$_REQUEST)
        || @$_REQUEST['controller']=='ImportAnnotations'){

    //execute smarty template,  $_REQUEST may be composed in resolver.php
    $controller = new FrontController($_REQUEST);
    $controller->run();
    exit;

}elseif (array_key_exists('file',$_REQUEST) || array_key_exists('thumb',$_REQUEST) ||
          array_key_exists('icon',$_REQUEST)){

    if(array_key_exists('icon',$_REQUEST))
    {
        //download entity icon or thumbnail
        $script_name = 'hserv/controller/fileGet.php';
    }else {
        //download file, thumb or remote url for recUploadedFiles
        $script_name = 'hserv/controller/fileDownload.php';
    }

    //to avoid "Open Redirect" security warning
    parse_str($_SERVER['QUERY_STRING'], $vars);
    $query_string = http_build_query($vars);
    header( 'Location: '.$script_name.'?'.$query_string );
    return;

}elseif (@$_REQUEST['asset']){ //only from context_help - download localized help or documentation

    $params = USanitize::sanitizeInputArray();

    $name = $params['asset'];
    $part = strstr($name,'#');
    if($part){
         $name = strstr($name,'#');
    }

    //default ext is html
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if(!$extension){
        $name = $name . '.htm';
    }

    $help_folder = 'context_help/';

    $locale = $params['lang'];//locale
    if($locale && preg_match('/^[A-Za-z]{3}$/', $locale)){
        $locale = strtolower($locale);
        $locale = ($locale=='eng')?'' :($locale.'/');
    }else{
        $locale = '';
    }

    $asset = $help_folder.$locale.basename($name);
    if(!file_exists($asset)){
        //without locale - default is English
        $locale = '';
        $asset = $help_folder.basename($name);
    }

    if(file_exists($help_folder.$name)){
        //download
        header( 'Location: '.$asset.' '.$part );
        return;
    }else{
        exit('Asset not found: '.htmlspecialchars($name));
    }

}elseif (@$_REQUEST['logo']){

    list($host_logo, $host_url, $mime_type) = USystem::getHostLogoAndUrl(false);

    if($host_logo!=null && file_exists($host_logo)){
        header('Content-type: image/'.$mime_type);
        readfile($host_logo);
        return;
    }
}


define('IS_INDEX_PAGE',true);
if(!defined('PDIR')) {define('PDIR','');}

require_once dirname(__FILE__).'/hclient/framecontent/initPage.php';

?>

<!-- it is needed in preference dialog -->
<script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/jquery.layout.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/jquery.ui-contextmenu.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/ui.tabs.paging.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/evol.colorpicker.js" charset="utf-8"></script>
<link href="<?php echo PDIR;?>external/jquery.widgets/evol.colorpicker.css" rel="stylesheet" type="text/css">


<!-- script type="text/javascript" src="ext/js/moment.min.js"></script
<script type="text/javascript" src="ext/js/date.format.js"></script>
-->

<!-- array of possible layouts -->
<script type="text/javascript" src="layout_default.js"></script>

<script type="text/javascript" src="hclient/widgets/baseAction.js"></script>
<script type="text/javascript" src="hclient/widgets/baseConfig.js"></script>
<script type="text/javascript" src="hclient/widgets/database/dbAction.js"></script>

<script type="text/javascript" src="hclient/widgets/record/recordAction.js"></script>
<script type="text/javascript" src="hclient/widgets/record/recordAccess.js"></script>
<script type="text/javascript" src="hclient/widgets/record/recordAdd.js"></script>
<script type="text/javascript" src="hclient/widgets/record/recordAddLink.js"></script>
<script type="text/javascript" src="hclient/widgets/record/recordExportCSV.js"></script>
<script type="text/javascript" src="hclient/widgets/record/recordTemplate.js"></script>

<script type="text/javascript" src="hclient/widgets/report/reportViewer.js"></script>
<script type="text/javascript" src="hclient/widgets/report/reportEditor.js"></script>

<script type="text/javascript" src="hclient/widgets/viewers/recordListExt.js"></script>
<script type="text/javascript" src="hclient/widgets/search/search_faceted.js"></script>
<script type="text/javascript" src="hclient/widgets/search/search_faceted_wiz.js"></script>
<script type="text/javascript" src="hclient/widgets/viewers/app_timemap.js"></script>
<script type="text/javascript" src="hclient/widgets/search/search.js"></script>
<script type="text/javascript" src="hclient/widgets/search/searchByEntity.js"></script>
<script type="text/javascript" src="hclient/widgets/search/searchBuilder.js"></script>
<script type="text/javascript" src="hclient/widgets/search/searchBuilderItem.js"></script>
<script type="text/javascript" src="hclient/widgets/search/searchBuilderSort.js"></script>

<script type="text/javascript" src="hclient/core/ActionHandler.js"></script>
<script type="text/javascript" src="hclient/widgets/cpanel/controlPanel.js"></script>
<script type="text/javascript" src="hclient/widgets/cpanel/buttonsMenu.js"></script>
<script type="text/javascript" src="hclient/widgets/cpanel/slidersMenu.js"></script>
<script type="text/javascript" src="hclient/widgets/cpanel/navigation.js"></script>

<script type="text/javascript" src="hclient/widgets/search/svs_edit.js"></script>
<script type="text/javascript" src="hclient/widgets/search/svs_list.js"></script>
<script type="text/javascript" src="hclient/widgets/viewers/resultList.js"></script>
<script type="text/javascript" src="hclient/widgets/viewers/resultListMenu.js"></script>
<script type="text/javascript" src="hclient/widgets/viewers/resultListCollection.js"></script>
<script type="text/javascript" src="hclient/widgets/viewers/resultListDataTable.js"></script>

<script type="text/javascript" src="hclient/widgets/viewers/staticPage.js"></script>
<script type="text/javascript" src="hclient/widgets/viewers/connections.js"></script>
<script type="text/javascript" src="hclient/widgets/profile/profile_login.js"></script>

<!-- edit entity -->
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/selectFile.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/selectMultiValues.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/selectFolders.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_input.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing2.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editing_exts.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editTheme.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/hLayoutMgr.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/cms/CmsManager.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/configEntity.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageEntity.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchEntity.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefGroups.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecords.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecords.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecUploadedFiles.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecUploadedFiles.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/viewers/mediaViewer.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageSysDashboard.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchSysDashboard.js"></script>

<!-- autoload
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefRecStructure.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefDetailTypes.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchDefDetailTypes.js"></script>

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefRecTypes.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchDefRecTypes.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefRecTypeGroups.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefTerms.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefVocabularyGroups.js"></script>
-->

<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/admin/importStructure.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>viewers/map/mapPublish.js"></script>
<script type="text/javascript" src="<?php echo PDIR;?>hclient/framecontent/publishDialog.js"></script>

<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery.fancybox/jquery.fancybox.css" />
<script type="text/javascript" src="<?php echo PDIR;?>external/jquery.fancybox/jquery.fancybox.js"></script>

<!-- loaded dynamically in editing.js
<script type="text/javascript" src="<?php echo PDIR;?>external/tinymce5/tinymce.min.js"></script>
-->
<script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/editing/editorCodeMirror.js"></script>
<link rel="stylesheet" href="<?php echo PDIR;?>external/codemirror-5.61.0/lib/codemirror.css">

<!-- os, browser detector -->
<script type="text/javascript" src="<?php echo PDIR;?>external/js/platform.js"></script>

<?php
if(false && $isLocalHost){
    ?>
    <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/js/datatable/datatables.min.css"/>
    <script type="text/javascript" src="<?php echo PDIR;?>external/js/datatable/datatables.min.js"></script>
    <?php
}else{
    ?>
    <link href="https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-2.1.6/b-3.1.2/b-html5-3.1.2/datatables.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js" integrity="sha384-VFQrHzqBh5qiJIU0uGU5CIW3+OWpdGGJM9LBnGbuIH2mkICcFZ7lPd/AAtI7SNf7" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js" integrity="sha384-/RlQG9uf0M2vcTw3CX7fbqgbj/h8wKxw7C3zu9/GxcBPRKOEcESxaxufwRXqzq6n" crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-2.1.6/b-3.1.2/b-html5-3.1.2/datatables.min.js" integrity="sha384-naBmfwninIkPENReA9wreX7eukcSAc9xLJ8Kov28yBxFr8U5dzgoed1DHwFAef4y" crossorigin="anonymous"></script>

    <?php
}
?>

<script src="<?php echo PDIR;?>hclient/widgets/admin/repositoryConfig.js"></script>

<script type="text/javascript">

    function onPageInit(success){

        if(!success) {return;}


        $(document).on('focusin', function(e) {
            if ($(e.target).closest(".mce-window, .moxman-window").length) {
                e.stopImmediatePropagation();
            }
        });

<?php
/*
if(@$_SERVER['REQUEST_METHOD']=='POST'){
    $req_params = filter_input_array(INPUT_POST);
    print 'window.hWin.HAPI4.postparams='.json_encode($req_params).';';
    print 'console.log(window.hWin.HAPI4.postparams)';
}
*/
?>

        //
        // cfg_widgets and cfg_layouts are defined in layout_default.js
        //
        window.hWin.HAPI4.LayoutMgr.init(cfg_widgets, cfg_layouts);


        if($( "#heurist-about" ).dialog('instance')){
            $( "#heurist-about" ).dialog("close");
        }


        <?php
        //returns total records in db and counts of active entries in dashboard
        list($db_total_records, $db_has_active_dashboard, $db_workset_count) = $system->getTotalRecordsAndDashboard();
        echo 'window.hWin.HAPI4.sysinfo.db_total_records = '.$db_total_records.';';
        echo 'window.hWin.HAPI4.sysinfo.db_has_active_dashboard = '.$db_has_active_dashboard.';';
        echo 'window.hWin.HAPI4.sysinfo.db_workset_count = '.$db_workset_count.';';
        ?>

        var lt = window.hWin.HAPI4.sysinfo['layout'];
        window.hWin.HAPI4.is_publish_mode = (lt=='WebSearch');

        //
        // init layout
        //
        window.hWin.HAPI4.LayoutMgr.appInitAll( window.hWin.HAPI4.sysinfo['layout'], "#layout_panes");

        //2024-12-08 const layout_cfg = window.hWin.HAPI4.LayoutMgr2.layoutGetById('H6Default2');
        //2024-12-08 window.hWin.HAPI4.LayoutMgr2.layoutInit(layout_cfg, "#layout_panes" );

        
        onInitCompleted_PerformSearch();
    }

    //
    // init about dialog
    //
    function onAboutInit(){
        //definition of ABOUT dialog, called from Help > About, see content below
        $( "#heurist-about" ).dialog(
            {
                autoOpen: true,
                height: 180,
                width: 450,
                modal: true,
                resizable: false,
                draggable: false,
                create:function(){
                    $(this).parent().find('.ui-dialog-titlebar').addClass('fullmode').hide();
                }
                /*hide: {
                effect: "puff",
                duration: 500
                }*/
            }
        );

    }

    //
    // Performs inital search: parameters from request or from user preferences
    //
    function onInitCompleted_PerformSearch(){

        if(!window.hWin.HAPI4.is_publish_mode)
        {

            if( window.hWin.HAPI4.SystemMgr.versionCheck() ) {
                //version is old
                return;
            }


            var editRecID = window.hWin.HEURIST4.util.getUrlParameter('edit_id', window.location.search);
            if(editRecID>0){
                //edit record
                window.hWin.HEURIST4.ui.openRecordEdit(editRecID, null);
            }else
                if(window.hWin.HEURIST4.util.getUrlParameter('rec_rectype', window.location.search) ||
                    (window.hWin.HEURIST4.util.getUrlParameter('t', window.location.search) &&
                        window.hWin.HEURIST4.util.getUrlParameter('u', window.location.search)))
                {
                    //add new record from bookmarklet  - see recordEdit.php as alternative, it opens record editor in separate window
                    var url = window.hWin.HEURIST4.util.getUrlParameter('u', window.location.search);

                    var new_record_params = {
                        RecTypeID: window.hWin.HEURIST4.util.getUrlParameter('rec_rectype', window.location.search)
                        || window.hWin.HEURIST4.util.getUrlParameter('rt', window.location.search),
                        OwnerUGrpID: window.hWin.HEURIST4.util.getUrlParameter('rec_owner', window.location.search),
                        NonOwnerVisibility: window.hWin.HEURIST4.util.getUrlParameter('rec_visibility', window.location.search),
                        tag: window.hWin.HEURIST4.util.getUrlParameter('tag', window.location.search)
                        ||window.hWin.HEURIST4.util.getUrlParameter('k', window.location.search),
                        Title:  window.hWin.HEURIST4.util.getUrlParameter('t', window.location.search),
                        URL:  url,
                        ScratchPad:  window.hWin.HEURIST4.util.getUrlParameter('d', window.location.search)
                    };

                    //default rectype for bookmarklet addition
                    if(url && !(new_record_params['RecTypeID']>0)){

                        if(window.hWin.HAPI4.sysinfo['dbconst']['RT_INTERNET_BOOKMARK']>0) {
                            new_record_params['RecTypeID']  = window.hWin.HAPI4.sysinfo['dbconst']['RT_INTERNET_BOOKMARK'];
                        }else if(window.hWin.HAPI4.sysinfo['dbconst']['RT_NOTE']>0) {
                            new_record_params['RecTypeID']  = window.hWin.HAPI4.sysinfo['dbconst']['RT_NOTE'];
                        }
                    }

                    //add new record
                    window.hWin.HEURIST4.ui.openRecordEdit(-1, null, {new_record_params:new_record_params});

                }else if(window.hWin.HAPI4.sysinfo.db_has_active_dashboard>0) {
                    /*
                    var _supress_dashboard = (window.hWin.HEURIST4.util.getUrlParameter('cms', window.hWin.location.search)>0);
                    if(_supress_dashboard!==true){
                    //show dashboard (another place - _performInitialSearch in controlPanel)
                    var prefs = window.hWin.HAPI4.get_prefs_def('prefs_sysDashboard', {show_on_startup:1, show_as_ribbon:1});
                    if(prefs.show_on_startup==1 && prefs.show_as_ribbon!=1)
                    {
                    var _keep = window.hWin.HAPI4.sysinfo.db_has_active_dashboard;
                    window.hWin.HAPI4.sysinfo.db_has_active_dashboard=0;
                    $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE);//hide button

                    window.hWin.HEURIST4.ui.showEntityDialog('sysDashboard',
                    {onClose:function(){
                    $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_PREFERENCES_CHANGE);
                    }});
                    setTimeout(function(){window.hWin.HAPI4.sysinfo.db_has_active_dashboard = _keep;},1000);
                    }
                    }
                    */
                }

            $('body').css({'overflow':'hidden'});

        }

        $(document).trigger(window.hWin.HAPI4.Event.ON_SYSTEM_INITED, []);

        var os = platform?platform.os.family.toLowerCase():'';
        if(os.indexOf('android')>=0 || os.indexOf('ios')>=0){ //test || os.indexOf('win')>=0
            window.hWin.HEURIST4.msg.showElementAsDialog(
                {element:document.getElementById('heurist-platform-warning'),
                    width:480, height:220,
                    title: 'Welcome',
                    buttons:{'Close':function(){ $(this).dialog( 'close' )} } });
        }else if (window.hWin.HEURIST4.util.isIE() ) {
            window.hWin.HEURIST4.msg.showMsgDlg('Heurist is not fully supported in Internet Explorer. Please use Chrome, Firefox or Edge.');
        }else if (platform.description.toLowerCase().indexOf('safari')>=0){
            window.hWin.HEURIST4.msg.showElementAsDialog(
                {element:document.getElementById('heurist-safari-warning'),
                    width:480, height:260,
                    title: 'Safari browser support',
                    buttons:{'Close':function(){ $(this).dialog( 'close' )} } });
        }

    } //onInitCompleted_PerformSearch

</script>

</head>
<body style="background-color:#c9c9c9;">

    <div id="layout_panes">
        &nbsp;
    </div>

    <div id="heurist-about" style="width:300px;display:none;">
        <div class='logo'></div>
        <h4>Heurist Academic Knowledge Management System</h4>
        <p style="margin-top:1em;">version <?=HEURIST_VERSION?></p>
        <p style="margin-top: 1em;">Copyright (C) 2005-2023 <a href="https://sydney.edu.au/arts/" style="outline:none;" target="_blank" rel="noopener">University of Sydney</a></p>
    </div>

    <div id="heurist-platform-warning" style="display:none;">
        <p style="padding:10px">Heurist is designed primarily for use with a keyboard and mouse. Tablets are not fully supported at this time, except for data collection on Android (see FAIMS in the Help system).</p>

        <p style="padding:10px">Please <?php echo CONTACT_HEURIST_TEAM;?> for further information or to express an interest in a tablet version</p>
    </div>

    <div id="heurist-safari-warning" style="display:none;">
        <p style="padding:10px">
            Heurist is not fully supported in Safari.
            Sorry, we no longer support Apple's Safari browser which was discontinued on Windows over a decade ago due to the appearance of widely used free cross-platform browsers such as Chrome and Firefox.
        </p>

        <p style="padding:10px">
            Please download Chrome or Firefox to use with Heurist (and perhaps with your other applications).
        </p>
    </div>

    <div id="heurist-dialog">
    </div>

</body>
</html>