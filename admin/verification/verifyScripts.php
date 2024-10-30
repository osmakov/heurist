<?php
    /*
    * Copyright (C) 2005-2023 University of Sydney
    *
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
    * in compliance with the License. You may obtain a copy of the License at
    *
    * https://www.gnu.org/licenses/gpl-3.0.txt
    *
    * Unless required by applicable law or agreed to in writing, software distributed under the License
    * is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
    * or implied. See the License for the specific language governing permissions and limitations under
    * the License.
    */

    /**
    *
    * Various actions to check/correct data and db structure per all databases on server
    *
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @copyright   (C) 2005-2023 University of Sydney
    * @link        https://HeuristNetwork.org
    * @version     3.1
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */
print 'disabled';
exit;
ini_set('max_execution_time', '0');


define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
require_once dirname(__FILE__).'/../../hserv/utilities/utils_db_load_script.php';
require_once dirname(__FILE__).'/../setup/dbupgrade/DBUpgrade_1.3.0_to_1.3.14.php';

global $mysqli, $databases;

$mysqli = $system->get_mysqli();

//find all database
$databases = mysql__getdatabases4($mysqli, false);

foreach ($databases as $idx=>$db_name){
    $databases[$idx] = htmlspecialchars( $db_name );
}

__checkVersionDatabase();

print '<br>[end]';

//
// Report database versions and missed tables
//
function __checkVersionDatabase(){
    global $system, $mysqli, $databases;

    $min_version = '1.3.16';

    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);

        $query = 'SELECT sys_dbVersion, sys_dbSubVersion, sys_dbSubSubVersion from sysIdentification';
        $ver = mysql__select_row_assoc($mysqli, $query);
        if(!$ver){
            print htmlspecialchars($db_name.'  >>> '.$mysqli->error);
        }else{


            $is_old_version = (version_compare($min_version,
                    $ver['sys_dbVersion'].'.'
                    .$ver['sys_dbSubVersion'].'.'
                    .$ver['sys_dbSubSubVersion'])>0);

            $missed = hasAllTables($mysqli); //, 'hdb_'.$db_name
            $has_missed = (!isEmptyArray($missed));
            if(!is_array($missed)){
                print 'ERROR '.$missed.'  ';
            }

            if($is_old_version || $has_missed){
                print DIV_S.htmlspecialchars($db_name.'  >>> '.$ver['sys_dbVersion'].'.'.$ver['sys_dbSubVersion'].'.'.$ver['sys_dbSubSubVersion']);

                if($has_missed){
                    print '<br>Missed: '.implode(', ',$missed);
                }

                if(@$_REQUEST['upgrade'] && $is_old_version && $ver['sys_dbSubVersion']==3 && $ver['sys_dbSubSubVersion']>=0){
                        $rep = updateDatabseTo_v1_3_16($system);

                        if($rep!==false && $ver['sys_dbSubSubVersion']<14){ //for db_utils.php
                            $rep2 = recreateRecDetailsDateIndex($system, true, true);
                            if($rep2){
                                $rep = array_merge($rep, $rep2);
                            }else{
                                $rep = false;
                            }
                        }
                        if(!$rep){
                            $error = $system->getError();
                            if($error){
                                print error_Div($error['message'].BR.@$error['sysmsg']);
                            }
                            break;
                        }


                        print implode('<br>',$rep);
                }



                print DIV_E;
            }else{

            }
        }
    }
}

//
// updata database - add new fields
//
function __updateDatabase(){

    global $mysqli, $databases;

    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);

        $db_name = htmlspecialchars($db_name);

        if(!hasTable($mysqli, 'defRecStructure')){
            continue;
        }

            if(!hasColumn($mysqli, 'defRecStructure', 'rst_SemanticReferenceURL')){

                //alter table
                $query = "ALTER TABLE `defRecStructure` ADD `rst_SemanticReferenceURL` VARCHAR( 250 ) NULL "
                ." COMMENT 'The URI to a semantic definition or web page describing this field used within this record type' "
                .' AFTER `rst_LocallyModified`';
                $res = $mysqli->query($query);
                if(!$res){
                    print $db_name.' Cannot modify defRecStructure to add rst_SemanticReferenceURL: '.$mysqli->error;
                    return false;
                }

                print $db_name.'<br>';

            }

            if(hasColumn($mysqli, 'defRecStructure', 'rst_TermsAsButtons')){
               print $db_name.': rst_TermsAsButtons already exists<br>';
            }else{
                //alter table
                $query = "ALTER TABLE `defRecStructure` ADD `rst_TermsAsButtons` TinyInt( 1 ) DEFAULT '0' "
                ." COMMENT 'If 1, term list fields are represented as buttons (if single value) or checkboxes (if repeat values)' "
                .' AFTER `rst_SemanticReferenceURL`';
                $res = $mysqli->query($query);
                if(!$res){
                    print $db_name.' Cannot modify defRecStructure to add rst_TermsAsButtons: '.$mysqli->error;
                    return false;
                }

                print $db_name.'<br>';

            }


    }
}

//------------------------------
//
//
function __renameDegreeToKM(){
    global $mysqli, $databases;

    print 'renameDegreeToKM<br>';


    //$query1 = 'UPDATE defRecStructure SET rst_DisplayName = REPLACE(rst_DisplayName, "degrees", "km"), rst_DefaultValue="" '


    $query1 = 'UPDATE defRecStructure SET rst_DisplayName = REPLACE(rst_DisplayName, "degrees", "km"), rst_DefaultValue="" '
    .'where rst_DetailTypeID in (select dty_ID from defDetailTypes where dty_OriginatingDBID=3 and dty_IDInOriginatingDB in (1085,1086))';

    $query2 = 'UPDATE defDetailTypes SET dty_NameInOriginatingDB = REPLACE(dty_NameInOriginatingDB, "degrees", "km"), '
    .'dty_Name = REPLACE(dty_Name, "degrees", "km") WHERE dty_ID>0';


    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);

        $db_name = htmlspecialchars($db_name);

        if(hasTable($mysqli, 'defRecStructure')){

            $res1 = $mysqli->query($query1);
            $res2 = $mysqli->query($query2);
            $res2 = true;
                if($res1 && $res2){
                    print $db_name.'<br>';
                }else{
                    print $db_name.' Cannot modify defRecStructure, defDetailTypes: '.$mysqli->error;
                    return false;
                }
        }
    }//for
}

