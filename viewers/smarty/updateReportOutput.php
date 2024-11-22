<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* updateReportOutput.php  - for backward capability. See ReportController
*
* It takes a report ID (rps_ID in usrReportSchedule) and writes out the report (html and js files)
* to the location specified for that ID.
* If ID is 0 it should trigger sequential refreshing of all the reports
*
* parameters
* 'id' - key field value in usrReportSchedule
* 'mode' - if publish>0: js or html (default) - output format
* 'publish' - 0 vsn 3 UI (smarty tab),  1 - publish,  2 - no browser output (save into file only)
*                 3 - redirect the existing report (use already publshed output), if it does not exist publish=1
*
* If publish=1 then the script displays a web page with a report on the process
* (success or errors as below). If not set, then the errors (file can't be written, can't find template,
* can't find file path, empty query etc) are sent by email to the database owner.
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson.heurist@gmail.com>
* @author      Stephen White
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/
use hserv\controller\FrontController;
use hserv\utilities\USanitize;

require_once dirname(__FILE__).'/../../autoload.php';
$params = USanitize::sanitizeInputArray();
$params['controller'] = 'ReportController';
if(!isset($params['id'])){
    $params['id'] = @$params['template_id'];
}
$frontController = new FrontController($params);
$frontController->run();