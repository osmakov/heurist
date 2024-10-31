<?php
/**
* Loads Heurist user custom theme from usr_Preferences
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

There are 3 color themes in Heurist.
Main (gray) with option of different bg (white) for lists and popups
Editor (light blue)
Header (iron head flower color)
Each theme has its own set for text/label, background, inputs bg and border colors.  Main and Editor share the same Color for buttons/clickable elements (default:lightgray; focus:gray with border; pressed:blue). Header’s buttons are always the same color as main background.
There are still some exception:
Result list color scheme - may I uniform colors with our general scheme?
Optgroup (group header in dropdown)  bg: #ECF1FB - can be changed to #95A7B7 (headers)
Resource selector (in edit form)  bg: #F4F2F4 - can be changed to button light gray or pressed button (light blue)
Select linked record button   bg:#f0ecf0 - can be changed to button light gray or pressed button (light blue)
Scrollbar tracks and thumbs  rgba(0,0,0,0.3)/#bac4cb

*/
require_once dirname(__FILE__).'/../../autoload.php';

global $ut;

// arbitrary color scheme defined in script that includes this one
// usage: websiteRecord.php takes color scheme from field of CMS_HOME record
if(isset($site_colors) && $site_colors!=null){

    $ut = json_decode($site_colors, true);

}else{

    if(!isset($system)){

        // init main system class
        $system = new hserv\System();

        if(@$_REQUEST['db']){
            //if database is defined then connect to given database
            $system->init(@$_REQUEST['db']);
        }
    }
    if($system->is_inited()){
        $user = $system->getCurrentUser();
        $ut = @$user['ugr_Preferences']['custom_theme'];
        if($ut!=null){
            $ut = json_decode($ut, true);
        }
    }
}
//default colors
define('CLR_WHITE','#ffffff');
define('CLR_BORDER', '#95A7B7');
define('CLR_ACTIVE', '#212121');
define('CLR_LIGHTGRAY', '#999999');
define('CLR_GRAY', '#555555');
define('CLR_DARKGRAY', '#333333');
define('CLR_BG_DARKGRAY', '#e0dfe0');
define('CLR_INPUT', '#F4F2F4');
define('CLR_HOVER_COLOR', '#2b2b2b');
define('CLR_SP_BORDER', '#003eff'); //clickable pressed
define('CLR_SD_BG', '#f2f2f2'); //disabled bg

define('CLR_DESIGN_BG', '#DAD0E4');
define('CLR_IMPORT_BG', '#307D96');
define('CLR_IMPORT_FADE_BG', '#e3f0f0');
define('CLR_PUBLISH_BG', '#627E5D');
define('CLR_PUBLISH_FASE_BG', '#CCEAC5');
define('CLR_ADMIN_BG', '#676E80');
define('CLR_EXPLORE_FADE_BG', '#D4DBEA');
define('CLR_EXPLORE_BG', '#4477B9');

if(!isset($ut) || !is_array($ut)){
    $ut = array();
}

$def_ut = array(
    //main scheme
    'cd_bg'=>CLR_BG_DARKGRAY,
    'cl_bg'=>CLR_WHITE,
    'cd_input'=>CLR_INPUT,
    'cd_color'=>CLR_DARKGRAY,
    'cd_border'=>CLR_BORDER,
    //alt scheme
    'ca_bg'=>'#364050',
    'ca_color'=>CLR_WHITE,
    'ca_input'=>'#536077',
    //editor
    'ce_bg' =>'#ECF1FB',
    'ce_color'=>'#6A7C99',
    'ce_input'=>CLR_WHITE,
    'ce_helper'=>CLR_LIGHTGRAY,
    'ce_readonly'=>CLR_LIGHTGRAY,
    'ce_mandatory'=>'#CC0000',
    //clickable default
    'cd_corner'=>'0',

    'sd_color' =>CLR_GRAY,
    'sd_bg'    =>CLR_SD_BG,

    //clickable hover
    'sh_border' =>CLR_LIGHTGRAY,
    'sh_color'  =>CLR_HOVER_COLOR,
    'sh_bg'     =>CLR_BORDER,

    //clickable active
    'sa_border' =>'#aaaaaa',
    'sa_bg'     =>CLR_BORDER,
    'sa_color'  =>CLR_ACTIVE,

    //clickable pressed
    'sp_border' =>CLR_SP_BORDER,
    'sp_color'  =>CLR_WHITE,
    'sp_bg'     =>'#9CC4D9'

);

