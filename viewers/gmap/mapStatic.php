<?php

/*
* Copyright (C) 2005-2023 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* mapStatic.php
*
* return static google map image with feature either by record Id or by geovalue
*
* 2018-04-24 rewritten for H4. It uses geoPHP and Simplify to prepare coordinates for encoding
*
* used in smarty output and mapViewer.js
*
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Viewers/Map
*/
require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../../hserv/records/search/recordSearch.php';

require_once dirname(__FILE__).'/../../vendor/autoload.php';//for geoPHP

require_once dirname(__FILE__)."/../../hserv/utilities/geo/mapEncodePolyline.php";
require_once dirname(__FILE__)."/../../hserv/utilities/geo/mapSimplify.php";

$mapobjects = array();

define('USE_GOOGLE', false);

$system = new hserv\System();
if( ! $system->init(@$_REQUEST['db']) ){
    //get error and response
    //$response = $system->getError();
    redirectURL(HEURIST_BASE_URL.'hclient/assets/notfound.png');
    exit;
}

if(false && @$_REQUEST['value'] && $_REQUEST['value']!='undefined'){

    $val = $_REQUEST['value'];
    $type = substr($val,0,strpos($val,' '));
    $geoval = substr($val,strpos($val,' ')+1);

    $geoobj = parseValueFromDb(0, $type, $geoval, null);

    if($geoobj){
        $mapobjects = array("cntWithGeo"=>1, "geoObjects"=>array($geoobj) );
    }

}else{
    //$mapobjects = getMapObjects($_REQUEST);


    if(@$_REQUEST['id'] && !$_REQUEST['q']){
        $_REQUEST['q'] = 'ids:'.$_REQUEST['id'];
    }
    $_REQUEST['w'] = 'e';
    $_REQUEST['detail'] = 'timemap';
    //retrieve records
    $response = recordSearch($system, $_REQUEST);

    if($response && $response['status'] == HEURIST_OK && $response['data'] && $response['data']['count']>0){

        $geo_fieldtypes_ids = dbs_GetDetailTypes($system, array('geo'), 3);

        $records = $response['data']['records'];
        foreach($records as $recID => $record){
            if($record['d']){
                foreach($geo_fieldtypes_ids as $dty_ID){
                    if(@$record['d'][$dty_ID]){
                        foreach($record['d'][$dty_ID] as $as_wkt){
                            //extract type
                            $k = strpos($as_wkt, ' ');
                            $geo_type = substr($as_wkt, 0, $k);
                            $as_wkt = substr($as_wkt, $k+1);

                            switch ($geo_type) {

                                case "r":   //special case for rectangle
                                    if (preg_match("/POLYGON\s?\(\\((\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*\\S+\\s+\\S+\\)\\)/i", $as_wkt, $matches)) {

                                        array_push($mapobjects, array("type" => "rect",
                                            "geo" => array("x0" => floatval($matches[1]), "y0" => floatval($matches[2]),
                                                "x1" => floatval($matches[5]), "y1" => floatval($matches[6]))));

                                    }
                                    break;

                                case "c": //special case for circle
                                    if (preg_match("/LINESTRING\s?\((\\S+)\\s+(\\S+),\\s*(\\S+)\\s+\\S+,\\s*\\S+\\s+\\S+,\\s*\\S+\\s+\\S+\\)/i", $as_wkt, $matches)) {

                                        array_push($mapobjects, array("type" => "circle",
                                            "geo" => array("x" => floatval($matches[1]), "y" => floatval($matches[2]),
                                                "radius" => floatval($matches[3] - $matches[1]))));
                                    }
                                    break;

                                default:

                                    //'geoObjects' => array() $geoObject['geo']['points']
                                    //$geoObject['type']

                                    //geometryType()
                                    //getGeos()

                                    $geom = geoPHP::load($as_wkt, 'wkt');
                                    if(!$geom->isEmpty()){

                                        $geojson_adapter = new GeoJSON();
                                        $json = $geojson_adapter->write($geom, true);

                                        ///$json = $geom->out('json');
                                        // $geom->numGeometries()
                                        if(!empty($json['coordinates'])){
                                            if($json['type']=='Polygon'){
                                                foreach($json['coordinates'] as $points){
                                                    array_push($mapobjects, array('type'=>$geo_type, 'geo'=>$points));
                                                }
                                            }elseif($json['type']=='MultiPoint'){
                                                array_push($mapobjects, array('type'=>$geo_type, 'geo'=>$json['coordinates']));
                                            }elseif($json['type']=='MultiPolygon'
                                            || $json['type']=='MultiLineString'){
                                                foreach($json['coordinates'] as $shape){
                                                    foreach($shape as $points){
                                                        array_push($mapobjects, array('type'=>$geo_type, 'geo'=>$points));
                                                    }
                                                }
                                            }else{
                                                array_push($mapobjects, array('type'=>$geo_type, 'geo'=>$json['coordinates']));
                                            }
                                        }

                                        // array_push($points, array(floatval($point['y']), floatval($point['x'])) );
                                    }
                            }//switch

                        }//foreach multi values
                    }
                }
            }
        }//foreach
    }
}

