<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* exportRecordsGEOJSON.php - class to export records as GeoJson
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
use hserv\structure\ConceptCode;

/**
*
*  setSession - switch current datbase
*  output - main method
*
*/
class ExportRecordsGEOJSON extends ExportRecords {

    private $is_leaflet = false;

    private $find_geo_by_pointer_rty = false;
    private $find_geo_by_pointer_dty = null;
    private $find_by_geofields = null;
    private $search_all_geofields = true;

    // variables for leaflet
    private $geojson_ids = array();
    private $geojson_dty_ids = array(); //unique list of all geofields
    private $geojson_rty_ids = array();
    private $timeline_dty_ids = array(); //unique list of all date fields

    private $timeline_data = array();
    private $layers_record_ids = array(); //list of ids RT_MAP_LAYER if this is search for layers in clearinghouse

    private $simplify_wkt = true;
    private $detail_mode = 0;
    private $separate_entity = false;

//
//
//
protected function _outputPrepare($data, $params){

    $res = parent::_outputPrepare($data, $params);
    if($res){

        $this->search_all_geofields = true;

        $this->is_leaflet = @$params['leaflet'];

        $this->simplify_wkt = intval(@$params['simplify'])==1;
        $this->detail_mode = $this->is_leaflet ?0:intval(@$params['detail_mode']);
        $this->separate_entity = $this->is_leaflet && (intval(@$params['separate'])==1);


        $this->find_geo_by_pointer_dty = null;
        $this->find_by_geofields = null;

        $this->find_geo_by_pointer_rty =  @$params['geofields'] ||
               ((@$params['suppress_linked_places']!=1)
               && ($this->system->user_GetPreference('deriveMapLocation', 1)==1));

        if($this->find_geo_by_pointer_rty){ //true

            //list of rectypes that are sources for geo location
            $rectypes_as_place = $this->system->settings->get('sys_TreatAsPlaceRefForMapping');
            if($rectypes_as_place){
                $rectypes_as_place = prepareIds($rectypes_as_place);
            }else {
                $rectypes_as_place = array();
            }
            //Place always in this array
            if($this->system->defineConstant('RT_PLACE')){
                if(!in_array(RT_PLACE, $rectypes_as_place)){
                    array_push($rectypes_as_place, RT_PLACE);
                }
            }
            //list of record types that are considered as Places with geo field
            $this->find_geo_by_pointer_rty = $rectypes_as_place;

            if($this->is_leaflet){
                $this->search_all_geofields = false;
                $_geofields = @$params['geofields'];
                $this->find_geo_by_pointer_dty = array();
                $this->find_by_geofields = array();

                if($_geofields){
                    if(is_String($_geofields)){
                        $_geofields = explode(',', $_geofields);
                    }
                    if(!isEmptyArray($_geofields)){

                        foreach($_geofields as $idx=>$code){
                            if($code=='all'){
                                //search all geofields
                                $this->search_all_geofields = true;
                            }elseif(is_array($code)){
                                if(!@$code['q']){
                                    array_push($this->find_by_geofields,$code['id']);
                                }else{
                                    array_push($this->find_by_geofields,$code);//with query to linked record
                                }

                            }else{
                                $dty_ID = ConceptCode::getDetailTypeLocalID($code);
                                if($dty_ID>0){
                                    array_push($this->find_geo_by_pointer_dty, $dty_ID);
                                }
                            }
                        }
                    }
                }


                if(isEmptyArray($this->find_geo_by_pointer_dty)){
                    $this->find_geo_by_pointer_dty = null;
                }
                if(isEmptyArray($this->find_by_geofields)){
                    $this->find_by_geofields = null;
                }

            }
        }

        //define constant for start and end places
        define('DT_PLACE_START', ConceptCode::getDetailTypeLocalID('2-134'));
        define('DT_PLACE_END', ConceptCode::getDetailTypeLocalID('2-864'));

        define('DT_PLACE_START2', ConceptCode::getDetailTypeLocalID('1414-1092'));
        define('DT_PLACE_END2', ConceptCode::getDetailTypeLocalID('1414-1088'));
        define('DT_PLACE_TRAN', ConceptCode::getDetailTypeLocalID('1414-1090'));

        $this->system->defineConstant('DT_MINIMUM_ZOOM_LEVEL', true);
        $this->system->defineConstant('DT_MAXIMUM_ZOOM_LEVEL', true);

    }
    return $res;
}

//
//
//
protected function _outputPrepareFields($params){

    if($this->is_leaflet){
        $this->retrieve_detail_fields = null;
        $this->retrieve_header_fields = 'rec_ID,rec_RecTypeID,rec_Title';
    }else{
        parent::_outputPrepareFields($params);
    }
}

//
//
//
protected function _outputHeader(){

    if($this->is_leaflet){
        fwrite($this->fd, '{"geojson":');
    }else{
        fwrite($this->fd, '{"type":"FeatureCollection","features":');
    }

    fwrite($this->fd, '[');

    $this->comma = '';

    //reset values
    $this->geojson_ids = array();
    $this->geojson_dty_ids = array();//unique list of all geofields
    $this->geojson_rty_ids = array();
    $this->timeline_dty_ids = array();//unique list of all date fields
    $this->timeline_data = array();
    $this->layers_record_ids = array();
}

//
//
//
protected function _outputRecord($record){

    $feature = $this->_getGeoJsonFeature($record,
        ($this->extended_mode==2),
        $this->simplify_wkt,  //simplify
        $this->detail_mode, //mode for details if leaflet - description only
        $this->separate_entity);//separate multi geo values per record as separate entries

    if($this->is_leaflet){ //include only geoenabled features, timeline data goes in the separate timeline array

        $recID = $record['rec_ID'];
        $rty_ID = $record['rec_RecTypeID'];

        if(@$feature['when']){
            $this->timeline_data[] = array('rec_ID'=>$recID, 'when'=>$feature['when']['timespans'],
                'rec_RecTypeID'=>$rty_ID, "rec_Title"=>$record['rec_Title']);

            foreach($feature['timevalues_dty'] as $dty_ID){
                if(!in_array($dty_ID, $this->timeline_dty_ids)){
                    $this->timeline_dty_ids[] = $dty_ID;  //unique list of all date fields
                }
            }

            $feature['when'] = null;
            unset($feature['when']);
            $feature['timevalues_dty'] = null;
            unset($feature['timevalues_dty']);
        }

        if( (defined('RT_TLCMAP_DATASET') && $rty_ID==RT_TLCMAP_DATASET) ||
        (defined('RT_MAP_LAYER') && $rty_ID==RT_MAP_LAYER) ){
            array_push($this->layers_record_ids, $recID);
        }

        if(!@$feature['geometry']) {return true;}

        $this->geojson_ids[] = $recID;
        /* simplify
        array_push($this->geojson_ids['all'], $recID);

        if(@$feature['geofield']>0){
        if(@$this->geojson_ids[$feature['geofield']]){
        //record ids grouped by geo pointer fields
        array_push($this->geojson_ids[$feature['geofield']], $recID);
        }
        $feature['geofield'] = null;
        unset($feature['geofield']);
        }*/

    }

    if(@$feature['geometries']){

        $geoms = $feature['geometries'];
        $geoms_dty = $feature['geometries_dty'];

        $feature['geometries'] = null;
        unset($feature['geometries']);
        $feature['geometries_dty']=null;
        unset($feature['geometries_dty']);

        foreach ($geoms as $idx=>$geom){
                $feature['geometry'] = $geom;
                $feature['properties']['rec_GeoField'] = $geoms_dty[$idx];//dty_ID
                fwrite($this->fd, $this->comma.json_encode($feature));
                $this->comma = ',';

                if(!in_array($geoms_dty[$idx], $this->geojson_dty_ids)){
                    $this->geojson_dty_ids[] = $geoms_dty[$idx];//unique list of all geofields
                }
        }
        $this->geojson_rty_ids = array_keys($this->rt_counts);

    }else{
        fwrite($this->fd, $this->comma.json_encode($feature));
    }

    $this->comma = ',';

    return true;

}

//
//
//
protected function _outputFooter(){


    fwrite($this->fd, ']');//close

    if($this->is_leaflet){ //return 2 array - pure geojson and timeline items

        fwrite($this->fd, ',"timeline":'.json_encode($this->timeline_data));
        fwrite($this->fd, ',"timeline_dty_ids":'.json_encode($this->timeline_dty_ids));//unique list of all date fields
        fwrite($this->fd, ',"geojson_ids":'.json_encode($this->geojson_ids));
        fwrite($this->fd, ',"geojson_dty_ids":'.json_encode($this->geojson_dty_ids));//unique list of all geofields
        fwrite($this->fd, ',"geojson_rty_ids":'.json_encode($this->geojson_rty_ids));
        fwrite($this->fd, ',"layers_ids":'.json_encode($this->layers_record_ids).'}');
    }else{
        fwrite($this->fd, '}');//close for FeatureCollection
    }

}

//
// convert heurist record to GeoJSON Feasture
//
// $extended - include concept codes, term code and labels
// $simplify - simplify paths with more than 1000 vertices
// $detail_mode - 0  - only header fields rec_ID, RecTypeID, rec_Title and description if details are defined (for leaflet output)
//                1  - details inline
//                2  - all details in "details" subarray
// $separate_geo_by_dty - if true it separates multi geo values per record as separate entries, otherwise it creates GeometryCollection
//
/// $this->find_by_geofields - search only specified geo fields (in main or linked records)
// if $this->find_by_geofields is not defined and
// if there is not geo data in main record it may search geo in linked records
// $this->find_geo_by_pointer_rty - if true it searches for linked RT_PLACE
//                        or it is array of rectypes defined in sys_TreatAsPlaceRefForMapping + RT_PLACE
// $this->find_geo_by_pointer_dty - list of pointer fields linked to record with geo field (narrow $this->find_geo_by_pointer_rty)
//
private function _getGeoJsonFeature($record, $extended=false, $simplify=false, $detail_mode=2, $separate_geo_by_dty=false){

    if(!($detail_mode==0 || $detail_mode==1 || $detail_mode==2)){
        $detail_mode=2;
    }

    if($extended){
        if(self::$defRecTypes==null) {self::$defRecTypes = dbs_GetRectypeStructures($this->system, null, 2);}
        $idx_name = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];

        if(self::$defTerms==null) {
            self::$defTerms = dbs_GetTerms($this->system);
            self::$defTerms = new \DbsTerms($this->system, self::$defTerms);
        }
    }else{
        $idx_name = -1;
    }