function uout($idx, $def){
    global $ut;
    if(@$ut[$idx]==null || @$ut[$idx]==''){
        print $def;
    }else{
        print $ut[$idx];
    }
}
?>
/* MAIN SCHEME */
.ui-dialog .ui-dialog-buttonpane button.ui-state-hover,
.ui-dialog .ui-dialog-buttonpane button.ui-state-focus  {
    background: none;
    background-color: <?php uout('cd_bg', CLR_BG_DARKGRAY);?> !important;
}
textarea.ui-widget-content, input.ui-widget-content, select.ui-widget-content{
    background: <?php uout('cd_input', CLR_INPUT);?> !important; /*0511 !important;*/
}
.ui-widget-content, .ui-widget-content-gray {
    border: 1px solid <?php uout('cd_bg', CLR_BG_DARKGRAY);?>;
    background: <?php uout('cd_bg', CLR_BG_DARKGRAY);?>;
    color: <?php uout('cd_color', CLR_DARKGRAY);?>;
}
.ui-widget-content a {
    color: <?php uout('cd_color', CLR_DARKGRAY);?>;
}
.ui-heurist-bg-light{
    background-color: <?php uout('cl_bg', CLR_WHITE);?> !important;
    color: <?php uout('cd_color', CLR_DARKGRAY);?>;
}
/* BORDERS, HEADERS AND DIALOG TITLE */
.ui-dialog {
    border: 2px solid <?php uout('cd_border', CLR_BORDER);?> !important;
}
/* .ui-heurist-header1 - rare use @todo remove */
.ui-dialog .ui-dialog-buttonpane, .ui-heurist-header1, optgroup {
    background-color: <?php uout('cd_border', CLR_BORDER);?> !important;
}
.ui-dialog-titlebar, .ui-progressbar-value {
    background: none;
    background-color: <?php uout('cd_border', CLR_BORDER);?> !important;
}
.ui-menu, .ui-heurist-border{
    border: 1px solid <?php uout('cd_border', CLR_BORDER);?>;
}
.ui-menu-divider {
    border-top: 1px solid <?php uout('cd_border', CLR_BORDER);?> !important;
}
.svs-acordeon, .svs-acordeon-group{
    border-bottom: <?php uout('cd_border', CLR_BORDER);?> 1px solid;
}
.svs-header{
    color: <?php uout('cd_border', CLR_BORDER);?>;
}
/* ALTERNATIVE SCHEME (HEURIST HEADER) */
select.ui-heurist-header2, input.ui-heurist-header2{
    background-color:<?php uout('ca_input', '#536077');?> !important;
}

.ui-heurist-header2, .ui-heurist-btn-header1 {
    background:<?php uout('ca_bg', '#364050');?> !important;
    color:<?php uout('ca_color', CLR_WHITE);?> !important;
}
.ui-heurist-btn-header1 {
    border: none !important;
}
.ui-heurist-header2 a{
    color:<?php uout('ca_color', CLR_WHITE);?> !important;
}
/* color for submenus */
.ui-heurist-header2 .ui-menu .ui-menu a {
    color: <?php uout('cd_color', CLR_DARKGRAY);?> !important;
}

/* EDITOR CONTENT */
<?php if(@$_REQUEST['ll']=='H5Default'){ ?>
.recordEditor, .ent_wrapper.editor{
    background-color:<?php uout('ce_bg', '#ECF1FB');?> !important;
}
<?php }  ?>

.ui-selectmenu-button{
    background:<?php uout('ce_input', CLR_WHITE);?>;
    border: 1px solid  <?php uout('cd_bg', CLR_BG_DARKGRAY );?> !important;
    outline: none;
    padding: 1px;
}
.ent_wrapper.editor{
    font-size:0.9em;
}
.ent_wrapper.editor .header, .header, .header_narrow, .header>label, .header_narrow>label, label.inside_header{
    color: <?php uout('ce_color', '#6A7C99');?>;
}
.ent_wrapper.editor .text{
    background: none repeat scroll 0 0 <?php uout('ce_input', CLR_WHITE);?>;/* 0511 !important */
    border: 1px solid  <?php uout('cd_bg', CLR_BG_DARKGRAY );?>;
}
.separator2{
    color: black; /* <?php uout('ce_helper', CLR_LIGHTGRAY);?>;*/
}
.ent_wrapper.editor .separator{
    color: <?php uout('ce_helper', CLR_LIGHTGRAY);?>;
    border-top: 1px solid <?php uout('cd_border', CLR_BORDER);?>;
}
.ent_wrapper.editor .smallbutton{
    color:<?php uout('cd_color', CLR_DARKGRAY);?>;
}
.ent_wrapper.editor .heurist-helper1, .prompt{
    color: <?php uout('ce_helper', CLR_LIGHTGRAY);?>;
}
.mandatory > label, .required, .required > label{
    color: <?php uout('ce_mandatory', '#CC0000');?>;
}
.readonly, .graytext, .smallicon{
    color: <?php uout('ce_readonly', CLR_LIGHTGRAY);?>;
}

