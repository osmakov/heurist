<?php
/*
* ReportTemplateMgr.php
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

namespace hserv\report;

use hserv\structure\ConceptCode;
use hserv\utilities\USanitize;

/**
 * Class ReportTemplateMgr
 *
 * Handles operations with Smarty template files, including saving, deleting,
 * retrieving, listing, exporting, and converting between local and global codes.
 */
class ReportTemplateMgr
{
    /**
     * @var mixed $system The system object for interacting with Heurist.
     */
    protected $system;

    /**
     * @var string $dir The directory where Smarty template files are stored.
     */
    protected $dir;

    /**
     * ReportTemplateMgr constructor.
     *
     * Initializes the object with the system and directory parameters. If the directory
     * is not passed, it uses the default defined 'HEURIST_SMARTY_TEMPLATES_DIR'.
     *
     * @param mixed $_system The system object.
     * @param string $_dir The directory where templates are stored.
     */
    public function __construct($_system, $_dir = null)
    {
        global $system;

        $this->system = $_system ?: $system;
        $this->dir = $_dir ?? (defined('HEURIST_SMARTY_TEMPLATES_DIR') ? HEURIST_SMARTY_TEMPLATES_DIR : null);
    }

    /**
     * Returns a list of available templates in the Smarty directory as a JSON array.
     *
     * This method scans the template directory for files, converts .gpl files to .tpl,
     * and returns all available .tpl files.
     *
     * @return array filename=>name (template name)
     */
    public function getList()
    {
        $files = scandir($this->dir);
        $results = [];

        foreach ($files as $filename) {
            $path_parts = pathinfo($filename);
            if (!array_key_exists('extension', $path_parts)) {
                continue;
            }

            $ext = strtolower($path_parts['extension']);
            $ind = strpos($filename, "_");
            $isnot_temp = (!(is_numeric($ind) && $ind == 0));

            if (file_exists($this->dir . $filename) && $ext == "gpl") {

                $processed_template = $this->processGplFile($filename);
                if ($processed_template) {
                    $results[] = $processed_template;
                }

            } elseif (file_exists($this->dir . $filename) && $ext == "tpl" && $isnot_temp) {
                $name = substr($filename, 0, -4);
                $results[] = ['filename' => $filename, 'name' => $name];
            }

        }

        return $results;
    }

    /**
    * Converts gpl file to tpl
    *
    * @param mixed $filename
    */
    private function processGplFile($filename)
    {
        $template_body = file_get_contents($this->dir . $filename);
        $res = $this->convertTemplate($template_body, 1);

        if (is_array($res) && isset($res['template'])) {
            $template_body = $res['template'];
            $filename_tpl = $this->saveTemplate($template_body, $filename);

            if ($filename_tpl) {
                $name = substr($filename_tpl, 0, -4);
                fileDelete($this->dir . $filename); // Remove .gpl
                return ['filename' => $filename_tpl, 'name' => $name];
            }
        }

        return null;
    }


    /**
     * Returns the content of a specified template file.
     *
     * @param string $template_file The name of the template file to retrieve.
     * @return void Outputs the content of the template file or throws error message if not found.
     */
    public function downloadTemplate($template_file)
    {
        try {
            if ($template_file == null || $template_file == '') {
                $template_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'template.tpl';
                if (!file_exists($template_file)) {
                    throw new \Exception("Template example file not found");
                }
            } else {
                $template_file = $this->checkTemplate($template_file);
            }

            header(CTYPE_HTML);
            $res = readfile($template_file);

            if (!$res) {
                throw new \Exception("Cannot read template file " . basename($template_file));
            }

        } catch (\Exception $e) {
            print $e->getMessage();
        }
    }

    /**
     * Saves the provided template content into a file.
     *
     * @param string $template_body The body/content of the template to save.
     * @param string $template_file The name of the template file to save.
     * @return string The file name if successful, throws error otherwise.
     */
    public function saveTemplate($template_body, $template_file)
    {
        $path_parts = pathinfo($template_file);
        $template_file = $path_parts['filename'] . '.tpl';
        $template_file_fullpath = $this->dir . $template_file;

        $res = folderExists($this->dir, true);
        if ($res > 0) {
            $res = fileSave($template_body, $template_file_fullpath);
        }
        if ($res <= 0) {
            throw new \Exception('Cannot write file. Check permissions for the Smarty template directory');
        }
        return $template_file;
    }