//------------------------------
//
//
function findMissedTermLinks() {
    global $mysqli, $databases;

    $db2_with_links = array();
    $db2_with_terms = array();
    $db3_with_terms = array();

    foreach ($databases as $idx=>$db_name){

        $db_name = preg_replace(REGEX_ALPHANUM, "", $db_name);

        $query = "SELECT sys_dbSubVersion from `$db_name`.sysIdentification";
        $ver = mysql__select_value($mysqli, $query);

        if(false && $ver<3){
            /* databases without trm_VocabularyGroupID
            if(!hasColumn($mysqli, 'defTerms', 'trm_VocabularyGroupID', $db_name)){
                print $db_name.'<br>';
            }
            continue;
            */

            //is defTermLinks exist
            if(!hasTable($mysqli, 'defTermsLinks', $db_name)){
                array_push($db2_with_links,$db_name);
            }

        }else{


            $query = "select count(*) from `$db_name`.defDetailTypes where "
            .'(dty_Type="relationtype") and (dty_JsonTermIDTree!="")';
            //dty_Type="enum" OR dty_Type="relmarker" OR  OR dty_Type="relationtype"
            $value = mysql__select_value($mysqli, $query);
            if($value>0){

                $query = "select dty_ID, dty_Name, dty_JsonTermIDTree from `$db_name`.defDetailTypes where "
                .'(dty_Type="relationtype") and (dty_JsonTermIDTree!="")';// OR dty_Type="relationtype"
                $value = mysql__select_all($mysqli, $query);

                if($ver<3){
                    $db2_with_terms[$db_name] = $value;
                }else{
                    if(@$_REQUEST["fix"]==1){
                        $query = "UPDATE `$db_name`.defDetailTypes SET dty_JsonTermIDTree='' WHERE (dty_Type='relationtype')";
                        $mysqli->query($query);
                        if($mysqli->error){
                            print error_Div($mysqli->error);
                            exit;
                        }
                    }
                    $db3_with_terms[$db_name] = $value;
                }

                /* datetime of creation for defTermsLinks
                $query = "SELECT create_time FROM INFORMATION_SCHEMA. TABLES WHERE table_schema = '"
                        .$db_name."' AND table_name = 'defTermsLinks'";
                $dt = mysql__select_value($mysqli, $query);
                $db3_with_terms[$db_name] = array($dt,$value);
                */
 /*
                //adds trash groups
                if(!(mysql__select_value($mysqli, 'select rtg_ID FROM '.$db_name.'.defRecTypeGroups WHERE rtg_Name="Trash"')>0)){
        $query = 'INSERT INTO '.$db_name.'.defRecTypeGroups (rtg_Name,rtg_Order,rtg_Description) '
        .'VALUES ("Trash",255,"Drag record types here to hide them, use dustbin icon on a record type to delete permanently")';

                    $report[] = '"Trash" group has been added to rectype groups';
                }

                if(!(mysql__select_value($mysqli, 'select vcg_ID FROM '.$db_name.'.defVocabularyGroups WHERE vcg_Name="Trash"')>0)){
        $query = 'INSERT INTO '.$db_name.'.defVocabularyGroups (vcg_Name,vcg_Order,vcg_Description) '
        .'VALUES ("Trash",255,"Drag vocabularies here to hide them, use dustbin icon on a vocabulary to delete permanently")';

                    $report[] = '"Trash" group has been added to vocabulary groups';
                }

                if(!(mysql__select_value($mysqli, 'select dtg_ID FROM '.$db_name.'.defDetailTypeGroups WHERE dtg_Name="Trash"')>0)){
        $query = 'INSERT INTO '.$db_name.'.defDetailTypeGroups (dtg_Name,dtg_Order,dtg_Description) '
        .'VALUES ("Trash",255,"Drag base fields here to hide them, use dustbin icon on a field to delete permanently")';

                    $report[] = '"Trash" group has been added to field groups';
                }
*/
            }
        }
        //find terms with specific ccodes - create new or set vocabularies
        /*
Show legend on startup 3-1079  ( 2-6255 )  3-5074, 3-5075, 3-5076
Suppress timeline 3-1080  ( 2-6256 )  3-5078, 3-5079
Hide layer outside zoom range 3-1087  ( 2-6257 )  3-5081, 3-5082
Show labels 3-1088  ( 2-6258 )  3-5084, 3-5085, 3-5086
        */
        //from fields to vocabs to terms - assign proper ccodes







    }//while  databases

    if(!empty($db2_with_links)){
        print '<p>v2 with defTermLinks</p>';
        print htmlspecialchars(print_r($db2_with_links, true));
    }

    print '<hr><p>v2 with individual term selection for relationtype</p>';

    foreach ($db2_with_terms as $db_name=>$value){
        print $db_name.'<br>';

    }


    print '<hr><p>v3 with individual term selection '.(@$_REQUEST["fix"]==1?'FIXED':'').'</p>';

    foreach ($db3_with_terms as $db_name=>$value){
        print $db_name.'<br>';
        print htmlspecialchars(print_r($value, true)).'<br>';
    }

/*
    foreach ($db3_with_terms as $db_name=>$dt){
        print $db_name.'  '.$dt[1].'   '.$dt[0].'<br>';
    }
*/

    print '</div>';
}

//
//
//
function verifySpatialVocab($sName,$f_code,$v_code){
    global $mysqli, $db_name;

        $query = 'SELECT dty_ID, dty_Name, dty_JsonTermIDTree, dty_OriginatingDBID, dty_IDInOriginatingDB FROM '
                .$db_name.'.defDetailTypes WHERE dty_Name="'.$sName.'"';

        $fields = mysql__select_row($mysqli, $query);
        if(!$fields){
            $query = 'SELECT dty_ID, dty_Name, dty_JsonTermIDTree FROM '
                .$db_name.'.defDetailTypes WHERE  dty_OriginatingDBID='.intval($f_code[0]).' AND dty_IDInOriginatingDB='.intval($f_code[1]);
            $fields = mysql__select_row($mysqli, $query);
            if($fields){
                print error_Div('FIELD HAS DIFFERENT NAME '.htmlspecialchars($fields[1]));
            }
            return;
        }

            $f_code = explode('-',$f_code);
            $v_code = explode('-',$v_code);

            print htmlspecialchars($fields[1]);

            if(!($fields[3]==$f_code[0] && $fields[4]==$f_code[1])){
                //need change ccode for field
                print error_Div('NEED CHANGE FIELD CCODES');
            }

            $query = 'select trm_ID, trm_Label, trm_OriginatingDBID, trm_IDInOriginatingDB from '
                .$db_name.'.defTerms where trm_ID='.intval($fields[2]);
            $vocab = mysql__select_row($mysqli, $query);
            if(!$vocab){
                 print error_Div('VOCAB NOT DEFINED');
                 return;
            }


                if(!($vocab[2]==$v_code[0] && $vocab[3]==$v_code[1])){
                    print DIV_S.htmlspecialchars($vocab[1].' NEED CHANGE VOCAB CCODES '.$vocab[2].'-'.$vocab[3]).DIV_E;

                    if(@$_REQUEST["fix"]==1){
                        $query = 'UPDATE '.$db_name.'.defTerms SET trm_OriginatingDBID='.intval($v_code[0])
                            .', trm_IDInOriginatingDB='.intval($v_code[1])
                            .' where trm_ID='.intval($fields[2]);
                        $mysqli->query($query);
                        if($mysqli->error){
                            print error_Div($mysqli->error);
                            exit;
                        }
                    }

                }
                //find terms
                $query = 'select trm_ID, trm_Label, trm_OriginatingDBID, trm_IDInOriginatingDB from '
                .$db_name.'.defTerms, '.$db_name.'.defTermsLinks WHERE trm_ID=trl_TermID AND trl_ParentID='.intval($vocab[0]);
                $terms = mysql__select_all($mysqli, $query);
                print '<table style="font-size:smaller">';
                foreach($terms as $term){
                    $list = str_replace(chr(29),TD,htmlspecialchars(implode(chr(29),$term)));
                    print TR_S.$list.TR_E;
                }
                print TABLE_E;


}

