<?php
namespace hserv\entity;
use hserv\entity\DbEntityBase;

    /**
    * db access to usrReminders table
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

class DbUsrReminders extends DbEntityBase
{

 public function __construct( $system, $data=null ) {

       if($data==null){
           $data = array();
       }
       if(!@$data['entity']){
           $data['entity'] = 'usrReminders';
       }

       parent::__construct( $system, $data );
    }

    /**
    *  search usrReminders
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

        if(!@$this->data['rem_OwnerUGrpID']){
            $this->data['rem_OwnerUGrpID'] = $this->system->getUserId();
        }

        if(parent::search()===false){
              return false;
        }

        $sup_tables = null;
        $sup_where = null;

        $this->searchMgr->addPredicate('rem_ID');
        $this->searchMgr->addPredicate('rem_OwnerUGrpID');
        $this->searchMgr->addPredicate('rem_RecID');
        $this->searchMgr->addPredicate('rem_Message');
        $this->searchMgr->addPredicate('rem_ToWorkgroupID');
        $this->searchMgr->addPredicate('rem_ToUserID');
        $this->searchMgr->addPredicate('rem_ToEmail');

        switch (@$this->data['details']){
            case 'id': $this->searchMgr->setSelFields('rem_ID'); break;
            case 'list':
            case 'name':
                $this->searchMgr->setSelFields('rem_ID,rem_RecID,rem_OwnerUGrpID,rem_ToWorkgroupID,rem_ToUserID,rem_ToEmail,rem_Message,rem_StartDate,rem_Freq,u1.ugr_Name as rem_ToWorkgroupName,concat(u2.ugr_FirstName,\' \',u2.ugr_LastName) as rem_ToUserName,rec_Title as rem_RecTitle');

                $sup_tables = ' LEFT JOIN sysUGrps u1 on rem_ToWorkgroupID=u1.ugr_ID '
                             .' LEFT JOIN sysUGrps u2 on rem_ToUserID=u2.ugr_ID, Records ';
                $sup_where = '(rec_ID=rem_RecID)';

                break;
            default: //case 'full':
                $this->searchMgr->setSelFields('rem_ID,rem_RecID,rem_OwnerUGrpID,rem_ToWorkgroupID,rem_ToUserID,rem_ToEmail,rem_Message,rem_StartDate,rem_Freq');
        }

        $orderby = $this->searchMgr->setOrderBy();

        return $this->searchMgr->composeAndExecute($orderby, $sup_tables, $sup_where);
    }

    //
    // validate permission for edit tag
    // for delete and assign see appropriate methods
    //
    protected function _validatePermission(){

        if(!$this->system->isDbOwner() && !isEmptyArray($this->recordIDs)){ //there are records to update/delete

            $ugrID = $this->system->getUserId();

            $mysqli = $this->system->getMysqli();

            $recIDs_norights = mysql__select_list($mysqli, $this->config['tableName'], $this->primaryField,
                    'rem_ID in ('.implode(',', $this->recordIDs).') AND rem_OwnerUGrpID!='.$ugrID);

            $cnt = count($recIDs_norights);

            if($cnt>0){
                $this->system->addError(HEURIST_REQUEST_DENIED,
                (($cnt==1 && (!is_array($this->records) || count($this->records)==1))
                    ? 'Reminder belongs'
                    : $cnt.' Reminders belong')
                    .' to other user. Insufficient rights (logout/in to refresh) for this operation');
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
            if($isinsert){
                if(!($this->records[$idx]['rem_OwnerUGrpID']>0)){
                    $this->records[$idx]['rem_OwnerUGrpID'] = $this->system->getUserId();
                }
                $this->records[$idx]['rem_Nonce'] = dechex(random_int(1,99));
                $this->fields['rem_Nonce'] = array();//to pass data to save
            }
            $this->records[$idx]['rem_Modified'] = date(DATE_8601);//reset
        }

        return $ret;

    }

    public function setmysql($mysqli){
        $this->system->setMysqli($mysqli);
    }

    //
    // batch action for reminders - email sending
    // It either
    // sends emails for given set of records (of param rec_IDs is defined)
    // OR
    // sends emails/reminders for records with rem_StartDate<=current date
    //
    public function batch_action(){

        $rec_IDs = prepareIds(@$this->data['rec_IDs']);
        $is_notification = (!empty($rec_IDs));//sends emails for given set of records
        $query = null;
        $record = null;

        if( (!empty($rec_IDs)) || (@$this->data['fields']['rem_RecID']>0) )
        {
            //sends emails for given set of records
            $ugrID = $this->system->getUserId();
            if(!($ugrID>0)){
                $this->system->addError(HEURIST_REQUEST_DENIED,
                    'You have to be logged in to send notification'
                    .' Insufficient rights (logout/in to refresh) for this operation');
                return false;
            }
            $is_notification = true;

            if(empty($rec_IDs)){
                $rec_IDs = array($this->data['fields']['rem_RecID']);
            }
            $record = $this->data['fields'];

            //$query = ' WHERE rem_RecID IN ('.implode(',',$rec_IDs).') AND rem_OwnerUGrpID='.$ugrID;
            if(!(@$record['rem_OwnerUGrpID']>0)){
                    $record['rem_OwnerUGrpID'] = $ugrID;
            }
        /*
        }elseif(@$this->data['fields'] && @$this->data['fields']['rem_RecID']>0){

            $rec_IDs = array($this->data['fields']['rem_RecID']);
            $is_notification = true;
            $record = $this->data['fields'];
            if(!(@$record['rem_OwnerUGrpID']>0)){
                    $record['rem_OwnerUGrpID'] = $ugrID;
            }
        */
        }else{
            //validate that this script is run from command line
            if (php_sapi_name() != 'cli'){
                $this->system->addError(HEURIST_REQUEST_DENIED,
                    'This script can be executed from CLI only');
                return false;
            }

            //sends emails/reminders for records with rem_StartDate<=current date
            // and rem_Freq
            $query =
                ' WHERE DATEDIFF(NOW(), rem_StartDate)>IF(rem_Freq="annually", 365, '
                .'IF(rem_Freq="monthly",30, IF(rem_Freq="weekly",7, 1)))';

            //'once','daily','weekly','monthly','annually
        }

        $report = array();

        $mysqli = $this->system->getMysqli();
