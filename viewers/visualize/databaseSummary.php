<?php

/**
* databaseSummary.php : displays table of record types and counts and SVG entity connections schema scaled with counts (built with D3.js)
* based on an aggregation query for all records grouped by record type
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Jan Jaap de Groot <jjedegroot@gmail.com>  SVG schema
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
print '<!DOCTYPE html>';
define('PDIR','../../');//need for proper path to js and css
require_once dirname(__FILE__).'/../../hclient/framecontent/initPage.php';
?>
        <style>

            A:visited {
                color: #6A7C99;
                text-decoration: none;
            }
            A:link {
                color: #6A7C99;
                text-decoration: none;
            }
            .external-link{
                background-image: url('<?php echo ICON_EXTLINK;?>');
                background-repeat: no-repeat;
                padding-left: 12px;
                padding-top: 1px;
            }

            #rectypes {
                height: 100%;
            }

            table {
                font-size: 11px;
                table-layout: auto;
                border-color: black;
                border-collapse: collapse;
            }

            td, th {
                vertical-align: top;
                margin: 0px;
                padding: 2px 1px;
            }

            .empty-row {
                border: none !important;
                padding:8px;
                border-spacing: 0px;
            }

            .records td, .records th {
                border: 1px solid black;
            }

            .count-divider {
                border-top: 3px solid black;
            }

            .row:hover {
                background-color: #CCCCCC;
            }

            a:hover, input:hover {
                text-decoration: none;
                cursor: pointer;
            }

            #visualisation {
                width: 100%;
                height: 100%;
                border-left: 1px dashed black;
            }

            #table-header {
                margin-top: 10px;
                margin-bottom: 5px;
                margin-right: 0px;
                margin-left: 5px;
                padding: 0px;
                font-size: 14px !important;
            }

            #expand {
                float: right;
                margin-right: 5px;
                display:none;
            }
        </style>

        <!-- Layouts -->
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/jquery.layout.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.widgets/evol.colorpicker.js" charset="utf-8"></script>
        <link type="text/css" href="<?php echo PDIR;?>external/jquery.widgets/evol.colorpicker.css" rel="stylesheet"/>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/layout.js"></script>

        <!-- D3 -->
        <script type="text/javascript" src="<?php echo PDIR;?>external/d3/d3.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/d3/fisheye.js"></script>

        <!-- Visualize plugin -->
        <script type="text/javascript" src="<?php echo PDIR;?>viewers/visualize/settings.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>viewers/visualize/overlay.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>viewers/visualize/selection.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>viewers/visualize/gephi.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>viewers/visualize/drag.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>viewers/visualize/visualize.js"></script>

        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>viewers/visualize/visualize.css">

        <!-- On Row Click -->
        <script>
            function onrowclick(rt_ID, innewtab){
                var query = "w=all&db=<?=HEURIST_DBNAME?>&q=t:"+rt_ID+'&nometadatadisplay=true';
                if(innewtab){
                    window.open(window.hWin.HAPI4.baseURL+"?"+query, "_blank");
                    return false;
                }else{

                    var request = {source: 'dbsummary', w:'a',
                                        q:  't:'+rt_ID};

                    window.hWin.HAPI4.RecordSearch.doSearch( $(window.hWin.document), request );

                    if(window.hWin.HAPI4.sysinfo['layout']!='H4Default'){
                        window.close();
                    }

                    return false;
                }
            }

           function onPageInit(success){
                   if(!success) {return;}
                   $("#expand").trigger('click');
            }

        </script>

        
        <meta name="robots" content="noindex,nofollow">
    </head>

    <body class="popup" style="background-color: #FFF;padding: 0px;margin: 0px;">

        <div class="ent_wrapper" style="height: 100%;">
        <div class="layout-container" style="height: 100%;">

            <div class="ui-layout-west">

                <div id="lblShowRectypeSelector" style="display:none;margin:10px;"></div>

                <div id="list_rectypes" class="ent_wrapper" style="display:none;">
                    <div class="ent_header" style="display:none">
                        <h3 id="table-header">Record types (entities)</h3>
                        <button id="expand">Expand &#10142;</button>
                    </div>
                    <div class="ent_content_full" style="padding-left:10px; top:0">

                        <table id="records" class="records" cellpadding="4" cellspacing="1">
                            <caption></caption>
                            <tr>
                                <th width="40">ID</th>
                                <th class="space">Icon</th>
                                <th class="space" width="200">Record&nbsp;type</th>
                                <th class="space">Link</th>
                                <th class="space">Count</th>
                                <th class="space show">Show <input type='checkbox' id="show-all"></th>
                            </tr>

                            <?php
                            /** RETRIEVING RECORDS WITH CONNECTIONS */
                            // Building query
                            $query = "SELECT d.rty_ID as id, rg.rtg_Name grp, rg.rtg_ID as grp_id, d.rty_Name as title, sum(if(r.rec_FlagTemporary!=1, 1, 0)) as count
                                      FROM defRecTypes d LEFT OUTER JOIN Records r ON r.rec_RectypeID=d.rty_ID,
                                      defRecTypeGroups rg
                                      WHERE rg.rtg_ID=d.rty_RecTypeGroupID
                                      GROUP BY id
                                      ORDER BY rtg_Order, title ASC";
                            // Put record types & counts in the table
                            $res = $system->getMysqli()->query($query);
                            $count = 0;
                            $grp_name = null;
                            $first_grp  = 'first_grp';

                            while($row = $res->fetch_assoc()) { // each loop is a complete table row
                                $rt_ID = $row["id"];
                                $title = htmlspecialchars($row["title"]);

                                if($grp_name!=$row['grp']){
                                    if($grp_name!=null) {$first_grp = '';}
                                    $grp_name = $row['grp'];
                                    ?>
                            <tr class="row">
                                <td colspan="5" style="padding-left:10px"><h2><?php echo htmlspecialchars($row["grp"]);?></h2></td>
                                <td align="center"><input type="checkbox" class="group_chkbox" title="Check all record types within group" data-id="<?php echo $row["grp_id"];?>"></td>
                            </tr>
                                    <?php
                                }

                                // ID
                                echo "<tr class='row'>";
                                echo "<td align='center'>$rt_ID</td>";

                                //HAPI4.iconBaseURL
                                // Image
                                $rectypeImg = "style='background-image:url(".HEURIST_RTY_ICON.$rt_ID.")'";
                                $img = "<img src='".ICON_PLACEHOLDER."' title='$title' $rectypeImg class='rft' />";
                                echo "<td align='center'>$img</td>";

                                // Type
                                echo "<td style='padding-left: 5px; padding-right: 5px'>"
                                ."<a href='#' title='Open search for this record type in current page' onclick='onrowclick($rt_ID, false)' class='dotted-link'>"
                                .$title.
                                "</a></td>";

                                // Link
                                echo "<td align='center'><a href='#' title='Open search for this record type in new page' onclick='onrowclick($rt_ID, true)' class='external-link'>&nbsp;</a></td>";

                                // Count
                                echo "<td align='center'>" .$row["count"]. "</td>";

                                // Show
                                if($row["count"] > 0 && $count < 10) {  //this record type has records
                                    echo "<td align='center' class='show'><input id='" .$rt_ID. "' type='checkbox' class='show-record rectype_grp_". $row["grp_id"] ."' name='" .$title. "' checked='true'></td>";
                                    $count++;
                                }else{
                                    echo "<td align='center' class='show'><input id='" .$rt_ID. "' type='checkbox' class='show-record $first_grp rectype_grp_". $row["grp_id"] ."' name='" .$title. "'></td>";
                                }
                                echo "</tr>";
                            }
                            ?>

                        </table>

                    </div>
                </div>
            </div>

            <div class="ui-layout-center">
                <div id="main_content" class="ent_wrapper" style="left:0px;">
                    <?php
                        $isDatabaseStructure = 1;
                        include_once dirname(__FILE__).'/visualize.php';
                    ?>
                </div>
            </div>
        </div>
        </div>

        <script>
            $("#expand").on('click', function(e) {
                // Show visualisation elements
                $(this).remove();
                //$(".show").slideToggle(500);
                //$("#visualisation-column").slideToggle(500);

                // VISUALISATION CALL
                var url = window.hWin.HAPI4.baseURL+"hserv/controller/rectype_relations.php" + window.location.search;
                d3.json(url, function(error, json_data) {
                    // Error check
                    if(error) {
                        window.hWin.HEURIST4.msg.showMsgErr({
                            message: `Error loading JSON data: ${error.message}`,
                            error_title: 'Unable to load diagram',
                            status: window.hWin.ResponseStatus.UNKNOWN_ERROR
                        });
                    }

                    // Data loaded successfully!
                    /** RECORD FILTERING */
                    // Set filtering settings in UI
                    let isfirst_time = false;
                    let at_least_one_marked = false;

                    <?php
                        if($count==0){ //reset setting for empty db (only once)
                            print 'isfirst_time = !(getSetting("'.HEURIST_DB_PREFIX.'"+window.hWin.HAPI4.database)>0); ';
                            print 'putSetting("'.HEURIST_DB_PREFIX.'"+window.hWin.HAPI4.database, 1); ';
                        }
                    ?>

                    if(!isfirst_time){
                        //restore setting for non empty db
                        $(".show-record").each(function() {
                            const name = $(this).attr("name");
                            const record = getSetting(name);//@todo - change to recordtype ID
                            if(record>0) {
                                at_least_one_marked = true;
                                $(this).prop("checked", true);
                            }else{
                                $(this).prop("checked", false);
                            }
                        }
                        );
                    }

                    if(isfirst_time || !at_least_one_marked){
                        $(".first_grp").each(function() {
                            $(this).prop("checked", true);
                            putSetting($(this).attr("name"), 1);
                        });

                        putSetting('startup_rectype_'+window.hWin.HAPI4.database, 1);
                    }else{
                        putSetting('startup_rectype_'+window.hWin.HAPI4.database, 0);
                    }

                    // Listen to 'show-record' checkbox changes
                    $(".show-record").on('change', function(e) {
                        // Update record field 'checked' value in localstorage
                        const name = $(e.target).attr("name");

                        const value = $(e.target).is(':checked') ? 1 : 0;
                        // Set 'checked' attribute and store it
                        putSetting(name, value);

                        // Update visualisation
                        filterData();
                    });

                    // Listen to the 'show-all' checkbox
                    $("#show-all").on('change', function() {
                        // Change all check boxes
                        const checked = $(this).prop('checked');
                        $(".show-record").prop("checked", checked);

                        // Update localstorage
                        $(".show-record").each(function(e) {
                            const name = $(this).attr("name");
                            // Set 'checked' attribute and store it
                            putSetting(name, checked?1:0);
                        });

                        filterData();
                    });

                    // Listen to the 'group_chkbox' checkboxes, toggles all checkboxes within a record type group
                    $('.group_chkbox').on('change', function(){

                        const group_id = $(this).attr('data-id');
                        const checked = $(this).prop('checked');

                        if(group_id){
                            $('input.rectype_grp_'+group_id).prop('checked', checked);

                            // Update localstorage
                            $(".show-record").each(function(e) {
                                const name = $(this).attr("name");
                                // Set 'checked' attribute and store it
                                putSetting(name, checked?1:0);
                            });

                            filterData();
                        }
                    });

                    /** VISUALIZING */
                    // Parses the data
                    function getData(data) {
                        // Build name filter
                        let names = [];
                        $(".show-record").each(function() {
                            var checked = $(this).prop('checked');
                            if(checked == false) {
                                const name = $(this).attr("name");
                                names.push(name);
                            }
                        });

                        // Filter nodes
                        let map = {};
                        let size = 0;
                        let nodes = data.nodes.filter(function(d, i) {
                            if($.inArray(d.name, names) == -1) {
                                map[i] = d;
                                return true;
                            }
                            return false;
                        });

                        // Filter links
                        let links = [];
                        data.links.filter(function(d) {
                            if(map.hasOwnProperty(d.source) && map.hasOwnProperty(d.target)) {
                                const link = {source: map[d.source], target: map[d.target], relation: d.relation, targetcount: d.targetcount};
                                links.push(link);
                            }
                        })

                        // Return filtered data
                        return {nodes: nodes, links: links}
                    }

                    // Visualizes the data
                    function initVisualizeData() {
                        // Call plugin
                        const data_to_vis = getData(json_data);

                        $("#visualisation").visualize({
                            data: json_data,
                            getData: function(data) { return data_to_vis; },
                            linelength: 200,
                            isDatabaseStructure: true,
                            showCounts: false
                        });
                    }

                    //reset settings for empty database
                    if(!(window.hWin.HAPI4.sysinfo.db_total_records>0)){
                        //localStorage.clear();
                    }

                    $(window).on('onresize',onVisualizeResize);

                    onVisualizeResize();
                    initVisualizeData();

                });
            });

            function onVisualizeResize(){

                /*
                var width = $(window).width();

                var is_advanced = getSetting('setting_advanced');

                var supw = 0;
                if(width<645 || (is_advanced && width <= 1440)){
                     supw = 2;
                }
                */

                const dbkey = 'db'+window.hWin.HAPI4.database;
                putSetting(dbkey, '1');

                //$('#divSvg').css('top', 8+supw+'em');
            }

        </script>
    </body>

</html>