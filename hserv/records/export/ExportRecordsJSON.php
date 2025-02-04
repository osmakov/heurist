<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* exportRecordsJSON.php - class to export records as JSON
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


/**
*
*
*/
class ExportRecordsJSON extends ExportRecords {

    private $is_restapi = false;

    private $records_cnt = 0;
    private $records_cnt_filtered = 0;

    private $datatable_session_id = 0;
    private $datatable_draw = 0;
    private $datatable_columns;
    private $datatable_row_placeholder;

    //see ImportHerist->importRecordsFromDatabase
    private $is_tlc_export = false;
    private $tlc_mapdoc_name = null;
    private $maplayer_fields = null;
    private $mapdoc_defaults = array();
    private $maplayer_records = array();
    private $maplayer_extents = array();//to calculate summary extent

//
//
//
protected function _outputPrepare($data, $params){

    $res = parent::_outputPrepare($data, $params);
    if($res){

        $this->datatable_session_id = 0;
        if(intval(@$params['datatable'])>0){
            $this->datatable_session_id = intval($params['datatable']);
            $this->datatable_draw = $params['draw'];
        }
        $this->is_restapi = @$params['restapi'];

        $this->records_cnt = intval(@$params['recordsTotal']);
        $this->records_cnt_filtered = intval(@$params['recordsFiltered']);

    }
    return $res;
}

//
//
//
protected function _outputPrepareFields($params){


    if($this->datatable_session_id>0){

        $this->datatable_columns = array('0'=>array());//for datatable
        $this->datatable_row_placeholder = array();
        $need_rec_type = false;

        //for datatable convert  $params['columns'] to array
            if(array_key_exists('columns', $params) && is_string($params['columns'])){
                $params['columns'] = json_decode($params['columns'], true);
            }
            

        /*
0: ["rec_ID","rec_Title"],
3: ["rec_ID", "rec_RecTypeID", "1", "949", "9", "61"]
5: ["1", "38"]
        */
        $need_tags = false;
        $this->retrieve_detail_fields = array();
        $this->retrieve_header_fields = array();//header fields
        $retrieve_relmarker_fields = array();

        if(array_key_exists('columns', $params) && is_array($params['columns'])){
            foreach($params['columns'] as $idx=>$column){
                
                if(!array_key_exists('data', $column)){
                    continue;
                }
                $col_name = $column['data'];

                if(strpos($col_name,'.')>0){
                    list($rt_id, $col_name) = explode('.',$col_name);

                    if(!@$this->datatable_row_placeholder[$rt_id]) {$this->datatable_row_placeholder[$rt_id] = array();}
                    $this->datatable_row_placeholder[$rt_id][$col_name] = '';
                }else{
                    $rt_id = 0;
                    $this->datatable_row_placeholder[$col_name] = '';
                }

                if($col_name=='rec_Tags'){
                    $need_tags = true;
                }elseif($rt_id==0){
                    if(strpos($col_name,'rec_')===0){
                        array_push($this->retrieve_header_fields, $col_name);
                    }elseif(strpos($col_name, 'lt')===0 || strpos($col_name, 'rt')===0){
                        array_push($this->retrieve_detail_fields, substr($col_name, 2));
                    }else{
                        if($col_name === 'typename'){
                            $need_rec_type = true;
                        }else{
                            array_push($this->retrieve_detail_fields, $col_name);
                        }
                    }
                }

                if(!array_key_exists($rt_id, $this->datatable_columns)) {
                      $this->datatable_columns[$rt_id] = array();
                }
                array_push($this->datatable_columns[$rt_id], $col_name);
            }
        }

        if(isEmptyArray($this->retrieve_detail_fields)){
            $this->retrieve_detail_fields = false;
        }

        //always include rec_ID and rec_RecTypeID fields
        if(!in_array('rec_ID',$this->retrieve_header_fields)){
            array_push($this->retrieve_header_fields,'rec_ID');
            array_push($this->datatable_columns['0'],'rec_ID');
        }
        if(!in_array('rec_RecTypeID',$this->retrieve_header_fields)){
            array_push($this->retrieve_header_fields,'rec_RecTypeID');
            array_push($this->datatable_columns['0'],'rec_RecTypeID');
        }

        $this->retrieve_header_fields = implode(',', $this->retrieve_header_fields);

    }elseif($this->extended_mode==3){ //for media viewer

        $this->retrieve_detail_fields = array('file');
        $this->retrieve_header_fields = 'rec_ID,rec_RecTypeID,rec_Title';

    }else{

        parent::_outputPrepareFields($params);


        $this->is_tlc_export = (@$params['tlcmap']!=null && defined('RT_TLCMAP_DATASET'));
        if($this->is_tlc_export){

            $this->tlc_mapdoc_name = $params['tlcmap'];
            //get list of detail types for MAP_LAYER
            $this->maplayer_fields = mysql__select_list2($this->mysqli,
                'select rst_DetailTypeID from defRecStructure where rst_RecTypeID='.RT_MAP_LAYER);
            //get list of field types with type "file"
            //$this->ds_file_fields = mysql__select_list2($this->mysqli,
            //    'select dty_ID from defDetailTypes where dty_Type="file"');
            //get default values for mapspace
            $this->mapdoc_defaults = mysql__select_assoc2($this->mysqli,
                'select rst_DetailTypeID, rst_DefaultValue from defRecStructure where rst_RecTypeID='.RT_MAP_DOCUMENT
                .' AND rst_DetailTypeID in ('.DT_MAP_BOOKMARK.','.DT_ZOOM_KM_POINT.')' );

            $this->maplayer_records = array();
            $this->maplayer_extents = array();
        }
    }
}

//
//
//
protected function _outputHeader(){

    if($this->datatable_session_id>1){ //session id

            //"recordsTotal": 57,"recordsFiltered":'.count($this->records).',
            fwrite($this->fd, '{"draw": '.$this->datatable_draw
                     .',"recordsTotal":'.$this->records_cnt
                     .',"recordsFiltered":'
                     .($this->records_cnt_filtered>0?$this->records_cnt_filtered:$this->records_cnt)
                     .',"data":[');

    }elseif($this->datatable_session_id==1){

            fwrite($this->fd, '{"data": [');

    }elseif($this->is_restapi==1){

        fwrite($this->fd, '{"records":[');

        /*
        if(count($records)==1 && @$params['recID']>0){
            //fwrite($fd, '');
        }else{
            fwrite($fd, '{"records":[');
        }
        */


    }else {

        fwrite($this->fd, '{"heurist":{"records":[');
    }

}

//
//
//
protected function _outputRecord($record){

    if($this->datatable_session_id>0){

        recordSearchDetailsRelations($this->system, $record, $this->retrieve_detail_fields);

        $feature = $this->_getJsonFlat( $record, $this->datatable_columns, $this->datatable_row_placeholder );

        fwrite($this->fd, $this->comma.json_encode($feature));

    }elseif($this->extended_mode>0 && $this->extended_mode<3){ //with concept codes and labels

        $feature = $this->_getJsonFeature($record, $this->extended_mode);
        fwrite($this->fd, $this->comma.json_encode($feature));

    }elseif($this->extended_mode==3){

        $feature = self::_getMediaViewerData($record);
        if($feature){
            fwrite($this->fd, $this->comma.$feature);
        }else{
            return true;
        }

    }else{

        //change record type to layer, remove redundant fields
        if($this->is_tlc_export && $record['rec_RecTypeID']==RT_TLCMAP_DATASET){
                $record['rec_RecTypeID'] = RT_MAP_LAYER;

                $new_details = array();
                //remove redundant fields from RT_TLCMAP_DATASET
                foreach($record["details"] as $dty_ID => $values){
                    if(in_array($dty_ID, $this->maplayer_fields)){
                        $new_details[$dty_ID] = $values;

                    }
                    if($dty_ID==DT_GEO_OBJECT){
                        //keep geo to calculate extent for mapdocument
                        array_push($this->maplayer_extents, $values);
                    }
                }
                $record["details"] = $new_details;

                //{"id":"287","type":"29","title":"Region cities","hhash":null}
                $this->maplayer_records[] = array('id'=>$record['rec_ID']);
        }

        fwrite($this->fd, $this->comma.json_encode($record));//as is
    }
    $this->comma = ',';

    return true;
}

//
//
//
protected function _outputFooter(){

    if($this->datatable_session_id>0){ //session id

        fwrite($this->fd, ']}');

    }elseif($this->is_restapi==1){

        fwrite($this->fd, ']}'); //close records

    }else {


        if($this->is_tlc_export){
            //create mapdocument record and write it as a last record in th set
            $record = $this->_calculateSummaryExtent(true);
            $this->_outputRecord($record);
        }

        //header fwrite($this->fd, '{"heurist":{"records":[');

        fwrite($this->fd, ']');

        $database_info = $this->_getDatabaseInfo();

        fwrite($this->fd, ',"database":'.json_encode($database_info));
        fwrite($this->fd, '}}');

    }


}


/*
Produces json for DataTable widget

$columns:
0: ["rec_ID","rec_Title"],
3: ["rec_ID", "rec_RecTypeID", "1", "949", "9", "61"]
5: ["1", "38"]
*/
private function _getJsonFlat( $record, $columns, $row_placeholder, $level=0 ){

    $res = ($level==0)?$row_placeholder:array();

    if($level==0){
        $rt_id = 0;
    }else{
        $rt_id = 't'.$record['rec_RecTypeID'];
    }

    if(self::$defTerms==null) {
        self::$defTerms = dbs_GetTerms($this->system);
        self::$defTerms = new \DbsTerms($this->system, self::$defTerms);
    }

    if(!array_key_exists($rt_id, $columns)) {return null;}

    foreach($columns[$rt_id] as $column){

        $col_name = $column; //($rt_id>0 ?$rt_id.'.':'').

        //HEADER FIELDS
        if ($column=='tags' || $column=='rec_Tags'){
            $value = recordSearchPersonalTags($this->system, $record['rec_ID']);
            $res[$col_name] = ($value==null)?'':implode(' | ', $value);
        }elseif(strpos($column,'rec_')===0){
            $res[$col_name] = $record[$column];
        }elseif($column=='ids'){
            $res[$col_name] = $record['rec_ID'];
        }elseif($column=='typeid'){
            $res[$col_name] = $record['rec_RecTypeID'];
        }elseif($column=='typename'){
            if(self::$defRecTypes==null){
                self::$defRecTypes = dbs_GetRectypeStructures($this->system, null, 0);
            }
            $res[$col_name] = self::$defRecTypes['names'][$record['rec_RecTypeID']];
        }elseif($column=='added'){
            $res[$col_name] = $record['rec_Added'];
        }elseif($column=='modified'){
            $res[$col_name] = $record['rec_Modified'];
        }elseif($column=='addedby'){
            $res[$col_name] = $record['rec_AddedBy'];
        }elseif($column=='url'){
            $res[$col_name] = $record['rec_URL'];
        }elseif($column=='notes'){
            $res[$col_name] = $record['rec_ScratchPad'];
        }elseif($column=='owner'){
            $res[$col_name] = $record['rec_OwnerUGrpID'];
        }elseif($column=='visibility'){
            $res[$col_name] = $record['rec_NonOwnerVisibility'];
        }else{
            $res[$col_name] = '';//placeholder
        }
    }

    if(self::$defDetailtypes==null){
        self::$defDetailtypes = dbs_GetDetailTypes($this->system, null, 2);
    }
    $idx_dtype = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Type'];

    //convert details to proper JSON format
    if(@$record['details'] && is_array($record['details'])){

        foreach ($record['details'] as $dty_ID=>$field_details) {

            $field_type = self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_dtype];

            if($field_type=='resource' && in_array('lt'.$dty_ID, $columns[0]) !== false){ // account for resources, which expects 'lt' before the Id
                $dty_ID = 'lt'.$dty_ID;
            }

            if(!in_array($dty_ID, $columns[$rt_id])) {continue;}

            $col_name = $dty_ID;

            $res[$col_name] = array();

            foreach($field_details as $dtl_ID=>$field_value){ //for detail multivalues

                if($field_type=='resource' || $field_type=='relmarker'){
                    //continue;
                    //if multi constraints - search all details
                    $link_rec_Id =  $field_value['id'];
                    $relation_id =  @$field_value['relation_id'];
                    $record = recordSearchByID($this->system, $link_rec_Id, true, null );
                    $field_value = $this->_getJsonFlat( $record, $columns, null, $level+1 );

                    if($field_value!=null){
                        $rt_id_link = 't'.$record['rec_RecTypeID'];
                        if(@$res[$rt_id_link]){ //such record type already exists in flat array - create multiple values
                            foreach($field_value as $col=>$field){
                                if(@$res[$rt_id_link][$col]==null || $res[$rt_id_link][$col]==''){
                                    $res[$rt_id_link][$col] = $field;
                                }else{
                                    if(!is_array($res[$rt_id_link][$col])){
                                        $res[$rt_id_link][$col] = array($res[$rt_id_link][$col]);
                                    }
                                    array_push($res[$rt_id_link][$col], $field);
                                }
                            }
                        }else{
                            $res[$rt_id_link] = $field_value;
                        }
                        if($field_type=='relmarker' && $relation_id>0){

                            $record2 = recordSearchByID($this->system, $relation_id, true, null );
                            $field_value2 = $this->_getJsonFlat( $record2, $columns, null, $level+1 );
                            if($field_value2!=null){
                                $rt_id_link = 't'.$record2['rec_RecTypeID'];//t1
                                if(@$res[$rt_id_link]){
                                    foreach($field_value2 as $col=>$field){
                                        //$col = 'r.'.$col;
                                        if(@$res[$rt_id_link][$col]==null || $res[$rt_id_link][$col]==''){
                                            $res[$rt_id_link][$col] = $field;
                                        }else{
                                            if(!is_array($res[$rt_id_link][$col])){
                                                $res[$rt_id_link][$col] = array($res[$rt_id_link][$col]);
                                            }
                                            array_push($res[$rt_id_link][$col], $field);
                                        }
                                    }
                                }else{
                                    $res[$rt_id_link] = $field_value2;
                                }
                            }
                        }
                    }
                    $field_value = $record['rec_Title'];//$link_rec_Id Record ID replaced with Record Title

                }elseif($field_type=='file'){

                    $field_value = $field_value['file']['ulf_ObfuscatedFileID'];

                }elseif($field_type=='geo'){

                    $field_value = @$field_value['geo']['wkt'];

                }elseif ($field_type=='enum' || $field_type=='relationtype'){

                    $field_value = self::$defTerms->getTermLabel($field_value, true);

                }elseif($this->datatable_session_id > 0 && $field_type=='date'){

                    $temporal = new \Temporal($field_value);
                    $field_value = $temporal && $temporal->isValid() ? $temporal->toReadableExt('', true) : $field_value;

                }

                if($field_value!=null){
                    array_push($res[$col_name], $field_value);
                }
            } //for detail multivalues

            if(is_array(@$res[$col_name]) && count($res[$col_name])==1){
                $res[$col_name] = $res[$col_name][0];
            }
        } //for all details of record
    }

