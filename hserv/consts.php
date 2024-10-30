<?php
use \hserv\utilities\USystem;

/**
* List of system constants
*
* Many of them are defined with values set in congigIni.php
* (and respectively in ../heuristConfigIni.php)
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

// TODO: Rationalise the duplication of constants across /php/consts.php and /common/connect/initialise.php
//       in particualr this duplication of HEURIST_MIN_DB_VERSION and any other explicit constants

define('HEURIST_VERSION', $version);//code version is defined congigIni.php
define('HEURIST_MIN_DBVERSION', "1.3.16");//minimal version of db for current version of code

// The reference server is the location of the Heurist Reference Index database (HEURIST_INDEX_DATABASE), the Heurist_Help database,

// curated template databases and also code updates
if(!@$heuristReferenceServer){
    $heuristReferenceServer = 'https://heuristref.net';//default value
    //$heuristReferenceServer = 'https://HeuristRef.Net';
}

define('HEURIST_DEF_DIR', '/heurist/'); //default Heurist folder
define('HEURIST_MAIN_SERVER', $heuristReferenceServer);
define('HEURIST_INDEX_BASE_URL', $heuristReferenceServer.HEURIST_DEF_DIR);//central index and template databases url
define('HEURIST_INDEX_DBREC', '1-22');//concept code for record type "Registered Database" in Heurist Reference Index (HEURIST_INDEX_DATABASE)

define('HEURIST_INDEX_DATABASE', 'Heurist_Reference_Index');
define('HEURIST_BUGREPORT_DATABASE', 'Heurist_Job_Tracker');
define('HEURIST_HELP', $heuristReferenceServer.HEURIST_DEF_DIR.'help');

if (@$httpProxy != '') {
    define('HEURIST_HTTP_PROXY_ALWAYS_ACTIVE', (isset($httpProxyAlwaysActive) && $httpProxyAlwaysActive===true));//always use proxy for CURL
    define('HEURIST_HTTP_PROXY', $httpProxy);//http address:port for proxy request
    if (@$httpProxyAuth != '') {
        define('HEURIST_HTTP_PROXY_AUTH', $httpProxyAuth);// "username:password" for proxy authorization
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
define('HEURIST_SERVER_NAME', @$host_params['server_name']);// server host name for the configured name, eg. myheurist.net

if(@$_SERVER["REQUEST_URI"]) {define('HEURIST_CURRENT_URL', $host_params['server_url'] . $_SERVER["REQUEST_URI"]);}//NOT USED
if(!defined('HEURIST_DIR')){
  define('HEURIST_DIR',
    (@$host_params['heurist_dir']? $host_params['heurist_dir'] :@$_SERVER["DOCUMENT_ROOT"])
    . $host_params['install_dir']);//  eg. /var/www/html/HEURIST @todo - read simlink (realpath)
}

define('HEURIST_BASE_URL', $host_params['server_url'] . $host_params['install_dir']  );// eg. https://myheurist.net/heurist/

define('HEURIST_BASE_URL_PRO', $host_params['server_url'] . $host_params['install_dir_pro'] );// production url eg. https://myheurist.net/heurist/

define('HEURIST_SCRATCHSPACE_DIR', sys_get_temp_dir());

if ($dbHost) {
    define('HEURIST_DBSERVER_NAME', $dbHost);
} else {
    define('HEURIST_DBSERVER_NAME', "localhost");//configure to access mysql on the same machine as the Heurist codebase
}
//to use native mysqldump you have to specify file with mysql credentials
//mysql_config_editor set --login-path=local --host=localhost --user={usename} --password

//0: use 3d party PDO mysqldump (default), 1:use internal routine, 2 - call mysql via shell

//path to mysqldump - it is required if $dbDumpMode==2
if(isset($dbMySQLDump) && file_exists($dbMySQLDump)){
    define('HEURIST_DB_MYSQLDUMP', $dbMySQLDump);
    $dbDumpMode = isset($dbDumpMode)?$dbDumpMode:2;
}else{
    $dbDumpMode = 0;
}
define('HEURIST_DB_MYSQL_DUMP_MODE', $dbDumpMode);

if(isset($dbMySQLpath) && file_exists($dbMySQLpath)){
    define('HEURIST_DB_MYSQLPATH', $dbMySQLpath);
    $dbScriptMode = isset($dbScriptMode)?$dbScriptMode:2;
}else{
    $dbScriptMode = 0;
}
define('HEURIST_DB_MYSQL_SCRIPT_MODE', $dbScriptMode);

define('ADMIN_DBUSERNAME', $dbAdminUsername);//user with all rights so we can create databases, etc.
define('ADMIN_DBUSERPSWD', $dbAdminPassword);
define('HEURIST_DB_PREFIX', $dbPrefix);
define('HEURIST_DB_PORT', $dbPort);

//---------------------------------
$date = new DateTime();

define('HEURIST_TITLE', 'Heurist V'.HEURIST_VERSION);

/**
* Response status for ajax requests. See ResponseStatus in hapi.js
*/
define("HEURIST_INVALID_REQUEST", "invalid");// 400 The Request provided was invalid.
define("HEURIST_NOT_FOUND", "notfound");// 404 The requested object not found.
define("HEURIST_ERROR", "error");// 500 General error: wrong data, file i/o
define("HEURIST_OK", "ok");// 200 The response contains a valid Result.
define("HEURIST_REQUEST_DENIED", "denied");// 403 Not enough rights (logout/in to refresh) or action
define("HEURIST_ACTION_BLOCKED", "blocked");// 409 The request could not be completed due to a conflict with the current state of the target resource. This code is used in situations where the user might be able to resolve the conflict and resubmit the request.
define("HEURIST_UNKNOWN_ERROR", "unknown");// 500 A request could not be processed due to a server error. The request may succeed if you try again.
define("HEURIST_DB_ERROR", "database");// 500 A request could not be processed due to a server database error. Most probably this is BUG. Contact developers
define("HEURIST_SYSTEM_CONFIG", "syscfg");// 500 System not-fatal configuration error. Contact system admin
define("HEURIST_SYSTEM_FATAL", "system");// 500 System fatal configuration error. Contact system admin
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
define('CONTACT_HEURIST_TEAM_PLEASE', ' Please '.CONTACT_HEURIST_TEAM);
define('CONTACT_SYSADMIN', 'contact your <a href=mailto:'.HEURIST_MAIL_TO_ADMIN.'>system administrator</a> ');

