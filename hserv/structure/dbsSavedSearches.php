<?php

    /**
    * CRUD for Saved Searches (usrSavedSearches)
    *
    * svs - prefix for functions
    *
    * controller:
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <osmakov@gmail.com>
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

    /**
    * Get all saved searches for given list of ids
    *
    * @param mixed $system
    */
    function svsGetByIds($system, $rec_ids=null){

        if ($rec_ids) {

            $rec_ids = prepareIds($rec_ids);

            if (!empty($rec_ids)) {

                $mysqli = $system->getMysqli();
                $query = 'SELECT svs_ID, svs_Name, svs_Query, svs_UGrpID FROM usrSavedSearches WHERE svs_ID in ('
                        .implode(',', $rec_ids).')';

                $res = $mysqli->query($query);

                if ($res){
                    $result = array();
                    while ($row = $res->fetch_row()){
                        $id = array_shift($row);
                        $result[$id] = $row;
                    }
                    $res->close();
                    return $result;
                }else{
                    $system->addError(HEURIST_DB_ERROR, 'Cannot get saved searches', $mysqli->error);
                    return false;
                }
            }
        }

        $system->addError(HEURIST_INVALID_REQUEST,
                'Cannot get filter criteria. IDs are not defined');
        return false;
    }


    /**
    * Get all saved searches for given user
    *
    * @param mixed $system
    * @param mixed $ugrID - if not defined it searches all
    * @param $keep_order - keep order as define in groups tree
    */
    function svsGetByUser($system, $ugrID=null, $keep_order=false){

        $mysqli = $system->getMysqli();

        //user id is not defined - take current user
        if (!$ugrID) {
            $ugrID = $system->getUserId();

            $ugr_groups = $system->getUserGroupIds(null, true);//always get latest

            $current_User = $system->getCurrentUser();
            if($current_User && @$current_User['ugr_Groups'] && count(array_keys($current_User['ugr_Groups']))>0 ){
                $ugrID = implode(',', array_keys($current_User['ugr_Groups'])).','.$ugrID;
            }
            if($system->isAdmin()){ //returns guest searches for admin
                $ugrID = $ugrID.',0';
            }

        }

        if(!$ugrID) {
            $ugrID = '0,4';//get saved searches for guest and websearches
        }

        $ugrID = prepareIds($ugrID,true);

        $query = 'SELECT svs_ID, svs_Name, svs_Query, svs_UGrpID FROM usrSavedSearches WHERE svs_UGrpID in ('.implode(',', $ugrID).')';

        if($keep_order){
            $order = array();
            $query2 = 'SELECT ugr_NavigationTree FROM `sysUGrps` WHERE ugr_ID in ('.implode(',', $ugrID).')';
            $res = $mysqli->query($query2);
            if($res){
                while ($row = $res->fetch_row()) {
                     $treedata = json_decode($row[0],true);
                     if($treedata!=null && is_array($treedata)){
                        svsGetOrderFromTree($treedata, $order);
                     }

                }
            }

            if(!empty($order)){
                $query = $query.' order by FIELD(svs_ID,'.implode(',',$order).')';
            }
        }

        $res = $mysqli->query($query);

        if ($res){
            $order = array();
            $result = array();
            while ($row = $res->fetch_row()){
                $id = array_shift($row);
                $result[$id] = $row;
                array_push($order, $id);
            }
            $res->close();
            if($keep_order){
                return array('order'=>$order, 'svs'=>$result);
            }else{
                return $result;
            }
        }else{
            $system->addError(HEURIST_DB_ERROR, 'Cannot get saved searches', $mysqli->error);
            return false;
        }
    }

    //
    //
    //
    function svsGetOrderFromTree($tree, &$order){

        foreach($tree as $key=>$value){
            if($key=='children'){
                svsGetOrderFromTree($value, $order);
            }
            elseif (is_array($value) && @$value['key']>0 && @$value['folder']!==true)
            {
                array_push($order, intval($value['key']));
            }
        }
    }

    /**
    * Duplicate given saved search
    *
    * @param mixed $system
    * @param mixed $record
    */
    function svsCopy($system, $record){

        if (!(@$record['svs_ID']>0)){
            $system->addError(HEURIST_INVALID_REQUEST, 'ID for saved search to be duplicated is not defined');//for new
        }else{

            //refresh groups
            $system->getUserGroupIds(null, true);
            $mysqli = $system->getMysqli();

            $row = mysql__select_row_assoc($mysqli,
                    'select svs_UGrpID, svs_Name, svs_Query FROM usrSavedSearches WHERE svs_ID='.$record['svs_ID']);

            if (!$row) {
                $system->addError(HEURIST_NOT_FOUND,
                    'Cannot duplicate filter criteria. Original filter not found');
            }elseif (!$system->isMember($row['svs_UGrpID'])) { //was has_access
                $system->addError(HEURIST_REQUEST_DENIED,
                    'Cannot duplicate filter criteria. Current user must be member for group');
            }else{
                    //get new name
                    $new_name = $row['svs_Name'].' (copy)';//$mysqli->real_escape_string(

                    $query = 'INSERT INTO `usrSavedSearches` '
                    .'(`svs_Name`,`svs_Added`,`svs_Modified`,`svs_Query`,`svs_UGrpID`,`svs_ExclusiveXSL`)'
                    .' SELECT ?,`svs_Added`,`svs_Modified`,`svs_Query`,`svs_UGrpID`,`svs_ExclusiveXSL` '
                    .' FROM usrSavedSearches WHERE svs_ID = '.$record['svs_ID'];


                    $res= mysql__exec_param_query($mysqli, $query, array('s',$new_name));

                    //$res = $mysqli->query($query);

                    if($res!==true){
                        $system->addError(HEURIST_DB_ERROR, 'Cannot copy saved filter #'
                             .$record['svs_ID'].' in database', $mysqli->error);
                    }else{
                        return array('svs_ID'=>$mysqli->insert_id,
                            'svs_Name'=>$new_name,'svs_Query'=>$row['svs_Query'],'svs_UGrpID'=>$row['svs_UGrpID']);
                    }
            }

        }
        return false;

    }

    /**
    * Insert/update saved search
    *
    * @param mixed $system
    * @param mixed $record  - [ svs_ID, svs_UGrpID, svs_Name, svs_Query ]
    */
    function svsSave($system, $record){

        if( !(@$record['svs_ID']>0) && !@$record['svs_Name']){
            $system->addError(HEURIST_INVALID_REQUEST, 'Name not defined');//for new
        }elseif(!(@$record['svs_ID']>0) && !@$record['svs_Query']){
            $system->addError(HEURIST_INVALID_REQUEST, 'Query not defined');//for new
        }else{

            //refresh groups
            $system->getUserGroupIds(null, true);

            if (!$system->isMember(@$record['svs_UGrpID'])) { //was has_access
                $system->addError(HEURIST_REQUEST_DENIED,
                    'Cannot update filter ' .$record['svs_Name']. '.<br>You must be a member of the ' .$record['svs_UGrpID']. ' group to edit this filter.<br><br>'
                    .'Please ask your database owner to add you to the group.');
            }else{

                $is_new = false;
                if(is_array(@$record['svs_ID'])){
                    $rec_IDs = $record['svs_ID'];
                }elseif (@$record['svs_ID']>0){
                    $rec_IDs = array($record['svs_ID']);
                }else{
                    $rec_IDs = array(-1);//new
                    $is_new = true;
                }

                //svs_UGrpID is not defined
                if(array_key_exists('svs_UGrpID', $record) && !($record['svs_UGrpID']>0)) //not defined or all|bookmark
                {
                    if($is_new){
                        $record['svs_UGrpID'] = $system->getUserId();
                    }else{
                        unset($record['svs_UGrpID']);
                    }
                }



                foreach($rec_IDs as $svs_ID){
                    $record['svs_ID'] = $svs_ID;
                    $res = mysql__insertupdate($system->getMysqli(), 'usrSavedSearches', 'svs', $record);
                    if(is_numeric($res)>0){
                        return $res; //returns affected record id
                    }else{
                        $system->addError(HEURIST_DB_ERROR, 'Cannot update saved filter #'.$svs_ID.' in database', $res);
                    }
                }


            }
        }
        return false;
    }

    /**
    * Delete saved search
    *
    * @param mixed $system
    * @param mixed $rec_ids  - comma separeted list of IDs
    */
    function svsDelete($system, $rec_ids, $ugrID=null){

        //verify that current user can delete
        if (!$system->hasAccess($ugrID)) {
            $system->addError(HEURIST_REQUEST_DENIED,
                'Cannot delete filter criteria. Current user must be an administrator for group');
            return false;
        }

            if(!$ugrID>0){
                $ugrID = $system->getUserId();
            }

            $rec_ids = prepareIds($rec_ids);

            if (isEmptyArray($rec_ids)) {
                $system->addError(HEURIST_INVALID_REQUEST);
                return false;
            }

                $query = 'delete from usrSavedSearches where svs_ID in ('. join(', ', $rec_ids) .') and svs_UGrpID='.$ugrID;

                $mysqli = $system->getMysqli();
                $res = $mysqli->query($query);

                if(!$res){
                    $system->addError(HEURIST_DB_ERROR,'Cannot delete saved search', $query.' '.$mysqli->error );
                    return false;
                }

                $cnt = $mysqli->affected_rows;
                if($cnt>0){
                    return array("status"=>HEURIST_OK, "data"=> $cnt);
                }else{
                    $system->addError(HEURIST_NOT_FOUND);
                    return false;
                }
    }

    /**
    * Save saved searches tree data into sysUGrps
    */
    function svsSaveTreeData($system, $data){

        $mysqli = $system->getMysqli();

        $groups = json_decode($data, true);

        $personal_data = array();

        $ugrID = $system->getUserId();
        $ugr_groups = $system->getUserGroupIds(null, true);//always get latest
        $lastID = null;

        foreach($groups as $id=>$treedata){

            if($id=="bookmark" || $id=="all"){
                array_push( $personal_data, '"'.$id.'":'.json_encode($treedata) );
            }elseif(in_array($id, $ugr_groups)){
                //check date of modification
                $res = mysql__insertupdate( $mysqli, 'sysUGrps', 'ugr', array('ugr_ID'=>$id, 'ugr_NavigationTree'=>json_encode($treedata) ));
                if(!is_int($res)){
                    $system->addError(HEURIST_DB_ERROR, 'Cannot update navigation tree (personal) on server sode', $res);
                    return false;
                }

                $lastID = $id;
            }
        }

        if(!empty($personal_data)){

                $res = mysql__insertupdate( $mysqli, 'sysUGrps', 'ugr',
                   array( 'ugr_ID'=>$ugrID, 'ugr_NavigationTree'=>implode(',', $personal_data)));

                if(!is_int($res)){
                    $system->addError(HEURIST_DB_ERROR, 'Cannot update navigation tree (personal) on server sode', $res);
                    return false;
                }

                $lastID = $ugrID;
        }

        if($lastID>0){
            //get modification time
            $date = mysql__select_value( $mysqli, 'SELECT `ugr_Modified` FROM `sysUGrps` WHERE ugr_ID='.$lastID);
            return $date;
        }

        $system->addError(HEURIST_INVALID_REQUEST, 'No data provided to update tree on server side.'
        .' This may be due to a network outage or minor database corruption. It means the changes you have just made may not have been'
        .' written into the database - please reload the page and check to see if they have been saved, try again, and '
        . CONTACT_HEURIST_TEAM.' if the problem persists');
        return false;
    }

    //
    // $grpID - load tree data only for particular group
    //
    function svsGetTreeData($system, $grpID=null){

        $mysqli = $system->getMysqli();

        $ugrID = $system->getUserId();

        if($grpID!=null){
            $groups = prepareIds($grpID, true);
        }else{
            //load personal treeviews - rules, my filters (all) and bookmarks
            $groups = $system->getUserGroupIds();
        }

        // 5 - websearch
        if(is_array($groups) && count($groups)==1){
            $where = ' = '.$groups[0];
        }elseif(is_array($groups) && count($groups)>1){
            $where =  ' in ('.implode(',',$groups).')';
        }else {
            $where = ' = '.$ugrID; //only personal
        }

        $ret = array();

        $query = 'SELECT `ugr_ID`, `ugr_NavigationTree`, `ugr_Modified` FROM `sysUGrps` WHERE ugr_ID'.$where;
        $res = $mysqli->query($query);
        if(!$res){
            $system->addError(HEURIST_DB_ERROR, 'Cannot retrieve filters treeviews', $mysqli->error);
            return false;
        }
        while ($row = $res->fetch_row()) {
            if($row[1]){
                if($row[0]==$ugrID){
                    array_push($ret, $row[1] );
                }else{
                    //add modification date for groups
                    $treedata = $row[1];
                    //$datetime = new DateTime($row[2]);
                    //$datetime->format(DateTime::ISO8601)
                    $treedata = '{"modified":"'.$row[2].'",'.substr($treedata,1);

                    array_push($ret, '"'.$row[0].'":'.$treedata );
                }
            }
        }
        $res->close();

        return '{'.implode(',', $ret).'}';
    }

?>