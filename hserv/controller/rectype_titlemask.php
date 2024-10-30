<?php

/**
* Controller for operations with record type title mask
* See records/edit/recordTitleMask.php
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Jan Jaap de Groot  <jjedegroot@gmail.com>
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

require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../records/edit/recordTitleMask.php';

/*
parameters

rty_id - record type id to check
mask - title mask, if not defined we get current mask if check=0
rec_id - execute mask for this record

check 0 - execute for given record id
      1 - validate mask
      2 - get coded mask
      3-  get human readable

*/

// Initialize a System object that uses the requested database
$system = new hserv\System();
if( $system->init(@$_REQUEST['db']) ){

            $rectypeID = @$_REQUEST['rty_id'];
            $mask = @$_REQUEST['mask'];
            $check_mode = @$_REQUEST["check"];

            $invalid_mask = null;
            $response = null;

            if($check_mode==2){ //get coded mask

                $res = TitleMask::execute($mask, $rectypeID, 1, null, ERROR_REP_MSG);
                if (is_array($res)) {
                    $invalid_mask =$res[0];
                }else{
                    $response = $res;
                }

            }elseif($check_mode==3){ //to human readable

                $res = TitleMask::execute($mask, $rectypeID, 2, null, ERROR_REP_MSG);

                if (is_array($res)) {
                    $invalid_mask =$res[0];
                }else{
                    $response = $res;
                }

            }elseif($check_mode==1){ //verify text title mask

                $check = TitleMask::check($mask, $rectypeID, true);

                if (!empty($check)) { //empty means titlemask is valid
                    $invalid_mask =$check;
                }else{
                    $response = null;
                }

            }else{

                $recID = @$_REQUEST['rec_id'];
                $new_title = TitleMask::execute($mask, $rectypeID, 3, $recID, ERROR_REP_WARN);//convert to coded and fill values
                $response =  $new_title;

            }

    $system->dbclose();

    $response = array("status"=>HEURIST_OK, 'data'=>$response, 'message'=>$invalid_mask );

}else{
    $response = $system->getError();
}

// Returning result as JSON
header(CTYPE_JSON);
print json_encode($response);
?>