<?php
/**
* smartyInit.php: init Smarty and additional functions
* this file should be included after system init
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

require_once dirname(__FILE__).'/../../vendor/autoload.php';

use Smarty\Smarty;
use Smarty\Security;
use Smarty\Template;

class HeuristSecurityPolicy extends Security {

  // disable acess to static classes
  public $static_classes = null;

  public $allowed_modifiers = array('isset', 'empty', 'escape', //'constant',
                    'sizeof', 'in_array', 'is_array', 'intval', 'implode', 'explode',
                    'array_key_exists', 'array_column', 'array_keys', 'array_multisort',
                    'array_diff', 'array_count_values', 'array_unique',
                    'asort', 'array_merge', 'array_slice', 'array_values', 'cat',
                    'capitalize',
                    'count',
                    'count_characters','count_words','count_paragraphs',
                    'date_format',
                    'floatval','indent','is_numeric','json_encode',
                    'lower','nl2br',
                    'preg_match_all','print_r', 'printf','replace',
                    'range', 'regex_replace', 'round',
                    'sort',
                    'setlocale','spacify', 'strcmp',
                    'strip', 'strstr', 'substr', 'strpos', 'string_format', 'strlen', 'strip_tags',
                    'arraysortby',
                    'time','translate','truncate',
                    'out','wrap',
                    'upper','utf8_encode','wordwrap');

  public $allow_super_globals = true; //default true

  public $allowed_tags = false;

  public $allow_constants = true;

}

function smartyInit($smarty_templates_dir=null){


    if($smarty_templates_dir==null && defined('HEURIST_SMARTY_TEMPLATES_DIR')){
        $smarty_templates_dir = HEURIST_SMARTY_TEMPLATES_DIR;
    }

    if(!file_exists($smarty_templates_dir)){
        throw new \Exception('Smarty templates folder does not exist');
    }
    //check folder existance and create new folders if they are missing
    if(!folderCreate($smarty_templates_dir, true)){
        throw new \Exception('Failed to create folder for smarty templates');
    }
    if(!folderCreate($smarty_templates_dir.'configs/', true)){
        throw new \Exception('Failed to create configs folder for smarty templates');
    }
    if(!folderCreate($smarty_templates_dir.'compiled/', true)){
        throw new \Exception('Failed to create compiled folder for smarty templates');
    }
    if(!folderCreate($smarty_templates_dir.'cache/', true)){
        throw new \Exception('Failed to create cache folder for smarty templates');
    }

        $smarty = new Smarty();


        $smarty->setTemplateDir($smarty_templates_dir);
        $smarty->setCompileDir($smarty_templates_dir.'compiled');
        $smarty->setCacheDir($smarty_templates_dir.'cache');
        $smarty->setConfigDir($smarty_templates_dir.'configs');

        // enable security

        $smarty->enableSecurity('HeuristSecurityPolicy');

        //allowed php functions
        $php_functions = array( 'count', //'constant',
                    'sizeof', 'in_array', 'is_array', 'intval', 'implode', 'explode',
                    //'array_key_exists', 'array_column',
                    'array_count_values', 'array_keys',
                    'array_diff', 'array_merge', 'array_slice', 'array_unique',
                    //'array_multisort', 'asort', 'ksort', 'sort', - these function returns result by reference - it does not work in smarty5
                    'array_values',
                    'floatval', 'is_numeric', 'json_encode',
                    'nl2br',
                    'preg_match_all','print_r','printf', 'range', 'round',
                    'setlocale', 'strcmp', 'strpos', 'strstr', 'substr', 'strlen',
                    'time',
                    'utf8_encode');

        //register php functions
        foreach($php_functions as $fname){
            $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, $fname, $fname);
        }

        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'array_key_exists', 'heuristModifierArrayKeyExists');
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'array_column', 'heuristModifierArrayColumn');
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'translate', 'heuristModifierTranslate');
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'arraysortby', 'heuristModifierArraySortByFields');

        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'array_multisort', 'heuristModifierArrayMultiSort');

        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'sort', 'heuristModifierArraySort');
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'asort', 'heuristModifierArrayASort');
        $smarty->registerPlugin(Smarty::PLUGIN_MODIFIER, 'ksort', 'heuristModifierArrayKSort');
        

        return $smarty;
}


function heuristModifierArraySort($arr){
    sort($arr);
    return $arr;
}
function heuristModifierArrayASort(&$arr){
    asort($arr);
    return $arr;
}
function heuristModifierArrayKSort($arr){
    ksort($arr);
    return $arr;
}

function heuristModifierArrayMultiSort(...$params){
    array_multisort(...$params);
    return end($params);
}

function heuristModifierArrayKeyExists($key, $arr){
    return is_array($arr) && array_key_exists($key, $arr);
}

function heuristModifierArrayColumn($arr, $column){
    if(is_array($arr)){ // && array_key_exists($column, $arr[0])
        return array_column($arr, $column);
    }else{
        return '';
    }
}
function heuristModifierTranslate($input, $lang, $field=null)
{
    return getTranslation($input, $lang, $field);//see ULocale
}
/**
* Smarty Plugin - Array Sort
* Sorts an array by supplied fields
*
* $sortby - comma separated string or array of columns/keys
*           prefix '-' sort desc, prefix '#' - sort numeric
*
* for example
*       foreach $arr|arraysortby:"-auteur,#auteurid"
* or
*       $arr=arraysortby($arr,"auteur")
*
* if $sortby is not defined it sorts
*   a) by first column for multidimensional array
*   b) use "sort" function for flat array
*
*/
function heuristModifierArraySortByFields($arr, $sortby=null)
{

    if(!isset($sortby)){
        if(is_array($arr[0])){
            //sort by first column
            $sortby = array_keys($arr[0])[0];
        }else{
            //flat array
            sort($arr);
            return $arr;
        }
    }
    if(!is_array($sortby)){
        $sortby = explode(',',$sortby);
    }

    uasort($arr, function ($a, $b) use (&$arr, $sortby) {
         for ($i = 0; $i < count($sortby); $i++) {

            $key = $sortby[$i];

            $d = 1;
            if (substr($key,0,1) == '-') {
                $d = -1;
                $key = substr($key,1);
            }
            $is_numeric = false;
            if (substr($key,0,1) == '#') {
                //numeric
                $is_numeric = true;
                $key = substr($key,1);
            }

            if ($a[$key] == $b[$key]) {
                if (isset($sortby[$i + 1])) { //next sort
                    $arr = heuristModifierArraySortByFields($arr, array_slice($sortby, 1));
                } else {
                    return 0;
                }
            } else {
                if($is_numeric){
                    $res = $d * ($a[$key]-$b[$key]);
                }else{
                    $res = $d * strcasecmp($a[$key], $b[$key]);
                }
                return $res;
            }
        }
    });

    return $arr;
}

//do not remove - for backward capability
function smarty_function_progress(){
    return false;
}
