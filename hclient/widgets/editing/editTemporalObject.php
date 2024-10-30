<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">

<!--

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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson.heurist@gmail.com>
* @author      Stephen White
* @author      Artem Osmakov   <osmakov@gmail.com>
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

-->

		<title>Temporal Object</title>
		<style type="text/css">
			/*margin and padding on body element
			  can introduce errors in determining
			  element position and are not recommended */
			body {
				margin:0;
				padding:0;
			}

            #display-div{
                 border: none;
                 background: none;
                font-size:0.8em;
            }

			.date-button {
				margin: 3px;
				width: 24px;
				height: 18px;
				background: url(../../assets/cal.gif) lightgray scroll center center no-repeat;
			}
            .calendars-trigger{
                margin-left:4px;
                vertical-align: middle;
            }
		</style>

        <link rel="stylesheet" type="text/css" href="../../../h4styles.css">
        <link rel="stylesheet" type="text/css" href="../../../h6styles.css">

<?php
    define('IS_INDEX_PAGE', false);
    if(!defined('PDIR')) {define('PDIR','../../../');}
    require_once dirname(__FILE__).'/../../../autoload.php';

    includeJQuery();
?>
        <script type="text/javascript" src="../../core/detectHeurist.js"></script>

        <style type="text/css">
        	.display-tab{
        		padding-top: 10px !important;
        	}
        </style>
	</head>

	<body style="background-color:rgb(240,240,240);" width=440 height=420 >

		<div id="display-div">
			<ul>
				<li><a href="#s"><em>Simple Date</em></a></li>
				<li><a href="#f"><em>Simple Range</em></a></li>
				<li><a href="#p"><em>Fuzzy Range</em></a></li>
				<li><a href="#c"><em>Radiometric</em></a></li>

			</ul>

			<div id="s" class="display-tab">

				<span id="tab_note" class="heurist-helper3" style="padding-left: 10px;">
					Note: Date fields record a single date estimation. They do NOT record a date range. For that you should use Start date and End date fields
				</span>

				<div style="margin: 15px 0px 0px 50px">
					<div>
						<span title="Enter date in  y-m-d">
						    Date:&nbsp;<input type=text id="simpleDate" style="width:100px;" class="withCalendarsPicker" />
                            <label style="font-size:8pt; color:grey;padding-left: 20px;"><input type="radio" name="CIR" id="CIR0"> Exact</label>
						    <label style="font-size:8pt; color:grey;padding-left: 20px;"><input type="radio" name="CIR" id="CIR1"> Circa / approximate</label>
                            <label style="font-size:8pt; color:grey;padding-left: 20px;"><input type="radio" name="CIR" id="CIR2"> Before</label>
                            <label style="font-size:8pt; color:grey;padding-left: 20px;"><input type="radio" name="CIR" id="CIR3"> After</label>
						</span>
					</div>
					<div style="margin:5px 0px 15px 35px; font-size:8pt; color:grey;"  >
						<span >Year (eg.1624) or ISO date (yyyy-mm or yyyy-mm-dd)</span><br>
						<span >Use minus (-) for BCE dates (eg. -375 for 375 BCE)</span>
					</div>
				</div>

				<div style="border-top:1px solid lightgrey; margin :0px 10px"></div>

				<div style="margin: 15px  0px 0px 50px">
					<div>
						<span title="Enter time in  h:m:s">
							Time:&nbsp;<input type=text id="simpleTime" style="width:80px;" />
							<span style="font-size:8pt; color:grey;">&nbsp;(optional)</span>
						</span>

						<span title="Enter Time Zone offset starting with hours only enter minute for non-zero" style="margin:0px 30px">
							Time Zone:&nbsp;<u>+</u>
							<input type=text id="tzone" title="Time Zone Adjust hours (-12 to +12)" style="width:60px;" />
							<span style="font-size:8pt; color:grey;">&nbsp;(optional)</span>
						</span>
					</div>

					<div style="margin:5px 0px 15px 35px; font-size:8pt; color:grey;"  >
						<span>hh or hh:mm or hh:mm:ss</span>
						<span style="margin: 0px 120px;">+ for east (Europe,Africa,Asia)</span><br>
						<span style="margin: 0px 0px 0px 245px;">- for west (N &amp; S America)</span>
					</div>
				</div>

				<div style="border-top:1px solid lightgrey; margin :0px 10px"></div>

				<div id="deterDiv" style="float:left;margin:10px 0px 0px 57px;">
					Type of Determination<br>

					<span style="font-size:8pt; color:grey;">
					    How were the dates arrived at?
					</span>

					<div style="margin:10px 25px;">
						<input type="radio" name="sDET"  value="0" checked="checked" />Unknown<br>
						<input type="radio" name="sDET" value="1"/>Attested<br>
						<input type="radio" name="sDET"  value="2"/>Conjecture<br>
						<input type="radio" name="sDET"  value="3"/>Measurement
					</div>
				</div>
			</div>

			<div id="f" class="display-tab">
				<div style="margin:15px 0px 20px 50px; ">
					<div style="float:left; width:430px;">
						<div style="display: inline-block">
							<span title="Enter date in  y-m-d">
							    Earliest estimate&nbsp;<input type=text id="fTPQ" style="width:100px;" class="withCalendarsPicker"/>
							</span>
						</div>
						<div style="display: inline-block;margin-left: 10px;">
							<span title="Enter date in  y-m-d">
							    Latest estimate&nbsp;<input type=text id="fTAQ" style="width:100px;" class="withCalendarsPicker"/>
							</span>
						</div>

						<div style="font-size:8pt; color:grey; margin:5px 0px 15px 90px;">
							<span >Year (eg.1624) or ISO date (yyyy-mm or yyyy-mm-dd)</span><br>
							<span >Use minus (-) for BCE dates (eg. -375 for 375 BCE)</span>
						</div>

						<div id="fRange" style="display: none;margin-bottom: 15px;"> <!--visibility: hidden;-->
							<div>
								<span style="margin-right:50px">Range</span>
								<div style="display:inline-block; width:250px; margin-right:5px;">
									<span id="fYears">0</span> Years
									<span id="fMonths">0</span> Months
									<span id="fDays">0</span> Days apart
								</div>
							</div>
							<div style="font-size:8pt; color:grey; margin:5px 0px 15px 90px;">
								<span >Note: This is based off of the Gregorian dates of both earliest and latest</span><br>
							</div>
							<!--<div title="Enter duration in  PnYnMnDTnHnMnS format">
								<span style="margin-right:50px">Range</span>
								<u>+</u>&nbsp;&nbsp;<input type=text id="fRNG" style="width:50px; margin-right:5px" />
								<select id="level">
									<option label="Year"  selected value="Y">Year</option>
									<option label="Month" value="M">Month</option>
									<option label="Day" value="D">Day</option>
								</select><br>
							</div>
							<div style="font-size:8pt; color:grey; margin: 5px 0px 15px 105px;">
								Actual date is assumed to lie within this range.
							</div>-->
						</div>

						<div id="fProfDiv" >
							<label for="fPRF" title="Select the profile">Probability curve &nbsp;</label>
							<select id="fPRF">
								<option label="Flat"  selected value=0>Flat</option>
								<option label="Central" value=1>Central Tendancy</option>
								<option label="Slow Start" value=2>Slow Start</option>
								<option label="Slow Finish" value=3>Slow Finish</option>
							</select><br>
							<div style="font-size:8pt; color:grey; margin: 5px 0px 0px 90px;">
								Distribution of the likelihood of the date between the limits set by the range.
							</div>
						</div>
					</div>

					<div id="fdeterDiv" style="float:left;margin:0px 0px 0px 57px;">
						Source of the estimate<br>
						<span style="font-size:8pt; color:grey;">
						    How the dates arrived at?
						</span>

						<div style="margin:10px 25px;">
							<input type="radio" name="fDET"  value="0" checked="checked" />Unknown<br>
							<input type="radio" name="fDET" value="1"/>Attested<br>
							<input type="radio" name="fDET"  value="2"/>Conjecture<br>
							<input type="radio" name="fDET"  value="3"/>Measurement
						</div>
					</div>
				</div>
			</div>

			<div id="p" class="display-tab">
					<div style="text-align: center;height:108px">
                        <img alt src="../../assets/temporalDateRange.png" style="height:95px"/>
                        <!--
                        background:url(../../assets/temporalDateRange.png) center center no-repeat;
						<canvas id="dRangeCanvas" width="530" height="80"></canvas>
                        -->
					</div>
					<div style="margin-left:30px;">
						<div>
							<span title="Enter only the known values starting with year" style=" margin:0px 5px;">
								<input type=text id="TPQ" style="width:95px;" class="withCalendarsPicker"/>
							</span>

							<span title="Press to set Probable begin equal to TPQ" style=" margin:0px 5px;">
								<input type=button id="setPDB" value="set =>" onclick="setPDBtoTPQ();" style="font-size: 7pt; width:35px;" />
							</span>

							<span title="Enter only the known values starting with year" style=" margin:0px 5px;">
								<input type=text id="PDB" style="width:95px;" class="withCalendarsPicker"/>
							</span>

							<img alt style="width:30px; height:1px; border-top: 2px solid grey; margin:0px 5px;"/>

							<span title="Enter only the known values starting with year" style=" margin:0px 5px;">
								<input type=text id="PDE" style="width:95px;" class="withCalendarsPicker"/>
							</span>

							<span title="Press to set Probable end equal to TAQ" style=" margin:0px 5px;">
								<input type=button id="setPDE" value="<= set" onclick="setPDEtoTAQ();" style="font-size: 7pt; width:35px;"/>
							</span>

							<span title="Enter only the known values starting with year" style=" margin:0px 5px;">
								<input type=text id="TAQ" style="width:95px;" class="withCalendarsPicker"/>
							</span>
						</div>

						<div>
							<span style="margin:5px 0px 15px 5px; font-size:8pt; color:grey;"> Terminus Post Quem </span>
							<span style="margin:5px 0px 15px 75px; font-size:8pt; color:grey;"> Probable begin </span>
							<span style="margin:5px 0px 15px 95px; font-size:8pt; color:grey;"> Probable End </span>
							<span style="margin:5px 0px 15px 72px; font-size:8pt; color:grey;"> Terminus Ante Quem </span>
						</div>
					</div>

					<div style="margin:20px 0px 20px 80px;">
						<span id="pSProfDiv">
							<label for="SPF" title="Select the profile">Start Profile:</label>
							<select id="SPF">
								<option label="Flat"  selected value=0>Flat</option>
								<option label="Central" value=1>Central Tendancy</option>
								<option label="Slow Start" value=2>Slow Start</option>
								<option label="Slow Finish" value=3>Slow Finish</option>
							</select>
						</span>

						<img alt style="height:1px; margin:0px 70px;"/>

						<span id="pEProfDiv">
							<label for="EPF" title="Select the profile">End Profile:</label>
							<select id="EPF">
								<option label="Flat"  selected value=0>Flat</option>
								<option label="Central" value=1>Central Tendancy</option>
								<option label="Slow Start" value=2>Slow Start</option>
								<option label="Slow Finish" value=3>Slow Finish</option>
							</select>
						</span>
					</div>

					<div style="margin:10px 20px; ">
						<div style="border: 1px solid grey; float:left; width:300px; padding:10px;" >
							<b>Entering dates</b><br>
							<span style="font-size:8pt; color:grey;">
								Year (eg.1624) or ISO date (yyyy-mm or yyyy-mm-dd)<br>
								Use minus (-) for BCE dates (eg. -375 for 375 BCE)
							</span><br><br>
							<b>Start &amp; End profile</b><br>
							<span style="font-size:8pt; color:grey;">What is the distribution of the likelihood of the date<br>
							between the terminus date and the probable date<br>
							(if in doubt, choose flat, that is any point is equally likely).</span><br>
						</div>

						<div id="deterDiv" style="float:left;margin:0px 0px 0px 57px;">
							Type of Determination<br>
							<span style="font-size:8pt; color:grey;">
							How the dates arrived at?
							</span>
							<div style="margin:10px 25px;">
								<input type="radio" name="pDET"  value="0" checked="checked" />Unknown<br>
								<input type="radio" name="pDET" value="1"/>Attested<br>
								<input type="radio" name="pDET"  value="2"/>Conjecture<br>
								<input type="radio" name="pDET"  value="3"/>Measurement
							</div>
						</div>
					</div>
				</div>

			<div id="c" class="display-tab">
					<div style="margin:20px 0px 20px 50px;">
						Mean Date:
						<input type=text id="BCE" title="Enter date in  y-m-d h:m:s" onclick="return c14DateClick('BCE');" style="width:80px; margin: 0px 5px" />
						<span style="margin:0px 15px 0px 15px" >or</span>
							<input type=text id="BPD" title="Enter date in  y-m-d h:m:s" tabindex="-1" onclick="return c14DateClick('BPD');" style="width:80px; margin: 0px 5px 0px 0px" />
						<br>
						<span style="margin:5px 10px 15px 78px; font-size:8pt; color:grey;"> Years BCE </span>
						<span style="margin:5px 0px 15px 55px; font-size:8pt; color:grey;"> Years BP </span>
					</div>

					<div style="margin:20px 0px 20px 50px;">
						<span title="Enter duration in  PnYnMnDTnHnMnS format" style=" margin:0px 25px 0px 65px">±
							<input type=text id="DEV" onclick="return c14DevClick('DEV');" style="width:50px; margin:0px 25px 0px 1px" />or
						</span>
						<span title="Enter duration in  PnYnMnDTnHnMnS format">+
							<input type=text id="DVP" onclick="return c14DevClick('DVP');" tabindex="-1" style="width:50px; margin:0px 9px 0px 1px" />
						</span>
						<span title="Enter duration in  PnYnMnDTnHnMnS format">-
							<input type=text id="DVN" onclick="return c14DevClick('DVP');" tabindex="-1" style="width:50px; margin:0px 0px 0px 4px" />
						</span><br>
						<span style="margin:5px 10px 15px 80px; font-size:8pt; color:grey;">Std dev</span>
						<span style="margin:5px 10px 15px 70px; font-size:8pt; color:grey;">Pos dev </span>
						<span style="margin:5px 0px 15px 20px; font-size:8pt; color:grey;">Neg dev</span>
					</div>

					<div style="margin:20px 0px 20px 50px;">
						Lab Code:
						<input type=text id="COD" title="Enter Lab Code" style="width:100px; margin: 0px 5px 0px 14px" />
						<span style="margin:0px 30px 0px 15px" >
							<input type="checkbox" id="CAL" />Calibrated
						</span>
					</div>
				</div>

		</div>
