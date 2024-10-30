<?php

    /**
    * elasticSearch.php: Functions to interact with ElasticSearch
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
    * @author      Artem Osmakov   <osmakov@gmail.com>
    * @author      Jan Jaap de Groot    <jjedegroot@gmail.com>
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
    A hook has been added in createNewDB.php which calls createElasticIndex($newDBName).
    The method createElasticIndex checks if Elastic has been enabled, and if it is running.
    If that is the case, it creates the index for the given database.

    The index that is created has a dynamic_mapping, which adds an additional .raw field for every string field.
    The raw field is not_analyzed which means it is not processed by Elastic.
    By default, strings are processed and cut into pieces for fuzzy searching.
    - Fuzzy searching can be done against the normal field
    - Exact matching or aggregations should be done against the raw field.

    Additional hooks have been added to methods that add or modify the underlaying MySQL database.
    With the help of these hooks the MySQL database and ElasticSearch index should always be synchronized.

    The methods getHighestMySqlTimestamp() and getHighestElasticTimestamp() are used to
    determine if the timestamps used in each system are equal. This check is done during
    each of the functions below, to make sure the systems are synchronized at all times.


    PUBLIC METHODS:

    updateRecordIndexEntry() -> whenever a record is written (new or updated, from record edit or record import or record recode)
    - db_record.php

    deleteRecordIndexEntry() -> whenever a record is saved with a different type (editRecord)
    - db_record.php

    deleteIndexForDatabase -> whenever a database is deleted (admin actions)
    - clearCurrentDB.php
    - deleteCurrentDB.php

    buildAllIndices() -> whenever a database upgrade has occurred or when a database has been cloned
    - uses deleteIndexForRectype()
    - uses buildAllIndices()
    - cloneDB.php
    - rebuildLuceneIndices.php

    */
require_once 'elasticSearchHelper.php';

// it is assumed that $system is already inited
class ElasticSearch {

     /**
     * Construct won't be called inside this class and is uncallable from
     * the outside. This prevents instantiating this class.
     * This is by purpose, because we want a static class.
     */
    private function __construct() {}
    private static $mysqli = null;
    private static $initialized = false;


    private static function initialize()
    {
        if (self::$initialized)  {return;}

        global $system;
        self::$mysqli = $system->get_mysqli();
        self::$initialized = true;
    }

    // ****************************************************************************************************************
    /**
    * Add a new key or update an existing key - ElasticSearch adds or updates as appropriate, no need to specify
    * By reading record from database we ensure that we are indexing only records which have been successfully written
    * @param string $dbName  The name of the Heurist database, excluding prefix
    * @param int $recTypeID  The record type ID of the record being indexed
    * @return bool True if successful
    */
    public static function updateRecordIndexEntry ($dbName, $recTypeID, $recID) {

        if(isElasticUp()) {

            self::initialize();

            $record = new stdClass();

            // Retrieve record level data
            $query = "SELECT rec_URL,rec_Added,rec_Modified,rec_Title,rec_RecTypeID,rec_AddedByUGrpID,rec_AddedByImport,rec_Popularity,".
            "rec_FlagTemporary,rec_OwnerUGrpID,rec_NonOwnerVisibility,rec_URLLastVerified,rec_URLErrorMessage,rec_URLExtensionForMimeType ".
            "from Records where rec_ID=".intval($recID);// omits scratchpad
            $res = self::$mysqli->query($query);

            // Check if query has succeed
            if ($res) {
                $row = $res->fetch_row();// Fetch record data

                // Construct record
                $record->URL            = $row[0];
                $record->Added          = $row[1];
                $record->Modified       = $row[2];
                $record->Title          = $row[3];
                $record->RecTypeID      = $row[4];
                $record->AddedBy        = $row[5];
                $record->Imported       = $row[6];
                $record->Popularity     = $row[7];
                $record->Temporary      = $row[8];
                $record->OwnerUGrpID    = $row[9];
                $record->NonOwnerVis    = $row[10];
                $record->URLLastVerif   = $row[11];
                $record->URLErrMsg      = $row[12];
                $record->URLExtMimeType = $row[13];

                // Check if recTypeID has changed
                if($record->RecTypeID != $recTypeID) {
                    // Delete index for old record type before updating index for new record type
                    self::deleteRecordIndexEntry($dbName, $recTypeID, $recID);
                }
                $res->close();
            } else {
                error_log("[elasticSearch.php] updateRecordIndexEntry --> record query failed: $query");
                return false;
            }

            // Retrieve detail level data
            $query = "SELECT dtl_DetailTypeID,dtl_Value,dtl_UploadedFileID,dtl_Geo from recDetails where dtl_RecID=".intval($recID);
            $res = self::$mysqli->query($query);

            // Check if query has succeeded
            if ($res) {
                // Append detail level data to record
                while ($row = $res->fetch_row()) {
                    // Detail ID is used as key, together with dtl_Value, dtl_UploadedFileID and dtl_Geo
                    // TODO: should use dtl_Value OR dtl_UploadedFileID OT dtl_Geo according to detail type
                    $record->$row[0] = $row[1].$row[2].$row[3];
                }
                $res->close();
            }else{
                error_log("[elasticSearch.php] updateRecordIndexEntry --> details query failed: $query");
                return false;
            }

            // PUT data to ElasticSearch
            $address = getElasticAddress($dbName, $recTypeID, $recID);
            $json = putElastic($address, $record);

            // Check if created
            if(checkElasticResponse($json, 'created')) {
                self::checkElasticSync();
                return true;
            }
        }
        return false;
    } // addUpdateRecordIndex