//
//
//
function __findWrongChars(){

    global $mysqli, $databases;


    print '[wrong characeters in rty_TitleMask]<br>';

    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);

        if(!hasTable($mysqli, 'defRecTypes')){
            continue;
        }

            $list = mysql__select_assoc($mysqli, 'select rty_ID, rty_TitleMask from defRecTypes');

            $isOK = true;

            $db_name = htmlspecialchars($db_name);

            $res = json_encode($list);//JSON_INVALID_UTF8_IGNORE

                foreach($list as $id => $val){
                    $wrong_string = null;
                    try{
                        find_invalid_string($val);

                    }catch(Exception $exception) {
                        $isOK = false;
                        $wrong_string = $exception->getMessage();
                        print error_Div($db_name.' rtyID='.$id.'. invalid: '.$wrong_string);
                    }
                }//foreach


            if($isOK){
                    print $db_name.' OK<br>';
            }
    }
}

function find_invalid_string($val){
    if(is_string($val)){
        $stripped_val = iconv('UTF-8', 'UTF-8//IGNORE', $val);   //
        if($stripped_val!=$val){
            throw new Exception(mb_convert_encoding($val,'UTF-8'));
        }
    }
}

//
//
//
function __findLongTermLabels(){

    global $mysqli, $databases;


    print '[long term labels]<br>';

    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);

            $list = mysql__select_assoc($mysqli, 'select trm_ID, trm_Label, CHAR_LENGTH(trm_Label) as chars, length(trm_Label) as len '
            .' from defTerms where length(trm_Label)>255');

            if($list && !empty($list)){

                print htmlspecialchars($db_name).'<br>';
                foreach($list as $id=>$row){
                    $lbl = htmlspecialchars($row['trm_Label']);
                    $len = intval($row['len']);
                    $chars = intval($row['chars']);
                    print "<div style=\"padding-left:100px\">$id&nbsp;$chars&nbsp;$len&nbsp;$lbl</div>";
                }

            }
    }
}

//
//
//
function __setTermNameTo255(){

    global $mysqli, $databases;


    print '[set both trm_Label and trm_NameInOriginatingDB  to varchar(250)]<br>';

    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);
$query = "ALTER TABLE `defTerms` "
."CHANGE COLUMN `trm_Label` `trm_Label` VARCHAR(250) NOT NULL COMMENT 'Human readable term used in the interface, cannot be blank' ,"
."CHANGE COLUMN `trm_NameInOriginatingDB` `trm_NameInOriginatingDB` VARCHAR(250) NULL DEFAULT NULL COMMENT 'Name (label) for this term in originating database'" ;

        $res = $mysqli->query($query);
        if(!$res){
            print htmlspecialchars($db_name.' Cannot modify defTerms: '.$mysqli->error);
            return false;
        }else{
            print htmlspecialchars($db_name).'<br>';
        }
    }
}

/*

In a correct database eg. core defs,

2-531 = No
2-532 = Yes

In a problem database:

The Flags vocabulary used in the show headings field, and possibly other fields, has No = 99-5447 and Yes = 99-5446 as valid values, which throws out data entry when the 2-53x terms have been inserted, and any functions which check for 2-53x terms (although the web page headings field checks for both)

The correct 2-53x terms exist in the database:
Replace the local IDs in any vocabulary which uses 99-544x terms with the corresponding 2-53x local IDs
Update any record details which specify the local ID of 99-544x terms with the corresponding local IDs of the 2-53x terms

The 2-53x terms are not present:
Add the 2-53x terms
Set the concept IDs of the 99-544x terms to 2-53x - these will now be associated with the correct terms
The local IDs specifying the terms in the vocab will now point to the correct terms
The local IDs in record details will continue to point to those terms

*/
function __setTermYesNo(){

    global $mysqli, $databases;

    define('UPDATE_QUERY','INSERT INTO defTermsLinks (trl_ParentID,trl_TermID) VALUES(');

    print '[Fix Yes/No terms]<br>';

    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);

        print htmlspecialchars($db_name).' ';

        if(!hasTable($mysqli, 'defTermsLinks')){
            print ' defTermsLinks does not exist<br>';
            continue;
        }


//get local codes for 2-532, 2-531 and 99-5446(yes) 99-5447 (no) in vocab (99-5445)
        $yes_0 = getLocalCode(2, 532);
        $no_0 = getLocalCode(2, 531);

        $yes_1 = getLocalCode(99, 5446);
        $no_1 = getLocalCode(99, 5447);

        if($yes_1>0 || $no_1>0){

            print '<b>';

        $vocab = getLocalCode(99, 5445);

// get all enum fields
        $enums = mysql__select_list2($mysqli, 'select dty_ID from defDetailTypes WHERE dty_Type="enum"');//, 'intval' snyk does not see it
        $enums = prepareIds($enums);
        $enums = 'dtl_DetailTypeID IN ('.implode(',',$enums).')';

        if($yes_1>0){
//replace 99-544x to 2-53x in recDetails
            $yes_0 = intval($yes_0);
            $yes_1 = intval($yes_1);
            $vocab = intval($vocab);
            if($yes_0>0){
                $query = 'UPDATE recDetails SET dtl_Value='.$yes_0.' WHERE dtl_Value='.$yes_1.SQL_AND.$enums;
                $mysqli->query($query);
    //replace in term links
                $query = 'UPDATE defTermsLinks trl_TermID='.$yes_0.' WHERE trl_TermID='.$yes_1;
                $mysqli->query($query);
    //add references to vocabulary 99-5445
                if($vocab>0){
                    $query = UPDATE_QUERY.$vocab.','.$yes_0.')';
                    $mysqli->query($query);
                }
    //remove old term
                $query = 'DELETE FROM defTerms WHERE trm_ID='.intval($yes_1);
                $mysqli->query($query);


                print ' "yes" replaced';
            }else{
                $query = 'UPDATE defTerms set trm_OriginatingDBID=2 trm_IDInOriginatingDB=532 WHERE trm_ID='.$yes_1;
                $mysqli->query($query);
                if($vocab>0){
                $query = UPDATE_QUERY.$vocab.','.$yes_1.')';
                $mysqli->query($query);
                }
                print ' "yes" added';
            }
        }

        if($no_1>0){
//replace 99-544x to 2-53x in recDetails
            $no_0 = intval($no_0);
            $no_1 = intval($no_1);
            $vocab = intval($vocab);

            if($no_0>0){
                $query = 'UPDATE recDetails SET dtl_Value='.$no_0.' WHERE dtl_Value='.$no_1.SQL_AND.$enums;
                $mysqli->query($query);
    //replace in term links
                $query = 'UPDATE defTermsLinks trl_TermID='.$no_0.' WHERE trl_TermID='.$no_1;
                $mysqli->query($query);
    //add references to vocabulary 99-5445
                if($vocab>0){
                $query = UPDATE_QUERY.$vocab.','.$no_0.')';
                $mysqli->query($query);
                }
    //remove old term
                $query = 'DELETE FROM defTerms WHERE trm_ID='.$no_1;
                $mysqli->query($query);

                print ' "no" replaced';
            }else{
                $query = 'UPDATE defTerms set trm_OriginatingDBID=2 trm_IDInOriginatingDB=531 WHERE trm_ID='.$no_1;
                $mysqli->query($query);
                if($vocab>0){
                $query = UPDATE_QUERY.$vocab.','.$no_1.')';
                $mysqli->query($query);
                }
                print ' "no" added';
            }
        }

        print '</b><br>';

        }else{
            print ' no wrong terms <br>';
        }

    }

}

