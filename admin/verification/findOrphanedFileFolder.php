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
    * Find orphaned file folders ie without databases
    *
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @copyright   (C) 2005-2023 University of Sydney
    * @link        https://HeuristNetwork.org
    * @version     3.1
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @package     Heurist academic knowledge management system
    * @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
    */

ini_set('max_execution_time', '0');

define('ADMIN_PWD_REQUIRED',1);
define('OWNER_REQUIRED',1);
define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';

if(!@$_REQUEST['mail']){
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>List of orphaned file folders. Ie without databases</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
    </head>
    <body class="popup">

        <script>window.history.pushState({}, '', '<?php echo htmlspecialchars($_SERVER['PHP_SELF']);?>')</script>
        <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px">
        <form name="actionForm">
<?php
}else{
    $_REQUEST['dbs'] = null; //reset - only report
}

    $mysqli = $system->get_mysqli();
    //1. find all database
    $databases = mysql__getdatabases4($mysqli, false);

    $root = $system->getFileStoreRootFolder();

    //get all subfolder in HEURIST_FILESTORE
    $folders = folderSubs($root, array('AAA_LOGS','_PURGES_IMPORTS', '_PURGES_SYSARCHIVE', 'DELETED_DATABASES', '_BATCH_PROCESS_ARCHIVE_PACKAGE'), false);

    $rep = '';
    $orphaned = array();

    //loop folders - check that database exists
    foreach ($folders as $db_name){

        $dbname = mysql__select_value($mysqli,
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = \''
                .$mysqli->real_escape_string(HEURIST_DB_PREFIX.$db_name).'\'');
        if(!$dbname){
            $orphaned[] =$db_name;
        }
    }
    $cnt = 0;
    $cnt_del = 0;
    if(!empty($orphaned)){

        $to_delete = null;
        if(@$_REQUEST['dbs']){
            $to_delete = $_REQUEST['dbs'];
            if(isEmptyArray($to_delete)){
                $to_delete = null;
            }

        }

        $rep.='<h3>ORPHANED FOLDERS in HEURIST_FILESTORE_DIR</h3>';

        foreach ($orphaned as $db_name){
            $res = false;
            if(is_array($to_delete) && in_array($db_name, $to_delete)){
                //remove folder
                $res = folderDelete2($root.$db_name, true);
                if($res){
                    $cnt_del++;
                }
            }else{
                $cnt++;
            }
            $db_name = htmlspecialchars($db_name);
            if($res){
                $rep .= '<label>REMOVED: '.$db_name.'</label><br>';
            }else{
                $rep .= '<label><input name="dbs[]" type="checkbox" value="'.$db_name.'">'.$db_name.'</label><br>';
            }

        }
        $rep.='<hr>';
    }

    if(@$_REQUEST['mail']){
        if($cnt>0){
            $rv = sendEmail(HEURIST_MAIL_TO_ADMIN, 'List of orphaned file folders (ie without databases)',
                                            $rep, true);
        }
    }else{
        if($cnt>0 || $cnt_del>0){
            print $rep;
        }
        if($cnt>0){
            print '<br><button type="submit" class="ui-button-action">Remove selected folders</button></form>';
        }else{
            print 'No orphaned folders detected';
        }
        print '</div></body></html>';

    }
?>