define('CRITICAL_DB_ERROR_CONTACT_SYSADMIN',
    'It is also possible that drive space has been exhausted. '
            .'<br><br>Please contact the system administrator (email: ' . HEURIST_MAIL_TO_ADMIN . ') for assistance.'
            .'<br><br>This error has been emailed to the Heurist team (for servers maintained by the project or those on which this function has been enabled).'
            .'<br><br>We apologise for any inconvenience');

define('CONTACT_SYSADMIN_ABOUT_PERMISSIONS',
        'Please ask your system administrator to correct the path and/or permissions for this directory');

//
define('WEBSITE_THUMBNAIL_SERVICE', $websiteThumbnailService);

//Expose all relationship vocabularies as options for term fields.
define("HEURIST_UNITED_TERMS", true);


//common constants
define('NAKALA_REPO', 'http'.'://nakala.fr/'); //split to avoid sonarcloud security hotspot
define('DATE_8601', 'Y-m-d H:i:s');
define('REGEX_YEARONLY', '/^-?\d+$/');
define('REGEX_ALPHANUM', '/[^a-zA-Z0-9_]/');
define('REGEX_EOL', '/[\r\n]/');

define('XML_HEADER', '<?xml version="1.0" encoding="UTF-8"?>');
define('CTYPE_JSON', 'Content-type: application/json;charset=UTF-8');
define('CTYPE_HTML', 'Content-type: text/html;charset=UTF-8');
define('CTYPE_JS', 'Content-type: text/javascript');
define('CONTENT_LENGTH', 'Content-Length: ');
define('HEADER_CORS_POLICY', 'Access-Control-Allow-Origin: *');

//common separators
define('TABLE_S','<table>');
define('TR_S','<tr><td>');
define('TD','</td><td>');
define('TD_E','</td>');
define('TR_E','</td></tr>');
define('TABLE_E','</table>');
define('DIV_S','<div>');
define('DIV_E','</div>');
define('BR','<br>');
define('BR2','<br><br>');