    if(self::$defDetailtypes==null) {self::$defDetailtypes = dbs_GetDetailTypes($this->system, null, 2);}
    $idx_dname = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Name'];
    $idx_dtype = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Type'];
    $idx_ccode = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_ConceptID'];

    $res = array('type'=>'Feature',
        'id'=>$record['rec_ID'],
        'properties'=>array(),
        'geometry'=>array());

    $rty_ID = $record['rec_RecTypeID'];

    $res['properties'] = $record;
    $res['properties']['details'] = array();

    $geovalues = array();
    $geovalues_dty = array();
    $timevalues = array();
    $timevalues_dty = array();
    $date_start = null;
    $date_end = null;
    $symbology = null;
    $ext_description = null;

    //convert details to proper JSON format, extract geo fields and convert WKT to geojson geometry
    foreach ($record['details'] as $dty_ID=>$field_details) {

        $field_type = self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_dtype];

        foreach($field_details as $dtl_ID=>$value){ //for detail multivalues

            if(is_array($value)){ //geo,file,resource (record pointer)
                if(@$value['file']){
                    //remove some fields
                    $val = $value['file'];
                    unset($val['ulf_ID']);
                    unset($val['fullPath']);
                    unset($val['ulf_Parameters']);

                }elseif(@$value['id']){ //resource (record pointer)
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
                        }catch(\Exception $e){
                        }

                        $val = $wkt;

                    }

                    continue;  //it will be included into separate geometry property
                }
            }else{
                if($field_type=='date' || $field_type=='year'){
                    if($dty_ID==DT_START_DATE){
                        $date_start = $value;
                    }elseif($dty_ID==DT_END_DATE){
                        $date_end = $value;
                    }elseif($value!=null){
                        //parse temporal
                        $ta = new \Temporal($value);
                        $ta = $ta->getTimespan(true);
                        if($ta!=null){
                            $ta[] = $dty_ID;
                            $timevalues[] = $ta;  //temporal json array for geojson
                            $timevalues_dty[] = $dty_ID;
                        }
                    }
                }elseif(defined('DT_SYMBOLOGY') && $dty_ID==DT_SYMBOLOGY){
                    $symbology = json_decode($value,true);
                //}elseif(defined('DT_EXTENDED_DESCRIPTION') && $dty_ID==DT_EXTENDED_DESCRIPTION){
                //    $ext_description = $value;
                }elseif(defined('DT_MINIMUM_ZOOM_LEVEL') && $dty_ID==DT_MINIMUM_ZOOM_LEVEL){
                    $res['properties']['rec_MinZoom'] = $value;
                }elseif(defined('DT_MAXIMUM_ZOOM_LEVEL') && $dty_ID==DT_MAXIMUM_ZOOM_LEVEL){
                    $res['properties']['rec_MaxZoom'] = $value;
                }
                $val = $value;
            }

            if(!isset($val)) {$val = '';}

            $val = array('ID'=>$dty_ID,'value'=>$val);

            if(is_array($value) && @$value['id']>0){ //resource (record poitner)
                $val['resourceTitle']     = @$value['title'];
                $val['resourceRecTypeID'] = @$value['type'];
            }

            if($extended){
                //It needs to include the field name and term label and term standard code.
                if($field_type=='enum' || $field_type=='relationtype'){
                    $val['termLabel'] = self::$defTerms->getTermLabel($val['value'], true);
                    $term_code  = self::$defTerms->getTermCode($val['value']);
                    if($term_code) {$val['termCode'] = $term_code;}
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
        } //for detail multivalues
    } //for all details of record


    if($detail_mode==0){ //for leaflet - header and description only
        if($ext_description) {$res['properties']['description'] = $ext_description;}
        $res['properties']['details'] = null;
        unset($res['properties']['details']);
    }elseif($detail_mode==1){
        //details are inline
        $det = $res['properties']['details'];
        for($k=0; $k<count($det); $k++){
            $res['properties'][$det[$k]['fieldName']] = $det[$k]['value'];
        }
        unset($res['properties']['details']);
    }

    if(!isEmptyArray($this->find_by_geofields)){
        //find geo values in linked records
        foreach ($this->find_by_geofields as $idx => $code){
            if(is_array($code) && @$code['id'] && @$code['q']){
                //@todo group details by same queries
                $geodetails = recordSearchLinkedDetails($this->system, $record['rec_ID'], $code['id'], $code['q']);
                foreach ($geodetails as $dty_ID=>$field_details) {
                    foreach($field_details as $dtl_ID=>$value){ //for detail multivalues
                        if(@$value['geo']){
                            $wkt = $value['geo']['wkt'];
                            $json = self::_getJsonFromWkt($wkt, $simplify);
                            if($json){
                               $geovalues[] = $json;
                               //$geovalues_dty[] = $value['geo']['pointerDtyID'];
                            }
                        }
                    }
                }
            }/*elseif (is_numeric($code) && intval($code)>0){
                if(@$record['details'][$code]['geo']){
                            $wkt = $record['details'][$code]['geo']['wkt'];
                            $json = self::_getJsonFromWkt($wkt, $simplify);
                            if($json){
                               $geovalues[] = $json;
                            }
                }
            }*/
        }

    }elseif( (isEmptyArray($geovalues)) &&
        ($this->find_geo_by_pointer_rty===true || (!isEmptyArray($this->find_geo_by_pointer_rty))) ){
        //record does not contains geo field - search geo in linked records

        //this record does not have geo value - find it in related/linked places
        $point0 = array();
        $point1 = array();
        $points = array();

        //returns array of wkt
        //"geo" => array("type","wkt","placeID","pointerDtyID")
        $geodetails = recordSearchGeoDetails($this->system, $record['rec_ID'],
                                $this->find_geo_by_pointer_rty,
                                $this->search_all_geofields?null:$this->find_geo_by_pointer_dty);

        foreach ($geodetails as $dty_ID=>$field_details) {
            foreach($field_details as $dtl_ID=>$value){ //for detail multivalues
                    $wkt = $value['geo']['wkt'];
                    $json = self::_getJsonFromWkt($wkt, $simplify);
                    if($json){
                       $geovalues[] = $json;
                       if($value['geo']['pointerDtyID']>0){
                           $geovalues_dty[] = $value['geo']['pointerDtyID'];

                           if($json['type']=='Point' && $this->find_geo_by_pointer_dty==null){
                               $pointerDtyID =  @$value['geo']['pointerDtyID'];

                               if(defined('DT_PLACE_END') && $pointerDtyID==DT_PLACE_END){
                                    $point1[] = $json;
                               }elseif(defined('DT_PLACE_START') && $pointerDtyID==DT_PLACE_START){
                                    $point0[] = $json;
                               }elseif(defined('DT_PLACE_START') && $pointerDtyID==DT_PLACE_END2){
                                    $point1[] = $json;
                               }elseif(defined('DT_PLACE_START') && $pointerDtyID==DT_PLACE_START2){
                                    $point0[] = $json;
                               }elseif(defined('DT_PLACE_TRAN') && $pointerDtyID==DT_PLACE_TRAN){
                                    $points[] = $json;
                               }
                           }
                       }elseif($value['geo']['relationID']>0){
                           $geovalues_dty[] = 'relation:'.$value['geo']['relationID'];
                       }
                    }
            } //foreach
        } //foreach

        //special case
        //create link path from begin to end place
        if ((!empty($point1)
            && !empty($point0))
            || !empty($points)){
            //$geovalues = array();

            //many start points and transition points - star from start points to first transition
            if(count($point0)>1 || count($point1)>1){
                $path = array('type'=>'MultiLineString', 'coordinates'=>array());


                if(!empty($points)){

                    //adds lines from start to first transition
                    if(!empty($point0)){
                        foreach($point0 as $pnt){
                            $path['coordinates'][] = array($pnt['coordinates'], $points[0]['coordinates']);
                        }
                    }

                    $path_t = array();
                    foreach($points as $pnt){
                        $path_t[] = $pnt['coordinates'];
                    }
                    $path['coordinates'][] = $path_t;

                    //lines from last transition to end points
                    if(!empty($point1)){
                        $lidx = count($points)-1;
                        foreach($point1 as $pnt){
                            $path['coordinates'][] = array($points[$lidx]['coordinates'], $pnt['coordinates']);
                        }
                    }

                }elseif(count($point0)==count($point1)){
                    //adds lines from start to end
                    foreach($point0 as $idx=>$pnt){
                        $path['coordinates'][] = array($pnt['coordinates'], $point1[$idx]['coordinates']);
                    }
                }


            }else{
                $path = array('type'=>'LineString', 'coordinates'=>array());

                if(!empty($point0)) {$path['coordinates'][] = $point0[0]['coordinates'];}

                if(!empty($points)){
                    foreach($points as $pnt){
                        $path['coordinates'][] = $pnt['coordinates'];
                    }
                }
                if(!empty($point1)) {$path['coordinates'][] = $point1[0]['coordinates'];}

            }


            if(!empty($path['coordinates'])){
                $geovalues[] = $path;
                $geovalues_dty[] = 'Path';
            }
        }
    }//if search for linked values

    // $res['geometry'] - merged GeometryCollection
    // $res['geometries'] - separated by fields  $res['geometries_dty']
    // $res['geometries_dty'] - dty_IDs
    if(is_array($geovalues)){
        if(count($geovalues)>1){
            $res['geometry'] = array('type'=>'GeometryCollection','geometries'=>$geovalues);
        }elseif(count($geovalues)==1){
            $res['geometry'] = $geovalues[0];
        }
        if($separate_geo_by_dty){
            $res['geometries'] = $geovalues;
            $res['geometries_dty'] = $geovalues_dty; //dty_ID
        }
    }

    //if data_start and date_end are temporal objects - take action
    if($date_start || $date_end){

        $dty_ID = intval(DT_START_DATE);

        if($date_start && $date_end){ //both are defined
            $dt = \Temporal::mergeTemporals($date_start, $date_end);
        }else{
            if(!$date_start){
                $date_start = $date_end;
                $dty_ID = intval(DT_END_DATE);
            }
            $dt = new \Temporal($date_start);
        }

        if($dt && $dt->isValid())
        {
            $ta = $dt->getTimespan(true);
            if($ta!=null){
                //array($date_start, '', '', $date_end, '');
                $ta[] = $dty_ID;
                $timevalues[] = $ta;  //temporal json array for geojson
                $timevalues_dty[] = $dty_ID;
            }
        }
    }

    if(!empty($timevalues)){

//profile: Flat(0), Central(1) (circa), Slow Start(2) (before), Slow Finish(3) (after) - responsible for gradient
//determination: Unknown(0), Conjecture(2), Measurment(3), Attested(1)  - color depth
// start.earliest - end.latest => vis-item-bbox
// start.earlist~latest => vis-item-bbox-start
// end.earlist~latest => vis-item-bbox-end

            //https://github.com/kgeographer/topotime/wiki/GeoJSON%E2%80%90T
            // "timespans": [["-323-01-01 ","","","-101-12-31","Hellenistic period"]],
            // [start, latest-start, earliest-end, end, label, profile-start, profile-end, determination]
            $res['when'] = array('timespans'=>$timevalues);
            $res['timevalues_dty'] = $timevalues_dty;
    }
    if($symbology){
        $res['style'] = $symbology; //individual symbology per feature
    }

    return $res;

} // _getGeoJsonFeature

