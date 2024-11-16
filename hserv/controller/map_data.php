<?php

//USED only in Gmap
//ARTEM:   @todo JJ made a lot of duplication - many methods already exist

/**
* Retrieves map data for a certain database.
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Jan Jaap de Groot  <jjedegroot@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
* @deprecated  since Heurist 6.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

require_once dirname(__FILE__).'/../../autoload.php';
require_once dirname(__FILE__).'/../records/search/recordFile.php';

$recordQuery = "SELECT * FROM Records r INNER JOIN defRecTypes d ON r.rec_RecTypeID=d.rty_ID";

$recordWhere = '(not r.rec_FlagTemporary) and ((not r.rec_NonOwnerVisibility="hidden") or '
. 'rec_OwnerUGrpID = 0 )';

$detailQuery = 'SELECT dtl_DetailTypeID, dtl_Value, '
.'rf.ulf_ObfuscatedFileID, rf.ulf_ExternalFileReference, ST_asWKT(dtl_Geo) as dtl_Geo '
.'FROM recDetails rd LEFT JOIN recUploadedFiles rf on rf.ulf_ID=rd.dtl_UploadedFileID WHERE rd.dtl_RecID=';


/**
* Finds the url that belongs to the file with the given fileID in the given system
*
* @param mixed $system System reference
* @param mixed $fileID FileID
* @return mixed Image URL
*/
function getFileURL($system, $fileID) {
    return HEURIST_BASE_URL."?db=".HEURIST_DBNAME."&download=1&debug=4&file=".$fileID;
}

/**
* Retrieves a term by its ID
*
* @param mixed $system System reference
* @param mixed $id     Term ID
*/
function _getTermByID($system, $id) {
    $term = new stdClass();

    // Select term
    $query = "SELECT * FROM defTerms WHERE trm_ID=".intval($id);
    $res = $system->getMysqli()->query($query);

    if ($res) {
        $row = $res->fetch_assoc();
        if($row) {
            $term->id = $row["trm_ID"];
            $term->label = $row["trm_Label"];
            $term->description = $row["trm_Description"];
            $term->code = $row["trm_Code"];
        }
    }

    return $term;
}

/**
* Finds a record in the database by ID
*
* @param mixed $system System reference
* @param mixed $id  Record ID
*/
function getRecordByID($system, $id) {
    global $recordQuery, $recordWhere;
    $record = new stdClass();

    // Select the record
    $query = $recordQuery.SQL_WHERE.$recordWhere." and r.rec_ID=".intval($id);
    $res = $system->getMysqli()->query($query);

    if ($res) {
        $row = $res->fetch_assoc();
        if($row) {
            // Data object containing the row values
            $record = getRecord($row);
        }
    }

    return $record;
}

/**
* Constructs a record object from a SQL result row:
* - ID: the id
* - Title: record title
* - RectypeID: record type id
*
* @param mixed $row SQL row
* @return stdClass Record object
*/
function getRecord($row) {
    $obj = new stdClass();
    $obj->id = intval($row["rec_ID"]);
    $obj->title = $row["rec_Title"];
    $obj->rectypeID = intval($row["rec_RecTypeID"]);
    $obj->rectypeName = $row["rty_Name"];
    return $obj;
}

/**
* Retrieves a detailed record data out of the database
*
* Allowed types according to field type 'Data source' (3-1083):
* - Map image file (tiled)        RT_TILED_IMAGE_SOURCE
* - KML                           RT_KML_SOURCE
* - Shapefile                     RT_SHP_SOURCE
* - Map image file (untiled)      RT_IMAGE_SOURCE
* - Mappable query                RT_QUERY_SOURCE
*
* @param mixed $system System reference
* @param mixed $id Record ID
*/
function getDetailedRecord($system, $id) {
    //echo "Get detailed record #".$id;
    $record = getRecordByID($system, $id);
    if(@$record->id){
        $record = getRecordDetails($system, $record);
    }
    return $record;
}