    return $res;
}

//
// convert heurist record to more interpretable format
//
// $extended_mode = 0 - as is, 1 - details in format {dty_ID: val: }, 2 - with concept codes and names/labels
//
private function _getJsonFeature($record, $extended_mode){

    if($extended_mode==0){ //leave as is
        return $record;
    }

    $rty_ID = $record['rec_RecTypeID'];

    $res = $record;
    $res['details'] = array();

    if(self::$defDetailtypes==null){
        self::$defDetailtypes = dbs_GetDetailTypes($this->system, null, 2);
    }
    $idx_dtype = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Type'];

    if($extended_mode==2){ //extended - with concept codes and names/labels

        if(self::$defRecTypes==null) {
            self::$defRecTypes = dbs_GetRectypeStructures($this->system, null, 2);
        }
        if(self::$defTerms==null) {
            self::$defTerms = dbs_GetTerms($this->system);
            self::$defTerms = new \DbsTerms($this->system, self::$defTerms);
        }
        $idx_name = self::$defRecTypes['typedefs']['dtFieldNamesToIndex']['rst_DisplayName'];

        $idx_dname = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_Name'];
        $idx_ccode = self::$defDetailtypes['typedefs']['fieldNamesToIndex']['dty_ConceptID'];


        $idx_ccode2 = self::$defRecTypes['typedefs']['commonNamesToIndex']['rty_ConceptID'];

        $res['rec_RecTypeName'] = self::$defRecTypes['names'][$rty_ID];
        $idx_ccode2 = self::$defRecTypes['typedefs'][$rty_ID]['commonFields'][$idx_ccode2];
        if($idx_ccode2) {$res['rec_RecTypeConceptID'] = $idx_ccode2;}

    }

    //convert details to proper JSON format, extract geo fields and convert WKT to geojson geometry
    foreach ($record['details'] as $dty_ID=>$field_details) {

        foreach($field_details as $dtl_ID=>$value){ //for detail multivalues

            $val = array('dty_ID'=>$dty_ID,'value'=>$value);
            $field_type = self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_dtype];

            if($extended_mode==2){ //extended - with concept codes and names/labels

                //It needs to include the field name and term label and term standard code.
                if($field_type=='enum' || $field_type=='relationtype'){
                    $val['termLabel'] = self::$defTerms->getTermLabel($value, true);
                    $term_code  = self::$defTerms->getTermCode($value);
                    if($term_code) {$val['termCode'] = $term_code; }
                    $term_code  = self::$defTerms->getTermConceptID($value);
                    if($term_code) {$val['termConceptID'] = $term_code;}
                }

                if(@self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dty_ID]){
                    $val['fieldName'] = self::$defRecTypes['typedefs'][$rty_ID]['dtFields'][$dty_ID][$idx_name];
                }else{
                    //non standard field
                    $val['fieldName'] = self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_dname];
                }

                $val['fieldType'] = $field_type;
                $val['conceptID'] = self::$defDetailtypes['typedefs'][$dty_ID]['commonFields'][$idx_ccode];
            }
            if($field_type=='date'){ //decode json temporal
                $temporal = json_decode($val['value']);
                if($temporal!=null){
                    $val['value'] = $temporal;
                }
            }

            $res['details'][] = $val;
        } //for detail multivalues
    } //for all details of record

    return $res;
}

