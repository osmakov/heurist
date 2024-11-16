<?php
/**
* Class to retrieve system configurations in database (sysIdentification), /settings folder
* and server root folder (list of databases javascript allowed, disk usage quotes )
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
namespace hserv;
use hserv\utilities\USystem;

/**
* Class to retrieve system configurations in database (sysIdentification), /settings folder
* and server root folder (list of databases javascript allowed, disk usage quotes )
*/
class SystemSettings {

    private $system;

    private $settingsInDb = null; //from sysIdentification
    private $settingsInFiles = array('TinyMCE formats' => 'text_styles.json', 'Webfonts' => 'webfonts.json'); //fromm /settings folder

    public function __construct( $system ) {
        $this->system = $system;
    }

    private function getSettingsFileName($setting_name)
    {
        return $this->system->getSysDir('settings') . $this->settingsInFiles[$setting_name];
    }

    private function isValidParam($setting_name){

        if(!defined('HEURIST_FILESTORE_ROOT')){
            return false;
        }

        if(!array_key_exists($setting_name, $this->settingsInFiles)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid settings requested');
            return false;
        }

        return true;
    }

    private function readSettings($setting_name){

        $setting_file = $this->getSettingsFileName($setting_name);

        $settings = array();

        if(file_exists($setting_file)){

            $settings = file_get_contents($setting_file);
            if($settings === false){
                $this->system->addError(HEURIST_ERROR, "An error occurred while attempting to read database settings for $setting_name");
            }elseif(empty($settings)){
                $settings = array();
            }else{
                $settings = json_decode($settings, true);
                if(json_last_error() !== JSON_ERROR_NONE){
                    $this->system->addError(HEURIST_ERROR, "An error occurred while decoding the existing database settings for $setting_name");
                    return false;
                }
            }
        }
        return $settings;
    }

    /**
    * Retrieves saved settings for current database in settings/ folder
    *
    * @param string $setting_name - setting's name, matches a key in settingsInFiles
    * @return array - returns either false, or array of settings
    */
    public function getDatabaseSetting($setting_name){

        if(!$this->isValidParam($setting_name)){
            return false;
        }

        return $this->readSettings($setting_name);
    }

    /**
    * Save settings for current database in settings/
    *
    * @param string $setting_name - setting's name, matches a key in settingsInFiles
    * @param array $settings - settings in JSON format
    * @param int $replace_settings - how to handle the saving, 
    *                               0 - completely replace; 
    *                               1 - merge and replace existing; 
    *                               2 - merge and retain existing
    *
    * @return true|false 
    */
    public function setDatabaseSetting($setting_name, $settings, $replace_settings = 0){

        $existing_settings = $this->getDatabaseSetting($setting_name);

        if(!$existing_settings){
            return false;
        }

        if(!is_array($settings)){
            $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid settings format');
            return false;
        }

        if($replace_settings==0 || isEmptyArray($existing_settings) ){
            $existing_settings = $settings;
        }elseif($replace_settings == 1){
            $existing_settings = array_replace_recursive($existing_settings, $settings);
        }else{
            $existing_settings = array_replace_recursive($settings, $existing_settings);
        }

        $result = false;

        $setting_file = $this->getSettingsFileName($setting_name);

        $final_settings = json_encode($existing_settings);
        if(json_last_error() !== JSON_ERROR_NONE){
            $this->system->addError(HEURIST_ACTION_BLOCKED, 'JSON ENCODE ERROR => ' . json_last_error_msg());
        }elseif(fileSave($final_settings, $setting_file) == 0){
            $this->system->addError(HEURIST_ERROR, "An error occurred while attempting to save database settings for $setting_name");
        }else{
            $result = true;
        }

        return $result;
    }

    //
    //
    //
    /**
    * Returns font-family and @impport font-face css for current Webfonts settings
    * 
    * @param mixed $default_family - default font family name
    */
    public function getWebFontsLinks($default_family=null){

        $webfonts = $this->getDatabaseSetting('Webfonts');
        $settingsURL = $this->system->getSysUrl('settings');

        $font_styles = '';

        if(!isEmptyArray($webfonts)){
            $font_families = array();

            foreach($webfonts as $font_family => $src){
                $src = str_replace("url('settings/", "url('".$settingsURL,$src);
                if(strpos($src,'@import')===0){
                    $font_styles = $font_styles . $src;
                }else{
                    $font_styles = $font_styles . ' @font-face {font-family:"'.$font_family.'";src:'.$src.';} ';
                }
                $font_families[] = $font_family;
            }

            if(!empty($font_families)){
                //add default family
                if($default_family){
                    $font_families[] = $default_family;
                }
                $font_styles = 'body,.ui-widget,.ui-widget input,.ui-widget textarea,.ui-widget select{font-family: '
                                .implode(',',$font_families).'} '.$font_styles;
            }
        }

        return $font_styles;
    }

    /**
    * Loads system settings (default values) from sysIdentification
    * 
    * @param mixed $fieldname - returns particular value or all values as array
    * @param mixed $need_reset - reloads all values from database
    */
    public function get( $fieldname=null, $need_reset = false ){

        if(!$this->settingsInDb || $need_reset)
        {
            //load from database

            $mysqli = $this->system->get_mysqli();
            $this->settingsInDb = getSysValues($mysqli);

            if(!$this->settingsInDb){
                //HEURIST_SYSTEM_FATAL
                $this->system->addError(HEURIST_DB_ERROR, 'Unable to read sysIdentification', $mysqli->error);
                return null;
            }
        }

        //returns all or particular value
        $ret = ($fieldname) ?@$this->settingsInDb[$fieldname] :$this->settingsInDb;
        return $ret;
    }

    /**
    * Checks if user's javascript is allowed in smarty reports and CMS
    *
    * text files with settings is  root server folder
    */
    public function isJavaScriptAllowed(){

        $is_allowed = false;
        $fname = realpath(dirname(__FILE__)."/../../js_in_database_authorised.txt");
        if($fname!==false && file_exists($fname)){
            //  ini_set('auto_detect_line_endings', 'true');
            $handle = @fopen($fname, "r");
            while (!feof($handle)) {
                $line = trim(fgets($handle, 100));
                if($line==$this->system->dbname()){
                    $is_allowed=true;
                    break;
                }
            }
            fclose($handle);
        }
        return $is_allowed;
    }

    /**
    * Returns allowed disk quota (for file_uploads and uploaded_tilestacks)
    *
    * text files with settings is  root server folder
    */
    public function getDiskQuota(){

        $quota = 0;
        $fname = realpath(dirname(__FILE__)."/../../disk_quota_allowances.txt");
        if($fname!==false && file_exists($fname)){
            //ini_set('auto_detect_line_endings', 'true');
            $handle = @fopen($fname, "r");
            while (!feof($handle)) {
                $line = trim(fgets($handle, 100));
                if(strpos($line,$this->system->dbname())===0){
                    $quota = USystem::getConfigBytes(null, substr($line, strlen($this->system->dbname())));
                    break;
                }
            }
            fclose($handle);
        }

        if(!isPositiveInt($quota)){
            $quota = 0;
            //$quota = 1073741824; //1GB
        }
        return $quota;
    }
}
