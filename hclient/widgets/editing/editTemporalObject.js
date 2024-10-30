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

/* global Temporal,TDate, formatGregJulian, fixCalendarPickerCMDs */

/**
* Dialog to define complex date (range, approximate)
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
* @subpackage  edining
*/

function switchDev (toAsym) {
	if (toAsym) {
		$("#DEV")[0].disabled = true;
		$("#DVP")[0].disabled = false;
		$("#DVN")[0].disabled = false;
		$("input[name=stdDEV][value=asym]").attr("checked","checked");
		$("input[name=stdDEV][value=sym]").attr("checked","");
	}else{
		$("#DEV")[0].disabled = false;
		$("#DVP")[0].disabled = true;
		$("#DVN")[0].disabled = true;
		$("input[name=stdDEV][value=asym]").attr("checked","");
		$("input[name=stdDEV][value=sym]").attr("checked","checked");
	}
}

//
// show alternative calendar
/*
function calendarPopup(buttonElt) {

	let callback =	function(date)
	{
		if (date) {
			document.getElementById("simpleDate").value = date;
			if(window.hWin && window.hWin.HAPI4) window.hWin.HAPI4.save_pref("record-edit-date", date);
			calendarViewer.close();
		}
	}
	let date = document.getElementById("simpleDate").value;
    
	if(window.hWin.HEURIST4.util.isempty(date) && window.hWin && window.hWin.HAPI4){
		date = window.hWin.HAPI4.get_prefs_def('record-edit-date','');
    }

    calendarViewer.showAt(getOffset(buttonElt), date, callback);
}*/

function getOffset(obj) {

        let x = 0, y = 0;
        let sleft = 0
        let stop = 0;
        while (obj) {
            x += obj.offsetLeft;
            y += obj.offsetTop;
            obj = obj.offsetParent;
        }
        return [x-sleft, y-stop];
}


function setPDBtoTPQ () {
	let tpq = document.getElementById("TPQ");
	let pdb = document.getElementById("PDB");
	if (typeof tpq == "object" && typeof pdb == "object" && tpq.value) {
		pdb.value = tpq.value
	}
}

function setPDEtoTAQ () {
	let taq = document.getElementById("TAQ");
	let pde = document.getElementById("PDE");
	if (typeof taq == "object" && typeof pde == "object" && taq.value) {
		pde.value = taq.value
	}
}

function c14DevClick(id) {
	if($("#"+id).attr("tabindex")> -1) return true; //if input is in tab path then pass click on
	if (id == "DEV"){
		$("#DEV").attr("tabindex","2");
		$("#DEV").val($("#DVP").val());
		$("#DVP").val("");
		$("#DVN").val("");
		$("#DVP").attr("tabindex","-1");
		$("#DVN").attr("tabindex","-1");
	}else{
		$("#DEV").attr("tabindex","-1");
		$("#DVP").attr("tabindex","2");
		$("#DVP").val($("#DEV").val());
		$("#DVN").val($("#DEV").val());
		$("#DEV").val("");
		$("#DVN").attr("tabindex","3");
	}
}

function c14DateClick(id) {
	if($("#"+id).attr("tabindex")> -1) return true; //if input is in tab path then pass click on
	if (id == "BCE"){
		$("#BCE").attr("tabindex","1");
		$("#BCE").val($("#BPD").val());
		$("#BPD").val("");
		$("#BPD").attr("tabindex","-1");
	}else{
		$("#BPD").attr("tabindex","1");
		$("#BPD").val($("#BCE").val());
		$("#BCE").val("");
		$("#BCE").attr("tabindex","-1");
	}
}

function drawLabel ( ctx, label, xPos, yPos, maxX) {
	let pxLen = ctx.measureText(label).width;
	ctx.fillText(label, xPos - pxLen/2, yPos, maxX);
}

