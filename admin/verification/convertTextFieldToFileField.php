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
    * convertTextFieldToFileField - change text field to file field, register new remote in recUploadedFiles and update recDetails
    *
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @copyright   (C) 2005-2023 University of Sydney
    * @link        https://HeuristNetwork.org
    * @version     3.1
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */

define('PDIR','../../');//need for proper path to js and css

use hserv\utilities\USanitize;

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';

$sysadmin_pwd = USanitize::getAdminPwd();

if( $system->verifyActionPassword($sysadmin_pwd, $passwordForServerFunctions) ){
    ?>

    <form action="convertTextFieldToFileField.php" method="POST">
        <div style="padding:20px 0px">
            Only an administrator (server manager) can carry out this action.<br>
            This action requires a special system administrator password (not a normal login password)
        </div>

        <span style="display: inline-block;padding: 10px 0px;">Enter password:&nbsp;</span>
        <input type="password" name="pwd" autocomplete="off" />

        <br>
        <span style="display: inline-block;padding: 10px 0px;">Concept code:&nbsp;</span>
        <input name="conceptid" autocomplete="off" />

        <input type="submit" value="OK" />
    </form>

    <?php
    exit;
}

?>

<script>window.history.pushState({}, '', '<?php echo htmlspecialchars($_SERVER['PHP_SELF']);?>')</script>

<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px">
            <p>It converts specified text field to file field, registers url (from dtl_Value) and assign ulf_ID to recDetails</p>
<?php

    $ccode = @$_REQUEST['conceptid'];

    if(!$ccode){
        print 'conceptid is not defined';
        exit;
    }
    list($orig_db_id, $orig_id) = explode('-',$ccode);
    $orig_db_id = intval($orig_db_id);
    $orig_id = intval($orig_id);
    if(!($orig_db_id>0 && $orig_id>0)){
        print 'conceptid is not wrong';
        exit;
    }

    $type_2 = 'external';
    $type_ = ULF_REMOTE;
    if($orig_db_id==2 && $orig_id==34){
        $type_ = ULF_TILED_IMAGE.'@';
        $type_2 = 'tiled';
    }

    $mysqli = $system->get_mysqli();

    //1. find all database
    $databases = mysql__getdatabases4($mysqli, true);
    
    print DIV_S;
    $k = 1;



    foreach ($databases as $idx=>$db_name){

        $db_name = preg_replace(REGEX_ALPHANUM, "", $db_name);//for snyk

        $query = "select dty_ID from `$db_name`.defDetailTypes where  dty_Type='freetext' AND dty_OriginatingDBID="
                    .$orig_db_id.' and dty_IDInOriginatingDB='.$orig_id;
        $dty_ID = mysql__select_value($mysqli, $query);

        $dty_ID = intval($dty_ID);

        if($dty_ID>0)
        {
            //change
            $query = "update `$db_name`.defDetailTypes set dty_Type='file' where dty_ID=".$dty_ID;
            $mysqli->query($query);

            $m = 0;
            //create new recUploadedFiles entries and set ulf_ID to records
            $query = "select dtl_ID, dtl_Value from `$db_name`.recDetails where dtl_DetailTypeID=".$dty_ID;
            $res = $mysqli->query($query);
            while ($row = $res->fetch_row()){

                $dtl_ID = intval($row[0]);
                $url = $row[1];



                $nonce = addslashes(sha1($k.'.'.random_int(0,99)));
                $ext = ($type_==ULF_REMOTE) ? recognizeMimeTypeFromURL($mysqli, $url) :'png';//@todo check preferred source

                $insert_query = "insert into `$db_name`.recUploadedFiles "
                .'(ulf_OrigFileName,ulf_ObfuscatedFileID,ulf_UploaderUGrpID,ulf_ExternalFileReference,ulf_MimeExt,ulf_PreferredSource) '
                .' values (?,?,2,?,?,?)';

                $ulf_ID = mysql__exec_param_query($mysqli, $insert_query,
                    array('sssss', $type_, $nonce, $url, $ext, $type_2), true);

                if($ulf_ID>0){
                    $query = "update `$db_name`.recDetails set dtl_Value=null, `dtl_UploadedFileID`="
                            .intval($ulf_ID).' where dtl_ID='.$dtl_ID;
                    $mysqli->query($query);

                    $k++;
                    $m++;
                }else{
                    print 'Cannot register url '.htmlspecialchars($url).' for detail id '.$dtl_ID;
                    exit;
                }

            }//while recDeetails

            if($m>0){
                print $db_name.'  dty_ID='.intval($dty_ID).'  count='.$m.'<br>';
            }

        }
    }//foreach
    print '</div><br>[end operation]';
?>
