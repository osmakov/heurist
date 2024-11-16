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

checkVersionDatabase();

print '<br>[end]';

//
// Report database versions and missed tables
//
function checkVersionDatabase(){
    global $system, $mysqli, $databases;

    $min_version = '1.3.16';

    foreach ($databases as $db_name){

        mysql__usedatabase($mysqli, $db_name);
        
        $current_db_version = getDbVersion($mysqli);

        if(!$current_db_version){
            print htmlspecialchars($db_name.'  >>> cannot get db version '.$mysqli->error);
        }else{

            $is_old_version = (version_compare($min_version, $current_db_version)>0);

            $missed = hasAllTables($mysqli); //, 'hdb_'.$db_name
            $has_missed = (!isEmptyArray($missed));
            if(!is_array($missed)){
                print 'ERROR '.$missed.'  ';
            }

            if($is_old_version || $has_missed){
                print DIV_S.htmlspecialchars($db_name.'  >>> '.$current_db_version);

                if($has_missed){
                    print '<br>Missed: '.implode(', ',$missed);
                }

                if(@$_REQUEST['upgrade'] && $is_old_version && version_compare($current_db_version, '1.3.0')>=0){
                        $rep = updateDatabseTo_v1_3_16($system);

                        if($rep!==false &&  version_compare('1.3.14', $current_db_version)>0){ //older than 1.3.14
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
            }
        }
    }
}

//------------------------------
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
// to be removed
//
function findWrongChars(){

    global $mysqli, $databases;


    print '[wrong characeters in rty_TitleMask]<br>';

    foreach ($databases as $db_name){

        mysql__usedatabase($mysqli, $db_name);

        if(!hasTable($mysqli, 'defRecTypes')){
            continue;
        }

            $list = mysql__select_assoc($mysqli, 'select rty_ID, rty_TitleMask from defRecTypes');

            $isOK = true;

            $db_name = htmlspecialchars($db_name);

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
// to be removed
//
function findLongTermLabels(){

    global $mysqli, $databases;


    print '[long term labels]<br>';

    foreach ($databases as $db_name){

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
// to be removed
//
function setTermNameTo255(){

    global $mysqli, $databases;


    print '[set both trm_Label and trm_NameInOriginatingDB  to varchar(250)]<br>';

    foreach ($databases as $db_name){

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
function setTermYesNo(){

    global $mysqli, $databases;

    define('UPDATE_QUERY','INSERT INTO defTermsLinks (trl_ParentID,trl_TermID) VALUES(');

    print '[Fix Yes/No terms]<br>';

    foreach ($databases as $db_name){

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

//
//
//
function removeDuplicationValues(){

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
function listOfAdminUsers(){

    global $system, $mysqli, $databases;

    $mind = '9999';

    foreach ($databases as $db_name){

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
function convertTustep(){
     global $system, $mysqli, $databases;

     $enable_database_update = false;

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
            if($enable_database_update){
                $update_stmt->bind_param('si', $m, $row[0]);
                $res33 = $update_stmt->execute();
                if(! $res33 )
                {
                    $isOK = false;
                    print error_Div('Record #'.$row[3].'. Cannot replace value in record details. SQL error: '.$mysqli->error);
                    $mysqli->rollback();
                    break;
                }
            }


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
function findRDF(){
    global $system, $mysqli, $databases;

    foreach ($databases as $db_name){

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

function dropBkpDateIndex(){


    global $system, $mysqli, $databases;

    foreach ($databases as $db_name){

        if($db_name=='') {continue;}

        $db_name = htmlspecialchars($db_name);

        mysql__usedatabase($mysqli, $db_name);

        if(hasTable($mysqli, 'bkpDetailsDateIndex')){
            $mysqli->query('DROP TABLE bkpDetailsDateIndex');
            print $db_name.'<br>';
        }
    }
}

function findBelegSpan($context){

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

    foreach ($nodes as $node)
    {
        $nvals[] = $dom->saveHTML($node);
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
        }

        $res = $res.$space.$nodes[$idx]->nodeValue;
    }

    print $res."\t";
    print htmlspecialchars($context_original)."\n";
}

//example: <span class="Beleg">a</span><span class="Beleg"><span class="Beleg">s</span> hey sachte</span>


function getBelegContext(){
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

             echo intval($recID)."\t";
             findBelegSpan($val);
         }
     }

}

//
// detect ./HEURIST_FILESTORE/ references and absence of the obfuscation code, and replace with the correct relative path string
//
function fixDirectPathImages(){

    global $system, $mysqli, $databases;


    $databases = array('efeo_khmermanuscripts');

    $doc = new DOMDocument();

    foreach ($databases as $db_name){

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

        foreach($vals as $val){

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


                $cnt2++;
            }
        }

        mysql__end_transaction($mysqli, $success, $keep_autocommit);

        }else{
            print 'CMS rectypes not defined '.$rty_ID_1.' '.$rty_ID_2.' '.$dty_ID;
        }
    }

    print '<br>'.implode(',',$affected_recs);
    print '<br>Entries:'.$cnt.'  Fields:'.$cnt2;

}
