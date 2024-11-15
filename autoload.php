<?php
    /**
    * Registers autload function to enable for classes and interfaces to be
    * automatically loaded if they are currently not defined (by include/require).
    *
    * Includes common scripts: config, const, db access and 3 static classes
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





spl_autoload_register(function ($class) {

    $prefix = 'hserv\\';
    if (strpos($class, $prefix) !== 0) {
        //alternative $prefix = __NAMESPACE__ . $class;
        return;
    }

    $filename = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    $filepath = __DIR__ . DIRECTORY_SEPARATOR . $filename;

    if (!is_readable($filepath)) {
        return;
    }
    require_once $filepath;
});

require_once dirname(__FILE__).'/configIni.php';// read in the configuration file
require_once dirname(__FILE__).'/hserv/consts.php';

require_once dirname(__FILE__).'/hserv/dbaccess/utils_db.php';
require_once dirname(__FILE__).'/hserv/utilities/UFile.php';
require_once dirname(__FILE__).'/hserv/utilities/UMail.php';
require_once dirname(__FILE__).'/hserv/utilities/ULocale.php';

global $system;
global $glb_curl_error;
?>
