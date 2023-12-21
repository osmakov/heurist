<?php

/**
* List of system constants
*
* Many of them are defined with values set in congigIni.php 
* (and respectively in ../heuristConfigIni.php)
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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

// TODO: Rationalise the duplication of constants across /php/consts.php and /common/connect/initialise.php
//       in particualr this duplication of HEURIST_MIN_DB_VERSION and any other explicit constants
require_once dirname(__FILE__).'/utilities/uSystem.php';

define('HEURIST_VERSION', $version);  //code version is defined congigIni.php
define('HEURIST_MIN_DBVERSION', "1.3.14"); //minimal version of db for current version of code

// The reference server is the location of the Heurist Reference Index database (HEURIST_INDEX_DATABASE), the Heurist_Help database, 

// curated template databases and also code updates
if(!@$heuristReferenceServer){
    $heuristReferenceServer = 'https://heuristref.net';  //default value
    //$heuristReferenceServer = 'https://HeuristRef.Net';
}

define('HEURIST_MAIN_SERVER', $heuristReferenceServer);
define('HEURIST_INDEX_BASE_URL', $heuristReferenceServer.'/heurist/'); //central index and template databases url
define('HEURIST_INDEX_DBREC', '1-22'); //concept code for record type "Registered Database" in Heurist Reference Index (HEURIST_INDEX_DATABASE)

define('HEURIST_INDEX_DATABASE', 'Heurist_Reference_Index');
define('HEURIST_HELP', $heuristReferenceServer.'/heurist/help');

if (@$httpProxy != '') {
    define('HEURIST_HTTP_PROXY_ALWAYS_ACTIVE', (isset($httpProxyAlwaysActive) && $httpProxyAlwaysActive===true)); //always use proxy for CURL
    define('HEURIST_HTTP_PROXY', $httpProxy); //http address:port for proxy request
    if (@$httpProxyAuth != '') {
        define('HEURIST_HTTP_PROXY_AUTH', $httpProxyAuth); // "username:password" for proxy authorization
    }
}

$host_params = USystem::getHostParams(@$argv);

define('HEURIST_DOMAIN', $host_params['domain']);

if (!@$mailDomain) {
    define('HEURIST_MAIL_DOMAIN', HEURIST_DOMAIN);
}else{
    define('HEURIST_MAIL_DOMAIN', $mailDomain);
}

define('HEURIST_SERVER_URL', $host_params['server_url']);
define('HEURIST_SERVER_NAME', @$host_params['server_name']); // server host name for the configured name, eg. myheurist.net

if(@$_SERVER["REQUEST_URI"]) define('HEURIST_CURRENT_URL', $host_params['server_url'] . $_SERVER["REQUEST_URI"]); //NOT USED
if(!defined('HEURIST_DIR')){
  define('HEURIST_DIR', 
    (@$host_params['heurist_dir']? $host_params['heurist_dir'] :@$_SERVER["DOCUMENT_ROOT"]) 
    . $host_params['install_dir']); //  eg. /var/www/html/HEURIST @todo - read simlink (realpath)  
} 

define('HEURIST_BASE_URL', $host_params['server_url'] . $host_params['install_dir']  ); // eg. https://myheurist.net/heurist/

define('HEURIST_BASE_URL_PRO', $host_params['server_url'] . $host_params['install_dir_pro'] ); // production url eg. https://myheurist.net/heurist/

define('HEURIST_SCRATCHSPACE_DIR', sys_get_temp_dir());

if ($dbHost) {
    define('HEURIST_DBSERVER_NAME', $dbHost);
} else {
    define('HEURIST_DBSERVER_NAME', "localhost"); //configure to access mysql on the same machine as the Heurist codebase
}
//to use native mysqldump you have to specify file with mysql credentials
//mysql_config_editor set --login-path=local --host=localhost --user={usename} --password

//0: use 3d party PDO mysqldump (default), 1:use internal routine, 2 - call mysql via shell
define('HEURIST_DB_MYSQL_SCRIPT_MODE', isset($dbScriptMode)?$dbScriptMode:0);
define('HEURIST_DB_MYSQL_DUMP_MODE', isset($dbDumpMode)?$dbDumpMode:0);

//path to mysqldump - it is required if $dbDumpMode==2
if(isset($dbMySQLDump)){
    define('HEURIST_DB_MYSQLDUMP', $dbMySQLDump);  
}
if(isset($dbMySQLpath)){
    define('HEURIST_DB_MYSQLPATH', $dbMySQLpath);  
}



/*  @todo - redirect to system config error page

if (!($dbAdminUsername && $dbAdminPassword)) { //if these are not specified then we can't do anything
returnErrorMsgPage(1, "MySql user account/password not specified. Set in configIni.php");
}
if(preg_match('/[^a-z_\-0-9]/i', $dbAdminPassword)){
//die("MySql user password contains non valid charactes. Only alphanumeric allowed. Set in configIni.php");
returnErrorMsgPage(1, "MySql user password may not contain special characters. To avoid problems down the line they are restricted to alphanumeric only. Set in configIni.php");
}
*/
define('ADMIN_DBUSERNAME', $dbAdminUsername); //user with all rights so we can create databases, etc.
define('ADMIN_DBUSERPSWD', $dbAdminPassword);
define('HEURIST_DB_PREFIX', $dbPrefix);
define('HEURIST_DB_PORT', $dbPort);

