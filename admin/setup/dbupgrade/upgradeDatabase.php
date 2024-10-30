<?php

    /**
    * upgradeDatabase.php: detects outdataed database and applies SQL to database to make changes to underlying format
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
    * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     3.2
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

if(!defined('PDIR')){
    define('PDIR','../../../');//need for proper path to js and css
    require_once dirname(__FILE__).'/../../../hclient/framecontent/initPageMin.php';
}
    require_once dirname(__FILE__).'/../../../hserv/utilities/utils_db_load_script.php';
    require_once dirname(__FILE__).'/../../../hserv/structure/import/dbsImport.php';

    $src_maj = intval( $system->get_system('sys_dbVersion') );
    $src_min = intval( $system->get_system('sys_dbSubVersion') );
    $src_sub = intval( $system->get_system('sys_dbSubSubVersion') );

    $trg_ver = explode(".", HEURIST_MIN_DBVERSION);
    $trg_maj = intval($trg_ver[0]);
    $trg_min = intval($trg_ver[1]);
    $trg_sub = intval($trg_ver[2]);

    if( $src_maj==$trg_maj && $src_min == $trg_min && $src_sub==$trg_sub){ //versions are ok redirect to main page
        redirectURL(HEURIST_BASE_URL . '?db=' . $_REQUEST['db']);
        exit;
    }
?>
<!DOCTYPE html>
<html lang="en" xml:lang="en">
    <head>
        <title>Heurist database version upgrade</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">

        <link rel=icon href="<?php echo PDIR?>favicon.ico" type="image/x-icon">

<?php
        includeJQuery();
?>

        <!-- CSS -->
        <?php include_once dirname(__FILE__).'/../../../hclient/framecontent/initPageCss.php';?>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hapi.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/HSystemMgr.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/profile/profile_login.js"></script>
        <script>
            $(document).ready(function() {

                if(!window.hWin.HAPI4){
                    window.hWin.HAPI4 = new hAPI('<?php echo htmlspecialchars($_REQUEST['db'])?>', onHapiInit);
                }else{
                    onHapiInit(true);
                }

                function onHapiInit(success){

                    var prefs = window.hWin.HAPI4.get_prefs();
                    if(!window.hWin.HR){
                        //loads localization
                        window.hWin.HR = window.hWin.HAPI4.setLocale(prefs['layout_language']);
                    }

                    $('button').button();

                    $(window.hWin.document).on(
                        window.hWin.HAPI4.Event.ON_CREDENTIALS,
                        function(){
                            if(window.hWin.HAPI4.has_access()){
                                window.location.reload()
                            }
                        });

                }

            });
        </script>
    </head>

    <body class="ui-heurist-header1" style="font-size:0.7em">
        <div class="ui-corner-all ui-widget-content"
            style="text-align:left; min-width:220px; padding: 0.5em; position:absolute; top:30px; bottom:30px; left:60px;right:60px">

            <div class="logo" style="background-color:#2e3e50;width:100%"></div>

            <div style="margin-top:24px;">
                <h2>Heurist Database Version upgrade</h2>
            </div>
            <div style="position:absolute;top:100px;bottom:10px;width: 99%;overflow-y:auto;text-align:left;">

                <?php

                    if($system->is_admin() && @$_REQUEST['mode']=='action' && $src_maj==$trg_maj){ //upgrade minor versions
                    //2d iteration ACTION!!!
                        $upgrade_success = true;
                        $keep_minver = $src_min;
                        $dir = HEURIST_DIR.'admin/setup/dbupgrade/';
                        while ( $src_min<$trg_min || ($src_min==3 && $src_sub<$trg_sub) ) {
                            $filename = "DBUpgrade_$src_maj.$src_min.0_to_$trg_maj.".($src_min+1).'.0';

                            if($trg_maj==1 && $src_min==2){
                                $filename = $filename.'.php';
                            }elseif($src_min==3 && $trg_sub>0){
                                $filename = 'DBUpgrade_1.3.0_to_1.3.14.php';
                            }else{
                                $filename = $filename.'.sql';
                            }

                            if( file_exists($dir.$filename) ){

                                if($trg_maj==1 && $src_min==2){
                                    include_once $filename;
                                    $rep = updateDatabseTo_v3($system);//PHP
                                }elseif($src_min==3 && $src_sub<$trg_sub){

                                    if($src_sub<16){
                                        include_once $filename;
                                        $rep = updateDatabseTo_v1_3_16($system);

                                        if($rep!==false && $src_sub<14){ //for db_utils.php
                                            $rep2 = recreateRecDetailsDateIndex($system, true, true);
                                            if($rep2){
                                                $rep = array_merge($rep, $rep2);
                                            }else{
                                                $rep = false;
                                            }
                                        }

                                    }else{
                                        $rep = array('');
                                    }

                                }else{
                                    $rep = executeScript($dir.$filename);//execute SQL script
                                }

                                if($rep){
                                    $src_min++;

                                    if(is_array($rep)){
                                        foreach($rep as $msg){
                                            print '<p>'.$msg.'</p>';
                                        }
                                    }
                                    if($trg_min==3 && $trg_sub>0){ //to 1.3.16

                                    }else{
                                        print "<p>Upgraded to $src_maj.$src_min.0</p>";
                                    }

                                }else{
                                    $error = $system->getError();
                                    if($error){
                                        print error_Div($error['message'].BR.@$error['sysmsg']);
                                    }

                                    $upgrade_success = false;
                                    break;
                                }

                            }else{
                                print "<p style='font-weight:bold'>Cannot find the database upgrade script '".$filename
                                ."'.".CONTACT_HEURIST_TEAM_PLEASE.'</p>';
                                $upgrade_success = false;
                                break;
                            }
                        }

                        if( (!($trg_min==3 && $trg_sub>0)) && $src_min>$keep_minver){ //update database - set version up to date
                            $mysqli = $system->get_mysqli();
                            mysql__usedatabase($mysqli, HEURIST_DBNAME);
                            $query1 = "update sysIdentification set sys_dbSubVersion=$src_min, sys_dbSubSubVersion=0 where 1";
                            $res1 = $mysqli->query($query1);

                            print "<br>";
                        }

                        if($upgrade_success){
                            print "<p>Upgrade was successeful.&nbsp;&nbsp;<a href='".HEURIST_BASE_URL."?db=".HEURIST_DBNAME."'>Return to main page</a></p>";
                        }

                    }
                    else{
                    //1d itweration INFORMATION!!!
                    ?>

                    <p>Your database <b> <?=HEURIST_DBNAME?> </b> currently uses database format version
                        <b><?=$src_maj.'.'.$src_min.'.'.$system->get_system('sys_dbSubSubVersion')?> </b>
                        <br>(this is distinct from the program version # listed below)</p>

                    <p>This version of the software <b>(Vsn <?=HEURIST_VERSION?>)</b>
                        requires upgrade of your database to format version <b><?=HEURIST_MIN_DBVERSION?></b>
                        <hr>
                    </p>

                    <?php

                        if($system->is_admin()){

                            if($src_maj!=$trg_maj){
                                print '<p style="font-weight:bold">Automatic upgrade applies to minor version updates only (ie. within database version 1, 2 etc.).'.CONTACT_HEURIST_TEAM_PLEASE.' to upgrade major version (1 => 2, 2 => 3)</p>';
                            }else{

                                //verification that all scripts exist and get safety rating from these scrips

                                $scripts_info = "";
                                $is_allfind = true;
                                //DBUpgrade_1.1.0_to_1.2.0.sql etc.
                                $dir = HEURIST_DIR.'admin/setup/dbupgrade/';
                                while ($src_min<$trg_min) {
                                    $filename = "DBUpgrade_$src_maj.$src_min.0_to_$trg_maj.".($src_min+1).".0.sql";
                                    if( file_exists($dir.$filename) ){

                                        $safety = "";
                                        $description = "";

                                        $file = fopen($dir.$filename, "r");
                                        if($file){
                                            while(!feof($file)){
                                                $line = fgets($file);

                                                if(strpos($line,"-- Safety rating")===0){

                                                    $safety = substr($line,17);

                                                }elseif(strpos($line,"-- Description")===0){

                                                    $description = substr($line,15);
                                                }

                                                if($safety && $description){
                                                    break;
                                                }
                                            }
                                            fclose($file);
                                        }else{
                                            print "<p style='font-weight:bold'>Cannot read the upgrade script '".$filename."'."
                                            .CONTACT_HEURIST_TEAM_PLEASE."</p>";
                                            $is_allfind = false;
                                            break;
                                        }

                                        $scripts_info  = $scripts_info."<tr><td width='100'>".
                                        $src_maj.".".$src_min.".0 to ".$src_maj.".".($src_min+1).".0</td><td>".$safety
                                        ." <i>".$description."</i></td></tr>";

                                        $src_min++;
                                    }else{
                                        print "<p style='font-weight:bold'>Cannot find the upgrade script '".$filename
                                        ."'.".CONTACT_HEURIST_TEAM_PLEASE."</p>";
                                        $is_allfind = false;
                                        break;
                                    }
                                }
                                //special case
                                if($trg_min==3 && $trg_sub==14){

$description = 'Modify tables:  defRecStructure(rst_SemanticReferenceURL,rst_TermsAsButtons,rst_PointerMode,rst_NonOwnerVisibility),   recUploadedFiles(ulf_PreferredSource), defTerms (trm_OrderInBranch), recDetails(dtl_HideFromPublic),sysIdentification(sys_NakalaKey), sysUGrps(usr_ExternalAuthentication)   Add tables:sysWorkflowRules,defTranslations,recDetailsDateIndex';

                                    $scripts_info  = $scripts_info
                                        .'<tr><td width="130">1.3.0 to 1.3.14</td><td> SAFE '
                                        ." <i>".$description."</i></td></tr>";
                                }

                                if($is_allfind)    {
                                ?>

                                <p>Upgrades marked SAFE are unlikely to harm your database but will make it incompatible with older versions of the code.<br>
                                    Upgrades marked DANGEROUS involve major changes to your database structure and could cause data loss.<br>
                                    For these upgrades we strongly recommend cloning your database first with an older version of the software<br>
                                    or asking your system adminstrator to backup the MySQL database and database file directory.<br>
                                    <br>
                                    If you need a partial upgrade eg. to an intermediate version, you will need to run an older version of the software -
                                    please consult Heurist support.
                                </p>
                                <p>
                                    Please determine whether you need backward compatibility and/or clone your database with an older version before proceeding.
                                    <br>
                                    Specific changes in each version upgrade are listed below:
                                </p>

                                <table role="presentation">
                                    <?php echo $scripts_info;?>
                                </table>
                                <br>
                                <p>
                                    <form action="<?php echo HEURIST_BASE_URL?>admin/setup/dbupgrade/upgradeDatabase.php">
                                        <input type="hidden" name="db" value="<?=HEURIST_DBNAME?>">
                                        <input type="hidden" name="mode" value="action">
                                        <button value="submit">Upgrade database</button>
                                    </form>
                                </p>
                                <?php
                                }
                            }

                        }else{ //not admin
                        ?>
                <div class="ui-state-error" style="width:90%;margin:auto;margin-top:10px;padding:10px;">
                    <span class="ui-icon ui-icon-alert" style="float: left; margin: .3em;"></span>
                    You must be logged in as database owner to upgrade the database structure
                    <button onclick="showLoginDialog(true)">Login</button>
                </div>
                        <?php
                        }
                    }
                ?>
<br><br>
<a href="<?=HEURIST_BASE_URL?>">Select different database</a>
            </div>
        </div>
    </body>
</html>

<?php
    function executeScript($filename){

        global $system;

        if(db_script(HEURIST_DBNAME_FULL, $filename)){
            return true;
        }else{
?>
                <div class="ui-state-error" style="width:90%;margin:auto;margin-top:10px;padding:10px;">
                    <span class="ui-icon ui-icon-alert" style="float: left; margin: .3em;"></span>
                    Error: Unable to execute <?php echo $filename;?> for database <?php echo HEURIST_DBNAME; ?><br>
                    Please check whether this file is valid. <?php echo CONTACT_HEURIST_TEAM_PLEASE;?> if needed<br>
                </div>
<?php
                if(!$system->is_admin()){
                ?>
        <div class="ui-state-error" style="width:90%;margin:auto;margin-top:10px;padding:10px;">
            <span class="ui-icon ui-icon-alert" style="float: left; margin: .3em;"></span>
            You must be logged in as database owner to upgrade the database structure
            <button onclick="doLogin(true)">Login</button>
        </div>
                <?php
                }


            return false;
        }
    }
?>