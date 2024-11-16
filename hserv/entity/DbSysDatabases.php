<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;

    /**
    * db access to Heurist_DBs_index.sysIdentifications and sysUsers tables
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

class DbSysDatabases extends DbEntityBase
{
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

        //compose WHERE
        $where = array();
        $current_user_email = null;

        $mysqli = $this->system->getMysqli();
        $user = user_getById($mysqli, $this->system->getUserId());
        if($user){
            $current_user_email = $user['ugr_eMail'];
        }

        $order   = array();
        $records = array();

        $query = "show databases where `database` like '".HEURIST_DB_PREFIX."%'";
        $res = $this->system->getMysqli()->query($query);

        $query = array();
        while ($row = $res->fetch_row()) {
            $database  = $row[0];
            $test = strpos($database, HEURIST_DB_PREFIX);
            if ($test === 0){
                //$query[] = "SELECT '".$database."' as sys_Database, sys_dbRegisteredID, sys_dbName FROM `"
                //        .$database."`.sysIdentification";
                $records[$database] = array($database);
                $order[] = $database;
            }
        }

        $res->close();

        return array('queryid'=>@$this->data['request_id'],  //query unqiue id set in doRequest
            'entityName'=>$this->config['entityName'],
            'pageno'=>@$this->data['pageno'],  //page number to sync
            'offset'=>@$this->data['offset'],
            'count'=>count($records),
            'reccount'=>count($records),
            'records'=>$records,

            'order'=>$order,
            'fields'=>array('sys_Database')//,'sys_dbRegisteredID','sys_dbName'),
        );

    }

    //
    // deletion and not allowed
    //
    public function delete($disable_foreign_checks = false){
        //virtual method
        return false;
    }
    public function save(){
        //virtual method
        return false;
    }

}
?>
