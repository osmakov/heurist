<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* exportRecordsRDF.php - class to export records as RDF with different serialization (XML, tripple, json)
*
* Controller is records_output
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

namespace hserv\records\export;
use hserv\records\export\ExportRecords;

define('DC_TITLE', 'dc:title');

/**
*
*  setSession - switch current datbase
*  output - main method
*
*/
class ExportRecordsRDF extends ExportRecords {

    private $graph;

    //indexes in defintions
    private $idx_rty_name;
    private $idx_rty_ccode;
    private $idx_rty_surl;
    private $idx_rty_dbid;

    private $idx_rst_name;
    private $idx_rst_surl;
    private $idx_rst_dbid;

    private $idx_dty_ccode;
    private $idx_dty_surl;

    private $idx_dtype;

    private $serial_format = null;
    private $dbid;

    private $include_definition_label = true;
    private $include_resource_rec_title = true;
    private $include_resource_term_label = true;
    private $include_resource_file_info = true;


protected function _outputPrepare($data, $params)
{
    $res = parent::_outputPrepare($data, $params);
    if($res){
        $this->serial_format = @$params['serial_format'];
        $this->dbid = $this->system->get_system('sys_dbRegisteredID');

        if(!defined('HEURIST_REF')){
            define('HEURIST_REF','https://heuristref.net/');
        }


        $ext_info = @$params['extinfo'];
        if($ext_info==null) {$ext_info = '0';}
        elseif($ext_info==='1') {$ext_info = '1111';}
        if(strlen($ext_info)<4){
            $ext_info = str_pad($ext_info,4,'0');
        }
        $this->include_definition_label = ($ext_info[0]==1);
        $this->include_resource_term_label = ($ext_info[1]==1);
        $this->include_resource_rec_title = ($ext_info[2]==1);
        $this->include_resource_file_info = ($ext_info[3]==1);

    }

    return $res;
}

//
//
//
protected function _outputHeader(){
     $this->graph = new \EasyRdf\Graph();

     \EasyRdf\RdfNamespace::set('xsd', 'http://www.w3.org/2001/XMLSchema#');
     \EasyRdf\RdfNamespace::set('base', HEURIST_REF);
     \EasyRdf\RdfNamespace::set('db', HEURIST_REF.'db/');
     \EasyRdf\RdfNamespace::set('dc', 'http://purl.org/dc/elements/1.1/');

    if(self::$defRecTypes==null) {
        self::$defRecTypes = dbs_GetRectypeStructures($this->system, null, 2);
    }
    if(self::$defDetailtypes==null){
        self::$defDetailtypes = dbs_GetDetailTypes($this->system, null, 2);
    }

    $this->idx_rty_name = self::$defRecTypes['typedefs']['commonNamesToIndex']['rty_Name'];
    $this->idx_rty_surl = self::$defRecTypes['typedefs']['commonNamesToIndex']['rty_ReferenceURL'];
    $this->idx_rty_ccode = self::$defRecTypes['typedefs']['commonNamesToIndex']['rty_ConceptID'];
    $this->idx_rty_dbid = self::$defRecTypes['typedefs']['commonNamesToIndex']['rty_OriginatingDBID'];

    $this->idx_rst_name = self::$defRecTypes['typedefs']['commonNamesToIndex']['rst_DiaplayName'];
    $this->idx_rst_surl = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_SemanticReferenceURL'];
    $this->idx_rst_dbid = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_OriginatingDBID'];

    $this->idx_dtype = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Type'];
    $this->idx_dty_surl = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_SemanticReferenceURL'];
    $this->idx_dty_ccode = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_ConceptID'];

}

//
//
//
private function initializeTerms(){
    if(self::$defTerms==null) {
        self::$defTerms = dbs_GetTerms($this->system);
        self::$defTerms = new \DbsTerms($this->system, self::$defTerms);
    }
}

//
// 1. returns pair: namespace and type
// 2. fill array of namespaces
// for example
// 1. https://www.ica.org/standards/RiC/ontology#Person  => rico:Person
// 2. rico => https://www.ica.org/standards/RiC/ontology#
//
private function _prepareURI($surl, $original_dbid=null){

    $ns = null;
    $heurist_schema = HEURIST_REF.'schema/';

    if($surl){

        //take first only
        if(strpos($surl,';')>0){
            $surl = explode(';',$surl);
            $surl = $surl[0];
        }

        //
        if(strpos($surl,'#')>0){
            list($uri, $type) = explode('#',$surl);
        }elseif(strpos($surl,'http://')===0 || strpos($surl,'https://')===0){
            $parts = explode('/',$surl);
            $type = array_pop($parts);
            if($type=='') {$type = array_pop($parts);}
            $uri = implode('/',$parts).'/';
        }else{
            //dty-2-1
            //familyName
            $type = $surl;
            if(strpos($type,'rty-')===0 || strpos($type,'dty-')===0 || strpos($type,'trm-')===0){
                $uri = $heurist_schema;
            }elseif($original_dbid!=null){

                if(is_string($original_dbid) && strpos($original_dbid,'-')>0){
                    list($dbid, $id) = explode('-', $original_dbid);
                }else{
                    $dbid = $original_dbid;
                }

                if(intval($dbid)>0){
                    $uri = $heurist_schema;
                    $uri .= $original_dbid.'/';
                }

            }
        }

        if($type){

            $ns = @$this->namespaces[$uri];
            if($ns==null){
                //https://www.ica.org/standards/RiC/
                if(strpos($uri, $heurist_schema)===0){

                    $ns = 'heurist';

                    $parts = explode('/',$uri);
                    $dbid = array_pop($parts);
                    if($dbid=='') {$dbid = array_pop($parts);}
                    if(intval($dbid)>0){
                        $ns .= $dbid;
                    }

                }elseif($uri=='https://www.ica.org/standards/RiC/ontology'){

                    $ns = 'rico';
                }elseif($uri=='http://www.w3.org/2000/01/rdf-schema'){
                    $ns = 'rdfs';
                }elseif($uri=='https://www.omg.org/spec/LCC/Languages/ISO639-2-LanguageCodes'){

                    $ns = 'lcc-639-2';

                }elseif($uri=='http://xmlns.com/foaf/0.1/'){

                    $ns = 'foaf';

                }else {


                    /*  http://xmlns.com/foaf/0.1/familyName

                    }elseif($uri=='http://dbpedia.org/resource/Category:'){
                    $ns = 'dbc';
                    }elseif($uri=='http://dbpedia.org/resource/'){
                    $ns = 'dbpedia';

                    }elseif($uri=='http://dbpedia.org/resource/'){
                    $ns = 'dbo';

                    }elseif($uri=='http://dbpedia.org/property/'){
                    $ns = 'dbp';*/
                }

                if($ns!=null){
                    $this->namespaces[$uri] = $ns;
                    if(substr($uri,-1)!='/'){
                        $uri = $uri.'#';
                    }
                    \EasyRdf\RdfNamespace::set($ns, $uri);
                }else{
                    if($uri && substr($uri,-1)=='/'){
                        return $uri.$type;
                    }
                }
            }
        }
    }
    if($ns!=null){
        return $ns.':'.$type;
    }else{
        return null;
    }

}

//
//
//
protected function _outputRecord($record){

    $recID = intval($record['rec_ID']);
    $rty_ID = intval($record['rec_RecTypeID']);

    $type = $this->_prepareURI(self::$defRecTypes['typedefs'][$rty_ID]['commonFields'][$this->idx_rty_surl],
                               self::$defRecTypes['typedefs'][$rty_ID]['commonFields'][$this->idx_rty_dbid] );

    if($type==null && self::$defRecTypes['typedefs'][$rty_ID]['commonFields'][$this->idx_rty_ccode]){
        $type = $this->_prepareURI('rty-'.self::$defRecTypes['typedefs'][$rty_ID]['commonFields'][$this->idx_rty_ccode]);
    }

    if($type==null){
        $type = 'rdf:Description';
    }

    if($type){



        //https://www.ica.org/standards/RiC/ontology#Person

        //$uri = HEURIST_BASE_URL_PRO.'api/'.$this->system->dbname().'/view/'.$recID;
        $uri = HEURIST_REF.'db/record/'.$this->dbid.'-'.$recID; //new


        $me = $this->graph->resource($uri, $type);

        $this->_setResourceProps($record, $me);

    }

    return true;

}

//
//
//
protected function _outputFooter(){

    if(!$this->graph->isEmpty()){
/*
        foreach (\EasyRdf\Format::getFormats() as $f) {
            if ($f->getSerialiserClass()) {
    error_log($f.', '.$f->getName().', '.$f->getLabel());
            }
        }
*/
//ntriples
//turtle
//rdfxml
//json, jsonld - Please install "ml/json-ld" dependency to use JSON-LD serialisation
//php
//svg -
//png -

        if($this->serial_format == null || !in_array($this->serial_format,array('rdfxml','json','ntriples','turtle'))){
            $this->serial_format = 'rdfxml';
        }

        $data = $this->graph->serialise( $this->serial_format );
        fwrite($this->fd, $data);
    }

}


//
// convert heurist record to more interpretable format
//
// $extended_mode = 0 - as is, 1 - details in format {dty_ID: val: }, 2 - with concept codes and names/labels
//
private function _setResourceProps($record, &$resource){

    $rec_ID = $record['rec_ID'];
    $rty_ID = $record['rec_RecTypeID'];
    $rec_Title = $record['rec_Title'];

    if($this->include_definition_label){
        // record type label
        $resource->set('rdfs:label', self::$defRecTypes['typedefs'][$rty_ID]['commonFields'][$this->idx_rty_name]);
    }


    // label or name attribute
    //$field_surl = $this->_prepareURI('http://www.w3.org/2000/01/rdf-schema#name');//or label ?
    $resource->set(DC_TITLE, $rec_Title);

    //convert details to attributes
    foreach ($record['details'] as $dty_ID=>$field_details) {

        $field_URI = $this->_getFieldURI($rty_ID, $dty_ID);

        if($field_URI==null) {continue;} //sematic url is not defined

        $field_type = self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$this->idx_dtype];

        foreach($field_details as $dtl_ID=>$value){ //for detail multivalues

            if(is_array($value)){ //geo,file,resource

                if(@$value['file']){
                    //remove some fields
                    $fileinfo = $value['file'];

                    $file_resource_uri = HEURIST_REF.'db/file/'.$this->dbid.'-'.$fileinfo['ulf_ObfuscatedFileID'];

                    $value = $this->graph->resource($file_resource_uri);//create new or find resource
                    if($this->include_resource_file_info){
                        if(@$fileinfo['ulf_OrigFileName']){
                            $skip_file = strpos(@$fileinfo['ulf_OrigFileName'], ULF_REMOTE) === 0 || // skip if not local file
                                         strpos(@$fileinfo['ulf_OrigFileName'], ULF_IIIF) === 0 ||
                                         strpos(@$fileinfo['ulf_OrigFileName'], ULF_TILED_IMAGE) === 0;
                            if(!$skip_file){
                                $value->set(DC_TITLE, $fileinfo['ulf_OrigFileName']);
                            }
                        }
                        if(@$fileinfo['ulf_Description']){
                            $value->set('dc:description', $fileinfo['ulf_Description']);
                        }
                    }

                }else{

                    continue;
                }
                /*
                elseif(@$value['id']){ //resource
                    $val = $value['id'];
                }elseif(@$value['geo']){

                    if($this->find_by_geofields==null || in_array($dty_ID, $this->find_by_geofields)){

                        $wkt = $value['geo']['wkt'];
                        try{
                            $json = self::_getJsonFromWkt($wkt, $simplify);
                            if($json){
                               $geovalues[] = $json;
                               $geovalues_dty[] = $dty_ID;
                            }
                        }catch(Exception $e){
                        }

                        $val = $wkt;

                    }

                    continue;  //it will be included into separate geometry property
                }
                */
            }
            else{
                $lang = null;
                $dtype = null;

                if($field_type=='enum'){
                    $this->initializeTerms();

                    $trm_ID = $value;
                    $trm_Label = self::$defTerms->getTermLabel($trm_ID);
                    $trm_ConceptCode = self::$defTerms->getTermConceptID($trm_ID);
                    $term_resource_uri = $this->_prepareURI(self::$defTerms->getTermReferenceURL($trm_ID));

                    if($term_resource_uri == null){
                        $term_resource_uri = HEURIST_REF.'db/term/'.$trm_ConceptCode;
                    }
                    if($term_resource_uri!=null){
                        $value = $this->graph->resource($term_resource_uri);//create new or find resource
                        if($this->include_resource_term_label){
                            $value->set(DC_TITLE, $trm_Label);
                        }

                        //works: $value->addLiteral('rdfs:name', $label);

                        //as separate resource to graph root
                        //$value->add($field_surl, $value);//add new resource
                    }else{
                        $value = $trm_Label;
                    }


                }elseif($field_type=='date' || $field_type=='year'){
                    //http://www.w3.org/2001/XMLSchema#date
                    $dtype = 'xsd:dateTime';
                    //$dtype = 'xsd:date';'xsd:gYear'
                }elseif($field_type=='float'){
                    $dtype = 'xsd:decimal';
                }elseif($field_type=='integer'){
                    $dtype = 'xsd:integer';
                }elseif($field_type=='boolean'){
                    $dtype = 'xsd:boolean';
                }elseif($field_type=='freetext' || $field_type=='blocktext'){
                    //detect language
                    // it does not work in EasyRdf
                    /*
                    list($lang, $value) = extractLangPrefix($value);
                    $dtype = 'xsd:string';
                    if($lang!=null) {
                        $lang = 'fr';
                    }
                    */
                }

                if($dtype!=null){
                    $value = new \EasyRdf\Literal($value, $lang, $dtype);
                }

                $val = $value;
            }

            $resource->add($field_URI, $value);

/*
            if(!isset($val)) {$val = '';}

            $val = array('ID'=>$dty_ID,'value'=>$val);

            if(is_array($value) && @$value['id']>0){ //resource
                $val['resourceTitle']     = @$value['title'];
                $val['resourceRecTypeID'] = @$value['type'];
            }

            if($extended){
                //It needs to include the field name and term label and term standard code.
                if($field_type=='enum' || $field_type=='relationtype'){
                    $val['termLabel'] = self::$defTerms->getTermLabel($val['value'], true);
                    $term_code  = self::$defTerms->getTermCode($val['value']);
                    if($term_code) {$val['termCode'] = $term_code; }
                }

                //take name for rt structure
                if(@self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dty_ID] && $idx_name>=0){
                    $val['fieldName'] = self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dty_ID][$idx_name];
                }else{
                    //non standard field
                    $val['fieldName'] = self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_dname];
                }

                $val['fieldType'] = $field_type;
                $val['conceptID'] = self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_ccode];
            }

            $res['properties']['details'][] = $val;
*/
        } //for detail multivalues
    } //for all details of record


