<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;

    /**
    * db access to sysIdentification table
    *
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

class DbSysIdentification extends DbEntityBase
{
/*
    'dty_Documentation'=>5000,
    'dty_EntryMask'=>'text',
    'dty_OriginatingDBID'=>'int',
    'dty_NameInOriginatingDB'=>255,
    'dty_IDInOriginatingDB'=>'int',

    'dty_OrderInGroup'=>'int',
    'dty_TermIDTreeNonSelectableIDs'=>1000,
    'dty_FieldSetRectypeID'=>'int',
    'dty_LocallyModified'=>'bool2'
*/

    /**
    *  search user or/and groups
    *
    *  sysUGrps.ugr_ID
    *  sysUGrps.ugr_Type
    *  sysUGrps.ugr_Name
    *  sysUGrps.ugr_Enabled
    *  sysUGrps.ugr_Modified
    *  sysUsrGrpLinks.ugl_UserID
    *  sysUsrGrpLinks.ugl_GroupID
    *  sysUsrGrpLinks.ugl_Role
    *  (omit table name)
    *
    *  other parameters :
    *  details - id|name|list|all or list of table fields
    *  offset
    *  limit
    *  request_id
    *
    *  @todo overwrite
    */
    public function search(){

        $query = 'SELECT * FROM sysIdentification LIMIT 1';

        $mysqli = $this->system->getMysqli();
        $res = $mysqli->query($query);

        if (!$res){
            $this->system->addError(HEURIST_DB_ERROR, 'Search error', $mysqli->error);
            return false;
        }

        // read all field names
        $_flds =  $res->fetch_fields();
        $fields = array();
        foreach($_flds as $fld){
            array_push($fields, $fld->name);
        }
        array_push($fields, 'sys_dbVersion');

        $records = array();
        $order = array();
        // load record
        $row = $res->fetch_row();
        if($row){
            $row[] = $row[2].'.'.$row[3].'.'.$row[4];//sys_dbVersion
            $records[$row[0]] = $row;
            $order =  $row[0];
        }
        $res->close();


        $response = array(
                'count'=>1,
                'reccount'=>1,
                'fields'=>$fields,
                'records'=>$records,
                'order'=>$order,
                'entityName'=>'sysIdentification');
        return $response;
    }

    public function save(){


        //add new field into sysIdentification
        $sysValues = $this->system->settings->get();
        if(!array_key_exists('sys_ExternalReferenceLookups', $sysValues))
        {
            $query = "ALTER TABLE `sysIdentification` ADD COLUMN `sys_ExternalReferenceLookups` TEXT default NULL COMMENT 'Record type-function-field specifications for lookup to external reference sources such as GeoNames'";
            $mysqli = $this->system->getMysqli();
            $res = $mysqli->query($query);
        }


        $ret = parent::save();

        if($ret!==false){
            //copy temporary file
            foreach($this->records as $idx=>$record){
                $sys_ID = @$record['sys_ID'];
                if($sys_ID>0 && in_array($sys_ID, $ret)){
                    //treat database image
                    $thumb_file_name = @$record['sys_Thumb'];
                    //rename it to recID.png
                    if($thumb_file_name){
                        parent::renameEntityImage($thumb_file_name, $sys_ID);
                    }
                }
            }
        }
        return $ret;
    }
    //
    // deletion not allowed for db properties
    //
    public function delete($disable_foreign_checks = false){
        //virtual method
        return false;
    }


}
?>