//---------------------------------
$date = new DateTime();
//define('HEURIST_TITLE', 'Heurist Academic Knowledge Management System - &copy; 2005-2023 The University of Sydney.');
define('HEURIST_TITLE', 'Heurist V'.HEURIST_VERSION); //.' '.$date->format('d M Y @ H:i') );

/**
* Response status for ajax requests. See ResponseStatus in hapi.js
*/
define("HEURIST_INVALID_REQUEST", "invalid");    // 400 The Request provided was invalid.
define("HEURIST_NOT_FOUND", "notfound");         // 404 The requested object not found.
define("HEURIST_ERROR", "error");                // 500 General error: wrong data, file i/o
define("HEURIST_OK", "ok");                      // 200 The response contains a valid Result.
define("HEURIST_REQUEST_DENIED", "denied");      // 403 Not enough rights (logout/in to refresh) or action
define("HEURIST_ACTION_BLOCKED", "blocked");     // 409 The request could not be completed due to a conflict with the current state of the target resource. This code is used in situations where the user might be able to resolve the conflict and resubmit the request.
define("HEURIST_UNKNOWN_ERROR", "unknown");      // 500 A request could not be processed due to a server error. The request may succeed if you try again.
define("HEURIST_DB_ERROR", "database");          // 500 A request could not be processed due to a server database error. Most probably this is BUG. Contact developers
define("HEURIST_SYSTEM_CONFIG", "syscfg");       // 500 System not-fatal configuration error. Contact system admin
define("HEURIST_SYSTEM_FATAL", "system");        // 500 System fatal configuration error. Contact system admin
/*
$usrTags = array(
"rty_ID"=>"i",
"rty_Name"=>"s",
"rty_OrderInGroup"=>"i",
"rty_Description"=>"s",
"rty_TitleMask"=>"s",
"rty_CanonicalTitleMask"=>"s",
"rty_Plural"=>"s",
"rty_Status"=>"s",
"rty_OriginatingDBID"=>"i",
"rty_NameInOriginatingDB"=>"s",
"rty_IDInOriginatingDB"=>"i",
"rty_NonOwnerVisibility"=>"s",
"rty_ShowInLists"=>"i",
"rty_RecTypeGroupID"=>"i",
"rty_RecTypeModelsIDs"=>"s",
"rty_FlagAsFieldset"=>"i",
"rty_ReferenceURL"=>"s",
"rty_AlternativeRecEditor"=>"s",
"rty_Type"=>"s",
"rty_ShowURLOnEditForm" =>"i",
"rty_ShowDescriptionOnEditForm" =>"i",
"rty_Modified"=>"i",
"rty_LocallyModified"=>"i"
);
*/