//
// convert heurist record to plain object for mediaViewer
//
// return null if not media content found
//
private static function _getMediaViewerData($record){

    $res = '';
    $comma = '';
    $info = array();

    //1. get "file" field values
    foreach ($record['details'] as $dty_ID=>$field_details) {
        foreach($field_details as $dtl_ID=>$file){
            array_push($info, $file['file']);
        }
    }

    //2. get file info
    if(!empty($info)){

        foreach($info as $fileinfo){

            if(strpos($fileinfo['ulf_OrigFileName'],ULF_TILED_IMAGE)===0) {continue;}

            $mimeType = $fileinfo['fxm_MimeType'];

            $resource_type = null;

            if(strpos($mimeType,"video/")===0){
                $resource_type = 'Video';
            }elseif(strpos($mimeType,"audio/")===0){
                if(strpos($mimeType,"soundcloud")>0) {continue;}
                $resource_type = 'Sound';
            }elseif(strpos($mimeType,DIR_IMAGE)===0 || $fileinfo['ulf_OrigFileName']==ULF_IIIF_IMAGE){
                $resource_type = 'Image';
            }

            if($resource_type){

                $fileid = $fileinfo['ulf_ObfuscatedFileID'];
                $external_url = $fileinfo['ulf_ExternalFileReference'];

                /*
                $item = '{rec_ID:'.$record['rec_ID'].', id:"'.$fileid.'",mimeType:"'.$mimeType
                        .'",filename:"'.htmlspecialchars($fileinfo['ulf_OrigFileName'])
                        .'",external:"'.htmlspecialchars($external_url).'"}';
                */

                $res = $res.$comma.json_encode(array('rec_ID'=>$record['rec_ID'],
                               'caption'=>htmlspecialchars($record['rec_Title']),
                               'id'=>$fileid,
                               'mimeType'=>$mimeType,
                               'filename'=>htmlspecialchars($fileinfo['ulf_OrigFileName']),
                               'external'=>htmlspecialchars($external_url)));//important need restore on client side
                $comma =  ",\n";

            }
        }//for

    }//!empty($file_ids)


    return $res;
}

