<?php
/**
* indexController.php
* Interface/Controller for requests to Heurist_Reference_Index database
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
action

info
register
update
delete
*/

use hserv\utilities\DbRegis;
use hserv\utilities\USanitize;

require_once dirname(__FILE__).'/../../autoload.php';

    $isOutSideRequest = (strpos(strtolower(HEURIST_INDEX_BASE_URL), strtolower(HEURIST_SERVER_URL))===false);//this is NOT reference server

    $sysadmin_pwd = USanitize::getAdminPwd('pwd');
    $req_params = USanitize::sanitizeInputArray();

    $res = false;

    $action = @$req_params['action'];

    $allow_action = true;

    $system = new hserv\System();//global system

    //if db parameter is defined this is initial request
    //1. checks permission - must be dbowner or sysadmin password provided
    //2. adds dbowner credentials to request
    if(@$req_params['db'] && $action!='url'){

        $allow_action = false;
        if($system->init($req_params['db'])){

            if($system->isDbOwner()){
                $allow_action = true;
            }else{
                //sysadmin protection
                $allow_action = !$system->verifyActionPassword($sysadmin_pwd, $passwordForServerFunctions);
            }

            if($allow_action){
                //get database owner credentials
                $dbowner = user_getByField($system->getMysqli(), 'ugr_ID', 2);

                $req_params['usrPassword'] = $dbowner['ugr_Password'];
                $req_params['usrEmail']    = $dbowner['ugr_eMail'];

                if($action=='register'){
                    $req_params['usrName']      = $dbowner['ugr_Name'];
                    $req_params['usrFirstName'] = $dbowner['ugr_FirstName'];
                    $req_params['usrLastName']  = $dbowner['ugr_LastName'];
                }

            }else{
                $system->addError(HEURIST_REQUEST_DENIED,
                            'To perform this action you must be logged in as Database Owner');
            }
        }
    }

    if($allow_action){
        if($action=='info'){
            $res = DbRegis::registrationGet($req_params);
        }elseif($action=='register'){
            $res = DbRegis::registrationAdd($req_params);//returns ID or false
        }elseif($action=='update'){
            $res = DbRegis::registrationUpdate($req_params);
        }elseif($action=='delete'){
            $res = DbRegis::registrationDelete($req_params);//returns ID or false
        }else{
            $system->addError(HEURIST_INVALID_REQUEST, 'Action parameter is missing or incorrect');
        }
    }

if(is_bool($res) && $res==false){
    $response = $system->getError();
}else{
    $response = array("status"=>HEURIST_OK, "data"=> $res);
}

header(CTYPE_JSON);
print json_encode($response);
?>