<?php
/**
* Service to get icons and thumbs for entities
* (for recUploadFiles see fileDownload.php)
*
* fileGet.php - 1) get image for given entity, record ID, version and color
*               2) get or check file from code folders - tips, help, doc content
*               3) load file from scratch folder (tries to convert to UTF8) - for import terms
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
require_once 'entityScrudSrv.php';

/*

id - record id
entity - by default recordtypes
def - if file not found return empty gif (0) or add image gif (1) or default icon/thumb for entity (2)
version - thumb or thumbnail|icon|full (thumb is default)

*/

$req_params = USanitize::sanitizeInputArray();

//main purpose - download entity images
$db = @$req_params['db'];
$filename = basename(@$req_params['file']);
$entity_name = htmlspecialchars(@$req_params['entity']);

$error = mysql__check_dbname($db);

if($error==null){

        list($db_full, $db) = mysql__get_names( $db );

        $system = new hserv\System();//without db connection and session - just paths
        $system->initPathConstants($db);

if($filename){ //download from scratch (for csv import)

        $msgTempFile = 'Temporary file (uploaded csv data) ';

        //remove slashes - prevents Local file disclosure
        $filename = USanitize::sanitizeFileName($filename, false);

        $file_read = HEURIST_FILESTORE_DIR.DIR_SCRATCH.$filename;

        $file_read = isPathInHeuristUploadFolder( $file_read );

        if(!$file_read || is_dir($file_read))
        {
            print $msgTempFile.htmlspecialchars($filename). ' not found';
            exit;
        }

        $content_type = null;

        $csv_encoding = @$req_params['encoding'];

        if($csv_encoding && $csv_encoding!='UTF-8'){ //force convert to utf8

            $handle = @fopen($file_read, "r");
            if (!$handle) {
                $s = null;
                if (! file_exists($file_read)){
                     $s = ' does not exist.<br><br>'
                    .'Please clear your browser cache and try again. if problem persists please '.CONTACT_HEURIST_TEAM.' immediately';
                }elseif (! is_readable($file_read)) {
                    $s = ' is not readable';
                }
                else {
                    $s = ' could not be read';
                }

                if($s){
                    print $msgTempFile.htmlspecialchars($file_read). $s;
                    exit;
                }
            }

            setlocale(LC_ALL, 'en_US.utf8');
            fclose($handle);
            
            $content = file_get_contents($file_read);
            $content = mb_convert_encoding( $content, 'UTF-8', $csv_encoding);
            if(!$content){
                print $msgTempFile.htmlspecialchars($file_read)
                .' can\'t be converted to UTF-8. Please open it in any advanced editor and save with UTF-8 text encoding';
                exit;
            }

            $res = file_put_contents($file_read, $content);
            unset($content);
            if(!$res){
                print 'Cant save temporary file (with UTF-8 encoded csv data) '.htmlspecialchars($file_read);
                exit;
            }

        }
        _download_file($file_read, null);

}else{  //download entity images (icons, thumbs) for entity folder in HEURIST_FILESTORE_DIR


        $content_type = 'image/png';

        $rec_id = @$req_params['icon'];
        if($rec_id==null) {$rec_id = @$req_params['id'];}

        //icon, thumb, full
        $viewmode = rawurlencode(@$req_params['version']);

        if($rec_id && substr($rec_id,0,4)=='term'){
            //backward support - icons for Digital Harlem
            $rec_id = substr($rec_id, 4);
            $entity_name  = 'trm';
            $viewmode = 'icon';
        }elseif($rec_id && strpos($rec_id, 'thumb/th_')===0){
            //backward support - thumbnail for rectype import
            $rec_id = substr($rec_id, 9);
            $entity_name  = 'rty';
            $viewmode = 'thumb';

        }elseif(!$entity_name) {
            $entity_name = 'rty';//default - defRecTypes
        }

        $entity_name = entityResolveName($entity_name);

        if(!$entity_name){
            exit;
        }

        if(strpos($rec_id, '.png')>0){
            $rec_id = substr($rec_id, 0, -4);
        }

        list($filename, $content_type, $file_url) = resolveEntityFilename($entity_name, $rec_id, $viewmode, $db);


        //entity id either not defined or requested file doesn't exist
        //editmode: empty gif (0) or add image gif (1) or default icon/thumb for entity (2), or (check)  'ok' if it exists or '' missing

        //what return by default?
        // 3 (or check) - return "ok" if file exists otherwise "not found"
        // 2 - entity default icon or thumb
        // 1 - returns image with invitation "add image"
        // otherwise it returns empty image placeholder (100x100 or 16x16 for icons)
        $default_mode = @$req_params['def'];
        if($default_mode=='check') {$default_mode = 3;}
        elseif($default_mode==null) {$default_mode = 2;}


        if(file_exists($filename) && !is_dir($filename)){
            if($default_mode==3){ //check

                $response = array('status'=>HEURIST_OK, 'data'=>'ok');
                header(CTYPE_JSON);
                print json_encode($response);

            }else{

                //color, bg, circle
                if(@$req_params['color'] && $ext!='svg'){
                    UImage::changeImageColor($filename, null, @$req_params['color'], @$req_params['circle'], @$req_params['bg']);
                }else{
                    if($file_url!=null && isset($allowWebAccessEntityFiles) && $allowWebAccessEntityFiles){
                        header('Location:'.$file_url);
                    }else{
                        _download_file($filename, $content_type);
                    }
                }
            }

        }elseif($default_mode==3){ //check existance

                $response = array('status'=>HEURIST_OK, 'data'=>'not found');
                header(CTYPE_JSON);
                print json_encode($response);

        }else{

            if($entity_name && ($default_mode=='view' || $default_mode==2)) //get entity default icon or thumb
            {
                //at the moment we don't have full images that describe entity - only icons and thumbs
                $filename = dirname(__FILE__).'/../../hclient/assets/'
                                .$entity_name.(($viewmode=='icon')?'':'_thumb').'.png';

                if(file_exists($filename) && !is_dir($filename)){
                    _download_file($filename, $content_type);
                    exit;
                }
            }

            if ($default_mode=='edit' || $default_mode==1){ //show invitation to add image
                _download_file(dirname(__FILE__).'/../../hclient/assets/100x100click.png', $content_type);
            }else {
                $content_type = 'image/gif';
                if($viewmode = 'icon'){
                    _download_file(dirname(__FILE__).'/../../hclient/assets/16x16.gif', $content_type);
                }else{
                    _download_file(dirname(__FILE__).'/../../hclient/assets/100x100.gif', $content_type);
                }
            }

        }
        exit;
}

}else {
    if(!$entity_name){ //print error as text
        print htmlspecialchars($error);
    }else{
        _download_file(dirname(__FILE__).'/../../hclient/assets/100x100.gif', 'image/gif');
    }
}


//
//
//
function _download_file($filename, $content_type){

        ob_start();
        if($content_type) {header('Content-type: '.$content_type);}
        header('Pragma: public');
        header(CONTENT_LENGTH . filesize($filename));
        @ob_clean();
        flush();
        readfile($filename);
}
?>