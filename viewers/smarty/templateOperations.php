<?php

    /**
    * templateOperations.php: Controller for reportActions - operations with Smarty template files - save, delete, get, list, serve, convert global <-> local
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
use hserv\utilities\USanitize;

$mode = $_REQUEST['mode'];
if($mode!='serve'){ // OK to serve tempalte files without login
    define('LOGIN_REQUIRED',1);
}
define('PDIR','../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
require_once dirname(__FILE__).'/../../hserv/structure/search/dbsData.php';

    $dir = HEURIST_SMARTY_TEMPLATES_DIR;

    $req_params = USanitize::sanitizeInputArray();

    if($mode){ //operations with template files

        //get name of template file
        $template_file = (array_key_exists('template',$req_params)?  USanitize::sanitizeFileName(basename(urldecode($req_params['template'])),false) :null);
        //get template body from request (for execution from editor)
        $template_body = (array_key_exists('template_body',$req_params)?$req_params['template_body']:null);

        $repAction = new ReportActions($system, $dir);

        try{

            switch ($mode) {

                case 'list':
                    $repAction->getList();
                    break;

                case 'get':
                    $repAction->getTemplate($template_file);
                    break;

                case 'save':

                    //add extension and save in default template directory
                    $template_body = urldecode($template_body);

                    $res = $repAction->saveTemplate($template_body, $template_file);

                    print json_encode($res);
                    break;

                case 'delete':

                    $template_file = $dir.$template_file;
                    if(file_exists($template_file)){
                        unlink($template_file);
                    }else{
                        throw new Exception("Template file does not exist");
                    }

                    header("Content-type: text/javascript");
                    print json_encode(array("ok"=>$mode));

                    break;

                case 'import':

                    $for_cms = null;
                    if(@$req_params['import_template']['cms_tmp_name']){
                        //for CMS
                        $for_cms = basename($req_params['import_template']['cms_tmp_name']);
                        $params['size'] = 999;
                        $params['name'] = @$req_params['import_template']['name'];
                    }else{
                        //for impport uloaded gpl
                        $params = @$_FILES['import_template'];
                    }

                    $repAction->importTemplate($params, $for_cms);

                    break;
                case 'serve':
                    // convert template file to global concept IDs and serve up to caller
                    if($template_file){
                        $template_body = null;
                    }
                    $repAction->smartyLocalIDsToConceptIDs($template_file, $template_body);


                    break;

                case 'check':
                    // check if the template exists
                    if($template_file && file_exists($dir.$template_file)){
                        header("Content-type: text/javascript");
                        print json_encode(array("ok"=>"Template file exists"));
                    }else{
                        throw new Exception("Template file does not exist");
                    }
                    break;
                default;
            }

        }
        catch(Exception $e)
        {
            header("Content-type: text/javascript");
            print json_encode(array("error"=>$e->getMessage()));
        }

    }

?>