//---------------------------------
// set up email defines
//
define('HEURIST_MAIL_TO_BUG', $bugEmail?$bugEmail:'info@HeuristNetwork.org');
define('HEURIST_MAIL_TO_INFO', $infoEmail?$infoEmail:'info@HeuristNetwork.org');
define('HEURIST_MAIL_TO_ADMIN', $sysAdminEmail?$sysAdminEmail:HEURIST_MAIL_TO_INFO);

define('CONTACT_HEURIST_TEAM', 'contact <a href=mailto:'.HEURIST_MAIL_TO_INFO.'>Heurist team</a> ');
define('CONTACT_SYSADMIN', 'contact your <a href=mailto:'.HEURIST_MAIL_TO_ADMIN.'>system administrator</a> ');

//
define('WEBSITE_THUMBNAIL_SERVICE', $websiteThumbnailService);

//Expose all relationship vocabularies as options for term fields.
define("HEURIST_UNITED_TERMS", true);


$glb_lang_codes = null;

//common languages for translation database definitions (ISO639-2 codes)
if(!isset($common_languages_for_translation)){
    $common_languages_for_translation = array('ENG','FRE','CHI','SPA','ARA','GER','POR','LAT','GRE','GRC');    
}

//---------------------------------
// used in Uploadhandler.php
define('HEURIST_ALLOWED_EXT', 
'jpg,jpe,jpeg,jfif,sid,png,gif,tif,tiff,bmp,rgb,doc,docx,odt,mp3,mp4,mpg,mpeg,mov,avi,wmv,wmz,aif,aiff,ashx,pdf,mbtiles,'
.'mid,midi,wms,wmd,qt,evo,cda,wav,csv,tsv,tab,txt,rtf,xml,xsl,xslx,xslt,xls,xlsx,hml,kml,kmz,shp,dbf,shx,svg,htm,html,xhtml,'
.'ppt,pptx,zip,gzip,tar,json,ecw,nxs,nxz,obj,mtl,3ds,stl,ply,gltf,glb,off,3dm,fbx,dae,wrl,3mf,ifc,brep,step,iges,fcstd,bim');
 

