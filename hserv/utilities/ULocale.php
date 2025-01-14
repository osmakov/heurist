<?php

    /**
    * Localization utilities
    *
    * getLangCode3 - validates lang code and returns upper case 3 letters code
    * extractLangPrefix - splits and extract language code and value from string code:value
    * getTranslation - for smarty modifier
    * getCurrentTranslation - returns translated value for multivalue field
    * getExternalTranslation - translates given string to traget language via Deepl's API
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

    //
    //
    //
    function initLangCodes(){
        global $glb_lang_codes, $glb_lang_codes_index;

        if(!isset($glb_lang_codes)){
            $glb_lang_codes = json_decode(file_get_contents(HEURIST_DIR.'hclient/assets/language-codes-active-list.json'),true);
            foreach($glb_lang_codes as $codes){
                $glb_lang_codes_index[strtoupper($codes['a3'])] = strtoupper($codes['a2']);
            }
        }
    }

    //
    // get 3 letters ISO code
    //
    function getLangCode3($lang){
        global $glb_lang_codes, $glb_lang_codes_index;

        $res = null;

        if ($lang) {

            initLangCodes();

            $lang = strtoupper($lang);
            if(strlen($lang)==3){
                $lang = strtoupper($lang);
                if(@$glb_lang_codes_index[$lang]!=null){
                    $res = $lang;
                }
            }else{
                $res = array_search($lang, $glb_lang_codes_index);
            }

            /*
            $key = (strlen($lang)==2)?'a2':'a3';
            foreach($glb_lang_codes as $codes){
                if(strcasecmp($codes[$key], $lang)===0){
                    $res = $codes['a3'];
                    break;
                }
            }*/

        }

        return $res;
    }

    //
    // get 2 letters ISO code
    //
    function getLangCode2($lang){

        global $glb_lang_codes, $glb_lang_codes_index;

        $res = null;

        if ($lang) {

            initLangCodes();

            $lang = strtoupper($lang);
            if(strlen($lang)==3){
                $lang = strtoupper($lang);
                if(@$glb_lang_codes_index[$lang]!=null){
                    $res = $glb_lang_codes_index[$lang];
                }
            }else{
                $res = array_search($lang, $glb_lang_codes_index) === false ? null : $lang;
            }
        }

        return $res;
    }

    //
    //  splits and extract language code and value from string code:value
    //  if $val is 2 chars code ISO639-1 - it will be converted to 3 chars ISO639-2
    //
    function extractLangPrefix($val){

        //global $glb_lang_codes, $common_languages_for_translation;
        $lang = null;

        if(is_string($val) && mb_strlen($val)>4){

            $val = trim($val);
            $val_orig = $val;
            $tag_to_remove = null;
            if(strpos($val,'<p')===0 || strpos($val,'<span')===0){
                /*
                $document = DOMDocument::loadHTML( $val );
                $childToRemove = $document->getElementsByTagName('p')->item(0);
                $childToRemove->parentNode->removeChild($childToRemove);
                $val = $document->saveHTML();
                */
                $tag_to_remove = strpos($val,'<p')===0?'</p>':'</span>';
                $val = trim(strip_tags($val));
            }

            if(substr($val,0,2)=='*:'){
                $lang = 'ALL';
                $pos = 2;
            }else{

                if($val[2]==':'){
                    $lang = substr($val,0,2);
                    $pos = 3;
                }elseif($val[3]==':'){
                    $lang = substr($val,0,3);
                    $pos = 4;
                }

                if($lang){
                    $lang = getLangCode3($lang);//validate
                }
            }

            if($lang){ //lang detected

                //if (strcasecmp($lang,'ALL')===0 || in_array($lang, $common_languages_for_translation)){
                if($tag_to_remove == null){
                    $val = substr($val_orig, $pos);
                }else{
                    //remove first p or span
                    $val = trim(substr(strstr($val_orig, $tag_to_remove), strlen($tag_to_remove)));
                }

            }else{
                $val = $val_orig;
            }
        }

        return array($lang, $val);
    }

    //
    // For smarty modifier "translate"
    // $filed - label or desc - for terms
    //
    function getTranslation($input, $lang, $field=null){
        global $smarty;

        $res = null;
        $lang = getLangCode3($lang);

        //detect if it is usual record or term
        if(is_array($input) && (@$input['term'] || (is_array(@$input[0]) && @$input[0]['term']))){

            if($field==null) {$field = 'label';}

            $trm = @$input[0]?$input[0]:$input;

            if(isset($smarty)){

                //$heuristRec = @$smarty['tpl_vars']['heurist']['value'];

                $heuristRec = $smarty->getTemplateVars('heurist');
                if($heuristRec){
                    return $heuristRec->getTranslation('trm', $trm['id'], $field, $lang);
                }
            }
            return $trm[$field];
        }


        // this is record detail field;
        $res = getCurrentTranslation($input, $lang);

        $ret = ($res==null)?$input:$res;
        return $ret;
    }

    //
    // It returns translated value for multivalue field
    // if all values have language prefix (except default one)
    // $input - array of values
    //
    function getCurrentTranslation($input, $lang){

        $res = null;

        if(is_array($input)){

            $lang = getLangCode3($lang);
            $def = null;
            $fnd = null;
            $cnt = 0;
            //all values except one must be with lang: prefix
            foreach($input as $val){

                list($lang_, $val) = extractLangPrefix($val);

                if ($lang_!=null && $lang_==$lang){
                    $cnt++;
                    $fnd = $val;
                }elseif($lang_==null){
                    $def = $val;
                }else{
                    $cnt++;
                }

            } //foreach
            if($fnd && ($cnt>=count($input)-1)){
                $res = $fnd;
            }else{
                $res = $def;
            }

        }elseif(is_string($input)) {
            list($lang_, $res) = extractLangPrefix($input);//there is no localization
        }

        return $res;
    }

    /**
     * Translate given string to traget language via Deepl's API
     *  A valid Deepl API key needs to be assigned to the variable $accessToken_DeepLAPI within heuristConfigIni.php
     *
     * @param object $system - Heurist's initialised system object
     * @param string $string - String to be translated
     * @param string $target_language - AR2 or AR3 of language being translated to
     * @param string $source_language - AR2 or AR3 of language being translated from (if missing Deepl uses auto-detection)
     */
    function getExternalTranslation($system, $string, $target_language, $source_language = null){

        global $glb_lang_codes, $glb_lang_codes_index, $accessToken_DeepLAPI;

        initLangCodes();

        // Default list of languages - from https://www.deepl.com/docs-api/general/get-languages
        $def_languages = array('AR', 'BG', 'CS', 'DA', 'DE', 'EL', 'EN', 'ES', 'ET', 'FI',
                               'FR', 'HU', 'ID', 'IT', 'JA', 'KO', 'LT', 'LV', 'NB', 'NL',
                               'PL', 'PT', 'RO', 'RU', 'SK', 'SL', 'SV', 'TR', 'UK', 'ZH');

        // Retrieve from file, created by daily script
        $language_file = HEURIST_FILESTORE_ROOT . 'DEEPL_languages.json';
        $deepl_languages = array();

        if(file_exists($language_file)){
            $langs = file_get_contents($language_file);

            $langs = json_decode($langs, true);
            $deepl_languages = json_last_error() !== JSON_ERROR_NONE ? array() : $langs;

            $deepl_languages = !empty($langs) ? $langs : $def_languages;
        }

        if(empty($string) || empty($target_language)){

            $msg = 'Your request is missing ' . (empty($string) ? 'a value to translate' : 'the target language to translate to');

            $system->addError(HEURIST_INVALID_REQUEST, $msg);
            return false;
        }

        $url = '';
        $additional_headers = array();

        $useragent = 'Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.0.6) Gecko/2009011913 Firefox/3.0.6';

        $curl_handle = curl_init();

        curl_setopt($curl_handle, CURLOPT_COOKIEFILE, '/dev/null');
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);//return the output as a string from curl_exec
        curl_setopt($curl_handle, CURLOPT_NOBODY, 0);
        curl_setopt($curl_handle, CURLOPT_HEADER, 0);//don't include header in output
        curl_setopt($curl_handle, CURLOPT_FOLLOWLOCATION, 1);// follow server header redirects

        curl_setopt($curl_handle, CURLOPT_TIMEOUT, '30');// timeout after thirty seconds
        curl_setopt($curl_handle, CURLOPT_MAXREDIRS, 5);// no more than 5 redirections

        curl_setopt($curl_handle, CURLOPT_USERAGENT, $useragent);
        curl_setopt($curl_handle, CURLOPT_FAILONERROR, true);
        curl_setopt($curl_handle, CURLOPT_AUTOREFERER, true);

        // check if the proxy needs to be used, $httpProxyActive defined in heuristConfigIni.php
        $use_proxy = defined('HEURIST_HTTP_PROXY_ALWAYS_ACTIVE') && HEURIST_HTTP_PROXY_ALWAYS_ACTIVE && defined('HEURIST_HTTP_PROXY');

        if($use_proxy){

            curl_setopt($curl_handle, CURLOPT_PROXY, HEURIST_HTTP_PROXY);
            if(  defined('HEURIST_HTTP_PROXY_AUTH') ) {
                curl_setopt($curl_handle, CURLOPT_PROXYUSERPWD, HEURIST_HTTP_PROXY_AUTH);
            }
        }

        // Check auth key has been defined
        if(empty($accessToken_DeepLAPI)){
            $system->addError(HEURIST_ACTION_BLOCKED, 'Deepl API key has not been configured - please ask your system administrator to setup the translator key');
            return false;
        }

        // Handle target language
        if(strlen($target_language) == 3){ // get ar2
            $target_language = $glb_lang_codes_index[$target_language];
        }
        if(!in_array($target_language, $deepl_languages)){
            $system->addError(HEURIST_INVALID_REQUEST, 'The provided language is not supported by Deepl.<br>If you believe this is in error, please contact the Heurist team.');
            return false;
        }

        $is_xml = strpos($string, '<?xml') === 0;
        $handling_encoding = false;
        $handling_copyright = false;


        // Add no translate flags where necessary
        /**
         * &[a-zA-Z]; html entity
         * &#[0-9]; html code
         * &#x[a-fA-F0-9]; hex code
         */
        $regex_entities = '&(?:[a-zA-Z]{2,35}|#[0-9]{1,6}|#x[a-fA-F0-9]{1,6});?';
        $regex_less_than = '(?:<|&lt;)';
        $regex_great_than = '(?:>|&gt;)';
        $regex_quotes = '(?:\'|"|&quot;|&apos;)';

        $add_tags = function($matches) use ($is_xml) {
            $response = $is_xml ? "<notranslate>{$matches[0]}</notranslate>" : "<p translate='no'>{$matches[0]}</p>";
            return $response;
        };
        $remove_tags = function($matches){

            if(count($matches) == 1){
                return $matches[0];
            }

            return $matches[1];
        };

        $org_string = $string; // backup string before processing

        if(preg_match("/$regex_entities/", $string)){ // html encoded entities

            $string = mb_ereg_replace_callback($regex_entities, $add_tags, $string);

            $handling_encoding = true;

            $string = $string !== false ? $string : $org_string;
            $org_string = $string; // update backup string
        }

        if(preg_match("/©/", $string)){ // copyright symbol, sometimes gets removed by Deepl during translation

            $replacement = $is_xml ? '<notranslate>©</notranslate>' : '<p translate="no">©</p>';
            $string = mb_ereg_replace("©", $replacement, $string);

            $handling_copyright = true;

            $string = $string !== false ? $string : $org_string;
        }

        /**
         * free => api-free.deepl.com
         * pro => api.deepl.com
         */
        $url = 'https://api-free.deepl.com/v2/translate?text=' . urlencode($string) . '&target_lang=' . $target_language;

        // Handle source language
        if(!empty($source_language) && strlen($source_language) == 3){ // get ar2
            $source_language = $glb_lang_codes_index[$source_language];
        }

        if(!empty($source_language) && in_array($source_language, $deepl_languages)){
            $k = array_search($source_language, $deepl_languages);
            $url .= '&source_lang=' . $deepl_languages[$k];
        }

        if($is_xml){ // possible xml
            $url .= '&tag_handling=xml&ignore_tags=notranslate';
        }else{ // assume html
            $url .= '&tag_handling=html';
        }

        $additional_headers = array('Authorization: DeepL-Auth-Key ' . $accessToken_DeepLAPI);

        if(is_array($additional_headers) && !empty($additional_headers)){
            curl_setopt($curl_handle, CURLOPT_HTTPHEADER, $additional_headers);
        }

        curl_setopt($curl_handle, CURLOPT_URL, $url);
        $data = curl_exec($curl_handle);

        $error = curl_error($curl_handle);

        if($error){

            $hmsg = '';// Heurist's error message
            $herror = HEURIST_UNKNOWN_ERROR;
            $code = intval(curl_getinfo($curl_handle, CURLINFO_HTTP_CODE));

            switch ($code) {

                case 400:
                    $herror = HEURIST_INVALID_REQUEST;
                    $hmsg = 'Deepl was unable to complete this request.<br>'
                           .'Please make a bug report if this persists.';
                    break;

                case 403:
                    $herror = HEURIST_REQUEST_DENIED;
                    $hmsg = 'Heurist was unable to access Deepl.<br>'
                           .'This may be due to an error in handling or the necessary API key is missing.<br>'
                           .'Please contact your system administrator and ask them if the API key has been configured.';
                    break;

                case 404:
                case 504:
                    $herror = HEURIST_INVALID_REQUEST; //HEURIST_NOT_FOUND
                    $hmsg = 'Deepl encountered an error with locating the desired function.<br>'
                           .'Please make a bug report.';
                    break;

                case 429:
                case 529:
                    $herror = HEURIST_ACTION_BLOCKED;
                    $hmsg = 'Deepl is currently busy processing other requests.<br>'
                           .'Please re-try your request in a few minutes.<br>'
                           .'If this persists, please make a bug report.';
                    $error = '';
                    break;

                case 459:
                    $herror = HEURIST_ACTION_BLOCKED;
                    $hmsg = 'Heurist has exceeded it\'s quota with Deepl and will be unable to attempt automatic translations of your texts.<br>'
                           .'We apologise for the inconvenience.';
                    break;

                case 413:
                case 414:
                    $herror = HEURIST_ACTION_BLOCKED;
                    $hmsg = 'The request to Deepl\'s services was too large to process.<br>'
                           .'Please either:<br>'
                           .'Split the value into smaller parts and then re-combine then when you are finished, or '
                           .'Make a bug report including which record and field you were attempting to translate and into which language.';
                    break;

                default: // unknown error or no additional handling
                    $herror = HEURIST_REQUEST_DENIED; //HEURIST_UNKNOWN_ERROR
                    $hmsg = 'An unknown error occurred with Deepl\'s services.<br>'
                           .'Please re-try your request in a few minutes.<br>'
                           .'If this problem persists, please make a bug report.';
                    break;
            }

            $system->addError($herror, $hmsg, $error);

            return false;
        }

        $data = json_decode($data, true);
        if(json_last_error() !== JSON_ERROR_NONE || !is_array($data) || !array_key_exists('translations', $data)){
            $system->addError(HEURIST_ERROR, 'Deepl has responsed in an unknown format.<br>Please report this to the Heurist team.');
            return false;
        }

        $res = '';
        $translation = $data['translations'];
        if(is_array($translation) && !empty($translation)){
            $res = $translation[0]['text'];
        }

        // Remove notranslate tags
        $org_res = $res; // backup original result
        if($handling_encoding && !empty($res)){

            $match = $is_xml
                    ? "{$regex_less_than}notranslate{$regex_great_than}($regex_entities){$regex_less_than}\/notranslate{$regex_great_than}"
                    : "{$regex_less_than}p translate={$regex_quotes}no{$regex_quotes}{$regex_great_than}($regex_entities){$regex_less_than}\/p{$regex_great_than}";

            $res = mb_ereg_replace_callback($match, $remove_tags, $res);

            $res = $res !== false ? $res : $org_res;
            $org_res = $res; // update backup string
        }

        if($handling_copyright && !empty($res)){

            $match = $is_xml
                    ? "{$regex_less_than}notranslate{$regex_great_than}©{$regex_less_than}\/notranslate{$regex_great_than}"
                    : "{$regex_less_than}p translate={$regex_quotes}no{$regex_quotes}{$regex_great_than}©{$regex_less_than}\/p{$regex_great_than}";

            mb_ereg_replace($match, "©", $res);

            $res = $res !== false ? $res : $org_res;
        }

        return $res;
    }