    //all records related to original set of records are already found in outputPrepare (with recordSearchRelatedIds)
    //now we need to detect only relation for current record
    $links = recordSearchRelated($this->system, $rec_ID, 0, true);

    if($links['status']==HEURIST_OK){
        if(@$links['data']['direct']){
            $this->_composeLinks($resource, $links['data']['direct'], 'direct', $rty_ID, $links['data']['headers']);
        }
        if(@$links['data']['reverse']){
            $this->_composeLinks($resource, $links['data']['reverse'], 'reverse', $rty_ID, $links['data']['headers']);
        }
    }

}

//
//
//
private function _composeLinks(&$resource, $relations, $direction, $rty_ID, $headers){

    /*
                $relation->recID
                $relation->targetID
                $relation->trmID  rl_RelationTypeID
                $relation->dtID  rl_DetailTypeID
                $relation->relationID  rl_RelationID

                if($relation->relationID>0) {

                    $vals = mysql__select_row($mysqli, $query_rel.$relation->relationID);
                    if($vals!=null){
                        $relation->dtl_StartDate = $vals[1];
                        $relation->dtl_EndDate = $vals[2];
                    }
                }
    */
    foreach($relations as $relation){

        //relationship
        if($relation->relationID>0) {

            $this->initializeTerms();

            //link
            $trm_ID = $relation->trmID;

            if($direction=='reverse'){
                  $rev_trm_ID = self::$defTerms->getTermField($term_id, 'trm_InverseTermID');
                  if($rev_trm_ID>0){
                      $trm_ID = $rev_trm_ID;
                  }
            }
            $relation_uri = $this->_getRelationURI($trm_ID);

        }else{
            //link
            $dty_ID = $relation->dtID;
            $relation_uri = $this->_getFieldURI($rty_ID, $dty_ID);
        }

        /*
        if($field_surl==null){
            //$field_surl = $this->_prepareURI('https://www.ica.org/standards/RiC/ontology#Relation');
            if($direction=='direct'){
                $field_surl = $this->_prepareURI('https://www.ica.org/standards/RiC/ontology#isSourceOf');
            }else{
                $field_surl = $this->_prepareURI('https://www.ica.org/standards/RiC/ontology#hasSource');
            }
        }
        */

        if($relation_uri!=null){

            if($direction=='direct'){
                $related_rec_ID = $relation->targetID;
            }else{
                $related_rec_ID = $relation->sourceID;
            }

            //old $uri = HEURIST_BASE_URL_PRO.'api/'.$this->system->dbname().'/view/'.$related_rec_ID;
            $uri = HEURIST_REF.'db/record/'.$this->dbid.'-'.$related_rec_ID; //new

            $rec_resource = $this->graph->resource($uri);
            if($this->include_resource_rec_title && @$headers[$related_rec_ID][0]){
                $rec_resource->set(DC_TITLE, $headers[$related_rec_ID][0]);
            }

            //$resource->add($field_surl, $this->graph->resource($uri));
            $resource->add($relation_uri, $rec_resource);

        }
    }

}

