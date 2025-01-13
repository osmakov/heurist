<?php
use hserv\entity\DbRecUploadedFiles;
use hserv\entity\DbDefRecTypes;
use hserv\utilities\USanitize;
use hserv\utilities\UImage;
use hserv\structure\ConceptCode;
use hserv\report\ReportRecord;

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* recordModify.php
*
* Library to create/update/delete heurist (user data) records
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*
*
* recordAdd  - create temporary record for given user
* recordSave - Save record
* recordDuplicate - Duplicate record
* recordDelete
*
* isWrongAccessRights - validate parameter values
* recordCanChangeOwnerwhipAndAccess  - Verifies access right value and is the current user able to change ownership for given record
*
* recordUpdateCalcFields
* recordUpdateTitle
* recordUpdateOwnerAccess
* _prepareDetails - validate records detail (need to combine with validators in fileParse)
*
*/
require_once dirname(__FILE__).'/recordTitleMask.php';
require_once dirname(__FILE__).'/../search/recordSearch.php';
require_once dirname(__FILE__).'/../../structure/search/dbsData.php';
require_once dirname(__FILE__).'/../../structure/dbsUsersGroups.php';
require_once dirname(__FILE__).'/../../structure/dbsTerms.php';

require_once dirname(__FILE__).'/../../../hserv/records/indexing/elasticSearch.php';

require_once dirname(__FILE__).'/../../report/smartyInit.php';


global $useNewTemporalFormatInRecDetails;
global $recstructures, $detailtypes, $terms, $block_swf_email;

$recstructures = array();
$detailtypes   = array();
$terms         = null;
$useNewTemporalFormatInRecDetails = false;

$block_swf_email = false;

/**
* Returns default values for rec_NonOwnerVisibility, rec_NonOwnerVisibilityGroups, rec_OwnerUGrpID
*
*/
function recordAddDefaultValues($system, $record=null){

    $sysvals = null;
    $rectype = null;
    $owner_grps = array();
    $ownerid = -1;
    $access = null;
    $access_grps = null;


    //obtain user preferences values
    $addRecDefaults = $system->userGetPreference('record-add-defaults');
    if ($addRecDefaults){
        if (@$addRecDefaults[0]){
            $userDefaultRectype = intval($addRecDefaults[0]);
        }
        if (@$addRecDefaults[1]!=null){ //default ownership
            if(is_string($addRecDefaults[1]) &&  $addRecDefaults[1]!=''){
                $userDefaultOwnerGroupID = explode(',', $addRecDefaults[1]);
            }elseif(is_numeric($addRecDefaults[1])){
                $userDefaultOwnerGroupID = intval($addRecDefaults[1]);
            }
        }
        if (@$addRecDefaults[2]){
            $userDefaultAccess = $addRecDefaults[2];
        }
        if (@$addRecDefaults[4]){
            $userDefaultAccessGroups = $addRecDefaults[4];
        }
    }

    //from record
    if(@$record){
        //it is allowed with prefix rec_ and without
        foreach ($record as $key=>$val){
            if(strpos($key,'rec_')===0){
                $record[substr($key,4)] = $val;
                unset($record[$key]);
            }
        }

        $rectype = @$record['RecTypeID'];
        $access = @$record['NonOwnerVisibility'];
        $access_grps = @$record['NonOwnerVisibilityGroups'];
        $ownerid = (empty(@$record['OwnerUGrpID']) && @$record['OwnerUGrpID']!=0) ? -1 : $record['OwnerUGrpID'];

        if($ownerid == 'current_user'){
            $ownerid = $system->getUserId();
        }else {
            $ownerid = prepareIds($ownerid, true);
        }

        $rectype = ConceptCode::getRecTypeLocalID($rectype);
    }


    // RECTYPE
    $rectype = intval($rectype);
    if(!$rectype && isset($userDefaultRectype)){
        $rectype = $userDefaultRectype;
    }
    // OWNERSHIP
    if(($ownerid == -1 || empty($ownerid)) && isset($userDefaultOwnerGroupID)){ // from user preferences
        $ownerid = is_array($userDefaultOwnerGroupID)?$userDefaultOwnerGroupID:array($userDefaultOwnerGroupID);
    }
    if(!is_array($ownerid) || !($ownerid[0]>=0)){
        if(!$sysvals) {$sysvals = $system->settings->get();}
        $ownerid = @$sysvals['sys_NewRecOwnerGrpID'];//from database properties
    }
    if(!(is_array($ownerid) && !empty($ownerid)) || !($ownerid[0]>=0)){
        $ownerid = $system->getUserId();//by default current user
    }
    if(is_array($ownerid)){
        $owner_grps = $ownerid;
    }elseif($ownerid>=0){
        $owner_grps = array($ownerid);
    }

    // NON OWNER VISIBILITY
    if($access==null && isset($userDefaultAccess)) {//from user prefs
        $access = $userDefaultAccess;
    }
    if(!$access){
        $sysvals = $system->settings->get();
        $access = @$sysvals['sys_NewRecAccess'];//from db properties
    }
    if(!$access){
        $access = 'viewable';// default value
    }
    //access groups
    if($access!='viewable'){
        $access_grps = null;
    }elseif($access_grps==null && isset($userDefaultAccessGroups)){
        $access_grps = $userDefaultAccessGroups;
    }

        return array('rectype'=>$rectype, 'owner_grps'=>$owner_grps, 'access'=>$access, 'access_grps'=>$access_grps );
}

