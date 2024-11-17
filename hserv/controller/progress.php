<?php
    require_once dirname(__FILE__).'/../../autoload.php';

    $res = '';

    if(@$_REQUEST['db'] && @$_REQUEST['session']){

        $system = new hserv\System();
        $dbname = @$_REQUEST['db'];
        $error = mysql__check_dbname($dbname);
        if($error==null){

            if(!defined('HEURIST_SCRATCH_DIR')){
                $warn = '';
                $dir = $system->getSysDir(DIR_SCRATCH, basename($dbname));
                if(!file_exists($dir)){
                    $warn = folderCreate2($dir, '', false);
                }
                if($warn==''){
                    define('HEURIST_SCRATCH_DIR', $dir);
                }
            }
            $mysqli = null;

            //keep progress value in HEURIST_SCRATCH_DIR
            if(@$_REQUEST['terminate']==1){
                $res = 'terminate';
                mysql__update_progress($mysqli, intval($_REQUEST['session']), false, $res);
            }else{
                //retrieve current status
                $res = mysql__update_progress($mysqli, intval($_REQUEST['session']), false, null);
            }
        }
        if($res==null) {$res = '';}
        print $res;
    }else{
        print 'terminate';
    }
?>