//
//
//
private function _getFieldURI($rty_ID, $dty_ID){

    $field_URI = $this->_prepareURI(self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dty_ID][$this->idx_rst_surl],
                                    self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dty_ID][$this->idx_rst_dbid]);
    if($field_URI==null){
        $field_URI = $this->_prepareURI(self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$this->idx_dty_surl],
                                         self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$this->idx_dty_ccode]);
    }
    if($field_URI==null && self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$this->idx_dty_ccode]){
        $field_URI = $this->_prepareURI('dty-'.self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$this->idx_dty_ccode]);
        //$field_surl .= '-'.preg_replace('/\s+/', '', self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$this->idx_dty_name]);
    }

    return $field_URI;
}

//
//
//
private function _getRelationURI($trm_ID){

    //$term = self::$defTerms->getTerm($trm_ID);

    $term_URI = $this->_prepareURI(self::$defTerms->getTermReferenceURL($trm_ID),
                                   self::$defTerms->getTermField($trm_ID, 'trm_OriginatingDBID'));

    if($term_URI == null){
        $trm_ConceptCode = self::$defTerms->getTermConceptID($trm_ID);
        $trm_Label = self::$defTerms->getTermLabel($trm_ID);
        if($term_URI==null && $trm_ConceptCode){
            $field_URI = $this->_prepareURI('trm-'.$trm_ConceptCode);
            //$term_URI = 'heurist:trm-'.$trm_ConceptCode.'-'.preg_replace('/\s+/', '', $trm_Label);
        }
    }

    return $term_URI;

}


} //end class
?>