/**
* Adds extended details to a record
* Possible etail types:
topLayer: Top Map Layer
layers: array of Map Layers
sourceURL: URL pointing somewhere
description: Description of the record
imageType: Image type
long: Longitude
lat: Latitude
maxZoom: Maximum zoom
minZoom: Minimum zoom
Opacity: Desired opacity value
Data source: Contains a record object
minorSpan: The initial minor span on a map
thumbnail: Record thumbnail
query: Heurist query string
tilingSchema: Image tiling schema
kmlSnippet: KML snippet string
kmlFile: A .kml file reference
shapeFile: a SHP component
dbfFile: a DBF component
shxFile: a SHX component
files: Array of files
zipFile: A .zip file
*
* @param mixed $system System reference
* @param mixed $record Record reference
*/
function getRecordDetails($system, $record) {
    global $detailQuery;
    //echo "Get record details of " . ($record->id);

    // Retrieve extended details
    $query = $detailQuery . intval($record->id);
    $details = $system->getMysqli()->query($query);
    if($details) {

        $record->bookmarks = array();

        // [dtl_ID]  [dtl_RecID]  [dtl_DetailTypeID]  [dtl_Value] [dtl_AddedByImport]  [ulf_ObfuscatedFileID]   [dtl_Geo]  [dtl_ValShortened]  [dtl_Modified]
        while($detail = $details->fetch_assoc()) {
            // Fields
            //print_r($detail);
            $type = $detail["dtl_DetailTypeID"];
            $value = $detail["dtl_Value"];
            $fileID = $detail["ulf_ObfuscatedFileID"];
            $geo_value = $detail["dtl_Geo"];
            $fileID = $detail["ulf_ObfuscatedFileID"];
            $external_url = $detail['ulf_ExternalFileReference'];

            /* GENERAL */
            if($type == DT_NAME) {
                $record->name = $value; //for layers use it instead of title (rec_Title)
            }elseif($type == DT_SHORT_SUMMARY) {
                // Description
                $record->description = $value;

            }elseif($type == DT_CREATOR) {
                // Creators
                if(!property_exists($record, "creators")) {
                    $record->creators = array();
                }
                array_push($record->creators, getRecordByID($system, $value));


                /* SOURCE */
            }elseif(defined('DT_SERVICE_URL') && $type == DT_SERVICE_URL) {
                // Source URL

                if($fileID){

                    if(!(strpos($external_url,'http://')===0 || strpos($external_url,'https://')===0)){
                        $external_url = HEURIST_TILESTACKS_URL.$external_url;
                    }

                    $record->sourceURL = $external_url;

                }else{
                    $record->sourceURL = $value;
                }

            }elseif(defined('DT_DATA_SOURCE') && $type == DT_DATA_SOURCE) {
                // Data source
                $record->dataSource = getDetailedRecord($system, $value);


                /* MAP LAYERS */
            }elseif(defined('DT_MAP_LAYER') && $type == DT_MAP_LAYER) {
                // Map layer
                if(!property_exists($record, "layers")) { // Recursive
                    $record->layers = array();
                }
                array_push($record->layers, getDetailedRecord($system, $value));

            }elseif(defined('DT_MAP_BOOKMARK') && $type == DT_MAP_BOOKMARK) {
                //string in format <xmin>,<xmax>,<ymin>,<ymax>,<tmin>,<tmax>
                array_push($record->bookmarks, explode(',', $value));

                /* LOCATION */
            }elseif(defined('DT_SYMBOLOGY_POINTMARKER') && $type == DT_SYMBOLOGY_POINTMARKER) {
                //marker icon url
                $record->iconMarker = $fileID; //getFileURL($system, $fileID);

            }elseif(defined('DT_MAXIMUM_ZOOM') && $type == DT_MAXIMUM_ZOOM) {
                // Maximum zoom
                $record->maxZoom = floatval($value);

            }elseif(defined('DT_MINIMUM_ZOOM') && $type == DT_MINIMUM_ZOOM) {
                // Minimum zoom
                $record->minZoom = floatval($value);



            }elseif(defined('DT_OPACITY') && $type == DT_OPACITY) {
                // Opacity
                $record->opacity = floatval($value);

            }elseif(defined('DT_SYMBOLOGY_COLOR') && $type == DT_SYMBOLOGY_COLOR) {
                // Color - take value from term code
                $color = _getTermByID($system, $value);

                $record->color_id = $value;
                $record->color_code = $color->code;
                if($color->code) {
                    $record->color = $color->code;
                }
                /* alas timemap understands color only as hex or rgb
                else{
                $record->color = $color->label;
                }*/

            } elseif(defined('DT_THUMBNAIL') && $type == DT_THUMBNAIL) {
                // Uploaded thumbnail
                $record->thumbnail = getFileURL($system, $fileID);

            } elseif(defined('DT_MIME_TYPE') && $type == DT_MIME_TYPE) {
                // Mime type
                $record->mimeType = _getTermByID($system, $value);

            } elseif(defined('DT_IMAGE_TYPE') && $type == DT_IMAGE_TYPE) {
                // Tiled image type
                $record->imageType = _getTermByID($system, $value);

            }elseif(defined('DT_MAP_IMAGE_LAYER_SCHEMA') && $type == DT_MAP_IMAGE_LAYER_SCHEMA) {
                // Image tiling schema
                $record->tilingSchema = _getTermByID($system, $value);


                /* SNIPPET */
            }elseif(defined('DT_QUERY_STRING') && $type == DT_QUERY_STRING) {
                // Heurist query string
                $record->query = $value;

            }elseif(defined('DT_KML') && $type == DT_KML) {
                // KML snippet
                $record->kmlSnippet = 1;


                /* FILES */
            }elseif(defined('DT_FILE_RESOURCE') && $type == DT_FILE_RESOURCE) {
                // File(s)
                if(!property_exists($record, "files")) {
                    $record->files = array();
                }
                array_push($record->files, getFileURL($system, $fileID));

            }elseif(defined('DT_SHAPE_FILE') && $type == DT_SHAPE_FILE) {
                // Shape file (SHP component)
                $record->shpFile = getFileURL($system, $fileID);

            }elseif(defined('DT_DBF_FILE') && $type == DT_DBF_FILE) {
                // DBF file (DBF component)
                $record->dbfFile = getFileUrl($system, $fileID);

            }elseif(defined('DT_SHX_FILE') && $type == DT_SHX_FILE) {
                // SHX file (SHX component)
                $record->shxFile = getFileURL($system, $fileID);

            }elseif(defined('DT_ZIP_FILE') && $type == DT_ZIP_FILE) {
                // Zip file
                $record->zipFile = getFileURL($system, $fileID);

            }elseif(defined('DT_GEO_OBJECT') && $type == DT_GEO_OBJECT) {
                // Zip file
                $record->bounds = $geo_value;
            }
        }
    }
    return $record;
}



