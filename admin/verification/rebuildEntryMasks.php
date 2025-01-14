<?php

/**
* rebuildEntryMasks.php: Re-apply field entry masks for ALL records, specified records or records of a specific type
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6
*/

use hserv\utilities\USanitize;

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

set_time_limit(0);

define('MANGER_REQUIRED', 1);
define('PDIR', '../../'); // need for proper path to js and css

require_once __DIR__.'/../../hclient/framecontent/initPageMin.php';
require_once __DIR__.'/../../hserv/records/edit/recordModify.php';

$request = USanitize::sanitizeInputArray();

$init_client = @$request['verbose'] != 1;

$session_id = @$request['session'];

//results = []
$results = [
    'total' => 0,
    'processed' => 0,
    'updated' => 0,
    'skipped' => 0,
    'invalid' => 0,
    'records' => [
        'updated' => [],
        'skipped' => [],
        'invalid' => [],
        'title_error' => []
    ],
    'title_updated' => 0,
    'invalid_masks' => []
];
$gettingAllRecords = false;

if(!$init_client || $session_id > 0){

    $mysqli = $system->getMysqli();

    // Retrieve and prepare list of record IDs via: provided ids, record type ids, or all necessary ids
    $recIDs = [];
    if(array_key_exists('recIDs', $request) && !empty($request['recIDs'])){
        $recIDs = prepareIds($request['recIDs']);
        $recIDs = array_unique($recIDs); // remove dups to avoid double processing
    }elseif(array_key_exists('recTypeIDs', $request) && !empty($request['recTypeIDs'])){

        $recTypeIDs = prepareIds($request['recTypeIDs']);
        $recIDs = getRecordIDsByRecType($recTypeIDs);

    }else{

        $recIDs = getAllRecords();
        $gettingAllRecords = true;

    }

    $recIDs = array_unique($recIDs);

    $results['total'] = count($recIDs);

    if($session_id > 0){ // setup progress tracker with max count

        $system->setResponseHeader();

        mysql__update_progress(null, $session_id, true, "0,{$results['total']}");
    }

    $current = 0; // for progress flag
    foreach($recIDs as $recID){

        $current ++;

        $rtyID = mysql__select_value($mysqli, "SELECT rec_RecTypeID FROM Records WHERE rec_ID = ?", ['i', $recID]);

        $res = recordUpdateMaskFields($system, $recID, $rtyID, true); // update entry field values

        if($session_id > 0 && $current % 10 === 0){ // update progress flag

            $current_flag = mysql__update_progress(null, $session_id, false, "{$current},{$results['total']}");
            if($current_flag == 'terminate'){ // user has terminated
                $results['msg'] = 'Operation has been terminated by user';
                break;
            }
        }

        $results['updated'] += intval($res['updated']);
        $results['skipped'] += intval($res['skipped']);
        $results['invalid'] += intval($res['invalid']);

        // Add to record ID list
        if($res['updated'] > 0){
            $results['records']['updated'][] = $recID;

            if(!recordUpdateTitle($system, $recID, $rtyID, null)){
                $results['records']['title_error'][] = $recID;
            }else{
                $results['title_updated'] ++;
            }
        }
        if($res['skipped'] > 0){
            $results['records']['skipped'][] = $recID;
        }
        if($res['invalid'] > 0){
            $results['records']['invalid'][] = $recID;
        }

        if(!empty($res['invalid_masks'])){

            if(array_key_exists($rtyID, $results['invalid_masks'])){
                $results['invalid_masks'][$rtyID] = [];
            }

            $results['invalid_masks'][$rtyID] = array_merge($results['invalid_masks'][$rtyID], $res['invalid_masks']);
        }

    }

    setResultRecordIDs($results['records']['updated']);
    setResultRecordIDs($results['records']['skipped']);
    setResultRecordIDs($results['records']['invalid']);
    setResultRecordIDs($results['records']['title_error']);

    $results['processed'] = $current;

    if($session_id > 0){

        mysql__update_progress(null, $session_id, false, 'REMOVE');

        print json_encode(['status' => HEURIST_OK, 'data' => $results]);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

    <head>
        <title>Re-apply Field Entry Masks</title>

        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css">

    <?php
    if($init_client){
        includeJQuery();
    ?>

        <script type="text/javascript" src="../../hclient/core/detectHeurist.js"></script>
        <script type="text/javascript" src="../../hclient/core/hapi.js"></script>
        <script type="text/javascript" src="../../hclient/core/utils.js"></script>
        <script type="text/javascript" src="../../hclient/core/utils_dbs.js"></script>

        <script type="text/javascript">

            if(!window.hWin.HAPI4 && typeof hAPI === 'function'){
                window.hWin.HAPI4 = new hAPI('<?php echo HEURIST_DBNAME; ?>', $.noop);
            }

            $(document).ready(() => {

                if(top.hWin){
                    window.hWin = top.hWin;
                }else{
                    return;
                }

                let action_url = `${window.hWin.HAPI4.baseURL}admin/verification/rebuildEntryMasks.php`;

                let session_id = window.hWin.HEURIST4.msg.showProgress( {container:$('.progress_div'), interval: 500} );

                let request = {
                    'session': session_id
                };

                <?php
                if(array_key_exists('recTypeIDs', $request)){
                    echo "request['recTypeIDs'] = '{$request['recTypeIDs']}';";
                }
                ?>

                let query_base = `${window.hWin.HAPI4.baseURL}?w=a&db=${window.hWin.HAPI4.database}&q=`;

                window.hWin.HEURIST4.util.sendRequest(action_url, request, null, (response) => {
                    window.hWin.HEURIST4.msg.hideProgress();

                    if(response.status !== window.hWin.ResponseStatus.OK){
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                        return;
                    }

                    let data = response.data;

                    $('#fld_total').text(data.total);
                    $('#fld_processed').text(data.processed);
                    $('#fld_invalid').text(data.invalid);
                    $('#fld_skipped').text(data.skipped);
                    $('#fld_updated').text(data.updated);
                    $('#fld_title_updated').text(data.title_updated);

                    if(data.records.updated?.length > 0){
                        $('#lnk_updated').attr('href', query_base + data.records.updated).show();
                    }

                    if(data.records.skipped?.length > 0){
                        $('#lnk_skipped').attr('href', query_base + data.records.skipped).show();
                    }

                    if(data.records.invalid?.length > 0){
                        $('#lnk_invalid').attr('href', query_base + data.records.invalid).show();
                    }

                    if(data.records.title_error?.length > 0){
                        $('#lnk_titles').attr('href', query_base + data.records.title_error).show();
                    }

                    if(data.invalid_masks?.length > 0){

                        let invalid_masks = '';
                        for(const rtyID of data.invalid_masks){

                            let fields = `${$Db.rty(rtyID, 'rty_Name')}:<div style="padding-left:15px;">`;

                            for(const dtyID of data.invalid_masks[rtyID]){
                                fields += `${$Db.rst(rtyID, dtyID, 'rst_DisplayName')} => ${data.invalid_masks[rtyID][dtyID]}<br>`;
                            }

                            invalid_masks += `<div style='padding: 5px 0px;'>${fields}</div></div>`;
                        }

                        $('#div_invalidmasks').show();
                    }

                    $('.result_div').show();
                    $('.header_info').hide();
                });
            });

        </script>
    <?php
    }
    ?>


    </head>

    <body class="popup">

        <div class="banner"><h2 style="margin:0">Rebuild Entry Masks</h2></div>

        <div id="page-inner" style="overflow:auto;padding: 10px;">

        <?php

        $updated_url = '#';
        $skipped_url = '#';
        $invalid_url = '#';
        $invalid_titles_url = '#';
        $invalid_masks = '';

        if($init_client){
            if($gettingAllRecords){
            ?>

                <div class="header_info" style="max-width: 800px;">
                    This function re-applies all field entry masks,
                    updating values where the mask has been applied,
                    and skipping ones that are already up to date or do not match the mask.
                    At the end a list of updated records, records with skipped values and invalid entry masks will be added.
                </div>

                <p class="header_info">This can take some time for larger databases</p>

            <?php
            }
        ?>

        <?php
        }else{

            if(!empty($results['records']['updated'])){
                $updated_url = HEURIST_BASE_URL . "?w=a&q={$results['records']['updated']}&db=" . HEURIST_DBNAME;
            }

            if(!empty($results['records']['skipped'])){
                $skipped_url = HEURIST_BASE_URL . "?w=a&q={$results['records']['skipped']}&db=" . HEURIST_DBNAME;
            }

            if(!empty($results['records']['invalid'])){
                $inavlid_url = HEURIST_BASE_URL . "?w=a&q={$results['records']['invalid']}&db=" . HEURIST_DBNAME;
            }

            if(!empty($results['records']['title_error'])){
                $invalid_titles_url = HEURIST_BASE_URL . "?w=a&q={$results['records']['title_error']}&db=" . HEURIST_DBNAME;
            }

            if(!empty($results['invalid_masks'])){
        
                foreach($results['invalid_masks'] as $rtyID => $masks){
        
                    $str = USanitize::sanitizeString(mysql__select_value($mysqli, "SELECT rty_Name FROM defRecTypes WHERE rty_ID = {$rtyID}")) . ':<div style="padding-left:15px;">';
                    foreach($masks as $dtyID => $mask){
                        $str .= USanitize::sanitizeString(mysql__select_value($mysqli, "SELECT rst_DisplayName FROM defRecStructure WHERE rst_DetailTypeID = {$dtyID} AND rst_RecTypeID = {$rtyID}")) . " => {$mask}<br>";
                    }
                    $invalid_masks .= "<div style='padding: 5px 0px;'>{$str}</div></div>";
                }
            }
        ?>

        <?php
        }
        ?>
            <div class="progress_div" style="background:white;min-height:40px;width:100%"></div>

            <div class="result_div" style="display:<?php echo $init_client?'none':'block';?>;">

                <div><span id="fld_total"><?php echo $results['total']; ?></span> records total</div>
                <div><span id="fld_processed"><?php echo $results['processed']; ?></span> records processed</div>
                <div><span id="fld_invalid"><?php echo $results['invalid']; ?></span> invalid values</div>
                <div><span id="fld_skipped"><?php echo $results['skipped']; ?></span> values skipped</div>
                <div><span id="fld_updated"><?php echo $results['updated']; ?></span> values updated</div>
                <div><span id="fld_title_updated"><?php echo $results['title_updated']; ?></span> record titles updated</div>

                <?php

                echo "<br><a href='{$updated_url}' id='lnk_updated' style='display:". ($updated_url == '#' ? 'inline' : 'none') ."'>click to view records with updated values</a><br><br>";

                echo "<a href='{$skipped_url}' id='lnk_skipped' style='display:". ($skipped_url == '#' ? 'inline' : 'none') ."'>click to view records with values skipped</a><br><br>";

                echo "<a href='{$invalid_url}' id='lnk_invalid' style='display:". ($invalid_url == '#' ? 'inline' : 'none') ."'>click to view records with invalid values</a><br><br>";

                echo "<a href='{$invalid_titles_url}' id='lnk_titles' style='display:". ($invalid_titles_url == '#' ? 'inline' : 'none') ."'>click to view records that failed to update their title</a><br><br>";

                // Show invalid masks
                echo "<br><hr><br>"; // divider

                echo "<div id='div_invalidmasks' style='display:". (!empty($invalid_masks) ? 'block' : 'none') ."'>The following fields were found to have <strong>invalid masks</strong>: {$invalid_masks}</div>";

                ?>

            </div>
        </div>
        
    </body>
</html>

<?php

/**
 * Retrieve array of ALL relevant record IDs, i.e. only those which type have an entry mask
 *
 * @return array complete list of record IDs
 */

function getAllRecords(){

    global $system;

    $mysqli = $system->getMysqli();

    $rtyIDs = mysql__select_list2($mysqli, "SELECT rst_RecTypeID FROM defRecStructures WHERE rst_EntryMask IS NOT NULL", 'intval');

    return getRecordIDsByRecType($rtyIDs, false);
}

/**
 * Rtrieve array of ALL releveant record IDs by record type
 *
 * @param array<int>|int $recTypeIDs array of record type IDs to update
 * @param bool $checkRecTypes whether to check for the existence of an entry mask
 *
 * @return array complete list of record IDs
 */

function getRecordIDsByRecType($recTypeIDs, $checkRecTypes = true){

    global $system;

    $mysqli = $system->getMysqli();

    if(empty($recTypeIDs)){
        return [];
    }

    if($checkRecTypes){

        // Check for an entry mask before retrieving record IDs
        $query = "SELECT rst_ID FROM defRecStructures WHERE rst_RecTypeID = ? AND rst_EntryMask IS NOT NULL";
        $recTypeIDs = array_filter($recTypeIDs, function($rtyID) use ($mysqli, $query){
            return mysql__select_value($mysqli, $query, ['i', $rtyID]) <= 0;
        });

        if(empty($recTypeIDs)){
            return [];
        }
    }

    $recTypeClause = count($recTypeIDs) == 1 ? "= {$recTypeIDs[0]}" : 'IN ('. implode(',', $recTypeIDs) .')';

    $query = "SELECT rec_ID FROM Records WHERE rec_RecTypeID {$recTypeClause} AND NOT rec_FlagTemporary";

    return mysql__select_list2($mysqli, $query, 'intval');
}

/**
 * Condense and format array of record IDs for a Heurist search, max at 100
 *
 * @param array<int> $recordIDs array of record IDs
 *
 * @return void
 */

function setResultRecordIDs(&$recordIDs){

    if(!empty($recordIDs)){
        $recordIDs = 'ids:' . implode(',', array_splice($recordIDs, 0, 1000));
    }
}