    /**
     * Deletes the specified template file.
     *
     * @param string $template_file The name of the template file to delete.
     * @return string 'deleted' if the file is successfully deleted.
     */
    public function deleteTemplate($template_file)
    {
        $template_file = $this->checkTemplate($template_file);
        unlink($template_file);
        return 'deleted';
    }

    /**
     * Validates and returns the full path of the template file.
     *
     * @param string $template_file The name of the template file to check.
     * @return string The full path to the template file.
     * @throws \Exception If the file is not found.
     */
    public function checkTemplate($template_file)
    {
        $safeFileName = basename($template_file);
        $template_file = $this->dir . $safeFileName;
        if (file_exists($template_file)) {
            return $template_file;
        } else {
            throw new \Exception("Template file $safeFileName not found");
        }
    }

    /**
     * Converts a template by replacing local IDs with concept IDs or vice versa.
     *
     * @param string $template The template content to be converted.
     * @param int $mode 0 to convert to concept code, 1 to convert to local code.
     * @return mixed Returns the converted template or an array with errors.
     */
    public function convertTemplate($template, $mode)
    {
        //1. get template content
        //2. find all texts within {}
        //3. find words within this text
        //4. split by .
        //5. find starting with "f"
        //6. get local DT ID - find Concept Code
        //7. replace

        //1. get template content
        if($template==null || $template==''){
            throw new \Exception('Template is empty');
        }

        //2. find all texts within {} - expressions
        if (! preg_match_all('/\{([^}]+)\}/s', $template, $matches)){
            return $template;    // nothing to do -- no substitutions
        }

        $not_found_details = array();
        $replacements_exp = array();

        $len = count($matches[1]);
        for ($i=0; $i < $len; ++$i) {

            $exp = $matches[1][$i];
            if(!trim($exp)) {continue;} //empty{}
            if(substr($exp,0,1)=="*" && substr($exp,-1)=="*") {continue;} //this is comment

        //3. find words within this text
            if (! preg_match_all('/(\\$([a-zA-Z_0-9.])+)/', $exp, $matches2) ){
                continue;
            }

            $replacements = array();

            foreach ($matches2[1] as $var) {

        //4. split by "."
                    $parts = explode(".", $var);
                    $parts2 = array();
                    foreach ($parts as $part) {
        //5. find starting with "f"
                        if(strpos($part, 'f')===0){
                            $prefix = 'f';
                        }elseif(strpos($part, '$f')===0){
                            $prefix = '$f';
                        }else{
                            $prefix = null;
                        }

                        if($prefix){
        //6. get local DT ID - find Concept Code
                            $code = substr($part, strlen($prefix));
                            if(substr($part, -1)=='s'){
                                    $suffix = 's';
                                    $code = substr($code,0,strlen($code)-1);
                            }elseif(ReportTemplateMgr::endsWith($part,'_originalvalue')){
                                    $suffix = '_originalvalue';
                                    $code = substr($code,0,strlen($code)-strlen($suffix));
                            }else{
                                    $suffix = "";
                            }

                            if($mode==0){
                                $localID = $code;
                                if(strpos($localID,"_")===false){
                                    $conceptCode = ConceptCode::getDetailTypeConceptID($localID);
                                    $part = $prefix.str_replace("-","_",$conceptCode).$suffix;
                                }
                            }else{
                                $conceptCode = $code;

                                if(strpos($conceptCode,"_")!==false){
                                    $conceptCode = str_replace("_","-",$conceptCode);

                                    $localID = ConceptCode::getDetailTypeLocalID($conceptCode);
                                    if($localID==null){
                                        //local code not found - it means that this detail is not in this database
                                        array_push($not_found_details, $conceptCode);
                                        $part = $prefix."[[".$conceptCode."]]".$suffix;
                                    }else{
                                        $part = $prefix.$localID.$suffix;
                                    }
                                }
                            }
                        }
                        array_push($parts2, $part);
                    }
                    $new_var = implode(".", $parts2);

                    if($var!=$new_var){
                        $replacements[$var] = $new_var;
                    }
            }//for vars

            if(!empty($replacements)){
                   $new_exp = "{".$this->arrayStrReplace(array_keys($replacements), array_values($replacements), $exp)."}";
                   if($matches[0][$i] != $new_exp){
                        $replacements_exp[$matches[0][$i]] = $new_exp;
                   }
            }
        }//for expressions


        if(!empty($replacements_exp)){
             $template = $this->arrayStrReplace(array_keys($replacements_exp), array_values($replacements_exp), $template);
        }

        return $mode == 1 ? ["template" => $template, "details_not_found" => $not_found_details] : $template;
    }

