<?php
use hserv\structure\ConceptCode;

    /**
    *  Website generator based on CMS records 99-51,52,53
    *
    *  It is either generate home page from cmsTemplate file (inits main menu, header, footer)
    *  or returns content for particular page
    *
    *  Parameters
    *  recID - home page record (99-51) or web page (99-53)
    *          if is is not defined it takes first record of type 'Home page'
    *
    * if home page has defined as template file it is loaded as body, otherwise default template
    * that includes header with main-logo, main-title, main-menu and
    * main-content where content of particular page will be loaded
    *
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

//Documentation
/* Documentation

INITIALIZATION workflow:
1. websiteRecord.php - load parameters from CMS record including name of template (by default cmsTemplate.php)
2. in template it has to include websiteScriptAndStyles.php with list of all heurist scripts and styles
3. in websiteScriptAndStyles.php
    On client side
    a. HAPI initialization, DB defintions load -> onHapiInit -> onPageInit
    b. onPageInit: init LayoutMgr, init main menu in #main-menu element
    c. loadPageContent(pageid): Loads content of page into #main-content and
       calls widget initialization width LayoutMgr.appInitFromContainer
    d. If database configuration permits only:
       After widgets initialization it loads javascript (field 2-927) and incapsulate
       this code into afterPageLoad function. The purpose of this script is additional
       configuration of widgets on page (that cannot be set via cms editor) - mainly
       addition of event listeners.


Default layout for Heurist CMS web site consists of 3 divs with absolute positions
.ent_wrapper.heurist-website
    .ent_header   #main_header
    .ent_content_full  #main-content-container

Main setting for these elements is height of header. To change it set
.ent_header{height:180px} .ent_content_full{top:190px}

HEADER:

#main_header.ent_header is hardcoded in cmsTemplate.php. It has the following elements
    #main-logo      - content defined via field "Site logo" (99-51.2-38). On click it reloads main page
    #main-logo-alt  - content defined via field "Supplementary logo image" (99-51.2-926)
    #main-title>h2  - field "Website title" (99-51.2-1)
    #main-host      - information about host and heurist. Content defined in Heurist settings
    #main-menu      - generated based on linked Menu/Page records (99-52)
    #main-pagetitle>.webpageheading - loaded Page title "Menu label" (99-52.2-1) hidden if 99-952
    #main-languages - link to swtich current language (list of languages is defined in field 99-51.2-967

If your write your own cms template you have to define only 2 mandatory elements
#main-menu and #main-content


You may overwrite default styles for these elements in field "Website CSS" (99-51.99-46).
Background image for #main_header is defiend in field "Banner image" (99-51.99-951).

CONTENT:
#main-content-container.ent_content_full cosists of sole element #main-content

    This element is emptied and reloaded for every page of website. Its content is
    arbitrary and defined via CMS editor or directly via record editor in field
    "Website home page content"/"HTML content". (2-4)

    After load, Heurist invokes
    window.hWin.HAPI4.LayoutMgr.appInitFromContainer( document, "#main-content" )
    This method replaces all div elements with attribute "data-heurist-app-id" to
    the appropriate Heurist widgets (search, map, result list etc)

    There are 2 fields per menu/page record target css and target element. They are reserved
    for future use. At the moment page content is always loaded into #main-content and applied
    general Heurist color scheme unless the style is overdefined for particular
    widget.

#main-recordview - container to load content of particular heurist record. Usually this is smarty report.
    Alternatively record view can be in popup or in new browser tab. The target is defined in field 99-51.2-949

Content of website can be defined as custom smarty template in field 99-51.2-922.
In this case website designer has to define at least one element with id #main-content.
Element with this name will be used as layout container for widget initialization.
All other elements (#main-xxx) are optional.

*/

if(!defined('PDIR')) {
    define('PDIR','../../../');//need for proper path to js and css
}

require_once dirname(__FILE__).'/../../framecontent/initPageMin.php';//without client hapi
require_once dirname(__FILE__).'/../../../hserv/records/search/recordSearch.php';
require_once dirname(__FILE__).'/../../../hserv/structure/dbsUsersGroups.php';


