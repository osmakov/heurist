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

/**
 * @class HRapidFieldAdditions
 * @classdesc Simple interface to insert several fields into the record structure at once
 * 
 * @property {integer} rty_ID - Current Record Type ID
 * @property {array} assigned_fields - List of newly assigned fields
 * @property {array} selected_fields - List of checked options
 * @property {array} all_fields - Base Fields [ [ dty id, dty name, [rst name 1, rst name 2, ...] ], ... ]
 * @property {jQuery} tab_container - Container for tabs widget
 * @property {jQuery} btn_action - Action button
 * @property {jQuery} btn_close - Cancel/close button
 * 
 * @function setupVariables - Initialise class properties
 * @function setupElements - Initialise elements
 * @function setupStyling - Add styling to elements
 * @function getAssignedFields - Get fields already in record structure
 * @function populateBaseFields - Populate and initialise tabs
 * @function getCheckedFields - Get list of selected fields to add
 * @function searchBaseField - Search for field using user input
 * @function getTypeName - Field type to plain text
 * @function alphabeticSort - Alphabetic sorting on a specific 2d array
 * @function stripNewlines - Remove newlines from text
 * @function isInArray - Search array of strings
 */
class HRapidFieldAdditions{

	rty_ID = null;

	assigned_fields = [];
	selected_fields = [];

	all_fields = [];

	tab_container = null;
	btn_action = null;
	btn_close = null;

	constructor(_rty_ID){

		if(window.hWin.HEURIST4.util.isempty(_rty_ID)){
			window.hWin.HEURIST4.msg.showMsgErr({
				message: 'A record type is required to use this tool',
				error_title: 'Missing record type'
			});

			window.close();
			return;
		}

		this.rty_ID = _rty_ID;

		this.setupVariables();

		this.getAssignedFields();

		this.populateBaseFields();

		this.setupElements();

		this.setupStyling();
	}

	setupVariables(){

		let rectypes = $Db.rty().getIds();
		let idx = rectypes.indexOf(this.rty_ID);

		if(idx >= 0) { rectypes.splice(idx, 1); }

		rectypes.sort((a, b) => {

			a = $Db.rty(a, 'rty_Name');
			b = $Db.rty(b, 'rty_Name');

			return this.alphabeticSort(a, b);
		});

		this.all_fields = $Db.getBaseFieldInstances(rectypes, 0, 'all', []);

		this.tab_container = $('.tabs');

		this.btn_action = $('#btnAddSelected');
		this.btn_close = $('#btnClose');
	}

	setupElements(){

		this.tab_container.tabs({
			beforeActivate: function(e, ui){
				if(window.hWin.HEURIST4.util.isempty(ui.newPanel) || ui.newPanel.length == 0) {
					e.preventDefault();
				}
			}
		});

		// Initialise Buttons
		this.btn_action.addClass('ui-button-action')
			.button({label:'Insert selected fields'})
			.on('click', () => {

				this.getCheckedFields();

				if(window.hWin.HEURIST4.util.isempty(this.selected_fields)){
					window.hWin.HEURIST4.msg.showMsgErr({
						message: 'No fields have been selected',
						error_title: 'Missing fields'
					});
					return;
				}
				else{
					window.close(this.selected_fields);
				}
			});

		this.btn_close.button({label:'Close'}).on('click', () => {window.close();});

		// Initialise Text Searching
		$('#field_search').on('keyup', () => { this.searchBaseField(); });
	}

	setupStyling(){

		this.btn_action.css({'font-size':'1em', 'float':'right', 'color':'white', 'background':'#3D9946 0% 0% no-repeat padding-box'});

		this.btn_close.css({'font-size':'1em', 'float':'right'});
	}

	/**
	 * Get list of already assigned fields, to disable from master list
	 */
	getAssignedFields(){

		let recset = $Db.rst(this.rty_ID);

		if(window.hWin.HEURIST4.util.isempty(recset)){ return; } // skip if there are no base fields

		this.assigned_fields = recset.getIds();
	}

	/**
	 * Retrieve all existing base fields, grey out/disable options already assigned
	 */
	populateBaseFields(){

		let that = this;
		let a_list = $('.tabs-list');

		$Db.dtg().each2(function(gID, group){
			let arr = [];

			if(group['dtg_Name'] == 'Trash') { return; }

			// Create Grouping
			a_list.append(`<li class="tabs-items"><a href="#${gID}" class="no-overflow-item tabs-text">${group['dtg_Name']}</a></li>`);

			let tab_page = `<div id="${gID}" style="border:1px solid lightgrey;background:#C9BFD4;height:540px;">`
				+ `<div class="tabs-desc no-overflow-item">${group['dtg_Description']}</div><hr style="margin-bottom:5.5px;"/><div class="field-group">`;

			// Get all Base Fields belonging to this group
			$Db.dty().each2(function(dID, field){

				if(field['dty_DetailTypeGroupID'] == gID){
					let type = that.getTypeName(field['dty_Type']);

					arr.push([dID, field['dty_Name'], type, field['dty_HelpText']]);
				}
			});

			arr.sort(that.alphabeticSort);

			/*
			arr:
				0 => ID
				1 => Label/Name
				2 => Type
				3 => Help Text/Additional Info
			*/
			// Display Base Fields
			for(let i = 0; i < arr.length; i++){

				tab_page += '<div class="field-container">';

				let is_checked = that.isInArray(arr[i][0], that.assigned_fields, false);
				tab_page += `<input type="checkbox" data-id="${arr[i][0]}" ${is_checked ? 'disabled checked="checked"' : ''}>`;

				tab_page += `<div class="field-item no-overflow-item" title="${arr[i][1]}">${arr[i][1]}</div>`
					+ `<div class="field-item no-overflow-item" title="${arr[i][2]}">${arr[i][2]}</div>`
					+ `<div class="field-item no-overflow-item" title="${arr[i][3]}">${that.stripNewlines(arr[i][3])}</div></div>`;

			}

			tab_page += '</div></div>';

			that.tab_container.append(tab_page);
		});

		this.tab_container.on('click', function(e){

			let ele = $(e.target);

			if(ele.is('.field-group, .tabs-desc, input, div[role="tabpanel"], a, ul, li')){
				return;
			}

			let cb = $(ele.parent('div').find('input')[0]);
			
			if(!cb.prop('disabled')){
				cb.trigger('click');
			}
		});
	}