function __recreateProceduresTriggers(){

    global $mysqli, $databases;

    print 'Recreate procedures and triggers<br>';

    foreach ($databases as $idx=>$db_name){

        print htmlspecialchars($db_name).'<br>';

        mysql__usedatabase($mysqli, $db_name);

        $res = false;
        if(db_script('hdb_'.$db_name, dirname(__FILE__).'/../setup/dbcreate/addProceduresTriggers.sql', false)){
            $res = true;
            if(db_script('hdb_'.$db_name, dirname(__FILE__).'/../setup/dbcreate/addFunctions.sql', false)){
                $res = true;
            }else{
                exit;
            }
        }
    }//foreach

}

function getLocalCode($db_id, $id){
    global $mysqli;
    $query = 'select trm_ID from defTerms where trm_OriginatingDBID='.$db_id.' and trm_IDInOriginatingDB='.$id;
    return mysql__select_value($mysqli, $query);
}
function getDtyLocalCode($db_id, $id){
    global $mysqli;
    $query = 'select dty_ID from defDetailTypes where dty_OriginatingDBID='.$db_id.' and dty_IDInOriginatingDB='.$id;
    return mysql__select_value($mysqli, $query);
}
function getRtyLocalCode($db_id, $id){
    global $mysqli;
    $query = 'select rty_ID from defRecTypes where rty_OriginatingDBID='.$db_id.' and rty_IDInOriginatingDB='.$id;
    return mysql__select_value($mysqli, $query);
}

//---------------
function __addOtherSources(){
/*
    global $mysqli;
    mysql__usedatabase($mysqli, 'hdb_judaism_and_rome');

    //1. assign HID
    $query = 'SELECT * FROM import20220906103458';
    $res = $mysqli->query($query);

    if ($res){

        $query_match = 'SELECT dtl_RecID FROM recDetails WHERE `dtl_DetailTypeID`=36 AND dtl_Value=';
        $query_update = 'INSERT INTO recDetails (dtl_RecID,dtl_DetailTypeID,dtl_Value,dtl_Annotation) VALUES ';//1107
        $query_check = 'SELECT dtl_ID FROM recDetails WHERE dtl_DetailTypeID=1107 AND dtl_RecID=';


        $not_found1 = array();
        $not_found2 = array();
        $cnt = 0;
        $cnt2 = 0;
        $cnt3 = 0;

        while ($row = $res->fetch_row()){

            $nid = $row[1];

            $rec_ID = mysql__select_value($mysqli, $query_match.$nid);

            if($rec_ID>0){
print '<br>'.htmlspecialchars($row[3]);
                $nids = explode('|',$row[3]);
                if(!empty($nids)){
                    $values = array();
                    foreach ($nids as $id=>$nid){
                        $os_rec_ID = mysql__select_value($mysqli, $query_match.$nid);
                        if($os_rec_ID>0){
                            $dtl_ID = mysql__select_value($mysqli, $query_check.$rec_ID.' AND dtl_Value='.$os_rec_ID);
                            if($dtl_ID>0){ //already added?
print '<br>  exist '.$os_rec_ID;
                                $cnt2++;
                            }else{
                                $val = '('.$rec_ID.',1107,'.$os_rec_ID.',"art220906")';
                                $values[] = $val;
print '<br>&nbsp;&nbsp;&nbsp;'.$val;
                            }
                        }else{
                            $not_found2[] = $nid;
                        }
                    }
                    if(!empty($values)){
                        $res2 = $mysqli->query($query_update.implode(',',$values));
                        $cnt = $cnt + $mysqli->affected_rows;
                    }
                }

            }else{
                $not_found1[] = $nid;

            }

        }
        $res->close();


        print '<br>AAAdded '.$cnt;
        print '<br>Already added '.$cnt2;
        print '<br>Not found '.implode(', ',$not_found1);
        print '<br>Other sources not found '.implode(', ',$not_found2);
    }


*/
}

function __renameField39(){

    global $mysqli, $databases;

    print 'Rename field #2-39<br>';

    $old1 = 'Representative image or thumbnail';
    $new1 = 'Primary / preferred image';

    $query1 = "UPDATE defDetailTypes SET dty_Name='$new1' WHERE dty_Name='$old1' AND dty_ID=";
    $query3 = "UPDATE defRecStructure SET rst_DisplayName='$new1' WHERE rst_DisplayName='$old1' AND rst_DetailTypeID=";

    $old2 = 'An image of up to 400 pixels wide, used approx. 200 pixels wide to represent the record in search results and other compact listings';
    $new2 = 'The representative image used in record view, in search results and in other compact listings';

    $query2 = "UPDATE defDetailTypes SET dty_HelpText='$new2' WHERE dty_HelpText='$old2' AND dty_ID=";
    $query4 = "UPDATE defRecStructure SET rst_DisplayHelpText='$new2' WHERE rst_DisplayHelpText='$old2' AND rst_DetailTypeID=";

    foreach ($databases as $idx=>$db_name){

        print htmlspecialchars($db_name);

        mysql__usedatabase($mysqli, $db_name);

        $dty_ID = intval(getDtyLocalCode(2, 39));

        if($dty_ID>0){

            //1. rename in `defDetailTypes`
            $mysqli->query($query1.$dty_ID);
            $c1 = $mysqli->affected_rows;
            $mysqli->query($query2.$dty_ID);
            $c2 = $mysqli->affected_rows;

            //2. rename in `defRecStructure`
            $mysqli->query($query3.$dty_ID);
            $c3 = $mysqli->affected_rows;
            $mysqli->query($query4.$dty_ID);
            $c4 = $mysqli->affected_rows;

            print '&nbsp;&nbsp;'.$c1.' '.$c2.' '.$c3.' '.$c4.'<br>';

        }else{
            print '&nbsp;&nbsp;not found<br>';
        }



    }

}

