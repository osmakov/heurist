<?php

/**
* configIni.php: configuration file for this Heurist instance.
*
* Note: This file is overriden by heuristConfigIni.php in the parent directory, allowing a single config file for all instances
*       Program version number, however, is always specified by this file and should not be changed
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
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


/*
**************************************************************

WARNING:

Setting any of the values in the configIni.php file overrides the
values set in ../heuristConfigIni.php FOR THAT INSTANCE ONLY of Heurist.
All other instances will use the value set in ../heuristConfigIni.php

WE THEREFORE RECOMMEND NOT CHANGING ANYTHING IN THIS FILE

Values in this file should only be set for testing purposes or
by an experienced sysadmin for very unusual server setups

**************************************************************
*/

/* --------------------------------------------------------------------------------------------

Setting up the server to support multiple code versions
-------------------------------------------------------

Note: This is done automatically by the installation routies in install_heurist.sh

Move the file move_to_parent_as_heuristConfigIni.php to the parent directory of the codebase
rename to heuristConfigIni.php and enter MySQL passwords, paths and other config settings there.
This allows Heurist instances to exist as multiple codebases on a single server and avoids
the need for duplication of information or the accidental distribution of passwords etc.
if one of these codebases is used as a code source.

Also move move_to_parent_as_index.html - the Heurist 'switchboard' to the parent directory
of the codebase and rename it to index.html

--------------------------------------------------------------------------------------------
*/

// *** DO NOT EDIT THIS FILE (except if you really know why you are doing it!) *** //
// =============================================================================== //

// WARNING: this file will be overwritten every time you upgrade Heurist,
// so your changes will be lost unless you take very special precautions
// not to have it updated (you should, however, update the version number
// in that case so that it correspondswith the version installed).

// The blank values in this file are overridden by values in heuristConfigIni.php in the parent directory
// (normally /var/www/html/HEURIST), which is where the configuration of Heurist, applied to all instances, is located.

// So why does this file exist?

// In some exceptional cases you might want to create an 'orphan' version of Heurist
// with a fixed and different configuration from the standard version. This might be
// to access a special database with some special settings eg. on a different server
// or with different access codes. By placing values in the fields in this file you
// can override the values which are otherwise set by heuristConfigIni.php in th parent.

// ---------------------------------------------------------------------------------
// *** DO NOT CHANGE VERSION NUMBER, THIS IS SET BY THE HEURIST DEVELOPMENT TEAM ***

$version = "6.6.6";// sets current program version number, determined by Heurist development lead

// ---------------------------------------------------------------------------------

// 6.6.6  16 Feb 2025
// 6.6.5  05 Feb 2025
// 6.6.3  21 Dec 2024
// 6.6.2  3 Dec 2024
// 6.5.7  29 May 2024
// 6.5.6  20 May 2024

// 2024 release dates: 6.5.4 21 Feb 2024  6.5.3 05 Feb 2024  6.5.2 19 Jan 2024  6.5.1  1 Jan 2024
// Version 6 released early 2021. Version 5 released 25 Jul 2018  Version 4.2.20 5 Jun 2017. Not recorded prior.

$heuristReferenceServer = "https://heuristref.net";// DO NOT CHANGE THIS as it is critical for a number of functions

// *** DO NOT SET THESE UNLESS YOU KNOW WHAT YOU ARE DOING ***
//     they override the values set in ../heuristConfigIni.php
$dbHost = '';
$dbPort = null;  //'3306'
$dbAdminUsername = '';
$dbAdminPassword = '';

//Determines how to execute sql script file  -  0: use db_script function (based on 3d party BigDump (default), 2 - call mysql via shell
$dbScriptMode = 0;
//Determines how to  dump database  -  0: use 3d party PDO Mysqldump (default), 1:use internal routine (disabled), 2 - call mysqldump via shell
$dbDumpMode = 0;

// path to mysql executables
$dbMySQLpath = null;
$dbMySQLDump = null;


// dbPrefix will be prepended to all database names so that you can easily distinguish Heurist databases on your database server
// from other MySQL databases. Some Admin tools such as PHPMyAdmin will group databases with common prefixes ending in underscore
// The prefix may be left blank, in which case nothing is prepended. For practial management we strongly recommend a prefix.