/** RECORD TYPE DEFINITIONS */
$rtDefines = array(
    // Standard core record types (HeuristCoreDefinitions: DB = 2)
    'RT_RELATION' => array(2, 1),
    'RT_INTERNET_BOOKMARK' => array(2, 2),
    'RT_NOTE' => array(2, 3),
    'RT_ORGANIZATION' => array(2, 4),
    'RT_MEDIA_RECORD' => array(2, 5),
    'RT_AGGREGATION' => array(2, 6),
    'RT_COLLECTION' => array(2, 6), // duplicate naming
    'RT_BLOG_ENTRY' => array(2, 7),
    'RT_INTERPRETATION' => array(2, 8),
    'RT_PERSON' => array(2, 10),

    // Record types added by SW and SH for their extensions, no longer in core definitions, now in DB 4 Heurist ToolExtensions
    'RT_FILTER' => array(2, 12),
    'RT_XML_DOCUMENT' => array(2, 13),
    'RT_TRANSFORM' => array(2, 14),
    'RT_ANNOTATION' => array(2, 15),
    'RT_LAYOUT' => array(2, 16),
    'RT_PIPELINE' => array(2, 17),
    'RT_TOOL' => array(2, 19),

    // Cleaned up bibliographic record types
    'RT_BOOK' => array(3, 102),
    'RT_CONFERENCE' => array(3, 103),
    'RT_PUB_SERIES' => array(3, 104),
    'RT_BOOK_CHAPTER' => array(3, 108),
    'RT_JOURNAL' => array(3, 111),
    'RT_JOURNAL_ARTICLE' => array(3, 112),
    'RT_JOURNAL_VOLUME' => array(3, 113),
    'RT_MAP' => array(3, 115),
    'RT_OTHER_DOC' => array(3, 117),
    'RT_REPORT' => array(3, 119),
    'RT_THESIS' => array(3, 120),
    'RT_PERSONAL_COMMUNICATION' => array(3, 121),
    'RT_ARTWORK' => array(3, 122),
    'RT_MAGAZINE_ARTICLE' => array(3, 123),
    'RT_MAGAZINE' => array(3, 124),
    'RT_MAGAZINE_VOLUME' => array(3, 125),
    'RT_NEWSPAPER' => array(3, 126),
    'RT_NEWSPAPER_VOLUME' => array(3, 127),
    'RT_NEWSPAPER_ARTICLE' => array(3, 128),
    'RT_PHOTOGRAPH' => array(3, 129),
    'RT_ARCHIVAL_RECORD' => array(3, 1000),
    'RT_ARCHIVAL_SERIES' => array(3, 1001),
    
    // Spatial data
    'RT_PLACE' => array(3, 1009), 
    'RT_EN_PLACE' => array(1125, 25), //place for Expert Nation database
    'RT_MAP_ANNOTATION' => array(2, 101),
    'RT_MAP_DOCUMENT' => array(3, 1019), // HeuristReferenceSet DB 3: Map document, layers and queries for new map function Oct 2014
    'RT_MAP_LAYER' => array(3, 1020),     

    'RT_KML_SOURCE' => array(3, 1014),
    'RT_FILE_SOURCE' => array(2, 53), //csv tsv or dbf source
    'RT_SHP_SOURCE' => array(3, 1017),
    'RT_QUERY_SOURCE' => array(3, 1021),  //RT_MAPABLE_QUERY
    'RT_TLCMAP_DATASET' => array(1271, 54),     

    'RT_IMAGE_SOURCE' => array(3, 1018),
    'RT_TILED_IMAGE_SOURCE' => array(2, 11), // added Ian 23/10/14 for consistency
    'RT_GEOTIFF_SOURCE' => array(3, 1018),

    //Web content
    'RT_WEB_CONTENT' => array(1147, 25),
    
    'RT_CMS_HOME' => array(99, 51),  
    'RT_CMS_MENU' => array(99, 52)
);