let TemporalPopup = (function () {
	//private members
	const _className = "Applet";  // I know this is a singleton and the application object, but hey it matches the pattern.
	let _type2TabIndexMap = {};

	let _change_tab_only = false;

	function _init () {

		if (location.search.length > 1) { // the calling app passed a parameter string - save it
			that.originalInputString = decodeURIComponent (location.search.substring(1));
		}
		if ( Temporal.isValidFormat(that.originalInputString)) {
			try {
				that.origTemporal = Temporal.parse(that.originalInputString);
			}
			catch (e) {
				return "Error creating temporal from valid input string : " + that.originalInputString + " : " + e;
			}
		} else if (that.originalInputString) { // non temporal non empty string
			try {
					let tDate = TDate.parse(that.originalInputString);
					that.origTemporal = new Temporal();
					that.origTemporal.setType("s");  // simple date
					that.origTemporal.setTDate("DAT",tDate);
					that.origTemporal.setField("COM",that.originalInputString);
				}
				catch(e) { // unknown string
					that.origTemporal = new Temporal();
					that.origTemporal.setType("s");  // simple date with no date
					that.origTemporal.setField("COM",that.originalInputString);
				}
		} else { // empty string
			that.origTemporal = new Temporal();
			that.origTemporal.setType("s");  // simple date with no data
		}
		// set current temporal to original
		try {
			that.curTemporal = Temporal.cloneObj(that.origTemporal);
		}
		catch(e) {
			that.curTemporal = new Temporal();
			that.curTemporal.setType("s");  // simple date with no date
			that.curTemporal.setField("COM",that.originalInputString);
		}
		// set display
        $('#display-div').tabs({
        	beforeActivate: function( event, ui ) {

	            let curType = ui.oldPanel.attr('id'),
	                newType = ui.newPanel.attr('id');

	            _updateUIFromTemporal(that.curTemporal, false); //do not dates
	            _updateGeorgianDate();

	            if(!_change_tab_only){
		            // grab all the data from the current tab using the
                    try{
		                _updateTemporalFromUI(that.curTemporal);
                    }catch(e) {
						/* continue regardless of error */
                    }
		            that.curTemporal.setType(newType);
	            }else{
					_change_tab_only = false;
					return false;

				}

	            //move tab_note to new tab
	            ui.newPanel.prepend($('#tab_note'));
	        },
	        activate: function(event, ui){
	        	let newType = ui.newPanel.attr('id');
                $('#selectCLD').parent().show()
	        	if(newType === "f"){
	        		_updateSimpleRange();
	        	}else if(newType === "c"){
                    $('#selectCLD').parent().hide()
                }
	        }
    	});
        
        
        // set up temporal type to tab index mapping
        $.each($('#display-div').find('.display-tab'), function(i,item){
            _type2TabIndexMap[$(item).attr('id')] = i;
        });

        if(that.curTemporal.getType() === "p" && // if only earliest and latest estimates exist, treat as simple range instead of fuzzy range
			window.hWin.HEURIST4.util.isempty(that.curTemporal.getStringForCode('PDB')) && window.hWin.HEURIST4.util.isempty(that.curTemporal.getStringForCode('PDE')) && 
			!window.hWin.HEURIST4.util.isempty(that.curTemporal.getStringForCode('TPQ')) && !window.hWin.HEURIST4.util.isempty(that.curTemporal.getStringForCode('TAQ'))){

			that.curTemporal.setType("f");	
		}

        //
        _initJqCalendar(that.curTemporal);
        
		// select the tab for the initial temporal's type and change the label to show the user this is where things started
        _updateUIFromTemporal(that.curTemporal, true);
        let active_idx = _type2TabIndexMap[ that.curTemporal.getType() ? that.curTemporal.getType():'s' ];
        $('#display-div').tabs('option','active',active_idx);
        
	

        $(".withCalendarsPicker").on('change',_updateGeorgianDate);
        _updateGeorgianDate();
        
        
        $('input[value="Save"]').addClass('ui-button-action').button();
        $('input[value="Cancel"]').button();
        

        $('#fTPQ, #fTAQ').blur(_updateSimpleRange).on('change', function(){
        	const tpq = $('#fTPQ').val();
        	const taq = $('#fTAQ').val();
        	if(!window.hWin.HEURIST4.util.isempty(tpq) && !window.hWin.HEURIST4.util.isempty(taq)){
        		setTimeout(function(){
        			const T_tpq = $('#fTPQ').val();
        			const T_taq = $('#fTAQ').val();
        			if(T_tpq == tpq && T_taq == taq){ // unchanged
        				_updateSimpleRange();
        			}
        		}, 3000);
        	}
        });
	};

	function _updateSimpleRange(is_selection=false){

		let $range_cont = $('#fRange');

		if($('#display-div').tabs('option', 'active') != _type2TabIndexMap["f"] || ($('.calendars-popup').is(':visible') && !is_selection)){
			$range_cont.hide();
			return;
		}

		let $early = $('#fTPQ');
		let $latest = $('#fTAQ');

		let $range_amount = $('#fRNG');
		let $range_level = $('#level');

		if($early.val() == '' || $latest.val() == ''){
			$range_cont.hide();
			return;
		}

		let early_date = $early.val();
		let late_date = $latest.val();

		let from_calendar_type = $('#selectCLD').val();

		// Get values as gregorian, then send across
		if(from_calendar_type.toLowerCase() != 'gregorian'){
            
            early_date = convertCLD($early.val(), from_calendar_type);
            late_date = convertCLD($latest.val(), from_calendar_type);
            
		
		
		}
        
        let tDate1 = TDate.parse(early_date);
        let tDate2 = TDate.parse(late_date);

		if( tDate1.compare(tDate2) >= 0 ){ //new Date(early_date).getTime() >= new Date(late_date).getTime()){

			$range_cont.hide();
			window.hWin.HEURIST4.msg.showMsgFlash('Earliest estimate needs to be before latest date', 3000);
			return;
		}

		window.hWin.HAPI4.SystemMgr.get_time_diffs({'early_date': early_date, 'latest_date': late_date}, function(response){
			if(response.status == window.hWin.ResponseStatus.OK){

				const data = response.data;

				$('#fYears').text(parseInt(data.years));
				$('#fMonths').text(parseInt(data.months));
				$('#fDays').text(parseInt(data.days));

				$range_cont.show();

			}else{
				$range_cont.hide();
				window.hWin.HEURIST4.msg.showMsgErr(response);
			}
		});
	}

    function _updateGeorgianDate(){
        
        let type = that.curTemporal.getType();
        if(calendar && calendar.name.toLowerCase()!='gregorian' && type && type!='c'){

            let value = '';
            let from_calendar_type = calendar.name.toLowerCase();
            
            if (type === "s") {
               
                value = convertCLD($("#simpleDate").val(), from_calendar_type);
            }else if (type === "f") {
               

                value = convertCLD($("#fTPQ").val(), from_calendar_type)
                        +' '
                        +convertCLD($("#fTAQ").val(), from_calendar_type);

            }else  if (type === "p") {
               
                //PDB  PDE
                
                value = convertCLD($("#TPQ").val(), from_calendar_type)
                        +' '
                        +convertCLD($("#TAQ").val(), from_calendar_type);
            }

            $("#dateGregorian").text(value?"gregorian: "+value:'');

        }else{
            $("#dateGregorian").text('');
        }
    }

    //changedates - false for tab switch, it assign date intputs on init only
	function _updateUIFromTemporal (temporal, changedates) {
		let type = temporal.getType();
		if (!type) {
			return;
		}

        //"|VER=1|TYP=s|DAT=2024-07-01|CLD=Islamic|CL2=1445-12-24"
        let calendar_type = temporal.getStringForCode("CLD");
            
		if (type === "s") {
            
			let tDate = temporal.getTDate("DAT");
			// if DAT then separate Date, Time and TimeZone
			if (tDate) {
                if(changedates){
                    let dateValue = tDate.toString("yy-MM-dd");
                    convertAndAssign($("#simpleDate"), dateValue, calendar_type);
                }
				$("#simpleTime").val(tDate.toString("HH:mm:ss"));
				$("#tzone").val(tDate.toString("z"));
			}
            
            $( 'input[name="CIR"]').prop('checked', false);
            let val = temporal.getStringForCode('CIR');
            if(!(val>0)) val = 0;
            $( "#CIR" + val).prop('checked', true);
            
        }

		let fields = Temporal.getFieldsForType(type);
		for(let i =0; i< fields.length; i++) {
			let code = fields[i];
			let val = temporal.getStringForCode(code);
			let elem = $( "#" + type + code);
			if (elem.length == 0) {
				elem = $("#" + code);
			}
			if (elem.length == 0) {
				elem = $("input[name=" + type + code + "]:checked");
			}
			if (type === "c" && val && (code == "DEV" || code == "DVP" ||code == "DVN" )) {
				const v = val.match(/P(\d+)Y/);
				val = v[1] ? v[1] : val;
			}
			if (type === "f" && val && code == "RNG" ) {
				const v = val.match(/P(\d+)(Y|M|D)/);
				val = v[1] ? v[1] : val;
				if (v[2]) $("#level").val(v[2]);
			}
			if (elem.length != 0) {
				switch (elem[0].type) {
					case "checkbox" :
						if (val) {
							elem.prop("checked","checked");
						}
						break;
					case "radio" :
						if (val) {
							elem.prop("checked","");
							elem = $("input[name=" + type + code + "][value=" + val + "]");
							elem.prop("checked","checked");
						}
						break;
					case "select-one" :
						if (val) {
							elem.val(val);
						}
						break;
					default :
                        if(!elem.hasClass('withCalendarsPicker') ||  changedates){
                            
                                let tDate = temporal.getTDate(code);
                                // if DAT then separate Date, Time and TimeZone
                                if (tDate) {
                                    let dateValue = tDate.toString("yy-MM-dd");
                                    convertAndAssign(elem, dateValue, calendar_type);
                                }else{
                                    elem.val(val);    
                                }
                        }
				}
			}
		}//for

	};

    //
    //
    //
	function _updateTemporalFromUI (temporal) {
		let type = temporal.getType();
        
        let togregorian = true;
        
        //store values in native calendar
        let from_calendar_type = (calendar && calendar.name)?calendar.name.toLowerCase():'';
        let is_japanese_cal = (from_calendar_type === 'japanese');
        
        if(from_calendar_type!='' && from_calendar_type!='gregorian'){

            let isj = (from_calendar_type=='julian');

            let value = '';
            if (type === "s") {
                value = formatGregJulian($("#simpleDate").val(), isj);
            }else if (type === "f") {
                value = formatGregJulian($("#fTPQ").val(), isj) + " to " + formatGregJulian($("#fTAQ").val(), isj);
            }else  if (type === "p") {
                if($("#TPQ").val()!='' && $("#TAQ").val()!=''){
                    value = formatGregJulian($("#TPQ").val(), isj) + " to " + formatGregJulian($("#TAQ").val(), isj);
                }else if($("#PDB").val()!='' && $("#PDE").val()!=''){
                    value = formatGregJulian($("#PDB").val(), isj) + " to " + formatGregJulian($("#PDE").val(), isj);
                }
            }
            temporal.addObjForString("CL2", value);
            temporal.addObjForString("CLD", calendar.name);
        }else{
            temporal.removeObjForCode("CL2");
            temporal.removeObjForCode("CLD");
        }
    
		let fields = Temporal.getFieldsForType(type);
		for(let i =0; i< fields.length; i++) {
			let code = fields[i];
			let elem = $( "#" + type + code);
			if (elem.length == 0) {
				elem = $("#" + code);
			}
			if (elem.length == 0) {
				elem = $("input[name=" + type + code + "]:checked");  //radio button group
			}
			if (elem.length != 0) {
				switch (elem[0].type) {
					case "checkbox" :
						if (elem.is(":checked")) {
							temporal.addObjForString(code, "1");
						}else {
							temporal.removeObjForCode(code);
						}
						break;
					default :
						if (elem.val()) {
							let val = elem.val();
							if (code == "RNG") {
								val = "P" + val + $("#level").val();
							}
							if (code == "DEV" || code == "DVP" ||code == "DVN" ) {
								val = "P" + val + "Y";
							}
                            //convert to gregorian
                            if(elem.hasClass('withCalendarsPicker')){
                               
                                val = convertCLD(elem.val(), from_calendar_type);
                            }
							temporal.addObjForString(code, val);  // FIXME  this should validate input from the user.
						}else if (elem.length != 0) {
							temporal.removeObjForCode(code);
						}
				}
			}
		}
		if (type === "s") {

			let strDate = ($("#simpleTime").val());
			if (strDate && $("#tzone").val()) {
				let zone = $("#tzone").val().match(/^\s*(?:UTC|GMT)?\s*([\+|\-]?\d?\d:?(?:\d\d)?)?/)[1];
				if (zone) {
					strDate += " " + zone;
				}
			}
            let elem = $("#simpleDate");
            let dt = elem.val();
            if(togregorian){
                /*if(calendar && calendar.name!='gregorian'){
                    temporal.addObjForString("CL2", elem.val());
                }else{
                    temporal.removeObjForCode("CL2");
                }*/
                
               
                dt = convertCLD(elem.val(), from_calendar_type);
                
			}else if(is_japanese_cal || dt.indexOf('年') !== -1){
				dt = $.calendars.instance('japanese').japaneseToGregorianStr(dt); // translate japanese
			}
			if (strDate && dt) {
				strDate = dt + " " + strDate;
			}else{
				strDate = dt;
			}
			if (strDate) {
                temporal.addObjForString("DAT", strDate);
                   
                if($("#CIR1").prop('checked')){
                    temporal.addObjForString("CIR",'1');    
                }else if($("#CIR2").prop('checked')){
                    temporal.addObjForString("CIR",'2');    
                }else if($("#CIR3").prop('checked')){
                    temporal.addObjForString("CIR",'3');    
                }else{
                    temporal.removeObjForCode("CIR");    
                }
            }
		}

		if(togregorian && type === "f"){ // check earliest and latest estimates

			let tpq = temporal.getStringForCode('TPQ');
			let taq = temporal.getStringForCode('TAQ')
			if(window.hWin.HEURIST4.util.isempty(tpq) || window.hWin.HEURIST4.util.isempty(taq)){
				throw 'Both earliest and latest estimates are required';
			}

			if(new Date(tpq).getTime() >= new Date(taq).getTime()){
				throw 'Earliest estimate needs to be before latest estimate';
			}
		}
		if(togregorian && type === "f" // change to date range format and add simple range flag
			&& !window.hWin.HEURIST4.util.isempty(temporal.getStringForCode('TPQ')) 
            && !window.hWin.HEURIST4.util.isempty(temporal.getStringForCode('TAQ'))){

        	temporal.setType("p");
        }

	};

    let calendar = null;
	let $eras_sel = null;

    function _initJqCalendar(temporal){

        let defaultDate = null;

        let calendar_type = temporal.getStringForCode("CLD");
        calendar_type = !calendar_type ? 'gregorian' : calendar_type.toLowerCase();

        fixCalendarPickerCMDs();

        let type = temporal.getType();
        if (!type) {
            return;
        }

        calendar = $.calendars.instance(calendar_type);

        let calendarsPicker = $.calendarsPicker || $.calendars.picker; //v2 or v1


        let calendar_options = {
            calendar: calendar,
            showOnFocus: false,

            dateFormat: 'yyyy-mm-dd',
            pickerClass: 'calendars-jumps',
			onShow: function($calendar, calendar_locale, config){

				let $ele = $(config.target);
				if($ele.length > 0 && calendar_locale.local.name.toLowerCase() === 'japanese'){ // Add eras dropdown to calendar

					let $year_dropdown = $($calendar.find('.calendars-month-year')[1]);
					let $era_sel = $eras_sel.clone().insertAfter($year_dropdown);

					function updateYearTitle(){ // update year's rollover

						let era_name = $era_sel.find(`option[value="${$era_sel.val()}"]`).text();
						if(window.hWin.HEURIST4.util.isempty(era_name)){
							$year_dropdown.attr('title', 'Change the year');
							return;
						}
						era_name = era_name.split(' (')[0];

						let cur_year = $year_dropdown.find('option[selected="selected"]').text();
						if(window.hWin.HEURIST4.util.isempty(cur_year)){
							$year_dropdown.attr('title', 'Change the year');
							return;
						}
						cur_year = cur_year.split(' (');
						cur_year[1] = cur_year[1].slice(0, -1);

						$year_dropdown.attr('title', `Change the year\n${era_name} ${cur_year[0]}\nGregorian year: ${cur_year[1]}`);
					}

					let current_era = !window.hWin.HEURIST4.util.isempty($ele.attr('data-era')) && $ele.attr('data-era') > 0 ? $ele.attr('data-era') : 0;

					$year_dropdown.find('option').each((idx, option) => {
						let year = $(option).text();
						$(option).text(`${idx+1} (${year})`);
					});

					let label = $eras_sel.find(`option[value="${current_era}"]`).text();
					$era_sel.val(current_era).attr('title', `Change the era\nCurrent era: ${label}`);
					updateYearTitle();

					$year_dropdown.on('change', () => { updateYearTitle(); });
					$era_sel.on('change', () => {
						// Update min + max dates

						let era = $era_sel.val();
						$ele.attr('data-era', era);
						let limits = calendar_locale.getEraLimits(era);
						let new_options = {
							minDate: calendar_locale.newDate(...limits[0]),
							maxDate: limits[1].length > 0 ? calendar_locale.newDate(...limits[1]) : ''
						};
						$ele.calendarsPicker('option', new_options);
					});
				}
			},
            onSelect: function(dates){ 
				_updateGeorgianDate(); 
                if($('#display-div').tabs('option', 'active') == _type2TabIndexMap["f"]){
	                _updateSimpleRange(true);
                }
            },
            renderer: $.extend({}, calendarsPicker.defaultRenderer,
                    {picker: calendarsPicker.defaultRenderer.picker.
                        replace(/\{link:prev\}/, '{link:prevJump}{link:prev}').
                        replace(/\{link:next\}/, '{link:nextJump}{link:next}')}),
            showTrigger: '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/cal.gif" alt="Popup" class="trigger">'
        };

        if(calendar_type == 'japanese' && !$eras_sel){ // first era dates
			setupEras();
        }

        $('.withCalendarsPicker').calendarsPicker(calendar_options);

        //change current calendar
        $('#selectCLD').on('change', function() {

            let new_calendar = $(this).val();
            if(!new_calendar) return;
            
            let old_calendar = calendar.name.toLowerCase();            

            calendar = $.calendars.instance(new_calendar);
            if(!calendar) return;

			let is_japanese_cal = new_calendar == 'japanese';
            if(is_japanese_cal && !$eras_sel){
				setupEras();
            }

            let changed_options = {
				calendar: calendar
            };
            
            //assign new calendar for all pickers
            $('.withCalendarsPicker').each(function() {

                changed_options['defaultDate'] = '';
                if($(this).val()!=''){
                    try{
                        //convert to new 
                       
                        changed_options['defaultDate'] = convertCLD($(this).val(), old_calendar, new_calendar);
                        $(this).val(changed_options['defaultDate']);
                    }catch(e){
						/* continue regardless of error */
                    }
                }

				if(is_japanese_cal){

					let era = 0;
					if(!window.hWin.HEURIST4.util.isempty(changed_options['defaultDate'])){
                        let date = calendar.japaneseToGregorian(changed_options['defaultDate']); // translate to gregorian
						era = calendar.getEraFromGregorian(date.year(), date.month(), date.day());
					}
					let limits = calendar.getEraLimits(era);
					$(this).attr('data-era', era);

					changed_options['minDate'] = calendar.newDate(...limits[0]);
					changed_options['maxDate'] = limits[1].length > 0 ? calendar.newDate(...limits[1]) : '';
				}

                $(this).calendarsPicker('option', changed_options);
            });
            
            _updateGeorgianDate();
        }); //end change calendar

        $('#selectCLD').val(calendar_type);
        $('#selectCLD').trigger('change');
    }

	function setupEras(){

		$eras_sel = $('<select>', {class: 'calendars-eras'});
		let options = calendar.getEras();
		for(let i = 0; i < options.length; i ++){
			window.hWin.HEURIST4.ui.addoption($eras_sel[0], i, options[i]);
		}
	}

    //
    // Convert date from Gregorian to Native and assign to input
    //
    let convertAndAssign = function($input, dateValue, calendar_type){
                    
            if(calendar_type.toLowerCase()!='gregorian'){
                let newvalue = convertCLD(dateValue, 'gregorian', calendar_type);
                if(newvalue!=''){
                    dateValue = newvalue;
                }
            }
            $input.val(dateValue);    
    }
    
    //
    //
    //
    let convertCLD = function(value, fromcal, tocal) {
        
        let newval = '';
        
        if(value!='' && value!=null && fromcal!=''){
            let tDate;
            let dformat = 'yyyy';
            let hasMonth = true;
            
            fromcal = fromcal.toLowerCase();
            tocal = tocal?tocal.toLowerCase():'gregorian';
            let fromCalendar = $.calendars.instance(fromcal);
            let cal_value = null;
            
            
            if(fromcal == 'japanese'){
                if(tocal == 'japanese'){
                    return value;    
                }
                //japanese->gregorian->native
                //try{
               
                //}catch($e){
                try{
                    cal_value = fromCalendar.japaneseToGregorian(value); // translate first to gregorian
                    value = `${cal_value.year()}-${cal_value.month()}-${cal_value.day()}`;
                    cal_value = null;
                   
                }catch($e3){
					/* continue regardless of error */
                }
                //}
                fromcal = 'gregorian';
                fromCalendar = $.calendars.instance(fromcal);
            }
            try{
                tDate = TDate.parse(value);    
            }catch($e){
                try{
                    dformat = 'yyyy-mm-dd';
                    cal_value = fromCalendar.parseDate(dformat, value);
                }catch($e2){
                    alert('Can not parse given date value '+value);
                }
            }
            
            if (cal_value==null && tDate && tDate.getYear()) {
                hasMonth = tDate.getMonth()>0;
                let hasDay  = tDate.getDay()>0;

                let month = hasMonth? tDate.getMonth(): 1;
                let day = hasDay? tDate.getDay(): 1;

                dformat = dformat + (hasMonth?'-mm':'');
                dformat = dformat + (hasDay?'-dd':'');

                cal_value = fromCalendar.newDate(tDate.getYear(),month, day);
            }
            
            if(cal_value){
                
                function __noNeedConvert(from, to){
                    let cc = ['taiwan','julian','gregorian'];
                    return (from==to) ||
                    (!hasMonth && (cc.indexOf(from)>=0 && cc.indexOf(to)>=0));
                }

                if(__noNeedConvert(fromcal, tocal)){
                   
                   
                   
                    //
                    newval = cal_value._calendar.formatDate(dformat, cal_value);
                }else{
                    
                    try{

                        if(fromcal=='gregorian' && tocal=='japanese'){
                            newval = $.calendars.instance('japanese').gregorianToJapaneseStr(cal_value);
                        }else{
                            
                            let toCalendar = $.calendars.instance(tocal=='japanese'?'gregorian':tocal);
                            let jd = cal_value._calendar.toJD(Number(cal_value.year()), Number(cal_value.month()), Number(cal_value.day()));
                            cal_value = toCalendar.fromJD(jd);  
                            if(tocal=='japanese'){
                                //native->gregorian->japanese
                                newval = $.calendars.instance('japanese').gregorianToJapaneseStr(cal_value);
                            }else{
                                newval = toCalendar.formatDate(dformat, cal_value);    
                            }
                        }
                        
                    }catch(err){
                        alert(err);
                    }
                }
            }
        }

        return newval;        
    }
    
    
    // mode 0 - to gregorian (no assign), 1 - to
/*    
    let convert = function($inpt, togregorian) {

		//current value
		let fromcal = $inpt.calendarsPicker('option', 'calendar');
		let tDate = null;
		let value = $inpt.val();
        
        if(value=='') return ''; 
        
        
		let toJapaneseStr = false;
		let cur_calendar = $('#selectCLD').val();

		if((fromcal.name.toLowerCase() == 'japanese' || cur_calendar == 'japanese') && value != ''){

			try{
				tDate = TDate.parse(value);
				toJapaneseStr = true;
			}catch($e){
				toJapaneseStr = value;
				value = fromcal.japaneseToGregorian(value); // translate first
				value = `${value.year()}-${value.month()}-${value.day()}`;
			}
		}

		tDate = TDate.parse(value);
		let dformat = 'yyyy';
		let hasMonth = true;
		if (tDate && tDate.getYear()) {
			hasMonth = tDate.getMonth()>0;
			let hasDay  = tDate.getDay()>0;

			let month = hasMonth? tDate.getMonth(): 1;
			let day = hasDay? tDate.getDay(): 1;

			dformat = dformat + (hasMonth?'-mm':'');
			dformat = dformat + (hasDay?'-dd':'');

			value = fromcal.newDate(tDate.getYear(),month, day);
		}

		toJapaneseStr = togregorian || cur_calendar == 'japanese' ? toJapaneseStr : false;

		if(cur_calendar == 'japanese' && toJapaneseStr === true){
			toJapaneseStr = $.calendars.instance('japanese').gregorianToJapaneseStr(value);
		}

		function noNeedConvert(from, to){
			let cc = ['taiwan','julian','gregorian'];
			return (from.name.toLowerCase()==to.name.toLowerCase()) ||
			(!hasMonth && (cc.indexOf(from.name.toLowerCase())>=0 && cc.indexOf(to.name.toLowerCase())>=0));
		}

		let newval = '';
		if(value){

			let tocalendar = togregorian ? $.calendars.instance('gregorian') :calendar;
			if(noNeedConvert(value._calendar, tocalendar)){
				if(togregorian){
					newval = $inpt.val();
				}else{
				
					newval = value;
					newval._calendar.local.name = tocalendar.local.name;
					newval._calendar.name  = tocalendar.local.name;
				}
			}else{
				try{
					let jd = value._calendar.toJD(Number(value.year()), Number(value.month()), Number(value.day()));
					newval = tocalendar.fromJD(jd);
				}catch(err){
					alert(err);
					if(togregorian){
						newval = '';
					}else{
						$inpt.val('');
					}
				}

				if(togregorian){
					newval = tocalendar.formatDate(dformat, newval);
				}else {
					$inpt.val( tocalendar.formatDate(dformat, newval) );
				}
			}
		}else{
			$inpt.val('');
		}

		if(toJapaneseStr !== false){
			$inpt.val(toJapaneseStr);
		}

		return newval;
    };
*/    
	//public members
	let that = {
			originalInputString : "",
			origTemporal : null,
			curTemporal : null,
			name : "App",
			//getTabView : function () {return _tabView; },
			close : function () {
				try{
					_updateTemporalFromUI(that.curTemporal);
				}catch(e) {	// save string in COM field and keep an empty simple date temporal
					alert(e);
					return;
				}
				let validity = Temporal.checkValidity(that.curTemporal);
				if (validity[0]) {  // valid temporal
					if (validity[2]) { //some extra code fields, so remove them
						for (let i=0; i<validity[2].length; i++) {
							that.curTemporal.removeObjForCode(validity[2][i]);
						}
					}
					window.close( that.curTemporal.toString());
				}else{
					let msg = "";
					for (let i = 0; i < validity[1].length; i++) {
					 if (!msg){
						msg = Temporal.getStringForCode(validity[1][i]);
					 }else{
						msg += ", " + Temporal.getStringForCode(validity[1][i]);
					 }
					}
					msg = msg ? "The current temporal is missing the " + msg + " value(s)." : "";
					msg +=  validity[3] ? " " + validity[3] : "";
                    
                    alert(msg);
					/*
                    if (!confirm( msg +
							"Would you like to continue working? Press cancel to reset to the original string.")) {
						window.close(that.originalInputString);
					}else{
						window.close("");
					}*/
				}

			},
			cancel : function () {
				window.close(that.originalInputString);
			},
			getClass : function () {
				return _className;
			},
			isA: function(strClass) {
				if(strClass === _className) return true;
				return false;
			}
	};

	_init();
	return that;
})();