/**
* Creates temporary record for given user
*/
function recordAdd($system, $record, $return_id_only=false){

    // Check that the user is allowed to create records
    $is_allowed = userCheckPermissions($system, 'add');
    if(!$is_allowed){
        return false;
    }

    $mysqli = $system->getMysqli();

    $def_params = recordAddDefaultValues($system, $record);

    $rectype = $def_params['rectype'];
    $owner_grps = $def_params['owner_grps'];
    $access = $def_params['access'];
    $access_grps = $def_params['access_grps'];

    if (!($rectype && dbs_GetRectypeByID($mysqli, $rectype)) ) {
        return $system->addError(HEURIST_INVALID_REQUEST, 'Record type not defined or wrong ('.$rectype.')');
    }

    // for CMS rectypes by default public and owner is Database owners group
    if ($system->defineConstant('RT_CMS_MENU') && $rectype==RT_CMS_MENU)
    {
        $access= 'public';
        $owner_grps = array(1);//database manager group
    }

    //$record['swf'] - ownership is set from swf rules
    if (!(@$record['swf'] || $system->isAdmin() || $system->isMember($owner_grps) || $system->isGuestUser() )){
        $system->addError(HEURIST_REQUEST_DENIED,
            'Current user does not have sufficient authority to add record with default ownership. '
            .'User must be member of the group that will own this record', 'Default ownership: '.implode(',', $owner_grps));
        return false;
    }
    //check that $owner_grps exists
    $usr_exists = mysql__select_value($mysqli, 'SELECT ugr_ID FROM sysUGrps WHERE ugr_ID='.intval($owner_grps[0]));
    if($usr_exists==null){
        $system->addError(HEURIST_REQUEST_DENIED,
'Proposed record ownership for record addition is invalid. Most probably the specified group or user has been deleted, or a non-existent  user or group has been specified.'
.'<br><br>Change the specified ownership  in the record addition link in the custom report or website, or in setup of the workflow (in Design menu).',
'Proposed ownership: '.implode(',', $owner_grps));
        return false;
    }


    if(isWrongAccessRights($system, $access)){
        return $system->getError();
    }


    //ActioN!

    if(is_numeric(@$record['ID']) && @$record['ID']>0){
        //case: insert csv with predefined ID
        $rec_id = $record['ID'];
        $recid1 = 'rec_ID, ';
        $recid2 = '?, ';
    }else{
        $rec_id = 0;
        $recid1 = '';
        $recid2 = '';
    }

    $query = "INSERT INTO Records
    ($recid1 rec_AddedByUGrpID, rec_RecTypeID, rec_OwnerUGrpID, rec_NonOwnerVisibility,"
    ."rec_URL, rec_ScratchPad, rec_Added, rec_Modified, rec_AddedByImport, rec_FlagTemporary, rec_Title) "
    ."VALUES ($recid2 ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $mysqli->prepare($query);

    $currentUserId = $system->getUserId();
    $rec_url  = USanitize::sanitizeURL(@$record['URL']);

    $rec_scr  = @$record['ScratchPad'];
    $rec_imp  = (@$record['AddedByImport']?1:0);
    $rec_temp = (@$record['FlagTemporary']?1:0);
    $rec_title = @$record['Title']==null?'':$record['Title'];

    //DateTime('now')->format(DATE_8601) is same as date(DATE_8601)
    $data_add = date(DATE_8601);

    if(is_numeric(@$record['ID']) && @$record['ID']>0){
        //case: insert csv with predefined ID
        $stmt->bind_param('iiiisssssiis', $rec_id, $currentUserId, $rectype, $owner_grps[0], $access,
            $rec_url, $rec_scr, $data_add, $data_add, $rec_imp, $rec_temp, $rec_title);
    }else{
        $stmt->bind_param('iiisssssiis', $currentUserId, $rectype, $owner_grps[0], $access,
            $rec_url, $rec_scr, $data_add, $data_add, $rec_imp, $rec_temp, $rec_title);
    }
    $stmt->execute();
    $newId = $stmt->insert_id;
    $syserror = $mysqli->error;
    $stmt->close();


    if(!$newId){
    //HEURIST_DB_ERROR
        $response = $system->addError(HEURIST_ACTION_BLOCKED , 'Cannot add record '.$syserror, $syserror);

    }else {

        array_shift( $owner_grps );//remove first
        if($access_grps!=null || (is_array($owner_grps) && !empty($owner_grps))){
            updateUsrRecPermissions($mysqli, $newId, $access_grps, $owner_grps);
        }

        if($return_id_only){

            $response = array("status"=>HEURIST_OK, "data"=> $newId);

        }else{

            $params = array("q"=>"ids:".$newId, 'detail'=>'complete', "w"=>"e");
            //retrieve new record with structure
            $response = recordSearch($system, $params);
        }
    }
    return $response;
}

/**
* Save record
*   1) _prepareDetails
*   2) add or update header
*   3) remove old details, add new details
*   4) recordUpdateCalcFields
*   5) recordUpdateTitle
*
* @param mixed $system
* @param mixed $record
*       [ID:, RecTypeID:, OwnerUGrpID:, NonOwnerVisibility:, AddedByImport:, URL:, FlagTemporary:,
*               details:
*            ]
*    details = array("t:1" => array("bd:234463" => "7th Ave"),
*                      ,,,
*                     "t:11" => array("0" => "p POINT (-73.951172 40.805661)"));
*
*
* @param mixed $update_mode
*   - 0,1 owerwrite current record completely  (Load new values, replacing all existing values for these records/fields)
*   - 2 Add new values without deletion of existing values (duplicates are ignored)
*   - 3 Add new values only if field is empty (new values ignored for non-empty fields)
*   - 4 Replace existing values with new values, retain existing value if no new value supplied
*
* @param int $total_record_count - Count of records to be (or should be) saved, used to avoid sending several emails to users
*
*  Add new values without deletion of existing values (duplicates are ignored)
Load new values, replacing all existing values for these records/fields
Other options
Add new values only if field is empty (new values ignored for non-empty fields)
Replace existing values with new values, retain existing value if no new value supplied
*
* returns
* array("status"=>HEURIST_OK, "data"=> $recID, 'rec_Title'=>$newTitle);
* or
* error array
*
*/
function recordSave($system, $record, $use_transaction=true, $suppress_parent_child=false, $update_mode=0, $total_record_count=1){

    global $block_swf_email, $useNewTemporalFormatInRecDetails;

    //check capture for newsletter subscription
    if (@$record['Captcha'] && @$_SESSION["captcha_code"]){

        $is_InValid = (@$_SESSION["captcha_code"] != @$record['Captcha']);

        if (@$_SESSION["captcha_code"]){
            unset($_SESSION["captcha_code"]);
        }
        if(@$record['Captcha']){
            unset($record['Captcha']);
        }

        if($is_InValid) {
            return $system->addError(HEURIST_ACTION_BLOCKED,
                'Are you a bot? Please enter the correct answer to the challenge question');
        }else{
            if($system->getUserId()<1){ //if captcha is valid allow
                $system->setCurrentUser(array('ugr_ID'=>5, 'ugr_FullName'=>'Guest'));
            }
        }
    }

    // Check that the user is allowed to edit records
    $is_allowed = userCheckPermissions($system, 'edit');
    if(!$is_allowed){
        return false;
    }

    $recID = intval(@$record['ID']);
    if ( @$record['ID']!=='0' && @$record['ID']!==0 && $recID==0 ) {
        return $system->addError(HEURIST_INVALID_REQUEST, "Record ID is not defined");
    }

    $mysqli = $system->getMysqli();

    //it is allowed with prefix rec_ and without
    foreach ($record as $key=>$val){
        if(strpos($key,'rec_')===0){
            $record[substr($key,4)] = $val;
            unset($record[$key]);
        }
    }

    $useNewTemporalFormatInRecDetails = ($system->settings->get('sys_dbSubSubVersion')>=14);


    //0 normal, 1 import, 2 - faims or zotero import (add without recstructure check)
    $modeImport = @$record['AddedByImport']?intval($record['AddedByImport']):0;

    $validation_mode = 2; //check everything

    if(@$record['no_validation']==='ignore_all'){
        $validation_mode = 0; //no validation at all
    }elseif($modeImport==2 || @$record['no_validation']){
        $validation_mode = 1; //don't validate resources
    }

    if(@$record['no_validation']!=null){
        unset($record['no_validation']);
    }

    $rectype = intval(@$record['RecTypeID']);
    $detailValues = null;

    if ($rectype && !dbs_GetRectypeByID($mysqli, $rectype))  {
        return $system->addError(HEURIST_INVALID_REQUEST, "Record type is wrong");
    }

    $is_insert = ($recID<1);
    $is_save_new_record = false;
    $missingParents = [];
    $entryMaskIssues = [];

    // recDetails data
    if ( @$record['details'] ) {

        if(@$record['details_encoded']==1 || @$record['details_encoded']==2){
            //$record['details'] = json_decode(str_replace( ' xxx_style=', ' style=',
            //            str_replace( '^^/', '../', urldecode($record['details']))), true);
            $record['details'] = json_decode(urldecode($record['details']), true);
            $record['details_visibility'] = json_decode(urldecode($record['details_visibility']), true);
        }elseif(@$record['details_encoded']==3){
            $record['details'] = json_decode($record['details'], true);
            $record['details_visibility'] = json_decode($record['details_visibility'], true);
        }

        $detailValues = _prepareDetails($system, $rectype, $record, $validation_mode, $recID, $modeImport);
        if(!$detailValues){
            return $system->getError();
        }

        //prepare header and details for special update modes
        if(!$is_insert && $update_mode>1){ //if 0 or 1 - it overwrites current version of record completely
            $detailValues = prepareRecordForUpdate($system, $record, $detailValues, $update_mode);

            if($update_mode!=1){ //1 - always overwrite
                $record_orig = recordSearchByID($system, $record['ID'], false);
                //keep previous header values if no new value supplied
                if( @$record['URL']==null || @$record['URL']==''
                || (@$record_orig['rec_URL'] && $update_mode==4)) //retain
                {
                    $record['URL'] = @$record_orig['rec_URL'];
                }
                if(@$record['ScratchPad']==null || @$record['ScratchPad']==''
                || (@$record_orig['rec_ScratchPad'] && $update_mode==4))
                {
                    $record['ScratchPad'] = @$record_orig['rec_ScratchPad'];
                }
            }
        }

        if(!$is_insert){
            $missingParents = validateParentRecords($system, $record, $detailValues);
        }

    }  else {
        return $system->addError(HEURIST_INVALID_REQUEST, "Details not defined");
    }



    $system->defineConstant('RT_RELATION');
    $system->defineConstant('DT_PARENT_ENTITY');

    // if source of target of relationship record is temporal - relationship is temporal as well
    if($record['RecTypeID']==RT_RELATION && @$record['FlagTemporary']!=1){
        $system->defineConstant('DT_PRIMARY_RESOURCE');
        $system->defineConstant('DT_TARGET_RESOURCE');

        $recids = array();
        foreach ($detailValues as $values) {
            $dtyID = $values['dtl_DetailTypeID'];
            if(($dtyID==DT_PRIMARY_RESOURCE || $dtyID==DT_TARGET_RESOURCE) && @$values['dtl_Value']){
                $recids[] = $values['dtl_Value'];
            }
        }

        $query = 'SELECT rec_FlagTemporary FROM Records where rec_FlagTemporary=1 AND rec_ID in ('
        .implode(',',$recids).')';
        if(mysql__select_value($mysqli, $query)>0){
            $record['FlagTemporary'] = 1;
        }
    }elseif(!$is_insert) {

        //check if previous FlagTemporary is 1
        if($system->defineConstant('TRM_SWF_ADDED')){
            $query = 'SELECT rec_FlagTemporary FROM Records WHERE rec_ID='.$recID;
            $is_save_new_record = (mysql__select_value($mysqli, $query)==1);
        }

        $record['FlagTemporary'] = 0;
    }

    //workflow stages
    $new_swf_stage = 0;
    $swf_emails = null;
    $stage_field_idx = -1;
    $is_new_record = $is_insert || $is_save_new_record;
    if($record['FlagTemporary']!=1 && $system->defineConstant('DT_WORKFLOW_STAGE')){

        if($modeImport > 0 && $system->defineConstant('TRM_SWF_IMPORT')){
            //hardcoded term id for "import" stage

            $recID = abs(intval(@$record['ID']));
            $existing_swf = mysql__select_value($mysqli, "SELECT dtl_ID FROM recDetails WHERE dtl_RecID = $recID AND dtl_DetailTypeID = " . DT_WORKFLOW_STAGE);

            $new_swf_stage = !$existing_swf ? TRM_SWF_IMPORT : 0;
        }else{
            foreach ($detailValues as $idx=>$values) {
                if($values['dtl_DetailTypeID']==DT_WORKFLOW_STAGE){
                    $stage_field_idx = $idx;
                    $new_swf_stage = @$values['dtl_Value'];
                    break;
                }
            }
            if($is_save_new_record && !($new_swf_stage>0)){
                $new_swf_stage = TRM_SWF_ADDED;
            }
        }
        if($new_swf_stage>0){
            // set $record onwership and visibility
            // and assign $record['swf'] = true, to avoid recordCanChangeOwnerwhipAndAccess
            // returns array( new_value, curr_value, emails )
            $swf_res = recordWorkFlowStage($system, $record, $new_swf_stage, $is_new_record);

            $new_swf_stage = @$swf_res['new_value'];
            if($new_swf_stage==0){ //not allowed - keep old stage
                if($stage_field_idx>=0 && @$swf_res['curr_value']>0){
                    $detailValues[$stage_field_idx]['dtl_Value'] = $swf_res['curr_value'];
                }
            }else{
                $swf_emails = @$swf_res['emails'];
                if($stage_field_idx<0){
                    array_push($detailValues,array('dtl_DetailTypeID'=>DT_WORKFLOW_STAGE, 'dtl_Value'=>$new_swf_stage));
                }
            }
        }
    }

    if($is_insert){   // ADD NEW RECORD

        //add with predifined id - this is is case happens only in import csv
        //to keep H-ID defined in source csv
        if($recID<0){
            $record['ID'] = abs($recID);
        }

        // start transaction
        if($use_transaction){
            $keep_autocommit = mysql__begin_transaction($mysqli);
        }

        $response = recordAdd($system, $record, true);
        if($response['status'] == HEURIST_OK){
            $recID = intval($response['data']);
        }else{
            if($use_transaction){
                $mysqli->rollback();
                if($keep_autocommit===true) {$mysqli->autocommit(true);}
            }
            return $response;
        }

    }else{  //UPDATE EXISTING ONE

        $owner_grps = prepareIds(@$record['OwnerUGrpID'], true);//list of owner groups

        $access = @$record['NonOwnerVisibility'];
        $rectypes = array();

        if(!@$record['swf'] && !recordCanChangeOwnerwhipAndAccess($system, $recID, $owner_grps, $access, $rectypes)){
            return $system->getError();
        }

        // start transaction
        if($use_transaction){
            $keep_autocommit = mysql__begin_transaction($mysqli);
        }

        if(!$modeImport) {
            mysql__supress_trigger($mysqli, true);
        }

        $query = 'UPDATE Records set rec_Modified=?, rec_RecTypeID=?, rec_OwnerUGrpID=?, rec_NonOwnerVisibility=?,rec_FlagTemporary=? ';

        $rec_mod = date(DATE_8601);
        $rec_temp = (@$record['FlagTemporary']==1)?1:0;

        //$stmt->bind_param('siisssi', $rec_mod, $rectype, $owner_grps[0], $access, $rec_temp, $rec_url, $rec_spad);

        $params = array('siisi', $rec_mod, $rectype, $owner_grps[0], $access, $rec_temp);

        $rec_url = USanitize::sanitizeURL(@$record['URL']);
        if($rec_url || (array_key_exists('URL', $record) && $update_mode < 2)){
            $params[0] = $params[0].'s';
            $params[] = $rec_url;
            $query = $query.', rec_URL=?';
        }
        $rec_spad = @$record['ScratchPad'];
        if($rec_spad || (array_key_exists('ScratchPad', $record) && $update_mode < 2)){
            $params[0] = $params[0].'s';
            $params[] = $rec_spad;
            $query = $query.', rec_ScratchPad=?';
        }

        $query = $query.' where rec_ID='.$recID;

        $stmt = $mysqli->prepare($query);

        //Call the $stmt->bind_param() method with atrguments (string $types, mixed &...$vars)
        call_user_func_array(array($stmt, 'bind_param'), referenceValues($params));

        if(!$stmt->execute()){
            $syserror = $mysqli->error;
            $stmt->close();
            if($use_transaction){
                $mysqli->rollback();
                if($keep_autocommit===true) {$mysqli->autocommit(true);}
            }
            return $system->addError(HEURIST_DB_ERROR, 'Cannot save record', $syserror);
        }
        $stmt->close();

        //update group view and edit permissions
        $access_grps = ($access=='viewable')?@$record['NonOwnerVisibilityGroups']:null;
        array_shift($owner_grps);//remove first
        updateUsrRecPermissions($mysqli, $recID, $access_grps, $owner_grps);

        if(!$modeImport){
            if($system->getUserId()>0){
                //set current user for stored procedures (log purposes)
                $mysqli->query('set @logged_in_user_id = '.$system->getUserId());
            }
            mysql__supress_trigger($mysqli, false);
        }

        //delete ALL existing details
        $query = "DELETE FROM recDetails where dtl_RecID=".$recID;
        if(!$mysqli->query($query)){
            $syserror = $mysqli->error;
            if($use_transaction){
                $mysqli->rollback();
                if($keep_autocommit===true) {$mysqli->autocommit(true);}
            }
            return $system->addError(HEURIST_DB_ERROR, 'Cannot delete old details', $syserror);
        }
    }
    //END HEADER SAVE

    //ADD DETAILS
    $addedByImport = ($modeImport?1:0);


    $query = 'INSERT INTO recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport, dtl_UploadedFileID, dtl_Geo, dtl_HideFromPublic) '.
    "VALUES ($recID, ?, ?, $addedByImport, ?, ST_GeomFromText(?), ?)";
    $stmt = $mysqli->prepare($query);

    /* $query_geo = "INSERT INTO recDetails ".
    "(dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport, dtl_Geo) ".
    "VALUES ($recID, ?, ?, $addedByImport, ST_GeomFromText(?) )";
    $stmt_geo = $mysqli->prepare($query2);*/

    //


    if ($stmt) {

        // $stmt->bind_param('isis', $dtyID, $dtl_Value, $dtl_UploadedFileID, $dtl_Geo);
        foreach ($detailValues as $idx=>$values) {

            $dtyID = $values['dtl_DetailTypeID'];
            $dtl_Value = @$values['dtl_Value'];
            if($dtl_Value) {$dtl_Value = super_trim($dtl_Value);}//including &nbsp; and &xef; (BOM)
            $dtl_UploadedFileID = @$values['dtl_UploadedFileID'];
            $dtl_Geo = @$values['dtl_Geo'];
            $dtl_HideFromPublic = @$values['dtl_HideFromPublic'];

            $stmt->bind_param('isisi', $dtyID, $dtl_Value, $dtl_UploadedFileID, $dtl_Geo, $dtl_HideFromPublic);
            if(!$stmt->execute()){
                $syserror = $mysqli->error;
                if($use_transaction){
                    $mysqli->rollback();
                    if($keep_autocommit===true) {$mysqli->autocommit(true);}
                }

                return $system->addError(HEURIST_DB_ERROR, 'Cannot save value - possibly bad encoding or invalid date format (System error: '.$syserror.').', $syserror);

            }

            //add reverce field "Parent Entity" (#247) in child resource record
            if(defined('DT_PARENT_ENTITY') && !$suppress_parent_child){
                if(@$values['dtl_ParentChild']==true){

                    // $dtl_Value  is id of child record
                    $res = addReverseChildToParentPointer($mysqli, $dtl_Value, $recID, $addedByImport, false);

                    if($res<0){
                        $syserror = $mysqli->error;
                        if($use_transaction){
                            $mysqli->rollback();
                            if($keep_autocommit===true) {$mysqli->autocommit(true);}
                        }
                        return $system->addError(HEURIST_DB_ERROR,
                            'Cannot save value. Cannot insert reverse pointer for child record', $syserror);
                    }elseif($res!=0){
                        //update record title for child record
                        list($child_rectype, $child_title) = mysql__select_row($mysqli,
                            'SELECT rec_RecTypeID, rec_Title FROM Records WHERE rec_ID='
                            .intval($dtl_Value));
                        recordUpdateTitle($system, $dtl_Value, $child_rectype, $child_title);
                    }

                }elseif($dtyID == DT_PARENT_ENTITY){

                    $res = addParentToChildPointer($mysqli, $recID, $rectype, $dtl_Value, null, $addedByImport);
                    if($res<0){
                        $syserror = $mysqli->error;
                        if($use_transaction){
                            $mysqli->rollback();
                            if($keep_autocommit===true) {$mysqli->autocommit(true);}
                        }
                        return $system->addError(HEURIST_DB_ERROR,
                            'Cannot save value. Cannot insert pointer for parent record', $syserror);
                    }elseif($res!=0){
                        //update record title for parent record
                        list($parent_rectype, $parent_title) = mysql__select_row($mysqli,
                            'SELECT rec_RecTypeID, rec_Title FROM Records WHERE rec_ID='
                            .intval($dtl_Value));
                        recordUpdateTitle($system, $dtl_Value, $parent_rectype, $parent_title);
                    }

                }
            }

        }
        $stmt->close();
        //$stmt_geo->close();

    }else{
        $syserror = $mysqli->error;
        if($use_transaction){
            $mysqli->rollback();
            if($keep_autocommit===true) {$mysqli->autocommit(true);}
        }
        return $system->addError(HEURIST_DB_ERROR, 'Cannot save details(3)', $syserror);
    }

    $newTitle = recordUpdateTitle($system, $recID, $rectype, @$record['Title']);
    $rty_counts = null;

    if(!$is_insert && !$modeImport)
    {
        mysql__supress_trigger($mysqli, true);

        recordUpdateCalcFields( $system, $recID, $rectype );//update calculated fields in this record

        $entryMaskIssues = recordUpdateMaskFields($system, $recID, $rectype);

        //check that this record my affect other records with calculated fields
        //1. cfn_RecTypeIDs -> cfn_ID
        //2. defRecStructure where rst_CalcFunctionID  -> rst_RecTypeID+rst_DetailTypeID
        //it may consume waste of time findAndUpdateAffectedCalcFields( $system, $rectype )

        removeReverseChildToParentPointer($system, $recID, $rectype);

        //find all relationship records and update FlagTemporary and record title
        $relRecsIDs = array();

        //@todo - rollback in case of error
        $mask = mysql__select_value($mysqli,"select rty_TitleMask from defRecTypes where rty_ID=".RT_RELATION);

        $relRecs = recordGetRelationship($system, $recID, null, array('detail'=>'ids'));
        if(!isEmptyArray($relRecs)){
            $relRecsIDs = $relRecs;
        }
        $relRecs = recordGetRelationship($system, null, $recID, array('detail'=>'ids'));
        if(!isEmptyArray($relRecs)){
            $relRecsIDs = array_merge($relRecsIDs, $relRecs);
        }
        //reset temporary flag for all relationship records
        if(!isEmptyArray($relRecsIDs)){
            foreach($relRecsIDs as $relID){
                $res = recordUpdateTitle($system, $relID, $mask, 'Title Mask for Relationship not defined');
            }
            $query = 'UPDATE Records set rec_FlagTemporary=0 where rec_ID in ('.implode(',',$relRecsIDs).')';
            $res = $mysqli->query($query);
        }

        //recordGetLinkedRecords - get all linked and related records and update them
        $links = recordGetLinkedRecords($system, $recID);
        if(!isEmptyArray($links)){
            //find title masks
            $links_rectypes = array_unique(array_values($links));
            $masks = mysql__select_assoc2($mysqli,'select rty_ID, rty_TitleMask from defRecTypes where rty_ID in ('
                .implode(',',$links_rectypes) .')');

            foreach($links as $linkRecID=>$linkRecTypeID){
                $res = recordUpdateTitle($system, $linkRecID, $masks[$linkRecTypeID], null);
            }
        }
        mysql__supress_trigger($mysqli, false);

    }//update flagtemporary and title for related,linked records

    //calculate counts
    //$rty = new DbDefRecTypes($system,array('mode'=>'record_count', 'rty_ID'=>array(1, $rectype)));
    //$rty_counts = $rty->counts();

    if($use_transaction){
        $mysqli->commit();
        if($keep_autocommit===true) {$mysqli->autocommit(true);}
    }

    //send notification email
    if($swf_emails!=null && !$block_swf_email){

        $stage_name = mysql__select_value($mysqli, 'select trm_Label from defTerms where trm_ID='.$new_swf_stage);
        $user = $system->getCurrentUser();
        $user = @$user['ugr_FullName'];

        $title = HEURIST_DBNAME . ", ID: $recID >> workflow: $stage_name";
        $msg = '<b>'.$title.'</b> '
        .'<a href="'.HEURIST_BASE_URL.'hclient/framecontent/recordEdit.php?db='.HEURIST_DBNAME.'&recID='.$recID.'">Record #'.$recID
        .'  "'.USanitize::sanitizeString($newTitle, false).'"</a><br>'
        .' has been changed to "'.$stage_name
        .'"<br><br> by user: '.($user?$user:$system->getUserId());

        if($total_record_count > 1){
            $msg = $msg . '<br><br><i>This is the first of multiple records'. ($modeImport > 0 ? ' imported' : '') .'. Please visit database for additional records.</i>';
        }

        $res = sendPHPMailer(HEURIST_MAIL_TO_ADMIN, 'Heurist DB '.HEURIST_DBNAME.'. ID: '.$recID, //'Workflow stage update notification',
                    $swf_emails, $title, $msg, null, true);

        if($total_record_count > 1 && $res){ // block further emails for imports, only if the email was sent
            $block_swf_email = true;
        }
    }

    $rtn = [
        'status' => HEURIST_OK,
        'data' => intval($recID),
        'rec_Title' => $newTitle,
        'affectedRty' =>$rectype,
        'issues' => []
    ];

    if(!empty($missingParents)){
        $rtn['issues']['parents'] = $missingParents;
    }
    if(!empty($entryMaskIssues)){
        $rtn['issues']['entryMask'] = $entryMaskIssues;
    }

    return $rtn;
    //, 'counts'=>$rty_counts
    /*
    $response = array("status"=>HEURIST_OK,
    "data"=> array(
    "count"=>$num_rows,
    "fields"=>$fields,
    "records"=>$records,
    "rectypes"=>$rectypes,
    "structures"=>$rectype_structures));
    */
}//recordSave


/**
* removes heurist record and all dependent entries
* (note heurist record will be kept in sysArchive)
*
* @param mixed $system
* @param mixed $recids
* @param mixed $need_transaction - false when record are removed for user/group/rectype deletion
* @param mixed $check_source_links - prevents action if there are target records that points to given record
*/
function recordDelete($system, $recids, $need_transaction=true,
    $check_source_links=false, $filterByRectype=0, $progress_session_id=null){

    // Check that the user is allowed to delete records
    $is_allowed = userCheckPermissions($system, 'delete');
    if($is_allowed !== true){
        return $is_allowed;
    }

    $recids = prepareIds($recids);
    if(!empty($recids)){

        if(count($recids)>100){
            ini_set('max_execution_time', '0');
        }


        /*narrow by record type
        $rec_RecTypeID = @$params['rec_RecTypeID'];
        if($rec_RecTypeID>0){
        $recids = mysql__select_list2($mysqli, 'SELECT rec_ID from Records where rec_ID in ('
        .implode(',', $recids).') and rec_RecTypeID='. $rec_RecTypeID);

        if($recids==null || empty($recids)){
        $this->system->addError(HEURIST_NOT_FOUND, 'No record found for provided record type');
        return false;
        }
        }*/

        $rectypes = array();
        $noaccess_count = 0;
        $allowed_recids = array();

        //check permission
        foreach ($recids as $recID) {
            $ownerid = null;
            $access = null;
            $is_allowed = recordCanChangeOwnerwhipAndAccess($system, $recID, $ownerid, $access, $rectypes);
            if( (!($filterByRectype>0)) || ($rectypes[$recID]==$filterByRectype)) {
                if($is_allowed){
                    array_push($allowed_recids, $recID);
                }else{
                    $noaccess_count++;
                }
            }
        }
        if(count($recids)==1 && $noaccess_count==1){
            return $system->getError();
            //}elseif(count($recids)==$noaccess_count){
        }else{
            $system->clearError();
        }

        //find reverse links to given set of ids
        if($check_source_links && !empty($allowed_recids)){
            $links = recordSearchRelated($system, $allowed_recids, -1, 'ids', 1);

            if($links['status']==HEURIST_OK && @$links['data']['reverse']!=null
                && !isEmptyArray(@$links['data']['reverse'])){
                return array('status'=>HEURIST_OK,
                    'data'=> array( 'source_links_count'=>count($links['data']['reverse']),
                        'source_links'=>implode(',',$links['data']['reverse']) ));
            }
        }

        $is_error = false;
        $mysqli = $system->getMysqli();
        if($need_transaction){
            $keep_autocommit = mysql__begin_transaction($mysqli);
        }

        $bkmk_count = 0;
        $rels_count = 0;
        $deleted = array();
        $affected_rectypes = array();
        $msg_error = '';
        $msg_termination = null;

        $system->defineConstant('RT_RELATION');

        if($system->getUserId()>0){
            //set current user for stored procedures (log purposes)
            $mysqli->query('set @logged_in_user_id = '.$system->getUserId());
        }
        mysql__supress_trigger($mysqli, false);

        $tot_count = count($allowed_recids);

        if($progress_session_id){
            //init progress session
            mysql__update_progress(null, $progress_session_id, true, '0,'.$tot_count);
        }

        foreach ($allowed_recids as $recID) {
            //$stat = array('deleted'=>array($recID), 'rels_count'=>0, 'bkmk_count'=>0);
            $stat = deleteOneRecord($system, $recID, $rectypes[$recID]);

            if( array_key_exists('error', $stat) ){
                $msg_error = $stat['error'];
                break;
            }else{
                $deleted = array_merge($deleted, $stat['deleted']);
                $rels_count += $stat['rels_count'];
                $bkmk_count += $stat['bkmk_count'];

                if(!in_array($rectypes[$recID],$affected_rectypes)){
                    array_push($affected_rectypes, $rectypes[$recID]);
                }
            }

            //update session and check for termination
            if($progress_session_id && (count($deleted) % 10 == 0)){
                $session_val = count($deleted).','.$tot_count;
                $current_val = mysql__update_progress(null, $progress_session_id, false, $session_val);
                if($current_val && $current_val=='terminate'){
                    $msg_termination = 'Deletion is terminated by user';
                    break;
                }
            }
        }//foreach

        if($progress_session_id){
            //remove session file
            mysql__update_progress(null, $progress_session_id, false, 'REMOVE');
        }

        if($msg_termination){
            $res = $system->addError(HEURIST_ACTION_BLOCKED, $msg_termination);
        }elseif($msg_error){
            $res = $system->addError(HEURIST_DB_ERROR, 'Cannot delete record. '.$msg_error);
        }else{
            $res = array('status'=>HEURIST_OK,
                'affectedRty'=>$affected_rectypes,
                'data'=> array( 'processed'=>count($allowed_recids),
                    'deleted'=>count($deleted), 'noaccess'=>$noaccess_count,
                    'bkmk_count'=>$bkmk_count, 'rels_count'=>$rels_count));
        }

        if($need_transaction){
            mysql__end_transaction($mysqli, !($msg_termination || $msg_error), $keep_autocommit);
        }
        return $res;

    }else{
        return $system->addError(HEURIST_INVALID_REQUEST, 'Record IDs not defined');
    }
}

/**
* get incremeneted value for given field
*
* @param mixed $system
* @param mixed $params
*/
function recordGetIncrementedValue($system, $params){


    $rt_ID = intval(@$params["rtyID"]);
    $dt_ID = intval(@$params["dtyID"]);

    if($rt_ID>0 && $dt_ID>0){

        $mysqli = $system->getMysqli();

        //1. get detail type
        $res = mysql__select_list($mysqli, 'defDetailTypes','dty_Type','dty_ID='.$dt_ID);
        if(!isEmptyArray($res)){
            $isNumeric = ($res[0]!='freetext');

            //2. get max value for numeric and last value for non numeric
            if($isNumeric){
                $res = mysql__select_value($mysqli, 'select max(CAST(dtl_Value as SIGNED)) FROM recDetails, Records'
                    ." WHERE dtl_RecID=rec_ID and rec_RecTypeID=$rt_ID and dtl_DetailTypeID=$dt_ID");
            }else{
                $res = mysql__select_value($mysqli, 'select dtl_Value FROM recDetails, Records'
                    ." WHERE dtl_RecID=rec_ID and rec_RecTypeID=$rt_ID and dtl_DetailTypeID=$dt_ID"
                    .' ORDER BY rec_ID DESC LIMIT 1');
            }

            $value = 1;

            if($res!=null){

                if($isNumeric){
                    $value = 1 + intval($res);
                }else{
                    //find digits at the end of string
                    $value = $res;
                    $matches = array();
                    if (preg_match('/(\d+)$/', $value, $matches)){
                        $digits = $matches[1];
                        $increment_digit = str_pad(intval($digits) + 1, strlen($digits), '0', STR_PAD_LEFT);

                        $value = substr($value,0,-strlen($digits)).($increment_digit);

                    }else{
                        $value = $value.'1';
                    }
                }
            }

            return array("status"=>HEURIST_OK, 'result'=>$value);
        }else{
            return $system->addError(HEURIST_INVALID_REQUEST, 'Get incremented value. Detail type '.$dt_ID.' not found');
        }
    }else{
        return $system->addError(HEURIST_INVALID_REQUEST, 'Get incremented value. Parameters are wrong or undefined');
    }

}

/**
* get all incremeneted value for given record type
*
* @param mixed $system
* @param mixed $params
*/
function recordGetAllIncremenetedValues($system, $params){

    $rty_ID = intval(@$params['rtyID']);
    $ignore_dtys = @$params['ignore_dtys'];

    if(!($rty_ID > 0)){
        return $system->addError(HEURIST_INVALID_REQUEST, 'Get all ] incremented values. Record type is missing');
    }

    $ret = array();

        if(!empty($ignore_dtys) && !is_array($ignore_dtys)){
            $ignore_dtys = explode(',', $ignore_dtys);
        }
        if(!empty($ignore_dtys)){
            $ignore_dtys = array_filter($ignore_dtys, function($id){
                return intval($id) > 0;
            });
        }

        $mysqli = $system->getMysqli();
        $rst_dty_filter = '';

        if(!empty($ignore_dtys)){
            $rst_dty_filter = 'AND rst_DetailTypeID ' . (count($ignore_dtys) > 1 ? ' NOT IN ('. implode(',', $ignore_dtys) .')' : ' != ' . $ignore_dtys[0]);
        }

        $query = "SELECT rst_DetailTypeID FROM defRecStructure WHERE rst_DefaultValue = 'increment_new_values_by_1' AND rst_RecTypeID = $rty_ID $rst_dty_filter";

        $dty_IDs = mysql__select_list2($mysqli, $query);

        foreach ($dty_IDs as $dty_ID) {

            $result = recordGetIncrementedValue($system, array('rtyID' => $rty_ID, 'dtyID' => $dty_ID));

            if($result['status'] === HEURIST_OK){
                $ret[$dty_ID] = $result['result'];
            }
        }

    return $ret;
}

/**
* update ownership and access for set of records
* $params
*     ids - array of record ids
*     OwnerUGrpID - new ownership
*     NonOwnerVisibility - access rights
*/
function recordUpdateOwnerAccess($system, $params){

    $recids = @$params['ids'];

    $recids = prepareIds($recids);
    if(!empty($recids)){

        if(@$params['OwnerUGrpID']=='current_user'){
            $params['OwnerUGrpID'] = $system->getUserId();
        }

        $owner_grps = prepareIds( @$params['OwnerUGrpID'], true);
        $access = @$params['NonOwnerVisibility'];

        if((isEmptyArray($owner_grps) || $access==null) && !$system->isAdmin()){
            return $system->addError(HEURIST_INVALID_REQUEST, 'Neither owner nor visibility parameters defined');
        }

        $mysqli = $system->getMysqli();

        //narrow by record type
        $rec_RecTypeID = @$params['rec_RecTypeID'];
        if($rec_RecTypeID>0){
            $recids = mysql__select_list2($mysqli, 'SELECT rec_ID from Records where rec_ID in ('
                .implode(',', $recids).') and rec_RecTypeID='. $rec_RecTypeID);
            $recids = prepareIds($recids);//for snyk
            if(isEmptyArray($recids)){
                return $system->addError(HEURIST_NOT_FOUND, 'No record found for provided record type');
            }
        }

        $rectypes = array();//stub param for recordCanChangeOwnerwhipAndAccess

        $noaccess_count = 0;

        $allowed_recids = array();

        $msg_termination = null;
        $tot_count = count($recids);
        $processed = 0;
        $progress_session_id = @$params['session'];

        if($system->isAdmin())  //admin can change everything
        {

            $allowed_recids = $recids;
        }else{

            if($progress_session_id){
                //init progress session
                mysql__update_progress(null, $progress_session_id, true, '0,'.$tot_count);
            }

            foreach ($recids as $recID) {
                if(!recordCanChangeOwnerwhipAndAccess($system, $recID, $owner_grps, $access, $rectypes)){
                    $noaccess_count++;
                }else{
                    array_push($allowed_recids, $recID);
                }
                $processed++;
                //update session and check for termination
                if($progress_session_id && ($processed % 1000 == 0)){
                    $session_val = $processed.','.$tot_count;
                    $current_val = mysql__update_progress(null, $progress_session_id, false, $session_val);
                    if($current_val && $current_val=='terminate'){
                        $msg_termination = 'Operation is terminated by user';
                        break;
                    }
                }


            }//foreach

        }//not admin

        $cnt_allowed_recids = count($allowed_recids);

        if($cnt_allowed_recids==0 && $progress_session_id){
            //remove session file
            mysql__update_progress(null, $progress_session_id, false, 'REMOVE');
            if($msg_termination){
                return $system->addError(HEURIST_ACTION_BLOCKED, $msg_termination);
            }
        }

        if(count($recids)==1 && $noaccess_count==1){
            return $system->getError();
        }elseif($cnt_allowed_recids==0) {
            return $system->addError(HEURIST_REQUEST_DENIED,
                'User does not have sufficient authority to change ownership and access for any of '.count($recids).' selected record');
        }else{
            $system->clearError();
        }


        // start transaction
        $keep_autocommit = mysql__begin_transaction($mysqli);

        $msg_termination = null;
        $tot_count = $cnt_allowed_recids;

        $rec_mod = date(DATE_8601);
        $main_owner = null;
        if(!empty($owner_grps)){
            $main_owner = $owner_grps[0];
            array_shift( $owner_grps );//other owners
        }
        $access_grps = @$params['NonOwnerVisibilityGroups'];
        $success = true;
        $updated_count = 0;

        //update by chunks
        $k = 0;

        // Setup base query
        $fields = ['rec_Modified=?'];
        $data = [$rec_mod];
        $types = 's';
        if(!empty($main_owner)){
            $fields[] = 'rec_OwnerUGrpID=?';
            $data[] = $main_owner;
            $types .= 'i';
        }
        if(!empty($access)){
            $fields[] = 'rec_NonOwnerVisibility=?';
            $data[] = $access;
            $types .= 's';
        }
        $base_query = 'UPDATE Records set ' . implode(', ', $fields);

        while ($k < $cnt_allowed_recids) {

            if($progress_session_id && $cnt_allowed_recids>5000){

                $session_val = $k.','.$cnt_allowed_recids;
                $current_val = mysql__update_progress(null, $progress_session_id, false, $session_val);
                if($current_val && $current_val=='terminate'){
                    $success = false;
                    $msg_termination = 'Operation is terminated by user';
                    break;
                }
            }

            $chunk = array_slice($allowed_recids, $k, 5000);

            $query = $base_query . ' where rec_ID in ('.implode(',', $chunk).')';

            $stmt = $mysqli->prepare($query);

            $stmt->bind_param($types, ...$data);

            if(!$stmt->execute()){
                $syserror = $mysqli->error;
                $stmt->close();
                $system->addError(HEURIST_DB_ERROR, 'Cannot updated ownership and access', $syserror);
                $success = false;
                break;
            }else{
                $updated_count = $updated_count + $mysqli->affected_rows;
                $stmt->close();

                updateUsrRecPermissions($mysqli, $chunk, $access_grps, $owner_grps);
            }

            $k = $k + 5000;
        }//while

        if($progress_session_id && $cnt_allowed_recids>5000){
            mysql__update_progress(null, $progress_session_id, false, 'REMOVE');
        }

        //
        // commit ot rollback
        //
        if($success){
            $mysqli->commit();

            $res = array("status"=>HEURIST_OK,
                "data"=> array('processed'=>$cnt_allowed_recids,
                    'updated'=>$updated_count,
                    'noaccess'=>$noaccess_count));

        }else{
            $mysqli->rollback();

            if($msg_termination){
                $system->addError(HEURIST_ACTION_BLOCKED, $msg_termination);
            }
            $res = false;
        }


        //restore
        if($keep_autocommit===true) {$mysqli->autocommit(true);}
        return $res;


    }else{
        return $system->addError(HEURIST_INVALID_REQUEST, 'Record IDs not defined');
    }

}

/*
returns

$res = array("error" => $msg_error.'  '.$mysqli->error);
OR
$res = array("deleted"=>$deleted, "bkmk_count"=>$bkmk_count, "rels_count"=>$rels_count);
*/
function deleteOneRecord($system, $id, $rectype){


    $id = intval($id);
    $rectype = intval($rectype);

    if(!($id>0)){
        return array("error" => errorWrongParam('Record id'));
    }

    $bkmk_count = 0;
    $rels_count = 0;
    $deleted = array();//ids of deleted records
    $msg_error = '';
    $mysqli = $system->getMysqli();

    //get list if child records
    $query = 'SELECT dtl_Value FROM recDetails, defRecStructure '
    ." WHERE dtl_RecID=$id AND dtl_DetailTypeID=rst_DetailTypeID AND rst_CreateChildIfRecPtr=1 AND rst_RecTypeID=$rectype";

    $child_records = mysql__select_list2($mysqli, $query);
    if(is_array($child_records) && !empty($child_records)){
        $query = 'SELECT rec_ID, rec_RecTypeID FROM Records WHERE '.predicateId('rec_ID',$child_records);
        $child_records = mysql__select_assoc2($mysqli, $query);
    }

    //find target records where resource (record pointer) field points to record to be deleted
    $links = recordSearchRelated($system, array($id), -1, false, 1);
    if($links['status']==HEURIST_OK && count(@$links['data']['reverse'])>0){
        $links = $links['data']['reverse'];
    }else{
        $links = null;
    }

    while(true){
        mysql__foreign_check($mysqli, false);

        $id = intval($id);
        //
        $mysqli->query('delete from recDetails where dtl_RecID = ' . $id);
        if ($mysqli->error) {break;}

        //
        $mysqli->query('delete from Records where rec_ID = ' . $id);
        if ($mysqli->error) {break;}
        array_push($deleted, $id);

        //remove pointer fields
        if($links){
            foreach ($links as $relation) {
                $mysqli->query('delete from recDetails where dtl_RecID = ' . intval($relation->sourceID)
                    .' and dtl_DetailTypeID = '.intval($relation->dtID).' and dtl_Value='.$id);
                if ($mysqli->error) {break;}
            }
        }

        ElasticSearch::deleteRecordIndexEntry(HEURIST_DBNAME, $rectype, $id);

        $mysqli->query('delete from usrReminders where rem_RecID = ' . $id);
        if ($mysqli->error) {break;}

        $mysqli->query('delete from usrRecPermissions where rcp_RecID = ' . $id);
        if ($mysqli->error) {break;}

        $mysqli->query('delete from recForwarding where rfw_NewRecID = ' . $id);
        if ($mysqli->error) {break;}

        $mysqli->query('delete from usrRecTagLinks where rtl_RecID = ' . $id);
        if ($mysqli->error) {break;}

        $mysqli->query('delete from recThreadedComments where cmt_RecID = ' . $id);
        if ($mysqli->error) {break;}


        //change all woots with title bookmark: to user:
        $mysqli->query('update woots set woot_Title="user:" where woot_Title in (select concat("boomark:",bkm_ID) as title from usrBookmarks where bkm_recID = ' . $id.')');
        if ($mysqli->error) {break;}


        $mysqli->query('delete from usrBookmarks where bkm_recID = ' . $id);
        if ($mysqli->error) {break;}
        $bkmk_count = $bkmk_count + $mysqli->affected_rows;

        //delete from woot
        $mysqli->query('delete from woot_ChunkPermissions where wprm_ChunkID in '.
            '(SELECT chunk_ID FROM woots, woot_Chunks where chunk_WootID=woot_ID and woot_Title="record:'.$id.'")');
        if ($mysqli->error) {break;}

        $mysqli->query('delete from woot_Chunks where chunk_WootID in '.
            '(SELECT woot_ID FROM woots where woot_Title="record:'.$id.'")');
        if ($mysqli->error) {break;}

        $mysqli->query('delete from woot_RecPermissions where wrprm_WootID in '.
            '(SELECT woot_ID FROM woots where woot_Title="record:'.$id.'")');
        if ($mysqli->error) {break;}

        $mysqli->query('delete from woots where woot_Title="record:'.$id.'"');
        if ($mysqli->error) {break;}

        mysql__foreign_check($mysqli, true);

        //remove special kind of record - relationship
        $refs_res = $mysqli->query('select rec_ID from recDetails left join defDetailTypes on dty_ID=dtl_DetailTypeID left join Records on rec_ID=dtl_RecID where dty_Type="resource" and dtl_Value='.$id.' and rec_RecTypeID='.RT_RELATION);
        if($refs_res){
            while ($row = $refs_res->fetch_assoc()) {
                $res = deleteOneRecord($system, $row['rec_ID'], RT_RELATION);
                if( array_key_exists('error', $res) ){
                    $msg_error = $res['error'];
                    break;
                }else{
                    $deleted = array_merge($deleted, $res['deleted']);
                    $rels_count += $res['rels_count'];
                    $bkmk_count += $res['bkmk_count'];
                }
            }
            $refs_res->close();
        } else {
            $msg_error = 'Cannot get relationship records';
            break;
        }


        if(!isEmptyArray($child_records)){
            foreach ($child_records as $recid => $rectypeid) {
                $res = deleteOneRecord($system, $recid, $rectypeid);
                if( array_key_exists('error', $res) ){
                    $msg_error = 'Cannot delete child records'.$res['error'];
                    break;
                }else{
                    $deleted = array_merge($deleted, $res['deleted']);
                    $rels_count += $res['rels_count'];
                    $bkmk_count += $res['bkmk_count'];
                }
            }
        }
        break;
    }//while

    if($mysqli->error || $msg_error){
        $res = array("error" => $msg_error.'  '.$mysqli->error);
    }else{
        $res = array("deleted"=>$deleted, "bkmk_count"=>$bkmk_count, "rels_count"=>$rels_count);
    }
    mysql__foreign_check($mysqli, true);
    return $res;
}

//
// add/update reverse pointer detail field in child record
// return -1 - error, 0 - nothing done, 1 - insert, 2 - update(change parent)
//
// $allow_multi_parent - if true means that there can be many parents for child, if true - insert only
function addReverseChildToParentPointer($mysqli, $child_id, $parent_id, $addedByImport=0, $allow_multi_parent=false){

    if(!defined('DT_PARENT_ENTITY')){
        return 0;
    }

    $res = 0;

        $child_id  = intval($child_id);
        $dtl_ID = -1;

        $query = "SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_RecID=$child_id AND dtl_DetailTypeID=".DT_PARENT_ENTITY;
        $res = $mysqli->query($query);
        if ($res){
            $matches = array();
            while ($row = $res->fetch_row()){
                if($parent_id == $row[1]){
                    return 0; //exactly the same already exists
                }
                $dtl_ID = $row[0];
            }
            $res->close();
            $res = ($dtl_ID>0)?2:1;
        }

        $parent_id = intval($parent_id);

        if($dtl_ID>0 && !$allow_multi_parent){ //pointer already exists
            $mysqli->query('UPDATE recDetails '.
                'SET dtl_Value='.$parent_id.' WHERE dtl_ID='.intval($dtl_ID));

            if($mysqli->error) {$res = -1; }
            return $res;
        }

        $mysqli->query('INSERT INTO recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport) '.
                "VALUES ($child_id, ".DT_PARENT_ENTITY.", $parent_id, $addedByImport )");
        if(!($mysqli->insert_id>0)) {$res=-1;}
        return $res;
}


//
// remove reverse pointer detail field from child record in case there is not direct pointer to child record
//
function removeReverseChildToParentPointer($system, $parent_id, $rectype){

    if($system->defineConstant('DT_PARENT_ENTITY')){
        //get list of valid record
        $query = 'SELECT dtl_Value FROM recDetails, defRecStructure '
        ." WHERE dtl_RecID=$parent_id AND dtl_DetailTypeID=rst_DetailTypeID AND rst_CreateChildIfRecPtr=1 AND rst_RecTypeID=$rectype";

        $mysqli = $system->getMysqli();

        $recids = mysql__select_list2($mysqli, $query, 'intval');

        $query = "DELETE FROM recDetails WHERE dtl_Value=$parent_id AND dtl_DetailTypeID=".DT_PARENT_ENTITY;

        if(!isEmptyArray($recids)){
            $recids = prepareIds($recids);//redundant for snyk
            $query = $query.' AND dtl_RecID NOT IN ('.implode(',',$recids).')';
        }

        $mysqli->query($query);
    }
}


//
// add/update pointer detail field TO child record
// return -1 - error, 0 - nothing done, 1 - insert
//
// only ONE parent allowed
function addParentToChildPointer($mysqli, $child_id, $child_rectype, $parent_id,  $detailTypeId=null, $addedByImport=0){

    $res = 0;

    if(defined('DT_PARENT_ENTITY')){

        $dtl_ID = -1;
        $parent_id = intval($parent_id);
        $child_id = intval($child_id);

        //find what field in parent record refers
        if(!($detailTypeId>0)){

            $query =
            'SELECT rst_DetailTypeID, dty_PtrTargetRectypeIDs FROM defRecStructure, defDetailTypes, Records '
            .'WHERE rec_ID='.$parent_id.' AND rec_RecTypeID=rst_RecTypeID AND rst_CreateChildIfRecPtr=1 '
            .'AND rst_DetailTypeID=dty_ID';

            $pointers = mysql__select_assoc2($mysqli, $query);
            if(!isEmptyArray($pointers)){
                foreach($pointers as $dt_ID=>$ptr){
                    if($ptr) {$ptr = explode(',',$ptr);}
                    if(!empty($ptr) && in_array($child_rectype, $ptr)){
                        $detailTypeId = $dt_ID;
                        break;
                    }
            }}
        }

        if(!($detailTypeId>0)){
            return 0; //appropriate pointer field in parent record type not found
        }

        //check if already exists
        $query = "SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_RecID=$parent_id AND dtl_DetailTypeID=$detailTypeId";
        $res = $mysqli->query($query);
        if ($res){
            $matches = array();
            while ($row = $res->fetch_row()){
                if($child_id == $row[1]){
                    return 0; //exactly the same already exists
                }
                $dtl_ID = $row[0];
            }
            $res->close();
        }

        $mysqli->query('INSERT INTO recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_AddedByImport) '.
            "VALUES ($parent_id, ".$detailTypeId.", $child_id, $addedByImport )");

        $res = 1;
        if(!($mysqli->insert_id>0)) {$res=-1;}
    }

    return $res;

}

//
// add/update pointer detail field TO child record
// return -1 - error, 0 - nothing done, 1 - insert
//
function addPointerField($system, $source_id, $target_id, $dty_ID, $to_replace){

    $res = 0;

    $mysqli = $system->getMysqli();

        $dtl_ID = -1;
        $source_id = intval($source_id);
        $target_id = intval($target_id);
        $dty_ID = intval($dty_ID);

        if(!($source_id>0 && $target_id && $dty_ID>0)){
            $system->addError(HEURIST_INVALID_REQUEST, 'Wrong paramters for records link creation');
            return -1;
        }

        //check that link already exists
        $target_IDs = mysql__select_assoc2($mysqli, 'SELECT rl_DetailID, rl_TargetID FROM recLinks WHERE rl_SourceID='.$source_id
                //.' AND rl_TargetID='.$target_id
                .' AND rl_DetailTypeID='.$dty_ID);
        if(!empty($target_IDs)){
            if(in_array($target_id, $target_IDs)){
                return 0; //such link already exists
            }
            if($to_replace){
                //remove existing one
                $dtl_IDs = array_keys($target_IDs);
                if(count($dtl_IDs)==1){
                    $mysqli->query('DELETE FROM recDetails WHERE dtl_ID ='.intval($dtl_IDs[0]));
                }else{
                    $dtl_IDs = prepareIds($dtl_IDs);
                    $mysqli->query('DELETE FROM recDetails WHERE dtl_ID IN ('.implode(',',$dtl_IDs).')');
                }
            }
        }

        $mysqli->query('INSERT INTO recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) '.
            "VALUES ($source_id, $dty_ID, $target_id)");

        $res = 1;
        if(!($mysqli->insert_id>0)){
            $system->addError(HEURIST_DB_ERROR, 'Can not add record pointer field', $mysqli->error);
            $res=-1;
        }

    return $res;
}


// verify ACCESS RIGHTS -------------
//
//
function isWrongAccessRights($system, $access){
    if ($access=='viewable' || $access=='hidden' || $access=='public' || $access=='pending') {
        return false;
    }else{
        $system->addError(HEURIST_INVALID_REQUEST, 'Non-owner visibility value is missing or invalid');
        return true;
    }
}

/**
* Verifies access right value and is the current user able to change ownership for given record
*
* @param mixed $system
* @param mixed $recID
* @param mixed $owner_grps
* @param mixed $access
*   $rectypes  - return record type of current record
*/
function recordCanChangeOwnerwhipAndAccess($system, $recID, &$owner_grps, &$access, &$rectypes)
{

    $mysqli = $system->getMysqli();
    $recID = intval($recID);
    //get current values
    $query = 'select rec_OwnerUGrpID, rec_NonOwnerVisibility, rec_RecTypeID from Records where rec_ID = '.$recID;
    $res = $mysqli->query($query);

    if($res){
        $record = $res->fetch_assoc();
        $res->close();
    } else {
        $system->addError(HEURIST_DB_ERROR, 'Cannot get record', $mysqli->error);
        return false;
    }
    //get group permissions
    $isEveryOne = true;
    $current_owner_groups = null;
    if($record["rec_OwnerUGrpID"]>0){ //not everyone
        $isEveryOne = false;
        $query = 'select rcp_UGrpID from usrRecPermissions where rcp_Level="edit" AND rcp_RecID = '.$recID; //not used
        $current_owner_groups = mysql__select_list2($mysqli, $query);

    }
    if(!$current_owner_groups) {$current_owner_groups = array();}
    array_unshift($current_owner_groups, $record["rec_OwnerUGrpID"]);//add to begin of array

    if(count($current_owner_groups)==1 && !($current_owner_groups[0]>=0)){
        //rare case when current record has wrong value
        $current_owner_groups = array($system->getUserId());
    }

    //$ownerid_old = @$record["rec_OwnerUGrpID"];//current ownership
    //new owners are not defined - take current one
    if(isEmptyArray($owner_grps) || !($owner_grps[0]>=0)){
        $owner_grps = $current_owner_groups;
    }
    if(array_search(0, $owner_grps, true)!==false){ //there is "everyone"
        $owner_grps = array(0);
    }

    //1. Can current user edit this record?
    // record is not "everyone" and current user is_admin or itself or member of group
    if (!$isEveryOne  && !($system->isAdmin() || $system->isMember($current_owner_groups) || $system->isGuestUser() )){

        $system->addError(HEURIST_REQUEST_DENIED,
            'Current user does not have sufficient authority to change the record ID:'.$recID
            .'. User must be either the database administrator or member of the group'
            .(count($current_owner_groups)>1?'s':'')
            .' that own'
            .(count($current_owner_groups)>1?'':'s').' this record');
        return false;
    }

    //2. Can current user change ownership of this record?
    if(!$system->isAdmin()){

        if($isEveryOne  && $owner_grps[0]>0){
            //C. Only DB admin can change "Everyone" record to group record
            $system->addError(HEURIST_REQUEST_DENIED,
                'User does not have sufficient authority to change public record to group record');
            return false;

        }else{

            //check that new ownership is different
            //A. new owners
            foreach($owner_grps as $grp){
                if(array_search($grp, $current_owner_groups)===false){
                    if(!$system->isMember($grp)){
                        $system->addError(HEURIST_REQUEST_DENIED,
                            'Cannot set ownership of record to the group without membership in this group', 'Group#'.grp);
                        return false;
                    }
                }
            }
            //B. owners to remove
            foreach($current_owner_groups as $grp){
                if(array_search($grp, $owner_grps)===false){
                    if(!$system->hasAccess($grp)){
                        $system->addError(HEURIST_REQUEST_DENIED,
                            'Cannot change ownership. User does not have ownership rights. '
                            .'User must be either database administrator, record owner or administrator or record\'s ownership group',
                            'Group#'.grp);
                        return false;
                    }
                }
            }
        }

    }


    //---------------------------
    //change public to pending in case db system preferences
    if($access=='public' && $record["rec_NonOwnerVisibility"]=='public'
    && $system->settings->get('sys_SetPublicToPendingOnEdit')==1){
        $access='pending';
    }elseif(!$access){
        $access = $record["rec_NonOwnerVisibility"];
    }
    //if defined and wrong it fails
    if($access && isWrongAccessRights($system, $access))
    {
        return false;
    }

    //return record type for given record id
    if(is_array($rectypes)){
        $rectypes[$recID] = $record["rec_RecTypeID"];
    }

    return $res;


}

//
// check that this record my affect other records with calculated fields
// 1. cfn_RecTypeIDs -> cfn_ID
// 2. defRecStructure where rst_CalcFunctionID  -> rst_RecTypeID+rst_DetailTypeID
//
function findAndUpdateAffectedCalcFields( $system, $rty_ID ){

    $mysqli = $system->getMysqli();

    $query = 'SELECT cfn_ID FROM defCalcFunctions WHERE find_in_set('.$mysqli->real_escape_string($rty_ID).',cfn_RecTypeIDs) <> 0';
    $field_ids = mysql__select_list2($mysqli, $query);

    if(!isEmptyArray($field_ids)){

        $query = 'SELECT rst_RecTypeID WHERE rst_CalcFunctionID IN ('.implode(',',$field_ids).')';
        $rectype_ids = mysql__select_list2($mysqli, $query);

        if(!isEmptyArray($rectype_ids)){
            recordUpdateCalcFields($system, null, $rectype_ids);
        }
    }
}

//
// $recID - record(s) to be updated. If it is omitted it updates all records for $rty_ID
// $rty_ID - record type(s)
// if both parameters are null it updates all calculated fields for entire database
//
function recordUpdateCalcFields($system, $recID, $rty_ID=null, $progress_session_id=null)
{
    $mysqli = $system->getMysqli();

    $rectypes = null;
    $rec_count = 0;

    if($recID!=null && !isEmptyArray($recID)){ //for selected set of records
        //group records by rectype
        $query = 'select rec_RecTypeID, rec_ID from Records where rec_ID in ('
                        .implode(',',$recID).') ORDER BY rec_RecTypeID';

        $rectypes = array();
        $rty_ID = null;
        $res = $mysqli->query($query);
        if ($res){
            while ($row = $res->fetch_row()){
                if($rty_ID != $row[0]){
                    if($rty_ID && is_array(@$rectypes[$rty_ID])){
                        $rec_count = $rec_count + count($rectypes[$rty_ID]);
                    }
                    $rty_ID = $row[0];
                    $rectypes[$rty_ID] = array();
                }
                array_push($rectypes[$rty_ID], $row[1]);
            }
            $res->close();
        }
        if($rty_ID && is_array(@$rectypes[$rty_ID])){
          $rec_count = $rec_count + count($rectypes[$rty_ID]);
        }

    }elseif($recID>0){

        //find record type if not defined
        if(!(isset($rty_ID) && $rty_ID>0)){
            $rty_ID = mysql__select_value($mysqli, 'select rec_RecTypeID from Records where rec_ID='.$recID);
            if(!($rty_ID>0)){
                $system->addError(HEURIST_DB_ERROR, 'Cannot get record for calculation fields update. Rec#'.$recID);
                return false;
            }
        }

        $rectypes = array($rty_ID=>array($recID));
        $rec_count = 1;
    }else //record is not defined - update all records
    {

        if($rty_ID!=null && !is_array($rty_ID)){
            $rty_ID = prepareIds($rty_ID);
        }

        if(isEmptyArray($rty_ID)){
            //all rectypes - entire database
            $rty_ID = mysql__select_list2($mysqli, 'SELECT rty_ID FROM defRecTypes');
            $rec_count = mysql__select_value($mysqli, 'SELECT count(rec_ID) FROM Records WHERE (NOT rec_FlagTemporary)');
        }else{
            $rec_count = mysql__select_value($mysqli, 'SELECT count(rec_ID) FROM Records '
            .'WHERE (rec_RecTypeID IN ('.implode(',',$rty_ID).')) AND (NOT rec_FlagTemporary)');
        }
        $rectypes = array();
        foreach ($rty_ID as $id){
            $rectypes[$id] = '*';
        }
    }

    try{
        $smarty = smartyInit($system);
    } catch (Exception $e) {
        return array('message'=>'Smarty init error: '.$e->getMessage());
    }

    if($progress_session_id>0 && $rec_count>100){
        mysql__update_progress(null, $progress_session_id, true, '0,'.$rec_count);
    }else{
        $progress_session_id = 0;
    }

    $progress_count = 0;

    $updates = array();// record ids
    $cleared = array();// record ids
    $errors  = array();// formulae errors

    $updated_count = 0;   // updated fields
    $cleared_count = 0;   // cleared fields
    $unchanged_count = 0; // unchanged fields

    $heuristRec = new ReportRecord($system);//helper class - to obtain access to heurist data from smarty report

    foreach ($rectypes as $rty_ID => $record_ids){

        //find calculation fields for this record type
        // dty_ID => cfn_FunctionSpecification
        $formulae = mysql__select_assoc2($mysqli,
            'SELECT rst_DetailTypeID, cfn_FunctionSpecification FROM defRecStructure, defCalcFunctions '
            .' WHERE rst_RecTypeID='.$rty_ID
            .' AND cfn_ID=rst_CalcFunctionID');

        //there are not calculation fields for this record type
        if(isEmptyArray($formulae)){

            if($record_ids=='*'){
               $cnt = mysql__select_value($mysqli, 'SELECT count(rec_ID) FROM Records '
                .'WHERE (rec_RecTypeID='.$rty_ID.') AND (NOT rec_FlagTemporary)');
               $progress_count = $progress_count + $cnt;
            }elseif (is_array($record_ids)) {
               $progress_count = $progress_count + count($record_ids);
            }

            continue; //no formulae for this record type
        }

        $keep = $progress_count;

        //@todo calculation field can not be repeatable
        foreach($formulae  as $dty_ID => $formula){

            $idx = 0;
            $rows = null;
            $mode = null;
            if($record_ids=='*'){
                $query = 'SELECT rec_ID FROM Records WHERE (rec_RecTypeID='.intval($rty_ID).') AND (NOT rec_FlagTemporary)';
                $rows = $mysqli->query($query);
                //$mode = 'string:';
            }elseif (count($record_ids)>1){
                //$mode = 'string:';
            }

            $params = array();
            $params['template'] = $formula;

            $progress_count = $keep; //reset - each record can have several calculated fields

            while(true){ //loop for records

                if($record_ids=='*'){
                     $row = $rows->fetch_row();
                     if($row){
                         $recID = intval($row[0]);
                     }else{
                         break;
                     }
                }else{
                    if(is_array($record_ids) && $idx<count($record_ids)){
                         $recID = intval($record_ids[$idx]);
                         $idx++;
                    }else{
                         break;
                    }
                }

                $params['records'] = array($recID);

                $new_value = executeSmarty($system, $smarty, $params, $mode, $heuristRec);

                if(is_array($new_value)){
                    if($new_value[0]=='fatal'){  //fatal smarty error
                        if($progress_session_id>0){
                            mysql__update_progress(null, $progress_session_id, false, 'REMOVE');
                        }
                        return array('message'=>$new_value[1]);
                    }else{
                        //formula has errors - skip
                        $errors[$rty_ID.'.'.$dty_ID] = $new_value[1];
                        break;
                    }
                }elseif($new_value == 'NAN' || $new_value == 'INF' || $new_value == SQL_NULL){
                    // relpace not a number, infinite, and null with an empty string
                    $new_value = '';
                }

                $current_value = mysql__select_value($mysqli,
                    "SELECT dtl_Value FROM recDetails WHERE dtl_RecID=$recID AND dtl_DetailTypeID=$dty_ID");

                if($new_value!=null) {$new_value = trim($new_value);}

                if($current_value==$new_value){
                    $unchanged_count++;
                }else{

                    if($current_value!=null && $current_value!=''){
                        $query = "DELETE FROM recDetails WHERE dtl_RecID=$recID AND dtl_DetailTypeID=$dty_ID";
                        $mysqli->query($query);
                    }

                    if($new_value!=null && $new_value!=''){
                        $query = 'INSERT INTO recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) '
                        .' VALUES ('.$recID.', '.$dty_ID.', ? )';
                        $stmt = $mysqli->prepare($query);

                        $stmt->bind_param('s', $new_value);
                        if(!$stmt->execute()){
                            $syserror = $mysqli->error;
                            $stmt->close();
                            $system->addError(HEURIST_DB_ERROR, "Cannot save calculated field $dty_ID for record # $recID", $syserror);
                            return false;
                        }
                        $stmt->close();

                        $updates[] = $recID;
                        $updated_count++;
                    }else{
                        $cleared[] = $recID;
                        $cleared_count++;
                    }
                }
                $progress_count++;

                if($progress_session_id>0 && ($progress_count % 100 == 0)){
                    $session_val = $progress_count.','.$rec_count;
                    $current_val = mysql__update_progress(null, $progress_session_id, false, $session_val);
                    if($current_val && $current_val=='terminate'){
                        mysql__update_progress(null, $progress_session_id, false, 'REMOVE');
                        return array('message'=>'Operation has been terminated by user');
                    }
                }

            }//while records
        }//for formulae

    }//for record types

    if($rec_count>1){

        //remove session file
        if($progress_session_id>0){
            mysql__update_progress(null, $progress_session_id, false, 'REMOVE');
        }

        $q_updates = '';
        $q_cleared = '';

        if(count($updates)>1000){
            $q_updates = 'ids:'.array_slice($updates, 0, 1000);
        }elseif(!empty($updates)){
            $q_updates = 'ids:'.implode(',',$updates);
        }
        if(count($cleared)>1000){
            $q_cleared = 'ids:'.array_slice($cleared, 0, 1000);
        }elseif(!empty($cleared)){
            $q_cleared = 'ids:'.implode(',',$cleared);
        }

        return array(
            // fields
            'fld_changed'=>$updated_count,
            'fld_same'=>$unchanged_count,
            'fld_cleared'=>$cleared_count,
            //records
            'rec_updates'=>count($updates),
            'rec_cleared'=>count($cleared),
            'rec_processed'=>$progress_count,
            'rec_total'=>$rec_count,
            //errors in formula  rty_ID.dty_ID - message
            'errors'=>$errors,
            //queries
            'q_updates'=>$q_updates, 'q_cleared'=>$q_cleared);
    }else{
            return array('errors'=>$errors);
    }
}

//
// $params - array
//     template - string with code
//     records - record ids
//     mode - eval or string (re-use)
//
function executeSmarty($system, $smarty, $params, $mode=null, $heuristRec=null){

  $content = (array_key_exists('template',$params)?$params['template']:null);

  if($content==null || $content=='') {return array('error', 'Formula not defined');}

  $record_ids = @$params['records'];

  if(!is_array($record_ids) || empty($record_ids)) {return '';}

  $mode = $mode ?$mode:'eval:';//string: - use complied or eval: - compile every time

  /*
  $template_folder = $smarty->getTemplateDir();
  if(is_array($template_folder)) {$template_folder = $template_folder[0];}

  //$user = $system->getCurrentUser();'_'.$user['ugr_Name']
  $template_file = $template_folder.'calc_fld_'.uniqid().'.tpl';
  $file = fopen ($template_file, "w");
  fwrite($file, $content);
  fclose ($file);
  */

  //@todo use ReportExecute class
  if($heuristRec==null) {$heuristRec = new ReportRecord($system);}

  $smarty->assign('heurist', $heuristRec);

  $smarty->assign('results', $record_ids);//assign
  $smarty->error_reporting = 0;
  $smarty->debugging = false;

  $smarty->assign('r', $heuristRec->getRecord($record_ids[0]));

  try{
      $output = $smarty->fetch($mode.$content);

  } catch (Exception $e) {
      $output = array('error', 'Exception on field calculation: '.$e->getMessage());
  }
  //unlink($file);
  return $output; //new value
}
/*
function smarty_remove_temp_template($tpl_source, Smarty_Internal_Template $template){

}
*/

/**
* Calculate and update title mask
*
* @param mixed $system
* @param mixed $recID
* @param mixed $rectype_or_mask - record type or title mask
* @param mixed $recTitleDefault - default title, null means don't update title if something goes wrong
*/
function recordUpdateTitle($system, $recID, $rectype_or_mask, $recTitleDefault)
{

    $mysqli = $system->getMysqli();

    $mask = null;
    $rectype = null;

    if(is_numeric($rectype_or_mask) && $rectype_or_mask>0){
        $rectype = $rectype_or_mask;
    }elseif(!isEmptyStr($rectype_or_mask)){
        $mask = $rectype_or_mask;
    }

    if($mask == null)
    {
        if(!isPositiveInt($rectype)){
            $rectype = mysql__select_value($mysqli, "select rec_RecTypeID from Records where rec_ID=".$recID);
        }

        if(!isPositiveInt($rectype)){
            $system->addError(HEURIST_DB_ERROR, 'Cannot get record for title mask update. Rec#'.$recID);
            return false;
        }

        $mask = mysql__select_value($mysqli, 'select rty_TitleMask from defRecTypes where rty_ID='.$rectype);
        if(!$mask){
            $system->addError(HEURIST_DB_ERROR, 'Cannot get title mask for record type', $mysqli->error);
            return false;
        }
    }

    TitleMask::initialize($system);
    $new_title = TitleMask::fill($recID, $mask);

    if(($new_title==null || strpos($new_title, 'Title mask not generated.') === 0) && $recTitleDefault!=null) {
        $new_title = $recTitleDefault;
    }

    $new_title = trim($new_title);

    if(isEmptyStr($new_title)){
        return 'Can\'t get title for #'.$recID;
    }

    if(mb_strlen($new_title)>1023){
        $new_title = mb_substr($new_title,0,1023);
    }

    $res = mysql__exec_param_query($mysqli, 'UPDATE Records set rec_Title=? where rec_ID='.intval($recID), array('s',$new_title) );
    if($res!==true){
        $system->addError(HEURIST_DB_ERROR, 'Cannot save record title', $res);
        return false;
    }


    return $new_title;
}

/*
*   $record - new values for record
*   $detailValues -  array ready to insert (dtl_DetailTypeID=>, dtl_Value=>, dtl_Geo=>....)
*   $update_mode
*   - 2 Add new values without deletion of existing values (duplicates are ignored)
*   - 3 Add new values only if field is empty (new values ignored for non-empty fields)
*   - 4 Replace existing values with new values, retain existing value if no new value supplied
*
*   It finds original (existing) record in database and either add, replace or retain values
*   and returns modified  $detailValues
*
*/
function prepareRecordForUpdate($system, $record, $detailValuesNew, $update_mode){

    /*
    todo
    $rec_url = USanitize::sanitizeURL(@$record['URL']);
    $rec_spad = @$record['ScratchPad'];
    $rec_temp = (@$record['FlagTemporary']==1)?1:0;
    */

    $detailValues = recordSearchDetailsRaw($system, $record['ID']);
    $processed_dtyID = array();

    foreach($detailValuesNew as $idx=>$values){

        $dty_ID = $values['dtl_DetailTypeID'];

        if(in_array($dty_ID,$processed_dtyID)){
            continue;
        }
        $processed_dtyID[] = $dty_ID;

        //find in original
        $is_found = false;
        foreach($detailValues as $idx2=>$val){
            if($val['dtl_DetailTypeID']==$dty_ID){
                $is_found = true;
                break;
            }
        }

        if($is_found){  //exists

            if($update_mode==3){
                continue; //Add new values only if field is empty
            }

            if($update_mode==4){
                //replace all fields of certain type with new value
                foreach($detailValues as $idx2=>$val){
                    if($val['dtl_DetailTypeID']==$dty_ID){
                        unset($detailValues[$idx2]);
                    }
                }
                foreach($detailValuesNew as $idx2=>$val){
                    if($val['dtl_DetailTypeID']==$dty_ID){
                        array_push($detailValues, $val);
                    }
                }

            }else{ //$update_mode==2  always add but prevent duplications

                $details_lc = array();
                foreach($detailValues as $idx2=>$val){
                    if($val['dtl_DetailTypeID']==$dty_ID){
                        if(@$val['dtl_Geo']){
                            if(strlen(@$val['dtl_Geo'])<1000){
                                $details_lc[] = $val['dtl_Geo'];
                            }
                        }elseif($val['dtl_Value'] && strlen($val['dtl_Value'])<200){
                            $details_lc[] = trim_lower_accent($val['dtl_Value']);
                        }
                    }
                }

                foreach($detailValuesNew as $idx2=>$val){
                    if($val['dtl_DetailTypeID']==$dty_ID){

                        $need_add = false;

                        if(@$val['dtl_UploadedFileID']>0){
                            $need_add = true;
                        }elseif(@$val['dtl_Geo']){

                            if(strlen($val['dtl_Geo'])>=1000
                            || array_search($val['dtl_Geo'], $details_lc, true)===false){
                                $need_add = true;
                            }

                        }elseif(strlen($val['dtl_Value'])>=200
                        || array_search(trim_lower_accent($val['dtl_Value']), $details_lc, true)===false)
                        {
                            $need_add = true;
                        }

                        if($need_add){
                            array_push($detailValues, $val);
                        }
                    }
                }
            }

        }else{ //not exists
            //add new values
            foreach($detailValuesNew as $idx2=>$val){
                if($val['dtl_DetailTypeID']==$dty_ID){
                    array_push($detailValues, $val);
                }
            }
        }
    }//for

    return $detailValues;
}

//function doDetailInsertion($recID, $details, $rectype, $wg, &$nonces, &$retitleRecs, $modeImport)
/**
* @todo make private
*
* uses getHTMLPurifier, checkMaxLength
*
* @param mixed $mysqli
* @param mixed $rectype
* @param mixed $details
* @param mixed $validation_mode - 0 (no validation at all), 1 - don't check resource, 2 - check everything
*
* return details in format ready to insert to database
*       array('dtl_DetailTypeID'=>$dtyID,'dtl_Value'=>$value,'dtl_UploadedFileID'=>, 'dtl_Geo'=>)
*
*/
function _prepareDetails($system, $rectype, $record, $validation_mode, $recID, $modeImport)
{
    global $terms, $useNewTemporalFormatInRecDetails;

    $details = $record['details'];

    /*
    * $details is the form
    *    $details = array("t:1" => array("bd:234463" => "7th Ave"),
    *                      ,,,
    *                     "t:11" => array("0" => "p POINT (-73.951172 40.805661)"));
    * where t:id means detail type id  and bd:id means detail record id
    * new details are array values without a preceeding detail ID as in the last line of this example
    */

    //1. load record structure
    //2. verify (value, termid, file id, resource id) and prepare details (geo field). verify required field presence
    //3. delete existing details
    //4. insert new set


    $mysqli = $system->getMysqli();

    //exlude empty and wrong entries         t:dty_ID:[0:value, 1:value]
    $details2 = array();
    foreach ($details as $dtyID => $pairs) {

        if( (is_array($pairs) && empty($pairs)) || $pairs=='') {continue;} //empty value

        if(preg_match("/^t:\\d+$/", $dtyID)){ //old format with t:NNN
            $dtyID = substr($dtyID, 2);
        }
        if(is_numeric($dtyID) && $dtyID>0){  //ignore header and supplementary fields
            $details2[$dtyID] = is_array($pairs)?$pairs:array($pairs);
        }
    }

    //get list of fieldtypes for all details
    $query = 'SELECT dty_ID, dty_Type FROM defDetailTypes WHERE dty_ID in (' . implode(',', array_keys($details2)) . ')';
    $det_types = mysql__select_assoc2($mysqli, $query);

    $det_required = array();
    if($validation_mode>1){
        //load list of required details except relmarker
        $query = 'SELECT rst_DetailTypeID, IF((rst_DisplayName=\'\' OR rst_DisplayName IS NULL), dty_Name, rst_DisplayName) as rst_DisplayName '
        .'FROM defRecStructure, defDetailTypes WHERE '
        ."rst_RecTypeID=$rectype and rst_RequirementType='required' and dty_ID=rst_DetailTypeID "
        ." and dty_Type!='relmarker' and dty_Type!='separator'";
        $det_required = mysql__select_assoc2($mysqli, $query);
    }

    $det_childpointers =  mysql__select_list($mysqli, "defRecStructure",
        "rst_DetailTypeID",
        "rst_RecTypeID=$rectype and rst_CreateChildIfRecPtr=1");


    //$query_size = 'select LENGTH(?)';
    //$stmt_size = $mysqli->prepare($query_size);

    $system->defineConstant('RT_CMS_HOME');
    $system->defineConstant('RT_CMS_MENU');
    $system->defineConstant('DT_EXTENDED_DESCRIPTION');

    //list of field ids that will not html purified
    $not_purify = array();
    /*if($system->defineConstant('DT_CMS_SCRIPT')){ array_push($not_purify, DT_CMS_SCRIPT);}
    if($system->defineConstant('DT_CMS_CSS')){ array_push($not_purify, DT_CMS_CSS);}
    if($system->defineConstant('DT_SYMBOLOGY')){ array_push($not_purify, DT_SYMBOLOGY);}
    if($system->defineConstant('DT_KML')){ array_push($not_purify, DT_KML);}
    if($system->defineConstant('DT_QUERY_STRING')){ array_push($not_purify, DT_QUERY_STRING);}
    if($system->defineConstant('DT_SERVICE_URL')){ array_push($not_purify, DT_SERVICE_URL);}*/
    if($system->defineConstant('DT_CMS_EXTFILES')){ array_push($not_purify, DT_CMS_EXTFILES);}
    // $purifier = USanitize::getHTMLPurifier();
    //2. verify (value, termid, file id, resource id) and prepare details (geo field). verify required field presence

    $insertValues = array();
    $errorValues = array();
    $cntErrors = 0;

    foreach ($details2 as $dtyID => $values) {

        $splitValues = array();
        $idx_in_vis = 0;

        foreach ($values as $eltID => $dtl_Value) {

            if(!is_array($dtl_Value) && strlen(super_trim($dtl_Value))==0){
                $idx_in_vis++;
                continue;
            }

            $dtl_HideFromPublic = null;
            if(@$record['details_visibility'][$dtyID]){
                $dtl_HideFromPublic = (@$record['details_visibility'][$dtyID][$idx_in_vis]>0)?1:0;
            }
            $idx_in_vis++;

            $dval = array('dtl_DetailTypeID'=>$dtyID);

            $dtl_UploadedFileID = null;
            $dtl_Geo = null;
            $isValid = false;
            $err_msg = '';

            if(!(is_array($dtl_Value) || $det_types[$dtyID]=='geo' || $det_types[$dtyID]=='file')){
                $rval = $mysqli->real_escape_string( $dtl_Value );


                //special case: split huge web content
                if(defined('RT_CMS_MENU') && $rectype==RT_CMS_MENU && $dtyID==DT_EXTENDED_DESCRIPTION){
                    $lim = checkMaxLength2($rval);
                    //TEST $lim = 100;
                    if($lim>0){
                        //remove script tag
                        $dtl_Value = super_trim($dtl_Value);
                        $dtl_Value = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $dtl_Value);

                        //$dtl_Value = $purifier->purify($dtl_Value);
                        //$dtl_Value = htmlspecialchars_decode( $dtl_Value );

                        $iStart = 0;
                        while($iStart<mb_strlen($dtl_Value)){
                            array_push($splitValues, mb_substr($dtl_Value, $iStart, $lim));
                            $iStart = $iStart + $lim;
                        }
                    }
                }else{
                    $err_msg = checkMaxLength('#'.$dtyID, $rval);
                    if($err_msg!=null) {break;}
                }
            }

            switch ($det_types[$dtyID]) {

                case "freetext":
                case "blocktext":
                    $len  = strlen(super_trim($dtl_Value));
                    $isValid = ($len > 0);
                    if(!$isValid ){
                        $err_msg = 'Value is empty';
                    }elseif(!in_array($dtyID, $not_purify)){
                        $dtl_Value = super_trim($dtl_Value);
                        $dtl_Value = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $dtl_Value);

                        if($det_types[$dtyID]=="freetext"){ //remove non standard attributes
                        //(\w+)
                        $allowed = array('src','class','style','href');
                        $allowed2 = implode('=|',$allowed).'=';
                        $regex = ')[^>]))*((?:';
                        $allowed = implode('|',$allowed);
$dtl_Value = preg_replace('#<([A-Z][A-Z0-9]*)(\s*)(?:(?:(?:(?!'.$allowed2.$regex.$allowed
                     .')=[\'"][^\'"]*[\'"]\s*)?)(?:(?:(?:(?!'.$allowed2.$regex.$allowed
                     .')=[\'"][^\'"]*[\'"]\s*)?)(?:(?:(?:(?!'.$allowed2.$regex.$allowed
                     .')=[\'"][^\'"]*[\'"]\s*)?)[^>]*>#si','<$1$2$3$4$5>',$dtl_Value);
                        }

                    }
                    break;

                case "date":

                    if(is_array($dtl_Value)){ //date is temporal json array
                        $isValid = count($dtl_Value)>1 && (@$dtl_Value['timestamp'] || @$dtl_Value['start']);
                    }else{
                        $len  = strlen(super_trim($dtl_Value));
                        $isValid = ($len > 0);
                    }

                    if(!$isValid ){
                        $err_msg = 'Value is empty';
                    }else{

                        $dtl_Value = Temporal::getValueForRecDetails( $dtl_Value, $useNewTemporalFormatInRecDetails );

/* Use old plain temporals
                        }else{
                            //yesterday, today, tomorrow, now
                            $sdate = strtolower(super_trim($dtl_Value));
                            if($sdate=='today'){
                                $dtl_Value = date('Y-m-d');
                            }elseif($sdate=='now'){
                                $dtl_Value = date(DATE_8601);
                            }elseif($sdate=='yesterday'){
                                $dtl_Value = date('Y-m-d',strtotime("-1 days"));
                            }elseif($sdate=='tomorrow'){
                                $dtl_Value = date('Y-m-d',strtotime("+1 days"));
                            }elseif(strlen($dtl_Value)>=8 && strpos($dtl_Value,'-')==false){

                                try{
                                    $t2 = new DateTime($dtl_Value);

                                    $format = 'Y-m-d';
                                    if($t2->format('H')>0 || $t2->format('i')>0 || $t2->format('s')>0){
                                    //strlen($dtl_Value)>=12 || strpos($dtl_Value,'T')>7 || strpos($dtl_Value,' ')>7){
                                        if($t2->format('s')>0){
                                            $format .= ' H:i:s';
                                        }else{
                                            $format .= ' H:i';
                                        }
                                    }
                                    $dtl_Value = $t2->format($format);

                                }catch(Exception  $e){
                                    //skip conversion

                                }
                            }
                        }
*/
                    }
                    break;
                case "float":
                    $isValid = preg_match("/^\\s*-?(?:\\d+[.]?|\\d*[.]\\d+(?:[eE]-?\\d+)?)\\s*$/", $dtl_Value);
                    //preg_match('/^0(?:[.]0*)?$/', $dtl_Value)
                    if(!$isValid ) {$err_msg = 'Not valid float value '.htmlspecialchars($dtl_Value);}
                    break;
                case "enum":
                case "relationtype":

                    if($validation_mode>1){

                        if(!$terms){
                            $terms = new DbsTerms($system, dbs_GetTerms($system));
                        }

                        $term_domain = ($det_types[$dtyID]=="enum"?"enum":"relation");

                        if (is_numeric($dtl_Value)){
                            $term_tocheck = $dtl_Value;
                        }else{
                            $term_tocheck = $terms->getTermByLabel($term_domain, $dtl_Value);//within domain
                        }
                        $isValid = isValidTerm($system, $term_tocheck, $term_domain, $dtyID, $rectype);
                        if($isValid){
                            $dtl_Value = $term_tocheck;
                        }else{
                            $trm = $terms->getTerm($dtl_Value);
                            $err_msg = 'Term ID '.htmlspecialchars($dtl_Value)
                            . ($trm!=null
                                ?( ' <i>'.htmlspecialchars($trm[0]).'</i> is not in the list of values defined for this field')
                                :' not found');
                        }
                    }else{
                        $isValid = (intval($dtl_Value)>0);
                    }

                    break;

                case "resource":

                    if($validation_mode>1){
                        //check if resource record exists
                        $rectype_tocheck = mysql__select_row($mysqli, 'select rec_RecTypeID, rec_Title '
                            .'from Records where rec_ID = '.$dtl_Value);//or dbs_GetRectypeByID from db_strucuture
                        if($rectype_tocheck){


                            //check that this rectype is valid for given detail (constrained pointer)
                            $isValid = isValidRectype($system, $rectype_tocheck[0], $dtyID, $rectype);
                            if(!$isValid){

                                $err_msg = '<div style="padding-left:30px">'
                                . _getRtConstraintNames($system, $dtyID, $rectype)
                                . '<br>Target ID:'.$dtl_Value.'  '.USanitize::sanitizeString($rectype_tocheck[1], false).DIV_E;


                                //$err_msg = 'Record type '.$rectype_tocheck.' is not valid for specified constraints';
                            }
                        }else{
                            $err_msg = 'Record with specified id '.htmlspecialchars($dtl_Value).' does not exist';
                        }
                    }else{
                        $isValid = (intval($dtl_Value)>0);
                        if(!$isValid){
                            $err_msg = 'Record ID '.htmlspecialchars($dtl_Value).' is not valid integer';
                        }
                    }
                    //this is parent-child resource (record pointer)
                    if($isValid && in_array($dtyID, $det_childpointers)){
                        $dval['dtl_ParentChild'] = true;
                    }

                    break;


                case "file": //@TODO

                    if($dtl_Value=='generate_thumbnail_from_url' && @$record['URL']){

                        $tmp_file = UImage::makeURLScreenshot($record['URL']);

                        if(!is_a($tmp_file,'stdClass')){
                            $err_msg = is_array($tmp_file) ?$tmp_file['error'] :('System message: '.$tmp_file);
                        }else{
                            $entity = new DbRecUploadedFiles($system);

                            $dtl_UploadedFileID = $entity->registerFile($tmp_file, null);//it returns ulf_ID

                            if($dtl_UploadedFileID===false){
                                $err_msg = $system->getError();
                                $err_msg = $err_msg['message'];
                                $system->clearError();
                            }else{
                                $dtl_UploadedFileID = $dtl_UploadedFileID[0];
                            }
                        }

                        if($err_msg!=''){
                            //send email to heurist team about fail generation from url
                            $msg = 'The thumbnailer fails to return an image '.$record['URL'].'. '.$err_msg;
                            sendEmail(HEURIST_MAIL_TO_ADMIN, 'The thumbnailer fails to return an image '.$system->dbname(), $msg);
                            $err_msg = '';
                            $dtl_Value = '';
                            $isValid = 'ignore';
                            break; //just ignore this value
                        }

                    }elseif(is_numeric($dtl_Value)){  //this is ulf_ID
                        $dtl_UploadedFileID = intval($dtl_Value);

                        //TODO !!! mysql_num_rows(mysql_query("select ulf_ID from recUploadedFiles where ulf_ID=".dtl_UploadedFileID)) <=0 )

                    }elseif(is_string($dtl_Value)){  //this is base64 encoded image

                        //save encoded image as file and register it
                        $entity = new DbRecUploadedFiles($system);
                        $dtl_UploadedFileID = $entity->registerImage($dtl_Value, 'map_snapshot_'.$recID);//it returns ulf_ID
                        if( is_bool($dtl_UploadedFileID) && !$dtl_UploadedFileID ){
                            $dtl_UploadedFileID = -1; //fail
                            $err_msg = 'Can\'t register snapshot image';
                        }
                        if(is_array($dtl_UploadedFileID)){
                            $dtl_UploadedFileID = $dtl_UploadedFileID[0];
                        }


                    }else{  // new way - URL or JSON string with file data array (structure similar get_uploaded_file_info)
                        //TODO!!!!!
                        // $dtl_UploadedFileID = register_external($dtl_Value);
                        $dtl_UploadedFileID = intval(@$dtl_Value['ulf_ID']);
                    }


                    $dtl_Value = null;
                    $isValid = ($dtl_UploadedFileID>0);

                    if($validation_mode==0 && !$isValid) {$isValid = 'ignore';}

                    break;

                case "geo":

                    //note geoType can be not defined - detect it from dtl_Geo
                    list($dtl_Value, $dtl_Geo) = prepareGeoValue($mysqli, $dtl_Value);
                    if($dtl_Value===false){
                        $err_msg = $dtl_Geo;
                        $isValid = ($validation_mode==0)?'ignore':false;
                        if(!$isValid && $modeImport == 1){
                            $dval['dtl_Value'] = $values[$eltID];
                            $dval['dtl_UploadedFileID'] = null;
                            $dval['dtl_Geo'] = null;
                            array_push($insertValues, $dval);
                            $isValid = 'ignore';
                        }
                    }else{
                        $isValid = true;
                    }


                    /*
                    $res = $mysqli->query("select ST_asWKT(ST_GeomFromText('".addslashes($dtl_Geo)."'))");
                    if ($res){
                    if($res->fetch_row()){
                    $dtl_Value = $geoType;
                    $isValid = true;
                    }
                    $res->close();
                    }*/
                    break;
                    // retained for backward compatibility
                case "year":
                    $isValid = preg_match("/^\\s*(?:(?:-|ad\\s*)?\\d+(?:\\s*bce?)?|in\\s+press)\\s*$/i", $dtl_Value);
                    if(!$isValid){
                        $err_msg = htmlspecialchars($dtl_Value);
                        $err_msg = "Value $err_msg is not valid Year";
                    }
                    break;
                case "boolean":

                    $isValid = preg_match("/^(?:yes|true|no|false|1|0|T|F|Y|N)$/", $dtl_Value);
                    if($isValid){
                        if ($dtl_Value==1 || $dtl_Value == 'T' || $dtl_Value == 'Y'
                            || $dtl_Value == "yes"  ||  $dtl_Value == "true"){
                            $dtl_Value = "true";
                        }else{
                            $dtl_Value = "false";
                        }
                    }else{
                        $err_msg = htmlspecialchars($dtl_Value);
                        $err_msg = "Value $err_msg is not valid boolean";
                    }
                    break;
                case "integer":
                    $isValid = preg_match("/^\\s*-?\\d+\\s*$/", $dtl_Value);
                    if(!$isValid){
                        $err_msg = htmlspecialchars($dtl_Value);
                        $err_msg = "Value $err_msg is not valid integer";
                    }
                    break;

                case "separator":
                case "relmarker":
                default:
                    break;    //noop since separators and relmarker have no detail values
            } //switch


            if($isValid==='ignore') {continue;}

            //ignore all errors and skip empty values
            if($validation_mode==0 && $isValid!==true){
                if(strlen(super_trim($dtl_Value))==0) {continue;}
                $isValid = true;
            }

            if($isValid == true){

                if(@$det_required[$dtyID]!=null){
                    unset($det_required[$dtyID]);//value is valid - removes from list of required
                }

                $dval['dtl_UploadedFileID'] = $dtl_UploadedFileID;
                $dval['dtl_Geo'] = $dtl_Geo;
                $dval['dtl_HideFromPublic'] = $dtl_HideFromPublic;
                if(!empty($splitValues)){
                    foreach($splitValues as $val){
                        $dval['dtl_Value'] = $val;
                        array_push($insertValues, $dval);
                    }
                }else{
                    $dval['dtl_Value'] = $dtl_Value;
                    array_push($insertValues, $dval);
                }
            }else{
                if(!@$errorValues[$dtyID])
                {
                    $query = 'SELECT rst_DisplayName FROM defRecStructure WHERE rst_RecTypeID='.$rectype
                        .' and rst_DetailTypeID='.$dtyID;
                    $field_name = mysql__select_value($mysqli, $query);
                    if(!$field_name){
                        $query = 'SELECT dty_Name FROM defDetailTypes WHERE dty_ID='.$dtyID;
                        $field_name = mysql__select_value($mysqli, $query);
                    }

                    $dt_names = dbs_GetDtLookups();

                    if($modeImport>0){
                        $errorValues[$dtyID] = $field_name;
                    }else{
                        $errorValues[$dtyID] = '<br><div>Field ID '.$dtyID.': "'
                        .$field_name.'" ('.@$dt_names[$det_types[$dtyID]].')</div>';
                    }
                }
                if($modeImport>0){
                    $errorValues[$dtyID] .= (' '.$err_msg);
                }else{
                    $errorValues[$dtyID] .= ('<div style="padding-left:20px">'.$err_msg.DIV_E);
                }
                $cntErrors++;
            }

        }//for values
    }//for detail types

    //$stmt_size->close();


    $res = false;

    //there is undefined required details
    if ($cntErrors>0) {

        $ss = ($cntErrors>1?'s':'');
        /*
        array_push($errorValues,
        '<br><br>Please run Verify > Verify integrity to check for and fix data problems.<br>'
        .'If the problem cannot be fixed, or re-occurs frequently, please '.CONTACT_HEURIST_TEAM);
        */

        if($modeImport>0){
            $sMsg = implode(' ',$errorValues);
        }else{
            $sMsg = 'Encountered invalid value'.$ss
            .' for Record# '.$recID.'<br>'.implode(' ',$errorValues)
            .'<br> This may be due to your browser cache being out-of-date (use Ctrl-F5 to reload the page)';
        }

        $system->addError(HEURIST_ACTION_BLOCKED, $sMsg, null);

    }else{

        if (!isEmptyArray($det_required)) {

            $missed_req_dty = array_keys($det_required);
            foreach($missed_req_dty as $dty_ID){
                //try to add default values for missed required fields
                $query = 'SELECT rst_DefaultValue FROM defRecStructure WHERE rst_RecTypeID='.$rectype
                                .' and rst_DetailTypeID='.$dty_ID;
                $defaultValue = mysql__select_value($mysqli, $query);
                if($defaultValue!=null && $defaultValue!=''){
                    array_push($insertValues, array('dtl_DetailTypeID'=>$dty_ID, 'dtl_Value'=>$defaultValue));
                    unset($det_required[$dty_ID]);
                }
            }
        }

        if (!isEmptyArray($det_required)) {
            $isMulti = (count($det_required)>1);
            $query = 'SELECT rty_Name FROM defRecTypes WHERE rty_ID='.$rectype;
            $rty_Name = mysql__select_value($mysqli, $query);

            $system->addError(HEURIST_ACTION_BLOCKED, 'Required field'.($isMulti?'s':'')
                .' missing value or '.
                (count($det_required)>1?'have':'has')
                .' invalid value:<div style="padding-left:10px;font-style:italic;">'.implode('<br>',array_values($det_required)).DIV_E
                .' <br>Please change '.($isMulti?'these fields':'this field')
                .' in record type "'.htmlspecialchars($rty_Name)
                .'" to "optional" or specify default value for the field');

        }elseif (!is_array($insertValues) || empty($insertValues)) {
            $system->addError(HEURIST_INVALID_REQUEST, "It is not possible save record. No fields are defined");
        }else{
            $res = $insertValues;
        }
    }

    return $res;

} //END _prepareDetails


