<?php

/**
* Override configuration file for a Heurist installation - place in parent of the individual codebases,
* obviating the need to set these parameters for each version, avoiding redundancy and ensuring consistency
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2024 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

// See configIni.php for further documentation

// This file should be placed in the parent directory of Heurist, normally /var/www/html/HEURIST
// and renamed heuristConfigIni.php, the values in this file will then overide those given (if any)
// in the configIni.php file in each copy of Heurist on the server

// In practice the configIni.php file does NOT specify most of the values, since heuristConfigIni.php
// provides all copies with shared values.

// The installation script automatically moves this file along with other relevant files to /var/www/html/HEURIST

// **** DO NOT EDIT THE COPY OF THIS FILE IN THE CODEBASE (..../HEURIST/movetoparent)
//      as this will have no effect (it is the copy in ..../HEURIST/ which is used)

// [server url]                 
// enter the server name or IP address of your Web server, null will pull SERVER_NAME from the request header
// you may set this value if several domains point to your server. It will unify urls across links, web pages, reports
// for example $serverName = "heuristscholar.org";  Be sure to include the port if not port 80
if (!@$serverName) {$serverName = null;} // if not 'null', overrides default taken from request header SERVER_NAME
if (!@$mailDomain) {$mailDomain = null;} // set mail domain if it does not use server domain

// if base $heuristBaseURL is null, heurist detects it automatically 
// Although it may differ from desired url you wish to see (because web server settings: aliases, rewrite rules etc)
// Set this value explicitely to avoid possible issues
if (!@$heuristBaseURL) {$heuristBaseURL = null;}  // base url ( ie server url+optional folder https://heuristscholar.org/h6-alpha )  
// if you have several heurist instances of heurist, set this value to production instance
//
// if $heuristBaseURL is set and $heuristBaseURL_pro is null, then production version is the same as $heuristBaseURL
// if both $heuristBaseURL and $heuristBaseURL_pro are null, heurist detects it automatically, default folder for pro version is /heurist
if (!@$heuristBaseURL_pro) {$heuristBaseURL_pro = null;}

// [database]
// enter the host name or IP address of your MySQL server, blank --> localhost
// for example $dbHost = "heuristscholar.org";  will cause the code to use mysql on the server at heuristscholar.org
// Can be used to specify a separate database server in a tiered setup
if (!@$dbHost) {$dbHost= "";}// Optional, blank = localhost for single tier, or set IP of MySQL server

// MySQL user with full write (create) access on this database server
// The default installation of MySql gives you "root" as the master user with whatever password you set up for this,
// but you can specify another user and password with full access if preferred. We recommend "heurist". Password cannot be null.
// MySQL passwords may not contain special characters - if generating random password generate as alphanumeric
// Values can be assigned to environment variable or defined here
if (!@$dbAdminUsername) {$dbAdminUsername = getenv("DB_ADMIN_USERNAME") ?getenv("DB_ADMIN_USERNAME") : "";}// required
if (!@$dbAdminPassword) {$dbAdminPassword = getenv("DB_ADMIN_PASSWORD") ?getenv("DB_ADMIN_PASSWORD") : "";}// required

// [FOLDERS]

// REQUIRED: defines URL of Heurist filestore (contains files associated with databases)
if (!@$defaultRootFileUploadURL) {$defaultRootFileUploadURL = "http://localhost/HEURIST/HEURIST_FILESTORE/";}
// REQUIRED: defines internal location of Heurist filestore
if (!@$defaultRootFileUploadPath) {$defaultRootFileUploadPath = "/var/www/html/HEURIST/HEURIST_FILESTORE/";}

// [EMAIL]

if (!@$sysAdminEmail) {$sysAdminEmail = "admin@example.org";}
// REQUIRED, please set to email of the system administrator or mailing group
if (!@$infoEmail) {$infoEmail = "info@example.org";}
// recommended, please set to the email of whoever provides user assistance
if (!@$bugEmail) {$bugEmail = "info@HeuristNetwork.org";}
// recommended, set to info@heuristNetwork.org if your server is running a standard Heurist installation

// [ADMINSTRATOR ACCESS PASSWORDS]
// A simple challenge password > 14 characters for creation of new databases.
// If left blank - normal condition - any logged in user can create a new database
if (!@$passwordForDatabaseCreation) {$passwordForDatabaseCreation="";}// normally blank = any logged in user can create

// Note: We strongly recommend setting a password at least for the server functions
// Password(s) to allow system adminstrator certain extra rights. Must be > 14 characters or they are treated as blank
if (!@$passwordForServerFunctions) {$passwordForServerFunctions="";}// if blank, no-one can run server functions, otherwise challenge for password
if (!@$passwordForDatabaseDeletion) {$passwordForDatabaseDeletion="";}// db owner can always delete. Can delete up to 10 at a time with password challenge.
if (!@$passwordForReservedChanges) {$passwordForReservedChanges="";}// if blank, no-one can modify reserved fields, otherwise challenge for password


// [THUMBNAILING SERVICE]

// URL of 3d party website thumbnail service. Heurist can call any thumbnailing service which returns an
// appropriate JPEG or GIF file when passed the URL of a web page. This may be a thumbnail of a security block page
// if the URL is passworded. The thumbnailing service is called automatically when web pages are bookmarked.
// Beware of exceeding free thumbnailign limits if your database is used for a lot of web page bookmarking
if (!@$websiteThumbnailService) {$websiteThumbnailService = "https://api.thumbnail.ws/api/ab73cfc7f4cdf591e05c916e74448eb37567feb81d44/thumbnail/get?url=[URL]&width=320";}
if (!@$websiteThumbnailUsername) {$websiteThumbnailUsername = "";}
if (!@$websiteThumbnailPassword) {$websiteThumbnailPassword = "";}
if (!@$websiteThumbnailXsize) {$websiteThumbnailXsize = 500;} // required
if (!@$websiteThumbnailYsize) {$websiteThumbnailYsize = 300;} // required


// [ACCESS AND PERFORMANCE]

// We STRONGLY  recommend setting the rewrite rules so that you can use short, clean URLs for websites etc.
// use [base_url]/[database]/view/[rec_id] links - Need to define RewriteRule in httpd.conf
// if null it checks for RewriteRule on every system init, set it to true or false to reduce workload
$useRewriteRulesForRecordLink = null;
// If apache redirects are properly set as in the example in server_scripts/apache_configurations.txt, 
// URLs such as https://HeuristRef.net/MyDatabase or https://HeuristRef.net/MyDatabase/39 
// will redirect to https://heurist.huma-num.fr/MyDatabase/web/39
// which will in turn redirect to something like https://heurist.huma-num.fr/heurist/?db=MyDatabase&website=39. 
// Note that omitting the ID will redirect to the website with the lowest ID.

// array of saml service providers
$saml_service_provides = null;
//for example:  $saml_service_provides = array("default-sp"=>"BnF Authentication");

// use webserver to speed access to thumbnail images and uploaded files
// otherwise images will be accessed via php
$allowWebAccessThumbnails = true;
$allowWebAccessUploadedFiles = true;
$allowWebAccessEntityFiles = true;

//Proxy use. If httpProxyAuth is set this will override the value of bypassProxy when making external requests via cURL within UFile.php
if (!@$httpProxy) {$httpProxy = '';}// blank = assumes direct internet access from server
if (!@$httpProxyAuth) {$httpProxyAuth = '';}// authorization for proxy server "username:password"
$httpProxyAlwaysActive = false;           // if true - always use proxy for CURL, otherwise proxy will mostly be used for non-heurist resources

// API keys and accessTokens
$accessToken_MapBox = 'OBTAIN THIS FROM MAPBOX';
$accessToken_MapTiles = 'OBTAIN THIS FROM MAPTILER';
$accessToken_GoogleAPI = 'OBTAIN THIS FROM GOOGLE MAPS';
$accessToken_GeonamesAPI = 'OBTAIN THIS FROM GEONAMES';
$accessToken_Matomo = 'OBTAIN THIS FROM MATOMO';

// Opentheso Servers for external lookup
// List of opentheso compaitable servers for user's to query
//  the uri needs to be up until to the point where the function name is added (as below for pactols and huma-num)
$OPENTHESO_SERVERS = array(
    "pactols" => "https://pactols.frantiq.fr/opentheso/openapi/v1/",
    "huma-num" => "https://opentheso.huma-num.fr/opentheso/openapi/v1/"
);

// [TRANSLATIONS]

// Set these to enable translations with DEEPL
$accessToken_DeepLAPI = 'OBTAIN THIS FROM DEEPL';// To enable DeepL translations
$serverName_DeepL = "DEPENDS ON WHETHER FREE OR PAID SERVICE";

// Common languages for translation database definitions (ISO639-2 codes) 3 char in upper case
// change here to set for the entire installation, overriden by list in configIni.php if present for a specific instance
// The full names and 2 character codes will be looked up in hclient\assets\language-codes-active-list.txt
// The order puts languages at the top which are most likely to be used on this installation
// Place languages supported by DEEPL at the top of the list
// Place languages which are not supported by DEEPL at the end of the list - they can still be used to insert the translation prefix
$common_languages_for_translation = array('ENG','FRE','CHI','SPA','ITA','DUT','GER','GRE','TUR','DAN','NOR','SWE','EST','FIN','ARA','BUR','CZE','HIN','HUN','IND','JPN','JAV','KOR','KUR','LAO','LAT','MAO','MAY','MKH','BUR','NEP','PER','POR','RUS','SLO','SLV','SWA','THA','TIB','UIG','UKR','VIE','YID','ZUL');


// [DATABASE DUMP CONFIGURATION - TIERED SERVERS]

// Tiered system: Change this if running on a system with MySQL running on a separate server from the web application
// MySQL script execution:  see https://dev.mysql.com/doc/refman/8.0/en/mysql-config-editor.html for info
// Determines how to execute mysql scripts, notably mysqldump.
// On a multi-tier server you need to use the 0 option to use the PDO library (bigdump), which may fail on some (large) databases
// On a single tier server, use the 2 option which uses the standard mysqldump program via the shell, which is faster and less likely to fail
// 0: use 3d party PDO library (bigdump), 2: call mysql via shell (default)
$dbScriptMode = 2;
//Determines how to  dump database - 0: use 3d party PDO mysqldump (for multi-tier), 2 - call mysqldump via shell (default, single tier system)
$dbDumpMode = 2;

// path to mysql executables
$dbMySQLpath = '/usr/bin/mysql';
$dbMySQLDump = '/usr/bin/mysqldump';


// [ELASTIC SEARCH]

//  set to IP address and port of Elastic search server, if used
if (!@$indexServerAddress) {$indexServerAddress = "";}
if (!@$indexServerPort) {$indexServerPort = "9200";}


// [SECURITY LOCKDOWNS]

// set to -1 to block creation of new websites with a message to contact sysadmin
$allowCMSCreation = 1;

// On some servers severe restrictions can be activated. They may prevent sending to server json, html or js code snippets
// In other words it forbids any request which is suspected as malicious
// To workaround set this value to "1" to encode json requests for record edit
//
if (!@$needEncodeRecordDetails) {$needEncodeRecordDetails = 0; }


// [LEGACY FIXES]

// Add paths to any servers previously used (since 2020), in the form https://server.domain,http://server.domain, …
// which may have been incorporated in web pages and which should be replaced by local relative paths
// These paths are used in conjunction with Admin > Server management > Fix absolute paths in web page content
// if (!@$absolutePathsToRemoveFromWebPages) $absolutePathsToRemoveFromWebPages = array('https://heuristplus.sydney.edu.au');
$absolutePathsToRemoveFromWebPages = null;

//
// Matomo tracking server configuration
//
$matomoUrl = null; //'domain.com/matomo';
$matomoSiteId = null; //'1';
?>
