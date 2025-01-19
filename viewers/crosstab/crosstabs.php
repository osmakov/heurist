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
 *   Corsstabs analysis UI
 *
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @copyright   (C) 2005-2023 University of Sydney
 * @link        https://HeuristNetwork.org
 * @version     3.1.0
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @package     Heurist academic knowledge management system
 */

define('PDIR', '../../');//need for proper path to js and css
require_once dirname(__FILE__) . '/../../hclient/framecontent/initPage.php';
?>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.7.0/css/buttons.dataTables.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-eOJMYsd53ii+scO/bJGFsiCZc+5NDVN2yr8+0RDqr0Ql0h+rP48ckxlpbzKgwra6" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.7/css/responsive.dataTables.min.css"/>
<!-- script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.2.0/chart.min.js"></script> -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo PDIR; ?>hclient/widgets/entity/configEntity.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta3/dist/js/bootstrap.bundle.min.js" integrity="sha384-JEW9xMcG8R+pH31jmWH6WWP0WintQrMb4s7ZOdauHnUtxwoG2vI5DkLtS3qm9Ekf" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.js"></script>
<script src="https://cdn.datatables.net/buttons/1.7.0/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.7.0/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.7.0/js/buttons.colVis.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.7.0/js/buttons.flash.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.7.0/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.7/js/dataTables.responsive.min.js"></script>

<script>
    var mapping, menu_datasets, btn_datasets;

    // Callback function on page initialization - see initPage.php
    function onPageInit(success) {

        const urlParams = new URLSearchParams(window.location.search);

        var query_main = '';

        if(urlParams.has('q')){
            query_main = decodeURIComponent(urlParams.get('q'));
        }

        //database, query q, domain w
        window.crosstabsAnalysis = CrosstabsAnalysis(query_main, '');
        //
    }
        //Used to open the crosstabs section to the full width on the initial load.
        // window.hWin.HAPI4.LayoutMgr.cardinalPanel('sizePane',
        //     ['east', (top ? top.innerWidth : window.innerWidth)]);
        if(window.hWin.HAPI4 && window.hWin.HAPI4.LayoutMgr){
            window.hWin.HAPI4.LayoutMgr.cardinalPanel('sizePane',
                ['east', (top ?  '55%' : window.innerWidth)]);
        }
</script>

<link rel=stylesheet href="crosstabs.css" media="all">
</head>