//
//
//
function prepareGeoValue($mysqli, $dtl_Value){

    $geoType = super_trim(substr($dtl_Value, 0, 2));
    $hasGeoType = false;
    $res = false;

    if($geoType=='p'||$geoType=='l'||$geoType=='pl'||$geoType=='c'||$geoType=='r'||$geoType=='m'){
        $geoValue = super_trim(substr($dtl_Value, 2));
        $hasGeoType = true;
    }else{
        $geoValue = super_trim($dtl_Value);
        if(strpos($geoValue, 'GEOMETRYCOLLECTION')!==false || strpos($geoValue, 'MULTI')!==false){
            $geoType = "m";
            $hasGeoType = true;
        }elseif(strpos($geoValue,'POINT')!==false){
            $geoType = "p";
            $hasGeoType = true;
        }elseif(strpos($geoValue,'LINESTRING')!==false){
            $geoType = "l";
            $hasGeoType = true;
        }elseif(strpos($geoValue,'POLYGON')!==false){ //MULTIPOLYGON
            $geoType = "pl";
            $hasGeoType = true;
        }
    }

    if(preg_match('/\d/', $geoValue) && $hasGeoType){ // check that the value has ANY numbers (coordinates) and has an identified geo type
        try{
            $res = mysql__select_value($mysqli, "select ST_asWKT(ST_GeomFromText('".addslashes($geoValue)."'))");
        } catch (Exception $e) {
            return array(false, 'Geo WKT value '.substr(htmlspecialchars($geoValue),0,15).'... is not valid');
        }
    }

    if($res){
        return array($geoType, $geoValue);
    }else{
        return array(false, 'Geo WKT value '.substr(htmlspecialchars($geoValue),0,15).'... is not valid');
    }

}
//
//
//
function recordDuplicate($system, $id){

    // Check that the user is allowed to create records
    $is_allowed = userCheckPermissions($system, 'add');
    if(!$is_allowed){
        return false;
    }

    $mysqli = $system->getMysqli();

    $id = intval($id);
    if ( $id<1 ) {
        return $system->addError(HEURIST_INVALID_REQUEST, "Record ID is not defined");
    }

    $def_params = recordAddDefaultValues($system);
    $new_owner = $def_params['owner_grps'][0];
    $access = $def_params['access'];
    $access_grps = $def_params['access_grps'];

    $currentUserId = $system->getUserId();

    $row = mysql__select_row($mysqli, "SELECT rec_OwnerUGrpID, rec_RecTypeID FROM Records WHERE rec_ID = ".$id);
    //$owner = $row[0];
    $recTypeID = intval($row[1]);
    if (!is_numeric($new_owner) || !(intval($new_owner)>=0)){   //current user is not member of current group
        $new_owner = $currentUserId;
        //return $system->addError(HEURIST_REQUEST_DENIED, 'User not authorised to duplicate record');
    }


    $bkmk_count = 0;
    $rels_count = 0;

    $error = null;

    $system->defineConstant('DT_TARGET_RESOURCE');
    $system->defineConstant('DT_PRIMARY_RESOURCE');

    $prefixDbErrorMsg = 'database error - ';

    while (true) {

        mysql__foreign_check($mysqli, false);

        //duplicate record header
        $new_id = mysql__duplicate_table_record($mysqli, 'Records', 'rec_ID', $id, null);

        $query = 'UPDATE Records set rec_Modified=NOW(), rec_Added=NOW(), rec_AddedByUGrpID='.$currentUserId;
        if(is_numeric($new_owner) && intval($new_owner)>=0){
            $query = $query.', rec_OwnerUGrpID='.$new_owner;
        }
        if($access){
            $query = $query.', rec_NonOwnerVisibility="'.$access.'"';
        }

        $query = $query.' where rec_ID='.$new_id;
        $res = $mysqli->query($query);
        if(!$res){
            $error = $prefixDbErrorMsg .$mysqli->error;
            break;
        }


        if(!is_int($new_id)){ $error = $new_id; break; }


        if($access_grps!=null){
            updateUsrRecPermissions($mysqli, $new_id, $access_grps, null);
        }

        //duplicate record details
        $res = mysql__duplicate_table_record($mysqli, 'recDetails', 'dtl_RecID', $id, $new_id);
        if(!is_int($res)){ $error = $res; break; }


        //assign increment values
        //1. find increment detail types
        $dty_IDs = mysql__select_list2($mysqli,
            'SELECT rst_DetailTypeID FROM defRecStructure WHERE rst_RecTypeID='.$recTypeID
            .' AND rst_DefaultValue="increment_new_values_by_1"');

        if(!isEmptyArray($dty_IDs)){
            foreach($dty_IDs as $dty_ID){
                //2. get new incremented value
                $res = recordGetIncrementedValue($system, array('rtyID'=>$recTypeID, 'dtyID'=>$dty_ID));
                if($res['status']==HEURIST_OK){
                    $new_val = $res['result'];

                    $query = 'UPDATE recDetails set dtl_Value=?'
                    ." where dtl_RecID=$new_id and dtl_DetailTypeID=$dty_ID";

                    $res = mysql__exec_param_query($mysqli, $query, array('s', $new_val));

                    // .$mysqli->real_escape_string( $new_val )
                    // $res = $mysqli->query($query);
                    if(!$res){
                        $error = $prefixDbErrorMsg .$mysqli->error;
                        break;
                    }
                }else{
                    return $res;
                }
            }//for
        }



        //remove pointer fields where Parent-Child flag is ON
        $query = 'DELETE FROM recDetails where dtl_RecID='.$new_id.' and dtl_DetailTypeID in '
        .'(SELECT rst_DetailTypeID FROM defRecStructure WHERE rst_RecTypeID='.$recTypeID.' AND rst_CreateChildIfRecPtr=1)';
        $res = $mysqli->query($query);
        if(!$res){
            $error = $prefixDbErrorMsg .$mysqli->error;
            break;
        }

        $res = mysql__duplicate_table_record($mysqli, 'usrReminders', 'rem_RecID', $id, $new_id);
        if(!is_int($res)){ $error = $res; break; }

        $res = mysql__duplicate_table_record($mysqli, 'usrRecTagLinks', 'rtl_RecID', $id, $new_id);
        if(!is_int($res)){ $error = $res; break; }

        $res = mysql__duplicate_table_record($mysqli, 'usrRecPermissions', 'rcp_RecID', $id, $new_id);
        if(!is_int($res)){ $error = $res; break; }

        $res = mysql__duplicate_table_record($mysqli, 'usrBookmarks', 'bkm_RecID', $id, $new_id);
        if(!is_int($res)){ $error = $res; break; }
        $bkmk_count = $mysqli->affected_rows;

        mysql__foreign_check($mysqli, true);

        //add special kind of record - relationships
        $refs_res = mysql__select_list($mysqli, 'recLinks', 'rl_RelationID',
            '(rl_RelationTypeID is not null) and  (rl_SourceID='.$id.' or rl_TargetID='.$id.')');


        foreach ($refs_res as $rel_recid){

            $res = recordDuplicate($system, $rel_recid);

            if($res && @$res['status']==HEURIST_OK){

                $new_rel_recid = intval(@$res['data']['added']);

                if($new_rel_recid>0){

                    //change reference to old record id to new one
                    $query = 'UPDATE recDetails set dtl_Value='.$new_id
                    .' where dtl_RecID='.$new_rel_recid
                    .' and dtl_Value='.$id   //old record id
                    .' and (dtl_DetailTypeID='.DT_TARGET_RESOURCE.' or dtl_DetailTypeID='.DT_PRIMARY_RESOURCE.')';

                    $res = $mysqli->query($query);
                    if(!$res){
                        $error = $prefixDbErrorMsg .$mysqli->error;
                        break;
                    }else{
                        $rels_count++;
                    }
                }
            }else{
                $error = @$res['message'];
            }
        } //foreach

        break;
    }//while

    if($error==null){
        $res = array("status"=>HEURIST_OK,
            'affectedRty'=>$recTypeID,
            'data'=>array("added"=>$new_id, "bkmk_count"=>$bkmk_count, "rel_count"=>$rels_count));
    }else{
        $res = $system->addError(HEURIST_DB_ERROR, $error);
    }
    mysql__foreign_check($mysqli, true);
    return $res;

}