/* Corner radius */
.ui-corner-all,
.ui-corner-top,
.ui-corner-left,
.ui-corner-tl {
    border-top-left-radius: <?php uout('cd_corner', '0');?>px;
}
.ui-corner-all,
.ui-corner-top,
.ui-corner-right,
.ui-corner-tr {
    border-top-right-radius: <?php uout('cd_corner', '0');?>px;
}
.ui-corner-all,
.ui-corner-bottom,
.ui-corner-left,
.ui-corner-bl {
    border-bottom-left-radius: <?php uout('cd_corner', '0');?>px;
}
.ui-corner-all,
.ui-corner-bottom,
.ui-corner-right,
.ui-corner-br {
    border-bottom-right-radius: <?php uout('cd_corner', '0');?>px;
}


/* CLICKABLE: DEFAULT */
.ui-state-default,
.ui-widget-content .ui-state-default,
.ui-widget-header .ui-state-default,
.ui-button,
/* We use html here because we need a greater specificity to make sure disabled
works properly when clicked or hovered */
html .ui-button.ui-state-disabled:hover,
html .ui-button.ui-state-disabled:active {
    /*heurist*/
    border: 1px solid <?php uout('sd_bg', CLR_SD_BG);?>;
    background: <?php uout('sd_bg', CLR_SD_BG);?>;
    font-weight: normal;
    color: <?php uout('sd_color', CLR_GRAY);?>;
}
.ui-state-default a,
.ui-state-default a:link,
.ui-state-default a:visited,
a.ui-button,
a:link.ui-button,
a:visited.ui-button,
.ui-button {
    color: <?php uout('sd_color', CLR_GRAY);?>;
    text-decoration: none;
}

/*  CLICKABLE: HOVER AND FOCUS */
.ui-button:hover,
.ui-button:focus {
    border: 1px solid <?php uout('sh_border', CLR_LIGHTGRAY);?>;/*for buttons change border only*/
}
.ui-state-hover,
.ui-widget-content .ui-state-hover,
.ui-widget-header .ui-state-hover,
.ui-state-focus,
.ui-widget-content .ui-state-focus,
.ui-widget-header .ui-state-focus{
    border: 1px solid <?php uout('sh_border', CLR_LIGHTGRAY);?>;
    background: <?php uout('sh_bg', CLR_BORDER);?>;
    font-weight: normal;
    color: <?php uout('sh_color', CLR_HOVER_COLOR);?>;
}
.ui-state-hover a,
.ui-state-hover a:hover,
.ui-state-hover a:link,
.ui-state-hover a:visited,
.ui-state-focus a,
.ui-state-focus a:hover,
.ui-state-focus a:link,
.ui-state-focus a:visited,
a.ui-button:hover,
a.ui-button:focus {
    color: <?php uout('sh_color', CLR_HOVER_COLOR);?>;
    text-decoration: none;
}

.ui-visual-focus {
    box-shadow: 0 0 3px 1px rgb(94, 158, 214);
}