<body style="padding:5px;overflow:auto;" class="popup selectmenu-parent">

    <script src="crosstabs.js"></script>

    <div style="padding: 0.5em; height: 100%;"> <!-- margin:0px auto; overflow: auto; -->
        <div class="container-fluid">
            <!-- Page container -->
            <div class="row">
                <div class="col-12 col-mb-2 d-none output-content" id="errorContainerFilter"></div>
                <div class="col-12 col-mb-2 d-none" id="errorContainerRecChange"></div>
                <div class="col-12 col-mb-2 d-none" id="errorContainer"></div>

                <!-- Top Container -->
                <div id="topContainer">
                    <div id="qform" class="disign-content" style="width:100%;min-width: 850px;">
                        <div>

                            <!-- Left Side -->
                            <div class="border border-dark bg-white" style="width: 340px;padding: 7px;float: left;height: 92%;">
                                <fieldset><legend style="display:none"></legend>
                                    <!-- Dataset selection -->
                                    <div>
                                        <div class="align-items-center" style="width: 100%;" id="divLoadSettings">

                                        </div>
                                        <div class="align-items-center" id="divSaveSettings" style="margin-top: 10px;">

                                        </div>

                                        <div>

                                            <fieldset id="shows" style="display:none;"><legend style="display:none"></legend>
                                                <div class="align-items-center">
                                                    <div style="font-size: 14px;vertical-align: top;">
                                                        <label for="rbShowValue">Show</label>
                                                    </div>

                                                    <div class="input-cell">
                                                        <div class="checkboxValues">
                                                            <input type="checkbox" class="btn-check" onchange="crosstabsAnalysis.doRender()" style="margin-left: 0;" checked id="rbShowValue">
                                                            <label class="btn btn-outline-primary" for="rbShowValue">Values</label>
                                                        </div>

                                                        <div class="checkboxValues">
                                                            <input type="checkbox" class="btn-check" onchange="crosstabsAnalysis.doRender()" checked id="rbShowTotals">
                                                            <label class="btn btn-outline-primary" for="rbShowTotals">Totals</label>
                                                        </div>

                                                        <br>

                                                        <div class="checkboxValues">
                                                            <input type="checkbox" class="btn-check" onchange="crosstabsAnalysis.doRender()" id="rbShowPercentRow">
                                                            <label class="btn btn-outline-primary" for="rbShowPercentRow">Row %</label>
                                                        </div>

                                                        <div class="checkboxValues">
                                                            <input type="checkbox" class="btn-check" onchange="crosstabsAnalysis.doRender()" id="rbShowPercentColumn">
                                                            <label class="btn btn-outline-primary" for="rbShowPercentColumn">Column %</label>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="align-items-center" style="display: block;">
                                                    <div style="font-size: 14px;">
                                                        <label for="aggregationMode">Aggregates</label>
                                                    </div>

                                                    <div class="input-cell">
                                                        <div class="crosstab-aggregation checkboxValues">
                                                            <input type="radio" class="btn-check" checked value="count" id="aggregationModeCount" name="aggregationMode" onchange="crosstabsAnalysis.changeAggregationMode()">
                                                            <label class="btn btn-outline-primary" for="aggregationModeCount">Counts</label>
                                                        </div>

                                                        <div id="aggSum" class="crosstab-aggregation checkboxValues" style="display: none;">
                                                            <input type="radio" class="btn-check" id="aggregationModeSum" value="sum" name="aggregationMode" onchange="crosstabsAnalysis.changeAggregationMode()">
                                                            <label class="btn btn-outline-primary" for="aggregationModeSum">Sum</label>
                                                        </div>

                                                        <div id="aggAvg" class="crosstab-aggregation checkboxValues" style="display: none;">
                                                            <input type="radio" class="btn-check" id="aggregationModeAvg" value="avg" name="aggregationMode" onchange="crosstabsAnalysis.changeAggregationMode()">
                                                            <label class="btn btn-outline-primary" for="aggregationModeAvg">Average</label>
                                                        </div>

                                                        <div id="divAggField" class="crosstab-aggregation" style="margin-top:10px;">of&nbsp;
                                                            <select id="cbAggField" name="column" onchange="crosstabsAnalysis.changeAggregationMode()" class="text ui-widget-content ui-corner-all"></select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div>
                                                    <div class="input-cell">
                                                        <div class="blankCheckboxes">
                                                            <input type="checkbox" onchange="crosstabsAnalysis.doRender()" checked id="rbSupressZero">
                                                            <label for="rbSupressZero">blank for zero values</label>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div style="display: block; padding-bottom: 15px;">
                                                    <div class="input-cell">
                                                        <div class="blankCheckboxes">
                                                            <input type="checkbox" onchange="crosstabsAnalysis.doRender()" id="rbShowBlanks">
                                                            <label for="rbShowBlanks">show blank rows/columns</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </fieldset>

                                        </div>
                                    </div>
                                </fieldset>
                            </div>
                            <!-- End of Left Side -->

                            <!-- Right Side -->
                            <div class="border border-dark bg-white" style="width: 43%;padding: 7px;display: inline-block;height: 92%;">

                                <div style="font-size: 0.8em;">
                                    Choice of record type determines the list of fields avaiable<br>but does not filter the results - the analysis is based on all<br>records in the result set
                                </div>

                                <div style="margin-top: 10px;">
                                    <div style="margin-right: 10px;">
                                        Show fields for
                                    </div>

                                    <div>
                                        <select id="cbRectypes" class="ui-widget-content ui-corner-all"></select>
                                    </div>
                                </div>

                                <fieldset id="vars" style="display:none;"><legend style="display:none"></legend>

                                    <div id="row_container">
                                        <div class="align-items-center" id="rowVars" style="margin-bottom: 5px;">

                                            <div class="fldheader" style="margin: 0px 5px 0px 11px;">
                                                <label for="cbRows">Var 1 <br><span style="font-size: smaller;font-weight: normal;">(rows)</span></label>
                                            </div>
                                            <div>
                                                <select id="cbRows" name="row" onchange="crosstabsAnalysis.resetIntervals(event);" class="text ui-widget-content ui-corner-all" style="width:100%"></select>
                                            </div>

                                            <div>
                                                <span id="rowTooltip" data-bs-toggle="tooltip" title="Select field to set intervals" style="margin-left: 5px;">
                                                    <button type="button" tt='row' class="btn btn-warning showintervals" disabled>
                                                        <span class="ui-icon ui-icon-pencil"></span>
                                                    </button>
                                                </span>
                                            </div>

                                        </div>
                                        <div id="rowWarning" class="align-items-center">&nbsp;</div>
                                    </div>

                                    <div id="column_container">
                                        <div class="align-items-center" id="columnVars" style="margin-bottom: 5px;">

                                            <div class="fldheader" style="margin: 0px 5px 0px -5px;">
                                                <label for="cbColumns">Var 2 <br><span style="font-size: smaller;font-weight: normal;">(columns)</span></label>
                                            </div>
                                            <div>
                                                <select id="cbColumns" name="column" onchange="crosstabsAnalysis.resetIntervals(event)" class="text ui-widget-content ui-corner-all" style="width:100%"></select>
                                            </div>

                                            <div>
                                                <span id="columnTooltip" data-bs-toggle="tooltip" title="Select field to set intervals" style="margin-left: 5px;">
                                                    <button type="button" tt="column" class="btn btn-warning showintervals" disabled>
                                                        <span class="ui-icon ui-icon-pencil"></span>
                                                    </button>
                                                </span>
                                            </div>

                                        </div>
                                        <div id="columnWarning" class="align-items-center">&nbsp;</div>
                                    </div>

                                    <div id="page_container">
                                        <div class="align-items-center" id="pageVars" style="margin-bottom: 5px;">

                                            <div class="fldheader" style="margin: 0px 5px 0px 7px;">
                                                <label for="cbPages">Var3 <br><span style="font-size: smaller;font-weight: normal;">(pages)</span></label>
                                            </div>
                                            <div>
                                                <select id="cbPages" name="page" onchange="crosstabsAnalysis.resetIntervals(event)" class="text ui-widget-content ui-corner-all" style="width:100%"></select>
                                            </div>

                                            <div>
                                                <span id="pageTooltip" data-bs-toggle="tooltip" title="Select field to set intervals" style="margin-left: 5px;">
                                                    <button type="button" tt='page' class="btn btn-warning showintervals" disabled>
                                                        <span class="ui-icon ui-icon-pencil"></span>
                                                    </button>
                                                </span>
                                            </div>

                                        </div>
                                        <div id="pageWarning" class="align-items-center">&nbsp;</div>
                                    </div>

                                </fieldset>

                            </div>
                            <!-- End of Right Side -->

                        </div>

                    </div>
                </div>
                <!-- End of Top Container -->

                <div style="text-align:center;padding-top:1em; padding-bottom:1em; display:none;" id="btnPanels">
                    <button id="btnUpdate" onclick="crosstabsAnalysis.doRetrieve()" style="font-size:larger;font-weight:bold">Update results</button>
                </div>

                <!-- Bottom Container -->
                <div class="d-none" id="bottomContainer">
                    <!--Tab Bar for table and visualisation -->
                    <ul class="nav nav-tabs" id="tabs">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="table-tab" data-bs-toggle="tab" data-bs-target="#table" type="button" role="tab" aria-controls="table" aria-selected="true">Table</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="pie-tab" data-bs-toggle="tab" data-bs-target="#pie" type="button" role="tab" aria-controls="pie" aria-selected="false">Pie Chart</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="tabContent">
                        <!-- Datables goes here!!! -->
                        <div class="tab-pane fade show active bg-white overflow-auto" id="table" role="tabpanel" aria-labelledby="table-tab" style="padding: 10px;">
                            <div id="divres" class="output-content" style="display:none;">
                            </div>
                        </div>

                        <!--Pie Chart goes here!!! -->
                        <div class="tab-pane fade d-flex justify-content-center bg-white" id="pie" role="tabpanel" aria-labelledby="pie-tab" style="padding: 10px;">
                            <div class="alert alert-info d-none" role="alert" id="pieMessage">
                                <i class="ui-icon ui-icon-alert"></i>
                                Graphs currently do not work for column and page selections. Only selection of a row variable will produce a result.
                            </div>
                            <canvas id="pieResults" width="700" height="700"></canvas>
                        </div>
                    </div>
                </div>
                <!-- End of Bottom Container -->

            </div>
        </div>

        <!-- Progress Indicator -->
        <div id="inporgress" style="display:none;">
            <div id="inporgress-loading"></div>
            <div id="pmessage" class="progress-message">Requesting</div>
        </div>

    <!-- Modal for Add, Edit and Delete values -->
    <div class="modal fade" tabindex="-1" id="rowIntervalsModal">
        <div class="modal-dialog modal-dialog-width" id="rowDialog">
            <div class="modal-content">
                <div class="modal-header" id="rowIntervalHeader">
                    <h4 id="rowHeader">&nbsp;</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="rowIntervalsBody">

                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" tabindex="-1" id="columnIntervalsModal">
        <div class="modal-dialog modal-dialog-width" id="columnDialog">
            <div class="modal-content">
                <div class="modal-header" id="columnIntervalHeader">
                    <h4 id="columnHeader">&nbsp;</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="columnIntervalsBody">

                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" tabindex="-1" id="pageIntervalsModal">
        <div class="modal-dialog modal-dialog-width" id="pageDialog">
            <div class="modal-content">
                <div class="modal-header" id="pageIntervalHeader">
                    <h4 id="pageHeader">&nbsp;</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="pageIntervalsBody">

                </div>
            </div>
        </div>
    </div>
</body>

</html>