//
// Update usrRecPermissions for multigroup view permission
//
function updateUsrRecPermissions($mysqli, $recIDs, $access_grps, $owner_grps){

    $recIDs = prepareIds($recIDs);

    if(isEmptyArray($recIDs)){
        return;
    }


        $access_grps = prepareIds($access_grps);
        $owner_grps = prepareIds($owner_grps, true);

        $has_access_values = !empty($access_grps);
        $has_owner_values = !empty($owner_grps);

        if($has_access_values){
            $query = 'DELETE FROM usrRecPermissions WHERE rcp_RecID in ('.implode(',', $recIDs).') AND rcp_Level = "view"';
            $mysqli->query($query);
        }
        if($has_owner_values){
            $query = 'DELETE FROM usrRecPermissions WHERE rcp_RecID in ('.implode(',', $recIDs).') AND rcp_Level = "edit"';
            $mysqli->query($query);
        }

        if(!($has_access_values || $has_owner_values)){
            return;
        }

        //add group record permissions
        $values = array();
        foreach($recIDs as $recID){

                foreach ($owner_grps as $grp_id){
                    array_push($values,'('.intval($grp_id).','.$recID.',"edit")');
                }

                foreach ($access_grps as $grp_id){
                    array_push($values,'('.intval($grp_id).','.$recID.',"view")');
                }
        }
        $query = 'INSERT INTO usrRecPermissions (rcp_UGrpID,rcp_RecID,rcp_Level) VALUES '.implode(',',$values);
        $mysqli->query($query);

}


