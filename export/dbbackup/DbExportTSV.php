<?php
  
/**
* DbExportCSV.php: export entire database to TSV
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

class DbExportTSV {
    
    private $mysqli = null;
    private $system = null;    
    
    public function __construct($system) {
        $this->setSession($system);
    }
   
    /**
     * Sets the session system instance and initializes the database connection.
     *
     * @param mixed $system System instance
     */
    public function setSession($system) {
        $this->system = $system;
        $this->mysqli = $system->getMysqli();
        //$this->initialized = true;
    }    
    
}
?>
