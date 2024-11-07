<?php
/**
* 3D online viewer (https://3dviewer.net/) for
*  obj, 3ds, stl, ply, gltf, glb, off, 3dm, fbx, dae, wrl, 3mf, ifc, brep, step, iges, fcstd, bim
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

require_once dirname(__FILE__).'/../../../autoload.php';
require_once dirname(__FILE__).'/../../../hserv/records/search/recordFile.php';

define('ERROR_REDIR', dirname(__FILE__).'/../../framecontent/infoPage.php');

/*
Parameters:

db - database name
file or ulf_ID - obfuscation id for registred 3object in nxs or nxz format
@todo q  - standard heurist query that return record with 3object media (only first record will be taken)
@todo id - record with 3object media

*/
$req_params = USanitize::sanitizeInputArray();

$is_not_inited = true;
$db = @$req_params['db'];

// init main system class
$system = new hserv\System();

define('EDIR','../../../external/3D/');

if($system->init($db, true, false) && $system->initPathConstants($db)){ //without session

    if(@$req_params['file'] || @$req_params['ulf_ID']) { //ulf_ID is obfuscation id here

        $fileid = @$req_params['file']? $req_params['file'] :@$req_params['ulf_ID'];

        //find file info
        $listpaths = fileGetFullInfo($system, $fileid);
        if(!isEmptyArray($listpaths)){
            $fileinfo = $listpaths[0];//
            $fileExt = $fileinfo['ulf_MimeExt'];
            $allowed_exts = array('obj', '3ds', 'stl', 'ply', 'gltf', 'glb', 'off', '3dm', 'fbx', 'dae', 'wrl', '3mf', 'ifc', 'brep', 'step', 'iges', 'fcstd', 'bim');

            if(in_array($fileExt, $allowed_exts)){

                $url = $system->getSysUrl().$fileinfo['fullPath']; //need extension
                $textures = array();

                //find related mtl and texture files by original file name
                if($fileExt=='obj'){
                    $filename = USanitize::sanitizePath($system->getSysDir().$fileinfo['fullPath']);

                    $file_obj = realpath($filename);
                    $file_mtl = null;
                    //find mtl file name  'mtllib name_of_file.mtl'
                    $handle = @fopen($file_obj, 'r');

                    if($handle){
                        while (($line = fgets($handle, 4096)) !== false) {
                            if(strpos($line,'mtllib')===0)  //strpos($line,'#')===false
                            {
                                $file_mtl = trim(substr($line,7));
                                break;
                            }
                            if(strpos($line,'v ')===0){
                                break;
                            }
                        }
                        fclose($handle);

                        $file_mtl_full = null;
                        if($file_mtl!=null){
                            $file_mtl_full = fileRenameToOriginal($system, $file_mtl);
                        }

                        //read mtl and find textures  map_**, bump disp decal
                        if($file_mtl_full && file_exists($file_mtl_full)){

                            array_push($textures, $file_mtl);

                            $is_changed = false;
                            $content = array();
                            foreach(file($file_mtl_full) as $line) {
                                // do stuff here
                                $line = trim($line);
                                if(strpos($line, 'map_')===0){
                                    // || strpos($line, 'bump')===0 || strpos($line, 'disp')===0 || strpos($line, 'decal')===0)
                                    $k = strpos($line,' ');
                                    $file_txt2 = trim(substr($line, $k+1));
                                    $file_txt = preg_replace('/\s+/', '', $file_txt2);

                                    if($file_txt!=$file_txt2){
                                        $line = substr($line, 0, $k).' '.$file_txt;
                                        $is_changed = true;
                                    }

                                    $file_txt_full = fileRenameToOriginal($system, $file_txt2, $file_txt);
                                    if($file_txt_full!=null && file_exists($file_txt_full)){
                                        array_push($textures, $file_txt);
                                    }
                                }
                                array_push($content, $line."\n");
                            }
                            if($is_changed){
                                file_put_contents($file_mtl_full, $content);
                            }
                        }

                    }

                    foreach($textures as $idx=>$fname) {
                        $textures[$idx] = $system->getSysUrl(DIR_FILEUPLOADS).$fname;

                    }
                }
                if(!empty($textures)){
                    $textures = ',"'.implode('","',$textures).'"';
                }else{
                    $textures = '';
                }

                $is_not_inited = false;

            }else{
                $system->addError(HEURIST_ACTION_BLOCKED, 'Requested 3D media is not supported format');
            }

        }else{
            $system->addError(HEURIST_NOT_FOUND, 'Requested file is not found. Check parameter "file"');
        }

    }else{
        $system->addError(HEURIST_INVALID_REQUEST, 'Parameter "file" is not defined');
    }
}

if($is_not_inited){
    include_once ERROR_REDIR;
    exit;
}

$url = str_replace('&amp;','&',htmlspecialchars($url));

?>
<!DOCTYPE HTML>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
<meta name="robots" content="noindex,nofollow">
<meta content="charset=UTF-8"/>
<title>3D Viewer</title>
<script type="text/javascript" src="<?php echo EDIR;?>o3dv.min.js"></script>
</head>
<body>
<div id="online_3d_viewer" class="online_3d_viewer" style="width:100%;height:100%;">
</div>
</body>
<script type="text/javascript">

function setup3viewer() {

    // set the location of the external libraries
    OV.SetExternalLibLocation ('libs');

    if(false){
        //attribute for div model="<?php echo $url?>"
        OV.Init3DViewerElements ();// init all viewers on the page
        return;
    }

    // get the parent element of the viewer
    let parentDiv = document.getElementById ('online_3d_viewer');

    // initialize the viewer with the parent element and some parameters
    let opts1 = {
        backgroundColor : new OV.RGBAColor (255, 255, 255, 255)
        ,defaultColor : new OV.RGBColor (200, 200, 200)
        ,edgeSettings : new OV.EdgeSettings (false, new OV.RGBColor (0, 0, 0), 1)
        //toolbarDiv:
        /*
        environmentSettings : {
            environmentMap : [
                '<?php echo EDIR;?>envmaps/fishermans_bastion/posx.jpg',
                '<?php echo EDIR;?>envmaps/fishermans_bastion/negx.jpg',
                '<?php echo EDIR;?>envmaps/fishermans_bastion/posy.jpg',
                '<?php echo EDIR;?>envmaps/fishermans_bastion/negy.jpg',
                '<?php echo EDIR;?>envmaps/fishermans_bastion/posz.jpg',
                '<?php echo EDIR;?>envmaps/fishermans_bastion/negz.jpg'
            ],
            backgroundIsEnvMap : false
        },
        onModelLoaded : () => {
            let model = viewer.GetModel ();
            // do something with the model
        },
        camera : new OV.Camera (
            new OV.Coord3D (-1.5, -3.0, 2.0),
            new OV.Coord3D (0.0, 0.0, 0.0),
            new OV.Coord3D (0.0, 0.0, 1.0)
        ),
        */
    };

    let opts2 = {};

    let viewer = new OV.EmbeddedViewer (parentDiv, opts1);

    // load a model providing model urls
    viewer.LoadModelFromUrlList ([
        "<?php echo $url;?>"<?php echo $textures; ?>
    ]);
}

window.addEventListener('DOMContentLoaded', (event) => {
    setup3viewer();
});
</script>
</html>