// @todo REMOVE - all these functions are duplicated in VerifyValue and dbsData.php

/**
* check that rectype is valid for given detail (constrained pointer)
*
* @param mixed $mysqli
* @param mixed $rectype_tocheck  - rectype to be verified
* @param mixed $dtyID  - detail type id
* @param mixed $rectype - for rectype
*/
function isValidRectype($system, $rectype_tocheck, $dtyID, $rectype)
{
    global $recstructures, $detailtypes;

    $rectype_ids = null;

    $recstr = dbs_GetRectypeStructure($system, $recstructures, $rectype);

    if($recstr && @$recstr['dtFields'][$dtyID])
    {
        $val = $recstr['dtFields'][$dtyID];
        $idx = $recstructures['dtFieldNamesToIndex']['rst_PtrFilteredIDs'];
        $rectype_ids = $val[$idx];//constraint for pointer
    }else{
        //detail type may be not in rectype structure

        $dtype = getDetailType($system, $detailtypes, $dtyID);
        if ($dtype) {
            $idx = $detailtypes['fieldNamesToIndex']['dty_PtrTargetRectypeIDs'];
            $rectype_ids = @$dtype[$idx];
        }
    }

    if($rectype_ids){
        $allowed_rectypes = explode(",", $rectype_ids);
        return in_array($rectype_tocheck, $allowed_rectypes);
    }

    return true;
}

