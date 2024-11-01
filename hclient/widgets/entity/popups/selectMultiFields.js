/**
*  Quick addition of fields to a record type
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Brandon McKay   <blmckay13@gmail.com>
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

function HMultiSelect(){

	let rtyID = null;	// Current Record Type ID

	let assigned_fields = [];	// List of newly assigned fields
	let selected_fields = [];	// List of checked options

	let all_fields = [];	// Base Fields [ [ dty id, dty name, [rst name 1, rst name 2, ...] ], ... ]
	
	/*
	 * Assign more standard names to dty_Types
	 *
	 * Param: (string) type => detail type's Type
	 * 
	 * Return: (string) Type Name
	 */
	function getTypeName(type) {

		if(window.hWin.HEURIST4.util.isempty(type)){
			return "Unknown";
		}

		switch (type) {
			case 'resource':
				type = 'Record pointer';

				break;
			case 'relmarker':
				type = 'Relationship marker';
				
				break;
			case 'freetext':
				type = 'Single line Text';
				
				break;
			case 'blocktext':
				type = 'Multi-line Text';
				
				break;
			case 'float':
				type = 'Number';
				
				break;
			case 'enum':
				type = 'Terms list';
				
				break;
			case 'date':
				type = 'Date/Time';
				
				break;
			case 'separator':
				type = 'Tab header';
				
				break;
			case 'geo':
				type = 'Geospatial';

				break;
			case 'calculated':
				type = 'Calculated';

				break;
			default:
				type = type = type.charAt(0).toUpperCase() + type.slice(1);

				break;
		}

		return type;
	}

	/*
	 *	Alphabetic sorting, sortby index 1
	 */

	function alphabetic_sort(a, b){

		if(a.constructor === Array && b.constructor === Array) {
			a = a[1];
			b = b[1];
		}else if(a.constructor === Array || b.constructor === Array) {
			return 0;
		}

		let min_len = Math.min(a.length, b.length);
		let i = 0;

		for(; i < min_len; i++){

			let c = a[i].toUpperCase();
            let d = b[i].toUpperCase();

            if (c < d) {
                return -1;
            }
            if (c > d) {
                return 1;
            }
        }

        if(window.hWin.HEURIST4.util.isempty(a[i])){
        	return -1;
        }else if(window.hWin.HEURIST4.util.isempty(b[i])){
        	return 1;
        }else{
        	return 0;
        }

	}

	/*
	 *	Search 2d array of strings
	 *
	 *	Param:
	 *		(string) needle => searching for
	 *		(string) haystack => searching in
	 *		(boolean) check_partial => whether to check for a partial check
	 *
	 *	Return:
	 *		(boolean || int) whether the needle is in the haystack
	 */

	function isInArray(needle, haystack, check_partial) {

		let idx = haystack.indexOf(needle);

		if(!check_partial){
			return idx >= 0;
		}

		if(idx == -1){ // Check for partial match
			for(let i in haystack){
				idx = haystack[i].indexOf(needle);

				if(idx >= 0){
					return idx;
				}
			}
		}else{ // Exact match
			return true;
		}

		return false;
	}

	/* 
	 *	Remove newline chars and br tags from string
	 *	
	 *	Param:
	 *		(string) text => Text to be stripped of newline values
	 *
	 * 	Return: new string stripped of newlines
	 */
	function strip_newlines(text){
		return text.replaceAll(/\n|\r|<br>/g, ' ');
	}

	/*
	 * Get list of already assigned fields, to disable from master list
	 *
	 * Param: (int) rty_ID => current record type's ID
	 *
	 * Return: VOID
	 */

	function getAssignedFields(rty_ID) {

		let recset = $Db.rst(rty_ID);

		if(window.hWin.HEURIST4.util.isempty(recset)){ return; } // skip if there are no base fields

		recset.each2(function(fID, fields){ // proceed through all fields and record each id
			assigned_fields.push(fID);
		});
	}

	/*
	 * Retrieve all existing base fields, grey out/disable options already assigned
	 *
	 * Param: NONE
	 *
	 * Return: VOID
	 */

	function populateBaseFields() {
		let a_list = $('.tabs-list');
		let tabs_container = $('.tabs');

		$Db.dtg().each2(function(gID, group){
			let arr = [];

			if(group['dtg_Name'] == 'Trash') { return; }

			// Create Grouping
			a_list.append('<li class="tabs-items"><a href="#'+ gID +'" class="no-overflow-item tabs-text">'+ group['dtg_Name'] +'</a></li>');

			let tab_page = '<div id="'+ gID +'" style="border:1px solid lightgrey;background:#C9BFD4;height:540px;">'
				+ '<div class="tabs-desc no-overflow-item">'+ group['dtg_Description'] +'</div><hr style="margin-bottom:5.5px;"/><div class="field-group">';

			// Get all Base Fields belonging to this group
			$Db.dty().each2(function(dID, field){

			    if(field['dty_DetailTypeGroupID'] == gID){
			    	let type = getTypeName(field['dty_Type']);

			    	arr.push([dID, field['dty_Name'], type, field['dty_HelpText']]);
			    }
			});

			arr.sort(alphabetic_sort);

			/*
			arr:
				0 => ID
				1 => Label/Name
				2 => Type
				3 => Help Text/Additional Info
			*/
			// Display Base Fields
			for(let i = 0; i < arr.length; i++){

		        tab_page = tab_page + '<div class="field-container">';

		        if(!isInArray(arr[i][0], assigned_fields, false)){
			        tab_page = tab_page + '<input type="checkbox" data-id="'+ arr[i][0] +'">';
		        }
		        else{
		        	tab_page = tab_page + '<input type="checkbox" data-id="'+ arr[i][0] +'" disabled checked="checked">';		        	
		        }

		        tab_page = tab_page 
		        	+ '<div class="field-item no-overflow-item" title="'+ arr[i][1] +'">'+ arr[i][1] +'</div>'
		        	+ '<div class="field-item no-overflow-item" title="'+ arr[i][2] +'">'+ arr[i][2] +'</div>'
		        	+ '<div class="field-item no-overflow-item" title="'+ arr[i][3] +'">'+ strip_newlines(arr[i][3]) +'</div></div>';

			}

			tab_page = tab_page + '</div></div>';

			tabs_container.append(tab_page);
		});

		tabs_container.on('click', function(e){

			let ele = $(e.target);

			if(!ele.is('.field-group, .tabs-desc, input, div[role="tabpanel"], a, ul, li')){
				let cb = $(ele.parent('div').find('input')[0]);
				
				if(!cb.prop('disabled')){
					cb.trigger('click');
				}
			}
		});
	}

	/*
	 * Retrieve full list of checked fields to send back
	 *
	 * Param: NONE
	 *
	 * Return: VOID
	 */

	function getCheckedFields(){
		let tabs_container = $('.tabs');

		let checked_opts = tabs_container.find('input:checked').not(':disabled');
		let cnt = checked_opts.length;

		for(let i = 0; i < cnt; i++){
			selected_fields.push($(checked_opts[i]).attr('data-id')); // Get each field's ID
		}
	}

	/*
	 * Base Field searching and displaying results
	 *
	 * Param: NONE
	 *
	 * Return: VOID
	 */

	function searchBaseField() {

		let search_field = $('#field_search');
		let search_container = $('.field_search_container');
		let result_container = $('#field_result');

		let searched = search_field.val().toLowerCase();

		let has_result = false;

		if(search_field.length == 0) {
			return false;
		}

		if(result_container.length == 0) { // Create result container

			result_container = $('<div id="field_result">').appendTo(search_container);

			$(document).on('click', function(e){
				if(!$(e.target).is('#field_result') && $(e.target).parents('#field_result').length == 0){
					result_container.hide();
				}
			});
		}

		// Begin Search
		if(searched.length > 2){

			result_container.empty();

			// For instances where the entered value has an exact match
			let first_entry = $('<div class="no-overflow-item">').appendTo(result_container);

			// Ensure there are fields to compare against
			if(all_fields.length > 0) {

				for(const dty_field of all_fields){

					const name = dty_field[1];
					const id = dty_field[0];

					// Check if there is a customised instance with the search string
					const in_other_array = isInArray(searched, dty_field[2], true);

					if(!isInArray(id, assigned_fields, false) && (name.toLowerCase().indexOf(searched) >= 0 || in_other_array)){

						let main_ele;

						if(name.toLowerCase == searched || in_other_array == true){
							main_ele = first_entry;
						}else{
							main_ele = $('<div class="no-overflow-item">').appendTo(result_container);
						}

						// Add original base field for search
						main_ele
						.attr({'d-id': id, 'title': name})
						.text(name)
						.on('click', function(e){

							let id = $(e.target).attr('d-id');
							let name = $(e.target).text();

							let cb = $('.tabs').find('input[data-id="'+ id +'"]');

							if(cb.length > 0) {
								cb.prop('checked', true);

								window.hWin.HEURIST4.msg.showMsgFlash('Checked ' + name, 5000);
							}else{
								window.hWin.HEURIST4.msg.showMsgErr({
									message: `An error has occurred with the selection of base field ${name} (${id})`,
									error_title: 'Invalid base field',
									status: window.hWin.ResponseStatus.UNKNOWN_ERROR									
								});
							}

							result_container.hide();
						});

						for(const rst_name of dty_field[2]) {

							let sub_ele = $('<div class="no-overflow-item sub-text">').appendTo(result_container);

							// Add customised version of base field
							sub_ele
							.attr({'d-id': id, 'title': name + '(' + rst_name + ')', 'd-name': name})
							.html('&nbsp;' + rst_name)
							.on('click', function(e){

								let id = $(e.target).attr('d-id');
								let name = $(e.target).attr('d-name');
								let sel_name = $(e.target).text();

								let cb = $('.tabs').find('input[data-id="'+ id +'"]');

								if(cb.length > 0) {
									cb.prop('checked', true);

									window.hWin.HEURIST4.msg.showMsgFlash('Checked ' + name + ' (' + sel_name + ')', 5000);
								}else{
									window.hWin.HEURIST4.msg.showMsgErr({
										message: `An error has occurred with the selection of base field ${sel_name} (${id} => ${name})`,
										error_title: 'Invalid base field',
										status: window.hWin.ResponseStatus.UNKNOWN_ERROR
									});
								}

								result_container.hide();
							});
						}

						result_container.append('<div style="margin-bottom: 5px;">----------------------------------------</div>');

						has_result = true;
					}
				}
			}

			if(has_result) {
				result_container
				.css({
					'width': '530px', 
					'position': 'absolute',
					'top': '20px',
					'right': 0 
				})
				.show();
			}else{
				result_container.hide();
			}

		}else{
			result_container.hide();
		}
	}

	function setupVariables() {

		let rectypes = $Db.rty().getIds();
		let idx = rectypes.indexOf(rtyID);

		if(idx >= 0) { rectypes.splice(idx, 1); }

		rectypes.sort((a, b) => {

			a = $Db.rty(a, 'rty_Name');
			b = $Db.rty(b, 'rty_Name');

			return alphabetic_sort(a, b);
		});

		all_fields = $Db.getBaseFieldInstances(rectypes, 0, 'all', []);
	}

	function _setupElements() {

		$('.tabs').tabs({
			beforeActivate: function(e, ui){
				if(window.hWin.HEURIST4.util.isempty(ui.newPanel) || ui.newPanel.length == 0) {
					e.preventDefault();
				}
			}
		});

		// Initialise Buttons
		$('#btnAddSelected')
		.button({label:'Insert selected fields'})
		.addClass('ui-button-action')
		.on('click', function(e){
			getCheckedFields();

			if(window.hWin.HEURIST4.util.isempty(selected_fields)){
				window.hWin.HEURIST4.msg.showMsgErr({
					message: 'No fields have been selected',
					error_title: 'Missing fields'
				});
				return;
			}
			else{
				window.close(selected_fields);
			}
		});

		$('#btnClose').button({label:'Close'}).on('click', 
			function(e){
				window.close();
			}
		);

		// Initialise Text Searching
		$('#field_search').on({'keyup': searchBaseField});
	}

	function _setupStyling() {

		$('#btnAddSelected').css({'font-size':'1em', 'float':'right', 'color':'white', 'background':'#3D9946 0% 0% no-repeat padding-box'});

		$('#btnClose').css({'font-size':'1em', 'float':'right'});
	}

	/*
	 * Initialise function, called from within html
	 *
	 * Param: NONE
	 *
	 * Return: VOID
	 */
	function _init() {
		rtyID = window.hWin.HEURIST4.util.getUrlParameter('rtyID', location.search);

		if(window.hWin.HEURIST4.util.isempty(rtyID)){
			window.hWin.HEURIST4.msg.showMsgErr({
				message: 'A record type is required to use this tool',
				error_title: 'Missing record type'
			});
			window.close();
		}

		setupVariables();

		getAssignedFields(rtyID);

		populateBaseFields();
	}

	let that = {

		init: function(){
			_init();
		},

		setupElements: function(){
			_setupElements();
		},

		setupStyling: function(){
			_setupStyling();
		}
	};

	return that;
}