/*
Workflow:
loads main page for logo, icon, banner, style


*/
$edit_OldEditor = (@$_REQUEST['edit']==1);

$system->defineConstants();

$mysqli = $system->getMysqli();

$isEmptyHomePage = false;
$open_page_or_record_on_init = 0;
if(isPositiveInt(@$_REQUEST['initid'])) {
    $open_page_or_record_on_init = intval(@$_REQUEST['initid']);
}elseif(isPositiveInt(@$_REQUEST['pageid'])) {
    $open_page_or_record_on_init = intval(@$_REQUEST['pageid']);
}

$rec_id = 0;  //home record id
if(isPositiveInt(@$_REQUEST['recID'])) {
    $rec_id = intval(@$_REQUEST['recID']);
}elseif(isPositiveInt(@$_REQUEST['recid'])) {
    $rec_id = intval(@$_REQUEST['recid']);
}elseif(isPositiveInt(@$_REQUEST['id'])) {
    $rec_id = intval(@$_REQUEST['id']);
}elseif(isPositiveInt(@$_REQUEST['website'])) {
    $rec_id = intval(@$_REQUEST['website']);
}

//find default website
$res = recordSearch($system, array('q'=>array('t'=>RT_CMS_HOME), 'detail'=>'ids'));
$def_rec_id = 0;
if(@$res['status']==HEURIST_OK){
    $def_rec_id = @$res['data']['records'][0];
}

if(!isPositiveInt($rec_id)){
    // if recID is not defined - use fist available "CMS home" record
    $rec_id = $def_rec_id;
    if(!isPositiveInt($rec_id)){
        $try_login = $system->getCurrentUser() == null;
        $message = 'Sorry, there are no publicly accessible websites defined for this database. '
        .'Please ' . ($try_login ? '<a class="login-link">login</a> or' : '') . ' ask the owner to publish their website(s).';

        include_once ERROR_REDIR;
        exit;
    }
}

// check if this record has been replaced (merged)
$rec_id = recordSearchReplacement($mysqli, $rec_id, 0);

$rec = recordSearchByID($system, $rec_id, true);

$home_page_on_init = $rec_id;

if($rec==null){

    $message = 'Website ID '.$home_page_on_init.' does not refer to a CMS Home record';

    include_once ERROR_REDIR;
    exit;
}

$hasAccess = (($rec['rec_NonOwnerVisibility'] == 'public') ||
               $system->isAdmin() ||
    ( ($system->getUserId()>0) &&
            ($rec['rec_NonOwnerVisibility'] !== 'hidden' ||    //visible for logged
             $system->isMember($rec['rec_OwnerUGrpID']) )) );//owner

if(!$hasAccess){

    $try_login = $system->getCurrentUser() == null;

    $message = 'The Heurist website at this address is not yet publicly accessible. '
        . ($try_login ? '<br>Try <a class="login-link">logging in</a> to view this website.' : '');

    include_once ERROR_REDIR;
    exit;
}

$showWarnAboutPublic = !$edit_OldEditor && ($rec['rec_NonOwnerVisibility'] != 'public');

$hasAccess = ($system->isAdmin() || $system->isMember($rec['rec_OwnerUGrpID']));

$site_owner = user_getDbOwner($mysqli);//info about user #2

$website_title = '';
$show_pagetitle = false;
$isWebPage = false;

$TRM_NO = ConceptCode::getTermLocalID('2-531');//TRM_NO
$TRM_NO_OLD = ConceptCode::getTermLocalID('99-5447');//TRM_NO_OLD


$isWebPage = ($rec['rec_RecTypeID']==RT_CMS_MENU &&
            defined('DT_CMS_PAGETYPE') &&
            __getValue($rec, DT_CMS_PAGETYPE)==ConceptCode::getTermLocalID('2-6254'));//TRM_PAGETYPE_WEBPAGE