//
//
//
function _getRtConstraintNames($system, $dtyID, $rectype)
{
    global $recstructures;

    $recstr = dbs_GetRectypeStructure($system, $recstructures, $rectype);

    if($recstr && @$recstr['dtFields'][$dtyID])
    {
        $val = $recstr['dtFields'][$dtyID];
        $idx = $recstructures['dtFieldNamesToIndex']['rst_PtrFilteredIDs'];
        $rectype_ids = $val[$idx];//constraint for pointer

        $idx_name = $recstructures['commonNamesToIndex']['rty_Name'];

        $rty_Name = $recstructures[$rectype]['commonFields'][$idx_name];


        $allowed_rectypes = explode(",", $rectype_ids);
        $allowed_names = array();

        foreach($allowed_rectypes as $rty_ID){
            $recstr = dbs_GetRectypeStructure($system, $recstructures, $rty_ID);
            array_push( $allowed_names, $recstructures[$rty_ID]['commonFields'][$idx_name] );
        }
        return 'Field expects target type <i>'.implode(', ',$allowed_names)
        .'</i><br>Target record is type <i>'.$rty_Name.'</i>';
    }
    return '';
}

// @todo use DbsTerms
// @todo REMOVE - all these functions are duplicated in DbsTerms and dbsData.php
// see VerifyValue
function isValidTerm($system, $term_tocheck, $domain, $dtyID, $rectype)
{
    global $recstructures, $detailtypes, $terms; //DbsTerms

    $terms_ids = null;

    $dtype = getDetailType($system, $detailtypes, $dtyID);
    if ($dtype) {
        $idx = $detailtypes['fieldNamesToIndex']['dty_JsonTermIDTree'];
        $terms_ids = @$dtype[$idx];
        $idx = $detailtypes['fieldNamesToIndex']['dty_TermIDTreeNonSelectableIDs'];
        $terms_none = @$dtype[$idx];
    }

    if($terms_ids){

        //get all terms for given vocabulary
        $allowed_terms = $terms->treeData($terms_ids,'set');

        /*
        $terms = getTermsFromFormat2($terms_ids, $domain);//parse

        if (($cntTrm = count($terms)) > 0) {
        if ($cntTrm == 1) { //vocabulary
        $vocabId = $terms[0];
        $terms = getTermsByParent($terms[0], $domain);
        array_push($terms, $vocabId);
        }else{
        $nonTerms = getTermsFromFormat2($terms_none, $domain);
        if (!empty($nonTerms)) {
        $terms = array_diff($terms, $nonTerms);
        }
        }
        if (!empty($terms)) {
        $allowed_terms = $terms;
        }
        }
        */

        return $allowed_terms && in_array($term_tocheck, $allowed_terms);
    }

    return true;

}