	/**
	 * Retrieve full list of checked fields to send back
	 */
	getCheckedFields(){

		let checked_opts = this.tab_container.find('input:checked').not(':disabled');
		let cnt = checked_opts.length;

		for(let i = 0; i < cnt; i++){
			this.selected_fields.push($(checked_opts[i]).attr('data-id')); // Get each field's ID
		}
	}

	/**
	 * Base Field searching and displaying results
	 */
	searchBaseField(){

		let search_field = $('#field_search');
		let search_container = $('.field_search_container');
		let result_container = $('#field_result');

		let searched = search_field.val().toLowerCase();

		let has_result = false;

		if(search_field.length == 0){
			return false;
		}

		if(result_container.length == 0){ // Create result container

			result_container = $('<div>', {id: 'field_result'}).appendTo(search_container);

			$(document).on('click', function(e){
				if(!$(e.target).is('#field_result') && $(e.target).parents('#field_result').length == 0){
					result_container.hide();
				}
			});
		}

		if(searched.length <= 2){
			result_container.hide();
			return;
		}

		// Begin Search
		result_container.empty();

		// For instances where the entered value has an exact match
		let first_entry = $('<div>', {class: 'no-overflow-item'}).appendTo(result_container);

		// Ensure there are fields to compare against
		if(this.all_fields.length == 0){
			result_container.hide();
			return;
		}

		for(const dty_field of this.all_fields){

			const name = dty_field[1];
			const id = dty_field[0];

			// Check if there is a customised instance with the search string
			const in_other_array = this.isInArray(searched, dty_field[2], true);

			if(this.isInArray(id, this.assigned_fields, false) || (name.toLowerCase().indexOf(searched) == -1 && !in_other_array)){
				continue;
			}

			let main_ele;

			if(name.toLowerCase == searched || in_other_array == true){
				main_ele = first_entry;
			}else{
				main_ele = $('<div>', {class: 'no-overflow-item'}).appendTo(result_container);
			}

			// Add original base field for search
			main_ele
				.attr({'d-id': id, title: name})
				.text(name)
				.on('click', (e) => {

					let id = $(e.target).attr('d-id');
					let name = $(e.target).text();

					let cb = this.tab_container.find(`input[data-id="${id}"]`);

					if(cb.length > 0) {
						cb.prop('checked', true);

						window.hWin.HEURIST4.msg.showMsgFlash(`Checked ${name}`, 5000);
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

				let sub_ele = $('<div>', {class: 'no-overflow-item sub-text'}).appendTo(result_container);

				// Add customised version of base field
				sub_ele
				.attr({'d-id': id, title: `${name} (${rst_name})`, 'd-name': name})
				.html(`&nbsp;${rst_name}`)
				.on('click', (e) => {

					let id = $(e.target).attr('d-id');
					let name = $(e.target).attr('d-name');
					let sel_name = $(e.target).text();

					let cb = this.tab_container.find(`input[data-id="${id}"]`);

					if(cb.length > 0) {
						cb.prop('checked', true);

						window.hWin.HEURIST4.msg.showMsgFlash(`Checked ${name} (${sel_name})`, 5000);
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
	}

	/**
	 * Assign more standard names to dty_Types
	 *
	 * @param {string} type - detail type's Type
	 * 
	 * @returns {string} Type Name
	 */
	getTypeName(type){

		if(window.hWin.HEURIST4.util.isempty(type)){
			return "Unknown";
		}

		switch (type){

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

	/**
	 * Alphabetic sorting, sortby index 1
	 */
	alphabeticSort(a, b){

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

	/**
	 * Remove newline chars and br tags from string
	 *
	 * @param {string} text - Text to be stripped of newline values
	 *
	 * @returns {string} string stripped of newlines
	 */
	stripNewlines(text){
		return text.replaceAll(/\n|\r|<br>/g, ' ');
	}

	/**
	 * Search 2d array of strings
	 *
	 * @param {string} needle - searching for
	 * @param {string} haystack - searching in
	 * @param {boolean} check_partial - whether to check for a partial match
	 * 
	 * @returns {boolean || integer} whether the needle is in the haystack
	 */
	isInArray(needle, haystack, check_partial){

		let idx = haystack.indexOf(needle);

		if(!check_partial){
			return idx >= 0;
		}

		if(idx >= 0){
			return true;
		}

		// Check for partial match
		for(let i in haystack){
			idx = haystack[i].indexOf(needle);

			if(idx >= 0){
				return idx;
			}
		}

		return false;
	}
}