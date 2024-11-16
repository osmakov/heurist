<?php
    /**
    * Representations of iiif objects in Presentation API v3
    * https://iiif.io/api/presentation/3.0/
    *
    *
    * parameters
    * db - heurist database
    * resource - name of resource: Canvas, Annotation Page, Annotation, Image
    * id - unique identificator of object. In case of Heurist this is obfuscation ID of registered image
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
    require_once dirname(__FILE__).'/../../autoload.php';

    $response = array();

    if(isset($req_params)){
        $params = $req_params; //from api.php
    }else{
        $params = $_REQUEST;
    }


    if(!isset($system) || $system==null){

        $system = new hserv\System();

        if( ! $system->init(@$params['db']) ){
            //get error and response
            $system->errorExitApi();//exit from script
        }
    }

    if(!(array_key_exists('id',$params)
        && $params['id']!='' && $params['id']!=null)){

        $system->errorExitApi('Resource id is not defined');//exit from script
    }

    if(!(array_key_exists('resource',$params)
        && $params['resource']!='' && $params['resource']!=null)){


    }

    $res = hserv\records\export\ExportRecordsIIIF::getIiifResource($system, null, 3, $params['id'], @$params['resource']);

    $system->dbclose();

    if($res) {
        header(HEADER_CORS_POLICY);
        $system->setResponseHeader();
        print $res;
    }else{
        $system->errorExitApi();
    }
?>