<!--
<div style="background-color:rgb(240,240,240); border:0;"></div>
-->

		<div style="font-size:0.8em;">
			<span style="margin:10px 270px">&nbsp;</span>
			<span>
				<input type="button" value="Save" onclick="TemporalPopup.close();" />
				<input type="button" value="Cancel" onclick="TemporalPopup.cancel();" />
			</span>
		</div>

		<div style="border-top:1px solid grey; margin :10px 10px"></div>

		<div style="margin: 10px 0px 0px 20px;font-size:0.8em;">
            <div title="Select calendar" style="margin: 4px 0;">
                <div style="width:200px;float:left;text-align:right;padding: 2px 5px;">Use calendar:</div>
                    <select id="selectCLD" style="font-size:1em;">
                        <option value="gregorian">Gregorian</option>
                        <option value="taiwan">Taiwan</option>
                        <option value="thai">Thai</option>
                        <option value="julian">Julian</option>
                        <option value="persian">Persian</option>
                        <option value="islamic">Islamic</option>
                        <option value="ummalqura">Umm al-Qura</option>
                        <option value="hebrew">Hebrew</option>
                        <option value="ethiopian">Ethiopian</option>
                        <option value="coptic">Coptic</option>
                        <option value="nepali">Nepali</option>
                        <option value="mayan">Mayan</option>
                        <option value="japanese">Traditional Japanese</option>
                    </select>
                    <span id="dateGregorian"></span>
            </div>

			<div title="Enter comment for this temporal or ">
				<div style="width:200px;float:left;text-align:right;padding: 2px 5px;">Comment/original information:</div>
				<input type=text id="COM" style="width:300px;font-size:1em;" />
			</div>

			<div style="margin:5px 0px 15px 175px; font-size:7pt; color:grey;"  >
				<span >Temporal objects are converted to a standard format.</span><br>
				<span >This field is stored in the temporal to record the original values entered.</span>
			</div>
		</div>

		<script type="text/javascript" src="../../core/temporalObjectLibrary.js"></script>
        <script type="text/javascript" src="editTemporalObject.js"></script>
	</body>
</html>