//
//
//
private function _calculateSummaryExtent($is_return_rec){

        $zoomKm = 0;
        $mbookmark = null;
        $mbox = array();
        foreach($this->maplayer_extents as $dtl_ID=>$values){
            foreach($values as $value){
                if(is_array($value) && @$value['geo']){
                    $wkt = $value['geo']['wkt'];
                    $bbox = self::_getExtentFromWkt($wkt);
                    if($bbox!=null){
                        if( !@$mbox['maxy'] || $mbox['maxy']<$bbox['maxy'] ){
                            $mbox['maxy'] = $bbox['maxy'];
                        }
                        if( !@$mbox['maxx'] || $mbox['maxx']<$bbox['maxx'] ){
                            $mbox['maxx'] = $bbox['maxx'];
                        }
                        if( !@$mbox['miny'] || $mbox['miny']>$bbox['miny'] ){
                            $mbox['miny'] = $bbox['miny'];
                        }
                        if( !@$mbox['minx'] || $mbox['minx']>$bbox['minx'] ){
                            $mbox['minx'] = $bbox['minx'];
                        }
                    }
                }
            }
        }
        if(count($mbox)==4){

            $gPoint = new \GpointConverter();
            $gPoint->setLongLat($mbox['minx'], $mbox['miny']);
            $zoomKm = round($gPoint->distanceFrom($mbox['maxx'], $mbox['minx'])/100000,0);


            $mbookmark = 'Extent,'.$mbox['miny'].','.$mbox['minx']
                         .','.$mbox['maxy'].','.$mbox['maxx'].',1800,2050';

            $mbox = array($mbox['minx'].' '.$mbox['miny'], $mbox['minx'].' '.$mbox['maxy'],
                            $mbox['maxx'].' '.$mbox['maxy'], $mbox['maxx'].' '.$mbox['miny'],
                              $mbox['minx'].' '.$mbox['miny']);
            $mbox = 'POLYGON(('.implode(',',$mbox).'))';

        }

        if($is_return_rec){

            //add constructed mapspace record
            $record['rec_ID'] = 999999999;
            $record['rec_RecTypeID'] = RT_MAP_DOCUMENT;
            $record['rec_Title'] = $this->tlc_mapdoc_name;
            $record['rec_URL'] = '';
            $record['rec_ScratchPad'] = '';
            $record["details"] = array(
                DT_NAME=>array('1'=>$this->tlc_mapdoc_name),
                DT_MAP_BOOKMARK=>array('2'=>($mbookmark!=null?$mbookmark:$this->mapdoc_defaults[DT_MAP_BOOKMARK])),
                DT_ZOOM_KM_POINT=>array('3'=>($zoomKm>0?$zoomKm:$this->mapdoc_defaults[DT_ZOOM_KM_POINT])),
                DT_GEO_OBJECT=>array('4'=>($mbox!=null?array('geo'=>array("type"=>"pl", "wkt"=>$mbox)):null)),
                DT_MAP_LAYER=>$this->maplayer_records
            );

            return $record;
        }else{
            return $mbox;
        }

}

//
//
//
private static function _getExtentFromWkt($wkt)
{
        $bbox = null;
        $geom = \geoPHP::load($wkt, 'wkt');
        if(!$geom->isEmpty()){
            $bbox = $geom->getBBox();
        }
        return $bbox;
}

} //end class
?>