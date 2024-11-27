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
        $email_filter = '';
        $database_filter = '';
        $mysqli = $this->system->getMysqli();

        if(@$this->data['ugr_eMail']){
            $email_filter = $this->data['ugr_eMail'];
        }

        $order = [];
        $records = [];

        $databases = mysql__getdatabases4($mysqli, false, $database_filter, $email_filter, 'user');

        foreach($databases as $database){
            $records[$database] = [$database];
            $order[] = $database;
        }

        return [
            'queryid'=> @$this->data['request_id'],  //query unqiue id set in doRequest
            'entityName'=> $this->config['entityName'],
            'pageno'=> @$this->data['pageno'],  //page number to sync
            'offset'=> @$this->data['offset'],
            'count'=> count($records),
            'reccount'=> count($records),
            'records'=> $records,

            'order'=> $order,
            'fields'=> ['sys_Database']
        ];

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
