<?php
   /**
    * Manipulations with defTerms
    *
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

    /*
    * Public methods
    *  findTermByConceptCode
    *  getTermsFromFormat
    *  getTermLabel
    *  getTermField
    *  getTermReferenceURL
    *  getTermCode
    *  getTerm
    *  getTermByLabel
    *  getVocabs - for specified domain
    *  getSiblings
    *  treeData($parent_id, $mode) - returns tree of flat array of children ids (all levels)
    *  addNewTerm
    *  addNewTermRef
    *  addChild - private
    *  getTopMostTermParent
    *  doDisambiguateTerms
    *  getSameLevelLabelsAndCodes
    */
use hserv\utilities\USanitize;

class DbsTerms
{
    protected $system;

    /*
      loaded terms
    */
    protected $data;

    //
    // constructor - load configuration from json file
    //
    public function __construct( $system, $data ) {
       $this->system = $system;
       $this->data = $data;
    }

    //
    // assign terms
    //
    public function setTerms($data){
        $this->data = $data;
    }

    //
    //
    //
    public function findTermByConceptCode($ccode, $domain=null){

        if($domain==null){
            $term_id = $this->findTermByConceptCode($ccode, 'enum');
            if($term_id==null){
                $term_id = $this->findTermByConceptCode($ccode, 'relation');
            }
            return $term_id;
        }

        $idx_ccode = intval($this->data['fieldNamesToIndex']["trm_ConceptID"]);

        foreach ($this->data['termsByDomainLookup'][$domain] as $term_id => $def) {
            if(is_numeric($term_id) && $def[$idx_ccode]==$ccode){
                return $term_id;
            }
        }
        return null;
    }

    //
    // parse term string (as json or comma separated) and validate it
    // if domain not defined - no validation
    //
    public function getTermsFromFormat($formattedStringOfTermIDs, $domain) {


        $validTermIDs = array();
        if (!$formattedStringOfTermIDs || $formattedStringOfTermIDs == "") {
            return $validTermIDs;
        }

        if (strpos($formattedStringOfTermIDs,"{")!== false) {
            $temp = preg_replace("/[\{\}\",]/","",$formattedStringOfTermIDs);
            if (strrpos($temp,":") == strlen($temp)-1) {
                $temp = substr($temp,0, strlen($temp)-1);
            }
            $termIDs = explode(":",$temp);
        } else {
            $temp = preg_replace("/[\[\]\"]/","",$formattedStringOfTermIDs);
            $termIDs = explode(",",$temp);
        }

        // Validate termIDs
        if($domain!=null){
            $trmLookup2 = $this->data['termsByDomainLookup'][$domain];

            foreach ($termIDs as $trmID) {
                // check that the term valid
                if ( $trmID && array_key_exists($trmID, $trmLookup2) && !in_array($trmID, $validTermIDs)){ // valid trm ID
                    array_push($validTermIDs,$trmID);
                }
            }
        }else{
            $validTermIDs = $termIDs; //no validation - take all
        }
        return $validTermIDs;
    }

    //
    //
    //
    public function getTermLabel($term_id, $with_hierarchy=false) {

        $term = $this->getTerm($term_id);
        if(!$term){
            return '';
        }

            $idx_term_label = $this->data['fieldNamesToIndex']['trm_Label'];

            if(!$with_hierarchy){
                return @$term[$idx_term_label]?$term[$idx_term_label]:'';
            }

                $labels = '';
                $idx_term_parent = $this->data['fieldNamesToIndex']['trm_ParentTermID'];
                $idx_term_domain = $this->data['fieldNamesToIndex']['trm_Domain'];


                $labels = array();
                array_push($labels, $term[$idx_term_label]);

                while ( $term[$idx_term_parent]>0 ) {
                    $term = $this->getTerm($term[$idx_term_parent]);

                    if($term[$idx_term_parent]>0){
                        array_unshift($labels, $term[$idx_term_label]);
                    }else{
                        break; //ignore vocabulary
                    }
                }
                return implode('.',$labels);
    }

    //
    //
    //
    public function getTermField($term_id, $field_name) {
        $term = $this->getTerm($term_id);
        if($term){
            $idx_term_code = $this->data['fieldNamesToIndex'][$field_name];
            return @$term[$idx_term_code]?$term[$idx_term_code]:'';
        }else{
            return '';
        }
    }


    //
    //
    //
    public function getTermCode($term_id) {
        return $this->getTermField($term_id, 'trm_Code');
    }

    //
    //
    //
    public function getTermConceptID($term_id) {
        return $this->getTermField($term_id, 'trm_ConceptID');
    }