//common sql reserved words
define('SQL_AND',' AND ');
define('SQL_NOT',' NOT ');
define('SQL_WHERE',' WHERE ');
define('SQL_NULL', 'NULL');
define('SQL_DELETE', 'DELETE FROM ');
define('SQL_IN',' IN (');
define('SQL_FALSE','(1=0)');
define('SQL_BETWEEN',' BETWEEN ');

define('MT_VIMEO','video/vimeo');
define('MT_YOUTUBE','video/youtube');
define('MT_SOUNDCLOUD','audio/soundcloud');

//
define('HTTP_SCHEMA','http://');
define('HTTPS_SCHEMA','https://');
define('XML_SCHEMA','http://www.w3.org/2001/XMLSchema#string');
define('TEMP_MEMORY', 'php://temp/maxmemory:1048576');

global $glb_lang_codes;
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

//special media types
define('ULF_REMOTE','_remote');
define('ULF_IIIF','_iiif');
define('ULF_IIIF_IMAGE','_iiif_image');
define('ULF_TILED_IMAGE','_tiled');

//default system folders
define('DIR_IMAGE','image/');
define('DIR_SCRATCH','scratch/');
define('DIR_BACKUP','backup/');
define('DIR_THUMBS','thumbs/');
define('DIR_ENTITY','entity/');


define('ICON_PLACEHOLDER', HEURIST_BASE_URL.'hclient/assets/16x16.gif');
define('ICON_EXTLINK', HEURIST_BASE_URL.'hclient/assets/external_link_16x16.gif');

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
    'DT_MINIMUM_ZOOM_LEVEL' => array(2, 32), //in basemap zoom levels (0-20)
    'DT_MAXIMUM_ZOOM_LEVEL' => array(2, 33),
    // zoom in km used for map documents (map zoom ranges) and layers (visibility range)
    //note that minimum in km turns to maximum in native zoom
    'DT_MAXIMUM_ZOOM' => array(3, 1085), //in UI this field acts as minimum zoom in km
    'DT_MINIMUM_ZOOM' => array(3, 1086), //in UI this field acts as maximum zoom out km
    'DT_LEGEND_OUT_ZOOM' => array(3, 1087), //hide or disable layer in legend if layer is out of zoom range
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

    'DT_EXTERNAL_ID' => array(2, 581), //external non heurist record id
    // Spatial & mapping
    'DT_KML_FILE' => array(3, 1044),
    'DT_KML' => array(3, 1036), //snippet
    'DT_MAP_IMAGE_LAYER_REFERENCE' => array(3, 1043),
    'DT_MAP_IMAGE_WORLDFILE' => array(3, 1095),
    'DT_ALTERNATE_NAME' => array(3, 1009),
    'DT_TIMELINE_FIELDS' => array(2, 1105),
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
    'DT_CMS_MENU_FORMAT' => array(2, 1104), //show name + icon, name only, or icon only

    'DT_WORKFLOW_STAGE' => array(2, 1080)

);//TODO: add email magic numbers


$trmDefines = array(
    'TRM_PAGETYPE_WEBPAGE' => array(2, 6254),
    'TRM_PAGETYPE_MENUITEM' => array(2, 6253),
    'TRM_NO' => array(2, 531),
    'TRM_NO_OLD' => array(99, 5447),
    'TRM_SWF' => array(2, 9453), //workflow stages vocabulary
    'TRM_SWF_ADDED' => array(2, 9464), //01 - Editing (includes manually created)
    'TRM_SWF_IMPORT' => array(2, 9454), //00 - Imported

    // For DT_CMS_MENU_FORMAT
    'TRM_NAME_ONLY' => array(2, 9634),
    'TRM_ICON_ONLY' => array(2, 9635),
    'TRM_NAME_AND_ICON' => array(2, 9636),

    'TRM_LEGEND_OUT_ZOOM_HIDDEN' => array(3, 5081),
    'TRM_LEGEND_OUT_ZOOM_DISABLED' => array(3, 5082)
);


//---------------------------------

function boot_error_handler($errno, $errstr, $errfile, $errline){
    if($errno==E_WARNING){ //E_PARSE E_NOTICE
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
    }
}