    /**
     * Converts local IDs in a template file to global concept IDs and outputs the result.
     *
     * @param string $filename The name of the template file.
     * @param bool $is_check_only Whether to only check or export the file.
     * @param string|null $template_body The template content, if null, it is loaded from the file.
     * @return void Outputs the converted template or an error in JSON format.
     */
    public function exportTemplate($filename, $is_check_only, $template_body = null)
    {
        $dbID = $this->system->settings->get('sys_dbRegisteredID');
        if (!$dbID) {
            throw new \Exception('Database must be registered to allow translation of local template to global template');
        }

        if ($filename) {
            $template_file = $this->checkTemplate($filename);
            $template_body = file_get_contents($template_file);
        } else {
            $filename = 'Export.gpl';
        }

        if ($template_body && strlen($template_body) > 0) {
            $filename = str_replace(".tpl", ".gpl", basename($filename));

            if ($is_check_only) {
                return 'ok';
            } else {
                $content = $this->convertTemplate($template_body, 0);
                header('Content-type: html/text');
                header('Content-Disposition: attachment; filename=' . $filename);
                print $content;
            }
        } else {
            throw new \Exception('Template is not defined or empty');
        }
        return null;
    }

    /**
     * Imports a template from the provided data.
     *
     * @param array $params The uploaded file parameters.
     * @param string|null $for_cms Indicates a CMS template upload if provided.
     * @return array with the name of the template and a list of unrecognized details.
     */
    public function importTemplate($params, $for_cms = null)
    {
        if (!$params || !$params['size']) {
            throw new \Exception('Error occurred during upload - file is zero size');
        }

        $origfilename = basename($params['name']);
        $filename = null;

        if ($for_cms) {
            $path = realpath(dirname(__FILE__) . '/../../hclient/widgets/cms/templates/snippets/');
            if ($path) {
                $filename = $path . DIRECTORY_SEPARATOR . basename($for_cms);
            }
        } elseif (isset($params['tmp_name']) && is_uploaded_file($params['tmp_name'])) {
            $filename = USanitize::sanitizePath($params['tmp_name']);
        }

        if (!$filename || !file_exists($filename)) {
            throw new \Exception('Error occurred during upload - file does not exist');
        }

        $template = file_get_contents($filename);
        $res = $this->convertTemplate($template, 1);

        if (isset($res['error'])) {
            throw new \Exception($res['error']);
        }

        $origfilename = getUniqueFileName($this->dir, $origfilename, 'tpl');

        $save_res = [];
        $save_res['filename'] = $this->saveTemplate($res['template'], $origfilename);

        if (!empty($res['details_not_found'])) {
            $save_res['details_not_found'] = $res['details_not_found'];
        }

        return $save_res;
    }

    /**
     * Safely replaces an array of search strings in the subject with the corresponding replacement strings.
     *
     * @param array $search Array of search strings.
     * @param array $replace Array of replacement strings.
     * @param string $subject The string in which to perform replacements.
     * @return string The modified string with replacements applied.
     */
    private function arrayStrReplace($search, $replace, $subject)
    {
        $result = '';

        while ($subject !== '') {
            list($match_idx, $match_offset) = $this->findNextMatch($search, $subject);

            if ($match_idx !== -1) {
                $result .= substr($subject, 0, $match_offset) . $replace[$match_idx];
                $subject = substr($subject, $match_offset + strlen($search[$match_idx]));
            } else {
                $result .= $subject;
                break;
            }
        }

        return $result;
    }

    /**
     * Helper function to find the next match for any of the search terms.
     *
     * @param array $search Array of search strings.
     * @param string $subject The subject string in which to search.
     * @return array An array with the match index and offset.
     */
    private function findNextMatch($search, $subject)
    {
        $match_idx = -1;
        $match_offset = -1;

        foreach ($search as $i => $term) {
            if ($term === '') {
                continue;
            }

            $offset = strpos($subject, $term);
            if ($offset !== false && ($match_offset === -1 || $offset < $match_offset)) {
                $match_idx = $i;
                $match_offset = $offset;
            }
        }

        return [$match_idx, $match_offset];
    }

    //
    // in php v8 use str_ends_with
    //
    private static function endsWith($haystack, $needle) {
        // search forward starting from end minus needle length characters
        return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
    }

}
