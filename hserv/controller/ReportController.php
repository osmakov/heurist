<?php
/*
* ReportController.php
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

use hserv\System;
use hserv\report\ReportTemplateMgr;
use hserv\report\ReportExecute;
use hserv\utilities\USanitize;

/**
 * Class ReportController
 *
 * This class handles report-related actions such as executing, updating, listing,
 * importing, and exporting report templates.
 *
 * @package hserv\controller
 */
class ReportController
{
    /**
     * @var System The system instance for managing core functionalities.
     */
    private $system;

    /**
     * @var string The directory path for templates.
     */
    private $dir;

    /**
     * @var ReportTemplateMgr Manages report template-related actions.
     */
    private $repAction;

    /**
     * @var array The request parameters, sanitized from GET or POST.
     */
    private $req_params;

    /**
     * ReportController constructor.
     *
     * Initializes the request parameters, sets up the system, and the directory for templates.
     * Also creates an instance of the ReportTemplateMgr class for managing report actions.
     *
     * @param System $system The system instance.
     * @param array|null $params Optional array of request parameters.
     *
     * @return void
     */
    public function __construct($system, $params = null)
    {
        $this->req_params = is_array($params) ? $params : USanitize::sanitizeInputArray();

        if (!isset($system)) {
            $system = new System();
            if (!$system->init(@$this->req_params['db'])) {
                dataOutput($system->getError());
                return null;
            }
        }

        $this->system = $system;

        if (defined('HEURIST_SMARTY_TEMPLATES_DIR')) {
            $this->dir = HEURIST_SMARTY_TEMPLATES_DIR;
        } else {
            $this->dir = $this->system->getSysDir('smarty-templates');
        }

        $this->repAction = new ReportTemplateMgr($this->system, $this->dir);
    }

    /**
     * Handles different actions related to report management based on the input action.
     *
     * @param string|null $action The action to be performed such as 'execute', 'update', 'list', etc.
     *
     * @return void
     */
    public function handleRequest($action)
    {
        $result = null;
        $mimeType = null;
        $filename = null;

        try {
            $template_file = $this->getTemplateFileName();
            $template_body = $this->getTemplateBody();

            if ($this->req_params['template_id'] > 0 || $this->req_params['id'] > 0) {
                $action = 'update';
            }

            if ($template_file && $action == null) {
                $action = 'execute';
            }

            switch ($action) {
                case 'execute':
                    $repExec = new ReportExecute($this->system, $this->req_params);
                    $repExec->execute();
                    break;

                case 'update':
                    $this->updateTemplate();
                    break;

                case 'list':
                    $result = $this->repAction->getList();
                    break;

                case 'get':
                    $this->repAction->downloadTemplate($template_file);
                    break;

                case 'save':
                    $result = $this->saveTemplate($template_body, $template_file);
                    break;

                case 'delete':
                    $result = $this->repAction->deleteTemplate($template_file);
                    break;

                case 'import':
                    $result = $this->importTemplate();
                    break;

                case 'export':
                    $is_check_only = array_key_exists('check', $this->req_params);
                    $result = $this->repAction->exportTemplate($template_file, $is_check_only, null);
                    break;

                case 'check':
                    $this->repAction->checkTemplate($template_file);
                    $result = 'exist';
                    break;

                default:
                    throw new \Exception('Invalid "action" parameter');
            }
        } catch (\Exception $e) {
            $result = false;
            $this->system->addError(HEURIST_ACTION_BLOCKED, $e->getMessage());
        }

        if (isset($result)) {
            if ($mimeType == null) {
                if (is_bool($result) && $result == false) {
                    $result = $this->system->getError();
                } else {
                    $result = ['status' => HEURIST_OK, 'data' => $result];
                }
            }
            dataOutput($result, $filename, $mimeType);
        }
    }