/**
* Assigns ownership, visibility and sends notification if stage is changed
* $new_value is always >0 and $record['FlagTemporary'] = 0
*
* @param mixed $system
* @param mixed $record
* @return array( new_value, curr_value, emails )
*/
function recordWorkFlowStage($system, &$record, $new_value, $is_insert){

    $current_value = 0;
    $emails = null;

    $res = array('new_value'=>$new_value, 'curr_value'=>$current_value, 'emails'=>$emails);

    if (!($new_value>0 && @$record['FlagTemporary']!=1)) {
        return $res;
    }


    $recID = intval(@$record['ID']);
    $recID = abs($recID);


    $mysqli = $system->getMysqli();

    if(!$is_insert){
        //find current stage
        $query = "SELECT dtl_Value FROM recDetails WHERE dtl_RecID=$recID AND dtl_DetailTypeID=".DT_WORKFLOW_STAGE;
        $current_value = mysql__select_value($mysqli, $query);
        $res['curr_value'] = $current_value;
    }

    if($current_value==$new_value){
        return $res;
    }


        //if stage is changed - assign new values for rec_OwnerUGrpID and rec_NonOwnerVisibility
            $query = 'SELECT swf_StageRestrictedTo, swf_SetOwnership, swf_SetVisibility, swf_SendEmail FROM sysWorkflowRules '
            .'WHERE swf_RecTypeID='.$record['RecTypeID'].' AND swf_Stage='.$new_value;
            $rule = mysql__select_row_assoc($mysqli, $query);

            //check that current user can change workflow stage
            $is_allowed = false;
            if($rule!=null &&
                ($rule['swf_StageRestrictedTo']==null
                || $system->isAdmin()
                || $system->isMember($rule['swf_StageRestrictedTo']))
            ){

                $is_allowed = true;
            }

            if($is_allowed){

                //changing ownership
                if($rule['swf_SetOwnership']!=null && $rule['swf_SetOwnership']>=0){
                    $record['OwnerUGrpID'] = $rule['swf_SetOwnership'];
                    $record['swf'] = true; //marker that ownership is change by workflow stage - it will not check that current user has rights
                }
                //changing visibility
                if($rule['swf_SetVisibility']!=null){
                    if($rule['swf_SetVisibility']=='public' ||
                       $rule['swf_SetVisibility']=='viewable' ||
                       $rule['swf_SetVisibility']=='hidden'){
                       $record['NonOwnerVisibility'] = $rule['swf_SetVisibility'];
                    }else{
                       $record['NonOwnerVisibility'] = 'viewable';
                       $record['NonOwnerVisibilityGroups'] = $rule['swf_SetVisibility'];
                    }
                }

                //get email addresses for notification

                if($rule['swf_SendEmail']!=null){

                    $query = 'SELECT ugr_eMail FROM sysUGrps '
                    .'WHERE ugr_ID IN ('.$rule['swf_SendEmail'].')';

                    $res['emails'] = mysql__select_list2($mysqli, $query);
                }

            }else{
                $res['new_value'] = 0; //not allowed
            }


    return $res;
}