    // ****************************************************************************************************************
    // Note: Reported bug in PHP @ 18/11/13: must reset to null to obtain internal default.
    //       Resetting directly to eg. PUT or GET will not reset, it will remain set as DELETE
    // ****************************************************************************************************************


    // ****************************************************************************************************************
    /**
    * Delete the index entry for a specified record - use when record type changed or record deleted
    * @param string $dbName The name of the Heurist databasem, excluding prefix
    * @param int $recTypeID The record type ID of the record being deleted from the index
    * @param int $recID     The record to be deleted from the index
    * @return bool True if successful
    */
    public static function deleteRecordIndexEntry ($dbName, $recTypeID, $recID ) {
        if(isElasticUp()) {

            // Delete record from ElasticSearch
            $address = getElasticAddress($dbName, $recTypeID, $recID);
            $query = new stdClass();
            $json = deleteElastic($address, $query);

            // Check if acknowledged
            if(checkElasticResponse($json, 'acknowledged')) {
                self::checkElasticSync();
                return true;
            }
        }
        return false;
    } // deleteRecordIndex


    // ****************************************************************************************************************
    /**
    * Delete the index for a specified record type
    * @param string $dbName The name of the Heurist database, excluding prefix
    * @param int $recTypeID The record type ID of the record being deleted from the index
    * @return bool True if successfully deleted RecType from index.
    */
    private static function deleteIndexForRectype ($dbName, $recTypeID) {
        if(isElasticUp()) {
            // Delete record from ElasticSearch
            $address = getElasticAddress($dbName, $recTypeID);
            $query = new stdClass();
            $json = deleteElastic($address, $query);

            // Check if acknowledged
            if(checkElasticResponse($json, 'acknowledged')) {
                self::checkElasticSync();
                return true;
            }
        }
        return false;
    } // deleteIndexForRectype


    // ****************************************************************************************************************
    /**
    * Delete the index for a specified database
    * @param string $dbName The name of the Heurist database, excluding prefix
    * @return bool True if successfully deleted complete index from ElasticSearch.
    */
    public static function deleteIndexForDatabase ($dbName) {
        if(isElasticUp()) {

            // Delete record from ElasticSearch
            $address = getElasticAddress($dbName);
            $query = new stdClass();
            $json = deleteElastic($address, $query);

            // Check if acknowledged
            if(checkElasticResponse($json, 'acknowledged')) {
                self::checkElasticSync();
                return true;
            }
        }
        return false;
    } // deleteIndexForDatabase