//
// Common functions
//
function error_WrongParam($param){
    return $param.' parameter is not defined or wrong';
}

function error_Div($text){
    return '<div class="error" style="color:red">'.$text.DIV_E;
}


function redirectURL($url){
    header('Location: '.$url);
}

function isEmptyStr($val){
    // !empty is analogous to isset($foo) && $foo
    return empty($val) || $val=='';
}

function isEmptyArray($val){
    return !is_array($val) || empty($val);
}

/**
 * Searches for a value in a two-dimensional array by a specific key.
 *
 * @param array $arr The array to search in (2D array).
 * @param string $key The key to search for within the nested arrays.
 * @param mixed $keyvalue The value to match against.
 * @return int|null Returns the index of the found item, or null if not found.
 */
function findInArray(array $arr, string $key, $keyvalue): ?int {
    foreach ($arr as $idx => $item) {
        if (is_array($item) && array_key_exists($key, $item) && $item[$key] === $keyvalue) {
            return $idx;
        }
    }
    return null;
}

function isPositiveInt($val){
    //return isset($val) && is_numeric($val) && $val>0;
    return isset($val) && (is_int($val) || ctype_digit($val)) && (int)$val>0;
}

function isLocalHost(){
    return $_SERVER["SERVER_NAME"]=='localhost' || $_SERVER["SERVER_NAME"]=='127.0.0.1';
}


function dataOutput($data, $filename=null, $mimeType=null)
{
    if($mimeType==null){
        $mimeType = 'application/json';
    }
    if($mimeType=='application/json' && is_array($data)){
        $data = json_encode($data);
    }

    header('Content-type: '.$mimeType.';charset=UTF-8');

    if($filename){ //browser downloads it as file
        header('Content-Disposition: attachment; filename="' . $filename . '";');
        header("Pragma: no-cache;");
        header('Expires: ' . gmdate("D, d M Y H:i:s", time() - 3600));
    }

    $len = strlen($data);
    if($len>0){header('Content-Length: '. $len);}

    if($mimeType=='application/json'){
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; frame-ancestors \'self\'');
    }

    echo $data;
}

