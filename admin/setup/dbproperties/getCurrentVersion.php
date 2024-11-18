<?php

/*
* Copyright (C) 2005-2023 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* getCurrentVersion.php - requests code and database version from Heurist master index server
* this script runs on master index server ONLY
* this script is invoked from other than Heurist master index server domains ONLY
*
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
require_once dirname(__FILE__).'/../../../autoload.php';

$rawdata = '';

$mysqli = mysql__init(@$_REQUEST['db']);
$db_version = getDbVersion($mysqli);
if($db_version){
    $rawdata = HEURIST_VERSION."|".$db_version;
}

print $rawdata;
