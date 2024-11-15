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
    * This is fix for bug of database creation (happened between 2017-09-22 and 2017-10-16)
    * New database has empty pointer constraints for resouce fields
    *
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @copyright   (C) 2005-2023 University of Sydney
    * @link        https://HeuristNetwork.org
    * @version     3.1
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */
    use hserv\utilities\USanitize;

    if(!@$_REQUEST['db']) {$_REQUEST['db'] = 'Heurist_Bibliographic';}

    if(@$_REQUEST['verbose']!=1){

        define('OWNER_REQUIRED',1);
        require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
        // <li>optrectypes=1    test for presence of missing record types not referenced by a pointer field</li>
        require_once 'verifyValue.php';
        require_once 'verifyFieldTypes.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta name="robots" content="noindex,nofollow">
<title>Verify Origin</title>
<style>
    p {padding:0;margin:0px;}
    * {font-size: medium;}
</style>
</head>
<body>
<ul>
    <li>filter_exact - exact name of database to be tested</li>
    <li>filter=all     databases to be tested, hdb_xyz acts as wildcard for all DBs starting with xyz</li>
    <li>biblio=0       omit test bibliographic record types against Heurist_Bibliographic (#6)</li>
    <li>spatial=0      omit test spatial and temporal record types against Heurist_Core_Definitions (#2)</li>
    <li>optfields=1    test for presence of optional fields</li>
    <li>recfields=1    test for presence of recommended fields</li>
    <li>termlists=1    test for missing terms in lists for fields (undefined codes are always shown)</li>
    <li>showalldbs=1   show databases without errors</li>
</ul>
<?php
    }

    $sysadmin_pwd = USanitize::getAdminPwd();

    if( $system->verifyActionPassword($sysadmin_pwd, $passwordForServerFunctions) ){
        print $response = $system->getErrorMsg();
        $system->clearError();
    }else{


    $test_optfields = (@$_REQUEST['optfields']==1);
    $test_recfields = (@$_REQUEST['recfields']==1);

    $mysqli = $system->get_mysqli();


    $filter = @$_REQUEST['filter_exact'];
    if(!$filter){
        $filter = @$_REQUEST['filter'];
        if(!$filter) {$filter = 'hdb_johns';}


        //1. find all database
        if($filter=='all'){
            $filter = null;
        }
        $databases = mysql__getdatabases4($mysqli, true, $filter);

    }else{
        $databases = array($filter);
    }

    //origin database and record types to check
    $origin_dbs = array();
    if(@$_REQUEST['biblio']==null || @$_REQUEST['biblio']!=0){
        $origin_dbs['hdb_Heurist_Bibliographic'] = array('rty_RecTypeGroupID'=>array(7,9),'rty_ID'=>array());
    }
    if(@$_REQUEST['spatial']==null || @$_REQUEST['spatial']!=0){
        $origin_dbs['hdb_Heurist_Core_Definitions'] = array('rty_RecTypeGroupID'=>array(8),'rty_ID'=>array());
    }

    $rty_CodesToCheck = array();//rty ids in origin db to be checked
    $rty_Names = array();//concept code -> name in origin database

    $fields = array();//per record type
    $fields_req = array();//requirement per record type
    $constraints = array();//per detail
    $terms_codes = array();//per detail

    foreach ($origin_dbs as $db_name=>$ids){

        mysql__usedatabase($mysqli, $db_name);

        VerifyValue::reset();
        $all_terms = dbs_GetTerms($system);
        $ti_dbid = $all_terms['fieldNamesToIndex']['trm_OriginatingDBID'];
        $ti_oid = $all_terms['fieldNamesToIndex']['trm_IDInOriginatingDB'];

        //get record structure for origin
        $where = null;
        if(!isEmptyArray(@$ids['rty_RecTypeGroupID'])){
            $where = 'rty_RecTypeGroupID in ('.implode(',',$ids['rty_RecTypeGroupID']).')';
        }
        if(!isEmptyArray(@$ids['rty_ID'])){
            if($where) {$where = $where.' OR ';}
            $where = 'rty_ID in ('.implode(',',$ids['rty_ID']).')';
        }
        if($where==null){
            print 'Record types to be checked not defined for '.$db_name;
            continue;
        }

        //all codes in this database
        $rty_Codes = mysql__select_assoc2($mysqli, 'select rty_ID, CONCAT(rty_OriginatingDBID,"-",rty_IDInOriginatingDB) as rty_Code FROM defRecTypes');
        $rty_Names_temp = mysql__select_assoc2($mysqli, 'select rty_Name, CONCAT(rty_OriginatingDBID,"-",rty_IDInOriginatingDB) as rty_Code FROM defRecTypes');

        $rty_IDs = mysql__select_list($mysqli, 'defRecTypes', 'rty_ID', $where);
        $rty_IDs_ToCheck = array();

        foreach($rty_IDs as $rty_ID){
            $code = $rty_Codes[$rty_ID];
            if(!@$rty_CodesToCheck[$code]){ //not already was in previous database
                array_push($rty_IDs_ToCheck, intval($rty_ID));
                array_push($rty_CodesToCheck, $code);
            }
       }
        $rty_Names = array_merge($rty_Names, $rty_Names_temp);


        $query = 'select rst_RecTypeID, dty_Type, rst_DisplayName, dty_OriginatingDBID, dty_IDInOriginatingDB, '
        .'dty_ID, dty_PtrTargetRectypeIDs, dty_JsonTermIDTree, rst_RequirementType '
        .'from defRecStructure, defDetailTypes  where dty_ID=rst_DetailTypeID AND rst_RecTypeID in ('
                .implode(',',$rty_IDs_ToCheck).')';

        $res = $mysqli->query($query);
        if (!$res) {  print htmlspecialchars($query.'  '.$mysqli->error); return; }

        //gather fields ids, pointer constraints, vocabs/terms - as concept codes
        while ($row = $res->fetch_assoc()) {

             $rty_Code = $rty_Codes[$row['rst_RecTypeID']];
             $dty_Code = $row['dty_OriginatingDBID'].'-'.$row['dty_IDInOriginatingDB'];

             if(!@$fields[$rty_Code]) {$fields[$rty_Code] = array();}

             $fields[$rty_Code][$dty_Code] = $row['rst_DisplayName'];
             $fields_req[$rty_Code][$dty_Code] = $row['rst_RequirementType'];

             if($row['dty_Type']=='resource' && $row['dty_PtrTargetRectypeIDs'] && !@$constraints[$dty_Code]){
                 //convert constraints to concept codes
                 $rids = explode(',', $row['dty_PtrTargetRectypeIDs']);
                 $codes = array();

                 foreach($rids as $rty_id){
                     $codes[] = $rty_Codes[$rty_id];
                 }
                 //keep constraints
                 $constraints[$dty_Code] = $codes;

             } elseif (($row['dty_Type']=='enum' || $row['dty_Type']=='relmarker') && !@$terms_codes[$dty_Code]){

                 $domain = $row['dty_Type']=='enum'?'enum':'relation';

                 $terms = VerifyValue::getAllowedTerms($row['dty_JsonTermIDTree'], $row['dty_ID']);
                 $codes = array();
                 foreach($terms as $trm_id){
                     $term = $all_terms['termsByDomainLookup'][$domain][$trm_id];
                     $codes[] = $term[$ti_dbid].'-'.$term[$ti_oid];
                 }
                 //get concept codes
                 $terms_codes[$dty_Code] = $codes;
             }

        }

    }//for origin

    //-----------------------------------------------

    foreach ($databases as $idx=>$db_name){

        mysql__usedatabase($mysqli, $db_name);

        $smsg = "";

        VerifyValue::reset();
        $all_terms = dbs_GetTerms($system);

        $constraints2 = array();//per detail
        $terms_codes2 = array();//per detail

        $fileds_differ_terms = array();//differenece in field list
        $fileds_missed_terms = array();//missed in this db
        $fileds_missed_rectypes = array();

        //find all rty-codes
        $rty_Codes2 =  mysql__select_assoc2($mysqli,'SELECT rty_ID, CONCAT(rty_OriginatingDBID,"-",rty_IDInOriginatingDB) as rty_Code FROM defRecTypes');
        //find all term codes
        $trm_Codes2 =  mysql__select_assoc2($mysqli,'SELECT trm_ID, CONCAT(trm_OriginatingDBID,"-",trm_IDInOriginatingDB) as trm_Code FROM defTerms');


        $stmt = $mysqli->prepare('select rty_OriginatingDBID, rty_IDInOriginatingDB FROM defRecTypes WHERE rty_Name=?');

        //loop by rectypes
        foreach ($rty_CodesToCheck as $index=>$rty_Code){

            $msg_error = '';

            if(array_search($rty_Code, $rty_Codes2)===false) {continue;}//there is no such record type

            list($db_id, $orig_id) = explode('-',$rty_Code);

            //check for unexpected concept code by name
            $rty_Name = @$rty_Names[$rty_Code];
            if($rty_Name==null){
                $msg_error = $msg_error. "<p style='padding-left:20px'> Record type for code ".htmlspecialchars($rty_Code)
                        ." not found in original database</p>";
            }else{
                $res = false;

                call_user_func_array(array($stmt, 'bind_param'), referenceValues(array('s',$rty_Name)));
                if($stmt->execute()){
                    $res = $stmt->get_result();
                }



                if (!$res) {  print htmlspecialchars($db_name.'  '.$query.'  '.$mysqli->error); return; }
                $row = $res->fetch_assoc();
                if($row && $row[0]!=$db_id || $row[1]!=$orig_id){
                       $msg_error = $msg_error."<p style='padding-left:20px'>name = ".htmlspecialchars($rty_Name)
                        ." : Unexpected concept ID ".$row[0].'-'.$row[1].'</p>';
                }
            }


            $query = 'select rst_RecTypeID, dty_Type, rty_Name, dty_Name, dty_OriginatingDBID, dty_IDInOriginatingDB, '
            .'dty_ID, dty_PtrTargetRectypeIDs, dty_JsonTermIDTree '
            .'from defRecStructure, defDetailTypes, defRecTypes '
            .'where rty_ID=rst_RecTypeID AND dty_ID=rst_DetailTypeID AND rty_OriginatingDBID='
            .intval($db_id).' AND rty_IDInOriginatingDB='.intval($orig_id);

            $res = $mysqli->query($query);
            if (!$res) {  print htmlspecialchars($db_name.'  '.$query.'  '.$mysqli->error); return; }

            $fields2 = array();//per record type

            //gather fields ids, pointer constraints, vocabs/terms - as concept codes
            while ($row = $res->fetch_assoc()) {

                 $rty_Name = $row['rty_Name'];
                 $dty_Code = $row['dty_OriginatingDBID'].'-'.$row['dty_IDInOriginatingDB'];

                 array_push($fields2, $dty_Code);

                 if($row['dty_Type']=='resource' && $row['dty_PtrTargetRectypeIDs'] && !@$constraints2[$dty_Code]){
                     //convert constraints to concept codes
                     $rids = explode(',', $row['dty_PtrTargetRectypeIDs']);
                     $codes = array();

                     foreach($rids as $r_id){
                         $code = @$rty_Codes2[$r_id];
                         if($code){
                            $codes[] = $code;
                         }else{
                            $msg_error = $msg_error.'<p>Field '.$row['dty_Name'].' (code '.$row['dty_ID']
                                .') has invalid record type constraint (code: '.$r_id.')</p>';
                         }
                     }
                     //keep constraints
                     $constraints2[$dty_Code] = $codes;

                 } elseif (($row['dty_Type']=='enum' || $row['dty_Type']=='relmarker') && !@$terms_codes2[$dty_Code]){

                     $domain = $row['dty_Type']=='enum'?'enum':'relation';

                     $terms = VerifyValue::getAllowedTerms($row['dty_JsonTermIDTree'], $row['dty_ID']);

                     $codes = array();
                     if(is_array($terms)){
                         foreach($terms as $trm_id){
                             if(@$all_terms['termsByDomainLookup'][$domain][$trm_id]){
                                $term = $all_terms['termsByDomainLookup'][$domain][$trm_id];
                                $codes[] = $term[$ti_dbid].'-'.$term[$ti_oid];
                             }else{
                                $msg_error = $msg_error.'<p>Term does not exist '.$trm_id.' in field '.$row['dty_ID'].'  '.$row['dty_Name'].' ('.$row['dty_JsonTermIDTree'].')</p>';
                             }

                         }
                     }elseif($terms!='all'){
                         $msg_error = $msg_error.'<p>Can\'t parse term list "'.$row['dty_JsonTermIDTree'].'" for field '.$row['dty_ID'].'  '.$row['dty_Name'].'</p>';
                     }
                     //get concept codes
                     $terms_codes2[$dty_Code] = $codes;
                 }
            }//for fields

            //analyze

            if(count($fields2)===0) {continue;} //there is no such record type

            //1. check fields
            $missing = array();
            foreach($fields[$rty_Code] as $f_code=>$f_name){  //field code=>field name

                if ((!$test_optfields && $fields_req[$rty_Code][$f_code] == 'optional')||
                   (!$test_recfields && $fields_req[$rty_Code][$f_code] == 'recommended')) {
                    continue;
                }

                if(array_search($f_code, $fields2)===false){ //not found

                    if($fields_req[$rty_Code][$f_code] == 'required'){
                        $red_color = ' style="color:red">';
                    }else{
                        $red_color = '>';
                    }

                    array_push($missing, '<i'.$red_color.htmlspecialchars($f_code.'  '.$f_name).'</i>');
                }
            }
            if(!empty($missing)){
                $msg_error = $msg_error."<p style='padding-left:40px'>missing fields: ".implode(', ',$missing)."</p>";
            }
            //2. check constraints
            foreach($constraints as $dty_Code=>$codes){
                if(@$fields[$rty_Code][$dty_Code] && array_search($dty_Code, $fields2)!==false){ //exists in original recctype and in this db
                    $missing = array();
                    $missing2 = array();
                    foreach($codes as $rty_code){
                        if(!is_array(@$constraints2[$dty_Code]) || array_search($rty_code, $constraints2[$dty_Code])===false){ //not found or unconstrained
                            array_push($missing, htmlspecialchars($rty_code.'  '.@$rty_Names[$rty_code]) );
                        }
                        if(!@$fileds_missed_rectypes[$rty_code] && array_search($rty_code, $rty_Codes2)===false){
                            $fileds_missed_rectypes[$rty_code] = $rty_code.'  '.htmlspecialchars(@$rty_Names[$rty_code]);

                        }
                    }
                    if(!empty($missing)){
                       $msg_error = $msg_error."<p style='padding-left:40px'>field ".$dty_Code
                                    .' '.htmlspecialchars($fields[$rty_Code][$dty_Code])
                                    .': missing constraint record types '.implode(',',$missing).'</p>';
                    }
                }
            }//foreach constaints

            //3. check terms
            foreach($terms_codes as $dty_Code=>$codes){
                if(@$fields[$rty_Code][$dty_Code] && array_search($dty_Code, $fields2)!==false){ //exists in original recctype and in this db
                    $missing = array();
                    $missing2 = array();
                    if(!@$fileds_differ_terms[$dty_Code]){
                        foreach($codes as $trm_code){
                            if(!is_array(@$terms_codes2[$dty_Code]) || array_search($trm_code, $terms_codes2[$dty_Code])===false){ //not found
                                array_push($missing, $trm_code );
                            }
                            if(array_search($trm_code, $fileds_missed_terms)===false
                                        && array_search($trm_code, $trm_Codes2)===false){ //all codes of terms in this db
                                array_push($fileds_missed_terms, $trm_code );
                            }

                        }
                        if(!empty($missing)){
                               $fileds_differ_terms[$dty_Code] =
                                        "<p style='padding-left:10px'>field ".$dty_Code.' <b>'
                                        .htmlspecialchars($fields[$rty_Code][$dty_Code])
                                        .'</b>:</p><p style="padding-left:40px"> missing terms in defined list: '
                                        .implode(', ',$missing).'</p>';
                        }
                    }
                    if(@$fileds_differ_terms[$dty_Code]){
                       $msg_error = $msg_error."<p style='padding-left:40px'>field ".$dty_Code
                                    .' <i>'.htmlspecialchars($fields[$rty_Code][$dty_Code])
                                    .'</i>: missing terms (see below)</p>';
                    }

                    /*heck term presence in this db
                    if(!empty($missing2)){
                       $msg_error = $msg_error."<p style='padding-left:40px'>field ".$dty_Code.' '.$fields[$rty_Code][$dty_Code]
                                    .': missing terms '.implode(',',$missing2).'</p>';
                    }
                    */
                }
            }//foreach terms

            if($msg_error){
                $smsg = $smsg
                    ."<p style='padding-left:20px'>id = ".intval($rty_Code).'  <b>'.htmlspecialchars(@$rty_Names[$rty_Code])
                    .((@$rty_Names[$rty_Code]!=$rty_Name)?'</b> <i>(in this database: '.htmlspecialchars($rty_Name).')</i>':'</b>').'</p>'
                    .$msg_error;
            }

        }//for record types


        $has_issues = ($smsg!='' || !empty($fileds_differ_terms) || !empty($fileds_missed_rectypes) ||
            (@$_REQUEST['termlists']==1 && !empty($fileds_missed_terms)));


        //
        //
        if(@$_REQUEST['verbose']==1 && count($databases)==1){
           if($has_issues){
               print '<p>The differences indicated are not necessarily of concern, as we make incremental improvements to the core structures, but if there appear to be errors and you are concerned, please '.CONTACT_HEURIST_TEAM.' with the name of your database and we will check it out</p>';
           }else{
               print '<p>No differences found</p>';
           }
        }

        if(count($databases)>1 && ($has_issues || @$_REQUEST['showalldbs']==1)){
            print '<h4>db = '.htmlspecialchars($db_name).'</h4>';
        }

        if($has_issues){

            if($smsg) {print $smsg;} //main error message

            if(!empty($fileds_differ_terms)){
                print implode('',$fileds_differ_terms);
            }
            if(@$_REQUEST['termlists']==1 && !empty($fileds_missed_terms)){
                print "<p style='padding-left:10px'>Missing terms (they are in defined the list of enumeration fields but not found in the terms table): "
                    .implode(', ',$fileds_missed_terms).'</p>';
            }
            if(!empty($fileds_missed_rectypes)){
                print "<p style='padding-left:10px'>Missing record types (however they are present in the constraint of record pointer field(s)):</p>"
                    ."<p style='padding-left:40px'>"
                    .implode('<br>',$fileds_missed_rectypes).'</p>';
            }
            if(count($databases)>1){
                print '<hr>';
            }
        }


    }//while  databases

    }//password check


    if(@$_REQUEST['verbose']!=1){
?>
</body></html>
<?php
    }
?>
