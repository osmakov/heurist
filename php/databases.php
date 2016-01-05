<?php

    /**
    * Produces the page - list of available databases
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2015 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */


    require_once(dirname(__FILE__)."/System.php");

    $system = new System();
    $isSystemInited = $system->init(@$_REQUEST['db'], false); //init wihout db

    if( !$isSystemInited ){  //can not init system (apparently connection to Database Server is wrong or server is down)
        $err = $system->getError();
        $error_msg = @$err['message'];
    }else {
        
        if (@$_REQUEST['msg']){
            $error_msg = $_REQUEST['msg'];
        }

        $list =  mysql__getdatabases4($system->get_mysqli());
        if(count($list)<1){
            //reditrect to create database
            header('Location: ' . HEURIST_BASE_URL . 'admin/setup/dbcreate/createNewDB.php');
            return;
        }
    }
?>
<html>
    <head>
        <title><?=HEURIST_TITLE?></title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">

        <link rel=icon href="../favicon.ico" type="image/x-icon">

        <link rel="stylesheet" href="../ext/jquery-ui-1.10.2/themes/heurist/jquery-ui.css" />
        <link rel="stylesheet" type="text/css" href="../h4styles.css">

        <script type="text/javascript">
        </script>

    </head>
    <body class="ui-heurist-header1">
        <div class="ui-corner-all ui-widget-content" 
            style="text-align:left; min-width:220px; padding: 0.5em; position:absolute; top:30px; bottom:30px; left:60px;right:60px">
            

            <div class="logo" style="background-color:#2e3e50;width:100%"></div>

<?php
    if(isset($error_msg)){
            echo '<div class="ui-state-error" style="width:90%;margin:auto;margin-top:10px;padding:10px;">';
            echo '<span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>';
            echo $error_msg.'</div>';
            $list_top = '12em';
    }else{
            $list_top = '6em';
    }
    //<div style="width:70%; height:100%;margin:0px auto; padding: 0.5em;">
    //position:absolute;top:12em;bottom:0.2em;
//            <div style="overflow-y:auto;width:90%;">
//style="overflow-y:auto;height: echo $list_height;;"

    if($isSystemInited){
?>
            <div style="padding: 0.5em;">Please select a database from the list</div>
                <ul class="db-list" style="overflow-y:auto;position:absolute;top:<?php echo $list_top;?>;bottom:0.5em;left:1em;right:0.5em">
                    <?php
                        /* DEBUG for($i=0;$i<100;$i++) {
                        array_push($list, "database".$i);
                        }*/
                        foreach ($list as $name) {
                            print("<li><a href='".HEURIST_BASE_URL."?db=$name'>$name</a></li>");
                        }

                    ?>
                </ul>

<?php
    }
?>

        </div>
    </body>
</html>
