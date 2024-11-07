<?php

    /**
    * rebuildRecordTitles.php
    * Rebuilds the constructed record titles listed in search results, for ALL records or for speficied rectypes
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Tom Murtagh
    * @author      Kim Jackson
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @author      Stephen White
    * @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     3.1.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

    /*
    * TODO: Massive redundancy: This is pretty much identical code to recalcTitlesSopecifiedRectypes.php and should be
    * combined into one file, or call the same functions to do the work
    */
set_time_limit(0);

define('MANGER_REQUIRED',1);
define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
require_once dirname(__FILE__).'/../../hserv/records/edit/recordTitleMask.php';

//
// options:
// 1. run as standalone script without progress
// 2a. init operation on client side and update progress  verbose=1
// 2b. execute operation on server   session=numeric

$init_client = (@$_REQUEST['verbose']!=1);

$rty_ids_list = null;
//sanitize
if(@$_REQUEST['recTypeIDs']){
    $rty_ids = prepareIds(filter_var($_REQUEST['recTypeIDs']));
    $mysqli = $system->get_mysqli();

    if(!empty($rty_ids)) {$rty_ids_list = implode(',', $rty_ids);}
}

if(!$init_client || intval(@$_REQUEST['session'])>0){ //2a. init operation on client side

    $res = doRecTitleUpdate($system, intval(@$_REQUEST['session']),  $rty_ids_list);

    if(@$_REQUEST['session']>0)
    {
        //2b. response to client side
        if( is_bool($res) && !$res ){
            $response = $system->getError();
        }else{
            $response = array("status"=>HEURIST_OK, "data"=> $res);
        }

        $system->setResponseHeader();
        print json_encode($response);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Rebuild Constructed Record Titles</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />

<?php if($init_client){
        includeJQuery();
?>
        <script type="text/javascript">


    //
    //
    //
    $(document).ready(function() {

        if(top.hWin){  //main heurist window
            window.hWin = top.hWin;
        }else{
            return;
        }

        var action_url = window.hWin.HAPI4.baseURL + "admin/verification/rebuildRecordTitles.php";

        var session_id = window.hWin.HEURIST4.msg.showProgress( {container:$('.progress_div'), interval:500} );

        var request = {
            'session': session_id
        };
<?php
        if($rty_ids_list){
            print "request['recTypeIDs'] = '".$rty_ids_list."';";
        }
?>

        var sURL = window.hWin.HAPI4.baseURL
                        +'?w=all&db='+window.hWin.HAPI4.database+'&nometadatadisplay=true&q=';

        window.hWin.HEURIST4.util.sendRequest(action_url, request, null, function(response){
            window.hWin.HEURIST4.msg.hideProgress();

            if(response.status == window.hWin.ResponseStatus.OK){
                $('#changed_count').text(response.data['changed_count']);
                $('#same_count').text(response.data['same_count']);
                $('#blank_count').text(response.data['blank_count']);
                $('#total_count').text(response.data['total_count']);
                if(response.data['q_updates']){
                    $('#q_updates').attr('href', sURL + response.data['q_updates'] ).show();
                }else{
                    $('#q_updates').hide();
                }
                if(response.data['q_blanks']){
                    $('#q_blanks').attr('href', sURL + response.data['q_blanks'] ).show();
                }else{
                    $('#q_blanks').hide();
                    $('#q_blanks_info').hide();
                }

                $('.result_div').show();
                $('.header_info').hide();
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }

        });


    });

        </script>

<?php
}
?>
    </head>

    <body class="popup">
        <div class="banner"><h2 style="margin:0">Rebuild Constructed Record Titles</h2></div>
        <div id="page-inner" style="overflow:auto;padding: 10px;">

<?php
if($init_client){
    if(!@$_REQUEST['recTypeIDs']){
?>

            <div class="header_info" style="max-width: 800px;">
                This function recalculates all the constructed (composite) record titles, compares
                them with the existing title and updates the title where the title has
                changed (generally due to changes in the title mask for the record type).
                At the end of the process it will display a list of records
                for which the titles were changed and a list of records for which the
                new title would be blank (an error condition).
            </div>
<?php
}
?>
            <p class="header_info">This will take some time for large databases<br>During this time you will not be able to access the database</p>

            <div class="progress_div" style="background:white;min-height:40px;width:100%"></div>

            <div class="result_div" style="display:none;">
                <div><span id=total_count></span> records in total</div>
                <div><span id=changed_count></span> are updated</div>
                <div><span id=same_count></span> are unchanged</div>
                <div><span id=blank_count></span> are left as-is (missing fields etc)</div>

                <br><a target=_blank id="q_updates" href="#">Click to view updated records</a><br>&nbsp;<br>

                <a target=_blank id="q_blanks" href="#">Click to view records for which the data would create a blank title</a>
                <span id="q_blanks_info">
                    <br>This is generally due to a faulty title mask (verify with Check Title Masks)
                    <br>or faulty data in individual records. These titles have not been changed.
                </span>
            </div>
<?php
/*
print '<br><br><b>DONE</b><br><br><a target=_blank href="'.HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.
    '&w=all&q=ids:'.join(',', $updates).'">Click to view updated records</a><br>&nbsp;<br>';

if(!empty($blanks)){
    print '<br>&nbsp;<br><a target=_blank href="'.HEURIST_BASE_URL.'?db='.HEURIST_DBNAME.
        '&w=all&q=ids:'.join(',', $blanks).
    '">Click to view records for which the data would create a blank title</a>'.
    '<br>This is generally due to faulty title mask (verify with Check Title Masks)<br>'.
    'or faulty data in individual records. These titles have not been changed.';
}
*/

}else{

    if( is_bool($res) && !$res ){

        print error_Div($system->getErrorMsg());

    }else{
        print '<div><span id=total_count>'.intval($res['total_count']).'</span> records in total</div>';
        print '<div><span id=changed_count>'.intval($res['changed_count']).'</span> are updated</div>';
        print '<div><span id=same_count>'.intval($res['same_count']).'</span> are unchanged</div>';
        print '<div><span id=blank_count>'.intval($res['blank_count']).'</span> are left as-is (missing fields etc)</div>';

        if($res['q_updates']){
            print '<a target=_blank href="'.HEURIST_BASE_URL.'?w=all&q='.htmlspecialchars($res['q_updates'])
                .'&db='.HEURIST_DBNAME.'&nometadatadisplay=true">Click to view updated records</a><br>&nbsp;<br>';
        }
        if($res['q_blanks']){
            print '<a target=_blank href="'.HEURIST_BASE_URL.'?w=all&q='.htmlspecialchars($res['q_blanks']).'&db='.HEURIST_DBNAME.
                '&nometadatadisplay=true">Click to view records for which the data would create a blank title</a>'.
                '<br>This is generally due to a faulty title mask (verify with Check Title Masks)'.
                '<br>or faulty data in individual records. These titles have not been changed.';

        }

    }

}
if(@$_REQUEST['recTypeIDs']){
?>
        <hr>
        <div style="color: green;padding-top:10px;">
            If the titles of other record types depend on these titles,
            you should run Admin > Rebuild record titles to rebuild all record titles in the database
        </div>
<?php
}
?>
        </div>
    </body>
</html>
<?php
//
//
//
function doRecTitleUpdate( $system, $progress_session_id, $recTypeIDs ){
    $updates = array();
    $blanks = array();

    $blank_count = 0;
    $repair_count = 0;
    $processed_count = 0;
    $unchanged_count = 0;


    $mysqli = $system->get_mysqli();

    $rec_count = mysql__select_value($mysqli, 'select count(rec_ID) rec_RecTypeID from Records where !rec_FlagTemporary '
                .($recTypeIDs?'and rec_RecTypeID in ('.$recTypeIDs.')':''));

    $res = $mysqli->query('select rec_ID, rec_Title, rec_RecTypeID from Records where !rec_FlagTemporary '
                .($recTypeIDs?'and rec_RecTypeID in ('.$recTypeIDs.')':'').' order by rand()');
    if(!$res){
        $system->addError(HEURIST_DB_ERROR, 'Cannot retrieve records to update title', $mysqli->error);
        return false;
    }

    //masks per record types
    $masks = mysql__select_assoc2($mysqli, 'select rty_ID, rty_TitleMask from  defRecTypes');

    if($progress_session_id>0 && $rec_count>100){
        mysql__update_progress(null, $progress_session_id, true, '0,'.$rec_count);
    }

    $titleDT = $system->defineConstant('DT_NAME')?DT_NAME :0;

    while ($row = $res->fetch_assoc() ) {

        $rec_id = $row['rec_ID'];
        $rec = $row;


        $mask = $masks[$rec['rec_RecTypeID']];

        $new_title = TitleMask::execute($mask, $rec['rec_RecTypeID'], 0, $rec_id, ERROR_REP_WARN);
        if(mb_strlen($new_title)>1023){
            $new_title = mb_substr($new_title,0,1023);
        }

        $processed_count++;

        $rec_id = intval($rec_id);
        $rec_title = trim($rec['rec_Title']);
        if ($new_title && $rec_title && $new_title == $rec_title && strstr($new_title, $rec_title) ){
            //not changed
            $unchanged_count++;

        }elseif (! preg_match('/^\\s*$/', $new_title)) {    // if new title is blank, leave the existing title

            $updates[] = $rec_id;
            $mysqli->query('update Records set rec_Modified=rec_Modified, rec_Title="'.
                $mysqli->real_escape_string($new_title).'" where rec_ID='.$rec_id);

        }else {
            if ( $rec['rec_RecTypeID'] == 1 && $rec['rec_Title']) {
                $titleDT = intval($titleDT);

                $has_detail_160 = mysql__select_value($mysqli,
                    "select dtl_ID from recDetails where dtl_DetailTypeID = $titleDT and dtl_RecID =". $rec_id);
                //touch the record so we can update it  (required by the heuristdb triggers)
                $mysqli->query('update Records set rec_RecTypeID=1 where rec_ID='.$rec_id);
                if ($has_detail_160) {
                    $query = "update recDetails set dtl_Value=? where dtl_DetailTypeID = $titleDT and dtl_RecID=".$rec_id;
                }else{
                    $query = 'insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) VALUES('
                        .intval($rec_id) . ','. intval($titleDT)  .',?)';
                }

                mysql__exec_param_query($mysqli, $query, array('s', $rec['rec_Title']));

                ++$repair_count;
            }else{
                array_push($blanks, $rec_id);
                ++$blank_count;
            }
        }

        if($progress_session_id>0 &&  $rec_count>100){
            $session_val = $processed_count.','.$rec_count;
            $current_val = mysql__update_progress(null, $progress_session_id, false, $session_val);
            if($current_val && $current_val=='terminate'){
                $msg_termination = 'Operation has been terminated by user';
                break;
            }
        }

    }//while records

    $res->close();

    //remove session file
    if($progress_session_id>0 && $rec_count>25 && ($processed_count % 25 == 0)){
        mysql__update_progress(null, $progress_session_id, false, 'REMOVE');
    }

    $q_updates = '';
    if(count($updates)>1000){
        $q_updates = 'sortby:-m';
    }elseif(!empty($updates)){
        $q_updates = 'ids:'.implode(',',$updates);
    }
    $q_blanks = '';
    if(count($blanks)>2000){
        $q_blanks = 'ids:'.array_slice($blanks, 0, 2000);
    }elseif(!empty($blanks)){
        $q_blanks = 'ids:'.implode(',',$blanks);
    }

    return array('changed_count'=>count($updates), 'same_count'=>$unchanged_count, 'blank_count'=>$blank_count, 'total_count'=>$rec_count,
       'q_updates'=>$q_updates, 'q_blanks'=>$q_blanks);
}
?>