//
//
//
function __correctGetEstDate(){

    global $mysqli, $databases;


    print '__correctGetEstDate<br>';

    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);


        $query = 'SELECT dtl_ID, dtl_Value, dtl_RecID FROM recDetails, recDetailsDateIndex where rdi_DetailID=dtl_ID AND rdi_estMaxDate>2100';
        $res = $mysqli->query($query);
        if (!$res){
            continue;
        }

            $cnt=0;
            $is_invalid = false;
            while ($row = $res->fetch_row()){
                $dtl_ID = $row[0];
                $dtl_Value = $row[1];
                $rec_ID = $row[2];

                $preparedDate = new Temporal( $dtl_Value );
                if($preparedDate && $preparedDate->isValidSimple()){

                    $dtl_NewValue = $preparedDate->getValue(true);

                    $query = 'UPDATE recDetails SET dtl_Value="'.
                                                    $mysqli->real_escape_string($dtl_NewValue).'" WHERE dtl_ID='.$dtl_ID;

                    print htmlspecialchars($rec_ID.'  '.$dtl_Value.'  '.$dtl_NewValue).'<br>';

                    $cnt++;
                    if($cnt>10) {break;}
                }else{
                    print htmlspecialchars($rec_ID.'  '.$dtl_Value).'<br>';
                    $is_invalid = true;
                }

            }

            //if($cnt>0 || $is_invalid){}
            print htmlspecialchars($db_name.'  '.$cnt).'<br>';



    }//for
}

//
// converts back to plain
//
function __correctGetEstDate_and_ConvertTemporals_JSON_to_Plain(){

    global $mysqli, $databases;

    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);

        //get version of database
        $query = 'SELECT sys_dbSubVersion, sys_dbSubSubVersion from sysIdentification';
        $ver = mysql__select_row_assoc($mysqli, $query);
        if($ver['sys_dbSubSubVersion']==13){

            print '<br>'.htmlspecialchars($db_name);

            //create sql script file from this remarked code
/*
-- Created by Artem Osmakov 2023-06-01

-- This file contains getEstDate function

DELIMITER $$


DROP function IF EXISTS `getEstDate`$$

-- extract estMinDate or estMaxDate from json string
CREATE DEFINER=CURRENT_USER FUNCTION `getEstDate`(sTemporal varchar(4095), typeDate tinyint) RETURNS DECIMAL(15,4)
    DETERMINISTIC
    BEGIN
            declare iBegin integer;
            declare iEnd integer;
            declare nameDate varchar(20) default '';
            declare strDate varchar(15) default '';
            declare estDate decimal(15,4);

-- error handler for date conversion
            DECLARE EXIT HANDLER FOR 1292
            BEGIN
                RETURN 0;
            END;

-- find the temporal type might not be a temporal format, see else below
            IF (TRIM(sTemporal) REGEXP '^-?[0-9]+$') THEN
                RETURN CAST(TRIM(sTemporal) AS DECIMAL(15,4));
            END IF;

            SET iBegin = LOCATE('|VER=1|',sTemporal);
            if iBegin = 1 THEN
                SET sTemporal = getTemporalDateString(sTemporal);
            END IF;

            IF (typeDate = 0) THEN
                set nameDate = '"estMinDate":';
            ELSE
                set nameDate = '"estMaxDate":';
            END IF;

            SET iBegin = LOCATE(nameDate,sTemporal);
            IF iBegin = 0 THEN
-- it will work for valid CE dates only, dates without days or month will fail
                IF DATE(sTemporal) IS NULL THEN
                    RETURN 0;
                ELSE
                    RETURN CAST(CONCAT(YEAR(sTemporal),'.',LPAD(MONTH(sTemporal),2,'0'),LPAD(DAY(sTemporal),2,'0')) AS DECIMAL(15,4));
                END IF;

            ELSE
                SET iBegin = iBegin + 13;
                SET iEnd = LOCATE(',', sTemporal, iBegin);
                IF iEnd = 0 THEN
                    SET iEnd = LOCATE('}', sTemporal, iBegin);
                END IF;
                IF iEnd > 0 THEN
                    SET strDate =  substring(sTemporal, iBegin, iEnd - iBegin);
                    RETURN CAST(TRIM(strDate) AS DECIMAL(15,4));
                END IF;
                RETURN 0;
            END IF;
    END$$

DELIMITER ;
*/
            // recreate getEstDate function
            if(db_script('hdb_'.$db_name, dirname(__FILE__).'/../setup/dbcreate/getEstDate.sql', false)){

                $cnt = 0;
                //converts back to plain
                $query = 'SELECT dtl_ID,dtl_RecID,dtl_DetailTypeID,dtl_Value FROM recDetails, defDetailTypes '
                .'WHERE dtl_DetailTypeID=dty_ID AND dty_Type="date" AND dtl_Value LIKE "%\"estMinDate\":%"';
                $res = $mysqli->query($query);

                if ($res){

                    while ($row = $res->fetch_row()){
                        $dtl_ID = $row[0];
                        $dtl_RecID = $row[1];
                        $dtl_DetailTypeID = $row[2];
                        $dtl_Value = $row[3];
                        $dtl_NewValue = '';
                        $error = '';

                        $value = json_decode($dtl_Value,true);

                        if(is_array($value)){

                            $preparedDate = new Temporal( $dtl_Value );
                            if($preparedDate && $preparedDate->isValid()){
                                $dtl_NewValue = $preparedDate->toPlain();

                                $query = 'UPDATE recDetails SET dtl_Value="'.
                                                $mysqli->real_escape_string($dtl_NewValue).'" WHERE dtl_ID='.intval($dtl_ID);
                                $mysqli->query($query);

                                $cnt++;
                            }
                        }


                    }//while

                    print ' '.$cnt;
                }
            }
        }
    }

}

//
//
//
function __delete_OLD_RecType_And_Term_Icons_Folders(){
    global $mysqli, $databases;

    echo '__delete_OLD_RecType_And_Term_Icons_Folders<br>';

    foreach ($databases as $idx=>$db_name){

        $cnt = 0;

        $db_name = basename($db_name);

        $old_path = HEURIST_FILESTORE_ROOT . $db_name . '/rectype-icons/';
        if(file_exists($old_path)){
            folderDelete($old_path, true);
            $cnt++;
        }


        $old_path = HEURIST_FILESTORE_ROOT . $db_name . '/term-images/';
        if(file_exists($old_path)){
            folderDelete($old_path, true);
            $cnt++;
        }

        $old_path = HEURIST_FILESTORE_ROOT . $db_name . '/term-icons/';
        if(file_exists($old_path)){
            folderDelete($old_path, true);
            $cnt++;
        }


        echo htmlspecialchars($db_name.'  '.$cnt).'<br>';
    }

}

//
//
//
function __removeDuplicationValues(){

    global $system, $mysqli, $databases;

    $cnt = 0;


    $query = 'SELECT dtl_RecID, dtl_DetailTypeID, dtl_Value, count(dtl_Value) as cnt '.
    'FROM recDetails WHERE dtl_Geo IS NULL AND dtl_UploadedFileID IS NULL '.
    'GROUP BY dtl_RecID, dtl_DetailTypeID, dtl_Value HAVING cnt>1';

    $res = $mysqli->query($query);

    if (!$res) {  print $query.'  '.$mysqli->error;  return; }

    while ($row = $res->fetch_row()) {

        $q = 'DELETE FROM recDetails WHERE dtl_RecID='.intval($row[0]).' AND dtl_DetailTypeID='.intval($row[1])
            .' AND dtl_Value=? LIMIT '.(intval($row[3])-1);

        $ret = mysql__exec_param_query($mysqli,$q,array('s',$row[2],true));
        if(is_string($ret)){
            print 'ERROR. '.$ret;
            break;
        }else{
            $cnt = $cnt + $ret;
        }


    }
    $res->close();

    print 'DONE. Removed '.$cnt.' duplications';
}