if(!$isWebPage){ //for standalone webpage always without title
    $show_pagetitle = __getValue($rec, DT_CMS_PAGETITLE);
    $show_pagetitle = ($show_pagetitle!== $TRM_NO && //TRM_NO
                       $show_pagetitle!== $TRM_NO_OLD);//TRM_NO_OLD
}


//-----------------------

if(!($rec['rec_RecTypeID']==RT_CMS_HOME || $isWebPage)){
    $message = 'Record #'.$rec_id.' is not allowed record type. '
                    .'Expecting Website Home Page or Standalone Web Page';
    include_once ERROR_REDIR;
    exit;
}

$image_icon = __getFile($rec, DT_THUMBNAIL, (array_key_exists('embed', $_REQUEST)?PDIR:HEURIST_BASE_URL).'favicon.ico');
$image_logo = __getFile($rec, DT_FILE_RESOURCE, null);

$image_altlogo = __getFile($rec, '2-926', null);//DT_CMS_ALTLOGO
$image_altlogo_url = __getValue($rec, '2-943');//DT_CMS_ALTLOGO_URL
$title_alt = __getValue($rec, '3-1009');//DT_CMS_ALT_TITLE
$title_alt2 = __getValue($rec, '2-1052');
$image_banner = __getFile($rec, '99-951', null);//DT_CMS_BANNER

$image_logo = $image_logo?'<img style="max-width:270px;" src="'.$image_logo.'">':'';

$meta_keywords = htmlspecialchars(strip_tags(__getValue($rec, DT_CMS_KEYWORDS)));
$meta_description = htmlspecialchars(strip_tags(__getValue($rec, DT_SHORT_SUMMARY)));

$website_language_def = '';
$website_languages_links = '';
$website_languages = null;
if(defined('DT_LANGUAGES')){
    $website_languages = @$rec['details'][DT_LANGUAGES];
    if(!isEmptyArray($website_languages)){
        $orig_arr = print_r($website_languages,true);
        $website_languages_codes = getTermCodes($mysqli, $website_languages);
        $res = '';
        $website_languages_res = array();//defined codes

        foreach($website_languages as $term_id){
            $lang_code = @$website_languages_codes[$term_id];

            if($lang_code){
                $lang_code = strtoupper($lang_code);
                if($website_language_def=='') {$website_language_def = $lang_code;}
                $res = $res.'<a href="#" data-lang="'.$lang_code.'" onclick="switchLanguage(event)">'.$lang_code.'</a><br>';
                array_push($website_languages_res, $lang_code);
            }
        }
        $website_languages_links = count($website_languages_res)>1?$res:'';
        $website_languages = $website_languages_res;
    }
}

$current_language = @$_REQUEST['lang'];
if(!$current_language) {$current_language = $website_language_def;}

if(!empty($website_languages_links)){
    $curr_lang_text = 'data-lang="'.$website_language_def.'"';
    $website_languages_links = str_replace($curr_lang_text, $curr_lang_text . ' class="lang-selected"', $website_languages_links);
}

$website_title = __getValueAsJSON($rec, DT_NAME);//multilang titles
$website_title_translated = getCurrentTranslation(@$rec['details'][DT_NAME], $current_language);//default title


$show_login_button = true; // by default, show login button
if(!$isWebPage){
    $show_login_button = __getValue($rec, '2-1095');

    $show_login_button = empty($show_login_button) ||
                            ((($show_login_button == $TRM_NO) ||
                          ($show_login_button == $TRM_NO_OLD)) ? false : true);
}

//2-532 - YES   2-531 - NO

if(!$isWebPage && __getValue($rec,DT_EXTENDED_DESCRIPTION)==''){
    //home page is empty
    $isEmptyHomePage = true;
}

