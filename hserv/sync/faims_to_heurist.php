<?php

/**
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


require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../../records/edit/recordModify.php';

//@todo HARDCODED id of OriginalID
$dt_SourceRecordID = 36;

$system = new hserv\System();

if(! $system->init(@$_REQUEST['db'], true) ){
    //@todo - redirect to error page
    print_r($system->getError(),true);
    exit;
}
?>
<!DOCTYPE HTML>
<html lang="en">
    <head>
        <title><?=HEURIST_TITLE ?></title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
    </head>
    <body style="padding:44px;">
        <div class="ui-corner-all ui-widget-content" style="width:640px; margin:0px auto; padding: 0.5em;">

        <div class="logo"></div>
        <div class="utility-content">
            <?php

            if(!$system->is_admin()){
                print "You must be logged in as database owner";
                exit;
            }

            $mysqli = $system->get_mysqli();

            print "<br><h4>HELLO! This is FAIMS sync</h4><br>";


            //$dir_faims.
            $dbname_faims = HEURIST_FILESTORE_DIR. "faims/db.sqlite3";

            print $dbname_faims."<br><br>";

            if(!file_exists($dbname_faims)){
                print "DB file not found";
                exit;
            }

            if (!class_exists('PDO' )) {
                print "FAIMS synchronisation requires installation of the PDO SQLite extension to PHP."
                ." Please ask your system administrator to install this extension. See <put in appropriate URL to doco> for installation information.";
                exit;
            }
            if (!extension_loaded ('pdo_sqlite' )) {
                echo 'PDO sqllite unavailable';
            }

            //$database = new SQLiteDatabase($dbname, 0666, $sqliteerror);
            //$database = sqlite_open($dbname, 0666, $sqliteerror);
            try{

                $dir = 'sqlite:'.$dbname_faims;
                $dbfaims  = new PDO($dir) or die("cannot open the database");
            }
            catch(PDOException $e)
            {
                print_r($e->getMessage());
                print_r("<br><br>Database NOT loaded");
                die();
            }


            /* database structure
            print "<h3>Tables</h3>";
            $query =  "SELECT name FROM sqlite_master WHERE type='table'";
            foreach ($dbfaims->query($query) as $row)
            {
            echo $row[0]."<br>";
            }*/

            $need_debug_info = false;

            if($need_debug_info){
                // AttributeKey -> defDetailTypes
                print "<h3>AttributeKey</h3>";
                $query =  "SELECT AttributeID, AttributeType, AttributeName, AttributeDescription FROM AttributeKey";
                foreach ($dbfaims->query($query) as $row)
                {
                    echo $row[0]."  ".$row[1]."  ".$row[2]."<br>";
                }


                // Vocabulary  -> defTerms
                print "<h3>Vocabulary</h3>";
                $query =  "SELECT VocabID, AttributeID, VocabName FROM Vocabulary";
                foreach ($dbfaims->query($query) as $row)
                {
                    echo $row[0]."  ".$row[2]."<br>";
                }


                //AEntType - defRecTypes   IdealAEnt - defRecStrucutre
                print "<h3>Record types and structures</h3>";
                $query =  "SELECT AEntTypeID, AEntTypeName, AEntTypeCategory, AEntTypeDescription FROM AEntType";
                foreach ($dbfaims->query($query) as $row)
                {
                    echo $row[0]."  ".$row[1]."  ".$row[3]."<br>";

                    $query2 =  "SELECT AEntTypeID, AttributeID, AEntDescription, IsIdentifier, MinCardinality, MaxCardinality FROM IdealAEnt where AEntTypeID=".intval($row[0]);
                    foreach ($dbfaims->query($query2) as $row2)
                    {
                        echo "<div style='padding-left:30px'>{$row2[1]}  {$row2[2]}</div>";
                    }
                }

            }

            //@TODO create mapping form with defRecTypes

            if($need_debug_info){

                print "<h3>The most current Record set</h3>";
                $query = "SELECT  a1.* FROM    ArchEntity a1 INNER JOIN ".
                "(SELECT  uuid, MAX(VersionNum) AS vn FROM  ArchEntity where deleted is null GROUP BY uuid )  a2 ".
                "ON  a1.uuid=a2.uuid and a1.VersionNum=a2.vn";

                foreach ($dbfaims->query($query) as $row)
                {
                    echo $row[0]."  ".$row[1]."  ".$row[4]."<br>";

                    $query2 =  "SELECT * FROM AEntValue where uuid=".intval($row[0])." and VersionNum=".intval($row[7]);
                    foreach ($dbfaims->query($query2) as $row2)
                    {
                        echo "<div style='padding-left:30px'>{$row2[3]}  {$row2[5]}  {$row2[2]}</div>";
                    }
                }

            }


            $rectypeMap = array();
            $detailMap = array();
            $termsMap = array();

            //create/update defDetailTypes on base of AEntValue
            $query1 =  "SELECT AttributeID, AttributeName, AttributeType, AttributeDescription FROM AttributeKey";
            $res1 = $dbfaims->query($query1);
            foreach ($res1 as $row1)
            {
                $attrID = $row1[0];

                //try to find correspondant dettype in Heurist
                $row = mysql__select_row($mysqli, "select dty_ID, dty_Name, dty_JsonTermIDTree from defDetailTypes where dty_NameInOriginatingDB='FAIMS.".$attrID."'");
                if($row){

                    $dtyId = intval($row[0]);

                    print  "DT ".intval($dtyId)."  ".htmlspecialchars($row[1])."  =>".htmlspecialchars($attrID)."<br>";

                    $dtyName = $row[1];
                    $vocabID = $row[2];

                    $detailMap[$attrID] = $dtyId;
                }else{
                    //add new detail type into HEURIST
                    $query = "INSERT INTO defDetailTypes (dty_Name, dty_Documentation, dty_Type, dty_NameInOriginatingDB) VALUES (?,?,?,?)";
                    $stmt = $mysqli->prepare($query);
                    $fid = 'FAIMS.'.$attrID;
                    $ftype = faimsToHeurist_dt_mapping($row1[2]);
                    $stmt->bind_param('ssss', $row1[1], $row1[3], $ftype, $fid);
                    $stmt->execute();

                    $dtyId = $stmt->insert_id;
                    $dtyName = $row1[1];
                    $vocabID = null;

                    $stmt->close();

                    $detailMap[$attrID] = $dtyId;

                    $dtyId = intval($dtyId);
                    print  "DT added $dtyId based on ".htmlspecialchars($attrID." ".$row1[1]." ".$row1[3])."<br>";
                }


                //if AttributeKey has Vocabulary entries it will be ENUM on Heurist
                $query = "select VocabID, VocabName from Vocabulary where AttributeID=".intval($attrID);
                $vocabs = $dbfaims->query($query);

                foreach ($vocabs as $row_vocab)
                {
                    if(!$vocabID){
                        //make shure that we have parent term in Heursit and our detail type refers to this vocabulary (parent term)
                        $query = "INSERT INTO defTerms (trm_Label, trm_Description, trm_Domain) VALUES (?,?,'enum')";
                        $stmt = $mysqli->prepare($query);
                        $flbl = 'Vocab #'.$dtyId;
                        $fdesc = 'Vocabulary for detailtype '.$dtyId;
                        $stmt->bind_param('ss', $flbl , $fdesc );
                        $stmt->execute();
                        $vocabID = intval($stmt->insert_id);
                        $stmt->close();

                        $query = "UPDATE defDetailTypes set dty_Type='enum', dty_JsonTermIDTree=$vocabID where dty_ID=$dtyId";
                        $stmt = $mysqli->prepare($query);
                        $stmt->execute();
                        $stmt->close();
                    }


                    $row = mysql__select_row($mysqli, "select trm_ID, trm_Label from defTerms where trm_NameInOriginatingDB='FAIMS.".$row_vocab[0]."'");
                    if($row){

                        print  "&nbsp;&nbsp;&nbsp;&nbsp;Term ".htmlspecialchars($row[0]."  ".$row[1])."  =>".htmlspecialchars($row_vocab[0])."<br>";

                        $termsMap[$row_vocab[0]] = $row[0];
                    }else{
                        $vocabID = intval($vocabID);
                        //add new detail type into HEURIST
                        $query = "INSERT INTO defTerms (trm_Label, trm_Domain, trm_NameInOriginatingDB, trm_ParentTermID) VALUES (?,'enum',?, $vocabID)";
                        $stmt = $mysqli->prepare($query);
                        $fid = 'FAIMS.'.$row_vocab[0];
                        $stmt->bind_param('ss', $row_vocab[1], $fid );
                        $stmt->execute();
                        $trm_ID = $stmt->insert_id;
                        $stmt->close();

                        $termsMap[$row_vocab[0]] = $trm_ID;

                        $trm_ID = intval($trm_ID);
                        print  "&nbsp;&nbsp;&nbsp;&nbsp;Term added $trm_ID based on ".htmlspecialchars($row_vocab[0]." ".$row_vocab[1])."<br>";
                    }//add terms

                }


            }//for attributes

            //----------------------------------------------------------------------------------------


            //create/update defRecTypes/defRecStrucure on base of AEntType and IdealAEnt
            $query1 =  "SELECT AEntTypeID, AEntTypeName, AEntTypeDescription FROM AEntType";
            $res1 = $dbfaims->query($query1);
            foreach ($res1 as $row1)
            {
                $attrID = $row1[0];

                //try to find correspondant rectype in Heurist
                $row = mysql__select_row($mysqli, "select rty_ID, rty_Name from defRecTypes where rty_NameInOriginatingDB='FAIMS.".$attrID."'");
                if($row){

                    print  "RT ".htmlspecialchars($row[0]."  ".$row[1])."  =>".htmlspecialchars($attrID)."<br>";

                    $rtyId = $row[0];
                    $rtyName = $row[1];

                    $rectypeMap[$attrID] = $row[0];
                }else{
                    //add new record type into HEURIST
                    $query = "INSERT INTO defRecTypes (rty_Name, rty_Description, rty_NameInOriginatingDB) VALUES (?,?,?)";
                    $stmt = $mysqli->prepare($query);
                    $fid = 'FAIMS.'.$attrID;

                    $stmt->bind_param('sss', $row1[1], $row1[2], $fid);
                    $stmt->execute();

                    $rtyId = $stmt->insert_id;
                    $rtyName = $row1[1];

                    $stmt->close();

                    $rectypeMap[$attrID] = $rtyId;

                    print  "RT added ".htmlspecialchars($rtyId."  based on ".$attrID." ".$row1[1]." ".$row1[2])."<br>";
                }

                //if AEntType has strucute described in IdealAEnt

                $query2 =  "SELECT AttributeID, AEntDescription, IsIdentifier, MinCardinality, MaxCardinality FROM IdealAEnt where AEntTypeID=".intval($attrID);
                $recstructure = $dbfaims->query($query2);

                foreach ($recstructure as $row_recstr)
                {


                    $row = mysql__select_row($mysqli,
                        "select rst_DetailTypeID, rst_DisplayName from defDetailTypes d, defRecStructure r ".
                        "where d.dty_ID=r.rst_DetailTypeID and r.rst_RecTypeID=$rtyId and d.dty_NameInOriginatingDB='FAIMS.".$row_recstr[0]."'");

                    if($row){  //such detal in structure already exists

                        print  "&nbsp;&nbsp;&nbsp;&nbsp;detail ".htmlspecialchars($row[0]."  ".$row[1])."<br>";

                    }else{

                        $row3 = mysql__select_row($mysqli, "select dty_ID, dty_Name from defDetailTypes where dty_NameInOriginatingDB='FAIMS.".$row_recstr[0]."'");
                        if($row3){
                            //add new detail type into HEURIST
                            $query = "INSERT INTO defRecStructure (rst_RecTypeID, rst_DetailTypeID, rst_DisplayName, rst_DisplayHelpText) VALUES (?,?,?, '')";
                            $stmt = $mysqli->prepare($query);
                            $stmt->bind_param('iis', $rtyId, $row3[0], $row3[1] );
                            $stmt->execute();

                            $stmt->close();


                            print  "&nbsp;&nbsp;&nbsp;&nbsp;detail added ".htmlspecialchars($row3[0]."  ".$row3[1]."  based on ".$row_recstr[0])."<br>";
                        }else{
                            print  "&nbsp;&nbsp;&nbsp;DETAIL NOT FOUND FAIMS.".htmlspecialchars($row_recstr[0])." !<br>";
                        }
                    }

                }//for add details for structure
            }//for AEntTypes

            //----------------------------------------------------------------------------------------
            /* */
            print "<h3>The most current Record set</h3>";
            $query = "SELECT  a1.* FROM    ArchEntity a1 INNER JOIN ".
            "(SELECT  uuid, MAX(AEntTimestamp) AS vn FROM  ArchEntity where deleted is null GROUP BY uuid )  a2 ".
            "ON  a1.uuid=a2.uuid and a1.AEntTimestamp=a2.vn";

            $cntInsterted = 0;
            $cntUpdated = 0;

            foreach ($dbfaims->query($query) as $row)
            {
                echo $row[0]."  ".$row[1]."  ".$row[4]."<br>";

                $faims_id = $row[0];
                $faims_atype = $row[4];
                $faims_time = $row[1];

                if(@$rectypeMap[$faims_atype]){
                    $rectype = $rectypeMap[$faims_atype];
                }else{
                    print "RECORD TYPE NOT FOUND for Vocabulary ".$faims_atype."<br>";
                    continue;
                }

                $details = array();

                //add special detail type 2-589 - reference to original record id
                if(isset($dt_SourceRecordID) && $dt_SourceRecordID>0){
                    $details["t:".$dt_SourceRecordID] = array('0'=>$faims_id);

                    //find the existing record in Heurist database
                    $recID = mysql__select_value($mysqli, "select dtl_RecID from recDetails where dtL_DetailTypeID=$dt_SourceRecordID and dtl_Value=$faims_id");
                }else{
                    $recID = 0;
                }

                $query2 =  "SELECT uuid, ValueTimestamp, VocabID, AttributeID, Measure, FreeText, Certainty FROM AEntValue where uuid=".intval($faims_id)." and ValueTimestamp='".$dbfaims->real_escape_string($faims_time)."'";
                foreach ($dbfaims->query($query2) as $row2)
                {
                    //attr id, freetext, measure, certainity, vocabid
                    echo "<div style='padding-left:30px'>{$row2[3]}  {$row2[5]} {$row2[4]}  {$row2[6]}  {$row2[2]}</div>";

                    //detail type
                    $key = intval(@$detailMap[$row2[3]]);
                    if($key>0){

                        $vocabID = $row2[2];

                        if($vocabID){ //vocabID
                            if(@$termsMap[$vocabID]){
                                $value = $termsMap[$vocabID];
                            }else{
                                print "TERM NOT FOUND for Vocabulary ".$vocabID."<br>";
                                continue;
                            }
                        }elseif($row2[5]){ //freetext
                            $value = $row2[5];
                        }elseif($row2[4]){ //measure
                            $value = $row2[4];
                        }elseif($row2[6]){ //Certainty
                            $value = $row2[6];
                        }else{
                            continue;
                        }

                        if($value){
                            if(!@$details["t:".$key]){
                                $details["t:".$key] = array();
                            }
                            array_push($details["t:".$key], $value);
                        }


                    }else{
                        print "DETAIL TYPE NOT FOUND for Attrubute ".$row2[3]."<br>";
                    }

                }

                $record = array('ID'=>$recID, 'RecTypeID'=>$rectype, 'AddedByImport'=>2, 'details'=>$details);

                $response = recordSave($system, $record);

                if($response['status']==HEURIST_OK){
                    if($recID){
                        $cntUpdated++;
                        print "UPDATED as #".$recID."<br>";
                    }else{
                        $cntInsterted++;
                        print "INSERTED as #".htmlspecialchars($response['data'])."<br>";
                    }
                }else{
                    print 'Error: '.htmlspecialchars(@$response['status'].'  '.@$response['message']);
                    //print print_r($response, true)."<br>";
                }


                break; //DEBUG - IMPORT ONE ONLY

            }//for records


            print "Inserted ".$cntInsterted."<br>";
            print "Updated ".$cntUpdated."<br>";

            // TODO: this also exists in syncFAIMS.php
            function faimsToHeurist_dt_mapping($ftype){

                $res = null;

                switch (strtolower($ftype)){
                    case 'string':
                    case 'text':
                    case 'checklist':
                    case 'radiogroup':
                        $res = 'freetext';
                        break;
                    case 'integer':
                    case 'measure':
                        $res = 'float';
                        break;
                    case 'date':
                    case 'timestamp':
                        $res = 'date';
                        break;
                    case 'lookup':
                        $res = 'enum';
                        break;
                    case 'dropdown':
                        $res = 'resource';
                        break;
                    default:
                        $res = 'freetext';
                        break;
                }
                //vsn 3 types ENUM('freetext','blocktext','integer','date','year','relmarker','boolean','enum','relationtype','resource','float','file','geo','separator','calculated','fieldsetmarker','urlinclude')
                return $res;
            }
            ?>
        </div>
    </body>
</html>