//
//
//
function __listOfAdminUsers(){

    global $system, $mysqli, $databases;

    $mind = '9999';

    foreach ($databases as $idx=>$db_name){

        if($db_name=='') {continue;}

        mysql__usedatabase($mysqli, $db_name);

        //get version of database
        $query = "SELECT ugr_Name, ugr_eMail, ugr_Modified FROM sysUGrps where  ugr_FirstName = 'sys' AND ugr_LastName = 'admin'";
        $vals = mysql__select_row_assoc($mysqli, $query);
        if($vals){
            if(strpos($vals['ugr_Modified'],'2019')!==0 && $vals['ugr_Modified']<$mind)
            {
               $mind = $vals['ugr_Modified'];
            }
            echo '<br>'.htmlspecialchars($db_name.'   '.$vals['ugr_Modified']);
        }
    }
    print '<br>Earliest: '.$mind.'<br>';
}


function hexToString($str){return chr(hexdec(substr($str, 2)));}
//
//
//
function __convertTustep(){
     global $system, $mysqli, $databases;

/*
    print '<xmp>'.$s.'</xmp>';
    print '<br>';


    $m = html_entity_decode($s, ENT_QUOTES|ENT_HTML401, 'UTF-8' );

    // Convert the codepoints to entities




    $matches = array();
    preg_match_all("/\&[0-9a-zA-Z]+;/", $s, $matches);

    $matches2 = array();
    preg_match_all("/\&#x[0-9a-fA-F]{4};/", $s, $matches2);


    print '<xmp>'.$m.'</xmp>';
    print '<br>';

    print '<br>';
    print print_r($matches, true);
    print '<br>';
    print print_r($matches2, true);


*/


    define('AMP', '&amp;');
    define('TS_AMP', '#%#%#');

$tustep_to_html = array(
AMP =>TS_AMP,
'^u'    =>'&uuml;',
'#.s'   =>'&#x017F;',
'%/u'   =>'&uacute;',
//'_'     =>'&nbsp;',
"^'"    =>'&lsquo;',
'^-'    =>'&mdash;',
'#(MPU)'=>'&#x00B7;',
'%/U'   =>'&Uacute;',
'#(MKR)'=>'&#x00D7;',
'#.>'   =>'&ldquo;',
'#.<'   =>'&rdquo;',
'%<u'   =>'&ucirc;',
'^o'    =>'&ouml;',
'^s'    =>'&szlig;',
'^a'    =>'&auml;',
'^U'    =>'&Uuml;',
'#.z'   =>'&#x0292;',
'%:w'   =>'&#x1E85;',
'#.>'   =>'&#x00BB;',
'#.<'   =>'&#x00AB;',
'%:e'   =>'&#x00EB;',
'%:i'   =>'&#x00EF;',
'^O'    =>'&Ouml;',
'#.:'   =>'&rsaquo;',
'#.;'   =>'&lsaquo;',
"^'^'^'"=>'&#x2037;',
"''"    =>'&#x2034;',
//'_'     =>'&thinsp;',
"#.'#.'"=>'&#x201C;',
//"#.'#.'"=>'',
'#.^o'  =>'&#x0153;',
'%/e'   =>'&eacute;',
'%<e'   =>'&ecirc;',
'%/o'   =>'&oacute;',
'%<o'   =>'&ocirc;',
'%<u'   =>'&ucirc;',
'%<a'   =>'&acirc;',
'%<i'   =>'&icirc;',
'#.^a'  =>'&aelig;',
'^.'    =>'&middot;',
'%/w'   =>'&x#1E83;',
'%>o'   =>'&#x014F;',
'%<A'   =>'&Acirc;',
'%<E'   =>'&Ecirc;',
'%<U'   =>'&Ucirc;',
'%;;e'  =>'&#x0119;',
'%;;a'  =>'&#x0115;',
'%;;e'  =>'&#x0119;',
'%<y'   =>'&#x0177;',
'%<w'   =>'&#x0175;',
'%<O'   =>'&Ocirc;',
'^A'    =>'&Auml;',
'%<j'   =>'&#x0135;',
'%/a'   =>'&aacute;',
'%-a'   =>'&#x0101;',
'%-e'   =>'&#x0113;',
'%-i'   =>'&#x012B;',
'%-o'   =>'&#x014D;',
'%-u'   =>'&#x016B;',
'%.w'   =>'&#x1E87;',
'#.l'   =>'&#x0197;',
'#.^A'  =>'&AElig;',
'^+'    =>'&dagger;',
//','     =>'&sbquo;',
'%/y'   =>'&yacute;',
'%/Y'   =>'&Yacute;',
'%<I'   =>'&Icirc;',
'#.^O'  =>'&OElig;',
'#;er'  =>'&re;',
'%;e'   =>'&#x0229;',
'>llll' =>'&gt;',
'<IIII' =>'&lt;',
'%-H'   =>'&#x0048;&#x0304;', //  '&Hmacr;',
'#;e#.^a'=>'&aelige;',
'#;vw'  =>'&wv;',  //????
'%)u'   =>'&uslenis;',
'%?n'   =>'&ntilde;',
'#;oo'  =>'&oo;',
'%>a'   =>'&ahacek;',
'%/i'   =>'&iacute;',
'%a'    =>'&agrave;',
'%e'    =>'&egrave;',
'%i'    =>'&igrave;',
'%o'    =>'&ograve;',
'%u'    =>'&ugrave;',
'%:y'   =>'&ytrema;',
'#;iv'  =>'&vi;', //????
'#;iu'  =>'&ui;',
'%-y'   =>'&ymacr;',
'%..d'  =>'&dundpunkt;',
'%>c'   =>'&chacek;',
'%/'    =>'&#180;',
'#.ä'   =>'&#230;',
'#.ö'   =>'&oelig;');

$html_to_hex = array(
'&Hmacr;' =>  '&#x0048;&#x0304;'
);

$tustep_to_html = array(
AMP =>TS_AMP,
'#;ou' =>'&#x016F;',
'#;eo' =>'&#xE4CF;',
'#;ev' =>'&#x011B;'
);



/* test
    $s = '<p>#.ö   &#163; > %/Y#;iv < &#x017F;  &longs; &Ouml;  &#x201E; &ldquo;  &#x201C; &rdquo; &#x0153; &oelig; &Hmacr;  &#x0048;&#x0304;   &wv;</p>';

    print '<xmp>'.$s.'</xmp>';
    print '<br>';
*/
    $cnt = 0;
    $missed = array();//dty_Type="freetext" OR blocktext
    $txt_field_types = mysql__select_list2($mysqli, 'SELECT dty_ID FROM defDetailTypes WHERE dty_Type="freetext" OR dty_Type="blocktext"','intval');
    $txt_field_types = prepareIds($txt_field_types);//snyk does see intval in previous function

    $update_stmt = $mysqli->prepare('UPDATE recDetails SET dtl_Value=? WHERE dtl_ID=?');
    $keep_autocommit = mysql__begin_transaction($mysqli);
    $isOK = true;

    // dtl_RecID=18 AND   dtl_RecID=85057 AND
    //
    $query = 'SELECT dtl_ID, dtl_Value, dtl_DetailTypeID, dtl_RecID FROM recDetails, Records '
    .'WHERE dtl_RecID=rec_ID AND rec_RecTypeID NOT IN (51,52) AND dtl_DetailTypeID in ('.implode(',',$txt_field_types).')';

    $res = $mysqli->query($query);
    if ($res){
        while ($row = $res->fetch_row()){

            //skip json content
            if(strpos($row[1],'{')===0 || strpos($row[1],'[')===0){
                $r = json_decode($row[1],true);
                if(is_array($r)) {continue;}
            }

            $s = ''.$row[1];

            $not_found = true;

            //1. Convert TUSTEP to html entities
            foreach ($tustep_to_html as $tustep=>$entity) {
                if(strpos($s,$tustep)!==false){
                    $s = str_replace($tustep, $entity, $s);
                    $not_found = false;
                }
            }
            if($not_found) {continue;}

            //2. Decode HTML entities
            $m = html_entity_decode($s, ENT_QUOTES|ENT_HTML401, 'UTF-8' );

            $m2 = str_replace(TS_AMP, AMP, $m);//convert back

            //3. List unrecognized
            if($m2!=$row[1]){
                // remove remarks to see raw output
                /*  remarked due snyk security report
                print $row[3].' '.$row[0].'<xmp>'.$row[1].'</xmp>';
                print '<xmp>'.$m2.'</xmp>';
                */
                $cnt++;
            }

            //find missed unconverted HTML entities
            $matches = array();
            preg_match_all("/\&[0-9a-zA-Z]+;/", $m, $matches);

            if(!isEmptyArray(@$matches[0])){
                    $missed = array_merge_unique($missed, $matches[0]);
            }

            $m = str_replace(TS_AMP, AMP, $m);//convert back

            //update in database
            /*
            $update_stmt->bind_param('si', $m, $row[0]);
            $res33 = $update_stmt->execute();
            if(! $res33 )
            {
                $isOK = false;
                print error_Div('Record #'.$row[3].'. Cannot replace value in record details. SQL error: '.$mysqli->error);
                $mysqli->rollback();
                break;
            }
            */


        }//while
        $res->close();
    }

    mysql__end_transaction($mysqli, $isOK, $keep_autocommit);

    print '<br>Replaced in '.$cnt.' fields';

    print '<br>Missed:';
    print print_r($missed, true);

    print '<xmp>'.print_r($missed, true).'</xmp>';


}