function includeJQuery(){

   $useVersion3 =  false;

   if ($useVersion3) {
       // or 1.13.2/jquery-ui.js
       // or ui/1.12.1/themes/base/jquery-ui.css
       // integrity has been got with https://www.srihash.org/
?>
        <script src="https://code.jquery.com/jquery-3.7.1.js" integrity="sha384-wsqsSADZR1YRBEZ4/kKHNSmU+aX8ojbnKUMN4RyD3jDkxw5mHtoe2z/T/n4l56U/" crossorigin="anonymous"></script>
        <script src="https://code.jquery.com/jquery-migrate-3.5.2.js" integrity="sha384-v0gmY8lRWAAaI20hT2ehyGAhsZiQpB+ZMpRHg/ipfVinhY4zxJXPjV8zaVW3kq4W" crossorigin="anonymous"></script>
        <script src="https://code.jquery.com/ui/1.14.0/jquery-ui.js" integrity="sha384-/L7+EN15GOciWSd0nb17+43i1HKOo5t8SFtgDKGqRJ2REbp8N6fwVumuBezFc4qC" crossorigin="anonymous"></script>
        <link rel="stylesheet" type="text/css" href="https://code.jquery.com/ui/1.14.0/themes/base/jquery-ui.css">

        <!-- Calendar picker -->
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.calendars-2.1.1/js/jquery.plugin.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.calendars-2.1.1/js/jquery.calendars.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.calendars-2.1.1/js/jquery.calendars.plus.js"></script>

        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery.calendars-2.1.1/css/jquery.calendars.picker-1.2.1.css">
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.calendars-2.1.1/js/jquery.calendars.picker.js"></script>

        <script src="<?php echo PDIR;?>external/jquery.calendars-2.1.1/js/jquery.calendars.taiwan.js"></script>
        <script src="<?php echo PDIR;?>external/jquery.calendars-2.1.1/js/jquery.calendars.thai.js"></script>
        <script src="<?php echo PDIR;?>external/jquery.calendars-2.1.1/js/jquery.calendars.julian.js"></script>
        <script src="<?php echo PDIR;?>external/jquery.calendars-2.1.1/js/jquery.calendars.persian.js"></script>
        <script src="<?php echo PDIR;?>external/jquery.calendars-2.1.1/js/jquery.calendars.islamic.js"></script>
        <script src="<?php echo PDIR;?>external/jquery.calendars-2.1.1/js/jquery.calendars.ummalqura.js"></script>
        <script src="<?php echo PDIR;?>external/jquery.calendars-2.1.1/js/jquery.calendars.hebrew.js"></script>
        <script src="<?php echo PDIR;?>external/jquery.calendars-2.1.1/js/jquery.calendars.ethiopian.js"></script>
        <script src="<?php echo PDIR;?>external/jquery.calendars-2.1.1/js/jquery.calendars.coptic.js"></script>
        <script src="<?php echo PDIR;?>external/jquery.calendars-2.1.1/js/jquery.calendars.nepali.js"></script>
        <script src="<?php echo PDIR;?>external/jquery.calendars-2.1.1/js/jquery.calendars.mayan.js"></script>
        <script src="<?php echo PDIR;?>hclient/core/jquery.calendars.japanese.js"></script>
<?php
   }else{


   if(isLocalHost()){
?>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery-ui-1.12.1/jquery-ui.js"></script>
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery-ui-themes-1.12.1/themes/base/jquery-ui.css"/>
<?php
   }else{
?>
        <script src="https://code.jquery.com/jquery-1.12.2.min.js" integrity="sha256-lZFHibXzMHo3GGeehn1hudTAP3Sc0uKXBXAzHX1sjtk=" crossorigin="anonymous"></script>
        <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js" integrity="sha256-VazP97ZCwtekAsvgPBSUwPFKdrwD3unUfSGVYrahUqU=" crossorigin="anonymous"></script>
        <link rel="stylesheet" type="text/css" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">

<?php
   }
?>
        <!-- Calendar picker -->
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.calendars-1.2.1/jquery.calendars.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.calendars-1.2.1/jquery.calendars.plus.js"></script>

        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery.calendars-1.2.1/jquery.calendars.picker.css">
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.calendars-1.2.1/jquery.calendars.picker.js"></script>

        <script src="<?php echo PDIR;?>external/jquery.calendars-1.2.1/jquery.calendars.taiwan.js"></script>
        <script src="<?php echo PDIR;?>external/jquery.calendars-1.2.1/jquery.calendars.thai.js"></script>
        <script src="<?php echo PDIR;?>external/jquery.calendars-1.2.1/jquery.calendars.julian.js"></script>
        <script src="<?php echo PDIR;?>external/jquery.calendars-1.2.1/jquery.calendars.persian.js"></script>
        <script src="<?php echo PDIR;?>external/jquery.calendars-1.2.1/jquery.calendars.islamic.js"></script>
        <script src="<?php echo PDIR;?>external/jquery.calendars-1.2.1/jquery.calendars.ummalqura.js"></script>
        <script src="<?php echo PDIR;?>external/jquery.calendars-1.2.1/jquery.calendars.hebrew.js"></script>
        <script src="<?php echo PDIR;?>external/jquery.calendars-1.2.1/jquery.calendars.ethiopian.js"></script>
        <script src="<?php echo PDIR;?>external/jquery.calendars-1.2.1/jquery.calendars.coptic.js"></script>
        <script src="<?php echo PDIR;?>external/jquery.calendars-1.2.1/jquery.calendars.nepali.js"></script>
        <script src="<?php echo PDIR;?>external/jquery.calendars-1.2.1/jquery.calendars.mayan.js"></script>
        <script src="<?php echo PDIR;?>hclient/core/jquery.calendars.japanese.js"></script>
<?php
   }
?>
   <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.fancytree/2.38.3/jquery.fancytree-all.js" integrity="sha384-BSBg3ImWc3aK3fo7lX3qP5Ben/mH1jIVv4MJPkG7txP2Qg+kmn7l5u6XWDCxrrYK" crossorigin="anonymous"></script>
   <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery.widgets/jquery.fancytree/skin-themeroller/ui.fancytree.css" />
<?php
}
?>
