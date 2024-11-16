<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;

    /**
    * db access to usrBoomarks table
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

require_once dirname(__FILE__).'/../records/search/recordFile.php';

class DbUsrBookmarks extends DbEntityBase
{

    /**
    *  search bookmarks
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


        $this->searchMgr->addPredicate('bkm_ID');
        $this->searchMgr->addPredicate('bkm_UGrpID');
        $this->searchMgr->addPredicate('bkm_RecID');
        $this->searchMgr->addPredicate('bkm_Rating');

        if(@$this->data['details']=='id'){
            $this->searchMgr->setSelFields('bkm_ID');
        }else{
            $this->searchMgr->setSelFields('bkm_ID,bkm_UGrpID,bkm_RecID,bkm_Rating,bkm_PwdReminder,bkm_Notes');
        }

        return $this->searchMgr->composeAndExecute(null);
    }


    //
    // validate permission for edit/delete bookmark
    // for delete and assign see appropriate methods
    //
    protected function _validatePermission(){

        if(!$this->system->isDbOwner() && !isEmptyArray($this->recordIDs)){ //there are records to update/delete

            //$ugrs = $this->system->getUserGroupIds();
            $ugrID = $this->system->getUserId();

            $mysqli = $this->system->getMysqli();

            $recIDs_norights = mysql__select_list($mysqli, $this->config['tableName'], $this->primaryField,
                    'bkm_ID in ('.implode(',', $this->recordIDs).') AND bkm_UGrpID!='.$ugrID);


            $cnt = count($recIDs_norights);

            if($cnt>0){
                $this->system->addError(HEURIST_REQUEST_DENIED,
                (($cnt==1 && (!is_array($this->records) || count($this->records)==1))
                    ? 'Bookmark belongs'
                    : $cnt.' Bookmark belong')
                    .' to other user. Insufficient rights (logout/in to refresh) for this operation');// or workgroup you are not a member
                return false;
            }
        }

        return true;
    }

    //
    //
    //
    protected function prepareRecords(){

        $ret = parent::prepareRecords();

        //add specific field values
        foreach($this->records as $idx=>$record){
            $rec_ID = intval(@$record[$this->primaryField]);
            $isinsert = ($rec_ID<1);
            if($isinsert && !($this->records[$idx]['bkm_UGrpID']>0)){
                $this->records[$idx]['bkm_UGrpID'] = $this->system->getUserId();
            }
            if($isinsert || !$this->records[$idx]['bkm_Added']){
                $this->records[$idx]['bkm_Added'] = date('Y/m/d H:i:s');
            }
            $this->records[$idx]['bkm_Modified'] = date(DATE_8601);//reset
        }

        return $ret;

    }

    //
    //
    //
    public function delete($disable_foreign_checks = false){

        $this->recordIDs = null; //reset to obtain ids from $data
        $this->isDeleteReady = false;

        $this->foreignChecks = array(
                    array('SELECT count(tag_ID) FROM usrBookmarks, usrTags, usrRecTagLinks '
                    .'WHERE tag_ID=rtl_TagID AND tag_UGrpID='.$this->system->getUserId()
                    .' AND rtl_RecID=bkm_RecID AND bkm_ID',
                    'It is not possible to remove bookmark. Bookmarked record has personal tags')
                );

        return parent::delete();
    }


    //
    // add/remove bookmarks in batch, set rating in batch
    //
    public function batch_action(){

        $is_unbookmark = (@$this->data['mode']=='unbookmark');

        $rec_IDs = prepareIds(@$this->data['bkm_RecID']);//these are rec_IDs from Record table
        $bkm_IDs = prepareIds(@$this->data['bkm_ID']);

        if(empty($rec_IDs) && empty($bkm_IDs)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of identificators');
            return false;
        }

        $mysqli = $this->system->getMysqli();

        //bookmarks id not defined - find them by record ids
        if(empty($bkm_IDs)){
            $query =  'bkm_RecID in (' . join(',', $rec_IDs).')';

            $rec_RecTypeID = @$this->data['rec_RecTypeID'];
            if($rec_RecTypeID>0){
                $query = ', Records where (rec_RecTypeID='.$rec_RecTypeID.') and (rec_ID=bkm_RecID) and '.$query;
            }else{
                $query = SQL_WHERE.$query;
            }

            //get bookmarks
            $query = 'select bkm_ID from usrBookmarks '.$query
                    . ' and bkm_UGrpID = ' . $this->system->getUserId();

            $bkm_IDs = mysql__select_list2($mysqli, $query);
            $bkm_IDs = prepareIds( $bkm_IDs );
        }


        if($is_unbookmark){
            //remove bookmarks and detach personal tags

            if(!empty($bkm_IDs)){

                $keep_autocommit = mysql__begin_transaction($mysqli);

                $query = 'DELETE usrRecTagLinks FROM usrBookmarks LEFT JOIN usrRecTagLinks ON rtl_RecID=bkm_RecID '
                .' WHERE bkm_ID IN ('.implode(',', $bkm_IDs).') AND bkm_UGrpID=' .$this->system->getUserId();
                $res = $mysqli->query($query);
                if(!$res){
                    $mysqli->rollback();
                    if($keep_autocommit===true) {$mysqli->autocommit(true);}

                    $this->system->addError(HEURIST_DB_ERROR,"Cannot detach personal tags from records", $mysqli->error );
                    return false;
                }
                $res_tag_removed = $mysqli->affected_rows;

                $query = 'DELETE FROM usrBookmarks '
                .' WHERE bkm_ID in ('.implode(',', $bkm_IDs).') and bkm_UGrpID=' .$this->system->getUserId();
                $res = $mysqli->query($query);
                if(!$res){
                    $mysqli->rollback();
                    if($keep_autocommit===true) {$mysqli->autocommit(true);}

                    $this->system->addError(HEURIST_DB_ERROR,"Cannot remove bookmarks", $mysqli->error );
                    return false;
                }
                $res_bookmark_removed = $mysqli->affected_rows;

                $mysqli->commit();

                return array('processed'=>count($bkm_IDs),
                             'tag_detached'=>$res_tag_removed,
                             'deleted'=>$res_bookmark_removed);

            }else{
                $this->system->addError(HEURIST_NOT_FOUND,
                    'None of the record(s) is/are bookmarked. Nothing to unbookmark');
                return false;
            }


        }else{
            //set rating

            $rating = intval(@$this->data['rating']);
            if(!($rating>=0 && $rating<6)){
                $this->system->addError(HEURIST_INVALID_REQUEST, 'Rating is out of range (0~5)');
                return false;
            }

            if(!empty($bkm_IDs)){
                $query =  'bkm_ID in (' . join(',', $bkm_IDs).')';

                $query =  'update usrBookmarks set bkm_Rating = ' . $rating . SQL_WHERE.$query
                        .' and bkm_UGrpID = ' . $this->system->getUserId();

                $res = $mysqli->query($query);
                if(!$res){
                    $this->system->addError(HEURIST_DB_ERROR, 'Cannot set rating', $mysqli->error);
                    return false;
                }

                $update_count = $mysqli->affected_rows;
                return array('processed'=>count($bkm_IDs),
                             'updated'=>$update_count);

            }else{
                $this->system->addError(HEURIST_NOT_FOUND,
                    'Rating can be set for bookmarked records only. None of provided are bookmarked');
                return false;
            }
        }

    }
}
?>
