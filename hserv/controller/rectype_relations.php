<?php

    /**
    * Determines rectype relations for a certain database.
    *
    * @package     Heurist academic knowledge management system
    * @link        https://HeuristNetwork.org
    * @copyright   (C) 2005-2023 University of Sydney
    * @author      Jan Jaap de Groot  <jjedegroot@gmail.com>
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

    require_once dirname(__FILE__).'/../../autoload.php';

    if(isset($_REQUEST['db'])) {
        $dbName = $_REQUEST['db'];

        // Initialize a System object that uses the requested database
        $system = new hserv\System();
        if( $system->init($dbName) ){
            // Result object
            $result = new stdClass();
            $result->HeuristVersion = HEURIST_VERSION;
            $result->HeuristBaseURL = HEURIST_BASE_URL;
            $result->HeuristDBName = $dbName;


            // Retrieving all nodes
            $rectypes = getRectypes($system);
            $result->nodes = $rectypes;

            // Retrieving all links
            $links = getLinks($system, $rectypes);
            $result->links = $links;

            $system->dbclose();

            // Returning result as JSON
            header(CTYPE_JSON);
            print json_encode($result);
        }else {
            // Show construction error
            echo $system->getError();
        }
    }else{
        echo "\"db\" parameter is required";
    }

    /**
    * Retrieves all RecTypes
    * @param mixed $system System reference
    * @return Array of nodes
    */
    function getRectypes($system) {
        $rectypes = array();

        // Select all rectype ids, names and count the occurence in the Record table. The defRectypes table is used to retrieve all record types in a certain database an the Records table is used to determine the occurence.
        $query = "SELECT d.rty_ID as id, d.rty_Name as name, sum(if(r.rec_FlagTemporary!=1, 1, 0)) as count FROM defRecTypes d LEFT JOIN Records r ON d.rty_ID=r.rec_RecTypeID GROUP BY id";
        $res = $system->getMysqli()->query($query);
        while($row = $res->fetch_assoc()) {
            $rectype = new stdClass();
            $rectype->id = intval($row["id"]);
            $rectype->name = $row["name"];
            $rectype->count = intval($row["count"]);
            $rectype->image = HEURIST_RTY_ICON.$row["id"];

            //print_r($rectype);
            array_push($rectypes, $rectype);
        }

        return $rectypes;
    }

    /**
    * Retrieve all detail types of a record type that point to other records
    *
    * Find all constrained resource (record pointer) and relmarker fields
    *
    * @param mixed $system   System reference
    * @param mixed $rectype  Record type
    */
    function getConstrainedResourceAndRelmarkerFields($system, $rectype) {
        $relations = array();

        // Select all relation details that have "dty_PtrTargetRectypeIDs" defined.  The defRecStructure table is used to determine the structure of a record. The defDetailTypes and recDetails tabes are used to ultimately get access to the "dty.dty_PtrTargetRectypeIDs" field. This field stores a comma seperated links of record types where this record points to.
        //COUNT(rd.dtl_ID) as count,
        $query = "SELECT rst_DetailTypeID as id, rst_DisplayName as name, dty.dty_Type as reltype, dty.dty_PtrTargetRectypeIDs as ids "
        ."FROM defRecStructure rst INNER JOIN defDetailTypes dty ON rst.rst_DetailTypeID=dty.dty_ID "
        ."LEFT JOIN recDetails rd ON rd.dtl_DetailTypeID=rst.rst_DetailTypeID "
        ."WHERE rst.rst_RectypeID=" .$rectype->id. " AND rst.rst_RequirementType <> 'forbidden' AND NOT (dty.dty_PtrTargetRectypeIDs IS NULL OR dty.dty_PtrTargetRectypeIDs='') "
        ."GROUP BY rst.rst_DetailTypeID;";


        $res = $system->getMysqli()->query($query);
        while($row = $res->fetch_assoc()) {
            $relation = new stdClass();
            $relation->id = intval($row["id"]);//detail type id
            $relation->name = $row["name"];
            $relation->count = 0;
            $relation->type = $row["reltype"];
            $relation->ids = $row["ids"];

            //print_r($relation);
            array_push($relations, $relation);
        }

        return $relations;
    }

    /**
    * Find count of links/relation by pair of source->target rectypes
    *
    * @param mixed $system   System reference
    * @param mixed $rectype  Parent rectype
    * @param mixed $relation Relation object
    */
    function getTargets($system, $rectype, $relation) {
        $targets = array();

        // Go through all ID's
        //echo "\nID's for relation #" . $relation->id . ": " . $relation->ids;
        $ids = explode(",", $relation->ids);//target record types ids (constraints from field type definitions)
        foreach($ids as $id) {
            if($relation->type=='relmarker'){

                //find allowed relation types (terms) for fieldtype


                $query = "SELECT COUNT(r2.rec_ID) as count, rl.rl_DetailTypeID, r1.rec_Title, r1.rec_RecTypeID, r2.rec_Title, r2.rec_RecTypeID "
                ."FROM recLinks rl INNER JOIN Records r1 ON r1.rec_ID=rl.rl_SourceID "
                ."INNER JOIN Records r2 ON r2.rec_ID=rl.rl_TargetID "
                ."WHERE rl.rl_DetailTypeID IS NULL AND r1.rec_RecTypeID=" .$rectype->id. " AND r2.rec_RecTypeID=".$id;


            }else{
                // Count how many times the $relation points to this id. The recLinks table is used to determine record links.
                // rl_sourceID and rl_targetID details are looked up in the Records table.
                // Actual relationships are then filtered by using the record type id and detail type id of $relation and
                // the record type id of the target record.
                $query = "SELECT COUNT(r2.rec_ID) as count, rl.rl_DetailTypeID, r1.rec_Title, r1.rec_RecTypeID, r2.rec_Title, r2.rec_RecTypeID "
                ."FROM recLinks rl INNER JOIN Records r1 ON r1.rec_ID=rl.rl_SourceID "
                ."INNER JOIN Records r2 ON r2.rec_ID=rl.rl_TargetID "
                ."WHERE rl.rl_DetailTypeID=" .$relation->id
                . " AND r1.rec_RecTypeID=" .$rectype->id. " AND r2.rec_RecTypeID=".$id;
            }

            if($res = $system->getMysqli()->query($query)) {
                if($row = $res->fetch_assoc()) {
                    $target = new stdClass();
                    $target->id = intval($id);
                    $target->count = intval($row["count"]);

                    //print_r($target);
                    array_push($targets, $target);
                }
            }
        }

        return $targets;
    }

    /**
    * Helper method to find the index of $target in the $rectypes array
    *
    * @param mixed $rectypes Array of rectypes
    * @param mixed $target   A target object
    * @return mixed The index
    */
    function getIndex($rectypes, $target) {
        for($i = 0; $i < sizeof($rectypes); $i++) {
            if($rectypes[$i]->id == $target->id) {
                return $i;
            }
        }
        return 0;
    }

    /**
    * Retrieves all links for a certain RecType
    *
    * @param mixed $system  System reference
    * @param mixed $rectype Rectype reference
    */
    function getLinks($system, $rectypes) {
        $links = array();

        // Go through all rectypes
        for($i = 0; $i < sizeof($rectypes); $i++) {
            // Find all constrined and relarion fields
            $relations = getConstrainedResourceAndRelmarkerFields($system, $rectypes[$i]);

            // Find all targets for each relation
            foreach($relations as $relation) {
                //get counts by target
                $targets = getTargets($system, $rectypes[$i], $relation);

                // Construct a link for each target
                foreach($targets as $target) {
                    $link = new stdClass();
/* example
relation:Object
    count:0
    id:16
    ids:"10"
    name:"Person(s) concerned"
    type:"resource"
source:1
target:9
targetcount:0
*/

//@TODO - FIX count for relationships. It returns count of links disregard detail type id
// This issue is a bit more complex than I’d expected. At the moment we have no data to
// restore what relmarker was an originator of relationship.



                    // Records
                    $link->source = $i;
                    $link->target = getIndex($rectypes, $target);
                    $link->relation = $relation;
                    // Counts
                    $link->targetcount = $target->count;
                    $link->relation->count = $target->count;

                    //print_r($link);
                    array_push($links, $link);
                }
            }
        }

        return $links;
    }

?>