    /**
     * Saves the report template to the system.
     *
     * @param string $template_body The body of the template.
     * @param string $template_file The file name of the template.
     *
     * @return mixed The result of the template save operation.
     */
    private function saveTemplate($template_body, $template_file)
    {
        return $this->repAction->saveTemplate($template_body, $template_file);
    }

    /**
     * Imports a template either from CMS or an uploaded file.
     *
     * @return mixed The result of the import operation.
     */
    private function importTemplate()
    {
        $params = null;
        $for_cms = null;
        if (isset($this->req_params['import_template']['cms_tmp_name'])) {
            $for_cms = basename($this->req_params['import_template']['cms_tmp_name']);
            $params['size'] = 999;
            $params['name'] = $this->req_params['import_template']['name'];
        } else {
            $params = $_FILES['import_template'];
        }

        return $this->repAction->importTemplate($params, $for_cms);
    }

    /**
     * Retrieves the file name of the report template from the request parameters.
     *
     * @return string|null The sanitized template file name.
     */
    private function getTemplateFileName()
    {
        if (array_key_exists('template', $this->req_params)) {
            return USanitize::sanitizeFileName(basename(urldecode($this->req_params['template'])), false);
        }

        return null;
    }

    /**
     * Retrieves the body of the report template from the request parameters.
     *
     * @return string|null The template body.
     */
    private function getTemplateBody()
    {
        return array_key_exists('template_body', $this->req_params) ? $this->req_params['template_body'] : null;
    }

    /**
     * Updates the report template based on the request parameters.
     *
     * @return array The result of the update operation including error, created, and updated counts.
     */
    public function updateTemplate()
    {
        $result_report = [0, 0, 0, 0, [], []];
        $is_void = false;

        $rps_ID = intval($this->req_params['template_id'] ?? @$this->req_params['id']);
        $publishmode = $this->req_params['publish'] ?? 4;
        $query = 'SELECT * FROM usrReportSchedule';

        if ($rps_ID > 0) {
            $query .= ' WHERE rps_ID=' . $rps_ID;
        } else {
            $publishmode = 4;
        }

        if ($publishmode == 4) {
            $publishmode = 3;
            $is_void = true;
        }

        $repExec = new ReportExecute($this->system);
        $repExec->setParameters(['publish' => 3, 'void' => $is_void]);

        if (!$repExec->initSmarty(true)) {
            $result_report[5]['fatal'] = $repExec->getError();
            return $result_report;
        }

        $res = $this->system->getMysqli()->query($query);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $format = $this->req_params['mode'] ?? @$row['rps_URL'];
                $params = [
                    'publish' => $publishmode,
                    'mode' => $format,
                    'output' => $row['rps_FileName'] ?? $row['rps_Template'],
                    'template' => $row['rps_Template'],
                    'rps_id' => $row['rps_ID'],
                    'void' => $is_void
                ];

                $hquery = $row['rps_HQuery'];
                if (strpos($hquery, "&q=") > 0) {
                    parse_str($hquery, $params2);
                    $params = array_merge($params, $params2);
                } else {
                    $params = ["q" => $hquery];
                }

                $repExec->setParameters($params);

                //result: 0 - error, 1 - created, 2 - updated, 3 - intakted
                //check that report is already exists
                $result = 1;

                if ($publishmode == 3) {
                    $result = $repExec->outputGeneratedReport($row['rps_IntervalMinutes']);
                }

                if ($result != 3) {
                    $proc_start = time();

                    if (!$repExec->execute()) {
                        $result = 0;
                        $result_report[5][$row['rps_ID'] . ' ' . basename($row['rps_Template'])] = $repExec->getError();
                    }

                    $proc_length = time() - $proc_start;
                    if ($proc_length > 10) {
                        $result_report[4][$row['rps_ID'] . ' ' . basename($row['rps_Template'])] = $proc_length;
                    }
                }

                $result_report[$result]++;
            }
            $res->close();
        }

        return $result_report;
    }
}
