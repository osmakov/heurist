<?php
require_once dirname(__FILE__).'/../../../hserv/utilities/utils_db_load_script.php';

function doUpgradeDatabase($system, $dbname, $trg_maj, $trg_min, $verbose=false)
{

    $dir = HEURIST_DIR.'admin/setup/dbupgrade/';

    $mysqli = $system->getMysqli();

    //select database
    if($dbname){
        mysql__usedatabase($mysqli, $dbname);
    }

    $row = mysql__select_row_assoc($mysqli, 'select sys_dbVersion, sys_dbSubVersion from sysIdentification WHERE 1');

    $src_maj = intval( $row['sys_dbVersion'] );
    $src_min = intval( $row['sys_dbSubVersion'] );

    $upgrade_success = true;

    if($src_min>=$trg_min){
        return true;
    }

    $keep_autocommit = mysql__begin_transaction($mysqli);

    while ($src_min<$trg_min) {
        $filename = "DBUpgrade_$src_maj.$src_min.0_to_$trg_maj.".($src_min+1).'.0';

        if($trg_maj==1 && $src_min==2){
            $filename = $filename.'.php';
        }else{
            $filename = $filename.'.sql';
        }

        if( file_exists($dir.$filename) ){

            if($trg_maj==1 && $src_min==2){
                include_once $filename;
                $rep = updateDatabseTo_v3($system, $dbname);//PHP
            }elseif(!db_script($dbname, $dir.$filename)){ //SQL
                $system->addError(HEURIST_DB_ERROR, 'Error: Unable to execute '.$filename.' for database '.$dbname
                    .'Please check whether this file is valid');
                $rep = false;
            }else{
                $rep = true;
            }

            if($rep){
                $src_min++;

                if($verbose){
                    if(is_array($rep)){
                        array_walk($rep, function($msg){ echo '<p>'.htmlspecialchars($msg).'</p>';} );
                    }
                    print "<p>Upgraded to $src_maj.$src_min.0</p>";
                }

            }else{
                $error = $system->getError();
                if($verbose && $error){
                    print errorDiv($error['message'].BR.@$error['sysmsg']);
                }

                $upgrade_success = false;
                break;
            }

        }else{
            $sMsg = "<p style='font-weight:bold'>Cannot find the database upgrade script '$filename'</p>";
            if($verbose){
                print $sMsg.CONTACT_HEURIST_TEAM_PLEASE;
            }else{
                $system->addError(HEURIST_SYSTEM_CONFIG, $sMsg);
            }
            $upgrade_success = false;
            break;
        }

    }//while


    mysql__end_transaction($mysqli, $upgrade_success, $keep_autocommit);

    return $upgrade_success;
}
?>
