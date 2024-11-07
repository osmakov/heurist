<?php

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* UI for record view - html iframe wrap fo rendering record info see renderRecordData
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson.heurist@gmail.com>
* @author      Stephen White
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Records/View
*/
require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../../hserv/records/search/recordSearch.php';

$system = new hserv\System();

if(!defined('ERROR_INCLUDE')){
    define('ERROR_INCLUDE', dirname(__FILE__).'/../../hclient/framecontent/infoPage.php');
}

if(!$system->init(@$_REQUEST['db'])){
    include_once ERROR_INCLUDE;
    exit;
}

$mysqli = $system->get_mysqli();

$rec_id = 0;
$bkm_ID = 0;


if (@$_REQUEST['bkmk_id']>0) {  //find record by bookmark id
	$bkm_ID = $_REQUEST['bkmk_id'];
	$rec_id = mysql__select_value($mysqli, 'select * from usrBookmarks where bkm_ID = ' . $bkm_ID);
    if(!($rec_id>0)){
        $_REQUEST['error'] = 'Can\'t find record by bookmark ID';
        include_once ERROR_INCLUDE;
        exit;
    }
} else {
	$rec_id = @$_REQUEST['recID'];
    if(!($rec_id>0)){
        $_REQUEST['error'] = 'Parameter recID not defined';
        include_once ERROR_INCLUDE;
        exit;
    }
}

// check if this record has been replaced (merged)
$rec_id = recordSearchReplacement($mysqli, $rec_id, 0);

//validate permissions
$rec = mysql__select_row_assoc($mysqli,
        'select rec_Title, rec_NonOwnerVisibility, rec_OwnerUGrpID from Records where rec_ID='.$rec_id);

if($rec==null){
    $_REQUEST['error'] = 'Record #'.$rec_id.' not found';
    include_once ERROR_INCLUDE;
    exit;
}

$hasAccess = ($rec['rec_NonOwnerVisibility'] == 'public' ||
    ($system->get_user_id()>0 && $rec['rec_NonOwnerVisibility'] !== 'hidden') ||    //visible for logged
    $system->is_member($rec['rec_OwnerUGrpID']) );//owner

if(!$hasAccess){
        $_REQUEST['error'] = 'You are not a member of the workgroup that owns the Heurist record #'
        .$rec_id.', and cannot therefore view or edit this information.';
        include_once ERROR_INCLUDE;
        exit;
}

//find bookmark by rec id
if(!($bkm_ID>0) && $system->get_user_id()>0 ){ //logged in
    $bkm_ID = mysql__select_value($mysqli, 'select bkm_ID from usrBookmarks where bkm_recID = ' . $rec_id
            . ' and bkm_UGrpID = ' . $system->get_user_id());
}


$noclutter = array_key_exists('noclutter', $_REQUEST)? '&noclutter' : '';
$hideImages = array_key_exists('hideImages', $_REQUEST) ? '&hideImages='.intval($_REQUEST['hideImages']) : '';
$hideImages = '&privateDetails=' . (array_key_exists('privateDetails', $_REQUEST) ? intval($_REQUEST['privateDetails']) : 1);

$rec_title = $rec['rec_Title'];

$record_renderer_url = HEURIST_BASE_URL.'viewers/record/renderRecordData.php?db='
        .HEURIST_DBNAME.'&'.($bkm_ID>0 ? ('bkmk_id='.intval($bkm_ID)) : ('recID='.intval($rec_id)))
        .$noclutter
        .$hideImages;

if(!@$_REQUEST['popup']){
    redirectURL($record_renderer_url);
    exit;
}

?>
<!DOCTYPE HTML>
<html lang="en">
<head>
	<title>HEURIST - View record</title>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <meta name="robots" content="noindex,nofollow">
    
	<link rel="icon" href="<?=HEURIST_BASE_URL?>favicon.ico" type="image/x-icon">
	<link rel="shortcut icon" href="<?=HEURIST_BASE_URL?>favicon.ico" type="image/x-icon">

    <link rel="stylesheet" type="text/css" href="<?=HEURIST_BASE_URL?>h4styles.css">
</head>

<body style="margin: 0px;<?php if (@$_REQUEST['popup']) { ?>width: 480px; height: 600px; background-color: transparent;<?php } ?>" class="popup">
	<div>
	<!--<h3><?= htmlspecialchars($rec_title) ?></h3>-->
	<iframe title="viewer" name="viewer" frameborder="0" style="width: 100%;height: 100%;" src="<?php echo $record_renderer_url;?>"></iframe>
	</div>
</body>
</html>

