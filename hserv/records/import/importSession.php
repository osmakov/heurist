<?php

/**
* importSession.php: methods to work with import session table and import tables
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
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
*
*/
class ImportSession {

    private static $system = null;
    private static $mysqli = null;
    private static $initialized = false;

private static function initialize()
{
    if (self::$initialized)  {return;}

    global $system;
    self::$system  = $system;
    self::$mysqli = $system->getMysqli();
    self::$initialized = true;
}

/**
* Loads import sessions by ID
*
* @param mixed $import_id
* @return string as error of array with session values
*/
public static function load($import_id){

    self::initialize();

    if($import_id && is_numeric($import_id)){

        $res = mysql__select_row(self::$mysqli,
            "select sif_ProcessingInfo , sif_TempDataTable from sysImportFiles where sif_ID=".intval($import_id));

        $session = json_decode($res[0], true);
        $session["import_id"] = $import_id;
        $session["import_file"] = $res[1];
        if(!@$session["import_table"]){ //backward capability
            $session["import_table"] = $res[1];
        }

        return $session;
    }else{
        self::$system->addError(HEURIST_NOT_FOUND, 'Import session #'.$import_id.' not found');
        return false;
    }
}

/**
* update record in import session table
*
* @param mixed $mysqli
* @param mixed $imp_session
*/
public static function save($imp_session){

    self::initialize();

    $imp_id = mysql__insertupdate(self::$mysqli, "sysImportFiles", "sif",
        array("sif_ID"=>@$imp_session["import_id"],
            "sif_UGrpID"=>self::$system->getUserId(),
            "sif_TempDataTable"=>$imp_session["import_name"],
            "sif_ProcessingInfo"=>json_encode($imp_session) ));

    if(intval($imp_id)<1){
        return $imp_id;
    }else{
        $imp_session["import_id"] = $imp_id;
        return $imp_session;
    }
}


//
// 1. saves new primary rectype in session
// 2. returns treeview strucuture for given rectype
//
public static function setPrimaryRectype($imp_ID, $rty_ID, $sequence){

     self::initialize();

     if($sequence!=null){
        //get session
        $imp_session = self::load($imp_ID);
        if($imp_session==false){
                return false;
        }
        //save session with new ID
        $imp_session['primary_rectype'] = $rty_ID;
        $imp_session['sequence'] = $sequence;
        $res = self::save($imp_session);
        if(!is_array($res)){
            self::$system->addError(HEURIST_DB_ERROR, 'Cannot save import session #'.$imp_ID, $res);
            return false;
        }

        return 'ok';
     }else{
        //get dependent record types
        try{
            return dbs_GetRectypeStructureTree(self::$system, $rty_ID, 6, 'resource');//?? 6
        }catch(Exception $e){
            $sMsg = $e->getCode().' ('.$e->getErrorType().'): '.$e->getMessage();
            self::$system->addError(HEURIST_ERROR, 'Cannot generate structure tree for record type '.$rty_ID.' session #'.$imp_ID, $sMsg);
            return false;
        }


     }
}


//
// searches all sessions and find matchings for given rectype
// it is used to quick restore field matching for chunks csv import (similar csv files)
//
public static function getMatchingSamples($imp_ID, $rty_ID){

     self::initialize();

     $matching = array();

     if(!($imp_ID>0)) {$imp_ID = 0;}

     $sessions = mysql__select_assoc2(self::$mysqli, 'select sif_ID, sif_ProcessingInfo from sysImportFiles where sif_ID!='.$imp_ID);

     foreach($sessions as $id=>$imp_session){

        $imp_session = json_decode($imp_session, true);
        if($imp_session!==false && is_array(@$imp_session['sequence'])){

            foreach($imp_session['sequence'] as $seq){

                if($seq['rectype']==$rty_ID && !isEmptyArray(@$seq['mapping_flds'])){
                    $matching[ $imp_session['import_name'] ] = $seq['mapping_flds'];
                    break;
                }
            }
        }
     }

     return $matching;
}



/**
* load records from import table
*
* @param mixed $rec_id
* @param mixed $import_table
*/
public static function getRecordsFromImportTable1( $import_table, $imp_ids) {

    self::initialize();

    $mysqli = self::$system->getMysqli();

    $imp_ids = prepareIds($imp_ids);

    $query = 'SELECT * FROM `'.$import_table.'` WHERE imp_id IN ('. implode( ',', $imp_ids ) .')';
    $res = mysql__select_row($mysqli, $query);

    return $res;
}

//
// $output - csv, json
//
public static function getRecordsFromImportTable2( $import_table, $id_field, $mode, $mapping, $offset, $limit=100, $output ){

    self::initialize();

    $mysqli = self::$system->getMysqli();

    if($id_field==null || $id_field=='' || $id_field=='null' || $mode=='all'){
        $where  = '1';
        $order_field = 'imp_id';
    }elseif($mode=='insert'){
        $where  = " ($id_field<0 OR $id_field IS NULL) ";
        $order_field = $id_field;
    }else{
        $where  = " ($id_field>0) ";
        $order_field = $id_field;
    }

    if(!($offset>0)) {$offset = 0;}
    if(!is_int($limit)) {$limit = 100;}

    if($mapping!=null && !is_array($mapping)){
        $mapping = json_decode($mapping, true);
    }

    if(!isEmptyArray($mapping)){


        $field_idx = array_keys($mapping);

        $sel_fields = array($order_field);

        foreach($field_idx as $idx){
            if('field_'.$idx!=$id_field){
                array_push($sel_fields, 'field_'.$idx);
            }
        }
        if($mode=='insert' && count($sel_fields)>1){
            $order_field = $sel_fields[1];
        }

        $sel_fields = 'DISTINCT '.implode(',',$sel_fields);
    }else{
        $sel_fields = '*';
    }


    $query = "SELECT $sel_fields FROM $import_table WHERE $where ORDER BY $order_field";
    if($limit>0){
        $query = $query." LIMIT $limit OFFSET $offset";
    }


    $res = mysql__select_all($mysqli, $query, 0, ($output=='csv'?0:30) );
    return $res;
}



} //end class
?>