    // ****************************************************************************************************************
    /**
    * Rebuild the index for a specified record type
    * @param string $dbName   The name of the Heurist databasem, excluding prefix
    * @param int $recTypeID   The record type to rebuild for
    * @return bool True if successful
    */
    public function buildIndexForRectype ($dbName, $recTypeID) {
        if(isElasticUp()) {

            self::initialize();

            self::deleteIndexForRectype ($dbName, $recTypeID);// clear the existing index

            $query = "SELECT rec_ID FROM Records WHERE rec_RecTypeID = $recTypeID";
            $res = self::$mysqli->query($query);

            if ($res) {
                while ($row = $res->fetch_row()) { // fetch records
                    // Update all records while successful
                    if(!self::updateRecordIndexEntry ($dbName, $recTypeID, $row[0]/*recID*/)) {
                        return false;
                    }
                }
                $res->close();
                return true;
            }else{
                error_log("[elasticSearch.php] buildIndexForRectype --> invalid query: $query");
            }
        }
        return false;
    } // buildIndexForRectype


    // ****************************************************************************************************************
    /**
    * Rebuild the index for all record types
    * @param string $dbName The name of the Heurist database, excluding prefix
    * @return bool True if OK, false if Error
    */
    public static function buildAllIndices ($dbName, $print=true) {
        if(!isElasticUp()) {
            print "ElasticSearch service not detected";
            return false;
        }

            if ($print){
                print "Building all Elasticsearch indices for: $dbName<br>";
            }
            $query = "SELECT MAX(rec_RecTypeID) FROM Records WHERE 1";
            $res = self::$mysqli->query($query);
            $count = 0;

            if ($res) {
                $row = $res->fetch_row();
                $maxRecTypeID = $row[0];

                // Index all record types that exist
                for ($i = 1; $i <= $maxRecTypeID; $i++) {
                    if(self::buildIndexForRectype ($dbName, $i)) {
                        $count++;
                    }
                }

                if ($print){
                    print "ElasticSearch indices have successfully been built for $count record types.";
                }
                $res->close();
                return true;
            }


        error_log("[elasticSearch.php] buildAllIndices --> invalid query: $query");
        return false;
    } // buildAllIndices

    /**
     * Checks if ElasticSearch is synchronised, called by functions in elasticSearch.php
     */
    private static function checkElasticSync($dbName) {

        self::initialize();
        // 1. Retrieve highest MySQL timestamp
        $mysqlTimestamp = self::getHighestMySqlTimestamp();

        // 2. Retrieve highest Elastic timestamp
        $elasticTimestamp = self::getHighestElasticTimestamp($dbName);

        // 3. Compare timestamps
        if($mysqlTimestamp != null && $elasticTimestamp != null) {
            if(strcmp($mysqlTimestamp, $elasticTimestamp) !== 0) {
                // The timestamps are not equal. Note that ElasticSearch indexing takes ~100ms.
                //error_log("[elasticSearchHelper.php] mysqlTimestamp: $mysqlTimestamp & elasticTimestamp: $elasticTimestamp are not equal.");
            }
        }
    }

    /**
     * Attempts to retrieve the highest rec_Modified timestamp in the MySql database
     * @return null|string Null, or timestamp in the following form: 2017-05-16 11:26:52
     */
    private static function getHighestMySqlTimestamp() {

        $query = 'SELECT MAX(rec_Modified) FROM Records';
        $res = mysql__select_value(self::$mysqli, $query);

        if ($res) {
            return $res; // Gets the rec_Modified value from the first row.
        } else {
            error_log("[elasticSearchHelper.php] getHighestMySqlTimestamp failed - query: $query");
        }
        return null;
    }

    /**
     * Attempts to retrieve the highest rec_Modified timestamp in the Elastic instance
     * @return null|string Null, or timestamp in the following form: 2017-05-16 11:26:52
     */
    private static function getHighestElasticTimestamp($dbName) {
        $address = getElasticAddress($dbName) . '/_search?size=1';
        $query = '{
                    "query": {
                        "match_all": {}
                    },
                    "sort": {
                        "Modified.raw": {
                            "order" : "desc"
                        }
                    }
                  }';
        $json = postElastic($address, json_decode($query));

        if ($json != null) {
            $response = json_decode($json);
            return $response->hits->hits[0]->_source->Modified; // Gets the Modified value from the first hit.
        }else{
            error_log("[elasticSearchHelper.php] getHighestElasticTimestamp failed - query: $query");
        }

        return null;
    }

}
?>
