<?php
/**
* Access tokens for basemaps. They are defined as global vars in heuristConfigIni.php
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

$parentIni = dirname(__FILE__)."/../../../heuristConfigIni.php";

// parent directory configuration file is optional, hence include not require
$accessToken_MapBox = '';
$accessToken_MapTiles = '';
$accessToken_GoogleAPI = '';
$accessToken_GeonamesAPI = '';

if (is_file($parentIni)){
    include_once($parentIni);
}

echo ' var accessToken_MapBox="'.$accessToken_MapBox.'",';
echo 'accessToken_MapTiles="'.$accessToken_MapTiles.'",';
echo 'accessToken_GoogleAPI="'.$accessToken_GoogleAPI.'",';
echo 'accessToken_GeonamesAPI="'.$accessToken_GeonamesAPI.'";';
?>
