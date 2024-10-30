<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;

    /**
    * db access to sysImportFiles table
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

class DbSysImportFiles extends DbEntityBase
{
    private $is_table_exists = true;


    public function init(){

        $this->requireAdminRights = false;

        $mysqli = $this->system->get_mysqli();

        $this->is_table_exists = hasTable($mysqli, 'sysImportFiles');

        if(!$this->is_table_exists){

    $query = "CREATE TABLE IF NOT EXISTS `sysImportFiles` (
    `sif_ID` int(11) unsigned NOT NULL auto_increment
    COMMENT 'Sequentially generated ID for delimited text or other files imported into temporary tables ready for processing',
    `sif_FileType` enum('delimited') NOT NULL Default 'delimited' COMMENT 'The type of file which has been read into a temporary table for this import',
    `sif_UGrpID` int(11) unsigned NOT NULL default 0 COMMENT 'The user ID of the user who imported the file',
    `sif_TempDataTable` varchar(255) NOT NULL default '' COMMENT 'The name of the temporary data table created by the import',
    `sif_ProcessingInfo` mediumtext  COMMENT 'Primary record type, field matching selections, dependency list etc. created while processing the temporary data table',
    PRIMARY KEY  (`sif_ID`))";


            if ($mysqli->query($query)) {
                $this->is_table_exists = true;
            }

        }

    }

    /**
    */
    public function isvalid(){
        return $this->is_table_exists && parent::isvalid();
    }


    /**
    *  search import sessions
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

        if(parent::search()===false){
            return false;
        }

        $orderBy = '';
        //compose WHERE
        $where = array();

        $pred = $this->searchMgr->getPredicate('sif_ID');
        if($pred!=null) {array_push($where, $pred);}

        $pred = $this->searchMgr->getPredicate('sif_UGrpID');
        if($pred!=null) {array_push($where, $pred);}

        $needCheck = false;

        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){

            $this->data['details'] = 'sif_ID';

        }elseif(@$this->data['details']=='name'){

            $this->data['details'] = 'sif_ID,sif_TempDataTable';

        }elseif(@$this->data['details']=='list'){

            $this->data['details'] = 'sif_ID,sif_TempDataTable,sif_ProcessingInfo';

        }elseif(@$this->data['details']=='full'){

            $this->data['details'] = implode(',', $this->fields );
        }else{
            $needCheck = true;
        }

        if(!is_array($this->data['details'])){ //specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);
        }

        if(empty($where) && in_array('sif_ID', $this->data['details'])){
            $orderBy = ' ORDER BY sif_ID DESC';// newest to oldest
        }

        //validate names of fields
        if($needCheck && !$this->_validateFieldsForSearch()){
            return false;
        }

        $is_ids_only = (count($this->data['details'])==1);
        $from_table = $this->config['tableName'];

        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS  '.implode(',', $this->data['details']).' FROM '.$from_table;

         if(!empty($where)){
            $query = $query.SQL_WHERE.implode(SQL_AND,$where);
         }
         $query = $query.$orderBy.$this->searchMgr->getLimit().$this->searchMgr->getOffset();


        $res = $this->searchMgr->execute($query, $is_ids_only, $from_table);
        return $res;

    }

    //
    //
    //
    public function save(){

        $ret = parent::save();

        if($ret!==false){
            /* treat thumbnail image
            foreach($this->records as $record){
                if(in_array(@$record['trm_ID'], $ret)){
                    $thumb_file_name = @$record['trm_Thumb'];

                    //rename it to recID.png
                    if($thumb_file_name){
                        parent::renameEntityImage($thumb_file_name, $record['trm_ID']);
                    }
                }
            }*/
        }

        return $ret;
    }


    //
    //
    //
    public function delete($disable_foreign_checks = false){

        if(!$this->_validatePermission()){
            return false;
        }

        $rec_ID = @$this->data[$this->primaryField];
        $rec_ID = intval(@$rec_ID);

        $delete_all_import_tables = false;

        if($rec_ID>0){
            $where = " where sif_ID=".$rec_ID;
        }else{
            $where = " where sif_ID>0";
            $delete_all_import_tables = true;
        }

        $mysqli = $this->system->get_mysqli();

        $res = mysql__select_all($mysqli,
                "select sif_ID, sif_ProcessingInfo  from sysImportFiles".$where, 1);

        if(!$res){
            $this->system->addError(HEURIST_NOT_FOUND,
                "No data found. Cannot delete from import sessions table");
            return false;
        }

        //drop import data
        foreach($res as $row){
        foreach($row as $id => $session){

            $session = json_decode($session, true);
            $table_name = preg_replace(REGEX_ALPHANUM, "", $session['import_table']);//for snyk

            $query = "drop table IF EXISTS `$table_name`";

            if (!$mysqli->query($query)) {
                $this->system->addError(HEURIST_DB_ERROR,
                        'Cannot drop import session table: '.$session['import_table'].' '.$mysqli->error);
                return false;
            }
        }
        }

        if (!$mysqli->query("delete from sysImportFiles ".$where)) {
                $this->system->addError(HEURIST_DB_ERROR,
                        'Cannot delete data from list of imported files', $mysqli->error);
                return false;
        }

        if(!$delete_all_import_tables){
            return true;
        }

        $tables = mysql__select_list2($mysqli, "SHOW TABLES LIKE 'import20%'");
        foreach($tables as $table_name){
            $query = "drop table IF EXISTS `$table_name`";
            $mysqli->query($query);
        }

        return true;

    }


}
?>
