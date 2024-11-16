<?php
/**
* Interface/Controller for external repository configuration and file manipulations
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

  list -  get list of available external repositories for given user - user_getRepositoryList
    (need select available repositories on file upload )

  get - returns credentials for given service and current user - user_getRepositoryCredentials
    (1. returns values to edit on client side 2. returns parameters to create url or login to repository)

  update - save/delete credentials in ugr_Preferences - user_saveRepositoryCredentials

*/

/*
Credentials for external repositories are saved in sysUGrps.ugr_Preferences
per database (group 0) per group or per user.
Unique id is "serviceName_groupUserid"
{
    "externalRepositories": {
        "nakala_1": {
            "service": "nakala",
            "label": "Nakala",
            "service_id": "nakala_1",
            "usr_ID": "1",
            "params": {
                "writeApiKey": "2222",
                "writeUser": "",
                "writePwd": "",
                "readApiKey": "",
                "readUser": "",
                "readPwd": ""
            }
        }
    }
}

These values are not transferred  to client as other user preferences.
On client side it is possible to obtain
1) either list of available repositories for current user (action "list")
        see recordAction.js  window.hWin.HAPI4.SystemMgr.repositoryAction({'a': 'list'}
2) or for edit form (action "get")
        see repositoryConfig.js window.hWin.HAPI4.SystemMgr.repositoryAction({'a': 'get'}

When a file is uploaded to ext.repository, repository service_id should be provided.
see recordBatch.php

$service_id = $this->data['repository'];
$credentials = user_getRepositoryCredentials2($this->system, $service_id);

$credentials[$service_id]['params']['writeApiKey'] or user+pwd can be used for authentication

on registration of URL, provide ulf_Parameters

$fields = array('ulf_Parameters'=>'{"repository":"'.$service_id.'"}');
$new_ulf_ID = $file_entity->registerURL($rtn,false,0,$fields)

It will be used later for authentication in fileDownload.php

if(is_array($fileParams) && @$fileParams['repository']){
    $service_id = $fileParams['repository'];
    $credentials = user_getRepositoryCredentials2($system, $service_id);
    if($credentials!=null){
           $readApiKey = @$credentials[$service_id]['params']['readApiKey'];

    }
}

see dbsUserGroups.php for repository credentials methods

    user_getRepositoryList - list of available/writeable external repositories for given user
    user_getRepositoryCredentials2 - returns credentials for given service_id  (service_name+user_id)
    user_getRepositoryCredentials - returns read/write credentials for given service and user_id  (for edit on client side)
    user_saveRepositoryCredentials - Saves repository credentials in ugr_Preferences

*/
require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../structure/dbsUsersGroups.php';

$need_compress = false;

$system = new hserv\System();

if(!$system->init(@$_REQUEST['db'])){
    //get error and response
    $response = $system->getError();
}else{

   if(!$system->getUserId()>0){
        $response = $system->addError(HEURIST_REQUEST_DENIED, 'You must be logged in');
        // 'Administrator permissions are required');
   }else{

        //for kml step2,step3,set_primary_rectype,step3
        $action = @$_REQUEST["a"];
        $res = false;
        $ugr_ID = $system->getUserId();

        if($action=='list'){
            //get list of available repositories for given user (including for database and groups)

            //array(ugr_ID, serviceName)
            $res = user_getRepositoryList($system, $ugr_ID, true);

            if(@$_REQUEST['include_test'] == 1 && is_array($res)){ // add test accounts

                // add Nakala testing
                array_push($res, ['tnakala', 'Nakala', 0, 'tnakala'], ['unakala1', 'Nakala', 0, 'unakala1'], ['unakala2', 'Nakala', 0, 'unakala2'], ['unakala3', 'Nakala', 0, 'unakala3'] );
            }

        }elseif($action=='get'){
            //get credentials (to edit on client side) for given user

            $res = user_getRepositoryCredentials($system, true, $ugr_ID);

        }elseif($action=='update'){
            //save credentials
            $to_delete = @$_REQUEST["delete"];
            $to_edit = @$_REQUEST["edit"];

            $res = user_saveRepositoryCredentials($system, $to_edit, $to_delete);

        //}elseif($action=='upload'){
            //upload and register file to external repository


        }else{
            $system->addError(HEURIST_INVALID_REQUEST, "Action parameter is missing or incorrect");
            $res = false;
        }

        if(is_bool($res) && $res==false){
                $response = $system->getError();
        }else{
                $response = array("status"=>HEURIST_OK, "data"=> $res);
        }
   }
}
header(CTYPE_JSON);
print json_encode($response);
?>