//
//
//
function __findRDF(){
    global $system, $mysqli, $databases;

    foreach ($databases as $idx=>$db_name){

        if($db_name=='') {continue;}

        mysql__usedatabase($mysqli, $db_name);

        $r1 = intval(mysql__select_value($mysqli, 'select count(rty_ID) from defRecTypes'));
        $d1 = intval(mysql__select_value($mysqli, 'select count(dty_ID) from defDetailTypes'));

        $t1 = intval(mysql__select_value($mysqli, 'select count(trm_ID) from defTerms'));

        $r2 = intval(mysql__select_value($mysqli, 'select count(rty_ID) from defRecTypes where rty_ReferenceURL!="" and rty_ReferenceURL is not null'));
        $d2 = intval(mysql__select_value($mysqli, 'select count(dty_ID) from defDetailTypes where dty_SemanticReferenceURL!="" and dty_SemanticReferenceURL is not null'));

        $t2 = intval(mysql__select_value($mysqli, 'select count(trm_ID) from defTerms where trm_SemanticReferenceURL!="" and trm_SemanticReferenceURL is not null'));

        if($r2>0 && $d2>1){
            if($r2/$r1>0.2 || $d2>50){
                $s = 'bold';
            }else{
                $s = 'normal';
            }

            $rec_cnt2 = intval(mysql__select_value($mysqli, 'select count(rec_ID) from Records, defRecTypes '
                .'where rty_ID=rec_RecTypeID and rty_ReferenceURL!=""'));

            $rec_cnt1 = intval(mysql__select_value($mysqli, 'select count(rec_ID) from Records'));

            $dtl_cnt = intval(mysql__select_value($mysqli, 'select count(dtl_ID) from recDetails, defDetailTypes '
                .'where dty_ID=dtl_DetailTypeID and dty_SemanticReferenceURL!=""'));

            $db_name = htmlentities($db_name);
            echo  "<div style='font-weight:$s'>$db_name rty: $r1/$r2&nbsp;&nbsp;&nbsp;dty: $d1/$d2 &nbsp;&nbsp;&nbsp;trm:$t1/$t2 &nbsp;&nbsp;&nbsp;Records:$rec_cnt1/$rec_cnt2 $dtl_cnt</div>";//$s1/$s2
        }
    }
}

function __dropBkpDateIndex(){


    global $system, $mysqli, $databases;

    foreach ($databases as $idx=>$db_name){

        if($db_name=='') {continue;}

        $db_name = htmlspecialchars($db_name);

        mysql__usedatabase($mysqli, $db_name);

        if(hasTable($mysqli, 'bkpDetailsDateIndex')){
            $mysqli->query('DROP TABLE bkpDetailsDateIndex');
            print $db_name.'<br>';
        }
    }
}

function __findBelegSpan($context){

    $context_original = $context;

    $dom = new DomDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = false;

    //remove ident and formatting
    $context = preg_replace("/[ \t]+/S", " ", $context);
    $context = str_replace("\n <",'<',$context);
    $context = str_replace("\n </",'</',$context);

    //remove indent spaces after new line before \n...<span
    $dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.$context,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);



    $finder = new DomXPath($dom);

    $nodes = $finder->query("//span[contains(concat(' ', normalize-space(@class), ' '), ' Beleg ')]");

    foreach ($nodes as $idsx=>$node)
    {
        $nvals[] = $dom->saveHTML($node);
        //if(strpos($nval))


    }

    //if there no characters between spans they merge without space <span>a</span><span>dam</span> => adam
    $res = '';
    foreach ($nvals as $idx=>$nval)
    {
        //exclude internal spans
        foreach ($nvals as $nval2)
        {
            $k = mb_strpos($nval2, $nval);
            if($k>19){
                continue 2;
            }
        }

        //detect if between this and next node no other characrters
        $space = '';
        if($idx>0){
            $pos2 = mb_strpos($context, $nval);
            $pos1 = mb_strpos($context, $nvals[$idx-1])+mb_strlen($nvals[$idx-1]);
            if($pos1<$pos2){
                $str = mb_substr($context,$pos1,$pos2-$pos1);
                $str = strip_tags($str);

                $str = preg_replace("/\s+/S", " ", $str);
                if(mb_strlen($str)>0){
                    if($str==' '){
                        $space = ' ';
                    }else{
                        $space = ' […] ';
                    }
                }
            }
            /*
            $pos2 = mb_strpos($context2, $nodes[$idx]->nodeValue);
            $pos1 = mb_strpos($context, $nodes[$idx-1]->nodeValue)+mb_strlen($nodes[$idx-1]->nodeValue);
            if($pos1<$pos2){
                $space = ' ';
            }
            */
        }

        /*
        $str = $nodes[$idx]->nodeValue;
        $str = $dom->saveHTML($nodes[$idx]);
        print $str.'  '.mb_detect_encoding($str).'  '.
                    mb_convert_encoding($str, "UTF-8").'<br>';
        */


        $res = $res.$space.$nodes[$idx]->nodeValue;
    }




    print $res."\t";
    print htmlspecialchars($context_original)."\n";

}

