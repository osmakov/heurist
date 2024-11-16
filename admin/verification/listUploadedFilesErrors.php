<?php

/**
* listUploadedFilesErrors.php: Lists orphaned and missed files, broken paths
* for specific database
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

define('OWNER_REQUIRED',1);
define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
require_once dirname(__FILE__).'/../../hserv/records/search/recordFile.php';
require_once dirname(__FILE__).'/../../import/fieldhelper/harvestLib.php';

$mysqli = $system->getMysqli();

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Missing and orphaned files</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">

<?php
        includeJQuery();
?>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>

        <!-- CSS -->
        <?php include_once dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php';?>

        <style type="text/css">
            h3, h3 span {
                display: inline-block;
                padding:0 0 10px 0;
            }
            table tr td {
                line-height:2em;
            }
            .msgline{
                line-height: 3ex;
            }
            A:link, A:visited {color: #6A7C99;}
        </style>
        <script>
            $(document).ready(function() {
                $('button').button();
            });
        </script>

    </head>


    <body class="popup" style="overflow:auto">

        <div id="in_porgress" class="coverall-div" style="display:none;"><h2>Repairing....</h2></div>

        <div class="banner">
            <h2>Disk usage and quota</h2>
        </div>
<?php
        $quota = $system->settings->getDiskQuota();//takes value from disk_quota_allowances.txt
        $quota_not_defined = (!isPositiveInt($quota));
        if($quota_not_defined){
            $quota = 1073741824; //1GB
        }
        $usage = filestoreGetUsageByScan($system);

        $quota /= 1048576;
        $quota = round((float)$quota, 2);

        $usage /= 1048576;
        $usage = round((float)$usage, 2);

        $dirs = filestoreGetUsageByFolders($system);

        print '<p>Disk quota: '.$quota.' MB</p>';
        print 'Disk usage by folder<br>'.TABLE_S.'<tr><th>folder</th><th>MB</th></tr>';

        foreach ($dirs as $dir=>$size){
            if($size>0){
                $size /= 1048576;
                $size = round((float)$size, 2);
                print TR_S.htmlspecialchars($dir).TD.$size.TR_E;
            }
        }//for
        print TABLE_E;
        print 'Total usage: <b>'.$usage.' MB</b><br><hr>';
?>
        <div class="banner">
            <h2>Check for missing and orphaned files and incorrect paths</h2>
        </div>

        <div>
            These checks look for errors in uploaded file records.
            <br><br><hr><br><br>
            <div id="linkbar"></div>
        </div>

        <div id="page-inner" style="top:72px;">

            <?php

    $files_duplicates = array();
    $files_duplicates_all_ids = array();//not used

    $files_orphaned = array();
    $files_unused_local = array();
    $files_unused_remote = array();


    $files_notfound = array();//missed files
    $files_path_to_correct = array();
    $external_count = 0;
    $local_count = 0;

    $autoRepair = true;

    if($autoRepair){

    //search for duplicates
    //local
    $query2 = 'SELECT ulf_FilePath, ulf_FileName, count(*) as cnt FROM recUploadedFiles '
                .'where ulf_FileName is not null GROUP BY ulf_FilePath, ulf_FileName HAVING cnt>1';//ulf_ID<1000 AND
    $res2 = $mysqli->query($query2);

    if ($res2 && $res2->num_rows > 0) {

            $fix_dupes = 0;
            //find id with duplicated path+filename
            while ($res = $res2->fetch_assoc()) {

                $query3 = 'SELECT ulf_ID FROM recUploadedFiles '
                    .'where ulf_FilePath'.(@$res['ulf_FilePath']!=null
                            ?('="'.$mysqli->real_escape_string($res['ulf_FilePath']).'"')
                            :' IS NULL ')
                    .' and ulf_FileName="'.$mysqli->real_escape_string($res['ulf_FileName']).'" ORDER BY ulf_ID DESC';
                $res3 = $mysqli->query($query3);
                $dups_ids = array();

                while ($res4 = $res3->fetch_row()) {
                    array_push($files_duplicates_all_ids, $res4[0]);
                    $dups_ids[] = intval($res4[0]);

                }
                $res3->close();

                if(count($dups_ids)<2) {continue;}

                if(@$res['ulf_FilePath']==null){
                    $res_fullpath = $res['ulf_FileName'];
                }else{
                    $res_fullpath = resolveFilePath( $res['ulf_FilePath'].$res['ulf_FileName'] );//see recordFile.php
                }
                $files_duplicates[$res_fullpath] = $dups_ids;

                //FIX duplicates at once
                $max_ulf_id = array_shift($dups_ids);

                filestoreReplaceDuplicatesInDetails($mysqli, $max_ulf_id, $dups_ids);

                $fix_dupes = $fix_dupes + count($dups_ids);
            }

        if($fix_dupes){
            print '<div>Autorepair: '.$fix_dupes.' multiple registrations removed for '.count($files_duplicates).' files. Pointed all details referencing them to the one retained</div>';
        }

        $res2->close();
    }

    //search for duplicated remotes
    $query2 = 'SELECT ulf_ExternalFileReference, count(*) as cnt FROM recUploadedFiles '
                .'where ulf_ExternalFileReference is not null GROUP BY ulf_ExternalFileReference HAVING cnt>1';
    $res2 = $mysqli->query($query2);

    if ($res2 && $res2->num_rows > 0) {

            $fix_dupes = 0;
            $fix_url = 0;
            //find id with duplicated path+filename
            while ($roww = $res2->fetch_row()) {
                $external_url = $roww[0];
                $query3 = 'SELECT ulf_ID FROM recUploadedFiles where ulf_ExternalFileReference=?';
                $res3 = mysql__select_param_query($mysqli, $query3, array('s', $external_url));

                $dups_ids = array();

                while ($res4 = $res3->fetch_row()) {
                    array_push($files_duplicates_all_ids, $res4[0]);
                    $dups_ids[] = intval($res4[0]);

                }
                $res3->close();

                if(count($dups_ids)<2) {continue;}

                $files_duplicates[$external_url] = $dups_ids;

                //FIX duplicates at once
                $max_ulf_id = array_shift($dups_ids);

                filestoreReplaceDuplicatesInDetails($mysqli, $max_ulf_id, $dups_ids);

                $fix_dupes = $fix_dupes + count($dups_ids);
                $fix_url++;
            }

            if($fix_dupes){
                print '<div>System info: cleared '.$fix_dupes.' duplicated registration for '.$fix_url.' URL</div>';
            }
            $res2->close();
    }



    //search for duplicated files (identical files in different folders)
    $query2 = 'SELECT ulf_OrigFileName, count(*) as cnt FROM recUploadedFiles '
.' where ulf_OrigFileName is not null and ulf_OrigFileName<>"_remote" and '
.'ulf_OrigFileName NOT LIKE "'.ULF_IIIF.'%" and ulf_OrigFileName NOT LIKE "'.ULF_TILED_IMAGE.'%" '
.'GROUP BY ulf_OrigFileName HAVING cnt>1';
    $res2 = $mysqli->query($query2);

    if ($res2 && $res2->num_rows > 0) {


            $cnt_dupes = 0;
            $cnt_unique = 0;
            //find id with duplicated path+filename
            while ($res = $res2->fetch_row()) {
                $query3 = 'SELECT ulf_ID, ulf_FilePath, ulf_FileName  FROM recUploadedFiles '
                    .' where ulf_OrigFileName=?'
                    .' ORDER BY ulf_ID DESC';

                $res3 = mysql__select_param_query($mysqli, $query3, array('s', $res[0]));

                if(!$res3){
                    //$this->system->addError(HEURIST_DB_ERROR, 'Unable to query recUploadedFiles for file '

                    continue;
                }

                $dups_files = array();//id=>path,size,md,array(dup_ids)

                while ($res4 = $res3->fetch_assoc()) {

                    //compare files
                    if(@$res4['ulf_FilePath']==null){
                        $res_fullpath = $res4['ulf_FileName'];
                    }else{
                        $res_fullpath = resolveFilePath( $res4['ulf_FilePath'].$res4['ulf_FileName'] );//see recordFile.php
                    }


                    $f_size = filesize($res_fullpath);
                    $f_md5 = md5_file($res_fullpath);
                    $is_unique = true;
                    foreach ($dups_files as $id=>$file_a){

                        if ($file_a['size'] == $f_size && $file_a['md5'] == $f_md5){
                            //files are the same
                            $is_unique = false;
                            $dups_files[$id]['dupes'][ $res4['ulf_ID'] ] = $res_fullpath;

                            break;
                        }
                    }
                    if($is_unique){
                        $dups_files[$res4['ulf_ID']] = array('path'=>$res_fullpath,
                                                    'md5'=>$f_md5,
                                                    'size'=>$f_size,
                                                    'dupes'=>array());
                    }
                }//while
                $res3->close();

                //FIX duplicates at once
                foreach ($dups_files as $ulf_ID=>$file_a){
                    if(!isEmptyArray($file_a['dupes'])){

                        $dup_ids = array_keys($file_a['dupes']);

                        filestoreReplaceDuplicatesInDetails($mysqli, $ulf_ID, $dup_ids);

                        $cnt_dupes = $cnt_dupes + count($dup_ids);
                        $cnt_unique++;

                        /* report
                        foreach($file_a['dupes'] as $id=>$path){
                            print DIV_S.$id.' '.$path.DIV_E;
                        }
                        print '<div style="padding:0 0 10px 60px">removed in favour of '.$ulf_ID.' '.$file_a['path'].DIV_E;
                        */
                    }
                }//foreach

            }//while

        if($cnt_unique>0){
            print '<div>Autorepair: '.$cnt_dupes.' registration for identical files are removed in favour of '
                        .$cnt_unique.' unique ones. Pointed all details referencing them to the one retained</div>';
        }

        $res2->close();
    }

    }//autoRepair


    $query1 = 'SELECT ulf_ID, ulf_ExternalFileReference, ulf_FilePath, ulf_FileName from recUploadedFiles';// where ulf_ID=5188
    $res1 = $mysqli->query($query1);
    if (!$res1 || $res1->num_rows == 0) {
        die ("<p><b>This database does not have uploaded files</b></p>");
    }
    else {
        print "<p><br>Number of files processed: ".$res1->num_rows."<br></p>";
    }

    //
    //
    //
    while ( $res = $res1->fetch_assoc() ) {

            //verify path
            $res['db_fullpath'] = null;

            if(@$res['ulf_FilePath'] || @$res['ulf_FileName']){

                $res['db_fullpath'] = $res['ulf_FilePath'].@$res['ulf_FileName'];
                $res['res_fullpath'] = resolveFilePath(@$res['db_fullpath']);
            }

            //missed link from recDetails - orphaned files - file is not used in heurist records
            $query2 = "SELECT dtl_RecID from recDetails where dtl_UploadedFileID=".intval($res['ulf_ID']);
            $res2 = $mysqli->query($query2);
            $currentRecID = null;
            if ($res2) {
                if($res2->num_rows == 0) {

                    if(@$res['ulf_ExternalFileReference']!=null){
                        $files_unused_remote[$res['ulf_ID']] = array('ulf_ID'=>$res['ulf_ID'],
                                            'ulf_ExternalFileReference'=>@$res['ulf_ExternalFileReference']);
                    }else{
                        $files_unused_local[$res['ulf_ID']] = array('ulf_ID'=>$res['ulf_ID'],
                                            'res_fullpath'=>@$res['res_fullpath'],
                                            'isfound'=>file_exists($res['res_fullpath'])?1:0);
                    }

                    $files_orphaned[$res['ulf_ID']] = array('ulf_ID'=>$res['ulf_ID'],
                                            'res_fullpath'=>@$res['res_fullpath'],
                                            'isfound'=>file_exists(@$res['res_fullpath'])?1:0,
                                            'ulf_ExternalFileReference'=>@$res['ulf_ExternalFileReference']);
                }else{
                    $row = $res2->fetch_row();
                    $currentRecID = $row[0];
                }
                $res2->close();
            }

            if( $res['db_fullpath']!=null && @$res['res_fullpath'] ){

                $is_local = (strpos($res['db_fullpath'],'http://')===false && strpos($res['db_fullpath'],'https://')===false);

                if($currentRecID==null){
                    continue;
                }elseif ( $is_local && !file_exists($res['res_fullpath']) ){
                    //file not found
                    $files_notfound[$res['ulf_ID']] = array(
                                    'ulf_ID'=>$res['ulf_ID'],
                                    'db_fullpath'=>$res['db_fullpath'], //failed path
                                    'rec_ID'=>$currentRecID,
                                    'is_remote'=>!@$res['ulf_ExternalFileReference'] );

                }elseif($is_local) {

                    chdir(HEURIST_FILESTORE_DIR);// relatively db root

                    $fpath = realpath($res['db_fullpath']);

                    if(!$fpath || !file_exists($fpath)){
                        chdir(HEURIST_FILES_DIR);// relatively file_uploads
                        $fpath = realpath($res['db_fullpath']);
                    }

                    if($fpath!==false){
                        //realpath gives real path on remote file server
                        if(strpos($fpath, '/srv/HEURIST_FILESTORE/')===0){
                            $fpath = str_replace('/srv/HEURIST_FILESTORE/', HEURIST_FILESTORE_ROOT, $fpath);
                        }elseif(strpos($fpath, '/misc/heur-filestore/')===0){
                            $fpath = str_replace('/misc/heur-filestore/', HEURIST_FILESTORE_ROOT, $fpath);
                        }


                        //check that the relative path is correct
                        $path_parts = pathinfo($fpath);
                        if(!@$path_parts['dirname']){
                           continue;
                        }else{
                            $dirname = $path_parts['dirname'].'/';
                            $filename = $path_parts['basename'];
                        }

                        $dirname = str_replace("\0", '', $dirname);
                        $dirname = str_replace('\\', '/', $dirname);
                    }else{
                        $dirname = 'xxx';
                    }

                    if(strpos($dirname, HEURIST_FILESTORE_DIR)===0){


                        $relative_path = getRelativePath(HEURIST_FILESTORE_DIR, $dirname);//db root folder

                        if($relative_path!=@$res['ulf_FilePath']){

                            $files_path_to_correct[$res['ulf_ID']] = array('ulf_ID'=>$res['ulf_ID'],
                                        'db_fullpath'=>$res['db_fullpath'],
                                        'res_fullpath'=>$fpath,
                                        'ulf_FilePath'=>@$res['ulf_FilePath'],
                                        'res_relative'=>$relative_path,
                                        'filename'=>$filename
                                        );
                        }
                    }
                }else{

                            $files_path_to_correct[$res['ulf_ID']] = array('ulf_ID'=>$res['ulf_ID'],
                                        'clear_remote'=>$res['db_fullpath']
                                        );

                }
            }

    }//while

    //AUTO FIX PATH at once
    foreach ($files_path_to_correct as $row){

        $ulf_ID = $row['ulf_ID'];
        if(@$row['clear_remote']){ //remove url from ulf_FilePath
                $query = 'update recUploadedFiles set ulf_ExternalFileReference=?'
                                .', ulf_FilePath=NULL, ulf_FileName=NULL where ulf_ID = '.intval($ulf_ID);

                mysql__exec_param_query($mysqli, $query, array('s', $row['clear_remote']));

        }else{
                $query = 'update recUploadedFiles set ulf_FilePath=?, ulf_FileName=? where ulf_ID = '.intval($ulf_ID);

                mysql__exec_param_query($mysqli, $query, array('ss', $row['res_relative'], $row['filename']));

        }
    }
    if(!isEmptyArray($files_path_to_correct)){
            print '<div>Autorepair: corrected '.count($files_path_to_correct).' paths</div>';
            $files_path_to_correct = array();
    }



    //check for non-registered files in mediafolders
    // $reg_info - global array to be filled in doHarvest
    $reg_info = array('reg'=>array(), 'nonreg'=>array());
    $dirs_and_exts = getMediaFolders( $mysqli );
    doHarvest($system, $dirs_and_exts, false, 1);

    $files_notreg = $reg_info['nonreg'];

    //count($files_duplicates)+
    $is_found = (count($files_unused_remote)+count($files_unused_local)+count($files_notfound)+count($files_notreg) > 0);

            if ($is_found) {
                ?>
                <script>

                    //NOT USED
                    function repairBrokenPaths(){

                        function _callbackRepair(context){

                            $('#in_porgress').hide();

                            if(window.hWin.HEURIST4.util.isnull(context) || window.hWin.HEURIST4.util.isnull(context['result'])){
                                window.hWin.HEURIST4.msg.showMsgErr(null);
                            }else{

                                var url = window.hWin.HAPI4.baseURL + 'admin/verification/listDatabaseErrorsInit.php?type=files&db='+window.hWin.HAPI4.database;

                                if(window.parent.parent.addDataMenu)
                                    window.parent.parent.addDataMenu.doAction('menulink-verify-files');
                            }
                        }

                        var dt2 = {"orphaned":[
                            <?php

                            $pref = '';
                            foreach ($files_orphaned as $row) { //to remove
                                print $pref.'['.intval($row['ulf_ID']).','.intval($row['isfound']).']';
                                $pref = ',';
                            }

                            print '],"notfound":[';
                            $pref = '';
                            //to remove from recDetails and recUplodedFiles
                            foreach ($files_notfound as $row) {
                                print $pref.intval($row['ulf_ID']);
                                $pref = ',';
                            }
                            print '],"fixpath":[';
                            $pref = '';

                            foreach ($files_path_to_correct as $row) {
                                print $pref.intval($row['ulf_ID']);
                                $pref = ',';
                            }
                        ?>]};

                        var dt = {orphaned:[],fixpath:[],notfound:[]};
                        if(document.getElementById('do_orphaned')
                                && document.getElementById('do_orphaned').checked){
                            dt['orphaned'] = dt2['orphaned'];
                        }
                        if(document.getElementById('do_fixpath')
                                && document.getElementById('do_fixpath').checked){
                            dt['fixpath'] = dt2['fixpath'];
                        }
                        var i;
                        for (i=0;i<dt2['notfound'].length;i++){
                            if(document.getElementById('fnf'+dt2['notfound'][i]).checked)
                                    dt.notfound.push(dt2['notfound'][i]);
                        }

                        var str = JSON.stringify(dt);

                        var baseurl = window.hWin.HAPI4.baseURL + "admin/verification/repairUploadedFiles.php";
                        var callback = _callbackRepair;
                        var params = 'db='+window.hWin.HAPI4.database+'&data=' + encodeURIComponent(str);

                        $('#in_porgress').show();
                        window.hWin.HEURIST4.ajax.getJsonData(baseurl, callback, params);

                        document.getElementById('page-inner').style.display = 'none';
                    }

                    //
                    // NOT USED
                    //
/*
                    function removeUnlinkedFiles(){

                        function _callback(context){
                            document.getElementById('page-inner').style.display = 'block';

                            if(window.hWin.HEURIST4.util.isnull(context) || context['status']!='ok'){
                                window.hWin.HEURIST4.msg.showMsgErr(context || context['message']);
                            }else{

                                var ft = $('input.file_to_clear:checked');
                                var i, j, cnt=0, fdeleted = context['data'];

                                if($('input.file_to_clear').length==fdeleted.length){
                                    cnt = fdeleted.length;
                                    //all removed
                                    $('#nonreg').remove();
                                }else{

                                    for (i=0; i<fdeleted.length; i++){
                                        for (j=0; j<ft.length; j++){
                                            if($(ft[j]).parent().text()==fdeleted[i]){
                                                //remove div
                                                $(ft[j]).parents('.msgline').remove();
                                                cnt++;
                                                break;
                                            }
                                        }
                                    }
                                }
                                window.hWin.HEURIST4.msg.showMsg(cnt+' non-registered/unlinked files have been removed from media folders');
                            }
                        }


                        var res = [];
                        $.each($('input.file_to_clear:checked'), function(idx, item){
                            var filename = $(item).parent().text();
                            res.push(filename);
                        });

                        if(res.length==0){
                            alert('Mark at least one file');
                            return;
                        }

                        var dt = {"unlinked":res};
                        var str = JSON.stringify(dt);


                        var baseurl = window.hWin.HAPI4.baseURL + "admin/verification/repairUploadedFiles.php";
                        var callback = _callback;
                        var params = "db="+window.hWin.HAPI4.database+"&data=" + encodeURIComponent(str);
                        window.hWin.HEURIST4.ajax.getJsonData(baseurl, callback, params);

                        document.getElementById('page-inner').style.display = 'none';

                    }
*/
                    //
                    //
                    //
                    function doRepairAction(action_name){

                        function _callback(context){
                            document.getElementById('page-inner').style.display = 'block';//restore visibility

                            if(window.hWin.HEURIST4.util.isnull(context) || context['status']!='ok'){
                                window.hWin.HEURIST4.msg.showMsgErr(context || context['message']);
                            }else{

                                var ft = $('input.'+action_name+':checked');
                                var i, j, cnt=0, fdeleted = context['data'];

                                if($('input.'+action_name).length==fdeleted.length){
                                    cnt = fdeleted.length;
                                    //all removed - remove entire div
                                    $('#'+action_name).remove();
                                }else{

                                    for (i=0; i<fdeleted.length; i++){
                                        for (j=0; j<ft.length; j++){
                                            if($(ft[j]).attr('data-id')==fdeleted[i]){
                                                //remove div
                                                $(ft[j]).parents('.msgline').remove();
                                                cnt++;
                                                break;
                                            }
                                        }
                                    }
                                }
                                window.hWin.HEURIST4.msg.showMsg(cnt+' entries have been fixed');
                            }
                        }


                        var res = [];
                        $.each($('input.'+action_name+':checked'), function(idx, item){
                            var ulf_id = $(item).attr('data-id');
                            res.push(ulf_id);
                        });

                        if(res.length==0){
                            alert('Mark at least one file');
                            return;
                        }else if(res.length>2000){
                            alert('You can only process 2000 files at a time due to server side limitations. '+
                            'Please repeat the operation for this number of files as many times as needed.');
                            return;
                        }

                        var dt = {}; dt[action_name] = res;
                        var str = JSON.stringify(dt);


                        var baseurl = window.hWin.HAPI4.baseURL + "admin/verification/repairUploadedFiles.php";
                        var callback = _callback;

                        var request = {db:window.hWin.HAPI4.database, data:str};
                        window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, callback);


                        document.getElementById('page-inner').style.display = 'none';//hide all

                    }//doRepairAction

                    function doIndexing(selected_only){

                        let $selected_files = $('.files_notreg:is(:checked)');

                        if((!selected_only && $('.files_notreg').length == 0)
                        || (selected_only && $selected_files.length == 0)){

                            return;
                        }

                        let request = {
                            'a': 'batch',
                            'entity': that.options.entity.entityName,
                            'request_id': window.hWin.HEURIST4.util.random(),
                            'bulk_reg_filestore': 1
                        };

                        if(selected_only){
                            // Add checked files
                            let files = [];
                            $selected_files.each((idx, input) => {
                                files.push(input.parentNode.textContent);
                            });
                            request['files'] = JSON.stringify(files);
                        }

                        window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));

                        window.hWin.HAPI4.EntityMgr.doRequest(request, function(response){

                            window.hWin.HEURIST4.msg.sendCoverallToBack();

                            if(response.status != window.hWin.ResponseStatus.OK){
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                                return;
                            }

                            let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(response.data, {'OK': function(){
                                $dlg.dialog('close');

                                selected_only ? $selected_files.closest('.msgline').remove() : $('#files_notreg, a[href="#file_notreg"]').remove();

                            }}, {title: 'Refresh indexes results', 'OK': window.HR('OK')}, {default_palette_class: 'ui-heurist-admin', dialogId: 'refresh-file-indexes'});
                        });
                    }
                <?php
                    $smsg='';

                if($is_found){

                    $smsg = 'Go to: ';

                if(!isEmptyArray($files_unused_local)){
                    $smsg = $smsg.'<a href="#unused_local" style="white-space: nowrap;padding-right:20px">Unused local files</a>';
                }
                if(!isEmptyArray($files_unused_remote)){
                    $smsg = $smsg.'<a href="#unused_remote" style="white-space: nowrap;padding-right:20px">Unused remote files</a>';
                }
                if(!isEmptyArray($files_notfound)){
                    $smsg = $smsg.'<a href="#files_notfound" style="white-space: nowrap;padding-right:20px">Files not found</a>';
                }
                if(!isEmptyArray($files_notreg)){
                    $smsg = $smsg.'<a href="#files_notreg" style="white-space: nowrap;padding-right:20px">Non-registered files</a>';
                }
                }

                print "document.getElementById('linkbar').innerHTML='".$smsg."'";
                ?>

                </script>

                <?php
                if(!isEmptyArray($files_unused_local)){
                ?>
                <div id="unused_file_local" style="padding-top:20px">
                    <a href="#unused_local"></a>
                    <h3>Unused local files</h3>
                    <div style="padding-bottom:10px;font-weight:bold"><?php echo count($files_unused_local);?> entries</div>
                    <div>These files are not referenced by a File field in any record in the database.
                    
                    <br>Unfortunately these files cannot be removed because they may have
                    <br>been referenced within text fields, which we do not check at present.
                    <br>If you need this function, please let us know.
                    </div>
                    <br>
                    <label><input type=checkbox
                        onchange="{$('.unused_file_local').prop('checked', $(event.target).is(':checked'));}">&nbsp;Select/unselect all</label>
                    <br>
                    <br>
                <?php
                foreach ($files_unused_local as $row) {
                    outCheckbox('unused_file_local', $row['ulf_ID'], htmlspecialchars($row['res_fullpath']).( $row['isfound']?'':' ( file not found )' ));
                }//for

                /*  24/12/23 - removed by Ian b/c too dangerous, see explanation below
                if(is_array($files_unused_local) && count($files_unused_local)>10){
                    print '<div><br><button onclick="doRepairAction(\'unused_file_local\')">Remove selected unused local files</button></div>';
                }
                */
                print '
                    <br>Unfortunately these files cannot be removed because they may have
                    <br>been referenced within text fields, which we do not check at present.
                    <br>If you need this function, please let us know.
                    <br><br><hr></div>';
                }
                //------------------------------------------
                if(!isEmptyArray($files_unused_remote)){
                ?>
                <div id="unused_file_remote" style="padding-top:20px">
                    <a href="#unused_remote"></a>
                    <h3>Unused remote files</h3>
                    <div style="padding-bottom:10px;font-weight:bold"><?php echo count($files_unused_remote);?> entries</div>
                    <div>These URLs are not referenced by any record in the database.

                    <br>Unfortunately these references cannot be removed because they may have
                    <br>been referenced within text fields, which we do not check at present.
                    <br>If you need this function, please let us know.
                </div>

                    <br>
                    <label><input type=checkbox
                        onchange="{$('.unused_file_remote').prop('checked', $(event.target).is(':checked'));}">&nbsp;Select/unselect all</label>
                    <br>
                    <br>
                <?php
                foreach ($files_unused_remote as $row) {
                    outCheckbox('unused_file_remote', $row['ulf_ID'], filter_var($row['ulf_ExternalFileReference'],FILTER_SANITIZE_URL));
                }//for


                print '
                    <br>Unfortunately these references cannot be removed because they may have
                    <br>been referenced within text fields, which we do not check at present.
                    <br>If you need this function, please let us know.
                    <br><br><hr></div>';
                }//if

                //------------------------------------------
                if(!isEmptyArray($files_notfound)){
                ?>
                <div id="files_notfound" style="padding-top:20px">
                    <a href="#files_notfound"></a>
                    <h3>Missing registered files </h3>
                    <div style="padding-bottom:10px;font-weight:bold"><?php echo count($files_notfound);?> entries</div>
                    <div>Path specified in database is wrong and file cannot be found.
                    Select all or some entries and click the button
                    <button onclick="doRepairAction('files_notfound')">Remove entries for missing files</button>
                    to remove registrations from the database.</div>

                    <br>
                    <label><input type=checkbox
                        onchange="{$('.files_notfound').prop('checked', $(event.target).is(':checked'));}">&nbsp;Select/unselect all</label>
                    <br>
                    <br>
                <?php
                foreach ($files_notfound as $row) {
                    outCheckbox('files_notfound', $row['ulf_ID'], htmlspecialchars($row['db_fullpath']));
                }//for
                if(count($files_notfound)>10){
                    print '<div><br><button onclick="doRepairAction(\'files_notfound\')">Remove entries for missing files</button></div>';
                }
                print '<br><br><hr></div>';
                }//if

                //------------------------------------------
                if(!isEmptyArray($files_notreg)){
                ?>
                <div id="files_notreg" style="padding-top:20px">
                    <a href="#files_notreg"></a>
                    <h3>Non-registered files</h3>
                    <div style="padding-bottom:10px;font-weight:bold"><?php echo count($files_notreg);?> entries</div>
                    <div>
                    Use Populate > Create media records to add these to the database as Digital Media records. Or
                    select all or some entries and click the button
                    <button onclick="doRepairAction('files_notreg')">Remove non-registered files</button>
                    to delete files from system.</div>

                    <br>
                    <label><input type=checkbox
                        onchange="{$('.files_notreg').prop('checked', $(event.target).is(':checked'));}">&nbsp;Select/unselect all</label>
                    <button onclick="doIndexing(false)" style="margin: 0px 10px;">Register all files</button>
                    <button onclick="doIndexing(true)">Register selected files</button>
                    <br>
                    <br>
                <?php
                foreach ($files_notreg as $row) { //filenames
                    print '<div class="msgline"><label><input type=checkbox class="files_notreg" data-id="'.htmlspecialchars($row).'">&nbsp;'
                            .htmlspecialchars($row).'</label></div>';
                }//for
                if(count($files_notreg)>10){
                    print '<div><br><button onclick="doRepairAction(\'files_notreg\')">Remove non-registered files</button></div>';
                }
                print '<br><br><hr></div>';
                }//if

                //------------------------------------------
            }else{
                print "<br><br><p><h3>All uploaded file entries are valid</h3></p>";
            }

function outCheckbox($ele_class, $ulf_id, $text){

    $ulf_id = intval($ulf_id);
    print <<<EXP
<div class="msgline"><label><input type=checkbox class="$ele_class" data-id="$ulf_id">&nbsp;<b>$ulf_id</b> $text</label></div>
EXP;
}
            ?>


        </div>
<script>
/*
    var parent = $(window.parent.document);
    parent.find('#verification_output').css({width:'100%',height:'100%'}).show();
    parent.find('#in_porgress').hide();
*/
</script>
    </body>
</html>