/*  CLICKABLE: ACTIVE */
.ui-state-active,
.ui-widget-content .ui-state-active,
.ui-widget-header .ui-state-active{
    border: 1px solid <?php uout('sa_border', '#aaaaaa');?>;
    background: <?php uout('sa_bg', CLR_BORDER);?>;
    color: <?php uout('sa_color', CLR_ACTIVE);?>;
    font-weight: normal;
}
/*  CLICKABLE: PRESED */
a.ui-button:active,
.ui-button:active,
.ui-button.ui-state-active:hover {
    background: <?php uout('sp_bg', '#9CC4D9');?>;
    border: 1px solid <?php uout('sp_border', CLR_SP_BORDER);?>;
    color: <?php uout('sp_color', CLR_WHITE);?>;
    font-weight: normal;
}
.ui-icon-background,
.ui-state-active .ui-icon-background {
    border: <?php uout('sp_border', CLR_SP_BORDER);?>;
    background-color: <?php uout('sp_color', CLR_WHITE);?>;
}
.ui-state-active a,
.ui-state-active a:link,
.ui-state-active a:visited {
    color: <?php uout('sa_color', CLR_ACTIVE);?>;
    text-decoration: none;
}
.fancytree-active, .fancytree-node:hover{
    background: <?php uout('sa_bg', CLR_BORDER);?>;/* !important */
    color: <?php uout('sp_color', CLR_WHITE);?>;
}
/*
.fancytree-active > .fancytree-title{
    background: <?php uout('sa_bg', CLR_BORDER);?> !important;
}
span.fancytree-node.fancytree-active{
    background: <?php uout('sa_bg', CLR_BORDER);?> !important;
    color: <?php uout('sp_color', CLR_WHITE);?> !important;
}
span.fancytree-node:hover{
    background: <?php uout('sa_bg', CLR_BORDER);?>;
    color: <?php uout('sp_color', CLR_WHITE);?> !important;
}
*/
/* --------------------------------------------------------------------------- */
<?php if(@$_REQUEST['ll']!='H5Default'){ ?>
/* H6 SPECIFIC */
.ui-button-action{
    background:<?php uout('button_action_bg', '#3D9946');?> 0% 0% no-repeat padding-box !important;
    color:<?php uout('button_action_bg', '#FFFFFF');?> !important;
}
.ui-dialog-heurist .ui-dialog-titlebar{
    background: none;
    border: none;
    padding: 10px;
    color: <?php uout('publish_title_color', '#FFFFFF');?>;
}
.ui-dialog-heurist{
    border: 0.25px solid #707070 !important;
    box-shadow: 2px 3px 10px #00000080 !important;
    border-radius: 4px !important;
    padding: 0;
}
.ui-dialog-heurist .ui-dialog-title{
    font-size: 1.3em;
    margin: 0;
}

/* SECTION SCHEME: ERROR */
.ui-heurist-error.ui-heurist-header, .ui-heurist-error .ui-heurist-header,
.ui-heurist-error .ui-dialog-titlebar,
.ui-heurist-error .ui-dialog-buttonpane
{
    background:<?php uout('design_bg', '#F7CAC9');?>  !important;
    color: white;
}
.ui-menu6 .ui-menu6-container.ui-heurist-error, .ui-heurist-error .ui-helper-popup{
    border-width: 2px !important;
    border-color:<?php uout('design_bg', '#F7CAC9');?> !important;
}
.ui-heurist-error .ui-heurist-title{color:<?php uout('design_title_color', '#EB7C79');?>}

.ui-heurist-error .ui-widget-content,
.ui-heurist-error .ui-dialog-heurist{
    background:<?php uout('design_fade_bg', '#F9F1F0');?>
}

.ui-heurist-error .ui-heurist-button,
.ui-heurist-error .ui-state-active,
.ui-heurist-error .fancytree-active,
.ui-heurist-error .fancytree-node:hover
{
        background:<?php uout('design_active', '#F1A3A1');?> !important;
}
.ui-heurist-error .edit-form-tabs li.ui-tabs-active,
.ui-heurist-error-fade{background:<?php uout('design_fade_bg', '#F9F1F0');?> !important;}

/* SECTION SCHEME: DESIGN */
.ui-heurist-design.ui-heurist-header, .ui-heurist-design .ui-heurist-header,
.ui-heurist-design .ui-dialog-titlebar,
.ui-heurist-design .ui-dialog-buttonpane
{
    background:<?php uout('design_bg', '#523365');?>  !important;
    color: white;
}
.ui-menu6 .ui-menu6-container.ui-heurist-design, .ui-heurist-design .ui-helper-popup{
    border-width: 2px !important;
    border-color:<?php uout('design_bg', '#523365');?> !important;
}
.ui-heurist-design .ui-heurist-title{color:<?php uout('design_title_color', '#7B4C98');?>}

.ui-heurist-design .ui-widget-content,
.ui-heurist-design .ui-dialog-heurist{
    background:<?php uout('design_fade_bg', CLR_DESIGN_BG);?>
}
/*
.ui-heurist-design .ui-heurist-header .ui-button-icon-only,
.ui-heurist-design .ui-dialog-titlebar .ui-button-icon-only,
.ui-heurist-design .ui-fade-color{
    color:<?php uout('design_fade_bg', CLR_DESIGN_BG);?> !important;
}
*/

.ui-heurist-design .ui-heurist-button,
.ui-heurist-design .ui-state-active,
.ui-heurist-design .fancytree-active,
.ui-heurist-design .fancytree-node:hover
{
        background:<?php uout('design_active', '#A487B9');?> !important;
}
.ui-heurist-design .edit-form-tabs li.ui-tabs-active,
.ui-heurist-design-fade{background:<?php uout('design_fade_bg', CLR_DESIGN_BG);?> !important;}

/* SECTION SCHEME: EXPLORE */
.ui-heurist-explore.ui-heurist-header, .ui-heurist-explore .ui-heurist-header,
.ui-heurist-explore .ui-dialog-titlebar,
.ui-heurist-explore .ui-dialog-buttonpane
{
    background-color: <?php uout('explore_bg', '#305586');?> !important;
    color: white;
}
.ui-heurist-explore-fade{background:<?php uout('explore_fade_bg', CLR_EXPLORE_FADE_BG);?> !important;}
.ui-heurist-explore .ui-heurist-title{color:<?php uout('explore_title_color', CLR_EXPLORE_BG);?>}
.ui-heurist-explore .ui-widget-content{
    background:<?php uout('explore_fade_bg', CLR_EXPLORE_FADE_BG);?>
}
/* button within menu section */
.ui-heurist-explore .ui-heurist-btn-header1{
    background:none !important;
    border:1px solid <?php uout('explore_bg', CLR_EXPLORE_BG)?> !important;
    color:<?php uout('explore_bg', CLR_EXPLORE_BG)?> !important;
}
.ui-heurist-explore .ui-button-icon-only,
.ui-heurist-explore .ui-main-color
{
    background: none;
    color:<?php uout('explore_bg', CLR_EXPLORE_BG)?> !important;
}
.ui-heurist-explore .ui-state-active,
.ui-heurist-explore .fancytree-active,
.ui-heurist-explore .fancytree-node:hover
{
    margin: 0;
    background:<?php uout('explore_active', '#AFBFDA');?> !important;
}
.ui-heurist-explore .ui-tabs-tab.ui-state-active{
    background:<?php uout('explore_fade_bg', CLR_EXPLORE_FADE_BG);?> !important;
}
.ui-heurist-explore .ui-heurist-button.ui-state-active{
    background:<?php uout('explore_bg', CLR_EXPLORE_BG)?> !important;
    color: #ffffff;
}

/*
.ui-heurist-explore .fancytree-node:hover{
    border: 1px dotted blue !important;
}
*/
/* SECTION SCHEME: POPULATE */
.ui-heurist-populate.ui-heurist-header, .ui-heurist-populate .ui-heurist-header,
.ui-heurist-populate .ui-dialog-titlebar,
.ui-heurist-populate .ui-dialog-buttonpane
{
    background:<?php uout('import_bg', CLR_IMPORT_BG);?> !important;
    color: white;
}
.ui-heurist-populate .edit-form-tabs li.ui-tabs-active,
.ui-heurist-populate-fade{background:<?php uout('import_fade_bg', CLR_IMPORT_FADE_BG);?> !important;}

.ui-heurist-populate .ui-heurist-title{color:<?php uout('import_title_color', CLR_IMPORT_BG);?>}
.ui-heurist-populate .ui-widget-content{
    background:<?php uout('import_fade_bg', CLR_IMPORT_FADE_BG);?>
}

/* button within menu section */
.ui-heurist-populate .ui-heurist-btn-header1{
    background:none !important;
    border:1px solid <?php uout('import_bg', CLR_IMPORT_BG)?> !important;
    color:<?php uout('import_bg', CLR_IMPORT_BG)?> !important;
}
.ui-heurist-populate .ui-button-icon-only{
    background: none;
    color:<?php uout('import_bg', CLR_IMPORT_BG)?> !important;
}
.ui-heurist-populate .ui-heurist-header .ui-button-icon-only,
.ui-heurist-populate .ui-dialog-titlebar .ui-button-icon-only,
.ui-heurist-populate .ui-fade-color{
    color:<?php uout('import_fade_bg', CLR_IMPORT_FADE_BG)?> !important;
}

.ui-menu6 .ui-menu6-container.ui-heurist-populate, .ui-heurist-populate .ui-helper-popup{
    border-width: 2px !important;
    border-color:<?php uout('import_bg', CLR_IMPORT_BG);?> !important;
}
.ui-heurist-populate .ui-state-active,
.ui-heurist-populate .fancytree-active,
.ui-heurist-populate .fancytree-node:hover
{
        background:<?php uout('import_active', '#86CDE8');?> !important;
}

/* SECTION SCHEME: PUBLISH */
.ui-heurist-publish.ui-heurist-header, .ui-heurist-publish .ui-heurist-header,
.ui-heurist-publish .ui-dialog-titlebar,
.ui-heurist-publish .ui-dialog-buttonpane{
    background:<?php uout('publish_bg', CLR_PUBLISH_BG);?> !important;
    color: white;
}
.ui-heurist-publish-fade{background:<?php uout('publish_fade_bg', CLR_PUBLISH_FASE_BG);?> !important;}
.ui-heurist-publish .ui-heurist-title{color:<?php uout('publish_title_color', CLR_PUBLISH_BG);?>}
.ui-heurist-publish .ui-widget-content{
    background:<?php uout('publish_fade_bg', CLR_PUBLISH_FASE_BG);?>
}
/* button within menu section */
.ui-heurist-publish .ui-heurist-btn-header1{
    background:none !important;
    border:1px solid <?php uout('import_bg', CLR_IMPORT_BG)?> !important;
    color:<?php uout('publish_bg', CLR_PUBLISH_BG)?> !important;
}
.ui-heurist-publish .ui-button-icon-only{
    background: none;
    color:<?php uout('publish_bg', CLR_PUBLISH_BG)?> !important;
}
.ui-heurist-publish .ui-heurist-header .ui-button-icon-only,
.ui-heurist-publish .ui-dialog-titlebar .ui-button-icon-only,
.ui-heurist-publish  .ui-fade-color{
    color:<?php uout('publish_fade_bg', CLR_PUBLISH_FASE_BG)?> !important;
}

.ui-menu6 .ui-menu6-container.ui-heurist-publish, .ui-heurist-publish .ui-helper-popup{
    border-width: 2px !important;
    border-color:<?php uout('publish_bg', CLR_PUBLISH_BG);?> !important;
}

.ui-heurist-publish .ui-state-active,
.ui-heurist-publish .fancytree-active,
.ui-heurist-publish .fancytree-node:hover
{
    background:<?php uout('publish_active', '#CCEBC5');?>;/*  !important */
}

/* SECTION SCHEME: ADMIN */

.ui-heurist-admin.ui-heurist-header, .ui-heurist-admin .ui-heurist-header,
.ui-heurist-admin .ui-dialog-titlebar,
.ui-heurist-admin .ui-dialog-buttonpane
{
    background:<?php uout('admin_bg', CLR_ADMIN_BG);?> !important;
    color: white;
}
.ui-heurist-admin-fade{background:<?php uout('admin_fade_bg', CLR_EXPLORE_FADE_BG);?> !important;}
.ui-heurist-admin .ui-heurist-title{color:<?php uout('admin_title_color', CLR_ADMIN_BG);?>}
.ui-heurist-admin .ui-widget-content{
    background:<?php uout('admin_fade_bg', CLR_EXPLORE_FADE_BG);?>
}

/* button within menu section */
.ui-heurist-admin .ui-heurist-btn-header1{
    background:none !important;
    border:1px solid <?php uout('admin_bg', CLR_ADMIN_BG)?> !important;
    color:<?php uout('admin_bg', CLR_ADMIN_BG)?> !important;
}
.ui-heurist-admin .ui-button-icon-only{
    background: none;
    color:<?php uout('admin_bg', CLR_ADMIN_BG)?> !important;
}
.ui-heurist-admin .ui-heurist-header .ui-button-icon-only, .ui-heurist-admin .ui-dialog-titlebar .ui-button-icon-only{
    color:<?php uout('admin_fade_bg', CLR_EXPLORE_FADE_BG)?> !important;
}
.ui-menu6 .ui-menu6-container.ui-heurist-admin, .ui-heurist-admin .ui-helper-popup{
    border-width: 2px !important;
    border-color:<?php uout('admin_bg', CLR_ADMIN_BG);?> !important;
}

<?php } ?>

.ui-widget-no-background, .ui-accordion-header, .ui-accordion-header.ui-accordion-header-active{
    background:none !important;
    background-color: none !important;
    border: none !important;
}

.borderless{
    border: none !important;
}
