<?php

/**
* listUploadedFilesMissed.php - light weight version of listUploadedFilesErrors.php:
* Lists missed files that are listed in recUploadedFiles
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.1.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

use hserv\utilities\USanitize;

$is_included = (defined('PDIR'));
$has_broken_url = false;

if($is_included){

    print '<div style="padding:10px"><h3 id="recordfiles_missed_msg">Check missed registered files</h3><br>';

}else{

    define('PDIR','../../');

    require_once dirname(__FILE__).'/../../autoload.php';

    $sysadmin_pwd = USanitize::getAdminPwd();

    $system = new hserv\System();
    if( ! $system->init(@$_REQUEST['db']) ){
        //get error and response
        print $system->getErrorMsg();
        return;
    }

    if( @$_REQUEST['all']==1 ){
        if($system->verifyActionPassword($sysadmin_pwd, $passwordForServerFunctions)){
        ?>

        <form action="listUploadedFilesMissed.php" method="POST">
            <div style="padding:20px 0px">
                Only an administrator (server manager) can carry out this action.<br>
                This action requires a special system administrator password (not a normal login password)
            </div>

            <span style="display: inline-block;padding: 10px 0px;">Enter password:&nbsp;</span>
            <input type="password" name="pwd" autocomplete="off" />
            <input type="hidden" name="db" value="<?php  echo htmlspecialchars($_REQUEST['db']);?>"/>
            <input type="hidden" name="all" value="1"/>

            <input type="submit" value="OK" />
        </form>

        <?php
        exit;
        }
    }elseif(!$system->is_admin()){ //  $system->is_dbowner()
        print '<span>You must be logged in as Database Administrator to perform this operation</span>';
        exit;
    }
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title><?php echo HEURIST_TITLE; ?></title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
    </head>
    <body class="popup">
        <div class="banner">
            <h3>Missed registered files</h3>
        </div>
        <div id="page-inner">
<?php
}

$mysqli = $system->get_mysqli();

$is_all_databases = false;
if(@$_REQUEST['all']==1){
    //scan all databases
    $is_all_databases = true;
    $databases = mysql__getdatabases4($mysqli, true);
}elseif(@$_REQUEST['db']){
    list($db_full, $db) = mysql__get_names($_REQUEST['db']);
    $databases = array($db_full);
}

$total_count = 0;
$missed = array();
$missed_folders = array();

foreach ($databases as $idx=>$db_name){


    list($db_full_name, $db_name) = mysql__get_names($db_name);// full name used for query, short hand used for filestore

    $db_full_name = preg_replace(REGEX_ALPHANUM, "", $db_full_name);//for snyk
    $db_name = preg_replace(REGEX_ALPHANUM, "", $db_name);//for snyk

    $query2 = 'SELECT ulf_FilePath, ulf_FileName FROM `'.$db_full_name.'`.recUploadedFiles '
                    .'WHERE ulf_FileName is not null ORDER BY ulf_FilePath';

    $res2 = $mysqli->query($query2);

    if($res2){

        while ($row = $res2->fetch_assoc()) {

            if(@$row['ulf_FilePath'] || @$row['ulf_FileName']){

                $full_path = (@$row['ulf_FilePath']==null?'':$row['ulf_FilePath']).@$row['ulf_FileName'];
                $res_fullpath = resolveFilePath($full_path, $db_name);
                if(!file_exists($res_fullpath)){

                    $missed[] = array($db_name, @$row['ulf_FilePath'], $row['ulf_FileName']);

                    $key = htmlspecialchars($db_name.','.@$row['ulf_FilePath']);
                    if(!@$missed_folders[$key]){
                        $missed_folders[$key] = 0;
                    }
                    $missed_folders[$key]++;
                }
                $total_count++;
            }

        }//while

        $res2->close();

    }else{
        print htmlspecialchars($db_name).' Cannot execute query. Error: '.$mysqli->error;
    }

}//for databases

if(isEmptyArray($missed)){
    echo '<div><h3 class="res-valid">OK: All records have valid URL</h3></div>';
}else{

    print 'Summary:<br>';
    foreach($missed_folders as $key=>$cnt){
        print $key.",".intval($cnt).'<br>';
    }

    print '<br><br>Detail:<br>';
    print 'Database name,Directory name,File name<br>';
    foreach($missed as $data){
        print htmlspecialchars(implode(',',$data)).'<br>';
    }

    print '<div style="padding-top:20px;color:red">There are <b>'.count($missed).' of '.$total_count
         .'</b> registered files are missed</div>';

}

if(!$is_included){
    print '</div></body></html>';
}else{

    if($has_broken_url){
        echo '<script>$(".recordfiles_missed").css("background-color", "#E60000");</script>';
    }else{
        echo '<script>$(".recordfiles_missed").css("background-color", "#6AA84F");</script>';
    }
    print '<br></div>';
}
?>