//
// arbitrary external links and scripts
//
$external_files = null;
$website_custom_css = null;
$website_custom_javascript = null;
$website_custom_javascript_allowed = $system->settings->isJavaScriptAllowed();
if($website_custom_javascript_allowed)
{
    if($system->defineConstant('DT_CMS_EXTFILES')){
        $external_files = @$rec['details'][DT_CMS_EXTFILES];
        if($external_files!=null && !is_array($external_files)){
                $external_files = array($external_files);
        }
    }
    $website_custom_javascript = __getValue($rec, DT_CMS_SCRIPT);
}
//custom styles - mainly to override positions/visibility for #main-xxx elements
$website_custom_css = __getValue($rec, DT_CMS_CSS);

//color scheme for website
if($system->defineConstant('DT_SYMBOLOGY')){
    $site_colors = __getValue($rec, DT_SYMBOLOGY);
}else{
    $site_colors = '';
}

//
// returns link to uploaded file
//
function __getFile(&$rec, $id, $def){

    if(is_string($id) && strpos($id,'-')){
        $id = ConceptCode::getDetailTypeLocalID($id);
    }

    $file = @$rec['details'][$id];

    if(is_array($file)){
        $file = array_shift($file);
        $file = (array_key_exists('embed', $_REQUEST)?PDIR:HEURIST_BASE_URL).'?db='.HEURIST_DBNAME.'&file='.$file['fileid'];
    }else{
        $file = $def;
    }

    return $file;
}

//
// returns first value for given field
//
function __getValue(&$page_record, $id){

    if(is_string($id) && strpos($id,'-')){
        $id = ConceptCode::getDetailTypeLocalID($id);
    }

    $val = @$page_record['details'][$id];

    if(is_array($val)){
        return array_shift($val);
    }elseif($val==null){
        return '';
    }else{
        return $val;
    }
}

//
// returns all values as JSON array
//
function __getValueAsJSON(&$page_record, $id){

    if(is_string($id) && strpos($id,'-')){
        $id = ConceptCode::getDetailTypeLocalID($id);
    }

    $val = @$page_record['details'][$id];

    if(!is_array($val)){
        $val = array($val);
    }
    return json_encode($val);
}


$custom_website_php_template = null;
$record_view_smarty_template = null;
$record_view_target = null; //blank(_blank),popup,recordview(main-recordview)

$page_header = null;
$page_header_menu = null;

$page_footer = null;
$is_page_footer_fixed = true;
$page_footer_height = 0;

