<?php
namespace hserv\structure;

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* conceptCode.php - gets local code by concept code and vice versa
*
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     4
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage
*/
class ConceptCode {

 /**
 * Construct won't be called inside this class and is uncallable from
 * the outside. This prevents instantiating this class.
 * This is by purpose, because we want a static class.
 */
private function __construct() {}

private static $initialized = false;
private static $system = null;
private static $database_id = null;

private static function initialize($init_system=null)
{
    if($init_system!=null){
        self::$system = $init_system;
    }
    elseif (self::$initialized){
        return;
    }else{

        global $system;
        self::$system = $system;
    }

    self::$initialized = true;

    self::$database_id = self::$system->settings->get('sys_dbRegisteredID');
}

public static function setSystem($new_system){
    self::initialize($new_system);
}


/**
* translate a local id for a given table to it's concept ID
* @param     string $lclID local id of row in $tableName table
* @param     string $tableName name of table
* @param     string $fieldNamePrefix column name prefix used in $tableName table
* @return    string concept id (dbID-origID) or null if no HEURIST DBID
* @uses      self::$database_id
*/
private static function getConceptID($lclID, $tableName, $fieldNamePrefix) {

    self::initialize();

    if($lclID>0){

        $query = "select {$fieldNamePrefix}OriginatingDBID,{$fieldNamePrefix}IDInOriginatingDB from $tableName where {$fieldNamePrefix}ID = $lclID";

        $ids = mysql__select_row(self::$system->get_mysqli(), $query);

        //return "".$ids[0]."-".$ids[1];
        if (is_array($ids) && count($ids) == 2 && is_numeric($ids[0]) && is_numeric($ids[1])) {
            return "" . $ids[0] . '-' . $ids[1];
        } elseif (self::$database_id) {
            return '' . self::$database_id . '-' . $lclID;
        } else {
            return '0000-'.$lclID;
        }

    }else{
        return '';
    }
}
/**
* return a terms concpet ID
* @param     int $lclTermID local Term ID
* @return    mixed from getConceptID()
* @uses      getConceptID()
*/
public static function getTermConceptID($lclTermID) {
    return self::getConceptID($lclTermID, "defTerms", "trm_");
}
/**
* return a detailTypes concpet ID
* @param     int $lclDtyID local detailType ID
* @return    mixed from getConceptID()
* @uses      getConceptID()
*/
public static function getDetailTypeConceptID($lclDtyID) {
    return self::getConceptID($lclDtyID, "defDetailTypes", "dty_");
}
/**
* return a recTypes concpet ID
* @param     int $lclRecTypeID local recType ID
* @return    mixed from getConceptID()
* @uses      getConceptID()
*/
public static function getRecTypeConceptID($lclRecTypeID) {
    return self::getConceptID($lclRecTypeID, "defRecTypes", "rty_");
}
/**
* return a ontologies concpet ID
* @param     int $lclOntID local ontology ID
* @return    mixed from getConceptID()
* @uses      getConceptID()
*/
public static function getOntologyConceptID($lclOntID) {
    return self::getConceptID($lclOntID, "defOntologies", "ont_");
}

//-------------------
/**
* get local id for concept id of a given table row
* @global    type description of global variable usage in a function
* @staticvar type [$varname] description of static variable usage in function
* @param     string $conceptID concept id of row in $tableName table
* @param     string $tableName name of table
* @param     string $fieldNamePrefix column name prefix used in $tableName table
* @return    int id or null if not found
* @uses      self::$database_id
*/
private static function getLocalID($conceptID, $tableName, $fieldNamePrefix) {

    self::initialize();

    $ids = explode('-', $conceptID);
    $res_id = null;
    if (is_array($ids) && (count($ids) == 1 && is_numeric($ids[0]))
            || (count($ids) == 2 && is_numeric($ids[1]) && ( (!($ids[0] > 0)) || $ids[0] == self::$database_id)) )
    {
        //local or defined in this database

        if (count($ids) == 2) {
            $res_id = $ids[1];//this code is already local
        } else {
            $res_id = $ids[0];
        }

        $query = "select {$fieldNamePrefix}ID from $tableName where {$fieldNamePrefix}ID=" . intval($res_id);

        $res_id = mysql__select_value(self::$system->get_mysqli(), $query);


    } elseif (is_array($ids) && count($ids) == 2 && is_numeric($ids[0]) && is_numeric($ids[1])) {
 $query = "select {$fieldNamePrefix}ID from $tableName where {$fieldNamePrefix}OriginatingDBID=".intval($ids[0])
             . SQL_AND . $fieldNamePrefix . "IDInOriginatingDB=" . intval($ids[1]);

        $res_id = mysql__select_value(self::$system->get_mysqli(), $query);
    }

    if (!($res_id>0)) {
        $res_id = null;
    }
    return $res_id;
}
/**
* return local term id for a terms concept ID
* @param     int $trmConceptID Term concept ID
* @return    mixed from getLocalID()
* @uses      getLocalID()
*/
public static function getTermLocalID($trmConceptID) {
    return self::getLocalID($trmConceptID, "defTerms", "trm_");
}
/**
* return local detailType id for a detailTypes concept ID
* @param     int $dtyConceptID detailType concept ID
* @return    mixed from getLocalID()
* @uses      getLocalID()
*/
public static function getDetailTypeLocalID($dtyConceptID) {
    return self::getLocalID($dtyConceptID, "defDetailTypes", "dty_");
}
/**
* return local recType id for a recTypes concept ID
* @param     int $rtyConceptID recType concept ID
* @return    mixed from getLocalID()
* @uses      getLocalID()
*/
public static function getRecTypeLocalID($rtyConceptID) {
    return self::getLocalID($rtyConceptID, "defRecTypes", "rty_");
}
/**
* return local ontology id for a ontologys concept ID
* @param     int $ontConceptID ontology concept ID
* @return    mixed from getLocalID()
* @uses      getLocalID()
*/
public static function getOntologyLocalID($ontConceptID) {
    return self::getLocalID($ontConceptID, "defOntologies", "ont_");
}

}