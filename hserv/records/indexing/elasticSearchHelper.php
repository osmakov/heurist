<?php

    /**
     * elasticHelper.php: Functions to help interacting with ElasticSearch, mainly used by elasticSearch.php
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


    //****************************************************************************************************************
    // ElasticSearch index helpers
    //****************************************************************************************************************

    $isElasticUp = NULL; // Global variable whether or not Elastic indexing is enabled & operational

    /**
     * Checks if ElasticSearch indexing is enabled in configIni.php
     * @return bool True if Elastic indexing is enabled.
     */
    function isElasticEnabled() {
        global $indexServerAddress, $indexServerPort; // Set in configIni.php
        return !empty($indexServerAddress) && !empty($indexServerPort);
    }

    /**
     * Determines the ElasticSearch index name for a database
     * @param string $dbName Name of the database
     * @return string Name of the ElasticSearch index
     */
    function getElasticIndex($dbName) {

        list($database_name_full, $database_name) = mysql__get_names( $dbName );

        $elasticIndex = strtolower($database_name);// Must be lowercase
        preg_replace('/[^A-Za-z0-9 ]/', '_', $elasticIndex);// Replace non-alphanumeric with underscore
        return $elasticIndex;
    }

    /**
     * Determines the ElasticSearch address based on the given parameters
     * @param string $dbName Name of the database
     * @param int $recTypeID Record Type ID
     * @param int $recID Record ID
     * @return string The ElasticSearch address for the given parameters
     */
    function getElasticAddress($dbName=null, $recTypeID=null, $recID=null) {
        global $indexServerAddress, $indexServerPort; // Set in configIni.php
        $url = $indexServerAddress .':'. $indexServerPort;

        // dbName check
        if(!empty($dbName)) {
            $url .= '/' . getElasticIndex($dbName);
        }

        // recTypeID check
        if(!empty($recTypeID)) {
            $url .= '/' . $recTypeID;
        }

        // recID check
        if(!empty($recID)) {
            $url .= '/' . $recID;
        }

        return $url;
    }

    /**
     * Checks if the given ElasticSearch address is created by checking if the HTTP Code is 200.
     * @param string $address The address to check
     * @return bool True if the given address is created
     */
    function isElasticAddressCreated($address) {
        $handle = curl_init($address);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

        curl_exec($handle);
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        return $httpCode == 200;
    }

    /**
     * Checks if an ElasticSearch index exists for the given database
     * @param string $dbName Name of the database
     * @return bool True if an index has already been created
     */
    function isElasticIndexCreated($dbName) {
        $address = getElasticAddress($dbName);
        return isElasticAddressCreated($address);
    }

    /**
     * Checks if ElasticSearch is running
     * @return bool Returns true if ElasticSearch is running
     */
    function isElasticRunning() {
        $address = getElasticAddress();
        return isElasticAddressCreated($address);
    }

    /**
     * Checks if ElasticSearch indexing is enabled and if ElasticSearch is running
     * @return bool Returns true if ElasticSearch enabled & running
     */
    function isElasticUp() {
        global $isElasticUp;
        if($isElasticUp == NULL) {
            $isElasticUp = isElasticEnabled() && isElasticRunning();
        }
        return $isElasticUp;
    }


    //****************************************************************************************************************
    // ElasticSearch query helpers
    //****************************************************************************************************************

    // GET-request to address with query
    function getElastic($address, $query) {
        $curl = curl_init();
        return queryElastic($curl, $address, $query);
    }

    // POST-request to adress with query
    function postElastic($address, $query) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        return queryElastic($curl, $address, $query);
    }

    // PUT-request to address with query
    function putElastic($address, $query) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
        return queryElastic($curl, $address, $query);
    }

    // UPDATE-request to adress with query
    function updateElastic($address, $query) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        return queryElastic($curl, $address.'/_update_by_query', $query);
    }

    // DELETE-request to adress with query
    function deleteElastic($address, $query) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        return queryElastic($curl, $address, $query);
    }

    /**
     * @param resource $curl Curl instance reference
     * @param string $address ElasticSearch instance address
     * @param stdClass $query The query to send to the ElasticSearch instance
     * @return string ElasticSearch response (JSON)
     */
    function queryElastic($curl, $address, $query) {
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(CTYPE_JSON));
        curl_setopt($curl, CURLOPT_URL, $address);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($query));
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);// Max 10 seconds connection time
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);// Max 60 seconds query time

        $json = curl_exec($curl);
        curl_close($curl);
        //error_log("[elasticSearchHelper.php] Query $query --> resulted in $json");

        return $json;
    }

    /**
     * Checks if the response from ElasticSearch has the given property, and if so, if this property holds the value 'true'
     * @param string $json JSON received as ElasticSearch response
     * @param string $property The property to check the value for
     * @return bool True if $property exists and is true.
     */
    function checkElasticResponse($json, $property) {
        if ($json != NULL) {
            $response = json_decode($json);
            return property_exists($response, $property) && $response->$property;
        }
        return false;
    }


    //****************************************************************************************************************
    // ElasticSearch synchronisation helpers
    //****************************************************************************************************************

    /**
     * Called from /admin/setup/createNewDB.php upon database creation.
     * Sets up the correct Elastic mapping to automatically add an additional not_analyzed field to each field.
     */
    function createElasticIndex($database) {
        if(isElasticUp()) {
            $query = '{
                        "settings": {
                            "number_of_replicas": 1,
                            "number_of_shards": 1
                        },
                        "mappings": {
                            "_default_": {
                                "dynamic_templates": [
                                    {
                                        "raw_template": {
                                            "match": "*",
                                            "match_mapping_type": "*",
                                            "mapping": {
                                                "type": "string",
                                                "fields": {
                                                    "raw": {
                                                        "type":  "string",
                                                        "index": "not_analyzed"
                                                    }
                                                }
                                            }
                                        }
                                    }
                                ]
                            }
                        }
                     }';

            $address = getElasticAddress($database);
            $json = putElastic($address, json_decode($query));

            if(!checkElasticResponse($json, 'acknowledged')) {
                error_log("[elasticSearchHelper.php] Failed to create dynamic template for index $database : $json");
            }
        }
    }
?>