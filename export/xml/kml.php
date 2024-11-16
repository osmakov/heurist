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
* Returns kml for given record id. It searches detail with type 221 or 551
*
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Export/xml
* @todo - only one kml per record, perhaps need to return the combination of kml
*/
use hserv\utilities\USanitize;

require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../../hserv/records/search/recordSearch.php';
require_once dirname(__FILE__).'/../../hserv/utilities/Temporal.php';
require_once dirname(__FILE__).'/../../vendor/autoload.php';//for geoPHP

$system = new hserv\System();
if( !$system->init(@$_REQUEST['db']) ){
    die("Cannot connect to database");
}

define('KML_CLOSE','</Document></kml>');

$islist = array_key_exists("q", $_REQUEST);

if(@$_REQUEST['file']==1 || @$_REQUEST['file']===true){
header("Cache-Control: public");
header("Content-Description: File Transfer");
header("Content-Disposition: attachment; filename=\"export.kml\"");
}
header("Content-Type: text/xml; charset=utf-8");

$dtFile = ($system->defineConstant('DT_FILE_RESOURCE')?DT_FILE_RESOURCE:0);
$dtKMLfile = ($system->defineConstant('DT_KML_FILE')?DT_KML_FILE:0);
$dtKML = ($system->defineConstant('DT_KML')?DT_KML:0);
$dtDate = ($system->defineConstant('DT_DATE')?DT_DATE:0);
$dtDateStart = ($system->defineConstant('DT_START_DATE')?DT_START_DATE:0);
$dtDateEnd = ($system->defineConstant('DT_END_DATE')?DT_END_DATE:0);



$mysqli = $system->getMysqli();

/*
1. just print out content of uploaded kml files
*/
if(!$islist){

    if(array_key_exists("id", $_REQUEST) && $_REQUEST["id"]!="")
    {
        //kml is stored in uploaded file
        $kml_file = mysql__select_row($mysqli,
            'select ulf_FilePath,ulf_FileName from recDetails '
            .'left join recUploadedFiles on ulf_ID = dtl_UploadedFileID where dtl_RecID = '
            . intval($_REQUEST["id"]) . " and (dtl_DetailTypeID = "
            .$dtFile." OR dtl_DetailTypeID = ".$dtKMLfile.")");

        if ($kml_file[0] && $kml_file[1]) {
            $path =  USanitize::sanitizePath(resolveFilePath($kml_file[0]));
            $kml_file = basename($kml_file[1]);
            $kml_file = $path.$kml_file;
        }else{

            $kml_file = tempnam(HEURIST_SCRATCHSPACE_DIR, "kml");
            //$tmp_destination = fopen(TEMP_MEMORY, 'w');//less than 1MB in memory otherwise as temp file
            $kml_file_stream = fopen($kml_file, 'w');

            //kml snippet
            $kml = mysql__select_value($mysqli, "select dtl_Value from recDetails where dtl_RecID = "
                            . intval($_REQUEST["id"]) . " and dtl_DetailTypeID = ".$dtKML);

            //write to temp file (to avoid Cross-site Scripting warning)
            fwrite($kml_file_stream, XML_HEADER."\n");
            fwrite($kml_file_stream, '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom"><Document>');

            if($kml!=null){
                fwrite($kml_file_stream, $kml);
            }

            fwrite($kml_file_stream, KML_CLOSE);
            fclose($kml_file_stream);
        }

        if(file_exists($kml_file)){
            //todo: use readfile_by_chunks
            print file_get_contents($kml_file);
        }


    }
    exit;
}

$kml_file = tempnam(HEURIST_SCRATCHSPACE_DIR, "kml");
$kml_file_stream = fopen($kml_file, 'w');

fwrite($kml_file_stream, XML_HEADER."\n");
fwrite($kml_file_stream, '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">');
fwrite($kml_file_stream, '<Document>');
fwrite($kml_file_stream, '<name>Exported from Heurist</name>');

