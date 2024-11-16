<?php
/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
* exportRecordsIIIF.php - class to export records as IIIF manifest
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
*  setSession - switch current datbase
*  output - main method
*
*/
class ExportRecordsIIIF extends ExportRecords {

    private $iiif_version = 3;
    private $ulf_ObfuscatedFileID = null;
    private $cnt = 0;


protected function _outputPrepare($data, $params){

    $params['depth'] = 0;

    $res = parent::_outputPrepare($data, $params);
    if($res){
        $this->iiif_version = (@$params['version']==2 || @$params['v']==2)?2:3;
    }
    return $res;
}

//
//
//
protected function _outputPrepareFields($params){

    $this->retrieve_detail_fields = array('file');
    $this->retrieve_header_fields = 'rec_ID,rec_RecTypeID,rec_Title';

    $this->ulf_ObfuscatedFileID = @$params['iiif_image'];
}


//
//
//
protected function _outputHeader(){

    if($this->iiif_version==2){

        $manifest_uri = self::genUUID();
        $sequence_uri = self::genUUID();

    $iiif_header = <<<IIIF
{
    "@context": "http://iiif.io/api/presentation/2/context.json",
    "@id": "http://$manifest_uri",
    "@type": "sc:Manifest",
    "label": "Heurist IIIF manifest",
    "metadata": [],
    "description": [
        {
            "@value": "[Click to edit description]",
            "@language": "en"
        }
    ],
    "license": "https://creativecommons.org/licenses/by/3.0/",
    "attribution": "[Click to edit attribution]",
    "sequences": [
        {
            "@id": "http://$sequence_uri",
            "@type": "sc:Sequence",
            "label": [
                {
                    "@value": "Normal Sequence",
                    "@language": "en"
                }
            ],
            "canvases": [
IIIF;
    }else{
            //VERSION 3
        /*
        $pageURL = 'http';
        if ($_SERVER["HTTPS"] == "on") {
            $pageURL .= "s";
        }
        $pageURL .= "://"; $_SERVER["SERVER_NAME"] */
        $manifest_uri = HEURIST_SERVER_URL.$_SERVER["REQUEST_URI"];

    $iiif_header = <<<IIIF
{
  "@context": "http://iiif.io/api/presentation/3/context.json",
  "id": "$manifest_uri",
  "type": "Manifest",
  "label": {
    "en": [
      "Heurist IIIF manifest"
    ]
  },
  "items": [
IIIF;

    }

    fwrite($this->fd, $iiif_header);

    $this->cnt = 0;
}

//
//
//
protected function _outputRecord($record){

    $canvas = self::getIiifResource($this->system, $record, $this->iiif_version, $this->ulf_ObfuscatedFileID);
    if($canvas && $canvas!=''){
        fwrite($this->fd, $this->comma.$canvas);
        $this->comma = ",\n";
        $this->cnt++;
    }
    //not more than 1000 records per manifest
    //or the only image if it is specified
    $ret = (!($this->cnt>1000 || $this->ulf_ObfuscatedFileID!=null));
    return $ret;

}

//
//
//
protected function _outputFooter(){

    if($this->iiif_version==2){
        fwrite($this->fd, ']}],"structures": []}');
    }else{
        fwrite($this->fd, ']}');
    }
}

//
// Converts heurist record to iiif canvas json
// It allows to see any media in mirador viewer
//
// return null if not media content found
//
public static function getIiifResource($system, $record, $iiif_version, $ulf_ObfuscatedFileID, $type_resource='Canvas'){

    $mysqli = $system->getMysqli();

    $canvas = '';
    $comma = '';
    $info = array();

    if($record==null){
        //find file info by obfuscation id
        $info = fileGetFullInfo($system, $ulf_ObfuscatedFileID);

        if(!empty($info)){
            $label = trim(htmlspecialchars(strip_tags($info[0]['ulf_Description'])));

            if($label==''){
                //find name from linked record
                $query = 'SELECT rec_RecTypeID, rec_Title FROM Records, recDetails '
                .'WHERE rec_ID=dtl_RecID and dtl_UploadedFileID='.$info[0]['ulf_ID']
                .' LIMIT 1';

                $record = mysql__select_row($mysqli, $query);
                $label = htmlspecialchars(strip_tags($record[1]));//rec_Title
                $rectypeID = $record[0];//rec_RecTypeID
            }else{
                $rectypeID = 5;
            }

        }else{
            $system->addError(HEURIST_NOT_FOUND, 'Resource with given id not found');
            return false;
        }

    }else{

        $label = htmlspecialchars(strip_tags($record['rec_Title']));
        $rectypeID = $record['rec_RecTypeID'];
        //1. get "file" from field values
        foreach ($record['details'] as $dty_ID=>$field_details) {
            foreach($field_details as $dtl_ID=>$file){

                if($ulf_ObfuscatedFileID){
                    if($file['file']['ulf_ObfuscatedFileID']==$ulf_ObfuscatedFileID){
                        array_push($info, $file['file']);
                        break 2;
                    }
                }else{
                    array_push($info, $file['file']);
                }
            }
        }

    }

    $label = preg_replace('/\r|\n/','\n',trim($label));

    //2. get file info
    if(!empty($info)){
        //$info = fileGetFullInfo($system, $file_ids);

        foreach($info as $fileinfo){

        $mimeType = $fileinfo['fxm_MimeType'];

        $resource_type = null;

        if(strpos($mimeType,"video/")===0){
            if(strpos($mimeType,"youtube")>0 || strpos($mimeType,"vimeo")>0) {continue;}

            $resource_type = 'Video';
        }elseif(strpos($mimeType,"audio/")===0){

            if(strpos($mimeType,"soundcloud")>0) {continue;}

            $resource_type = 'Sound';
        }elseif(strpos($mimeType, DIR_IMAGE)===0 || $fileinfo['ulf_OrigFileName']==ULF_IIIF_IMAGE){
            $resource_type = 'Image';
        }

        if (($iiif_version==2 && $resource_type!='Image') || ($resource_type==null)){
            continue;
        }

        $fileid = $fileinfo['ulf_ObfuscatedFileID'];
        $external_url = $fileinfo['ulf_ExternalFileReference'];
        if($external_url && strpos($external_url,'http://')!==0){ //download non secure external resource via heurist
            $resource_url = $external_url;  //external
        }else{
            //to itself
            $resource_url = HEURIST_BASE_URL_PRO."?db=".$system->dbname()."&file=".$fileid;
        }

        $height = 800;
        $width = 1000;
        if($resource_type=='Image' && $fileinfo['ulf_OrigFileName']!=ULF_IIIF_IMAGE){
            $img_size = getimagesize($resource_url);
            if(is_array($img_size)){
                $width = $img_size[0];
                $height = $img_size[1];
            }
        }


        $thumbfile = HEURIST_THUMB_DIR.'ulf_'.$fileid.'.png';
        if(file_exists($thumbfile)){
            $tumbnail_url = HEURIST_BASE_URL_PRO.'?db='.$system->dbname().'&thumb='.$fileid;
        }else{
            //if thumb not exists - rectype thumb (HEURIST_RTY_ICON)
            $tumbnail_url = HEURIST_BASE_URL_PRO.'?db='.$system->dbname().'&version=thumb&icon='.$rectypeID;
        }

        $service = '';
        $resource_id = '';

        //get iiif image parameters
        if($fileinfo['ulf_OrigFileName']==ULF_IIIF_IMAGE){ //this is image info - it gets all required info from json

                $iiif_manifest = loadRemoteURLContent($fileinfo['ulf_ExternalFileReference']);//retrieve iiif image.info to be included into manifest
                $iiif_manifest = json_decode($iiif_manifest, true);
                if($iiif_manifest!==false && is_array($iiif_manifest)){

                    $context = @$iiif_manifest['@context'];
                    $service_id = $iiif_manifest['@id'];
                    if(@$iiif_manifest['width']>0) {$width = $iiif_manifest['width'];}
                    if(@$iiif_manifest['height']>0) {$height = $iiif_manifest['height'];}

                    $profile = @$iiif_manifest['profile'];

                    $mimeType = null;
                    if(is_array($profile)){
                        $mimeType = @$profile[1]['formats'][0];
                        if($mimeType) {$mimeType = DIR_IMAGE.$mimeType;}
                        $profile = @$profile[0];
                    }elseif($profile==null){
                        $profile = 'level1';
                    }
                    if(!$mimeType) {$mimeType= 'image/jpeg';}

                    if(strpos($profile, 'library.stanford.edu/iiif/image-api/1.1')>0){
                        $quality = 'native';
                    }else{
                        $quality = 'default';
                    }
                    $resource_url = $iiif_manifest['@id'].'/full/full/0/'.$quality.'.jpg';
                    $resource_id = $iiif_manifest['@id'];

                    if($iiif_version==2){
$service = <<<SERVICE2
                "height": $height,
                "width": $width,
                "service" : {
                            "profile" : "$profile",
                            "@context" : "$context",
                            "@id" : "$service_id"
                          }
                ],
SERVICE2;
                    }else{
$service = <<<SERVICE3
                "height": $height,
                "width": $width,
                "service": [
                  {
                    "id": "$service_id",
                    "profile": "$profile"
                  }
                ],
SERVICE3;
//                    "type": "ImageService3"
                    }
                }
        }


        $canvas_uri = self::genUUID();

        $tumbnail_height = 200;
        $tumbnail_width = 200;

        if($iiif_version==2){ //not used - outdated for mirador v2

$item = <<<CANVAS2
{
        "@id": "http://$canvas_uri",
        "@type": "sc:Canvas",
        "label": "$label",
        "height": $height,
        "width": $width,
        "thumbnail" : {
                "@id" : "$tumbnail_url",
                "height": $tumbnail_height,
                "width": $tumbnail_width
         },
        "images": [
            {
                "@type": "oa:Annotation",
                "motivation": "sc:painting",
                "resource": {
                    $service
                    "@id": "$resource_url",
                    "@type": "dctypes:$resource_type",
                    "format": "$mimeType"
                },
                "on": "http://$canvas_uri"
            }
        ]
  }
CANVAS2;

//                    "height": $height,
//                    "width": $width
      }else{

//$annotation_uri = self::genUUID();
//  "duration": 5,
//        "height": $height,
//        "width": $width

// Returns json
if($resource_id){ //this is iiif image

    //last section
    $parts = explode('/',$resource_id);
    $cnt = count($parts)-1;
    array_splice( $parts, $cnt, 0, 'canvas');
    $canvas_uri = implode('/',$parts);
    $parts[$cnt] = 'page';
    $annopage_uri = implode('/',$parts);
    $parts[$cnt] = 'annotation';
    $annotation_uri = implode('/',$parts);
    $image_uri = $resource_id.'/info.json';


}else{
    $root_uri = HEURIST_BASE_URL_PRO.'api/'.$system->dbname().'/iiif/';
    $canvas_uri = $root_uri.'canvas/'.$fileid;
    $annopage_uri = $root_uri.'page/'.$fileid;
    $annotation_uri = $root_uri.'annotation/'.$fileid;
    $image_uri = $root_uri.DIR_IMAGE.$fileid.'/info.json';
}



$annotation = <<<ANNOTATION3
            {
              "id": "$annotation_uri",
              "type": "Annotation",
              "motivation": "painting",
              "body": {
                $service
                "id": "$resource_url",
                "type": "$resource_type",
                "format": "$mimeType"
              },
              "target": "$canvas_uri"
            }
ANNOTATION3;

if($type_resource=='annotation'){
    return $annotation;
}

$annotation_page = <<<PAGE3
        {
          "id": "$annopage_uri",
          "type": "AnnotationPage",
          "items": [
                $annotation
          ]
        }
PAGE3;

if($type_resource=='page'){
    return $annotation_page;
}

$item = <<<CANVAS3
{
      "id": "$canvas_uri",
      "type": "Canvas",
      "label": "$label",
                "height": $height,
                "width": $width,
      "items": [
           $annotation_page
      ],
      "thumbnail": [
        {
          "id": "$tumbnail_url",
          "type": "Image",
          "format": "image/png",
          "width": $tumbnail_width,
          "height": $tumbnail_height
        }
      ]

 }
CANVAS3;

/*
                "height": $height,
                "width": $width,
                "duration": 5,
*/
        }


        $canvas = $canvas.$comma.$item;
        $comma =  ",\n";

        }//for info in fileinfo

    }//!empty($file_ids)


    return $canvas;
}

//
// not used
//
private static function genUUID2() {
    return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4) );
}

//
//
//
private static function genUUID() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        // 32 bits for "time_low"
        random_int( 0, 0xffff ), random_int( 0, 0xffff ),

        // 16 bits for "time_mid"
        random_int( 0, 0xffff ),

        // 16 bits for "time_hi_and_version",
        // four most significant bits holds version number 4
        random_int( 0, 0x0fff ) | 0x4000,

        // 16 bits, 8 bits for "clk_seq_hi_res",
        // 8 bits for "clk_seq_low",
        // two most significant bits holds zero and one for variant DCE1.1
        random_int( 0, 0x3fff ) | 0x8000,

        // 48 bits for "node"
        random_int( 0, 0xffff ), random_int( 0, 0xffff ), random_int( 0, 0xffff )
    );
}


} //end class
?>