//
// Re-add missing parent values if the parent has a child record pointer field that could point validly to the current record
//
function validateParentRecords($system, $child_record, &$new_child_details){

    $mysqli = $system->getMysqli();
    $rec_ID = $child_record['ID'];
    $rectype_ID = $child_record['RecTypeID'];

    $system->defineConstant('DT_PARENT_ENTITY');
    $parent_entity = defined('DT_PARENT_ENTITY') ? DT_PARENT_ENTITY : 0;

    if($parent_entity <= 0){
        return [];
    }

    $has_parent_entity_fld = mysql__select_value($mysqli, "SELECT rst_ID FROM defRecStructure WHERE rst_RecTypeID = ? AND rst_DetailTypeID = ?", ['ii', $rectype_ID, $parent_entity]);
    $is_child_of = mysql__select_assoc2($mysqli, "SELECT dtl_RecID, dtl_DetailTypeID FROM recDetails WHERE dtl_Value = {$rec_ID} AND dtl_RecID != {$rec_ID}");

    if(!$has_parent_entity_fld || empty($is_child_of)){
        return [];
    }

    $new_parents = array_filter($new_child_details, function($detail) use ($parent_entity){
        return $detail['dtl_DetailTypeID'] == $parent_entity;
    });
    $missing_parents = [];

    foreach($is_child_of as $parent_rec_ID => $parent_dty_ID){

        if(in_array($parent_rec_ID, array_column($new_parents, 'dtl_Value'))){
            // Still a parent
            continue;
        }

        [$parent_title, $parent_type] = mysql__select_row($mysqli, "SELECT rec_Title, rec_RecTypeID FROM Records WHERE rec_ID = ?", ['i', $parent_rec_ID]);
        $parent_type = intval($parent_type);

        $rectype_list_query = "SELECT dty_PtrTargetRectypeIDs, rst_ID FROM defDetailTypes INNER JOIN defRecStructure ON rst_DetailTypeID = dty_ID WHERE dty_ID = {$parent_dty_ID} AND dty_Type = 'resource' AND rst_RecTypeID = {$parent_type} AND rst_CreateChildIfRecPtr = 1";
        [$rectype_list, $rst_ID] = mysql__select_row($mysqli, $rectype_list_query);
        $rst_ID ??= 0;
        $rectype_list ??= '';
        $rectype_list = explode(',', $rectype_list);

        if($rst_ID <= 0 || !empty($rectype_list) && !in_array($rectype_ID, $rectype_list)){
            continue;
        }

        $new_child_details[] = [
            'dtl_DetailTypeID' => intval($parent_entity),
            'dtl_Value' => intval($parent_rec_ID)
        ];
        $missing_parents[$parent_rec_ID] = [
            'title' => $parent_title,
            'type' => $parent_type,
            'field' => intval($parent_dty_ID)
        ];
    }

    return $missing_parents;
}

/**
 * Update field entry values for the provided record, reporting those that have been updated, skipped or are invalid (value or entry mask)
 *
 * @param hserv\System $system Initialised Heurist system instance and connected to the necessary database
 * @param int $recID Record ID for the record being checked and updated
 * @param int $rtyID Record type for the record
 * @param bool $verbose Whether to include counts for action (values skipped, updated, or invalid)
 *
 * @return array resulting counts, for verbose = false an empty array means success
 */
function recordUpdateMaskFields($system, $recID, $rtyID = 0, $verbose = false){

    $mysqli = $system->getMysqli();
    $entryMaskFields = null;
    $result = [];

    if($recID > 0 && $rtyID <= 0){
        $rtyID = mysql__select_value($mysqli, "SELECT rec_RecTypeID FROM Records WHERE rec_ID = {$recID}");
    }

    if($recID > 0 && $rtyID > 0){
        $entryMaskFields = mysql__select_assoc2($mysqli, "SELECT rst_DetailTypeID, rst_EntryMask FROM defRecStructure WHERE rst_RecTypeID = {$rtyID} AND rst_EntryMask IS NOT NULL");
    }

    if(empty($entryMaskFields)){
        return [];
    }

    $values_updated = 0;
    $values_skipped = 0;
    $values_invalid = 0;
    $mask_invalid = [];

    foreach($entryMaskFields as $dtyID => $mask){

        $cur_vals = mysql__select_assoc2($mysqli, "SELECT dtl_ID, dtl_Value FROM recDetails WHERE dtl_RecID = {$recID} AND dtl_DetailTypeID = {$dtyID}");

        preg_match('~\$([adimn])(\d)*(\(\d,?\d*\))*\$~', $mask, $matches);

        if(count($matches) < 2){ // invalid mask

            $values_skipped ++;
            $mask_invalid[$dtyID] = $mask;

            continue;
        }

        $to_replace = $matches[0]; // mask's logical substring, replaced with value
        $check_for = substr($mask, 0, strpos($mask, $to_replace)); // used to check if mask has been applied
        $type = $matches[1]; // mask type [a,d,i,m,n]

        $length = count($matches) > 2 && is_numeric($matches[2]) ? intval($matches[2]) : 0;

        // Number range
        $range = count($matches) > 2 && is_string($matches[2]) && $matches[2][0] == '(' ? $matches[2] : [];
        $range = count($matches) > 3 && is_string($matches[3]) && $matches[3][0] == '(' ? $matches[3] : $range;
        $range = empty($range) ? [] : explode(',', str_replace(['(',')'], '', $range));

        if(count($range) !== 2){ // only one number was provided
            $range = null;
        }elseif(count($range) == 2 && $range[0] > $range[1]){ // swap min and max
            $temp = $range[0];
            $range[0] = $range[1];
            $range[1] = $temp;
        }

        foreach($cur_vals as $dtl_ID => $value){

            if(strpos($value, $check_for) === 0){ // mask already applied
                $values_skipped ++;
                continue;
            }

            $org_value = $value;
            $reason = false;

            [$value, $reason] = updateMaskFields($type, $value, $length, $range);

            if($reason === false){ // type not handled
                $mask_invalid[$dtyID] = $mask;
                continue;
            }elseif(!empty($reason)){ // value doesn't match the mask, leave value unchanged
                if(!array_key_exists($dtyID, $result)){
                    $result[$dtyID] = [ 'mask' => $mask ];
                }

                $result[$dtyID][] = ['value' => $org_value, 'reason' => $reason];

                $values_invalid ++;

                continue;
            }

            // Valid value, update value using mask
            $value = str_replace($to_replace, $value, $mask);

            $res = mysql__insertupdate($mysqli, 'recDetails', 'dtl', ['dtl_ID' => $dtl_ID, 'dtl_Value' => $value]);
            if(!$res){ // failed to update record detail
                if(!array_key_exists($dtyID, $result)){
                    $result[$dtyID] = [ 'mask' => $mask ];
                }

                $result[$dtyID][] = ['value' => $value, 'reason' => 'Failed to update record detail'];

                $values_invalid ++;

                continue;
            }

            $values_updated ++;
        }
    }

    if($verbose){
        $result['skipped'] = $values_skipped;
        $result['updated'] = $values_updated;
        $result['invalid'] = $values_invalid;

        $result['invalid_masks'] = $mask_invalid;
    }

    return $result;
}

/**
 * Process numeric related values
 *
 * @param string $type the specific numeric type [d, i, n]
 * @param mixed $value the value being checked
 * @param int $length the allotted number of decimal points for d & n
 * @param array[int, int] $range the minimum and maximum range for the number
 *
 * @return array[numeric, string] the resulting [value, invalid reasoning]
 */
function updateMaskFieldsNumeric($type, $value, $length, $range) {

    $type_text = $type === 'i' ? 'an integer' : 'numeric';
    $type_text = $type === 'd' ? 'a decimal number' : $type_text;

    if(is_numeric($value)){
        $value = $type === 'i' ? intval($value) : floatval($value);
    }

    $reason = '';

    if(!is_float($value) && !is_int($value)){
        $reason = "Not {$type_text}";
    }elseif(count($range) === 2 && ($value < $range[0] || $value > $range[1])){
        $reason = "Out of range: {$range[0]} - {$range[1]}";
    }elseif(is_float($value)){
        $value = number_format($value, $length);
    }

    return [$value, $reason];
}

/**
 * Process the value against the mask typing
 *
 * @param string $type the entry mask type [a, d, i, n, m]
 * @param mixed $value the value being validated
 * @param int $length the allotted length of the string [a, m] or number of decimal points [d, n]
 * @param array[int, int] $range the minimum and maximum range for the number [a, d, n]
 *
 * @return array[mixed, string] the resulting [value, invalid reasoning] combination
 */
function updateMaskFields($type, $value, $length, $range){

    $reason = '';

    switch($type){

        case 'a': // alphabetic, letters only

            $leng_regex = $length > 0 ? "{{1,$length}}" : '';
            $leng_regex = "~\w{$leng_regex}~";

            if(preg_match($leng_regex, $value, $word) === 1){
                $value = $word[0];
            }else{
                $reason = 'Not alphabetic';
            }

            break;

        case 'd': // decimal, float point number, will convert integers
        case 'i': // integer, whole number, will conevrt float points
        case 'n': // numeric, any type of number

            [$value, $reason] = updateMaskFieldsNumeric($type, $value, $length, $range);

            break;

        case 'm': // mixed, alphanumeric no special characters

            $mixed_regex = '~[^\w\d]~';
            $leng_regex = $length > 0 ? "{{1,$length}}" : '';
            $leng_regex = "~[\w\d]{$leng_regex}~";

            if(preg_match($mixed_regex, $value) !== 1){
                $reason = 'Contains non-alphanumeric characters';
            }elseif(preg_match($leng_regex, $value, $mixed) === 1){
                $value = $mixed[0];
            }

            break;

        default:

            $reason = false;
            break;
    }

    return [$value, $reason];
}
?>