    //
    //
    //
    public function getTermReferenceURL($term_id) {
        return $this->getTermField($term_id, 'trm_SemanticReferenceURL');
    }

    //
    //
    public function getTerm($term_id, $domain='enum') {
        $term = null;

        if(@$this->data['termsByDomainLookup'][$domain][$term_id]!=null){
            $term = $this->data['termsByDomainLookup'][$domain][$term_id];
        }else{
            //search in other domain too
            $term = @$this->data['termsByDomainLookup'][$domain=='enum'?'relation':'enum'][$term_id];
        }
        return $term;
    }

    //
    //
    //
    public function getTermByLabel($vocab_id, $label){

        $all_terms = $this->treeData($vocab_id, 3);

        $label = trim(mb_strtolower($label));

        foreach($all_terms as $trm_id){

            $label2 = mb_strtolower($this->getTermLabel($trm_id));

            if($label2==$label){
                return $trm_id;
            }
        }
        return null;
    }

    //
    // get all vocabularies OR for given domain
    //
    public function getVocabs($domain){
        return array_keys(@$this->data['treesByDomain'][$domain]);
    }

    //
    // NOT USED
    //
    public function getSiblings($term_id, $domain) {

        $idx_term_parent = $this->data['fieldNamesToIndex']['trm_ParentTermID'];
        $term = $this->getTerm($term_id, $domain);

        return $this->treeData($term[$idx_term_parent], 3);
    }


    // $parent_id -  parent term
    // mode - 1, tree - returns treedata for fancytree
    //        3, set  - array of ids
    //        4, labels - flat array of labels in lower case
    //
    public function treeData($parent_id, $mode):array{

        if($mode=='set'){
            $mode = 3;
        }elseif($mode=='tree'){
            $mode = 1;
        }elseif($mode=='labels'){
            $mode = 4;
        }


        if($mode==1){
            $res = array($parent_id=>array());
        }else{
            $res = array();
        }

        if($parent_id=='relation' || $parent_id=='enum'){
            //find all vocabulary with domain "relation"
            $vocab_ids = $this->getVocabs($parent_id);
            foreach($vocab_ids as $trm_ID){
                $res2 = $this->treeData($trm_ID, $mode);
                $res = array_merge($res,$res2);
            }
        }else{

            $children = @$this->data['trm_Links'][$parent_id];
            if(!isEmptyArray($children)){

                foreach($children as $trm_ID){

                    if($trm_ID==$parent_id){
                        USanitize::errorLog('!!!!Database '.HEURIST_DBNAME
                            .' Recursive tree for term '.$trm_ID.' parent '.$parent_id);
                        continue;
                    }

                    if($mode==1){ //tree
                        $res[$parent_id][$trm_ID] = array();

                    }elseif($mode==3){
                        if(in_array($trm_ID, $res)){ //already in set
                            USanitize::errorLog('!!!!Database '.HEURIST_DBNAME
                                .' Recursive tree or duplication for term '.$trm_ID.' parent '.$parent_id);
                            continue;
                        }else{
                            array_push($res, $trm_ID);
                        }
                    }else{
                        array_push($res, strtolower($this->getTermLabel($trm_ID)));
                    }

                    $res2 = $this->treeData($trm_ID, $mode);
                    if(!isEmptyArray($res2)){
                        if($mode==1){
                            //tree
                            $res[$trm_ID] = $res2;
                        }else{
                            //flat array
                            $res = array_merge($res,$res2);
                        }
                    }
                }
            }
        }
        return $res;
    }

    //
    // get all labels and codes of children for given parent term
    //
    public function getSameLevelLabelsAndCodes($parent_id, $domain){

        $lvl_src = array('code'=>array(),'label'=>array());

        if($parent_id>0){
            $children = $this->treeData($parent_id, 3);//ids
            if(!empty($children)){
                $idx_code = intval($this->data['fieldNamesToIndex']["trm_Code"]);
                $idx_label = intval($this->data['fieldNamesToIndex']["trm_Label"]);

                foreach($children as $trmId){
                    if(@$this->data['termsByDomainLookup'][$domain][$trmId]){
                        $code = (trim($this->data['termsByDomainLookup'][$domain][$trmId][$idx_code]));//removeLastNum
                        $label = (trim($this->data['termsByDomainLookup'][$domain][$trmId][$idx_label]));//removeLastNum
                        $lvl_src['code'][] = $code;
                        $lvl_src['label'][] = $label;
                    }
                }
            }
        }

        return $lvl_src;
    }

