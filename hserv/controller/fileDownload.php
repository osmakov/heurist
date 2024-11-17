<?php

/**
* fileDownload.php : Download (or proxy) files that are registered in Heurist database (recUploadedFiles)
* Usually it is callled via redirection from index.php (if there is parameter file, thumb or url)
*
* for entity images (rt icons, user, group images) see fileGet.php
*
* db
* thumb - obfuscated file id - returns existing thumbnail or resized image
* file - obfuscated file id - uses fileGetFullInfo to get path to file or URL
*
* mode
*   page - return
*   tag - returns html wrap iframe with embed player, video, audio or img tag
*   size - returns width and height (for images only!)
*   url - rerurns url for uploaded_tilestacks
* size - width and height for html tag
* embedplayer - for player
*
* Notes about thumbnails
*    for uploaded file - thumbnail is created in
*           hserv/utilities/UploadHandler.php create_scaled_image()
*           on time of uploading and after registration it is copied to our filethumbs folder
*    for remote file - thumbnail is created on first request fileDownload.php?thumb=  recordFile.php fileCreateThumbnail
*    if record has an rec_URL, the thumbnail is created with UImage::makeURLScreenshot
*
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

use hserv\utilities\USanitize;

require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../records/search/recordFile.php';

$req_params = USanitize::sanitizeInputArray();

$db = @$req_params['db'];

if(mysql__check_dbname($db)==null){

    $system = new hserv\System();//without connection
    $fileid = filter_var(@$req_params['thumb'], FILTER_SANITIZE_STRING);
    if($fileid!=null){

        if(preg_match('/^[a-z0-9]+$/', $fileid)){ //validatate obfuscation id

            $force_recreate = (@$req_params['refresh']==1);

            if($system->initPathConstants($db)){

                $thumbfile = HEURIST_THUMB_DIR.basename('ulf_'.$fileid.'.png');

                if(!$force_recreate && file_exists($thumbfile)){

                    if(defined('HEURIST_THUMB_URL')){
                        //rawurlencode - required for security reports only
                        redirectURL( HEURIST_THUMB_URL.basename('ulf_'.rawurlencode($fileid).'.png') );
                    }else{
                        downloadFile('image/png', $thumbfile);
                    }

                }else{
                    //recreate thumbnail and output it
                    $system->init($db);
                    fileCreateThumbnail( $system, $fileid, true );
                    $system->dbclose();
                }
            }
        }
    }
    elseif(@$req_params['file'] || @$req_params['ulf_ID']) { //ulf_ID is obfuscation id here

        $fileid = @$req_params['file']? $req_params['file'] :intval(@$req_params['ulf_ID']);
        $size = @$req_params['size'];

        $need_session = (@$req_params['mode']==null && @$req_params['metadata']==null);

        if( is_numeric($fileid)         
            || !preg_match('/^[a-z0-9]+$/', $fileid)  //Obfuscated id is allowed only
            || !$system->init($db, true, $need_session) //database not inited
            || !$system->initPathConstants($db) )  //folder not found
        {
            exit;
        }

        //find
        $listpaths = fileGetFullInfo($system, $fileid);
        if(!isEmptyArray($listpaths)){

            $fileinfo = $listpaths[0];//
            $filepath = USanitize::sanitizePath($fileinfo['fullPath']);//concat(ulf_FilePath,ulf_FileName as fullPath
            $external_url = $fileinfo['ulf_ExternalFileReference'];//ulf_ExternalFileReference
            $mimeType = $fileinfo['fxm_MimeType'];//fxm_MimeType
            $sourceType = $fileinfo['ulf_PreferredSource'];//not used
            $originalFileName = $fileinfo['ulf_OrigFileName'];
            $fileSize = $fileinfo['ulf_FileSizeKB'];
            $fileExt = $fileinfo['ulf_MimeExt'];
            $fileParams = $fileinfo['ulf_Parameters'];// external repository service id
            $all_can_view = empty($fileinfo['ulf_WhoCanView']) || $fileinfo['ulf_WhoCanView'] == 'viewable';
            if($fileParams!=null && !empty($fileParams)){
                $fileParams = json_decode($fileParams, true);
            }


            $params = null;

            if( @$req_params['mode']=='page')     //return full page with embed player
            {
                $mode_3d_viewer = detect3D_byExt($fileExt);

                if($mode_3d_viewer!=''){

                    redirectURL(HEURIST_BASE_URL.'hclient/widgets/viewers/'.$mode_3d_viewer.'Viewer.php?db='.$db.'&file='.$fileid);

                }else{
                    $url = HEURIST_BASE_URL.'?mode=tag&db='.basename($db).'&file='.$fileid.'&size='.$size;

                    ?>
                    <!DOCTYPE HTML>
                    <html lang="en" xmlns="http://www.w3.org/1999/xhtml">
                        <head>
                            <title>Heurist mediaplayer</title>
                            <base href="<?php echo HEURIST_BASE_URL;?>">
                            <meta name="robots" content="noindex,nofollow">
                        </head>
                        <body>
                           <?php
                             print file_get_contents($url);//execute this script to get html tag for player
                           ?>
                        </body>
                    </html>
                    <?php
                }

            }elseif( @$req_params['mode']=='tag'){

                //request may have special parameters for audio/video players
                if(@$req_params['fancybox']){
                    $params = array('fancybox'=>1);//returns player in wrapper
                }else{
                    $params = null;
                }

                print fileGetPlayerTag($system, $fileid, $mimeType, $params, $external_url);
            }
            else  //just download file from heurist server or redirect to original remote url
            {

                $filepath = resolveFilePath( $filepath );
                $filepath = isPathInHeuristUploadFolder($filepath);//snyk SSRF

                if( @$req_params['mode']=='metaonly'){ //get width and height for image file

                    fileGetMetadata($fileinfo);

                }elseif(@$req_params['metadata']){//download zip file: registered file and file with links to html and xml

                    downloadFileWithMetadata($system, $fileinfo, $req_params['metadata']);

                }elseif(is_string($filepath) && file_exists($filepath) && !is_dir($filepath)){

                    //fix issue if original name does not have ext
                    if(@$req_params['embedplayer']!=1){
                        $finfo = pathinfo($originalFileName);
                        $ext = @$finfo['extension'];
                        if($ext==null || $ext==''){
                            $finfo = pathinfo($filepath);//take from path
                            if(@$finfo['extension']){
                                $originalFileName = $originalFileName.'.'.@$finfo['extension'];
                            }elseif($fileExt){
                                if($fileExt=='jpe'){ $fileExt = 'jpg';}
                                $originalFileName = $originalFileName.'.'.$fileExt;
                            }
                        }
                    }

                    $is_download = (@$req_params['download']==1);

                    $is_image = (strpos($mimeType, DIR_IMAGE)===0);
                    $get_blurred_image = $is_image && !$all_can_view && !$system->hasAccess();

                    if(!$is_download && isset($allowWebAccessUploadedFiles) && $allowWebAccessUploadedFiles)
                    { //&& strpos($fileinfo['fullPath'],'file_uploads/')===0

                        //show in viewer directly
                        $direct_url = $system->getSysUrl().$fileinfo['fullPath'];

                        if($get_blurred_image){ //!$all_can_view

                            $blurred_url = getBlurredImage($system, $fileinfo);
                            $direct_url = !$blurred_url ? HEURIST_BASE_URL . 'hclient/assets/100x100-login-required.png' : $blurred_url;

                        }elseif(@$req_params['fullres'] === '0'){ //$get_cached_image get web cached version

                            $cache_url = getWebImageCache($system, $fileinfo, !$get_blurred_image);
                            if($cache_url){
                                $direct_url = $cache_url;
                            }
                        }

                        redirectURL($direct_url);

                    }elseif(!$is_download
                        && ($fileExt=='nxz' || $fileExt=='nxs' || $fileExt=='ply' || $fileExt=='fbx' || $fileExt=='obj'))
                    {

                        //for 3D viewer - direct url to file
                        redirectURL($system->getSysUrl().$fileinfo['fullPath']);

                    }else {
                        if($get_blurred_image){
                            $filepath = getBlurredImage($system, $fileinfo, false);
                        }
                        //see recordFile.php
                        downloadFile($mimeType, $filepath, @$req_params['embedplayer']==1?null:$originalFileName);
                    }
                }
                elseif($external_url){
                    if(@$req_params['mode']=='url'){

                        //if it does not start with http - this is relative path
                        if(strpos($originalFileName,ULF_TILED_IMAGE)===0 ||
                          !(strpos($external_url,'http://')===0 || strpos($external_url,'https://')===0)){

                            $path = USanitize::sanitizePath( $external_url );
                            //check presence of mbtiles file within folder
                            $path = HEURIST_TILESTACKS_DIR.$path;
                            if(is_dir($path)){
                                $recs = folderContent($path, 'mbtiles');
                                if($recs['count']>0){
                                    $filename = $recs['records'][1][1];
                                    $filename = pathinfo($filename);
                                    $external_url = HEURIST_BASE_URL.'mbtiles.php?'.HEURIST_DBNAME.'/'.$external_url.$filename['filename'];
                                }else{
                                    $external_url = HEURIST_TILESTACKS_URL.$external_url;
                                }
                            }elseif (file_exists($path)) {
                                $filename = pathinfo($path);
                                $external_url = HEURIST_BASE_URL.'mbtiles.php?'.HEURIST_DBNAME.'/'.$filename['filename'];
                            }
                        }

                        header(CTYPE_JSON);
                        $response = array('status'=>HEURIST_OK, 'data'=>$external_url);
                        print json_encode($response);

                    }
                    elseif(strpos($originalFileName,ULF_TILED_IMAGE)===0){

                        $thumbfile = HEURIST_THUMB_DIR.'ulf_'.$fileid.'.png';

                        if(file_exists($thumbfile)){
                            downloadFile('image/png', $thumbfile);
                        }else{
                            fileCreateThumbnail( $system, $fileid, true );
                        }


                    }else{
                        redirectURL($external_url);//redirect to URL (external)
                    }


                }else{
                    //File not found
                    redirectURL('../../hclient/assets/200x200-missed2.png');
                }
            }
        }else{
            //Filedata not found
            redirectURL('../../hclient/assets/200x200-missed.png');
        }

        $system->dbclose();

    }
}
?>
