<?php
/*
* FrontController.php
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2024 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.6
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

namespace hserv\controller;

use hserv\controller\ReportController;
use hserv\records\import\ImportAnnotations;
use hserv\System;
use hserv\utilities\USanitize;
use hserv\structure\ConceptCode;

/**
 * Class FrontController
 *
 * This class is responsible for managing the overall system flow and handling
 * requests by detecting and delegating to the appropriate controller.
 *
 * @package hserv\controller
 */
class FrontController
{
    /**
     * @var System $system The system object managing the core system functionalities.
     */
    private $system;

    /**
     * @var array $req_params The sanitized request parameters from GET or POST.
     */
    private $req_params;

    /**
     * FrontController constructor.
     *
     * Initializes the request parameters by sanitizing the input and
     * sets up the system. If the system initialization fails, an error is returned.
     *
     * @return void
     */
    public function __construct($params=null)
    {
        // Take from GET or POST
        $this->req_params = $this->req_params = is_array($params) ? $params : USanitize::sanitizeInputArray();

        $system = new System();
        if (!$system->init(@$this->req_params['db'])) {
            dataOutput($system->getError());
            return null;
        }

        $this->system = $system;

        ConceptCode::setSystem($system);
    }

    /**
     * Runs the front controller logic.
     *
     * Detects if the system is initialized and decides which controller to
     * delegate the request to. If the controller is detected as ReportController,
     * the request is passed on for further handling.
     *
     * @return void
     */
    public function run()
    {
        // Detect controller class
        if (!(isset($this->system) && $this->system->isInited())) {
            return;
        }

        if (@$this->req_params['controller'] == 'ReportController'  // $this->req_params['controller']
            || @$this->req_params['template']
            || @$this->req_params['template_body']
            || @$this->req_params['template_id']) {

            $controller = new ReportController($this->system, $this->req_params);
            $controller->handleRequest(@$this->req_params['action']);
            
        }elseif(@$this->req_params['controller'] == 'ImportAnnotations'){
            
            $controller = new ImportAnnotations($this->system, $this->req_params);
            $result = $controller->execute();
            
            if (is_bool($result) && $result == false) {
                $result = $this->system->getError();
            } else {
                $result = ['status' => HEURIST_OK, 'data' => $result];
            }
            dataOutput($result);            
        }
    }
}
