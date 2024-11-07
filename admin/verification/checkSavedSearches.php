<?php

/**
* checkSavedSearches.php: Retrieve and process all saved searches, reporting those with issues
*   These issue aren't normally going to cause major system errors, but just result in empty results
*   Some can return errors, e.g. query 'q' is missing from search, this are more annoying
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

ini_set('max_execution_time', '0');

define('ADMIN_PWD_REQUIRED',1);
define('MANAGER_REQUIRED', 1);
define('PDIR', '../../');//need for proper path to js and css

require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
require_once dirname(__FILE__).'/../../hserv/records/search/recordSearch.php';// for recordSearch()

$mysqli = $system->get_mysqli();

$databases = mysql__getdatabases4($mysqli);

// Generic record fields
$rec_meta = array('title','typeid','typename','added','modified','addedby','url','notes','owner','access','tag');

$databases = array_map('htmlentities', $databases);

$results = array_fill_keys(array_values($databases), array());

$field_in_rst = 'SELECT rst_ID FROM defRecStructure WHERE rst_DetailTypeID = dtyID AND rst_RecTypeID = rtyID';
$rectype_exists = 'SELECT rty_ID FROM defRecTypes WHERE rty_ID = rtyID';

// Process each database
foreach($databases as $db){

    mysql__usedatabase($mysqli, $db);

    $saved_searches = mysql__select_assoc($mysqli, 'SELECT svs_ID, svs_Name, svs_Query, svs_UGrpID FROM usrSavedSearches');
    $ss_err = empty($saved_searches) ? $mysqli->error : '';// save possible error

    $owners = mysql__select_list2($mysqli, 'SELECT ugr_ID FROM sysUGrps', 'intval');

    $results_str = "<h2 data-db='$db'>$db:</h2><div style='padding: 10px 5px;' data-db='$db'>";

    foreach($saved_searches as $svs_ID => $svs_Details){

        $svs_Query = $svs_Details['svs_Query'];

        $query_obj = json_decode($svs_Query, true);// filter/facet search

        $query_str = is_string($svs_Query) && strpos($svs_Query, '?') === 0 ? $svs_Query : "";// simple search

        $results[$db][$svs_ID] = array();

        if(json_last_error() !== JSON_ERROR_NONE && empty($query_str)){
            $results[$db][$svs_ID][] = "Query is in unknown format, " . htmlspecialchars($svs_Query);
        }elseif(is_array($query_obj) && !array_key_exists('facets', $query_obj) && !array_key_exists('q', $query_obj)){
            $results[$db][$svs_ID][] = "Unknown formatting for saved search";
        }

        if(!empty($query_str)){ // simple query, convert to object
            $query_str = strpos($query_str, '?') !== false ? substr($query_str, 1) : $query_str;
            parse_str($query_str, $query_obj);
        }

        if(!empty($owners) && !in_array(intval($svs_Details['svs_UGrpID']), $owners)){
            $results[$db][$svs_ID][] = "Saved search belongs to a non-existant user/workgroup, formerly #" . $svs_Details['svs_UGrpID'];
        }

        $is_filter_facet = is_array($query_obj) && !empty($query_obj);// is a simple filter or facet search
        $type = 'unknown';
        if($is_filter_facet && array_key_exists('q', $query_obj)){ // is simple query

            $query_obj['detail'] = 'count';// 'ids' return something simple
            $res = recordSearch($system, $query_obj);

            if(!is_array($res) || $res['status'] != HEURIST_OK){

                $msg = is_array($res) && array_key_exists('message', $res) ? $res['message'] : 'Unknown error occurred while attempting to running query';
                $msg = is_string($res) ? $res : $msg;

                if(strpos($query_str, 'rules') === false && !array_key_exists('rules', $query_obj)){
                    // make sure this isn't just a ruleset

                    $results[$db][$svs_ID][] = "Error => $msg";
                }
            }

            $type = !empty($query_str) ? 'simple' : 'filter';

        }elseif($is_filter_facet && array_key_exists('facet', $query_obj)){ // is facet, check facets=>[]=>code

            foreach($query_obj['facet'] as $facet){

                // Check that field code exists
                $code = $facet['code'];
                if(empty($code)){

                    $results[$db][$svs_ID][] = 'Facet field is missing identifier';
                    continue;
                }

                // Field codes are at least rty_id:dty_id
                $code_parts = explode(':', $code);
                if(count($code_parts) < 2 || count($code_parts) % 2 !== 0){
                    // Is missing details, there should be an even number of ids

                    $results[$db][$svs_ID][] = "Invalid field code => $code";
                    continue;
                }

                // Check that top level record type and field exists
                $top_rty = array_shift($code_parts);
                $top_dty = array_shift($code_parts);

                $top_rty = strpos($top_rty, ',') !== false ? explode(',', $top_rty)[0] : $top_rty;

                if(in_array($top_dty, $rec_meta)){ // uses record data field

                    // check rectype exists
                    $res = $mysqli->query(str_replace('rtyID', $top_rty, $rectype_exists));

                    if($res){

                        if($res->num_rows == 0){
                            $results[$db][$svs_ID][] = "Invalid record type #$top_rty used in code => $code";
                        }
                        $res->close();
                    }

                    continue;
                }

                $top_dty = filter_var($top_dty, FILTER_SANITIZE_NUMBER_INT);// strip possible 'lt', 'rt', etc...

                $res = $mysqli->query(str_replace(array('dtyID', 'rtyID'), array(intval($top_dty), intval($top_rty)), $field_in_rst));

                if($res){

                    if($res->num_rows == 0){
                        $results[$db][$svs_ID][] = "Field #$top_dty does not exist within record type #$top_rty code => $code";
                    }
                    $res->close();

                    continue;
                }

                // Check remaining connections
                for($i = 0; $i < count($code_parts); $i += 2){

                    $j = $i + 1;

                    $rty_id = $code_parts[$i];
                    $dty_id = $code_parts[$j];

                    $rty_id = strpos($rty_id, ',') !== false ? explode(',', $rty_id)[0] : $rty_id;

                    if(in_array($dty_id, $rec_meta)){ // uses record data field

                        // check rectype exists
                        $res = $mysqli->query(str_replace('rtyID', $rty_id, $rectype_exists));

                        if($res){

                            if($res->num_rows == 0){
                                $results[$db][$svs_ID][] = "Invalid record type #$rty_id used in code => $code";
                            }
                            $res->close();
                        }

                        continue;
                    }

                    $dty_id = filter_var($dty_id, FILTER_SANITIZE_NUMBER_INT);// strip possible 'lt', 'rt', etc...

                    $res = $mysqli->query(str_replace(array('dtyID', 'rtyID'), array(intval($dty_id), intval($rty_id)), $field_in_rst));

                    if($res){
                        if($res->num_rows == 0){
                            $results[$db][$svs_ID][] = "Field #$dty_id does not exist within record type #$rty_id code => $code";
                        }
                        $res->close();
                    }
                }
            }

            $type = 'facet';
        }// else simple query that's appended to the url

        if(array_key_exists($svs_ID, $results[$db]) && !empty($results[$db][$svs_ID])){

            $results[$db][$svs_ID] = "<div style='padding: 10px 5px;' data-type='$type' data-name='". $svs_Details['svs_Name'] ."'>"
                                        . "ID: <strong style='padding-right: 15px;'>$svs_ID</strong> "
                                        . "Name: <strong>" . $svs_Details['svs_Name'] . "</strong><br>"
                                        . implode("<br>", $results[$db][$svs_ID])
                                    .DIV_E;
        }elseif(array_key_exists($svs_ID, $results[$db])){
            unset($results[$db][$svs_ID]);
        }
    }

    if(!empty($results[$db])){
        $results_str .= "<div>" . implode('</div><div>', $results[$db]) . "</div></div>";

        if(empty($owners)){
            $results_str .= "<h3>An error occurred while attmpeting to retrieve all available workgroups and users on this database</h3>";
        }
        if(!empty($ss_err)){
            $results_str .= "<h3>An error occurred while attmpeting to retrieve details about saved searches on this database</h3>";
        }

        $results[$db] = $results_str;
    }else{
        unset($results[$db]);// omit from results
    }
}
?>

<!DOCTYPE html>
<html lang="en">

    <head>

        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">
        <title>Check saved searches</title>

        <?php include_once dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php';?>

        <script type="text/javascript">

            window.onload = () => {

                if(document.querySelector('#db_filter').length == 0){ // no additional setup necessary
                    return;
                }

                // Filter db name
                document.querySelector('#db_filter').onkeyup = (event) => {

                    let filter = event.target.value;

                    document.querySelectorAll('[data-db]').forEach((ele) => {

                        let db = ele.getAttribute('data-db');
                        let dis_status = db.indexOf(filter) !== -1 || filter == '' ? 'block' : 'none';

                        ele.style.display = dis_status;
                        if(ele.tagName == 'H2' && ele.previousSibling && ele.previousSibling.tagName == 'HR'){
                            ele.previousSibling.style.display = dis_status;
                        }
                    });
                };

                // Filter saved search name
                document.querySelector('#ss_filter').onkeyup = (event) => {

                    let filter = event.target.value;

                    document.querySelectorAll('[data-name]').forEach((ele) => {

                        let name = ele.getAttribute('data-name');
                        let dis_status = name.indexOf(filter) !== -1 || filter == '' ? 'block' : 'none';

                        ele.style.display = dis_status;
                    });
                };

                // Filter search type
                document.querySelectorAll('.search_type').forEach(node => node.onchange = (event) => {

                    let type = event.target.value;
                    let dis_status = event.target.checked ? 'block' : 'none';
                    let nodes = document.querySelectorAll(`[data-type="${type}"]`);

                    if(nodes.length > 0){
                        nodes.forEach(ele => ele.style.display = dis_status);
                    }
                });

                // Hide filter types not used
                let chkboxes = document.querySelectorAll('.search_type');
                if(chkboxes.length === 4){

                    chkboxes.forEach(
                        ele => document.querySelectorAll(`[data-type="${ele.value}"]`).length === 0 ?
                                    ele.parentNode.style.display = 'none' : ele.parentNode.style.display = 'inline'
                    );
                }
            };
        </script>

    </head>

    <body class="popup" style="overflow:auto">

        <?php
        if(!empty($results)){
        ?>

        <div>
            <label style="padding-right: 10px;">Database name: <input type="text" id="db_filter"></label>
            <label style="padding-right: 20px;">Search name: <input type="text" id="ss_filter"></label>
            Search type:
            <label><input type="checkbox" class="search_type" value="filter" checked="checked"> Filter</label>
            <label><input type="checkbox" class="search_type" value="facet" checked="checked"> Facet</label>
            <label><input type="checkbox" class="search_type" value="simple" checked="checked"> Simple</label>
            <label><input type="checkbox" class="search_type" value="unknown" checked="checked"> Unknown</label>
        </div>

        <?php
            echo implode('<hr>', $results);
        }elseif(empty($databases)){
            echo "<h2>An error occurred with retrieving a list of available databases</h2>";
        }else{
            echo "<h2>No issues found with any saved filters or facets</h2>";
        }
        ?>

    </body>

</html>