/*
2. create new KML output that contains placemarks created from WKT and links to heurist's uploaded kml files
*/
if($islist || (array_key_exists("id", $_REQUEST) && $_REQUEST["id"]!="")){

    // for wkt
    $squery = "select rec_ID, rec_URL, rec_Title, d0.dtl_DetailTypeID, d0.dtl_Value, if(d0.dtl_Geo is null, null, ST_asWKT(d0.dtl_Geo)) as dtl_Geo, ".
    "d1.dtl_Value as Date0, d2.dtl_Value as DateStart, d3.dtl_Value as DateEnd ";
    $ourwhere = " and (d0.dtl_RecID=rec_ID) and (d0.dtl_Geo is not null ".($dtKML>0?" or d0.dtl_DetailTypeID=".intval($dtKML):"").")";

    $detTable =
    " left join recDetails d1 on d1.dtl_RecID=rec_ID and d1.dtl_DetailTypeID=".intval($dtDate).
    " left join recDetails d2 on d2.dtl_RecID=rec_ID and d2.dtl_DetailTypeID=".intval($dtDateStart).
    " left join recDetails d3 on d3.dtl_RecID=rec_ID and d3.dtl_DetailTypeID=".intval($dtDateEnd).
    ", recDetails d0";

    //for kml
    $squery2 = "select  rec_ID, rec_URL, rec_Title, ulf_ID, ulf_FilePath, ulf_FileName ";

    $ourwhere2 = " and (dtl_RecID=rec_ID) and (dtl_DetailTypeID=".intval($dtKMLfile)
            .($dtFile>0?" or (dtl_DetailTypeID = ".intval($dtFile)." AND ulf_MimeExt='kml'))":")");

    $detTable2 = ", recDetails left join recUploadedFiles on ulf_ID = dtl_UploadedFileID";

    $isSearchKml = ($dtKMLfile>0 || $dtFile>0);

    if($islist){

            $_REQUEST['detail'] = 'ids';// return ids only

            $result = recordSearch($system, $_REQUEST);//see recordSearch.php

            if(!(@$result['status']==HEURIST_OK && @$result['data']['reccount']>0)){
                $error_msg = $system->getError();
                $error_msg = $error_msg[0]['message'];
                fwrite($kml_file_stream, $error_msg);
                fwrite($kml_file_stream, KML_CLOSE);
                fclose($kml_file_stream);
                print file_get_contents($kml_file);
                return;
            }
            $result = $result['data'];
            $rec_ids = $result['records'];
            $limit = intval(@$_REQUEST['limit']);
            if($limit>0){
                $rec_ids = array_slice($rec_ids,0,$limit);
            }

            $squery = _composeQuery($squery, $detTable, $rec_ids, $ourwhere);
            $squery2 = _composeQuery($squery2, $detTable2, $rec_ids, $ourwhere2);

    }else{

        $squery = _composeQuery($squery, $detTable, $_REQUEST["id"], $ourwhere);
        $squery2 = _composeQuery($squery2, $detTable2, $_REQUEST["id"], $ourwhere2);
    }

    $wkt_reccount=0;
    $kml_reccount=0;

    if($squery){

        $res = $mysqli->query($squery);
        if($res===false){
            fwrite($kml_file_stream, KML_CLOSE);
            return;
        }
        $wkt_reccount = $res->num_rows;

        $kml_reccount = 0;
        if($isSearchKml){
            $res2 = $mysqli->query($squery2);
            if($res2!==false){
                $kml_reccount = $res2->num_rows;
            }
        }

    }

    if ($wkt_reccount>0 || $kml_reccount>0)
    {

        if($wkt_reccount>0){
            while ($row = $res->fetch_row()) {
                $kml = null;

                if($row[5]){ //dtl_Geo
                    $wkt = $row[5];
                    $geom = geoPHP::load($wkt,'wkt');
                    $kml = $geom->out('kml');
                    if($kml){
                        fwrite($kml_file_stream, '<Placemark>');

                        //timestap or timespan
                        if($row[6] || $row[7] || $row[8]){

                            if($row[7] || $row[8]){

                                if(!$row[7]) {$row[7] = $row[8];}

                                //create timespan from two temporal objects
                                $dt = Temporal::mergeTemporals($row[7], $row[8]);

                            }elseif($row[6]){
                                $dt = new Temporal($row[6]);
                            }
                            if($dt && $dt->isValid()){
                                fwrite($kml_file_stream, $dt->toKML());
                            }
                        }

                        fwrite($kml_file_stream, '<id>'.htmlspecialchars($row[0]).'</id>');
                        fwrite($kml_file_stream, '<name>'.htmlspecialchars ($row[2]).'</name>');
                        if($row[1]){ //  FILTER_SANITIZE_SPECIAL_CHARS
                            $url = htmlentities($row[1]);
                            fwrite($kml_file_stream, '<description><![CDATA[ <a href="'.$url.'">link</a>]]></description>');
                        }
                        fwrite($kml_file_stream, $kml);
                        fwrite($kml_file_stream, '</Placemark>');
                    }

                }
            }//while wkt records
        }

        if($kml_reccount>0){
            while ($file_data = $res2->fetch_row()) {
                if ($file_data[3]) {

                    $file_id = intval($file_data[0]);

                    fwrite($kml_file_stream, '<NetworkLink>');
                    fwrite($kml_file_stream, '<name>'.htmlspecialchars($file_data[2]).'</name>');
                    fwrite($kml_file_stream, '<Link id="'.$file_id.'">');
                    fwrite($kml_file_stream, '<href>'.HEURIST_BASE_URL.'export/xml/kml.php?id='.$file_id.'</href>');
                    fwrite($kml_file_stream, '</Link>');
                    fwrite($kml_file_stream, '</NetworkLink>');
                }
            }//while kml records
        }


    }
}
fwrite($kml_file_stream, KML_CLOSE);
fclose($kml_file_stream);
print file_get_contents($kml_file);


//
//
//
function _composeQuery($select,$from,$rec_ids,$where){

   if(is_array($rec_ids)){
        $where_ids = 'in ('.implode(',', prepareIds($rec_ids)).')';
   }else{
        $where_ids = '='.intval($rec_ids);
   }

   $squery = "$select from Records $from WHERE rec_ID $where_ids $where";

   return $squery;
}
?>