/** DETAIL TYPE DEFINITIONS */
$dtDefines = array('DT_NAME' => array(2, 1),
    'DT_SHORT_NAME' => array(2, 2),
    'DT_SHORT_SUMMARY' => array(2, 3),
    'DT_EXTENDED_DESCRIPTION' => array(2, 4),
    'DT_TARGET_RESOURCE' => array(2, 5),
    'DT_RELATION_TYPE' => array(2, 6),
    'DT_PRIMARY_RESOURCE' => array(2, 7),
    'DT_INTERPRETATION_REFERENCE' => array(2, 8),
    'DT_DATE' => array(2, 9),
    'DT_START_DATE' => array(2, 10),
    'DT_END_DATE' => array(2, 11),
    'DT_QUERY_STRING' => array(2, 12),
    'DT_RESOURCE' => array(2, 13),
    'DT_CREATOR' => array(2, 15),
    'DT_CONTACT_INFO' => array(2, 17),
    'DT_GIVEN_NAMES' => array(2, 18),
    'DT_GENDER' => array(2, 20),
    'DT_EMAIL' => array(2, 23),
    'DT_LOCATION' => array(2, 27), // TODO : change DT_PLACE_NAME with new update.
    'DT_GEO_OBJECT' => array(2, 28),
    'DT_MIME_TYPE' => array(2, 29),
    'DT_IMAGE_TYPE' => array(2, 30),
    'DT_MAP_IMAGE_LAYER_SCHEMA' => array(2, 31),
    'DT_MINIMUM_ZOOM_LEVEL' => array(2, 32), //in basemap zoom levels (0-19)
    'DT_MAXIMUM_ZOOM_LEVEL' => array(2, 33),
    // zoom in km used for map documents (map zoom ranges) and layers (visibility range)
    //note that minimum in km turns to maximum in native zoom  
    'DT_MINIMUM_ZOOM' => array(3, 1086), //in UI this field acts as maximum zoom in km
    'DT_MAXIMUM_ZOOM' => array(3, 1085), //in UI this field acts as minimum zoom in km  
    'DT_IS_VISIBLE' => array(2, 1100),   //is layer initially visible on mapdocument initialization
    
    'DT_SERVICE_URL' => array(2, 34),
    'DT_URL' => array(3, 1058),
    'DT_ORIGINAL_RECORD_ID' => array(2, 36),
    'DT_FILE_RESOURCE' => array(2, 38),
    'DT_THUMBNAIL' => array(2, 39),
    'DT_ANNOTATION_INFO' => array(2, 1098), //for iiif and map annotations
    
    //xslt not used
    'DT_FILTER_STRING' => array(2, 40),
    'DT_FILE_TYPE' => array(2, 41),
    'DT_ANNOTATION_RESOURCE' => array(2, 42),
    'DT_ANNOTATION_RANGE' => array(2, 43),
    'DT_START_WORD' => array(2, 44),
    'DT_END_WORD' => array(2, 45),
    'DT_START_ELEMENT' => array(2, 46),
    'DT_END_ELEMENT' => array(2, 47),
    'DT_LAYOUT_STRING' => array(2, 48),    
    'DT_TRANSFORM_RESOURCE' => array(2, 50), 
    'DT_PROPERTY_VALUE' => array(2, 51),
    'DT_TOOL_TYPE' => array(2, 52),
    'DT_RECORD_TYPE' => array(2, 53),
    'DT_DETAIL_TYPE' => array(2, 54),
    'DT_COMMAND' => array(2, 55),
    'DT_COLOUR' => array(2, 56),
    'DT_DRAWING' => array(2, 59),
    'DT_COUNTER' => array(2, 60),
    'DT_MEDIA_RESOURCE' => array(2, 61), //refetence to media record
    //xslt not used
    'DT_FILE_NAME' => array(2, 62),
    'DT_FILE_FOLDER' => array(2, 63),
    'DT_FILE_EXT' => array(2, 64),
    'DT_FILE_DEVICE' => array(2, 65),
    'DT_FILE_DURATION' => array(2, 66),
    'DT_FILE_SIZE' => array(2, 67),
    'DT_FILE_MD5' => array(2, 68),
    'DT_PARENT_ENTITY' => array(2, 247),
    'DT_EDITOR' => array(3, 1013),
    'DT_OTHER_FILE' => array(3, 62), //TODO: remove from code
    'DT_LOGO_IMAGE' => array(3, 222), //TODO: remove from code
    'DT_IMAGES' => array(3, 224), //TODO: remove from code
    'DT_DOI' => array(3, 1003),
    'DT_WEBSITE_ICON' => array(3, 347), //TODO: remove from code
    'DT_ISBN' => array(3, 1011),
    'DT_ISSN' => array(3, 1032),
    'DT_JOURNAL_REFERENCE' => array(3, 1034),
    'DT_MEDIA_REFERENCE' => array(3, 508), //*******************ERROR  THIS IS MISSING
    'DT_TEI_DOCUMENT_REFERENCE' => array(3, 1045), //TODO : change DT_XML_DOCUMENT_REFERENCE with new update.
    'DT_ORDER' => array(1147, 94), //order of web content - origin Digital Harlem
    
    'DT_EXTERNAL_ID' => array(2, 581), //external non heurist record id
    // Spatial & mapping
    'DT_KML_FILE' => array(3, 1044),
    'DT_KML' => array(3, 1036), //snippet
    'DT_MAP_IMAGE_LAYER_REFERENCE' => array(3, 1043),
    'DT_MAP_IMAGE_WORLDFILE' => array(3, 1095),
    'DT_ALTERNATE_NAME' => array(3, 1009),
    // Map document
    'DT_MAP_LAYER' => array(3, 1081),
    'DT_MAP_BOOKMARK' => array(3, 1082),
    'DT_SYMBOLOGY_POINTMARKER' => array(3, 1091),  //outdated
    'DT_SYMBOLOGY' => array(3, 1092),  //MAIN field that stores ALL styles for map symbology (including thematic maps)
    'DT_ZOOM_KM_POINT' => array(2, 925), //area to zoom in on point selection (per map space document)
    'DT_SMARTY_TEMPLATE' => array(2, 922),  // smarty template to produce popup info per layer
    'DT_SYMBOLOGY_COLOR' => array(3, 1037), // outdated
    'DT_BG_COLOR' => array(2, 551),         // outdated
    'DT_OPACITY' => array(3, 1090),         // outdated
    'DT_ORDERING_HIERARCHY' => array(2, 1082), // field used to define drag-drop ordering of records
    'DT_DATA_SOURCE' => array(3, 1083),
    // Shape
    'DT_ZIP_FILE' => array(3, 1072),
    'DT_SHAPE_FILE' => array(3, 1069),
    'DT_DBF_FILE' => array(3, 1070),
    'DT_SHX_FILE' => array(3, 1071),
    
    'DT_CRS' => array(2, 1092), // Coordinate Reference System
    'DT_WORLD_BASEMAP' => array(2, 1093),  // Need to use trm_Label for terms to get basemap name

    'DT_EXTRACTED_TEXT' => array(2, 652),  //for pdf parser
    
    'DT_CMS_TOP_MENU' => array(99, 742),  //pointer  to top level menues in home page
    'DT_CMS_MENU' => array(99, 761),  //pointer to sub menu
    'DT_CMS_KEYWORDS' => array(99, 948),
    'DT_CMS_TEMPLATE' => array(2, 1099),
    'DT_CMS_TARGET' => array(99, 949),
    'DT_CMS_HEADER' => array(2, 929),
    'DT_CMS_CSS' => array(99, 946),
    'DT_CMS_PAGETITLE' => array(99, 952),   //show page title above content
    'DT_CMS_TOPMENUSELECTABLE' => array(2, 938), // allow top menu to be selectable, if a submenu is present
    //'DT_CMS_BANNER' => array(99, 951),
    //'DT_CMS_ALTLOGO' => array(2, 926),  
    //'DT_CMS_ALTLOGO_URL' => array(2, 943),  
    //'DT_CMS_ALT_TITLE' => array(3, 1009),
    'DT_CMS_SCRIPT' => array(2, 927),
    'DT_CMS_PAGETYPE' => array(2, 928), //menu (2-6253) or standalone (2-6254)
    'DT_CMS_EXTFILES' => array(2, 939), //external links and scripts
    'DT_CMS_FOOTER' => array(2, 940),
    'DT_CMS_FOOTER_FIXED' => array(2, 941),    //fixed 2-532
    'DT_LANGUAGES' => array(2, 967),
    
    'DT_WORKFLOW_STAGE' => array(2, 1080)

); //TODO: add email magic numbers


$trmDefines = array(
    'TRM_PAGETYPE_WEBPAGE' => array(2, 6254),
    'TRM_PAGETYPE_MENUITEM' => array(2, 6253),
    'TRM_NO' => array(2, 531),
    'TRM_NO_OLD' => array(99, 5447),
    'TRM_SWF' => array(2, 9453), //workflow stages vocabulary
    'TRM_SWF_ADDED' => array(2, 9464), //01 - Editing (includes manually created)
    'TRM_SWF_IMPORT' => array(2, 9454) //00 - Imported
);


//---------------------------------

function boot_error_handler($errno, $errstr, $errfile, $errline){
    switch($errno){
        case E_WARNING:
        //case E_PARSE:
        //case E_NOTICE:
            if(strpos($errstr,'Input variables')>0){
        
                $message = "$errstr $errfile:$errline";
                error_log('Large INPUT: '.htmlspecialchars($message));
                error_log(print_r(array_slice($_REQUEST, 0, 100),true));
                error_log(print_r($_SERVER, true));
            /*
            if(class_exists('Log')){
                Log::write($message, 'warning', true);
            }
            if(ENV != ENV_PROD){
                echo $message;
            }
            */
            }
            break;
    }
}
?>