    //
    //
    //
    public function isTermLinked($parent_id, $term_id){

        if(@$this->data['trm_Links'][$parent_id]){
            return in_array($term_id, $this->data['trm_Links'][$parent_id] );
        }
        return false;
    }

    //
    //
    //
    public function addNewTermRef($parent_id, $new_term_id){

        if(@$this->data['trm_Links'][$parent_id]){

            if( !in_array($new_term_id, $this->data['trm_Links'][$parent_id] )){
                $this->data['trm_Links'][$parent_id][] = $new_term_id;
            }
        }else{
            $this->data['trm_Links'][$parent_id] = array($new_term_id);
        }
    }

    //
    //
    //
    public function addNewTerm($new_term_id, $term_to_add){

        $idx_term_parent = $this->data['fieldNamesToIndex']['trm_ParentTermID'];
        $idx_term_domain = $this->data['fieldNamesToIndex']['trm_Domain'];

        $domain = $term_to_add[$idx_term_domain];
        $parent_id = $term_to_add[$idx_term_parent];

        $this->data['termsByDomainLookup'][$domain][$new_term_id] = $term_to_add;
        $this->addChild($this->data['treesByDomain'][$domain], $parent_id, $new_term_id);
        $this->addNewTermRef($parent_id, $new_term_id);

    }

    private function addChild(&$lvl, $parent_id, $new_term_id) {

        if($parent_id>0){

            foreach($lvl as $trmId=>$children){
                if($trmId==$parent_id){

                    if(!is_array(@$lvl[$trmId])) {$lvl[$trmId] = array();}
                    $lvl[$trmId][$new_term_id] = array();

                    break;

                }elseif(!isEmptyArray($children)){
                    $this->addChild($lvl[$trmId], $parent_id, $new_term_id);
                }
            }

        }else{
            //vocabulary
            if(!is_array($lvl)) {$lvl = array();}
            $lvl[$new_term_id] = array();
        }
    }


    //
    //  Find vocabulary ID
    //
    public function getTopMostTermParent($term_id, $domain, $topmost=null) {

        if(is_array($domain)){
            $lvl = $domain;
        }else{
            $lvl = $this->data['treesByDomain'][$domain];
        }
        foreach($lvl as $sub_term_id=>$children){

            if($sub_term_id == $term_id){
                return $topmost?$topmost:$term_id;
            }elseif( !isEmptyArray($children) ) {

                $res = $this->getTopMostTermParent($term_id, $children, $topmost?$topmost:$sub_term_id );
                if($res) {return $res;}
            }
        }
        return null; //not found
    }



    // Disambiguate elements (including terms at the same level of a vocabulary) which have the same label but
    // different concept IDs, by adding 1, 2, 3 etc. to the end of the label.
    //
    // $lvl_src - level to search
    // $idx - field index to check
    // return new term name with index
    //
    public function doDisambiguateTerms($term_import, $idx){

        if(!$term_import || $term_import=="") {return $term_import;}

        $lvl_values = array();

        $domain = 'enum';
        $lvl_src = $this->data['treesByDomain'][$domain];

        if(is_array($lvl_src)){
            foreach($lvl_src as $trmId=>$children){
                $lvl_values[] = trim($this->data['termsByDomainLookup'][$domain][$trmId][$idx]);
            }
        }

        $domain = 'relation';
        $lvl_src = $this->data['treesByDomain'][$domain];

        if(is_array($lvl_src)){
            foreach($lvl_src as $trmId=>$children){
                $lvl_values[] = trim($this->data['termsByDomainLookup'][$domain][$trmId][$idx]);
            }
        }

        return $this->doDisambiguateTerms2($term_import, $lvl_values);
    }

    /**
    * Avoid the same labels and codes on the same level
    *
    * @param mixed $term_value - label or code
    * @param mixed $same_level_values - to compare with
    */
    public function doDisambiguateTerms2($term_value, $same_level_values){

        if(!$term_value || $term_value=="") {return $term_value;}
/*
        $name = removeLastNum(trim($term_value));
        $found = 0;

        if(!empty($same_level_values))
        foreach ($same_level_values as $value){
                $name1 = removeLastNum(trim($value));
                if(strcasecmp($name, $name1)==0){
                    $found++;
                }
        }
        if($found>0){
                $term_value = $name." ".($found+1);
        }
*/
        $name = removeLastNum(trim($term_value));
        $found = 1;

        while (in_array($term_value, $same_level_values)){
            $term_value = $name.' '.$found;
            $found++;
        }


        return $term_value;
    }

}
?>
