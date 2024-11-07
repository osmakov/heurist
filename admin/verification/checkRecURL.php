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
* checkRecURL.php - checks all record URLs to see if they are valid
*
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
*/

set_time_limit(0);

define('PDIR','../../');

require_once dirname(__FILE__).'/../../autoload.php';
require_once 'URLChecker.php';

$list_only = (@$_REQUEST['list']==1);

$system = new hserv\System();
if( ! $system->init(@$_REQUEST['db']) ){
    //get error and response
    print $system->getErrorMsg();
    return;
}
if(!$system->is_admin()){ //  $system->is_dbowner()
    print '<span>You must be logged in as Database Administrator to perform this operation</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
        <title>Check Records URL</title>
    </head>
    <body class="popup">
        <div class="banner">
            <h3>Check Records URL</h3>
        </div>
        <div id="page-inner">
<?php

//$list_only = true;
$isHeuristReferenceIndex = (strcasecmp(HEURIST_DBNAME,'Heurist_Reference_Index')==0);
$checker = new URLChecker($system->get_mysqli(), HEURIST_SERVER_URL, $isHeuristReferenceIndex);
$results = $checker->checkURLs(true, $list_only);

/* heurist instances
$heurist_instances = array(
 'https://int-heuristweb-prod.intersect.org.au',
 'https://heuristref.net',
 'https://heurist.huma-num.fr',
 'https://heurist.sfb1288.uni-bielefeld.de',
 'https://heurisko.io',
 'https://heurist.eie.gr',
 'https://ship.lub.lu.se',
 'https://heurist.fdm.uni-hamburg.de',
 'http://fedora.gwin.gwiss.uni-hamburg.de',
 'https://pfcmati.bnf.fr',
 'https://heurist.researchsoftware.unimelb.edu.au',
 'https://heurist.unige.ch',
 'https://dcsrs-test-ssp.ad.unil.ch',
 );
*/
?>
</div></body></html>