/**
* Returns an array of all Map Documents for this database
*
* Document object:
* -----------------------------------------
* - id: Record ID
* - title: Record title
* - rectypeID: Record type ID
* -----------------------------------------
* - topLayer: Layer object
* - layers: Array of Layer objects
* - long: Longitude
* - lat: Latitude
* - minZoom: Minimum zoom
* - maxZoom: Maximum zoom
* - minorSpan: Initial minor span in degrees
* - thumbnail: Thumbnail file
* ------------------------------------------
*
* @param mixed $system System reference
*/
function getMapDocuments($system, $recId) {
    //echo "getMapDocuments() called!";
    global $recordQuery, $recordWhere;
    global $detailQuery;
    $documents = array();

    if(defined('RT_MAP_DOCUMENT') && RT_MAP_DOCUMENT>0){
        // Select all Map Document types
        $query = $recordQuery.SQL_WHERE.$recordWhere." and rec_RecTypeID=".intval(RT_MAP_DOCUMENT);//InOriginatingDB

        if($recId>0){
            $query = $query . ' and rec_ID='.intval($recId);
        }

        $mysqli = $system->getMysqli();
        $res = $mysqli->query($query);
        if ($res) {
            // Loop through all rows
            while($row = $res->fetch_assoc()) {
                // Document object containing the row values
                $document = getRecord($row);
                $document = getRecordDetails($system, $document);
                //print_r($document);
                array_push($documents, $document);
            }
        }
    }
    //print_r($documents);
    return $documents;
}

// Initialize a System object that uses the requested database
$system = new hserv\System();

if( $system->init(@$_REQUEST['db']) ){

    $wg_ids = $system->getUserGroupIds();
    if($wg_ids==null) {$wg_ids = array();}
    array_push($wg_ids, 0);
    $recordWhere = '(not r.rec_FlagTemporary) and ((not r.rec_NonOwnerVisibility="hidden") or '
    . 'rec_OwnerUGrpID in (' . join(',', $wg_ids).') )';

    $system->defineConstants();
    // Get all Map Documents
    $response = array("status"=>HEURIST_OK, "data"=>getMapDocuments($system, @$_REQUEST['id']));

}else {
    // Show construction error
    $response = $system->getError();
}

// Return the response object as JSON
$system->setResponseHeader();
print json_encode($response);
?>