if(!isEmptyArray($mapobjects)){

    if(USE_GOOGLE){

        if(@$_REQUEST['width'] && @$_REQUEST['height']){
            $size = $_REQUEST['width']."x".$_REQUEST['height'];
        }else{
            $size = "300x300";
        }

        $url = "https://maps.google.com/maps/api/staticmap";
        $url = $url."?size=".$size."&sensor=false";

        if(@$_REQUEST['zoom'] && is_numeric($_REQUEST['zoom'])){
            $url = $url."&zoom=".$_REQUEST['zoom'];
        }
        if(@$_REQUEST['maptype']){
            $url = $url."&maptype=".$_REQUEST['maptype'];
        }else{
            $url = $url."&maptype=terrain";
        }
        if(@$_REQUEST['m_style']){
            $style_marker = $_REQUEST['m_style'];
        }else{
            $style_marker = "color:red";
        }
        if(@$_REQUEST['pl_style']){
            $style_path = $_REQUEST['pl_style'];
        }else{
            $style_path = "weight:3|color:red";
        }
        if(@$_REQUEST['pg_style']){
            $style_poly = $_REQUEST['pg_style'];
        }else{
            $style_poly = "weight:3|color:red|fillcolor:0x0000ff40";
        }

    }else{

        if(@$_REQUEST['width'] && @$_REQUEST['height']){
            $size = $_REQUEST['width'].",".$_REQUEST['height'];
        }else{
            $size = "300,300";
        }

        $url = 'https://static-maps.yandex.ru/1.x/?size='.$size.'&l=map&lang=en_RU';

        if(@$_REQUEST['zoom'] && is_numeric($_REQUEST['zoom'])){
            $url = $url."&z=".$_REQUEST['zoom'];
        }

        /*
        if(@$_REQUEST['m_style']){
        $style_marker = $_REQUEST['m_style'];
        }else{
        $style_marker = "color:red";
        }
        if(@$_REQUEST['pl_style']){
        $style_path = $_REQUEST['pl_style'];
        }else{
        $style_path = "weight:3|color:red";
        }
        if(@$_REQUEST['pg_style']){
        $style_poly = $_REQUEST['pg_style'];
        }else{
        $style_poly = "weight:3|color:red|fillcolor:0x0000ff40";
        }
        */
    }




    //$url = $url."&key=ABQIAAAAGZugEZOePOFa_Kc5QZ0UQRQUeYPJPN0iHdI_mpOIQDTyJGt-ARSOyMjfz0UjulQTRjpuNpjk72vQ3w";

    $markers = "";
    $path_all = "";
    $poly_all = "";

    $verties_cnt = 0;
    $shapes_cnt = 0;

    foreach ($mapobjects as $geoObject) {

        if($geoObject['type']=="point" || $geoObject['type']=="p"){
            if(USE_GOOGLE){
                $markers = $markers.$geoObject['geo'][1].','.$geoObject['geo'][0].'|';
            }else{
                $markers = $markers.$geoObject['geo'][0].','.$geoObject['geo'][1].',pm2rdm';
            }
            $verties_cnt++;
        }
        elseif($geoObject['type']=="rect"){

            $crd = $geoObject['geo'];
            if(USE_GOOGLE){
                $poly_all = $poly_all.'&path='.$style_poly.'|';
                $poly_all = $poly_all.$crd['y0'].','.$crd['x0'].'|';
                $poly_all = $poly_all.$crd['y0'].','.$crd['x1'].'|';
                $poly_all = $poly_all.$crd['y1'].','.$crd['x1'].'|';
                $poly_all = $poly_all.$crd['y1'].','.$crd['x0'].'|';
                $poly_all = $poly_all.$crd['y0'].','.$crd['x0'];
            }else{
                if($poly_all!=''){
                    $poly_all = $poly_all.'~';
                }
                $poly_all = $poly_all.'c:FF0000FF,f:0000FF40,w:3,'
                .$crd['x0'].','.$crd['y0'].','
                .$crd['x0'].','.$crd['y1'].','
                .$crd['x1'].','.$crd['y1'].','
                .$crd['x1'].','.$crd['y0'].','
                .$crd['x0'].','.$crd['y0'];
            }

        }elseif($geoObject['type']=="circle"){
            if(USE_GOOGLE){
                $markers = $markers.$geoObject['geo']['y'].",".$geoObject['geo']['x']."|";
            }else{
                $markers = $markers.$geoObject['geo']['x'].','.$geoObject['geo']['y'].',pm2rdm';
            }
            $verties_cnt++;
        }elseif(USE_GOOGLE){

            $points = array();
            $points2 = $geoObject['geo'];
            $points_to_encode = array();

            if(count($points2)>1000){

                $points = array();
                foreach ($geoObject['geo'] as $point) {
                    array_push($points, array('y'=>$point[1], 'x'=>$point[0]));
                }

                $tolerance = 0.01;// 0.002;
                $crn = 0; //count of run
                $points2 = $points;
                while(count($points2)>1000 && $crn<4){
                    $points2 = Simplify::run($points, $tolerance);
                    $tolerance = $tolerance + 0.002;
                    $crn++;
                }//while simplify

                if(count($points2)<=1000){
                    foreach ($points2 as $point) {
                        array_push($points_to_encode, array($point['y'], $point['x']) );
                    }
                }
            }else{

                foreach ($points2 as $point) {
                    array_push($points_to_encode, array($point[1], $point[0]) );
                }
            }

            if(count($points_to_encode)>1){

                if($geoObject['type']=="polygon" || $geoObject['type']=="pl"){
                    array_push($points_to_encode, $points_to_encode[0]);//add last as first
                }

                $encodedPoints = dpEncode($points_to_encode);

                if($geoObject['type']=="polygon" || $geoObject['type']=="pl"){
                    $poly_all = "$poly_all&path=$style_poly|enc:".$encodedPoints[0];
                    if(strlen($poly_all)>1900){
                        break; //total length of url is too long
                    }
                }else{
                    $path_all = "$path_all&path=$style_path|enc:".$encodedPoints[0];
                    if(strlen($path_all)>1900){
                        break; //total length of url is too long
                    }
                }

            }

        }elseif($shapes_cnt<5 && $verties_cnt<97) { //limit 5 shapes and 100 vertcies

            $points = array();
            $points2 = $geoObject['geo'];
            $points_to_encode = array();

            if(count($points2)>100){

                $points = array();
                foreach ($geoObject['geo'] as $point) {
                    array_push($points, array('y'=>$point[1], 'x'=>$point[0]));
                }

                $tolerance = 0.02;// 0.002;
                $crn = 0; //count of run
                $points2 = $points;
                while($verties_cnt+count($points2)>100 && $crn<4){
                    $points2 = Simplify::run($points, $tolerance);
                    $tolerance = $tolerance + 0.005;
                    $crn++;
                }//while simplify

                if($verties_cnt+count($points2)<=100){
                    foreach ($points2 as $point) {
                        $points_to_encode[] = $point['x'];
                        $points_to_encode[] = $point['y'];
                        $shapes_cnt++;
                        $verties_cnt = $verties_cnt + count($points2);
                    }
                }
            }else{
                $shapes_cnt++;
                if(!isEmptyArray($points2)){
                    $verties_cnt = $verties_cnt + count($points2);
                    foreach ($points2 as $point) {
                        $points_to_encode[] = $point[0];
                        $points_to_encode[] = $point[1];
                    }
                }
            }
            if(count($points_to_encode)>2 && $geoObject['type']=="polygon" || $geoObject['type']=="pl"){
                //add first as last
                $points_to_encode[] = $points_to_encode[0];
                $points_to_encode[] = $points_to_encode[1];
                array_unshift($points_to_encode,'c:FF0000FF,f:0000FF40,w:3');
            }
            $points_to_encode = implode(',',$points_to_encode);


            if($points_to_encode!=''){

                if($poly_all!=''){
                    $poly_all = $poly_all.'~';
                }
                $poly_all = $poly_all.$points_to_encode;

            }

        }
    }

    if(USE_GOOGLE){

        if($markers!=''){
            $markers = "&markers=".$style_marker."|".$markers;
            $url = $url.$markers;
        }
        if($path_all!=''){         //0xff00007f
            $url = $url.$path_all;
        }
        if($poly_all!=''){
            $url = $url.$poly_all;
        }

    }else{
        if($markers!=''){
            $markers = '&pt='.$markers;
            $url = $url.$markers;
        }
        if($poly_all!=''){
            $url = $url.'&pl='.$poly_all;
        }
    }

    redirectURL($url);
}else{
    redirectURL(HEURIST_BASE_URL.'hclient/assets/notfound.png');
}
