<?php

    /**
    * rebuildCalculatedFields.php
    * Rebuilds the calculated fields for records listed in search results, for ALL records or for speficied rectypes
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

set_time_limit(0);

define('MANGER_REQUIRED',1);
define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
require_once dirname(__FILE__).'/../../hserv/records/edit/recordModify.php';

//
// options:
// 1. run as standalone script without progress
// 2a. init operation on client side and update progress  verbose=1
// 2b. execute operation on server   session=numeric

$init_client = (@$_REQUEST['verbose']!=1);

if(!$init_client || @$_REQUEST['session']>0){ //2a. init operation on client side


    if(@$_REQUEST['session']>0)
    {
        $rty_IDs = null;
        if(@$_REQUEST['recTypeIDs']!=null){
            $rty_IDs = prepareIds(filter_var($_REQUEST['recTypeIDs']));
        }

        $system->setResponseHeader();
        $res = recordUpdateCalcFields($system, null, $rty_IDs, intval(@$_REQUEST['session']));

        //2b. response to client side
        if( is_bool($res) && !$res ){
            $response = $system->getError();
        }else{
            $response = array("status"=>HEURIST_OK, "data"=> $res);
        }

        print json_encode($response);
        exit;
    }else{

        if(@$_REQUEST['recTypeIDs']){
            $rty_IDs = prepareIds(filter_var($_REQUEST['recTypeIDs']));
        }else{
            $rty_IDs = null;
        }

        $res = recordUpdateCalcFields($system, null, $rty_IDs);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Rebuild Calculated Fields</title>
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

        var action_url = window.hWin.HAPI4.baseURL + "admin/verification/rebuildCalculatedFields.php";

        var session_id = window.hWin.HEURIST4.msg.showProgress( {container:$('.progress_div'), interval:500} );

        var request = {
            'session': session_id
        };
<?php
        if(@$_REQUEST['recTypeIDs']){
            print "request['recTypeIDs'] = '".htmlspecialchars($_REQUEST['recTypeIDs'])."';";//js output
        }
?>
        //url to show affected records
        var sURL = window.hWin.HAPI4.baseURL
                        +'?w=all&db='+window.hWin.HAPI4.database+'&q=';

        window.hWin.HEURIST4.util.sendRequest(action_url, request, null, function(response){
            window.hWin.HEURIST4.msg.hideProgress();

            if(response.status == window.hWin.ResponseStatus.OK){
                $('#rec_total').text(response.data['rec_total']);
                $('#rec_processed').text(response.data['rec_processed']);
                $('#fld_changed').text(response.data['fld_changed']);
                $('#fld_cleared').text(response.data['fld_cleared']);
                $('#fld_same').text(response.data['fld_same']);

                if(response.data['q_updates']){
                    $('#q_updates').attr('href', sURL + response.data['q_updates'] ).show();
                }else{
                    $('#q_updates').hide();
                }
                if(response.data['q_cleared']){
                    $('#q_blanks').attr('href', sURL + response.data['q_cleared'] ).show();
                }else{
                    $('#q_blanks').hide();
                }

                var sErrors = '';
                if(response.data['errors']){

                    for(var key in response.data['errors']){
                        sErrors = sErrors + key+'  '+response.data['errors'][key]+'<br>';
                    }
                    $('#formulae_errors_info').html(sErrors);
                }
                if(!sErrors){
                    $('#formulae_errors').hide();
                }


                $('#info_div').show();
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
        <div class="banner"><h2 style="margin:0">Rebuild Calculated Fields</h2></div>
        <div id="page-inner" style="overflow:auto;padding: 10px;">

<?php
$q_updates = '#';
$q_cleared = '#';
$q_errors = '#';

if($init_client){
    if(!@$_REQUEST['recTypeIDs']){ //long operation - entire database
?>
            <div class="header_info" style="max-width: 800px;">
                This function recalculates all the calculated fields, compares
                them with the existing value and updates the field where the value has
                changed.
                At the end of the process it will display a list of records
                for which the fields were changed, cleared and a list of errors if formula cannot be executed.
            </div>
            <p class="header_info">This will take some time for large databases</p>
<?php
    }
}else{
    if( is_bool($res) && !$res ){
        print errorDiv(htmlspecialchars($system->getErrorMsg()));
        print '</div></body></html>';
        exit;
    }elseif($res['message']){
        print errorDiv(htmlspecialchars($system->getErrorMsg()));
    }

    if($res['q_updates']){
        $q_updates = HEURIST_BASE_URL.'?w=all&q='.$res['q_updates']
            .'&db='.HEURIST_DBNAME;
    }else{
        $q_updates = '';
    }
    if($res['q_cleared']){
        $q_cleared = HEURIST_BASE_URL.'?w=all&q='.$res['q_cleared']
            .'&db='.HEURIST_DBNAME;
    }else{
        $q_cleared = '';
    }

    if(!isEmptyArray(@$res['errors'])){
        $q_errors = '';
        foreach($res['errors'] as $key=>$msg){
            $q_errors = $q_errors . $key . '  ' .$msg . '<br>';
        }
    }else{
        $q_errors = '';
    }
}

?>

            <div class="progress_div" style="background:white;min-height:40px;width:100%"></div>

            <div class="result_div" style="display:<?php echo $init_client?'none':'block';?>;">
                <div><span id=rec_total><?php echo intval(@$res['rec_total']);?></span> records in total</div>
                <div><span id=rec_processed><?php echo intval(@$res['rec_processed']);?></span> records processed</div>
                <div><span id=fld_changed><?php echo intval(@$res['fld_changed']);?></span> fields updated in
                                <span id=rec_updates><?php echo intval(@$res['rec_updates']);?></span> records</div>
                <div><span id=fld_cleared><?php echo intval(@$res['fld_cleared']);?></span> fields cleared in
                                <span id=rec_cleared><?php echo intval(@$res['rec_cleared']);?></span> records</div>
                <div><span id=fld_same><?php echo intval(@$res['fld_same']);?></span> fields unchanged</div>

                <br>

                <?php
                if($q_updates){
                    print '<a target=_blank id="q_updates" href="'.$q_updates.'">Click to view updated records</a><br>&nbsp;<br>';
                }
                if($q_cleared){
                    print '<a target=_blank id="q_blanks" href="'.$q_cleared.'">Click to view records where fields were cleared</a>';
                }
                if($q_errors){
                ?>
                    <br><br>
                    <span id="formulae_errors">
                        <p>
                        There are errors in calculations execution. This is generally due to a faulty in formula
                        <br>or faulty data in individual records. Affected fields have not been changed.
                        </p>
                        <span id="formulae_errors_info">
                        <?php echo htmlspecialchars($q_errors);?>
                        </span>
                    </span>
                <?php
                }
                ?>
            </div>
<?php

if(@$_REQUEST['recTypeIDs']){
?>
        <div id="info_div" style="color: green;padding-top:10px;display:<?php echo $init_client?'none':'block'?>;">
        <hr>
            If the fields of other record types depend on updated records,
            you should run Admin > Rebuild calculated fields to rebuild all calculated fields in the database
        </div>
<?php
}
?>
        </div>
    </body>
</html>