// <span class="Beleg">a</span><span class="Beleg"><span class="Beleg">s</span> hey sachte</span>


function __getBelegContext(){
     global $system, $mysqli;


     header('Content-type: text/plain;charset=UTF-8');

     mysql__usedatabase($mysqli, 'HiFoS');

     //'ids:628,477'   '[{"t":"102"},{"fc:1184":">1"}]'
     $res = recordSearch($system, array('q'=>'[{"t":"102"},{"fc:1184":">1"}]', 'detail'=>'ids'));// 'limit'=>10,

     $ids = @$res['data']['records'];

     if(!isEmptyArray($ids)){
         foreach($ids as $recID){
             $rec = array('rec_ID'=>$recID);
             recordSearchDetails($system, $rec, array(1094));

             $val = $rec['details'][1094];
             $val = array_shift($val);

/*
$val = '<span class="Beleg">
    <span style="mso-char-type: symbol; mso-symbol-font-family: Mediaevum;">
      <span style="font-family: Mediaevum;">a
      </span>
    </span>
  </span>
  <span style="font-family: Times New Roman;">aaaa
    <span class="Beleg">ů eine
      <em style="mso-bidi-font-style: normal;">m
      </em> male
    </span> da |
  </span>';
*/
             echo intval($recID)."\t";
             __findBelegSpan($val);
         }
     }

}

//
// detect ./HEURIST_FILESTORE/ references and absence of the obfuscation code, and replace with the correct relative path string
//
function __fixDirectPathImages(){

    global $system, $mysqli, $databases;


    $databases = array('efeo_khmermanuscripts');

    $doc = new DOMDocument();

    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);

        print "<h4>$db_name</h4>";

        //find CMS Page content
        $rty_ID_1 = intval(getRtyLocalCode(99, 51));
        $rty_ID_2 = intval(getRtyLocalCode(99, 52));
        $dty_ID = intval(getDtyLocalCode(2, 4));

        if($rty_ID_1>0 && $rty_ID_2>0 && $dty_ID>0){

        $query ='select dtl_ID, dtl_Value, rec_ID from recDetails, Records where dtl_RecID=rec_ID'
                ." AND rec_RecTypeID in ($rty_ID_1, $rty_ID_2) and dtl_DetailTypeID=$dty_ID";

        $vals = mysql__select_assoc($mysqli, $query);
        $path = './HEURIST_FILESTORE/'.$db_name.'/file_uploads/';
        $cnt = 0; //entries
        $cnt2 = 0; //fields
        $success = true;

        $keep_autocommit = mysql__begin_transaction($mysqli);

        foreach($vals as $dtl_ID=>$val){

            $rec_ID = $val['rec_ID'];
            $val_orig = $val['dtl_Value'];
            $val = $val['dtl_Value'];

            $prevent_endless_loop = 0;
            $was_replaced = false;

            while(stripos($val, $path)>0 && $prevent_endless_loop<100){

                $prevent_endless_loop++;

                $k = stripos($val, $path);

                $start = strripos(substr($val,0,$k), '<img');
                $end = strpos($val,'/>',$k);
                if($end>0){
                    $end = $end+2;
                }else{
                    $end = strpos($val,'>',$k);
                    if($end>0){
                         $end = $end+1;
                    }
                }

                if($end>0){

                    $cnt++;

                    //extract image tag
                    $img = substr($val, $start, $end-$start);

                    print $rec_ID." <xmp>$img</xmp>";

                    $img2 = str_replace('\"','"',$img);

                    $doc->loadHTML( $img2 );
                    $doc->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.$img2,
                            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

                    $imgele = $doc->getElementsByTagName('img')->item(0);
                    if($imgele->hasAttribute('data-id')){
                        $obf = $imgele->getAttribute('data-id');
                    }else{
                        //find obfuscation by file name
                        $filename = $imgele->getAttribute('src');

                        $file_id = fileGetByFileName($system, basename($filename));
                        if($file_id>0){
                            $file_info = fileGetFullInfo($system, $file_id);
                            $obf = $file_info[0]['ulf_ObfuscatedFileID'];

                            $imgele->setAttribute('data-id',$obf);
                        }else{
                            print 'file not found '.$filename.'<br>';
                            $obf = null;
                        }
                    }

                    if($obf!=null){
                        $imgele->setAttribute('src', './?db='.$db_name.'&file='.$obf);//.'&fancybox=1'
                        $img2 = $doc->saveHTML($imgele);
                        $img2 = str_replace('"','\"',$img2);

                        print "<xmp>$img2</xmp><br>";
                    }

                    $was_replaced = true;
                    $val_orig = str_replace($img,$img2,$val_orig);

                    $val = substr($val, $end+1);//rest


                }else{
                    $success = false;
                    print "end of tag not found <xmp>{substr($val, $start, 50)}</xmp>";
                    break;
                }
            }

            if($success && $was_replaced){

                if(!in_array($rec_ID,$affected_recs)){
                    $affected_recs[] = $rec_ID;
                }
                /*
                $query = 'update recDetails set dtl_Value=? where dtl_ID='.$dtl_ID;
                $res = mysql__exec_param_query($mysqli,$query,array('s', $val_orig));
                if($res!==true){
                    $success = false;
                    print 'ERROR: '.$rec_ID.'  '.$res;
                    break;
                }
                */


                $cnt2++;
            }

/*
            while(strpos($val, $path)>0){

                $k = strpos($val, $path);

                $m = strpos(strstr($val, $path),'\"');

                if($m>0){
                    $cnt++;

                    $surl = substr($val, $k, $m);

                    print $rec_ID.' '.$surl.'<br>';

                    $val = substr($val, $k+$m+1);//rest


                }else{
                    print 'end of url not found '.substr($val, $k, 50);
                    break;
                }
            }
*/

        }

        mysql__end_transaction($mysqli, $success, $keep_autocommit);

        }else{
            print 'CMS rectypes not defined '.$rty_ID_1.' '.$rty_ID_2.' '.$dty_ID;
        }
    }

    print '<br>'.implode(',',$affected_recs);
    print '<br>Entries:'.$cnt.'  Fields:'.$cnt2;

}

?>