$dbPrefix = 'hdb_';// Although this can be overwritten in heuristConfigIni.php WE STRONGLY recommend retaining hdb_

// Elastic Search (Lucene) server
$indexServerAddress = '';
$indexServerPort = '';

$httpProxyAlwaysActive = false; // if true - always use proxy for CURL, otherwise proxy will be used for non heurist resources mostly
$httpProxy = '';
$httpProxyAuth = '';
$indexServerAddress='';
$indexServerPort='';

// Functions normally available only to the system adminstrator. Password(s) must be > 14 characters or they are treated as blank
// If DatabaseDeletion password set, system administrator can delete up to 10 at a time (with password challenge)
$passwordForDatabaseCreation ='';// normally blank = any logged in user can create, otherwise password challenge
$passwordForDatabaseDeletion ='';// if blank = no one can delete db except db owner (to delete from server management)
$passwordForReservedChanges  ='';// if blank = no-one can modify reserved fields, otherwise password challenge
$passwordForServerFunctions  ='';// if blank = no-one can run server analysis functions - risk of overload - otherwise password challenge

// The default root pathname of a directory where Heurist can store uploaded files eg. images, pdfs, as well as record type icons, templates,
// output files, scratch space and so forth.
$defaultRootFileUploadPath ='';
$defaultRootFileUploadURL = '';

// [server]                 
// enter the server name or IP address of your Web server, null will pull SERVER_NAME from the request header
// you may set this value if several domains point to your server. It will unify urls across links, web pages, reports
// for example $serverName = "heuristscholar.org";  Be sure to include the port if not port 80
$serverName = null; // if not 'null', overrides default taken from request header SERVER_NAME
$mailDomain = null; // set mail domain if it does not use server domain

// if base $heuristBaseURL is null, heurist detects it automatically 
// Although it may differ from desired url you wish to see (because web server settings: aliases, rewrite rules etc)
// Set this value explicitely to avoid possible issues
$heuristBaseURL = null;     // base url ( ie server url+optional folder https://heuristscholar.org/h6-alpha )  
// if you have several heurist instances of heurist, set this value to production instance
//
// if $heuristBaseURL is set and $heuristBaseURL_pro is null, then production version is the same as $heuristBaseURL
// if both $heuristBaseURL and $heuristBaseURL_pro are null, heurist detects it automatically, default folder for pro version is /heurist
$heuristBaseURL_pro = null;  //url for production version  

$sysAdminEmail = '';
$infoEmail = '';
$bugEmail = '';
$websiteThumbnailService = '';
$websiteThumbnailUsername = '';
$websiteThumbnailPassword = '';

//name of template file in /hclient/widgets/cms/templates. If it is not defined it takes cmsTemplate.php
$default_CMS_Template = '';

$absolutePathsToRemoveFromWebPages = null;

//array - pairs: "sp id"=>"Service Provide Name"
$saml_service_provides = null;

// if value is 1, it hides the standard heurist login and show SAML auth only ($saml_service_provides must be defined)
$hideStandardLogin = 0;

$defaultFaimsModulesPath = "";// FAIMS only: the location where FAIMS module files will be written

// use webserver to fasten access to thumbnail images and uploaded files
// otherwise images will be accessed via php
$allowWebAccessThumbnails = true;
$allowWebAccessUploadedFiles = false;
$allowWebAccessEntityFiles = false;

// use [base_url]/[database]/view/[rec_id] links - Need to define RewriteRule in httpd.conf
// see heuristConfigIni.php for more information
// if null it checks for RewriteRule on every system init, set it to true or false to reduce workload
$useRewriteRulesForRecordLink = null;


// matomo tracking server
$matomoUrl = null; // for example 'domain.com/matomo'
$matomoSiteId = null; // ID of tracked heurist domain in matomo configuration

// system default file - if a heuristConfigIni.php file exists in the parent directory of the installation,
// the configIni.php in the installation does not need to be configured. This allows unconfigured ConfigIni.php files
// to exist in multiple experimental codebases on a single server and avoids accidental distribution of passwords etc.

$parentIni = dirname(__FILE__)."/../heuristConfigIni.php";

// parent directory configuration file is optional, hence include not required
// heuristConfigIni.php in parent directory overrides empty values in current file
if (is_file($parentIni)){
    include_once $parentIni;
}
?>
