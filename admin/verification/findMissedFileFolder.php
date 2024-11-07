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
    * Find missed system folders for all databases
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
define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';

if(!@$_REQUEST['mail']){
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>List of databases with missing or non-writeable folders</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
    </head>
    <body class="popup">

        <script>window.history.pushState({}, '', '<?php echo htmlspecialchars($_SERVER['PHP_SELF']);?>')</script>
        <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px">
                <h2>List of databases with missing or non-writeable folders</h2>
<?php
}

    $mysqli = $system->get_mysqli();

    //1. find all database
    $databases = mysql__getdatabases4($mysqli, false);

    $root = $system->getFileStoreRootFolder();

    $check_subfolders = true;

    $folders = $system->getArrayOfSystemFolders();

    $not_exists = array();
    $not_writeable = array();
    $not_exists2 = array();
    $not_writeable2 = array();

    foreach ($databases as $idx=>$db_name){

        $db_name = basename($db_name);

        $dir = $root.$db_name.'/';

        $check = folderExists($dir, true);
        if($check==-1){
            $not_exists[] = $db_name;
        }elseif($check<0){
            $not_writeable[] = $db_name;
        }elseif($check_subfolders){
             //check subfolders
             foreach ($folders as $folder_name=>$folder){

                 if($folder[0]=='' || $folder[0]==null || $folder_name=='uploaded_tilestacks') {continue;}

                 $subdir = $dir.$folder_name.'/';
                 $check = folderExists($subdir, true);
                 if($check==-1){
                      $not_exists2[] = $subdir;
                 }elseif($check<0){
                      $not_writeable2[] = $subdir;
                 }
             }

        }

    }//while  databases

    $rep = '';

    if(!empty($not_exists)){
        $rep.='<h3>MISSED HEURIST_FILESTORE_DIR for databases:</h3>';

        foreach ($not_exists as $db_name){
                $rep.=$db_name.'<br>';
        }
        $rep.='<hr>';
    }
    if(!empty($not_writeable)){
        $rep.='<h3>NOT WRITEABLE HEURIST_FILESTORE_DIR for databases:</h3>';

        foreach ($not_writeable as $db_name){
                $rep.=$db_name.'<br>';
        }
        $rep.='<hr>';
    }

    if(!empty($not_exists2)){
        $rep.='<h3>MISSED subfolders</h3>';

        foreach ($not_exists2 as $dir){
            if(strpos($dir,'file_uploads')>0){
                $rep.='<b>'.$dir.'</b><br>';
            }else{
                $rep.=$dir.'<br>';
            }
        }
        $rep.='<hr>';
    }
    if(!empty($not_writeable2)){
        $rep.='<h3>NOT WRITEABLE subfolders</h3>';

        foreach ($not_writeable2 as $dir){
            if(strpos($dir,'file_uploads')>0){
                $rep.='<b>'.$dir.'</b><br>';
            }else{
                $rep.=$dir.'<br>';
            }
        }
        $rep.='<hr>';
    }


    $rep.='[end report]</div>';

    if(@$_REQUEST['mail']){

        $rv = sendEmail(HEURIST_MAIL_TO_ADMIN, 'List of databases with missing/inaccessible folders',
                                            $rep, true);
        exit;
    }else{

        print $rep;
        print '</body></html>';

    }


?>