if(!$isWebPage){  //not standalone web page

    $page_header_menu =
            '<div class="widget-design-header" style="padding: 10px;"><img style="vertical-align: middle;" height="22" />'
            .'<strong>navigation</strong><a class="edit" style="padding: 0 10px;" title="Click to edit" href="#">edit</a> '
            .' <a class="remove" href="#">remove</a> height:50px width:100%</div>'
            .'<span class="widget-options" style="font-style: italic; display: none;">{"menu_recIDs":"'.$rec_id
            .'","use_next_level":true,"orientation":"horizontal","init_at_once":true}</span>';

    $custom_website_php_template = defined('DT_CMS_TEMPLATE')?__getValue($rec, DT_CMS_TEMPLATE):null;

    $record_view_smarty_template = defined('DT_SMARTY_TEMPLATE')?__getValue($rec, DT_SMARTY_TEMPLATE):null;
    $record_view_target = defined('DT_CMS_TARGET')?__getValue($rec, DT_CMS_TARGET):null;
    if($record_view_target=='recordview') {$record_view_target='main-recordview';} //blank(_blank),popup,recordview(main-recordview)

    //backward capability
    if($custom_website_php_template==null && strpos($record_view_smarty_template, 'cmsTemplate')===0){
        $custom_website_php_template = $record_view_smarty_template;
        $record_view_smarty_template = null;
    }

    $page_header = defined('DT_CMS_HEADER')?__getValue($rec, DT_CMS_HEADER):null;

    $page_footer_type = defined('DT_CMS_FOOTER_FIXED')?__getValue($rec, DT_CMS_FOOTER_FIXED):null;

    if($page_footer_type !== ConceptCode::getTermLocalID('3-5029'))    //unknown position
    //== ConceptCode::getTermLocalID('2-532') || $page_footer_type == ConceptCode::getTermLocalID('2-531'))
    {

        $page_footers = null;
        if(defined('DT_CMS_FOOTER')){
            $page_footers = @$rec['details'][DT_CMS_FOOTER];
        }

        $is_page_footer_fixed = ($page_footer_type != ConceptCode::getTermLocalID('2-531'));
        $default_style = ";border-top:2px solid rgb(112,146,190);background:lightgray;";
        if ($is_page_footer_fixed) {
            $footer_height = ($page_footers!=null) ? '80px' : '48px';
            $page_footer_style = 'height:'.$footer_height.$default_style;
        } else {
            $footer_height = 'auto';
            $page_footer_style = 'height:'.$footer_height.(($page_footers!=null) ? '' : $default_style);
        }

        $mailto = '';
        if(@$site_owner['ugr_eMail']){
            $mailto = '<div>&bull;&nbsp;&nbsp;<a href="mailto:'.@$site_owner['ugr_eMail'].'" target="_blank">site owner</a></div>';
        }
        $favicon_url = HEURIST_BASE_URL.'favicon.ico';

        // CSS in h4styles.css
        $host_information = <<<EXP
        <div id="main-host" style="min-height:48px;">
            <div>
                &nbsp;&nbsp;<a href="#" onclick="performCaptcha();">report site</a>
            </div>
            $mailto
            <div>
                <a href="https://HeuristNetwork.org" target="_blank" style="text-decoration:none;"
                title="This website is generated by Heurist, an academic knowledge management system developed at the University of Sydney Faculty of Arts and Social Sciences under the direction of Dr Ian Johnson, chief programmer Artem Osmakov.">
                powered by &nbsp;&nbsp;<img src="$favicon_url" style="vertical-align:sub"> Heurist
                </a>
            </div>
                <div id="host_info"></div>
        </div>
        EXP;

        $page_footer = '<footer id="page-footer" class="'.($is_page_footer_fixed?'ent_footer':'static_footer').'"'
                .' style="'.$page_footer_style.'">';
        if($page_footers!=null){
        foreach($page_footers as $val){
                list($lang,$val) = extractLangPrefix($val);

                $st = (count($page_footers)==1 || ($current_language == $lang)
                        ||($current_language==$website_language_def && $lang==null))?'':' style="display:none"';
                $page_footer = $page_footer.
                        '<div class="page-footer-content" data-lang="'.($lang!=null?$lang:'').'"'.$st.'>'
                            .$val.DIV_E;
        }
        }
        $page_footer = $page_footer.$host_information.'</footer>';

    }
}

$home_page_record_id = $rec_id;

//$default_CMS_Template_Path

$websiteScriptAndStyles_php = HEURIST_DIR.'hclient/widgets/cms/websiteScriptAndStyles.php';

$template = getCmsTemplateFullPath($custom_website_php_template); //defined CMS Home in field DT_CMS_TEMPLATE
if(!$template && $default_CMS_Template){  //defined in heuristConfigIni
    $template = getCmsTemplateFullPath($default_CMS_Template);
}

if($template!==false){
    //use custom template for website
    include_once $template;
}else{
    //use default template in this folder
    $template = HEURIST_DIR.'hclient/widgets/cms/cmsTemplate.php';
    if(!file_exists($template)){
            $message = 'Sorry, it is not possible to load default cms template. '
            .'Please ask the owner to verify server configuration.';

            include_once ERROR_REDIR;
            exit;
    }else{
        include_once 'cmsTemplate.php';
    }
}

//
// retuns path to cms template
//
function getCmsTemplateFullPath($template){

    if($template){
        if(substr( $template, -4 ) !== '.php'){
                $template = $template.'.php';
        }
        $template = basename($template);
        if($template!='cmsTemplate.php'){
            $template = HEURIST_DIR.'hclient/widgets/cms/templates/'.$template;
            if(!file_exists($template)) {return false;}
        }
        return $template;
    }
    return false;
}
?>