//
// Convert WKT to geojson and simplifies coordinates
// @TODO use mapCoordinates.php
//
private static function _getJsonFromWkt($wkt, $simplify=true)
{
        $geom = \geoPHP::load($wkt, 'wkt');
        if(!$geom->isEmpty()){

            /*The GEOS-php extension needs to be installed for these functions to be available
            if($simplify)$geom->simplify(0.0001, true);
            */

            $geojson_adapter = new \GeoJSON();
            $json = $geojson_adapter->write($geom, true);

            if(!isEmptyArray(@$json['coordinates'])){

                if($simplify){
                    if($json['type']=='LineString'){

                        simplifyCoordinates($json['coordinates']);

                    } elseif($json['type']=='Polygon'){
                        for($idx=0; $idx<count($json['coordinates']); $idx++){
                            simplifyCoordinates($json['coordinates'][$idx]);
                        }
                    } elseif ( $json['type']=='MultiPolygon' || $json['type']=='MultiLineString')
                    {
                        for($idx=0; $idx<count($json['coordinates']); $idx++){ //shapes
                            for($idx2=0; $idx2<count($json['coordinates'][$idx]); $idx2++){ //points
                                simplifyCoordinates($json['coordinates'][$idx][$idx2]);}}
                    }
                }

                return $json;
            }
        }//isEmpty
        return null;
}
} //end class
?>