/*
        if(!$mysqli){
echo 'Database connection not established '.spl_object_id($this->system).'   '.isset($mysqli).'>>>'."\n";
exit;
            //$this->system->addError(HEURIST_ERROR, 'Database connection not established');
            return false;
        }
*/

        if($query==null){
            $res = true;
        }else{
            //find reminders that can be send now
            $query = 'SELECT * FROM '.$this->config['tableName'].$query;
            $res = $mysqli->query($query);
        }

        if($res){
            while ($query==null || $record = $res->fetch_assoc()) {

            //
            // fill $recipients list
            //
            $recipients = array();
            if (@$record['rem_ToEmail']) {
                array_push($recipients, array(
                    "email" => $record['rem_ToEmail'],
                    "e"        => $record['rem_ToEmail'],
                    "u"        => null));
            }
            elseif (@$record['rem_ToUserID']) {

                $row = mysql__select_row($mysqli,
                    'select usr.ugr_FirstName,usr.ugr_LastName,usr.ugr_eMail FROM sysUGrps usr '
                    .' left join usrRemindersBlockList on rbl_UGrpID=usr.ugr_ID and rbl_RemID = '.intval($record['rem_ID'])
                    .' WHERE usr.ugr_Type="user" and usr.ugr_ID='.intval($record['rem_ToUserID']).' and isnull(rbl_RemID)');
                if ($row) {
                    array_push($recipients, array(
                        "email" => $row[0].' '.$row[1].' <'.$row[2].'>', //username <email>
                        "e"        => null,
                        "u"        => $record['rem_ToUserID']));
                }
            }
            elseif (@$record['rem_ToWorkgroupID']) {

                if($record['rem_ID']>0){

                    $query = 'select usr.ugr_FirstName,usr.ugr_LastName,usr.ugr_eMail,usr.ugr_ID '
                               .' from sysUsrGrpLinks left join sysUGrps usr on ugl_UserID=usr.ugr_ID'
                               .' left join usrRemindersBlockList on rbl_UGrpID=usr.ugr_ID and rbl_RemID = '.intval($record['rem_ID'])
                               .' WHERE ugl_GroupID = '.intval($record['rem_ToWorkgroupID']).' and isnull(rbl_RemID)';
                }else{
                    $query = 'select usr.ugr_FirstName,usr.ugr_LastName,ugr_eMail,usr.ugr_ID'
                                       .' from sysUsrGrpLinks left join sysUGrps usr on ugl_UserID=usr.ugr_ID'
                                       .' where ugl_GroupID='.intval($record['rem_ToWorkgroupID']);
                }


                $recs = mysql__select_all($mysqli, $query);

                foreach ($recs as $row){
                    array_push($recipients, array(
                        "email" => $row[0].' '.$row[1].' <'.$row[2].'>',
                        "e"        => null,
                        "u"        => $row[3]));
                }
            }

            //
            //
            //
            if(!empty($recipients)){

                if(!@$report[$record['rem_Freq']]) {$report[$record['rem_Freq']] = 0;}
                $report[$record['rem_Freq']] = $report[$record['rem_Freq']] + count($recipients);

            //sender params - reminder owner
            $owner = mysql__select_row($mysqli,
                'select usr.ugr_FirstName,usr.ugr_LastName,usr.ugr_eMail '
                    .'FROM sysUGrps usr where usr.ugr_Type="user" and usr.ugr_ID='
                    .intval($record['rem_OwnerUGrpID']));
            if ($owner) {
                //from email
                $email_owner = $owner[0].' '.$owner[1].' <'.$owner[2].'>';

                if($is_notification){
                    //sened notification email for one or several records
                    $email_from_name = 'Heurist notification';
                    $email_headers = 'From: '.$email_owner.' <no-reply@'.HEURIST_SERVER_NAME.'>';

                    //find associated records
                    $bibs = mysql__select_assoc2($mysqli,
                        'select rec_ID, rec_Title from Records '.
                                        'where rec_ID in ('. implode(',', $rec_IDs) .')');

                    $email_title = '[HEURIST] Email from '.$owner[0].' '.$owner[1].' ('
                        .(count($bibs)>1?count($bibs).' references':'one reference').')';
                }else{
                    //sened reminder email about particular record

                    $email_from_name = 'Heurist reminder service';
                    $email_headers = 'From: Heurist reminder service <no-reply@'.HEURIST_SERVER_NAME.'>';

                    //find associated record
                    $bib = mysql__select_row($mysqli,
                        'select rec_Title, rec_OwnerUGrpID, rec_NonOwnerVisibility, grp.ugr_Name from Records '.
                                        'left join sysUGrps grp on grp.ugr_ID=rec_OwnerUGrpID and grp.ugr_Type!="user" '.
                                        'where rec_ID = '.intval($record['rem_RecID']));

                    $email_title = '"'.$bib[0].'"';//rec_Title
                    if (@$record['rem_ToUserID'] != @$record['rem_OwnerUGrpID']){
                        $email_title .= ' from ' . $owner[0].' '.$owner[1];
                    }
                }

                if (@$record['rem_ToEmail']!=$owner[2]  || @$record['rem_ToUserID'] != @$record['rem_OwnerUGrpID']){
                    $email_headers .= "\r\nCc: ".$email_owner;
                }
                $email_headers .= "\r\nReply-To: ".$email_owner;


                foreach($recipients as $recipient) {
                    if($is_notification){

                        $email_text = $owner[0].' '.$owner[1].' <'.$owner[2]
                                    .'> would like to draw some records to your attention, with the following note:'. "\n\n"
                                    . '"'.$record['rem_Message'] . '"' . "\n\n"
                                    . 'Access them and add them (if desired) to your Heurist records at:' . "\n\n"
                                    . HEURIST_BASE_URL.'?w=all&db='.$this->system->dbname().'&q=ids:'.implode(',', $rec_IDs) . "\n\n"
                                    . 'To add records, select them and then Selected > Bookmark' . "\n\n"
                                    . "Id      Title\n" . "------  ---------\n";
                        foreach($bibs as $rec_id => $rec_title){
                            $email_text .= str_pad("$rec_id", 8) . $rec_title . "\n";
                        }

                        $email_text .= "\n\n-------------------------------------------\n\n"
                                    .  "This email was generated by Heurist (".HEURIST_MAIL_TO_INFO.").\n\n";


                    }else{
                        $email_text = 'Reminder From: ' . ($record['rem_ToUserID'] == $record['rem_OwnerUGrpID'] ? 'you'
                                                            : $email_owner) . "\n\n"
                                    . 'For: "'.$bib[0].'"' . "\n\n" //rec_Title
                                    . 'URL: '.HEURIST_BASE_URL.'?w=all&db='.$this->system->dbname().'&q=ids:'.$record['rem_RecID'] . "\n\n";

                        if ($bib[1] && $bib[2] == 'hidden') { //rec_OwnerUGrpID  rec_NonOwnerVisibility
                            $email_text .= "Note: Record belongs to workgroup ".$bib[3] . "\n"   //ugr_Name
                                            ."You must be logged in to Heurist and a member of this workgroup to view it". "\n\n";
                        }

                        $email_text .= 'Message: '.$record['rem_Message'] . "\n\n";

                        if (@$record['rem_ID']  &&  $record['rem_Freq'] != "once") {
                            $email_text .= "-------------------------------------------\n\n"
                                        .  "You will receive this reminder " . $record['rem_Freq'] . "\n"
                                        .  "Click this link if you do not wish to receive this reminder again: \n\n"
                                        .  HEURIST_BASE_URL."?ent=rem&method=delete&id=".$record['rem_ID']
                                        .  "&db=".$this->system->dbname()
                                        .  ($recipient['u'] ? "&u=".$recipient['u'] : "&e=".$recipient['e']) . "&h=".$record['rem_Nonce'] . "\n\n";

                        } else {
                            $email_text .= "-------------------------------------------\n\n"
                                        .  "You will not receive this reminder again.\n\n";
                        }
                    }

                    //$res = sendEmail($recipient['email'], $email_title, $email_text, $email_headers, true);
                    $recipient_sanitized = filter_var($recipient['email'], FILTER_VALIDATE_EMAIL);
                    sendPHPMailer(null, $email_from_name, $recipient_sanitized, $email_title, $email_text, null, false);


                }//for recipients

            }else{
                //can get owner data
                $this->system->addError(HEURIST_NOT_FOUND, 'Can\'t get reminder\'s owner information');
                return false;
            }
            }else{
                //no recipients found
                $this->system->addError(HEURIST_NOT_FOUND, 'Can\'t get reminder\'s recipients');
                return false;
            }

            if(!$is_notification && $record['rem_Freq'] != "once"){
                //update start date
                $update = 'UPDATE '.$this->config['tableName'].' SET rem_StartDate=NOW() WHERE rem_ID='.intval($record['rem_ID']);
                $mysqli->query($update);
            }

                if($query==null){
                    return true;
                }

            }//while

            $res->close();
        }//for reminders

        return $is_notification?true:$report;
    }

    //
    // ...?db=xxx&ent=rem&id=1&e=some@xyz.com&h=3ab77f51&method=delete
    //
    public function delete($disable_foreign_checks=false){

        if(is_numeric(@$this->data['rem_ID']) && $this->data['rem_ID']>0 && $this->data['h']){

            //find reminder
            $mysqli = $this->system->getMysqli();
            $query = 'SELECT rem_ID FROM '.$this->config['tableName'].' WHERE rem_ID='.$this->data['rem_ID']
                .' and rem_Nonce="'.$mysqli->real_escape_string($this->data['h']).'"';

            $rem_ID = mysql__select_value($mysqli, $query);
            $rem_ID = intval($rem_ID);

            if($rem_ID>0){

                //@$this->data['e'] ||
                if(is_numeric(@$this->data['u']) && $this->data['u']>0){
                    //adds reminder into block list
                    $query = 'INSERT INTO usrRemindersBlockList VALUES ('.$rem_ID.','.$this->data['u'].')';
                }else{
                    //remove reminder
                    $query = SQL_DELETE.$this->config['tableName'].' WHERE rem_ID='.$this->data['rem_ID'];
                }
                $res = $mysqli->query($query);
            }

            return 'You will not receive this reminder again';

        }else{
            return parent::delete($disable_foreign_checks);
        }
    }

}
?>
